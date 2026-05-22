<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({ transfers: Object, filters: Object, user: Object, outlets: Array });

const filters = ref({
    search: props.filters?.search || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
    status: props.filters?.status || '',
    owner_outlet_id: props.filters?.owner_outlet_id || '',
});

const applyFilters = debounce(() => {
    router.get('/asset-owner-transfers', {
        ...Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== '' && v != null)),
    }, { preserveState: true, replace: true });
}, 400);

watch(filters, applyFilters, { deep: true });

function statusBadge(status) {
    const map = { draft: 'bg-gray-100 text-gray-700', submitted: 'bg-yellow-100 text-yellow-700', approved: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700' };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function deleteTransfer(id) {
    Swal.fire({ title: 'Hapus?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' }).then((r) => {
        if (r.isConfirmed) router.delete(`/asset-owner-transfers/${id}`);
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-violet-700">Transfer Kepemilikan Aset</h1>
                <Link href="/asset-owner-transfers/create" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold">
                    <i class="fa-solid fa-plus mr-1"></i> Buat Baru
                </Link>
            </div>

            <div class="bg-white rounded-xl shadow p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div><label class="text-xs text-gray-500">Cari</label><input v-model="filters.search" class="w-full rounded-lg border-gray-300 text-sm" placeholder="No. transfer" /></div>
                <div><label class="text-xs text-gray-500">Dari</label><input v-model="filters.from" type="date" class="w-full rounded-lg border-gray-300 text-sm" /></div>
                <div><label class="text-xs text-gray-500">Sampai</label><input v-model="filters.to" type="date" class="w-full rounded-lg border-gray-300 text-sm" /></div>
                <div><label class="text-xs text-gray-500">Status</label>
                    <select v-model="filters.status" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Semua</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div v-if="user.id_outlet == 1"><label class="text-xs text-gray-500">Pemilik</label>
                    <select v-model="filters.owner_outlet_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Semua</option>
                        <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                    </select>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-violet-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-violet-700 uppercase">No.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-violet-700 uppercase">Pemilik Asal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-violet-700 uppercase">Pemilik Tujuan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-violet-700 uppercase">Lokasi / Gudang</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-violet-700 uppercase">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-violet-700 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-violet-700 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!transfers.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Tidak ada data.</td></tr>
                        <tr v-for="t in transfers.data" :key="t.id" class="hover:bg-violet-50/30">
                            <td class="px-4 py-3 font-semibold text-violet-700">{{ t.transfer_number }}</td>
                            <td class="px-4 py-3 text-sm">{{ t.owner_from_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ t.owner_to_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ t.location_outlet_name }}<br><span class="text-xs text-gray-400">{{ t.warehouse_outlet_name }}</span></td>
                            <td class="px-4 py-3 text-sm">{{ t.transfer_date }}</td>
                            <td class="px-4 py-3"><span :class="statusBadge(t.status)" class="px-2 py-1 rounded-full text-xs capitalize">{{ t.status }}</span></td>
                            <td class="px-4 py-3 text-center">
                                <Link :href="`/asset-owner-transfers/${t.id}`" class="text-violet-600 text-sm font-medium mr-2">Lihat</Link>
                                <button v-if="t.status === 'draft'" @click="deleteTransfer(t.id)" class="text-red-500 text-sm">Hapus</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
