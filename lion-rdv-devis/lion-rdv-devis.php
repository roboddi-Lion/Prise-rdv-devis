<?php
/**
 * Plugin Name: Lion Rénovation - Prise de RDV Devis
 * Description: Permet aux visiteurs du site de réserver eux-mêmes une visite technique / devis, synchronisée avec les agendas Google Calendar de Romain et Emmanuel Bonnevie.
 * Version: 1.5.0
 * Author: Lion Rénovation
 * Text Domain: lion-rdv-devis
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LION_RDV_DEVIS_VERSION', '1.5.0' );
define( 'LION_RDV_DEVIS_PLUGIN_FILE', __FILE__ );
define( 'LION_RDV_DEVIS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LION_RDV_DEVIS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LION_RDV_DEVIS_DB_VERSION', '1.1.0' );

require_once LION_RDV_DEVIS_PLUGIN_DIR . 'includes/class-lion-rdv-devis-settings.php';
require_once LION_RDV_DEVIS_PLUGIN_DIR . 'includes/class-lion-rdv-devis-db.php';
require_once LION_RDV_DEVIS_PLUGIN_DIR . 'includes/class-lion-rdv-devis-google-client.php';
require_once LION_RDV_DEVIS_PLUGIN_DIR . 'includes/class-lion-rdv-devis-availability.php';
require_once LION_RDV_DEVIS_PLUGIN_DIR . 'includes/class-lion-rdv-devis-notifications.php';
require_once LION_RDV_DEVIS_PLUGIN_DIR . 'includes/class-lion-rdv-devis-rest-controller.php';
require_once LION_RDV_DEVIS_PLUGIN_DIR . 'includes/class-lion-rdv-devis-shortcode.php';
require_once LION_RDV_DEVIS_PLUGIN_DIR . 'includes/class-lion-rdv-devis-plugin.php';

register_activation_hook( __FILE__, array( 'Lion_RDV_Devis_DB', 'activate' ) );

Lion_RDV_Devis_Plugin::instance();
