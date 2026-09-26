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
	 * The response body is the source of truth: a business rejection comes
	 * back with `success` false plus a `message`, even when the HTTP status
	 * is a 4xx. A transport failure (unreachable server, 5xx or a malformed
	 * body) is not a verdict on the key: the local state is left untouched
	 * and the caller gets a neutral notice.
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
			return $this->neutral_response(
				__( 'The license server could not be reached right now. Your key was not changed — please try again in a moment.', 'fsweb-ai-automated-contact' )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// A body outside the documented contract (success, status, expires,
		// message) is transport garbage, not a verdict on the key.
		if ( ! is_array( $body ) || ! array_key_exists( 'success', $body ) ) {
			return $this->neutral_response(
				__( 'The license server did not answer correctly right now. Your key was not changed — please try again in a moment.', 'fsweb-ai-automated-contact' )
			);
		}

		// The body decides: only `success` true activates the license locally.
		if ( ! empty( $body['success'] ) ) {
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

		// Business rejection: the API says why, so the message comes from it.
		$status = $this->normalise_status( isset( $body['status'] ) ? $body['status'] : 'invalid' );
		$this->store( $license_key, $status, isset( $body['expires'] ) ? sanitize_text_field( $body['expires'] ) : '' );

		return array(
			'success' => false,
			'message' => ( isset( $body['message'] ) && '' !== trim( (string) $body['message'] ) )
				? sanitize_text_field( (string) $body['message'] )
				: __( 'Invalid license key.', 'fsweb-ai-automated-contact' ),
			'status'  => $status,
			'expires' => '',
		);
	}

	/**
	 * Deactivate the stored license key.
	 *
	 * Releasing the key on the server is best effort: the local state is
	 * always cleared below, whatever the API answers and whatever the HTTP
	 * status is, so a transport failure never blocks the merchant from
	 * moving the key to another site.
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
	 * The status carried in the response body wins, even when the API answers
	 * a 4xx with `success` false. A transport failure keeps the local state
	 * and returns a neutral notice only.
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
			return $this->neutral_response(
				__( 'The license server could not be reached right now.', 'fsweb-ai-automated-contact' )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Without a status in the body there is nothing to sync; a body outside
		// the documented contract (success, status, expires, message) is
		// transport garbage, not a verdict on the key.
		if ( ! is_array( $body ) || ! array_key_exists( 'success', $body ) || ! isset( $body['status'] ) ) {
			return $this->neutral_response(
				__( 'The license server did not answer correctly right now.', 'fsweb-ai-automated-contact' )
			);
		}

		$status  = $this->normalise_status( $body['status'] );
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
	 * Reply for a transient failure that says nothing about the key.
	 *
	 * Transport problems (unreachable server, 5xx, malformed payload) are not
	 * a verdict on the license: the stored state is kept and the caller shows
	 * a neutral notice instead of an alarming error.
	 *
	 * @since  1.0.2
	 * @param  string $message Neutral notice to show.
	 * @return array{success:bool,message:string,status:string,expires:string}
	 */
	private function neutral_response( $message ) {
		return array(
			'success' => false,
			'message' => $message,
			'status'  => $this->get_status(),
			'expires' => $this->get_expires(),
		);
	}

	/**
	 * Keep only the statuses the plugin understands.
	 *
	 * @since  1.0.2
	 * @param  string $status Raw status from the API body.
	 * @return string active|expired|invalid|pending|inactive
	 */
	private function normalise_status( $status ) {
		$status = sanitize_key( $status );

		return in_array( $status, array( 'active', 'expired', 'invalid', 'pending', 'inactive' ), true ) ? $status : 'invalid';
	}

	/**
	 * Perform a license API request with a retry layer.
	 *
	 * Only transport failures (unreachable server, 5xx, empty body) are
	 * retried, so a momentary blip never flips the license state. A business
	 * answer — including a rejection — is returned right away: the caller
	 * reads the body as the source of truth.
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
		$failure  = null;

		for ( $i = 1; $i <= $attempts; $i++ ) {
			$response = ( 'POST' === $method ) ? wp_remote_post( $url, $args ) : wp_remote_get( $url, $args );
			$failure  = $this->transport_error( $response );

			if ( null === $failure ) {
				return $response;
			}

			if ( $i < $attempts ) {
				usleep( 250000 * $i );
			}
		}

		return $failure;
	}

	/**
	 * Describe a transport failure, or null when the server answered a request.
	 *
	 * An HTTP 5xx is transport, not a business decision, so it is treated as a
	 * failure of the call. From 4xx down the server answered something and the
	 * body carries the verdict.
	 *
	 * @since  1.0.2
	 * @param  array|WP_Error $response Response from wp_remote_*.
	 * @return WP_Error|null
	 */
	private function transport_error( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 500 ) {
			return new WP_Error(
				'fsweb_aic_server_error',
				sprintf(
					/* translators: %d: HTTP status code returned by the license server. */
					__( 'The license server responded with error %d.', 'fsweb-ai-automated-contact' ),
					$code
				)
			);
		}

		if ( '' === trim( (string) wp_remote_retrieve_body( $response ) ) ) {
			return new WP_Error( 'fsweb_aic_empty_response', __( 'The license server returned an empty response.', 'fsweb-ai-automated-contact' ) );
		}

		return null;
	}
}
