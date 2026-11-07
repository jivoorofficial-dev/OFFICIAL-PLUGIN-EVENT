<?php
/**
 * Uninstaller for Tarbwy Event Manager
 *
 * By default, event data is retained on uninstall to prevent accidental loss of official event records.
 * Data is only dropped if the administrator explicitly enabled 'delete_data_on_uninstall' in settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$settings = get_option( 'tarbwy_em_global_settings', array() );
$delete_all = isset( $settings['delete_data_on_uninstall'] ) && true === (bool) $settings['delete_data_on_uninstall'];

// Always clear scheduled cron hooks
wp_clear_scheduled_hook( 'tarbwy_em_process_email_queue' );
wp_clear_scheduled_hook( 'tarbwy_em_send_event_reminders' );

if ( $delete_all ) {
	$tables = array(
		$wpdb->prefix . 'tarbwy_checkins',
		$wpdb->prefix . 'tarbwy_tickets',
		$wpdb->prefix . 'tarbwy_invitations',
		$wpdb->prefix . 'tarbwy_email_logs',
		$wpdb->prefix . 'tarbwy_activity_log',
		$wpdb->prefix . 'tarbwy_guests',
		$wpdb->prefix . 'tarbwy_registrations',
		$wpdb->prefix . 'tarbwy_events',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	delete_option( 'tarbwy_em_db_version' );
	delete_option( 'tarbwy_em_global_settings' );
	delete_option( 'tarbwy_em_email_templates' );

	remove_role( 'tarbwy_event_admin' );
	remove_role( 'tarbwy_registration_manager' );
	remove_role( 'tarbwy_checkin_staff' );
}
