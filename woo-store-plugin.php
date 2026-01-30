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
 * Initialize and run the main plugin class.
 */
require_once WSP_PATH . 'includes/class-wsp-plugin.php';
require_once WSP_PATH . 'includes/admin/class-wsp-admin-orders.php';
require_once WSP_PATH . 'includes/cron/class-wsp-store-cron.php';


/**
 * Instantiate and run the main plugin functionality.
 *
 * Creates a new WSP_Plugin instance and executes the plugin initialization.
 *
 * @return void
 */
function run_wsp_plugin() {
	$plugin = new WSP_Plugin();
	$plugin->run();
}
run_wsp_plugin();


/**
 * Schedule cron jobs on plugin activation and deactivation.
 */
register_activation_hook( __FILE__, 'wsp_schedule_pickup_reminder_cron' );
register_deactivation_hook( __FILE__, 'wsp_clear_pickup_reminder_cron' );

