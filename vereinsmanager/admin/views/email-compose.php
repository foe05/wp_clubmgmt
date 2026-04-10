<?php
/**
 * E-Mail-Compose-View.
 *
 * @package Vereinsmanager
 *
 * @var int $queue_count
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'E-Mail-Versand', 'vereinsmanager' ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = sanitize_text_field( wp_unslash( $_GET['vm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'queued' === $notice ) {
			$count = isset( $_GET['vm_count'] ) ? (int) $_GET['vm_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d E-Mails in die Warteschlange eingereiht. Verarbeitung läuft im Hintergrund.', 'vereinsmanager' ), (int) $count ) . '</p></div>';
		} elseif ( 'invalid' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Bitte Betreff und Text angeben.', 'vereinsmanager' ) . '</p></div>';
		}
	}
	?>

	<?php if ( $queue_count > 0 ) : ?>
		<div class="notice notice-info"><p><?php printf( esc_html__( 'In der Warteschlange: %d E-Mails', 'vereinsmanager' ), (int) $queue_count ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="vm_email_send" />
		<?php wp_nonce_field( 'vm_email_send' ); ?>

		<h2><?php esc_html_e( 'Empfänger', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="vm_filter_status"><?php esc_html_e( 'Status', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="vm_filter_status" id="vm_filter_status">
						<option value=""><?php esc_html_e( 'Alle', 'vereinsmanager' ); ?></option>
						<?php foreach ( VM_Members::status_labels() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="vm_filter_type"><?php esc_html_e( 'Beitragsart', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="vm_filter_type" id="vm_filter_type">
						<option value=""><?php esc_html_e( 'Alle', 'vereinsmanager' ); ?></option>
						<option value="standard"><?php esc_html_e( 'Standard', 'vereinsmanager' ); ?></option>
						<option value="reduced"><?php esc_html_e( 'Reduziert', 'vereinsmanager' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'Es werden nur Mitglieder angeschrieben, die der E-Mail-Kontaktaufnahme zugestimmt haben.', 'vereinsmanager' ); ?></p>

		<h2><?php esc_html_e( 'Nachricht', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="vm_subject"><?php esc_html_e( 'Betreff', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="vm_subject" id="vm_subject" class="large-text" required /></td>
			</tr>
			<tr>
				<th><label for="vm_body"><?php esc_html_e( 'Text', 'vereinsmanager' ); ?></label></th>
				<td>
					<?php
					wp_editor(
						'',
						'vm_body',
						[
							'textarea_name' => 'vm_body',
							'textarea_rows' => 12,
							'media_buttons' => false,
						]
					);
					?>
					<p class="description"><?php esc_html_e( 'Verfügbare Platzhalter: {vorname}, {nachname}, {anrede}, {mitgliedsnummer}', 'vereinsmanager' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Versenden', 'vereinsmanager' ) ); ?>
	</form>
</div>
