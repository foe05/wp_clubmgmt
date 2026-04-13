<?php
/**
 * Ämter und Funktionen (Vorstand, Kassenwart etc.).
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Member_Roles
 */
class VM_Member_Roles {

	/**
	 * Tabellenname.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vm_member_roles';
	}

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_member_role_add', [ __CLASS__, 'handle_add' ] );
		add_action( 'admin_post_vm_member_role_end', [ __CLASS__, 'handle_end' ] );
	}

	/**
	 * Alle Rollen-Zuweisungen eines Mitglieds.
	 *
	 * @param int $member_id Mitglied.
	 * @return array
	 */
	public static function for_member( int $member_id ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE member_id = %d ORDER BY start_date DESC", $member_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Aktive Mitglieder zu einer Rolle.
	 *
	 * @param string $role_key Rollen-Key.
	 * @return array
	 */
	public static function get_members_by_role( string $role_key ): array {
		return self::get_members_by_role_keys( [ $role_key ] );
	}

	/**
	 * Aktive Mitglieder zu einer Menge Rollen-Keys.
	 *
	 * @param array $role_keys Rollen-Keys.
	 * @return array
	 */
	public static function get_members_by_role_keys( array $role_keys ): array {
		global $wpdb;
		$role_keys = array_values( array_filter( array_map( 'sanitize_key', $role_keys ) ) );
		if ( empty( $role_keys ) ) {
			return [];
		}

		$table      = self::table();
		$members    = VM_Members::table();
		$today      = current_time( 'Y-m-d' );
		$placeholder = implode( ',', array_fill( 0, count( $role_keys ), '%s' ) );

		$sql    = "SELECT DISTINCT m.* FROM {$members} m
			INNER JOIN {$table} r ON r.member_id = m.id
			WHERE r.role_key IN ({$placeholder})
				AND (r.end_date IS NULL OR r.end_date > %s)
				AND m.status = 'active'";
		$params = array_merge( $role_keys, [ $today ] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return $rows ?: [];
	}

	/**
	 * Prüft, ob Mitglied aktuell Vorstandsmitglied ist.
	 *
	 * @param int $member_id Mitglied.
	 * @return bool
	 */
	public static function is_board_member( int $member_id ): bool {
		global $wpdb;
		$table = self::table();
		$today = current_time( 'Y-m-d' );
		$keys  = array_filter( array_map( 'trim', explode( ',', (string) get_option( 'vm_board_role_keys', 'vorstand' ) ) ) );
		if ( empty( $keys ) ) {
			return false;
		}
		$placeholder = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		$sql    = "SELECT COUNT(*) FROM {$table} WHERE member_id = %d
			AND role_key IN ({$placeholder})
			AND (end_date IS NULL OR end_date > %s)";
		$params = array_merge( [ $member_id ], $keys, [ $today ] );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) > 0;
	}

	/**
	 * Handler: Neue Rollen-Zuweisung.
	 *
	 * @return void
	 */
	public static function handle_add(): void {
		if ( ! current_user_can( 'vm_manage_members' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_member_role_add' );

		$member_id = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;
		$key       = isset( $_POST['role_key'] ) ? sanitize_key( wp_unslash( $_POST['role_key'] ) ) : '';
		$label     = isset( $_POST['role_label'] ) ? sanitize_text_field( wp_unslash( $_POST['role_label'] ) ) : '';
		$start     = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$notes     = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		if ( $member_id <= 0 || '' === $key ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=vm-members' ) );
			exit;
		}

		global $wpdb;
		$wpdb->insert(
			self::table(),
			[
				'member_id'  => $member_id,
				'role_key'   => $key,
				'role_label' => $label,
				'start_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ? $start : null,
				'notes'      => $notes,
				'created_at' => current_time( 'mysql' ),
			]
		);

		VM_Central_Logger::log(
			'member_role_assigned',
			[
				'member_id' => $member_id,
				'role_key'  => $key,
			]
		);

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'vm-member-edit', 'id' => $member_id, 'vm_notice' => 'role_added' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handler: Rollenzuweisung beenden (end_date setzen).
	 *
	 * @return void
	 */
	public static function handle_end(): void {
		if ( ! current_user_can( 'vm_manage_members' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id        = isset( $_GET['role_id'] ) ? (int) $_GET['role_id'] : 0;
		$member_id = isset( $_GET['member_id'] ) ? (int) $_GET['member_id'] : 0;
		check_admin_referer( 'vm_member_role_end_' . $id );

		global $wpdb;
		$wpdb->update(
			self::table(),
			[ 'end_date' => current_time( 'Y-m-d' ) ],
			[ 'id' => $id ]
		);

		VM_Central_Logger::log( 'member_role_ended', [ 'role_id' => $id, 'member_id' => $member_id ] );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'vm-member-edit', 'id' => $member_id, 'vm_notice' => 'role_ended' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
