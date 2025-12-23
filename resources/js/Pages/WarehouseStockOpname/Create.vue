<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Buat Warehouse Stock Opname Baru
        </h1>
        <Link
          :href="route('warehouse-stock-opnames.index')"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submitForm" class="bg-white rounded-xl shadow-lg p-6">
        <!-- Autosave Status -->
        <div class="mb-4 flex items-center justify-end gap-2 text-sm">
          <span v-if="autosaveStatus === 'saving'" class="text-blue-600 flex items-center gap-1">
            <i class="fas fa-spinner fa-spin"></i>
            Menyimpan...
          </span>
          <span v-else-if="autosaveStatus === 'saved'" class="text-green-600 flex items-center gap-1">
            <i class="fas fa-check-circle"></i>
            Tersimpan
            <span v-if="lastSavedAt" class="text-xs text-gray-500">
              ({{ new Date(lastSavedAt).toLocaleTimeString('id-ID') }})
            </span>
          </span>
          <span v-else-if="autosaveStatus === 'error'" class="text-red-600 flex items-center gap-1">
            <i class="fas fa-exclamation-circle"></i>
            Gagal menyimpan
          </span>
        </div>

        <!-- Basic Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse *</label>
            <select
              v-model="form.warehouse_id"
              @change="onWarehouseChange"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Pilih Warehouse</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Warehouse Division
              <span v-if="warehouseHasDivisions" class="text-red-500">*</span>
            </label>
            <select
              v-model="form.warehouse_division_id"
              @change="onWarehouseDivisionChange"
              :required="warehouseHasDivisions"
              :disabled="!form.warehouse_id || checkingDivisions"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
            >
              <option value="">{{ warehouseHasDivisions ? 'Pilih Division' : 'Semua Division' }}</option>
              <option v-for="wd in filteredWarehouseDivisions" :key="wd.id" :value="wd.id">
                {{ wd.name }}
              </option>
            </select>
            <p v-if="checkingDivisions" class="text-xs text-gray-500 mt-1">
              <i class="fa fa-spinner fa-spin mr-1"></i>Memeriksa division...
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Opname *</label>
            <input
              v-model="form.opname_date"
              type="date"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Catatan tambahan..."
            ></textarea>
          </div>
        </div>

        <!-- Approval Flow Section -->
        <div class="mb-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Flow</h3>
          <p class="text-sm text-gray-600 mb-4">Tambahkan approvers secara berurutan dari terendah ke tertinggi. Approver pertama = level terendah, approver terakhir = level tertinggi.</p>
          
          <!-- Add Approver Input -->
          <div class="mb-4">
            <div class="relative">
              <input
                v-model="approverSearch"
                type="text"
                placeholder="Cari user berdasarkan nama, email, atau jabatan..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="onApproverSearch"
                @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
              />
              
              <!-- Dropdown Results -->
              <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                <div
                  v-for="user in approverResults"
                  :key="user.id"
                  @click="addApprover(user)"
                  class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                >
                  <div class="font-medium">{{ user.name }}</div>
                  <div class="text-sm text-gray-600">{{ user.email }}</div>
                  <div v-if="user.jabatan" class="text-xs text-blue-600 font-medium">{{ user.jabatan }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Approvers List -->
          <div v-if="form.approvers.length > 0" class="space-y-2">
            <h4 class="font-medium text-gray-700">Urutan Approval (Terendah ke Tertinggi):</h4>
            
            <template v-for="(approver, index) in form.approvers" :key="approver?.id || index">
              <div
                v-if="approver && approver.id"
                class="flex items-center justify-between p-3 rounded-md bg-gray-50 border border-gray-200"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="index > 0"
                      @click="reorderApprover(index, index - 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Pindah ke atas"
                    >
                      <i class="fa fa-arrow-up"></i>
                    </button>
                    <button
                      v-if="index < form.approvers.length - 1"
                      @click="reorderApprover(index, index + 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Pindah ke bawah"
                    >
                      <i class="fa fa-arrow-down"></i>
                    </button>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      Level {{ index + 1 }}
                    </span>
                    <div>
                      <div class="font-medium">{{ approver.name }}</div>
                      <div class="text-sm text-gray-600">{{ approver.email }}</div>
                      <div v-if="approver.jabatan" class="text-xs text-blue-600 font-medium">{{ approver.jabatan }}</div>
                    </div>
                  </div>
                </div>
                <button
                  @click="removeApprover(index)"
                  class="p-1 text-red-500 hover:text-red-700"
                  title="Hapus Approver"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </template>
          </div>
        </div>

        <!-- Items Table -->
        <div v-if="form.items.length > 0" class="mb-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Items ({{ form.items.length }})</h3>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase w-48">Item</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Small
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Medium
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Large
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-32">MAC</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">Subtotal<br/>(Qty × MAC)</th>
                  <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase w-48">Selisih</th>
                  <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase w-48">Alasan</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <template v-for="(category, categoryName) in groupedItems" :key="categoryName">
                  <!-- Category Header Row -->
                  <tr
                    class="bg-blue-50 hover:bg-blue-100 cursor-pointer transition"
                    @click="toggleCategory(categoryName)"
                  >
                    <td class="px-4 py-3 font-bold text-gray-800" colspan="8">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <i 
                            :class="expandedCategories.has(categoryName) ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"
                            class="text-blue-600 transition-transform"
                          ></i>
                          <span class="text-sm">{{ categoryName || 'Uncategorized' }}</span>
                          <span class="text-xs text-gray-500 font-normal">
                            ({{ category.length }} item{{ category.length > 1 ? 's' : '' }})
                          </span>
                        </div>
                        <div class="text-xs text-gray-600">
                          <i class="fa-solid fa-chevron-down" v-if="expandedCategories.has(categoryName)"></i>
                          <i class="fa-solid fa-chevron-right" v-else></i>
                        </div>
                      </div>
                    </td>
                  </tr>
                  
                  <!-- Items in Category -->
                  <tr
                    v-for="item in category"
                    :key="item.inventory_item_id"
                    v-show="expandedCategories.has(categoryName)"
                    :class="{ 'bg-yellow-50': hasDifference(item) }"
                    class="hover:bg-gray-50 transition"
                  >
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                      <div class="font-semibold pl-6">{{ item.item_name }}</div>
                    </td>
                    <td class="px-3 py-3">
                      <div class="flex items-center gap-2">
                        <input
                          v-model.number="item.qty_physical_small"
                          type="number"
                          step="any"
                          min="0"
                          @input="onQtyPhysicalChange(item, 'small')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.small_unit_name || '-' }}</span>
                      </div>
                    </td>
                    <td class="px-3 py-3">
                      <div class="flex items-center gap-2">
                        <input
                          v-model.number="item.qty_physical_medium"
                          type="number"
                          step="any"
                          min="0"
                          @input="onQtyPhysicalChange(item, 'medium')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.medium_unit_name || '-' }}</span>
                      </div>
                    </td>
                    <td class="px-3 py-3">
                      <div class="flex items-center gap-2">
                        <input
                          v-model.number="item.qty_physical_large"
                          type="number"
                          step="any"
                          min="0"
                          @input="onQtyPhysicalChange(item, 'large')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.large_unit_name || '-' }}</span>
                      </div>
                    </td>
                    <td class="px-3 py-3 text-sm text-right text-gray-700">
                      <div class="font-medium">{{ formatCurrency(item.mac) }}</div>
                    </td>
                    <td class="px-3 py-3 text-sm text-right text-gray-700">
                      <div class="font-semibold">{{ formatNumber(getSubtotal(item)) }}</div>
                    </td>
                    <td class="px-3 py-3 text-center text-sm">
                      <div v-if="hasDifference(item)">
                        <span
                          :class="getDifferenceClass(item)"
                          class="px-2 py-1 rounded text-xs font-semibold whitespace-nowrap"
                        >
                          {{ getDifferenceSign(item) }}
                        </span>
                      </div>
                      <span v-else class="text-gray-400 text-xs">-</span>
                    </td>
                    <td class="px-3 py-3">
                      <input
                        v-if="hasDifference(item)"
                        v-model="item.reason"
                        type="text"
                        placeholder="Alasan selisih..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      />
                      <span v-else class="text-gray-400 text-xs">-</span>
                    </td>
                  </tr>
                </template>
              </tbody>
              <tfoot class="bg-gray-100 border-t-2 border-gray-400">
                <tr>
                  <td class="px-4 py-4 text-right font-bold text-gray-900" colspan="5">
                    GRAND TOTAL
                  </td>
                  <td class="px-3 py-4 text-right font-bold text-gray-900 text-lg">
                    {{ formatNumber(grandTotal) }}
                  </td>
                  <td class="px-3 py-4" colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loadingItems" class="text-center py-8">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
          <p class="mt-2 text-gray-600">Memuat items...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="form.items.length === 0 && form.warehouse_id" class="text-center py-8 text-gray-500">
          <i class="fa-solid fa-inbox text-4xl mb-4"></i>
          <p>Tidak ada item dengan stock untuk warehouse yang dipilih.</p>
        </div>

        <!-- Submit Button -->
        <div v-if="form.items.length > 0" class="flex justify-end gap-4 mt-6">
          <button
            type="button"
            @click="$inertia.visit(route('warehouse-stock-opnames.index'))"
            class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-semibold"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 font-semibold disabled:opacity-50"
          >
            <i v-if="submitting" class="fa fa-spinner fa-spin mr-2"></i>
            <i v-else class="fa-solid fa-save mr-2"></i>
            Simpan Draft
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  warehouses: Array,
  warehouseDivisions: Array,
  items: Array,
  selectedWarehouseId: [String, Number],
  selectedWarehouseDivisionId: [String, Number],
});

