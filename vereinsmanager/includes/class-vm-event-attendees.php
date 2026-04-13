<?php
/**
 * Event-Teilnehmer + Einladungs-Batch.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Event_Attendees
 */
class VM_Event_Attendees {

	/**
	 * Tabellenname.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vm_event_attendees';
	}

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_event_invite', [ __CLASS__, 'handle_invite' ] );
		add_action( 'admin_post_vm_event_checkin', [ __CLASS__, 'handle_checkin' ] );
	}

	/**
	 * Token generieren.
	 *
	 * @return string
	 */
	public static function generate_token(): string {
		return wp_generate_password( 48, false, false );
	}

	/**
	 * Teilnehmer per member_id+event_id holen.
	 *
	 * @param int $event_id  Event.
	 * @param int $member_id Mitglied.
	 * @return array|null
	 */
	public static function get_by_member( int $event_id, int $member_id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %d AND member_id = %d", $event_id, $member_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Teilnehmer per Token holen.
	 *
	 * @param string $token Token.
	 * @return array|null
	 */
	public static function get_by_token( string $token ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE rsvp_token = %s", $token ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Alle Teilnehmer eines Events.
	 *
	 * @param int $event_id Event.
	 * @return array
	 */
	public static function list_for_event( int $event_id ): array {
		global $wpdb;
		$table   = self::table();
		$members = VM_Members::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, m.first_name, m.last_name, m.organization, m.email, m.member_number
				FROM {$table} a LEFT JOIN {$members} m ON m.id = a.member_id
				WHERE a.event_id = %d ORDER BY COALESCE(m.last_name, a.guest_name)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$event_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Statistik für ein Event.
	 *
	 * @param int $event_id Event.
	 * @return array<string,int>
	 */
	public static function stats_for_event( int $event_id ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT rsvp_status, COUNT(*) c FROM {$table} WHERE event_id = %d GROUP BY rsvp_status", $event_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		$out = [ 'pending' => 0, 'yes' => 0, 'no' => 0, 'maybe' => 0, 'total' => 0 ];
		foreach ( (array) $rows as $r ) {
			$out[ $r['rsvp_status'] ] = (int) $r['c'];
			$out['total']            += (int) $r['c'];
		}
		return $out;
	}

	/**
	 * Ermittelt Empfänger basierend auf Event-Einladungsgruppe.
	 *
	 * @param array $event Event-Datensatz.
	 * @return array
	 */
	public static function recipients_for_event( array $event ): array {
		global $wpdb;
		$members = VM_Members::table();

		$group = $event['invitation_group'] ?? 'active';
		switch ( $group ) {
			case 'all':
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
				return (array) $wpdb->get_results( "SELECT * FROM {$members} WHERE status IN ('active','prospect')", ARRAY_A );
			case 'board':
				if ( class_exists( 'VM_Member_Roles' ) ) {
					$keys = array_filter( array_map( 'trim', explode( ',', (string) get_option( 'vm_board_role_keys', 'vorstand' ) ) ) );
					return VM_Member_Roles::get_members_by_role_keys( $keys );
				}
				return [];
			case 'custom':
				if ( class_exists( 'VM_Member_Roles' ) && ! empty( $event['custom_role_key'] ) ) {
					return VM_Member_Roles::get_members_by_role_keys( [ $event['custom_role_key'] ] );
				}
				return [];
			case 'active':
			default:
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
				return (array) $wpdb->get_results( "SELECT * FROM {$members} WHERE status = 'active'", ARRAY_A );
		}
	}

	/**
	 * Erzeugt Attendee-Zeilen + enqueued Mails.
	 *
	 * @param int    $event_id Event.
	 * @param string $subject  E-Mail-Betreff.
	 * @param string $body     E-Mail-Body.
	 * @return int Anzahl erstellter Einladungen.
	 */
	public static function create_invitation_batch( int $event_id, string $subject, string $body ): int {
		global $wpdb;
		$event = VM_Events::get( $event_id );
		if ( ! $event ) {
			return 0;
		}

		$recipients = self::recipients_for_event( $event );
		if ( empty( $recipients ) ) {
			return 0;
		}

		$attendees_table = self::table();
		$queue_table     = $wpdb->prefix . 'vm_email_queue';
		$count           = 0;

		$event_date = $event['start_datetime'] ? mysql2date( 'd.m.Y H:i', $event['start_datetime'] ) : '';

		foreach ( $recipients as $member ) {
			if ( empty( $member['email'] ) ) {
				continue;
			}

			$existing = self::get_by_member( $event_id, (int) $member['id'] );
			if ( $existing ) {
				$token = (string) $existing['rsvp_token'];
			} else {
				$token = self::generate_token();
				$wpdb->insert(
					$attendees_table,
					[
						'event_id'     => $event_id,
						'member_id'    => (int) $member['id'],
						'rsvp_status'  => 'pending',
						'rsvp_token'   => $token,
						'voting_right' => ( 'mitgliederversammlung' === $event['event_type'] && 'active' === $member['status'] ) ? 1 : 0,
						'created_at'   => current_time( 'mysql' ),
					]
				);
			}

			$url = self::rsvp_url( $token );

			// Event-spezifische Platzhalter pro Teilnehmer ersetzen.
			$replacements = [
				'{event_titel}' => (string) $event['title'],
				'{event_datum}' => $event_date,
				'{event_ort}'   => (string) $event['location'],
				'{rsvp_link}'   => $url,
			];
			$personalized_subject = strtr( $subject, $replacements );
			$personalized_body    = strtr( $body, $replacements );

			// Falls {rsvp_link} nicht im Body war, hängen wir ihn sicherheitshalber an.
			if ( false === strpos( $body, '{rsvp_link}' ) ) {
				$personalized_body .= "\n\n" . self::rsvp_link_hint( $token );
			}

			$wpdb->insert(
				$queue_table,
				[
					'member_id'  => (int) $member['id'],
					'subject'    => $personalized_subject,
					'body'       => $personalized_body,
					'created_at' => current_time( 'mysql' ),
				]
			);
			++$count;
		}

		VM_Central_Logger::log(
			'event_invitation_sent',
			[
				'event_id'        => $event_id,
				'recipient_count' => $count,
			]
		);

		return $count;
	}

	/**
	 * Hint-Block mit RSVP-Link als Platzhalter-Ersetzung einmalig im Body.
	 * Wir ersetzen {rsvp_link} später über den Placeholder-Filter.
	 * Dieses Helper ist nur ein Default, falls der Text den Platzhalter nicht enthält.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function rsvp_link_hint( string $token ): string {
		$url = self::rsvp_url( $token );
		return '<p>' . __( 'Bitte antworte über folgenden Link:', 'vereinsmanager' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>';
	}

	/**
	 * Baut die RSVP-URL.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function rsvp_url( string $token ): string {
		return add_query_arg( 'vm_rsvp', $token, home_url( '/' ) );
	}

	/**
	 * Einladungs-Handler.
	 *
	 * @return void
	 */
	public static function handle_invite(): void {
		if ( ! current_user_can( 'vm_manage_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_event_invite' );

		$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
		$subject  = isset( $_POST['vm_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['vm_subject'] ) ) : '';
		$body     = isset( $_POST['vm_body'] ) ? wp_kses_post( wp_unslash( $_POST['vm_body'] ) ) : '';

		if ( ! $event_id || '' === $subject || '' === $body ) {
			wp_safe_redirect(
				add_query_arg(
					[ 'page' => 'vm-event-invite', 'id' => $event_id, 'vm_notice' => 'invalid' ],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$count = self::create_invitation_batch( $event_id, $subject, $body );

		// Sofort Queue anstoßen.
		VM_Email::process_queue();

		wp_safe_redirect(
			add_query_arg(
				[
					'page'      => 'vm-event-attendees',
					'id'        => $event_id,
					'vm_notice' => 'invited',
					'vm_count'  => $count,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Check-in Handler.
	 *
	 * @return void
	 */
	public static function handle_checkin(): void {
		if ( ! current_user_can( 'vm_manage_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id       = isset( $_GET['att_id'] ) ? (int) $_GET['att_id'] : 0;
		$event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
		$val      = isset( $_GET['v'] ) ? (int) $_GET['v'] : 1;
		check_admin_referer( 'vm_event_checkin_' . $id );

		global $wpdb;
		$wpdb->update( self::table(), [ 'attended' => $val ? 1 : 0 ], [ 'id' => $id ] );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'vm-event-attendees', 'id' => $event_id ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * RSVP-Response eintragen (vom Frontend aufgerufen).
	 *
	 * @param string $token  Token.
	 * @param array  $fields Eingabe-Felder.
	 * @return true|WP_Error
	 */
	public static function save_rsvp_response( string $token, array $fields ) {
		global $wpdb;
		$att = self::get_by_token( $token );
		if ( ! $att ) {
			return new WP_Error( 'vm_invalid_token', __( 'Ungültiger Link.', 'vereinsmanager' ) );
		}
		$status = isset( $fields['rsvp_status'] ) ? sanitize_key( $fields['rsvp_status'] ) : '';
		if ( ! in_array( $status, [ 'yes', 'no', 'maybe' ], true ) ) {
			return new WP_Error( 'vm_invalid_status', __( 'Bitte Zu-/Absage auswählen.', 'vereinsmanager' ) );
		}
		$update = [
			'rsvp_status'       => $status,
			'rsvp_responded_at' => current_time( 'mysql' ),
			'plus_one_count'    => isset( $fields['plus_one_count'] ) ? max( 0, (int) $fields['plus_one_count'] ) : 0,
			'plus_one_names'    => isset( $fields['plus_one_names'] ) ? sanitize_textarea_field( (string) $fields['plus_one_names'] ) : '',
			'food_choice'       => isset( $fields['food_choice'] ) ? sanitize_text_field( (string) $fields['food_choice'] ) : '',
			'dietary_notes'     => isset( $fields['dietary_notes'] ) ? sanitize_textarea_field( (string) $fields['dietary_notes'] ) : '',
		];
		$wpdb->update( self::table(), $update, [ 'id' => (int) $att['id'] ] );

		VM_Central_Logger::log(
			'event_rsvp_received',
			[
				'event_id'    => (int) $att['event_id'],
				'rsvp_status' => $status,
			]
		);
		return true;
	}
}
