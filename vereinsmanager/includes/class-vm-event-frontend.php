<?php
/**
 * Frontend-Shortcode und RSVP-Verarbeitung.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Event_Frontend
 */
class VM_Event_Frontend {

	const RATE_LIMIT_MAX     = 10;
	const RATE_LIMIT_WINDOW  = 60;

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'vm_event_rsvp', [ __CLASS__, 'render_shortcode' ] );
		add_action( 'admin_post_nopriv_vm_rsvp_submit', [ __CLASS__, 'handle_submit' ] );
		add_action( 'admin_post_vm_rsvp_submit', [ __CLASS__, 'handle_submit' ] );
	}

	/**
	 * Shortcode `[vm_event_rsvp]`.
	 *
	 * @param array $atts Attribute.
	 * @return string
	 */
	public static function render_shortcode( $atts ): string {
		$token = isset( $_GET['vm_rsvp'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_rsvp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return '<p class="vm-rsvp-notice">' . esc_html__( 'Kein RSVP-Link aufgerufen. Bitte nutze den Link aus deiner Einladungs-E-Mail.', 'vereinsmanager' ) . '</p>';
		}

		$attendee = VM_Event_Attendees::get_by_token( $token );
		if ( ! $attendee ) {
			return '<p class="vm-rsvp-notice">' . esc_html__( 'Ungültiger oder abgelaufener Link.', 'vereinsmanager' ) . '</p>';
		}

		$event = VM_Events::get( (int) $attendee['event_id'] );
		if ( ! $event ) {
			return '<p class="vm-rsvp-notice">' . esc_html__( 'Veranstaltung nicht gefunden.', 'vereinsmanager' ) . '</p>';
		}

		// Notice bei Submit-Erfolg.
		$notice_html = '';
		if ( isset( $_GET['vm_rsvp_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice_html = '<div class="vm-rsvp-notice vm-rsvp-ok">' . esc_html__( 'Vielen Dank, deine Rückmeldung wurde gespeichert.', 'vereinsmanager' ) . '</div>';
		}
		if ( isset( $_GET['vm_rsvp_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$err         = sanitize_text_field( wp_unslash( $_GET['vm_rsvp_err'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice_html = '<div class="vm-rsvp-notice vm-rsvp-err">' . esc_html( $err ) . '</div>';
		}

		ob_start();
		?>
		<div class="vm-rsvp-form">
			<h2><?php echo esc_html( $event['title'] ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Datum', 'vereinsmanager' ); ?>:</strong>
				<?php echo esc_html( $event['start_datetime'] ? mysql2date( 'l, d.m.Y H:i', $event['start_datetime'] ) : '' ); ?><br />
				<?php if ( ! empty( $event['location'] ) ) : ?>
					<strong><?php esc_html_e( 'Ort', 'vereinsmanager' ); ?>:</strong> <?php echo esc_html( $event['location'] ); ?>
				<?php endif; ?>
			</p>

			<?php if ( ! empty( $event['description'] ) ) : ?>
				<div class="vm-rsvp-desc"><?php echo wp_kses_post( $event['description'] ); ?></div>
			<?php endif; ?>

			<?php echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="vm_rsvp_submit" />
				<input type="hidden" name="vm_rsvp" value="<?php echo esc_attr( $token ); ?>" />

				<p>
					<label>
						<input type="radio" name="rsvp_status" value="yes" <?php checked( $attendee['rsvp_status'], 'yes' ); ?> required />
						<?php esc_html_e( 'Ich nehme teil', 'vereinsmanager' ); ?>
					</label><br />
					<label>
						<input type="radio" name="rsvp_status" value="no" <?php checked( $attendee['rsvp_status'], 'no' ); ?> />
						<?php esc_html_e( 'Ich kann nicht teilnehmen', 'vereinsmanager' ); ?>
					</label><br />
					<label>
						<input type="radio" name="rsvp_status" value="maybe" <?php checked( $attendee['rsvp_status'], 'maybe' ); ?> />
						<?php esc_html_e( 'Vielleicht', 'vereinsmanager' ); ?>
					</label>
				</p>

				<?php if ( 'sommerfest' === $event['event_type'] ) : ?>
					<p>
						<label><?php esc_html_e( 'Anzahl Begleitpersonen', 'vereinsmanager' ); ?><br />
							<input type="number" name="plus_one_count" min="0" max="10" value="<?php echo esc_attr( (string) $attendee['plus_one_count'] ); ?>" />
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'Namen der Begleitpersonen', 'vereinsmanager' ); ?><br />
							<textarea name="plus_one_names" rows="2"><?php echo esc_textarea( (string) $attendee['plus_one_names'] ); ?></textarea>
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'Essensauswahl', 'vereinsmanager' ); ?><br />
							<select name="food_choice">
								<option value=""><?php esc_html_e( 'Keine Angabe', 'vereinsmanager' ); ?></option>
								<option value="omnivor" <?php selected( $attendee['food_choice'], 'omnivor' ); ?>><?php esc_html_e( 'Omnivor', 'vereinsmanager' ); ?></option>
								<option value="vegetarisch" <?php selected( $attendee['food_choice'], 'vegetarisch' ); ?>><?php esc_html_e( 'Vegetarisch', 'vereinsmanager' ); ?></option>
								<option value="vegan" <?php selected( $attendee['food_choice'], 'vegan' ); ?>><?php esc_html_e( 'Vegan', 'vereinsmanager' ); ?></option>
							</select>
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'Allergien / Besonderheiten', 'vereinsmanager' ); ?><br />
							<textarea name="dietary_notes" rows="2"><?php echo esc_textarea( (string) $attendee['dietary_notes'] ); ?></textarea>
						</label>
					</p>
				<?php elseif ( 'mitgliederversammlung' === $event['event_type'] ) : ?>
					<p class="vm-rsvp-vote-hint">
						<?php if ( (int) $attendee['voting_right'] === 1 ) : ?>
							<?php esc_html_e( 'Du bist stimmberechtigtes Mitglied.', 'vereinsmanager' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Hinweis: Nur aktive Mitglieder sind stimmberechtigt.', 'vereinsmanager' ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<p>
					<button type="submit" class="vm-rsvp-submit"><?php esc_html_e( 'Rückmeldung absenden', 'vereinsmanager' ); ?></button>
				</p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Rate-Limit-Prüfung.
	 *
	 * @return bool
	 */
	private static function rate_limited(): bool {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key  = 'vm_rsvp_rate_' . md5( $ip );
		$curr = (int) get_transient( $key );
		if ( $curr >= self::RATE_LIMIT_MAX ) {
			return true;
		}
		set_transient( $key, $curr + 1, self::RATE_LIMIT_WINDOW );
		return false;
	}

	/**
	 * RSVP-Submit-Handler.
	 *
	 * @return void
	 */
	public static function handle_submit(): void {
		if ( self::rate_limited() ) {
			wp_safe_redirect( add_query_arg( 'vm_rsvp_err', rawurlencode( __( 'Zu viele Anfragen. Bitte später erneut versuchen.', 'vereinsmanager' ) ), home_url( '/' ) ) );
			exit;
		}

		$token = isset( $_POST['vm_rsvp'] ) ? sanitize_text_field( wp_unslash( $_POST['vm_rsvp'] ) ) : '';
		if ( '' === $token ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$result = VM_Event_Attendees::save_rsvp_response( $token, wp_unslash( $_POST ) );

		$back = add_query_arg( 'vm_rsvp', $token, wp_get_referer() ?: home_url( '/' ) );
		if ( is_wp_error( $result ) ) {
			$back = add_query_arg( 'vm_rsvp_err', rawurlencode( $result->get_error_message() ), $back );
		} else {
			$back = add_query_arg( 'vm_rsvp_ok', '1', $back );
		}
		wp_safe_redirect( $back );
		exit;
	}
}
