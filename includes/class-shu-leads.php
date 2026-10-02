<?php
/**
 * Handles saving captured leads to wp_shu_chat_leads, service-specific fields,
 * priority scoring, review request email nudges, and admin notifications.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Leads {

	/**
	 * Validate + save a lead. Returns the new lead ID on success, or a
	 * WP_Error on validation/spam failure.
	 *
	 * @param array $fields Expected keys: name, phone, email, service,
	 *                      location, urgency, extra_fields, honeypot, transcript (array).
	 * @return int|WP_Error
	 */
	public function capture( array $fields ) {
		if ( ! empty( $fields['honeypot'] ) ) {
			return new WP_Error( 'shu_spam_honeypot', 'Rejected as spam.' );
		}

		foreach ( array( 'name', 'phone', 'email', 'service', 'location', 'urgency' ) as $field ) {
			if ( isset( $fields[ $field ] ) && ! is_scalar( $fields[ $field ] ) ) {
				return new WP_Error( 'shu_bad_field', 'Invalid lead field.' );
			}
		}
		$name         = isset( $fields['name'] ) ? sanitize_text_field( $fields['name'] ) : '';
		$phone        = isset( $fields['phone'] ) ? sanitize_text_field( $fields['phone'] ) : '';
		$email        = isset( $fields['email'] ) ? sanitize_email( $fields['email'] ) : '';
		$service      = isset( $fields['service'] ) ? sanitize_text_field( $fields['service'] ) : '';
		$location     = isset( $fields['location'] ) ? sanitize_text_field( $fields['location'] ) : '';
		$urgency      = isset( $fields['urgency'] ) ? sanitize_text_field( $fields['urgency'] ) : '';
		$service_map  = SHU_Lead_Fields::get_map();
		foreach ( array_keys( $service_map ) as $canonical_service ) {
			if ( 0 === strcasecmp( $canonical_service, $service ) ) {
				$service = $canonical_service;
				break;
			}
		}
		$extra_fields = isset( $fields['extra_fields'] ) && is_array( $fields['extra_fields'] ) ? $fields['extra_fields'] : array();
		$session_id   = isset( $fields['session_id'] ) && is_string( $fields['session_id'] ) && preg_match( '/^[a-f0-9]{64}$/', $fields['session_id'] ) ? $fields['session_id'] : null;
		$allowed_extra = isset( $service_map[ $service ] ) ? $service_map[ $service ] : array();
		$extra_fields = array_intersect_key( $extra_fields, $allowed_extra );
		$extra_fields = array_map( function ( $value ) {
			return is_scalar( $value ) ? substr( sanitize_text_field( (string) $value ), 0, 300 ) : '';
		}, $extra_fields );

		if ( empty( $name ) ) {
			return new WP_Error( 'shu_missing_name', 'Name is required.' );
		}
		if ( ! isset( $service_map[ $service ] ) || '' === $location || '' === $urgency ) {
			return new WP_Error( 'shu_incomplete_lead', 'Service, location and urgency are required.' );
		}

		if ( empty( $phone ) && empty( $email ) ) {
			return new WP_Error( 'shu_missing_contact', 'A phone number or email is required.' );
		}

		if ( ! empty( $email ) && ! is_email( $email ) ) {
			return new WP_Error( 'shu_bad_email', 'That does not look like a valid email address.' );
		}

		if ( ! empty( $phone ) && ! $this->looks_like_phone( $phone ) ) {
			return new WP_Error( 'shu_bad_phone', 'That does not look like a valid phone number.' );
		}

		// Calculate server-side lead priority (hot/warm/cold)
		$priority = SHU_Lead_Scoring::score( compact( 'service', 'urgency', 'extra_fields' ) );

		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_leads';
		if ( $session_id ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE session_id = %s", $session_id ) );
			if ( $existing ) {
				return (int) $existing;
			}
		}

		$transcript_json   = isset( $fields['transcript'] ) ? wp_json_encode( $fields['transcript'] ) : '';
		$extra_fields_json = ! empty( $extra_fields ) ? wp_json_encode( $extra_fields ) : null;

		$inserted = $wpdb->insert(
			$table,
			array(
				'name'                    => $name,
				'phone'                   => $phone,
				'email'                   => $email,
				'service'                 => $service,
				'location'                => $location,
				'urgency'                 => $urgency,
				'extra_fields'            => $extra_fields_json,
				'priority'                => $priority,
				'conversation_transcript' => $transcript_json,
				'session_id'              => $session_id,
				'status'                  => 'new',
				'created_at'              => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			if ( $session_id ) {
				$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE session_id = %s", $session_id ) );
				if ( $existing ) {
					return (int) $existing;
				}
			}
			return new WP_Error( 'shu_db_error', 'Could not save the lead.' );
		}

		$lead_id = (int) $wpdb->insert_id;

		$this->maybe_notify_admin( $lead_id, compact( 'name', 'phone', 'email', 'service', 'location', 'urgency', 'priority', 'extra_fields' ) );

		return $lead_id;
	}

	private function looks_like_phone( $phone ) {
		$digits_only = preg_replace( '/[^0-9]/', '', $phone );
		$len         = strlen( $digits_only );
		return $len >= 7 && $len <= 15;
	}

	private function maybe_notify_admin( $lead_id, array $lead ) {
		$options = shu_ai_get_options();

		if ( empty( $options['notify_enabled'] ) ) {
			return;
		}

		$recipients = array_filter( array_map( 'trim', explode( ',', $options['notify_emails'] ) ), 'is_email' );

		if ( empty( $recipients ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( '[%s] [%s Priority] New consultation lead from AI Assistant', $site_name, strtoupper( $lead['priority'] ) );

		$body  = "A new lead was captured by the SHU AI Assistant chat widget.\n\n";
		$body .= 'Priority: ' . strtoupper( $lead['priority'] ) . "\n";
		$body .= 'Name: ' . $lead['name'] . "\n";
		$body .= 'Phone: ' . $lead['phone'] . "\n";
		$body .= 'Email: ' . $lead['email'] . "\n";
		$body .= 'Service: ' . $lead['service'] . "\n";
		$body .= 'Location: ' . $lead['location'] . "\n";
		$body .= 'Urgency / start date: ' . $lead['urgency'] . "\n";

		if ( ! empty( $lead['extra_fields'] ) && is_array( $lead['extra_fields'] ) ) {
			$body .= "\nService Details:\n";
			foreach ( $lead['extra_fields'] as $key => $val ) {
				$label = SHU_Lead_Fields::get_field_label( $lead['service'], $key );
				$body .= "  - {$label}: {$val}\n";
			}
		}

		$body .= "\nView full transcript in wp-admin under Settings -> SHU AI Assistant -> Leads (Lead #" . $lead_id . ').' . "\n";

		wp_mail( $recipients, $subject, $body );
	}

	/**
	 * Send Google Review request email to lead if status is closed and email is available.
	 *
	 * @param int $lead_id
	 * @return true|WP_Error
	 */
	public function send_review_request( $lead_id ) {
		$lead = $this->get_lead( $lead_id );
		if ( empty( $lead ) ) {
			return new WP_Error( 'shu_no_lead', 'Lead not found.' );
		}
		if ( 'closed' !== $lead['status'] ) {
			return new WP_Error( 'shu_review_status', 'Close the lead before sending a review request.' );
		}

		if ( empty( $lead['email'] ) || ! is_email( $lead['email'] ) ) {
			return new WP_Error( 'shu_no_lead_email', 'Lead does not have a valid email address.' );
		}

		$options    = shu_ai_get_options();
		$review_url = ! empty( $options['google_review_url'] ) ? $options['google_review_url'] : '';
		if ( ! wp_http_validate_url( $review_url ) || ! preg_match( '~^https://~i', $review_url ) ) {
			return new WP_Error( 'shu_review_url', 'Configure a valid HTTPS review URL under Notifications first.' );
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( 'Thank you for choosing %s - How was your experience?', $site_name );

		$body  = sprintf( "Dear %s,\n\n", $lead['name'] );
		$body .= "Thank you for reaching out to Safety Host Unit. We appreciate the opportunity to serve your security needs.\n\n";
		$body .= "If you have a moment, we would greatly appreciate your feedback. Please leave us a quick review on Google using the link below:\n\n";
		$body .= $review_url . "\n\n";
		$body .= "Your insights help us maintain the highest standard of executive and private security service.\n\n";
		$body .= "Warm regards,\nSafety Host Unit Team";

		$sent = wp_mail( $lead['email'], $subject, $body );

		if ( ! $sent ) {
			return new WP_Error( 'shu_mail_failed', 'Could not send review request email.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_leads';
		$wpdb->update(
			$table,
			array( 'review_requested_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $lead_id ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	public function get_leads( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_leads';

		$defaults = array(
			'search'   => '',
			'status'   => '',
			'priority' => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 50,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR location LIKE %s OR service LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['priority'] ) ) {
			$where[]  = 'priority = %s';
			$params[] = $args['priority'];
		}

		$allowed_orderby = array( 'id', 'created_at', 'name', 'status', 'priority', 'service' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$args['per_page'] = min( 500, max( 1, (int) $args['per_page'] ) );
		$offset = max( 0, ( (int) $args['paged'] - 1 ) * $args['per_page'] );

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = (int) $args['per_page'];
		$params[] = $offset;

		$sql = $wpdb->prepare( $sql, $params );

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function count_leads( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_leads';

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR location LIKE %s OR service LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['priority'] ) ) {
			$where[]  = 'priority = %s';
			$params[] = $args['priority'];
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return (int) $wpdb->get_var( $sql );
	}

	public function update_status( $lead_id, $status ) {
		$allowed = array( 'new', 'contacted', 'closed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_leads';

		return $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => (int) $lead_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public function get_lead( $lead_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'shu_chat_leads';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $lead_id ),
			ARRAY_A
		);
	}
}
