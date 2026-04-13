<?php
/**
 * Event anlegen / bearbeiten.
 *
 * @package Vereinsmanager
 *
 * @var array|null $event
 */

defined( 'ABSPATH' ) || exit;

$is_edit = is_array( $event );
$e       = $event ?: [];
$get     = function ( $key, $default = '' ) use ( $e ) {
	return isset( $e[ $key ] ) && null !== $e[ $key ] ? $e[ $key ] : $default;
};
$extra   = $is_edit && isset( $e['extra_fields_decoded'] ) && is_array( $e['extra_fields_decoded'] ) ? $e['extra_fields_decoded'] : [];

// HTML5 datetime-local braucht "T" statt Leerzeichen.
$dt_fmt = function ( $mysql ) {
	if ( ! $mysql ) {
		return '';
	}
	return str_replace( ' ', 'T', substr( (string) $mysql, 0, 16 ) );
};
?>
<div class="wrap vm-wrap">
	<h1><?php echo $is_edit ? esc_html__( 'Veranstaltung bearbeiten', 'vereinsmanager' ) : esc_html__( 'Neue Veranstaltung', 'vereinsmanager' ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = sanitize_text_field( wp_unslash( $_GET['vm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'updated' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gespeichert.', 'vereinsmanager' ) . '</p></div>';
		} elseif ( 'error' === $notice && isset( $_GET['vm_msg'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( wp_unslash( $_GET['vm_msg'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="vm_event_save" />
		<?php wp_nonce_field( 'vm_event_save' ); ?>
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="event_type"><?php esc_html_e( 'Typ', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="event_type" id="event_type">
						<?php foreach ( VM_Events::types() as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $get( 'event_type', 'other' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="title"><?php esc_html_e( 'Titel', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $get( 'title' ) ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="location"><?php esc_html_e( 'Ort', 'vereinsmanager' ); ?></label></th>
				<td><input type="text" name="location" id="location" class="regular-text" value="<?php echo esc_attr( $get( 'location' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="start_datetime"><?php esc_html_e( 'Start', 'vereinsmanager' ); ?></label></th>
				<td><input type="datetime-local" name="start_datetime" id="start_datetime" value="<?php echo esc_attr( $dt_fmt( $get( 'start_datetime' ) ) ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="end_datetime"><?php esc_html_e( 'Ende', 'vereinsmanager' ); ?></label></th>
				<td><input type="datetime-local" name="end_datetime" id="end_datetime" value="<?php echo esc_attr( $dt_fmt( $get( 'end_datetime' ) ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="registration_deadline"><?php esc_html_e( 'Anmeldeschluss', 'vereinsmanager' ); ?></label></th>
				<td><input type="datetime-local" name="registration_deadline" id="registration_deadline" value="<?php echo esc_attr( $dt_fmt( $get( 'registration_deadline' ) ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="max_attendees"><?php esc_html_e( 'Max. Teilnehmer', 'vereinsmanager' ); ?></label></th>
				<td><input type="number" name="max_attendees" id="max_attendees" class="small-text" min="0" value="<?php echo esc_attr( (string) $get( 'max_attendees' ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="invitation_group"><?php esc_html_e( 'Einladungsgruppe', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="invitation_group" id="invitation_group">
						<?php foreach ( VM_Events::invitation_groups() as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $get( 'invitation_group', 'active' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="custom_role_key"><?php esc_html_e( 'Benutzerdef. Rollen-Key', 'vereinsmanager' ); ?></label></th>
				<td>
					<input type="text" name="custom_role_key" id="custom_role_key" class="regular-text" value="<?php echo esc_attr( $get( 'custom_role_key' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Nur relevant, wenn Einladungsgruppe = "Einzelne Rolle".', 'vereinsmanager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="status"><?php esc_html_e( 'Status', 'vereinsmanager' ); ?></label></th>
				<td>
					<select name="status" id="status">
						<?php foreach ( VM_Events::statuses() as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $get( 'status', 'draft' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="description"><?php esc_html_e( 'Beschreibung', 'vereinsmanager' ); ?></label></th>
				<td><textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $get( 'description' ) ); ?></textarea></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Zusatzfelder (typ-spezifisch)', 'vereinsmanager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr class="vm-extra vm-extra-sommerfest">
				<th><?php esc_html_e( 'Plus-One erlaubt', 'vereinsmanager' ); ?></th>
				<td><input type="text" name="extra_fields[plus_one_allowed]" value="<?php echo esc_attr( (string) ( $extra['plus_one_allowed'] ?? '1' ) ); ?>" class="small-text" /> <span class="description">(0/1)</span></td>
			</tr>
			<tr class="vm-extra vm-extra-sommerfest">
				<th><?php esc_html_e( 'Externe Gäste erlaubt', 'vereinsmanager' ); ?></th>
				<td><input type="text" name="extra_fields[external_guests]" value="<?php echo esc_attr( (string) ( $extra['external_guests'] ?? '0' ) ); ?>" class="small-text" /> <span class="description">(0/1)</span></td>
			</tr>
			<tr class="vm-extra vm-extra-mv">
				<th><?php esc_html_e( 'Beschlussfähigkeit (%)', 'vereinsmanager' ); ?></th>
				<td><input type="number" name="extra_fields[quorum_percentage]" value="<?php echo esc_attr( (string) ( $extra['quorum_percentage'] ?? '' ) ); ?>" class="small-text" min="0" max="100" /></td>
			</tr>
			<tr class="vm-extra vm-extra-vorstand">
				<th><?php esc_html_e( 'Protokoll erforderlich', 'vereinsmanager' ); ?></th>
				<td><input type="text" name="extra_fields[protokoll_erforderlich]" value="<?php echo esc_attr( (string) ( $extra['protokoll_erforderlich'] ?? '1' ) ); ?>" class="small-text" /> <span class="description">(0/1)</span></td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Speichern', 'vereinsmanager' ) : __( 'Anlegen', 'vereinsmanager' ) ); ?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-events' ) ); ?>"><?php esc_html_e( '« zurück zur Liste', 'vereinsmanager' ); ?></a></p>
	</form>

	<script>
	(function(){
		var select = document.getElementById('event_type');
		if (!select) return;
		var map = { sommerfest: 'vm-extra-sommerfest', mitgliederversammlung: 'vm-extra-mv', vorstandssitzung: 'vm-extra-vorstand' };
		function update() {
			var show = map[select.value] || null;
			document.querySelectorAll('.vm-extra').forEach(function(row){
				row.style.display = (show && row.classList.contains(show)) ? '' : 'none';
			});
		}
		select.addEventListener('change', update);
		update();
	})();
	</script>
</div>
