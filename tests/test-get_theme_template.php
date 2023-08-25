<?php
/**
 * Class getThemeTemplate
 *
 * @package vektor-inc/vk-css-optimize
 */

use VektorInc\VK_CSS_Optimize\VkCssOptimize;
new VkCssOptimize();

class getThemeTemplate extends WP_UnitTestCase {

	public function test_get_theme_template() {
		$test_data = array(
			array(
				'option'  => array(
					'theme' => 'lightning',
				),
				'correct' => 'lightning',
			),
			array(
				'option'  => array(
					'theme' => 'x-t9',
				),
				'correct' => 'x-t9',
			),
			// lightningからxt-9のライブプレビューを行う
			array(
				'option'  => array(
					'theme' => 'lightning',
					'target_url' => admin_url() . '/site-editor.php?wp_theme_preview=x-t9',
				),
				'correct' => 'x-t9',
			),
		);
		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'get_theme_template()' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		foreach ( $test_data as $test_value ) {

			if ( ! empty( $test_value['option']['theme'] ) ){
				switch_theme( $test_value['option']['theme'] );
			}

			if ( ! empty( $test_value['option']['target_url'] ) ){
				$this->go_to( $test_value['option']['target_url'] );
			}

      $return = VkCssOptimize::get_theme_template();
			$correct = $test_value['correct'];

			print 'return  :';
			print PHP_EOL;
			var_dump( $return );
			print PHP_EOL;
			print 'correct  :';
			print PHP_EOL;
			var_dump( $correct );
			print PHP_EOL;
			$this->assertSame( $correct, $return );

		}
	}
}
