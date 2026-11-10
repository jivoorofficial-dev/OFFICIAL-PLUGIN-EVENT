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
		try {
			self::create_tables();
			self::ensure_schema_columns();
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
		} catch ( \Throwable $e ) {
			error_log( 'Tarbwy Event Manager activation notice: ' . $e->getMessage() );
		}
	}

	/**
	 * Run on plugin deactivation (Never deletes event data)
	 */
	public static function deactivate() {
		try {
			wp_clear_scheduled_hook( 'tarbwy_em_process_email_queue' );
			wp_clear_scheduled_hook( 'tarbwy_em_send_event_reminders' );
			flush_rewrite_rules();
		} catch ( \Throwable $e ) {
			error_log( 'Tarbwy Event Manager deactivation notice: ' . $e->getMessage() );
		}
	}

	/**
	 * Check and run safe non-destructive migrations if version changed.
	 * Executes during 'init' hook where all WP core globals ($wpdb, $wp_roles, $wp_rewrite) are ready.
	 */
	public static function maybe_upgrade() {
		try {
			$installed_version = get_option( 'tarbwy_em_db_version', '0.0.0' );
			if ( version_compare( $installed_version, TARBWY_EM_DB_VERSION, '<' ) ) {
				self::create_tables();
				self::ensure_schema_columns();
				self::seed_default_event();
				self::heal_orphaned_registrations();
				Roles::register_roles_and_caps();
				EmailManager::seed_default_templates();

				if ( function_exists( 'flush_rewrite_rules' ) ) {
					FrontendRouter::register_rewrite_rules();
					flush_rewrite_rules();
				}

				update_option( 'tarbwy_em_db_version', TARBWY_EM_DB_VERSION );
			}
		} catch ( \Throwable $e ) {
			error_log( 'Tarbwy Event Manager upgrade notice: ' . $e->getMessage() );
		}
	}

	/**
	 * Safe non-destructive migration helper: verifies and adds any missing columns or tables
	 * on existing production installations without modifying or deleting existing records.
	 */
	public static function ensure_schema_columns() {
		global $wpdb;
		try {
			$prefix = $wpdb->prefix;

			$migrations = array(
				$prefix . 'tarbwy_registrations' => array(
					'primary_guest_id'  => "ALTER TABLE `{$prefix}tarbwy_registrations` ADD COLUMN primary_guest_id bigint(20) unsigned DEFAULT NULL AFTER reference_code",
					'review_status'     => "ALTER TABLE `{$prefix}tarbwy_registrations` ADD COLUMN review_status varchar(32) NOT NULL DEFAULT 'pending' AFTER primary_guest_id",
					'previous_status'   => "ALTER TABLE `{$prefix}tarbwy_registrations` ADD COLUMN previous_status varchar(32) DEFAULT NULL AFTER review_status",
					'reviewed_by'       => "ALTER TABLE `{$prefix}tarbwy_registrations` ADD COLUMN reviewed_by bigint(20) unsigned DEFAULT NULL AFTER previous_status",
					'reviewed_at'       => "ALTER TABLE `{$prefix}tarbwy_registrations` ADD COLUMN reviewed_at datetime DEFAULT NULL AFTER reviewed_by",
					'review_note'       => "ALTER TABLE `{$prefix}tarbwy_registrations` ADD COLUMN review_note text DEFAULT NULL AFTER reviewed_at",
					'applicant_payload' => "ALTER TABLE `{$prefix}tarbwy_registrations` ADD COLUMN applicant_payload longtext DEFAULT NULL AFTER review_note",
					'submitted_at'      => "ALTER TABLE `{$prefix}tarbwy_registrations` ADD COLUMN submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER applicant_payload",
				),
				$prefix . 'tarbwy_guests'        => array(
					'event_id'                   => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN event_id bigint(20) unsigned NOT NULL DEFAULT 1 AFTER id",
					'registration_id'            => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN registration_id bigint(20) unsigned DEFAULT NULL AFTER event_id",
					'parent_guest_id'            => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN parent_guest_id bigint(20) unsigned DEFAULT NULL AFTER registration_id",
					'guest_type'                 => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN guest_type varchar(32) NOT NULL DEFAULT 'public' AFTER parent_guest_id",
					'full_name'                  => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN full_name varchar(191) NOT NULL DEFAULT '' AFTER guest_type",
					'email'                      => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN email varchar(191) DEFAULT NULL AFTER full_name",
					'phone'                      => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN phone varchar(64) DEFAULT NULL AFTER email",
					'organization'               => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN organization varchar(255) DEFAULT NULL AFTER phone",
					'job_title'                  => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN job_title varchar(255) DEFAULT NULL AFTER organization",
					'rsvp_status'                => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN rsvp_status varchar(32) NOT NULL DEFAULT 'not_invited' AFTER job_title",
					'checkin_status'             => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN checkin_status varchar(32) NOT NULL DEFAULT 'not_checked_in' AFTER rsvp_status",
					'checked_in_at'              => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN checked_in_at datetime DEFAULT NULL AFTER checkin_status",
					'checked_in_by'              => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN checked_in_by bigint(20) unsigned DEFAULT NULL AFTER checked_in_at",
					'dietary_requirements'       => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN dietary_requirements varchar(255) DEFAULT NULL AFTER checked_in_by",
					'accessibility_requirements' => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN accessibility_requirements varchar(255) DEFAULT NULL AFTER dietary_requirements",
					'admin_notes'                => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN admin_notes text DEFAULT NULL AFTER accessibility_requirements",
					'created_at'                 => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
					'updated_at'                 => "ALTER TABLE `{$prefix}tarbwy_guests` ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
				),
				$prefix . 'tarbwy_invitations'   => array(
					'event_id'                => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN event_id bigint(20) unsigned NOT NULL DEFAULT 1 AFTER id",
					'guest_id'                => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN guest_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER event_id",
					'token_hash'              => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN token_hash char(64) NOT NULL DEFAULT '' AFTER guest_id",
					'token_preview'           => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN token_preview varchar(128) DEFAULT NULL AFTER token_hash",
					'max_companions_override' => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN max_companions_override int(11) unsigned DEFAULT 2 AFTER token_preview",
					'status'                  => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN status varchar(32) NOT NULL DEFAULT 'active' AFTER max_companions_override",
					'expires_at'              => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN expires_at datetime DEFAULT NULL AFTER status",
					'last_accessed_at'        => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN last_accessed_at datetime DEFAULT NULL AFTER expires_at",
					'sent_at'                 => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN sent_at datetime DEFAULT NULL AFTER last_accessed_at",
					'created_by'              => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN created_by bigint(20) unsigned DEFAULT NULL AFTER sent_at",
					'created_at'              => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
					'updated_at'              => "ALTER TABLE `{$prefix}tarbwy_invitations` ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
				),
				$prefix . 'tarbwy_tickets'       => array(
					'event_id'         => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN event_id bigint(20) unsigned NOT NULL DEFAULT 1 AFTER id",
					'guest_id'         => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN guest_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER event_id",
					'ticket_reference' => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN ticket_reference varchar(32) NOT NULL DEFAULT '' AFTER guest_id",
					'qr_payload'       => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN qr_payload varchar(128) DEFAULT NULL AFTER ticket_reference",
					'token_hash'       => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN token_hash char(64) NOT NULL DEFAULT '' AFTER qr_payload",
					'token_preview'    => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN token_preview varchar(128) DEFAULT NULL AFTER token_hash",
					'status'           => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN status varchar(32) NOT NULL DEFAULT 'active' AFTER token_preview",
					'issued_at'        => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN issued_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status",
					'revoked_at'       => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN revoked_at datetime DEFAULT NULL AFTER issued_at",
					'created_at'       => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
					'updated_at'       => "ALTER TABLE `{$prefix}tarbwy_tickets` ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
				),
				$prefix . 'tarbwy_email_logs'    => array(
					'recipient_email' => "ALTER TABLE `{$prefix}tarbwy_email_logs` ADD COLUMN recipient_email varchar(191) NOT NULL DEFAULT '' AFTER recipient_hash",
					'subject'         => "ALTER TABLE `{$prefix}tarbwy_email_logs` ADD COLUMN subject varchar(255) NOT NULL DEFAULT '' AFTER recipient_email",
					'payload_json'    => "ALTER TABLE `{$prefix}tarbwy_email_logs` ADD COLUMN payload_json longtext DEFAULT NULL AFTER subject",
					'attempts'        => "ALTER TABLE `{$prefix}tarbwy_email_logs` ADD COLUMN attempts tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER status",
					'scheduled_at'    => "ALTER TABLE `{$prefix}tarbwy_email_logs` ADD COLUMN scheduled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER attempts",
					'sent_at'         => "ALTER TABLE `{$prefix}tarbwy_email_logs` ADD COLUMN sent_at datetime DEFAULT NULL AFTER scheduled_at",
					'last_error'      => "ALTER TABLE `{$prefix}tarbwy_email_logs` ADD COLUMN last_error text DEFAULT NULL AFTER sent_at",
				),
			);

			$prev_suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : false;

			foreach ( $migrations as $table_name => $columns ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
				if ( ! $table_exists ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cols_full = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_name}`" );
				$existing_cols = array();
				if ( is_array( $cols_full ) ) {
					foreach ( $cols_full as $c_obj ) {
						if ( isset( $c_obj->Field ) ) {
							$col_lower                   = strtolower( (string) $c_obj->Field );
							$existing_cols[ $col_lower ] = $c_obj;
						}
					}
				}

				foreach ( $columns as $col_name => $alter_sql ) {
					if ( ! isset( $existing_cols[ strtolower( $col_name ) ] ) ) {
						try {
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
							$wpdb->query( $alter_sql );
						} catch ( \Throwable $alter_e ) {
							// Ignored safely if column already exists
						}
					}
				}

				// Relax any legacy NOT NULL columns that have no default value so STRICT_TRANS_TABLES never blocks INSERTs
				$known_lower = array_merge( array( 'id' ), array_map( 'strtolower', array_keys( $columns ) ) );
				foreach ( $existing_cols as $col_lower => $c_info ) {
					$is_nullable = isset( $c_info->Null ) && 'YES' === strtoupper( (string) $c_info->Null );
					$has_default = isset( $c_info->Default ) && null !== $c_info->Default;
					$is_auto     = isset( $c_info->Extra ) && false !== stripos( (string) $c_info->Extra, 'auto_increment' );
					$field_safe  = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $c_info->Field );
					$type_safe   = ! empty( $c_info->Type ) ? (string) $c_info->Type : 'varchar(255)';

					if ( ! $is_auto && $field_safe && ( in_array( $col_lower, array( 'registration_id', 'parent_guest_id', 'primary_guest_id', 'email', 'phone', 'organization', 'job_title', 'dietary_requirements', 'accessibility_requirements', 'admin_notes', 'payload_json', 'qr_payload', 'token_preview', 'revoked_at' ), true ) || ( ! in_array( $col_lower, $known_lower, true ) && ! $is_nullable && ! $has_default ) ) ) {
						if ( ! $is_nullable ) {
							try {
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY COLUMN `{$field_safe}` {$type_safe} NULL DEFAULT NULL" );
							} catch ( \Throwable $mod_e ) {
								// Ignore if not supported
							}
						}
					}
				}
			}

			// Backfill qr_payload on tarbwy_tickets so every stored ticket has a consistent canonical qr_payload
			$tickets_table = $prefix . 'tarbwy_tickets';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tickets_table ) ) ) {
				try {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( "UPDATE `{$tickets_table}` SET qr_payload = UPPER(TRIM(ticket_reference)) WHERE (qr_payload IS NULL OR qr_payload = '' OR qr_payload != UPPER(TRIM(ticket_reference))) AND ticket_reference != ''" );
				} catch ( \Throwable $tkt_e ) {
					// Ignore
				}
			}

			// Ensure tarbwy_events has approval_auto_confirms = 1 so approving a registration always confirms and issues QR
			$events_table = $prefix . 'tarbwy_events';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) ) ) {
				try {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( "UPDATE `{$events_table}` SET approval_auto_confirms = 1 WHERE approval_auto_confirms = 0 OR approval_auto_confirms IS NULL" );
				} catch ( \Throwable $ev_e ) {
					// Ignore
				}
			}

			// Ensure tarbwy_guests has NO unique index on email or phone (Spec Section 7: email is intentionally not unique globally)
			$guests_table = $prefix . 'tarbwy_guests';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $guests_table ) ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$unique_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$guests_table}` WHERE Non_unique = 0 AND Key_name != 'PRIMARY'" );
				if ( is_array( $unique_indexes ) ) {
					$dropped_keys = array();
					foreach ( $unique_indexes as $idx_row ) {
						$kname = isset( $idx_row->Key_name ) ? preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $idx_row->Key_name ) : '';
						if ( $kname && ! in_array( $kname, $dropped_keys, true ) ) {
							$dropped_keys[] = $kname;
							try {
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$wpdb->query( "ALTER TABLE `{$guests_table}` DROP INDEX `{$kname}`" );
							} catch ( \Throwable $idx_e ) {
								// Ignore
							}
						}
					}
				}
			}

			if ( method_exists( $wpdb, 'suppress_errors' ) ) {
				$wpdb->suppress_errors( $prev_suppress );
			}
		} catch ( \Throwable $e ) {
			error_log( 'Tarbwy Event Manager schema migration notice: ' . $e->getMessage() );
		}
	}

	/**
	 * Resilient Guest Insertion Helper:
	 * - Strips null fields so WordPress never converts NULL to 0 via %d format.
	 * - Automatically self-heals tables/columns and disables STRICT_TRANS_TABLES if initial insert fails.
	 *
	 * @param array $guest_data Associative array of guest columns.
	 * @return int Inserted guest ID or 0 on failure.
	 */
	public static function safe_insert_guest( array $guest_data ) {
		global $wpdb;
		$guests_table = $wpdb->prefix . 'tarbwy_guests';

		// Remove null values so MySQL uses DEFAULT NULL natively
		$clean_data = array();
		foreach ( $guest_data as $k => $v ) {
			if ( null !== $v ) {
				$clean_data[ $k ] = $v;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $guests_table, $clean_data );
		if ( $ok && (int) $wpdb->insert_id > 0 ) {
			return (int) $wpdb->insert_id;
		}

		// Self-heal schema and retry with non-strict SQL mode
		self::create_tables();
		self::ensure_schema_columns();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "SET SESSION sql_mode = ''" );

		// Filter against actual table columns in case DB user lacked ALTER permission on a specific column
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$actual_cols = $wpdb->get_col( "DESCRIBE `{$guests_table}`" );
		if ( is_array( $actual_cols ) && ! empty( $actual_cols ) ) {
			$actual_lower  = array_map( 'strtolower', $actual_cols );
			$filtered_data = array();
			foreach ( $clean_data as $k => $v ) {
				if ( in_array( strtolower( $k ), $actual_lower, true ) ) {
					$filtered_data[ $k ] = $v;
				}
			}
			if ( ! empty( $filtered_data ) ) {
				$clean_data = $filtered_data;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $guests_table, $clean_data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Resilient VIP Invitation Insertion Helper:
	 * Ensures tarbwy_invitations table and columns exist and inserts invitation record reliably.
	 *
	 * @param array $inv_data Associative array of invitation columns.
	 * @return int Inserted invitation ID or 0 on failure.
	 */
	public static function safe_insert_invitation( array $inv_data ) {
		global $wpdb;
		$inv_table = $wpdb->prefix . 'tarbwy_invitations';

		$clean_data = array();
		foreach ( $inv_data as $k => $v ) {
			if ( null !== $v ) {
				$clean_data[ $k ] = $v;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $inv_table, $clean_data );
		if ( $ok && (int) $wpdb->insert_id > 0 ) {
			return (int) $wpdb->insert_id;
		}

		self::create_tables();
		self::ensure_schema_columns();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "SET SESSION sql_mode = ''" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$actual_cols = $wpdb->get_col( "DESCRIBE `{$inv_table}`" );
		if ( is_array( $actual_cols ) && ! empty( $actual_cols ) ) {
			$actual_lower  = array_map( 'strtolower', $actual_cols );
			$filtered_data = array();
			foreach ( $clean_data as $k => $v ) {
				if ( in_array( strtolower( $k ), $actual_lower, true ) ) {
					$filtered_data[ $k ] = $v;
				}
			}
			if ( ! empty( $filtered_data ) ) {
				$clean_data = $filtered_data;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $inv_table, $clean_data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Resilient Ticket Insertion Helper:
	 * Ensures tarbwy_tickets table and columns exist and inserts ticket record reliably.
	 *
	 * @param array $ticket_data Associative array of ticket columns.
	 * @return int Inserted ticket ID or 0 on failure.
	 */
	public static function safe_insert_ticket( array $ticket_data ) {
		global $wpdb;
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';

		$clean_data = array();
		foreach ( $ticket_data as $k => $v ) {
			if ( null !== $v ) {
				$clean_data[ $k ] = $v;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $tickets_table, $clean_data );
		if ( $ok && (int) $wpdb->insert_id > 0 ) {
			return (int) $wpdb->insert_id;
		}

		self::create_tables();
		self::ensure_schema_columns();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "SET SESSION sql_mode = ''" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$actual_cols = $wpdb->get_col( "DESCRIBE `{$tickets_table}`" );
		if ( is_array( $actual_cols ) && ! empty( $actual_cols ) ) {
			$actual_lower  = array_map( 'strtolower', $actual_cols );
			$filtered_data = array();
			foreach ( $clean_data as $k => $v ) {
				if ( in_array( strtolower( $k ), $actual_lower, true ) ) {
					$filtered_data[ $k ] = $v;
				}
			}
			if ( ! empty( $filtered_data ) ) {
				$clean_data = $filtered_data;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $tickets_table, $clean_data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Auto-heal any registration rows where primary_guest_id is missing or 0,
	 * heal approved registrations that were stuck in pending_response without QR tickets,
	 * ensure all confirmed guests have an active/used ticket, and clean up accidental duplicate VIP invitations.
	 */
	public static function heal_orphaned_registrations() {
		global $wpdb;
		try {
			$reg_table     = $wpdb->prefix . 'tarbwy_registrations';
			$guests_table  = $wpdb->prefix . 'tarbwy_guests';
			$mail_table    = $wpdb->prefix . 'tarbwy_email_logs';
			$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
			$inv_table     = $wpdb->prefix . 'tarbwy_invitations';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$orphans = $wpdb->get_results(
				"SELECT r.* FROM `{$reg_table}` r
				 LEFT JOIN `{$guests_table}` g ON r.primary_guest_id = g.id
				 WHERE r.primary_guest_id IS NULL OR r.primary_guest_id = 0 OR g.id IS NULL
				 LIMIT 100"
			);

			if ( ! empty( $orphans ) && is_array( $orphans ) ) {
				foreach ( $orphans as $reg ) {
					$rid = (int) $reg->id;
					$eid = (int) $reg->event_id;

					// 1. Check if a guest row already exists with registration_id = $rid
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$existing_gid = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM `{$guests_table}` WHERE registration_id = %d ORDER BY CASE WHEN parent_guest_id IS NULL OR parent_guest_id = 0 THEN 0 ELSE 1 END ASC, id ASC LIMIT 1",
							$rid
						)
					);

					if ( $existing_gid > 0 ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->update(
							$reg_table,
							array( 'primary_guest_id' => $existing_gid ),
							array( 'id' => $rid ),
							array( '%d' ),
							array( '%d' )
						);
						continue;
					}

					// 2. Check if applicant_payload JSON exists on registration row
					if ( ! empty( $reg->applicant_payload ) ) {
						$payload = json_decode( (string) $reg->applicant_payload, true );
						if ( is_array( $payload ) && ! empty( $payload['full_name'] ) ) {
							$new_gid = self::safe_insert_guest(
								array(
									'event_id'                   => $eid,
									'registration_id'            => $rid,
									'guest_type'                 => 'public',
									'full_name'                  => sanitize_text_field( $payload['full_name'] ),
									'email'                      => sanitize_email( $payload['email'] ?? '' ),
									'phone'                      => sanitize_text_field( $payload['phone'] ?? '' ),
									'organization'               => sanitize_text_field( $payload['organization'] ?? '' ),
									'job_title'                  => sanitize_text_field( $payload['job_title'] ?? '' ),
									'rsvp_status'                => 'approved' === $reg->review_status ? 'confirmed' : 'not_invited',
									'checkin_status'             => 'not_checked_in',
									'dietary_requirements'       => sanitize_text_field( $payload['dietary_requirements'] ?? '' ),
									'accessibility_requirements' => sanitize_text_field( $payload['accessibility_requirements'] ?? '' ),
									'created_at'                 => $reg->submitted_at ? $reg->submitted_at : current_time( 'mysql' ),
									'updated_at'                 => current_time( 'mysql' ),
								)
							);
							if ( $new_gid > 0 ) {
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->update(
									$reg_table,
									array( 'primary_guest_id' => $new_gid ),
									array( 'id' => $rid ),
									array( '%d' ),
									array( '%d' )
								);
								if ( ! empty( $payload['companions'] ) && is_array( $payload['companions'] ) ) {
									foreach ( $payload['companions'] as $comp ) {
										$cname = sanitize_text_field( $comp['full_name'] ?? '' );
										if ( '' !== $cname ) {
											self::safe_insert_guest(
												array(
													'event_id'        => $eid,
													'registration_id' => $rid,
													'parent_guest_id' => $new_gid,
													'guest_type'      => 'companion',
													'full_name'       => $cname,
													'email'           => sanitize_email( $comp['email'] ?? '' ),
													'phone'           => sanitize_text_field( $comp['phone'] ?? '' ),
													'organization'    => sanitize_text_field( $payload['organization'] ?? '' ),
													'job_title'       => sanitize_text_field( $comp['job_title'] ?? 'مرافق' ),
													'rsvp_status'     => 'approved' === $reg->review_status ? 'confirmed' : 'not_invited',
													'checkin_status'  => 'not_checked_in',
													'created_at'      => $reg->submitted_at ? $reg->submitted_at : current_time( 'mysql' ),
													'updated_at'      => current_time( 'mysql' ),
												)
											);
										}
									}
								}
								continue;
							}
						}
					}

					// 3. Check if tarbwy_email_logs has a payload_json referencing this reference_code
					if ( ! empty( $reg->reference_code ) ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$mail_row = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT recipient_email, payload_json FROM `{$mail_table}` WHERE event_id = %d AND payload_json LIKE %s ORDER BY id ASC LIMIT 1",
								$eid,
								'%' . $wpdb->esc_like( (string) $reg->reference_code ) . '%'
							)
						);
						if ( $mail_row && ! empty( $mail_row->payload_json ) ) {
							$m_data = json_decode( (string) $mail_row->payload_json, true );
							if ( is_array( $m_data ) && ! empty( $m_data['guest_name'] ) ) {
								$new_gid = self::safe_insert_guest(
									array(
										'event_id'        => $eid,
										'registration_id' => $rid,
										'guest_type'      => 'public',
										'full_name'       => sanitize_text_field( $m_data['guest_name'] ),
										'email'           => sanitize_email( $mail_row->recipient_email ),
										'phone'           => sanitize_text_field( $m_data['phone'] ?? '' ),
										'organization'    => sanitize_text_field( $m_data['organization'] ?? '' ),
										'job_title'       => sanitize_text_field( $m_data['job_title'] ?? '' ),
										'rsvp_status'     => 'approved' === $reg->review_status ? 'confirmed' : 'not_invited',
										'checkin_status'  => 'not_checked_in',
										'created_at'      => $reg->submitted_at ? $reg->submitted_at : current_time( 'mysql' ),
										'updated_at'      => current_time( 'mysql' ),
									)
								);
								if ( $new_gid > 0 ) {
									// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->update(
										$reg_table,
										array( 'primary_guest_id' => $new_gid ),
										array( 'id' => $rid ),
										array( '%d' ),
										array( '%d' )
									);
								}
							}
						}
					}
				}
			}

			// 4. Heal approved registrations whose primary guest or companions were stuck in pending_response / not_invited without QR tickets
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$approved_regs = $wpdb->get_results(
				"SELECT id, event_id, primary_guest_id FROM `{$reg_table}` WHERE review_status = 'approved' LIMIT 200"
			);
			if ( ! empty( $approved_regs ) && is_array( $approved_regs ) ) {
				foreach ( $approved_regs as $areg ) {
					$arid = (int) $areg->id;
					$aeid = (int) $areg->event_id;
					$pgid = (int) $areg->primary_guest_id;

					// Ensure primary guest has registration_id linked
					if ( $pgid > 0 ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->query(
							$wpdb->prepare(
								"UPDATE `{$guests_table}` SET registration_id = %d WHERE id = %d AND (registration_id IS NULL OR registration_id = 0)",
								$arid,
								$pgid
							)
						);
					}

					// Confirm any non-declined guests belonging to this approved registration
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE `{$guests_table}` SET rsvp_status = 'confirmed' WHERE event_id = %d AND (registration_id = %d OR id = %d) AND rsvp_status != 'declined' AND rsvp_status != 'confirmed'",
							$aeid,
							$arid,
							$pgid
						)
					);
				}
			}

			// 5. Ensure every confirmed guest (public, VIP, companion) has an active or used ticket in tarbwy_tickets
			if ( class_exists( __NAMESPACE__ . '\\QRGenerator' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$confirmed_without_ticket = $wpdb->get_results(
					"SELECT g.id, g.event_id, g.checkin_status
					 FROM `{$guests_table}` g
					 LEFT JOIN `{$tickets_table}` t ON t.guest_id = g.id AND t.status IN ('active', 'used')
					 WHERE g.rsvp_status = 'confirmed' AND t.id IS NULL
					 LIMIT 200"
				);
				if ( ! empty( $confirmed_without_ticket ) && is_array( $confirmed_without_ticket ) ) {
					foreach ( $confirmed_without_ticket as $cg ) {
						$issued = QRGenerator::issue_ticket_for_guest( (int) $cg->event_id, (int) $cg->id, false );
						if ( ! is_wp_error( $issued ) && 'checked_in' === $cg->checkin_status && ! empty( $issued['ticket_id'] ) ) {
							// Mark ticket as 'used' if guest already checked in
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->update(
								$tickets_table,
								array( 'status' => 'used' ),
								array( 'id' => (int) $issued['ticket_id'] ),
								array( '%s' ),
								array( '%d' )
							);
						}
					}
				}
			}

			// 6. Clean up accidental duplicate VIP guest rows caused by double-clicking the VIP creation form
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$vip_guests = $wpdb->get_results(
				"SELECT id, event_id, full_name, email, rsvp_status, checkin_status
				 FROM `{$guests_table}`
				 WHERE guest_type = 'vip' AND email IS NOT NULL AND email != ''
				 ORDER BY CASE WHEN checkin_status = 'checked_in' THEN 0 WHEN rsvp_status = 'confirmed' THEN 1 ELSE 2 END ASC, id DESC"
			);
			if ( ! empty( $vip_guests ) && is_array( $vip_guests ) ) {
				$seen_vip_keys = array();
				foreach ( $vip_guests as $vg ) {
					$vkey = (int) $vg->event_id . '|' . strtolower( trim( (string) $vg->email ) ) . '|' . strtolower( trim( (string) $vg->full_name ) );
					if ( ! isset( $seen_vip_keys[ $vkey ] ) ) {
						$seen_vip_keys[ $vkey ] = (int) $vg->id;
					} else {
						// Only remove the duplicate if it is still unconfirmed (pending_response / not_invited) and not checked in
						if ( 'checked_in' !== $vg->checkin_status && in_array( $vg->rsvp_status, array( 'pending_response', 'not_invited' ), true ) ) {
							$dup_id = (int) $vg->id;
							// Re-parent any companions just in case
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->update(
								$guests_table,
								array( 'parent_guest_id' => $seen_vip_keys[ $vkey ] ),
								array( 'parent_guest_id' => $dup_id ),
								array( '%d' ),
								array( '%d' )
							);
							// Delete duplicate invitations and guest record
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->delete( $inv_table, array( 'guest_id' => $dup_id ), array( '%d' ) );
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->delete( $guests_table, array( 'id' => $dup_id ), array( '%d' ) );
						}
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Ignore healing errors safely
		}
	}

	/**
	 * Create all 8 custom InnoDB tables with proper indexes and UNIQUE constraints
	 */
	public static function create_tables() {
		global $wpdb;

		try {
			if ( ! function_exists( 'dbDelta' ) ) {
				if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				}
			}

			$prev_suppress   = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : false;
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
				previous_status varchar(32) DEFAULT NULL,
				reviewed_by bigint(20) unsigned DEFAULT NULL,
				reviewed_at datetime DEFAULT NULL,
				review_note text DEFAULT NULL,
				applicant_payload longtext DEFAULT NULL,
				submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY reference_code (reference_code),
				KEY event_id_status (event_id, review_status),
				KEY primary_guest_id (primary_guest_id),
				KEY submitted_at (submitted_at)
			) ENGINE=InnoDB {$charset_collate};";

			// 3. tarbwy_guests (Email is intentionally NOT unique globally per spec)
			$sql[] = "CREATE TABLE {$prefix}tarbwy_guests (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_id bigint(20) unsigned NOT NULL,
				registration_id bigint(20) unsigned DEFAULT NULL,
				parent_guest_id bigint(20) unsigned DEFAULT NULL,
				guest_type varchar(32) NOT NULL DEFAULT 'public',
				full_name varchar(191) NOT NULL,
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
				KEY phone_lookup (phone),
				KEY full_name_lookup (full_name)
			) ENGINE=InnoDB {$charset_collate};";

			// 4. tarbwy_invitations (Stores SHA-256 token_hash + token_preview for idempotent resend)
			$sql[] = "CREATE TABLE {$prefix}tarbwy_invitations (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_id bigint(20) unsigned NOT NULL,
				guest_id bigint(20) unsigned NOT NULL,
				token_hash char(64) NOT NULL,
				token_preview varchar(128) DEFAULT NULL,
				max_companions_override int(11) unsigned DEFAULT 2,
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

			// 5. tarbwy_tickets (Stores SHA-256 token_hash, unique ticket_reference, and canonical qr_payload)
			$sql[] = "CREATE TABLE {$prefix}tarbwy_tickets (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_id bigint(20) unsigned NOT NULL,
				guest_id bigint(20) unsigned NOT NULL,
				ticket_reference varchar(32) NOT NULL,
				qr_payload varchar(128) DEFAULT NULL,
				token_hash char(64) NOT NULL,
				token_preview varchar(128) DEFAULT NULL,
				status varchar(32) NOT NULL DEFAULT 'active',
				issued_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				revoked_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY ticket_reference (ticket_reference),
				UNIQUE KEY token_hash (token_hash),
				KEY qr_payload_lookup (qr_payload),
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
				try {
					$if_not_exists_sql = preg_replace( '/^CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', trim( $query ) );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
					$wpdb->query( $if_not_exists_sql );
				} catch ( \Throwable $direct_e ) {
					// Continue to dbDelta
				}

				if ( function_exists( 'dbDelta' ) ) {
					try {
						dbDelta( $query );
					} catch ( \Throwable $delta_e ) {
						// Safely ignore if table or index already created
					}
				}
			}

			if ( method_exists( $wpdb, 'suppress_errors' ) ) {
				$wpdb->suppress_errors( $prev_suppress );
			}
		} catch ( \Throwable $e ) {
			error_log( 'Tarbwy Event Manager table creation notice: ' . $e->getMessage() );
		}
	}

	/**
	 * Seed configurable initial event record without inventing unconfirmed dates/speakers
	 */
	public static function seed_default_event() {
		global $wpdb;
		try {
			$table = $wpdb->prefix . 'tarbwy_events';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_key = %s", 'tarbwy-launch-2026' ) );
			if ( $exists ) {
				return;
			}

		$default_settings = array(
			'subtitle'             => 'الواجهة الرقمية الرسمية لحفل إشهار «تربوي» وإطلاق المساحة المؤسسية المشتركة للقيادة التربوية في العالم العربي',
			'about_event'          => 'يمثل حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية» محطة تأسيسية جامعة لنخبة القيادات التربوية وصناع القرار والشخصيات المرجعية والخبراء؛ بهدف الإعلان الرسمي عن انطلاق الاتحاد، واستعراض رؤيته التأسيسية، وتفعيل مسارات العمل المشترك للارتقاء بالقيادة التربوية العربية.',
			'about_tarbwy'         => '«تربوي — الاتحاد العربي للقيادة التربوية» إطار مؤسسي ومهني عربي يُعنى بتمكين القيادات التربوية، وتطوير الفكر والممارسة القيادية في المؤسسات التعليمية، وبناء منظومة تشاركية رصينة تجمع القادة والخبراء التربويين في الوطن العربي.',
			'why_tarbwy'           => 'أُطلق «تربوي» استجابةً لحاجة المشهد التربوي العربي إلى مظلة مؤسسية جامعة توحّد الجهود القيادية المبعثرة، وتنقل المبادرات التربوية من الأطر الفردية المحدودة إلى عمل مؤسسي مستدام يواكب التحولات التعليمية المعاصرة.',
			'tarbwy_space'         => 'يسعى «تربوي» إلى بناء مساحة عربية مهنية ومعرفية مشتركة تربط القيادات التربوية وصناع السياسات والباحثين، وتتيح تبادل الخبرات النوعية، وتطوير المعايير القيادية، وإطلاق البرامج والمبادرات العابرة للقطاعات التعليمية.',
			'city_display'         => 'سيُعلن لاحقاً فور الاعتماد النهائي',
			'date_display'         => 'سيُعلن لاحقاً فور الاعتماد النهائي',
			'access_info'          => 'سيتم نشر إرشادات الوصول وبوابات الدخول ومواقف الاصطفاف المخصصة للضيوف فور اعتماد القاعة النهائية.',
			'logo_url'             => '',
			'primary_color'        => '#14532D',
			'accent_color'         => '#9A3412',
			'show_schedule'        => true,
			'schedule_items'       => array(),
			'show_speakers'        => true,
			'speakers'             => array(),
			'show_sponsors'        => true,
			'sponsors'             => array(),
			'sender_name'          => 'Tarbwy',
			'sender_email'         => 'noreply@tarbwy.org',
			'reply_to_email'       => 'events@tarbwy.org',
			'contact_email'        => 'events@tarbwy.org',
			'contact_phone'        => 'سيُعلن لاحقاً',
			'entry_instructions'   => 'الدخول يوم الحفل يتم حصرياً عبر إبراز بطاقة الدخول الشخصية المزودة برمز QR الفريد (عبر الهاتف أو مطبوعة) عند بوابة الاستقبال. يُسجل طلب الحضور العام أولاً بحالة (قيد المراجعة / Pending)، وتصدر بطاقة الدخول بعد اعتماد الطلب فقط.',
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
				'title'                  => 'حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية»',
				'slug'                   => 'launch',
				'status'                 => 'published',
				'event_start_at'         => null,
				'event_end_at'           => null,
				'timezone'               => 'Asia/Riyadh',
				'venue_name'             => 'سيُعلن لاحقاً',
				'venue_address'          => 'سيتم تحديد اسم المكان والعنوان التفصيلي ورابط الخريطة فور الاعتماد النهائي من قِبل إدارة الحدث',
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
	} catch ( \Throwable $e ) {
		error_log( 'Tarbwy Event Manager default event seeding notice: ' . $e->getMessage() );
	}
}

}


