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
  contact_email: string;
  contact_phone: string;
  entry_instructions: string;
  schedule_items: Array<{ time: string; title: string; desc: string }>;
  speakers: Array<{ name: string; role: string }>;
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

// Helpers matching PHP Security class
function sha256(input: string): string {
  return crypto.createHash('sha256').update(input.trim()).digest('hex');
}

function generateTokenPair(bytes = 20): { raw: string; hash: string } {
  const raw = crypto.randomBytes(bytes).toString('hex');
  return { raw, hash: sha256(raw) };
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
    title: 'حفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية»',
    slug: 'launch',
    status: 'published',
    event_start_at: null,
    event_end_at: null,
    timezone: 'Asia/Riyadh',
    venue_name: 'سيُعلن لاحقاً (قابل للتحرير من إعدادات الحدث)',
    venue_address: 'سيتم تحديد العنوان التفصيلي ومعلومات الوصول من قِبل إدارة الحدث',
    map_url: '',
    capacity_total: 500,
    max_companions_public: 2,
    max_companions_vip: 3,
    approval_auto_confirms: true,
    registration_open: true,
    rsvp_deadline: 'سيُعلن لاحقاً',
    subtitle: 'حفل الإشهار الرسمي وإطلاق المبادرات القيادية التربوية في العالم العربي',
    date_display: 'سيُعلن لاحقاً (قابل للتحرير من لوحة الإدارة)',
    city_display: 'سيُعلن لاحقاً',
    about_event:
      'يمثل حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية» محطة تأسيسية تجمع نخبة القيادات التربوية وصناع القرار والخبراء الأكاديميين لإطلاق منظومة عمل مؤسسية مشتركة تهدف إلى الارتقاء بالقيادة التعليمية في العالم العربي.',
    about_tarbwy:
      '«تربوي — الاتحاد العربي للقيادة التربوية» مظلة مهنية ومؤسسية تعنى بتمكين القيادات التربوية، وتطوير السياسات التعليمية، وبناء شبكات التعاون المعرفي والمهني بين المؤسسات التربوية العربية.',
    contact_email: 'events@tarbwy.org',
    contact_phone: 'سيُعلن لاحقاً',
    entry_instructions:
      'يرجى إبراز رمز الاستجابة السريعة (QR) الخاص بك عند بوابة الاستقبال. هذه البطاقة شخصية ومخصصة لحاملها فقط، ولكل مرافق بطاقة مستقلة.',
    schedule_items: [],
    speakers: [],
  } as EventRecord,

  registrations: [] as RegistrationRecord[],
  guests: [] as GuestRecord[],
  invitations: [] as InvitationRecord[],
  tickets: [] as TicketRecord[],
  checkins: [] as CheckinRecord[],
  emailLogs: [] as EmailLogRecord[],
  activityLogs: [] as ActivityLogRecord[],
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

function issueTicketForGuest(guestId: number): TicketRecord | null {
  const guest = db.guests.find((g) => g.id === guestId);
  if (!guest || guest.rsvp_status !== 'confirmed') return null;

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
  logActivity('ticket_issued', 'ticket', ticket.id, { guest_id: guestId, ticket_reference: ticket.ticket_reference });
  return ticket;
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
}

seedInitialData();

// ============================================================================
// API ENDPOINTS (Mirrors WordPress REST API /wp-json/tarbwy/v1/* + Source Code Viewer)
// ============================================================================

