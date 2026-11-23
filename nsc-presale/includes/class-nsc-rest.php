<?php
/**
 * REST endpoints.
 *
 * GET  /wp-json/nsc/v1/stats   live presale + token figures (public, read-only, cached 20 s)
 * GET  /wp-json/nsc/v1/buys    latest presale purchases (public, read-only)
 * POST /wp-json/nsc/v1/sync    record new transactions now (administrators only)
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

class NSC_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'nsc/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( $req ) {
					$fresh = ! empty( $req['fresh'] ) && current_user_can( 'manage_options' );
					$res   = rest_ensure_response( NSC_Chain::stats( $fresh ) );
					$res->header( 'Cache-Control', 'public, max-age=15' );
					return $res;
				},
			)
		);

		register_rest_route(
			'nsc/v1',
			'/buys',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'default'           => 8,
						'sanitize_callback' => 'absint',
					),
				),
				'callback'            => function ( $req ) {
					list( $rows ) = NSC_DB::transactions(
						array(
							'kind'     => 'buy',
							'per_page' => min( 20, max( 1, (int) $req['limit'] ) ),
						)
					);
					$out = array();
					foreach ( $rows as $r ) {
						$out[] = array(
							'buyer' => nsc_short( $r->to_addr ),
							'nsc'   => NSC_Chain::compact( (float) $r->amount_num ),
							'bnb'   => NSC_Chain::num( (float) $r->bnb_num, 4 ),
							'time'  => $r->block_time ? strtotime( $r->block_time . ' UTC' ) : null,
							'tx'    => nsc_explorer( 'tx', $r->tx_hash ),
						);
					}
					return rest_ensure_response( $out );
				},
			)
		);

		register_rest_route(
			'nsc/v1',
			'/sync',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => function () {
					return rest_ensure_response( NSC_Indexer::sync( 25 ) );
				},
			)
		);
	}
}
