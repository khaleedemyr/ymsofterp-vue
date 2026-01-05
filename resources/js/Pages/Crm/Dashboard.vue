<script setup>
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
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
  occupationDistribution: {
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
  purchasingPowerByAgeThisMonth: {
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
  topSpendersDateRange: {
    type: Object,
    default: () => null
  },
  mostActiveMembers: {
    type: Array,
    default: () => []
  },
  mostActiveMembersDateRange: {
    type: Object,
    default: () => null
  },
  top10Points: {
    type: Array,
    default: () => []
  },
  top10VoucherOwners: {
    type: Array,
    default: () => []
  },
  top10PointRedemptions: {
    type: Array,
    default: () => []
  },
  memberFavouritePicks: {
    type: Object,
    default: () => ({ food: [], beverages: [] })
  },
  activeVouchers: {
    type: Array,
    default: () => []
  },
  activeChallenges: {
    type: Array,
    default: () => []
  },
  activeRewards: {
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
    type: Object,
    default: () => ({
      currentMonth: { outlets: [], regions: [], period: '', startDate: '', endDate: '' },
      last60Days: { outlets: [], regions: [], period: '', startDate: '', endDate: '' },
      last90Days: { outlets: [], regions: [], period: '', startDate: '', endDate: '' }
    })
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

// Contribution by outlet modal
const showContributionModal = ref(false);
const contributionModalPeriod = ref('today');
const contributionByOutlet = ref([]);
const loadingContribution = ref(false);

const openContributionModal = async (period) => {
  contributionModalPeriod.value = period;
  showContributionModal.value = true;
  loadingContribution.value = true;
  contributionByOutlet.value = [];
  
  try {
    const response = await axios.get('/api/crm/contribution-by-outlet', {
      params: { period }
    });
    
    if (response.data.status === 'success') {
      contributionByOutlet.value = response.data.data || [];
    }
  } catch (error) {
    console.error('Error fetching contribution by outlet:', error);
  } finally {
    loadingContribution.value = false;
  }
};

const closeContributionModal = () => {
  showContributionModal.value = false;
  contributionByOutlet.value = [];
};

// Member transactions modal
const showMemberTransactionsModal = ref(false);
const regionalPeriod = ref('currentMonth'); // 'currentMonth', 'last60Days', 'last90Days'
const memberTransactionsData = ref({
  memberId: '',
  memberName: '',
  type: 'orders', // 'orders', 'points', 'vouchers', 'redemptions'
});
const memberTransactions = ref([]);
const loadingMemberTransactions = ref(false);
const expandedTransactions = ref(new Set()); // Track which transactions are expanded

const openMemberTransactionsModal = async (memberId, memberName, type = 'orders') => {
  memberTransactionsData.value = {
    memberId: memberId,
    memberName: memberName,
    type: type,
  };
  showMemberTransactionsModal.value = true;
  loadingMemberTransactions.value = true;
  memberTransactions.value = [];
  
  try {
    let endpoint = '/api/crm/member-transactions';
    let params = { 
      member_id: memberId,
      type: type
    };
    
    // Use different endpoints for vouchers and redemptions
    if (type === 'vouchers') {
      endpoint = '/api/crm/member-vouchers';
      params = { member_id: memberId };
    } else if (type === 'redemptions') {
      endpoint = '/api/crm/member-point-redemptions';
      params = { member_id: memberId };
    }
    
    const response = await axios.get(endpoint, { params });
    
    if (response.data.status === 'success') {
      memberTransactions.value = response.data.data || [];
    } else {
      console.error('Error response:', response.data);
      memberTransactions.value = [];
    }
  } catch (error) {
    console.error('Error fetching member data:', error);
    if (error.response) {
      console.error('Error response data:', error.response.data);
    }
    memberTransactions.value = [];
  } finally {
    loadingMemberTransactions.value = false;
  }
};

const closeMemberTransactionsModal = () => {
  showMemberTransactionsModal.value = false;
  memberTransactions.value = [];
  expandedTransactions.value.clear();
  memberTransactionsData.value = {
    memberId: '',
    memberName: '',
    type: 'orders',
  };
};

const toggleTransactionDetails = (transactionId) => {
  if (expandedTransactions.value.has(transactionId)) {
    expandedTransactions.value.delete(transactionId);
  } else {
    expandedTransactions.value.add(transactionId);
  }
};

const isTransactionExpanded = (transactionId) => {
  return expandedTransactions.value.has(transactionId);
};

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

// Occupation Distribution Chart
const occupationChartOptions = computed(() => ({
  chart: {
    type: 'donut',
    height: 300,
    fontFamily: 'Inter, sans-serif',
  },
  colors: props.occupationDistribution?.map(o => o.color) || [],
  labels: props.occupationDistribution?.map(o => o.occupation) || [],
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
              return formatNumber(props.occupationDistribution?.reduce((sum, o) => sum + o.count, 0) || 0);
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
        const item = props.occupationDistribution?.[seriesIndex];
        return `${formatNumber(val)} (${item?.percentage || 0}%)`;
      },
    },
  },
}));

const occupationChartSeries = computed(() => 
  props.occupationDistribution?.map(o => o.count) || []
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
  plotOptions: {
    bar: {
      distributed: true, // Enable different colors for each bar
      borderRadius: 4,
      columnWidth: '60%',
    },
  },
  dataLabels: {
    enabled: true,
    formatter: function(val) {
      return formatNumber(val);
    },
  },
  xaxis: {
    categories: props.ageDistribution?.map(a => a.age_group_label || a.age_group) || [],
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
  legend: {
    show: false, // Hide legend since we have the list below
  },
}));

const ageChartSeries = computed(() => [
  {
    name: 'Jumlah Member',
    data: props.ageDistribution?.map(a => a.count) || [],
  },
]);

// Purchasing Power by Age Chart (Priority 2)
const purchasingPowerChartOptions = computed(() => {
  // Define colors for each age group
  const ageGroupColors = {
    'Anak-anak': '#f59e0b', // amber
    'Remaja': '#ef4444', // red
    'Dewasa Muda': '#3b82f6', // blue
    'Dewasa Produktif': '#10b981', // emerald
    'Dewasa Matang': '#8b5cf6', // purple
    'Usia Tua': '#6b7280', // gray
    'Tidak Diketahui': '#9ca3af', // light gray
  };
  
  const categories = props.purchasingPowerByAge?.map(p => p.age_group_label || p.age_group) || [];
  const colors = props.purchasingPowerByAge?.map(p => {
    const ageGroup = p.age_group || 'Tidak Diketahui';
    return ageGroupColors[ageGroup] || '#9ca3af';
  }) || [];
  
  return {
    chart: {
      type: 'bar',
      height: 350,
      toolbar: { show: false },
      fontFamily: 'Inter, sans-serif',
      stacked: false,
    },
    plotOptions: {
      bar: {
        distributed: true, // Different color for each bar
        borderRadius: 6,
        columnWidth: '60%',
        dataLabels: {
          position: 'top',
        },
      },
    },
    colors: colors,
    dataLabels: {
      enabled: true,
      formatter: function(val) {
        if (val >= 1000000000) {
          return 'Rp ' + (val / 1000000000).toFixed(1) + 'M';
        } else if (val >= 1000000) {
          return 'Rp ' + (val / 1000000).toFixed(1) + 'Jt';
        } else if (val >= 1000) {
          return 'Rp ' + (val / 1000).toFixed(0) + 'Rb';
        }
        return 'Rp ' + formatNumber(val);
      },
      style: {
        fontSize: '10px',
        fontWeight: 600,
        colors: ['#fff'],
      },
      offsetY: -5,
    },
    xaxis: {
      categories: categories,
      labels: {
        style: { fontSize: '11px', colors: '#6b7280' },
        rotate: -45,
      },
    },
    yaxis: {
      title: {
        text: 'Total Spending (Rp)',
        style: { color: '#6b7280', fontSize: '12px', fontWeight: 600 },
      },
      labels: {
        style: { colors: '#6b7280', fontSize: '11px' },
        formatter: function(val) {
          if (val >= 1000000000) {
            return 'Rp ' + (val / 1000000000).toFixed(1) + 'M';
          } else if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(1) + 'Jt';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(0) + 'Rb';
          }
          return 'Rp ' + formatNumber(val);
        },
      },
    },
    grid: {
      borderColor: '#f3f4f6',
      strokeDashArray: 3,
    },
    legend: {
      show: false, // Hide legend since we have distributed colors
    },
    tooltip: {
      theme: 'dark',
      shared: true,
      intersect: false,
    },
  };
});

const purchasingPowerChartSeries = computed(() => {
  if (!props.purchasingPowerByAge || !Array.isArray(props.purchasingPowerByAge) || props.purchasingPowerByAge.length === 0) {
    return [];
  }
  
  try {
    const data = props.purchasingPowerByAge.map(p => {
      if (!p || typeof p !== 'object') return 0;
      const spending = p.total_spending;
      if (spending === null || spending === undefined) return 0;
      const numValue = typeof spending === 'number' ? spending : parseFloat(spending);
      return isNaN(numValue) ? 0 : numValue;
    });
    
    if (data.length === 0 || data.every(val => val === 0)) {
      return [];
    }
    
    return [
      {
        name: 'Total Spending',
        data: data,
      },
    ];
  } catch (error) {
    console.error('Error processing purchasing power chart series:', error);
    return [];
  }
});

