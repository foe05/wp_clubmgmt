<?php
/**
 * Beitragsverwaltung.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Payments
 */
class VM_Payments {

	/**
	 * Tabellenname.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vm_payments';
	}

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_generate_fees', [ __CLASS__, 'handle_generate_fees' ] );
		add_action( 'admin_post_vm_mark_paid', [ __CLASS__, 'handle_mark_paid' ] );
		add_action( 'admin_post_vm_sepa_export', [ __CLASS__, 'handle_sepa_export' ] );
	}

	/**
	 * Render Payments-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_manage_finances' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}

		$year   = isset( $_GET['vm_year'] ) ? (int) $_GET['vm_year'] : (int) gmdate( 'Y' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['vm_pay_status'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_pay_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$payments = self::query(
			[
				'year'   => $year,
				'status' => $status,
			]
		);

		$totals = self::totals( $year );

		include VM_PLUGIN_DIR . 'admin/views/payments.php';
	}

	/**
	 * Zahlungen abfragen.
	 *
	 * @param array $args Filter.
	 * @return array
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;
		$table   = self::table();
		$members = VM_Members::table();

		$where  = 'WHERE 1=1';
		$params = [];

		if ( ! empty( $args['year'] ) ) {
			$where   .= ' AND p.year = %d';
			$params[] = (int) $args['year'];
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], [ 'open', 'paid', 'cancelled' ], true ) ) {
			$where   .= ' AND p.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['member_id'] ) ) {
			$where   .= ' AND p.member_id = %d';
			$params[] = (int) $args['member_id'];
		}
		if ( ! empty( $args['only_sepa'] ) ) {
			$where .= ' AND m.sepa_mandate = 1';
		}

		$sql = "SELECT p.*, m.first_name, m.last_name, m.organization, m.member_number, m.email,
				m.bank_iban, m.bank_bic, m.bank_account_holder, m.sepa_mandate, m.sepa_mandate_reference
				FROM {$table} p LEFT JOIN {$members} m ON m.id = p.member_id
				{$where} ORDER BY m.last_name, m.first_name";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		return $rows ?: [];
	}

	/**
	 * Soll/Ist Summen für ein Jahr.
	 *
	 * @param int $year Jahr.
	 * @return array{soll: float, ist: float, open: float, count_open: int, count_paid: int}
	 */
	public static function totals( int $year ): array {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS c, SUM(amount) AS s FROM {$table} WHERE year = %d GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$year
			),
			ARRAY_A
		);

		$out = [
			'soll'       => 0.0,
			'ist'        => 0.0,
			'open'       => 0.0,
			'count_open' => 0,
			'count_paid' => 0,
		];

		foreach ( (array) $rows as $row ) {
			$sum = (float) $row['s'];
			$out['soll'] += $sum;
			if ( 'paid' === $row['status'] ) {
				$out['ist']        += $sum;
				$out['count_paid']  = (int) $row['c'];
			} elseif ( 'open' === $row['status'] ) {
				$out['open']       += $sum;
				$out['count_open']  = (int) $row['c'];
			}
		}

		return $out;
	}

	/**
	 * Standard-Beitragshöhe für ein Mitglied.
	 *
	 * @param array $member Mitglied.
	 * @return float
	 */
	public static function fee_for( array $member ): float {
		if ( isset( $member['fee_amount'] ) && null !== $member['fee_amount'] && '' !== $member['fee_amount'] ) {
			return (float) $member['fee_amount'];
		}
		$key = 'reduced' === ( $member['membership_type'] ?? 'standard' ) ? 'vm_fee_reduced' : 'vm_fee_standard';
		return (float) get_option( $key, 0 );
	}

	/**
	 * Beiträge für ein Jahr generieren.
	 *
	 * @param int $year Jahr.
	 * @return int Anzahl erzeugter Forderungen.
	 */
	public static function generate_for_year( int $year ): int {
		global $wpdb;
		$table = self::table();

		$members_table = VM_Members::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$members = $wpdb->get_results( "SELECT * FROM {$members_table} WHERE status = 'active'", ARRAY_A );

		$count = 0;
		foreach ( (array) $members as $member ) {
			// Doppel-Generierung verhindern.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE member_id = %d AND year = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $member['id'],
					$year
				)
			);
			if ( $exists ) {
				continue;
			}

			$amount = self::fee_for( $member );
			if ( $amount <= 0 ) {
				continue;
			}

			$wpdb->insert(
				$table,
				[
					'member_id'      => (int) $member['id'],
					'year'           => $year,
					'amount'         => $amount,
					'payment_method' => $member['sepa_mandate'] ? 'sepa' : 'transfer',
					'status'         => 'open',
					'created_at'     => current_time( 'mysql' ),
				]
			);

			VM_Central_Logger::log(
				'payment_created',
				[
					'member_id' => (int) $member['id'],
					'year'      => $year,
					'amount'    => $amount,
				]
			);

			++$count;
		}

		VM_Central_Logger::log( 'fees_generated', [ 'year' => $year, 'count' => $count ] );
		return $count;
	}

	/**
	 * Handler: Beiträge generieren.
	 *
	 * @return void
	 */
	public static function handle_generate_fees(): void {
		if ( ! current_user_can( 'vm_manage_finances' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_generate_fees' );

		$year  = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) gmdate( 'Y' );
		$count = self::generate_for_year( $year );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'      => 'vm-payments',
					'vm_year'   => $year,
					'vm_notice' => 'fees_generated',
					'vm_count'  => $count,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handler: Zahlung als bezahlt markieren.
	 *
	 * @return void
	 */
	public static function handle_mark_paid(): void {
		if ( ! current_user_can( 'vm_manage_finances' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id = isset( $_GET['payment_id'] ) ? (int) $_GET['payment_id'] : 0;
		check_admin_referer( 'vm_mark_paid_' . $id );

		global $wpdb;
		$wpdb->update(
			self::table(),
			[
				'status'       => 'paid',
				'payment_date' => current_time( 'Y-m-d' ),
			],
			[ 'id' => $id ]
		);

		VM_Central_Logger::log( 'payment_status_changed', [ 'payment_id' => $id, 'new_status' => 'paid' ] );

		$year = isset( $_GET['year'] ) ? (int) $_GET['year'] : (int) gmdate( 'Y' );
		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => 'vm-payments',
					'vm_year' => $year,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handler: SEPA-CSV-Export.
	 *
	 * @return void
	 */
	public static function handle_sepa_export(): void {
		if ( ! current_user_can( 'vm_export_data' ) && ! current_user_can( 'vm_manage_finances' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_sepa_export' );

		$year = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) gmdate( 'Y' );
		$rows = self::query(
			[
				'year'      => $year,
				'status'    => 'open',
				'only_sepa' => true,
			]
		);

		$club_name = (string) get_option( 'vm_club_name', '' );
		$total     = 0.0;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sepa-' . $year . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM für Excel.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv(
			$out,
			[
				__( 'Kontoinhaber', 'vereinsmanager' ),
				'IBAN',
				'BIC',
				__( 'Betrag', 'vereinsmanager' ),
				__( 'Mandatsreferenz', 'vereinsmanager' ),
				__( 'Verwendungszweck', 'vereinsmanager' ),
			],
			';'
		);

		foreach ( $rows as $row ) {
			$holder = $row['bank_account_holder'] ?: trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
			$amount = number_format( (float) $row['amount'], 2, ',', '' );
			$total += (float) $row['amount'];

			fputcsv(
				$out,
				[
					$holder,
					$row['bank_iban'],
					$row['bank_bic'],
					$amount,
					$row['sepa_mandate_reference'],
					sprintf( __( 'Mitgliedsbeitrag %1$d – %2$s', 'vereinsmanager' ), (int) $row['year'], $club_name ),
				],
				';'
			);
		}
		fclose( $out );

		VM_Central_Logger::log(
			'sepa_export',
			[
				'count'        => count( $rows ),
				'total_amount' => $total,
			]
		);

		exit;
	}
}
