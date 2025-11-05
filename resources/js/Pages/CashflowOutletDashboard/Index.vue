<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    dashboardData: Object,
    comparisonData: Object,
    outlets: Array,
    filters: Object
});

const loading = ref(false);
const showDetailModal = ref(false);
const detailType = ref(null);
const detailCategory = ref(null);
const detailData = ref([]);
const detailLoading = ref(false);

// Filter states
const filters = ref({
    date_from: props.filters?.date_from || new Date().toISOString().split('T')[0],
    date_to: props.filters?.date_to || new Date().toISOString().split('T')[0],
    outlet_id: props.filters?.outlet_id || 'ALL',
    compare_mode: props.filters?.compare_mode || 'none'
});

// Expandable summary rows
const expandedOutlets = ref(new Set());

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

// Get color class for cash out percentage (semakin besar semakin merah)
const getCashOutPercentageColor = (percentage) => {
    if (percentage === undefined || percentage === null) return 'text-gray-600';
    
    if (percentage >= 90) {
        return 'text-red-700'; // Sangat merah (buruk)
    } else if (percentage >= 75) {
        return 'text-red-600'; // Merah (perhatian tinggi)
    } else if (percentage >= 60) {
        return 'text-orange-600'; // Orange-merah (perhatian)
    } else if (percentage >= 50) {
        return 'text-yellow-600'; // Kuning (warning)
    } else if (percentage >= 40) {
        return 'text-yellow-500'; // Kuning muda (hati-hati)
    } else {
        return 'text-green-600'; // Hijau (baik)
    }
};

// Apply filters
const applyFilters = () => {
    loading.value = true;
    const routeName = typeof route === 'function' ? route('cashflow-outlet-dashboard.index') : '/cashflow-outlet-dashboard';
    router.get(routeName, filters.value, {
        preserveState: true,
        onFinish: () => {
            loading.value = false;
        }
    });
};

// Toggle outlet expansion
const toggleOutlet = (outletId) => {
    if (expandedOutlets.value.has(outletId)) {
        expandedOutlets.value.delete(outletId);
    } else {
        expandedOutlets.value.add(outletId);
    }
};

// Show detail modal
const showDetail = async (type, category = null) => {
    detailType.value = type;
    detailCategory.value = category;
    showDetailModal.value = true;
    detailLoading.value = true;
    detailData.value = [];

    try {
        const params = {
            type: type,
            date_from: filters.value.date_from,
            date_to: filters.value.date_to,
            outlet_id: filters.value.outlet_id
        };
        
        if (category) {
            params.category = category;
        }

        const routeName = typeof route === 'function' ? route('cashflow-outlet-dashboard.detail') : '/cashflow-outlet-dashboard/detail';
        const response = await axios.get(routeName, { params });
        
        // Handle different response structures
        if (response.data.category === 'all' && typeof response.data.data === 'object') {
            // If category is 'all', data is an object with multiple categories
            detailData.value = response.data.data;
        } else {
            // If category is specific, data is an array
            detailData.value = response.data.data || [];
        }
    } catch (error) {
        console.error('Error fetching detail:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.error || 'Failed to load detail data'
        });
    } finally {
        detailLoading.value = false;
    }
};

// Overview metrics
const overview = computed(() => props.dashboardData?.overview || {});

// Cashflow Trend Chart
const cashflowTrendSeries = computed(() => {
    if (!props.dashboardData?.cashflowTrend) return [];
    
    const trend = props.dashboardData.cashflowTrend;
    return [
        {
            name: 'Cash In',
            type: 'line',
            data: trend.map(item => item.cash_in || 0)
        },
        {
            name: 'Cash Out',
            type: 'line',
            data: trend.map(item => item.cash_out || 0)
        },
        {
            name: 'Net Cashflow',
            type: 'line',
            data: trend.map(item => item.net_cashflow || 0)
        }
    ];
});

const cashflowTrendOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 400,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: props.dashboardData?.cashflowTrend?.map(item => {
            return new Date(item.date).toLocaleDateString('id-ID', { 
                day: '2-digit', 
                month: 'short' 
            });
        }) || [],
        title: { text: 'Tanggal' },
        labels: { 
            rotate: -45,
            style: { fontSize: '12px' }
        }
    },
    yaxis: {
        title: { text: 'Amount (Rp)' },
        labels: {
            formatter: (value) => formatCurrency(value)
        }
    },
    colors: ['#10b981', '#ef4444', '#3b82f6'], // Green, Red, Blue
    stroke: {
        width: [3, 3, 3],
        curve: 'smooth'
    },
    legend: {
        position: 'top',
        fontSize: '14px'
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: true,
        intersect: false,
        custom: function({ series, seriesIndex, dataPointIndex, w }) {
            const date = w.globals.labels[dataPointIndex];
            const trend = props.dashboardData?.cashflowTrend;
            const dayData = trend[dataPointIndex];
            
            if (!dayData) return '';
            
            return `
                <div class="px-3 py-2 bg-white border border-gray-200 rounded-lg shadow-lg">
                    <div class="text-sm font-semibold text-gray-900 mb-2">${date}</div>
                    <div class="text-sm space-y-1">
                        <div><span class="font-medium">Cash In:</span> ${formatCurrency(dayData.cash_in || 0)}</div>
                        <div><span class="font-medium">Cash Out:</span> ${formatCurrency(dayData.cash_out || 0)}</div>
                        <div><span class="font-medium">Net Cashflow:</span> ${formatCurrency(dayData.net_cashflow || 0)}</div>
                    </div>
                </div>
            `;
        }
    }
}));

// Cash In vs Cash Out Comparison Chart
const comparisonSeries = computed(() => {
    if (!props.dashboardData?.cashflowTrend) return [];
    
    const trend = props.dashboardData.cashflowTrend;
    return [
        {
            name: 'Cash In',
            data: trend.map(item => item.cash_in || 0)
        },
        {
            name: 'Cash Out',
            data: trend.map(item => item.cash_out || 0)
        }
    ];
});

const comparisonOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 400,
        stacked: false,
        toolbar: { show: true }
    },
    xaxis: {
        categories: props.dashboardData?.cashflowTrend?.map(item => {
            return new Date(item.date).toLocaleDateString('id-ID', { 
                day: '2-digit', 
                month: 'short' 
            });
        }) || [],
        title: { text: 'Tanggal' },
        labels: { 
            rotate: -45,
            style: { fontSize: '12px' }
        }
    },
    yaxis: {
        title: { text: 'Amount (Rp)' },
        labels: {
            formatter: (value) => formatCurrency(value)
        }
    },
    colors: ['#10b981', '#ef4444'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%'
        }
    },
    legend: {
        position: 'top'
    },
    tooltip: {
        shared: true,
        intersect: false
    }
}));

// Cash Out Breakdown Pie Chart
const cashOutBreakdownSeries = computed(() => {
    if (!props.dashboardData?.cashOutBreakdown) return [];
    return props.dashboardData.cashOutBreakdown.map(item => item.value);
});

const cashOutBreakdownOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: 350
    },
    labels: props.dashboardData?.cashOutBreakdown?.map(item => item.name) || [],
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'], // Blue for GR, Green for Retail Food, Orange for Retail Non Food, Red for Payment
    legend: {
        position: 'bottom'
    },
    tooltip: {
        y: {
            formatter: (value) => formatCurrency(value)
        }
    },
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        formatter: () => formatCurrency(
                            props.dashboardData?.cashOutBreakdown?.reduce((sum, item) => sum + item.value, 0) || 0
                        )
                    }
                }
            }
        }
    }
}));

// Outlet Summary
const outletSummary = computed(() => props.dashboardData?.outletSummary || []);

// Comparison data
const hasComparison = computed(() => props.comparisonData !== null);

watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        filters.value = { ...newFilters };
    }
}, { deep: true });
</script>

