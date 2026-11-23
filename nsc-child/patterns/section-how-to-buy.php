<?php
/**
 * Title: How to buy
 * Slug: nsc-child/section-how-to-buy
 * Categories: noscam
 * Inserter: true
 *
 * The steps follow the presale contract exactly: buy() / receive(), its checks
 * (sale open, minimum, wallet limit, tokens left, fresh BNB price) and what it does
 * (sends N$C to the buyer, forwards BNB to the treasury, emits Bought).
 *
 * @package nsc-child
 */
?>
<!-- wp:html -->
<section id="how-to-buy" class="how-to-buy">
  <div class="section-inner">
    <div class="divider"></div>
    <span class="tag">Getting Started</span>
    <h2 class="section-title">How to Buy N$C</h2>
    <p class="section-sub">
      You buy straight from the N$C presale smart contract on BNB Chain. The price
      is set in US dollars, you pay in BNB, and your N$C arrives in the same
      transaction.
    </p>

    <ol class="buy-steps">
      <li class="buy-step fade-up">
        <span class="bs-num">01</span>
        <h3>Get BNB on BNB Smart Chain</h3>
        <p>
          Use MetaMask, Trust Wallet, Binance Wallet or any WalletConnect wallet,
          with <strong>BNB on BNB Smart Chain (BEP-20)</strong>. Keep about
          0.0005 BNB extra for the network fee.
        </p>
      </li>
      <li class="buy-step fade-up">
        <span class="bs-num">02</span>
        <h3>Connect on noscamcoin.org</h3>
        <p>
          Click <strong>Buy N$C</strong> and pick your wallet. The site switches
          it to BNB Smart Chain for you. On a phone, open this site in your
          wallet app's browser.
        </p>
      </li>
      <li class="buy-step fade-up">
        <span class="bs-num">03</span>
        <h3>Enter your BNB amount</h3>
        <p>
          The contract prices N$C in dollars using Chainlink's live BNB price:
        </p>
        <p class="bs-formula">N$C = BNB × BNB price ÷ [nsc_stat key="price"]</p>
        <p>
          Right now 1 BNB ≈ <strong>[nsc_stat key="nscPerBnb"] N$C</strong>.
          Minimum purchase: <strong>[nsc_stat key="minBuy"] BNB</strong>.
        </p>
      </li>
      <li class="buy-step fade-up">
        <span class="bs-num">04</span>
        <h3>Confirm in your wallet</h3>
        <p>
          Your wallet asks you to send BNB to the presale contract's
          <code>buy()</code> function. Check the amount and that it says BNB
          Smart Chain, then confirm.
        </p>
      </li>
      <li class="buy-step fade-up">
        <span class="bs-num">05</span>
        <h3>The contract checks</h3>
        <ul>
          <li>The sale is open</li>
          <li>You sent at least the minimum</li>
          <li>You're within any per-wallet limit</li>
          <li>Enough N$C is left in the presale</li>
          <li>The BNB price is up to date (under 2 hours old)</li>
        </ul>
        <p>If any check fails, nothing is bought and your BNB stays with you. Only the network fee is spent.</p>
      </li>
      <li class="buy-step fade-up">
        <span class="bs-num">06</span>
        <h3>Receive N$C instantly</h3>
        <p>
          In the same transaction the contract sends your N$C (no 3% tax on
          presale buys) and forwards your BNB to the NOSCAM treasury. Nothing to
          claim later. Tap <strong>Show N$C in my wallet</strong> to add the token.
        </p>
      </li>
    </ol>

    <div class="buy-direct">
      <div>
        <h3>Or pay directly from any wallet</h3>
        <p>
          Sending BNB straight to the presale contract buys N$C automatically,
          with the same checks and price. Always copy the address from here and
          check it on BscScan. <strong>Never send from an exchange account</strong>
          (Binance, Bybit…): the N$C would go back to the exchange's address, not
          to you. Use a wallet you control.
        </p>
      </div>
      <div class="buy-addresses">
        <p><span>Presale contract</span>[nsc_contract which="presale"]</p>
        <p><span>N$C token</span>[nsc_contract]</p>
      </div>
    </div>
    <p class="buy-note">
      After buying, N$C is an ordinary BEP-20 token in your wallet. When you later
      send it to someone, the token's 3% transfer tax applies: 1% burned, 1% to the
      Victim Fund, 1% to Liquidity.
    </p>
  </div>
</section>
<!-- /wp:html -->
