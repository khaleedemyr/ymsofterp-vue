<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  filters: Object,
  canChooseOutlet: Boolean,
  outlets: { type: Array, default: () => [] },
  lockedOutlet: { type: Object, default: null },
  selectedOutlet: { type: Object, default: null },
  analysis: { type: Object, default: null },
});

const month = ref(props.filters?.month || new Date().toISOString().slice(0, 7));
const idOutlet = ref(
  props.filters?.id_outlet !== null && props.filters?.id_outlet !== undefined
    ? String(props.filters.id_outlet)
    : '',
);

const activeModal = ref(null);
const expandedPettyCashKeys = ref(new Set());
const expandedPrOpsKeys = ref(new Set());
const expandedCatCostKeys = ref(new Set());

const hasAnalysis = computed(() => !!props.analysis?.outlet);

const periodLabel = computed(() => {
  if (!props.filters?.start_date || !props.filters?.end_date) return '';
  const fmt = (d) => new Date(`${d}T12:00:00`).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
  return `${fmt(props.filters.start_date)} – ${fmt(props.filters.end_date)}`;
});

const modalMeta = computed(() => ({
  revenue: {
    title: 'Detail Revenue',
    subtitle: 'Breakdown penjualan outlet per periode',
    icon: 'fa-solid fa-coins',
    accent: 'from-blue-600 to-indigo-700',
  },
  guest_gsi: {
    title: 'Detail GSI Guest Comment',
    subtitle: 'Guest Satisfaction Index dari form komentar tamu',
    icon: 'fa-solid fa-comment-dots',
    accent: 'from-emerald-600 to-teal-700',
  },
  google_gsi: {
    title: 'Detail GSI Google Review',
    subtitle: 'Review Google & scraper per outlet',
    icon: 'fa-brands fa-google',
    accent: 'from-sky-600 to-blue-700',
  },
  regional: {
    title: 'Detail Kunjungan Regional',
    subtitle: 'Frekuensi kunjungan tim regional per area',
    icon: 'fa-solid fa-map-location-dot',
    accent: 'from-violet-600 to-indigo-700',
  },
  petty_cash: {
    title: 'Detail Pengeluaran Petty Cash',
    subtitle: 'Retail food & retail non food (selain contra bon)',
    icon: 'fa-solid fa-wallet',
    accent: 'from-rose-600 to-orange-700',
  },
  pr_ops: {
    title: 'Detail Pengeluaran PR Ops',
    subtitle: 'Pembayaran Purchase Requisition Ops per kategori',
    icon: 'fa-solid fa-file-invoice-dollar',
    accent: 'from-cyan-600 to-blue-700',
  },
  catcost: {
    title: 'Detail Category Cost Outlet',
    subtitle: 'Internal use, spoil, waste, usage, marketing, dan tipe lainnya',
    icon: 'fa-solid fa-layer-group',
    accent: 'from-emerald-600 to-teal-700',
  },
}));

function applyFilters() {
  const q = { month: month.value };
  if (props.canChooseOutlet && idOutlet.value) {
    q.id_outlet = idOutlet.value;
  }
  router.get(route('outlet-analyzer.index'), q, { preserveState: true, replace: true });
}

function openModal(key) {
  activeModal.value = key;
}

function closeModal() {
  activeModal.value = null;
}

function onKeydown(e) {
  if (e.key === 'Escape') closeModal();
}

watch(activeModal, (val) => {
  document.body.style.overflow = val ? 'hidden' : '';
  if (val !== 'petty_cash') {
    expandedPettyCashKeys.value = new Set();
  }
  if (val !== 'pr_ops') {
    expandedPrOpsKeys.value = new Set();
  }
  if (val !== 'catcost') {
    expandedCatCostKeys.value = new Set();
  }
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});

function formatRupiah(value) {
  const num = Number(value || 0);
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num);
}

function pct(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
  return `${Number(value).toFixed(2)}%`;
}

function pettyCashRowKey(row) {
  return `${row.source}-${row.id}`;
}

function isPettyCashExpanded(row) {
  return expandedPettyCashKeys.value.has(pettyCashRowKey(row));
}

function togglePettyCashRow(row) {
  const key = pettyCashRowKey(row);
  const next = new Set(expandedPettyCashKeys.value);
  if (next.has(key)) {
    next.delete(key);
  } else {
    next.add(key);
  }
  expandedPettyCashKeys.value = next;
}

function prOpsRowKey(row) {
  return row.row_key || `nfp_${row.id}`;
}

function isPrOpsExpanded(row) {
  return expandedPrOpsKeys.value.has(prOpsRowKey(row));
}

function togglePrOpsRow(row) {
  const key = prOpsRowKey(row);
  const next = new Set(expandedPrOpsKeys.value);
  if (next.has(key)) {
    next.delete(key);
  } else {
    next.add(key);
  }
  expandedPrOpsKeys.value = next;
}

function catCostRowKey(row) {
  return row.row_key || `cc_${row.id}`;
}

function isCatCostExpanded(row) {
  return expandedCatCostKeys.value.has(catCostRowKey(row));
}

function toggleCatCostRow(row) {
  const key = catCostRowKey(row);
  const next = new Set(expandedCatCostKeys.value);
  if (next.has(key)) {
    next.delete(key);
  } else {
    next.add(key);
  }
  expandedCatCostKeys.value = next;
}

function formatApprovers(approvers) {
  if (!approvers?.length) return '-';
  return approvers.map((a) => `${a.role}: ${a.name}`).join(' · ');
}

function truncateLabel(value, max = 28) {
  const text = String(value || '');
  return text.length > max ? `${text.slice(0, max)}…` : text;
}

function waiterAvatarUrl(avatar) {
  if (!avatar) return null;
  const value = String(avatar);
  if (value.startsWith('http://') || value.startsWith('https://')) return value;
  if (value.startsWith('/storage/')) return value;
  if (value.startsWith('/')) return value;
  return `/storage/${value}`;
}

function waiterInitial(name) {
  return String(name || '?').trim().charAt(0).toUpperCase() || '?';
}

const chartBase = {
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  legend: { position: 'bottom', fontSize: '13px' },
};

const gsiTone = (value) => {
  const v = Number(value ?? 0);
  if (v >= 85) return 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:border-emerald-300';
  if (v >= 75) return 'text-amber-700 bg-amber-50 border-amber-200 hover:border-amber-300';
  return 'text-red-700 bg-red-50 border-red-200 hover:border-red-300';
};

const kpiCardClass = 'group relative cursor-pointer rounded-xl border p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-blue-400/60';

const regionalAreas = computed(() => props.analysis?.regional_visits?.areas || []);

const areaPieSeries = computed(() => regionalAreas.value.map((a) => a.visit_days));

const areaPieLabels = computed(() => regionalAreas.value.map((a) => a.area));

const areaColorMap = { Bar: '#6366F1', Kitchen: '#F97316', Service: '#10B981' };

const areaPieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'pie' },
  labels: areaPieLabels.value,
  colors: areaPieLabels.value.map((label) => areaColorMap[label] || '#94A3B8'),
  tooltip: {
    y: {
      formatter: (val, opts) => {
        const area = regionalAreas.value[opts.seriesIndex];
        return `${val} hari kunjungan · ${area?.total_hours ?? 0} jam`;
      },
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${Math.round(val)}%`,
  },
}));

const revenueDailySeries = computed(() => [{
  name: 'Revenue',
  data: (props.analysis?.revenue?.daily || []).map((d) => d.revenue),
}]);

const revenueDailyOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, sparkline: { enabled: false } },
  stroke: { curve: 'smooth', width: 2 },
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05 },
  },
  xaxis: {
    categories: (props.analysis?.revenue?.daily || []).map((d) => d.label),
    labels: { rotate: -45, style: { fontSize: '10px' } },
  },
  yaxis: {
    labels: {
      formatter: (v) => `${Math.round(v / 1_000_000)}jt`,
    },
  },
  colors: ['#2563EB'],
  dataLabels: { enabled: false },
  grid: { borderColor: '#E5E7EB' },
  tooltip: { y: { formatter: (v) => formatRupiah(v) } },
}));

const topFoodItems = computed(() => props.analysis?.top_menu_items?.top_food || []);
const topBeverageItems = computed(() => props.analysis?.top_menu_items?.top_beverages || []);

const waiterUpsellTop = computed(() => props.analysis?.waiter_upsell_ranking?.top || []);
const waiterUpsellRest = computed(() => props.analysis?.waiter_upsell_ranking?.rest || []);
const waiterUpsellRank1 = computed(() => waiterUpsellTop.value[0] || null);
const waiterUpsellRank2 = computed(() => waiterUpsellTop.value[1] || null);
const waiterUpsellRank3 = computed(() => waiterUpsellTop.value[2] || null);
const waiterUpsellMaxRevenue = computed(() => Number(waiterUpsellTop.value[0]?.total_revenue || 0));

const topFoodBarSeries = computed(() => [{
  name: 'Qty',
  data: topFoodItems.value.map((item) => Number(item.total_qty)),
}]);
const topFoodBarOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '65%' } },
  xaxis: {
    categories: topFoodItems.value.map((item) => truncateLabel(item.item_name)),
    labels: { style: { fontSize: '11px' } },
  },
  dataLabels: { enabled: true, formatter: (v) => `${v}` },
  colors: ['#F97316'],
  grid: { borderColor: '#E5E7EB' },
  tooltip: {
    y: {
      formatter: (val, opts) => {
        const item = topFoodItems.value[opts.dataPointIndex];
        return item ? `${val} qty · ${formatRupiah(item.total_revenue)}` : `${val}`;
      },
    },
  },
}));

const topBeverageBarSeries = computed(() => [{
  name: 'Qty',
  data: topBeverageItems.value.map((item) => Number(item.total_qty)),
}]);
const topBeverageBarOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '65%' } },
  xaxis: {
    categories: topBeverageItems.value.map((item) => truncateLabel(item.item_name)),
    labels: { style: { fontSize: '11px' } },
  },
  dataLabels: { enabled: true, formatter: (v) => `${v}` },
  colors: ['#0EA5E9'],
  grid: { borderColor: '#E5E7EB' },
  tooltip: {
    y: {
      formatter: (val, opts) => {
        const item = topBeverageItems.value[opts.dataPointIndex];
        return item ? `${val} qty · ${formatRupiah(item.total_revenue)}` : `${val}`;
      },
    },
  },
}));

const guestSubjectSeries = computed(() => [
  { name: 'Excellent', data: (props.analysis?.guest_comment_gsi?.subjects || []).map((s) => s.excellent) },
  { name: 'Good', data: (props.analysis?.guest_comment_gsi?.subjects || []).map((s) => s.good) },
  { name: 'Average', data: (props.analysis?.guest_comment_gsi?.subjects || []).map((s) => s.average) },
  { name: 'Poor', data: (props.analysis?.guest_comment_gsi?.subjects || []).map((s) => s.poor) },
]);

const guestSubjectOptions = computed(() => ({
  chart: { type: 'bar', stacked: true, toolbar: { show: false } },
  plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
  xaxis: {
    categories: (props.analysis?.guest_comment_gsi?.subjects || []).map((s) => s.subject),
    labels: { rotate: -20, style: { fontSize: '10px' } },
  },
  colors: ['#34D399', '#60A5FA', '#FBBF24', '#F87171'],
  legend: { position: 'top' },
  dataLabels: { enabled: false },
}));

const regionalHourlySeries = computed(() => [{
  name: 'Kunjungan',
  data: props.analysis?.regional_visits?.hourly_frequency?.data || [],
}]);

const regionalHourlyOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '70%' } },
  xaxis: {
    categories: props.analysis?.regional_visits?.hourly_frequency?.labels || [],
    labels: { rotate: -45, style: { fontSize: '9px' } },
  },
  colors: ['#6366F1'],
  dataLabels: { enabled: false },
}));

const fjCategories = computed(() =>
  (props.analysis?.fj_inventory?.categories || []).filter((c) => Number(c.amount) > 0),
);

const fjPieSeries = computed(() => fjCategories.value.map((c) => Number(c.amount)));
const fjPieLabels = computed(() => fjCategories.value.map((c) => c.label));

const fjPieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'pie' },
  labels: fjPieLabels.value,
  colors: ['#F97316', '#3B82F6', '#22C55E', '#A855F7', '#EC4899'],
  tooltip: { y: { formatter: (val) => formatRupiah(val) } },
  dataLabels: { enabled: true, formatter: (val) => `${Math.round(val)}%` },
}));

const pettyCashSources = computed(() =>
  (props.analysis?.petty_cash?.sources || []).filter((s) => Number(s.amount) > 0),
);
const pettyCashPieSeries = computed(() => pettyCashSources.value.map((s) => Number(s.amount)));
const pettyCashPieLabels = computed(() => pettyCashSources.value.map((s) => s.label));
const pettyCashPieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'pie' },
  labels: pettyCashPieLabels.value,
  colors: ['#F97316', '#6366F1'],
  tooltip: { y: { formatter: (val) => formatRupiah(val) } },
  dataLabels: { enabled: true, formatter: (val) => `${Math.round(val)}%` },
}));

const pettyCashAllTransactions = computed(() => {
  const pc = props.analysis?.petty_cash?.transactions || {};
  return [...(pc.retail_food || []), ...(pc.retail_non_food || [])].sort(
    (a, b) => String(b.transaction_date || '').localeCompare(String(a.transaction_date || '')),
  );
});

const prOpsCategories = computed(() =>
  (props.analysis?.pr_ops_expenditure?.categories || []).filter((c) => Number(c.amount) > 0),
);
const prOpsPieSeries = computed(() => prOpsCategories.value.map((c) => Number(c.amount)));
const prOpsPieLabels = computed(() => prOpsCategories.value.map((c) => {
  const division = c.division ? `[${c.division}] ` : '';
  return `${division}${c.label}`;
}));
const prOpsPieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'pie' },
  labels: prOpsPieLabels.value,
  colors: ['#0EA5E9', '#6366F1', '#14B8A6', '#F59E0B', '#EC4899', '#8B5CF6', '#EF4444', '#64748B'],
  tooltip: { y: { formatter: (val) => formatRupiah(val) } },
  dataLabels: { enabled: true, formatter: (val) => `${Math.round(val)}%` },
}));

const catCostModes = computed(() =>
  (props.analysis?.category_cost_outlet?.modes || []).filter((m) => Number(m.amount) > 0),
);
const catCostPieSeries = computed(() => catCostModes.value.map((m) => Number(m.amount)));
const catCostPieLabels = computed(() => catCostModes.value.map((m) => m.label));
const catCostPieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'pie' },
  labels: catCostPieLabels.value,
  colors: ['#10B981', '#6366F1', '#F59E0B', '#EF4444', '#8B5CF6', '#0EA5E9', '#EC4899', '#14B8A6', '#64748B', '#F97316'],
  tooltip: { y: { formatter: (val) => formatRupiah(val) } },
  dataLabels: { enabled: true, formatter: (val) => `${Math.round(val)}%` },
}));

const attendanceComposition = computed(() =>
  (props.analysis?.employee_attendance?.composition || []).filter((c) => Number(c.days) > 0),
);
const attendancePieSeries = computed(() => attendanceComposition.value.map((c) => Number(c.days)));
const attendancePieLabels = computed(() => attendanceComposition.value.map((c) => c.label));
const attendancePieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'pie' },
  labels: attendancePieLabels.value,
  colors: ['#10B981', '#EF4444', '#64748B', '#6366F1'],
  tooltip: { y: { formatter: (val) => `${val} hari` } },
}));

const leaveBreakdown = computed(() =>
  (props.analysis?.employee_attendance?.leave_breakdown || []).filter((l) => Number(l.days) > 0),
);
const leavePieSeries = computed(() => leaveBreakdown.value.map((l) => Number(l.days)));
const leavePieLabels = computed(() => leaveBreakdown.value.map((l) => l.name));
const leavePieOptions = computed(() => ({
  ...chartBase,
  chart: { ...chartBase.chart, type: 'pie' },
  labels: leavePieLabels.value,
  colors: ['#6366F1', '#14B8A6', '#F59E0B', '#EC4899', '#8B5CF6', '#0EA5E9'],
  tooltip: { y: { formatter: (val) => `${val} hari` } },
}));

const lateBarSeries = computed(() => [{
  name: 'Telat (menit)',
  data: (props.analysis?.employee_attendance?.top_late || []).map((e) => e.total_telat),
}]);
const lateBarOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '65%' } },
  xaxis: {
    categories: (props.analysis?.employee_attendance?.top_late || []).map((e) => e.name),
    labels: { style: { fontSize: '11px' } },
  },
  dataLabels: { enabled: true, formatter: (v) => `${v} mnt` },
  colors: ['#F59E0B'],
  grid: { borderColor: '#E5E7EB' },
}));

const overtimeBarSeries = computed(() => [{
  name: 'Lembur (jam)',
  data: (props.analysis?.employee_attendance?.top_overtime || []).map((e) => e.total_lembur),
}]);
const overtimeBarOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '65%' } },
  xaxis: {
    categories: (props.analysis?.employee_attendance?.top_overtime || []).map((e) => e.name),
    labels: { style: { fontSize: '11px' } },
  },
  dataLabels: { enabled: true, formatter: (v) => `${v} jam` },
  colors: ['#F97316'],
  grid: { borderColor: '#E5E7EB' },
}));

const attendanceSummary = computed(() => props.analysis?.employee_attendance?.summary || {});

const summaryCards = computed(() => {
  const s = attendanceSummary.value;
  return [
    { key: 'hadir', label: 'Hadir', value: s.present_days ?? 0, unit: 'hari', tone: 'emerald' },
    { key: 'telat', label: 'Terlambat', value: s.total_telat ?? 0, unit: 'menit', tone: 'amber' },
    { key: 'alpha', label: 'Alpha', value: s.alpa_days ?? 0, unit: 'hari', tone: 'rose' },
    { key: 'off', label: 'OFF', value: s.off_days ?? 0, unit: 'hari', tone: 'slate' },
    { key: 'lembur', label: 'Lembur', value: s.total_lembur ?? 0, unit: 'jam', tone: 'orange' },
    { key: 'ph', label: 'PH Kompensasi', value: s.ph_days ?? 0, unit: 'hari', tone: 'sky' },
    { key: 'izin', label: 'Izin & Cuti', value: s.leave_days ?? 0, unit: 'hari', tone: 'indigo' },
    { key: 'pct', label: 'Kehadiran', value: s.percentage ?? 0, unit: '%', tone: 'teal' },
  ];
});

const toneClasses = {
  emerald: 'bg-emerald-50 border-emerald-200 text-emerald-700',
  amber: 'bg-amber-50 border-amber-200 text-amber-700',
  rose: 'bg-rose-50 border-rose-200 text-rose-700',
  slate: 'bg-slate-50 border-slate-200 text-slate-700',
  orange: 'bg-orange-50 border-orange-200 text-orange-700',
  sky: 'bg-sky-50 border-sky-200 text-sky-700',
  indigo: 'bg-indigo-50 border-indigo-200 text-indigo-700',
  teal: 'bg-teal-50 border-teal-200 text-teal-700',
};

function areaBadgeClass(area) {
  if (area === 'Bar') return 'bg-indigo-100 text-indigo-800 border-indigo-200';
  if (area === 'Kitchen') return 'bg-orange-100 text-orange-800 border-orange-200';
  if (area === 'Service') return 'bg-emerald-100 text-emerald-800 border-emerald-200';
  return 'bg-slate-100 text-slate-700 border-slate-200';
}

function visitorArea(userId) {
  const visitor = props.analysis?.regional_visits?.visitors?.find((v) => v.id === userId);
  return visitor?.area || null;
}
</script>

<template>
  <AppLayout>
    <div class="w-full max-w-[1400px] mx-auto px-4 py-6 space-y-5">
      <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Outlet Analyzer</h1>
          <p class="text-sm text-slate-500 mt-1">Analisa outlet per bulan kalender</p>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap items-end gap-3">
        <div v-if="canChooseOutlet">
          <label class="block text-xs font-semibold text-slate-600 mb-1">Outlet</label>
          <select
            v-model="idOutlet"
            class="min-w-[220px] border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">— Pilih outlet —</option>
            <option v-for="o in outlets" :key="o.id_outlet" :value="String(o.id_outlet)">
              {{ o.nama_outlet }}
            </option>
          </select>
        </div>
        <div v-else-if="lockedOutlet" class="text-sm px-3 py-2 rounded-lg bg-blue-50 border border-blue-200 text-blue-900">
          Outlet: <span class="font-semibold">{{ lockedOutlet.nama_outlet }}</span>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Bulan</label>
          <input
            v-model="month"
            type="month"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <button
          type="button"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700"
          @click="applyFilters"
        >
          <i class="fas fa-search mr-2"></i>
          Tampilkan
        </button>
      </div>

      <div v-if="periodLabel" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
        <i class="fas fa-calendar mr-2"></i>
        Periode: <strong>{{ periodLabel }}</strong>
      </div>

      <div v-if="!selectedOutlet" class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-500">
        Pilih outlet dan bulan, lalu klik <strong>Tampilkan</strong>.
      </div>

      <template v-else-if="hasAnalysis">
        <!-- KPI Row (clickable) -->
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <button type="button" :class="[kpiCardClass, 'bg-white border-slate-200 text-left']" @click="openModal('revenue')">
            <p class="text-xs font-semibold uppercase text-slate-500">Revenue</p>
            <p class="text-xl font-bold text-slate-900 mt-1">{{ formatRupiah(analysis.revenue?.total) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ analysis.revenue?.cover ?? 0 }} cover</p>
            <span class="absolute top-3 right-3 text-slate-300 group-hover:text-blue-500 transition-colors">
              <i class="fas fa-arrow-up-right-from-square text-xs"></i>
            </span>
          </button>

          <button
            type="button"
            :class="[kpiCardClass, gsiTone(analysis.guest_comment_gsi?.overall_pct), 'text-left']"
            @click="openModal('guest_gsi')"
          >
            <p class="text-xs font-semibold uppercase opacity-80">GSI Guest Comment</p>
            <p class="text-xl font-bold mt-1">{{ pct(analysis.guest_comment_gsi?.overall_pct) }}</p>
            <p class="text-xs opacity-70 mt-1">{{ analysis.guest_comment_gsi?.total_forms ?? 0 }} form</p>
            <span class="absolute top-3 right-3 opacity-40 group-hover:opacity-100 transition-opacity">
              <i class="fas fa-arrow-up-right-from-square text-xs"></i>
            </span>
          </button>

          <button
            type="button"
            :class="[kpiCardClass, gsiTone(analysis.google_review_gsi?.overall_pct), 'text-left']"
            @click="openModal('google_gsi')"
          >
            <p class="text-xs font-semibold uppercase opacity-80">GSI Google Review</p>
            <p class="text-xl font-bold mt-1">{{ pct(analysis.google_review_gsi?.overall_pct) }}</p>
            <p class="text-xs opacity-70 mt-1">{{ analysis.google_review_gsi?.total_reviews ?? 0 }} review</p>
            <span class="absolute top-3 right-3 opacity-40 group-hover:opacity-100 transition-opacity">
              <i class="fas fa-arrow-up-right-from-square text-xs"></i>
            </span>
          </button>

          <button
            type="button"
            :class="[kpiCardClass, 'bg-white border-slate-200 text-left']"
            @click="openModal('regional')"
          >
            <p class="text-xs font-semibold uppercase text-slate-500">Kunjungan Regional</p>
            <p class="text-xl font-bold text-slate-900 mt-1">{{ analysis.regional_visits?.visit_days ?? 0 }} hari</p>
            <p class="text-xs text-slate-500 mt-1">
              {{ analysis.regional_visits?.unique_visitors ?? 0 }} orang ·
              {{ analysis.regional_visits?.total_hours ?? 0 }} jam
            </p>
            <span class="absolute top-3 right-3 text-slate-300 group-hover:text-violet-500 transition-colors">
              <i class="fas fa-arrow-up-right-from-square text-xs"></i>
            </span>
          </button>
        </section>

        <!-- Top Menu Items -->
        <section class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <h2 class="text-base font-semibold text-slate-900">Top 10 Menu Terjual</h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Berdasarkan qty penjualan POS · Food &amp; Beverages
                <span class="text-blue-600">· Klik Revenue untuk detail lengkap</span>
              </p>
            </div>
            <button
              type="button"
              class="text-xs font-semibold text-blue-700 hover:text-blue-900"
              @click="openModal('revenue')"
            >
              Lihat detail revenue →
            </button>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="rounded-xl border border-orange-100 bg-orange-50/30 p-4">
              <h3 class="text-sm font-semibold text-orange-900 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-utensils text-orange-500"></i>
                Top 10 Food
              </h3>
              <div v-if="topFoodItems.length" class="bg-white rounded-lg p-2 border border-orange-100">
                <apexchart type="bar" height="320" :options="topFoodBarOptions" :series="topFoodBarSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada penjualan food pada periode ini.</p>
              <div v-if="topFoodItems.length" class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="text-left text-xs uppercase text-slate-500 border-b border-orange-100">
                      <th class="py-2 pr-3">#</th>
                      <th class="py-2 pr-3">Menu</th>
                      <th class="py-2 text-right">Qty</th>
                      <th class="py-2 text-right">Revenue</th>
                      <th class="py-2 text-right">Order</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(item, idx) in topFoodItems"
                      :key="item.item_id"
                      class="border-b border-orange-50 last:border-0"
                    >
                      <td class="py-2 pr-3 text-slate-400">{{ idx + 1 }}</td>
                      <td class="py-2 pr-3 font-medium text-slate-800">{{ item.item_name }}</td>
                      <td class="py-2 text-right">{{ item.total_qty }}</td>
                      <td class="py-2 text-right">{{ formatRupiah(item.total_revenue) }}</td>
                      <td class="py-2 text-right text-slate-600">{{ item.order_count }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="rounded-xl border border-sky-100 bg-sky-50/30 p-4">
              <h3 class="text-sm font-semibold text-sky-900 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-mug-hot text-sky-500"></i>
                Top 10 Beverages
              </h3>
              <div v-if="topBeverageItems.length" class="bg-white rounded-lg p-2 border border-sky-100">
                <apexchart type="bar" height="320" :options="topBeverageBarOptions" :series="topBeverageBarSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada penjualan beverages pada periode ini.</p>
              <div v-if="topBeverageItems.length" class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="text-left text-xs uppercase text-slate-500 border-b border-sky-100">
                      <th class="py-2 pr-3">#</th>
                      <th class="py-2 pr-3">Menu</th>
                      <th class="py-2 text-right">Qty</th>
                      <th class="py-2 text-right">Revenue</th>
                      <th class="py-2 text-right">Order</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(item, idx) in topBeverageItems"
                      :key="item.item_id"
                      class="border-b border-sky-50 last:border-0"
                    >
                      <td class="py-2 pr-3 text-slate-400">{{ idx + 1 }}</td>
                      <td class="py-2 pr-3 font-medium text-slate-800">{{ item.item_name }}</td>
                      <td class="py-2 text-right">{{ item.total_qty }}</td>
                      <td class="py-2 text-right">{{ formatRupiah(item.total_revenue) }}</td>
                      <td class="py-2 text-right text-slate-600">{{ item.order_count }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <!-- Waiter Upsell Ranking -->
        <section class="bg-white rounded-xl border border-slate-200 p-5 overflow-hidden">
          <div class="mb-2">
            <h2 class="text-base font-semibold text-slate-900">Ranking Penjualan Waiters</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Top 3 waiter · total revenue dari order POS
            </p>
          </div>

          <div v-if="waiterUpsellTop.length" class="relative rounded-2xl bg-gradient-to-b from-amber-50 via-yellow-50 to-white border border-amber-100 px-4 sm:px-8 pt-8 pb-6">
            <div class="flex items-end justify-center gap-2 sm:gap-6 max-w-2xl mx-auto min-h-[300px]">
              <!-- Rank 2 -->
              <div
                v-if="waiterUpsellRank2"
                class="flex flex-col items-center flex-1 max-w-[150px]"
              >
                <div class="flex flex-col items-center text-center mb-3 px-1">
                  <div class="w-16 h-16 rounded-full bg-white border-4 border-amber-200 shadow-md overflow-hidden flex items-center justify-center mb-2">
                    <img
                      v-if="waiterAvatarUrl(waiterUpsellRank2.avatar)"
                      :src="waiterAvatarUrl(waiterUpsellRank2.avatar)"
                      :alt="waiterUpsellRank2.waiter_name"
                      class="w-full h-full object-cover"
                    />
                    <span v-else class="text-2xl font-bold text-amber-600">{{ waiterInitial(waiterUpsellRank2.waiter_name) }}</span>
                  </div>
                  <p class="text-sm font-bold text-slate-800 leading-tight">{{ waiterUpsellRank2.waiter_name }}</p>
                  <p class="text-base font-bold text-amber-700 mt-1">{{ formatRupiah(waiterUpsellRank2.total_revenue) }}</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">
                    {{ waiterUpsellRank2.order_count }} order · {{ waiterUpsellRank2.cover }} cover
                  </p>
                </div>
                <div class="w-full relative">
                  <div class="absolute -top-1 left-0 right-0 h-2 bg-amber-300 rounded-t-md"></div>
                  <div class="w-full h-24 sm:h-28 bg-amber-400 rounded-t-lg flex items-center justify-center shadow-inner">
                    <span class="text-4xl sm:text-5xl font-black text-amber-900/80">2</span>
                  </div>
                </div>
              </div>

              <!-- Rank 1 -->
              <div
                v-if="waiterUpsellRank1"
                class="flex flex-col items-center flex-1 max-w-[170px] -mt-2 sm:-mt-4 z-10"
              >
                <div class="mb-1">
                  <span class="inline-flex items-center rounded-full bg-yellow-300 text-yellow-900 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide shadow-sm">
                    Top 1
                  </span>
                </div>
                <div class="flex flex-col items-center text-center mb-3 px-1">
                  <div class="w-20 h-20 rounded-full bg-white border-4 border-yellow-300 shadow-lg overflow-hidden flex items-center justify-center mb-2 ring-4 ring-yellow-200/50">
                    <img
                      v-if="waiterAvatarUrl(waiterUpsellRank1.avatar)"
                      :src="waiterAvatarUrl(waiterUpsellRank1.avatar)"
                      :alt="waiterUpsellRank1.waiter_name"
                      class="w-full h-full object-cover"
                    />
                    <span v-else class="text-3xl font-bold text-yellow-600">{{ waiterInitial(waiterUpsellRank1.waiter_name) }}</span>
                  </div>
                  <p class="text-sm font-bold text-slate-900 leading-tight">{{ waiterUpsellRank1.waiter_name }}</p>
                  <p class="text-lg font-bold text-amber-700 mt-1">{{ formatRupiah(waiterUpsellRank1.total_revenue) }}</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">
                    {{ waiterUpsellRank1.order_count }} order · {{ waiterUpsellRank1.cover }} cover
                  </p>
                </div>
                <div class="w-full relative">
                  <div class="absolute -top-1 left-0 right-0 h-2.5 bg-yellow-300 rounded-t-md"></div>
                  <div class="w-full h-32 sm:h-36 bg-amber-400 rounded-t-lg flex items-center justify-center shadow-inner">
                    <span class="text-5xl sm:text-6xl font-black text-amber-900/80">1</span>
                  </div>
                </div>
              </div>

              <!-- Rank 3 -->
              <div
                v-if="waiterUpsellRank3"
                class="flex flex-col items-center flex-1 max-w-[140px]"
              >
                <div class="flex flex-col items-center text-center mb-3 px-1">
                  <div class="w-14 h-14 rounded-full bg-white border-4 border-amber-200 shadow-md overflow-hidden flex items-center justify-center mb-2">
                    <img
                      v-if="waiterAvatarUrl(waiterUpsellRank3.avatar)"
                      :src="waiterAvatarUrl(waiterUpsellRank3.avatar)"
                      :alt="waiterUpsellRank3.waiter_name"
                      class="w-full h-full object-cover"
                    />
                    <span v-else class="text-xl font-bold text-amber-600">{{ waiterInitial(waiterUpsellRank3.waiter_name) }}</span>
                  </div>
                  <p class="text-sm font-bold text-slate-800 leading-tight">{{ waiterUpsellRank3.waiter_name }}</p>
                  <p class="text-sm font-bold text-amber-700 mt-1">{{ formatRupiah(waiterUpsellRank3.total_revenue) }}</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">
                    {{ waiterUpsellRank3.order_count }} order · {{ waiterUpsellRank3.cover }} cover
                  </p>
                </div>
                <div class="w-full relative">
                  <div class="absolute -top-1 left-0 right-0 h-2 bg-amber-300 rounded-t-md"></div>
                  <div class="w-full h-20 sm:h-24 bg-amber-400 rounded-t-lg flex items-center justify-center shadow-inner">
                    <span class="text-3xl sm:text-4xl font-black text-amber-900/80">3</span>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="waiterUpsellRest.length"
              class="mt-6 pt-5 border-t border-amber-200/80 max-w-2xl mx-auto"
            >
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
                Waiter lainnya
              </p>
              <div class="space-y-2.5">
                <div
                  v-for="waiter in waiterUpsellRest"
                  :key="`${waiter.rank}-${waiter.waiter_name}`"
                  class="flex items-center gap-3 rounded-xl bg-white/80 border border-amber-100 px-3 py-2.5 shadow-sm"
                >
                  <span class="w-6 text-center text-xs font-bold text-slate-400 shrink-0">
                    {{ waiter.rank }}
                  </span>
                  <div class="w-10 h-10 rounded-full bg-white border-2 border-amber-200 overflow-hidden flex items-center justify-center shrink-0">
                    <img
                      v-if="waiterAvatarUrl(waiter.avatar)"
                      :src="waiterAvatarUrl(waiter.avatar)"
                      :alt="waiter.waiter_name"
                      class="w-full h-full object-cover"
                    />
                    <span v-else class="text-sm font-bold text-amber-600">{{ waiterInitial(waiter.waiter_name) }}</span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 truncate">{{ waiter.waiter_name }}</p>
                    <div class="mt-1.5 h-1.5 rounded-full bg-amber-100 overflow-hidden">
                      <div
                        class="h-full rounded-full bg-gradient-to-r from-amber-400 to-yellow-500"
                        :style="{ width: `${waiterUpsellMaxRevenue ? Math.max(4, (Number(waiter.total_revenue) / waiterUpsellMaxRevenue) * 100) : 0}%` }"
                      />
                    </div>
                  </div>
                  <p class="text-sm font-bold text-amber-700 shrink-0 tabular-nums">
                    {{ formatRupiah(waiter.total_revenue) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <p v-else class="text-sm text-slate-400 text-center py-12 rounded-xl bg-slate-50 border border-dashed border-slate-200">
            Tidak ada data penjualan waiter pada periode ini.
          </p>
        </section>

        <!-- Regional by Area -->
        <section class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <h2 class="text-base font-semibold text-slate-900">Kunjungan Regional per Area</h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Berdasarkan Regional Management (Bar · Kitchen · Service)
                <span class="text-violet-600">· Klik chart untuk detail</span>
              </p>
            </div>
            <button
              type="button"
              class="text-xs font-semibold text-violet-700 hover:text-violet-900"
              @click="openModal('regional')"
            >
              Lihat detail lengkap →
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div
              v-if="regionalAreas.length"
              class="bg-slate-50 rounded-xl p-3 cursor-pointer hover:ring-2 hover:ring-violet-200 transition-all"
              @click="openModal('regional')"
            >
              <apexchart type="pie" height="320" :options="areaPieOptions" :series="areaPieSeries" />
            </div>
            <div v-else class="bg-slate-50 rounded-xl p-8 text-center text-slate-400 text-sm flex items-center justify-center">
              Tidak ada kunjungan regional pada periode ini.
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="text-left text-xs uppercase text-slate-500 border-b">
                    <th class="py-2 pr-4">Area</th>
                    <th class="py-2 text-right">Hari</th>
                    <th class="py-2 text-right">Jam</th>
                    <th class="py-2 text-right">Anggota</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="area in regionalAreas"
                    :key="area.area"
                    class="border-b border-slate-100 hover:bg-violet-50/50 cursor-pointer"
                    @click="openModal('regional')"
                  >
                    <td class="py-2.5 pr-4">
                      <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold border" :class="areaBadgeClass(area.area)">
                        {{ area.area }}
                      </span>
                    </td>
                    <td class="py-2.5 text-right font-medium">{{ area.visit_days }}</td>
                    <td class="py-2.5 text-right text-slate-600">{{ area.total_hours }}</td>
                    <td class="py-2.5 text-right text-slate-600">{{ area.members?.length ?? 0 }}</td>
                  </tr>
                  <tr v-if="!regionalAreas.length">
                    <td colspan="4" class="py-6 text-center text-slate-400">—</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- FJ Inventory -->
        <section class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="mb-4">
            <h2 class="text-base font-semibold text-slate-900">Belanja Inventory (Rekap FJ)</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Good Receive per kategori gudang
              <span v-if="(analysis.fj_inventory?.retail_food_contra_bon_total ?? 0) > 0">
                · termasuk retail food contra bon {{ formatRupiah(analysis.fj_inventory.retail_food_contra_bon_total) }}
              </span>
            </p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div v-if="fjCategories.length" class="bg-slate-50 rounded-xl p-3">
              <apexchart type="pie" height="320" :options="fjPieOptions" :series="fjPieSeries" />
            </div>
            <div v-else class="bg-slate-50 rounded-xl p-8 text-center text-slate-400 text-sm">
              Tidak ada data belanja inventory pada periode ini.
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="text-left text-xs uppercase text-slate-500 border-b">
                    <th class="py-2 pr-4">Kategori</th>
                    <th class="py-2 text-right">Nominal</th>
                    <th class="py-2 text-right">%</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="cat in analysis.fj_inventory?.categories || []"
                    :key="cat.key"
                    class="border-b border-slate-100"
                  >
                    <td class="py-2 pr-4">{{ cat.label }}</td>
                    <td class="py-2 text-right font-medium">{{ formatRupiah(cat.amount) }}</td>
                    <td class="py-2 text-right text-slate-500">
                      {{
                        analysis.fj_inventory?.line_total > 0
                          ? `${((cat.amount / analysis.fj_inventory.line_total) * 100).toFixed(1)}%`
                          : '-'
                      }}
                    </td>
                  </tr>
                  <tr class="font-bold bg-slate-50">
                    <td class="py-2 pr-4">Line Total</td>
                    <td class="py-2 text-right">{{ formatRupiah(analysis.fj_inventory?.line_total) }}</td>
                    <td class="py-2 text-right">100%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Petty Cash -->
        <section class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <h2 class="text-base font-semibold text-slate-900">Pengeluaran Petty Cash</h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Retail food & retail non food · metode pembayaran selain contra bon
                <span class="text-rose-600">· Klik untuk detail</span>
              </p>
            </div>
            <button
              type="button"
              class="text-left rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 hover:border-rose-300 hover:shadow-sm transition-all"
              @click="openModal('petty_cash')"
            >
              <p class="text-xs font-semibold uppercase text-rose-700">Total Petty Cash</p>
              <p class="text-xl font-bold text-rose-900 mt-0.5">{{ formatRupiah(analysis.petty_cash?.total) }}</p>
              <p class="text-xs text-rose-600 mt-1">
                {{ analysis.petty_cash?.transaction_count ?? 0 }} transaksi
              </p>
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div
              v-if="pettyCashSources.length"
              class="bg-slate-50 rounded-xl p-3 cursor-pointer hover:ring-2 hover:ring-rose-200 transition-all"
              @click="openModal('petty_cash')"
            >
              <apexchart type="pie" height="320" :options="pettyCashPieOptions" :series="pettyCashPieSeries" />
            </div>
            <div
              v-else
              class="bg-slate-50 rounded-xl p-8 text-center text-slate-400 text-sm flex items-center justify-center"
            >
              Tidak ada pengeluaran petty cash pada periode ini.
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="text-left text-xs uppercase text-slate-500 border-b">
                    <th class="py-2 pr-4">Sumber</th>
                    <th class="py-2 text-right">Nominal</th>
                    <th class="py-2 text-right">Transaksi</th>
                    <th class="py-2 text-right">%</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="src in analysis.petty_cash?.sources || []"
                    :key="src.key"
                    class="border-b border-slate-100 hover:bg-rose-50/50 cursor-pointer"
                    @click="openModal('petty_cash')"
                  >
                    <td class="py-2.5 pr-4">{{ src.label }}</td>
                    <td class="py-2.5 text-right font-medium">{{ formatRupiah(src.amount) }}</td>
                    <td class="py-2.5 text-right text-slate-600">
                      {{
                        src.key === 'retail_food'
                          ? (analysis.petty_cash?.retail_food_count ?? 0)
                          : (analysis.petty_cash?.retail_non_food_count ?? 0)
                      }}
                    </td>
                    <td class="py-2.5 text-right text-slate-500">
                      {{
                        analysis.petty_cash?.total > 0
                          ? `${((src.amount / analysis.petty_cash.total) * 100).toFixed(1)}%`
                          : '-'
                      }}
                    </td>
                  </tr>
                  <tr class="font-bold bg-slate-50">
                    <td class="py-2 pr-4">Total</td>
                    <td class="py-2 text-right">{{ formatRupiah(analysis.petty_cash?.total) }}</td>
                    <td class="py-2 text-right">{{ analysis.petty_cash?.transaction_count ?? 0 }}</td>
                    <td class="py-2 text-right">100%</td>
                  </tr>
                  <tr v-if="!(analysis.petty_cash?.sources?.length)">
                    <td colspan="4" class="py-6 text-center text-slate-400">—</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- PR Ops Expenditure -->
        <section class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <h2 class="text-base font-semibold text-slate-900">Pengeluaran Purchase Requisition Ops</h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Pembayaran non food dari PR Ops · per kategori budget
                <span class="text-cyan-600">· Klik untuk detail</span>
              </p>
            </div>
            <button
              type="button"
              class="text-left rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 hover:border-cyan-300 hover:shadow-sm transition-all"
              @click="openModal('pr_ops')"
            >
              <p class="text-xs font-semibold uppercase text-cyan-700">Total PR Ops</p>
              <p class="text-xl font-bold text-cyan-900 mt-0.5">{{ formatRupiah(analysis.pr_ops_expenditure?.total) }}</p>
              <p class="text-xs text-cyan-600 mt-1">
                {{ analysis.pr_ops_expenditure?.payment_count ?? 0 }} pembayaran
              </p>
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div
              v-if="prOpsCategories.length"
              class="bg-slate-50 rounded-xl p-3 cursor-pointer hover:ring-2 hover:ring-cyan-200 transition-all"
              @click="openModal('pr_ops')"
            >
              <apexchart type="pie" height="320" :options="prOpsPieOptions" :series="prOpsPieSeries" />
            </div>
            <div
              v-else
              class="bg-slate-50 rounded-xl p-8 text-center text-slate-400 text-sm flex items-center justify-center"
            >
              Tidak ada pengeluaran PR Ops pada periode ini.
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="text-left text-xs uppercase text-slate-500 border-b">
                    <th class="py-2 pr-4">Kategori</th>
                    <th class="py-2 text-right">Nominal</th>
                    <th class="py-2 text-right">Pembayaran</th>
                    <th class="py-2 text-right">%</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="cat in analysis.pr_ops_expenditure?.categories || []"
                    :key="cat.category_id"
                    class="border-b border-slate-100 hover:bg-cyan-50/50 cursor-pointer"
                    @click="openModal('pr_ops')"
                  >
                    <td class="py-2.5 pr-4">
                      <span v-if="cat.division" class="text-xs text-slate-400 block">{{ cat.division }}</span>
                      {{ cat.label }}
                    </td>
                    <td class="py-2.5 text-right font-medium">{{ formatRupiah(cat.amount) }}</td>
                    <td class="py-2.5 text-right text-slate-600">{{ cat.payment_count ?? 0 }}</td>
                    <td class="py-2.5 text-right text-slate-500">
                      {{
                        analysis.pr_ops_expenditure?.total > 0
                          ? `${((cat.amount / analysis.pr_ops_expenditure.total) * 100).toFixed(1)}%`
                          : '-'
                      }}
                    </td>
                  </tr>
                  <tr class="font-bold bg-slate-50">
                    <td class="py-2 pr-4">Total</td>
                    <td class="py-2 text-right">{{ formatRupiah(analysis.pr_ops_expenditure?.total) }}</td>
                    <td class="py-2 text-right">{{ analysis.pr_ops_expenditure?.payment_count ?? 0 }}</td>
                    <td class="py-2 text-right">100%</td>
                  </tr>
                  <tr v-if="!(analysis.pr_ops_expenditure?.categories?.length)">
                    <td colspan="4" class="py-6 text-center text-slate-400">—</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Category Cost Outlet -->
        <section class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <h2 class="text-base font-semibold text-slate-900">Category Cost Outlet</h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Internal use, spoil, waste, usage, marketing, dan tipe catcost lainnya
                <span class="text-emerald-600">· Klik untuk detail</span>
              </p>
            </div>
            <button
              type="button"
              class="text-left rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 hover:border-emerald-300 hover:shadow-sm transition-all"
              @click="openModal('catcost')"
            >
              <p class="text-xs font-semibold uppercase text-emerald-700">Total Category Cost</p>
              <p class="text-xl font-bold text-emerald-900 mt-0.5">{{ formatRupiah(analysis.category_cost_outlet?.total) }}</p>
              <p class="text-xs text-emerald-600 mt-1">
                {{ analysis.category_cost_outlet?.document_count ?? 0 }} dokumen
              </p>
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div
              v-if="catCostModes.length"
              class="bg-slate-50 rounded-xl p-3 cursor-pointer hover:ring-2 hover:ring-emerald-200 transition-all"
              @click="openModal('catcost')"
            >
              <apexchart type="pie" height="320" :options="catCostPieOptions" :series="catCostPieSeries" />
            </div>
            <div
              v-else
              class="bg-slate-50 rounded-xl p-8 text-center text-slate-400 text-sm flex items-center justify-center"
            >
              Tidak ada category cost outlet pada periode ini.
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="text-left text-xs uppercase text-slate-500 border-b">
                    <th class="py-2 pr-4">Mode Catcost</th>
                    <th class="py-2 text-right">Subtotal MAC</th>
                    <th class="py-2 text-right">Dokumen</th>
                    <th class="py-2 text-right">%</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="mode in analysis.category_cost_outlet?.modes || []"
                    :key="mode.key"
                    class="border-b border-slate-100 hover:bg-emerald-50/50 cursor-pointer"
                    @click="openModal('catcost')"
                  >
                    <td class="py-2.5 pr-4">{{ mode.label }}</td>
                    <td class="py-2.5 text-right font-medium">{{ formatRupiah(mode.amount) }}</td>
                    <td class="py-2.5 text-right text-slate-600">{{ mode.document_count ?? 0 }}</td>
                    <td class="py-2.5 text-right text-slate-500">
                      {{
                        analysis.category_cost_outlet?.total > 0
                          ? `${((mode.amount / analysis.category_cost_outlet.total) * 100).toFixed(1)}%`
                          : '-'
                      }}
                    </td>
                  </tr>
                  <tr class="font-bold bg-slate-50">
                    <td class="py-2 pr-4">Total</td>
                    <td class="py-2 text-right">{{ formatRupiah(analysis.category_cost_outlet?.total) }}</td>
                    <td class="py-2 text-right">{{ analysis.category_cost_outlet?.document_count ?? 0 }}</td>
                    <td class="py-2 text-right">100%</td>
                  </tr>
                  <tr v-if="!(analysis.category_cost_outlet?.modes?.length)">
                    <td colspan="4" class="py-6 text-center text-slate-400">—</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Employee Attendance -->
        <section class="space-y-4">
          <div>
            <h2 class="text-base font-semibold text-slate-900">Kehadiran Karyawan</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              {{ attendanceSummary.employee_count ?? 0 }} karyawan aktif di outlet
            </p>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
            <div
              v-for="card in summaryCards"
              :key="card.key"
              class="rounded-xl border p-3"
              :class="toneClasses[card.tone]"
            >
              <p class="text-[10px] font-semibold uppercase tracking-wide opacity-90">{{ card.label }}</p>
              <p class="text-xl font-bold leading-none mt-2">
                {{ card.value }}<span class="text-xs font-medium ml-1 opacity-70">{{ card.unit }}</span>
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
              <h3 class="text-sm font-semibold text-slate-900">Komposisi Kehadiran</h3>
              <div v-if="attendanceComposition.length" class="mt-3 bg-slate-50 rounded-xl p-2">
                <apexchart type="pie" height="300" :options="attendancePieOptions" :series="attendancePieSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada data komposisi.</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
              <h3 class="text-sm font-semibold text-slate-900">Izin & Cuti</h3>
              <div v-if="leaveBreakdown.length" class="mt-3 bg-slate-50 rounded-xl p-2">
                <apexchart type="pie" height="300" :options="leavePieOptions" :series="leavePieSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada izin/cuti pada periode ini.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
              <h3 class="text-sm font-semibold text-slate-900">Top Terlambat</h3>
              <div v-if="analysis.employee_attendance?.top_late?.length" class="mt-3">
                <apexchart type="bar" height="320" :options="lateBarOptions" :series="lateBarSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada data keterlambatan.</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
              <h3 class="text-sm font-semibold text-slate-900">Top Lembur</h3>
              <div v-if="analysis.employee_attendance?.top_overtime?.length" class="mt-3">
                <apexchart type="bar" height="320" :options="overtimeBarOptions" :series="overtimeBarSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada data lembur.</p>
            </div>
          </div>
        </section>
      </template>
    </div>

    <!-- Full-width detail modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="activeModal"
          class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center p-0 sm:p-4"
          @click.self="closeModal"
        >
          <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeModal"></div>

          <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-[0.98]"
            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 sm:scale-100"
            leave-to-class="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-[0.98]"
          >
            <div
              v-if="activeModal"
              class="relative w-full max-w-[min(1400px,98vw)] max-h-[92vh] bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col overflow-hidden"
            >
              <!-- Header -->
              <div
                class="shrink-0 px-6 py-5 text-white bg-gradient-to-r"
                :class="modalMeta[activeModal]?.accent"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center shrink-0">
                      <i :class="[modalMeta[activeModal]?.icon, 'text-lg']"></i>
                    </div>
                    <div>
                      <h2 class="text-xl font-bold">{{ modalMeta[activeModal]?.title }}</h2>
                      <p class="text-sm text-white/80 mt-0.5">{{ modalMeta[activeModal]?.subtitle }}</p>
                      <p v-if="periodLabel" class="text-xs text-white/60 mt-1">{{ selectedOutlet?.nama_outlet }} · {{ periodLabel }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 flex items-center justify-center transition-colors shrink-0"
                    @click="closeModal"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>

              <!-- Body -->
              <div class="flex-1 overflow-y-auto p-6 space-y-6 oa-modal-scroll">
                <!-- Revenue -->
                <template v-if="activeModal === 'revenue'">
                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-xl bg-blue-50 border border-blue-100 p-4">
                      <p class="text-xs text-blue-600 font-semibold uppercase">Total Revenue</p>
                      <p class="text-2xl font-bold text-blue-900 mt-1">{{ formatRupiah(analysis.revenue?.total) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Cover</p>
                      <p class="text-2xl font-bold text-slate-900 mt-1">{{ analysis.revenue?.cover ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-100 p-4">
                      <p class="text-xs text-amber-700 font-semibold uppercase">Lunch</p>
                      <p class="text-xl font-bold text-amber-900 mt-1">{{ formatRupiah(analysis.revenue?.lunch) }}</p>
                    </div>
                    <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-4">
                      <p class="text-xs text-indigo-700 font-semibold uppercase">Dinner</p>
                      <p class="text-xl font-bold text-indigo-900 mt-1">{{ formatRupiah(analysis.revenue?.dinner) }}</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="rounded-xl bg-red-50 border border-red-100 p-3">
                      <p class="text-[10px] text-red-700 font-semibold uppercase">Discount Promo</p>
                      <p class="text-lg font-bold text-red-900 mt-1">{{ formatRupiah(analysis.revenue?.discount) }}</p>
                    </div>
                    <div class="rounded-xl bg-rose-50 border border-rose-100 p-3">
                      <p class="text-[10px] text-rose-700 font-semibold uppercase">Manual Discount</p>
                      <p class="text-lg font-bold text-rose-900 mt-1">{{ formatRupiah(analysis.revenue?.manual_discount) }}</p>
                    </div>
                    <div class="rounded-xl bg-yellow-50 border border-yellow-100 p-3">
                      <p class="text-[10px] text-yellow-700 font-semibold uppercase">Service Charge</p>
                      <p class="text-lg font-bold text-yellow-900 mt-1">{{ formatRupiah(analysis.revenue?.service_charge) }}</p>
                    </div>
                    <div class="rounded-xl bg-violet-50 border border-violet-100 p-3">
                      <p class="text-[10px] text-violet-700 font-semibold uppercase">Commission Fee</p>
                      <p class="text-lg font-bold text-violet-900 mt-1">{{ formatRupiah(analysis.revenue?.commission_fee) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-3 sm:col-span-3 lg:col-span-1">
                      <p class="text-[10px] text-emerald-700 font-semibold uppercase">Net Sales</p>
                      <p class="text-lg font-bold text-emerald-900 mt-1">{{ formatRupiah(analysis.revenue?.net_sales) }}</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <div v-if="topFoodItems.length" class="rounded-xl border border-orange-100 bg-orange-50/40 p-4">
                      <h3 class="text-sm font-semibold text-orange-900 mb-2">Top 10 Food (Qty)</h3>
                      <apexchart type="bar" height="300" :options="topFoodBarOptions" :series="topFoodBarSeries" />
                    </div>
                    <div v-if="topBeverageItems.length" class="rounded-xl border border-sky-100 bg-sky-50/40 p-4">
                      <h3 class="text-sm font-semibold text-sky-900 mb-2">Top 10 Beverages (Qty)</h3>
                      <apexchart type="bar" height="300" :options="topBeverageBarOptions" :series="topBeverageBarSeries" />
                    </div>
                  </div>

                  <div v-if="analysis.revenue?.daily?.length" class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-800 mb-3">Trend Harian</h3>
                    <apexchart type="area" height="280" :options="revenueDailyOptions" :series="revenueDailySeries" />
                  </div>
                  <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                          <th class="px-4 py-3 text-left">Tanggal</th>
                          <th class="px-4 py-3 text-right">Revenue</th>
                          <th class="px-4 py-3 text-right">Discount</th>
                          <th class="px-4 py-3 text-right">Manual</th>
                          <th class="px-4 py-3 text-right">Service</th>
                          <th class="px-4 py-3 text-right">Comm Fee</th>
                          <th class="px-4 py-3 text-right">Net Sales</th>
                          <th class="px-4 py-3 text-right">Cover</th>
                          <th class="px-4 py-3 text-right">Lunch</th>
                          <th class="px-4 py-3 text-right">Dinner</th>
                          <th class="px-4 py-3 text-right">Order</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in analysis.revenue?.daily || []" :key="row.date" class="hover:bg-slate-50">
                          <td class="px-4 py-2.5 whitespace-nowrap">{{ row.date }}</td>
                          <td class="px-4 py-2.5 text-right font-medium">{{ formatRupiah(row.revenue) }}</td>
                          <td class="px-4 py-2.5 text-right text-red-600">{{ formatRupiah(row.discount) }}</td>
                          <td class="px-4 py-2.5 text-right text-rose-600">{{ formatRupiah(row.manual_discount) }}</td>
                          <td class="px-4 py-2.5 text-right text-yellow-700">{{ formatRupiah(row.service_charge) }}</td>
                          <td class="px-4 py-2.5 text-right text-violet-700">{{ formatRupiah(row.commission_fee) }}</td>
                          <td class="px-4 py-2.5 text-right font-semibold text-emerald-700">{{ formatRupiah(row.net_sales) }}</td>
                          <td class="px-4 py-2.5 text-right">{{ row.cover }}</td>
                          <td class="px-4 py-2.5 text-right text-slate-600">{{ formatRupiah(row.lunch) }}</td>
                          <td class="px-4 py-2.5 text-right text-slate-600">{{ formatRupiah(row.dinner) }}</td>
                          <td class="px-4 py-2.5 text-right">{{ row.orders }}</td>
                        </tr>
                      </tbody>
                      <tfoot v-if="analysis.revenue?.daily?.length" class="bg-slate-50 font-semibold text-sm">
                        <tr>
                          <td class="px-4 py-3">Total</td>
                          <td class="px-4 py-3 text-right">{{ formatRupiah(analysis.revenue?.total) }}</td>
                          <td class="px-4 py-3 text-right text-red-600">{{ formatRupiah(analysis.revenue?.discount) }}</td>
                          <td class="px-4 py-3 text-right text-rose-600">{{ formatRupiah(analysis.revenue?.manual_discount) }}</td>
                          <td class="px-4 py-3 text-right text-yellow-700">{{ formatRupiah(analysis.revenue?.service_charge) }}</td>
                          <td class="px-4 py-3 text-right text-violet-700">{{ formatRupiah(analysis.revenue?.commission_fee) }}</td>
                          <td class="px-4 py-3 text-right text-emerald-700">{{ formatRupiah(analysis.revenue?.net_sales) }}</td>
                          <td class="px-4 py-3 text-right">{{ analysis.revenue?.cover ?? 0 }}</td>
                          <td class="px-4 py-3 text-right">{{ formatRupiah(analysis.revenue?.lunch) }}</td>
                          <td class="px-4 py-3 text-right">{{ formatRupiah(analysis.revenue?.dinner) }}</td>
                          <td class="px-4 py-3 text-right">{{ analysis.revenue?.order_count ?? 0 }}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </template>

                <!-- Guest GSI -->
                <template v-else-if="activeModal === 'guest_gsi'">
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-xl border p-4" :class="gsiTone(analysis.guest_comment_gsi?.overall_pct)">
                      <p class="text-xs font-semibold uppercase opacity-80">GSI Overall</p>
                      <p class="text-3xl font-bold mt-1">{{ pct(analysis.guest_comment_gsi?.overall_pct) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Total Form</p>
                      <p class="text-3xl font-bold text-slate-900 mt-1">{{ analysis.guest_comment_gsi?.total_forms ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Total Respons</p>
                      <p class="text-3xl font-bold text-slate-900 mt-1">{{ analysis.guest_comment_gsi?.total_responses ?? 0 }}</p>
                    </div>
                  </div>
                  <div v-if="analysis.guest_comment_gsi?.subjects?.length" class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                    <apexchart type="bar" height="320" :options="guestSubjectOptions" :series="guestSubjectSeries" />
                  </div>
                  <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                          <th class="px-4 py-3 text-left">Subject</th>
                          <th class="px-4 py-3 text-right">Excellent</th>
                          <th class="px-4 py-3 text-right">Good</th>
                          <th class="px-4 py-3 text-right">Average</th>
                          <th class="px-4 py-3 text-right">Poor</th>
                          <th class="px-4 py-3 text-right">GSI %</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in analysis.guest_comment_gsi?.subjects || []" :key="row.subject">
                          <td class="px-4 py-2.5 font-medium">{{ row.subject }}</td>
                          <td class="px-4 py-2.5 text-right">{{ row.excellent }}</td>
                          <td class="px-4 py-2.5 text-right">{{ row.good }}</td>
                          <td class="px-4 py-2.5 text-right">{{ row.average }}</td>
                          <td class="px-4 py-2.5 text-right">{{ row.poor }}</td>
                          <td class="px-4 py-2.5 text-right font-semibold">{{ pct(row.gsi_pct) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </template>

                <!-- Google GSI -->
                <template v-else-if="activeModal === 'google_gsi'">
                  <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="rounded-xl border p-4 sm:col-span-1" :class="gsiTone(analysis.google_review_gsi?.overall_pct)">
                      <p class="text-xs font-semibold uppercase opacity-80">GSI Overall</p>
                      <p class="text-3xl font-bold mt-1">{{ pct(analysis.google_review_gsi?.overall_pct) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Total Review</p>
                      <p class="text-2xl font-bold text-slate-900 mt-1">{{ analysis.google_review_gsi?.total_reviews ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4">
                      <p class="text-xs text-emerald-700 font-semibold uppercase">Positif</p>
                      <p class="text-2xl font-bold text-emerald-900 mt-1">{{ analysis.google_review_gsi?.positive_reviews ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm">
                      <p class="text-xs text-slate-500 font-semibold uppercase mb-2">Sumber</p>
                      <p>Manual: <strong>{{ analysis.google_review_gsi?.sources?.manual ?? 0 }}</strong></p>
                      <p>AI/Scraper: <strong>{{ analysis.google_review_gsi?.sources?.ai_classified ?? 0 }}</strong></p>
                    </div>
                  </div>
                  <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                          <th class="px-4 py-3 text-left">Tanggal</th>
                          <th class="px-4 py-3 text-left">Author</th>
                          <th class="px-4 py-3 text-center">Rating</th>
                          <th class="px-4 py-3 text-left">Sumber</th>
                          <th class="px-4 py-3 text-center">Status</th>
                          <th class="px-4 py-3 text-left">Review</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, idx) in analysis.google_review_gsi?.items || []" :key="idx">
                          <td class="px-4 py-2.5 whitespace-nowrap text-slate-600">{{ row.review_date || '-' }}</td>
                          <td class="px-4 py-2.5 font-medium">{{ row.author }}</td>
                          <td class="px-4 py-2.5 text-center">{{ row.rating ?? '-' }}</td>
                          <td class="px-4 py-2.5">{{ row.source }}</td>
                          <td class="px-4 py-2.5 text-center">
                            <span
                              class="text-xs px-2 py-0.5 rounded-full font-semibold"
                              :class="row.is_positive ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                            >
                              {{ row.is_positive ? 'Positif' : 'Negatif' }}
                            </span>
                          </td>
                          <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate">{{ row.text || '-' }}</td>
                        </tr>
                        <tr v-if="!(analysis.google_review_gsi?.items?.length)">
                          <td colspan="6" class="px-4 py-8 text-center text-slate-400">Tidak ada review pada periode ini.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </template>

                <!-- Regional -->
                <template v-else-if="activeModal === 'regional'">
                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-xl bg-violet-50 border border-violet-100 p-4">
                      <p class="text-xs text-violet-700 font-semibold uppercase">Hari Kunjungan</p>
                      <p class="text-2xl font-bold text-violet-900 mt-1">{{ analysis.regional_visits?.visit_days ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Total Jam</p>
                      <p class="text-2xl font-bold text-slate-900 mt-1">{{ analysis.regional_visits?.total_hours ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Scan IN</p>
                      <p class="text-2xl font-bold text-slate-900 mt-1">{{ analysis.regional_visits?.scan_in_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Unique Visitor</p>
                      <p class="text-2xl font-bold text-slate-900 mt-1">{{ analysis.regional_visits?.unique_visitors ?? 0 }}</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <div v-if="regionalAreas.length" class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                      <h3 class="text-sm font-semibold text-slate-800 mb-2">Distribusi per Area</h3>
                      <apexchart type="pie" height="300" :options="areaPieOptions" :series="areaPieSeries" />
                    </div>
                    <div class="space-y-3">
                      <div
                        v-for="area in regionalAreas"
                        :key="area.area"
                        class="rounded-xl border border-slate-200 p-4 hover:border-violet-200 transition-colors"
                      >
                        <div class="flex items-center justify-between gap-2 mb-3">
                          <span class="inline-flex px-3 py-1 rounded-full text-sm font-bold border" :class="areaBadgeClass(area.area)">
                            {{ area.area }}
                          </span>
                          <span class="text-sm text-slate-600">{{ area.visit_days }} hari · {{ area.total_hours }} jam</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                          <span
                            v-for="member in area.members || []"
                            :key="member.id"
                            class="text-xs px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700"
                          >
                            {{ member.name }} · {{ member.visit_days }} hari
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-if="analysis.regional_visits?.hourly_frequency?.data?.some((v) => v > 0)" class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-800 mb-3">Frekuensi per Jam</h3>
                    <apexchart type="bar" height="240" :options="regionalHourlyOptions" :series="regionalHourlySeries" />
                  </div>

                  <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                          <th class="px-4 py-3 text-left">Tanggal</th>
                          <th class="px-4 py-3 text-left">Hari</th>
                          <th class="px-4 py-3 text-left">Regional</th>
                          <th class="px-4 py-3 text-left">Area</th>
                          <th class="px-4 py-3 text-center">Masuk</th>
                          <th class="px-4 py-3 text-center">Keluar</th>
                          <th class="px-4 py-3 text-right">Durasi</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <template v-for="day in analysis.regional_visits?.daily_visits || []" :key="day.tanggal">
                          <tr v-for="session in day.sessions || []" :key="`${day.tanggal}-${session.user_id}`" class="hover:bg-violet-50/30">
                            <td class="px-4 py-2.5">{{ day.tanggal_label }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ day.hari }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ session.user_name }}</td>
                            <td class="px-4 py-2.5">
                              <span
                                v-if="visitorArea(session.user_id)"
                                class="text-xs px-2 py-0.5 rounded-full border font-semibold"
                                :class="areaBadgeClass(visitorArea(session.user_id))"
                              >
                                {{ visitorArea(session.user_id) }}
                              </span>
                            </td>
                            <td class="px-4 py-2.5 text-center">{{ session.jam_masuk_display || '-' }}</td>
                            <td class="px-4 py-2.5 text-center">{{ session.jam_keluar_display || '-' }}</td>
                            <td class="px-4 py-2.5 text-right">{{ session.durasi_label || '-' }}</td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </template>

                <!-- Petty Cash -->
                <template v-else-if="activeModal === 'petty_cash'">
                  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-xl bg-rose-50 border border-rose-100 p-4 lg:col-span-2">
                      <p class="text-xs text-rose-700 font-semibold uppercase">Total Petty Cash</p>
                      <p class="text-2xl font-bold text-rose-900 mt-1">{{ formatRupiah(analysis.petty_cash?.total) }}</p>
                    </div>
                    <div class="rounded-xl bg-orange-50 border border-orange-100 p-4">
                      <p class="text-xs text-orange-700 font-semibold uppercase">Retail Food</p>
                      <p class="text-xl font-bold text-orange-900 mt-1">{{ formatRupiah(analysis.petty_cash?.retail_food_total) }}</p>
                      <p class="text-xs text-orange-600 mt-1">{{ analysis.petty_cash?.retail_food_count ?? 0 }} transaksi</p>
                    </div>
                    <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-4">
                      <p class="text-xs text-indigo-700 font-semibold uppercase">Retail Non Food</p>
                      <p class="text-xl font-bold text-indigo-900 mt-1">{{ formatRupiah(analysis.petty_cash?.retail_non_food_total) }}</p>
                      <p class="text-xs text-indigo-600 mt-1">{{ analysis.petty_cash?.retail_non_food_count ?? 0 }} transaksi</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <div v-if="pettyCashSources.length" class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                      <h3 class="text-sm font-semibold text-slate-800 mb-2">Komposisi Sumber</h3>
                      <apexchart type="pie" height="280" :options="pettyCashPieOptions" :series="pettyCashPieSeries" />
                    </div>
                    <div class="space-y-4">
                      <div v-if="analysis.petty_cash?.retail_food_categories?.length" class="rounded-xl border border-slate-200 p-4">
                        <h3 class="text-sm font-semibold text-slate-800 mb-3">Retail Food per Kategori Gudang</h3>
                        <div class="space-y-2">
                          <div
                            v-for="cat in analysis.petty_cash.retail_food_categories"
                            :key="cat.key"
                            class="flex items-center justify-between text-sm"
                          >
                            <span class="text-slate-700">{{ cat.label }}</span>
                            <span class="font-semibold">{{ formatRupiah(cat.amount) }}</span>
                          </div>
                        </div>
                      </div>
                      <div v-if="analysis.petty_cash?.retail_non_food_categories?.length" class="rounded-xl border border-slate-200 p-4">
                        <h3 class="text-sm font-semibold text-slate-800 mb-3">Retail Non Food per Kategori Budget</h3>
                        <div class="space-y-2">
                          <div
                            v-for="cat in analysis.petty_cash.retail_non_food_categories"
                            :key="cat.label"
                            class="flex items-center justify-between text-sm"
                          >
                            <span class="text-slate-700">{{ cat.label }}</span>
                            <span class="font-semibold">{{ formatRupiah(cat.amount) }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                          <th class="px-3 py-3 w-8"></th>
                          <th class="px-4 py-3 text-left">Tanggal</th>
                          <th class="px-4 py-3 text-left">No. Retail</th>
                          <th class="px-4 py-3 text-left">Sumber</th>
                          <th class="px-4 py-3 text-left">Kategori</th>
                          <th class="px-4 py-3 text-left">Creator</th>
                          <th class="px-4 py-3 text-left">Metode</th>
                          <th class="px-4 py-3 text-right">Nominal</th>
                          <th class="px-4 py-3 text-left">Catatan</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <template v-for="row in pettyCashAllTransactions" :key="pettyCashRowKey(row)">
                          <tr
                            class="hover:bg-rose-50/30 cursor-pointer"
                            @click="togglePettyCashRow(row)"
                          >
                            <td class="px-3 py-2.5 text-slate-400">
                              <i
                                class="fas text-xs transition-transform"
                                :class="isPettyCashExpanded(row) ? 'fa-chevron-down' : 'fa-chevron-right'"
                              ></i>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-slate-600">{{ row.transaction_date || '-' }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ row.retail_number }}</td>
                            <td class="px-4 py-2.5">{{ row.source_label }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ row.category_name || '-' }}</td>
                            <td class="px-4 py-2.5">{{ row.creator_name || '-' }}</td>
                            <td class="px-4 py-2.5">{{ row.payment_method_label }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ formatRupiah(row.total_amount) }}</td>
                            <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate">{{ row.notes || '-' }}</td>
                          </tr>
                          <tr v-if="isPettyCashExpanded(row)">
                            <td colspan="9" class="px-4 py-3 bg-slate-50">
                              <div v-if="row.items?.length" class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <table class="min-w-full text-xs">
                                  <thead class="bg-slate-100 text-slate-500 uppercase">
                                    <tr>
                                      <th class="px-3 py-2 text-left">Item</th>
                                      <th class="px-3 py-2 text-right">Qty</th>
                                      <th class="px-3 py-2 text-left">Satuan</th>
                                      <th class="px-3 py-2 text-right">Harga</th>
                                      <th class="px-3 py-2 text-right">Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(item, idx) in row.items" :key="idx">
                                      <td class="px-3 py-2 font-medium text-slate-800">{{ item.item_name }}</td>
                                      <td class="px-3 py-2 text-right">{{ item.qty }}</td>
                                      <td class="px-3 py-2">{{ item.unit || '-' }}</td>
                                      <td class="px-3 py-2 text-right">{{ formatRupiah(item.price) }}</td>
                                      <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal) }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                              <p v-else class="text-sm text-slate-400 text-center py-3">Tidak ada detail item.</p>
                            </td>
                          </tr>
                        </template>
                        <tr v-if="!pettyCashAllTransactions.length">
                          <td colspan="9" class="px-4 py-8 text-center text-slate-400">Tidak ada transaksi petty cash pada periode ini.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </template>

                <!-- PR Ops -->
                <template v-else-if="activeModal === 'pr_ops'">
                  <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="rounded-xl bg-cyan-50 border border-cyan-100 p-4 lg:col-span-2">
                      <p class="text-xs text-cyan-700 font-semibold uppercase">Total Pengeluaran PR Ops</p>
                      <p class="text-2xl font-bold text-cyan-900 mt-1">{{ formatRupiah(analysis.pr_ops_expenditure?.total) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Jumlah Pembayaran</p>
                      <p class="text-2xl font-bold text-slate-900 mt-1">{{ analysis.pr_ops_expenditure?.payment_count ?? 0 }}</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <div v-if="prOpsCategories.length" class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                      <h3 class="text-sm font-semibold text-slate-800 mb-2">Distribusi per Kategori</h3>
                      <apexchart type="pie" height="300" :options="prOpsPieOptions" :series="prOpsPieSeries" />
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                      <h3 class="text-sm font-semibold text-slate-800 mb-3">Ringkasan Kategori</h3>
                      <div class="space-y-2 max-h-[320px] overflow-y-auto">
                        <div
                          v-for="cat in analysis.pr_ops_expenditure?.categories || []"
                          :key="cat.category_id"
                          class="flex items-center justify-between gap-3 text-sm py-2 border-b border-slate-100 last:border-0"
                        >
                          <div>
                            <span v-if="cat.division" class="text-xs text-slate-400 block">{{ cat.division }}</span>
                            <span class="text-slate-800 font-medium">{{ cat.label }}</span>
                          </div>
                          <div class="text-right shrink-0">
                            <p class="font-semibold">{{ formatRupiah(cat.amount) }}</p>
                            <p class="text-xs text-slate-500">
                              {{
                                analysis.pr_ops_expenditure?.total > 0
                                  ? `${((cat.amount / analysis.pr_ops_expenditure.total) * 100).toFixed(1)}%`
                                  : '-'
                              }}
                            </p>
                          </div>
                        </div>
                        <p v-if="!(analysis.pr_ops_expenditure?.categories?.length)" class="text-sm text-slate-400 text-center py-6">
                          Tidak ada data kategori.
                        </p>
                      </div>
                    </div>
                  </div>

                  <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                          <th class="px-3 py-3 w-8"></th>
                          <th class="px-4 py-3 text-left">Tanggal</th>
                          <th class="px-4 py-3 text-left">No. Payment</th>
                          <th class="px-4 py-3 text-left">PR</th>
                          <th class="px-4 py-3 text-left">PO</th>
                          <th class="px-4 py-3 text-left">Kategori</th>
                          <th class="px-4 py-3 text-left">Approver</th>
                          <th class="px-4 py-3 text-left">Metode</th>
                          <th class="px-4 py-3 text-right">Nominal</th>
                          <th class="px-4 py-3 text-left">Judul</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <template v-for="row in analysis.pr_ops_expenditure?.transactions || []" :key="prOpsRowKey(row)">
                          <tr
                            class="hover:bg-cyan-50/30 cursor-pointer"
                            @click="togglePrOpsRow(row)"
                          >
                            <td class="px-3 py-2.5 text-slate-400">
                              <i
                                class="fas text-xs transition-transform"
                                :class="isPrOpsExpanded(row) ? 'fa-chevron-down' : 'fa-chevron-right'"
                              ></i>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-slate-600">{{ row.payment_date || '-' }}</td>
                            <td class="px-4 py-2.5">
                              <div class="font-medium">{{ row.payment_number }}</div>
                              <div v-if="row.payment_creator_name" class="text-xs text-slate-500 mt-0.5">
                                {{ row.payment_creator_name }}
                              </div>
                            </td>
                            <td class="px-4 py-2.5">
                              <div>{{ row.pr_number }}</div>
                              <div v-if="row.pr_creator_name" class="text-xs text-slate-500 mt-0.5">
                                {{ row.pr_creator_name }}
                              </div>
                            </td>
                            <td class="px-4 py-2.5">
                              <div>{{ row.po_number || '-' }}</div>
                              <div v-if="row.po_creator_name" class="text-xs text-slate-500 mt-0.5">
                                {{ row.po_creator_name }}
                              </div>
                            </td>
                            <td class="px-4 py-2.5">{{ row.category_name }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600 max-w-[180px]">{{ formatApprovers(row.approvers) }}</td>
                            <td class="px-4 py-2.5">{{ row.payment_method_label }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ formatRupiah(row.amount) }}</td>
                            <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate">{{ row.title || '-' }}</td>
                          </tr>
                          <tr v-if="isPrOpsExpanded(row)">
                            <td colspan="10" class="px-4 py-3 bg-slate-50">
                              <div v-if="row.approvers?.length" class="mb-3 flex flex-wrap gap-2">
                                <span
                                  v-for="(approver, idx) in row.approvers"
                                  :key="idx"
                                  class="inline-flex items-center rounded-full bg-cyan-100 text-cyan-800 border border-cyan-200 px-2.5 py-1 text-xs font-medium"
                                >
                                  {{ approver.role }}: {{ approver.name }}
                                </span>
                              </div>
                              <div v-if="row.items?.length" class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <table class="min-w-full text-xs">
                                  <thead class="bg-slate-100 text-slate-500 uppercase">
                                    <tr>
                                      <th class="px-3 py-2 text-left">Item</th>
                                      <th class="px-3 py-2 text-right">Qty</th>
                                      <th class="px-3 py-2 text-left">Satuan</th>
                                      <th class="px-3 py-2 text-right">Harga</th>
                                      <th class="px-3 py-2 text-right">Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(item, idx) in row.items" :key="idx">
                                      <td class="px-3 py-2 font-medium text-slate-800">{{ item.item_name }}</td>
                                      <td class="px-3 py-2 text-right">{{ item.qty }}</td>
                                      <td class="px-3 py-2">{{ item.unit || '-' }}</td>
                                      <td class="px-3 py-2 text-right">{{ formatRupiah(item.price) }}</td>
                                      <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal) }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                              <p v-else class="text-sm text-slate-400 text-center py-3">Tidak ada detail item.</p>
                            </td>
                          </tr>
                        </template>
                        <tr v-if="!(analysis.pr_ops_expenditure?.transactions?.length)">
                          <td colspan="10" class="px-4 py-8 text-center text-slate-400">Tidak ada pembayaran PR Ops pada periode ini.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </template>

                <!-- Category Cost Outlet -->
                <template v-else-if="activeModal === 'catcost'">
                  <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4 lg:col-span-2">
                      <p class="text-xs text-emerald-700 font-semibold uppercase">Total Category Cost Outlet</p>
                      <p class="text-2xl font-bold text-emerald-900 mt-1">{{ formatRupiah(analysis.category_cost_outlet?.total) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                      <p class="text-xs text-slate-500 font-semibold uppercase">Jumlah Dokumen</p>
                      <p class="text-2xl font-bold text-slate-900 mt-1">{{ analysis.category_cost_outlet?.document_count ?? 0 }}</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <div v-if="catCostModes.length" class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                      <h3 class="text-sm font-semibold text-slate-800 mb-2">Distribusi per Mode Catcost</h3>
                      <apexchart type="pie" height="300" :options="catCostPieOptions" :series="catCostPieSeries" />
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                      <h3 class="text-sm font-semibold text-slate-800 mb-3">Ringkasan Mode</h3>
                      <div class="space-y-2 max-h-[320px] overflow-y-auto">
                        <div
                          v-for="mode in analysis.category_cost_outlet?.modes || []"
                          :key="mode.key"
                          class="flex items-center justify-between gap-3 text-sm py-2 border-b border-slate-100 last:border-0"
                        >
                          <span class="text-slate-800 font-medium">{{ mode.label }}</span>
                          <div class="text-right shrink-0">
                            <p class="font-semibold">{{ formatRupiah(mode.amount) }}</p>
                            <p class="text-xs text-slate-500">
                              {{ mode.document_count ?? 0 }} dokumen ·
                              {{
                                analysis.category_cost_outlet?.total > 0
                                  ? `${((mode.amount / analysis.category_cost_outlet.total) * 100).toFixed(1)}%`
                                  : '-'
                              }}
                            </p>
                          </div>
                        </div>
                        <p v-if="!(analysis.category_cost_outlet?.modes?.length)" class="text-sm text-slate-400 text-center py-6">
                          Tidak ada data mode catcost.
                        </p>
                      </div>
                    </div>
                  </div>

                  <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                          <th class="px-3 py-3 w-8"></th>
                          <th class="px-4 py-3 text-left">Tanggal</th>
                          <th class="px-4 py-3 text-left">No. Dokumen</th>
                          <th class="px-4 py-3 text-left">Mode</th>
                          <th class="px-4 py-3 text-left">Gudang</th>
                          <th class="px-4 py-3 text-left">Creator</th>
                          <th class="px-4 py-3 text-left">Status</th>
                          <th class="px-4 py-3 text-right">Subtotal MAC</th>
                          <th class="px-4 py-3 text-left">Catatan</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <template v-for="row in analysis.category_cost_outlet?.transactions || []" :key="catCostRowKey(row)">
                          <tr
                            class="hover:bg-emerald-50/30 cursor-pointer"
                            @click="toggleCatCostRow(row)"
                          >
                            <td class="px-3 py-2.5 text-slate-400">
                              <i
                                class="fas text-xs transition-transform"
                                :class="isCatCostExpanded(row) ? 'fa-chevron-down' : 'fa-chevron-right'"
                              ></i>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-slate-600">{{ row.date || '-' }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ row.document_number }}</td>
                            <td class="px-4 py-2.5">{{ row.type_label }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ row.warehouse_outlet_name || '-' }}</td>
                            <td class="px-4 py-2.5">{{ row.creator_name || '-' }}</td>
                            <td class="px-4 py-2.5">
                              <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-700">
                                {{ row.status || '-' }}
                              </span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ formatRupiah(row.subtotal_mac) }}</td>
                            <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate">{{ row.notes || '-' }}</td>
                          </tr>
                          <tr v-if="isCatCostExpanded(row)">
                            <td colspan="9" class="px-4 py-3 bg-slate-50">
                              <div v-if="row.items?.length" class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                <table class="min-w-full text-xs">
                                  <thead class="bg-slate-100 text-slate-500 uppercase">
                                    <tr>
                                      <th class="px-3 py-2 text-left">Item</th>
                                      <th class="px-3 py-2 text-right">Qty</th>
                                      <th class="px-3 py-2 text-left">Satuan</th>
                                      <th class="px-3 py-2 text-right">MAC</th>
                                      <th class="px-3 py-2 text-right">Subtotal MAC</th>
                                      <th class="px-3 py-2 text-left">Catatan</th>
                                    </tr>
                                  </thead>
                                  <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(item, idx) in row.items" :key="idx">
                                      <td class="px-3 py-2 font-medium text-slate-800">{{ item.item_name }}</td>
                                      <td class="px-3 py-2 text-right">{{ item.qty }}</td>
                                      <td class="px-3 py-2">{{ item.unit || '-' }}</td>
                                      <td class="px-3 py-2 text-right">{{ item.mac != null ? formatRupiah(item.mac) : '-' }}</td>
                                      <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal_mac) }}</td>
                                      <td class="px-3 py-2 text-slate-600">{{ item.note || '-' }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                              <p v-else class="text-sm text-slate-400 text-center py-3">Tidak ada detail item.</p>
                            </td>
                          </tr>
                        </template>
                        <tr v-if="!(analysis.category_cost_outlet?.transactions?.length)">
                          <td colspan="9" class="px-4 py-8 text-center text-slate-400">Tidak ada dokumen category cost pada periode ini.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </template>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
.oa-modal-scroll {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}
</style>
