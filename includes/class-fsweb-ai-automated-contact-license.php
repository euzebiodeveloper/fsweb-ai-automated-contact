<?php

/**
 * License management: local status, remote activation and validation.
 *
 * The UI lives in the admin class; every operation against the FSWEB license
 * API is centralised here so the admin screens, the REST layer and the
 * onboarding flow all behave identically.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */

/**
 * License client.
 *
 * @since      1.0.0
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */
class Fsweb_Ai_Automated_Contact_License {

	/**
	 * wp_options key holding the license key.
	 */
	const OPTION_KEY = 'fsweb_aic_license_key';

	/**
	 * wp_options key holding the locally cached status.
	 */
	const OPTION_STATUS = 'fsweb_aic_license_status';

	/**
	 * wp_options key holding the expiration date.
	 */
	const OPTION_EXPIRES = 'fsweb_aic_license_expires';

	/**
	 * Stored license key.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_key() {
		return (string) get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Stored license status.
	 *
	 * @since  1.0.0
	 * @return string active|expired|invalid|pending|inactive
	 */
	public function get_status() {
		return (string) get_option( self::OPTION_STATUS, 'inactive' );
	}

	/**
	 * Stored expiration date (Y-m-d H:i:s or empty for lifetime).
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_expires() {
		return (string) get_option( self::OPTION_EXPIRES, '' );
	}

	/**
	 * Persist the local license state.
	 *
	 * @since  1.0.0
	 * @param  string $key     License key.
	 * @param  string $status  License status.
	 * @param  string $expires Expiration date.
	 * @return void
	 */
	private function store( $key, $status, $expires = '' ) {
		update_option( self::OPTION_KEY, $key );
		update_option( self::OPTION_STATUS, sanitize_key( $status ) );
		update_option( self::OPTION_EXPIRES, sanitize_text_field( $expires ) );
	}

