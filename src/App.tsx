import React, { useState, useEffect, useCallback, useRef } from 'react';
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
  Copy,
  Camera,
  Check,
} from 'lucide-react';
import { LocalQRCode } from './components/LocalQRCode';
import { LaunchV2View } from './components/LaunchV2View';

type ActiveRoute = 'landing' | 'vip' | 'ticket' | 'checkin' | 'admin' | 'deliverables';
type AdminSubTab = 'overview' | 'requests' | 'guests' | 'vip' | 'tickets' | 'reports' | 'settings' | 'emails' | 'staff';
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
  const [copiedVipCode, setCopiedVipCode] = useState<string>('');

  // Ticket View state (/launch/ticket)
  const [activeTicketToken, setActiveTicketToken] = useState('');
  const [ticketViewData, setTicketViewData] = useState<any>(null);
  const [ticketViewError, setTicketViewError] = useState('');

  // Check-in state (/launch/checkin)
  const [scanTokenInput, setScanTokenInput] = useState('');
  const [checkinBanner, setCheckinBanner] = useState<any>(null);
  const [concurrentRaceResult, setConcurrentRaceResult] = useState<any>(null);
  const [checkinSearch, setCheckinSearch] = useState('');
  const [checkinFilter, setCheckinFilter] = useState<'all' | 'pending_checkin' | 'confirmed' | 'checked_in'>('all');
  const [checkinLookupResults, setCheckinLookupResults] = useState<any[]>([]);
  const [checkinMobileTab, setCheckinMobileTab] = useState<'scanner' | 'search' | 'history'>('scanner');
  const [checkinHistory, setCheckinHistory] = useState<any[]>([]);
  const [cameraActive, setCameraActive] = useState(false);
  const [cameraInitializing, setCameraInitializing] = useState(false);
  const [cameraFacing, setCameraFacing] = useState<'environment' | 'user'>('environment');
  const [cameraError, setCameraError] = useState('');
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const cameraStreamRef = useRef<MediaStream | null>(null);
  const cameraTimeoutRef = useRef<number | null>(null);
  const [simulateOffline, setSimulateOffline] = useState(false);

  const handleCopyCode = (code: string) => {
    const clean = String(code || '').trim();
    if (!clean) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(clean).catch(() => {});
    }
    setCopiedVipCode(clean);
    setTimeout(() => setCopiedVipCode(''), 2800);
  };

  // Admin Guest Search & Filters
  const [guestSearch, setGuestSearch] = useState('');
  const [guestTypeFilter, setGuestTypeFilter] = useState('all');
  const [rsvpFilter, setRsvpFilter] = useState('all');

  // Admin Partial Companion Selection map: { [registrationId]: number[] }
  const [partialCompanionMap, setPartialCompanionMap] = useState<Record<number, number[]>>({});
  const [resetDataFeedback, setResetDataFeedback] = useState<{ ok: boolean; text: string } | null>(null);

  // VIP CSV Import state
  const [csvText, setCsvText] = useState(
    'full_name,email,phone,organization,job_title,max_companions\nمعالي د. سامي بن فهد العبدالله,sami.vip@arab-edu.org,+966501239876,اتحاد الجامعات العربية,أمين عام مساعد,2\nأ.د. ريم بنت خالد الزهراني,reem.vip@edu.org,+966559876543,معهد القيادة التربوية,رئيسة المجلس العلمي,1'
  );
  const [csvImportReport, setCsvImportReport] = useState<any>(null);

  // Per-Person & Bulk Email Dispatcher + Email Studio state
  const [singleEmailGuestId, setSingleEmailGuestId] = useState<number>(1);
  const [singleEmailTemplate, setSingleEmailTemplate] = useState<string>('ticket_issued');
  const [bulkEmailSegment, setBulkEmailSegment] = useState<string>('confirmed');
  const [bulkEmailTemplate, setBulkEmailTemplate] = useState<string>('event_reminder');
  const [emailActionFeedback, setEmailActionFeedback] = useState<string>('');
  const [selectedPreviewTplKey, setSelectedPreviewTplKey] = useState<string>('ticket_issued');
  const [emailPreviewViewport, setEmailPreviewViewport] = useState<'desktop' | 'mobile' | 'plain'>('desktop');
  const [testEmailRecipient, setTestEmailRecipient] = useState<string>('events@tarbwy.org');

  // Staff & Role Management state
  const [newStaffName, setNewStaffName] = useState<string>('');
  const [newStaffEmail, setNewStaffEmail] = useState<string>('');
  const [newStaffRole, setNewStaffRole] = useState<string>('tarbwy_checkin_staff');
  const [staffActionFeedback, setStaffActionFeedback] = useState<string>('');

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
        // Set default VIP Personal Invitation Code if empty
        if (!vipTokenInput && data.invitations && data.invitations.length > 0) {
          const firstInv = data.invitations.find((i: any) => i.status === 'active') || data.invitations[0];
          if (firstInv) {
            setVipTokenInput(firstInv.personal_code || firstInv.demo_raw_token_preview);
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

  // Fetch Check-in Lookup Results (Search by Attendee Name, Email Address, or Ticket Number)
  const fetchCheckinLookup = useCallback(async (q = checkinSearch, filter = checkinFilter) => {
    try {
      const res = await fetch(
        `/api/tarbwy/v1/checkin/lookup?q=${encodeURIComponent(q.trim())}&filter=${encodeURIComponent(filter)}`
      );
      if (res.ok) {
        const data = await res.json();
        setCheckinLookupResults(data.results || []);
      }
    } catch {
      // ignore
    }
  }, [checkinSearch, checkinFilter]);

  useEffect(() => {
    fetchCheckinLookup(checkinSearch, checkinFilter);
  }, [checkinSearch, checkinFilter, stateData, fetchCheckinLookup]);

  const stopCameraScanner = useCallback(() => {
    if (cameraTimeoutRef.current) {
      window.clearTimeout(cameraTimeoutRef.current);
      cameraTimeoutRef.current = null;
    }
    if (cameraStreamRef.current) {
      cameraStreamRef.current.getTracks().forEach((t) => {
        try {
          t.stop();
        } catch {
          // ignore
        }
      });
      cameraStreamRef.current = null;
    }
    if (videoRef.current) {
      try {
        videoRef.current.pause();
        videoRef.current.srcObject = null;
      } catch {
        // ignore
      }
    }
    setCameraInitializing(false);
    setCameraActive(false);
  }, []);

  const startCameraWithFacing = async (facing: 'environment' | 'user') => {
    setCameraError('');
    setCameraInitializing(true);

    if (cameraTimeoutRef.current) {
      window.clearTimeout(cameraTimeoutRef.current);
    }
    cameraTimeoutRef.current = window.setTimeout(() => {
      stopCameraScanner();
      setCameraError(
        'استغرق تشغيل الكاميرا وقتاً أطول من المعتاد (انتهت مهلة الانتظار). يرجى التأكد من السماح بإذن الكاميرا في المتصفح وعدم انشغالها بتطبيق آخر، ثم المحاولة مجدداً.'
      );
    }, 9500);

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      stopCameraScanner();
      setCameraError('متصفح الجهاز لا يدعم تشغيل الكاميرا مباشرة أو يتطلب اتصال HTTPS آمن.');
      return;
    }

    const constraintsList: MediaStreamConstraints[] = [
      { video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
      { video: { facingMode: facing }, audio: false },
      { video: true, audio: false },
    ];

    let acquiredStream: MediaStream | null = null;
    let lastErr: any = null;

    for (const c of constraintsList) {
      try {
        acquiredStream = await navigator.mediaDevices.getUserMedia(c);
        if (acquiredStream) break;
      } catch (err: any) {
        lastErr = err;
        if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError')) {
          break;
        }
      }
    }

    if (!acquiredStream) {
      stopCameraScanner();
      const errName = lastErr?.name || '';
      if (errName === 'NotAllowedError' || errName === 'PermissionDeniedError') {
        setCameraError('تم رفض صلاحية الوصول إلى الكاميرا. يرجى السماح بالكاميرا من شريط العنوان في المتصفح ثم المحاولة مجدداً.');
      } else if (errName === 'NotFoundError') {
        setCameraError('لم يتم العثور على كاميرا متصلة بهذا الجهاز. يمكنك استخدام حقل المسح المباشر أو البحث اليدوي أدناه.');
      } else {
        setCameraError('تعذر الوصول إلى الكاميرا المباشرة. يرجى التأكد من منح صلاحية الكاميرا للمتصفح وعدم انشغالها بتطبيق آخر.');
      }
      return;
    }

    try {
      cameraStreamRef.current = acquiredStream;
      setCameraFacing(facing);
      setCameraActive(true);
      if (videoRef.current) {
        const v = videoRef.current;
        v.muted = true;
        v.playsInline = true;
        v.setAttribute('playsinline', '');
        v.setAttribute('webkit-playsinline', '');
        v.srcObject = acquiredStream;
        await v.play();
      }
      if (cameraTimeoutRef.current) {
        window.clearTimeout(cameraTimeoutRef.current);
        cameraTimeoutRef.current = null;
      }
      setCameraInitializing(false);
    } catch {
      stopCameraScanner();
      setCameraError('تعذر بدء عرض البث المباشر للكاميرا. يرجى الضغط على تشغيل الكاميرا للمحاولة مجدداً.');
    }
  };

  const toggleCameraScanner = async () => {
    if (cameraActive || cameraInitializing) {
      stopCameraScanner();
      return;
    }
    await startCameraWithFacing(cameraFacing);
  };

  const switchCameraFacing = async () => {
    const nextFacing = cameraFacing === 'environment' ? 'user' : 'environment';
    stopCameraScanner();
    await startCameraWithFacing(nextFacing);
  };

  useEffect(() => {
    if (activeRoute !== 'checkin' && cameraActive) {
      stopCameraScanner();
    }
  }, [activeRoute, cameraActive, stopCameraScanner]);

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
    setCheckinHistory((prev) => [
      {
        code: data.result_code,
        name: data.guest?.full_name || data.message || 'عملية مسح',
        ticket: data.guest?.ticket_reference || '',
        msg: data.message || '',
        time: new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      },
      ...prev.slice(0, 14),
    ]);
    fetchAllState();
    fetchCheckinLookup(checkinSearch, checkinFilter);
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
    fetchCheckinLookup(checkinSearch, checkinFilter);
  };

  // Manual Check-in
  const handleManualCheckin = async (guestId: number, ticketReference?: string) => {
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
      body: JSON.stringify({ guest_id: guestId, ticket_reference: ticketReference || '', device_label: 'Manual-Desk-1' }),
    });
    const data = await res.json();
    setCheckinBanner(data);
    setCheckinHistory((prev) => [
      {
        code: data.result_code,
        name: data.guest?.full_name || data.message || 'تسجيل يدوي',
        ticket: data.guest?.ticket_reference || ticketReference || '',
        msg: data.message || '',
        time: new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      },
      ...prev.slice(0, 14),
    ]);
    fetchAllState();
    fetchCheckinLookup(checkinSearch, checkinFilter);
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

  // Send Single Email Per Person
  const handleSendSingleEmail = async (guestId = singleEmailGuestId, templateKey = singleEmailTemplate) => {
    setEmailActionFeedback('');
    const res = await fetch('/api/tarbwy/v1/emails/send-single', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ guest_id: guestId, template_key: templateKey }),
    });
    const data = await res.json();
    setEmailActionFeedback(data.message || 'تم تنفيذ الإجراء.');
    fetchAllState();
  };

  // Send Bulk Email to Segment
  const handleSendBulkEmail = async (e: React.FormEvent) => {
    e.preventDefault();
    setEmailActionFeedback('');
    const res = await fetch('/api/tarbwy/v1/emails/send-bulk', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ target_segment: bulkEmailSegment, template_key: bulkEmailTemplate }),
    });
    const data = await res.json();
    setEmailActionFeedback(data.message || 'تم إرسال الحملة البريدية.');
    fetchAllState();
  };

  // Send Test Email with Verified Sender Identity
  const handleSendTestEmail = async (e: React.FormEvent) => {
    e.preventDefault();
    setEmailActionFeedback('');
    const res = await fetch('/api/tarbwy/v1/emails/send-test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ test_email: testEmailRecipient, template_key: selectedPreviewTplKey }),
    });
    const data = await res.json();
    setEmailActionFeedback(data.message || 'تم إرسال الرسالة التجريبية.');
    fetchAllState();
  };

  // Save Updated Email Template
  const handleSaveEmailTemplate = async (
    templateKey: string,
    payload: { subject: string; heading: string; badge: string; body: string; cta_label: string }
  ) => {
    setEmailActionFeedback('');
    const res = await fetch('/api/tarbwy/v1/emails/templates', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ template_key: templateKey, ...payload }),
    });
    const data = await res.json();
    setEmailActionFeedback(data.message || 'تم حفظ القالب.');
    fetchAllState();
  };

  // Restore All 11 Default Email Templates
  const handleRestoreEmailTemplates = async () => {
    setEmailActionFeedback('');
    const res = await fetch('/api/tarbwy/v1/emails/restore-defaults', { method: 'POST' });
    const data = await res.json();
    setEmailActionFeedback(data.message || 'تمت استعادة القوالب الافتراضية.');
    fetchAllState();
  };

  // Update or Create Staff Member Role
  const handleAssignStaffRole = async (userId: number, role: string) => {
    setStaffActionFeedback('');
    const res = await fetch('/api/tarbwy/v1/staff/assign', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: userId, role }),
    });
    const data = await res.json();
    setStaffActionFeedback(data.message || 'تم تحديث الدور.');
    fetchAllState();
  };

  const handleAddNewStaff = async (e: React.FormEvent) => {
    e.preventDefault();
    setStaffActionFeedback('');
    const res = await fetch('/api/tarbwy/v1/staff/assign', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        display_name: newStaffName,
        email: newStaffEmail,
        role: newStaffRole,
      }),
    });
    const data = await res.json();
    setStaffActionFeedback(data.message || 'تمت إضافة الموظف.');
    if (res.ok) {
      setNewStaffName('');
      setNewStaffEmail('');
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

          <nav className="hidden lg:flex items-center gap-5 text-sm font-semibold text-stone-600">
            <button
              onClick={() => setActiveRoute('landing')}
              className={`hover:text-emerald-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 inline-flex items-center gap-1.5 ${
                activeRoute === 'landing' ? 'text-emerald-900 border-b-2 border-emerald-900 font-extrabold' : ''
              }`}
            >
              <span className="inline-block w-2 h-2 rounded-full bg-emerald-600" />
              <span>صفحة الحفل الرسمية (/launch)</span>
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
        <button onClick={() => setActiveRoute('landing')} className="px-3 py-1.5 bg-emerald-900 text-white border border-emerald-900 whitespace-nowrap shrink-0">
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
          ROUTE 1: OFFICIAL PRODUCTION LANDING PAGE (/launch — v2.0)
         ===================================================================== */}
      {activeRoute === 'landing' && (
        <LaunchV2View
          event={ev}
          regName={regName}
          setRegName={setRegName}
          regEmail={regEmail}
          setRegEmail={setRegEmail}
          regPhone={regPhone}
          setRegPhone={setRegPhone}
          regOrg={regOrg}
          setRegOrg={setRegOrg}
          regTitle={regTitle}
          setRegTitle={setRegTitle}
          regDietary={regDietary}
          setRegDietary={setRegDietary}
          regHoneypot={regHoneypot}
          setRegHoneypot={setRegHoneypot}
          regCompanions={regCompanions}
          setRegCompanions={setRegCompanions}
          regFeedback={regFeedback}
          regSubmitting={regSubmitting}
          onSubmitRegistration={handlePublicRegSubmit}
          onOpenAdminSettings={() => {
            setActiveRoute('admin');
            setAdminTab('settings');
          }}
        />
      )}

      {/* =====================================================================
          ROUTE 2: PRIVATE VIP INVITATION & RSVP (/launch/vip)
         ===================================================================== */}
      {activeRoute === 'vip' && (
        <main className="flex-1 py-12 px-6">
          <div className="max-w-[780px] mx-auto bg-white border border-stone-200 p-8">
            <span className="block text-xs font-bold text-[#9A3412] mb-1">
              المسار الخاص: /launch/vip · رمز الدعوة الشخصي المستقل (VIP-XXXX-XXXX) · SHA-256 Token Hash
            </span>
            <h1 className="text-2xl font-extrabold text-stone-900 mb-2">بوابة الدعوات الخاصة لكبار الشخصيات (VIP RSVP)</h1>
            <p className="text-xs text-stone-600 mb-6">
              أدخل <strong>رمز الدعوة الشخصي</strong> المكون من 8 محارف (مثال: <code className="font-mono font-bold text-[#9A3412]">VIP-XXXX-XXXX</code>) المرسل في بريد الدعوة الرسمي دون الحاجة لنسخ أو استخراج أي رابط طويل.
            </p>

            {copiedVipCode && (
              <div className="mb-5 p-3.5 bg-emerald-50 border border-emerald-700 text-emerald-950 text-xs font-bold flex items-center justify-between">
                <span>✓ تم نسخ رمز الدعوة الشخصي ({copiedVipCode}) إلى الحافظة بنجاح!</span>
                <button type="button" onClick={() => setCopiedVipCode('')} className="underline cursor-pointer">
                  إغلاق
                </button>
              </div>
            )}

            {/* Quick Selector for Demo VIP Invitations with Standalone Personal Codes */}
            {stateData?.invitations?.length > 0 && (
              <div className="mb-6 p-4 bg-[#F4EFE6] border border-stone-300 text-xs">
                <strong className="block text-stone-800 mb-2">رموز الدعوات الشخصية (Personal Invitation Codes) المتاحة للتجربة الفورية:</strong>
                <div className="flex flex-wrap gap-2">
                  {stateData.invitations.map((inv: any) => {
                    const pCode = inv.personal_code || inv.demo_raw_token_preview;
                    return (
                      <div key={inv.id} className="inline-flex items-center bg-white border border-stone-300">
                        <button
                          type="button"
                          onClick={() => {
                            setVipTokenInput(pCode);
                            handleVerifyVipToken(pCode);
                          }}
                          className="px-3 py-1.5 hover:bg-stone-50 text-stone-800 font-semibold cursor-pointer flex items-center gap-2"
                        >
                          <span>{inv.guest?.full_name}</span>
                          <code className="font-mono font-extrabold text-[#9A3412]" dir="ltr">
                            {pCode}
                          </code>
                          <span className="text-[11px] text-stone-500">({inv.status})</span>
                        </button>
                        <button
                          type="button"
                          onClick={() => handleCopyCode(pCode)}
                          title="نسخ رمز الدعوة الشخصي"
                          className="px-2.5 py-1.5 border-r border-stone-200 bg-amber-50 hover:bg-amber-100 text-[#9A3412] font-bold cursor-pointer flex items-center gap-1"
                        >
                          <Copy className="w-3.5 h-3.5" />
                          <span>نسخ الرمز</span>
                        </button>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            <div className="flex flex-col sm:flex-row gap-3 mb-6">
              <input
                type="text"
                dir="ltr"
                value={vipTokenInput}
                onChange={(e) => setVipTokenInput(e.target.value)}
                placeholder="VIP-XXXX-XXXX"
                className="flex-1 px-4 py-3 text-base font-mono font-extrabold tracking-widest text-center border-2 border-stone-300 focus:border-emerald-900 bg-[#FBF9F5]"
              />
              <button
                type="button"
                onClick={() => handleCopyCode(vipTokenInput)}
                className="px-4 py-3 bg-amber-50 border border-amber-700/40 text-[#9A3412] text-xs font-bold hover:bg-amber-100 cursor-pointer whitespace-nowrap shrink-0 inline-flex items-center justify-center gap-1.5"
              >
                <Copy className="w-4 h-4" />
                نسخ الرمز
              </button>
              <button
                type="button"
                onClick={() => handleVerifyVipToken(vipTokenInput)}
                className="px-6 py-3 bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 cursor-pointer whitespace-nowrap shrink-0"
              >
                التحقق من الرمز وفتح الدعوة
              </button>
            </div>

            {vipError && (
              <div className="p-4 bg-red-50 border border-red-700 text-red-950 text-sm mb-6">
                <strong>رفض التحقق الأمني:</strong> {vipError}
              </div>
            )}

            {vipLoaded && (
              <div className="border-t border-stone-200 pt-6">
                <div className="p-4 bg-amber-50/80 border border-amber-600/40 mb-4 flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <span className="block text-[11px] font-bold text-[#9A3412]">
                      رمز الدعوة الشخصي المعتمد (Personal Invitation Code)
                    </span>
                    <code className="text-lg font-mono font-extrabold tracking-widest text-stone-900" dir="ltr">
                      {vipLoaded.personal_code || vipTokenInput}
                    </code>
                  </div>
                  <button
                    type="button"
                    onClick={() => handleCopyCode(vipLoaded.personal_code || vipTokenInput)}
                    className="px-3.5 py-2 bg-[#9A3412] text-white text-xs font-bold hover:bg-[#7C2D12] cursor-pointer inline-flex items-center gap-1.5"
                  >
                    {copiedVipCode === (vipLoaded.personal_code || vipTokenInput) ? (
                      <>
                        <Check className="w-4 h-4" />
                        تم نسخ الرمز!
                      </>
                    ) : (
                      <>
                        <Copy className="w-4 h-4" />
                        نسخ رمز الدعوة الشخصي
                      </>
                    )}
                  </button>
                </div>

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
          ROUTE 4: STAFF CHECK-IN INTERFACE (/launch/checkin) — Android App UI
         ===================================================================== */}
      {activeRoute === 'checkin' && (
        <main className="flex-1 py-6 px-4 md:px-6 bg-stone-100">
          <div className="max-w-[960px] mx-auto space-y-5">
            {/* Android-Style Dark Emerald App Bar */}
            <div className="bg-[#0B291E] text-white rounded-2xl p-4 md:p-5 shadow-md border border-emerald-900/60">
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                  <div className="w-11 h-11 rounded-xl bg-amber-600/20 border border-amber-500/40 flex items-center justify-center text-amber-300">
                    <QrCode className="w-6 h-6" />
                  </div>
                  <div>
                    <span className="text-[11px] font-bold text-amber-300 block">
                      تطبيق الاستقبال الذكي · /launch/checkin (Android & iOS Ready)
                    </span>
                    <h1 className="text-lg md:text-xl font-extrabold text-white">
                      بوابة استقبال وتسجيل دخول الضيوف
                    </h1>
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-2.5 text-xs">
                  <span
                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full font-bold border ${
                      simulateOffline
                        ? 'bg-red-950/90 border-red-500 text-red-200'
                        : 'bg-emerald-900/80 border-emerald-500/40 text-emerald-100'
                    }`}
                  >
                    <span
                      className={`w-2 h-2 rounded-full ${
                        simulateOffline ? 'bg-red-400 animate-ping' : 'bg-emerald-400'
                      }`}
                    />
                    {simulateOffline ? 'غير متصل' : 'متصل بالخادم'}
                  </span>
                </div>
              </div>
            </div>

            {/* Server Check-in Verification Banner */}
            {checkinBanner && (
              <div
                className={`p-5 rounded-xl border-2 shadow-sm ${
                  checkinBanner.result_code === 'valid'
                    ? 'bg-emerald-50 border-emerald-700 text-emerald-950'
                    : checkinBanner.result_code === 'already_checked_in'
                    ? 'bg-amber-50 border-amber-700 text-amber-950'
                    : 'bg-red-50 border-red-700 text-red-950'
                }`}
              >
                <div className="flex items-center justify-between gap-2 font-extrabold text-base mb-1">
                  <div className="flex items-center gap-2">
                    {checkinBanner.result_code === 'valid' && <CheckCircle2 className="w-6 h-6 text-emerald-800 shrink-0" />}
                    {checkinBanner.result_code === 'already_checked_in' && (
                      <AlertTriangle className="w-6 h-6 text-amber-800 shrink-0" />
                    )}
                    {checkinBanner.result_code !== 'valid' && checkinBanner.result_code !== 'already_checked_in' && (
                      <XCircle className="w-6 h-6 text-red-800 shrink-0" />
                    )}
                    <span>{checkinBanner.message}</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setCheckinBanner(null)}
                    className="text-xs px-2.5 py-1 bg-white/80 rounded border border-stone-300 cursor-pointer"
                  >
                    إغلاق
                  </button>
                </div>
                {checkinBanner.guest && (
                  <p className="text-xs mt-1.5">
                    الضيف: <strong>{checkinBanner.guest.full_name}</strong> · الفئة: {checkinBanner.guest.guest_type} · رقم التذكرة:{' '}
                    <code className="font-mono font-bold">{checkinBanner.guest.ticket_reference}</code>
                    {checkinBanner.guest.parent_name && ` · مرافق لـ: ${checkinBanner.guest.parent_name}`}
                  </p>
                )}
              </div>
            )}

            {/* Simultaneous 2-Device Race Condition Result */}
            {concurrentRaceResult && (
              <div className="p-5 bg-white rounded-xl border-2 border-stone-900 space-y-3">
                <h3 className="text-sm font-extrabold text-stone-900">
                  نتيجة اختبار المسح المتزامن لنفس البطاقة من جهازين في اللحظة نفسها (Section 9 & 15.11):
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                  <div className="p-3.5 bg-emerald-50 border border-emerald-700 rounded-lg">
                    <strong className="block text-emerald-950 mb-1">{concurrentRaceResult.deviceA.device}</strong>
                    <span className="font-mono">
                      HTTP {concurrentRaceResult.deviceA.httpStatus} — {concurrentRaceResult.deviceA.result_code}
                    </span>
                    <p className="mt-1">{concurrentRaceResult.deviceA.message}</p>
                  </div>
                  <div className="p-3.5 bg-amber-50 border border-amber-700 rounded-lg">
                    <strong className="block text-amber-950 mb-1">{concurrentRaceResult.deviceB.device}</strong>
                    <span className="font-mono">
                      HTTP {concurrentRaceResult.deviceB.httpStatus} — {concurrentRaceResult.deviceB.result_code}
                    </span>
                    <p className="mt-1">{concurrentRaceResult.deviceB.message}</p>
                  </div>
                </div>
              </div>
            )}

            {/* 1. FIRST & MOST PROMINENT SECTION: Smart Camera QR Scanner (Android & iOS) */}
            <section className="bg-white rounded-2xl border-2 border-emerald-900 p-5 space-y-4 shadow-md">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <span className="block text-xs font-extrabold text-[#9A3412] mb-0.5">
                    ماسح الكاميرا الذكي · Android &amp; iOS
                  </span>
                  <h2 className="text-base md:text-lg font-extrabold text-stone-900 flex items-center gap-2">
                    <Camera className="w-5 h-5 text-emerald-900" />
                    مسح رمز الاستجابة السريعة (QR Scanner)
                  </h2>
                </div>
                <span
                  className={`px-3 py-1 rounded-full text-xs font-bold border ${
                    cameraActive
                      ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                      : 'bg-stone-100 text-stone-700 border-stone-300'
                  }`}
                >
                  {cameraActive ? '🟢 الكاميرا نشطة (Scanning)' : 'جاهز للتشغيل'}
                </span>
              </div>

              {/* Camera Viewport Box (Shown when cameraActive or cameraInitializing is true) */}
              <div
                className={`relative bg-stone-950 rounded-xl overflow-hidden aspect-[4/3] max-h-[380px] flex flex-col items-center justify-center border-2 border-emerald-700 text-center p-4 ${
                  cameraActive || cameraInitializing ? 'block' : 'hidden'
                }`}
              >
                <video
                  ref={videoRef}
                  playsInline
                  autoPlay
                  muted
                  className="absolute inset-0 w-full h-full object-cover block"
                />
                <div className="relative z-10 w-48 h-48 border-2 border-emerald-400/90 rounded-2xl pointer-events-none shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]" />
                <div className="absolute bottom-3 inset-x-3 z-20 flex items-center justify-between gap-2 bg-stone-950/80 px-3 py-2 rounded-lg text-xs text-white">
                  <span>
                    {cameraInitializing
                      ? '⏳ جاري تهيئة الكاميرا وطلب الإذن...'
                      : 'الكاميرا نشطة — وجّه رمز QR داخل المربع'}
                  </span>
                  {cameraActive && (
                    <button
                      type="button"
                      onClick={switchCameraFacing}
                      className="px-2.5 py-1 rounded bg-white/15 hover:bg-white/25 text-white font-bold cursor-pointer"
                    >
                      🔄 تبديل الكاميرا
                    </button>
                  )}
                </div>
              </div>

              {cameraError && (
                <div className="p-3.5 bg-amber-50 border border-amber-400 rounded-lg text-xs text-amber-950 font-semibold">
                  {cameraError}
                </div>
              )}

              {/* Primary Scanner Action Button */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={toggleCameraScanner}
                  className={`w-full py-3.5 rounded-xl font-extrabold text-sm cursor-pointer flex items-center justify-center gap-2 shadow-xs ${
                    cameraActive || cameraInitializing
                      ? 'bg-red-700 hover:bg-red-800 text-white'
                      : 'bg-[#0F3D22] hover:bg-emerald-900 text-white'
                  }`}
                >
                  <Camera className="w-4 h-4" />
                  {cameraInitializing
                    ? '⏳ جاري تشغيل الكاميرا... (اضغط للإلغاء)'
                    : cameraActive
                    ? '⏹ إيقاف كاميرا المسح المؤقت'
                    : '📷 تشغيل كاميرا المسح المباشر'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    const firstTicket = stateData?.tickets?.[0];
                    if (firstTicket?.demo_raw_token_preview) {
                      handleExecuteScan(firstTicket.demo_raw_token_preview);
                    }
                  }}
                  className="w-full py-3.5 rounded-xl font-extrabold text-xs border-2 border-stone-300 bg-stone-50 hover:border-emerald-900 text-stone-900 cursor-pointer flex items-center justify-center gap-2"
                >
                  🖼️ التقاط / محاكاة مسح فوري لبطاقة QR
                </button>
              </div>

              {/* Manual / Hardware Barcode Scanner Input */}
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  if (scanTokenInput.trim()) {
                    handleExecuteScan(scanTokenInput);
                    setScanTokenInput('');
                  }
                }}
                className="pt-3 border-t border-stone-200 space-y-2"
              >
                <label className="block text-xs font-bold text-stone-700">
                  إدخال رمز التذكرة أو القارئ الليزري (USB Scanner / رقم التذكرة <code dir="ltr">TKT-...</code>):
                </label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    dir="ltr"
                    value={scanTokenInput}
                    onChange={(e) => setScanTokenInput(e.target.value)}
                    placeholder="TKT-2026-XXXXXX أو امسح بالقارئ..."
                    className="flex-1 px-3.5 py-2.5 text-xs font-mono border border-stone-300 rounded-xl bg-[#FBF9F5]"
                  />
                  <button
                    type="submit"
                    className="px-5 py-2.5 bg-[#0F3D22] text-white text-xs font-bold rounded-xl cursor-pointer shrink-0"
                  >
                    تحقق وتسجيل
                  </button>
                </div>
              </form>

              {/* Instant Simulation Cards for All Issued Tickets */}
              <div className="pt-3 border-t border-stone-200 space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <strong className="text-stone-800">محاكاة مسح التذاكر الصادرة أو اختبار التزامن:</strong>
                  <button
                    type="button"
                    onClick={() => handleExecuteScan('0000000000000000000000000000000000000000')}
                    className="text-[11px] font-bold text-red-800 underline cursor-pointer"
                  >
                    اختبار رمز غير معروف
                  </button>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-[220px] overflow-y-auto">
                  {stateData?.tickets?.map((t: any) => (
                    <div
                      key={t.id}
                      className="p-3 bg-[#FBF9F5] border border-stone-200 rounded-xl flex items-center justify-between gap-2 text-xs"
                    >
                      <div>
                        <strong className="block text-stone-900">{t.guest?.full_name}</strong>
                        <span className="text-stone-500 font-mono text-[11px]">
                          {t.ticket_reference} · {t.status} · {t.guest?.checkin_status}
                        </span>
                      </div>
                      <div className="flex items-center gap-1.5 shrink-0">
                        <button
                          type="button"
                          onClick={() => handleExecuteScan(t.demo_raw_token_preview)}
                          className="px-3 py-1.5 bg-emerald-900 text-white rounded-lg font-bold hover:bg-emerald-800 cursor-pointer"
                        >
                          مسح QR
                        </button>
                        <button
                          type="button"
                          onClick={() => handleSimulateConcurrentRace(t.demo_raw_token_preview)}
                          className="px-2.5 py-1.5 bg-stone-800 text-white rounded-lg font-bold hover:bg-stone-700 cursor-pointer"
                          title="اختبار مسح نفس البطاقة من جهازين في نفس اللحظة"
                        >
                          مسح متزامن
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </section>

            {/* 2. SECOND SECTION: Manual Attendee Search by Name, Email, and Ticket Number */}
            <section className="bg-white rounded-2xl border border-stone-200 p-5 space-y-4 shadow-xs">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <span className="block text-xs font-extrabold text-[#9A3412] mb-0.5">
                    البحث الفوري والتحضير اليدوي
                  </span>
                  <h2 className="text-base font-extrabold text-stone-900 flex items-center gap-2">
                    <Search className="w-5 h-5 text-emerald-900" />
                    بحث عن مشارك (الاسم، البريد، رقم التذكرة)
                  </h2>
                </div>
                <span className="px-2.5 py-1 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-full text-xs font-extrabold">
                  {checkinLookupResults.length} نتائج
                </span>
              </div>

              {/* Search Input Box */}
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  fetchCheckinLookup(checkinSearch, checkinFilter);
                }}
                className="flex gap-2"
              >
                <div className="relative flex-1">
                  <input
                    type="search"
                    value={checkinSearch}
                    onChange={(e) => setCheckinSearch(e.target.value)}
                    placeholder="ابحث بالاسم، البريد الإلكتروني، أو رقم التذكرة (TKT-2026-...)..."
                    className="w-full px-4 py-3 text-xs border-2 border-stone-300 focus:border-emerald-900 rounded-xl bg-[#FBF9F5] font-semibold"
                  />
                  {checkinSearch && (
                    <button
                      type="button"
                      onClick={() => setCheckinSearch('')}
                      className="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-stone-500 hover:text-stone-900 cursor-pointer"
                    >
                      ✕
                    </button>
                  )}
                </div>
                <button
                  type="submit"
                  className="px-5 py-3 bg-emerald-900 text-white text-xs font-extrabold rounded-xl hover:bg-emerald-800 cursor-pointer shrink-0"
                >
                  بحث
                </button>
              </form>

              {/* Quick Filter Chips */}
              <div className="flex flex-wrap gap-2">
                {[
                  { key: 'pending_checkin', label: '⏳ بانتظار الدخول' },
                  { key: 'confirmed', label: '✓ جميع المؤكدين' },
                  { key: 'checked_in', label: '🟢 حضروا فعلياً' },
                  { key: 'all', label: '👥 كل المسجلين' },
                ].map((chip) => (
                  <button
                    key={chip.key}
                    type="button"
                    onClick={() => setCheckinFilter(chip.key as any)}
                    className={`px-3 py-1.5 rounded-full text-xs font-bold border cursor-pointer transition-colors ${
                      checkinFilter === chip.key
                        ? 'bg-emerald-900 text-white border-emerald-900'
                        : 'bg-[#FBF9F5] text-stone-700 border-stone-300 hover:border-emerald-800'
                    }`}
                  >
                    {chip.label}
                  </button>
                ))}
              </div>

              {/* Attendee Search Results Directory */}
              <div className="space-y-2.5 max-h-[430px] overflow-y-auto pr-0.5">
                {checkinLookupResults.length === 0 ? (
                  <div className="p-8 text-center bg-[#FBF9F5] border border-stone-200 rounded-xl text-xs text-stone-500">
                    لا توجد نتائج مطابقة للبحث الحالي. جرّب البحث بجزء من الاسم أو البريد أو رقم التذكرة أو اختر فلتر «👥 كل المسجلين».
                  </div>
                ) : (
                  checkinLookupResults.map((g: any) => {
                    const isCheckedIn = g.checkin_status === 'checked_in';
                    const isConfirmed = g.rsvp_status === 'confirmed';
                    return (
                      <div
                        key={g.guest_id}
                        className={`p-3.5 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs ${
                          isCheckedIn
                            ? 'bg-emerald-50/50 border-emerald-300'
                            : 'bg-[#FBF9F5] border-stone-200 hover:border-stone-300'
                        }`}
                      >
                        <div className="space-y-1">
                          <div className="flex flex-wrap items-center gap-2">
                            <strong className="text-sm font-extrabold text-stone-900">{g.full_name}</strong>
                            <span
                              className={`px-2 py-0.5 rounded text-[11px] font-bold ${
                                g.guest_type === 'vip'
                                  ? 'bg-amber-100 text-amber-950'
                                  : g.guest_type === 'companion'
                                  ? 'bg-stone-200 text-stone-800'
                                  : 'bg-emerald-100 text-emerald-950'
                              }`}
                            >
                              {g.guest_type === 'vip' ? 'VIP' : g.guest_type === 'companion' ? 'مرافق' : 'عام'}
                            </span>
                            <span
                              className={`px-2 py-0.5 rounded text-[11px] font-bold ${
                                isConfirmed ? 'bg-emerald-900 text-white' : 'bg-stone-300 text-stone-800'
                              }`}
                            >
                              {isConfirmed ? 'مؤكد' : g.rsvp_status}
                            </span>
                          </div>
                          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-stone-600 text-[11px]">
                            <span>
                              التذكرة:{' '}
                              <code className="font-mono font-bold text-stone-900" dir="ltr">
                                {g.ticket_reference || 'تصدر تلقائياً عند الدخول'}
                              </code>
                            </span>
                            {g.email && (
                              <span>
                                البريد:{' '}
                                <span className="font-mono" dir="ltr">
                                  {g.email}
                                </span>
                              </span>
                            )}
                            {g.organization && <span>الجهة: {g.organization}</span>}
                            {g.parent_name && <span>مرافق لـ: {g.parent_name}</span>}
                          </div>
                        </div>

                        <div className="shrink-0">
                          {isCheckedIn ? (
                            <button
                              type="button"
                              onClick={() => handleManualCheckin(g.guest_id, g.ticket_reference)}
                              className="w-full sm:w-auto px-3.5 py-2 bg-emerald-100 border border-emerald-600 text-emerald-950 font-extrabold rounded-lg cursor-pointer"
                              title="اضغط لاختبار منع التكرار"
                            >
                              ✓ تم الدخول ({g.checked_in_at ? g.checked_in_at.slice(11, 16) : 'مسجل'})
                            </button>
                          ) : (
                            <button
                              type="button"
                              onClick={() => handleManualCheckin(g.guest_id, g.ticket_reference)}
                              className="w-full sm:w-auto px-4 py-2.5 bg-emerald-900 hover:bg-emerald-800 text-white font-extrabold rounded-lg cursor-pointer"
                            >
                              تسجيل دخول يدوي
                            </button>
                          )}
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </section>

            {/* 3. THIRD SECTION: Live Attendance Progress Bar & 4 KPI Pills */}
            <section className="bg-white rounded-2xl border border-stone-200 p-5 space-y-3 shadow-xs">
              <div className="flex items-center justify-between text-xs text-stone-600">
                <div>
                  <span className="block text-[11px] font-bold text-stone-500">
                    مؤشر الحضور الفعلي المباشر (Live Attendance)
                  </span>
                  <strong className="text-sm text-stone-900">
                    نسبة الحضور الفعلي ({stats.checked_in || 0} من أصل {stats.confirmed || 0} مؤكدين)
                  </strong>
                </div>
                <strong className="text-lg font-extrabold font-mono text-emerald-900">
                  {stats.confirmed > 0 ? Math.min(100, Math.round(((stats.checked_in || 0) / stats.confirmed) * 100)) : 0}%
                </strong>
              </div>
              <div className="w-full h-2.5 bg-stone-200 rounded-full overflow-hidden">
                <div
                  className="h-full bg-gradient-to-l from-emerald-700 to-emerald-950 transition-all duration-300"
                  style={{
                    width: `${
                      stats.confirmed > 0 ? Math.min(100, Math.round(((stats.checked_in || 0) / stats.confirmed) * 100)) : 0
                    }%`,
                  }}
                />
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                <div className="bg-stone-50 border border-stone-200 rounded-xl p-3">
                  <span className="block text-[11px] font-bold text-stone-600">المؤكدون (Confirmed)</span>
                  <strong className="text-xl font-extrabold font-mono text-stone-900">{stats.confirmed || 0}</strong>
                </div>
                <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                  <span className="block text-[11px] font-bold text-emerald-900">حضروا فعلياً (Checked-in)</span>
                  <strong className="text-xl font-extrabold font-mono text-emerald-800">{stats.checked_in || 0}</strong>
                </div>
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-3">
                  <span className="block text-[11px] font-bold text-amber-900">المتبقي (Remaining)</span>
                  <strong className="text-xl font-extrabold font-mono text-amber-800">{stats.remaining || 0}</strong>
                </div>
                <div className="bg-stone-50 border border-stone-200 rounded-xl p-3">
                  <span className="block text-[11px] font-bold text-stone-600">السعة المعتمدة</span>
                  <strong className="text-xl font-extrabold font-mono text-stone-900">{stats.capacity_total || 500}</strong>
                </div>
              </div>
            </section>

            {/* 4. FOURTH SECTION: Recent Session Scans Log */}
            <section className="bg-white rounded-2xl border border-stone-200 p-5 space-y-3 shadow-xs">
              <div className="flex items-center justify-between text-xs">
                <div>
                  <span className="block text-[11px] font-extrabold text-[#9A3412]">
                    سجل عمليات البوابة الحالية ({checkinHistory.length})
                  </span>
                  <strong className="text-sm font-extrabold text-stone-900">
                    آخر عمليات المسح والتحضير في هذه الجلسة
                  </strong>
                </div>
                {checkinHistory.length > 0 && (
                  <button
                    type="button"
                    onClick={() => setCheckinHistory([])}
                    className="text-xs px-3 py-1.5 bg-stone-100 rounded-lg border border-stone-300 text-stone-700 font-bold cursor-pointer"
                  >
                    مسح السجل المحلي
                  </button>
                )}
              </div>
              {checkinHistory.length === 0 ? (
                <p className="text-xs text-stone-500">لم يتم إجراء عمليات مسح في هذه الجلسة بعد.</p>
              ) : (
                <div className="space-y-1.5 max-h-[180px] overflow-y-auto">
                  {checkinHistory.map((item, idx) => (
                    <div
                      key={idx}
                      className="p-2.5 bg-stone-50 border border-stone-200 rounded-lg flex items-center justify-between text-xs"
                    >
                      <div>
                        <strong>{item.name}</strong>{' '}
                        {item.ticket && <code className="text-[11px] text-emerald-900">{item.ticket}</code>}
                      </div>
                      <span className="text-[11px] font-mono text-stone-500">{item.time}</span>
                    </div>
                  ))}
                </div>
              )}
            </section>
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
                        إرسال البريد (فردي / جماعي) والسجل
                      </button>
                      <button
                        onClick={() => setAdminTab('staff')}
                        className={`px-4 py-2 cursor-pointer ${
                          adminTab === 'staff' ? 'bg-emerald-900 text-white' : 'bg-white border border-stone-200 text-stone-700'
                        }`}
                      >
                        الموظفون والصلاحيات (Staff & Roles)
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
                                    <button
                                      type="button"
                                      onClick={() => {
                                        setSingleEmailGuestId(g.id);
                                        setSingleEmailTemplate(g.rsvp_status === 'confirmed' ? 'ticket_issued' : 'event_reminder');
                                        setAdminTab('emails');
                                        handleSendSingleEmail(g.id, g.rsvp_status === 'confirmed' ? 'ticket_issued' : 'event_reminder');
                                      }}
                                      className="inline-flex items-center gap-1 px-2.5 py-1 border border-emerald-800 text-emerald-900 bg-emerald-50 hover:bg-emerald-100 font-bold cursor-pointer"
                                      title="إرسال بريد إلكتروني مباشر لهذا الشخص"
                                    >
                                      <Mail className="w-3 h-3" />
                                      إرسال بريد فردي
                                    </button>
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
                        {stateData?.invitations?.map((inv: any) => {
                          const pCode = inv.personal_code || inv.demo_raw_token_preview;
                          return (
                            <div key={inv.id} className="p-4 bg-[#FBF9F5] border border-stone-200 text-xs space-y-2">
                              <div className="flex justify-between items-center">
                                <strong className="text-sm text-stone-900">{inv.guest?.full_name}</strong>
                                <span className="font-mono font-bold text-[#9A3412]">حالة الدعوة: {inv.status}</span>
                              </div>
                              <p className="text-stone-600">
                                {inv.guest?.job_title} — {inv.guest?.organization} · الحد الأقصى للمرافقين: {inv.max_companions_override}
                              </p>
                              <div className="p-2.5 bg-amber-50/90 border border-amber-600/30 flex items-center justify-between gap-2">
                                <div>
                                  <span className="block text-[10px] font-bold text-[#9A3412]">رمز الدعوة الشخصي (Personal Invitation Code):</span>
                                  <code className="font-mono text-sm font-extrabold text-stone-900" dir="ltr">
                                    {pCode}
                                  </code>
                                </div>
                                <button
                                  type="button"
                                  onClick={() => handleCopyCode(pCode)}
                                  className="px-3 py-1.5 bg-[#9A3412] text-white font-bold cursor-pointer inline-flex items-center gap-1"
                                >
                                  <Copy className="w-3.5 h-3.5" />
                                  {copiedVipCode === pCode ? 'تم النسخ!' : 'نسخ الرمز'}
                                </button>
                              </div>
                              <p className="font-mono text-[11px] text-stone-500 truncate">
                                SHA-256 Hash Stored in DB: {inv.token_hash}
                              </p>
                              <div className="flex flex-wrap gap-2 pt-1">
                                <button
                                  type="button"
                                  onClick={() => {
                                    setVipTokenInput(pCode);
                                    handleVerifyVipToken(pCode);
                                    setActiveRoute('vip');
                                  }}
                                  className="px-3 py-1.5 bg-emerald-900 text-white font-bold cursor-pointer"
                                >
                                  فتح الدعوة بالرمز الشخصي (/launch/vip)
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
                          );
                        })}
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

                {/* Subtab: Event Settings (Edits all 9 sections of /launch) */}
                {adminTab === 'settings' && (
                  <div className="bg-white border border-stone-200 p-6 space-y-4">
                    <h2 className="text-base font-extrabold text-stone-900 flex items-center gap-2">
                      <Settings className="w-5 h-5 text-emerald-900" />
                      إعدادات الحدث وتحرير محتوى الأقسام الـ 9 لصفحة الهبوط (/launch)
                    </h2>
                    <form
                      onSubmit={async (e) => {
                        e.preventDefault();
                        const formData = new FormData(e.currentTarget);

                        const schedRaw = String(formData.get('schedule_raw') || '').trim();
                        const schedule_items = schedRaw
                          ? schedRaw
                              .split('\n')
                              .map((l) => l.trim())
                              .filter(Boolean)
                              .map((l) => {
                                const parts = l.split('|').map((s) => s.trim());
                                return { time: parts[0] || '', title: parts[1] || parts[0] || '', desc: parts[2] || '' };
                              })
                          : [];

                        const spkRaw = String(formData.get('speakers_raw') || '').trim();
                        const speakers = spkRaw
                          ? spkRaw
                              .split('\n')
                              .map((l) => l.trim())
                              .filter(Boolean)
                              .map((l) => {
                                const parts = l.split('|').map((s) => s.trim());
                                return { name: parts[0] || '', role: parts[1] || '', photo_url: parts[2] || '' };
                              })
                          : [];

                        const sponRaw = String(formData.get('sponsors_raw') || '').trim();
                        const sponsors = sponRaw
                          ? sponRaw
                              .split('\n')
                              .map((l) => l.trim())
                              .filter(Boolean)
                              .map((l) => {
                                const parts = l.split('|').map((s) => s.trim());
                                return { name: parts[0] || '', tier: parts[1] || 'شريك مؤسسي' };
                              })
                          : [];

                        await fetch('/api/tarbwy/v1/settings', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({
                            title: formData.get('title'),
                            subtitle: formData.get('subtitle'),
                            date_display: formData.get('date_display'),
                            city_display: formData.get('city_display'),
                            venue_name: formData.get('venue_name'),
                            venue_address: formData.get('venue_address'),
                            map_url: formData.get('map_url'),
                            access_info: formData.get('access_info'),
                            about_event: formData.get('about_event'),
                            about_tarbwy: formData.get('about_tarbwy'),
                            why_tarbwy: formData.get('why_tarbwy'),
                            tarbwy_space: formData.get('tarbwy_space'),
                            contact_email: formData.get('contact_email'),
                            contact_phone: formData.get('contact_phone'),
                            sender_name: formData.get('sender_name'),
                            sender_email: formData.get('sender_email'),
                            reply_to_email: formData.get('reply_to_email'),
                            capacity_total: Number(formData.get('capacity_total') || 500),
                            approval_auto_confirms: formData.get('approval_auto_confirms') === 'on',
                            schedule_items,
                            speakers,
                            sponsors,
                          }),
                        });
                        fetchAllState();
                      }}
                      className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs"
                    >
                      <div>
                        <label className="block font-bold mb-1">1. اسم حفل الإشهار</label>
                        <input name="title" defaultValue={ev.title} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">هوية / عبارة الحفل الرئيسة</label>
                        <input name="subtitle" defaultValue={ev.subtitle} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">التاريخ والوقت المعروض</label>
                        <input name="date_display" defaultValue={ev.date_display} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">المدينة</label>
                        <input name="city_display" defaultValue={ev.city_display} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div className="md:col-span-2">
                        <label className="block font-bold mb-1">2. عن حفل الإشهار (تعريف مختصر بهدف اللقاء وما يمثله إطلاق تربوي)</label>
                        <textarea name="about_event" rows={2} defaultValue={ev.about_event} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div className="md:col-span-2">
                        <label className="block font-bold mb-1">3. نبذة مؤسسية مختصرة عن تربوي</label>
                        <textarea name="about_tarbwy" rows={2} defaultValue={ev.about_tarbwy} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">لماذا أُطلق تربوي؟</label>
                        <textarea name="why_tarbwy" rows={2} defaultValue={ev.why_tarbwy} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">المساحة التي يسعى لبنائها في المشهد التربوي العربي</label>
                        <textarea name="tarbwy_space" rows={2} defaultValue={ev.tarbwy_space} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">4. برنامج الحفل (سطر لكل فقرة: الوقت | العنوان | الوصف)</label>
                        <textarea
                          name="schedule_raw"
                          rows={3}
                          placeholder="06:00 مساءً | الاستقبال والتسجيل | مسح بطاقات QR الفردية"
                          defaultValue={
                            Array.isArray(ev.schedule_items)
                              ? ev.schedule_items.map((i: any) => `${i.time} | ${i.title} | ${i.desc}`).join('\n')
                              : ''
                          }
                          className="w-full px-3 py-2 border border-stone-300"
                        />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">5. المتحدثون (سطر لكل متحدث: الاسم | الصفة المهنية المختصرة)</label>
                        <textarea
                          name="speakers_raw"
                          rows={3}
                          placeholder="أ.د. عبدالله السليمان | رئيس المجلس التأسيسي"
                          defaultValue={
                            Array.isArray(ev.speakers)
                              ? ev.speakers.map((s: any) => `${s.name} | ${s.role}`).join('\n')
                              : ''
                          }
                          className="w-full px-3 py-2 border border-stone-300"
                        />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">6. اسم القاعة / المكان</label>
                        <input name="venue_name" defaultValue={ev.venue_name} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">رابط الخريطة (Map URL)</label>
                        <input name="map_url" dir="ltr" defaultValue={ev.map_url} placeholder="https://maps.google.com/..." className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">العنوان التفصيلي</label>
                        <input name="venue_address" defaultValue={ev.venue_address} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">معلومات الوصول أو الاصطفاف (Access & Parking)</label>
                        <input name="access_info" defaultValue={ev.access_info} className="w-full px-3 py-2 border border-stone-300" />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">7. الشركاء والرعاة (سطر لكل شريك: اسم الجهة | التصنيف)</label>
                        <textarea
                          name="sponsors_raw"
                          rows={2}
                          placeholder="مؤسسة القيادة التعليمية | شريك استراتيجي"
                          defaultValue={
                            Array.isArray(ev.sponsors)
                              ? ev.sponsors.map((s: any) => `${s.name} | ${s.tier}`).join('\n')
                              : ''
                          }
                          className="w-full px-3 py-2 border border-stone-300"
                        />
                      </div>
                      <div>
                        <label className="block font-bold mb-1">9. البريد والهاتف المخصص للاستفسارات</label>
                        <div className="grid grid-cols-2 gap-2">
                          <input name="contact_email" defaultValue={ev.contact_email} className="px-3 py-2 border border-stone-300" />
                          <input name="contact_phone" defaultValue={ev.contact_phone} className="px-3 py-2 border border-stone-300" />
                        </div>
                      </div>
                      {/* Professional Sender Identity Configuration */}
                      <div className="md:col-span-2 p-4 bg-[#FBF9F5] border border-emerald-900/20 space-y-3">
                        <div className="flex items-center justify-between">
                          <strong className="text-emerald-950 text-xs">
                            هوية المرسل الاحترافية للبريد الإلكتروني (Sender Identity — يمنع ظهور WordPress كاسم مرسل)
                          </strong>
                          <span className="font-mono text-[11px] text-emerald-900 bg-emerald-50 px-2 py-0.5 border border-emerald-200">
                            SMTP Authenticated: noreply@tarbwy.org
                          </span>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                          <div>
                            <label className="block font-bold mb-1">اسم المرسل (From Name)</label>
                            <input
                              name="sender_name"
                              defaultValue={ev.sender_name || 'تربوي | الاتحاد العربي للقيادة التربوية'}
                              className="w-full px-3 py-2 border border-stone-300 bg-white"
                            />
                          </div>
                          <div>
                            <label className="block font-bold mb-1">بريد الإرسال المعتمد (From Email)</label>
                            <input
                              name="sender_email"
                              dir="ltr"
                              defaultValue={ev.sender_email || 'noreply@tarbwy.org'}
                              className="w-full px-3 py-2 border border-stone-300 bg-white font-mono"
                            />
                          </div>
                          <div>
                            <label className="block font-bold mb-1">بريد الرد (Reply-To)</label>
                            <input
                              name="reply_to_email"
                              dir="ltr"
                              defaultValue={ev.reply_to_email || ev.contact_email || 'events@tarbwy.org'}
                              className="w-full px-3 py-2 border border-stone-300 bg-white font-mono"
                            />
                          </div>
                        </div>
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
                          حفظ الإعدادات وتحديث صفحة /launch وهوية البريد فوراً
                        </button>
                      </div>
                    </form>

                    {/* Danger Zone: Reset All Event Data Before Official Launch (Password: "tarbwy") */}
                    <div className="mt-6 pt-5 border-t-2 border-red-600 bg-red-50/70 p-5 space-y-3">
                      <div className="flex items-center justify-between flex-wrap gap-2">
                        <h3 className="text-sm font-extrabold text-red-900 flex items-center gap-2">
                          <AlertTriangle className="w-4 h-4 text-red-700" />
                          11. تصفير كافة بيانات التجربة استعداداً للإطلاق الرسمي (Reset All Event Data)
                        </h3>
                        <span className="text-[11px] font-bold bg-red-100 text-red-800 px-2.5 py-0.5 border border-red-300">
                          محمي بكلمة سر الإطلاق
                        </span>
                      </div>
                      <p className="text-xs text-red-950 leading-relaxed">
                        تُستخدم هذه الخاصية لحذف كافة البيانات التجريبية دفعة واحدة قبل إطلاق الحدث رسمياً، وتشمل:{' '}
                        <strong>
                          جميع طلبات التسجيل العام، قائمة الضيوف والمرافقين، دعوات كبار الشخصيات (VIP)، بطاقات الدخول ورموز QR، سجلات تسجيل الدخول (Check-in)، وسجلات البريد المرسل
                        </strong>
                        ، مع إعادة ضبط الترقيم التسلسلي للجداول ليبدأ من <code className="font-mono font-bold">#1</code>.{' '}
                        <span className="text-emerald-900 font-bold">
                          (لن يتم المساس بإعدادات الحدث أو نصوص صفحة الهبوط أو قوالب البريد أو إعدادات SMTP).
                        </span>
                      </p>
                      <form
                        onSubmit={async (e) => {
                          e.preventDefault();
                          const formEl = e.currentTarget;
                          const fd = new FormData(formEl);
                          const password = String(fd.get('reset_password') || '').trim();
                          const res = await fetch('/api/tarbwy/v1/reset-data', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ password }),
                          });
                          const data = await res.json();
                          if (!res.ok) {
                            setResetDataFeedback({
                              ok: false,
                              text: data.message || 'كلمة سر التصفير غير صحيحة.',
                            });
                            return;
                          }
                          formEl.reset();
                          setResetDataFeedback({
                            ok: true,
                            text: data.message,
                          });
                          fetchAllState();
                        }}
                        className="flex flex-wrap items-end gap-3 bg-white p-4 border border-red-200"
                      >
                        <div className="flex-1 min-w-[220px]">
                          <label className="block text-xs font-bold text-red-900 mb-1">
                            أدخل كلمة سر التصفير المعتمدة لتأكيد العملية:
                          </label>
                          <input
                            type="password"
                            name="reset_password"
                            required
                            dir="ltr"
                            placeholder="أدخل كلمة سر التصفير..."
                            className="w-full px-3 py-2 border border-red-300 text-xs font-mono"
                            autoComplete="off"
                          />
                        </div>
                        <button
                          type="submit"
                          className="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold cursor-pointer transition-colors"
                        >
                          تصفير جميع بيانات الإضافة الآن (Reset All Data)
                        </button>
                      </form>
                      {resetDataFeedback && (
                        <div
                          className={`p-3 border text-xs font-bold ${
                            resetDataFeedback.ok
                              ? 'bg-emerald-50 border-emerald-300 text-emerald-900'
                              : 'bg-red-100 border-red-300 text-red-900'
                          }`}
                        >
                          {resetDataFeedback.text}
                        </div>
                      )}
                    </div>
                  </div>
                )}

                {/* Subtab: Email Notification System, Live Responsive RTL Preview, 11-Template Studio & Logs */}
                {adminTab === 'emails' && (
                  <div className="space-y-6">
                    {emailActionFeedback && (
                      <div className="p-4 bg-emerald-50 border border-emerald-800 text-emerald-950 text-xs font-bold flex items-center justify-between">
                        <span>{emailActionFeedback}</span>
                        <button onClick={() => setEmailActionFeedback('')} className="underline cursor-pointer">
                          إغلاق
                        </button>
                      </div>
                    )}

                    {/* 1. Active Sender Identity Banner (Never "WordPress") */}
                    <div className="bg-emerald-950 text-stone-100 p-5 border border-emerald-800 flex flex-wrap items-center justify-between gap-4">
                      <div>
                        <span className="text-[11px] font-bold text-amber-300 block mb-1">
                          هوية المرسل الاحترافية المعتمدة (Active SMTP Sender Identity — يمنع ظهور WordPress كاسم مرسل)
                        </span>
                        <div className="text-sm font-extrabold font-mono" dir="ltr">
                          From: "{ev.sender_name || 'تربوي | الاتحاد العربي للقيادة التربوية'}" &lt;{ev.sender_email || 'noreply@tarbwy.org'}&gt;
                        </div>
                        <div className="text-xs text-stone-300 mt-1 font-mono" dir="ltr">
                          Reply-To: {ev.reply_to_email || ev.contact_email || 'events@tarbwy.org'} · Multipart HTML + Plain-Text Fallback
                        </div>
                      </div>
                      <button
                        type="button"
                        onClick={() => setAdminTab('settings')}
                        className="px-4 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/25 text-xs font-bold cursor-pointer"
                      >
                        تعديل هوية المرسل من إعدادات الحدث
                      </button>
                    </div>

                    {/* 2. Interactive Responsive RTL Email Previewer (Desktop 640px / Mobile 375px / Plain-Text) & Test Email Sender */}
                    {(() => {
                      const templatesMap = stateData?.emailTemplates || {};
                      const activeTpl = templatesMap[selectedPreviewTplKey] || {
                        title: 'إصدار بطاقة الدخول الفردية ورمز QR (Ticket & QR Delivery)',
                        badge: 'بطاقة دخول رسمية · QR Pass',
                        accent_color: '#113C2E',
                        subject: 'بطاقة الدخول الشخصية (QR): {event_title} — ({guest_name})',
                        heading: 'بطاقة الدخول الفردية الخاصة بكم جاهزة',
                        body: 'المكرم/ة {guest_name}،\nمرفق لكم في هذه الرسالة بطاقة الدخول الفردية والرمز المرجعي ({ticket_reference}).',
                        cta_label: 'فتح وطباعة بطاقة الدخول (QR Ticket)',
                        show_qr_box: true,
                      };
                      const sampleReplace = (txt: string) =>
                        String(txt || '')
                          .replace(/\{guest_name\}/g, 'معالي أ.د. خالد بن منصور العتيبي')
                          .replace(/\{event_title\}/g, ev.title || 'حفل إشهار تربوي — الاتحاد العربي للقيادة التربوية')
                          .replace(/\{event_date\}/g, ev.date_display || '15 نوفمبر 2026 — 06:00 مساءً')
                          .replace(/\{venue_name\}/g, ev.venue_name || 'قاعة المؤتمرات الكبرى — الرياض')
                          .replace(/\{reference_code\}/g, 'TRB-2026-8041')
                          .replace(/\{ticket_reference\}/g, 'TKT-2026-00101')
                          .replace(/\{vip_code\}/g, 'VIP-9D4A-8B21')
                          .replace(/\{ticket_url\}/g, 'https://tarbwy.org/launch/ticket?token=9f8e7d6c5b4a3f2e1d0c9b8a7f6e5d4c3b2a1f0e')
                          .replace(/\{vip_url\}/g, 'https://tarbwy.org/launch/vip?v=VIP-9D4A-8B21')
                          .replace(/\{checkin_time\}/g, '2026-11-15 18:12')
                          .replace(/\{rsvp_deadline\}/g, '2026-11-10')
                          .replace(/\{contact_email\}/g, ev.reply_to_email || 'events@tarbwy.org');

                      const previewSubject = sampleReplace(activeTpl.subject);
                      const previewHeading = sampleReplace(activeTpl.heading);
                      const previewBody = sampleReplace(activeTpl.body);

                      return (
                        <div className="bg-white border border-stone-200 p-6 space-y-5">
                          <div className="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-4">
                            <div>
                              <span className="text-xs font-bold text-[#9A3412]">
                                معاينة حية متجاوبة (Live Responsive RTL Email Preview & Test Dispatcher)
                              </span>
                              <h2 className="text-base font-extrabold text-stone-900">
                                استوديو معاينة واختبار القوالب البريدية الـ 11 (سطح المكتب · الجوال · النص المجرد)
                              </h2>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-xs">
                              <select
                                value={selectedPreviewTplKey}
                                onChange={(e) => setSelectedPreviewTplKey(e.target.value)}
                                className="px-3 py-2 border border-stone-300 bg-[#FBF9F5] font-bold text-stone-900"
                              >
                                {Object.entries(templatesMap).map(([k, t]: [string, any]) => (
                                  <option key={k} value={k}>
                                    {t.title} ({k})
                                  </option>
                                ))}
                              </select>
                              <button
                                type="button"
                                onClick={() => setEmailPreviewViewport('desktop')}
                                className={`px-3 py-2 font-bold border cursor-pointer ${
                                  emailPreviewViewport === 'desktop'
                                    ? 'bg-emerald-900 text-white border-emerald-900'
                                    : 'bg-white text-stone-800 border-stone-300'
                                }`}
                              >
                                سطح المكتب (640px)
                              </button>
                              <button
                                type="button"
                                onClick={() => setEmailPreviewViewport('mobile')}
                                className={`px-3 py-2 font-bold border cursor-pointer ${
                                  emailPreviewViewport === 'mobile'
                                    ? 'bg-emerald-900 text-white border-emerald-900'
                                    : 'bg-white text-stone-800 border-stone-300'
                                }`}
                              >
                                الجوال (375px)
                              </button>
                              <button
                                type="button"
                                onClick={() => setEmailPreviewViewport('plain')}
                                className={`px-3 py-2 font-bold border cursor-pointer ${
                                  emailPreviewViewport === 'plain'
                                    ? 'bg-stone-900 text-white border-stone-900'
                                    : 'bg-white text-stone-800 border-stone-300'
                                }`}
                              >
                                النص المجرد (Plain-Text Fallback)
                              </button>
                            </div>
                          </div>

                          {/* Send Test Email Form */}
                          <form onSubmit={handleSendTestEmail} className="p-3.5 bg-[#FBF9F5] border border-stone-200 flex flex-wrap items-center justify-between gap-3 text-xs">
                            <div className="flex items-center gap-2">
                              <Mail className="w-4 h-4 text-emerald-900" />
                              <span className="font-bold text-stone-800">إرسال رسالة تجريبية للقالب المحدد ({selectedPreviewTplKey}):</span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 flex-1 max-w-md">
                              <input
                                type="email"
                                required
                                dir="ltr"
                                value={testEmailRecipient}
                                onChange={(e) => setTestEmailRecipient(e.target.value)}
                                placeholder="admin@tarbwy.org"
                                className="flex-1 px-3 py-2 border border-stone-300 bg-white font-mono"
                              />
                              <button
                                type="submit"
                                className="px-4 py-2 bg-emerald-900 text-white font-bold hover:bg-emerald-800 cursor-pointer whitespace-nowrap"
                              >
                                إرسال بريد تجريبي الآن
                              </button>
                            </div>
                          </form>

                          {/* Preview Stage */}
                          <div className="bg-stone-100 border border-stone-300 p-4 md:p-6 overflow-x-auto">
                            <div className="max-w-[640px] mx-auto mb-3 bg-white border border-stone-300 p-3 text-xs space-y-1">
                              <div className="flex flex-wrap justify-between gap-2">
                                <span className="text-stone-500">المرسل (From):</span>
                                <strong className="font-mono text-stone-900" dir="ltr">
                                  "{ev.sender_name || 'تربوي | الاتحاد العربي للقيادة التربوية'}" &lt;{ev.sender_email || 'noreply@tarbwy.org'}&gt;
                                </strong>
                              </div>
                              <div className="flex flex-wrap justify-between gap-2">
                                <span className="text-stone-500">الموضوع (Subject):</span>
                                <strong className="text-stone-900">{previewSubject}</strong>
                              </div>
                            </div>

                            {emailPreviewViewport === 'plain' ? (
                              <pre
                                dir="rtl"
                                className="max-w-[640px] mx-auto p-6 bg-stone-950 text-stone-100 text-xs leading-relaxed whitespace-pre-wrap border border-stone-800 font-mono"
                              >
{`============================================================
${ev.sender_name || 'تربوي | الاتحاد العربي للقيادة التربوية'}
${ev.title}
============================================================

[${activeTpl.badge}]
${previewHeading}

${previewBody}
${activeTpl.show_vip_code_box || selectedPreviewTplKey.startsWith('vip_invitation') ? `\n------------------------------------------------------------\nرمز الدعوة الشخصي (Personal Invitation Code): VIP-9D4A-8B21\nبوابة تأكيد حضور كبار الشخصيات: https://tarbwy.org/launch/vip\n------------------------------------------------------------` : ''}
------------------------------------------------------------
بيانات الفعالية:
- الحدث: ${ev.title}
- الموعد: ${ev.date_display}
- المكان: ${ev.venue_name} — ${ev.venue_address}
- الرقم المرجعي للتذكرة: TKT-2026-00101
------------------------------------------------------------
${activeTpl.cta_label ? `${activeTpl.cta_label}:\nhttps://tarbwy.org/launch/ticket?token=9f8e7d6c5b4a3f2e1d0c\n------------------------------------------------------------` : ''}
للاستفسارات والتواصل: ${ev.reply_to_email || 'events@tarbwy.org'}
الموقع الرسمي: https://tarbwy.org`}
                              </pre>
                            ) : (
                              <div
                                dir="rtl"
                                className={`mx-auto transition-all duration-200 bg-[#F4F1EA] p-3 md:p-5 border border-stone-300 ${
                                  emailPreviewViewport === 'mobile' ? 'max-w-[375px]' : 'max-w-[640px]'
                                }`}
                              >
                                <div className="bg-white border border-stone-200 overflow-hidden shadow-sm">
                                  <div style={{ height: '6px', background: 'linear-gradient(90deg, #113C2E 0%, #B45309 50%, #113C2E 100%)' }} />
                                  <div className="bg-[#113C2E] text-white p-6 border-b-[3px] border-[#B45309] flex items-center justify-between gap-3">
                                    <div>
                                      <div className="text-base font-extrabold text-[#FBF9F5]">تربوي — الاتحاد العربي للقيادة التربوية</div>
                                      <div className="text-[11px] text-[#D6D3C9] mt-0.5">Tarbwy · Arab Union for Educational Leadership</div>
                                    </div>
                                    <div className="w-11 h-11 border border-[#B45309] bg-[#164A39] flex items-center justify-center font-extrabold text-sm text-[#FDE68A] shrink-0">
                                      تربوي
                                    </div>
                                  </div>
                                  <div className="p-6 space-y-4 text-right">
                                    <span
                                      className="inline-block px-3 py-1 text-[11px] font-extrabold text-white"
                                      style={{ backgroundColor: activeTpl.accent_color || '#113C2E' }}
                                    >
                                      {activeTpl.badge}
                                    </span>
                                    <h3 className="text-lg font-extrabold text-stone-900 leading-snug">{previewHeading}</h3>
                                    <div className="space-y-2.5 text-xs text-stone-700 leading-relaxed whitespace-pre-line">
                                      {previewBody}
                                    </div>

                                    {(activeTpl.show_vip_code_box || selectedPreviewTplKey.startsWith('vip_invitation')) && (
                                      <div className="p-5 bg-[#FFFBEB] border-2 border-[#B45309] rounded-xl text-center space-y-2.5">
                                        <div className="text-[11px] font-extrabold text-[#9A3412]">
                                          رمز الدعوة الشخصي · PERSONAL INVITATION CODE
                                        </div>
                                        <div className="inline-block px-5 py-2.5 bg-white border-2 border-dashed border-[#B45309] rounded-lg font-mono text-xl font-extrabold tracking-widest text-stone-900" dir="ltr">
                                          VIP-9D4A-8B21
                                        </div>
                                        <p className="text-[11px] text-stone-700">
                                          أدخل هذا الرمز مباشرة في صفحة <code className="font-mono font-bold">tarbwy.org/launch/vip</code> دون الحاجة لاستخراج أي رابط.
                                        </p>
                                        <div>
                                          <button
                                            type="button"
                                            onClick={() => handleCopyCode('VIP-9D4A-8B21')}
                                            className="px-4 py-2 bg-[#9A3412] text-white text-xs font-extrabold rounded-md cursor-pointer inline-flex items-center gap-1.5"
                                          >
                                            <Copy className="w-3.5 h-3.5" />
                                            {copiedVipCode === 'VIP-9D4A-8B21' ? '✓ تم نسخ الرمز!' : 'نسخ رمز الدعوة الشخصي (Copy Code)'}
                                          </button>
                                        </div>
                                      </div>
                                    )}

                                    {activeTpl.show_qr_box && (
                                      <div className="p-4 bg-[#FBF9F5] border-2 border-[#113C2E] text-center space-y-2">
                                        <div className="text-[11px] font-extrabold text-[#9A3412]">
                                          بطاقة الدخول الفردية المعتمدة · INDIVIDUAL QR PASS
                                        </div>
                                        <div className="text-sm font-extrabold text-stone-900">معالي أ.د. خالد بن منصور العتيبي</div>
                                        <div className="flex justify-center py-2">
                                          <LocalQRCode token="9f8e7d6c5b4a3f2e1d0c9b8a7f6e5d4c3b2a1f0e" size={128} />
                                        </div>
                                        <div className="inline-block px-3 py-1 bg-white border border-stone-300 font-mono text-xs font-extrabold text-[#113C2E]">
                                          TKT-2026-00101
                                        </div>
                                        <p className="text-[11px] text-stone-500">
                                          يرجى إبراز رمز الاستجابة السريعة (QR) عند بوابة الاستقبال (صالح لشخص واحد فقط).
                                        </p>
                                      </div>
                                    )}

                                    {activeTpl.cta_label && (
                                      <div className="pt-2">
                                        <span
                                          className="inline-block w-full sm:w-auto text-center px-6 py-3 text-white text-xs font-extrabold"
                                          style={{ backgroundColor: activeTpl.accent_color || '#113C2E' }}
                                        >
                                          {activeTpl.cta_label} ←
                                        </span>
                                      </div>
                                    )}

                                    <div className="p-4 bg-[#FBF9F5] border border-stone-200 text-xs space-y-1.5">
                                      <div className="font-extrabold text-[#113C2E] mb-1">بيانات حفل الإشهار والمكان</div>
                                      <div>
                                        <span className="text-stone-500">الفعالية: </span>
                                        <strong className="text-stone-900">{ev.title}</strong>
                                      </div>
                                      <div>
                                        <span className="text-stone-500">التاريخ والوقت: </span>
                                        <strong className="text-stone-900">{ev.date_display}</strong>
                                      </div>
                                      <div>
                                        <span className="text-stone-500">المكان: </span>
                                        <strong className="text-stone-900">{ev.venue_name} — {ev.venue_address}</strong>
                                      </div>
                                    </div>
                                  </div>
                                  <div className="bg-stone-900 text-stone-300 p-5 text-[11px] space-y-1">
                                    <div className="font-bold text-stone-100">{ev.sender_name || 'تربوي | الاتحاد العربي للقيادة التربوية'}</div>
                                    <div>للاستفسارات والتواصل: {ev.reply_to_email || 'events@tarbwy.org'} · https://tarbwy.org</div>
                                  </div>
                                </div>
                              </div>
                            )}
                          </div>
                        </div>
                      );
                    })()}

                    {/* 3. Per-Person & Bulk Email Dispatchers */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                      {/* Send Email Per Person */}
                      <div className="bg-white border border-stone-200 p-6 space-y-4">
                        <span className="text-xs font-bold text-[#9A3412]">الإرسال الفردي المباشر (Per-Person Email)</span>
                        <h2 className="text-base font-extrabold text-stone-900">
                          1. إرسال بريد فردي لشخص محدد (ضيف أو مرافق)
                        </h2>
                        <p className="text-xs text-stone-600 leading-relaxed">
                          اختر أي ضيف أو مرافق وأرسل له أي قالب من القوالب الـ 11 المعتمدة بشكل فوري. إذا لم يكن للمرافق بريد إلكتروني خاص، يرسل النظام البطاقة تلقائياً إلى بريد الضيف الأساسي مع توضيح اسم المرافق.
                        </p>

                        <div className="space-y-3 text-xs">
                          <div>
                            <label className="block font-bold text-stone-800 mb-1">اختر الشخص (الضيف أو المرافق):</label>
                            <select
                              value={singleEmailGuestId}
                              onChange={(e) => setSingleEmailGuestId(Number(e.target.value))}
                              className="w-full px-3 py-2 border border-stone-300 bg-white"
                            >
                              {stateData?.guests?.map((g: any) => (
                                <option key={g.id} value={g.id}>
                                  #{g.id} — {g.full_name} ({g.guest_type} · {g.email || `يُرسل لبريد الأساسي: ${g.parent_name || ''}`})
                                </option>
                              ))}
                            </select>
                          </div>

                          <div>
                            <label className="block font-bold text-stone-800 mb-1">اختر قالب الرسالة المراد إرسالها:</label>
                            <select
                              value={singleEmailTemplate}
                              onChange={(e) => setSingleEmailTemplate(e.target.value)}
                              className="w-full px-3 py-2 border border-stone-300 bg-white"
                            >
                              <option value="ticket_issued">إرسال / إعادة إرسال بطاقة الدخول الفردية (QR Ticket)</option>
                              <option value="event_reminder">تذكير بموعد ومكان الحفل (Event Reminder)</option>
                              <option value="vip_invitation">إرسال رابط الدعوة الخاصة (VIP Invitation)</option>
                              <option value="vip_rsvp_confirmed">تأكيد حضور كبار الشخصيات (VIP RSVP Confirmation)</option>
                              <option value="registration_approved">إشعار قبول الطلب وتأكيد الحضور</option>
                              <option value="registration_waitlist">إشعار الإدراج في قائمة الانتظار (Waitlist)</option>
                              <option value="registration_declined">إشعار الاعتذار عن عدم قبول الطلب (Rejected)</option>
                              <option value="checkin_confirmation">تأكيد تسجيل الدخول في القاعة (Check-in Confirmation)</option>
                              <option value="post_event_thank_you">رسالة شكر بعد الحدث (Post-Event Thank You)</option>
                            </select>
                          </div>

                          <button
                            type="button"
                            onClick={() => handleSendSingleEmail(singleEmailGuestId, singleEmailTemplate)}
                            className="px-5 py-2.5 bg-emerald-900 text-white font-bold hover:bg-emerald-800 cursor-pointer"
                          >
                            إرسال البريد للشخص المحدد الآن
                          </button>
                        </div>
                      </div>

                      {/* Send Bulk Email Campaign */}
                      <div className="bg-white border border-stone-200 p-6 space-y-4">
                        <span className="text-xs font-bold text-[#9A3412]">الإرسال الجماعي للفئات (Bulk Email Campaign)</span>
                        <h2 className="text-base font-extrabold text-stone-900">
                          2. إرسال بريد جماعي لفئة كاملة (تذكير / بطاقات / شكر)
                        </h2>
                        <p className="text-xs text-stone-600 leading-relaxed">
                          أرسل رسالة موحدة مخصصة بالاسم والبيانات (`{'{guest_name}'}`, `{'{ticket_url}'}`) إلى فئة كاملة من الضيوف دفعة واحدة عبر طابور البريد (`tarbwy_email_logs`).
                        </p>

                        <form onSubmit={handleSendBulkEmail} className="space-y-3 text-xs">
                          <div>
                            <label className="block font-bold text-stone-800 mb-1">الفئة المستهدفة بالإرسال الجماعي:</label>
                            <select
                              value={bulkEmailSegment}
                              onChange={(e) => setBulkEmailSegment(e.target.value)}
                              className="w-full px-3 py-2 border border-stone-300 bg-white"
                            >
                              <option value="confirmed">جميع المؤكدين (Confirmed Attendees — للتذكير وإرسال البطاقات)</option>
                              <option value="checked_in">جميع الحاضرين فعلياً (Checked-in — لرسالة الشكر بعد الحفل)</option>
                              <option value="vip_pending">مدعوو VIP الذين لم يردوا بعد (Pending VIPs)</option>
                              <option value="all_vip">جميع كبار الشخصيات (All VIP Guests)</option>
                              <option value="waitlist">المسجلون في قائمة الانتظار (Waitlist)</option>
                              <option value="all">جميع المسجلين في الحدث (All Guests)</option>
                            </select>
                          </div>

                          <div>
                            <label className="block font-bold text-stone-800 mb-1">قالب الرسالة الجماعية:</label>
                            <select
                              value={bulkEmailTemplate}
                              onChange={(e) => setBulkEmailTemplate(e.target.value)}
                              className="w-full px-3 py-2 border border-stone-300 bg-white"
                            >
                              <option value="event_reminder">تذكير قبل الحدث (للمؤكدين)</option>
                              <option value="post_event_thank_you">شكر بعد الحدث (للحاضرين أو المؤكدين)</option>
                              <option value="ticket_issued">إرسال بطاقات الدخول الجماعي (QR Tickets)</option>
                              <option value="vip_invitation">إرسال دعوات VIP الجماعية</option>
                            </select>
                          </div>

                          <button
                            type="submit"
                            className="px-5 py-2.5 bg-stone-900 text-white font-bold hover:bg-stone-800 cursor-pointer"
                          >
                            جدولة وإرسال البريد الجماعي للفئة المحددة
                          </button>
                        </form>
                      </div>
                    </div>

                    {/* 4. Customize All 11 Email Templates + Restore Default Templates */}
                    <div className="bg-white border border-stone-200 p-6 space-y-4">
                      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-4">
                        <div>
                          <span className="text-xs font-bold text-[#9A3412]">تخصيص القوالب الـ 11 (Template Management Studio)</span>
                          <h2 className="text-base font-extrabold text-stone-900">
                            تحرير عناوين ومحتوى وأزرار قوالب البريد الإلكتروني الـ 11 مع دعم المتغيرات الديناميكية
                          </h2>
                          <p className="text-xs text-stone-500 mt-1 font-mono">
                            المتغيرات المدعومة: {'{guest_name}'} · {'{event_title}'} · {'{event_date}'} · {'{venue_name}'} · {'{reference_code}'} · {'{ticket_reference}'} · {'{ticket_url}'} · {'{vip_url}'} · {'{qr_code}'} · {'{checkin_time}'}
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={handleRestoreEmailTemplates}
                          className="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-900 border border-stone-300 text-xs font-bold cursor-pointer"
                        >
                          استعادة كافة القوالب الافتراضية الـ 11
                        </button>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {Object.entries(stateData?.emailTemplates || {}).map(([tplKey, tpl]: [string, any]) => (
                          <form
                            key={tplKey}
                            onSubmit={(e) => {
                              e.preventDefault();
                              const fd = new FormData(e.currentTarget);
                              handleSaveEmailTemplate(tplKey, {
                                subject: String(fd.get('subject') || ''),
                                heading: String(fd.get('heading') || ''),
                                badge: String(fd.get('badge') || ''),
                                body: String(fd.get('body') || ''),
                                cta_label: String(fd.get('cta_label') || ''),
                              });
                            }}
                            className="p-4 bg-[#FBF9F5] border border-stone-200 space-y-2.5 text-xs"
                          >
                            <div className="flex items-center justify-between gap-2">
                              <strong className="text-stone-900">{tpl.title}</strong>
                              <code className="text-[11px] font-mono text-emerald-900 bg-emerald-50 px-2 py-0.5 border border-emerald-200">
                                {tplKey}
                              </code>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                              <div>
                                <label className="block font-bold text-stone-700 mb-0.5">شارة القالب (Badge)</label>
                                <input name="badge" defaultValue={tpl.badge} className="w-full px-2.5 py-1.5 border border-stone-300 bg-white" />
                              </div>
                              <div>
                                <label className="block font-bold text-stone-700 mb-0.5">نص زر الإجراء (CTA Label)</label>
                                <input name="cta_label" defaultValue={tpl.cta_label} className="w-full px-2.5 py-1.5 border border-stone-300 bg-white" />
                              </div>
                            </div>
                            <div>
                              <label className="block font-bold text-stone-700 mb-0.5">موضوع الرسالة (Subject)</label>
                              <input name="subject" defaultValue={tpl.subject} className="w-full px-2.5 py-1.5 border border-stone-300 bg-white font-semibold" />
                            </div>
                            <div>
                              <label className="block font-bold text-stone-700 mb-0.5">العنوان الترحيبي داخل البطاقة (Heading)</label>
                              <input name="heading" defaultValue={tpl.heading} className="w-full px-2.5 py-1.5 border border-stone-300 bg-white" />
                            </div>
                            <div>
                              <label className="block font-bold text-stone-700 mb-0.5">محتوى الرسالة (Body)</label>
                              <textarea name="body" rows={3} defaultValue={tpl.body} className="w-full px-2.5 py-1.5 border border-stone-300 bg-white leading-relaxed" />
                            </div>
                            <div className="flex items-center justify-between pt-1">
                              <button
                                type="button"
                                onClick={() => setSelectedPreviewTplKey(tplKey)}
                                className="text-emerald-900 font-bold underline cursor-pointer"
                              >
                                معاينة هذا القالب في الأعلى
                              </button>
                              <button type="submit" className="px-3.5 py-1.5 bg-emerald-900 text-white font-bold cursor-pointer">
                                حفظ تعديل القالب
                              </button>
                            </div>
                          </form>
                        ))}
                      </div>
                    </div>

                    {/* 5. Queue Logs & Redacted Audit Log */}
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
                  </div>
                )}

                {/* Subtab: Staff Roles & Permissions Management (الموظفون والصلاحيات) */}
                {adminTab === 'staff' && (
                  <div className="space-y-6">
                    {staffActionFeedback && (
                      <div className="p-4 bg-emerald-50 border border-emerald-800 text-emerald-950 text-xs font-bold flex items-center justify-between">
                        <span>{staffActionFeedback}</span>
                        <button onClick={() => setStaffActionFeedback('')} className="underline cursor-pointer">
                          إغلاق
                        </button>
                      </div>
                    )}

                    {/* Roles & Capabilities Reference Matrix (Spec Section 10) */}
                    <div className="bg-white border border-stone-200 p-6 space-y-4">
                      <span className="text-xs font-bold text-[#9A3412]">
                        WordPress المسار: فعاليات تربوي ← الموظفون والصلاحيات (tarbwy-em-staff) · أو عبر: أعضاء (Users) ← تحرير العضو
                      </span>
                      <h2 className="text-base font-extrabold text-stone-900">
                        مصفوفة الأدوار والصلاحيات الـ 6 المعتمدة (WordPress Roles & Capabilities — القسم 10)
                      </h2>
                      <div className="overflow-x-auto">
                        <table className="w-full text-xs border-collapse">
                          <thead>
                            <tr className="bg-stone-100 border-b border-stone-200 text-stone-700">
                              <th className="p-3 text-right">الدور الوظيفي في WordPress</th>
                              <th className="p-3 text-right">الاسم البرمجي (Role Slug)</th>
                              <th className="p-3 text-right">الصلاحيات الممنوحة (Capabilities)</th>
                              <th className="p-3 text-right">القيود الأمنية</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr className="border-b border-stone-200">
                              <td className="p-3 font-bold text-stone-900">مدير فعالية تربوي (Event Administrator)</td>
                              <td className="p-3 font-mono">tarbwy_event_admin</td>
                              <td className="p-3 font-mono text-[11px]">
                                manage_tarbwy_events, review_tarbwy_registrations, manage_tarbwy_guests, manage_tarbwy_invitations, checkin_tarbwy_guests, export_tarbwy_reports
                              </td>
                              <td className="p-3 text-emerald-900 font-semibold">كامل صلاحيات الفعالية والتقارير والإعدادات وإرسال البريد</td>
                            </tr>
                            <tr className="border-b border-stone-200">
                              <td className="p-3 font-bold text-stone-900">مسؤول تسجيل تربوي (Registration Manager)</td>
                              <td className="p-3 font-mono">tarbwy_registration_manager</td>
                              <td className="p-3 font-mono text-[11px]">
                                review_tarbwy_registrations, manage_tarbwy_guests, manage_tarbwy_invitations, checkin_tarbwy_guests
                              </td>
                              <td className="p-3 text-amber-900">لا يمكنه تعديل إعدادات الحدث أو تصدير تقارير CSV الشاملة</td>
                            </tr>
                            <tr className="border-b border-stone-200">
                              <td className="p-3 font-bold text-stone-900">موظف استقبال تربوي (Check-in Staff)</td>
                              <td className="p-3 font-mono">tarbwy_checkin_staff</td>
                              <td className="p-3 font-mono text-[11px]">checkin_tarbwy_guests فقط</td>
                              <td className="p-3 text-red-900 font-semibold">
                                يدخل فقط لبوابة (/launch/checkin)؛ محظور من إدارة الطلبات أو التصدير أو الإعدادات
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    {/* Assign / Add Staff Member */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                      <div className="lg:col-span-2 bg-white border border-stone-200 p-6 space-y-4">
                        <h3 className="text-base font-extrabold text-stone-900">قائمة موظفي الفعالية وتعديل أدوارهم</h3>
                        <div className="overflow-x-auto">
                          <table className="w-full text-xs border-collapse">
                            <thead>
                              <tr className="bg-stone-100 border-b border-stone-200 text-stone-700">
                                <th className="p-3 text-right">اسم الموظف</th>
                                <th className="p-3 text-right">البريد الإلكتروني</th>
                                <th className="p-3 text-right">دور WordPress الأساسي</th>
                                <th className="p-3 text-right">الدور الحالي في الفعالية</th>
                                <th className="p-3 text-right">تغيير الدور فوراً</th>
                              </tr>
                            </thead>
                            <tbody>
                              {stateData?.staffUsers?.map((u: any) => (
                                <tr key={u.id} className="border-b border-stone-200">
                                  <td className="p-3 font-bold text-stone-900">{u.display_name}</td>
                                  <td className="p-3 font-mono">{u.email}</td>
                                  <td className="p-3">
                                    <span className="font-mono text-[11px] bg-stone-100 px-2 py-0.5 border border-stone-200">
                                      {u.wp_role || 'subscriber'}
                                    </span>
                                    {u.wp_role === 'administrator' && (
                                      <span className="block text-[10px] text-emerald-800 font-bold mt-0.5">
                                        محمي (يحتفظ بصلاحية Administrator)
                                      </span>
                                    )}
                                  </td>
                                  <td className="p-3 font-semibold text-emerald-900">{u.role_label}</td>
                                  <td className="p-3">
                                    <select
                                      value={u.role}
                                      onChange={(e) => handleAssignStaffRole(u.id, e.target.value)}
                                      className="px-2.5 py-1.5 border border-stone-300 bg-white font-semibold"
                                    >
                                      <option value="tarbwy_event_admin">مدير الفعالية (Event Administrator)</option>
                                      <option value="tarbwy_registration_manager">مسؤول التسجيل (Registration Manager)</option>
                                      <option value="tarbwy_checkin_staff">موظف استقبال (Check-in Staff)</option>
                                      <option value="none">بدون دور في الفعالية (إلغاء صلاحية الموظف)</option>
                                    </select>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <div className="bg-white border border-stone-200 p-6 space-y-4">
                        <h3 className="text-base font-extrabold text-stone-900">إضافة موظف جديد وتعيين صلاحيته</h3>
                        <form onSubmit={handleAddNewStaff} className="space-y-3 text-xs">
                          <div>
                            <label className="block font-bold mb-1">اسم الموظف *</label>
                            <input
                              type="text"
                              required
                              value={newStaffName}
                              onChange={(e) => setNewStaffName(e.target.value)}
                              placeholder="مثال: أ. فهد القحطاني"
                              className="w-full px-3 py-2 border border-stone-300"
                            />
                          </div>
                          <div>
                            <label className="block font-bold mb-1">البريد الإلكتروني *</label>
                            <input
                              type="email"
                              required
                              value={newStaffEmail}
                              onChange={(e) => setNewStaffEmail(e.target.value)}
                              placeholder="staff@tarbwy.org"
                              className="w-full px-3 py-2 border border-stone-300"
                            />
                          </div>
                          <div>
                            <label className="block font-bold mb-1">الدور والصلاحية *</label>
                            <select
                              value={newStaffRole}
                              onChange={(e) => setNewStaffRole(e.target.value)}
                              className="w-full px-3 py-2 border border-stone-300 bg-white"
                            >
                              <option value="tarbwy_checkin_staff">موظف استقبال تربوي (Check-in Staff)</option>
                              <option value="tarbwy_registration_manager">مسؤول تسجيل تربوي (Registration Manager)</option>
                              <option value="tarbwy_event_admin">مدير فعالية تربوي (Event Administrator)</option>
                            </select>
                          </div>
                          <button
                            type="submit"
                            className="w-full px-4 py-2.5 bg-emerald-900 text-white font-bold hover:bg-emerald-800 cursor-pointer"
                          >
                            إضافة الموظف وتفعيل الصلاحيات
                          </button>
                        </form>
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
