<?php
/**
 * NOSCAM one-click site setup: Appearance → NOSCAM setup.
 *
 * Builds (or refreshes) the homepage from the theme's sections, makes it the front page,
 * creates the News page and the first real news posts, a Terms & Risks page, switches to
 * "Post name" permalinks and trashes WordPress's sample content. Safe to run again.
 *
 * @package nsc-child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_theme_page( 'NOSCAM setup', 'NOSCAM setup', 'manage_options', 'nsc-setup', 'nsc_setup_page' );
	}
);

/** The homepage's block content, built from the section patterns. */
function nsc_home_content() {
	$registry = WP_Block_Patterns_Registry::get_instance();
	$home     = $registry->get_registered( 'nsc-child/page-home' );
	if ( ! $home ) {
		return '';
	}
	// Expand each section so the page holds real, editable blocks.
	return preg_replace_callback(
		'/<!-- wp:pattern \{"slug":"([^"]+)"\} \/-->/',
		function ( $m ) use ( $registry ) {
			$p = $registry->get_registered( $m[1] );
			return $p ? trim( $p['content'] ) : '';
		},
		$home['content']
	);
}

/** First news posts (true statements only). */
function nsc_setup_posts() {
	$token   = function_exists( 'nsc_setting' ) ? nsc_setting( 'token' ) : '0x2Af2Ab246e909D2e2Ae79825023893283faD5Ef9';
	$presale = function_exists( 'nsc_setting' ) ? nsc_setting( 'presale' ) : '0x4E664ACE8Df9C5Dc69b2D23Fb31726B999B88592';
	return array(
		array(
			'slug'    => 'nsc-is-live-on-bnb-chain',
			'title'   => 'N$C Is Live on BNB Chain',
			'excerpt' => 'The NOSCAM Coin token is deployed on BNB Chain with its source code verified on BscScan: 1 billion fixed supply, a 3% tax capped in the contract, and no mint, blacklist or pause.',
			'content' => '<!-- wp:paragraph --><p>NOSCAM Coin (N$C) is now live on BNB Chain as a BEP-20 token, and its source code is verified on BscScan so anyone can read exactly what it does.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">The facts, all checkable on-chain</h2><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li><strong>Contract:</strong> <code>' . esc_html( $token ) . '</code></li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Supply:</strong> 1,000,000,000 N$C, created once. The contract has no mint function, so no more can ever be made.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>3% transfer tax:</strong> 1% is burned for good, 1% goes to the Victim Fund wallet and 1% to the Liquidity wallet.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Capped:</strong> the tax can only ever be lowered, never raised above 3%.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>No traps:</strong> no blacklist, no pause on transfers and no wallet limit, so holders can always move their tokens.</li><!-- /wp:list-item --></ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Only trust this contract address. If anyone sends you a different "N$C" address, it is not us.</p><!-- /wp:paragraph -->',
		),
		array(
			'slug'    => 'the-nsc-presale-is-open',
			'title'   => 'The N$C Presale Is Open',
			'excerpt' => 'Buy N$C directly on noscamcoin.org: connect your wallet, pay in BNB, and the presale contract sends N$C to your wallet in the same transaction.',
			'content' => '<!-- wp:paragraph --><p>The N$C presale runs on its own smart contract on BNB Chain. The price is set in US dollars and you pay in BNB; the contract uses Chainlink\'s live BNB/USD price to work out exactly how much N$C you receive.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">How it works</h2><!-- /wp:heading -->
<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><!-- wp:list-item --><li>Click <strong>Buy N$C</strong> on noscamcoin.org and connect MetaMask, Trust Wallet, Binance Wallet or another wallet.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Enter how much BNB to spend. You see the dollar value and the N$C you will get before you confirm.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Confirm in your wallet. N$C arrives in the same transaction; there is nothing to claim later.</li><!-- /wp:list-item --></ol><!-- /wp:list -->
<!-- wp:paragraph --><p>The presale contract is <code>' . esc_html( $presale ) . '</code>, verified on BscScan. Your BNB goes straight to the project treasury; the contract never holds buyers\' money.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><em>Crypto is risky. Only spend what you can afford to lose.</em></p><!-- /wp:paragraph -->',
		),
		array(
			'slug'    => 'how-to-spot-a-fake-presale',
			'title'   => 'How to Spot a Fake Crypto Presale',
			'excerpt' => 'Fake presales copy real projects and steal deposits. Five checks that take two minutes and could save your money.',
			'content' => '<!-- wp:paragraph --><p>Presale scams are one of the most common ways people lose crypto. Scammers copy a real project\'s name and logo, set up a look-alike site or a "support" account, and ask you to send funds to their own wallet. Before you buy any presale, ours included, run these checks.</p><!-- /wp:paragraph -->
<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><!-- wp:list-item --><li><strong>Check the address.</strong> Compare the contract address with the one on the project\'s official website, character by character.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Read the code.</strong> On BscScan, a verified contract shows a green tick and its source code. Unverified code is a red flag.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Ignore DMs.</strong> Real teams never message you first with a "special" address or a bonus offer.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Look for traps.</strong> Contracts that can blacklist wallets, pause transfers or raise taxes can stop you from ever selling.</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Distrust guarantees.</strong> No honest project can promise you profits.</li><!-- /wp:list-item --></ol><!-- /wp:list -->
<!-- wp:paragraph --><p>Seen a fake? Report it on our homepage so others are warned.</p><!-- /wp:paragraph -->',
		),
	);
}

