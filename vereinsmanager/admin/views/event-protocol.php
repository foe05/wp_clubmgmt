<?php
/**
 * Event-Protokoll-Seite: Tagesordnung + Beschlüsse + Protokolltext.
 *
 * @package Vereinsmanager
 *
 * @var array      $event
 * @var array      $agenda
 * @var array|null $protocol
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php printf( esc_html__( 'Protokoll: %s', 'vereinsmanager' ), esc_html( $event['title'] ) ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) && 'saved' === $_GET['vm_notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gespeichert.', 'vereinsmanager' ) . '</p></div>';
	}
	$pdf_url = wp_nonce_url(
		add_query_arg(
			[ 'action' => 'vm_protocol_pdf', 'event_id' => $event['id'] ],
			admin_url( 'admin-post.php' )
		),
		'vm_protocol_pdf_' . $event['id']
	);
	?>

	<p>
		<a href="<?php echo esc_url( $pdf_url ); ?>" class="button button-primary"><?php esc_html_e( 'PDF herunterladen', 'vereinsmanager' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-event-attendees&id=' . (int) $event['id'] ) ); ?>" class="button"><?php esc_html_e( 'Teilnehmer', 'vereinsmanager' ); ?></a>
	</p>

	<h2><?php esc_html_e( 'Tagesordnung & Beschlüsse', 'vereinsmanager' ); ?></h2>

	<?php if ( empty( $agenda ) ) : ?>
		<p><?php esc_html_e( 'Noch keine Tagesordnungspunkte.', 'vereinsmanager' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Pos.', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Titel', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Beschluss', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Aktion', 'vereinsmanager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $agenda as $a ) :
					$del = wp_nonce_url(
						add_query_arg(
							[ 'action' => 'vm_agenda_delete', 'id' => $a['id'], 'event_id' => $event['id'] ],
							admin_url( 'admin-post.php' )
						),
						'vm_agenda_delete_' . $a['id']
					);
					?>
					<tr>
						<td><?php echo (int) $a['position']; ?></td>
						<td>
							<strong><?php echo esc_html( (string) $a['title'] ); ?></strong><br />
							<small><?php echo esc_html( (string) $a['description'] ); ?></small>
						</td>
						<td>
							<?php if ( (int) $a['is_resolution'] === 1 ) : ?>
								<?php printf( 'Ja %d / Nein %d / Enth. %d', (int) $a['votes_yes'], (int) $a['votes_no'], (int) $a['votes_abstain'] ); ?>
								<?php if ( ! empty( $a['resolution_text'] ) ) : ?>
									<br /><em><?php echo esc_html( (string) $a['resolution_text'] ); ?></em>
								<?php endif; ?>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<a href="#vm-agenda-form" class="button button-small vm-agenda-edit"
								data-id="<?php echo (int) $a['id']; ?>"
								data-pos="<?php echo (int) $a['position']; ?>"
								data-title="<?php echo esc_attr( (string) $a['title'] ); ?>"
								data-desc="<?php echo esc_attr( (string) $a['description'] ); ?>"
								data-res="<?php echo (int) $a['is_resolution']; ?>"
								data-yes="<?php echo (int) $a['votes_yes']; ?>"
								data-no="<?php echo (int) $a['votes_no']; ?>"
								data-abstain="<?php echo (int) $a['votes_abstain']; ?>"
								data-text="<?php echo esc_attr( (string) $a['resolution_text'] ); ?>">
								<?php esc_html_e( 'Bearbeiten', 'vereinsmanager' ); ?>
							</a>
							<a href="<?php echo esc_url( $del ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Löschen?', 'vereinsmanager' ) ); ?>');"><?php esc_html_e( 'Löschen', 'vereinsmanager' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h3 id="vm-agenda-form"><?php esc_html_e( 'TOP hinzufügen / bearbeiten', 'vereinsmanager' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="vm_agenda_save" />
		<input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>" />
		<input type="hidden" name="id" id="vm_agenda_id" value="0" />
		<?php wp_nonce_field( 'vm_agenda_save' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Position', 'vereinsmanager' ); ?></th>
				<td><input type="number" name="position" id="vm_agenda_pos" value="<?php echo (int) ( count( $agenda ) + 1 ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Titel', 'vereinsmanager' ); ?></th>
				<td><input type="text" name="title" id="vm_agenda_title" class="regular-text" required /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Beschreibung', 'vereinsmanager' ); ?></th>
				<td><textarea name="description" id="vm_agenda_desc" rows="3" class="large-text"></textarea></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Beschluss?', 'vereinsmanager' ); ?></th>
				<td><label><input type="checkbox" name="is_resolution" id="vm_agenda_res" value="1" /> <?php esc_html_e( 'Ja, Abstimmung erfassen', 'vereinsmanager' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Stimmen (Ja / Nein / Enth.)', 'vereinsmanager' ); ?></th>
				<td>
					<input type="number" name="votes_yes" id="vm_agenda_yes" value="0" class="small-text" />
					<input type="number" name="votes_no" id="vm_agenda_no" value="0" class="small-text" />
					<input type="number" name="votes_abstain" id="vm_agenda_abstain" value="0" class="small-text" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Beschlussformulierung', 'vereinsmanager' ); ?></th>
				<td><textarea name="resolution_text" id="vm_agenda_text" rows="2" class="large-text"></textarea></td>
			</tr>
		</table>
		<?php submit_button( __( 'Speichern', 'vereinsmanager' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Protokolltext', 'vereinsmanager' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="vm_protocol_save" />
		<input type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>" />
		<?php wp_nonce_field( 'vm_protocol_save' ); ?>
		<?php
		wp_editor(
			$protocol ? (string) $protocol['protocol_text'] : '',
			'protocol_text',
			[
				'textarea_name' => 'protocol_text',
				'textarea_rows' => 14,
				'media_buttons' => false,
			]
		);
		?>
		<p>
			<label><?php esc_html_e( 'Unterzeichnet von', 'vereinsmanager' ); ?>:
				<input type="text" name="signed_by" class="regular-text" value="<?php echo esc_attr( (string) ( $protocol['signed_by'] ?? '' ) ); ?>" />
			</label>
		</p>
		<?php submit_button( __( 'Protokoll speichern', 'vereinsmanager' ) ); ?>
	</form>

	<script>
	(function(){
		document.querySelectorAll('.vm-agenda-edit').forEach(function(btn){
			btn.addEventListener('click', function(e){
				e.preventDefault();
				document.getElementById('vm_agenda_id').value      = btn.dataset.id;
				document.getElementById('vm_agenda_pos').value     = btn.dataset.pos;
				document.getElementById('vm_agenda_title').value   = btn.dataset.title;
				document.getElementById('vm_agenda_desc').value    = btn.dataset.desc;
				document.getElementById('vm_agenda_res').checked   = btn.dataset.res === '1';
				document.getElementById('vm_agenda_yes').value     = btn.dataset.yes;
				document.getElementById('vm_agenda_no').value      = btn.dataset.no;
				document.getElementById('vm_agenda_abstain').value = btn.dataset.abstain;
				document.getElementById('vm_agenda_text').value    = btn.dataset.text;
				window.location.hash = 'vm-agenda-form';
			});
		});
	})();
	</script>
</div>
