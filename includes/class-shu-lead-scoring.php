<?php
/**
 * Server-side lead priority scoring engine (Hot / Warm / Cold).
 * Evaluates urgency phrasing, service type, and dates against admin-configurable rules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Lead_Scoring {

	/**
	 * Compute lead priority ('hot', 'warm', or 'cold').
	 *
	 * @param array $lead Expected keys: service, urgency, extra_fields
	 * @return string 'hot'|'warm'|'cold'
	 */
	public static function score( array $lead ) {
		$options = shu_ai_get_options();

		$hot_keywords_raw  = isset( $options['scoring_hot_keywords'] ) ? $options['scoring_hot_keywords'] : "today\nasap\nimmediately\nthis week\nurgent\nright now\nnext 24 hours";
		$cold_keywords_raw = isset( $options['scoring_cold_keywords'] ) ? $options['scoring_cold_keywords'] : "just researching\nnot sure yet\na few months\nno timeline\nexploratory\nsometime this year\nnext year\nnot urgent\nno rush";
		$high_val_services_raw = isset( $options['scoring_high_value_services'] ) ? $options['scoring_high_value_services'] : "Executive & Personal Protection";

		$lower = function ( $text ) { return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text ); };
		$hot_keywords        = array_filter( array_map( 'trim', explode( "\n", $lower( $hot_keywords_raw ) ) ) );
		$cold_keywords       = array_filter( array_map( 'trim', explode( "\n", $lower( $cold_keywords_raw ) ) ) );
		$high_value_services = array_filter( array_map( 'trim', explode( "\n", $lower( $high_val_services_raw ) ) ) );

		$urgency_text = isset( $lead['urgency'] ) ? $lower( trim( $lead['urgency'] ) ) : '';
		$service_text = isset( $lead['service'] ) ? $lower( trim( $lead['service'] ) ) : '';

		// Check if service is high-value line
		if ( in_array( $service_text, $high_value_services, true ) ) {
			return 'hot';
		}

		// Check event date / start date in extra fields or urgency for <= 7 days indicator
		if ( self::is_within_7_days( $urgency_text ) ) {
			return 'hot';
		}

		if ( isset( $lead['extra_fields'] ) && is_array( $lead['extra_fields'] ) ) {
			foreach ( $lead['extra_fields'] as $val ) {
				if ( is_string( $val ) && self::is_within_7_days( $lower( $val ) ) ) {
					return 'hot';
				}
			}
		}

		// Check cold keywords in urgency
		foreach ( $cold_keywords as $keyword ) {
			if ( '' !== $keyword && false !== strpos( $urgency_text, $keyword ) ) {
				return 'cold';
			}
		}
		// Cold phrases take precedence over generic words like "urgent" in "not urgent".
		foreach ( $hot_keywords as $keyword ) {
			if ( '' !== $keyword && false !== strpos( $urgency_text, $keyword ) ) {
				return 'hot';
			}
		}

		// Default to warm
		return 'warm';
	}

	/**
	 * Helper to check if text contains indication of within 7 days.
	 *
	 * @param string $text Lowercase text string.
	 * @return bool
	 */
	private static function is_within_7_days( $text ) {
		// Only parse unambiguous calendar dates. Relative phrases are handled below.
		if ( preg_match( '/\b\d{4}-\d{2}-\d{2}\b/', $text, $match ) ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $match[0], wp_timezone() );
			if ( $date && $date->format( 'Y-m-d' ) === $match[0] ) {
				$today = new DateTimeImmutable( 'today', wp_timezone() );
				$days = (int) $today->diff( $date )->format( '%r%a' );
				return $days >= 0 && $days <= 7;
			}
		}
		$quick_phrases = array( 'tomorrow', 'this weekend', 'in 2 days', 'in 3 days', 'in 4 days', 'in 5 days', 'in 6 days', 'in 7 days', '1 week' );
		foreach ( $quick_phrases as $phrase ) {
			if ( false !== strpos( $text, $phrase ) ) {
				return true;
			}
		}
		return false;
	}
}