function nsc_terms_content() {
	return '<!-- wp:paragraph --><p>N$C is a utility token for the NOSCAM anti-fraud ecosystem. It is not a share, a deposit or a promise of profit.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">Risks</h2><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>The value of N$C can go down as well as up, and you could lose everything you spend.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Blockchain transactions cannot be reversed. Always check addresses and amounts before you confirm.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Rules on buying and selling crypto differ by country. You are responsible for following the law where you live.</li><!-- /wp:list-item --><!-- wp:list-item --><li>Features marked "coming soon" (staking, referrals, the browser extension, DAO governance) are plans, not guarantees.</li><!-- /wp:list-item --></ul><!-- /wp:list -->
<!-- wp:heading --><h2 class="wp-block-heading">The presale</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Purchases go through the presale smart contract shown on our homepage. The price in US dollars is set by the NOSCAM team and may change; the amount of N$C you receive is shown before you confirm and is final once the transaction succeeds.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><em>Please have this page reviewed by a lawyer in your country before launch.</em></p><!-- /wp:paragraph -->';
}

/** Create or update a page by slug. */
function nsc_setup_upsert_page( $slug, $title, $content, $template = '' ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$data = array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);
	if ( $page ) {
		$data['ID'] = $page->ID;
		$id         = wp_update_post( wp_slash( $data ), true );
	} else {
		$id = wp_insert_post( wp_slash( $data ), true );
	}
	if ( ! is_wp_error( $id ) && $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	return $id;
}

/** Run the setup. Returns lines for the report. */
function nsc_setup_run() {
	$log = array();

	$home = nsc_setup_upsert_page( 'home', 'NOSCAMCOIN — The Anti-Fraud Cryptocurrency', nsc_home_content() );
	if ( is_wp_error( $home ) ) {
		return array( 'Homepage failed: ' . $home->get_error_message() );
	}
	$log[] = 'Homepage built from ' . substr_count( nsc_home_content(), '<!-- wp:html -->' ) . ' sections.';

	$news = nsc_setup_upsert_page( 'news', 'News', '' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $home );
	if ( ! is_wp_error( $news ) ) {
		update_option( 'page_for_posts', (int) $news );
	}
	$log[] = 'Homepage set as the front page; News page holds the posts.';

	$terms = get_page_by_path( 'terms' );
	if ( ! $terms ) {
		nsc_setup_upsert_page( 'terms', 'Terms & Risks', nsc_terms_content() );
		$log[] = 'Terms & Risks page created (have it checked before launch).';
	}

	$made = 0;
	foreach ( array_reverse( nsc_setup_posts() ) as $i => $p ) {
		if ( get_page_by_path( $p['slug'], OBJECT, 'post' ) ) {
			continue;
		}
		wp_insert_post(
			wp_slash(
				array(
					'post_title'   => $p['title'],
					'post_name'    => $p['slug'],
					'post_excerpt' => $p['excerpt'],
					'post_content' => $p['content'],
					'post_status'  => 'publish',
					'post_type'    => 'post',
					'post_date'    => gmdate( 'Y-m-d H:i:s', time() - ( 3 - $i ) * 60 ),
				)
			)
		);
		++$made;
	}
	$log[] = $made ? $made . ' news post(s) added.' : 'News posts already exist (left as they are).';

	// Sample content.
	foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $s ) {
		$p = get_page_by_path( $s[0], OBJECT, $s[1] );
		if ( $p && 'trash' !== $p->post_status ) {
			wp_trash_post( $p->ID );
			$log[] = 'Moved WordPress sample "' . $s[0] . '" to the trash.';
		}
	}

	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
		$log[] = 'Permalinks set to "Post name".';
	}
	flush_rewrite_rules();
	update_option( 'blogname', 'NOSCAMCOIN' );
	update_option( 'blogdescription', 'The Anti-Fraud Cryptocurrency' );
	return $log;
}

function nsc_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log = array();
	if ( isset( $_POST['nsc_setup'] ) && check_admin_referer( 'nsc_setup' ) ) {
		$log = nsc_setup_run();
	}
	echo '<div class="wrap"><h1>NOSCAM setup</h1>';
	if ( $log ) {
		echo '<div class="notice notice-success"><ul style="list-style:disc;padding-left:20px">';
		foreach ( $log as $l ) {
			echo '<li>' . esc_html( $l ) . '</li>';
		}
		echo '</ul><p><a class="button button-primary" href="' . esc_url( home_url( '/' ) ) . '" target="_blank">View the site</a></p></div>';
	}
	echo '<p>Builds the NOSCAM homepage from the theme\'s sections, sets it as the front page, adds the News page with the first posts and a Terms &amp; Risks page, and tidies WordPress\'s sample content.</p>';
	echo '<p><strong>Running it again rebuilds the homepage</strong> from the theme, replacing edits made to the homepage in the editor. News posts and other pages are left alone.</p>';
	if ( ! function_exists( 'nsc_setting' ) ) {
		echo '<div class="notice notice-warning inline"><p>Activate the <strong>NOSCAM Presale</strong> plugin first so the live figures and the Buy button work.</p></div>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'nsc_setup' );
	echo '<p><button class="button button-primary button-hero" name="nsc_setup" value="1">Build the site</button></p></form></div>';
}
