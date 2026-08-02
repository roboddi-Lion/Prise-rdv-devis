<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings       = Lion_RDV_Devis_Settings::get_settings();
$persons_labels = Lion_RDV_Devis_Settings::get_persons_labels();

$day_labels = array(
	'lundi'    => __( 'Lundi', 'lion-rdv-devis' ),
	'mardi'    => __( 'Mardi', 'lion-rdv-devis' ),
	'mercredi' => __( 'Mercredi', 'lion-rdv-devis' ),
	'jeudi'    => __( 'Jeudi', 'lion-rdv-devis' ),
	'vendredi' => __( 'Vendredi', 'lion-rdv-devis' ),
	'samedi'   => __( 'Samedi', 'lion-rdv-devis' ),
	'dimanche' => __( 'Dimanche', 'lion-rdv-devis' ),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Prise de RDV Devis Lion Rénovation - Réglages', 'lion-rdv-devis' ); ?></h1>

	<p>
		<?php esc_html_e( 'Insérez le shortcode suivant sur la page de prise de rendez-vous devis de votre site :', 'lion-rdv-devis' ); ?>
		<code>[lion_rdv_devis]</code>
	</p>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=lion-rdv-devis-bookings' ) ); ?>" class="button">
			<?php esc_html_e( 'Voir les réservations récentes', 'lion-rdv-devis' ); ?>
		</a>
	</p>

	<?php if ( isset( $_GET['lion_rdv_devis_oauth_error'] ) ) : ?>
		<div class="notice notice-error" style="padding:10px;">
			<p><strong><?php esc_html_e( 'Échec de connexion Google Calendar :', 'lion-rdv-devis' ); ?></strong> <?php echo esc_html( wp_unslash( $_GET['lion_rdv_devis_oauth_error'] ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['lion_rdv_devis_oauth_connected'] ) ) : ?>
		<div class="notice notice-success" style="padding:10px;">
			<p><?php esc_html_e( 'Agenda Google Calendar connecté avec succès.', 'lion-rdv-devis' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['lion_rdv_devis_oauth_disconnected'] ) ) : ?>
		<div class="notice notice-warning" style="padding:10px;">
			<p><?php esc_html_e( 'Agenda Google Calendar déconnecté.', 'lion-rdv-devis' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Connexion Google Calendar', 'lion-rdv-devis' ); ?></h2>
	<div class="notice notice-info inline" style="padding:10px;margin-left:0;">
		<p><strong><?php esc_html_e( 'Avant de connecter les agendas, créez un projet Google Cloud :', 'lion-rdv-devis' ); ?></strong></p>
		<ol style="list-style:decimal;margin-left:20px;">
			<li><?php esc_html_e( 'Sur console.cloud.google.com, créez un projet et activez l\'API "Google Calendar API".', 'lion-rdv-devis' ); ?></li>
			<li><?php esc_html_e( 'Configurez l\'écran de consentement OAuth (type "Externe" suffit, avec Romain et Emmanuel ajoutés comme utilisateurs test si l\'application reste en mode test).', 'lion-rdv-devis' ); ?></li>
			<li>
				<?php esc_html_e( 'Créez un identifiant OAuth de type "Application Web" et ajoutez cette URI de redirection autorisée :', 'lion-rdv-devis' ); ?>
				<br /><code><?php echo esc_html( Lion_RDV_Devis_Settings::get_oauth_redirect_uri() ); ?></code>
			</li>
			<li><?php esc_html_e( 'Copiez l\'ID client et le secret client obtenus dans les champs ci-dessous, puis enregistrez.', 'lion-rdv-devis' ); ?></li>
		</ol>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'lion_rdv_devis_settings_group' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="google_client_id"><?php esc_html_e( 'ID client Google (Client ID)', 'lion-rdv-devis' ); ?></label></th>
				<td>
					<input type="text" id="google_client_id" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[google_client_id]" value="<?php echo esc_attr( $settings['google_client_id'] ); ?>" class="regular-text" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="google_client_secret"><?php esc_html_e( 'Secret client Google (Client Secret)', 'lion-rdv-devis' ); ?></label></th>
				<td>
					<input type="password" id="google_client_secret" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[google_client_secret]" value="<?php echo esc_attr( $settings['google_client_secret'] ); ?>" class="regular-text" autocomplete="off" />
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Types de projet proposés et routage vers les agendas', 'lion-rdv-devis' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Pour chaque type de projet, cochez le ou les agendas à consulter. Si les deux sont cochés, le plugin propose le premier créneau où au moins l\'un des deux est libre, sans jamais indiquer au client lequel des deux sera présent.', 'lion-rdv-devis' ); ?>
		</p>
		<table class="widefat" style="margin-bottom:1.5em;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nom affiché', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Description affichée', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Durée (min)', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Préavis min. (h)', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Agenda(s) consulté(s)', 'lion-rdv-devis' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $settings['services'] as $service_key => $service ) : ?>
				<tr>
					<td><input type="text" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[services][<?php echo esc_attr( $service_key ); ?>][label]" value="<?php echo esc_attr( $service['label'] ); ?>" class="regular-text" /></td>
					<td><input type="text" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[services][<?php echo esc_attr( $service_key ); ?>][description]" value="<?php echo esc_attr( $service['description'] ); ?>" class="regular-text" /></td>
					<td><input type="number" min="15" step="15" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[services][<?php echo esc_attr( $service_key ); ?>][duration_minutes]" value="<?php echo esc_attr( $service['duration_minutes'] ); ?>" class="small-text" /></td>
					<td><input type="number" min="0" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[services][<?php echo esc_attr( $service_key ); ?>][lead_time_hours]" value="<?php echo esc_attr( $service['lead_time_hours'] ); ?>" class="small-text" /></td>
					<td>
						<?php foreach ( $persons_labels as $person_key => $person_label ) : ?>
							<label style="display:block;white-space:nowrap;">
								<input type="checkbox" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[services][<?php echo esc_attr( $service_key ); ?>][persons][]" value="<?php echo esc_attr( $person_key ); ?>" <?php checked( in_array( $person_key, $service['persons'], true ) ); ?> />
								<?php echo esc_html( $person_label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="horizon_days"><?php esc_html_e( 'Réservable jusqu\'à (jours à l\'avance)', 'lion-rdv-devis' ); ?></label></th>
				<td><input type="number" min="1" max="180" id="horizon_days" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[horizon_days]" value="<?php echo esc_attr( $settings['horizon_days'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="slot_step_minutes"><?php esc_html_e( 'Battement entre deux rendez-vous (min)', 'lion-rdv-devis' ); ?></label></th>
				<td>
					<input type="number" min="0" step="5" id="slot_step_minutes" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[slot_step_minutes]" value="<?php echo esc_attr( $settings['slot_step_minutes'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Temps ajouté après la fin d\'un rendez-vous avant que le suivant puisse commencer (trajet, imprévu...). 0 = rendez-vous proposés bout à bout, sans battement.', 'lion-rdv-devis' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="notification_email"><?php esc_html_e( 'Email de notification interne', 'lion-rdv-devis' ); ?></label></th>
				<td><input type="email" id="notification_email" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" class="regular-text" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Horaires d\'ouverture', 'lion-rdv-devis' ); ?></h2>
		<table class="widefat" style="max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Jour', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Ouvert', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Matin début', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Matin fin', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Après-midi début', 'lion-rdv-devis' ); ?></th>
					<th><?php esc_html_e( 'Après-midi fin', 'lion-rdv-devis' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $day_labels as $day_key => $day_label ) : $day = $settings['hours'][ $day_key ]; ?>
				<tr>
					<td><?php echo esc_html( $day_label ); ?></td>
					<td><input type="checkbox" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[hours][<?php echo esc_attr( $day_key ); ?>][open]" <?php checked( $day['open'] ); ?> /></td>
					<td><input type="time" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[hours][<?php echo esc_attr( $day_key ); ?>][matin_debut]" value="<?php echo esc_attr( $day['matin_debut'] ); ?>" /></td>
					<td><input type="time" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[hours][<?php echo esc_attr( $day_key ); ?>][matin_fin]" value="<?php echo esc_attr( $day['matin_fin'] ); ?>" /></td>
					<td><input type="time" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[hours][<?php echo esc_attr( $day_key ); ?>][apres_midi_debut]" value="<?php echo esc_attr( $day['apres_midi_debut'] ); ?>" /></td>
					<td><input type="time" name="<?php echo esc_attr( Lion_RDV_Devis_Settings::OPTION_KEY ); ?>[hours][<?php echo esc_attr( $day_key ); ?>][apres_midi_fin]" value="<?php echo esc_attr( $day['apres_midi_fin'] ); ?>" /></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Laissez les champs après-midi vides pour une journée continue ou une demi-journée.', 'lion-rdv-devis' ); ?></p>

		<?php submit_button( __( 'Enregistrer les réglages', 'lion-rdv-devis' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Agendas connectés', 'lion-rdv-devis' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Romain et Emmanuel doivent chacun cliquer sur "Connecter" et autoriser l\'accès à leur propre agenda Google Calendar avec leur compte respectif.', 'lion-rdv-devis' ); ?></p>
	<table class="widefat" style="max-width:700px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Agenda', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Statut', 'lion-rdv-devis' ); ?></th>
				<th><?php esc_html_e( 'Action', 'lion-rdv-devis' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $persons_labels as $person_key => $person_label ) : $connected = Lion_RDV_Devis_Settings::is_person_connected( $person_key ); ?>
			<tr>
				<td><?php echo esc_html( $person_label ); ?></td>
				<td>
					<?php if ( $connected ) : ?>
						<span style="color:#1a7f37;">✓ <?php esc_html_e( 'Connecté', 'lion-rdv-devis' ); ?></span>
						<?php if ( ! empty( $settings['persons'][ $person_key ]['connected_email'] ) ) : ?>
							(<?php echo esc_html( $settings['persons'][ $person_key ]['connected_email'] ); ?>)
						<?php endif; ?>
					<?php else : ?>
						<span style="color:#996800;">– <?php esc_html_e( 'Non connecté', 'lion-rdv-devis' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $connected ) : ?>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lion_rdv_devis_oauth_disconnect&person=' . $person_key ), 'lion_rdv_devis_oauth_disconnect' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Déconnecter cet agenda ?', 'lion-rdv-devis' ) ); ?>');">
							<?php esc_html_e( 'Déconnecter', 'lion-rdv-devis' ); ?>
						</a>
					<?php else : ?>
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lion_rdv_devis_oauth_connect&person=' . $person_key ), 'lion_rdv_devis_oauth_connect' ) ); ?>">
							<?php esc_html_e( 'Connecter', 'lion-rdv-devis' ); ?>
						</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
