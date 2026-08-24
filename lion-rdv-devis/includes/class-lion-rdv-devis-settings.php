<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page de réglages (Réglages > Prise de RDV Devis Lion) et accès centralisé
 * aux options, y compris la connexion OAuth2 des agendas Google Calendar de
 * Romain et Emmanuel.
 */
class Lion_RDV_Devis_Settings {

	const OPTION_KEY = 'lion_rdv_devis_settings';

	public static $days = array(
		'lundi'    => 1,
		'mardi'    => 2,
		'mercredi' => 3,
		'jeudi'    => 4,
		'vendredi' => 5,
		'samedi'   => 6,
		'dimanche' => 7,
	);

	/**
	 * Les deux agendas Google Calendar interrogés par le plugin. La clé est
	 * utilisée partout dans le code (routage des services, stockage des
	 * jetons OAuth, assignation d'un créneau) ; le libellé n'est là que pour
	 * l'affichage dans l'administration.
	 */
	public static function get_persons_labels() {
		return array(
			'romain'   => __( 'Romain Bonnevie (gérant)', 'lion-rdv-devis' ),
			'emmanuel' => __( 'Emmanuel Bonnevie (co-directeur)', 'lion-rdv-devis' ),
		);
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_lion_rdv_devis_oauth_connect', array( $this, 'handle_oauth_connect' ) );
		add_action( 'admin_post_lion_rdv_devis_oauth_callback', array( $this, 'handle_oauth_callback' ) );
		add_action( 'admin_post_lion_rdv_devis_oauth_disconnect', array( $this, 'handle_oauth_disconnect' ) );
		add_action( 'admin_post_lion_rdv_devis_test_calendar', array( $this, 'handle_test_calendar' ) );
	}

	public static function default_settings() {
		$default_hours = array();
		foreach ( array_keys( self::$days ) as $day ) {
			$is_open       = ! in_array( $day, array( 'samedi', 'dimanche' ), true );
			$is_vendredi   = 'vendredi' === $day;
			$default_hours[ $day ] = array(
				'open'             => $is_open,
				'matin_debut'      => $is_open ? '08:00' : '',
				'matin_fin'        => $is_open ? '12:00' : '',
				'apres_midi_debut' => $is_open ? '14:00' : '',
				'apres_midi_fin'   => $is_open ? ( $is_vendredi ? '17:00' : '18:00' ) : '',
			);
		}

		$default_persons = array();
		foreach ( array_keys( self::get_persons_labels() ) as $key ) {
			$default_persons[ $key ] = array(
				'access_token'     => '',
				'refresh_token'    => '',
				'token_expires_at' => 0,
				'connected_email'  => '',
				// Agenda Google Calendar réellement interrogé/utilisé pour cette
				// personne : "primary" (son agenda par défaut) sauf si un agenda
				// secondaire est renseigné ici (ex. un agenda partagé
				// "...@group.calendar.google.com"). Voir
				// Lion_RDV_Devis_Google_Client::get_calendar_id().
				'calendar_id'      => 'primary',
			);
		}

		return array(
			'google_client_id'     => '',
			'google_client_secret' => '',
			'horizon_days'         => 30,
			'slot_step_minutes'    => 30,
			'notification_email'   => get_option( 'admin_email' ),
			'hours'                => $default_hours,
			'services'             => self::default_services(),
			'persons'              => $default_persons,
		);
	}

	/**
	 * Questions communes à tous les types de projet (budget, délai), posées
	 * en plus des questions spécifiques au type choisi. Jamais obligatoires
	 * (comme sur le formulaire de contact du site) pour ne pas ajouter de
	 * friction avant la prise de rendez-vous.
	 *
	 * @return array Liste de questions, voir le format documenté sur
	 *               default_services() (clé 'questions').
	 */
	public static function common_questions() {
		return array(
			array(
				'key'      => 'budget',
				'label'    => __( 'Budget estimatif', 'lion-rdv-devis' ),
				'type'     => 'select',
				'required' => false,
				'options'  => array(
					array(
						'value' => '',
						'label' => __( 'Non défini', 'lion-rdv-devis' ),
					),
					array(
						'value' => 'moins_5k',
						'label' => __( 'Moins de 5 000 €', 'lion-rdv-devis' ),
					),
					array(
						'value' => '5k_15k',
						'label' => __( '5 000 - 15 000 €', 'lion-rdv-devis' ),
					),
					array(
						'value' => '15k_30k',
						'label' => __( '15 000 - 30 000 €', 'lion-rdv-devis' ),
					),
					array(
						'value' => '30k_50k',
						'label' => __( '30 000 - 50 000 €', 'lion-rdv-devis' ),
					),
					array(
						'value' => 'plus_50k',
						'label' => __( 'Plus de 50 000 €', 'lion-rdv-devis' ),
					),
				),
			),
			array(
				'key'      => 'delai',
				'label'    => __( 'Délai souhaité', 'lion-rdv-devis' ),
				'type'     => 'select',
				'required' => false,
				'options'  => array(
					array(
						'value' => '',
						'label' => __( 'Non défini', 'lion-rdv-devis' ),
					),
					array(
						'value' => 'asap',
						'label' => __( 'Dès que possible', 'lion-rdv-devis' ),
					),
					array(
						'value' => '3_mois',
						'label' => __( 'Dans les 3 mois', 'lion-rdv-devis' ),
					),
					array(
						'value' => '6_mois',
						'label' => __( 'Dans les 6 mois', 'lion-rdv-devis' ),
					),
					array(
						'value' => 'plus_6_mois',
						'label' => __( 'Plus de 6 mois', 'lion-rdv-devis' ),
					),
					array(
						'value' => 'renseigne',
						'label' => __( 'Je me renseigne pour l\'instant', 'lion-rdv-devis' ),
					),
				),
			),
		);
	}

