<script setup>
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({})
  },
  memberGrowth: {
    type: Array,
    default: () => []
  },
  tierDistribution: {
    type: Array,
    default: () => []
  },
  genderDistribution: {
    type: Array,
    default: () => []
  },
  ageDistribution: {
    type: Array,
    default: () => []
  },
  purchasingPowerByAge: {
    type: Array,
    default: () => []
  },
  spendingTrend: {
    type: Array,
    default: () => []
  },
  pointActivityTrend: {
    type: Array,
    default: () => []
  },
  latestMembers: {
    type: Array,
    default: () => []
  },
  latestPointTransactions: {
    type: Array,
    default: () => []
  },
  latestActivities: {
    type: Array,
    default: () => []
  },
  topSpenders: {
    type: Array,
    default: () => []
  },
  mostActiveMembers: {
    type: Array,
    default: () => []
  },
  pointStats: {
    type: Object,
    default: () => ({})
  },
  engagementMetrics: {
    type: Object,
    default: () => ({})
  },
  memberSegmentation: {
    type: Object,
    default: () => ({ vip: 0, active: 0, new: 0, atRisk: 0, dormant: 0 })
  },
  memberLifetimeValue: {
    type: Object,
    default: () => ({})
  },
  churnAnalysis: {
    type: Object,
    default: () => ({})
  },
  conversionFunnel: {
    type: Object,
    default: () => ({})
  },
  regionalBreakdown: {
    type: Array,
    default: () => []
  },
  comparisonData: {
    type: Object,
    default: () => ({})
  },
  filters: {
    type: Object,
    default: () => ({ start_date: '', end_date: '' })
  },
  error: {
    type: String,
    default: ''
  },
});

const dateFilters = ref({
  start_date: props.filters?.start_date || '',
  end_date: props.filters?.end_date || '',
});

// ApexCharts configurations
const memberGrowthChartOptions = computed(() => ({
  chart: {
    type: 'area',
    height: 300,
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'Inter, sans-serif',
  },
  colors: ['#8b5cf6', '#3b82f6'],
  dataLabels: { enabled: false },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.1,
      stops: [0, 90, 100],
    },
  },
  xaxis: {
    categories: props.memberGrowth?.map(m => m.monthShort) || [],
    labels: { style: { fontSize: '11px', colors: '#6b7280' } },
  },
  yaxis: {
    labels: { style: { fontSize: '11px', colors: '#6b7280' } },
  },
  grid: {
    borderColor: '#f3f4f6',
    strokeDashArray: 3,
  },
  legend: {
    position: 'top',
    fontSize: '12px',
    fontWeight: 500,
    markers: { radius: 12 },
  },
  tooltip: {
    theme: 'dark',
    style: { fontSize: '12px' },
  },
}));

const memberGrowthChartSeries = computed(() => [
  {
    name: 'Member Baru',
    data: props.memberGrowth?.map(m => m.newMembers) || [],
  },
  {
    name: 'Total Member',
    data: props.memberGrowth?.map(m => m.totalMembers) || [],
  },
]);

const tierChartOptions = computed(() => ({
  chart: {
    type: 'donut',
    height: 300,
    fontFamily: 'Inter, sans-serif',
  },
  colors: props.tierDistribution?.map(t => t.color) || [],
  labels: props.tierDistribution?.map(t => t.tier) || [],
  legend: {
    position: 'bottom',
    fontSize: '12px',
    fontWeight: 500,
  },
  dataLabels: {
    enabled: true,
    formatter: function(val) {
      return val.toFixed(1) + '%';
    },
    style: {
      fontSize: '12px',
      fontWeight: 600,
    },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '65%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Total',
            fontSize: '14px',
            fontWeight: 600,
            formatter: function() {
              return formatNumber(props.stats?.totalMembers || 0);
            },
          },
        },
      },
    },
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: function(val) {
        return formatNumber(val);
      },
    },
  },
}));

const tierChartSeries = computed(() => props.tierDistribution?.map(t => t.count) || []);

const spendingTrendChartOptions = computed(() => ({
  chart: {
    type: 'area',
    height: 300,
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'Inter, sans-serif',
  },
  colors: ['#10b981'],
  dataLabels: { enabled: false },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.1,
      stops: [0, 90, 100],
    },
  },
  xaxis: {
    categories: props.spendingTrend?.map(s => s.monthShort) || [],
    labels: { style: { fontSize: '11px', colors: '#6b7280' } },
  },
  yaxis: {
    labels: {
      style: { fontSize: '11px', colors: '#6b7280' },
      formatter: function(val) {
        return 'Rp ' + formatNumber(val);
      },
    },
  },
  grid: {
    borderColor: '#f3f4f6',
    strokeDashArray: 3,
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: function(val) {
        return formatCurrency(val);
      },
    },
  },
}));

const spendingTrendChartSeries = computed(() => [
  {
    name: 'Total Spending',
    data: props.spendingTrend?.map(s => s.totalSpending) || [],
  },
]);

