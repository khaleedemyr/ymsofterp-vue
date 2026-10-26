<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-rose-50/40 to-orange-50/30 py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 text-white shadow-lg">
              <i class="fa-solid fa-chart-pie"></i>
            </span>
            <span>
              Marcomm Tracker Dashboard
              <span class="block text-sm font-medium text-rose-600 mt-0.5">Analisa & progress semua task</span>
            </span>
          </h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('marcomm-trackers.index')"
            class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 text-sm font-semibold"
          >
            <i class="fa-solid fa-list mr-2"></i>List Tracker
          </Link>
          <Link
            :href="route('marcomm-trackers.create')"
            class="px-4 py-2 rounded-xl bg-rose-600 text-white hover:bg-rose-700 text-sm font-semibold shadow-sm"
          >
            <i class="fa-solid fa-plus mr-2"></i>Buat Tracker
          </Link>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Dari</label>
            <input v-model="localFilters.date_from" type="date" class="w-full rounded-xl border-gray-200 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Sampai</label>
            <input v-model="localFilters.date_to" type="date" class="w-full rounded-xl border-gray-200 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Category</label>
            <select v-model="localFilters.job_category" class="w-full rounded-xl border-gray-200 text-sm">
              <option value="">Semua</option>
              <option v-for="c in jobCategories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">PIC</label>
            <select v-model="localFilters.pic_user_id" class="w-full rounded-xl border-gray-200 text-sm">
              <option value="">Semua PIC</option>
              <option v-for="u in picUsers" :key="u.id" :value="u.id">{{ u.nama_lengkap }}</option>
            </select>
          </div>
          <button type="submit" class="px-4 py-2.5 rounded-xl bg-rose-600 text-white hover:bg-rose-700 text-sm font-semibold">
            <i class="fa-solid fa-filter mr-1"></i> Terapkan
          </button>
        </form>
      </div>

      <!-- KPI -->
      <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-3 mb-6">
        <div v-for="card in kpiCards" :key="card.label" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
          <div class="text-xs font-medium text-gray-500 mb-1">{{ card.label }}</div>
          <div class="text-2xl font-bold" :class="card.color">{{ card.value }}</div>
          <div v-if="card.suffix" class="text-xs text-gray-400 mt-0.5">{{ card.suffix }}</div>
        </div>
      </div>

      <!-- Progress completion -->
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
          <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-layer-group text-rose-500"></i> By Job Category
          </h2>
          <apexchart v-if="categoryChart.series[0].data.length" type="bar" height="280" :options="categoryChart.options" :series="categoryChart.series" />
          <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-circle-notch text-rose-500"></i> By Job Status
          </h2>
          <apexchart v-if="jobStatusChart.series.some((n) => n > 0)" type="donut" height="280" :options="jobStatusChart.options" :series="jobStatusChart.series" />
          <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-rose-500"></i> Trend Task Dibuat
          </h2>
          <apexchart v-if="trendChart.series[0].data.length" type="area" height="260" :options="trendChart.options" :series="trendChart.series" />
          <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data.</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-user-check text-rose-500"></i> Progress per PIC
          </h2>
          <div v-if="!byPic.length" class="text-sm text-gray-400 py-8 text-center">Belum ada data.</div>
          <div v-else class="space-y-3 max-h-[280px] overflow-y-auto pr-1">
            <div v-for="pic in byPic" :key="pic.pic_user_id" class="border border-gray-100 rounded-xl p-3">
              <div class="flex justify-between items-start gap-2 mb-1.5">
                <div class="text-sm font-semibold text-gray-800 truncate">{{ pic.pic_name }}</div>
                <div class="text-xs font-bold text-emerald-600 shrink-0">{{ pic.progress_pct }}%</div>
              </div>
              <div class="h-2 rounded-full bg-gray-100 overflow-hidden mb-1.5">
                <div class="h-full bg-emerald-500 rounded-full" :style="{ width: `${pic.progress_pct}%` }"></div>
              </div>
              <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-gray-500">
                <span>Total {{ pic.total }}</span>
                <span class="text-emerald-600">Approved {{ pic.approved }}</span>
                <span class="text-amber-600">Submitted {{ pic.submitted }}</span>
                <span class="text-red-600">Overdue {{ pic.overdue }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800"><i class="fa-solid fa-clock text-red-500 mr-2"></i>Overdue Tasks</h2>
            <span class="text-xs font-semibold text-red-600">{{ summary.overdue }} task</span>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                  <th class="px-4 py-2 text-left">Nomor</th>
                  <th class="px-4 py-2 text-left">Category</th>
                  <th class="px-4 py-2 text-left">PIC</th>
                  <th class="px-4 py-2 text-left">Due</th>
                  <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!overdueTasks.length">
                  <td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada overdue.</td>
                </tr>
                <tr v-for="row in overdueTasks" :key="row.id" class="border-t border-gray-50 hover:bg-red-50/40">
                  <td class="px-4 py-2.5 font-medium">{{ row.number }}</td>
                  <td class="px-4 py-2.5">{{ row.job_category }}</td>
                  <td class="px-4 py-2.5">{{ row.pic?.nama_lengkap || '-' }}</td>
                  <td class="px-4 py-2.5 text-red-600 font-medium">{{ formatDate(row.due_date) }}</td>
                  <td class="px-4 py-2.5 text-right">
                    <Link :href="route('marcomm-trackers.show', row.id)" class="text-rose-600 hover:underline text-xs">Detail</Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-bold text-gray-800"><i class="fa-solid fa-list-check text-rose-500 mr-2"></i>Recent Tasks</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                  <th class="px-4 py-2 text-left">Nomor</th>
                  <th class="px-4 py-2 text-left">Category</th>
                  <th class="px-4 py-2 text-left">Status</th>
                  <th class="px-4 py-2 text-left">Workflow</th>
                  <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!recentTasks.length">
                  <td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada task.</td>
                </tr>
                <tr v-for="row in recentTasks" :key="row.id" class="border-t border-gray-50 hover:bg-rose-50/40">
                  <td class="px-4 py-2.5 font-medium">{{ row.number }}</td>
                  <td class="px-4 py-2.5">{{ row.job_category }}</td>
                  <td class="px-4 py-2.5">{{ jobStatuses[row.job_status] || row.job_status }}</td>
                  <td class="px-4 py-2.5">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="workflowClass(row.workflow_status)">
                      {{ workflowStatuses[row.workflow_status] || row.workflow_status }}
                    </span>
                  </td>
                  <td class="px-4 py-2.5 text-right">
                    <Link :href="route('marcomm-trackers.show', row.id)" class="text-rose-600 hover:underline text-xs">Detail</Link>
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
  jobCategories: { type: Array, default: () => [] },
  jobStatuses: { type: Object, default: () => ({}) },
  workflowStatuses: { type: Object, default: () => ({}) },
  picUsers: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  byCategory: { type: Array, default: () => [] },
  byJobStatus: { type: Array, default: () => [] },
  byPic: { type: Array, default: () => [] },
  trend: { type: Array, default: () => [] },
  recentTasks: { type: Array, default: () => [] },
  overdueTasks: { type: Array, default: () => [] },
});

