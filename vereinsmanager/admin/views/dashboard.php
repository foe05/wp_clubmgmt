<?php
/**
 * Dashboard-View.
 *
 * @package Vereinsmanager
 *
 * @var array $counts
 * @var int   $current_year
 * @var int   $new_this_year
 * @var int   $exits
 * @var array $totals
 * @var int   $sepa_count
 * @var array $years
 */

defined( 'ABSPATH' ) || exit;

$max = max( max( array_values( $years ) ), 1 );
?>
<div class="wrap vm-wrap">
	<h1><?php esc_html_e( 'Vereinsmanager – Dashboard', 'vereinsmanager' ); ?></h1>

	<div class="vm-cards">
		<div class="vm-card">
			<h3><?php esc_html_e( 'Aktive Mitglieder', 'vereinsmanager' ); ?></h3>
			<p class="vm-big"><?php echo (int) $counts['active']; ?></p>
		</div>
		<div class="vm-card">
			<h3><?php esc_html_e( 'Interessenten', 'vereinsmanager' ); ?></h3>
			<p class="vm-big"><?php echo (int) $counts['prospect']; ?></p>
		</div>
		<div class="vm-card">
			<h3><?php esc_html_e( 'Ehemalige', 'vereinsmanager' ); ?></h3>
			<p class="vm-big"><?php echo (int) $counts['former']; ?></p>
		</div>
		<div class="vm-card">
			<h3><?php printf( esc_html__( 'Neueintritte %d', 'vereinsmanager' ), (int) $current_year ); ?></h3>
			<p class="vm-big"><?php echo (int) $new_this_year; ?></p>
		</div>
		<div class="vm-card">
			<h3><?php printf( esc_html__( 'Austritte %d', 'vereinsmanager' ), (int) $current_year ); ?></h3>
			<p class="vm-big"><?php echo (int) $exits; ?></p>
		</div>
		<div class="vm-card">
			<h3><?php esc_html_e( 'Aktive SEPA-Mandate', 'vereinsmanager' ); ?></h3>
			<p class="vm-big"><?php echo (int) $sepa_count; ?></p>
		</div>
	</div>

	<div class="vm-cards">
		<div class="vm-card vm-card-wide">
			<h3><?php printf( esc_html__( 'Beitragseinnahmen %d', 'vereinsmanager' ), (int) $current_year ); ?></h3>
			<p>
				<strong><?php esc_html_e( 'Soll', 'vereinsmanager' ); ?>:</strong> <?php echo esc_html( number_format( $totals['soll'], 2, ',', '.' ) ); ?> €<br />
				<strong><?php esc_html_e( 'Ist', 'vereinsmanager' ); ?>:</strong> <?php echo esc_html( number_format( $totals['ist'], 2, ',', '.' ) ); ?> €<br />
				<strong><?php esc_html_e( 'Offen', 'vereinsmanager' ); ?>:</strong> <?php echo esc_html( number_format( $totals['open'], 2, ',', '.' ) ); ?> €
				(<?php echo (int) $totals['count_open']; ?>)
			</p>
		</div>
	</div>

	<?php if ( ! empty( $upcoming_events ) ) : ?>
		<h2><?php esc_html_e( 'Nächste Veranstaltungen', 'vereinsmanager' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Titel', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Typ', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Datum', 'vereinsmanager' ); ?></th>
					<th><?php esc_html_e( 'Ort', 'vereinsmanager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php $types = VM_Events::types(); foreach ( $upcoming_events as $ev ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-event-edit&id=' . (int) $ev['id'] ) ); ?>"><?php echo esc_html( (string) $ev['title'] ); ?></a></td>
						<td><?php echo esc_html( $types[ $ev['event_type'] ] ?? $ev['event_type'] ); ?></td>
						<td><?php echo esc_html( mysql2date( 'd.m.Y H:i', $ev['start_datetime'] ) ); ?></td>
						<td><?php echo esc_html( (string) $ev['location'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( ! empty( $upcoming_bds ) || ! empty( $upcoming_jubs ) ) : ?>
		<div class="vm-cards">
			<?php if ( ! empty( $upcoming_bds ) ) : ?>
				<div class="vm-card vm-card-wide">
					<h3><?php esc_html_e( 'Bevorstehende Geburtstage (30 Tage)', 'vereinsmanager' ); ?></h3>
					<ul>
						<?php foreach ( array_slice( $upcoming_bds, 0, 10 ) as $r ) : ?>
							<li><?php echo esc_html( VM_Members::format_name( $r ) ); ?> – <?php echo esc_html( mysql2date( 'd.m.', $r['next_birthday'] ) ); ?> (<?php echo (int) $r['turning_age']; ?>)</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $upcoming_jubs ) ) : ?>
				<div class="vm-card vm-card-wide">
					<h3><?php esc_html_e( 'Bevorstehende Mitgliedschaftsjubiläen (30 Tage)', 'vereinsmanager' ); ?></h3>
					<ul>
						<?php foreach ( array_slice( $upcoming_jubs, 0, 10 ) as $r ) : ?>
							<li>
								<?php echo esc_html( VM_Members::format_name( $r ) ); ?> –
								<?php echo esc_html( mysql2date( 'd.m.', $r['next_anniversary'] ) ); ?>
								(<?php echo (int) $r['years']; ?> <?php esc_html_e( 'Jahre', 'vereinsmanager' ); ?><?php echo $r['is_round'] ? ' ⭐' : ''; ?>)
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Mitgliederentwicklung (letzte 5 Jahre)', 'vereinsmanager' ); ?></h2>
	<div class="vm-bar-chart">
		<?php foreach ( $years as $y => $count ) :
			$pct = $max > 0 ? round( ( $count / $max ) * 100 ) : 0;
			?>
			<div class="vm-bar">
				<div class="vm-bar-fill" style="height: <?php echo (int) $pct; ?>%;" title="<?php echo (int) $count; ?>"></div>
				<div class="vm-bar-label"><strong><?php echo (int) $count; ?></strong><br /><?php echo (int) $y; ?></div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
