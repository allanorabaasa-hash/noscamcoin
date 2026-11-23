<?php
/**
 * Theme shortcodes: site header, footer, social cards, coin image, whitepaper link.
 *
 * They are shortcodes (not hard-coded HTML) so links and images follow the site's address
 * when it moves from your computer to noscamcoin.org, and the social links follow
 * NSC Presale → Settings.
 *
 * @package nsc-child
 */

defined( 'ABSPATH' ) || exit;

/** A setting from the NSC Presale plugin, with a fallback if the plugin is off. */
function nsc_theme_setting( $key, $fallback = '' ) {
	if ( function_exists( 'nsc_setting' ) ) {
		$v = nsc_setting( $key );
		return '' !== (string) $v ? $v : $fallback;
	}
	return $fallback;
}

/**
 * If the NOSCAM Presale plugin is off, its shortcodes would show as raw [nsc_…] text.
 * Register quiet stand-ins instead, and tell administrators what to do.
 */
add_action(
	'init',
	function () {
		if ( function_exists( 'nsc_setting' ) ) {
			return;
		}
		$dash = function () {
			return '<span class="nsc-missing">—</span>';
		};
		foreach ( array( 'nsc_stat', 'nsc_contract', 'nsc_recent_buys', 'nsc_ticker' ) as $tag ) {
			add_shortcode( $tag, 'nsc_ticker' === $tag || 'nsc_recent_buys' === $tag ? '__return_empty_string' : $dash );
		}
		add_shortcode(
			'nsc_buy_button',
			function ( $atts, $content = '' ) {
				$a = shortcode_atts( array( 'class' => 'btn-primary' ), $atts );
				return '<a class="' . esc_attr( $a['class'] ) . '" href="#buy"><span>' . esc_html( $content ? $content : 'Buy N$C' ) . '</span></a>';
			}
		);
		add_shortcode(
			'nsc_presale_card',
			function () {
				return '<div class="presale-card" id="buy"><div class="pc-stage">PRESALE</div><p class="pc-note">The presale is being connected. Please check back shortly.</p></div>';
			}
		);
		add_shortcode(
			'nsc_report_form',
			function () {
				return '<div class="report-form"><p class="rf-foot">The report form is temporarily unavailable.</p></div>';
			}
		);
		add_shortcode(
			'nsc_news',
			function () {
				return '';
			}
		);
	},
	20
);

add_action(
	'admin_notices',
	function () {
		if ( function_exists( 'nsc_setting' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>NOSCAM theme:</strong> the <strong>NOSCAM Presale</strong> plugin is not active, so the live figures, ticker and Buy N$C button are off. Go to <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Plugins</a> and activate it (or upload <code>nsc-presale.zip</code> under Plugins → Add New Plugin → Upload Plugin).</p></div>';
	}
);

/** Image URL inside the theme. */
function nsc_theme_img( $file ) {
	return esc_url( get_stylesheet_directory_uri() . '/assets/images/' . ltrim( $file, '/' ) );
}

/** "#about" on the homepage, "https://site/#about" elsewhere. */
function nsc_anchor( $id ) {
	return is_front_page() ? '#' . $id : esc_url( home_url( '/#' . $id ) );
}

/** Social networks: key => [ label, logo, card class, fallback URL ]. */
function nsc_socials_list() {
	return array(
		'social_instagram' => array( 'Instagram', 'ig_logo.jpeg', 'ig', '' ),
		'social_telegram'  => array( 'Telegram', 'telegram_logo.webp', 'tg', 'https://t.me/notocryptoscamss' ),
		'social_x'         => array( 'X (Twitter)', 'x_logo.png', 'tw', 'https://x.com/NoscamEcosystem' ),
		'social_youtube'   => array( 'YouTube', 'youtube.jpeg', 'yt', 'https://www.youtube.com/@noscamcointv' ),
		'social_linkedin'  => array( 'LinkedIn', 'linkedin_logo.jpeg', 'li', 'https://www.linkedin.com/groups/9867218' ),
		'social_whatsapp'  => array( 'WhatsApp', 'whatsapp_logo.jpeg', 'wa', '' ),
	);
}

add_shortcode(
	'nsc_coin',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'class' => '',
				'alt'   => 'NOSCAM Coin',
				'size'  => 'hero',
			),
			$atts
		);
		$file = 'hero' === $a['size'] ? 'coin-hero.jpg' : 'coin.png';
		return '<img class="' . esc_attr( $a['class'] ) . '" src="' . nsc_theme_img( $file ) . '" alt="' . esc_attr( $a['alt'] ) . '" decoding="async">';
	}
);

