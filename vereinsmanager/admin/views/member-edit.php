<?php
/**
 * Mitglied anlegen / bearbeiten.
 *
 * @package Vereinsmanager
 *
 * @var array|null $member
 * @var int        $id
 */

defined( 'ABSPATH' ) || exit;

$is_edit = is_array( $member );
$m       = $member ?: [];
$get  = function ( $key, $default = '' ) use ( $m ) {
	return isset( $m[ $key ] ) && null !== $m[ $key ] ? $m[ $key ] : $default;
};
?>
<div class="wrap vm-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Mitglied bearbeiten', 'vereinsmanager' ) : esc_html__( 'Neues Mitglied', 'vereinsmanager' ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = sanitize_text_field( wp_unslash( $_GET['vm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'updated' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mitglied gespeichert.', 'vereinsmanager' ) . '</p></div>';
		} elseif ( 'error' === $notice && isset( $_GET['vm_msg'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( wp_unslash( $_GET['vm_msg'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vm-form">
		<input type="hidden" name="action" value="vm_save_member" />
		<?php wp_nonce_field( 'vm_save_member' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>" />
		<?php endif; ?>

		<h2><?php esc_html_e( 'Stammdaten', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php if ( $is_edit && ! empty( $m['member_number'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Mitgliedsnummer', 'vereinsmanager' ); ?></th>
					<td><code><?php echo esc_html( $m['member_number'] ); ?></code></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><label for="status"><?php esc_html_e( 'Status', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="status" id="status">
						<?php foreach ( VM_Members::status_labels() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $get( 'status', 'prospect' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="salutation"><?php esc_html_e( 'Anrede', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="salutation" id="salutation">
						<?php
						$salutations = [
							''       => __( '— bitte wählen —', 'vereinsmanager' ),
							'Herr'   => __( 'Herr', 'vereinsmanager' ),
							'Frau'   => __( 'Frau', 'vereinsmanager' ),
							'Divers' => __( 'Divers', 'vereinsmanager' ),
							'Firma'  => __( 'Firma', 'vereinsmanager' ),
						];
						foreach ( $salutations as $key => $label ) :
							?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $get( 'salutation' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="title"><?php esc_html_e( 'Titel', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $get( 'title' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="first_name"><?php esc_html_e( 'Vorname', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="first_name" id="first_name" class="regular-text" value="<?php echo esc_attr( $get( 'first_name' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="last_name"><?php esc_html_e( 'Nachname', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="last_name" id="last_name" class="regular-text" value="<?php echo esc_attr( $get( 'last_name' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="organization"><?php esc_html_e( 'Organisation', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="organization" id="organization" class="regular-text" value="<?php echo esc_attr( $get( 'organization' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="date_of_birth"><?php esc_html_e( 'Geburtsdatum', 'vereinsmanager' ); ?></label></th>
				<td><input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo esc_attr( $get( 'date_of_birth' ) ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Anschrift & Kontakt', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="street"><?php esc_html_e( 'Straße', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="street" id="street" class="regular-text" value="<?php echo esc_attr( $get( 'street' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="postal_code"><?php esc_html_e( 'PLZ', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="postal_code" id="postal_code" class="small-text" value="<?php echo esc_attr( $get( 'postal_code' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="city"><?php esc_html_e( 'Ort', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="city" id="city" class="regular-text" value="<?php echo esc_attr( $get( 'city' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="country"><?php esc_html_e( 'Land', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="country" id="country" class="small-text" maxlength="2" value="<?php echo esc_attr( $get( 'country', 'DE' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="email"><?php esc_html_e( 'E-Mail', 'vereinsmanager' ); ?></label></th>
				<td><input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( $get( 'email' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="phone"><?php esc_html_e( 'Telefon', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr( $get( 'phone' ) ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Mitgliedschaft', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="entry_date"><?php esc_html_e( 'Eintrittsdatum', 'vereinsmanager' ); ?></label></th>
				<td><input type="date" name="entry_date" id="entry_date" value="<?php echo esc_attr( $get( 'entry_date' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="exit_date"><?php esc_html_e( 'Austrittsdatum', 'vereinsmanager' ); ?></label></th>
				<td><input type="date" name="exit_date" id="exit_date" value="<?php echo esc_attr( $get( 'exit_date' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="exit_reason"><?php esc_html_e( 'Austrittsgrund', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="exit_reason" id="exit_reason" class="regular-text" value="<?php echo esc_attr( $get( 'exit_reason' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="membership_type"><?php esc_html_e( 'Beitragsart', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="membership_type" id="membership_type">
						<option value="standard" <?php selected( $get( 'membership_type', 'standard' ), 'standard' ); ?>><?php esc_html_e( 'Standard', 'vereinsmanager' ); ?></option>
						<option value="reduced" <?php selected( $get( 'membership_type' ), 'reduced' ); ?>><?php esc_html_e( 'Reduziert', 'vereinsmanager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="fee_amount"><?php esc_html_e( 'Individueller Jahresbeitrag (€)', 'vereinsmanager' ); ?></label></th>
				<td>
					<input type="text" name="fee_amount" id="fee_amount" class="small-text" value="<?php echo esc_attr( $get( 'fee_amount' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leer lassen, um den Standard-Beitrag laut Einstellungen zu verwenden.', 'vereinsmanager' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Bankverbindung & SEPA', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="bank_account_holder"><?php esc_html_e( 'Kontoinhaber', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="bank_account_holder" id="bank_account_holder" class="regular-text" value="<?php echo esc_attr( $get( 'bank_account_holder' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="bank_iban"><?php esc_html_e( 'IBAN', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="bank_iban" id="bank_iban" class="regular-text" value="<?php echo esc_attr( $get( 'bank_iban' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="bank_bic"><?php esc_html_e( 'BIC', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="bank_bic" id="bank_bic" class="regular-text" value="<?php echo esc_attr( $get( 'bank_bic' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'SEPA-Mandat erteilt', 'vereinsmanager' ); ?></th>
				<td>
					<label><input type="checkbox" name="sepa_mandate" value="1" <?php checked( (int) $get( 'sepa_mandate', 0 ), 1 ); ?> /> <?php esc_html_e( 'Ja, Lastschriftmandat liegt vor', 'vereinsmanager' ); ?></label>
				</td>
			</tr>
			<tr>
				<th><label for="sepa_mandate_reference"><?php esc_html_e( 'Mandatsreferenz', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="sepa_mandate_reference" id="sepa_mandate_reference" class="regular-text" value="<?php echo esc_attr( $get( 'sepa_mandate_reference' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="sepa_mandate_date"><?php esc_html_e( 'Datum Mandatserteilung', 'vereinsmanager' ); ?></label></th>
				<td><input type="date" name="sepa_mandate_date" id="sepa_mandate_date" value="<?php echo esc_attr( $get( 'sepa_mandate_date' ) ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Einwilligungen (DSGVO)', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Datenverarbeitung', 'vereinsmanager' ); ?></th>
				<td>
					<label><input type="checkbox" name="consent_data_processing" value="1" <?php checked( (int) $get( 'consent_data_processing', 0 ), 1 ); ?> /> <?php esc_html_e( 'Einwilligung zur Datenverarbeitung erteilt', 'vereinsmanager' ); ?></label><br />
					<label><?php esc_html_e( 'Datum:', 'vereinsmanager' ); ?> <input type="date" name="consent_data_processing_date" value="<?php echo esc_attr( $get( 'consent_data_processing_date' ) ); ?>" /></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'E-Mail-Kontakt', 'vereinsmanager' ); ?></th>
				<td>
					<label><input type="checkbox" name="consent_email" value="1" <?php checked( (int) $get( 'consent_email', 0 ), 1 ); ?> /> <?php esc_html_e( 'Darf E-Mails erhalten', 'vereinsmanager' ); ?></label><br />
					<label><?php esc_html_e( 'Datum:', 'vereinsmanager' ); ?> <input type="date" name="consent_email_date" value="<?php echo esc_attr( $get( 'consent_email_date' ) ); ?>" /></label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Notizen', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="notes"><?php esc_html_e( 'Interne Notizen', 'vereinsmanager' ); ?></label></th>
				<td><textarea name="notes" id="notes" rows="5" class="large-text"><?php echo esc_textarea( $get( 'notes' ) ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Speichern', 'vereinsmanager' ) : __( 'Anlegen', 'vereinsmanager' ) ); ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-members' ) ); ?>"><?php esc_html_e( '« zurück zur Liste', 'vereinsmanager' ); ?></a>
		</p>
	</form>

	<?php if ( $is_edit && class_exists( 'VM_Member_Roles' ) ) :
		$roles = VM_Member_Roles::for_member( (int) $m['id'] );
		?>
		<hr />
		<h2><?php esc_html_e( 'Ämter / Funktionen', 'vereinsmanager' ); ?></h2>

		<?php if ( empty( $roles ) ) : ?>
			<p><?php esc_html_e( 'Keine Ämter zugewiesen.', 'vereinsmanager' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Amt', 'vereinsmanager' ); ?></th>
						<th><?php esc_html_e( 'Bezeichnung', 'vereinsmanager' ); ?></th>
						<th><?php esc_html_e( 'Von', 'vereinsmanager' ); ?></th>
						<th><?php esc_html_e( 'Bis', 'vereinsmanager' ); ?></th>
						<th><?php esc_html_e( 'Aktion', 'vereinsmanager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $roles as $r ) :
						$end_url = wp_nonce_url(
							add_query_arg(
								[ 'action' => 'vm_member_role_end', 'role_id' => $r['id'], 'member_id' => $m['id'] ],
								admin_url( 'admin-post.php' )
							),
							'vm_member_role_end_' . $r['id']
						);
						?>
						<tr>
							<td><code><?php echo esc_html( (string) $r['role_key'] ); ?></code></td>
							<td><?php echo esc_html( (string) $r['role_label'] ); ?></td>
							<td><?php echo esc_html( (string) $r['start_date'] ); ?></td>
							<td><?php echo $r['end_date'] ? esc_html( (string) $r['end_date'] ) : '<em>' . esc_html__( 'aktiv', 'vereinsmanager' ) . '</em>'; ?></td>
							<td>
								<?php if ( ! $r['end_date'] ) : ?>
									<a href="<?php echo esc_url( $end_url ); ?>" class="button button-small"><?php esc_html_e( 'Beenden', 'vereinsmanager' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Neues Amt hinzufügen', 'vereinsmanager' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vm-inline-form">
			<input type="hidden" name="action" value="vm_member_role_add" />
			<input type="hidden" name="member_id" value="<?php echo (int) $m['id']; ?>" />
			<?php wp_nonce_field( 'vm_member_role_add' ); ?>
			<label><?php esc_html_e( 'Key', 'vereinsmanager' ); ?>
				<input type="text" name="role_key" placeholder="vorstand" required />
			</label>
			<label><?php esc_html_e( 'Bezeichnung', 'vereinsmanager' ); ?>
				<input type="text" name="role_label" placeholder="1. Vorsitzender" />
			</label>
			<label><?php esc_html_e( 'Start', 'vereinsmanager' ); ?>
				<input type="date" name="start_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
			</label>
			<?php submit_button( __( 'Hinzufügen', 'vereinsmanager' ), 'secondary', '', false ); ?>
		</form>
	<?php endif; ?>
</div>
