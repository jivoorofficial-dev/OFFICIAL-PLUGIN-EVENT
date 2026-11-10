<?php
/**
 * WordPress Admin Dashboard Template (Scoped RTL Arabic & LTR-Safe)
 *
 * Covers all administrative modules:
 * - Dashboard Overview
 * - Guest List (Paginated, Filtered)
 * - Registration Requests (Approve & Generate Individual QR, Reject, Waitlist, Change Status, Edit, Delete)
 * - VIP Invitations (Create with Phone, View, Edit, Resend without duplicates, Cancel/Revoke, Delete)
 * - Tickets & QR (Reissue, Status, Direct View)
 * - Check-in Launchpad
 * - Reports & Export (All 5 Categories: All, Approved, Rejected, Waitlist, Pending + Excel, CSV, PDF, Print)
 * - Staff & Permissions (RBAC Role Assignment with Administrator Protection & Persistence)
 * - Event Settings (All 9 /launch sections + Hostinger SMTP & Sender Identity)
 * - Email Templates, Isolated Iframe Live Preview, Per-Person & Bulk Dispatch, and Delivery Logs with Retry
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'tarbwy-em-dashboard';
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
$eid = (int) $event->id;
$settings = json_decode( (string) ( $event->settings_json ?? '{}' ), true );
if ( ! is_array( $settings ) ) {
	$settings = array();
}

$guests_table = $wpdb->prefix . 'tarbwy_guests';
$reg_table    = $wpdb->prefix . 'tarbwy_registrations';
$inv_table    = $wpdb->prefix . 'tarbwy_invitations';
$tkt_table    = $wpdb->prefix . 'tarbwy_tickets';
$mail_table   = $wpdb->prefix . 'tarbwy_email_logs';

// Self-heal orphaned registrations, approved unissued tickets, and duplicate VIP rows on any admin tab load
Installer::heal_orphaned_registrations();

$report_stats = CSVHandler::get_report_statistics( $eid );
$role_labels  = Roles::get_role_labels();
?>
<div class="wrap tarbwy-admin-wrap" dir="rtl">
	<header class="tarbwy-admin-header">
		<div>
			<h1>إدارة فعاليات تربوي — Tarbwy Event Manager</h1>
			<p class="tarbwy-admin-subtitle"><?php echo esc_html( $event ? $event->title : '' ); ?> · السعة المعتمدة: <?php echo esc_html( (int) ( $event->capacity_total ?? 500 ) ); ?> شخص (شاملاً المرافقين)</p>
		</div>
		<div class="tarbwy-admin-quicklinks">
			<a href="<?php echo esc_url( home_url( '/launch' ) ); ?>" target="_blank" class="button">صفحة الحدث الرسمية (/launch)</a>
			<a href="<?php echo esc_url( home_url( '/launch/vip' ) ); ?>" target="_blank" class="button">بوابة VIP (/launch/vip)</a>
			<a href="<?php echo esc_url( home_url( '/launch/checkin' ) ); ?>" target="_blank" class="button button-primary">بوابة الدخول (/launch/checkin)</a>
		</div>
	</header>

	<!-- Top Navigation Tabs -->
	<nav class="nav-tab-wrapper tarbwy-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-dashboard' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-dashboard' === $current_page ? 'nav-tab-active' : ''; ?>">لوحة المؤشرات</a>
		<?php if ( Roles::can_review_registrations() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-registrations' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-registrations' === $current_page ? 'nav-tab-active' : ''; ?>">طلبات التسجيل (<?php echo (int) $report_stats['public_pending']; ?>)</a>
		<?php endif; ?>
		<?php if ( Roles::can_manage_guests() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-guests' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-guests' === $current_page ? 'nav-tab-active' : ''; ?>">قائمة الضيوف (<?php echo (int) $report_stats['total_individuals']; ?>)</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-tickets' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-tickets' === $current_page ? 'nav-tab-active' : ''; ?>">التذاكر وQR (<?php echo (int) $report_stats['valid_qr_codes']; ?>)</a>
		<?php endif; ?>
		<?php if ( Roles::can_manage_invitations() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-vip' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-vip' === $current_page ? 'nav-tab-active' : ''; ?>">دعوات VIP (<?php echo (int) $report_stats['vip_submissions']; ?>)</a>
		<?php endif; ?>
		<?php if ( Roles::can_export() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-reports' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-reports' === $current_page ? 'nav-tab-active' : ''; ?>">التقارير والتصدير</a>
		<?php endif; ?>
		<?php if ( Roles::can_manage_events() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-emails' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-emails' === $current_page ? 'nav-tab-active' : ''; ?>">إرسال البريد والقوالب</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-staff' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-staff' === $current_page ? 'nav-tab-active' : ''; ?>">الموظفون والصلاحيات</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-settings' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-settings' === $current_page ? 'nav-tab-active' : ''; ?>">إعدادات الحدث</a>
		<?php endif; ?>
	</nav>

	<!-- Global Admin Notices -->
	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				if ( isset( $_GET['assigned_role'] ) ) {
					$assigned_key   = sanitize_key( wp_unslash( $_GET['assigned_role'] ) );
					$assigned_label = $role_labels[ $assigned_key ] ?? $assigned_key;
					echo 'تم تحديث وحفظ صلاحية المستخدم بنجاح إلى: <strong>' . esc_html( $assigned_label ) . '</strong> (مع الحفاظ الكامل على أدوار WordPress الأساسية).';
				} elseif ( isset( $_GET['decision'] ) ) {
					$dec_key = sanitize_key( wp_unslash( $_GET['decision'] ) );
					$qr_cnt  = isset( $_GET['qr_count'] ) ? absint( $_GET['qr_count'] ) : 0;
					if ( 'approved' === $dec_key ) {
						echo 'تم اعتماد التسجيل بنجاح وإصدار <strong>(' . (int) $qr_cnt . ') بطاقات دخول فردية برمز QR مستقل</strong> للضيف الأساسي والمرافقين المعتمدين.';
					} elseif ( 'declined' === $dec_key ) {
						echo 'تم تحديث حالة الطلب إلى <strong>مرفوض (Rejected)</strong> وإبطال أي بطاقات QR سابقة مرتبطة به فوراً.';
					} elseif ( 'waitlist' === $dec_key ) {
						echo 'تم تحويل الطلب إلى <strong>قائمة الانتظار (Waiting List)</strong> وإبطال أي بطاقات QR سابقة مرتبطة به.';
					} else {
						echo 'تم تحديث حالة التسجيل بنجاح.';
					}
				} else {
					echo 'تم حفظ التغييرات بنجاح.';
				}
				?>
			</p>
		</div>
	<?php endif; ?>
	<?php if ( isset( $_GET['email_sent'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تم إرسال بريد الإشعار الرسمي وتضمين بطاقات الدخول (QR) بنجاح عبر خادم البريد.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['retry_sent'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تمت إعادة إرسال البريد الإلكتروني بنجاح دون إنشاء أي رموز QR مكررة.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['email_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong>تنبيه بريد SMTP:</strong> <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['email_error'] ) ) ); ?>
				<?php if ( ! empty( $_GET['retry_log_id'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:10px;">
						<input type="hidden" name="action" value="tarbwy_retry_email">
						<input type="hidden" name="log_id" value="<?php echo esc_attr( absint( $_GET['retry_log_id'] ) ); ?>">
						<input type="hidden" name="return_page" value="<?php echo esc_attr( $current_page ); ?>">
						<?php wp_nonce_field( 'tarbwy_retry_email_nonce' ); ?>
						<button type="submit" class="button button-small">إعادة محاولة إرسال البريد الآن (دون تكرار QR)</button>
					</form>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>
	<?php if ( isset( $_GET['edited'] ) || isset( $_GET['vip_edited'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تم تحديث بيانات السجل وحفظها في قاعدة البيانات بنجاح.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) || isset( $_GET['vip_deleted'] ) ) : ?>
		<div class="notice notice-warning is-dismissible"><p>تم حذف السجل وإبطال كافة البطاقات المرتبطة به وتوثيق العملية في سجل النشاط.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['vip_created'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تم إنشاء وحفظ دعوة VIP في قاعدة البيانات وإدراجها في القائمة أدناه بنجاح.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['vip_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['vip_error'] ) ) ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['role_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['role_error'] ) ) ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['sent'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تم إرسال رابط دعوة VIP الخاصة عبر البريد الإلكتروني بنجاح.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['revoked'] ) ) : ?>
		<div class="notice notice-warning is-dismissible"><p>تم إبطال رابط دعوة VIP وإلغاء أي بطاقات QR مرتبطة بها بنجاح.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['single_sent'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تم إرسال البريد الفردي للضيف المحدد بنجاح.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['bulk_sent'] ) || isset( $_GET['bulk_queued'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تمت معالجة الحملة البريدية الجماعية: تم إرسال (<?php echo absint( $_GET['bulk_sent'] ?? 0 ); ?>) من أصل (<?php echo absint( $_GET['bulk_queued'] ?? $_GET['bulk_sent'] ?? 0 ); ?>) رسالة.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['test_sent'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تم إرسال البريد التجريبي (Test Email) بنجاح عبر هوية المرسل المعتمدة (<code><?php echo esc_html( EmailManager::get_sender_config( $eid )['sender_email'] ); ?></code>).</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['test_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p>تعذر إرسال البريد التجريبي: <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['test_error'] ) ) ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['restored'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تمت استعادة قوالب البريد الإلكتروني الافتراضية المعتمدة بنجاح.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['data_reset'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><strong>تم تصفير البيانات بنجاح:</strong> تم مسح كافة بيانات التجربة (طلبات التسجيل، الضيوف، المرافقون، دعوات VIP، بطاقات QR، سجلات الدخول، وسجل البريد) وإعادة ترقيم الجداول من (1) مع الحفاظ الكامل على إعدادات الحدث وبيانات SMTP. النظام جاهز الآن للإطلاق الرسمي.</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['reset_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><strong>تعذر تصفير البيانات:</strong> <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['reset_error'] ) ) ); ?></p></div>
	<?php endif; ?>

	<!-- KPI Summary Grid (Distinguishes Submissions vs Individual Attendees + Valid QR Codes) -->
	<section class="tarbwy-kpi-grid">
		<div class="tarbwy-kpi-box">
			<span>إجمالي طلبات التسجيل (Submissions)</span>
			<strong><?php echo (int) $report_stats['all_submissions']; ?></strong>
			<small>عام: <?php echo (int) $report_stats['public_submissions']; ?> · دعوات VIP: <?php echo (int) $report_stats['vip_submissions']; ?> · قيد المراجعة: <?php echo (int) $report_stats['pending_submissions']; ?></small>
		</div>
		<div class="tarbwy-kpi-box">
			<span>المقبولون / الانتظار / المرفوضون</span>
			<strong style="color:#14532D;"><?php echo (int) $report_stats['approved_submissions']; ?></strong>
			<small>قائمة انتظار: <?php echo (int) $report_stats['waitlist_submissions']; ?> · مرفوض: <?php echo (int) $report_stats['rejected_submissions']; ?></small>
		</div>
		<div class="tarbwy-kpi-box">
			<span>إجمالي الأفراد مع المرافقين</span>
			<strong><?php echo (int) $report_stats['total_individuals']; ?></strong>
			<small>مؤكدون: <?php echo (int) $report_stats['confirmed_individuals']; ?> / <?php echo (int) ( $event->capacity_total ?? 500 ); ?> · مرافقون: <?php echo (int) $report_stats['total_companions']; ?></small>
		</div>
		<div class="tarbwy-kpi-box">
			<span>بطاقات QR الفردية الفعالة</span>
			<strong style="color:#1E3A8A;"><?php echo (int) $report_stats['valid_qr_codes']; ?></strong>
			<small>بطاقة QR مستقلة لكل شخص · ملغاة: <?php echo (int) $report_stats['revoked_qr_codes']; ?></small>
		</div>
		<div class="tarbwy-kpi-box">
			<span>الحاضرون فعلياً (Checked-in)</span>
			<strong style="color:#14532D;"><?php echo (int) $report_stats['checked_in_individuals']; ?></strong>
			<small>المتبقي للدخول: <?php echo max( 0, (int) $report_stats['confirmed_individuals'] - (int) $report_stats['checked_in_individuals'] ); ?></small>
		</div>
	</section>

	<?php if ( 'tarbwy-em-settings' === $current_page && Roles::can_manage_events() ) : ?>
		<?php
		$smtp_diag   = EmailManager::diagnose_smtp_and_domain( $eid );
		$track_lines = array();
		if ( ! empty( $settings['tracks_items'] ) && is_array( $settings['tracks_items'] ) ) {
			foreach ( $settings['tracks_items'] as $tr ) {
				$track_lines[] = ( $tr['title'] ?? '' ) . ' | ' . ( $tr['badge'] ?? 'مسار رئيسي' ) . ' | ' . ( $tr['desc'] ?? '' );
			}
		}
		$sched_lines = array();
		if ( ! empty( $settings['schedule_items'] ) && is_array( $settings['schedule_items'] ) ) {
			foreach ( $settings['schedule_items'] as $it ) {
				$sched_lines[] = ( $it['time'] ?? '' ) . ' | ' . ( $it['title'] ?? '' ) . ' | ' . ( $it['desc'] ?? '' );
			}
		}
		$spk_lines = array();
		if ( ! empty( $settings['speakers'] ) && is_array( $settings['speakers'] ) ) {
			foreach ( $settings['speakers'] as $sp ) {
				$line_parts = array(
					$sp['name'] ?? '',
					$sp['role'] ?? '',
					$sp['bio'] ?? '',
					$sp['photo_url'] ?? '',
					$sp['link_url'] ?? '',
				);
				$spk_lines[] = rtrim( implode( ' | ', $line_parts ), ' |' );
			}
		}
		$spon_lines = array();
		if ( ! empty( $settings['sponsors'] ) && is_array( $settings['sponsors'] ) ) {
			foreach ( $settings['sponsors'] as $spon ) {
				$spon_lines[] = ( $spon['name'] ?? '' ) . ' | ' . ( $spon['tier'] ?? 'شريك مؤسسي' );
			}
		}
		?>
		<section class="tarbwy-admin-panel">
			<h2>إعدادات الحدث، خادم البريد Hostinger SMTP، ومحتوى صفحة الهبوط (<code>/launch</code>)</h2>
			<p>جميع الحقول أدناه تنعكس فوراً في صفحة الحدث الرسمية المعتمدة (<code><a href="<?php echo esc_url( home_url( '/launch' ) ); ?>" target="_blank">/launch</a></code>) وفي نظام البريد الرسمي.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tarbwy_save_settings">
				<?php wp_nonce_field( 'tarbwy_save_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="title">1. اسم حفل الإشهار (Hero Title)</label></th>
						<td><input type="text" id="title" name="title" class="large-text" value="<?php echo esc_attr( $event->title ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="subtitle">هوية / عبارة الحفل الرئيسة (Hero Subtitle)</label></th>
						<td><input type="text" id="subtitle" name="subtitle" class="large-text" value="<?php echo esc_attr( $settings['subtitle'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="date_display">التاريخ والوقت (النص المعروض)</label></th>
						<td>
							<input type="text" id="date_display" name="date_display" class="regular-text" value="<?php echo esc_attr( $settings['date_display'] ?? 'سيُعلن لاحقاً' ); ?>">
							&nbsp; | &nbsp;
							<label for="countdown_target"><strong>توقيت العد التنازلي الحي (اختياري — YYYY-MM-DDTHH:MM):</strong></label>
							<input type="datetime-local" id="countdown_target" name="countdown_target" dir="ltr" value="<?php echo esc_attr( $settings['countdown_target'] ?? '' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="hero_bg_url">الصورة الخلفية السينمائية للـ Hero وصورة القاعة (/launch)</label></th>
						<td>
							<div style="display:flex; gap:12px; flex-wrap:wrap;">
								<div style="flex:1; min-width:260px;">
									<label for="hero_bg_url"><strong>رابط صورة خلفية الـ Hero (اتركه فارغاً لاستخدام الصورة المعمارية المدمجة):</strong></label><br>
									<input type="url" id="hero_bg_url" name="hero_bg_url" class="large-text" dir="ltr" placeholder="<?php echo esc_attr( TARBWY_EM_URL . 'assets/img/launch-v2-hero.jpg' ); ?>" value="<?php echo esc_attr( $settings['hero_bg_url'] ?? '' ); ?>">
								</div>
								<div style="flex:1; min-width:260px;">
									<label for="venue_image_url"><strong>رابط صورة القاعة / المكان (اتركه فارغاً لاستخدام الصورة المعمارية المدمجة):</strong></label><br>
									<input type="url" id="venue_image_url" name="venue_image_url" class="large-text" dir="ltr" placeholder="<?php echo esc_attr( TARBWY_EM_URL . 'assets/img/launch-v2-venue.jpg' ); ?>" value="<?php echo esc_attr( $settings['venue_image_url'] ?? '' ); ?>">
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="city_display">المدينة</label></th>
						<td><input type="text" id="city_display" name="city_display" class="regular-text" value="<?php echo esc_attr( $settings['city_display'] ?? 'سيُعلن لاحقاً' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="about_event">2. عن حفل الإشهار</label></th>
						<td><textarea id="about_event" name="about_event" rows="3" class="large-text"><?php echo esc_textarea( $settings['about_event'] ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="about_tarbwy">3. نبذة مؤسسية مختصرة عن تربوي</label></th>
						<td><textarea id="about_tarbwy" name="about_tarbwy" rows="3" class="large-text"><?php echo esc_textarea( $settings['about_tarbwy'] ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="why_tarbwy">لماذا أُطلق تربوي؟</label></th>
						<td><textarea id="why_tarbwy" name="why_tarbwy" rows="2" class="large-text"><?php echo esc_textarea( $settings['why_tarbwy'] ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="tarbwy_space">المساحة التي يسعى إلى بنائها</label></th>
						<td><textarea id="tarbwy_space" name="tarbwy_space" rows="2" class="large-text"><?php echo esc_textarea( $settings['tarbwy_space'] ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="tracks_raw">3.ب. المسارات والمحاور الرئيسة (العنوان | التصنيف | الوصف)</label></th>
						<td>
							<textarea id="tracks_raw" name="tracks_raw" rows="4" class="large-text" placeholder="القيادة التربوية والتحول المؤسسي | المسار الأول | وصف مختصر للمحور..."><?php echo esc_textarea( implode( "\n", $track_lines ) ); ?></textarea>
							<p class="description">كل سطر يمثل مساراً في صفحة <code>/launch</code> بصيغة: <code>عنوان المسار | شارة المسار | وصف مختصر للمسار</code>. عند تركه فارغاً تظهر 4 مسارات تمهيدية قابلة للتحرير.</p>
						</td>
					</tr>
					<tr>
						<th><label for="schedule_raw">4. برنامج الحفل (الوقت | العنوان | الوصف)</label></th>
						<td><textarea id="schedule_raw" name="schedule_raw" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", $sched_lines ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="speakers_raw">5. المتحدثون والضيوف (الاسم | الصفة/المؤسسة | نبذة قصيرة | رابط الصورة | رابط تعريفي)</label></th>
						<td>
							<textarea id="speakers_raw" name="speakers_raw" rows="5" class="large-text" placeholder="الاسم الكامل | المسمى الوظيفي أو المؤسسة | نبذة مهنية موجزة | https://.../photo.jpg | https://..."><?php echo esc_textarea( implode( "\n", $spk_lines ) ); ?></textarea>
							<p class="description">يمكنك إضافة أو تعديل أو حذف أو إعادة ترتيب المتحدثين (سطر لكل متحدث). عند تركه فارغاً تعرض صفحة <code>/launch</code> 5 بطاقات تمهيدية قابلة للتحرير.</p>
						</td>
					</tr>
					<tr>
						<th><label for="venue_name">6. اسم المكان / القاعة</label></th>
						<td><input type="text" id="venue_name" name="venue_name" class="regular-text" value="<?php echo esc_attr( $event->venue_name ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="venue_address">العنوان التفصيلي</label></th>
						<td><textarea id="venue_address" name="venue_address" rows="2" class="large-text"><?php echo esc_textarea( $event->venue_address ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="map_url">رابط الخريطة (Google Maps URL)</label></th>
						<td><input type="url" id="map_url" name="map_url" class="large-text" dir="ltr" value="<?php echo esc_attr( $event->map_url ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="access_info">معلومات الوصول أو الاصطفاف</label></th>
						<td><textarea id="access_info" name="access_info" rows="2" class="large-text"><?php echo esc_textarea( $settings['access_info'] ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="sponsors_raw">7. الشركاء والرعاة (اسم الجهة | التصنيف)</label></th>
						<td>
							<label style="display:block; margin-bottom:8px;">
								<input type="checkbox" name="show_sponsors" value="1" <?php checked( 1, (int) ( $settings['show_sponsors'] ?? 0 ) ); ?>>
								<strong>إظهار قسم الشركاء والرعاة في صفحة <code>/launch</code> (يبقى مخفياً تلقائياً حتى اعتماده وتفعيله هنا)</strong>
							</label>
							<textarea id="sponsors_raw" name="sponsors_raw" rows="3" class="large-text"><?php echo esc_textarea( implode( "\n", $spon_lines ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="capacity_total">8. إعدادات طلب الحضور والسعة</label></th>
						<td>
							السعة الكلية: <input type="number" id="capacity_total" name="capacity_total" style="width:90px;" value="<?php echo esc_attr( (int) ( $event->capacity_total ?? 500 ) ); ?>">
							&nbsp; | &nbsp; الحد الأقصى للمرافقين (عام): <input type="number" id="max_companions_public" name="max_companions_public" style="width:70px;" value="<?php echo esc_attr( (int) ( $event->max_companions_public ?? 2 ) ); ?>">
							&nbsp; | &nbsp; الحد الأقصى للمرافقين (VIP): <input type="number" id="max_companions_vip" name="max_companions_vip" style="width:70px;" value="<?php echo esc_attr( (int) ( $event->max_companions_vip ?? 3 ) ); ?>">
						</td>
					</tr>
					<tr>
						<th>خيارات الاعتماد والتسجيل</th>
						<td>
							<label><input type="checkbox" name="approval_auto_confirms" value="1" <?php checked( 1, (int) ( $event->approval_auto_confirms ?? 1 ) ); ?>> الموافقة على الطلب العام تؤكد الحضور وتصدر QR تلقائياً (approval_auto_confirms)</label><br>
							<label><input type="checkbox" name="registration_open" value="1" <?php checked( 1, (int) ( $event->registration_open ?? 1 ) ); ?>> فتح باب التسجيل العام في صفحة (/launch)</label>
						</td>
					</tr>
					<tr>
						<th><label for="contact_email">9. وسيلة التواصل المخصصة للاستفسارات</label></th>
						<td>
							البريد الإلكتروني: <input type="email" id="contact_email" name="contact_email" class="regular-text" dir="ltr" value="<?php echo esc_attr( $settings['contact_email'] ?? 'noreply@tarbwy.org' ); ?>">
							&nbsp; | &nbsp; الهاتف: <input type="text" id="contact_phone" name="contact_phone" class="regular-text" value="<?php echo esc_attr( $settings['contact_phone'] ?? 'سيُعلن لاحقاً' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="sender_name">10. إعدادات Hostinger SMTP وهوية المرسل الرسمية</label></th>
						<td>
							<div style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:6px; padding:14px; margin-bottom:12px;">
								<strong>إعدادات Hostinger SMTP المعتمدة (تُقرأ من متغيرات البيئة <code>SMTP_*</code> أو من الحقول أدناه):</strong>
								<div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
									<div>
										<label><strong>SMTP Host:</strong></label><br>
										<input type="text" name="smtp_host" dir="ltr" value="<?php echo esc_attr( $smtp_diag['smtp_host'] ); ?>" class="regular-text" style="width:200px;">
									</div>
									<div>
										<label><strong>SMTP Port:</strong></label><br>
										<input type="number" name="smtp_port" dir="ltr" value="<?php echo esc_attr( (int) $smtp_diag['smtp_port'] ); ?>" style="width:90px;">
									</div>
									<div>
										<label><strong>Encryption:</strong></label><br>
										<select name="smtp_encryption" dir="ltr">
											<option value="ssl" <?php selected( strtolower( $smtp_diag['smtp_encryption'] ), 'ssl' ); ?>>SSL (Port 465)</option>
											<option value="tls" <?php selected( strtolower( $smtp_diag['smtp_encryption'] ), 'tls' ); ?>>TLS (Port 587)</option>
										</select>
									</div>
									<div>
										<label><strong>SMTP Username:</strong></label><br>
										<input type="email" name="smtp_username" dir="ltr" value="<?php echo esc_attr( $smtp_diag['smtp_username'] ); ?>" class="regular-text" style="width:210px;">
									</div>
									<div>
										<label><strong>SMTP Password (اختياري إن لم يُضبط في <code>SMTP_PASSWORD</code>):</strong></label><br>
										<input type="password" name="smtp_password" dir="ltr" placeholder="<?php echo $smtp_diag['password_set'] ? '•••••••• (محفوظ ومفعل)' : 'أدخل كلمة مرور SMTP أو اضبط SMTP_PASSWORD'; ?>" class="regular-text" style="width:260px;" autocomplete="new-password">
									</div>
								</div>
							</div>
							<div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:8px;">
								<div>
									<label for="sender_name"><strong>اسم المرسل الرسمي (SMTP_FROM_NAME):</strong></label><br>
									<input type="text" id="sender_name" name="sender_name" class="regular-text" value="<?php echo esc_attr( $settings['sender_name'] ?? 'Tarbwy' ); ?>">
								</div>
								<div>
									<label for="sender_email"><strong>بريد الإرسال الموثق (SMTP_FROM_EMAIL):</strong></label><br>
									<input type="email" id="sender_email" name="sender_email" class="regular-text" dir="ltr" value="<?php echo esc_attr( $settings['sender_email'] ?? EmailManager::DEFAULT_SENDER_EMAIL ); ?>">
								</div>
								<div>
									<label for="reply_to_email"><strong>بريد الرد (Reply-To):</strong></label><br>
									<input type="email" id="reply_to_email" name="reply_to_email" class="regular-text" dir="ltr" value="<?php echo esc_attr( $settings['reply_to_email'] ?? EmailManager::DEFAULT_REPLY_TO ); ?>">
								</div>
								<div>
									<label for="logo_url"><strong>رابط شعار المؤسسة (Logo URL):</strong></label><br>
									<input type="url" id="logo_url" name="logo_url" class="regular-text" dir="ltr" value="<?php echo esc_attr( $settings['logo_url'] ?? '' ); ?>">
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="entry_instructions">تعليمات بطاقة الدخول ورمز QR</label></th>
						<td><textarea id="entry_instructions" name="entry_instructions" rows="2" class="large-text"><?php echo esc_textarea( $settings['entry_instructions'] ?? '' ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( 'حفظ إعدادات الحدث وبيانات Hostinger SMTP' ); ?>
			</form>
		</section>

		<!-- Danger Zone: Reset / Wipe All Test Event Data Before Official Launch (Password: "tarbwy") -->
		<section class="tarbwy-admin-panel" style="margin-top:24px; border:2px solid #DC2626; border-right:6px solid #B91C1C; background:#FEF2F2;">
			<h2 style="margin-top:0; color:#991B1B;">11. تصفير كافة بيانات التجربة استعداداً للإطلاق الرسمي (Reset All Event Data)</h2>
			<p style="color:#7F1D1D; font-size:13px; line-height:1.7; margin-bottom:14px;">
				تُستخدم هذه الخاصية لحذف كافة البيانات التجريبية دفعة واحدة قبل إطلاق الحدث رسمياً، وتشمل:
				<strong>جميع طلبات التسجيل العام، قائمة الضيوف والمرافقين، دعوات كبار الشخصيات (VIP)، بطاقات الدخول ورموز QR، سجلات تسجيل الدخول (Check-in)، وسجلات البريد المرسل</strong>، مع إعادة ضبط الترقيم التسلسلي للجداول ليبدأ من <code>#1</code>.<br>
				<span style="color:#14532D; font-weight:700;">ملاحظة آمنة:</span> لن يتم المساس بإعدادات الحدث أو نصوص صفحة الهبوط (<code>/launch</code>) أو قوالب البريد أو إعدادات خادم SMTP.
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('تحذير نهائي: هل أنت متأكد من تصفير ومسح جميع طلبات التسجيل والضيوف ودعوات VIP وبطاقات QR وسجلات الدخول؟ لا يمكن التراجع عن هذه العملية.');" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; background:#FFFFFF; border:1px solid #FECACA; padding:16px; border-radius:6px;">
				<input type="hidden" name="action" value="tarbwy_reset_event_data">
				<?php wp_nonce_field( 'tarbwy_reset_event_data_nonce' ); ?>
				<div>
					<label for="tarbwy_reset_password" style="display:block; font-weight:700; color:#991B1B; margin-bottom:6px;">
						أدخل كلمة سر التصفير المعتمدة لتأكيد العملية:
					</label>
					<input type="password" id="tarbwy_reset_password" name="reset_password" required dir="ltr" placeholder="أدخل كلمة سر التصفير..." class="regular-text" style="width:240px; border-color:#FCA5A5;" autocomplete="off">
				</div>
				<div>
					<button type="submit" class="button" style="background:#DC2626; border-color:#B91C1C; color:#FFFFFF; font-weight:700; padding:4px 18px; height:36px;">
						تصفير جميع بيانات الإضافة الآن (Reset All Data)
					</button>
				</div>
			</form>
		</section>

	<?php elseif ( 'tarbwy-em-staff' === $current_page && Roles::can_manage_events() ) : ?>
		<?php
		$wp_users         = get_users( array( 'number' => 150, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$selected_user_id = isset( $_GET['selected_user'] ) ? absint( $_GET['selected_user'] ) : 0;
		if ( 0 === $selected_user_id && ! empty( $wp_users ) ) {
			$selected_user_id = (int) $wp_users[0]->ID;
		}
		$selected_user_obj         = $selected_user_id > 0 ? get_user_by( 'id', $selected_user_id ) : null;
		$selected_user_tarbwy_role = $selected_user_obj ? Roles::get_user_tarbwy_role( $selected_user_obj ) : 'tarbwy_event_admin';
		?>
		<section class="tarbwy-admin-panel">
			<h2>إدارة الموظفين والأدوار والصلاحيات (Staff & Permissions — RBAC)</h2>
			<p>تعتمد الإضافة نظام صلاحيات دقيقاً يمنع موظفي الاستقبال (Check-in Staff) من الوصول إلى طلبات التسجيل أو التصدير أو الإعدادات، مع حماية حسابات مديري النظام (WordPress Administrators) من فقدان صلاحيات الإدارة الأساسية للموقع.</p>

			<div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:18px; margin-bottom:24px;">
				<h3 style="margin-top:0;">تعيين دور موظف لمستخدم WordPress حالي</h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
					<input type="hidden" name="action" value="tarbwy_assign_staff_role">
					<?php wp_nonce_field( 'tarbwy_assign_staff_nonce' ); ?>
					<p style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin:0;">
						<label for="tarbwy-staff-user-select"><strong>اختر المستخدم:</strong></label>
						<select name="user_id" id="tarbwy-staff-user-select" required style="min-width:320px;">
							<?php
							foreach ( $wp_users as $u ) :
								$u_tarbwy_role = Roles::get_user_tarbwy_role( $u );
								$u_role_label  = $role_labels[ $u_tarbwy_role ] ?? $u_tarbwy_role;
								$wp_native     = array_diff( (array) $u->roles, Roles::get_plugin_role_keys() );
								$wp_role_str   = ! empty( $wp_native ) ? implode( ', ', $wp_native ) : implode( ', ', (array) $u->roles );
								?>
								<option value="<?php echo esc_attr( $u->ID ); ?>" data-tarbwy-role="<?php echo esc_attr( $u_tarbwy_role ); ?>" <?php selected( $selected_user_id, (int) $u->ID ); ?>>
									<?php echo esc_html( $u->display_name . ' (' . $u->user_email . ') — ' . $wp_role_str . ' [' . $u_role_label . ']' ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<label for="tarbwy-staff-role-select"><strong>الدور الجديد:</strong></label>
						<select name="tarbwy_role" id="tarbwy-staff-role-select" required style="min-width:250px;">
							<option value="tarbwy_event_admin" <?php selected( $selected_user_tarbwy_role, 'tarbwy_event_admin' ); ?>>مدير الفعالية (Event Administrator)</option>
							<option value="tarbwy_registration_manager" <?php selected( $selected_user_tarbwy_role, 'tarbwy_registration_manager' ); ?>>مسؤول تسجيل (Registration Manager)</option>
							<option value="tarbwy_checkin_staff" <?php selected( $selected_user_tarbwy_role, 'tarbwy_checkin_staff' ); ?>>موظف استقبال (Check-in Staff)</option>
							<option value="none" <?php selected( $selected_user_tarbwy_role, 'none' ); ?>>بدون دور في الفعالية (إلغاء صلاحية الموظف)</option>
						</select>

						<button type="submit" class="button button-primary">تحديث صلاحية الموظف</button>
					</p>
				</form>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>المستخدم</th>
						<th>البريد الإلكتروني</th>
						<th>دور WordPress الأساسي</th>
						<th>دور فعالية تربوي (Tarbwy Role)</th>
						<th>حالة الحماية</th>
						<th>إجراء</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wp_users as $u ) : ?>
						<?php
						$u_tarbwy_role = Roles::get_user_tarbwy_role( $u );
						$u_role_label  = $role_labels[ $u_tarbwy_role ] ?? $u_tarbwy_role;
						$is_wp_admin   = in_array( 'administrator', (array) $u->roles, true ) || user_can( $u, 'manage_options' );
						$wp_native     = array_diff( (array) $u->roles, Roles::get_plugin_role_keys() );
						?>
						<tr>
							<td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
							<td><code><?php echo esc_html( $u->user_email ); ?></code></td>
							<td><code><?php echo esc_html( ! empty( $wp_native ) ? implode( ', ', $wp_native ) : '-' ); ?></code></td>
							<td><span class="tarbwy-status-badge tarbwy-status-approved"><?php echo esc_html( $u_role_label ); ?></span></td>
							<td><?php echo $is_wp_admin ? '<span style="color:#15803d;font-weight:600;">محمي (Administrator)</span>' : 'حساب موظف قياسي'; ?></td>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-staff&selected_user=' . (int) $u->ID ) ); ?>" class="button button-small">تحديد للتعديل</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>

	<?php elseif ( 'tarbwy-em-reports' === $current_page && Roles::can_export() ) : ?>
		<?php
		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : 'all';
		$filter_type   = isset( $_GET['filter_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_type'] ) ) : 'all';
		$date_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$s_query       = isset( $_GET['s_query'] ) ? sanitize_text_field( wp_unslash( $_GET['s_query'] ) ) : '';

		$report_filters = array(
			'status'    => $filter_status,
			'reg_type'  => $filter_type,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'search'    => $s_query,
		);
		$report_rows    = CSVHandler::get_filtered_registrations_report( $eid, $report_filters );

		$category_tabs = array(
			'all'      => array( 'label' => 'جميع التسجيلات (All Registrations)', 'count' => $report_stats['all_submissions'] ),
			'approved' => array( 'label' => 'التسجيلات المعتمدة (Approved)', 'count' => $report_stats['approved_submissions'] ),
			'declined' => array( 'label' => 'التسجيلات المرفوضة (Rejected)', 'count' => $report_stats['rejected_submissions'] ),
			'waitlist' => array( 'label' => 'قائمة الانتظار (Waiting List)', 'count' => $report_stats['waitlist_submissions'] ),
			'pending'  => array( 'label' => 'قيد المراجعة (Pending)', 'count' => $report_stats['pending_submissions'] ),
		);
		?>
		<section class="tarbwy-admin-panel">
			<h2>نظام التقارير الشامل وتصدير البيانات (Reports & Multi-Format Export)</h2>
			<p>يعرض النظام جميع فئات التسجيل (المقبولة، المرفوضة، قائمة الانتظار، وقيد المراجعة) مع التمييز الدقيق بين عدد استمارات التسجيل وعدد الأفراد الإجمالي شاملاً المرافقين وبطاقات QR الفعالة.</p>

			<!-- 5 Required Registration Category Tabs -->
			<div style="display:flex; gap:8px; flex-wrap:wrap; margin:16px 0;">
				<?php foreach ( $category_tabs as $cat_key => $cat_info ) : ?>
					<?php
					$tab_url    = add_query_arg(
						array(
							'page'          => 'tarbwy-em-reports',
							'filter_status' => $cat_key,
							'filter_type'   => $filter_type,
							'date_from'     => $date_from,
							'date_to'       => $date_to,
							's_query'       => $s_query,
						),
						admin_url( 'admin.php' )
					);
					$is_active = ( $filter_status === $cat_key );
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="button <?php echo $is_active ? 'button-primary' : ''; ?>">
						<?php echo esc_html( $cat_info['label'] ); ?> (<?php echo (int) $cat_info['count']; ?>)
					</a>
				<?php endforeach; ?>
			</div>

			<!-- Filter Bar (Status, Type, Date Range, Search) -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:6px; padding:14px; margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
				<input type="hidden" name="page" value="tarbwy-em-reports">
				<div>
					<label><strong>الحالة (Status):</strong></label><br>
					<select name="filter_status">
						<option value="all" <?php selected( $filter_status, 'all' ); ?>>جميع الحالات (All)</option>
						<option value="approved" <?php selected( $filter_status, 'approved' ); ?>>المقبولون (Approved)</option>
						<option value="declined" <?php selected( $filter_status, 'declined' ); ?>>المرفوضون (Rejected)</option>
						<option value="waitlist" <?php selected( $filter_status, 'waitlist' ); ?>>قائمة الانتظار (Waitlist)</option>
						<option value="pending" <?php selected( $filter_status, 'pending' ); ?>>قيد المراجعة (Pending)</option>
					</select>
				</div>
				<div>
					<label><strong>النوع (Type):</strong></label><br>
					<select name="filter_type">
						<option value="all" <?php selected( $filter_type, 'all' ); ?>>الكل (Standard + VIP)</option>
						<option value="standard" <?php selected( $filter_type, 'standard' ); ?>>تسجيل عام (Standard)</option>
						<option value="vip" <?php selected( $filter_type, 'vip' ); ?>>كبار الشخصيات (VIP)</option>
					</select>
				</div>
				<div>
					<label><strong>من تاريخ:</strong></label><br>
					<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
				</div>
				<div>
					<label><strong>إلى تاريخ:</strong></label><br>
					<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
				</div>
				<div style="flex:1; min-width:200px;">
					<label><strong>بحث (الاسم، البريد، الهاتف، الرقم المرجعي):</strong></label><br>
					<input type="search" name="s_query" value="<?php echo esc_attr( $s_query ); ?>" placeholder="ابحث بالاسم أو البريد أو الهاتف..." style="width:100%;">
				</div>
				<div>
					<button type="submit" class="button button-primary">تطبيق الفلتر</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-reports' ) ); ?>" class="button">إعادة ضبط</a>
				</div>
			</form>

			<!-- Multi-Format Export Toolbar (Excel, CSV, PDF, Print) -->
			<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
				<div>
					<strong>عدد السجلات المطابقة للفلتر الحالي:</strong> <code><?php echo (int) count( $report_rows ); ?></code> طلب تسجيل
				</div>
				<div class="tarbwy-action-bar">
					<?php
					$base_export_args = array(
						'action'        => 'tarbwy_export_report',
						'event_id'      => $eid,
						'filter_status' => $filter_status,
						'filter_type'   => $filter_type,
						'date_from'     => $date_from,
						'date_to'       => $date_to,
						's_query'       => $s_query,
					);
					$excel_url = wp_nonce_url( add_query_arg( array_merge( $base_export_args, array( 'export_format' => 'excel' ) ), admin_url( 'admin-post.php' ) ), 'tarbwy_export_report_nonce' );
					$csv_url   = wp_nonce_url( add_query_arg( array_merge( $base_export_args, array( 'export_format' => 'csv' ) ), admin_url( 'admin-post.php' ) ), 'tarbwy_export_report_nonce' );
					$pdf_url   = wp_nonce_url( add_query_arg( array_merge( $base_export_args, array( 'export_format' => 'pdf' ) ), admin_url( 'admin-post.php' ) ), 'tarbwy_export_report_nonce' );
					$print_url = wp_nonce_url( add_query_arg( array_merge( $base_export_args, array( 'export_format' => 'print' ) ), admin_url( 'admin-post.php' ) ), 'tarbwy_export_report_nonce' );
					?>
					<a href="<?php echo esc_url( $excel_url ); ?>" class="button button-primary">تصدير Excel (.xls)</a>
					<a href="<?php echo esc_url( $csv_url ); ?>" class="button button-primary">تصدير CSV (UTF-8 BOM)</a>
					<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" class="button">تصدير PDF</a>
					<a href="<?php echo esc_url( $print_url ); ?>" target="_blank" class="button">نسخة للطباعة (Print Report)</a>
				</div>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>معرف التسجيل</th>
						<th>الاسم الكامل</th>
						<th>البريد الإلكتروني</th>
						<th>رقم الهاتف</th>
						<th>نوع التسجيل</th>
						<th>عدد المرافقين</th>
						<th>إجمالي الأفراد</th>
						<th>حالة التسجيل</th>
						<th>تاريخ التسجيل</th>
						<th>حالة بطاقات QR</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $report_rows ) ) : ?>
						<tr><td colspan="10" style="text-align:center; padding:20px;">لا توجد سجلات مطابقة لمعايير البحث أو الفلترة المحددة.</td></tr>
					<?php else : ?>
						<?php foreach ( $report_rows as $rr ) : ?>
							<tr>
								<td><code><?php echo esc_html( $rr['registration_id'] ); ?></code></td>
								<td><strong><?php echo esc_html( $rr['full_name'] ); ?></strong><br><small><?php echo esc_html( $rr['organization'] ); ?></small></td>
								<td><code><?php echo esc_html( $rr['email'] ); ?></code></td>
								<td><span dir="ltr"><?php echo esc_html( $rr['phone'] ); ?></span></td>
								<td><code><?php echo esc_html( $rr['registration_type'] ); ?></code></td>
								<td>
									<strong><?php echo (int) $rr['companions_count']; ?></strong>
									<?php if ( '-' !== $rr['companions_names'] ) : ?>
										<br><small><?php echo esc_html( $rr['companions_names'] ); ?></small>
									<?php endif; ?>
								</td>
								<td><strong><?php echo (int) $rr['total_attendees']; ?></strong></td>
								<td><span class="tarbwy-status-badge tarbwy-status-<?php echo esc_attr( $rr['registration_status'] ); ?>"><?php echo esc_html( $rr['status_label'] ); ?></span></td>
								<td><small><?php echo esc_html( $rr['registration_date'] ); ?></small></td>
								<td><code><?php echo esc_html( $rr['qr_status_label'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

	<?php elseif ( 'tarbwy-em-emails' === $current_page && Roles::can_manage_events() ) : ?>
		<?php
		$sender_cfg         = EmailManager::get_sender_config( $eid );
		$smtp_diag          = EmailManager::diagnose_smtp_and_domain( $eid );
		$all_tpls           = EmailManager::get_templates();
		$preview_tpl        = isset( $_GET['preview_tpl'] ) ? sanitize_key( wp_unslash( $_GET['preview_tpl'] ) ) : 'registration_approved';
		if ( ! isset( $all_tpls[ $preview_tpl ] ) ) {
			$preview_tpl = 'registration_approved';
		}
		$preview_pkg        = EmailManager::render_email_package( $preview_tpl, EmailManager::get_sample_placeholders( $eid ), $eid );
		$current_user       = wp_get_current_user();
		$default_test_email = ( $current_user && ! empty( $current_user->user_email ) ) ? $current_user->user_email : $sender_cfg['reply_to_email'];

		// Fetch recent email logs
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$email_logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, g.full_name FROM {$mail_table} l LEFT JOIN {$guests_table} g ON l.guest_id = g.id WHERE l.event_id = %d ORDER BY l.id DESC LIMIT 40",
				$eid
			)
		);
		?>
		<!-- Active Sender Identity & Hostinger SMTP Status Banner -->
		<section class="tarbwy-admin-panel" style="margin-bottom:20px; background:#F0FDF4; border-right:4px solid #14532D;">
			<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
				<div>
					<h3 style="margin:0 0 6px 0; color:#14532D;">هوية المرسل الرسمية وإعدادات Hostinger SMTP النشطة</h3>
					<p style="margin:0 0 4px 0; font-size:13px; color:#1C1917;">
						<strong>From:</strong> <code>"<?php echo esc_html( $sender_cfg['sender_name'] ); ?>" &lt;<?php echo esc_html( $sender_cfg['sender_email'] ); ?>&gt;</code>
						&nbsp;|&nbsp;
						<strong>Reply-To:</strong> <code>&lt;<?php echo esc_html( $sender_cfg['reply_to_email'] ); ?>&gt;</code>
					</p>
					<p style="margin:0; font-size:12px; color:#475569;">
						<strong>خادم SMTP:</strong> <code><?php echo esc_html( $smtp_diag['smtp_host'] . ':' . $smtp_diag['smtp_port'] . ' (' . $smtp_diag['smtp_encryption'] . ')' ); ?></code>
						&nbsp;|&nbsp;
						<strong>حساب المصادقة:</strong> <code><?php echo esc_html( $smtp_diag['smtp_username'] ); ?></code>
						&nbsp;|&nbsp;
						<strong>التنسيق:</strong> <code>Multipart HTML (RTL/LTR) + Plain-Text Fallback (AltBody)</code>
					</p>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-settings' ) ); ?>" class="button">ضبط إعدادات SMTP وهوية المرسل</a>
			</div>
		</section>

		<!-- 1 & 2: Per-Person and Bulk Email Dispatchers -->
		<div class="tarbwy-grid-2col">
			<section class="tarbwy-admin-panel" style="margin:0;">
				<h2>1. إرسال بريد فردي لشخص محدد (Send Email Per Person)</h2>
				<p>اختر أي ضيف أو مرافق لإرسال تذكرته أو دعوته أو تذكير خاص به بشكل فردي فوري:</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tarbwy_send_single_email">
					<?php wp_nonce_field( 'tarbwy_send_single_email_nonce' ); ?>
					<?php
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$all_guests_list = $wpdb->get_results( $wpdb->prepare( "SELECT id, full_name, email, guest_type, rsvp_status FROM {$guests_table} WHERE event_id = %d ORDER BY id DESC LIMIT 200", $eid ) );
					?>
					<p style="display:flex; flex-direction:column; gap:10px;">
						<select name="guest_id" required style="width:100%;">
							<?php foreach ( $all_guests_list as $ag ) : ?>
								<option value="<?php echo esc_attr( $ag->id ); ?>">
									<?php echo esc_html( '#' . $ag->id . ' - ' . $ag->full_name . ' (' . $ag->guest_type . ' / ' . $ag->rsvp_status . ') - ' . ( $ag->email ? $ag->email : 'عبر بريد الضيف الأساسي' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<select name="template_key" required style="width:100%;">
							<?php foreach ( $all_tpls as $tkey => $tpl ) : ?>
								<option value="<?php echo esc_attr( $tkey ); ?>"><?php echo esc_html( $tpl['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button button-primary">إرسال البريد للشخص المحدد الآن</button>
					</p>
				</form>
			</section>

			<section class="tarbwy-admin-panel" style="margin:0;">
				<h2>2. إرسال بريد جماعي لفئة كاملة (Send Bulk Email Campaign)</h2>
				<p>أرسل رسائل جماعية (تذكير للمؤكدين، بطاقات QR، دعوات VIP، أو شكر للحاضرين) عبر طابور البريد المحمي:</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tarbwy_send_bulk_email">
					<?php wp_nonce_field( 'tarbwy_send_bulk_email_nonce' ); ?>
					<p style="display:flex; flex-direction:column; gap:10px;">
						<select name="target_segment" required style="width:100%;">
							<option value="confirmed">جميع المؤكدين (Confirmed Attendees — للتذكير أو البطاقات)</option>
							<option value="checked_in">جميع الحاضرين فعلياً (Checked-in — لرسالة الشكر بعد الحدث)</option>
							<option value="vip_pending">مدعوو VIP بانتظار الرد (VIP Pending Response)</option>
							<option value="all_vip">جميع كبار الشخصيات (All VIP Guests)</option>
						</select>
						<select name="template_key" required style="width:100%;">
							<option value="event_reminder">تذكير قبل موعد الحدث للمؤكدين فقط</option>
							<option value="event_updates">إشعار وتحديثات الفعالية (Event Updates)</option>
							<option value="post_event_thank_you">رسالة شكر بعد انتهاء الحدث</option>
							<option value="vip_invitation">دعوة خاصة لكبار الشخصيات (VIP)</option>
							<option value="ticket_issued">إصدار / إعادة إرسال بطاقة الدخول (QR)</option>
						</select>
						<button type="submit" class="button button-primary">إرسال وجدولة الحملة الجماعية (Bulk Send)</button>
					</p>
				</form>
			</section>
		</div>

		<!-- 3: Isolated Iframe Live Preview (Prevents Global RTL/LTR DOM Mutation) + Test Email -->
		<section class="tarbwy-admin-panel" style="margin-bottom:20px;">
			<h2>3. معاينة القوالب المعزولة (Desktop / Mobile / Plain-Text) وإرسال بريد تجريبي</h2>
			<p>يتم عرض معاينة القالب داخل إطار معزول (<code>&lt;iframe srcdoc&gt;</code>) لضمان عدم تأثر اتجاه لوحة تحكم WordPress (سواء كانت بالعربية RTL أو الإنجليزية LTR).</p>

			<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; background:#F8FAFC; border:1px solid #CBD5E1; padding:14px; border-radius:6px; margin-bottom:16px;">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0;">
					<input type="hidden" name="page" value="tarbwy-em-emails">
					<label for="preview_tpl"><strong>اختر القالب للمعاينة:</strong></label>
					<select name="preview_tpl" id="preview_tpl" onchange="this.form.submit()">
						<?php foreach ( $all_tpls as $tkey => $tpl ) : ?>
							<option value="<?php echo esc_attr( $tkey ); ?>" <?php selected( $preview_tpl, $tkey ); ?>><?php echo esc_html( $tpl['title'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<noscript><button type="submit" class="button">عرض المعاينة</button></noscript>
				</form>

				<div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
					<button type="button" class="button button-primary" id="tarbwy-prev-btn-desktop">سطح المكتب (Desktop 640px)</button>
					<button type="button" class="button" id="tarbwy-prev-btn-mobile">الجوال (Mobile 375px)</button>
					<button type="button" class="button" id="tarbwy-prev-btn-plain">النص البديل (Plain-Text)</button>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0;">
					<input type="hidden" name="action" value="tarbwy_send_test_email">
					<input type="hidden" name="template_key" value="<?php echo esc_attr( $preview_tpl ); ?>">
					<?php wp_nonce_field( 'tarbwy_send_test_email_nonce' ); ?>
					<input type="email" name="test_email" required dir="ltr" placeholder="admin@tarbwy.org" value="<?php echo esc_attr( $default_test_email ); ?>" class="regular-text" style="width:210px;">
					<button type="submit" class="button button-primary">إرسال بريد تجريبي لهذا القالب</button>
				</form>
			</div>

			<div class="tarbwy-email-preview-stage">
				<div style="margin-bottom:10px; font-size:13px; color:#1E293B;">
					<strong>عنوان الرسالة (Subject):</strong> <?php echo esc_html( $preview_pkg['subject'] ); ?>
				</div>
				<div id="tarbwy-email-preview-html-wrap" class="tarbwy-email-iframe-container">
					<iframe id="tarbwy-email-preview-iframe" title="Email Template Preview" sandbox="allow-same-origin" srcdoc="<?php echo esc_attr( $preview_pkg['html'] ); ?>"></iframe>
				</div>
				<pre id="tarbwy-email-preview-plain-wrap" hidden style="max-width:640px; margin:0 auto; background:#1C1917; color:#F5F5F4; padding:20px; border-radius:8px; text-align:right; direction:rtl; white-space:pre-wrap; font-family:monospace; font-size:13px; line-height:1.8;"><?php echo esc_html( $preview_pkg['plain_text'] ); ?></pre>
			</div>
		</section>

		<!-- 4: Email Delivery & Queue Log Table with Safe Retry -->
		<section class="tarbwy-admin-panel" style="margin-bottom:20px;">
			<h2>4. سجل إرسال البريد الإلكتروني وإعادة المحاولة الآمنة (Email Delivery Logs)</h2>
			<p>تتبع حالة كل رسالة صادرة (<code>sent</code>, <code>queued</code>, <code>failed</code>) مع إمكانية إعادة إرسال أي بريد متعثر دون إنشاء رموز QR مكررة.</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>#</th>
						<th>المستلم</th>
						<th>القالب</th>
						<th>عنوان الرسالة</th>
						<th>الحالة</th>
						<th>المحاولات</th>
						<th>التوقيت / الخطأ</th>
						<th>إجراء</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $email_logs ) ) : ?>
						<tr><td colspan="8">لا توجد سجلات بريد مرسلة بعد.</td></tr>
					<?php else : ?>
						<?php
						$mail_row_num = 0;
						foreach ( $email_logs as $ml ) :
							++$mail_row_num;
						?>
							<tr>
								<td><strong><?php echo (int) $mail_row_num; ?></strong></td>
								<td><strong><?php echo esc_html( $ml->full_name ? $ml->full_name : '-' ); ?></strong><br><code><?php echo esc_html( $ml->recipient_email ); ?></code></td>
								<td><code><?php echo esc_html( $ml->template_key ); ?></code></td>
								<td><?php echo esc_html( $ml->subject ); ?></td>
								<td><span class="tarbwy-status-badge tarbwy-status-<?php echo esc_attr( $ml->status ); ?>"><?php echo esc_html( $ml->status ); ?></span></td>
								<td><?php echo (int) $ml->attempts; ?></td>
								<td>
									<small><?php echo esc_html( $ml->sent_at ? $ml->sent_at : $ml->updated_at ); ?></small>
									<?php if ( ! empty( $ml->last_error ) ) : ?>
										<br><small style="color:#991B1B;"><?php echo esc_html( $ml->last_error ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="tarbwy_retry_email">
										<input type="hidden" name="log_id" value="<?php echo esc_attr( $ml->id ); ?>">
										<input type="hidden" name="return_page" value="tarbwy-em-emails">
										<?php wp_nonce_field( 'tarbwy_retry_email_nonce' ); ?>
										<button type="submit" class="button button-small">إعادة إرسال</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<!-- 5: Full 14-Template Editor & Restore Defaults -->
		<section class="tarbwy-admin-panel">
			<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
				<div>
					<h2 style="margin:0 0 4px 0;">5. إدارة وتحرير قوالب البريد الإلكتروني الـ 14 (Arabic RTL & English LTR)</h2>
					<p style="margin:0;">المتغيرات الديناميكية: <code>{guest_name}</code>, <code>{event_title}</code>, <code>{event_date}</code>, <code>{venue_name}</code>, <code>{reference_code}</code>, <code>{ticket_reference}</code>, <code>{ticket_url}</code>, <code>{vip_url}</code>, <code>{qr_code}</code>, <code>{tickets_count}</code>, <code>{previous_status_label}</code>, <code>{new_status_label}</code></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('هل أنت متأكد من استعادة جميع قوالب البريد إلى النصوص والتصميمات الافتراضية؟');">
					<input type="hidden" name="action" value="tarbwy_restore_email_templates">
					<input type="hidden" name="restore_key" value="all">
					<?php wp_nonce_field( 'tarbwy_restore_templates_nonce' ); ?>
					<button type="submit" class="button">استعادة جميع القوالب الافتراضية (Restore Defaults)</button>
				</form>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tarbwy_save_email_templates">
				<?php wp_nonce_field( 'tarbwy_save_templates_nonce' ); ?>
				<?php foreach ( $all_tpls as $tkey => $tpl ) : ?>
					<div style="border:1px solid #CBD5E1; border-right:4px solid <?php echo esc_attr( $tpl['accent_color'] ?? '#14532D' ); ?>; border-radius:6px; padding:18px; margin-bottom:18px; background:#fff;">
						<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
							<h3 style="margin:0; color:#14532D;"><?php echo esc_html( $tpl['title'] ); ?> (<code><?php echo esc_html( $tkey ); ?></code>)</h3>
							<div style="display:flex; gap:8px; align-items:center;">
								<label>اتجاه القالب:
									<select name="templates[<?php echo esc_attr( $tkey ); ?>][lang_dir]">
										<option value="rtl" <?php selected( $tpl['lang_dir'] ?? 'rtl', 'rtl' ); ?>>العربية (RTL)</option>
										<option value="ltr" <?php selected( $tpl['lang_dir'] ?? 'rtl', 'ltr' ); ?>>English (LTR)</option>
									</select>
								</label>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-emails&preview_tpl=' . rawurlencode( $tkey ) ) ); ?>" class="button button-small">معاينة حية</a>
							</div>
						</div>
						<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px; margin-bottom:12px;">
							<div>
								<label><strong>عنوان البريد (Subject Line):</strong></label><br>
								<input type="text" name="templates[<?php echo esc_attr( $tkey ); ?>][subject]" class="large-text" value="<?php echo esc_attr( $tpl['subject'] ?? '' ); ?>">
							</div>
							<div>
								<label><strong>العنوان الرئيسي (Heading):</strong></label><br>
								<input type="text" name="templates[<?php echo esc_attr( $tkey ); ?>][heading]" class="large-text" value="<?php echo esc_attr( $tpl['heading'] ?? '' ); ?>">
							</div>
							<div>
								<label><strong>شارة الترويسة (Header Badge):</strong></label><br>
								<input type="text" name="templates[<?php echo esc_attr( $tkey ); ?>][badge]" class="large-text" value="<?php echo esc_attr( $tpl['badge'] ?? '' ); ?>">
							</div>
							<div>
								<label><strong>نص زر الدعوة (CTA Label):</strong></label><br>
								<input type="text" name="templates[<?php echo esc_attr( $tkey ); ?>][cta_label]" class="large-text" value="<?php echo esc_attr( $tpl['cta_label'] ?? '' ); ?>">
							</div>
						</div>
						<div>
							<label><strong>محتوى الرسالة (Message Content):</strong></label><br>
							<textarea name="templates[<?php echo esc_attr( $tkey ); ?>][body]" rows="4" class="large-text"><?php echo esc_textarea( $tpl['body'] ?? '' ); ?></textarea>
						</div>
					</div>
				<?php endforeach; ?>
				<?php submit_button( 'حفظ جميع تعديلات قوالب البريد' ); ?>
			</form>
		</section>

	<?php elseif ( 'tarbwy-em-registrations' === $current_page && Roles::can_review_registrations() ) : ?>
		<!-- Registration Requests Review & Status Management Table -->
		<?php
		Installer::heal_orphaned_registrations();

		$status_tab = isset( $_GET['reg_status'] ) ? sanitize_key( wp_unslash( $_GET['reg_status'] ) ) : 'all';
		$reg_where  = $wpdb->prepare( 'WHERE r.event_id = %d', $eid );
		if ( in_array( $status_tab, array( 'pending', 'approved', 'waitlist', 'declined' ), true ) ) {
			$reg_where .= $wpdb->prepare( ' AND r.review_status = %s', $status_tab );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$regs = $wpdb->get_results(
			"SELECT r.*, g.full_name, g.email, g.phone, g.organization, g.job_title
			 FROM {$reg_table} r
			 LEFT JOIN {$guests_table} g ON r.primary_guest_id = g.id
			 {$reg_where}
			 ORDER BY r.id DESC LIMIT 150"
		);
		?>
		<section class="tarbwy-admin-panel">
			<h2>إدارة طلبات التسجيل وإصدار بطاقات QR الفردية (Registration Requests & Status Management)</h2>
			<p>عند النقر على <strong>«اعتماد وإصدار QR»</strong> يتم تأكيد الحضور وإصدار رمز QR فريد ومستقل لكل شخص معتمد (الضيف الأساسي + كل مرافق معتمد) وإرسالها جميعاً في رسالة التأكيد دون أي تكرار عند إعادة النقر.</p>

			<!-- Filter Tabs -->
			<div style="display:flex; gap:8px; flex-wrap:wrap; margin:14px 0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-registrations&reg_status=all' ) ); ?>" class="button <?php echo 'all' === $status_tab ? 'button-primary' : ''; ?>">الكل (<?php echo (int) $report_stats['public_submissions']; ?>)</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-registrations&reg_status=pending' ) ); ?>" class="button <?php echo 'pending' === $status_tab ? 'button-primary' : ''; ?>">قيد المراجعة (<?php echo (int) $report_stats['public_pending']; ?>)</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-registrations&reg_status=approved' ) ); ?>" class="button <?php echo 'approved' === $status_tab ? 'button-primary' : ''; ?>">المعتمدون (<?php echo (int) $report_stats['public_approved']; ?>)</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-registrations&reg_status=waitlist' ) ); ?>" class="button <?php echo 'waitlist' === $status_tab ? 'button-primary' : ''; ?>">قائمة الانتظار (<?php echo (int) $report_stats['public_waitlist']; ?>)</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-registrations&reg_status=declined' ) ); ?>" class="button <?php echo 'declined' === $status_tab ? 'button-primary' : ''; ?>">المرفوضون (<?php echo (int) $report_stats['public_rejected']; ?>)</a>
			</div>

			<table class="widefat striped" style="margin-top:14px;">
				<thead>
					<tr>
						<th style="width:46px;">#</th>
						<th>رقم المرجع والتاريخ</th>
						<th>مقدم الطلب الأساسي</th>
						<th>المؤسسة / الصفة</th>
						<th>الأفراد والمرافقون وبطاقات QR الصادرة</th>
						<th>الحالة الحالية</th>
						<th>الإجراءات وتغيير الحالة (Approve / Reject / Waitlist / Edit / Delete)</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $regs ) ) : ?>
						<tr><td colspan="7" style="text-align:center; padding:20px;">لا توجد طلبات تسجيل ضمن هذا التصنيف حالياً.</td></tr>
					<?php else : ?>
						<?php
						$reg_row_num = 0;
						foreach ( $regs as $r ) :
							++$reg_row_num;
							$rid     = (int) $r->id;
							$form_id = 'tarbwy-review-form-' . $rid;

							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$raw_r_guests = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT g.id, g.full_name, g.email, g.phone, g.organization, g.job_title, g.parent_guest_id, g.rsvp_status,
									        t.id AS ticket_id, t.ticket_reference, t.token_preview, t.status AS ticket_status
									 FROM {$guests_table} g
									 LEFT JOIN {$tkt_table} t ON t.guest_id = g.id AND t.status IN ('active', 'used')
									 WHERE g.registration_id = %d
									    OR (%d > 0 AND (g.id = %d OR g.parent_guest_id = %d))
									 ORDER BY CASE WHEN g.parent_guest_id IS NULL OR g.parent_guest_id = 0 THEN 0 ELSE 1 END ASC, g.id ASC, t.id DESC",
									$rid,
									(int) $r->primary_guest_id,
									(int) $r->primary_guest_id,
									(int) $r->primary_guest_id
								)
							);
							$r_guests = array();
							$seen_rg  = array();
							foreach ( (array) $raw_r_guests as $rg_item ) {
								$rg_id = (int) $rg_item->id;
								if ( isset( $seen_rg[ $rg_id ] ) ) {
									continue;
								}
								$seen_rg[ $rg_id ] = true;
								$r_guests[]        = $rg_item;
							}

							// Fallback if primary_guest_id was unlinked but guest rows exist for registration_id
							if ( empty( $r->full_name ) && ! empty( $r_guests ) ) {
								$r->full_name    = $r_guests[0]->full_name;
								$r->email        = $r_guests[0]->email;
								$r->phone        = $r_guests[0]->phone;
								$r->organization = $r_guests[0]->organization;
								$r->job_title    = $r_guests[0]->job_title;
							} elseif ( empty( $r->full_name ) && ! empty( $r->applicant_payload ) ) {
								$backup_data = json_decode( (string) $r->applicant_payload, true );
								if ( is_array( $backup_data ) ) {
									$r->full_name    = $backup_data['full_name'] ?? '';
									$r->email        = $backup_data['email'] ?? '';
									$r->phone        = $backup_data['phone'] ?? '';
									$r->organization = $backup_data['organization'] ?? '';
									$r->job_title    = $backup_data['job_title'] ?? '';
								}
							}

							$party_count = count( $r_guests );
							$active_qrs  = 0;
							foreach ( $r_guests as $rg_chk ) {
								if ( ! empty( $rg_chk->ticket_reference ) ) {
									++$active_qrs;
								}
							}
							?>
							<tr>
								<td><strong><?php echo (int) $reg_row_num; ?></strong></td>
								<td>
									<code><?php echo esc_html( $r->reference_code ); ?></code><br>
									<small><?php echo esc_html( $r->submitted_at ); ?></small>
								</td>
								<td>
									<?php if ( ! empty( $r->full_name ) ) : ?>
										<strong><?php echo esc_html( $r->full_name ); ?></strong><br>
										<small><code><?php echo esc_html( $r->email ); ?></code><?php echo ! empty( $r->phone ) ? ' · <span dir="ltr">' . esc_html( $r->phone ) . '</span>' : ''; ?></small>
									<?php else : ?>
										<span style="color:#B45309; font-weight:600; font-size:12px;">سجل سابق غير مكتمل — اضغط «تعديل البيانات» لإضافة بيانات مقدم الطلب</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( ( ! empty( $r->organization ) ? $r->organization : '-' ) . ' / ' . ( ! empty( $r->job_title ) ? $r->job_title : '-' ) ); ?></td>
								<td>
									<div style="font-size:12px; color:#14532D; font-weight:700; margin-bottom:6px;">
										إجمالي الأفراد: <?php echo (int) $party_count; ?> (بطاقات QR الفعالة: <?php echo (int) $active_qrs; ?> / <?php echo (int) $party_count; ?>)
									</div>
									<?php foreach ( $r_guests as $rg ) : ?>
										<?php
										$is_Checked = ( 'declined' !== $rg->rsvp_status );
										$raw_tok    = ! empty( $rg->token_preview ) ? $rg->token_preview : ( $rg->ticket_id ? get_transient( 'tarbwy_tkt_raw_' . $rg->ticket_id ) : '' );
										if ( empty( $raw_tok ) && ! empty( $rg->ticket_reference ) ) {
											$raw_tok = $rg->ticket_reference;
										}
										$tkt_link   = $raw_tok ? QRGenerator::get_ticket_url( $raw_tok ) : '';
										?>
										<label style="display:block; margin-bottom:5px; background:#F8FAFC; padding:5px 8px; border-radius:4px; border:1px solid #E2E8F0;">
											<input type="checkbox" form="<?php echo esc_attr( $form_id ); ?>" name="approved_guest_ids[]" value="<?php echo esc_attr( $rg->id ); ?>" <?php checked( $is_Checked ); ?>>
											<strong><?php echo esc_html( $rg->full_name ); ?></strong>
											<small>(<?php echo empty( $rg->parent_guest_id ) ? 'ضيف أساسي' : 'مرافق'; ?> — <?php echo esc_html( $rg->rsvp_status ); ?>)</small>
											<?php if ( ! empty( $rg->ticket_reference ) ) : ?>
												<br><small style="color:#14532D;">QR: <code><?php echo esc_html( $rg->ticket_reference ); ?></code>
												<?php if ( $tkt_link ) : ?>
													— <a href="<?php echo esc_url( $tkt_link ); ?>" target="_blank">عرض البطاقة</a>
												<?php endif; ?>
												</small>
											<?php endif; ?>
										</label>
									<?php endforeach; ?>
								</td>
								<td>
									<span class="tarbwy-status-badge tarbwy-status-<?php echo esc_attr( $r->review_status ); ?>">
										<?php echo esc_html( $r->review_status ); ?>
									</span>
									<?php if ( ! empty( $r->previous_status ) ) : ?>
										<br><small style="color:#64748B;">السابقة: <?php echo esc_html( $r->previous_status ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<!-- Self-contained Review & Status Form inside this single TD (fixes HTML table form bug) -->
									<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
										<input type="hidden" name="action" value="tarbwy_review_registration">
										<input type="hidden" name="registration_id" value="<?php echo esc_attr( $rid ); ?>">
										<?php wp_nonce_field( 'tarbwy_review_reg_nonce' ); ?>
										<div class="tarbwy-action-bar" style="margin-bottom:6px;">
											<button type="submit" name="decision" value="approved" class="button button-primary">اعتماد وإصدار QR (<?php echo (int) $party_count; ?>)</button>
											<button type="submit" name="decision" value="waitlist" class="button">قائمة انتظار</button>
											<button type="submit" name="decision" value="declined" class="button">رفض (Reject)</button>
											<button type="submit" name="decision" value="pending" class="button">قيد المراجعة</button>
										</div>
									</form>

									<div class="tarbwy-action-bar">
										<button type="button" class="button button-small" data-toggle-target="edit-reg-row-<?php echo esc_attr( $rid ); ?>">تعديل البيانات (Edit)</button>

										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('هل أنت متأكد من حذف هذا التسجيل وكافة المرافقين وإبطال بطاقات QR المرتبطة به نهائياً؟');" style="display:inline;">
											<input type="hidden" name="action" value="tarbwy_delete_registration">
											<input type="hidden" name="registration_id" value="<?php echo esc_attr( $rid ); ?>">
											<?php wp_nonce_field( 'tarbwy_delete_reg_nonce' ); ?>
											<button type="submit" class="button button-small" style="color:#991B1B; border-color:#FCA5A5;">حذف (Delete)</button>
										</form>
									</div>
								</td>
							</tr>
							<!-- Expandable Inline Edit Row -->
							<tr id="edit-reg-row-<?php echo esc_attr( $rid ); ?>" hidden style="background:#FAF8F5;">
								<td colspan="7">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="padding:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
										<input type="hidden" name="action" value="tarbwy_edit_registration">
										<input type="hidden" name="registration_id" value="<?php echo esc_attr( $rid ); ?>">
										<?php wp_nonce_field( 'tarbwy_edit_reg_nonce' ); ?>
										<div><label>الاسم الكامل:</label><br><input type="text" name="full_name" value="<?php echo esc_attr( $r->full_name ); ?>" required></div>
										<div><label>البريد الإلكتروني:</label><br><input type="email" name="email" dir="ltr" value="<?php echo esc_attr( $r->email ); ?>" required></div>
										<div><label>الهاتف:</label><br><input type="text" name="phone" dir="ltr" value="<?php echo esc_attr( $r->phone ); ?>"></div>
										<div><label>المؤسسة:</label><br><input type="text" name="organization" value="<?php echo esc_attr( $r->organization ); ?>"></div>
										<div><label>المسمى الوظيفي:</label><br><input type="text" name="job_title" value="<?php echo esc_attr( $r->job_title ); ?>"></div>
										<div><label>ملاحظة داخلية:</label><br><input type="text" name="review_note" value="<?php echo esc_attr( $r->review_note ); ?>"></div>
										<div><button type="submit" class="button button-primary">حفظ التعديلات</button></div>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

	<?php elseif ( 'tarbwy-em-vip' === $current_page && Roles::can_manage_invitations() ) : ?>
		<!-- VIP Invitations Management (Full Lifecycle: Create, View, Edit, Resend without duplicates, Cancel/Revoke, Delete) -->
		<?php
		// Query from tarbwy_guests (LEFT JOIN tarbwy_invitations) so every VIP guest always appears immediately!
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_vips = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.id AS guest_id, g.full_name, g.email, g.phone, g.organization, g.job_title, g.rsvp_status, g.created_at AS guest_created_at,
				        i.id AS invitation_id, i.status AS inv_status, i.token_preview, i.max_companions_override, i.sent_at, i.created_at AS inv_created_at,
				        t.id AS ticket_id, t.ticket_reference, t.token_preview AS ticket_token_preview, t.status AS ticket_status
				 FROM {$guests_table} g
				 LEFT JOIN {$inv_table} i ON i.guest_id = g.id AND i.event_id = g.event_id
				 LEFT JOIN {$tkt_table} t ON t.guest_id = g.id AND t.status IN ('active', 'used')
				 WHERE g.event_id = %d AND g.guest_type = 'vip'
				 ORDER BY g.id DESC, i.id DESC, t.id DESC LIMIT 200",
				$eid
			)
		);
		$vips      = array();
		$seen_vips = array();
		foreach ( (array) $raw_vips as $v_item ) {
			$vid = (int) $v_item->guest_id;
			if ( isset( $seen_vips[ $vid ] ) ) {
				continue;
			}
			$seen_vips[ $vid ] = true;
			$vips[]            = $v_item;
		}
		?>
		<section class="tarbwy-admin-panel" style="margin-bottom:20px;">
			<h2>إضافة مدعو VIP جديد وإرسال رابط دعوة مشفر (/launch/vip)</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tarbwy_create_vip_guest">
				<?php wp_nonce_field( 'tarbwy_create_vip_nonce' ); ?>
				<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
					<div>
						<label><strong>الاسم الكامل واللقب *:</strong></label><br>
						<input type="text" name="full_name" placeholder="سعادة د. ..." required class="regular-text">
					</div>
					<div>
						<label><strong>البريد الإلكتروني *:</strong></label><br>
						<input type="email" name="email" placeholder="vip@domain.org" required class="regular-text" dir="ltr">
					</div>
					<div>
						<label><strong>رقم الهاتف:</strong></label><br>
						<input type="text" name="phone" placeholder="+9665..." class="regular-text" dir="ltr" style="width:160px;">
					</div>
					<div>
						<label><strong>الجهة / المؤسسة:</strong></label><br>
						<input type="text" name="organization" placeholder="الجهة" class="regular-text" style="width:170px;">
					</div>
					<div>
						<label><strong>المنصب / الصفة:</strong></label><br>
						<input type="text" name="job_title" placeholder="المنصب" class="regular-text" style="width:150px;">
					</div>
					<div>
						<label><strong>حد المرافقين:</strong></label><br>
						<input type="number" name="max_companions" value="2" min="0" max="5" style="width:70px;">
					</div>
					<div style="padding-bottom:4px;">
						<label><input type="checkbox" name="pre_confirm_vip" value="1"> تأكيد الحضور وإصدار QR فوراً</label>
					</div>
					<div>
						<button type="submit" class="button button-primary">حفظ دعوة VIP وإرسال البريد</button>
					</div>
				</div>
			</form>
		</section>

		<section class="tarbwy-admin-panel">
			<h2>سجل دعوات كبار الشخصيات (VIP Invitations Dashboard — <?php echo (int) count( $vips ); ?>)</h2>
			<table class="widefat striped" style="margin-top:12px;">
				<thead>
					<tr>
						<th style="width:46px;">#</th>
						<th>معرف الدعوة</th>
						<th>اسم المدعو (Recipient)</th>
						<th>البريد والهاتف</th>
						<th>الجهة / حد المرافقين</th>
						<th>حالة الدعوة وRSVP</th>
						<th>حالة رمز QR</th>
						<th>حالة تسليم البريد</th>
						<th>تاريخ الإنشاء / الإرسال</th>
						<th>الإجراءات (View / Edit / Resend / Cancel / Delete)</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $vips ) ) : ?>
						<tr><td colspan="10" style="text-align:center; padding:20px;">لا توجد دعوات VIP مسجلة بعد.</td></tr>
					<?php else : ?>
						<?php
						$vip_row_num = 0;
						foreach ( $vips as $v ) :
							++$vip_row_num;
							$vgid         = (int) $v->guest_id;
							$vinv_id      = (int) $v->invitation_id;
							$raw_vtok     = ! empty( $v->token_preview ) ? $v->token_preview : ( $vinv_id ? get_transient( 'tarbwy_vip_raw_' . $vinv_id ) : '' );
							$personal_vip = $raw_vtok ? Security::format_vip_personal_code( $raw_vtok ) : sprintf( 'VIP-%04d', $vinv_id ? $vinv_id : $vgid );
							$vip_link     = $raw_vtok ? home_url( '/launch/vip/?token=' . rawurlencode( $personal_vip ) ) : home_url( '/launch/vip' );
							$raw_ttok     = ! empty( $v->ticket_token_preview ) ? $v->ticket_token_preview : ( $v->ticket_id ? get_transient( 'tarbwy_tkt_raw_' . $v->ticket_id ) : '' );
							if ( empty( $raw_ttok ) && ! empty( $v->ticket_reference ) ) {
								$raw_ttok = $v->ticket_reference;
							}
							$tkt_link     = $raw_ttok ? QRGenerator::get_ticket_url( $raw_ttok ) : '';

							// Latest email log for this VIP guest
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$last_mail = $wpdb->get_row(
								$wpdb->prepare(
									"SELECT id, status, last_error, sent_at FROM {$mail_table} WHERE event_id = %d AND guest_id = %d ORDER BY id DESC LIMIT 1",
									$eid,
									$vgid
								)
							);
							?>
							<tr>
								<td><strong><?php echo (int) $vip_row_num; ?></strong></td>
								<td>
									<code dir="ltr" style="font-weight:bold; font-size:13px; color:#14532D; background:#ECFDF5; padding:3px 7px; border-radius:4px; border:1px solid #A7F3D0; display:inline-block; margin-bottom:4px;"><?php echo esc_html( $personal_vip ); ?></code><br>
									<button type="button" class="button button-small" onclick="if(navigator.clipboard){navigator.clipboard.writeText('<?php echo esc_js( $personal_vip ); ?>');this.innerText='✓ تم النسخ';}" title="نسخ رمز الدعوة الشخصي">نسخ الرمز (Copy Code)</button>
								</td>
								<td><strong><?php echo esc_html( $v->full_name ); ?></strong></td>
								<td>
									<code><?php echo esc_html( $v->email ); ?></code><br>
									<small dir="ltr"><?php echo esc_html( $v->phone ? $v->phone : '-' ); ?></small>
								</td>
								<td>
									<?php echo esc_html( ( $v->organization ? $v->organization : '-' ) . ' / ' . ( $v->job_title ? $v->job_title : '-' ) ); ?><br>
									<small>حد المرافقين: <strong><?php echo (int) ( null !== $v->max_companions_override ? $v->max_companions_override : 2 ); ?></strong></small>
								</td>
								<td>
									<span class="tarbwy-status-badge tarbwy-status-<?php echo esc_attr( $v->inv_status ? $v->inv_status : 'active' ); ?>">
										الدعوة: <?php echo esc_html( $v->inv_status ? $v->inv_status : 'active' ); ?>
									</span><br>
									<small>RSVP: <strong><?php echo esc_html( $v->rsvp_status ); ?></strong></small>
								</td>
								<td>
									<?php if ( ! empty( $v->ticket_reference ) ) : ?>
										<span class="tarbwy-status-badge tarbwy-status-approved"><?php echo esc_html( $v->ticket_reference ); ?></span>
										<?php if ( $tkt_link ) : ?>
											<br><a href="<?php echo esc_url( $tkt_link ); ?>" target="_blank"><small>عرض بطاقة QR</small></a>
										<?php endif; ?>
									<?php else : ?>
										<small style="color:#64748B;">يُصدر عند تأكيد الحضور</small>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $last_mail ) : ?>
										<span class="tarbwy-status-badge tarbwy-status-<?php echo esc_attr( $last_mail->status ); ?>">
											<?php echo esc_html( $last_mail->status ); ?>
										</span>
										<?php if ( ! empty( $last_mail->last_error ) ) : ?>
											<br><small style="color:#991B1B;"><?php echo esc_html( $last_mail->last_error ); ?></small>
										<?php endif; ?>
									<?php else : ?>
										<small>لم يُرسل بعد</small>
									<?php endif; ?>
								</td>
								<td>
									<small>الإنشاء: <?php echo esc_html( $v->inv_created_at ? $v->inv_created_at : $v->guest_created_at ); ?></small><br>
									<small>الإرسال: <?php echo esc_html( $v->sent_at ? $v->sent_at : '-' ); ?></small>
								</td>
								<td>
									<div class="tarbwy-action-bar">
										<a href="<?php echo esc_url( $vip_link ); ?>" target="_blank" class="button button-small">عرض (View)</a>
										<button type="button" class="button button-small" data-toggle-target="edit-vip-row-<?php echo esc_attr( $vgid ); ?>">تعديل (Edit)</button>

										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
											<input type="hidden" name="action" value="tarbwy_send_vip_invitation">
											<input type="hidden" name="invitation_id" value="<?php echo esc_attr( $vinv_id ); ?>">
											<input type="hidden" name="guest_id" value="<?php echo esc_attr( $vgid ); ?>">
											<?php wp_nonce_field( 'tarbwy_vip_action_nonce' ); ?>
											<button type="submit" class="button button-primary button-small">إعادة إرسال (Resend)</button>
										</form>

										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
											<input type="hidden" name="action" value="tarbwy_revoke_vip_invitation">
											<input type="hidden" name="invitation_id" value="<?php echo esc_attr( $vinv_id ); ?>">
											<input type="hidden" name="guest_id" value="<?php echo esc_attr( $vgid ); ?>">
											<?php wp_nonce_field( 'tarbwy_vip_action_nonce' ); ?>
											<button type="submit" class="button button-small">إلغاء (Cancel)</button>
										</form>

										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('هل أنت متأكد من حذف دعوة VIP هذه نهائياً؟');" style="display:inline;">
											<input type="hidden" name="action" value="tarbwy_delete_vip_invitation">
											<input type="hidden" name="invitation_id" value="<?php echo esc_attr( $vinv_id ); ?>">
											<input type="hidden" name="guest_id" value="<?php echo esc_attr( $vgid ); ?>">
											<?php wp_nonce_field( 'tarbwy_vip_action_nonce' ); ?>
											<button type="submit" class="button button-small" style="color:#991B1B; border-color:#FCA5A5;">حذف (Delete)</button>
										</form>
									</div>
								</td>
							</tr>
							<!-- Inline VIP Edit Row -->
							<tr id="edit-vip-row-<?php echo esc_attr( $vgid ); ?>" hidden style="background:#FAF8F5;">
								<td colspan="10">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="padding:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
										<input type="hidden" name="action" value="tarbwy_edit_vip_invitation">
										<input type="hidden" name="guest_id" value="<?php echo esc_attr( $vgid ); ?>">
										<input type="hidden" name="invitation_id" value="<?php echo esc_attr( $vinv_id ); ?>">
										<?php wp_nonce_field( 'tarbwy_edit_vip_nonce' ); ?>
										<div><label>الاسم الكامل:</label><br><input type="text" name="full_name" value="<?php echo esc_attr( $v->full_name ); ?>" required></div>
										<div><label>البريد الإلكتروني:</label><br><input type="email" name="email" dir="ltr" value="<?php echo esc_attr( $v->email ); ?>" required></div>
										<div><label>الهاتف:</label><br><input type="text" name="phone" dir="ltr" value="<?php echo esc_attr( $v->phone ); ?>"></div>
										<div><label>الجهة:</label><br><input type="text" name="organization" value="<?php echo esc_attr( $v->organization ); ?>"></div>
										<div><label>المنصب:</label><br><input type="text" name="job_title" value="<?php echo esc_attr( $v->job_title ); ?>"></div>
										<div><label>حد المرافقين:</label><br><input type="number" name="max_companions" value="<?php echo esc_attr( (int) ( null !== $v->max_companions_override ? $v->max_companions_override : 2 ) ); ?>" min="0" max="5" style="width:65px;"></div>
										<div>
											<label>حالة RSVP:</label><br>
											<select name="rsvp_status">
												<option value="pending_response" <?php selected( $v->rsvp_status, 'pending_response' ); ?>>بانتظار الرد (Pending)</option>
												<option value="confirmed" <?php selected( $v->rsvp_status, 'confirmed' ); ?>>مؤكد + إصدار QR (Confirmed)</option>
												<option value="declined" <?php selected( $v->rsvp_status, 'declined' ); ?>>معتذر + إبطال QR (Declined)</option>
											</select>
										</div>
										<div><button type="submit" class="button button-primary">حفظ تعديلات VIP</button></div>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

	<?php elseif ( 'tarbwy-em-tickets' === $current_page && Roles::can_manage_guests() ) : ?>
		<?php
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tickets = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, g.full_name, g.guest_type, g.rsvp_status, g.checkin_status
				 FROM {$tkt_table} t
				 INNER JOIN {$guests_table} g ON t.guest_id = g.id
				 WHERE t.event_id = %d
				 ORDER BY t.id DESC LIMIT 200",
				$eid
			)
		);
		?>
		<section class="tarbwy-admin-panel">
			<h2>التذاكر الفردية ورموز QR (Tickets & QR)</h2>
			<p>لكل مشارك مؤكد ومرافق رمز QR مستقل ومحمي ببصمة SHA-256. يمكنك عرض البطاقة أو إبطال التذكرة السابقة وإصدار رمز QR جديد في أي وقت.</p>
			<table class="widefat striped" style="margin-top:12px;">
				<thead>
					<tr>
						<th style="width:46px;">#</th>
						<th>رقم التذكرة</th>
						<th>حامل البطاقة</th>
						<th>الفئة</th>
						<th>حالة التذكرة</th>
						<th>حالة الحضور</th>
						<th>إجراءات البطاقة</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $tickets ) ) : ?>
						<tr><td colspan="7">لم يتم إصدار تذاكر بعد. تُصدر التذاكر تلقائياً فور اعتماد الطلب أو تأكيد حضور VIP.</td></tr>
					<?php else : ?>
						<?php
						$tkt_row_num = 0;
						foreach ( $tickets as $tk ) :
							++$tkt_row_num;
							$raw_tok  = ! empty( $tk->token_preview ) ? $tk->token_preview : get_transient( 'tarbwy_tkt_raw_' . $tk->id );
							if ( empty( $raw_tok ) && ! empty( $tk->ticket_reference ) ) {
								$raw_tok = $tk->ticket_reference;
							}
							$tkt_link = $raw_tok ? QRGenerator::get_ticket_url( $raw_tok ) : '';
							?>
							<tr>
								<td><strong><?php echo (int) $tkt_row_num; ?></strong></td>
								<td><code><?php echo esc_html( $tk->ticket_reference ); ?></code></td>
								<td><strong><?php echo esc_html( $tk->full_name ); ?></strong></td>
								<td><code><?php echo esc_html( $tk->guest_type ); ?></code></td>
								<td><span class="tarbwy-status-badge tarbwy-status-<?php echo esc_attr( $tk->status ); ?>"><?php echo esc_html( $tk->status ); ?></span></td>
								<td><?php echo esc_html( $tk->checkin_status ); ?></td>
								<td class="tarbwy-action-bar">
									<?php if ( $tkt_link && 'revoked' !== $tk->status ) : ?>
										<a href="<?php echo esc_url( $tkt_link ); ?>" target="_blank" class="button button-small">فتح البطاقة وQR</a>
									<?php endif; ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<input type="hidden" name="action" value="tarbwy_reissue_ticket">
										<input type="hidden" name="guest_id" value="<?php echo esc_attr( $tk->guest_id ); ?>">
										<?php wp_nonce_field( 'tarbwy_reissue_ticket_nonce' ); ?>
										<button type="submit" class="button button-small">إبطال وإعادة إصدار QR جديد</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

	<?php else : ?>
		<!-- Guest List / Dashboard Overview -->
		<?php
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_guests_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.*, t.id AS ticket_id, t.ticket_reference, t.token_preview, p.full_name AS parent_name
				 FROM {$guests_table} g
				 LEFT JOIN {$tkt_table} t ON g.id = t.guest_id AND t.status IN ('active', 'used')
				 LEFT JOIN {$guests_table} p ON g.parent_guest_id = p.id
				 WHERE g.event_id = %d
				 ORDER BY g.id DESC, t.id DESC LIMIT 250",
				$eid
			)
		);
		$guests_rows    = array();
		$seen_guest_ids = array();
		foreach ( (array) $raw_guests_rows as $gr_item ) {
			$gid = (int) $gr_item->id;
			if ( isset( $seen_guest_ids[ $gid ] ) ) {
				continue;
			}
			$seen_guest_ids[ $gid ] = true;
			$guests_rows[]          = $gr_item;
		}
		?>
		<section class="tarbwy-admin-panel" id="tarbwy-admin-interactive-root" data-page="<?php echo esc_attr( $current_page ); ?>">
			<h2>قائمة الضيوف والمرافقين المسجلين (Guest List — <?php echo (int) count( $guests_rows ); ?>)</h2>
			<p>سجل شامل للضيوف الأساسيين وكبار الشخصيات والمرافقين مع حالة التأكيد (RSVP) ورقم التذكرة الفردية وحالة الدخول يوم الحدث.</p>
			<table class="widefat striped" style="margin-top:12px;">
				<thead>
					<tr>
						<th style="width:46px;">#</th>
						<th>الاسم الكامل</th>
						<th>الفئة / التبعية</th>
						<th>المؤسسة / الصفة</th>
						<th>حالة الحضور (RSVP)</th>
						<th>التذكرة النشطة وQR</th>
						<th>تسجيل الدخول (Check-in)</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $guests_rows ) ) : ?>
						<tr><td colspan="7">لا توجد سجلات ضيوف حالياً.</td></tr>
					<?php else : ?>
						<?php
						$guest_row_num = 0;
						foreach ( $guests_rows as $gr ) :
							++$guest_row_num;
							$raw_tok  = ! empty( $gr->token_preview ) ? $gr->token_preview : ( $gr->ticket_id ? get_transient( 'tarbwy_tkt_raw_' . $gr->ticket_id ) : '' );
							if ( empty( $raw_tok ) && ! empty( $gr->ticket_reference ) ) {
								$raw_tok = $gr->ticket_reference;
							}
							$tkt_link = $raw_tok ? QRGenerator::get_ticket_url( $raw_tok ) : '';
							?>
							<tr>
								<td><strong><?php echo (int) $guest_row_num; ?></strong></td>
								<td>
									<strong><?php echo esc_html( $gr->full_name ); ?></strong><br>
									<small><?php echo esc_html( $gr->email ? $gr->email : 'مرتبط ببريد الضيف الأساسي' ); ?></small>
								</td>
								<td>
									<code><?php echo esc_html( $gr->guest_type ); ?></code>
									<?php if ( ! empty( $gr->parent_name ) ) : ?>
										<br><small>مرافق لـ: <?php echo esc_html( $gr->parent_name ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( ( $gr->organization ? $gr->organization : '-' ) . ' / ' . ( $gr->job_title ? $gr->job_title : '-' ) ); ?></td>
								<td><span class="tarbwy-status-badge tarbwy-status-<?php echo esc_attr( $gr->rsvp_status ); ?>"><?php echo esc_html( $gr->rsvp_status ); ?></span></td>
								<td>
									<code><?php echo esc_html( $gr->ticket_reference ? $gr->ticket_reference : '-' ); ?></code>
									<?php if ( $tkt_link ) : ?>
										<br><a href="<?php echo esc_url( $tkt_link ); ?>" target="_blank"><small>عرض البطاقة</small></a>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'checked_in' === $gr->checkin_status ) : ?>
										<span style="color:#15803d; font-weight:700;">تم الدخول (<?php echo esc_html( $gr->checked_in_at ); ?>)</span>
									<?php else : ?>
										<span style="color:#64748b;">لم يسجل الدخول بعد</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</div>
