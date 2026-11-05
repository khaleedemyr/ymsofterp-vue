<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
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
const exporting = ref(null);

const showTicketModal = ref(false);
const ticketSubmitting = ref(false);
const ticketOptionsLoaded = ref(false);
const ticketOptionsLoading = ref(false);
const categories = ref([]);
const priorities = ref([]);
const divisis = ref([]);
const selectedRow = ref(null);
const ticketForm = ref({
    title: '',
    description: '',
    category_id: '',
    priority_id: '',
    divisi_id: '',
});

const selectedPriority = computed(() =>
    priorities.value.find((p) => String(p.id) === String(ticketForm.value.priority_id)) || null
);

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

function buildExportQuery() {
    const params = new URLSearchParams();
    if (filters.value.date) params.set('date', filters.value.date);
    if (filters.value.search) params.set('search', filters.value.search);
    if (props.canChooseOutlet && filters.value.outlet_id) {
        params.set('outlet_id', String(filters.value.outlet_id));
    }
    return params.toString();
}

function exportExcel() {
    if (!props.rows.length) return;
    exporting.value = 'excel';
    window.open(`${route('chiller-temperature.export-excel')}?${buildExportQuery()}`, '_blank');
    window.setTimeout(() => { exporting.value = null; }, 2000);
}

function exportPdf() {
    if (!props.rows.length) return;
    exporting.value = 'pdf';
    window.open(`${route('chiller-temperature.export-pdf')}?${buildExportQuery()}`, '_blank');
    window.setTimeout(() => { exporting.value = null; }, 2000);
}

function formatTemp(value) {
    if (value === null || value === undefined || value === '') return '-';
    return Number(value).toFixed(1);
}

function buildDefaultDescription(row) {
    const lines = [
        `Kendala pada unit: ${row.item_name || '-'}`,
        `Serial: ${row.serial_number || '-'}`,
        `Outlet: ${row.outlet_name || '-'}`,
        `Warehouse: ${row.warehouse_name || '-'}`,
        `Tanggal cek: ${row.check_date || filters.value.date}`,
        '',
        'Detail kendala:',
        '',
    ];
    return lines.join('\n');
}

async function loadTicketOptions() {
    if (ticketOptionsLoaded.value || ticketOptionsLoading.value) return;
    ticketOptionsLoading.value = true;
    try {
        const { data } = await axios.get(route('chiller-temperature.ticket-options'));
        categories.value = data?.data?.categories || [];
        priorities.value = data?.data?.priorities || [];
        divisis.value = data?.data?.divisis || [];
        ticketOptionsLoaded.value = true;
    } catch (error) {
        Swal.fire('Gagal', error.response?.data?.message || 'Gagal memuat opsi ticket.', 'error');
    } finally {
        ticketOptionsLoading.value = false;
    }
}

async function openTicketModal(row) {
    if (!props.canChooseOutlet) return;
    selectedRow.value = row;
    ticketForm.value = {
        title: `Kontrol Suhu - ${row.item_name || row.serial_number || 'Unit'} (${row.outlet_name || 'Outlet'})`,
        description: buildDefaultDescription(row),
        category_id: '',
        priority_id: '',
        divisi_id: '',
    };
    showTicketModal.value = true;
    await loadTicketOptions();
}

function closeTicketModal() {
    showTicketModal.value = false;
    selectedRow.value = null;
}

