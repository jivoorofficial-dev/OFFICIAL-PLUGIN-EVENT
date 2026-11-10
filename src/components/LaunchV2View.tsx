import React, { useState, useEffect, useRef } from 'react';
import {
  MapPin,
  ArrowLeft,
  ArrowDown,
  Clock,
  Star,
  CheckCircle2,
  Compass,
  Globe,
  Layers,
  Users,
  ExternalLink,
  Plus,
  Trash2,
  Menu,
  X,
} from 'lucide-react';

interface CompanionInput {
  full_name: string;
  email: string;
  job_title: string;
}

interface LaunchV2ViewProps {
  event: any;
  regName: string;
  setRegName: (v: string) => void;
  regEmail: string;
  setRegEmail: (v: string) => void;
  regPhone: string;
  setRegPhone: (v: string) => void;
  regOrg: string;
  setRegOrg: (v: string) => void;
  regTitle: string;
  setRegTitle: (v: string) => void;
  regDietary: string;
  setRegDietary: (v: string) => void;
  regHoneypot: string;
  setRegHoneypot: (v: string) => void;
  regCompanions: CompanionInput[];
  setRegCompanions: React.Dispatch<React.SetStateAction<CompanionInput[]>>;
  regFeedback: { ok: boolean; msg: string; ref?: string } | null;
  regSubmitting: boolean;
  onSubmitRegistration: (e: React.FormEvent) => void;
  onSwitchToV1?: () => void;
  onOpenAdminSettings?: () => void;
}

const DEFAULT_TRACKS = [
  {
    code: 'track-1',
    badge: 'المسار الأول · قابل للتحرير',
    title: 'المحور الرئيس الأول (يُحدث فور اعتماد المحاور الرسمية)',
    desc: 'مساحة مخصصة لاستعراض المحور الفكري أو القيادي الأول لحفل الإشهار. يمكن تحرير عنوان هذا المسار ووصفه بالكامل من لوحة تحكم ووردبريس (إعدادات الحدث).',
  },
  {
    code: 'track-2',
    badge: 'المسار الثاني · قابل للتحرير',
    title: 'المحور الرئيس الثاني (يُحدث فور اعتماد المحاور الرسمية)',
    desc: 'مساحة مخصصة لاستعراض المحور الثاني ومجالات النقاش المرتبطة به بين القيادات التربوية وصنّاع القرار المشاركين في حفل الإشهار.',
  },
  {
    code: 'track-3',
    badge: 'المسار الثالث · قابل للتحرير',
    title: 'المحور الرئيس الثالث (يُحدث فور اعتماد المحاور الرسمية)',
    desc: 'مساحة مخصصة لتسليط الضوء على المسار التطبيقي أو المؤسسي الثالث ومبادرات العمل التربوي المشترك المزمع إطلاقها.',
  },
  {
    code: 'track-4',
    badge: 'المسار الرابع · قابل للتحرير',
    title: 'المحور الرئيس الرابع (يُحدث فور اعتماد المحاور الرسمية)',
    desc: 'مساحة مخصصة لاستعراض آفاق الشراكات التربوية وتكامل الخبرات القيادية العربية ضمن إطار الاتحاد.',
  },
];

const DEFAULT_SPEAKERS = [
  {
    name: 'ضيف متحدث 01 (يُعلن لاحقاً)',
    role: 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
    bio: 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
    photo_url: '',
    link_url: '',
  },
  {
    name: 'ضيف متحدث 02 (يُعلن لاحقاً)',
    role: 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
    bio: 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
    photo_url: '',
    link_url: '',
  },
  {
    name: 'ضيف متحدث 03 (يُعلن لاحقاً)',
    role: 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
    bio: 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
    photo_url: '',
    link_url: '',
  },
  {
    name: 'ضيف متحدث 04 (يُعلن لاحقاً)',
    role: 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
    bio: 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
    photo_url: '',
    link_url: '',
  },
  {
    name: 'ضيف متحدث 05 (يُعلن لاحقاً)',
    role: 'المسمى الوظيفي أو الصفة المؤسسية — قابل للتحرير',
    bio: 'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.',
    photo_url: '',
    link_url: '',
  },
];

const DEFAULT_PROGRAM = [
  {
    stage: 'فاتحة الحفل · الاستقبال ومراسم الافتتاح',
    time: 'الفقرة 01 · يُحدد التوقيت لاحقاً',
    title: 'الاستقبال والتسجيل الرقمي ومراسم الافتتاح الرسمي (Welcome & Opening)',
    desc: 'استقبال الضيوف والمدعوين عبر مسح بطاقات الدخول الفردية (QR Code)، تليها كلمة الترحيب والافتتاح الرسمي لحفل إشهار تربوي.',
  },
  {
    stage: 'المحور الأول · العروض الرئيسة',
    time: 'الفقرة 02 · يُحدد التوقيت لاحقاً',
    title: 'العروض الرئيسة وإشهار الرؤية التأسيسية للاتحاد (Main Presentations)',
    desc: 'استعراض الهوية المؤسسية لـ «تربوي — الاتحاد العربي للقيادة التربوية»، ومنطلقات تأسيسه، والمسارات الاستراتيجية الأولى.',
  },
  {
    stage: 'المحور الثاني · الجلسات الحوارية',
    time: 'الفقرة 03 · يُحدد التوقيت لاحقاً',
    title: 'جلسات النقاش ومحاور العمل التربوي المشترك (Discussion Sessions)',
    desc: 'حوار معمّق حول أولويات القيادة التربوية في العالم العربي وآليات بناء المبادرات التشاركية العابرة للمؤسسات.',
  },
  {
    stage: 'المحور الثالث · المتحدثون الرئيسيون',
    time: 'الفقرة 04 · يُحدد التوقيت لاحقاً',
    title: 'كلمات المتحدثين الرئيسيين والشخصيات المرجعية (Keynote Speakers)',
    desc: 'مداخلات رئيسة لنخبة من القادة والخبراء وصنّاع القرار التربويين حول مستقبل القيادة التعليمية.',
  },
  {
    stage: 'مسك الختام · البيان الختامي والتواصل',
    time: 'الفقرة 05 · يُحدد التوقيت لاحقاً',
    title: 'الجلسة الختامية وإعلان المخرجات والتواصل المهني (Closing Session)',
    desc: 'تلاوة البيان الختامي لإشهار الاتحاد، وتوثيق الشراكات المؤسسية، ولقاء التواصل المهني بين الضيوف.',
  },
];

