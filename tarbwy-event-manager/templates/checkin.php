<?php
/**
 * Staff Check-in Interface (/launch/checkin)
 *
 * Spec Section 4.5 & 9:
 * - Protected by WordPress authentication and 'checkin_tarbwy_guests' capability.
 * - Mobile-first, RTL, large touch controls.
 * - Camera QR scanning + Search by name, email, or ticket reference + Manual Check-in.
 * - Explicit server-verified statuses:
 *   1. صالح (Valid)
 *   2. مستخدم سابقاً (Already Checked-in)
 *   3. ملغى (Revoked)
 *   4. غير معروف (Unknown)
 *   5. غير مؤكد (Not Confirmed)
 * - Displays minimal required guest data.
 * - Live statistics polling every 15 seconds.
 * - Never displays success before server confirmation; explicitly alerts on offline/network failure.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Security::send_no_leak_headers();
$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="referrer" content="no-referrer">
	<meta name="robots" content="noindex, nofollow">
	<title>بوابة تسجيل الدخول (Check-in) — تربوي</title>
	<link rel="stylesheet" href="<?php echo esc_url( TARBWY_EM_URL . 'assets/css/tarbwy-public.css?ver=' . TARBWY_EM_VERSION ); ?>">
</head>
<body class="tarbwy-public-body tarbwy-checkin-body" dir="rtl">
	<header class="tarbwy-header tarbwy-checkin-header">
		<div class="tarbwy-container tarbwy-header-inner">
			<div class="tarbwy-brand">
				<span class="tarbwy-brand-title">بوابة استقبال الضيوف (Check-in)</span>
			</div>
			<div class="tarbwy-staff-meta">
				<span>الموظف: <?php echo esc_html( $current_user->display_name ); ?></span>
				<span>·</span>
				<span id="checkin-net-status" class="tarbwy-net-online">متصل بالخادم</span>
			</div>
		</div>
	</header>

	<main class="tarbwy-container" style="max-width: 820px; padding-top: 24px; padding-bottom: 48px;">
		<!-- Live Statistics Ribbon -->
		<section class="tarbwy-stats-ribbon" aria-label="إحصائيات الحضور المباشرة">
			<div class="tarbwy-stat-cell">
				<span class="tarbwy-stat-label">إجمالي المؤكدين</span>
				<strong id="stat-confirmed" class="tarbwy-stat-num">0</strong>
			</div>
			<div class="tarbwy-stat-cell">
				<span class="tarbwy-stat-label">سجلوا الدخول (حاضر)</span>
				<strong id="stat-checked-in" class="tarbwy-stat-num tarbwy-text-success">0</strong>
			</div>
			<div class="tarbwy-stat-cell">
				<span class="tarbwy-stat-label">المتبقي</span>
				<strong id="stat-remaining" class="tarbwy-stat-num">0</strong>
			</div>
			<div class="tarbwy-stat-cell">
				<span class="tarbwy-stat-label">السعة الكلية</span>
				<strong id="stat-capacity" class="tarbwy-stat-num">500</strong>
			</div>
		</section>

		<!-- Server Verification Banner (Shows Valid / Already Checked-in / Revoked / Unknown / Not Confirmed / Offline) -->
		<section id="checkin-result-banner" class="tarbwy-checkin-banner" hidden></section>

		<!-- Dual Mode Controls: Camera QR Scanner + Token/Manual Lookup -->
		<div class="tarbwy-grid-2" style="margin-top: 20px;">
			<section class="tarbwy-card">
				<h2>1. مسح رمز QR بالكاميرا أو القارئ</h2>
				<p class="tarbwy-muted">وجّه كاميرا الهاتف أو جهاز التابلت نحو رمز QR على بطاقة الضيف، أو امسح الرمز مباشرة.</p>

				<div id="checkin-camera-box" class="tarbwy-camera-box" hidden>
					<video id="checkin-video" playsinline muted style="width:100%; border-radius:6px; background:#000;"></video>
				</div>

				<div style="display:flex; gap:10px; margin-top:12px;">
					<button type="button" id="btn-toggle-camera" class="tarbwy-btn tarbwy-btn-primary" style="flex:1;">
						تشغيل كاميرا المسح
					</button>
				</div>

				<form id="form-direct-qr-scan" style="margin-top: 16px;">
					<label for="input-qr-token" style="font-size:13px; font-weight:600;">إدخال أو قراءة رمز التذكرة المشفر مباشرة:</label>
					<div style="display:flex; gap:8px; margin-top:6px;">
						<input type="text" id="input-qr-token" dir="ltr" placeholder="امسح أو الصق رمز QR هنا..." required style="flex:1;">
						<button type="submit" class="tarbwy-btn tarbwy-btn-primary">تحقق وتسجيل</button>
					</div>
				</form>
			</section>

			<section class="tarbwy-card">
				<h2>2. بحث يدوي (الاسم / البريد / رقم التذكرة)</h2>
				<p class="tarbwy-muted">ابحث عن الضيف أو المرافق لتنفيذ تسجيل الدخول اليدوي (Manual Check-in).</p>
				<form id="form-checkin-lookup" style="margin-top: 12px;">
					<div style="display:flex; gap:8px;">
						<input type="search" id="input-checkin-search" placeholder="ابحث بالاسم أو رقم التذكرة TKT-..." style="flex:1;">
						<button type="submit" class="tarbwy-btn tarbwy-btn-outline">بحث</button>
					</div>
				</form>
				<div id="checkin-search-results" class="tarbwy-lookup-list" style="margin-top: 16px;"></div>
			</section>
		</div>
	</main>

	<script>
	window.TarbwyCheckinConfig = {
		restUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( RestAPI::NAMESPACE_V1 ) ) ); ?>,
		nonce: <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
		deviceLabel: <?php echo wp_json_encode( 'Terminal-' . $current_user->ID ); ?>
	};
	</script>
	<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-qr.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
	<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-app.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
</body>
</html>
