<?php
/**
 * Event-Liste.
 *
 * @package Vereinsmanager
 *
 * @var VM_Events_List_Table $list_table
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vm-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Veranstaltungen', 'vereinsmanager' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=vm-event-edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Neu anlegen', 'vereinsmanager' ); ?></a>
	<hr class="wp-header-end" />

	<?php
	if ( isset( $_GET['vm_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice   = sanitize_text_field( wp_unslash( $_GET['vm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = [ 'deleted' => __( 'Veranstaltung gelöscht.', 'vereinsmanager' ) ];
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}
	?>

	<form method="get">
		<input type="hidden" name="page" value="vm-events" />
		<?php $list_table->search_box( __( 'Suchen', 'vereinsmanager' ), 'vm-event-search' ); ?>
		<?php $list_table->display(); ?>
	</form>
</div>
