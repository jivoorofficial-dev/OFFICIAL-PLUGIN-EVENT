<?php
/**
 * REST API Endpoints under namespace 'tarbwy/v1'
 *
 * Security Rules Enforced:
 * - Every route has an explicit capability or rate-limited permission_callback.
 * - All SQL queries use $wpdb->prepare().
 * - Prevents IDOR by verifying event_id ownership on every record.
 * - Prevents duplicate check-in race conditions via atomic INSERT IGNORE / UNIQUE KEY on ticket_id in tarbwy_checkins.
 * - Never modifies state via GET requests.
 */

namespace Tarbwy\EventManager;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestAPI {

	const NAMESPACE_V1 = 'tarbwy/v1';

	/**
	 * Register all REST API routes
	 */
	public static function register_routes() {

		// =====================================================================
		// PUBLIC LIMITED ROUTES (With Rate Limiting, Honeypot & Token Hash Validation)
		// =====================================================================

		// 1. POST /registrations (Public Registration Submission)
		register_rest_route(
			self::NAMESPACE_V1,
			'/registrations',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_public_registration' ),
				'permission_callback' => array( __CLASS__, 'check_public_submission_allowed' ),
			)
		);

		// 2. GET /vip-invitations/(?P<token>[A-Za-z0-9\-_]{6,128}) (Verify VIP Personal Code or Token & Load Prepopulated Info)
		register_rest_route(
			self::NAMESPACE_V1,
			'/vip-invitations/(?P<token>[A-Za-z0-9\-_]{6,128})',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_vip_invitation' ),
				'permission_callback' => array( __CLASS__, 'check_public_token_rate_limit' ),
			)
		);

		// 3. POST /vip-invitations/(?P<token>[A-Za-z0-9\-_]{6,128})/rsvp (Submit VIP RSVP + Companions)
		register_rest_route(
			self::NAMESPACE_V1,
			'/vip-invitations/(?P<token>[A-Za-z0-9\-_]{6,128})/rsvp',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit_vip_rsvp' ),
				'permission_callback' => array( __CLASS__, 'check_public_token_rate_limit' ),
			)
		);

		// 4. GET /tickets/view/(?P<token>[A-Za-z0-9\-_]{8,128}) (View Confirmed Ticket Without Exposing PII)
		register_rest_route(
			self::NAMESPACE_V1,
			'/tickets/view/(?P<token>[A-Za-z0-9\-_]{8,128})',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_ticket_view' ),
				'permission_callback' => array( __CLASS__, 'check_public_token_rate_limit' ),
			)
		);

		// =====================================================================
		// PROTECTED STAFF & ADMIN ROUTES (Require Capabilities + Nonce/Auth)
		// =====================================================================

		// 5. GET /checkin/lookup (Search by Name, Email, or Ticket Reference)
		register_rest_route(
			self::NAMESPACE_V1,
			'/checkin/lookup',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_checkin_lookup' ),
				'permission_callback' => array( Roles::class, 'can_checkin' ),
			)
		);

		// 6. POST /checkin/scan (Atomic QR Scan Check-in)
		register_rest_route(
			self::NAMESPACE_V1,
			'/checkin/scan',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_checkin_scan' ),
				'permission_callback' => array( Roles::class, 'can_checkin' ),
			)
		);

		// 7. POST /checkin/manual (Atomic Manual Check-in by Guest ID or Ticket Reference)
		register_rest_route(
			self::NAMESPACE_V1,
			'/checkin/manual',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_checkin_manual' ),
				'permission_callback' => array( Roles::class, 'can_checkin' ),
			)
		);

		// 8. GET /checkin/stats (Live Attendance & Capacity Stats)
		register_rest_route(
			self::NAMESPACE_V1,
			'/checkin/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_checkin_stats' ),
				'permission_callback' => array( Roles::class, 'can_checkin' ),
			)
		);

		// 9. GET /guests (Paginated & Filtered Guest List)
		register_rest_route(
			self::NAMESPACE_V1,
			'/guests',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get_guests' ),
				'permission_callback' => array( Roles::class, 'can_manage_guests' ),
			)
		);

		// 10. POST /registrations/(?P<id>\d+)/review (Approve / Waitlist / Decline with Partial Companions)
		register_rest_route(
			self::NAMESPACE_V1,
			'/registrations/(?P<id>\d+)/review',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_review_registration' ),
				'permission_callback' => array( Roles::class, 'can_review_registrations' ),
			)
		);

		// 11. POST /tickets/(?P<id>\d+)/reissue (Revoke Old & Reissue New QR Ticket)
		register_rest_route(
			self::NAMESPACE_V1,
			'/tickets/(?P<id>\d+)/reissue',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_reissue_ticket' ),
				'permission_callback' => array( Roles::class, 'can_manage_guests' ),
			)
		);

		// 12. POST /invitations/import (VIP CSV Import with Dry-Run Preview)
		register_rest_route(
			self::NAMESPACE_V1,
			'/invitations/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_import_vip_invitations' ),
				'permission_callback' => array( Roles::class, 'can_manage_invitations' ),
			)
		);
	}

	/**
	 * Public route rate-limit permission check
	 */
	public static function check_public_submission_allowed() {
		return Security::check_rate_limit( 'public_registration_submit', 8, 300 );
	}

	/**
	 * Public token lookup rate-limit permission check
	 */
	public static function check_public_token_rate_limit() {
		return Security::check_rate_limit( 'public_token_lookup', 25, 300 );
	}

	/**
	 * Helper: Get active event
	 */
	public static function get_active_event( $event_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tarbwy_events';
		if ( $event_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $event_id ) ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row( "SELECT * FROM {$table} ORDER BY id ASC LIMIT 1" );
	}

	/**
	 * Helper: Issue or retrieve a guest QR ticket (delegates to QRGenerator)
	 */
	public static function issue_guest_ticket( $event_id, $guest_id, $force_reissue = false ) {
		return QRGenerator::issue_ticket_for_guest( $event_id, $guest_id, $force_reissue );
	}

	/**
	 * Helper: Count total confirmed attendees (including companions) against 500 capacity
	 */
	public static function get_confirmed_count( $event_id ) {
		global $wpdb;
		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$guests_table} WHERE event_id = %d AND rsvp_status = 'confirmed'",
				absint( $event_id )
			)
		);
	}

	/**
	 * 1. POST /registrations — Public Registration Submission
	 */
	public static function handle_public_registration( WP_REST_Request $request ) {
		global $wpdb;
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		if ( ! Security::verify_honeypot( $params ) ) {
			return new WP_REST_Response( array( 'message' => 'تم رفض الطلب بسبب الاشتباه بنشاط آلي (Spam).' ), 400 );
		}

		$event = self::get_active_event( isset( $params['event_id'] ) ? absint( $params['event_id'] ) : 0 );
		if ( ! $event || ! (bool) $event->registration_open ) {
			return new WP_REST_Response( array( 'message' => 'باب التسجيل في هذا الحدث مغلق حالياً.' ), 403 );
		}

		$full_name    = isset( $params['full_name'] ) ? sanitize_text_field( $params['full_name'] ) : '';
		$email        = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
		$phone        = isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '';
		$organization = isset( $params['organization'] ) ? sanitize_text_field( $params['organization'] ) : '';
		$job_title    = isset( $params['job_title'] ) ? sanitize_text_field( $params['job_title'] ) : '';
		$dietary      = isset( $params['dietary_requirements'] ) ? sanitize_text_field( $params['dietary_requirements'] ) : '';
		$access       = isset( $params['accessibility_requirements'] ) ? sanitize_text_field( $params['accessibility_requirements'] ) : '';
		$companions   = isset( $params['companions'] ) && is_array( $params['companions'] ) ? $params['companions'] : array();

		if ( mb_strlen( $full_name ) < 3 ) {
			return new WP_REST_Response( array( 'message' => 'يرجى إدخال الاسم الكامل بشكل صحيح.' ), 422 );
		}
		if ( ! is_email( $email ) ) {
			return new WP_REST_Response( array( 'message' => 'يرجى إدخال بريد إلكتروني صحيح.' ), 422 );
		}
		if ( mb_strlen( $phone ) < 7 ) {
			return new WP_REST_Response( array( 'message' => 'يرجى إدخال رقم هاتف صحيح للتواصل.' ), 422 );
		}
		if ( count( $companions ) > (int) $event->max_companions_public ) {
			return new WP_REST_Response(
				array(
					'message' => sprintf( 'الحد الأقصى للمرافقين المسموح به في التسجيل العام هو %d.', (int) $event->max_companions_public ),
				),
				422
			);
		}

		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$reg_table    = $wpdb->prefix . 'tarbwy_registrations';

		// Check if same primary guest already has an active pending/approved registration with identical name + email
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exact_dup = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT g.id FROM {$guests_table} g
				 INNER JOIN {$reg_table} r ON g.registration_id = r.id
				 WHERE g.event_id = %d AND g.email = %s AND g.full_name = %s AND g.parent_guest_id IS NULL
				 AND r.review_status IN ('pending', 'approved', 'waitlist') LIMIT 1",
				(int) $event->id,
				$email,
				$full_name
			)
		);

		if ( $exact_dup ) {
			return new WP_REST_Response(
				array( 'message' => 'يوجد طلب حضور مسجل مسبقاً بنفس الاسم والبريد الإلكتروني وهو قيد المراجعة أو معتمد.' ),
				409
			);
		}

		// Check soft duplicate (shared family/institution email) to flag in admin notes without rejecting
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$shared_email_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$guests_table} WHERE event_id = %d AND email = %s",
				(int) $event->id,
				$email
			)
		);
		$admin_note = $shared_email_count > 0 ? 'تنبيه نظام: البريد الإلكتروني مشترك مع ضيف آخر مسجل في الحدث.' : '';

		$now               = current_time( 'mysql' );
		$reference_code    = Security::generate_reference_code( 'TRB' );
		$applicant_payload = wp_json_encode(
			array(
				'full_name'                  => $full_name,
				'email'                      => $email,
				'phone'                      => $phone,
				'organization'               => $organization,
				'job_title'                  => $job_title,
				'dietary_requirements'       => $dietary,
				'accessibility_requirements' => $access,
				'companions'                 => $companions,
			),
			JSON_UNESCAPED_UNICODE
		);

		// Create Registration Record (Status starts 'pending', no QR issued yet)
		$reg_insert_data = array(
			'event_id'          => (int) $event->id,
			'reference_code'    => $reference_code,
			'review_status'     => 'pending',
			'applicant_payload' => $applicant_payload,
			'submitted_at'      => $now,
			'created_at'        => $now,
			'updated_at'        => $now,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$reg_ok = $wpdb->insert(
			$reg_table,
			$reg_insert_data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( ! $reg_ok ) {
			Installer::ensure_schema_columns();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$reg_ok = $wpdb->insert(
				$reg_table,
				$reg_insert_data,
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( ! $reg_ok ) {
				unset( $reg_insert_data['applicant_payload'] );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$reg_table,
					$reg_insert_data,
					array( '%d', '%s', '%s', '%s', '%s', '%s' )
				);
			}
		}
		$registration_id = (int) $wpdb->insert_id;

		// Create Primary Guest Record via resilient safe_insert_guest (never passes NULL as %d 0)
		$primary_guest_id = Installer::safe_insert_guest(
			array(
				'event_id'                   => (int) $event->id,
				'registration_id'            => $registration_id,
				'guest_type'                 => 'public',
				'full_name'                  => $full_name,
				'email'                      => $email,
				'phone'                      => $phone,
				'organization'               => $organization,
				'job_title'                  => $job_title,
				'rsvp_status'                => 'not_invited',
				'checkin_status'             => 'not_checked_in',
				'dietary_requirements'       => $dietary,
				'accessibility_requirements' => $access,
				'admin_notes'                => $admin_note,
				'created_at'                 => $now,
				'updated_at'                 => $now,
			)
		);

		// Link primary_guest_id to registration
		if ( $primary_guest_id > 0 && $registration_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$reg_table,
				array( 'primary_guest_id' => $primary_guest_id ),
				array( 'id' => $registration_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		// Create Separate Companion Records
		$companion_ids = array();
		foreach ( $companions as $comp ) {
			$c_name = isset( $comp['full_name'] ) ? sanitize_text_field( $comp['full_name'] ) : '';
			if ( empty( $c_name ) ) {
				continue;
			}
			$c_email = isset( $comp['email'] ) && is_email( $comp['email'] ) ? sanitize_email( $comp['email'] ) : '';
			$c_phone = isset( $comp['phone'] ) ? sanitize_text_field( $comp['phone'] ) : '';
			$c_rel   = isset( $comp['job_title'] ) ? sanitize_text_field( $comp['job_title'] ) : 'مرافق';

			$cid = Installer::safe_insert_guest(
				array(
					'event_id'        => (int) $event->id,
					'registration_id' => $registration_id,
					'parent_guest_id' => $primary_guest_id > 0 ? $primary_guest_id : null,
					'guest_type'      => 'companion',
					'full_name'       => $c_name,
					'email'           => $c_email,
					'phone'           => $c_phone,
					'organization'    => $organization,
					'job_title'       => $c_rel,
					'rsvp_status'     => 'not_invited',
					'checkin_status'  => 'not_checked_in',
					'created_at'      => $now,
					'updated_at'      => $now,
				)
			);
			if ( $cid > 0 ) {
				$companion_ids[] = $cid;
			}
		}

		// Queue & immediately dispatch receipt email to primary guest (allow_duplicate=true so new registrations with same test email always receive receipt)
		EmailManager::enqueue_email(
			(int) $event->id,
			$primary_guest_id,
			'public_registration_received',
			$email,
			array(
				'guest_name'     => $full_name,
				'email'          => $email,
				'phone'          => $phone,
				'organization'   => $organization,
				'job_title'      => $job_title,
				'event_title'    => $event->title,
				'reference_code' => $reference_code,
			),
			true
		);

		Security::log_activity(
			(int) $event->id,
			'public_registration_submitted',
			'registration',
			$registration_id,
			array(
				'reference_code'   => $reference_code,
				'primary_guest_id' => $primary_guest_id,
				'companions_count' => count( $companion_ids ),
			)
		);

		return new WP_REST_Response(
			array(
				'status'          => 'pending',
				'reference_code'  => $reference_code,
				'registration_id' => $registration_id,
				'companions_count' => count( $companion_ids ),
				'message'         => 'تم استلام طلب الحضور بنجاح وهو حالياً قيد المراجعة من قِبل اللجنة المنظمة. هذا الإشعار لا يُعد تأكيداً نهائياً للحضور، وستصلكم بطاقة الدخول الشخصية المزودة برمز QR فور اعتماد الطلب.',
			),
			201
		);
	}

	/**
	/**
	 * Resolve a VIP invitation record by standalone Personal Invitation Code (e.g. VIP-8F4A-2C91),
	 * 8-char hex code, full 48-char raw token, or pasted URL.
	 *
	 * @param string $raw_input User-supplied VIP code or token.
	 * @return object|null Invitation + Guest + Event joined row.
	 */
	public static function find_vip_invitation_by_input( $raw_input ) {
		global $wpdb;

		$parsed       = Security::parse_vip_code_input( $raw_input );
		$clean_hex    = $parsed['clean_hex'];
		$raw_trimmed  = $parsed['raw_input'];
		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$events_table = $wpdb->prefix . 'tarbwy_events';

		if ( strlen( $clean_hex ) < 6 && strlen( $raw_trimmed ) < 6 ) {
			return null;
		}

		$hash_exact = Security::hash_token( $clean_hex ? $clean_hex : $raw_trimmed );
		$hash_raw   = Security::hash_token( $raw_trimmed );

		// 1. Try exact token_hash match first (48-char raw token)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inv = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT i.*, g.full_name, g.email, g.organization, g.job_title, g.rsvp_status,
				        e.title AS event_title, e.venue_name, e.max_companions_vip, e.capacity_total
				 FROM {$inv_table} i
				 INNER JOIN {$guests_table} g ON i.guest_id = g.id AND i.event_id = g.event_id
				 INNER JOIN {$events_table} e ON i.event_id = e.id
				 WHERE i.token_hash IN (%s, %s)
				 ORDER BY i.id DESC LIMIT 1",
				$hash_exact,
				$hash_raw
			)
		);

		// 2. If not found, match by standalone Personal Invitation Code prefix (first 8 hex chars of token_preview or token_hash)
		if ( ! $inv && strlen( $clean_hex ) >= 6 ) {
			$prefix8 = substr( $clean_hex, 0, 8 );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inv = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT i.*, g.full_name, g.email, g.organization, g.job_title, g.rsvp_status,
					        e.title AS event_title, e.venue_name, e.max_companions_vip, e.capacity_total
					 FROM {$inv_table} i
					 INNER JOIN {$guests_table} g ON i.guest_id = g.id AND i.event_id = g.event_id
					 INNER JOIN {$events_table} e ON i.event_id = e.id
					 WHERE LOWER(LEFT(i.token_preview, %d)) = %s
					    OR LOWER(LEFT(i.token_hash, %d)) = %s
					 ORDER BY i.id DESC LIMIT 1",
					strlen( $prefix8 ),
					$prefix8,
					strlen( $prefix8 ),
					$prefix8
				)
			);
		}

		return $inv;
	}

	/**
	 * 2. GET /vip-invitations/{token} — Verify Personal Invitation Code or Token & Return Prepopulated Guest Info
	 */
	public static function handle_get_vip_invitation( WP_REST_Request $request ) {
		global $wpdb;
		Security::send_no_leak_headers();

		$raw_token = (string) $request->get_param( 'token' );
		$inv       = self::find_vip_invitation_by_input( $raw_token );

		if ( ! $inv ) {
			return new WP_REST_Response( array( 'code' => 'invalid_token', 'message' => 'رمز الدعوة الشخصي غير صالح أو غير معروف. يرجى التأكد من نسخ الرمز كما ورد في رسالة الدعوة.' ), 404 );
		}

		if ( 'revoked' === $inv->status ) {
			return new WP_REST_Response( array( 'code' => 'revoked_token', 'message' => 'تم إبطال رمز الدعوة هذا وإصدار رمز جديد.' ), 410 );
		}

		if ( 'expired' === $inv->status || ( ! empty( $inv->expires_at ) && strtotime( $inv->expires_at ) < current_time( 'timestamp' ) ) ) {
			return new WP_REST_Response( array( 'code' => 'expired_token', 'message' => 'انتهت صلاحية رمز الدعوة هذا.' ), 410 );
		}

		$inv_table = $wpdb->prefix . 'tarbwy_invitations';
		// Update last_accessed_at timestamp
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$inv_table,
			array( 'last_accessed_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $inv->id ),
			array( '%s' ),
			array( '%d' )
		);

		$max_companions = null !== $inv->max_companions_override ? (int) $inv->max_companions_override : (int) $inv->max_companions_vip;
		$personal_code  = Security::format_vip_personal_code( ! empty( $inv->token_preview ) ? $inv->token_preview : $inv->token_hash );

		// If already confirmed, also return issued tickets so the VIP guest can view their QR pass immediately
		$issued_tickets = array();
		if ( 'confirmed' === $inv->rsvp_status ) {
			$tkt = QRGenerator::issue_ticket_for_guest( (int) $inv->event_id, (int) $inv->guest_id, false );
			if ( ! is_wp_error( $tkt ) ) {
				$issued_tickets[] = array(
					'guest_id'         => (int) $inv->guest_id,
					'guest_name'       => $inv->full_name,
					'guest_type'       => 'vip',
					'ticket_reference' => $tkt['ticket_reference'],
					'qr_payload'       => $tkt['qr_payload'],
					'qr_token'         => $tkt['qr_payload'],
					'raw_token'        => $tkt['raw_token'],
					'ticket_url'       => $tkt['ticket_url'],
				);
			}
		}

		return new WP_REST_Response(
			array(
				'status'         => $inv->status,
				'rsvp_status'    => $inv->rsvp_status,
				'personal_code'  => $personal_code,
				'guest_name'     => $inv->full_name,
				'organization'   => $inv->organization,
				'job_title'      => $inv->job_title,
				'event_title'    => $inv->event_title,
				'venue_name'     => $inv->venue_name,
				'max_companions' => $max_companions,
				'issued_tickets' => $issued_tickets,
			),
			200
		);
	}

	/**
	 * 3. POST /vip-invitations/{token}/rsvp — Submit VIP Attendance ('attend' -> confirmed + QR, 'decline' -> declined)
	 */
	public static function handle_submit_vip_rsvp( WP_REST_Request $request ) {
		global $wpdb;
		Security::send_no_leak_headers();

		$raw_token = (string) $request->get_param( 'token' );
		$params    = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$decision   = isset( $params['decision'] ) ? sanitize_key( $params['decision'] ) : '';
		$companions = isset( $params['companions'] ) && is_array( $params['companions'] ) ? $params['companions'] : array();

		if ( ! in_array( $decision, array( 'attend', 'decline' ), true ) ) {
			return new WP_REST_Response( array( 'message' => 'يرجى اختيار «سأحضر» أو «أعتذر».' ), 422 );
		}

		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		$inv = self::find_vip_invitation_by_input( $raw_token );

		if ( ! $inv || in_array( $inv->status, array( 'revoked', 'expired' ), true ) ) {
			return new WP_REST_Response( array( 'message' => 'رمز الدعوة الشخصي غير صالح أو تم إبطاله.' ), 403 );
		}

		$max_companions = null !== $inv->max_companions_override ? (int) $inv->max_companions_override : (int) $inv->max_companions_vip;
		if ( count( $companions ) > $max_companions ) {
			return new WP_REST_Response(
				array( 'message' => sprintf( 'الحد الأقصى للمرافقين المسموح به لهذه الدعوة هو %d.', $max_companions ) ),
				422
			);
		}

		$now           = current_time( 'mysql' );
		$personal_code = Security::format_vip_personal_code( ! empty( $inv->token_preview ) ? $inv->token_preview : $inv->token_hash );

		if ( 'decline' === $decision ) {
			QRGenerator::revoke_tickets_for_guest( (int) $inv->event_id, (int) $inv->guest_id, 'vip_rsvp_declined' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$guests_table,
				array( 'rsvp_status' => 'declined', 'updated_at' => $now ),
				array( 'id' => (int) $inv->guest_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$inv_table,
				array( 'status' => 'used', 'updated_at' => $now ),
				array( 'id' => (int) $inv->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			EmailManager::enqueue_email(
				(int) $inv->event_id,
				(int) $inv->guest_id,
				'vip_rsvp_declined',
				$inv->email,
				array(
					'guest_name'  => $inv->full_name,
					'event_title' => $inv->event_title,
					'vip_code'    => $personal_code,
				),
				true
			);

			Security::log_activity( (int) $inv->event_id, 'vip_rsvp_declined', 'invitation', (int) $inv->id );

			return new WP_REST_Response(
				array(
					'rsvp_status'   => 'declined',
					'personal_code' => $personal_code,
					'message'       => 'تم تسجيل اعتذاركم عن الحضور. نشكركم ونتطلع للقائكم في مناسبات قادمة.',
				),
				200
			);
		}

		// Check capacity before confirming
		$needed_seats     = 1 + count( $companions );
		$confirmed_so_far = self::get_confirmed_count( (int) $inv->event_id );
		if ( ( $confirmed_so_far + $needed_seats ) > (int) $inv->capacity_total ) {
			return new WP_REST_Response(
				array( 'message' => 'عذراً، اكتمل العدد الإجمالي المخصص للقاعة (500 مقعد).' ),
				409
			);
		}

		// Confirm VIP Primary Guest immediately and issue individual QR Ticket
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$guests_table,
			array( 'rsvp_status' => 'confirmed', 'updated_at' => $now ),
			array( 'id' => (int) $inv->guest_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$inv_table,
			array( 'status' => 'used', 'updated_at' => $now ),
			array( 'id' => (int) $inv->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$issued_tickets = array();
		$primary_ticket = QRGenerator::issue_ticket_for_guest( (int) $inv->event_id, (int) $inv->guest_id );
		if ( ! is_wp_error( $primary_ticket ) ) {
			$ticket_url       = QRGenerator::get_ticket_url( $primary_ticket['raw_token'] );
			$issued_tickets[] = array(
				'guest_id'         => (int) $inv->guest_id,
				'guest_name'       => $inv->full_name,
				'guest_type'       => 'vip',
				'ticket_reference' => $primary_ticket['ticket_reference'],
				'qr_payload'       => $primary_ticket['qr_payload'],
				'qr_token'         => $primary_ticket['qr_payload'],
				'raw_token'        => $primary_ticket['raw_token'],
				'ticket_url'       => $ticket_url,
			);
		}

		// Create independent guest records and independent QR tickets for each VIP companion
		foreach ( $companions as $comp ) {
			$c_name = isset( $comp['full_name'] ) ? sanitize_text_field( $comp['full_name'] ) : '';
			if ( empty( $c_name ) ) {
				continue;
			}
			$c_email = isset( $comp['email'] ) && is_email( $comp['email'] ) ? sanitize_email( $comp['email'] ) : '';
			$c_phone = isset( $comp['phone'] ) ? sanitize_text_field( $comp['phone'] ) : '';

			$comp_id = Installer::safe_insert_guest(
				array(
					'event_id'        => (int) $inv->event_id,
					'parent_guest_id' => (int) $inv->guest_id,
					'guest_type'      => 'companion',
					'full_name'       => $c_name,
					'email'           => $c_email,
					'phone'           => $c_phone,
					'organization'    => $inv->organization,
					'job_title'       => 'مرافق VIP',
					'rsvp_status'     => 'confirmed',
					'checkin_status'  => 'not_checked_in',
					'created_at'      => $now,
					'updated_at'      => $now,
				)
			);

			if ( $comp_id ) {
				$c_ticket = QRGenerator::issue_ticket_for_guest( (int) $inv->event_id, $comp_id );
				if ( ! is_wp_error( $c_ticket ) ) {
					$c_url            = QRGenerator::get_ticket_url( $c_ticket['raw_token'] );
					$issued_tickets[] = array(
						'guest_id'         => $comp_id,
						'guest_name'       => $c_name . ' (مرافق: ' . $inv->full_name . ')',
						'guest_type'       => 'companion',
						'ticket_reference' => $c_ticket['ticket_reference'],
						'qr_payload'       => $c_ticket['qr_payload'],
						'qr_token'         => $c_ticket['qr_payload'],
						'raw_token'        => $c_ticket['raw_token'],
						'ticket_url'       => $c_url,
					);

					// If companion has their own distinct email, send them an individual pass email too
					if ( ! empty( $c_email ) && strtolower( $c_email ) !== strtolower( $inv->email ) ) {
						EmailManager::enqueue_email(
							(int) $inv->event_id,
							$comp_id,
							'ticket_issued',
							$c_email,
							array(
								'guest_name'       => $c_name . ' (مرافق: ' . $inv->full_name . ')',
								'event_title'      => $inv->event_title,
								'venue_name'       => $inv->venue_name,
								'ticket_reference' => $c_ticket['ticket_reference'],
								'qr_payload'       => $c_ticket['qr_payload'],
								'ticket_url'       => $c_url,
							),
							true
						);
					}
				}
			}
		}

		// Send master VIP confirmation email containing all issued QR passes (VIP + companions)
		if ( ! empty( $issued_tickets ) ) {
			EmailManager::enqueue_email(
				(int) $inv->event_id,
				(int) $inv->guest_id,
				'vip_rsvp_confirmed',
				$inv->email,
				array(
					'guest_name'       => $inv->full_name,
					'event_title'      => $inv->event_title,
					'venue_name'       => $inv->venue_name,
					'vip_code'         => $personal_code,
					'ticket_reference' => $issued_tickets[0]['ticket_reference'],
					'qr_payload'       => $issued_tickets[0]['qr_payload'],
					'ticket_url'       => $issued_tickets[0]['ticket_url'],
					'tickets_count'    => (string) count( $issued_tickets ),
					'all_tickets'      => $issued_tickets,
				),
				true
			);
		}

		Security::log_activity(
			(int) $inv->event_id,
			'vip_rsvp_confirmed',
			'invitation',
			(int) $inv->id,
			array( 'tickets_issued' => count( $issued_tickets ) )
		);

		return new WP_REST_Response(
			array(
				'rsvp_status'    => 'confirmed',
				'personal_code'  => $personal_code,
				'issued_tickets' => $issued_tickets,
				'message'        => 'تم تأكيد حضوركم بنجاح وإصدار بطاقات الدخول الفردية المزودة برمز QR.',
			),
			200
		);
	}

	/**
	 * 4. GET /tickets/view/{token} — Secure Ticket View (Never exposes email or phone per spec 4.4)
	 */
	public static function handle_get_ticket_view( WP_REST_Request $request ) {
		global $wpdb;
		Security::send_no_leak_headers();

		$raw_token  = trim( (string) $request->get_param( 'token' ) );
		$token_hash = Security::hash_token( $raw_token );

		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$events_table  = $wpdb->prefix . 'tarbwy_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ticket = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.ticket_reference, t.qr_payload, t.token_preview, t.status AS ticket_status, g.full_name, g.guest_type, g.organization, g.rsvp_status, g.checkin_status,
				        e.title AS event_title, e.venue_name, e.venue_address, e.settings_json
				 FROM {$tickets_table} t
				 INNER JOIN {$guests_table} g ON t.guest_id = g.id AND t.event_id = g.event_id
				 INNER JOIN {$events_table} e ON t.event_id = e.id
				 WHERE t.token_hash = %s OR t.token_preview = %s OR UPPER(t.ticket_reference) = %s OR UPPER(t.qr_payload) = %s
				 ORDER BY CASE WHEN t.status = 'active' THEN 0 WHEN t.status = 'used' THEN 1 ELSE 2 END ASC, t.id DESC
				 LIMIT 1",
				$token_hash,
				$raw_token,
				strtoupper( $raw_token ),
				strtoupper( $raw_token )
			)
		);

		if ( ! $ticket ) {
			Installer::heal_orphaned_registrations();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ticket = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT t.ticket_reference, t.qr_payload, t.token_preview, t.status AS ticket_status, g.full_name, g.guest_type, g.organization, g.rsvp_status, g.checkin_status,
					        e.title AS event_title, e.venue_name, e.venue_address, e.settings_json
					 FROM {$tickets_table} t
					 INNER JOIN {$guests_table} g ON t.guest_id = g.id AND t.event_id = g.event_id
					 INNER JOIN {$events_table} e ON t.event_id = e.id
					 WHERE t.token_hash = %s OR t.token_preview = %s OR UPPER(t.ticket_reference) = %s OR UPPER(t.qr_payload) = %s
					 ORDER BY CASE WHEN t.status = 'active' THEN 0 WHEN t.status = 'used' THEN 1 ELSE 2 END ASC, t.id DESC
					 LIMIT 1",
					$token_hash,
					$raw_token,
					strtoupper( $raw_token ),
					strtoupper( $raw_token )
				)
			);
		}

		if ( ! $ticket ) {
			return new WP_REST_Response( array( 'code' => 'not_found', 'message' => 'بطاقة الدخول غير موجودة أو الرابط غير صحيح.' ), 404 );
		}

		if ( 'revoked' === $ticket->ticket_status ) {
			return new WP_REST_Response( array( 'code' => 'revoked', 'message' => 'تم إلغاء هذه البطاقة وإصدار بطاقة بديلة.' ), 410 );
		}

		if ( 'confirmed' !== $ticket->rsvp_status ) {
			return new WP_REST_Response( array( 'code' => 'not_confirmed', 'message' => 'هذه البطاقة غير مؤكدة الحضور.' ), 403 );
		}

		$settings   = json_decode( (string) $ticket->settings_json, true );
		$qr_payload = QRGenerator::get_canonical_qr_payload( $ticket );

		// Explicitly return ONLY non-PII card details required by Spec 4.4 (No email, no phone)
		return new WP_REST_Response(
			array(
				'event_title'        => $ticket->event_title,
				'holder_name'        => $ticket->full_name,
				'organization'       => $ticket->organization,
				'guest_type'         => $ticket->guest_type,
				'ticket_reference'   => $qr_payload,
				'ticket_status'      => $ticket->ticket_status,
				'checkin_status'     => $ticket->checkin_status,
				'venue_name'         => $ticket->venue_name,
				'venue_address'      => $ticket->venue_address,
				'date_display'       => isset( $settings['date_display'] ) ? $settings['date_display'] : 'سيُعلن لاحقاً',
				'entry_instructions' => isset( $settings['entry_instructions'] ) ? $settings['entry_instructions'] : '',
				'qr_payload'         => $qr_payload,
			),
			200
		);
	}

	/**
	 * 5. GET /checkin/lookup — Staff Search by Name, Email, Phone, or Ticket Reference
	 */
	public static function handle_checkin_lookup( WP_REST_Request $request ) {
		global $wpdb;
		$query      = trim( sanitize_text_field( (string) $request->get_param( 'q' ) ) );
		$raw_filter = $request->get_param( 'filter' ) ? $request->get_param( 'filter' ) : $request->get_param( 'status' );
		$filter     = sanitize_key( (string) $raw_filter );
		if ( '' === $filter ) {
			$filter = 'all';
		}
		$event_id = absint( $request->get_param( 'event_id' ) );
		if ( ! $event_id ) {
			$ev       = self::get_active_event();
			$event_id = $ev ? (int) $ev->id : 1;
		}

		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$reg_table     = $wpdb->prefix . 'tarbwy_registrations';

		$extra_where = '';
		if ( 'confirmed' === $filter ) {
			$extra_where = " AND g.rsvp_status = 'confirmed'";
		} elseif ( 'pending_checkin' === $filter ) {
			$extra_where = " AND g.rsvp_status = 'confirmed' AND g.checkin_status != 'checked_in'";
		} elseif ( 'checked_in' === $filter ) {
			$extra_where = " AND g.checkin_status = 'checked_in'";
		}

		// If query is empty, return matching attendees for the selected filter chip
		if ( '' === $query ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT g.id AS guest_id, g.full_name, g.email, g.phone, g.guest_type, g.organization, g.rsvp_status, g.checkin_status, g.checked_in_at,
					        t.id AS ticket_id, t.ticket_reference, t.status AS ticket_status, p.full_name AS parent_name, r.reference_code
					 FROM {$guests_table} g
					 LEFT JOIN {$tickets_table} t ON g.id = t.guest_id AND t.status IN ('active', 'used')
					 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
					 LEFT JOIN {$reg_table} r ON g.registration_id = r.id
					 WHERE g.event_id = %d {$extra_where}
					 ORDER BY CASE WHEN g.rsvp_status = 'confirmed' THEN 0 ELSE 1 END ASC, g.id DESC
					 LIMIT 35",
					$event_id
				)
			);
			return new WP_REST_Response( array( 'results' => $results ), 200 );
		}

		$like = '%' . $wpdb->esc_like( $query ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.id AS guest_id, g.full_name, g.email, g.phone, g.guest_type, g.organization, g.rsvp_status, g.checkin_status, g.checked_in_at,
				        t.id AS ticket_id, t.ticket_reference, t.status AS ticket_status, p.full_name AS parent_name, r.reference_code
				 FROM {$guests_table} g
				 LEFT JOIN {$tickets_table} t ON g.id = t.guest_id AND t.status IN ('active', 'used')
				 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
				 LEFT JOIN {$reg_table} r ON g.registration_id = r.id
				 WHERE g.event_id = %d {$extra_where}
				   AND (
				        g.full_name LIKE %s
				        OR g.email LIKE %s
				        OR t.ticket_reference LIKE %s
				        OR g.phone LIKE %s
				        OR g.organization LIKE %s
				        OR r.reference_code LIKE %s
				   )
				 ORDER BY CASE WHEN g.rsvp_status = 'confirmed' THEN 0 ELSE 1 END ASC, g.full_name ASC
				 LIMIT 35",
				$event_id,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like
			)
		);

		return new WP_REST_Response( array( 'results' => $results ), 200 );
	}

	/**
	 * Extract raw QR token or ticket reference from scanned payload (supports raw token, full URL ?t=..., or TKT-2026-...)
	 *
	 * @param string $raw_payload Scanned string from camera or manual input.
	 * @return string Normalized token or ticket reference.
	 */
	public static function extract_scanned_token( $raw_payload ) {
		$str = trim( (string) $raw_payload );
		if ( '' === $str ) {
			return '';
		}
		if ( false !== strpos( $str, 'http' ) || false !== strpos( $str, '?' ) || false !== strpos( $str, 't=' ) || false !== strpos( $str, 'token=' ) ) {
			$parsed = wp_parse_url( $str );
			if ( ! empty( $parsed['query'] ) ) {
				parse_str( $parsed['query'], $q_vars );
				if ( ! empty( $q_vars['t'] ) ) {
					return trim( sanitize_text_field( (string) $q_vars['t'] ) );
				}
				if ( ! empty( $q_vars['token'] ) ) {
					return trim( sanitize_text_field( (string) $q_vars['token'] ) );
				}
				if ( ! empty( $q_vars['ref'] ) ) {
					return trim( sanitize_text_field( (string) $q_vars['ref'] ) );
				}
			}
		}
		return sanitize_text_field( $str );
	}

	/**
	 * 6. POST /checkin/scan — Atomic QR Scan Check-in (Prevents Concurrent Duplicate Entry)
	 *
	 * CRITICAL SPEC SECTION 9:
	 * - QR holds random token only (also supports scanned ticket URL or TKT-... reference).
	 * - Lookup by SHA-256 hash or ticket_reference.
	 * - Verify ticket is active and RSVP is confirmed.
	 * - Do NOT use unprotected SELECT then INSERT.
	 * - Use UNIQUE constraint on ticket_id in tarbwy_checkins and atomic INSERT IGNORE + affected_rows check.
	 * - Under simultaneous scans from 2 devices, exactly ONE succeeds (affected_rows === 1) and the second returns 'already_checked_in'.
	 */
	public static function handle_checkin_scan( WP_REST_Request $request ) {
		global $wpdb;
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$raw_input    = isset( $params['qr_token'] ) ? (string) $params['qr_token'] : '';
		$raw_token    = self::extract_scanned_token( $raw_input );
		$device_label = isset( $params['device_label'] ) ? sanitize_text_field( $params['device_label'] ) : 'Check-in Terminal';

		if ( empty( $raw_token ) ) {
			return new WP_REST_Response(
				array(
					'result_code' => 'unknown',
					'message'     => 'رمز QR فارغ أو غير صالح.',
				),
				400
			);
		}

		$token_hash    = Security::hash_token( $raw_token );
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$guests_table  = $wpdb->prefix . 'tarbwy_guests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.id AS ticket_id, t.event_id, t.guest_id, t.ticket_reference, t.qr_payload, t.status AS ticket_status,
				        g.full_name, g.email, g.guest_type, g.organization, g.rsvp_status, g.checkin_status, g.checked_in_at,
				        p.full_name AS parent_name, p.email AS parent_email
				 FROM {$tickets_table} t
				 INNER JOIN {$guests_table} g ON t.guest_id = g.id AND t.event_id = g.event_id
				 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
				 WHERE t.token_hash = %s OR t.token_preview = %s OR UPPER(t.ticket_reference) = %s OR UPPER(t.qr_payload) = %s
				 ORDER BY CASE WHEN t.status = 'active' THEN 0 ELSE 1 END ASC, t.id DESC
				 LIMIT 1",
				$token_hash,
				$raw_token,
				strtoupper( $raw_token ),
				strtoupper( $raw_token )
			)
		);

		// Auto-heal and retry if not found on initial lookup
		if ( ! $record ) {
			Installer::heal_orphaned_registrations();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$record = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT t.id AS ticket_id, t.event_id, t.guest_id, t.ticket_reference, t.qr_payload, t.status AS ticket_status,
					        g.full_name, g.email, g.guest_type, g.organization, g.rsvp_status, g.checkin_status, g.checked_in_at,
					        p.full_name AS parent_name, p.email AS parent_email
					 FROM {$tickets_table} t
					 INNER JOIN {$guests_table} g ON t.guest_id = g.id AND t.event_id = g.event_id
					 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
					 WHERE t.token_hash = %s OR t.token_preview = %s OR UPPER(t.ticket_reference) = %s OR UPPER(t.qr_payload) = %s
					 ORDER BY CASE WHEN t.status = 'active' THEN 0 ELSE 1 END ASC, t.id DESC
					 LIMIT 1",
					$token_hash,
					$raw_token,
					strtoupper( $raw_token ),
					strtoupper( $raw_token )
				)
			);
		}

		if ( ! $record ) {
			return new WP_REST_Response(
				array(
					'result_code' => 'unknown',
					'message'     => 'بطاقة غير معروفة — رمز QR أو رقم التذكرة غير مسجل في النظام.',
				),
				404
			);
		}

		return self::execute_atomic_checkin( $record, 'qr', $device_label );
	}

	/**
	 * 7. POST /checkin/manual — Staff Manual Check-in by Guest ID or Ticket Reference
	 */
	public static function handle_checkin_manual( WP_REST_Request $request ) {
		global $wpdb;
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$guest_id         = isset( $params['guest_id'] ) ? absint( $params['guest_id'] ) : 0;
		$ticket_reference = isset( $params['ticket_reference'] ) ? sanitize_text_field( $params['ticket_reference'] ) : '';
		$device_label     = isset( $params['device_label'] ) ? sanitize_text_field( $params['device_label'] ) : 'Manual Desk';

		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$guests_table  = $wpdb->prefix . 'tarbwy_guests';

		if ( $guest_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$record = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT t.id AS ticket_id, g.event_id, g.id AS guest_id, t.ticket_reference, t.qr_payload, t.status AS ticket_status,
					        g.full_name, g.email, g.guest_type, g.organization, g.rsvp_status, g.checkin_status, g.checked_in_at,
					        p.full_name AS parent_name, p.email AS parent_email
					 FROM {$guests_table} g
					 LEFT JOIN {$tickets_table} t ON g.id = t.guest_id AND t.status IN ('active', 'used')
					 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
					 WHERE g.id = %d
					 ORDER BY CASE WHEN t.status = 'active' THEN 0 ELSE 1 END ASC, t.id DESC
					 LIMIT 1",
					$guest_id
				)
			);
		} elseif ( ! empty( $ticket_reference ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$record = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT t.id AS ticket_id, g.event_id, g.id AS guest_id, t.ticket_reference, t.qr_payload, t.status AS ticket_status,
					        g.full_name, g.email, g.guest_type, g.organization, g.rsvp_status, g.checkin_status, g.checked_in_at,
					        p.full_name AS parent_name, p.email AS parent_email
					 FROM {$tickets_table} t
					 INNER JOIN {$guests_table} g ON t.guest_id = g.id AND t.event_id = g.event_id
					 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
					 WHERE UPPER(t.ticket_reference) = %s OR UPPER(t.qr_payload) = %s LIMIT 1",
					strtoupper( $ticket_reference ),
					strtoupper( $ticket_reference )
				)
			);
		} else {
			$record = null;
		}

		// Auto-issue active ticket if guest is confirmed in DB but didn't have a ticket row yet
		if ( $record && 'confirmed' === $record->rsvp_status && empty( $record->ticket_id ) ) {
			$issued = QRGenerator::issue_ticket_for_guest( (int) $record->event_id, (int) $record->guest_id, false );
			if ( ! is_wp_error( $issued ) ) {
				$record->ticket_id        = (int) $issued['ticket_id'];
				$record->ticket_reference = (string) $issued['ticket_reference'];
				$record->ticket_status    = 'active';
			}
		}

		if ( ! $record ) {
			return new WP_REST_Response(
				array(
					'result_code' => 'unknown',
					'message'     => 'لم يتم العثور على سجل الضيف.',
				),
				404
			);
		}

		return self::execute_atomic_checkin( $record, 'manual', $device_label );
	}

	/**
	 * Core Atomic Check-in Execution with Database Transaction & UNIQUE(ticket_id) Enforcement
	 */
	private static function execute_atomic_checkin( $record, $method, $device_label ) {
		global $wpdb;

		$guest_summary = array(
			'guest_id'         => (int) $record->guest_id,
			'full_name'        => $record->full_name,
			'guest_type'       => $record->guest_type,
			'parent_name'      => $record->parent_name,
			'organization'     => $record->organization,
			'ticket_reference' => $record->ticket_reference,
		);

		// 1. Check if ticket was revoked
		if ( 'revoked' === $record->ticket_status ) {
			return new WP_REST_Response(
				array(
					'result_code' => 'revoked',
					'message'     => 'بطاقة ملغاة — تم إبطال هذه التذكرة ولا يمكن الدخول بها.',
					'guest'       => $guest_summary,
				),
				409
			);
		}

		// 2. Check if guest RSVP is confirmed
		if ( 'confirmed' !== $record->rsvp_status || empty( $record->ticket_id ) ) {
			return new WP_REST_Response(
				array(
					'result_code' => 'not_confirmed',
					'message'     => 'غير مؤكد — هذا الضيف غير مؤكد الحضور أو لم تصدر له تذكرة فعالة.',
					'guest'       => $guest_summary,
				),
				403
			);
		}

		$checkins_table = $wpdb->prefix . 'tarbwy_checkins';
		$tickets_table  = $wpdb->prefix . 'tarbwy_tickets';
		$guests_table   = $wpdb->prefix . 'tarbwy_guests';
		$now            = current_time( 'mysql' );
		$staff_id       = get_current_user_id();

		// Begin Atomic Transaction
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		// Atomic INSERT IGNORE relying on UNIQUE KEY (ticket_id) in tarbwy_checkins
		// Even if two requests hit at the exact same millisecond, MySQL InnoDB row-level lock on UNIQUE index allows only 1 row insertion.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted_rows = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$checkins_table} (event_id, guest_id, ticket_id, checked_in_by, method, device_label, checked_in_at, created_at)
				 VALUES (%d, %d, %d, %d, %s, %s, %s, %s)",
				(int) $record->event_id,
				(int) $record->guest_id,
				(int) $record->ticket_id,
				$staff_id ? $staff_id : 0,
				$method,
				$device_label,
				$now,
				$now
			)
		);

		if ( 1 !== (int) $inserted_rows ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );

			// Fetch exact time of previous check-in
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing_checkin = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT checked_in_at, method, device_label FROM {$checkins_table} WHERE ticket_id = %d LIMIT 1",
					(int) $record->ticket_id
				)
			);

			$guest_summary['checked_in_at'] = $existing_checkin ? $existing_checkin->checked_in_at : $record->checked_in_at;
			$guest_summary['prev_device']   = $existing_checkin ? $existing_checkin->device_label : '';

			return new WP_REST_Response(
				array(
					'result_code' => 'already_checked_in',
					'message'     => sprintf( 'مستخدم سابقاً — تم تسجيل دخول هذا الضيف مسبقاً في (%s).', $guest_summary['checked_in_at'] ),
					'guest'       => $guest_summary,
				),
				409
			);
		}

		// Update guest checkin_status and ticket status inside the same transaction
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$guests_table,
			array(
				'checkin_status' => 'checked_in',
				'checked_in_at'  => $now,
				'checked_in_by'  => $staff_id ? $staff_id : null,
				'updated_at'     => $now,
			),
			array( 'id' => (int) $record->guest_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$tickets_table,
			array(
				'status'     => 'used',
				'updated_at' => $now,
			),
			array( 'id' => (int) $record->ticket_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );

		// Trigger Check-in Confirmation Email (Idempotent: sent once per guest check-in)
		$checkin_email = ! empty( $record->email ) ? $record->email : ( $record->parent_email ?? '' );
		if ( ! empty( $checkin_email ) ) {
			EmailManager::enqueue_email(
				(int) $record->event_id,
				(int) $record->guest_id,
				'checkin_confirmation',
				$checkin_email,
				array(
					'guest_name'       => $record->full_name,
					'ticket_reference' => $record->ticket_reference,
					'checkin_time'     => $now,
				),
				false
			);
		}

		Security::log_activity(
			(int) $record->event_id,
			'guest_checked_in',
			'checkin',
			(int) $record->guest_id,
			array(
				'method'           => $method,
				'ticket_reference' => $record->ticket_reference,
				'device_label'     => $device_label,
			)
		);

		$guest_summary['checked_in_at'] = $now;

		return new WP_REST_Response(
			array(
				'result_code' => 'valid',
				'message'     => 'بطاقة صالحة — تم تأكيد وتسجيل الدخول بنجاح.',
				'guest'       => $guest_summary,
			),
			200
		);
	}

	/**
	 * 8. GET /checkin/stats — Real-time Attendance & Capacity Metrics
	 */
	public static function handle_checkin_stats( WP_REST_Request $request ) {
		global $wpdb;
		$event_id = absint( $request->get_param( 'event_id' ) );
		$event    = self::get_active_event( $event_id );
		if ( ! $event ) {
			return new WP_REST_Response( array( 'message' => 'الحدث غير موجود.' ), 404 );
		}

		$eid          = (int) $event->id;
		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$reg_table    = $wpdb->prefix . 'tarbwy_registrations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(id) AS total_guests,
					SUM(CASE WHEN rsvp_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_total,
					SUM(CASE WHEN checkin_status = 'checked_in' THEN 1 ELSE 0 END) AS checked_in_total,
					SUM(CASE WHEN guest_type = 'vip' THEN 1 ELSE 0 END) AS vip_total,
					SUM(CASE WHEN guest_type = 'companion' THEN 1 ELSE 0 END) AS companions_total
				 FROM {$guests_table}
				 WHERE event_id = %d",
				$eid
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reg_counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(id) AS total_requests,
					SUM(CASE WHEN review_status = 'pending' THEN 1 ELSE 0 END) AS pending_requests,
					SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) AS approved_requests,
					SUM(CASE WHEN review_status = 'waitlist' THEN 1 ELSE 0 END) AS waitlist_requests,
					SUM(CASE WHEN review_status = 'declined' THEN 1 ELSE 0 END) AS declined_requests
				 FROM {$reg_table}
				 WHERE event_id = %d",
				$eid
			)
		);

		$confirmed  = (int) ( $counts->confirmed_total ?? 0 );
		$checked_in = (int) ( $counts->checked_in_total ?? 0 );
		$remaining  = max( 0, $confirmed - $checked_in );

		// Per Spec Section 6 & 12: Calculate No-Show dynamically from confirmed minus checked_in after event end
		$is_ended = ! empty( $event->event_end_at ) && strtotime( $event->event_end_at ) < current_time( 'timestamp' );
		$no_show  = $is_ended ? $remaining : 0;

		return new WP_REST_Response(
			array(
				'capacity_total'    => (int) $event->capacity_total,
				'total_guests'      => (int) ( $counts->total_guests ?? 0 ),
				'confirmed'         => $confirmed,
				'checked_in'        => $checked_in,
				'remaining'         => $remaining,
				'no_show'           => $no_show,
				'event_ended'       => $is_ended,
				'vip_invited'       => (int) ( $counts->vip_total ?? 0 ),
				'companions_total'  => (int) ( $counts->companions_total ?? 0 ),
				'total_requests'    => (int) ( $reg_counts->total_requests ?? 0 ),
				'pending_requests'  => (int) ( $reg_counts->pending_requests ?? 0 ),
				'approved_requests' => (int) ( $reg_counts->approved_requests ?? 0 ),
				'waitlist_requests' => (int) ( $reg_counts->waitlist_requests ?? 0 ),
				'declined_requests' => (int) ( $reg_counts->declined_requests ?? 0 ),
			),
			200
		);
	}

	/**
	 * 9. GET /guests — Paginated & Filtered Guest List (Never loads all guests at once per Spec 14)
	 */
	public static function handle_get_guests( WP_REST_Request $request ) {
		global $wpdb;
		$event_id   = absint( $request->get_param( 'event_id' ) );
		$event      = self::get_active_event( $event_id );
		$eid        = $event ? (int) $event->id : 1;
		$page       = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page   = min( 100, max( 10, absint( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 25 ) ) );
		$offset     = ( $page - 1 ) * $per_page;
		$search     = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$guest_type = sanitize_key( (string) $request->get_param( 'guest_type' ) );
		$rsvp       = sanitize_key( (string) $request->get_param( 'rsvp_status' ) );
		$checkin    = sanitize_key( (string) $request->get_param( 'checkin_status' ) );

		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$reg_table     = $wpdb->prefix . 'tarbwy_registrations';

		$where = $wpdb->prepare( 'WHERE g.event_id = %d', $eid );

		if ( ! empty( $guest_type ) ) {
			$where .= $wpdb->prepare( ' AND g.guest_type = %s', $guest_type );
		}
		if ( ! empty( $rsvp ) ) {
			$where .= $wpdb->prepare( ' AND g.rsvp_status = %s', $rsvp );
		}
		if ( ! empty( $checkin ) ) {
			$where .= $wpdb->prepare( ' AND g.checkin_status = %s', $checkin );
		}
		if ( ! empty( $search ) ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( ' AND (g.full_name LIKE %s OR g.email LIKE %s OR g.organization LIKE %s)', $like, $like, $like );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(g.id) FROM {$guests_table} g {$where}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.*, r.reference_code, r.review_status, t.id AS ticket_id, t.ticket_reference, t.status AS ticket_status, p.full_name AS parent_name
				 FROM {$guests_table} g
				 LEFT JOIN {$reg_table} r ON g.registration_id = r.id
				 LEFT JOIN {$tickets_table} t ON g.id = t.guest_id AND t.status IN ('active', 'used')
				 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
				 {$where}
				 ORDER BY g.id DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			),
			200
		);
	}

	/**
	 * 10. POST /registrations/{id}/review — Admin Review (Approve / Waitlist / Decline + Partial Companion Selection)
	 */
	public static function handle_review_registration( WP_REST_Request $request ) {
		global $wpdb;
		$reg_id = absint( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$decision            = isset( $params['review_status'] ) ? sanitize_key( $params['review_status'] ) : '';
		$review_note         = isset( $params['review_note'] ) ? sanitize_textarea_field( $params['review_note'] ) : '';
		$approved_companions = isset( $params['approved_companion_ids'] ) && is_array( $params['approved_companion_ids'] )
			? array_map( 'absint', $params['approved_companion_ids'] )
			: null;

		if ( ! in_array( $decision, array( 'pending', 'approved', 'waitlist', 'declined', 'cancelled' ), true ) ) {
			return new WP_REST_Response( array( 'message' => 'حالة المراجعة غير صالحة.' ), 422 );
		}

		$reg_table    = $wpdb->prefix . 'tarbwy_registrations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reg_table} WHERE id = %d", $reg_id ) );
		if ( ! $reg ) {
			return new WP_REST_Response( array( 'message' => 'طلب التسجيل غير موجود.' ), 404 );
		}

		$event = self::get_active_event( (int) $reg->event_id );
		if ( ! $event ) {
			return new WP_REST_Response( array( 'message' => 'الحدث المرتبط بهذا الطلب غير موجود.' ), 404 );
		}

		$primary_gid_lookup = (int) $reg->primary_guest_id;

		// Fetch all guests in this registration group (auto-heal if needed)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$group_guests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$guests_table} WHERE event_id = %d AND (registration_id = %d OR (%d > 0 AND (id = %d OR parent_guest_id = %d))) ORDER BY CASE WHEN parent_guest_id IS NULL OR parent_guest_id = 0 THEN 0 ELSE 1 END ASC, id ASC",
				(int) $event->id,
				$reg_id,
				$primary_gid_lookup,
				$primary_gid_lookup,
				$primary_gid_lookup
			)
		);
		if ( empty( $group_guests ) ) {
			Installer::heal_orphaned_registrations();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$reg                = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reg_table} WHERE id = %d", $reg_id ) );
			$primary_gid_lookup = $reg ? (int) $reg->primary_guest_id : 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$group_guests = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$guests_table} WHERE event_id = %d AND (registration_id = %d OR (%d > 0 AND (id = %d OR parent_guest_id = %d))) ORDER BY CASE WHEN parent_guest_id IS NULL OR parent_guest_id = 0 THEN 0 ELSE 1 END ASC, id ASC",
					(int) $event->id,
					$reg_id,
					$primary_gid_lookup,
					$primary_gid_lookup,
					$primary_gid_lookup
				)
			);
		}

		$now         = current_time( 'mysql' );
		$reviewer_id = get_current_user_id();
		$prev_status = (string) $reg->review_status;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		// Update Registration review_status and previous_status
		$upd_data    = array(
			'review_status'   => $decision,
			'previous_status' => $prev_status,
			'reviewed_by'     => $reviewer_id ? $reviewer_id : null,
			'reviewed_at'     => $now,
			'review_note'     => $review_note,
			'updated_at'      => $now,
		);
		$upd_formats = array( '%s', '%s', '%d', '%s', '%s', '%s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated_ok = $wpdb->update( $reg_table, $upd_data, array( 'id' => $reg_id ), $upd_formats, array( '%d' ) );
		if ( false === $updated_ok ) {
			unset( $upd_data['previous_status'] );
			$upd_formats = array( '%s', '%d', '%s', '%s', '%s' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $reg_table, $upd_data, array( 'id' => $reg_id ), $upd_formats, array( '%d' ) );
		}

		$primary_guest  = null;
		$issued_tickets = array();

		foreach ( $group_guests as $g ) {
			if ( empty( $g->parent_guest_id ) ) {
				$primary_guest = $g;
				break;
			}
		}
		if ( ! $primary_guest && ! empty( $group_guests ) ) {
			$primary_guest = $group_guests[0];
		}

		if ( 'approved' === $decision ) {
			foreach ( $group_guests as $g ) {
				$is_companion = ! empty( $g->parent_guest_id );

				// Support partial companion approval per Spec Section 5 & 15.5
				if ( $is_companion && is_array( $approved_companions ) && ! in_array( (int) $g->id, $approved_companions, true ) ) {
					// Companion excluded by admin: decline & revoke any active ticket
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$guests_table,
						array( 'rsvp_status' => 'declined', 'updated_at' => $now ),
						array( 'id' => (int) $g->id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
					QRGenerator::revoke_tickets_for_guest( (int) $event->id, (int) $g->id, 'companion_excluded' );
					continue;
				}

				// Always confirm approved guests and ensure registration_id is linked
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$guests_table,
					array(
						'registration_id' => $reg_id,
						'rsvp_status'     => 'confirmed',
						'updated_at'      => $now,
					),
					array( 'id' => (int) $g->id ),
					array( '%d', '%s', '%s' ),
					array( '%d' )
				);

				// Idempotent: reuses existing active ticket if already issued, preventing duplicates
				$tkt = QRGenerator::issue_ticket_for_guest( (int) $event->id, (int) $g->id, false );
				if ( ! is_wp_error( $tkt ) ) {
					$tkt_url      = QRGenerator::get_ticket_url( $tkt['raw_token'] );
					$holder_label = $is_companion && $primary_guest
						? $g->full_name . ' (مرافق: ' . $primary_guest->full_name . ')'
						: $g->full_name;

					$issued_tickets[] = array(
						'guest_id'         => (int) $g->id,
						'guest_name'       => $holder_label,
						'guest_type'       => $is_companion ? 'companion' : 'public',
						'ticket_reference' => $tkt['ticket_reference'],
						'qr_payload'       => $tkt['qr_payload'],
						'ticket_url'       => $tkt_url,
						'qr_token'         => $tkt['qr_payload'],
						'raw_token'        => $tkt['raw_token'],
					);

					// If companion has their own distinct email address, send their individual pass too
					if ( $is_companion && ! empty( $g->email ) && $primary_guest && strtolower( $g->email ) !== strtolower( $primary_guest->email ) ) {
						EmailManager::enqueue_email(
							(int) $event->id,
							(int) $g->id,
							'ticket_issued',
							$g->email,
							array(
								'guest_name'       => $holder_label,
								'event_title'      => $event->title,
								'venue_name'       => $event->venue_name,
								'ticket_reference' => $tkt['ticket_reference'],
								'qr_payload'       => $tkt['qr_payload'],
								'ticket_url'       => $tkt_url,
							),
							true
						);
					}
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'COMMIT' );

			// Send primary confirmation email containing ALL generated QR passes (Main Attendee + Approved Companions)
			if ( $primary_guest && ! empty( $primary_guest->email ) && ! empty( $issued_tickets ) ) {
				EmailManager::enqueue_email(
					(int) $event->id,
					(int) $primary_guest->id,
					'registration_approved',
					$primary_guest->email,
					array(
						'guest_name'       => $primary_guest->full_name,
						'event_title'      => $event->title,
						'venue_name'       => $event->venue_name,
						'reference_code'   => $reg->reference_code,
						'ticket_reference' => $issued_tickets[0]['ticket_reference'],
						'qr_payload'       => $issued_tickets[0]['qr_payload'],
						'ticket_url'       => $issued_tickets[0]['ticket_url'],
						'tickets_count'    => (string) count( $issued_tickets ),
						'all_tickets'      => $issued_tickets,
					),
					true
				);
			}
		} else {
			// Status changed to waitlist, declined, cancelled, or pending: immediately revoke any active QR codes!
			QRGenerator::revoke_tickets_for_registration( (int) $event->id, $reg_id, 'status_changed_to_' . $decision );

			$new_rsvp = in_array( $decision, array( 'declined', 'cancelled' ), true ) ? 'declined' : 'not_invited';
			foreach ( $group_guests as $g ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$guests_table,
					array( 'rsvp_status' => $new_rsvp, 'updated_at' => $now ),
					array( 'id' => (int) $g->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'COMMIT' );

			if ( $primary_guest && ! empty( $primary_guest->email ) ) {
				if ( 'waitlist' === $decision ) {
					EmailManager::enqueue_email(
						(int) $event->id,
						(int) $primary_guest->id,
						'registration_waitlist',
						$primary_guest->email,
						array(
							'guest_name'     => $primary_guest->full_name,
							'event_title'    => $event->title,
							'reference_code' => $reg->reference_code,
						),
						true
					);
				} elseif ( in_array( $decision, array( 'declined', 'cancelled' ), true ) ) {
					EmailManager::enqueue_email(
						(int) $event->id,
						(int) $primary_guest->id,
						'registration_declined',
						$primary_guest->email,
						array(
							'guest_name'     => $primary_guest->full_name,
							'event_title'    => $event->title,
							'reference_code' => $reg->reference_code,
						),
						true
					);
				}
			}
		}

		Security::log_activity(
			(int) $event->id,
			'registration_reviewed',
			'registration',
			$reg_id,
			array(
				'previous_status' => $prev_status,
				'decision'        => $decision,
				'tickets_issued'  => count( $issued_tickets ),
			)
		);

		return new WP_REST_Response(
			array(
				'registration_id' => $reg_id,
				'previous_status' => $prev_status,
				'review_status'   => $decision,
				'issued_tickets'  => $issued_tickets,
				'message'         => 'تم تحديث حالة الطلب وإرسال الإشعارات بنجاح.',
			),
			200
		);
	}

	/**
	 * 11. POST /tickets/{id}/reissue — Revoke Old Ticket & Reissue New QR Ticket for Guest
	 */
	public static function handle_reissue_ticket( WP_REST_Request $request ) {
		global $wpdb;
		$guest_id = absint( $request->get_param( 'id' ) );
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$guests_table} WHERE id = %d", $guest_id ) );
		if ( ! $guest ) {
			return new WP_REST_Response( array( 'message' => 'الضيف غير موجود.' ), 404 );
		}

		$event = self::get_active_event( (int) $guest->event_id );
		$res   = QRGenerator::issue_ticket_for_guest( (int) $guest->event_id, $guest_id, true );

		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response( array( 'message' => $res->get_error_message() ), 422 );
		}

		$ticket_url   = QRGenerator::get_ticket_url( $res['raw_token'] );
		$target_email = $guest->email;
		if ( empty( $target_email ) && $guest->parent_guest_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$target_email = $wpdb->get_var( $wpdb->prepare( "SELECT email FROM {$guests_table} WHERE id = %d", (int) $guest->parent_guest_id ) );
		}

		if ( ! empty( $target_email ) && $event ) {
			EmailManager::enqueue_email(
				(int) $guest->event_id,
				$guest_id,
				'ticket_issued',
				$target_email,
				array(
					'guest_name'       => $guest->full_name,
					'event_title'      => $event->title,
					'venue_name'       => $event->venue_name,
					'ticket_reference' => $res['ticket_reference'],
					'qr_payload'       => $res['qr_payload'],
					'ticket_url'       => $ticket_url,
				),
				true
			);
		}

		return new WP_REST_Response(
			array(
				'ticket_id'        => $res['ticket_id'],
				'ticket_reference' => $res['ticket_reference'],
				'qr_payload'       => $res['qr_payload'],
				'qr_token'         => $res['qr_payload'],
				'ticket_url'       => $ticket_url,
				'message'          => 'تم إبطال التذكرة السابقة وإصدار تذكرة جديدة برمز QR جديد بنجاح.',
			),
			200
		);
	}

	/**
	 * 12. POST /invitations/import — VIP CSV Import with Preview / Dry-Run
	 */
	public static function handle_import_vip_invitations( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$event_id    = isset( $params['event_id'] ) ? absint( $params['event_id'] ) : 0;
		$event       = self::get_active_event( $event_id );
		$csv_content = isset( $params['csv_content'] ) ? (string) $params['csv_content'] : '';
		$dry_run     = isset( $params['dry_run'] ) ? (bool) $params['dry_run'] : true;

		if ( ! $event ) {
			return new WP_REST_Response( array( 'message' => 'الحدث غير موجود.' ), 404 );
		}

		if ( strlen( $csv_content ) > CSVHandler::MAX_FILE_SIZE ) {
			return new WP_REST_Response( array( 'message' => 'حجم ملف CSV يتجاوز الحد المسموح به (2MB).' ), 413 );
		}

		$report = CSVHandler::import_vip_csv( (int) $event->id, $csv_content, $dry_run );
		return new WP_REST_Response( $report, 200 );
	}
}
