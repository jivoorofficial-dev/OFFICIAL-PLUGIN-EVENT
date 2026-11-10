import express from 'express';
import { createServer as createViteServer } from 'vite';
import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = 3000;

app.use(express.json({ limit: '5mb' }));

// ============================================================================
// RELATIONAL EVENT DATABASE STATE (Mirrors the 8 MySQL InnoDB Plugin Tables)
// ============================================================================

export interface EventRecord {
  id: number;
  event_key: string;
  title: string;
  slug: string;
  status: string;
  event_start_at: string | null;
  event_end_at: string | null;
  timezone: string;
  venue_name: string;
  venue_address: string;
  map_url: string;
  capacity_total: number;
  max_companions_public: number;
  max_companions_vip: number;
  approval_auto_confirms: boolean;
  registration_open: boolean;
  rsvp_deadline: string;
  subtitle: string;
  date_display: string;
  city_display: string;
  about_event: string;
  about_tarbwy: string;
  why_tarbwy: string;
  tarbwy_space: string;
  access_info: string;
  contact_email: string;
  contact_phone: string;
  sender_name: string;
  sender_email: string;
  reply_to_email: string;
  logo_url?: string;
  entry_instructions: string;
  schedule_items: Array<{ time: string; title: string; desc: string }>;
  speakers: Array<{ name: string; role: string; photo_url?: string }>;
  sponsors: Array<{ name: string; tier: string }>;
}

export interface RegistrationRecord {
  id: number;
  event_id: number;
  reference_code: string;
  primary_guest_id: number;
  review_status: 'pending' | 'approved' | 'waitlist' | 'declined' | 'cancelled';
  reviewed_by: string | null;
  reviewed_at: string | null;
  review_note: string;
  submitted_at: string;
}

export interface GuestRecord {
  id: number;
  event_id: number;
  registration_id: number | null;
  parent_guest_id: number | null;
  guest_type: 'public' | 'vip' | 'companion';
  full_name: string;
  email: string;
  phone: string;
  organization: string;
  job_title: string;
  rsvp_status: 'not_invited' | 'pending_response' | 'confirmed' | 'declined';
  checkin_status: 'not_checked_in' | 'checked_in';
  checked_in_at: string | null;
  checked_in_by: string | null;
  dietary_requirements: string;
  accessibility_requirements: string;
  admin_notes: string;
  created_at: string;
}

export interface InvitationRecord {
  id: number;
  event_id: number;
  guest_id: number;
  token_hash: string;
  demo_raw_token_preview: string; // Stored only in simulator memory so evaluator can test 1-click VIP links
  max_companions_override: number;
  status: 'active' | 'revoked' | 'used' | 'expired';
  expires_at: string | null;
  last_accessed_at: string | null;
  sent_at: string | null;
  created_at: string;
}

export interface TicketRecord {
  id: number;
  event_id: number;
  guest_id: number;
  ticket_reference: string;
  token_hash: string;
  demo_raw_token_preview: string; // For 1-click testing in simulator
  status: 'active' | 'revoked' | 'used';
  issued_at: string;
  revoked_at: string | null;
}

export interface CheckinRecord {
  id: number;
  event_id: number;
  guest_id: number;
  ticket_id: number; // UNIQUE constraint enforced
  checked_in_by: string;
  method: 'qr' | 'manual';
  device_label: string;
  checked_in_at: string;
}

export interface EmailLogRecord {
  id: number;
  event_id: number;
  guest_id: number;
  template_key: string;
  recipient_hash: string;
  recipient_email: string;
  subject: string;
  status: 'queued' | 'sent' | 'failed';
  attempts: number;
  scheduled_at: string;
  sent_at: string | null;
  last_error: string | null;
}

export interface ActivityLogRecord {
  id: number;
  event_id: number;
  actor_role: string;
  action: string;
  object_type: string;
  object_id: number;
  metadata_json: string;
  created_at: string;
}

export interface StaffUserRecord {
  id: number;
  display_name: string;
  user_login: string;
  email: string;
  wp_role: 'administrator' | 'editor' | 'subscriber';
  role: 'tarbwy_event_admin' | 'tarbwy_registration_manager' | 'tarbwy_checkin_staff' | 'none';
  role_label: string;
  capabilities: string[];
}

const ROLE_DEFINITIONS: Record<
  StaffUserRecord['role'],
  { label: string; capabilities: string[] }
> = {
  tarbwy_event_admin: {
    label: 'مدير الفعالية (Event Administrator)',
    capabilities: [
      'manage_tarbwy_events',
      'review_tarbwy_registrations',
      'manage_tarbwy_guests',
      'manage_tarbwy_invitations',
      'checkin_tarbwy_guests',
      'export_tarbwy_reports',
    ],
  },
  tarbwy_registration_manager: {
    label: 'مسؤول التسجيل (Registration Manager)',
    capabilities: [
      'review_tarbwy_registrations',
      'manage_tarbwy_guests',
      'manage_tarbwy_invitations',
      'checkin_tarbwy_guests',
    ],
  },
  tarbwy_checkin_staff: {
    label: 'موظف استقبال (Check-in Staff)',
    capabilities: ['checkin_tarbwy_guests'],
  },
  none: {
    label: 'بدون دور في الفعالية (No Event Role)',
    capabilities: [],
  },
};

const EMAIL_TEMPLATE_SUBJECTS: Record<string, string> = {
  ticket_issued: 'بطاقة الدخول الرسمية ورمز QR: {event_title} ({guest_name})',
  event_reminder: 'تذكير بموعد {event_title} وبطاقة الدخول السريع (QR)',
  post_event_thank_you: 'شكراً لتشريفكم ومشاركتكم في {event_title}',
  vip_invitation: 'دعوة خاصة لحضور {event_title}',
  vip_invitation_resent: 'تذكير وإعادة إرسال دعوة VIP الخاصة لحضور {event_title}',
  registration_approved: 'تأكيد اعتماد حضوركم وبطاقات الدخول (QR): {event_title}',
  registration_waitlist: 'تحديث بشأن طلب حضوركم (قائمة الانتظار): {event_title}',
  registration_declined: 'إشعار بخصوص طلب التسجيل في {event_title}',
  registration_status_changed: 'إشعار بتحديث حالة طلب التسجيل ({reference_code}): {event_title}',
  public_registration_received: 'استلام طلب حضوركم: {event_title} — رقم الطلب {reference_code}',
  vip_rsvp_confirmed: 'تأكيد تشريفكم وبطاقة الدخول الخاصة (VIP): {event_title}',
  vip_rsvp_declined: 'استلام اعتذاركم عن حضور {event_title}',
  checkin_confirmation: 'أهلاً بكم في {event_title} — تم تسجيل دخولكم بنجاح',
  event_updates: 'تحديث هام للمشاركين في {event_title}',
};

export interface EmailTemplateConfig {
  title: string;
  badge: string;
  accent_color: string;
  subject: string;
  heading: string;
  body: string;
  cta_label: string;
  cta_url_placeholder: string;
  show_qr_box: boolean;
  show_event_card: boolean;
}

