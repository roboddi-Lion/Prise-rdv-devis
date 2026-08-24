<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calcule les créneaux encore libres en combinant les horaires d'ouverture
 * configurés dans le plugin et les événements déjà planifiés dans le ou les
 * agendas Google Calendar concernés par le type de projet choisi.
 */
class Lion_RDV_Devis_Availability {

	/** @var Lion_RDV_Devis_Google_Client */
	private $client;

	public function __construct( Lion_RDV_Devis_Google_Client $client = null ) {
		$this->client = $client ?: new Lion_RDV_Devis_Google_Client();
	}

	/**
	 * @param string $service_type Clé d'un service configuré dans Lion_RDV_Devis_Settings::default_services().
	 * @return array{success:bool,error:?string,days:array}
	 */
	public function get_available_slots( $service_type ) {
		$settings = Lion_RDV_Devis_Settings::get_settings();
		$tz       = wp_timezone();

		if ( ! isset( $settings['services'][ $service_type ] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Type de projet inconnu.', 'lion-rdv-devis' ),
				'days'    => array(),
			);
		}

		$service          = $settings['services'][ $service_type ];
		$duration_minutes = (int) $service['duration_minutes'];
		$lead_hours       = (int) $service['lead_time_hours'];
		$persons          = $service['persons'];
		$horizon_days     = (int) $settings['horizon_days'];
		$step_minutes     = (int) $settings['slot_step_minutes'];

		$now      = new DateTimeImmutable( 'now', $tz );
		$earliest = $now->modify( "+{$lead_hours} hours" );

		$range_start = $now->setTime( 0, 0, 0 );
		$range_end   = $range_start->modify( "+{$horizon_days} days" );

		$busy_by_person = array();
		foreach ( $persons as $person ) {
			$result = $this->client->get_busy_periods( $person, $range_start, $range_end );
			if ( ! $result['success'] ) {
				return array(
					'success' => false,
					'error'   => $result['error'],
					'days'    => array(),
				);
			}
			$busy_by_person[ $person ] = $result['periods'];
		}

		$days     = array();
		$day_keys = array_flip( Lion_RDV_Devis_Settings::$days );

		$cursor = $range_start;
		while ( $cursor < $range_end ) {
			$iso_weekday = (int) $cursor->format( 'N' );
			$day_key     = $day_keys[ $iso_weekday ] ?? null;
			$day_config  = $day_key ? $settings['hours'][ $day_key ] : null;

			if ( $day_config && $day_config['open'] ) {
				$windows = array();
				if ( ! empty( $day_config['matin_debut'] ) && ! empty( $day_config['matin_fin'] ) ) {
					$windows[] = array( $day_config['matin_debut'], $day_config['matin_fin'] );
				}
				if ( ! empty( $day_config['apres_midi_debut'] ) && ! empty( $day_config['apres_midi_fin'] ) ) {
					$windows[] = array( $day_config['apres_midi_debut'], $day_config['apres_midi_fin'] );
				}

				$day_slots = array();

				foreach ( $windows as $window ) {
					list( $window_start_time, $window_end_time ) = $window;

					$window_start = $this->combine_date_time( $cursor, $window_start_time, $tz );
					$window_end   = $this->combine_date_time( $cursor, $window_end_time, $tz );

					if ( $window_end <= $window_start ) {
						continue;
					}

					$slot_start = $window_start;
					while ( true ) {
						$slot_end = $slot_start->modify( "+{$duration_minutes} minutes" );

						if ( $slot_end > $window_end ) {
							break;
						}

						if ( $slot_start >= $earliest && $this->slot_is_available( $slot_start, $slot_end, $busy_by_person, $persons )['available'] ) {
							$day_slots[] = array(
								'start' => $slot_start->format( DateTimeInterface::ATOM ),
								'end'   => $slot_end->format( DateTimeInterface::ATOM ),
								'label' => $slot_start->format( 'H:i' ) . ' - ' . $slot_end->format( 'H:i' ),
							);
						}

						// L'intervalle s'ajoute APRÈS la fin du rendez-vous (battement),
						// jamais pendant : deux créneaux proposés ne se chevauchent
						// donc jamais, quelle que soit la durée du service.
						$slot_start = $slot_start->modify( '+' . ( $duration_minutes + $step_minutes ) . ' minutes' );
					}
				}

				if ( ! empty( $day_slots ) ) {
					$days[] = array(
						'date'  => $cursor->format( 'Y-m-d' ),
						// wp_date() (pas date_i18n()) : date_i18n() attend un timestamp
						// auquel le décalage horaire du site a déjà été ajouté, alors que
						// DateTimeImmutable::getTimestamp() renvoie un timestamp UTC brut.
						// Résultat avec date_i18n() : le libellé affiché tombait un jour
						// avant la vraie date (ex. un vrai lundi affiché "dimanche"),
						// alors que le créneau réservé restait sur la bonne date.
						'label' => wp_date( 'l j F', $cursor->getTimestamp(), $tz ),
						'slots' => $day_slots,
					);
				}
			}

			$cursor = $cursor->modify( '+1 day' );
		}

		return array(
			'success' => true,
			'error'   => null,
			'days'    => $days,
		);
	}

