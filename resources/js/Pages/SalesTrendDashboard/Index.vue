<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    trendData: Object,
    filters: Object,
});

const loading = ref(false);
const filters = ref({
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
    group_by: props.filters?.group_by || 'monthly',
});

const chartSeries = computed(() => [
    {
        name: 'Revenue',
        type: 'line',
        data: props.trendData?.series?.map(item => Number(item.revenue || 0)) || [],
    },
    {
        name: 'Orders',
        type: 'column',
        data: props.trendData?.series?.map(item => Number(item.orders || 0)) || [],
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
        categories: props.trendData?.series?.map(item => formatPeriodLabel(item.period, filters.value.group_by)) || [],
    },
    yaxis: [
        {
            title: { text: 'Revenue (Rp)' },
            labels: {
                formatter: (value) => formatCompactCurrency(value),
            },
        },
        {
            opposite: true,
            title: { text: 'Orders' },
            labels: {
                formatter: (value) => Number(value).toLocaleString('id-ID'),
            },
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

function applyFilters() {
    loading.value = true;
    router.get(route('sales-trend-dashboard.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            loading.value = false;
        },
    });
}

function resetFilters() {
    filters.value = {
        date_from: new Date(new Date().setMonth(new Date().getMonth() - 11)).toISOString().slice(0, 10),
        date_to: new Date().toISOString().slice(0, 10),
        group_by: 'monthly',
    };
    applyFilters();
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
                    Analisa trend revenue dan order lintas bulan/tahun (sumber revenue mengikuti Sales Outlet Dashboard).
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                        <input v-model="filters.date_from" type="date" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                        <input v-model="filters.date_to" type="date" class="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Agregasi</label>
                        <select v-model="filters.group_by" class="w-full border rounded-lg px-3 py-2">
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                            <option value="yearly">Tahunan</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button :disabled="loading" @click="applyFilters" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            Terapkan
                        </button>
                        <button :disabled="loading" @click="resetFilters" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50">
                            Reset
                        </button>
                    </div>
                </div>
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
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Trend Revenue vs Orders</h2>
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
        </div>
    </AppLayout>
</template>
