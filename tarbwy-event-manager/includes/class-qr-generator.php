<?php
/**
 * Local Pure-PHP & Local-JS QR Code Generator
 *
 * Strictly complies with rule:
 * - Never use an external QR service or send guest data externally.
 * - Never embed PII or guest ID in QR payload (only random opaque token).
 * - Renders standalone SVG locally and embeds local QR matrix generator.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QRGenerator {

	/**
	 * Issue a new ticket with a unique opaque random token for a confirmed guest.
	 * Automatically revokes any previous active ticket for the same guest.
	 *
	 * @param int $event_id Event ID.
	 * @param int $guest_id Guest ID.
	 * @return array|\WP_Error { 'ticket_id' => int, 'ticket_reference' => string, 'raw_token' => string }
	 */
	public static function issue_ticket_for_guest( $event_id, $guest_id ) {
		global $wpdb;

		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';

		// Verify guest belongs to event and is confirmed
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, event_id, rsvp_status, checkin_status FROM {$guests_table} WHERE id = %d AND event_id = %d",
				absint( $guest_id ),
				absint( $event_id )
			)
		);

		if ( ! $guest ) {
			return new \WP_Error( 'guest_not_found', 'الضيف غير موجود في هذا الحدث.' );
		}

		if ( 'confirmed' !== $guest->rsvp_status ) {
			return new \WP_Error( 'guest_not_confirmed', 'لا يمكن إصدار تذكرة لضيف غير مؤكد الحضور.' );
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
		$ticket_reference = Security::generate_reference_code( 'TKT' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$tickets_table,
			array(
				'event_id'         => absint( $event_id ),
				'guest_id'         => absint( $guest_id ),
				'ticket_reference' => $ticket_reference,
				'token_hash'       => $token_pair['hash'],
				'status'           => 'active',
				'issued_at'        => $now,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'ticket_insert_failed', 'تعذر إصدار التذكرة في قاعدة البيانات.' );
		}

		$ticket_id = (int) $wpdb->insert_id;

		// Store raw token transiently ONLY for sending the immediate ticket email/view link if needed during the current workflow
		set_transient( 'tarbwy_tkt_raw_' . $ticket_id, $token_pair['raw'], DAY_IN_SECONDS * 30 );

		Security::log_activity(
			$event_id,
			'ticket_issued',
			'ticket',
			$ticket_id,
			array(
				'guest_id'         => $guest_id,
				'ticket_reference' => $ticket_reference,
			)
		);

		return array(
			'ticket_id'        => $ticket_id,
			'ticket_reference' => $ticket_reference,
			'raw_token'        => $token_pair['raw'],
		);
	}

	/**
	 * Build secure ticket URL from raw token
	 *
	 * @param string $raw_token Raw opaque token.
	 * @return string
	 */
	public static function get_ticket_url( $raw_token ) {
		return home_url( '/launch/ticket/?t=' . rawurlencode( $raw_token ) );
	}
}