const DEFAULT_EMAIL_TEMPLATES: Record<string, EmailTemplateConfig> = {
  public_registration_received: {
    title: '1. استلام طلب حضور عام (Registration Received)',
    badge: 'إشعار استلام طلب حضور · قيد المراجعة',
    accent_color: '#B45309',
    subject: 'استلام طلب حضوركم: {event_title} — رقم الطلب {reference_code}',
    heading: 'تم استلام طلب حضوركم وهو قيد المراجعة',
    body: 'مرحباً {guest_name}،\n\nنشكركم على اهتمامكم بالمشاركة في «{event_title}».\nلقد تم استلام طلب التسجيل الخاص بكم بنجاح برقم مرجعي ({reference_code})، وهو الآن قيد المراجعة من قِبل اللجنة التنظيمية للحدث.\n\nيرجى ملاحظة أن هذا الإشعار يفيد باستلام الطلب فقط ولا يُعد تأكيداً نهائياً للحضور. سنوافيكم برسالة مستقلة فور اعتماد الطلب تتضمن بطاقة الدخول الشخصية المزودة برمز QR الفريد.',
    cta_label: 'استعراض صفحة حفل الإشهار',
    cta_url_placeholder: '{event_url}',
    show_qr_box: false,
    show_event_card: true,
  },
  registration_approved: {
    title: '2. اعتماد طلب الحضور وإصدار البطاقات (Registration Approved + Multi-QR)',
    badge: 'تأكيد رسمي لاعتماد الحضور · بطاقة QR جاهزة',
    accent_color: '#14532D',
    subject: 'تأكيد اعتماد حضوركم وبطاقات الدخول (QR): {event_title}',
    heading: 'يسرنا تأكيد اعتماد حضوركم وإصدار بطاقات الدخول الفردية',
    body: 'مرحباً {guest_name}،\n\nيسعدنا إبلاغكم بموافقة اللجنة المنظمة على طلب حضوركم في «{event_title}» وتأكيد تسجيلكم رسمياً.\n\nتجدون أدناه بطاقات الدخول الفردية المزودة برمز الاستجابة السريعة (QR) لكل شخص معتمد ضمن طلبكم. يرجى الاحتفاظ بالبطاقات لإبرازها عند بوابة الاستقبال يوم الحدث.\n\n{qr_code}',
    cta_label: 'فتح وطباعة بطاقة الدخول (QR)',
    cta_url_placeholder: '{ticket_url}',
    show_qr_box: true,
    show_event_card: true,
  },
  registration_declined: {
    title: '3. الاعتذار عن عدم قبول طلب التسجيل (Registration Rejected)',
    badge: 'تحديث حالة طلب الحضور',
    accent_color: '#44403C',
    subject: 'إشعار بخصوص طلب التسجيل في {event_title}',
    heading: 'نشكر اهتمامكم بحضور حفل إشهار تربوي',
    body: 'مرحباً {guest_name}،\n\nتتقدم اللجنة المنظمة لـ «{event_title}» بخالص الشكر والتقدير لاهتمامكم بطلب الحضور (رقم المرجع: {reference_code}).\n\nنعتذر إليكم عن عدم إمكانية تلبية طلب الحضور في هذه الفعالية نظراً لمحدودية المقاعد واكتمال الطاقة الاستيعابية المخصصة للقاعة، ونتطلع للتشرف بحضوركم ومشاركتكم في الملتقيات والمبادرات التربوية القادمة.',
    cta_label: 'متابعة أخبار ومبادرات تربوي',
    cta_url_placeholder: '{event_url}',
    show_qr_box: false,
    show_event_card: false,
  },
  registration_waitlist: {
    title: '4. إشعار قائمة الانتظار (Waitlist Notification)',
    badge: 'قائمة الانتظار ذات الأولوية · Waitlist',
    accent_color: '#B45309',
    subject: 'تحديث بشأن طلب حضوركم (قائمة الانتظار): {event_title}',
    heading: 'تم إدراج طلبكم في قائمة الانتظار ذات الأولوية',
    body: 'مرحباً {guest_name}،\n\nنشكركم على تقديم طلب الحضور للمشاركة في «{event_title}» (رقم الطلب: {reference_code}).\n\nنظراً للإقبال الكبير ومحدودية المقاعد المخصصة للقاعة، تم إدراج طلبكم في قائمة الانتظار ذات الأولوية (Waitlist). سنقوم بإشعاركم فوراً وإصدار بطاقة الدخول الشخصية (QR) في حال توفر مقعد شاغر قبل موعد الحفل.',
    cta_label: 'عرض تفاصيل الحدث على المنصة',
    cta_url_placeholder: '{event_url}',
    show_qr_box: false,
    show_event_card: true,
  },
  registration_status_changed: {
    title: '5. إشعار بتحديث حالة التسجيل (Registration Status Changed)',
    badge: 'تحديث إداري لحالة التسجيل',
    accent_color: '#1E3A8A',
    subject: 'إشعار بتحديث حالة طلب التسجيل ({reference_code}): {event_title}',
    heading: 'تم تحديث حالة طلب حضوركم في النظام',
    body: 'مرحباً {guest_name}،\n\nنود إشعاركم بأنه تم تحديث حالة طلب التسجيل الخاص بكم برقم ({reference_code}) في «{event_title}».\n\nيرجى ملاحظة أنه في حال تحويل الحالة إلى قائمة الانتظار أو الاعتذار عن القبول، فإن أي بطاقات دخول (QR) سابقة قد تم إبطال صلاحيتها تلقائياً.',
    cta_label: 'زيارة صفحة الحدث الرسمية',
    cta_url_placeholder: '{event_url}',
    show_qr_box: false,
    show_event_card: true,
  },
  vip_invitation: {
    title: '6. دعوة خاصة لكبار الشخصيات (VIP Invitation)',
    badge: 'دعوة رسمية خاصة · كبار الشخصيات VIP',
    accent_color: '#9A3412',
    subject: 'دعوة خاصة لحضور {event_title}',
    heading: 'دعوة رسمية لتشريف حفل إشهار «تربوي»',
    body: 'سعادة الأستاذ / الدكتور {guest_name} حفظه الله،\n\nيتشرف «تربوي — الاتحاد العربي للقيادة التربوية» بدعوتكم لحضور «{event_title}»، والذي يجمع نخبة القيادات التربوية وصناع القرار والخبراء في العالم العربي.\n\nرمز الدعوة الشخصي الخاص بكم (Personal Invitation Code): {vip_code}\n\nللتفضل بتأكيد الحضور أو الاعتذار — مع إمكانية تسجيل المرافقين ضمن الحد المخصص لدعوتكم — يرجى نسخ رمز الدعوة الشخصي أعلاه وإدخاله في بوابة كبار الشخصيات (/launch/vip) أو الضغط على زر الدخول المباشر أدناه قبل تاريخ ({rsvp_deadline}).',
    cta_label: 'تأكيد الحضور أو الاعتذار عبر بوابة VIP الخاصة',
    cta_url_placeholder: '{vip_url}',
    show_qr_box: false,
    show_event_card: true,
  },
  vip_invitation_resent: {
    title: '7. إعادة إرسال وتذكير دعوة كبار الشخصيات (VIP Invitation Resent)',
    badge: 'تذكير بدعوة خاصة · كبار الشخصيات VIP',
    accent_color: '#9A3412',
    subject: 'تذكير وإعادة إرسال دعوة VIP الخاصة لحضور {event_title}',
    heading: 'تذكير بالدعوة الرسمية لتشريف حفل إشهار «تربوي»',
    body: 'سعادة الأستاذ / الدكتور {guest_name} حفظه الله،\n\nنود تذكير سعادتكم بالدعوة الخاصة الموجهة إليكم لحضور «{event_title}».\n\nرمز الدعوة الشخصي الخاص بكم (Personal Invitation Code): {vip_code}\n\nيسعدنا استقبال ردكم الكريم بتأكيد الحضور أو الاعتذار عبر إدخال رمز الدعوة الشخصي في بوابة VIP أو عبر الرابط الآمن المخصص لسعادتكم أدناه.',
    cta_label: 'فتح بوابة دعوة VIP وتأكيد الحضور',
    cta_url_placeholder: '{vip_url}',
    show_qr_box: false,
    show_event_card: true,
  },
  vip_rsvp_confirmed: {
    title: '8. تأكيد حضور VIP وإصدار البطاقة (VIP RSVP Confirmation)',
    badge: 'تأكيد تشريف VIP · بطاقة الدخول الخاصة',
    accent_color: '#14532D',
    subject: 'تأكيد تشريفكم وبطاقة الدخول الخاصة (VIP): {event_title}',
    heading: 'نسعد بتأكيد تشريفكم وإصدار بطاقة الدخول الخاصة',
    body: 'سعادة الأستاذ / الدكتور {guest_name} حفظه الله،\n\nنتقدم لسعادتكم بخالص الشكر لتأكيد تشريفكم وحضوركم «{event_title}».\n\nأُصدرت لسعادتكم بطاقة الدخول الشخصية الفردية برقم ({ticket_reference}) المزودة برمز QR الخاص بكم. وفي حال تسجيل مرافقين، فقد تم إصدار بطاقة دخول مستقلة برمز QR فريد لكل مرافق.\n\n{qr_code}',
    cta_label: 'عرض وطباعة بطاقة دخول VIP الخاصة',
    cta_url_placeholder: '{ticket_url}',
    show_qr_box: true,
    show_event_card: true,
  },
  vip_rsvp_declined: {
    title: '9. استلام اعتذار VIP عن الحضور (VIP RSVP Declined)',
    badge: 'تأكيد استلام الرد · كبار الشخصيات VIP',
    accent_color: '#44403C',
    subject: 'استلام اعتذاركم عن حضور {event_title}',
    heading: 'تلقينا بالتقدير اعتذاركم عن الحضور',
    body: 'سعادة الأستاذ / الدكتور {guest_name} حفظه الله،\n\nتلقينا بوافر التقدير والاحترام ردكم الكريم والاعتذار عن عدم التمكن من حضور «{event_title}»، ونتطلع دائماً للتشرف بلقائكم ومشاركتكم في الملتقيات والمبادرات القيادية القادمة للاتحاد.',
    cta_label: 'زيارة البوابة الرسمية لتربوي',
    cta_url_placeholder: '{event_url}',
    show_qr_box: false,
    show_event_card: false,
  },
  ticket_issued: {
    title: '10. تسليم بطاقة الدخول الفردية ورمز QR (Event Ticket & QR Delivery)',
    badge: 'بطاقة الدخول الرسمية المعتمدة · QR Ticket',
    accent_color: '#14532D',
    subject: 'بطاقة الدخول الرسمية ورمز QR: {event_title} ({guest_name})',
    heading: 'بطاقة الدخول الشخصية المعتمدة ورمز QR',
    body: 'مرحباً {guest_name}،\n\nمرفق في هذه الرسالة بطاقة الدخول الرسمية المعتمدة لحاملها ({guest_name}) برقم مرجعي ({ticket_reference}) لحضور «{event_title}».\n\nيرجى إبراز رمز الاستجابة السريعة (QR) الموضح أدناه عند بوابة الاستقبال يوم الفعالية ليتم التحقق الفوري وتسجيل الدخول.\n\n{qr_code}',
    cta_label: 'فتح بطاقة الدخول للطباعة أو الحفظ (QR)',
    cta_url_placeholder: '{ticket_url}',
    show_qr_box: true,
    show_event_card: true,
  },
  event_reminder: {
    title: '11. تذكير قبل موعد الحدث للمؤكدين (Event Reminder)',
    badge: 'تذكير باقتراب موعد الحفل · للمؤكدين فقط',
    accent_color: '#9A3412',
    subject: 'تذكير بموعد {event_title} وبطاقة الدخول السريع (QR)',
    heading: 'تذكير باقتراب موعد حفل الإشهار وإرشادات الدخول',
    body: 'مرحباً {guest_name}،\n\nيسعدنا تذكيركم باقتراب موعد انعقاد «{event_title}»، ونتطلع للترحيب بكم.\n\nلتسهيل إجراءات الاستقبال والدخول السريع يوم الفعالية، يرجى التأكد من جاهزية بطاقة الدخول الشخصية الخاصة بكم ({ticket_reference}) عبر هاتفكم المحمول أو بنسخة مطبوعة قبل الوصول إلى القاعة.\n\n{qr_code}',
    cta_label: 'استعراض بطاقة الدخول ورمز QR الآن',
    cta_url_placeholder: '{ticket_url}',
    show_qr_box: true,
    show_event_card: true,
  },
  checkin_confirmation: {
    title: '12. تأكيد تسجيل الدخول يوم الحدث (Check-in Confirmation)',
    badge: 'تأكيد تسجيل الوصول · أهلاً بكم في القاعة',
    accent_color: '#14532D',
    subject: 'أهلاً بكم في {event_title} — تم تسجيل دخولكم بنجاح',
    heading: 'مرحباً بكم في رحاب حفل إشهار «تربوي»',
    body: 'مرحباً {guest_name}،\n\nتم تسجيل دخولكم بنجاح عبر بوابة الاستقبال في «{event_title}» ببطاقة رقم ({ticket_reference}) في تمام ({checkin_time}).\n\nنرحب بكم أجمل ترحيب بين نخبة القيادات التربوية العربية، ونتمنى لكم لقاءً مثمراً وتجربة ثرية.',
    cta_label: 'استعراض برنامج الحفل والفقرات',
    cta_url_placeholder: '{event_url}',
    show_qr_box: false,
    show_event_card: true,
  },
  event_updates: {
    title: '13. إشعار تحديثات الحدث والبرنامج (Event Updates Notification)',
    badge: 'تحديث تنظيمي هام · حفل إشهار تربوي',
    accent_color: '#1E3A8A',
    subject: 'تحديث هام للمشاركين في {event_title}',
    heading: 'مستجدات وتحديثات تنظيمية خاصة بحفل الإشهار',
    body: 'مرحباً {guest_name}،\n\nنود إطلاعكم على آخر المستجدات والتحديثات التنظيمية المتعلقة بـ «{event_title}».\n\nيمكنكم الاطلاع على البرنامج المحدث وتفاصيل الموقع وإرشادات الوصول عبر الصفحة الرسمية للحدث.',
    cta_label: 'الاطلاع على التحديثات في صفحة الحدث',
    cta_url_placeholder: '{event_url}',
    show_qr_box: false,
    show_event_card: true,
  },
  post_event_thank_you: {
    title: '14. رسالة شكر وتقدير بعد الحدث (Post-Event Thank-You)',
    badge: 'شكر وتقدير من الأمانة العامة · تربوي',
    accent_color: '#14532D',
    subject: 'شكراً لتشريفكم ومشاركتكم في {event_title}',
    heading: 'شكراً لتشريفكم حفل إشهار «تربوي»',
    body: 'سعادة المشارك الكريم {guest_name}،\n\nيتقدم «تربوي — الاتحاد العربي للقيادة التربوية» بخالص الشكر وعظيم الامتنان لتشريفكم ومشاركتكم الفاعلة في «{event_title}».\n\nإن حضوركم وإسهامكم يمثلان ركيزة أساسية في بناء المساحة المهنية والمعرفية المشتركة للقيادة التربوية في الوطن العربي، ونتطلع لاستمرار التواصل والتكامل في مبادرات الاتحاد القادمة.',
    cta_label: 'زيارة منصة تربوي الرسمية',
    cta_url_placeholder: '{event_url}',
    show_qr_box: false,
    show_event_card: false,
  },
};

// Helpers matching PHP Security class
function sha256(input: string): string {
  return crypto.createHash('sha256').update(input.trim()).digest('hex');
}

function generateTokenPair(bytes = 20): { raw: string; hash: string } {
  const raw = crypto.randomBytes(bytes).toString('hex');
  return { raw, hash: sha256(raw) };
}

function formatVipPersonalCode(tokenHash: string): string {
  const clean = tokenHash.replace(/[^a-fA-F0-9]/g, '').toUpperCase();
  const short = clean.substring(0, 8).padEnd(8, '0');
  return `VIP-${short.substring(0, 4)}-${short.substring(4, 8)}`;
}

function findVipInvitationByInput(rawInput: string): InvitationRecord | undefined {
  let clean = String(rawInput || '').trim();
  if (!clean) return undefined;
  const urlMatch = clean.match(/[?&](?:v|code|token)=([A-Za-z0-9\-_]+)/i);
  if (urlMatch && urlMatch[1]) {
    clean = urlMatch[1];
  }
  const alnum = clean.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
  if (alnum.startsWith('VIP') && alnum.length === 11) {
    const prefix = alnum.substring(3).toLowerCase();
    return db.invitations.find((i) => i.token_hash.toLowerCase().startsWith(prefix));
  }
  if (/^[A-F0-9]{8}$/.test(alnum)) {
    const prefix = alnum.toLowerCase();
    return db.invitations.find((i) => i.token_hash.toLowerCase().startsWith(prefix));
  }
  const hash = sha256(clean);
  return db.invitations.find(
    (i) => i.token_hash === hash || i.demo_raw_token_preview.toLowerCase() === clean.toLowerCase()
  );
}

function generateRefCode(prefix = 'TRB'): string {
  const suffix = crypto.randomBytes(3).toString('hex').toUpperCase();
  return `${prefix}-2026-${suffix}`;
}

function sanitizeCsvCell(val: string | number | null | undefined): string {
  const str = String(val ?? '').replace(/[\r\n]+/g, ' ');
  if (!str) return '';
  const first = str.trimStart().charAt(0);
  if (['=', '+', '-', '@', '\t', '\r'].includes(first)) {
    return `'${str}`;
  }
  return str;
}

function nowIso(): string {
  return new Date().toISOString().replace('T', ' ').substring(0, 19);
}