	/**
	 * Activate a license key against the remote server.
	 *
	 * A network failure or a malformed response never flips the key to
	 * "invalid": the key is kept as "pending" so a flaky server cannot
	 * silently disable the plugin.
	 *
	 * @since  1.0.0
	 * @param  string $license_key Raw license key from the request.
	 * @return array{success:bool,message:string,status:string,expires:string}
	 */
	public function activate( $license_key ) {
		$license_key = sanitize_text_field( $license_key );

		if ( '' === $license_key ) {
			return array(
				'success' => false,
				'message' => __( 'Enter a license key.', 'fsweb-ai-automated-contact' ),
				'status'  => $this->get_status(),
				'expires' => $this->get_expires(),
			);
		}

		if ( 'active' === $this->get_status() && $this->get_key() === $license_key ) {
			return array(
				'success' => true,
				'message' => __( 'This license is already active.', 'fsweb-ai-automated-contact' ),
				'status'  => 'active',
				'expires' => $this->get_expires(),
			);
		}

		$response = $this->request(
			'POST',
			FSWEB_LICENSE_API_URL . '/activate',
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => wp_json_encode(
					array(
						'license_key' => $license_key,
						'domain'      => home_url(),
						'plugin_slug' => FSWEB_AI_AUTOMATED_CONTACT_SLUG,
					)
				),
				'headers'   => array( 'Content-Type' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->store( $license_key, 'pending' );

			return array(
				'success' => false,
				'message' => __( 'Could not reach the license server. Your key was saved and is pending validation.', 'fsweb-ai-automated-contact' ),
				'status'  => 'pending',
				'expires' => '',
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 === $code && is_array( $body ) && ! empty( $body['success'] ) ) {
			$expires = isset( $body['expires'] ) ? sanitize_text_field( $body['expires'] ) : '';
			$this->store( $license_key, 'active', $expires );

			if ( function_exists( 'fsweb_ai_automated_contact_reset_update_checker_cache' ) ) {
				fsweb_ai_automated_contact_reset_update_checker_cache();
			}

			return array(
				'success' => true,
				'message' => __( 'License activated successfully!', 'fsweb-ai-automated-contact' ),
				'status'  => 'active',
				'expires' => $expires,
			);
		}

		if ( ! is_array( $body ) ) {
			$this->store( $license_key, 'pending' );

			return array(
				'success' => false,
				'message' => __( 'The license server returned an invalid response. Your key was saved and is pending validation.', 'fsweb-ai-automated-contact' ),
				'status'  => 'pending',
				'expires' => '',
			);
		}

		$this->store( $license_key, 'invalid' );

		return array(
			'success' => false,
			'message' => isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : __( 'Invalid license key.', 'fsweb-ai-automated-contact' ),
			'status'  => 'invalid',
			'expires' => '',
		);
	}

	/**
	 * Deactivate the stored license key.
	 *
	 * The local state is always cleared, even when the remote call fails.
	 *
	 * @since  1.0.0
	 * @return array{success:bool,message:string,status:string}
	 */
	public function deactivate() {
		$license_key = $this->get_key();

		if ( '' !== $license_key ) {
			$this->request(
				'POST',
				FSWEB_LICENSE_API_URL . '/deactivate',
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => wp_json_encode(
						array(
							'license_key' => $license_key,
							'domain'      => home_url(),
							'plugin_slug' => FSWEB_AI_AUTOMATED_CONTACT_SLUG,
						)
					),
					'headers'   => array( 'Content-Type' => 'application/json' ),
				)
			);
		}

		$this->store( $license_key, 'pending' );

		return array(
			'success' => true,
			'message' => __( 'License deactivated.', 'fsweb-ai-automated-contact' ),
			'status'  => 'pending',
		);
	}

	/**
	 * Validate the stored (or provided) key and sync the local state.
	 *
	 * @since  1.0.0
	 * @param  string $license_key Optional key; falls back to the stored one.
	 * @return array{success:bool,message:string,status:string,expires:string}
	 */
	public function validate( $license_key = '' ) {
		$license_key = '' !== $license_key ? sanitize_text_field( $license_key ) : $this->get_key();

		if ( '' === $license_key ) {
			return array(
				'success' => false,
				'message' => __( 'No license saved. Enter a key first.', 'fsweb-ai-automated-contact' ),
				'status'  => $this->get_status(),
				'expires' => '',
			);
		}

		$url = add_query_arg(
			array(
				'license_key' => rawurlencode( $license_key ),
				'plugin_slug' => FSWEB_AI_AUTOMATED_CONTACT_SLUG,
				'domain'      => rawurlencode( home_url() ),
			),
			FSWEB_LICENSE_API_URL . '/validate'
		);

		$response = $this->request( 'GET', $url, array( 'timeout' => 15, 'sslverify' => false ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not reach the license server.', 'fsweb-ai-automated-contact' ),
				'status'  => $this->get_status(),
				'expires' => $this->get_expires(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! isset( $body['status'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid response from the license server.', 'fsweb-ai-automated-contact' ),
				'status'  => $this->get_status(),
				'expires' => $this->get_expires(),
			);
		}

		$status  = sanitize_key( $body['status'] );
		$expires = isset( $body['expires'] ) ? sanitize_text_field( $body['expires'] ) : '';

		update_option( self::OPTION_STATUS, $status );
		update_option( self::OPTION_EXPIRES, $expires );

		return array(
			'success' => true,
			'message' => $this->status_message( $status, $expires ),
			'status'  => $status,
			'expires' => $expires,
		);
	}

	/**
	 * Sync the local status with the server, used on page load.
	 *
	 * @since  1.0.0
	 * @return array{status:string,expires:string}|null Null when the server is unreachable.
	 */
	public function sync() {
		$license_key = $this->get_key();

		if ( '' === $license_key ) {
			return null;
		}

		$result = $this->validate( $license_key );

		if ( empty( $result['success'] ) ) {
			return null;
		}

		return array(
			'status'  => $result['status'],
			'expires' => $result['expires'],
		);
	}

	/**
	 * Human-readable message for a status.
	 *
	 * @since  1.0.0
	 * @param  string $status  License status.
	 * @param  string $expires Expiration date.
	 * @return string
	 */
	public function status_message( $status, $expires = '' ) {
		switch ( $status ) {
			case 'active':
				return __( 'License is active and valid.', 'fsweb-ai-automated-contact' );
			case 'expired':
				return __( 'License expired. Renew it to keep receiving updates.', 'fsweb-ai-automated-contact' );
			case 'invalid':
				return __( 'Invalid license key.', 'fsweb-ai-automated-contact' );
			case 'pending':
				return __( 'License pending activation.', 'fsweb-ai-automated-contact' );
			default:
				return __( 'License inactive.', 'fsweb-ai-automated-contact' );
		}
	}

	/**
	 * Perform a license API request with a retry layer.
	 *
	 * The license server can crash and answer with an empty body. Transient
	 * failures are retried so a momentary blip never flips the license state.
	 *
	 * @since  1.0.0
	 * @param  string $method   'GET' or 'POST'.
	 * @param  string $url      Request URL.
	 * @param  array  $args     wp_remote_* arguments.
	 * @param  int    $attempts Attempt count.
	 * @return array|WP_Error
	 */
	private function request( $method, $url, $args = array(), $attempts = 3 ) {
		$attempts = max( 1, absint( $attempts ) );
		$method   = strtoupper( $method );
		$last     = null;

		for ( $i = 1; $i <= $attempts; $i++ ) {
			$response = ( 'POST' === $method ) ? wp_remote_post( $url, $args ) : wp_remote_get( $url, $args );

			if ( ! is_wp_error( $response ) && '' !== trim( (string) wp_remote_retrieve_body( $response ) ) ) {
				return $response;
			}

			$last = is_wp_error( $response )
				? $response
				: new WP_Error( 'fsweb_aic_empty_response', __( 'The license server returned an empty response.', 'fsweb-ai-automated-contact' ) );

			if ( $i < $attempts ) {
				sleep( $i );
			}
		}

		return $last;
	}
}
