<?php
/**
 * Zuwendungsbestätigungen (PDF).
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_PDF
 */
class VM_PDF {

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_donation_single', [ __CLASS__, 'handle_single' ] );
		add_action( 'admin_post_vm_donation_bulk', [ __CLASS__, 'handle_bulk' ] );
	}

	/**
	 * Render-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_manage_finances' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}

		$year = isset( $_GET['vm_year'] ) ? (int) $_GET['vm_year'] : (int) gmdate( 'Y' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		global $wpdb;
		$members_table = VM_Members::table();
		$payments_table = VM_Payments::table();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.id, m.member_number, m.first_name, m.last_name, m.organization, SUM(p.amount) AS total
				FROM {$members_table} m
				INNER JOIN {$payments_table} p ON p.member_id = m.id
				WHERE p.status = 'paid' AND p.year = %d
				GROUP BY m.id ORDER BY m.last_name", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$year
			),
			ARRAY_A
		);

		include VM_PLUGIN_DIR . 'admin/views/donations.php';
	}

	/**
	 * Einzel-PDF Handler.
	 *
	 * @return void
	 */
	public static function handle_single(): void {
		if ( ! current_user_can( 'vm_manage_finances' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$member_id = isset( $_GET['member_id'] ) ? (int) $_GET['member_id'] : 0;
		$year      = isset( $_GET['year'] ) ? (int) $_GET['year'] : (int) gmdate( 'Y' );
		check_admin_referer( 'vm_donation_' . $member_id . '_' . $year );

		$pdf = self::generate_for_member( $member_id, $year );
		if ( is_wp_error( $pdf ) ) {
			wp_die( esc_html( $pdf->get_error_message() ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="zuwendungsbestaetigung-' . $member_id . '-' . $year . '.pdf"' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Bulk-Handler – packt alle PDFs in ein ZIP.
	 *
	 * @return void
	 */
	public static function handle_bulk(): void {
		if ( ! current_user_can( 'vm_manage_finances' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_donation_bulk' );

		$year = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) gmdate( 'Y' );

		global $wpdb;
		$members_table  = VM_Members::table();
		$payments_table = VM_Payments::table();
		$member_ids     = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT m.id FROM {$members_table} m
				INNER JOIN {$payments_table} p ON p.member_id = m.id
				WHERE p.status = 'paid' AND p.year = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$year
			)
		);

		if ( empty( $member_ids ) ) {
			wp_die( esc_html__( 'Keine Zahlungen für dieses Jahr gefunden.', 'vereinsmanager' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'PHP-ZipArchive ist nicht verfügbar.', 'vereinsmanager' ) );
		}

		$tmp = wp_tempnam( 'vm-donations-' . $year . '.zip' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'ZIP konnte nicht erstellt werden.', 'vereinsmanager' ) );
		}

		foreach ( $member_ids as $member_id ) {
			$pdf = self::generate_for_member( (int) $member_id, $year );
			if ( ! is_wp_error( $pdf ) ) {
				$zip->addFromString( 'zuwendungsbestaetigung-' . $member_id . '-' . $year . '.pdf', $pdf );
			}
		}
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="zuwendungsbestaetigungen-' . $year . '.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * PDF für ein Mitglied + Jahr generieren.
	 *
	 * @param int $member_id Mitglied.
	 * @param int $year      Jahr.
	 * @return string|WP_Error PDF-Binary oder Fehler.
	 */
	public static function generate_for_member( int $member_id, int $year ) {
		$member = VM_Members::get( $member_id );
		if ( ! $member ) {
			return new WP_Error( 'vm_no_member', __( 'Mitglied nicht gefunden.', 'vereinsmanager' ) );
		}

		global $wpdb;
		$payments_table = VM_Payments::table();
		$total          = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$payments_table} WHERE member_id = %d AND year = %d AND status = 'paid'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$member_id,
				$year
			)
		);

		if ( $total <= 0 ) {
			return new WP_Error( 'vm_no_payments', __( 'Keine bezahlten Beiträge in diesem Jahr.', 'vereinsmanager' ) );
		}

		if ( ! class_exists( 'TCPDF' ) ) {
			return new WP_Error( 'vm_no_tcpdf', __( 'TCPDF ist nicht verfügbar. Bitte "composer install" im Plugin-Verzeichnis ausführen.', 'vereinsmanager' ) );
		}

		$club_name      = (string) get_option( 'vm_club_name', '' );
		$club_street    = (string) get_option( 'vm_club_street', '' );
		$club_postal    = (string) get_option( 'vm_club_postal_code', '' );
		$club_city      = (string) get_option( 'vm_club_city', '' );
		$club_tax       = (string) get_option( 'vm_club_tax_number', '' );
		$club_office    = (string) get_option( 'vm_club_tax_office', '' );
		$exemption_date = (string) get_option( 'vm_club_exemption_date', '' );

		$pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
		$pdf->SetCreator( 'Vereinsmanager' );
		$pdf->SetAuthor( $club_name );
		$pdf->SetTitle( 'Zuwendungsbestätigung ' . $year );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->SetMargins( 20, 20, 20 );
		$pdf->AddPage();

		$pdf->SetFont( 'helvetica', 'B', 11 );
		$pdf->Cell( 0, 6, $club_name, 0, 1 );
		$pdf->SetFont( 'helvetica', '', 10 );
		$pdf->Cell( 0, 5, $club_street, 0, 1 );
		$pdf->Cell( 0, 5, trim( $club_postal . ' ' . $club_city ), 0, 1 );
		$pdf->Ln( 8 );

		$pdf->SetFont( 'helvetica', 'B', 13 );
		$pdf->Cell( 0, 8, 'Bestätigung über Geldzuwendungen / Mitgliedsbeiträge', 0, 1, 'C' );
		$pdf->SetFont( 'helvetica', '', 9 );
		$pdf->Cell( 0, 5, 'im Sinne des § 10b des Einkommensteuergesetzes an eine der in § 5 Abs. 1 Nr. 9 KStG bezeichneten Körperschaften', 0, 1, 'C' );
		$pdf->Ln( 6 );

		$pdf->SetFont( 'helvetica', '', 10 );
		$pdf->Cell( 0, 6, 'Name und Anschrift des Zuwendenden:', 0, 1 );
		$pdf->SetFont( 'helvetica', 'B', 10 );
		$pdf->Cell( 0, 6, VM_Members::format_name( $member ), 0, 1 );
		$pdf->SetFont( 'helvetica', '', 10 );
		$pdf->Cell( 0, 5, (string) ( $member['street'] ?? '' ), 0, 1 );
		$pdf->Cell( 0, 5, trim( ( $member['postal_code'] ?? '' ) . ' ' . ( $member['city'] ?? '' ) ), 0, 1 );
		$pdf->Ln( 5 );

		$pdf->SetFont( 'helvetica', '', 10 );
		$pdf->Cell( 60, 6, 'Betrag der Zuwendung:', 0, 0 );
		$pdf->SetFont( 'helvetica', 'B', 10 );
		$pdf->Cell( 0, 6, number_format( $total, 2, ',', '.' ) . ' EUR', 0, 1 );

		$pdf->SetFont( 'helvetica', '', 10 );
		$pdf->Cell( 60, 6, 'In Worten:', 0, 0 );
		$pdf->Cell( 0, 6, self::amount_in_words( $total ), 0, 1 );

		$pdf->Cell( 60, 6, 'Tag der Zuwendung:', 0, 0 );
		$pdf->Cell( 0, 6, 'Sammelbestätigung für das Jahr ' . $year, 0, 1 );
		$pdf->Ln( 4 );

		$pdf->MultiCell(
			0,
			5,
			'Es handelt sich um den Verzicht auf Erstattung von Aufwendungen: Nein.' .
			"\nEs handelt sich um Mitgliedsbeiträge im Sinne des § 10b Abs. 1 Satz 8 EStG.",
			0,
			'L'
		);
		$pdf->Ln( 3 );

		$exemption_text = $exemption_date
			? 'Wir sind wegen Förderung gemeinnütziger Zwecke nach dem Freistellungsbescheid des Finanzamts ' . $club_office . ', StNr. ' . $club_tax . ' vom ' . $exemption_date . ' nach § 5 Abs. 1 Nr. 9 KStG von der Körperschaftsteuer befreit.'
			: 'Wir sind wegen Förderung gemeinnütziger Zwecke beim Finanzamt ' . $club_office . ' unter StNr. ' . $club_tax . ' als gemeinnützig anerkannt.';

		$pdf->MultiCell( 0, 5, $exemption_text, 0, 'L' );
		$pdf->Ln( 6 );

		$pdf->MultiCell(
			0,
			5,
			'Es wird bestätigt, dass die Zuwendung nur zur Förderung der satzungsmäßigen Zwecke verwendet wird.',
			0,
			'L'
		);
		$pdf->Ln( 10 );

		$pdf->Cell( 0, 6, trim( $club_city . ', ' . wp_date( get_option( 'date_format' ) ) ), 0, 1 );
		$pdf->Ln( 12 );
		$pdf->Cell( 0, 6, '_______________________________', 0, 1 );
		$pdf->Cell( 0, 5, 'Unterschrift / Vorstand', 0, 1 );

		$pdf->Ln( 8 );
		$pdf->SetFont( 'helvetica', 'I', 8 );
		$pdf->MultiCell(
			0,
			4,
			'Hinweis: Wer vorsätzlich oder grob fahrlässig eine unrichtige Zuwendungsbestätigung erstellt oder veranlasst, dass Zuwendungen nicht zu den in der Zuwendungsbestätigung angegebenen steuerbegünstigten Zwecken verwendet werden, haftet für die entgangene Steuer (§ 10b Abs. 4 EStG).',
			0,
			'L'
		);

		VM_Central_Logger::log(
			'donation_receipt_generated',
			[
				'member_id' => $member_id,
				'year'      => $year,
			]
		);

		return $pdf->Output( 'donation.pdf', 'S' );
	}

	/**
	 * Sehr einfache "in Worten" Darstellung (Float -> Text).
	 *
	 * @param float $amount Betrag.
	 * @return string
	 */
	private static function amount_in_words( float $amount ): string {
		// Vereinfachte Variante: numerischer Text mit "Euro".
		return number_format( $amount, 2, ',', '.' ) . ' Euro';
	}
}
