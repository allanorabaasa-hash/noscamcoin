<?php
/**
 * NOSCAM Coin child theme.
 *
 * @package nsc-child
 */

defined( 'ABSPATH' ) || exit;

require_once get_stylesheet_directory() . '/inc/shortcodes.php';
require_once get_stylesheet_directory() . '/inc/setup.php';

/** Styles and scripts. */
add_action(
	'wp_enqueue_scripts',
	function () {
		$v   = wp_get_theme()->get( 'Version' );
		$uri = get_stylesheet_directory_uri();
		wp_enqueue_style( 'nsc-site', $uri . '/assets/css/nsc.css', array(), $v );
		wp_enqueue_style( 'nsc-child', get_stylesheet_uri(), array( 'nsc-site' ), $v );
		wp_enqueue_script(
			'nsc-theme',
			$uri . '/assets/js/theme.js',
			array(),
			$v,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
);

/** Fonts: preload the two the first screen uses. */
add_action(
	'wp_head',
	function () {
		$uri = get_stylesheet_directory_uri() . '/assets/fonts/';
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $uri . 'Unbounded-Variable.woff2' ) );
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $uri . 'Manrope-Variable.woff2' ) );
		echo '<meta name="theme-color" content="#04030f">' . "\n";
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" type="image/png" href="%s">' . "\n", esc_url( get_stylesheet_directory_uri() . '/assets/images/coin.png' ) );
		}
	},
	1
);

/** Shortcodes inside the header and footer template parts (and HTML blocks). */
add_filter(
	'render_block_core/html',
	function ( $html ) {
		return false !== strpos( $html, '[' ) ? do_shortcode( $html ) : $html;
	}
);

/** Body class for the long homepage layout. */
add_filter(
	'body_class',
	function ( $c ) {
		$c[] = 'nsc-site';
		return $c;
	}
);

/** Search engines and link previews. */
add_action(
	'wp_head',
	function () {
		if ( ! is_front_page() ) {
			return;
		}
		$desc = 'NOSCAM Coin (N$C) is the anti-fraud cryptocurrency on BNB Chain. Buy N$C in the live presale, report scams and help protect crypto users.';
		$img  = get_stylesheet_directory_uri() . '/assets/images/coin-hero.jpg';
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( 'NOSCAMCOIN — The Anti-Fraud Cryptocurrency' ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}
);

/** No comments: a crypto site attracts spam, and support happens on Telegram. */
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );

/** Group the page designs in the block inserter. */
add_action(
	'init',
	function () {
		register_block_pattern_category( 'noscam', array( 'label' => __( 'NOSCAM sections', 'nsc-child' ) ) );
	}
);

/** Don't advertise the WordPress version. */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
