<?php
/**
 * Public Landing Page & Registration Template (/launch)
 *
 * Arabic RTL, Mobile-First, Institutional Design, Zero External CDN Runtime Dependencies.
 * Sections:
 * 1. Hero (Title, Subtitle, Date/Time, City/Venue, Primary CTA 'طلب حضور')
 * 2. عن حفل الإشهار (About the Launch Ceremony)
 * 3. نبذة مؤسسية مختصرة عن تربوي (About Tarbwy)
 * 4. البرنامج (Schedule - Shows 'سيُعلن لاحقاً' if not yet configured)
 * 5. المتحدثون (Speakers - Shows 'سيُعلن لاحقاً' if not yet configured, no fake speaker photos)
 * 6. المكان والخريطة ومعلومات الوصول (Venue, Map & Access Info)
 * 7. الشركاء والرعاة (Partners & Sponsors)
 * 8. نموذج طلب الحضور داخل الصفحة (Public Registration Form with Companions, Honeypot, Rate Limit)
 * 9. وسيلة التواصل (Contact Info)
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = json_decode( (string) ( $event->settings_json ?? '{}' ), true );
if ( ! is_array( $settings ) ) {
	$settings = array();
}

$title        = ! empty( $event->title ) ? $event->title : 'حفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية»';
$subtitle     = ! empty( $settings['subtitle'] ) ? $settings['subtitle'] : 'حفل الإشهار الرسمي وإطلاق المبادرات القيادية التربوية في العالم العربي';
$date_display = ! empty( $settings['date_display'] ) ? $settings['date_display'] : 'سيُعلن لاحقاً';
$city_display = ! empty( $settings['city_display'] ) ? $settings['city_display'] : 'سيُعلن لاحقاً';
$venue_name   = ! empty( $event->venue_name ) ? $event->venue_name : 'سيُعلن لاحقاً';
$venue_addr   = ! empty( $event->venue_address ) ? $event->venue_address : 'سيُعلن لاحقاً';
$about_event  = ! empty( $settings['about_event'] ) ? $settings['about_event'] : '';
$about_tarbwy = ! empty( $settings['about_tarbwy'] ) ? $settings['about_tarbwy'] : '';
$max_comp     = isset( $event->max_companions_public ) ? (int) $event->max_companions_public : 2;
$reg_open     = isset( $event->registration_open ) ? (bool) $event->registration_open : true;

if ( empty( $is_shortcode_embed ) ) :
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $title ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( TARBWY_EM_URL . 'assets/css/tarbwy-public.css?ver=' . TARBWY_EM_VERSION ); ?>">
</head>
<body class="tarbwy-public-body" dir="rtl">
<?php endif; ?>

<div class="tarbwy-landing-wrapper" dir="rtl">
	<!-- Top Institutional Header -->
	<header class="tarbwy-header">
		<div class="tarbwy-container tarbwy-header-inner">
			<div class="tarbwy-brand">
				<span class="tarbwy-brand-title">تربوي — الاتحاد العربي للقيادة التربوية</span>
			</div>
			<nav class="tarbwy-nav">
				<a href="#about-event">عن الحفل</a>
				<a href="#about-tarbwy">عن تربوي</a>
				<a href="#schedule">البرنامج</a>
				<a href="#venue">المكان والوصول</a>
				<a href="#contact">التواصل</a>
			</nav>
			<div class="tarbwy-header-cta">
				<a href="#register" class="tarbwy-btn tarbwy-btn-primary">طلب حضور</a>
			</div>
		</div>
	</header>

	<!-- 1. Hero Section -->
	<section class="tarbwy-hero">
		<div class="tarbwy-container">
			<p class="tarbwy-kicker">الإشهار المؤسسي الرسمي · tarbwy.org</p>
			<h1 class="tarbwy-hero-title"><?php echo esc_html( $title ); ?></h1>
			<p class="tarbwy-hero-subtitle"><?php echo esc_html( $subtitle ); ?></p>

			<div class="tarbwy-hero-meta">
				<div class="tarbwy-meta-item">
					<span class="tarbwy-meta-label">التاريخ والوقت</span>
					<strong class="tarbwy-meta-value"><?php echo esc_html( $date_display ); ?></strong>
				</div>
				<div class="tarbwy-meta-item">
					<span class="tarbwy-meta-label">المدينة والمكان</span>
					<strong class="tarbwy-meta-value"><?php echo esc_html( $city_display . ' — ' . $venue_name ); ?></strong>
				</div>
				<div class="tarbwy-meta-item">
					<span class="tarbwy-meta-label">الطاقة الاستيعابية</span>
					<strong class="tarbwy-meta-value"><?php echo esc_html( (int) ( $event->capacity_total ?? 500 ) ); ?> مشارك (شاملاً المرافقين)</strong>
				</div>
			</div>

			<div class="tarbwy-hero-actions">
				<a href="#register" class="tarbwy-btn tarbwy-btn-primary tarbwy-btn-lg">طلب حضور الحفل</a>
				<a href="#about-event" class="tarbwy-btn tarbwy-btn-outline tarbwy-btn-lg">الاطلاع على التفاصيل</a>
			</div>
		</div>
	</section>

	<!-- 2 & 3. About the Launch Ceremony & Institutional Brief on Tarbwy -->
	<section id="about-event" class="tarbwy-section">
		<div class="tarbwy-container tarbwy-grid-2">
			<div class="tarbwy-card">
				<span class="tarbwy-section-num">01 · عن حفل الإشهار</span>
				<h2>محطة تأسيسية للقيادة التربوية العربية</h2>
				<p><?php echo nl2br( esc_html( $about_event ) ); ?></p>
			</div>
			<div id="about-tarbwy" class="tarbwy-card">
				<span class="tarbwy-section-num">02 · نبذة مؤسسية عن تربوي</span>
				<h2>الاتحاد العربي للقيادة التربوية</h2>
				<p><?php echo nl2br( esc_html( $about_tarbwy ) ); ?></p>
			</div>
		</div>
	</section>

	<!-- 4. Event Schedule -->
	<section id="schedule" class="tarbwy-section tarbwy-section-alt">
		<div class="tarbwy-container">
			<span class="tarbwy-section-num">03 · البرنامج الزمني</span>
			<h2>برنامج حفل الإشهار</h2>
			<?php if ( ! empty( $settings['schedule_items'] ) && is_array( $settings['schedule_items'] ) ) : ?>
				<div class="tarbwy-schedule-list">
					<?php foreach ( $settings['schedule_items'] as $item ) : ?>
						<div class="tarbwy-schedule-row">
							<span class="tarbwy-schedule-time"><?php echo esc_html( $item['time'] ?? '' ); ?></span>
							<div class="tarbwy-schedule-content">
								<h3><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
								<p><?php echo esc_html( $item['desc'] ?? '' ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="tarbwy-placeholder-notice">
					<p>سيُعلن لاحقاً — يتم حالياً اعتماد الجدول الزمني التفصيلي لفقرات الحفل من قِبل اللجنة التنظيمية.</p>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- 5. Speakers -->
	<section id="speakers" class="tarbwy-section">
		<div class="tarbwy-container">
			<span class="tarbwy-section-num">04 · المتحدثون والقيادات</span>
			<h2>المتحدثون الرئيسيون</h2>
			<?php if ( ! empty( $settings['speakers'] ) && is_array( $settings['speakers'] ) ) : ?>
				<div class="tarbwy-grid-3">
					<?php foreach ( $settings['speakers'] as $sp ) : ?>
						<div class="tarbwy-card">
							<h3><?php echo esc_html( $sp['name'] ?? '' ); ?></h3>
							<p class="tarbwy-muted"><?php echo esc_html( $sp['role'] ?? '' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="tarbwy-placeholder-notice">
					<p>سيُعلن لاحقاً — ستُنشر قائمة المتحدثين الرسميين فور اعتمادها من الأمانة العامة للاتحاد.</p>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- 6. Venue, Map & Access Info -->
	<section id="venue" class="tarbwy-section tarbwy-section-alt">
		<div class="tarbwy-container">
			<span class="tarbwy-section-num">05 · المكان ومعلومات الوصول</span>
			<h2>موقع انعقاد الحفل وإرشادات الدخول</h2>
			<div class="tarbwy-grid-2">
				<div class="tarbwy-card">
					<h3><?php echo esc_html( $venue_name ); ?></h3>
					<p><?php echo nl2br( esc_html( $venue_addr ) ); ?></p>
					<?php if ( ! empty( $event->map_url ) ) : ?>
						<p><a href="<?php echo esc_url( $event->map_url ); ?>" target="_blank" rel="noopener noreferrer" class="tarbwy-link">فتح الموقع على الخريطة &larr;</a></p>
					<?php endif; ?>
				</div>
				<div class="tarbwy-card">
					<h3>تعليمات الدخول التنظيمية</h3>
					<p><?php echo nl2br( esc_html( $settings['entry_instructions'] ?? 'الدخول مقتصر على حاملي بطاقات الحضور المؤكدة والمزودة برمز QR شخصي.' ) ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<!-- 7. Partners & Sponsors -->
	<section id="sponsors" class="tarbwy-section">
		<div class="tarbwy-container">
			<span class="tarbwy-section-num">06 · الشركاء والرعاة</span>
			<h2>الشركاء المؤسسيون والرعاة</h2>
			<div class="tarbwy-placeholder-notice">
				<p>سيُعلن لاحقاً — قسم مخصص للشركاء الاستراتيجيين والرعاة الرسميين لحفل الإشهار.</p>
			</div>
		</div>
	</section>

	<!-- 8. Public Registration Form -->
	<section id="register" class="tarbwy-section tarbwy-section-form">
		<div class="tarbwy-container">
			<div class="tarbwy-form-card">
				<span class="tarbwy-section-num">07 · استمارة التسجيل العام</span>
				<h2>نموذج طلب حضور الحفل</h2>
				<p class="tarbwy-form-intro">
					يخضع طلب التسجيل العام للمراجعة والاعتماد من قِبل اللجنة المنظمة وفق الطاقة الاستيعابية للقاعة.
					في حال اصطحاب مرافقين، يُنشأ لكل مرافق سجل مستقل وتصدر له بطاقة QR مستقلة عند الاعتماد.
				</p>

				<?php if ( ! $reg_open ) : ?>
					<div class="tarbwy-alert tarbwy-alert-warning">
						باب التسجيل مغلق حالياً لاكتمال العدد أو انتهاء فترة التسجيل.
					</div>
				<?php else : ?>
					<form id="tarbwy-public-reg-form" class="tarbwy-form" novalidate>
						<!-- Honeypot anti-spam field (hidden from real users) -->
						<div class="tarbwy-hp-field" aria-hidden="true">
							<label for="website_url_hp">اترك هذا الحقل فارغاً</label>
							<input type="text" id="website_url_hp" name="website_url_hp" tabindex="-1" autocomplete="off">
						</div>

						<div class="tarbwy-form-grid">
							<div class="tarbwy-field">
								<label for="reg_full_name">الاسم الكامل <span class="req">*</span></label>
								<input type="text" id="reg_full_name" name="full_name" required placeholder="الاسم الثلاثي كما سيظهر في البطاقة">
							</div>
							<div class="tarbwy-field">
								<label for="reg_email">البريد الإلكتروني <span class="req">*</span></label>
								<input type="email" id="reg_email" name="email" required placeholder="name@organization.org">
							</div>
							<div class="tarbwy-field">
								<label for="reg_phone">رقم الهاتف / الجوال <span class="req">*</span></label>
								<input type="tel" id="reg_phone" name="phone" required placeholder="+966 5X XXX XXXX">
							</div>
							<div class="tarbwy-field">
								<label for="reg_organization">المؤسسة / الجهة <span class="req">*</span></label>
								<input type="text" id="reg_organization" name="organization" required placeholder="اسم الجامعة / الوزارة / المؤسسة التربوية">
							</div>
							<div class="tarbwy-field">
								<label for="reg_job_title">المسمى الوظيفي / الصفة <span class="req">*</span></label>
								<input type="text" id="reg_job_title" name="job_title" required placeholder="المسمى الوظيفي أو الأكاديمي">
							</div>
							<div class="tarbwy-field">
								<label for="reg_dietary">ملاحظات غذائية أو متطلبات وصول (اختياري)</label>
								<input type="text" id="reg_dietary" name="dietary_requirements" placeholder="اختياري — يُحفظ بسرية لفريق التنظيم فقط">
							</div>
						</div>

						<!-- Companions Section -->
						<div class="tarbwy-companions-box">
							<div class="tarbwy-companions-header">
								<div>
									<h3>المرافقون (الحد الأقصى: <?php echo esc_html( $max_comp ); ?>)</h3>
									<p class="tarbwy-muted">لكل مرافق سجل مستقل وبطاقة QR مستقلة. إذا لم يتوفر بريد للمرافق، تُرسل بطاقته إلى بريدك الأساسي مع توضيح اسم حامل البطاقة.</p>
								</div>
								<button type="button" id="tarbwy-add-companion-btn" class="tarbwy-btn tarbwy-btn-outline" data-max="<?php echo esc_attr( $max_comp ); ?>">
									+ إضافة مرافق
								</button>
							</div>
							<div id="tarbwy-companions-list"></div>
						</div>

						<div id="tarbwy-reg-feedback" class="tarbwy-feedback" hidden></div>

						<div class="tarbwy-form-submit">
							<button type="submit" id="tarbwy-reg-submit-btn" class="tarbwy-btn tarbwy-btn-primary tarbwy-btn-lg">
								إرسال طلب الحضور للمراجعة
							</button>
						</div>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- 9. Contact Information & Footer -->
	<footer id="contact" class="tarbwy-footer">
		<div class="tarbwy-container tarbwy-footer-inner">
			<div>
				<h3>تربوي — الاتحاد العربي للقيادة التربوية</h3>
				<p>الموقع الرسمي: <a href="https://tarbwy.org">https://tarbwy.org</a></p>
			</div>
			<div>
				<p>البريد المخصص للفعالية: <strong><?php echo esc_html( $settings['contact_email'] ?? 'events@tarbwy.org' ); ?></strong></p>
				<p>هاتف التواصل: <strong><?php echo esc_html( $settings['contact_phone'] ?? 'سيُعلن لاحقاً' ); ?></strong></p>
			</div>
		</div>
	</footer>
</div>

<?php if ( empty( $is_shortcode_embed ) ) : ?>
<script>
window.TarbwyPublicConfig = {
	restUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( RestAPI::NAMESPACE_V1 ) ) ); ?>,
	eventId: <?php echo (int) ( $event->id ?? 1 ); ?>,
	maxCompanions: <?php echo (int) $max_comp; ?>
};
</script>
<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-app.js?ver=' . TARBWY_EM_VERSION ); ?>"></script>
</body>
</html>
<?php endif; ?>
