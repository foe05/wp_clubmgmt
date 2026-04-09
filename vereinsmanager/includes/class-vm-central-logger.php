<?php
/**
 * Zentrales Logging an log.broetzens.de.
 *
 * @package Vereinsmanager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class VM_Central_Logger
 *
 * Sendet ausgewählte Plugin-Events an die zentrale Logging-API.
 * Versendet niemals personenbezogene Daten – nur IDs, Zähler, technische Infos.
 */
class VM_Central_Logger {

	/**
	 * Sendet ein Event an die Central Logging API.
	 *
	 * Fehlschläge werden geloggt (error_log), blockieren aber NIEMALS
	 * den normalen Plugin-Betrieb (fire-and-forget via wp_remote_post).
	 *
	 * @param string $event   Event-Name (z.B. "member_created").
	 * @param array  $payload Optionale Nutzdaten (keine PII!).
	 *
	 * @return void
	 */
	public static function log( string $event, array $payload = [] ): void {
		$enabled = get_option( 'vm_logging_enabled', '0' );
		$api_key = get_option( 'vm_logging_api_key', '' );
		$api_url = get_option( 'vm_logging_api_url', 'https://log.broetzens.de' );

		if ( '1' !== (string) $enabled || empty( $api_key ) ) {
			return;
		}

		$body = wp_json_encode(
			[
				'tool'         => 'vereinsmanager',
				'tool_version' => defined( 'VM_VERSION' ) ? VM_VERSION : 'unknown',
				'instance'     => home_url(),
				'event'        => $event,
				'payload'      => $payload,
			]
		);

		if ( false === $body ) {
			error_log( '[Vereinsmanager] Central-Logger: JSON-Encoding fehlgeschlagen.' );
			return;
		}

		$response = wp_remote_post(
			rtrim( (string) $api_url, '/' ) . '/api/log',
			[
				'headers'  => [
					'Content-Type' => 'application/json',
					'X-Api-Key'    => (string) $api_key,
				],
				'body'     => $body,
				'timeout'  => 5,
				'blocking' => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[Vereinsmanager] Central-Logger fehlgeschlagen: ' . $response->get_error_message() );
		}
	}
}
