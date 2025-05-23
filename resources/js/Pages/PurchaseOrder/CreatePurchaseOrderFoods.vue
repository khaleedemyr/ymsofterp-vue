<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router'

const prList = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const expandedPRs = ref({});
const notes = ref('');
const router = useRouter()

// Form untuk generate PO
const poForm = useForm({
    items_by_supplier: {}, // Akan diisi array per item
});

// Fetch PR list yang belum di-PO
const fetchPRList = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/pr-foods/available');
        prList.value = response.data.map(pr => ({
            ...pr,
            items: pr.items.map(item => {
                if (!poForm.items_by_supplier[item.id]) {
                    // Default: satu baris, qty penuh
                    poForm.items_by_supplier[item.id] = [{
                        supplier_id: '',
                        qty: item.quantity,
                        price: '',
                        last_price: '',
                        min_price: '',
                        max_price: ''
                    }];
                }
                return { ...item };
            })
        }));
    } catch (error) {
        console.error('Error fetching PR list:', error);
        Swal.fire('Error', 'Failed to fetch PR list', 'error');
    } finally {
        loading.value = false;
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

// Toggle expand/collapse PR
const togglePR = (prId) => {
    expandedPRs.value[prId] = !expandedPRs.value[prId];
};

// Tambah baris split untuk item tertentu
function addSplit(itemId) {
    poForm.items_by_supplier[itemId].push({
        supplier_id: '',
        qty: 0,
        price: '',
        last_price: '',
        min_price: '',
        max_price: ''
    });
}
// Hapus baris split tertentu
function removeSplit(itemId, idx) {
    if (poForm.items_by_supplier[itemId].length > 1) {
        poForm.items_by_supplier[itemId].splice(idx, 1);
    }
}
// Hitung total qty split (kecuali idx tertentu jika sedang edit)
function totalQtyUsed(itemId, exceptIdx = -1) {
    return poForm.items_by_supplier[itemId]
        .filter((_, idx) => idx !== exceptIdx)
        .reduce((sum, split) => sum + Number(split.qty || 0), 0);
}

function validateSplitQty() {
    for (const [itemId, splits] of Object.entries(poForm.items_by_supplier)) {
        const total = splits.reduce((sum, s) => sum + Number(s.qty || 0), 0);
        // Cari item di prList
        const item = prList.value.flatMap(pr => pr.items).find(i => i.id == itemId);
        if (item && total > item.quantity) {
            Swal.fire('Error', `Total qty split untuk item "${item.name}" melebihi qty PR (${item.quantity})`, 'error');
            return false;
        }
    }
    return true;
}

// Generate PO berdasarkan supplier
const generatePO = async () => {
    if (!validateSplitQty()) return;
    // Hanya kirim field yang diperlukan
    const itemsBySupplier = {};
    Object.entries(poForm.items_by_supplier).forEach(([itemId, splits]) => {
        splits.forEach(split => {
            if (!split.supplier_id || !split.price || !split.qty || split.qty <= 0) return;
            if (!itemsBySupplier[split.supplier_id]) itemsBySupplier[split.supplier_id] = [];
            itemsBySupplier[split.supplier_id].push({
                id: Number(itemId),
                supplier_id: split.supplier_id,
                qty: split.qty,
                price: split.price
            });
        });
    });

    try {
        loading.value = true;
        const response = await axios.post('/api/po-foods/generate', {
            items_by_supplier: itemsBySupplier,
            notes: notes.value
        });
        Swal.fire('Success', 'PO has been generated successfully', 'success')
            .then(() => {
                window.location.href = '/po-foods';
            });
    } catch (error) {
        console.error('Error generating PO:', error);
        Swal.fire('Error', 'Failed to generate PO', 'error');
    } finally {
        loading.value = false;
    }
};

// Tambahkan fungsi untuk mengambil konversi dari item atau item.item
function getSmallConv(item) {
    return Number(item.small_conversion_qty || (item.item && item.item.small_conversion_qty) || 1);
}
function getMediumConv(item) {
    return Number(item.medium_conversion_qty || (item.item && item.item.medium_conversion_qty) || 1);
}
function getMediumUnitName(item) {
    return item.medium_unit_name || (item.item && item.item.mediumUnit && item.item.mediumUnit.name) || '';
}
function getLargeUnitName(item) {
    return item.large_unit_name || (item.item && item.item.largeUnit && item.item.largeUnit.name) || '';
}

function convertPrice(priceSmall, item) {
    const smallConv = getSmallConv(item);
    const mediumConv = getMediumConv(item);
    let priceMedium = priceSmall * smallConv;
    let priceLarge = priceSmall * smallConv * mediumConv;
    return {
        priceSmall,
        priceMedium,
        priceLarge
    };
}

const onSupplierChange = async (item) => {
    const supplierId = poForm.items_by_supplier[item.id][0].supplier_id;
    if (!supplierId) {
        poForm.items_by_supplier[item.id].forEach(split => {
            split.price = '';
            split.last_price = 0;
            split.min_price = 0;
            split.max_price = 0;
            split.price_medium = 0;
            split.price_large = 0;
        });
        return;
    }
    try {
        console.log('Item data:', item); // Debug log
        const res = await axios.get('/api/items/last-price', {
            params: {
                item_id: item.item_id || item.item?.id, // Try both item_id and item.id
                supplier_id: supplierId,
                unit: item.unit
            }
        });
        poForm.items_by_supplier[item.id].forEach(split => {
            split.price = res.data.last_price ?? 0;
            split.last_price = res.data.last_price ?? 0;
            split.min_price = res.data.min_price ?? 0;
            split.max_price = res.data.max_price ?? 0;
            // Konversi ke medium dan large
            const { priceMedium, priceLarge } = convertPrice(res.data.last_price ?? 0, item);
            split.price_medium = priceMedium;
            split.price_large = priceLarge;
        });
    } catch (error) {
        console.error('Error fetching last price:', error);
        poForm.items_by_supplier[item.id].forEach(split => {
            split.price = 0;
            split.last_price = 0;
            split.min_price = 0;
            split.max_price = 0;
            split.price_medium = 0;
            split.price_large = 0;
        });
    }
};

function formatRupiah(value) {
    if (!value) return 'Rp 0';
    return 'Rp ' + Number(value).toLocaleString('id-ID');
}

function goBack() {
    window.location.href = '/po-foods'
}

onMounted(() => {
    fetchPRList();
    fetchSuppliers();
});
</script>

<template>
    <AppLayout title="Create Purchase Order Foods">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create Purchase Order Foods
            </h2>
        </template>

        <div class="py-12">
            <div class="w-full px-0">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="mb-4">
                            <button @click="goBack" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                                &larr; Kembali
                            </button>
                        </div>
                        <!-- Notes Input -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="notes" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        <!-- PR List -->
                        <div v-if="loading" class="flex justify-center items-center py-8">
                            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div v-else>
                            <div v-for="pr in prList" :key="pr.id" class="mb-4 border rounded-lg overflow-hidden">
                                <!-- PR Header -->
                                <div 
                                    class="bg-gray-50 px-4 py-3 flex justify-between items-center cursor-pointer hover:bg-gray-100"
                                    @click="togglePR(pr.id)"
                                >
                                    <div class="flex items-center">
                                        <svg 
                                            class="w-5 h-5 mr-2 transition-transform"
                                            :class="{ 'transform rotate-90': expandedPRs[pr.id] }"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                        <span class="font-medium">{{ pr.number }} - {{ pr.date }}</span>
                                    </div>
                                </div>

                                <!-- PR Items -->
                                <div v-if="expandedPRs[pr.id]" class="p-4 border-t overflow-x-auto">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Kedatangan</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <template v-for="item in pr.items" :key="item.id">
                                                    <tr v-for="(split, idx) in poForm.items_by_supplier[item.id]" :key="idx">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.name }}</td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            <input type="number" v-model="split.qty" :max="item.quantity - totalQtyUsed(item.id, idx)" class="w-20 border rounded px-2 py-1" />
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.arrival_date ? new Date(item.arrival_date).toLocaleDateString('id-ID') : '-' }}</td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            <select v-model="split.supplier_id" @change="onSupplierChange(item)" class="w-40 mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                                <option value="">Select Supplier</option>
                                                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                                                    {{ supplier.name }}
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            <input type="number" v-model="split.price" placeholder="Enter price" class="w-24 border rounded px-2 py-1" />
                                                            <div>
                                                                <small class="text-gray-400">
                                                                    Last: {{ formatRupiah(split.last_price ?? 0) }} |
                                                                    Min: {{ formatRupiah(split.min_price ?? 0) }} |
                                                                    Max: {{ formatRupiah(split.max_price ?? 0) }}
                                                                </small>
                                                            </div>
                                                            <div v-if="split.price_medium || split.price_large" class="text-xs text-blue-500 mt-1">
                                                                <div v-if="split.price_medium">
                                                                    Medium: {{ formatRupiah(split.price_medium) }} <span v-if="item.medium_unit_name">/ {{ item.medium_unit_name }}</span>
                                                                </div>
                                                                <div v-if="split.price_large">
                                                                    Large: {{ formatRupiah(split.price_large) }} <span v-if="item.large_unit_name">/ {{ item.large_unit_name }}</span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            {{ formatRupiah((split.price || 0) * split.qty) }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            <button type="button" @click="addSplit(item.id)" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded text-xs mr-1">Split</button>
                                                            <button v-if="poForm.items_by_supplier[item.id].length > 1" type="button" @click="removeSplit(item.id, idx)" class="bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded text-xs">Hapus</button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Generate PO Button -->
                            <div class="mt-6 flex justify-end">
                                <button
                                    @click="generatePO"
                                    :disabled="loading"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring focus:ring-blue-300 disabled:opacity-25 transition"
                                >
                                    <svg v-if="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ loading ? 'Generating...' : 'Generate PO' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template> 