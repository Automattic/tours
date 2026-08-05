<?php
/**
 * PHPUnit bootstrap.
 *
 * @package Tours
 */

require dirname( __DIR__ ) . '/class-tour-package.php';

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Test stub for nonce verification.
	 *
	 * @param string $nonce  Nonce.
	 * @param string $action Action.
	 *
	 * @return bool
	 */
	function wp_verify_nonce( $nonce, $action ) {
		return 'nonce' === $nonce && 'update-post_123' === $action;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Test stub for WordPress unslashing.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value );
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	/**
	 * Test stub for WordPress slashing.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	function wp_slash( $value ) {
		return is_array( $value ) ? array_map( 'wp_slash', $value ) : addslashes( $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Test stub for text sanitization.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( wp_strip_all_tags( $value ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Test stub for tag stripping.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	function wp_strip_all_tags( $value ) {
		return strip_tags( $value );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Test stub for post HTML sanitization.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	function wp_kses_post( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Test stub for JSON encoding.
	 *
	 * @param mixed $value   Value.
	 * @param int   $options JSON options.
	 *
	 * @return string
	 */
	function wp_json_encode( $value, $options = 0 ) {
		return json_encode( $value, $options );
	}
}

require dirname( __DIR__ ) . '/class-tours.php';
