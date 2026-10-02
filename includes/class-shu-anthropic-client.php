<?php
/**
 * Anthropic Claude API Client implementing SHU_AI_Provider_Interface with multi-key failover.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Anthropic_Client implements SHU_AI_Provider_Interface {

	const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * Send chat request to Anthropic API.
	 *
	 * @param array $messages Standard format ['role' => 'system'|'user'|'assistant', 'content' => string]
	 * @return string|WP_Error
	 */
	public function chat( array $messages ) {
		$options   = shu_ai_get_options();
		$raw_keys  = isset( $options['anthropic_api_key'] ) ? SHU_Crypto::decrypt( $options['anthropic_api_key'] ) : '';
		$keys_list = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_keys ) ) ) );

		if ( empty( $keys_list ) ) {
			return new WP_Error( 'shu_no_api_key', 'Anthropic API key is not configured.' );
		}

		$model = ! empty( $options['anthropic_model'] ) ? $options['anthropic_model'] : 'claude-haiku-4-5-20251001';
		$total_keys = count( $keys_list );

		$pointer     = (int) get_option( 'shu_ai_anthropic_key_pointer', 0 );
		$start_index = $pointer % $total_keys;

		// Extract system prompt from standard messages structure
		$system_prompt = '';
		$api_messages  = array();

		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_prompt = $msg['content'];
			} else {
				$api_messages[] = array(
					'role'    => 'user' === $msg['role'] ? 'user' : 'assistant',
					'content' => $msg['content'],
				);
			}
		}

		for ( $i = 0; $i < $total_keys; $i++ ) {
			$current_index = ( $start_index + $i ) % $total_keys;
			$key           = $keys_list[ $current_index ];

			$cooldown_key = 'shu_ai_cooldown_anthropic_' . md5( $key );
			if ( get_transient( $cooldown_key ) ) {
				error_log( sprintf( 'SHU AI Assistant - Anthropic key #%d is in cooldown.', $current_index + 1 ) );
				continue;
			}

			update_option( 'shu_ai_anthropic_key_pointer', $current_index + 1 );

			$body = array(
				'model'      => $model,
				'max_tokens' => 600,
				'messages'   => $api_messages,
			);

			if ( ! empty( $system_prompt ) ) {
				$body['system'] = $system_prompt;
			}

			$response = wp_remote_post(
				self::API_ENDPOINT,
				array(
					'timeout' => 20,
					'headers' => array(
						'Content-Type'      => 'application/json',
						'x-api-key'         => $key,
						'anthropic-version' => '2023-06-01',
					),
					'body'    => wp_json_encode( $body ),
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( sprintf( 'SHU AI Assistant - Anthropic key #%d failed to connect.', $current_index + 1 ) );
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$raw    = wp_remote_retrieve_body( $response );
			$data   = json_decode( $raw, true );

			if ( 429 === $status || 401 === $status || 403 === $status ) {
				set_transient( $cooldown_key, 1, 60 );
				error_log( sprintf( 'SHU AI Assistant - Anthropic key #%d returned HTTP %d. Entering 60s cooldown.', $current_index + 1, $status ) );
				continue;
			}

			if ( 200 !== $status ) {
				error_log( sprintf( 'SHU AI Assistant - Anthropic key #%d returned HTTP %d.', $current_index + 1, $status ) );
				continue;
			}

			if ( empty( $data['content'][0]['text'] ) ) {
				error_log( sprintf( 'SHU AI Assistant - Anthropic key #%d returned empty text content.', $current_index + 1 ) );
				continue;
			}

			return trim( $data['content'][0]['text'] );
		}

		return new WP_Error( 'shu_anthropic_all_failed', 'All Anthropic API keys failed or are in cooldown.' );
	}
}
