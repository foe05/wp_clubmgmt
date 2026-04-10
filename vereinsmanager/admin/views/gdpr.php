<?php
/**
 * GDPR-View.
 *
 * @package Vereinsmanager
 *
 * @var int   $retention
 * @var array $expired
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'DSGVO-Werkzeuge', 'vereinsmanager' ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) && 'anonymized' === $_GET['vm_notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mitglied anonymisiert.', 'vereinsmanager' ) . '</p></div>';
	}
	?>

	<p><?php esc_html_e( 'Werkzeuge zur Datenauskunft (Art. 15) und Anonymisierung. Datenauskunft und Löschung erfolgen pro Mitglied – verwende den entsprechenden Knopf in der Mitgliederliste oder unten bei den abgelaufenen Aufbewahrungsfristen.', 'vereinsmanager' ); ?></p>

	<h2><?php printf( esc_html__( 'Aufbewahrungsfrist abgelaufen (älter als %d Jahre nach Austritt)', 'vereinsmanager' ), (int) $retention ); ?></h2>

	<?php if ( empty( $expired ) ) : ?>
		<p><?php esc_html_e( 'Keine Mitglieder mit abgelaufener Frist.', 'vereinsmanager' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Mitglied', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Mitgliedsnr.', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Austrittsdatum', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Aktionen', 'vereinsmanager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $expired as $m ) :
					$export_url = wp_nonce_url(
						add_query_arg(
							[
								'action'    => 'vm_gdpr_export',
								'member_id' => $m['id'],
							],
							admin_url( 'admin-post.php' )
						),
						'vm_gdpr_export_' . $m['id']
					);
					$anon_url = wp_nonce_url(
						add_query_arg(
							[
								'action'    => 'vm_gdpr_anonymize',
								'member_id' => $m['id'],
							],
							admin_url( 'admin-post.php' )
						),
						'vm_gdpr_anonymize_' . $m['id']
					);
					?>
					<tr>
						<td><?php echo esc_html( VM_Members::format_name( $m ) ); ?></td>
						<td><?php echo esc_html( $m['member_number'] ?: '—' ); ?></td>
						<td><?php echo esc_html( (string) $m['exit_date'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $export_url ); ?>" class="button button-small"><?php esc_html_e( 'Datenauskunft', 'vereinsmanager' ); ?></a>
							<?php if ( current_user_can( 'vm_delete_members' ) ) : ?>
								<a href="<?php echo esc_url( $anon_url ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Wirklich anonymisieren? Diese Aktion ist nicht umkehrbar.', 'vereinsmanager' ) ); ?>');"><?php esc_html_e( 'Anonymisieren', 'vereinsmanager' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
