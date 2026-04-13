<?php
/**
 * Plugin Name:       Vereinsmanager
 * Plugin URI:        https://github.com/foe05/wp_clubmgmt
 * Description:       Vollständige Vereinsverwaltung für gemeinnützige Fördervereine: CRM, Beiträge, SEPA, Zuwendungsbestätigungen, E-Mails, DSGVO und Dashboard.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            BroetzensTools
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vereinsmanager
 * Domain Path:       /languages
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin-Konstanten.
 */
define( 'VM_VERSION', '1.0.0' );
define( 'VM_PLUGIN_FILE', __FILE__ );
define( 'VM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Composer-Autoloader (TCPDF) – falls vorhanden.
 */
if ( file_exists( VM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once VM_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Klassen einbinden.
 */
require_once VM_PLUGIN_DIR . 'includes/class-vm-central-logger.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-roles.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-activator.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-deactivator.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-members.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-member-roles.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-payments.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-email.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-import.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-export.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-pdf.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-gdpr.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-dashboard.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-settings.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-events.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-event-attendees.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-event-frontend.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-event-protocol.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-documents.php';
require_once VM_PLUGIN_DIR . 'includes/class-vm-anniversaries.php';

/**
 * Activation / Deactivation Hooks.
 */
register_activation_hook( __FILE__, [ 'VM_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'VM_Deactivator', 'deactivate' ] );

/**
 * Plugin-Initialisierung.
 */
function vm_init() {
	load_plugin_textdomain( 'vereinsmanager', false, dirname( VM_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'plugins_loaded', 'vm_init' );

/**
 * Admin-Menü registrieren.
 */
function vm_admin_menu() {
	$capability = 'vm_view_members';

	add_menu_page(
		__( 'Vereinsmanager', 'vereinsmanager' ),
		__( 'Vereinsmanager', 'vereinsmanager' ),
		$capability,
		'vereinsmanager',
		[ 'VM_Dashboard', 'render' ],
		'dashicons-groups',
		25
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Dashboard', 'vereinsmanager' ),
		__( 'Dashboard', 'vereinsmanager' ),
		$capability,
		'vereinsmanager',
		[ 'VM_Dashboard', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Mitglieder', 'vereinsmanager' ),
		__( 'Mitglieder', 'vereinsmanager' ),
		'vm_view_members',
		'vm-members',
		[ 'VM_Members', 'render_list' ]
	);

	// Versteckte Unterseite zur Bearbeitung.
	add_submenu_page(
		null,
		__( 'Mitglied bearbeiten', 'vereinsmanager' ),
		__( 'Mitglied bearbeiten', 'vereinsmanager' ),
		'vm_view_members',
		'vm-member-edit',
		[ 'VM_Members', 'render_edit' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Beiträge & Zahlungen', 'vereinsmanager' ),
		__( 'Beiträge', 'vereinsmanager' ),
		'vm_manage_finances',
		'vm-payments',
		[ 'VM_Payments', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Zuwendungsbestätigungen', 'vereinsmanager' ),
		__( 'Spendenquittungen', 'vereinsmanager' ),
		'vm_manage_finances',
		'vm-donations',
		[ 'VM_PDF', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'E-Mail-Versand', 'vereinsmanager' ),
		__( 'E-Mails', 'vereinsmanager' ),
		'vm_send_emails',
		'vm-email',
		[ 'VM_Email', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Veranstaltungen', 'vereinsmanager' ),
		__( 'Veranstaltungen', 'vereinsmanager' ),
		'vm_view_events',
		'vm-events',
		[ 'VM_Events', 'render_list' ]
	);

	// Versteckte Unterseiten für Events.
	add_submenu_page(
		null,
		__( 'Veranstaltung bearbeiten', 'vereinsmanager' ),
		__( 'Veranstaltung bearbeiten', 'vereinsmanager' ),
		'vm_view_events',
		'vm-event-edit',
		[ 'VM_Events', 'render_edit' ]
	);
	add_submenu_page(
		null,
		__( 'Teilnehmer', 'vereinsmanager' ),
		__( 'Teilnehmer', 'vereinsmanager' ),
		'vm_view_events',
		'vm-event-attendees',
		[ 'VM_Events', 'render_attendees' ]
	);
	add_submenu_page(
		null,
		__( 'Einladungen', 'vereinsmanager' ),
		__( 'Einladungen', 'vereinsmanager' ),
		'vm_manage_events',
		'vm-event-invite',
		[ 'VM_Events', 'render_invite' ]
	);
	add_submenu_page(
		null,
		__( 'Protokoll', 'vereinsmanager' ),
		__( 'Protokoll', 'vereinsmanager' ),
		'vm_view_events',
		'vm-event-protocol',
		[ 'VM_Event_Protocol', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Dokumente', 'vereinsmanager' ),
		__( 'Dokumente', 'vereinsmanager' ),
		'vm_manage_documents',
		'vm-documents',
		[ 'VM_Documents', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Import', 'vereinsmanager' ),
		__( 'Import', 'vereinsmanager' ),
		'vm_import_data',
		'vm-import',
		[ 'VM_Import', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Export', 'vereinsmanager' ),
		__( 'Export', 'vereinsmanager' ),
		'vm_export_data',
		'vm-export',
		[ 'VM_Export', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'DSGVO', 'vereinsmanager' ),
		__( 'DSGVO', 'vereinsmanager' ),
		'vm_view_members',
		'vm-gdpr',
		[ 'VM_GDPR', 'render' ]
	);

	add_submenu_page(
		'vereinsmanager',
		__( 'Einstellungen', 'vereinsmanager' ),
		__( 'Einstellungen', 'vereinsmanager' ),
		'vm_manage_settings',
		'vm-settings',
		[ 'VM_Settings', 'render' ]
	);
}
add_action( 'admin_menu', 'vm_admin_menu' );

/**
 * Admin-Assets laden.
 */
function vm_admin_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'vereinsmanager' ) && false === strpos( (string) $hook, 'vm-' ) ) {
		return;
	}

	wp_enqueue_style(
		'vm-admin',
		VM_PLUGIN_URL . 'admin/css/vm-admin.css',
		[],
		VM_VERSION
	);

	wp_enqueue_script(
		'vm-admin',
		VM_PLUGIN_URL . 'admin/js/vm-admin.js',
		[ 'jquery' ],
		VM_VERSION,
		true
	);

	// E-Mail-Editor-Seite -> WordPress Editor.
	if ( false !== strpos( (string) $hook, 'vm-email' ) ) {
		wp_enqueue_editor();
	}
}
add_action( 'admin_enqueue_scripts', 'vm_admin_assets' );

/**
 * Settings, Cron-Hooks und Action-Handler initialisieren.
 */
function vm_register_handlers() {
	VM_Settings::register();
	VM_Members::register();
	VM_Member_Roles::register();
	VM_Payments::register();
	VM_Email::register();
	VM_Import::register();
	VM_Export::register();
	VM_PDF::register();
	VM_GDPR::register();
	VM_Events::register();
	VM_Event_Attendees::register();
	VM_Event_Frontend::register();
	VM_Event_Protocol::register();
	VM_Documents::register();
	VM_Anniversaries::register();
}
add_action( 'init', 'vm_register_handlers' );

/**
 * Cron-Hook für E-Mail-Queue.
 */
add_action( 'vm_process_email_queue', [ 'VM_Email', 'process_queue' ] );

/**
 * Globaler Error-Handler für Plugin-Fehler.
 *
 * @param string $message Fehlermeldung.
 * @param string $context Kontext.
 */
function vm_log_error( string $message, string $context = '' ): void {
	error_log( '[Vereinsmanager] ' . $context . ': ' . $message );
	VM_Central_Logger::log(
		'plugin_error',
		[
			'error'   => $message,
			'context' => $context,
		]
	);
}
