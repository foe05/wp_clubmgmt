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
