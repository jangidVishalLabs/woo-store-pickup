<?php
/**
 * Plugin Name: Store Plugin
 * Description: Store Pickup with reminder emails
 * Version: 1.0.0
 * 
 * @package WooStorePlugin
 * @author Your Company
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants.
 */
define( 'WSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSP_URL', plugin_dir_url( __FILE__ ) );
define( 'WSP_VERSION', '1.0.0' );

/**
 * Activation / Deactivation hooks.
 * MUST be called directly in the main plugin file — not inside a class or function.
 */
register_activation_hook( __FILE__, 'wsp_activate' );
register_deactivation_hook( __FILE__, 'wsp_deactivate' );

function wsp_activate() {
	// Schedule cron jobs
	require_once WSP_PATH . 'includes/cron/class-wsp-store-cron.php';
	wsp_schedule_pickup_reminder_cron();

	// Register the shop_owner role on activation so it exists immediately
	require_once WSP_PATH . 'includes/admin/class-wsp-show-owner.php';
	$shop_owner = new WSP_Shop_Owner();
	$shop_owner->register_role();

	// Flush rewrite rules so the pickup_store CPT permalink works
	flush_rewrite_rules();
}

function wsp_deactivate() {
	require_once WSP_PATH . 'includes/cron/class-wsp-store-cron.php';
	wsp_clear_pickup_reminder_cron();
	flush_rewrite_rules();
}

/**
 * Bootstrap the plugin after WordPress and WooCommerce are loaded.
 */
add_action( 'plugins_loaded', 'run_wsp_plugin', 10 );

function run_wsp_plugin() {
	// Hard-fail gracefully if WooCommerce is not active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p><strong>Store Plugin</strong> requires WooCommerce to be active.</p></div>';
		});
		return;
	}

	require_once WSP_PATH . 'includes/class-wsp-plugin.php';

	$plugin = new WSP_Plugin();
	$plugin->run();
}