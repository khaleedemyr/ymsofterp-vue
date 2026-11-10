<template>
  <AppLayout title="Member Visit Conversion">
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-bullseye text-blue-700"></i>
            Member Visit Conversion
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Konversi marketing ke kunjungan tamu/member — registered, visits, sales, repeat & penetration.
          </p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
          <div>
            <label class="mb-1 block text-xs text-gray-500">Dari tanggal</label>
            <input v-model="filterForm.date_from" type="date" class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-500">Sampai tanggal</label>
            <input v-model="filterForm.date_to" type="date" class="w-full rounded-lg border-gray-300" />
          </div>
          <div class="lg:col-span-2">
            <label class="mb-1 block text-xs text-gray-500">Outlet</label>
            <select v-model="filterForm.outlet" class="w-full rounded-lg border-gray-300">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-700 text-white hover:bg-blue-800">Cari</button>
            <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>

      <!-- Scorecards (mockup style) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="rounded-xl overflow-hidden border border-blue-200 shadow-sm">
          <div class="bg-[#1e4b8f] text-white text-center text-sm font-semibold py-2 px-3">Total Registered Members</div>
          <div class="bg-[#dbe7f7] text-center py-6">
            <div class="text-3xl font-bold text-slate-800">{{ formatNumber(summary.total_registered_members) }}</div>
            <div class="text-xs text-slate-500 mt-1">member baru di periode</div>
          </div>
        </div>
        <div class="rounded-xl overflow-hidden border border-blue-200 shadow-sm">
          <div class="bg-[#1e4b8f] text-white text-center text-sm font-semibold py-2 px-3">Total Member Visits</div>
          <div class="bg-[#dbe7f7] text-center py-6">
            <div class="text-3xl font-bold text-slate-800">{{ formatNumber(summary.total_member_visits) }}</div>
            <div class="text-xs text-slate-500 mt-1">bill paid ber-member</div>
          </div>
        </div>
        <div class="rounded-xl overflow-hidden border border-blue-200 shadow-sm">
          <div class="bg-[#1e4b8f] text-white text-center text-sm font-semibold py-2 px-3">Total Member Sales</div>
          <div class="bg-[#dbe7f7] text-center py-6">
            <div class="text-2xl font-bold text-slate-800">{{ summary.total_member_sales_formatted }}</div>
            <div class="text-xs text-slate-500 mt-1">total revenue dari member</div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl overflow-hidden border border-blue-200 shadow-sm">
          <div class="bg-[#1e4b8f] text-white text-center text-sm font-semibold py-2 px-3">Repeat Customers</div>
          <div class="bg-[#dbe7f7] text-center py-6">
            <div class="text-3xl font-bold text-slate-800">{{ formatNumber(summary.repeat_customers) }}</div>
            <div class="text-xs text-slate-500 mt-1">member visit ≥ 2× di periode</div>
          </div>
        </div>
        <div class="rounded-xl overflow-hidden border border-blue-200 shadow-sm">
          <div class="bg-[#1e4b8f] text-white text-center text-sm font-semibold py-2 px-3">Repeat Visit Ratio</div>
          <div class="bg-[#dbe7f7] text-center py-6">
            <div class="text-3xl font-bold text-slate-800">{{ summary.repeat_visit_ratio }}%</div>
            <div class="text-xs text-slate-500 mt-1">Repeat Customer ÷ Total Member Visit</div>
          </div>
        </div>
        <div class="rounded-xl overflow-hidden border border-blue-200 shadow-sm">
          <div class="bg-[#1e4b8f] text-white text-center text-sm font-semibold py-2 px-3">Member Visit Penetration</div>
          <div class="bg-[#dbe7f7] text-center py-6">
            <div class="text-3xl font-bold text-slate-800">{{ summary.member_visit_penetration }}%</div>
            <div class="text-xs text-slate-500 mt-1">Total Member Visit ÷ Check ({{ formatNumber(summary.total_checks) }})</div>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
          <h2 class="text-sm font-semibold text-[#1e4b8f] mb-3 text-center">Repeat Visit Ratio</h2>
          <VueApexCharts type="area" height="300" :options="repeatChartOptions" :series="repeatChartSeries" />
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
          <h2 class="text-sm font-semibold text-[#1e4b8f] mb-3 text-center">Member Visit Penetration</h2>
          <VueApexCharts type="area" height="300" :options="penetrationChartOptions" :series="penetrationChartSeries" />
        </div>
      </div>

      <!-- Outlet breakdown -->
      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <div class="px-4 py-3 border-b">
          <h2 class="text-sm font-semibold text-gray-800">Breakdown per Outlet</h2>
        </div>
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 border-b">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-gray-700">Outlet</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700">Member Visits</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700">Checks</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700">Repeat Cust.</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700">Repeat Ratio</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700">Penetration</th>
              <th class="px-3 py-2 text-right font-semibold text-gray-700">Member Sales</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!byOutlet.length">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">Tidak ada data untuk filter ini.</td>
            </tr>
            <tr v-for="row in byOutlet" :key="row.kode_outlet" class="border-b border-gray-100 hover:bg-slate-50">
              <td class="px-3 py-2">
                <div class="font-medium text-gray-900">{{ row.outlet_name }}</div>
                <div class="text-xs text-gray-500 font-mono">{{ row.kode_outlet }}</div>
              </td>
              <td class="px-3 py-2 text-right">{{ formatNumber(row.member_visits) }}</td>
              <td class="px-3 py-2 text-right">{{ formatNumber(row.total_checks) }}</td>
              <td class="px-3 py-2 text-right">{{ formatNumber(row.repeat_customers) }}</td>
              <td class="px-3 py-2 text-right text-indigo-700 font-semibold">{{ row.repeat_visit_ratio }}%</td>
              <td class="px-3 py-2 text-right text-sky-700 font-semibold">{{ row.member_visit_penetration }}%</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">{{ row.member_sales_formatted }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  summary: {
    type: Object,
    default: () => ({
      total_registered_members: 0,
      total_member_visits: 0,
      total_member_sales: 0,
      total_member_sales_formatted: 'Rp 0',
      repeat_customers: 0,
      repeat_visit_ratio: 0,
      member_visit_penetration: 0,
      total_checks: 0,
      unique_visiting_members: 0,
    }),
  },
  daily: {
    type: Object,
    default: () => ({
      labels: [],
      repeat_visit_ratio: [],
      member_visit_penetration: [],
      member_visits: [],
      checks: [],
      repeat_customers: [],
    }),
  },
  byOutlet: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
});

