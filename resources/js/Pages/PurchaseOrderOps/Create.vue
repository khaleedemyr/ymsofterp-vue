<script setup>
import { ref, onMounted, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const prList = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const generatingPO = ref(false);
const expandedPRs = ref({});
const notes = ref('');

// Form untuk generate PO
const poForm = useForm({
    items_by_supplier: {}, // Akan diisi array per item
    ppn_enabled: false, // PPN switch
});

// Fetch PR list yang belum di-PO
const fetchPRList = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/pr-ops/available');
        prList.value = response.data.map(pr => ({
            ...pr,
            items: pr.items.map(item => {
                if (!poForm.items_by_supplier[item.id]) {
                    // Default: satu baris, qty penuh
                    poForm.items_by_supplier[item.id] = [{
                        supplier_id: null,
                        qty: item.qty,
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
    }
};

// Toggle expand/collapse PR
const togglePR = (prId) => {
    expandedPRs.value[prId] = !expandedPRs.value[prId];
};

// Add item row untuk supplier tertentu
const addItemRow = (itemId, supplierId) => {
    if (!poForm.items_by_supplier[itemId]) {
        poForm.items_by_supplier[itemId] = [];
    }
    
    poForm.items_by_supplier[itemId].push({
        supplier_id: supplierId,
        qty: 0,
        price: '',
        last_price: '',
        min_price: '',
        max_price: ''
    });
};

// Remove item row
const removeItemRow = (itemId, index) => {
    if (poForm.items_by_supplier[itemId] && poForm.items_by_supplier[itemId].length > 1) {
        poForm.items_by_supplier[itemId].splice(index, 1);
    }
};

// Removed getLastPrice function as requested

// Calculate total untuk item
const calculateItemTotal = (itemId, index) => {
    const item = poForm.items_by_supplier[itemId][index];
    if (item && item.qty && item.price) {
        return parseFloat(item.qty) * parseFloat(item.price);
    }
    return 0;
};

// Calculate grand total
const grandTotal = computed(() => {
    let total = 0;
    Object.values(poForm.items_by_supplier).forEach(items => {
        items.forEach(item => {
            if (item.qty && item.price) {
                total += parseFloat(item.qty) * parseFloat(item.price);
            }
        });
    });
    
    if (poForm.ppn_enabled) {
        total += total * 0.11; // 11% PPN
    }
    
    return total;
});

// Submit form
const submitForm = async () => {
    try {
        generatingPO.value = true;
        
        // Validate form
        const itemsBySupplier = {};
        let hasItems = false;
        
        Object.keys(poForm.items_by_supplier).forEach(itemId => {
            poForm.items_by_supplier[itemId].forEach(item => {
                if (item.supplier_id && item.qty && item.price) {
                    hasItems = true;
                    // Extract supplier ID (handle both object and integer)
                    const supplierId = typeof item.supplier_id === 'object' ? item.supplier_id.id : item.supplier_id;
                    
                    if (!itemsBySupplier[supplierId]) {
                        itemsBySupplier[supplierId] = [];
                    }
                    
                    // Find original item data
                    const originalItem = prList.value
                        .flatMap(pr => pr.items)
                        .find(i => i.id == itemId);
                    
                    itemsBySupplier[supplierId].push({
                        id: itemId,
                        supplier_id: supplierId,
                        qty: parseFloat(item.qty),
                        price: parseFloat(item.price),
                        pr_id: originalItem?.pr_id,
                        item_name: originalItem?.item_name,
                        unit: originalItem?.unit,
                        arrival_date: originalItem?.arrival_date
                    });
                }
            });
        });
        
        if (!hasItems) {
            Swal.fire('Error', 'Please select at least one item with supplier, quantity, and price', 'error');
            return;
        }
        
        poForm.items_by_supplier = itemsBySupplier;
        poForm.notes = notes.value;
        
        const response = await axios.post('/po-ops/generate', poForm.data());
        
        if (response.data.success) {
            Swal.fire('Success', 'Purchase Orders created successfully!', 'success');
            // Use window.location instead of router.visit
            window.location.href = '/po-ops';
        } else {
            Swal.fire('Error', response.data.message || 'Failed to create PO', 'error');
        }
    } catch (error) {
        console.error('Error creating PO:', error);
        Swal.fire('Error', error.response?.data?.message || 'Failed to create PO', 'error');
    } finally {
        generatingPO.value = false;
    }
};

onMounted(() => {
    fetchPRList();
    fetchSuppliers();
});
</script>

<template>
  <AppLayout title="Create Purchase Order Ops">
    <div class="w-full py-8 px-4 relative">
      <!-- Loading Overlay -->
      <div v-if="generatingPO" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 rounded-lg">
        <div class="bg-white p-6 rounded-lg shadow-lg flex items-center space-x-3">
          <i class="fas fa-spinner fa-spin text-blue-500 text-xl"></i>
          <span class="text-gray-700 font-medium">Generating Purchase Orders...</span>
        </div>
      </div>
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-plus text-blue-500"></i> Create Purchase Order Ops
        </h1>
        <Link
          href="/po-ops"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i>
          Back to List
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <!-- PPN Toggle -->
        <div class="mb-6">
          <label class="flex items-center">
            <input
              type="checkbox"
              v-model="poForm.ppn_enabled"
              class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
            />
            <span class="ml-2 text-sm text-gray-700">Enable PPN (11%)</span>
          </label>
        </div>

        <!-- Notes -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
          <textarea
            v-model="notes"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Enter notes..."
          ></textarea>
        </div>

        <!-- PR List -->
        <div v-if="loading" class="text-center py-8">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
          <p class="mt-2 text-gray-600">Loading PR data...</p>
        </div>

        <div v-else-if="prList.length === 0" class="text-center py-8">
          <p class="text-gray-600">No available PR found</p>
        </div>

        <div v-else class="space-y-6">
          <div v-for="pr in prList" :key="pr.id" class="border border-gray-200 rounded-lg">
            <!-- PR Header -->
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                  <button
                    @click="togglePR(pr.id)"
                    class="text-blue-600 hover:text-blue-800"
                  >
                    <i :class="expandedPRs[pr.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                  </button>
                  <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ pr.number }}</h3>
                    <p class="text-sm text-gray-600">{{ pr.title }} - {{ pr.division_name }}</p>
                    <p class="text-sm text-gray-500">Amount: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(pr.amount) }}</p>
                  </div>
                </div>
                <div class="text-sm text-gray-500">
                  {{ new Date(pr.date).toLocaleDateString('id-ID') }}
                </div>
              </div>
            </div>

            <!-- PR Items -->
            <div v-if="expandedPRs[pr.id]" class="p-4">
              <div class="space-y-4">
                <div v-for="item in pr.items" :key="item.id" class="border border-gray-200 rounded-lg p-4">
                  <div class="flex items-center justify-between mb-3">
                    <div>
                      <h4 class="font-medium text-gray-900">{{ item.item_name }}</h4>
                      <p class="text-sm text-gray-600">Qty: {{ item.qty }} {{ item.unit }} | Unit Price: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(item.unit_price) }}</p>
                    </div>
                    <button
                      @click="addItemRow(item.id, null)"
                      class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600"
                    >
                      <i class="fas fa-plus mr-1"></i> Add Row
                    </button>
                  </div>

                  <!-- Item Rows -->
                  <div class="space-y-3">
                    <div v-for="(row, index) in poForm.items_by_supplier[item.id]" :key="index" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                      <!-- Supplier -->
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Supplier</label>
                        <Multiselect
                          v-model="row.supplier_id"
                          :options="suppliers"
                          :custom-label="supplier => supplier.name"
                          placeholder="Select Supplier"
                          class="text-sm"
                        />
                      </div>

                      <!-- Quantity -->
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Qty</label>
                        <input
                          type="number"
                          v-model.number="row.qty"
                          min="0"
                          step="0.01"
                          class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                      </div>

                      <!-- Price -->
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Price</label>
                        <input
                          type="number"
                          v-model.number="row.price"
                          min="0"
                          step="0.01"
                          class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                      </div>

                      <!-- Last Price Info -->
                      <div v-if="row.last_price" class="text-xs text-gray-600">
                        <div>Last: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(row.last_price) }}</div>
                        <div>Min: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(row.min_price) }}</div>
                        <div>Max: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(row.max_price) }}</div>
                      </div>

                      <!-- Total -->
                      <div class="text-sm font-medium">
                        <div class="text-xs text-gray-600">Total</div>
                        <div>{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(calculateItemTotal(item.id, index)) }}</div>
                      </div>

                      <!-- Actions -->
                      <div class="flex space-x-1">
                        <button
                          v-if="poForm.items_by_supplier[item.id].length > 1"
                          @click="removeItemRow(item.id, index)"
                          class="text-red-600 hover:text-red-800"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Grand Total -->
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
          <div class="flex justify-between items-center">
            <span class="text-lg font-semibold">Grand Total:</span>
            <span class="text-xl font-bold text-blue-600">
              {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(grandTotal) }}
            </span>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="mt-6 flex justify-end">
          <button
            @click="submitForm"
            :disabled="generatingPO"
            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
          >
            <i v-if="generatingPO" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-plus mr-2"></i>
            {{ generatingPO ? 'Generating...' : 'Generate Purchase Orders' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