const form = useForm({
  warehouse_id: props.selectedWarehouseId || '',
  warehouse_division_id: props.selectedWarehouseDivisionId || '',
  opname_date: new Date().toISOString().split('T')[0],
  notes: '',
  items: props.items || [],
  approvers: [],
});

const loadingItems = ref(false);
const submitting = ref(false);
const expandedCategories = ref(new Set());
const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);
const approverSearchTimeout = ref(null);
const warehouseHasDivisions = ref(false);
const checkingDivisions = ref(false);
const warehouseDivisionsList = ref([]);

// Autosave
const draftId = ref(null);
const autosaveStatus = ref('idle'); // idle, saving, saved, error
const autosaveTimeout = ref(null);
const lastSavedAt = ref(null);

// Group items by category
const groupedItems = computed(() => {
  const grouped = {};
  form.items.forEach(item => {
    const categoryName = item.category_name || 'Uncategorized';
    if (!grouped[categoryName]) {
      grouped[categoryName] = [];
    }
    grouped[categoryName].push(item);
  });
  return grouped;
});

// Calculate subtotal for an item (qty_physical_small * mac)
function getSubtotal(item) {
  if (!item) return 0;
  // Return 0 if qty_physical_small is not filled (null/undefined/empty)
  if (item.qty_physical_small === null || item.qty_physical_small === undefined || item.qty_physical_small === '') {
    return 0;
  }
  const qtyPhysical = parseFloat(item.qty_physical_small) || 0;
  const mac = parseFloat(item.mac) || 0;
  return qtyPhysical * mac;
}

