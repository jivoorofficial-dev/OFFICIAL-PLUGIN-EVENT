<?php
/**
 * Comprehensive Reporting, Real-Time Statistics, Filtering, VIP CSV Import (Preview & Dry-Run),
 * and Multi-Format Report Exporter (CSV UTF-8 BOM, Excel .xlsx, PDF, and Print-Friendly Views).
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSVHandler {

	const MAX_FILE_SIZE = 2097152; // 2 MB max upload

	/**
	 * Parse and validate VIP CSV rows (Supports dry_run preview before actual DB insertion)
	 *
	 * @param int    $event_id    Event ID.
	 * @param string $csv_content Raw CSV string content.
	 * @param bool   $dry_run     If true, only validates and returns preview report without DB insert.
	 * @return array
	 */
	public static function import_vip_csv( $event_id, $csv_content, $dry_run = true ) {
		global $wpdb;

		// Strip UTF-8 BOM if present
		$csv_content = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv_content );
		$lines       = preg_split( '/\r\n|\r|\n/', trim( $csv_content ) );

		$report = array(
			'dry_run'      => (bool) $dry_run,
			'total_rows'   => 0,
			'valid_rows'   => 0,
			'invalid_rows' => 0,
			'warnings'     => array(),
			'errors'       => array(),
			'preview'      => array(),
			'imported_ids' => array(),
		);

		if ( empty( $lines ) || count( $lines ) < 2 ) {
			$report['errors'][] = 'ملف CSV فارغ أو لا يحتوي على صفوف بيانات بعد الترويسة.';
			return $report;
		}

		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map(
			function ( $col ) {
				return strtolower( trim( (string) $col ) );
			},
			$header
		);

		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';

		foreach ( $lines as $idx => $line ) {
			$row_num = $idx + 2;
			if ( '' === trim( $line ) ) {
				continue;
			}

			++$report['total_rows'];
			$cols = str_getcsv( $line );
			$row  = array();
			foreach ( $header as $i => $key ) {
				$row[ $key ] = isset( $cols[ $i ] ) ? trim( (string) $cols[ $i ] ) : '';
			}

			$full_name      = isset( $row['full_name'] ) ? sanitize_text_field( $row['full_name'] ) : ( isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '' );
			$email          = isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '';
			$phone          = isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '';
			$organization   = isset( $row['organization'] ) ? sanitize_text_field( $row['organization'] ) : '';
			$job_title      = isset( $row['job_title'] ) ? sanitize_text_field( $row['job_title'] ) : '';
			$max_companions = isset( $row['max_companions'] ) && '' !== $row['max_companions'] ? absint( $row['max_companions'] ) : 2;

			$row_errors   = array();
			$row_warnings = array();

			if ( empty( $full_name ) ) {
				$row_errors[] = "الصف {$row_num}: الاسم الكامل مطلوب.";
			}
			if ( empty( $email ) || ! is_email( $email ) ) {
				$row_errors[] = "الصف {$row_num}: البريد الإلكتروني غير صالح أو مفقود.";
			}

			if ( ! empty( $email ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$dup = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$guests_table} WHERE event_id = %d AND email = %s LIMIT 1",
						absint( $event_id ),
						$email
					)
				);
				if ( $dup ) {
					$row_warnings[] = "الصف {$row_num}: تنبيه تكرار — البريد الإلكتروني ({$email}) مسجل مسبقاً لضيف آخر في نفس الحدث.";
				}
			}

			if ( ! empty( $row_errors ) ) {
				++$report['invalid_rows'];
				$report['errors'] = array_merge( $report['errors'], $row_errors );
				continue;
			}

			++$report['valid_rows'];
			if ( ! empty( $row_warnings ) ) {
				$report['warnings'] = array_merge( $report['warnings'], $row_warnings );
			}

			$item_preview = array(
				'row_number'     => $row_num,
				'full_name'      => $full_name,
				'email'          => $email,
				'phone'          => $phone,
				'organization'   => $organization,
				'job_title'      => $job_title,
				'max_companions' => $max_companions,
				'warnings'       => $row_warnings,
			);

			if ( ! $dry_run ) {
				$now      = current_time( 'mysql' );
				$guest_id = Installer::safe_insert_guest(
					array(
						'event_id'       => absint( $event_id ),
						'guest_type'     => 'vip',
						'full_name'      => $full_name,
						'email'          => $email,
						'phone'          => $phone,
						'organization'   => $organization,
						'job_title'      => $job_title,
						'rsvp_status'    => 'pending_response',
						'checkin_status' => 'not_checked_in',
						'created_at'     => $now,
						'updated_at'     => $now,
					)
				);

				if ( $guest_id > 0 ) {
					$token_pair  = Security::generate_token_pair( 24 );
					$inv_payload = array(
						'event_id'                => absint( $event_id ),
						'guest_id'                => $guest_id,
						'token_hash'              => $token_pair['hash'],
						'token_preview'           => $token_pair['raw'],
						'max_companions_override' => $max_companions,
						'status'                  => 'active',
						'created_by'              => get_current_user_id(),
						'created_at'              => $now,
						'updated_at'              => $now,
					);
					$inv_id      = Installer::safe_insert_invitation( $inv_payload );
					if ( $inv_id > 0 ) {
						set_transient( 'tarbwy_vip_raw_' . $inv_id, $token_pair['raw'], DAY_IN_SECONDS * 30 );
					}
					$report['imported_ids'][] = $guest_id;
				}
			}

			$report['preview'][] = $item_preview;
		}

		if ( ! $dry_run && ! empty( $report['imported_ids'] ) ) {
			Security::log_activity(
				$event_id,
				'vip_csv_imported',
				'invitation',
				0,
				array(
					'imported_count' => count( $report['imported_ids'] ),
				)
			);
		}

		return $report;
	}

	/**
	 * Calculate comprehensive real-time report statistics distinguishing:
	 * - Registration form submissions (All, Approved, Rejected/Declined, Waitlisted, Pending, plus VIP invitations)
	 * - Total individual attendees including companions
	 * - Total valid QR codes generated
	 *
	 * @param int $event_id Event ID.
	 * @return array<string, int>
	 */
	public static function get_report_statistics( $event_id ) {
		global $wpdb;
		$reg_table    = $wpdb->prefix . 'tarbwy_registrations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$tkt_table    = $wpdb->prefix . 'tarbwy_tickets';
		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';

		// Public registration submissions by review_status
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reg_counts_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT review_status, COUNT(*) AS cnt FROM {$reg_table} WHERE event_id = %d GROUP BY review_status",
				absint( $event_id )
			)
		);
		$reg_by_status = array(
			'pending'   => 0,
			'approved'  => 0,
			'waitlist'  => 0,
			'declined'  => 0,
			'cancelled' => 0,
		);
		if ( is_array( $reg_counts_raw ) ) {
			foreach ( $reg_counts_raw as $rc ) {
				$st = (string) $rc->review_status;
				if ( isset( $reg_by_status[ $st ] ) ) {
					$reg_by_status[ $st ] = (int) $rc->cnt;
				}
			}
		}

		// VIP primary guests by rsvp_status
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$vip_counts_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rsvp_status, COUNT(*) AS cnt FROM {$guests_table} WHERE event_id = %d AND guest_type = 'vip' GROUP BY rsvp_status",
				absint( $event_id )
			)
		);
		$vip_total     = 0;
		$vip_confirmed = 0;
		$vip_declined  = 0;
		$vip_pending   = 0;
		if ( is_array( $vip_counts_raw ) ) {
			foreach ( $vip_counts_raw as $vc ) {
				$cnt        = (int) $vc->cnt;
				$vip_total += $cnt;
				if ( 'confirmed' === $vc->rsvp_status ) {
					$vip_confirmed += $cnt;
				} elseif ( 'declined' === $vc->rsvp_status ) {
					$vip_declined += $cnt;
				} else {
					$vip_pending += $cnt;
				}
			}
		}

		// Total primary submissions (Public registrations + VIP primary records)
		$public_submissions = array_sum( $reg_by_status );
		$all_submissions    = $public_submissions + $vip_total;

		// Combined category counts (Public + VIP)
		$approved_submissions = $reg_by_status['approved'] + $vip_confirmed;
		$rejected_submissions = $reg_by_status['declined'] + $reg_by_status['cancelled'] + $vip_declined;
		$waitlist_submissions = $reg_by_status['waitlist'];
		$pending_submissions  = $reg_by_status['pending'] + $vip_pending;

		// Individual attendees (Main + Companions)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_individuals = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE event_id = %d",
				absint( $event_id )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_companions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE event_id = %d AND guest_type = 'companion'",
				absint( $event_id )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$confirmed_individuals = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE event_id = %d AND rsvp_status = 'confirmed'",
				absint( $event_id )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$checked_in_individuals = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$guests_table} WHERE event_id = %d AND checkin_status = 'checked_in'",
				absint( $event_id )
			)
		);

		// Valid QR codes (active or used)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$valid_qr_codes = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tkt_table} WHERE event_id = %d AND status IN ('active', 'used')",
				absint( $event_id )
			)
		);

		// Revoked QR codes
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$revoked_qr_codes = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tkt_table} WHERE event_id = %d AND status = 'revoked'",
				absint( $event_id )
			)
		);

		// Active VIP invitations
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_vip_invitations = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$inv_table} WHERE event_id = %d AND status IN ('active', 'used')",
				absint( $event_id )
			)
		);

		return array(
			'all_submissions'        => $all_submissions,
			'public_submissions'     => $public_submissions,
			'vip_submissions'        => $vip_total,
			'approved_submissions'   => $approved_submissions,
			'rejected_submissions'   => $rejected_submissions,
			'waitlist_submissions'   => $waitlist_submissions,
			'pending_submissions'    => $pending_submissions,
			'public_approved'        => $reg_by_status['approved'],
			'public_rejected'        => $reg_by_status['declined'] + $reg_by_status['cancelled'],
			'public_waitlist'        => $reg_by_status['waitlist'],
			'public_pending'         => $reg_by_status['pending'],
			'vip_confirmed'          => $vip_confirmed,
			'vip_declined'           => $vip_declined,
			'vip_pending'            => $vip_pending,
			'active_vip_invitations' => $active_vip_invitations,
			'total_individuals'      => $total_individuals,
			'total_companions'       => $total_companions,
			'confirmed_individuals'  => $confirmed_individuals,
			'checked_in_individuals' => $checked_in_individuals,
			'valid_qr_codes'         => $valid_qr_codes,
			'revoked_qr_codes'       => $revoked_qr_codes,
		);
	}

	/**
	 * Query unified registrations report (Public Registrations + VIP Registrations) with full filtering:
	 * - status: all | approved | declined (or rejected) | waitlist | pending
	 * - reg_type: all | standard (or public) | vip
	 * - date_from: YYYY-MM-DD
	 * - date_to: YYYY-MM-DD
	 * - search: name, email, phone, reference_code
	 *
	 * @param int   $event_id Event ID.
	 * @param array $filters  Filter arguments.
	 * @return array List of normalized report rows.
	 */
	public static function get_filtered_registrations_report( $event_id, $filters = array() ) {
		global $wpdb;
		$reg_table    = $wpdb->prefix . 'tarbwy_registrations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$tkt_table    = $wpdb->prefix . 'tarbwy_tickets';
		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';

		$status_filter = isset( $filters['status'] ) ? strtolower( trim( (string) $filters['status'] ) ) : 'all';
		if ( 'rejected' === $status_filter ) {
			$status_filter = 'declined';
		}
		$type_filter = isset( $filters['reg_type'] ) ? strtolower( trim( (string) $filters['reg_type'] ) ) : 'all';
		if ( 'public' === $type_filter ) {
			$type_filter = 'standard';
		}
		$date_from = isset( $filters['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'] ) ? $filters['date_from'] : '';
		$date_to   = isset( $filters['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'] ) ? $filters['date_to'] : '';
		$search    = isset( $filters['search'] ) ? trim( sanitize_text_field( $filters['search'] ) ) : '';

		// Fetch all primary guests (public primary guests + VIP primary guests)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$primary_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.*,
				        r.id AS reg_db_id,
				        r.reference_code,
				        r.review_status,
				        r.submitted_at,
				        r.review_note,
				        i.id AS invitation_id,
				        i.status AS invitation_status
				 FROM {$guests_table} g
				 LEFT JOIN {$reg_table} r ON g.registration_id = r.id
				 LEFT JOIN {$inv_table} i ON i.guest_id = g.id AND i.event_id = g.event_id
				 WHERE g.event_id = %d AND g.guest_type IN ('public', 'vip')
				 ORDER BY g.created_at DESC, g.id DESC",
				absint( $event_id )
			)
		);

		if ( empty( $primary_rows ) ) {
			return array();
		}

		// Preload companion counts & names per parent_guest_id
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$companions_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, parent_guest_id, registration_id, full_name, rsvp_status FROM {$guests_table} WHERE event_id = %d AND guest_type = 'companion' ORDER BY id ASC",
				absint( $event_id )
			)
		);
		$companions_by_parent = array();
		if ( is_array( $companions_raw ) ) {
			foreach ( $companions_raw as $comp ) {
				$pid = (int) $comp->parent_guest_id;
				if ( ! isset( $companions_by_parent[ $pid ] ) ) {
					$companions_by_parent[ $pid ] = array();
				}
				$companions_by_parent[ $pid ][] = $comp;
			}
		}

		// Preload tickets per guest_id
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tickets_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT guest_id, ticket_reference, status FROM {$tkt_table} WHERE event_id = %d ORDER BY id DESC",
				absint( $event_id )
			)
		);
		$active_tickets_by_guest  = array();
		$revoked_tickets_by_guest = array();
		if ( is_array( $tickets_raw ) ) {
			foreach ( $tickets_raw as $t ) {
				$gid = (int) $t->guest_id;
				if ( in_array( $t->status, array( 'active', 'used' ), true ) ) {
					$active_tickets_by_guest[ $gid ] = $t->ticket_reference;
				} elseif ( 'revoked' === $t->status ) {
					$revoked_tickets_by_guest[ $gid ] = $t->ticket_reference;
				}
			}
		}

		$results      = array();
		$seen_guests  = array();

		foreach ( $primary_rows as $row ) {
			$gid = (int) $row->id;
			if ( isset( $seen_guests[ $gid ] ) ) {
				continue;
			}
			$seen_guests[ $gid ] = true;

			$is_vip   = ( 'vip' === $row->guest_type );
			$reg_type = $is_vip ? 'vip' : 'standard';

			// Determine normalized registration status: approved | declined | waitlist | pending
			if ( $is_vip ) {
				if ( 'confirmed' === $row->rsvp_status ) {
					$norm_status = 'approved';
				} elseif ( 'declined' === $row->rsvp_status || 'revoked' === $row->invitation_status ) {
					$norm_status = 'declined';
				} else {
					$norm_status = 'pending';
				}
			} else {
				$rev = $row->review_status ? strtolower( (string) $row->review_status ) : 'pending';
				if ( 'cancelled' === $rev ) {
					$norm_status = 'declined';
				} elseif ( in_array( $rev, array( 'approved', 'declined', 'waitlist', 'pending' ), true ) ) {
					$norm_status = $rev;
				} else {
					$norm_status = 'pending';
				}
			}

			// Filter by status
			if ( 'all' !== $status_filter && $status_filter !== $norm_status ) {
				continue;
			}

			// Filter by registration type
			if ( 'all' !== $type_filter && $type_filter !== $reg_type ) {
				continue;
			}

			$reg_date = ! empty( $row->submitted_at ) ? (string) $row->submitted_at : (string) $row->created_at;
			$day_only = substr( $reg_date, 0, 10 );

			if ( '' !== $date_from && $day_only < $date_from ) {
				continue;
			}
			if ( '' !== $date_to && $day_only > $date_to ) {
				continue;
			}

			$ref_code = ! empty( $row->reference_code )
				? (string) $row->reference_code
				: ( $is_vip ? sprintf( 'VIP-%04d', (int) ( $row->invitation_id ? $row->invitation_id : $gid ) ) : sprintf( 'REG-%04d', $gid ) );

			// Search filter (name, email, phone, reference_code, organization)
			if ( '' !== $search ) {
				$haystack = mb_strtolower(
					implode(
						' ',
						array(
							(string) $row->full_name,
							(string) $row->email,
							(string) $row->phone,
							$ref_code,
							(string) $row->organization,
						)
					),
					'UTF-8'
				);
				$needle = mb_strtolower( $search, 'UTF-8' );
				if ( false === mb_strpos( $haystack, $needle, 0, 'UTF-8' ) ) {
					continue;
				}
			}

			// Companion details & QR count across primary + companions
			$comps      = isset( $companions_by_parent[ $gid ] ) ? $companions_by_parent[ $gid ] : array();
			$comp_count = count( $comps );
			$comp_names = array();
			$qr_count   = isset( $active_tickets_by_guest[ $gid ] ) ? 1 : 0;
			$rev_count  = isset( $revoked_tickets_by_guest[ $gid ] ) ? 1 : 0;

			foreach ( $comps as $c ) {
				$comp_names[] = $c->full_name;
				if ( isset( $active_tickets_by_guest[ (int) $c->id ] ) ) {
					++$qr_count;
				} elseif ( isset( $revoked_tickets_by_guest[ (int) $c->id ] ) ) {
					++$rev_count;
				}
			}

			$total_party_size = 1 + $comp_count;
			if ( $qr_count > 0 ) {
				$qr_status_label = sprintf( 'تم الإصدار (%d / %d QR)', $qr_count, $total_party_size );
				$qr_status_code  = 'generated';
			} elseif ( $rev_count > 0 ) {
				$qr_status_label = 'ملغى (Revoked)';
				$qr_status_code  = 'revoked';
			} else {
				$qr_status_label = 'غير مصدر (Not Generated)';
				$qr_status_code  = 'not_generated';
			}

			$status_labels = array(
				'approved' => 'معتمد (Approved)',
				'declined' => 'مرفوض (Rejected)',
				'waitlist' => 'قائمة انتظار (Waitlist)',
				'pending'  => 'قيد المراجعة (Pending)',
			);

			$results[] = array(
				'registration_id'     => $ref_code,
				'reg_db_id'           => (int) $row->reg_db_id,
				'guest_id'            => $gid,
				'full_name'           => (string) $row->full_name,
				'email'               => (string) $row->email,
				'phone'               => (string) ( $row->phone ? $row->phone : '-' ),
				'organization'        => (string) ( $row->organization ? $row->organization : '-' ),
				'job_title'           => (string) ( $row->job_title ? $row->job_title : '-' ),
				'registration_type'   => $is_vip ? 'VIP' : 'Standard',
				'companions_count'    => $comp_count,
				'companions_names'    => ! empty( $comp_names ) ? implode( ' ، ', $comp_names ) : '-',
				'total_attendees'     => $total_party_size,
				'registration_status' => $norm_status,
				'status_label'        => isset( $status_labels[ $norm_status ] ) ? $status_labels[ $norm_status ] : $norm_status,
				'registration_date'   => $reg_date,
				'qr_count'            => $qr_count,
				'qr_status_code'      => $qr_status_code,
				'qr_status_label'     => $qr_status_label,
				'primary_ticket_ref'  => isset( $active_tickets_by_guest[ $gid ] ) ? $active_tickets_by_guest[ $gid ] : '-',
				'checkin_status'      => (string) $row->checkin_status,
			);
		}

		return $results;
	}

	/**
	 * Export filtered registrations report to UTF-8 BOM CSV with Formula Injection Protection
	 *
	 * @param int   $event_id Event ID.
	 * @param array $filters  Filter array.
	 * @return string|\WP_Error Raw CSV content.
	 */
	public static function export_registrations_csv( $event_id, $filters = array() ) {
		if ( ! Roles::can_export() ) {
			return new \WP_Error( 'forbidden_export', 'ليست لديك صلاحية تصدير التقارير (export_tarbwy_reports).' );
		}

		$rows = self::get_filtered_registrations_report( $event_id, $filters );

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, "\xEF\xBB\xBF" );

		$headers = array(
			'معرف التسجيل (Registration ID)',
			'الاسم الكامل (Full Name)',
			'البريد الإلكتروني (Email)',
			'رقم الهاتف (Phone)',
			'نوع التسجيل (Registration Type)',
			'المؤسسة (Organization)',
			'عدد المرافقين (Number of Companions)',
			'أسماء المرافقين (Companions)',
			'إجمالي الأفراد (Total Persons)',
			'حالة التسجيل (Registration Status)',
			'تاريخ التسجيل (Registration Date)',
			'حالة بطاقات QR (QR Generation Status)',
			'رقم تذكرة الضيف الأساسي (Primary Ticket Ref)',
		);
		fputcsv( $fh, $headers );

		foreach ( $rows as $r ) {
			$line = array(
				Security::sanitize_csv_cell( $r['registration_id'] ),
				Security::sanitize_csv_cell( $r['full_name'] ),
				Security::sanitize_csv_cell( $r['email'] ),
				Security::sanitize_csv_cell( $r['phone'] ),
				Security::sanitize_csv_cell( $r['registration_type'] ),
				Security::sanitize_csv_cell( $r['organization'] ),
				(int) $r['companions_count'],
				Security::sanitize_csv_cell( $r['companions_names'] ),
				(int) $r['total_attendees'],
				Security::sanitize_csv_cell( $r['status_label'] ),
				Security::sanitize_csv_cell( $r['registration_date'] ),
				Security::sanitize_csv_cell( $r['qr_status_label'] ),
				Security::sanitize_csv_cell( $r['primary_ticket_ref'] ),
			);
			fputcsv( $fh, $line );
		}

		rewind( $fh );
		$csv_output = stream_get_contents( $fh );
		fclose( $fh );

		Security::log_activity(
			$event_id,
			'report_registrations_csv_exported',
			'export',
			0,
			array(
				'status_filter' => $filters['status'] ?? 'all',
				'row_count'     => count( $rows ),
			)
		);

		return $csv_output;
	}

	/**
	 * Export filtered registrations report to Microsoft Excel (.xlsx / SpreadsheetML XML)
	 * Opens natively in Microsoft Excel, LibreOffice, and Google Sheets with full Arabic RTL & styling.
	 *
	 * @param int   $event_id Event ID.
	 * @param array $filters  Filter array.
	 * @return string|\WP_Error Excel SpreadsheetML string.
	 */
	public static function export_registrations_excel( $event_id, $filters = array() ) {
		if ( ! Roles::can_export() ) {
			return new \WP_Error( 'forbidden_export', 'ليست لديك صلاحية تصدير التقارير (export_tarbwy_reports).' );
		}

		$rows  = self::get_filtered_registrations_report( $event_id, $filters );
		$stats = self::get_report_statistics( $event_id );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
		$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
		$xml .= '<Styles>
			<Style ss:ID="Header">
				<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/>
				<Interior ss:Color="#14532D" ss:Pattern="Solid"/>
				<Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
			</Style>
			<Style ss:ID="Cell">
				<Font ss:Size="10" ss:Color="#1C1917"/>
				<Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
			</Style>
		</Styles>' . "\n";

		$xml .= '<Worksheet ss:Name="تقرير التسجيلات — تربوي" ss:RightToLeft="1"><Table>' . "\n";

		$headers = array(
			'معرف التسجيل (Registration ID)',
			'الاسم الكامل (Full Name)',
			'البريد الإلكتروني (Email)',
			'رقم الهاتف (Phone)',
			'نوع التسجيل (Registration Type)',
			'المؤسسة (Organization)',
			'عدد المرافقين (Companions Count)',
			'أسماء المرافقين (Companions)',
			'إجمالي الأفراد (Total Attendees)',
			'حالة التسجيل (Registration Status)',
			'تاريخ التسجيل (Registration Date)',
			'حالة بطاقات QR (QR Generation Status)',
		);

		$xml .= '<Row ss:Height="26">';
		foreach ( $headers as $h ) {
			$xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . esc_xml( $h ) . '</Data></Cell>';
		}
		$xml .= '</Row>' . "\n";

		foreach ( $rows as $r ) {
			$cells = array(
				Security::sanitize_csv_cell( $r['registration_id'] ),
				Security::sanitize_csv_cell( $r['full_name'] ),
				Security::sanitize_csv_cell( $r['email'] ),
				Security::sanitize_csv_cell( $r['phone'] ),
				Security::sanitize_csv_cell( $r['registration_type'] ),
				Security::sanitize_csv_cell( $r['organization'] ),
				(string) $r['companions_count'],
				Security::sanitize_csv_cell( $r['companions_names'] ),
				(string) $r['total_attendees'],
				Security::sanitize_csv_cell( $r['status_label'] ),
				Security::sanitize_csv_cell( $r['registration_date'] ),
				Security::sanitize_csv_cell( $r['qr_status_label'] ),
			);
			$xml .= '<Row ss:Height="20">';
			foreach ( $cells as $c ) {
				$xml .= '<Cell ss:StyleID="Cell"><Data ss:Type="String">' . esc_xml( (string) $c ) . '</Data></Cell>';
			}
			$xml .= '</Row>' . "\n";
		}

		// Summary footer row
		$xml .= '<Row ss:Height="24"><Cell ss:StyleID="Header"><Data ss:Type="String">الإجمالي الكلي</Data></Cell>';
		$xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">عدد الطلبات: ' . (int) count( $rows ) . ' | إجمالي الأفراد والمرافقين: ' . (int) $stats['total_individuals'] . ' | بطاقات QR الفعالة: ' . (int) $stats['valid_qr_codes'] . '</Data></Cell></Row>' . "\n";

		$xml .= '</Table></Worksheet></Workbook>';

		Security::log_activity(
			$event_id,
			'report_registrations_excel_exported',
			'export',
			0,
			array(
				'status_filter' => $filters['status'] ?? 'all',
				'row_count'     => count( $rows ),
			)
		);

		return $xml;
	}

	/**
	 * Render a Print-Friendly / PDF-Ready Arabic RTL Report Document
	 *
	 * @param int   $event_id Event ID.
	 * @param array $filters  Filter array.
	 * @param bool  $is_pdf   Whether triggered as PDF export.
	 * @return string|\WP_Error Full HTML document.
	 */
	public static function render_printable_report_html( $event_id, $filters = array(), $is_pdf = false ) {
		if ( ! Roles::can_export() ) {
			return new \WP_Error( 'forbidden_export', 'ليست لديك صلاحية تصدير التقارير (export_tarbwy_reports).' );
		}

		$rows   = self::get_filtered_registrations_report( $event_id, $filters );
		$stats  = self::get_report_statistics( $event_id );
		$sender = EmailManager::get_sender_config( $event_id );

		Security::log_activity(
			$event_id,
			$is_pdf ? 'report_registrations_pdf_exported' : 'report_registrations_print_exported',
			'export',
			0,
			array(
				'status_filter' => $filters['status'] ?? 'all',
				'row_count'     => count( $rows ),
			)
		);

		ob_start();
		?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8" />
	<title>تقرير التسجيلات والحضور — <?php echo esc_html( $sender['event_title'] ); ?></title>
	<style>
		@page { size: A4 landscape; margin: 12mm; }
		body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; direction: rtl; text-align: right; color: #1C1917; background: #FFFFFF; margin: 0; padding: 20px; }
		.report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #14532D; padding-bottom: 14px; margin-bottom: 18px; }
		.report-header h1 { margin: 0 0 6px 0; font-size: 20px; color: #14532D; }
		.report-header p { margin: 0; font-size: 12px; color: #57534E; }
		.stats-bar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-bottom: 18px; }
		.stat-box { background: #FAF8F5; border: 1px solid #E5E0D8; border-right: 4px solid #14532D; border-radius: 6px; padding: 8px 10px; }
		.stat-box strong { display: block; font-size: 17px; color: #14532D; }
		.stat-box span { font-size: 11px; color: #57534E; }
		table { width: 100%; border-collapse: collapse; font-size: 12px; }
		th { background: #14532D; color: #FFFFFF; padding: 8px 10px; text-align: right; border: 1px solid #14532D; }
		td { padding: 7px 10px; border: 1px solid #E5E0D8; vertical-align: middle; }
		tr:nth-child(even) td { background: #FDFBF7; }
		.toolbar { margin-bottom: 16px; display: flex; gap: 10px; }
		.btn { background: #14532D; color: #fff; border: 0; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 13px; }
		@media print { .toolbar { display: none !important; } body { padding: 0; } }
	</style>
</head>
<body>
	<div class="toolbar">
		<button class="btn" onclick="window.print()">طباعة / حفظ كملف PDF (Save as PDF)</button>
		<a class="btn" style="background:#44403C;" href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-reports' ) ); ?>">العودة إلى لوحة التقارير</a>
	</div>

	<div class="report-header">
		<div>
			<h1><?php echo esc_html( $sender['event_title'] ); ?> — التقرير الرسمي للتسجيلات والحضور</h1>
			<p>تاريخ استخراج التقرير: <?php echo esc_html( current_time( 'Y-m-d H:i' ) ); ?> | عدد السجلات المطابقة للفلتر: (<?php echo (int) count( $rows ); ?>)</p>
		</div>
		<div style="font-size:12px;color:#14532D;font-weight:bold;">تربوي — الاتحاد العربي للقيادة التربوية</div>
	</div>

	<div class="stats-bar">
		<div class="stat-box"><strong><?php echo (int) $stats['all_submissions']; ?></strong><span>إجمالي الطلبات (Submissions)</span></div>
		<div class="stat-box"><strong><?php echo (int) $stats['approved_submissions']; ?></strong><span>المقبولون (Approved)</span></div>
		<div class="stat-box"><strong><?php echo (int) $stats['rejected_submissions']; ?></strong><span>المرفوضون (Rejected)</span></div>
		<div class="stat-box"><strong><?php echo (int) $stats['waitlist_submissions']; ?></strong><span>قائمة الانتظار (Waitlist)</span></div>
		<div class="stat-box"><strong><?php echo (int) $stats['pending_submissions']; ?></strong><span>قيد المراجعة (Pending)</span></div>
		<div class="stat-box"><strong><?php echo (int) $stats['total_individuals']; ?></strong><span>إجمالي الأفراد مع المرافقين</span></div>
		<div class="stat-box"><strong><?php echo (int) $stats['valid_qr_codes']; ?></strong><span>بطاقات QR الفعالة</span></div>
	</div>

	<table>
		<thead>
			<tr>
				<th>معرف التسجيل</th>
				<th>الاسم الكامل</th>
				<th>البريد الإلكتروني</th>
				<th>الهاتف</th>
				<th>النوع</th>
				<th>عدد المرافقين</th>
				<th>إجمالي الأفراد</th>
				<th>حالة التسجيل</th>
				<th>تاريخ التسجيل</th>
				<th>حالة بطاقات QR</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="10" style="text-align:center;padding:20px;">لا توجد سجلات مطابقة لمعايير التصفية المحددة.</td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><code><?php echo esc_html( $r['registration_id'] ); ?></code></td>
						<td><strong><?php echo esc_html( $r['full_name'] ); ?></strong></td>
						<td><?php echo esc_html( $r['email'] ); ?></td>
						<td><?php echo esc_html( $r['phone'] ); ?></td>
						<td><?php echo esc_html( $r['registration_type'] ); ?></td>
						<td><?php echo (int) $r['companions_count']; ?></td>
						<td><?php echo (int) $r['total_attendees']; ?></td>
						<td><?php echo esc_html( $r['status_label'] ); ?></td>
						<td><?php echo esc_html( $r['registration_date'] ); ?></td>
						<td><?php echo esc_html( $r['qr_status_label'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php if ( $is_pdf ) : ?>
	<script>window.addEventListener('load', function() { window.print(); });</script>
	<?php endif; ?>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Generate UTF-8 BOM CSV string for individual guests (Main + Companions)
	 * Preserved for full backward compatibility.
	 *
	 * @param int    $event_id    Event ID.
	 * @param string $filter_type Filter type (all, confirmed, checked_in, no_show, vip, companions, approved, declined, waitlist, pending).
	 * @return string|\WP_Error Raw CSV string prefixed with UTF-8 BOM.
	 */
	public static function generate_guests_csv( $event_id, $filter_type = 'all' ) {
		if ( ! Roles::can_export() ) {
			return new \WP_Error( 'forbidden_export', 'ليست لديك صلاحية تصدير التقارير (export_tarbwy_reports).' );
		}

		global $wpdb;
		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$reg_table    = $wpdb->prefix . 'tarbwy_registrations';
		$tkt_table    = $wpdb->prefix . 'tarbwy_tickets';

		$where = $wpdb->prepare( 'WHERE g.event_id = %d', absint( $event_id ) );

		if ( 'confirmed' === $filter_type ) {
			$where .= " AND g.rsvp_status = 'confirmed'";
		} elseif ( 'checked_in' === $filter_type ) {
			$where .= " AND g.checkin_status = 'checked_in'";
		} elseif ( 'no_show' === $filter_type ) {
			$where .= " AND g.rsvp_status = 'confirmed' AND g.checkin_status = 'not_checked_in'";
		} elseif ( 'vip' === $filter_type ) {
			$where .= " AND g.guest_type = 'vip'";
		} elseif ( 'companions' === $filter_type ) {
			$where .= " AND g.guest_type = 'companion'";
		} elseif ( 'approved' === $filter_type ) {
			$where .= " AND (r.review_status = 'approved' OR (g.guest_type = 'vip' AND g.rsvp_status = 'confirmed'))";
		} elseif ( 'declined' === $filter_type || 'rejected' === $filter_type ) {
			$where .= " AND (r.review_status IN ('declined', 'cancelled') OR g.rsvp_status = 'declined')";
		} elseif ( 'waitlist' === $filter_type ) {
			$where .= " AND r.review_status = 'waitlist'";
		} elseif ( 'pending' === $filter_type ) {
			$where .= " AND (r.review_status = 'pending' OR (g.guest_type = 'vip' AND g.rsvp_status = 'pending_response'))";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT g.*, r.reference_code, r.review_status, t.ticket_reference, p.full_name AS parent_name
			 FROM {$guests_table} g
			 LEFT JOIN {$reg_table} r ON g.registration_id = r.id
			 LEFT JOIN {$tkt_table} t ON g.id = t.guest_id AND t.status IN ('active', 'used')
			 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
			 {$where}
			 ORDER BY g.id ASC"
		);

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, "\xEF\xBB\xBF" );

		$headers = array(
			'معرف الضيف (Guest ID)',
			'الفئة (Type)',
			'الاسم الكامل (Full Name)',
			'الضيف الأساسي (Primary Guest)',
			'المؤسسة (Organization)',
			'المسمى الوظيفي (Job Title)',
			'البريد الإلكتروني (Email)',
			'الهاتف (Phone)',
			'رقم الطلب (Registration Ref)',
			'حالة المراجعة (Review Status)',
			'حالة التأكيد (RSVP Status)',
			'رقم التذكرة (Ticket Ref)',
			'حالة الدخول (Check-in Status)',
			'وقت الدخول (Checked-in At)',
		);
		fputcsv( $fh, $headers );

		foreach ( $rows as $r ) {
			$line = array(
				$r->id,
				Security::sanitize_csv_cell( $r->guest_type ),
				Security::sanitize_csv_cell( $r->full_name ),
				Security::sanitize_csv_cell( $r->parent_name ? $r->parent_name : '-' ),
				Security::sanitize_csv_cell( $r->organization ),
				Security::sanitize_csv_cell( $r->job_title ),
				Security::sanitize_csv_cell( $r->email ),
				Security::sanitize_csv_cell( $r->phone ),
				Security::sanitize_csv_cell( $r->reference_code ? $r->reference_code : '-' ),
				Security::sanitize_csv_cell( $r->review_status ? $r->review_status : 'vip_direct' ),
				Security::sanitize_csv_cell( $r->rsvp_status ),
				Security::sanitize_csv_cell( $r->ticket_reference ? $r->ticket_reference : '-' ),
				Security::sanitize_csv_cell( $r->checkin_status ),
				Security::sanitize_csv_cell( $r->checked_in_at ? $r->checked_in_at : '-' ),
			);
			fputcsv( $fh, $line );
		}

		rewind( $fh );
		$csv_output = stream_get_contents( $fh );
		fclose( $fh );

		Security::log_activity(
			$event_id,
			'report_csv_exported',
			'export',
			0,
			array(
				'filter_type' => $filter_type,
				'row_count'   => count( $rows ),
			)
		);

		return $csv_output;
	}
}