const pointActivityChartOptions = computed(() => ({
  chart: {
    type: 'area',
    height: 300,
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'Inter, sans-serif',
  },
  colors: ['#3b82f6', '#fb923c'],
  dataLabels: { enabled: false },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.1,
      stops: [0, 90, 100],
    },
  },
  xaxis: {
    categories: props.pointActivityTrend?.map(p => p.monthShort) || [],
    labels: { style: { fontSize: '11px', colors: '#6b7280' } },
  },
  yaxis: {
    labels: {
      style: { fontSize: '11px', colors: '#6b7280' },
      formatter: function(val) {
        return formatNumber(val);
      },
    },
  },
  grid: {
    borderColor: '#f3f4f6',
    strokeDashArray: 3,
  },
  legend: {
    position: 'top',
    fontSize: '12px',
    fontWeight: 500,
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: function(val) {
        return formatNumber(val);
      },
    },
  },
}));

const pointActivityChartSeries = computed(() => [
  {
    name: 'Point Diperoleh',
    data: props.pointActivityTrend?.map(p => p.pointEarned) || [],
  },
  {
    name: 'Point Ditukar',
    data: props.pointActivityTrend?.map(p => p.pointRedeemed) || [],
  },
]);

// Gender Distribution Chart (Priority 2)
const genderChartOptions = computed(() => ({
  chart: {
    type: 'donut',
    height: 300,
    fontFamily: 'Inter, sans-serif',
  },
  colors: props.genderDistribution?.map(g => g.color) || [],
  labels: props.genderDistribution?.map(g => g.gender) || [],
  legend: {
    position: 'bottom',
    fontSize: '12px',
    fontWeight: 500,
  },
  plotOptions: {
    pie: {
      donut: {
        size: '65%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Total',
            fontSize: '14px',
            fontWeight: 600,
            formatter: function() {
              return formatNumber(props.genderDistribution?.reduce((sum, g) => sum + g.count, 0) || 0);
            },
          },
        },
      },
    },
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: function(val, { seriesIndex }) {
        const item = props.genderDistribution?.[seriesIndex];
        return `${formatNumber(val)} (${item?.percentage || 0}%)`;
      },
    },
  },
}));

const genderChartSeries = computed(() => 
  props.genderDistribution?.map(g => g.count) || []
);

// Age Distribution Chart (Priority 2)
const ageChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    height: 300,
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif',
  },
  colors: props.ageDistribution?.map(a => a.color) || [],
  dataLabels: {
    enabled: true,
    formatter: function(val) {
      return formatNumber(val);
    },
  },
  xaxis: {
    categories: props.ageDistribution?.map(a => a.age_group) || [],
    labels: { 
      style: { fontSize: '11px', colors: '#6b7280' },
      rotate: -45,
    },
  },
  yaxis: {
    labels: {
      style: { fontSize: '11px', colors: '#6b7280' },
      formatter: function(val) {
        return formatNumber(val);
      },
    },
  },
  grid: {
    borderColor: '#f3f4f6',
    strokeDashArray: 3,
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: function(val, { seriesIndex, dataPointIndex }) {
        const item = props.ageDistribution?.[dataPointIndex];
        return `${formatNumber(val)} (${item?.percentage || 0}%)`;
      },
    },
  },
}));

const ageChartSeries = computed(() => [
  {
    name: 'Jumlah Member',
    data: props.ageDistribution?.map(a => a.count) || [],
  },
]);

// Purchasing Power by Age Chart (Priority 2)
const purchasingPowerChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    height: 350,
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif',
    stacked: false,
  },
  colors: ['#10b981', '#3b82f6', '#8b5cf6'],
  dataLabels: {
    enabled: false,
  },
  xaxis: {
    categories: props.purchasingPowerByAge?.map(p => p.age_group) || [],
    labels: {
      style: { fontSize: '11px', colors: '#6b7280' },
      rotate: -45,
    },
  },
  yaxis: [
    {
      title: {
        text: 'Total Spending (Rp)',
        style: { color: '#10b981', fontSize: '12px' },
      },
      labels: {
        style: { colors: '#10b981', fontSize: '11px' },
        formatter: function(val) {
          if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(1) + 'M';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(0) + 'K';
          }
          return 'Rp ' + formatNumber(val);
        },
      },
    },
    {
      opposite: true,
      title: {
        text: 'Avg Transaction Value (Rp)',
        style: { color: '#3b82f6', fontSize: '12px' },
      },
      labels: {
        style: { colors: '#3b82f6', fontSize: '11px' },
        formatter: function(val) {
          if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(1) + 'M';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(0) + 'K';
          }
          return 'Rp ' + formatNumber(val);
        },
      },
    },
  ],
  grid: {
    borderColor: '#f3f4f6',
    strokeDashArray: 3,
  },
  legend: {
    position: 'top',
    fontSize: '12px',
    fontWeight: 500,
  },
  tooltip: {
    theme: 'dark',
    shared: true,
    intersect: false,
  },
}));

const purchasingPowerChartSeries = computed(() => [
  {
    name: 'Total Spending',
    data: props.purchasingPowerByAge?.map(p => p.total_spending) || [],
    type: 'column',
  },
  {
    name: 'Avg Transaction Value',
    data: props.purchasingPowerByAge?.map(p => p.avg_transaction_value) || [],
    type: 'line',
  },
  {
    name: 'Total Transactions',
    data: props.purchasingPowerByAge?.map(p => p.total_transactions) || [],
    type: 'column',
  },
]);

