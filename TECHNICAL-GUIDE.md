# الدليل التقني والمعماري — TECHNICAL-GUIDE.md

**اسم الإضافة:** `Tarbwy Event Manager`  
**الإصدار:** `1.0.0`  
**Namespace PHP:** `Tarbwy\EventManager`  
**REST API Namespace:** `tarbwy/v1`

---

## 1. هيكل ملفات الإضافة (Directory Structure)

```text
tarbwy-event-manager/
├── tarbwy-event-manager.php          # الملف الرئيسي للإضافة وفحص المتطلبات والـ Hooks
├── uninstall.php                     # سياسة الاحتفاظ بالبيانات عند الحذف
├── includes/
│   ├── class-plugin.php              # منسق الإضافة الرئيسي وجدولة WP-Cron
│   ├── class-installer.php           # إنشاء الجداول الـ 8 عبر dbDelta والترقيات (Migrations)
│   ├── class-roles.php               # إدارة الأدوار الـ 3 والصلاحيات الـ 6 (Capabilities)
│   ├── class-security.php            # التشفير SHA-256، Honeypot، Rate Limiter، سجل النشاط المنقح
│   ├── class-qr-generator.php        # إصدار التذاكر المشفرة وإبطال القديمة
│   ├── class-email-manager.php       # قوالب البريد الـ 10، الطابور، وإعادة المحاولة (Backoff)
│   ├── class-csv-handler.php         # استيراد VIP CSV (مع Dry-Run) وتصدير UTF-8 BOM المحمي
│   ├── class-rest-api.php            # نقاط النهاية REST API تحت tarbwy/v1 والعمليات الذرية
│   ├── class-frontend-router.php     # التوجيه المستقل للمسارات الأربعة والـ Shortcodes
│   └── class-admin-dashboard.php     # لوحة الإدارة العربية RTL في wp-admin
├── templates/
│   ├── landing.php                   # قالب صفحة الهبوط والتسجيل العام (/launch)
│   ├── vip-rsvp.php                  # قالب بوابة كبار الشخصيات (/launch/vip)
│   ├── ticket.php                    # قالب بطاقة الدخول القابلة للطباعة (/launch/ticket)
│   ├── checkin.php                   # قالب بوابة الاستقبال ومسح QR (/launch/checkin)
│   └── admin-page.php                # قالب لوحة التحكم الإدارية
└── assets/
    ├── css/
    │   ├── tarbwy-public.css         # تنسيقات الواجهات العامة والطباعة (بدون CDN)
    │   └── tarbwy-admin.css          # تنسيقات لوحة الإدارة RTL
    └── js/
        ├── tarbwy-qr.js              # مولد QR SVG المحلي وماسح الكاميرا المحلي
        └── tarbwy-app.js             # منطق الواجهة الأمامية وبوابة Check-in
```

---

## 2. مخطط قاعدة البيانات (MySQL InnoDB Schema)

تستخدم الإضافة `$wpdb->prefix` الفعلي للموقع وتنشئ 8 جداول `InnoDB` مفهرسة عبر `dbDelta()`:

1. **`{$prefix}tarbwy_events`:** إعدادات الحدث، السعة (`capacity_total = 500`), حدود المرافقين، `approval_auto_confirms`, و`settings_json`.
2. **`{$prefix}tarbwy_registrations`:** طلبات الحضور العامة، `reference_code` (`UNIQUE`), وحالة المراجعة `review_status` (`pending`, `approved`, `waitlist`, `declined`, `cancelled`).
3. **`{$prefix}tarbwy_guests`:** السجل الفردي لكل شخص (`public`, `vip`, `companion`), مرتبط بـ `registration_id` و`parent_guest_id`. البريد الإلكتروني غير فريد عالمياً للسماح بمشاركة بريد العائلة مع تنبيه المسؤول. يفصل بين `rsvp_status` و`checkin_status`.
4. **`{$prefix}tarbwy_invitations`:** دعوات VIP، يخزن `token_hash` (`CHAR(64) UNIQUE`) بصمة `SHA-256` فقط، ولا يخزن الرمز الخام.
5. **`{$prefix}tarbwy_tickets`:** التذاكر الفردية، يخزن `ticket_reference` (`UNIQUE`) و`token_hash` (`CHAR(64) UNIQUE`) وحالة التذكرة (`active`, `revoked`, `used`).
6. **`{$prefix}tarbwy_checkins`:** سجل الدخول الفعلي، يفرض قيد **`UNIQUE KEY ticket_id (ticket_id)`** لمنع الدخول المكرر على مستوى محرك قاعدة البيانات.
7. **`{$prefix}tarbwy_email_logs`:** طابور وسجل رسائل البريد الإلكتروني مع عدد المحاولات (`attempts`) والجدولة (`scheduled_at`) والخطأ الأخير (`last_error`).
8. **`{$prefix}tarbwy_activity_log`:** سجل التدقيق الأمني المنقح (يحذف تلقائياً أي رموز سرية أو بيانات حساسة قبل التسجيل).