// ============================================================================
// INITIAL SEEDED DATABASE STATE
// ============================================================================
const db = {
  event: {
    id: 1,
    event_key: 'tarbwy-launch-2026',
    title: 'حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية»',
    slug: 'launch',
    status: 'published',
    event_start_at: null,
    event_end_at: null,
    timezone: 'Asia/Riyadh',
    venue_name: 'سيُعلن لاحقاً',
    venue_address: 'سيتم تحديد اسم المكان والعنوان التفصيلي ورابط الخريطة فور الاعتماد النهائي من قِبل إدارة الحدث',
    map_url: '',
    capacity_total: 500,
    max_companions_public: 4,
    max_companions_vip: 4,
    approval_auto_confirms: true,
    registration_open: true,
    rsvp_deadline: 'سيُعلن لاحقاً',
    subtitle: 'الواجهة الرقمية الرسمية لحفل إشهار «تربوي» وإطلاق المساحة المؤسسية المشتركة للقيادة التربوية في العالم العربي',
    date_display: 'سيُعلن لاحقاً فور الاعتماد النهائي',
    city_display: 'سيُعلن لاحقاً فور الاعتماد النهائي',
    about_event:
      'يمثل حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية» محطة تأسيسية جامعة لنخبة القيادات التربوية وصناع القرار والشخصيات المرجعية والخبراء؛ بهدف الإعلان الرسمي عن انطلاق الاتحاد، واستعراض رؤيته التأسيسية، وتفعيل مسارات العمل المشترك للارتقاء بالقيادة التربوية العربية.',
    about_tarbwy:
      '«تربوي — الاتحاد العربي للقيادة التربوية» إطار مؤسسي ومهني عربي يُعنى بتمكين القيادات التربوية، وتطوير الفكر والممارسة القيادية في المؤسسات التعليمية، وبناء منظومة تشاركية رصينة تجمع القادة والخبراء التربويين في الوطن العربي.',
    why_tarbwy:
      'أُطلق «تربوي» استجابةً لحاجة المشهد التربوي العربي إلى مظلة مؤسسية جامعة توحّد الجهود القيادية المبعثرة، وتنقل المبادرات التربوية من الأطر الفردية المحدودة إلى عمل مؤسسي مستدام يواكب التحولات التعليمية المعاصرة.',
    tarbwy_space:
      'يسعى «تربوي» إلى بناء مساحة عربية مهنية ومعرفية مشتركة تربط القيادات التربوية وصناع السياسات والباحثين، وتتيح تبادل الخبرات النوعية، وتطوير المعايير القيادية، وإطلاق البرامج والمبادرات العابرة للقطاعات التعليمية.',
    access_info:
      'سيتم نشر إرشادات الوصول وبوابات الدخول ومواقف الاصطفاف المخصصة للضيوف فور اعتماد القاعة النهائية.',
    contact_email: 'events@tarbwy.org',
    contact_phone: 'سيُعلن لاحقاً',
    sender_name: 'Tarbwy',
    sender_email: 'noreply@tarbwy.org',
    reply_to_email: 'events@tarbwy.org',
    logo_url: '',
    entry_instructions:
      'الدخول يوم الحفل يتم حصرياً عبر إبراز بطاقة الدخول الشخصية المزودة برمز QR الفريد (عبر الهاتف أو مطبوعة) عند بوابة الاستقبال. يُسجل طلب الحضور العام أولاً بحالة (قيد المراجعة / Pending)، وتصدر بطاقة الدخول بعد اعتماد الطلب فقط.',
    schedule_items: [],
    speakers: [],
    sponsors: [],
  } as EventRecord,

  emailTemplates: JSON.parse(JSON.stringify(DEFAULT_EMAIL_TEMPLATES)) as Record<string, EmailTemplateConfig>,
  registrations: [] as RegistrationRecord[],
  guests: [] as GuestRecord[],
  invitations: [] as InvitationRecord[],
  tickets: [] as TicketRecord[],
  checkins: [] as CheckinRecord[],
  emailLogs: [] as EmailLogRecord[],
  activityLogs: [] as ActivityLogRecord[],
  staffUsers: [] as StaffUserRecord[],
  uniqueTicketCheckinSet: new Set<number>(), // Enforces UNIQUE KEY (ticket_id) in tarbwy_checkins
};

let seq = {
  reg: 1,
  guest: 1,
  inv: 1,
  tkt: 1,
  chk: 1,
  mail: 1,
  act: 1,
  staff: 1,
};

function logActivity(action: string, objectType: string, objectId: number, meta: Record<string, unknown> = {}, actor = 'event_admin') {
  const redacted: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(meta)) {
    if (['token', 'raw_token', 'email', 'phone'].includes(k.toLowerCase())) {
      redacted[k] = '[REDACTED]';
    } else {
      redacted[k] = v;
    }
  }
  db.activityLogs.unshift({
    id: seq.act++,
    event_id: 1,
    actor_role: actor,
    action,
    object_type: objectType,
    object_id: objectId,
    metadata_json: JSON.stringify(redacted),
    created_at: nowIso(),
  });
}

function enqueueEmail(guestId: number, templateKey: string, recipientEmail: string, subject: string, allowDuplicate = false) {
  if (!recipientEmail || !recipientEmail.includes('@')) return;
  if (!allowDuplicate) {
    const exists = db.emailLogs.find(
      (e) => e.guest_id === guestId && e.template_key === templateKey && (e.status === 'sent' || e.status === 'queued')
    );
    if (exists) return;
  }
  const ts = nowIso();
  db.emailLogs.unshift({
    id: seq.mail++,
    event_id: 1,
    guest_id: guestId,
    template_key: templateKey,
    recipient_hash: sha256(recipientEmail.toLowerCase()),
    recipient_email: recipientEmail,
    subject,
    status: 'sent',
    attempts: 1,
    scheduled_at: ts,
    sent_at: ts,
    last_error: null,
  });
}

function issueTicketForGuest(guestId: number, forceReissue = false): TicketRecord | null {
  const guest = db.guests.find((g) => g.id === guestId);
  if (!guest || guest.rsvp_status !== 'confirmed') return null;

  if (!forceReissue) {
    const existingActive = db.tickets.find((t) => t.guest_id === guestId && t.status === 'active');
    if (existingActive) {
      return existingActive;
    }
  }

  const ts = nowIso();
  // Revoke any existing active ticket for this guest
  for (const t of db.tickets) {
    if (t.guest_id === guestId && t.status === 'active') {
      t.status = 'revoked';
      t.revoked_at = ts;
    }
  }

  const pair = generateTokenPair(20);
  const ticket: TicketRecord = {
    id: seq.tkt++,
    event_id: 1,
    guest_id: guestId,
    ticket_reference: generateRefCode('TKT'),
    token_hash: pair.hash,
    demo_raw_token_preview: pair.raw,
    status: 'active',
    issued_at: ts,
    revoked_at: null,
  };
  db.tickets.push(ticket);
  logActivity('ticket_issued', 'ticket', ticket.id, { guest_id: guestId, ticket_reference: ticket.ticket_reference, force_reissue: forceReissue });
  return ticket;
}

function revokeTicketsForGuest(guestId: number): number {
  let count = 0;
  const ts = nowIso();
  for (const t of db.tickets) {
    if (t.guest_id === guestId && t.status === 'active') {
      t.status = 'revoked';
      t.revoked_at = ts;
      count++;
    }
  }
  return count;
}

function revokeTicketsForRegistration(regId: number): number {
  let count = 0;
  const guests = db.guests.filter((g) => g.registration_id === regId);
  for (const g of guests) {
    count += revokeTicketsForGuest(g.id);
  }
  return count;
}

// Seed realistic initial records so the dashboard, VIP, tickets, and check-in work immediately out-of-the-box
function seedInitialData() {
  const ts = nowIso();

  // 1. Approved & Confirmed Public Registration with 2 Companions (Each with independent QR ticket)
  const reg1Id = seq.reg++;
  const g1Id = seq.guest++;
  const g2Id = seq.guest++;
  const g3Id = seq.guest++;

  db.registrations.push({
    id: reg1Id,
    event_id: 1,
    reference_code: 'TRB-2026-A104F2',
    primary_guest_id: g1Id,
    review_status: 'approved',
    reviewed_by: 'مدير فعالية تربوي',
    reviewed_at: ts,
    review_note: 'تم اعتماد الضيف الأساسي والمرافق الأول، واستبعاد المرافق الثاني حسب السعة.',
    submitted_at: ts,
  });

  db.guests.push(
    {
      id: g1Id,
      event_id: 1,
      registration_id: reg1Id,
      parent_guest_id: null,
      guest_type: 'public',
      full_name: 'د. عبدالكريم بن محمد السليمان',
      email: 'alsulaiman@edu-council.org',
      phone: '+966501122334',
      organization: 'كلية القيادة والسياسات التربوية',
      job_title: 'عميد الكلية',
      rsvp_status: 'confirmed',
      checkin_status: 'not_checked_in',
      checked_in_at: null,
      checked_in_by: null,
      dietary_requirements: '',
      accessibility_requirements: '',
      admin_notes: '',
      created_at: ts,
    },
    {
      id: g2Id,
      event_id: 1,
      registration_id: reg1Id,
      parent_guest_id: g1Id,
      guest_type: 'companion',
      full_name: 'أ. فيصل عبدالكريم السليمان',
      email: '',
      phone: '+966501122335',
      organization: 'كلية القيادة والسياسات التربوية',
      job_title: 'مرافق',
      rsvp_status: 'confirmed',
      checkin_status: 'not_checked_in',
      checked_in_at: null,
      checked_in_by: null,
      dietary_requirements: '',
      accessibility_requirements: '',
      admin_notes: 'تُرسل بطاقته لبريد الضيف الأساسي لعدم وجود بريد مستقل',
      created_at: ts,
    },
    {
      id: g3Id,
      event_id: 1,
      registration_id: reg1Id,
      parent_guest_id: g1Id,
      guest_type: 'companion',
      full_name: 'أ. طارق عبدالكريم السليمان',
      email: '',
      phone: '',
      organization: 'كلية القيادة والسياسات التربوية',
      job_title: 'مرافق (مستبعد جزئياً)',
      rsvp_status: 'declined',
      checkin_status: 'not_checked_in',
      checked_in_at: null,
      checked_in_by: null,
      dietary_requirements: '',
      accessibility_requirements: '',
      admin_notes: 'تم استبعاده أثناء المراجعة الجزئية للمرافقين',
      created_at: ts,
    }
  );

  issueTicketForGuest(g1Id);
  issueTicketForGuest(g2Id);
  enqueueEmail(g1Id, 'registration_approved', 'alsulaiman@edu-council.org', 'تأكيد حضوركم وبطاقة الدخول: حفل إطلاق تربوي');
  enqueueEmail(g2Id, 'ticket_issued', 'alsulaiman@edu-council.org', 'بطاقة الدخول الخاصة بالمرافق: أ. فيصل عبدالكريم السليمان');

  // 2. Pending Public Registration with 1 Companion (Awaiting Admin Review - No QR issued yet)
  const reg2Id = seq.reg++;
  const g4Id = seq.guest++;
  const g5Id = seq.guest++;

  db.registrations.push({
    id: reg2Id,
    event_id: 1,
    reference_code: 'TRB-2026-B892E1',
    primary_guest_id: g4Id,
    review_status: 'pending',
    reviewed_by: null,
    reviewed_at: null,
    review_note: '',
    submitted_at: ts,
  });

  db.guests.push(
    {
      id: g4Id,
      event_id: 1,
      registration_id: reg2Id,
      parent_guest_id: null,
      guest_type: 'public',
      full_name: 'أ.د. هدى بنت عبدالرحمن المنصور',
      email: 'huda.mansour@arab-edu.org',
      phone: '+966554433221',
      organization: 'المركز العربي لتطوير التعليم',
      job_title: 'مديرة الأبحاث التربوية',
      rsvp_status: 'not_invited',
      checkin_status: 'not_checked_in',
      checked_in_at: null,
      checked_in_by: null,
      dietary_requirements: '',
      accessibility_requirements: '',
      admin_notes: '',
      created_at: ts,
    },
    {
      id: g5Id,
      event_id: 1,
      registration_id: reg2Id,
      parent_guest_id: g4Id,
      guest_type: 'companion',
      full_name: 'د. نورة عبدالرحمن المنصور',
      email: 'noura.m@arab-edu.org',
      phone: '',
      organization: 'المركز العربي لتطوير التعليم',
      job_title: 'مرافقة أكاديمية',
      rsvp_status: 'not_invited',
      checkin_status: 'not_checked_in',
      checked_in_at: null,
      checked_in_by: null,
      dietary_requirements: '',
      accessibility_requirements: '',
      admin_notes: '',
      created_at: ts,
    }
  );
  enqueueEmail(g4Id, 'public_registration_received', 'huda.mansour@arab-edu.org', 'استلام طلب حضوركم (قيد المراجعة) — TRB-2026-B892E1');

  // 3. Active VIP Invitation (Ready to test in /launch/vip)
  const g6Id = seq.guest++;
  const vipPair1 = generateTokenPair(24);
  db.guests.push({
    id: g6Id,
    event_id: 1,
    registration_id: null,
    parent_guest_id: null,
    guest_type: 'vip',
    full_name: 'معالي أ.د. إبراهيم بن سعد الحارثي',
    email: 'alharthi.vip@tarbwy.org',
    phone: '+966509988776',
    organization: 'المجلس الأعلى للقيادة التربوية',
    job_title: 'رئيس المجلس الاستشاري',
    rsvp_status: 'pending_response',
    checkin_status: 'not_checked_in',
    checked_in_at: null,
    checked_in_by: null,
    dietary_requirements: '',
    accessibility_requirements: '',
    admin_notes: 'كبار الشخصيات — دعوة خاصة فعالة',
    created_at: ts,
  });
  db.invitations.push({
    id: seq.inv++,
    event_id: 1,
    guest_id: g6Id,
    token_hash: vipPair1.hash,
    demo_raw_token_preview: vipPair1.raw,
    max_companions_override: 2,
    status: 'active',
    expires_at: null,
    last_accessed_at: null,
    sent_at: ts,
    created_at: ts,
  });
  enqueueEmail(g6Id, 'vip_invitation', 'alharthi.vip@tarbwy.org', 'دعوة خاصة لحضور حفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية»');

  // 4. Confirmed VIP Guest who already checked in (To demonstrate 'already_checked_in' state)
  const g7Id = seq.guest++;
  db.guests.push({
    id: g7Id,
    event_id: 1,
    registration_id: null,
    parent_guest_id: null,
    guest_type: 'vip',
    full_name: 'سعادة د. ماجد بن ناصر القحطاني',
    email: 'majed.q@tarbwy.org',
    phone: '+966507766554',
    organization: 'الأكاديمية العربية للقيادة التربوية',
    job_title: 'الأمين العام المساعد',
    rsvp_status: 'confirmed',
    checkin_status: 'checked_in',
    checked_in_at: ts,
    checked_in_by: 'Terminal-Gate-1',
    dietary_requirements: '',
    accessibility_requirements: '',
    admin_notes: '',
    created_at: ts,
  });
  const tkt7 = issueTicketForGuest(g7Id);
  if (tkt7) {
    tkt7.status = 'used';
    db.uniqueTicketCheckinSet.add(tkt7.id);
    db.checkins.push({
      id: seq.chk++,
      event_id: 1,
      guest_id: g7Id,
      ticket_id: tkt7.id,
      checked_in_by: 'موظف الاستقبال #1',
      method: 'qr',
      device_label: 'Terminal-Gate-1',
      checked_in_at: ts,
    });
  }

  // 5. Seed initial WordPress Staff Users with native wp_role + Tarbwy role
  db.staffUsers.push(
    {
      id: seq.staff++,
      display_name: 'أ. خالد المنصور (مدير النظام والفعالية)',
      user_login: 'khalid_admin',
      email: 'khalid@tarbwy.org',
      wp_role: 'administrator',
      role: 'tarbwy_event_admin',
      role_label: ROLE_DEFINITIONS.tarbwy_event_admin.label,
      capabilities: ROLE_DEFINITIONS.tarbwy_event_admin.capabilities,
    },
    {
      id: seq.staff++,
      display_name: 'أ. سارة العتيبي (مسؤولة التسجيل والدعوات)',
      user_login: 'sara_reg',
      email: 'sara@tarbwy.org',
      wp_role: 'editor',
      role: 'tarbwy_registration_manager',
      role_label: ROLE_DEFINITIONS.tarbwy_registration_manager.label,
      capabilities: ROLE_DEFINITIONS.tarbwy_registration_manager.capabilities,
    },
    {
      id: seq.staff++,
      display_name: 'موظف بوابة الاستقبال #1 (Check-in Terminal)',
      user_login: 'gate_staff_1',
      email: 'checkin1@tarbwy.org',
      wp_role: 'subscriber',
      role: 'tarbwy_checkin_staff',
      role_label: ROLE_DEFINITIONS.tarbwy_checkin_staff.label,
      capabilities: ROLE_DEFINITIONS.tarbwy_checkin_staff.capabilities,
    }
  );
}

