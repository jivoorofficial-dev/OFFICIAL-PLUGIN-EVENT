# Tarbwy Event Manager

## المواصفات الفنية والتنفيذية --- الإصدار 1.0

**الموقع:** `https://tarbwy.org`\
**المنصة:** WordPress / PHP / MySQL\
**اللغة:** العربية فقط، RTL\
**الحضور المتوقع:** 500 شخص إجمالاً بمن فيهم المرافقون\
**الاستضافة:** استضافة WordPress الحالية\
**الاتصال يوم الحدث:** Online\
**اسم الإضافة:** Tarbwy Event Manager

------------------------------------------------------------------------

## 1. الهدف

تطوير إضافة WordPress مستقلة لإدارة حفل إطلاق «تربوي -- الاتحاد العربي
للقيادة التربوية»، من صفحة الهبوط والتسجيل العام وVIP RSVP، إلى إصدار QR
الفردي، وCheck-in، والرسائل والتقارير.

يجب أن تكون الإضافة سهلة لفريق تربوي، آمنة، قابلة لإعادة الاستخدام في
فعاليات لاحقة، ومستقلة عن القالب الحالي.

### خارج نطاق الإصدار الأول

-   الدفع أو بيع التذاكر.
-   تطبيق Android/iOS مستقل.
-   المقاعد المرقمة.
-   CRM متكامل أو حملات تسويقية.
-   مزامنة Google Sheets.
-   Offline multi-device.
-   تعدد اللغات.
-   WooCommerce أو منصة SaaS منفصلة.

## 2. القرارات المعتمدة

  المتطلب          القرار
  ---------------- --------------------------------
  عدد الأشخاص      500 إجمالاً، بمن فيهم المرافقون
  المرافقون        سجل مستقل وQR مستقل لكل مرافق
  اللغة            العربية فقط
  الاتجاه          RTL
  الاستضافة        WordPress الحالية
  الاتصال          Online
  قاعدة البيانات   MySQL/MariaDB المتاحة
  Landing Page     `/launch`
  VIP RSVP         `/launch/vip`
  Check-in         `/launch/checkin`
  Ticket           مسار آمن ضمن `/launch/ticket`

لا تفترض إصدار PHP أو MySQL قبل فحص الاستضافة. افحص بيئة الموقع والقالب
والإضافات والتوافق قبل البرمجة.

## 3. المعمارية

-   إضافة PHP مخصصة مستقلة عن القالب.
-   Elementor أو Gutenberg لتصميم صفحة الهبوط فقط إذا كان ذلك متوافقاً مع
    الموقع.
-   جداول MySQL مخصصة باستخدام `$wpdb->prefix` الفعلي.
-   REST API تحت namespace بإصدار `tarbwy/v1`.
-   JavaScript وCSS محليان، دون CDN وقت التشغيل.
-   WordPress capabilities للأدوار، و`wp_mail()` عبر SMTP يحدده العميل.
-   Cron Job حقيقي من الاستضافة لمعالجة البريد والتذكيرات.
-   لا تعديل على WordPress Core أو القالب أو إضافات أخرى.
-   لا أسرار أو كلمات مرور داخل ملفات المصدر أو Git.

## 4. الصفحات

### 4.1 `/launch`

Landing Page عربية RTL، Mobile-first، سريعة ومركزة: 1. Hero: اسم الحفل،
العبارة، التاريخ والوقت، المدينة والمكان، زر «طلب حضور». 2. عن حفل
الإشهار. 3. نبذة مؤسسية مختصرة عن تربوي. 4. البرنامج. 5. المتحدثون. 6.
المكان والخريطة ومعلومات الوصول. 7. الشركاء والرعاة. 8. نموذج طلب الحضور
داخل الصفحة. 9. وسيلة التواصل.

كل المحتوى والتاريخ والمكان والمتحدثون قابلون للتحرير من الإدارة. لا
تخترع بيانات غير مستلمة؛ أخفِ القسم أو اعرض «سيُعلن لاحقاً».

### 4.2 Public Registration

الاستمارة النهائية ستصل لاحقاً؛ اجعل حقولها قابلة للتعديل. الحقول
المؤقتة: الاسم، البريد، الهاتف، المؤسسة/الصفة. عند الإرسال: - تحقق
خادمياً من المدخلات. - honeypot/rate limit وحماية من التكرار. - أنشئ طلباً
بحالة `pending`. - أنشئ سجلاً للضيف الأساسي ولكل مرافق. - اعرض رسالة أن
الطلب مستلم وقيد المراجعة وليس تأكيداً. - أرسل رسالة استلام وإشعاراً لفريق
الحدث. - لا تصدر QR قبل الاعتماد والتأكيد.

### 4.3 VIP `/launch/vip`

