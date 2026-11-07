<?php
/**
 * Private VIP Invitation & RSVP Page (/launch/vip)
 *
 * Security & Spec Rules:
 * - Does not appear in public navigation.
 * - Sends Referrer-Policy: no-referrer header.
 * - Never reveals guest data before cryptographic token verification via SHA-256 hash.
 * - Options: «سأحضر» (Immediately confirms RSVP and issues individual QR tickets) or «أعتذر» (Declines).
 * - Supports companions up to admin-defined limit, each getting an independent guest record and QR.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Security::send_no_leak_headers();

$raw_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="referrer" content="no-referrer">
	<meta name="robots" content="noindex, nofollow">
	<title>بوابة الدعوات الخاصة لكبار الشخصيات (VIP) — تربوي</title>
	<link rel="stylesheet" href="<?php echo esc_url( TARBWY_EM_URL . 'assets/css/tarbwy-public.css?ver=' . TARBWY_EM_VERSION ); ?>">
</head>
<body class="tarbwy-public-body tarbwy-vip-body" dir="rtl">
	<header class="tarbwy-header">
		<div class="tarbwy-container tarbwy-header-inner">
			<div class="tarbwy-brand">
				<span class="tarbwy-brand-title">تربوي — الاتحاد العربي للقيادة التربوية</span>
			</div>
			<div>
				<span class="tarbwy-kicker">بوابة كبار الشخصيات · دعوة خاصة</span>
			</div>
		</div>
	</header>

	<main class="tarbwy-section">
		<div class="tarbwy-container" style="max-width: 760px;">
			<div class="tarbwy-form-card" id="tarbwy-vip-portal">
				<!-- Token Entry / Verification State (Never reveals guest PII prior to verification) -->
				<div id="tarbwy-vip-token-gate" <?php echo ! empty( $raw_token ) ? 'hidden' : ''; ?>>
					<span class="tarbwy-section-num">التحقق الأمني من رمز الدعوة</span>
					<h2>بوابة الرد على الدعوة الخاصة (VIP RSVP)</h2>
					<p class="tarbwy-muted">يرجى إدخال رمز الدعوة الشخصي المرسل إليكم أو استخدام الرابط المباشر المرفق في رسالتكم الرسمية.</p>
					<div class="tarbwy-field" style="margin-top: 16px;">
						<label for="vip_token_input">رمز الدعوة الشخصي</label>
						<input type="text" id="vip_token_input" dir="ltr" placeholder="أدخل الرمز السري المكون من 48 حرفاً..." value="<?php echo esc_attr( $raw_token ); ?>">
					</div>
					<button type="button" id="tarbwy-vip-verify-btn" class="tarbwy-btn tarbwy-btn-primary" style="margin-top: 16px;">
						التحقق وعرض الدعوة
					</button>
				</div>

				<div id="tarbwy-vip-loading" <?php echo empty( $raw_token ) ? 'hidden' : ''; ?>>
					<p>جاري التحقق من صلاحية الدعوة الشخصية المشفرة...</p>
				</div>

				<div id="tarbwy-vip-error" class="tarbwy-alert tarbwy-alert-error" hidden></div>

				<!-- Verified VIP Invitation Details & RSVP Form -->
				<div id="tarbwy-vip-content" hidden>
					<span class="tarbwy-section-num">دعوة رسمية خاصة · VIP</span>
					<h2 id="vip-event-title">حفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية»</h2>
					<div class="tarbwy-vip-welcome">
						<p>مرحباً بسعادة الضيف الكريم: <strong id="vip-guest-name"></strong></p>
						<p class="tarbwy-muted"><span id="vip-guest-title"></span> — <span id="vip-guest-org"></span></p>
					</div>

					<form id="tarbwy-vip-rsvp-form" class="tarbwy-form">
						<div class="tarbwy-rsvp-choices">
							<label class="tarbwy-choice-card">
								<input type="radio" name="decision" value="attend" checked>
								<span class="tarbwy-choice-title">سأحضر (تأكيد مباشر وإصدار البطاقة)</span>
								<span class="tarbwy-choice-desc">يتم تأكيد مقعدكم فوراً وإصدار بطاقة الدخول الشخصية المزودة برمز QR.</span>
							</label>
							<label class="tarbwy-choice-card">
								<input type="radio" name="decision" value="decline">
								<span class="tarbwy-choice-title">أعتذر عن عدم التمكن من الحضور</span>
								<span class="tarbwy-choice-desc">إبلاغ الأمانة العامة بالاعتذار عن عدم الحضور في هذا الموعد.</span>
							</label>
						</div>

						<div id="tarbwy-vip-companions-wrap" class="tarbwy-companions-box">
							<div class="tarbwy-companions-header">
								<div>
									<h3>المرافقون (الحد الأقصى المسموح: <span id="vip-max-companions">2</span>)</h3>
									<p class="tarbwy-muted">يُصدر لكل مرافق سجل مستقل وبطاقة دخول QR مستقلة خاصة به.</p>
								</div>
								<button type="button" id="tarbwy-vip-add-comp-btn" class="tarbwy-btn tarbwy-btn-outline">
									+ إضافة مرافق
								</button>
							</div>
							<div id="tarbwy-vip-companions-list"></div>
						</div>

						<div id="tarbwy-vip-feedback" class="tarbwy-feedback" hidden></div>

						<div class="tarbwy-form-submit">
							<button type="submit" id="tarbwy-vip-submit-btn" class="tarbwy-btn tarbwy-btn-primary tarbwy-btn-lg">
								اعتماد الرد وإصدار بطاقات الدخول
							</button>
						</div>
					</form>

					<!-- Issued Tickets Output Area -->
					<div id="tarbwy-vip-tickets-output" hidden></div>
				</div>
			</div>
		</div>
	</main>

	<script>
	window.TarbwyVipConfig = {
		restUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( RestAPI::NAMESPACE_V1 ) ) ); ?>,
		initialToken: <?php echo wp_json_encode( $raw_token ); ?>
	};
	</script>
	<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-qr.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
	<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-app.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
</body>
</html>
