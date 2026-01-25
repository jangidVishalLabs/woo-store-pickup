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
	}

	private function load_dependencies() {
		require_once WSP_PATH . 'includes/class-wsp-loader.php';
		require_once WSP_PATH . 'includes/admin/class-wsp-store-cpt.php';
		require_once WSP_PATH . 'includes/admin/class-wsp-store-meta.php';

		$this->loader = new WSP_Loader();
	}

	private function define_admin_hooks() {
		$store_cpt = new WSP_Store_CPT();
		$store_meta = new WSP_Store_Meta();

		$this->loader->add_action( 'init', $store_cpt, 'register_cpt' );
		$this->loader->add_action( 'add_meta_boxes', $store_meta, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post', $store_meta, 'save_meta', 10, 2 );
	}

	public function run() {
		$this->loader->run();
	}
}