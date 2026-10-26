<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-violet-50/40 to-indigo-50/30 py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-lg">
              <i class="fa-solid fa-chart-pie"></i>
            </span>
            <span>
              Content Plan Dashboard
              <span class="block text-sm font-medium text-violet-600 mt-0.5">Analisa & progress semua konten</span>
            </span>
          </h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('content-plans.index')"
            class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 text-sm font-semibold"
          >
            <i class="fa-solid fa-list mr-2"></i>List Plan
          </Link>
          <Link
            :href="route('content-plans.create')"
            class="px-4 py-2 rounded-xl bg-violet-600 text-white hover:bg-violet-700 text-sm font-semibold shadow-sm"
          >
            <i class="fa-solid fa-plus mr-2"></i>Buat Plan
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Dari</label>
            <input v-model="localFilters.date_from" type="date" class="w-full rounded-xl border-gray-200 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Sampai</label>
            <input v-model="localFilters.date_to" type="date" class="w-full rounded-xl border-gray-200 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Platform</label>
            <select v-model="localFilters.platform" class="w-full rounded-xl border-gray-200 text-sm">
              <option value="">Semua</option>
              <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Funnel</label>
            <select v-model="localFilters.funnel" class="w-full rounded-xl border-gray-200 text-sm">
              <option value="">Semua</option>
              <option v-for="f in funnels" :key="f" :value="f">{{ f }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Pillar</label>
            <select v-model="localFilters.pillar" class="w-full rounded-xl border-gray-200 text-sm">
              <option value="">Semua</option>
              <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <button type="submit" class="px-4 py-2.5 rounded-xl bg-violet-600 text-white hover:bg-violet-700 text-sm font-semibold">
            <i class="fa-solid fa-filter mr-1"></i> Terapkan
          </button>
        </form>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-9 gap-3 mb-6">
        <div v-for="card in kpiCards" :key="card.label" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
          <div class="text-xs font-medium text-gray-500 mb-1">{{ card.label }}</div>
          <div class="text-2xl font-bold" :class="card.color">{{ card.value }}</div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-sm font-bold text-gray-800">Completion Rate (Approved)</h2>
          <span class="text-lg font-bold text-emerald-600">{{ summary.completion_rate }}%</span>
        </div>
        <div class="h-3 rounded-full bg-gray-100 overflow-hidden">
          <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all" :style="{ width: `${summary.completion_rate}%` }"></div>
        </div>
        <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-2 text-center text-xs">
          <div class="rounded-lg bg-gray-50 p-2"><span class="text-gray-500">Draft</span><div class="font-bold text-gray-800">{{ summary.draft }}</div></div>
          <div class="rounded-lg bg-amber-50 p-2"><span class="text-amber-600">Submitted</span><div class="font-bold text-amber-800">{{ summary.submitted }}</div></div>
          <div class="rounded-lg bg-emerald-50 p-2"><span class="text-emerald-600">Approved</span><div class="font-bold text-emerald-800">{{ summary.approved }}</div></div>
          <div class="rounded-lg bg-red-50 p-2"><span class="text-red-600">Rejected</span><div class="font-bold text-red-800">{{ summary.rejected }}</div></div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h2 class="text-sm font-bold text-gray-800 mb-3"><i class="fa-solid fa-share-nodes text-violet-500 mr-2"></i>By Platform</h2>
          <apexchart v-if="platformChart.series[0].data.length" type="bar" height="260" :options="platformChart.options" :series="platformChart.series" />
          <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h2 class="text-sm font-bold text-gray-800 mb-3"><i class="fa-solid fa-circle-notch text-violet-500 mr-2"></i>By Status</h2>
          <apexchart v-if="jobStatusChart.series.some((n) => n > 0)" type="donut" height="260" :options="jobStatusChart.options" :series="jobStatusChart.series" />
          <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h2 class="text-sm font-bold text-gray-800 mb-3"><i class="fa-solid fa-filter text-violet-500 mr-2"></i>By Funnel</h2>
          <apexchart v-if="funnelChart.series.some((n) => n > 0)" type="donut" height="240" :options="funnelChart.options" :series="funnelChart.series" />
          <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h2 class="text-sm font-bold text-gray-800 mb-3"><i class="fa-solid fa-columns text-violet-500 mr-2"></i>By Pillar</h2>
          <apexchart v-if="pillarChart.series[0].data.length" type="bar" height="240" :options="pillarChart.options" :series="pillarChart.series" />
          <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h2 class="text-sm font-bold text-gray-800 mb-3"><i class="fa-solid fa-photo-film text-violet-500 mr-2"></i>By Format</h2>
          <apexchart v-if="formatChart.series[0].data.length" type="bar" height="240" :options="formatChart.options" :series="formatChart.series" />
          <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
        <h2 class="text-sm font-bold text-gray-800 mb-3"><i class="fa-solid fa-chart-line text-violet-500 mr-2"></i>Trend Date Post</h2>
        <apexchart v-if="trendChart.series[0].data.length" type="area" height="260" :options="trendChart.options" :series="trendChart.series" />
        <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800"><i class="fa-solid fa-clock text-red-500 mr-2"></i>Overdue (Date Post lewat)</h2>
            <span class="text-xs font-semibold text-red-600">{{ summary.overdue }} plan</span>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                  <th class="px-4 py-2 text-left">Nomor</th>
                  <th class="px-4 py-2 text-left">Platform</th>
                  <th class="px-4 py-2 text-left">Date Post</th>
                  <th class="px-4 py-2 text-left">Pillar</th>
                  <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!overduePlans.length">
                  <td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada overdue.</td>
                </tr>
                <tr v-for="row in overduePlans" :key="row.id" class="border-t border-gray-50 hover:bg-red-50/40">
                  <td class="px-4 py-2.5 font-medium">{{ row.number }}</td>
                  <td class="px-4 py-2.5">{{ row.platform }}</td>
                  <td class="px-4 py-2.5 text-red-600 font-medium">{{ formatDate(row.date_post) }}</td>
                  <td class="px-4 py-2.5">{{ row.pillar || '-' }}</td>
                  <td class="px-4 py-2.5 text-right">
                    <Link :href="route('content-plans.show', row.id)" class="text-violet-600 hover:underline text-xs">Detail</Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-bold text-gray-800"><i class="fa-solid fa-list-check text-violet-500 mr-2"></i>Recent Plans</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                  <th class="px-4 py-2 text-left">Nomor</th>
                  <th class="px-4 py-2 text-left">Platform</th>
                  <th class="px-4 py-2 text-left">Status</th>
                  <th class="px-4 py-2 text-left">Workflow</th>
                  <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!recentPlans.length">
                  <td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada plan.</td>
                </tr>
                <tr v-for="row in recentPlans" :key="row.id" class="border-t border-gray-50 hover:bg-violet-50/40">
                  <td class="px-4 py-2.5 font-medium">{{ row.number }}</td>
                  <td class="px-4 py-2.5">{{ row.platform }}</td>
                  <td class="px-4 py-2.5">{{ jobStatuses[row.job_status] || row.job_status }}</td>
                  <td class="px-4 py-2.5">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="workflowClass(row.workflow_status)">
                      {{ workflowStatuses[row.workflow_status] || row.workflow_status }}
                    </span>
                  </td>
                  <td class="px-4 py-2.5 text-right">
                    <Link :href="route('content-plans.show', row.id)" class="text-violet-600 hover:underline text-xs">Detail</Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const apexchart = VueApexCharts;

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  platforms: { type: Array, default: () => [] },
  funnels: { type: Array, default: () => [] },
  pillars: { type: Array, default: () => [] },
  jobStatuses: { type: Object, default: () => ({}) },
  workflowStatuses: { type: Object, default: () => ({}) },
  summary: { type: Object, default: () => ({}) },
  byPlatform: { type: Array, default: () => [] },
  byFunnel: { type: Array, default: () => [] },
  byPillar: { type: Array, default: () => [] },
  byFormat: { type: Array, default: () => [] },
  byJobStatus: { type: Array, default: () => [] },
  trend: { type: Array, default: () => [] },
  recentPlans: { type: Array, default: () => [] },
  overduePlans: { type: Array, default: () => [] },
});

