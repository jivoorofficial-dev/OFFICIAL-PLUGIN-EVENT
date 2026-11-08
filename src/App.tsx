import React, { useState, useEffect, useCallback } from 'react';
import {
  Download,
  ShieldCheck,
  QrCode,
  Users,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  FileSpreadsheet,
  Search,
  Printer,
  RefreshCw,
  FileCode2,
  Eye,
  Plus,
  Trash2,
  Settings,
  Mail,
} from 'lucide-react';
import { LocalQRCode } from './components/LocalQRCode';

type ActiveRoute = 'landing' | 'vip' | 'ticket' | 'checkin' | 'admin' | 'deliverables';
type AdminSubTab = 'overview' | 'requests' | 'guests' | 'vip' | 'tickets' | 'reports' | 'settings' | 'emails';
type StaffRole = 'event_admin' | 'registration_manager' | 'checkin_staff';

interface CompanionInput {
  full_name: string;
  email: string;
  job_title: string;
}

export default function App() {
  const [activeRoute, setActiveRoute] = useState<ActiveRoute>('landing');
  const [adminTab, setAdminTab] = useState<AdminSubTab>('overview');
  const [currentRole, setCurrentRole] = useState<StaffRole>('event_admin');

  // App state from backend
  const [stateData, setStateData] = useState<any>(null);
  const [pluginMeta, setPluginMeta] = useState<{
    files: any[];
    tests: any[];
    zipSize: number;
    zipSha256?: string;
    zipBase64?: string;
  }>({
    files: [],
    tests: [],
    zipSize: 0,
    zipSha256: '',
    zipBase64: '',
  });
  const [downloadingZip, setDownloadingZip] = useState(false);
  const [selectedFile, setSelectedFile] = useState<{ path: string; content: string } | null>(null);

  // Public registration state (/launch)
  const [regName, setRegName] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regPhone, setRegPhone] = useState('');
  const [regOrg, setRegOrg] = useState('');
  const [regTitle, setRegTitle] = useState('');
  const [regDietary, setRegDietary] = useState('');
  const [regHoneypot, setRegHoneypot] = useState('');
  const [regCompanions, setRegCompanions] = useState<CompanionInput[]>([]);
  const [regFeedback, setRegFeedback] = useState<{ ok: boolean; msg: string; ref?: string } | null>(null);
  const [regSubmitting, setRegSubmitting] = useState(false);

  // VIP RSVP state (/launch/vip)
  const [vipTokenInput, setVipTokenInput] = useState('');
  const [vipLoaded, setVipLoaded] = useState<any>(null);
  const [vipError, setVipError] = useState('');
  const [vipDecision, setVipDecision] = useState<'attend' | 'decline'>('attend');
  const [vipCompanions, setVipCompanions] = useState<CompanionInput[]>([]);
  const [vipResult, setVipResult] = useState<any>(null);

  // Ticket View state (/launch/ticket)
  const [activeTicketToken, setActiveTicketToken] = useState('');
  const [ticketViewData, setTicketViewData] = useState<any>(null);
  const [ticketViewError, setTicketViewError] = useState('');

  // Check-in state (/launch/checkin)
  const [scanTokenInput, setScanTokenInput] = useState('');
  const [checkinBanner, setCheckinBanner] = useState<any>(null);
  const [concurrentRaceResult, setConcurrentRaceResult] = useState<any>(null);
  const [checkinSearch, setCheckinSearch] = useState('');
  const [simulateOffline, setSimulateOffline] = useState(false);

  // Admin Guest Search & Filters
  const [guestSearch, setGuestSearch] = useState('');
  const [guestTypeFilter, setGuestTypeFilter] = useState('all');
  const [rsvpFilter, setRsvpFilter] = useState('all');

  // Admin Partial Companion Selection map: { [registrationId]: number[] }
  const [partialCompanionMap, setPartialCompanionMap] = useState<Record<number, number[]>>({});

  // VIP CSV Import state
  const [csvText, setCsvText] = useState(
    'full_name,email,phone,organization,job_title,max_companions\nمعالي د. سامي بن فهد العبدالله,sami.vip@arab-edu.org,+966501239876,اتحاد الجامعات العربية,أمين عام مساعد,2\nأ.د. ريم بنت خالد الزهراني,reem.vip@edu.org,+966559876543,معهد القيادة التربوية,رئيسة المجلس العلمي,1'
  );
  const [csvImportReport, setCsvImportReport] = useState<any>(null);

  const fetchAllState = useCallback(async () => {
    try {
      const res = await fetch('/api/tarbwy/v1/state');
      if (res.ok) {
        const data = await res.json();
        setStateData(data);
        // Initialize default partial companion selections for pending requests
        if (data.registrations) {
          setPartialCompanionMap((prev) => {
            const next = { ...prev };
            for (const r of data.registrations) {
              if (!next[r.id] && r.guests) {
                next[r.id] = r.guests.filter((g: any) => g.parent_guest_id !== null).map((g: any) => g.id);
              }
            }
            return next;
          });
        }
        // Set default ticket token if empty
        if (!activeTicketToken && data.tickets && data.tickets.length > 0) {
          const firstActive = data.tickets.find((t: any) => t.status === 'active') || data.tickets[0];
          if (firstActive) {
            setActiveTicketToken(firstActive.demo_raw_token_preview);
          }
        }
        // Set default VIP token if empty
        if (!vipTokenInput && data.invitations && data.invitations.length > 0) {
          const firstInv = data.invitations.find((i: any) => i.status === 'active') || data.invitations[0];
          if (firstInv) {
            setVipTokenInput(firstInv.demo_raw_token_preview);
          }
        }
      }
    } catch {
      // ignore
    }
  }, [activeTicketToken, vipTokenInput]);

  const fetchPluginFiles = useCallback(async () => {
    try {
      const res = await fetch('/api/plugin/files');
      if (res.ok) {
        const data = await res.json();
        setPluginMeta(data);
        if (data.files && data.files.length > 0 && !selectedFile) {
          loadFileContent('tarbwy-event-manager.php');
        }
      }
    } catch {
      // ignore
    }
  }, [selectedFile]);

  const loadFileContent = async (filePath: string) => {
    const res = await fetch(`/api/plugin/file-content?path=${encodeURIComponent(filePath)}`);
    if (res.ok) {
      const data = await res.json();
      setSelectedFile(data);
    }
  };

  // Guaranteed Corruption-Free Binary ZIP Downloader (Bypasses iframe navigation & proxy stream issues)
  const handleDownloadZip = async () => {
    setDownloadingZip(true);
    try {
      let b64 = pluginMeta.zipBase64;
      if (!b64) {
        const res = await fetch('/api/plugin/files');
        if (res.ok) {
          const data = await res.json();
          setPluginMeta(data);
          b64 = data.zipBase64;
        }
      }

      if (b64) {
        const binaryStr = window.atob(b64);
        const len = binaryStr.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
          bytes[i] = binaryStr.charCodeAt(i);
        }
        const blob = new Blob([bytes], { type: 'application/zip' });
        const blobUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = 'tarbwy-event-manager.zip';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(blobUrl), 5000);
        return;
      }

      // Fallback direct binary fetch
      const binRes = await fetch('/api/plugin/download-zip');
      const arrayBuf = await binRes.arrayBuffer();
      const blob = new Blob([arrayBuf], { type: 'application/zip' });
      const blobUrl = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = blobUrl;
      link.download = 'tarbwy-event-manager.zip';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setTimeout(() => URL.revokeObjectURL(blobUrl), 5000);
    } finally {
      setDownloadingZip(false);
    }
  };

  useEffect(() => {
    fetchAllState();
    fetchPluginFiles();
    const timer = setInterval(fetchAllState, 15000);
    return () => clearInterval(timer);
  }, [fetchAllState, fetchPluginFiles]);

  // Load ticket view when route is 'ticket' or activeTicketToken changes
  useEffect(() => {
    if (activeRoute === 'ticket' && activeTicketToken) {
      setTicketViewError('');
      fetch(`/api/tarbwy/v1/tickets/view/${encodeURIComponent(activeTicketToken)}`)
        .then(async (r) => ({ ok: r.ok, data: await r.json() }))
        .then((res) => {
          if (res.ok) {
            setTicketViewData(res.data);
          } else {
            setTicketViewData(null);
            setTicketViewError(res.data.message || 'بطاقة غير صالحة');
          }
        });
    }
  }, [activeRoute, activeTicketToken]);

  // Submit Public Registration
  const handlePublicRegSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setRegSubmitting(true);
    setRegFeedback(null);
    try {
      const res = await fetch('/api/tarbwy/v1/registrations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          website_url_hp: regHoneypot,
          full_name: regName,
          email: regEmail,
          phone: regPhone,
          organization: regOrg,
          job_title: regTitle,
          dietary_requirements: regDietary,
          companions: regCompanions,
        }),
      });
      const data = await res.json();
      if (res.ok) {
        setRegFeedback({ ok: true, msg: data.message, ref: data.reference_code });
        setRegName('');
        setRegEmail('');
        setRegPhone('');
        setRegOrg('');
        setRegTitle('');
        setRegDietary('');
        setRegCompanions([]);
        fetchAllState();
      } else {
        setRegFeedback({ ok: false, msg: data.message || 'خطأ في التحقق من المدخلات.' });
      }
    } catch {
      setRegFeedback({ ok: false, msg: 'تعذر الاتصال بالخادم.' });
    } finally {
      setRegSubmitting(false);
    }
  };

  // Verify VIP Token
  const handleVerifyVipToken = async (tokenToVerify = vipTokenInput) => {
    setVipError('');
    setVipResult(null);
    const res = await fetch(`/api/tarbwy/v1/vip-invitations/${encodeURIComponent(tokenToVerify.trim())}`);
    const data = await res.json();
    if (res.ok) {
      setVipLoaded(data);
    } else {
      setVipLoaded(null);
      setVipError(data.message || 'رابط الدعوة غير صالح.');
    }
  };

  // Submit VIP RSVP
  const handleVipRsvpSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setVipError('');
    const res = await fetch(`/api/tarbwy/v1/vip-invitations/${encodeURIComponent(vipTokenInput.trim())}/rsvp`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        decision: vipDecision,
        companions: vipDecision === 'attend' ? vipCompanions : [],
      }),
    });
    const data = await res.json();
    if (res.ok) {
      setVipResult(data);
      fetchAllState();
    } else {
      setVipError(data.message || 'تعذر تسجيل الرد.');
    }
  };

  // Execute QR Scan Check-in
  const handleExecuteScan = async (tokenToScan: string) => {
    setConcurrentRaceResult(null);
    if (simulateOffline) {
      setCheckinBanner({
        result_code: 'offline',
        message: 'فشل الاتصال بالخادم (Offline) — لم يتم تسجيل الدخول! يحظر النظام تسجيل نجاح محلي وهمي قبل تأكيد الخادم.',
      });
      return;
    }
    const res = await fetch('/api/tarbwy/v1/checkin/scan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        qr_token: tokenToScan.trim(),
        device_label: 'Terminal-Gate-1',
      }),
    });
    const data = await res.json();
    setCheckinBanner(data);
    fetchAllState();
  };

  // Simulate Simultaneous Scan of Same Ticket from 2 Devices
  const handleSimulateConcurrentRace = async (tokenToScan: string) => {
    setCheckinBanner(null);
    const res = await fetch('/api/tarbwy/v1/checkin/simulate-concurrent-scan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ qr_token: tokenToScan.trim() }),
    });
    const data = await res.json();
    setConcurrentRaceResult(data);
    fetchAllState();
  };

  // Manual Check-in
  const handleManualCheckin = async (guestId: number) => {
    setConcurrentRaceResult(null);
    if (simulateOffline) {
      setCheckinBanner({
        result_code: 'offline',
        message: 'فشل الاتصال بالخادم (Offline) — تعذر تنفيذ التسجيل اليدوي.',
      });
      return;
    }
    const res = await fetch('/api/tarbwy/v1/checkin/manual', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ guest_id: guestId, device_label: 'Manual-Desk-1' }),
    });
    const data = await res.json();
    setCheckinBanner(data);
    fetchAllState();
  };

  // Admin Review Registration (Approve / Waitlist / Decline)
  const handleReviewAction = async (regId: number, status: 'approved' | 'waitlist' | 'declined') => {
    const approvedCompanions = partialCompanionMap[regId] || [];
    await fetch(`/api/tarbwy/v1/registrations/${regId}/review`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        review_status: status,
        approved_companion_ids: approvedCompanions,
        review_note: `تمت المراجعة بواسطة (${currentRole})`,
      }),
    });
    fetchAllState();
  };

  // Reissue Ticket
  const handleReissueTicket = async (guestId: number) => {
    const res = await fetch(`/api/tarbwy/v1/tickets/${guestId}/reissue`, { method: 'POST' });
    if (res.ok) {
      const data = await res.json();
      if (data.ticket) {
        setActiveTicketToken(data.ticket.demo_raw_token_preview);
      }
      fetchAllState();
    }
  };

  // VIP CSV Import (Dry Run or Commit)
  const handleVipCsvImport = async (dryRun: boolean) => {
    const res = await fetch('/api/tarbwy/v1/invitations/import', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csv_content: csvText, dry_run: dryRun }),
    });
    const data = await res.json();
    setCsvImportReport(data);
    if (!dryRun) {
      fetchAllState();
    }
  };

  const ev = stateData?.event || {};
  const stats = stateData?.stats || {};

  return (
    <div className="min-h-screen bg-[#FBF9F5] text-stone-900 flex flex-col" dir="rtl">
      {/* Top Bar Contract: Zone 1 (Wordmark) — Zone 2 (Single-line Nav Links) — Zone 3 (1 Primary Action) */}
      <header className="sticky top-0 z-40 bg-white border-b border-stone-200 px-6 py-3.5 no-print">
        <div className="max-w-[1380px] mx-auto flex items-center justify-between gap-8">
          <button
            onClick={() => setActiveRoute('landing')}
            className="text-lg font-extrabold tracking-tight text-emerald-900 whitespace-nowrap shrink-0 cursor-pointer"
          >
            تربوي — الاتحاد العربي للقيادة التربوية
          </button>

          <nav className="hidden lg:flex items-center gap-6 text-sm font-semibold text-stone-600">
            <button
              onClick={() => setActiveRoute('landing')}
              className={`hover:text-emerald-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 ${
                activeRoute === 'landing' ? 'text-emerald-900 border-b-2 border-emerald-900' : ''
              }`}
            >
              صفحة الحفل (/launch)
            </button>
            <button
              onClick={() => setActiveRoute('vip')}
              className={`hover:text-emerald-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 ${
                activeRoute === 'vip' ? 'text-emerald-900 border-b-2 border-emerald-900' : ''
              }`}
            >
              بوابة VIP (/launch/vip)
            </button>
            <button
              onClick={() => setActiveRoute('ticket')}
              className={`hover:text-emerald-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 ${
                activeRoute === 'ticket' ? 'text-emerald-900 border-b-2 border-emerald-900' : ''
              }`}
            >
              بطاقة QR (/launch/ticket)
            </button>
            <button
              onClick={() => setActiveRoute('checkin')}
              className={`hover:text-emerald-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 ${
                activeRoute === 'checkin' ? 'text-emerald-900 border-b-2 border-emerald-900' : ''
              }`}
            >
              بوابة الاستقبال (/launch/checkin)
            </button>
            <button
              onClick={() => setActiveRoute('admin')}
              className={`hover:text-emerald-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 ${
                activeRoute === 'admin' ? 'text-emerald-900 border-b-2 border-emerald-900' : ''
              }`}
            >
              لوحة إدارة WordPress
            </button>
            <button
              onClick={() => setActiveRoute('deliverables')}
              className={`hover:text-emerald-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 ${
                activeRoute === 'deliverables' ? 'text-emerald-900 border-b-2 border-emerald-900' : ''
              }`}
            >
              ملفات الإضافة واختبارات الـ 21 بنداً
            </button>
          </nav>

          <div className="flex items-center gap-3 shrink-0">
            <button
              type="button"
              onClick={handleDownloadZip}
              disabled={downloadingZip}
              className="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-emerald-900 hover:bg-emerald-800 transition-colors whitespace-nowrap shrink-0 cursor-pointer disabled:opacity-60"
            >
              <Download className="w-4 h-4" />
              {downloadingZip ? 'جاري تجهيز ملف ZIP...' : 'تحميل الإضافة (tarbwy-event-manager.zip)'}
            </button>
          </div>
        </div>
      </header>

      {/* Mobile Route Switcher Bar */}
      <div className="lg:hidden flex overflow-x-auto gap-2 bg-stone-100 border-b border-stone-200 px-4 py-2 text-xs font-bold no-print">
        <button onClick={() => setActiveRoute('landing')} className="px-3 py-1.5 bg-white border border-stone-200 whitespace-nowrap shrink-0">
          /launch
        </button>
        <button onClick={() => setActiveRoute('vip')} className="px-3 py-1.5 bg-white border border-stone-200 whitespace-nowrap shrink-0">
          /launch/vip
        </button>
        <button onClick={() => setActiveRoute('ticket')} className="px-3 py-1.5 bg-white border border-stone-200 whitespace-nowrap shrink-0">
          /launch/ticket
        </button>
        <button onClick={() => setActiveRoute('checkin')} className="px-3 py-1.5 bg-white border border-stone-200 whitespace-nowrap shrink-0">
          /launch/checkin
        </button>
        <button onClick={() => setActiveRoute('admin')} className="px-3 py-1.5 bg-white border border-stone-200 whitespace-nowrap shrink-0">
          wp-admin
        </button>
        <button onClick={() => setActiveRoute('deliverables')} className="px-3 py-1.5 bg-white border border-stone-200 whitespace-nowrap shrink-0">
          الكود والاختبارات
        </button>
      </div>

      {/* =====================================================================
          ROUTE 1: PUBLIC LANDING PAGE & REGISTRATION (/launch)
         ===================================================================== */}
      {activeRoute === 'landing' && (
        <main className="flex-1">
          {/* 1. Institutional Hero Section */}
          <section className="bg-[#F4EFE6] border-b border-stone-200 py-16 px-6">
            <div className="max-w-[1140px] mx-auto">
              <p className="text-xs font-bold text-[#9A3412] mb-3">
                الإشهار المؤسسي الرسمي · الموقع الرسمي https://tarbwy.org · المسار: /launch
              </p>
              <h1 className="text-3xl md:text-5xl font-extrabold text-stone-900 leading-tight mb-4 max-w-4xl">
                {ev.title || 'حفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية»'}
              </h1>
              <p className="text-base md:text-lg text-stone-600 max-w-3xl mb-8 leading-relaxed">
                {ev.subtitle}
              </p>

              {/* Operational Utility Ribbon */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-6 bg-white border border-stone-200 p-6 mb-8">
                <div>
                  <span className="block text-xs text-stone-500">التاريخ والوقت</span>
                  <strong className="text-sm font-bold text-stone-900">{ev.date_display || 'سيُعلن لاحقاً'}</strong>
                </div>
                <div>
                  <span className="block text-xs text-stone-500">المدينة والمكان</span>
                  <strong className="text-sm font-bold text-stone-900">
                    {ev.city_display || 'سيُعلن لاحقاً'} — {ev.venue_name || 'سيُعلن لاحقاً'}
                  </strong>
                </div>
                <div>
                  <span className="block text-xs text-stone-500">السعة الإجمالية المعتمدة</span>
                  <strong className="text-sm font-bold text-stone-900 font-mono tabular-nums">
                    {ev.capacity_total || 500} شخص (شاملاً المرافقين)
                  </strong>
                </div>
                <div>
                  <span className="block text-xs text-stone-500">المقاعد المؤكدة حتى الآن</span>
                  <strong className="text-sm font-bold text-emerald-900 font-mono tabular-nums">
                    {stats.confirmed || 0} / {ev.capacity_total || 500}
                  </strong>
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-4">
                <a
                  href="#public-registration-form"
                  className="px-7 py-3.5 bg-emerald-900 text-white font-bold text-sm hover:bg-emerald-800 transition-colors whitespace-nowrap shrink-0"
                >
                  طلب حضور الحفل
                </a>
                <button
                  onClick={() => setActiveRoute('vip')}
                  className="px-6 py-3.5 bg-white border border-stone-300 text-stone-800 font-bold text-sm hover:bg-stone-50 transition-colors whitespace-nowrap shrink-0 cursor-pointer"
                >
                  لديك رمز دعوة خاصة (VIP)؟
                </button>
              </div>
            </div>
          </section>

          {/* 2 & 3. About Event & About Tarbwy */}
          <section className="py-14 px-6 border-b border-stone-200">
            <div className="max-w-[1140px] mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
              <div className="bg-white border border-stone-200 p-8">
                <span className="block text-xs font-bold text-[#9A3412] mb-2">01 · عن حفل الإشهار</span>
                <h2 className="text-xl font-bold text-stone-900 mb-3">محطة تأسيسية للقيادة التربوية العربية</h2>
                <p className="text-sm text-stone-700 leading-relaxed">{ev.about_event}</p>
              </div>
              <div className="bg-white border border-stone-200 p-8">
                <span className="block text-xs font-bold text-[#9A3412] mb-2">02 · نبذة مؤسسية مختصرة عن تربوي</span>
                <h2 className="text-xl font-bold text-stone-900 mb-3">الاتحاد العربي للقيادة التربوية</h2>
                <p className="text-sm text-stone-700 leading-relaxed">{ev.about_tarbwy}</p>
              </div>
            </div>
          </section>

          {/* 4, 5, 6, 7. Schedule, Speakers, Venue & Sponsors (Configurable / No Invented Data) */}
          <section className="py-14 px-6 bg-[#F4EFE6] border-b border-stone-200">
            <div className="max-w-[1140px] mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="bg-white border border-stone-200 p-6">
                <span className="block text-xs font-bold text-[#9A3412] mb-1">03 · البرنامج الزمني</span>
                <h3 className="text-lg font-bold text-stone-900 mb-2">فقرات حفل الإشهار</h3>
                <p className="text-xs text-stone-600 leading-relaxed">
                  سيُعلن لاحقاً — قابل للتحرير الكامل من لوحة إدارة الحدث فور اعتماد البرنامج النهائي من قِبل «تربوي».
                </p>
              </div>
              <div className="bg-white border border-stone-200 p-6">
                <span className="block text-xs font-bold text-[#9A3412] mb-1">04 · المتحدثون والقيادات</span>
                <h3 className="text-lg font-bold text-stone-900 mb-2">المتحدثون الرئيسيون</h3>
                <p className="text-xs text-stone-600 leading-relaxed">
                  سيُعلن لاحقاً — التزاماً بالمواصفات الفنية، لم يتم اختراع أسماء أو صور متحدثين عشوائية. يُدار القسم من لوحة التحكم.
                </p>
              </div>
              <div className="bg-white border border-stone-200 p-6">
                <span className="block text-xs font-bold text-[#9A3412] mb-1">05 · المكان والشركاء</span>
                <h3 className="text-lg font-bold text-stone-900 mb-2">{ev.venue_name}</h3>
                <p className="text-xs text-stone-600 leading-relaxed">{ev.venue_address}</p>
              </div>
            </div>
          </section>

          {/* 8. Public Registration Form inside /launch */}
          <section id="public-registration-form" className="py-16 px-6">
            <div className="max-w-[880px] mx-auto bg-white border border-stone-200 p-8 md:p-10">
              <span className="block text-xs font-bold text-[#9A3412] mb-2">06 · نموذج طلب الحضور العام</span>
              <h2 className="text-2xl font-bold text-stone-900 mb-2">استمارة طلب حضور حفل الإشهار</h2>
              <p className="text-sm text-stone-600 mb-6 leading-relaxed">
                عند إرسال هذا النموذج، يُسجل طلبكم بحالة <strong>قيد المراجعة (Pending)</strong> ولا يُعد تأكيداً نهائياً للحضور.
                عند اعتماد الطلب من قِبل لجنة التسجيل، تصدر بطاقة دخول شخصية برمز QR مستقل للضيف الأساسي ولكل مرافق معتمد.
              </p>

              <form onSubmit={handlePublicRegSubmit} className="space-y-6">
                {/* Hidden Honeypot Field */}
                <div className="hidden" aria-hidden="true">
                  <label>اترك هذا الحقل فارغاً</label>
                  <input
                    type="text"
                    value={regHoneypot}
                    onChange={(e) => setRegHoneypot(e.target.value)}
                    tabIndex={-1}
                    autoComplete="off"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <div>
                    <label className="block text-xs font-bold text-stone-800 mb-1.5">الاسم الكامل (كما سيظهر في البطاقة) *</label>
                    <input
                      type="text"
                      required
                      value={regName}
                      onChange={(e) => setRegName(e.target.value)}
                      placeholder="مثال: د. محمد بن عبدالله العتيبي"
                      className="w-full px-3.5 py-2.5 text-sm border border-stone-300 bg-white focus:outline-none focus:border-emerald-900"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-stone-800 mb-1.5">البريد الإلكتروني *</label>
                    <input
                      type="email"
                      required
                      value={regEmail}
                      onChange={(e) => setRegEmail(e.target.value)}
                      placeholder="name@organization.org"
                      className="w-full px-3.5 py-2.5 text-sm border border-stone-300 bg-white focus:outline-none focus:border-emerald-900"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-stone-800 mb-1.5">رقم الهاتف / الجوال *</label>
                    <input
                      type="tel"
                      required
                      value={regPhone}
                      onChange={(e) => setRegPhone(e.target.value)}
                      placeholder="+966 50 000 0000"
                      className="w-full px-3.5 py-2.5 text-sm border border-stone-300 bg-white focus:outline-none focus:border-emerald-900"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-stone-800 mb-1.5">المؤسسة / الجهة التربوية *</label>
                    <input
                      type="text"
                      required
                      value={regOrg}
                      onChange={(e) => setRegOrg(e.target.value)}
                      placeholder="اسم الجامعة / الوزارة / المؤسسة"
                      className="w-full px-3.5 py-2.5 text-sm border border-stone-300 bg-white focus:outline-none focus:border-emerald-900"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-stone-800 mb-1.5">المسمى الوظيفي / الصفة *</label>
                    <input
                      type="text"
                      required
                      value={regTitle}
                      onChange={(e) => setRegTitle(e.target.value)}
                      placeholder="أستاذ جامعي / مدير عام / مشرف تربوي"
                      className="w-full px-3.5 py-2.5 text-sm border border-stone-300 bg-white focus:outline-none focus:border-emerald-900"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-stone-800 mb-1.5">متطلبات غذائية أو إمكانية وصول (اختياري)</label>
                    <input
                      type="text"
                      value={regDietary}
                      onChange={(e) => setRegDietary(e.target.value)}
                      placeholder="اختياري — محدود الوصول لفريق التنظيم"
                      className="w-full px-3.5 py-2.5 text-sm border border-stone-300 bg-white focus:outline-none focus:border-emerald-900"
                    />
                  </div>
                </div>

                {/* Companions Section */}
                <div className="pt-6 border-t border-stone-200">
                  <div className="flex items-center justify-between flex-wrap gap-4 mb-4">
                    <div>
                      <h3 className="text-sm font-bold text-stone-900">
                        إضافة المرافقين (الحد الأقصى: {ev.max_companions_public || 2})
                      </h3>
                      <p className="text-xs text-stone-500">
                        يُنشأ لكل مرافق سجل مستقل وبطاقة QR مستقلة. إن لم يتوفر بريد للمرافق تُرسل بطاقته لبريدك الأساسي.
                      </p>
                    </div>
                    <button
                      type="button"
                      disabled={regCompanions.length >= (ev.max_companions_public || 2)}
                      onClick={() =>
                        setRegCompanions([...regCompanions, { full_name: '', email: '', job_title: 'مرافق' }])
                      }
                      className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold border border-stone-300 bg-stone-50 hover:bg-stone-100 disabled:opacity-40 cursor-pointer"
                    >
                      <Plus className="w-3.5 h-3.5" />
                      إضافة مرافق
                    </button>
                  </div>

                  <div className="space-y-3">
                    {regCompanions.map((comp, idx) => (
                      <div key={idx} className="p-4 bg-[#FBF9F5] border border-stone-200 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                          <label className="block text-xs font-bold text-stone-700 mb-1">اسم المرافق #{idx + 1} *</label>
                          <input
                            type="text"
                            required
                            value={comp.full_name}
                            onChange={(e) => {
                              const next = [...regCompanions];
                              next[idx].full_name = e.target.value;
                              setRegCompanions(next);
                            }}
                            placeholder="الاسم الثلاثي للمرافق"
                            className="w-full px-3 py-2 text-xs border border-stone-300 bg-white"
                          />
                        </div>
                        <div>
                          <label className="block text-xs font-bold text-stone-700 mb-1">بريد المرافق (اختياري)</label>
                          <input
                            type="email"
                            value={comp.email}
                            onChange={(e) => {
                              const next = [...regCompanions];
                              next[idx].email = e.target.value;
                              setRegCompanions(next);
                            }}
                            placeholder="إن تُرك فارغاً تُرسل لبريدك"
                            className="w-full px-3 py-2 text-xs border border-stone-300 bg-white"
                          />
                        </div>
                        <div className="flex items-center gap-2">
                          <div className="flex-1">
                            <label className="block text-xs font-bold text-stone-700 mb-1">الصفة</label>
                            <input
                              type="text"
                              value={comp.job_title}
                              onChange={(e) => {
                                const next = [...regCompanions];
                                next[idx].job_title = e.target.value;
                                setRegCompanions(next);
                              }}
                              className="w-full px-3 py-2 text-xs border border-stone-300 bg-white"
                            />
                          </div>
                          <button
                            type="button"
                            onClick={() => setRegCompanions(regCompanions.filter((_, i) => i !== idx))}
                            className="p-2 text-red-700 hover:bg-red-50 border border-stone-200 cursor-pointer"
                            title="حذف المرافق"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {regFeedback && (
                  <div
                    className={`p-4 border text-sm ${
                      regFeedback.ok
                        ? 'bg-emerald-50 border-emerald-700 text-emerald-950'
                        : 'bg-red-50 border-red-700 text-red-950'
                    }`}
                  >
                    <p className="font-bold mb-1">
                      {regFeedback.ok ? 'تم استلام الطلب بنجاح (الحالة: قيد المراجعة — Pending)' : 'تنبيه التحقق الخادمي'}
                    </p>
                    <p>{regFeedback.msg}</p>
                    {regFeedback.ref && (
                      <p className="mt-2 font-mono text-xs">
                        رقم المرجع: <strong>{regFeedback.ref}</strong> (لا يتم إصدار رمز QR إلا بعد مراجعة واعتماد الإدارة)
                      </p>
                    )}
                  </div>
                )}

                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={regSubmitting}
                    className="px-8 py-3.5 bg-emerald-900 text-white font-bold text-sm hover:bg-emerald-800 transition-colors cursor-pointer"
                  >
                    {regSubmitting ? 'جاري الإرسال...' : 'إرسال طلب الحضور للمراجعة'}
                  </button>
                </div>
              </form>
            </div>
          </section>
        </main>
      )}

      {/* =====================================================================
          ROUTE 2: PRIVATE VIP INVITATION & RSVP (/launch/vip)
         ===================================================================== */}
      {activeRoute === 'vip' && (
        <main className="flex-1 py-12 px-6">
          <div className="max-w-[780px] mx-auto bg-white border border-stone-200 p-8">
            <span className="block text-xs font-bold text-[#9A3412] mb-1">
              المسار الخاص: /launch/vip · Referrer-Policy: no-referrer · SHA-256 Token Hash
            </span>
            <h1 className="text-2xl font-extrabold text-stone-900 mb-2">بوابة الدعوات الخاصة لكبار الشخصيات (VIP RSVP)</h1>
            <p className="text-xs text-stone-600 mb-6">
              التزاماً بالمواصفات الأمنية، لا يتم كشف اسم أو بيانات المدعو قبل التحقق من البصمة المشفرة (SHA-256) للرمز العشوائي.
            </p>

            {/* Quick Selector for Demo VIP Invitations */}
            {stateData?.invitations?.length > 0 && (
              <div className="mb-6 p-4 bg-[#F4EFE6] border border-stone-300 text-xs">
                <strong className="block text-stone-800 mb-2">روابط VIP المتاحة للتجربة الفورية:</strong>
                <div className="flex flex-wrap gap-2">
                  {stateData.invitations.map((inv: any) => (
                    <button
                      key={inv.id}
                      type="button"
                      onClick={() => {
                        setVipTokenInput(inv.demo_raw_token_preview);
                        handleVerifyVipToken(inv.demo_raw_token_preview);
                      }}
                      className="px-3 py-1.5 bg-white border border-stone-300 hover:border-emerald-900 text-stone-800 font-semibold cursor-pointer"
                    >
                      {inv.guest?.full_name} ({inv.status})
                    </button>
                  ))}
                </div>
              </div>
            )}

            <div className="flex flex-col sm:flex-row gap-3 mb-6">
              <input
                type="text"
                dir="ltr"
                value={vipTokenInput}
                onChange={(e) => setVipTokenInput(e.target.value)}
                placeholder="أدخل رمز الدعوة العشوائي..."
                className="flex-1 px-3.5 py-2.5 text-xs font-mono border border-stone-300 bg-white"
              />
              <button
                type="button"
                onClick={() => handleVerifyVipToken(vipTokenInput)}
                className="px-6 py-2.5 bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 cursor-pointer whitespace-nowrap shrink-0"
              >
                التحقق من الرمز وعرض الدعوة
              </button>
            </div>

            {vipError && (
              <div className="p-4 bg-red-50 border border-red-700 text-red-950 text-sm mb-6">
                <strong>رفض التحقق الأمني:</strong> {vipError}
              </div>
            )}

            {vipLoaded && (
              <div className="border-t border-stone-200 pt-6">
                <div className="p-5 bg-[#FBF9F5] border border-stone-200 mb-6">
                  <p className="text-xs text-stone-500 mb-1">دعوة رسمية موجهة إلى:</p>
                  <h2 className="text-xl font-bold text-stone-900">{vipLoaded.guest_name}</h2>
                  <p className="text-xs text-stone-600 mt-1">
                    {vipLoaded.job_title} · {vipLoaded.organization}
                  </p>
                </div>

                <form onSubmit={handleVipRsvpSubmit} className="space-y-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label
                      className={`p-4 border cursor-pointer block ${
                        vipDecision === 'attend' ? 'border-emerald-900 bg-emerald-50/40' : 'border-stone-200 bg-white'
                      }`}
                    >
                      <div className="flex items-center gap-2 font-bold text-sm text-stone-900">
                        <input
                          type="radio"
                          name="vip_decision"
                          checked={vipDecision === 'attend'}
                          onChange={() => setVipDecision('attend')}
                        />
                        سأحضر (تأكيد مباشر وإصدار بطاقات QR)
                      </div>
                      <p className="text-xs text-stone-600 mt-1">
                        يتحول الحضور إلى مؤكد (confirmed) مباشرة وتصدر بطاقتكم وبطاقات المرافقين فوراً.
                      </p>
                    </label>

                    <label
                      className={`p-4 border cursor-pointer block ${
                        vipDecision === 'decline' ? 'border-red-800 bg-red-50/30' : 'border-stone-200 bg-white'
                      }`}
                    >
                      <div className="flex items-center gap-2 font-bold text-sm text-stone-900">
                        <input
                          type="radio"
                          name="vip_decision"
                          checked={vipDecision === 'decline'}
                          onChange={() => setVipDecision('decline')}
                        />
                        أعتذر عن عدم الحضور
                      </div>
                      <p className="text-xs text-stone-600 mt-1">تسجيل الاعتذار رسمياً لدى الأمانة العامة.</p>
                    </label>
                  </div>

                  {vipDecision === 'attend' && (
                    <div className="p-5 bg-stone-50 border border-stone-200">
                      <div className="flex items-center justify-between mb-3">
                        <div>
                          <h3 className="text-sm font-bold text-stone-900">
                            المرافقون (الحد الأقصى المسموح لدعوتكم: {vipLoaded.max_companions})
                          </h3>
                          <p className="text-xs text-stone-500">لكل مرافق سجل مستقل وبطاقة QR مستقلة.</p>
                        </div>
                        <button
                          type="button"
                          disabled={vipCompanions.length >= vipLoaded.max_companions}
                          onClick={() =>
                            setVipCompanions([...vipCompanions, { full_name: '', email: '', job_title: 'مرافق VIP' }])
                          }
                          className="px-3 py-1.5 text-xs font-bold bg-white border border-stone-300 disabled:opacity-40 cursor-pointer"
                        >
                          + إضافة مرافق
                        </button>
                      </div>
                      <div className="space-y-3">
                        {vipCompanions.map((c, i) => (
                          <div key={i} className="grid grid-cols-1 md:grid-cols-2 gap-3 bg-white p-3 border border-stone-200">
                            <input
                              type="text"
                              required
                              placeholder="الاسم الكامل للمرافق *"
                              value={c.full_name}
                              onChange={(e) => {
                                const next = [...vipCompanions];
                                next[i].full_name = e.target.value;
                                setVipCompanions(next);
                              }}
                              className="px-3 py-2 text-xs border border-stone-300"
                            />
                            <div className="flex gap-2">
                              <input
                                type="email"
                                placeholder="بريد المرافق (أو يرسل لبريدكم)"
                                value={c.email}
                                onChange={(e) => {
                                  const next = [...vipCompanions];
                                  next[i].email = e.target.value;
                                  setVipCompanions(next);
                                }}
                                className="flex-1 px-3 py-2 text-xs border border-stone-300"
                              />
                              <button
                                type="button"
                                onClick={() => setVipCompanions(vipCompanions.filter((_, idx) => idx !== i))}
                                className="px-2.5 text-red-700 border border-stone-200 cursor-pointer"
                              >
                                حذف
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  <button
                    type="submit"
                    className="px-7 py-3 bg-emerald-900 text-white text-sm font-bold hover:bg-emerald-800 cursor-pointer"
                  >
                    اعتماد رد VIP
                  </button>
                </form>

                {vipResult && (
                  <div className="mt-6 p-5 bg-emerald-50 border border-emerald-800">
                    <h3 className="font-bold text-emerald-950 mb-2">{vipResult.message}</h3>
                    {vipResult.issued_tickets && vipResult.issued_tickets.length > 0 && (
                      <div className="space-y-2 mt-3">
                        {vipResult.issued_tickets.map((t: any, idx: number) => (
                          <div key={idx} className="flex items-center justify-between bg-white p-3 border border-stone-200 text-xs">
                            <div>
                              <strong>{t.guest_name}</strong> ({t.guest_type}) — <code>{t.ticket_reference}</code>
                            </div>
                            <button
                              type="button"
                              onClick={() => {
                                setActiveTicketToken(t.raw_token);
                                setActiveRoute('ticket');
                              }}
                              className="px-3 py-1.5 bg-emerald-900 text-white font-bold cursor-pointer"
                            >
                              عرض وطباعة البطاقة (QR)
                            </button>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>
        </main>
      )}

      {/* =====================================================================
          ROUTE 3: SECURE PRINTABLE GUEST TICKET (/launch/ticket)
         ===================================================================== */}
      {activeRoute === 'ticket' && (
        <main className="flex-1 py-10 px-6">
          <div className="max-w-[700px] mx-auto">
            {/* Ticket Switcher Bar (Hidden when printing) */}
            <div className="no-print mb-6 p-4 bg-white border border-stone-200 flex flex-wrap items-center justify-between gap-4">
              <div className="flex items-center gap-2 text-xs">
                <span className="font-bold text-stone-700">اختر تذكرة لعرضها:</span>
                <select
                  value={activeTicketToken}
                  onChange={(e) => setActiveTicketToken(e.target.value)}
                  className="px-3 py-1.5 border border-stone-300 bg-white text-xs"
                >
                  {stateData?.tickets?.map((t: any) => (
                    <option key={t.id} value={t.demo_raw_token_preview}>
                      {t.guest?.full_name} — {t.ticket_reference} ({t.status})
                    </option>
                  ))}
                </select>
              </div>
              <button
                type="button"
                onClick={() => window.print()}
                className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 cursor-pointer"
              >
                <Printer className="w-4 h-4" />
                طباعة البطاقة / حفظ PDF
              </button>
            </div>

            {ticketViewError && (
              <div className="p-6 bg-red-50 border-2 border-red-800 text-red-950 font-bold">
                {ticketViewError}
              </div>
            )}

            {ticketViewData && (
              <article className="bg-white border-2 border-emerald-900 p-8 md:p-10">
                <header className="border-b border-stone-200 pb-6 mb-6 flex items-start justify-between gap-4">
                  <div>
                    <p className="text-xs font-extrabold text-emerald-900 mb-1">
                      تربوي — الاتحاد العربي للقيادة التربوية
                    </p>
                    <h1 className="text-xl md:text-2xl font-extrabold text-stone-900">{ticketViewData.event_title}</h1>
                    <p className="text-xs text-stone-500 mt-2">
                      بطاقة حضور رسمية مؤكدة · فئة الضيف: {ticketViewData.guest_type} · رقم التذكرة:{' '}
                      <span className="font-mono font-bold text-stone-900">{ticketViewData.ticket_reference}</span>
                    </p>
                  </div>
                </header>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
                  <div className="md:col-span-2 space-y-4">
                    <div>
                      <span className="block text-xs text-stone-500">اسم حامل البطاقة (شخصية ومستقلة)</span>
                      <strong className="text-xl font-extrabold text-stone-900">{ticketViewData.holder_name}</strong>
                    </div>
                    <div>
                      <span className="block text-xs text-stone-500">المؤسسة / الجهة</span>
                      <span className="text-sm font-semibold text-stone-800">{ticketViewData.organization || '-'}</span>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                      <div>
                        <span className="block text-xs text-stone-500">الموعد</span>
                        <span className="text-xs font-bold text-stone-800">{ticketViewData.date_display}</span>
                      </div>
                      <div>
                        <span className="block text-xs text-stone-500">المكان</span>
                        <span className="text-xs font-bold text-stone-800">{ticketViewData.venue_name}</span>
                      </div>
                    </div>
                    <div className="pt-2 text-[11px] text-stone-500">
                      ملاحظة الخصوصية (القسم 4.4): لا تعرض هذه البطاقة البريد الإلكتروني أو رقم الهاتف، ولا يحتوي رمز QR على أي بيانات شخصية.
                    </div>
                  </div>

                  <div className="text-center border border-stone-200 p-4 bg-[#FBF9F5]">
                    <LocalQRCode token={ticketViewData.qr_payload} size={168} />
                    <p className="text-[11px] font-mono text-stone-600 mt-2">{ticketViewData.ticket_reference}</p>
                  </div>
                </div>

                <footer className="mt-8 pt-5 border-t border-stone-200 text-xs text-stone-700 leading-relaxed">
                  <strong>تعليمات الدخول: </strong>
                  {ticketViewData.entry_instructions}
                </footer>
              </article>
            )}
          </div>
        </main>
      )}

      {/* =====================================================================
          ROUTE 4: STAFF CHECK-IN INTERFACE (/launch/checkin)
         ===================================================================== */}
      {activeRoute === 'checkin' && (
        <main className="flex-1 py-8 px-6">
          <div className="max-w-[960px] mx-auto space-y-6">
            {/* Staff Header & Offline Simulation Toggle */}
            <div className="bg-white border border-stone-200 p-5 flex flex-wrap items-center justify-between gap-4">
              <div>
                <span className="text-xs font-bold text-emerald-900">المسار المحمي: /launch/checkin · صلاحية checkin_tarbwy_guests</span>
                <h1 className="text-xl font-extrabold text-stone-900">بوابة استقبال الضيوف ومسح رموز QR</h1>
              </div>
              <div className="flex items-center gap-3">
                <label className="inline-flex items-center gap-2 text-xs font-bold px-3 py-2 border border-stone-300 bg-stone-50 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={simulateOffline}
                    onChange={(e) => setSimulateOffline(e.target.checked)}
                  />
                  محاكاة انقطاع الإنترنت (Offline Test)
                </label>
              </div>
            </div>

            {/* Live Attendance Metrics Ribbon */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="bg-white border border-stone-200 p-4">
                <span className="block text-xs text-stone-500">إجمالي المؤكدين (Confirmed)</span>
                <strong className="text-2xl font-extrabold font-mono tabular-nums text-stone-900">
                  {stats.confirmed || 0}
                </strong>
              </div>
              <div className="bg-white border border-stone-200 p-4">
                <span className="block text-xs text-stone-500">سجلوا الدخول (Checked-in)</span>
                <strong className="text-2xl font-extrabold font-mono tabular-nums text-emerald-800">
                  {stats.checked_in || 0}
                </strong>
              </div>
              <div className="bg-white border border-stone-200 p-4">
                <span className="block text-xs text-stone-500">المتبقي للدخول</span>
                <strong className="text-2xl font-extrabold font-mono tabular-nums text-stone-800">
                  {stats.remaining || 0}
                </strong>
              </div>
              <div className="bg-white border border-stone-200 p-4">
                <span className="block text-xs text-stone-500">السعة الكلية للقاعة</span>
                <strong className="text-2xl font-extrabold font-mono tabular-nums text-stone-600">
                  {stats.capacity_total || 500}
                </strong>
              </div>
            </div>

            {/* Server Check-in Verification Banner */}
            {checkinBanner && (
              <div
                className={`p-5 border-2 ${
                  checkinBanner.result_code === 'valid'
                    ? 'bg-emerald-50 border-emerald-700 text-emerald-950'
                    : checkinBanner.result_code === 'already_checked_in'
                    ? 'bg-amber-50 border-amber-700 text-amber-950'
                    : 'bg-red-50 border-red-700 text-red-950'
                }`}
              >
                <div className="flex items-center gap-2 font-extrabold text-base mb-1">
                  {checkinBanner.result_code === 'valid' && <CheckCircle2 className="w-5 h-5 text-emerald-800" />}
                  {checkinBanner.result_code === 'already_checked_in' && <AlertTriangle className="w-5 h-5 text-amber-800" />}
                  {checkinBanner.result_code !== 'valid' && checkinBanner.result_code !== 'already_checked_in' && (
                    <XCircle className="w-5 h-5 text-red-800" />
                  )}
                  <span>{checkinBanner.message}</span>
                </div>
                {checkinBanner.guest && (
                  <p className="text-xs mt-1">
                    الضيف: <strong>{checkinBanner.guest.full_name}</strong> · الفئة: {checkinBanner.guest.guest_type} · رقم التذكرة:{' '}
                    <code className="font-mono font-bold">{checkinBanner.guest.ticket_reference}</code>
                    {checkinBanner.guest.parent_name && ` · مرافق لـ: ${checkinBanner.guest.parent_name}`}
                  </p>
                )}
              </div>
            )}

            {/* Simultaneous 2-Device Race Condition Result */}
            {concurrentRaceResult && (
              <div className="p-5 bg-white border-2 border-stone-900 space-y-3">
                <h3 className="text-sm font-extrabold text-stone-900">
                  نتيجة اختبار المسح المتزامن لنفس البطاقة من جهازين في اللحظة نفسها (Section 9 & 15.11):
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                  <div className="p-3.5 bg-emerald-50 border border-emerald-700">
                    <strong className="block text-emerald-950 mb-1">{concurrentRaceResult.deviceA.device}</strong>
                    <span className="font-mono">HTTP {concurrentRaceResult.deviceA.httpStatus} — {concurrentRaceResult.deviceA.result_code}</span>
                    <p className="mt-1">{concurrentRaceResult.deviceA.message}</p>
                  </div>
                  <div className="p-3.5 bg-amber-50 border border-amber-700">
                    <strong className="block text-amber-950 mb-1">{concurrentRaceResult.deviceB.device}</strong>
                    <span className="font-mono">HTTP {concurrentRaceResult.deviceB.httpStatus} — {concurrentRaceResult.deviceB.result_code}</span>
                    <p className="mt-1">{concurrentRaceResult.deviceB.message}</p>
                  </div>
                </div>
              </div>
            )}

            {/* Check-in Controls Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* 1. QR Scanner & Concurrent Test */}
              <div className="bg-white border border-stone-200 p-6 space-y-4">
                <h2 className="text-base font-bold text-stone-900 flex items-center gap-2">
                  <QrCode className="w-5 h-5 text-emerald-900" />
                  1. مسح رمز QR أو اختبار الحالات الخمس
                </h2>
                <p className="text-xs text-stone-600">
                  اختر بطاقة لمحاكاة مسح الكاميرا أو اختبر المسح المتزامن من جهازين في نفس اللحظة:
                </p>

                <div className="space-y-2">
                  {stateData?.tickets?.map((t: any) => (
                    <div key={t.id} className="p-3 bg-[#FBF9F5] border border-stone-200 flex items-center justify-between gap-2 text-xs">
                      <div>
                        <strong className="block text-stone-900">{t.guest?.full_name}</strong>
                        <span className="text-stone-500 font-mono">
                          {t.ticket_reference} · التذكرة: {t.status} · الدخول: {t.guest?.checkin_status}
                        </span>
                      </div>
                      <div className="flex items-center gap-1.5 shrink-0">
                        <button
                          type="button"
                          onClick={() => handleExecuteScan(t.demo_raw_token_preview)}
                          className="px-3 py-2 bg-emerald-900 text-white font-bold hover:bg-emerald-800 cursor-pointer"
                        >
                          مسح QR
                        </button>
                        <button
                          type="button"
                          onClick={() => handleSimulateConcurrentRace(t.demo_raw_token_preview)}
                          className="px-2.5 py-2 bg-stone-800 text-white font-bold hover:bg-stone-700 cursor-pointer"
                          title="اختبار مسح نفس البطاقة من جهازين في نفس اللحظة"
                        >
                          مسح متزامن (جهازين)
                        </button>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="pt-3 border-t border-stone-200 flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={() => handleExecuteScan('0000000000000000000000000000000000000000')}
                    className="px-3 py-2 text-xs font-bold border border-stone-300 bg-stone-50 hover:bg-stone-100 cursor-pointer"
                  >
                    اختبار مسح رمز غير معروف (Unknown)
                  </button>
                </div>

                <form
                  onSubmit={(e) => {
                    e.preventDefault();
                    handleExecuteScan(scanTokenInput);
                  }}
                  className="pt-3 border-t border-stone-200 flex gap-2"
                >
                  <input
                    type="text"
                    dir="ltr"
                    value={scanTokenInput}
                    onChange={(e) => setScanTokenInput(e.target.value)}
                    placeholder="أدخل رمز QR المشفر..."
                    className="flex-1 px-3 py-2 text-xs font-mono border border-stone-300"
                  />
                  <button type="submit" className="px-4 py-2 bg-emerald-900 text-white text-xs font-bold cursor-pointer">
                    تحقق
                  </button>
                </form>
              </div>

              {/* 2. Search & Manual Check-in */}
              <div className="bg-white border border-stone-200 p-6 space-y-4">
                <h2 className="text-base font-bold text-stone-900 flex items-center gap-2">
                  <Search className="w-5 h-5 text-emerald-900" />
                  2. البحث والتسجيل اليدوي (Manual Check-in)
                </h2>
                <input
                  type="search"
                  value={checkinSearch}
                  onChange={(e) => setCheckinSearch(e.target.value)}
                  placeholder="ابحث بالاسم أو البريد أو رقم التذكرة TKT-..."
                  className="w-full px-3.5 py-2.5 text-xs border border-stone-300 bg-white"
                />

                <div className="space-y-2 max-h-[340px] overflow-y-auto">
                  {stateData?.guests
                    ?.filter((g: any) => {
                      if (!checkinSearch.trim()) return true;
                      const q = checkinSearch.toLowerCase();
                      return (
                        g.full_name.toLowerCase().includes(q) ||
                        (g.email && g.email.toLowerCase().includes(q)) ||
                        (g.ticket?.ticket_reference && g.ticket.ticket_reference.toLowerCase().includes(q))
                      );
                    })
                    .map((g: any) => (
                      <div key={g.id} className="p-3 bg-[#FBF9F5] border border-stone-200 flex items-center justify-between gap-2 text-xs">
                        <div>
                          <strong className="block text-stone-900">
                            {g.full_name} ({g.guest_type})
                          </strong>
                          <span className="text-stone-500">
                            التأكيد: {g.rsvp_status} · التذكرة: {g.ticket?.ticket_reference || 'لم تصدر'}
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => handleManualCheckin(g.id)}
                          className="px-3.5 py-2 bg-white border border-stone-300 hover:border-emerald-900 font-bold text-stone-800 cursor-pointer whitespace-nowrap shrink-0"
                        >
                          {g.checkin_status === 'checked_in' ? 'حاضر (اختبار تكرار)' : 'تسجيل دخول يدوي'}
                        </button>
                      </div>
                    ))}
                </div>
              </div>
            </div>
          </div>
        </main>
      )}

      {/* =====================================================================
          ROUTE 5: WORDPRESS ADMIN DASHBOARD (wp-admin)
         ===================================================================== */}
      {activeRoute === 'admin' && (
        <main className="flex-1 py-8 px-6">
          <div className="max-w-[1320px] mx-auto space-y-6">
            {/* Role Switcher to test RBAC Permissions (Section 10 & 15.13) */}
            <div className="bg-white border border-stone-200 p-5 flex flex-wrap items-center justify-between gap-4">
              <div>
                <h1 className="text-xl font-extrabold text-stone-900">لوحة إدارة فعاليات تربوي (WordPress Admin RTL)</h1>
                <p className="text-xs text-stone-500">
                  اختبر الصلاحيات والأدوار الثلاثة: موظف الاستقبال (Check-in Staff) محظور من إدارة الطلبات والتصدير والإعدادات.
                </p>
              </div>
              <div className="flex items-center gap-2 text-xs">
                <span className="font-bold text-stone-700">الدور الحالي للمستخدم:</span>
                <select
                  value={currentRole}
                  onChange={(e) => {
                    const role = e.target.value as StaffRole;
                    setCurrentRole(role);
                    if (role === 'checkin_staff') {
                      setAdminTab('overview');
                    }
                  }}
                  className="px-3 py-2 border border-stone-300 bg-stone-50 font-bold"
                >
                  <option value="event_admin">مدير الفعالية (Event Administrator — كامل الصلاحيات)</option>
                  <option value="registration_manager">مسؤول التسجيل (Registration Manager — بدون إعدادات/تصدير)</option>
                  <option value="checkin_staff">موظف استقبال (Check-in Staff — استقبال فقط)</option>
                </select>
              </div>
            </div>

            {currentRole === 'checkin_staff' ? (
              <div className="p-8 bg-amber-50 border border-amber-700 text-amber-950">
                <h2 className="text-lg font-extrabold mb-2">
                  حماية الصلاحيات (RBAC Capability Enforcement — Section 10 & 15.13)
                </h2>
                <p className="text-sm mb-4">
                  أنت مسجل حالياً بدور <strong>موظف استقبال (tarbwy_checkin_staff)</strong>. يمتلك هذا الدور صلاحية{' '}
                  <code>checkin_tarbwy_guests</code> فقط، ولا يملك صلاحية عرض أو قبول الطلبات أو تصدير تقارير CSV أو تعديل إعدادات الموقع.
                </p>
                <button
                  type="button"
                  onClick={() => setActiveRoute('checkin')}
                  className="px-5 py-2.5 bg-emerald-900 text-white text-xs font-bold cursor-pointer"
                >
                  الانتقال إلى بوابة الاستقبال (/launch/checkin)
                </button>
              </div>
            ) : (
              <>
                {/* Admin Navigation Tabs */}
                <div className="flex flex-wrap gap-2 border-b border-stone-200 pb-3 text-xs font-bold">
                  <button
                    onClick={() => setAdminTab('overview')}
                    className={`px-4 py-2 cursor-pointer ${
                      adminTab === 'overview' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                    }`}
                  >
                    لوحة المؤشرات (Dashboard)
                  </button>
                  <button
                    onClick={() => setAdminTab('requests')}
                    className={`px-4 py-2 cursor-pointer ${
                      adminTab === 'requests' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                    }`}
                  >
                    طلبات التسجيل ({stats.pending_requests || 0} قيد المراجعة)
                  </button>
                  <button
                    onClick={() => setAdminTab('guests')}
                    className={`px-4 py-2 cursor-pointer ${
                      adminTab === 'guests' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                    }`}
                  >
                    قائمة الضيوف والمرافقين ({stats.total_guests || 0})
                  </button>
                  <button
                    onClick={() => setAdminTab('vip')}
                    className={`px-4 py-2 cursor-pointer ${
                      adminTab === 'vip' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                    }`}
                  >
                    دعوات VIP واستيراد CSV
                  </button>
                  <button
                    onClick={() => setAdminTab('tickets')}
                    className={`px-4 py-2 cursor-pointer ${
                      adminTab === 'tickets' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                    }`}
                  >
                    التذاكر وإعادة إصدار QR
                  </button>
                  {currentRole === 'event_admin' && (
                    <>
                      <button
                        onClick={() => setAdminTab('reports')}
                        className={`px-4 py-2 cursor-pointer ${
                          adminTab === 'reports' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                        }`}
                      >
                        التقارير وتصدير Excel CSV
                      </button>
                      <button
                        onClick={() => setAdminTab('settings')}
                        className={`px-4 py-2 cursor-pointer ${
                          adminTab === 'settings' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                        }`}
                      >
                        إعدادات الحدث
                      </button>
                      <button
                        onClick={() => setAdminTab('emails')}
                        className={`px-4 py-2 cursor-pointer ${
                          adminTab === 'emails' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                        }`}
                      >
                        سجل البريد والتدقيق الأمني
                      </button>
                    </>
                  )}
                </div>

                {/* KPI Metrics Row */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                  <div className="bg-white border border-stone-200 p-4">
                    <span className="block text-xs text-stone-500">إجمالي الطلبات</span>
                    <strong className="text-2xl font-extrabold font-mono tabular-nums">{stats.total_requests || 0}</strong>
                    <span className="block text-[11px] text-stone-500 mt-1">قيد المراجعة: {stats.pending_requests || 0}</span>
                  </div>
                  <div className="bg-white border border-stone-200 p-4">
                    <span className="block text-xs text-stone-500">معتمد / قائمة انتظار</span>
                    <strong className="text-2xl font-extrabold font-mono tabular-nums">{stats.approved_requests || 0}</strong>
                    <span className="block text-[11px] text-stone-500 mt-1">
                      انتظار: {stats.waitlist_requests || 0} · مرفوض: {stats.declined_requests || 0}
                    </span>
                  </div>
                  <div className="bg-white border border-stone-200 p-4">
                    <span className="block text-xs text-stone-500">كبار الشخصيات (VIP)</span>
                    <strong className="text-2xl font-extrabold font-mono tabular-nums">{stats.vip_invited || 0}</strong>
                    <span className="block text-[11px] text-stone-500 mt-1">المرافقون: {stats.companions_total || 0}</span>
                  </div>
                  <div className="bg-white border border-stone-200 p-4">
                    <span className="block text-xs text-stone-500">المؤكدون (Confirmed)</span>
                    <strong className="text-2xl font-extrabold font-mono tabular-nums text-emerald-900">
                      {stats.confirmed || 0} / {stats.capacity_total || 500}
                    </strong>
                    <span className="block text-[11px] text-stone-500 mt-1">شاملاً الضيوف والمرافقين</span>
                  </div>
                  <div className="bg-white border border-stone-200 p-4">
                    <span className="block text-xs text-stone-500">الحاضرون (Checked-in)</span>
                    <strong className="text-2xl font-extrabold font-mono tabular-nums text-emerald-700">
                      {stats.checked_in || 0}
                    </strong>
                    <span className="block text-[11px] text-stone-500 mt-1">المتبقي / No-Show: {stats.remaining || 0}</span>
                  </div>
                </div>

                {/* Subtab: Registration Requests Review + Partial Companion Approval */}
                {(adminTab === 'overview' || adminTab === 'requests') && (
                  <div className="bg-white border border-stone-200 p-6">
                    <h2 className="text-base font-extrabold text-stone-900 mb-1">
                      مراجعة طلبات الحضور العامة واعتماد المرافقين جزئياً أو كلياً
                    </h2>
                    <p className="text-xs text-stone-500 mb-4">
                      عند الموافقة (مع تفعيل approval_auto_confirms)، يتحول الضيف الأساسي والمرافقون المحددون فقط إلى confirmed وتصدر لهم تذاكر QR فردية.
                    </p>

                    <div className="space-y-4">
                      {stateData?.registrations?.map((reg: any) => {
                        const primary = reg.guests?.find((g: any) => g.parent_guest_id === null);
                        const comps = reg.guests?.filter((g: any) => g.parent_guest_id !== null) || [];
                        const selectedComps = partialCompanionMap[reg.id] || [];

                        return (
                          <div key={reg.id} className="p-5 bg-[#FBF9F5] border border-stone-200 flex flex-col lg:flex-row justify-between gap-4">
                            <div className="space-y-2">
                              <div className="flex items-center gap-2 text-xs">
                                <span className="font-mono font-bold text-stone-900">{reg.reference_code}</span>
                                <span>·</span>
                                <span className="font-bold text-[#9A3412]">حالة الطلب: {reg.review_status}</span>
                              </div>
                              <h3 className="text-base font-bold text-stone-900">
                                {primary?.full_name} — <span className="text-xs font-normal text-stone-600">{primary?.job_title} ({primary?.organization})</span>
                              </h3>
                              <p className="text-xs text-stone-600">
                                البريد: {primary?.email} · الهاتف: {primary?.phone}
                              </p>

                              {comps.length > 0 && (
                                <div className="pt-2">
                                  <span className="block text-xs font-bold text-stone-800 mb-1.5">
                                    المرافقون المرتبطون بالطلب (حدد المرافقين المراد اعتمادهم):
                                  </span>
                                  <div className="flex flex-wrap gap-3">
                                    {comps.map((c: any) => (
                                      <label key={c.id} className="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-stone-300 text-xs cursor-pointer">
                                        <input
                                          type="checkbox"
                                          checked={selectedComps.includes(c.id)}
                                          onChange={(e) => {
                                            const next = e.target.checked
                                              ? [...selectedComps, c.id]
                                              : selectedComps.filter((id) => id !== c.id);
                                            setPartialCompanionMap({ ...partialCompanionMap, [reg.id]: next });
                                          }}
                                        />
                                        <span>
                                          {c.full_name} (حالته: {c.rsvp_status})
                                        </span>
                                      </label>
                                    ))}
                                  </div>
                                </div>
                              )}
                            </div>

                            <div className="flex flex-wrap items-center gap-2 shrink-0 self-start">
                              <button
                                type="button"
                                onClick={() => handleReviewAction(reg.id, 'approved')}
                                className="px-4 py-2 bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 cursor-pointer"
                              >
                                موافقة وإصدار QR للمحددين
                              </button>
                              <button
                                type="button"
                                onClick={() => handleReviewAction(reg.id, 'waitlist')}
                                className="px-3.5 py-2 bg-amber-700 text-white text-xs font-bold hover:bg-amber-800 cursor-pointer"
                              >
                                قائمة انتظار (Waitlist)
                              </button>
                              <button
                                type="button"
                                onClick={() => handleReviewAction(reg.id, 'declined')}
                                className="px-3.5 py-2 bg-red-800 text-white text-xs font-bold hover:bg-red-900 cursor-pointer"
                              >
                                رفض (Decline)
                              </button>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {/* Subtab: Guest List & Tickets */}
                {(adminTab === 'guests' || adminTab === 'tickets') && (
                  <div className="bg-white border border-stone-200 p-6 space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                      <h2 className="text-base font-extrabold text-stone-900">
                        سجل الضيوف والمرافقين والتذاكر الفردية (كل مرافق له guest_id و QR مستقل)
                      </h2>
                      <div className="flex flex-wrap gap-2 text-xs">
                        <input
                          type="search"
                          placeholder="بحث بالاسم أو المؤسسة..."
                          value={guestSearch}
                          onChange={(e) => setGuestSearch(e.target.value)}
                          className="px-3 py-1.5 border border-stone-300"
                        />
                        <select
                          value={guestTypeFilter}
                          onChange={(e) => setGuestTypeFilter(e.target.value)}
                          className="px-3 py-1.5 border border-stone-300 bg-white"
                        >
                          <option value="all">جميع الفئات</option>
                          <option value="public">عام (Public)</option>
                          <option value="vip">كبار الشخصيات (VIP)</option>
                          <option value="companion">مرافق (Companion)</option>
                        </select>
                        <select
                          value={rsvpFilter}
                          onChange={(e) => setRsvpFilter(e.target.value)}
                          className="px-3 py-1.5 border border-stone-300 bg-white"
                        >
                          <option value="all">جميع حالات RSVP</option>
                          <option value="confirmed">مؤكد (Confirmed)</option>
                          <option value="pending_response">بانتظار الرد</option>
                          <option value="declined">معتذر / مستبعد</option>
                        </select>
                      </div>
                    </div>

                    <div className="overflow-x-auto">
                      <table className="w-full text-xs border-collapse">
                        <thead>
                          <tr className="bg-stone-100 border-b border-stone-200 text-stone-700">
                            <th className="p-3 text-right">ID</th>
                            <th className="p-3 text-right">الاسم الكامل</th>
                            <th className="p-3 text-right">الفئة / الضيف الأساسي</th>
                            <th className="p-3 text-right">المؤسسة</th>
                            <th className="p-3 text-right">حالة التأكيد (RSVP)</th>
                            <th className="p-3 text-right">رقم التذكرة (QR)</th>
                            <th className="p-3 text-right">حالة الدخول</th>
                            <th className="p-3 text-right">إجراءات البطاقة</th>
                          </tr>
                        </thead>
                        <tbody>
                          {stateData?.guests
                            ?.filter((g: any) => {
                              if (guestTypeFilter !== 'all' && g.guest_type !== guestTypeFilter) return false;
                              if (rsvpFilter !== 'all' && g.rsvp_status !== rsvpFilter) return false;
                              if (guestSearch.trim()) {
                                const q = guestSearch.toLowerCase();
                                return g.full_name.toLowerCase().includes(q) || (g.organization && g.organization.toLowerCase().includes(q));
                              }
                              return true;
                            })
                            .map((g: any) => (
                              <tr key={g.id} className="border-b border-stone-200 hover:bg-stone-50">
                                <td className="p-3 font-mono">{g.id}</td>
                                <td className="p-3 font-bold text-stone-900">
                                  {g.full_name}
                                  {g.admin_notes && <span className="block text-[11px] text-amber-800 font-normal">{g.admin_notes}</span>}
                                </td>
                                <td className="p-3">
                                  {g.guest_type}
                                  {g.parent_name && <span className="block text-[11px] text-stone-500">مرافق لـ: {g.parent_name}</span>}
                                </td>
                                <td className="p-3">{g.organization || '-'}</td>
                                <td className="p-3 font-semibold">{g.rsvp_status}</td>
                                <td className="p-3 font-mono">{g.ticket ? `${g.ticket.ticket_reference} (${g.ticket.status})` : 'بدون تذكرة'}</td>
                                <td className="p-3">{g.checkin_status === 'checked_in' ? `حاضر (${g.checked_in_at})` : 'لم يحضر بعد'}</td>
                                <td className="p-3">
                                  <div className="flex items-center gap-2">
                                    {g.ticket && (
                                      <button
                                        type="button"
                                        onClick={() => {
                                          setActiveTicketToken(g.ticket.demo_raw_token_preview);
                                          setActiveRoute('ticket');
                                        }}
                                        className="px-2.5 py-1 bg-stone-800 text-white font-bold cursor-pointer"
                                      >
                                        عرض البطاقة
                                      </button>
                                    )}
                                    {g.rsvp_status === 'confirmed' && (
                                      <button
                                        type="button"
                                        onClick={() => handleReissueTicket(g.id)}
                                        className="inline-flex items-center gap-1 px-2.5 py-1 border border-stone-300 bg-white hover:bg-stone-100 font-bold cursor-pointer"
                                        title="إبطال التذكرة السابقة وإصدار رمز QR جديد"
                                      >
                                        <RefreshCw className="w-3 h-3" />
                                        إعادة إصدار QR
                                      </button>
                                    )}
                                  </div>
                                </td>
                              </tr>
                            ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}

                {/* Subtab: VIP Invitations & CSV Import with Dry-Run */}
                {adminTab === 'vip' && (
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-white border border-stone-200 p-6 space-y-4">
                      <h2 className="text-base font-extrabold text-stone-900">
                        استيراد قائمة كبار الشخصيات (VIP CSV Import + Dry-Run Preview)
                      </h2>
                      <p className="text-xs text-stone-600">
                        افحص الملف أولاً عبر (معاينة Dry-Run) للتحقق من الصيغة وكشف التكرار. لا تُرسل الدعوات تلقائياً عند الاستيراد إلا بعد تأكيد المدير.
                      </p>
                      <textarea
                        rows={5}
                        dir="ltr"
                        value={csvText}
                        onChange={(e) => setCsvText(e.target.value)}
                        className="w-full p-3 text-xs font-mono border border-stone-300 bg-stone-50"
                      />
                      <div className="flex gap-3">
                        <button
                          type="button"
                          onClick={() => handleVipCsvImport(true)}
                          className="px-4 py-2 bg-stone-800 text-white text-xs font-bold cursor-pointer"
                        >
                          1. فحص ومعاينة (Dry-Run Preview)
                        </button>
                        <button
                          type="button"
                          onClick={() => handleVipCsvImport(false)}
                          className="px-4 py-2 bg-emerald-900 text-white text-xs font-bold cursor-pointer"
                        >
                          2. اعتماد الاستيراد الفعلي وتوليد الرموز المشفرة
                        </button>
                      </div>

                      {csvImportReport && (
                        <div className="p-4 bg-[#FBF9F5] border border-stone-300 text-xs space-y-2">
                          <strong>
                            {csvImportReport.dry_run ? 'نتيجة المعاينة التجريبية (Dry-Run):' : 'تم الاستيراد الفعلي بنجاح:'}
                          </strong>
                          <p>
                            إجمالي الصفوف: {csvImportReport.total_rows} · صالحة: {csvImportReport.valid_rows} · أخطاء:{' '}
                            {csvImportReport.invalid_rows}
                          </p>
                          {csvImportReport.warnings?.map((w: string, i: number) => (
                            <p key={i} className="text-amber-800">{w}</p>
                          ))}
                          {csvImportReport.errors?.map((err: string, i: number) => (
                            <p key={i} className="text-red-800">{err}</p>
                          ))}
                        </div>
                      )}
                    </div>

                    <div className="bg-white border border-stone-200 p-6 space-y-4">
                      <h2 className="text-base font-extrabold text-stone-900">دعوات VIP الحالية وإدارة الرموز</h2>
                      <div className="space-y-3">
                        {stateData?.invitations?.map((inv: any) => (
                          <div key={inv.id} className="p-4 bg-[#FBF9F5] border border-stone-200 text-xs space-y-2">
                            <div className="flex justify-between items-center">
                              <strong className="text-sm text-stone-900">{inv.guest?.full_name}</strong>
                              <span className="font-mono font-bold text-[#9A3412]">حالة الدعوة: {inv.status}</span>
                            </div>
                            <p className="text-stone-600">
                              {inv.guest?.job_title} — {inv.guest?.organization} · الحد الأقصى للمرافقين: {inv.max_companions_override}
                            </p>
                            <p className="font-mono text-[11px] text-stone-500 truncate">
                              SHA-256 Hash Stored in DB: {inv.token_hash}
                            </p>
                            <div className="flex flex-wrap gap-2 pt-1">
                              <button
                                type="button"
                                onClick={() => {
                                  setVipTokenInput(inv.demo_raw_token_preview);
                                  handleVerifyVipToken(inv.demo_raw_token_preview);
                                  setActiveRoute('vip');
                                }}
                                className="px-3 py-1.5 bg-emerald-900 text-white font-bold cursor-pointer"
                              >
                                فتح رابط الدعوة (/launch/vip)
                              </button>
                              <button
                                type="button"
                                onClick={async () => {
                                  await fetch(`/api/tarbwy/v1/invitations/${inv.id}/reissue`, { method: 'POST' });
                                  fetchAllState();
                                }}
                                className="px-3 py-1.5 bg-white border border-stone-300 font-bold cursor-pointer"
                              >
                                إعادة إصدار الرمز
                              </button>
                              <button
                                type="button"
                                onClick={async () => {
                                  await fetch(`/api/tarbwy/v1/invitations/${inv.id}/revoke`, { method: 'POST' });
                                  fetchAllState();
                                }}
                                className="px-3 py-1.5 bg-red-50 border border-red-300 text-red-900 font-bold cursor-pointer"
                              >
                                إبطال الدعوة (Revoke)
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                )}

                {/* Subtab: Reports & CSV Export */}
                {adminTab === 'reports' && (
                  <div className="bg-white border border-stone-200 p-6 space-y-4">
                    <h2 className="text-base font-extrabold text-stone-900 flex items-center gap-2">
                      <FileSpreadsheet className="w-5 h-5 text-emerald-900" />
                      التقارير وتصدير ملفات Excel العربية (UTF-8 BOM + CSV Formula Injection Protection)
                    </h2>
                    <p className="text-xs text-stone-600 leading-relaxed">
                      يتم تصدير الملفات مسبوقة بعلامة <code>\xEF\xBB\xBF</code> (UTF-8 BOM) لفتح الحروف العربية مباشرة في برنامج Microsoft Excel دون تشوه، مع تحييد أي خلايا تبدأ بـ (<code>=</code>, <code>+</code>, <code>-</code>, <code>@</code>).
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 pt-2">
                      {[
                        { key: 'all', label: 'تصدير جميع الضيوف والمرافقين (All Guests)' },
                        { key: 'confirmed', label: 'تصدير المؤكدين فقط (Confirmed Attendees)' },
                        { key: 'checked_in', label: 'تصدير الحاضرين فعلياً (Checked-in)' },
                        { key: 'no_show', label: 'تصدير المتغيبين (No-Show Report)' },
                        { key: 'vip', label: 'تصدير كبار الشخصيات (VIP Guests)' },
                        { key: 'companions', label: 'تصدير المرافقين (Companions)' },
                      ].map((item) => (
                        <a
                          key={item.key}
                          href={`/api/tarbwy/v1/export-csv?filter=${item.key}`}
                          download
                          className="p-4 bg-[#FBF9F5] border border-stone-300 hover:border-emerald-900 flex items-center justify-between text-xs font-bold text-stone-900"
                        >
                          <span>{item.label}</span>
                          <Download className="w-4 h-4 text-emerald-900 shrink-0" />
                        </a>
                      ))}
                    </div>
                  </div>
                )}

                {/* Subtab: Event Settings */}
                {adminTab === 'settings' && (
                  <div className="bg-white border border-stone-200 p-6 space-y-4">
                    <h2 className="text-base font-extrabold text-stone-900 flex items-center gap-2">
                      <Settings className="w-5 h-5 text-emerald-900" />
                      إعدادات الحدث ومحتوى صفحة الهبوط (/launch)
                    </h2>
                    <form
                      onSubmit={async (e) => {
                        e.preventDefault();
                        const formData = new FormData(e.currentTarget);
                        await fetch('/api/tarbwy/v1/settings', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({
                            title: formData.get('title'),
                            date_display: formData.get('date_display'),
                            city_display: formData.get('city_display'),
                            venue_name: formData.get('venue_name'),
                            capacity_total: Number(formData.get('capacity_total') || 500),
                            approval_auto_confirms: formData.get('approval_auto_confirms') === 'on',
                          }),
                        });
                        fetchAllState();
                      }}
                      className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs"
                    >
                      <div>
                        <label className="block font-bold mb-1">عنوان الحفل</label>
                        <input name="title" defaultValue={ev.title} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">التاريخ والوقت المعروض</label>
                        <input name="date_display" defaultValue={ev.date_display} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">المدينة</label>
                        <input name="city_display" defaultValue={ev.city_display} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">اسم القاعة / المكان</label>
                        <input name="venue_name" defaultValue={ev.venue_name} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">السعة الإجمالية (شاملاً المرافقين)</label>
                        <input
                          name="capacity_total"
                          type="number"
                          defaultValue={ev.capacity_total || 500}
                          className="w-full px-3 py-2 border border-stone-300"
                        />
                      </div>
                      <div className="flex items-center pt-5">
                        <label className="inline-flex items-center gap-2 font-bold cursor-pointer">
                          <input
                            type="checkbox"
                            name="approval_auto_confirms"
                            defaultChecked={Boolean(ev.approval_auto_confirms)}
                          />
                          اعتماد الطلب العام يؤكد الحضور ويصدر QR تلقائياً (approval_auto_confirms)
                        </label>
                      </div>
                      <div className="md:col-span-2 pt-2">
                        <button type="submit" className="px-6 py-2.5 bg-emerald-900 text-white font-bold cursor-pointer">
                          حفظ الإعدادات وتحديث صفحة /launch
                        </button>
                      </div>
                    </form>
                  </div>
                )}

                {/* Subtab: Email Queue Logs & Redacted Audit Log */}
                {adminTab === 'emails' && (
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-white border border-stone-200 p-6 space-y-3">
                      <h2 className="text-base font-extrabold text-stone-900 flex items-center gap-2">
                        <Mail className="w-5 h-5 text-emerald-900" />
                        طابور وسجل رسائل البريد الإلكتروني (tarbwy_email_logs)
                      </h2>
                      <div className="space-y-2 max-h-[380px] overflow-y-auto text-xs">
                        {stateData?.emailLogs?.map((m: any) => (
                          <div key={m.id} className="p-3 bg-[#FBF9F5] border border-stone-200">
                            <div className="flex justify-between font-bold">
                              <span>{m.subject}</span>
                              <span className="text-emerald-800">{m.status} (محاولات: {m.attempts})</span>
                            </div>
                            <p className="text-stone-500 font-mono text-[11px] mt-1">
                              القالب: {m.template_key} · المستلم المشفر: {m.recipient_hash.substring(0, 16)}... · الوقت: {m.sent_at}
                            </p>
                          </div>
                        ))}
                      </div>
                    </div>

                    <div className="bg-white border border-stone-200 p-6 space-y-3">
                      <h2 className="text-base font-extrabold text-stone-900 flex items-center gap-2">
                        <ShieldCheck className="w-5 h-5 text-emerald-900" />
                        سجل النشاط الأمني المنقح (tarbwy_activity_log — بدون أسرار أو رموز خام)
                      </h2>
                      <div className="space-y-2 max-h-[380px] overflow-y-auto text-xs">
                        {stateData?.activityLogs?.map((a: any) => (
                          <div key={a.id} className="p-3 bg-[#FBF9F5] border border-stone-200">
                            <div className="flex justify-between font-bold">
                              <span>{a.action} ({a.object_type} #{a.object_id})</span>
                              <span className="text-stone-500 font-mono">{a.created_at}</span>
                            </div>
                            <code className="block text-[11px] text-stone-600 mt-1">{a.metadata_json}</code>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </main>
      )}

      {/* =====================================================================
          ROUTE 6: DELIVERABLES, SOURCE CODE INSPECTOR & 21 MANDATORY QA TESTS
         ===================================================================== */}
      {activeRoute === 'deliverables' && (
        <main className="flex-1 py-8 px-6">
          <div className="max-w-[1320px] mx-auto space-y-8">
            {/* Deliverable Banner */}
            <div className="bg-white border border-stone-200 p-6 flex flex-wrap items-center justify-between gap-4">
              <div>
                <span className="text-xs font-bold text-[#9A3412]">الحزمة الإنتاجية الكاملة · متوافقة مع Windows Explorer و WordPress 6.4.3+ (PclZip & ZipArchive)</span>
                <h1 className="text-2xl font-extrabold text-stone-900">
                  حزمة التسليم النهائية: Tarbwy Event Manager v1.0.0
                </h1>
                <p className="text-xs text-stone-600 mt-1">
                  حجم ملف الـ ZIP الجاهز للتثبيت: <strong className="font-mono">{(pluginMeta.zipSize / 1024).toFixed(1)} KB</strong>
                  {pluginMeta.zipSha256 && (
                    <>
                      {' '}· بصمة التحقق SHA-256: <code className="font-mono text-[11px]">{pluginMeta.zipSha256.substring(0, 16)}...</code>
                    </>
                  )}
                </p>
              </div>
              <button
                type="button"
                onClick={handleDownloadZip}
                disabled={downloadingZip}
                className="inline-flex items-center gap-2 px-6 py-3 bg-emerald-900 text-white text-sm font-bold hover:bg-emerald-800 cursor-pointer disabled:opacity-60"
              >
                <Download className="w-4 h-4" />
                {downloadingZip ? 'جاري التحميل...' : 'تحميل tarbwy-event-manager.zip الآن'}
              </button>
            </div>

            {/* 21 Mandatory Tests Verification Matrix */}
            <section className="bg-white border border-stone-200 p-6">
              <h2 className="text-lg font-extrabold text-stone-900 mb-4 flex items-center gap-2">
                <ShieldCheck className="w-5 h-5 text-emerald-900" />
                نتائج تنفيذ الاختبارات الـ 21 الإلزامية (القسم 15 من المواصفات الفنية)
              </h2>
              <div className="overflow-x-auto">
                <table className="w-full text-xs border-collapse">
                  <thead>
                    <tr className="bg-stone-100 border-b border-stone-200 text-stone-700">
                      <th className="p-2.5 text-right">#</th>
                      <th className="p-2.5 text-right">بند الاختبار الإلزامي</th>
                      <th className="p-2.5 text-right">النتيجة</th>
                      <th className="p-2.5 text-right">التفاصيل والتحقق الفعلي</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pluginMeta.tests?.map((t: any) => (
                      <tr key={t.id} className="border-b border-stone-200">
                        <td className="p-2.5 font-mono font-bold">{t.id}</td>
                        <td className="p-2.5 font-bold text-stone-900">{t.name}</td>
                        <td className="p-2.5 font-bold text-emerald-800 whitespace-nowrap">{t.status}</td>
                        <td className="p-2.5 text-stone-600">{t.details}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            {/* Plugin Source Code & Documentation Explorer */}
            <section className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <div className="bg-white border border-stone-200 p-5">
                <h3 className="text-sm font-extrabold text-stone-900 mb-3 flex items-center gap-2">
                  <FileCode2 className="w-4 h-4 text-emerald-900" />
                  ملفات الإضافة والوثائق ({pluginMeta.files?.length || 0} ملف)
                </h3>
                <div className="space-y-1 max-h-[540px] overflow-y-auto text-xs">
                  {pluginMeta.files?.map((f: any) => (
                    <button
                      key={f.path}
                      type="button"
                      onClick={() => loadFileContent(f.path)}
                      className={`w-full text-right px-3 py-2 flex items-center justify-between cursor-pointer ${
                        selectedFile?.path === f.path
                          ? 'bg-emerald-900 text-white font-bold'
                          : 'hover:bg-stone-100 text-stone-800'
                      }`}
                    >
                      <span className="font-mono truncate" dir="ltr">{f.path}</span>
                      <span className="text-[10px] opacity-75 shrink-0">{(f.size / 1024).toFixed(1)} KB</span>
                    </button>
                  ))}
                </div>
              </div>

              <div className="lg:col-span-2 bg-white border border-stone-200 p-5 flex flex-col">
                <div className="flex items-center justify-between border-b border-stone-200 pb-3 mb-3">
                  <strong className="font-mono text-xs text-stone-900" dir="ltr">
                    tarbwy-event-manager/{selectedFile?.path}
                  </strong>
                  <span className="text-xs text-stone-500">كود مصدري كامل وجاهز للإنتاج بدون TODO</span>
                </div>
                <pre
                  dir="ltr"
                  className="flex-1 p-4 bg-stone-950 text-stone-100 text-xs font-mono overflow-x-auto max-h-[520px] leading-relaxed"
                >
                  {selectedFile?.content || 'اختر ملفاً لعرض محتواه...'}
                </pre>
              </div>
            </section>
          </div>
        </main>
      )}

      {/* Quiet Institutional Footer */}
      <footer className="bg-white border-t border-stone-200 py-6 px-6 text-xs text-stone-500 no-print">
        <div className="max-w-[1380px] mx-auto flex flex-wrap items-center justify-between gap-4">
          <div>
            <strong>Tarbwy Event Manager v1.0.0</strong> — إضافة WordPress مخصصة لحفل إطلاق «تربوي — الاتحاد العربي للقيادة التربوية» (tarbwy.org)
          </div>
          <div className="flex items-center gap-4">
            <span>العربية فقط · RTL · السعة 500 شامل المرافقين</span>
            <span>·</span>
            <button onClick={() => setActiveRoute('deliverables')} className="underline hover:text-stone-900 cursor-pointer">
              عرض تقرير الاختبارات والوثائق
            </button>
          </div>
        </div>
      </footer>
    </div>
  );
}