// Member Segmentation Chart (Priority 3)
const segmentationChartOptions = computed(() => ({
  chart: {
    type: 'donut',
    height: 300,
    fontFamily: 'Inter, sans-serif',
  },
  colors: ['#8b5cf6', '#10b981', '#3b82f6', '#f97316', '#6b7280'],
  labels: ['VIP', 'Active', 'New', 'At Risk', 'Dormant'],
  legend: {
    position: 'bottom',
    fontSize: '12px',
    fontWeight: 500,
  },
  plotOptions: {
    pie: {
      donut: {
        size: '65%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Total',
            fontSize: '14px',
            fontWeight: 600,
            formatter: function() {
              const seg = props.memberSegmentation || {};
              const total = (seg.vip || 0) + 
                           (seg.active || 0) + 
                           (seg.new || 0) + 
                           (seg.atRisk || 0) + 
                           (seg.dormant || 0);
              return formatNumber(total);
            },
          },
        },
      },
    },
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: function(val) {
        return formatNumber(val);
      },
    },
  },
}));

const segmentationChartSeries = computed(() => {
  const seg = props.memberSegmentation || {};
  return [
    seg.vip || 0,
    seg.active || 0,
    seg.new || 0,
    seg.atRisk || 0,
    seg.dormant || 0,
  ];
});

// Conversion Funnel Chart (Priority 3)
const funnelChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    height: 300,
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif',
  },
  colors: ['#3b82f6', '#10b981', '#8b5cf6', '#f97316', '#14b8a6'],
  dataLabels: {
    enabled: true,
    formatter: function(val) {
      return formatNumber(val);
    },
  },
  xaxis: {
    categories: ['Registered', 'Email Verified', 'First Login', 'First Transaction', 'Repeat Customers'],
    labels: {
      style: { fontSize: '11px', colors: '#6b7280' },
      rotate: -45,
    },
  },
  yaxis: {
    labels: {
      style: { fontSize: '11px', colors: '#6b7280' },
      formatter: function(val) {
        return formatNumber(val);
      },
    },
  },
  grid: {
    borderColor: '#f3f4f6',
    strokeDashArray: 3,
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: function(val) {
        return formatNumber(val);
      },
    },
  },
}));

const funnelChartSeries = computed(() => [
  {
    name: 'Members',
    data: [
      conversionFunnel?.registered || 0,
      conversionFunnel?.emailVerified || 0,
      conversionFunnel?.firstLogin || 0,
      conversionFunnel?.firstTransaction || 0,
      conversionFunnel?.repeatCustomers || 0,
    ],
  },
]);

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num);
}

function formatCurrency(amount) {
  return 'Rp ' + formatNumber(amount);
}

function goToMembers() {
  router.visit('/members');
}

function exportData() {
  // Export functionality - can be enhanced with Laravel Excel
  const data = {
    start_date: dateFilters.value.start_date,
    end_date: dateFilters.value.end_date,
    type: 'dashboard_summary',
  };
  
  // For now, just show a message
  alert('Export functionality akan diimplementasikan dengan Laravel Excel.\n\nData yang akan diexport:\n- Member Statistics\n- Spending Trends\n- Point Activity\n- Regional Breakdown');
  
  // Future implementation:
  // router.visit('/crm/dashboard/export', {
  //   data: data,
  //   method: 'get',
  // });
}

function applyDateFilter() {
  router.visit('/crm/dashboard', {
    data: {
      start_date: dateFilters.value.start_date,
      end_date: dateFilters.value.end_date,
    },
    preserveState: true,
  });
}

function clearDateFilter() {
  dateFilters.value.start_date = '';
  dateFilters.value.end_date = '';
  router.visit('/crm/dashboard', {
    data: {
      start_date: '',
      end_date: '',
    },
    preserveState: true,
  });
}

function getTierColor(tier) {
  const colors = {
    silver: 'from-gray-400 to-gray-600',
    gold: 'from-yellow-400 to-yellow-600',
    platinum: 'from-purple-400 to-purple-600',
  };
  return colors[tier?.toLowerCase()] || 'from-gray-400 to-gray-600';
}

function getTierIcon(tier) {
  const icons = {
    silver: 'fa-medal',
    gold: 'fa-trophy',
    platinum: 'fa-crown',
  };
  return icons[tier?.toLowerCase()] || 'fa-medal';
}
</script>

