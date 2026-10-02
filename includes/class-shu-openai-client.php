<?php
/**
 * OpenAI Chat Completions API Client implementing SHU_AI_Provider_Interface with multi-key failover.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_OpenAI_Client implements SHU_AI_Provider_Interface {

	const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Send chat request to OpenAI API.
	 *
	 * @param array $messages Standard format ['role' => 'system'|'user'|'assistant', 'content' => string]
	 * @return string|WP_Error
	 */
	public function chat( array $messages ) {
		$options   = shu_ai_get_options();
		$raw_keys  = isset( $options['openai_api_key'] ) ? SHU_Crypto::decrypt( $options['openai_api_key'] ) : '';
		$keys_list = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_keys ) ) ) );

		if ( empty( $keys_list ) ) {
			return new WP_Error( 'shu_no_api_key', 'OpenAI API key is not configured.' );
		}

		$model      = ! empty( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-4o-mini';
		$total_keys = count( $keys_list );

		$pointer     = (int) get_option( 'shu_ai_openai_key_pointer', 0 );
		$start_index = $pointer % $total_keys;

		for ( $i = 0; $i < $total_keys; $i++ ) {
			$current_index = ( $start_index + $i ) % $total_keys;
			$key           = $keys_list[ $current_index ];

			$cooldown_key = 'shu_ai_cooldown_openai_' . md5( $key );
			if ( get_transient( $cooldown_key ) ) {
				error_log( sprintf( 'SHU AI Assistant - OpenAI key #%d is in cooldown.', $current_index + 1 ) );
				continue;
			}

			update_option( 'shu_ai_openai_key_pointer', $current_index + 1 );

			$response = wp_remote_post(
				self::API_ENDPOINT,
				array(
					'timeout' => 20,
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $key,
					),
					'body'    => wp_json_encode(
						array(
							'model'       => $model,
							'messages'    => $messages,
							'temperature' => 0.3,
							'max_tokens'  => 600,
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( sprintf( 'SHU AI Assistant - OpenAI key #%d failed to connect.', $current_index + 1 ) );
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$raw    = wp_remote_retrieve_body( $response );
			$data   = json_decode( $raw, true );

			if ( 429 === $status || 401 === $status || 403 === $status ) {
				set_transient( $cooldown_key, 1, 60 );
				error_log( sprintf( 'SHU AI Assistant - OpenAI key #%d returned HTTP %d. Entering 60s cooldown.', $current_index + 1, $status ) );
				continue;
			}

			if ( 200 !== $status ) {
				error_log( sprintf( 'SHU AI Assistant - OpenAI key #%d returned HTTP %d.', $current_index + 1, $status ) );
				continue;
			}

			if ( empty( $data['choices'][0]['message']['content'] ) ) {
				error_log( sprintf( 'SHU AI Assistant - OpenAI key #%d returned empty content.', $current_index + 1 ) );
				continue;
			}

			return trim( $data['choices'][0]['message']['content'] );
		}

		return new WP_Error( 'shu_openai_all_failed', 'All OpenAI API keys failed or are in cooldown.' );
	}
}
