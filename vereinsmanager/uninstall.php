<?php
/**
 * Wird bei der Plugin-Deinstallation ausgeführt.
 * Tabellen droppen, Optionen löschen, Rollen entfernen.
 *
 * @package Vereinsmanager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = [
	$wpdb->prefix . 'vm_members',
	$wpdb->prefix . 'vm_payments',
	$wpdb->prefix . 'vm_email_log',
	$wpdb->prefix . 'vm_email_queue',
	$wpdb->prefix . 'vm_status_log',
	$wpdb->prefix . 'vm_events',
	$wpdb->prefix . 'vm_event_attendees',
	$wpdb->prefix . 'vm_event_agenda',
	$wpdb->prefix . 'vm_event_protocols',
	$wpdb->prefix . 'vm_member_roles',
	$wpdb->prefix . 'vm_documents',
];

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$options = [
	'vm_club_name',
	'vm_club_street',
	'vm_club_postal_code',
	'vm_club_city',
	'vm_club_tax_number',
	'vm_club_tax_office',
	'vm_club_exemption_date',
	'vm_creditor_id',
	'vm_fee_standard',
	'vm_fee_reduced',
	'vm_member_number_prefix',
	'vm_retention_years',
	'vm_email_from_name',
	'vm_email_from_address',
	'vm_logging_api_url',
	'vm_logging_api_key',
	'vm_logging_enabled',
	'vm_mv_ladungsfrist_days',
	'vm_board_role_keys',
	'vm_anniversary_notify',
	'vm_anniversary_days_ahead',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// Rollen entfernen.
remove_role( 'vm_admin' );
remove_role( 'vm_board_member' );

$capabilities = [
	'vm_manage_members',
	'vm_view_members',
	'vm_manage_finances',
	'vm_send_emails',
	'vm_manage_settings',
	'vm_export_data',
	'vm_import_data',
	'vm_delete_members',
	'vm_manage_events',
	'vm_view_events',
	'vm_manage_documents',
];

$administrator = get_role( 'administrator' );
if ( $administrator ) {
	foreach ( $capabilities as $cap ) {
		$administrator->remove_cap( $cap );
	}
}

// Cron aufräumen.
$timestamp = wp_next_scheduled( 'vm_process_email_queue' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'vm_process_email_queue' );
}
$anniv_ts = wp_next_scheduled( 'vm_anniversary_daily' );
if ( $anniv_ts ) {
	wp_unschedule_event( $anniv_ts, 'vm_anniversary_daily' );
}