const localFilters = reactive({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  platform: props.filters.platform || '',
  funnel: props.filters.funnel || '',
  pillar: props.filters.pillar || '',
});

const kpiCards = computed(() => [
  { label: 'Total', value: props.summary.total || 0, color: 'text-gray-900' },
  { label: 'Draft', value: props.summary.draft || 0, color: 'text-gray-600' },
  { label: 'Submitted', value: props.summary.submitted || 0, color: 'text-amber-600' },
  { label: 'Approved', value: props.summary.approved || 0, color: 'text-emerald-600' },
  { label: 'Rejected', value: props.summary.rejected || 0, color: 'text-red-600' },
  { label: 'Posted', value: props.summary.posted || 0, color: 'text-violet-600' },
  { label: 'Overdue', value: props.summary.overdue || 0, color: 'text-red-600' },
  { label: 'Due ≤7 hari', value: props.summary.due_soon || 0, color: 'text-orange-600' },
  { label: 'Completion', value: `${props.summary.completion_rate || 0}%`, color: 'text-emerald-600' },
]);

const barOpts = (categories, color = '#7c3aed') => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '70%' } },
  colors: [color],
  dataLabels: { enabled: true },
  xaxis: { categories },
  grid: { borderColor: '#f1f5f9' },
});

const platformChart = computed(() => ({
  series: [{ name: 'Plan', data: props.byPlatform.map((c) => c.total) }],
  options: barOpts(props.byPlatform.map((c) => c.label)),
}));

