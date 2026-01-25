<?php
/**
 *  Plugin Name: Store Plugin
 *  Description: A plugin to manage store functionalities.
 *  Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// Define plugin constants.
define( 'WSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSP_URL', plugin_dir_url( __FILE__ ) );

require_once WSP_PATH . 'includes/class-wsp-plugin.php';
function run_wsp_plugin() {
	$plugin = new WSP_Plugin();
	$plugin->run();
}
run_wsp_plugin();
