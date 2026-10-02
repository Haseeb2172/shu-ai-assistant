<?php
/**
 * Shared interface for AI Model Providers (Groq, Anthropic, OpenAI).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SHU_AI_Provider_Interface {

	/**
	 * Send a chat completion request to the provider.
	 *
	 * @param array $messages Array of ['role' => 'system'|'user'|'assistant', 'content' => string]
	 * @return string|WP_Error Reply string on success, or WP_Error on failure.
	 */
	public function chat( array $messages );
}
