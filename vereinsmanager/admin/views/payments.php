<?php
/**
 * Payments-View.
 *
 * @package Vereinsmanager
 *
 * @var int   $year
 * @var string $status
 * @var array $payments
 * @var array $totals
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'Beiträge & Zahlungen', 'vereinsmanager' ); ?></h1>

	<?php
	if ( isset( $_GET['vm_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = sanitize_text_field( wp_unslash( $_GET['vm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'fees_generated' === $notice ) {
			$count = isset( $_GET['vm_count'] ) ? (int) $_GET['vm_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d Beitragsforderungen erstellt.', 'vereinsmanager' ), (int) $count ) . '</p></div>';
		}
	}
	?>

	<div class="vm-cards">
		<div class="vm-card">
			<h3><?php esc_html_e( 'Soll', 'vereinsmanager' ); ?></h3>
			<p class="vm-big"><?php echo esc_html( number_format( $totals['soll'], 2, ',', '.' ) ); ?> €</p>
		</div>
		<div class="vm-card">
			<h3><?php esc_html_e( 'Ist (bezahlt)', 'vereinsmanager' ); ?></h3>
			<p class="vm-big"><?php echo esc_html( number_format( $totals['ist'], 2, ',', '.' ) ); ?> €</p>
			<small><?php echo (int) $totals['count_paid']; ?> <?php esc_html_e( 'Zahlungen', 'vereinsmanager' ); ?></small>
		</div>
		<div class="vm-card">
			<h3><?php esc_html_e( 'Offen', 'vereinsmanager' ); ?></h3>
			<p class="vm-big"><?php echo esc_html( number_format( $totals['open'], 2, ',', '.' ) ); ?> €</p>
			<small><?php echo (int) $totals['count_open']; ?> <?php esc_html_e( 'Forderungen', 'vereinsmanager' ); ?></small>
		</div>
	</div>

	<form method="get" class="vm-inline-form">
		<input type="hidden" name="page" value="vm-payments" />
		<label><?php esc_html_e( 'Jahr', 'vereinsmanager' ); ?>: <input type="number" name="vm_year" value="<?php echo esc_attr( $year ); ?>" min="2000" max="2100" /></label>
		<select name="vm_pay_status">
			<option value=""><?php esc_html_e( 'Alle', 'vereinsmanager' ); ?></option>
			<option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Offen', 'vereinsmanager' ); ?></option>
			<option value="paid" <?php selected( $status, 'paid' ); ?>><?php esc_html_e( 'Bezahlt', 'vereinsmanager' ); ?></option>
			<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Storniert', 'vereinsmanager' ); ?></option>
		</select>
		<?php submit_button( __( 'Filtern', 'vereinsmanager' ), '', '', false ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vm-inline-form">
		<input type="hidden" name="action" value="vm_generate_fees" />
		<?php wp_nonce_field( 'vm_generate_fees' ); ?>
		<label><?php esc_html_e( 'Beiträge generieren für Jahr', 'vereinsmanager' ); ?>: <input type="number" name="year" value="<?php echo esc_attr( $year ); ?>" min="2000" max="2100" /></label>
		<?php submit_button( __( 'Jetzt generieren', 'vereinsmanager' ), 'primary', '', false ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vm-inline-form">
		<input type="hidden" name="action" value="vm_sepa_export" />
		<?php wp_nonce_field( 'vm_sepa_export' ); ?>
		<input type="hidden" name="year" value="<?php echo esc_attr( $year ); ?>" />
		<?php submit_button( __( 'SEPA-CSV exportieren', 'vereinsmanager' ), 'secondary', '', false ); ?>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Mitglied', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Mitgliedsnr.', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Jahr', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Betrag', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Methode', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Status', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Zahlungsdatum', 'vereinsmanager' ); ?></th>
				<th><?php esc_html_e( 'Aktion', 'vereinsmanager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $payments ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'Keine Zahlungen gefunden.', 'vereinsmanager' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $payments as $p ) : ?>
					<tr>
						<td><?php echo esc_html( VM_Members::format_name( $p ) ); ?></td>
						<td><?php echo esc_html( $p['member_number'] ?? '' ); ?></td>
						<td><?php echo (int) $p['year']; ?></td>
						<td><?php echo esc_html( number_format( (float) $p['amount'], 2, ',', '.' ) ); ?> €</td>
						<td><?php echo esc_html( $p['payment_method'] ); ?></td>
						<td><?php echo esc_html( $p['status'] ); ?></td>
						<td><?php echo $p['payment_date'] ? esc_html( mysql2date( 'd.m.Y', $p['payment_date'] ) ) : '—'; ?></td>
						<td>
							<?php if ( 'open' === $p['status'] ) :
								$url = wp_nonce_url(
									add_query_arg(
										[
											'action'     => 'vm_mark_paid',
											'payment_id' => $p['id'],
											'year'       => $year,
										],
										admin_url( 'admin-post.php' )
									),
									'vm_mark_paid_' . $p['id']
								);
								?>
								<a href="<?php echo esc_url( $url ); ?>" class="button button-small"><?php esc_html_e( 'Als bezahlt markieren', 'vereinsmanager' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
