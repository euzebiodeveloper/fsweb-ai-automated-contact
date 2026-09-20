<?php
/**
 * FSWEB Update Checker — Metadata Repository
 *
 * Responsible for fetching plugin metadata from a remote JSON endpoint,
 * caching it in wp_options, and validating the response.
 *
 * @package   FSWEB_Update_Checker
 * @version   1.0.0
 * @link      https://flowsyncweb.com
 * @license   GPL-2.0+
 */

namespace FSWEB\UpdateChecker\AiAutomatedContact\V1;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Metadata Repository — fetch, cache and validate remote plugin metadata.
 */
class Metadata {

	/**
	 * Maximum age of cached metadata in seconds (2 hours).
	 *
	 * After this time, get_cached() returns null so stale data
	 * is never served — the next cron tick or manual check will
	 * attempt a fresh fetch.
	 *
	 * @var int
	 */
	const CACHE_TTL = 2 * HOUR_IN_SECONDS;

	/**
	 * Remote JSON metadata URL.
	 *
	 * @var string
	 */
	private $metadata_url;

	/**
	 * Full URL for the details endpoint.
	 *
	 * @var string
	 */
	private $details_url;

	/**
	 * Full URL for the readme endpoint (returns raw README.txt).
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $readme_url;

	/**
	 * wp_options key for cached metadata.
	 *
	 * @var string
	 */
	private $option_name;

	/**
	 * Plugin slug (used for HTTP args filter).
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * License key for authenticated update requests.
	 *
	 * @var string
	 */
	private $license_key = '';

	/**
	 * Installed plugin version (sent in POST body for validation).
	 *
	 * @var string
	 */
	private $plugin_version = '';

	/**
	 * Constructor.
	 *
	 * @param string $metadata_url Remote JSON metadata URL.
	 * @param string $slug         Plugin slug.
	 * @param string $option_name  wp_options key for caching.
	 */
	public function __construct( $metadata_url, $slug, $option_name ) {
		$this->metadata_url = esc_url_raw( $metadata_url );
		$this->details_url  = esc_url_raw( rtrim( $metadata_url, '/' ) . '/details' );
		$this->readme_url   = esc_url_raw( rtrim( $metadata_url, '/' ) . '/readme' );
		$this->slug         = $slug;
		$this->option_name  = $option_name;
	}

	/**
	 * Set the license key to be sent with every update request.
	 *
	 * Also clears any stale license error that may have been recorded
	 * before the license was activated.
	 *
	 * @param string $license_key The license key.
	 * @return void
	 */
	public function set_license( $license_key ) {
		$this->license_key = sanitize_text_field( $license_key );

		// If we have a key, clear any stale error from a previous
		// failed check (e.g. cron ran before activation).
		if ( ! empty( $this->license_key ) ) {
			delete_option( $this->option_name . '_license_error' );
		}
	}

	/**
	 * Set the installed plugin version (sent in POST body).
	 *
	 * @param string $version Installed plugin version.
	 * @return void
	 */
	public function set_plugin_version( $version ) {
		$this->plugin_version = sanitize_text_field( $version );
	}

	/**
	 * Get the last license error code, if any.
	 *
	 * Returns 'invalid_key' for 401, 'forbidden' for 403, or empty string if OK.
	 *
	 * @return string
	 */
	public function get_last_license_error() {
		$error = get_option( $this->option_name . '_license_error', '' );
		return is_string( $error ) ? $error : '';
	}

	/**
	 * Get the last server error message, if any.
	 *
	 * Returns a generic error message when the last fetch failed due to
	 * a network error, HTTP 5xx, invalid JSON response, or any other
	 * non-license server-side problem.
	 *
	 * @return string Error message or empty string if the last fetch succeeded.
	 */
	public function get_last_server_error() {
		$error = get_option( $this->option_name . '_server_error', '' );
		return is_string( $error ) ? $error : '';
	}

	/**
	 * Check whether a valid license is configured.
	 *
	 * Returns true only when a license key has been set AND no license
	 * error (401/403) is currently recorded.
	 *
	 * @return bool
	 */
	public function has_license() {
		return ! empty( $this->license_key )
			&& empty( $this->get_last_license_error() );
	}

