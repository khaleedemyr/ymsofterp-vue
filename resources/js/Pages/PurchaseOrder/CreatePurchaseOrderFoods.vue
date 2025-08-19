<script setup>
import { ref, onMounted, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router'
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const prList = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const expandedPRs = ref({});
const expandedWarehouses = ref({});
const notes = ref('');
const router = useRouter()

// Form untuk generate PO
const poForm = useForm({
    items_by_supplier: {}, // Akan diisi array per item
    ppn_enabled: false, // PPN switch
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
                        supplier_id: null,
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

// Group PRs by warehouse
const groupedPRs = computed(() => {
    const grouped = {};
    prList.value.forEach(pr => {
        const warehouseId = pr.warehouse_id || 'unknown';
        const warehouseName = pr.warehouse_name || 'Unknown Warehouse';
        
        if (!grouped[warehouseId]) {
            grouped[warehouseId] = {
                id: warehouseId,
                name: warehouseName,
                prs: []
            };
        }
        grouped[warehouseId].prs.push(pr);
    });
    return grouped;
});

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

// Toggle expand/collapse warehouse
const toggleWarehouse = (warehouseId) => {
    expandedWarehouses.value[warehouseId] = !expandedWarehouses.value[warehouseId];
};

// Tambah baris split untuk item tertentu
function addSplit(itemId) {
    poForm.items_by_supplier[itemId].push({
        supplier_id: null,
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

async function validateSplitQty() {
    for (const [itemId, splits] of Object.entries(poForm.items_by_supplier)) {
        const total = splits.reduce((sum, s) => sum + Number(s.qty || 0), 0);
        // Cari item di prList
        const item = prList.value.flatMap(pr => pr.items).find(i => i.id == itemId);
        if (item && total > item.quantity) {
            // Show warning instead of error, allowing user to proceed
            const result = await Swal.fire({
                title: 'Quantity Exceeds PR',
                text: `Total qty split untuk item "${item.name}" (${total}) melebihi qty PR (${item.quantity}). Apakah Anda yakin ingin melanjutkan?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal'
            });
            return result.isConfirmed;
        }
    }
    return true;
}

// Generate PO berdasarkan supplier
const generatePO = async () => {
    if (!(await validateSplitQty())) return;
    // Hanya kirim field yang diperlukan
    const itemsBySupplier = {};
    Object.entries(poForm.items_by_supplier).forEach(([itemId, splits]) => {
        splits.forEach(split => {
            const supplierId = split.supplier_id ? split.supplier_id.id : null;
            if (!supplierId || !split.price || !split.qty || split.qty <= 0) return;
            if (!itemsBySupplier[supplierId]) itemsBySupplier[supplierId] = [];
            itemsBySupplier[supplierId].push({
                id: Number(itemId),
                supplier_id: supplierId,
                qty: split.qty,
                price: split.price
            });
        });
    });

    try {
        loading.value = true;
        const response = await axios.post('/api/po-foods/generate', {
            items_by_supplier: itemsBySupplier,
            notes: notes.value,
            ppn_enabled: poForm.ppn_enabled
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
    const supplier = poForm.items_by_supplier[item.id][0].supplier_id;
    const supplierId = supplier ? supplier.id : null;
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
    // Ambil filter state dari sessionStorage
    try {
        const savedFilters = sessionStorage.getItem('po-foods-filters');
        if (savedFilters) {
            const filters = JSON.parse(savedFilters);
            const queryParams = new URLSearchParams();
            
            if (filters.search) queryParams.append('search', filters.search);
            if (filters.status) queryParams.append('status', filters.status);
            if (filters.from) queryParams.append('from', filters.from);
            if (filters.to) queryParams.append('to', filters.to);
            if (filters.perPage) queryParams.append('perPage', filters.perPage);
            
            const queryString = queryParams.toString();
            const url = queryString ? `/po-foods?${queryString}` : '/po-foods';
            window.location.href = url;
        } else {
            window.location.href = '/po-foods';
        }
    } catch (error) {
        console.error('Error restoring filters:', error);
        window.location.href = '/po-foods';
    }
}

// Calculate total for all items
const calculateTotal = () => {
    let total = 0;
    Object.values(poForm.items_by_supplier).forEach(splits => {
        splits.forEach(split => {
            if (split.price && split.qty) {
                total += Number(split.price) * Number(split.qty);
            }
        });
    });
    return total;
};

// Calculate PPN amount
const calculatePPN = () => {
    if (!poForm.ppn_enabled) return 0;
    return calculateTotal() * 0.11;
};

// Calculate grand total
const calculateGrandTotal = () => {
    return calculateTotal() + calculatePPN();
};

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
                        <!-- PPN Switch -->
                        <div class="mb-6 flex items-center">
                            <input type="checkbox" id="ppnSwitch" v-model="poForm.ppn_enabled" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="ppnSwitch" class="ml-2 text-sm text-gray-700">Include PPN (11%)</label>
                        </div>
                        <!-- PR List -->
                        <div v-if="loading" class="flex justify-center items-center py-8">
                            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div v-else>
                            <!-- Warehouse Groups -->
                            <div v-for="warehouse in Object.values(groupedPRs)" :key="warehouse.id" class="mb-6 border rounded-lg overflow-hidden">
                                <!-- Warehouse Header -->
                                <div 
                                    class="bg-blue-50 px-4 py-3 flex justify-between items-center cursor-pointer hover:bg-blue-100 border-b"
                                    @click="toggleWarehouse(warehouse.id)"
                                >
                                    <div class="flex items-center">
                                        <svg 
                                            class="w-5 h-5 mr-2 transition-transform"
                                            :class="{ 'transform rotate-90': expandedWarehouses[warehouse.id] }"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                        <span class="font-semibold text-blue-800">{{ warehouse.name }}</span>
                                        <span class="ml-2 text-sm text-blue-600">({{ warehouse.prs.length }} PR)</span>
                                    </div>
                                </div>

                                <!-- PR List for this Warehouse -->
                                <div v-if="expandedWarehouses[warehouse.id]">
                                    <div v-for="pr in warehouse.prs" :key="pr.id" class="border-b last:border-b-0">
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
                                                                    <Multiselect
                                                                        v-model="split.supplier_id"
                                                                        :options="suppliers"
                                                                        :searchable="true"
                                                                        :close-on-select="true"
                                                                        :clear-on-select="false"
                                                                        :preserve-search="true"
                                                                        placeholder="Pilih atau cari supplier..."
                                                                        track-by="id"
                                                                        label="name"
                                                                        :preselect-first="false"
                                                                        @input="onSupplierChange(item)"
                                                                        class="w-40"
                                                                    />
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
                                </div>
                            </div>

                            <!-- Total Calculation -->
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-3">Total Calculation</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Subtotal:</span>
                                        <span class="font-medium">{{ formatRupiah(calculateTotal()) }}</span>
                                    </div>
                                    <div v-if="poForm.ppn_enabled" class="flex justify-between">
                                        <span class="text-gray-600">PPN (11%):</span>
                                        <span class="font-medium text-blue-600">{{ formatRupiah(calculatePPN()) }}</span>
                                    </div>
                                    <div class="flex justify-between border-t pt-2">
                                        <span class="text-lg font-semibold">Grand Total:</span>
                                        <span class="text-lg font-bold text-green-600">{{ formatRupiah(calculateGrandTotal()) }}</span>
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

<style scoped>
/* Custom styling for vue-multiselect */
:deep(.multiselect) {
  min-height: 38px;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 8px 12px;
}

:deep(.multiselect__single) {
  padding: 8px 12px;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__input) {
  padding: 8px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

:deep(.multiselect__option) {
  padding: 8px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}
</style> 