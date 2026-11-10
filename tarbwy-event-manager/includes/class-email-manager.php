<?php
/**
 * Email Automation, Hostinger SMTP Integration (smtp.hostinger.com:465 SSL),
 * Professional Sender Identity, Responsive RTL/LTR HTML + Plain-Text Templates,
 * Multi-Pass QR Code Embedding, Queue, Retries with Exponential Backoff, and Cron Dispatcher.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailManager {

	const MAX_ATTEMPTS         = 3;
	const DEFAULT_SENDER_NAME  = 'Tarbwy | تربوي — الاتحاد العربي للقيادة التربوية';
	const DEFAULT_SENDER_EMAIL = 'noreply@tarbwy.org';
	const DEFAULT_REPLY_TO     = 'noreply@tarbwy.org';
	const DEFAULT_SMTP_HOST    = 'smtp.hostinger.com';
	const DEFAULT_SMTP_PORT    = 465;
	const DEFAULT_SMTP_ENC     = 'ssl';
	const DEFAULT_SMTP_USER    = 'noreply@tarbwy.org';

	/**
	 * Read an environment variable safely from getenv(), $_ENV, $_SERVER, or WP constant
	 * Never hardcodes credentials in source code.
	 *
	 * @param string $key     Environment variable name.
	 * @param string $default Default fallback value.
	 * @return string
	 */
	public static function get_env_var( $key, $default = '' ) {
		$val = getenv( $key );
		if ( false !== $val && '' !== trim( (string) $val ) ) {
			return trim( (string) $val );
		}
		if ( isset( $_ENV[ $key ] ) && '' !== trim( (string) $_ENV[ $key ] ) ) {
			return trim( (string) $_ENV[ $key ] );
		}
		if ( isset( $_SERVER[ $key ] ) && '' !== trim( (string) $_SERVER[ $key ] ) ) {
			return trim( (string) $_SERVER[ $key ] );
		}
		if ( defined( $key ) ) {
			$const_val = constant( $key );
			if ( is_scalar( $const_val ) && '' !== trim( (string) $const_val ) ) {
				return trim( (string) $const_val );
			}
		}
		$prefixed = 'TARBWY_' . $key;
		if ( defined( $prefixed ) ) {
			$const_val = constant( $prefixed );
			if ( is_scalar( $const_val ) && '' !== trim( (string) $const_val ) ) {
				return trim( (string) $const_val );
			}
		}
		return $default;
	}

	/**
	 * Retrieve centralized Hostinger SMTP configuration from environment variables and Event Settings
	 *
	 * Required production parameters:
	 * - SMTP_HOST=smtp.hostinger.com
	 * - SMTP_PORT=465
	 * - SMTP_ENCRYPTION=ssl
	 * - SMTP_USERNAME=noreply@tarbwy.org
	 * - SMTP_PASSWORD=[Configured securely via environment variables]
	 * - SMTP_FROM_EMAIL=noreply@tarbwy.org
	 * - SMTP_FROM_NAME=Tarbwy
	 *
	 * @param int $event_id Optional event ID.
	 * @return array<string, mixed>
	 */
	public static function get_smtp_config( $event_id = 0 ) {
		$event    = RestAPI::get_active_event( $event_id );
		$settings = array();
		if ( $event && ! empty( $event->settings_json ) ) {
			$decoded = json_decode( (string) $event->settings_json, true );
			if ( is_array( $decoded ) ) {
				$settings = $decoded;
			}
		}

		$smtp_host = self::get_env_var(
			'SMTP_HOST',
			! empty( $settings['smtp_host'] ) ? sanitize_text_field( $settings['smtp_host'] ) : self::DEFAULT_SMTP_HOST
		);
		$smtp_port = (int) self::get_env_var(
			'SMTP_PORT',
			! empty( $settings['smtp_port'] ) ? (string) absint( $settings['smtp_port'] ) : (string) self::DEFAULT_SMTP_PORT
		);
		if ( $smtp_port <= 0 ) {
			$smtp_port = self::DEFAULT_SMTP_PORT;
		}

		$smtp_enc = strtolower(
			self::get_env_var(
				'SMTP_ENCRYPTION',
				! empty( $settings['smtp_encryption'] ) ? sanitize_text_field( $settings['smtp_encryption'] ) : self::DEFAULT_SMTP_ENC
			)
		);
		if ( ! in_array( $smtp_enc, array( 'ssl', 'tls', 'none' ), true ) ) {
			$smtp_enc = self::DEFAULT_SMTP_ENC;
		}

		$smtp_user = self::get_env_var(
			'SMTP_USERNAME',
			! empty( $settings['smtp_username'] ) ? sanitize_email( $settings['smtp_username'] ) : self::DEFAULT_SMTP_USER
		);
		if ( ! is_email( $smtp_user ) ) {
			$smtp_user = self::DEFAULT_SMTP_USER;
		}

		// Password read exclusively from environment variables or secure runtime option (never hardcoded)
		$smtp_pass = self::get_env_var( 'SMTP_PASSWORD', '' );
		if ( '' === $smtp_pass ) {
			$opt_pass = get_option( 'tarbwy_em_smtp_password', '' );
			if ( is_string( $opt_pass ) && '' !== trim( $opt_pass ) ) {
				$smtp_pass = trim( $opt_pass );
			}
		}

		$from_email = self::get_env_var(
			'SMTP_FROM_EMAIL',
			! empty( $settings['sender_email'] ) ? sanitize_email( $settings['sender_email'] ) : self::DEFAULT_SENDER_EMAIL
		);
		if ( ! is_email( $from_email ) || 0 === strpos( strtolower( $from_email ), 'wordpress@' ) ) {
			$from_email = self::DEFAULT_SENDER_EMAIL;
		}

		$from_name = self::get_env_var(
			'SMTP_FROM_NAME',
			! empty( $settings['sender_name'] ) ? sanitize_text_field( $settings['sender_name'] ) : 'Tarbwy'
		);
		if ( '' === $from_name || 'wordpress' === strtolower( $from_name ) ) {
			$from_name = 'Tarbwy';
		}

		return array(
			'host'          => $smtp_host,
			'port'          => $smtp_port,
			'encryption'    => $smtp_enc,
			'username'      => $smtp_user,
			'password'      => $smtp_pass,
			'has_password'  => ( '' !== $smtp_pass ),
			'from_email'    => $from_email,
			'from_name'     => $from_name,
			'auth_enabled'  => true,
		);
	}

	/**
	 * Diagnose Hostinger SMTP configuration & Domain Authentication (SPF, DKIM, DMARC)
	 *
	 * @param int $event_id Event ID.
	 * @return array<string, mixed>
	 */
	public static function diagnose_smtp_and_domain( $event_id = 0 ) {
		$smtp   = self::get_smtp_config( $event_id );
		$domain = 'tarbwy.org';
		if ( false !== strpos( $smtp['from_email'], '@' ) ) {
			$parts  = explode( '@', $smtp['from_email'] );
			$domain = strtolower( trim( end( $parts ) ) );
		}

		$spf_found   = false;
		$dmarc_found = false;

		if ( function_exists( 'dns_get_record' ) && defined( 'DNS_TXT' ) ) {
			$txt_records = @dns_get_record( $domain, DNS_TXT );
			if ( is_array( $txt_records ) ) {
				foreach ( $txt_records as $rec ) {
					$txt = isset( $rec['txt'] ) ? strtolower( (string) $rec['txt'] ) : '';
					if ( false !== strpos( $txt, 'v=spf1' ) ) {
						$spf_found = true;
					}
				}
			}
			$dmarc_records = @dns_get_record( '_dmarc.' . $domain, DNS_TXT );
			if ( is_array( $dmarc_records ) ) {
				foreach ( $dmarc_records as $rec ) {
					$txt = isset( $rec['txt'] ) ? strtolower( (string) $rec['txt'] ) : '';
					if ( false !== strpos( $txt, 'v=dmarc1' ) ) {
						$dmarc_found = true;
					}
				}
			}
		}

		return array(
			'smtp_host'       => $smtp['host'],
			'smtp_port'       => $smtp['port'],
			'smtp_encryption' => strtoupper( $smtp['encryption'] ),
			'smtp_username'   => $smtp['username'],
			'from_email'      => $smtp['from_email'],
			'from_name'       => $smtp['from_name'],
			'password_set'    => $smtp['has_password'],
			'domain'          => $domain,
			'spf_detected'    => $spf_found,
			'dmarc_detected'  => $dmarc_found,
			'ssl_port_ready'  => ( 465 === (int) $smtp['port'] && 'ssl' === $smtp['encryption'] ),
		);
	}

	/**
	 * Retrieve configurable Sender Identity & Event Context from Event Settings + Environment
	 *
	 * Never allows "WordPress" as the sender name. Defaults to "noreply@tarbwy.org".
	 *
	 * @param int $event_id Optional event ID.
	 * @return array<string, string>
	 */
	public static function get_sender_config( $event_id = 0 ) {
		$event    = RestAPI::get_active_event( $event_id );
		$settings = array();
		if ( $event && ! empty( $event->settings_json ) ) {
			$decoded = json_decode( (string) $event->settings_json, true );
			if ( is_array( $decoded ) ) {
				$settings = $decoded;
			}
		}

		$smtp = self::get_smtp_config( $event_id );

		$sender_name = isset( $settings['sender_name'] ) ? trim( sanitize_text_field( $settings['sender_name'] ) ) : '';
		if ( '' === $sender_name || 'wordpress' === strtolower( $sender_name ) ) {
			$sender_name = $smtp['from_name'] ? $smtp['from_name'] : self::DEFAULT_SENDER_NAME;
		}

		$sender_email = isset( $settings['sender_email'] ) ? sanitize_email( $settings['sender_email'] ) : '';
		if ( ! is_email( $sender_email ) || 0 === strpos( strtolower( $sender_email ), 'wordpress@' ) ) {
			$sender_email = $smtp['from_email'] ? $smtp['from_email'] : self::DEFAULT_SENDER_EMAIL;
		}

		$reply_to = isset( $settings['reply_to_email'] ) ? sanitize_email( $settings['reply_to_email'] ) : '';
		if ( ! is_email( $reply_to ) ) {
			$contact_fallback = isset( $settings['contact_email'] ) ? sanitize_email( $settings['contact_email'] ) : '';
			$reply_to         = is_email( $contact_fallback ) ? $contact_fallback : self::DEFAULT_REPLY_TO;
		}

		return array(
			'sender_name'    => $sender_name,
			'sender_email'   => $sender_email,
			'reply_to_email' => $reply_to,
			'contact_email'  => isset( $settings['contact_email'] ) && is_email( $settings['contact_email'] ) ? $settings['contact_email'] : self::DEFAULT_REPLY_TO,
			'contact_phone'  => isset( $settings['contact_phone'] ) && '' !== trim( $settings['contact_phone'] ) ? $settings['contact_phone'] : 'سيُعلن لاحقاً',
			'logo_url'       => isset( $settings['logo_url'] ) ? esc_url_raw( $settings['logo_url'] ) : '',
			'event_title'    => $event && ! empty( $event->title ) ? $event->title : 'حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية»',
			'event_date'     => isset( $settings['date_display'] ) && '' !== trim( $settings['date_display'] ) ? $settings['date_display'] : 'سيُعلن لاحقاً',
			'city_display'   => isset( $settings['city_display'] ) && '' !== trim( $settings['city_display'] ) ? $settings['city_display'] : 'سيُعلن لاحقاً',
			'venue_name'     => $event && ! empty( $event->venue_name ) ? $event->venue_name : 'سيُعلن لاحقاً',
			'venue_address'  => $event && ! empty( $event->venue_address ) ? $event->venue_address : '',
		);
	}

	/**
	 * Build standard RFC-2822 headers for wp_mail()
	 *
	 * @param array $sender Sender configuration array from get_sender_config().
	 * @return string[]
	 */
	public static function build_mail_headers( $sender = null ) {
		if ( ! is_array( $sender ) ) {
			$sender = self::get_sender_config();
		}
		$clean_name = str_replace( array( "\r", "\n", '"' ), '', $sender['sender_name'] );
		return array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: "%s" <%s>', $clean_name, $sender['sender_email'] ),
			sprintf( 'Reply-To: "%s" <%s>', $clean_name, $sender['reply_to_email'] ),
		);
	}

	/**
	 * Default Email Templates for all 14 notification types (supports Arabic RTL and English LTR)
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_default_templates() {
		return array(
			'public_registration_received' => array(
				'title'               => '1. استلام طلب حضور عام (Registration Received)',
				'badge'               => 'إشعار استلام طلب حضور · قيد المراجعة',
				'accent_color'        => '#B45309',
				'lang_dir'            => 'rtl',
				'subject'             => 'استلام طلب حضوركم: {event_title} — رقم الطلب {reference_code}',
				'heading'             => 'تم استلام طلب حضوركم وهو قيد المراجعة',
				'body'                => "مرحباً {guest_name}،\n\nنشكركم على اهتمامكم بالمشاركة في «{event_title}».\nلقد تم استلام طلب التسجيل الخاص بكم بنجاح برقم مرجعي ({reference_code})، وهو الآن قيد المراجعة من قِبل اللجنة التنظيمية للحدث.\n\nيرجى ملاحظة أن هذا الإشعار يفيد باستلام الطلب فقط ولا يُعد تأكيداً نهائياً للحضور. سنوافيكم برسالة مستقلة فور اعتماد الطلب تتضمن بطاقات الدخول الشخصية المزودة برمز QR الفريد لكل مشارك.",
				'cta_label'           => 'استعراض صفحة حفل الإشهار',
				'cta_url_placeholder' => '{event_url}',
				'show_qr_box'         => false,
				'show_event_card'     => true,
			),
			'registration_approved'        => array(
				'title'               => '2. اعتماد طلب الحضور وإصدار بطاقات QR (Registration Approved)',
				'badge'               => 'تأكيد رسمي لاعتماد الحضور · بطاقات QR جاهزة',
				'accent_color'        => '#14532D',
				'lang_dir'            => 'rtl',
				'subject'             => 'تأكيد اعتماد حضوركم وبطاقات الدخول (QR): {event_title}',
				'heading'             => 'يسرنا تأكيد اعتماد حضوركم وإصدار بطاقات الدخول الفردية',
				'body'                => "مرحباً {guest_name}،\n\nيسعدنا إبلاغكم بموافقة اللجنة المنظمة على طلب حضوركم في «{event_title}» وتأكيد تسجيلكم رسمياً (إجمالي البطاقات الصادرة: {tickets_count}).\n\nتجدون أدناه بطاقة الدخول الشخصية الخاصة بكم وبالمرافقين المعتمدين، حيث خُصص لكل شخص رمز استجابة سريعة (QR) مستقل وفريد. يرجى الاحتفاظ بالبطاقات على الهاتف المحمول أو طباعتها لإبرازها عند بوابة الاستقبال يوم الحدث.\n\n{qr_code}",
				'cta_label'           => 'فتح وطباعة بطاقة الدخول الرئيسية (QR)',
				'cta_url_placeholder' => '{ticket_url}',
				'show_qr_box'         => true,
				'show_event_card'     => true,
			),
			'registration_declined'        => array(
				'title'               => '3. الاعتذار عن عدم قبول طلب التسجيل (Registration Rejected)',
				'badge'               => 'تحديث حالة طلب الحضور · اعتذار',
				'accent_color'        => '#44403C',
				'lang_dir'            => 'rtl',
				'subject'             => 'إشعار بخصوص طلب التسجيل في {event_title}',
				'heading'             => 'نشكر اهتمامكم بحضور حفل إشهار تربوي',
				'body'                => "مرحباً {guest_name}،\n\nتتقدم اللجنة المنظمة لـ «{event_title}» بخالص الشكر والتقدير لاهتمامكم بطلب الحضور (رقم المرجع: {reference_code}).\n\nنعتذر إليكم عن عدم إمكانية تلبية طلب الحضور في هذه الفعالية نظراً لمحدودية المقاعد واكتمال الطاقة الاستيعابية المخصصة للقاعة، علماً بأنه قد تم إلغاء أي بطاقات دخول سابقة مرتبطة بهذا الطلب. نتطلع للتشرف بحضوركم ومشاركتكم في الملتقيات التربوية القادمة.",
				'cta_label'           => 'متابعة أخبار ومبادرات تربوي',
				'cta_url_placeholder' => '{event_url}',
				'show_qr_box'         => false,
				'show_event_card'     => false,
			),
			'registration_waitlist'        => array(
				'title'               => '4. إشعار قائمة الانتظار (Waitlist Notification)',
				'badge'               => 'قائمة الانتظار ذات الأولوية · Waitlist',
				'accent_color'        => '#B45309',
				'lang_dir'            => 'rtl',
				'subject'             => 'تحديث بشأن طلب حضوركم (قائمة الانتظار): {event_title}',
				'heading'             => 'تم إدراج طلبكم في قائمة الانتظار ذات الأولوية',
				'body'                => "مرحباً {guest_name}،\n\nنشكركم على تقديم طلب الحضور للمشاركة في «{event_title}» (رقم الطلب: {reference_code}).\n\nنظراً للإقبال الكبير ومحدودية المقاعد المخصصة للقاعة، تم إدراج طلبكم في قائمة الانتظار ذات الأولوية (Waitlist). سنقوم بإشعاركم فوراً وإصدار بطاقات الدخول الشخصية (QR) في حال توفر مقعد شاغر قبل موعد الحفل.",
				'cta_label'           => 'عرض تفاصيل الحدث على المنصة',
				'cta_url_placeholder' => '{event_url}',
				'show_qr_box'         => false,
				'show_event_card'     => true,
			),
			'registration_status_changed'  => array(
				'title'               => '5. إشعار تحديث حالة التسجيل (Status Change Notification)',
				'badge'               => 'تحديث رسمي لحالة التسجيل · Status Update',
				'accent_color'        => '#1E3A8A',
				'lang_dir'            => 'rtl',
				'subject'             => 'تحديث حالة طلب التسجيل ({reference_code}): {event_title}',
				'heading'             => 'تم تحديث حالة طلب تسجيلكم في الفعالية',
				'body'                => "مرحباً {guest_name}،\n\nنود إحاطتكم علماً بأنه قد تم تحديث حالة طلب التسجيل الخاص بكم رقم ({reference_code}) في «{event_title}»:\n• الحالة السابقة: {previous_status_label}\n• الحالة الجديدة المعتمدة: {new_status_label}\n\n{status_action_note}",
				'cta_label'           => 'عرض تفاصيل الفعالية',
				'cta_url_placeholder' => '{event_url}',
				'show_qr_box'         => false,
				'show_event_card'     => true,
			),
			'vip_invitation'               => array(
				'title'               => '6. دعوة خاصة لكبار الشخصيات (VIP Invitation)',
				'badge'               => 'دعوة رسمية خاصة · كبار الشخصيات VIP',
				'accent_color'        => '#9A3412',
				'lang_dir'            => 'rtl',
				'subject'             => 'دعوة خاصة لحضور {event_title} — رمز الدعوة: {vip_code}',
				'heading'             => 'دعوة رسمية لتشريف حفل إشهار «تربوي»',
				'body'                => "سعادة الأستاذ / الدكتور {guest_name} حفظه الله،\n\nيتشرف «تربوي — الاتحاد العربي للقيادة التربوية» بدعوتكم لحضور «{event_title}»، والذي يجمع نخبة القيادات التربوية وصناع القرار والخبراء في العالم العربي.\n\nللتفضل بتأكيد الحضور أو الاعتذار — مع إمكانية تسجيل المرافقين ضمن الحد المخصص لدعوتكم — يمكنكم استخدام رمز الدعوة الشخصي المستقل الخاص بكم أدناه مباشرة في صفحة كبار الشخصيات (tarbwy.org/launch/vip)، أو النقر على زر بوابة VIP أدناه قبل تاريخ ({rsvp_deadline}).",
				'cta_label'           => 'فتح بوابة VIP وتأكيد الحضور مباشرة',
				'cta_url_placeholder' => '{vip_url}',
				'show_qr_box'         => false,
				'show_vip_code_box'   => true,
				'show_event_card'     => true,
			),
			'vip_invitation_resent'        => array(
				'title'               => '7. إعادة إرسال دعوة كبار الشخصيات (VIP Invitation Resend)',
				'badge'               => 'تذكير وإعادة إرسال دعوة VIP الرسمية',
				'accent_color'        => '#9A3412',
				'lang_dir'            => 'rtl',
				'subject'             => 'تذكير بالدعوة الخاصة لحضور {event_title} — رمز الدعوة: {vip_code}',
				'heading'             => 'نسخة محدثة من دعوتكم الرسمية لحضور حفل إشهار «تربوي»',
				'body'                => "سعادة الأستاذ / الدكتور {guest_name} حفظه الله،\n\nيسعدنا إعادة إرسال رمز ورابط دعوتكم الخاصة لحضور «{event_title}».\n\nيمكنكم نسخ رمز الدعوة الشخصي المستقل الموضح أدناه واستخدامه مباشرة في بوابة كبار الشخصيات (tarbwy.org/launch/vip) أو النقر على الزر أدناه لتأكيد تشريفكم وإصدار بطاقة الدخول الفورية المزودة برمز QR.\n\n{qr_code}",
				'cta_label'           => 'فتح بوابة دعوة VIP الخاصة بكم',
				'cta_url_placeholder' => '{vip_url}',
				'show_qr_box'         => false,
				'show_vip_code_box'   => true,
				'show_event_card'     => true,
			),
			'vip_rsvp_confirmed'           => array(
				'title'               => '8. تأكيد حضور VIP وإصدار البطاقة (VIP RSVP Confirmation)',
				'badge'               => 'تأكيد تشريف VIP · بطاقات الدخول الخاصة',
				'accent_color'        => '#14532D',
				'lang_dir'            => 'rtl',
				'subject'             => 'تأكيد تشريفكم وبطاقات الدخول الخاصة (VIP): {event_title}',
				'heading'             => 'نسعد بتأكيد تشريفكم وإصدار بطاقات الدخول الخاصة',
				'body'                => "سعادة الأستاذ / الدكتور {guest_name} حفظه الله،\n\nنتقدم لسعادتكم بخالص الشكر لتأكيد تشريفكم وحضوركم «{event_title}».\n\nأُصدرت لسعادتكم بطاقة الدخول الشخصية الفردية برقم ({ticket_reference}) المزودة برمز QR الخاص بكم. وفي حال تسجيل مرافقين، فقد تم إصدار بطاقة دخول مستقلة برمز QR فريد لكل مرافق أدناه.\n\n{qr_code}",
				'cta_label'           => 'عرض وطباعة بطاقة دخول VIP الخاصة',
				'cta_url_placeholder' => '{ticket_url}',
				'show_qr_box'         => true,
				'show_event_card'     => true,
			),
			'vip_rsvp_declined'            => array(
				'title'               => '9. استلام اعتذار VIP عن الحضور (VIP RSVP Declined)',
				'badge'               => 'تأكيد استلام الرد · كبار الشخصيات VIP',
				'accent_color'        => '#44403C',
				'lang_dir'            => 'rtl',
				'subject'             => 'استلام اعتذاركم عن حضور {event_title}',
				'heading'             => 'تلقينا بالتقدير اعتذاركم عن الحضور',
				'body'                => "سعادة الأستاذ / الدكتور {guest_name} حفظه الله،\n\nتلقينا بوافر التقدير والاحترام ردكم الكريم والاعتذار عن عدم التمكن من حضور «{event_title}»، ونتطلع دائماً للتشرف بلقائكم ومشاركتكم في الملتقيات والمبادرات القيادية القادمة للاتحاد.",
				'cta_label'           => 'زيارة البوابة الرسمية لتربوي',
				'cta_url_placeholder' => '{event_url}',
				'show_qr_box'         => false,
				'show_event_card'     => false,
			),
			'ticket_issued'                => array(
				'title'               => '10. تسليم بطاقة الدخول الفردية ورمز QR (QR Code Confirmation)',
				'badge'               => 'بطاقة الدخول الرسمية المعتمدة · QR Ticket',
				'accent_color'        => '#14532D',
				'lang_dir'            => 'rtl',
				'subject'             => 'بطاقة الدخول الرسمية ورمز QR: {event_title} ({guest_name})',
				'heading'             => 'بطاقة الدخول الشخصية المعتمدة ورمز QR',
				'body'                => "مرحباً {guest_name}،\n\nمرفق في هذه الرسالة بطاقة الدخول الرسمية المعتمدة لحاملها ({guest_name}) برقم مرجعي ({ticket_reference}) لحضور «{event_title}».\n\nيرجى إبراز رمز الاستجابة السريعة (QR) الموضح أدناه عند بوابة الاستقبال يوم الفعالية ليتم التحقق الفوري وتسجيل الدخول.\n\n{qr_code}",
				'cta_label'           => 'فتح بطاقة الدخول للطباعة أو الحفظ (QR)',
				'cta_url_placeholder' => '{ticket_url}',
				'show_qr_box'         => true,
				'show_event_card'     => true,
			),
			'event_updates'                => array(
				'title'               => '11. إشعار وتحديثات الفعالية (Event Updates Notification)',
				'badge'               => 'تحديث تنظيمي هام · Event Update',
				'accent_color'        => '#14532D',
				'lang_dir'            => 'rtl',
				'subject'             => 'تحديث هام للمشاركين في {event_title}',
				'heading'             => 'مستجدات وتوجيهات تنظيمية خاصة بحفل الإشهار',
				'body'                => "مرحباً {guest_name}،\n\nتود اللجنة المنظمة لـ «{event_title}» إطلاعكم على أحدث المستجدات والترتيبات التنظيمية الخاصة بالفعالية.\n\nنرجو مراجعة بيانات الموعد والمكان الموضحة في البطاقة أدناه، والتأكد من الاحتفاظ ببطاقة الدخول الشخصية (QR) في حال تأكيد حضوركم.",
				'cta_label'           => 'الاطلاع على البرنامج والتفاصيل الكاملة',
				'cta_url_placeholder' => '{event_url}',
				'show_qr_box'         => false,
				'show_event_card'     => true,
			),
			'event_reminder'               => array(
				'title'               => '12. تذكير قبل موعد الحدث للمؤكدين (Event Reminder)',
				'badge'               => 'تذكير باقتراب موعد الحفل · للمؤكدين فقط',
				'accent_color'        => '#9A3412',
				'lang_dir'            => 'rtl',
				'subject'             => 'تذكير بموعد {event_title} وبطاقة الدخول السريع (QR)',
				'heading'             => 'تذكير باقتراب موعد حفل الإشهار وإرشادات الدخول',
				'body'                => "مرحباً {guest_name}،\n\nيسعدنا تذكيركم باقتراب موعد انعقاد «{event_title}»، ونتطلع للترحيب بكم.\n\nلتسهيل إجراءات الاستقبال والدخول السريع يوم الفعالية، يرجى التأكد من جاهزية بطاقة الدخول الشخصية الخاصة بكم ({ticket_reference}) عبر هاتفكم المحمول أو بنسخة مطبوعة قبل الوصول إلى القاعة.\n\n{qr_code}",
				'cta_label'           => 'استعراض بطاقة الدخول ورمز QR الآن',
				'cta_url_placeholder' => '{ticket_url}',
				'show_qr_box'         => true,
				'show_event_card'     => true,
			),
			'checkin_confirmation'         => array(
				'title'               => '13. تأكيد تسجيل الدخول يوم الحدث (Check-in Confirmation)',
				'badge'               => 'تأكيد تسجيل الوصول · أهلاً بكم في القاعة',
				'accent_color'        => '#14532D',
				'lang_dir'            => 'rtl',
				'subject'             => 'أهلاً بكم في {event_title} — تم تسجيل دخولكم بنجاح',
				'heading'             => 'مرحباً بكم في رحاب حفل إشهار «تربوي»',
				'body'                => "مرحباً {guest_name}،\n\nتم تسجيل دخولكم بنجاح عبر بوابة الاستقبال في «{event_title}» ببطاقة رقم ({ticket_reference}) في تمام ({checkin_time}).\n\nنرحب بكم أجمل ترحيب بين نخبة القيادات التربوية العربية، ونتمنى لكم لقاءً مثمراً وتجربة ثرية.",
				'cta_label'           => 'استعراض برنامج الحفل والفقرات',
				'cta_url_placeholder' => '{event_url}',
				'show_qr_box'         => false,
				'show_event_card'     => true,
			),
			'post_event_thank_you'         => array(
				'title'               => '14. رسالة شكر وتقدير بعد الحدث (Post-Event Thank-You)',
				'badge'               => 'شكر وتقدير من الأمانة العامة · تربوي',
				'accent_color'        => '#14532D',
				'lang_dir'            => 'rtl',
				'subject'             => 'شكراً لتشريفكم ومشاركتكم في {event_title}',
				'heading'             => 'شكراً لتشريفكم حفل إشهار «تربوي»',
				'body'                => "سعادة المشارك الكريم {guest_name}،\n\nيتقدم «تربوي — الاتحاد العربي للقيادة التربوية» بخالص الشكر وعظيم الامتنان لتشريفكم ومشاركتكم الفاعلة في «{event_title}».\n\nإن حضوركم وإسهامكم يمثلان ركيزة أساسية في بناء المساحة المهنية والمعرفية المشتركة للقيادة التربوية في الوطن العربي، ونتطلع لاستمرار التواصل والتكامل في مبادرات الاتحاد القادمة.",
				'cta_label'           => 'زيارة منصة تربوي الرسمية',
				'cta_url_placeholder' => '{event_url}',
				'show_qr_box'         => false,
				'show_event_card'     => false,
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
		} else {
			update_option( 'tarbwy_em_email_templates', self::get_templates() );
		}
	}

	/**
	 * Get templates (deep-merged with defaults so every field is guaranteed present)
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_templates() {
		$saved    = get_option( 'tarbwy_em_email_templates', array() );
		$defaults = self::get_default_templates();
		if ( ! is_array( $saved ) ) {
			return $defaults;
		}

		$merged = array();
		foreach ( $defaults as $key => $def_tpl ) {
			if ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
				$merged[ $key ] = wp_parse_args( $saved[ $key ], $def_tpl );
			} else {
				$merged[ $key ] = $def_tpl;
			}
		}
		return $merged;
	}

	/**
	 * Restore one or all email templates back to factory defaults
	 *
	 * @param string $template_key 'all' or a specific template key.
	 * @return bool
	 */
	public static function restore_default_templates( $template_key = 'all' ) {
		$defaults = self::get_default_templates();
		if ( 'all' === $template_key || ! isset( $defaults[ $template_key ] ) ) {
			update_option( 'tarbwy_em_email_templates', $defaults );
			return true;
		}

		$current                  = self::get_templates();
		$current[ $template_key ] = $defaults[ $template_key ];
		update_option( 'tarbwy_em_email_templates', $current );
		return true;
	}

	/**
	 * Enqueue an email with idempotency protection and dispatch immediately
	 *
	 * @param int    $event_id        Event ID.
	 * @param int    $guest_id        Guest ID.
	 * @param string $template_key    Template key.
	 * @param string $recipient_email Recipient email address (primary guest email fallback if companion has none).
	 * @param array  $placeholders    Associative array of placeholders (may include 'all_tickets' array).
	 * @param bool   $allow_duplicate Whether to allow resending (e.g. ticket reissue or manual resend).
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

		if ( ! $allow_duplicate && absint( $guest_id ) > 0 ) {
			// Check existing email for same event + guest + template
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, status FROM {$table} WHERE event_id = %d AND guest_id = %d AND template_key = %s ORDER BY id DESC LIMIT 1",
					absint( $event_id ),
					absint( $guest_id ),
					sanitize_key( $template_key )
				)
			);
			if ( $existing ) {
				if ( 'sent' === $existing->status ) {
					return (int) $existing->id;
				}
				// Re-attempt delivery if previously queued or failed
				self::dispatch_single_log( (int) $existing->id );
				return (int) $existing->id;
			}
		}

		$templates = self::get_templates();
		$tpl       = isset( $templates[ $template_key ] ) ? $templates[ $template_key ] : $templates['ticket_issued'];

		$subject = self::replace_placeholders( $tpl['subject'], $placeholders, (int) $event_id );
		$now     = current_time( 'mysql' );

		$insert_data    = array(
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
		);
		$insert_formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $insert_data, $insert_formats );

		if ( ! $inserted ) {
			Installer::create_tables();
			Installer::ensure_schema_columns();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "SET SESSION sql_mode = ''" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert( $table, $insert_data, $insert_formats );
		}

		if ( ! $inserted ) {
			// Fallback: dispatch email directly even if log table insertion failed so registrant/VIP always receives the email!
			$placeholders = self::hydrate_ticket_and_vip_urls( (int) $event_id, (int) $guest_id, $placeholders );
			$package      = self::render_email_package( $template_key, $placeholders, (int) $event_id );
			$direct_res   = self::send_via_wp_mail( $recipient_email, $package, (int) $event_id );
			return $direct_res['sent'] ? 1 : false;
		}

		$log_id = (int) $wpdb->insert_id;

		// Attempt immediate synchronous dispatch; if SMTP fails, remains in queue with error details for retry.
		self::dispatch_single_log( $log_id );

		return $log_id;
	}

	/**
	 * Check the delivery status of a specific email log entry
	 *
	 * @param int $log_id Email log ID.
	 * @return array { 'status' => string, 'sent' => bool, 'error' => string }
	 */
	public static function get_log_status( $log_id ) {
		global $wpdb;
		if ( ! $log_id ) {
			return array(
				'status' => 'failed',
				'sent'   => false,
				'error'  => 'لم يتم إنشاء سجل البريد.',
			);
		}
		$table = $wpdb->prefix . 'tarbwy_email_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, last_error FROM {$table} WHERE id = %d", absint( $log_id ) ) );
		if ( ! $row ) {
			return array(
				'status' => 'sent',
				'sent'   => ( 1 === (int) $log_id ),
				'error'  => '',
			);
		}
		return array(
			'status' => (string) $row->status,
			'sent'   => ( 'sent' === $row->status ),
			'error'  => (string) $row->last_error,
		);
	}

	/**
	 * Retry sending a failed or queued email log entry without generating duplicate QR codes
	 *
	 * @param int $log_id Email log ID.
	 * @return array { 'sent' => bool, 'error' => string }
	 */
	public static function retry_failed_email( $log_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tarbwy_email_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $log_id ) ) );
		if ( ! $log ) {
			return array(
				'sent'  => false,
				'error' => 'سجل البريد المطلوب غير موجود.',
			);
		}

		// Reset status to queued so dispatch_single_log can attempt delivery
		if ( 'sent' === $log->status ) {
			// Allow manual re-dispatch if requested by admin
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'status'     => 'queued',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => absint( $log_id ) ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		$sent   = self::dispatch_single_log( $log_id );
		$status = self::get_log_status( $log_id );

		Security::log_activity(
			(int) $log->event_id,
			'email_retry_dispatched',
			'email',
			(int) $log_id,
			array(
				'guest_id'     => (int) $log->guest_id,
				'template_key' => (string) $log->template_key,
				'sent'         => $sent ? 'true' : 'false',
			)
		);

		return array(
			'sent'  => $sent,
			'error' => $status['error'],
		);
	}

	/**
	 * Dispatch a single email log entry via wp_mail() with Hostinger SMTP, Sender Identity,
	 * Responsive RTL/LTR HTML + Plain-Text fallback, and retry backoff
	 *
	 * @param int $log_id Email log ID.
	 * @return bool True if accepted by SMTP/wp_mail(), False otherwise.
	 */
	public static function dispatch_single_log( $log_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tarbwy_email_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $log_id ) ) );
		if ( ! $log || 'sent' === $log->status ) {
			return false;
		}

		$placeholders = json_decode( (string) ( $log->payload_json ?? '{}' ), true );
		if ( ! is_array( $placeholders ) ) {
			$placeholders = array();
		}

		// Hydrate ticket URLs dynamically from DB if previously redacted or missing
		$placeholders = self::hydrate_ticket_and_vip_urls( (int) $log->event_id, (int) $log->guest_id, $placeholders );

		$package  = self::render_email_package( $log->template_key, $placeholders, (int) $log->event_id );
		$attempts = (int) $log->attempts + 1;
		$now      = current_time( 'mysql' );

		$dispatch_res = self::send_via_wp_mail( $log->recipient_email, $package, (int) $log->event_id );
		$sent         = $dispatch_res['sent'];
		$mail_error   = $dispatch_res['error'];

		if ( $sent ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'status'     => 'sent',
					'subject'    => sanitize_text_field( $package['subject'] ),
					'attempts'   => $attempts,
					'sent_at'    => $now,
					'last_error' => null,
					'updated_at' => $now,
				),
				array( 'id' => absint( $log_id ) ),
				array( '%s', '%s', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return true;
		}

		// Exponential backoff: 5 mins, 20 mins, 60 mins
		$backoff_minutes = pow( 4, max( 0, $attempts - 1 ) ) * 5;
		$next_schedule   = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + ( $backoff_minutes * 60 ) );
		$new_status      = ( $attempts >= self::MAX_ATTEMPTS ) ? 'failed' : 'queued';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'       => $new_status,
				'attempts'     => $attempts,
				'scheduled_at' => $next_schedule,
				'last_error'   => sanitize_text_field( $mail_error ? $mail_error : 'تعذر تسليم الرسالة عبر خادم البريد SMTP (smtp.hostinger.com:465).' ),
				'updated_at'   => $now,
			),
			array( 'id' => absint( $log_id ) ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false;
	}

	/**
	 * Ensure ticket_url, vip_url, and all_tickets are always valid when retrying or resending an email
	 * Never creates duplicate QR codes—reads existing active tickets from DB.
	 *
	 * @param int   $event_id     Event ID.
	 * @param int   $guest_id     Guest ID.
	 * @param array $placeholders Existing placeholders.
	 * @return array Hydrated placeholders.
	 */
	public static function hydrate_ticket_and_vip_urls( $event_id, $guest_id, $placeholders ) {
		global $wpdb;
		if ( ! $guest_id ) {
			return $placeholders;
		}

		$guests_table  = $wpdb->prefix . 'tarbwy_guests';
		$tickets_table = $wpdb->prefix . 'tarbwy_tickets';
		$inv_table     = $wpdb->prefix . 'tarbwy_invitations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, registration_id, full_name, guest_type, rsvp_status FROM {$guests_table} WHERE id = %d AND event_id = %d",
				absint( $guest_id ),
				absint( $event_id )
			)
		);

		if ( ! $guest ) {
			return $placeholders;
		}

		$default_ticket_url = home_url( '/launch/ticket' );
		$needs_ticket_hydration = (
			empty( $placeholders['ticket_url'] ) ||
			'[REDACTED_AFTER_DISPATCH]' === ( $placeholders['ticket_url'] ?? '' ) ||
			$default_ticket_url === ( $placeholders['ticket_url'] ?? '' ) ||
			empty( $placeholders['ticket_reference'] ) ||
			'-' === trim( (string) ( $placeholders['ticket_reference'] ?? '' ) ) ||
			empty( $placeholders['qr_payload'] ) ||
			'-' === trim( (string) ( $placeholders['qr_payload'] ?? '' ) )
		);

		if ( $needs_ticket_hydration ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$tkt = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, ticket_reference, token_preview FROM {$tickets_table} WHERE event_id = %d AND guest_id = %d AND status IN ('active', 'used') ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END ASC, id DESC LIMIT 1",
					absint( $event_id ),
					absint( $guest_id )
				)
			);
			// If guest is confirmed in DB and has no ticket yet, auto-issue their ticket now
			if ( ! $tkt && 'confirmed' === $guest->rsvp_status ) {
				$issued = QRGenerator::issue_ticket_for_guest( (int) $event_id, (int) $guest_id, false );
				if ( ! is_wp_error( $issued ) ) {
					$placeholders['ticket_reference'] = $issued['ticket_reference'];
					$placeholders['qr_payload']       = $issued['qr_payload'];
					$placeholders['ticket_url']       = $issued['ticket_url'];
				}
			} elseif ( $tkt ) {
				$canonical_payload                = QRGenerator::get_canonical_qr_payload( $tkt );
				$raw_tok                          = ! empty( $tkt->token_preview ) ? $tkt->token_preview : get_transient( 'tarbwy_tkt_raw_' . $tkt->id );
				$placeholders['ticket_reference'] = $canonical_payload;
				$placeholders['qr_payload']       = $canonical_payload;
				$placeholders['ticket_url']       = QRGenerator::get_ticket_url( $raw_tok ? $raw_tok : $canonical_payload );
			}
		}

		if ( empty( $placeholders['vip_url'] ) || empty( $placeholders['vip_code'] ) || '[REDACTED_AFTER_DISPATCH]' === ( $placeholders['vip_url'] ?? '' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inv = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, token_preview, token_hash FROM {$inv_table} WHERE event_id = %d AND guest_id = %d ORDER BY id DESC LIMIT 1",
					absint( $event_id ),
					absint( $guest_id )
				)
			);
			if ( ! $inv && 'vip' === $guest->guest_type ) {
				// Auto-create an active VIP invitation token if a VIP guest did not have one yet
				$now        = current_time( 'mysql' );
				$token_pair = Security::generate_token_pair( 24 );
				$new_inv_id = Installer::safe_insert_invitation(
					array(
						'event_id'      => absint( $event_id ),
						'guest_id'      => absint( $guest_id ),
						'token_hash'    => $token_pair['hash'],
						'token_preview' => $token_pair['raw'],
						'status'        => 'active',
						'sent_at'       => $now,
						'created_by'    => get_current_user_id(),
						'created_at'    => $now,
						'updated_at'    => $now,
					)
				);
				if ( $new_inv_id > 0 ) {
					set_transient( 'tarbwy_vip_raw_' . $new_inv_id, $token_pair['raw'], DAY_IN_SECONDS * 30 );
					$placeholders['vip_url']  = home_url( '/launch/vip/?token=' . rawurlencode( $token_pair['raw'] ) );
					$placeholders['vip_code'] = Security::format_vip_personal_code( $token_pair['raw'] );
				}
			} elseif ( $inv ) {
				$raw_inv = ! empty( $inv->token_preview ) ? $inv->token_preview : get_transient( 'tarbwy_vip_raw_' . $inv->id );
				if ( $raw_inv ) {
					$placeholders['vip_url']  = home_url( '/launch/vip/?token=' . rawurlencode( $raw_inv ) );
					$placeholders['vip_code'] = Security::format_vip_personal_code( $raw_inv );
				} elseif ( ! empty( $inv->token_hash ) ) {
					$placeholders['vip_code'] = Security::format_vip_personal_code( $inv->token_hash );
					$placeholders['vip_url']  = home_url( '/launch/vip/?token=' . rawurlencode( $placeholders['vip_code'] ) );
				}
			}
		}

		if ( empty( $placeholders['all_tickets'] ) ) {
			if ( ! empty( $guest->registration_id ) ) {
				$all_passes = QRGenerator::get_active_tickets_for_registration( $event_id, (int) $guest->registration_id );
				if ( ! empty( $all_passes ) ) {
					$placeholders['all_tickets']   = $all_passes;
					$placeholders['tickets_count'] = (string) count( $all_passes );
				}
			} elseif ( 'vip' === $guest->guest_type ) {
				$vip_passes = QRGenerator::get_active_tickets_for_vip_guest( $event_id, (int) $guest->id );
				if ( ! empty( $vip_passes ) ) {
					$placeholders['all_tickets']   = $vip_passes;
					$placeholders['tickets_count'] = (string) count( $vip_passes );
				}
			}
		}

		// Ensure every pass inside all_tickets has a canonical qr_payload matching ticket_reference
		if ( ! empty( $placeholders['all_tickets'] ) && is_array( $placeholders['all_tickets'] ) ) {
			foreach ( $placeholders['all_tickets'] as $idx => $pass_item ) {
				if ( is_array( $pass_item ) ) {
					$canonical_p                                           = QRGenerator::get_canonical_qr_payload( $pass_item );
					$placeholders['all_tickets'][ $idx ]['qr_payload']     = $canonical_p;
					$placeholders['all_tickets'][ $idx ]['qr_token']       = $canonical_p;
					$placeholders['all_tickets'][ $idx ]['ticket_reference'] = $canonical_p;
				}
			}
		}

		return $placeholders;
	}

	/**
	 * Execute wp_mail() with multi-stage resilience:
	 * 1. Hostinger SMTP (with SSL peer-verification relaxation and authenticated From matching) or WordPress SMTP plugin compatibility.
	 * 2. Automatic port 587 STARTTLS fallback if port 465 SSL is blocked.
	 * 3. Native WordPress wp_mail() fallback without custom From overrides if custom sender was rejected by the server's MTA.
	 *
	 * @param string $recipient_email Recipient email.
	 * @param array  $package         Rendered email package from render_email_package().
	 * @param int    $event_id        Optional Event ID.
	 * @return array { 'sent' => bool, 'error' => string }
	 */
	public static function send_via_wp_mail( $recipient_email, $package, $event_id = 0 ) {
		$sender     = $package['sender'];
		$plain_text = $package['plain_text'];
		$smtp       = self::get_smtp_config( $event_id );
		$mail_error = '';

		// When authenticating via SMTP, Hostinger requires From address to match the authenticated SMTP Username
		$effective_from_email = $sender['sender_email'];
		if ( ! empty( $smtp['has_password'] ) && ! empty( $smtp['username'] ) && is_email( $smtp['username'] ) ) {
			$effective_from_email = $smtp['username'];
		}

		$from_email_filter = function ( $orig_email ) use ( $effective_from_email, $smtp ) {
			// If no SMTP password is set in our plugin and another SMTP plugin already set a non-wordpress From address, respect it
			if ( empty( $smtp['has_password'] ) && ! empty( $orig_email ) && is_email( $orig_email ) && 0 !== strpos( strtolower( $orig_email ), 'wordpress@' ) ) {
				return $orig_email;
			}
			return $effective_from_email;
		};
		$from_name_filter  = function ( $orig_name ) use ( $sender, $smtp ) {
			if ( empty( $smtp['has_password'] ) && ! empty( $orig_name ) && 'wordpress' !== strtolower( trim( $orig_name ) ) ) {
				return $orig_name;
			}
			return $sender['sender_name'];
		};
		$phpmailer_hook    = function ( $phpmailer ) use ( $plain_text, $sender, $smtp, $effective_from_email ) {
			if ( is_object( $phpmailer ) ) {
				$phpmailer->AltBody = $plain_text;
				$phpmailer->CharSet = 'UTF-8';

				// Configure Hostinger SMTP directly when SMTP_PASSWORD is provided in environment/options
				// and another plugin hasn't already forced custom SMTP credentials.
				if ( ! empty( $smtp['has_password'] ) && ( 'mail' === $phpmailer->Mailer || empty( $phpmailer->Host ) || 'localhost' === $phpmailer->Host ) ) {
					if ( method_exists( $phpmailer, 'isSMTP' ) ) {
						$phpmailer->isSMTP();
					}
					$phpmailer->Host        = $smtp['host'];
					$phpmailer->SMTPAuth    = true;
					$phpmailer->Port        = (int) $smtp['port'];
					$phpmailer->SMTPSecure  = ( 'none' === $smtp['encryption'] ) ? '' : $smtp['encryption'];
					$phpmailer->Username    = $smtp['username'];
					$phpmailer->Password    = $smtp['password'];
					$phpmailer->Timeout     = 15;
					$phpmailer->SMTPOptions = array(
						'ssl' => array(
							'verify_peer'       => false,
							'verify_peer_name'  => false,
							'allow_self_signed' => true,
						),
					);
					$phpmailer->From     = $effective_from_email;
					$phpmailer->FromName = $sender['sender_name'];
				} elseif ( empty( $phpmailer->From ) || 0 === strpos( strtolower( (string) $phpmailer->From ), 'wordpress@' ) ) {
					$phpmailer->From     = $effective_from_email;
					$phpmailer->FromName = $sender['sender_name'];
				}

				if ( method_exists( $phpmailer, 'clearReplyTos' ) && method_exists( $phpmailer, 'addReplyTo' ) && ! empty( $sender['reply_to_email'] ) ) {
					$phpmailer->clearReplyTos();
					$phpmailer->addReplyTo( $sender['reply_to_email'], $sender['sender_name'] );
				}
			}
		};
		$error_watcher     = function ( $wp_error ) use ( &$mail_error ) {
			if ( is_wp_error( $wp_error ) ) {
				$mail_error = $wp_error->get_error_message();
			}
		};

		add_filter( 'wp_mail_from', $from_email_filter, 20 );
		add_filter( 'wp_mail_from_name', $from_name_filter, 20 );
		add_action( 'phpmailer_init', $phpmailer_hook, 20 );
		add_action( 'wp_mail_failed', $error_watcher );

		$primary_headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		if ( ! empty( $sender['reply_to_email'] ) && is_email( $sender['reply_to_email'] ) ) {
			$clean_name        = str_replace( array( "\r", "\n", '"' ), '', $sender['sender_name'] );
			$primary_headers[] = sprintf( 'Reply-To: "%s" <%s>', $clean_name, $sender['reply_to_email'] );
		}

		$sent = wp_mail(
			$recipient_email,
			$package['subject'],
			$package['html'],
			$primary_headers
		);

		remove_filter( 'wp_mail_from', $from_email_filter, 20 );
		remove_filter( 'wp_mail_from_name', $from_name_filter, 20 );
		remove_action( 'phpmailer_init', $phpmailer_hook, 20 );
		remove_action( 'wp_mail_failed', $error_watcher );

		if ( ! empty( $mail_error ) ) {
			$sent = false;
		}

		// Stage 2 Fallback: If custom SMTP on port 465 failed, try port 587 STARTTLS
		if ( ! $sent && ! empty( $smtp['has_password'] ) && 465 === (int) $smtp['port'] ) {
			$mail_error = '';
			$tls_hook   = function ( $phpmailer ) use ( $plain_text, $sender, $smtp, $effective_from_email ) {
				if ( is_object( $phpmailer ) ) {
					$phpmailer->AltBody = $plain_text;
					$phpmailer->CharSet = 'UTF-8';
					if ( method_exists( $phpmailer, 'isSMTP' ) ) {
						$phpmailer->isSMTP();
					}
					$phpmailer->Host        = $smtp['host'];
					$phpmailer->SMTPAuth    = true;
					$phpmailer->Port        = 587;
					$phpmailer->SMTPSecure  = 'tls';
					$phpmailer->Username    = $smtp['username'];
					$phpmailer->Password    = $smtp['password'];
					$phpmailer->Timeout     = 15;
					$phpmailer->SMTPOptions = array(
						'ssl' => array(
							'verify_peer'       => false,
							'verify_peer_name'  => false,
							'allow_self_signed' => true,
						),
					);
					$phpmailer->From     = $effective_from_email;
					$phpmailer->FromName = $sender['sender_name'];
				}
			};
			add_action( 'phpmailer_init', $tls_hook, 999 );
			add_action( 'wp_mail_failed', $error_watcher );
			$sent = wp_mail(
				$recipient_email,
				$package['subject'],
				$package['html'],
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
			remove_action( 'phpmailer_init', $tls_hook, 999 );
			remove_action( 'wp_mail_failed', $error_watcher );
			if ( ! empty( $mail_error ) ) {
				$sent = false;
			}
		}

		// Stage 3 Fallback: Native WordPress default mailer / active SMTP plugin without custom From overrides
		if ( ! $sent ) {
			$fallback_error   = '';
			$fallback_watcher = function ( $wp_error ) use ( &$fallback_error ) {
				if ( is_wp_error( $wp_error ) ) {
					$fallback_error = $wp_error->get_error_message();
				}
			};
			add_action( 'wp_mail_failed', $fallback_watcher );
			$sent = wp_mail(
				$recipient_email,
				$package['subject'],
				$package['html'],
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
			remove_action( 'wp_mail_failed', $fallback_watcher );
			if ( $sent && empty( $fallback_error ) ) {
				$mail_error = '';
			} elseif ( ! empty( $fallback_error ) && empty( $mail_error ) ) {
				$mail_error = $fallback_error;
			}
		}

		return array(
			'sent'  => (bool) $sent,
			'error' => $mail_error,
		);
	}

	/**
	 * Send a live Test Email to an administrator before sending to real attendees
	 *
	 * @param string $template_key Template key to test.
	 * @param string $target_email Destination email address.
	 * @param int    $event_id     Event ID.
	 * @return array { 'sent' => bool, 'error' => string, 'subject' => string }
	 */
	public static function send_test_email( $template_key, $target_email, $event_id = 1 ) {
		$target_email = sanitize_email( $target_email );
		if ( ! is_email( $target_email ) ) {
			return array(
				'sent'    => false,
				'error'   => 'عنوان البريد الإلكتروني التجريبي غير صالح.',
				'subject' => '',
			);
		}

		$sample_placeholders = self::get_sample_placeholders( $event_id );
		$package             = self::render_email_package( $template_key, $sample_placeholders, $event_id );
		$package['subject']  = '[بريد تجريبي — معاينة القالب] ' . $package['subject'];

		$res = self::send_via_wp_mail( $target_email, $package, $event_id );
		Security::log_activity(
			$event_id,
			'test_email_sent',
			'email',
			0,
			array(
				'template_key' => $template_key,
				'sent'         => $res['sent'] ? 'true' : 'false',
			)
		);

		return array(
			'sent'    => $res['sent'],
			'error'   => $res['error'],
			'subject' => $package['subject'],
		);
	}

	/**
	 * Sample placeholders for live preview and test email dispatch (includes multi-ticket sample)
	 *
	 * @param int $event_id Event ID.
	 * @return array<string, mixed>
	 */
	public static function get_sample_placeholders( $event_id = 1 ) {
		$sender = self::get_sender_config( $event_id );
		return array(
			'guest_name'            => 'سعادة د. عبدالرحمن المنصور (نموذج معاينة)',
			'event_title'           => $sender['event_title'],
			'event_date'            => $sender['event_date'],
			'venue_name'            => $sender['venue_name'],
			'venue_address'         => $sender['venue_address'],
			'reference_code'        => 'TRB-2026-8F4A2C',
			'ticket_reference'      => 'TKT-2026-99104B',
			'qr_payload'            => 'TKT-2026-99104B',
			'ticket_url'            => home_url( '/launch/ticket/?t=sample_preview_token_99104b' ),
			'vip_url'               => home_url( '/launch/vip/?token=8f4a2c91e7b3094512a6c8d4f0192b3e4c5d6a7b8c9d0e1f' ),
			'vip_code'              => 'VIP-8F4A-2C91',
			'event_url'             => home_url( '/launch' ),
			'rsvp_deadline'         => 'قبل موعد الحفل بـ 48 ساعة',
			'checkin_time'          => current_time( 'Y-m-d H:i' ),
			'contact_email'         => $sender['contact_email'],
			'tickets_count'         => '3',
			'previous_status_label' => 'قيد المراجعة (Pending)',
			'new_status_label'      => 'معتمد ومؤكد (Approved)',
			'status_action_note'    => 'تم إصدار بطاقات الدخول الفردية المزودة برمز QR لكافة الأفراد المعتمدين.',
			'all_tickets'           => array(
				array(
					'guest_name'       => 'سعادة د. عبدالرحمن المنصور (الضيف الأساسي)',
					'guest_type'       => 'public',
					'ticket_reference' => 'TKT-2026-99104B',
					'qr_payload'       => 'TKT-2026-99104B',
					'ticket_url'       => home_url( '/launch/ticket/?t=sample_preview_token_99104b' ),
				),
				array(
					'guest_name'       => 'أ. خلدون المنصور (مرافق #1)',
					'guest_type'       => 'companion',
					'ticket_reference' => 'TKT-2026-99105C',
					'qr_payload'       => 'TKT-2026-99105C',
					'ticket_url'       => home_url( '/launch/ticket/?t=sample_preview_token_99105c' ),
				),
				array(
					'guest_name'       => 'أ. سارة المنصور (مرافق #2)',
					'guest_type'       => 'companion',
					'ticket_reference' => 'TKT-2026-99106D',
					'qr_payload'       => 'TKT-2026-99106D',
					'ticket_url'       => home_url( '/launch/ticket/?t=sample_preview_token_99106d' ),
				),
			),
		);
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
	 * Normalize placeholder values with event defaults
	 *
	 * @param array $placeholders Raw placeholders.
	 * @param int   $event_id     Event ID.
	 * @return array<string, mixed>
	 */
	public static function normalize_placeholders( $placeholders = array(), $event_id = 0 ) {
		$sender   = self::get_sender_config( $event_id );
		$defaults = array(
			'guest_name'            => 'المشارك الكريم',
			'event_title'           => $sender['event_title'],
			'event_date'            => $sender['event_date'],
			'venue_name'            => $sender['venue_name'],
			'venue_address'         => $sender['venue_address'],
			'reference_code'        => '-',
			'ticket_reference'      => '-',
			'ticket_url'            => home_url( '/launch/ticket' ),
			'vip_url'               => home_url( '/launch/vip' ),
			'vip_code'              => '',
			'event_url'             => home_url( '/launch' ),
			'rsvp_deadline'         => 'الموعد المحدد',
			'checkin_time'          => current_time( 'Y-m-d H:i' ),
			'contact_email'         => $sender['contact_email'],
			'tickets_count'         => '1',
			'previous_status_label' => '-',
			'new_status_label'      => '-',
			'status_action_note'    => '',
			'all_tickets'           => array(),
		);

		$merged = wp_parse_args( is_array( $placeholders ) ? $placeholders : array(), $defaults );

		// Automatically derive standalone Personal Invitation Code (VIP-XXXX-XXXX) if vip_url contains a token
		if ( empty( $merged['vip_code'] ) && ! empty( $merged['vip_url'] ) ) {
			$parsed = Security::parse_vip_code_input( $merged['vip_url'] );
			if ( ! empty( $parsed['clean_hex'] ) && strlen( $parsed['clean_hex'] ) >= 8 ) {
				$merged['vip_code'] = Security::format_vip_personal_code( $parsed['clean_hex'] );
			}
		}

		return $merged;
	}

	/**
	 * Safe placeholder replacement for plain strings (subject, heading, CTA)
	 */
	public static function replace_placeholders( $text, $placeholders, $event_id = 0 ) {
		$data = self::normalize_placeholders( $placeholders, $event_id );
		$text = str_replace( '{qr_code}', '', (string) $text );
		foreach ( $data as $key => $val ) {
			if ( is_scalar( $val ) ) {
				$text = str_replace( '{' . $key . '}', sanitize_text_field( (string) $val ), $text );
			}
		}
		return trim( $text );
	}

	/**
	 * Render a dedicated standalone "Personal Invitation Code" (رمز الدعوة الشخصي) card with Copy Code button
	 *
	 * @param array  $data     Normalized placeholders array.
	 * @param string $lang_dir 'rtl' or 'ltr'.
	 * @return string HTML table block.
	 */
	public static function render_vip_personal_code_box_html( $data, $lang_dir = 'rtl' ) {
		$vip_code = ! empty( $data['vip_code'] ) ? (string) $data['vip_code'] : 'VIP-8F4A-2C91';
		$vip_portal_base = home_url( '/launch/vip' );
		$copy_and_open_url = add_query_arg(
			array(
				'token' => $vip_code,
				'copy'  => '1',
			),
			$vip_portal_base
		);
		$align = ( 'ltr' === $lang_dir ) ? 'left' : 'right';

		return '
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;background-color:#FFFBEB;border:2px solid #9A3412;border-radius:10px;overflow:hidden;">
			<tr>
				<td style="background-color:#9A3412;color:#FFFFFF;padding:10px 18px;font-size:12px;font-weight:bold;letter-spacing:0.4px;text-align:' . esc_attr( $align ) . ';">
					رمز الدعوة الشخصي المستقل · PERSONAL INVITATION CODE
				</td>
			</tr>
			<tr>
				<td style="padding:20px 22px;direction:' . esc_attr( $lang_dir ) . ';text-align:center;">
					<div style="font-size:13px;color:#57534E;margin-bottom:10px;line-height:1.6;">
						تم تخصيص هذا الرمز الفريد لسعادتكم. يمكنكم نسخه واستخدامه مباشرة في صفحة كبار الشخصيات (<a href="' . esc_url( $vip_portal_base ) . '" style="color:#9A3412;font-weight:bold;text-decoration:underline;">tarbwy.org/launch/vip</a>) دون الحاجة لاستخراج أي رمز من الرابط:
					</div>
					<div style="margin:12px auto;max-width:320px;background-color:#FFFFFF;border:2px dashed #9A3412;border-radius:8px;padding:14px 18px;">
						<div style="font-size:11px;color:#78716C;margin-bottom:4px;font-weight:bold;">رمز الدعوة الشخصي (Personal Invitation Code)</div>
						<div id="tarbwy-email-vip-code" dir="ltr" style="font-family:\'Courier New\',Courier,monospace;font-size:24px;font-weight:bold;letter-spacing:2px;color:#14532D;user-select:all;-webkit-user-select:all;cursor:pointer;">' . esc_html( $vip_code ) . '</div>
					</div>
					<div style="margin-top:14px;">
						<a href="' . esc_url( $copy_and_open_url ) . '" data-copy-vip-code="' . esc_attr( $vip_code ) . '" onclick="if(navigator.clipboard){navigator.clipboard.writeText(\'' . esc_js( $vip_code ) . '\');this.innerHTML=\'&#10003; تم نسخ رمز الدعوة الشخصي (' . esc_js( $vip_code ) . ')\';this.style.backgroundColor=\'#14532D\';return false;}" style="display:inline-block;background-color:#1C1917;color:#FFFFFF !important;font-family:Tahoma,Arial,sans-serif;font-size:13px;font-weight:bold;text-decoration:none;padding:10px 22px;border-radius:6px;border:1px solid #1C1917;">
							نسخ رمز الدعوة الشخصي (Copy Code)
						</a>
					</div>
				</td>
			</tr>
		</table>';
	}

	/**
	 * Render a real ISO/IEC 18004 HTML Table QR Code Matrix + Ticket Pass Box for a single pass
	 *
	 * @param array  $pass      Single pass array ('guest_name', 'guest_type', 'ticket_reference', 'qr_payload', 'ticket_url').
	 * @param int    $index     1-based pass index.
	 * @param int    $total     Total passes in this email.
	 * @param string $lang_dir  'rtl' or 'ltr'.
	 * @return string HTML table block.
	 */
	public static function render_single_qr_pass_card( $pass, $index = 1, $total = 1, $lang_dir = 'rtl' ) {
		$qr_payload = QRGenerator::get_canonical_qr_payload( $pass );
		$tkt_ref    = ( ! empty( $pass['ticket_reference'] ) && '-' !== trim( (string) $pass['ticket_reference'] ) ) ? (string) $pass['ticket_reference'] : $qr_payload;
		$ticket_url = isset( $pass['ticket_url'] ) ? (string) $pass['ticket_url'] : home_url( '/launch/ticket' );
		$holder     = isset( $pass['guest_name'] ) ? (string) $pass['guest_name'] : 'المشارك';
		$gtype      = isset( $pass['guest_type'] ) ? (string) $pass['guest_type'] : 'public';

		$qr_table_html = QRGenerator::render_qr_html_table( $qr_payload, 6 );

		$role_badge = 'companion' === $gtype
			? 'بطاقة مرافق معتمد · COMPANION PASS'
			: ( 'vip' === $gtype ? 'بطاقة كبار الشخصيات · VIP PASS' : 'بطاقة الضيف الأساسي · MAIN ATTENDEE PASS' );
		if ( $total > 1 ) {
			$role_badge .= sprintf( ' (%d / %d)', $index, $total );
		}

		$align = ( 'ltr' === $lang_dir ) ? 'left' : 'right';

		return '
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;background-color:#F8FAFC;border:2px dashed #14532D;border-radius:10px;overflow:hidden;">
			<tr>
				<td style="padding:20px 18px 14px 18px;direction:' . esc_attr( $lang_dir ) . ';text-align:' . esc_attr( $align ) . ';border-bottom:1px solid #E2E8F0;">
					<div style="font-size:11px;font-weight:bold;color:#9A3412;letter-spacing:0.4px;margin-bottom:6px;">' . esc_html( $role_badge ) . '</div>
					<div style="font-size:18px;font-weight:bold;color:#14532D;margin-bottom:8px;line-height:1.4;">' . esc_html( $holder ) . '</div>
					<div style="font-size:13px;color:#44403C;line-height:1.6;">رقم التذكرة المرجعي: <strong dir="ltr" style="display:inline-block;font-family:monospace;background-color:#E2E8F0;padding:2px 8px;border-radius:4px;color:#0F172A;">' . esc_html( $tkt_ref ) . '</strong></div>
				</td>
			</tr>
			<tr>
				<td align="center" style="padding:20px 18px;background-color:#FFFFFF;text-align:center;">
					' . $qr_table_html . '
					<div dir="ltr" style="font-size:12px;font-family:monospace;font-weight:bold;color:#0F172A;margin-top:8px;letter-spacing:1px;text-align:center;">' . esc_html( $qr_payload ) . '</div>
					<div style="font-size:12px;color:#57534E;margin-top:12px;direction:' . esc_attr( $lang_dir ) . ';">رابط تحميل وطباعة بطاقة الدخول الفردية (QR):</div>
					<div style="margin-top:4px;direction:ltr;text-align:center;"><a href="' . esc_url( $ticket_url ) . '" style="color:#14532D;font-size:12px;font-weight:bold;text-decoration:underline;word-break:break-all;">' . esc_html( $ticket_url ) . '</a></div>
				</td>
			</tr>
		</table>';
	}

	/**
	 * Render 1 or more QR Pass Cards (supports Main Attendee + N Companions)
	 *
	 * @param array  $data     Normalized placeholders array.
	 * @param string $lang_dir 'rtl' or 'ltr'.
	 * @return string Combined HTML for all passes.
	 */
	public static function render_inline_qr_pass_html( $data, $lang_dir = 'rtl' ) {
		if ( ! empty( $data['all_tickets'] ) && is_array( $data['all_tickets'] ) ) {
			$total = count( $data['all_tickets'] );
			$html  = '';
			if ( $total > 1 ) {
				$html .= '<div style="margin:20px 0 8px 0;padding:10px 14px;background-color:#ECFDF5;border:1px solid #A7F3D0;border-radius:6px;font-size:13px;font-weight:bold;color:#14532D;text-align:' . ( 'ltr' === $lang_dir ? 'left' : 'right' ) . ';">إجمالي بطاقات الدخول الفردية الصادرة لهذا التسجيل: (' . (int) $total . ') بطاقات — لكل مشارك ومرافق رمز QR مستقل</div>';
			}
			$idx = 1;
			foreach ( $data['all_tickets'] as $pass ) {
				$html .= self::render_single_qr_pass_card( $pass, $idx, $total, $lang_dir );
				++$idx;
			}
			return $html;
		}

		$single_payload = QRGenerator::get_canonical_qr_payload( $data );

		return self::render_single_qr_pass_card(
			array(
				'guest_name'       => $data['guest_name'] ?? 'المشارك الكريم',
				'guest_type'       => $data['guest_type'] ?? 'public',
				'ticket_reference' => $single_payload,
				'qr_payload'       => $single_payload,
				'ticket_url'       => $data['ticket_url'] ?? home_url( '/launch/ticket' ),
			),
			1,
			1,
			$lang_dir
		);
	}

	/**
	 * Render the complete email package (Subject, Responsive RTL/LTR HTML, Plain-Text Fallback, Sender, Headers)
	 *
	 * @param string $template_key Template key.
	 * @param array  $placeholders Placeholders array.
	 * @param int    $event_id     Event ID.
	 * @return array<string, mixed>
	 */
	public static function render_email_package( $template_key, $placeholders = array(), $event_id = 0 ) {
		$templates = self::get_templates();
		$tpl       = isset( $templates[ $template_key ] ) ? $templates[ $template_key ] : $templates['ticket_issued'];
		$sender    = self::get_sender_config( $event_id );
		$data      = self::normalize_placeholders( $placeholders, $event_id );

		if ( ! empty( $data['all_tickets'] ) && is_array( $data['all_tickets'] ) ) {
			$data['tickets_count'] = (string) count( $data['all_tickets'] );
		}

		$subject      = self::replace_placeholders( $tpl['subject'], $data, $event_id );
		$heading      = self::replace_placeholders( $tpl['heading'] ?? $tpl['title'], $data, $event_id );
		$badge        = self::replace_placeholders( $tpl['badge'] ?? 'إشعار رسمي · تربوي', $data, $event_id );
		$cta_label    = self::replace_placeholders( $tpl['cta_label'] ?? '', $data, $event_id );
		$accent_color = ! empty( $tpl['accent_color'] ) ? sanitize_hex_color( $tpl['accent_color'] ) : '#14532D';
		if ( ! $accent_color ) {
			$accent_color = '#14532D';
		}

		$lang_dir = isset( $placeholders['lang_dir'] ) && in_array( $placeholders['lang_dir'], array( 'rtl', 'ltr' ), true )
			? $placeholders['lang_dir']
			: ( isset( $tpl['lang_dir'] ) && in_array( $tpl['lang_dir'], array( 'rtl', 'ltr' ), true ) ? $tpl['lang_dir'] : 'rtl' );

		$cta_placeholder = isset( $tpl['cta_url_placeholder'] ) ? trim( (string) $tpl['cta_url_placeholder'] ) : '{event_url}';
		$cta_url         = $data['event_url'];
		if ( '{ticket_url}' === $cta_placeholder && ! empty( $data['ticket_url'] ) ) {
			$cta_url = $data['ticket_url'];
		} elseif ( '{vip_url}' === $cta_placeholder && ! empty( $data['vip_url'] ) ) {
			$cta_url = $data['vip_url'];
		} elseif ( filter_var( $cta_placeholder, FILTER_VALIDATE_URL ) ) {
			$cta_url = $cta_placeholder;
		}

		$raw_body   = (string) ( $tpl['body'] ?? '' );
		$has_qr_tag = ( false !== strpos( $raw_body, '{qr_code}' ) );
		$clean_body = str_replace( '{qr_code}', '', $raw_body );
		foreach ( $data as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$clean_body = str_replace( '{' . $k . '}', (string) $v, $clean_body );
			}
		}
		$clean_body = trim( $clean_body );

		$show_qr_box       = ! empty( $tpl['show_qr_box'] ) || $has_qr_tag;
		$show_vip_code_box = ! empty( $tpl['show_vip_code_box'] ) || in_array( $template_key, array( 'vip_invitation', 'vip_invitation_resent' ), true );
		$show_event_card   = ! empty( $tpl['show_event_card'] );

		$html_body = self::build_responsive_rtl_html_email(
			array(
				'subject'           => $subject,
				'heading'           => $heading,
				'badge'             => $badge,
				'accent_color'      => $accent_color,
				'lang_dir'          => $lang_dir,
				'body_text'         => $clean_body,
				'cta_label'         => $cta_label,
				'cta_url'           => $cta_url,
				'show_qr_box'       => $show_qr_box,
				'show_vip_code_box' => $show_vip_code_box,
				'show_event_card'   => $show_event_card,
				'data'              => $data,
				'sender'            => $sender,
			)
		);

		$plain_text = self::build_plain_text_email(
			array(
				'heading'           => $heading,
				'badge'             => $badge,
				'body_text'         => $clean_body,
				'cta_label'         => $cta_label,
				'cta_url'           => $cta_url,
				'show_qr_box'       => $show_qr_box,
				'show_vip_code_box' => $show_vip_code_box,
				'show_event_card'   => $show_event_card,
				'data'              => $data,
				'sender'            => $sender,
			)
		);

		return array(
			'template_key' => $template_key,
			'subject'      => $subject,
			'heading'      => $heading,
			'badge'        => $badge,
			'lang_dir'     => $lang_dir,
			'html'         => $html_body,
			'plain_text'   => $plain_text,
			'sender'       => $sender,
			'headers'      => self::build_mail_headers( $sender ),
		);
	}

	/**
	 * Build Email-Client-Compatible Responsive RTL/LTR HTML Email (Tables + Inline CSS + Mobile Breakpoints)
	 *
	 * @param array $args Render arguments.
	 * @return string Complete HTML document.
	 */
	public static function build_responsive_rtl_html_email( $args ) {
		$subject           = $args['subject'];
		$heading           = $args['heading'];
		$badge             = $args['badge'];
		$accent_color      = $args['accent_color'];
		$lang_dir          = isset( $args['lang_dir'] ) && 'ltr' === $args['lang_dir'] ? 'ltr' : 'rtl';
		$lang_code         = ( 'ltr' === $lang_dir ) ? 'en' : 'ar';
		$text_align        = ( 'ltr' === $lang_dir ) ? 'left' : 'right';
		$opp_align         = ( 'ltr' === $lang_dir ) ? 'right' : 'left';
		$body_text         = $args['body_text'];
		$cta_label         = $args['cta_label'];
		$cta_url           = $args['cta_url'];
		$show_qr_box       = $args['show_qr_box'];
		$show_vip_code_box = ! empty( $args['show_vip_code_box'] );
		$show_event_card   = $args['show_event_card'];
		$data              = $args['data'];
		$sender            = $args['sender'];

		$paragraphs_html = '';
		$blocks          = preg_split( '/\n\s*\n/', $body_text );
		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( '' === $block ) {
				continue;
			}
			$escaped_block = nl2br( esc_html( $block ) );
			$escaped_block = preg_replace(
				'#(https?://[^\s<]+)#i',
				'<a href="$1" style="color:' . esc_attr( $accent_color ) . ';font-weight:bold;text-decoration:underline;word-break:break-all;">$1</a>',
				$escaped_block
			);
			$paragraphs_html .= '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.85;color:#292524;text-align:' . esc_attr( $text_align ) . ';">' . $escaped_block . '</p>';
		}

		if ( ! empty( $sender['logo_url'] ) ) {
			$brand_header_html = '<img src="' . esc_url( $sender['logo_url'] ) . '" alt="تربوي — الاتحاد العربي للقيادة التربوية" style="max-height:56px;max-width:220px;width:auto;height:auto;display:inline-block;border:0;" />';
		} else {
			$brand_header_html = '
			<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="direction:' . esc_attr( $lang_dir ) . ';">
				<tr>
					<td valign="middle" style="padding-' . esc_attr( $opp_align ) . ':12px;">
						<div style="width:42px;height:42px;line-height:42px;background-color:#14532D;color:#F7F4EE;font-weight:bold;font-size:20px;text-align:center;border-radius:8px;border:2px solid #9A3412;">ت</div>
					</td>
					<td valign="middle" style="text-align:' . esc_attr( $text_align ) . ';">
						<div style="font-size:18px;font-weight:bold;color:#14532D;line-height:1.3;">تربوي — الاتحاد العربي للقيادة التربوية</div>
						<div style="font-size:11px;color:#78716C;letter-spacing:0.4px;">TARBWY · ARAB UNION FOR EDUCATIONAL LEADERSHIP</div>
					</td>
				</tr>
			</table>';
		}

		$vip_code_box_html = $show_vip_code_box ? self::render_vip_personal_code_box_html( $data, $lang_dir ) : '';
		$qr_box_html       = $show_qr_box ? self::render_inline_qr_pass_html( $data, $lang_dir ) : '';

		$event_card_html = '';
		if ( $show_event_card ) {
			$ref_display = ( ! empty( $data['ticket_reference'] ) && '-' !== $data['ticket_reference'] )
				? $data['ticket_reference']
				: ( $data['reference_code'] ?? '-' );

			$event_card_html = '
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;background-color:#FAF8F5;border:1px solid #E7E5E4;border-' . esc_attr( $text_align ) . ':4px solid ' . esc_attr( $accent_color ) . ';border-radius:6px;">
				<tr>
					<td style="padding:18px 20px;direction:' . esc_attr( $lang_dir ) . ';text-align:' . esc_attr( $text_align ) . ';">
						<div style="font-size:12px;font-weight:bold;color:#9A3412;margin-bottom:10px;">بطاقة بيانات الحفل والموعد · EVENT DETAILS</div>
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;color:#292524;line-height:1.7;">
							<tr>
								<td width="110" valign="top" style="padding:4px 0;color:#78716C;font-weight:bold;">الفعالية:</td>
								<td valign="top" style="padding:4px 0;font-weight:bold;color:#1C1917;">' . esc_html( $data['event_title'] ) . '</td>
							</tr>
							<tr>
								<td width="110" valign="top" style="padding:4px 0;color:#78716C;font-weight:bold;">التاريخ والوقت:</td>
								<td valign="top" style="padding:4px 0;">' . esc_html( $data['event_date'] ) . '</td>
							</tr>
							<tr>
								<td width="110" valign="top" style="padding:4px 0;color:#78716C;font-weight:bold;">المكان / القاعة:</td>
								<td valign="top" style="padding:4px 0;">' . esc_html( $data['venue_name'] ) . '</td>
							</tr>
							' . ( '-' !== $ref_display ? '
							<tr>
								<td width="110" valign="top" style="padding:4px 0;color:#78716C;font-weight:bold;">الرقم المرجعي:</td>
								<td valign="top" style="padding:4px 0;"><code style="background-color:#E7E5E4;padding:2px 6px;border-radius:4px;font-family:monospace;">' . esc_html( $ref_display ) . '</code></td>
							</tr>' : '' ) . '
						</table>
					</td>
				</tr>
			</table>';
		}

		$cta_html = '';
		if ( ! empty( $cta_label ) && ! empty( $cta_url ) ) {
			$cta_html = '
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 18px 0;">
				<tr>
					<td align="center" style="text-align:center;">
						<a href="' . esc_url( $cta_url ) . '" class="tarbwy-email-btn" style="display:inline-block;background-color:' . esc_attr( $accent_color ) . ';color:#FFFFFF !important;font-family:Tahoma,Arial,sans-serif;font-size:15px;font-weight:bold;text-decoration:none;padding:14px 32px;border-radius:6px;border:1px solid ' . esc_attr( $accent_color ) . ';min-width:220px;text-align:center;">
							' . esc_html( $cta_label ) . ' &larr;
						</a>
					</td>
				</tr>
				<tr>
					<td align="center" style="padding-top:10px;text-align:center;font-size:11px;color:#78716C;">
						في حال تعذر النقر على الزر، يمكنكم نسخ الرابط التالي وفتحه في المتصفح:<br>
						<a href="' . esc_url( $cta_url ) . '" style="color:' . esc_attr( $accent_color ) . ';text-decoration:underline;word-break:break-all;">' . esc_html( $cta_url ) . '</a>
					</td>
				</tr>
			</table>';
		}

		return '<!DOCTYPE html>
<html lang="' . esc_attr( $lang_code ) . '" dir="' . esc_attr( $lang_dir ) . '" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<title>' . esc_html( $subject ) . '</title>
	<style type="text/css">
		.tarbwy-email-wrapper, .tarbwy-email-wrapper table, .tarbwy-email-wrapper td, .tarbwy-email-wrapper p, .tarbwy-email-wrapper a, .tarbwy-email-wrapper h1 {
			font-family: "Segoe UI", Tahoma, Arial, sans-serif;
		}
		@media only screen and (max-width: 620px) {
			.tarbwy-email-wrapper {
				padding: 10px !important;
			}
			.tarbwy-email-container {
				width: 100% !important;
			}
			.tarbwy-email-header {
				padding: 18px 20px !important;
			}
			.tarbwy-email-content {
				padding: 22px 18px !important;
			}
			.tarbwy-email-heading {
				font-size: 20px !important;
			}
			.tarbwy-email-btn {
				display: block !important;
				width: 100% !important;
				box-sizing: border-box !important;
				padding: 15px 18px !important;
			}
		}
	</style>
</head>
<body dir="' . esc_attr( $lang_dir ) . '" style="margin:0;padding:0;background-color:#F3EFE6;color:#1C1917;direction:' . esc_attr( $lang_dir ) . ';text-align:' . esc_attr( $text_align ) . ';-webkit-font-smoothing:antialiased;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="tarbwy-email-wrapper" style="background-color:#F3EFE6;padding:28px 14px;direction:' . esc_attr( $lang_dir ) . ';">
		<tr>
			<td align="center">
				<table role="presentation" width="620" cellpadding="0" cellspacing="0" border="0" class="tarbwy-email-container" style="max-width:620px;width:100%;background-color:#FFFFFF;border:1px solid #E5E0D8;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(28,25,23,0.06);">
					<!-- Top Institutional Accent Bar -->
					<tr>
						<td style="height:6px;background-color:' . esc_attr( $accent_color ) . ';font-size:1px;line-height:1px;">&nbsp;</td>
					</tr>

					<!-- Brand Header (Full-width Logo row without side-by-side badge collision) -->
					<tr>
						<td class="tarbwy-email-header" style="padding:24px 32px 20px 32px;border-bottom:1px solid #F0ECE1;background-color:#FDFBF7;direction:' . esc_attr( $lang_dir ) . ';text-align:' . esc_attr( $text_align ) . ';">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
								<tr>
									<td align="' . esc_attr( $text_align ) . '" valign="middle" style="text-align:' . esc_attr( $text_align ) . ';">
										' . $brand_header_html . '
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<!-- Main Content Body -->
					<tr>
						<td class="tarbwy-email-content" style="padding:28px 32px 30px 32px;direction:' . esc_attr( $lang_dir ) . ';text-align:' . esc_attr( $text_align ) . ';">
							' . ( ! empty( $badge ) ? '
							<div style="margin:0 0 14px 0;text-align:' . esc_attr( $text_align ) . ';">
								<span style="display:inline-block;background-color:#F5F0E6;color:' . esc_attr( $accent_color ) . ';border:1px solid #E5DEC9;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:bold;line-height:1.5;">
									' . esc_html( $badge ) . '
								</span>
							</div>' : '' ) . '
							<h1 class="tarbwy-email-heading" style="margin:0 0 20px 0;font-size:22px;line-height:1.45;font-weight:bold;color:#14532D;text-align:' . esc_attr( $text_align ) . ';">
								' . esc_html( $heading ) . '
							</h1>

							' . $paragraphs_html . '

							' . $vip_code_box_html . '

							' . $qr_box_html . '

							' . $event_card_html . '

							' . $cta_html . '
						</td>
					</tr>

					<!-- Institutional Footer -->
					<tr>
						<td style="padding:22px 32px;background-color:#FAF8F5;border-top:1px solid #EBE6DF;direction:' . esc_attr( $lang_dir ) . ';text-align:' . esc_attr( $text_align ) . ';font-size:12px;line-height:1.7;color:#57534E;">
							<div style="font-weight:bold;color:#14532D;margin-bottom:4px;">' . esc_html( $sender['sender_name'] ) . '</div>
							<div>الموقع الرسمي: <a href="' . esc_url( home_url( '/launch' ) ) . '" style="color:#14532D;text-decoration:underline;">tarbwy.org/launch</a> &nbsp;|&nbsp; للاستفسارات: <a href="mailto:' . esc_attr( $sender['contact_email'] ) . '" style="color:#14532D;text-decoration:underline;">' . esc_html( $sender['contact_email'] ) . '</a></div>
							<div style="margin-top:8px;font-size:11px;color:#78716C;">
								صدرت هذه الرسالة الرسمية عبر نظام إدارة الفعاليات المعتمد من عنوان الإرسال الموثق (<code>' . esc_html( $sender['sender_email'] ) . '</code>). جميع بطاقات الدخول فردية ومحمية بتشفير SHA-256.
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';
	}

	/**
	 * Build Plain-Text Fallback Version (Attached automatically as PHPMailer AltBody)
	 *
	 * @param array $args Render arguments.
	 * @return string Plain-text email.
	 */
	public static function build_plain_text_email( $args ) {
		$sender = $args['sender'];
		$data   = $args['data'];
		$lines  = array();

		$lines[] = '============================================================';
		$lines[] = $sender['sender_name'];
		$lines[] = '[' . $args['badge'] . ']';
		$lines[] = '============================================================';
		$lines[] = '';
		$lines[] = $args['heading'];
		$lines[] = '------------------------------------------------------------';
		$lines[] = $args['body_text'];
		$lines[] = '';

		if ( ! empty( $args['show_vip_code_box'] ) ) {
			$vip_code = ! empty( $data['vip_code'] ) ? (string) $data['vip_code'] : 'VIP-8F4A-2C91';
			$lines[]  = '--- رمز الدعوة الشخصي (Personal Invitation Code) ---';
			$lines[]  = 'رمز الدعوة المستقل الخاص بكم: ' . $vip_code;
			$lines[]  = 'بوابة كبار الشخصيات المباشرة: ' . home_url( '/launch/vip' );
			$lines[]  = '';
		}

		if ( ! empty( $args['show_qr_box'] ) && ! empty( $data['all_tickets'] ) && is_array( $data['all_tickets'] ) ) {
			$lines[] = '--- بطاقات الدخول الفردية الصادرة (QR Passes) ---';
			foreach ( $data['all_tickets'] as $idx => $pass ) {
				$num     = $idx + 1;
				$lines[] = sprintf(
					'%d) %s — رقم التذكرة: %s — رابط البطاقة: %s',
					$num,
					$pass['guest_name'] ?? '',
					$pass['ticket_reference'] ?? '',
					$pass['ticket_url'] ?? ''
				);
			}
			$lines[] = '';
		}

		if ( ! empty( $args['show_event_card'] ) ) {
			$lines[] = '--- تفاصيل الفعالية ---';
			$lines[] = '* الفعالية: ' . $data['event_title'];
			$lines[] = '* التاريخ والوقت: ' . $data['event_date'];
			$lines[] = '* المكان: ' . $data['venue_name'];
			if ( ! empty( $data['ticket_reference'] ) && '-' !== $data['ticket_reference'] ) {
				$lines[] = '* رقم التذكرة: ' . $data['ticket_reference'];
			} elseif ( ! empty( $data['reference_code'] ) && '-' !== $data['reference_code'] ) {
				$lines[] = '* رقم الطلب المرجعي: ' . $data['reference_code'];
			}
			$lines[] = '';
		}

		if ( ! empty( $args['cta_label'] ) && ! empty( $args['cta_url'] ) ) {
			$lines[] = $args['cta_label'] . ':';
			$lines[] = $args['cta_url'];
			$lines[] = '';
		}

		$lines[] = '------------------------------------------------------------';
		$lines[] = 'للتواصل والاستفسارات: ' . $sender['contact_email'];
		$lines[] = 'الموقع الرسمي: ' . home_url( '/launch' );
		$lines[] = 'المرسل الرسمي: ' . $sender['sender_name'] . ' <' . $sender['sender_email'] . '>';

		return implode( "\n", $lines );
	}

	/**
	 * Backward-compatible helper for wrapping plain text into RTL HTML
	 */
	public static function wrap_rtl_html_email( $subject, $body_text ) {
		$sender = self::get_sender_config();
		$data   = self::normalize_placeholders();
		return self::build_responsive_rtl_html_email(
			array(
				'subject'         => $subject,
				'heading'         => $subject,
				'badge'           => 'إشعار رسمي · تربوي',
				'accent_color'    => '#14532D',
				'lang_dir'        => 'rtl',
				'body_text'       => $body_text,
				'cta_label'       => 'زيارة صفحة الحدث',
				'cta_url'         => home_url( '/launch' ),
				'show_qr_box'     => false,
				'show_event_card' => true,
				'data'            => $data,
				'sender'          => $sender,
			)
		);
	}
}