	/**
	 * Types de projet proposés dans le widget, chacun associé à la ou aux
	 * personnes dont l'agenda Google Calendar doit être consulté :
	 *
	 * - un seul agenda ('romain' ou 'emmanuel') : seul son planning compte.
	 * - les deux ('romain' + 'emmanuel') : le plugin propose le premier
	 *   créneau où AU MOINS L'UN DES DEUX est libre (voir
	 *   Lion_RDV_Devis_Availability), sans jamais indiquer au client lequel
	 *   des deux sera présent.
	 *
	 * Pour ajouter un nouveau type de projet, ajoutez une entrée ici avec une
	 * clé unique ; elle apparaîtra automatiquement dans les réglages et le
	 * widget.
	 *
	 * 'questions' : questions "entonnoir" spécifiques à ce type de projet,
	 * posées à l'étape "Vos coordonnées" en plus des questions communes
	 * (voir common_questions()), pour dégrossir le dossier du client avant
	 * la visite technique. Chaque question a la forme :
	 * array(
	 *   'key'      => identifiant unique (clé de stockage/affichage),
	 *   'label'    => libellé affiché,
	 *   'type'     => 'text' | 'number' | 'textarea' | 'select' | 'radio',
	 *   'required' => bool (aucune ne l'est par défaut, pour rester aussi
	 *                 simple que le formulaire de contact existant du site),
	 *   'full'     => bool optionnel, la question prend toute la largeur de
	 *                 la grille (par défaut : une demi-largeur),
	 *   'options'  => pour 'select'/'radio' uniquement, liste de
	 *                 array('value' => ..., 'label' => ...).
	 * )
	 * Non éditable depuis l'administration WordPress pour l'instant :
	 * modifiez cette liste directement dans le code (ou demandez à Claude).
	 */
	public static function default_services() {
		return array(
			'renovation_energetique' => array(
				'label'            => __( 'Rénovation énergétique', 'lion-rdv-devis' ),
				'description'      => __( 'Isolation, menuiseries, chauffage performant...', 'lion-rdv-devis' ),
				'duration_minutes' => 60,
				'lead_time_hours'  => 24,
				'persons'          => array( 'romain' ),
				'questions'        => array(
					array(
						'key'      => 'surface',
						'label'    => __( 'Surface habitable (m²)', 'lion-rdv-devis' ),
						'type'     => 'number',
						'required' => false,
					),
					array(
						'key'      => 'annee_construction',
						'label'    => __( 'Année de construction du logement', 'lion-rdv-devis' ),
						'type'     => 'select',
						'required' => false,
						'options'  => array(
							array( 'value' => '', 'label' => __( 'Non défini', 'lion-rdv-devis' ) ),
							array( 'value' => 'avant_1975', 'label' => __( 'Avant 1975', 'lion-rdv-devis' ) ),
							array( 'value' => '1975_2000', 'label' => __( '1975 - 2000', 'lion-rdv-devis' ) ),
							array( 'value' => '2000_2012', 'label' => __( '2000 - 2012', 'lion-rdv-devis' ) ),
							array( 'value' => 'apres_2012', 'label' => __( 'Après 2012', 'lion-rdv-devis' ) ),
						),
					),
					array(
						'key'      => 'chauffage_actuel',
						'label'    => __( 'Chauffage actuel', 'lion-rdv-devis' ),
						'type'     => 'select',
						'required' => false,
						'options'  => array(
							array( 'value' => '', 'label' => __( 'Non défini', 'lion-rdv-devis' ) ),
							array( 'value' => 'gaz', 'label' => __( 'Chaudière gaz', 'lion-rdv-devis' ) ),
							array( 'value' => 'fioul', 'label' => __( 'Chaudière fioul', 'lion-rdv-devis' ) ),
							array( 'value' => 'electrique', 'label' => __( 'Chauffage électrique', 'lion-rdv-devis' ) ),
							array( 'value' => 'pac', 'label' => __( 'Pompe à chaleur', 'lion-rdv-devis' ) ),
							array( 'value' => 'autre', 'label' => __( 'Autre / je ne sais pas', 'lion-rdv-devis' ) ),
						),
					),
					array(
						'key'      => 'aides',
						'label'    => __( 'Souhaitez-vous être accompagné pour les aides (MaPrimeRénov\', CEE, Éco-PTZ) ?', 'lion-rdv-devis' ),
						'type'     => 'radio',
						'required' => false,
						'full'     => true,
						'options'  => array(
							array( 'value' => 'oui', 'label' => __( 'Oui', 'lion-rdv-devis' ) ),
							array( 'value' => 'non', 'label' => __( 'Non', 'lion-rdv-devis' ) ),
						),
					),
				),
			),
			'cuisine'                => array(
				'label'            => __( 'Cuisine', 'lion-rdv-devis' ),
				'description'      => __( 'Conception et rénovation de cuisine', 'lion-rdv-devis' ),
				'duration_minutes' => 60,
				'lead_time_hours'  => 24,
				'persons'          => array( 'romain' ),
				'questions'        => array(
					array(
						'key'      => 'surface_cuisine',
						'label'    => __( 'Surface de la cuisine (m²)', 'lion-rdv-devis' ),
						'type'     => 'number',
						'required' => false,
					),
					array(
						'key'      => 'configuration',
						'label'    => __( 'Configuration souhaitée', 'lion-rdv-devis' ),
						'type'     => 'select',
						'required' => false,
						'options'  => array(
							array( 'value' => '', 'label' => __( 'Non défini', 'lion-rdv-devis' ) ),
							array( 'value' => 'fermee', 'label' => __( 'Cuisine fermée', 'lion-rdv-devis' ) ),
							array( 'value' => 'ouverte', 'label' => __( 'Cuisine ouverte / à ouvrir', 'lion-rdv-devis' ) ),
							array( 'value' => 'ne_sais_pas', 'label' => __( 'Je ne sais pas encore', 'lion-rdv-devis' ) ),
						),
					),
					array(
						'key'      => 'ampleur',
						'label'    => __( 'Ampleur des travaux', 'lion-rdv-devis' ),
						'type'     => 'select',
						'required' => false,
						'options'  => array(
							array( 'value' => '', 'label' => __( 'Non défini', 'lion-rdv-devis' ) ),
							array( 'value' => 'travaux_uniquement', 'label' => __( 'Travaux uniquement', 'lion-rdv-devis' ) ),
							array( 'value' => 'amenagement_et_travaux', 'label' => __( 'Aménagement de cuisine et travaux', 'lion-rdv-devis' ) ),
							array( 'value' => 'mobilier_uniquement', 'label' => __( 'Mobilier de cuisine uniquement', 'lion-rdv-devis' ) ),
						),
					),
				),
			),
			'renovation_complete'    => array(
				'label'            => __( 'Rénovation complète', 'lion-rdv-devis' ),
				'description'      => __( 'Rénovation totale d\'un logement', 'lion-rdv-devis' ),
				'duration_minutes' => 60,
				'lead_time_hours'  => 24,
				'persons'          => array( 'romain' ),
				'questions'        => array(
					array(
						'key'      => 'surface',
						'label'    => __( 'Surface totale du bien (m²)', 'lion-rdv-devis' ),
						'type'     => 'number',
						'required' => false,
					),
					array(
						'key'      => 'nb_pieces',
						'label'    => __( 'Nombre de pièces concernées', 'lion-rdv-devis' ),
						'type'     => 'number',
						'required' => false,
					),
					array(
						'key'      => 'ampleur',
						'label'    => __( 'Ampleur du projet', 'lion-rdv-devis' ),
						'type'     => 'select',
						'required' => false,
						'options'  => array(
							array( 'value' => '', 'label' => __( 'Non défini', 'lion-rdv-devis' ) ),
							array( 'value' => 'complete', 'label' => __( 'Rénovation complète (tous corps d\'état)', 'lion-rdv-devis' ) ),
							array( 'value' => 'partielle', 'label' => __( 'Rénovation partielle de plusieurs pièces', 'lion-rdv-devis' ) ),
						),
					),
					array(
						'key'      => 'statut_propriete',
						'label'    => __( 'Êtes-vous propriétaire ou en phase d\'acquisition ?', 'lion-rdv-devis' ),
						'type'     => 'radio',
						'required' => false,
						'full'     => true,
						'options'  => array(
							array( 'value' => 'proprietaire', 'label' => __( 'Propriétaire', 'lion-rdv-devis' ) ),
							array( 'value' => 'en_acquisition', 'label' => __( 'En phase d\'acquisition', 'lion-rdv-devis' ) ),
						),
					),
				),
			),
			'locaux_professionnels'  => array(
				'label'            => __( 'Locaux professionnels', 'lion-rdv-devis' ),
				'description'      => __( 'Aménagement ou rénovation de locaux professionnels', 'lion-rdv-devis' ),
				'duration_minutes' => 60,
				'lead_time_hours'  => 24,
				'persons'          => array( 'romain' ),
				'questions'        => array(
					array(
						'key'      => 'type_activite',
						'label'    => __( 'Type d\'activité', 'lion-rdv-devis' ),
						'type'     => 'text',
						'required' => false,
					),
					array(
						'key'      => 'surface',
						'label'    => __( 'Surface des locaux (m²)', 'lion-rdv-devis' ),
						'type'     => 'number',
						'required' => false,
					),
					array(
						'key'      => 'ouverture_souhaitee',
						'label'    => __( 'Date d\'ouverture / de remise en service souhaitée', 'lion-rdv-devis' ),
						'type'     => 'text',
						'required' => false,
					),
				),
			),
			'chauffage_climatisation' => array(
				'label'            => __( 'Chauffage / Climatisation', 'lion-rdv-devis' ),
				'description'      => __( 'Chauffage, chaudière, climatisation ou pompe à chaleur (PAC)', 'lion-rdv-devis' ),
				'duration_minutes' => 60,
				'lead_time_hours'  => 24,
				'persons'          => array( 'emmanuel' ),
				'questions'        => array(
					array(
						'key'      => 'type_besoin',
						'label'    => __( 'Nature du besoin', 'lion-rdv-devis' ),
						'type'     => 'select',
						'required' => false,
						'options'  => array(
							array( 'value' => '', 'label' => __( 'Non défini', 'lion-rdv-devis' ) ),
							array( 'value' => 'neuf', 'label' => __( 'Installation neuve', 'lion-rdv-devis' ) ),
							array( 'value' => 'remplacement', 'label' => __( 'Remplacement d\'un équipement existant', 'lion-rdv-devis' ) ),
						),
					),
					array(
						'key'      => 'type_equipement',
						'label'    => __( 'Type d\'équipement concerné', 'lion-rdv-devis' ),
						'type'     => 'select',
						'required' => false,
						'options'  => array(
							array( 'value' => '', 'label' => __( 'Non défini', 'lion-rdv-devis' ) ),
							array( 'value' => 'chaudiere_gaz', 'label' => __( 'Chaudière gaz', 'lion-rdv-devis' ) ),
							array( 'value' => 'chaudiere_fioul', 'label' => __( 'Chaudière fioul', 'lion-rdv-devis' ) ),
							array( 'value' => 'electrique', 'label' => __( 'Chauffage électrique', 'lion-rdv-devis' ) ),
							array( 'value' => 'pac', 'label' => __( 'Pompe à chaleur', 'lion-rdv-devis' ) ),
							array( 'value' => 'clim', 'label' => __( 'Climatisation réversible', 'lion-rdv-devis' ) ),
							array( 'value' => 'ne_sais_pas', 'label' => __( 'Je ne sais pas / à conseiller', 'lion-rdv-devis' ) ),
						),
					),
					array(
						'key'      => 'nb_pieces',
						'label'    => __( 'Nombre de pièces à équiper', 'lion-rdv-devis' ),
						'type'     => 'number',
						'required' => false,
					),
				),
			),
			'salle_de_bain'          => array(
				'label'            => __( 'Salle de bain', 'lion-rdv-devis' ),
				'description'      => __( 'Conception et rénovation de salle de bain', 'lion-rdv-devis' ),
				'duration_minutes' => 60,
				'lead_time_hours'  => 24,
				'persons'          => array( 'romain', 'emmanuel' ),
				'questions'        => array(
					array(
						'key'      => 'surface_sdb',
						'label'    => __( 'Surface de la salle de bain (m²)', 'lion-rdv-devis' ),
						'type'     => 'number',
						'required' => false,
					),
					array(
						'key'      => 'douche_baignoire',
						'label'    => __( 'Douche ou baignoire ?', 'lion-rdv-devis' ),
						'type'     => 'select',
						'required' => false,
						'options'  => array(
							array( 'value' => '', 'label' => __( 'Non défini', 'lion-rdv-devis' ) ),
							array( 'value' => 'douche', 'label' => __( 'Douche à l\'italienne', 'lion-rdv-devis' ) ),
							array( 'value' => 'baignoire', 'label' => __( 'Baignoire', 'lion-rdv-devis' ) ),
							array( 'value' => 'les_deux', 'label' => __( 'Les deux', 'lion-rdv-devis' ) ),
							array( 'value' => 'ne_sais_pas', 'label' => __( 'Je ne sais pas encore', 'lion-rdv-devis' ) ),
						),
					),
					array(
						'key'      => 'pmr',
						'label'    => __( 'Accès PMR (personne à mobilité réduite) souhaité ?', 'lion-rdv-devis' ),
						'type'     => 'radio',
						'required' => false,
						'full'     => true,
						'options'  => array(
							array( 'value' => 'oui', 'label' => __( 'Oui', 'lion-rdv-devis' ) ),
							array( 'value' => 'non', 'label' => __( 'Non', 'lion-rdv-devis' ) ),
						),
					),
				),
			),
		);
	}

