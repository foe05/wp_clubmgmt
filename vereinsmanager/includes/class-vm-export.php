<?php
/**
 * CSV-Export.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Export
 */
class VM_Export {

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_export_csv', [ __CLASS__, 'handle_export' ] );
	}

	/**
	 * Render-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_export_data' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}

		$fields = self::available_fields();

		include VM_PLUGIN_DIR . 'admin/views/export.php';
	}

	/**
	 * Verfügbare Felder.
	 *
	 * @return array<string,string>
	 */
	public static function available_fields(): array {
		return [
			'member_number'   => __( 'Mitgliedsnummer', 'vereinsmanager' ),
			'status'          => __( 'Status', 'vereinsmanager' ),
			'salutation'      => __( 'Anrede', 'vereinsmanager' ),
			'title'           => __( 'Titel', 'vereinsmanager' ),
			'first_name'      => __( 'Vorname', 'vereinsmanager' ),
			'last_name'       => __( 'Nachname', 'vereinsmanager' ),
			'organization'    => __( 'Organisation', 'vereinsmanager' ),
			'street'          => __( 'Straße', 'vereinsmanager' ),
			'postal_code'     => __( 'PLZ', 'vereinsmanager' ),
			'city'            => __( 'Ort', 'vereinsmanager' ),
			'country'         => __( 'Land', 'vereinsmanager' ),
			'email'           => __( 'E-Mail', 'vereinsmanager' ),
			'phone'           => __( 'Telefon', 'vereinsmanager' ),
			'date_of_birth'   => __( 'Geburtsdatum', 'vereinsmanager' ),
			'entry_date'      => __( 'Eintrittsdatum', 'vereinsmanager' ),
			'exit_date'       => __( 'Austrittsdatum', 'vereinsmanager' ),
			'membership_type' => __( 'Beitragsart', 'vereinsmanager' ),
			'fee_amount'      => __( 'Beitrag', 'vereinsmanager' ),
			'bank_iban'       => 'IBAN',
			'bank_bic'        => 'BIC',
		];
	}

	/**
	 * Export-Handler.
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		if ( ! current_user_can( 'vm_export_data' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_export_csv' );

		$status = isset( $_POST['vm_status'] ) ? sanitize_text_field( wp_unslash( $_POST['vm_status'] ) ) : '';
		$type   = isset( $_POST['vm_type'] ) ? sanitize_text_field( wp_unslash( $_POST['vm_type'] ) ) : '';
		$fields = isset( $_POST['vm_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['vm_fields'] ) ) : [];

		$available = self::available_fields();
		$fields    = array_filter( $fields, function ( $f ) use ( $available ) {
			return isset( $available[ $f ] );
		} );
		if ( empty( $fields ) ) {
			$fields = array_keys( $available );
		}

		$result = VM_Members::query(
			[
				'status'          => $status,
				'membership_type' => $type,
				'per_page'        => 100000,
				'paged'           => 1,
			]
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mitglieder-export-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );

		// Header.
		$header = [];
		foreach ( $fields as $f ) {
			$header[] = $available[ $f ];
		}
		fputcsv( $out, $header, ';' );

		foreach ( $result['items'] as $member ) {
			$row = [];
			foreach ( $fields as $f ) {
				$row[] = (string) ( $member[ $f ] ?? '' );
			}
			fputcsv( $out, $row, ';' );
		}
		fclose( $out );

		VM_Central_Logger::log( 'csv_export', [ 'count' => count( $result['items'] ) ] );

		exit;
	}
}
