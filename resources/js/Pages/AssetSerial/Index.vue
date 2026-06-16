<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    serials: Object,
    filters: Object,
    outlets: Array,
    warehouseOutlets: Array,
    user: Object,
    tableReady: { type: Boolean, default: true },
});

const filters = ref({
    search: props.filters?.search || '',
    owner_outlet_id: props.filters?.owner_outlet_id || '',
    warehouse_outlet_id: props.filters?.warehouse_outlet_id || '',
    status: props.filters?.status || '',
});

const filteredWarehouses = ref([]);

function updateWarehouses() {
    const all = Array.isArray(props.warehouseOutlets) ? props.warehouseOutlets : [];
    if (!filters.value.owner_outlet_id) {
        filteredWarehouses.value = all;
        return;
    }
    filteredWarehouses.value = all.filter(w => String(w.outlet_id) === String(filters.value.owner_outlet_id));
}

updateWarehouses();

const applyFilters = debounce(() => {
    router.get('/asset-serials', {
        ...Object.fromEntries(Object.entries(filters.value).filter(([_, v]) => v !== '' && v !== null)),
    }, { preserveState: true, replace: true });
}, 400);

watch(filters, applyFilters, { deep: true });
watch(() => filters.value.owner_outlet_id, () => {
    updateWarehouses();
    if (filters.value.warehouse_outlet_id) {
        const ok = filteredWarehouses.value.some(w => String(w.id) === String(filters.value.warehouse_outlet_id));
        if (!ok) filters.value.warehouse_outlet_id = '';
    }
});

function statusBadge(status) {
    const map = {
        available: 'bg-green-100 text-green-700',
        in_transfer: 'bg-blue-100 text-blue-700',
        in_service: 'bg-purple-100 text-purple-700',
        disposed: 'bg-red-100 text-red-700',
        lost: 'bg-orange-100 text-orange-700',
        replaced: 'bg-gray-100 text-gray-500',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function statusLabel(status) {
    const map = {
        available: 'Available',
        in_transfer: 'In Transfer',
        in_service: 'In Service',
        disposed: 'Disposed',
        lost: 'Lost',
        replaced: 'Replaced',
    };
    return map[status] || status;
}

function sourceLabel(type) {
    const map = {
        manual_register: 'Registrasi Manual',
        retroactive_tag: 'Tag Stok Lama',
        asset_good_receive: 'Good Receive',
    };
    return map[type] || type || '-';
}

function canDeleteRow(row) {
    return row.status === 'available';
}

function deleteSerial(row) {
    Swal.fire({
        title: 'Hapus Nomor Seri?',
        html: `Nomor seri <b>${row.serial_number}</b> akan dihapus permanen.<br><small class="text-gray-500">Hanya serial Available yang belum dipakai transaksi lain.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/asset-serials/${row.id}`, {
                onSuccess: () => Swal.fire('Terhapus', 'Nomor seri berhasil dihapus.', 'success'),
                onError: (errors) => Swal.fire('Gagal', errors.message || 'Tidak dapat menghapus nomor seri ini.', 'error'),
            });
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-teal-700">Asset Nomor Seri</h1>
                    <p class="text-sm text-gray-500 mt-1">Pelacakan unit fisik asset per nomor seri</p>
                </div>
                <Link v-if="tableReady" href="/asset-serials/create"
                    class="inline-flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-5 py-2.5 rounded-lg shadow font-semibold text-sm transition">
                    <i class="fa-solid fa-plus"></i> Daftarkan Nomor Seri
                </Link>
            </div>

            <div v-if="!tableReady" class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-yellow-800 text-sm">
                Tabel database belum tersedia. Jalankan <code class="bg-yellow-100 px-1 rounded">database/sql/asset_serial_mode.sql</code> terlebih dahulu.
            </div>

            <template v-else>
                <div class="bg-white rounded-xl shadow p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
                        <input v-model="filters.search" type="text" placeholder="Nomor seri / UID / barang..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                    </div>
                    <div v-if="user.id_outlet == 1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Pemilik</label>
                        <select v-model="filters.owner_outlet_id"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Semua Pemilik</option>
                            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Warehouse</label>
                        <select v-model="filters.warehouse_outlet_id"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Semua Warehouse</option>
                            <option v-for="w in filteredWarehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select v-model="filters.status"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Semua</option>
                            <option value="available">Available</option>
                            <option value="in_transfer">In Transfer</option>
                            <option value="in_service">In Service</option>
                            <option value="disposed">Disposed</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Nomor Seri</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Barang</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Pemilik</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Warehouse</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">UID Tag</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Sumber</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Di-tag</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="!serials.data?.length">
                                    <td colspan="9" class="px-4 py-10 text-center text-gray-400">Belum ada nomor seri terdaftar.</td>
                                </tr>
                                <tr v-for="row in serials.data" :key="row.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono font-semibold text-teal-700">{{ row.serial_number }}</td>
                                    <td class="px-4 py-3">{{ row.item_name }}</td>
                                    <td class="px-4 py-3">{{ row.owner_outlet_name }}</td>
                                    <td class="px-4 py-3">{{ row.warehouse_name || '-' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ row.tag_uid || '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(row.status)">
                                            {{ statusLabel(row.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs">{{ sourceLabel(row.source_type) }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ row.tagged_at ? new Date(row.tagged_at).toLocaleString('id-ID') : '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <Link :href="`/asset-serials/${row.id}`" class="text-teal-600 hover:underline font-medium">Lihat</Link>
                                            <button
                                                v-if="canDeleteRow(row)"
                                                type="button"
                                                @click.stop="deleteSerial(row)"
                                                class="text-red-600 hover:underline font-medium text-sm"
                                            >Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="serials.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
                    <Link v-for="link in serials.links" :key="link.label" :href="link.url || '#'"
                        class="px-3 py-1 rounded border text-sm"
                        :class="link.active ? 'bg-teal-600 text-white border-teal-600' : (link.url ? 'bg-white hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed')"
                        v-html="link.label" />
                </div>
            </template>
        </div>
    </AppLayout>
</template>