	/**
	 * Questions à poser pour un type de projet donné : questions communes
	 * (budget, délai) suivies des questions spécifiques à ce service.
	 *
	 * @return array
	 */
	public static function get_questions_for_service( $service_type ) {
		$settings = self::get_settings();
		$service  = $settings['services'][ $service_type ] ?? null;

		if ( ! $service ) {
			return array();
		}

		$specific = is_array( $service['questions'] ?? null ) ? $service['questions'] : array();

		return array_merge( self::common_questions(), $specific );
	}

	public static function get_settings() {
		$settings = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( $settings, self::default_settings() );

		// Fusion clé par clé plutôt qu'un simple wp_parse_args (superficiel) :
		// si un nouveau service/personne est ajouté au code après que
		// l'utilisateur ait déjà enregistré ses réglages, il apparaît quand
		// même avec ses valeurs par défaut au lieu d'être silencieusement absent.
		$settings['services'] = self::merge_defaults( $settings['services'] ?? null, self::default_services() );
		$settings['persons']  = self::merge_defaults( $settings['persons'] ?? null, self::default_settings()['persons'] );

		return $settings;
	}

	private static function merge_defaults( $saved, array $defaults ) {
		$saved  = is_array( $saved ) ? $saved : array();
		$merged = array();
		foreach ( $defaults as $key => $default_value ) {
			$merged[ $key ] = isset( $saved[ $key ] ) && is_array( $saved[ $key ] )
				? wp_parse_args( $saved[ $key ], $default_value )
				: $default_value;
		}
		return $merged;
	}

