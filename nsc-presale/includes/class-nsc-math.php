<?php
/**
 * Whole-number arithmetic on decimal strings.
 *
 * Token amounts have 18 decimals, so 1B N$C is a 28-digit number: too big for PHP
 * integers. Many shared hosts lack BCMath/GMP, so this works on plain strings.
 *
 * @package nsc-presale
 */

defined( 'ABSPATH' ) || exit;

class NSC_Math {

	/** Strip leading zeros; '' becomes '0'. */
	public static function norm( $a ) {
		$a = ltrim( (string) $a, '0' );
		return '' === $a ? '0' : $a;
	}

	/** Is this a non-negative whole number string? */
	public static function is_uint( $a ) {
		return is_string( $a ) && '' !== $a && ctype_digit( $a );
	}

	/** Compare: -1, 0 or 1. */
	public static function cmp( $a, $b ) {
		$a = self::norm( $a );
		$b = self::norm( $b );
		if ( strlen( $a ) !== strlen( $b ) ) {
			return strlen( $a ) < strlen( $b ) ? -1 : 1;
		}
		return strcmp( $a, $b ) <=> 0;
	}

	/** a + b. */
	public static function add( $a, $b ) {
		$a     = strrev( self::norm( $a ) );
		$b     = strrev( self::norm( $b ) );
		$len   = max( strlen( $a ), strlen( $b ) );
		$out   = '';
		$carry = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			$d     = ( $i < strlen( $a ) ? (int) $a[ $i ] : 0 ) + ( $i < strlen( $b ) ? (int) $b[ $i ] : 0 ) + $carry;
			$out  .= (string) ( $d % 10 );
			$carry = intdiv( $d, 10 );
		}
		if ( $carry ) {
			$out .= (string) $carry;
		}
		return self::norm( strrev( $out ) );
	}

	/** a - b, never below zero. */
	public static function sub( $a, $b ) {
		if ( self::cmp( $a, $b ) <= 0 ) {
			return '0';
		}
		$a      = strrev( self::norm( $a ) );
		$b      = strrev( self::norm( $b ) );
		$out    = '';
		$borrow = 0;
		for ( $i = 0; $i < strlen( $a ); $i++ ) {
			$d = (int) $a[ $i ] - $borrow - ( $i < strlen( $b ) ? (int) $b[ $i ] : 0 );
			if ( $d < 0 ) {
				$d     += 10;
				$borrow = 1;
			} else {
				$borrow = 0;
			}
			$out .= (string) $d;
		}
		return self::norm( strrev( $out ) );
	}

	/** a × small (small < 10^6). */
	public static function mul_small( $a, $small ) {
		$a     = strrev( self::norm( $a ) );
		$out   = '';
		$carry = 0;
		for ( $i = 0; $i < strlen( $a ); $i++ ) {
			$d     = (int) $a[ $i ] * $small + $carry;
			$out  .= (string) ( $d % 10 );
			$carry = intdiv( $d, 10 );
		}
		while ( $carry ) {
			$out  .= (string) ( $carry % 10 );
			$carry = intdiv( $carry, 10 );
		}
		return self::norm( strrev( $out ) );
	}

	/** Hex (with or without 0x) to a decimal string. */
	public static function hex2dec( $hex ) {
		$hex = strtolower( preg_replace( '/^0x/i', '', (string) $hex ) );
		if ( '' === $hex || ! ctype_xdigit( $hex ) ) {
			return '0';
		}
		$dec = '0';
		foreach ( str_split( $hex ) as $c ) {
			$dec = self::add( self::mul_small( $dec, 16 ), (string) hexdec( $c ) );
		}
		return $dec;
	}

	/** Decimal string to a 64-character hex word (for contract call data). */
	public static function dec2word( $dec ) {
		$dec = self::norm( $dec );
		$hex = '';
		while ( '0' !== $dec ) {
			list( $dec, $rem ) = self::divmod_small( $dec, 16 );
			$hex = dechex( $rem ) . $hex;
		}
		return str_pad( $hex, 64, '0', STR_PAD_LEFT );
	}

	/** [ a div small, a mod small ]. */
	public static function divmod_small( $a, $small ) {
		$a   = self::norm( $a );
		$out = '';
		$rem = 0;
		for ( $i = 0; $i < strlen( $a ); $i++ ) {
			$cur  = $rem * 10 + (int) $a[ $i ];
			$out .= (string) intdiv( $cur, $small );
			$rem  = $cur % $small;
		}
		return array( self::norm( $out ), $rem );
	}

	/**
	 * Units (18 decimals) to a plain decimal string: 1500000000000000000 -> "1.5".
	 *
	 * @param string $raw      Whole number.
	 * @param int    $decimals Token decimals.
	 */
	public static function to_units( $raw, $decimals = 18 ) {
		$raw  = str_pad( self::norm( $raw ), $decimals + 1, '0', STR_PAD_LEFT );
		$int  = substr( $raw, 0, -$decimals );
		$frac = rtrim( substr( $raw, -$decimals ), '0' );
		return self::norm( $int ) . ( '' !== $frac ? '.' . $frac : '' );
	}

	/** Plain decimal string ("0.0042") to units with 18 decimals. */
	public static function from_units( $value, $decimals = 18 ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^\d*(\.\d*)?$/', $value ) || '' === $value || '.' === $value ) {
			return '0';
		}
		$parts = explode( '.', $value . '.' );
		$frac  = substr( str_pad( $parts[1], $decimals, '0' ), 0, $decimals );
		return self::norm( $parts[0] . $frac );
	}

	/** Units as a float, for display and sorting only. */
	public static function to_float( $raw, $decimals = 18 ) {
		return (float) self::to_units( $raw, $decimals );
	}
}
