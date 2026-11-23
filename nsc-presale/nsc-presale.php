<?php
/**
 * Plugin Name:       NOSCAM Presale
 * Plugin URI:        https://github.com/allanorabaasa-hash/noscamcoin
 * Description:       Connects the NOSCAM Coin website to the N$C token and presale contracts on BNB Chain: live presale stats, the Buy N$C box (any wallet), a record of every N$C transaction, owner controls and the scam-report inbox.
 * Version:           1.0.1
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Allan
 * License:           GPL-2.0-or-later
 * Text Domain:       nsc-presale
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

define( 'NSC_VERSION', '1.0.1' );
define( 'NSC_DB_VERSION', '1' );
define( 'NSC_FILE', __FILE__ );
define( 'NSC_DIR', plugin_dir_path( __FILE__ ) );
define( 'NSC_URL', plugin_dir_url( __FILE__ ) );

require_once NSC_DIR . 'includes/class-nsc-math.php';
require_once NSC_DIR . 'includes/class-nsc-chain.php';
require_once NSC_DIR . 'includes/class-nsc-db.php';
require_once NSC_DIR . 'includes/class-nsc-indexer.php';
require_once NSC_DIR . 'includes/class-nsc-rest.php';
require_once NSC_DIR . 'includes/class-nsc-public.php';
require_once NSC_DIR . 'includes/class-nsc-reports.php';
if ( is_admin() ) {
	require_once NSC_DIR . 'includes/class-nsc-admin.php';
}

/**
 * Networks the site can talk to. Mainnet is the live sale; testnet is for trying changes safely.
 */
function nsc_networks() {
	return apply_filters( 'nsc_networks', array(
		'mainnet' => array(
			'label'    => 'BNB Chain (mainnet)',
			'chain_id' => 56,
			'rpc'      => 'https://bsc-dataseed.bnbchain.org',
			'logs_rpc' => 'https://bsc-rpc.publicnode.com',
			'explorer' => 'https://bscscan.com',
			'token'    => '0x2Af2Ab246e909D2e2Ae79825023893283faD5Ef9',
			'presale'  => '0x4E664ACE8Df9C5Dc69b2D23Fb31726B999B88592',
			'currency' => 'BNB',
		),
		'testnet' => array(
			'label'    => 'BNB Smart Chain Testnet',
			'chain_id' => 97,
			'rpc'      => 'https://data-seed-prebsc-1-s1.bnbchain.org:8545',
			'logs_rpc' => 'https://bsc-testnet-rpc.publicnode.com',
			'explorer' => 'https://testnet.bscscan.com',
			'token'    => '0x2Af2Ab246e909D2e2Ae79825023893283faD5Ef9',
			'presale'  => '0x5A2c6AeBf96904a6003eCbAec3A6965d220689C0',
			'currency' => 'tBNB',
		),
	) );
}

/**
 * Settings with defaults. Blank fields fall back to the chosen network's defaults.
 */
function nsc_settings() {
	$saved    = get_option( 'nsc_settings', array() );
	$defaults = array(
		'network'         => 'mainnet',
		'rpc'             => '',
		'logs_rpc'        => '',
		'token'           => '',
		'presale'         => '',
		'start_block'     => '',
		'chunk'           => 5000,
		'walletconnect'   => '',
		'whitepaper'      => 'https://heyzine.com/flip-book/cbfb32e708.html',
		'report_email'    => get_option( 'admin_email' ),
		'social_x'        => 'https://x.com/NoscamEcosystem',
		'social_telegram' => 'https://t.me/notocryptoscamss',
		'social_youtube'  => 'https://www.youtube.com/@noscamcointv',
		'social_linkedin' => 'https://www.linkedin.com/groups/9867218',
		'social_instagram' => '',
		'social_whatsapp' => '',
	);
	$s       = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	$nets    = nsc_networks();
	$net     = isset( $nets[ $s['network'] ] ) ? $nets[ $s['network'] ] : $nets['mainnet'];
	$s['network'] = isset( $nets[ $s['network'] ] ) ? $s['network'] : 'mainnet';
	foreach ( array( 'rpc', 'logs_rpc', 'token', 'presale' ) as $key ) {
		if ( '' === trim( (string) $s[ $key ] ) ) {
			$s[ $key ] = $net[ $key ];
		}
	}
	$s['chain_id'] = $net['chain_id'];
	$s['explorer'] = $net['explorer'];
	$s['currency'] = $net['currency'];
	$s['net_label'] = $net['label'];
	return $s;
}

/** One setting. */
function nsc_setting( $key ) {
	$s = nsc_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/** Link to an address or transaction on BscScan. */
function nsc_explorer( $type, $value ) {
	return trailingslashit( nsc_setting( 'explorer' ) ) . ( 'tx' === $type ? 'tx/' : ( 'token' === $type ? 'token/' : 'address/' ) ) . $value;
}

/** Short form of an address or hash: 0x1234…abcd. */
function nsc_short( $hex ) {
	$hex = (string) $hex;
	return strlen( $hex ) > 12 ? substr( $hex, 0, 6 ) . '…' . substr( $hex, -4 ) : $hex;
}

/* ---------------------------------------------------------------- activation */

register_activation_hook( __FILE__, 'nsc_activate' );
function nsc_activate() {
	NSC_DB::install();
	if ( ! wp_next_scheduled( 'nsc_sync_event' ) ) {
		wp_schedule_event( time() + 60, 'nsc_five_minutes', 'nsc_sync_event' );
	}
}

register_deactivation_hook( __FILE__, 'nsc_deactivate' );
function nsc_deactivate() {
	wp_clear_scheduled_hook( 'nsc_sync_event' );
}

add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'nsc_db_version' ) !== NSC_DB_VERSION ) {
			NSC_DB::install();
		}
	}
);

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['nsc_five_minutes'] = array(
			'interval' => 300,
			'display'  => 'Every 5 minutes (NOSCAM transactions)',
		);
		return $s;
	}
);

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'nsc_sync_event' ) ) {
			wp_schedule_event( time() + 60, 'nsc_five_minutes', 'nsc_sync_event' );
		}
	}
);
add_action( 'nsc_sync_event', array( 'NSC_Indexer', 'sync' ) );

NSC_Rest::init();
NSC_Public::init();
NSC_Reports::init();
if ( is_admin() ) {
	NSC_Admin::init();
}
