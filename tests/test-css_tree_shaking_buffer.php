<?php
/**
 * Class CssTreeShakingBufferTest
 *
 * @package vektor-inc/vk-css-optimize
 */

use VektorInc\VK_CSS_Optimize\VkCssOptimize;

class CssTreeShakingBufferTest extends WP_UnitTestCase {

	/**
	 * テスト用 CSS ファイルのパス
	 *
	 * @var string
	 */
	private $css_path = '';

	/**
	 * テスト用 CSS ファイルの URL
	 *
	 * @var string
	 */
	private $css_url = '';

	/**
	 * テスト用 CSS ファイルを uploads 直下に作成する
	 */
	public function set_up() {
		parent::set_up();
		$upload         = wp_upload_dir();
		$this->css_url  = $upload['baseurl'] . '/vk-css-optimize-test.css';
		$this->css_path = $upload['basedir'] . '/vk-css-optimize-test.css';
		wp_mkdir_p( $upload['basedir'] );
		file_put_contents( $this->css_path, ".used { color: red; }\n.unused { color: blue; }\n" );
	}

	/**
	 * テスト用 CSS ファイルと登録を片付ける
	 */
	public function tear_down() {
		if ( file_exists( $this->css_path ) ) {
			unlink( $this->css_path );
		}
		wp_deregister_style( 'vk-css-test' );
		remove_all_filters( 'vk_css_tree_shaking_handles' );
		remove_all_filters( 'vk_css_simple_minify_handles' );
		parent::tear_down();
	}

	/**
	 * css_tree_shaking_buffer() が type 属性の有無にかかわらず link を style に置換するか
	 */
	public function test_css_tree_shaking_buffer() {
		$url  = $this->css_url;
		$body = '<div class="used">a</div>';

		$test_cases = array(
			array(
				'test_condition_name' => 'Tree Shaking で type="text/css" ありの link => 未使用セレクタが消えた style に置換',
				'filter'              => 'vk_css_tree_shaking_handles',
				'buffer'              => "<link rel='stylesheet' id='vk-css-test-css' href='{$url}?ver=1.0' type='text/css' media='all' />" . $body,
				'expected_contains'   => "<style id='vk-css-test-css' type='text/css'>.used{color:red;}</style>",
				'expected_absent'     => '<link',
			),
			array(
				'test_condition_name' => 'Tree Shaking で type 属性なしの link（現行 WordPress）=> 未使用セレクタが消えた style に置換',
				'filter'              => 'vk_css_tree_shaking_handles',
				'buffer'              => "<link rel='stylesheet' id='vk-css-test-css' href='{$url}?ver=1.0' media='all' />" . $body,
				'expected_contains'   => "<style id='vk-css-test-css' type='text/css'>.used{color:red;}</style>",
				'expected_absent'     => '<link',
			),
			array(
				'test_condition_name' => 'Tree Shaking で type 属性なし・末尾が > の link => style に置換',
				'filter'              => 'vk_css_tree_shaking_handles',
				'buffer'              => "<link rel='stylesheet' id='vk-css-test-css' href='{$url}?ver=1.0' media='all'>" . $body,
				'expected_contains'   => "<style id='vk-css-test-css' type='text/css'>.used{color:red;}</style>",
				'expected_absent'     => '<link',
			),
			array(
				'test_condition_name' => 'Simple Minify で type="text/css" ありの link（ver あり）=> 圧縮した style に置換',
				'filter'              => 'vk_css_simple_minify_handles',
				'buffer'              => "<link rel='stylesheet' id='vk-css-test-css' href='{$url}?ver=1.0' type='text/css' media='all' />" . $body,
				'expected_contains'   => "<style id='vk-css-test-css' type='text/css'>.used{color:red;}.unused{color:blue;}</style>",
				'expected_absent'     => '<link',
			),
			array(
				'test_condition_name' => 'Simple Minify で type 属性なしの link（ver あり）=> 圧縮した style に置換',
				'filter'              => 'vk_css_simple_minify_handles',
				'buffer'              => "<link rel='stylesheet' id='vk-css-test-css' href='{$url}?ver=1.0' media='all' />" . $body,
				'expected_contains'   => "<style id='vk-css-test-css' type='text/css'>.used{color:red;}.unused{color:blue;}</style>",
				'expected_absent'     => '<link',
			),
			array(
				'test_condition_name' => 'Simple Minify で type 属性なしの link（ver なし）=> 圧縮した style に置換',
				'filter'              => 'vk_css_simple_minify_handles',
				'buffer'              => "<link rel='stylesheet' id='vk-css-test-css' href='{$url}' media='all' />" . $body,
				'expected_contains'   => "<style id='vk-css-test-css' type='text/css'>.used{color:red;}.unused{color:blue;}</style>",
				'expected_absent'     => '<link',
			),
			array(
				'test_condition_name' => '別 id の link は置換されない（境界）',
				'filter'              => 'vk_css_tree_shaking_handles',
				'buffer'              => "<link rel='stylesheet' id='other-css' href='{$url}?ver=1.0' media='all' />" . $body,
				'expected_contains'   => "<link rel='stylesheet' id='other-css'",
				'expected_absent'     => '<style',
			),
		);

		foreach ( $test_cases as $case ) {
			// 対象ハンドルを登録して CSS を wp_styles に載せる.
			wp_deregister_style( 'vk-css-test' );
			wp_register_style( 'vk-css-test', $this->css_url, array(), '1.0' );
			add_filter(
				$case['filter'],
				function ( $handles ) {
					$handles[] = 'vk-css-test';
					return $handles;
				}
			);

			$actual = VkCssOptimize::css_tree_shaking_buffer( $case['buffer'] );

			$this->assertStringContainsString( $case['expected_contains'], $actual, $case['test_condition_name'] );
			$this->assertStringNotContainsString( $case['expected_absent'], $actual, $case['test_condition_name'] );

			remove_all_filters( $case['filter'] );
		}
	}

