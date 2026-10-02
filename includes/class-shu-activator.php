<?php
/**
 * Handles plugin activation/deactivation: creates/upgrades custom tables
 * (leads, knowledge base, chat log) using dbDelta(), which is safe and additive.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Activator {

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		update_option( 'shu_ai_db_version', SHU_AI_DB_VERSION );

		// Seed default options if fresh install
		if ( false === get_option( 'shu_ai_options', false ) ) {
			add_option( 'shu_ai_options', array() );
		}

		self::schedule_events();
	}

	/** Repair missing schedules on upgrades as well as first activation. */
	public static function schedule_events() {
		// A single event is rescheduled after every run so local Monday 08:00
		// stays correct when daylight saving time changes.
		if ( ! wp_next_scheduled( 'shu_ai_weekly_digest' ) ) {
			$now  = new DateTimeImmutable( 'now', wp_timezone() );
			$next = 1 === (int) $now->format( 'N' ) && $now->format( 'H:i' ) < '08:00'
				? $now->setTime( 8, 0 ) : new DateTimeImmutable( 'next monday 08:00', wp_timezone() );
			wp_schedule_single_event( $next->getTimestamp(), 'shu_ai_weekly_digest' );
		}

		// Schedule daily log cleanup cron
		if ( ! wp_next_scheduled( 'shu_ai_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'shu_ai_daily_cleanup' );
		}

	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'shu_ai_weekly_digest' );
		wp_clear_scheduled_hook( 'shu_ai_daily_cleanup' );
	}

	/**
	 * Creates (or upgrades) custom DB tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$leads_table = $wpdb->prefix . 'shu_chat_leads';
		$kb_table    = $wpdb->prefix . 'shu_chat_kb';
		$log_table   = $wpdb->prefix . 'shu_chat_log';

		$sql_leads = "CREATE TABLE {$leads_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(64) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			service VARCHAR(191) NOT NULL DEFAULT '',
			location VARCHAR(191) NOT NULL DEFAULT '',
			urgency VARCHAR(191) NOT NULL DEFAULT '',
			extra_fields LONGTEXT NULL,
			priority VARCHAR(10) NOT NULL DEFAULT 'warm',
			review_requested_at DATETIME NULL,
			conversation_transcript LONGTEXT NULL,
			session_id VARCHAR(64) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY status (status),
			KEY priority (priority),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_kb = "CREATE TABLE {$kb_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_url VARCHAR(500) NOT NULL DEFAULT '',
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(500) NOT NULL DEFAULT '',
			content_chunk LONGTEXT NULL,
			vector LONGTEXT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id)
		) {$charset_collate};";

		$sql_log = "CREATE TABLE {$log_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL DEFAULT '',
			role VARCHAR(20) NOT NULL DEFAULT '',
			message LONGTEXT NULL,
			kb_score_max FLOAT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_leads );
		dbDelta( $sql_kb );
		dbDelta( $sql_log );
	}

	/**
	 * Drops custom tables on explicit uninstall.
	 */
	public static function drop_tables() {
		global $wpdb;

		$leads_table = $wpdb->prefix . 'shu_chat_leads';
		$kb_table    = $wpdb->prefix . 'shu_chat_kb';
		$log_table   = $wpdb->prefix . 'shu_chat_log';

		$wpdb->query( "DROP TABLE IF EXISTS {$leads_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$kb_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$log_table}" );
	}
}
