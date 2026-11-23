<?php
/**
 * Title: Hero with presale card
 * Slug: nsc-child/section-hero
 * Categories: noscam
 * Inserter: true
 *
 * @package nsc-child
 */
?>
<!-- wp:html -->
<section class="hero" id="home">
  <div class="hero-nebula"></div>
  <div class="hero-grid"></div>
  <div class="hero-particles" id="particles"></div>
  <div class="hero-orb">
    <div class="orb-ring"></div>
    <div class="orb-ring"></div>
    <div class="orb-ring"></div>
    <div class="orb-ring"></div>
    <div class="orb-core">[nsc_coin alt="NOSCAM Coin"]</div>
  </div>
  <div class="hero-inner">
    <div>
      <div class="hero-badge">🛡️ &nbsp;The Anti-Fraud Crypto on BNB Chain</div>
      <h1 class="hero-h1">
        Don't Get Played.<br /><span class="shimmer">Fight Back.</span>Build
        Wealth.
      </h1>
      <p class="hero-sub">
        N$C is the cryptocurrency powered by truth. Every transaction fights
        internet crime — funding scam investigations, compensating victims,
        and protecting the next generation of crypto users.
      </p>
      <div class="hero-btns">
        [nsc_buy_button class="btn-primary hero-buy"]🚀 Buy N$C — Presale Live[/nsc_buy_button]
        [nsc_whitepaper class="btn-ghost hero-wp"]📄 Whitepaper[/nsc_whitepaper]
      </div>
      <div class="hero-stats">
        <div class="hstat"><span class="hstat-val">[nsc_stat key="raisedUsd"]</span><span class="hstat-lbl">Raised</span></div>
        <div class="hstat"><span class="hstat-val">[nsc_stat key="sold"]</span><span class="hstat-lbl">Presale Sold</span></div>
        <div class="hstat"><span class="hstat-val">[nsc_stat key="holders"]</span><span class="hstat-lbl">Holders</span></div>
        <div class="hstat"><span class="hstat-val">[nsc_stat key="price"]</span><span class="hstat-lbl">Token Price</span></div>
      </div>
    </div>
    [nsc_presale_card]
  </div>
</section>
<!-- /wp:html -->