	/**
	 * replace_stylesheet_link_with_style() が置換後の CSS の $ や \ を壊さず、特殊文字を含む id・href でも置換できるか
	 */
	public function test_replace_stylesheet_link_with_style() {
		$test_cases = array(
			array(
				'test_condition_name' => 'CSS に $1 や \\ を含んでも文字どおり出力される',
				'buffer'              => "<link rel='stylesheet' id='a-css' href='http://x/a.css?ver=1' media='all' />",
				'id'                  => 'a',
				'href'                => 'http://x/a.css?ver=1',
				'css'                 => '.a:after{content:"\\201C $1 $0"}',
				'expected'            => '<style id=\'a-css\' type=\'text/css\'>.a:after{content:"\\201C $1 $0"}</style>',
			),
			array(
				'test_condition_name' => 'id と href に正規表現の特殊文字（. + ?）を含んでも置換される',
				'buffer'              => "<link rel='stylesheet' id='a.b+c-css' href='http://x/a.css?ver=1.2+3' type='text/css' media='all' />",
				'id'                  => 'a.b+c',
				'href'                => 'http://x/a.css?ver=1.2+3',
				'css'                 => 'p{}',
				'expected'            => "<style id='a.b+c-css' type='text/css'>p{}</style>",
			),
			array(
				'test_condition_name' => 'ver の違う link は置換されない（境界）',
				'buffer'              => "<link rel='stylesheet' id='a-css' href='http://x/a.css?ver=2' media='all' />",
				'id'                  => 'a',
				'href'                => 'http://x/a.css?ver=1',
				'css'                 => 'p{}',
				'expected'            => "<link rel='stylesheet' id='a-css' href='http://x/a.css?ver=2' media='all' />",
			),
		);

		foreach ( $test_cases as $case ) {
			$actual = VkCssOptimize::replace_stylesheet_link_with_style( $case['buffer'], $case['id'], $case['href'], $case['css'] );
			$this->assertSame( $case['expected'], $actual, $case['test_condition_name'] );
		}
	}
}
