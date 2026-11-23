// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

/// @dev Test-only stand-in for the Chainlink BNB/USD feed. Not deployed to mainnet.
contract MockPriceFeed {
    int256 public answer;
    uint256 public updatedAt;

    constructor(int256 _answer) { set(_answer); }

    function set(int256 _answer) public { answer = _answer; updatedAt = block.timestamp; }
    function setUpdatedAt(uint256 t) external { updatedAt = t; }

    function latestRoundData() external view returns (uint80, int256, uint256, uint256, uint80) {
        return (1, answer, updatedAt, updatedAt, 1);
    }
}
