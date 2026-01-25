<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if (class_exists('WSP_Plugin')) {
	return;
}

class WSP_Plugin {
	protected $loader;

	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_shipping_hooks();
	}
	/**
	 * Core dependencies (Non-woocommerce)
	 */

	private function load_dependencies() {
		require_once WSP_PATH . 'includes/class-wsp-loader.php';
		require_once WSP_PATH . 'includes/admin/class-wsp-store-cpt.php';
		require_once WSP_PATH . 'includes/admin/class-wsp-store-meta.php';

		$this->loader = new WSP_Loader();
	}
	/**
	 * Load Shipping class After WooCommerce is loaded
	 */
	public function load_shipping_method() {
		if ( class_exists( 'WC_Shipping_Method' ) ) {
			require_once WSP_PATH . 'includes/shipping/class-wsp-shipping-pickup.php';
		}
	}
	/**
	 * Register shipping method with woocommerce
	 */
	public static function register_shipping_method( $methods ) {
		$methods[ 'wsp_store_pickup' ] = 'WSP_Shipping_Pickup';
		return $methods;
	}

	private function define_admin_hooks() {
		$store_cpt = new WSP_Store_CPT();
		$store_meta = new WSP_Store_Meta();

		$this->loader->add_action( 'init', $store_cpt, 'register_cpt' );
		$this->loader->add_action( 'add_meta_boxes', $store_meta, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post', $store_meta, 'save_meta', 10, 2 );
	}
	private function define_shipping_hooks() {
    	// Load shipping class at the right time
		add_action(
			'woocommerce_shipping_init',
			array( $this, 'load_shipping_method' )
		);

		// Register shipping method
		add_filter(
			'woocommerce_shipping_methods',
			array( $this, 'register_shipping_method' )
		);
	}


	public function run() {
		$this->loader->run();
	}
	
}