export const LaunchV2View: React.FC<LaunchV2ViewProps> = ({
  event,
  regName,
  setRegName,
  regEmail,
  setRegEmail,
  regPhone,
  setRegPhone,
  regOrg,
  setRegOrg,
  regTitle,
  setRegTitle,
  regDietary,
  setRegDietary,
  regHoneypot,
  setRegHoneypot,
  regCompanions,
  setRegCompanions,
  regFeedback,
  regSubmitting,
  onSubmitRegistration,
  onSwitchToV1,
  onOpenAdminSettings,
}) => {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [activeSpeakerIdx, setActiveSpeakerIdx] = useState(0);
  const [cardTransforms, setCardTransforms] = useState<
    Array<{ scale: number; rotate: number; brightness: number }>
  >([]);
  const [timelineProgress, setTimelineProgress] = useState(35);
  const [countdown, setCountdown] = useState({
    days: '14',
    hours: '06',
    minutes: '42',
    seconds: '18',
    tickPulse: false,
  });

  const speakerRefs = useRef<Array<HTMLDivElement | null>>([]);
  const timelineRef = useRef<HTMLDivElement | null>(null);
  const particlesCanvasRef = useRef<HTMLCanvasElement | null>(null);

  const settings = event?.settings || {};
  const heroMainTitle = 'حفل إشهار تربوي';
  const heroSubIdentity =
    event?.title && String(event.title).includes('الاتحاد')
      ? event.title
      : 'تربوي — الاتحاد العربي للقيادة التربوية';
  const subtitle =
    settings.subtitle ||
    'ملتقى تأسيسي يجمع القيادات التربوية وصنّاع القرار والمرجعيات الفكرية لإطلاق مظلة مؤسسية عربية مشتركة تصنع مستقبل القيادة التعليمية.';
  const dateDisplay = settings.date_display || 'يُحدد الموعد النهائي قريباً من اللجنة المنظمة';
  const cityDisplay = settings.city_display || 'يُعلن قريباً فور الاعتماد النهائي';
  const venueName = event?.venue_name || 'القاعة الرئيسة — يُعلن عنها قريباً';
  const venueAddress =
    event?.venue_address ||
    'سيتم نشر العنوان التفصيلي وإحداثيات الموقع فور تثبيت القاعة المعتمدة من اللجنة المنظمة.';
  const mapUrl = event?.map_url || '';
  const accessInfo =
    settings.access_info ||
    'سيتم تزويد الضيوف المؤكدين بإرشادات الوصول المباشر وبوابات الاستقبال ومواقف السيارات المخصصة ضمن رسالة بطاقة الدخول.';
  const aboutEvent =
    settings.about_event ||
    'يمثل حفل إشهار «تربوي — الاتحاد العربي للقيادة التربوية» محطة تأسيسية جامعة لنخبة القيادات التربوية وصنّاع القرار والشخصيات المرجعية والخبراء؛ بهدف الإعلان الرسمي عن انطلاق الاتحاد، واستعراض رؤيته التأسيسية، وتفعيل مسارات العمل المشترك للارتقاء بالقيادة التربوية العربية.';
  const aboutTarbwy =
    settings.about_tarbwy ||
    '«تربوي — الاتحاد العربي للقيادة التربوية» إطار مؤسسي ومهني عربي يُعنى بتمكين القيادات التربوية، وتطوير الفكر والممارسة القيادية في المؤسسات التعليمية، وبناء منظومة تشاركية رصينة تجمع القادة والخبراء التربويين في الوطن العربي.';
  const whyTarbwy =
    settings.why_tarbwy ||
    'أُطلق «تربوي» استجابةً لحاجة المشهد التربوي العربي إلى مظلة مؤسسية جامعة توحّد الجهود القيادية المبعثرة، وتنقل المبادرات التربوية من الأطر الفردية المحدودة إلى عمل مؤسسي مستدام يواكب التحولات التعليمية المعاصرة.';
  const tarbwySpace =
    settings.tarbwy_space ||
    'يسعى «تربوي» إلى بناء مساحة عربية مهنية ومعرفية مشتركة تربط القيادات التربوية وصنّاع السياسات والباحثين، وتتيح تبادل الخبرات النوعية، وتطوير المعايير القيادية، وإطلاق البرامج والمبادرات العابرة للقطاعات التعليمية.';
  const entryInstructions =
    settings.entry_instructions ||
    'الدخول يوم الحفل يتم حصرياً عبر إبراز بطاقة الدخول الشخصية المزودة برمز QR الفريد (عبر الهاتف أو مطبوعة) عند بوابة الاستقبال. يُسجل طلب الحضور العام أولاً بحالة (قيد المراجعة / Pending)، وتصدر بطاقة الدخول بعد اعتماد الطلب فقط.';
  const contactEmail = settings.contact_email || 'events@tarbwy.org';
  const contactPhone = settings.contact_phone || 'يُعلن لاحقاً';
  const capacityTotal = event?.capacity_total ?? 500;
  const maxComp = event?.max_companions_public ?? 2;
  const regOpen = event?.registration_open !== false;

  const heroBgUrl = settings.hero_bg_url || '/assets/images/launch-v2-hero.jpg';
  const venueImageUrl = settings.venue_image_url || '/assets/images/launch-v2-venue.jpg';

  const tracks =
    Array.isArray(settings.tracks_items) && settings.tracks_items.length > 0
      ? settings.tracks_items
      : DEFAULT_TRACKS;
  const speakers =
    Array.isArray(settings.speakers) && settings.speakers.length > 0
      ? settings.speakers
      : DEFAULT_SPEAKERS;
  const programItems =
    Array.isArray(settings.schedule_items) && settings.schedule_items.length > 0
      ? settings.schedule_items
      : DEFAULT_PROGRAM;
  const sponsors = Array.isArray(settings.sponsors) ? settings.sponsors : [];
  const showSponsors = Boolean(settings.show_sponsors) && sponsors.length > 0;

  // Live 4-Unit Countdown Timer (Days, Hours, Minutes, Seconds)
  useEffect(() => {
    const rawTarget = String(settings.countdown_target || '').trim();
    let targetMs = 0;
    if (rawTarget) {
      const parsed = Date.parse(rawTarget.replace(' ', 'T'));
      if (!Number.isNaN(parsed) && parsed > Date.now()) {
        targetMs = parsed;
      }
    }
    if (!targetMs) {
      const now = new Date();
      targetMs = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 14, 18, 0, 0).getTime();
    }

    const pad2 = (n: number) => (n < 10 ? `0${n}` : `${n}`);
    const tick = () => {
      const diff = Math.max(0, targetMs - Date.now());
      const totalSec = Math.floor(diff / 1000);
      const d = Math.floor(totalSec / 86400);
      const h = Math.floor((totalSec % 86400) / 3600);
      const m = Math.floor((totalSec % 3600) / 60);
      const s = totalSec % 60;
      setCountdown({
        days: pad2(d),
        hours: pad2(h),
        minutes: pad2(m),
        seconds: pad2(s),
        tickPulse: true,
      });
      setTimeout(() => {
        setCountdown((prev) => ({ ...prev, tickPulse: false }));
      }, 180);
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [settings.countdown_target]);

  // Subtle Floating Golden Light Particles on Hero Canvas
  useEffect(() => {
    const canvas = particlesCanvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let animId = 0;
    const resize = () => {
      canvas.width = canvas.offsetWidth || window.innerWidth;
      canvas.height = canvas.offsetHeight || window.innerHeight;
    };
    resize();
    window.addEventListener('resize', resize, { passive: true });

    const particles = Array.from({ length: 28 }, () => ({
      x: Math.random() * canvas.width,
      y: Math.random() * canvas.height,
      r: 1.2 + Math.random() * 2.2,
      vx: (Math.random() - 0.5) * 0.25,
      vy: -0.15 - Math.random() * 0.35,
      alpha: 0.2 + Math.random() * 0.55,
    }));

    const render = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      for (const p of particles) {
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
        ctx.fillStyle = `rgba(230, 199, 134, ${p.alpha})`;
        ctx.fill();
      }
      animId = requestAnimationFrame(render);
    };
    animId = requestAnimationFrame(render);

    return () => {
      cancelAnimationFrame(animId);
      window.removeEventListener('resize', resize);
    };
  }, []);

  // ScrollTrigger-style Stacked Speaker Cards & Vertical Timeline Progress
  useEffect(() => {
    const handleScroll = () => {
      const vh = window.innerHeight || 800;

      if (timelineRef.current) {
        const rect = timelineRef.current.getBoundingClientRect();
        const trigger = vh * 0.72;
        const scrolled = trigger - rect.top;
        const pct = Math.min(100, Math.max(8, (scrolled / Math.max(1, rect.height)) * 100));
        setTimelineProgress(pct);
      }

      if (window.innerWidth >= 768 && speakerRefs.current.length > 0) {
        let currentActive = 0;
        const nextTransforms = speakerRefs.current.map((el, idx) => {
          if (!el) return { scale: 1, rotate: 0, brightness: 1 };
          const rect = el.getBoundingClientRect();
          const stickyTop = 104 + idx * 20;
          const nextEl = speakerRefs.current[idx + 1];
          let overlap = 0;
          if (nextEl) {
            const nextRect = nextEl.getBoundingClientRect();
            const dist = nextRect.top - stickyTop;
            overlap = Math.min(1, Math.max(0, 1 - dist / 380));
          }
          if (rect.top <= stickyTop + 45) {
            currentActive = idx;
          }
          const scale = Number((1 - overlap * 0.055).toFixed(3));
          const rotSign = idx % 2 === 0 ? -1 : 1;
          const rotate = Number((overlap * rotSign * 1.15).toFixed(2));
          const brightness = Number((1 - overlap * 0.14).toFixed(2));
          return { scale, rotate, brightness };
        });
        setActiveSpeakerIdx(currentActive);
        setCardTransforms(nextTransforms);
      }
    };

    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleScroll, { passive: true });
    return () => {
      window.removeEventListener('scroll', handleScroll);
      window.removeEventListener('resize', handleScroll);
    };
  }, [speakers.length]);

  const jumpToSpeakerCard = (idx: number) => {
    setActiveSpeakerIdx(idx);
    const el = speakerRefs.current[idx];
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  };

  const trackIcons = [Layers, Globe, Users, Compass];

  return (
    <div className="w-full max-w-full overflow-x-clip bg-[#FAF7F0] text-[#131210] selection:bg-[#D4AF6A]/30" dir="rtl">
      {/* Official Production Deployment Banner (/launch — v2.0) */}
      <div className="bg-[#040D09] text-[#FAF7F0] border-b border-[#D4AF6A]/25 px-4 sm:px-6 py-2.5 text-xs">
        <div className="max-w-[1320px] mx-auto flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <span className="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0" />
            <span className="font-bold text-[#E6C786]">Tarbwy Event Manager v2.0 (/launch):</span>
            <span className="text-stone-300">
              الواجهة الرسمية المعتمدة لحفل الإشهار على المسار الرئيسي (https://tarbwy.org/launch)
            </span>
          </div>
          <div className="flex items-center gap-2">
            {onOpenAdminSettings && (
              <button
                type="button"
                onClick={onOpenAdminSettings}
                className="px-3 py-1 rounded-full bg-[#D4AF6A]/15 hover:bg-[#D4AF6A]/25 text-[#E6C786] border border-[#D4AF6A]/35 font-bold transition-colors cursor-pointer"
              >
                تحرير المحتوى والعد التنازلي (إعدادات الحدث)
              </button>
            )}
          </div>
        </div>
      </div>

      {/* In-Page Sticky Navigation Bar */}
      <div className="sticky top-[57px] z-30 bg-[#05110C]/90 backdrop-blur-md border-b border-[#D4AF6A]/20 px-4 sm:px-6 py-3">
        <div className="max-w-[1320px] mx-auto flex items-center justify-between gap-3">
          <a href="#v2-hero" className="flex items-center gap-2.5 min-w-0 text-white no-underline">
            <span className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#D4AF6A]/30 to-[#14532D]/70 border border-[#D4AF6A] flex items-center justify-center text-[#E6C786] font-black text-base shrink-0">
              ت
            </span>
            <div className="min-w-0">
              <span className="block font-extrabold text-sm sm:text-base text-white truncate">
                تربوي — الاتحاد العربي للقيادة التربوية
              </span>
              <span className="hidden sm:block text-[11px] font-semibold text-[#D4AF6A]">
                حفل الإشهار التأسيسي الرسمي
              </span>
            </div>
          </a>

          <nav className="hidden lg:flex items-center gap-6 text-xs font-bold text-stone-200/85">
            <a href="#v2-about" className="hover:text-[#E6C786] transition-colors">
              البيان التأسيسي
            </a>
            <a href="#v2-vision" className="hover:text-[#E6C786] transition-colors">
              الرؤية والهوية
            </a>
            <a href="#v2-speakers" className="hover:text-[#E6C786] transition-colors">
              بطاقات المتحدثين
            </a>
            <a href="#v2-tracks" className="hover:text-[#E6C786] transition-colors">
              المحاور
            </a>
            <a href="#v2-program" className="hover:text-[#E6C786] transition-colors">
              البرنامج الزمني (يوم واحد)
            </a>
            <a href="#v2-venue" className="hover:text-[#E6C786] transition-colors">
              المقر والوصول
            </a>
          </nav>

          <div className="flex items-center gap-2 shrink-0">
            <a
              href="#v2-register"
              className="hidden sm:inline-flex items-center gap-2 px-5 py-2 rounded-full bg-gradient-to-r from-[#E5C47E] to-[#C89B4B] text-[#05110C] font-extrabold text-xs shadow-lg shadow-[#D4AF6A]/25 hover:brightness-110 transition-all"
            >
              <span>طلب الحضور</span>
              <ArrowLeft className="w-3.5 h-3.5" />
            </a>
            <button
              type="button"
              onClick={() => setMobileMenuOpen((v) => !v)}
              className="lg:hidden inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/10 text-white border border-[#D4AF6A]/35 text-xs font-bold cursor-pointer"
            >
              {mobileMenuOpen ? <X className="w-4 h-4" /> : <Menu className="w-4 h-4" />}
              <span>{mobileMenuOpen ? 'إغلاق' : 'القائمة'}</span>
            </button>
          </div>
        </div>

        {/* Mobile Touch Drawer */}
        {mobileMenuOpen && (
          <div className="lg:hidden mt-3 pt-3 border-t border-[#D4AF6A]/20 flex flex-col gap-1.5 text-sm font-bold text-white">
            <a
              href="#v2-about"
              onClick={() => setMobileMenuOpen(false)}
              className="px-3 py-2.5 rounded-xl hover:bg-white/10"
            >
              01 · عن حفل الإشهار والبيان التأسيسي
            </a>
            <a
              href="#v2-vision"
              onClick={() => setMobileMenuOpen(false)}
              className="px-3 py-2.5 rounded-xl hover:bg-white/10"
            >
              02 · الرؤية والهوية المؤسسية لـ «تربوي»
            </a>
            <a
              href="#v2-speakers"
              onClick={() => setMobileMenuOpen(false)}
              className="px-3 py-2.5 rounded-xl hover:bg-white/10"
            >
              03 · المتحدثون والشخصيات المرجعية
            </a>
            <a
              href="#v2-tracks"
              onClick={() => setMobileMenuOpen(false)}
              className="px-3 py-2.5 rounded-xl hover:bg-white/10"
            >
              04 · المسارات والمحاور الرئيسة
            </a>
            <a
              href="#v2-program"
              onClick={() => setMobileMenuOpen(false)}
              className="px-3 py-2.5 rounded-xl hover:bg-white/10"
            >
              05 · برنامج الحفل (يوم واحد)
            </a>
            <a
              href="#v2-venue"
              onClick={() => setMobileMenuOpen(false)}
              className="px-3 py-2.5 rounded-xl hover:bg-white/10"
            >
              06 · مقر الانعقاد ومعلومات الوصول
            </a>
            <a
              href="#v2-register"
              onClick={() => setMobileMenuOpen(false)}
              className="mt-1 py-3 rounded-full bg-gradient-to-r from-[#E5C47E] to-[#C89B4B] text-[#05110C] text-center font-black"
            >
              طلب الحضور الآن
            </a>
          </div>
        )}
      </div>

      {/* =====================================================================
          1. HERO SECTION — FULL-SCREEN 100VH CENTERED CINEMATIC EXPERIENCE
         ===================================================================== */}
      <section
        id="v2-hero"
        className="relative w-full min-h-[calc(100svh-110px)] flex items-center justify-center py-20 sm:py-28 px-4 sm:px-6 bg-[#05110C] text-white overflow-hidden isolate"
      >
        {/* Full-Width Architectural Background Image */}
        <div
          className="absolute inset-0 -z-30 bg-cover bg-center bg-no-repeat scale-105 transition-transform duration-1000"
          style={{ backgroundImage: `url('${heroBgUrl}')` }}
          aria-hidden="true"
        />

        {/* Sophisticated Multi-Layered Dark Vignette & Radial Lighting */}
        <div
          className="absolute inset-0 -z-20"
          style={{
            background:
              'radial-gradient(circle at 50% 38%, rgba(15, 46, 32, 0.50) 0%, rgba(5, 17, 12, 0.88) 65%, rgba(5, 17, 12, 0.97) 100%), linear-gradient(180deg, rgba(5, 17, 12, 0.78) 0%, rgba(5, 17, 12, 0.56) 45%, rgba(5, 17, 12, 0.96) 100%)',
          }}
          aria-hidden="true"
        />
        <div
          className="absolute inset-0 -z-10 pointer-events-none"
          style={{
            background:
              'radial-gradient(ellipse 55% 40% at 50% 18%, rgba(212, 175, 106, 0.22) 0%, transparent 70%)',
          }}
          aria-hidden="true"
        />

        {/* Subtle Floating Golden Light Particles */}
        <canvas
          ref={particlesCanvasRef}
          className="absolute inset-0 -z-10 w-full h-full pointer-events-none opacity-75"
          aria-hidden="true"
        />

        {/* Perfectly Centered Hero Content Stage */}
        <div className="w-full max-w-[960px] mx-auto flex flex-col items-center justify-center text-center z-10">
          {/* 1. Small Event Identity / Eyebrow Text */}
          <div className="inline-flex items-center gap-2.5 px-4 sm:px-5 py-2 rounded-full bg-[#D4AF6A]/15 border border-[#D4AF6A]/45 text-[#E6C786] text-xs sm:text-sm font-bold mb-6 backdrop-blur-md">
            <span className="w-2 h-2 rounded-full bg-[#E6C786] shadow-[0_0_10px_#E6C786]" />
            <span>الحدث التأسيسي الرسمي · الاتحاد العربي للقيادة التربوية</span>
          </div>

          {/* 2. Main Event Title: حفل إشهار تربوي */}
          <h1 className="text-4xl sm:text-6xl md:text-7xl font-black text-white leading-[1.14] tracking-tight mb-3 drop-shadow-[0_12px_36px_rgba(0,0,0,0.6)]">
            {heroMainTitle}
          </h1>
          <p className="text-lg sm:text-2xl font-bold text-[#E6C786] mb-5">{heroSubIdentity}</p>

          {/* 3. Short, Powerful Description Communicating the Event's Purpose */}
          <p className="text-base sm:text-lg md:text-xl text-stone-100/90 leading-[1.85] max-w-[65ch] mx-auto mb-9">
            {subtitle}
          </p>

          {/* 4. Prominent Live Countdown Timer (Days, Hours, Minutes, Seconds) */}
          <div className="w-full max-w-[680px] mx-auto mb-10">
            <div className="grid grid-cols-4 gap-2.5 sm:gap-4 w-full" role="timer">
              <div className="relative bg-gradient-to-b from-white/10 to-[#0A1F16]/80 border border-[#D4AF6A]/40 rounded-2xl py-3.5 sm:py-5 px-2 backdrop-blur-md shadow-2xl flex flex-col items-center justify-center">
                <span className="text-2xl sm:text-4xl md:text-5xl font-black text-white tabular-nums leading-none">
                  {countdown.days}
                </span>
                <span className="text-xs sm:text-sm font-bold text-[#D4AF6A] mt-1.5">يوم</span>
              </div>
              <div className="relative bg-gradient-to-b from-white/10 to-[#0A1F16]/80 border border-[#D4AF6A]/40 rounded-2xl py-3.5 sm:py-5 px-2 backdrop-blur-md shadow-2xl flex flex-col items-center justify-center">
                <span className="text-2xl sm:text-4xl md:text-5xl font-black text-white tabular-nums leading-none">
                  {countdown.hours}
                </span>
                <span className="text-xs sm:text-sm font-bold text-[#D4AF6A] mt-1.5">ساعة</span>
              </div>
              <div className="relative bg-gradient-to-b from-white/10 to-[#0A1F16]/80 border border-[#D4AF6A]/40 rounded-2xl py-3.5 sm:py-5 px-2 backdrop-blur-md shadow-2xl flex flex-col items-center justify-center">
                <span className="text-2xl sm:text-4xl md:text-5xl font-black text-white tabular-nums leading-none">
                  {countdown.minutes}
                </span>
                <span className="text-xs sm:text-sm font-bold text-[#D4AF6A] mt-1.5">دقيقة</span>
              </div>
              <div className="relative bg-gradient-to-b from-white/10 to-[#0A1F16]/80 border border-[#D4AF6A]/40 rounded-2xl py-3.5 sm:py-5 px-2 backdrop-blur-md shadow-2xl flex flex-col items-center justify-center">
                <span
                  className={`text-2xl sm:text-4xl md:text-5xl font-black tabular-nums leading-none transition-all duration-200 ${
                    countdown.tickPulse ? 'text-[#E6C786] -translate-y-0.5 scale-105' : 'text-white'
                  }`}
                >
                  {countdown.seconds}
                </span>
                <span className="text-xs sm:text-sm font-bold text-[#D4AF6A] mt-1.5">ثانية</span>
              </div>
            </div>

            <div className="mt-3.5 flex flex-wrap items-center justify-center gap-2 sm:gap-3 text-xs text-stone-200/80">
              <span className="px-3 py-1 rounded-full bg-white/10 border border-white/15">
                <strong>الموعد:</strong> {dateDisplay}
              </span>
              <span className="px-3 py-1 rounded-full bg-white/10 border border-white/15">
                <strong>المقر:</strong> {cityDisplay} · {venueName}
              </span>
              <span className="px-3 py-1 rounded-full bg-white/10 border border-white/15">
                <strong>الدخول:</strong> بطاقة QR فردية لكل مشارك معتمد
              </span>
            </div>
          </div>

          {/* 5. Two CTA Buttons: طلب الحضور + اكتشف الحفل */}
          <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3.5 w-full sm:w-auto">
            <a
              href="#v2-register"
              className="inline-flex items-center justify-center gap-2.5 px-9 py-4 rounded-full bg-gradient-to-r from-[#E5C47E] to-[#C89B4B] text-[#05110C] font-black text-base shadow-[0_14px_34px_-8px_rgba(212,175,106,0.65)] hover:-translate-y-0.5 hover:brightness-105 transition-all"
            >
              <span>طلب الحضور</span>
              <ArrowLeft className="w-4 h-4" />
            </a>
            <a
              href="#v2-about"
              className="inline-flex items-center justify-center gap-2.5 px-9 py-4 rounded-full bg-white/10 hover:bg-[#D4AF6A]/20 text-white border border-[#D4AF6A]/45 font-extrabold text-base backdrop-blur-md hover:-translate-y-0.5 transition-all"
            >
              <span>اكتشف الحفل</span>
              <ArrowDown className="w-4 h-4" />
            </a>
          </div>
        </div>
      </section>

      {/* =====================================================================
          2. EVENT IDENTITY, ABOUT THE LAUNCH & VISION (ASYMMETRIC STORYTELLING)
         ===================================================================== */}
      <section id="v2-about" className="py-16 sm:py-24 px-4 sm:px-6 bg-[#FAF7F0] border-b border-[#E2DACB]">
        <div className="max-w-[1320px] mx-auto">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-start">
            {/* Right Editorial Narrative & Monolith Quote */}
            <div className="lg:col-span-5 space-y-6">
              <div className="inline-flex items-center gap-2.5 text-xs font-extrabold text-[#9A3412]">
                <span className="w-7 h-0.5 bg-[#9A3412]" />
                <span>الفصل الأول · البيان التأسيسي للحفل</span>
              </div>
              <h2 className="text-2xl sm:text-4xl font-black text-[#131210] leading-[1.24]">
                انطلاقة مؤسسية تجمع العقول القيادية لرسم أفق تربوي عربي مشترك
              </h2>
              <p className="text-base sm:text-lg text-[#3F3B36] leading-[1.9]">{aboutEvent}</p>

              <blockquote className="bg-[#0A1F16] text-[#FAF7F0] p-6 sm:p-8 rounded-3xl border-r-4 border-[#D4AF6A] shadow-xl">
                <p className="text-base sm:text-lg font-bold leading-[1.85] text-white mb-3">
                  «لا يقتصر حفل إشهار تربوي على كونه مناسبة احتفالية؛ بل هو إعلان تأسيسي عن ولادة مساحة مرجعية عربية تنتقل بالقيادة التربوية من المبادرات الفردية المتفرقة إلى العمل المؤسسي المستدام.»
                </p>
                <cite className="not-italic text-xs font-bold text-[#D4AF6A]">
                  — وثيقة الإشهار · تربوي — الاتحاد العربي للقيادة التربوية
                </cite>
              </blockquote>
            </div>

            {/* Left Staggered Monumental Pillars */}
            <div className="lg:col-span-7 flex flex-col gap-5">
              <article className="bg-white border border-[#CBBFA8] hover:border-[#14532D] rounded-3xl p-6 sm:p-8 flex flex-col sm:flex-row gap-5 items-start shadow-sm hover:-translate-y-1 transition-all">
                <div className="w-16 h-16 rounded-2xl bg-[#0A1F16] text-[#E6C786] font-black text-3xl flex items-center justify-center shrink-0">
                  ٠١
                </div>
                <div>
                  <h3 className="text-xl font-black text-[#131210] mb-2">الإشهار المؤسسي الرسمي</h3>
                  <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.8]">
                    الإعلان الرسمي عن انطلاق «تربوي — الاتحاد العربي للقيادة التربوية» واستعراض هويته التأسيسية ومنطلقاته الفكرية والتنظيمية أمام المجتمع التربوي العربي.
                  </p>
                </div>
              </article>

              <article className="bg-white border border-[#CBBFA8] hover:border-[#14532D] rounded-3xl p-6 sm:p-8 flex flex-col sm:flex-row gap-5 items-start shadow-sm hover:-translate-y-1 transition-all">
                <div className="w-16 h-16 rounded-2xl bg-[#0A1F16] text-[#E6C786] font-black text-3xl flex items-center justify-center shrink-0">
                  ٠٢
                </div>
                <div>
                  <h3 className="text-xl font-black text-[#131210] mb-2">تلاقي القامات وصنّاع القرار</h3>
                  <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.8]">
                    جمع نخبة من القيادات التربوية، والشخصيات المرجعية، والخبراء، والباحثين، وممثلي المؤسسات التعليمية في لقاء رفيع المستوى يرسخ التكامل القيادي.
                  </p>
                </div>
              </article>

              <article className="bg-white border border-[#CBBFA8] hover:border-[#14532D] rounded-3xl p-6 sm:p-8 flex flex-col sm:flex-row gap-5 items-start shadow-sm hover:-translate-y-1 transition-all">
                <div className="w-16 h-16 rounded-2xl bg-[#0A1F16] text-[#E6C786] font-black text-3xl flex items-center justify-center shrink-0">
                  ٠٣
                </div>
                <div>
                  <h3 className="text-xl font-black text-[#131210] mb-2">إطلاق المسارات والمبادرات العملية</h3>
                  <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.8]">
                    تحويل الرؤية التأسيسية إلى برامج عمل ومسارات مهنية ومعرفية ملموسة تسهم في تطوير المعايير القيادية وبناء الشراكات التربوية العابرة للأقطار.
                  </p>
                </div>
              </article>
            </div>
          </div>

          {/* Vision & Identity Asymmetric Contrast Band */}
          <div id="v2-vision" className="mt-14 sm:mt-20 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6 items-stretch">
            <div className="md:col-span-2 lg:col-span-4 bg-gradient-to-br from-[#071A12] to-[#123D26] text-white rounded-3xl p-7 sm:p-9 border border-[#D4AF6A]/35 flex flex-col justify-between">
              <div>
                <span className="inline-block text-xs font-extrabold text-[#E6C786] mb-2">
                  الفصل الثاني · الهوية المؤسسية
                </span>
                <h3 className="text-2xl font-black text-white mb-3">
                  تربوي — الاتحاد العربي للقيادة التربوية
                </h3>
                <p className="text-sm sm:text-base text-stone-100/90 leading-[1.85]">{aboutTarbwy}</p>
              </div>
              <div className="mt-6 pt-5 border-t border-[#D4AF6A]/25 flex flex-wrap items-center justify-between gap-3">
                <span className="text-xs font-bold text-[#E6C786]">
                  السعة المعتمدة: {capacityTotal} مشارك ومرافق
                </span>
                <a
                  href="#v2-register"
                  className="px-5 py-2.5 rounded-full bg-gradient-to-r from-[#E5C47E] to-[#C89B4B] text-[#05110C] font-extrabold text-xs"
                >
                  طلب الحضور
                </a>
              </div>
            </div>

            <article className="md:col-span-1 lg:col-span-4 bg-[#F1ECE1] border border-[#CBBFA8] rounded-3xl p-7 sm:p-8 flex flex-col justify-between">
              <div>
                <span className="inline-block text-xs font-extrabold text-[#9A3412] mb-2">منطلق التأسيس</span>
                <h3 className="text-xl font-black text-[#131210] mb-3">لماذا أُطلق «تربوي»؟</h3>
                <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.85]">{whyTarbwy}</p>
              </div>
              <div className="mt-6 pt-4 border-t border-[#CBBFA8] text-xs font-extrabold text-[#14532D]">
                01 · توحيد الجهود القيادية التربوية العربية
              </div>
            </article>

            <article className="md:col-span-1 lg:col-span-4 bg-[#F1ECE1] border border-[#CBBFA8] rounded-3xl p-7 sm:p-8 flex flex-col justify-between">
              <div>
                <span className="inline-block text-xs font-extrabold text-[#9A3412] mb-2">الأثر المستهدف</span>
                <h3 className="text-xl font-black text-[#131210] mb-3">
                  ما الفضاء الذي يبنيه في المشهد التربوي؟
                </h3>
                <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.85]">{tarbwySpace}</p>
              </div>
              <div className="mt-6 pt-4 border-t border-[#CBBFA8] text-xs font-extrabold text-[#14532D]">
                02 · مساحة مهنية ومعرفية مشتركة ومستدامة
              </div>
            </article>
          </div>
        </div>
      </section>

      {/* =====================================================================
          3. SPEAKERS — SCROLL-DRIVEN STACKED EDITORIAL CARDS (Physical Folio Deck)
         ===================================================================== */}
      <section
        id="v2-speakers"
        className="py-16 sm:py-24 px-4 sm:px-6 bg-gradient-to-b from-[#06150E] via-[#0A2117] to-[#06150E] text-[#FAF7F0] border-b border-[#D4AF6A]/25"
      >
        <div className="max-w-[1320px] mx-auto">
          <div className="flex flex-wrap items-end justify-between gap-6 mb-10 sm:mb-14">
            <div className="max-w-2xl">
              <div className="inline-flex items-center gap-2 text-xs font-extrabold text-[#E6C786] mb-2">
                <span className="w-7 h-0.5 bg-[#E6C786]" />
                <span>الفصل الثالث · المتحدثون والشخصيات المرجعية</span>
              </div>
              <h2 className="text-2xl sm:text-4xl font-black text-white leading-tight mb-3">
                بطاقات المتحدثين والقامات التربوية المشاركة
              </h2>
              <p className="text-sm sm:text-base text-stone-200/80 leading-[1.8]">
                صُممت بطاقات المتحدثين كأوراق دعوة تحريرية متراكبة (Stacked Editorial Cards) تتفاعل مع التمرير وتمنح كل شخصية حضورها المستقل. قابلة للإضافة والتعديل والحذف وإعادة الترتيب من لوحة التحكم.
              </p>
            </div>

            {/* Interactive Deck Switcher Pills */}
            <div className="flex flex-wrap items-center gap-2">
              {speakers.map((_: any, idx: number) => (
                <button
                  key={idx}
                  type="button"
                  onClick={() => jumpToSpeakerCard(idx)}
                  className={`px-4 py-2 rounded-full text-xs font-extrabold border transition-all cursor-pointer tabular-nums ${
                    activeSpeakerIdx === idx
                      ? 'bg-[#D4AF6A] text-[#05110C] border-[#E6C786] shadow-md'
                      : 'bg-white/5 text-stone-200 border-[#D4AF6A]/30 hover:bg-white/10'
                  }`}
                >
                  بطاقة 0{idx + 1}
                </button>
              ))}
            </div>
          </div>

          {/* Stacked Editorial Speaker Folios Stage */}
          <div className="flex flex-col gap-8 sm:gap-10 pb-6">
            {speakers.map((sp: any, idx: number) => {
              const tf = cardTransforms[idx] || { scale: 1, rotate: 0, brightness: 1 };
              return (
                <div
                  key={idx}
                  ref={(el) => {
                    speakerRefs.current[idx] = el;
                  }}
                  className="md:sticky transition-transform duration-150 ease-out origin-top"
                  style={{
                    top: `${104 + idx * 20}px`,
                    zIndex: 10 + idx,
                    transform: `scale(${tf.scale}) rotate(${tf.rotate}deg)`,
                    filter: `brightness(${tf.brightness})`,
                  }}
                >
                  <article className="relative w-full bg-gradient-to-br from-[#FAF7F0] to-[#F1ECE1] text-[#131210] border-2 border-[#D4AF6A] rounded-[26px] shadow-[0_28px_60px_-15px_rgba(0,0,0,0.65)] overflow-hidden grid grid-cols-1 md:grid-cols-12 min-h-[340px]">
                    {/* Inner Ornamental Foil Border */}
                    <div
                      className="pointer-events-none absolute inset-3.5 border border-[#14532D]/15 rounded-[18px]"
                      aria-hidden="true"
                    />

                    {/* Portrait / Monogram Seal Column */}
                    <div className="md:col-span-4 relative bg-gradient-to-br from-[#071A12] via-[#14532D] to-[#0A2418] flex flex-col items-center justify-center p-8 min-h-[220px] md:border-l-2 border-b-2 md:border-b-0 border-[#D4AF6A]/45 overflow-hidden">
                      {sp.photo_url ? (
                        <img
                          src={sp.photo_url}
                          alt={sp.name}
                          referrerPolicy="no-referrer"
                          className="absolute inset-0 w-full h-full object-cover"
                        />
                      ) : (
                        <div className="w-28 h-28 rounded-full border-2 border-dashed border-[#D4AF6A]/70 bg-[#05110C]/55 flex items-center justify-center shadow-2xl mb-3">
                          <span className="text-4xl font-black text-[#E6C786] tabular-nums">
                            0{idx + 1}
                          </span>
                        </div>
                      )}
                      <span className="relative z-10 text-[11px] font-bold text-stone-100 bg-[#05110C]/70 px-3.5 py-1 rounded-full border border-[#D4AF6A]/35">
                        {sp.photo_url ? `المتحدث الرسمي 0${idx + 1}` : 'صورة المتحدث · قابلة للرفع من الإدارة'}
                      </span>
                    </div>

                    {/* Folio Content Column */}
                    <div className="md:col-span-8 p-6 sm:p-10 flex flex-col justify-between relative z-10">
                      <div>
                        <div className="flex flex-wrap items-center justify-between gap-2 pb-3.5 mb-4 border-b border-[#E2DACB]">
                          <span className="px-3.5 py-1 rounded-full bg-[#14532D]/10 text-[#14532D] text-xs font-extrabold tabular-nums">
                            بطاقة متحدث · 0{idx + 1} / 0{speakers.length}
                          </span>
                          <span className="text-xs font-bold text-[#9A3412]">
                            حفل إشهار تربوي · القائمة المرجعية
                          </span>
                        </div>

                        <h3 className="text-2xl sm:text-3xl font-black text-[#131210] mb-2 leading-tight">
                          {sp.name}
                        </h3>
                        <p className="text-sm sm:text-base font-extrabold text-[#14532D] mb-4">
                          {sp.role}
                        </p>
                        <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.85] max-w-[65ch]">
                          {sp.bio ||
                            'نبذة مهنية موجزة تُستكمل فور الاعتماد النهائي لقائمة المتحدثين والشخصيات المرجعية المشاركة في حفل الإشهار.'}
                        </p>
                      </div>

                      <div className="mt-6 pt-4 border-t border-dashed border-[#CBBFA8] flex flex-wrap items-center justify-between gap-3 text-xs">
                        {sp.link_url ? (
                          <a
                            href={sp.link_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-[#14532D] text-white font-extrabold hover:bg-emerald-800 transition-colors"
                          >
                            <span>استعراض الملف التعريفي والمؤسسي</span>
                            <ExternalLink className="w-3.5 h-3.5" />
                          </a>
                        ) : (
                          <span className="font-bold text-[#6E685F]">
                            بطاقة قابلة للتحرير والإضافة والحذف وإعادة الترتيب من إعدادات الحدث
                          </span>
                        )}
                        <a
                          href="#v2-register"
                          className="font-extrabold text-[#14532D] hover:underline inline-flex items-center gap-1"
                        >
                          <span>احجز مقعدك لحضور الجلسة</span>
                          <ArrowLeft className="w-3.5 h-3.5" />
                        </a>
                      </div>
                    </div>
                  </article>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {/* =====================================================================
          4. EVENT TRACKS & MAIN THEMES — NUMBERED ARCHITECTURAL MONOLITHS
         ===================================================================== */}
      <section id="v2-tracks" className="py-16 sm:py-24 px-4 sm:px-6 bg-[#F1ECE1] border-b border-[#CBBFA8]">
        <div className="max-w-[1320px] mx-auto">
          <div className="max-w-3xl mb-10">
            <div className="inline-flex items-center gap-2 text-xs font-extrabold text-[#9A3412] mb-2">
              <span className="w-7 h-0.5 bg-[#9A3412]" />
              <span>الفصل الرابع · المسارات والمحاور الرئيسة</span>
            </div>
            <h2 className="text-2xl sm:text-4xl font-black text-[#131210] leading-tight mb-3">
              المسارات الفكرية والمحاور التأسيسية للحفل
            </h2>
            <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.8]">
              صُممت هذه المسارات لتكون قابلة للتحرير الكامل من إعدادات الحدث فور اعتماد المحاور الرسمية لحفل الإشهار، دون اختلاق أي محاور غير معتمدة.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {tracks.map((tr: any, idx: number) => {
              const IconComp = trackIcons[idx % trackIcons.length];
              return (
                <article
                  key={idx}
                  className="relative bg-white border border-[#CBBFA8] hover:border-[#14532D] rounded-3xl p-7 sm:p-9 overflow-hidden shadow-sm hover:-translate-y-1 transition-all"
                >
                  <span
                    className="pointer-events-none select-none absolute -top-2 left-5 text-7xl sm:text-8xl font-black text-[#D4AF6A]/15 tabular-nums leading-none"
                    aria-hidden="true"
                  >
                    0{idx + 1}
                  </span>
                  <div className="relative z-10">
                    <div className="flex items-center gap-3.5 mb-5">
                      <div className="w-14 h-14 rounded-2xl bg-[#0A1F16] text-[#E6C786] flex items-center justify-center shrink-0">
                        <IconComp className="w-6 h-6" />
                      </div>
                      <span className="px-3.5 py-1 rounded-full bg-[#9A3412]/10 text-[#9A3412] text-xs font-extrabold">
                        {tr.badge || `المسار 0${idx + 1}`}
                      </span>
                    </div>
                    <h3 className="text-xl sm:text-2xl font-black text-[#131210] mb-3 leading-snug">
                      {tr.title}
                    </h3>
                    <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.85]">{tr.desc}</p>
                  </div>
                </article>
              );
            })}
          </div>
        </div>
      </section>

      {/* =====================================================================
          5. EVENT SCHEDULE — SINGLE-DAY VERTICAL TIMELINE (ONE DAY ONLY)
         ===================================================================== */}
      <section id="v2-program" className="py-16 sm:py-24 px-4 sm:px-6 bg-[#FAF7F0] border-b border-[#E2DACB]">
        <div className="max-w-[1060px] mx-auto">
          <div className="text-center max-w-2xl mx-auto mb-12 sm:mb-16">
            <div className="inline-flex items-center justify-center gap-2 text-xs font-extrabold text-[#9A3412] mb-2">
              <span className="w-7 h-0.5 bg-[#9A3412]" />
              <span>الفصل الخامس · البرنامج الزمني للحفل</span>
            </div>
            <h2 className="text-2xl sm:text-4xl font-black text-[#131210] leading-tight mb-3">
              مسار فقرات وجلسات يوم الإشهار
            </h2>
            <p className="text-sm sm:text-base text-[#3F3B36] leading-[1.8]">
              ينعقد حفل إشهار «تربوي» في <strong>يوم واحد مكثف</strong> يبدأ بمراسم الاستقبال والتسجيل الرقمي وينتهي بالبيان الختامي ولقاء التواصل المهني. جميع الأوقات والفقرات قابلة للتحديث الفوري من إعدادات الحدث.
            </p>
            <div className="mt-4 inline-flex items-center gap-2 px-5 py-2 rounded-full bg-[#0A1F16] text-[#E6C786] text-xs font-extrabold">
              <Clock className="w-4 h-4" />
              <span>برنامج يوم واحد متكامل · {dateDisplay}</span>
            </div>
          </div>

          {/* Single-Day Connected Vertical Timeline */}
          <div ref={timelineRef} className="relative py-2">
            {/* Vertical Connecting Spine */}
            <div
              className="absolute top-6 bottom-6 right-[19px] md:right-[236px] w-[3px] bg-[#CBBFA8] rounded-full overflow-hidden"
              aria-hidden="true"
            >
              <div
                className="w-full bg-gradient-to-b from-[#D4AF6A] via-[#14532D] to-[#9A3412] transition-all duration-150"
                style={{ height: `${timelineProgress}%` }}
              />
            </div>

            <div className="space-y-7 sm:space-y-8">
              {programItems.map((item: any, idx: number) => {
                const isFirst = idx === 0;
                const isLast = idx === programItems.length - 1;
                const isCeremonial = isFirst || isLast;
                const stageLabel =
                  item.stage ||
                  (isFirst
                    ? 'فاتحة الحفل · مراسم الافتتاح'
                    : isLast
                    ? 'مسك الختام · الجلسة الختامية'
                    : `الجلسة 0${idx + 1}`);

                return (
                  <div
                    key={idx}
                    className="relative grid grid-cols-[40px_1fr] md:grid-cols-[206px_64px_1fr] gap-3 md:gap-0 items-start"
                  >
                    {/* Time Column (Placed above title on mobile, side column on desktop) */}
                    <div className="col-start-2 row-start-1 md:col-start-1 md:row-start-1 md:pt-4 md:pl-5 md:text-left flex flex-wrap md:block items-center gap-2">
                      <span className="inline-block px-3.5 py-1.5 rounded-xl bg-[#F1ECE1] border border-[#CBBFA8] text-[#14532D] text-xs sm:text-sm font-black tabular-nums">
                        {item.time || 'يُحدد لاحقاً'}
                      </span>
                      <span className="block text-[11px] font-extrabold text-[#6E685F] md:mt-1.5 tabular-nums">
                        المرحلة 0{idx + 1} من 0{programItems.length}
                      </span>
                    </div>

                    {/* Visual Marker Node on Vertical Spine */}
                    <div className="col-start-1 row-start-1 row-span-2 md:col-start-2 md:row-start-1 md:row-span-1 flex justify-center pt-1 md:pt-3.5 z-10">
                      <div
                        className={`w-10 h-10 md:w-11 md:h-11 rounded-full flex items-center justify-center border-[3px] shadow-[0_0_0_6px_#FAF7F0] ${
                          isCeremonial
                            ? 'bg-[#0A1F16] border-[#D4AF6A] text-[#E6C786]'
                            : 'bg-white border-[#14532D] text-[#14532D]'
                        }`}
                      >
                        {isFirst ? (
                          <Star className="w-4 h-4" />
                        ) : isLast ? (
                          <CheckCircle2 className="w-4 h-4" />
                        ) : (
                          <Clock className="w-4 h-4" />
                        )}
                      </div>
                    </div>

                    {/* Session Card */}
                    <article
                      className={`col-start-2 row-start-2 md:col-start-3 md:row-start-1 rounded-3xl p-5 sm:p-7 transition-all ${
                        isCeremonial
                          ? 'bg-gradient-to-br from-[#081D14] to-[#103523] text-white border-[1.5px] border-[#D4AF6A] shadow-xl'
                          : 'bg-white text-[#131210] border border-[#CBBFA8] shadow-sm hover:border-[#14532D]'
                      }`}
                    >
                      {isFirst ? (
                        <span className="inline-block px-3 py-1 rounded-full bg-[#D4AF6A]/20 border border-[#D4AF6A]/45 text-[#E6C786] text-[11px] font-extrabold mb-2.5">
                          ★ الجلسة الافتتاحية وبداية مراسم الإشهار
                        </span>
                      ) : isLast ? (
                        <span className="inline-block px-3 py-1 rounded-full bg-[#D4AF6A]/20 border border-[#D4AF6A]/45 text-[#E6C786] text-[11px] font-extrabold mb-2.5">
                          ✦ الجلسة الختامية وإعلان المخرجات التأسيسية
                        </span>
                      ) : (
                        <span className="inline-block text-xs font-extrabold text-[#9A3412] mb-1.5">
                          {stageLabel}
                        </span>
                      )}

                      <h3
                        className={`text-lg sm:text-xl font-black mb-2 leading-snug ${
                          isCeremonial ? 'text-white' : 'text-[#131210]'
                        }`}
                      >
                        {item.title}
                      </h3>
                      {item.desc && (
                        <p
                          className={`text-sm sm:text-base leading-[1.8] ${
                            isCeremonial ? 'text-stone-200/85' : 'text-[#3F3B36]'
                          }`}
                        >
                          {item.desc}
                        </p>
                      )}
                    </article>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </section>

      {/* =====================================================================
          6. EVENT VENUE — ARCHITECTURAL PHOTOGRAPHY & LOCATION SHOWCASE
         ===================================================================== */}
      <section id="v2-venue" className="py-16 sm:py-24 px-4 sm:px-6 bg-[#05110C] text-[#FAF7F0] border-b border-[#D4AF6A]/25">
        <div className="max-w-[1320px] mx-auto">
          <div className="max-w-2xl mb-10">
            <div className="inline-flex items-center gap-2 text-xs font-extrabold text-[#E6C786] mb-2">
              <span className="w-7 h-0.5 bg-[#E6C786]" />
              <span>الفصل السادس · مقر الانعقاد وبروتوكول الوصول</span>
            </div>
            <h2 className="text-2xl sm:text-4xl font-black text-white leading-tight mb-2">
              المكان ومعلومات الوصول والدخول
            </h2>
            <p className="text-sm sm:text-base text-stone-300">
              تُعرض أدناه تفاصيل القاعة المعتمدة، وإرشادات الوصول ومواقف السيارات، وآلية الدخول عبر بطاقة QR الفردية.
            </p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
            {/* Large Venue Photograph with Glassmorphic Location Card */}
            <div
              className="lg:col-span-7 relative min-h-[360px] sm:min-h-[440px] rounded-3xl overflow-hidden border border-[#D4AF6A]/35 flex items-end p-5 sm:p-8 bg-cover bg-center isolate"
              style={{ backgroundImage: `url('${venueImageUrl}')` }}
            >
              <div
                className="absolute inset-0 -z-10"
                style={{
                  background:
                    'linear-gradient(180deg, rgba(5,17,12,0.15) 0%, rgba(5,17,12,0.82) 70%, rgba(5,17,12,0.96) 100%)',
                }}
              />
              <div className="w-full bg-[#05110C]/80 backdrop-blur-md border border-[#D4AF6A]/40 rounded-2xl p-5 sm:p-7">
                <span className="inline-flex items-center gap-1.5 text-xs font-extrabold text-[#E6C786] mb-1.5">
                  <MapPin className="w-3.5 h-3.5" />
                  <span>المقر الرسمي لانعقاد حفل الإشهار</span>
                </span>
                <h3 className="text-xl sm:text-2xl font-black text-white mb-2">
                  {cityDisplay} — {venueName}
                </h3>
                <p className="text-sm sm:text-base text-stone-200/90 leading-relaxed mb-5">
                  {venueAddress}
                </p>
                <div className="flex flex-wrap items-center gap-3">
                  {mapUrl ? (
                    <a
                      href={mapUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-gradient-to-r from-[#E5C47E] to-[#C89B4B] text-[#05110C] font-extrabold text-xs"
                    >
                      <span>فتح الموقع على الخريطة التفاعلية</span>
                      <ExternalLink className="w-3.5 h-3.5" />
                    </a>
                  ) : (
                    <span className="text-xs font-bold text-[#E6C786] px-3.5 py-2 rounded-xl bg-[#D4AF6A]/15 border border-[#D4AF6A]/30">
                      رابط الخريطة التفاعلية: يُفعل تلقائياً فور تثبيت القاعة النهائية في إعدادات الحدث
                    </span>
                  )}
                  <a
                    href="#v2-register"
                    className="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-white/10 hover:bg-white/15 text-white border border-white/25 font-bold text-xs"
                  >
                    طلب بطاقة حضور
                  </a>
                </div>
              </div>
            </div>

            {/* Side Stack: Arrival & QR Protocol */}
            <div className="lg:col-span-5 flex flex-col gap-6">
              <div className="bg-white/5 border border-[#D4AF6A]/25 rounded-3xl p-6 sm:p-8 flex-1 flex flex-col justify-between">
                <div>
                  <span className="text-xs font-extrabold text-[#E6C786]">01 · الوصول والاصطفاف</span>
                  <h3 className="text-xl font-black text-white mt-1.5 mb-2.5">
                    إرشادات الوصول ومواقف السيارات
                  </h3>
                  <p className="text-sm sm:text-base text-stone-200/80 leading-[1.8]">{accessInfo}</p>
                </div>
                <div className="mt-5 pt-3.5 border-t border-white/10 text-xs font-bold text-[#D4AF6A]">
                  بوابات استقبال مخصصة لكبار الشخصيات والضيوف المسجلين
                </div>
              </div>

              <div className="bg-white/5 border border-[#D4AF6A]/25 rounded-3xl p-6 sm:p-8 flex-1 flex flex-col justify-between">
                <div>
                  <span className="text-xs font-extrabold text-[#E6C786]">
                    02 · بروتوكول بوابة الاستقبال
                  </span>
                  <h3 className="text-xl font-black text-white mt-1.5 mb-2.5">
                    الدخول الحصري عبر بطاقة QR الفردية
                  </h3>
                  <p className="text-sm sm:text-base text-stone-200/80 leading-[1.8]">
                    {entryInstructions}
                  </p>
                </div>
                <div className="mt-5 pt-3.5 border-t border-white/10 text-xs font-bold text-[#D4AF6A]">
                  تحقق فوري عبر النظام المركزي لمنع التكرار وضمان سلاسة الدخول
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* =====================================================================
          7. CEREMONIAL REGISTRATION PAVILION (Integrated Public Registration)
         ===================================================================== */}
      <section
        id="v2-register"
        className="py-16 sm:py-24 px-4 sm:px-6 bg-gradient-to-b from-[#F1ECE1] to-[#FAF7F0] border-b border-[#CBBFA8]"
      >
        <div className="max-w-[1320px] mx-auto">
          <div className="grid grid-cols-1 lg:grid-cols-12 bg-white border-2 border-[#14532D] rounded-3xl overflow-hidden shadow-2xl">
            {/* Right Charter Side */}
            <div className="lg:col-span-5 bg-gradient-to-br from-[#06150E] via-[#0F2E20] to-[#14532D] text-white p-7 sm:p-11 flex flex-col justify-between border-b lg:border-b-0 lg:border-l border-[#D4AF6A]/30">
              <div>
                <span className="inline-block text-xs font-extrabold text-[#E6C786] mb-2">
                  الفصل السابع · التسجيل وطلب الحضور
                </span>
                <h2 className="text-2xl sm:text-3xl font-black text-white leading-tight mb-3">
                  سجّل طلب حضورك لحفل إشهار «تربوي»
                </h2>
                <p className="text-sm sm:text-base text-stone-100/85 leading-[1.8] mb-7">
                  ترتبط هذه الاستمارة مباشرة بقاعدة البيانات المركزية للحدث. يُحفظ الطلب فور إرساله بحالة{' '}
                  <strong>قيد المراجعة (Pending)</strong>، وفور اعتماده يصدر النظام بطاقة دخول رقمية مستقلة برمز{' '}
                  <strong>QR Code</strong> للضيف الأساسي ولكل مرافق معتمد.
                </p>

                <div className="space-y-3.5">
                  {[
                    {
                      num: '01',
                      title: 'تقديم طلب الحضور والمرافقين',
                      desc: `إدخال بياناتك الرسمية وإضافة المرافقين (حتى ${maxComp} مرافقين).`,
                    },
                    {
                      num: '02',
                      title: 'إشعار الاستلام الفوري (Pending)',
                      desc: 'إصدار رقم مرجعي للطلب وإرسال رسالة تأكيد الاستلام لبريدك الإلكتروني.',
                    },
                    {
                      num: '03',
                      title: 'المراجعة والاعتماد من اللجنة',
                      desc: 'مراجعة الطلب واعتماده (Approved) أو إدراجه في قائمة الانتظار (Waitlist).',
                    },
                    {
                      num: '04',
                      title: 'إصدار بطاقات الدخول الفردية (QR)',
                      desc: 'توليد بطاقة QR مشفرة ومستقلة لكل شخص معتمد للدخول يوم الحفل.',
                    },
                  ].map((st) => (
                    <div
                      key={st.num}
                      className="flex items-start gap-3.5 p-3.5 rounded-2xl bg-white/5 border border-[#D4AF6A]/20"
                    >
                      <span className="w-8 h-8 rounded-full bg-[#D4AF6A] text-[#05110C] font-black text-xs flex items-center justify-center shrink-0 tabular-nums">
                        {st.num}
                      </span>
                      <div>
                        <strong className="block text-sm text-white">{st.title}</strong>
                        <span className="text-xs text-stone-200/75">{st.desc}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="mt-8 pt-4 border-t border-[#D4AF6A]/25 text-xs text-stone-200/85">
                نظام التسجيل متصل مباشرة بقاعدة بيانات الحدث وإصدار بطاقات QR الفردية.
              </div>
            </div>

            {/* Left Form Side */}
            <div className="lg:col-span-7 p-6 sm:p-11 bg-white">
              {!regOpen ? (
                <div className="p-6 bg-amber-50 border border-amber-300 rounded-2xl text-amber-950 font-bold text-sm">
                  باب تقديم طلبات الحضور مغلق حالياً لاكتمال الطاقة الاستيعابية أو انتهاء فترة التسجيل.
                </div>
              ) : (
                <form onSubmit={onSubmitRegistration} className="space-y-6 relative overflow-hidden">
                  {/* Honeypot field (RTL-safe clipped) */}
                  <div className="sr-only" aria-hidden="true">
                    <input
                      type="text"
                      tabIndex={-1}
                      autoComplete="off"
                      value={regHoneypot}
                      onChange={(e) => setRegHoneypot(e.target.value)}
                    />
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-extrabold text-[#131210] mb-1.5">
                        الاسم الكامل (كما سيظهر في بطاقة الدخول) <span className="text-red-700">*</span>
                      </label>
                      <input
                        type="text"
                        required
                        value={regName}
                        onChange={(e) => setRegName(e.target.value)}
                        placeholder="مثال: د. عبدالله بن محمد السليمان"
                        className="w-full px-4 py-3 text-sm border border-[#CBBFA8] rounded-xl bg-[#FAF7F0] focus:outline-none focus:border-[#14532D]"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-extrabold text-[#131210] mb-1.5">
                        البريد الإلكتروني (لإرسال البطاقة وQR) <span className="text-red-700">*</span>
                      </label>
                      <input
                        type="email"
                        required
                        dir="ltr"
                        value={regEmail}
                        onChange={(e) => setRegEmail(e.target.value)}
                        placeholder="name@organization.org"
                        className="w-full px-4 py-3 text-sm border border-[#CBBFA8] rounded-xl bg-[#FAF7F0] focus:outline-none focus:border-[#14532D]"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-extrabold text-[#131210] mb-1.5">
                        رقم الهاتف / الجوال <span className="text-red-700">*</span>
                      </label>
                      <input
                        type="tel"
                        required
                        dir="ltr"
                        value={regPhone}
                        onChange={(e) => setRegPhone(e.target.value)}
                        placeholder="+966 5X XXX XXXX"
                        className="w-full px-4 py-3 text-sm border border-[#CBBFA8] rounded-xl bg-[#FAF7F0] focus:outline-none focus:border-[#14532D]"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-extrabold text-[#131210] mb-1.5">
                        المؤسسة / الجهة التربوية <span className="text-red-700">*</span>
                      </label>
                      <input
                        type="text"
                        required
                        value={regOrg}
                        onChange={(e) => setRegOrg(e.target.value)}
                        placeholder="اسم الجامعة / الوزارة / المؤسسة"
                        className="w-full px-4 py-3 text-sm border border-[#CBBFA8] rounded-xl bg-[#FAF7F0] focus:outline-none focus:border-[#14532D]"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-extrabold text-[#131210] mb-1.5">
                        الصفة المهنية / المسمى الوظيفي <span className="text-red-700">*</span>
                      </label>
                      <input
                        type="text"
                        required
                        value={regTitle}
                        onChange={(e) => setRegTitle(e.target.value)}
                        placeholder="المسمى الوظيفي أو الصفة الأكاديمية"
                        className="w-full px-4 py-3 text-sm border border-[#CBBFA8] rounded-xl bg-[#FAF7F0] focus:outline-none focus:border-[#14532D]"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-extrabold text-[#131210] mb-1.5">
                        الاحتياجات الخاصة / الغذائية (اختياري)
                      </label>
                      <input
                        type="text"
                        value={regDietary}
                        onChange={(e) => setRegDietary(e.target.value)}
                        placeholder="اختياري — يُحفظ بسرية لفريق التنظيم"
                        className="w-full px-4 py-3 text-sm border border-[#CBBFA8] rounded-xl bg-[#FAF7F0] focus:outline-none focus:border-[#14532D]"
                      />
                    </div>
                  </div>

                  {/* Companions Section */}
                  <div className="p-5 bg-[#FAF7F0] border border-[#E2DACB] rounded-2xl space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <div>
                        <h3 className="text-sm font-black text-[#131210]">
                          إضافة مرافقين (الحد الأقصى المسموح: {maxComp})
                        </h3>
                        <p className="text-xs text-[#6E685F]">
                          يُنشأ لكل مرافق سجل مستقل في قاعدة البيانات ويصدر له رمز QR مستقل عند اعتماد الطلب.
                        </p>
                      </div>
                      {regCompanions.length < maxComp && (
                        <button
                          type="button"
                          onClick={() =>
                            setRegCompanions((prev) => [
                              ...prev,
                              { full_name: '', email: '', job_title: '' },
                            ])
                          }
                          className="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-[#CBBFA8] hover:border-[#14532D] text-xs font-extrabold rounded-full cursor-pointer"
                        >
                          <Plus className="w-3.5 h-3.5" />
                          <span>إضافة مرافق</span>
                        </button>
                      )}
                    </div>

                    {regCompanions.map((comp, idx) => (
                      <div
                        key={idx}
                        className="p-4 bg-white border border-[#E2DACB] rounded-xl grid grid-cols-1 sm:grid-cols-4 gap-3 items-end"
                      >
                        <div>
                          <label className="block text-[11px] font-bold mb-1">اسم المرافق الكامل *</label>
                          <input
                            type="text"
                            required
                            value={comp.full_name}
                            onChange={(e) => {
                              const next = [...regCompanions];
                              next[idx].full_name = e.target.value;
                              setRegCompanions(next);
                            }}
                            placeholder="الاسم الكامل للمرافق"
                            className="w-full px-3 py-2 text-xs border border-[#CBBFA8] rounded-lg"
                          />
                        </div>
                        <div>
                          <label className="block text-[11px] font-bold mb-1">بريد المرافق (اختياري)</label>
                          <input
                            type="email"
                            dir="ltr"
                            value={comp.email}
                            onChange={(e) => {
                              const next = [...regCompanions];
                              next[idx].email = e.target.value;
                              setRegCompanions(next);
                            }}
                            placeholder="أو تُرسل لبريدك الأساسي"
                            className="w-full px-3 py-2 text-xs border border-[#CBBFA8] rounded-lg"
                          />
                        </div>
                        <div>
                          <label className="block text-[11px] font-bold mb-1">صلة القرابة / الصفة</label>
                          <input
                            type="text"
                            value={comp.job_title}
                            onChange={(e) => {
                              const next = [...regCompanions];
                              next[idx].job_title = e.target.value;
                              setRegCompanions(next);
                            }}
                            placeholder="زميل / مرافق"
                            className="w-full px-3 py-2 text-xs border border-[#CBBFA8] rounded-lg"
                          />
                        </div>
                        <div>
                          <button
                            type="button"
                            onClick={() => setRegCompanions((prev) => prev.filter((_, i) => i !== idx))}
                            className="w-full px-3 py-2 bg-red-50 hover:bg-red-100 text-red-800 text-xs font-bold rounded-lg inline-flex items-center justify-center gap-1 cursor-pointer"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                            <span>حذف</span>
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>

                  {regFeedback && (
                    <div
                      className={`p-5 rounded-2xl border-2 text-sm font-bold ${
                        regFeedback.ok
                          ? 'bg-emerald-50 border-emerald-700 text-emerald-950'
                          : 'bg-red-50 border-red-700 text-red-950'
                      }`}
                    >
                      <p>{regFeedback.msg}</p>
                      {regFeedback.ref && (
                        <p className="mt-1 text-xs font-mono">
                          الرقم المرجعي للطلب: <strong>{regFeedback.ref}</strong> (الحالة: قيد المراجعة / Pending)
                        </p>
                      )}
                    </div>
                  )}

                  <button
                    type="submit"
                    disabled={regSubmitting}
                    className="w-full py-4 px-8 bg-gradient-to-r from-[#14532D] to-[#0D3B1E] hover:brightness-110 text-white font-black text-base rounded-full shadow-lg transition-all cursor-pointer disabled:opacity-50"
                  >
                    {regSubmitting ? 'جاري إرسال طلب الحضور...' : 'إرسال طلب الحضور للمراجعة'}
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* =====================================================================
          8. PARTNERS & SPONSORS (Strictly Hidden Until Confirmed & Enabled)
         ===================================================================== */}
      {showSponsors && (
        <section id="v2-sponsors" className="py-16 px-4 sm:px-6 bg-[#FAF7F0] border-b border-[#E2DACB]">
          <div className="max-w-[1320px] mx-auto text-center">
            <span className="inline-block text-xs font-extrabold text-[#9A3412] mb-2">
              الشركاء والرعاة الرسميون
            </span>
            <h2 className="text-2xl sm:text-3xl font-black text-[#131210] mb-8">
              الشركاء المؤسسيون والجهات الداعمة
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
              {sponsors.map((sp: any, idx: number) => (
                <div key={idx} className="bg-white border border-[#CBBFA8] rounded-2xl p-6">
                  <span className="block text-xs font-extrabold text-[#9A3412] mb-1">
                    {sp.tier || 'شريك مؤسسي'}
                  </span>
                  <strong className="text-lg font-black text-[#131210]">{sp.name}</strong>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* =====================================================================
          9. FOOTER & CONTACT
         ===================================================================== */}
      <footer id="v2-contact" className="bg-[#040D09] text-stone-300 py-14 px-4 sm:px-6 border-t border-[#D4AF6A]/25">
        <div className="max-w-[1320px] mx-auto space-y-10">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 pb-10 border-b border-white/10">
            <div className="lg:col-span-7 space-y-4">
              <span className="inline-block text-xs font-extrabold text-[#E6C786]">
                الواجهة الرسمية لحفل الإشهار · Tarbwy Launch V2
              </span>
              <h2 className="text-2xl sm:text-3xl font-black text-white">
                تربوي — الاتحاد العربي للقيادة التربوية
              </h2>
              <p className="text-sm sm:text-base text-stone-300/80 max-w-xl leading-[1.8]">
                للاستفسارات المتعلقة بطلبات الحضور، أو الدعوات الرسمية لكبار الشخصيات، أو التنسيق المؤسسي الخاص بحفل الإشهار، يرجى التواصل مباشرة مع اللجنة التنظيمية.
              </p>
              <div className="flex flex-wrap gap-3 pt-1">
                <a
                  href="#v2-register"
                  className="px-6 py-3 rounded-full bg-gradient-to-r from-[#E5C47E] to-[#C89B4B] text-[#05110C] font-black text-xs"
                >
                  طلب الحضور
                </a>
                <a
                  href={`mailto:${contactEmail}`}
                  className="px-6 py-3 rounded-full bg-white/5 hover:bg-white/10 text-white border border-[#D4AF6A]/35 font-bold text-xs"
                >
                  {contactEmail}
                </a>
              </div>
            </div>

            <div className="lg:col-span-5 bg-white/5 border border-[#D4AF6A]/25 rounded-3xl p-6 space-y-3">
              <span className="text-xs font-extrabold text-[#E6C786]">قنوات التواصل الرسمية</span>
              <div>
                <span className="block text-xs text-stone-400">البريد الإلكتروني الرسمي</span>
                <a href={`mailto:${contactEmail}`} className="text-white font-extrabold text-base">
                  {contactEmail}
                </a>
              </div>
              <div>
                <span className="block text-xs text-stone-400">هاتف التنسيق والاستفسارات</span>
                <strong className="text-white font-extrabold text-base">{contactPhone}</strong>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-4 text-xs text-stone-400">
            <div>
              © {new Date().getFullYear()} تربوي — الاتحاد العربي للقيادة التربوية · جميع الحقوق محفوظة
            </div>
            <div className="flex flex-wrap gap-4 font-bold text-stone-300">
              <a href="#v2-hero" className="hover:text-[#E6C786]">
                الرئيسية
              </a>
              <a href="#v2-about" className="hover:text-[#E6C786]">
                البيان التأسيسي
              </a>
              <a href="#v2-speakers" className="hover:text-[#E6C786]">
                بطاقات المتحدثين
              </a>
              <a href="#v2-program" className="hover:text-[#E6C786]">
                البرنامج الزمني
              </a>
              <a href="#v2-venue" className="hover:text-[#E6C786]">
                المقر
              </a>
              <a href="#v2-register" className="hover:text-[#E6C786]">
                طلب الحضور
              </a>
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
};
