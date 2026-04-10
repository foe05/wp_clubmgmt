<?php
/**
 * CSV-Import.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Import
 */
class VM_Import {

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_import_upload', [ __CLASS__, 'handle_upload' ] );
		add_action( 'admin_post_vm_import_run', [ __CLASS__, 'handle_run' ] );
	}

	/**
	 * Importierbare Felder.
	 *
	 * @return array<string,string>
	 */
	public static function importable_fields(): array {
		return [
			'salutation'      => __( 'Anrede', 'vereinsmanager' ),
			'title'           => __( 'Titel', 'vereinsmanager' ),
			'first_name'      => __( 'Vorname', 'vereinsmanager' ),
			'last_name'       => __( 'Nachname', 'vereinsmanager' ),
			'organization'    => __( 'Organisation', 'vereinsmanager' ),
			'street'          => __( 'Straße', 'vereinsmanager' ),
			'postal_code'     => __( 'PLZ', 'vereinsmanager' ),
			'city'            => __( 'Ort', 'vereinsmanager' ),
			'country'         => __( 'Land (ISO)', 'vereinsmanager' ),
			'email'           => __( 'E-Mail', 'vereinsmanager' ),
			'phone'           => __( 'Telefon', 'vereinsmanager' ),
			'date_of_birth'   => __( 'Geburtsdatum', 'vereinsmanager' ),
			'entry_date'      => __( 'Eintrittsdatum', 'vereinsmanager' ),
			'membership_type' => __( 'Beitragsart', 'vereinsmanager' ),
			'fee_amount'      => __( 'Beitrag', 'vereinsmanager' ),
			'bank_iban'       => 'IBAN',
			'bank_bic'        => 'BIC',
			'bank_account_holder' => __( 'Kontoinhaber', 'vereinsmanager' ),
			'status'          => __( 'Status', 'vereinsmanager' ),
		];
	}

	/**
	 * Render-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_import_data' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}

		$preview_data = get_transient( 'vm_import_preview_' . get_current_user_id() );

		include VM_PLUGIN_DIR . 'admin/views/import.php';
	}

	/**
	 * Upload-Handler: liest CSV ein und speichert in Transient für Mapping.
	 *
	 * @return void
	 */
	public static function handle_upload(): void {
		if ( ! current_user_can( 'vm_import_data' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_import_upload' );

		if ( empty( $_FILES['vm_csv']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'vm-import', 'vm_notice' => 'no_file' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$file = $_FILES['vm_csv']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$rows = [];
		$h    = fopen( $file, 'r' );
		if ( ! $h ) {
			wp_die( esc_html__( 'CSV-Datei konnte nicht geöffnet werden.', 'vereinsmanager' ) );
		}

		$delimiter = isset( $_POST['vm_delimiter'] ) && ';' === $_POST['vm_delimiter'] ? ';' : ',';
		$header    = fgetcsv( $h, 0, $delimiter );
		while ( ( $row = fgetcsv( $h, 0, $delimiter ) ) !== false ) {
			$rows[] = $row;
			if ( count( $rows ) >= 1000 ) {
				break;
			}
		}
		fclose( $h );

		set_transient(
			'vm_import_preview_' . get_current_user_id(),
			[
				'header'    => $header,
				'rows'      => $rows,
				'delimiter' => $delimiter,
			],
			HOUR_IN_SECONDS
		);

		wp_safe_redirect( add_query_arg( [ 'page' => 'vm-import' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Run-Handler: importiert wirklich oder im Dry-Run.
	 *
	 * @return void
	 */
	public static function handle_run(): void {
		if ( ! current_user_can( 'vm_import_data' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_import_run' );

		$preview = get_transient( 'vm_import_preview_' . get_current_user_id() );
		if ( ! $preview ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'vm-import', 'vm_notice' => 'no_preview' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$mapping = isset( $_POST['vm_mapping'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['vm_mapping'] ) ) : [];
		$dry_run = ! empty( $_POST['vm_dry_run'] );
		$valid_fields = self::importable_fields();

		$imported = 0;
		$skipped  = 0;
		$errors   = 0;

		global $wpdb;
		$members_table = VM_Members::table();

		foreach ( $preview['rows'] as $row ) {
			$data = [];
			foreach ( $mapping as $col_index => $field_key ) {
				if ( '' === $field_key || ! isset( $valid_fields[ $field_key ] ) ) {
					continue;
				}
				if ( isset( $row[ (int) $col_index ] ) ) {
					$data[ $field_key ] = $row[ (int) $col_index ];
				}
			}

			if ( empty( $data['last_name'] ) && empty( $data['organization'] ) ) {
				++$skipped;
				continue;
			}

			// Duplikat-Check auf E-Mail.
			if ( ! empty( $data['email'] ) ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$members_table} WHERE email = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$data['email']
					)
				);
				if ( $exists ) {
					++$skipped;
					continue;
				}
			}

			if ( $dry_run ) {
				++$imported;
				continue;
			}

			$result = VM_Members::save( $data );
			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$imported;
			}
		}

		if ( ! $dry_run ) {
			delete_transient( 'vm_import_preview_' . get_current_user_id() );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'        => 'vm-import',
					'vm_notice'   => 'done',
					'vm_imported' => $imported,
					'vm_skipped'  => $skipped,
					'vm_errors'   => $errors,
					'vm_dry'      => $dry_run ? '1' : '0',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
