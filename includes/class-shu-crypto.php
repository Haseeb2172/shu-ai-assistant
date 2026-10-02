<?php
/** Protect provider credentials in WordPress options. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Crypto {
	const ENCRYPTED_PREFIX = 'SHU_ENC_V2::';
	const LEGACY_PREFIX    = 'SHU_ENC::';

	private static function key() {
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'AUTH_SALT' ) || ! function_exists( 'openssl_encrypt' ) ) {
			return false;
		}
		return hash( 'sha256', AUTH_KEY . '::' . AUTH_SALT, true );
	}

	/** Authenticated encryption detects changed ciphertext and wrong WP salts. */
	public static function encrypt( $plaintext ) {
		$plaintext = trim( (string) $plaintext );
		if ( '' === $plaintext ) {
			return '';
		}
		$key = self::key();
		if ( false === $key ) {
			return false;
		}
		$nonce = random_bytes( 12 );
		$tag   = '';
		$data  = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );
		return false === $data ? false : self::ENCRYPTED_PREFIX . base64_encode( $nonce . $tag . $data );
	}

	/** Legacy CBC data is read only for migration; bad ciphertext is never used as an API key. */
	public static function decrypt( $ciphertext ) {
		$ciphertext = trim( (string) $ciphertext );
		if ( '' === $ciphertext ) {
			return '';
		}
		$key = self::key();
		if ( 0 === strpos( $ciphertext, self::ENCRYPTED_PREFIX ) ) {
			if ( false === $key ) {
				return '';
			}
			$raw = base64_decode( substr( $ciphertext, strlen( self::ENCRYPTED_PREFIX ) ), true );
			if ( false === $raw || strlen( $raw ) < 29 ) {
				return '';
			}
			$value = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			return false === $value ? '' : $value;
		}
		if ( 0 === strpos( $ciphertext, self::LEGACY_PREFIX ) ) {
			if ( false === $key ) {
				return '';
			}
			$raw = base64_decode( substr( $ciphertext, strlen( self::LEGACY_PREFIX ) ), true );
			// The old format placed a delimiter after a 16-byte binary IV. Splitting on
			// the delimiter itself was unsafe because the IV can contain those bytes.
			if ( false === $raw || strlen( $raw ) < 19 || '::' !== substr( $raw, 16, 2 ) ) {
				return '';
			}
			$value = openssl_decrypt( substr( $raw, 18 ), 'aes-256-cbc', $key, 0, substr( $raw, 0, 16 ) );
			return false === $value ? '' : $value;
		}
		return $ciphertext; // Existing plaintext keys are migrated on init.
	}

	/** Rewrite legacy keys once, without touching unrelated settings. */
	public static function migrate_api_keys() {
		$options = get_option( 'shu_ai_options', array() );
		if ( ! is_array( $options ) || false === self::key() ) {
			return;
		}
		$changed = false;
		foreach ( array( 'groq_api_key', 'anthropic_api_key', 'openai_api_key' ) as $field ) {
			$value = isset( $options[ $field ] ) ? $options[ $field ] : '';
			if ( '' === $value || 0 === strpos( $value, self::ENCRYPTED_PREFIX ) ) {
				continue;
			}
			$plain = self::decrypt( $value );
			if ( '' === $plain ) {
				continue;
			}
			$encrypted = self::encrypt( $plain );
			if ( false !== $encrypted ) {
				$options[ $field ] = $encrypted;
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( 'shu_ai_options', $options );
		}
	}
}
