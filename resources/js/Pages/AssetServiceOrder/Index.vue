<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    serviceOrders: Object,
    filters: Object,
    user: Object,
    outlets: Array,
});

const filters = ref({
    search: props.filters?.search || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
    status: props.filters?.status || '',
    outlet_id: props.filters?.outlet_id || '',
});

const applyFilters = debounce(() => {
    router.get('/asset-service-orders', {
        ...Object.fromEntries(Object.entries(filters.value).filter(([_, v]) => v !== '' && v !== null)),
    }, { preserveState: true, replace: true });
}, 400);

watch(filters, applyFilters, { deep: true });

function clearFilters() {
    filters.value = { search: '', from: '', to: '', status: '', outlet_id: '' };
}

function statusBadge(status) {
    const map = {
        waiting_approval: 'bg-yellow-100 text-yellow-700',
        in_service: 'bg-blue-100 text-blue-700',
        partially_returned: 'bg-orange-100 text-orange-700',
        returned: 'bg-green-100 text-green-700',
        rejected: 'bg-red-100 text-red-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function statusLabel(status) {
    const map = {
        waiting_approval: 'Waiting Approval',
        in_service: 'In Service',
        partially_returned: 'Partially Returned',
        returned: 'Returned',
        rejected: 'Rejected',
    };
    return map[status] || status;
}

function formatCurrency(val) {
    if (!val) return '-';
    return Number(val).toLocaleString('id-ID');
}

function deleteOrder(id) {
    Swal.fire({
        title: 'Hapus Service Order?',
        text: 'Data service order akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/asset-service-orders/${id}`, {
                onSuccess: () => Swal.fire('Terhapus!', 'Service order berhasil dihapus.', 'success'),
            });
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <h1 class="text-2xl font-bold text-teal-700">Asset Service</h1>
                <Link href="/asset-service-orders/create"
                    class="inline-flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-5 py-2.5 rounded-lg shadow font-semibold text-sm transition">
                    <i class="fa-solid fa-plus"></i> Buat Service Order Baru
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
                    <input v-model="filters.search" type="text" placeholder="No. / Deskripsi / Supplier..."
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                    <input v-model="filters.from" type="date"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                    <input v-model="filters.to" type="date"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <select v-model="filters.status"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                        <option value="">Semua</option>
                        <option value="waiting_approval">Waiting Approval</option>
                        <option value="in_service">In Service</option>
                        <option value="partially_returned">Partially Returned</option>
                        <option value="returned">Returned</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div v-if="user.id_outlet == 1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                    <select v-model="filters.outlet_id"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                        <option value="">Semua Outlet</option>
                        <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-teal-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-teal-700 uppercase">Nomor</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-teal-700 uppercase">Tanggal</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-teal-700 uppercase">Outlet</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-teal-700 uppercase">Warehouse</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-teal-700 uppercase">Supplier</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-teal-700 uppercase">Est. Cost</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-teal-700 uppercase">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-teal-700 uppercase">Dibuat Oleh</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold text-teal-700 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!serviceOrders.data || !serviceOrders.data.length">
                            <td colspan="9" class="px-5 py-8 text-center text-gray-400">Tidak ada data service order.</td>
                        </tr>
                        <tr v-for="s in serviceOrders.data" :key="s.id" class="hover:bg-teal-50/30 transition">
                            <td class="px-5 py-3 font-semibold text-teal-700">{{ s.number }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600">{{ s.date }}</td>
                            <td class="px-5 py-3 text-sm text-gray-700">{{ s.outlet_name }}</td>
                            <td class="px-5 py-3 text-sm text-gray-700">{{ s.warehouse_outlet_name }}</td>
                            <td class="px-5 py-3 text-sm text-gray-700">{{ s.supplier_name }}</td>
                            <td class="px-5 py-3 text-sm text-gray-700 text-right">{{ formatCurrency(s.estimated_cost) }}</td>
                            <td class="px-5 py-3">
                                <span :class="statusBadge(s.status)"
                                    class="px-2.5 py-1 rounded-full text-xs font-semibold">{{ statusLabel(s.status) }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600">{{ s.creator_name || '-' }}</td>
                            <td class="px-5 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <Link :href="`/asset-service-orders/${s.id}`"
                                        class="text-teal-600 hover:text-teal-800 text-sm font-medium">
                                        <i class="fa-solid fa-eye"></i> Lihat
                                    </Link>
                                    <button v-if="s.status === 'waiting_approval'" @click="deleteOrder(s.id)"
                                        class="text-red-500 hover:text-red-700 text-sm font-medium">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="serviceOrders.links && serviceOrders.links.length > 3" class="flex justify-center mt-6 gap-1">
                <button v-for="link in serviceOrders.links" :key="link.label"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url, {}, { preserveState: true })"
                    :class="[
                        'px-3 py-1.5 rounded text-sm border transition',
                        link.active ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50',
                        !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
