<?php
/**
 * Records every N$C transaction from the blockchain into the site's database.
 *
 * Reads the token's Transfer events and the presale's Bought events in block ranges
 * (eth_getLogs), labels each transfer, and keeps a running balance per address.
 * Runs every 5 minutes by WP-Cron, and on demand from NSC Presale → Transactions.
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

class NSC_Indexer {

	const ZERO = '0x0000000000000000000000000000000000000000';

	/** Kinds, as shown in the admin. */
	public static function kinds() {
		return array(
			'buy'          => 'Presale buy',
			'transfer'     => 'Transfer',
			'tax'          => 'Tax (Victim Fund / Liquidity)',
			'burn'         => 'Burn',
			'presale_fund' => 'Loaded into presale',
			'presale_out'  => 'Taken out of presale',
			'mint'         => 'Created (mint)',
		);
	}

	/**
	 * Catch up from the last recorded block to the newest one (or until the time budget runs out).
	 *
	 * @param int $budget Seconds to spend.
	 * @return array Summary: new rows, from/to block, done flag, error.
	 */
	public static function sync( $budget = 20 ) {
		if ( get_transient( 'nsc_sync_lock' ) ) {
			return array( 'error' => 'A sync is already running. Try again in a minute.' );
		}
		set_transient( 'nsc_sync_lock', 1, 90 );
		$started = microtime( true );
		$summary = array(
			'added' => 0,
			'from'  => 0,
			'to'    => 0,
			'done'  => false,
			'error' => '',
		);

		$url    = nsc_setting( 'logs_rpc' );
		$latest = NSC_Chain::block_number( $url );
		if ( is_wp_error( $latest ) ) {
			delete_transient( 'nsc_sync_lock' );
			$summary['error'] = 'Blockchain node not reachable: ' . $latest->get_error_message();
			update_option( 'nsc_sync_status', $summary + array( 'time' => time() ), false );
			return $summary;
		}

		$next = self::next_block( $latest, $url );
		if ( is_wp_error( $next ) ) {
			delete_transient( 'nsc_sync_lock' );
			$summary['error'] = $next->get_error_message();
			update_option( 'nsc_sync_status', $summary + array( 'time' => time() ), false );
			return $summary;
		}
		$summary['from'] = $next;
		$chunk           = max( 100, (int) nsc_setting( 'chunk' ) );

		while ( $next <= $latest && ( microtime( true ) - $started ) < $budget ) {
			$to   = min( $latest, $next + $chunk - 1 );
			$logs = self::get_logs( $next, $to, $url );
			if ( is_wp_error( $logs ) ) {
				if ( $chunk > 100 ) {
					$chunk = max( 100, (int) floor( $chunk / 2 ) ); // Node said the range was too big: try smaller.
					continue;
				}
				$summary['error'] = 'Reading blocks ' . $next . '–' . $to . ' failed: ' . $logs->get_error_message();
				break;
			}
			$summary['added'] += self::store( $logs, $url );
			update_option( 'nsc_last_block', $to, false );
			$summary['to'] = $to;
			$next          = $to + 1;
		}
		$summary['done'] = $next > $latest;
		$summary['time'] = time();
		update_option( 'nsc_sync_status', $summary, false );
		if ( $summary['added'] ) {
			delete_transient( 'nsc_stats' );
		}
		delete_transient( 'nsc_sync_lock' );
		return $summary;
	}

	/** First block still to read. */
	private static function next_block( $latest, $url ) {
		$last = get_option( 'nsc_last_block' );
		if ( '' !== (string) $last && false !== $last ) {
			return (int) $last + 1;
		}
		$start = trim( (string) nsc_setting( 'start_block' ) );
		if ( '' !== $start && ctype_digit( $start ) ) {
			return (int) $start;
		}
		$found = self::find_deploy_block( $latest, $url );
		if ( is_wp_error( $found ) ) {
			return new WP_Error( 'nsc_start', 'Enter the token\'s creation block in NSC Presale → Settings → "Token creation block". Find it on BscScan: open the token address, click the transaction next to "Contract Creator", and copy its Block number. (The public node would not let the site look it up automatically.)' );
		}
		$s                = get_option( 'nsc_settings', array() );
		$s['start_block'] = (string) $found;
		update_option( 'nsc_settings', $s );
		return $found;
	}

	/**
	 * Binary-search the block where the token contract first has code.
	 * Needs a node that answers eth_getCode for old blocks; returns WP_Error if not.
	 */
	public static function find_deploy_block( $latest, $url ) {
		$token = nsc_setting( 'token' );
		$lo    = 0;
		$hi    = $latest;
		$code  = NSC_Chain::rpc( 'eth_getCode', array( $token, '0x' . dechex( $hi ) ), $url );
		if ( is_wp_error( $code ) || ! is_string( $code ) || strlen( $code ) < 4 ) {
			return new WP_Error( 'nsc_start', is_wp_error( $code ) ? $code->get_error_message() : 'the token has no code at the latest block — check the token address and network' );
		}
		for ( $i = 0; $i < 64 && $lo < $hi; $i++ ) {
			$mid  = (int) floor( ( $lo + $hi ) / 2 );
			$code = NSC_Chain::rpc( 'eth_getCode', array( $token, '0x' . dechex( $mid ) ), $url );
			if ( is_wp_error( $code ) ) {
				return $code;
			}
			if ( is_string( $code ) && strlen( $code ) > 2 ) {
				$hi = $mid;
			} else {
				$lo = $mid + 1;
			}
		}
		return $hi;
	}

	/** Token Transfer + presale Bought logs in a block range. */
	private static function get_logs( $from, $to, $url ) {
		return NSC_Chain::rpc(
			'eth_getLogs',
			array(
				array(
					'fromBlock' => '0x' . dechex( $from ),
					'toBlock'   => '0x' . dechex( $to ),
					'address'   => array( nsc_setting( 'token' ), nsc_setting( 'presale' ) ),
					'topics'    => array( array( NSC_Chain::TOPIC_TRANSFER, NSC_Chain::TOPIC_BOUGHT ) ),
				),
			),
			$url
		);
	}

	/** Topic (32 bytes) to an address. */
	private static function topic_addr( $topic ) {
		return '0x' . strtolower( substr( (string) $topic, -40 ) );
	}

	/**
	 * Label and save a batch of logs. Returns the number of new rows.
	 *
	 * @param array  $logs Logs from eth_getLogs.
	 * @param string $url  RPC for block timestamps.
	 */
	public static function store( $logs, $url = '' ) {
		global $wpdb;
		if ( ! is_array( $logs ) || ! $logs ) {
			return 0;
		}
		$token   = strtolower( nsc_setting( 'token' ) );
		$presale = strtolower( nsc_setting( 'presale' ) );
		list( $victim, $liq ) = self::tax_wallets();

		// Group by transaction.
		$bought    = array(); // tx => bnb wei.
		$transfers = array();
		$blocks    = array();
		foreach ( $logs as $log ) {
			$addr  = strtolower( $log['address'] );
			$topic = strtolower( $log['topics'][0] );
			$tx    = strtolower( $log['transactionHash'] );
			if ( $addr === $presale && NSC_Chain::TOPIC_BOUGHT === $topic ) {
				$bought[ $tx ] = NSC_Math::hex2dec( substr( $log['data'], 2, 64 ) );
			} elseif ( $addr === $token && NSC_Chain::TOPIC_TRANSFER === $topic && count( $log['topics'] ) >= 3 ) {
				$transfers[] = array(
					'block' => hexdec( $log['blockNumber'] ),
					'index' => hexdec( $log['logIndex'] ),
					'tx'    => $tx,
					'from'  => self::topic_addr( $log['topics'][1] ),
					'to'    => self::topic_addr( $log['topics'][2] ),
					'amt'   => NSC_Math::hex2dec( $log['data'] ),
				);
				$blocks[ hexdec( $log['blockNumber'] ) ] = true;
			}
		}
		if ( ! $transfers ) {
			return 0;
		}

		// Which transactions included a burn (= taxed transfers)?
		$taxed = array();
		foreach ( $transfers as $tr ) {
			if ( self::ZERO === $tr['to'] && self::ZERO !== $tr['from'] ) {
				$taxed[ $tr['tx'] ] = true;
			}
		}

		$times = self::block_times( array_keys( $blocks ), $url );
		$added = 0;
		usort(
			$transfers,
			function ( $a, $b ) {
				return array( $a['block'], $a['index'] ) <=> array( $b['block'], $b['index'] );
			}
		);
		foreach ( $transfers as $tr ) {
			$kind = 'transfer';
			$bnb  = null;
			if ( self::ZERO === $tr['from'] ) {
				$kind = 'mint';
			} elseif ( self::ZERO === $tr['to'] ) {
				$kind = 'burn';
			} elseif ( $tr['from'] === $presale ) {
				if ( isset( $bought[ $tr['tx'] ] ) ) {
					$kind = 'buy';
					$bnb  = $bought[ $tr['tx'] ];
				} else {
					$kind = 'presale_out';
				}
			} elseif ( $tr['to'] === $presale ) {
				$kind = 'presale_fund';
			} elseif ( isset( $taxed[ $tr['tx'] ] ) && ( $tr['to'] === $victim || $tr['to'] === $liq ) ) {
				$kind = 'tax';
			}
			$ok = $wpdb->insert(
				NSC_DB::t( 'tx' ),
				array(
					'block_number' => $tr['block'],
					'block_time'   => isset( $times[ $tr['block'] ] ) ? gmdate( 'Y-m-d H:i:s', $times[ $tr['block'] ] ) : null,
					'tx_hash'      => $tr['tx'],
					'log_index'    => $tr['index'],
					'kind'         => $kind,
					'from_addr'    => $tr['from'],
					'to_addr'      => $tr['to'],
					'amount'       => $tr['amt'],
					'amount_num'   => NSC_Math::to_float( $tr['amt'] ),
					'bnb'          => $bnb,
					'bnb_num'      => null === $bnb ? 0 : NSC_Math::to_float( $bnb ),
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%f' )
			);
			if ( ! $ok ) {
				continue; // Already recorded (unique tx + log index).
			}
			++$added;
			if ( self::ZERO !== $tr['from'] ) {
				NSC_DB::bump_balance( $tr['from'], $tr['amt'], false, $tr['block'] );
			}
			if ( self::ZERO !== $tr['to'] ) {
				NSC_DB::bump_balance( $tr['to'], $tr['amt'], true, $tr['block'] );
			}
		}
		return $added;
	}

	/** The token's Victim Fund and Liquidity wallets (read from the token, cached for an hour). */
	private static function tax_wallets() {
		$cached = get_transient( 'nsc_tax_wallets' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$token = nsc_setting( 'token' );
		$res   = NSC_Chain::rpc(
			array(
				array( 'eth_call', array( array( 'to' => $token, 'data' => NSC_Chain::SEL['victimFundWallet'] ), 'latest' ) ),
				array( 'eth_call', array( array( 'to' => $token, 'data' => NSC_Chain::SEL['liquidityWallet'] ), 'latest' ) ),
			)
		);
		if ( is_wp_error( $res ) || count( $res ) < 2 ) {
			return array( '', '' );
		}
		$out = array( strtolower( (string) NSC_Chain::decode( $res[0], 'address' ) ), strtolower( (string) NSC_Chain::decode( $res[1], 'address' ) ) );
		set_transient( 'nsc_tax_wallets', $out, HOUR_IN_SECONDS );
		return $out;
	}

	/** Block number => unix time, in one batch request. */
	private static function block_times( $numbers, $url ) {
		if ( ! $numbers ) {
			return array();
		}
		$calls = array();
		foreach ( $numbers as $n ) {
			$calls[] = array( 'eth_getBlockByNumber', array( '0x' . dechex( $n ), false ) );
		}
		$out = array();
		foreach ( array_chunk( $calls, 50 ) as $part ) {
			$res = NSC_Chain::rpc( $part, array(), $url );
			if ( is_wp_error( $res ) ) {
				continue;
			}
			foreach ( $res as $b ) {
				if ( is_array( $b ) && isset( $b['number'], $b['timestamp'] ) ) {
					$out[ hexdec( $b['number'] ) ] = hexdec( $b['timestamp'] );
				}
			}
		}
		return $out;
	}
}
