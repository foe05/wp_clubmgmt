<?php
/**
 * Event-Tagesordnung und Protokoll (inkl. PDF-Export).
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Event_Protocol
 */
class VM_Event_Protocol {

	/**
	 * Agenda-Tabelle.
	 *
	 * @return string
	 */
	public static function agenda_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vm_event_agenda';
	}

	/**
	 * Protokoll-Tabelle.
	 *
	 * @return string
	 */
	public static function protocol_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vm_event_protocols';
	}

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_agenda_save', [ __CLASS__, 'handle_agenda_save' ] );
		add_action( 'admin_post_vm_agenda_delete', [ __CLASS__, 'handle_agenda_delete' ] );
		add_action( 'admin_post_vm_protocol_save', [ __CLASS__, 'handle_protocol_save' ] );
		add_action( 'admin_post_vm_protocol_pdf', [ __CLASS__, 'handle_pdf_export' ] );
	}

	/**
	 * Agenda-Items zu einem Event holen.
	 *
	 * @param int $event_id Event.
	 * @return array
	 */
	public static function get_agenda( int $event_id ): array {
		global $wpdb;
		$table = self::agenda_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %d ORDER BY position ASC, id ASC", $event_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Protokoll-Text holen oder null.
	 *
	 * @param int $event_id Event.
	 * @return array|null
	 */
	public static function get_protocol( int $event_id ): ?array {
		global $wpdb;
		$table = self::protocol_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %d", $event_id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Render Protokoll-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_view_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$event = $id > 0 ? VM_Events::get( $id ) : null;
		if ( ! $event ) {
			wp_die( esc_html__( 'Veranstaltung nicht gefunden.', 'vereinsmanager' ) );
		}
		$agenda   = self::get_agenda( $id );
		$protocol = self::get_protocol( $id );
		include VM_PLUGIN_DIR . 'admin/views/event-protocol.php';
	}

	/**
	 * Handler: Agenda speichern (inline create/update).
	 *
	 * @return void
	 */
	public static function handle_agenda_save(): void {
		if ( ! current_user_can( 'vm_manage_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_agenda_save' );

		$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
		$id       = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$data = [
			'event_id'        => $event_id,
			'position'        => isset( $_POST['position'] ) ? (int) $_POST['position'] : 0,
			'title'           => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'     => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'is_resolution'   => ! empty( $_POST['is_resolution'] ) ? 1 : 0,
			'votes_yes'       => isset( $_POST['votes_yes'] ) ? (int) $_POST['votes_yes'] : 0,
			'votes_no'        => isset( $_POST['votes_no'] ) ? (int) $_POST['votes_no'] : 0,
			'votes_abstain'   => isset( $_POST['votes_abstain'] ) ? (int) $_POST['votes_abstain'] : 0,
			'resolution_text' => isset( $_POST['resolution_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['resolution_text'] ) ) : '',
		];

		global $wpdb;
		$table = self::agenda_table();
		if ( $id > 0 ) {
			$wpdb->update( $table, $data, [ 'id' => $id ] );
		} else {
			$wpdb->insert( $table, $data );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'vm-event-protocol', 'id' => $event_id ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handler: Agenda-Item löschen.
	 *
	 * @return void
	 */
	public static function handle_agenda_delete(): void {
		if ( ! current_user_can( 'vm_manage_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id       = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
		check_admin_referer( 'vm_agenda_delete_' . $id );

		global $wpdb;
		$wpdb->delete( self::agenda_table(), [ 'id' => $id ] );

		wp_safe_redirect( add_query_arg( [ 'page' => 'vm-event-protocol', 'id' => $event_id ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handler: Protokoll-Text speichern.
	 *
	 * @return void
	 */
	public static function handle_protocol_save(): void {
		if ( ! current_user_can( 'vm_manage_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_protocol_save' );

		$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
		$text     = isset( $_POST['protocol_text'] ) ? wp_kses_post( wp_unslash( $_POST['protocol_text'] ) ) : '';
		$signed   = isset( $_POST['signed_by'] ) ? sanitize_text_field( wp_unslash( $_POST['signed_by'] ) ) : '';

		global $wpdb;
		$table   = self::protocol_table();
		$existing = self::get_protocol( $event_id );
		$now     = current_time( 'mysql' );

		if ( $existing ) {
			$wpdb->update(
				$table,
				[
					'protocol_text' => $text,
					'signed_by'     => $signed,
					'updated_at'    => $now,
				],
				[ 'event_id' => $event_id ]
			);
		} else {
			$wpdb->insert(
				$table,
				[
					'event_id'      => $event_id,
					'protocol_text' => $text,
					'signed_by'     => $signed,
					'created_by'    => get_current_user_id(),
					'created_at'    => $now,
					'updated_at'    => $now,
				]
			);
		}

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'vm-event-protocol', 'id' => $event_id, 'vm_notice' => 'saved' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handler: PDF-Export.
	 *
	 * @return void
	 */
	public static function handle_pdf_export(): void {
		if ( ! current_user_can( 'vm_view_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
		check_admin_referer( 'vm_protocol_pdf_' . $event_id );

		$pdf = self::generate_pdf( $event_id );
		if ( is_wp_error( $pdf ) ) {
			wp_die( esc_html( $pdf->get_error_message() ) );
		}

		// Optional: in Dokumentenablage speichern.
		if ( class_exists( 'VM_Documents' ) ) {
			VM_Documents::store_binary_for_event(
				$event_id,
				'protokoll-' . $event_id . '.pdf',
				$pdf,
				'application/pdf'
			);
		}

		VM_Central_Logger::log( 'event_protocol_exported', [ 'event_id' => $event_id ] );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="protokoll-' . $event_id . '.pdf"' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * PDF-Binary erzeugen.
	 *
	 * @param int $event_id Event.
	 * @return string|WP_Error
	 */
	public static function generate_pdf( int $event_id ) {
		$event = VM_Events::get( $event_id );
		if ( ! $event ) {
			return new WP_Error( 'vm_no_event', __( 'Veranstaltung nicht gefunden.', 'vereinsmanager' ) );
		}
		if ( ! class_exists( 'TCPDF' ) ) {
			return new WP_Error( 'vm_no_tcpdf', __( 'TCPDF ist nicht verfügbar.', 'vereinsmanager' ) );
		}

		$agenda    = self::get_agenda( $event_id );
		$protocol  = self::get_protocol( $event_id );
		$attendees = VM_Event_Attendees::list_for_event( $event_id );
		$present   = array_filter(
			$attendees,
			function ( $a ) {
				return (int) $a['attended'] === 1 || 'yes' === $a['rsvp_status'];
			}
		);

		$club_name = (string) get_option( 'vm_club_name', '' );
		$types     = VM_Events::types();

		$pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
		$pdf->SetCreator( 'Vereinsmanager' );
		$pdf->SetAuthor( $club_name );
		$pdf->SetTitle( 'Protokoll – ' . $event['title'] );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->SetMargins( 20, 20, 20 );
		$pdf->AddPage();

		$pdf->SetFont( 'helvetica', 'B', 14 );
		$pdf->Cell( 0, 8, $club_name, 0, 1 );
		$pdf->SetFont( 'helvetica', 'B', 12 );
		$pdf->Cell( 0, 7, 'Protokoll: ' . ( $types[ $event['event_type'] ] ?? $event['event_type'] ), 0, 1 );
		$pdf->Ln( 2 );

		$pdf->SetFont( 'helvetica', '', 10 );
		$pdf->Cell( 40, 6, 'Titel:', 0, 0 );
		$pdf->Cell( 0, 6, $event['title'], 0, 1 );
		$pdf->Cell( 40, 6, 'Datum/Uhrzeit:', 0, 0 );
		$pdf->Cell( 0, 6, $event['start_datetime'] ? mysql2date( 'd.m.Y H:i', $event['start_datetime'] ) : '', 0, 1 );
		$pdf->Cell( 40, 6, 'Ort:', 0, 0 );
		$pdf->Cell( 0, 6, (string) $event['location'], 0, 1 );
		$pdf->Cell( 40, 6, 'Anwesende:', 0, 0 );
		$pdf->Cell( 0, 6, (string) count( $present ), 0, 1 );
		$pdf->Ln( 3 );

		// Anwesenheitsliste.
		$pdf->SetFont( 'helvetica', 'B', 11 );
		$pdf->Cell( 0, 6, 'Anwesenheitsliste', 0, 1 );
		$pdf->SetFont( 'helvetica', '', 9 );
		foreach ( $present as $p ) {
			$name = trim( ( $p['first_name'] ?? '' ) . ' ' . ( $p['last_name'] ?? '' ) );
			if ( '' === $name ) {
				$name = $p['organization'] ?? $p['guest_name'] ?? '—';
			}
			$mark = 1 === (int) $p['attended'] ? '[x]' : '[ ]';
			$pdf->Cell( 0, 5, $mark . ' ' . $name, 0, 1 );
		}
		$pdf->Ln( 4 );

		// Tagesordnung + Beschlüsse.
		$pdf->SetFont( 'helvetica', 'B', 11 );
		$pdf->Cell( 0, 6, 'Tagesordnung & Beschlüsse', 0, 1 );
		$pdf->SetFont( 'helvetica', '', 9 );
		$num = 1;
		foreach ( $agenda as $a ) {
			$pdf->SetFont( 'helvetica', 'B', 10 );
			$pdf->MultiCell( 0, 5, 'TOP ' . $num . ': ' . (string) $a['title'], 0, 'L' );
			$pdf->SetFont( 'helvetica', '', 9 );
			if ( ! empty( $a['description'] ) ) {
				$pdf->MultiCell( 0, 4, (string) $a['description'], 0, 'L' );
			}
			if ( (int) $a['is_resolution'] === 1 ) {
				$pdf->MultiCell(
					0,
					4,
					sprintf(
						'Abstimmung – Ja: %d, Nein: %d, Enthaltung: %d',
						(int) $a['votes_yes'],
						(int) $a['votes_no'],
						(int) $a['votes_abstain']
					),
					0,
					'L'
				);
				if ( ! empty( $a['resolution_text'] ) ) {
					$pdf->MultiCell( 0, 4, 'Beschluss: ' . (string) $a['resolution_text'], 0, 'L' );
				}
			}
			$pdf->Ln( 2 );
			++$num;
		}

		if ( $protocol && ! empty( $protocol['protocol_text'] ) ) {
			$pdf->Ln( 3 );
			$pdf->SetFont( 'helvetica', 'B', 11 );
			$pdf->Cell( 0, 6, 'Protokolltext', 0, 1 );
			$pdf->SetFont( 'helvetica', '', 9 );
			$plain = wp_strip_all_tags( (string) $protocol['protocol_text'] );
			$pdf->MultiCell( 0, 4, $plain, 0, 'L' );
		}

		$pdf->Ln( 10 );
		$pdf->Cell( 0, 5, '_______________________________', 0, 1 );
		$pdf->Cell( 0, 5, 'Unterschrift: ' . ( $protocol['signed_by'] ?? '' ), 0, 1 );

		return $pdf->Output( 'protocol.pdf', 'S' );
	}
}
