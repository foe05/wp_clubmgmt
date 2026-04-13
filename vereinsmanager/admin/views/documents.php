<?php
/**
 * Dokumentenablage.
 *
 * @package Vereinsmanager
 *
 * @var array $documents
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'Dokumentenablage', 'vereinsmanager' ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = sanitize_text_field( wp_unslash( $_GET['vm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map    = [
			'uploaded' => __( 'Dokument hochgeladen.', 'vereinsmanager' ),
			'deleted'  => __( 'Dokument gelöscht.', 'vereinsmanager' ),
			'no_file'  => __( 'Bitte Datei auswählen.', 'vereinsmanager' ),
		];
		if ( isset( $map[ $notice ] ) ) {
			$class = 'no_file' === $notice ? 'error' : 'success';
			echo '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
		}
	}
	?>

	<h2><?php esc_html_e( 'Neues Dokument hochladen', 'vereinsmanager' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="vm_doc_upload" />
		<?php wp_nonce_field( 'vm_doc_upload' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Titel', 'vereinsmanager' ); ?></th>
				<td><input type="text" name="vm_title" class="regular-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Kategorie', 'vereinsmanager' ); ?></th>
				<td>
					<select name="vm_category">
						<?php foreach ( VM_Documents::categories() as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Sichtbarkeit', 'vereinsmanager' ); ?></th>
				<td>
					<select name="vm_visibility">
						<?php foreach ( VM_Documents::visibilities() as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Zu Mitglied (optional)', 'vereinsmanager' ); ?></th>
				<td><input type="number" name="vm_member_id" class="small-text" min="0" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Zu Veranstaltung (optional)', 'vereinsmanager' ); ?></th>
				<td><input type="number" name="vm_event_id" class="small-text" min="0" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Datei', 'vereinsmanager' ); ?></th>
				<td>
					<input type="file" name="vm_file" required />
					<p class="description"><?php printf( esc_html__( 'Erlaubt: %s', 'vereinsmanager' ), esc_html( implode( ', ', VM_Documents::ALLOWED_EXT ) ) ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Hochladen', 'vereinsmanager' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Dokumente', 'vereinsmanager' ); ?></h2>
	<table class="widefat striped vm-doc-list">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Titel', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Kategorie', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Sichtbarkeit', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Größe', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Hochgeladen', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Aktion', 'vereinsmanager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $documents ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Noch keine Dokumente.', 'vereinsmanager' ); ?></td></tr>
			<?php else : ?>
				<?php
				$cats = VM_Documents::categories();
				$vis  = VM_Documents::visibilities();
				foreach ( $documents as $d ) :
					$download = wp_nonce_url(
						add_query_arg( [ 'action' => 'vm_doc_download', 'id' => $d['id'] ], admin_url( 'admin-post.php' ) ),
						'vm_doc_download_' . $d['id']
					);
					$delete = wp_nonce_url(
						add_query_arg( [ 'action' => 'vm_doc_delete', 'id' => $d['id'] ], admin_url( 'admin-post.php' ) ),
						'vm_doc_delete_' . $d['id']
					);
					?>
					<tr>
						<td><?php echo esc_html( (string) $d['title'] ); ?></td>
						<td><?php echo esc_html( $cats[ $d['category'] ] ?? $d['category'] ); ?></td>
						<td><?php echo esc_html( $vis[ $d['visibility'] ] ?? $d['visibility'] ); ?></td>
						<td><?php echo esc_html( size_format( (int) $d['file_size'] ) ); ?></td>
						<td><?php echo esc_html( (string) $d['uploaded_at'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $download ); ?>" class="button button-small"><?php esc_html_e( 'Download', 'vereinsmanager' ); ?></a>
							<a href="<?php echo esc_url( $delete ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Wirklich löschen?', 'vereinsmanager' ) ); ?>');"><?php esc_html_e( 'Löschen', 'vereinsmanager' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
