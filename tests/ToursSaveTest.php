<?php
/**
 * Tests for saving tours.
 *
 * @package Tours
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests tour post save handling.
 */
class ToursSaveTest extends TestCase {
	/**
	 * Original POST superglobal.
	 *
	 * @var array
	 */
	private $post_backup;

	/**
	 * Back up POST before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->post_backup = $_POST;
	}

	/**
	 * Restore POST after each test.
	 */
	protected function tearDown(): void {
		$_POST = $this->post_backup;
		parent::tearDown();
	}

	/**
	 * Test admin saves keep steps when order indices are browser-submitted strings.
	 */
	public function test_wp_insert_post_data_saves_steps_with_string_order_indices() {
		$_POST = array(
			'_wpnonce'   => 'nonce',
			'post_title' => 'Example Tour',
			'color'      => '#123abc',
			'order'      => array( '1', '0' ),
			'tour'       => array(
				0 => array(
					'element' => '.first',
					'popover' => array(
						'title'       => 'First',
						'description' => 'First description.',
					),
				),
				1 => array(
					'element' => '.second',
					'popover' => array(
						'title'       => 'Second',
						'description' => 'Second description.',
					),
				),
			),
		);

		$data = Tours::wp_insert_post_data(
			array(
				'post_type'    => 'tour',
				'post_title'   => '',
				'post_content' => '',
			),
			array(
				'ID' => 123,
			)
		);

		$tour = json_decode( wp_unslash( $data['post_content'] ), true );

		$this->assertSame( 'Example Tour', $data['post_title'] );
		$this->assertCount( 3, $tour );
		$this->assertSame( '#123abc', $tour[0]['color'] );
		$this->assertSame( 'Example Tour', $tour[0]['title'] );
		$this->assertSame( '.second', $tour[1]['element'] );
		$this->assertSame( 'Second', $tour[1]['popover']['title'] );
		$this->assertSame( '.first', $tour[2]['element'] );
	}
}