// Calculate grand total
const grandTotal = computed(() => {
  if (!form.items || form.items.length === 0) return 0;
  return form.items.reduce((total, item) => {
    return total + getSubtotal(item);
  }, 0);
});

function toggleCategory(categoryName) {
  if (expandedCategories.value.has(categoryName)) {
    expandedCategories.value.delete(categoryName);
  } else {
    expandedCategories.value.add(categoryName);
  }
}

// Auto expand all categories on load
watch(() => form.items, (newItems) => {
  if (newItems && newItems.length > 0) {
    const categories = new Set();
    newItems.forEach(item => {
      categories.add(item.category_name || 'Uncategorized');
    });
    expandedCategories.value = categories;
  }
}, { immediate: true });

const filteredWarehouseDivisions = computed(() => {
  // Use divisions from API check if available, otherwise use props
  if (warehouseDivisionsList.value.length > 0) {
    return warehouseDivisionsList.value;
  }
  
  let divisions = props.warehouseDivisions || [];
  
  if (form.warehouse_id) {
    divisions = divisions.filter(wd => String(wd.warehouse_id) === String(form.warehouse_id));
  }
  
  return divisions;
});

async function onWarehouseChange() {
  form.warehouse_division_id = '';
  form.items = [];
  warehouseHasDivisions.value = false;
  warehouseDivisionsList.value = [];
  
  if (!form.warehouse_id) {
    return;
  }

  // Check if warehouse has divisions
  checkingDivisions.value = true;
  try {
    const response = await axios.get(route('warehouse-stock-opnames.check-divisions'), {
      params: {
        warehouse_id: form.warehouse_id,
      },
    });

    if (response.data && response.data.has_divisions) {
      warehouseHasDivisions.value = true;
      warehouseDivisionsList.value = response.data.divisions || [];
      // Items will be loaded when user selects a division
    } else {
      warehouseHasDivisions.value = false;
      warehouseDivisionsList.value = [];
      // If no divisions, load items immediately
      await loadItems();
    }
  } catch (error) {
    console.error('Error checking warehouse divisions:', error);
    // On error, assume no divisions and try to load items
    warehouseHasDivisions.value = false;
    await loadItems();
  } finally {
    checkingDivisions.value = false;
  }
}

