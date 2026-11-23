<?php
/**
 * Reads the N$C contracts on BNB Chain over JSON-RPC.
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

class NSC_Chain {

	/** Function selectors (first 4 bytes of keccak256 of the signature). */
	const SEL = array(
		// Presale.
		'priceUsd'         => '0x8b3948bd',
		'bnbUsd'           => '0x58614dbf',
		'tokensLeft'       => '0xb31f8f93',
		'totalSold'        => '0x9106d7ba',
		'totalRaised'      => '0xc5c4744c',
		'saleActive'       => '0x68428a1b',
		'minBuyWei'        => '0x01e6f521',
		'maxPerWalletWei'  => '0xed5f6412',
		'treasury'         => '0x61d027b3',
		'owner'            => '0x8da5cb5b',
		// Token.
		'totalSupply'      => '0x18160ddd',
		'victimFundWallet' => '0x9f237bea',
		'liquidityWallet'  => '0xd4698016',
		'totalTaxBps'      => '0xde126e18',
	);

	const TOPIC_TRANSFER = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
	const TOPIC_BOUGHT   = '0xa9a40dec7a304e5915d11358b968c1e8d365992abf20f82285d1df1b30c8e24c';
	const ONE_BILLION    = '1000000000000000000000000000';

	/**
	 * One JSON-RPC request, or a batch when $method is an array of [method, params] pairs.
	 *
	 * @param string|array $method Method or batch.
	 * @param array        $params Params.
	 * @param string       $url    RPC URL (defaults to the main RPC).
	 * @return mixed|WP_Error Result, or array of results for a batch (WP_Error items on failure).
	 */
	public static function rpc( $method, $params = array(), $url = '' ) {
		$url   = $url ? $url : nsc_setting( 'rpc' );
		$batch = is_array( $method );
		$body  = array();
		if ( $batch ) {
			foreach ( array_values( $method ) as $i => $m ) {
				$body[] = array(
					'jsonrpc' => '2.0',
					'id'      => $i + 1,
					'method'  => $m[0],
					'params'  => $m[1],
				);
			}
		} else {
			$body = array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => $method,
				'params'  => $params,
			);
		}
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => $batch ? 12 : 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'nsc_rpc', 'The blockchain node returned an unreadable answer (HTTP ' . wp_remote_retrieve_response_code( $res ) . ').' );
		}
		if ( ! $batch ) {
			if ( isset( $data['error'] ) ) {
				return new WP_Error( 'nsc_rpc', isset( $data['error']['message'] ) ? $data['error']['message'] : 'RPC error' );
			}
			return isset( $data['result'] ) ? $data['result'] : null;
		}
		// Batch: map answers back by id.
		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'nsc_rpc', isset( $data['error']['message'] ) ? $data['error']['message'] : 'RPC error' );
		}
		$out = array();
		foreach ( $data as $item ) {
			$id = isset( $item['id'] ) ? (int) $item['id'] - 1 : -1;
			if ( $id < 0 ) {
				continue;
			}
			$out[ $id ] = isset( $item['error'] ) ? new WP_Error( 'nsc_rpc', isset( $item['error']['message'] ) ? $item['error']['message'] : 'call failed' ) : ( isset( $item['result'] ) ? $item['result'] : null );
		}
		ksort( $out );
		return $out;
	}

	/** eth_call params for a contract read. */
	private static function call_params( $to, $data ) {
		return array(
			array(
				'to'   => $to,
				'data' => $data,
			),
			'latest',
		);
	}

	/** Decode a 32-byte word. */
	public static function decode( $hex, $type ) {
		if ( is_wp_error( $hex ) || ! is_string( $hex ) || strlen( $hex ) < 66 ) {
			return null;
		}
		$word = substr( $hex, 2, 64 );
		switch ( $type ) {
			case 'bool':
				return '1' === substr( $word, -1 );
			case 'address':
				return '0x' . substr( $word, 24 );
			default:
				return NSC_Math::hex2dec( $word );
		}
	}

	/**
	 * Read every value the website shows. Uses one batch request; falls back to
	 * one call at a time for nodes that refuse batches.
	 *
	 * @return array|WP_Error
	 */
	public static function read_contracts() {
		$presale = nsc_setting( 'presale' );
		$token   = nsc_setting( 'token' );
		$plan    = array(
			'priceUsd'         => array( $presale, 'uint' ),
			'bnbUsd'           => array( $presale, 'uint' ),
			'tokensLeft'       => array( $presale, 'uint' ),
			'totalSold'        => array( $presale, 'uint' ),
			'totalRaised'      => array( $presale, 'uint' ),
			'saleActive'       => array( $presale, 'bool' ),
			'minBuyWei'        => array( $presale, 'uint' ),
			'maxPerWalletWei'  => array( $presale, 'uint' ),
			'treasury'         => array( $presale, 'address' ),
			'owner'            => array( $presale, 'address' ),
			'totalSupply'      => array( $token, 'uint' ),
			'victimFundWallet' => array( $token, 'address' ),
			'liquidityWallet'  => array( $token, 'address' ),
			'totalTaxBps'      => array( $token, 'uint' ),
		);
		$calls = array();
		foreach ( $plan as $fn => $p ) {
			$calls[] = array( 'eth_call', self::call_params( $p[0], self::SEL[ $fn ] ) );
		}
		$res = self::rpc( $calls );
		if ( is_wp_error( $res ) || count( $res ) !== count( $calls ) ) {
			// Fallback: one call at a time.
			$res = array();
			foreach ( $calls as $c ) {
				$res[] = self::rpc( $c[0], $c[1] );
			}
		}
		$out    = array();
		$i      = 0;
		$failed = 0;
		foreach ( $plan as $fn => $p ) {
			$val        = self::decode( isset( $res[ $i ] ) ? $res[ $i ] : null, $p[1] );
			$out[ $fn ] = $val;
			if ( null === $val && 'bnbUsd' !== $fn ) {
				++$failed;
			}
			++$i;
		}
		if ( $failed > 3 ) {
			return new WP_Error( 'nsc_rpc', 'Could not read the contracts from the blockchain node.' );
		}
		return $out;
	}

	/**
	 * Presale and token figures, cached for 20 seconds. Keeps the last good copy so
	 * the site still shows numbers if the node is briefly unreachable.
	 *
	 * @param bool $fresh Skip the cache.
	 */
	public static function stats( $fresh = false ) {
		$cached = get_transient( 'nsc_stats' );
		if ( ! $fresh && is_array( $cached ) ) {
			return $cached;
		}
		$raw = self::read_contracts();
		if ( is_wp_error( $raw ) ) {
			$last = get_option( 'nsc_stats_last' );
			if ( is_array( $last ) ) {
				$last['stale'] = true;
			} else {
				$last = self::empty_stats( $raw->get_error_message() );
			}
			// Don't make every visitor wait for an unreachable node: retry in a minute.
			set_transient( 'nsc_stats', $last, 60 );
			return $last;
		}
		$s = self::build_stats( $raw );
		set_transient( 'nsc_stats', $s, 20 );
		update_option( 'nsc_stats_last', $s, false );
		return $s;
	}

	/** Shape the raw contract values for the website. */
	private static function build_stats( $r ) {
		$sold   = $r['totalSold'] ? $r['totalSold'] : '0';
		$left   = $r['tokensLeft'] ? $r['tokensLeft'] : '0';
		$supply = $r['totalSupply'] ? $r['totalSupply'] : self::ONE_BILLION;
		$raised = $r['totalRaised'] ? $r['totalRaised'] : '0';
		$price  = $r['priceUsd'] ? $r['priceUsd'] : '0';
		$bnb    = $r['bnbUsd'];

		$sold_f   = NSC_Math::to_float( $sold );
		$left_f   = NSC_Math::to_float( $left );
		$raised_f = NSC_Math::to_float( $raised );
		$bnb_f    = $bnb ? NSC_Math::to_float( $bnb ) : 0.0;
		$price_f  = NSC_Math::to_float( $price );
		$alloc    = $sold_f + $left_f;

		return array(
			'ok'              => true,
			'stale'           => false,
			'oracle_ok'       => null !== $bnb,
			'network'         => nsc_setting( 'network' ),
			'chainId'         => (int) nsc_setting( 'chain_id' ),
			'explorer'        => nsc_setting( 'explorer' ),
			'currency'        => nsc_setting( 'currency' ),
			'token'           => nsc_setting( 'token' ),
			'presale'         => nsc_setting( 'presale' ),
			'symbol'          => 'NSC',
			'decimals'        => 18,
			'priceUsd'        => $price,
			'bnbUsd'          => $bnb ? $bnb : '0',
			'saleActive'      => (bool) $r['saleActive'],
			'minBuyWei'       => $r['minBuyWei'] ? $r['minBuyWei'] : '0',
			'maxPerWalletWei' => $r['maxPerWalletWei'] ? $r['maxPerWalletWei'] : '0',
			'tokensLeft'      => $left,
			'totalSold'       => $sold,
			'totalRaised'     => $raised,
			'totalSupply'     => $supply,
			'burned'          => NSC_Math::sub( self::ONE_BILLION, $supply ),
			'treasury'        => $r['treasury'],
			'owner'           => $r['owner'],
			'victimFund'      => $r['victimFundWallet'],
			'liquidityWallet' => $r['liquidityWallet'],
			'taxBps'          => (int) ( $r['totalTaxBps'] ? $r['totalTaxBps'] : 0 ),
			'holders'         => NSC_DB::holder_count(),
			'buys'            => NSC_DB::buy_count(),
			'updated'         => time(),
			'view'            => array(
				'price'     => self::usd( $price_f, true ),
				'bnbPrice'  => $bnb_f ? self::usd( $bnb_f ) : '—',
				'raisedBnb' => self::num( $raised_f, 4 ) . ' ' . nsc_setting( 'currency' ),
				'raisedUsd' => self::usd( $raised_f * $bnb_f ),
				'sold'      => self::compact( $sold_f ) . ' N$C',
				'left'      => self::compact( $left_f ) . ' N$C',
				'soldPct'   => $alloc > 0 ? round( $sold_f / $alloc * 100, 2 ) : 0,
				'burned'    => self::compact( NSC_Math::to_float( NSC_Math::sub( self::ONE_BILLION, $supply ) ) ) . ' N$C',
				'supply'    => self::compact( NSC_Math::to_float( $supply ) ),
				'nscPerBnb' => $price_f > 0 ? self::compact( $bnb_f / $price_f ) : '—',
				'minBuy'    => NSC_Math::to_units( $r['minBuyWei'] ? $r['minBuyWei'] : '0' ),
			),
		);
	}

	/** Placeholder when the node can't be reached and nothing is cached yet. */
	private static function empty_stats( $error ) {
		return array(
			'ok'      => false,
			'error'   => $error,
			'network' => nsc_setting( 'network' ),
			'chainId' => (int) nsc_setting( 'chain_id' ),
			'token'   => nsc_setting( 'token' ),
			'presale' => nsc_setting( 'presale' ),
			'explorer' => nsc_setting( 'explorer' ),
			'currency' => nsc_setting( 'currency' ),
			'saleActive' => false,
			'view'    => array(
				'price'     => '—',
				'bnbPrice'  => '—',
				'raisedBnb' => '—',
				'raisedUsd' => '—',
				'sold'      => '—',
				'left'      => '—',
				'soldPct'   => 0,
				'burned'    => '—',
				'supply'    => '1B',
				'nscPerBnb' => '—',
				'minBuy'    => '0.001',
			),
			'holders' => NSC_DB::holder_count(),
			'buys'    => NSC_DB::buy_count(),
		);
	}

	/** $1,234.56, or $0.0042 for small prices. */
	public static function usd( $v, $price = false ) {
		if ( $price && $v > 0 && $v < 1 ) {
			return '$' . rtrim( rtrim( number_format( $v, 8, '.', ',' ), '0' ), '.' );
		}
		return '$' . number_format( $v, $v >= 1000 ? 0 : 2 );
	}

	/** Number with up to $dp decimals, trailing zeros trimmed. */
	public static function num( $v, $dp = 2 ) {
		$s = number_format( $v, $dp, '.', ',' );
		return false !== strpos( $s, '.' ) ? rtrim( rtrim( $s, '0' ), '.' ) : $s;
	}

	/** 1.25M, 400M, 12.5K. */
	public static function compact( $v ) {
		$units = array( 1e9 => 'B', 1e6 => 'M', 1e3 => 'K' );
		foreach ( $units as $size => $u ) {
			if ( $v >= $size * 0.9995 ) {
				return self::num( $v / $size, 2 ) . $u;
			}
		}
		return self::num( $v, 2 );
	}

	/** Latest block number (int). */
	public static function block_number( $url = '' ) {
		$r = self::rpc( 'eth_blockNumber', array(), $url );
		return is_wp_error( $r ) ? $r : hexdec( $r );
	}
}