seedInitialData();

// ============================================================================
// API ENDPOINTS (Mirrors WordPress REST API /wp-json/tarbwy/v1/* + Source Code Viewer)
// ============================================================================

// Download the real installable WordPress plugin ZIP
app.get('/api/plugin/download-zip', (_req, res) => {
  const zipPath = path.join(__dirname, 'tarbwy-event-manager.zip');
  if (fs.existsSync(zipPath)) {
    const zipBuffer = fs.readFileSync(zipPath);
    res.setHeader('Content-Type', 'application/zip');
    res.setHeader('Content-Length', String(zipBuffer.length));
    res.setHeader('Content-Disposition', 'attachment; filename="tarbwy-event-manager.zip"');
    res.setHeader('Cache-Control', 'no-store');
    res.end(zipBuffer);
  } else {
    res.status(404).json({ message: 'ملف ZIP غير موجود.' });
  }
});

// Inspect plugin source files and documentation
app.get('/api/plugin/files', (_req, res) => {
  const pluginDir = path.join(__dirname, 'tarbwy-event-manager');
  const fileList: Array<{ path: string; size: number; category: string }> = [];

  function walk(dir: string) {
    if (!fs.existsSync(dir)) return;
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        walk(full);
      } else {
        const rel = path.relative(pluginDir, full);
        const ext = path.extname(entry.name);
        const category = ext === '.php' ? 'PHP Core & Templates' : ext === '.md' ? 'Documentation (AR)' : 'Local Assets (JS/CSS)';
        fileList.push({
          path: rel,
          size: fs.statSync(full).size,
          category,
        });
      }
    }
  }
  walk(pluginDir);

  const testJsonPath = path.join(__dirname, 'public', 'test-results.json');
  const testData = fs.existsSync(testJsonPath)
    ? JSON.parse(fs.readFileSync(testJsonPath, 'utf-8'))
    : { tests: [], zipSize: 0, zipSha256: '', zipBase64: '' };

  res.json({
    files: fileList,
    tests: testData.tests,
    zipSize: testData.zipSize,
    zipSha256: testData.zipSha256 || '',
    zipBase64: testData.zipBase64 || '',
  });
});

app.get('/api/plugin/file-content', (req, res) => {
  const relPath = String(req.query.path || '');
  const safePath = path.normalize(relPath).replace(/^(\.\.(\/|\\|$))+/, '');
  const fullPath = path.join(__dirname, 'tarbwy-event-manager', safePath);
  if (!fullPath.startsWith(path.join(__dirname, 'tarbwy-event-manager')) || !fs.existsSync(fullPath)) {
    res.status(404).json({ message: 'الملف غير موجود.' });
    return;
  }
  res.json({
    path: safePath,
    content: fs.readFileSync(fullPath, 'utf-8'),
  });
});

// Get complete state for simulator dashboard & live views
app.get('/api/tarbwy/v1/state', (_req, res) => {
  const confirmed = db.guests.filter((g) => g.rsvp_status === 'confirmed').length;
  const checkedIn = db.guests.filter((g) => g.checkin_status === 'checked_in').length;
  const remaining = Math.max(0, confirmed - checkedIn);

  res.json({
    event: db.event,
    stats: {
      capacity_total: db.event.capacity_total,
      total_guests: db.guests.length,
      confirmed,
      checked_in: checkedIn,
      remaining,
      no_show: remaining,
      vip_invited: db.guests.filter((g) => g.guest_type === 'vip').length,
      companions_total: db.guests.filter((g) => g.guest_type === 'companion').length,
      total_requests: db.registrations.length,
      pending_requests: db.registrations.filter((r) => r.review_status === 'pending').length,
      approved_requests: db.registrations.filter((r) => r.review_status === 'approved').length,
      waitlist_requests: db.registrations.filter((r) => r.review_status === 'waitlist').length,
      declined_requests: db.registrations.filter((r) => r.review_status === 'declined').length,
    },
    registrations: db.registrations.map((r) => ({
      ...r,
      guests: db.guests.filter((g) => g.registration_id === r.id),
    })),
    guests: db.guests.map((g) => {
      const tkt = db.tickets.find((t) => t.guest_id === g.id && (t.status === 'active' || t.status === 'used'));
      const parent = g.parent_guest_id ? db.guests.find((p) => p.id === g.parent_guest_id) : null;
      return {
        ...g,
        parent_name: parent ? parent.full_name : null,
        ticket: tkt || null,
      };
    }),
    invitations: db.invitations.map((inv) => {
      const g = db.guests.find((x) => x.id === inv.guest_id);
      return {
        ...inv,
        personal_code: formatVipPersonalCode(inv.token_hash),
        guest: g || null,
      };
    }),
    tickets: db.tickets.map((t) => {
      const g = db.guests.find((x) => x.id === t.guest_id);
      return { ...t, guest: g || null };
    }),
    emailLogs: db.emailLogs.slice(0, 60),
    activityLogs: db.activityLogs.slice(0, 60),
    staffUsers: db.staffUsers,
    emailTemplates: db.emailTemplates,
  });
});

// 1. POST /api/tarbwy/v1/registrations (Public Registration)
app.post('/api/tarbwy/v1/registrations', (req, res) => {
  const body = req.body || {};
  if (body.website_url_hp) {
    res.status(400).json({ message: 'تم رفض الطلب بسبب الاشتباه بنشاط آلي (Honeypot Spam Protection).' });
    return;
  }
  if (!db.event.registration_open) {
    res.status(403).json({ message: 'باب التسجيل في هذا الحدث مغلق حالياً.' });
    return;
  }

  const fullName = String(body.full_name || '').trim();
  const email = String(body.email || '').trim();
  const phone = String(body.phone || '').trim();
  const organization = String(body.organization || '').trim();
  const jobTitle = String(body.job_title || '').trim();
  const dietary = String(body.dietary_requirements || '').trim();
  const companions = Array.isArray(body.companions) ? body.companions : [];

  if (fullName.length < 3) {
    res.status(422).json({ message: 'يرجى إدخال الاسم الكامل بشكل صحيح.' });
    return;
  }
  if (!email.includes('@')) {
    res.status(422).json({ message: 'يرجى إدخال بريد إلكتروني صحيح.' });
    return;
  }
  if (phone.length < 7) {
    res.status(422).json({ message: 'يرجى إدخال رقم هاتف صحيح للتواصل.' });
    return;
  }
  if (companions.length > db.event.max_companions_public) {
    res.status(422).json({
      message: `الحد الأقصى للمرافقين المسموح به في التسجيل العام هو ${db.event.max_companions_public}.`,
    });
    return;
  }

  // Exact duplicate check
  const exactDup = db.guests.find((g) => {
    if (g.parent_guest_id !== null) return false;
    if (g.email.toLowerCase() === email.toLowerCase() && g.full_name === fullName) {
      const r = db.registrations.find((reg) => reg.id === g.registration_id);
      return r && ['pending', 'approved', 'waitlist'].includes(r.review_status);
    }
    return false;
  });

  if (exactDup) {
    res.status(409).json({
      message: 'يوجد طلب حضور مسجل مسبقاً بنفس الاسم والبريد الإلكتروني وهو قيد المراجعة أو معتمد.',
    });
    return;
  }

  const sharedEmail = db.guests.some((g) => g.email && g.email.toLowerCase() === email.toLowerCase());
  const ts = nowIso();
  const refCode = generateRefCode('TRB');
  const regId = seq.reg++;
  const primaryId = seq.guest++;

  db.registrations.unshift({
    id: regId,
    event_id: 1,
    reference_code: refCode,
    primary_guest_id: primaryId,
    review_status: 'pending',
    reviewed_by: null,
    reviewed_at: null,
    review_note: '',
    submitted_at: ts,
  });

  db.guests.unshift({
    id: primaryId,
    event_id: 1,
    registration_id: regId,
    parent_guest_id: null,
    guest_type: 'public',
    full_name: fullName,
    email,
    phone,
    organization,
    job_title: jobTitle,
    rsvp_status: 'not_invited',
    checkin_status: 'not_checked_in',
    checked_in_at: null,
    checked_in_by: null,
    dietary_requirements: dietary,
    accessibility_requirements: '',
    admin_notes: sharedEmail ? 'تنبيه نظام: البريد الإلكتروني مشترك مع ضيف آخر مسجل في الحدث.' : '',
    created_at: ts,
  });

  for (const c of companions) {
    const cName = String(c.full_name || '').trim();
    if (!cName) continue;
    db.guests.push({
      id: seq.guest++,
      event_id: 1,
      registration_id: regId,
      parent_guest_id: primaryId,
      guest_type: 'companion',
      full_name: cName,
      email: String(c.email || '').trim(),
      phone: '',
      organization,
      job_title: String(c.job_title || 'مرافق').trim(),
      rsvp_status: 'not_invited',
      checkin_status: 'not_checked_in',
      checked_in_at: null,
      checked_in_by: null,
      dietary_requirements: '',
      accessibility_requirements: '',
      admin_notes: '',
      created_at: ts,
    });
  }

  enqueueEmail(primaryId, 'public_registration_received', email, `استلام طلب حضوركم: ${db.event.title} — رقم الطلب ${refCode}`);
  logActivity('public_registration_submitted', 'registration', regId, { reference_code: refCode, companions_count: companions.length }, 'public_visitor');

  res.status(201).json({
    status: 'pending',
    reference_code: refCode,
    registration_id: regId,
    message:
      'تم استلام طلب الحضور بنجاح وهو حالياً قيد المراجعة من قِبل اللجنة المنظمة. هذا الإشعار لا يُعد تأكيداً نهائياً للحضور، وستصلكم بطاقة الدخول الشخصية المزودة برمز QR فور اعتماد الطلب.',
  });
});