function onWarehouseDivisionChange() {
  if (form.warehouse_division_id) {
    loadItems();
  } else {
    form.items = [];
  }
}

async function loadItems() {
  if (!form.warehouse_id) {
    form.items = [];
    return;
  }

  // If warehouse has divisions but no division selected, don't load items
  if (warehouseHasDivisions.value && !form.warehouse_division_id) {
    form.items = [];
    return;
  }

  loadingItems.value = true;
  try {
    const response = await axios.get(route('warehouse-stock-opnames.get-items'), {
      params: {
        warehouse_id: form.warehouse_id,
        warehouse_division_id: form.warehouse_division_id || null,
      },
    });

    // Check if response.data is an array
    if (!Array.isArray(response.data)) {
      console.error('Response is not an array:', response.data);
      Swal.fire({
        title: 'Error',
        text: 'Format data tidak valid. Silakan coba lagi.',
        icon: 'error',
        confirmButtonColor: '#3085d6'
      });
      return;
    }

    form.items = response.data.map(item => ({
      inventory_item_id: item.inventory_item_id,
      item_name: item.item_name,
      category_name: item.category_name,
      qty_system_small: parseFloat(item.qty_system_small) || 0,
      qty_system_medium: parseFloat(item.qty_system_medium) || 0,
      qty_system_large: parseFloat(item.qty_system_large) || 0,
      qty_physical_small: null,
      qty_physical_medium: null,
      qty_physical_large: null,
      reason: '',
      small_unit_name: item.small_unit_name,
      medium_unit_name: item.medium_unit_name,
      large_unit_name: item.large_unit_name,
      small_conversion_qty: parseFloat(item.small_conversion_qty) || 1,
      medium_conversion_qty: parseFloat(item.medium_conversion_qty) || 1,
      mac: parseFloat(item.mac) || 0,
    }));
  } catch (error) {
    console.error('Error loading items:', error);
    let errorMessage = 'Gagal memuat items. Silakan coba lagi.';
    
    if (error.response) {
      // Server responded with error status
      if (error.response.data && error.response.data.error) {
        errorMessage = error.response.data.error;
      } else if (error.response.status === 422) {
        errorMessage = 'Data yang dikirim tidak valid. Silakan cek kembali.';
      } else if (error.response.status === 500) {
        errorMessage = 'Terjadi kesalahan di server. Silakan hubungi administrator.';
      }
    } else if (error.request) {
      // Request was made but no response received
      errorMessage = 'Tidak ada response dari server. Silakan cek koneksi internet.';
    }
    
    Swal.fire({
      title: 'Error',
      text: errorMessage,
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
    form.items = [];
  } finally {
    loadingItems.value = false;
  }
}


function onQtyPhysicalChange(item, changedUnit) {
  const value = item[`qty_physical_${changedUnit}`];
  
  if (value === null || value === undefined || value === '') {
    item.qty_physical_small = null;
    item.qty_physical_medium = null;
    item.qty_physical_large = null;
    calculateDifference(item);
    return;
  }

  const numValue = parseFloat(value);
  if (isNaN(numValue) || numValue < 0) {
    calculateDifference(item);
    return;
  }

  // Konversi unit
  if (changedUnit === 'small') {
    if (item.small_conversion_qty && item.small_conversion_qty > 0) {
      item.qty_physical_medium = numValue / item.small_conversion_qty;
      
      if (item.medium_conversion_qty && item.medium_conversion_qty > 0) {
        item.qty_physical_large = numValue / (item.small_conversion_qty * item.medium_conversion_qty);
      } else {
        item.qty_physical_large = item.qty_physical_medium;
      }
    } else {
      item.qty_physical_medium = numValue;
      item.qty_physical_large = numValue;
    }
  } else if (changedUnit === 'medium') {
    if (item.small_conversion_qty && item.small_conversion_qty > 0) {
      item.qty_physical_small = numValue * item.small_conversion_qty;
    } else {
      item.qty_physical_small = numValue;
    }
    
    if (item.medium_conversion_qty && item.medium_conversion_qty > 0) {
      item.qty_physical_large = numValue / item.medium_conversion_qty;
    } else {
      item.qty_physical_large = numValue;
    }
  } else if (changedUnit === 'large') {
    if (item.medium_conversion_qty && item.medium_conversion_qty > 0) {
      item.qty_physical_medium = numValue * item.medium_conversion_qty;
      
      if (item.small_conversion_qty && item.small_conversion_qty > 0) {
        item.qty_physical_small = item.qty_physical_medium * item.small_conversion_qty;
      } else {
        item.qty_physical_small = item.qty_physical_medium;
      }
    } else {
      item.qty_physical_medium = numValue;
      item.qty_physical_small = numValue;
    }
  }

  // Round to 2 decimal places
  if (item.qty_physical_small !== null) {
    item.qty_physical_small = Math.round(item.qty_physical_small * 100) / 100;
  }
  if (item.qty_physical_medium !== null) {
    item.qty_physical_medium = Math.round(item.qty_physical_medium * 100) / 100;
  }
  if (item.qty_physical_large !== null) {
    item.qty_physical_large = Math.round(item.qty_physical_large * 100) / 100;
  }

  calculateDifference(item);
}

function calculateDifference(item) {
  // Difference akan dihitung di backend
}

function hasDifference(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  const diffMedium = (item.qty_physical_medium ?? item.qty_system_medium) - item.qty_system_medium;
  const diffLarge = (item.qty_physical_large ?? item.qty_system_large) - item.qty_system_large;
  return diffSmall !== 0 || diffMedium !== 0 || diffLarge !== 0;
}

function formatDifference(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  const diffMedium = (item.qty_physical_medium ?? item.qty_system_medium) - item.qty_system_medium;
  const diffLarge = (item.qty_physical_large ?? item.qty_system_large) - item.qty_system_large;
  
  const parts = [];
  if (diffSmall !== 0) parts.push(`${diffSmall > 0 ? '+' : ''}${formatNumber(diffSmall)} ${item.small_unit_name}`);
  if (diffMedium !== 0) parts.push(`${diffMedium > 0 ? '+' : ''}${formatNumber(diffMedium)} ${item.medium_unit_name}`);
  if (diffLarge !== 0) parts.push(`${diffLarge > 0 ? '+' : ''}${formatNumber(diffLarge)} ${item.large_unit_name}`);
  
  return parts.join(', ') || '0';
}

function getDifferenceArray(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  const diffMedium = (item.qty_physical_medium ?? item.qty_system_medium) - item.qty_system_medium;
  const diffLarge = (item.qty_physical_large ?? item.qty_system_large) - item.qty_system_large;
  
  const diffs = [];
  if (diffSmall !== 0) {
    diffs.push(`${diffSmall > 0 ? '+' : ''}${formatNumber(diffSmall)} ${item.small_unit_name}`);
  }
  if (diffMedium !== 0) {
    diffs.push(`${diffMedium > 0 ? '+' : ''}${formatNumber(diffMedium)} ${item.medium_unit_name}`);
  }
  if (diffLarge !== 0) {
    diffs.push(`${diffLarge > 0 ? '+' : ''}${formatNumber(diffLarge)} ${item.large_unit_name}`);
  }
  
  return diffs.length > 0 ? diffs : ['0'];
}

function getDifferenceSign(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  if (diffSmall > 0) return '+';
  if (diffSmall < 0) return '-';
  return '0';
}

function getDifferenceClass(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  if (diffSmall > 0) return 'bg-green-100 text-green-800';
  if (diffSmall < 0) return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-800';
}

function formatNumber(val) {
  if (val == null) return '0';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCurrency(val) {
  if (val == null || val === '' || isNaN(val)) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadApprovers(search = '') {
  if (search.length < 2) {
    approverResults.value = [];
    showApproverDropdown.value = false;
    return;
  }

  try {
    const response = await axios.get(route('warehouse-stock-opnames.approvers'), {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    });
    
    console.log('Approvers response:', response.data);
    
    if (response.data && response.data.success) {
      approverResults.value = response.data.users || [];
      showApproverDropdown.value = approverResults.value.length > 0;
    } else {
      approverResults.value = [];
      showApproverDropdown.value = false;
    }
  } catch (error) {
    console.error('Failed to load approvers:', error);
    approverResults.value = [];
    showApproverDropdown.value = false;
  }
}

function onApproverSearch() {
  // Debounce search to avoid too many API calls
  if (approverSearchTimeout.value) {
    clearTimeout(approverSearchTimeout.value);
  }
  
  approverSearchTimeout.value = setTimeout(() => {
    if (approverSearch.value.length >= 2) {
      loadApprovers(approverSearch.value);
    } else {
      approverResults.value = [];
      showApproverDropdown.value = false;
    }
  }, 300);
}

function addApprover(user) {
  if (!user || !user.id) {
    console.error('Invalid user object:', user);
    return;
  }
  // Check if user already exists
  if (!form.approvers.find(approver => approver && approver.id === user.id)) {
    form.approvers.push(user);
    triggerAutosave();
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
}

function removeApprover(index) {
  form.approvers.splice(index, 1);
  triggerAutosave();
}

function reorderApprover(fromIndex, toIndex) {
  const approver = form.approvers.splice(fromIndex, 1)[0];
  form.approvers.splice(toIndex, 0, approver);
  triggerAutosave();
}

// Autosave function
async function autosave() {
  // Skip autosave if form is not valid enough (at least warehouse must be selected)
  if (!form.warehouse_id) {
    return;
  }

  // Skip if already submitting
  if (submitting.value) {
    return;
  }

  autosaveStatus.value = 'saving';

  try {
    // Only autosave items that have been explicitly filled (not null/undefined)
    // This prevents overwriting user input with system qty
    const itemsToSave = form.items.map(item => {
      const itemData = {
        inventory_item_id: item.inventory_item_id,
        reason: item.reason || '',
      };
      
      // Only include qty_physical if it's been explicitly set (not null/undefined)
      // This way, backend won't overwrite with system qty
      if (item.qty_physical_small !== null && item.qty_physical_small !== undefined && item.qty_physical_small !== '') {
        itemData.qty_physical_small = parseFloat(item.qty_physical_small);
      }
      if (item.qty_physical_medium !== null && item.qty_physical_medium !== undefined && item.qty_physical_medium !== '') {
        itemData.qty_physical_medium = parseFloat(item.qty_physical_medium);
      }
      if (item.qty_physical_large !== null && item.qty_physical_large !== undefined && item.qty_physical_large !== '') {
        itemData.qty_physical_large = parseFloat(item.qty_physical_large);
      }
      
      return itemData;
    });

    // Prepare approvers array (only IDs)
    const approversIds = form.approvers
      .filter(approver => approver && approver.id)
      .map(approver => approver.id);

    const formData = {
      warehouse_id: form.warehouse_id,
      warehouse_division_id: form.warehouse_division_id,
      opname_date: form.opname_date,
      notes: form.notes,
      items: itemsToSave,
      approvers: approversIds,
      autosave: true,
    };

    let response;
    if (draftId.value) {
      // Update existing draft
      response = await axios.put(route('warehouse-stock-opnames.update', draftId.value), formData);
    } else {
      // Create new draft
      response = await axios.post(route('warehouse-stock-opnames.store'), formData);
    }

    if (response.data.success) {
      if (response.data.id && !draftId.value) {
        draftId.value = response.data.id;
      }
      autosaveStatus.value = 'saved';
      lastSavedAt.value = new Date();
      
      // Clear saved status after 3 seconds
      setTimeout(() => {
        if (autosaveStatus.value === 'saved') {
          autosaveStatus.value = 'idle';
        }
      }, 3000);
    }
  } catch (error) {
    console.error('Autosave error:', error);
    autosaveStatus.value = 'error';
    
    // Clear error status after 5 seconds
    setTimeout(() => {
      if (autosaveStatus.value === 'error') {
        autosaveStatus.value = 'idle';
      }
    }, 5000);
  }
}

// Debounced autosave
function triggerAutosave() {
  // Clear existing timeout
  if (autosaveTimeout.value) {
    clearTimeout(autosaveTimeout.value);
  }

  // Set new timeout (2 seconds after last change)
  autosaveTimeout.value = setTimeout(() => {
    autosave();
  }, 2000);
}

function submitForm() {
  if (form.items.length === 0) {
    Swal.fire({
      title: 'Error',
      text: 'Minimal harus ada 1 item.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
    return;
  }

  // Ensure all items have correct data structure
  const itemsToSubmit = form.items.map(item => {
    const processedItem = {
      inventory_item_id: item.inventory_item_id,
      qty_physical_small: item.qty_physical_small !== null && item.qty_physical_small !== undefined && item.qty_physical_small !== '' 
        ? parseFloat(item.qty_physical_small) 
        : null,
      qty_physical_medium: item.qty_physical_medium !== null && item.qty_physical_medium !== undefined && item.qty_physical_medium !== '' 
        ? parseFloat(item.qty_physical_medium) 
        : null,
      qty_physical_large: item.qty_physical_large !== null && item.qty_physical_large !== undefined && item.qty_physical_large !== '' 
        ? parseFloat(item.qty_physical_large) 
        : null,
      reason: item.reason || '',
    };
    
    // Log for debugging
    console.log('Item before processing:', {
      inventory_item_id: item.inventory_item_id,
      qty_physical_small_raw: item.qty_physical_small,
      qty_physical_medium_raw: item.qty_physical_medium,
      qty_physical_large_raw: item.qty_physical_large,
      qty_system_small: item.qty_system_small,
      qty_system_medium: item.qty_system_medium,
      qty_system_large: item.qty_system_large,
    });
    console.log('Item after processing:', processedItem);
    
    return processedItem;
  });

  // Log for debugging
  console.log('All items to submit:', itemsToSubmit);

  submitting.value = true;
  
  // If draft exists, use update route, otherwise use store
  const routeName = draftId.value ? 'warehouse-stock-opnames.update' : 'warehouse-stock-opnames.store';
  const routeParams = draftId.value ? { id: draftId.value } : {};
  
  // Prepare approvers array (only IDs)
  const approversIds = form.approvers
    .filter(approver => approver && approver.id)
    .map(approver => approver.id);

  // Create a new form with the processed items
  const submitForm = useForm({
    warehouse_id: form.warehouse_id,
    warehouse_division_id: form.warehouse_division_id,
    opname_date: form.opname_date,
    notes: form.notes,
    items: itemsToSubmit,
    approvers: approversIds,
  });
  
  submitForm.post(route(routeName, routeParams), {
    preserveScroll: true,
    onSuccess: () => {
      submitting.value = false;
      // Clear draft ID after successful submit
      draftId.value = null;
    },
    onError: (errors) => {
      submitting.value = false;
      console.error('Error:', errors);
      Swal.fire({
        title: 'Error',
        text: 'Gagal menyimpan stock opname. Silakan coba lagi.',
        icon: 'error',
        confirmButtonColor: '#3085d6'
      });
    },
  });
}

// Load items when warehouse division changes (only if warehouse doesn't require division or division is selected)
watch(() => form.warehouse_division_id, () => {
  if (form.warehouse_division_id) {
    loadItems();
  } else if (!warehouseHasDivisions.value) {
    // If warehouse doesn't have divisions, load items when division is cleared
    loadItems();
  }
});

// Watch form changes for autosave (but not items to avoid overwriting user input)
watch([
  () => form.warehouse_id,
  () => form.warehouse_division_id,
  () => form.opname_date,
  () => form.notes,
], () => {
  triggerAutosave();
});

// Watch items changes separately with debounce to avoid overwriting while user is typing
watch(() => form.items, () => {
  // Only autosave items if user has finished editing (debounced)
  triggerAutosave();
}, { deep: true });

// Auto check divisions and load items if warehouse is already selected
if (form.warehouse_id && form.items.length === 0) {
  onWarehouseChange();
}
</script>

