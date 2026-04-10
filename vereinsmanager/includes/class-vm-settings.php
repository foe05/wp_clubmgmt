<?php
/**
 * Plugin-Einstellungen.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Settings
 */
class VM_Settings {

	/**
	 * Liste aller Settings mit Sanitize-Callback.
	 *
	 * @return array<string,callable|string>
	 */
	public static function fields(): array {
		return [
			'vm_club_name'            => 'sanitize_text_field',
			'vm_club_street'          => 'sanitize_text_field',
			'vm_club_postal_code'     => 'sanitize_text_field',
			'vm_club_city'            => 'sanitize_text_field',
			'vm_club_tax_number'      => 'sanitize_text_field',
			'vm_club_tax_office'      => 'sanitize_text_field',
			'vm_club_exemption_date'  => 'sanitize_text_field',
			'vm_creditor_id'          => 'sanitize_text_field',
			'vm_fee_standard'         => 'sanitize_text_field',
			'vm_fee_reduced'          => 'sanitize_text_field',
			'vm_member_number_prefix' => 'sanitize_text_field',
			'vm_retention_years'      => 'absint',
			'vm_email_from_name'      => 'sanitize_text_field',
			'vm_email_from_address'   => 'sanitize_email',
			'vm_logging_api_url'      => 'esc_url_raw',
			'vm_logging_api_key'      => 'sanitize_text_field',
			'vm_logging_enabled'      => 'sanitize_text_field',
		];
	}

	/**
	 * Settings registrieren.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', [ __CLASS__, 'admin_init' ] );
	}

	/**
	 * register_setting() für jedes Feld.
	 *
	 * @return void
	 */
	public static function admin_init(): void {
		foreach ( self::fields() as $key => $sanitize ) {
			register_setting(
				'vm_settings_group',
				$key,
				[
					'sanitize_callback' => $sanitize,
				]
			);
		}
	}

	/**
	 * Settings-View rendern.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'vm_manage_settings' ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung für diese Seite.', 'vereinsmanager' ) );
		}

		include VM_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
