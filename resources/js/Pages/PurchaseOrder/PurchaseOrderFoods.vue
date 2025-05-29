<script setup>
import { ref, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { debounce } from 'lodash';

const prList = ref([]);
const selectedPRs = ref([]);
const items = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const poList = ref([]);

const props = defineProps({
  purchaseOrders: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

const debouncedSearch = debounce(() => {
  router.get('/po-foods', { search: search.value, status: selectedStatus.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}
function onStatusChange() {
  debouncedSearch();
}
function onDateChange() {
  debouncedSearch();
}
function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}
function openCreate() {
  router.visit('/po-foods/create');
}
function openEdit(id) {
  router.visit(`/po-foods/${id}/edit`);
}
function openDetail(id) {
  router.visit(`/po-foods/${id}`);
}
async function hapus(po) {
  const result = await Swal.fire({
    title: 'Hapus PO Foods?',
    text: `Yakin ingin menghapus PO ${po.number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('po-foods.destroy', po.id), {
    onSuccess: () => Swal.fire('Berhasil', 'PO berhasil dihapus!', 'success'),
  });
}

// Form untuk generate PO
const poForm = useForm({
    items: [],
});

// Fetch PR list yang belum di-PO
const fetchPRList = async () => {
    try {
        const response = await axios.get('/api/pr-foods/available');
        prList.value = response.data;
    } catch (error) {
        console.error('Error fetching PR list:', error);
        Swal.fire('Error', 'Failed to fetch PR list', 'error');
    }
};

// Fetch suppliers
const fetchSuppliers = async () => {
    try {
        const response = await axios.get('/api/suppliers');
        suppliers.value = response.data;
    } catch (error) {
        console.error('Error fetching suppliers:', error);
        Swal.fire('Error', 'Failed to fetch suppliers', 'error');
    }
};

// Ketika PR dipilih, fetch items dari PR tersebut
const handlePRSelection = async () => {
    if (selectedPRs.value.length === 0) {
        items.value = [];
        return;
    }

    try {
        const response = await axios.post('/api/pr-foods/items', {
            pr_ids: selectedPRs.value
        });
        items.value = response.data;
    } catch (error) {
        console.error('Error fetching PR items:', error);
        Swal.fire('Error', 'Failed to fetch PR items', 'error');
    }
};

// Generate PO berdasarkan supplier
const generatePO = async () => {
    // Validasi
    const itemsWithoutSupplier = items.value.filter(item => !item.supplier_id);
    if (itemsWithoutSupplier.length > 0) {
        Swal.fire('Error', 'Please select supplier for all items', 'error');
        return;
    }

    // Kelompokkan items berdasarkan supplier
    const itemsBySupplier = {};
    items.value.forEach(item => {
        if (!itemsBySupplier[item.supplier_id]) {
            itemsBySupplier[item.supplier_id] = [];
        }
        itemsBySupplier[item.supplier_id].push(item);
    });

    try {
        loading.value = true;
        const response = await axios.post('/api/po-foods/generate', {
            items_by_supplier: itemsBySupplier
        });
        
        Swal.fire('Success', 'PO has been generated successfully', 'success');
        // Reset form
        selectedPRs.value = [];
        items.value = [];
        await fetchPRList();
    } catch (error) {
        console.error('Error generating PO:', error);
        Swal.fire('Error', 'Failed to generate PO', 'error');
    } finally {
        loading.value = false;
    }
};

// Fetch PO list
const fetchPOList = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/po-foods');
        poList.value = response.data;
    } catch (error) {
        console.error('Error fetching PO list:', error);
        Swal.fire('Error', 'Failed to fetch PO list', 'error');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchPRList();
    fetchSuppliers();
    fetchPOList();
});
</script>

<template>
    <AppLayout>
        <div class="w-full py-8 px-0">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-blue-500"></i> Purchase Order Foods
                </h1>
                <button
                    @click="openCreate"
                    class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
                >
                    + Buat PO Foods Baru
                </button>
            </div>
            <div class="py-4">
                <div class="flex flex-wrap gap-3 mb-4 items-center">
                    <input
                        v-model="search"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Cari nomor PO..."
                        class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                    />
                    <select v-model="selectedStatus" @change="onStatusChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="receive">Receive</option>
                        <option value="payment">Payment</option>
                    </select>
                    <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
                    <span>-</span>
                    <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
                </div>
            </div>
            <div class="py-12">
                <div class="w-full px-0">
                    <div class="bg-white overflow-hidden shadow-xl rounded-none p-0">
                        <!-- PO List -->
                        <div v-if="loading" class="flex justify-center items-center py-8">
                            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div v-else>
                            <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
                                <table class="w-full min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No. PO</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">PR Numbers</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tgl Kedatangan</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal Print</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Created By</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!purchaseOrders?.data || purchaseOrders.data.length === 0">
                                            <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data PO Foods.</td>
                                        </tr>
                                        <tr v-for="po in purchaseOrders?.data || []" :key="po.id" class="hover:bg-blue-50 transition shadow-sm">
                                            <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ po.number }}</td>
                                            <td class="px-6 py-3">{{ new Date(po.date).toLocaleDateString('id-ID') }}</td>
                                            <td class="px-6 py-3">
                                                <div class="flex flex-wrap gap-1">
                                                    <span v-for="pr in po.pr_numbers" :key="pr" 
                                                          class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                                        {{ pr }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3">{{ po.supplier?.name }}</td>
                                            <td class="px-6 py-3">{{ po.arrival_date ? new Date(po.arrival_date).toLocaleDateString('id-ID') : '-' }}</td>
                                            <td class="px-6 py-3">{{ po.printed_at ? new Date(po.printed_at).toLocaleDateString('id-ID') + ' ' + new Date(po.printed_at).toLocaleTimeString('id-ID') : '-' }}</td>
                                            <td class="px-6 py-3">{{ po.creator?.nama_lengkap }}</td>
                                            <td class="px-6 py-3">
                                                <span :class="{
                                                    'bg-gray-100 text-gray-700': po.status === 'draft',
                                                    'bg-green-100 text-green-700': po.status === 'approved',
                                                    'bg-red-100 text-red-700': po.status === 'rejected',
                                                    'bg-yellow-100 text-yellow-700': po.status === 'receive',
                                                    'bg-purple-100 text-purple-700': po.status === 'payment',
                                                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                                                    {{ po.status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3">
                                                <div class="flex gap-2">
                                                    <button @click="openDetail(po.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                        Detail
                                                    </button>
                                                    <button @click="openEdit(po.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                                        Edit
                                                    </button>
                                                    <button @click="hapus(po)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                                        Hapus
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination -->
                            <div class="flex justify-end mt-4 gap-2">
                                <button
                                    v-for="link in purchaseOrders?.links || []"
                                    :key="link.label"
                                    :disabled="!link.url"
                                    @click="goToPage(link.url)"
                                    v-html="link.label"
                                    class="px-3 py-1 rounded-lg border text-sm font-semibold"
                                    :class="[
                                        link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template> 