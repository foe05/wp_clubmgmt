<?php
/**
 * DSGVO-Funktionen.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_GDPR
 */
class VM_GDPR {

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_gdpr_export', [ __CLASS__, 'handle_export' ] );
		add_action( 'admin_post_vm_gdpr_anonymize', [ __CLASS__, 'handle_anonymize' ] );
	}

	/**
	 * Render-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_view_members' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}

		$retention = (int) get_option( 'vm_retention_years', 10 );
		$expired   = self::expired_members( $retention );

		include VM_PLUGIN_DIR . 'admin/views/gdpr.php';
	}

	/**
	 * Mitglieder, deren Aufbewahrungsfrist nach Austritt abgelaufen ist.
	 *
	 * @param int $retention_years Aufbewahrungsfrist in Jahren.
	 * @return array
	 */
	public static function expired_members( int $retention_years ): array {
		global $wpdb;
		$table     = VM_Members::table();
		$threshold = gmdate( 'Y-m-d', strtotime( '-' . $retention_years . ' years' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'former' AND exit_date IS NOT NULL AND exit_date <= %s ORDER BY exit_date", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$threshold
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Datenauskunft (HTML).
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		if ( ! current_user_can( 'vm_view_members' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id = isset( $_GET['member_id'] ) ? (int) $_GET['member_id'] : 0;
		check_admin_referer( 'vm_gdpr_export_' . $id );

		$member = VM_Members::get( $id );
		if ( ! $member ) {
			wp_die( esc_html__( 'Mitglied nicht gefunden.', 'vereinsmanager' ) );
		}

		global $wpdb;
		$payments_table = VM_Payments::table();
		$payments       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$payments_table} WHERE member_id = %d ORDER BY year DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		$email_log_table = $wpdb->prefix . 'vm_email_log';
		$emails          = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, subject, sent_at, status FROM {$email_log_table} WHERE member_id = %d ORDER BY sent_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="datenauskunft-' . $id . '.html"' );

		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Datenauskunft</title>';
		echo '<style>body{font-family:sans-serif;max-width:800px;margin:2em auto;padding:1em;}h1,h2{border-bottom:1px solid #ccc;}table{border-collapse:collapse;width:100%;}td,th{border:1px solid #ccc;padding:6px;text-align:left;}</style>';
		echo '</head><body>';
		echo '<h1>' . esc_html__( 'Datenauskunft gemäß Art. 15 DSGVO', 'vereinsmanager' ) . '</h1>';
		echo '<p>' . esc_html__( 'Stand:', 'vereinsmanager' ) . ' ' . esc_html( wp_date( 'd.m.Y H:i' ) ) . '</p>';

		$date_fields     = [ 'date_of_birth', 'entry_date', 'exit_date', 'sepa_mandate_date', 'consent_data_processing_date', 'consent_email_date' ];
		$datetime_fields = [ 'created_at', 'updated_at' ];

		echo '<h2>' . esc_html__( 'Stammdaten', 'vereinsmanager' ) . '</h2>';
		echo '<table>';
		foreach ( $member as $key => $value ) {
			$display = (string) $value;
			if ( $display && in_array( $key, $date_fields, true ) ) {
				$display = mysql2date( 'd.m.Y', $display );
			} elseif ( $display && in_array( $key, $datetime_fields, true ) ) {
				$display = mysql2date( 'd.m.Y H:i', $display );
			}
			echo '<tr><th>' . esc_html( $key ) . '</th><td>' . esc_html( $display ) . '</td></tr>';
		}
		echo '</table>';

		echo '<h2>' . esc_html__( 'Beiträge / Zahlungen', 'vereinsmanager' ) . '</h2>';
		if ( $payments ) {
			echo '<table><tr><th>Jahr</th><th>Betrag</th><th>Status</th><th>Datum</th><th>Methode</th></tr>';
			foreach ( $payments as $p ) {
				$pay_date = $p['payment_date'] ? mysql2date( 'd.m.Y', (string) $p['payment_date'] ) : '—';
				echo '<tr><td>' . (int) $p['year'] . '</td><td>' . esc_html( $p['amount'] ) . '</td><td>' . esc_html( $p['status'] ) . '</td><td>' . esc_html( $pay_date ) . '</td><td>' . esc_html( $p['payment_method'] ) . '</td></tr>';
			}
			echo '</table>';
		} else {
			echo '<p>—</p>';
		}

		echo '<h2>' . esc_html__( 'E-Mail-Verlauf', 'vereinsmanager' ) . '</h2>';
		if ( $emails ) {
			echo '<table><tr><th>ID</th><th>Betreff</th><th>Gesendet</th><th>Status</th></tr>';
			foreach ( $emails as $e ) {
				$sent = $e['sent_at'] ? mysql2date( 'd.m.Y H:i', (string) $e['sent_at'] ) : '—';
				echo '<tr><td>' . (int) $e['id'] . '</td><td>' . esc_html( (string) $e['subject'] ) . '</td><td>' . esc_html( $sent ) . '</td><td>' . esc_html( $e['status'] ) . '</td></tr>';
			}
			echo '</table>';
		} else {
			echo '<p>—</p>';
		}

		echo '</body></html>';
		exit;
	}

	/**
	 * Mitglied anonymisieren.
	 *
	 * @return void
	 */
	public static function handle_anonymize(): void {
		if ( ! current_user_can( 'vm_delete_members' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id = isset( $_GET['member_id'] ) ? (int) $_GET['member_id'] : 0;
		check_admin_referer( 'vm_gdpr_anonymize_' . $id );

		global $wpdb;
		$table = VM_Members::table();

		$wpdb->update(
			$table,
			[
				'salutation'                   => '',
				'title'                        => '',
				'first_name'                   => 'Anonym',
				'last_name'                    => 'Anonym',
				'organization'                 => '',
				'street'                       => '',
				'postal_code'                  => '',
				'city'                         => '',
				'email'                        => '',
				'phone'                        => '',
				'date_of_birth'                => null,
				'bank_iban'                    => '',
				'bank_bic'                     => '',
				'bank_account_holder'          => '',
				'sepa_mandate'                 => 0,
				'sepa_mandate_reference'       => '',
				'sepa_mandate_date'            => null,
				'consent_data_processing'      => 0,
				'consent_email'                => 0,
				'notes'                        => '[anonymisiert ' . current_time( 'mysql' ) . ']',
				'status'                       => 'former',
				'updated_at'                   => current_time( 'mysql' ),
			],
			[ 'id' => $id ]
		);

		wp_safe_redirect(
			add_query_arg(
				[
					'page'      => 'vm-gdpr',
					'vm_notice' => 'anonymized',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