// 2. POST /api/tarbwy/v1/registrations/:id/review (Admin Review + Partial Companion Approval + Status Reversal QR Revocation)
app.post('/api/tarbwy/v1/registrations/:id/review', (req, res) => {
  const regId = Number(req.params.id);
  const reg = db.registrations.find((r) => r.id === regId);
  if (!reg) {
    res.status(404).json({ message: 'طلب التسجيل غير موجود.' });
    return;
  }

  const oldStatus = reg.review_status;
  const decision = req.body.review_status as RegistrationRecord['review_status'];
  const reviewNote = String(req.body.review_note || '');
  const approvedCompanionIds: number[] | null = Array.isArray(req.body.approved_companion_ids)
    ? req.body.approved_companion_ids.map(Number)
    : null;

  const ts = nowIso();
  reg.review_status = decision;
  reg.reviewed_by = 'مدير الفعالية';
  reg.reviewed_at = ts;
  reg.review_note = reviewNote;

  const groupGuests = db.guests.filter((g) => g.registration_id === regId);
  const primaryGuest = groupGuests.find((g) => g.parent_guest_id === null);
  const issuedTickets: TicketRecord[] = [];
  let revokedTicketsCount = 0;

  if (decision === 'approved') {
    for (const g of groupGuests) {
      const isCompanion = g.parent_guest_id !== null;
      if (isCompanion && approvedCompanionIds !== null && !approvedCompanionIds.includes(g.id)) {
        g.rsvp_status = 'declined';
        revokedTicketsCount += revokeTicketsForGuest(g.id);
        continue;
      }
      g.rsvp_status = db.event.approval_auto_confirms ? 'confirmed' : 'pending_response';
      if (db.event.approval_auto_confirms) {
        // forceReissue = false ensures repeated clicks reuse existing active ticket (no duplicate QR)
        const tkt = issueTicketForGuest(g.id, false);
        if (tkt) issuedTickets.push(tkt);
      }
    }
    if (primaryGuest && db.event.approval_auto_confirms) {
      const refList = issuedTickets.map((t) => t.ticket_reference).join(', ');
      enqueueEmail(
        primaryGuest.id,
        'registration_approved',
        primaryGuest.email,
        `تأكيد اعتماد حضوركم وبطاقات الدخول (${issuedTickets.length} QR): ${refList}`,
        true
      );
    }
  } else if (decision === 'waitlist') {
    revokedTicketsCount = revokeTicketsForRegistration(regId);
    for (const g of groupGuests) {
      g.rsvp_status = 'not_invited';
    }
    if (primaryGuest) {
      enqueueEmail(primaryGuest.id, 'registration_waitlist', primaryGuest.email, `إدراج طلبكم في قائمة الانتظار — ${reg.reference_code}`, true);
    }
  } else if (decision === 'declined' || decision === 'cancelled') {
    revokedTicketsCount = revokeTicketsForRegistration(regId);
    for (const g of groupGuests) {
      g.rsvp_status = 'declined';
    }
    if (primaryGuest) {
      enqueueEmail(primaryGuest.id, 'registration_declined', primaryGuest.email, `بشأن طلب التسجيل — ${reg.reference_code}`, true);
    }
  } else if (decision === 'pending') {
    revokedTicketsCount = revokeTicketsForRegistration(regId);
    for (const g of groupGuests) {
      g.rsvp_status = 'not_invited';
    }
  }

  logActivity('registration_reviewed', 'registration', regId, {
    previous_status: oldStatus,
    new_status: decision,
    tickets_issued: issuedTickets.length,
    tickets_revoked: revokedTicketsCount,
  });

  res.json({
    registration_id: regId,
    previous_status: oldStatus,
    review_status: decision,
    issued_tickets: issuedTickets,
    revoked_tickets: revokedTicketsCount,
    message:
      decision === 'approved'
        ? `تم اعتماد الطلب بنجاح وإصدار (${issuedTickets.length}) بطاقة QR فردية للمعتمدين وإرسال البريد.`
        : `تم تحديث حالة الطلب إلى (${decision}) وإبطال (${revokedTicketsCount}) بطاقة QR سابقة بنجاح.`,
  });
});

// 2b. POST /api/tarbwy/v1/registrations/:id/edit (Edit Registration Details)
app.post('/api/tarbwy/v1/registrations/:id/edit', (req, res) => {
  const regId = Number(req.params.id);
  const reg = db.registrations.find((r) => r.id === regId);
  if (!reg) {
    res.status(404).json({ message: 'طلب التسجيل غير موجود.' });
    return;
  }
  const primaryGuest = db.guests.find((g) => g.registration_id === regId && g.parent_guest_id === null);
  if (primaryGuest) {
    if (req.body.full_name) primaryGuest.full_name = String(req.body.full_name).trim();
    if (req.body.email) primaryGuest.email = String(req.body.email).trim();
    if (req.body.phone !== undefined) primaryGuest.phone = String(req.body.phone).trim();
    if (req.body.organization !== undefined) primaryGuest.organization = String(req.body.organization).trim();
    if (req.body.job_title !== undefined) primaryGuest.job_title = String(req.body.job_title).trim();
  }
  if (req.body.review_note !== undefined) {
    reg.review_note = String(req.body.review_note);
  }
  logActivity('registration_edited', 'registration', regId);
  res.json({ registration: reg, message: 'تم حفظ تعديلات بيانات التسجيل بنجاح.' });
});

// 2c. POST /api/tarbwy/v1/registrations/:id/delete (Delete Registration with Cascading QR Revocation)
app.post('/api/tarbwy/v1/registrations/:id/delete', (req, res) => {
  const regId = Number(req.params.id);
  const regIdx = db.registrations.findIndex((r) => r.id === regId);
  if (regIdx === -1) {
    res.status(404).json({ message: 'طلب التسجيل غير موجود.' });
    return;
  }
  const refCode = db.registrations[regIdx].reference_code;
  const revokedCount = revokeTicketsForRegistration(regId);
  const guestIds = db.guests.filter((g) => g.registration_id === regId).map((g) => g.id);
  db.tickets = db.tickets.filter((t) => !guestIds.includes(t.guest_id));
  db.guests = db.guests.filter((g) => g.registration_id !== regId);
  db.registrations.splice(regIdx, 1);
  logActivity('registration_deleted', 'registration', regId, { reference_code: refCode, revoked_tickets: revokedCount });
  res.json({ message: `تم حذف طلب التسجيل (${refCode}) وإلغاء جميع بطاقات QR المرتبطة به بنجاح.` });
});

// 3. GET /api/tarbwy/v1/vip-invitations/:token (Verify VIP Personal Invitation Code or Token Hash)
app.get('/api/tarbwy/v1/vip-invitations/:token', (req, res) => {
  const rawToken = String(req.params.token || '').trim();
  const inv = findVipInvitationByInput(rawToken);
  if (!inv) {
    res.status(404).json({ code: 'invalid_token', message: 'رمز الدعوة الشخصي أو الرابط غير صالح أو غير معروف.' });
    return;
  }
  if (inv.status === 'revoked') {
    res.status(410).json({ code: 'revoked_token', message: 'تم إبطال رمز الدعوة هذا وإصدار رمز بديل.' });
    return;
  }
  if (inv.status === 'expired') {
    res.status(410).json({ code: 'expired_token', message: 'انتهت صلاحية رمز الدعوة هذا.' });
    return;
  }

  inv.last_accessed_at = nowIso();
  const guest = db.guests.find((g) => g.id === inv.guest_id);
  if (!guest) {
    res.status(404).json({ message: 'بيانات المدعو غير موجودة.' });
    return;
  }

  const existingTickets = db.tickets
    .filter((t) => {
      if (t.status !== 'active' && t.status !== 'used') return false;
      if (t.guest_id === guest.id) return true;
      const g = db.guests.find((x) => x.id === t.guest_id);
      return g && g.parent_guest_id === guest.id;
    })
    .map((t) => {
      const g = db.guests.find((x) => x.id === t.guest_id);
      return {
        guest_name: g ? g.full_name : guest.full_name,
        guest_type: g ? g.guest_type : 'vip',
        ticket_reference: t.ticket_reference,
        raw_token: t.demo_raw_token_preview,
      };
    });

  res.json({
    invitation_id: inv.id,
    personal_code: formatVipPersonalCode(inv.token_hash),
    status: inv.status,
    rsvp_status: guest.rsvp_status,
    guest_name: guest.full_name,
    organization: guest.organization,
    job_title: guest.job_title,
    event_title: db.event.title,
    venue_name: db.event.venue_name,
    max_companions: inv.max_companions_override ?? db.event.max_companions_vip,
    issued_tickets: existingTickets,
  });
});

// 4. POST /api/tarbwy/v1/vip-invitations/:token/rsvp (Submit VIP RSVP via Personal Code or Token)
app.post('/api/tarbwy/v1/vip-invitations/:token/rsvp', (req, res) => {
  const rawToken = String(req.params.token || '').trim();
  const inv = findVipInvitationByInput(rawToken);
  if (!inv || (inv.status !== 'active' && inv.status !== 'used')) {
    res.status(403).json({ message: 'رمز الدعوة الشخصي غير صالح أو تم إبطاله مسبقاً.' });
    return;
  }

  const guest = db.guests.find((g) => g.id === inv.guest_id);
  if (!guest) {
    res.status(404).json({ message: 'الضيف غير موجود.' });
    return;
  }

  const decision = req.body.decision;
  const companions = Array.isArray(req.body.companions) ? req.body.companions : [];
  const maxComp = inv.max_companions_override ?? db.event.max_companions_vip;

  if (companions.length > maxComp) {
    res.status(422).json({ message: `الحد الأقصى للمرافقين المسموح به لهذه الدعوة هو ${maxComp}.` });
    return;
  }

  const ts = nowIso();

  if (decision === 'decline') {
    guest.rsvp_status = 'declined';
    inv.status = 'used';
    revokeTicketsForGuest(guest.id);
    enqueueEmail(guest.id, 'vip_rsvp_declined', guest.email, `استلام اعتذاركم عن حضور ${db.event.title}`, true);
    logActivity('vip_rsvp_declined', 'invitation', inv.id, {}, 'vip_guest');
    res.json({
      rsvp_status: 'declined',
      issued_tickets: [],
      message: 'تم تسجيل اعتذاركم عن الحضور. نشكركم ونتطلع للقائكم في مناسبات قادمة.',
    });
    return;
  }

  guest.rsvp_status = 'confirmed';
  inv.status = 'used';
  const issuedTickets: Array<{ guest_name: string; guest_type: string; ticket_reference: string; raw_token: string }> = [];

  const mainTkt = issueTicketForGuest(guest.id, false);
  if (mainTkt) {
    issuedTickets.push({
      guest_name: guest.full_name,
      guest_type: 'vip',
      ticket_reference: mainTkt.ticket_reference,
      raw_token: mainTkt.demo_raw_token_preview,
    });
    enqueueEmail(guest.id, 'vip_rsvp_confirmed', guest.email, `تأكيد تشريفكم وبطاقة الدخول الخاصة: ${mainTkt.ticket_reference}`, true);
  }

  for (const c of companions) {
    const cName = String(c.full_name || '').trim();
    if (!cName) continue;
    const cEmail = String(c.email || '').trim();
    const compId = seq.guest++;
    db.guests.push({
      id: compId,
      event_id: 1,
      registration_id: null,
      parent_guest_id: guest.id,
      guest_type: 'companion',
      full_name: cName,
      email: cEmail,
      phone: '',
      organization: guest.organization,
      job_title: 'مرافق VIP',
      rsvp_status: 'confirmed',
      checkin_status: 'not_checked_in',
      checked_in_at: null,
      checked_in_by: null,
      dietary_requirements: '',
      accessibility_requirements: '',
      admin_notes: '',
      created_at: ts,
    });
    const cTkt = issueTicketForGuest(compId, false);
    if (cTkt) {
      issuedTickets.push({
        guest_name: cName,
        guest_type: 'companion',
        ticket_reference: cTkt.ticket_reference,
        raw_token: cTkt.demo_raw_token_preview,
      });
      enqueueEmail(
        compId,
        'ticket_issued',
        cEmail || guest.email,
        `بطاقة دخول مرافق VIP (${cName}) — ${cTkt.ticket_reference}`,
        true
      );
    }
  }

  logActivity('vip_rsvp_confirmed', 'invitation', inv.id, { tickets_issued: issuedTickets.length }, 'vip_guest');

  res.json({
    rsvp_status: 'confirmed',
    issued_tickets: issuedTickets,
    message: 'تم تأكيد حضوركم بنجاح وإصدار بطاقات الدخول الفردية المزودة برمز QR.',
  });
});

// 5. GET /api/tarbwy/v1/tickets/view/:token (Printable Ticket View — No Email or Phone Exposed)
app.get('/api/tarbwy/v1/tickets/view/:token', (req, res) => {
  const rawToken = String(req.params.token || '').trim();
  const hash = sha256(rawToken);
  const tkt = db.tickets.find((t) => t.token_hash === hash);
  if (!tkt) {
    res.status(404).json({ code: 'not_found', message: 'بطاقة الدخول غير موجودة أو الرابط غير صحيح.' });
    return;
  }
  if (tkt.status === 'revoked') {
    res.status(410).json({ code: 'revoked', message: 'تم إلغاء هذه البطاقة وإصدار بطاقة بديلة.' });
    return;
  }
  const guest = db.guests.find((g) => g.id === tkt.guest_id);
  if (!guest || guest.rsvp_status !== 'confirmed') {
    res.status(403).json({ code: 'not_confirmed', message: 'هذه البطاقة غير مؤكدة الحضور.' });
    return;
  }

  // Strictly omits email & phone per Spec Section 4.4
  res.json({
    event_title: db.event.title,
    holder_name: guest.full_name,
    organization: guest.organization,
    guest_type: guest.guest_type,
    ticket_reference: tkt.ticket_reference,
    ticket_status: tkt.status,
    checkin_status: guest.checkin_status,
    venue_name: db.event.venue_name,
    venue_address: db.event.venue_address,
    date_display: db.event.date_display,
    entry_instructions: db.event.entry_instructions,
    qr_payload: rawToken,
  });
});

