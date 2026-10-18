<?php

/**
 * Conversation storage, token accounting and spend limits.
 *
 * The widget sends a conversation key with every turn. This class keeps one
 * row per conversation plus one row per stored message, so the merchant can
 * audit usage and cost without reading the visitor's browser storage. Only
 * messages, token counts and estimated cost are stored — provider API keys
 * never reach this table.
 *
 * @link       https://flowsyncweb.com
 * @since      1.1.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */

/**
 * Conversation repository and usage accounting.
 *
 * @since      1.1.0
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */
class Fsweb_Ai_Automated_Contact_Conversations {

	/**
	 * Schema version, bumped whenever the tables change.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option holding the installed schema version.
	 */
	const DB_OPTION = 'fsweb_aic_db_version';

	/**
	 * Conversation status: still accepting messages.
	 */
	const STATUS_OPEN = 'open';

	/**
	 * Conversation status: the configured spend limit was reached.
	 */
	const STATUS_LIMITED = 'limited';

	/**
	 * Conversations table name.
	 *
	 * @since  1.1.0
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'fsweb_aic_conversations';
	}

	/**
	 * Messages table name.
	 *
	 * @since  1.1.0
	 * @return string
	 */
	public static function messages_table() {
		global $wpdb;

		return $wpdb->prefix . 'fsweb_aic_messages';
	}

	/**
	 * Create or upgrade the tables.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$conversations   = self::table();
		$messages        = self::messages_table();

		$sql = "CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_key varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			visitor_hash varchar(64) NOT NULL DEFAULT '',
			provider varchar(50) NOT NULL DEFAULT '',
			model varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'open',
			message_count int(10) unsigned NOT NULL DEFAULT 0,
			prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			total_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			cost decimal(14,6) NOT NULL DEFAULT 0.000000,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			started_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY conversation_key (conversation_key),
			KEY user_id (user_id),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		dbDelta( $sql );

		$sql = "CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL DEFAULT '',
			content longtext NOT NULL,
			provider varchar(50) NOT NULL DEFAULT '',
			model varchar(100) NOT NULL DEFAULT '',
			prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			total_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			cost decimal(14,6) NOT NULL DEFAULT 0.000000,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Install the schema when the plugin was updated without reactivation.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_OPTION, '' ) ) {
			self::install();
		}
	}

	/**
	 * Sanitize a conversation key coming from the browser.
	 *
	 * Keys are generated client-side (UUID) and are only used to group turns.
	 * They are not authentication: the rate limiter remains the guard rail.
	 *
	 * @since  1.1.0
	 * @param  mixed $key Raw key.
	 * @return string
	 */
	public static function sanitize_key( $key ) {
		$key = is_scalar( $key ) ? strtolower( trim( (string) $key ) ) : '';
		$key = preg_replace( '/[^a-z0-9._-]/', '', $key );

		return substr( (string) $key, 0, 64 );
	}

