<?php
/**
 * Dokumentenablage.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Documents
 */
class VM_Documents {

	/**
	 * Erlaubte Extensions.
	 */
	const ALLOWED_EXT = [ 'pdf', 'doc', 'docx', 'odt', 'jpg', 'jpeg', 'png' ];

	/**
	 * Tabellenname.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vm_documents';
	}

	/**
	 * Kategorien.
	 *
	 * @return array<string,string>
	 */
	public static function categories(): array {
		return [
			'satzung'    => __( 'Satzung', 'vereinsmanager' ),
			'protokoll'  => __( 'Protokoll', 'vereinsmanager' ),
			'bescheid'   => __( 'Bescheide', 'vereinsmanager' ),
			'sonstiges'  => __( 'Sonstiges', 'vereinsmanager' ),
		];
	}

	/**
	 * Sichtbarkeiten.
	 *
	 * @return array<string,string>
	 */
	public static function visibilities(): array {
		return [
			'admin'       => __( 'Nur Admins', 'vereinsmanager' ),
			'board'       => __( 'Vorstand', 'vereinsmanager' ),
			'all_members' => __( 'Alle Mitglieder', 'vereinsmanager' ),
		];
	}

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_vm_doc_upload', [ __CLASS__, 'handle_upload' ] );
		add_action( 'admin_post_vm_doc_delete', [ __CLASS__, 'handle_delete' ] );
		add_action( 'admin_post_vm_doc_download', [ __CLASS__, 'handle_download' ] );
	}

	/**
	 * Absoluten Upload-Pfad liefern.
	 *
	 * @return string
	 */
	public static function upload_dir(): string {
		$u = wp_upload_dir();
		return trailingslashit( $u['basedir'] ) . 'vereinsmanager';
	}

	/**
	 * Render-Seite.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_manage_documents' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}

		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$documents = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY uploaded_at DESC LIMIT 500", ARRAY_A );

		include VM_PLUGIN_DIR . 'admin/views/documents.php';
	}

	/**
	 * Handler: Upload.
	 *
	 * @return void
	 */
	public static function handle_upload(): void {
		if ( ! current_user_can( 'vm_manage_documents' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		check_admin_referer( 'vm_doc_upload' );

		if ( empty( $_FILES['vm_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'vm-documents', 'vm_notice' => 'no_file' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$file = $_FILES['vm_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$name = sanitize_file_name( (string) $file['name'] );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::ALLOWED_EXT, true ) ) {
			wp_die( esc_html__( 'Dateityp nicht erlaubt.', 'vereinsmanager' ) );
		}

		$title      = isset( $_POST['vm_title'] ) ? sanitize_text_field( wp_unslash( $_POST['vm_title'] ) ) : $name;
		$category   = isset( $_POST['vm_category'] ) ? sanitize_key( wp_unslash( $_POST['vm_category'] ) ) : 'sonstiges';
		$visibility = isset( $_POST['vm_visibility'] ) ? sanitize_key( wp_unslash( $_POST['vm_visibility'] ) ) : 'admin';
		$member_id  = isset( $_POST['vm_member_id'] ) ? (int) $_POST['vm_member_id'] : 0;
		$event_id   = isset( $_POST['vm_event_id'] ) ? (int) $_POST['vm_event_id'] : 0;

		if ( ! isset( self::categories()[ $category ] ) ) {
			$category = 'sonstiges';
		}
		if ( ! isset( self::visibilities()[ $visibility ] ) ) {
			$visibility = 'admin';
		}

		$dir = self::upload_dir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Eindeutiger Dateiname via Zeitstempel.
		$target_rel  = gmdate( 'Y' ) . '/' . wp_generate_uuid4() . '.' . $ext;
		$target_abs  = $dir . '/' . $target_rel;
		$target_dir  = dirname( $target_abs );
		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}
		if ( ! @move_uploaded_file( $file['tmp_name'], $target_abs ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			wp_die( esc_html__( 'Upload fehlgeschlagen.', 'vereinsmanager' ) );
		}

		global $wpdb;
		$wpdb->insert(
			self::table(),
			[
				'title'       => $title,
				'category'    => $category,
				'file_path'   => $target_rel,
				'mime_type'   => (string) ( $file['type'] ?? '' ),
				'file_size'   => (int) ( $file['size'] ?? 0 ),
				'member_id'   => $member_id ?: null,
				'event_id'    => $event_id ?: null,
				'visibility'  => $visibility,
				'uploaded_by' => get_current_user_id(),
				'uploaded_at' => current_time( 'mysql' ),
			]
		);

		VM_Central_Logger::log(
			'document_uploaded',
			[
				'doc_id'     => (int) $wpdb->insert_id,
				'category'   => $category,
				'visibility' => $visibility,
				'file_size'  => (int) ( $file['size'] ?? 0 ),
			]
		);

		wp_safe_redirect( add_query_arg( [ 'page' => 'vm-documents', 'vm_notice' => 'uploaded' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Binary aus Protokoll-PDF-Export direkt speichern.
	 *
	 * @param int    $event_id Event.
	 * @param string $filename Dateiname.
	 * @param string $binary   Datei-Inhalt.
	 * @param string $mime     MIME-Typ.
	 * @return int|false Dokument-ID oder false.
	 */
	public static function store_binary_for_event( int $event_id, string $filename, string $binary, string $mime = 'application/pdf' ) {
		$dir = self::upload_dir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$rel = gmdate( 'Y' ) . '/' . wp_generate_uuid4() . '-' . sanitize_file_name( $filename );
		$abs = $dir . '/' . $rel;
		if ( ! file_exists( dirname( $abs ) ) ) {
			wp_mkdir_p( dirname( $abs ) );
		}
		if ( false === file_put_contents( $abs, $binary ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}

		global $wpdb;
		$wpdb->insert(
			self::table(),
			[
				'title'       => $filename,
				'category'    => 'protokoll',
				'file_path'   => $rel,
				'mime_type'   => $mime,
				'file_size'   => strlen( $binary ),
				'event_id'    => $event_id,
				'visibility'  => 'board',
				'uploaded_by' => get_current_user_id(),
				'uploaded_at' => current_time( 'mysql' ),
			]
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Handler: Löschen.
	 *
	 * @return void
	 */
	public static function handle_delete(): void {
		if ( ! current_user_can( 'vm_manage_documents' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vereinsmanager' ) );
		}
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'vm_doc_delete_' . $id );

		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( $row ) {
			$abs = self::upload_dir() . '/' . ltrim( (string) $row['file_path'], '/' );
			if ( file_exists( $abs ) ) {
				@unlink( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			}
			$wpdb->delete( $table, [ 'id' => $id ] );
			VM_Central_Logger::log( 'document_deleted', [ 'doc_id' => $id ] );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'vm-documents', 'vm_notice' => 'deleted' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handler: Download (mit Visibility-Prüfung).
	 *
	 * @return void
	 */
	public static function handle_download(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'vm_doc_download_' . $id );

		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			wp_die( esc_html__( 'Dokument nicht gefunden.', 'vereinsmanager' ) );
		}

		if ( ! self::current_user_may_see( $row ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Kein Zugriff auf dieses Dokument.', 'vereinsmanager' ) );
		}

		$abs = self::upload_dir() . '/' . ltrim( (string) $row['file_path'], '/' );
		if ( ! file_exists( $abs ) ) {
			wp_die( esc_html__( 'Datei fehlt.', 'vereinsmanager' ) );
		}

		VM_Central_Logger::log( 'document_downloaded', [ 'doc_id' => $id ] );

		nocache_headers();
		header( 'Content-Type: ' . ( $row['mime_type'] ?: 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . basename( (string) $row['title'] ) . '"' );
		header( 'Content-Length: ' . filesize( $abs ) );
		readfile( $abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Visibility-Prüfung für den aktuellen User.
	 *
	 * @param array $row Dokument.
	 * @return bool
	 */
	private static function current_user_may_see( array $row ): bool {
		if ( current_user_can( 'vm_manage_documents' ) ) {
			return true;
		}
		$vis = $row['visibility'] ?? 'admin';
		if ( 'admin' === $vis ) {
			return false;
		}
		if ( 'board' === $vis ) {
			return current_user_can( 'vm_manage_events' );
		}
		if ( 'all_members' === $vis ) {
			return current_user_can( 'vm_view_members' );
		}
		return false;
	}
}
