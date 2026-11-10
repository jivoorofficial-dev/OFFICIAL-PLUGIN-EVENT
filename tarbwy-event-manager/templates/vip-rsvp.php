<?php
/**
 * Private VIP Invitation & RSVP Page (/launch/vip)
 *
 * Spec Section 4.3 & Final Launch Fix #3:
 * - Not shown in public navigation.
 * - Supports standalone Personal Invitation Code (e.g. VIP-8F4A-2C91) as well as 48-char token links.
 * - Includes dedicated Copy Code (نسخ رمز الدعوة) and Paste Code (لصق الرمز) buttons.
 * - Never reveals guest data before server verification of the code.
 * - Sends Referrer-Policy: no-referrer.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Security::send_no_leak_headers();

$raw_token = '';
if ( isset( $_GET['token'] ) ) {
	$raw_token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
} elseif ( isset( $_GET['code'] ) ) {
	$raw_token = sanitize_text_field( wp_unslash( $_GET['code'] ) );
}
$auto_copy = isset( $_GET['copy'] ) && '1' === (string) $_GET['copy'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="referrer" content="no-referrer">
	<meta name="robots" content="noindex, nofollow">
	<title>الدعوة الخاصة لكبار الشخصيات (VIP) — تربوي</title>
	<link rel="stylesheet" href="<?php echo esc_url( TARBWY_EM_URL . 'assets/css/tarbwy-public.css?ver=' . TARBWY_EM_VERSION ); ?>">
</head>
<body class="tarbwy-public-body" dir="rtl">
	<header class="tarbwy-header">
		<div class="tarbwy-container tarbwy-header-inner">
			<div class="tarbwy-brand">
				<span class="tarbwy-brand-mark" aria-hidden="true">ت</span>
				<span class="tarbwy-brand-title">تربوي — بوابة الدعوات الخاصة (VIP)</span>
			</div>
			<a href="<?php echo esc_url( home_url( '/launch' ) ); ?>" class="tarbwy-btn tarbwy-btn-outline">صفحة الحدث العامة</a>
		</div>
	</header>

	<main class="tarbwy-main">
		<div class="tarbwy-container" style="max-width: 720px; padding-top: 40px; padding-bottom: 64px;">

			<!-- Toast Banner when Personal Invitation Code is copied -->
			<div id="tarbwy-vip-copy-toast" class="tarbwy-alert tarbwy-alert-success" style="margin-bottom: 18px;" hidden>
				<strong>✓ تم نسخ رمز الدعوة الشخصي بنجاح!</strong> يمكنكم استخدامه مباشرة للتحقق وتأكيد الحضور أدناه.
			</div>

			<!-- Step 1: Enter / Verify Standalone Personal Invitation Code (Guest PII is NEVER rendered before verification) -->
			<div id="tarbwy-vip-token-gate" class="tarbwy-card" <?php echo ! empty( $raw_token ) ? 'hidden' : ''; ?>>
				<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
					<span class="tarbwy-vip-code-pill">بوابة كبار الشخصيات · VIP RSVP</span>
					<span style="font-size:0.82rem; color:var(--tarbwy-muted);">تحقق مشفر فوري</span>
				</div>
				<h1 class="tarbwy-section-title" style="margin-bottom:8px;">التحقق من رمز الدعوة الشخصي (VIP)</h1>
				<p style="margin-top:0; color:var(--tarbwy-muted); line-height:1.75;">
					أدخل <strong>رمز الدعوة الشخصي (Personal Invitation Code)</strong> المرسل إليكم في رسالة الدعوة الرسمية (مثال: <code dir="ltr" style="font-weight:700; color:var(--tarbwy-primary);">VIP-8F4A-2C91</code>) للوصول المباشر إلى بطاقة دعوتكم دون الحاجة لاستخراج أي رابط:
				</p>
				<form id="tarbwy-vip-verify-form" class="tarbwy-form">
					<div class="tarbwy-field">
						<label for="vip_token_input">رمز الدعوة الشخصي (Personal Invitation Code) <span class="req">*</span></label>
						<div class="tarbwy-vip-input-group">
							<input
								type="text"
								id="vip_token_input"
								name="token"
								required
								dir="ltr"
								autocomplete="off"
								spellcheck="false"
								placeholder="VIP-XXXX-XXXX"
								class="tarbwy-vip-code-input"
								value="<?php echo esc_attr( $raw_token ); ?>"
							>
							<button type="button" id="btn-vip-paste-code" class="tarbwy-btn tarbwy-btn-outline" title="لصق الرمز من الحافظة">
								لصق الرمز
							</button>
							<button type="button" id="btn-vip-copy-input-code" class="tarbwy-btn tarbwy-btn-outline" title="نسخ الرمز الحالي">
								نسخ الرمز
							</button>
						</div>
						<small style="display:block; margin-top:6px; color:var(--tarbwy-muted);">
							يقبل النظام رمز الدعوة الشخصي المختصر (<code dir="ltr">VIP-XXXX-XXXX</code>) أو رمز الرابط الكامل.
						</small>
					</div>
					<div id="tarbwy-vip-verify-error" class="tarbwy-alert tarbwy-alert-error" style="margin-top:14px;" hidden></div>
					<div class="tarbwy-form-submit">
						<button type="submit" id="btn-vip-verify-submit" class="tarbwy-btn tarbwy-btn-primary tarbwy-btn-lg" style="width:100%;">
							فتح الدعوة الخاصة والتحقق من الرمز
						</button>
					</div>
				</form>
			</div>

			<!-- Loading state when token/code is passed in URL -->
			<div id="tarbwy-vip-loading" class="tarbwy-card" <?php echo empty( $raw_token ) ? 'hidden' : ''; ?>>
				<p style="margin:0; font-weight:700; color:var(--tarbwy-primary);">جاري التحقق من رمز الدعوة الشخصي واسترجاع بيانات الدعوة...</p>
			</div>

			<!-- Step 2: Verified VIP Invitation & RSVP Card -->
			<div id="tarbwy-vip-rsvp-card" class="tarbwy-card" hidden>
				<!-- Standalone Personal Invitation Code Banner with Dedicated Copy Code Button -->
				<div class="tarbwy-vip-personal-code-banner">
					<div>
						<span class="tarbwy-vip-code-label">رمز الدعوة الشخصي (Personal Invitation Code)</span>
						<strong id="vip-personal-code-display" class="tarbwy-vip-code-value" dir="ltr">VIP-XXXX-XXXX</strong>
					</div>
					<div style="display:flex; gap:8px; flex-wrap:wrap;">
						<button type="button" id="btn-vip-copy-personal-code" class="tarbwy-btn tarbwy-btn-primary" style="min-height:40px; padding:8px 16px; font-size:0.86rem;">
							نسخ الرمز (Copy Code)
						</button>
						<button type="button" id="btn-vip-change-code" class="tarbwy-btn tarbwy-btn-outline" style="min-height:40px; padding:8px 14px; font-size:0.84rem;">
							تغيير الرمز
						</button>
					</div>
				</div>

				<div class="tarbwy-vip-badge" style="margin-top:16px; display:inline-block; background:#FFFBEB; color:#9A3412; border:1px solid #FDE68A; padding:4px 12px; font-size:0.82rem; font-weight:700;">
					دعوة خاصة · كبار الشخصيات (VIP)
				</div>
				<h1 id="vip-event-title" class="tarbwy-section-title" style="margin-top:10px;"></h1>
				<p class="tarbwy-vip-greeting" style="font-size:1.05rem; line-height:1.8;">
					مرحباً بسعادة <strong id="vip-guest-name" style="color:var(--tarbwy-primary);"></strong>
					<span id="vip-guest-org" class="tarbwy-muted"></span>
				</p>
				<p>يتشرف «تربوي — الاتحاد العربي للقيادة التربوية» بدعوتكم لحضور حفل الإشهار الرسمي. يرجى اختيار حالة حضوركم أدناه:</p>

				<form id="tarbwy-vip-rsvp-form" class="tarbwy-form">
					<div class="tarbwy-rsvp-choices" style="display:grid; grid-template-columns:repeat(2, 1fr); gap:14px; margin:20px 0;">
						<label class="tarbwy-radio-card" style="display:flex; align-items:flex-start; gap:10px; padding:16px; border:2px solid var(--tarbwy-primary); background:#F0FDF4; cursor:pointer;">
							<input type="radio" name="decision" value="attend" checked style="margin-top:4px;">
							<div>
								<strong style="display:block; color:var(--tarbwy-primary); font-size:1rem;">سأحضر (تأكيد مباشر)</strong>
								<span style="font-size:0.84rem; color:var(--tarbwy-muted);">يتم تأكيد الحضور وإصدار بطاقة الدخول الفورية المزودة برمز QR.</span>
							</div>
						</label>
						<label class="tarbwy-radio-card" style="display:flex; align-items:flex-start; gap:10px; padding:16px; border:1px solid var(--tarbwy-border-strong); background:#FFFFFF; cursor:pointer;">
							<input type="radio" name="decision" value="decline" style="margin-top:4px;">
							<div>
								<strong style="display:block; color:var(--tarbwy-text); font-size:1rem;">أعتذر عن الحضور</strong>
								<span style="font-size:0.84rem; color:var(--tarbwy-muted);">إبلاغ فريق المراسم بالاعتذار عن عدم التمكن من الحضور.</span>
							</div>
						</label>
					</div>

					<div id="vip-companions-section" class="tarbwy-companions-box">
						<div class="tarbwy-companions-header">
							<div>
								<h3>المرافقون (اختياري)</h3>
								<p class="tarbwy-muted">يُسمح بإضافة مرافقين حسب الحد المخصص لدعوتكم، ويُصدر لكل مرافق سجل وبطاقة QR مستقلة.</p>
							</div>
							<button type="button" id="btn-add-vip-companion" class="tarbwy-btn tarbwy-btn-outline">
								+ إضافة مرافق
							</button>
						</div>
						<div id="vip-companions-list" class="tarbwy-companions-list"></div>
					</div>

					<div id="tarbwy-vip-rsvp-feedback" class="tarbwy-alert" style="margin-top:16px;" hidden></div>

					<div class="tarbwy-form-submit">
						<button type="submit" id="btn-submit-vip-rsvp" class="tarbwy-btn tarbwy-btn-primary tarbwy-btn-lg" style="width:100%;">
							حفظ الرد وإصدار بطاقة الدخول (QR)
						</button>
					</div>
				</form>

				<!-- Issued Tickets Container (populated immediately on confirmation or if already confirmed) -->
				<div id="tarbwy-vip-tickets-result" class="tarbwy-tickets-result" style="margin-top:24px;" hidden>
					<h3 style="margin:0 0 8px; color:var(--tarbwy-primary);">بطاقات الدخول الفردية الصادرة (QR Passes)</h3>
					<p style="margin:0 0 14px; font-size:0.9rem; color:var(--tarbwy-muted);">يرجى حفظ أو طباعة البطاقات التالية لإبرازها عند بوابة الاستقبال يوم الحفل:</p>
					<div id="vip-tickets-cards-grid" class="tarbwy-grid-2"></div>
				</div>
			</div>
		</div>
	</main>

	<script>
	window.TarbwyVipConfig = {
		restUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( RestAPI::NAMESPACE_V1 ) ) ); ?>,
		initialToken: <?php echo wp_json_encode( $raw_token ); ?>,
		autoCopy: <?php echo wp_json_encode( (bool) $auto_copy ); ?>,
		ticketBaseUrl: <?php echo wp_json_encode( esc_url_raw( home_url( '/launch/ticket' ) ) ); ?>
	};
	</script>
	<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-qr.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
	<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-app.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
</body>
</html>