async function submitTicket() {
    if (!selectedRow.value) return;

    if (!ticketForm.value.title.trim()
        || !ticketForm.value.description.trim()
        || !ticketForm.value.category_id
        || !ticketForm.value.priority_id
        || !ticketForm.value.divisi_id) {
        Swal.fire('Lengkapi form', 'Title, description, category, priority, dan divisi wajib diisi.', 'warning');
        return;
    }

    ticketSubmitting.value = true;
    try {
        const temps = {};
        (props.checkTimes || []).forEach((time) => {
            const slot = selectedRow.value.slots?.[time];
            temps[time] = slot
                ? {
                    temp_display: slot.temp_display,
                    temp_actual: slot.temp_actual,
                    notes: slot.notes || '',
                }
                : null;
        });

        const { data } = await axios.post(route('chiller-temperature.create-ticket'), {
            outlet_id: selectedRow.value.outlet_id,
            asset_serial_id: selectedRow.value.asset_serial_id,
            serial_number: selectedRow.value.serial_number,
            item_name: selectedRow.value.item_name,
            warehouse_name: selectedRow.value.warehouse_name,
            check_date: selectedRow.value.check_date || filters.value.date,
            title: ticketForm.value.title,
            description: ticketForm.value.description,
            category_id: ticketForm.value.category_id,
            priority_id: ticketForm.value.priority_id,
            divisi_id: ticketForm.value.divisi_id,
            temps,
        });

        closeTicketModal();

        const ticketNumber = data?.data?.ticket_number || '';
        const ticketId = data?.data?.id;
        const result = await Swal.fire({
            icon: 'success',
            title: 'Ticket dibuat',
            html: ticketNumber
                ? `Ticket <strong>${ticketNumber}</strong> berhasil dibuat.`
                : (data?.message || 'Ticket berhasil dibuat.'),
            showCancelButton: !!ticketId,
            confirmButtonText: ticketId ? 'Lihat Ticket' : 'OK',
            cancelButtonText: 'Tutup',
        });

        if (result.isConfirmed && ticketId) {
            window.open(`/tickets/${ticketId}`, '_blank');
        }
    } catch (error) {
        const message = error.response?.data?.message
            || Object.values(error.response?.data?.errors || {})?.[0]?.[0]
            || 'Gagal membuat ticket.';
        Swal.fire('Gagal', message, 'error');
    } finally {
        ticketSubmitting.value = false;
    }
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
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        v-if="tableReady"
                        type="button"
                        @click="exportExcel"
                        :disabled="exporting || !rows.length"
                        class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg shadow font-semibold text-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i class="fa-solid fa-file-excel"></i>
                        {{ exporting === 'excel' ? 'Menyiapkan...' : 'Export Excel' }}
                    </button>
                    <button
                        v-if="tableReady"
                        type="button"
                        @click="exportPdf"
                        :disabled="exporting || !rows.length"
                        class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg shadow font-semibold text-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i class="fa-solid fa-file-pdf"></i>
                        {{ exporting === 'pdf' ? 'Menyiapkan...' : 'Export PDF' }}
                    </button>
                    <Link
                        v-if="tableReady"
                        href="/chiller-temperature/create"
                        class="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-5 py-2.5 rounded-lg shadow font-semibold text-sm transition"
                    >
                        <i class="fa-solid fa-plus"></i> Tambah
                    </Link>
                </div>
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
                                    <th
                                        v-if="canChooseOutlet"
                                        rowspan="2"
                                        class="px-3 py-2 text-center font-bold border border-amber-400 whitespace-nowrap"
                                    >
                                        Ticket
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
                                    <td :colspan="3 + checkTimes.length * 2 + (canChooseOutlet ? 1 : 0)" class="px-4 py-10 text-center text-gray-400 border border-gray-200">
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
                                                <div
                                                    v-if="row.slots[time].notes"
                                                    class="mt-1 text-[10px] text-gray-500 leading-tight max-w-[110px] mx-auto break-words"
                                                    :title="row.slots[time].notes"
                                                >
                                                    <i class="fa-regular fa-note-sticky mr-0.5"></i>{{ row.slots[time].notes }}
                                                </div>
                                            </template>
                                            <span v-else class="text-gray-300">-</span>
                                        </td>
                                    </template>
                                    <td v-if="canChooseOutlet" class="px-2 py-2 border border-gray-200 text-center align-middle">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1.5 rounded-lg text-xs font-semibold shadow-sm transition"
                                            title="Buat ticket dari unit ini"
                                            @click="openTicketModal(row)"
                                        >
                                            <i class="fa-solid fa-ticket"></i>
                                            Add to Ticketing
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>

        <!-- Ticket Modal (HO only) -->
        <div
            v-if="showTicketModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
            @click.self="closeTicketModal"
        >
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-100 px-5 py-4 flex items-center justify-between rounded-t-2xl">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <i class="fa-solid fa-ticket text-indigo-600"></i>
                            Add to Ticketing
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Buat ticket jika ada kendala pada unit chiller/freezer ini.
                        </p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeTicketModal">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <div class="px-5 py-4 space-y-4">
                    <div v-if="selectedRow" class="rounded-xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm">
                        <div class="font-semibold text-cyan-900">{{ selectedRow.item_name || '-' }}</div>
                        <div class="text-xs font-mono text-cyan-700 mt-0.5">{{ selectedRow.serial_number }}</div>
                        <div class="text-xs text-cyan-800 mt-1">
                            {{ selectedRow.outlet_name || '-' }} • {{ selectedRow.warehouse_name || '-' }} • {{ selectedRow.check_date || filters.date }}
                        </div>
                    </div>

                    <div v-if="ticketOptionsLoading" class="text-sm text-gray-500 py-6 text-center">
                        <i class="fa-solid fa-spinner fa-spin mr-1"></i> Memuat opsi ticket...
                    </div>

                    <template v-else>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input
                                v-model="ticketForm.title"
                                type="text"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description / Finding *</label>
                            <textarea
                                v-model="ticketForm.description"
                                rows="6"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Jelaskan kendala suhu / kondisi unit..."
                            ></textarea>
                            <p class="text-xs text-gray-400 mt-1">Ringkasan suhu Display/Actual otomatis ditambahkan saat submit.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                                <select
                                    v-model="ticketForm.category_id"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">Pilih Category</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Priority *</label>
                                <select
                                    v-model="ticketForm.priority_id"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">Pilih Priority</option>
                                    <option v-for="p in priorities" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <p v-if="selectedPriority" class="text-xs text-indigo-700 mt-1">
                                    Due: {{ selectedPriority.max_days ?? '-' }} hari
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Divisi Concern *</label>
                                <select
                                    v-model="ticketForm.divisi_id"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">Pilih Divisi</option>
                                    <option v-for="d in divisis" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
                                </select>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="sticky bottom-0 bg-white border-t border-gray-100 px-5 py-4 flex justify-end gap-2 rounded-b-2xl">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        :disabled="ticketSubmitting"
                        @click="closeTicketModal"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50"
                        :disabled="ticketSubmitting || ticketOptionsLoading"
                        @click="submitTicket"
                    >
                        <i v-if="ticketSubmitting" class="fa-solid fa-spinner fa-spin"></i>
                        <i v-else class="fa-solid fa-ticket"></i>
                        {{ ticketSubmitting ? 'Membuat...' : 'Buat Ticket' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