// Line chart for current month
const purchasingPowerLineChartOptions = computed(() => {
  const ageGroupColors = {
    'Anak-anak': '#f59e0b',
    'Remaja': '#ef4444',
    'Dewasa Muda': '#3b82f6',
    'Dewasa Produktif': '#10b981',
    'Dewasa Matang': '#8b5cf6',
    'Usia Tua': '#6b7280',
  };
  
  const categories = props.purchasingPowerByAgeThisMonth?.map(d => d.date_label || d.day) || [];
  
  return {
    chart: {
      type: 'line',
      height: 350,
      toolbar: { show: false },
      fontFamily: 'Inter, sans-serif',
      zoom: { enabled: false },
    },
    colors: Object.values(ageGroupColors),
    stroke: {
      width: 3,
      curve: 'smooth',
    },
    markers: {
      size: 4,
      hover: {
        size: 6,
      },
    },
    dataLabels: {
      enabled: false,
    },
    xaxis: {
      categories: categories,
      labels: {
        style: { fontSize: '11px', colors: '#6b7280' },
        rotate: -45,
      },
    },
    yaxis: {
      title: {
        text: 'Total Spending (Rp)',
        style: { color: '#6b7280', fontSize: '12px', fontWeight: 600 },
      },
      labels: {
        style: { colors: '#6b7280', fontSize: '11px' },
        formatter: function(val) {
          if (val >= 1000000000) {
            return 'Rp ' + (val / 1000000000).toFixed(1) + 'M';
          } else if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(1) + 'Jt';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(0) + 'Rb';
          }
          return 'Rp ' + formatNumber(val);
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
      shared: true,
      intersect: false,
    },
  };
});

const purchasingPowerLineChartSeries = computed(() => {
  const ageGroups = ['Anak-anak', 'Remaja', 'Dewasa Muda', 'Dewasa Produktif', 'Dewasa Matang', 'Usia Tua'];
  const ageGroupLabels = {
    'Anak-anak': 'Anak-anak (< 13 tahun)',
    'Remaja': 'Remaja (13-18 tahun)',
    'Dewasa Muda': 'Dewasa Muda (19-30 tahun)',
    'Dewasa Produktif': 'Dewasa Produktif (31-45 tahun)',
    'Dewasa Matang': 'Dewasa Matang (46-59 tahun)',
    'Usia Tua': 'Usia Tua (≥ 60 tahun)',
  };
  
  return ageGroups.map(ageGroup => ({
    name: ageGroupLabels[ageGroup] || ageGroup,
    data: props.purchasingPowerByAgeThisMonth?.map(d => d[ageGroup] || 0) || [],
  }));
});

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

// Regional Breakdown - Current Data (must be declared first as it's used by chart options)
const currentRegionalData = computed(() => {
  const data = props.regionalBreakdown || {};
  return data[regionalPeriod.value] || { outlets: [], regions: [], period: '', startDate: '', endDate: '' };
});

// Regional Breakdown - Get Color by Region
const getRegionColor = (regionName) => {
  const colors = {
    'Jakarta-Tangerang': '#3b82f6', // Blue
    'Bandung Prime': '#10b981', // Green
    'Bandung Reguler': '#f59e0b', // Orange
    'Tempayan': '#8b5cf6', // Purple
    'Unknown': '#6b7280', // Gray
  };
  return colors[regionName] || '#6b7280';
};

// Regional Pie Chart Options
const regionalPieChartOptions = computed(() => {
  const regions = currentRegionalData.value?.regions || [];
  const labels = regions.map(r => r.region);
  const colors = regions.map(r => getRegionColor(r.region));
  
  return {
    chart: {
      type: 'pie',
      height: 350,
      fontFamily: 'Inter, sans-serif',
    },
    colors: colors,
    labels: labels,
    legend: {
      position: 'bottom',
      fontSize: '12px',
      fontWeight: 500,
    },
    tooltip: {
      y: {
        formatter: function(val) {
          return 'Rp ' + formatNumber(val);
        }
      }
    },
    dataLabels: {
      enabled: true,
      formatter: function(val, opts) {
        const total = opts.w.globals.seriesTotals.reduce((a, b) => a + b, 0);
        const percentage = total > 0 ? (val / total * 100).toFixed(1) : '0.0';
        return percentage + '%';
      }
    },
  };
});

// Regional Pie Chart Series
const regionalPieChartSeries = computed(() => {
  const regions = currentRegionalData.value?.regions || [];
  return regions.map(r => r.total_spending);
});

// Regional Bar Chart Options
const regionalBarChartOptions = computed(() => {
  const outlets = currentRegionalData.value?.outlets || [];
  const labels = outlets.map(o => o.outlet_name);
  const colors = outlets.map(o => getRegionColor(o.region));
  
  return {
    chart: {
      type: 'bar',
      height: 350,
      fontFamily: 'Inter, sans-serif',
      toolbar: { show: false },
    },
    colors: colors,
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: '60%',
        distributed: true,
        borderRadius: 4,
      },
    },
    dataLabels: {
      enabled: false,
    },
    xaxis: {
      categories: labels,
      labels: {
        rotate: -45,
        rotateAlways: true,
        style: {
          fontSize: '11px',
        },
      },
    },
    yaxis: {
      labels: {
        formatter: function(val) {
          if (val >= 1000000000) {
            return 'Rp ' + (val / 1000000000).toFixed(1) + 'M';
          } else if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(1) + 'Jt';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(1) + 'Rb';
          }
          return 'Rp ' + val;
        }
      }
    },
    tooltip: {
      y: {
        formatter: function(val) {
          return 'Rp ' + formatNumber(val);
        }
      }
    },
    legend: {
      show: false,
    },
  };
});

// Regional Bar Chart Series
const regionalBarChartSeries = computed(() => {
  const outlets = currentRegionalData.value?.outlets || [];
  return [{
    name: 'Total Spending',
    data: outlets.map(o => o.total_spending),
  }];
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

const funnelChartSeries = computed(() => {
  const funnel = props.conversionFunnel || {};
  return [
    {
      name: 'Members',
      data: [
        funnel.registered || 0,
        funnel.emailVerified || 0,
        funnel.firstLogin || 0,
        funnel.firstTransaction || 0,
        funnel.repeatCustomers || 0,
      ],
    },
  ];
});

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
    loyal: 'from-yellow-400 to-yellow-600',
    elite: 'from-purple-400 to-purple-600',
  };
  return colors[tier?.toLowerCase()] || 'from-gray-400 to-gray-600';
}

function getTierIcon(tier) {
  const icons = {
    silver: 'fa-medal',
    loyal: 'fa-trophy',
    elite: 'fa-crown',
  };
  return icons[tier?.toLowerCase()] || 'fa-medal';
}

