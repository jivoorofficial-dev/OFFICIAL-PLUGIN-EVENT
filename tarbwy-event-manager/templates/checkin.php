<?php
/**
 * Protected Staff Check-in Application (/launch/checkin)
 *
 * Spec Section 4.5, 9, 10 & Final Launch Fixes #1 & #2:
 * - Protected by login and `checkin_tarbwy_guests` capability.
 * - Mobile-first, RTL, clean modern Android-app-style UI.
 * - Reliable Camera QR Scanner for Android & iOS browsers (live video + camera switch + torch + photo scan fallback).
 * - Fast, accurate Manual Attendee Search by Name, Email, Phone, or Ticket Number (live typing + submit + quick filters).
 * - Clear server-verified results: Valid, Already Checked-in, Revoked, Not Confirmed, Unknown.
 * - Live attendance statistics and recent scan history log.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Security::send_no_leak_headers();

$event = RestAPI::get_active_event();
if ( ! is_object( $event ) ) {
	$event = (object) array(
		'id'                     => 1,
		'event_key'              => 'tarbwy-launch-2026',
		'title'                  => 'حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية»',
		'slug'                   => 'launch',
		'status'                 => 'published',
		'event_start_at'         => null,
		'event_end_at'           => null,
		'timezone'               => 'Asia/Riyadh',
		'venue_name'             => 'سيُعلن لاحقاً',
		'venue_address'          => 'سيتم تحديد اسم المكان والعنوان التفصيلي فور الاعتماد النهائي من قِبل إدارة الحدث',
		'map_url'                => '',
		'capacity_total'         => 500,
		'max_companions_public'  => 2,
		'max_companions_vip'     => 3,
		'approval_auto_confirms' => 1,
		'registration_open'      => 1,
		'rsvp_deadline'          => null,
		'settings_json'          => '{}',
	);
}
$current_user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$css_ver = TARBWY_EM_VERSION . '.' . (int) @filemtime( TARBWY_EM_PATH . 'assets/css/tarbwy-public.css' );
$qr_ver  = TARBWY_EM_VERSION . '.' . (int) @filemtime( TARBWY_EM_PATH . 'assets/js/tarbwy-qr.js' );
$app_ver = TARBWY_EM_VERSION . '.' . (int) @filemtime( TARBWY_EM_PATH . 'assets/js/tarbwy-app.js' );
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<meta name="referrer" content="no-referrer">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="#0F3D22">
	<title>تطبيق الاستقبال وتسجيل الدخول (Check-in) — تربوي</title>
	<link rel="stylesheet" href="<?php echo esc_url( TARBWY_EM_URL . 'assets/css/tarbwy-public.css?ver=' . $css_ver ); ?>">
</head>
<body class="tarbwy-public-body tarbwy-checkin-app-body" dir="rtl">

	<!-- Android-Style Top App Bar -->
	<header class="tarbwy-checkin-appbar">
		<div class="tarbwy-checkin-appbar-inner">
			<div class="tarbwy-checkin-brand">
				<div class="tarbwy-checkin-app-icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/>
						<rect x="7" y="7" width="3" height="3"/><rect x="14" y="7" width="3" height="3"/><rect x="7" y="14" width="3" height="3"/><path d="M14 14h3v3"/>
					</svg>
				</div>
				<div>
					<h1 class="tarbwy-checkin-app-title">بوابة استقبال وتسجيل دخول الضيوف</h1>
					<p class="tarbwy-checkin-app-subtitle"><?php echo esc_html( $event ? $event->title : 'حفل إشهار تربوي' ); ?></p>
				</div>
			</div>

			<div class="tarbwy-checkin-appbar-actions">
				<span id="checkin-network-status" class="tarbwy-net-badge tarbwy-net-online">
					<span class="tarbwy-net-dot"></span>
					<span id="checkin-network-label">متصل بالخادم</span>
				</span>
				<button type="button" id="btn-checkin-sound-toggle" class="tarbwy-appbar-icon-btn" title="تفعيل/كتم التنبيه الصوتي">
					<span id="checkin-sound-label">🔊 الصوت مفعل</span>
				</button>
				<span class="tarbwy-staff-chip">
					👤 <?php echo esc_html( ! empty( $current_user->display_name ) ? $current_user->display_name : ( ! empty( $current_user->user_login ) ? $current_user->user_login : 'موظف الاستقبال' ) ); ?>
				</span>
				<a href="<?php echo esc_url( function_exists( 'wp_logout_url' ) ? wp_logout_url( home_url( '/launch/checkin' ) ) : home_url( '/wp-login.php?action=logout' ) ); ?>" class="tarbwy-appbar-logout">خروج</a>
			</div>
		</div>
	</header>

	<main class="tarbwy-checkin-main">
		<div class="tarbwy-checkin-shell">

			<!-- Server Verification Result Banner (Valid / Duplicate / Revoked / Not Confirmed / Unknown) -->
			<section id="checkin-result-banner" class="tarbwy-checkin-result-sheet" aria-live="assertive" hidden></section>

			<!-- 1. FIRST & MOST PROMINENT SECTION: Smart Camera QR Scanner (Android & iOS) -->
			<section id="panel-checkin-scanner" class="tarbwy-checkin-panel tarbwy-checkin-scanner-top is-active-panel">
				<div class="tarbwy-panel-head">
					<div>
						<span class="tarbwy-panel-badge">ماسح الكاميرا الذكي · Android &amp; iOS</span>
						<h2 class="tarbwy-panel-title">مسح رمز الاستجابة السريعة (QR Scanner)</h2>
					</div>
					<span id="camera-state-pill" class="tarbwy-camera-pill">جاهز للتشغيل</span>
				</div>

				<!-- Camera Viewfinder Container -->
				<div id="checkin-camera-box" class="tarbwy-camera-viewport" hidden>
					<video id="checkin-video" playsinline webkit-playsinline autoplay muted></video>
					<div class="tarbwy-scanner-reticle" aria-hidden="true">
						<span class="tarbwy-corner tl"></span>
						<span class="tarbwy-corner tr"></span>
						<span class="tarbwy-corner bl"></span>
						<span class="tarbwy-corner br"></span>
						<div class="tarbwy-laser-line"></div>
					</div>
					<div class="tarbwy-camera-overlay-bar">
						<span id="camera-status-text" class="tarbwy-camera-hint">وجّه الكاميرا نحو رمز QR على بطاقة الضيف...</span>
						<div class="tarbwy-camera-floating-btns">
							<button type="button" id="btn-switch-camera" class="tarbwy-cam-ctrl-btn" title="تبديل الكاميرا (أمامية / خلفية)">
								🔄 تبديل الكاميرا
							</button>
							<button type="button" id="btn-toggle-torch" class="tarbwy-cam-ctrl-btn" hidden title="تشغيل/إيقاف الفلاش">
								🔦 الفلاش
							</button>
						</div>
					</div>
				</div>

				<!-- Camera Action Buttons (Live Stream + Snapshot Fallback for 100% iOS/Android Support) -->
				<div class="tarbwy-scanner-actions">
					<button type="button" id="btn-toggle-camera" class="tarbwy-btn tarbwy-btn-primary tarbwy-btn-scanner-main">
						📷 تشغيل كاميرا المسح المباشر
					</button>
					<button type="button" id="btn-photo-qr-scan" class="tarbwy-btn tarbwy-btn-outline tarbwy-btn-scanner-alt" title="مسح رمز QR عبر التقاط صورة بالكاميرا أو اختيار صورة">
						🖼️ التقاط صورة لرمز QR
					</button>
					<input type="file" id="input-photo-qr" accept="image/*" capture="environment" hidden>
				</div>

				<div id="camera-error-box" class="tarbwy-alert tarbwy-alert-warning" style="margin-top:12px;" hidden></div>

				<!-- Direct QR Token / USB Barcode Scanner / Ticket Reference Input -->
				<div class="tarbwy-direct-scan-box">
					<form id="form-qr-scan" class="tarbwy-form">
						<label for="input-qr-token" class="tarbwy-direct-scan-label">
							إدخال رمز التذكرة أو القارئ الليزري (USB Scanner / رقم التذكرة <code dir="ltr">TKT-...</code>):
						</label>
						<div class="tarbwy-direct-scan-row">
							<input
								type="text"
								id="input-qr-token"
								name="qr_token"
								dir="ltr"
								autocomplete="off"
								placeholder="TKT-2026-XXXXXX أو امسح بالقارئ..."
								required
							>
							<button type="submit" id="btn-submit-qr-token" class="tarbwy-btn tarbwy-btn-primary">
								تحقق وتسجيل
							</button>
						</div>
					</form>
				</div>
			</section>

			<!-- 2. SECOND SECTION: Fast Manual Attendee Search (Name, Email, Ticket Number, Phone) -->
			<section id="panel-checkin-search" class="tarbwy-checkin-panel is-active-panel" style="margin-top: 18px;">
				<div class="tarbwy-panel-head">
					<div>
						<span class="tarbwy-panel-badge">البحث الفوري والتحضير اليدوي</span>
						<h2 class="tarbwy-panel-title">بحث عن مشارك (الاسم، البريد، رقم التذكرة)</h2>
					</div>
					<span id="search-results-count" class="tarbwy-camera-pill">بحث مباشر</span>
				</div>

				<form id="form-checkin-lookup" class="tarbwy-form" role="search">
					<div class="tarbwy-search-bar-wrap">
						<input
							type="search"
							id="input-checkin-search"
							name="q"
							autocomplete="off"
							placeholder="ابحث بالاسم، البريد الإلكتروني، أو رقم التذكرة (TKT-...)..."
							class="tarbwy-checkin-search-input"
						>
						<button type="button" id="btn-clear-checkin-search" class="tarbwy-search-clear-btn" hidden title="مسح البحث">✕</button>
						<button type="submit" id="btn-submit-checkin-search" class="tarbwy-btn tarbwy-btn-primary">
							بحث
						</button>
					</div>
				</form>

				<!-- Quick Filter Chips so staff can browse Confirmed / Pending Check-in / Checked-in with 1 tap -->
				<div id="checkin-quick-filters" class="tarbwy-quick-filter-chips">
					<button type="button" class="tarbwy-filter-chip is-active" data-lookup-filter="pending_checkin">
						⏳ بانتظار الدخول
					</button>
					<button type="button" class="tarbwy-filter-chip" data-lookup-filter="confirmed">
						✓ جميع المؤكدين
					</button>
					<button type="button" class="tarbwy-filter-chip" data-lookup-filter="checked_in">
						🟢 حضروا فعلياً
					</button>
					<button type="button" class="tarbwy-filter-chip" data-lookup-filter="all">
						👥 كل المسجلين
					</button>
				</div>

				<!-- Search Results List -->
				<div id="checkin-search-results" class="tarbwy-checkin-results-list" aria-live="polite">
					<div class="tarbwy-checkin-empty-state">
						<p>اكتب اسم الضيف أو بريده الإلكتروني أو رقم التذكرة أعلاه، أو اختر أحد الفلاتر السريعة لعرض القائمة.</p>
					</div>
				</div>
			</section>

			<!-- 3. THIRD SECTION: Live Attendance Progress & KPI Strip -->
			<section class="tarbwy-checkin-kpi-card" aria-label="إحصائيات الحضور المباشرة" style="margin-top: 18px;">
				<div class="tarbwy-checkin-progress-header">
					<div>
						<span class="tarbwy-kpi-eyebrow">مؤشر الحضور الفعلي المباشر (Live Attendance)</span>
						<strong id="stat-progress-text" class="tarbwy-kpi-progress-title">جاري التحديث...</strong>
					</div>
					<div style="display:flex; align-items:center; gap:10px;">
						<span id="stat-progress-pct" class="tarbwy-kpi-pct">0%</span>
						<button type="button" id="btn-refresh-stats" class="tarbwy-icon-pill-btn" title="تحديث الإحصائيات الآن">↻ تحديث</button>
					</div>
				</div>
				<div class="tarbwy-checkin-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100">
					<div id="stat-progress-bar" class="tarbwy-checkin-progress-fill" style="width: 0%;"></div>
				</div>

				<div class="tarbwy-checkin-kpi-grid">
					<div class="tarbwy-kpi-box">
						<span class="tarbwy-kpi-label">المؤكدون (Confirmed)</span>
						<strong id="stat-confirmed" class="tarbwy-kpi-value">-</strong>
					</div>
					<div class="tarbwy-kpi-box tarbwy-kpi-box-success">
						<span class="tarbwy-kpi-label">حضروا فعلياً (Checked-in)</span>
						<strong id="stat-checked-in" class="tarbwy-kpi-value">-</strong>
					</div>
					<div class="tarbwy-kpi-box tarbwy-kpi-box-accent">
						<span class="tarbwy-kpi-label">المتبقي (Remaining)</span>
						<strong id="stat-remaining" class="tarbwy-kpi-value">-</strong>
					</div>
					<div class="tarbwy-kpi-box">
						<span class="tarbwy-kpi-label">السعة المعتمدة</span>
						<strong id="stat-capacity" class="tarbwy-kpi-value"><?php echo esc_html( $event ? $event->capacity_total : 500 ); ?></strong>
					</div>
				</div>
			</section>

			<!-- 4. FOURTH SECTION: Recent Scans & Check-in History Log -->
			<section id="panel-checkin-history" class="tarbwy-checkin-panel tarbwy-history-panel is-active-panel" style="margin-top: 18px;">
				<div class="tarbwy-panel-head">
					<div>
						<span class="tarbwy-panel-badge">سجل عمليات البوابة الحالية (<span id="checkin-history-count">0</span>)</span>
						<h2 class="tarbwy-panel-title">آخر عمليات المسح والتحضير في هذه الجلسة</h2>
					</div>
					<button type="button" id="btn-clear-scan-history" class="tarbwy-icon-pill-btn">مسح السجل المحلي</button>
				</div>
				<div id="checkin-recent-history-list" class="tarbwy-history-list">
					<p class="tarbwy-muted" style="margin:0; font-size:0.9rem;">لم يتم إجراء عمليات مسح في هذه الجلسة بعد.</p>
				</div>
			</section>

		</div>
	</main>

	<script data-cfasync="false">
	window.TarbwyCheckinConfig = {
		restUrl: <?php echo wp_json_encode( function_exists( 'rest_url' ) && function_exists( 'esc_url_raw' ) ? esc_url_raw( rest_url( RestAPI::NAMESPACE_V1 ) ) : home_url( '/wp-json/' . RestAPI::NAMESPACE_V1 ) ); ?>,
		nonce: <?php echo wp_json_encode( function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wp_rest' ) : '' ); ?>,
		eventId: <?php echo wp_json_encode( $event ? (int) $event->id : 1 ); ?>,
		staffName: <?php echo wp_json_encode( ! empty( $current_user->display_name ) ? $current_user->display_name : ( ! empty( $current_user->user_login ) ? $current_user->user_login : 'موظف الاستقبال' ) ); ?>
	};
	</script>
	<script data-cfasync="false" src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-qr.js?ver=' . $qr_ver ); ?>"></script>
	<script data-cfasync="false">
	if ( window.TARBWY_QR_ENGINE_VERSION !== '1.5.0' ) {
		<?php
		// Inline fallback if a CDN edge or mobile browser served a stale cached tarbwy-qr.js
		$qr_inline_js = @file_get_contents( TARBWY_EM_PATH . 'assets/js/tarbwy-qr.js' );
		if ( is_string( $qr_inline_js ) && '' !== $qr_inline_js ) {
			echo $qr_inline_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted local plugin JS asset
		}
		?>
	}
	</script>
	<script data-cfasync="false" src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-app.js?ver=' . $app_ver ); ?>"></script>
</body>
</html>
