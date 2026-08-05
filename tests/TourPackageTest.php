<?php
/**
 * Tests for tour packages.
 *
 * @package Tours
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests package conversion and validation.
 */
class TourPackageTest extends TestCase {
	/**
	 * Test package validation succeeds for a complete package.
	 */
	public function test_validate_package_accepts_valid_package() {
		$this->assertTrue( Tour_Package::validate_package( $this->package() ) );
	}

	/**
	 * Test package validation rejects unsupported schemas.
	 */
	public function test_validate_package_rejects_invalid_schema() {
		$package           = $this->package();
		$package['schema'] = 'https://example.com/invalid.json';

		$this->assertError( Tour_Package::validate_package( $package ) );
	}

	/**
	 * Test package validation rejects invalid steps.
	 */
	public function test_validate_package_rejects_invalid_step() {
		$package                         = $this->package();
		$package['steps'][0]['popover'] = array();

		$this->assertError( Tour_Package::validate_package( $package ) );
	}

	/**
	 * Test package conversion to stored tour steps.
	 */
	public function test_package_to_tour_steps() {
		$tour_steps = Tour_Package::package_to_tour_steps( $this->package() );

		$this->assertSame( '#3939c7', $tour_steps[0]['color'] );
		$this->assertSame( 'Block Editor Basics', $tour_steps[0]['title'] );
		$this->assertSame( '#editor', $tour_steps[1]['element'] );
		$this->assertSame( 'Editor', $tour_steps[1]['popover']['title'] );
	}

	/**
	 * Test stored tour steps conversion to package.
	 */
	public function test_tour_steps_to_package() {
		$package = Tour_Package::tour_steps_to_package(
			array(
				array(
					'color' => '#123abc',
					'title' => 'Example Tour',
				),
				array(
					'element' => '.example',
					'popover' => array(
						'title'       => 'Example',
						'description' => 'Example description.',
					),
				),
			)
		);

		$this->assertSame( Tour_Package::PACKAGE_SCHEMA, $package['schema'] );
		$this->assertSame( 'example-tour', $package['id'] );
		$this->assertSame( '#123abc', $package['color'] );
		$this->assertSame( '.example', $package['steps'][0]['element'] );
	}

	/**
	 * Test catalog validation.
	 */
	public function test_validate_catalog() {
		$catalog = array(
			'schema'  => Tour_Package::CATALOG_SCHEMA,
			'version' => 1,
			'tours'   => array(
				array(
					'id'          => 'block-editor-basics',
					'title'       => 'Block Editor Basics',
					'description' => 'Introductory tour.',
					'path'        => 'block-editor-basics.json',
				),
			),
		);

		$this->assertTrue( Tour_Package::validate_catalog( $catalog ) );
	}

	/**
	 * Test catalog validation rejects incomplete entries.
	 */
	public function test_validate_catalog_rejects_incomplete_entry() {
		$catalog = array(
			'schema'  => Tour_Package::CATALOG_SCHEMA,
			'version' => 1,
			'tours'   => array(
				array(
					'id'    => 'block-editor-basics',
					'title' => 'Block Editor Basics',
				),
			),
		);

		$this->assertError( Tour_Package::validate_catalog( $catalog ) );
	}

	/**
	 * Assert a validation result is an error.
	 *
	 * @param mixed $result Validation result.
	 */
	private function assertError( $result ) {
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Return a valid package.
	 *
	 * @return array Package.
	 */
	private function package() {
		return array(
			'schema'      => Tour_Package::PACKAGE_SCHEMA,
			'version'     => 1,
			'id'          => 'block-editor-basics',
			'title'       => 'Block Editor Basics',
			'color'       => '#3939c7',
			'description' => 'Introductory tour.',
			'source'      => array(
				'type' => 'bundled',
				'path' => 'library/tours/block-editor-basics.json',
			),
			'steps'       => array(
				array(
					'element' => '#editor',
					'popover' => array(
						'title'       => 'Editor',
						'description' => 'This is where you write.',
					),
				),
			),
		);
	}
}
