<template>
  <AppLayout title="Ticket Dashboard">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-blue-50 p-4 md:p-8">
      <!-- Header -->
      <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-600 text-white shadow-lg">
              <i class="fa-solid fa-chart-pie"></i>
            </span>
            <span>
              Ticket Dashboard
              <span class="block text-base font-semibold text-indigo-600 mt-0.5">{{ dashboard.period?.label }} · {{ dashboard.division_label }}</span>
            </span>
          </h1>
          <p class="text-sm text-gray-600 mt-2 ml-15">
            Analitik bulanan progress ticket, status, dan estimasi biaya (PR).
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-semibold"
            @click="goBack"
          >
            <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 font-semibold"
            :disabled="loading"
            @click="applyFilters"
          >
            <i class="fa-solid fa-sync-alt mr-2" :class="{ 'fa-spin': loading }"></i> Refresh
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-5 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Bulan</label>
            <select v-model.number="localFilters.month" class="w-full rounded-xl border-gray-200 focus:ring-indigo-500 focus:border-indigo-500">
              <option v-for="m in 12" :key="m" :value="m">{{ monthLabel(m) }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun</label>
            <select v-model.number="localFilters.year" class="w-full rounded-xl border-gray-200 focus:ring-indigo-500 focus:border-indigo-500">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Divisi Concern</label>
            <select v-model="localFilters.division" class="w-full rounded-xl border-gray-200 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="all">Semua Divisi</option>
              <option v-for="d in filterOptions.divisions" :key="d.id" :value="String(d.id)">{{ d.nama_divisi }}</option>
            </select>
          </div>
          <div class="flex gap-2">
            <button type="button" class="flex-1 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 font-semibold" @click="shiftMonth(-1)">
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button type="button" class="flex-[2] px-4 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 font-semibold" @click="applyFilters">
              Terapkan
            </button>
            <button type="button" class="flex-1 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 font-semibold" @click="shiftMonth(1)">
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4 mb-6">
        <KpiCard title="Total Ticket" :value="dashboard.total_tickets" icon="fa-ticket" color="indigo" :delta="dashboard.comparison?.total_tickets" />
        <KpiCard title="Open" :value="dashboard.open" icon="fa-folder-open" color="blue" :suffix="`${dashboard.progress?.open_rate || 0}%`" />
        <KpiCard title="In Progress" :value="dashboard.in_progress" icon="fa-spinner" color="amber" :suffix="`${dashboard.progress?.in_progress_rate || 0}%`" />
        <KpiCard title="Closed" :value="dashboard.closed" icon="fa-check-circle" color="emerald" :delta="dashboard.comparison?.closed" :suffix="`${dashboard.progress?.closed_rate || 0}%`" />
        <KpiCard title="Overdue" :value="dashboard.overdue" icon="fa-clock" color="rose" :suffix="`${dashboard.progress?.overdue_rate || 0}%`" />
        <KpiCard title="Ditutup (bulan)" :value="dashboard.closed_in_month" icon="fa-calendar-check" color="violet" />
      </div>

      <!-- Progress + Expense summary -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <div class="xl:col-span-2 bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
          <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-bars-progress text-indigo-500"></i> Progress Status
          </h2>
          <div class="space-y-4">
            <ProgressBar label="Completion Rate (Closed)" :percent="dashboard.progress?.completion_rate || 0" color="bg-emerald-500" :delta="dashboard.comparison?.completion_rate" />
            <ProgressBar label="Open" :percent="dashboard.progress?.open_rate || 0" color="bg-blue-500" />
            <ProgressBar label="In Progress" :percent="dashboard.progress?.in_progress_rate || 0" color="bg-amber-500" />
            <ProgressBar label="Overdue (aktif)" :percent="dashboard.progress?.overdue_rate || 0" color="bg-rose-500" />
            <ProgressBar label="Ticket dengan PR" :percent="dashboard.progress?.pr_coverage_rate || 0" color="bg-indigo-500" />
          </div>
          <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
            <div class="rounded-xl bg-slate-50 p-3">
              <div class="text-xs text-gray-500">Avg Close</div>
              <div class="text-lg font-bold text-gray-800">{{ dashboard.avg_close_days }} hari</div>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
              <div class="text-xs text-gray-500">Internal</div>
              <div class="text-lg font-bold text-gray-800">{{ dashboard.internal_executor }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
              <div class="text-xs text-gray-500">External Vendor</div>
              <div class="text-lg font-bold text-orange-700">{{ dashboard.external_vendor_executor }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
              <div class="text-xs text-gray-500">Belum diisi</div>
              <div class="text-lg font-bold text-gray-500">{{ dashboard.unset_executor }}</div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
          <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-money-bill-wave text-emerald-500"></i> Expenses (PR)
          </h2>
          <div class="space-y-4">
            <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4">
              <div class="text-xs font-semibold text-emerald-700 uppercase">Est. Expense</div>
              <div class="text-2xl font-extrabold text-emerald-800">{{ formatCurrency(dashboard.expenses?.est_expense) }}</div>
              <DeltaBadge v-if="dashboard.comparison?.est_expense" :delta="dashboard.comparison.est_expense" class="mt-1" />
            </div>
            <div class="rounded-xl bg-blue-50 border border-blue-100 p-4">
              <div class="text-xs font-semibold text-blue-700 uppercase">Sudah Dibayar</div>
              <div class="text-xl font-bold text-blue-800">{{ formatCurrency(dashboard.expenses?.paid_expense) }}</div>
              <div class="text-xs text-blue-600 mt-1">{{ dashboard.progress?.paid_expense_rate || 0 }}% dari est. expense</div>
            </div>
            <div class="rounded-xl bg-amber-50 border border-amber-100 p-4">
              <div class="text-xs font-semibold text-amber-700 uppercase">Pending</div>
              <div class="text-xl font-bold text-amber-800">{{ formatCurrency(dashboard.expenses?.pending_expense) }}</div>
            </div>
            <ProgressBar label="Payment Progress" :percent="dashboard.progress?.paid_expense_rate || 0" color="bg-emerald-500" />
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
              <div class="rounded-lg bg-gray-50 p-2">
                <div class="font-bold text-gray-800">{{ dashboard.no_pr }}</div>
                <div class="text-gray-500">Tanpa PR</div>
              </div>
              <div class="rounded-lg bg-gray-50 p-2">
                <div class="font-bold text-gray-800">{{ dashboard.with_pr }}</div>
                <div class="text-gray-500">Dengan PR</div>
              </div>
              <div class="rounded-lg bg-gray-50 p-2">
                <div class="font-bold text-gray-800">{{ dashboard.paid_pr_count }}</div>
                <div class="text-gray-500">PR Paid</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts row 1 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <ChartCard title="Volume Harian (Ticket Baru)" icon="fa-chart-line">
          <apexchart type="area" height="300" :options="dailyTrendOptions" :series="dailyTrendSeries" />
        </ChartCard>
        <ChartCard title="Distribusi Status" icon="fa-chart-pie">
          <apexchart type="donut" height="300" :options="statusDonutOptions" :series="statusDonutSeries" />
        </ChartCard>
      </div>

      <!-- Charts row 2 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <ChartCard title="Ticket per Kategori" icon="fa-tags">
          <apexchart type="bar" height="300" :options="barOptions(dashboard.charts?.by_category)" :series="barSeries(dashboard.charts?.by_category)" />
        </ChartCard>
        <ChartCard title="Ticket per Prioritas" icon="fa-flag">
          <apexchart type="bar" height="300" :options="barOptions(dashboard.charts?.by_priority)" :series="barSeries(dashboard.charts?.by_priority)" />
        </ChartCard>
      </div>

      <!-- Charts row 3 - division/outlet -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <ChartCard v-if="localFilters.division === 'all'" title="Ticket per Divisi" icon="fa-sitemap">
          <apexchart type="bar" height="320" :options="barOptions(dashboard.charts?.by_divisi, true)" :series="barSeries(dashboard.charts?.by_divisi)" />
        </ChartCard>
        <ChartCard v-else title="Ticket per Outlet" icon="fa-store">
          <apexchart type="bar" height="320" :options="barOptions(dashboard.charts?.by_outlet, true)" :series="barSeries(dashboard.charts?.by_outlet)" />
        </ChartCard>
        <ChartCard title="Dikerjakan Oleh" icon="fa-user-gear">
          <apexchart type="pie" height="320" :options="executorPieOptions" :series="executorPieSeries" />
        </ChartCard>
      </div>

      <!-- Expense charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <ChartCard title="Est. Expense per Outlet (Top)" icon="fa-coins">
          <apexchart type="bar" height="320" :options="expenseBarOptions(dashboard.charts?.expense_by_outlet)" :series="barSeries(dashboard.charts?.expense_by_outlet)" />
        </ChartCard>
        <ChartCard title="Est. Expense per Kategori" icon="fa-coins">
          <apexchart type="bar" height="320" :options="expenseBarOptions(dashboard.charts?.expense_by_category)" :series="barSeries(dashboard.charts?.expense_by_category)" />
        </ChartCard>
      </div>

      <!-- Payment funnel + top tickets -->
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
        <ChartCard title="Payment Funnel (PR)" icon="fa-filter">
          <apexchart type="bar" height="280" :options="funnelOptions" :series="funnelSeries" />
        </ChartCard>

        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
          <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-ranking-star text-amber-500"></i> Top Ticket by Expense
          </h2>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-xs uppercase text-gray-500 border-b">
                  <th class="py-2 pr-2">Ticket</th>
                  <th class="py-2 pr-2">Outlet</th>
                  <th class="py-2 pr-2 text-right">Est.</th>
                  <th class="py-2 text-right">Paid</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!dashboard.top_expense_tickets?.length">
                  <td colspan="4" class="py-6 text-center text-gray-400">Belum ada expense pada periode ini</td>
                </tr>
                <tr
                  v-for="row in dashboard.top_expense_tickets"
                  :key="row.id"
                  class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer"
                  @click="openTicket(row.id)"
                >
                  <td class="py-2 pr-2">
                    <div class="font-semibold text-indigo-700">{{ row.ticket_number }}</div>
                    <div class="text-xs text-gray-500 truncate max-w-[180px]">{{ row.title }}</div>
                  </td>
                  <td class="py-2 pr-2 text-gray-600">{{ row.outlet }}</td>
                  <td class="py-2 pr-2 text-right font-semibold">{{ formatCurrency(row.est_expense) }}</td>
                  <td class="py-2 text-right text-emerald-700">{{ formatCurrency(row.paid_expense) }}</td>
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
import { computed, reactive, ref, defineComponent, h } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

const apexchart = VueApexCharts;

const props = defineProps({
  dashboard: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({ divisions: [] }) },
});

const loading = ref(false);
const localFilters = reactive({
  year: props.filters.year || new Date().getFullYear(),
  month: props.filters.month || new Date().getMonth() + 1,
  division: props.filters.division || 'all',
});

const yearOptions = computed(() => {
  const y = new Date().getFullYear();
  return Array.from({ length: 6 }, (_, i) => y - i);
});

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function monthLabel(m) {
  return monthNames[m - 1] || m;
}

function formatCurrency(value) {
  const n = Number(value || 0);
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
}

function applyFilters() {
  loading.value = true;
  router.get('/tickets/dashboard', { ...localFilters }, {
    preserveScroll: true,
    onFinish: () => { loading.value = false; },
  });
}

function shiftMonth(delta) {
  let m = localFilters.month + delta;
  let y = localFilters.year;
  if (m < 1) { m = 12; y -= 1; }
  if (m > 12) { m = 1; y += 1; }
  localFilters.month = m;
  localFilters.year = y;
  applyFilters();
}

function goBack() {
  router.visit('/tickets');
}

function openTicket(id) {
  router.visit(`/tickets/${id}`);
}

const dailyTrendSeries = computed(() => [{
  name: 'Ticket Baru',
  data: (props.dashboard.charts?.daily_trend || []).map((d) => d.count),
}]);

const dailyTrendOptions = computed(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false } },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.05 } },
  colors: ['#4F46E5'],
  xaxis: { categories: (props.dashboard.charts?.daily_trend || []).map((d) => d.label) },
  dataLabels: { enabled: false },
  tooltip: { y: { formatter: (v) => `${v} ticket` } },
}));

