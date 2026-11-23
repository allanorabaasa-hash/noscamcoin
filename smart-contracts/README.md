# NOSCAM Coin (N$C): BEP-20 smart contract

| Item | Value |
|---|---|
| Chain | BNB Smart Chain (BEP-20) |
| Name / Symbol | NOSCAM Coin / NSC |
| Supply | 1,000,000,000 (fixed, minted once, no mint function) |
| Tax | 3%: 1% burn, 1% Victim Fund wallet, 1% Liquidity wallet |
| Safety | Tax hard-capped at 3% (can only go down), no blacklist, no pause, no max-wallet |
| Deploy gas | ~1.11M gas (under a dollar at normal BSC gas prices) |

## Cheapest way to deploy: Remix + MetaMask (nothing to install)

1. Create two wallets in MetaMask: **Victim Fund** and **Liquidity**. Copy their addresses.
2. Add **BSC Testnet** to MetaMask and get free test BNB from the faucet: https://www.bnbchain.org/en/testnet-faucet
3. Open https://remix.ethereum.org, create `NoScamCoin.sol`, and paste the contract in.
4. **Solidity Compiler** tab: version `0.8.24`, enable optimization (200 runs), then click Compile.
5. **Deploy** tab: Environment = *Injected Provider – MetaMask*. Enter the Victim Fund and Liquidity addresses, then click Deploy.
6. Test on testnet: send tokens between two non-exempt wallets and confirm 97% arrives.
7. When it works, switch MetaMask to **BSC Mainnet** (≈0.001 BNB is plenty) and deploy again.
8. Verify on BscScan: *Verify & Publish* → Solidity single file, 0.8.24, optimization yes / 200 runs. Use Remix's **Flatten** (right-click the file) so the OpenZeppelin imports are included.

## After deploy
- `setTaxExempt(address, true)` for the presale contract, the vesting contract, and the PancakeSwap router if you add liquidity from it.
- Team tokens (30M, 24-month lock): put them in an OpenZeppelin `VestingWallet`. That's a separate small contract and comes next.
- Optional: `renounceOwnership()` once settings are final. This locks the tax forever.

## Local testing (developers)
```
npm install
npx hardhat test            # or: USE_NPM_SOLC=1 npx hardhat test (offline)
```
