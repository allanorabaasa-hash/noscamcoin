<?php
/**
 * wp-admin screens: NSC Presale → Dashboard, Transactions, Holders, Owner controls,
 * Scam reports, Settings.
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

class NSC_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_nsc_sync', array( __CLASS__, 'do_sync' ) );
		add_action( 'admin_post_nsc_rescan', array( __CLASS__, 'do_rescan' ) );
		add_action( 'admin_post_nsc_export', array( __CLASS__, 'do_export' ) );
		add_action( 'admin_post_nsc_report_update', array( __CLASS__, 'do_report_update' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M10 1 2.5 4v5.2c0 4.5 3.2 8.7 7.5 9.8 4.3-1.1 7.5-5.3 7.5-9.8V4L10 1Zm0 4.2a1 1 0 0 1 1 1v3.6a1 1 0 1 1-2 0V6.2a1 1 0 0 1 1-1Zm0 7.6a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Z"/></svg>' );
		add_menu_page( 'NSC Presale', 'NSC Presale', self::CAP, 'nsc', array( __CLASS__, 'page_dashboard' ), $icon, 3 );
		add_submenu_page( 'nsc', 'NSC Dashboard', 'Dashboard', self::CAP, 'nsc', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'nsc', 'N$C Transactions', 'Transactions', self::CAP, 'nsc-transactions', array( __CLASS__, 'page_transactions' ) );
		add_submenu_page( 'nsc', 'N$C Holders', 'Holders', self::CAP, 'nsc-holders', array( __CLASS__, 'page_holders' ) );
		add_submenu_page( 'nsc', 'Owner controls', 'Owner controls', self::CAP, 'nsc-owner', array( __CLASS__, 'page_owner' ) );
		add_submenu_page( 'nsc', 'Scam reports', 'Scam reports' . self::new_reports_bubble(), self::CAP, 'nsc-reports', array( __CLASS__, 'page_reports' ) );
		add_submenu_page( 'nsc', 'NSC Settings', 'Settings', self::CAP, 'nsc-settings', array( __CLASS__, 'page_settings' ) );
	}

	private static function new_reports_bubble() {
		global $wpdb;
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . NSC_DB::t( 'reports' ) . " WHERE status = 'new'" ); // phpcs:ignore
		return $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '';
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'nsc' ) ) {
			return;
		}
		wp_enqueue_style( 'nsc-admin', NSC_URL . 'assets/admin.css', array(), NSC_VERSION );
		if ( false !== strpos( (string) $hook, 'nsc-owner' ) ) {
			wp_enqueue_script( 'nsc-admin', NSC_URL . 'assets/admin.js', array(), NSC_VERSION, true );
			$s = nsc_settings();
			wp_localize_script(
				'nsc-admin',
				'NSC_ADMIN',
				array(
					'rest'      => esc_url_raw( rest_url( 'nsc/v1/' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'chainId'   => (int) $s['chain_id'],
					'chainName' => 'testnet' === $s['network'] ? 'BNB Smart Chain Testnet' : 'BNB Smart Chain',
					'rpc'       => $s['rpc'],
					'explorer'  => $s['explorer'],
					'currency'  => $s['currency'],
					'presale'   => $s['presale'],
					'token'     => $s['token'],
				)
			);
		}
	}

	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'nsc' ) ) {
			return;
		}
		if ( 'testnet' === nsc_setting( 'network' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Test mode:</strong> the website is using BNB Smart Chain <em>Testnet</em>. Buyers pay with test BNB. Switch to mainnet in NSC Presale → Settings before launch.</p></div>';
		}
	}

	/* ------------------------------------------------------------ helpers */

	private static function addr_link( $addr, $label = '' ) {
		if ( ! $addr ) {
			return '—';
		}
		$known = self::known();
		$name  = isset( $known[ strtolower( $addr ) ] ) ? $known[ strtolower( $addr ) ] : '';
		$text  = $label ? $label : ( $name ? $name : nsc_short( $addr ) );
		return '<a href="' . esc_url( nsc_explorer( 'address', $addr ) ) . '" target="_blank" rel="noopener" title="' . esc_attr( $addr ) . '"' . ( $name ? ' class="nsc-known"' : '' ) . '>' . esc_html( $text ) . '</a>';
	}

	/** Addresses we can name. */
	private static function known() {
		static $k = null;
		if ( null !== $k ) {
			return $k;
		}
		$s = get_option( 'nsc_stats_last', array() );
		$k = array(
			strtolower( nsc_setting( 'presale' ) ) => 'Presale contract',
			strtolower( nsc_setting( 'token' ) )   => 'Token contract',
			NSC_Indexer::ZERO                       => 'Zero address (0x0)',
		);
		foreach ( array( 'owner' => 'Owner / deployer', 'victimFund' => 'Victim Fund', 'liquidityWallet' => 'Liquidity wallet', 'treasury' => 'Treasury' ) as $key => $label ) {
			if ( ! empty( $s[ $key ] ) && ! isset( $k[ strtolower( $s[ $key ] ) ] ) ) {
				$k[ strtolower( $s[ $key ] ) ] = $label;
			} elseif ( ! empty( $s[ $key ] ) ) {
				$k[ strtolower( $s[ $key ] ) ] .= ' + ' . $label;
			}
		}
		return $k;
	}

	private static function card( $label, $value, $sub = '' ) {
		echo '<div class="nsc-card"><span>' . esc_html( $label ) . '</span><strong>' . wp_kses_post( $value ) . '</strong>' . ( $sub ? '<em>' . wp_kses_post( $sub ) . '</em>' : '' ) . '</div>';
	}

	/* ------------------------------------------------------------ dashboard */

	public static function page_dashboard() {
		$s    = NSC_Chain::stats( true );
		$v    = $s['view'];
		$sync = get_option( 'nsc_sync_status', array() );
		echo '<div class="wrap nsc-wrap"><h1>NOSCAM Coin — Dashboard</h1>';
		if ( empty( $s['ok'] ) ) {
			echo '<div class="notice notice-error"><p>Could not read the contracts: ' . esc_html( isset( $s['error'] ) ? $s['error'] : '' ) . ' — check the RPC address in Settings.</p></div>';
		} elseif ( ! empty( $s['stale'] ) ) {
			echo '<div class="notice notice-warning"><p>The blockchain node did not answer just now; these are the last figures read.</p></div>';
		}
		if ( ! empty( $s['ok'] ) && empty( $s['oracle_ok'] ) ) {
			echo '<div class="notice notice-error"><p><strong>Price feed problem:</strong> the presale can\'t read the BNB/USD price, so buying is blocked. Check the feed in Owner controls.</p></div>';
		}
		echo '<div class="nsc-status ' . ( ! empty( $s['saleActive'] ) ? 'is-live' : 'is-paused' ) . '"><strong>' . ( ! empty( $s['saleActive'] ) ? '● PRESALE LIVE' : '❚❚ PRESALE PAUSED' ) . '</strong> on ' . esc_html( nsc_setting( 'net_label' ) ) . ' · <a href="' . esc_url( admin_url( 'admin.php?page=nsc-owner' ) ) . '">Owner controls →</a></div>';

		echo '<div class="nsc-cards">';
		self::card( 'N$C price', esc_html( $v['price'] ), '1 ' . esc_html( $s['currency'] ) . ' ≈ ' . esc_html( $v['nscPerBnb'] ) . ' N$C' );
		self::card( 'BNB price (Chainlink)', esc_html( $v['bnbPrice'] ) );
		self::card( 'Raised', esc_html( $v['raisedUsd'] ), esc_html( $v['raisedBnb'] ) );
		self::card( 'Presale sold', esc_html( $v['sold'] ), esc_html( NSC_Chain::num( (float) $v['soldPct'] ) ) . '% of the presale allocation' );
		self::card( 'Left in presale', esc_html( $v['left'] ) );
		self::card( 'Purchases', number_format( (int) $s['buys'] ) );
		self::card( 'Holders', number_format( (int) $s['holders'] ) );
		self::card( 'Burned (1% tax)', esc_html( $v['burned'] ) );
		echo '</div>';

		echo '<h2>Contracts and wallets</h2><table class="widefat striped nsc-kv"><tbody>';
		$rows = array(
			'Token (N$C)'          => self::addr_link( $s['token'], $s['token'] ),
			'Presale'              => self::addr_link( $s['presale'], $s['presale'] ),
			'Owner'                => self::addr_link( isset( $s['owner'] ) ? $s['owner'] : '', isset( $s['owner'] ) ? $s['owner'] : '' ),
			'Treasury (receives buyers\' BNB)' => self::addr_link( isset( $s['treasury'] ) ? $s['treasury'] : '', isset( $s['treasury'] ) ? $s['treasury'] : '' ),
			'Victim Fund (1% tax)' => self::addr_link( isset( $s['victimFund'] ) ? $s['victimFund'] : '', isset( $s['victimFund'] ) ? $s['victimFund'] : '' ),
			'Liquidity (1% tax)'   => self::addr_link( isset( $s['liquidityWallet'] ) ? $s['liquidityWallet'] : '', isset( $s['liquidityWallet'] ) ? $s['liquidityWallet'] : '' ),
		);
		foreach ( $rows as $k => $val ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . $val . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
		if ( ! empty( $s['treasury'] ) && ! empty( $s['owner'] ) && strtolower( $s['treasury'] ) === strtolower( $s['owner'] ) ) {
			echo '<p class="nsc-tip">💡 Buyers\' BNB currently goes to the deployer wallet on your computer. Consider moving it to a phone wallet with <strong>Owner controls → Treasury</strong>.</p>';
		}

		echo '<h2>Transaction record</h2><p>';
		if ( ! empty( $sync['time'] ) ) {
			echo 'Last checked ' . esc_html( human_time_diff( (int) $sync['time'] ) ) . ' ago, up to block ' . esc_html( number_format( (int) get_option( 'nsc_last_block' ) ) ) . '. ';
			if ( ! empty( $sync['error'] ) ) {
				echo '<span class="nsc-err">' . esc_html( $sync['error'] ) . '</span> ';
			}
		} else {
			echo 'Not checked yet. ';
		}
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=nsc-transactions' ) ) . '">See all transactions</a></p></div>';
	}

	/* ------------------------------------------------------------ transactions */

	public static function page_transactions() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$kind   = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$synced = isset( $_GET['synced'] ) ? sanitize_text_field( wp_unslash( $_GET['synced'] ) ) : '';
		// phpcs:enable
		$kinds = NSC_Indexer::kinds();
		if ( $kind && ! isset( $kinds[ $kind ] ) ) {
			$kind = '';
		}
		$per             = 50;
		list( $rows, $n ) = NSC_DB::transactions(
			array(
				'kind'     => $kind,
				'search'   => $search,
				'page'     => $paged,
				'per_page' => $per,
			)
		);
		$totals = NSC_DB::kind_totals();
		$sync   = get_option( 'nsc_sync_status', array() );

		echo '<div class="wrap nsc-wrap"><h1 class="wp-heading-inline">N$C Transactions</h1>';
		echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nsc-inline">';
		wp_nonce_field( 'nsc_sync' );
		echo '<input type="hidden" name="action" value="nsc_sync"><button class="page-title-action">Check for new transactions</button></form>';
		echo ' <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nsc_export&kind=' . rawurlencode( $kind ) ), 'nsc_export' ) ) . '">Download CSV</a>';
		echo '<hr class="wp-header-end">';

		$result = $synced ? get_transient( 'nsc_sync_msg_' . get_current_user_id() ) : false;
		if ( is_array( $result ) ) {
			delete_transient( 'nsc_sync_msg_' . get_current_user_id() );
			echo '<div class="notice notice-' . ( $result['note'] ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( (int) $result['added'] ) . ' new transaction(s) recorded.' . ( $result['note'] ? ' ' . esc_html( $result['note'] ) : '' ) . '</p></div>';
		}
		echo '<p class="description">Every N$C transfer on ' . esc_html( nsc_setting( 'net_label' ) ) . ', read from the blockchain every 5 minutes. ';
		if ( ! empty( $sync['time'] ) ) {
			echo 'Last checked ' . esc_html( human_time_diff( (int) $sync['time'] ) ) . ' ago (block ' . esc_html( number_format( (int) get_option( 'nsc_last_block' ) ) ) . ').';
			if ( empty( $sync['done'] ) && empty( $sync['error'] ) ) {
				echo ' <strong>Still catching up — click "Check for new transactions" again.</strong>';
			}
			if ( ! empty( $sync['error'] ) ) {
				echo ' <span class="nsc-err">' . esc_html( $sync['error'] ) . '</span>';
			}
		}
		echo '</p>';

		echo '<ul class="subsubsub"><li><a href="' . esc_url( admin_url( 'admin.php?page=nsc-transactions' ) ) . '"' . ( '' === $kind ? ' class="current"' : '' ) . '>All</a></li>';
		foreach ( $kinds as $k => $label ) {
			$c = isset( $totals[ $k ] ) ? (int) $totals[ $k ]->n : 0;
			echo '<li> | <a href="' . esc_url( admin_url( 'admin.php?page=nsc-transactions&kind=' . $k ) ) . '"' . ( $k === $kind ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( $c ) . ')</span></a></li>';
		}
		echo '</ul>';
		echo '<form method="get" class="search-box"><input type="hidden" name="page" value="nsc-transactions"><input type="hidden" name="kind" value="' . esc_attr( $kind ) . '"><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Address or tx hash"><button class="button">Search</button></form>';

		echo '<table class="widefat striped nsc-tx"><thead><tr><th>Time (UTC)</th><th>Type</th><th>From</th><th>To</th><th class="num">N$C</th><th class="num">BNB paid</th><th>Transaction</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">No transactions recorded yet.' . ( empty( $sync['time'] ) ? ' Click <strong>Check for new transactions</strong>.' : '' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r->block_time ? $r->block_time : '#' . $r->block_number ) . '</td>';
			echo '<td><span class="nsc-kind nsc-kind-' . esc_attr( $r->kind ) . '">' . esc_html( isset( $kinds[ $r->kind ] ) ? $kinds[ $r->kind ] : $r->kind ) . '</span></td>';
			echo '<td>' . self::addr_link( $r->from_addr ) . '</td><td>' . self::addr_link( $r->to_addr ) . '</td>'; // phpcs:ignore
			echo '<td class="num" title="' . esc_attr( NSC_Math::to_units( $r->amount ) ) . '">' . esc_html( NSC_Chain::num( (float) $r->amount_num, 2 ) ) . '</td>';
			echo '<td class="num">' . ( $r->bnb ? esc_html( NSC_Chain::num( (float) $r->bnb_num, 6 ) ) : '' ) . '</td>';
			echo '<td><a href="' . esc_url( nsc_explorer( 'tx', $r->tx_hash ) ) . '" target="_blank" rel="noopener">' . esc_html( nsc_short( $r->tx_hash ) ) . ' ↗</a></td></tr>';
		}
		echo '</tbody></table>';

		$pages = (int) ceil( $n / $per );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			) . '</div></div>'; // phpcs:ignore
		}
		echo '<p class="description">' . esc_html( number_format( $n ) ) . ' row(s). One purchase shows as one "Presale buy" row; a taxed transfer shows the transfer plus its burn and tax rows.</p>';
		echo '<details class="nsc-advanced"><summary>Re-scan from the start</summary><p>Deletes the recorded transactions and holders and reads them again from the token\'s creation block. Use it if you changed contract addresses or the network.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'nsc_rescan' );
		echo '<input type="hidden" name="action" value="nsc_rescan"><button class="button" onclick="return confirm(\'Delete the recorded transactions and read them again?\')">Re-scan</button></form></details></div>';
	}

	public static function do_sync() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'nsc_sync' ) ) {
			wp_die( 'Not allowed.' );
		}
		$r = NSC_Indexer::sync( 25 );
		self::sync_result( $r );
		wp_safe_redirect( admin_url( 'admin.php?page=nsc-transactions&synced=1' ) );
		exit;
	}

	public static function do_rescan() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'nsc_rescan' ) ) {
			wp_die( 'Not allowed.' );
		}
		NSC_DB::reset_index();
		$r = NSC_Indexer::sync( 25 );
		self::sync_result( $r );
		wp_safe_redirect( admin_url( 'admin.php?page=nsc-transactions&synced=1' ) );
		exit;
	}

	/** Keep a sync's outcome for the next page view (a URL would mangle the message). */
	private static function sync_result( $r ) {
		$note = (string) ( isset( $r['error'] ) ? $r['error'] : '' );
		if ( '' === $note && empty( $r['done'] ) ) {
			$note = 'Still catching up — click "Check for new transactions" again to continue.';
		}
		set_transient(
			'nsc_sync_msg_' . get_current_user_id(),
			array(
				'added' => isset( $r['added'] ) ? (int) $r['added'] : 0,
				'note'  => $note,
			),
			300
		);
	}

	public static function do_export() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'nsc_export' ) ) {
			wp_die( 'Not allowed.' );
		}
		$kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		list( $rows ) = NSC_DB::transactions(
			array(
				'kind'     => $kind,
				'per_page' => 500,
				'order'    => 'ASC',
			)
		);
		$page = 1;
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=nsc-transactions-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'time_utc', 'block', 'type', 'from', 'to', 'nsc', 'bnb_paid', 'tx_hash' ) );
		while ( $rows ) {
			foreach ( $rows as $r ) {
				fputcsv( $out, array( $r->block_time, $r->block_number, $r->kind, $r->from_addr, $r->to_addr, NSC_Math::to_units( $r->amount ), $r->bnb ? NSC_Math::to_units( $r->bnb ) : '', $r->tx_hash ) );
			}
			++$page;
			list( $rows ) = NSC_DB::transactions(
				array(
					'kind'     => $kind,
					'per_page' => 500,
					'page'     => $page,
					'order'    => 'ASC',
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ------------------------------------------------------------ holders */

	public static function page_holders() {
		$rows = NSC_DB::holders( 200 );
		echo '<div class="wrap nsc-wrap"><h1>N$C Holders</h1><p class="description">' . esc_html( number_format( NSC_DB::holder_count() ) ) . ' addresses hold N$C (from the recorded transactions).</p>';
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Address</th><th class="num">N$C</th><th class="num">Share of supply</th><th>Since block</th></tr></thead><tbody>';
		$i = 0;
		foreach ( $rows as $r ) {
			++$i;
			echo '<tr><td>' . esc_html( $i ) . '</td><td>' . self::addr_link( $r->address, $r->address ) . '</td><td class="num">' . esc_html( NSC_Chain::num( (float) $r->balance_num, 2 ) ) . '</td><td class="num">' . esc_html( NSC_Chain::num( (float) $r->balance_num / 1e9 * 100, 4 ) ) . '%</td><td>' . esc_html( $r->first_block ) . '</td></tr>'; // phpcs:ignore
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="5">No holders recorded yet.</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/* ------------------------------------------------------------ owner controls */

	public static function page_owner() {
		$s = NSC_Chain::stats( true );
		$v = $s['view'];
		$min = isset( $s['minBuyWei'] ) ? NSC_Math::to_units( $s['minBuyWei'] ) : '';
		$max = isset( $s['maxPerWalletWei'] ) ? NSC_Math::to_units( $s['maxPerWalletWei'] ) : '';
		?>
		<div class="wrap nsc-wrap" id="nsc-owner">
			<h1>Owner controls</h1>
			<p class="description">Changes here are blockchain transactions sent from your <strong>owner wallet</strong> (<?php echo self::addr_link( isset( $s['owner'] ) ? $s['owner'] : '', isset( $s['owner'] ) ? $s['owner'] : '' ); // phpcs:ignore ?>) with MetaMask. Each costs a few cents of BNB and takes effect in seconds.</p>

			<div class="nsc-connect">
				<button type="button" class="button button-primary button-hero" data-a="connect">Connect owner wallet</button>
				<span data-a="who"></span>
			</div>
			<div class="nsc-msg" data-a="msg" hidden></div>

			<div class="nsc-grid">
				<section class="nsc-box">
					<h2>Sale</h2>
					<p>Now: <strong class="<?php echo ! empty( $s['saleActive'] ) ? 'nsc-ok' : 'nsc-err'; ?>"><?php echo ! empty( $s['saleActive'] ) ? 'LIVE — buyers can buy' : 'PAUSED — buying is blocked'; ?></strong></p>
					<p><button type="button" class="button button-primary" data-a="open">Open the sale</button> <button type="button" class="button" data-a="pause">Pause the sale</button></p>
				</section>

				<section class="nsc-box">
					<h2>Price</h2>
					<p>Now: <strong><?php echo esc_html( $v['price'] ); ?></strong> per N$C · BNB <?php echo esc_html( $v['bnbPrice'] ); ?> · 1 BNB buys <?php echo esc_html( $v['nscPerBnb'] ); ?> N$C</p>
					<label>New price in US dollars <input type="text" inputmode="decimal" data-a="price" placeholder="0.0042" value=""></label>
					<p class="description" data-a="price-preview"></p>
					<button type="button" class="button button-primary" data-a="set-price">Set price</button>
				</section>

				<section class="nsc-box">
					<h2>Treasury</h2>
					<p>Buyers' BNB goes to: <?php echo self::addr_link( isset( $s['treasury'] ) ? $s['treasury'] : '', isset( $s['treasury'] ) ? $s['treasury'] : '' ); // phpcs:ignore ?></p>
					<label>New treasury address <input type="text" data-a="treasury" placeholder="0x…" spellcheck="false"></label>
					<button type="button" class="button" data-a="set-treasury">Change treasury</button>
				</section>

				<section class="nsc-box">
					<h2>Purchase limits</h2>
					<label>Smallest purchase (BNB) <input type="text" inputmode="decimal" data-a="min" value="<?php echo esc_attr( $min ); ?>"></label>
					<label>Most one wallet can spend in total (BNB, 0 = no limit) <input type="text" inputmode="decimal" data-a="max" value="<?php echo esc_attr( $max ); ?>"></label>
					<button type="button" class="button" data-a="set-limits">Save limits</button>
				</section>

				<section class="nsc-box">
					<h2>Unsold N$C</h2>
					<p>In the presale: <strong><?php echo esc_html( $v['left'] ); ?></strong></p>
					<label>Send to <input type="text" data-a="wd-to" placeholder="0x…" spellcheck="false" value="<?php echo esc_attr( isset( $s['owner'] ) ? $s['owner'] : '' ); ?>"></label>
					<label>Amount of N$C <input type="text" inputmode="decimal" data-a="wd-amount" placeholder="e.g. 1000000"></label>
					<button type="button" class="button" data-a="withdraw">Take N$C out of the presale</button>
				</section>
			</div>
			<p class="description">Every change is public on <a href="<?php echo esc_url( nsc_explorer( 'address', nsc_setting( 'presale' ) ) ); ?>" target="_blank" rel="noopener">BscScan</a>. The presale's ownership can't be renounced, so you can't lock yourself out by accident.</p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------ reports */

	public static function page_reports() {
		global $wpdb;
		$t      = NSC_DB::t( 'reports' );
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore
		$all    = array( 'new' => 'New', 'reviewing' => 'Reviewing', 'verified' => 'Verified', 'rejected' => 'Rejected' );
		$where  = isset( $all[ $status ] ) ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
		$rows   = $wpdb->get_results( "SELECT * FROM $t $where ORDER BY created DESC LIMIT 200" ); // phpcs:ignore
		echo '<div class="wrap nsc-wrap"><h1>Scam reports</h1><ul class="subsubsub"><li><a href="' . esc_url( admin_url( 'admin.php?page=nsc-reports' ) ) . '">All</a></li>';
		foreach ( $all as $k => $label ) {
			echo '<li> | <a href="' . esc_url( admin_url( 'admin.php?page=nsc-reports&status=' . $k ) ) . '"' . ( $k === $status ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul><table class="widefat striped nsc-reports"><thead><tr><th>Received (UTC)</th><th>Type</th><th>Scammer</th><th>What happened</th><th class="num">Lost (USD)</th><th>Reporter</th><th>Status</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">No reports yet.</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r->created ) . '</td><td>' . esc_html( $r->scam_type ) . '</td><td><code>' . esc_html( $r->target ) . '</code></td><td><div class="nsc-details">' . nl2br( esc_html( $r->details ) ) . '</div></td><td class="num">' . ( null !== $r->amount_usd ? esc_html( number_format( (float) $r->amount_usd, 2 ) ) : '' ) . '</td><td>' . esc_html( $r->reporter_name ? $r->reporter_name : 'Anonymous' ) . ( $r->reporter_email ? '<br><a href="mailto:' . esc_attr( $r->reporter_email ) . '">' . esc_html( $r->reporter_email ) . '</a>' : '' ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'nsc_report_update_' . $r->id );
			echo '<input type="hidden" name="action" value="nsc_report_update"><input type="hidden" name="id" value="' . esc_attr( $r->id ) . '"><select name="status">';
			foreach ( $all as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $r->status, $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '<option value="delete">Delete</option></select><textarea name="staff_note" rows="2" placeholder="Note (staff only)">' . esc_textarea( (string) $r->staff_note ) . '</textarea><button class="button button-small">Save</button></form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function do_report_update() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'nsc_report_update_' . $id ) ) {
			wp_die( 'Not allowed.' );
		}
		global $wpdb;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'new';
		if ( 'delete' === $status ) {
			$wpdb->delete( NSC_DB::t( 'reports' ), array( 'id' => $id ), array( '%d' ) );
		} elseif ( in_array( $status, array( 'new', 'reviewing', 'verified', 'rejected' ), true ) ) {
			$wpdb->update(
				NSC_DB::t( 'reports' ),
				array(
					'status'     => $status,
					'staff_note' => isset( $_POST['staff_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['staff_note'] ) ) : '',
				),
				array( 'id' => $id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=nsc-reports' ) );
		exit;
	}

	/* ------------------------------------------------------------ settings */

	public static function register_settings() {
		register_setting(
			'nsc_settings',
			'nsc_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	public static function sanitize( $in ) {
		$old = get_option( 'nsc_settings', array() );
		$out = is_array( $old ) ? $old : array();
		$in  = is_array( $in ) ? $in : array();
		$nets           = nsc_networks();
		$out['network'] = isset( $in['network'], $nets[ $in['network'] ] ) ? $in['network'] : 'mainnet';
		foreach ( array( 'rpc', 'logs_rpc', 'whitepaper', 'social_x', 'social_telegram', 'social_youtube', 'social_linkedin', 'social_instagram', 'social_whatsapp' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? esc_url_raw( trim( $in[ $k ] ) ) : '';
		}
		foreach ( array( 'token', 'presale' ) as $k ) {
			$v         = isset( $in[ $k ] ) ? trim( $in[ $k ] ) : '';
			$out[ $k ] = preg_match( '/^0x[0-9a-fA-F]{40}$/', $v ) ? $v : '';
		}
		$out['start_block']   = isset( $in['start_block'] ) && ctype_digit( trim( $in['start_block'] ) ) ? trim( $in['start_block'] ) : '';
		$out['chunk']         = isset( $in['chunk'] ) ? max( 100, min( 50000, absint( $in['chunk'] ) ) ) : 5000;
		$out['walletconnect'] = isset( $in['walletconnect'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( $in['walletconnect'] ) ) : '';
		$out['report_email']  = isset( $in['report_email'] ) ? sanitize_email( $in['report_email'] ) : '';

		// A different network or contract means the recorded transactions no longer match.
		$changed = ( isset( $old['network'] ) ? $old['network'] : 'mainnet' ) !== $out['network']
			|| ( isset( $old['token'] ) ? $old['token'] : '' ) !== $out['token']
			|| ( isset( $old['presale'] ) ? $old['presale'] : '' ) !== $out['presale'];
		if ( $changed && get_option( 'nsc_settings' ) !== false ) {
			NSC_DB::reset_index();
			if ( ( isset( $old['network'] ) ? $old['network'] : 'mainnet' ) !== $out['network'] && ( ! isset( $in['start_block'] ) || '' === trim( $in['start_block'] ) || ( isset( $old['start_block'] ) && $old['start_block'] === $out['start_block'] ) ) ) {
				$out['start_block'] = '';
			}
		}
		delete_transient( 'nsc_stats' );
		delete_option( 'nsc_stats_last' );
		return $out;
	}

	public static function page_settings() {
		$s    = get_option( 'nsc_settings', array() );
		$e    = nsc_settings();
		$nets = nsc_networks();
		$f    = function ( $key, $label, $help = '', $ph = '' ) use ( $s ) {
			echo '<tr><th><label for="nsc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text code" id="nsc-' . esc_attr( $key ) . '" name="nsc_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( isset( $s[ $key ] ) ? $s[ $key ] : '' ) . '" placeholder="' . esc_attr( $ph ) . '">' . ( $help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		echo '<div class="wrap nsc-wrap"><h1>NSC Settings</h1><form method="post" action="options.php">';
		settings_fields( 'nsc_settings' );
		echo '<h2>Blockchain</h2><table class="form-table"><tr><th>Network</th><td><select name="nsc_settings[network]">';
		foreach ( $nets as $k => $n ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $e['network'], $k, false ) . '>' . esc_html( $n['label'] ) . '</option>';
		}
		echo '</select><p class="description">Mainnet for the real sale. Testnet to try things with free test BNB.</p></td></tr>';
		$f( 'token', 'Token contract', 'Leave blank for the default.', $nets[ $e['network'] ]['token'] );
		$f( 'presale', 'Presale contract', 'Leave blank for the default.', $nets[ $e['network'] ]['presale'] );
		$f( 'rpc', 'RPC for reading figures', 'Leave blank for the default.', $nets[ $e['network'] ]['rpc'] );
		$f( 'logs_rpc', 'RPC for the transaction record', 'Must allow <code>eth_getLogs</code>. Leave blank for the default.', $nets[ $e['network'] ]['logs_rpc'] );
		$f( 'start_block', 'Token creation block', 'Where the transaction record starts. Found automatically when possible; otherwise copy it from BscScan (token page → Contract Creator transaction → Block).' );
		$f( 'chunk', 'Blocks per request', 'Lower it if the transaction record reports "range too large".', '5000' );
		echo '</table><h2>Wallets</h2><table class="form-table">';
		$f( 'walletconnect', 'WalletConnect project ID', 'Optional. Lets buyers on phones connect any wallet by QR code. Free at <a href="https://cloud.reown.com" target="_blank" rel="noopener">cloud.reown.com</a> (add your domain to the project\'s allowed list).' );
		echo '</table><h2>Website</h2><table class="form-table">';
		$f( 'whitepaper', 'Whitepaper link' );
		$f( 'report_email', 'Email new scam reports to' );
		$f( 'social_x', 'X (Twitter)' );
		$f( 'social_telegram', 'Telegram' );
		$f( 'social_youtube', 'YouTube' );
		$f( 'social_linkedin', 'LinkedIn' );
		$f( 'social_instagram', 'Instagram' );
		$f( 'social_whatsapp', 'WhatsApp link', 'e.g. https://wa.me/2567…' );
		echo '</table>';
		submit_button();
		echo '</form></div>';
	}
}