const statusDonutSeries = computed(() =>
  (props.dashboard.charts?.status_distribution || []).map((d) => d.value)
);

const statusDonutOptions = computed(() => ({
  labels: (props.dashboard.charts?.status_distribution || []).map((d) => d.label),
  colors: ['#3B82F6', '#F59E0B', '#10B981', '#94A3B8'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true },
}));

const executorPieSeries = computed(() =>
  (props.dashboard.charts?.by_executor || []).map((d) => d.value)
);

const executorPieOptions = computed(() => ({
  labels: (props.dashboard.charts?.by_executor || []).map((d) => d.label),
  colors: ['#6366F1', '#F97316', '#CBD5E1'],
  legend: { position: 'bottom' },
}));

function barSeries(data) {
  return [{ name: 'Jumlah', data: (data || []).map((d) => d.value) }];
}

function barOptions(data, horizontal = false) {
  return {
    chart: { toolbar: { show: false } },
    plotOptions: { bar: { borderRadius: 6, horizontal } },
    colors: ['#4F46E5'],
    xaxis: { categories: (data || []).map((d) => d.label) },
    dataLabels: { enabled: false },
  };
}

function expenseBarOptions(data) {
  return {
    chart: { toolbar: { show: false } },
    plotOptions: { bar: { borderRadius: 6, horizontal: true } },
    colors: ['#059669'],
    xaxis: { categories: (data || []).map((d) => d.label) },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => formatCurrency(v) } },
  };
}

