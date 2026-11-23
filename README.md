# NOSCAMCOIN website (WordPress)

The noscamcoin.org website on WordPress, connected to the live N$C contracts on BNB Chain.

| Part | What it is |
|---|---|
| `nsc-child/` | Theme (child of Twenty Twenty-Five): the original site's sections, colours and animations. The type is now Unbounded (headings), Manrope (text) and JetBrains Mono (addresses), all under the SIL OFL licence. |
| `nsc-presale/` | Plugin: reads the contracts, shows live figures, runs the **Buy N$C** window, keeps a record of every N$C transaction, provides the owner controls and the scam-report inbox. |
| `smart-contracts/` | The N$C token and presale contracts (Solidity), with a Hardhat project and tests (`npm install && npx hardhat test`). |

**Contracts (BNB Chain mainnet, verified on BscScan)**
- Token (N$C): `0x2Af2Ab246e909D2e2Ae79825023893283faD5Ef9`
- Presale: `0x4E664ACE8Df9C5Dc69b2D23Fb31726B999B88592`
- BNB/USD price feed (Chainlink): `0x0567F2323251f0Aab15c8dFb1967E4e8A7D42aeE`

## How buying works

1. The visitor clicks **Buy N$C**. The window lists every wallet found in the browser: MetaMask, Trust Wallet, Binance Wallet, OKX, Coinbase, Rabby and any other wallet that announces itself (EIP-6963).
2. On a phone with no wallet installed, the window offers links that open the site inside the MetaMask or Trust Wallet app.
3. WalletConnect (QR code, works with any wallet) appears once you add a free project ID under Settings.
4. The buyer enters an amount of BNB. The window shows the dollar value and the exact N$C they will get, worked out the same way the contract does: BNB × live BNB price ÷ your $ price.
5. They confirm in their wallet. The presale contract sends the N$C to them in the same transaction, and the BNB goes to the treasury.
6. If the sale is paused, the amount is below the minimum, or the BNB price feed is late, the window says so in plain words before the buyer confirms.

## Admin: WordPress → NSC Presale

- **Dashboard:** live price, BNB price, money raised, tokens sold and left, number of purchases, holders, tokens burned, and every contract and wallet address.
- **Transactions:** every N$C transfer read from the blockchain every 5 minutes, labelled as presale buy, transfer, tax, burn, presale top-up or mint. Filter by type, search by address or transaction hash, and download a CSV.
- **Holders:** every address holding N$C. These balances were checked to match the blockchain exactly.
- **Owner controls:** connect the owner wallet (MetaMask) to:
  - set the price in **US dollars**
  - **open or pause** the sale
  - change the treasury wallet
  - set the minimum and maximum purchase
  - take unsold N$C back out of the presale
- **Scam reports:** reports sent from the website form, with a status and staff notes. Each new report is also emailed to you.
- **Settings:** network (mainnet or testnet), RPC addresses, WalletConnect project ID, whitepaper link and social links.

## Install on noscamcoin.org (Namecheap cPanel)

1. In cPanel, open **Softaculous → WordPress** and install it on `noscamcoin.org`, with HTTPS.
2. Upload the theme: **Appearance → Themes → Add New → Upload** `nsc-child.zip`, then click **Activate**. Twenty Twenty-Five, its parent theme, comes with WordPress.
3. Upload the plugin: **Plugins → Add New → Upload** `nsc-presale.zip`, then click **Activate**.
4. Go to **Appearance → NOSCAM setup → Build the site**.
5. Go to **NSC Presale → Transactions → Check for new transactions**. Click it again until it says it's up to date.
6. Set up a real cron so the transaction record keeps updating even when nobody visits. In cPanel **Cron Jobs**, add an entry that runs every 5 minutes:
   ```
   */5 * * * * wget -q -O - https://noscamcoin.org/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```

## Go-live checklist

- [ ] **NSC Presale → Owner controls → Change treasury** to a phone wallet, so buyers' BNB doesn't sit on the deployer machine.
- [ ] **Owner controls → Open the sale.** The presale was deployed closed.
- [ ] Optional: get a WalletConnect project ID at cloud.reown.com, add `noscamcoin.org` as an allowed domain, and paste the ID into Settings.
- [ ] Update the team bios, press email and social links if needed.
- [ ] Have a lawyer review the **Terms & Risks** page and the presale before promoting it.

## Local development (Kali), same layout as the other WordPress projects

```bash
sudo mkdir -p /opt/nsc-wordpress && sudo chown $USER:$USER /opt/nsc-wordpress
git clone https://github.com/allanorabaasa-hash/noscamcoin.git /opt/nsc-wordpress
WP=/var/www/html/nsc/wp-content
sudo ln -s /opt/nsc-wordpress/nsc-child   $WP/themes/nsc-child
sudo ln -s /opt/nsc-wordpress/nsc-presale $WP/plugins/nsc-presale
```

To try changes safely, switch **NSC Presale → Settings → Network** to *BNB Smart Chain Testnet*. It uses the testnet presale `0x5A2c…89C0` and free test BNB.

## Shortcodes

| Shortcode | Shows |
|---|---|
| `[nsc_ticker]` | Scrolling header bar of live figures |
| `[nsc_presale_card]` | Presale card with live figures and the Buy button |
| `[nsc_buy_button]Label[/nsc_buy_button]` | Button that opens the Buy N$C window |
| `[nsc_stat key="…"]` | One live figure. Keys: `price`, `bnbPrice`, `raisedUsd`, `raisedBnb`, `sold`, `left`, `soldPct`, `holders`, `buys`, `burned`, `nscPerBnb`, `minBuy` |
| `[nsc_contract]` | Token address with Copy and BscScan buttons |
| `[nsc_news count="6"]` | Latest posts as news cards |
| `[nsc_recent_buys]` | Latest presale purchases |
| `[nsc_report_form]` | Scam report form |

The `/wp-json/nsc/v1/stats` endpoint returns the same live figures as JSON. It's public, read-only, and cached for 20 seconds.

## Security

- **Server-side reads only:** the website reads the chain on the server and never holds a private key. Every change to the contracts is signed in the owner's own wallet.
- **Report form:** protected by a nonce, a hidden spam trap, a limit of 5 reports per visitor per hour, and sanitised input. Reports are never published automatically.
- **Database:** all queries use prepared statements. Admin pages check permissions and nonces.
- **Presale contract:** ownership can't be renounced by accident.
