<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client OAuth2 + API Google Calendar.
 *
 * Chaque personne (Romain, Emmanuel) autorise individuellement l'accès à son
 * propre agenda ("primary") via le flux OAuth2 standard (voir
 * Lion_RDV_Devis_Settings::handle_oauth_connect/callback). Toutes les
 * requêtes (freeBusy comme création d'événement) sont donc effectuées avec
 * le jeton d'accès propre à la personne concernée, sur son calendrier
 * "primary" — pas besoin que Romain et Emmanuel partagent la visibilité de
 * leurs agendas entre eux.
 *
 * Prérequis côté Google Cloud Console :
 * - Un projet avec l'API "Google Calendar API" activée.
 * - Un identifiant OAuth "Application Web" (Identifiants > Créer des
 *   identifiants > ID client OAuth), avec comme URI de redirection
 *   autorisée exactement Lion_RDV_Devis_Settings::get_oauth_redirect_uri().
 */
class Lion_RDV_Devis_Google_Client {

	const AUTHORIZE_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_ENDPOINT     = 'https://oauth2.googleapis.com/token';
	const USERINFO_ENDPOINT  = 'https://www.googleapis.com/oauth2/v3/userinfo';
	const CALENDAR_API_BASE  = 'https://www.googleapis.com/calendar/v3';

	const SCOPES = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email openid';

	private $client_id;
	private $client_secret;

	public function __construct() {
		$settings            = Lion_RDV_Devis_Settings::get_settings();
		$this->client_id     = $settings['google_client_id'];
		$this->client_secret = $settings['google_client_secret'];
	}

	public function is_configured() {
		return ! empty( $this->client_id ) && ! empty( $this->client_secret );
	}

	public function get_authorize_url( $state ) {
		$params = array(
			'client_id'              => $this->client_id,
			'redirect_uri'           => Lion_RDV_Devis_Settings::get_oauth_redirect_uri(),
			'response_type'          => 'code',
			'scope'                  => self::SCOPES,
			'access_type'            => 'offline',
			// Force le renvoi d'un refresh_token même si la personne avait déjà
			// autorisé l'application par le passé (sinon Google ne le renvoie
			// qu'au tout premier consentement).
			'prompt'                 => 'consent',
			'include_granted_scopes' => 'true',
			'state'                  => $state,
		);

		return self::AUTHORIZE_ENDPOINT . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * @return array{success:bool,access_token:?string,refresh_token:?string,expires_at:?int,email:?string,error:?string}
	 */
	public function exchange_code_for_tokens( $code ) {
		$response = $this->token_request(
			array(
				'code'          => $code,
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'redirect_uri'  => Lion_RDV_Devis_Settings::get_oauth_redirect_uri(),
				'grant_type'    => 'authorization_code',
			)
		);

		if ( ! $response['success'] ) {
			return array(
				'success'       => false,
				'access_token'  => null,
				'refresh_token' => null,
				'expires_at'    => null,
				'email'         => null,
				'error'         => $response['error'],
			);
		}

		$data          = $response['data'];
		$access_token  = $data['access_token'] ?? '';
		$refresh_token = $data['refresh_token'] ?? '';
		$expires_at    = time() + (int) ( $data['expires_in'] ?? 3600 );

		if ( empty( $refresh_token ) ) {
			return array(
				'success'       => false,
				'access_token'  => null,
				'refresh_token' => null,
				'expires_at'    => null,
				'email'         => null,
				'error'         => __( 'Google n\'a pas renvoyé de jeton de rafraîchissement. Révoquez l\'accès de l\'application dans votre compte Google (myaccount.google.com/permissions) puis réessayez.', 'lion-rdv-devis' ),
			);
		}

		$email = $this->fetch_email( $access_token );

		return array(
			'success'       => true,
			'access_token'  => $access_token,
			'refresh_token' => $refresh_token,
			'expires_at'    => $expires_at,
			'email'         => $email,
			'error'         => null,
		);
	}

	private function fetch_email( $access_token ) {
		$response = wp_remote_get(
			self::USERINFO_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) && isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
	}

	/**
	 * Retourne un access_token valide pour la personne, en le rafraîchissant
	 * automatiquement auprès de Google s'il est expiré ou proche de l'expiration.
	 *
	 * @return array{success:bool,access_token:?string,error:?string}
	 */
	public function get_valid_access_token( $person ) {
		$settings = Lion_RDV_Devis_Settings::get_settings();
		$stored   = $settings['persons'][ $person ] ?? null;

		if ( ! $stored || empty( $stored['refresh_token'] ) ) {
			return array(
				'success'      => false,
				'access_token' => null,
				/* translators: %s: person label */
				'error'        => sprintf( __( 'L\'agenda Google de %s n\'est pas connecté. Rendez-vous dans Réglages > Prise de RDV Devis Lion pour l\'autoriser.', 'lion-rdv-devis' ), self::get_persons_labels()[ $person ] ?? $person ),
			);
		}

		$access_token  = Lion_RDV_Devis_Settings::decrypt_secret( $stored['access_token'] );
		$expires_at    = (int) $stored['token_expires_at'];
		$refresh_token = Lion_RDV_Devis_Settings::decrypt_secret( $stored['refresh_token'] );

		if ( $access_token && $expires_at > ( time() + 60 ) ) {
			return array(
				'success'      => true,
				'access_token' => $access_token,
				'error'        => null,
			);
		}

		if ( ! $this->is_configured() ) {
			return array(
				'success'      => false,
				'access_token' => null,
				'error'        => __( 'L\'ID client et le secret client Google ne sont pas configurés.', 'lion-rdv-devis' ),
			);
		}

		$response = $this->token_request(
			array(
				'refresh_token' => $refresh_token,
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'grant_type'    => 'refresh_token',
			)
		);

		if ( ! $response['success'] ) {
			return array(
				'success'      => false,
				'access_token' => null,
				'error'        => $response['error'],
			);
		}

		$data           = $response['data'];
		$new_access     = $data['access_token'] ?? '';
		$new_expires_at = time() + (int) ( $data['expires_in'] ?? 3600 );

		Lion_RDV_Devis_Settings::save_person_tokens(
			$person,
			array(
				'access_token'     => $new_access,
				'token_expires_at' => $new_expires_at,
			)
		);

		return array(
			'success'      => true,
			'access_token' => $new_access,
			'error'        => null,
		);
	}

	private static function get_persons_labels() {
		return Lion_RDV_Devis_Settings::get_persons_labels();
	}

	/**
	 * Interroge le calendrier "primary" de la personne (FreeBusy) sur la
	 * plage demandée.
	 *
	 * @return array{success:bool,periods:array,error:?string} periods = liste de
	 *         ['start' => DateTimeImmutable, 'end' => DateTimeImmutable]
	 */
	public function get_busy_periods( $person, DateTimeImmutable $start, DateTimeImmutable $end ) {
		$token_result = $this->get_valid_access_token( $person );
		if ( ! $token_result['success'] ) {
			return array(
				'success' => false,
				'periods' => array(),
				'error'   => $token_result['error'],
			);
		}

		$body = array(
			'timeMin' => $start->format( DateTimeInterface::ATOM ),
			'timeMax' => $end->format( DateTimeInterface::ATOM ),
			'items'   => array( array( 'id' => 'primary' ) ),
		);

		$response = $this->request( 'POST', self::CALENDAR_API_BASE . '/freeBusy', $token_result['access_token'], array(), $body );

		if ( ! $response['success'] ) {
			return array(
				'success' => false,
				'periods' => array(),
				'error'   => $response['error'],
			);
		}

		$raw   = $response['data']['calendars']['primary'] ?? array();
		$busy  = isset( $raw['busy'] ) && is_array( $raw['busy'] ) ? $raw['busy'] : array();
		$error = isset( $raw['errors'] ) && ! empty( $raw['errors'] );

		if ( $error ) {
			return array(
				'success' => false,
				'periods' => array(),
				/* translators: %s: person label */
				'error'   => sprintf( __( 'Impossible de lire l\'agenda Google de %s.', 'lion-rdv-devis' ), self::get_persons_labels()[ $person ] ?? $person ),
			);
		}

		$periods = array();
		foreach ( $busy as $item ) {
			if ( empty( $item['start'] ) || empty( $item['end'] ) ) {
				continue;
			}
			try {
				$periods[] = array(
					'start' => new DateTimeImmutable( $item['start'] ),
					'end'   => new DateTimeImmutable( $item['end'] ),
				);
			} catch ( Exception $e ) {
				continue;
			}
		}

		return array(
			'success' => true,
			'periods' => $periods,
			'error'   => null,
		);
	}

	/**
	 * Crée l'événement dans l'agenda "primary" de la personne assignée, avec
	 * le client en participant ("attendee") : Google envoie automatiquement
	 * une invitation par email au client (sendUpdates=all), sans action
	 * supplémentaire du plugin.
	 *
	 * @return array{success:bool,event_id:?string,event_link:?string,error:?string}
	 */
	public function create_event( $person, array $event_data ) {
		$token_result = $this->get_valid_access_token( $person );
		if ( ! $token_result['success'] ) {
			return array(
				'success'    => false,
				'event_id'   => null,
				'event_link' => null,
				'error'      => $token_result['error'],
			);
		}

		$tz = wp_timezone_string();

		$description_lines = array(
			sprintf( '%s : %s %s', __( 'Client', 'lion-rdv-devis' ), $event_data['first_name'], $event_data['last_name'] ),
			sprintf( '%s : %s', __( 'Téléphone', 'lion-rdv-devis' ), $event_data['phone'] ),
			sprintf( '%s : %s', __( 'Email', 'lion-rdv-devis' ), $event_data['email'] ),
			sprintf( '%s : %s, %s %s', __( 'Adresse', 'lion-rdv-devis' ), $event_data['address'], $event_data['postal_code'], $event_data['city'] ),
			sprintf( '%s : %s', __( 'Message', 'lion-rdv-devis' ), $event_data['message'] ? $event_data['message'] : '-' ),
		);

		$body = array(
			'summary'     => sprintf( '%s - %s %s', $event_data['service_label'], $event_data['first_name'], $event_data['last_name'] ),
			'description' => implode( "\n", $description_lines ),
			'location'    => $event_data['address'] . ', ' . $event_data['postal_code'] . ' ' . $event_data['city'],
			'start'       => array(
				'dateTime' => $event_data['start']->format( DateTimeInterface::ATOM ),
				'timeZone' => $tz,
			),
			'end'         => array(
				'dateTime' => $event_data['end']->format( DateTimeInterface::ATOM ),
				'timeZone' => $tz,
			),
			'attendees'   => array(
				array(
					'email'       => $event_data['email'],
					'displayName' => $event_data['first_name'] . ' ' . $event_data['last_name'],
				),
			),
			'reminders'   => array( 'useDefault' => true ),
		);

		$body = apply_filters( 'lion_rdv_devis_google_event_payload', $body, $event_data, $person );

		$response = $this->request(
			'POST',
			self::CALENDAR_API_BASE . '/calendars/primary/events',
			$token_result['access_token'],
			array( 'sendUpdates' => 'all' ),
			$body
		);

		if ( ! $response['success'] ) {
			return array(
				'success'    => false,
				'event_id'   => null,
				'event_link' => null,
				'error'      => $response['error'],
			);
		}

		$data = $response['data'];

		return array(
			'success'    => true,
			'event_id'   => isset( $data['id'] ) ? (string) $data['id'] : null,
			'event_link' => isset( $data['htmlLink'] ) ? (string) $data['htmlLink'] : null,
			'error'      => null,
		);
	}

	private function token_request( array $params ) {
		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout' => 15,
				'body'    => $params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['error_description'] ) ? $data['error_description'] : ( is_array( $data ) && isset( $data['error'] ) ? $data['error'] : 'HTTP ' . $code );
			return array(
				'success' => false,
				'data'    => $data,
				'error'   => sprintf( 'Google OAuth : %s', $message ),
			);
		}

		return array(
			'success' => true,
			'data'    => is_array( $data ) ? $data : array(),
			'error'   => null,
		);
	}

	private function request( $method, $url, $access_token, array $query = array(), array $body = null ) {
		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['error']['message'] ) ? $data['error']['message'] : $raw;
			return array(
				'success' => false,
				'data'    => $data,
				'error'   => sprintf( 'Google Calendar HTTP %d : %s', $code, $message ),
			);
		}

		return array(
			'success' => true,
			'data'    => is_array( $data ) ? $data : array(),
			'error'   => null,
		);
	}
}
