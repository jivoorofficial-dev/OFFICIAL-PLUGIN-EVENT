<?php
/**
 * Email Automation, RTL Arabic Templates, Queue, Retries with Backoff, and Cron Dispatcher
 *
 * Uses wp_mail() for compatibility with any client-configured SMTP plugin/provider.
 * Note: Acceptance by wp_mail() indicates dispatch handoff to the mail transport layer, not guaranteed inbox placement.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailManager {

	const MAX_ATTEMPTS = 3;

	/**
	 * Default Arabic RTL Email Templates with safe placeholders
	 */
	public static function get_default_templates() {
		return array(
			'public_registration_received' => array(
				'title'   => 'استلام طلب حضور عام (قيد المراجعة)',
				'subject' => 'استلام طلب حضوركم: {event_title} — رقم الطلب {reference_code}',
				'body'    => "مرحباً {guest_name}،\n\nنشكر لكم اهتمامكم بحضور «{event_title}».\nلقد تم استلام طلب التسجيل الخاص بكم برقم مرجعي ({reference_code}) وهو حالياً قيد المراجعة من قِبل اللجنة المنظمة.\n\nيرجى ملاحظة أن هذه الرسالة تفيد باستلام الطلب فقط ولا تُعد تأكيداً نهائياً للحضور. سنوافيكم برسالة أخرى فور اعتماد الطلب وإصدار بطاقة الدخول الشخصية (QR).\n\nمع خالص التقدير،\nفريق {event_title}",
			),
			'registration_approved'        => array(
				'title'   => 'اعتماد وتأكيد طلب الحضور وإصدار البطاقة',
				'subject' => 'تأكيد حضوركم وبطاقة الدخول: {event_title}',
				'body'    => "مرحباً {guest_name}،\n\nيسرنا إبلاغكم بالموافقة على طلب حضوركم في «{event_title}» وتأكيد تسجيلكم.\n\nتفاصيل الحدث:\n- الموعد: {event_date}\n- المكان: {venue_name}\n- رقم التذكرة: {ticket_reference}\n\nيمكنكم عرض وطباعة بطاقة الدخول الشخصية المحتوية على رمز QR عبر الرابط الآمن التالي:\n{ticket_url}\n\nملاحظة هامة: بطاقة الدخول شخصية ومستقلة لكل مشارك، ويرجى إبراز رمز QR عند بوابة الاستقبال.\n\nنسعد بلقائكم،\nفريق {event_title}",
			),
			'registration_waitlist'        => array(
				'title'   => 'إدراج الطلب في قائمة الانتظار (Waitlist)',
				'subject' => 'تحديث بشأن طلب حضوركم: {event_title}',
				'body'    => "مرحباً {guest_name}،\n\nنشكركم على طلب حضور «{event_title}» (رقم الطلب: {reference_code}).\nنظراً لمحدودية المقاعد المخصصة للقاعة، تم إدراج طلبكم في قائمة الانتظار ذات الأولوية. سنتواصل معكم فوراً وإصدار بطاقة الدخول في حال توفر مقعد شاغر.\n\nمع وافر الاحترام،\nفريق {event_title}",
			),
			'registration_declined'        => array(
				'title'   => 'الاعتذار عن عدم قبول طلب التسجيل',
				'subject' => 'بشأن طلب التسجيل في {event_title}',
				'body'    => "مرحباً {guest_name}،\n\nنشكركم على اهتمامكم بحضور «{event_title}».\nنعتذر عن عدم إمكانية تلبية طلب الحضور في هذه الفعالية نظراً لاكتمال العدد المخصص وفق الطاقة الاستيعابية للقاعة، ونتطلع للتشرف بحضوركم في الفعاليات التربوية القادمة.\n\nمع خالص التقدير،\nفريق {event_title}",
			),
			'vip_invitation'               => array(
				'title'   => 'دعوة خاصة لكبار الشخصيات (VIP)',
				'subject' => 'دعوة خاصة لحضور {event_title}',
				'body'    => "سعادة الأستاذ/الدكتور {guest_name} حفظه الله،\n\nيتشرف «تربوي — الاتحاد العربي للقيادة التربوية» بدعوتكم لحضور «{event_title}».\n\nتفاصيل الحفل:\n- التاريخ والوقت: {event_date}\n- المكان: {venue_name}\n\nللتفضل بتأكيد الحضور أو الاعتذار (وإضافة بيانات المرافقين إن وجدوا)، يرجى زيارة الرابط الشخصي الآمن الخاص بكم:\n{vip_url}\n\nنأمل التكرم بالرد قبل تاريخ {rsvp_deadline}.\n\nوتفضلوا بقبول فائق الاحترام والتقدير،\nالأمانة العامة — تربوي",
			),
			'vip_rsvp_confirmed'           => array(
				'title'   => 'تأكيد حضور VIP وإصدار البطاقة',
				'subject' => 'تأكيد تشريفكم وبطاقة الدخول الخاصة: {event_title}',
				'body'    => "سعادة الأستاذ/الدكتور {guest_name} حفظه الله،\n\nنسعد بتأكيد حضوركم في «{event_title}».\n\nمرفق أدناه رابط بطاقة الدخول الشخصية الخاصة بحاملها ({guest_name}) برقم مرجعي ({ticket_reference}):\n{ticket_url}\n\nتفاصيل الموعد والمكان:\n- التاريخ: {event_date}\n- المكان: {venue_name}\n\n(ملاحظة: في حال تسجيل مرافقين، تصدر لكل مرافق بطاقة دخول مستقلة برمز QR خاص به).\n\nمرحباً بكم،\nالأمانة العامة — تربوي",
			),
			'vip_rsvp_declined'            => array(
				'title'   => 'استلام اعتذار VIP عن الحضور',
				'subject' => 'استلام اعتذاركم عن حضور {event_title}',
				'body'    => "سعادة الأستاذ/الدكتور {guest_name} حفظه الله،\n\nتلقينا بالتقدير ردكم والاعتذار عن عدم التمكن من حضور «{event_title}»، ونتطلع للتشرف بلقائكم في مناسبات الاتحاد القادمة.\n\nوتفضلوا بقبول فائق الاحترام،\nالأمانة العامة — تربوي",
			),
			'ticket_issued'                => array(
				'title'   => 'إصدار / إعادة إصدار بطاقة الدخول (QR)',
				'subject' => 'بطاقة الدخول الخاصة بكم: {event_title} ({guest_name})',
				'body'    => "مرحباً،\n\nهذه رسالة تحتوي على بطاقة الدخول الرسمية المعتمدة لحامل البطاقة:\nالاسم: {guest_name}\nرقم التذكرة: {ticket_reference}\nالحدث: {event_title}\nالتاريخ: {event_date}\nالمكان: {venue_name}\n\nرابط عرض وطباعة البطاقة ورمز QR:\n{ticket_url}\n\nيرجى إبراز هذه البطاقة عند بوابة التسجيل يوم الحدث.",
			),
			'event_reminder'               => array(
				'title'   => 'تذكير قبل موعد الحدث للمؤكدين فقط',
				'subject' => 'تذكير بموعد {event_title} وبطاقة الدخول',
				'body'    => "مرحباً {guest_name}،\n\nيسعدنا تذكيركم باقتراب موعد «{event_title}».\n- التاريخ والوقت: {event_date}\n- المكان: {venue_name}\n\nللدخول السريع يوم الفعالية، يرجى التأكد من جاهزية بطاقة الدخول الخاصة بكم ({ticket_reference}) عبر الرابط:\n{ticket_url}\n\nنتطلع للقائكم،\nفريق {event_title}",
			),
			'post_event_thank_you'         => array(
				'title'   => 'رسالة شكر بعد انتهاء الحدث',
				'subject' => 'شكراً لتشريفكم حفل إطلاق {event_title}',
				'body'    => "سعادة المشارك الكريم {guest_name}،\n\nيتقدم «تربوي — الاتحاد العربي للقيادة التربوية» بخالص الشكر والتقدير لحضوركم ومشاركتكم الفاعلة في «{event_title}».\nإن حضوركم يمثل دعماً حقيقياً لمسيرة القيادة التربوية في عالمنا العربي.\n\nمع خالص التحية والتقدير،\nالأمانة العامة — تربوي",
			),
		);
	}

	/**
	 * Seed default email templates into wp_options if not present
	 */
	public static function seed_default_templates() {
		$existing = get_option( 'tarbwy_em_email_templates' );
		if ( empty( $existing ) || ! is_array( $existing ) ) {
			update_option( 'tarbwy_em_email_templates', self::get_default_templates() );
		}
	}

	/**
	 * Get templates (merged with defaults)
	 */
	public static function get_templates() {
		$saved    = get_option( 'tarbwy_em_email_templates', array() );
		$defaults = self::get_default_templates();
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Enqueue an email with idempotency protection (prevents duplicate sent emails for same guest+template unless forced)
	 *
	 * @param int    $event_id        Event ID.
	 * @param int    $guest_id        Guest ID.
	 * @param string $template_key    Template key.
	 * @param string $recipient_email Recipient email address (primary guest email fallback if companion has none).
	 * @param array  $placeholders    Associative array of placeholders.
	 * @param bool   $allow_duplicate Whether to allow resending (e.g. ticket reissue).
	 * @return int|false Queue log ID or false.
	 */
	public static function enqueue_email( $event_id, $guest_id, $template_key, $recipient_email, $placeholders = array(), $allow_duplicate = false ) {
		global $wpdb;

		$recipient_email = sanitize_email( $recipient_email );
		if ( ! is_email( $recipient_email ) ) {
			return false;
		}

		$table          = $wpdb->prefix . 'tarbwy_email_logs';
		$recipient_hash = Security::hash_token( strtolower( $recipient_email ) );

		if ( ! $allow_duplicate ) {
			// Prevent duplicate sent or queued email for same event + guest + template
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE event_id = %d AND guest_id = %d AND template_key = %s AND status IN ('sent', 'queued') LIMIT 1",
					absint( $event_id ),
					absint( $guest_id ),
					sanitize_key( $template_key )
				)
			);
			if ( $existing ) {
				return (int) $existing;
			}
		}

		$templates = self::get_templates();
		$tpl       = isset( $templates[ $template_key ] ) ? $templates[ $template_key ] : $templates['ticket_issued'];

		$subject = self::replace_placeholders( $tpl['subject'], $placeholders );
		$now     = current_time( 'mysql' );

		// Do not persist raw secret URLs in permanent logs if avoidable; store rendered body transiently and redact in log
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'event_id'        => absint( $event_id ),
				'guest_id'        => absint( $guest_id ),
				'template_key'    => sanitize_key( $template_key ),
				'recipient_hash'  => $recipient_hash,
				'recipient_email' => $recipient_email,
				'subject'         => sanitize_text_field( $subject ),
				'payload_json'    => wp_json_encode( $placeholders, JSON_UNESCAPED_UNICODE ),
				'status'          => 'queued',
				'attempts'        => 0,
				'scheduled_at'    => $now,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return false;
		}

		$log_id = (int) $wpdb->insert_id;

		// Attempt immediate synchronous dispatch for critical transactional emails (ticket/RSVP/registration receipt)
		// If it fails, it remains in queue with exponential backoff for cron retry.
		self::dispatch_single_log( $log_id );

		return $log_id;
	}

	/**
	 * Dispatch a single email log entry via wp_mail() with RTL HTML wrapper and retry backoff
	 *
	 * @param int $log_id Email log ID.
	 * @return bool True if accepted by wp_mail(), False otherwise.
	 */
	public static function dispatch_single_log( $log_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tarbwy_email_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $log_id ) ) );
		if ( ! $log || 'sent' === $log->status ) {
			return false;
		}

		$templates    = self::get_templates();
		$tpl          = isset( $templates[ $log->template_key ] ) ? $templates[ $log->template_key ] : null;
		$placeholders = json_decode( (string) $log->payload_json, true );
		if ( ! is_array( $placeholders ) ) {
			$placeholders = array();
		}

		$body_text = $tpl ? self::replace_placeholders( $tpl['body'], $placeholders ) : '';
		$html_body = self::wrap_rtl_html_email( $log->subject, $body_text );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$attempts = (int) $log->attempts + 1;
		$now      = current_time( 'mysql' );

		$mail_error = '';
		$error_watcher = function ( $wp_error ) use ( &$mail_error ) {
			if ( is_wp_error( $wp_error ) ) {
				$mail_error = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $error_watcher );

		$sent = wp_mail( $log->recipient_email, $log->subject, $html_body, $headers );

		remove_action( 'wp_mail_failed', $error_watcher );

		// Redact sensitive URLs from payload_json after sending
		$redacted_placeholders = $placeholders;
		if ( isset( $redacted_placeholders['vip_url'] ) ) {
			$redacted_placeholders['vip_url'] = '[REDACTED_AFTER_DISPATCH]';
		}
		if ( isset( $redacted_placeholders['ticket_url'] ) ) {
			$redacted_placeholders['ticket_url'] = '[REDACTED_AFTER_DISPATCH]';
		}

		if ( $sent ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'status'       => 'sent',
					'attempts'     => $attempts,
					'sent_at'      => $now,
					'last_error'   => null,
					'payload_json' => wp_json_encode( $redacted_placeholders, JSON_UNESCAPED_UNICODE ),
					'updated_at'   => $now,
				),
				array( 'id' => absint( $log_id ) ),
				array( '%s', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return true;
		}

		// Exponential backoff: 5 mins, 20 mins, 60 mins
		$backoff_minutes = pow( 4, $attempts - 1 ) * 5;
		$next_schedule   = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + ( $backoff_minutes * 60 ) );
		$new_status      = ( $attempts >= self::MAX_ATTEMPTS ) ? 'failed' : 'queued';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'       => $new_status,
				'attempts'     => $attempts,
				'scheduled_at' => $next_schedule,
				'last_error'   => sanitize_text_field( $mail_error ? $mail_error : 'تعذر تسليم الرسالة إلى طبقة wp_mail' ),
				'updated_at'   => $now,
			),
			array( 'id' => absint( $log_id ) ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false;
	}

	/**
	 * Process queued emails in batches via Cron Job
	 */
	public static function process_queue_batch( $limit = 25 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tarbwy_email_logs';
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = 'queued' AND attempts < %d AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d",
				self::MAX_ATTEMPTS,
				$now,
				absint( $limit )
			)
		);

		$processed = 0;
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				self::dispatch_single_log( (int) $id );
				++$processed;
			}
		}

		return $processed;
	}

	/**
	 * Safe placeholder replacement
	 */
	public static function replace_placeholders( $text, $placeholders ) {
		$defaults = array(
			'guest_name'       => 'المشارك الكريم',
			'event_title'      => 'حفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية»',
			'event_date'       => 'سيُعلن لاحقاً',
			'venue_name'       => 'سيُعلن لاحقاً',
			'reference_code'   => '-',
			'ticket_reference' => '-',
			'ticket_url'       => home_url( '/launch' ),
			'vip_url'          => home_url( '/launch/vip' ),
			'rsvp_deadline'    => 'الموعد المحدد',
		);

		$data = wp_parse_args( $placeholders, $defaults );
		foreach ( $data as $key => $val ) {
			$text = str_replace( '{' . $key . '}', esc_html( (string) $val ), $text );
		}
		return $text;
	}

	/**
	 * Wrap plain/template text in institutional RTL Arabic HTML email layout
	 */
	public static function wrap_rtl_html_email( $subject, $body_text ) {
		$paragraphs = nl2br( esc_html( $body_text ) );
		// Convert raw URLs on their own lines into clickable links
		$paragraphs = preg_replace(
			'#(https?://[^\s<]+)#i',
			'<a href="$1" style="color:#14532D;font-weight:bold;text-decoration:underline;word-break:break-all;">$1</a>',
			$paragraphs
		);

		return '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>' . esc_html( $subject ) . '</title>
</head>
<body dir="rtl" style="margin:0;padding:24px;background-color:#F7F4EE;font-family:Tahoma,Arial,sans-serif;color:#1C1917;direction:rtl;text-align:right;">
	<div style="max-width:620px;margin:0 auto;background-color:#FFFFFF;border:1px solid #E5E0D8;border-top:4px solid #14532D;padding:32px;">
		<div style="border-bottom:1px solid #EBE6DF;padding-bottom:16px;margin-bottom:24px;">
			<h2 style="margin:0;color:#14532D;font-size:20px;">تربوي — الاتحاد العربي للقيادة التربوية</h2>
		</div>
		<div style="font-size:15px;line-height:1.8;color:#292524;">
			' . $paragraphs . '
		</div>
		<div style="margin-top:32px;padding-top:16px;border-top:1px solid #EBE6DF;font-size:12px;color:#78716C;">
			هذه رسالة آلية صادرة عن نظام إدارة الفعاليات الرسمي لمنصة تربوي (tarbwy.org).
		</div>
	</div>
</body>
</html>';
	}
}
