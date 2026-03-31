<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    externalUser: {
        type: Object,
        default: () => ({}),
    },
});

const filters = reactive({
    outlet: '',
    date_from: '',
    date_to: '',
});

const outlets = ref([]);
const loading = ref(false);
const showReport = ref(false);
const report = reactive({
    summary: {},
    per_day: {},
});

const avgCheck = computed(() => {
    const grandTotal = Number(report.summary?.grand_total || 0);
    const pax = Number(report.summary?.total_pax || 0);
    return pax > 0 ? grandTotal / pax : 0;
});

const currency = (value) =>
    Number(value || 0).toLocaleString('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

const loadOutlets = async () => {
    const response = await axios.get('/external/api/outlets');
    outlets.value = response.data?.outlets || [];

    // Default ke outlet user external jika ada mapping
    if (props.externalUser?.kode_outlet) {
        filters.outlet = props.externalUser.kode_outlet;
    }
};

const fetchReport = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/external/api/report/sales-simple', { params: filters });
        report.summary = response.data?.summary || {};
        report.per_day = response.data?.per_day || {};
        showReport.value = true;
    } finally {
        loading.value = false;
    }
};

onMounted(loadOutlets);
</script>

<template>
    <Head title="External Revenue Report" />

    <div class="min-h-screen bg-gray-100 p-6">
        <div class="max-w-7xl mx-auto bg-white rounded-2xl shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Revenue Report (External)</h1>
                <Link
                    as="button"
                    method="post"
                    :href="route('external.logout')"
                    class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700"
                >
                    Logout
                </Link>
            </div>

            <div v-if="props.externalUser?.nama_outlet" class="mb-4 text-sm text-gray-600">
                Outlet akses: <span class="font-semibold">{{ props.externalUser.nama_outlet }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium mb-1">Outlet</label>
                    <select
                        v-model="filters.outlet"
                        :disabled="Boolean(props.externalUser?.kode_outlet)"
                        class="w-full rounded-lg border-gray-300 disabled:bg-gray-100 disabled:text-gray-500"
                    >
                        <option value="">Pilih Outlet</option>
                        <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.qr_code">
                            {{ outlet.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Tanggal From</label>
                    <input v-model="filters.date_from" type="date" class="w-full rounded-lg border-gray-300" />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Tanggal To</label>
                    <input v-model="filters.date_to" type="date" class="w-full rounded-lg border-gray-300" />
                </div>
                <div class="flex items-end">
                    <button
                        @click="fetchReport"
                        class="w-full py-2 rounded-lg bg-blue-700 text-white font-semibold hover:bg-blue-800"
                    >
                        Tampilkan
                    </button>
                </div>
            </div>

            <div v-if="loading" class="text-gray-500">Loading...</div>

            <div v-if="showReport && !loading" class="space-y-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 rounded-lg bg-blue-50">
                        <div class="text-sm text-gray-500">Total Sales</div>
                        <div class="font-bold">{{ currency(report.summary.total_sales) }}</div>
                    </div>
                    <div class="p-4 rounded-lg bg-green-50">
                        <div class="text-sm text-gray-500">Grand Total</div>
                        <div class="font-bold">{{ currency(report.summary.grand_total) }}</div>
                    </div>
                    <div class="p-4 rounded-lg bg-yellow-50">
                        <div class="text-sm text-gray-500">Total Order</div>
                        <div class="font-bold">{{ report.summary.total_order || 0 }}</div>
                    </div>
                    <div class="p-4 rounded-lg bg-purple-50">
                        <div class="text-sm text-gray-500">Avg Check</div>
                        <div class="font-bold">{{ currency(avgCheck) }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left">Tanggal</th>
                                <th class="px-3 py-2 text-right">Total Order</th>
                                <th class="px-3 py-2 text-right">Total Sales</th>
                                <th class="px-3 py-2 text-right">Grand Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, date) in report.per_day" :key="date" class="border-t">
                                <td class="px-3 py-2">{{ date }}</td>
                                <td class="px-3 py-2 text-right">{{ row.total_order }}</td>
                                <td class="px-3 py-2 text-right">{{ currency(row.total_sales) }}</td>
                                <td class="px-3 py-2 text-right">{{ currency(row.grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
