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
	 * User meta key storing the explicitly assigned Tarbwy Event Manager role
	 */
	const META_KEY_ROLE = 'tarbwy_em_role';

	/**
	 * Return all plugin capabilities
	 *
	 * @return string[]
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
	 * Return all plugin role keys
	 *
	 * @return string[]
	 */
	public static function get_plugin_role_keys() {
		return array(
			'tarbwy_event_admin',
			'tarbwy_registration_manager',
			'tarbwy_checkin_staff',
		);
	}

	/**
	 * Return human-readable Arabic labels for plugin roles
	 *
	 * @return array<string, string>
	 */
	public static function get_role_labels() {
		return array(
			'tarbwy_event_admin'          => 'مدير الفعالية (Event Administrator)',
			'tarbwy_registration_manager' => 'مسؤول التسجيل (Registration Manager)',
			'tarbwy_checkin_staff'        => 'موظف استقبال (Check-in Staff)',
			'none'                        => 'بدون دور في الفعالية (No Event Role)',
		);
	}

	/**
	 * Return capability map for each plugin role
	 *
	 * @param string $role_key Role slug.
	 * @return array<string, bool>
	 */
	public static function get_role_capabilities_map( $role_key ) {
		switch ( $role_key ) {
			case 'tarbwy_event_admin':
				return array(
					'read'                        => true,
					'manage_tarbwy_events'        => true,
					'review_tarbwy_registrations' => true,
					'manage_tarbwy_guests'        => true,
					'manage_tarbwy_invitations'   => true,
					'checkin_tarbwy_guests'       => true,
					'export_tarbwy_reports'       => true,
				);
			case 'tarbwy_registration_manager':
				return array(
					'read'                        => true,
					'review_tarbwy_registrations' => true,
					'manage_tarbwy_guests'        => true,
					'manage_tarbwy_invitations'   => true,
					'checkin_tarbwy_guests'       => true,
				);
			case 'tarbwy_checkin_staff':
				return array(
					'read'                  => true,
					'checkin_tarbwy_guests' => true,
				);
			default:
				return array();
		}
	}

	/**
	 * Register custom roles and grant capabilities to WordPress Administrator
	 */
	public static function register_roles_and_caps() {
		try {
			if ( ! function_exists( 'get_role' ) || ! function_exists( 'add_role' ) ) {
				return;
			}

			$all_caps = self::get_all_capabilities();

			// Grant all capabilities to WordPress Administrator
			$admin_role = get_role( 'administrator' );
			if ( $admin_role && method_exists( $admin_role, 'add_cap' ) ) {
				foreach ( $all_caps as $cap ) {
					$admin_role->add_cap( $cap );
				}
			}

			// 1. Event Administrator (مدير الفعالية)
			if ( function_exists( 'remove_role' ) ) {
				remove_role( 'tarbwy_event_admin' );
			}
			add_role(
				'tarbwy_event_admin',
				'مدير فعالية تربوي (Event Administrator)',
				self::get_role_capabilities_map( 'tarbwy_event_admin' )
			);

			// 2. Registration Manager (مسؤول التسجيل والدعوات)
			if ( function_exists( 'remove_role' ) ) {
				remove_role( 'tarbwy_registration_manager' );
			}
			add_role(
				'tarbwy_registration_manager',
				'مسؤول تسجيل تربوي (Registration Manager)',
				self::get_role_capabilities_map( 'tarbwy_registration_manager' )
			);

			// 3. Check-in Staff (موظف الاستقبال والتسجيل)
			// Strictly barred from managing requests, exports, or event settings.
			if ( function_exists( 'remove_role' ) ) {
				remove_role( 'tarbwy_checkin_staff' );
			}
			add_role(
				'tarbwy_checkin_staff',
				'موظف استقبال تربوي (Check-in Staff)',
				self::get_role_capabilities_map( 'tarbwy_checkin_staff' )
			);
		} catch ( \Throwable $e ) {
			error_log( 'Tarbwy Event Manager roles registration notice: ' . $e->getMessage() );
		}
	}

	/**
	 * Ensure plugin roles exist in WP_Roles even if plugin was updated without reactivation
	 */
	public static function ensure_roles_registered() {
		if ( ! get_role( 'tarbwy_event_admin' ) || ! get_role( 'tarbwy_registration_manager' ) || ! get_role( 'tarbwy_checkin_staff' ) ) {
			self::register_roles_and_caps();
		}
	}

	/**
	 * Determine the user's current Tarbwy Event Manager role
	 *
	 * Priority:
	 * 1. Explicit Tarbwy role present in $user->roles (tarbwy_event_admin, tarbwy_registration_manager, tarbwy_checkin_staff).
	 * 2. Persisted user meta 'tarbwy_em_role' if valid.
	 * 3. Default to 'tarbwy_event_admin' for WordPress Administrators who have not been assigned a specific sub-role.
	 * 4. Otherwise 'none'.
	 *
	 * @param \WP_User|int $user User object or ID.
	 * @return string Role key ('tarbwy_event_admin', 'tarbwy_registration_manager', 'tarbwy_checkin_staff', or 'none').
	 */
	public static function get_user_tarbwy_role( $user ) {
		if ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', (int) $user );
		}
		if ( ! $user || ! isset( $user->ID ) ) {
			return 'none';
		}

		$user_roles   = is_array( $user->roles ) ? $user->roles : array();
		$plugin_roles = self::get_plugin_role_keys();

		// Check persisted meta first if it matches an active plugin role
		$meta_role = get_user_meta( (int) $user->ID, self::META_KEY_ROLE, true );
		if ( is_string( $meta_role ) && in_array( $meta_role, $plugin_roles, true ) && in_array( $meta_role, $user_roles, true ) ) {
			return $meta_role;
		}

		// Check user->roles in priority order
		foreach ( $plugin_roles as $r_key ) {
			if ( in_array( $r_key, $user_roles, true ) ) {
				return $r_key;
			}
		}

		// Fallback to persisted meta if valid
		if ( is_string( $meta_role ) && in_array( $meta_role, $plugin_roles, true ) ) {
			return $meta_role;
		}

		// WordPress site administrators default to Event Administrator (never Check-in Staff)
		if ( in_array( 'administrator', $user_roles, true ) || user_can( $user, 'manage_options' ) ) {
			return 'tarbwy_event_admin';
		}

		return 'none';
	}

	/**
	 * Safely assign, change, or revert a Tarbwy Event Manager role for a WordPress user.
	 *
	 * Guarantees:
	 * - Never uses $user->set_role(), preserving existing native WordPress roles (especially 'administrator').
	 * - Never removes 'administrator' or locks out a site administrator.
	 * - Prevents unauthorized users or non-admin staff from modifying administrator accounts or escalating privileges.
	 * - Persists both the WordPress secondary role in wp_capabilities usermeta and 'tarbwy_em_role' usermeta.
	 *
	 * @param int    $target_user_id User ID to modify.
	 * @param string $new_role       Target role ('tarbwy_event_admin', 'tarbwy_registration_manager', 'tarbwy_checkin_staff', or 'none').
	 * @param int    $actor_user_id  Optional ID of user performing the action (defaults to current_user_id).
	 * @return true|\WP_Error
	 */
	public static function assign_user_role( $target_user_id, $new_role, $actor_user_id = 0 ) {
		$target_user_id = absint( $target_user_id );
		$new_role       = sanitize_key( (string) $new_role );
		$actor_user_id  = $actor_user_id ? absint( $actor_user_id ) : get_current_user_id();

		if ( $actor_user_id <= 0 || ! ( user_can( $actor_user_id, 'manage_tarbwy_events' ) || user_can( $actor_user_id, 'manage_options' ) ) ) {
			return new \WP_Error( 'forbidden', 'غير مصرح لك بتعديل أدوار وصلاحيات الموظفين.' );
		}

		$allowed_roles = array_merge( self::get_plugin_role_keys(), array( 'none' ) );
		if ( $target_user_id <= 0 || ! in_array( $new_role, $allowed_roles, true ) ) {
			return new \WP_Error( 'invalid_input', 'بيانات المستخدم أو الدور المحدد غير صالحة.' );
		}

		$user = get_user_by( 'id', $target_user_id );
		if ( ! $user || ! isset( $user->ID ) ) {
			return new \WP_Error( 'user_not_found', 'المستخدم المحدد غير موجود في قاعدة بيانات WordPress.' );
		}

		$is_target_wp_admin = in_array( 'administrator', (array) $user->roles, true ) || user_can( $user, 'manage_options' );
		$is_actor_wp_admin  = user_can( $actor_user_id, 'manage_options' );

		// Prevent non-WP-administrators from modifying a WP Administrator account
		if ( $is_target_wp_admin && ! $is_actor_wp_admin ) {
			return new \WP_Error( 'admin_protected', 'لا يمكن تعديل حساب مدير النظام الأساسي إلا بواسطة مدير نظام (WordPress Administrator).' );
		}

		self::ensure_roles_registered();

		// 1. Remove any previously assigned Tarbwy plugin roles without touching native WP roles
		foreach ( self::get_plugin_role_keys() as $plugin_role ) {
			if ( in_array( $plugin_role, (array) $user->roles, true ) ) {
				$user->remove_role( $plugin_role );
			}
		}

		// 2. For non-administrators, clean up any direct per-user Tarbwy capability overrides
		if ( ! $is_target_wp_admin ) {
			foreach ( self::get_all_capabilities() as $cap ) {
				$user->remove_cap( $cap );
			}
		}

		// 3. Ensure WordPress Administrator never loses 'administrator' role
		if ( $is_target_wp_admin && ! in_array( 'administrator', (array) $user->roles, true ) ) {
			$user->add_role( 'administrator' );
		}

		// 4. Apply the newly selected Tarbwy role (or revert to 'none')
		if ( 'none' === $new_role ) {
			delete_user_meta( (int) $user->ID, self::META_KEY_ROLE );
			// If a regular user has no remaining WP roles at all, assign 'subscriber' so account stays valid
			if ( ! $is_target_wp_admin && empty( $user->roles ) ) {
				$user->add_role( 'subscriber' );
			}
		} else {
			$user->add_role( $new_role );
			update_user_meta( (int) $user->ID, self::META_KEY_ROLE, $new_role );
		}

		// 5. Flush user cache so role changes persist across refresh and login sessions
		clean_user_cache( (int) $user->ID );
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( (int) $user->ID, 'users' );
			wp_cache_delete( (int) $user->ID, 'user_meta' );
		}

		return true;
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
