<?php
/**
 * Database Installer & Schema Migrations
 *
 * Uses $wpdb->prefix and dbDelta() with InnoDB engine and strict UNIQUE constraints.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {

	/**
	 * Run on plugin activation
	 */
	public static function activate() {
		self::create_tables();
		self::seed_default_event();
		Roles::register_roles_and_caps();
		EmailManager::seed_default_templates();
		FrontendRouter::register_rewrite_rules();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( 'tarbwy_em_process_email_queue' ) ) {
			wp_schedule_event( time(), 'every_five_minutes', 'tarbwy_em_process_email_queue' );
		}
		if ( ! wp_next_scheduled( 'tarbwy_em_send_event_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'tarbwy_em_send_event_reminders' );
		}

		update_option( 'tarbwy_em_db_version', TARBWY_EM_DB_VERSION );
	}

	/**
	 * Run on plugin deactivation (Never deletes event data)
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'tarbwy_em_process_email_queue' );
		wp_clear_scheduled_hook( 'tarbwy_em_send_event_reminders' );
		flush_rewrite_rules();
	}

	/**
	 * Check and run migrations if version changed
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'tarbwy_em_db_version', '0.0.0' );
		if ( version_compare( $installed_version, TARBWY_EM_DB_VERSION, '<' ) ) {
			self::create_tables();
			Roles::register_roles_and_caps();
			update_option( 'tarbwy_em_db_version', TARBWY_EM_DB_VERSION );
		}
	}

	/**
	 * Create all 8 custom InnoDB tables with proper indexes and UNIQUE constraints
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql = array();

		// 1. tarbwy_events
		$sql[] = "CREATE TABLE {$prefix}tarbwy_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key varchar(64) NOT NULL,
			title varchar(255) NOT NULL,
			slug varchar(100) NOT NULL DEFAULT 'launch',
			status varchar(32) NOT NULL DEFAULT 'published',
			event_start_at datetime DEFAULT NULL,
			event_end_at datetime DEFAULT NULL,
			timezone varchar(64) NOT NULL DEFAULT 'Asia/Riyadh',
			venue_name varchar(255) DEFAULT NULL,
			venue_address text DEFAULT NULL,
			map_url text DEFAULT NULL,
			capacity_total int(11) unsigned NOT NULL DEFAULT 500,
			max_companions_public int(11) unsigned NOT NULL DEFAULT 2,
			max_companions_vip int(11) unsigned NOT NULL DEFAULT 3,
			approval_auto_confirms tinyint(1) NOT NULL DEFAULT 1,
			registration_open tinyint(1) NOT NULL DEFAULT 1,
			rsvp_deadline datetime DEFAULT NULL,
			settings_json longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY event_key (event_key),
			KEY slug (slug),
			KEY status (status)
		) ENGINE=InnoDB {$charset_collate};";

		// 2. tarbwy_registrations
		$sql[] = "CREATE TABLE {$prefix}tarbwy_registrations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			reference_code varchar(32) NOT NULL,
			primary_guest_id bigint(20) unsigned DEFAULT NULL,
			review_status varchar(32) NOT NULL DEFAULT 'pending',
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			review_note text DEFAULT NULL,
			submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY reference_code (reference_code),
			KEY event_id_status (event_id, review_status),
			KEY primary_guest_id (primary_guest_id)
		) ENGINE=InnoDB {$charset_collate};";

		// 3. tarbwy_guests (Email is intentionally NOT unique globally per spec)
		$sql[] = "CREATE TABLE {$prefix}tarbwy_guests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			registration_id bigint(20) unsigned DEFAULT NULL,
			parent_guest_id bigint(20) unsigned DEFAULT NULL,
			guest_type varchar(32) NOT NULL DEFAULT 'public',
			full_name varchar(255) NOT NULL,
			email varchar(191) DEFAULT NULL,
			phone varchar(64) DEFAULT NULL,
			organization varchar(255) DEFAULT NULL,
			job_title varchar(255) DEFAULT NULL,
			rsvp_status varchar(32) NOT NULL DEFAULT 'not_invited',
			checkin_status varchar(32) NOT NULL DEFAULT 'not_checked_in',
			checked_in_at datetime DEFAULT NULL,
			checked_in_by bigint(20) unsigned DEFAULT NULL,
			dietary_requirements varchar(255) DEFAULT NULL,
			accessibility_requirements varchar(255) DEFAULT NULL,
			admin_notes text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_id_type (event_id, guest_type),
			KEY event_id_rsvp (event_id, rsvp_status),
			KEY event_id_checkin (event_id, checkin_status),
			KEY registration_id (registration_id),
			KEY parent_guest_id (parent_guest_id),
			KEY email_lookup (email),
			KEY full_name_lookup (full_name(100))
		) ENGINE=InnoDB {$charset_collate};";

		// 4. tarbwy_invitations (Stores SHA-256 token_hash only, never raw token)
		$sql[] = "CREATE TABLE {$prefix}tarbwy_invitations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			max_companions_override int(11) unsigned DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'active',
			expires_at datetime DEFAULT NULL,
			last_accessed_at datetime DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY event_guest (event_id, guest_id),
			KEY status (status)
		) ENGINE=InnoDB {$charset_collate};";

		// 5. tarbwy_tickets (Stores SHA-256 token_hash only, unique ticket_reference)
		$sql[] = "CREATE TABLE {$prefix}tarbwy_tickets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned NOT NULL,
			ticket_reference varchar(32) NOT NULL,
			token_hash char(64) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'active',
			issued_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			revoked_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ticket_reference (ticket_reference),
			UNIQUE KEY token_hash (token_hash),
			KEY event_guest_status (event_id, guest_id, status)
		) ENGINE=InnoDB {$charset_collate};";

		// 6. tarbwy_checkins (CRITICAL: UNIQUE KEY on ticket_id prevents duplicate check-in race conditions)
		$sql[] = "CREATE TABLE {$prefix}tarbwy_checkins (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned NOT NULL,
			ticket_id bigint(20) unsigned NOT NULL,
			checked_in_by bigint(20) unsigned DEFAULT NULL,
			method varchar(32) NOT NULL DEFAULT 'qr',
			device_label varchar(128) DEFAULT NULL,
			checked_in_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ticket_id (ticket_id),
			KEY event_guest (event_id, guest_id),
			KEY checked_in_at (checked_in_at)
		) ENGINE=InnoDB {$charset_collate};";

		// 7. tarbwy_email_logs
		$sql[] = "CREATE TABLE {$prefix}tarbwy_email_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			guest_id bigint(20) unsigned NOT NULL,
			template_key varchar(64) NOT NULL,
			recipient_hash char(64) NOT NULL,
			recipient_email varchar(191) NOT NULL,
			subject varchar(255) NOT NULL,
			payload_json longtext DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'queued',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			scheduled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			sent_at datetime DEFAULT NULL,
			last_error text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status_scheduled (status, scheduled_at),
			KEY event_guest_template (event_id, guest_id, template_key)
		) ENGINE=InnoDB {$charset_collate};";

		// 8. tarbwy_activity_log
		$sql[] = "CREATE TABLE {$prefix}tarbwy_activity_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			action varchar(64) NOT NULL,
			object_type varchar(64) NOT NULL,
			object_id bigint(20) unsigned DEFAULT NULL,
			metadata_json longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_action (event_id, action),
			KEY object_lookup (object_type, object_id),
			KEY created_at (created_at)
		) ENGINE=InnoDB {$charset_collate};";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Seed configurable initial event record without inventing unconfirmed dates/speakers
	 */
	public static function seed_default_event() {
		global $wpdb;
		$table = $wpdb->prefix . 'tarbwy_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_key = %s", 'tarbwy-launch-2026' ) );
		if ( $exists ) {
			return;
		}

		$default_settings = array(
			'subtitle'             => 'حفل الإشهار الرسمي وإطلاق المبادرات القيادية التربوية في العالم العربي',
			'about_event'          => 'يمثل حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية» محطة تأسيسية تجمع نخبة القيادات التربوية وصناع القرار والخبراء الأكاديميين لإطلاق منظومة عمل مؤسسية مشتركة تهدف إلى الارتقاء بالقيادة التعليمية في العالم العربي.',
			'about_tarbwy'         => '«تربوي — الاتحاد العربي للقيادة التربوية» مظلة مهنية ومؤسسية تعنى بتمكين القيادات التربوية، وتطوير السياسات التعليمية، وبناء شبكات التعاون المعرفي والمهني بين المؤسسات التربوية العربية.',
			'city_display'         => 'سيُعلن لاحقاً (قابل للتحرير من الإعدادات)',
			'date_display'         => 'سيُعلن لاحقاً (قابل للتحرير من الإعدادات)',
			'logo_url'             => '',
			'primary_color'        => '#14532D',
			'accent_color'         => '#9A3412',
			'show_schedule'        => true,
			'schedule_items'       => array(),
			'show_speakers'        => true,
			'speakers'             => array(),
			'show_sponsors'        => true,
			'sponsors'             => array(),
			'contact_email'        => 'events@tarbwy.org',
			'contact_phone'        => 'سيُعلن لاحقاً',
			'entry_instructions'   => 'يرجى إبراز رمز الاستجابة السريعة (QR) الخاص بك عند بوابة الاستقبال. هذه البطاقة شخصية ومخصصة لحاملها فقط، ولكل مرافق بطاقة مستقلة.',
			'form_fields'          => array(
				'require_organization' => true,
				'require_job_title'    => true,
				'enable_dietary'       => true,
				'enable_accessibility' => true,
			),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'event_key'              => 'tarbwy-launch-2026',
				'title'                  => 'حفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية»',
				'slug'                   => 'launch',
				'status'                 => 'published',
				'event_start_at'         => null,
				'event_end_at'           => null,
				'timezone'               => 'Asia/Riyadh',
				'venue_name'             => 'سيُعلن لاحقاً',
				'venue_address'          => 'سيتم تحديد العنوان التفصيلي ومعلومات الوصول من قِبل إدارة الحدث',
				'map_url'                => '',
				'capacity_total'         => 500,
				'max_companions_public'  => 2,
				'max_companions_vip'     => 3,
				'approval_auto_confirms' => 1,
				'registration_open'      => 1,
				'rsvp_deadline'          => null,
				'settings_json'          => wp_json_encode( $default_settings, JSON_UNESCAPED_UNICODE ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}
}
