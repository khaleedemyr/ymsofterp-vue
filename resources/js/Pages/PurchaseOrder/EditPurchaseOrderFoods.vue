<template>
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit Purchase Order Foods
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <form @submit.prevent="handleSubmit">
              <!-- Dropdown Supplier -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Supplier</label>
                <select v-model="form.supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                  <option value="">Pilih Supplier</option>
                  <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                </select>
              </div>
              <!-- Header Info -->
              <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                  <h3 class="text-lg font-semibold mb-2">Informasi PO</h3>
                  <div class="space-y-2">
                    <p><span class="font-medium">Nomor PO:</span> {{ po.number }}</p>
                    <p><span class="font-medium">Tanggal:</span> {{ formatDate(po.date) }}</p>
                    <p><span class="font-medium">Status:</span> 
                      <span :class="getStatusClass(po.status)">{{ po.status }}</span>
                    </p>
                    <p><span class="font-medium">Supplier:</span>
                      {{ suppliers.find(s => s.id == form.supplier_id)?.name || po.supplier?.name || '-' }}
                    </p>
                  </div>
                </div>
                <div>
                  <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Catatan</label>
                    <textarea
                      v-model="form.notes"
                      rows="3"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                  </div>
                </div>
              </div>

              <!-- Notes Input -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea v-model="form.notes" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
              </div>

              <!-- PPN Switch -->
              <div class="mb-6 flex items-center">
                <input type="checkbox" id="ppnSwitch" v-model="form.ppn_enabled" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="ppnSwitch" class="ml-2 text-sm text-gray-700">Include PPN (11%)</label>
              </div>

              <!-- Items Table -->
              <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-semibold">Daftar Item</h3>
                  <!-- Add Item Button - Only show if status is draft -->
                  <button
                    v-if="po.status === 'draft'"
                    type="button"
                    @click="showAddItemModal = true"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Tambah Item
                  </button>
                </div>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr v-for="(item, index) in form.items" :key="index">
                        <td class="px-6 py-4 whitespace-nowrap">{{ item.item?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input
                            type="number"
                            v-model="item.quantity"
                            min="0"
                            step="0.01"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            @input="updateItemTotal(index)"
                          />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          {{ item.unit?.name || item.unit_name || item.unit || '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input
                            type="number"
                            v-model="item.price"
                            @input="updateItemTotal(index)"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ formatRupiah(item.total) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <button
                            type="button"
                            @click="removeItem(index)"
                            class="text-red-600 hover:text-red-900"
                          >
                            Hapus
                          </button>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="bg-gray-50">
                        <td colspan="4" class="px-6 py-4 text-right font-medium">Subtotal:</td>
                        <td class="px-6 py-4 font-medium">{{ formatRupiah(calculateTotal()) }}</td>
                        <td></td>
                      </tr>
                      <tr v-if="form.ppn_enabled" class="bg-gray-50">
                        <td colspan="4" class="px-6 py-4 text-right font-medium text-blue-600">PPN (11%):</td>
                        <td class="px-6 py-4 font-medium text-blue-600">{{ formatRupiah(calculatePPN()) }}</td>
                        <td></td>
                      </tr>
                      <tr class="bg-gray-100">
                        <td colspan="4" class="px-6 py-4 text-right font-bold text-lg">Grand Total:</td>
                        <td class="px-6 py-4 font-bold text-lg text-green-600">{{ formatRupiah(calculateGrandTotal()) }}</td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex justify-end space-x-4">
                <Link
                  :href="route('po-foods.show', po.id)"
                  class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Batal
                </Link>
                <button
                  type="submit"
                  class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Simpan
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Item Modal -->
    <div v-if="showAddItemModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-5/6 lg:w-4/5 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
        <div class="mt-3">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Tambah Item dari PR</h3>
            <button
              @click="closeAddItemModal"
              class="text-gray-400 hover:text-gray-600"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <!-- Loading State -->
          <div v-if="loadingPR" class="flex justify-center items-center py-8">
            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </div>

          <!-- PR List -->
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
                            <tr v-for="(split, idx) in addItemForm.items_by_supplier[item.id]" :key="idx">
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.name }}</td>
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <input 
                                  type="number" 
                                  v-model="split.qty" 
                                  min="0"
                                  step="0.01"
                                  :max="item.quantity - totalQtyUsed(item.id, idx)" 
                                  class="w-20 border rounded px-2 py-1" 
                                />
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ item.arrival_date ? new Date(item.arrival_date).toLocaleDateString('id-ID') : '-' }}
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
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ formatRupiah((split.price || 0) * split.qty) }}
                              </td>
                              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <button type="button" @click="addSplit(item.id)" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded text-xs mr-1">Split</button>
                                <button v-if="addItemForm.items_by_supplier[item.id].length > 1" type="button" @click="removeSplit(item.id, idx)" class="bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded text-xs">Hapus</button>
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
                  <span class="font-medium">{{ formatRupiah(calculateAddItemTotal()) }}</span>
                </div>
                <div class="flex justify-between border-t pt-2">
                  <span class="text-lg font-semibold">Grand Total:</span>
                  <span class="text-lg font-bold text-green-600">{{ formatRupiah(calculateAddItemTotal()) }}</span>
                </div>
              </div>
            </div>

            <!-- Modal Actions -->
            <div class="flex justify-end space-x-3 mt-6">
              <button
                @click="closeAddItemModal"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Batal
              </button>
              <button
                @click="addSelectedItems"
                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700"
              >
                Tambah Item ke PO
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { debounce } from 'lodash'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  po: {
    type: Object,
    required: true
  },
  suppliers: {
    type: Array,
    required: true
  }
})

