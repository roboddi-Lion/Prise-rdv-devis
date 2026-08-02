<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification interne (Lion Rénovation) à chaque nouvelle demande de RDV.
 *
 * Le client, lui, n'a pas besoin d'un email envoyé par ce plugin : quand
 * l'événement Google Calendar est créé avec succès, Google lui envoie
 * automatiquement une invitation par email (sendUpdates=all, voir
 * Lion_RDV_Devis_Google_Client::create_event()). Si la création échoue, le
 * widget affiche directement au client un message l'informant qu'il sera
 * recontacté (voir Lion_RDV_Devis_Rest_Controller::handle_book_slot()).
 *
 * ⚠️ wp_mail() utilise par défaut la fonction PHP mail() du serveur, souvent
 * bloquée ou fortement filtrée par les hébergeurs mutualisés. Si la
 * notification interne n'arrive pas, installez un plugin SMTP (ex. "WP Mail
 * SMTP") relié à un vrai service d'envoi plutôt que mail() natif.
 */
class Lion_RDV_Devis_Notifications {

	private static $last_mail_error = null;

	public static function init() {
		add_action( 'wp_mail_failed', array( __CLASS__, 'capture_mail_error' ) );
	}

	public static function capture_mail_error( WP_Error $error ) {
		self::$last_mail_error = $error->get_error_message();
	}

	/**
	 * @param array $booking       Voir Lion_RDV_Devis_Rest_Controller::handle_book_slot().
	 * @param array $google_result Retour de Lion_RDV_Devis_Google_Client::create_event().
	 */
	public static function send_internal_notification( array $booking, $google_result ) {
		$settings = Lion_RDV_Devis_Settings::get_settings();
		$to       = $settings['notification_email'];

		if ( empty( $to ) ) {
			return;
		}

		$service_label  = $settings['services'][ $booking['service_type'] ]['label'] ?? $booking['service_type'];
		$persons_labels = Lion_RDV_Devis_Settings::get_persons_labels();
		$assigned_label = $booking['assigned_person'] ? ( $persons_labels[ $booking['assigned_person'] ] ?? $booking['assigned_person'] ) : __( 'non déterminé', 'lion-rdv-devis' );

		$status_label = $google_result['success']
			? sprintf(
				/* translators: %s: assigned person */
				__( 'créé dans l\'agenda Google de %s', 'lion-rdv-devis' ),
				$assigned_label
			)
			: __( 'ÉCHEC de création dans Google Calendar - à créer manuellement', 'lion-rdv-devis' );

		$subject = sprintf(
			/* translators: %s: service type */
			__( 'Nouvelle demande de RDV devis "%s" en ligne', 'lion-rdv-devis' ),
			$service_label
		);

		$lines = array(
			sprintf( '%s : %s', __( 'Type de projet', 'lion-rdv-devis' ), $service_label ),
			sprintf( '%s : %s', __( 'Statut', 'lion-rdv-devis' ), $status_label ),
			sprintf( '%s : %s', __( 'Date', 'lion-rdv-devis' ), date_i18n( 'l j F Y', $booking['start']->getTimestamp() ) ),
			sprintf( '%s : %s - %s', __( 'Heure', 'lion-rdv-devis' ), $booking['start']->format( 'H:i' ), $booking['end']->format( 'H:i' ) ),
			sprintf( '%s : %s %s', __( 'Client', 'lion-rdv-devis' ), $booking['first_name'], $booking['last_name'] ),
			sprintf( '%s : %s', __( 'Téléphone', 'lion-rdv-devis' ), $booking['phone'] ),
			sprintf( '%s : %s', __( 'Email', 'lion-rdv-devis' ), $booking['email'] ),
			sprintf( '%s : %s, %s %s', __( 'Adresse', 'lion-rdv-devis' ), $booking['address'], $booking['postal_code'], $booking['city'] ),
			sprintf( '%s : %s', __( 'Message', 'lion-rdv-devis' ), $booking['message'] ? $booking['message'] : '-' ),
		);

		if ( ! $google_result['success'] ) {
			$lines[] = sprintf( '%s : %s', __( 'Erreur Google Calendar', 'lion-rdv-devis' ), $google_result['error'] );
		} elseif ( ! empty( $google_result['event_link'] ) ) {
			$lines[] = sprintf( '%s : %s', __( 'Lien de l\'événement', 'lion-rdv-devis' ), $google_result['event_link'] );
		}

		self::$last_mail_error = null;
		$sent                  = wp_mail( $to, $subject, implode( "\n", $lines ) );

		if ( ! $sent && self::$last_mail_error ) {
			error_log( sprintf( '[Lion RDV Devis] Échec envoi notification interne à %s : %s', $to, self::$last_mail_error ) );
		}
	}
}
