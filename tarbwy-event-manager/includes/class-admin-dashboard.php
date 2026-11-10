<?php
/**
 * WordPress Admin Dashboard Controller
 *
 * Menus:
 * 1. Dashboard (لوحة المؤشرات)
 * 2. Guest List (قائمة الضيوف والمرافقين)
 * 3. Registration Requests (طلبات الحضور العامة)
 * 4. VIP Invitations (دعوات كبار الشخصيات VIP)
 * 5. Tickets & QR (التذاكر ورموز QR)
 * 6. Check-in (بوابة الاستقبال Check-in)
 * 7. Reports & Export (التقارير والتصدير)
 * 8. Email Templates & Logs (إرسال البريد والقوالب)
 * 9. Staff & Permissions (الموظفون والصلاحيات)
 * 10. Event Settings (إعدادات الحدث)
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminDashboard {

	/**
	 * Initialize admin menus, actions, and assets
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// Reports & Exports (CSV, Excel, PDF, Print)
		add_action( 'admin_post_tarbwy_export_csv', array( __CLASS__, 'handle_csv_download' ) );
		add_action( 'admin_post_tarbwy_export_report', array( __CLASS__, 'handle_export_report' ) );

		// Settings & Email Templates
		add_action( 'admin_post_tarbwy_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_tarbwy_save_email_templates', array( __CLASS__, 'handle_save_email_templates' ) );
		add_action( 'admin_post_tarbwy_send_test_email', array( __CLASS__, 'handle_send_test_email' ) );
		add_action( 'admin_post_tarbwy_restore_email_templates', array( __CLASS__, 'handle_restore_email_templates' ) );
		add_action( 'admin_post_tarbwy_retry_email', array( __CLASS__, 'handle_retry_failed_email' ) );

		// VIP Invitations (Create, View, Edit, Resend, Revoke/Cancel, Delete)
		add_action( 'admin_post_tarbwy_create_vip_guest', array( __CLASS__, 'handle_create_vip_guest' ) );
		add_action( 'admin_post_tarbwy_edit_vip_invitation', array( __CLASS__, 'handle_edit_vip_invitation' ) );
		add_action( 'admin_post_tarbwy_send_vip_invitation', array( __CLASS__, 'handle_send_vip_invitation' ) );
		add_action( 'admin_post_tarbwy_revoke_vip_invitation', array( __CLASS__, 'handle_revoke_vip_invitation' ) );
		add_action( 'admin_post_tarbwy_delete_vip_invitation', array( __CLASS__, 'handle_delete_vip_invitation' ) );

		// Registrations (Approve & Generate QR, Reject, Waitlist, Change Status, Edit, Delete)
		add_action( 'admin_post_tarbwy_review_registration', array( __CLASS__, 'handle_review_registration' ) );
		add_action( 'admin_post_tarbwy_edit_registration', array( __CLASS__, 'handle_edit_registration' ) );
		add_action( 'admin_post_tarbwy_delete_registration', array( __CLASS__, 'handle_delete_registration' ) );

		// Tickets, Single/Bulk Emails, Staff Roles, and Full Data Reset
		add_action( 'admin_post_tarbwy_reissue_ticket', array( __CLASS__, 'handle_reissue_ticket' ) );
		add_action( 'admin_post_tarbwy_send_single_email', array( __CLASS__, 'handle_send_single_email' ) );
		add_action( 'admin_post_tarbwy_send_bulk_email', array( __CLASS__, 'handle_send_bulk_email' ) );
		add_action( 'admin_post_tarbwy_assign_staff_role', array( __CLASS__, 'handle_assign_staff_role' ) );
		add_action( 'admin_post_tarbwy_reset_event_data', array( __CLASS__, 'handle_reset_event_data' ) );
	}

	/**
	 * Register WordPress Admin Menus with granular capabilities
	 */
	public static function register_menus() {
		add_menu_page(
			'إدارة فعاليات تربوي',
			'فعاليات تربوي',
			'checkin_tarbwy_guests',
			'tarbwy-em-dashboard',
			array( __CLASS__, 'render_page' ),
			'dashicons-groups',
			26
		);

		$submenus = array(
			array( 'tarbwy-em-dashboard', 'لوحة المؤشرات (Dashboard)', 'checkin_tarbwy_guests' ),
			array( 'tarbwy-em-guests', 'قائمة الضيوف (Guest List)', 'manage_tarbwy_guests' ),
			array( 'tarbwy-em-registrations', 'طلبات التسجيل (Requests)', 'review_tarbwy_registrations' ),
			array( 'tarbwy-em-vip', 'دعوات VIP (Invitations)', 'manage_tarbwy_invitations' ),
			array( 'tarbwy-em-tickets', 'التذاكر وQR (Tickets)', 'manage_tarbwy_guests' ),
			array( 'tarbwy-em-checkin', 'بوابة الدخول (Check-in)', 'checkin_tarbwy_guests' ),
			array( 'tarbwy-em-reports', 'التقارير والتصدير (Reports)', 'export_tarbwy_reports' ),
			array( 'tarbwy-em-emails', 'إرسال البريد والقوالب (Emails)', 'manage_tarbwy_events' ),
			array( 'tarbwy-em-staff', 'الموظفون والصلاحيات (Staff)', 'manage_tarbwy_events' ),
			array( 'tarbwy-em-settings', 'إعدادات الحدث (Settings)', 'manage_tarbwy_events' ),
		);

		foreach ( $submenus as $sub ) {
			add_submenu_page(
				'tarbwy-em-dashboard',
				$sub[1],
				$sub[1],
				$sub[2],
				$sub[0],
				array( __CLASS__, 'render_page' )
			);
		}
	}

	/**
	 * Enqueue local CSS and JS for admin screens
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'tarbwy-em' ) ) {
			return;
		}
		wp_enqueue_style(
			'tarbwy-em-admin',
			TARBWY_EM_URL . 'assets/css/tarbwy-admin.css',
			array(),
			TARBWY_EM_VERSION
		);
		wp_enqueue_script(
			'tarbwy-em-qr',
			TARBWY_EM_URL . 'assets/js/tarbwy-qr.js',
			array(),
			TARBWY_EM_VERSION,
			true
		);
		wp_enqueue_script(
			'tarbwy-em-admin-js',
			TARBWY_EM_URL . 'assets/js/tarbwy-app.js',
			array( 'tarbwy-em-qr' ),
			TARBWY_EM_VERSION,
			true
		);
		wp_localize_script(
			'tarbwy-em-admin-js',
			'TarbwyAdminConfig',
			array(
				'restUrl' => esc_url_raw( rest_url( RestAPI::NAMESPACE_V1 ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'homeUrl' => esc_url_raw( home_url() ),
			)
		);
	}

	/**
	 * Render admin view
	 */
	public static function render_page() {
		include TARBWY_EM_PATH . 'templates/admin-page.php';
	}

	/**
	 * Handle UTF-8 BOM CSV Download (Guests List) with Nonce + Capability check
	 */
	public static function handle_csv_download() {
		check_admin_referer( 'tarbwy_export_csv_nonce' );

		if ( ! Roles::can_export() ) {
			wp_die( 'عذراً، ليست لديك صلاحية تصدير التقارير (export_tarbwy_reports).', 'غير مصرح', array( 'response' => 403 ) );
		}

		$event_id    = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 1;
		$filter_type = isset( $_GET['filter_type'] ) ? sanitize_key( $_GET['filter_type'] ) : 'all';

		$csv_content = CSVHandler::generate_guests_csv( $event_id, $filter_type );
		if ( is_wp_error( $csv_content ) ) {
			wp_die( esc_html( $csv_content->get_error_message() ) );
		}

		$filename = sprintf( 'tarbwy-guests-%s-%s.csv', $filter_type, gmdate( 'Y-m-d' ) );

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $csv_content;
		exit;
	}

	/**
	 * Handle Filtered Registrations Report Export (CSV, Excel .xls/.xlsx, PDF, Print)
	 */
	public static function handle_export_report() {
		check_admin_referer( 'tarbwy_export_report_nonce' );

		if ( ! Roles::can_export() ) {
			wp_die( 'عذراً، ليست لديك صلاحية تصدير التقارير (export_tarbwy_reports).', 'غير مصرح', array( 'response' => 403 ) );
		}

		$event_id = isset( $_REQUEST['event_id'] ) ? absint( $_REQUEST['event_id'] ) : 1;
		$format   = isset( $_REQUEST['export_format'] ) ? sanitize_key( $_REQUEST['export_format'] ) : 'csv';
		$filters  = array(
			'status'    => isset( $_REQUEST['filter_status'] ) ? sanitize_key( $_REQUEST['filter_status'] ) : 'all',
			'reg_type'  => isset( $_REQUEST['filter_type'] ) ? sanitize_key( $_REQUEST['filter_type'] ) : 'all',
			'date_from' => isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '',
			'date_to'   => isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '',
			'search'    => isset( $_REQUEST['s_query'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s_query'] ) ) : '',
		);

		$date_stamp = gmdate( 'Y-m-d' );

		if ( 'excel' === $format || 'xlsx' === $format ) {
			$excel_xml = CSVHandler::export_registrations_excel( $event_id, $filters );
			if ( is_wp_error( $excel_xml ) ) {
				wp_die( esc_html( $excel_xml->get_error_message() ) );
			}
			$filename = sprintf( 'tarbwy-registrations-%s-%s.xls', $filters['status'], $date_stamp );
			if ( ! headers_sent() ) {
				header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $excel_xml;
			exit;
		}

		if ( 'pdf' === $format || 'print' === $format ) {
			$html = CSVHandler::render_printable_report_html( $event_id, $filters, ( 'pdf' === $format ) );
			if ( is_wp_error( $html ) ) {
				wp_die( esc_html( $html->get_error_message() ) );
			}
			if ( ! headers_sent() ) {
				header( 'Content-Type: text/html; charset=UTF-8' );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
			exit;
		}

		// Default: UTF-8 BOM CSV
		$csv_content = CSVHandler::export_registrations_csv( $event_id, $filters );
		if ( is_wp_error( $csv_content ) ) {
			wp_die( esc_html( $csv_content->get_error_message() ) );
		}

		$filename = sprintf( 'tarbwy-registrations-%s-%s.csv', $filters['status'], $date_stamp );
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $csv_content;
		exit;
	}

	/**
	 * Save Event Settings + Optional Hostinger SMTP Settings
	 */
	public static function handle_save_settings() {
		check_admin_referer( 'tarbwy_save_settings_nonce' );

		if ( ! Roles::can_manage_events() ) {
			wp_die( 'غير مصرح لك بتعديل إعدادات الفعالية.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$event = RestAPI::get_active_event();
		if ( ! $event ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-settings' ) );
			exit;
		}

		$settings = json_decode( (string) $event->settings_json, true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$title                  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $event->title;
		$venue_name             = isset( $_POST['venue_name'] ) ? sanitize_text_field( wp_unslash( $_POST['venue_name'] ) ) : $event->venue_name;
		$venue_address          = isset( $_POST['venue_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['venue_address'] ) ) : $event->venue_address;
		$map_url                = isset( $_POST['map_url'] ) ? esc_url_raw( wp_unslash( $_POST['map_url'] ) ) : '';
		$capacity_total         = isset( $_POST['capacity_total'] ) ? max( 1, absint( $_POST['capacity_total'] ) ) : 500;
		$max_companions_public  = isset( $_POST['max_companions_public'] ) ? absint( $_POST['max_companions_public'] ) : 2;
		$max_companions_vip     = isset( $_POST['max_companions_vip'] ) ? absint( $_POST['max_companions_vip'] ) : 3;
		$approval_auto_confirms = isset( $_POST['approval_auto_confirms'] ) ? 1 : 0;
		$registration_open      = isset( $_POST['registration_open'] ) ? 1 : 0;

		$settings['subtitle']           = isset( $_POST['subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['subtitle'] ) ) : '';
		$settings['date_display']       = isset( $_POST['date_display'] ) ? sanitize_text_field( wp_unslash( $_POST['date_display'] ) ) : '';
		$settings['countdown_target']   = isset( $_POST['countdown_target'] ) ? sanitize_text_field( wp_unslash( $_POST['countdown_target'] ) ) : ( $settings['countdown_target'] ?? '' );
		$settings['hero_bg_url']        = isset( $_POST['hero_bg_url'] ) ? esc_url_raw( wp_unslash( $_POST['hero_bg_url'] ) ) : ( $settings['hero_bg_url'] ?? '' );
		$settings['venue_image_url']    = isset( $_POST['venue_image_url'] ) ? esc_url_raw( wp_unslash( $_POST['venue_image_url'] ) ) : ( $settings['venue_image_url'] ?? '' );
		$settings['city_display']       = isset( $_POST['city_display'] ) ? sanitize_text_field( wp_unslash( $_POST['city_display'] ) ) : '';
		$settings['about_event']        = isset( $_POST['about_event'] ) ? sanitize_textarea_field( wp_unslash( $_POST['about_event'] ) ) : '';
		$settings['about_tarbwy']       = isset( $_POST['about_tarbwy'] ) ? sanitize_textarea_field( wp_unslash( $_POST['about_tarbwy'] ) ) : '';
		$settings['why_tarbwy']         = isset( $_POST['why_tarbwy'] ) ? sanitize_textarea_field( wp_unslash( $_POST['why_tarbwy'] ) ) : '';
		$settings['tarbwy_space']       = isset( $_POST['tarbwy_space'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tarbwy_space'] ) ) : '';
		$settings['access_info']        = isset( $_POST['access_info'] ) ? sanitize_textarea_field( wp_unslash( $_POST['access_info'] ) ) : '';
		$settings['contact_email']      = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
		$settings['contact_phone']      = isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '';
		$settings['logo_url']           = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
		$settings['entry_instructions'] = isset( $_POST['entry_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['entry_instructions'] ) ) : '';

		// Sender Identity Configuration (Never allows "WordPress", defaults to noreply@tarbwy.org)
		$raw_sender_name = isset( $_POST['sender_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['sender_name'] ) ) ) : '';
		if ( '' === $raw_sender_name || 'wordpress' === strtolower( $raw_sender_name ) ) {
			$raw_sender_name = EmailManager::DEFAULT_SENDER_NAME;
		}
		$settings['sender_name']    = $raw_sender_name;
		$raw_sender_email           = isset( $_POST['sender_email'] ) ? sanitize_email( wp_unslash( $_POST['sender_email'] ) ) : '';
		$settings['sender_email']   = is_email( $raw_sender_email ) ? $raw_sender_email : EmailManager::DEFAULT_SENDER_EMAIL;
		$raw_reply_to               = isset( $_POST['reply_to_email'] ) ? sanitize_email( wp_unslash( $_POST['reply_to_email'] ) ) : '';
		$settings['reply_to_email'] = is_email( $raw_reply_to ) ? $raw_reply_to : EmailManager::DEFAULT_REPLY_TO;

		// Optional Hostinger SMTP settings (Host, Port, Encryption, Username)
		if ( isset( $_POST['smtp_host'] ) ) {
			$settings['smtp_host'] = sanitize_text_field( wp_unslash( $_POST['smtp_host'] ) );
		}
		if ( isset( $_POST['smtp_port'] ) ) {
			$settings['smtp_port'] = absint( $_POST['smtp_port'] );
		}
		if ( isset( $_POST['smtp_encryption'] ) ) {
			$settings['smtp_encryption'] = sanitize_key( wp_unslash( $_POST['smtp_encryption'] ) );
		}
		if ( isset( $_POST['smtp_username'] ) ) {
			$settings['smtp_username'] = sanitize_email( wp_unslash( $_POST['smtp_username'] ) );
		}
		if ( ! empty( $_POST['smtp_password'] ) ) {
			update_option( 'tarbwy_em_smtp_password', trim( sanitize_text_field( wp_unslash( $_POST['smtp_password'] ) ) ), false );
		}

		// Parse optional line-delimited Event Tracks & Main Themes (Title | Badge | Description)
		if ( isset( $_POST['tracks_raw'] ) ) {
			$lines      = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( $_POST['tracks_raw'] ) ) );
			$track_list = array();
			$t_idx      = 1;
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts        = array_map( 'trim', explode( '|', $line ) );
				$track_list[] = array(
					'code'  => 'track-' . $t_idx,
					'title' => $parts[0] ?? '',
					'badge' => $parts[1] ?? 'مسار رئيسي',
					'desc'  => $parts[2] ?? '',
				);
				++$t_idx;
			}
			$settings['tracks_items'] = $track_list;
		}

		// Parse optional line-delimited schedule items
		if ( isset( $_POST['schedule_raw'] ) ) {
			$lines      = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( $_POST['schedule_raw'] ) ) );
			$sched_list = array();
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts        = array_map( 'trim', explode( '|', $line ) );
				$sched_list[] = array(
					'time'  => $parts[0] ?? '',
					'title' => $parts[1] ?? ( $parts[0] ?? '' ),
					'desc'  => $parts[2] ?? '',
				);
			}
			$settings['schedule_items'] = $sched_list;
		}

		// Parse optional line-delimited speakers (Supports 3-col: Name | Role | PhotoURL OR 5-col: Name | Role | Bio | PhotoURL | LinkURL)
		if ( isset( $_POST['speakers_raw'] ) ) {
			$lines    = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( $_POST['speakers_raw'] ) ) );
			$spk_list = array();
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts = array_map( 'trim', explode( '|', $line ) );
				$p2    = $parts[2] ?? '';
				$is_p2_url = ( '' !== $p2 && ( 0 === strpos( $p2, 'http://' ) || 0 === strpos( $p2, 'https://' ) || 0 === strpos( $p2, '/' ) ) );
				$spk_list[] = array(
					'name'      => $parts[0] ?? '',
					'role'      => $parts[1] ?? '',
					'bio'       => $is_p2_url ? ( $parts[3] ?? '' ) : $p2,
					'photo_url' => $is_p2_url ? esc_url_raw( $p2 ) : ( isset( $parts[3] ) ? esc_url_raw( $parts[3] ) : '' ),
					'link_url'  => isset( $parts[4] ) ? esc_url_raw( $parts[4] ) : '',
				);
			}
			$settings['speakers'] = $spk_list;
		}

		// Parse optional line-delimited sponsors + visibility toggle
		$settings['show_sponsors'] = isset( $_POST['show_sponsors'] ) ? 1 : 0;
		if ( isset( $_POST['sponsors_raw'] ) ) {
			$lines     = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( $_POST['sponsors_raw'] ) ) );
			$spon_list = array();
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts       = array_map( 'trim', explode( '|', $line ) );
				$spon_list[] = array(
					'name' => $parts[0] ?? '',
					'tier' => $parts[1] ?? 'شريك مؤسسي',
				);
			}
			$settings['sponsors'] = $spon_list;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'tarbwy_events',
			array(
				'title'                  => $title,
				'venue_name'             => $venue_name,
				'venue_address'          => $venue_address,
				'map_url'                => $map_url,
				'capacity_total'         => $capacity_total,
				'max_companions_public'  => $max_companions_public,
				'max_companions_vip'     => $max_companions_vip,
				'approval_auto_confirms' => $approval_auto_confirms,
				'registration_open'      => $registration_open,
				'settings_json'          => wp_json_encode( $settings, JSON_UNESCAPED_UNICODE ),
				'updated_at'             => current_time( 'mysql' ),
			),
			array( 'id' => (int) $event->id ),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);

		Security::log_activity( (int) $event->id, 'event_settings_updated', 'settings', (int) $event->id );

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-settings&updated=1' ) );
		exit;
	}

	/**
	 * Save Email Templates (Subject, Heading, Badge, Body, CTA Label, Direction)
	 */
	public static function handle_save_email_templates() {
		check_admin_referer( 'tarbwy_save_templates_nonce' );
		if ( ! Roles::can_manage_events() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		$templates = EmailManager::get_templates();
		if ( isset( $_POST['templates'] ) && is_array( $_POST['templates'] ) ) {
			foreach ( $templates as $key => $tpl ) {
				if ( isset( $_POST['templates'][ $key ]['subject'] ) ) {
					$templates[ $key ]['subject'] = sanitize_text_field( wp_unslash( $_POST['templates'][ $key ]['subject'] ) );
				}
				if ( isset( $_POST['templates'][ $key ]['heading'] ) ) {
					$templates[ $key ]['heading'] = sanitize_text_field( wp_unslash( $_POST['templates'][ $key ]['heading'] ) );
				}
				if ( isset( $_POST['templates'][ $key ]['badge'] ) ) {
					$templates[ $key ]['badge'] = sanitize_text_field( wp_unslash( $_POST['templates'][ $key ]['badge'] ) );
				}
				if ( isset( $_POST['templates'][ $key ]['body'] ) ) {
					$templates[ $key ]['body'] = sanitize_textarea_field( wp_unslash( $_POST['templates'][ $key ]['body'] ) );
				}
				if ( isset( $_POST['templates'][ $key ]['cta_label'] ) ) {
					$templates[ $key ]['cta_label'] = sanitize_text_field( wp_unslash( $_POST['templates'][ $key ]['cta_label'] ) );
				}
				if ( isset( $_POST['templates'][ $key ]['lang_dir'] ) && in_array( $_POST['templates'][ $key ]['lang_dir'], array( 'rtl', 'ltr' ), true ) ) {
					$templates[ $key ]['lang_dir'] = sanitize_key( wp_unslash( $_POST['templates'][ $key ]['lang_dir'] ) );
				}
			}
			update_option( 'tarbwy_em_email_templates', $templates );
			Security::log_activity( 1, 'email_templates_updated', 'settings', 1 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&updated=1' ) );
		exit;
	}

	/**
	 * Send a Test Email to Verify Design, Placeholders, and SMTP Delivery Before Real Dispatch
	 */
	public static function handle_send_test_email() {
		check_admin_referer( 'tarbwy_send_test_email_nonce' );
		if ( ! Roles::can_manage_events() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		$template_key = isset( $_POST['template_key'] ) ? sanitize_key( wp_unslash( $_POST['template_key'] ) ) : 'registration_approved';
		$test_email   = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		$event        = RestAPI::get_active_event();
		$eid          = $event ? (int) $event->id : 1;

		$res = EmailManager::send_test_email( $template_key, $test_email, $eid );
		if ( $res['sent'] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&test_sent=1&preview_tpl=' . rawurlencode( $template_key ) ) );
		} else {
			$err = rawurlencode( $res['error'] ? $res['error'] : 'تعذر تسليم البريد التجريبي، يرجى التحقق من إعدادات SMTP.' );
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&test_error=' . $err . '&preview_tpl=' . rawurlencode( $template_key ) ) );
		}
		exit;
	}

	/**
	 * Restore One or All Email Templates to Factory Defaults
	 */
	public static function handle_restore_email_templates() {
		check_admin_referer( 'tarbwy_restore_templates_nonce' );
		if ( ! Roles::can_manage_events() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		$target_key = isset( $_POST['restore_key'] ) ? sanitize_key( wp_unslash( $_POST['restore_key'] ) ) : 'all';
		EmailManager::restore_default_templates( $target_key );
		Security::log_activity( 1, 'email_templates_restored', 'settings', 1, array( 'restore_key' => $target_key ) );

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&restored=1' ) );
		exit;
	}

	/**
	 * Retry a failed or queued email without creating duplicate QR codes
	 */
	public static function handle_retry_failed_email() {
		check_admin_referer( 'tarbwy_retry_email_nonce' );
		if ( ! Roles::can_review_registrations() && ! Roles::can_manage_events() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		$log_id      = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		$return_page = isset( $_POST['return_page'] ) ? sanitize_key( wp_unslash( $_POST['return_page'] ) ) : 'tarbwy-em-emails';

		$res = EmailManager::retry_failed_email( $log_id );
		if ( $res['sent'] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . $return_page . '&retry_sent=1' ) );
		} else {
			$err = rawurlencode( $res['error'] ? $res['error'] : 'فشلت إعادة إرسال البريد عبر خادم SMTP.' );
			wp_safe_redirect( admin_url( 'admin.php?page=' . $return_page . '&email_error=' . $err ) );
		}
		exit;
	}

	/**
	 * Handle Registration Review & Status Change (Approve & Generate QR, Reject, Waitlist, Pending)
	 *
	 * - Uses DB transactions.
	 * - Generates 1 unique QR code per person (Main attendee + all approved companions).
	 * - Prevents duplicate QR generation on repeated clicks.
	 * - Revokes QR codes immediately if changed from Approved to Rejected, Waitlisted, or Pending.
	 * - Embeds all generated QR passes in the primary confirmation email.
	 * - Never reports email as sent if SMTP fails.
	 */
	public static function handle_review_registration() {
		check_admin_referer( 'tarbwy_review_reg_nonce' );
		if ( ! Roles::can_review_registrations() ) {
			wp_die( 'غير مصرح لك بمراجعة طلبات التسجيل.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$reg_id        = isset( $_POST['registration_id'] ) ? absint( $_POST['registration_id'] ) : 0;
		$decision      = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$review_note   = isset( $_POST['review_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_note'] ) ) : '';
		$approved_gids = isset( $_POST['approved_guest_ids'] ) && is_array( $_POST['approved_guest_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['approved_guest_ids'] ) )
			: array();

		$allowed_decisions = array( 'pending', 'approved', 'waitlist', 'declined' );
		if ( $reg_id <= 0 || ! in_array( $decision, $allowed_decisions, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-registrations' ) );
			exit;
		}

		$reg_table    = $wpdb->prefix . 'tarbwy_registrations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reg_table} WHERE id = %d LIMIT 1", $reg_id ) );
		if ( ! $reg ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-registrations' ) );
			exit;
		}

		$event       = RestAPI::get_active_event( (int) $reg->event_id );
		$eid         = $event ? (int) $event->id : (int) $reg->event_id;
		$now         = current_time( 'mysql' );
		$prev_status = (string) $reg->review_status;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		$upd_data    = array(
			'review_status'   => $decision,
			'previous_status' => $prev_status,
			'reviewed_by'     => get_current_user_id(),
			'reviewed_at'     => $now,
			'review_note'     => $review_note,
			'updated_at'      => $now,
		);
		$upd_formats = array( '%s', '%s', '%d', '%s', '%s', '%s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->update( $reg_table, $upd_data, array( 'id' => $reg_id ), $upd_formats, array( '%d' ) );
		if ( false === $ok ) {
			unset( $upd_data['previous_status'] );
			$upd_formats = array( '%s', '%d', '%s', '%s', '%s' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $reg_table, $upd_data, array( 'id' => $reg_id ), $upd_formats, array( '%d' ) );
		}

		$primary_gid_lookup = (int) $reg->primary_guest_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$guests_table} WHERE registration_id = %d OR (%d > 0 AND (id = %d OR parent_guest_id = %d)) ORDER BY CASE WHEN parent_guest_id IS NULL OR parent_guest_id = 0 THEN 0 ELSE 1 END ASC, id ASC",
				$reg_id,
				$primary_gid_lookup,
				$primary_gid_lookup,
				$primary_gid_lookup
			)
		);
		if ( empty( $guests ) ) {
			Installer::heal_orphaned_registrations();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$reg                = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reg_table} WHERE id = %d LIMIT 1", $reg_id ) );
			$primary_gid_lookup = $reg ? (int) $reg->primary_guest_id : 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$guests = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$guests_table} WHERE registration_id = %d OR (%d > 0 AND (id = %d OR parent_guest_id = %d)) ORDER BY CASE WHEN parent_guest_id IS NULL OR parent_guest_id = 0 THEN 0 ELSE 1 END ASC, id ASC",
					$reg_id,
					$primary_gid_lookup,
					$primary_gid_lookup,
					$primary_gid_lookup
				)
			);
		}

		$primary_guest = null;
		foreach ( $guests as $g ) {
			if ( empty( $g->parent_guest_id ) ) {
				$primary_guest = $g;
				break;
			}
		}
		if ( ! $primary_guest && ! empty( $guests ) ) {
			$primary_guest = $guests[0];
		}

		$issued_tickets = array();
		$primary_log_id = 0;

		if ( 'approved' === $decision ) {
			foreach ( $guests as $g ) {
				$gid        = (int) $g->id;
				$is_primary = empty( $g->parent_guest_id );
				$include    = $is_primary || empty( $approved_gids ) || in_array( $gid, $approved_gids, true );

				if ( $include ) {
					// Always confirm approved guests and ensure registration_id is linked
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$guests_table,
						array(
							'registration_id' => $reg_id,
							'rsvp_status'     => 'confirmed',
							'updated_at'      => $now,
						),
						array( 'id' => $gid ),
						array( '%d', '%s', '%s' ),
						array( '%d' )
					);

					// Idempotent: reuses existing active QR if already issued, preventing duplicate QR codes on repeated clicks
					$tkt = QRGenerator::issue_ticket_for_guest( $eid, $gid, false );
					if ( ! is_wp_error( $tkt ) ) {
						$tkt_url      = QRGenerator::get_ticket_url( $tkt['raw_token'] );
						$holder_label = ( ! $is_primary && $primary_guest )
							? $g->full_name . ' (مرافق: ' . $primary_guest->full_name . ')'
							: $g->full_name;

						$issued_tickets[] = array(
							'guest_id'         => $gid,
							'guest_name'       => $holder_label,
							'guest_type'       => $is_primary ? 'public' : 'companion',
							'ticket_reference' => $tkt['ticket_reference'],
							'qr_payload'       => $tkt['ticket_reference'],
							'ticket_url'       => $tkt_url,
							'qr_token'         => $tkt['raw_token'],
						);

						// If companion has a distinct email address, send them their own pass as well
						if ( ! $is_primary && ! empty( $g->email ) && $primary_guest && strtolower( $g->email ) !== strtolower( $primary_guest->email ) ) {
							EmailManager::enqueue_email(
								$eid,
								$gid,
								'ticket_issued',
								$g->email,
								array(
									'guest_name'       => $holder_label,
									'event_title'      => $event ? $event->title : '',
									'venue_name'       => $event ? $event->venue_name : '',
									'ticket_reference' => $tkt['ticket_reference'],
									'qr_payload'       => $tkt['ticket_reference'],
									'ticket_url'       => $tkt_url,
								),
								true
							);
						}
					}
				} else {
					// Companion excluded during partial approval: mark declined and revoke any active QR
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$guests_table,
						array(
							'rsvp_status' => 'declined',
							'updated_at'  => $now,
						),
						array( 'id' => $gid ),
						array( '%s', '%s' ),
						array( '%d' )
					);
					QRGenerator::revoke_tickets_for_guest( $eid, $gid, 'companion_excluded' );
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'COMMIT' );

			if ( $primary_guest && ! empty( $primary_guest->email ) && ! empty( $issued_tickets ) ) {
				$primary_log_id = (int) EmailManager::enqueue_email(
					$eid,
					(int) $primary_guest->id,
					'registration_approved',
					$primary_guest->email,
					array(
						'guest_name'       => $primary_guest->full_name,
						'event_title'      => $event ? $event->title : '',
						'venue_name'       => $event ? $event->venue_name : '',
						'reference_code'   => $reg->reference_code,
						'ticket_reference' => $issued_tickets[0]['ticket_reference'],
						'qr_payload'       => $issued_tickets[0]['ticket_reference'],
						'ticket_url'       => $issued_tickets[0]['ticket_url'],
						'tickets_count'    => (string) count( $issued_tickets ),
						'all_tickets'      => $issued_tickets,
					),
					true
				);
			}
		} else {
			// Status changed to waitlist, declined, or pending: immediately revoke all active QR tickets for this registration!
			QRGenerator::revoke_tickets_for_registration( $eid, $reg_id, 'status_changed_to_' . $decision );

			$rsvp_st = ( 'declined' === $decision ) ? 'declined' : 'not_invited';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$guests_table,
				array(
					'rsvp_status' => $rsvp_st,
					'updated_at'  => $now,
				),
				array( 'registration_id' => $reg_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'COMMIT' );

			if ( $primary_guest && ! empty( $primary_guest->email ) ) {
				if ( 'waitlist' === $decision ) {
					$primary_log_id = (int) EmailManager::enqueue_email(
						$eid,
						(int) $primary_guest->id,
						'registration_waitlist',
						$primary_guest->email,
						array(
							'guest_name'     => $primary_guest->full_name,
							'event_title'    => $event ? $event->title : '',
							'reference_code' => $reg->reference_code,
						),
						true
					);
				} elseif ( 'declined' === $decision ) {
					$primary_log_id = (int) EmailManager::enqueue_email(
						$eid,
						(int) $primary_guest->id,
						'registration_declined',
						$primary_guest->email,
						array(
							'guest_name'     => $primary_guest->full_name,
							'event_title'    => $event ? $event->title : '',
							'reference_code' => $reg->reference_code,
						),
						true
					);
				} else {
					$primary_log_id = (int) EmailManager::enqueue_email(
						$eid,
						(int) $primary_guest->id,
						'registration_status_changed',
						$primary_guest->email,
						array(
							'guest_name'            => $primary_guest->full_name,
							'event_title'           => $event ? $event->title : '',
							'reference_code'        => $reg->reference_code,
							'previous_status_label' => $prev_status,
							'new_status_label'      => 'قيد المراجعة (Pending)',
							'status_action_note'    => 'تمت إعادة الطلب إلى حالة قيد المراجعة وإلغاء أي بطاقات دخول سابقة.',
						),
						true
					);
				}
			}
		}

		Security::log_activity(
			$eid,
			'registration_reviewed_' . $decision,
			'registration',
			$reg_id,
			array(
				'previous_status' => $prev_status,
				'new_status'      => $decision,
				'qr_count'        => count( $issued_tickets ),
			)
		);

		$redirect_args = array(
			'page'     => 'tarbwy-em-registrations',
			'updated'  => 1,
			'decision' => $decision,
			'qr_count' => count( $issued_tickets ),
		);

		if ( $primary_log_id > 0 ) {
			$mail_st = EmailManager::get_log_status( $primary_log_id );
			if ( $mail_st['sent'] ) {
				$redirect_args['email_sent'] = 1;
			} else {
				$redirect_args['email_error']  = rawurlencode( $mail_st['error'] ? $mail_st['error'] : 'تعذر إرسال البريد عبر خادم SMTP — تم حفظ البطاقات ويمكنك إعادة المحاولة.' );
				$redirect_args['retry_log_id'] = $primary_log_id;
			}
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle Editing an Existing Registration (Primary Guest Info & Notes)
	 */
	public static function handle_edit_registration() {
		check_admin_referer( 'tarbwy_edit_reg_nonce' );
		if ( ! Roles::can_review_registrations() ) {
			wp_die( 'غير مصرح لك بتعديل طلبات التسجيل.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$reg_id       = isset( $_POST['registration_id'] ) ? absint( $_POST['registration_id'] ) : 0;
		$full_name    = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$organization = isset( $_POST['organization'] ) ? sanitize_text_field( wp_unslash( $_POST['organization'] ) ) : '';
		$job_title    = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '';
		$review_note  = isset( $_POST['review_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_note'] ) ) : '';

		$reg_table    = $wpdb->prefix . 'tarbwy_registrations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reg_table} WHERE id = %d LIMIT 1", $reg_id ) );
		if ( ! $reg ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-registrations' ) );
			exit;
		}

		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$reg_table,
			array(
				'review_note' => $review_note,
				'updated_at'  => $now,
			),
			array( 'id' => $reg_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( ! empty( $full_name ) ) {
			$primary_gid  = (int) $reg->primary_guest_id;
			$guest_exists = false;
			if ( $primary_gid > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$guest_exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$guests_table} WHERE id = %d LIMIT 1", $primary_gid ) );
			}

			if ( $primary_gid > 0 && $guest_exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$guests_table,
					array(
						'registration_id' => $reg_id,
						'full_name'       => $full_name,
						'email'           => $email,
						'phone'           => $phone,
						'organization'    => $organization,
						'job_title'       => $job_title,
						'updated_at'      => $now,
					),
					array( 'id' => $primary_gid ),
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// Create missing primary guest record and link it to this registration
				$new_gid = Installer::safe_insert_guest(
					array(
						'event_id'        => (int) $reg->event_id,
						'registration_id' => $reg_id,
						'guest_type'      => 'public',
						'full_name'       => $full_name,
						'email'           => $email,
						'phone'           => $phone,
						'organization'    => $organization,
						'job_title'       => $job_title,
						'rsvp_status'     => 'approved' === $reg->review_status ? 'confirmed' : 'not_invited',
						'checkin_status'  => 'not_checked_in',
						'created_at'      => $now,
						'updated_at'      => $now,
					)
				);
				if ( $new_gid > 0 ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$reg_table,
						array( 'primary_guest_id' => $new_gid ),
						array( 'id' => $reg_id ),
						array( '%d' ),
						array( '%d' )
					);
					if ( 'approved' === $reg->review_status ) {
						QRGenerator::issue_ticket_for_guest( (int) $reg->event_id, $new_gid, false );
					}
				}
			}
		}

		Security::log_activity( (int) $reg->event_id, 'registration_edited', 'registration', $reg_id );

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-registrations&edited=1' ) );
		exit;
	}

	/**
	 * Handle Deleting a Registration with Cascading Ticket/Companion Cleanup & Activity Log
	 */
	public static function handle_delete_registration() {
		check_admin_referer( 'tarbwy_delete_reg_nonce' );
		if ( ! Roles::can_review_registrations() ) {
			wp_die( 'غير مصرح لك بحذف طلبات التسجيل.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$reg_id = isset( $_POST['registration_id'] ) ? absint( $_POST['registration_id'] ) : 0;
		if ( $reg_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-registrations' ) );
			exit;
		}

		$reg_table      = $wpdb->prefix . 'tarbwy_registrations';
		$guests_table   = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table  = $wpdb->prefix . 'tarbwy_tickets';
		$checkins_table = $wpdb->prefix . 'tarbwy_checkins';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reg_table} WHERE id = %d LIMIT 1", $reg_id ) );
		if ( ! $reg ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-registrations' ) );
			exit;
		}

		$eid = (int) $reg->event_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		// Revoke and delete all tickets & checkins for guests in this registration
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$guests_table} WHERE registration_id = %d", $reg_id ) );
		if ( ! empty( $guest_ids ) ) {
			foreach ( $guest_ids as $gid ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $checkins_table, array( 'guest_id' => (int) $gid ), array( '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $tickets_table, array( 'guest_id' => (int) $gid ), array( '%d' ) );
			}
		}

		// Delete guests (primary + companions) and registration record
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $guests_table, array( 'registration_id' => $reg_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $reg_table, array( 'id' => $reg_id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );

		Security::log_activity(
			$eid,
			'registration_deleted',
			'registration',
			$reg_id,
			array(
				'reference_code' => (string) $reg->reference_code,
				'deleted_guests' => count( $guest_ids ),
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-registrations&deleted=1' ) );
		exit;
	}

	/**
	 * Handle Creating a New VIP Guest + Invitation from Admin Panel
	 * Saves guest & invitation to DB (or reuses existing VIP guest with same email to prevent duplicates on double-click),
	 * issues QR if pre-confirmed, dispatches email, and reports accurate email status.
	 */
	public static function handle_create_vip_guest() {
		check_admin_referer( 'tarbwy_create_vip_nonce' );
		if ( ! Roles::can_manage_invitations() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$full_name      = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone          = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$organization   = isset( $_POST['organization'] ) ? sanitize_text_field( wp_unslash( $_POST['organization'] ) ) : '';
		$job_title      = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '';
		$max_companions = isset( $_POST['max_companions'] ) ? absint( $_POST['max_companions'] ) : 2;
		$pre_confirm    = ! empty( $_POST['pre_confirm_vip'] );

		if ( empty( $full_name ) || ! is_email( $email ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip&vip_error=' . rawurlencode( 'يرجى إدخال الاسم الكامل وبريد إلكتروني صالح.' ) ) );
			exit;
		}

		$event        = RestAPI::get_active_event();
		$eid          = $event ? (int) $event->id : 1;
		$now          = current_time( 'mysql' );
		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';

		// Prevent double-click duplicate submission within 15 seconds for the same email
		$lock_key = 'tarbwy_vip_create_lock_' . md5( $eid . '|' . strtolower( trim( $email ) ) );
		if ( get_transient( $lock_key ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'tarbwy-em-vip',
						'vip_created' => 1,
						'sent'        => 1,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		set_transient( $lock_key, 1, 15 );

		$rsvp_initial = $pre_confirm ? 'confirmed' : 'pending_response';

		// Check if a VIP guest with the same email already exists in this event (idempotency)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_vip = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$guests_table} WHERE event_id = %d AND guest_type = 'vip' AND LOWER(email) = LOWER(%s) ORDER BY id DESC LIMIT 1",
				$eid,
				$email
			)
		);

		if ( $existing_vip ) {
			$guest_id = (int) $existing_vip->id;
			$new_rsvp = ( 'confirmed' === $existing_vip->rsvp_status || $pre_confirm ) ? 'confirmed' : $existing_vip->rsvp_status;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$guests_table,
				array(
					'full_name'    => $full_name,
					'phone'        => $phone ? $phone : $existing_vip->phone,
					'organization' => $organization ? $organization : $existing_vip->organization,
					'job_title'    => $job_title ? $job_title : $existing_vip->job_title,
					'rsvp_status'  => $new_rsvp,
					'updated_at'   => $now,
				),
				array( 'id' => $guest_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$guest_id = Installer::safe_insert_guest(
				array(
					'event_id'       => $eid,
					'guest_type'     => 'vip',
					'full_name'      => $full_name,
					'email'          => $email,
					'phone'          => $phone,
					'organization'   => $organization,
					'job_title'      => $job_title,
					'rsvp_status'    => $rsvp_initial,
					'checkin_status' => 'not_checked_in',
					'created_at'     => $now,
					'updated_at'     => $now,
				)
			);
		}

		if ( $guest_id <= 0 ) {
			delete_transient( $lock_key );
			$db_err = ! empty( $wpdb->last_error ) ? $wpdb->last_error : 'تعذر حفظ سجل ضيف VIP في قاعدة البيانات.';
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip&vip_error=' . rawurlencode( $db_err ) ) );
			exit;
		}

		// Reuse existing active invitation for this VIP guest if one already exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_inv = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$inv_table} WHERE event_id = %d AND guest_id = %d ORDER BY id DESC LIMIT 1",
				$eid,
				$guest_id
			)
		);

		if ( $existing_inv && ! empty( $existing_inv->token_preview ) && 'revoked' !== $existing_inv->status ) {
			$inv_id    = (int) $existing_inv->id;
			$raw_token = (string) $existing_inv->token_preview;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$inv_table,
				array(
					'max_companions_override' => $max_companions,
					'status'                  => 'active',
					'sent_at'                 => $now,
					'updated_at'              => $now,
				),
				array( 'id' => $inv_id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$token_pair  = Security::generate_token_pair( 24 );
			$raw_token   = $token_pair['raw'];
			$inv_payload = array(
				'event_id'                => $eid,
				'guest_id'                => $guest_id,
				'token_hash'              => $token_pair['hash'],
				'token_preview'           => $raw_token,
				'max_companions_override' => $max_companions,
				'status'                  => 'active',
				'sent_at'                 => $now,
				'created_by'              => get_current_user_id(),
				'created_at'              => $now,
				'updated_at'              => $now,
			);

			$inv_id = Installer::safe_insert_invitation( $inv_payload );
		}

		if ( $inv_id > 0 ) {
			set_transient( 'tarbwy_vip_raw_' . $inv_id, $raw_token, DAY_IN_SECONDS * 30 );
		}

		$vip_code     = Security::format_vip_personal_code( $raw_token );
		$vip_url      = home_url( '/launch/vip/?token=' . rawurlencode( $raw_token ) );
		$placeholders = array(
			'guest_name'  => $full_name,
			'event_title' => $event ? $event->title : '',
			'venue_name'  => $event ? $event->venue_name : '',
			'vip_url'     => $vip_url,
			'vip_code'    => $vip_code,
		);

		// Issue QR ticket immediately if pre-confirmed
		if ( $pre_confirm ) {
			$tkt = QRGenerator::issue_ticket_for_guest( $eid, $guest_id, false );
			if ( ! is_wp_error( $tkt ) ) {
				$placeholders['ticket_reference'] = $tkt['ticket_reference'];
				$placeholders['qr_payload']       = $tkt['ticket_reference'];
				$placeholders['ticket_url']       = $tkt['ticket_url'];
				$placeholders['all_tickets']      = array(
					array(
						'guest_id'         => $guest_id,
						'guest_name'       => $full_name,
						'guest_type'       => 'vip',
						'ticket_reference' => $tkt['ticket_reference'],
						'qr_payload'       => $tkt['ticket_reference'],
						'ticket_url'       => $tkt['ticket_url'],
					),
				);
			}
		}

		$log_id = (int) EmailManager::enqueue_email(
			$eid,
			$guest_id,
			$pre_confirm ? 'vip_rsvp_confirmed' : 'vip_invitation',
			$email,
			$placeholders,
			true
		);
		Security::log_activity( $eid, 'vip_guest_created_and_invited', 'invitation', $guest_id );

		$redirect_args = array(
			'page'        => 'tarbwy-em-vip',
			'vip_created' => 1,
		);
		if ( $log_id > 0 ) {
			$st = EmailManager::get_log_status( $log_id );
			if ( $st['sent'] ) {
				$redirect_args['sent'] = 1;
			} else {
				$redirect_args['email_error']  = rawurlencode( $st['error'] ? $st['error'] : 'تم حفظ دعوة VIP في قاعدة البيانات، ولكن تعذر إرسال البريد عبر خادم SMTP.' );
				$redirect_args['retry_log_id'] = $log_id;
			}
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Edit an Existing VIP Guest & Invitation
	 */
	public static function handle_edit_vip_invitation() {
		check_admin_referer( 'tarbwy_edit_vip_nonce' );
		if ( ! Roles::can_manage_invitations() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$guest_id       = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		$inv_id         = isset( $_POST['invitation_id'] ) ? absint( $_POST['invitation_id'] ) : 0;
		$full_name      = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone          = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$organization   = isset( $_POST['organization'] ) ? sanitize_text_field( wp_unslash( $_POST['organization'] ) ) : '';
		$job_title      = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '';
		$max_companions = isset( $_POST['max_companions'] ) ? absint( $_POST['max_companions'] ) : 2;
		$rsvp_status    = isset( $_POST['rsvp_status'] ) ? sanitize_key( wp_unslash( $_POST['rsvp_status'] ) ) : 'pending_response';

		if ( $guest_id <= 0 || empty( $full_name ) || ! is_email( $email ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip' ) );
			exit;
		}

		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';
		$now          = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$guests_table} WHERE id = %d LIMIT 1", $guest_id ) );
		if ( $guest ) {
			$eid = (int) $guest->event_id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$guests_table,
				array(
					'full_name'    => $full_name,
					'email'        => $email,
					'phone'        => $phone,
					'organization' => $organization,
					'job_title'    => $job_title,
					'rsvp_status'  => in_array( $rsvp_status, array( 'pending_response', 'confirmed', 'declined' ), true ) ? $rsvp_status : $guest->rsvp_status,
					'updated_at'   => $now,
				),
				array( 'id' => $guest_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( $inv_id > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$inv_table,
					array(
						'max_companions_override' => $max_companions,
						'updated_at'              => $now,
					),
					array( 'id' => $inv_id ),
					array( '%d', '%s' ),
					array( '%d' )
				);
			}

			if ( 'confirmed' === $rsvp_status ) {
				QRGenerator::issue_ticket_for_guest( $eid, $guest_id, false );
			} elseif ( 'declined' === $rsvp_status ) {
				QRGenerator::revoke_tickets_for_guest( $eid, $guest_id, 'vip_declined' );
			}

			Security::log_activity( $eid, 'vip_invitation_edited', 'invitation', $guest_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip&vip_edited=1' ) );
		exit;
	}

	/**
	 * Resend VIP Invitation Link WITHOUT Creating Duplicate Invitations or Duplicate QR Codes
	 */
	public static function handle_send_vip_invitation() {
		check_admin_referer( 'tarbwy_vip_action_nonce' );
		if ( ! Roles::can_manage_invitations() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$inv_id       = isset( $_POST['invitation_id'] ) ? absint( $_POST['invitation_id'] ) : 0;
		$guest_id     = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		$force_new    = ! empty( $_POST['force_new_token'] );
		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		if ( $inv_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inv = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT i.*, g.full_name, g.email, g.rsvp_status FROM {$inv_table} i INNER JOIN {$guests_table} g ON i.guest_id = g.id WHERE i.id = %d",
					$inv_id
				)
			);
		} else {
			$inv = null;
		}

		// Auto-heal if VIP guest existed without an invitation row
		if ( ! $inv && $guest_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$g = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$guests_table} WHERE id = %d LIMIT 1", $guest_id ) );
			if ( $g ) {
				$now        = current_time( 'mysql' );
				$token_pair = Security::generate_token_pair( 24 );
				$inv_id     = Installer::safe_insert_invitation(
					array(
						'event_id'      => (int) $g->event_id,
						'guest_id'      => (int) $g->id,
						'token_hash'    => $token_pair['hash'],
						'token_preview' => $token_pair['raw'],
						'status'        => 'active',
						'sent_at'       => $now,
						'created_by'    => get_current_user_id(),
						'created_at'    => $now,
						'updated_at'    => $now,
					)
				);
				if ( $inv_id > 0 ) {
					set_transient( 'tarbwy_vip_raw_' . $inv_id, $token_pair['raw'], DAY_IN_SECONDS * 30 );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$inv = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT i.*, g.full_name, g.email, g.rsvp_status FROM {$inv_table} i INNER JOIN {$guests_table} g ON i.guest_id = g.id WHERE i.id = %d",
							$inv_id
						)
					);
				}
			}
		}

		$log_id = 0;
		if ( $inv ) {
			// Prevent accidental double-click resend within 10 seconds
			$resend_lock = 'tarbwy_vip_resend_lock_' . (int) $inv->id;
			if ( ! $force_new && get_transient( $resend_lock ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip&sent=1' ) );
				exit;
			}
			set_transient( $resend_lock, 1, 10 );

			$now       = current_time( 'mysql' );
			$raw_token = ! empty( $inv->token_preview ) ? (string) $inv->token_preview : (string) get_transient( 'tarbwy_vip_raw_' . $inv->id );

			// Reuse existing active token unless forced or revoked/missing
			if ( empty( $raw_token ) || 'revoked' === $inv->status || $force_new ) {
				$token_pair = Security::generate_token_pair( 24 );
				$raw_token  = $token_pair['raw'];
				$upd_data   = array(
					'token_hash'    => $token_pair['hash'],
					'token_preview' => $raw_token,
					'status'        => 'active',
					'sent_at'       => $now,
					'updated_at'    => $now,
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ok = $wpdb->update( $inv_table, $upd_data, array( 'id' => (int) $inv->id ), array( '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
				if ( false === $ok ) {
					unset( $upd_data['token_preview'] );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update( $inv_table, $upd_data, array( 'id' => (int) $inv->id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
				}
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$inv_table,
					array(
						'sent_at'    => $now,
						'updated_at' => $now,
					),
					array( 'id' => (int) $inv->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}

			set_transient( 'tarbwy_vip_raw_' . $inv->id, $raw_token, DAY_IN_SECONDS * 30 );

			$vip_code     = Security::format_vip_personal_code( $raw_token );
			$vip_url      = home_url( '/launch/vip/?token=' . rawurlencode( $raw_token ) );
			$event        = RestAPI::get_active_event( (int) $inv->event_id );
			$placeholders = array(
				'guest_name'  => $inv->full_name,
				'event_title' => $event ? $event->title : '',
				'venue_name'  => $event ? $event->venue_name : '',
				'vip_url'     => $vip_url,
				'vip_code'    => $vip_code,
			);

			// If VIP already confirmed, reuse their existing QR ticket without creating a duplicate
			if ( 'confirmed' === $inv->rsvp_status ) {
				$tkt = QRGenerator::issue_ticket_for_guest( (int) $inv->event_id, (int) $inv->guest_id, false );
				if ( ! is_wp_error( $tkt ) ) {
					$placeholders['ticket_reference'] = $tkt['ticket_reference'];
					$placeholders['qr_payload']       = $tkt['ticket_reference'];
					$placeholders['ticket_url']       = $tkt['ticket_url'];
					$placeholders['all_tickets']      = array(
						array(
							'guest_id'         => (int) $inv->guest_id,
							'guest_name'       => $inv->full_name,
							'guest_type'       => 'vip',
							'ticket_reference' => $tkt['ticket_reference'],
							'qr_payload'       => $tkt['ticket_reference'],
							'ticket_url'       => $tkt['ticket_url'],
						),
					);
				}
			}

			$log_id = (int) EmailManager::enqueue_email(
				(int) $inv->event_id,
				(int) $inv->guest_id,
				'vip_invitation_resent',
				$inv->email,
				$placeholders,
				true
			);

			Security::log_activity( (int) $inv->event_id, 'vip_invitation_resent', 'invitation', (int) $inv->id );
		}

		$redirect_args = array(
			'page' => 'tarbwy-em-vip',
		);
		if ( $log_id > 0 ) {
			$st = EmailManager::get_log_status( $log_id );
			if ( $st['sent'] ) {
				$redirect_args['sent'] = 1;
			} else {
				$redirect_args['email_error']  = rawurlencode( $st['error'] ? $st['error'] : 'تعذر إعادة إرسال البريد عبر خادم SMTP.' );
				$redirect_args['retry_log_id'] = $log_id;
			}
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Revoke / Cancel VIP Invitation and Invalidate Associated QR Codes
	 */
	public static function handle_revoke_vip_invitation() {
		check_admin_referer( 'tarbwy_vip_action_nonce' );
		if ( ! Roles::can_manage_invitations() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$inv_id    = isset( $_POST['invitation_id'] ) ? absint( $_POST['invitation_id'] ) : 0;
		$guest_id  = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		$inv_table = $wpdb->prefix . 'tarbwy_invitations';

		if ( $inv_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$inv_table} WHERE id = %d LIMIT 1", $inv_id ) );
			if ( $inv ) {
				$guest_id = (int) $inv->guest_id;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$inv_table,
					array(
						'status'     => 'revoked',
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $inv_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		if ( $guest_id > 0 ) {
			QRGenerator::revoke_tickets_for_guest( 1, $guest_id, 'vip_invitation_revoked' );
		}

		Security::log_activity( 1, 'vip_invitation_revoked', 'invitation', $inv_id ? $inv_id : $guest_id );

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip&revoked=1' ) );
		exit;
	}

	/**
	 * Delete a VIP Invitation & Guest Record
	 */
	public static function handle_delete_vip_invitation() {
		check_admin_referer( 'tarbwy_vip_action_nonce' );
		if ( ! Roles::can_manage_invitations() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$inv_id         = isset( $_POST['invitation_id'] ) ? absint( $_POST['invitation_id'] ) : 0;
		$guest_id       = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		$inv_table      = $wpdb->prefix . 'tarbwy_invitations';
		$guests_table   = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table  = $wpdb->prefix . 'tarbwy_tickets';
		$checkins_table = $wpdb->prefix . 'tarbwy_checkins';

		if ( $inv_id > 0 && 0 === $guest_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$guest_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT guest_id FROM {$inv_table} WHERE id = %d", $inv_id ) );
		}

		if ( $guest_id > 0 ) {
			// Delete VIP companions and their tickets
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$comp_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$guests_table} WHERE parent_guest_id = %d", $guest_id ) );
			$all_gids = array_merge( array( $guest_id ), array_map( 'intval', $comp_ids ) );

			foreach ( $all_gids as $gid ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $checkins_table, array( 'guest_id' => $gid ), array( '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $tickets_table, array( 'guest_id' => $gid ), array( '%d' ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $guests_table, array( 'parent_guest_id' => $guest_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $inv_table, array( 'guest_id' => $guest_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $guests_table, array( 'id' => $guest_id ), array( '%d' ) );

			Security::log_activity( 1, 'vip_invitation_deleted', 'invitation', $guest_id );
		} elseif ( $inv_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $inv_table, array( 'id' => $inv_id ), array( '%d' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip&vip_deleted=1' ) );
		exit;
	}

	/**
	 * Reissue Guest QR Ticket from Admin Panel (Forces a New Token & Revokes Old Token)
	 */
	public static function handle_reissue_ticket() {
		check_admin_referer( 'tarbwy_reissue_ticket_nonce' );
		if ( ! Roles::can_manage_guests() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$guest_id     = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$guests_table} WHERE id = %d LIMIT 1", $guest_id ) );
		if ( $guest && 'confirmed' === $guest->rsvp_status ) {
			$tkt = QRGenerator::issue_ticket_for_guest( (int) $guest->event_id, (int) $guest->id, true );
			if ( ! is_wp_error( $tkt ) ) {
				Security::log_activity( (int) $guest->event_id, 'ticket_reissued', 'ticket', (int) $guest->id );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-tickets&updated=1' ) );
		exit;
	}

	/**
	 * Send Email to a Single Person (Per-Person Dispatch)
	 */
	public static function handle_send_single_email() {
		check_admin_referer( 'tarbwy_send_single_email_nonce' );
		if ( ! Roles::can_manage_guests() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$guest_id     = isset( $_POST['guest_id'] ) ? absint( $_POST['guest_id'] ) : 0;
		$template_key = isset( $_POST['template_key'] ) ? sanitize_key( wp_unslash( $_POST['template_key'] ) ) : 'ticket_issued';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$tkt_table    = $wpdb->prefix . 'tarbwy_tickets';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT g.*, t.ticket_reference, t.token_preview, p.email AS parent_email
				 FROM {$guests_table} g
				 LEFT JOIN {$tkt_table} t ON g.id = t.guest_id AND t.status IN ('active', 'used')
				 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
				 WHERE g.id = %d
				 ORDER BY CASE WHEN t.status = 'active' THEN 0 ELSE 1 END ASC, t.id DESC
				 LIMIT 1",
				$guest_id
			)
		);

		$log_id = 0;
		if ( $guest ) {
			$event        = RestAPI::get_active_event( (int) $guest->event_id );
			$target_email = ! empty( $guest->email ) ? $guest->email : $guest->parent_email;
			if ( ! empty( $target_email ) ) {
				if ( empty( $guest->ticket_reference ) && ( 'confirmed' === $guest->rsvp_status || in_array( $template_key, array( 'ticket_issued', 'registration_approved', 'vip_rsvp_confirmed', 'event_reminder' ), true ) ) ) {
					$issued = QRGenerator::issue_ticket_for_guest( (int) $guest->event_id, (int) $guest->id, false );
					if ( ! is_wp_error( $issued ) ) {
						$guest->ticket_reference = $issued['ticket_reference'];
						$guest->token_preview    = $issued['raw_token'];
					}
				}
				$canonical_qr = ! empty( $guest->ticket_reference ) ? QRGenerator::get_canonical_qr_payload( $guest ) : '-';
				$tkt_url      = ! empty( $guest->token_preview ) ? QRGenerator::get_ticket_url( $guest->token_preview ) : ( '-' !== $canonical_qr ? QRGenerator::get_ticket_url( $canonical_qr ) : home_url( '/launch/ticket' ) );
				$log_id       = (int) EmailManager::enqueue_email(
					(int) $guest->event_id,
					(int) $guest->id,
					$template_key,
					$target_email,
					array(
						'guest_name'       => $guest->full_name,
						'event_title'      => $event ? $event->title : '',
						'venue_name'       => $event ? $event->venue_name : '',
						'ticket_reference' => $canonical_qr,
						'qr_payload'       => $canonical_qr,
						'ticket_url'       => $tkt_url,
					),
					true
				);
				Security::log_activity( (int) $guest->event_id, 'single_email_dispatched', 'email', (int) $guest->id, array( 'template_key' => $template_key ) );
			}
		}

		if ( $log_id > 0 ) {
			$st = EmailManager::get_log_status( $log_id );
			if ( $st['sent'] ) {
				wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&single_sent=1' ) );
			} else {
				$err = rawurlencode( $st['error'] ? $st['error'] : 'تعذر إرسال البريد عبر خادم SMTP.' );
				wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&email_error=' . $err . '&retry_log_id=' . $log_id ) );
			}
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&email_error=' . rawurlencode( 'عنوان البريد الإلكتروني للضيف غير متوفر.' ) ) );
		}
		exit;
	}

	/**
	 * Send Bulk Email to a Target Segment (VIP Unsent, All Confirmed, Checked-in Thank You, Waitlist)
	 */
	public static function handle_send_bulk_email() {
		check_admin_referer( 'tarbwy_send_bulk_email_nonce' );
		if ( ! Roles::can_manage_events() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$segment      = isset( $_POST['target_segment'] ) ? sanitize_key( wp_unslash( $_POST['target_segment'] ) ) : 'confirmed';
		$template_key = isset( $_POST['template_key'] ) ? sanitize_key( wp_unslash( $_POST['template_key'] ) ) : 'event_reminder';
		$event        = RestAPI::get_active_event();
		$eid          = $event ? (int) $event->id : 1;

		$guests_table = $wpdb->prefix . 'tarbwy_guests';
		$tkt_table    = $wpdb->prefix . 'tarbwy_tickets';
		$where        = $wpdb->prepare( 'WHERE g.event_id = %d', $eid );

		if ( 'confirmed' === $segment ) {
			$where .= " AND g.rsvp_status = 'confirmed'";
		} elseif ( 'checked_in' === $segment ) {
			$where .= " AND g.checkin_status = 'checked_in'";
		} elseif ( 'vip_pending' === $segment ) {
			$where .= " AND g.guest_type = 'vip' AND g.rsvp_status = 'pending_response'";
		} elseif ( 'all_vip' === $segment ) {
			$where .= " AND g.guest_type = 'vip'";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw_recipients = $wpdb->get_results(
			"SELECT g.id, g.full_name, g.email, g.rsvp_status, t.ticket_reference, t.token_preview, p.email AS parent_email
			 FROM {$guests_table} g
			 LEFT JOIN {$tkt_table} t ON g.id = t.guest_id AND t.status IN ('active', 'used')
			 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
			 {$where}
			 ORDER BY g.id ASC, t.id DESC"
		);

		$recipients = array();
		$seen_rec   = array();
		foreach ( (array) $raw_recipients as $rec_item ) {
			$rid_key = (int) $rec_item->id;
			if ( isset( $seen_rec[ $rid_key ] ) ) {
				continue;
			}
			$seen_rec[ $rid_key ] = true;
			$recipients[]         = $rec_item;
		}

		$queued_count = 0;
		$sent_count   = 0;
		foreach ( $recipients as $r ) {
			$target_email = ! empty( $r->email ) ? $r->email : $r->parent_email;
			if ( ! empty( $target_email ) ) {
				if ( empty( $r->ticket_reference ) && 'confirmed' === $r->rsvp_status ) {
					$issued = QRGenerator::issue_ticket_for_guest( $eid, (int) $r->id, false );
					if ( ! is_wp_error( $issued ) ) {
						$r->ticket_reference = $issued['ticket_reference'];
						$r->token_preview    = $issued['raw_token'];
					}
				}
				$canonical_qr = ! empty( $r->ticket_reference ) ? QRGenerator::get_canonical_qr_payload( $r ) : '-';
				$tkt_url      = ! empty( $r->token_preview ) ? QRGenerator::get_ticket_url( $r->token_preview ) : ( '-' !== $canonical_qr ? QRGenerator::get_ticket_url( $canonical_qr ) : home_url( '/launch/ticket' ) );
				$lid          = EmailManager::enqueue_email(
					$eid,
					(int) $r->id,
					$template_key,
					$target_email,
					array(
						'guest_name'       => $r->full_name,
						'event_title'      => $event ? $event->title : '',
						'venue_name'       => $event ? $event->venue_name : '',
						'ticket_reference' => $canonical_qr,
						'qr_payload'       => $canonical_qr,
						'ticket_url'       => $tkt_url,
					),
					true
				);
				++$queued_count;
				if ( $lid ) {
					$st = EmailManager::get_log_status( (int) $lid );
					if ( $st['sent'] ) {
						++$sent_count;
					}
				}
			}
		}

		Security::log_activity(
			$eid,
			'bulk_email_dispatched',
			'email',
			0,
			array(
				'segment'      => $segment,
				'template_key' => $template_key,
				'queued_count' => $queued_count,
				'sent_count'   => $sent_count,
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&bulk_sent=' . $sent_count . '&bulk_queued=' . $queued_count ) );
		exit;
	}

	/**
	 * Assign, Change, or Revert a WordPress User's Tarbwy Event Staff Role
	 */
	public static function handle_assign_staff_role() {
		check_admin_referer( 'tarbwy_assign_staff_nonce' );
		if ( ! Roles::can_manage_events() ) {
			wp_die( 'غير مصرح لك بتعديل صلاحيات الموظفين.', 'غير مصرح', array( 'response' => 403 ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$role    = isset( $_POST['tarbwy_role'] ) ? sanitize_key( wp_unslash( $_POST['tarbwy_role'] ) ) : '';

		$result = Roles::assign_user_role( $user_id, $role, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$err_msg = rawurlencode( $result->get_error_message() );
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-staff&role_error=' . $err_msg . '&selected_user=' . $user_id ) );
			exit;
		}

		Security::log_activity(
			1,
			'staff_role_assigned',
			'staff',
			$user_id,
			array(
				'assigned_role' => $role,
			)
		);

		wp_safe_redirect(
			admin_url(
				'admin.php?page=tarbwy-em-staff&updated=1&selected_user=' . $user_id . '&assigned_role=' . rawurlencode( $role )
			)
		);
		exit;
	}

	/**
	 * Reset / Wipe All Test Event Data Before Official Launch (Protected by password "tarbwy")
	 *
	 * Clears all registrations, guests, companions, VIP invitations, QR tickets, check-ins,
	 * email queue/logs, and cached transients, and resets AUTO_INCREMENT to 1 while keeping
	 * event configuration and SMTP settings intact.
	 */
	public static function handle_reset_event_data() {
		check_admin_referer( 'tarbwy_reset_event_data_nonce' );

		if ( ! Roles::can_manage_events() ) {
			wp_die( 'غير مصرح لك بتصفير بيانات الفعالية.', 'غير مصرح', array( 'response' => 403 ) );
		}

		$password = isset( $_POST['reset_password'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['reset_password'] ) ) ) : '';

		if ( ! hash_equals( 'tarbwy', $password ) ) {
			$err_msg = rawurlencode( 'كلمة سر التصفير غير صحيحة. يرجى إدخال كلمة السر المعتمدة لتأكيد تصفير كافة البيانات.' );
			wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-settings&reset_error=' . $err_msg ) );
			exit;
		}

		global $wpdb;
		$event = RestAPI::get_active_event();
		$eid   = $event ? (int) $event->id : 1;

		$tables_to_wipe = array(
			$wpdb->prefix . 'tarbwy_checkins',
			$wpdb->prefix . 'tarbwy_tickets',
			$wpdb->prefix . 'tarbwy_invitations',
			$wpdb->prefix . 'tarbwy_registrations',
			$wpdb->prefix . 'tarbwy_guests',
			$wpdb->prefix . 'tarbwy_email_logs',
			$wpdb->prefix . 'tarbwy_activity_log',
		);

		foreach ( $tables_to_wipe as $table_name ) {
			// Delete all rows and reset AUTO_INCREMENT to 1 (works even when MySQL user lacks DROP/TRUNCATE privilege)
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM {$table_name}" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table_name} AUTO_INCREMENT = 1" );
		}

		// Clear any cached raw token or rate-limit transients created during testing
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\_transient\_tarbwy\_%'
			    OR option_name LIKE '\_transient\_timeout\_tarbwy\_%'"
		);

		Security::log_activity(
			$eid,
			'event_data_reset_for_launch',
			'system',
			$eid,
			array(
				'reset_by_user_id' => get_current_user_id(),
				'timestamp'        => current_time( 'mysql' ),
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-settings&data_reset=1' ) );
		exit;
	}
}

