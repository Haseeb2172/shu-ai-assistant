<?php
/**
 * Weekly email digest cron handler firing every Monday morning.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Weekly_Digest {

	public static function send_digest() {
		SHU_Activator::schedule_events();
		$options = shu_ai_get_options();

		if ( empty( $options['notify_weekly_digest'] ) ) {
			return;
		}

		$recipients = array_filter( array_map( 'trim', explode( ',', $options['notify_emails'] ) ), 'is_email' );
		if ( empty( $recipients ) ) {
			return;
		}

		$total_sessions = SHU_Analytics::get_session_count( 7 );
		$total_leads    = SHU_Analytics::get_lead_count( 7 );
		$conversion_rate = $total_sessions > 0 ? round( ( $total_leads / $total_sessions ) * 100, 1 ) : 0;

		global $wpdb;
		$leads_table = $wpdb->prefix . 'shu_chat_leads';
		$since      = SHU_Analytics::since( 7 );
		$counts     = array();
		foreach ( array( 'hot', 'warm', 'cold' ) as $priority ) {
			$counts[ $priority ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads_table} WHERE priority = %s AND created_at >= %s", $priority, $since ) );
		}
		$hot_leads  = $counts['hot'];
		$warm_leads = $counts['warm'];
		$cold_leads = $counts['cold'];

		$top_questions = SHU_Analytics::get_top_questions( 7, 5 );
		$gaps          = SHU_Analytics::get_coverage_gaps( 7, 5 );

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( '[%s] Weekly AI Assistant Performance Digest', $site_name );

		$body  = "Here is your weekly summary for the SHU AI Assistant chat widget:\n\n";
		$body .= "--- ACTIVITY OVERVIEW (LAST 7 DAYS) ---\n";
		$body .= "Total Conversations: {$total_sessions}\n";
		$body .= "New Consultation Leads: {$total_leads} (Hot: {$hot_leads}, Warm: {$warm_leads}, Cold: {$cold_leads})\n";
		$body .= "Lead Conversion Rate: {$conversion_rate}%\n\n";

		$body .= "--- TOP 5 MOST-ASKED QUESTION THEMES ---\n";
		if ( ! empty( $top_questions ) ) {
			foreach ( $top_questions as $idx => $q ) {
				$num = $idx + 1;
				$body .= "{$num}. {$q['cluster']} ({$q['count']} asks)\n   Example: \"{$q['sample']}\"\n";
			}
		} else {
			$body .= "No question activity recorded last week.\n";
		}
		$body .= "\n";

		$body .= "--- TOP 5 KNOWLEDGE BASE COVERAGE GAPS (LOW RELEVANCE RESULTS) ---\n";
		if ( ! empty( $gaps ) ) {
			foreach ( $gaps as $idx => $g ) {
				$num = $idx + 1;
				$body .= "{$num}. \"{$g['message']}\" ({$g['count']} occurrences)\n";
			}
		} else {
			$body .= "No unanswered question gaps detected last week.\n";
		}
		$body .= "\n";

		$body .= "View full operational metrics & leads in wp-admin under Settings -> SHU AI Assistant.\n";

		wp_mail( $recipients, $subject, $body );
	}
}
