<?php
/**
 * Secure CSV Import (VIP Preview & Dry-Run) and UTF-8 BOM CSV Export with Formula Injection Protection
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSVHandler {

	const MAX_FILE_SIZE = 2097152; // 2 MB max upload

	/**
	 * Parse and validate VIP CSV rows (Supports dry_run preview before actual DB insertion)
	 * Never sends invitations automatically upon import per spec.
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

			// Duplicate detection warning (does not hard-block family/shared emails, alerts admin)
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
				$now = current_time( 'mysql' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$guests_table,
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
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
				);

				$guest_id = (int) $wpdb->insert_id;
				if ( $guest_id ) {
					$token_pair = Security::generate_token_pair( 24 );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->insert(
						$inv_table,
						array(
							'event_id'                => absint( $event_id ),
							'guest_id'                => $guest_id,
							'token_hash'              => $token_pair['hash'],
							'max_companions_override' => $max_companions,
							'status'                  => 'active',
							'created_by'              => get_current_user_id(),
							'created_at'              => $now,
							'updated_at'              => $now,
						),
						array( '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
					);
					$inv_id = (int) $wpdb->insert_id;
					set_transient( 'tarbwy_vip_raw_' . $inv_id, $token_pair['raw'], DAY_IN_SECONDS * 30 );
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
	 * Generate UTF-8 BOM CSV string for Arabic Excel compatibility with Formula Injection sanitization
	 * Requires 'export_tarbwy_reports' capability and logs the export operation.
	 *
	 * @param int    $event_id    Event ID.
	 * @param string $filter_type Filter type (all, confirmed, checked_in, no_show, vip, companions).
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
		// Write UTF-8 BOM (\xEF\xBB\xBF) so Microsoft Excel opens Arabic properly
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