<template>
    <AppLayout>
        <Head title="Cashflow Outlet Dashboard" />

        <div class="p-6 space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Cashflow Outlet Dashboard</h1>
                    <p class="text-gray-600 mt-1">Monitor cashflow per outlet dengan analisis lengkap</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                        <select
                            v-model="filters.outlet_id"
                            @change="applyFilters"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="ALL">All Outlets</option>
                            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                                {{ outlet.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input
                            type="date"
                            v-model="filters.date_from"
                            @change="applyFilters"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input
                            type="date"
                            v-model="filters.date_to"
                            @change="applyFilters"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Compare</label>
                        <select
                            v-model="filters.compare_mode"
                            @change="applyFilters"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="none">No Comparison</option>
                            <option value="previous_period">Previous Period</option>
                            <option value="previous_year">Previous Year</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button
                            @click="applyFilters"
                            :disabled="loading"
                            class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            <i class="fas fa-sync-alt mr-2"></i>
                            {{ loading ? 'Loading...' : 'Refresh' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Cash In</p>
                            <p class="text-2xl font-bold text-green-600 mt-1">
                                {{ formatCurrency(overview.total_cash_in || 0) }}
                            </p>
                            <button
                                @click="showDetail('cash_in')"
                                class="text-xs text-blue-600 hover:text-blue-800 mt-2"
                            >
                                <i class="fas fa-eye mr-1"></i> View Detail
                            </button>
                        </div>
                        <div class="bg-green-100 rounded-full p-3">
                            <i class="fas fa-arrow-down text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Cash Out</p>
                            <p class="text-2xl font-bold text-red-600 mt-1">
                                {{ formatCurrency(overview.total_cash_out || 0) }}
                            </p>
                            <p 
                                v-if="overview.cash_out_percentage !== undefined"
                                class="text-sm font-semibold mt-1"
                                :class="getCashOutPercentageColor(overview.cash_out_percentage)"
                            >
                                {{ overview.cash_out_percentage.toFixed(1) }}% dari Cash In
                            </p>
                            <button
                                @click="showDetail('cash_out')"
                                class="text-xs text-blue-600 hover:text-blue-800 mt-2"
                            >
                                <i class="fas fa-eye mr-1"></i> View Detail
                            </button>
                        </div>
                        <div class="bg-red-100 rounded-full p-3">
                            <i class="fas fa-arrow-up text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Net Cashflow</p>
                            <p 
                                class="text-2xl font-bold mt-1"
                                :class="(overview.net_cashflow || 0) >= 0 ? 'text-green-600' : 'text-red-600'"
                            >
                                {{ formatCurrency(overview.net_cashflow || 0) }}
                            </p>
                        </div>
                        <div 
                            class="rounded-full p-3"
                            :class="(overview.net_cashflow || 0) >= 0 ? 'bg-green-100' : 'bg-red-100'"
                        >
                            <i 
                                class="fas fa-chart-line text-xl"
                                :class="(overview.net_cashflow || 0) >= 0 ? 'text-green-600' : 'text-red-600'"
                            ></i>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Comparison Cards (if comparison enabled) -->
            <div v-if="hasComparison" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div 
                    v-for="(metric, index) in [
                        { label: 'Cash In', key: 'total_cash_in', color: 'green' },
                        { label: 'Cash Out', key: 'total_cash_out', color: 'red' },
                        { label: 'Net Cashflow', key: 'net_cashflow', color: 'blue' }
                    ]"
                    :key="index"
                    class="bg-white rounded-lg shadow p-4"
                >
                    <p class="text-sm font-medium text-gray-600">{{ metric.label }}</p>
                    <div class="mt-2">
                        <p class="text-lg font-bold">
                            Current: {{ formatCurrency(overview[metric.key] || 0) }}
                        </p>
                        <p class="text-sm text-gray-600">
                            Compare: {{ formatCurrency(comparisonData?.overview?.[metric.key] || 0) }}
                        </p>
                        <p 
                            class="text-xs mt-1"
                            :class="(overview[metric.key] || 0) >= (comparisonData?.overview?.[metric.key] || 0) ? 'text-green-600' : 'text-red-600'"
                        >
                            {{ ((overview[metric.key] || 0) - (comparisonData?.overview?.[metric.key] || 0)) >= 0 ? '+' : '' }}
                            {{ formatCurrency((overview[metric.key] || 0) - (comparisonData?.overview?.[metric.key] || 0)) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1: Cashflow Trend -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Cashflow Trend</h2>
                <VueApexCharts
                    type="line"
                    height="400"
                    :options="cashflowTrendOptions"
                    :series="cashflowTrendSeries"
                />
            </div>

            <!-- Charts Row 2: Comparison & Breakdown -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Cash In vs Cash Out</h2>
                    <VueApexCharts
                        type="bar"
                        height="400"
                        :options="comparisonOptions"
                        :series="comparisonSeries"
                    />
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Cash Out Breakdown</h2>
                    <VueApexCharts
                        type="donut"
                        height="350"
                        :options="cashOutBreakdownOptions"
                        :series="cashOutBreakdownSeries"
                    />
                    <div class="mt-4 space-y-2">
                        <button
                            v-for="item in dashboardData?.cashOutBreakdown"
                            :key="item.name"
                            @click="showDetail('cash_out', item.name === 'GR' ? 'gr' : item.name.toLowerCase().replace(/ /g, '_'))"
                            class="block w-full text-left px-3 py-2 bg-gray-50 rounded hover:bg-gray-100 text-sm"
                        >
                            <span class="font-medium">{{ item.name }}:</span>
                            <span class="ml-2">{{ formatCurrency(item.value) }}</span>
                            <span class="text-gray-500 ml-2">({{ item.percentage ? item.percentage.toFixed(1) : 0 }}%)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Outlet Summary Table (if multiple outlets) -->
            <div v-if="outletSummary.length > 0" class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Outlet Summary</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cash In</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cash Out</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Cashflow</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Closing Balance</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="outlet in outletSummary" :key="outlet.outlet_id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ outlet.outlet_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">
                                    {{ formatCurrency(outlet.cash_in) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600">
                                    {{ formatCurrency(outlet.cash_out) }}
                                </td>
                                <td 
                                    class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                    :class="outlet.net_cashflow >= 0 ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ formatCurrency(outlet.net_cashflow) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-blue-600">
                                    {{ formatCurrency(outlet.closing_balance) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                    <button
                                        @click="toggleOutlet(outlet.outlet_id)"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        <i 
                                            :class="expandedOutlets.has(outlet.outlet_id) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"
                                        ></i>
                                    </button>
                                </td>
                            </tr>
                            <!-- Expanded Detail Row -->
                            <template v-for="outlet in outletSummary" :key="outlet.outlet_id">
                                <tr v-if="expandedOutlets.has(outlet.outlet_id)">
                                    <td colspan="6" class="px-6 py-4 bg-gray-50">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 mb-2">Cash In Details:</p>
                                                <ul class="text-sm text-gray-600 space-y-1">
                                                    <li>Total Sales: {{ formatCurrency(outlet.cash_in) }}</li>
                                                </ul>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 mb-2">Cash Out Details:</p>
                                                <ul class="text-sm text-gray-600 space-y-1">
                                                    <li>Retail Food</li>
                                                    <li>Retail Non Food</li>
                                                    <li>Payment</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div
            v-if="showDetailModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showDetailModal = false"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full m-4 max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">
                        Detail {{ detailType === 'cash_in' ? 'Cash In' : 'Cash Out' }}
                        <span v-if="detailCategory" class="text-sm font-normal text-gray-600">
                            - {{ detailCategory.replace('_', ' ').toUpperCase() }}
                        </span>
                    </h3>
                    <button
                        @click="showDetailModal = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6">
                    <div v-if="detailLoading" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i>
                        <p class="mt-2 text-gray-600">Loading...</p>
                    </div>

                    <div v-else-if="!detailData || (Array.isArray(detailData) && detailData.length === 0) || (typeof detailData === 'object' && !Array.isArray(detailData) && Object.keys(detailData).length === 0)" class="text-center py-8">
                        <p class="text-gray-600">No data available</p>
                    </div>

                    <!-- All Categories View (when category is 'all') -->
                    <div v-else-if="detailCategory === null && typeof detailData === 'object' && !Array.isArray(detailData)" class="space-y-6">
                        <div v-for="(categoryData, categoryKey) in detailData" :key="categoryKey" class="border rounded-lg">
                            <div class="bg-gray-50 px-4 py-3 border-b">
                                <h4 class="font-semibold text-gray-900 capitalize">{{ categoryKey.replace('_', ' ') }}</h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-if="!categoryData || categoryData.length === 0">
                                            <td colspan="5" class="px-4 py-3 text-center text-sm text-gray-500">No data available</td>
                                        </tr>
                                        <tr v-for="(item, index) in categoryData" :key="index">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ new Date(item.date || item.receive_date || item.sale_date).toLocaleDateString('id-ID') }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ item.number || '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ item.outlet_name || '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span 
                                                    v-if="item.source"
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                    :class="item.source === 'GR' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'"
                                                >
                                                    {{ item.source }}
                                                </span>
                                                <span v-else class="text-gray-400 text-xs">-</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-red-600">
                                                {{ formatCurrency(item.total || 0) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="4" class="px-4 py-3 text-right text-sm font-bold text-gray-900">Total:</td>
                                            <td class="px-4 py-3 text-right text-sm font-bold text-red-600">
                                                {{ formatCurrency(
                                                    (categoryData || []).reduce((sum, item) => {
                                                        const total = parseFloat(item.total) || 0;
                                                        return sum + total;
                                                    }, 0)
                                                ) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Single Category View (when category is specific) -->
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th v-if="detailType === 'cash_in'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transactions</th>
                                    <th v-if="detailType === 'cash_out'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                                    <th v-if="detailType === 'cash_in'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                                    <th v-if="detailType === 'cash_in'" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                    <th v-if="detailType === 'cash_out'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                                    <th v-if="detailType === 'cash_out'" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                                    <th v-if="detailType === 'cash_out'" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(item, index) in detailData" :key="index">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ new Date(item.created_at || item.date || item.receive_date || item.sale_date).toLocaleDateString('id-ID') }}
                                    </td>
                                    <td v-if="detailType === 'cash_in'" class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.transaction_count || 0 }} transaksi
                                    </td>
                                    <td v-if="detailType === 'cash_out'" class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.number || item.payment_number || '-' }}
                                    </td>
                                    <td v-if="detailType === 'cash_in'" class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.outlet_name || item.kode_outlet || '-' }}
                                    </td>
                                    <td v-if="detailType === 'cash_in'" class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                        {{ formatCurrency(item.revenue || item.grand_total || 0) }}
                                    </td>
                                    <td v-if="detailType === 'cash_out'" class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.outlet_name || '-' }}
                                    </td>
                                    <td v-if="detailType === 'cash_out'" class="px-4 py-3 whitespace-nowrap">
                                        <span 
                                            v-if="item.source"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="item.source === 'GR' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'"
                                        >
                                            {{ item.source }}
                                        </span>
                                        <span v-else class="text-gray-400 text-xs">-</span>
                                    </td>
                                    <td v-if="detailType === 'cash_out'" class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-red-600">
                                        {{ formatCurrency(item.total || item.amount || 0) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td :colspan="detailType === 'cash_in' ? 4 : 5" class="px-4 py-3 text-right text-sm font-bold text-gray-900">
                                        Total: {{ formatCurrency(
                                            detailData.reduce((sum, item) => {
                                                if (detailType === 'cash_in') {
                                                    const revenue = parseFloat(item.revenue || item.grand_total) || 0;
                                                    return sum + revenue;
                                                } else {
                                                    const total = parseFloat(item.total || item.amount) || 0;
                                                    return sum + total;
                                                }
                                            }, 0)
                                        ) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