const filterForm = reactive({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  outlet: props.filters.outlet || '',
});

const formatNumber = (n) => Number(n || 0).toLocaleString('id-ID');

const applyFilters = () => {
  router.get(route('crm.member-visit-conversion-report'), { ...filterForm }, { preserveState: true, replace: true });
};

const resetFilters = () => {
  filterForm.date_from = '';
  filterForm.date_to = '';
  filterForm.outlet = '';
  applyFilters();
};

const baseChartOptions = {
  chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  grid: { borderColor: '#e5e7eb' },
  xaxis: {
    categories: props.daily.labels || [],
    labels: { rotate: -45, style: { fontSize: '11px' } },
  },
  yaxis: {
    labels: {
      formatter: (v) => `${Number(v || 0).toFixed(0)}%`,
    },
    min: 0,
  },
  tooltip: {
    y: { formatter: (v) => `${Number(v || 0).toFixed(2)}%` },
  },
};

const repeatChartOptions = computed(() => ({
  ...baseChartOptions,
  colors: ['#4f46e5'],
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 0.4, opacityFrom: 0.45, opacityTo: 0.05 },
  },
  xaxis: { ...baseChartOptions.xaxis, categories: props.daily.labels || [] },
}));

const penetrationChartOptions = computed(() => ({
  ...baseChartOptions,
  colors: ['#0284c7'],
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 0.4, opacityFrom: 0.45, opacityTo: 0.05 },
  },
  xaxis: { ...baseChartOptions.xaxis, categories: props.daily.labels || [] },
}));

const repeatChartSeries = computed(() => [
  { name: 'Repeat Visit Ratio', data: props.daily.repeat_visit_ratio || [] },
]);

const penetrationChartSeries = computed(() => [
  { name: 'Member Visit Penetration', data: props.daily.member_visit_penetration || [] },
]);
</script>
