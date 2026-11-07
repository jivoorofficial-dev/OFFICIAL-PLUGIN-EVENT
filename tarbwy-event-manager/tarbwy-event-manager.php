<?php
/**
 * Plugin Name:       Tarbwy Event Manager
 * Plugin URI:        https://tarbwy.org
 * Description:       إضافة WordPress مستقلة ومتكاملة لإدارة حفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية»، تشمل صفحة الهبوط، التسجيل العام، دعوات VIP، بطاقات QR الفردية، نظام Check-in المتزامن، والرسائل والتقارير.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            فريق الهندسة — تربوي
 * Author URI:        https://tarbwy.org
 * Text Domain:       tarbwy-event-manager
 * Domain Path:       /languages
 * License:           GPL v2 or later
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TARBWY_EM_VERSION', '1.0.0' );
define( 'TARBWY_EM_DB_VERSION', '1.0.0' );
define( 'TARBWY_EM_FILE', __FILE__ );
define( 'TARBWY_EM_PATH', plugin_dir_path( __FILE__ ) );
define( 'TARBWY_EM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimum Environment Check before loading classes
 */
function tarbwy_em_check_requirements() {
	$errors = array();

	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		$errors[] = sprintf( 'تتطلب إضافة Tarbwy Event Manager إصدار PHP 7.4 أو أحدث. الإصدار الحالي هو %s.', esc_html( PHP_VERSION ) );
	}

	global $wp_version;
	if ( isset( $wp_version ) && version_compare( $wp_version, '6.0', '<' ) ) {
		$errors[] = sprintf( 'تتطلب إضافة Tarbwy Event Manager إصدار WordPress 6.0 أو أحدث. الإصدار الحالي هو %s.', esc_html( $wp_version ) );
	}

	if ( ! empty( $errors ) ) {
		add_action(
			'admin_notices',
			function () use ( $errors ) {
				foreach ( $errors as $error ) {
					echo '<div class="notice notice-error is-dismissible" dir="rtl"><p><strong>Tarbwy Event Manager:</strong> ' . wp_kses_post( $error ) . '</p></div>';
				}
			}
		);
		return false;
	}

	return true;
}

/**
 * Load Plugin Files
 */
function tarbwy_em_autoload() {
	$files = array(
		'includes/class-installer.php',
		'includes/class-roles.php',
		'includes/class-security.php',
		'includes/class-qr-generator.php',
		'includes/class-email-manager.php',
		'includes/class-csv-handler.php',
		'includes/class-rest-api.php',
		'includes/class-frontend-router.php',
		'includes/class-admin-dashboard.php',
		'includes/class-plugin.php',
	);

	foreach ( $files as $file ) {
		$filepath = TARBWY_EM_PATH . $file;
		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}
}

if ( tarbwy_em_check_requirements() ) {
	tarbwy_em_autoload();

	register_activation_hook( __FILE__, array( __NAMESPACE__ . '\\Installer', 'activate' ) );
	register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\\Installer', 'deactivate' ) );

	add_action(
		'plugins_loaded',
		function () {
			Plugin::get_instance()->init();
		}
	);
}
