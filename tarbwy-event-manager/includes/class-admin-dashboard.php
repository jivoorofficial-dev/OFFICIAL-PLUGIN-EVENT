<?php
/**
 * WordPress Admin Dashboard Controller (RTL Arabic Interface)
 *
 * Menus:
 * 1. Dashboard (لوحة المؤشرات)
 * 2. Guest List (قائمة الضيوف والمرافقين)
 * 3. Registration Requests (طلبات الحضور العامة)
 * 4. VIP Invitations (دعوات كبار الشخصيات VIP)
 * 5. Tickets & QR (التذاكر ورموز QR)
 * 6. Check-in (بوابة الاستقبال Check-in)
 * 7. Reports & Export (التقارير والتصدير)
 * 8. Event Settings (إعدادات الفعالية والمحتوى)
 * 9. Email Templates & Logs (قوالب وسجل البريد)
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
		add_action( 'admin_post_tarbwy_export_csv', array( __CLASS__, 'handle_csv_download' ) );
		add_action( 'admin_post_tarbwy_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_tarbwy_save_email_templates', array( __CLASS__, 'handle_save_email_templates' ) );
		add_action( 'admin_post_tarbwy_send_vip_invitation', array( __CLASS__, 'handle_send_vip_invitation' ) );
		add_action( 'admin_post_tarbwy_revoke_vip_invitation', array( __CLASS__, 'handle_revoke_vip_invitation' ) );
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
			array( 'tarbwy-em-settings', 'إعدادات الحدث (Settings)', 'manage_tarbwy_events' ),
			array( 'tarbwy-em-emails', 'القوالب وسجل البريد (Emails)', 'manage_tarbwy_events' ),
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
	 * Handle UTF-8 BOM CSV Download with Nonce + Capability check
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
	 * Save Event Settings
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
		$settings['city_display']       = isset( $_POST['city_display'] ) ? sanitize_text_field( wp_unslash( $_POST['city_display'] ) ) : '';
		$settings['about_event']        = isset( $_POST['about_event'] ) ? sanitize_textarea_field( wp_unslash( $_POST['about_event'] ) ) : '';
		$settings['about_tarbwy']       = isset( $_POST['about_tarbwy'] ) ? sanitize_textarea_field( wp_unslash( $_POST['about_tarbwy'] ) ) : '';
		$settings['contact_email']      = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
		$settings['contact_phone']      = isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '';
		$settings['entry_instructions'] = isset( $_POST['entry_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['entry_instructions'] ) ) : '';

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
	 * Save Email Templates
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
				if ( isset( $_POST['templates'][ $key ]['body'] ) ) {
					$templates[ $key ]['body'] = sanitize_textarea_field( wp_unslash( $_POST['templates'][ $key ]['body'] ) );
				}
			}
			update_option( 'tarbwy_em_email_templates', $templates );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-emails&updated=1' ) );
		exit;
	}

	/**
	 * Send or Reissue VIP Invitation Link
	 */
	public static function handle_send_vip_invitation() {
		check_admin_referer( 'tarbwy_vip_action_nonce' );
		if ( ! Roles::can_manage_invitations() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$inv_id       = isset( $_POST['invitation_id'] ) ? absint( $_POST['invitation_id'] ) : 0;
		$inv_table    = $wpdb->prefix . 'tarbwy_invitations';
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inv = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT i.*, g.full_name, g.email FROM {$inv_table} i INNER JOIN {$guests_table} g ON i.guest_id = g.id WHERE i.id = %d",
				$inv_id
			)
		);

		if ( $inv ) {
			$token_pair = Security::generate_token_pair( 24 );
			$now        = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$inv_table,
				array(
					'token_hash' => $token_pair['hash'],
					'status'     => 'active',
					'sent_at'    => $now,
					'updated_at' => $now,
				),
				array( 'id' => $inv_id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			$vip_url = home_url( '/launch/vip/?token=' . rawurlencode( $token_pair['raw'] ) );
			$event   = RestAPI::get_active_event( (int) $inv->event_id );

			EmailManager::enqueue_email(
				(int) $inv->event_id,
				(int) $inv->guest_id,
				'vip_invitation',
				$inv->email,
				array(
					'guest_name'  => $inv->full_name,
					'event_title' => $event ? $event->title : '',
					'venue_name'  => $event ? $event->venue_name : '',
					'vip_url'     => $vip_url,
				),
				true
			);

			Security::log_activity( (int) $inv->event_id, 'vip_invitation_sent', 'invitation', $inv_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip&sent=1' ) );
		exit;
	}

	/**
	 * Revoke VIP Invitation
	 */
	public static function handle_revoke_vip_invitation() {
		check_admin_referer( 'tarbwy_vip_action_nonce' );
		if ( ! Roles::can_manage_invitations() ) {
			wp_die( 'غير مصرح.', 'غير مصرح', array( 'response' => 403 ) );
		}

		global $wpdb;
		$inv_id    = isset( $_POST['invitation_id'] ) ? absint( $_POST['invitation_id'] ) : 0;
		$inv_table = $wpdb->prefix . 'tarbwy_invitations';

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

		Security::log_activity( 1, 'vip_invitation_revoked', 'invitation', $inv_id );

		wp_safe_redirect( admin_url( 'admin.php?page=tarbwy-em-vip&revoked=1' ) );
		exit;
	}
}
