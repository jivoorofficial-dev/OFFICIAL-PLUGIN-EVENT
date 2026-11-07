# سياسة إزالة الإضافة والاحتفاظ بالبيانات — UNINSTALL.md

يوضح هذا المستند السلوك البرمجي الدقيق لإضافة **Tarbwy Event Manager** عند التعطيل (`Deactivation`) وعند الحذف (`Uninstallation`)، وكيفية إدارة الاحتفاظ ببيانات الحفل أو إزالتها نهائياً وفق قرارات مالك البيانات («تربوي — الاتحاد العربي للقيادة التربوية»).

---

## 1. السلوك عند تعطيل الإضافة (Plugin Deactivation)

عند قيام المسؤول بتعطيل الإضافة من لوحة تحكم WordPress (**إضافات ← تعطيل**):
- يتم إيقاف المهام المجدولة المؤقتة (`tarbwy_em_process_email_queue` و `tarbwy_em_send_event_reminders`).
- يتم تحديث قواعد التوجيه (`flush_rewrite_rules`).
- **لا يتم حذف أي جدول أو سجل ضيف أو تذكرة أو إعدادات على الإطلاق.**

---

## 2. السلوك الافتراضي عند حذف الإضافة (Plugin Uninstall — Safe Retention Default)

حرصاً على عدم ضياع سجلات الحضور الرسمية أو التقارير نتيجة حذف الإضافة بالخطأ:
- الوضع الافتراضي في `uninstall.php` هو **الاحتفاظ الآمن بالبيانات (`delete_data_on_uninstall = false`)**.
- عند حذف الإضافة في الوضع الافتراضي، تبقى الجداول الـ 8 محفوظة في قاعدة البيانات لاستعادتها أو أرشفتها.

---

## 3. الحذف النهائي الكامل للبيانات (Complete Data Purge)

إذا قرر مالك البيانات («تربوي») بعد انتهاء فترة الاحتفاظ القانونية حذف كافة سجلات الضيوف والتذاكر والجداول نهائياً عند إزالة الإضافة:
1. يتم تفعيل خيار `delete_data_on_uninstall = true` في إعدادات الإضافة (`tarbwy_em_global_settings`).
2. عند حذف الإضافة من WordPress، يقوم الملف `uninstall.php` بتنفيذ `DROP TABLE IF EXISTS` للجداول الثمانية التالية:
   - `{$prefix}tarbwy_checkins`
   - `{$prefix}tarbwy_tickets`
   - `{$prefix}tarbwy_invitations`
   - `{$prefix}tarbwy_email_logs`
   - `{$prefix}tarbwy_activity_log`
   - `{$prefix}tarbwy_guests`
   - `{$prefix}tarbwy_registrations`
   - `{$prefix}tarbwy_events`
3. كما يقوم بحذف الخيارات (`tarbwy_em_db_version`, `tarbwy_em_global_settings`, `tarbwy_em_email_templates`) وإزالة الأدوار الثلاثة المخصصة.
