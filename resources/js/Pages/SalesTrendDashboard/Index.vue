<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';

const props = defineProps({
    trendData: Object,
    regionTrend: Object,
    regions: Array,
    filters: Object,
});

const loading = ref(false);
const outletLoading = ref(false);
const selectedRegionId = ref('');
const outletTrend = ref(null);

function currentMonthValue() {
    return new Date().toISOString().slice(0, 7);
}

function monthMonthsAgo(months) {
    const date = new Date();
    date.setMonth(date.getMonth() - months);
    return date.toISOString().slice(0, 7);
}

const filters = ref({
    month_from: props.filters?.month_from || props.filters?.date_from?.slice(0, 7) || monthMonthsAgo(11),
    month_to: props.filters?.month_to || props.filters?.date_to?.slice(0, 7) || currentMonthValue(),
    group_by: props.filters?.group_by || 'monthly',
});

const periodRangeLabel = computed(() => {
    if (!filters.value.month_from || !filters.value.month_to) return '';
    const fmt = (ym) => {
        const [y, m] = ym.split('-').map(Number);
        const label = new Date(y, m - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        return label.charAt(0).toUpperCase() + label.slice(1);
    };
    return `${fmt(filters.value.month_from)} – ${fmt(filters.value.month_to)}`;
});

const REGION_COLORS = [
    '#2563eb', '#16a34a', '#ea580c', '#db2777', '#7c3aed',
    '#0891b2', '#ca8a04', '#dc2626', '#4f46e5', '#059669',
];

const chartSeries = computed(() => [
    {
        name: 'Revenue',
        type: 'line',
        data: props.trendData?.series?.map((item) => Number(item.revenue || 0)) || [],
    },
    {
        name: 'Orders',
        type: 'column',
        data: props.trendData?.series?.map((item) => Number(item.orders || 0)) || [],
    },
]);

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 380,
        toolbar: { show: true },
    },
    stroke: {
        width: [3, 0],
        curve: 'smooth',
    },
    colors: ['#2563eb', '#22c55e'],
    xaxis: {
        categories: props.trendData?.series?.map((item) => formatPeriodLabel(item.period, filters.value.group_by)) || [],
    },
    yaxis: [
        {
            title: { text: 'Revenue (Rp)' },
            labels: { formatter: (value) => formatCompactCurrency(value) },
        },
        {
            opposite: true,
            title: { text: 'Orders' },
            labels: { formatter: (value) => Number(value).toLocaleString('id-ID') },
        },
    ],
    dataLabels: { enabled: false },
    legend: { position: 'top' },
    tooltip: {
        shared: true,
        intersect: false,
        custom: ({ dataPointIndex }) => {
            const row = props.trendData?.series?.[dataPointIndex];
            if (!row) return '';

            const revenue = Number(row.revenue || 0);
            const orders = Number(row.orders || 0);
            const customers = Number(row.customers || 0);
            const avgOrder = Number(row.avg_order_value || 0);
            const avgCheck = customers > 0 ? revenue / customers : 0;

            return `
                <div class="px-3 py-2 bg-white rounded border shadow">
                    <div class="font-semibold">${formatPeriodLabel(row.period, filters.value.group_by)}</div>
                    <div>Revenue: ${formatCurrency(revenue)}</div>
                    <div>Orders: ${orders.toLocaleString('id-ID')}</div>
                    <div>Pax: ${customers.toLocaleString('id-ID')}</div>
                    <div>Avg/Order: ${formatCurrency(avgOrder)}</div>
                    <div>Avg/Pax: ${formatCurrency(avgCheck)}</div>
                </div>
            `;
        },
    },
}));

const regionChartSeries = computed(() => {
    const regions = props.regionTrend?.regions || [];
    return regions.map((region) => ({
        name: region.region_name,
        data: (region.series || []).map((item) => Number(item.revenue || 0)),
    }));
});

const regionChartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 380,
        toolbar: { show: true },
        zoom: { enabled: true },
    },
    stroke: { width: 3, curve: 'smooth' },
    colors: REGION_COLORS,
    xaxis: {
        categories: (props.regionTrend?.periods || []).map((period) => formatPeriodLabel(period, filters.value.group_by)),
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        labels: { formatter: (value) => formatCompactCurrency(value) },
    },
    dataLabels: { enabled: false },
    legend: { position: 'top' },
    tooltip: {
        shared: true,
        y: {
            formatter: (value) => formatCurrency(value),
        },
    },
}));

const outletChartSeries = computed(() => {
    const outlets = outletTrend.value?.outlets || [];
    return outlets.map((outlet) => ({
        name: outlet.outlet_name,
        data: (outlet.series || []).map((item) => Number(item.revenue || 0)),
    }));
});

const outletChartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 380,
        toolbar: { show: true },
        zoom: { enabled: true },
    },
    stroke: { width: 3, curve: 'smooth' },
    colors: REGION_COLORS,
    xaxis: {
        categories: (outletTrend.value?.periods || []).map((period) => formatPeriodLabel(period, filters.value.group_by)),
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        labels: { formatter: (value) => formatCompactCurrency(value) },
    },
    dataLabels: { enabled: false },
    legend: { position: 'top' },
    tooltip: {
        shared: true,
        y: {
            formatter: (value) => formatCurrency(value),
        },
    },
}));

const selectedRegionLabel = computed(() => {
    if (selectedRegionId.value === '' || selectedRegionId.value === null) return '';
    if (String(selectedRegionId.value) === '0') return 'Unknown Region';
    const region = (props.regions || []).find((item) => String(item.id) === String(selectedRegionId.value));
    return region?.name || '';
});

watch(selectedRegionId, (value) => {
    if (value === '' || value === null) {
        outletTrend.value = null;
        return;
    }
    fetchOutletTrend();
});

function applyFilters() {
    loading.value = true;
    outletTrend.value = null;
    selectedRegionId.value = '';

    let monthFrom = filters.value.month_from;
    let monthTo = filters.value.month_to;
    if (monthFrom && monthTo && monthFrom > monthTo) {
        [monthFrom, monthTo] = [monthTo, monthFrom];
        filters.value.month_from = monthFrom;
        filters.value.month_to = monthTo;
    }

    router.get(route('sales-trend-dashboard.index'), {
        month_from: monthFrom,
        month_to: monthTo,
        group_by: filters.value.group_by,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            loading.value = false;
        },
    });
}

function resetFilters() {
    filters.value = {
        month_from: monthMonthsAgo(11),
        month_to: currentMonthValue(),
        group_by: 'monthly',
    };
    applyFilters();
}

async function fetchOutletTrend() {
    if (selectedRegionId.value === '' || selectedRegionId.value === null) return;

    outletLoading.value = true;
    try {
        const response = await axios.get(route('sales-trend-dashboard.outlet-trend'), {
            params: {
                month_from: filters.value.month_from,
                month_to: filters.value.month_to,
                group_by: filters.value.group_by,
                region_id: selectedRegionId.value,
            },
        });
        outletTrend.value = response.data?.data || null;
    } catch (error) {
        console.error('Error fetching outlet trend:', error);
        outletTrend.value = null;
    } finally {
        outletLoading.value = false;
    }
}

