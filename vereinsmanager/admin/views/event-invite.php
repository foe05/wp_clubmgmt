<?php
/**
 * Event-Einladungs-Formular.
 *
 * @package Vereinsmanager
 *
 * @var array $event
 */

defined( 'ABSPATH' ) || exit;

$default_subject = sprintf( __( 'Einladung: %s', 'vereinsmanager' ), $event['title'] );
$default_body    = "<p>Hallo {vorname},</p>\n" .
	"<p>hiermit laden wir dich herzlich zu <strong>{event_titel}</strong> ein.</p>\n" .
	"<ul>\n" .
	"<li>Datum: {event_datum}</li>\n" .
	"<li>Ort: {event_ort}</li>\n" .
	"</ul>\n" .
	"<p>Bitte gib uns über den folgenden Link deine Rückmeldung:<br /><a href=\"{rsvp_link}\">{rsvp_link}</a></p>\n" .
	"<p>Viele Grüße,<br />der Vorstand</p>";
?>
<div class="wrap vm-wrap">
	<h1><?php printf( esc_html__( 'Einladung: %s', 'vereinsmanager' ), esc_html( $event['title'] ) ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) && 'invalid' === $_GET['vm_notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Bitte Betreff und Text angeben.', 'vereinsmanager' ) . '</p></div>';
	}
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="vm_event_invite" />
		<input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>" />
		<?php wp_nonce_field( 'vm_event_invite' ); ?>

		<p>
			<?php
			printf(
				esc_html__( 'Empfängergruppe: %s', 'vereinsmanager' ),
				'<strong>' . esc_html( VM_Events::invitation_groups()[ $event['invitation_group'] ] ?? $event['invitation_group'] ) . '</strong>'
			);
			?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="vm_subject"><?php esc_html_e( 'Betreff', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="vm_subject" id="vm_subject" class="large-text" value="<?php echo esc_attr( $default_subject ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="vm_body"><?php esc_html_e( 'Nachricht', 'vereinsmanager' ); ?></label></th>
				<td>
					<?php
					wp_editor(
						$default_body,
						'vm_body',
						[
							'textarea_name' => 'vm_body',
							'textarea_rows' => 14,
							'media_buttons' => false,
						]
					);
					?>
					<p class="description">
						<?php esc_html_e( 'Verfügbare Platzhalter: {vorname}, {nachname}, {anrede}, {mitgliedsnummer}, {event_titel}, {event_datum}, {event_ort}, {rsvp_link}', 'vereinsmanager' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Einladungen versenden', 'vereinsmanager' ) ); ?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-event-attendees&id=' . (int) $event['id'] ) ); ?>"><?php esc_html_e( '« zurück zu Teilnehmern', 'vereinsmanager' ); ?></a></p>
	</form>
</div>