const form = ref({
  supplier_id: props.po.supplier_id || props.po.supplier?.id || '',
  notes: props.po.notes || '',
  ppn_enabled: props.po.ppn_enabled || false,
  items: props.po.items.map(item => ({
    ...item,
    price: item.price,
    total: item.total
  }))
})

const deletedItems = ref([])

// Add Item Modal State
const showAddItemModal = ref(false)
const loadingPR = ref(false)
const prList = ref([])
const expandedPRs = ref({})
const expandedWarehouses = ref({})

// Form untuk add item (mirip dengan CreatePurchaseOrderFoods)
const addItemForm = ref({
  items_by_supplier: {}, // Akan diisi array per item
})

// Fetch PR list yang belum di-PO
const fetchPRList = async () => {
  try {
    loadingPR.value = true;
    const response = await axios.get('/api/pr-foods/available');
    prList.value = response.data.map(pr => ({
      ...pr,
      items: pr.items.map(item => {
        if (!addItemForm.value.items_by_supplier[item.id]) {
          // Default: satu baris, qty penuh
          addItemForm.value.items_by_supplier[item.id] = [{
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
  } finally {
    loadingPR.value = false;
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
  addItemForm.value.items_by_supplier[itemId].push({
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
  if (addItemForm.value.items_by_supplier[itemId].length > 1) {
    addItemForm.value.items_by_supplier[itemId].splice(idx, 1);
  }
}

// Hitung total qty split (kecuali idx tertentu jika sedang edit)
function totalQtyUsed(itemId, exceptIdx = -1) {
  return addItemForm.value.items_by_supplier[itemId]
    .filter((_, idx) => idx !== exceptIdx)
    .reduce((sum, split) => sum + Number(split.qty || 0), 0);
}

// Tambahkan fungsi untuk mengambil konversi dari item atau item.item
function getSmallConv(item) {
  return Number(item.small_conversion_qty || (item.item && item.item.small_conversion_qty) || 1);
}

function getMediumConv(item) {
  return Number(item.medium_conversion_qty || (item.item && item.item.medium_conversion_qty) || 1);
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
  const supplier = addItemForm.value.items_by_supplier[item.id][0].supplier_id;
  const supplierId = supplier ? supplier.id : null;
  if (!supplierId) {
    addItemForm.value.items_by_supplier[item.id].forEach(split => {
      split.price = '';
      split.last_price = 0;
      split.min_price = 0;
      split.max_price = 0;
    });
    return;
  }
  try {
    const res = await axios.get('/api/items/last-price', {
      params: {
        item_id: item.item_id,
        supplier_id: supplierId,
        unit: item.unit
      }
    });
    addItemForm.value.items_by_supplier[item.id].forEach(split => {
      split.price = res.data.last_price ?? 0;
      split.last_price = res.data.last_price ?? 0;
      split.min_price = res.data.min_price ?? 0;
      split.max_price = res.data.max_price ?? 0;
    });
  } catch (error) {
    console.error('Error fetching last price:', error);
    addItemForm.value.items_by_supplier[item.id].forEach(split => {
      split.price = 0;
      split.last_price = 0;
      split.min_price = 0;
      split.max_price = 0;
    });
  }
};

const closeAddItemModal = () => {
  showAddItemModal.value = false;
  // Reset form
  addItemForm.value.items_by_supplier = {};
  expandedPRs.value = {};
  expandedWarehouses.value = {};
};

const addSelectedItems = () => {
  // Process selected items and add them to the main form
  let addedCount = 0;
  
  Object.entries(addItemForm.value.items_by_supplier).forEach(([itemId, splits]) => {
    splits.forEach(split => {
      const supplierId = split.supplier_id ? split.supplier_id.id : null;
      if (!supplierId || !split.price || !split.qty || split.qty < 0) return;
      
      // Find the item details
      const prItem = prList.value.flatMap(pr => pr.items).find(i => i.id == itemId);
      if (!prItem) return;

      const newItem = {
        id: null, // This will be a new item
        item: {
          id: prItem.item_id,
          name: prItem.name,
          sku: prItem.sku || ''
        },
        quantity: Number(split.qty),
        price: Number(split.price),
        total: Number(split.qty) * Number(split.price),
        unit: {
          name: prItem.unit
        },
        unit_name: prItem.unit,
        is_new: true, // Flag to identify new items
        pr_food_item_id: itemId // Reference to PR item
      };

      form.value.items.push(newItem);
      addedCount++;
    });
  });

  if (addedCount > 0) {
    closeAddItemModal();
  } else {
    alert('Pilih supplier dan isi quantity untuk item yang ingin ditambahkan');
  }
};

const calculateAddItemTotal = () => {
  let total = 0;
  Object.values(addItemForm.value.items_by_supplier).forEach(splits => {
    splits.forEach(split => {
      if (split.price && split.qty) {
        total += Number(split.price) * Number(split.qty);
      }
    });
  });
  return total;
};

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

const getStatusClass = (status) => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    pending_gm_finance: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800'
  }
  return `px-2 py-1 rounded-full text-xs font-medium ${classes[status] || classes.draft}`
}

const formatRupiah = (value) => {
  if (typeof value !== 'number') value = Number(value) || 0;
  return 'Rp ' + value.toLocaleString('id-ID');
}

const updateItemTotal = (index) => {
  const item = form.value.items[index]
  item.total = item.quantity * item.price
}

const removeItem = (index) => {
  const item = form.value.items[index]
  if (item.id) {
    // If item has an ID, it's an existing item that should be deleted
    deletedItems.value.push(item)
  }
  form.value.items.splice(index, 1)
}

const calculateTotal = () => {
  return form.value.items.reduce((sum, item) => sum + (Number(item.total) || 0), 0)
}

const calculatePPN = () => {
  if (!form.value.ppn_enabled) return 0;
  return calculateTotal() * 0.11;
}

const calculateGrandTotal = () => {
  return calculateTotal() + calculatePPN();
}

const handleSubmit = async () => {
  try {
    if (form.value.items.length === 0) {
      // Hapus PO jika tidak ada item
      await axios.delete(`/po-foods/${props.po.id}`);
      // Kembali ke index dengan filter yang tersimpan
      goBackToIndex();
      return;
    }

    // Separate existing and new items
    const existingItems = form.value.items.filter(item => item.id && !item.is_new)
    const newItems = form.value.items.filter(item => item.is_new).map(item => ({
      item: item.item,
      quantity: item.quantity,
      price: item.price,
      pr_food_item_id: item.pr_food_item_id
    }))

    const response = await axios.put(`/po-foods/${props.po.id}`, {
      ...form.value,
      items: existingItems,
      new_items: newItems,
      deleted_items: deletedItems.value.map(i => i.id)
    })
    
    if (response.data.success) {
      router.visit(route('po-foods.show', props.po.id))
    }
  } catch (error) {
    console.error('Update failed:', error)
  }
}

// Fungsi untuk kembali ke index dengan filter yang tersimpan
const goBackToIndex = () => {
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
      router.visit(url);
    } else {
      router.visit(route('po-foods.index'));
    }
  } catch (error) {
    console.error('Error restoring filters:', error);
    router.visit(route('po-foods.index'));
  }
}

// Watch for modal open to fetch PR list
watch(showAddItemModal, (newVal) => {
  if (newVal) {
    fetchPRList();
  }
});
</script>

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