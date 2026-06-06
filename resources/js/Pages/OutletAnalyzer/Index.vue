<script setup>
import { computed, ref } from 'vue';
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

function applyFilters() {
  const q = { month: month.value };
  if (props.canChooseOutlet && idOutlet.value) {
    q.id_outlet = idOutlet.value;
  }
  router.get(route('outlet-analyzer.index'), q, { preserveState: true, replace: true });
}

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
  dataLabels: { enabled: true, formatter: (val) => `${Math.round(val)}%` },
};

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
  tooltip: {
    y: {
      formatter: (val) => formatRupiah(val),
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${Math.round(val)}%`,
  },
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
  tooltip: {
    y: {
      formatter: (val) => `${val} hari`,
    },
  },
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
  tooltip: {
    y: {
      formatter: (val) => `${val} hari`,
    },
  },
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

const gsiTone = (value) => {
  const v = Number(value ?? 0);
  if (v >= 85) return 'text-emerald-700 bg-emerald-50 border-emerald-200';
  if (v >= 75) return 'text-amber-700 bg-amber-50 border-amber-200';
  return 'text-red-700 bg-red-50 border-red-200';
};
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
        <!-- KPI Row -->
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Revenue</p>
            <p class="text-xl font-bold text-slate-900 mt-1">{{ formatRupiah(analysis.revenue?.total) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ analysis.revenue?.cover ?? 0 }} cover</p>
          </div>
          <div class="rounded-xl border p-4" :class="gsiTone(analysis.guest_comment_gsi?.overall_pct)">
            <p class="text-xs font-semibold uppercase opacity-80">GSI Guest Comment</p>
            <p class="text-xl font-bold mt-1">{{ pct(analysis.guest_comment_gsi?.overall_pct) }}</p>
            <p class="text-xs opacity-70 mt-1">{{ analysis.guest_comment_gsi?.total_forms ?? 0 }} form</p>
          </div>
          <div class="rounded-xl border p-4" :class="gsiTone(analysis.google_review_gsi?.overall_pct)">
            <p class="text-xs font-semibold uppercase opacity-80">GSI Google Review</p>
            <p class="text-xl font-bold mt-1">{{ pct(analysis.google_review_gsi?.overall_pct) }}</p>
            <p class="text-xs opacity-70 mt-1">{{ analysis.google_review_gsi?.total_reviews ?? 0 }} review</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Kunjungan Regional</p>
            <p class="text-xl font-bold text-slate-900 mt-1">{{ analysis.regional_visits?.visit_days ?? 0 }} hari</p>
            <p class="text-xs text-slate-500 mt-1">
              {{ analysis.regional_visits?.unique_visitors ?? 0 }} orang ·
              {{ analysis.regional_visits?.total_hours ?? 0 }} jam
            </p>
          </div>
        </section>

        <!-- Regional visitors list -->
        <section
          v-if="analysis.regional_visits?.visitors?.length"
          class="bg-white rounded-xl border border-slate-200 p-4"
        >
          <h2 class="text-sm font-semibold text-slate-800 mb-2">Tim Regional yang Berkunjung</h2>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="v in analysis.regional_visits.visitors"
              :key="v.id"
              class="text-xs px-3 py-1.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200"
            >
              {{ v.name }} · {{ v.visit_days }}x
            </span>
          </div>
        </section>

        <!-- FJ Inventory -->
        <section class="bg-white rounded-xl border border-slate-200 p-5">
          <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
              <h2 class="text-base font-semibold text-slate-900">Belanja Inventory (Rekap FJ)</h2>
              <p class="text-xs text-slate-500 mt-0.5">Good Receive per kategori gudang</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-slate-500">Food Cost</p>
              <p class="text-lg font-bold text-slate-900">{{ pct(analysis.fj_inventory?.food_cost_pct) }}</p>
            </div>
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

        <!-- Employee Attendance -->
        <section class="space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="text-base font-semibold text-slate-900">Kehadiran Karyawan</h2>
              <p class="text-xs text-slate-500 mt-0.5">
                {{ attendanceSummary.employee_count ?? 0 }} karyawan aktif di outlet
              </p>
            </div>
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
              <p class="text-xs text-slate-500 mt-0.5">Hadir · Alpha · OFF · Izin & Cuti</p>
              <div v-if="attendanceComposition.length" class="mt-3 bg-slate-50 rounded-xl p-2">
                <apexchart type="pie" height="300" :options="attendancePieOptions" :series="attendancePieSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada data komposisi.</p>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
              <h3 class="text-sm font-semibold text-slate-900">Izin & Cuti</h3>
              <p class="text-xs text-slate-500 mt-0.5">Breakdown per jenis (disetujui)</p>
              <div v-if="leaveBreakdown.length" class="mt-3 bg-slate-50 rounded-xl p-2">
                <apexchart type="pie" height="300" :options="leavePieOptions" :series="leavePieSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada izin/cuti pada periode ini.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
              <h3 class="text-sm font-semibold text-slate-900">Top Terlambat</h3>
              <p class="text-xs text-slate-500 mt-0.5">10 karyawan dengan telat terbanyak (menit)</p>
              <div v-if="analysis.employee_attendance?.top_late?.length" class="mt-3">
                <apexchart type="bar" height="320" :options="lateBarOptions" :series="lateBarSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada data keterlambatan.</p>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
              <h3 class="text-sm font-semibold text-slate-900">Top Lembur</h3>
              <p class="text-xs text-slate-500 mt-0.5">10 karyawan dengan lembur terbanyak (jam)</p>
              <div v-if="analysis.employee_attendance?.top_overtime?.length" class="mt-3">
                <apexchart type="bar" height="320" :options="overtimeBarOptions" :series="overtimeBarSeries" />
              </div>
              <p v-else class="text-sm text-slate-400 text-center py-10">Tidak ada data lembur.</p>
            </div>
          </div>
        </section>
      </template>
    </div>
  </AppLayout>
</template>
