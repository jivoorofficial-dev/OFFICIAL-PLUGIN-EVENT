<?php
/**
 * Role-Based Access Control (RBAC) and Capabilities
 *
 * Capabilities:
 * - manage_tarbwy_events
 * - review_tarbwy_registrations
 * - manage_tarbwy_guests
 * - manage_tarbwy_invitations
 * - checkin_tarbwy_guests
 * - export_tarbwy_reports
 *
 * Roles:
 * - tarbwy_event_admin (Event Administrator)
 * - tarbwy_registration_manager (Registration Manager)
 * - tarbwy_checkin_staff (Check-in Staff - strictly limited to check-in only)
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Roles {

	/**
	 * Return all plugin capabilities
	 */
	public static function get_all_capabilities() {
		return array(
			'manage_tarbwy_events',
			'review_tarbwy_registrations',
			'manage_tarbwy_guests',
			'manage_tarbwy_invitations',
			'checkin_tarbwy_guests',
			'export_tarbwy_reports',
		);
	}

	/**
	 * Register custom roles and grant capabilities to Administrator
	 */
	public static function register_roles_and_caps() {
		$all_caps = self::get_all_capabilities();

		// Grant all capabilities to WordPress Administrator
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( $all_caps as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}

		// 1. Event Administrator (مدير الفعالية)
		remove_role( 'tarbwy_event_admin' );
		add_role(
			'tarbwy_event_admin',
			'مدير فعالية تربوي (Event Administrator)',
			array(
				'read'                        => true,
				'manage_tarbwy_events'        => true,
				'review_tarbwy_registrations' => true,
				'manage_tarbwy_guests'        => true,
				'manage_tarbwy_invitations'   => true,
				'checkin_tarbwy_guests'       => true,
				'export_tarbwy_reports'       => true,
			)
		);

		// 2. Registration Manager (مسؤول التسجيل والدعوات)
		remove_role( 'tarbwy_registration_manager' );
		add_role(
			'tarbwy_registration_manager',
			'مسؤول تسجيل تربوي (Registration Manager)',
			array(
				'read'                        => true,
				'review_tarbwy_registrations' => true,
				'manage_tarbwy_guests'        => true,
				'manage_tarbwy_invitations'   => true,
				'checkin_tarbwy_guests'       => true,
			)
		);

		// 3. Check-in Staff (موظف الاستقبال والتسجيل)
		// Strictly barred from managing requests, exports, or event settings.
		remove_role( 'tarbwy_checkin_staff' );
		add_role(
			'tarbwy_checkin_staff',
			'موظف استقبال تربوي (Check-in Staff)',
			array(
				'read'                  => true,
				'checkin_tarbwy_guests' => true,
			)
		);
	}

	/**
	 * Helper checks
	 */
	public static function can_manage_events() {
		return current_user_can( 'manage_tarbwy_events' ) || current_user_can( 'manage_options' );
	}

	public static function can_review_registrations() {
		return current_user_can( 'review_tarbwy_registrations' ) || self::can_manage_events();
	}

	public static function can_manage_guests() {
		return current_user_can( 'manage_tarbwy_guests' ) || self::can_manage_events();
	}

	public static function can_manage_invitations() {
		return current_user_can( 'manage_tarbwy_invitations' ) || self::can_manage_events();
	}

	public static function can_checkin() {
		return current_user_can( 'checkin_tarbwy_guests' ) || self::can_manage_events();
	}

	public static function can_export() {
		return current_user_can( 'export_tarbwy_reports' ) || self::can_manage_events();
	}
}
