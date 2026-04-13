<?php
/**
 * Jubiläen und Geburtstage.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Anniversaries
 */
class VM_Anniversaries {

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'vm_anniversary_daily', [ __CLASS__, 'cron_notify' ] );
	}

	/**
	 * Geburtstage in den nächsten N Tagen.
	 *
	 * @param int $days_ahead Tage.
	 * @return array
	 */
	public static function upcoming_birthdays( int $days_ahead = 30 ): array {
		global $wpdb;
		$table = VM_Members::table();
		// Wir filtern clientseitig, weil MONTH/DAY-Vergleiche mit Jahreswechsel komplex sind.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT id, first_name, last_name, organization, date_of_birth FROM {$table}
			WHERE date_of_birth IS NOT NULL AND status = 'active'",
			ARRAY_A
		);

		$today   = new DateTimeImmutable( current_time( 'Y-m-d' ) );
		$limit   = $today->modify( '+' . $days_ahead . ' days' );
		$results = [];
		foreach ( (array) $rows as $r ) {
			$dob = $r['date_of_birth'];
			if ( ! $dob ) {
				continue;
			}
			$next = self::next_occurrence( $dob, $today );
			if ( ! $next ) {
				continue;
			}
			if ( $next <= $limit ) {
				$r['next_birthday'] = $next->format( 'Y-m-d' );
				$r['turning_age']   = (int) $next->format( 'Y' ) - (int) substr( $dob, 0, 4 );
				$results[]          = $r;
			}
		}
		usort( $results, function ( $a, $b ) { return strcmp( $a['next_birthday'], $b['next_birthday'] ); } );
		return $results;
	}

	/**
	 * Eintrittsjubiläen in den nächsten N Tagen.
	 *
	 * @param int $days_ahead Tage.
	 * @return array
	 */
	public static function upcoming_anniversaries( int $days_ahead = 30 ): array {
		global $wpdb;
		$table = VM_Members::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT id, first_name, last_name, organization, entry_date FROM {$table}
			WHERE entry_date IS NOT NULL AND status = 'active'",
			ARRAY_A
		);

		$today   = new DateTimeImmutable( current_time( 'Y-m-d' ) );
		$limit   = $today->modify( '+' . $days_ahead . ' days' );
		$results = [];
		foreach ( (array) $rows as $r ) {
			$entry = $r['entry_date'];
			if ( ! $entry ) {
				continue;
			}
			$next  = self::next_occurrence( $entry, $today );
			if ( ! $next || $next > $limit ) {
				continue;
			}
			$years = (int) $next->format( 'Y' ) - (int) substr( $entry, 0, 4 );
			if ( $years <= 0 ) {
				continue;
			}
			$r['next_anniversary'] = $next->format( 'Y-m-d' );
			$r['years']            = $years;
			$r['is_round']         = in_array( $years, [ 5, 10, 15, 20, 25, 30, 40, 50 ], true );
			$results[]             = $r;
		}
		usort( $results, function ( $a, $b ) { return strcmp( $a['next_anniversary'], $b['next_anniversary'] ); } );
		return $results;
	}

	/**
	 * Nächstes Auftreten von Tag/Monat ab heute.
	 *
	 * @param string            $date  Y-m-d.
	 * @param DateTimeImmutable $today Heute.
	 * @return DateTimeImmutable|null
	 */
	private static function next_occurrence( string $date, DateTimeImmutable $today ): ?DateTimeImmutable {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}
		$month = (int) substr( $date, 5, 2 );
		$day   = (int) substr( $date, 8, 2 );
		if ( $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
			return null;
		}
		try {
			$candidate = $today->setDate( (int) $today->format( 'Y' ), $month, $day );
			if ( $candidate < $today ) {
				$candidate = $candidate->setDate( (int) $today->format( 'Y' ) + 1, $month, $day );
			}
			return $candidate;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Cron-Callback: tägliche Benachrichtigung.
	 *
	 * @return void
	 */
	public static function cron_notify(): void {
		if ( '1' !== (string) get_option( 'vm_anniversary_notify', '0' ) ) {
			return;
		}
		$days      = (int) get_option( 'vm_anniversary_days_ahead', 30 );
		$birthdays = self::upcoming_birthdays( $days );
		$jubs      = self::upcoming_anniversaries( $days );

		// Nur heute fällige senden, um nicht täglich den gesamten 30-Tage-Block zu spammen.
		$today        = current_time( 'Y-m-d' );
		$today_bd     = array_filter( $birthdays, function ( $r ) use ( $today ) { return $r['next_birthday'] === $today; } );
		$today_jubs   = array_filter( $jubs, function ( $r ) use ( $today ) { return $r['next_anniversary'] === $today; } );

		if ( empty( $today_bd ) && empty( $today_jubs ) ) {
			return;
		}

		$to   = (string) get_option( 'vm_email_from_address', get_option( 'admin_email' ) );
		$subj = sprintf( __( '[Vereinsmanager] Jubiläen & Geburtstage am %s', 'vereinsmanager' ), $today );

		$body = '<p>' . esc_html__( 'Heutige Ereignisse:', 'vereinsmanager' ) . '</p><ul>';
		foreach ( $today_bd as $r ) {
			$body .= '<li>🎂 ' . esc_html( VM_Members::format_name( $r ) ) . ' – ' . (int) $r['turning_age'] . '. ' . esc_html__( 'Geburtstag', 'vereinsmanager' ) . '</li>';
		}
		foreach ( $today_jubs as $r ) {
			$body .= '<li>🎉 ' . esc_html( VM_Members::format_name( $r ) ) . ' – ' . (int) $r['years'] . ' ' . esc_html__( 'Jahre Mitgliedschaft', 'vereinsmanager' ) . '</li>';
		}
		$body .= '</ul>';

		wp_mail( $to, $subj, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );

		VM_Central_Logger::log(
			'anniversary_notification_sent',
			[
				'birthdays'     => count( $today_bd ),
				'anniversaries' => count( $today_jubs ),
			]
		);
	}
}
