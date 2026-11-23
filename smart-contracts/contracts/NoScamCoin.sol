// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import {ERC20} from "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import {Ownable} from "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title  NOSCAM Coin (N$C)
 * @notice BEP-20 token for BNB Chain.
 *         - Fixed supply: 1,000,000,000 tokens, all minted once at deploy. No mint function exists.
 *         - Transfer tax (default 3%): 1% burned, 1% to the Victim Fund wallet, 1% to the Liquidity wallet.
 *         - The tax can only ever be LOWERED below the 3% hard cap, never raised past it,
 *           so the owner cannot turn the token into a honeypot.
 *         - No blacklist, no pause, no max-wallet: holders can always sell.
 */
contract NoScamCoin is ERC20, Ownable {
    uint256 public constant TOTAL_SUPPLY = 1_000_000_000 * 1e18;
    uint256 public constant MAX_TOTAL_TAX_BPS = 300; // 3% hard cap (100 bps = 1%)
    uint256 private constant BPS = 10_000;

    uint256 public burnBps = 100;      // 1%
    uint256 public victimFundBps = 100; // 1%
    uint256 public liquidityBps = 100;  // 1%

    address public victimFundWallet;
    address public liquidityWallet;

    mapping(address => bool) public isTaxExempt;

    event TaxesUpdated(uint256 burnBps, uint256 victimFundBps, uint256 liquidityBps);
    event WalletsUpdated(address victimFundWallet, address liquidityWallet);
    event TaxExemptSet(address indexed account, bool exempt);

    error ZeroAddress();
    error TaxTooHigh();

    constructor(address _victimFundWallet, address _liquidityWallet)
        ERC20("NOSCAM Coin", "NSC")
        Ownable(msg.sender)
    {
        if (_victimFundWallet == address(0) || _liquidityWallet == address(0)) revert ZeroAddress();
        victimFundWallet = _victimFundWallet;
        liquidityWallet = _liquidityWallet;

        isTaxExempt[msg.sender] = true;
        isTaxExempt[_victimFundWallet] = true;
        isTaxExempt[_liquidityWallet] = true;

        _mint(msg.sender, TOTAL_SUPPLY);
    }

    // ---------------------------------------------------------------- tax logic

    function _update(address from, address to, uint256 value) internal override {
        // Mints, burns and exempt addresses pay no tax.
        if (from == address(0) || to == address(0) || isTaxExempt[from] || isTaxExempt[to]) {
            super._update(from, to, value);
            return;
        }

        uint256 burnAmt = (value * burnBps) / BPS;
        uint256 victimAmt = (value * victimFundBps) / BPS;
        uint256 liqAmt = (value * liquidityBps) / BPS;

        if (burnAmt > 0) super._update(from, address(0), burnAmt); // real burn: totalSupply goes down
        if (victimAmt > 0) super._update(from, victimFundWallet, victimAmt);
        if (liqAmt > 0) super._update(from, liquidityWallet, liqAmt);

        super._update(from, to, value - burnAmt - victimAmt - liqAmt);
    }

    // ---------------------------------------------------------------- owner controls

    /// @notice Change the tax split. Total can never exceed 3%.
    function setTaxes(uint256 _burnBps, uint256 _victimFundBps, uint256 _liquidityBps) external onlyOwner {
        if (_burnBps + _victimFundBps + _liquidityBps > MAX_TOTAL_TAX_BPS) revert TaxTooHigh();
        burnBps = _burnBps;
        victimFundBps = _victimFundBps;
        liquidityBps = _liquidityBps;
        emit TaxesUpdated(_burnBps, _victimFundBps, _liquidityBps);
    }

    function setWallets(address _victimFundWallet, address _liquidityWallet) external onlyOwner {
        if (_victimFundWallet == address(0) || _liquidityWallet == address(0)) revert ZeroAddress();
        victimFundWallet = _victimFundWallet;
        liquidityWallet = _liquidityWallet;
        emit WalletsUpdated(_victimFundWallet, _liquidityWallet);
    }

    /// @notice Exempt an address from tax (e.g. presale contract, vesting contract).
    function setTaxExempt(address account, bool exempt) external onlyOwner {
        isTaxExempt[account] = exempt;
        emit TaxExemptSet(account, exempt);
    }

    function totalTaxBps() external view returns (uint256) {
        return burnBps + victimFundBps + liquidityBps;
    }
}