-   لا يظهر في التنقل العام.
-   رابط شخصي برمز عشوائي قوي من `random_bytes()`، وتخزن بصمته SHA-256
    فقط.
-   لا تستخدم ID متسلسلاً أو البريد كرمز.
-   لا تكشف بيانات المدعو قبل التحقق من الرمز.
-   الدعوة قابلة للإبطال وإعادة الإصدار والانتهاء.
-   الخيارات: «سأحضر» أو «أعتذر».
-   عند «سأحضر» يصبح RSVP `confirmed` مباشرة.
-   عند الاعتذار يصبح `declined`.
-   يسمح بالمرافقين حسب حد يحدده المدير، ولكل مرافق سجل وQR مستقل.
-   سجل كل تعديل وأرسل رسائل RSVP المناسبة.
-   أضف `Referrer-Policy: no-referrer` حيث أمكن لتقليل تسرب الرمز.

### 4.4 Ticket

صفحة آمنة تعرض بطاقة الشخص المؤكد فقط: شعار، اسم الحدث، اسم حامل
البطاقة، رقم مرجعي، الموعد والمكان، QR وتعليمات الدخول. لا تعرض البريد
أو الهاتف. اجعل HTML printable أساساً، وPDF ميزة إضافية إذا كانت المكتبة
مستقرة.

### 4.5 Check-in `/launch/checkin`

واجهة مستقلة محمية بتسجيل دخول، Mobile-first وRTL: - مسح QR بكاميرا
الهاتف/Tablet. - بحث بالاسم أو البريد أو رقم التذكرة. - Manual
Check-in. - نتائج واضحة: صالح، مستخدم سابقاً، ملغى، غير معروف، غير
مؤكد. - عرض أقل قدر لازم من بيانات الضيف. - إحصائيات مباشرة. - لا تعرض
نجاحاً قبل تأكيد الخادم. - عند انقطاع الإنترنت، اعرض فشل الاتصال بوضوح
ولا تسجل نجاحاً محلياً على أنه مؤكد.

## 5. المرافقون

-   لكل مرافق `guest_id` مستقل وQR مستقل.
-   `parent_guest_id` يربطه بالضيف الأساسي.
-   لكل شخص RSVP وCheck-in مستقلان.
-   لا يؤدي دخول الضيف الأساسي إلى دخول المرافق تلقائياً.
-   العدد 500 يشمل الضيوف الأساسيين والمرافقين معاً.
-   في طلب الجمهور، يمكن للمسؤول اعتماد أو استبعاد مرافقين محددين.
-   في VIP، يحدد المدير الحد الأقصى للمرافقين.
-   إذا لم يكن للمرافق بريد، ترسل بطاقته إلى البريد الأساسي مع توضيح اسم
    حامل البطاقة.
-   لا تمنح المرافق دعوة VIP مستقلة إلا بإنشاء دعوة منفصلة.

## 6. نموذج الحالات

افصل الحالات في حقول مستقلة:

**`review_status`:** `pending`, `approved`, `waitlist`, `declined`,
`cancelled`\
**`rsvp_status`:** `not_invited`, `pending_response`, `confirmed`,
`declined`\
**`checkin_status`:** `not_checked_in`, `checked_in`

في الجمهور: الطلب يبدأ `pending`. عند الموافقة، الافتراضي
`approval_auto_confirms=true` فيحوّل الأشخاص المعتمدين إلى `confirmed`
ويصدر QR. اجعل الإعداد قابلاً للتغيير. هذا يطابق رحلة الوثيقة: القبول
ينتج عنه تأكيد وبطاقة.

في VIP: «سأحضر» يؤدي مباشرة إلى `confirmed`، والاعتذار إلى `declined`.

لا تخزن No-show كحالة دخول أثناء الحفل؛ احسبها بعد نهاية الحدث من
المؤكدين الذين لم يسجلوا الدخول، على مستوى كل شخص.

## 7. قاعدة البيانات

استخدم `$wpdb->prefix` و`dbDelta()` مع schema version/migrations.
الجداول InnoDB ومفهرسة.

### `tarbwy_events`

`id`, `event_key`, `title`, `slug`, `status`, `event_start_at`,
`event_end_at`, `timezone`, `venue_name`, `venue_address`, `map_url`,
`capacity_total` default 500, `max_companions_public`,
`max_companions_vip`, `approval_auto_confirms`, `registration_open`,
`rsvp_deadline`, `settings_json`, `created_at`, `updated_at`.

### `tarbwy_registrations`

`id`, `event_id`, `reference_code`, `primary_guest_id`, `review_status`,
`reviewed_by`, `reviewed_at`, `review_note` (داخلي)، `submitted_at`,
`created_at`, `updated_at`.

### `tarbwy_guests`