const funnelSeries = computed(() => [{
  name: 'Jumlah',
  data: (props.dashboard.charts?.payment_funnel || []).map((d) => d.value),
}]);

const funnelOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 6, distributed: true } },
  colors: ['#94A3B8', '#6366F1', '#F59E0B', '#10B981'],
  xaxis: { categories: (props.dashboard.charts?.payment_funnel || []).map((d) => d.label) },
  legend: { show: false },
  dataLabels: { enabled: true },
}));

// Sub-components (inline) — DeltaBadge harus didefinisikan dulu
const DeltaBadge = defineComponent({
  props: { delta: Object },
  setup(p) {
    return () => {
      if (!p.delta) return null;
      const up = p.delta.diff > 0;
      const down = p.delta.diff < 0;
      const cls = up ? 'text-emerald-600' : down ? 'text-rose-600' : 'text-gray-500';
      const icon = up ? 'fa-arrow-up' : down ? 'fa-arrow-down' : 'fa-minus';
      return h('div', { class: `inline-flex items-center gap-1 text-xs font-semibold ${cls}` }, [
        h('i', { class: `fa-solid ${icon}` }),
        h('span', {}, `${p.delta.pct > 0 ? '+' : ''}${p.delta.pct}% vs bulan lalu`),
      ]);
    };
  },
});

