<?php

/*
Plugin Name: WP-gogtranslator
Plugin URI: http://neutronik.pl
Description: Automaticly translate your whole posts by google Api transaltor
Version: 1.0
Author: Krzysztof Grygiel
Author URI:  http://neutronik.pl
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



class SP_Plugin {
		// set Google Api Key only here
	static $apiKey = '';

	// class instance
	static $instance;


	// class constructor
	public function __construct() {
		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
	}


	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {

		$hook = add_menu_page(
			'WP gogtranslator',
			'WP gogtranslator',
			'manage_options',
			'wp-gogtranslator_dashboard',
			[ $this, 'plugin_settings_page' ]
		);

		add_action( "load-$hook", [ $this, 'screen_option' ] );

		add_action('admin_enqueue_scripts', 'ln_reg_css_and_js');

		    function ln_reg_css_and_js($hook)
		    {

		    $current_screen = get_current_screen();

		    if ( strpos($current_screen->base, 'wp-gogtranslator') === false) {
		        return;
		    } else {

		        wp_enqueue_style('boot_css', plugins_url('css/custom.css',__FILE__ ));
		        //wp_enqueue_script('boot_js', plugins_url('inc/bootstrap.js',__FILE__ ));
		        //wp_enqueue_script('ln_script', plugins_url('inc/main_script.js', __FILE__), ['jquery'], false, true);
		        }
		    }

	}



	/**
	 * Screen options
	 */
	public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => 'Items',
			'default' => 5,
			'option'  => 'items_per_page'
		];

		add_screen_option( $option, $args );

	}

	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function mainTranslation($url) {
		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($handle);
		$responseDecoded = json_decode($response, true);
		$responseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);      //Here we fetch the HTTP response code
		curl_close($handle);

		if($responseCode != 200) {
				return false;
			}else{
				return $responseDecoded;
		}
	}

	public static function getTranslationInfo($string) {
		$url = 'https://translation.googleapis.com/language/translate/v2/detect?key=' . self::$apiKey . '&q=' . rawurlencode($string);
		$response = self::mainTranslation($url);
		if ($response) {
			return $response['data']['detections'][0][0]['language'];
		}else{
			return false;
		}
	}

	public static function getTranslation($string) {
		$url = 'https://translation.googleapis.com/language/translate/v2?key=' . self::$apiKey . '&q=' . rawurlencode($string) . '&target=pl';
		$response = self::mainTranslation($url);
		if ($response) {
			return $response['data']['translations'][0]['translatedText'];
		}else{
			return false;
		}
	}

	public static function getPosts($how_many) {
		global $wpdb;
		$sql = "SELECT id, post_title, post_content FROM {$wpdb->prefix}posts ".$how_many;
		$cdata = $wpdb->get_results($sql);
		return $cdata;
	}

	public static function translateContent($how_many) {
		$posts = self::getPosts($how_many);
		kses_remove_filters();
		foreach($posts as $post) {
			if (self::getTranslationInfo(strtolower($post->post_title)) == 'en') {
				$translated = self::getTranslation(strtolower($post->post_title));
				$my_post = array(
						'ID'         => $post->id,
						'post_title'   => ucwords($translated)
				);
				wp_update_post( $my_post );
			}
			if (self::getTranslationInfo($post->post_content) == 'en') {
				$translated = self::getTranslation($post->post_content);
				$my_post = array(
						'ID'         => $post->id,
						'post_content'   => $translated
				);
				wp_update_post( $my_post );
			}
			$posttags = get_the_tags($post->id);

				if ($posttags) {
				  foreach($posttags as $tag) {
						if (self::getTranslationInfo(strtolower($tag->name)) == 'en') {
							$translated = self::getTranslation(strtolower($tag->name));
							//echo $translated;
							$check_term = get_term_by( 'name', $translated, 'post_tag' );  //Sprawdzamy czy tag juz podony istNieje
							//echo '<pre>' . var_export($tag, true) . '</pre>';
							if ($check_term) {
									//echo '<pre>' . var_export($check_term, true) . '</pre>';
									wp_delete_term($tag->term_id, 'post_tag');
									wp_set_post_tags($post->id, $check_term->name, true);
									//var_dump(wp_update_term($tag->term_id, 'post_tag', array('name' => $check_term->name)));
								}else{
									wp_update_term($tag->term_id, 'post_tag', array('name' => $translated,'slug' => sanitize_title($translated)));
								}
														//echo '<pre>' . var_export($tag, true) . '</pre>';
						}
				  }
				}

		}
		kses_init_filters();
	}


	public function plugin_settings_page() {

		if ( ! empty( $_POST["submit1"] == 'Run' ) ) {
			$how_many = 'LIMIT 1000 OFFSET 0';
	   	self::translateContent($how_many);
			echo 'Translation over for LIMIT 1000 OFFSET 0';
		}
		if ( ! empty( $_POST["submit2"] == 'Run' ) ) {
			$how_many = 'LIMIT 1000 OFFSET 1001';
	   	self::translateContent($how_many);
			echo 'Translation over for LIMIT 1000 OFFSET 1001';
		}
		if ( ! empty( $_POST["submit3"] == 'Run' ) ) {
			$how_many = 'LIMIT 1000 OFFSET 2001';
	   	self::translateContent($how_many);
			echo 'Translation over for LIMIT 1000 OFFSET 2001';
		}
		if ( ! empty( $_POST["submit4"] == 'Run' ) ) {
			$how_many = 'LIMIT 1000 OFFSET 3001';
	   	self::translateContent($how_many);
			echo 'Translation over for LIMIT 1000 OFFSET 3001';
		}
		if ( ! empty( $_POST["submit5"] == 'Run' ) ) {
			$how_many = 'LIMIT 1000 OFFSET 4001';
	   	self::translateContent($how_many);
			echo 'Translation over for LIMIT 1000 OFFSET 4001';
		}
		if ( ! empty( $_POST["submit6"] == 'Run' ) ) {
			$how_many = 'LIMIT 1000 OFFSET 5001';
	   	self::translateContent($how_many);
			echo 'Translation over for LIMIT 1000 OFFSET 5001';
		}
		if ( ! empty( $_POST["submit7"] == 'Run' ) ) {
			$how_many = 'LIMIT 3000 OFFSET 6001';
	   	self::translateContent($how_many);
			echo 'Translation over for LIMIT 3000 OFFSET 6001';
		}
		global $wpdb;
		$sqli = "SELECT count(*) FROM {$wpdb->prefix}posts";
		$coundata = $wpdb->get_var($sqli);

		echo 'Number of posts: '.$coundata;
				?>
		<div class="wrap">
			<h2>WP GogTranslator</h2>

			<div id="col-container" class="wp-clearfix">

			<div id="col-left">
			<div class="col-wrap">

				<div class="form-wrap">
				<h2>Run translation process</h2>
				<form id="addtranslation" method="post" action="" class="validate">
					<input type="hidden" name="action" value="submit_form">
				<p class="submit"><input type="submit" name="submit1" id="submit1" class="button button-primary" value="Run">LIMIT 1000 OFFSET 0</p>
				<p class="submit"><input type="submit" name="submit2" id="submit2" class="button button-primary" value="Run">LIMIT 1000 OFFSET 1001</p>
				<p class="submit"><input type="submit" name="submit3" id="submit3" class="button button-primary" value="Run">LIMIT 1000 OFFSET 2001</p>
				<p class="submit"><input type="submit" name="submit4" id="submit4" class="button button-primary" value="Run">LIMIT 1000 OFFSET 3001</p>
				<p class="submit"><input type="submit" name="submit5" id="submit5" class="button button-primary" value="Run">LIMIT 1000 OFFSET 4001</p>
				<p class="submit"><input type="submit" name="submit6" id="submit6" class="button button-primary" value="Run">LIMIT 1000 OFFSET 5001</p>
				<p class="submit"><input type="submit" name="submit7" id="submit7" class="button button-primary" value="Run">LIMIT 3000 OFFSET 6001</p>
			</form></div>

			</div>
			</div><!-- /col-left -->


			</div>
		</div>
	<?php
	}
}

add_action( 'plugins_loaded', function () {
	SP_Plugin::get_instance();
} );
