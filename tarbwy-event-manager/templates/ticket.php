<?php
/**
 * Secure Printable Guest Ticket Page (/launch/ticket)
 *
 * Spec Section 4.4 & 9:
 * - Displays ONLY confirmed guest ticket: Logo/Header, Event Title, Cardholder Name, Reference Code, Date & Venue, QR Code, Entry Instructions.
 * - Strictly DOES NOT expose email or phone number.
 * - QR payload contains ONLY the opaque cryptographic random token (no PII, no guest_id).
 * - HTML Printable layout with dedicated @media print styles.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Security::send_no_leak_headers();

$raw_token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ( isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '' );
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="referrer" content="no-referrer">
	<meta name="robots" content="noindex, nofollow">
	<title>بطاقة حضور رسمية — تربوي</title>
	<link rel="stylesheet" href="<?php echo esc_url( TARBWY_EM_URL . 'assets/css/tarbwy-public.css?ver=' . TARBWY_EM_VERSION ); ?>">
</head>
<body class="tarbwy-public-body tarbwy-ticket-body" dir="rtl">
	<div class="tarbwy-container" style="max-width: 640px; padding-top: 32px; padding-bottom: 48px;">
		<div class="tarbwy-no-print" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
			<a href="<?php echo esc_url( home_url( '/launch' ) ); ?>" class="tarbwy-btn tarbwy-btn-outline">&rarr; العودة لصفحة الحفل</a>
			<button type="button" onclick="window.print()" class="tarbwy-btn tarbwy-btn-primary">طباعة البطاقة / حفظ PDF</button>
		</div>

		<div id="tarbwy-ticket-loading" class="tarbwy-card">
			<p>جاري التحقق من البطاقة الرقمية المشفرة...</p>
		</div>

		<div id="tarbwy-ticket-error" class="tarbwy-alert tarbwy-alert-error" hidden></div>

		<!-- Printable Official Institutional Ticket Pass -->
		<article id="tarbwy-ticket-card" class="tarbwy-official-ticket" hidden>
			<header class="tarbwy-ticket-header">
				<div class="tarbwy-ticket-org">تربوي — الاتحاد العربي للقيادة التربوية</div>
				<h1 id="tkt-event-title" class="tarbwy-ticket-title"></h1>
				<div class="tarbwy-ticket-badge-row">
					<span id="tkt-guest-type">بطاقة حضور مؤكدة</span>
					<span>·</span>
					<span id="tkt-ref" class="tarbwy-mono"></span>
				</div>
			</header>

			<div class="tarbwy-ticket-body-grid">
				<div class="tarbwy-ticket-details">
					<div class="tarbwy-tkt-field">
						<span class="tarbwy-tkt-label">اسم حامل البطاقة</span>
						<strong id="tkt-holder-name" class="tarbwy-tkt-value-lg"></strong>
					</div>
					<div class="tarbwy-tkt-field">
						<span class="tarbwy-tkt-label">المؤسسة / الجهة</span>
						<span id="tkt-organization" class="tarbwy-tkt-value"></span>
					</div>
					<div class="tarbwy-tkt-field">
						<span class="tarbwy-tkt-label">التاريخ والوقت</span>
						<span id="tkt-date" class="tarbwy-tkt-value"></span>
					</div>
					<div class="tarbwy-tkt-field">
						<span class="tarbwy-tkt-label">المكان</span>
						<span id="tkt-venue" class="tarbwy-tkt-value"></span>
					</div>
				</div>

				<div class="tarbwy-ticket-qr-box">
					<div id="tkt-qr-canvas" class="tarbwy-qr-container" aria-label="رمز الاستجابة السريعة QR"></div>
					<p class="tarbwy-qr-caption">رمز مشفر مخصص لشخص واحد فقط</p>
				</div>
			</div>

			<footer class="tarbwy-ticket-footer">
				<strong>تعليمات الدخول:</strong>
				<p id="tkt-instructions"></p>
			</footer>
		</article>
	</div>

	<script>
	window.TarbwyTicketConfig = {
		restUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( RestAPI::NAMESPACE_V1 ) ) ); ?>,
		token: <?php echo wp_json_encode( $raw_token ); ?>
	};
	</script>
	<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-qr.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
	<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-app.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
</body>
</html>