const ProgressBar = defineComponent({
  props: { label: String, percent: Number, color: String, delta: Object },
  setup(p) {
    return () => h('div', {}, [
      h('div', { class: 'flex justify-between text-sm mb-1' }, [
        h('span', { class: 'font-medium text-gray-700' }, p.label),
        h('span', { class: 'font-bold text-gray-900' }, `${p.percent || 0}%`),
      ]),
      h('div', { class: 'h-3 rounded-full bg-gray-100 overflow-hidden' }, [
        h('div', { class: `${p.color} h-full rounded-full transition-all`, style: { width: `${Math.min(100, p.percent || 0)}%` } }),
      ]),
      p.delta ? h(DeltaBadge, { delta: p.delta, class: 'mt-1 text-xs' }) : null,
    ]);
  },
});

const KpiCard = defineComponent({
  props: { title: String, value: [Number, String], icon: String, color: String, suffix: String, delta: Object },
  setup(p) {
    const colorMap = {
      indigo: 'from-indigo-500 to-indigo-600',
      blue: 'from-blue-500 to-blue-600',
      amber: 'from-amber-500 to-amber-600',
      emerald: 'from-emerald-500 to-emerald-600',
      rose: 'from-rose-500 to-rose-600',
      violet: 'from-violet-500 to-violet-600',
    };
    return () => h('div', { class: 'bg-white rounded-2xl shadow border border-gray-100 p-4' }, [
      h('div', { class: 'flex items-start justify-between gap-2' }, [
        h('div', [
          h('div', { class: 'text-xs font-semibold text-gray-500 uppercase' }, p.title),
          h('div', { class: 'text-2xl font-extrabold text-gray-900 mt-1' }, p.value ?? 0),
          p.suffix ? h('div', { class: 'text-xs text-gray-500 mt-0.5' }, p.suffix) : null,
        ]),
        h('div', { class: `h-10 w-10 rounded-xl bg-gradient-to-br ${colorMap[p.color] || colorMap.indigo} flex items-center justify-center text-white` }, [
          h('i', { class: `fa-solid ${p.icon}` }),
        ]),
      ]),
      p.delta ? h(DeltaBadge, { delta: p.delta, class: 'mt-2' }) : null,
    ]);
  },
});

const ChartCard = defineComponent({
  props: { title: String, icon: String },
  setup(p, { slots }) {
    return () => h('div', { class: 'bg-white rounded-2xl shadow-lg border border-gray-100 p-6' }, [
      h('h2', { class: 'text-lg font-bold text-gray-800 mb-4 flex items-center gap-2' }, [
        h('i', { class: `fa-solid ${p.icon} text-indigo-500` }),
        p.title,
      ]),
      slots.default?.(),
    ]);
  },
});
</script>
