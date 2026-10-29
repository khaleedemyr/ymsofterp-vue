<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    records: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    outlets: { type: Array, default: () => [] },
    canChooseOutlet: { type: Boolean, default: false },
    tableReady: { type: Boolean, default: true },
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
    router.get('/cleaning-ipal', params, { preserveState: true, replace: true });
}, 350);

watch(filters, applyFilters, { deep: true });

function photoUrl(path) {
    if (!path) return null;
    return `/storage/${path}`;
}

function statusLabel(row) {
    return row.photo_after ? 'Lengkap' : 'Menunggu After';
}

function statusClass(row) {
    return row.photo_after
        ? 'bg-green-100 text-green-700'
        : 'bg-amber-100 text-amber-700';
}

function deleteRow(row) {
    Swal.fire({
        title: 'Hapus data Cleaning IPAL?',
        text: 'Foto before/after juga akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/cleaning-ipal/${row.id}`, {
                onSuccess: () => Swal.fire('Terhapus', 'Data berhasil dihapus.', 'success'),
                onError: (errors) => Swal.fire('Gagal', errors.message || 'Tidak dapat menghapus.', 'error'),
            });
        }
    });
}

function openPreview(path) {
    const url = photoUrl(path);
    if (!url) return;
    Swal.fire({
        imageUrl: url,
        imageAlt: 'Foto Cleaning IPAL',
        width: 640,
        showConfirmButton: false,
        showCloseButton: true,
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-emerald-800 flex items-center gap-2">
                        <i class="fa-solid fa-broom"></i>
                        Cleaning IPAL
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Dokumentasi cleaning IPAL per outlet (foto before & after)</p>
                </div>
                <Link
                    v-if="tableReady"
                    href="/cleaning-ipal/create"
                    class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg shadow font-semibold text-sm transition"
                >
                    <i class="fa-solid fa-plus"></i> Tambah
                </Link>
            </div>

            <div v-if="!tableReady" class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-yellow-800 text-sm mb-6">
                Tabel belum tersedia. Jalankan
                <code class="bg-yellow-100 px-1 rounded">database/sql/cleaning_ipal_logs.sql</code>
                terlebih dahulu.
            </div>

            <template v-else>
                <div class="bg-white rounded-xl shadow p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <input
                            v-model="filters.date"
                            type="date"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                        />
                    </div>
                    <div v-if="canChooseOutlet">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                        <select
                            v-model="filters.outlet_id"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option value="">Semua Outlet</option>
                            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                        </select>
                    </div>
                    <div v-else>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                            Outlet sendiri (terkunci)
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Nama outlet / QR / keterangan..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                        />
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Tanggal</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Outlet</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Keterangan</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-600">Before</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-600">After</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="!records.data?.length">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">Belum ada data Cleaning IPAL.</td>
                                </tr>
                                <tr v-for="row in records.data" :key="row.id" class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-3 whitespace-nowrap">{{ row.cleaning_date }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ row.outlet_name || '-' }}</div>
                                        <div class="text-xs font-mono text-gray-500">{{ row.outlet_qr_code || '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 max-w-xs">
                                        <div class="line-clamp-2 text-gray-700">{{ row.keterangan || '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            v-if="row.photo_before"
                                            type="button"
                                            class="inline-block"
                                            @click="openPreview(row.photo_before)"
                                        >
                                            <img :src="photoUrl(row.photo_before)" alt="Before" class="w-14 h-14 object-cover rounded-lg border mx-auto" />
                                        </button>
                                        <span v-else class="text-gray-300">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            v-if="row.photo_after"
                                            type="button"
                                            class="inline-block"
                                            @click="openPreview(row.photo_after)"
                                        >
                                            <img :src="photoUrl(row.photo_after)" alt="After" class="w-14 h-14 object-cover rounded-lg border mx-auto" />
                                        </button>
                                        <span v-else class="text-xs text-amber-600 font-medium">Belum ada</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(row)">
                                            {{ statusLabel(row) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <Link
                                                :href="`/cleaning-ipal/${row.id}/edit`"
                                                class="text-emerald-700 hover:underline font-medium"
                                            >
                                                {{ row.photo_after ? 'Edit' : 'Isi After' }}
                                            </Link>
                                            <button
                                                type="button"
                                                class="text-red-600 hover:underline font-medium"
                                                @click="deleteRow(row)"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="records.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
                    <Link
                        v-for="link in records.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-1 rounded border text-sm"
                        :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : (link.url ? 'bg-white hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed')"
                        v-html="link.label"
                    />
                </div>
            </template>
        </div>
    </AppLayout>
</template>
