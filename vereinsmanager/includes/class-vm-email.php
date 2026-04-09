<?php
/**
 * E-Mail-Versand mit Queue.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Email
 */
class VM_Email {

	const BATCH_SIZE = 50;

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_email_send', [ __CLASS__, 'handle_send' ] );
		add_filter( 'wp_mail_from', [ __CLASS__, 'mail_from' ] );
		add_filter( 'wp_mail_from_name', [ __CLASS__, 'mail_from_name' ] );
	}

	/**
	 * Filter: wp_mail_from.
	 *
	 * @param string $email Default.
	 * @return string
	 */
	public static function mail_from( $email ) {
		$override = get_option( 'vm_email_from_address', '' );
		return $override ? $override : $email;
	}

	/**
	 * Filter: wp_mail_from_name.
	 *
	 * @param string $name Default.
	 * @return string
	 */
	public static function mail_from_name( $name ) {
		$override = get_option( 'vm_email_from_name', '' );
		return $override ? $override : $name;
	}

	/**
	 * Render-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_send_emails' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}

		$queue_count = self::queue_count();

		include VM_PLUGIN_DIR . 'admin/views/email-compose.php';
	}

	/**
	 * Anzahl Mails in der Queue.
	 *
	 * @return int
	 */
	public static function queue_count(): int {
		global $wpdb;
		$queue = $wpdb->prefix . 'vm_email_queue';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$queue}" );
	}

	/**
	 * Empfänger ermitteln basierend auf Filtern.
	 *
	 * @param array $filters Filter.
	 * @return array
	 */
	public static function recipients( array $filters ): array {
		global $wpdb;
		$members_table = VM_Members::table();

		$where  = "WHERE email <> '' AND consent_email = 1";
		$params = [];

		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], VM_Members::statuses(), true ) ) {
			$where   .= ' AND status = %s';
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['membership_type'] ) && in_array( $filters['membership_type'], [ 'standard', 'reduced' ], true ) ) {
			$where   .= ' AND membership_type = %s';
			$params[] = $filters['membership_type'];
		}
		if ( ! empty( $filters['member_ids'] ) ) {
			$ids = array_map( 'intval', (array) $filters['member_ids'] );
			$ids = array_filter( $ids );
			if ( $ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$where       .= " AND id IN ($placeholders)";
				$params       = array_merge( $params, $ids );
			}
		}

		$sql = "SELECT * FROM {$members_table} {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		return $rows ?: [];
	}

	/**
	 * Platzhalter ersetzen.
	 *
	 * @param string $text   Text mit Platzhaltern.
	 * @param array  $member Mitglied.
	 * @return string
	 */
	public static function replace_placeholders( string $text, array $member ): string {
		$replacements = [
			'{vorname}'        => $member['first_name'] ?? '',
			'{nachname}'       => $member['last_name'] ?? '',
			'{anrede}'         => $member['salutation'] ?? '',
			'{mitgliedsnummer}' => $member['member_number'] ?? '',
		];
		return strtr( $text, $replacements );
	}

	/**
	 * Send-Handler.
	 *
	 * @return void
	 */
	public static function handle_send(): void {
		if ( ! current_user_can( 'vm_send_emails' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_email_send' );

		$subject = isset( $_POST['vm_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['vm_subject'] ) ) : '';
		$body    = isset( $_POST['vm_body'] ) ? wp_kses_post( wp_unslash( $_POST['vm_body'] ) ) : '';
		$status  = isset( $_POST['vm_filter_status'] ) ? sanitize_text_field( wp_unslash( $_POST['vm_filter_status'] ) ) : '';
		$type    = isset( $_POST['vm_filter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['vm_filter_type'] ) ) : '';

		if ( '' === $subject || '' === $body ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'vm-email', 'vm_notice' => 'invalid' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$recipients = self::recipients(
			[
				'status'          => $status,
				'membership_type' => $type,
			]
		);

		// In Queue einreihen.
		global $wpdb;
		$queue_table = $wpdb->prefix . 'vm_email_queue';
		$queued      = 0;
		foreach ( $recipients as $member ) {
			$wpdb->insert(
				$queue_table,
				[
					'member_id'  => (int) $member['id'],
					'subject'    => $subject,
					'body'       => $body,
					'created_at' => current_time( 'mysql' ),
				]
			);
			++$queued;
		}

		// Sofort einen ersten Batch verarbeiten.
		self::process_queue();

		VM_Central_Logger::log(
			'email_sent',
			[
				'recipient_count' => $queued,
				'subject'         => substr( $subject, 0, 64 ),
			]
		);

		wp_safe_redirect(
			add_query_arg(
				[
					'page'      => 'vm-email',
					'vm_notice' => 'queued',
					'vm_count'  => $queued,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Cron-Verarbeitung der Queue.
	 *
	 * @return void
	 */
	public static function process_queue(): void {
		global $wpdb;
		$queue_table = $wpdb->prefix . 'vm_email_queue';
		$log_table   = $wpdb->prefix . 'vm_email_log';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$queue_table} ORDER BY id ASC LIMIT %d", self::BATCH_SIZE ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			$member = VM_Members::get( (int) $item['member_id'] );
			if ( ! $member || empty( $member['email'] ) ) {
				$wpdb->delete( $queue_table, [ 'id' => $item['id'] ] );
				continue;
			}

			$subject = self::replace_placeholders( (string) $item['subject'], $member );
			$body    = self::replace_placeholders( (string) $item['body'], $member );

			$sent = wp_mail(
				$member['email'],
				$subject,
				$body,
				[ 'Content-Type: text/html; charset=UTF-8' ]
			);

			$wpdb->insert(
				$log_table,
				[
					'member_id' => (int) $member['id'],
					'subject'   => $subject,
					'body'      => $body,
					'sent_at'   => current_time( 'mysql' ),
					'status'    => $sent ? 'sent' : 'failed',
				]
			);

			$wpdb->delete( $queue_table, [ 'id' => $item['id'] ] );
		}
	}
}
