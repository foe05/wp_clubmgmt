<?php
/**
 * Donations / Zuwendungsbestätigungen.
 *
 * @package Vereinsmanager
 *
 * @var int   $year
 * @var array $rows
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'Zuwendungsbestätigungen', 'vereinsmanager' ); ?></h1>

	<form method="get" class="vm-inline-form">
		<input type="hidden" name="page" value="vm-donations" />
		<label><?php esc_html_e( 'Jahr', 'vereinsmanager' ); ?>: <input type="number" name="vm_year" value="<?php echo esc_attr( $year ); ?>" min="2000" max="2100" /></label>
		<?php submit_button( __( 'Anzeigen', 'vereinsmanager' ), '', '', false ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vm-inline-form">
		<input type="hidden" name="action" value="vm_donation_bulk" />
		<input type="hidden" name="year" value="<?php echo esc_attr( $year ); ?>" />
		<?php wp_nonce_field( 'vm_donation_bulk' ); ?>
		<?php submit_button( sprintf( __( 'Alle als ZIP für %d herunterladen', 'vereinsmanager' ), $year ), 'primary', '', false ); ?>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Mitglied', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Mitgliedsnr.', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Summe bezahlt', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Aktion', 'vereinsmanager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'Keine bezahlten Beiträge in diesem Jahr.', 'vereinsmanager' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) :
					$url = wp_nonce_url(
						add_query_arg(
							[
								'action'    => 'vm_donation_single',
								'member_id' => $row['id'],
								'year'      => $year,
							],
							admin_url( 'admin-post.php' )
						),
						'vm_donation_' . $row['id'] . '_' . $year
					);
					?>
					<tr>
						<td><?php echo esc_html( VM_Members::format_name( $row ) ); ?></td>
						<td><?php echo esc_html( $row['member_number'] ?: '—' ); ?></td>
						<td><?php echo esc_html( number_format( (float) $row['total'], 2, ',', '.' ) ); ?> €</td>
						<td><a href="<?php echo esc_url( $url ); ?>" class="button button-small"><?php esc_html_e( 'PDF herunterladen', 'vereinsmanager' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