	/**
	 * Revérifie qu'un créneau précis est toujours libre juste avant la création
	 * de l'événement Google Calendar (limite le risque de double réservation),
	 * et détermine quelle personne libre lui assigner.
	 *
	 * @param string $service_type Clé du service réservé, pour appliquer sa propre liste de personnes éligibles.
	 * @return array{free:bool,error:?string,person:?string}
	 */
	public function is_slot_still_free( DateTimeImmutable $start, DateTimeImmutable $end, $service_type ) {
		$settings = Lion_RDV_Devis_Settings::get_settings();
		$service  = $settings['services'][ $service_type ] ?? null;

		if ( ! $service ) {
			return array(
				'free'   => false,
				'error'  => __( 'Type de projet inconnu.', 'lion-rdv-devis' ),
				'person' => null,
			);
		}

		$persons        = $service['persons'];
		$busy_by_person = array();

		foreach ( $persons as $person ) {
			$result = $this->client->get_busy_periods( $person, $start->modify( '-1 minute' ), $end->modify( '+1 minute' ) );
			if ( ! $result['success'] ) {
				return array(
					'free'   => false,
					'error'  => $result['error'],
					'person' => null,
				);
			}
			$busy_by_person[ $person ] = $result['periods'];
		}

		$availability = $this->slot_is_available( $start, $end, $busy_by_person, $persons );

		return array(
			'free'   => $availability['available'],
			'error'  => null,
			'person' => $availability['person'],
		);
	}

	/**
	 * Détermine si un créneau est disponible : libre si AU MOINS UNE des
	 * personnes éligibles pour ce service n'a aucun événement qui chevauche
	 * ce créneau dans son agenda Google Calendar ; cette personne est
	 * retournée pour lui assigner le rendez-vous. Si les deux personnes sont
	 * éligibles (ex. salle de bain), le client ne voit jamais laquelle sera
	 * effectivement présente.
	 *
	 * @return array{available:bool,person:?string}
	 */
	private function slot_is_available( DateTimeImmutable $start, DateTimeImmutable $end, array $busy_by_person, array $persons ) {
		foreach ( $persons as $person ) {
			$blocked = false;

			foreach ( $busy_by_person[ $person ] ?? array() as $busy ) {
				if ( $busy['start'] < $end && $busy['end'] > $start ) {
					$blocked = true;
					break;
				}
			}

			if ( ! $blocked ) {
				return array(
					'available' => true,
					'person'    => $person,
				);
			}
		}

		return array(
			'available' => false,
			'person'    => null,
		);
	}

	private function combine_date_time( DateTimeImmutable $day, $time_string, DateTimeZone $tz ) {
		list( $hours, $minutes ) = array_map( 'intval', explode( ':', $time_string ) );
		return $day->setTime( $hours, $minutes, 0 );
	}
}
