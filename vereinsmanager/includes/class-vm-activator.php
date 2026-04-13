<?php
/**
 * Plugin-Aktivierung: Tabellen anlegen, Rollen erstellen, Defaults setzen.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Activator
 */
class VM_Activator {

	/**
	 * Wird bei Plugin-Aktivierung aufgerufen.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		VM_Roles::add_roles();
		self::set_default_options();
		self::schedule_cron();
		self::prepare_upload_dir();

		VM_Central_Logger::log(
			'plugin_activated',
			[
				'wp_version'  => get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
			]
		);
	}

	/**
	 * Defaults für Plugin-Optionen setzen.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = [
			'vm_club_name'                => '',
			'vm_club_street'              => '',
			'vm_club_postal_code'         => '',
			'vm_club_city'                => '',
			'vm_club_tax_number'          => '',
			'vm_club_tax_office'          => '',
			'vm_club_exemption_date'      => '',
			'vm_creditor_id'              => '',
			'vm_fee_standard'             => '60.00',
			'vm_fee_reduced'              => '30.00',
			'vm_member_number_prefix'     => 'FV',
			'vm_retention_years'          => '10',
			'vm_email_from_name'          => get_option( 'blogname' ),
			'vm_email_from_address'       => get_option( 'admin_email' ),
			'vm_logging_api_url'          => 'https://log.broetzens.de',
			'vm_logging_api_key'          => '',
			'vm_logging_enabled'          => '0',
			'vm_mv_ladungsfrist_days'     => '14',
			'vm_board_role_keys'          => 'vorstand,vorsitz,kassenwart,schriftfuehrer',
			'vm_anniversary_notify'       => '0',
			'vm_anniversary_days_ahead'   => '30',
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Cron-Event für E-Mail-Queue planen.
	 *
	 * @return void
	 */
	private static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'vm_process_email_queue' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'vm_process_email_queue' );
		}
		if ( ! wp_next_scheduled( 'vm_anniversary_daily' ) ) {
			wp_schedule_event( time() + 120, 'daily', 'vm_anniversary_daily' );
		}
	}

	/**
	 * Upload-Zielverzeichnis für Dokumente anlegen und mit .htaccess schützen.
	 *
	 * @return void
	 */
	private static function prepare_upload_dir(): void {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'vereinsmanager';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Order allow,deny\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Datenbanktabellen anlegen.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$members         = $wpdb->prefix . 'vm_members';
		$payments        = $wpdb->prefix . 'vm_payments';
		$email_log       = $wpdb->prefix . 'vm_email_log';
		$email_queue     = $wpdb->prefix . 'vm_email_queue';
		$status_log      = $wpdb->prefix . 'vm_status_log';
		$events          = $wpdb->prefix . 'vm_events';
		$event_attendees = $wpdb->prefix . 'vm_event_attendees';
		$event_agenda    = $wpdb->prefix . 'vm_event_agenda';
		$event_protocols = $wpdb->prefix . 'vm_event_protocols';
		$member_roles    = $wpdb->prefix . 'vm_member_roles';
		$documents       = $wpdb->prefix . 'vm_documents';

		$sql_members = "CREATE TABLE {$members} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_number VARCHAR(20) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'prospect',
			salutation VARCHAR(20) DEFAULT NULL,
			title VARCHAR(50) DEFAULT NULL,
			first_name VARCHAR(100) DEFAULT NULL,
			last_name VARCHAR(100) DEFAULT NULL,
			organization VARCHAR(200) DEFAULT NULL,
			street VARCHAR(200) DEFAULT NULL,
			postal_code VARCHAR(10) DEFAULT NULL,
			city VARCHAR(100) DEFAULT NULL,
			country VARCHAR(2) NOT NULL DEFAULT 'DE',
			email VARCHAR(200) DEFAULT NULL,
			phone VARCHAR(50) DEFAULT NULL,
			date_of_birth DATE DEFAULT NULL,
			entry_date DATE DEFAULT NULL,
			exit_date DATE DEFAULT NULL,
			exit_reason VARCHAR(200) DEFAULT NULL,
			membership_type VARCHAR(20) NOT NULL DEFAULT 'standard',
			fee_amount DECIMAL(10,2) DEFAULT NULL,
			bank_iban VARCHAR(34) DEFAULT NULL,
			bank_bic VARCHAR(11) DEFAULT NULL,
			bank_account_holder VARCHAR(200) DEFAULT NULL,
			sepa_mandate TINYINT(1) NOT NULL DEFAULT 0,
			sepa_mandate_reference VARCHAR(35) DEFAULT NULL,
			sepa_mandate_date DATE DEFAULT NULL,
			consent_data_processing TINYINT(1) NOT NULL DEFAULT 0,
			consent_data_processing_date DATE DEFAULT NULL,
			consent_email TINYINT(1) NOT NULL DEFAULT 0,
			consent_email_date DATE DEFAULT NULL,
			notes TEXT,
			created_at DATETIME DEFAULT NULL,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY member_number (member_number),
			KEY status (status),
			KEY email (email),
			KEY last_name (last_name)
		) {$charset_collate};";

		$sql_payments = "CREATE TABLE {$payments} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			year SMALLINT(4) NOT NULL,
			amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			payment_date DATE DEFAULT NULL,
			payment_method VARCHAR(20) NOT NULL DEFAULT 'transfer',
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			notes TEXT,
			created_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY member_id (member_id),
			KEY year (year),
			KEY status (status)
		) {$charset_collate};";

		$sql_email_log = "CREATE TABLE {$email_log} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			subject VARCHAR(255) DEFAULT NULL,
			body LONGTEXT,
			sent_at DATETIME DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'sent',
			PRIMARY KEY  (id),
			KEY member_id (member_id)
		) {$charset_collate};";

		$sql_email_queue = "CREATE TABLE {$email_queue} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			subject VARCHAR(255) DEFAULT NULL,
			body LONGTEXT,
			created_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY member_id (member_id)
		) {$charset_collate};";

		$sql_status_log = "CREATE TABLE {$status_log} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			old_status VARCHAR(20) DEFAULT NULL,
			new_status VARCHAR(20) DEFAULT NULL,
			changed_at DATETIME DEFAULT NULL,
			user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY member_id (member_id)
		) {$charset_collate};";

		$sql_events = "CREATE TABLE {$events} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(30) NOT NULL DEFAULT 'other',
			title VARCHAR(200) DEFAULT NULL,
			description LONGTEXT,
			location VARCHAR(200) DEFAULT NULL,
			start_datetime DATETIME DEFAULT NULL,
			end_datetime DATETIME DEFAULT NULL,
			registration_deadline DATETIME DEFAULT NULL,
			max_attendees INT UNSIGNED DEFAULT NULL,
			invitation_group VARCHAR(30) NOT NULL DEFAULT 'active',
			custom_role_key VARCHAR(50) DEFAULT NULL,
			extra_fields LONGTEXT,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_by BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT NULL,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY status (status),
			KEY start_datetime (start_datetime)
		) {$charset_collate};";

		$sql_event_attendees = "CREATE TABLE {$event_attendees} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL,
			member_id BIGINT(20) UNSIGNED DEFAULT NULL,
			guest_name VARCHAR(200) DEFAULT NULL,
			guest_email VARCHAR(200) DEFAULT NULL,
			rsvp_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			rsvp_token VARCHAR(64) DEFAULT NULL,
			rsvp_responded_at DATETIME DEFAULT NULL,
			plus_one_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
			plus_one_names TEXT,
			food_choice VARCHAR(100) DEFAULT NULL,
			dietary_notes TEXT,
			voting_right TINYINT(1) NOT NULL DEFAULT 0,
			attended TINYINT(1) NOT NULL DEFAULT 0,
			notes TEXT,
			created_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY rsvp_token (rsvp_token),
			UNIQUE KEY event_member (event_id, member_id),
			KEY event_id (event_id),
			KEY member_id (member_id)
		) {$charset_collate};";

		$sql_event_agenda = "CREATE TABLE {$event_agenda} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL,
			position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(255) DEFAULT NULL,
			description TEXT,
			is_resolution TINYINT(1) NOT NULL DEFAULT 0,
			votes_yes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			votes_no SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			votes_abstain SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			resolution_text TEXT,
			PRIMARY KEY  (id),
			KEY event_id (event_id)
		) {$charset_collate};";

		$sql_event_protocols = "CREATE TABLE {$event_protocols} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL,
			protocol_text LONGTEXT,
			signed_by VARCHAR(200) DEFAULT NULL,
			created_by BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT NULL,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id)
		) {$charset_collate};";

		$sql_member_roles = "CREATE TABLE {$member_roles} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			role_key VARCHAR(50) NOT NULL,
			role_label VARCHAR(100) DEFAULT NULL,
			start_date DATE DEFAULT NULL,
			end_date DATE DEFAULT NULL,
			notes TEXT,
			created_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY member_id (member_id),
			KEY role_key (role_key)
		) {$charset_collate};";

		$sql_documents = "CREATE TABLE {$documents} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) DEFAULT NULL,
			category VARCHAR(50) NOT NULL DEFAULT 'sonstiges',
			file_path VARCHAR(500) DEFAULT NULL,
			mime_type VARCHAR(100) DEFAULT NULL,
			file_size BIGINT UNSIGNED DEFAULT NULL,
			member_id BIGINT(20) UNSIGNED DEFAULT NULL,
			event_id BIGINT(20) UNSIGNED DEFAULT NULL,
			visibility VARCHAR(20) NOT NULL DEFAULT 'admin',
			uploaded_by BIGINT(20) UNSIGNED DEFAULT NULL,
			uploaded_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY category (category),
			KEY member_id (member_id),
			KEY event_id (event_id)
		) {$charset_collate};";

		dbDelta( $sql_members );
		dbDelta( $sql_payments );
		dbDelta( $sql_email_log );
		dbDelta( $sql_email_queue );
		dbDelta( $sql_status_log );
		dbDelta( $sql_events );
		dbDelta( $sql_event_attendees );
		dbDelta( $sql_event_agenda );
		dbDelta( $sql_event_protocols );
		dbDelta( $sql_member_roles );
		dbDelta( $sql_documents );
	}
}
