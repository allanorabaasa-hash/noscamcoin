<?php
/**
 * Website side: shortcodes, the Buy N$C window and its scripts.
 *
 * [nsc_ticker]          scrolling bar of live figures (used in the header)
 * [nsc_presale_card]    the presale card with live price, progress and the Buy button
 * [nsc_stat key="…"]    one live figure: price, bnbPrice, raisedUsd, raisedBnb, sold, left, soldPct, holders, burned, buys
 * [nsc_buy_button]      a button that opens the Buy N$C window
 * [nsc_contract]        the token address with copy and BscScan links
 * [nsc_news count="6"]  latest posts as news cards
 * [nsc_recent_buys]     latest presale purchases
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

class NSC_Public {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'buy_window' ) );
		add_shortcode( 'nsc_ticker', array( __CLASS__, 'ticker' ) );
		add_shortcode( 'nsc_presale_card', array( __CLASS__, 'presale_card' ) );
		add_shortcode( 'nsc_stat', array( __CLASS__, 'stat' ) );
		add_shortcode( 'nsc_buy_button', array( __CLASS__, 'buy_button' ) );
		add_shortcode( 'nsc_contract', array( __CLASS__, 'contract' ) );
		add_shortcode( 'nsc_news', array( __CLASS__, 'news' ) );
		add_shortcode( 'nsc_recent_buys', array( __CLASS__, 'recent_buys' ) );
		add_shortcode( 'nsc_setting', array( __CLASS__, 'setting_sc' ) );
	}

	public static function assets() {
		wp_enqueue_style( 'nsc-presale', NSC_URL . 'assets/presale.css', array(), NSC_VERSION );
		wp_enqueue_script(
			'nsc-presale',
			NSC_URL . 'assets/presale.js',
			array(),
			NSC_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		$s = nsc_settings();
		wp_localize_script(
			'nsc-presale',
			'NSC_CFG',
			array(
				'rest'          => esc_url_raw( rest_url( 'nsc/v1/' ) ),
				'chainId'       => (int) $s['chain_id'],
				'chainName'     => 'testnet' === $s['network'] ? 'BNB Smart Chain Testnet' : 'BNB Smart Chain',
				'rpc'           => $s['rpc'],
				'explorer'      => $s['explorer'],
				'currency'      => $s['currency'],
				'token'         => $s['token'],
				'presale'       => $s['presale'],
				'walletconnect' => $s['walletconnect'],
				'siteUrl'       => home_url( '/' ),
				'siteName'      => get_bloginfo( 'name' ),
				'icon'          => self::coin_url(),
			)
		);
	}

	/** Coin image: the theme's, or the plugin's copy. */
	public static function coin_url() {
		$theme = get_stylesheet_directory() . '/assets/images/coin.png';
		return file_exists( $theme ) ? get_stylesheet_directory_uri() . '/assets/images/coin.png' : NSC_URL . 'assets/coin.png';
	}

	/** A live value, server-rendered and refreshed in the browser. */
	public static function live( $key, $class = '' ) {
		$s = NSC_Chain::stats();
		if ( 'holders' === $key || 'buys' === $key ) {
			$v = number_format( (int) ( isset( $s[ $key ] ) ? $s[ $key ] : 0 ) );
		} elseif ( 'soldPct' === $key ) {
			$v = NSC_Chain::num( (float) $s['view']['soldPct'], 2 ) . '%';
		} else {
			$v = isset( $s['view'][ $key ] ) ? $s['view'][ $key ] : '—';
		}
		return '<span class="nsc-live ' . esc_attr( $class ) . '" data-nsc="' . esc_attr( $key ) . '">' . esc_html( $v ) . '</span>';
	}

	public static function stat( $atts ) {
		$a = shortcode_atts( array( 'key' => 'price' ), $atts );
		return self::live( preg_replace( '/[^A-Za-z]/', '', (string) $a['key'] ) );
	}

	public static function setting_sc( $atts ) {
		$a = shortcode_atts( array( 'key' => '' ), $atts );
		$allowed = array( 'whitepaper', 'social_x', 'social_telegram', 'social_youtube', 'social_linkedin', 'social_instagram', 'social_whatsapp', 'token', 'presale' );
		return in_array( $a['key'], $allowed, true ) ? esc_url( nsc_setting( $a['key'] ) ) : '';
	}

	/** Header ticker. */
	public static function ticker() {
		$s     = NSC_Chain::stats();
		$live  = ! empty( $s['saleActive'] );
		$items = array(
			'🛡️ NOSCAMCOIN TOKEN <span class="tick-badge" data-nsc="saleBadge">' . ( $live ? 'PRESALE LIVE' : 'PRESALE PAUSED' ) . '</span>',
			'💠 N$C Price: ' . self::live( 'price' ),
			'🔶 BNB: ' . self::live( 'bnbPrice' ),
			'💰 Raised: ' . self::live( 'raisedUsd' ) . ' <span class="tick-dim">(' . self::live( 'raisedBnb' ) . ')</span>',
			'⚡ Presale Sold: ' . self::live( 'sold' ) . ' <span class="tick-badge">' . self::live( 'soldPct' ) . '</span>',
			'📦 Remaining: ' . self::live( 'left' ),
			'👥 Holders: ' . self::live( 'holders' ),
			'🔥 Burned: ' . self::live( 'burned' ),
			'✅ Contract verified on <a href="' . esc_url( nsc_explorer( 'address', nsc_setting( 'token' ) ) . '#code' ) . '" target="_blank" rel="noopener">BscScan</a>',
		);
		$row = '';
		foreach ( $items as $it ) {
			$row .= '<div class="ticker-item">' . $it . '</div>';
		}
		// Twice, so the loop is seamless; the copy is hidden from screen readers.
		return '<div class="ticker-bar" role="region" aria-label="Live N$C figures"><div class="ticker-track">' . $row . '<div class="ticker-copy" aria-hidden="true">' . $row . '</div></div></div>';
	}

	/** Presale card (hero). */
	public static function presale_card() {
		$s    = NSC_Chain::stats();
		$live = ! empty( $s['saleActive'] );
		$pct  = (float) $s['view']['soldPct'];
		ob_start();
		?>
		<div class="presale-card" id="buy">
			<div class="pc-stage" data-nsc="stageLabel"><?php echo $live ? '⚡ PRESALE — LIVE NOW' : '⏸ PRESALE PAUSED'; ?></div>
			<div class="pc-price"><?php echo self::live( 'price' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span class="pc-per">/ N$C</span></div>
			<div class="pc-price-sub">1 <?php echo esc_html( $s['currency'] ); ?> ≈ <strong><?php echo self::live( 'nscPerBnb' ); // phpcs:ignore ?> N$C</strong> · <?php echo esc_html( $s['currency'] ); ?> <?php echo self::live( 'bnbPrice' ); // phpcs:ignore ?></div>
			<div class="progress-labels"><span>Presale progress</span><span class="pc-sold-pct"><?php echo self::live( 'soldPct' ); // phpcs:ignore ?> SOLD</span></div>
			<div class="progress-track"><div class="progress-fill" data-nsc-width="soldPct" style="width:<?php echo esc_attr( max( 0.6, min( 100, $pct ) ) ); ?>%"></div></div>
			<div class="pc-raised">
				<div class="pc-stat"><div class="pc-stat-lbl">Raised</div><div class="pc-stat-val"><?php echo self::live( 'raisedUsd' ); // phpcs:ignore ?></div><div class="pc-stat-sub"><?php echo self::live( 'raisedBnb' ); // phpcs:ignore ?></div></div>
				<div class="pc-stat"><div class="pc-stat-lbl">Tokens Sold</div><div class="pc-stat-val"><?php echo self::live( 'sold' ); // phpcs:ignore ?></div></div>
				<div class="pc-stat"><div class="pc-stat-lbl">Remaining</div><div class="pc-stat-val"><?php echo self::live( 'left' ); // phpcs:ignore ?></div></div>
				<div class="pc-stat"><div class="pc-stat-lbl">Holders</div><div class="pc-stat-val"><?php echo self::live( 'holders' ); // phpcs:ignore ?></div></div>
			</div>
			<div class="pc-methods"><span class="pc-method is-on"><?php echo esc_html( $s['currency'] ); ?></span><span class="pc-method">MetaMask</span><span class="pc-method">Trust Wallet</span><span class="pc-method">Binance Wallet</span><span class="pc-method">WalletConnect</span></div>
			<button type="button" class="buy-btn" data-nsc-buy><?php esc_html_e( '🛡️ BUY N$C NOW', 'nsc-presale' ); ?></button>
			<p class="pc-note">✅ <a href="<?php echo esc_url( nsc_explorer( 'address', $s['presale'] ) . '#code' ); ?>" target="_blank" rel="noopener">Verified on BscScan</a> &nbsp;·&nbsp; N$C sent to your wallet instantly &nbsp;·&nbsp; Pay in <?php echo esc_html( $s['currency'] ); ?> on BNB Chain</p>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function buy_button( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'class' => 'btn-primary' ), $atts );
		$label = $content ? $content : '🛡️ Buy N$C';
		return '<button type="button" class="' . esc_attr( $a['class'] ) . '" data-nsc-buy><span>' . esc_html( $label ) . '</span></button>';
	}

	public static function contract( $atts = array() ) {
		$a = shortcode_atts( array( 'which' => 'token' ), $atts );
		$t = 'presale' === $a['which'] ? nsc_setting( 'presale' ) : nsc_setting( 'token' );
		return '<span class="nsc-contract"><code>' . esc_html( $t ) . '</code> <button type="button" class="nsc-copy" data-copy="' . esc_attr( $t ) . '">Copy</button> <a href="' . esc_url( nsc_explorer( 'presale' === $a['which'] ? 'address' : 'token', $t ) ) . '" target="_blank" rel="noopener">BscScan ↗</a></span>';
	}

	/** News cards from the latest posts. */
	public static function news( $atts ) {
		$a = shortcode_atts( array( 'count' => 6 ), $atts );
		$q = new WP_Query(
			array(
				'post_type'           => 'post',
				'posts_per_page'      => max( 1, min( 12, (int) $a['count'] ) ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
		if ( ! $q->have_posts() ) {
			return '<p class="section-sub">News is on its way.</p>';
		}
		$out = '<div class="news-grid">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$out .= '<article class="news-card"><div class="news-date">' . esc_html( get_the_date() ) . '</div><h3>' . esc_html( get_the_title() ) . '</h3><p>' . esc_html( wp_trim_words( get_the_excerpt(), 28 ) ) . '</p><a href="' . esc_url( get_permalink() ) . '" class="news-link">Read Full Story ↗</a></article>';
		}
		wp_reset_postdata();
		return $out . '</div>';
	}

	public static function recent_buys() {
		return '<div class="nsc-recent" data-nsc-recent><p class="nsc-recent-empty">Loading the latest purchases…</p></div>';
	}

	/** The Buy N$C window (one per page). */
	public static function buy_window() {
		$cur = esc_html( nsc_setting( 'currency' ) );
		?>
		<div class="nsc-modal" id="nsc-buy" hidden aria-hidden="true">
			<div class="nsc-modal-bg" data-nsc-close></div>
			<div class="nsc-modal-box" role="dialog" aria-modal="true" aria-labelledby="nsc-buy-title">
				<button type="button" class="nsc-x" data-nsc-close aria-label="Close">×</button>
				<div class="nsc-head">
					<img src="<?php echo esc_url( self::coin_url() ); ?>" alt="" width="40" height="40">
					<div><h2 id="nsc-buy-title">Buy N$C</h2><p><span data-nsc="price">—</span> per N$C · <span data-nsc="stageShort">Presale</span></p></div>
				</div>

				<div class="nsc-step" data-step="wallet">
					<p class="nsc-lead">Choose your wallet</p>
					<div class="nsc-wallets" data-nsc-wallets></div>
					<div class="nsc-mobile" data-nsc-mobile hidden>
						<p class="nsc-small">On a phone? Open this page inside your wallet app's browser:</p>
						<div class="nsc-deeplinks">
							<a data-deeplink="metamask" href="#">MetaMask app</a>
							<a data-deeplink="trust" href="#">Trust Wallet app</a>
							<a data-deeplink="binance" href="#">Binance app</a>
						</div>
					</div>
					<p class="nsc-small nsc-safety">🔒 Only buy here or by sending <?php echo $cur; // phpcs:ignore ?> to the presale contract shown below. We will never DM you an address.</p>
				</div>

				<div class="nsc-step" data-step="amount" hidden>
					<div class="nsc-account"><span class="nsc-dot"></span><span data-nsc-account></span><button type="button" class="nsc-link" data-nsc-disconnect>Change</button></div>
					<label class="nsc-field">
						<span>You pay</span>
						<div class="nsc-input"><input type="text" inputmode="decimal" autocomplete="off" placeholder="0.00" data-nsc-amount><b><?php echo $cur; // phpcs:ignore ?></b></div>
						<small><span data-nsc-usd>≈ $0.00</span> · Balance: <span data-nsc-balance>—</span> <button type="button" class="nsc-link" data-nsc-max>Max</button></small>
					</label>
					<div class="nsc-quick"><button type="button" data-q="0.05">0.05</button><button type="button" data-q="0.1">0.1</button><button type="button" data-q="0.5">0.5</button><button type="button" data-q="1">1</button></div>
					<div class="nsc-receive"><span>You receive</span><strong data-nsc-receive>0 N$C</strong></div>
					<p class="nsc-small" data-nsc-rules></p>
					<p class="nsc-error" data-nsc-error hidden></p>
					<button type="button" class="buy-btn" data-nsc-confirm>Buy N$C</button>
					<p class="nsc-small">Your wallet will ask you to confirm. Network fee: a few cents in <?php echo $cur; // phpcs:ignore ?>.</p>
				</div>

				<div class="nsc-step" data-step="pending" hidden>
					<div class="nsc-spinner"></div>
					<p class="nsc-lead" data-nsc-pending-text>Confirm in your wallet…</p>
					<p class="nsc-small" data-nsc-pending-link></p>
				</div>

				<div class="nsc-step" data-step="done" hidden>
					<div class="nsc-done">✓</div>
					<p class="nsc-lead">You bought <strong data-nsc-bought></strong></p>
					<p class="nsc-small">The N$C is already in your wallet.</p>
					<div class="nsc-done-actions">
						<button type="button" class="btn-ghost" data-nsc-watch>Show N$C in my wallet</button>
						<a class="btn-ghost" data-nsc-txlink target="_blank" rel="noopener" href="#">View on BscScan ↗</a>
					</div>
					<button type="button" class="nsc-link" data-nsc-again>Buy more</button>
				</div>

				<div class="nsc-foot">
					<span>Presale contract</span>
					<a href="<?php echo esc_url( nsc_explorer( 'address', nsc_setting( 'presale' ) ) ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( nsc_setting( 'presale' ) ); ?></code></a>
				</div>
			</div>
		</div>
		<?php
	}
}
