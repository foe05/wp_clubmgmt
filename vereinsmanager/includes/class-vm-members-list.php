<?php
/**
 * WP_List_Table für Mitglieder.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class VM_Members_List_Table
 */
class VM_Members_List_Table extends WP_List_Table {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'member',
				'plural'   => 'members',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Spalten definieren.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return [
			'cb'              => '<input type="checkbox" />',
			'member_number'   => __( 'Nr.', 'vereinsmanager' ),
			'name'            => __( 'Name', 'vereinsmanager' ),
			'email'           => __( 'E-Mail', 'vereinsmanager' ),
			'status'          => __( 'Status', 'vereinsmanager' ),
			'membership_type' => __( 'Beitragsart', 'vereinsmanager' ),
			'entry_date'      => __( 'Eintritt', 'vereinsmanager' ),
		];
	}

	/**
	 * Sortierbare Spalten.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return [
			'member_number' => [ 'member_number', false ],
			'name'          => [ 'last_name', true ],
			'status'        => [ 'status', false ],
			'entry_date'    => [ 'entry_date', false ],
		];
	}

	/**
	 * Checkbox-Spalte.
	 *
	 * @param array $item Mitglied.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="member_ids[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * Default-Spaltenausgabe.
	 *
	 * @param array  $item        Mitglied.
	 * @param string $column_name Spaltenname.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'member_number':
				return esc_html( $item['member_number'] ?: '—' );
			case 'email':
				return esc_html( $item['email'] ?: '—' );
			case 'status':
				$labels = VM_Members::status_labels();
				$label  = $labels[ $item['status'] ] ?? $item['status'];
				return '<span class="vm-status vm-status-' . esc_attr( $item['status'] ) . '">' . esc_html( $label ) . '</span>';
			case 'membership_type':
				return 'reduced' === $item['membership_type'] ? esc_html__( 'Reduziert', 'vereinsmanager' ) : esc_html__( 'Standard', 'vereinsmanager' );
			case 'entry_date':
				return $item['entry_date'] ? esc_html( mysql2date( 'd.m.Y', $item['entry_date'] ) ) : '—';
			default:
				return '';
		}
	}

	/**
	 * Name-Spalte mit Aktionen.
	 *
	 * @param array $item Mitglied.
	 * @return string
	 */
	public function column_name( $item ): string {
		$edit_url = add_query_arg(
			[
				'page' => 'vm-member-edit',
				'id'   => $item['id'],
			],
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'vm_delete_member',
					'id'     => $item['id'],
				],
				admin_url( 'admin-post.php' )
			),
			'vm_delete_member_' . $item['id']
		);

		$name = VM_Members::format_name( $item );

		$actions = [
			'edit' => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Bearbeiten', 'vereinsmanager' ) ),
		];

		if ( current_user_can( 'vm_delete_members' ) ) {
			$actions['delete'] = sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Wirklich löschen?', 'vereinsmanager' ) ),
				esc_html__( 'Löschen', 'vereinsmanager' )
			);
		}

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $name ?: __( '(ohne Namen)', 'vereinsmanager' ) ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Bulk-Actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		$actions = [
			'set_active'   => __( 'Status: Aktiv setzen', 'vereinsmanager' ),
			'set_prospect' => __( 'Status: Interessent setzen', 'vereinsmanager' ),
			'set_former'   => __( 'Status: Ehemalig setzen', 'vereinsmanager' ),
		];
		if ( current_user_can( 'vm_delete_members' ) ) {
			$actions['delete'] = __( 'Löschen', 'vereinsmanager' );
		}
		return $actions;
	}

	/**
	 * Items vorbereiten.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = 25;
		$paged    = $this->get_pagenum();

		$status          = isset( $_GET['vm_status'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type            = isset( $_GET['vm_type'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby         = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'last_name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order           = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'ASC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = VM_Members::query(
			[
				'status'          => $status,
				'membership_type' => $type,
				'search'          => $search,
				'orderby'         => $orderby,
				'order'           => $order,
				'per_page'        => $per_page,
				'paged'           => $paged,
			]
		);

		$this->items = $result['items'];

		$this->set_pagination_args(
			[
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			]
		);

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	/**
	 * Filter-Dropdowns oberhalb der Tabelle.
	 *
	 * @param string $which "top" oder "bottom".
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$status = isset( $_GET['vm_status'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type   = isset( $_GET['vm_type'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<select name="vm_status">
				<option value=""><?php esc_html_e( 'Alle Status', 'vereinsmanager' ); ?></option>
				<?php foreach ( VM_Members::status_labels() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="vm_type">
				<option value=""><?php esc_html_e( 'Alle Beitragsarten', 'vereinsmanager' ); ?></option>
				<option value="standard" <?php selected( $type, 'standard' ); ?>><?php esc_html_e( 'Standard', 'vereinsmanager' ); ?></option>
				<option value="reduced" <?php selected( $type, 'reduced' ); ?>><?php esc_html_e( 'Reduziert', 'vereinsmanager' ); ?></option>
			</select>
			<?php submit_button( __( 'Filtern', 'vereinsmanager' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
