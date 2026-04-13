<?php
/**
 * Plugin-Deaktivierung.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Deactivator
 */
class VM_Deactivator {

	/**
	 * Wird bei Plugin-Deaktivierung aufgerufen.
	 * Entfernt Rollen + Cron, lässt aber Tabellen und Optionen erhalten.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		VM_Roles::remove_roles();

		$timestamp = wp_next_scheduled( 'vm_process_email_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'vm_process_email_queue' );
		}
		$anniv_ts = wp_next_scheduled( 'vm_anniversary_daily' );
		if ( $anniv_ts ) {
			wp_unschedule_event( $anniv_ts, 'vm_anniversary_daily' );
		}
	}
}
