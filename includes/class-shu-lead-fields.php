<?php
/**
 * Defines the per-service qualifying field mapping and human-readable field labels
 * for service-specific lead capture.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Lead_Fields {

	/**
	 * Return the map of qualifying fields keyed by service line name.
	 *
	 * @return array
	 */
	public static function get_map() {
		return array(
			'Event Security' => array(
				'event_date'     => 'Event Date',
				'guest_count'    => 'Expected Guest Count',
				'setting'        => 'Indoor / Outdoor Setting',
				'alcohol_served' => 'Alcohol Served',
			),
			'Residential Security' => array(
				'property_type'    => 'Property Type',
				'gated_community'  => 'Gated Community',
				'current_security' => 'Current Security Measures',
			),
			'Executive & Personal Protection' => array(
				'protection_duration' => 'Protection Duration',
				'number_of_principals' => 'Number of Principals',
				'perceived_threat'    => 'Known / Perceived Threat Level',
			),
			'Mobile Patrol' => array(
				'property_type'    => 'Property Type',
				'location_count'   => 'Number of Locations',
				'patrol_frequency' => 'Patrol Frequency Desired',
			),
			'Fire Watch' => array(
				'fire_watch_reason' => 'Reason for Fire Watch',
				'site_type'         => 'Site Type',
				'expected_duration' => 'Expected Duration',
			),
			'Warehouse & Commercial Security' => array(
				'site_size'        => 'Site Size / Approx Sq Ft',
				'current_coverage' => 'Current Coverage',
				'hours_needed'     => 'Hours Needed',
			),
			'Not sure' => array(),
		);
	}

	/**
	 * Return a prompt snippet describing the extra service-specific questions
	 * for the AI model's lead capture instruction system prompt.
	 *
	 * @return string
	 */
	public static function get_prompt_instructions() {
		$map = self::get_map();
		$lines = array();

		foreach ( $map as $service => $fields ) {
			if ( empty( $fields ) ) {
				continue;
			}
			$field_names = implode( ', ', array_values( $fields ) );
			$lines[] = "- {$service}: {$field_names}";
		}

		$instructions  = "Service-Specific Qualifying Fields Map:\n";
		$instructions .= implode( "\n", $lines );
		$instructions .= "\n\nWhen a service line is identified, collect the relevant qualifying fields for that service naturally alongside the six core fields. Event Security has four extra fields. Ask at most two questions per message. Include these answers under an \"extra_fields\" JSON object inside the final [[SHU_LEAD_CAPTURE]] payload.";

		return $instructions;
	}

	/**
	 * Get human-readable label for a given field key and service name.
	 *
	 * @param string $service
	 * @param string $key
	 * @return string
	 */
	public static function get_field_label( $service, $key ) {
		$map = self::get_map();
		if ( isset( $map[ $service ][ $key ] ) ) {
			return $map[ $service ][ $key ];
		}
		// Fallback formatting: converts snake_case to Title Case.
		return ucwords( str_replace( '_', ' ', $key ) );
	}
}
