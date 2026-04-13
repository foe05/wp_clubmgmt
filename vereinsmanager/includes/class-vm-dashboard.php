<?php
/**
 * Dashboard.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Dashboard
 */
class VM_Dashboard {

	/**
	 * Render-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_view_members' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}

		$counts        = VM_Members::count_by_status();
		$current_year  = (int) gmdate( 'Y' );
		$new_this_year = self::count_new_in_year( $current_year );
		$exits         = self::count_exits_in_year( $current_year );
		$totals        = VM_Payments::totals( $current_year );
		$sepa_count    = self::count_active_sepa();
		$years         = self::members_per_year( 5 );

		$upcoming_events = self::upcoming_events( 5 );
		$upcoming_bds    = class_exists( 'VM_Anniversaries' ) ? VM_Anniversaries::upcoming_birthdays( 30 ) : [];
		$upcoming_jubs   = class_exists( 'VM_Anniversaries' ) ? VM_Anniversaries::upcoming_anniversaries( 30 ) : [];

		include VM_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Nächste Veranstaltungen.
	 *
	 * @param int $limit Anzahl.
	 * @return array
	 */
	public static function upcoming_events( int $limit = 5 ): array {
		if ( ! class_exists( 'VM_Events' ) ) {
			return [];
		}
		global $wpdb;
		$table = VM_Events::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'published' AND start_datetime >= %s ORDER BY start_datetime ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' ),
				$limit
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Anzahl neu eingetretener Mitglieder im Jahr.
	 *
	 * @param int $year Jahr.
	 * @return int
	 */
	public static function count_new_in_year( int $year ): int {
		global $wpdb;
		$table = VM_Members::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE YEAR(entry_date) = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$year
			)
		);
	}

	/**
	 * Anzahl Austritte im Jahr.
	 *
	 * @param int $year Jahr.
	 * @return int
	 */
	public static function count_exits_in_year( int $year ): int {
		global $wpdb;
		$table = VM_Members::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE YEAR(exit_date) = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$year
			)
		);
	}

	/**
	 * Anzahl aktiver SEPA-Mandate.
	 *
	 * @return int
	 */
	public static function count_active_sepa(): int {
		global $wpdb;
		$table = VM_Members::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE sepa_mandate = 1 AND status = 'active'" );
	}

	/**
	 * Mitgliederzahl per Jahresende für die letzten N Jahre.
	 *
	 * @param int $n_years Anzahl Jahre.
	 * @return array<int,int>
	 */
	public static function members_per_year( int $n_years ): array {
		global $wpdb;
		$table  = VM_Members::table();
		$years  = [];
		$year   = (int) gmdate( 'Y' );
		for ( $i = $n_years - 1; $i >= 0; $i-- ) {
			$y      = $year - $i;
			$end    = $y . '-12-31';
			// Aktiv = eingetreten <= Jahresende AND (kein Austritt OR exit_date > Jahresende).
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE entry_date IS NOT NULL AND entry_date <= %s AND (exit_date IS NULL OR exit_date > %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$end,
					$end
				)
			);
			$years[ $y ] = $count;
		}
		return $years;
	}
}