// 6. POST /api/tarbwy/v1/checkin/scan (Atomic Check-in Scan with Concurrent Race Protection)
function executeAtomicCheckin(tkt: TicketRecord | undefined, guest: GuestRecord | undefined, method: 'qr' | 'manual', deviceLabel: string) {
  if (!guest) {
    return { status: 404, body: { result_code: 'unknown', message: 'بطاقة غير معروفة — رمز QR غير مسجل في النظام.' } };
  }
  const parent = guest.parent_guest_id ? db.guests.find((p) => p.id === guest.parent_guest_id) : null;
  const summary = {
    guest_id: guest.id,
    full_name: guest.full_name,
    guest_type: guest.guest_type,
    parent_name: parent ? parent.full_name : null,
    organization: guest.organization,
    ticket_reference: tkt ? tkt.ticket_reference : '-',
    checked_in_at: guest.checked_in_at,
  };

  if (tkt && tkt.status === 'revoked') {
    return {
      status: 409,
      body: {
        result_code: 'revoked',
        message: 'بطاقة ملغاة — تم إبطال هذه التذكرة ولا يمكن الدخول بها.',
        guest: summary,
      },
    };
  }

  if (guest.rsvp_status !== 'confirmed' || !tkt) {
    return {
      status: 403,
      body: {
        result_code: 'not_confirmed',
        message: 'غير مؤكد — هذا الضيف غير مؤكد الحضور أو لم تصدر له تذكرة فعالة.',
        guest: summary,
      },
    };
  }

  // Atomic UNIQUE(ticket_id) check
  if (db.uniqueTicketCheckinSet.has(tkt.id)) {
    const prev = db.checkins.find((c) => c.ticket_id === tkt.id);
    return {
      status: 409,
      body: {
        result_code: 'already_checked_in',
        message: `مستخدم سابقاً — تم تسجيل دخول هذا الضيف مسبقاً في (${prev ? prev.checked_in_at : guest.checked_in_at}) عبر (${prev ? prev.device_label : 'بوابة الاستقبال'}).`,
        guest: { ...summary, checked_in_at: prev ? prev.checked_in_at : guest.checked_in_at },
      },
    };
  }

  const ts = nowIso();
  db.uniqueTicketCheckinSet.add(tkt.id);
  db.checkins.unshift({
    id: seq.chk++,
    event_id: 1,
    guest_id: guest.id,
    ticket_id: tkt.id,
    checked_in_by: 'موظف الاستقبال',
    method,
    device_label: deviceLabel,
    checked_in_at: ts,
  });

  guest.checkin_status = 'checked_in';
  guest.checked_in_at = ts;
  guest.checked_in_by = deviceLabel;
  tkt.status = 'used';

  const parentGuest = guest.parent_guest_id ? db.guests.find((p) => p.id === guest.parent_guest_id) : null;
  const checkinEmail = guest.email || (parentGuest ? parentGuest.email : '');
  if (checkinEmail) {
    enqueueEmail(
      guest.id,
      'checkin_confirmation',
      checkinEmail,
      `أهلاً بكم في ${db.event.title} — تم تسجيل دخولكم بنجاح (${guest.full_name})`,
      false
    );
  }

  logActivity('guest_checked_in', 'checkin', guest.id, { method, ticket_reference: tkt.ticket_reference, device_label: deviceLabel }, 'checkin_staff');

  return {
    status: 200,
    body: {
      result_code: 'valid',
      message: 'بطاقة صالحة — تم تأكيد وتسجيل الدخول بنجاح.',
      guest: { ...summary, checked_in_at: ts },
    },
  };
}

function resolveScannedTicket(rawInput: string): TicketRecord | undefined {
  let clean = String(rawInput || '').trim();
  if (!clean) return undefined;
  const urlMatch = clean.match(/[?&]t=([a-fA-F0-9]{16,128})/);
  if (urlMatch && urlMatch[1]) {
    clean = urlMatch[1];
  }
  if (/^TKT-/i.test(clean)) {
    return db.tickets.find((t) => t.ticket_reference.toUpperCase() === clean.toUpperCase());
  }
  const hash = sha256(clean);
  return db.tickets.find((t) => t.token_hash === hash || t.demo_raw_token_preview.toLowerCase() === clean.toLowerCase());
}

app.post('/api/tarbwy/v1/checkin/scan', (req, res) => {
  const rawToken = String(req.body.qr_token || '').trim();
  const deviceLabel = String(req.body.device_label || 'Terminal-1');
  if (!rawToken) {
    res.status(400).json({ result_code: 'unknown', message: 'رمز QR فارغ.' });
    return;
  }
  const tkt = resolveScannedTicket(rawToken);
  const guest = tkt ? db.guests.find((g) => g.id === tkt.guest_id) : undefined;
  const result = executeAtomicCheckin(tkt, guest, 'qr', deviceLabel);
  res.status(result.status).json(result.body);
});

// 6b. GET /api/tarbwy/v1/checkin/lookup (Manual Attendee Search by Name, Email, Ticket Number, Phone, Org, or Quick Filter)
app.get('/api/tarbwy/v1/checkin/lookup', (req, res) => {
  const q = String(req.query.q || '').trim().toLowerCase();
  const statusFilter = String(req.query.status || 'all').trim();

  let list = db.guests.map((g) => {
    const tkt = db.tickets.find((t) => t.guest_id === g.id && (t.status === 'active' || t.status === 'used')) ||
      db.tickets.find((t) => t.guest_id === g.id);
    const reg = g.registration_id ? db.registrations.find((r) => r.id === g.registration_id) : null;
    const parent = g.parent_guest_id ? db.guests.find((p) => p.id === g.parent_guest_id) : null;
    return {
      guest_id: g.id,
      full_name: g.full_name,
      email: g.email,
      phone: g.phone,
      guest_type: g.guest_type,
      organization: g.organization,
      job_title: g.job_title,
      rsvp_status: g.rsvp_status,
      checkin_status: g.checkin_status,
      checked_in_at: g.checked_in_at,
      ticket_id: tkt ? tkt.id : null,
      ticket_reference: tkt ? tkt.ticket_reference : null,
      ticket_status: tkt ? tkt.status : null,
      reference_code: reg ? reg.reference_code : 'VIP-DIRECT',
      review_status: reg ? reg.review_status : 'approved',
      parent_name: parent ? parent.full_name : null,
    };
  });

  if (q) {
    list = list.filter(
      (item) =>
        item.full_name.toLowerCase().includes(q) ||
        (item.email && item.email.toLowerCase().includes(q)) ||
        (item.ticket_reference && item.ticket_reference.toLowerCase().includes(q)) ||
        (item.phone && item.phone.toLowerCase().includes(q)) ||
        (item.organization && item.organization.toLowerCase().includes(q)) ||
        (item.reference_code && item.reference_code.toLowerCase().includes(q))
    );
  }

  if (statusFilter === 'confirmed') {
    list = list.filter((item) => item.rsvp_status === 'confirmed');
  } else if (statusFilter === 'pending_checkin') {
    list = list.filter((item) => item.rsvp_status === 'confirmed' && item.checkin_status !== 'checked_in');
  } else if (statusFilter === 'checked_in') {
    list = list.filter((item) => item.checkin_status === 'checked_in');
  }

  res.json({
    query: q,
    count: list.length,
    results: list,
  });
});

// Simultaneous 2-Device Race Condition Simulator Endpoint (Spec Section 9 & 15.11)
app.post('/api/tarbwy/v1/checkin/simulate-concurrent-scan', (req, res) => {
  const rawToken = String(req.body.qr_token || '').trim();
  const tkt = resolveScannedTicket(rawToken);
  const guest = tkt ? db.guests.find((g) => g.id === tkt.guest_id) : undefined;

  const device1Result = executeAtomicCheckin(tkt, guest, 'qr', 'Scanner-Device-A');
  const device2Result = executeAtomicCheckin(tkt, guest, 'qr', 'Scanner-Device-B');

  res.json({
    deviceA: { device: 'Scanner-Device-A (البوابة الرئيسية)', httpStatus: device1Result.status, ...device1Result.body },
    deviceB: { device: 'Scanner-Device-B (البوابة الفرعية — في نفس اللحظة)', httpStatus: device2Result.status, ...device2Result.body },
  });
});

// 7. POST /api/tarbwy/v1/checkin/manual
app.post('/api/tarbwy/v1/checkin/manual', (req, res) => {
  let guestId = Number(req.body.guest_id || 0);
  const ticketRef = String(req.body.ticket_reference || '').trim();
  const deviceLabel = String(req.body.device_label || 'Manual-Desk');

  if (!guestId && ticketRef) {
    const foundTkt = db.tickets.find((t) => t.ticket_reference.toUpperCase() === ticketRef.toUpperCase());
    if (foundTkt) guestId = foundTkt.guest_id;
  }

  const guest = db.guests.find((g) => g.id === guestId);
  let tkt = guest ? db.tickets.find((t) => t.guest_id === guest.id && (t.status === 'active' || t.status === 'used')) : undefined;
  if (guest && !tkt && guest.rsvp_status === 'confirmed') {
    tkt = issueTicketForGuest(guest.id, false) || undefined;
  }
  const result = executeAtomicCheckin(tkt, guest, 'manual', deviceLabel);
  res.status(result.status).json(result.body);
});

// 8. POST /api/tarbwy/v1/tickets/:guestId/reissue (Revoke Old & Reissue New QR)
app.post('/api/tarbwy/v1/tickets/:guestId/reissue', (req, res) => {
  const guestId = Number(req.params.guestId);
  const guest = db.guests.find((g) => g.id === guestId);
  if (!guest || guest.rsvp_status !== 'confirmed') {
    res.status(422).json({ message: 'لا يمكن إعادة إصدار تذكرة لضيف غير مؤكد الحضور.' });
    return;
  }
  const newTkt = issueTicketForGuest(guestId, true);
  res.json({
    ticket: newTkt,
    message: 'تم إبطال التذكرة السابقة وإصدار تذكرة جديدة برمز QR جديد بنجاح.',
  });
});

// 9. POST /api/tarbwy/v1/invitations/create (Create Single VIP Invitation + Optional Email Dispatch)
app.post('/api/tarbwy/v1/invitations/create', (req, res) => {
  const fullName = String(req.body.full_name || '').trim();
  const email = String(req.body.email || '').trim();
  const phone = String(req.body.phone || '').trim();
  const organization = String(req.body.organization || '').trim();
  const jobTitle = String(req.body.job_title || '').trim();
  const maxComp = req.body.max_companions !== undefined ? Number(req.body.max_companions) : 2;
  const sendNow = Boolean(req.body.send_email_now ?? true);

  if (!fullName || !email.includes('@')) {
    res.status(422).json({ message: 'يرجى إدخال اسم ضيف VIP والبريد الإلكتروني الصحيح.' });
    return;
  }

  const ts = nowIso();
  const gid = seq.guest++;
  const pair = generateTokenPair(24);

  const guest: GuestRecord = {
    id: gid,
    event_id: 1,
    registration_id: null,
    parent_guest_id: null,
    guest_type: 'vip',
    full_name: fullName,
    email,
    phone,
    organization,
    job_title: jobTitle,
    rsvp_status: 'pending_response',
    checkin_status: 'not_checked_in',
    checked_in_at: null,
    checked_in_by: null,
    dietary_requirements: '',
    accessibility_requirements: '',
    admin_notes: 'VIP مضاف من لوحة الإدارة',
    created_at: ts,
  };
  db.guests.unshift(guest);

  const inv: InvitationRecord = {
    id: seq.inv++,
    event_id: 1,
    guest_id: gid,
    token_hash: pair.hash,
    demo_raw_token_preview: pair.raw,
    max_companions_override: maxComp,
    status: 'active',
    expires_at: null,
    last_accessed_at: null,
    sent_at: sendNow ? ts : null,
    created_at: ts,
  };
  db.invitations.unshift(inv);

  if (sendNow) {
    enqueueEmail(gid, 'vip_invitation', email, `دعوة خاصة لحضور ${db.event.title}`, true);
  }
  logActivity('vip_invitation_created', 'invitation', inv.id, { guest_id: gid, email_sent: sendNow });

  res.status(201).json({
    invitation: { ...inv, guest },
    message: `تمت إضافة دعوة VIP لـ (${fullName}) وحفظها في قاعدة البيانات ${sendNow ? 'وإرسال بريد الدعوة' : ''} بنجاح.`,
  });
});

// 9b. POST /api/tarbwy/v1/invitations/:id/edit (Edit VIP Invitation Details)
app.post('/api/tarbwy/v1/invitations/:id/edit', (req, res) => {
  const id = Number(req.params.id);
  const inv = db.invitations.find((i) => i.id === id);
  if (!inv) {
    res.status(404).json({ message: 'الدعوة غير موجودة.' });
    return;
  }
  const guest = db.guests.find((g) => g.id === inv.guest_id);
  if (guest) {
    if (req.body.full_name) guest.full_name = String(req.body.full_name).trim();
    if (req.body.email) guest.email = String(req.body.email).trim();
    if (req.body.phone !== undefined) guest.phone = String(req.body.phone).trim();
    if (req.body.organization !== undefined) guest.organization = String(req.body.organization).trim();
    if (req.body.job_title !== undefined) guest.job_title = String(req.body.job_title).trim();
  }
  if (req.body.max_companions !== undefined) {
    inv.max_companions_override = Number(req.body.max_companions);
  }
  logActivity('vip_invitation_edited', 'invitation', id);
  res.json({ invitation: { ...inv, guest }, message: 'تم تحديث بيانات دعوة VIP بنجاح.' });
});

// 9c. POST /api/tarbwy/v1/invitations/:id/resend (Resend VIP Email WITHOUT Creating Duplicate Invitation or Duplicate QR)
app.post('/api/tarbwy/v1/invitations/:id/resend', (req, res) => {
  const id = Number(req.params.id);
  const inv = db.invitations.find((i) => i.id === id);
  if (!inv) {
    res.status(404).json({ message: 'الدعوة غير موجودة.' });
    return;
  }
  const guest = db.guests.find((g) => g.id === inv.guest_id);
  if (!guest) {
    res.status(404).json({ message: 'الضيف غير موجود.' });
    return;
  }
  if (inv.status === 'revoked' || inv.status === 'expired') {
    const pair = generateTokenPair(24);
    inv.token_hash = pair.hash;
    inv.demo_raw_token_preview = pair.raw;
    inv.status = 'active';
  }
  inv.sent_at = nowIso();
  enqueueEmail(guest.id, 'vip_invitation_resent', guest.email, `تذكير وإعادة إرسال دعوة VIP الخاصة لحضور ${db.event.title}`, true);
  logActivity('vip_invitation_resent', 'invitation', id, { guest_id: guest.id });
  res.json({
    invitation: { ...inv, guest },
    message: `تمت إعادة إرسال دعوة VIP إلى (${guest.email}) دون تكرار السجل أو رمز QR.`,
  });
});

