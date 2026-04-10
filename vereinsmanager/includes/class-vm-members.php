<?php
/**
 * Mitgliederverwaltung – CRUD und Form-Handling.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Members
 */
class VM_Members {

	/**
	 * Tabellenname.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vm_members';
	}

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_save_member', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_post_vm_delete_member', [ __CLASS__, 'handle_delete' ] );
	}

	/**
	 * Erlaubte Status-Werte.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		return [ 'prospect', 'active', 'former' ];
	}

	/**
	 * Statuslabels.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels(): array {
		return [
			'prospect' => __( 'Interessent', 'vereinsmanager' ),
			'active'   => __( 'Aktiv', 'vereinsmanager' ),
			'former'   => __( 'Ehemalig', 'vereinsmanager' ),
		];
	}

	/**
	 * Mitgliedsdaten anhand ID laden.
	 *
	 * @param int $id Mitglieds-ID.
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = self::table();

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ?: null;
	}

	/**
	 * Mitgliederliste mit Filtern.
	 *
	 * @param array $args Filter-Argumente.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;
		$table = self::table();

		$defaults = [
			'status'          => '',
			'membership_type' => '',
			'search'          => '',
			'orderby'         => 'last_name',
			'order'           => 'ASC',
			'per_page'        => 25,
			'paged'           => 1,
		];
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = [];

		if ( in_array( $args['status'], self::statuses(), true ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( in_array( $args['membership_type'], [ 'standard', 'reduced' ], true ) ) {
			$where   .= ' AND membership_type = %s';
			$params[] = $args['membership_type'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR member_number LIKE %s OR organization LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$allowed_order = [ 'id', 'member_number', 'last_name', 'first_name', 'status', 'entry_date', 'created_at' ];
		$orderby       = in_array( $args['orderby'], $allowed_order, true ) ? $args['orderby'] : 'last_name';
		$order         = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		// Total.
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		// Items.
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
	 * Mitglied speichern (Insert oder Update).
	 *
	 * @param array $data Roh-POST-Daten.
	 * @return int|WP_Error Mitglieds-ID oder Fehler.
	 */
	public static function save( array $data ) {
		global $wpdb;
		$table = self::table();

		$id = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$sanitized = self::sanitize( $data );

		if ( empty( $sanitized['last_name'] ) && empty( $sanitized['organization'] ) ) {
			return new WP_Error( 'vm_missing_name', __( 'Bitte Nachname oder Organisation angeben.', 'vereinsmanager' ) );
		}

		$now                  = current_time( 'mysql' );
		$sanitized['updated_at'] = $now;

		if ( $id > 0 ) {
			$existing = self::get( $id );
			if ( ! $existing ) {
				return new WP_Error( 'vm_not_found', __( 'Mitglied nicht gefunden.', 'vereinsmanager' ) );
			}

			$old_status = $existing['status'];
			$new_status = $sanitized['status'];

			// Bei Statuswechsel auf "active" Mitgliedsnummer vergeben (falls noch nicht vorhanden).
			if ( 'active' === $new_status && empty( $existing['member_number'] ) ) {
				$sanitized['member_number'] = self::generate_member_number( $sanitized['entry_date'] ?: gmdate( 'Y-m-d' ) );
			}

			$result = $wpdb->update( $table, $sanitized, [ 'id' => $id ] );
			if ( false === $result ) {
				return new WP_Error( 'vm_db_error', __( 'Datenbankfehler beim Speichern.', 'vereinsmanager' ) );
			}

			$changed = array_keys( array_diff_assoc( $sanitized, $existing ) );

			VM_Central_Logger::log(
				'member_updated',
				[
					'member_id'      => $id,
					'changed_fields' => $changed,
				]
			);

			if ( $old_status !== $new_status ) {
				self::log_status_change( $id, $old_status, $new_status );
			}

			do_action( 'vm_member_updated', $id, $sanitized, $existing );

			return $id;
		}

		// Insert.
		$sanitized['created_at'] = $now;

		if ( 'active' === $sanitized['status'] ) {
			$sanitized['member_number'] = self::generate_member_number( $sanitized['entry_date'] ?: gmdate( 'Y-m-d' ) );
		}

		$result = $wpdb->insert( $table, $sanitized );
		if ( false === $result ) {
			return new WP_Error( 'vm_db_error', __( 'Datenbankfehler beim Anlegen.', 'vereinsmanager' ) );
		}

		$new_id = (int) $wpdb->insert_id;

		VM_Central_Logger::log(
			'member_created',
			[
				'member_id' => $new_id,
				'status'    => $sanitized['status'],
			]
		);

		self::log_status_change( $new_id, null, $sanitized['status'] );

		do_action( 'vm_member_created', $new_id, $sanitized );

		return $new_id;
	}

