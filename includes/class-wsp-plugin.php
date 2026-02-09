	<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly.
	}

	if ( class_exists( 'WSP_Plugin' ) ) {
		return;
	}

	/**
	 * WSP_Plugin class.
	 *
	 * Main plugin class responsible for initializing and managing all plugin functionality.
	 * This includes loading dependencies, registering hooks, and managing the plugin lifecycle.
	 *
	 * @class WSP_Plugin
	 * @version 1.0.0
	 */
	class WSP_Plugin {
		/**
		 * The loader instance that manages actions and filters.
		 *
		 * @var WSP_Loader
		 */
		protected $loader;

		/**
		 * Constructor.
		 *
		 * Loads dependencies, defines hooks for admin, shipping, checkout, and email functionality,
		 * and enqueues necessary scripts.
		 */
		public function __construct() {
			$this->load_dependencies();
			$this->define_admin_hooks();
			$this->define_shipping_hooks();
			$this->define_checkout_hooks();
			$this->define_email_hooks();

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
		/**
		 * Load plugin dependencies.
		 *
		 * Includes required files for the plugin functionality including loader,
		 * CPT, meta boxes, checkout, emails, and admin features.
		 *
		 * @return void
		 */
		private function load_dependencies() {
			require_once WSP_PATH . 'includes/class-wsp-loader.php';
			require_once WSP_PATH . 'includes/admin/class-wsp-store-cpt.php';
			require_once WSP_PATH . 'includes/admin/class-wsp-store-meta.php';
			require_once WSP_PATH . 'includes/checkout/class-wsp-checkout-fields.php';
			require_once WSP_PATH . 'includes/emails/class-wsp-email-handler.php';
			require_once WSP_PATH . 'includes/admin/class-wsp-admin-orders.php';
			require_once WSP_PATH . 'includes/admin/class-wsp-show-owner.php';

			$this->loader = new WSP_Loader();
		}
		/**
		 * Load the shipping method class after WooCommerce is initialized.
		 *
		 * This method is hooked to 'woocommerce_shipping_init' to ensure WooCommerce
		 * is fully loaded before attempting to extend WC_Shipping_Method.
		 *
		 * @return void
		 */
		public function load_shipping_method() {
			if ( class_exists( 'WC_Shipping_Method' ) ) {
				require_once WSP_PATH . 'includes/shipping/class-wsp-shipping-pickup.php';
			}
		}
		/**
		 * Register the store pickup shipping method with WooCommerce.
		 *
		 * @param array $methods Array of registered shipping methods.
		 * @return array Modified array of shipping methods.
		 */
		public static function register_shipping_method( $methods ) {
			$methods['wsp_store_pickup'] = 'WSP_Shipping_Pickup';
			return $methods;
		}
		/**
		 * Define checkout-related hooks.
		 *
		 * Registers hooks for rendering checkout fields, validation, saving order meta,
		 * and AJAX operations related to pickup store selection.
		 *
		 * @return void
		 */
		private function define_checkout_hooks() {
			$checkout = new WSP_Checkout_Fields();

			$this->loader->add_action( 'woocommerce_after_order_notes', $checkout, 'render_fields' );
			$this->loader->add_action( 'woocommerce_checkout_create_order', $checkout, 'save_fields', 20, 1 );
			$this->loader->add_action( 'woocommerce_checkout_process', $checkout, 'validate_fields' );
			$this->loader->add_action( 'woocommerce_admin_order_data_after_billing_address', $checkout, 'display_admin_order_pickup_details' );
			$this->loader->add_action( 'woocommerce_thankyou', $checkout, 'display_customer_pickup_details' );
			// My Account -> View Order
			$this->loader->add_action( 'woocommerce_view_order', $checkout, 'display_customer_pickup_details' );
			$this->loader->add_action(
				'wp_ajax_wsp_get_pickup_stores',
				$checkout,
				'wsp_get_pickup_stores_ajax'
			);
			$this->loader->add_action(
				'wp_ajax_nopriv_wsp_get_pickup_stores',
				$checkout,
				'wsp_get_pickup_stores_ajax'
			);
		}


		/**
		 * Define admin-related hooks.
		 *
		 * Registers hooks for custom post type, meta boxes, order admin columns,
		 * filtering, and shop owner role management.
		 *
		 * @return void
		 */
		private function define_admin_hooks() {
			$store_cpt  = new WSP_Store_CPT();
			$store_meta = new WSP_Store_Meta();

			$this->loader->add_action( 'init', $store_cpt, 'register_cpt' );

			$this->loader->add_action( 'add_meta_boxes', $store_meta, 'add_meta_boxes' );
			$this->loader->add_action( 'save_post', $store_meta, 'save_meta', 10, 2 );
			$this->loader->add_filter( 'wp_insert_post_data', $store_meta, 'mandatory_title', 10, 2 );
			$this->loader->add_action( 'before_delete_post', $store_meta, 'on_delete_remove_zone_mapping' );
			$this->loader->add_filter( 'manage_pickup_store_posts_columns', $store_meta, 'custom_status_columns' );
			$this->loader->add_action( 'manage_pickup_store_posts_custom_column', $store_meta, 'render_custom_status_columns', 10, 2 );
			$this->loader->add_action( 'admin_notices', $store_meta, 'show_title_validation_error' );


			$admin_orders = new WSP_Admin_Orders();

			// HPOS (High-Performance Order Storage) Hooks
			$this->loader->add_filter(
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
				'woocommerce_order_list_table_restrict_manage_orders',
				$admin_orders,
				'add_pickup_date_filter'
			);

			$this->loader->add_filter(
				'woocommerce_orders_table_query_clauses',
				$admin_orders,
				'filter_orders_by_pickup_date_hpos',
				10,
				2
			);

			// Legacy (Post-based Orders) Hooks - for backwards compatibility
			$this->loader->add_filter(
				'manage_edit-shop_order_columns',
				$admin_orders,
				'add_columns'
			);

			$this->loader->add_action(
				'manage_shop_order_posts_custom_column',
				$admin_orders,
				'render_columns',
				10,
				2
			);

			$this->loader->add_action(
				'restrict_manage_posts',
				$admin_orders,
				'add_pickup_date_filter_legacy'
			);

			$this->loader->add_filter(
				'parse_query',
				$admin_orders,
				'filter_orders_by_pickup_date_legacy'
			);

			$this->loader->add_action(
				'woocommerce_orders_table_query_clauses',
				$admin_orders,
				'search_orders_by_pickup_store_hpos',
				20,
				2
			);

			$this->loader->add_action(
				'woocommerce_admin_order_data_after_shipping_address',
				$admin_orders,
				'wsp_admin_edit_pickup_date_field'
			);

			$this->loader->add_action(
				'woocommerce_process_shop_order_meta',
				$admin_orders,
				'wsp_save_admin_pickup_date'
			);

			$shop_owner = new WSP_Shop_Owner();

			$this->loader->add_action( 'init', $shop_owner, 'register_role' );

			// Restrict Pickup Store CPT
			$this->loader->add_action( 'pre_get_posts', $shop_owner, 'filter_pickup_store_list' );
			$this->loader->add_filter( 'woocommerce_orders_table_query_clauses', $shop_owner, 'filter_orders_by_store_hpos', 10, 2 );
			$this->loader->add_action( 'pre_get_posts', $shop_owner, 'filter_orders_legacy', 10 );
			$this->loader->add_filter(
				'map_meta_cap', $shop_owner,
				'map_order_edit_caps', 10, 4
			);
			$this->loader->add_filter( 'woocommerce_valid_order_statuses_for_user', $shop_owner, 'allow_shop_owner_order_statuses', 10, 2 );

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

		/**
		 * Enqueue frontend scripts needed for checkout functionality.
		 *
		 * This is hooked to 'wp_enqueue_scripts' and only enqueues scripts on the checkout page.
		 *
		 * @return void
		 */
		public function enqueue_scripts() {
			if ( is_checkout() ) {
				wp_enqueue_script(
					'wsp-checkout',
					plugin_dir_url( __FILE__ ) . 'assets/js/wsp-checkout.js',
					array( 'jquery', 'wc-checkout' ),
					WSP_VERSION,
					true
				);
			}
		}

		/**
		 * Define email-related hooks.
		 *
		 * Registers hooks for adding pickup details to WooCommerce email notifications.
		 *
		 * @return void
		 */
		private function define_email_hooks() {
			$email_handler = new WSP_Email_Handler();

			$this->loader->add_action( 'woocommerce_email_order_details', $email_handler, 'add_pickup_details_to_email', 20, 4 );
		}

		/**
		 * Run the plugin.
		 *
		 * Executes all registered actions and filters through the loader.
		 *
		 * @return void
		 */
		public function run() {
			$this->loader->run();
		}
	}