// 9d. POST /api/tarbwy/v1/invitations/:id/delete (Delete VIP Invitation + Cascading Cleanup)
app.post('/api/tarbwy/v1/invitations/:id/delete', (req, res) => {
  const id = Number(req.params.id);
  const invIdx = db.invitations.findIndex((i) => i.id === id);
  if (invIdx === -1) {
    res.status(404).json({ message: 'الدعوة غير موجودة.' });
    return;
  }
  const inv = db.invitations[invIdx];
  const guest = db.guests.find((g) => g.id === inv.guest_id);
  revokeTicketsForGuest(inv.guest_id);
  const compIds = db.guests.filter((g) => g.parent_guest_id === inv.guest_id).map((g) => g.id);
  for (const cid of compIds) {
    revokeTicketsForGuest(cid);
  }
  db.tickets = db.tickets.filter((t) => t.guest_id !== inv.guest_id && !compIds.includes(t.guest_id));
  db.guests = db.guests.filter((g) => g.id !== inv.guest_id && g.parent_guest_id !== inv.guest_id);
  db.invitations.splice(invIdx, 1);
  logActivity('vip_invitation_deleted', 'invitation', id, { guest_name: guest ? guest.full_name : '' });
  res.json({ message: 'تم حذف دعوة VIP وإلغاء جميع البطاقات المرتبطة بها بنجاح.' });
});

// 9e. POST /api/tarbwy/v1/invitations/import (VIP CSV Import with Preview & Dry-Run)
app.post('/api/tarbwy/v1/invitations/import', (req, res) => {
  const csvContent = String(req.body.csv_content || '').replace(/^\uFEFF/, '');
  const dryRun = Boolean(req.body.dry_run);
  const lines = csvContent.split(/\r\n|\r|\n/).filter((l) => l.trim().length > 0);

  if (lines.length < 2) {
    res.status(422).json({ message: 'ملف CSV فارغ أو لا يحتوي على صفوف بعد الترويسة.' });
    return;
  }

  const headers = lines[0].split(',').map((h) => h.trim().toLowerCase());
  const preview: Array<Record<string, unknown>> = [];
  const errors: string[] = [];
  const warnings: string[] = [];

  for (let i = 1; i < lines.length; i++) {
    const cols = lines[i].split(',').map((c) => c.trim());
    const row: Record<string, string> = {};
    headers.forEach((h, idx) => {
      row[h] = cols[idx] || '';
    });

    const fullName = row.full_name || row.name || '';
    const email = row.email || '';
    const phone = row.phone || '';
    const organization = row.organization || '';
    const jobTitle = row.job_title || '';
    const maxComp = row.max_companions ? Number(row.max_companions) : 2;

    if (!fullName || !email.includes('@')) {
      errors.push(`الصف ${i + 1}: الاسم الكامل أو البريد الإلكتروني غير صالح.`);
      continue;
    }

    const dup = db.guests.some((g) => g.email.toLowerCase() === email.toLowerCase());
    if (dup) {
      warnings.push(`الصف ${i + 1}: تنبيه — البريد (${email}) مسجل مسبقاً لضيف آخر في الحدث.`);
    }

    if (!dryRun) {
      const ts = nowIso();
      const gid = seq.guest++;
      const pair = generateTokenPair(24);
      db.guests.unshift({
        id: gid,
        event_id: 1,
        registration_id: null,
        parent_guest_id: null,
        guest_type: 'vip',
        full_name: fullName,
        email,
        phone,
        organization,
        job_title: jobTitle,
        rsvp_status: 'pending_response',
        checkin_status: 'not_checked_in',
        checked_in_at: null,
        checked_in_by: null,
        dietary_requirements: '',
        accessibility_requirements: '',
        admin_notes: 'مستورد عبر CSV',
        created_at: ts,
      });
      db.invitations.unshift({
        id: seq.inv++,
        event_id: 1,
        guest_id: gid,
        token_hash: pair.hash,
        demo_raw_token_preview: pair.raw,
        max_companions_override: maxComp,
        status: 'active',
        expires_at: null,
        last_accessed_at: null,
        sent_at: null,
        created_at: ts,
      });
    }

    preview.push({ row_number: i + 1, full_name: fullName, email, phone, organization, job_title: jobTitle, max_companions: maxComp });
  }

  if (!dryRun && preview.length > 0) {
    logActivity('vip_csv_imported', 'invitation', 0, { imported_count: preview.length });
  }

  res.json({
    dry_run: dryRun,
    total_rows: lines.length - 1,
    valid_rows: preview.length,
    invalid_rows: errors.length,
    warnings,
    errors,
    preview,
  });
});

// 10. POST /api/tarbwy/v1/invitations/:id/revoke
app.post('/api/tarbwy/v1/invitations/:id/revoke', (req, res) => {
  const id = Number(req.params.id);
  const inv = db.invitations.find((i) => i.id === id);
  if (!inv) {
    res.status(404).json({ message: 'الدعوة غير موجودة.' });
    return;
  }
  inv.status = 'revoked';
  revokeTicketsForGuest(inv.guest_id);
  logActivity('vip_invitation_revoked', 'invitation', id);
  res.json({ message: 'تم إبطال رابط الدعوة وإلغاء أي بطاقة QR مرتبطة بها بنجاح.' });
});

// 11. POST /api/tarbwy/v1/invitations/:id/reissue
app.post('/api/tarbwy/v1/invitations/:id/reissue', (req, res) => {
  const id = Number(req.params.id);
  const inv = db.invitations.find((i) => i.id === id);
  if (!inv) {
    res.status(404).json({ message: 'الدعوة غير موجودة.' });
    return;
  }
  const pair = generateTokenPair(24);
  inv.token_hash = pair.hash;
  inv.demo_raw_token_preview = pair.raw;
  inv.status = 'active';
  inv.sent_at = nowIso();
  logActivity('vip_invitation_reissued', 'invitation', id);
  res.json({ invitation: inv, message: 'تم إصدار رمز دعوة جديد وإبطال الرمز السابق بنجاح.' });
});

// 12. POST /api/tarbwy/v1/settings
app.post('/api/tarbwy/v1/settings', (req, res) => {
  Object.assign(db.event, req.body);
  logActivity('event_settings_updated', 'settings', 1);
  res.json({ event: db.event, message: 'تم حفظ إعدادات الحدث بنجاح.' });
});

// 12a. POST /api/tarbwy/v1/reset-data (Wipe all test data before launch with password "tarbwy")
app.post('/api/tarbwy/v1/reset-data', (req, res) => {
  const password = String(req.body.password || '').trim();
  if (password !== 'tarbwy') {
    res.status(403).json({
      code: 'invalid_reset_password',
      message: 'كلمة سر التصفير غير صحيحة. يرجى إدخال كلمة السر المعتمدة لتأكيد تصفير كافة البيانات.',
    });
    return;
  }

  db.registrations = [];
  db.guests = [];
  db.invitations = [];
  db.tickets = [];
  db.checkins = [];
  db.emailLogs = [];
  db.activityLogs = [];

  seq.reg = 1;
  seq.guest = 1;
  seq.inv = 1;
  seq.tkt = 1;
  seq.chk = 1;
  seq.mail = 1;
  seq.act = 1;

  logActivity('event_data_reset_for_launch', 'system', 1, { timestamp: nowIso() });

  res.json({
    status: 'reset_completed',
    message:
      'تم تصفير جميع بيانات التجربة (الطلبات، الضيوف، المرافقون، دعوات VIP، بطاقات QR، سجلات الدخول، وسجل البريد) وإعادة ترقيم الجداول من (1) بنجاح مع الحفاظ على إعدادات الحدث.',
  });
});

// 12b. POST /api/tarbwy/v1/staff/assign (Assign, Change, or Revert Staff Member Role & Capabilities)
app.post('/api/tarbwy/v1/staff/assign', (req, res) => {
  const userId = req.body.user_id ? Number(req.body.user_id) : 0;
  const role = String(req.body.role || 'tarbwy_event_admin') as StaffUserRecord['role'];
  const displayName = String(req.body.display_name || '').trim();
  const email = String(req.body.email || '').trim();
  const userLogin = String(req.body.user_login || '').trim();

  if (!ROLE_DEFINITIONS[role]) {
    res.status(422).json({ message: 'الدور المحدد غير صالح.' });
    return;
  }

  if (userId > 0) {
    const existing = db.staffUsers.find((u) => u.id === userId);
    if (!existing) {
      res.status(404).json({ message: 'المستخدم غير موجود.' });
      return;
    }
    existing.role = role;
    existing.role_label = ROLE_DEFINITIONS[role].label;
    // WordPress administrators always retain full capabilities even if event role is changed/reverted
    existing.capabilities =
      existing.wp_role === 'administrator'
        ? ROLE_DEFINITIONS.tarbwy_event_admin.capabilities
        : ROLE_DEFINITIONS[role].capabilities;
    logActivity('staff_role_assigned', 'user', existing.id, { role, wp_role_preserved: existing.wp_role });
    res.json({
      staffUser: existing,
      message: `تم تحديث وحفظ صلاحيات المستخدم (${existing.display_name}) إلى دور (${existing.role_label}) مع الحفاظ على دور WordPress الأساسي (${existing.wp_role}).`,
    });
    return;
  }

  if (!displayName || !email.includes('@')) {
    res.status(422).json({ message: 'يرجى إدخال اسم الموظف وبريده الإلكتروني لإضافته وتعيين دوره.' });
    return;
  }

  const newStaff: StaffUserRecord = {
    id: seq.staff++,
    display_name: displayName,
    user_login: userLogin || email.split('@')[0],
    email,
    wp_role: 'subscriber',
    role,
    role_label: ROLE_DEFINITIONS[role].label,
    capabilities: ROLE_DEFINITIONS[role].capabilities,
  };
  db.staffUsers.push(newStaff);
  logActivity('staff_user_created', 'user', newStaff.id, { role });

  res.status(201).json({
    staffUser: newStaff,
    message: `تمت إضافة الموظف (${newStaff.display_name}) وتعيين دوره (${newStaff.role_label}) بنجاح.`,
  });
});

// 12c. POST /api/tarbwy/v1/emails/send-single (Send Email Per Person)
app.post('/api/tarbwy/v1/emails/send-single', (req, res) => {
  const guestId = Number(req.body.guest_id);
  const templateKey = String(req.body.template_key || 'ticket_issued');
  const guest = db.guests.find((g) => g.id === guestId);

  if (!guest) {
    res.status(404).json({ message: 'الضيف المحدد غير موجود.' });
    return;
  }

  const parent = guest.parent_guest_id ? db.guests.find((p) => p.id === guest.parent_guest_id) : null;
  const recipientEmail = guest.email || (parent ? parent.email : '');

  if (!recipientEmail || !recipientEmail.includes('@')) {
    res.status(422).json({ message: 'لا يوجد بريد إلكتروني مسجل لهذا الضيف أو للضيف الأساسي المرتبط به.' });
    return;
  }

  const baseSubject = EMAIL_TEMPLATE_SUBJECTS[templateKey] || 'إشعار من فعاليات تربوي';
  const subject = `${baseSubject} — (${guest.full_name})`;
  enqueueEmail(guest.id, templateKey, recipientEmail, subject, true);
  logActivity('single_email_dispatched', 'email', guest.id, { template_key: templateKey });

  res.json({
    guest_id: guest.id,
    recipient_email: recipientEmail,
    template_key: templateKey,
    message: `تم إرسال البريد الفردي (${baseSubject}) إلى (${guest.full_name}) عبر (${recipientEmail}) بنجاح.`,
  });
});

// 12d. POST /api/tarbwy/v1/emails/send-bulk (Send Bulk Email to Segment)
app.post('/api/tarbwy/v1/emails/send-bulk', (req, res) => {
  const segment = String(req.body.target_segment || 'confirmed');
  const templateKey = String(req.body.template_key || 'event_reminder');

  let targetGuests = [...db.guests];
  if (segment === 'confirmed') {
    targetGuests = targetGuests.filter((g) => g.rsvp_status === 'confirmed');
  } else if (segment === 'checked_in') {
    targetGuests = targetGuests.filter((g) => g.checkin_status === 'checked_in');
  } else if (segment === 'vip_pending') {
    targetGuests = targetGuests.filter((g) => g.guest_type === 'vip' && g.rsvp_status === 'pending_response');
  } else if (segment === 'all_vip') {
    targetGuests = targetGuests.filter((g) => g.guest_type === 'vip');
  } else if (segment === 'waitlist') {
    const waitlistRegIds = db.registrations.filter((r) => r.review_status === 'waitlist').map((r) => r.id);
    targetGuests = targetGuests.filter((g) => g.registration_id !== null && waitlistRegIds.includes(g.registration_id));
  }

  const baseSubject = EMAIL_TEMPLATE_SUBJECTS[templateKey] || 'إشعار جماعي من فعاليات تربوي';
  let sentCount = 0;

  for (const g of targetGuests) {
    const parent = g.parent_guest_id ? db.guests.find((p) => p.id === g.parent_guest_id) : null;
    const recipientEmail = g.email || (parent ? parent.email : '');
    if (!recipientEmail || !recipientEmail.includes('@')) continue;

    enqueueEmail(g.id, templateKey, recipientEmail, `${baseSubject} — (${g.full_name})`, true);
    sentCount++;
  }

  logActivity('bulk_email_dispatched', 'email_bulk', 0, {
    target_segment: segment,
    template_key: templateKey,
    sent_count: sentCount,
  });

  res.json({
    target_segment: segment,
    template_key: templateKey,
    sent_count: sentCount,
    message: `تمت جدولة وإرسال البريد الجماعي (${baseSubject}) إلى (${sentCount}) شخص من الفئة المحددة بنجاح.`,
  });
});

