<?php
/**
 * This class initialisises the plugin and does the setup legwork
 *
 * @package nine3versions
 */

namespace nine3versions;

/**
 * This is a wrapper class which instantiates everything else required for the plugin to work
 */
final class Versions {
	/**
	 * Declare object vars so we don't throw deeprecation errors in php 8.2
	 */
	public $helpers;
	public $ui;
	public $urls;
	public $actions;

	/**
	 * Nonce action
	 *
	 * @var string $nonce_action
	 */
	public $nonce_action = 'nine3v_action';

	/**
	 * Nonce name
	 *
	 * @var string $nine3v_name
	 */
	public $nonce_name = 'nine3v_name';

	/**
	 * Construct
	 */
	public function __construct() {
		// Load Helpers class.
		$this->helpers = new Versions_Helpers();

		// Admins only.
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include ABSPATH . 'wp-includes/pluggable.php';
		}
		if ( current_user_can( 'administrator' ) ) {
			// Enqueue assets.
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

			// Load UI class.
			$this->ui = new Versions_Ui();

			// Load actions class.
			$this->actions = new Versions_Actions();
		}

		// Load URLs class.
		$this->urls = new Versions_URLs();
	}

	/**
	 * 'admin_enqueue_scripts' action hook callbackk
	 * Enqueue a script in the WordPress admin on edit.php.
	 */
	public function enqueue_assets() {
		$current = get_current_screen();
		$allowed = [ 'edit-page', 'page' ];
		if ( ! $current || ! in_array( $current->id, $allowed ) ) {
			return;
		}
		
		$data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( $this->nonce_action ),
		];
		
		if ( $current->id === 'page' ) {
			$data['page'] = get_permalink();
		}

		wp_enqueue_style( 'nine3v-style', NINE3_VERSIONS_URI . '/styles.css', [], '1.0.0' );
		wp_register_script( 'nine3v-script', NINE3_VERSIONS_URI . '/build/scripts.js', [], '1.0.0', true );
		wp_localize_script( 'nine3v-script', 'nine3v', $data );
		wp_enqueue_script( 'nine3v-script' );
	}
}
