<?php
/**
 * Handles chat activity logging, operational analytics computations, and log housekeeping.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Analytics {
	/** Calendar lookback using the timezone in which chat timestamps are stored. */
	public static function since( $days ) {
		$days = min( 3650, max( 1, (int) $days ) );
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Log a message turn into wp_shu_chat_log.
	 *
	 * @param string $session_id
	 * @param string $role 'user' or 'assistant'
	 * @param string $message
	 * @param float  $kb_score_max Highest KB score for this turn (0 if none)
	 */
	public static function log_message( $session_id, $role, $message, $kb_score_max = 0.0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_log';

		$wpdb->insert(
			$table,
			array(
				'session_id'   => sanitize_text_field( $session_id ),
				'role'         => sanitize_text_field( $role ),
				'message'      => sanitize_textarea_field( $message ),
				'kb_score_max' => (float) $kb_score_max,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%f', '%s' )
		);
	}

	/** Paginate retained sessions; one row per browser conversation. */
	public static function get_conversations( $page = 1, $per_page = 20 ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'shu_chat_log';
		$offset = ( max( 1, (int) $page ) - 1 ) * $per_page;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, MIN(created_at) AS started_at, MAX(created_at) AS last_at, COUNT(*) AS turns FROM {$table} GROUP BY session_id ORDER BY last_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	public static function count_conversations() {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_log';
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$table}" );
	}

	/** Read the server-side record; never trust the browser's supplied history as a transcript. */
	public static function get_transcript( $session_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_log';
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT role, message, created_at FROM {$table} WHERE session_id = %s ORDER BY id DESC LIMIT 1000", $session_id ),
			ARRAY_A
		);
		return array_reverse( (array) $rows );
	}

	/**
	 * Get total distinct chat sessions in the last N days.
	 *
	 * @param int $days
	 * @return int
	 */
	public static function get_session_count( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_log';

		$sql = $wpdb->prepare(
			"SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE created_at >= %s",
			self::since( $days )
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get total lead conversions in the last N days.
	 *
	 * @param int $days
	 * @return int
	 */
	public static function get_lead_count( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_leads';

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
			self::since( $days )
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get conversation volume by day for the last N days.
	 *
	 * @param int $days
	 * @return array Array of ['date' => string, 'sessions' => int]
	 */
	public static function get_daily_volume( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_log';

		$sql = $wpdb->prepare(
			"SELECT DATE(created_at) as day, COUNT(DISTINCT session_id) as count FROM {$table} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC",
			self::since( $days )
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get top N most-asked questions clustered by extracted keywords.
	 *
	 * @param int $days
	 * @param int $limit
	 * @return array Array of ['cluster' => string, 'count' => int, 'sample' => string]
	 */
	public static function get_top_questions( $days = 30, $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_log';

		$sql = $wpdb->prepare(
			"SELECT message FROM {$table} WHERE role = 'user' AND created_at >= %s ORDER BY id DESC LIMIT 500",
			self::since( $days )
		);

		$rows = $wpdb->get_col( $sql );
		if ( empty( $rows ) ) {
			return array();
		}

		$clusters = array();
		$samples  = array();

		foreach ( $rows as $msg ) {
			$keywords = SHU_Knowledge_Base::extract_keywords( $msg );

			if ( empty( $keywords ) ) {
				continue;
			}

			sort( $keywords );
			$key = implode( ' ', array_slice( $keywords, 0, 3 ) );

			if ( ! isset( $clusters[ $key ] ) ) {
				$clusters[ $key ] = 0;
				$samples[ $key ]  = $msg;
			}
			$clusters[ $key ]++;
		}

		arsort( $clusters );
		$results = array();

		foreach ( array_slice( $clusters, 0, $limit, true ) as $cluster_key => $count ) {
			$results[] = array(
				'cluster' => ucwords( $cluster_key ),
				'count'   => $count,
				'sample'  => $samples[ $cluster_key ],
			);
		}

		return $results;
	}

	/**
	 * Get questions where KB search scored near 0 (low-relevance coverage gaps).
	 *
	 * @param int $days
	 * @param int $limit
	 * @return array Array of ['message' => string, 'count' => int, 'created_at' => string]
	 */
	public static function get_coverage_gaps( $days = 30, $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_log';

		$sql = $wpdb->prepare(
			"SELECT message, created_at FROM {$table} WHERE role = 'user' AND kb_score_max <= 0.1 AND created_at >= %s ORDER BY id DESC LIMIT 500",
			self::since( $days )
		);
		$groups = array();
		foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $row ) {
			$key = $row['message'];
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array( 'message' => $key, 'count' => 0, 'last_asked' => $row['created_at'] );
			}
			$groups[ $key ]['count']++;
		}
		usort( $groups, function ( $a, $b ) { return $b['count'] <=> $a['count']; } );
		return array_slice( $groups, 0, $limit );
	}

	/** Delete expired messages unless the administrator chose indefinite retention. */
	public static function purge_old_logs() {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_log';
		$options = shu_ai_get_options();
		$days = min( 3650, max( 0, (int) $options['conversation_retention_days'] ) );
		if ( $days ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", self::since( $days ) ) );
		}
	}
}
