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
const roSupplierList = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const expandedPRs = ref({});
const expandedROs = ref({});
const expandedWarehouses = ref({});
const notes = ref('');
const router = useRouter()

// Form untuk generate PO
const poForm = useForm({
    items_by_supplier: {}, // Akan diisi array per item
    ppn_enabled: false, // PPN switch
    discount_total_percent: 0, // Discount total percent
    discount_total_amount: 0, // Discount total amount
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
                        discount_percent: 0,
                        discount_amount: 0,
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

// Fetch RO Supplier list yang belum di-PO
const fetchROSupplierList = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/floor-order/supplier-available');
        
        // Pastikan response.data adalah array
        let data = response.data;
        
        // Jika response.data adalah object dengan numeric keys, konversi ke array
        if (!Array.isArray(data) && typeof data === 'object' && data !== null) {
            data = Object.values(data);
        }
        
        // Pastikan data adalah array
        if (!Array.isArray(data)) {
            data = [];
        }
        
        roSupplierList.value = data.map(ro => {
            return {
                ...ro,
                items: (ro.items || []).map(item => {
                    const itemKey = `ro_${ro.id}_${item.id}`;
                    if (!poForm.items_by_supplier[itemKey]) {
                        // Default: satu baris, qty penuh
                        poForm.items_by_supplier[itemKey] = [{
                            supplier_id: null,
                            qty: item.qty,
                            price: '',
                            discount_percent: 0,
                            discount_amount: 0,
                            last_price: '',
                            min_price: '',
                            max_price: '',
                            ro_id: ro.id,
                            ro_number: ro.order_number
                        }];
                    }
                    
                    return { 
                        ...item,
                        itemKey: itemKey,
                        ro_id: ro.id,
                        ro_number: ro.order_number
                    };
                })
            };
        });
    } catch (error) {
        console.error('Error fetching RO Supplier list:', error);
        Swal.fire('Error', 'Failed to fetch RO Supplier list', 'error');
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

// Group RO Suppliers by outlet
const groupedROSuppliers = computed(() => {
    const grouped = {};
    
    if (!roSupplierList.value || roSupplierList.value.length === 0) {
        return grouped;
    }
    
    roSupplierList.value.forEach(ro => {
        // Gunakan outlet_name dari data RO, bukan dari outlet relasi
        const outletId = ro.id_outlet || 'unknown';
        const outletName = ro.outlet_name || 'Unknown Outlet';
        
        if (!grouped[outletId]) {
            grouped[outletId] = {
                id: outletId,
                name: outletName,
                ros: []
            };
        }
        grouped[outletId].ros.push(ro);
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

// Toggle expand/collapse RO Supplier
const toggleRO = (roId) => {
    expandedROs.value[roId] = !expandedROs.value[roId];
};

// Toggle expand/collapse warehouse
const toggleWarehouse = (warehouseId) => {
    expandedWarehouses.value[warehouseId] = !expandedWarehouses.value[warehouseId];
};

// Bulk supplier selection
const bulkSupplier = ref(null);
const selectedItemsForBulk = ref([]);

// Select all items in a PR
const selectAllItemsInPR = (prId) => {
    const pr = prList.value.find(p => p.id === prId);
    if (pr) {
        selectedItemsForBulk.value = pr.items.map(item => item.id);
    }
};

// Select all items in a RO
const selectAllItemsInRO = (roId) => {
    const ro = roSupplierList.value.find(r => r.id === roId);
    if (ro) {
        selectedItemsForBulk.value = ro.items.map(item => item.itemKey);
    }
};

// Apply bulk supplier to selected items
const applyBulkSupplier = () => {
    if (!bulkSupplier.value) {
        Swal.fire('Error', 'Pilih supplier terlebih dahulu', 'error');
        return;
    }
    
    if (selectedItemsForBulk.value.length === 0) {
        Swal.fire('Error', 'Pilih item terlebih dahulu', 'error');
        return;
    }
    
    selectedItemsForBulk.value.forEach(itemId => {
        if (poForm.items_by_supplier[itemId]) {
            poForm.items_by_supplier[itemId].forEach(split => {
                split.supplier_id = bulkSupplier.value;
            });
        }
    });
    
    Swal.fire('Success', 'Supplier berhasil diterapkan ke item yang dipilih', 'success');
    selectedItemsForBulk.value = [];
    bulkSupplier.value = null;
};

// Clear bulk selection
const clearBulkSelection = () => {
    selectedItemsForBulk.value = [];
    bulkSupplier.value = null;
};

// Tambah baris split untuk item tertentu
function addSplit(itemId) {
    poForm.items_by_supplier[itemId].push({
        supplier_id: null,
        qty: 0,
        price: '',
        discount_percent: 0,
        discount_amount: 0,
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
            if (!supplierId || !split.price || !split.qty || split.qty < 0) return;
            if (!itemsBySupplier[supplierId]) itemsBySupplier[supplierId] = [];
            
            // Cek apakah ini dari RO Supplier atau PR
            if (split.ro_id) {
                // Dari RO Supplier
                // Cari item data dari roSupplierList
                const roData = roSupplierList.value.find(ro => ro.id === split.ro_id);
                const itemData = roData?.items.find(item => item.id === Number(itemId.split('_')[2]));
                
                
                itemsBySupplier[supplierId].push({
                    ro_id: split.ro_id,
                    ro_number: split.ro_number,
                    item_id: itemData?.item_id || Number(itemId.split('_')[2]), // Use actual item_id, fallback to key
                    item_name: itemData?.item_name || 'Unknown Item',
                    unit: itemData?.unit || null,
                    arrival_date: itemData?.arrival_date || null,
                    supplier_id: supplierId,
                    qty: split.qty,
                    price: split.price,
                    discount_percent: split.discount_percent || 0,
                    discount_amount: split.discount_amount || 0,
                    source: 'ro_supplier'
                });
            } else {
                // Dari PR
                // Cari item data dari prList
                const prItem = prList.value.flatMap(pr => pr.items).find(item => item.id == itemId);
                
                itemsBySupplier[supplierId].push({
                    id: Number(itemId),
                    supplier_id: supplierId,
                    qty: split.qty,
                    price: split.price,
                    discount_percent: split.discount_percent || 0,
                    discount_amount: split.discount_amount || 0,
                    arrival_date: prItem?.arrival_date || null,
                    source: 'pr_foods',
                    pr_item_id: prItem?.id || null
                });
            }
        });
    });

    try {
        loading.value = true;
        
        
        const response = await axios.post('/api/po-foods/generate', {
            items_by_supplier: itemsBySupplier,
            notes: notes.value,
            ppn_enabled: poForm.ppn_enabled,
            discount_total_percent: poForm.discount_total_percent || 0,
            discount_total_amount: poForm.discount_total_amount || 0
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

// Calculate item discount amount from percent
function calculateItemDiscount(split) {
    if (split.discount_percent > 0 && split.price && split.qty) {
        const subtotal = split.price * split.qty;
        split.discount_amount = subtotal * (split.discount_percent / 100);
    } else if (split.discount_percent === 0) {
        split.discount_amount = 0;
    }
}

// Calculate item discount percent from amount
function calculateItemDiscountPercent(split) {
    if (split.discount_amount > 0 && split.price && split.qty) {
        const subtotal = split.price * split.qty;
        split.discount_percent = (split.discount_amount / subtotal) * 100;
    } else if (split.discount_amount === 0) {
        split.discount_percent = 0;
    }
}

// Calculate item total after discount
function calculateItemTotal(split) {
    if (!split.price || !split.qty) return 0;
    const subtotal = split.price * split.qty;
    const discount = split.discount_amount || 0;
    return subtotal - discount;
}

// Calculate total discount amount from percent
function calculateTotalDiscount() {
    const subtotalAfterItemDiscount = calculateTotal();
    if (poForm.discount_total_percent > 0 && subtotalAfterItemDiscount > 0) {
        poForm.discount_total_amount = subtotalAfterItemDiscount * (poForm.discount_total_percent / 100);
    } else if (poForm.discount_total_percent === 0) {
        poForm.discount_total_amount = 0;
    }
}

// Calculate total discount percent from amount
function calculateTotalDiscountPercent() {
    const subtotalAfterItemDiscount = calculateTotal();
    if (poForm.discount_total_amount > 0 && subtotalAfterItemDiscount > 0) {
        poForm.discount_total_percent = (poForm.discount_total_amount / subtotalAfterItemDiscount) * 100;
    } else if (poForm.discount_total_amount === 0) {
        poForm.discount_total_percent = 0;
    }
}

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

// Calculate subtotal for all items (before discount per item)
const calculateSubtotalBeforeDiscount = () => {
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

// Calculate total discount per item
const calculateTotalItemDiscount = () => {
    let totalDiscount = 0;
    Object.values(poForm.items_by_supplier).forEach(splits => {
        splits.forEach(split => {
            if (split.price && split.qty) {
                const itemSubtotal = Number(split.price) * Number(split.qty);
                const discountPercent = Number(split.discount_percent || 0);
                const discountAmount = Number(split.discount_amount || 0);
                
                if (discountPercent > 0) {
                    totalDiscount += itemSubtotal * (discountPercent / 100);
                } else {
                    totalDiscount += discountAmount;
                }
            }
        });
    });
    return totalDiscount;
};

// Calculate total for all items (after discount per item)
const calculateTotal = () => {
    return calculateSubtotalBeforeDiscount() - calculateTotalItemDiscount();
};

// Calculate total discount amount (from discount_total_percent or discount_total_amount)
const calculateTotalDiscountAmount = () => {
    const subtotalAfterItemDiscount = calculateTotal();
    const discountTotalPercent = Number(poForm.discount_total_percent || 0);
    const discountTotalAmount = Number(poForm.discount_total_amount || 0);
    
    if (discountTotalPercent > 0) {
        return subtotalAfterItemDiscount * (discountTotalPercent / 100);
    }
    return discountTotalAmount;
};

// Calculate subtotal after discount total
const calculateSubtotalAfterDiscountTotal = () => {
    return calculateTotal() - calculateTotalDiscountAmount();
};

// Calculate PPN amount (after all discounts)
const calculatePPN = () => {
    if (!poForm.ppn_enabled) return 0;
    return calculateSubtotalAfterDiscountTotal() * 0.11;
};

// Calculate grand total
const calculateGrandTotal = () => {
    return calculateSubtotalAfterDiscountTotal() + calculatePPN();
};

// Fungsi untuk mengambil harga terakhir untuk semua item
const fetchLastPrices = async () => {
    // Fetch untuk PR items
    for (const pr of prList.value) {
        for (const item of pr.items) {
            try {
                const res = await axios.get('/api/items/last-price', {
                    params: {
                        item_id: item.item_id || item.item?.id,
                        unit: item.unit
                    }
                });
                
                if (poForm.items_by_supplier[item.id]) {
                    poForm.items_by_supplier[item.id].forEach(split => {
                        split.last_price = res.data.last_price ?? 0;
                        split.min_price = res.data.min_price ?? 0;
                        split.max_price = res.data.max_price ?? 0;
                        // Konversi ke medium dan large
                        const { priceMedium, priceLarge } = convertPrice(res.data.last_price ?? 0, item);
                        split.price_medium = priceMedium;
                        split.price_large = priceLarge;
                    });
                }
            } catch (error) {
                // Handle error gracefully - set default values if item not found
                console.warn(`Item ${item.item_id || item.item?.id} (${item.name}) not found in inventory, using default prices`);
                
                if (poForm.items_by_supplier[item.id]) {
                    poForm.items_by_supplier[item.id].forEach(split => {
                        split.last_price = 0;
                        split.min_price = 0;
                        split.max_price = 0;
                        split.price_medium = 0;
                        split.price_large = 0;
                    });
                }
            }
        }
    }
    
    // Fetch untuk RO Supplier items
    for (const ro of roSupplierList.value) {
        for (const item of ro.items) {
            try {
                const res = await axios.get('/api/items/last-price', {
                    params: {
                        item_id: item.item_id,
                        unit: item.unit
                    }
                });
                
                if (poForm.items_by_supplier[item.itemKey]) {
                    poForm.items_by_supplier[item.itemKey].forEach(split => {
                        split.last_price = res.data.last_price ?? 0;
                        split.min_price = res.data.min_price ?? 0;
                        split.max_price = res.data.max_price ?? 0;
                        // Konversi ke medium dan large
                        const { priceMedium, priceLarge } = convertPrice(res.data.last_price ?? 0, item);
                        split.price_medium = priceMedium;
                        split.price_large = priceLarge;
                    });
                }
            } catch (error) {
                // Handle error gracefully - set default values if item not found
                console.warn(`Item ${item.item_id} (${item.item_name}) not found in inventory, using default prices`);
                
                if (poForm.items_by_supplier[item.itemKey]) {
                    poForm.items_by_supplier[item.itemKey].forEach(split => {
                        split.last_price = 0;
                        split.min_price = 0;
                        split.max_price = 0;
                        split.price_medium = 0;
                        split.price_large = 0;
                    });
                }
            }
        }
    }
};

onMounted(async () => {
    await fetchPRList();
    await fetchROSupplierList();
    await fetchSuppliers();
    await fetchLastPrices();
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
                                                 <!-- Discount Total -->
                         <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                             <h4 class="text-sm font-semibold text-yellow-800 mb-3">Diskon Total</h4>
                             <div class="grid grid-cols-2 gap-4">
                                 <div>
                                     <label class="block text-sm font-medium text-gray-700 mb-1">Diskon (%)</label>
                                     <input type="number" v-model.number="poForm.discount_total_percent" min="0" max="100" step="0.01" placeholder="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" @input="calculateTotalDiscount" />
                                 </div>
                                 <div>
                                     <label class="block text-sm font-medium text-gray-700 mb-1">Diskon (Rp)</label>
                                     <input type="number" v-model.number="poForm.discount_total_amount" min="0" step="0.01" placeholder="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" @input="calculateTotalDiscountPercent" />
                                 </div>
                             </div>
                         </div>
                                                 <!-- PPN Switch -->
                         <div class="mb-6 flex items-center">
                             <input type="checkbox" id="ppnSwitch" v-model="poForm.ppn_enabled" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                             <label for="ppnSwitch" class="ml-2 text-sm text-gray-700">Include PPN (11%)</label>
                         </div>
                         
                         <!-- Bulk Supplier Selection -->
                         <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                             <h4 class="text-sm font-semibold text-blue-800 mb-3">Bulk Supplier Selection</h4>
                             <div class="flex flex-wrap gap-4 items-end">
                                 <div class="flex-1 min-w-64">
                                     <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Supplier</label>
                                     <Multiselect
                                         v-model="bulkSupplier"
                                         :options="suppliers"
                                         :searchable="true"
                                         :close-on-select="true"
                                         :clear-on-select="false"
                                         :preserve-search="true"
                                         placeholder="Pilih supplier untuk bulk assign..."
                                         track-by="id"
                                         label="name"
                                         :preselect-first="false"
                                     />
                                 </div>
                                 <div class="flex gap-2">
                                     <button 
                                         @click="applyBulkSupplier"
                                         :disabled="!bulkSupplier || selectedItemsForBulk.length === 0"
                                         class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                     >
                                         Terapkan ke Item Dipilih
                                     </button>
                                     <button 
                                         @click="clearBulkSelection"
                                         class="px-4 py-2 bg-gray-500 text-white rounded-md text-sm font-medium hover:bg-gray-600"
                                     >
                                         Clear
                                     </button>
                                 </div>
                             </div>
                             <div v-if="selectedItemsForBulk.length > 0" class="mt-3 text-sm text-blue-600">
                                 <strong>{{ selectedItemsForBulk.length }}</strong> item dipilih untuk bulk assign
                             </div>
                         </div>
                        <!-- PR List -->
                        <div v-if="loading" class="flex justify-center items-center py-8">
                            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div v-else>
                            <!-- PR Foods Section -->
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Purchase Request (PR) Foods</h3>
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
                                                 <div class="flex-1">
                                                     <span class="font-medium">{{ pr.number }} - {{ pr.date }}</span>
                                                     <div v-if="pr.description" class="text-sm text-gray-600 mt-1">
                                                         <strong>Notes:</strong> {{ pr.description }}
                                                     </div>
                                                 </div>
                                                 <button 
                                                     @click.stop="selectAllItemsInPR(pr.id)"
                                                     class="ml-2 px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded text-xs font-medium"
                                                 >
                                                     Select All Items
                                                 </button>
                                             </div>
                                        </div>

                                        <!-- PR Items -->
                                        <div v-if="expandedPRs[pr.id]" class="p-4 border-t overflow-x-auto">
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                                                                         <thead class="bg-gray-50">
                                                         <tr>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                 <input 
                                                                     type="checkbox" 
                                                                     @change="selectAllItemsInPR(pr.id)"
                                                                     class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                                 />
                                                             </th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Kedatangan</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diskon %</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diskon (Rp)</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                                         </tr>
                                                     </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                                                                                 <template v-for="item in pr.items" :key="item.id">
                                                             <tr v-for="(split, idx) in poForm.items_by_supplier[item.id]" :key="idx">
                                                                 <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                     <input 
                                                                         type="checkbox" 
                                                                         :value="item.id"
                                                                         v-model="selectedItemsForBulk"
                                                                         class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                                     />
                                                                 </td>
                                                                 <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.name }}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <input type="number" v-model="split.qty" min="0" step="0.01" :max="item.quantity - totalQtyUsed(item.id, idx)" class="w-20 border rounded px-2 py-1" />
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.arrival_date ? new Date(item.arrival_date).toLocaleDateString('id-ID') : '-' }}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <div v-if="item.note" class="max-w-xs">
                                                                        <span class="text-gray-600">{{ item.note }}</span>
                                                                    </div>
                                                                    <span v-else class="text-gray-400">-</span>
                                                                </td>
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
                                                                    <input type="number" v-model.number="split.discount_percent" min="0" max="100" step="0.01" placeholder="%" class="w-20 border rounded px-2 py-1" @input="calculateItemDiscount(split)" />
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <input type="number" v-model.number="split.discount_amount" min="0" step="0.01" placeholder="Rp" class="w-24 border rounded px-2 py-1" @input="calculateItemDiscountPercent(split)" />
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    {{ formatRupiah(calculateItemTotal(split)) }}
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
                            </div>

                            <!-- RO Supplier Section -->
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Request Order (RO) Supplier</h3>
                                <!-- Outlet Groups -->
                                <div v-for="warehouse in Object.values(groupedROSuppliers)" :key="warehouse.id" class="mb-6 border rounded-lg overflow-hidden">
                                    <!-- Outlet Header -->
                                    <div 
                                        class="bg-green-50 px-4 py-3 flex justify-between items-center cursor-pointer hover:bg-green-100 border-b"
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
                                            <span class="font-semibold text-green-800">{{ warehouse.name }}</span>
                                            <span class="ml-2 text-sm text-green-600">({{ warehouse.ros.length }} RO)</span>
                                        </div>
                                    </div>

                                    <!-- RO List for this Outlet -->
                                    <div v-if="expandedWarehouses[warehouse.id]">
                                        <div v-for="ro in warehouse.ros" :key="ro.id" class="border-b last:border-b-0">
                                            <!-- RO Header -->
                                            <div 
                                                class="bg-gray-50 px-4 py-3 flex justify-between items-center cursor-pointer hover:bg-gray-100"
                                                @click="toggleRO(ro.id)"
                                            >
                                                                                             <div class="flex items-center">
                                                 <svg 
                                                     class="w-5 h-5 mr-2 transition-transform"
                                                     :class="{ 'transform rotate-90': expandedROs[ro.id] }"
                                                     fill="none" 
                                                     stroke="currentColor" 
                                                     viewBox="0 0 24 24"
                                                 >
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                 </svg>
                                                 <div class="flex-1">
                                                     <span class="font-medium">{{ ro.order_number }} - {{ ro.tanggal }}</span>
                                                     <span class="ml-2 text-xs text-gray-500">({{ ro.outlet_name }})</span>
                                                 </div>
                                                 <button 
                                                     @click.stop="selectAllItemsInRO(ro.id)"
                                                     class="ml-2 px-3 py-1 bg-green-100 hover:bg-green-200 text-green-700 rounded text-xs font-medium"
                                                 >
                                                     Select All Items
                                                 </button>
                                             </div>
                                            </div>

                                            <!-- RO Items -->
                                            <div v-if="expandedROs[ro.id]" class="p-4 border-t overflow-x-auto">
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-gray-200">
                                                                                                                 <thead class="bg-gray-50">
                                                                                                                     <tr>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                 <input 
                                                                     type="checkbox" 
                                                                     @change="selectAllItemsInRO(ro.id)"
                                                                     class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                                                 />
                                                             </th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diskon %</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diskon (Rp)</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                                             <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                                         </tr>
                                                         </thead>
                                                        <tbody class="bg-white divide-y divide-gray-200">
                                                                                                                         <template v-for="item in ro.items" :key="item.id">
                                                                 <tr v-for="(split, idx) in poForm.items_by_supplier[item.itemKey]" :key="idx">
                                                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                         <input 
                                                                             type="checkbox" 
                                                                             :value="item.itemKey"
                                                                             v-model="selectedItemsForBulk"
                                                                             class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                                                         />
                                                                     </td>
                                                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.item_name }}</td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                        <input type="number" v-model="split.qty" min="0" step="0.01" :max="item.qty - totalQtyUsed(item.itemKey, idx)" class="w-20 border rounded px-2 py-1" />
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                        <div v-if="item.note" class="max-w-xs">
                                                                            <span class="text-gray-600">{{ item.note }}</span>
                                                                        </div>
                                                                        <span v-else class="text-gray-400">-</span>
                                                                    </td>
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
                                                                        <input type="number" v-model.number="split.discount_percent" min="0" max="100" step="0.01" placeholder="%" class="w-20 border rounded px-2 py-1" @input="calculateItemDiscount(split)" />
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                        <input type="number" v-model.number="split.discount_amount" min="0" step="0.01" placeholder="Rp" class="w-24 border rounded px-2 py-1" @input="calculateItemDiscountPercent(split)" />
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                        {{ formatRupiah(calculateItemTotal(split)) }}
                                                                    </td>
                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                        <button type="button" @click="addSplit(item.itemKey)" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded text-xs mr-1">Split</button>
                                                                        <button v-if="poForm.items_by_supplier[item.itemKey].length > 1" type="button" @click="removeSplit(item.itemKey, idx)" class="bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded text-xs">Hapus</button>
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
                            </div>

                            <!-- Total Calculation -->
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold mb-3">Total Calculation</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Subtotal:</span>
                                        <span class="font-medium">{{ formatRupiah(calculateSubtotalBeforeDiscount()) }}</span>
                                    </div>
                                    <div v-if="calculateTotalItemDiscount() > 0" class="flex justify-between">
                                        <span class="text-gray-600">Diskon per Item:</span>
                                        <span class="font-medium text-red-600">- {{ formatRupiah(calculateTotalItemDiscount()) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Subtotal setelah Diskon Item:</span>
                                        <span class="font-medium">{{ formatRupiah(calculateTotal()) }}</span>
                                    </div>
                                    <div v-if="calculateTotalDiscountAmount() > 0" class="flex justify-between">
                                        <span class="text-gray-600">Diskon Total:</span>
                                        <span class="font-medium text-red-600">- {{ formatRupiah(calculateTotalDiscountAmount()) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Subtotal setelah Diskon Total:</span>
                                        <span class="font-medium">{{ formatRupiah(calculateSubtotalAfterDiscountTotal()) }}</span>
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