<template>
  <AppLayout title="Dashboard CRM">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-purple-50">
      <!-- Animated Background Elements -->
      <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
      </div>

      <div class="relative z-10 w-full py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-10">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
              <h1 class="text-4xl font-bold bg-gradient-to-r from-purple-600 via-blue-600 to-indigo-600 bg-clip-text text-transparent mb-2">
                CRM Dashboard
              </h1>
              <p class="text-gray-600 text-lg">Overview dan analisis data member yang komprehensif</p>
            </div>
            <div class="flex gap-3">
              <button
                @click="goToMembers"
                class="group relative px-6 py-3 bg-white/80 backdrop-blur-sm border border-gray-200 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center gap-2 text-gray-700 font-medium"
              >
                <i class="fa-solid fa-users text-purple-500"></i>
                Lihat Semua Member
              </button>
            </div>
          </div>
        </div>

        <!-- Statistics Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Total Members Card -->
          <div class="group relative overflow-hidden bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
            <div class="absolute inset-0 bg-white/10 backdrop-blur-sm"></div>
            <div class="relative p-6 text-white">
              <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                  <i class="fa-solid fa-users text-2xl"></i>
                </div>
                <div class="text-right">
                  <div class="text-sm opacity-90 font-medium">Total Members</div>
                  <div class="text-3xl font-bold">{{ formatNumber(stats?.totalMembers || 0) }}</div>
                </div>
              </div>
              <div class="flex items-center gap-2 text-sm opacity-90">
                <i class="fa-solid fa-arrow-up"></i>
                <span>+{{ formatNumber(stats?.newMembersThisMonth || 0) }} bulan ini</span>
              </div>
            </div>
          </div>

          <!-- Active Members Card -->
          <div class="group relative overflow-hidden bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
            <div class="absolute inset-0 bg-white/10 backdrop-blur-sm"></div>
            <div class="relative p-6 text-white">
              <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                  <i class="fa-solid fa-user-check text-2xl"></i>
                </div>
                <div class="text-right">
                  <div class="text-sm opacity-90 font-medium">Active Members</div>
                  <div class="text-3xl font-bold">{{ formatNumber(stats?.activeMembers || 0) }}</div>
                </div>
              </div>
              <div class="flex items-center gap-2 text-sm opacity-90">
                <span>{{ Math.round((stats?.activeMembers / stats?.totalMembers) * 100) || 0 }}% dari total</span>
              </div>
            </div>
          </div>

          <!-- Total Spending Card -->
          <div class="group relative overflow-hidden bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
            <div class="absolute inset-0 bg-white/10 backdrop-blur-sm"></div>
            <div class="relative p-6 text-white">
              <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                  <i class="fa-solid fa-money-bill-wave text-2xl"></i>
                </div>
                <div class="text-right">
                  <div class="text-sm opacity-90 font-medium">Total Spending</div>
                  <div class="text-xl font-bold">{{ stats?.totalSpendingFormatted || 'Rp 0' }}</div>
                </div>
              </div>
              <div class="flex items-center gap-2 text-sm opacity-90">
                <i class="fa-solid fa-calendar"></i>
                <span>{{ stats?.spendingLastYearFormatted || 'Rp 0' }} setahun</span>
              </div>
            </div>
          </div>

          <!-- Point Balance Card -->
          <div class="group relative overflow-hidden bg-gradient-to-br from-amber-500 to-amber-700 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
            <div class="absolute inset-0 bg-white/10 backdrop-blur-sm"></div>
            <div class="relative p-6 text-white">
              <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                  <i class="fa-solid fa-coins text-2xl"></i>
                </div>
                <div class="text-right">
                  <div class="text-sm opacity-90 font-medium">Total Point Balance</div>
                  <div class="text-3xl font-bold">{{ formatNumber(stats?.totalPointBalance || 0) }}</div>
                </div>
              </div>
              <div class="flex items-center gap-2 text-sm opacity-90">
                <span>{{ formatNumber(stats?.membersWithPoints || 0) }} member aktif</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Secondary Stats Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="text-sm text-gray-600 mb-1">New Today</div>
            <div class="text-2xl font-bold text-purple-600">{{ formatNumber(stats?.newMembersToday || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="text-sm text-gray-600 mb-1">Email Verified</div>
            <div class="text-2xl font-bold text-emerald-600">{{ formatNumber(stats?.emailVerified || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="text-sm text-gray-600 mb-1">Exclusive</div>
            <div class="text-2xl font-bold text-amber-600">{{ formatNumber(stats?.exclusiveMembers || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="text-sm text-gray-600 mb-1">Silver</div>
            <div class="text-2xl font-bold text-gray-500">{{ formatNumber(stats?.tierBreakdown?.silver || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="text-sm text-gray-600 mb-1">Gold</div>
            <div class="text-2xl font-bold text-yellow-500">{{ formatNumber(stats?.tierBreakdown?.gold || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="text-sm text-gray-600 mb-1">Platinum</div>
            <div class="text-2xl font-bold text-purple-500">{{ formatNumber(stats?.tierBreakdown?.platinum || 0) }}</div>
          </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Member Growth Chart -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-purple-500"></i>
                Pertumbuhan Member
              </h3>
            </div>
            <div class="h-64">
              <VueApexCharts
                type="area"
                height="300"
                :options="memberGrowthChartOptions"
                :series="memberGrowthChartSeries"
              />
            </div>
          </div>

          <!-- Tier Distribution Chart -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-blue-500"></i>
                Distribusi Tier
              </h3>
            </div>
            <div class="h-64">
              <VueApexCharts
                type="donut"
                height="300"
                :options="tierChartOptions"
                :series="tierChartSeries"
              />
            </div>
            <div class="mt-4 space-y-2">
              <div v-for="tier in tierDistribution" :key="tier.tier" class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-purple-200 transition-all duration-300">
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full shadow-sm" :style="{ backgroundColor: tier.color }"></div>
                  <span class="font-medium text-gray-700">{{ tier.tier }}</span>
                </div>
                <div class="text-right">
                  <div class="font-bold text-gray-900">{{ formatNumber(tier.count) }}</div>
                  <div class="text-xs text-gray-500">{{ tier.percentage }}%</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Spending & Point Activity Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Spending Trend Chart -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-area text-emerald-500"></i>
                Spending Trend
              </h3>
            </div>
            <div class="h-64">
              <VueApexCharts
                type="area"
                height="300"
                :options="spendingTrendChartOptions"
                :series="spendingTrendChartSeries"
              />
            </div>
          </div>

          <!-- Point Activity Trend Chart -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-coins text-amber-500"></i>
                Point Activity Trend
              </h3>
            </div>
            <div class="h-64">
              <VueApexCharts
                type="area"
                height="300"
                :options="pointActivityChartOptions"
                :series="pointActivityChartSeries"
              />
            </div>
          </div>
        </div>

        <!-- Latest Members & Latest Point Transactions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Latest Members -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-500"></i>
                Member Terbaru
              </h3>
              <button @click="goToMembers" class="text-sm text-purple-600 hover:text-purple-700 font-medium">
                Lihat Semua →
              </button>
            </div>
            <div class="space-y-3">
              <div
                v-for="member in latestMembers"
                :key="member.id"
                class="group p-4 bg-gradient-to-r from-gray-50 to-white rounded-xl border border-gray-100 hover:border-purple-200 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div :class="['w-12 h-12 rounded-xl bg-gradient-to-br', getTierColor(member.tier), 'flex items-center justify-center text-white shadow-lg']">
                      <i :class="['fa-solid', getTierIcon(member.tier)]"></i>
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900">{{ member.name }}</div>
                      <div class="text-sm text-gray-500">{{ member.memberId }}</div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-sm font-bold text-purple-600">{{ member.pointBalanceFormatted }}</div>
                    <div class="text-xs text-gray-500">{{ member.totalSpendingFormatted }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Latest Point Transactions (Priority 1) -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-coins text-amber-500"></i>
                Transaksi Point Terbaru
              </h3>
            </div>
            <div class="space-y-3">
              <div
                v-for="transaction in latestPointTransactions"
                :key="transaction.id"
                :class="['group p-4 bg-gradient-to-r rounded-xl border hover:shadow-lg transition-all duration-300', 
                  transaction.type === 'earned' 
                    ? 'from-green-50 to-white border-green-100 hover:border-green-200' 
                    : 'from-orange-50 to-white border-orange-100 hover:border-orange-200']"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div :class="['w-10 h-10 rounded-lg flex items-center justify-center', 
                      transaction.type === 'earned' ? 'bg-green-500' : 'bg-orange-500']">
                      <i :class="['fa-solid text-white', transaction.type === 'earned' ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900">{{ transaction.memberName }}</div>
                      <div class="text-sm text-gray-500">{{ transaction.memberId }}</div>
                      <div class="text-xs text-gray-400 mt-1">{{ transaction.createdAt }}</div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div :class="['text-sm font-bold', transaction.type === 'earned' ? 'text-green-600' : 'text-orange-600']">
                      {{ transaction.type === 'earned' ? '+' : '-' }}{{ transaction.pointAmountFormatted }} point
                    </div>
                    <div class="text-xs text-gray-500">{{ transaction.transactionValueFormatted }}</div>
                  </div>
                </div>
              </div>
              <div v-if="latestPointTransactions.length === 0" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-coins text-4xl mb-2"></i>
                <p>Tidak ada transaksi point</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Latest Activities -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-clock text-indigo-500"></i>
                Aktivitas Terbaru
              </h3>
            </div>
            <div class="space-y-3">
              <div
                v-for="activity in latestActivities"
                :key="activity.id"
                class="group p-4 bg-gradient-to-r from-gray-50 to-white rounded-xl border border-gray-100 hover:border-indigo-200 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-center gap-3">
                  <div :class="['w-10 h-10 rounded-lg', activity.bgColor, 'flex items-center justify-center']">
                    <i :class="['fa-solid', activity.icon, activity.color]"></i>
                  </div>
                  <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ activity.memberName }}</div>
                    <div class="text-sm text-gray-600">{{ activity.description }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ activity.createdAt }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Gender & Age Distribution Charts (Priority 2) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Gender Distribution Chart -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-venus-mars text-pink-500"></i>
                Distribusi Gender
              </h3>
            </div>
            <div class="h-64">
              <VueApexCharts
                type="donut"
                height="300"
                :options="genderChartOptions"
                :series="genderChartSeries"
              />
            </div>
            <div class="mt-4 space-y-2">
              <div v-for="item in genderDistribution" :key="item.gender" class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-pink-200 transition-all duration-300">
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full shadow-sm" :style="{ backgroundColor: item.color }"></div>
                  <span class="font-medium text-gray-700">{{ item.gender }}</span>
                </div>
                <div class="text-right">
                  <div class="font-bold text-gray-900">{{ formatNumber(item.count) }}</div>
                  <div class="text-xs text-gray-500">{{ item.percentage }}%</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Age Distribution Chart -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-birthday-cake text-blue-500"></i>
                Distribusi Usia
              </h3>
            </div>
            <div class="h-64">
              <VueApexCharts
                type="bar"
                height="300"
                :options="ageChartOptions"
                :series="ageChartSeries"
              />
            </div>
            <div class="mt-4 space-y-2 max-h-48 overflow-y-auto">
              <div v-for="item in ageDistribution" :key="item.age_group" class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-blue-200 transition-all duration-300">
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full shadow-sm" :style="{ backgroundColor: item.color }"></div>
                  <span class="font-medium text-gray-700">{{ item.age_group }}</span>
                </div>
                <div class="text-right">
                  <div class="font-bold text-gray-900">{{ formatNumber(item.count) }}</div>
                  <div class="text-xs text-gray-500">{{ item.percentage }}%</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Purchasing Power by Age (Priority 2) -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-dollar-sign text-emerald-500"></i>
                Daya Beli per Kelompok Usia
              </h3>
            </div>
            <div class="h-80">
              <VueApexCharts
                type="bar"
                height="350"
                :options="purchasingPowerChartOptions"
                :series="purchasingPowerChartSeries"
              />
            </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div v-for="item in purchasingPowerByAge" :key="item.age_group" class="p-4 bg-gradient-to-r from-emerald-50 to-white rounded-xl border border-emerald-100 hover:border-emerald-200 transition-all duration-300">
                <div class="font-semibold text-gray-900 mb-2">{{ item.age_group }}</div>
                <div class="space-y-1 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Total Spending:</span>
                    <span class="font-bold text-emerald-600">{{ item.total_spending_formatted }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Avg/Transaksi:</span>
                    <span class="font-semibold text-gray-800">{{ item.avg_transaction_value_formatted }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Total Transaksi:</span>
                    <span class="font-semibold text-gray-800">{{ formatNumber(item.total_transactions) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Total Customer:</span>
                    <span class="font-semibold text-gray-800">{{ formatNumber(item.total_customers) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Email Verification Status (Priority 2) -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-envelope-circle-check text-blue-500"></i>
                Status Verifikasi Email
              </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="p-6 bg-gradient-to-br from-green-50 to-green-100 rounded-xl border-2 border-green-200">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <div class="text-sm font-medium text-green-700 mb-1">Email Terverifikasi</div>
                    <div class="text-3xl font-bold text-green-800">{{ formatNumber(stats?.emailVerified || 0) }}</div>
                  </div>
                  <div class="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-check text-white text-2xl"></i>
                  </div>
                </div>
                <div class="text-sm text-green-700">
                  {{ stats?.totalMembers > 0 ? Math.round((stats?.emailVerified / stats?.totalMembers) * 100) : 0 }}% dari total member
                </div>
              </div>
              <div class="p-6 bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl border-2 border-orange-200">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <div class="text-sm font-medium text-orange-700 mb-1">Email Belum Terverifikasi</div>
                    <div class="text-3xl font-bold text-orange-800">{{ formatNumber((stats?.totalMembers || 0) - (stats?.emailVerified || 0)) }}</div>
                  </div>
                  <div class="w-16 h-16 bg-orange-500 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-exclamation text-white text-2xl"></i>
                  </div>
                </div>
                <div class="text-sm text-orange-700">
                  {{ stats?.totalMembers > 0 ? Math.round(((stats?.totalMembers - stats?.emailVerified) / stats?.totalMembers) * 100) : 0 }}% dari total member
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Spenders & Most Active -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Top Spenders -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-6">
              <i class="fa-solid fa-trophy text-amber-500"></i>
              Top Spenders
            </h3>
            <div class="space-y-3">
              <div
                v-for="(spender, index) in topSpenders"
                :key="spender.memberId"
                class="group p-4 bg-gradient-to-r from-amber-50 to-white rounded-xl border border-amber-100 hover:border-amber-200 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white font-bold shadow-lg">
                      {{ index + 1 }}
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900">{{ spender.memberName }}</div>
                      <div class="text-sm text-gray-500">{{ spender.orderCount }} transaksi</div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-bold text-amber-600">{{ spender.totalSpendingFormatted }}</div>
                    <div class="text-xs text-gray-500">{{ spender.avgOrderValueFormatted }}/transaksi</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Most Active Members -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-6">
              <i class="fa-solid fa-fire text-orange-500"></i>
              Most Active Members
            </h3>
            <div class="space-y-3">
              <div
                v-for="(active, index) in mostActiveMembers"
                :key="active.memberId"
                class="group p-4 bg-gradient-to-r from-orange-50 to-white rounded-xl border border-orange-100 hover:border-orange-200 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white font-bold shadow-lg">
                      {{ index + 1 }}
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900">{{ active.memberName }}</div>
                      <div class="text-sm text-gray-500">{{ active.transactionCount }} transaksi point</div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-bold text-orange-600">{{ active.pointBalanceFormatted }} point</div>
                    <div class="text-xs text-gray-500">{{ active.orderCount }} orders</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Priority 3: Member Segmentation & Lifetime Value -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Member Segmentation (Priority 3) -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-users-slash text-purple-500"></i>
                Segmentasi Member
              </h3>
            </div>
            <div class="h-64 mb-4">
              <VueApexCharts
                type="donut"
                height="300"
                :options="segmentationChartOptions"
                :series="segmentationChartSeries"
              />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="p-3 bg-gradient-to-r from-purple-50 to-white rounded-lg border border-purple-100">
                <div class="text-xs text-gray-600 mb-1">VIP</div>
                <div class="text-lg font-bold text-purple-600">{{ formatNumber(props.memberSegmentation?.vip || 0) }}</div>
              </div>
              <div class="p-3 bg-gradient-to-r from-green-50 to-white rounded-lg border border-green-100">
                <div class="text-xs text-gray-600 mb-1">Active</div>
                <div class="text-lg font-bold text-green-600">{{ formatNumber(props.memberSegmentation?.active || 0) }}</div>
              </div>
              <div class="p-3 bg-gradient-to-r from-blue-50 to-white rounded-lg border border-blue-100">
                <div class="text-xs text-gray-600 mb-1">New</div>
                <div class="text-lg font-bold text-blue-600">{{ formatNumber(props.memberSegmentation?.new || 0) }}</div>
              </div>
              <div class="p-3 bg-gradient-to-r from-orange-50 to-white rounded-lg border border-orange-100">
                <div class="text-xs text-gray-600 mb-1">At Risk</div>
                <div class="text-lg font-bold text-orange-600">{{ formatNumber(props.memberSegmentation?.atRisk || 0) }}</div>
              </div>
              <div class="p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 col-span-2">
                <div class="text-xs text-gray-600 mb-1">Dormant</div>
                <div class="text-lg font-bold text-gray-600">{{ formatNumber(props.memberSegmentation?.dormant || 0) }}</div>
              </div>
            </div>
          </div>

          <!-- Member Lifetime Value (Priority 3) -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-dollar-sign text-emerald-500"></i>
                Member Lifetime Value (LTV)
              </h3>
            </div>
            <div class="space-y-4">
              <div class="p-4 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl border-2 border-emerald-200">
                <div class="text-sm font-medium text-emerald-700 mb-1">Average LTV</div>
                <div class="text-3xl font-bold text-emerald-800">{{ memberLifetimeValue?.averageFormatted || 'Rp 0' }}</div>
              </div>
              <div class="p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border-2 border-blue-200">
                <div class="text-sm font-medium text-blue-700 mb-1">Total LTV</div>
                <div class="text-2xl font-bold text-blue-800">{{ memberLifetimeValue?.totalFormatted || 'Rp 0' }}</div>
              </div>
              <div class="space-y-2">
                <div class="text-sm font-semibold text-gray-700 mb-2">LTV by Tier:</div>
                <div v-for="(tierData, tier) in memberLifetimeValue?.byTier" :key="tier" class="p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100">
                  <div class="flex justify-between items-center">
                    <span class="font-medium text-gray-700 capitalize">{{ tier }}</span>
                    <div class="text-right">
                      <div class="text-sm font-bold text-gray-900">{{ tierData.averageFormatted }}</div>
                      <div class="text-xs text-gray-500">{{ formatNumber(tierData.count) }} members</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Priority 3: Churn Analysis & Conversion Funnel -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Churn Analysis (Priority 3) -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-user-xmark text-red-500"></i>
                Churn Analysis
              </h3>
            </div>
            <div class="space-y-4">
              <div class="p-4 bg-gradient-to-br from-red-50 to-red-100 rounded-xl border-2 border-red-200">
                <div class="text-sm font-medium text-red-700 mb-1">Churned Members</div>
                <div class="text-3xl font-bold text-red-800">{{ formatNumber(churnAnalysis?.churned || 0) }}</div>
                <div class="text-xs text-red-600 mt-1">No activity in last 90 days</div>
              </div>
              <div class="p-4 bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl border-2 border-orange-200">
                <div class="text-sm font-medium text-orange-700 mb-1">At Risk of Churn</div>
                <div class="text-2xl font-bold text-orange-800">{{ formatNumber(churnAnalysis?.atRiskChurn || 0) }}</div>
                <div class="text-xs text-orange-600 mt-1">No activity in last 30-60 days</div>
              </div>
              <div class="p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl border-2 border-green-200">
                <div class="text-sm font-medium text-green-700 mb-1">Retention Rate</div>
                <div class="text-3xl font-bold text-green-800">{{ churnAnalysis?.retentionRate || 0 }}%</div>
                <div class="text-xs text-green-600 mt-1">{{ formatNumber(churnAnalysis?.activeLast30Days || 0) }} / {{ formatNumber(churnAnalysis?.totalActive || 0) }} active</div>
              </div>
            </div>
          </div>

          <!-- Conversion Funnel (Priority 3) -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-filter text-indigo-500"></i>
                Conversion Funnel (30 Days)
              </h3>
            </div>
            <div class="h-64 mb-4">
              <VueApexCharts
                type="bar"
                height="300"
                :options="funnelChartOptions"
                :series="funnelChartSeries"
              />
            </div>
            <div class="space-y-2">
              <div class="flex justify-between items-center p-2 bg-blue-50 rounded">
                <span class="text-sm font-medium text-gray-700">Registered</span>
                <span class="font-bold text-blue-600">{{ formatNumber(conversionFunnel?.registered || 0) }}</span>
              </div>
              <div class="flex justify-between items-center p-2 bg-green-50 rounded">
                <span class="text-sm font-medium text-gray-700">Email Verified</span>
                <span class="font-bold text-green-600">{{ formatNumber(conversionFunnel?.emailVerified || 0) }} ({{ conversionFunnel?.emailVerificationRate || 0 }}%)</span>
              </div>
              <div class="flex justify-between items-center p-2 bg-purple-50 rounded">
                <span class="text-sm font-medium text-gray-700">First Login</span>
                <span class="font-bold text-purple-600">{{ formatNumber(conversionFunnel?.firstLogin || 0) }} ({{ conversionFunnel?.loginRate || 0 }}%)</span>
              </div>
              <div class="flex justify-between items-center p-2 bg-orange-50 rounded">
                <span class="text-sm font-medium text-gray-700">First Transaction</span>
                <span class="font-bold text-orange-600">{{ formatNumber(conversionFunnel?.firstTransaction || 0) }} ({{ conversionFunnel?.transactionRate || 0 }}%)</span>
              </div>
              <div class="flex justify-between items-center p-2 bg-emerald-50 rounded">
                <span class="text-sm font-medium text-gray-700">Repeat Customers</span>
                <span class="font-bold text-emerald-600">{{ formatNumber(conversionFunnel?.repeatCustomers || 0) }} ({{ conversionFunnel?.repeatRate || 0 }}%)</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Priority 3: Comparison Data (MoM & YoY) -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-blue-500"></i>
                Perbandingan Data (MoM & YoY)
              </h3>
              <button @click="exportData" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2 shadow-md">
                <i class="fa-solid fa-download"></i>
                Export Data
              </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Month over Month -->
              <div class="p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border-2 border-blue-200">
                <h4 class="font-bold text-blue-800 mb-4">Month over Month</h4>
                <div class="space-y-3">
                  <div>
                    <div class="text-sm text-blue-700 mb-1">New Members</div>
                    <div class="flex items-center justify-between">
                      <span class="text-lg font-bold text-blue-900">{{ formatNumber(comparisonData?.monthOverMonth?.members?.current || 0) }}</span>
                      <span :class="['text-sm font-semibold', comparisonData?.monthOverMonth?.members?.growth >= 0 ? 'text-green-600' : 'text-red-600']">
                        <i :class="['fa-solid', comparisonData?.monthOverMonth?.members?.growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                        {{ Math.abs(comparisonData?.monthOverMonth?.members?.growth || 0) }}%
                      </span>
                    </div>
                    <div class="text-xs text-blue-600 mt-1">vs {{ formatNumber(comparisonData?.monthOverMonth?.members?.previous || 0) }} last month</div>
                  </div>
                  <div>
                    <div class="text-sm text-blue-700 mb-1">Total Spending</div>
                    <div class="flex items-center justify-between">
                      <span class="text-lg font-bold text-blue-900">{{ comparisonData?.monthOverMonth?.spending?.currentFormatted || 'Rp 0' }}</span>
                      <span :class="['text-sm font-semibold', comparisonData?.monthOverMonth?.spending?.growth >= 0 ? 'text-green-600' : 'text-red-600']">
                        <i :class="['fa-solid', comparisonData?.monthOverMonth?.spending?.growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                        {{ Math.abs(comparisonData?.monthOverMonth?.spending?.growth || 0) }}%
                      </span>
                    </div>
                    <div class="text-xs text-blue-600 mt-1">vs {{ comparisonData?.monthOverMonth?.spending?.previousFormatted || 'Rp 0' }} last month</div>
                  </div>
                </div>
              </div>

              <!-- Year over Year -->
              <div class="p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl border-2 border-purple-200">
                <h4 class="font-bold text-purple-800 mb-4">Year over Year</h4>
                <div class="space-y-3">
                  <div>
                    <div class="text-sm text-purple-700 mb-1">New Members</div>
                    <div class="flex items-center justify-between">
                      <span class="text-lg font-bold text-purple-900">{{ formatNumber(comparisonData?.yearOverYear?.members?.current || 0) }}</span>
                      <span :class="['text-sm font-semibold', comparisonData?.yearOverYear?.members?.growth >= 0 ? 'text-green-600' : 'text-red-600']">
                        <i :class="['fa-solid', comparisonData?.yearOverYear?.members?.growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                        {{ Math.abs(comparisonData?.yearOverYear?.members?.growth || 0) }}%
                      </span>
                    </div>
                    <div class="text-xs text-purple-600 mt-1">vs {{ formatNumber(comparisonData?.yearOverYear?.members?.previous || 0) }} last year</div>
                  </div>
                  <div>
                    <div class="text-sm text-purple-700 mb-1">Total Spending</div>
                    <div class="flex items-center justify-between">
                      <span class="text-lg font-bold text-purple-900">{{ comparisonData?.yearOverYear?.spending?.currentFormatted || 'Rp 0' }}</span>
                      <span :class="['text-sm font-semibold', comparisonData?.yearOverYear?.spending?.growth >= 0 ? 'text-green-600' : 'text-red-600']">
                        <i :class="['fa-solid', comparisonData?.yearOverYear?.spending?.growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                        {{ Math.abs(comparisonData?.yearOverYear?.spending?.growth || 0) }}%
                      </span>
                    </div>
                    <div class="text-xs text-purple-600 mt-1">vs {{ comparisonData?.yearOverYear?.spending?.previousFormatted || 'Rp 0' }} last year</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Priority 3: Regional Breakdown -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-map-marker-alt text-green-500"></i>
                Breakdown per Outlet/Region
              </h3>
            </div>
            <div v-if="regionalBreakdown.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-map text-4xl mb-2"></i>
              <p>Tidak ada data regional</p>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase">Outlet</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase">Region</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase">Total Members</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase">Total Orders</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase">Total Spending</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase">Avg Order Value</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="(item, index) in regionalBreakdown" :key="index" class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.outlet_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ item.region }}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatNumber(item.total_members) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700">{{ formatNumber(item.total_orders) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-blue-600">{{ item.total_spending_formatted }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700">{{ item.avg_order_value_formatted }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <style>
      @keyframes blob {
        0%, 100% {
          transform: translate(0, 0) scale(1);
        }
        33% {
          transform: translate(30px, -50px) scale(1.1);
        }
        66% {
          transform: translate(-20px, 20px) scale(0.9);
        }
      }
      .animate-blob {
        animation: blob 7s infinite;
      }
      .animation-delay-2000 {
        animation-delay: 2s;
      }
      .animation-delay-4000 {
        animation-delay: 4s;
      }
    </style>
  </AppLayout>
</template>
