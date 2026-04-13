<?php
/**
 * WP_List_Table für Veranstaltungen.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class VM_Events_List_Table
 */
class VM_Events_List_Table extends WP_List_Table {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'event',
				'plural'   => 'events',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Spalten.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return [
			'title'          => __( 'Titel', 'vereinsmanager' ),
			'event_type'     => __( 'Typ', 'vereinsmanager' ),
			'start_datetime' => __( 'Datum', 'vereinsmanager' ),
			'location'       => __( 'Ort', 'vereinsmanager' ),
			'status'         => __( 'Status', 'vereinsmanager' ),
			'attendees'      => __( 'Teilnehmer', 'vereinsmanager' ),
		];
	}

	/**
	 * Sortierbare Spalten.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return [
			'title'          => [ 'title', false ],
			'event_type'     => [ 'event_type', false ],
			'start_datetime' => [ 'start_datetime', true ],
			'status'         => [ 'status', false ],
		];
	}

	/**
	 * Default-Spalte.
	 *
	 * @param array  $item        Item.
	 * @param string $column_name Spaltenname.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'event_type':
				$types = VM_Events::types();
				return esc_html( $types[ $item['event_type'] ] ?? $item['event_type'] );
			case 'start_datetime':
				return $item['start_datetime'] ? esc_html( mysql2date( 'd.m.Y H:i', $item['start_datetime'] ) ) : '—';
			case 'location':
				return esc_html( (string) ( $item['location'] ?? '' ) );
			case 'status':
				$labels = VM_Events::statuses();
				return '<span class="vm-status vm-status-' . esc_attr( $item['status'] ) . '">' . esc_html( $labels[ $item['status'] ] ?? $item['status'] ) . '</span>';
			case 'attendees':
				$stats = VM_Event_Attendees::stats_for_event( (int) $item['id'] );
				return sprintf( '%d / %d', (int) $stats['yes'], (int) $stats['total'] );
			default:
				return '';
		}
	}

	/**
	 * Titel-Spalte mit Aktionen.
	 *
	 * @param array $item Event.
	 * @return string
	 */
	public function column_title( $item ): string {
		$edit = add_query_arg( [ 'page' => 'vm-event-edit', 'id' => $item['id'] ], admin_url( 'admin.php' ) );
		$att  = add_query_arg( [ 'page' => 'vm-event-attendees', 'id' => $item['id'] ], admin_url( 'admin.php' ) );
		$inv  = add_query_arg( [ 'page' => 'vm-event-invite', 'id' => $item['id'] ], admin_url( 'admin.php' ) );
		$prot = add_query_arg( [ 'page' => 'vm-event-protocol', 'id' => $item['id'] ], admin_url( 'admin.php' ) );
		$del  = wp_nonce_url(
			add_query_arg( [ 'action' => 'vm_event_delete', 'id' => $item['id'] ], admin_url( 'admin-post.php' ) ),
			'vm_event_delete_' . $item['id']
		);

		$actions = [
			'edit'      => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Bearbeiten', 'vereinsmanager' ) ),
			'attendees' => sprintf( '<a href="%s">%s</a>', esc_url( $att ), esc_html__( 'Teilnehmer', 'vereinsmanager' ) ),
			'invite'    => sprintf( '<a href="%s">%s</a>', esc_url( $inv ), esc_html__( 'Einladen', 'vereinsmanager' ) ),
			'protocol'  => sprintf( '<a href="%s">%s</a>', esc_url( $prot ), esc_html__( 'Protokoll', 'vereinsmanager' ) ),
		];
		if ( current_user_can( 'vm_manage_events' ) ) {
			$actions['delete'] = sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $del ),
				esc_js( __( 'Wirklich löschen?', 'vereinsmanager' ) ),
				esc_html__( 'Löschen', 'vereinsmanager' )
			);
		}

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit ),
			esc_html( $item['title'] ?: __( '(ohne Titel)', 'vereinsmanager' ) ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Items vorbereiten.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = 25;
		$paged    = $this->get_pagenum();

		$type    = isset( $_GET['vm_type'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status  = isset( $_GET['vm_status'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$year    = isset( $_GET['vm_year'] ) ? (int) $_GET['vm_year'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'start_datetime'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = VM_Events::query(
			[
				'type'     => $type,
				'status'   => $status,
				'year'     => $year ?: '',
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'paged'    => $paged,
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
	 * Filter oberhalb der Tabelle.
	 *
	 * @param string $which top/bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$type   = isset( $_GET['vm_type'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['vm_status'] ) ? sanitize_text_field( wp_unslash( $_GET['vm_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$year   = isset( $_GET['vm_year'] ) ? (int) $_GET['vm_year'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<select name="vm_type">
				<option value=""><?php esc_html_e( 'Alle Typen', 'vereinsmanager' ); ?></option>
				<?php foreach ( VM_Events::types() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="vm_status">
				<option value=""><?php esc_html_e( 'Alle Status', 'vereinsmanager' ); ?></option>
				<?php foreach ( VM_Events::statuses() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="number" name="vm_year" placeholder="<?php esc_attr_e( 'Jahr', 'vereinsmanager' ); ?>" value="<?php echo $year ? esc_attr( $year ) : ''; ?>" class="small-text" />
			<?php submit_button( __( 'Filtern', 'vereinsmanager' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
