<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Edit Warehouse Stock Opname
        </h1>
        <Link
          :href="route('warehouse-stock-opnames.show', stockOpname.id)"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submitForm" class="bg-white rounded-xl shadow-lg p-6">
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
            <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse Division</label>
            <select
              v-model="form.warehouse_division_id"
              @change="loadItems"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Semua Division</option>
              <option v-for="wd in filteredWarehouseDivisions" :key="wd.id" :value="wd.id">
                {{ wd.name }}
              </option>
            </select>
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
            <div class="flex gap-2">
              <button
                type="button"
                @click="autoFillAll"
                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 font-semibold"
              >
                <i class="fa-solid fa-equals mr-2"></i> Auto Fill Semua (=)
              </button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase w-48">Item</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-32">Qty System<br/>Small</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-32">Qty System<br/>Medium</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-32">Qty System<br/>Large</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Small
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Medium
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Large
                  </th>
                  <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase w-48">Selisih</th>
                  <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase w-48">Alasan</th>
                  <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase w-20">Action</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <template v-for="(category, categoryName) in groupedItems" :key="categoryName">
                  <!-- Category Header Row -->
                  <tr
                    class="bg-blue-50 hover:bg-blue-100 cursor-pointer transition"
                    @click="toggleCategory(categoryName)"
                  >
                    <td class="px-4 py-3 font-bold text-gray-800" colspan="10">
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
                    <td class="px-3 py-3 text-sm text-right text-gray-700">
                      <div class="font-medium">{{ formatNumber(item.qty_system_small) }}</div>
                      <div class="text-xs text-gray-500">{{ item.small_unit_name }}</div>
                    </td>
                    <td class="px-3 py-3 text-sm text-right text-gray-700">
                      <div class="font-medium">{{ formatNumber(item.qty_system_medium) }}</div>
                      <div class="text-xs text-gray-500">{{ item.medium_unit_name }}</div>
                    </td>
                    <td class="px-3 py-3 text-sm text-right text-gray-700">
                      <div class="font-medium">{{ formatNumber(item.qty_system_large) }}</div>
                      <div class="text-xs text-gray-500">{{ item.large_unit_name }}</div>
                    </td>
                    <td class="px-3 py-3">
                      <div class="flex items-center gap-2">
                        <input
                          v-model.number="item.qty_physical_small"
                          type="number"
                          step="0.01"
                          min="0"
                          @input="onQtyPhysicalChange(item, 'small')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          :placeholder="formatNumber(item.qty_system_small)"
                        />
                        <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.small_unit_name || '-' }}</span>
                      </div>
                    </td>
                    <td class="px-3 py-3">
                      <div class="flex items-center gap-2">
                        <input
                          v-model.number="item.qty_physical_medium"
                          type="number"
                          step="0.01"
                          min="0"
                          @input="onQtyPhysicalChange(item, 'medium')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          :placeholder="formatNumber(item.qty_system_medium)"
                        />
                        <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.medium_unit_name || '-' }}</span>
                      </div>
                    </td>
                    <td class="px-3 py-3">
                      <div class="flex items-center gap-2">
                        <input
                          v-model.number="item.qty_physical_large"
                          type="number"
                          step="0.01"
                          min="0"
                          @input="onQtyPhysicalChange(item, 'large')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          :placeholder="formatNumber(item.qty_system_large)"
                        />
                        <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.large_unit_name || '-' }}</span>
                      </div>
                    </td>
                    <td class="px-3 py-3 text-center text-sm">
                      <div v-if="hasDifference(item)" class="space-y-1">
                        <div
                          v-for="(diff, idx) in getDifferenceArray(item)"
                          :key="idx"
                          :class="getDifferenceClass(item)"
                          class="px-2 py-1 rounded text-xs font-semibold whitespace-nowrap"
                        >
                          {{ diff }}
                        </div>
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
                    <td class="px-3 py-3 text-center">
                      <button
                        type="button"
                        @click="autoFillItem(item)"
                        class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors shadow-sm hover:shadow-md"
                        title="Auto fill dengan qty system"
                      >
                        <i class="fa-solid fa-equals"></i>
                      </button>
                    </td>
                  </tr>
                </template>
              </tbody>
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
            Update Stock Opname
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

const props = defineProps({
  stockOpname: Object,
  warehouses: Array,
  warehouseDivisions: Array,
  items: Array,
  approvers: Array,
});

const form = useForm({
  warehouse_id: props.stockOpname?.warehouse_id || '',
  warehouse_division_id: props.stockOpname?.warehouse_division_id || '',
  opname_date: props.stockOpname?.opname_date || new Date().toISOString().split('T')[0],
  notes: props.stockOpname?.notes || '',
  items: props.items || [],
});

const loadingItems = ref(false);
const submitting = ref(false);
const expandedCategories = ref(new Set());

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
  let divisions = props.warehouseDivisions || [];
  
  if (form.warehouse_id) {
    divisions = divisions.filter(wd => String(wd.warehouse_id) === String(form.warehouse_id));
  }
  
  return divisions;
});

function onWarehouseChange() {
  form.warehouse_division_id = '';
  form.items = [];
}

async function loadItems() {
  if (!form.warehouse_id) {
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
    alert('Gagal memuat items. Silakan coba lagi.');
  } finally {
    loadingItems.value = false;
  }
}

function autoFillItem(item) {
  item.qty_physical_small = item.qty_system_small;
  item.qty_physical_medium = item.qty_system_medium;
  item.qty_physical_large = item.qty_system_large;
  item.reason = '';
  calculateDifference(item);
}

function autoFillAll() {
  form.items.forEach(item => {
    autoFillItem(item);
  });
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
    
    if (response.data.success) {
      approverResults.value = response.data.users;
      showApproverDropdown.value = true;
    }
  } catch (error) {
    console.error('Failed to load approvers:', error);
    approverResults.value = [];
  }
}

function onApproverSearch() {
  if (approverSearch.value.length >= 2) {
    loadApprovers(approverSearch.value);
  } else {
    approverResults.value = [];
    showApproverDropdown.value = false;
  }
}

function addApprover(user) {
  if (!user || !user.id) {
    console.error('Invalid user object:', user);
    return;
  }
  // Check if user already exists
  if (!form.approvers.find(approver => approver && approver.id === user.id)) {
    form.approvers.push(user);
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
}

function removeApprover(index) {
  form.approvers.splice(index, 1);
}

function reorderApprover(fromIndex, toIndex) {
  const approver = form.approvers.splice(fromIndex, 1)[0];
  form.approvers.splice(toIndex, 0, approver);
}

function submitForm() {
  if (form.items.length === 0) {
    alert('Minimal harus ada 1 item.');
    return;
  }

  submitting.value = true;
  form.put(route('warehouse-stock-opnames.update', props.stockOpname.id), {
    preserveScroll: true,
    onSuccess: () => {
      submitting.value = false;
    },
    onError: (errors) => {
      submitting.value = false;
      console.error('Error:', errors);
    },
  });
}

// Load items when warehouse or warehouse division changes
watch(() => [form.warehouse_id, form.warehouse_division_id], () => {
  if (form.warehouse_id) {
    loadItems();
  }
});

// Auto load items if already selected
if (form.warehouse_id && form.items.length === 0) {
  loadItems();
}
</script>

