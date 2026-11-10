<?php
/**
 * Tarbwy Event Manager v2.0 — Official Production Landing Page (/launch)
 *
 * Deployed as the sole active landing page experience at /launch.
 * Uses the existing Tarbwy Event Manager database, settings, and public registration workflow.
 *
 * Mandatory Structural Architecture:
 * 1. Full-Screen 100vh Centered Cinematic Hero (Background photograph, dark layered vignette,
 *    floating light particles, centered Arabic hierarchy, live 4-unit countdown timer, and 2 CTAs:
 *    «اكتشف الحفل» + «طلب الحضور»).
 * 2. Asymmetric Editorial Storytelling for About the Launch & Tarbwy Vision/Identity (#tv2-about, #tv2-vision).
 * 3. Scroll-Driven Stacked Editorial Speaker Cards (#tv2-speakers): Physical invitation / folio sheets
 *    that overlap, scale, and rotate with scroll depth on desktop and adapt to a vertical touch deck on mobile.
 * 4. Numbered Architectural Event Tracks & Main Themes (#tv2-tracks).
 * 5. Single-Day Interactive Vertical Event Timeline (#tv2-program): ONE DAY ONLY, scroll-illuminated
 *    vertical spine, prominent time column, distinct Opening & Closing session cards, single-column on mobile.
 * 6. Visual Architectural Venue Showcase (#tv2-venue) with large venue photograph & map integration.
 * 7. Ceremonial Registration Pavilion (#register) preserving #tarbwy-public-reg-form & QR workflow.
 * 8. Partners & Sponsors (#tv2-sponsors) strictly hidden until confirmed and enabled.
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $event ) || ! is_object( $event ) ) {
	$event = RestAPI::get_active_event();
}
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

$settings = json_decode( (string) ( $event->settings_json ?? '{}' ), true );
if ( ! is_array( $settings ) ) {
	$settings = array();
}

$raw_title          = ! empty( $event->title ) ? $event->title : 'حفل إشهار تربوي';
$hero_main_title    = 'حفل إشهار تربوي';
$hero_sub_identity  = ( false !== strpos( $raw_title, 'الاتحاد' ) ) ? $raw_title : 'تربوي — الاتحاد العربي للقيادة التربوية';
$subtitle           = ! empty( $settings['subtitle'] ) ? $settings['subtitle'] : 'ملتقى تأسيسي يجمع القيادات التربوية وصنّاع القرار والمرجعيات الفكرية لإطلاق مظلة مؤسسية عربية مشتركة تصنع مستقبل القيادة التعليمية.';
$date_display       = ! empty( $settings['date_display'] ) ? $settings['date_display'] : 'يُحدد الموعد النهائي قريباً من اللجنة المنظمة';
$countdown_target   = ! empty( $settings['countdown_target'] ) ? $settings['countdown_target'] : ( ! empty( $event->event_start_at ) && '0000-00-00 00:00:00' !== $event->event_start_at ? $event->event_start_at : '' );
$city_display       = ! empty( $settings['city_display'] ) ? $settings['city_display'] : 'يُعلن قريباً فور الاعتماد النهائي';
$venue_name         = ! empty( $event->venue_name ) ? $event->venue_name : 'القاعة الرئيسة — يُعلن عنها قريباً';
$venue_addr         = ! empty( $event->venue_address ) ? $event->venue_address : 'سيتم نشر العنوان التفصيلي وإحداثيات الموقع فور تثبيت القاعة المعتمدة من اللجنة المنظمة.';
$map_url            = ! empty( $event->map_url ) ? $event->map_url : '';
$access_info        = ! empty( $settings['access_info'] ) ? $settings['access_info'] : 'سيتم تزويد الضيوف المؤكدين بإرشادات الوصول المباشر وبوابات الاستقبال ومواقف السيارات المخصصة ضمن رسالة بطاقة الدخول.';
$about_event        = ! empty( $settings['about_event'] ) ? $settings['about_event'] : 'يمثل حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية» محطة تأسيسية جامعة لنخبة القيادات التربوية وصنّاع القرار والشخصيات المرجعية والخبراء؛ بهدف الإعلان الرسمي عن انطلاق الاتحاد، واستعراض رؤيته التأسيسية، وتفعيل مسارات العمل المشترك للارتقاء بالقيادة التربوية العربية.';
$about_tarbwy       = ! empty( $settings['about_tarbwy'] ) ? $settings['about_tarbwy'] : '«تربوي — الاتحاد العربي للقيادة التربوية» إطار مؤسسي ومهني عربي يُعنى بتمكين القيادات التربوية، وتطوير الفكر والممارسة القيادية في المؤسسات التعليمية، وبناء منظومة تشاركية رصينة تجمع القادة والخبراء التربويين في الوطن العربي.';
$why_tarbwy         = ! empty( $settings['why_tarbwy'] ) ? $settings['why_tarbwy'] : 'أُطلق «تربوي» استجابةً لحاجة المشهد التربوي العربي إلى مظلة مؤسسية جامعة توحّد الجهود القيادية المبعثرة، وتنقل المبادرات التربوية من الأطر الفردية المحدودة إلى عمل مؤسسي مستدام يواكب التحولات التعليمية المعاصرة.';
$tarbwy_space       = ! empty( $settings['tarbwy_space'] ) ? $settings['tarbwy_space'] : 'يسعى «تربوي» إلى بناء مساحة عربية مهنية ومعرفية مشتركة تربط القيادات التربوية وصنّاع السياسات والباحثين، وتتيح تبادل الخبرات النوعية، وتطوير المعايير القيادية، وإطلاق البرامج والمبادرات العابرة للقطاعات التعليمية.';
$entry_instructions = ! empty( $settings['entry_instructions'] ) ? $settings['entry_instructions'] : 'الدخول يوم الحفل يتم حصرياً عبر إبراز بطاقة الدخول الشخصية المزودة برمز QR الفريد (عبر الهاتف أو مطبوعة) عند بوابة الاستقبال. يُسجل طلب الحضور العام أولاً بحالة (قيد المراجعة / Pending)، وتصدر بطاقة الدخول بعد اعتماد الطلب فقط.';
$contact_email      = ! empty( $settings['contact_email'] ) ? $settings['contact_email'] : 'events@tarbwy.org';
$contact_phone      = ! empty( $settings['contact_phone'] ) ? $settings['contact_phone'] : 'يُعلن لاحقاً';
$max_comp           = isset( $event->max_companions_public ) ? (int) $event->max_companions_public : 2;
$capacity_total     = isset( $event->capacity_total ) ? (int) $event->capacity_total : 500;
$reg_open           = isset( $event->registration_open ) ? (bool) $event->registration_open : true;

$default_hero_img  = TARBWY_EM_URL . 'assets/img/launch-v2-hero.jpg';
$default_venue_img = TARBWY_EM_URL . 'assets/img/launch-v2-venue.jpg';
$hero_bg_url       = ! empty( $settings['hero_bg_url'] ) ? $settings['hero_bg_url'] : $default_hero_img;
$venue_image_url   = ! empty( $settings['venue_image_url'] ) ? $settings['venue_image_url'] : $default_venue_img;

// Editable Event Tracks & Main Themes
$tracks_items = ( ! empty( $settings['tracks_items'] ) && is_array( $settings['tracks_items'] ) ) ? $settings['tracks_items'] : array(
	array(
		'code'  => 'track-1',
		'badge' => 'المسار الأول · قابل للتحرير',
		'title' => 'المحور الرئيس الأول (يُحدث فور اعتماد المحاور الرسمية)',
		'desc'  => 'مساحة مخصصة لاستعراض المحور الفكري أو القيادي الأول لحفل الإشهار. يمكن تحرير عنوان هذا المسار ووصفه بالكامل من لوحة تحكم ووردبريس (إعدادات الحدث).',
	),
	array(
		'code'  => 'track-2',
		'badge' => 'المسار الثاني · قابل للتحرير',
		'title' => 'المحور الرئيس الثاني (يُحدث فور اعتماد المحاور الرسمية)',
		'desc'  => 'مساحة مخصصة لاستعراض المحور الثاني ومجالات النقاش المرتبطة به بين القيادات التربوية وصنّاع القرار المشاركين في حفل الإشهار.',
	),
	array(
		'code'  => 'track-3',
		'badge' => 'المسار الثالث · قابل للتحرير',
		'title' => 'المحور الرئيس الثالث (يُحدث فور اعتماد المحاور الرسمية)',
		'desc'  => 'مساحة مخصصة لتسليط الضوء على المسار التطبيقي أو المؤسسي الثالث ومبادرات العمل التربوي المشترك المزمع إطلاقها.',
	),
	array(
		'code'  => 'track-4',
		'badge' => 'المسار الرابع · قابل للتحرير',
		'title' => 'المحور الرئيس الرابع (يُحدث فور اعتماد المحاور الرسمية)',
		'desc'  => 'مساحة مخصصة لاستعراض آفاق الشراكات التربوية وتكامل الخبرات القيادية العربية ضمن إطار الاتحاد.',
	),
);

// Editable Speakers & Distinguished Guests (5 Stacked Editorial Cards)
$speakers = ( ! empty( $settings['speakers'] ) && is_array( $settings['speakers'] ) ) ? $settings['speakers'] : array(
	array(
		'name'      => 'ضيف متحدث 01 (يُعلن لاحقاً)',
		'role'      => 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
		'bio'       => 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
		'photo_url' => '',
		'link_url'  => '',
	),
	array(
		'name'      => 'ضيف متحدث 02 (يُعلن لاحقاً)',
		'role'      => 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
		'bio'       => 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
		'photo_url' => '',
		'link_url'  => '',
	),
	array(
		'name'      => 'ضيف متحدث 03 (يُعلن لاحقاً)',
		'role'      => 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
		'bio'       => 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
		'photo_url' => '',
		'link_url'  => '',
	),
	array(
		'name'      => 'ضيف متحدث 04 (يُعلن لاحقاً)',
		'role'      => 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
		'bio'       => 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
		'photo_url' => '',
		'link_url'  => '',
	),
	array(
		'name'      => 'ضيف متحدث 05 (يُعلن لاحقاً)',
		'role'      => 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
		'bio'       => 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
		'photo_url' => '',
		'link_url'  => '',
	),
);

// Single-Day Event Program Timeline (ONE DAY ONLY)
$schedule_items = ( ! empty( $settings['schedule_items'] ) && is_array( $settings['schedule_items'] ) ) ? $settings['schedule_items'] : array(
	array(
		'stage' => 'فاتحة الحفل · الاستقبال ومراسم الافتتاح',
		'time'  => 'الفقرة 01 · يُحدد التوقيت لاحقاً',
		'title' => 'الاستقبال والتسجيل الرقمي ومراسم الافتتاح الرسمي (Welcome & Opening)',
		'desc'  => 'استقبال الضيوف والمدعوين عبر مسح بطاقات الدخول الفردية (QR Code)، تليها كلمة الترحيب والافتتاح الرسمي لحفل إشهار تربوي.',
	),
	array(
		'stage' => 'المحور الأول · العروض الرئيسة',
		'time'  => 'الفقرة 02 · يُحدد التوقيت لاحقاً',
		'title' => 'العروض الرئيسة وإشهار الرؤية التأسيسية للاتحاد (Main Presentations)',
		'desc'  => 'استعراض الهوية المؤسسية لـ «تربوي — الاتحاد العربي للقيادة التربوية»، ومنطلقات تأسيسه، والمسارات الاستراتيجية الأولى.',
	),
	array(
		'stage' => 'المحور الثاني · الجلسات الحوارية',
		'time'  => 'الفقرة 03 · يُحدد التوقيت لاحقاً',
		'title' => 'جلسات النقاش ومحاور العمل التربوي المشترك (Discussion Sessions)',
		'desc'  => 'حوار معمّق حول أولويات القيادة التربوية في العالم العربي وآليات بناء المبادرات التشاركية العابرة للمؤسسات.',
	),
	array(
		'stage' => 'المحور الثالث · المتحدثون الرئيسيون',
		'time'  => 'الفقرة 04 · يُحدد التوقيت لاحقاً',
		'title' => 'كلمات المتحدثين الرئيسيين والشخصيات المرجعية (Keynote Speakers)',
		'desc'  => 'مداخلات رئيسة لنخبة من القادة والخبراء وصنّاع القرار التربويين حول مستقبل القيادة التعليمية.',
	),
	array(
		'stage' => 'مسك الختام · البيان الختامي والتواصل',
		'time'  => 'الفقرة 05 · يُحدد التوقيت لاحقاً',
		'title' => 'الجلسة الختامية وإعلان المخرجات والتواصل المهني (Closing Session)',
		'desc'  => 'تلاوة البيان الختامي لإشهار الاتحاد، وتوثيق الشراكات المؤسسية، ولقاء التواصل المهني بين الضيوف.',
	),
);

// Partners & Sponsors (Hidden unless official partners exist AND show_sponsors is enabled)
$sponsors      = ( ! empty( $settings['sponsors'] ) && is_array( $settings['sponsors'] ) ) ? $settings['sponsors'] : array();
$show_sponsors = ! empty( $settings['show_sponsors'] ) && ! empty( $sponsors );

$css_pub_ver = TARBWY_EM_VERSION . '.' . (int) @filemtime( TARBWY_EM_PATH . 'assets/css/tarbwy-public.css' );
$css_v2_ver  = TARBWY_EM_VERSION . '.' . (int) @filemtime( TARBWY_EM_PATH . 'assets/css/tarbwy-launch-v2.css' );
$app_js_ver  = TARBWY_EM_VERSION . '.' . (int) @filemtime( TARBWY_EM_PATH . 'assets/js/tarbwy-app.js' );

if ( empty( $is_shortcode_embed ) ) :
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="js-tv2-ready">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
	<title><?php echo esc_html( $hero_main_title . ' — ' . $hero_sub_identity ); ?></title>
	<meta name="description" content="<?php echo esc_attr( $subtitle ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( TARBWY_EM_URL . 'assets/css/tarbwy-public.css?ver=' . $css_pub_ver ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( TARBWY_EM_URL . 'assets/css/tarbwy-launch-v2.css?ver=' . $css_v2_ver ); ?>">
</head>
<body class="tv2-body" dir="rtl">
<?php endif; ?>

<div class="tv2-wrapper" dir="rtl">
	<div id="tv2-scroll-progress" class="tv2-scroll-progress" aria-hidden="true"></div>

	<!-- ======================================================================
	     FLOATING CINEMATIC HEADER
	     ====================================================================== -->
	<header id="tv2-header" class="tv2-header">
		<div class="tv2-container tv2-header-inner">
			<a href="#tv2-top" class="tv2-brand">
				<span class="tv2-brand-emblem" aria-hidden="true">ت</span>
				<span class="tv2-brand-text">
					<span class="tv2-brand-main">تربوي — الاتحاد العربي للقيادة التربوية</span>
					<span class="tv2-brand-sub">حفل الإشهار التأسيسي الرسمي</span>
				</span>
			</a>

			<nav class="tv2-nav" aria-label="التنقل الرئيسي">
				<a href="#tv2-about" class="tv2-nav-link">البيان التأسيسي</a>
				<a href="#tv2-vision" class="tv2-nav-link">الرؤية والهوية</a>
				<a href="#tv2-speakers" class="tv2-nav-link">المتحدثون</a>
				<a href="#tv2-tracks" class="tv2-nav-link">المحاور</a>
				<a href="#tv2-program" class="tv2-nav-link">البرنامج الزمني</a>
				<a href="#tv2-venue" class="tv2-nav-link">المقر والوصول</a>
			</nav>

			<div class="tv2-header-actions">
				<a href="#register" class="tv2-btn tv2-btn-gold tv2-header-cta-desktop">طلب الحضور</a>
				<button type="button" id="tv2-mobile-menu-btn" class="tv2-mobile-toggle" aria-expanded="false" aria-controls="tv2-mobile-drawer">
					<span>القائمة</span>
					<span aria-hidden="true">☰</span>
				</button>
			</div>
		</div>

		<!-- Dedicated Mobile Touch Drawer -->
		<div id="tv2-mobile-drawer" class="tv2-mobile-drawer" hidden>
			<div class="tv2-container tv2-mobile-drawer-inner">
				<a href="#tv2-about" class="tv2-mobile-link">01 · عن حفل الإشهار والبيان التأسيسي</a>
				<a href="#tv2-vision" class="tv2-mobile-link">02 · الرؤية والهوية المؤسسية لـ «تربوي»</a>
				<a href="#tv2-speakers" class="tv2-mobile-link">03 · المتحدثون والشخصيات المرجعية</a>
				<a href="#tv2-tracks" class="tv2-mobile-link">04 · المسارات والمحاور الرئيسة</a>
				<a href="#tv2-program" class="tv2-mobile-link">05 · برنامج الحفل (يوم واحد)</a>
				<a href="#tv2-venue" class="tv2-mobile-link">06 · مقر الانعقاد ومعلومات الوصول</a>
				<a href="#tv2-contact" class="tv2-mobile-link">07 · قنوات التواصل الرسمية</a>
				<a href="#register" class="tv2-btn tv2-btn-gold" style="margin-top:8px; width:100%;">طلب الحضور الآن</a>
			</div>
		</div>
	</header>

	<!-- ======================================================================
	     1. FULL-SCREEN 100VH CENTERED CINEMATIC HERO SECTION
	     ====================================================================== -->
	<section id="tv2-top" class="tv2-hero">
		<!-- Full-Width Architectural Background Image -->
		<div class="tv2-hero-bg-media" style="background-image: url('<?php echo esc_url( $hero_bg_url ); ?>');" aria-hidden="true"></div>
		<!-- Layered Dark Gradient & Volumetric Lighting -->
		<div class="tv2-hero-overlay" aria-hidden="true"></div>
		<div class="tv2-hero-light-beams" aria-hidden="true"></div>
		<!-- Subtle Floating Light Particles Canvas -->
		<canvas id="tv2-hero-particles" class="tv2-hero-particles" aria-hidden="true"></canvas>

		<div class="tv2-container">
			<div class="tv2-hero-center-stage">
				<!-- 1. Small Event Identity / Eyebrow Text -->
				<div class="tv2-hero-eyebrow">
					<span class="tv2-hero-eyebrow-dot" aria-hidden="true"></span>
					<span>الحدث التأسيسي الرسمي · الاتحاد العربي للقيادة التربوية</span>
				</div>

				<!-- 2. Main Event Title: حفل إشهار تربوي -->
				<h1 class="tv2-hero-main-title"><?php echo esc_html( $hero_main_title ); ?></h1>
				<p class="tv2-hero-sub-identity"><?php echo esc_html( $hero_sub_identity ); ?></p>

				<!-- 3. Short, Powerful Description Communicating the Event's Purpose -->
				<p class="tv2-hero-description"><?php echo esc_html( $subtitle ); ?></p>

				<!-- 4. Prominent Live Countdown Timer (Days, Hours, Minutes, Seconds) -->
				<div class="tv2-countdown-wrap" id="tv2-countdown-root" data-countdown-target="<?php echo esc_attr( $countdown_target ); ?>">
					<div class="tv2-countdown-grid" role="timer" aria-label="العد التنازلي لموعد حفل الإشهار">
						<div class="tv2-countdown-box">
							<span id="tv2-cd-days" class="tv2-countdown-digit">--</span>
							<span class="tv2-countdown-label">يوم</span>
						</div>
						<div class="tv2-countdown-box">
							<span id="tv2-cd-hours" class="tv2-countdown-digit">--</span>
							<span class="tv2-countdown-label">ساعة</span>
						</div>
						<div class="tv2-countdown-box">
							<span id="tv2-cd-minutes" class="tv2-countdown-digit">--</span>
							<span class="tv2-countdown-label">دقيقة</span>
						</div>
						<div class="tv2-countdown-box">
							<span id="tv2-cd-seconds" class="tv2-countdown-digit">--</span>
							<span class="tv2-countdown-label">ثانية</span>
						</div>
					</div>

					<div class="tv2-countdown-meta">
						<span class="tv2-countdown-meta-pill">
							<strong>الموعد:</strong> <?php echo esc_html( $date_display ); ?>
						</span>
						<span class="tv2-countdown-meta-pill">
							<strong>المقر:</strong> <?php echo esc_html( $city_display . ' · ' . $venue_name ); ?>
						</span>
						<span class="tv2-countdown-meta-pill">
							<strong>الدخول:</strong> بطاقة QR فردية لكل مشارك معتمد
						</span>
					</div>
				</div>

				<!-- 5. Two Primary CTA Buttons: اكتشف الحفل + طلب الحضور -->
				<div class="tv2-hero-cta-group">
					<a href="#register" class="tv2-btn tv2-btn-gold tv2-btn-hero">
						<span>طلب الحضور</span>
						<span aria-hidden="true">&larr;</span>
					</a>
					<a href="#tv2-about" class="tv2-btn tv2-btn-glass tv2-btn-hero">
						<span>اكتشف الحفل</span>
						<span aria-hidden="true">&darr;</span>
					</a>
				</div>

				<a href="#tv2-about" class="tv2-hero-scroll-cue" aria-label="التمرير لاستكشاف أقسام الحفل">
					<span>مرّر لاستكشاف فصول الحفل</span>
					<span class="tv2-hero-scroll-line" aria-hidden="true"></span>
				</a>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     2. EVENT IDENTITY, ABOUT THE LAUNCH & VISION (ASYMMETRIC EDITORIAL STORYTELLING)
	     ====================================================================== -->
	<section id="tv2-about" class="tv2-manifesto-section">
		<div class="tv2-container">
			<div class="tv2-manifesto-layout">
				<!-- Right Column: Editorial Narrative & Monolith Quote -->
				<div class="tv2-manifesto-sticky-col tv2-reveal">
					<span class="tv2-chapter-tag">الفصل الأول · البيان التأسيسي للحفل</span>
					<h2 class="tv2-display-heading">
						انطلاقة مؤسسية تجمع العقول القيادية لرسم أفق تربوي عربي مشترك
					</h2>
					<p class="tv2-lead-narrative">
						<?php echo nl2br( esc_html( $about_event ) ); ?>
					</p>

					<blockquote class="tv2-quote-monolith">
						<p>
							«لا يقتصر حفل إشهار تربوي على كونه مناسبة احتفالية؛ بل هو إعلان تأسيسي عن ولادة مساحة مرجعية عربية تنتقل بالقيادة التربوية من المبادرات الفردية المتفرقة إلى العمل المؤسسي المستدام.»
						</p>
						<cite>— وثيقة الإشهار · تربوي — الاتحاد العربي للقيادة التربوية</cite>
					</blockquote>
				</div>

				<!-- Left Column: Staggered Architectural Pillars -->
				<div class="tv2-pillars-stagger tv2-reveal">
					<article class="tv2-pillar-editorial-card">
						<div class="tv2-pillar-big-num" aria-hidden="true">٠١</div>
						<div>
							<h3>الإشهار المؤسسي الرسمي</h3>
							<p>
								الإعلان الرسمي عن انطلاق «تربوي — الاتحاد العربي للقيادة التربوية» واستعراض هويته التأسيسية ومنطلقاته الفكرية والتنظيمية أمام المجتمع التربوي العربي.
							</p>
						</div>
					</article>

					<article class="tv2-pillar-editorial-card">
						<div class="tv2-pillar-big-num" aria-hidden="true">٠٢</div>
						<div>
							<h3>تلاقي القامات وصنّاع القرار</h3>
							<p>
								جمع نخبة من القيادات التربوية، والشخصيات المرجعية، والخبراء، والباحثين، وممثلي المؤسسات التعليمية في لقاء رفيع المستوى يرسخ التكامل القيادي.
							</p>
						</div>
					</article>

					<article class="tv2-pillar-editorial-card">
						<div class="tv2-pillar-big-num" aria-hidden="true">٠٣</div>
						<div>
							<h3>إطلاق المسارات والمبادرات العملية</h3>
							<p>
								تحويل الرؤية التأسيسية إلى برامج عمل ومسارات مهنية ومعرفية ملموسة تسهم في تطوير المعايير القيادية وبناء الشراكات التربوية العابرة للأقطار.
							</p>
						</div>
					</article>
				</div>
			</div>

			<!-- Vision & Identity Asymmetric Contrast Band (#tv2-vision) -->
			<div id="tv2-vision" class="tv2-vision-contrast-band tv2-reveal">
				<div class="tv2-vision-dark-anchor">
					<div>
						<span class="tv2-chapter-tag" style="color:var(--tv2-gold-bright);">الفصل الثاني · الهوية المؤسسية</span>
						<h3>تربوي — الاتحاد العربي للقيادة التربوية</h3>
						<p><?php echo nl2br( esc_html( $about_tarbwy ) ); ?></p>
					</div>
					<div style="padding-top:18px; border-top:1px solid rgba(212,175,106,0.24); display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;">
						<span style="font-size:0.84rem; color:var(--tv2-gold-bright); font-weight:700;">السعة التنظيمية: <?php echo esc_html( (string) $capacity_total ); ?> مشارك ومرافق</span>
						<a href="#register" class="tv2-btn tv2-btn-gold">طلب الحضور</a>
					</div>
				</div>

				<article class="tv2-vision-architectural-panel">
					<div>
						<span class="tv2-chapter-tag">منطلق التأسيس</span>
						<h3>لماذا أُطلق «تربوي»؟</h3>
						<p><?php echo nl2br( esc_html( $why_tarbwy ) ); ?></p>
					</div>
					<div style="margin-top:20px; padding-top:14px; border-top:1px solid var(--tv2-border-strong); font-size:0.82rem; font-weight:800; color:var(--tv2-emerald);">
						01 · توحيد الجهود القيادية التربوية العربية
					</div>
				</article>

				<article class="tv2-vision-architectural-panel">
					<div>
						<span class="tv2-chapter-tag">الأثر المستهدف</span>
						<h3>ما الفضاء الذي يبنيه في المشهد التربوي؟</h3>
						<p><?php echo nl2br( esc_html( $tarbwy_space ) ); ?></p>
					</div>
					<div style="margin-top:20px; padding-top:14px; border-top:1px solid var(--tv2-border-strong); font-size:0.82rem; font-weight:800; color:var(--tv2-emerald);">
						02 · مساحة مهنية ومعرفية مشتركة ومستدامة
					</div>
				</article>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     3. SPEAKERS — SCROLL-DRIVEN STACKED EDITORIAL CARDS (Physical Folio Sheets)
	     ====================================================================== -->
	<section id="tv2-speakers" class="tv2-speakers-section">
		<div class="tv2-container">
			<div class="tv2-speakers-header-row tv2-reveal">
				<div style="max-width:680px;">
					<span class="tv2-chapter-tag" style="color:var(--tv2-gold-bright);">الفصل الثالث · المتحدثون والشخصيات المرجعية</span>
					<h2 class="tv2-display-heading" style="color:#FFFFFF; margin-bottom:12px;">
						بطاقات المتحدثين والقامات التربوية المشاركة
					</h2>
					<p style="font-size:1.02rem; color:rgba(250,247,240,0.8); margin:0; line-height:1.8;">
						تُعرض أدناه أوراق المتحدثين بتصميم البطاقات التحريرية المتراكبة (Stacked Editorial Cards) التي تتفاعل مع التمرير. جميع البطاقات قابلة للإضافة والتعديل والحذف وإعادة الترتيب من لوحة التحكم فور اعتماد القائمة النهائية.
					</p>
				</div>

				<!-- Quick-Jump Deck Switcher -->
				<div class="tv2-speakers-deck-nav" role="navigation" aria-label="التنقل السريع بين بطاقات المتحدثين">
					<?php foreach ( $speakers as $sp_idx => $sp ) : ?>
						<button type="button" class="tv2-speaker-deck-pill <?php echo 0 === $sp_idx ? 'is-active' : ''; ?>" data-tv2-speaker-jump="<?php echo (int) $sp_idx; ?>">
							بطاقة 0<?php echo (int) ( $sp_idx + 1 ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Stacked Editorial Cards Stage -->
			<div id="tv2-speakers-stage" class="tv2-speakers-stack-stage">
				<?php
				$total_speakers = count( $speakers );
				foreach ( $speakers as $sp_idx => $sp ) :
					$sp_name  = ! empty( $sp['name'] ) ? $sp['name'] : 'ضيف متحدث (يُعلن لاحقاً)';
					$sp_role  = ! empty( $sp['role'] ) ? $sp['role'] : 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير';
					$sp_bio   = ! empty( $sp['bio'] ) ? $sp['bio'] : 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.';
					$sp_photo = ! empty( $sp['photo_url'] ) ? $sp['photo_url'] : '';
					$sp_link  = ! empty( $sp['link_url'] ) ? $sp['link_url'] : '';
					?>
					<div class="tv2-speaker-stack-item" id="tv2-speaker-card-<?php echo (int) $sp_idx; ?>" data-stack-index="<?php echo (int) $sp_idx; ?>" style="--stack-index: <?php echo (int) $sp_idx; ?>;">
						<article class="tv2-speaker-folio-card">
							<!-- Large Portrait / Monogram Seal Frame -->
							<div class="tv2-speaker-folio-portrait">
								<?php if ( ! empty( $sp_photo ) ) : ?>
									<img src="<?php echo esc_url( $sp_photo ); ?>" alt="<?php echo esc_attr( $sp_name ); ?>" referrerpolicy="no-referrer" loading="lazy">
								<?php else : ?>
									<div class="tv2-speaker-seal-ring">
										<span class="tv2-speaker-seal-num">0<?php echo (int) ( $sp_idx + 1 ); ?></span>
									</div>
								<?php endif; ?>
								<span class="tv2-speaker-portrait-caption">
									<?php echo ! empty( $sp_photo ) ? 'المتحدث الرسمي 0' . (int) ( $sp_idx + 1 ) : 'صورة المتحدث · تُرفع من إعدادات الحدث'; ?>
								</span>
							</div>

							<!-- Editorial Invitation Sheet Content -->
							<div class="tv2-speaker-folio-content">
								<div>
									<div class="tv2-speaker-folio-top">
										<span class="tv2-speaker-edition-badge">
											بطاقة متحدث · 0<?php echo (int) ( $sp_idx + 1 ); ?> / 0<?php echo (int) $total_speakers; ?>
										</span>
										<span class="tv2-speaker-folio-stamp">
											حفل إشهار تربوي · القائمة المرجعية
										</span>
									</div>

									<h3 class="tv2-speaker-folio-name"><?php echo esc_html( $sp_name ); ?></h3>
									<p class="tv2-speaker-folio-role"><?php echo esc_html( $sp_role ); ?></p>
									<p class="tv2-speaker-folio-bio"><?php echo esc_html( $sp_bio ); ?></p>
								</div>

								<div class="tv2-speaker-folio-footer">
									<?php if ( ! empty( $sp_link ) ) : ?>
										<a href="<?php echo esc_url( $sp_link ); ?>" target="_blank" rel="noopener noreferrer" class="tv2-btn tv2-btn-emerald" style="padding:8px 18px; min-height:38px; font-size:0.84rem;">
											<span>استعراض الملف التعريفي والمؤسسي</span>
											<span aria-hidden="true">&larr;</span>
										</a>
									<?php else : ?>
										<span style="font-size:0.82rem; font-weight:700; color:var(--tv2-ink-muted);">
											ملف تعريفي قابل للتحرير والإضافة والحذف من لوحة تحكم ووردبريس
										</span>
									<?php endif; ?>

									<a href="#register" style="font-size:0.84rem; font-weight:800; color:var(--tv2-emerald); text-decoration:none;">
										احجز مقعدك لحضور الجلسة &larr;
									</a>
								</div>
							</div>
						</article>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     4. EVENT TRACKS & MAIN THEMES — NUMBERED ARCHITECTURAL MONOLITHS
	     ====================================================================== -->
	<section id="tv2-tracks" class="tv2-tracks-section">
		<div class="tv2-container">
			<div class="tv2-reveal" style="max-width:740px;">
				<span class="tv2-chapter-tag">الفصل الرابع · المسارات والمحاور الرئيسة</span>
				<h2 class="tv2-display-heading" style="margin-bottom:12px;">
					المسارات الفكرية والمحاور التأسيسية للحفل
				</h2>
				<p style="font-size:1.04rem; color:var(--tv2-ink-secondary); margin:0; line-height:1.8;">
					صُممت هذه المسارات لتكون قابلة للتحرير الكامل من إعدادات الحدث فور اعتماد المحاور الرسمية لحفل الإشهار، دون اختلاق أي محاور غير معتمدة.
				</p>
			</div>

			<div class="tv2-tracks-showcase tv2-reveal">
				<?php foreach ( $tracks_items as $t_idx => $track ) : ?>
					<article class="tv2-track-monolith">
						<span class="tv2-track-watermark" aria-hidden="true">0<?php echo (int) ( $t_idx + 1 ); ?></span>
						<div>
							<div class="tv2-track-header">
								<div class="tv2-track-emblem" aria-hidden="true">
									<?php if ( 0 === $t_idx % 4 ) : ?>
										<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
									<?php elseif ( 1 === $t_idx % 4 ) : ?>
										<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
									<?php elseif ( 2 === $t_idx % 4 ) : ?>
										<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
									<?php else : ?>
										<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
									<?php endif; ?>
								</div>
								<span class="tv2-track-badge"><?php echo esc_html( $track['badge'] ?? 'مسار رئيسي' ); ?></span>
							</div>
							<h3><?php echo esc_html( $track['title'] ?? '' ); ?></h3>
							<p><?php echo esc_html( $track['desc'] ?? '' ); ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     5. EVENT SCHEDULE — SINGLE-DAY VERTICAL TIMELINE (ONE DAY ONLY)
	     ====================================================================== -->
	<section id="tv2-program" class="tv2-schedule-section">
		<div class="tv2-container-narrow">
			<div class="tv2-reveal" style="text-align:center; max-width:740px; margin-inline:auto;">
				<span class="tv2-chapter-tag" style="justify-content:center;">الفصل الخامس · البرنامج الزمني للحفل</span>
				<h2 class="tv2-display-heading" style="margin-bottom:10px;">
					مسار فقرات وجلسات يوم الإشهار
				</h2>
				<p style="font-size:1.02rem; color:var(--tv2-ink-secondary); margin:0; line-height:1.8;">
					ينعقد حفل إشهار «تربوي» في <strong>يوم واحد مكثف</strong> يبدأ بمراسم الاستقبال والتسجيل الرقمي وينتهي بالبيان الختامي ولقاء التواصل المهني. جميع الأوقات والفقرات قابلة للتحديث الفوري من إعدادات الحدث.
				</p>
				<div class="tv2-oneday-banner">
					<span>برنامج يوم واحد متكامل · <?php echo esc_html( $date_display ); ?></span>
				</div>
			</div>

			<!-- Single-Day Connected Vertical Timeline -->
			<div id="tv2-single-timeline" class="tv2-single-timeline">
				<!-- Continuous Vertical Axis with Scroll-Driven Progress Beam -->
				<div class="tv2-timeline-axis" aria-hidden="true">
					<div id="tv2-timeline-axis-fill" class="tv2-timeline-axis-fill"></div>
				</div>

				<?php
				$total_sessions = count( $schedule_items );
				foreach ( $schedule_items as $s_idx => $item ) :
					$is_first = ( 0 === $s_idx );
					$is_last  = ( $s_idx === $total_sessions - 1 );
					$node_cls = 'tv2-timeline-node tv2-reveal';
					if ( $is_first ) {
						$node_cls .= ' is-opening';
					} elseif ( $is_last ) {
						$node_cls .= ' is-closing';
					}
					$s_stage = ! empty( $item['stage'] ) ? $item['stage'] : ( $is_first ? 'فاتحة الحفل · مراسم الافتتاح' : ( $is_last ? 'مسك الختام · الجلسة الختامية' : sprintf( 'الجلسة 0%d', $s_idx + 1 ) ) );
					?>
					<div class="<?php echo esc_attr( $node_cls ); ?>">
						<!-- Time Column (Placed above title on mobile, side column on desktop) -->
						<div class="tv2-timeline-time-box">
							<span class="tv2-timeline-time-pill">
								<?php echo esc_html( $item['time'] ?? 'يُحدد لاحقاً' ); ?>
							</span>
							<span class="tv2-timeline-seq-label">
								المرحلة 0<?php echo (int) ( $s_idx + 1 ); ?> من 0<?php echo (int) $total_sessions; ?>
							</span>
						</div>

						<!-- Distinct Visual Node Marker on the Vertical Axis -->
						<div class="tv2-timeline-marker-col" aria-hidden="true">
							<div class="tv2-timeline-marker-dot">
								<?php if ( $is_first ) : ?>
									<!-- Opening Star Icon -->
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
								<?php elseif ( $is_last ) : ?>
									<!-- Closing Check/Seal Icon -->
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
								<?php else : ?>
									<!-- Session Clock/Mic Icon -->
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
								<?php endif; ?>
							</div>
						</div>

						<!-- Session Card -->
						<article class="tv2-timeline-card">
							<?php if ( $is_first ) : ?>
								<span class="tv2-timeline-ceremonial-tag">★ الجلسة الافتتاحية وبداية مراسم الإشهار</span>
							<?php elseif ( $is_last ) : ?>
								<span class="tv2-timeline-ceremonial-tag">✦ الجلسة الختامية وإعلان المخرجات التأسيسية</span>
							<?php else : ?>
								<span class="tv2-timeline-session-tag"><?php echo esc_html( $s_stage ); ?></span>
							<?php endif; ?>

							<h3><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
							<?php if ( ! empty( $item['desc'] ) ) : ?>
								<p><?php echo esc_html( $item['desc'] ); ?></p>
							<?php endif; ?>
						</article>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     6. EVENT VENUE — ARCHITECTURAL PHOTOGRAPHY & LOCATION SHOWCASE
	     ====================================================================== -->
	<section id="tv2-venue" class="tv2-venue-section">
		<div class="tv2-container">
			<div class="tv2-reveal" style="max-width:720px;">
				<span class="tv2-chapter-tag" style="color:var(--tv2-gold-bright);">الفصل السادس · مقر الانعقاد وبروتوكول الوصول</span>
				<h2 class="tv2-display-heading" style="color:#FFFFFF; margin-bottom:10px;">
					المكان ومعلومات الوصول والدخول
				</h2>
				<p style="font-size:1.02rem; color:rgba(250,247,240,0.8); margin:0;">
					تُعرض أدناه تفاصيل القاعة المعتمدة، وإرشادات الوصول ومواقف السيارات، وآلية الدخول عبر بطاقة QR الفردية.
				</p>
			</div>

			<div class="tv2-venue-showcase tv2-reveal">
				<!-- Large Venue Image Frame with Glassmorphic Location Card -->
				<div class="tv2-venue-visual-frame" style="background-image: url('<?php echo esc_url( $venue_image_url ); ?>');">
					<div class="tv2-venue-glass-overlay">
						<span style="display:inline-block; font-size:0.78rem; font-weight:800; color:var(--tv2-gold-bright); margin-bottom:6px;">
							المقر الرسمي لانعقاد حفل الإشهار
						</span>
						<h3 style="font-size:clamp(1.3rem, 2.2vw, 1.75rem); font-weight:900; color:#FFFFFF; margin:0 0 10px 0;">
							<?php echo esc_html( $city_display . ' — ' . $venue_name ); ?>
						</h3>
						<p style="font-size:0.96rem; color:rgba(250,247,240,0.88); margin:0 0 20px 0; line-height:1.75;">
							<?php echo nl2br( esc_html( $venue_addr ) ); ?>
						</p>
						<div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px;">
							<?php if ( ! empty( $map_url ) ) : ?>
								<a href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener noreferrer" class="tv2-btn tv2-btn-gold">
									<span>فتح الموقع على الخريطة التفاعلية</span>
									<span aria-hidden="true">&larr;</span>
								</a>
							<?php else : ?>
								<span style="font-size:0.84rem; font-weight:700; color:var(--tv2-gold-bright); padding:8px 14px; border-radius:10px; background:rgba(212,175,106,0.12); border:1px solid rgba(212,175,106,0.3);">
									رابط الخريطة التفاعلية: يُفعل تلقائياً فور إدراج إحداثيات القاعة في إعدادات الحدث
								</span>
							<?php endif; ?>
							<a href="#register" class="tv2-btn tv2-btn-glass">طلب بطاقة حضور</a>
						</div>
					</div>
				</div>

				<!-- Side Stack: Arrival & QR Protocol -->
				<div class="tv2-venue-details-col">
					<div class="tv2-venue-info-panel">
						<div>
							<span style="font-size:0.78rem; font-weight:800; color:var(--tv2-gold-bright);">01 · الوصول والاصطفاف</span>
							<h3>إرشادات الوصول ومواقف السيارات</h3>
							<p><?php echo nl2br( esc_html( $access_info ) ); ?></p>
						</div>
						<div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.1); font-size:0.82rem; color:var(--tv2-gold);">
							بوابات استقبال مخصصة لكبار الشخصيات والضيوف المسجلين
						</div>
					</div>

					<div class="tv2-venue-info-panel">
						<div>
							<span style="font-size:0.78rem; font-weight:800; color:var(--tv2-gold-bright);">02 · بروتوكول بوابة الاستقبال</span>
							<h3>الدخول الحصري عبر بطاقة QR الفردية</h3>
							<p><?php echo nl2br( esc_html( $entry_instructions ) ); ?></p>
						</div>
						<div style="margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.1); font-size:0.82rem; color:var(--tv2-gold);">
							تحقق فوري عبر النظام المركزي لمنع التكرار وضمان سلاسة الدخول
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     7. CEREMONIAL REGISTRATION PAVILION (Integrated Public Registration Flow)
	     ====================================================================== -->
	<section id="register" class="tv2-registration-section">
		<div class="tv2-container">
			<div class="tv2-reg-pavilion tv2-reveal">
				<!-- Right Column: Ceremonial Invitation Charter & 4-Step QR Workflow -->
				<div class="tv2-reg-charter-side">
					<div>
						<span class="tv2-chapter-tag" style="color:var(--tv2-gold-bright);">الفصل السابع · التسجيل وطلب الحضور</span>
						<h2 style="font-size:clamp(1.6rem, 2.6vw, 2.25rem); font-weight:900; color:#FFFFFF; margin:0 0 14px 0; line-height:1.25;">
							سجّل طلب حضورك لحفل إشهار «تربوي»
						</h2>
						<p style="font-size:0.98rem; color:rgba(250,247,240,0.85); margin:0; line-height:1.8;">
							ترتبط هذه الاستمارة مباشرة بقاعدة البيانات المركزية للحدث. يُحفظ الطلب فور إرساله بحالة <strong>قيد المراجعة (Pending)</strong>، وفور اعتماده يصدر النظام بطاقة دخول رقمية مستقلة برمز <strong>QR Code</strong> للضيف الأساسي ولكل مرافق معتمد.
						</p>

						<div class="tv2-reg-charter-steps">
							<div class="tv2-reg-charter-step">
								<span class="tv2-reg-charter-step-num">01</span>
								<div>
									<strong style="display:block; color:#FFFFFF; font-size:0.95rem;">تقديم طلب الحضور والمرافقين</strong>
									<span style="font-size:0.84rem; color:rgba(250,247,240,0.75);">إدخال بياناتك الرسمية وإضافة المرافقين (حتى <?php echo esc_html( (string) $max_comp ); ?> مرافقين).</span>
								</div>
							</div>
							<div class="tv2-reg-charter-step">
								<span class="tv2-reg-charter-step-num">02</span>
								<div>
									<strong style="display:block; color:#FFFFFF; font-size:0.95rem;">إشعار الاستلام الفوري (Pending)</strong>
									<span style="font-size:0.84rem; color:rgba(250,247,240,0.75);">إصدار رقم مرجعي للطلب وإرسال رسالة تأكيد الاستلام لبريدك الإلكتروني.</span>
								</div>
							</div>
							<div class="tv2-reg-charter-step">
								<span class="tv2-reg-charter-step-num">03</span>
								<div>
									<strong style="display:block; color:#FFFFFF; font-size:0.95rem;">المراجعة والاعتماد من اللجنة</strong>
									<span style="font-size:0.84rem; color:rgba(250,247,240,0.75);">مراجعة الطلب واعتماده (Approved) أو إدراجه في قائمة الانتظار (Waitlist).</span>
								</div>
							</div>
							<div class="tv2-reg-charter-step">
								<span class="tv2-reg-charter-step-num">04</span>
								<div>
									<strong style="display:block; color:#FFFFFF; font-size:0.95rem;">إصدار بطاقات الدخول الفردية (QR)</strong>
									<span style="font-size:0.84rem; color:rgba(250,247,240,0.75);">توليد بطاقة QR مشفرة ومستقلة لكل شخص معتمد للدخول يوم الحفل.</span>
								</div>
							</div>
						</div>
					</div>

					<div style="padding-top:18px; border-top:1px solid rgba(212,175,106,0.25); font-size:0.84rem; color:rgba(250,247,240,0.8);">
						هل لديك رمز دعوة شخصية لكبار الشخصيات (VIP)؟
						<a href="<?php echo esc_url( home_url( '/launch/vip' ) ); ?>" style="color:var(--tv2-gold-bright); font-weight:800; text-decoration:underline; margin-inline-start:6px;">
							انتقل إلى بوابة كبار الشخصيات (/launch/vip) &larr;
						</a>
					</div>
				</div>

				<!-- Left Column: Live Public Registration Form (#tarbwy-public-reg-form) -->
				<div class="tv2-reg-form-side">
					<?php if ( ! $reg_open ) : ?>
						<div class="tarbwy-checkin-banner tarbwy-banner-warning">
							باب تقديم طلبات الحضور مغلق حالياً لاكتمال الطاقة الاستيعابية أو انتهاء فترة التسجيل.
						</div>
					<?php else : ?>
						<form id="tarbwy-public-reg-form" class="tarbwy-form" novalidate>
							<!-- Honeypot anti-spam field (RTL-safe clipped positioning) -->
							<div class="tarbwy-hp-field" aria-hidden="true">
								<label for="website_url_hp">اترك هذا الحقل فارغاً</label>
								<input type="text" id="website_url_hp" name="website_url_hp" tabindex="-1" autocomplete="off">
							</div>

							<div class="tarbwy-form-grid" style="margin-top:0;">
								<div class="tarbwy-field">
									<label for="reg_full_name">الاسم الكامل (كما سيظهر في بطاقة الدخول الرسمية) <span class="req">*</span></label>
									<input type="text" id="reg_full_name" name="full_name" required placeholder="مثال: د. عبدالله بن محمد السليمان">
								</div>
								<div class="tarbwy-field">
									<label for="reg_email">البريد الإلكتروني (لإرسال إشعار المراجعة وبطاقة QR) <span class="req">*</span></label>
									<input type="email" id="reg_email" name="email" dir="ltr" required placeholder="name@organization.org">
								</div>
								<div class="tarbwy-field">
									<label for="reg_phone">رقم الهاتف / الجوال <span class="req">*</span></label>
									<input type="tel" id="reg_phone" name="phone" dir="ltr" required placeholder="+966 5X XXX XXXX">
								</div>
								<div class="tarbwy-field">
									<label for="reg_organization">المؤسسة / الجهة التربوية <span class="req">*</span></label>
									<input type="text" id="reg_organization" name="organization" required placeholder="اسم الجامعة / الوزارة / المؤسسة">
								</div>
								<div class="tarbwy-field">
									<label for="reg_job_title">الصفة المهنية / المسمى الوظيفي <span class="req">*</span></label>
									<input type="text" id="reg_job_title" name="job_title" required placeholder="المسمى الوظيفي أو الصفة الأكاديمية">
								</div>
								<div class="tarbwy-field">
									<label for="reg_dietary">الاحتياجات الخاصة / الغذائية (اختياري)</label>
									<input type="text" id="reg_dietary" name="dietary_requirements" placeholder="اختياري — يُحفظ بسرية لفريق التنظيم">
								</div>
							</div>

							<!-- Companions Section -->
							<div class="tarbwy-companions-box">
								<div class="tarbwy-companions-header">
									<div>
										<h3>إضافة مرافقين (الحد الأقصى المسموح: <?php echo esc_html( (string) $max_comp ); ?>)</h3>
										<p class="tarbwy-muted">يُنشأ لكل مرافق سجل مستقل في قاعدة البيانات ويصدر له رمز QR مستقل عند اعتماد الطلب.</p>
									</div>
									<button type="button" id="tarbwy-add-companion-btn" class="tv2-btn tv2-btn-outline-dark" data-max="<?php echo esc_attr( (string) $max_comp ); ?>">
										+ إضافة مرافق
									</button>
								</div>
								<div id="tarbwy-companions-list"></div>
							</div>

							<div id="tarbwy-reg-feedback" class="tarbwy-feedback" hidden></div>

							<div class="tarbwy-form-submit" style="margin-top:24px;">
								<button type="submit" id="tarbwy-reg-submit-btn" class="tv2-btn tv2-btn-emerald tv2-btn-hero" style="width:100%;">
									إرسال طلب الحضور للمراجعة
								</button>
							</div>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     8. PARTNERS & SPONSORS (Strictly Hidden Until Confirmed & Enabled)
	     ====================================================================== -->
	<?php if ( $show_sponsors ) : ?>
		<section id="tv2-sponsors" class="tv2-sponsors-section">
			<div class="tv2-container">
				<div class="tv2-reveal" style="text-align:center; max-width:680px; margin-inline:auto;">
					<span class="tv2-chapter-tag" style="justify-content:center;">الشركاء والرعاة الرسميون</span>
					<h2 class="tv2-display-heading" style="margin-bottom:10px;">الشركاء المؤسسيون والجهات الداعمة</h2>
				</div>
				<div class="tv2-sponsors-grid tv2-reveal">
					<?php foreach ( $sponsors as $spon ) : ?>
						<div class="tv2-sponsor-seal-card">
							<span style="display:block; font-size:0.78rem; font-weight:800; color:var(--tv2-bronze); margin-bottom:8px;">
								<?php echo esc_html( $spon['tier'] ?? 'شريك مؤسسي' ); ?>
							</span>
							<strong style="font-size:1.18rem; font-weight:900; color:var(--tv2-ink);">
								<?php echo esc_html( $spon['name'] ?? '' ); ?>
							</strong>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- ======================================================================
	     9. FOOTER & CONTACT
	     ====================================================================== -->
	<footer id="tv2-contact" class="tv2-footer">
		<div class="tv2-container">
			<div class="tv2-footer-grid">
				<div>
					<span style="display:inline-block; font-size:0.78rem; font-weight:800; color:var(--tv2-gold-bright); margin-bottom:8px;">
						الواجهة الرسمية لحفل الإشهار · Tarbwy Launch V2
					</span>
					<h2 style="font-size:clamp(1.45rem, 2.4vw, 1.9rem); font-weight:900; color:#FFFFFF; margin:0 0 12px 0;">
						تربوي — الاتحاد العربي للقيادة التربوية
					</h2>
					<p style="font-size:0.96rem; color:rgba(250,247,240,0.75); max-width:58ch; margin:0 0 24px 0; line-height:1.8;">
						للاستفسارات المتعلقة بطلبات الحضور، أو الدعوات الرسمية لكبار الشخصيات، أو التنسيق المؤسسي الخاص بحفل الإشهار، يرجى التواصل مباشرة مع اللجنة التنظيمية.
					</p>
					<div style="display:flex; flex-wrap:wrap; gap:12px;">
						<a href="#register" class="tv2-btn tv2-btn-gold">طلب الحضور</a>
						<a href="mailto:<?php echo esc_attr( $contact_email ); ?>" class="tv2-btn tv2-btn-glass">
							<?php echo esc_html( $contact_email ); ?>
						</a>
					</div>
				</div>

				<div style="background:rgba(255,255,255,0.04); border:1px solid rgba(212,175,106,0.22); border-radius:20px; padding:26px;">
					<span style="font-size:0.8rem; font-weight:800; color:var(--tv2-gold-bright);">قنوات التواصل والروابط السريعة</span>
					<div style="margin-top:14px; display:flex; flex-direction:column; gap:12px;">
						<div>
							<span style="display:block; font-size:0.76rem; color:rgba(250,247,240,0.6);">البريد الإلكتروني الرسمي</span>
							<a href="mailto:<?php echo esc_attr( $contact_email ); ?>" style="color:#FFFFFF; font-weight:800; font-size:1rem; text-decoration:none;">
								<?php echo esc_html( $contact_email ); ?>
							</a>
						</div>
						<div>
							<span style="display:block; font-size:0.76rem; color:rgba(250,247,240,0.6);">هاتف التنسيق والاستفسارات</span>
							<strong style="color:#FFFFFF; font-size:0.98rem;"><?php echo esc_html( $contact_phone ); ?></strong>
						</div>
						<div style="padding-top:8px; border-top:1px solid rgba(255,255,255,0.1); display:flex; flex-wrap:wrap; gap:14px; font-size:0.82rem;">
							<a href="<?php echo esc_url( home_url( '/launch/vip' ) ); ?>" style="color:var(--tv2-gold-bright); text-decoration:none; font-weight:700;">بوابة كبار الشخصيات (VIP)</a>
							<a href="<?php echo esc_url( home_url( '/launch/checkin' ) ); ?>" style="color:rgba(250,247,240,0.75); text-decoration:none; font-weight:700;">بوابة الاستقبال وفحص التذاكر</a>
						</div>
					</div>
				</div>
			</div>

			<div class="tv2-footer-bottom">
				<div>
					© <?php echo esc_html( gmdate( 'Y' ) ); ?> تربوي — الاتحاد العربي للقيادة التربوية · جميع الحقوق محفوظة
				</div>
				<div class="tv2-footer-links">
					<a href="#tv2-top">الرئيسية</a>
					<a href="#tv2-about">البيان التأسيسي</a>
					<a href="#tv2-vision">الرؤية والهوية</a>
					<a href="#tv2-speakers">المتحدثون</a>
					<a href="#tv2-tracks">المحاور</a>
					<a href="#tv2-program">البرنامج الزمني</a>
					<a href="#tv2-venue">المقر</a>
					<a href="#register">طلب الحضور</a>
				</div>
			</div>
		</div>
	</footer>
</div>

<?php if ( empty( $is_shortcode_embed ) ) : ?>
<script>
window.TarbwyPublicConfig = {
	restUrl: <?php echo wp_json_encode( function_exists( 'rest_url' ) && function_exists( 'esc_url_raw' ) ? esc_url_raw( rest_url( RestAPI::NAMESPACE_V1 ) ) : home_url( '/wp-json/' . RestAPI::NAMESPACE_V1 ) ); ?>,
	eventId: <?php echo (int) ( $event->id ?? 1 ); ?>,
	maxCompanions: <?php echo (int) $max_comp; ?>
};
</script>
<script src="<?php echo esc_url( TARBWY_EM_URL . 'assets/js/tarbwy-app.js?ver=' . $app_js_ver ); ?>"></script>
<?php endif; ?>

<script>
(function () {
	'use strict';

	var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// 1. Mobile Touch Drawer Toggle
	var menuBtn = document.getElementById('tv2-mobile-menu-btn');
	var drawer = document.getElementById('tv2-mobile-drawer');
	if (menuBtn && drawer) {
		menuBtn.addEventListener('click', function () {
			var isOpen = !drawer.hidden;
			drawer.hidden = isOpen;
			menuBtn.setAttribute('aria-expanded', String(!isOpen));
			menuBtn.innerHTML = isOpen ? '<span>القائمة</span><span aria-hidden="true">☰</span>' : '<span>إغلاق</span><span aria-hidden="true">✕</span>';
		});
		drawer.querySelectorAll('a').forEach(function (link) {
			link.addEventListener('click', function () {
				drawer.hidden = true;
				menuBtn.setAttribute('aria-expanded', 'false');
				menuBtn.innerHTML = '<span>القائمة</span><span aria-hidden="true">☰</span>';
			});
		});
	}

	// 2. Live 4-Unit Countdown Timer (Days, Hours, Minutes, Seconds)
	// Uses admin-configured countdown_target if set; otherwise uses a rolling configurable preview horizon so digits animate live without inventing a fixed official date.
	var cdRoot = document.getElementById('tv2-countdown-root');
	var elDays = document.getElementById('tv2-cd-days');
	var elHours = document.getElementById('tv2-cd-hours');
	var elMinutes = document.getElementById('tv2-cd-minutes');
	var elSeconds = document.getElementById('tv2-cd-seconds');

	if (cdRoot && elDays && elHours && elMinutes && elSeconds) {
		var rawTarget = (cdRoot.getAttribute('data-countdown-target') || '').trim();
		var targetMs = 0;
		if (rawTarget) {
			var parsed = Date.parse(rawTarget.replace(' ', 'T'));
			if (!isNaN(parsed) && parsed > Date.now()) {
				targetMs = parsed;
			}
		}
		if (!targetMs) {
			// Dynamic rolling horizon anchored to start of current day + 14 days so countdown ticks live while awaiting official date lock
			var nowDate = new Date();
			var anchor = new Date(nowDate.getFullYear(), nowDate.getMonth(), nowDate.getDate() + 14, 18, 0, 0);
			targetMs = anchor.getTime();
		}

		var prevSec = -1;
		function pad2(n) {
			return n < 10 ? '0' + n : String(n);
		}
		function updateCountdown() {
			var diff = Math.max(0, targetMs - Date.now());
			var totalSec = Math.floor(diff / 1000);
			var d = Math.floor(totalSec / 86400);
			var h = Math.floor((totalSec % 86400) / 3600);
			var m = Math.floor((totalSec % 3600) / 60);
			var s = totalSec % 60;

			elDays.textContent = pad2(d);
			elHours.textContent = pad2(h);
			elMinutes.textContent = pad2(m);
			elSeconds.textContent = pad2(s);

			if (s !== prevSec && !prefersReducedMotion) {
				prevSec = s;
				elSeconds.classList.add('is-ticking');
				setTimeout(function () {
					elSeconds.classList.remove('is-ticking');
				}, 180);
			}
		}
		updateCountdown();
		setInterval(updateCountdown, 1000);
	}

	// 3. Subtle Floating Light Particles in Hero Canvas
	var canvas = document.getElementById('tv2-hero-particles');
	if (canvas && canvas.getContext && !prefersReducedMotion) {
		var ctx = canvas.getContext('2d');
		var particles = [];
		var particleCount = 28;

		function resizeCanvas() {
			canvas.width = canvas.offsetWidth || window.innerWidth;
			canvas.height = canvas.offsetHeight || window.innerHeight;
		}
		resizeCanvas();
		window.addEventListener('resize', resizeCanvas, { passive: true });

		for (var i = 0; i < particleCount; i++) {
			particles.push({
				x: Math.random() * canvas.width,
				y: Math.random() * canvas.height,
				r: 1.2 + Math.random() * 2.2,
				vx: (Math.random() - 0.5) * 0.25,
				vy: -0.15 - Math.random() * 0.35,
				alpha: 0.2 + Math.random() * 0.55
			});
		}

		function drawParticles() {
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			for (var j = 0; j < particles.length; j++) {
				var p = particles[j];
				p.x += p.vx;
				p.y += p.vy;
				if (p.y < -10) {
					p.y = canvas.height + 10;
					p.x = Math.random() * canvas.width;
				}
				if (p.x < -10) p.x = canvas.width + 10;
				if (p.x > canvas.width + 10) p.x = -10;

				ctx.beginPath();
				ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
				ctx.fillStyle = 'rgba(230, 199, 134, ' + p.alpha + ')';
				ctx.fill();
			}
			requestAnimationFrame(drawParticles);
		}
		requestAnimationFrame(drawParticles);
	}

	// 4. ScrollTrigger-Style Stacked Editorial Speaker Cards Engine + Timeline Spine Progress
	var stackItems = Array.prototype.slice.call(document.querySelectorAll('.tv2-speaker-stack-item'));
	var deckPills = Array.prototype.slice.call(document.querySelectorAll('[data-tv2-speaker-jump]'));
	var timelineEl = document.getElementById('tv2-single-timeline');
	var timelineFillEl = document.getElementById('tv2-timeline-axis-fill');
	var progressBar = document.getElementById('tv2-scroll-progress');
	var headerEl = document.getElementById('tv2-header');

	if (deckPills.length && stackItems.length) {
		deckPills.forEach(function (pill) {
			pill.addEventListener('click', function () {
				var idx = parseInt(pill.getAttribute('data-tv2-speaker-jump') || '0', 10);
				var targetCard = stackItems[idx];
				if (targetCard) {
					targetCard.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });
				}
			});
		});
	}

	var tickingScroll = false;
	function updateScrollDrivenAnimations() {
		tickingScroll = false;
		var scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
		var vh = window.innerHeight || 800;

		if (headerEl) {
			headerEl.classList.toggle('is-scrolled', scrollTop > 40);
		}

		if (progressBar) {
			var docHeight = Math.max(1, document.documentElement.scrollHeight - vh);
			var pct = Math.min(100, Math.max(0, (scrollTop / docHeight) * 100));
			progressBar.style.width = pct + '%';
		}

		// Single-Day Vertical Timeline Scroll Fill
		if (timelineEl && timelineFillEl) {
			var tlRect = timelineEl.getBoundingClientRect();
			var startTrigger = vh * 0.72;
			var scrolledInTl = startTrigger - tlRect.top;
			var tlPct = Math.min(100, Math.max(0, (scrolledInTl / Math.max(1, tlRect.height)) * 100));
			timelineFillEl.style.height = tlPct + '%';
		}

		// Desktop/Tablet Stacked Speaker Cards 3D Depth & Rotation
		if (stackItems.length && window.innerWidth >= 768 && !prefersReducedMotion) {
			var activeIdx = 0;
			stackItems.forEach(function (item, idx) {
				var rect = item.getBoundingClientRect();
				var stickyTop = 92 + idx * 20;
				var nextItem = stackItems[idx + 1];
				var overlapProgress = 0;

				if (nextItem) {
					var nextRect = nextItem.getBoundingClientRect();
					var distance = nextRect.top - stickyTop;
					overlapProgress = Math.min(1, Math.max(0, 1 - distance / 380));
				}

				if (rect.top <= stickyTop + 40) {
					activeIdx = idx;
				}

				var scale = 1 - overlapProgress * 0.055;
				var rotSign = idx % 2 === 0 ? -1 : 1;
				var rotate = overlapProgress * rotSign * 1.15;
				var brightness = 1 - overlapProgress * 0.14;

				item.style.transform = 'scale(' + scale.toFixed(3) + ') rotate(' + rotate.toFixed(2) + 'deg)';
				item.style.filter = 'brightness(' + brightness.toFixed(2) + ')';
			});

			deckPills.forEach(function (pill, pIdx) {
				pill.classList.toggle('is-active', pIdx === activeIdx);
			});
		}
	}

	window.addEventListener(
		'scroll',
		function () {
			if (!tickingScroll) {
				tickingScroll = true;
				requestAnimationFrame(updateScrollDrivenAnimations);
			}
		},
		{ passive: true }
	);
	updateScrollDrivenAnimations();

	// 5. Scroll-Triggered Reveal Observer
	var reveals = document.querySelectorAll('.tv2-reveal');
	if ('IntersectionObserver' in window && reveals.length) {
		var io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						entry.target.classList.add('is-visible');
						io.unobserve(entry.target);
					}
				});
			},
			{ threshold: 0.1, rootMargin: '0px 0px -30px 0px' }
		);
		reveals.forEach(function (el) {
			if (!el.classList.contains('is-visible')) {
				io.observe(el);
			}
		});
	} else {
		reveals.forEach(function (el) {
			el.classList.add('is-visible');
		});
	}
})();
</script>
<?php if ( empty( $is_shortcode_embed ) ) : ?>
</body>
</html>
<?php endif; ?>
