<?php
/**
 * Rollen und Capabilities.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Roles
 */
class VM_Roles {

	/**
	 * Liefert alle Plugin-Capabilities.
	 *
	 * @return string[]
	 */
	public static function all_capabilities(): array {
		return [
			'vm_manage_members',
			'vm_view_members',
			'vm_manage_finances',
			'vm_send_emails',
			'vm_manage_settings',
			'vm_export_data',
			'vm_import_data',
			'vm_delete_members',
		];
	}

	/**
	 * Capabilities für vm_admin (Vereinsverwalter).
	 *
	 * @return string[]
	 */
	public static function admin_caps(): array {
		return self::all_capabilities();
	}

	/**
	 * Capabilities für vm_board_member (Vorstand).
	 *
	 * @return string[]
	 */
	public static function board_caps(): array {
		return [
			'vm_manage_members',
			'vm_view_members',
			'vm_manage_finances',
			'vm_send_emails',
		];
	}

	/**
	 * Rollen anlegen und allen Caps zu administrator zuweisen.
	 *
	 * @return void
	 */
	public static function add_roles(): void {
		$admin_caps = array_fill_keys( self::admin_caps(), true );
		$admin_caps['read'] = true;

		$board_caps = array_fill_keys( self::board_caps(), true );
		$board_caps['read'] = true;

		add_role(
			'vm_admin',
			__( 'Vereinsverwalter', 'vereinsmanager' ),
			$admin_caps
		);

		add_role(
			'vm_board_member',
			__( 'Vorstandsmitglied', 'vereinsmanager' ),
			$board_caps
		);

		// Auch dem WP-Administrator volle Rechte geben.
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::all_capabilities() as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
	}

	/**
	 * Rollen entfernen und Caps vom Administrator entfernen.
	 *
	 * @return void
	 */
	public static function remove_roles(): void {
		remove_role( 'vm_admin' );
		remove_role( 'vm_board_member' );

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::all_capabilities() as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}
	}
}