const localFilters = reactive({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  job_category: props.filters.job_category || '',
  pic_user_id: props.filters.pic_user_id || '',
});

const kpiCards = computed(() => [
  { label: 'Total Task', value: props.summary.total || 0, color: 'text-gray-900' },
  { label: 'Draft', value: props.summary.draft || 0, color: 'text-gray-600' },
  { label: 'Submitted', value: props.summary.submitted || 0, color: 'text-amber-600' },
  { label: 'Approved', value: props.summary.approved || 0, color: 'text-emerald-600' },
  { label: 'Rejected', value: props.summary.rejected || 0, color: 'text-red-600' },
  { label: 'Overdue', value: props.summary.overdue || 0, color: 'text-red-600' },
  { label: 'Due ≤7 hari', value: props.summary.due_soon || 0, color: 'text-orange-600' },
  { label: 'Completion', value: `${props.summary.completion_rate || 0}%`, color: 'text-emerald-600' },
]);

const categoryChart = computed(() => ({
  series: [{
    name: 'Task',
    data: props.byCategory.map((c) => c.total),
  }],
  options: {
    chart: { toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '70%' } },
    colors: ['#e11d48'],
    dataLabels: { enabled: true },
    xaxis: { categories: props.byCategory.map((c) => c.category) },
    grid: { borderColor: '#f1f5f9' },
  },
}));

const jobStatusChart = computed(() => ({
  series: props.byJobStatus.map((s) => s.total),
  options: {
    chart: { toolbar: { show: false }, fontFamily: 'inherit' },
    labels: props.byJobStatus.map((s) => s.label),
    colors: ['#3b82f6', '#f59e0b', '#8b5cf6'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true },
  },
}));

const trendChart = computed(() => ({
  series: [{
    name: 'Task dibuat',
    data: props.trend.map((t) => t.total),
  }],
  options: {
    chart: { toolbar: { show: false }, fontFamily: 'inherit', zoom: { enabled: false } },
    stroke: { curve: 'smooth', width: 2 },
    colors: ['#e11d48'],
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 },
    },
    dataLabels: { enabled: false },
    xaxis: {
      categories: props.trend.map((t) => String(t.day).substring(5)),
      labels: { rotate: -45 },
    },
    grid: { borderColor: '#f1f5f9' },
  },
}));

const applyFilters = () => {
  router.get(route('marcomm-trackers.dashboard'), { ...localFilters }, { preserveState: true, replace: true });
};

const formatDate = (d) => (d ? String(d).substring(0, 10) : '-');

const workflowClass = (s) => {
  if (s === 'approved') return 'bg-green-100 text-green-800';
  if (s === 'submitted') return 'bg-amber-100 text-amber-800';
  if (s === 'rejected') return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-700';
};
</script>
