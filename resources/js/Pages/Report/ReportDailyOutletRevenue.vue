<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-chart-line"></i> Daily Outlet Revenue Report
      </h1>

      <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8 items-end">
        <div v-if="canSelectOutlet">
          <label class="block text-sm font-medium mb-1">Outlet</label>
          <select v-model="filters.outlet" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" required>
            <option value="">Pilih Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.qr_code">{{ outlet.name }}</option>
          </select>
        </div>
        <div v-else>
          <label class="block text-sm font-medium mb-1">Outlet</label>
          <div class="block w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-gray-800 font-medium">
            {{ lockedOutletName || '—' }}
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Bulan</label>
          <select v-model="filters.month" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" required>
            <option value="">Pilih Bulan</option>
            <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tahun</label>
          <select v-model="filters.year" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" required>
            <option value="">Pilih Tahun</option>
            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>
        <div class="flex items-end h-full gap-2 md:col-span-2">
          <button @click="fetchReport" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">
            Tampilkan
          </button>
          <button
            v-if="showReport"
            @click="exportExcel"
            class="flex-1 bg-emerald-600 text-white px-4 py-2 rounded-lg shadow hover:bg-emerald-700 transition inline-flex items-center justify-center gap-2"
          >
            <i class="fa-solid fa-file-excel"></i> Export Excel
          </button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-10">
        <span class="text-gray-500">Loading...</span>
      </div>

      <div v-else-if="showReport">
        <!-- Performance / Outlet Info -->
        <div class="mb-8 rounded-2xl overflow-hidden shadow-lg border border-slate-700">
          <div class="bg-slate-900 px-5 py-3 flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-white font-bold tracking-wide uppercase text-sm">Outlet Info — {{ getMonthName(filters.month) }} (Current)</h3>
            <span class="text-slate-400 text-xs">
              {{ reportMeta.outlet_name }}
              <span v-if="performance.last_month_label" class="ml-2 text-slate-500">
                · vs {{ performance.last_month_label }} (tgl {{ performance.compare_day }})
              </span>
            </span>
          </div>
          <div class="overflow-x-auto bg-slate-800">
            <table class="min-w-full text-sm text-white">
              <thead>
                <tr class="bg-slate-700/80 text-xs uppercase tracking-wider">
                  <th class="px-4 py-3 text-left w-12">No</th>
                  <th class="px-4 py-3 text-left">Outlet Name</th>
                  <th class="px-4 py-3 text-right">MTD Revenue</th>
                  <th class="px-4 py-3 text-right">
                    LM MTD
                    <span class="block normal-case font-normal text-[10px] text-slate-400">s/d tgl {{ performance.compare_day || '—' }}</span>
                  </th>
                  <th class="px-4 py-3 text-center">vs LM MTD</th>
                  <th class="px-4 py-3 text-right">
                    LM Full
                    <span class="block normal-case font-normal text-[10px] text-slate-400">bulan lalu full</span>
                  </th>
                  <th class="px-4 py-3 text-center">vs LM Full</th>
                  <th class="px-4 py-3 text-right">Budget</th>
                  <th class="px-4 py-3 text-center w-28">Perf%</th>
                  <th class="px-4 py-3 text-right">Var</th>
                  <th class="px-4 py-3 text-right">%</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-t border-slate-700">
                  <td class="px-4 py-4">1</td>
                  <td class="px-4 py-4 font-semibold">{{ reportMeta.outlet_name }}</td>
                  <td class="px-4 py-4 text-right text-sky-300 font-semibold">{{ formatCurrency(performance.mtd_revenue) }}</td>
                  <td class="px-4 py-4 text-right text-cyan-200/90">{{ formatCurrency(performance.last_month_mtd_to_date) }}</td>
                  <td class="px-4 py-4 text-center">
                    <span
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md font-bold text-white text-xs"
                      :class="growthBadgeClass(performance.vs_last_mtd_percent)"
                    >
                      <i v-if="performance.vs_last_mtd_percent != null" :class="performance.vs_last_mtd_percent >= 0 ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'"></i>
                      {{ formatGrowthPercent(performance.vs_last_mtd_percent) }}
                    </span>
                    <div class="text-[10px] mt-1" :class="growthTextClass(performance.vs_last_mtd_var)">
                      {{ formatVariance(performance.vs_last_mtd_var) }}
                    </div>
                  </td>
                  <td class="px-4 py-4 text-right text-slate-300">{{ formatCurrency(performance.last_month_full) }}</td>
                  <td class="px-4 py-4 text-center">
                    <span
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md font-bold text-white text-xs"
                      :class="growthBadgeClass(performance.vs_last_full_percent)"
                    >
                      <i v-if="performance.vs_last_full_percent != null" :class="performance.vs_last_full_percent >= 0 ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'"></i>
                      {{ formatGrowthPercent(performance.vs_last_full_percent) }}
                    </span>
                    <div class="text-[10px] mt-1" :class="growthTextClass(performance.vs_last_full_var)">
                      {{ formatVariance(performance.vs_last_full_var) }}
                    </div>
                  </td>
                  <td class="px-4 py-4 text-right text-slate-300 italic">
                    {{ performance.budget != null ? formatCurrency(performance.budget) : '—' }}
                  </td>
                  <td class="px-4 py-4 text-center">
                    <span
                      class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md font-bold text-white text-sm"
                      :class="perfBadgeClass"
                    >
                      <i v-if="performance.perf_percent != null" :class="performance.perf_percent >= 100 ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'"></i>
                      {{ performance.perf_percent != null ? performance.perf_percent + '%' : '—' }}
                    </span>
                  </td>
                  <td class="px-4 py-4 text-right font-medium" :class="varianceClass">{{ formatVariance(performance.variance) }}</td>
                  <td class="px-4 py-4 text-right font-medium" :class="varianceClass">
                    {{ performance.variance_percent != null ? performance.variance_percent + '%' : '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="performance.budget == null" class="text-xs text-amber-700 bg-amber-50 px-4 py-2 border-t border-amber-100">
            Budget belum di-set di menu <strong>Revenue Targets</strong> untuk bulan ini.
          </p>
        </div>

        <h2 class="font-semibold mb-2 text-lg text-gray-700">Daily Outlet Revenue Report - {{ getMonthName(filters.month) }} {{ filters.year }}</h2>
        <div class="overflow-x-auto mb-8">
          <table class="min-w-full rounded-2xl overflow-hidden shadow-lg border border-gray-200">
            <thead>
              <tr class="text-white font-bold text-sm">
                <th class="px-3 py-3 text-center border-r border-white/30 bg-[#2563eb]" rowspan="2">DATE</th>
                <th class="px-3 py-3 text-center border-r border-white/30 bg-[#2563eb]" rowspan="2">DAY</th>
                <th class="px-3 py-3 text-center border-r border-white/30 bg-emerald-600" colspan="4">LUNCH</th>
                <th class="px-3 py-3 text-center border-r border-white/30 bg-amber-600" colspan="4">DINNER</th>
                <th class="px-3 py-3 text-center bg-indigo-600" colspan="4">TOTAL FB REVENUE</th>
              </tr>
              <tr class="text-white font-bold text-xs">
                <th v-for="(h, idx) in subHeaders" :key="idx" class="px-3 py-2 text-center border-r border-white/20" :class="subHeaderClass(idx)">{{ h }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(dayData, date) in report.daily_data"
                :key="date"
                class="border-b border-gray-200 last:border-b-0 cursor-pointer transition-colors duration-150"
                @click="selectDailyRow(date)"
                @mouseenter="hoveredDate = date"
                @mouseleave="hoveredDate = null"
              >
                <td class="px-3 py-3 text-center font-semibold text-gray-800 border-r border-gray-200" :class="cellClass(dayData, date, 'meta')">{{ formatDate(date) }}</td>
                <td class="px-3 py-3 text-center font-semibold text-gray-800 border-r border-gray-200" :class="cellClass(dayData, date, 'meta')">
                  {{ dayData.day_name }}
                  <span v-if="dayData.is_holiday" class="block text-[10px] text-red-600 font-normal">{{ dayData.holiday_description }}</span>
                </td>

                <td class="px-3 py-3 text-center border-r border-gray-200" :class="cellClass(dayData, date, 'lunch')">{{ dayData.lunch.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-gray-200" :class="cellClass(dayData, date, 'lunch')">{{ formatNumber(dayData.lunch.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-gray-200" :class="cellClass(dayData, date, 'lunch')">{{ formatNumber(dayData.lunch.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-gray-200" :class="cellClass(dayData, date, 'lunch')">{{ formatNumber(dayData.lunch.disc || 0) }}</td>

                <td class="px-3 py-3 text-center border-r border-gray-200" :class="cellClass(dayData, date, 'dinner')">{{ dayData.dinner.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-gray-200" :class="cellClass(dayData, date, 'dinner')">{{ formatNumber(dayData.dinner.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-gray-200" :class="cellClass(dayData, date, 'dinner')">{{ formatNumber(dayData.dinner.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-gray-200" :class="cellClass(dayData, date, 'dinner')">{{ formatNumber(dayData.dinner.disc || 0) }}</td>

                <td class="px-3 py-3 text-center border-r border-gray-200 font-semibold" :class="cellClass(dayData, date, 'total')">{{ dayData.total.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-gray-200 font-semibold" :class="cellClass(dayData, date, 'total')">{{ formatNumber(dayData.total.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-gray-200 font-semibold" :class="cellClass(dayData, date, 'total')">{{ formatNumber(dayData.total.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right font-semibold" :class="cellClass(dayData, date, 'total')">{{ formatNumber(dayData.total.disc || 0) }}</td>
              </tr>

              <tr class="bg-[#1e3a8a] text-white font-bold">
                <td class="px-3 py-3 text-center border-r border-blue-400" colspan="2">MONTH TO DATE</td>
                <td class="px-3 py-3 text-center border-r border-emerald-400/50 bg-emerald-800/40">{{ report.summary.lunch.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-emerald-400/50 bg-emerald-800/40">{{ formatNumber(report.summary.lunch.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-emerald-400/50 bg-emerald-800/40">{{ formatNumber(report.summary.lunch.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-emerald-400/50 bg-emerald-800/40">{{ formatNumber(report.summary.lunch.disc || 0) }}</td>
                <td class="px-3 py-3 text-center border-r border-amber-400/50 bg-amber-800/40">{{ report.summary.dinner.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-amber-400/50 bg-amber-800/40">{{ formatNumber(report.summary.dinner.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-amber-400/50 bg-amber-800/40">{{ formatNumber(report.summary.dinner.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-amber-400/50 bg-amber-800/40">{{ formatNumber(report.summary.dinner.disc || 0) }}</td>
                <td class="px-3 py-3 text-center border-r border-indigo-400/50 bg-indigo-800/40">{{ report.summary.total.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-indigo-400/50 bg-indigo-800/40">{{ formatNumber(report.summary.total.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-indigo-400/50 bg-indigo-800/40">{{ formatNumber(report.summary.total.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right bg-indigo-800/40">{{ formatNumber(report.summary.total.disc || 0) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Line chart -->
        <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-6 shadow-lg">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
            <div>
              <h3 class="text-lg font-bold text-slate-800">Daily Revenue Trend</h3>
              <p class="text-sm text-slate-500">Lunch vs Dinner vs Total — line chart</p>
            </div>
          </div>
          <VueApexCharts type="line" height="420" :options="chartOptions" :series="chartSeries" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';

const filters = reactive({
  outlet: '',
  month: '',
  year: '',
});

const monthOptions = [
  { value: '1', label: 'Januari' },
  { value: '2', label: 'Februari' },
  { value: '3', label: 'Maret' },
  { value: '4', label: 'April' },
  { value: '5', label: 'Mei' },
  { value: '6', label: 'Juni' },
  { value: '7', label: 'Juli' },
  { value: '8', label: 'Agustus' },
  { value: '9', label: 'September' },
  { value: '10', label: 'Oktober' },
  { value: '11', label: 'November' },
  { value: '12', label: 'Desember' },
];

const subHeaders = ['COVER', 'REVENUE', 'A/C', 'DISC', 'COVER', 'REVENUE', 'A/C', 'DISC', 'COVER', 'REVENUE', 'A/C', 'DISC'];

const outlets = ref([]);
const lockedOutletName = ref('');
const report = reactive({
  summary: {
    lunch: { cover: 0, revenue: 0, avg_check: 0, disc: 0 },
    dinner: { cover: 0, revenue: 0, avg_check: 0, disc: 0 },
    total: { cover: 0, revenue: 0, avg_check: 0, disc: 0 },
  },
  daily_data: {},
});
const reportMeta = reactive({
  outlet_name: '',
});
const performance = reactive({
  mtd_revenue: 0,
  budget: null,
  perf_percent: null,
  variance: null,
  variance_percent: null,
  last_month_label: '',
  last_month_mtd_to_date: 0,
  last_month_full: 0,
  compare_day: null,
  vs_last_mtd_var: null,
  vs_last_mtd_percent: null,
  vs_last_full_var: null,
  vs_last_full_percent: null,
});
const loading = ref(false);
const showReport = ref(false);
const selectedDate = ref(null);
const hoveredDate = ref(null);
const user = usePage().props.auth?.user || {};

const canSelectOutlet = computed(() => Number(user.id_outlet) === 1);

const currentYear = new Date().getFullYear();
const availableYears = computed(() => {
  const years = [];
  for (let i = currentYear; i >= currentYear - 5; i--) {
    years.push(i);
  }
  return years;
});

const perfBadgeClass = computed(() => {
  if (performance.perf_percent == null) return 'bg-slate-500';
  if (performance.perf_percent >= 100) return 'bg-emerald-600';
  if (performance.perf_percent >= 80) return 'bg-amber-500';
  return 'bg-red-600';
});

const varianceClass = computed(() => {
  if (performance.variance == null) return 'text-slate-300';
  return performance.variance >= 0 ? 'text-emerald-400' : 'text-red-400';
});

const growthBadgeClass = (pct) => {
  if (pct == null) return 'bg-slate-500';
  if (pct >= 0) return 'bg-emerald-600';
  return 'bg-red-600';
};

const growthTextClass = (val) => {
  if (val == null) return 'text-slate-400';
  return val >= 0 ? 'text-emerald-400' : 'text-red-400';
};

const formatGrowthPercent = (pct) => {
  if (pct == null) return '—';
  const prefix = pct > 0 ? '+' : '';
  return `${prefix}${pct}%`;
};

const chartCategories = computed(() => {
  return Object.keys(report.daily_data).map((date) => {
    const d = new Date(date + 'T12:00:00');
    return d.getDate().toString();
  });
});

const chartSeries = computed(() => {
  const days = Object.values(report.daily_data);
  return [
    { name: 'Lunch', data: days.map((d) => Number(d.lunch?.revenue || 0)) },
    { name: 'Dinner', data: days.map((d) => Number(d.dinner?.revenue || 0)) },
    { name: 'Total FB', data: days.map((d) => Number(d.total?.revenue || 0)) },
  ];
});

const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    toolbar: { show: true, tools: { download: true } },
    animations: { enabled: true, easing: 'easeinout', speed: 700 },
    background: 'transparent',
    zoom: { enabled: true },
  },
  colors: ['#059669', '#D97706', '#4F46E5'],
  stroke: {
    curve: 'smooth',
    width: [3, 3, 3.5],
  },
  markers: {
    size: 4,
    strokeWidth: 2,
    strokeColors: '#fff',
    hover: { size: 6 },
  },
  dataLabels: { enabled: false },
  fill: { type: 'solid', opacity: 0 },
  xaxis: {
    categories: chartCategories.value,
    title: { text: 'Tanggal', style: { fontWeight: 600 } },
    labels: { rotate: -45, style: { fontSize: '11px' } },
  },
  yaxis: {
    title: { text: 'Revenue (Rp)', style: { fontWeight: 600 } },
    labels: {
      formatter: (val) => {
        if (val >= 1_000_000) return (val / 1_000_000).toFixed(1) + ' jt';
        if (val >= 1_000) return (val / 1_000).toFixed(0) + ' rb';
        return Math.round(val).toString();
      },
    },
  },
  legend: { position: 'top', horizontalAlign: 'right', fontWeight: 600 },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  tooltip: {
    shared: true,
    intersect: false,
    y: { formatter: (val) => formatCurrency(val) },
  },
}));

const fetchOutlets = async () => {
  const res = await axios.get('/api/outlets/report');
  outlets.value = res.data.outlets || [];
};

const fetchMyOutletQr = async () => {
  const res = await axios.get('/api/my-outlet-qr');
  if (res.data.qr_code) {
    filters.outlet = res.data.qr_code;
  }
  if (res.data.outlet_name) {
    lockedOutletName.value = res.data.outlet_name;
  }
};

const buildRequestParams = () => {
  const params = {
    month: filters.month,
    year: filters.year,
  };
  if (canSelectOutlet.value && filters.outlet) {
    params.outlet = filters.outlet;
  }
  return params;
};

const fetchReport = async () => {
  if (!filters.month || !filters.year) {
    alert('Pilih bulan dan tahun terlebih dahulu');
    return;
  }

  if (canSelectOutlet.value && !filters.outlet) {
    alert('Pilih outlet terlebih dahulu');
    return;
  }

  loading.value = true;
  try {
    const res = await axios.get('/api/report/daily-outlet-revenue', { params: buildRequestParams() });
    report.daily_data = res.data.daily_data || {};
    report.summary = res.data.summary || report.summary;
    reportMeta.outlet_name = res.data.outlet_name || lockedOutletName.value || '';
    Object.assign(performance, res.data.performance || {});
    selectedDate.value = null;
    hoveredDate.value = null;
    showReport.value = true;
  } catch (error) {
    console.error('Error fetching report:', error);
    const serverMsg = error?.response?.data?.error;
    alert(serverMsg || 'Terjadi kesalahan saat mengambil data report');
  } finally {
    loading.value = false;
  }
};

const exportExcel = () => {
  const params = new URLSearchParams(buildRequestParams());
  window.open(`/api/report/daily-outlet-revenue/export?${params.toString()}`, '_blank');
};

const formatDate = (dateStr) => {
  const date = new Date(dateStr + 'T12:00:00');
  const day = date.getDate();
  const month = date.toLocaleDateString('en-US', { month: 'short' });
  const year = date.getFullYear().toString().slice(-2);
  return `${day}-${month}-${year}`;
};

const formatNumber = (num) => {
  if (typeof num !== 'number') return '0';
  return num.toLocaleString('id-ID');
};

const formatCurrency = (num) => {
  const n = Number(num) || 0;
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
};

const formatVariance = (num) => {
  if (num == null) return '—';
  const prefix = num > 0 ? '+' : '';
  return prefix + formatNumber(Math.round(num));
};

const getMonthName = (month) => {
  const m = monthOptions.find((o) => o.value === String(month));
  return m?.label || '';
};

const isSpecialDay = (dayData) => {
  return dayData.is_weekend || dayData.is_holiday;
};

const selectDailyRow = (date) => {
  selectedDate.value = date;
};

const cellClass = (dayData, date, section) => {
  if (selectedDate.value === date) {
    return 'bg-sky-200 ring-1 ring-inset ring-sky-300';
  }
  if (hoveredDate.value === date) {
    return 'bg-sky-100';
  }
  if (isSpecialDay(dayData)) {
    return 'bg-orange-100';
  }
  if (section === 'meta') return 'bg-white';
  if (section === 'lunch') return 'bg-emerald-50/80';
  if (section === 'dinner') return 'bg-amber-50/80';
  return 'bg-indigo-50/80';
};

const subHeaderClass = (idx) => {
  if (idx < 4) return 'bg-emerald-700';
  if (idx < 8) return 'bg-amber-700';
  return 'bg-indigo-700';
};

onMounted(async () => {
  const now = new Date();
  filters.month = (now.getMonth() + 1).toString();
  filters.year = now.getFullYear().toString();

  if (canSelectOutlet.value) {
    await fetchOutlets();
  } else {
    await fetchMyOutletQr();
  }
});
</script>
