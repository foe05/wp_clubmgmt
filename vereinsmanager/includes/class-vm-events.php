<?php
/**
 * Veranstaltungsverwaltung.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Events
 */
class VM_Events {

	/**
	 * Tabellenname.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vm_events';
	}

	/**
	 * Erlaubte Event-Typen.
	 *
	 * @return array<string,string>
	 */
	public static function types(): array {
		return [
			'mitgliederversammlung' => __( 'Mitgliederversammlung', 'vereinsmanager' ),
			'sommerfest'            => __( 'Sommerfest', 'vereinsmanager' ),
			'vorstandssitzung'      => __( 'Vorstandssitzung', 'vereinsmanager' ),
			'other'                 => __( 'Sonstiges', 'vereinsmanager' ),
		];
	}

	/**
	 * Erlaubte Stati.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return [
			'draft'     => __( 'Entwurf', 'vereinsmanager' ),
			'published' => __( 'Veröffentlicht', 'vereinsmanager' ),
			'cancelled' => __( 'Abgesagt', 'vereinsmanager' ),
			'closed'    => __( 'Abgeschlossen', 'vereinsmanager' ),
		];
	}

	/**
	 * Einladungsgruppen.
	 *
	 * @return array<string,string>
	 */
	public static function invitation_groups(): array {
		return [
			'active' => __( 'Alle aktiven Mitglieder', 'vereinsmanager' ),
			'all'    => __( 'Alle Mitglieder (aktiv + Interessenten)', 'vereinsmanager' ),
			'board'  => __( 'Vorstand (über Ämter)', 'vereinsmanager' ),
			'custom' => __( 'Einzelne Rolle (Key)', 'vereinsmanager' ),
		];
	}

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_event_save', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_post_vm_event_delete', [ __CLASS__, 'handle_delete' ] );
		add_action( 'admin_notices', [ __CLASS__, 'maybe_show_ladungsfrist_warning' ] );
	}

	/**
	 * Event laden.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( $row && isset( $row['extra_fields'] ) && is_string( $row['extra_fields'] ) ) {
			$decoded              = json_decode( $row['extra_fields'], true );
			$row['extra_fields_decoded'] = is_array( $decoded ) ? $decoded : [];
		}
		return $row ?: null;
	}

	/**
	 * Events abfragen.
	 *
	 * @param array $args Filter.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;
		$table = self::table();

		$defaults = [
			'type'     => '',
			'status'   => '',
			'year'     => '',
			'search'   => '',
			'orderby'  => 'start_datetime',
			'order'    => 'DESC',
			'per_page' => 25,
			'paged'    => 1,
		];
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = [];

		if ( isset( self::types()[ $args['type'] ] ) ) {
			$where   .= ' AND event_type = %s';
			$params[] = $args['type'];
		}
		if ( isset( self::statuses()[ $args['status'] ] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['year'] ) ) {
			$where   .= ' AND YEAR(start_datetime) = %d';
			$params[] = (int) $args['year'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (title LIKE %s OR location LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$allowed_order = [ 'id', 'title', 'start_datetime', 'event_type', 'status' ];
		$orderby       = in_array( $args['orderby'], $allowed_order, true ) ? $args['orderby'] : 'start_datetime';
		$order         = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$sql        = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$sql_params = array_merge( $params, [ $per_page, $offset ] );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $sql_params ), ARRAY_A );

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}

	/**
	 * Eingaben sanitizen.
	 *
	 * @param array $data Roh-POST.
	 * @return array
	 */
	public static function sanitize( array $data ): array {
		$type = isset( $data['event_type'] ) ? sanitize_key( $data['event_type'] ) : 'other';
		if ( ! isset( self::types()[ $type ] ) ) {
			$type = 'other';
		}

		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft';
		if ( ! isset( self::statuses()[ $status ] ) ) {
			$status = 'draft';
		}

		$group = isset( $data['invitation_group'] ) ? sanitize_key( $data['invitation_group'] ) : 'active';
		if ( ! isset( self::invitation_groups()[ $group ] ) ) {
			$group = 'active';
		}

		$extra = [];
		if ( isset( $data['extra_fields'] ) && is_array( $data['extra_fields'] ) ) {
			foreach ( $data['extra_fields'] as $k => $v ) {
				$key           = sanitize_key( (string) $k );
				$extra[ $key ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
			}
		}

		return [
			'event_type'            => $type,
			'title'                 => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'description'           => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
			'location'              => isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : '',
			'start_datetime'        => self::sanitize_datetime( $data['start_datetime'] ?? '' ),
			'end_datetime'          => self::sanitize_datetime( $data['end_datetime'] ?? '' ),
			'registration_deadline' => self::sanitize_datetime( $data['registration_deadline'] ?? '' ),
			'max_attendees'         => isset( $data['max_attendees'] ) && '' !== $data['max_attendees'] ? absint( $data['max_attendees'] ) : null,
			'invitation_group'      => $group,
			'custom_role_key'       => isset( $data['custom_role_key'] ) ? sanitize_key( $data['custom_role_key'] ) : '',
			'extra_fields'          => wp_json_encode( $extra ),
			'status'                => $status,
		];
	}

	/**
	 * Datetime-Sanitizer.
	 *
	 * @param mixed $value Roh.
	 * @return string|null
	 */
	private static function sanitize_datetime( $value ): ?string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		// HTML5 datetime-local liefert "YYYY-MM-DDTHH:MM".
		$value = str_replace( 'T', ' ', $value );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value ) ) {
			return strlen( $value ) === 16 ? $value . ':00' : $value;
		}
		return null;
	}

	/**
	 * Event speichern.
	 *
	 * @param array $data Roh-Daten.
	 * @return int|WP_Error
	 */
	public static function save( array $data ) {
		global $wpdb;
		$table = self::table();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$sanitized = self::sanitize( $data );
		if ( empty( $sanitized['title'] ) ) {
			return new WP_Error( 'vm_missing_title', __( 'Titel ist erforderlich.', 'vereinsmanager' ) );
		}
		if ( empty( $sanitized['start_datetime'] ) ) {
			return new WP_Error( 'vm_missing_date', __( 'Startdatum/-zeit ist erforderlich.', 'vereinsmanager' ) );
		}

		$now                    = current_time( 'mysql' );
		$sanitized['updated_at'] = $now;

		if ( $id > 0 ) {
			$existing = self::get( $id );
			if ( ! $existing ) {
				return new WP_Error( 'vm_not_found', __( 'Veranstaltung nicht gefunden.', 'vereinsmanager' ) );
			}
			$result = $wpdb->update( $table, $sanitized, [ 'id' => $id ] );
			if ( false === $result ) {
				return new WP_Error( 'vm_db_error', __( 'Datenbankfehler.', 'vereinsmanager' ) );
			}
			if ( $existing['status'] !== $sanitized['status'] && 'published' === $sanitized['status'] ) {
				VM_Central_Logger::log( 'event_published', [ 'event_id' => $id ] );
			}
			do_action( 'vm_event_updated', $id, $sanitized, $existing );
			return $id;
		}

		$sanitized['created_at'] = $now;
		$sanitized['created_by'] = get_current_user_id();

		$result = $wpdb->insert( $table, $sanitized );
		if ( false === $result ) {
			return new WP_Error( 'vm_db_error', __( 'Datenbankfehler.', 'vereinsmanager' ) );
		}
		$new_id = (int) $wpdb->insert_id;

		VM_Central_Logger::log(
			'event_created',
			[
				'event_id'   => $new_id,
				'event_type' => $sanitized['event_type'],
			]
		);
		if ( 'published' === $sanitized['status'] ) {
			VM_Central_Logger::log( 'event_published', [ 'event_id' => $new_id ] );
		}
		do_action( 'vm_event_created', $new_id, $sanitized );
		return $new_id;
	}

	/**
	 * Event löschen.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$ok = (bool) $wpdb->delete( self::table(), [ 'id' => $id ] );
		if ( $ok ) {
			$wpdb->delete( $wpdb->prefix . 'vm_event_attendees', [ 'event_id' => $id ] );
			$wpdb->delete( $wpdb->prefix . 'vm_event_agenda', [ 'event_id' => $id ] );
			$wpdb->delete( $wpdb->prefix . 'vm_event_protocols', [ 'event_id' => $id ] );
			VM_Central_Logger::log( 'event_deleted', [ 'event_id' => $id ] );
		}
		return $ok;
	}

	/**
	 * Render-Liste.
	 *
	 * @return void
	 */
	public static function render_list(): void {
		if ( ! current_user_can( 'vm_view_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		require_once VM_PLUGIN_DIR . 'includes/class-vm-events-list.php';
		$list_table = new VM_Events_List_Table();
		$list_table->prepare_items();
		include VM_PLUGIN_DIR . 'admin/views/event-list.php';
	}

	/**
	 * Render-Edit.
	 *
	 * @return void
	 */
	public static function render_edit(): void {
		if ( ! current_user_can( 'vm_view_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$event = $id > 0 ? self::get( $id ) : null;
		include VM_PLUGIN_DIR . 'admin/views/event-edit.php';
	}

	/**
	 * Render Attendees.
	 *
	 * @return void
	 */
	public static function render_attendees(): void {
		if ( ! current_user_can( 'vm_view_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$event = $id > 0 ? self::get( $id ) : null;
		if ( ! $event ) {
			wp_die( esc_html__( 'Veranstaltung nicht gefunden.', 'vereinsmanager' ) );
		}
		$attendees = VM_Event_Attendees::list_for_event( $id );
		$stats     = VM_Event_Attendees::stats_for_event( $id );
		include VM_PLUGIN_DIR . 'admin/views/event-attendees.php';
	}

	/**
	 * Render Invite.
	 *
	 * @return void
	 */
	public static function render_invite(): void {
		if ( ! current_user_can( 'vm_manage_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$event = $id > 0 ? self::get( $id ) : null;
		if ( ! $event ) {
			wp_die( esc_html__( 'Veranstaltung nicht gefunden.', 'vereinsmanager' ) );
		}
		include VM_PLUGIN_DIR . 'admin/views/event-invite.php';
	}

	/**
	 * Save-Handler.
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		if ( ! current_user_can( 'vm_manage_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_event_save' );

		$data   = wp_unslash( $_POST );
		$result = self::save( $data );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'page'      => 'vm-event-edit',
						'id'        => isset( $data['id'] ) ? (int) $data['id'] : 0,
						'vm_notice' => 'error',
						'vm_msg'    => rawurlencode( $result->get_error_message() ),
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		wp_safe_redirect(
			add_query_arg(
				[
					'page'      => 'vm-event-edit',
					'id'        => $result,
					'vm_notice' => 'updated',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete-Handler.
	 *
	 * @return void
	 */
	public static function handle_delete(): void {
		if ( ! current_user_can( 'vm_manage_events' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'vm_event_delete_' . $id );
		self::delete( $id );
		wp_safe_redirect( add_query_arg( [ 'page' => 'vm-events', 'vm_notice' => 'deleted' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Warnung bei zu kurzer Ladungsfrist für Mitgliederversammlungen.
	 *
	 * @return void
	 */
	public static function maybe_show_ladungsfrist_warning(): void {
		if ( ! isset( $_GET['page'] ) || 'vm-event-edit' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id <= 0 ) {
			return;
		}
		$event = self::get( $id );
		if ( ! $event || 'mitgliederversammlung' !== $event['event_type'] || 'published' !== $event['status'] ) {
			return;
		}
		$days  = (int) get_option( 'vm_mv_ladungsfrist_days', 14 );
		$start = strtotime( (string) $event['start_datetime'] );
		if ( ! $start ) {
			return;
		}
		$min_deadline = $start - ( $days * DAY_IN_SECONDS );
		$deadline     = $event['registration_deadline'] ? strtotime( (string) $event['registration_deadline'] ) : 0;
		if ( ! $deadline || $deadline > $min_deadline ) {
			echo '<div class="notice notice-warning"><p>';
			printf(
				esc_html__( 'Achtung: Die Ladungsfrist für diese Mitgliederversammlung beträgt weniger als %d Tage. Bitte Satzung prüfen.', 'vereinsmanager' ),
				(int) $days
			);
			echo '</p></div>';
		}
	}
}
