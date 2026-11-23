<?php
/**
 * Title: Report a scam
 * Slug: nsc-child/section-report
 * Categories: noscam
 * Inserter: true
 *
 * @package nsc-child
 */
?>
<!-- wp:html -->
<section class="report" id="report">
  <div class="section-inner">
    <div class="divider"></div>
    <span class="tag">Take Action</span>
    <h2 class="section-title">Report a Scam. Save Someone.</h2>
    <p class="section-sub">
      Every report is reviewed before anything is published, added to the
      scam database once verified, and shared with law enforcement where it
      can help. Earn N$C bounties for verified reports.
    </p>
    <div class="report-grid">
      [nsc_report_form]
      <div>
        <h3
          style="
            font-family: var(--nsc-display);
            font-size: 0.7rem;
            color: var(--white);
            margin-bottom: 16px;
            letter-spacing: 0.08em;
          "
        >
          WHAT CAN YOU REPORT?
        </h3>
        <div class="report-types">
          <div class="rtype-card">
            <div class="rtype-icon">💎</div>
            <h4>Rug Pulls</h4>
            <p>
              Projects that take investor funds and disappear overnight.
            </p>
          </div>
          <div class="rtype-card">
            <div class="rtype-icon">❤️</div>
            <h4>Romance Scams</h4>
            <p>Pig-butchering and fake online relationship fraud.</p>
          </div>
          <div class="rtype-card">
            <div class="rtype-icon">🎣</div>
            <h4>Phishing</h4>
            <p>Fake websites, wallets, and credential-stealing attacks.</p>
          </div>
          <div class="rtype-card">
            <div class="rtype-icon">📈</div>
            <h4>Fake Investment</h4>
            <p>Ponzi schemes, fake trading bots, and yield scams.</p>
          </div>
          <div class="rtype-card">
            <div class="rtype-icon">🖼️</div>
            <h4>NFT Fraud</h4>
            <p>Counterfeit NFTs, wash trading, and fake marketplaces.</p>
          </div>
          <div class="rtype-card">
            <div class="rtype-icon">👤</div>
            <h4>Impersonation</h4>
            <p>Fake influencers, exchanges, and identity fraud.</p>
          </div>
        </div>
        <div class="report-links">
          <div id="scam-database" class="report-anchor">
            <h4>Public Scam Database</h4>
            <p>
              Search verified scam records, flagged wallets, and unsafe
              projects in one public registry.
            </p>
          </div>
          <div id="fraud-alerts" class="report-anchor">
            <h4>Fraud Alerts</h4>
            <p>
              Receive instant alerts on the latest dangerous projects,
              phishing campaigns, and scam reports.
            </p>
          </div>
          <div id="education-hub" class="report-anchor">
            <h4>Education Hub</h4>
            <p>
              Learn to spot scams with guides, safety checks, and practical
              crypto security tips.
            </p>
          </div>
          <div id="verify-project" class="report-anchor">
            <h4>Verify a Project</h4>
            <p>
              Check token contracts, audit status, and community credibility
              before investing.
            </p>
          </div>
        </div>
        <div
          style="
            background: var(--card);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 10px;
            padding: 18px;
            margin-top: 16px;
          "
        >
          <h4
            style="
              font-family: var(--nsc-display);
              font-size: 0.66rem;
              color: var(--green);
              font-weight: 700;
              margin-bottom: 8px;
              letter-spacing: 0.08em;
            "
          >
            💰 BOUNTY PROGRAM
          </h4>
          <p
            style="
              font-family: var(--nsc-body);
              font-size: 0.88rem;
              color: var(--muted);
              line-height: 1.7;
            "
          >
            Earn up to
            <strong style="color: var(--white)">5,000 N$C</strong> for
            verified high-value scam reports. Top reporters receive monthly
            leaderboard rewards and DAO governance rights.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- /wp:html -->