const pillarChart = computed(() => ({
  series: [{ name: 'Plan', data: props.byPillar.map((c) => c.total) }],
  options: barOpts(props.byPillar.map((c) => c.label), '#8b5cf6'),
}));

const formatChart = computed(() => ({
  series: [{ name: 'Plan', data: props.byFormat.map((c) => c.total) }],
  options: barOpts(props.byFormat.map((c) => c.label), '#6366f1'),
}));

const jobStatusChart = computed(() => ({
  series: props.byJobStatus.map((s) => s.total),
  options: {
    chart: { toolbar: { show: false }, fontFamily: 'inherit' },
    labels: props.byJobStatus.map((s) => s.label),
    colors: ['#3b82f6', '#a855f7', '#f59e0b', '#8b5cf6'],
    legend: { position: 'bottom' },
  },
}));

const funnelChart = computed(() => ({
  series: props.byFunnel.map((s) => s.total),
  options: {
    chart: { toolbar: { show: false }, fontFamily: 'inherit' },
    labels: props.byFunnel.map((s) => s.label),
    colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
    legend: { position: 'bottom' },
  },
}));

const trendChart = computed(() => ({
  series: [{ name: 'Date Post', data: props.trend.map((t) => t.total) }],
  options: {
    chart: { toolbar: { show: false }, fontFamily: 'inherit', zoom: { enabled: false } },
    stroke: { curve: 'smooth', width: 2 },
    colors: ['#7c3aed'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
    dataLabels: { enabled: false },
    xaxis: { categories: props.trend.map((t) => String(t.day).substring(5)), labels: { rotate: -45 } },
    grid: { borderColor: '#f1f5f9' },
  },
}));

const applyFilters = () => {
  router.get(route('content-plans.dashboard'), { ...localFilters }, { preserveState: true, replace: true });
};

const formatDate = (d) => (d ? String(d).substring(0, 10) : '-');

const workflowClass = (s) => {
  if (s === 'approved') return 'bg-green-100 text-green-800';
  if (s === 'submitted') return 'bg-amber-100 text-amber-800';
  if (s === 'rejected') return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-700';
};
</script>