	/**
	 * Status-Wechsel protokollieren.
	 *
	 * @param int         $member_id  Mitglieds-ID.
	 * @param string|null $old_status Alter Status.
	 * @param string      $new_status Neuer Status.
	 * @return void
	 */
	private static function log_status_change( int $member_id, ?string $old_status, string $new_status ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'vm_status_log',
			[
				'member_id'  => $member_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'changed_at' => current_time( 'mysql' ),
				'user_id'    => get_current_user_id(),
			]
		);

		if ( null !== $old_status ) {
			VM_Central_Logger::log(
				'member_status_changed',
				[
					'member_id'  => $member_id,
					'old_status' => $old_status,
					'new_status' => $new_status,
				]
			);
		}
	}

	/**
	 * Mitgliedsnummer generieren (FV-JJJJ-NNNN).
	 *
	 * @param string $entry_date Eintrittsdatum (Y-m-d).
	 * @return string
	 */
	public static function generate_member_number( string $entry_date ): string {
		global $wpdb;
		$table  = self::table();
		$prefix = (string) get_option( 'vm_member_number_prefix', 'FV' );
		$year   = substr( $entry_date, 0, 4 );
		if ( ! preg_match( '/^\d{4}$/', $year ) ) {
			$year = gmdate( 'Y' );
		}

		// Letzte Nummer dieses Jahres ermitteln.
		$like = $wpdb->esc_like( $prefix . '-' . $year . '-' ) . '%';
		$last = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT member_number FROM {$table} WHERE member_number LIKE %s ORDER BY member_number DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$like
			)
		);

		$next = 1;
		if ( $last && preg_match( '/-(\d+)$/', $last, $m ) ) {
			$next = (int) $m[1] + 1;
		}

		return sprintf( '%s-%s-%04d', $prefix, $year, $next );
	}

	/**
	 * Eingaben sanitizen.
	 *
	 * @param array $data Roh-Daten.
	 * @return array
	 */
	public static function sanitize( array $data ): array {
		$status = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'prospect';
		if ( ! in_array( $status, self::statuses(), true ) ) {
			$status = 'prospect';
		}

		$type = isset( $data['membership_type'] ) ? sanitize_text_field( $data['membership_type'] ) : 'standard';
		if ( ! in_array( $type, [ 'standard', 'reduced' ], true ) ) {
			$type = 'standard';
		}

		return [
			'status'                       => $status,
			'salutation'                   => isset( $data['salutation'] ) ? sanitize_text_field( $data['salutation'] ) : '',
			'title'                        => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'first_name'                   => isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '',
			'last_name'                    => isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '',
			'organization'                 => isset( $data['organization'] ) ? sanitize_text_field( $data['organization'] ) : '',
			'street'                       => isset( $data['street'] ) ? sanitize_text_field( $data['street'] ) : '',
			'postal_code'                  => isset( $data['postal_code'] ) ? sanitize_text_field( $data['postal_code'] ) : '',
			'city'                         => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : '',
			'country'                      => isset( $data['country'] ) ? strtoupper( substr( sanitize_text_field( $data['country'] ), 0, 2 ) ) : 'DE',
			'email'                        => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'phone'                        => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
			'date_of_birth'                => self::sanitize_date( $data['date_of_birth'] ?? '' ),
			'entry_date'                   => self::sanitize_date( $data['entry_date'] ?? '' ),
			'exit_date'                    => self::sanitize_date( $data['exit_date'] ?? '' ),
			'exit_reason'                  => isset( $data['exit_reason'] ) ? sanitize_text_field( $data['exit_reason'] ) : '',
			'membership_type'              => $type,
			'fee_amount'                   => isset( $data['fee_amount'] ) && '' !== $data['fee_amount'] ? self::sanitize_decimal( $data['fee_amount'] ) : null,
			'bank_iban'                    => isset( $data['bank_iban'] ) ? strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $data['bank_iban'] ) ) ) : '',
			'bank_bic'                     => isset( $data['bank_bic'] ) ? strtoupper( sanitize_text_field( $data['bank_bic'] ) ) : '',
			'bank_account_holder'          => isset( $data['bank_account_holder'] ) ? sanitize_text_field( $data['bank_account_holder'] ) : '',
			'sepa_mandate'                 => ! empty( $data['sepa_mandate'] ) ? 1 : 0,
			'sepa_mandate_reference'       => isset( $data['sepa_mandate_reference'] ) ? sanitize_text_field( $data['sepa_mandate_reference'] ) : '',
			'sepa_mandate_date'            => self::sanitize_date( $data['sepa_mandate_date'] ?? '' ),
			'consent_data_processing'      => ! empty( $data['consent_data_processing'] ) ? 1 : 0,
			'consent_data_processing_date' => self::sanitize_date( $data['consent_data_processing_date'] ?? '' ),
			'consent_email'                => ! empty( $data['consent_email'] ) ? 1 : 0,
			'consent_email_date'           => self::sanitize_date( $data['consent_email_date'] ?? '' ),
			'notes'                        => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
		];
	}

	/**
	 * Datum-Sanitizer (gibt NULL bei leerem String zurück).
	 *
	 * @param mixed $value Roh-Wert.
	 * @return string|null
	 */
	private static function sanitize_date( $value ): ?string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null;
	}

	/**
	 * Decimal-Sanitizer.
	 *
	 * @param mixed $value Roh-Wert.
	 * @return string
	 */
	private static function sanitize_decimal( $value ): string {
		$value = str_replace( ',', '.', (string) $value );
		return number_format( (float) $value, 2, '.', '' );
	}

	/**
	 * Mitglied löschen.
	 *
	 * @param int $id Mitglieds-ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete( self::table(), [ 'id' => $id ] );
		if ( $result ) {
			VM_Central_Logger::log( 'member_deleted', [ 'member_id' => $id ] );
			do_action( 'vm_member_deleted', $id );
			return true;
		}
		return false;
	}

	/**
	 * Vollständigen Namen formatieren.
	 *
	 * @param array $member Mitglied.
	 * @return string
	 */
	public static function format_name( array $member ): string {
		$parts = array_filter(
			[
				$member['title'] ?? '',
				$member['first_name'] ?? '',
				$member['last_name'] ?? '',
			]
		);
		$name  = trim( implode( ' ', $parts ) );

		if ( ! empty( $member['organization'] ) ) {
			return $name ? $member['organization'] . ' (' . $name . ')' : $member['organization'];
		}
		return $name;
	}

	/**
	 * Liste rendern.
	 *
	 * @return void
	 */
	public static function render_list(): void {
		if ( ! current_user_can( 'vm_view_members' ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung für diese Seite.', 'vereinsmanager' ) );
		}

		require_once VM_PLUGIN_DIR . 'includes/class-vm-members-list.php';
		$list_table = new VM_Members_List_Table();

		// Bulk-Action verarbeiten.
		self::maybe_process_bulk( $list_table );

		$list_table->prepare_items();

		include VM_PLUGIN_DIR . 'admin/views/member-list.php';
	}

	/**
	 * Bulk-Aktion auf der Mitglieder-Liste verarbeiten.
	 *
	 * @param VM_Members_List_Table $list_table List-Table-Instanz.
	 * @return void
	 */
	private static function maybe_process_bulk( $list_table ): void {
		$action = $list_table->current_action();
		if ( ! $action ) {
			return;
		}
		if ( ! current_user_can( 'vm_manage_members' ) ) {
			return;
		}
		check_admin_referer( 'bulk-' . $list_table->_args['plural'] );

		$ids = isset( $_REQUEST['member_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_REQUEST['member_ids'] ) ) : [];
		if ( empty( $ids ) ) {
			return;
		}

		switch ( $action ) {
			case 'set_active':
			case 'set_prospect':
			case 'set_former':
				$status = str_replace( 'set_', '', $action );
				foreach ( $ids as $id ) {
					$existing = self::get( $id );
					if ( $existing ) {
						$existing['status'] = $status;
						self::save( $existing );
					}
				}
				break;
			case 'delete':
				if ( current_user_can( 'vm_delete_members' ) ) {
					foreach ( $ids as $id ) {
						self::delete( $id );
					}
				}
				break;
		}

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Aktion ausgeführt.', 'vereinsmanager' ) . '</p></div>';
			}
		);
	}

	/**
	 * Bearbeiten/Anlegen rendern.
	 *
	 * @return void
	 */
	public static function render_edit(): void {
		if ( ! current_user_can( 'vm_view_members' ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung für diese Seite.', 'vereinsmanager' ) );
		}

		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$member = $id > 0 ? self::get( $id ) : null;

		include VM_PLUGIN_DIR . 'admin/views/member-edit.php';
	}

	/**
	 * Save-Handler (admin-post.php).
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		if ( ! current_user_can( 'vm_manage_members' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_save_member' );

		$data   = wp_unslash( $_POST );
		$result = self::save( $data );

		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg(
				[
					'page'      => 'vm-member-edit',
					'id'        => isset( $data['id'] ) ? (int) $data['id'] : 0,
					'vm_notice' => 'error',
					'vm_msg'    => rawurlencode( $result->get_error_message() ),
				],
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$redirect = add_query_arg(
			[
				'page'      => 'vm-member-edit',
				'id'        => $result,
				'vm_notice' => 'updated',
			],
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Lösch-Handler.
	 *
	 * @return void
	 */
	public static function handle_delete(): void {
		if ( ! current_user_can( 'vm_delete_members' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'vm_delete_member_' . $id );

		self::delete( $id );

		wp_safe_redirect( add_query_arg( [ 'page' => 'vm-members', 'vm_notice' => 'deleted' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Anzahl Mitglieder pro Status.
	 *
	 * @return array<string,int>
	 */
	public static function count_by_status(): array {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$out  = [
			'prospect' => 0,
			'active'   => 0,
			'former'   => 0,
		];
		foreach ( (array) $rows as $row ) {
			$out[ $row['status'] ] = (int) $row['c'];
		}
		return $out;
	}
}
