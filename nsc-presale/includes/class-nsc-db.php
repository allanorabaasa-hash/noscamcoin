<?php
/**
 * Database tables and queries (all with $wpdb->prepare()).
 *
 * nsc_tx       every N$C token transfer seen on-chain, with presale buys marked
 * nsc_holders  current N$C balance of every address that ever held N$C
 * nsc_reports  scam reports sent from the website
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

class NSC_DB {

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'nsc_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		dbDelta(
			'CREATE TABLE ' . self::t( 'tx' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			block_number bigint(20) unsigned NOT NULL,
			block_time datetime DEFAULT NULL,
			tx_hash varchar(66) NOT NULL,
			log_index int(10) unsigned NOT NULL,
			kind varchar(20) NOT NULL DEFAULT 'transfer',
			from_addr varchar(42) NOT NULL,
			to_addr varchar(42) NOT NULL,
			amount varchar(80) NOT NULL,
			amount_num double NOT NULL DEFAULT 0,
			bnb varchar(80) DEFAULT NULL,
			bnb_num double NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY tx_log (tx_hash,log_index),
			KEY block_number (block_number),
			KEY kind (kind),
			KEY from_addr (from_addr),
			KEY to_addr (to_addr)
		) $c;"
		);

		dbDelta(
			'CREATE TABLE ' . self::t( 'holders' ) . " (
			address varchar(42) NOT NULL,
			balance varchar(80) NOT NULL DEFAULT '0',
			balance_num double NOT NULL DEFAULT 0,
			first_block bigint(20) unsigned NOT NULL DEFAULT 0,
			last_block bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (address),
			KEY balance_num (balance_num)
		) $c;"
		);

		dbDelta(
			'CREATE TABLE ' . self::t( 'reports' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			reporter_name varchar(120) NOT NULL DEFAULT '',
			reporter_email varchar(190) NOT NULL DEFAULT '',
			scam_type varchar(80) NOT NULL DEFAULT '',
			target varchar(255) NOT NULL DEFAULT '',
			details text NOT NULL,
			amount_usd decimal(14,2) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'new',
			staff_note text,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created (created)
		) $c;"
		);

		update_option( 'nsc_db_version', NSC_DB_VERSION );
	}

	/** Addresses holding more than zero N$C (the zero address excluded). */
	public static function holder_count() {
		global $wpdb;
		$t = self::t( 'holders' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE balance <> '0' AND address <> '0x0000000000000000000000000000000000000000'" );
	}

	/** Number of presale purchases. */
	public static function buy_count() {
		global $wpdb;
		$t = self::t( 'tx' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE kind = 'buy'" );
	}

	/**
	 * Transactions for the admin list and the public feed.
	 *
	 * @param array $args kind, address, search, page, per_page, order.
	 * @return array [ rows, total ]
	 */
	public static function transactions( $args = array() ) {
		global $wpdb;
		$a     = wp_parse_args(
			$args,
			array(
				'kind'     => '',
				'address'  => '',
				'search'   => '',
				'page'     => 1,
				'per_page' => 50,
				'order'    => 'DESC',
			)
		);
		$t     = self::t( 'tx' );
		$where = array( '1=1' );
		$vals  = array();
		if ( $a['kind'] ) {
			$where[] = 'kind = %s';
			$vals[]  = $a['kind'];
		}
		if ( $a['address'] ) {
			$where[] = '(from_addr = %s OR to_addr = %s)';
			$vals[]  = strtolower( $a['address'] );
			$vals[]  = strtolower( $a['address'] );
		}
		if ( $a['search'] ) {
			$like    = '%' . $wpdb->esc_like( strtolower( $a['search'] ) ) . '%';
			$where[] = '(tx_hash LIKE %s OR from_addr LIKE %s OR to_addr LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}
		$order  = 'ASC' === $a['order'] ? 'ASC' : 'DESC';
		$per    = max( 1, min( 500, (int) $a['per_page'] ) );
		$offset = ( max( 1, (int) $a['page'] ) - 1 ) * $per;
		$w      = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM $t WHERE $w";
		$rows_sql  = "SELECT * FROM $t WHERE $w ORDER BY block_number $order, log_index $order LIMIT %d OFFSET %d";
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $vals, array( $per, $offset ) ) ) );
		// phpcs:enable
		return array( $rows, $total );
	}

	/** Totals by kind for the dashboard. */
	public static function kind_totals() {
		global $wpdb;
		$t = self::t( 'tx' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT kind, COUNT(*) AS n, SUM(amount_num) AS nsc, SUM(bnb_num) AS bnb FROM $t GROUP BY kind", OBJECT_K );
	}

	/** Top holders. */
	public static function holders( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$t = self::t( 'holders' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE balance <> '0' ORDER BY balance_num DESC LIMIT %d OFFSET %d", $limit, $offset ) );
	}

	/** Add a signed change to an address's balance. */
	public static function bump_balance( $address, $amount, $add, $block ) {
		global $wpdb;
		$t   = self::t( 'holders' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT balance, first_block FROM $t WHERE address = %s", $address ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$old = $row ? $row->balance : '0';
		$new = $add ? NSC_Math::add( $old, $amount ) : NSC_Math::sub( $old, $amount );
		$wpdb->replace(
			$t,
			array(
				'address'     => $address,
				'balance'     => $new,
				'balance_num' => NSC_Math::to_float( $new ),
				'first_block' => $row ? (int) $row->first_block : (int) $block,
				'last_block'  => (int) $block,
			),
			array( '%s', '%s', '%f', '%d', '%d' )
		);
	}

	/** Empty the transaction and holder tables (for a full re-scan). */
	public static function reset_index() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . self::t( 'tx' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DELETE FROM ' . self::t( 'holders' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		delete_option( 'nsc_last_block' );
		delete_transient( 'nsc_stats' );
		delete_transient( 'nsc_tax_wallets' );
	}
}
