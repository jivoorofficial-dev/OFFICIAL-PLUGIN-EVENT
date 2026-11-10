<?php
/**
 * Local Pure-PHP & Local-JS QR Code Generator & Ticket Lifecycle Manager
 *
 * Strictly complies with rules:
 * - Never use an external QR service or send guest data externally.
 * - Never embed PII or guest ID in QR payload (only random opaque token).
 * - Prevents duplicate QR generation on repeated clicks or status transitions.
 * - Supports immediate cascading revocation when a registration is rejected, waitlisted, or deleted.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QRGenerator {

	/**
	 * Normalize and extract the canonical QR payload for a ticket record, pass array, or string.
	 * Ensures 100% identical QR payload across DB storage, Email QR, Ticket Page (/launch/ticket),
	 * VIP Portal (/launch/vip), and Scanner (/launch/checkin).
	 *
	 * @param mixed $source Ticket DB row object, pass associative array, or raw string.
	 * @return string Canonical ASCII QR payload (e.g. TKT-2026-XXXXXX).
	 */
	public static function get_canonical_qr_payload( $source ) {
		$candidate = '';
		if ( is_object( $source ) ) {
			if ( ! empty( $source->qr_payload ) && '-' !== trim( (string) $source->qr_payload ) ) {
				$candidate = (string) $source->qr_payload;
			} elseif ( ! empty( $source->ticket_reference ) && '-' !== trim( (string) $source->ticket_reference ) ) {
				$candidate = (string) $source->ticket_reference;
			} elseif ( ! empty( $source->token_preview ) ) {
				$candidate = (string) $source->token_preview;
			}
		} elseif ( is_array( $source ) ) {
			if ( ! empty( $source['qr_payload'] ) && '-' !== trim( (string) $source['qr_payload'] ) ) {
				$candidate = (string) $source['qr_payload'];
			} elseif ( ! empty( $source['ticket_reference'] ) && '-' !== trim( (string) $source['ticket_reference'] ) ) {
				$candidate = (string) $source['ticket_reference'];
			} elseif ( ! empty( $source['qr_token'] ) && '-' !== trim( (string) $source['qr_token'] ) ) {
				$candidate = (string) $source['qr_token'];
			} elseif ( ! empty( $source['raw_token'] ) ) {
				$candidate = (string) $source['raw_token'];
			}
		} elseif ( is_scalar( $source ) ) {
			$candidate = (string) $source;
		}

		$candidate = trim( $candidate );
		if ( '' === $candidate || '-' === $candidate ) {
			return 'TARBWY-INVALID';
		}

		// Standardize TKT-... references to uppercase ASCII
		if ( 0 === stripos( $candidate, 'TKT-' ) ) {
			return strtoupper( $candidate );
		}

		return $candidate;
	}

	/**
	 * Issue or retrieve an active ticket with a unique opaque random token and canonical qr_payload for a confirmed guest.
	 *
	 * Idempotent by default ($force_reissue = false): if the guest already has an active/used ticket,
	 * it returns the existing ticket without creating a duplicate.
	 * When $force_reissue = true, it revokes any previous active ticket and issues a new one.
	 *
	 * @param int  $event_id      Event ID.
	 * @param int  $guest_id      Guest ID.
	 * @param bool $force_reissue Whether to revoke existing active ticket and force a new token.
	 * @return array|\WP_Error {
	 *     'ticket_id'        => int,
	 *     'ticket_reference' => string,
	 *     'qr_payload'       => string,
	 *     'qr_token'         => string,
	 *     'raw_token'        => string,
	 *     'ticket_url'       => string,
	 *     'reused'           => bool
	 * }
	 */
	public static function issue_ticket_for_guest( $event_id, $guest_id, $force_reissue = false ) {
		global $wpdb;

		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';

		// Verify guest belongs to event and is confirmed
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, event_id, full_name, guest_type, rsvp_status, checkin_status FROM {$guests_table} WHERE id = %d AND event_id = %d",
				absint( $guest_id ),
				absint( $event_id )
			)
		);

		if ( ! $guest ) {
			return new \WP_Error( 'guest_not_found', 'الضيف غير موجود في هذا الحدث.' );
		}

		// Auto-confirm guest if ticket issuance is requested and guest is not explicitly declined
		if ( 'confirmed' !== $guest->rsvp_status ) {
			if ( 'declined' === $guest->rsvp_status ) {
				return new \WP_Error( 'guest_not_confirmed', 'لا يمكن إصدار تذكرة لضيف معتذر عن الحضور.' );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$guests_table,
				array(
					'rsvp_status' => 'confirmed',
					'updated_at'  => current_time( 'mysql' ),
				),
				array( 'id' => absint( $guest_id ) ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			$guest->rsvp_status = 'confirmed';
		}

		// Check if an active or used ticket already exists when $force_reissue is false (prevents duplicate QR codes)
		if ( ! $force_reissue ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, ticket_reference, token_preview, status FROM {$tickets_table} WHERE event_id = %d AND guest_id = %d AND status IN ('active', 'used') ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END ASC, id DESC LIMIT 1",
					absint( $event_id ),
					absint( $guest_id )
				)
			);

			if ( $existing ) {
				$canonical_payload = self::get_canonical_qr_payload( $existing );
				$raw_token         = ! empty( $existing->token_preview )
					? (string) $existing->token_preview
					: (string) get_transient( 'tarbwy_tkt_raw_' . $existing->id );

				if ( empty( $raw_token ) ) {
					$raw_token = $canonical_payload;
				}

				// Ensure qr_payload is persisted consistently in DB if column exists
				try {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( $wpdb->prepare( "UPDATE `{$tickets_table}` SET qr_payload = %s WHERE id = %d AND (qr_payload IS NULL OR qr_payload = '' OR qr_payload != %s)", $canonical_payload, (int) $existing->id, $canonical_payload ) );
				} catch ( \Throwable $e ) {
					// Ignore if column not yet migrated
				}

				set_transient( 'tarbwy_tkt_raw_' . $existing->id, $raw_token, DAY_IN_SECONDS * 30 );
				return array(
					'ticket_id'        => (int) $existing->id,
					'ticket_reference' => $canonical_payload,
					'qr_payload'       => $canonical_payload,
					'qr_token'         => $canonical_payload,
					'raw_token'        => $raw_token,
					'ticket_url'       => self::get_ticket_url( $raw_token ),
					'reused'           => true,
				);
			}
		}

		$now = current_time( 'mysql' );

		// Revoke any existing active tickets for this guest before issuing a new one
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tickets_table} SET status = 'revoked', revoked_at = %s, updated_at = %s WHERE event_id = %d AND guest_id = %d AND status = 'active'",
				$now,
				$now,
				absint( $event_id ),
				absint( $guest_id )
			)
		);

		$token_pair       = Security::generate_token_pair( 20 ); // 40-char cryptographic hex token
		$ticket_reference = strtoupper( trim( Security::generate_reference_code( 'TKT' ) ) );
		$qr_payload       = $ticket_reference;

		$insert_data = array(
			'event_id'         => absint( $event_id ),
			'guest_id'         => absint( $guest_id ),
			'ticket_reference' => $ticket_reference,
			'qr_payload'       => $qr_payload,
			'token_hash'       => $token_pair['hash'],
			'token_preview'    => $token_pair['raw'],
			'status'           => 'active',
			'issued_at'        => $now,
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$ticket_id = Installer::safe_insert_ticket( $insert_data );

		if ( $ticket_id <= 0 ) {
			return new \WP_Error( 'ticket_insert_failed', 'تعذر إصدار التذكرة في قاعدة البيانات.' );
		}

		// Store raw token transiently as well for backwards compatibility
		set_transient( 'tarbwy_tkt_raw_' . $ticket_id, $token_pair['raw'], DAY_IN_SECONDS * 30 );

		Security::log_activity(
			$event_id,
			$force_reissue ? 'ticket_reissued' : 'ticket_issued',
			'ticket',
			$ticket_id,
			array(
				'guest_id'         => $guest_id,
				'ticket_reference' => $ticket_reference,
				'qr_payload'       => $qr_payload,
			)
		);

		return array(
			'ticket_id'        => $ticket_id,
			'ticket_reference' => $ticket_reference,
			'qr_payload'       => $qr_payload,
			'qr_token'         => $qr_payload,
			'raw_token'        => $token_pair['raw'],
			'ticket_url'       => self::get_ticket_url( $token_pair['raw'] ),
			'reused'           => false,
		);
	}

	/**
	 * Immediately revoke all active QR tickets for a single guest
	 *
	 * @param int    $event_id Event ID.
	 * @param int    $guest_id Guest ID.
	 * @param string $reason   Reason for revocation.
	 * @return int Number of tickets revoked.
	 */
	public static function revoke_tickets_for_guest( $event_id, $guest_id, $reason = 'status_changed' ) {
		global $wpdb;
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$now           = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$revoked = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tickets_table} SET status = 'revoked', revoked_at = %s, updated_at = %s WHERE event_id = %d AND guest_id = %d AND status = 'active'",
				$now,
				$now,
				absint( $event_id ),
				absint( $guest_id )
			)
		);

		if ( $revoked > 0 ) {
			Security::log_activity(
				$event_id,
				'ticket_revoked',
				'guest',
				$guest_id,
				array(
					'revoked_count' => $revoked,
					'reason'        => sanitize_key( $reason ),
				)
			);
		}

		return $revoked;
	}

	/**
	 * Immediately revoke all active QR tickets for a registration (Main attendee + all companions)
	 * Used when an Approved registration is changed to Rejected, Waitlisted, Pending, or Deleted.
	 *
	 * @param int    $event_id        Event ID.
	 * @param int    $registration_id Registration ID.
	 * @param string $reason          Reason for revocation.
	 * @return int Total tickets revoked.
	 */
	public static function revoke_tickets_for_registration( $event_id, $registration_id, $reason = 'status_changed' ) {
		global $wpdb;
		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$reg_table     = $wpdb->prefix . 'tarbwy_registrations';
		$now           = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$primary_gid = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT primary_guest_id FROM {$reg_table} WHERE id = %d", absint( $registration_id ) )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$guests_table} WHERE event_id = %d AND (registration_id = %d OR (%d > 0 AND (id = %d OR parent_guest_id = %d)))",
				absint( $event_id ),
				absint( $registration_id ),
				$primary_gid,
				$primary_gid,
				$primary_gid
			)
		);

		if ( empty( $guest_ids ) ) {
			return 0;
		}

		$total_revoked = 0;
		foreach ( $guest_ids as $gid ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$cnt = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$tickets_table} SET status = 'revoked', revoked_at = %s, updated_at = %s WHERE event_id = %d AND guest_id = %d AND status = 'active'",
					$now,
					$now,
					absint( $event_id ),
					absint( $gid )
				)
			);
			$total_revoked += $cnt;
		}

		if ( $total_revoked > 0 ) {
			Security::log_activity(
				$event_id,
				'registration_tickets_revoked',
				'registration',
				$registration_id,
				array(
					'revoked_count' => $total_revoked,
					'reason'        => sanitize_key( $reason ),
				)
			);
		}

		return $total_revoked;
	}

	/**
	 * Fetch all active/used tickets for a registration (Main attendee + all approved companions)
	 *
	 * @param int $event_id        Event ID.
	 * @param int $registration_id Registration ID.
	 * @return array List of ticket pass arrays.
	 */
	public static function get_active_tickets_for_registration( $event_id, $registration_id ) {
		global $wpdb;
		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$reg_table     = $wpdb->prefix . 'tarbwy_registrations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$primary_gid = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT primary_guest_id FROM {$reg_table} WHERE id = %d", absint( $registration_id ) )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.id AS guest_id, g.full_name, g.guest_type, g.email, t.id AS ticket_id, t.ticket_reference, t.token_preview, t.status
				 FROM {$guests_table} g
				 INNER JOIN {$tickets_table} t ON t.guest_id = g.id AND t.status IN ('active', 'used')
				 WHERE g.event_id = %d AND (g.registration_id = %d OR (%d > 0 AND (g.id = %d OR g.parent_guest_id = %d)))
				 ORDER BY CASE WHEN g.guest_type = 'companion' THEN 1 ELSE 0 END ASC, g.id ASC",
				absint( $event_id ),
				absint( $registration_id ),
				$primary_gid,
				$primary_gid,
				$primary_gid
			)
		);

		$passes     = array();
		$seen_guest = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$gid = (int) $r->guest_id;
				if ( isset( $seen_guest[ $gid ] ) ) {
					continue;
				}
				$seen_guest[ $gid ] = true;

				$canonical_payload = self::get_canonical_qr_payload( $r );
				$raw_token         = ! empty( $r->token_preview )
					? (string) $r->token_preview
					: (string) get_transient( 'tarbwy_tkt_raw_' . $r->ticket_id );
				$tkt_url           = ! empty( $raw_token ) ? self::get_ticket_url( $raw_token ) : home_url( '/launch/ticket/?t=' . rawurlencode( $canonical_payload ) );

				$passes[] = array(
					'guest_id'         => $gid,
					'guest_name'       => (string) $r->full_name,
					'guest_type'       => (string) $r->guest_type,
					'ticket_id'        => (int) $r->ticket_id,
					'ticket_reference' => $canonical_payload,
					'qr_payload'       => $canonical_payload,
					'qr_token'         => $canonical_payload,
					'raw_token'        => ! empty( $raw_token ) ? $raw_token : $canonical_payload,
					'ticket_url'       => $tkt_url,
				);
			}
		}

		return $passes;
	}

	/**
	 * Fetch all active/used tickets for a VIP guest and their confirmed companions
	 *
	 * @param int $event_id     Event ID.
	 * @param int $vip_guest_id Primary VIP Guest ID.
	 * @return array List of ticket pass arrays.
	 */
	public static function get_active_tickets_for_vip_guest( $event_id, $vip_guest_id ) {
		global $wpdb;
		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.id AS guest_id, g.full_name, g.guest_type, g.email, t.id AS ticket_id, t.ticket_reference, t.token_preview, t.status
				 FROM {$guests_table} g
				 INNER JOIN {$tickets_table} t ON t.guest_id = g.id AND t.status IN ('active', 'used')
				 WHERE g.event_id = %d AND (g.id = %d OR g.parent_guest_id = %d)
				 ORDER BY CASE WHEN g.guest_type = 'companion' THEN 1 ELSE 0 END ASC, g.id ASC",
				absint( $event_id ),
				absint( $vip_guest_id ),
				absint( $vip_guest_id )
			)
		);

		$passes     = array();
		$seen_guest = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$gid = (int) $r->guest_id;
				if ( isset( $seen_guest[ $gid ] ) ) {
					continue;
				}
				$seen_guest[ $gid ] = true;

				$canonical_payload = self::get_canonical_qr_payload( $r );
				$raw_token         = ! empty( $r->token_preview )
					? (string) $r->token_preview
					: (string) get_transient( 'tarbwy_tkt_raw_' . $r->ticket_id );
				$tkt_url           = ! empty( $raw_token ) ? self::get_ticket_url( $raw_token ) : home_url( '/launch/ticket/?t=' . rawurlencode( $canonical_payload ) );

				$passes[] = array(
					'guest_id'         => $gid,
					'guest_name'       => (string) $r->full_name,
					'guest_type'       => (string) $r->guest_type,
					'ticket_id'        => (int) $r->ticket_id,
					'ticket_reference' => $canonical_payload,
					'qr_payload'       => $canonical_payload,
					'qr_token'         => $canonical_payload,
					'raw_token'        => ! empty( $raw_token ) ? $raw_token : $canonical_payload,
					'ticket_url'       => $tkt_url,
				);
			}
		}

		return $passes;
	}

	/**
	 * Build secure ticket URL from raw token or ticket reference
	 *
	 * @param string $raw_token Raw opaque token or ticket reference.
	 * @return string
	 */
	public static function get_ticket_url( $raw_token ) {
		return home_url( '/launch/ticket/?t=' . rawurlencode( (string) $raw_token ) );
	}

	/**
	 * Pure-PHP ISO/IEC 18004 QR Code Matrix Builder (Versions 1 to 6, ECC Level L, Mask 0).
	 * Matches assets/js/tarbwy-qr.js bit-for-bit so Email QR and Ticket Page QR are 100% identical.
	 *
	 * @param string $text ASCII/UTF-8 payload to encode (e.g., TKT-2026-XXXXXX).
	 * @return array 2D array of booleans (true = dark module, false = light module).
	 */
	public static function build_qr_matrix( $text ) {
		$text  = trim( (string) $text );
		$bytes = array_values( unpack( 'C*', $text ) );
		$len   = count( $bytes );

		// Version capabilities for ECC Level L (Byte mode): [version => [totalCodewords, ecCodewordsPerBlock, numBlocks, maxBytes, alignCenters]]
		$versions = array(
			1 => array( 26, 7, 1, 17, array() ),
			2 => array( 44, 10, 1, 32, array( 6, 18 ) ),
			3 => array( 70, 15, 1, 53, array( 6, 22 ) ),
			4 => array( 100, 20, 1, 78, array( 6, 26 ) ),
			5 => array( 134, 26, 1, 106, array( 6, 30 ) ),
			6 => array( 172, 18, 2, 134, array( 6, 34 ) ),
		);

		$ver = 6;
		foreach ( $versions as $v => $spec ) {
			if ( $len <= $spec[3] ) {
				$ver = $v;
				break;
			}
		}
		if ( $len > 134 ) {
			$bytes = array_slice( $bytes, 0, 134 );
			$len   = 134;
		}

		$vspec      = $versions[ $ver ];
		$total_cw   = $vspec[0];
		$ec_per_blk = $vspec[1];
		$num_blocks = $vspec[2];
		$data_cw    = $total_cw - ( $ec_per_blk * $num_blocks );

		// Build bit stream: Mode 0100 (Byte mode) + 8-bit length + byte bits + terminator + padding
		$bits = array( 0, 1, 0, 0 );
		for ( $i = 7; $i >= 0; $i-- ) {
			$bits[] = ( $len >> $i ) & 1;
		}
		foreach ( $bytes as $b ) {
			for ( $i = 7; $i >= 0; $i-- ) {
				$bits[] = ( $b >> $i ) & 1;
			}
		}

		$max_bits = $data_cw * 8;
		for ( $i = 0; $i < 4 && count( $bits ) < $max_bits; $i++ ) {
			$bits[] = 0;
		}
		while ( count( $bits ) % 8 !== 0 ) {
			$bits[] = 0;
		}

		$codewords = array();
		$num_bits  = count( $bits );
		for ( $i = 0; $i < $num_bits; $i += 8 ) {
			$val = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$val = ( $val << 1 ) | $bits[ $i + $j ];
			}
			$codewords[] = $val;
		}

		$pad_bytes = array( 0xEC, 0x11 );
		$pad_idx   = 0;
		while ( count( $codewords ) < $data_cw ) {
			$codewords[] = $pad_bytes[ $pad_idx % 2 ];
			$pad_idx++;
		}

		// GF(256) Reed-Solomon tables
		$gf_exp = array_fill( 0, 512, 0 );
		$gf_log = array_fill( 0, 256, 0 );
		$x      = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			$gf_exp[ $i ] = $x;
			$gf_log[ $x ] = $i;
			$x <<= 1;
			if ( $x & 0x100 ) {
				$x ^= 0x11D;
			}
		}
		for ( $i = 255; $i < 512; $i++ ) {
			$gf_exp[ $i ] = $gf_exp[ $i - 255 ];
		}

		$gf_mul = function ( $a, $b ) use ( $gf_exp, $gf_log ) {
			if ( 0 === $a || 0 === $b ) {
				return 0;
			}
			return $gf_exp[ $gf_log[ $a ] + $gf_log[ $b ] ];
		};

		// Generator polynomial of degree $ec_per_blk
		$gen = array( 1 );
		for ( $i = 0; $i < $ec_per_blk; $i++ ) {
			$next = array_fill( 0, count( $gen ) + 1, 0 );
			$root = $gf_exp[ $i ];
			for ( $j = 0, $glen = count( $gen ); $j < $glen; $j++ ) {
				$next[ $j ]     ^= $gen[ $j ];
				$next[ $j + 1 ] ^= $gf_mul( $gen[ $j ], $root );
			}
			$gen = $next;
		}

		$rs_encode = function ( array $data ) use ( $ec_per_blk, $gen, $gf_mul ) {
			$rem = array_fill( 0, $ec_per_blk, 0 );
			foreach ( $data as $byte ) {
				$factor = $byte ^ array_shift( $rem );
				$rem[]  = 0;
				if ( 0 !== $factor ) {
					for ( $j = 0; $j < $ec_per_blk; $j++ ) {
						$rem[ $j ] ^= $gf_mul( $gen[ $j + 1 ], $factor );
					}
				}
			}
			return $rem;
		};

		$data_blocks = array();
		$ec_blocks   = array();
		$block_size  = intdiv( $data_cw, $num_blocks );
		$offset      = 0;
		for ( $b = 0; $b < $num_blocks; $b++ ) {
			$blk           = array_slice( $codewords, $offset, $block_size );
			$offset       += $block_size;
			$data_blocks[] = $blk;
			$ec_blocks[]   = $rs_encode( $blk );
		}

		$final_bytes = array();
		for ( $i = 0; $i < $block_size; $i++ ) {
			for ( $b = 0; $b < $num_blocks; $b++ ) {
				$final_bytes[] = $data_blocks[ $b ][ $i ];
			}
		}
		for ( $i = 0; $i < $ec_per_blk; $i++ ) {
			for ( $b = 0; $b < $num_blocks; $b++ ) {
				$final_bytes[] = $ec_blocks[ $b ][ $i ];
			}
		}

		$size     = $ver * 4 + 17;
		$matrix   = array_fill( 0, $size, array_fill( 0, $size, false ) );
		$reserved = array_fill( 0, $size, array_fill( 0, $size, false ) );

		$place_finder = function ( $row, $col ) use ( &$matrix, &$reserved, $size ) {
			for ( $r = -1; $r <= 7; $r++ ) {
				for ( $c = -1; $c <= 7; $c++ ) {
					$rr = $row + $r;
					$cc = $col + $c;
					if ( $rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size ) {
						continue;
					}
					$in_outer = ( $r >= 0 && $r <= 6 && ( 0 === $r || 6 === $r || 0 === $c || 6 === $c ) && $c >= 0 && $c <= 6 );
					$in_inner = ( $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4 );
					$matrix[ $rr ][ $cc ]   = ( $in_outer || $in_inner );
					$reserved[ $rr ][ $cc ] = true;
				}
			}
		};

		$place_finder( 0, 0 );
		$place_finder( 0, $size - 7 );
		$place_finder( $size - 7, 0 );

		// Alignment patterns
		$centers = $vspec[4];
		foreach ( $centers as $ar ) {
			foreach ( $centers as $ac ) {
				if ( ( $ar <= 8 && $ac <= 8 ) || ( $ar <= 8 && $ac >= $size - 9 ) || ( $ar >= $size - 9 && $ac <= 8 ) ) {
					continue;
				}
				for ( $r = -2; $r <= 2; $r++ ) {
					for ( $c = -2; $c <= 2; $c++ ) {
						$matrix[ $ar + $r ][ $ac + $c ]   = ( 2 === abs( $r ) || 2 === abs( $c ) || ( 0 === $r && 0 === $c ) );
						$reserved[ $ar + $r ][ $ac + $c ] = true;
					}
				}
			}
		}

		// Timing patterns
		for ( $i = 8; $i < $size - 8; $i++ ) {
			$val                  = ( 0 === $i % 2 );
			$matrix[6][ $i ]      = $val;
			$reserved[6][ $i ]    = true;
			$matrix[ $i ][6]      = $val;
			$reserved[ $i ][6]    = true;
		}

		// Dark module & format info areas
		$matrix[ $size - 8 ][8]   = true;
		$reserved[ $size - 8 ][8] = true;
		for ( $i = 0; $i < 9; $i++ ) {
			$reserved[8][ $i ] = true;
			$reserved[ $i ][8] = true;
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$reserved[8][ $size - 1 - $i ] = true;
			$reserved[ $size - 1 - $i ][8] = true;
		}

		// Place data bits with Mask 0 ((r + c) % 2 === 0)
		$bit_idx    = 0;
		$total_bits = count( $final_bytes ) * 8;
		$up         = true;
		for ( $col = $size - 1; $col > 0; $col -= 2 ) {
			if ( 6 === $col ) {
				$col--;
			}
			for ( $i = 0; $i < $size; $i++ ) {
				$r = $up ? ( $size - 1 - $i ) : $i;
				for ( $dc = 0; $dc < 2; $dc++ ) {
					$c = $col - $dc;
					if ( ! $reserved[ $r ][ $c ] ) {
						$bit = false;
						if ( $bit_idx < $total_bits ) {
							$byte_val = $final_bytes[ intdiv( $bit_idx, 8 ) ];
							$bit      = ( ( ( $byte_val >> ( 7 - ( $bit_idx % 8 ) ) ) & 1 ) === 1 );
							$bit_idx++;
						}
						if ( ( $r + $c ) % 2 === 0 ) {
							$bit = ! $bit;
						}
						$matrix[ $r ][ $c ] = $bit;
					}
				}
			}
			$up = ! $up;
		}

		// Format Information for ECC Level L (01) + Mask Pattern 0 (000) -> 0x77C4 (30660)
		// Per ISO/IEC 18004 Figure 25 (matches assets/js/tarbwy-qr.js bit-for-bit):
		// Bit 14 (MSB) at (8,0) and (N-1,8); Bit 0 (LSB) at (0,8) and (8,N-1)
		$fmt_bits    = 0x77C4;
		$get_fmt_bit = function ( $bit_pos ) use ( $fmt_bits ) {
			return ( ( ( $fmt_bits >> $bit_pos ) & 1 ) === 1 );
		};

		// Copy 1: Top-left format bits (bit 0 at (0,8) down to bit 14 at (8,0))
		for ( $fb = 0; $fb <= 5; $fb++ ) {
			$matrix[ $fb ][8] = $get_fmt_bit( $fb );
		}
		$matrix[7][8] = $get_fmt_bit( 6 );
		$matrix[8][8] = $get_fmt_bit( 7 );
		$matrix[8][7] = $get_fmt_bit( 8 );
		for ( $fb2 = 9; $fb2 < 15; $fb2++ ) {
			$matrix[8][ 14 - $fb2 ] = $get_fmt_bit( $fb2 );
		}

		// Copy 2: Top-right (bits 0..7) and Bottom-left (bits 8..14) format bits
		for ( $fb3 = 0; $fb3 < 8; $fb3++ ) {
			$matrix[8][ $size - 1 - $fb3 ] = $get_fmt_bit( $fb3 );
		}
		for ( $fb4 = 8; $fb4 < 15; $fb4++ ) {
			$matrix[ $size - 15 + $fb4 ][8] = $get_fmt_bit( $fb4 );
		}
		$matrix[ $size - 8 ][8] = true; // Fixed dark module

		return $matrix;
	}

	/**
	 * Render a real ISO/IEC 18004 QR Code as an email-compatible HTML table.
	 * Includes a 4-module white Quiet Zone directly inside the table grid (matching
	 * renderQRCodeSVG in assets/js/tarbwy-qr.js) so mobile & desktop camera scanners
	 * decode the QR reliably across all email clients and printouts.
	 *
	 * @param string $payload   Canonical QR payload string (e.g. TKT-2026-XXXXXX).
	 * @param int    $module_px Pixel size per QR module (default 6px -> 174x174px with 4-module quiet zone).
	 * @return string HTML table markup.
	 */
	public static function render_qr_html_table( $payload, $module_px = 6 ) {
		$canonical     = self::get_canonical_qr_payload( $payload );
		$matrix        = self::build_qr_matrix( $canonical );
		$size          = count( $matrix );
		$quiet_zone    = 4;
		$total_modules = $size + ( $quiet_zone * 2 );
		$module_px     = max( 4, min( 10, (int) $module_px ) );
		$total_px      = $total_modules * $module_px;

		$html  = '<div dir="ltr" data-qr-payload="' . esc_attr( $canonical ) . '" style="display:inline-block;padding:4px;background-color:#ffffff;border:2px solid #0f172a;border-radius:10px;line-height:0;font-size:0;">';
		$html .= '<table role="presentation" dir="ltr" cellpadding="0" cellspacing="0" border="0" width="' . $total_px . '" height="' . $total_px . '" style="width:' . $total_px . 'px;height:' . $total_px . 'px;border-collapse:collapse;table-layout:fixed;margin:0 auto;padding:0;background-color:#ffffff;">';
		for ( $r = -$quiet_zone; $r < $size + $quiet_zone; $r++ ) {
			$html .= '<tr style="height:' . $module_px . 'px;line-height:0;font-size:0;">';
			for ( $c = -$quiet_zone; $c < $size + $quiet_zone; $c++ ) {
				$is_dark = ( $r >= 0 && $r < $size && $c >= 0 && $c < $size ) ? $matrix[ $r ][ $c ] : false;
				$bg      = $is_dark ? '#0f172a' : '#ffffff';
				$html   .= '<td width="' . $module_px . '" height="' . $module_px . '" style="width:' . $module_px . 'px;height:' . $module_px . 'px;background-color:' . $bg . ';padding:0;margin:0;border:0;font-size:0;line-height:0;"></td>';
			}
			$html .= '</tr>';
		}
		$html .= '</table></div>';

		return $html;
	}
}

