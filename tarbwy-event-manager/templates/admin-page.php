<?php
/**
 * WordPress Admin Dashboard Template (RTL Arabic)
 *
 * Covers all 9 administrative modules:
 * - Dashboard Overview
 * - Guest List (Paginated, Filtered)
 * - Registration Requests (Approve / Waitlist / Decline + Partial Companion selection)
 * - VIP Invitations (CSV Import with Preview & Dry-Run, Send, Revoke, Reissue)
 * - Tickets & QR (Reissue, View)
 * - Check-in Launchpad
 * - Reports & UTF-8 BOM CSV Export
 * - Event Settings
 * - Email Templates & Queue Logs
 */

namespace Tarbwy\EventManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'tarbwy-em-dashboard';
$event        = RestAPI::get_active_event();
$eid          = $event ? (int) $event->id : 1;
$settings     = json_decode( (string) ( $event->settings_json ?? '{}' ), true );
if ( ! is_array( $settings ) ) {
	$settings = array();
}

$guests_table = $wpdb->prefix . 'tarbwy_guests';
$reg_table    = $wpdb->prefix . 'tarbwy_registrations';
$inv_table    = $wpdb->prefix . 'tarbwy_invitations';
$tkt_table    = $wpdb->prefix . 'tarbwy_tickets';
$mail_table   = $wpdb->prefix . 'tarbwy_email_logs';