function getAgeGroupColor(ageGroup) {
  const colors = {
    'Anak-anak': '#f59e0b', // amber
    'Remaja': '#ef4444', // red
    'Dewasa Muda': '#3b82f6', // blue
    'Dewasa Produktif': '#10b981', // emerald
    'Dewasa Matang': '#8b5cf6', // purple
    'Usia Tua': '#6b7280', // gray
    'Tidak Diketahui': '#9ca3af', // light gray
  };
  return colors[ageGroup] || '#9ca3af';
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
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
              <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm opacity-90">
                  <i class="fa-solid fa-shopping-cart text-xs"></i>
                  <span>Bertransaksi 90 hari terakhir</span>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-white/20">
                  <div class="flex items-center gap-2 text-sm opacity-90">
                    <i class="fa-solid fa-user-slash text-xs"></i>
                    <span>Dormant:</span>
                  </div>
                  <div class="text-lg font-semibold">{{ formatNumber(stats?.dormantMembers || 0) }}</div>
                </div>
                <div class="text-xs opacity-75 mt-1">
                  Tidak ada aktivitas & login 90 hari terakhir
                </div>
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
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4 mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-user-plus text-purple-500 text-lg"></i>
              <div class="text-sm text-gray-600">New Today</div>
            </div>
            <div class="text-2xl font-bold text-purple-600">{{ formatNumber(stats?.newMembersToday || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-calendar-plus text-indigo-500 text-lg"></i>
              <div class="text-sm text-gray-600">New This Month</div>
            </div>
            <div class="text-2xl font-bold text-indigo-600">{{ formatNumber(stats?.newMembersThisMonth || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-envelope-circle-check text-emerald-500 text-lg"></i>
              <div class="text-sm text-gray-600">Email Verified</div>
            </div>
            <div class="text-2xl font-bold text-emerald-600">{{ formatNumber(stats?.emailVerified || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-envelope text-red-500 text-lg"></i>
              <div class="text-sm text-gray-600">Email Unverified</div>
            </div>
            <div class="text-2xl font-bold text-red-600">{{ formatNumber(stats?.emailUnverified || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-medal text-gray-500 text-lg"></i>
              <div class="text-sm text-gray-600">Silver</div>
            </div>
            <div class="text-2xl font-bold text-gray-500">{{ formatNumber(stats?.tierBreakdown?.silver || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-trophy text-yellow-500 text-lg"></i>
              <div class="text-sm text-gray-600">Loyal</div>
            </div>
            <div class="text-2xl font-bold text-yellow-500">{{ formatNumber(stats?.tierBreakdown?.loyal || 0) }}</div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa-solid fa-crown text-purple-500 text-lg"></i>
              <div class="text-sm text-gray-600">Elite</div>
            </div>
            <div class="text-2xl font-bold text-purple-500">{{ formatNumber(stats?.tierBreakdown?.elite || 0) }}</div>
          </div>
        </div>

        <!-- Spending Cards (Today, This Month, This Year) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                  <i class="fa-solid fa-calendar-day text-blue-600 text-xl"></i>
                </div>
                <div>
                  <div class="text-sm text-gray-600">Spending Hari Ini</div>
                  <div class="text-2xl font-bold text-blue-600">{{ stats?.spendingTodayFormatted || 'Rp 0' }}</div>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                  <i class="fa-solid fa-calendar-alt text-green-600 text-xl"></i>
                </div>
                <div>
                  <div class="text-sm text-gray-600">Spending Bulan Ini</div>
                  <div class="text-2xl font-bold text-green-600">{{ stats?.spendingThisMonthFormatted || 'Rp 0' }}</div>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                  <i class="fa-solid fa-calendar-check text-purple-600 text-xl"></i>
                </div>
                <div>
                  <div class="text-sm text-gray-600">Spending Tahun Ini</div>
                  <div class="text-2xl font-bold text-purple-600">{{ stats?.spendingThisYearFormatted || 'Rp 0' }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Member Contribution to Revenue Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div @click="openContributionModal('today')" class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 cursor-pointer hover:scale-105">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                  <i class="fa-solid fa-chart-line text-blue-600 text-xl"></i>
                </div>
                <div>
                  <div class="text-sm text-gray-600">Kontribusi Member Hari Ini</div>
                  <div class="text-2xl font-bold text-blue-600">{{ stats?.memberContributionTodayFormatted || '0%' }}</div>
                </div>
              </div>
              <i class="fa-solid fa-chevron-right text-gray-400"></i>
            </div>
            <div class="text-xs text-gray-500 mt-2 space-y-1">
              <div>Member: {{ stats?.memberRevenueTodayFormatted || 'Rp 0' }}</div>
              <div>Total Revenue: {{ stats?.totalRevenueTodayFormatted || 'Rp 0' }}</div>
            </div>
          </div>
          <div @click="openContributionModal('month')" class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 cursor-pointer hover:scale-105">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                  <i class="fa-solid fa-chart-area text-green-600 text-xl"></i>
                </div>
                <div>
                  <div class="text-sm text-gray-600">Kontribusi Member Bulan Ini</div>
                  <div class="text-2xl font-bold text-green-600">{{ stats?.memberContributionThisMonthFormatted || '0%' }}</div>
                </div>
              </div>
              <i class="fa-solid fa-chevron-right text-gray-400"></i>
            </div>
            <div class="text-xs text-gray-500 mt-2 space-y-1">
              <div>Member: {{ stats?.memberRevenueThisMonthFormatted || 'Rp 0' }}</div>
              <div>Total Revenue: {{ stats?.totalRevenueThisMonthFormatted || 'Rp 0' }}</div>
            </div>
          </div>
          <div @click="openContributionModal('year')" class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 cursor-pointer hover:scale-105">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                  <i class="fa-solid fa-chart-pie text-purple-600 text-xl"></i>
                </div>
                <div>
                  <div class="text-sm text-gray-600">Kontribusi Member Tahun Ini</div>
                  <div class="text-2xl font-bold text-purple-600">{{ stats?.memberContributionThisYearFormatted || '0%' }}</div>
                </div>
              </div>
              <i class="fa-solid fa-chevron-right text-gray-400"></i>
            </div>
            <div class="text-xs text-gray-500 mt-2 space-y-1">
              <div>Member: {{ stats?.memberRevenueThisYearFormatted || 'Rp 0' }}</div>
              <div>Total Revenue: {{ stats?.totalRevenueThisYearFormatted || 'Rp 0' }}</div>
            </div>
          </div>
        </div>

        <!-- Point Redeemed & Voucher Purchase Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                  <i class="fa-solid fa-coins text-orange-600 text-xl"></i>
                </div>
                <div>
                  <div class="text-sm text-gray-600">Total Point Redeemed</div>
                  <div class="text-2xl font-bold text-orange-600">{{ formatNumber(stats?.totalPointRedeemed || 0) }}</div>
                </div>
              </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">
              Total point yang telah ditukar/digunakan
            </div>
          </div>
          <div class="bg-white/80 backdrop-blur-sm rounded-xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-pink-100 rounded-xl flex items-center justify-center">
                  <i class="fa-solid fa-ticket text-pink-600 text-xl"></i>
                </div>
                <div>
                  <div class="text-sm text-gray-600">Point Beli Voucher</div>
                  <div class="text-2xl font-bold text-pink-600">{{ formatNumber(stats?.totalPointVoucherPurchase || 0) }}</div>
                </div>
              </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">
              Total point yang digunakan untuk membeli voucher di voucher store
            </div>
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
                    <div class="flex-1">
                      <div class="font-semibold text-gray-900">{{ member.name }}</div>
                      <div class="text-xs text-gray-500 mt-1">
                        <div class="flex items-center gap-1">
                          <i class="fa-solid fa-id-card text-gray-400 text-xs"></i>
                          <span>{{ member.member_id || member.memberId }}</span>
                        </div>
                        <div v-if="member.email" class="flex items-center gap-1 mt-0.5">
                          <i class="fa-solid fa-envelope text-gray-400 text-xs"></i>
                          <span>{{ member.email }}</span>
                        </div>
                        <div v-if="member.phone || member.mobile_phone" class="flex items-center gap-1 mt-0.5">
                          <i class="fa-solid fa-phone text-gray-400 text-xs"></i>
                          <span>{{ member.phone || member.mobile_phone }}</span>
                        </div>
                      </div>
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
                    <div class="flex-1">
                      <div class="font-semibold text-gray-900">{{ transaction.memberName }}</div>
                      <div class="text-sm text-gray-500">{{ transaction.memberId }}</div>
                      <div v-if="transaction.description" class="text-xs font-medium mt-1" :class="transaction.type === 'earned' ? 'text-green-600' : 'text-orange-600'">
                        <i :class="['fa-solid mr-1', transaction.transactionType === 'voucher_purchase' ? 'fa-ticket' : transaction.transactionType === 'reward_redemption' || transaction.transactionType === 'redeem' ? 'fa-gift' : 'fa-coins']"></i>
                        {{ transaction.description }}
                      </div>
                      <div v-if="transaction.outletName" class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        <i class="fa-solid fa-store text-gray-400"></i>
                        <span>{{ transaction.outletName }}</span>
                      </div>
                      <div class="text-xs text-gray-400 mt-1">{{ transaction.createdAt }}</div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div :class="['text-sm font-bold', transaction.type === 'earned' ? 'text-green-600' : 'text-orange-600']">
                      {{ transaction.type === 'earned' ? '+' : '-' }}{{ transaction.pointAmountFormatted }} point
                    </div>
                    <div v-if="transaction.transactionAmount || transaction.transactionValue" class="text-xs text-gray-500 mt-1">
                      {{ transaction.transactionAmountFormatted || transaction.transactionValueFormatted }}
                    </div>
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
              <div v-if="!latestActivities || latestActivities.length === 0" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-clock text-4xl mb-2"></i>
                <p>Tidak ada aktivitas terbaru</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Gender & Age Distribution Charts (Priority 2) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
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

          <!-- Occupation Distribution Chart -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-briefcase text-indigo-500"></i>
                Distribusi Pekerjaan
              </h3>
            </div>
            <div class="h-64">
              <VueApexCharts
                v-if="occupationChartSeries && occupationChartSeries.length > 0 && occupationChartSeries.some(s => s > 0)"
                type="donut"
                height="300"
                :options="occupationChartOptions"
                :series="occupationChartSeries"
              />
              <div v-else class="flex items-center justify-center h-full text-gray-400">
                <div class="text-center">
                  <i class="fa-solid fa-briefcase text-4xl mb-2"></i>
                  <p>Tidak ada data</p>
                </div>
              </div>
            </div>
            <div class="mt-4 space-y-2 max-h-48 overflow-y-auto">
              <div v-for="item in props.occupationDistribution" :key="item.occupation" class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-indigo-200 transition-all duration-300">
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded-full shadow-sm" :style="{ backgroundColor: item.color }"></div>
                  <span class="font-medium text-gray-700 text-sm">{{ item.occupation }}</span>
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
              <div v-if="ageDistribution && ageDistribution.length > 0">
                <div v-for="item in ageDistribution" :key="item.age_group" class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-blue-200 transition-all duration-300">
                  <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded-full shadow-sm" :style="{ backgroundColor: item.color }"></div>
                    <span class="font-medium text-gray-700">{{ item.age_group_label || item.age_group }}</span>
            </div>
                  <div class="text-right">
                    <div class="font-bold text-gray-900">{{ formatNumber(item.count) }}</div>
                    <div class="text-xs text-gray-500">{{ item.percentage }}%</div>
          </div>
        </div>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-birthday-cake text-4xl mb-2"></i>
                <p>Data tidak tersedia</p>
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
              <div v-if="!props.purchasingPowerByAge || props.purchasingPowerByAge.length === 0" class="flex items-center justify-center h-full">
                <div class="text-center">
                  <i class="fa-solid fa-dollar-sign text-6xl text-gray-300 mb-4"></i>
                  <p class="text-gray-500">Data tidak tersedia</p>
                </div>
              </div>
              <template v-else-if="purchasingPowerChartSeries && purchasingPowerChartSeries.length > 0">
                <VueApexCharts
                  type="bar"
                  height="350"
                  :options="purchasingPowerChartOptions"
                  :series="purchasingPowerChartSeries"
                />
              </template>
              <div v-else class="flex items-center justify-center h-full">
                <div class="text-center">
                  <i class="fa-solid fa-exclamation-triangle text-4xl text-yellow-400 mb-4"></i>
                  <p class="text-gray-500">Data chart tidak valid</p>
                </div>
              </div>
            </div>
          
          <!-- Line Chart for Current Month -->
          <div class="mt-8">
            <div class="flex items-center justify-between mb-4">
              <h4 class="text-lg font-semibold text-gray-700 flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-blue-500"></i>
                Tren Bulan Ini (Harian)
              </h4>
            </div>
            <div class="h-80">
              <div v-if="!props.purchasingPowerByAgeThisMonth || props.purchasingPowerByAgeThisMonth.length === 0" class="flex items-center justify-center h-full">
                <div class="text-center">
                  <i class="fa-solid fa-chart-line text-6xl text-gray-300 mb-4"></i>
                  <p class="text-gray-500">Data tidak tersedia</p>
                </div>
              </div>
              <VueApexCharts
                v-else
                type="line"
                height="350"
                :options="purchasingPowerLineChartOptions"
                :series="purchasingPowerLineChartSeries"
              />
            </div>
          </div>
          
            <!-- Detail Cards Grid -->
            <div v-if="props.purchasingPowerByAge && props.purchasingPowerByAge.length > 0" class="mt-8">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                  v-for="item in props.purchasingPowerByAge"
                  :key="item.age_group"
                  class="bg-white rounded-xl shadow-md border border-gray-200 hover:shadow-lg transition-all duration-300 overflow-hidden"
                >
                  <!-- Card Header with Color Indicator -->
                  <div class="px-6 py-4 border-b border-gray-100" :style="{ backgroundColor: getAgeGroupColor(item.age_group) + '15' }">
                    <div class="flex items-center gap-3">
                      <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: getAgeGroupColor(item.age_group) }"></div>
                      <h4 class="font-bold text-gray-900 text-lg">{{ item.age_group_label || item.age_group }}</h4>
                    </div>
                  </div>
                  
                  <!-- Card Body -->
                  <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                      <div class="flex items-center gap-2">
                        <i class="fa-solid fa-money-bill-wave text-emerald-500"></i>
                        <span class="text-sm text-gray-600">Total Spending</span>
                      </div>
                      <span class="font-bold text-emerald-600 text-lg">{{ item.total_spending_formatted }}</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                      <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-xs text-gray-500 mb-1">Avg/Transaksi</div>
                        <div class="font-semibold text-gray-900">{{ item.avg_transaction_value_formatted }}</div>
                      </div>
                      <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-xs text-gray-500 mb-1">Total Transaksi</div>
                        <div class="font-semibold text-gray-900">{{ formatNumber(item.total_transactions) }}</div>
                      </div>
                    </div>
                    
                    <div class="pt-3 border-t border-gray-100">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <i class="fa-solid fa-users text-blue-500"></i>
                          <span class="text-sm text-gray-600">Total Customer</span>
                        </div>
                        <span class="font-bold text-blue-600">{{ formatNumber(item.total_customers) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="mt-8 text-center py-12 text-gray-500">
              <i class="fa-solid fa-dollar-sign text-5xl mb-4 text-gray-300"></i>
              <p class="text-lg">Data tidak tersedia</p>
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
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-2">
              <i class="fa-solid fa-trophy text-amber-500"></i>
              Top Spenders
            </h3>
            <div v-if="props.topSpendersDateRange" class="text-xs text-gray-500 mb-6 flex items-center gap-2">
              <i class="fa-solid fa-calendar-alt"></i>
              <span>Data dari {{ props.topSpendersDateRange.min_date_formatted }} sampai {{ props.topSpendersDateRange.max_date_formatted }}</span>
            </div>
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
                      <div 
                        @click="openMemberTransactionsModal(spender.memberId, spender.memberName, 'orders')"
                        class="font-semibold text-gray-900 cursor-pointer hover:text-amber-600 transition-colors"
                      >
                        {{ spender.memberName }}
                      </div>
                      <div class="text-sm text-gray-500">{{ spender.orderCount }} transaksi</div>
                      <div class="text-xs text-gray-400 mt-1">
                        <i class="fa-solid fa-envelope"></i> {{ spender.email }}
                      </div>
                      <div class="text-xs text-gray-400">
                        <i class="fa-solid fa-phone"></i> {{ spender.mobilePhone }}
                      </div>
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
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-2">
              <i class="fa-solid fa-fire text-orange-500"></i>
              Most Active Members
            </h3>
            <div v-if="props.mostActiveMembersDateRange" class="text-xs text-gray-500 mb-6 flex items-center gap-2">
              <i class="fa-solid fa-calendar-alt"></i>
              <span>Data dari {{ props.mostActiveMembersDateRange.min_date_formatted }} sampai {{ props.mostActiveMembersDateRange.max_date_formatted }}</span>
            </div>
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
                      <div 
                        @click="openMemberTransactionsModal(active.memberId, active.memberName, 'points')"
                        class="font-semibold text-gray-900 cursor-pointer hover:text-orange-600 transition-colors"
                      >
                        {{ active.memberName }}
                      </div>
                      <div class="text-sm text-gray-500">{{ active.transactionCount }} transaksi point</div>
                      <div class="text-xs text-gray-400 mt-1">
                        <i class="fa-solid fa-envelope"></i> {{ active.email }}
                      </div>
                      <div class="text-xs text-gray-400">
                        <i class="fa-solid fa-phone"></i> {{ active.mobilePhone }}
                      </div>
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

        <!-- Top 10 Points, Voucher Owners, and Point Redemptions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
          <!-- Top 10 Points -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-6">
              <i class="fa-solid fa-star text-yellow-500"></i>
              Top 10 Most Points
            </h3>
            <div v-if="props.top10Points.length === 0" class="text-center py-8 text-gray-400">
              <i class="fa-solid fa-star text-4xl mb-2"></i>
              <p class="text-sm">Tidak ada data</p>
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(member, index) in props.top10Points"
                :key="member.memberId"
                @click="openMemberTransactionsModal(member.memberId, member.memberName, 'points')"
                class="group p-4 bg-gradient-to-r from-yellow-50 to-white rounded-xl border border-yellow-100 hover:border-yellow-200 hover:shadow-lg transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-bold shadow-lg">
                      {{ index + 1 }}
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900 group-hover:text-yellow-600 transition-colors">{{ member.memberName }}</div>
                      <div class="text-xs text-gray-500">{{ member.memberId }}</div>
                      <div class="text-xs text-gray-400 mt-1">
                        <i class="fa-solid fa-envelope"></i> {{ member.email }}
                      </div>
                      <div class="text-xs text-gray-400">
                        <i class="fa-solid fa-phone"></i> {{ member.mobilePhone }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-bold text-yellow-600">{{ member.pointBalanceFormatted }}</div>
                    <div class="text-xs text-gray-500">point</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Top 10 Voucher Owners -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-6">
              <i class="fa-solid fa-ticket-alt text-purple-500"></i>
              Top 10 Voucher Owners
            </h3>
            <div v-if="props.top10VoucherOwners.length === 0" class="text-center py-8 text-gray-400">
              <i class="fa-solid fa-ticket-alt text-4xl mb-2"></i>
              <p class="text-sm">Tidak ada data</p>
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(member, index) in props.top10VoucherOwners"
                :key="member.memberId"
                @click="openMemberTransactionsModal(member.memberId, member.memberName, 'vouchers')"
                class="group p-4 bg-gradient-to-r from-purple-50 to-white rounded-xl border border-purple-100 hover:border-purple-200 hover:shadow-lg transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold shadow-lg">
                      {{ index + 1 }}
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900 group-hover:text-purple-600 transition-colors">{{ member.memberName }}</div>
                      <div class="text-xs text-gray-500">{{ member.memberId }}</div>
                      <div class="text-xs text-gray-400 mt-1">
                        <i class="fa-solid fa-envelope"></i> {{ member.email }}
                      </div>
                      <div class="text-xs text-gray-400">
                        <i class="fa-solid fa-phone"></i> {{ member.mobilePhone }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-bold text-purple-600">{{ member.voucherCountFormatted }}</div>
                    <div class="text-xs text-gray-500">voucher</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Top 10 Point Redemptions -->
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-6">
              <i class="fa-solid fa-exchange-alt text-red-500"></i>
              Top 10 Point Redemptions
            </h3>
            <div v-if="props.top10PointRedemptions.length === 0" class="text-center py-8 text-gray-400">
              <i class="fa-solid fa-exchange-alt text-4xl mb-2"></i>
              <p class="text-sm">Tidak ada data</p>
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="(member, index) in props.top10PointRedemptions"
                :key="member.memberId"
                @click="openMemberTransactionsModal(member.memberId, member.memberName, 'redemptions')"
                class="group p-4 bg-gradient-to-r from-red-50 to-white rounded-xl border border-red-100 hover:border-red-200 hover:shadow-lg transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center text-white font-bold shadow-lg">
                      {{ index + 1 }}
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900 group-hover:text-red-600 transition-colors">{{ member.memberName }}</div>
                      <div class="text-xs text-gray-500">{{ member.memberId }}</div>
                      <div class="text-xs text-gray-400 mt-1">
                        <i class="fa-solid fa-envelope"></i> {{ member.email }}
                      </div>
                      <div class="text-xs text-gray-400">
                        <i class="fa-solid fa-phone"></i> {{ member.mobilePhone }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-bold text-red-600">{{ member.totalRedeemedFormatted }}</div>
                    <div class="text-xs text-gray-500">{{ member.redemptionCountFormatted }}x redeem</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Member Favourite Picks -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-2">
              <i class="fa-solid fa-heart text-pink-500"></i>
              Member Favourite Picks
            </h3>
            <div class="text-xs text-gray-500 mb-6 flex items-center gap-2">
              <i class="fa-solid fa-calendar-alt"></i>
              <span>Top 10 menu yang paling sering di-order member dalam 90 hari terakhir</span>
            </div>
            
            <!-- Food and Beverages Side by Side -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
              <!-- Food Section -->
              <div>
                <div class="flex items-center gap-2 mb-4">
                  <i class="fa-solid fa-utensils text-orange-500"></i>
                  <h4 class="text-lg font-semibold text-gray-800">Food</h4>
                  <span class="text-xs text-gray-500 bg-orange-100 px-2 py-1 rounded-full">Top 10</span>
                </div>
                <div v-if="props.memberFavouritePicks?.food && props.memberFavouritePicks.food.length > 0" class="space-y-3">
                  <div
                    v-for="(item, index) in props.memberFavouritePicks.food"
                    :key="`food-${index}`"
                    class="group p-4 bg-gradient-to-r from-orange-50 to-white rounded-xl border border-orange-100 hover:border-orange-200 hover:shadow-lg transition-all duration-300"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3 flex-1">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white font-bold shadow-lg">
                          {{ index + 1 }}
                        </div>
                        <div class="flex-1">
                          <div class="font-semibold text-gray-900">{{ item.item_name }}</div>
                          <div class="text-sm text-gray-500 flex items-center gap-4 mt-1">
                            <span><i class="fa-solid fa-shopping-cart"></i> {{ item.total_quantity_formatted }}x</span>
                            <span><i class="fa-solid fa-receipt"></i> {{ item.order_count }} orders</span>
                            <span><i class="fa-solid fa-users"></i> {{ item.member_count }} members</span>
                          </div>
                        </div>
                      </div>
                      <div class="text-right ml-4">
                        <div class="text-lg font-bold text-orange-600">{{ item.total_revenue_formatted }}</div>
                        <div class="text-xs text-gray-500">Total Revenue</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-center py-8 text-gray-500">
                  <i class="fa-solid fa-utensils text-4xl mb-2 text-gray-300"></i>
                  <p class="text-sm">Tidak ada data food</p>
                </div>
              </div>
              
              <!-- Beverages Section -->
              <div>
                <div class="flex items-center gap-2 mb-4">
                  <i class="fa-solid fa-glass-water text-blue-500"></i>
                  <h4 class="text-lg font-semibold text-gray-800">Beverages</h4>
                  <span class="text-xs text-gray-500 bg-blue-100 px-2 py-1 rounded-full">Top 10</span>
                </div>
                <div v-if="props.memberFavouritePicks?.beverages && props.memberFavouritePicks.beverages.length > 0" class="space-y-3">
                  <div
                    v-for="(item, index) in props.memberFavouritePicks.beverages"
                    :key="`beverage-${index}`"
                    class="group p-4 bg-gradient-to-r from-blue-50 to-white rounded-xl border border-blue-100 hover:border-blue-200 hover:shadow-lg transition-all duration-300"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3 flex-1">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold shadow-lg">
                          {{ index + 1 }}
                        </div>
                        <div class="flex-1">
                          <div class="font-semibold text-gray-900">{{ item.item_name }}</div>
                          <div class="text-sm text-gray-500 flex items-center gap-4 mt-1">
                            <span><i class="fa-solid fa-shopping-cart"></i> {{ item.total_quantity_formatted }}x</span>
                            <span><i class="fa-solid fa-receipt"></i> {{ item.order_count }} orders</span>
                            <span><i class="fa-solid fa-users"></i> {{ item.member_count }} members</span>
                          </div>
                        </div>
                      </div>
                      <div class="text-right ml-4">
                        <div class="text-lg font-bold text-blue-600">{{ item.total_revenue_formatted }}</div>
                        <div class="text-xs text-gray-500">Total Revenue</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-center py-8 text-gray-500">
                  <i class="fa-solid fa-glass-water text-4xl mb-2 text-gray-300"></i>
                  <p class="text-sm">Tidak ada data beverages</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Active Vouchers -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-2">
              <i class="fa-solid fa-ticket text-purple-500"></i>
              Voucher Aktif
            </h3>
            <div class="text-xs text-gray-500 mb-6 flex items-center gap-2">
              <i class="fa-solid fa-info-circle"></i>
              <span>Daftar voucher aktif beserta statistik member yang memiliki dan sudah me-redeem voucher</span>
            </div>
            <div v-if="props.activeVouchers && props.activeVouchers.length > 0" class="space-y-4">
              <div
                v-for="(voucher, index) in props.activeVouchers"
                :key="voucher.id || index"
                class="group p-5 bg-gradient-to-r from-purple-50 to-white rounded-xl border border-purple-100 hover:border-purple-200 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold shadow-lg">
                        <i class="fa-solid fa-ticket"></i>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900 text-lg">{{ voucher.name }}</div>
                        <div class="text-sm text-gray-600 mt-1">{{ voucher.description }}</div>
                      </div>
                    </div>
                    <div class="mt-3 flex items-center gap-6 text-sm">
                      <div class="flex items-center gap-2">
                        <i class="fa-solid fa-coins text-yellow-500"></i>
                        <span class="text-gray-600">Point: <span class="font-semibold">{{ voucher.point_cost_formatted }}</span></span>
                      </div>
                      <div class="flex items-center gap-2">
                        <i class="fa-solid fa-tag text-green-500"></i>
                        <span class="text-gray-600">Diskon: <span class="font-semibold">{{ voucher.discount_display }}</span></span>
                      </div>
                      <div class="flex items-center gap-2">
                        <i class="fa-solid fa-calendar-times text-red-500"></i>
                        <span class="text-gray-600">Expired: <span class="font-semibold">{{ voucher.expired_at }}</span></span>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-4 pt-4 border-t border-purple-100 grid grid-cols-2 gap-4">
                  <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                    <div class="text-xs text-blue-600 mb-1 flex items-center gap-2">
                      <i class="fa-solid fa-users"></i>
                      <span>Member yang Memiliki</span>
                    </div>
                    <div class="text-xl font-bold text-blue-700">{{ voucher.member_count_formatted }}</div>
                  </div>
                  <div class="p-3 bg-green-50 rounded-lg border border-green-100">
                    <div class="text-xs text-green-600 mb-1 flex items-center gap-2">
                      <i class="fa-solid fa-check-circle"></i>
                      <span>Member yang Sudah Redeem</span>
                    </div>
                    <div class="text-xl font-bold text-green-700">{{ voucher.redeemed_count_formatted }}</div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12 text-gray-500">
              <i class="fa-solid fa-ticket text-5xl mb-4 text-gray-300"></i>
              <p class="text-lg">Tidak ada voucher aktif</p>
            </div>
          </div>
        </div>

        <!-- Active Challenges -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-2">
              <i class="fa-solid fa-trophy text-yellow-500"></i>
              Challenge Aktif
            </h3>
            <div class="text-xs text-gray-500 mb-6 flex items-center gap-2">
              <i class="fa-solid fa-info-circle"></i>
              <span>Daftar challenge yang sedang aktif dan statistik partisipasi member</span>
            </div>
            <div v-if="props.activeChallenges && props.activeChallenges.length > 0" class="space-y-4">
              <div
                v-for="(challenge, index) in props.activeChallenges"
                :key="challenge.id || index"
                class="group p-5 bg-gradient-to-r from-yellow-50 to-white rounded-xl border border-yellow-100 hover:border-yellow-200 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-start gap-3 mb-3">
                      <div v-if="challenge.image" class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                        <img :src="challenge.image" :alt="challenge.title" class="w-full h-full object-cover">
                      </div>
                      <div v-else class="w-16 h-16 rounded-lg bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                        <i class="fa-solid fa-trophy text-2xl"></i>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900 text-lg mb-1">{{ challenge.title }}</div>
                        <div class="text-sm text-gray-600 mb-2">{{ challenge.description || 'Tidak ada deskripsi' }}</div>
                        <div class="flex items-center gap-4 text-xs text-gray-500">
                          <div v-if="challenge.start_date" class="flex items-center gap-1">
                            <i class="fa-solid fa-calendar-alt"></i>
                            <span>Mulai: {{ challenge.start_date }}</span>
                          </div>
                          <div v-if="challenge.end_date" class="flex items-center gap-1">
                            <i class="fa-solid fa-calendar-times"></i>
                            <span>Selesai: {{ challenge.end_date }}</span>
                          </div>
                          <div v-if="challenge.points_reward > 0" class="flex items-center gap-1">
                            <i class="fa-solid fa-coins text-yellow-500"></i>
                            <span class="font-semibold">{{ formatNumber(challenge.points_reward) }} Point</span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                      <!-- In Progress -->
                      <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                        <div class="flex items-center gap-2 mb-2">
                          <i class="fa-solid fa-spinner text-blue-500"></i>
                          <span class="text-sm font-medium text-gray-700">In Progress</span>
                        </div>
                        <div class="text-2xl font-bold text-blue-600">{{ formatNumber(challenge.stats?.in_progress || 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Member sedang mengerjakan</div>
                      </div>
                      <!-- Completed -->
                      <div class="bg-green-50 rounded-lg p-4 border border-green-100">
                        <div class="flex items-center gap-2 mb-2">
                          <i class="fa-solid fa-check-circle text-green-500"></i>
                          <span class="text-sm font-medium text-gray-700">Selesai</span>
                        </div>
                        <div class="text-2xl font-bold text-green-600">{{ formatNumber(challenge.stats?.completed || 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Member menyelesaikan</div>
                      </div>
                      <!-- Redeemed -->
                      <div class="bg-purple-50 rounded-lg p-4 border border-purple-100">
                        <div class="flex items-center gap-2 mb-2">
                          <i class="fa-solid fa-gift text-purple-500"></i>
                          <span class="text-sm font-medium text-gray-700">Redeem Reward</span>
                        </div>
                        <div class="text-2xl font-bold text-purple-600">{{ formatNumber(challenge.stats?.redeemed || 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Member redeem reward</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-trophy text-4xl mb-2"></i>
              <p>Tidak ada challenge aktif</p>
            </div>
          </div>
        </div>

        <!-- Active Rewards -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-2">
              <i class="fa-solid fa-gift text-purple-500"></i>
              Reward Items Aktif
            </h3>
            <div class="text-xs text-gray-500 mb-6 flex items-center gap-2">
              <i class="fa-solid fa-info-circle"></i>
              <span>Daftar reward items yang sedang aktif dan jumlah redeem oleh member</span>
            </div>
            <div v-if="props.activeRewards && props.activeRewards.length > 0" class="space-y-4">
              <div
                v-for="(reward, index) in props.activeRewards"
                :key="reward.id || index"
                class="group p-5 bg-gradient-to-r from-purple-50 to-white rounded-xl border border-purple-100 hover:border-purple-200 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-start gap-4">
                  <div v-if="reward.item_image" class="w-20 h-20 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                    <img :src="reward.item_image" :alt="reward.item_name" class="w-full h-full object-cover">
                  </div>
                  <div v-else class="w-20 h-20 rounded-lg bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                    <i class="fa-solid fa-gift text-3xl"></i>
                  </div>
                  <div class="flex-1">
                    <div class="flex items-start justify-between mb-2">
                      <div>
                        <div class="font-semibold text-gray-900 text-lg mb-1">{{ reward.item_name }}</div>
                        <div class="text-sm text-gray-600 mb-2">
                          <span class="inline-flex items-center gap-1">
                            <i class="fa-solid fa-tag text-purple-500"></i>
                            <span>{{ reward.item_type || 'Item' }}</span>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                      <!-- Points Required -->
                      <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                        <div class="flex items-center gap-2 mb-1">
                          <i class="fa-solid fa-coins text-blue-500"></i>
                          <span class="text-sm font-medium text-gray-700">Point Required</span>
                        </div>
                        <div class="text-xl font-bold text-blue-600">{{ formatNumber(reward.points_required) }}</div>
                      </div>
                      <!-- Redemption Count -->
                      <div class="bg-green-50 rounded-lg p-3 border border-green-100">
                        <div class="flex items-center gap-2 mb-1">
                          <i class="fa-solid fa-check-circle text-green-500"></i>
                          <span class="text-sm font-medium text-gray-700">Total Redeem</span>
                        </div>
                        <div class="text-xl font-bold text-green-600">{{ formatNumber(reward.redemption_count) }}</div>
                        <div class="text-xs text-gray-500 mt-1">Kali di-redeem oleh member</div>
                      </div>
                    </div>
                    <div v-if="reward.serial_code" class="mt-3 text-xs text-gray-500">
                      <i class="fa-solid fa-barcode"></i>
                      <span>Serial Code: {{ reward.serial_code }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-gift text-4xl mb-2"></i>
              <p>Tidak ada reward aktif</p>
            </div>
          </div>
        </div>
                
        <!-- Member Segmentation -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2 mb-6">
              <i class="fa-solid fa-users-slash text-purple-500"></i>
              Segmentasi Member
            </h3>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Chart Section -->
              <div>
                <div class="h-64 mb-4">
                  <VueApexCharts
                    v-if="segmentationChartSeries && segmentationChartSeries.length > 0 && segmentationChartSeries.some(s => s > 0)"
                    type="donut"
                    height="300"
                    :options="segmentationChartOptions"
                    :series="segmentationChartSeries"
                  />
                  <div v-else class="flex items-center justify-center h-full text-gray-400">
                    <div class="text-center">
                      <i class="fa-solid fa-chart-pie text-4xl mb-2"></i>
                      <p>Tidak ada data</p>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Cards Section -->
              <div class="space-y-3">
                <div class="p-4 bg-gradient-to-r from-purple-50 to-white rounded-xl border border-purple-100 hover:shadow-md transition-all">
                  <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-purple-500 flex items-center justify-center text-white">
                      <i class="fa-solid fa-crown"></i>
                    </div>
                    <div class="flex-1">
                      <div class="text-xs text-gray-600 mb-1">VIP</div>
                      <div class="text-xl font-bold text-purple-600">{{ formatNumber(props.memberSegmentation?.vip || 0) }}</div>
                    </div>
                  </div>
                  <div class="text-xs text-gray-500 mt-2 pt-2 border-t border-purple-100">
                    Member aktif dengan tier Loyal/Elite dan memiliki point > 1.000
                  </div>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-green-50 to-white rounded-xl border border-green-100 hover:shadow-md transition-all">
                  <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-green-500 flex items-center justify-center text-white">
                      <i class="fa-solid fa-check-circle"></i>
                    </div>
                    <div class="flex-1">
                      <div class="text-xs text-gray-600 mb-1">Active</div>
                      <div class="text-xl font-bold text-green-600">{{ formatNumber(props.memberSegmentation?.active || 0) }}</div>
                    </div>
                  </div>
                  <div class="text-xs text-gray-500 mt-2 pt-2 border-t border-green-100">
                    Member aktif yang melakukan transaksi point dalam 30 hari terakhir
                  </div>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-blue-50 to-white rounded-xl border border-blue-100 hover:shadow-md transition-all">
                  <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center text-white">
                      <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div class="flex-1">
                      <div class="text-xs text-gray-600 mb-1">New</div>
                      <div class="text-xl font-bold text-blue-600">{{ formatNumber(props.memberSegmentation?.new || 0) }}</div>
                    </div>
                  </div>
                  <div class="text-xs text-gray-500 mt-2 pt-2 border-t border-blue-100">
                    Member yang baru mendaftar dalam 30 hari terakhir
                  </div>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-orange-50 to-white rounded-xl border border-orange-100 hover:shadow-md transition-all">
                  <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-orange-500 flex items-center justify-center text-white">
                      <i class="fa-solid fa-exclamation-triangle"></i>
                    </div>
                    <div class="flex-1">
                      <div class="text-xs text-gray-600 mb-1">At Risk</div>
                      <div class="text-xl font-bold text-orange-600">{{ formatNumber(props.memberSegmentation?.atRisk || 0) }}</div>
                    </div>
                  </div>
                  <div class="text-xs text-gray-500 mt-2 pt-2 border-t border-orange-100">
                    Member aktif dengan point ≤ 100 dan tidak login dalam 30 hari terakhir
                  </div>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-gray-50 to-white rounded-xl border border-gray-100 hover:shadow-md transition-all">
                  <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg bg-gray-500 flex items-center justify-center text-white">
                      <i class="fa-solid fa-moon"></i>
                    </div>
                    <div class="flex-1">
                      <div class="text-xs text-gray-600 mb-1">Dormant</div>
                      <div class="text-xl font-bold text-gray-600">{{ formatNumber(props.memberSegmentation?.dormant || 0) }}</div>
                    </div>
                  </div>
                  <div class="text-xs text-gray-500 mt-2 pt-2 border-t border-gray-100">
                    Member aktif yang tidak login dalam 90 hari terakhir
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Churn Analysis -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-user-xmark text-red-500"></i>
                Churn Analysis
              </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="p-4 bg-gradient-to-br from-red-50 to-red-100 rounded-xl border-2 border-red-200">
                <div class="text-sm font-medium text-red-700 mb-1">Churned Members</div>
                <div class="text-3xl font-bold text-red-800">{{ formatNumber(props.churnAnalysis?.churned || 0) }}</div>
                <div class="text-xs text-red-600 mt-1">No activity in last 90 days</div>
              </div>
              <div class="p-4 bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl border-2 border-orange-200">
                <div class="text-sm font-medium text-orange-700 mb-1">At Risk of Churn</div>
                <div class="text-2xl font-bold text-orange-800">{{ formatNumber(props.churnAnalysis?.atRiskChurn || 0) }}</div>
                <div class="text-xs text-orange-600 mt-1">No activity in last 30-60 days</div>
              </div>
              <div class="p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl border-2 border-green-200">
                <div class="text-sm font-medium text-green-700 mb-1">Retention Rate</div>
                <div class="text-3xl font-bold text-green-800">{{ props.churnAnalysis?.retentionRate || 0 }}%</div>
                <div class="text-xs text-green-600 mt-1">{{ formatNumber(props.churnAnalysis?.activeLast30Days || 0) }} / {{ formatNumber(props.churnAnalysis?.totalActive || 0) }} active</div>
              </div>
            </div>
          </div>
        </div>
          
        <!-- Comparison Data (MoM & YoY) -->
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
                      <span class="text-lg font-bold text-blue-900">{{ formatNumber(props.comparisonData?.monthOverMonth?.members?.current || 0) }}</span>
                      <span :class="['text-sm font-semibold', props.comparisonData?.monthOverMonth?.members?.growth >= 0 ? 'text-green-600' : 'text-red-600']">
                        <i :class="['fa-solid', props.comparisonData?.monthOverMonth?.members?.growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                        {{ Math.abs(props.comparisonData?.monthOverMonth?.members?.growth || 0) }}%
                      </span>
            </div>
                    <div class="text-xs text-blue-600 mt-1">vs {{ formatNumber(props.comparisonData?.monthOverMonth?.members?.previous || 0) }} last month</div>
                  </div>
                  <div>
                    <div class="text-sm text-blue-700 mb-1">Total Spending</div>
                    <div class="flex items-center justify-between">
                      <span class="text-lg font-bold text-blue-900">{{ props.comparisonData?.monthOverMonth?.spending?.currentFormatted || 'Rp 0' }}</span>
                      <span :class="['text-sm font-semibold', props.comparisonData?.monthOverMonth?.spending?.growth >= 0 ? 'text-green-600' : 'text-red-600']">
                        <i :class="['fa-solid', props.comparisonData?.monthOverMonth?.spending?.growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                        {{ Math.abs(props.comparisonData?.monthOverMonth?.spending?.growth || 0) }}%
                      </span>
                  </div>
                    <div class="text-xs text-blue-600 mt-1">vs {{ props.comparisonData?.monthOverMonth?.spending?.previousFormatted || 'Rp 0' }} last month</div>
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
                      <span class="text-lg font-bold text-purple-900">{{ formatNumber(props.comparisonData?.yearOverYear?.members?.current || 0) }}</span>
                      <span :class="['text-sm font-semibold', props.comparisonData?.yearOverYear?.members?.growth >= 0 ? 'text-green-600' : 'text-red-600']">
                        <i :class="['fa-solid', props.comparisonData?.yearOverYear?.members?.growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                        {{ Math.abs(props.comparisonData?.yearOverYear?.members?.growth || 0) }}%
                      </span>
                  </div>
                    <div class="text-xs text-purple-600 mt-1">vs {{ formatNumber(props.comparisonData?.yearOverYear?.members?.previous || 0) }} last year</div>
                </div>
                  <div>
                    <div class="text-sm text-purple-700 mb-1">Total Spending</div>
                    <div class="flex items-center justify-between">
                      <span class="text-lg font-bold text-purple-900">{{ props.comparisonData?.yearOverYear?.spending?.currentFormatted || 'Rp 0' }}</span>
                      <span :class="['text-sm font-semibold', props.comparisonData?.yearOverYear?.spending?.growth >= 0 ? 'text-green-600' : 'text-red-600']">
                        <i :class="['fa-solid', props.comparisonData?.yearOverYear?.spending?.growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
                        {{ Math.abs(props.comparisonData?.yearOverYear?.spending?.growth || 0) }}%
                      </span>
                    </div>
                    <div class="text-xs text-purple-600 mt-1">vs {{ props.comparisonData?.yearOverYear?.spending?.previousFormatted || 'Rp 0' }} last year</div>
                  </div>
              </div>
            </div>
          </div>
        </div>
      </div>

        <!-- Regional Breakdown with Charts -->
        <div class="mb-8">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-map-marker-alt text-green-500"></i>
                Breakdown per Outlet/Region
              </h3>
              <!-- Period Selector -->
              <div class="flex gap-2">
                <button
                  @click="regionalPeriod = 'currentMonth'"
                  :class="['px-4 py-2 rounded-lg transition-all text-sm font-medium', regionalPeriod === 'currentMonth' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
                >
                  Bulan Berjalan
                </button>
                <button
                  @click="regionalPeriod = 'last60Days'"
                  :class="['px-4 py-2 rounded-lg transition-all text-sm font-medium', regionalPeriod === 'last60Days' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
                >
                  60 Hari
                </button>
                <button
                  @click="regionalPeriod = 'last90Days'"
                  :class="['px-4 py-2 rounded-lg transition-all text-sm font-medium', regionalPeriod === 'last90Days' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
                >
                  90 Hari
                </button>
              </div>
            </div>
            
            <div v-if="!currentRegionalData || !currentRegionalData.regions || currentRegionalData.regions.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-map text-4xl mb-2"></i>
              <p>Tidak ada data regional</p>
            </div>
            <div v-else>
              <!-- Period Info -->
              <div class="mb-4 text-sm text-gray-600 text-center">
                <i class="fa-solid fa-calendar"></i>
                {{ currentRegionalData.period }} ({{ currentRegionalData.startDate }} - {{ currentRegionalData.endDate }})
              </div>
              
              <!-- Charts Grid -->
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Pie Chart - Region -->
                <div>
                  <h4 class="text-lg font-semibold text-gray-700 mb-4 text-center">Spending per Region</h4>
                  <VueApexCharts
                    v-if="regionalPieChartSeries && regionalPieChartSeries.length > 0"
                    type="pie"
                    height="350"
                    :options="regionalPieChartOptions"
                    :series="regionalPieChartSeries"
                  />
                  <div v-else class="flex items-center justify-center h-[350px] text-gray-400">
                    <p>Tidak ada data</p>
                  </div>
                </div>
                
                <!-- Bar Chart - Outlet -->
                <div>
                  <h4 class="text-lg font-semibold text-gray-700 mb-4 text-center">Spending per Outlet</h4>
                  <VueApexCharts
                    v-if="regionalBarChartSeries && regionalBarChartSeries.length > 0 && regionalBarChartSeries[0].data.length > 0"
                    type="bar"
                    height="350"
                    :options="regionalBarChartOptions"
                    :series="regionalBarChartSeries"
                  />
                  <div v-else class="flex items-center justify-center h-[350px] text-gray-400">
                    <p>Tidak ada data</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Contribution by Outlet Modal -->
    <div v-if="showContributionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="closeContributionModal">
      <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div class="bg-gradient-to-r from-purple-600 to-blue-600 p-6 text-white">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-2xl font-bold">Kontribusi Member per Outlet</h3>
              <p class="text-sm opacity-90 mt-1">
                {{ contributionModalPeriod === 'today' ? 'Hari Ini' : contributionModalPeriod === 'month' ? 'Bulan Ini' : 'Tahun Ini' }}
              </p>
            </div>
            <button @click="closeContributionModal" class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all">
              <i class="fa-solid fa-times"></i>
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="loadingContribution" class="text-center py-12">
            <i class="fa-solid fa-spinner fa-spin text-4xl text-purple-500 mb-4"></i>
            <p class="text-gray-600">Memuat data...</p>
        </div>
          
          <div v-else-if="contributionByOutlet.length === 0" class="text-center py-12">
            <i class="fa-solid fa-store text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-600">Tidak ada data</p>
          </div>
          
          <div v-else class="space-y-3">
            <div
              v-for="(outlet, index) in contributionByOutlet"
              :key="outlet.kode_outlet || index"
              class="p-4 bg-gradient-to-r from-gray-50 to-white rounded-xl border border-gray-200 hover:border-purple-300 hover:shadow-lg transition-all duration-300"
            >
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-store text-purple-600"></i>
                  </div>
                  <div>
                    <div class="font-semibold text-gray-900">{{ outlet.outlet_name }}</div>
                    <div class="text-xs text-gray-500">{{ outlet.kode_outlet }}</div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-lg font-bold text-purple-600">{{ outlet.contribution_formatted }}</div>
                  <div class="text-xs text-gray-500">Kontribusi</div>
      </div>
    </div>

              <div class="grid grid-cols-2 gap-4 mt-3 pt-3 border-t border-gray-200">
                <div>
                  <div class="text-xs text-gray-500 mb-1">Total Revenue</div>
                  <div class="font-semibold text-gray-900">{{ outlet.total_revenue_formatted }}</div>
                  <div class="text-xs text-gray-400 mt-1">{{ outlet.total_orders }} transaksi</div>
                </div>
                <div>
                  <div class="text-xs text-gray-500 mb-1">Member Revenue</div>
                  <div class="font-semibold text-green-600">{{ outlet.member_revenue_formatted }}</div>
                  <div class="text-xs text-gray-400 mt-1">{{ outlet.member_orders }} transaksi</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Member Transactions Modal -->
    <div v-if="showMemberTransactionsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="closeMemberTransactionsModal">
      <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-6 text-white">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-2xl font-bold">Detail Transaksi</h3>
              <p class="text-sm opacity-90 mt-1">
                {{ memberTransactionsData.memberName }}
              </p>
              <p class="text-xs opacity-75 mt-1">
                <span v-if="memberTransactionsData.type === 'orders'">Order History</span>
                <span v-else-if="memberTransactionsData.type === 'points'">Point Transactions</span>
                <span v-else-if="memberTransactionsData.type === 'vouchers'">Voucher List</span>
                <span v-else-if="memberTransactionsData.type === 'redemptions'">Point Redemptions</span>
                <span v-else>Transaction History</span>
              </p>
            </div>
            <button @click="closeMemberTransactionsModal" class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all">
              <i class="fa-solid fa-times"></i>
            </button>
          </div>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="loadingMemberTransactions" class="text-center py-12">
            <i class="fa-solid fa-spinner fa-spin text-4xl text-indigo-500 mb-4"></i>
            <p class="text-gray-600">Memuat data transaksi...</p>
          </div>
          
          <div v-else-if="memberTransactions.length === 0" class="text-center py-12">
            <i class="fa-solid fa-receipt text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-600">Tidak ada transaksi</p>
          </div>
          
          <div v-else class="space-y-4">
            <!-- Orders Type -->
            <div v-if="memberTransactionsData.type === 'orders'">
              <div
                v-for="(transaction, index) in memberTransactions"
                :key="transaction.id || index"
                class="p-5 bg-gradient-to-r from-gray-50 to-white rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                        <i class="fa-solid fa-shopping-cart text-indigo-600"></i>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900">{{ transaction.order_number }}</div>
                        <div class="text-sm text-gray-500">{{ transaction.outlet_name }}</div>
                      </div>
                    </div>
                    <div class="text-xs text-gray-400 mt-2">
                      <i class="fa-solid fa-clock"></i> {{ transaction.created_at }}
                    </div>
                  </div>
                  <div class="text-right ml-4">
                    <div class="text-xl font-bold text-indigo-600">{{ transaction.grand_total_formatted }}</div>
                    <button
                      v-if="transaction.items && transaction.items.length > 0"
                      @click="toggleTransactionDetails(transaction.id || index)"
                      class="mt-2 text-xs text-indigo-600 hover:text-indigo-700 flex items-center gap-1 transition-colors"
                    >
                      <i :class="['fa-solid transition-transform', isTransactionExpanded(transaction.id || index) ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                      <span>{{ isTransactionExpanded(transaction.id || index) ? 'Sembunyikan' : 'Lihat Detail' }}</span>
                    </button>
                  </div>
                </div>
                
                <!-- Order Items - Expandable -->
                <div 
                  v-if="transaction.items && transaction.items.length > 0"
                  class="overflow-hidden transition-all duration-300"
                  :class="isTransactionExpanded(transaction.id || index) ? 'max-h-[1000px] mt-4 pt-4 border-t border-gray-200' : 'max-h-0'"
                >
                  <div class="text-xs font-semibold text-gray-600 mb-2">Detail Item:</div>
                  <div class="space-y-2">
                    <div
                      v-for="(item, itemIndex) in transaction.items"
                      :key="itemIndex"
                      class="text-sm bg-white rounded-lg p-3 border border-gray-100"
                    >
                      <div class="flex items-center justify-between mb-2">
                        <div class="flex-1">
                          <div class="font-medium text-gray-700">{{ item.item_name }}</div>
                          <div class="text-xs text-gray-500">{{ item.quantity }}x {{ item.price_formatted }}</div>
                        </div>
                        <div class="font-semibold text-gray-900">{{ item.subtotal_formatted }}</div>
                      </div>
                      
                      <!-- Modifiers -->
                      <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-2 pt-2 border-t border-gray-100">
                        <div class="text-xs font-semibold text-gray-600 mb-1">Modifier:</div>
                        <div class="space-y-1">
                          <div
                            v-for="(modifier, modIndex) in item.modifiers"
                            :key="modIndex"
                            class="text-xs text-gray-600 pl-2"
                          >
                            <span class="font-medium">{{ modifier.name }}:</span>
                            <span class="text-gray-500">{{ modifier.options }}</span>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Notes -->
                      <div v-if="item.notes" class="mt-2 pt-2 border-t border-gray-100">
                        <div class="text-xs font-semibold text-gray-600 mb-1">Catatan:</div>
                        <div class="text-xs text-gray-600 pl-2 italic">{{ item.notes }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Points Type -->
            <div v-else-if="memberTransactionsData.type === 'points'">
              <div
                v-for="(transaction, index) in memberTransactions"
                :key="transaction.id || index"
                class="p-5 bg-gradient-to-r from-gray-50 to-white rounded-xl border border-gray-200 hover:border-purple-300 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', transaction.type === 'earned' ? 'bg-green-100' : 'bg-red-100']">
                        <i :class="['fa-solid', transaction.type === 'earned' ? 'fa-arrow-up text-green-600' : 'fa-arrow-down text-red-600']"></i>
                      </div>
                      <div>
                        <div class="font-semibold text-gray-900">{{ transaction.description }}</div>
                        <div class="text-sm text-gray-500">{{ transaction.outletName }}</div>
                      </div>
                    </div>
                    <div class="text-xs text-gray-400 mt-2">
                      <i class="fa-solid fa-clock"></i> {{ transaction.created_at }}
                    </div>
                  </div>
                  <div class="text-right">
                    <div :class="['text-xl font-bold', transaction.type === 'earned' ? 'text-green-600' : 'text-red-600']">
                      {{ transaction.type === 'earned' ? '+' : '-' }}{{ transaction.pointAmountFormatted }} point
                    </div>
                    <div v-if="transaction.transactionAmountFormatted !== '-'" class="text-sm text-gray-500 mt-1">
                      {{ transaction.transactionAmountFormatted }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Vouchers Type -->
            <div v-else-if="memberTransactionsData.type === 'vouchers'">
              <div
                v-for="(voucher, index) in memberTransactions"
                :key="voucher.id || index"
                class="p-5 bg-gradient-to-r from-purple-50 to-white rounded-xl border border-purple-200 hover:border-purple-300 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                        <i class="fa-solid fa-ticket-alt text-purple-600"></i>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900">{{ voucher.voucherName }}</div>
                        <div v-if="voucher.description" class="text-sm text-gray-500 mt-1">{{ voucher.description }}</div>
                        <div class="flex items-center gap-4 mt-2 text-xs">
                          <span class="text-gray-500">
                            <i class="fa-solid fa-barcode"></i> {{ voucher.serialCode }}
                          </span>
                          <span :class="[
                            'px-2 py-1 rounded-full text-xs font-medium',
                            voucher.status === 'active' ? 'bg-green-100 text-green-700' :
                            voucher.status === 'used' ? 'bg-gray-100 text-gray-700' :
                            'bg-red-100 text-red-700'
                          ]">
                            {{ voucher.statusText }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-3 pt-3 border-t border-purple-100">
                      <div>
                        <div class="text-xs text-gray-500 mb-1">Discount</div>
                        <div class="font-semibold text-purple-600">{{ voucher.discountFormatted }}</div>
                      </div>
                      <div>
                        <div class="text-xs text-gray-500 mb-1">Min. Purchase</div>
                        <div class="font-semibold text-gray-900">{{ voucher.minimumPurchaseFormatted }}</div>
                      </div>
                    </div>
                    <div class="text-xs text-gray-400 mt-2">
                      <i class="fa-solid fa-calendar"></i> 
                      <span v-if="voucher.expiresAt !== '-'">Expires: {{ voucher.expiresAt }}</span>
                      <span v-else>No expiration</span>
                      <span v-if="voucher.usedAt !== '-'" class="ml-3">
                        <i class="fa-solid fa-check-circle"></i> Used: {{ voucher.usedAt }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Redemptions Type -->
            <div v-else-if="memberTransactionsData.type === 'redemptions'">
              <div
                v-for="(redemption, index) in memberTransactions"
                :key="redemption.id || index"
                class="p-5 bg-gradient-to-r from-red-50 to-white rounded-xl border border-red-200 hover:border-red-300 hover:shadow-lg transition-all duration-300"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                        <i class="fa-solid fa-exchange-alt text-red-600"></i>
                      </div>
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900">{{ redemption.redemptionType }}</div>
                        <div class="text-sm text-gray-500 mt-1">{{ redemption.redemptionDetail }}</div>
                        <div class="text-sm text-gray-600 mt-2">
                          <i class="fa-solid fa-store"></i> {{ redemption.outletName }}
                          <span v-if="redemption.outletCode !== '-'" class="text-xs text-gray-400 ml-2">({{ redemption.outletCode }})</span>
                        </div>
                      </div>
                    </div>
                    <div class="text-xs text-gray-400 mt-2">
                      <i class="fa-solid fa-clock"></i> {{ redemption.createdAt }}
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-xl font-bold text-red-600">
                      -{{ redemption.pointAmountFormatted }} point
                    </div>
                    <div v-if="redemption.transactionAmountFormatted !== '-'" class="text-sm text-gray-500 mt-1">
                      {{ redemption.transactionAmountFormatted }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 

<style scoped>
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
