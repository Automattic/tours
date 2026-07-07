<?php
/**
 * Tour package helpers.
 *
 * @package Tours
 */

/**
 * Converts, validates, and normalizes shareable tour packages.
 */
class Tour_Package {
	const PACKAGE_SCHEMA = 'https://automattic.github.io/tours/schemas/tour-package-v1.json';
	const CATALOG_SCHEMA = 'https://automattic.github.io/tours/schemas/tour-catalog-v1.json';
	const VERSION        = 1;

	/**
	 * Validate a shareable tour package.
	 *
	 * @param array $package Package data.
	 * @return true|WP_Error|array True when valid, otherwise an error.
	 */
	public static function validate_package( $package ) {
		if ( ! is_array( $package ) ) {
			return self::error( 'tour_package_invalid', 'Tour package must be an object.' );
		}

		if ( self::PACKAGE_SCHEMA !== self::get_string( $package, 'schema' ) ) {
			return self::error( 'tour_package_invalid_schema', 'Tour package schema is not supported.' );
		}

		if ( self::VERSION !== self::get_int( $package, 'version' ) ) {
			return self::error( 'tour_package_invalid_version', 'Tour package version is not supported.' );
		}

		foreach ( array( 'id', 'title' ) as $field ) {
			if ( '' === self::get_string( $package, $field ) ) {
				return self::error( 'tour_package_missing_' . $field, 'Tour package is missing a required field.' );
			}
		}

		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $package['id'] ) ) {
			return self::error( 'tour_package_invalid_id', 'Tour package id must be a lowercase slug.' );
		}

		if ( isset( $package['color'] ) && ! self::is_color( $package['color'] ) ) {
			return self::error( 'tour_package_invalid_color', 'Tour package color must be a hex color.' );
		}

		if ( ! isset( $package['steps'] ) || ! is_array( $package['steps'] ) || empty( $package['steps'] ) ) {
			return self::error( 'tour_package_missing_steps', 'Tour package must contain at least one step.' );
		}

		foreach ( $package['steps'] as $step ) {
			$step_validation = self::validate_step( $step );
			if ( true !== $step_validation ) {
				return $step_validation;
			}
		}

		if ( isset( $package['source'] ) && ! is_array( $package['source'] ) ) {
			return self::error( 'tour_package_invalid_source', 'Tour package source must be an object.' );
		}

		return true;
	}

	/**
	 * Normalize a shareable package.
	 *
	 * @param array $package Package data.
	 * @return array Normalized package.
	 */
	public static function normalize_package( $package ) {
		$validation = self::validate_package( $package );
		if ( self::is_error( $validation ) ) {
			return $validation;
		}

		$normalized = array(
			'schema'      => self::PACKAGE_SCHEMA,
			'version'     => self::VERSION,
			'id'          => trim( $package['id'] ),
			'title'       => trim( $package['title'] ),
			'color'       => isset( $package['color'] ) ? strtolower( $package['color'] ) : '#3939c7',
			'description' => isset( $package['description'] ) ? trim( (string) $package['description'] ) : '',
			'steps'       => array(),
		);

		if ( isset( $package['source'] ) ) {
			$normalized['source'] = $package['source'];
		}

		foreach ( $package['steps'] as $step ) {
			$normalized['steps'][] = array(
				'element' => trim( $step['element'] ),
				'popover' => array(
					'title'       => trim( $step['popover']['title'] ),
					'description' => isset( $step['popover']['description'] ) ? trim( (string) $step['popover']['description'] ) : '',
				),
			);
		}

		return $normalized;
	}

	/**
	 * Convert a shareable package to the current stored tour steps.
	 *
	 * @param array $package Package data.
	 * @return array|WP_Error Stored tour steps or error.
	 */
	public static function package_to_tour_steps( $package ) {
		$package = self::normalize_package( $package );
		if ( self::is_error( $package ) ) {
			return $package;
		}

		$tour = array(
			array(
				'color' => $package['color'],
				'title' => $package['title'],
			),
		);

		foreach ( $package['steps'] as $step ) {
			$tour[] = $step;
		}

		return $tour;
	}

	/**
	 * Convert stored tour steps into a shareable package.
	 *
	 * @param array $tour_steps Stored tour steps.
	 * @param array $args       Package arguments.
	 * @return array|WP_Error Package data or error.
	 */
	public static function tour_steps_to_package( $tour_steps, $args = array() ) {
		if ( ! is_array( $tour_steps ) || empty( $tour_steps[0] ) || ! is_array( $tour_steps[0] ) ) {
			return self::error( 'tour_steps_invalid', 'Tour steps must include tour metadata.' );
		}

		$metadata = $tour_steps[0];
		$title    = isset( $args['title'] ) ? $args['title'] : self::get_string( $metadata, 'title' );
		$id       = isset( $args['id'] ) ? $args['id'] : self::slugify( $title );

		$package = array(
			'schema'      => self::PACKAGE_SCHEMA,
			'version'     => self::VERSION,
			'id'          => $id,
			'title'       => $title,
			'color'       => isset( $args['color'] ) ? $args['color'] : self::get_string( $metadata, 'color', '#3939c7' ),
			'description' => isset( $args['description'] ) ? $args['description'] : '',
			'steps'       => array_values( array_slice( $tour_steps, 1 ) ),
		);

		if ( isset( $args['source'] ) ) {
			$package['source'] = $args['source'];
		}

		return self::normalize_package( $package );
	}

	/**
	 * Validate a tour catalog.
	 *
	 * @param array $catalog Catalog data.
	 * @return true|WP_Error|array True when valid, otherwise an error.
	 */
	public static function validate_catalog( $catalog ) {
		if ( ! is_array( $catalog ) ) {
			return self::error( 'tour_catalog_invalid', 'Tour catalog must be an object.' );
		}

		if ( self::CATALOG_SCHEMA !== self::get_string( $catalog, 'schema' ) ) {
			return self::error( 'tour_catalog_invalid_schema', 'Tour catalog schema is not supported.' );
		}

		if ( self::VERSION !== self::get_int( $catalog, 'version' ) ) {
			return self::error( 'tour_catalog_invalid_version', 'Tour catalog version is not supported.' );
		}

		if ( ! isset( $catalog['tours'] ) || ! is_array( $catalog['tours'] ) ) {
			return self::error( 'tour_catalog_missing_tours', 'Tour catalog must contain tours.' );
		}

		foreach ( $catalog['tours'] as $tour ) {
			$tour_validation = self::validate_catalog_entry( $tour );
			if ( true !== $tour_validation ) {
				return $tour_validation;
			}
		}

		return true;
	}

	/**
	 * Validate a catalog entry.
	 *
	 * @param array $entry Catalog entry.
	 * @return true|WP_Error|array True when valid, otherwise an error.
	 */
	private static function validate_catalog_entry( $entry ) {
		if ( ! is_array( $entry ) ) {
			return self::error( 'tour_catalog_entry_invalid', 'Tour catalog entry must be an object.' );
		}

		foreach ( array( 'id', 'title', 'path' ) as $field ) {
			if ( '' === self::get_string( $entry, $field ) ) {
				return self::error( 'tour_catalog_entry_missing_' . $field, 'Tour catalog entry is missing a required field.' );
			}
		}

		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $entry['id'] ) ) {
			return self::error( 'tour_catalog_entry_invalid_id', 'Tour catalog entry id must be a lowercase slug.' );
		}

		return true;
	}

	/**
	 * Validate a package step.
	 *
	 * @param array $step Package step.
	 * @return true|WP_Error|array True when valid, otherwise an error.
	 */
	private static function validate_step( $step ) {
		if ( ! is_array( $step ) ) {
			return self::error( 'tour_package_invalid_step', 'Tour package step must be an object.' );
		}

		if ( '' === self::get_string( $step, 'element' ) ) {
			return self::error( 'tour_package_step_missing_element', 'Tour package step is missing an element.' );
		}

		if ( ! isset( $step['popover'] ) || ! is_array( $step['popover'] ) ) {
			return self::error( 'tour_package_step_missing_popover', 'Tour package step is missing a popover.' );
		}

		if ( '' === self::get_string( $step['popover'], 'title' ) ) {
			return self::error( 'tour_package_step_missing_title', 'Tour package step popover is missing a title.' );
		}

		return true;
	}

	/**
	 * Return a string field.
	 *
	 * @param array  $data    Source data.
	 * @param string $key     Field key.
	 * @param string $fallback Default value.
	 * @return string String value.
	 */
	private static function get_string( $data, $key, $fallback = '' ) {
		if ( ! is_array( $data ) || ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
			return $fallback;
		}

		return trim( (string) $data[ $key ] );
	}

	/**
	 * Return an integer field.
	 *
	 * @param array  $data Source data.
	 * @param string $key  Field key.
	 * @return int Integer value.
	 */
	private static function get_int( $data, $key ) {
		if ( ! is_array( $data ) || ! isset( $data[ $key ] ) ) {
			return 0;
		}

		return (int) $data[ $key ];
	}

	/**
	 * Check whether a value is a hex color.
	 *
	 * @param string $color Color value.
	 * @return bool Whether the color is valid.
	 */
	private static function is_color( $color ) {
		return is_string( $color ) && 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $color );
	}

	/**
	 * Convert text into a lowercase slug.
	 *
	 * @param string $text Text.
	 * @return string Slug.
	 */
	private static function slugify( $text ) {
		$slug = strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', (string) $text ) );
		$slug = trim( $slug, '-' );

		return '' === $slug ? 'tour' : $slug;
	}

	/**
	 * Create a WP_Error or fallback error array.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return WP_Error|array Error.
	 */
	private static function error( $code, $message ) {
		if ( class_exists( 'WP_Error' ) ) {
			return new WP_Error( $code, $message );
		}

		return array(
			'error'   => $code,
			'message' => $message,
		);
	}

	/**
	 * Check whether a value is an error.
	 *
	 * @param mixed $value Value.
	 * @return bool Whether the value is an error.
	 */
	private static function is_error( $value ) {
		if ( class_exists( 'WP_Error' ) && function_exists( 'is_wp_error' ) && is_wp_error( $value ) ) {
			return true;
		}

		return is_array( $value ) && isset( $value['error'] );
	}
}
