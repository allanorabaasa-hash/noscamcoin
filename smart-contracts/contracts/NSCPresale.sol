// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import {IERC20} from "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import {SafeERC20} from "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import {Ownable} from "@openzeppelin/contracts/access/Ownable.sol";
import {ReentrancyGuard} from "@openzeppelin/contracts/utils/ReentrancyGuard.sol";

/// @dev Minimal Chainlink price feed interface (BNB/USD, 8 decimals).
interface IPriceFeed {
    function latestRoundData()
        external
        view
        returns (uint80 roundId, int256 answer, uint256 startedAt, uint256 updatedAt, uint80 answeredInRound);
}

/**
 * @title  NSC Presale
 * @notice Buyers send BNB and instantly receive NSC at the USD price set by the owner.
 *         - Price is set in US dollars (priceUsd, 18 decimals: $0.0042 = 4200000000000000).
 *         - The live BNB/USD rate comes from Chainlink, so buyers always get $-correct amounts.
 *         - The BNB goes straight to the treasury wallet; the contract never holds buyers' money.
 *         - NSC comes from tokens the owner deposits here (e.g. the 400M presale allocation).
 *         - IMPORTANT: this contract must be tax-exempt on the token (setTaxExempt(presale, true)),
 *           otherwise buyers receive 3% less than the quote.
 */
contract NSCPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public immutable token;
    address payable public treasury;

    IPriceFeed public priceFeed;      // Chainlink BNB/USD
    uint256 public priceUsd;          // USD price of 1 NSC, 18 decimals ($0.0042 = 4200000000000000)
    uint256 public maxPriceAge = 2 hours; // reject stale oracle prices
    uint256 public minBuyWei;         // smallest purchase in BNB (wei)
    uint256 public maxPerWalletWei;   // total BNB one wallet may spend (0 = no limit)
    bool public saleActive;

    uint256 public totalSold;         // NSC sold (18 decimals)
    uint256 public totalRaised;       // BNB raised (wei)
    mapping(address => uint256) public spentBy;

    event Bought(address indexed buyer, uint256 bnbPaid, uint256 nscReceived);
    event PriceUpdated(uint256 priceUsd);
    event PriceFeedUpdated(address feed, uint256 maxPriceAge);
    event SaleActiveSet(bool active);
    event LimitsUpdated(uint256 minBuyWei, uint256 maxPerWalletWei);
    event TreasuryUpdated(address treasury);

    error SaleNotActive();
    error BelowMinimum();
    error AboveWalletLimit();
    error NotEnoughTokensLeft();
    error ZeroValue();
    error TransferFailed();
    error BadOraclePrice();
    error RenounceDisabled();

    constructor(address _token, address payable _treasury, address _priceFeed, uint256 _priceUsd)
        Ownable(msg.sender)
    {
        if (_token == address(0) || _treasury == address(0) || _priceFeed == address(0) || _priceUsd == 0) {
            revert ZeroValue();
        }
        token = IERC20(_token);
        treasury = _treasury;
        priceFeed = IPriceFeed(_priceFeed);
        priceUsd = _priceUsd;
        minBuyWei = 0.001 ether;
        emit PriceUpdated(_priceUsd);
    }

    // ---------------------------------------------------------------- buying

    /// @notice Live BNB price in USD, 18 decimals (e.g. $590.12 = 590120000000000000000).
    function bnbUsd() public view returns (uint256) {
        (, int256 answer, , uint256 updatedAt, ) = priceFeed.latestRoundData();
        if (answer <= 0 || block.timestamp - updatedAt > maxPriceAge) revert BadOraclePrice();
        return uint256(answer) * 1e10; // Chainlink BNB/USD has 8 decimals
    }

    /// @notice How much NSC (18 decimals) a given BNB amount buys at the current $ price.
    function quote(uint256 bnbWei) public view returns (uint256) {
        return (bnbWei * bnbUsd()) / priceUsd;
    }

    /// @notice NSC still available to buy.
    function tokensLeft() public view returns (uint256) {
        return token.balanceOf(address(this));
    }

    function buy() public payable nonReentrant {
        if (!saleActive) revert SaleNotActive();
        if (msg.value < minBuyWei) revert BelowMinimum();
        if (maxPerWalletWei != 0 && spentBy[msg.sender] + msg.value > maxPerWalletWei) revert AboveWalletLimit();

        uint256 amount = quote(msg.value);
        if (amount > tokensLeft()) revert NotEnoughTokensLeft();

        spentBy[msg.sender] += msg.value;
        totalSold += amount;
        totalRaised += msg.value;

        token.safeTransfer(msg.sender, amount);

        (bool ok, ) = treasury.call{value: msg.value}("");
        if (!ok) revert TransferFailed();

        emit Bought(msg.sender, msg.value, amount);
    }

    /// @notice Sending BNB directly to the contract also buys.
    receive() external payable {
        buy();
    }

    // ---------------------------------------------------------------- owner controls

    /// @notice Set the NSC price in USD (18 decimals). $0.0042 => 4200000000000000
    function setPriceUsd(uint256 _priceUsd) external onlyOwner {
        if (_priceUsd == 0) revert ZeroValue();
        priceUsd = _priceUsd;
        emit PriceUpdated(_priceUsd);
    }

    function setPriceFeed(address _priceFeed, uint256 _maxPriceAge) external onlyOwner {
        if (_priceFeed == address(0) || _maxPriceAge == 0) revert ZeroValue();
        priceFeed = IPriceFeed(_priceFeed);
        maxPriceAge = _maxPriceAge;
        emit PriceFeedUpdated(_priceFeed, _maxPriceAge);
    }

    function setSaleActive(bool active) external onlyOwner {
        saleActive = active;
        emit SaleActiveSet(active);
    }

    function setLimits(uint256 _minBuyWei, uint256 _maxPerWalletWei) external onlyOwner {
        minBuyWei = _minBuyWei;
        maxPerWalletWei = _maxPerWalletWei;
        emit LimitsUpdated(_minBuyWei, _maxPerWalletWei);
    }

    function setTreasury(address payable _treasury) external onlyOwner {
        if (_treasury == address(0)) revert ZeroValue();
        treasury = _treasury;
        emit TreasuryUpdated(_treasury);
    }

    /// @notice Disabled on purpose: without an owner the price, pause and unsold tokens
    ///         would be locked forever. Use transferOwnership to hand control to another wallet.
    function renounceOwnership() public pure override {
        revert RenounceDisabled();
    }

    /// @notice Take back unsold NSC (e.g. when the presale ends).
    function withdrawUnsold(address to, uint256 amount) external onlyOwner {
        token.safeTransfer(to, amount);
    }
}