add_shortcode(
	'nsc_whitepaper_url',
	function () {
		return esc_url( nsc_theme_setting( 'whitepaper', 'https://heyzine.com/flip-book/cbfb32e708.html' ) );
	}
);

add_shortcode(
	'nsc_whitepaper',
	function ( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'class' => 'btn-ghost' ), $atts );
		return '<a href="' . esc_url( nsc_theme_setting( 'whitepaper', 'https://heyzine.com/flip-book/cbfb32e708.html' ) ) . '" class="' . esc_attr( $a['class'] ) . '" target="_blank" rel="noopener noreferrer">' . do_shortcode( $content ? $content : '📄 Whitepaper' ) . '</a>';
	}
);

add_shortcode(
	'nsc_socials',
	function () {
		$labels = array(
			'ig' => 'Follow Us',
			'tg' => 'Join the chat',
			'tw' => 'Follow Us',
			'yt' => 'Subscribe',
			'li' => 'Connect',
			'wa' => 'Message Us',
		);
		$out = '<div class="social-grid">';
		foreach ( nsc_socials_list() as $key => $s ) {
			$url = nsc_theme_setting( $key, $s[3] );
			if ( ! $url ) {
				continue;
			}
			$out .= '<a href="' . esc_url( $url ) . '" class="social-card ' . esc_attr( $s[2] ) . '" target="_blank" rel="noopener"><img src="' . nsc_theme_img( 'social/' . $s[1] ) . '" alt="" class="social-icon" loading="lazy"><div class="social-name">' . esc_html( $s[0] ) . '</div><div class="social-count">' . esc_html( $labels[ $s[2] ] ) . '</div></a>';
		}
		return $out . '</div>';
	}
);

