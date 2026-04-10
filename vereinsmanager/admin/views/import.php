<?php
/**
 * CSV-Import-View.
 *
 * @package Vereinsmanager
 *
 * @var array|false $preview_data
 */

defined( 'ABSPATH' ) || exit;

$importable = VM_Import::importable_fields();
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'Mitglieder-Import', 'vereinsmanager' ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = sanitize_text_field( wp_unslash( $_GET['vm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'done' === $notice ) {
			$imp = isset( $_GET['vm_imported'] ) ? (int) $_GET['vm_imported'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$skp = isset( $_GET['vm_skipped'] ) ? (int) $_GET['vm_skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$err = isset( $_GET['vm_errors'] ) ? (int) $_GET['vm_errors'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$dry = isset( $_GET['vm_dry'] ) && '1' === $_GET['vm_dry']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo $dry ? esc_html__( 'Dry-Run abgeschlossen.', 'vereinsmanager' ) : esc_html__( 'Import abgeschlossen.', 'vereinsmanager' );
			echo ' ' . sprintf( esc_html__( 'Importiert: %1$d, Übersprungen: %2$d, Fehler: %3$d', 'vereinsmanager' ), $imp, $skp, $err );
			echo '</p></div>';
		} elseif ( 'no_file' === $notice ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Bitte eine CSV-Datei auswählen.', 'vereinsmanager' ) . '</p></div>';
		}
	}
	?>

	<h2><?php esc_html_e( '1. CSV hochladen', 'vereinsmanager' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="vm_import_upload" />
		<?php wp_nonce_field( 'vm_import_upload' ); ?>
		<p>
			<label><?php esc_html_e( 'Trennzeichen', 'vereinsmanager' ); ?>:
				<select name="vm_delimiter">
					<option value=",">,</option>
					<option value=";" selected>;</option>
				</select>
			</label>
		</p>
		<p><input type="file" name="vm_csv" accept=".csv,text/csv" required /></p>
		<?php submit_button( __( 'Hochladen & Vorschau', 'vereinsmanager' ) ); ?>
	</form>

	<?php if ( $preview_data && ! empty( $preview_data['header'] ) ) : ?>
		<h2><?php esc_html_e( '2. Spalten zuordnen & importieren', 'vereinsmanager' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="vm_import_run" />
			<?php wp_nonce_field( 'vm_import_run' ); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'CSV-Spalte', 'vereinsmanager' ); ?></th>
						<th><?php esc_html_e( 'Plugin-Feld', 'vereinsmanager' ); ?></th>
						<th><?php esc_html_e( 'Beispiel (Zeile 1)', 'vereinsmanager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $preview_data['header'] as $idx => $col_name ) : ?>
						<tr>
							<td><?php echo esc_html( $col_name ); ?></td>
							<td>
								<select name="vm_mapping[<?php echo (int) $idx; ?>]">
									<option value=""><?php esc_html_e( '— ignorieren —', 'vereinsmanager' ); ?></option>
									<?php foreach ( $importable as $key => $label ) :
										$guess = strtolower( trim( $col_name ) );
										$selected = ( $guess === strtolower( $label ) || $guess === $key ) ? 'selected' : '';
										?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php echo $selected; ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><?php echo isset( $preview_data['rows'][0][ $idx ] ) ? esc_html( $preview_data['rows'][0][ $idx ] ) : ''; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Vorschau (erste 5 Zeilen)', 'vereinsmanager' ); ?></h3>
			<table class="widefat striped">
				<thead><tr><?php foreach ( $preview_data['header'] as $col_name ) : ?><th><?php echo esc_html( $col_name ); ?></th><?php endforeach; ?></tr></thead>
				<tbody>
					<?php foreach ( array_slice( $preview_data['rows'], 0, 5 ) as $row ) : ?>
						<tr>
							<?php foreach ( $row as $cell ) : ?><td><?php echo esc_html( (string) $cell ); ?></td><?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p><label><input type="checkbox" name="vm_dry_run" value="1" checked /> <?php esc_html_e( 'Dry-Run (nur testen, nicht in DB schreiben)', 'vereinsmanager' ); ?></label></p>
			<?php submit_button( __( 'Import starten', 'vereinsmanager' ) ); ?>
		</form>
	<?php endif; ?>
</div>
