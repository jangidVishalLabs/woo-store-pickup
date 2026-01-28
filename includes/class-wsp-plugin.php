<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'WSP_Plugin' ) ) {
	return;
}

class WSP_Plugin {
	protected $loader;

	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_shipping_hooks();
		$this->define_checkout_hooks();
		$this->define_email_hooks();
	}
	/**
	 * Core dependencies (Non-woocommerce)
	 */
	private function load_dependencies() {
		require_once WSP_PATH . 'includes/class-wsp-loader.php';
		require_once WSP_PATH . 'includes/admin/class-wsp-store-cpt.php';
		require_once WSP_PATH . 'includes/admin/class-wsp-store-meta.php';
		require_once WSP_PATH . 'includes/checkout/class-wsp-checkout-fields.php';
		require_once WSP_PATH . 'includes/emails/class-wsp-email-handler.php';
		require_once WSP_PATH . 'includes/admin/class-wsp-admin-orders.php';

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
		$methods['wsp_store_pickup'] = 'WSP_Shipping_Pickup';
		return $methods;
	}
	private function define_checkout_hooks() {
		$checkout = new WSP_Checkout_Fields();

		$this->loader->add_action( 'woocommerce_after_order_notes', $checkout, 'render_fields' );
		$this->loader->add_action( 'woocommerce_checkout_create_order', $checkout, 'save_fields', 20, 1);
		$this->loader->add_action( 'woocommerce_checkout_process', $checkout, 'validate_fields' );
		$this->loader->add_action( 'woocommerce_admin_order_data_after_billing_address', $checkout, 'display_admin_order_pickup_details' );
		$this->loader->add_action( 'woocommerce_thankyou', $checkout, 'display_customer_pickup_details' );
		// My Account -> View Order
		$this->loader->add_action( 'woocommerce_view_order', $checkout, 'display_customer_pickup_details' );
	}


	private function define_admin_hooks() {
		$store_cpt  = new WSP_Store_CPT();
		$store_meta = new WSP_Store_Meta();

		$this->loader->add_action( 'init', $store_cpt, 'register_cpt' );
		$this->loader->add_action( 'add_meta_boxes', $store_meta, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post', $store_meta, 'save_meta', 10, 2 );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_scripts' );

		$admin_orders = new WSP_Admin_Orders();

		$this->loader->add_action(
			'manage_woocommerce_page_wc-orders_columns',
			$admin_orders,
			'add_columns'
		);

		$this->loader->add_action(
			'manage_woocommerce_page_wc-orders_custom_column',
			$admin_orders,
			'render_columns',
			10,
			2
		);

		$this->loader->add_action(
			'restrict_manage_posts',
			$admin_orders,
			'add_pickup_date_filter'
		);

		$this->loader->add_action(
			'pre_get_posts',
			$admin_orders,
			'filter_orders_by_pickup_date'
		);

		$this->loader->add_filter(
			'woocommerce_shop_order_search_fields',
			$admin_orders,
			'enable_store_search'
		);
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

	public function enqueue_scripts() {
		if ( is_checkout() ) {
			wp_enqueue_script(
				'wsp-checkout',
				plugin_dir_url( __FILE__ ) . 'assets/js/wsp-checkout.js',
				array( 'jquery' ),
				WSP_VERSION,
				true
			);

			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );

		}
	}
	private function define_email_hooks() {
		$email_handler = new WSP_Email_Handler();

		$this->loader->add_action( 'woocommerce_email_order_details', $email_handler, 'add_pickup_details_to_email', 20, 4 );
	}




	public function run() {
		$this->loader->run();
	}
}
