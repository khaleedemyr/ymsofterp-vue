<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    outlets: { type: Array, default: () => [] },
    canChooseOutlet: { type: Boolean, default: false },
    user: { type: Object, default: null },
    tableReady: { type: Boolean, default: true },
    checkTimes: { type: Array, default: () => ['10:00', '16:00', '22:00'] },
});

const filters = ref({
    date: props.filters?.date || new Date().toISOString().slice(0, 10),
    outlet_id: props.filters?.outlet_id || '',
    search: props.filters?.search || '',
});

const applyFilters = debounce(() => {
    const params = {
        date: filters.value.date,
        search: filters.value.search || undefined,
    };
    if (props.canChooseOutlet && filters.value.outlet_id) {
        params.outlet_id = filters.value.outlet_id;
    }
    router.get('/chiller-temperature', params, { preserveState: true, replace: true });
}, 350);

watch(filters, applyFilters, { deep: true });

function formatTemp(value) {
    if (value === null || value === undefined || value === '') return '-';
    return Number(value).toFixed(1);
}

function deleteSlot(slot) {
    if (!slot?.id) return;
    Swal.fire({
        title: 'Hapus data suhu?',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/chiller-temperature/${slot.id}`, {
                onSuccess: () => Swal.fire('Terhapus', 'Data suhu berhasil dihapus.', 'success'),
                onError: (errors) => Swal.fire('Gagal', errors.message || 'Tidak dapat menghapus data.', 'error'),
            });
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-cyan-800 flex items-center gap-2">
                        <i class="fa-solid fa-temperature-half"></i>
                        Kontrol Suhu Chiller & Freezer
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Catat suhu Display & Actual per jam (10:00 / 16:00 / 22:00)</p>
                </div>
                <Link
                    v-if="tableReady"
                    href="/chiller-temperature/create"
                    class="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-5 py-2.5 rounded-lg shadow font-semibold text-sm transition"
                >
                    <i class="fa-solid fa-plus"></i> Tambah
                </Link>
            </div>

            <div v-if="!tableReady" class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-yellow-800 text-sm mb-6">
                Tabel belum tersedia. Jalankan
                <code class="bg-yellow-100 px-1 rounded">database/sql/chiller_temperature_logs.sql</code>
                terlebih dahulu.
            </div>

            <template v-else>
                <div class="bg-white rounded-xl shadow p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <input
                            v-model="filters.date"
                            type="date"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                        />
                    </div>
                    <div v-if="canChooseOutlet">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                        <select
                            v-model="filters.outlet_id"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                        >
                            <option value="">Semua Outlet</option>
                            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                        </select>
                    </div>
                    <div v-else class="sm:col-span-1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                            Outlet sendiri (terkunci)
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Cari Item / Nomor Seri</label>
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Nama chiller / freezer / serial..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                        />
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-amber-300 text-amber-950">
                                    <th rowspan="2" class="px-3 py-2 text-left font-bold border border-amber-400 whitespace-nowrap">Nama Outlet</th>
                                    <th rowspan="2" class="px-3 py-2 text-left font-bold border border-amber-400 whitespace-nowrap">Warehouse Outlet</th>
                                    <th rowspan="2" class="px-3 py-2 text-left font-bold border border-amber-400 whitespace-nowrap">Chiller / Freezer</th>
                                    <th
                                        v-for="time in checkTimes"
                                        :key="`h-${time}`"
                                        colspan="2"
                                        class="px-3 py-2 text-center font-bold border border-sky-500 bg-sky-500 text-white"
                                    >
                                        {{ time }}
                                    </th>
                                </tr>
                                <tr class="bg-sky-100 text-sky-900">
                                    <template v-for="time in checkTimes" :key="`sub-${time}`">
                                        <th class="px-2 py-1.5 text-center font-semibold border border-sky-300 text-xs">Display</th>
                                        <th class="px-2 py-1.5 text-center font-semibold border border-sky-300 text-xs">Actual</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!rows.length">
                                    <td :colspan="3 + checkTimes.length * 2" class="px-4 py-10 text-center text-gray-400 border border-gray-200">
                                        Belum ada data suhu untuk filter ini.
                                    </td>
                                </tr>
                                <tr v-for="row in rows" :key="`${row.asset_serial_id}-${row.check_date}`" class="hover:bg-cyan-50/40">
                                    <td class="px-3 py-2 border border-gray-200 font-medium text-gray-800 whitespace-nowrap">
                                        {{ row.outlet_name || '-' }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-200 text-gray-700 whitespace-nowrap">
                                        {{ row.warehouse_name || '-' }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-200">
                                        <div class="font-semibold text-gray-900">{{ row.item_name || '-' }}</div>
                                        <div class="text-xs font-mono text-cyan-700">{{ row.serial_number }}</div>
                                    </td>
                                    <template v-for="time in checkTimes" :key="`${row.asset_serial_id}-${time}`">
                                        <td class="px-2 py-2 border border-gray-200 text-center align-top">
                                            <template v-if="row.slots[time]">
                                                <div class="font-semibold text-gray-800">{{ formatTemp(row.slots[time].temp_display) }}</div>
                                                <div class="mt-1 flex items-center justify-center gap-2">
                                                    <Link
                                                        :href="`/chiller-temperature/${row.slots[time].id}/edit`"
                                                        class="text-cyan-600 hover:text-cyan-800 text-xs"
                                                        title="Edit"
                                                    >
                                                        <i class="fa-solid fa-pen"></i>
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        class="text-red-600 hover:text-red-800 text-xs"
                                                        title="Hapus"
                                                        @click="deleteSlot(row.slots[time])"
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </template>
                                            <span v-else class="text-gray-300">-</span>
                                        </td>
                                        <td class="px-2 py-2 border border-gray-200 text-center align-top">
                                            <template v-if="row.slots[time]">
                                                <div class="font-semibold text-gray-800">{{ formatTemp(row.slots[time].temp_actual) }}</div>
                                            </template>
                                            <span v-else class="text-gray-300">-</span>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