// 12e. POST /api/tarbwy/v1/emails/templates (Save Updated Email Template Subject/Heading/Badge/Body/CTA)
app.post('/api/tarbwy/v1/emails/templates', (req, res) => {
  const { template_key, subject, heading, badge, body, cta_label } = req.body;
  if (template_key && db.emailTemplates[template_key]) {
    db.emailTemplates[template_key] = {
      ...db.emailTemplates[template_key],
      subject: String(subject || db.emailTemplates[template_key].subject),
      heading: String(heading || db.emailTemplates[template_key].heading),
      badge: String(badge || db.emailTemplates[template_key].badge),
      body: String(body || db.emailTemplates[template_key].body),
      cta_label: String(cta_label ?? db.emailTemplates[template_key].cta_label),
    };
    EMAIL_TEMPLATE_SUBJECTS[template_key] = db.emailTemplates[template_key].subject;
    logActivity('email_template_updated', 'email_template', 1, { template_key });
  }
  res.json({
    emailTemplates: db.emailTemplates,
    message: `تم حفظ تعديلات قالب البريد (${template_key}) بنجاح.`,
  });
});

// 12f. POST /api/tarbwy/v1/emails/restore-defaults (Restore All 14 Default Email Templates)
app.post('/api/tarbwy/v1/emails/restore-defaults', (_req, res) => {
  db.emailTemplates = JSON.parse(JSON.stringify(DEFAULT_EMAIL_TEMPLATES));
  logActivity('email_templates_restored_default', 'email_templates', 1);
  res.json({
    emailTemplates: db.emailTemplates,
    message: 'تمت استعادة القوالب الافتراضية الـ 14 بنجاح.',
  });
});

// 12g. POST /api/tarbwy/v1/emails/send-test (Send Test Email with Sample Placeholders & Verified Sender Identity)
app.post('/api/tarbwy/v1/emails/send-test', (req, res) => {
  const recipientEmail = String(req.body.test_email || db.event.reply_to_email || 'events@tarbwy.org').trim();
  const templateKey = String(req.body.template_key || 'ticket_issued');
  if (!recipientEmail.includes('@')) {
    res.status(422).json({ message: 'يرجى إدخال عنوان بريد إلكتروني صالح لإرسال الرسالة التجريبية.' });
    return;
  }
  const tpl = db.emailTemplates[templateKey] || db.emailTemplates.ticket_issued;
  const senderIdentity = `"${db.event.sender_name}" <${db.event.sender_email}>`;
  const subject = `[رسالة تجريبية] ${tpl.subject.replace('{event_title}', db.event.title).replace('{guest_name}', 'معالي أ.د. خالد بن منصور العتيبي')}`;
  enqueueEmail(1, templateKey, recipientEmail, subject, true);
  logActivity('test_email_sent', 'email_test', 1, {
    template_key: templateKey,
    sender_identity: senderIdentity,
    reply_to: db.event.reply_to_email,
  });
  res.json({
    recipient_email: recipientEmail,
    template_key: templateKey,
    sender_identity: senderIdentity,
    reply_to: db.event.reply_to_email,
    message: `تم إرسال الرسالة التجريبية لقالب (${tpl.title}) إلى (${recipientEmail}) بهوية المرسل المعتمدة ${senderIdentity} بنجاح.`,
  });
});

// 13. GET /api/tarbwy/v1/export-csv & Multi-Format Report Export (CSV, Excel .xls, Printable HTML/PDF across all 5 categories)
function filterReportGuests(category: string, regType = 'all', search = '') {
  let list = [...db.guests];
  if (category === 'approved') {
    list = list.filter((g) => {
      if (g.registration_id) {
        const r = db.registrations.find((reg) => reg.id === g.registration_id);
        return r?.review_status === 'approved' && g.rsvp_status === 'confirmed';
      }
      return g.rsvp_status === 'confirmed';
    });
  } else if (category === 'declined') {
    list = list.filter((g) => {
      if (g.registration_id) {
        const r = db.registrations.find((reg) => reg.id === g.registration_id);
        return r?.review_status === 'declined' || g.rsvp_status === 'declined';
      }
      return g.rsvp_status === 'declined';
    });
  } else if (category === 'waitlist') {
    list = list.filter((g) => {
      if (!g.registration_id) return false;
      const r = db.registrations.find((reg) => reg.id === g.registration_id);
      return r?.review_status === 'waitlist';
    });
  } else if (category === 'pending') {
    list = list.filter((g) => {
      if (g.registration_id) {
        const r = db.registrations.find((reg) => reg.id === g.registration_id);
        return r?.review_status === 'pending';
      }
      return g.rsvp_status === 'pending_response';
    });
  } else if (category === 'confirmed') {
    list = list.filter((g) => g.rsvp_status === 'confirmed');
  } else if (category === 'checked_in') {
    list = list.filter((g) => g.checkin_status === 'checked_in');
  } else if (category === 'no_show') {
    list = list.filter((g) => g.rsvp_status === 'confirmed' && g.checkin_status === 'not_checked_in');
  } else if (category === 'vip') {
    list = list.filter((g) => g.guest_type === 'vip');
  } else if (category === 'companions') {
    list = list.filter((g) => g.guest_type === 'companion');
  }

  if (regType === 'vip') {
    list = list.filter((g) => g.guest_type === 'vip');
  } else if (regType === 'standard') {
    list = list.filter((g) => g.guest_type !== 'vip');
  }

  if (search.trim()) {
    const q = search.trim().toLowerCase();
    list = list.filter(
      (g) =>
        g.full_name.toLowerCase().includes(q) ||
        g.email.toLowerCase().includes(q) ||
        g.phone.toLowerCase().includes(q) ||
        g.organization.toLowerCase().includes(q)
    );
  }
  return list;
}

app.get('/api/tarbwy/v1/export-csv', (req, res) => {
  const filter = String(req.query.filter || req.query.category || 'all');
  const format = String(req.query.format || 'csv').toLowerCase();
  const regType = String(req.query.reg_type || 'all');
  const search = String(req.query.search || '');
  const list = filterReportGuests(filter, regType, search);

  const headers = [
    'معرف الضيف (Guest ID)',
    'رقم الطلب (Ref Code)',
    'الفئة (Type)',
    'الاسم الكامل (Full Name)',
    'المؤسسة (Organization)',
    'المسمى الوظيفي (Job Title)',
    'البريد الإلكتروني (Email)',
    'الهاتف (Phone)',
    'حالة الطلب (Review Status)',
    'حالة التأكيد (RSVP Status)',
    'رقم التذكرة (Ticket Ref)',
    'حالة QR (Ticket Status)',
    'حالة الدخول (Check-in Status)',
    'وقت الدخول (Checked-in At)',
  ];

  if (format === 'excel') {
    let html = `<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40" dir="rtl" lang="ar">
<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head>
<body dir="rtl"><table border="1"><thead><tr style="background:#0B3B2B;color:#fff;font-weight:bold;">`;
    for (const h of headers) html += `<th>${h}</th>`;
    html += `</tr></thead><tbody>`;
    for (const g of list) {
      const reg = g.registration_id ? db.registrations.find((r) => r.id === g.registration_id) : null;
      const tkt = db.tickets.find((t) => t.guest_id === g.id && (t.status === 'active' || t.status === 'used'));
      const cols = [
        g.id,
        sanitizeCsvCell(reg ? reg.reference_code : 'VIP-DIRECT'),
        sanitizeCsvCell(g.guest_type),
        sanitizeCsvCell(g.full_name),
        sanitizeCsvCell(g.organization),
        sanitizeCsvCell(g.job_title),
        sanitizeCsvCell(g.email),
        sanitizeCsvCell(g.phone),
        sanitizeCsvCell(reg ? reg.review_status : 'vip'),
        sanitizeCsvCell(g.rsvp_status),
        sanitizeCsvCell(tkt ? tkt.ticket_reference : '-'),
        sanitizeCsvCell(tkt ? tkt.status : 'none'),
        sanitizeCsvCell(g.checkin_status),
        sanitizeCsvCell(g.checked_in_at || '-'),
      ];
      html += `<tr>${cols.map((c) => `<td>${c}</td>`).join('')}</tr>`;
    }
    html += `</tbody></table></body></html>`;
    logActivity('report_excel_exported', 'export', 0, { filter, row_count: list.length });
    res.setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    res.setHeader('Content-Disposition', `attachment; filename="tarbwy-report-${filter}.xls"`);
    res.send('\uFEFF' + html);
    return;
  }

  if (format === 'print' || format === 'pdf') {
    let html = `<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="UTF-8"><title>تقرير حضور تربوي — ${filter}</title>
<style>body{font-family:Tahoma,Arial,sans-serif;direction:rtl;margin:24px;color:#111827}table{width:100%;border-collapse:collapse;font-size:12px}th{background:#0B3B2B;color:#fff;padding:8px;border:1px solid #0B3B2B}td{padding:6px 8px;border:1px solid #d1d5db}</style></head>
<body><div style="margin-bottom:14px;"><button onclick="window.print()" style="padding:8px 16px;background:#0B3B2B;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:bold;">طباعة أو حفظ كملف PDF (Print / Save as PDF)</button></div>
<h2>${db.event.title} — التقرير (${filter}) — إجمالي السجلات: ${list.length}</h2>
<table><thead><tr>${headers.map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>`;
    for (const g of list) {
      const reg = g.registration_id ? db.registrations.find((r) => r.id === g.registration_id) : null;
      const tkt = db.tickets.find((t) => t.guest_id === g.id && (t.status === 'active' || t.status === 'used'));
      const cols = [
        g.id,
        sanitizeCsvCell(reg ? reg.reference_code : 'VIP-DIRECT'),
        sanitizeCsvCell(g.guest_type),
        sanitizeCsvCell(g.full_name),
        sanitizeCsvCell(g.organization),
        sanitizeCsvCell(g.job_title),
        sanitizeCsvCell(g.email),
        sanitizeCsvCell(g.phone),
        sanitizeCsvCell(reg ? reg.review_status : 'vip'),
        sanitizeCsvCell(g.rsvp_status),
        sanitizeCsvCell(tkt ? tkt.ticket_reference : '-'),
        sanitizeCsvCell(tkt ? tkt.status : 'none'),
        sanitizeCsvCell(g.checkin_status),
        sanitizeCsvCell(g.checked_in_at || '-'),
      ];
      html += `<tr>${cols.map((c) => `<td>${c}</td>`).join('')}</tr>`;
    }
    html += `</tbody></table></body></html>`;
    logActivity('report_print_exported', 'export', 0, { filter, format, row_count: list.length });
    res.setHeader('Content-Type', 'text/html; charset=UTF-8');
    res.send(html);
    return;
  }

  const rows = [headers.join(',')];
  for (const g of list) {
    const reg = g.registration_id ? db.registrations.find((r) => r.id === g.registration_id) : null;
    const tkt = db.tickets.find((t) => t.guest_id === g.id && (t.status === 'active' || t.status === 'used'));
    const cols = [
      g.id,
      sanitizeCsvCell(reg ? reg.reference_code : 'VIP-DIRECT'),
      sanitizeCsvCell(g.guest_type),
      sanitizeCsvCell(g.full_name),
      sanitizeCsvCell(g.organization),
      sanitizeCsvCell(g.job_title),
      sanitizeCsvCell(g.email),
      sanitizeCsvCell(g.phone),
      sanitizeCsvCell(reg ? reg.review_status : 'vip'),
      sanitizeCsvCell(g.rsvp_status),
      sanitizeCsvCell(tkt ? tkt.ticket_reference : '-'),
      sanitizeCsvCell(tkt ? tkt.status : 'none'),
      sanitizeCsvCell(g.checkin_status),
      sanitizeCsvCell(g.checked_in_at || '-'),
    ].map((c) => `"${String(c).replace(/"/g, '""')}"`);
    rows.push(cols.join(','));
  }

  logActivity('report_csv_exported', 'export', 0, { filter, row_count: list.length });

  const csvWithBom = '\uFEFF' + rows.join('\r\n');
  res.setHeader('Content-Type', 'text/csv; charset=UTF-8');
  res.setHeader('Content-Disposition', `attachment; filename="tarbwy-guests-${filter}.csv"`);
  res.send(csvWithBom);
});

async function startServer() {
  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(__dirname, 'dist');
    app.use(express.static(distPath));
    app.get('*', (_req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`Tarbwy Event Manager Server running on http://localhost:${PORT}`);
  });
}

startServer();