`id`, `event_id`, `registration_id`, `parent_guest_id`, `guest_type`
(public/vip/companion), `full_name`, `email`, `phone`, `organization`,
`job_title`, `rsvp_status`, `checkin_status`, `checked_in_at`,
`checked_in_by`, `dietary_requirements`, `accessibility_requirements`,
`admin_notes`, `created_at`, `updated_at`.

لا تجعل البريد فريداً عالمياً؛ قد يشترك أفراد العائلة في بريد واحد. استخدم
duplicate detection وتنبيهاً للمسؤول بدلاً من رفض المشاركة تلقائياً.

### `tarbwy_invitations`

`id`, `event_id`, `guest_id`, `token_hash` UNIQUE, `status`
(active/revoked/used/expired), `expires_at`, `last_accessed_at`,
`sent_at`, `created_by`, timestamps.

### `tarbwy_tickets`

`id`, `event_id`, `guest_id`, `ticket_reference` UNIQUE, `token_hash`
UNIQUE, `status` (active/revoked/used), `issued_at`, `revoked_at`,
timestamps.

### `tarbwy_checkins`

`id`, `event_id`, `guest_id`, `ticket_id` UNIQUE, `checked_in_by`,
`method` (qr/manual), `device_label`, `checked_in_at`, `created_at`.

### `tarbwy_email_logs`

`id`, `event_id`, `guest_id`, `template_key`, `recipient_hash`, `status`
(queued/sent/failed), `attempts`, `scheduled_at`, `sent_at`,
`last_error`, timestamps.

### `tarbwy_activity_log`

`id`, `event_id`, `actor_user_id`, `action`, `object_type`, `object_id`,
`metadata_json` منقح، `created_at`.

أضف الفهارس المناسبة. استخدم قيود UNIQUE لمنع تكرار ticket/check-in. لا
تعتمد على foreign keys وحدها لضمان التكامل.

## 8. REST API

Namespace: `tarbwy/v1`.

مسارات عامة محدودة: - `POST /registrations` -
`GET /vip-invitations/{token}` - `POST /vip-invitations/{token}/rsvp` -
`GET /tickets/view/{token}` عند الحاجة.

مسارات محمية: - `GET /checkin/lookup` - `POST /checkin/scan` -
`POST /checkin/manual` - `GET /checkin/stats` - `GET /guests` -
`POST /registrations/{id}/review` - `POST /tickets/{id}/reissue` -
`POST /invitations/import`.

كل route يجب أن يملك `permission_callback`. لا تستخدم `__return_true`
لمسار حساس. استخدم `$wpdb->prepare()`، تحقق من الصلاحيات وعلاقة السجل
بالحدث، وامنع IDOR وCSRF وXSS وSQL injection. لا تغير الحالات عبر GET.

## 9. منع الدخول المكرر

شرط حرج: - QR يحمل token عشوائياً لا PII ولا guest ID. - خزّن hash فقط. -
عند المسح، تحقق من أن التذكرة active وأن RSVP confirmed. - لا تستخدم نمط
SELECT ثم INSERT غير محمي. - استخدم قيد UNIQUE على `ticket_id` في
checkins ومعاملة/عملية ذرية. - عند مسحين متزامنين، يجب أن ينجح واحد فقط
والآخر يعرض Already Checked-in. - كل جهاز يتصل بالخادم نفسه؛ لا تعتبر
localStorage مصدراً للحقيقة.

## 10. الصلاحيات

Capabilities مقترحة: - `manage_tarbwy_events` -
`review_tarbwy_registrations` - `manage_tarbwy_guests` -
`manage_tarbwy_invitations` - `checkin_tarbwy_guests` -
`export_tarbwy_reports`

أدوار: Event Administrator، Registration Manager، Check-in Staff. موظف
الاستقبال لا يستطيع إدارة الطلبات أو التصدير أو إعدادات الموقع.

## 11. البريد والجدولة

استخدم `wp_mail()` عبر طبقة WordPress، قابلة للعمل مع SMTP
plugin/provider يحدده العميل. لا تفترض أن Brevo متصل.

القوالب: - استلام طلب عام. - قبول/انتظار/رفض. - دعوة VIP. - استلام
RSVP. - تأكيد VIP/اعتذار VIP. - إصدار التذكرة. - تذكير قبل الحدث. - شكر
بعد الحدث.

القوالب عربية RTL وقابلة للتحرير، مع placeholders آمنة مثل
`{guest_name}`, `{event_title}`, `{event_date}`, `{venue_name}`,
`{ticket_url}`.

أنشئ queue وسجل محاولات، وإعادة محاولة محدودة مع backoff ومنع تكرار
الرسائل الناجحة. استخدم server cron موثقاً. لا تدّعِ أن `wp_mail()` يثبت
وصول البريد إلى Inbox؛ هو يثبت قبول طبقة الإرسال فقط.

التذكير للمؤكدين فقط، والشكر للفئة التي يحددها المدير. استخدم timezone
الحدث.

