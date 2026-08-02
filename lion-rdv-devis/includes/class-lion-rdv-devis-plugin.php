<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrateur principal : câble les settings, la REST API et le shortcode.
 */
class Lion_RDV_Devis_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_db' ) );

		new Lion_RDV_Devis_Settings();
		new Lion_RDV_Devis_Rest_Controller();
		new Lion_RDV_Devis_Shortcode();

		Lion_RDV_Devis_Notifications::init();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'lion-rdv-devis', false, dirname( plugin_basename( LION_RDV_DEVIS_PLUGIN_FILE ) ) . '/languages' );
	}

	public function maybe_upgrade_db() {
		if ( get_option( 'lion_rdv_devis_db_version' ) !== LION_RDV_DEVIS_DB_VERSION ) {
			Lion_RDV_Devis_DB::activate();
		}
	}
}
