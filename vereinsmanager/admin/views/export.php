<?php
/**
 * Export-View.
 *
 * @package Vereinsmanager
 *
 * @var array $fields
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'Mitglieder-Export', 'vereinsmanager' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="vm_export_csv" />
		<?php wp_nonce_field( 'vm_export_csv' ); ?>

		<h2><?php esc_html_e( 'Filter', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Status', 'vereinsmanager' ); ?></th>
				<td>
					<select name="vm_status">
						<option value=""><?php esc_html_e( 'Alle', 'vereinsmanager' ); ?></option>
						<?php foreach ( VM_Members::status_labels() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Beitragsart', 'vereinsmanager' ); ?></th>
				<td>
					<select name="vm_type">
						<option value=""><?php esc_html_e( 'Alle', 'vereinsmanager' ); ?></option>
						<option value="standard"><?php esc_html_e( 'Standard', 'vereinsmanager' ); ?></option>
						<option value="reduced"><?php esc_html_e( 'Reduziert', 'vereinsmanager' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Felder', 'vereinsmanager' ); ?></h2>
		<p><label><input type="checkbox" id="vm-toggle-all" checked /> <?php esc_html_e( 'Alle markieren', 'vereinsmanager' ); ?></label></p>
		<div class="vm-checkbox-grid">
			<?php foreach ( $fields as $key => $label ) : ?>
				<label><input type="checkbox" class="vm-field-cb" name="vm_fields[]" value="<?php echo esc_attr( $key ); ?>" checked /> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
		</div>

		<?php submit_button( __( 'CSV exportieren', 'vereinsmanager' ) ); ?>
	</form>

	<script>
	(function(){
		var toggle = document.getElementById('vm-toggle-all');
		if (!toggle) return;
		toggle.addEventListener('change', function(){
			document.querySelectorAll('.vm-field-cb').forEach(function(cb){ cb.checked = toggle.checked; });
		});
	})();
	</script>
</div>
