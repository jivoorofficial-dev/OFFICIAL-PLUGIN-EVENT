<?php
/**
 * Main Plugin Orchestrator Class
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/**
	 * Singleton instance
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get Singleton Instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks
	 */
	public function init() {
		Installer::maybe_upgrade();

		// Register custom 5-minute cron schedule
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );

		// Register REST API routes
		add_action( 'rest_api_init', array( RestAPI::class, 'register_routes' ) );

		// Initialize Frontend Routes & Shortcodes
		FrontendRouter::init();

		// Initialize Admin Dashboard
		if ( is_admin() ) {
			AdminDashboard::init();
		}

		// Cron Hooks for Email Queue & Event Reminders
		add_action( 'tarbwy_em_process_email_queue', array( EmailManager::class, 'process_queue_batch' ) );
		add_action( 'tarbwy_em_send_event_reminders', array( $this, 'process_scheduled_reminders' ) );
	}

	/**
	 * Add 5-minute interval to WP Cron schedules
	 */
	public function register_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['every_five_minutes'] ) ) {
			$schedules['every_five_minutes'] = array(
				'interval' => 300,
				'display'  => 'كل 5 دقائق (Tarbwy Email Queue)',
			);
		}
		return $schedules;
	}

	/**
	 * Automatically queue reminder emails 24 hours before event start for confirmed guests only
	 */
	public function process_scheduled_reminders() {
		global $wpdb;
		$event = RestAPI::get_active_event();
		if ( ! $event || empty( $event->event_start_at ) ) {
			return;
		}

		$start_ts = strtotime( $event->event_start_at );
		$now_ts   = current_time( 'timestamp' );
		$diff     = $start_ts - $now_ts;

		// Trigger if event is within 24 hours and hasn't started yet
		if ( $diff > 0 && $diff <= DAY_IN_SECONDS ) {
			$guests_table  = $wpdb->prefix . 'tarbwy_guests';
			$tickets_table = $wpdb->prefix . 'tarbwy_tickets';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$confirmed_guests = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT g.id, g.full_name, g.email, g.parent_guest_id, t.ticket_reference, p.email AS parent_email
					 FROM {$guests_table} g
					 LEFT JOIN {$tickets_table} t ON g.id = t.guest_id AND t.status = 'active'
					 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
					 WHERE g.event_id = %d AND g.rsvp_status = 'confirmed'",
					(int) $event->id
				)
			);

			foreach ( $confirmed_guests as $cg ) {
				$email = ! empty( $cg->email ) ? $cg->email : $cg->parent_email;
				if ( ! empty( $email ) ) {
					EmailManager::enqueue_email(
						(int) $event->id,
						(int) $cg->id,
						'event_reminder',
						$email,
						array(
							'guest_name'       => $cg->full_name,
							'event_title'      => $event->title,
							'event_date'       => $event->event_start_at,
							'venue_name'       => $event->venue_name,
							'ticket_reference' => $cg->ticket_reference ? $cg->ticket_reference : '-',
						),
						false // Idempotent: only sends once per guest
					);
				}
			}
		}
	}
}