	/**
	 * Chiffrement au repos des jetons OAuth (access_token / refresh_token)
	 * avec une clé dérivée des sels WordPress (AUTH_KEY), propres à cette
	 * installation. Ne protège pas contre un accès direct à la base de
	 * données ET au fichier wp-config.php simultanément, mais évite qu'un
	 * simple export de la table wp_options suffise à voler les jetons.
	 */
	public static function encrypt_secret( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}

		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'aes-256-cbc', self::encryption_key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return '';
		}

		return 'enc:' . base64_encode( $iv . $cipher );
	}

	public static function decrypt_secret( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored || 0 !== strpos( $stored, 'enc:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, 4 ), true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}

		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', self::encryption_key(), OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	private static function encryption_key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Enregistre les jetons obtenus pour une personne suite au flux OAuth
	 * (ou après un rafraîchissement de l'access_token). Écrit directement en
	 * base, indépendamment du formulaire de réglages classique.
	 */
	public static function save_person_tokens( $person, array $tokens ) {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( ! isset( $settings['persons'] ) || ! is_array( $settings['persons'] ) ) {
			$settings['persons'] = array();
		}

		$current = $settings['persons'][ $person ] ?? array();

		if ( array_key_exists( 'access_token', $tokens ) ) {
			$current['access_token'] = self::encrypt_secret( $tokens['access_token'] );
		}
		if ( array_key_exists( 'refresh_token', $tokens ) ) {
			// La clé n'est incluse que lors d'un échange de code initial (connexion)
			// ou d'une déconnexion explicite (clear_person_tokens) : le simple
			// rafraîchissement d'un access_token n'inclut pas cette clé et ne
			// touche donc jamais au refresh_token existant.
			$current['refresh_token'] = self::encrypt_secret( $tokens['refresh_token'] );
		}
		if ( array_key_exists( 'token_expires_at', $tokens ) ) {
			$current['token_expires_at'] = (int) $tokens['token_expires_at'];
		}
		if ( array_key_exists( 'connected_email', $tokens ) ) {
			$current['connected_email'] = sanitize_email( $tokens['connected_email'] );
		}

		$settings['persons'][ $person ] = $current;

		update_option( self::OPTION_KEY, $settings );
	}

	public static function clear_person_tokens( $person ) {
		self::save_person_tokens(
			$person,
			array(
				'access_token'     => '',
				'refresh_token'    => '',
				'token_expires_at' => 0,
				'connected_email'  => '',
			)
		);
	}

	public static function is_person_connected( $person ) {
		$settings = self::get_settings();
		return ! empty( $settings['persons'][ $person ]['refresh_token'] );
	}

	public function add_menu() {
		add_options_page(
			__( 'Prise de RDV Devis Lion', 'lion-rdv-devis' ),
			__( 'Prise de RDV Devis Lion', 'lion-rdv-devis' ),
			'manage_options',
			'lion-rdv-devis',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			null,
			__( 'Réservations - Prise de RDV Devis Lion', 'lion-rdv-devis' ),
			__( 'Réservations Devis', 'lion-rdv-devis' ),
			'manage_options',
			'lion-rdv-devis-bookings',
			array( $this, 'render_bookings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'lion_rdv_devis_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$defaults = self::default_settings();
		$existing = get_option( self::OPTION_KEY, array() );
		$clean    = array();

		$clean['google_client_id'] = isset( $input['google_client_id'] ) ? sanitize_text_field( $input['google_client_id'] ) : '';

		// Champ affiché en clair dans le formulaire (comme la clé API InterFast
		// de l'autre plugin) : chiffré uniquement au repos en base, pas dans
		// le formulaire d'administration lui-même.
		$clean['google_client_secret'] = isset( $input['google_client_secret'] ) ? sanitize_text_field( $input['google_client_secret'] ) : '';

		$clean['horizon_days']      = max( 1, min( 180, (int) ( $input['horizon_days'] ?? $defaults['horizon_days'] ) ) );
		$clean['slot_step_minutes'] = max( 0, (int) ( $input['slot_step_minutes'] ?? $defaults['slot_step_minutes'] ) );

		$email                       = isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : '';
		$clean['notification_email'] = $email ? $email : $defaults['notification_email'];

		$valid_persons = array_keys( self::get_persons_labels() );

		$clean['services'] = array();
		foreach ( $defaults['services'] as $key => $default_service ) {
			$service_input = $input['services'][ $key ] ?? array();

			$persons_input = isset( $service_input['persons'] ) && is_array( $service_input['persons'] )
				? array_values( array_intersect( $valid_persons, $service_input['persons'] ) )
				: array();

			$clean['services'][ $key ] = array(
				'label'            => isset( $service_input['label'] ) && '' !== trim( $service_input['label'] ) ? sanitize_text_field( $service_input['label'] ) : $default_service['label'],
				'description'      => isset( $service_input['description'] ) ? sanitize_text_field( $service_input['description'] ) : $default_service['description'],
				'duration_minutes' => max( 15, (int) ( $service_input['duration_minutes'] ?? $default_service['duration_minutes'] ) ),
				'lead_time_hours'  => max( 0, (int) ( $service_input['lead_time_hours'] ?? $default_service['lead_time_hours'] ) ),
				// Si aucune personne n'est cochée, on retombe sur la config
				// par défaut plutôt que de créer un service sans agenda cible.
				'persons'          => ! empty( $persons_input ) ? $persons_input : $default_service['persons'],
			);
		}

		$clean['hours'] = array();
		foreach ( array_keys( self::$days ) as $day ) {
			$day_input = $input['hours'][ $day ] ?? array();
			$clean['hours'][ $day ] = array(
				'open'             => ! empty( $day_input['open'] ),
				'matin_debut'      => $this->sanitize_time( $day_input['matin_debut'] ?? '' ),
				'matin_fin'        => $this->sanitize_time( $day_input['matin_fin'] ?? '' ),
				'apres_midi_debut' => $this->sanitize_time( $day_input['apres_midi_debut'] ?? '' ),
				'apres_midi_fin'   => $this->sanitize_time( $day_input['apres_midi_fin'] ?? '' ),
			);
		}

		// Les jetons OAuth ne transitent jamais par CE FORMULAIRE (aucun champ
		// "persons" n'y est rendu) : dans ce cas $input['persons'] est absent et
		// on préserve ce qui est déjà enregistré. Mais cette même fonction est
		// aussi invoquée quand save_person_tokens() appelle update_option() —
		// WordPress fait passer TOUTE valeur par sanitize_option() avant de
		// l'écrire, donc $input['persons'] contient alors les jetons qu'on est
		// justement en train d'essayer d'enregistrer. Les ignorer au profit
		// d'un nouveau get_option() ici serait relire l'ANCIENNE valeur en base
		// (update_option() n'a pas encore écrit la nouvelle au moment où ce
		// filtre s'exécute) et effacerait silencieusement le jeton qu'on vient
		// de recevoir de Google : on doit donc bien repartir de $input quand il
		// est présent.
		$existing_persons = is_array( $existing['persons'] ?? null ) ? $existing['persons'] : array();
		$clean['persons']  = $this->sanitize_persons( $input['persons'] ?? null, $existing_persons );

		return $clean;
	}

	/**
	 * @param mixed $input_persons    $input['persons'] tel que reçu par sanitize_settings() (peut être absent).
	 * @param array $existing_persons Valeur actuellement enregistrée en base, pour combler ce que $input_persons ne fournit pas.
	 */
	private function sanitize_persons( $input_persons, array $existing_persons ) {
		$clean = array();

		foreach ( array_keys( self::get_persons_labels() ) as $person_key ) {
			$current = is_array( $existing_persons[ $person_key ] ?? null ) ? $existing_persons[ $person_key ] : array();
			$incoming = is_array( $input_persons[ $person_key ] ?? null ) ? $input_persons[ $person_key ] : null;

			if ( null === $incoming ) {
				$clean[ $person_key ] = $current;
				continue;
			}

			// access_token/refresh_token sont déjà chiffrés à ce stade (voir
			// save_person_tokens()) : de simples chaînes opaques, castées mais
			// jamais ré-échappées comme du texte affichable. calendar_id, lui,
			// vient du formulaire de réglages classique (champ "ID de l'agenda
			// Google"), soumis en texte brut.
			$calendar_id = isset( $incoming['calendar_id'] ) ? trim( sanitize_text_field( $incoming['calendar_id'] ) ) : ( $current['calendar_id'] ?? 'primary' );

			$clean[ $person_key ] = array(
				'access_token'     => isset( $incoming['access_token'] ) ? (string) $incoming['access_token'] : ( $current['access_token'] ?? '' ),
				'refresh_token'    => isset( $incoming['refresh_token'] ) ? (string) $incoming['refresh_token'] : ( $current['refresh_token'] ?? '' ),
				'token_expires_at' => isset( $incoming['token_expires_at'] ) ? (int) $incoming['token_expires_at'] : (int) ( $current['token_expires_at'] ?? 0 ),
				'connected_email'  => isset( $incoming['connected_email'] ) ? sanitize_email( $incoming['connected_email'] ) : ( $current['connected_email'] ?? '' ),
				'calendar_id'      => '' !== $calendar_id ? $calendar_id : 'primary',
			);
		}

		return $clean;
	}

	private function sanitize_time( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	/**
	 * URL de redirection à déclarer dans les identifiants OAuth du projet
	 * Google Cloud (écran "Identifiants" > client OAuth "Application Web").
	 * Fixe et indépendante de la personne : la personne concernée est
	 * transmise via le paramètre `state`, vérifié dans handle_oauth_callback().
	 */
	public static function get_oauth_redirect_uri() {
		return admin_url( 'admin-post.php?action=lion_rdv_devis_oauth_callback' );
	}

	public function handle_oauth_connect() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lion_rdv_devis_oauth_connect' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'lion-rdv-devis' ) );
		}

		$person = isset( $_GET['person'] ) ? sanitize_key( wp_unslash( $_GET['person'] ) ) : '';
		if ( ! array_key_exists( $person, self::get_persons_labels() ) ) {
			wp_die( esc_html__( 'Agenda inconnu.', 'lion-rdv-devis' ) );
		}

		$client = new Lion_RDV_Devis_Google_Client();

		if ( ! $client->is_configured() ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'lion-rdv-devis', 'lion_rdv_devis_oauth_error' => rawurlencode( __( 'Renseignez d\'abord l\'ID client et le secret client Google avant de connecter un agenda.', 'lion-rdv-devis' ) ) ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$state = wp_json_encode(
			array(
				'person' => $person,
				'nonce'  => wp_create_nonce( 'lion_rdv_devis_oauth_state_' . $person ),
			)
		);

		// wp_safe_redirect() refuserait cette redirection : par défaut, elle
		// n'autorise que les URL du site lui-même et retomberait donc
		// silencieusement sur l'admin WordPress au lieu d'envoyer l'utilisateur
		// vers l'écran de consentement Google. L'URL cible est ici entièrement
		// construite côté serveur (endpoint Google fixe + client_id + state
		// signé), jamais à partir d'une entrée utilisateur : wp_redirect() est
		// donc le bon choix.
		wp_redirect( $client->get_authorize_url( $state ) );
		exit;
	}

	public function handle_oauth_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'lion-rdv-devis' ) );
		}

		$redirect_base = admin_url( 'options-general.php?page=lion-rdv-devis' );

		if ( ! empty( $_GET['error'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			wp_safe_redirect( add_query_arg( 'lion_rdv_devis_oauth_error', rawurlencode( $message ), $redirect_base ) );
			exit;
		}

		$state_raw = isset( $_GET['state'] ) ? json_decode( wp_unslash( $_GET['state'] ), true ) : null;
		$person    = is_array( $state_raw ) && isset( $state_raw['person'] ) ? sanitize_key( $state_raw['person'] ) : '';
		$nonce     = is_array( $state_raw ) ? ( $state_raw['nonce'] ?? '' ) : '';

		if ( ! array_key_exists( $person, self::get_persons_labels() ) || ! wp_verify_nonce( $nonce, 'lion_rdv_devis_oauth_state_' . $person ) ) {
			wp_die( esc_html__( 'Requête OAuth invalide ou expirée. Merci de relancer la connexion depuis les réglages.', 'lion-rdv-devis' ) );
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code ) {
			wp_safe_redirect( add_query_arg( 'lion_rdv_devis_oauth_error', rawurlencode( __( 'Autorisation Google refusée ou incomplète.', 'lion-rdv-devis' ) ), $redirect_base ) );
			exit;
		}

		$client = new Lion_RDV_Devis_Google_Client();
		$result = $client->exchange_code_for_tokens( $code );

		if ( ! $result['success'] ) {
			wp_safe_redirect( add_query_arg( 'lion_rdv_devis_oauth_error', rawurlencode( $result['error'] ), $redirect_base ) );
			exit;
		}

		self::save_person_tokens(
			$person,
			array(
				'access_token'     => $result['access_token'],
				'refresh_token'    => $result['refresh_token'],
				'token_expires_at' => $result['expires_at'],
				'connected_email'  => $result['email'],
			)
		);

		wp_safe_redirect( add_query_arg( 'lion_rdv_devis_oauth_connected', $person, $redirect_base ) );
		exit;
	}

	public function handle_oauth_disconnect() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lion_rdv_devis_oauth_disconnect' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'lion-rdv-devis' ) );
		}

		$person = isset( $_GET['person'] ) ? sanitize_key( wp_unslash( $_GET['person'] ) ) : '';
		if ( array_key_exists( $person, self::get_persons_labels() ) ) {
			self::clear_person_tokens( $person );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'lion-rdv-devis', 'lion_rdv_devis_oauth_disconnected' => $person ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Bouton "Vérifier l'agenda" : interroge le FreeBusy réel de la personne
	 * sur les 14 prochains jours et affiche le résultat brut sur la page de
	 * réglages, pour comparer avec le contenu effectif de son agenda Google
	 * et localiser la cause si un créneau occupé reste proposé dans le
	 * widget (mauvais compte connecté, agenda secondaire non couvert,
	 * événement marqué "Disponible" plutôt que "Occupé", etc.).
	 */
	public function handle_test_calendar() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'lion_rdv_devis_test_calendar' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'lion-rdv-devis' ) );
		}

		$person = isset( $_GET['person'] ) ? sanitize_key( wp_unslash( $_GET['person'] ) ) : '';
		if ( ! array_key_exists( $person, self::get_persons_labels() ) ) {
			wp_die( esc_html__( 'Agenda inconnu.', 'lion-rdv-devis' ) );
		}

		$client = new Lion_RDV_Devis_Google_Client();
		$result = $client->test_busy_periods( $person );

		set_transient( 'lion_rdv_devis_test_calendar_' . $person, $result, MINUTE_IN_SECONDS * 5 );

		wp_safe_redirect( add_query_arg( array( 'page' => 'lion-rdv-devis', 'lion_rdv_devis_calendar_tested' => $person ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public function render_settings_page() {
		require LION_RDV_DEVIS_PLUGIN_DIR . 'includes/views/settings-page.php';
	}

	public function render_bookings_page() {
		require LION_RDV_DEVIS_PLUGIN_DIR . 'includes/views/bookings-page.php';
	}
}
