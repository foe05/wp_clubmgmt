<?php
/**
 * Settings-View.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'Vereinsmanager – Einstellungen', 'vereinsmanager' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'vm_settings_group' ); ?>

		<h2><?php esc_html_e( 'Vereinsdaten', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vm_club_name"><?php esc_html_e( 'Vereinsname', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_club_name" id="vm_club_name" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_club_name', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_club_street"><?php esc_html_e( 'Straße', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_club_street" id="vm_club_street" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_club_street', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_club_postal_code"><?php esc_html_e( 'PLZ', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_club_postal_code" id="vm_club_postal_code" type="text" class="small-text" value="<?php echo esc_attr( get_option( 'vm_club_postal_code', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_club_city"><?php esc_html_e( 'Ort', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_club_city" id="vm_club_city" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_club_city', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_club_tax_number"><?php esc_html_e( 'Steuernummer', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_club_tax_number" id="vm_club_tax_number" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_club_tax_number', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_club_tax_office"><?php esc_html_e( 'Finanzamt', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_club_tax_office" id="vm_club_tax_office" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_club_tax_office', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_club_exemption_date"><?php esc_html_e( 'Datum Freistellungsbescheid', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_club_exemption_date" id="vm_club_exemption_date" type="date" value="<?php echo esc_attr( get_option( 'vm_club_exemption_date', '' ) ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'SEPA / Beiträge', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vm_creditor_id"><?php esc_html_e( 'Gläubiger-ID', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_creditor_id" id="vm_creditor_id" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_creditor_id', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_fee_standard"><?php esc_html_e( 'Standardbeitrag (€)', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_fee_standard" id="vm_fee_standard" type="text" class="small-text" value="<?php echo esc_attr( get_option( 'vm_fee_standard', '60.00' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_fee_reduced"><?php esc_html_e( 'Reduzierter Beitrag (€)', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_fee_reduced" id="vm_fee_reduced" type="text" class="small-text" value="<?php echo esc_attr( get_option( 'vm_fee_reduced', '30.00' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_member_number_prefix"><?php esc_html_e( 'Mitgliedsnummer-Präfix', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_member_number_prefix" id="vm_member_number_prefix" type="text" class="small-text" value="<?php echo esc_attr( get_option( 'vm_member_number_prefix', 'FV' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_retention_years"><?php esc_html_e( 'Aufbewahrungsfrist ehemalige Mitglieder (Jahre)', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_retention_years" id="vm_retention_years" type="number" min="0" class="small-text" value="<?php echo esc_attr( get_option( 'vm_retention_years', '10' ) ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'E-Mail', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vm_email_from_name"><?php esc_html_e( 'Absendername', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_email_from_name" id="vm_email_from_name" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_email_from_name', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_email_from_address"><?php esc_html_e( 'Absender-E-Mail', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_email_from_address" id="vm_email_from_address" type="email" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_email_from_address', '' ) ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Veranstaltungen & Ämter', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vm_mv_ladungsfrist_days"><?php esc_html_e( 'Ladungsfrist Mitgliederversammlung (Tage)', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_mv_ladungsfrist_days" id="vm_mv_ladungsfrist_days" type="number" min="0" class="small-text" value="<?php echo esc_attr( get_option( 'vm_mv_ladungsfrist_days', '14' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_board_role_keys"><?php esc_html_e( 'Vorstandsrollen-Keys (kommasepariert)', 'vereinsmanager' ); ?></label></th>
				<td>
					<input name="vm_board_role_keys" id="vm_board_role_keys" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_board_role_keys', 'vorstand,vorsitz,kassenwart,schriftfuehrer' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Rollen-Keys, deren Inhaber beim Einladungstyp "Vorstand" angeschrieben werden.', 'vereinsmanager' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Jubiläen / Geburtstage', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vm_anniversary_notify"><?php esc_html_e( 'Tägliche Benachrichtigung', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="vm_anniversary_notify" id="vm_anniversary_notify">
						<option value="0" <?php selected( get_option( 'vm_anniversary_notify', '0' ), '0' ); ?>><?php esc_html_e( 'Nein', 'vereinsmanager' ); ?></option>
						<option value="1" <?php selected( get_option( 'vm_anniversary_notify', '0' ), '1' ); ?>><?php esc_html_e( 'Ja', 'vereinsmanager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_anniversary_days_ahead"><?php esc_html_e( 'Vorschau (Tage)', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_anniversary_days_ahead" id="vm_anniversary_days_ahead" type="number" min="0" class="small-text" value="<?php echo esc_attr( get_option( 'vm_anniversary_days_ahead', '30' ) ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Zentrales Logging', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vm_logging_enabled"><?php esc_html_e( 'Logging aktiviert', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="vm_logging_enabled" id="vm_logging_enabled">
						<option value="0" <?php selected( get_option( 'vm_logging_enabled', '0' ), '0' ); ?>><?php esc_html_e( 'Nein', 'vereinsmanager' ); ?></option>
						<option value="1" <?php selected( get_option( 'vm_logging_enabled', '0' ), '1' ); ?>><?php esc_html_e( 'Ja', 'vereinsmanager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_logging_api_url"><?php esc_html_e( 'API-URL', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_logging_api_url" id="vm_logging_api_url" type="url" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_logging_api_url', 'https://log.broetzens.de' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vm_logging_api_key"><?php esc_html_e( 'API-Key', 'vereinsmanager' ); ?></label></th>
				<td><input name="vm_logging_api_key" id="vm_logging_api_key" type="password" class="regular-text" value="<?php echo esc_attr( get_option( 'vm_logging_api_key', '' ) ); ?>" autocomplete="new-password" /></td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
