<?php
/**
 * Security, Token Cryptography, Rate Limiting, Sanitization, and Redacted Activity Logging
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Security {

	/**
	 * Generate a cryptographically secure random token (raw token sent to user once, never stored in DB)
	 *
	 * @param int $bytes Number of random bytes (default 24 -> 48 hex chars).
	 * @return array { 'raw' => string, 'hash' => string }
	 */
	public static function generate_token_pair( $bytes = 24 ) {
		try {
			$raw = bin2hex( random_bytes( $bytes ) );
		} catch ( \Exception $e ) {
			$raw = bin2hex( wp_generate_password( $bytes * 2, true, true ) );
		}

		return array(
			'raw'  => $raw,
			'hash' => self::hash_token( $raw ),
		);
	}

	/**
	 * Compute SHA-256 hash of a raw token
	 *
	 * @param string $raw_token Raw token string.
	 * @return string 64-character hex SHA-256 hash.
	 */
	public static function hash_token( $raw_token ) {
		return hash( 'sha256', trim( (string) $raw_token ) );
	}

	/**
	 * Generate human-readable unique Reference Code (e.g., TRB-2026-8F4A2C)
	 *
	 * @param string $prefix Prefix like TRB or TKT.
	 * @return string
	 */
	public static function generate_reference_code( $prefix = 'TRB' ) {
		try {
			$suffix = strtoupper( bin2hex( random_bytes( 3 ) ) );
		} catch ( \Exception $e ) {
			$suffix = strtoupper( substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 6 ) );
		}
		return sprintf( '%s-%s-%s', $prefix, gmdate( 'Y' ), $suffix );
	}

	/**
	 * Derive a standalone, human-friendly Personal Invitation Code (e.g., VIP-8F4A-2C91)
	 * from a guest's unique raw invitation token or hash.
	 *
	 * @param string $token_or_hash Raw token or token hash.
	 * @return string Standalone Personal Invitation Code (e.g. VIP-XXXX-XXXX).
	 */
	public static function format_vip_personal_code( $token_or_hash ) {
		$clean = strtoupper( preg_replace( '/[^A-Fa-f0-9]/', '', (string) $token_or_hash ) );
		if ( strlen( $clean ) < 8 ) {
			$clean = strtoupper( substr( md5( (string) $token_or_hash ), 0, 8 ) );
		}
		return sprintf( 'VIP-%s-%s', substr( $clean, 0, 4 ), substr( $clean, 4, 4 ) );
	}

	/**
	 * Extract normalized VIP code or raw token from user input (handles VIP-XXXX-XXXX, raw 48-char token, or full pasted URL).
	 *
	 * @param string $input Raw user input from /launch/vip or API.
	 * @return array { 'raw_input' => string, 'clean_hex' => string, 'is_short_code' => bool, 'formatted_code' => string }
	 */
	public static function parse_vip_code_input( $input ) {
		$str = trim( (string) $input );

		// If the user pasted a full URL (e.g. https://tarbwy.org/launch/vip/?token=...), extract query param
		if ( false !== strpos( $str, 'http' ) || false !== strpos( $str, '?' ) || false !== strpos( $str, 'token=' ) || false !== strpos( $str, 'code=' ) ) {
			$parsed = wp_parse_url( $str );
			if ( ! empty( $parsed['query'] ) ) {
				parse_str( $parsed['query'], $q_vars );
				if ( ! empty( $q_vars['token'] ) ) {
					$str = trim( (string) $q_vars['token'] );
				} elseif ( ! empty( $q_vars['code'] ) ) {
					$str = trim( (string) $q_vars['code'] );
				}
			}
		}

		// Strip optional "VIP-" prefix and hyphens/spaces
		$without_prefix = preg_replace( '/^VIP[\-\s_]*/i', '', $str );
		$clean_hex      = strtolower( preg_replace( '/[^a-fA-F0-9]/', '', (string) $without_prefix ) );

		$is_short = ( strlen( $clean_hex ) >= 6 && strlen( $clean_hex ) <= 16 );
		return array(
			'raw_input'      => $str,
			'clean_hex'      => $clean_hex,
			'is_short_code'  => $is_short,
			'formatted_code' => strlen( $clean_hex ) >= 8 ? self::format_vip_personal_code( $clean_hex ) : strtoupper( $str ),
		);
	}

	/**
	 * IP-based and Endpoint-based Rate Limiter using WordPress Transients
	 *
	 * @param string $action_key Action identifier (e.g. 'public_reg', 'vip_lookup').
	 * @param int    $max_attempts Maximum allowed attempts within window.
	 * @param int    $window_seconds Window in seconds.
	 * @return bool True if allowed, False if rate limit exceeded.
	 */
	public static function check_rate_limit( $action_key, $max_attempts = 10, $window_seconds = 300 ) {
		$ip       = self::get_client_ip_hash();
		$key      = 'tarbwy_rl_' . md5( $action_key . '_' . $ip );
		$attempts = (int) get_transient( $key );

		if ( $attempts >= $max_attempts ) {
			return false;
		}

		set_transient( $key, $attempts + 1, $window_seconds );
		return true;
	}

	/**
	 * Get privacy-preserving hash of client IP
	 */
	public static function get_client_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}

	/**
	 * Validate Honeypot field (must be empty)
	 *
	 * @param array $data Request payload.
	 * @return bool True if clean, False if bot detected.
	 */
	public static function verify_honeypot( $data ) {
		if ( ! empty( $data['website_url_hp'] ) || ! empty( $data['tarbwy_fax_hp'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Sanitize CSV cell against CSV Formula Injection (=, +, -, @, Tab, CR)
	 *
	 * @param string $value Raw cell value.
	 * @return string Safe cell value for Excel/CSV.
	 */
	public static function sanitize_csv_cell( $value ) {
		$str = (string) $value;
		$str = str_replace( array( "\r\n", "\r", "\n" ), ' ', $str );
		if ( '' === $str ) {
			return '';
		}
		$first_char = substr( ltrim( $str ), 0, 1 );
		if ( in_array( $first_char, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $str;
		}
		return $str;
	}

	/**
	 * Record redacted audit log entry (Never logs raw tokens, passwords, or sensitive PII)
	 *
	 * @param int    $event_id    Event ID.
	 * @param string $action      Action name.
	 * @param string $object_type Object type (registration, guest, invitation, ticket, checkin, export, settings).
	 * @param int    $object_id   Object ID.
	 * @param array  $metadata    Metadata array (automatically redacted).
	 */
	public static function log_activity( $event_id, $action, $object_type, $object_id = 0, $metadata = array() ) {
		global $wpdb;

		$forbidden_keys = array( 'token', 'raw_token', 'password', 'secret', 'smtp_pass', 'email', 'phone', 'dietary_requirements', 'accessibility_requirements' );
		$clean_meta     = array();

		if ( is_array( $metadata ) ) {
			foreach ( $metadata as $k => $v ) {
				if ( in_array( strtolower( (string) $k ), $forbidden_keys, true ) ) {
					$clean_meta[ $k ] = '[REDACTED]';
				} else {
					$clean_meta[ $k ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : $v;
				}
			}
		}

		$actor_id = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'tarbwy_activity_log',
			array(
				'event_id'      => absint( $event_id ),
				'actor_user_id' => $actor_id ? absint( $actor_id ) : null,
				'action'        => sanitize_key( $action ),
				'object_type'   => sanitize_key( $object_type ),
				'object_id'     => $object_id ? absint( $object_id ) : null,
				'metadata_json' => wp_json_encode( $clean_meta, JSON_UNESCAPED_UNICODE ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Send strict security headers for sensitive pages (/launch/vip, /launch/ticket, /launch/checkin)
	 */
	public static function send_no_leak_headers() {
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'Permissions-Policy: camera=(self), microphone=()' );
			header( "Feature-Policy: camera 'self'; microphone 'none'" );
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
		}
	}
}