// Summary metrics
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$metrics = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT
			COUNT(id) AS total_guests,
			SUM(CASE WHEN rsvp_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
			SUM(CASE WHEN checkin_status = 'checked_in' THEN 1 ELSE 0 END) AS checked_in,
			SUM(CASE WHEN guest_type = 'vip' THEN 1 ELSE 0 END) AS vip_count,
			SUM(CASE WHEN guest_type = 'companion' THEN 1 ELSE 0 END) AS companions_count
		 FROM {$guests_table} WHERE event_id = %d",
		$eid
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$reg_metrics = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT
			COUNT(id) AS total_reqs,
			SUM(CASE WHEN review_status = 'pending' THEN 1 ELSE 0 END) AS pending,
			SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) AS approved,
			SUM(CASE WHEN review_status = 'waitlist' THEN 1 ELSE 0 END) AS waitlist,
			SUM(CASE WHEN review_status = 'declined' THEN 1 ELSE 0 END) AS declined
		 FROM {$reg_table} WHERE event_id = %d",
		$eid
	)
);
?>
<div class="wrap tarbwy-admin-wrap" dir="rtl">
	<header class="tarbwy-admin-header">
		<div>
			<h1>إدارة فعاليات تربوي — Tarbwy Event Manager</h1>
			<p class="tarbwy-admin-subtitle"><?php echo esc_html( $event ? $event->title : '' ); ?> · السعة المعتمدة: <?php echo esc_html( (int) ( $event->capacity_total ?? 500 ) ); ?> شخص (شاملاً المرافقين)</p>
		</div>
		<div class="tarbwy-admin-quicklinks">
			<a href="<?php echo esc_url( home_url( '/launch' ) ); ?>" target="_blank" class="button">صفحة الحدث (/launch)</a>
			<a href="<?php echo esc_url( home_url( '/launch/checkin' ) ); ?>" target="_blank" class="button button-primary">بوابة الدخول (/launch/checkin)</a>
		</div>
	</header>

	<!-- Top Navigation Tabs -->
	<nav class="nav-tab-wrapper tarbwy-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-dashboard' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-dashboard' === $current_page ? 'nav-tab-active' : ''; ?>">لوحة المؤشرات</a>
		<?php if ( Roles::can_review_registrations() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-registrations' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-registrations' === $current_page ? 'nav-tab-active' : ''; ?>">طلبات التسجيل (<?php echo (int) ( $reg_metrics->pending ?? 0 ); ?>)</a>
		<?php endif; ?>
		<?php if ( Roles::can_manage_guests() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-guests' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-guests' === $current_page ? 'nav-tab-active' : ''; ?>">قائمة الضيوف</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-tickets' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-tickets' === $current_page ? 'nav-tab-active' : ''; ?>">التذاكر وQR</a>
		<?php endif; ?>
		<?php if ( Roles::can_manage_invitations() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-vip' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-vip' === $current_page ? 'nav-tab-active' : ''; ?>">دعوات VIP</a>
		<?php endif; ?>
		<?php if ( Roles::can_export() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-reports' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-reports' === $current_page ? 'nav-tab-active' : ''; ?>">التقارير والتصدير</a>
		<?php endif; ?>
		<?php if ( Roles::can_manage_events() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-settings' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-settings' === $current_page ? 'nav-tab-active' : ''; ?>">إعدادات الحدث</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tarbwy-em-emails' ) ); ?>" class="nav-tab <?php echo 'tarbwy-em-emails' === $current_page ? 'nav-tab-active' : ''; ?>">القوالب وسجل البريد</a>
		<?php endif; ?>
	</nav>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>تم حفظ التغييرات بنجاح.</p></div>
	<?php endif; ?>

	<!-- KPI Summary Grid -->
	<section class="tarbwy-kpi-grid">
		<div class="tarbwy-kpi-box">
			<span>إجمالي الطلبات</span>
			<strong><?php echo (int) ( $reg_metrics->total_reqs ?? 0 ); ?></strong>
			<small>قيد المراجعة: <?php echo (int) ( $reg_metrics->pending ?? 0 ); ?></small>
		</div>
		<div class="tarbwy-kpi-box">
			<span>الطلبات المعتمدة / الانتظار</span>
			<strong><?php echo (int) ( $reg_metrics->approved ?? 0 ); ?></strong>
			<small>انتظار: <?php echo (int) ( $reg_metrics->waitlist ?? 0 ); ?> · مرفوض: <?php echo (int) ( $reg_metrics->declined ?? 0 ); ?></small>
		</div>
		<div class="tarbwy-kpi-box">
			<span>كبار الشخصيات (VIP)</span>
			<strong><?php echo (int) ( $metrics->vip_count ?? 0 ); ?></strong>
			<small>المرافقون المسجلون: <?php echo (int) ( $metrics->companions_count ?? 0 ); ?></small>
		</div>
		<div class="tarbwy-kpi-box">
			<span>إجمالي المؤكدين (Confirmed)</span>
			<strong><?php echo (int) ( $metrics->confirmed ?? 0 ); ?> / <?php echo (int) ( $event->capacity_total ?? 500 ); ?></strong>
			<small>شاملاً الضيوف الأساسيين والمرافقين</small>
		</div>
		<div class="tarbwy-kpi-box">
			<span>الحاضرون فعلياً (Checked-in)</span>
			<strong style="color:#14532D;"><?php echo (int) ( $metrics->checked_in ?? 0 ); ?></strong>
			<small>المتبقي للدخول: <?php echo max( 0, (int) ( $metrics->confirmed ?? 0 ) - (int) ( $metrics->checked_in ?? 0 ) ); ?></small>
		</div>
	</section>

	<?php if ( 'tarbwy-em-settings' === $current_page && Roles::can_manage_events() ) : ?>
		<!-- Event Settings Form -->
		<section class="tarbwy-admin-panel">
			<h2>إعدادات الحدث ومحتوى صفحة الهبوط (/launch)</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tarbwy_save_settings">
				<?php wp_nonce_field( 'tarbwy_save_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="title">عنوان الحفل</label></th>
						<td><input type="text" id="title" name="title" class="large-text" value="<?php echo esc_attr( $event->title ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="subtitle">العبارة التعريفية (Hero Subtitle)</label></th>
						<td><input type="text" id="subtitle" name="subtitle" class="large-text" value="<?php echo esc_attr( $settings['subtitle'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="date_display">عرض التاريخ والوقت</label></th>
						<td><input type="text" id="date_display" name="date_display" class="regular-text" value="<?php echo esc_attr( $settings['date_display'] ?? 'سيُعلن لاحقاً' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="city_display">المدينة</label></th>
						<td><input type="text" id="city_display" name="city_display" class="regular-text" value="<?php echo esc_attr( $settings['city_display'] ?? 'سيُعلن لاحقاً' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="venue_name">اسم المكان / القاعة</label></th>
						<td><input type="text" id="venue_name" name="venue_name" class="regular-text" value="<?php echo esc_attr( $event->venue_name ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="venue_address">العنوان التفصيلي ومعلومات الوصول</label></th>
						<td><textarea id="venue_address" name="venue_address" rows="3" class="large-text"><?php echo esc_textarea( $event->venue_address ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="capacity_total">الطاقة الاستيعابية الكلية (شاملة المرافقين)</label></th>
						<td><input type="number" id="capacity_total" name="capacity_total" value="<?php echo esc_attr( (int) ( $event->capacity_total ?? 500 ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="max_companions_public">الحد الأقصى للمرافقين (تسجيل عام / VIP)</label></th>
						<td>
							عام: <input type="number" id="max_companions_public" name="max_companions_public" style="width:80px;" value="<?php echo esc_attr( (int) ( $event->max_companions_public ?? 2 ) ); ?>">
							VIP: <input type="number" id="max_companions_vip" name="max_companions_vip" style="width:80px;" value="<?php echo esc_attr( (int) ( $event->max_companions_vip ?? 3 ) ); ?>">
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
						<th><label for="about_event">عن حفل الإشهار</label></th>
						<td><textarea id="about_event" name="about_event" rows="4" class="large-text"><?php echo esc_textarea( $settings['about_event'] ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="about_tarbwy">نبذة مؤسسية عن تربوي</label></th>
						<td><textarea id="about_tarbwy" name="about_tarbwy" rows="4" class="large-text"><?php echo esc_textarea( $settings['about_tarbwy'] ?? '' ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( 'حفظ إعدادات الحدث' ); ?>
			</form>
		</section>

	<?php elseif ( 'tarbwy-em-reports' === $current_page && Roles::can_export() ) : ?>
		<!-- Reports & UTF-8 BOM CSV Export -->
		<section class="tarbwy-admin-panel">
			<h2>التقارير وتصدير البيانات (UTF-8 BOM CSV متوافق مع Excel العربي)</h2>
			<p>يتم تضمين علامة UTF-8 BOM في بداية الملف لضمان فتح الأسماء العربية بشكل سليم في برنامج Microsoft Excel، مع تطهير الخلايا تلقائياً ضد ثغرات CSV Formula Injection (<code>=</code>, <code>+</code>, <code>-</code>, <code>@</code>).</p>
			<div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:16px;">
				<?php
				$exports = array(
					'all'        => 'تصدير جميع الضيوف والمرافقين',
					'confirmed'  => 'تصدير المؤكدين فقط (Confirmed)',
					'checked_in' => 'تصدير الحاضرين فعلياً (Checked-in)',
					'no_show'    => 'تصدير المتغيبين (No-Show)',
					'vip'        => 'تصدير قائمة كبار الشخصيات (VIP)',
					'companions' => 'تصدير قائمة المرافقين',
				);
				foreach ( $exports as $key => $label ) :
					$url = wp_nonce_url(
						admin_url( 'admin-post.php?action=tarbwy_export_csv&event_id=' . $eid . '&filter_type=' . $key ),
						'tarbwy_export_csv_nonce'
					);
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
		</section>

	<?php elseif ( 'tarbwy-em-emails' === $current_page && Roles::can_manage_events() ) : ?>
		<!-- Email Templates & Logs -->
		<section class="tarbwy-admin-panel">
			<h2>تحرير قوالب البريد الإلكتروني العربية (RTL)</h2>
			<p>المتغيرات المتاحة: <code>{guest_name}</code>, <code>{event_title}</code>, <code>{event_date}</code>, <code>{venue_name}</code>, <code>{reference_code}</code>, <code>{ticket_reference}</code>, <code>{ticket_url}</code>, <code>{vip_url}</code></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tarbwy_save_email_templates">
				<?php wp_nonce_field( 'tarbwy_save_templates_nonce' ); ?>
				<?php foreach ( EmailManager::get_templates() as $tkey => $tpl ) : ?>
					<div style="border:1px solid #ddd; padding:16px; margin-bottom:16px; background:#fff;">
						<h3><?php echo esc_html( $tpl['title'] ); ?> (<code><?php echo esc_html( $tkey ); ?></code>)</h3>
						<p><label>العنوان:</label><br>
						<input type="text" name="templates[<?php echo esc_attr( $tkey ); ?>][subject]" class="large-text" value="<?php echo esc_attr( $tpl['subject'] ); ?>"></p>
						<p><label>النص:</label><br>
						<textarea name="templates[<?php echo esc_attr( $tkey ); ?>][body]" rows="4" class="large-text"><?php echo esc_textarea( $tpl['body'] ); ?></textarea></p>
					</div>
				<?php endforeach; ?>
				<?php submit_button( 'حفظ قوالب البريد' ); ?>
			</form>
		</section>

	<?php else : ?>
		<!-- Interactive Admin Workspace (Requests, Guests, VIP Import, Tickets) -->
		<section class="tarbwy-admin-panel" id="tarbwy-admin-interactive-root" data-page="<?php echo esc_attr( $current_page ); ?>">
			<h2>إدارة السجلات والطلبات والبطاقات</h2>
			<p>استخدم أدوات البحث والفلترة والمراجعة الفورية أدناه.</p>
		</section>
	<?php endif; ?>
</div>
