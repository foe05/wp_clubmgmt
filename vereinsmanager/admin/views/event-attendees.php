<?php
/**
 * Event-Teilnehmerliste.
 *
 * @package Vereinsmanager
 *
 * @var array $event
 * @var array $attendees
 * @var array $stats
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php printf( esc_html__( 'Teilnehmer: %s', 'vereinsmanager' ), esc_html( $event['title'] ) ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) && 'invited' === $_GET['vm_notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = isset( $_GET['vm_count'] ) ? (int) $_GET['vm_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d Einladungen versendet.', 'vereinsmanager' ), $count ) . '</p></div>';
	}
	?>

	<div class="vm-cards">
		<div class="vm-card"><h3><?php esc_html_e( 'Gesamt', 'vereinsmanager' ); ?></h3><p class="vm-big"><?php echo (int) $stats['total']; ?></p></div>
		<div class="vm-card"><h3><?php esc_html_e( 'Zusagen', 'vereinsmanager' ); ?></h3><p class="vm-big"><?php echo (int) $stats['yes']; ?></p></div>
		<div class="vm-card"><h3><?php esc_html_e( 'Absagen', 'vereinsmanager' ); ?></h3><p class="vm-big"><?php echo (int) $stats['no']; ?></p></div>
		<div class="vm-card"><h3><?php esc_html_e( 'Offen', 'vereinsmanager' ); ?></h3><p class="vm-big"><?php echo (int) $stats['pending']; ?></p></div>
	</div>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-event-edit&id=' . (int) $event['id'] ) ); ?>" class="button"><?php esc_html_e( 'Veranstaltung bearbeiten', 'vereinsmanager' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-event-invite&id=' . (int) $event['id'] ) ); ?>" class="button button-primary"><?php esc_html_e( 'Mitglieder einladen', 'vereinsmanager' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-event-protocol&id=' . (int) $event['id'] ) ); ?>" class="button"><?php esc_html_e( 'Protokoll', 'vereinsmanager' ); ?></a>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'E-Mail', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'RSVP', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Begleitung', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Essen', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Anwesend', 'vereinsmanager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $attendees ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Noch keine Teilnehmer. Zum Versand der Einladungen den Button oben nutzen.', 'vereinsmanager' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $attendees as $a ) :
					$name = trim( ( $a['first_name'] ?? '' ) . ' ' . ( $a['last_name'] ?? '' ) );
					if ( '' === $name ) {
						$name = $a['guest_name'] ?? $a['organization'] ?? '—';
					}
					$toggle_v = (int) $a['attended'] === 1 ? 0 : 1;
					$url      = wp_nonce_url(
						add_query_arg(
							[
								'action'    => 'vm_event_checkin',
								'att_id'    => $a['id'],
								'event_id'  => $event['id'],
								'v'         => $toggle_v,
							],
							admin_url( 'admin-post.php' )
						),
						'vm_event_checkin_' . $a['id']
					);
					?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td><?php echo esc_html( (string) $a['email'] ); ?></td>
						<td><span class="vm-status vm-rsvp-<?php echo esc_attr( $a['rsvp_status'] ); ?>"><?php echo esc_html( $a['rsvp_status'] ); ?></span></td>
						<td><?php echo (int) $a['plus_one_count']; ?></td>
						<td><?php echo esc_html( (string) $a['food_choice'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $url ); ?>" class="button button-small">
								<?php echo (int) $a['attended'] === 1 ? esc_html__( '✓ Anwesend', 'vereinsmanager' ) : esc_html__( 'Check-in', 'vereinsmanager' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
