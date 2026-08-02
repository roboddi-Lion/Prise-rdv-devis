<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Action non autorisée.', 'lion-rdv-devis' ) );
}

$bookings       = Lion_RDV_Devis_DB::get_recent_bookings( 100 );
$services       = Lion_RDV_Devis_Settings::get_settings()['services'];
$persons_labels = Lion_RDV_Devis_Settings::get_persons_labels();

$status_labels = array(
	'confirmed' => __( 'Confirmée', 'lion-rdv-devis' ),
	'failed'    => __( 'Échec Google Calendar', 'lion-rdv-devis' ),
	'pending'   => __( 'En attente', 'lion-rdv-devis' ),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Réservations devis récentes', 'lion-rdv-devis' ); ?></h1>
	<p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=lion-rdv-devis' ) ); ?>">&larr; <?php esc_html_e( 'Retour aux réglages', 'lion-rdv-devis' ); ?></a></p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Créé le', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Créneau', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Type de projet', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Agenda assigné', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Client', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Coordonnées', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Adresse', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Statut', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Google Calendar', 'lion-rdv-devis' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $bookings ) ) : ?>
			<tr><td colspan="9"><?php esc_html_e( 'Aucune réservation pour le moment.', 'lion-rdv-devis' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $bookings as $booking ) : ?>
			<tr>
				<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $booking->created_at ) ); ?></td>
				<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $booking->slot_start ) . ' - ' . mysql2date( 'H:i', $booking->slot_end ) ); ?></td>
				<td><?php echo esc_html( $services[ $booking->service_type ]['label'] ?? $booking->service_type ); ?></td>
				<td><?php echo esc_html( $booking->assigned_person ? ( $persons_labels[ $booking->assigned_person ] ?? $booking->assigned_person ) : '-' ); ?></td>
				<td><?php echo esc_html( $booking->first_name . ' ' . $booking->last_name ); ?></td>
				<td><?php echo esc_html( $booking->phone ); ?><br /><?php echo esc_html( $booking->email ); ?></td>
				<td><?php echo esc_html( $booking->address . ', ' . $booking->postal_code . ' ' . $booking->city ); ?></td>
				<td>
					<?php $status = $booking->status; ?>
					<span style="font-weight:600;color:<?php echo 'confirmed' === $status ? '#1a7f37' : ( 'failed' === $status ? '#c62828' : '#996800' ); ?>;">
						<?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
					</span>
				</td>
				<td>
					<?php if ( $booking->google_event_link ) : ?>
						<a href="<?php echo esc_url( $booking->google_event_link ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'voir l\'événement', 'lion-rdv-devis' ); ?></a>
					<?php elseif ( $booking->google_error ) : ?>
						<span title="<?php echo esc_attr( $booking->google_error ); ?>" style="color:#c62828;">⚠ <?php esc_html_e( 'voir erreur', 'lion-rdv-devis' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
