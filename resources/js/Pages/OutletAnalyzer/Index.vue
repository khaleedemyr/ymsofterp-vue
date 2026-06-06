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
                          <th class="px-4 py-3 text-right">Cover</th>
                          <th class="px-4 py-3 text-right">Lunch</th>
                          <th class="px-4 py-3 text-right">Dinner</th>
                          <th class="px-4 py-3 text-right">Order</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in analysis.revenue?.daily || []" :key="row.date" class="hover:bg-slate-50">
                          <td class="px-4 py-2.5">{{ row.date }}</td>
                          <td class="px-4 py-2.5 text-right font-medium">{{ formatRupiah(row.revenue) }}</td>
                          <td class="px-4 py-2.5 text-right">{{ row.cover }}</td>
                          <td class="px-4 py-2.5 text-right text-slate-600">{{ formatRupiah(row.lunch) }}</td>
                          <td class="px-4 py-2.5 text-right text-slate-600">{{ formatRupiah(row.dinner) }}</td>
                          <td class="px-4 py-2.5 text-right">{{ row.orders }}</td>
                        </tr>
                      </tbody>
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
                            {{ member.name }} · {{ member.visit_days }}x
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
                          <th class="px-4 py-3 text-left">Tanggal</th>
                          <th class="px-4 py-3 text-left">No. Retail</th>
                          <th class="px-4 py-3 text-left">Sumber</th>
                          <th class="px-4 py-3 text-left">Kategori</th>
                          <th class="px-4 py-3 text-left">Metode</th>
                          <th class="px-4 py-3 text-right">Nominal</th>
                          <th class="px-4 py-3 text-left">Catatan</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in pettyCashAllTransactions" :key="`${row.source}-${row.id}`" class="hover:bg-rose-50/30">
                          <td class="px-4 py-2.5 whitespace-nowrap text-slate-600">{{ row.transaction_date || '-' }}</td>
                          <td class="px-4 py-2.5 font-medium">{{ row.retail_number }}</td>
                          <td class="px-4 py-2.5">{{ row.source_label }}</td>
                          <td class="px-4 py-2.5 text-slate-600">{{ row.category_name || '-' }}</td>
                          <td class="px-4 py-2.5">{{ row.payment_method_label }}</td>
                          <td class="px-4 py-2.5 text-right font-semibold">{{ formatRupiah(row.total_amount) }}</td>
                          <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate">{{ row.notes || '-' }}</td>
                        </tr>
                        <tr v-if="!pettyCashAllTransactions.length">
                          <td colspan="7" class="px-4 py-8 text-center text-slate-400">Tidak ada transaksi petty cash pada periode ini.</td>
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