function formatPeriodLabel(period, groupBy) {
    if (!period) return '-';
    if (groupBy === 'yearly') return String(period);

    if (groupBy === 'monthly') {
        const [year, month] = String(period).split('-');
        const date = new Date(Number(year), Number(month) - 1, 1);
        return date.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
    }

    return new Date(`${period}T00:00:00`).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function formatCompactCurrency(value) {
    const number = Number(value || 0);
    if (number >= 1_000_000_000) return `Rp ${(number / 1_000_000_000).toFixed(1)}B`;
    if (number >= 1_000_000) return `Rp ${(number / 1_000_000).toFixed(1)}M`;
    return `Rp ${number.toLocaleString('id-ID')}`;
}
</script>

<template>
    <AppLayout>
        <Head title="Sales Trend Dashboard" />

        <div class="p-6 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h1 class="text-2xl font-bold text-gray-900">Sales Trend Dashboard</h1>
                <p class="text-sm text-gray-600 mt-1">
                    Analisa trend revenue lintas bulan/tahun. Chart region selalu tampil; chart outlet dimuat setelah pilih region.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bulan Awal</label>
                        <input v-model="filters.month_from" type="month" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bulan Akhir</label>
                        <input v-model="filters.month_to" type="month" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Agregasi</label>
                        <select v-model="filters.group_by" class="w-full border rounded-lg px-3 py-2">
                            <option value="monthly">Bulanan</option>
                            <option value="yearly">Tahunan</option>
                            <option value="daily">Harian</option>
                        </select>
                        <p v-if="filters.group_by === 'daily'" class="text-xs text-amber-600 mt-1">
                            Harian lebih berat untuk rentang panjang — sebaiknya 1–2 bulan saja.
                        </p>
                    </div>
                    <div class="flex items-end gap-2">
                        <button
                            :disabled="loading"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            @click="applyFilters"
                        >
                            Terapkan
                        </button>
                        <button
                            :disabled="loading"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </div>
                <p v-if="periodRangeLabel" class="text-sm text-gray-500 mt-3">
                    Periode: <span class="font-medium text-gray-800">{{ periodRangeLabel }}</span>
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl border p-4">
                    <div class="text-xs text-gray-500">Total Revenue</div>
                    <div class="text-xl font-semibold text-gray-900">{{ formatCurrency(props.trendData?.summary?.total_revenue) }}</div>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <div class="text-xs text-gray-500">Total Orders</div>
                    <div class="text-xl font-semibold text-gray-900">{{ Number(props.trendData?.summary?.total_orders || 0).toLocaleString('id-ID') }}</div>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <div class="text-xs text-gray-500">Total Pax</div>
                    <div class="text-xl font-semibold text-gray-900">{{ Number(props.trendData?.summary?.total_customers || 0).toLocaleString('id-ID') }}</div>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <div class="text-xs text-gray-500">Avg per Order</div>
                    <div class="text-xl font-semibold text-gray-900">{{ formatCurrency(props.trendData?.summary?.avg_order_value) }}</div>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <div class="text-xs text-gray-500">Avg per Pax</div>
                    <div class="text-xl font-semibold text-gray-900">{{ formatCurrency(props.trendData?.summary?.avg_check) }}</div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Trend Overall — Revenue vs Orders</h2>
                <VueApexCharts
                    v-if="chartSeries[0].data.length > 0"
                    type="line"
                    height="380"
                    :options="chartOptions"
                    :series="chartSeries"
                />
                <div v-else class="text-center text-gray-500 py-12">
                    Tidak ada data pada periode ini.
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Trend per Region</h2>
                    <p class="text-sm text-gray-500">Setiap garis = 1 region (revenue)</p>
                </div>
                <VueApexCharts
                    v-if="regionChartSeries.length > 0"
                    type="line"
                    height="380"
                    :options="regionChartOptions"
                    :series="regionChartSeries"
                />
                <div v-else class="text-center text-gray-500 py-12">
                    Tidak ada data region pada periode ini.
                </div>

                <div v-if="props.regionTrend?.regions?.length" class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-gray-500">
                                <th class="py-2 pr-4">Region</th>
                                <th class="py-2 pr-4 text-right">Revenue</th>
                                <th class="py-2 pr-4 text-right">Orders</th>
                                <th class="py-2 text-right">Pax</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="region in props.regionTrend.regions"
                                :key="region.region_id"
                                class="border-b border-gray-50"
                            >
                                <td class="py-2 pr-4 font-medium text-gray-900">{{ region.region_name }}</td>
                                <td class="py-2 pr-4 text-right">{{ formatCurrency(region.total_revenue) }}</td>
                                <td class="py-2 pr-4 text-right">{{ Number(region.total_orders).toLocaleString('id-ID') }}</td>
                                <td class="py-2 text-right">{{ Number(region.total_customers).toLocaleString('id-ID') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Trend per Outlet</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Pilih region dulu — data outlet dimuat on-demand (top 10 by revenue).
                        </p>
                    </div>
                    <div class="w-full md:w-72">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                        <select v-model="selectedRegionId" class="w-full border rounded-lg px-3 py-2">
                            <option value="">— Pilih Region —</option>
                            <option
                                v-for="region in props.regionTrend?.regions || []"
                                :key="region.region_id"
                                :value="String(region.region_id)"
                            >
                                {{ region.region_name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="!selectedRegionId" class="text-center text-gray-400 py-12 border border-dashed rounded-lg">
                    Pilih region untuk menampilkan trend outlet.
                </div>
                <div v-else-if="outletLoading" class="text-center text-gray-500 py-12">
                    Memuat trend outlet untuk {{ selectedRegionLabel }}...
                </div>
                <template v-else>
                    <p v-if="outletTrend?.outlet_count_total > outletTrend?.outlet_count_shown" class="text-xs text-amber-600 mb-3">
                        Menampilkan top {{ outletTrend.outlet_count_shown }} dari {{ outletTrend.outlet_count_total }} outlet di region ini.
                    </p>
                    <VueApexCharts
                        v-if="outletChartSeries.length > 0"
                        type="line"
                        height="380"
                        :options="outletChartOptions"
                        :series="outletChartSeries"
                    />
                    <div v-else class="text-center text-gray-500 py-12">
                        Tidak ada data outlet untuk region ini pada periode terpilih.
                    </div>

                    <div v-if="outletTrend?.outlets?.length" class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-500">
                                    <th class="py-2 pr-4">Outlet</th>
                                    <th class="py-2 pr-4 text-right">Revenue</th>
                                    <th class="py-2 pr-4 text-right">Orders</th>
                                    <th class="py-2 text-right">Pax</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="outlet in outletTrend.outlets"
                                    :key="outlet.outlet_code"
                                    class="border-b border-gray-50"
                                >
                                    <td class="py-2 pr-4 font-medium text-gray-900">{{ outlet.outlet_name }}</td>
                                    <td class="py-2 pr-4 text-right">{{ formatCurrency(outlet.total_revenue) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ Number(outlet.total_orders).toLocaleString('id-ID') }}</td>
                                    <td class="py-2 text-right">{{ Number(outlet.total_customers).toLocaleString('id-ID') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