/** Site header: background canvas, live ticker, navigation and mobile menu. */
add_shortcode(
	'nsc_header',
	function () {
		$links = array(
			'home'       => 'Home',
			'about'      => 'About',
			'whitepaper' => 'Whitepaper',
			'tokenomics' => 'Tokenomics',
			'team'       => 'Team',
			'roadmap'    => 'Roadmap',
			'report'     => 'Report',
			'faq'        => 'FAQ',
		);
		$li = '';
		foreach ( $links as $id => $label ) {
			$li .= '<li><a href="' . nsc_anchor( $id ) . '">' . esc_html( $label ) . '</a></li>';
		}
		$news = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : '';
		ob_start();
		?>
		<canvas id="bg-canvas" aria-hidden="true"></canvas>
		<?php echo shortcode_exists( 'nsc_ticker' ) ? do_shortcode( '[nsc_ticker]' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<nav class="navbar" aria-label="Primary navigation">
			<div class="nav-inner">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo">
					<span class="logo-icon"><img src="<?php echo nsc_theme_img( 'coin.png' ); // phpcs:ignore ?>" alt="" width="36" height="36"></span>
					<span class="logo-text"><em>NOSCAMCOIN</em></span>
				</a>
				<ul class="nav-links"><?php echo $li; // phpcs:ignore ?></ul>
				<div class="nav-right">
					<a href="<?php echo nsc_anchor( 'report' ); // phpcs:ignore ?>" class="btn-ghost">🚨 Report Scam</a>
					<button type="button" class="btn-primary" data-nsc-buy><span>Buy N$C</span></button>
				</div>
				<button class="hamburger" id="hamburger" type="button" aria-label="Menu" aria-controls="mobileMenu" aria-expanded="false"><span></span><span></span><span></span></button>
			</div>
		</nav>
		<nav class="mobile-menu" id="mobileMenu" aria-label="Mobile navigation">
			<ul><?php echo $li; // phpcs:ignore ?><?php echo $news ? '<li><a href="' . esc_url( $news ) . '">News</a></li>' : ''; // phpcs:ignore ?></ul>
			<button type="button" class="buy-btn mob-cta" data-nsc-buy>🛡️ Buy N$C</button>
		</nav>
		<?php
		return ob_get_clean();
	}
);

/** Site footer. */
add_shortcode(
	'nsc_footer',
	function () {
		$soc = '';
		foreach ( nsc_socials_list() as $key => $s ) {
			$url = nsc_theme_setting( $key, $s[3] );
			if ( $url ) {
				$soc .= '<a href="' . esc_url( $url ) . '" class="fsoc" aria-label="' . esc_attr( $s[0] ) . '" target="_blank" rel="noopener"><img src="' . nsc_theme_img( 'social/' . $s[1] ) . '" alt=""></a>';
			}
		}
		$privacy = get_privacy_policy_url();
		$terms   = get_page_by_path( 'terms' );
		ob_start();
		?>
		<footer class="nsc-footer">
			<div class="footer-inner">
				<div class="footer-grid">
					<div class="footer-brand">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo"><span class="logo-icon"><img src="<?php echo nsc_theme_img( 'coin.png' ); // phpcs:ignore ?>" alt="" width="36" height="36"></span><span class="logo-text">NOSCAMCOIN</span></a>
						<p>The anti-fraud cryptocurrency on BNB Chain. Powered by truth, governed by community, built to protect.</p>
						<div class="footer-social-row"><?php echo $soc; // phpcs:ignore ?></div>
					</div>
					<div class="footer-col">
						<h5>About</h5>
						<a href="<?php echo nsc_anchor( 'about' ); // phpcs:ignore ?>">Our Mission</a><a href="<?php echo nsc_anchor( 'whitepaper' ); // phpcs:ignore ?>">Whitepaper</a><a href="<?php echo nsc_anchor( 'tokenomics' ); // phpcs:ignore ?>">Tokenomics</a><a href="<?php echo nsc_anchor( 'team' ); // phpcs:ignore ?>">Team</a><a href="<?php echo nsc_anchor( 'roadmap' ); // phpcs:ignore ?>">Roadmap</a><a href="<?php echo nsc_anchor( 'press-kit' ); // phpcs:ignore ?>">Press Kit</a>
					</div>
					<div class="footer-col">
						<h5>Fight Scams</h5>
						<a href="<?php echo nsc_anchor( 'report' ); // phpcs:ignore ?>">Report a Scam</a><a href="<?php echo nsc_anchor( 'scam-database' ); // phpcs:ignore ?>">Scam Database</a><a href="<?php echo nsc_anchor( 'fraud-alerts' ); // phpcs:ignore ?>">Fraud Alerts</a><a href="<?php echo nsc_anchor( 'verify-project' ); // phpcs:ignore ?>">Verify a Project</a><a href="<?php echo nsc_anchor( 'education-hub' ); // phpcs:ignore ?>">Education Hub</a><a href="<?php echo nsc_anchor( 'browser-extension' ); // phpcs:ignore ?>">Browser Extension</a>
					</div>
					<div class="footer-col">
						<h5>Buy &amp; Earn</h5>
						<a href="<?php echo nsc_anchor( 'buy' ); // phpcs:ignore ?>">Buy N$C</a><a href="<?php echo nsc_anchor( 'how-to-buy' ); // phpcs:ignore ?>">How to Buy</a><a href="<?php echo nsc_anchor( 'referral-program' ); // phpcs:ignore ?>">Referral Program</a><a href="<?php echo nsc_anchor( 'report' ); // phpcs:ignore ?>">Bounty Program</a><a href="<?php echo nsc_anchor( 'staking' ); // phpcs:ignore ?>">Staking</a><a href="<?php echo nsc_anchor( 'faq' ); // phpcs:ignore ?>">FAQs</a>
					</div>
				</div>
				<div class="footer-bottom">
					<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> NOSCAMCOIN. All rights reserved.<?php echo $privacy ? ' <a href="' . esc_url( $privacy ) . '">Privacy Policy</a>' : ''; // phpcs:ignore ?><?php echo $terms ? ' · <a href="' . esc_url( get_permalink( $terms ) ) . '">Terms</a>' : ''; // phpcs:ignore ?></p>
					<p class="footer-contract">Contract: <?php echo shortcode_exists( 'nsc_contract' ) ? do_shortcode( '[nsc_contract]' ) : '—'; // phpcs:ignore ?></p>
				</div>
			</div>
			<p class="disclaimer">⚠️ This is not financial advice. Cryptocurrency carries significant risk and N$C may lose some or all of its value. N$C is a utility token for the NOSCAM anti-fraud ecosystem. Never invest more than you can afford to lose, check the contract address yourself, and always DYOR.</p>
		</footer>
		<?php
		return ob_get_clean();
	}
);