	/**
	 * Get the configured license key.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_license() {
		return $this->license_key;
	}

	/**
	 * Force-fetch metadata from the remote endpoint and cache it.
	 *
	 * @return object|null Decoded JSON metadata, or null on failure.
	 */
	public function fetch_and_cache() {
		$response = $this->fetch( $this->metadata_url );

		if ( is_wp_error( $response ) ) {
			$this->log( 'fetch_error', $response->get_error_message() );
			update_option(
				$this->option_name . '_server_error',
				__( 'Update server is temporarily unreachable. Please try again later.', 'fsweb-mercadopago' )
			);
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 401 — no license key or invalid key.
		if ( 401 === $code ) {
			$this->clear_cache();
			update_option( $this->option_name . '_license_error', 'invalid_key' );
			$this->log( 'license_invalid', 'Update server rejected the license key (401).' );
			return null;
		}

		// 403 — license exists but domain mismatch or expired.
		if ( 403 === $code ) {
			$this->clear_cache();
			update_option( $this->option_name . '_license_error', 'forbidden' );
			$this->log( 'license_forbidden', 'Update server denied access — domain mismatch or license expired (403).' );
			return null;
		}

		if ( 200 !== $code ) {
			$this->log( 'http_' . $code, 'Metadata endpoint returned HTTP ' . $code );
			update_option(
				$this->option_name . '_server_error',
				__( 'Update server returned an error. Please try again later.', 'fsweb-mercadopago' )
			);
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_object( $body ) || empty( $body->version ) ) {
			$this->log( 'invalid_metadata', 'Response is missing version field or is not valid JSON.' );

			// Try to extract a server-provided error message from the response.
			$server_message = '';
			if ( is_object( $body ) ) {
				if ( ! empty( $body->data->message ) ) {
					$server_message = sanitize_text_field( $body->data->message );
				} elseif ( ! empty( $body->message ) ) {
					$server_message = sanitize_text_field( $body->message );
				}
			}

			update_option(
				$this->option_name . '_server_error',
				$server_message ?: __( 'Update server returned an invalid response. Please try again later.', 'fsweb-mercadopago' )
			);
			return null;
		}

		// Success — clear any previous license and server errors.
		delete_option( $this->option_name . '_license_error' );
		delete_option( $this->option_name . '_server_error' );

		update_option( $this->option_name, $body );
		update_option( $this->option_name . '_last_check', time() );

		return $body;
	}

	/**
	 * Get cached metadata from wp_options if still fresh.
	 *
	 * Returns null when the cache is older than CACHE_TTL (2 hours),
	 * forcing the next cron tick or manual check to refresh it.
	 *
	 * @return object|null
	 */
	public function get_cached() {
		$metadata = get_option( $this->option_name );
		if ( ! is_object( $metadata ) ) {
			return null;
		}

		$last_check = (int) get_option( $this->option_name . '_last_check', 0 );
		if ( ( time() - $last_check ) > self::CACHE_TTL ) {
			// Cache expired — don't serve stale data.
			return null;
		}

		return $metadata;
	}

	/**
	 * Delete cached metadata, details, last-check timestamp, and license error.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_option( $this->option_name );
		delete_option( $this->option_name . '_last_check' );
		delete_option( $this->option_name . '_details' );
		delete_option( $this->option_name . '_license_error' );
		delete_option( $this->option_name . '_server_error' );
	}

	/**
	 * Clear only the error flags (license + server), keeping cached metadata intact.
	 *
	 * Use this when valid cached metadata exists but stale error flags
	 * from a previous transient failure are blocking update injection.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function clear_errors() {
		delete_option( $this->option_name . '_license_error' );
		delete_option( $this->option_name . '_server_error' );
	}

	/**
	 * Delete the cached README transient.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function clear_readme_cache() {
		delete_transient( $this->option_name . '_readme' );
	}

	/**
	 * Fetch details from the /details endpoint (sections, banners, icons).
	 *
	 * @deprecated 1.1.0 No longer used internally — "View Details" now uses
	 *             local README.txt + plugin assets exclusively. The /details
	 *             API route is no longer required. Kept for backward compat.
	 *
	 * @param bool $force Bypass cache and force a fresh fetch.
	 * @return object|null Object with `sections`, `banners`, `icons` — or null on failure.
	 */
	public function fetch_details( $force = false ) {
		$cache_key = $this->option_name . '_details';

		if ( ! $force ) {
			$cached = get_option( $cache_key );
			if ( is_object( $cached ) ) {
				return $cached;
			}
		}

		$response = $this->fetch( $this->details_url );

		if ( is_wp_error( $response ) ) {
			$this->log( 'details_fetch_error', $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->log( 'details_http_' . $code, 'Details endpoint returned HTTP ' . $code );
			return null;
		}

		$raw_body = wp_remote_retrieve_body( $response );
		$body     = json_decode( $raw_body );

		if ( ! is_object( $body ) ) {
			$this->log( 'details_invalid_json', 'Details response is not valid JSON.' );
			return null;
		}

		$result           = new \stdClass();
		$result->sections = null;
		$result->banners  = ! empty( $body->banners ) ? $body->banners : null;
		$result->icons    = ! empty( $body->icons ) ? $body->icons : null;

		// Sections may come as a nested object or as top-level fields.
		if ( ! empty( $body->sections ) ) {
			$result->sections = (array) $body->sections;
		} else {
			$flat_sections = array();
			foreach ( array( 'description', 'changelog', 'installation', 'faq', 'screenshots' ) as $key ) {
				if ( ! empty( $body->$key ) ) {
					$flat_sections[ $key ] = $body->$key;
				}
			}
			if ( ! empty( $flat_sections ) ) {
				$result->sections = $flat_sections;
			}
		}

		// Cache the result for 1 hour (avoids HTTP on every tab click).
		update_option( $cache_key, $result );

		return $result;
	}

	/**
	 * Get the metadata URL.
	 *
	 * @return string
	 */
	public function get_metadata_url() {
		return $this->metadata_url;
	}

	/**
	 * Get the readme URL.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_readme_url() {
		return $this->readme_url;
	}

	/**
	 * Fetch README.txt from the remote /readme endpoint.
	 *
	 * Uses a transient to cache the result for 24 hours, avoiding
	 * an HTTP request on every "View Details" click. The endpoint
	 * is public (no license key required) and returns the raw
	 * README.txt from the latest release on GitHub.
	 *
	 * @since 1.1.0
	 * @return string|null Raw README.txt content, or null on failure.
	 */
	public function fetch_readme() {
		$transient_key = $this->option_name . '_readme';
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			$this->readme_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'text/plain',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'readme_fetch_error', $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->log( 'readme_http_' . $code, 'Readme endpoint returned HTTP ' . $code );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return null;
		}

		// Cache for 24 hours — the README rarely changes between releases.
		set_transient( $transient_key, $body, DAY_IN_SECONDS );

		return $body;
	}

	// ------------------------------------------------------------------//
	//  Private helpers
	// ------------------------------------------------------------------//

	/**
	 * Perform an HTTP POST request to a URL with license data in the body.
	 *
	 * @param string $url URL to fetch.
	 * @return array|\WP_Error
	 */
	private function fetch( $url ) {
		$body = array(
			'license_key'    => $this->license_key,
			'domain'         => home_url(),
			'plugin_version' => $this->plugin_version,
		);

		$args = apply_filters(
			'fsweb_update_checker_http_args',
			array(
				'timeout'    => 10,
				'headers'    => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				'method'     => 'POST',
				'body'       => wp_json_encode( $body ),
			),
			$this->slug
		);

		$response = wp_remote_request( $url, $args );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug logging.
		if ( is_wp_error( $response ) ) {
			error_log( '[FSWEB UC] ' . $url . ' => WP_Error: ' . $response->get_error_message() );
		} else {
			error_log( '[FSWEB UC] ' . $url . ' => HTTP ' . wp_remote_retrieve_response_code( $response ) . ' | ' . wp_remote_retrieve_body( $response ) );
		}

		return $response;
	}

	/**
	 * Log errors when WP_DEBUG is enabled.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return void
	 */
	private function log( $code, $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error(
				esc_html(
					sprintf(
						/* translators: 1: error code, 2: error message */
						__( '[FSWEB Update Checker - %1$s] %2$s', 'fsweb-mercadopago' ),
						$code,
						$message
					)
				),
				E_USER_WARNING
			);
		}
	}
}
