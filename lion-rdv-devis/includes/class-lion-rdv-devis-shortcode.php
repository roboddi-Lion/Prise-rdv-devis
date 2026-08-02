<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode [lion_rdv_devis] : widget public de prise de rendez-vous pour
 * une visite technique / devis.
 */
class Lion_RDV_Devis_Shortcode {

	public function __construct() {
		add_shortcode( 'lion_rdv_devis', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		wp_enqueue_style(
			'lion-rdv-devis',
			LION_RDV_DEVIS_PLUGIN_URL . 'assets/css/devis-widget.css',
			array(),
			LION_RDV_DEVIS_VERSION
		);

		wp_enqueue_script(
			'lion-rdv-devis',
			LION_RDV_DEVIS_PLUGIN_URL . 'assets/js/devis-widget.js',
			array(),
			LION_RDV_DEVIS_VERSION,
			true
		);

		$services = array();
		foreach ( Lion_RDV_Devis_Settings::get_settings()['services'] as $key => $service ) {
			$services[] = array(
				'key'         => $key,
				'label'       => $service['label'],
				'description' => $service['description'],
			);
		}

		wp_localize_script(
			'lion-rdv-devis',
			'lionRdvDevisSettings',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'lion-rdv-devis/v1' ) ),
				'services' => $services,
				'i18n'     => array(
					'stepProject'   => __( 'Projet', 'lion-rdv-devis' ),
					'stepSlot'      => __( 'Créneau', 'lion-rdv-devis' ),
					'stepContact'   => __( 'Coordonnées', 'lion-rdv-devis' ),
					'chooseService' => __( 'Quel type de projet souhaitez-vous faire chiffrer ?', 'lion-rdv-devis' ),
					'loadingSlots'  => __( 'Chargement des créneaux disponibles…', 'lion-rdv-devis' ),
					'noSlots'       => __( 'Aucun créneau disponible pour le moment. Merci de nous contacter directement.', 'lion-rdv-devis' ),
					'chooseDay'     => __( 'Choisissez un jour', 'lion-rdv-devis' ),
					'chooseTime'    => __( 'Choisissez un horaire', 'lion-rdv-devis' ),
					'back'          => __( '← Retour', 'lion-rdv-devis' ),
					'yourInfo'      => __( 'Vos coordonnées', 'lion-rdv-devis' ),
					'firstName'     => __( 'Prénom', 'lion-rdv-devis' ),
					'lastName'      => __( 'Nom', 'lion-rdv-devis' ),
					'phone'         => __( 'Téléphone', 'lion-rdv-devis' ),
					'email'         => __( 'Email', 'lion-rdv-devis' ),
					'address'       => __( 'Adresse du projet', 'lion-rdv-devis' ),
					'postalCode'    => __( 'Code postal', 'lion-rdv-devis' ),
					'city'          => __( 'Ville', 'lion-rdv-devis' ),
					'message'       => __( 'Précisez votre projet (optionnel)', 'lion-rdv-devis' ),
					'confirm'       => __( 'Confirmer le rendez-vous', 'lion-rdv-devis' ),
					'sending'       => __( 'Envoi en cours…', 'lion-rdv-devis' ),
					'selectedSlot'  => __( 'Créneau sélectionné', 'lion-rdv-devis' ),
					'genericError'  => __( 'Une erreur est survenue. Merci de réessayer.', 'lion-rdv-devis' ),
					'startOver'     => __( 'Prendre un autre rendez-vous', 'lion-rdv-devis' ),
				),
			)
		);

		ob_start();
		?>
		<div id="lion-rdv-devis-widget" class="lion-rdv-devis-widget" aria-live="polite"></div>
		<?php
		return ob_get_clean();
	}
}
