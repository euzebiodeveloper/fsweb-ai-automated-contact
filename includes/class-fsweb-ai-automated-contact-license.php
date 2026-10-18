<?php

/**
 * License client for the PRO add-on.
 *
 * The free edition hosts the license screen so a merchant can activate a key
 * before (or without) the add-on being installed. The state is stored under
 * the same option names the add-on reads, so both plugins always agree on
 * whether the PRO features are unlocked.
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
	const OPTION_KEY = 'fsweb_aic_pro_license_key';

	/**
	 * wp_options key holding the locally cached status.
	 */
	const OPTION_STATUS = 'fsweb_aic_pro_license_status';

	/**
	 * wp_options key holding the expiration date.
	 */
	const OPTION_EXPIRES = 'fsweb_aic_pro_license_expires';

	/**
	 * Endpoint of the license API.
	 */
	const API_URL = 'https://api.flowsyncweb.com/api/license';

	/**
	 * Slug the license belongs to (the PRO add-on).
	 */
	const PLUGIN_SLUG = 'fsweb-ai-automated-contact-pro';

	/**
	 * Slug sent to the license API.
	 *
	 * The free edition and the PRO add-on share a single license, so both
	 * send the base slug: a key generated for `fsweb-ai-automated-contact`
	 * also unlocks the add-on, and the other way round.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function plugin_slug() {
		return self::normalize_slug( self::PLUGIN_SLUG );
	}

	/**
	 * Removes the `-pro` suffix from a plugin slug.
	 *
	 * @since  1.0.0
	 * @param  string $slug Plugin slug.
	 * @return string
	 */
	public static function normalize_slug( $slug ) {
		return (string) preg_replace( '/-pro$/i', '', trim( (string) $slug ) );
	}

	/**
	 * Stored license key.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_key() {
		if ( defined( 'FSWEB_AIC_PRO_LICENSE_KEY' ) && '' !== (string) FSWEB_AIC_PRO_LICENSE_KEY ) {
			return (string) FSWEB_AIC_PRO_LICENSE_KEY;
		}

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
	 * Stored expiration date.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_expires() {
		return (string) get_option( self::OPTION_EXPIRES, '' );
	}

	/**
	 * Whether the stored license is active.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_active() {
		return 'active' === $this->get_status();
	}

	/**
	 * Format the expiration instant for display in UTC.
	 *
	 * @since  1.0.0
	 * @param  string $expires Raw expiration (ISO 8601 or Y-m-d H:i:s).
	 * @return string
	 */
	public function format_expires( $expires = '' ) {
		$expires = '' !== $expires ? $expires : $this->get_expires();
		$expires = trim( (string) $expires );

		if ( '' === $expires ) {
			return '';
		}

		$timestamp = strtotime( $expires );

		if ( false === $timestamp ) {
			return '';
		}

		return (string) wp_date( (string) get_option( 'date_format', 'Y-m-d' ), $timestamp, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Activate a license key against the remote server.
	 *
	 * @since  1.0.0
	 * @param  string $license_key Raw key from the request.
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
			self::API_URL . '/activate',
			array(
				'body'    => wp_json_encode(
					array(
						'license_key' => $license_key,
						'domain'      => home_url(),
						'plugin_slug' => self::plugin_slug(),
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->neutral_response(
				__( 'The license server could not be reached right now. Your key was not changed — please try again in a moment.', 'fsweb-ai-automated-contact' )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! array_key_exists( 'success', $body ) ) {
			return $this->neutral_response(
				__( 'The license server did not answer correctly right now. Your key was not changed — please try again in a moment.', 'fsweb-ai-automated-contact' )
			);
		}

		if ( ! empty( $body['success'] ) ) {
			$expires = isset( $body['expires'] ) ? sanitize_text_field( $body['expires'] ) : '';
			$this->store( $license_key, 'active', $expires );

			// The add-on caches its own update verdict; drop it so the first
			// check after activation already sees the license.
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
	 * always cleared, so a transport failure never blocks the merchant.
	 *
	 * @since  1.0.0
	 * @return array{success:bool,message:string,status:string}
	 */
	public function deactivate() {
		$license_key = $this->get_key();

		if ( '' !== $license_key ) {
			$this->request(
				'POST',
				self::API_URL . '/deactivate',
				array(
					'body'    => wp_json_encode(
						array(
							'license_key' => $license_key,
							'domain'      => home_url(),
							'plugin_slug' => self::plugin_slug(),
						)
					),
					'headers' => array( 'Content-Type' => 'application/json' ),
				)
			);
		}

		// Release the key locally too: the screen goes back to the initial
		// state, with the field empty and only the activate button available.
		$this->store( '', 'inactive' );

		return array(
			'success' => true,
			'message' => __( 'License deactivated.', 'fsweb-ai-automated-contact' ),
			'status'  => 'inactive',
			'expires' => '',
		);
	}

	/**
	 * Validate the stored key and sync the local state.
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
				'plugin_slug' => self::plugin_slug(),
				'domain'      => rawurlencode( home_url() ),
			),
			self::API_URL . '/validate'
		);

		$response = $this->request( 'GET', $url );

		if ( is_wp_error( $response ) ) {
			return $this->neutral_response(
				__( 'The license server could not be reached right now.', 'fsweb-ai-automated-contact' )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! array_key_exists( 'success', $body ) || ! isset( $body['status'] ) ) {
			return $this->neutral_response(
				__( 'The license server did not answer correctly right now.', 'fsweb-ai-automated-contact' )
			);
		}

		$status  = $this->normalise_status( $body['status'] );
		$expires = isset( $body['expires'] ) ? sanitize_text_field( $body['expires'] ) : '';

		// A valid license only unlocks this site while the API reports the
		// domain as authorized.
		if ( in_array( $status, array( 'active', 'pending' ), true ) && array_key_exists( 'domain_authorized', $body ) ) {
			$status = ! empty( $body['domain_authorized'] ) ? 'active' : 'pending';
		}

		update_option( self::OPTION_STATUS, $status );
		update_option( self::OPTION_EXPIRES, $expires );

		return array(
			'success' => true,
			'message' => $this->status_message( $status ),
			'status'  => $status,
			'expires' => $expires,
		);
	}

	/**
	 * Human-readable message for a status.
	 *
	 * @since  1.0.0
	 * @param  string $status License status.
	 * @return string
	 */
	public function status_message( $status ) {
		switch ( $status ) {
			case 'active':
				return __( 'License active', 'fsweb-ai-automated-contact' );
			case 'expired':
				return __( 'License expired', 'fsweb-ai-automated-contact' );
			case 'invalid':
				return __( 'Invalid key', 'fsweb-ai-automated-contact' );
			case 'pending':
				return __( 'License pending', 'fsweb-ai-automated-contact' );
			default:
				return __( 'License inactive', 'fsweb-ai-automated-contact' );
		}
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
	 * Reply for a transient failure that says nothing about the key.
	 *
	 * @since  1.0.0
	 * @param  string $message Neutral notice.
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
	 * @since  1.0.0
	 * @param  string $status Raw status from the API body.
	 * @return string
	 */
	private function normalise_status( $status ) {
		$status = sanitize_key( $status );

		return in_array( $status, array( 'active', 'expired', 'invalid', 'pending', 'inactive' ), true ) ? $status : 'invalid';
	}

	/**
	 * Perform a license API request.
	 *
	 * @since  1.0.0
	 * @param  string $method 'GET' or 'POST'.
	 * @param  string $url    Request URL.
	 * @param  array  $args   wp_remote_* arguments.
	 * @return array|WP_Error
	 */
	private function request( $method, $url, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( 'POST' === $method ) {
			return wp_remote_post( $url, $args );
		}

		return wp_remote_get( $url, $args );
	}
}