	/**
	 * Pseudonymous visitor identifier.
	 *
	 * A salted hash keeps the ability to recognise repeat conversations from
	 * the same browser without storing the raw IP address or user agent.
	 *
	 * @since  1.1.0
	 * @return string
	 */
	public static function visitor_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return substr( hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'auth' ) ), 0, 64 );
	}

	/**
	 * Find a conversation by its public key.
	 *
	 * @since  1.1.0
	 * @param  string $key Conversation key.
	 * @return object|null
	 */
	public static function get_by_key( $key ) {
		global $wpdb;

		$key = self::sanitize_key( $key );

		if ( '' === $key ) {
			return null;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$key
			)
		);
	}

	/**
	 * Find a conversation by numeric id.
	 *
	 * @since  1.1.0
	 * @param  int $id Conversation id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id ) {
			return null;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}

	/**
	 * Return the conversation for a key, creating it on the first turn.
	 *
	 * @since  1.1.0
	 * @param  string $key      Conversation key (may be empty).
	 * @param  string $provider Provider id.
	 * @param  string $model    Model name.
	 * @return object|null
	 */
	public static function get_or_create( $key, $provider = '', $model = '' ) {
		global $wpdb;

		$key = self::sanitize_key( $key );

		if ( '' === $key ) {
			$key = wp_generate_uuid4();
		}

		$existing = self::get_by_key( $key );

		if ( $existing ) {
			return $existing;
		}

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'conversation_key' => $key,
				'user_id'          => get_current_user_id(),
				'visitor_hash'     => self::visitor_hash(),
				'provider'         => sanitize_key( $provider ),
				'model'            => sanitize_text_field( $model ),
				'status'           => self::STATUS_OPEN,
				'message_count'    => 0,
				'prompt_tokens'    => 0,
				'completion_tokens' => 0,
				'total_tokens'     => 0,
				'cost'             => 0,
				'currency'         => 'USD',
				'started_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			// A concurrent request may have created the same key: read it.
			return self::get_by_key( $key );
		}

		return self::get( (int) $wpdb->insert_id );
	}

	/**
	 * Store one user/assistant turn and add its usage to the conversation.
	 *
	 * @since  1.1.0
	 * @param  object $conversation Conversation row.
	 * @param  string $user_message User message.
	 * @param  string $assistant    Assistant reply.
	 * @param  array  $usage        Normalized token usage.
	 * @param  float  $cost         Estimated cost in USD.
	 * @return bool
	 */
	public static function record_turn( $conversation, $user_message, $assistant, array $usage, $cost ) {
		global $wpdb;

		if ( ! $conversation || empty( $conversation->id ) ) {
			return false;
		}

		$id        = (int) $conversation->id;
		$now       = current_time( 'mysql', true );
		$provider  = isset( $conversation->provider ) ? (string) $conversation->provider : '';
		$model     = isset( $conversation->model ) ? (string) $conversation->model : '';
		$prompt    = isset( $usage['prompt_tokens'] ) ? max( 0, (int) $usage['prompt_tokens'] ) : 0;
		$completion = isset( $usage['completion_tokens'] ) ? max( 0, (int) $usage['completion_tokens'] ) : 0;
		$total     = isset( $usage['total_tokens'] ) ? max( 0, (int) $usage['total_tokens'] ) : ( $prompt + $completion );
		$cost      = max( 0, (float) $cost );
		$table     = self::messages_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$saved = $wpdb->insert(
			$table,
			array(
				'conversation_id'  => $id,
				'role'             => 'user',
				'content'          => (string) $user_message,
				'provider'         => $provider,
				'model'            => $model,
				'prompt_tokens'    => 0,
				'completion_tokens' => 0,
				'total_tokens'     => 0,
				'cost'             => 0,
				'created_at'       => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s' )
		);

		if ( ! $saved ) {
			return false;
		}

		$saved = $wpdb->insert(
			$table,
			array(
				'conversation_id'  => $id,
				'role'             => 'assistant',
				'content'          => (string) $assistant,
				'provider'         => $provider,
				'model'            => $model,
				'prompt_tokens'    => $prompt,
				'completion_tokens' => $completion,
				'total_tokens'     => $total,
				'cost'             => $cost,
				'created_at'       => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s' )
		);

		if ( ! $saved ) {
			return false;
		}

		$conversations = self::table();
		$status        = (string) $conversation->status;

		if ( Fsweb_Ai_Automated_Contact_Settings::cost_limit() > 0
			&& ( (float) $conversation->cost + $cost ) >= Fsweb_Ai_Automated_Contact_Settings::cost_limit() ) {
			$status = self::STATUS_LIMITED;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$conversations}
				SET message_count = message_count + 2,
					prompt_tokens = prompt_tokens + %d,
					completion_tokens = completion_tokens + %d,
					total_tokens = total_tokens + %d,
					cost = cost + %f,
					status = %s,
					provider = %s,
					model = %s,
					updated_at = %s
				WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$prompt,
				$completion,
				$total,
				$cost,
				$status,
				$provider,
				$model,
				$now,
				$id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return true;
	}

	/**
	 * Estimate the cost of one turn in USD.
	 *
	 * Providers publish prices per one million tokens. When the merchant has
	 * not filled the prices in yet the cost is zero; the token counts are
	 * still stored so the amounts can be recalculated later.
	 *
	 * @since  1.1.0
	 * @param  int    $prompt     Prompt tokens.
	 * @param  int    $completion Completion tokens.
	 * @param  string $model      Model name.
	 * @return float
	 */
	public static function calculate_cost( $prompt, $completion, $model = '' ) {
		$pricing = Fsweb_Ai_Automated_Contact_Settings::pricing( $model );
		$input   = isset( $pricing['input'] ) ? max( 0, (float) $pricing['input'] ) : 0;
		$output  = isset( $pricing['output'] ) ? max( 0, (float) $pricing['output'] ) : 0;

		$cost = ( ( max( 0, (int) $prompt ) / 1000000 ) * $input )
			+ ( ( max( 0, (int) $completion ) / 1000000 ) * $output );

		return round( max( 0, $cost ), 6 );
	}

	/**
	 * Whether the conversation reached the configured spend limit.
	 *
	 * @since  1.1.0
	 * @param  object $conversation Conversation row.
	 * @return bool
	 */
	public static function is_over_limit( $conversation ) {
		$limit = Fsweb_Ai_Automated_Contact_Settings::cost_limit();

		if ( $limit <= 0 || ! $conversation ) {
			return false;
		}

		return (float) $conversation->cost >= $limit;
	}

	/**
	 * Messages of one conversation, oldest first.
	 *
	 * @since  1.1.0
	 * @param  int $conversation_id Conversation id.
	 * @param  int $limit           Maximum rows.
	 * @return array<int,object>
	 */
	public static function get_messages( $conversation_id, $limit = 500 ) {
		global $wpdb;

		$conversation_id = absint( $conversation_id );
		$limit           = max( 1, min( 2000, (int) $limit ) );

		if ( ! $conversation_id ) {
			return array();
		}

		$table = self::messages_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$conversation_id,
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Paginated conversation list.
	 *
	 * @since  1.1.0
	 * @param  array $args Page and per_page.
	 * @return array{rows:array<int,object>,total:int,page:int,per_page:int,pages:int}
	 */
	public static function paginate( array $args = array() ) {
		global $wpdb;

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$offset   = ( $page - 1 ) * $per_page;
		$table    = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		return array(
			'rows'     => is_array( $rows ) ? $rows : array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Global totals for the admin preview.
	 *
	 * @since  1.1.0
	 * @return array{conversations:int,messages:int,tokens:int,cost:float}
	 */
	public static function totals() {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS conversations,
				COALESCE(SUM(message_count), 0) AS messages,
				COALESCE(SUM(total_tokens), 0) AS tokens,
				COALESCE(SUM(cost), 0) AS cost
			FROM {$table}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return array(
			'conversations' => $row ? (int) $row->conversations : 0,
			'messages'      => $row ? (int) $row->messages : 0,
			'tokens'        => $row ? (int) $row->tokens : 0,
			'cost'          => $row ? (float) $row->cost : 0.0,
		);
	}

	/**
	 * Delete a conversation and its messages.
	 *
	 * @since  1.1.0
	 * @param  int $id Conversation id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( self::messages_table(), array( 'conversation_id' => $id ), array( '%d' ) );
		$deleted = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return false !== $deleted;
	}

	/**
	 * Human-readable cost.
	 *
	 * @since  1.1.0
	 * @param  float $amount Amount in USD.
	 * @return string
	 */
	public static function format_cost( $amount ) {
		$amount     = (float) $amount;
		$decimals   = $amount >= 1 ? 4 : 6;
		$formatted  = number_format_i18n( $amount, $decimals );

		return 'US$ ' . $formatted;
	}

	/**
	 * Whether token pricing is configured.
	 *
	 * @since  1.1.0
	 * @return bool
	 */
	public static function has_pricing() {
		$pricing = Fsweb_Ai_Automated_Contact_Settings::pricing();

		return ! empty( $pricing['configured'] );
	}
}