## 12. لوحة الإدارة والتقارير

القوائم: - Dashboard - Guest List - Registration Requests - VIP
Invitations - Tickets & QR - Check-in - Reports & Export - Event
Settings - Email Templates/Logs

المؤشرات: الطلبات، pending، approved، waitlist، declined، VIP invited،
confirmed، checked-in، remaining، no-show بعد نهاية الحدث.

وظائف: بحث وفلاتر وpagination، تعديل وإضافة ضيف، استيراد VIP CSV مع
preview وdry-run، إعادة إرسال/إبطال دعوة، إعادة إصدار QR، تصدير CSV.

التصدير UTF-8 BOM لفتح العربية في Excel، مع حماية CSV formula injection
(`=`, `+`, `-`, `@` ومحارف التحكم). صلاحية مستقلة للتصدير وسجل للعملية.

## 13. الأمان والخصوصية

-   HTTPS.
-   تحقق خادمي من كل مدخل.
-   sanitize عند الإدخال وescape عند الإخراج.
-   capabilities وnonces للعمليات الإدارية.
-   rate limiting وhoneypot للنماذج العامة.
-   لا تخزن أو تسجل raw tokens.
-   لا تضع بيانات شخصية داخل QR.
-   لا تعرض بيانات VIP قبل التحقق.
-   رفع CSV آمن مع فحص النوع والحجم والترميز.
-   سجل نشاط منقح دون أسرار أو بيانات حساسة.
-   اجمع الحد الأدنى من بيانات الاحتياجات الغذائية/إمكانية الوصول،
    واجعلها اختيارية ومحدودة الوصول.
-   سياسة الاحتفاظ والحذف يحددها مالك البيانات.
-   لا تطبع SQL أو stack traces للمستخدم.

## 14. الأداء والتصميم

-   500 شخص لا يحتاجون microservices أو Redis.
-   pagination وفهارس SQL.
-   لا تحمل كل الضيوف دفعة واحدة.
-   polling خفيف للإحصائيات (مثلاً 10--20 ثانية عند الحاجة).
-   صفحة هبوط خفيفة وmobile-first.
-   Check-in سريع بأزرار كبيرة ونتائج واضحة.
-   تصميم مؤسسي، لا تخترع الشعار أو الألوان النهائية.
-   لا صور متحدثين/شركاء عشوائية.
-   لا CDN runtime dependencies.

## 15. الاختبارات الإلزامية

اختبر ووثق: 1. التثبيت والتفعيل وإنشاء الجداول. 2. الترقية دون فقد
بيانات. 3. تسجيل عام صحيح وخاطئ ومكرر وسبام. 4.
pending/approved/waitlist/declined. 5. اعتماد عدد جزئي من المرافقين. 6.
VIP token صحيح وخاطئ ومنتهي وملغى. 7. RSVP confirm/decline. 8. QR مستقل
لكل شخص. 9. إبطال وإعادة إصدار QR. 10. مسح QR صالح ومستخدم وملغى وغير
مؤكد. 11. مسح متزامن من جهازين؛ نجاح واحد فقط. 12. Manual Check-in. 13.
منع موظف Check-in من صلاحيات الإدارة. 14. البريد والqueue والفشل وإعادة
المحاولة وعدم التكرار. 15. تذكير في الموعد. 16. تصدير عربي وCSV
injection. 17. 500 شخص على الأقل. 18. هاتف وTablet. 19.
XSS/SQLi/CSRF/IDOR الأساسية. 20. توافق القالب الحالي وعدم كسر الموقع.
21. backup/restore على staging.

## 16. متطلبات التسليم

سلّم: - مجلد الإضافة كاملاً. - ZIP قابل للتثبيت. - ملفات المصدر. -
`README-AR.md`. - `ADMIN-GUIDE-AR.md`. - `TECHNICAL-GUIDE.md`. -
`TEST-REPORT.md` بنتائج فعلية. - `CHANGELOG.md`. - `DECISIONS.md`. -
`UNINSTALL.md` لسياسة البيانات عند إزالة الإضافة.

لا تعتبر العمل مكتملاً بواجهة شكلية أو TODO. يجب أن تعمل الرحلة
End-to-End. اذكر الاختبارات التي نفذتها فعلياً، وما فشل، وما يحتاج إعداداً
على الاستضافة، وما ينتظر تربوي: الهوية، النصوص، الاستمارة، قائمة VIP،
SMTP، بيانات الحدث.

**ابدأ بفحص المشروع والبيئة وعرض خطة التنفيذ والمخاطر. ثم نفّذ المراحل
بالتتابع، واختبر كل مرحلة قبل الانتقال إلى التالية. لا ترفع إلى الإنتاج
قبل النسخ الاحتياطي والاختبار على Staging.**