---

## 3. آلية منع الدخول المكرر المتزامن (Atomic Race-Condition Prevention)

لمنع ثغرة المسح المتزامن لنفس رمز QR من جهازين في اللحظة نفسها (Time-of-Check to Time-of-Use / TOCTOU):
- لا تستخدم الإضافة نمط `SELECT` ثم `INSERT` غير المحمي.
- عند وصول طلب `POST /wp-json/tarbwy/v1/checkin/scan`:
  1. يتم حساب بصمة `SHA-256` للرمز الممسوح والتحقق من أن التذكرة `active` والضيف `confirmed`.
  2. تبدأ معاملة قاعدة بيانات (`START TRANSACTION`).
  3. يتم تنفيذ استعلام ذري:
     ```sql
     INSERT IGNORE INTO wp_tarbwy_checkins (event_id, guest_id, ticket_id, checked_in_by, method, device_label, checked_in_at, created_at) VALUES (...)
     ```
  4. نظراً لوجود قيد `UNIQUE KEY (ticket_id)` على جدول `InnoDB`، يقوم محرك قاعدة البيانات بقفل الفهرس ذرياً؛ فينجح طلب واحد فقط (`affected_rows === 1`) ويُحدث حالة الضيف والتذكرة ثم ينفذ `COMMIT`، بينما يحصل الطلب المتزامن الثاني فوراً على `affected_rows === 0` وينفذ `ROLLBACK` ويعيد حالة `already_checked_in` (HTTP 409).

---

## 4. توثيق نقاط النهاية (REST API Endpoints — `tarbwy/v1`)

| المسار | الطريقة | الصلاحية (`permission_callback`) | الوظيفة |
| :--- | :---: | :--- | :--- |
| `/registrations` | `POST` | Rate Limiter + Honeypot | إرسال طلب تسجيل عام مع المرافقين بحالة `pending` |
| `/vip-invitations/{token}` | `GET` | Rate Limiter + SHA-256 Hash | التحقق من رمز دعوة VIP واسترجاع بيانات المدعو |
| `/vip-invitations/{token}/rsvp` | `POST` | Rate Limiter + SHA-256 Hash | تسجيل رد VIP («سأحضر» يؤكد ويصدر QR فوراً، أو «أعتذر») |
| `/tickets/view/{token}` | `GET` | Rate Limiter + SHA-256 Hash | استرجاع بيانات البطاقة المؤكدة للطباعة دون كشف البريد/الهاتف |
| `/checkin/lookup` | `GET` | `Roles::can_checkin` | البحث عن الضيوف بالاسم أو البريد أو رقم التذكرة |
| `/checkin/scan` | `POST` | `Roles::can_checkin` | المسح الذري لرمز QR ومنع الدخول المكرر المتزامن |
| `/checkin/manual` | `POST` | `Roles::can_checkin` | التسجيل اليدوي الذري للدخول |
| `/checkin/stats` | `GET` | `Roles::can_checkin` | إحصائيات الحضور والسعة المباشرة |
| `/guests` | `GET` | `Roles::can_manage_guests` | قائمة الضيوف مع الفلترة والبحث وتقسيم الصفحات (Pagination) |
| `/registrations/{id}/review` | `POST` | `Roles::can_review_registrations` | اعتماد/انتظار/رفض الطلب العام مع الاعتماد الجزئي للمرافقين |
| `/tickets/{id}/reissue` | `POST` | `Roles::can_manage_guests` | إبطال التذكرة السابقة وإصدار تذكرة ورمز QR جديدين |
| `/invitations/import` | `POST` | `Roles::can_manage_invitations` | استيراد ملف VIP CSV مع دعم `dry_run` للمعاينة |