// Download the real installable WordPress plugin ZIP
app.get('/api/plugin/download-zip', (_req, res) => {
  const zipPath = path.join(__dirname, 'tarbwy-event-manager.zip');
  if (fs.existsSync(zipPath)) {
    res.setHeader('Content-Type', 'application/zip');
    res.setHeader('Content-Disposition', 'attachment; filename="tarbwy-event-manager.zip"');
    fs.createReadStream(zipPath).pipe(res);
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
  const testData = fs.existsSync(testJsonPath) ? JSON.parse(fs.readFileSync(testJsonPath, 'utf-8')) : { tests: [], zipSize: 0 };

  res.json({
    files: fileList,
    tests: testData.tests,
    zipSize: testData.zipSize,
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
      return { ...inv, guest: g || null };
    }),
    tickets: db.tickets.map((t) => {
      const g = db.guests.find((x) => x.id === t.guest_id);
      return { ...t, guest: g || null };
    }),
    emailLogs: db.emailLogs.slice(0, 50),
    activityLogs: db.activityLogs.slice(0, 50),
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

// 2. POST /api/tarbwy/v1/registrations/:id/review (Admin Review + Partial Companion Approval)
app.post('/api/tarbwy/v1/registrations/:id/review', (req, res) => {
  const regId = Number(req.params.id);
  const reg = db.registrations.find((r) => r.id === regId);
  if (!reg) {
    res.status(404).json({ message: 'طلب التسجيل غير موجود.' });
    return;
  }

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

  if (decision === 'approved') {
    for (const g of groupGuests) {
      const isCompanion = g.parent_guest_id !== null;
      if (isCompanion && approvedCompanionIds !== null && !approvedCompanionIds.includes(g.id)) {
        g.rsvp_status = 'declined';
        continue;
      }
      g.rsvp_status = db.event.approval_auto_confirms ? 'confirmed' : 'pending_response';
      if (db.event.approval_auto_confirms) {
        const tkt = issueTicketForGuest(g.id);
        if (tkt) issuedTickets.push(tkt);
        const targetEmail = g.email || (primaryGuest ? primaryGuest.email : '');
        enqueueEmail(
          g.id,
          'registration_approved',
          targetEmail,
          `تأكيد حضوركم وبطاقة الدخول (${g.full_name}) — ${tkt ? tkt.ticket_reference : ''}`,
          true
        );
      }
    }
  } else if (decision === 'waitlist') {
    if (primaryGuest) {
      enqueueEmail(primaryGuest.id, 'registration_waitlist', primaryGuest.email, `إدراج طلبكم في قائمة الانتظار — ${reg.reference_code}`, true);
    }
  } else if (decision === 'declined' || decision === 'cancelled') {
    for (const g of groupGuests) {
      g.rsvp_status = 'declined';
    }
    if (primaryGuest) {
      enqueueEmail(primaryGuest.id, 'registration_declined', primaryGuest.email, `بشأن طلب التسجيل — ${reg.reference_code}`, true);
    }
  }

  logActivity('registration_reviewed', 'registration', regId, { decision, tickets_issued: issuedTickets.length });

  res.json({
    registration_id: regId,
    review_status: decision,
    issued_tickets: issuedTickets,
    message: 'تم تحديث حالة الطلب وإصدار التذاكر للمعتمدين بنجاح.',
  });
});

// 3. GET /api/tarbwy/v1/vip-invitations/:token (Verify VIP Token Hash)
app.get('/api/tarbwy/v1/vip-invitations/:token', (req, res) => {
  const rawToken = String(req.params.token || '').trim();
  const hash = sha256(rawToken);
  const inv = db.invitations.find((i) => i.token_hash === hash);
  if (!inv) {
    res.status(404).json({ code: 'invalid_token', message: 'رابط الدعوة غير صالح أو غير معروف.' });
    return;
  }
  if (inv.status === 'revoked') {
    res.status(410).json({ code: 'revoked_token', message: 'تم إبطال رابط الدعوة هذا وإصدار رابط بديل.' });
    return;
  }
  if (inv.status === 'expired') {
    res.status(410).json({ code: 'expired_token', message: 'انتهت صلاحية رابط الدعوة هذا.' });
    return;
  }

  inv.last_accessed_at = nowIso();
  const guest = db.guests.find((g) => g.id === inv.guest_id);
  if (!guest) {
    res.status(404).json({ message: 'بيانات المدعو غير موجودة.' });
    return;
  }

  res.json({
    invitation_id: inv.id,
    status: inv.status,
    rsvp_status: guest.rsvp_status,
    guest_name: guest.full_name,
    organization: guest.organization,
    job_title: guest.job_title,
    event_title: db.event.title,
    venue_name: db.event.venue_name,
    max_companions: inv.max_companions_override ?? db.event.max_companions_vip,
  });
});

// 4. POST /api/tarbwy/v1/vip-invitations/:token/rsvp (Submit VIP RSVP)
app.post('/api/tarbwy/v1/vip-invitations/:token/rsvp', (req, res) => {
  const rawToken = String(req.params.token || '').trim();
  const hash = sha256(rawToken);
  const inv = db.invitations.find((i) => i.token_hash === hash);
  if (!inv || inv.status !== 'active') {
    res.status(403).json({ message: 'رابط الدعوة غير صالح أو تم استخدامه/إبطاله مسبقاً.' });
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

  const mainTkt = issueTicketForGuest(guest.id);
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
    const cTkt = issueTicketForGuest(compId);
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

app.post('/api/tarbwy/v1/checkin/scan', (req, res) => {
  const rawToken = String(req.body.qr_token || '').trim();
  const deviceLabel = String(req.body.device_label || 'Terminal-1');
  if (!rawToken) {
    res.status(400).json({ result_code: 'unknown', message: 'رمز QR فارغ.' });
    return;
  }
  const hash = sha256(rawToken);
  const tkt = db.tickets.find((t) => t.token_hash === hash);
  const guest = tkt ? db.guests.find((g) => g.id === tkt.guest_id) : undefined;
  const result = executeAtomicCheckin(tkt, guest, 'qr', deviceLabel);
  res.status(result.status).json(result.body);
});

// Simultaneous 2-Device Race Condition Simulator Endpoint (Spec Section 9 & 15.11)
app.post('/api/tarbwy/v1/checkin/simulate-concurrent-scan', (req, res) => {
  const rawToken = String(req.body.qr_token || '').trim();
  const hash = sha256(rawToken);
  const tkt = db.tickets.find((t) => t.token_hash === hash);
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
  const guestId = Number(req.body.guest_id);
  const deviceLabel = String(req.body.device_label || 'Manual-Desk');
  const guest = db.guests.find((g) => g.id === guestId);
  const tkt = guest ? db.tickets.find((t) => t.guest_id === guest.id && (t.status === 'active' || t.status === 'used')) : undefined;
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
  const newTkt = issueTicketForGuest(guestId);
  res.json({
    ticket: newTkt,
    message: 'تم إبطال التذكرة السابقة وإصدار تذكرة جديدة برمز QR جديد بنجاح.',
  });
});

// 9. POST /api/tarbwy/v1/invitations/import (VIP CSV Import with Preview & Dry-Run)
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

    preview.push({ row_number: i + 1, full_name: fullName, email, organization, job_title: jobTitle, max_companions: maxComp });
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
  logActivity('vip_invitation_revoked', 'invitation', id);
  res.json({ message: 'تم إبطال رابط الدعوة بنجاح.' });
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

// 13. GET /api/tarbwy/v1/export-csv (UTF-8 BOM + Formula Injection Protection)
app.get('/api/tarbwy/v1/export-csv', (req, res) => {
  const filter = String(req.query.filter || 'all');
  let list = [...db.guests];
  if (filter === 'confirmed') list = list.filter((g) => g.rsvp_status === 'confirmed');
  else if (filter === 'checked_in') list = list.filter((g) => g.checkin_status === 'checked_in');
  else if (filter === 'no_show') list = list.filter((g) => g.rsvp_status === 'confirmed' && g.checkin_status === 'not_checked_in');
  else if (filter === 'vip') list = list.filter((g) => g.guest_type === 'vip');
  else if (filter === 'companions') list = list.filter((g) => g.guest_type === 'companion');

  const headers = [
    'معرف الضيف (Guest ID)',
    'الفئة (Type)',
    'الاسم الكامل (Full Name)',
    'المؤسسة (Organization)',
    'المسمى الوظيفي (Job Title)',
    'البريد الإلكتروني (Email)',
    'الهاتف (Phone)',
    'حالة التأكيد (RSVP Status)',
    'رقم التذكرة (Ticket Ref)',
    'حالة الدخول (Check-in Status)',
    'وقت الدخول (Checked-in At)',
  ];

  const rows = [headers.join(',')];
  for (const g of list) {
    const tkt = db.tickets.find((t) => t.guest_id === g.id && (t.status === 'active' || t.status === 'used'));
    const cols = [
      g.id,
      sanitizeCsvCell(g.guest_type),
      sanitizeCsvCell(g.full_name),
      sanitizeCsvCell(g.organization),
      sanitizeCsvCell(g.job_title),
      sanitizeCsvCell(g.email),
      sanitizeCsvCell(g.phone),
      sanitizeCsvCell(g.rsvp_status),
      sanitizeCsvCell(tkt ? tkt.ticket_reference : '-'),
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
