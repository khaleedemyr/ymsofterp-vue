<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Laporan Stok Akhir Outlet</h1>
          <p class="text-sm text-gray-500 mt-1">Ringkasan stok akhir per outlet & warehouse outlet, lengkap dengan detail kartu stok.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
            Real-time (session)
          </span>
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
            Updated: {{ new Date().toLocaleDateString('id-ID') }}
          </span>
        </div>
      </div>

      <div class="bg-white/70 backdrop-blur border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-5 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold text-gray-600">Pencarian</label>
            <input v-model="search" type="text" placeholder="Cari nama barang atau outlet..." class="px-4 py-2 border border-gray-300 rounded-lg w-full focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold text-gray-600">Outlet</label>
            <select v-model="selectedOutlet" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" :disabled="!outletSelectable">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold text-gray-600">Warehouse Outlet</label>
            <select v-model="selectedWarehouseOutlet" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="">Semua Warehouse Outlet</option>
              <option v-for="w in filteredWarehouseOutlets" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold text-gray-600">Tampilkan</label>
            <div class="flex items-center gap-2">
              <select v-model="perPage" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                <option v-for="n in [10, 25, 50, 100, 500, 1000]" :key="n" :value="n">{{ n }}</option>
              </select>
              <span class="text-sm text-gray-500">data</span>
            </div>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold text-gray-600">Aksi</label>
            <div class="grid grid-cols-2 gap-2">
              <button @click="reloadData" :disabled="loadingReload" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 shadow-sm">
                <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
                <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
                Load
              </button>
              <button @click="exportToExcel" :disabled="exporting || !hasAnySelection || stocks.length === 0" class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 shadow-sm disabled:bg-gray-400 disabled:cursor-not-allowed">
                <span v-if="exporting" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
                <span v-else class="mr-2"><i class="fas fa-file-excel"></i></span>
                Export
              </button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="props.error" class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl my-6 text-center font-semibold">
        {{ props.error }}
      </div>
      <div v-else-if="!hasAnySelection" class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-xl my-6 text-center font-semibold">
        <i class="fas fa-info-circle mr-2"></i>
        Silakan pilih minimal satu filter (Outlet atau Warehouse Outlet), kemudian klik tombol "Load Data" untuk melihat laporan stok akhir.
      </div>
      <div v-else-if="stocks.length === 0" class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-xl my-6 text-center font-semibold">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Tidak ada data stok untuk filter yang dipilih. Coba ubah filter atau pilih kombinasi filter yang berbeda.
      </div>
      <div v-else class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200">
        <div class="px-4 sm:px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
          <div class="text-sm text-gray-600">
            Total <span class="font-semibold text-gray-900">{{ filteredStocks.length }}</span> item
          </div>
          <div class="text-xs text-gray-500">Klik kategori untuk expand/collapse</div>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 sticky top-0 z-10">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Kategori</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nama Barang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Small</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Medium</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Large</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal Update</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-if="Object.keys(groupedStocks).length === 0">
              <tr>
                <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data stok.</td>
              </tr>
            </template>
            <template v-else>
              <template v-for="(categoryGroup, categoryName) in groupedStocks" :key="categoryName || 'no-category'">
                <!-- Category Header Row -->
                <tr class="bg-blue-50 hover:bg-blue-100 cursor-pointer" @click="toggleCategory(categoryName || 'no-category')">
                  <td class="px-6 py-3 font-semibold text-blue-700" colspan="8">
                    <div class="flex items-center gap-2">
                      <i :class="expandedCategories.includes(categoryName || 'no-category') ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"></i>
                      <span>{{ categoryName || 'Tanpa Kategori' }}</span>
                      <span class="text-xs font-normal text-gray-600">({{ categoryGroup.length }} item)</span>
                    </div>
                  </td>
                </tr>
                <!-- Item Rows (shown when expanded) -->
                <template v-if="expandedCategories.includes(categoryName || 'no-category')">
                  <template v-for="row in categoryGroup" :key="row.item_id + '-' + row.outlet_id + '-' + row.warehouse_outlet_id">
                    <!-- Item Row (clickable to expand) -->
                    <tr 
                      class="hover:bg-gray-50 transition cursor-pointer" 
                      @click="toggleItemDetail(row)"
                      :class="{ 'bg-blue-50': expandedItems.includes(getItemKey(row)) }"
                    >
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <div class="flex items-center gap-2">
                          <i :class="expandedItems.includes(getItemKey(row)) ? 'fa-solid fa-chevron-down text-blue-600' : 'fa-solid fa-chevron-right text-gray-400'"></i>
                          <span>{{ row.item_name }}</span>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.outlet_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_outlet_name || '-' }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <span v-if="row.qty_small > 0">{{ Number(row.qty_small).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span v-if="row.small_unit_name">{{ row.small_unit_name }}</span></span>
                        <span v-else>-</span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <span v-if="row.qty_medium > 0">{{ Number(row.qty_medium).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span v-if="row.medium_unit_name">{{ row.medium_unit_name }}</span></span>
                        <span v-else>-</span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <span v-if="row.qty_large > 0">{{ Number(row.qty_large).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span v-if="row.large_unit_name">{{ row.large_unit_name }}</span></span>
                        <span v-else>-</span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.updated_at ? new Date(row.updated_at).toLocaleString() : '-' }}</td>
                    </tr>
                    <!-- Stock Card Detail Row (shown when expanded) -->
                    <tr v-if="expandedItems.includes(getItemKey(row))" class="bg-gray-50">
                      <td colspan="8" class="px-6 py-4">
                        <div v-if="loadingItems[getItemKey(row)]" class="text-center py-4">
                          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                          <p class="mt-2 text-gray-600 text-sm">Memuat detail transaksi...</p>
                        </div>
                        <div v-else-if="itemDetails[getItemKey(row)] && Array.isArray(itemDetails[getItemKey(row)]) && itemDetails[getItemKey(row)].length > 0" class="overflow-x-auto">
                          <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-100">
                              <tr>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-700 uppercase">Tanggal</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700 uppercase">Masuk (Qty)</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700 uppercase">Keluar (Qty)</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700 uppercase">Saldo (Qty)</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-700 uppercase">Referensi</th>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-700 uppercase">Keterangan</th>
                              </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                              <!-- Saldo Awal Bulan Berjalan -->
                              <tr v-if="saldoAwalItems[getItemKey(row)]" class="bg-yellow-50 font-semibold">
                                <td class="px-4 py-2 text-gray-700">
                                  {{ getCurrentMonthFirstDate() }}
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700">-</td>
                                <td class="px-4 py-2 text-right text-gray-700">-</td>
                                <td class="px-4 py-2 text-right text-gray-700">
                                  {{ formatSaldoQty(saldoAwalItems[getItemKey(row)]) }}
                                </td>
                                <td class="px-4 py-2 text-gray-700">Saldo Awal Bulan</td>
                                <td class="px-4 py-2 text-gray-700">Saldo akhir bulan sebelumnya</td>
                              </tr>
                              <!-- Transaction Rows -->
                              <tr 
                                v-for="(card, index) in itemDetails[getItemKey(row)]" 
                                :key="card.id"
                                :class="index === itemDetails[getItemKey(row)].length - 1 ? 'bg-yellow-200 font-bold' : 'hover:bg-gray-50'"
                              >
                                <td class="px-4 py-2 text-gray-700">{{ card.date ? new Date(card.date).toLocaleDateString('id-ID') : '-' }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ formatQty(card, 'in') }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ formatQty(card, 'out') }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ formatSaldoQty(card) }}</td>
                                <td class="px-4 py-2 text-gray-700">
                                  {{ card.reference_type ? card.reference_type + (card.reference_id ? ' #' + card.reference_id : '') : '-' }}
                                </td>
                                <td class="px-4 py-2 text-gray-700">{{ card.description || '-' }}</td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                        <div v-else class="text-center py-4 text-gray-500 text-sm">
                          <div v-if="itemDetails[getItemKey(row)] === undefined">
                            <p>Memuat data...</p>
                          </div>
                          <div v-else-if="itemDetails[getItemKey(row)] && itemDetails[getItemKey(row)].length === 0">
                            Tidak ada data transaksi untuk item ini.
                          </div>
                          <div v-else>
                            <p>Data tidak tersedia.</p>
                            <p class="text-xs mt-1">Debug: {{ JSON.stringify(itemDetails[getItemKey(row)]) }}</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </template>
              </template>
            </template>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4" v-if="Object.keys(groupedStocks).length > 0">
        <div class="text-sm text-gray-600">
          Total {{ Object.keys(groupedStocks).length }} kategori, {{ filteredStocks.length }} item
          <button @click="expandAll" class="ml-4 text-blue-600 hover:text-blue-800 text-sm">Expand All</button>
          <button @click="collapseAll" class="ml-2 text-blue-600 hover:text-blue-800 text-sm">Collapse All</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  stocks: Array,
  outlets: Array,
  user_outlet_id: [String, Number],
  warehouse_outlets: Array,
  error: String
});
const stocks = computed(() => (Array.isArray(props.stocks) ? props.stocks : []));
const outlets = computed(() => (Array.isArray(props.outlets) ? props.outlets : []));
const warehouseOutlets = computed(() => (Array.isArray(props.warehouse_outlets) ? props.warehouse_outlets : []));
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedOutlet = ref('');
const selectedWarehouseOutlet = ref('');
const loadingReload = ref(false);
const exporting = ref(false);

// Check if user has made any selection
const hasAnySelection = computed(() => {
  return selectedOutlet.value || selectedWarehouseOutlet.value;
});

// Set outlet filter sesuai user saat mount
onMounted(() => {
  if (props.user_outlet_id && String(props.user_outlet_id) !== '1') {
    selectedOutlet.value = String(props.user_outlet_id);
  }
});

const outletSelectable = computed(() => String(props.user_outlet_id) === '1');

// Filter warehouse outlets based on selected outlet
const filteredWarehouseOutlets = computed(() => {
  let warehouseOutletsList = warehouseOutlets.value;
  
  // Jika bukan superadmin, hanya tampilkan warehouse outlet milik outlet user
  if (!outletSelectable.value && props.user_outlet_id) {
    warehouseOutletsList = warehouseOutletsList.filter(wo => String(wo.outlet_id) === String(props.user_outlet_id));
  }
  
  // Jika outlet dipilih, filter berdasarkan outlet tersebut
  if (selectedOutlet.value) {
    warehouseOutletsList = warehouseOutletsList.filter(wo => String(wo.outlet_id) === String(selectedOutlet.value));
  }
  
  return warehouseOutletsList;
});

const filteredStocks = computed(() => {
  let data = stocks.value;
  if (selectedOutlet.value) {
    data = data.filter(row => String(row.outlet_id) === String(selectedOutlet.value));
  }
  if (selectedWarehouseOutlet.value) {
    data = data.filter(row => String(row.warehouse_outlet_id) === String(selectedWarehouseOutlet.value));
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.outlet_name && row.outlet_name.toLowerCase().includes(s)) ||
    (row.category_name && row.category_name.toLowerCase().includes(s))
  );
});

// Group stocks by category
const groupedStocks = computed(() => {
  const grouped = {};
  filteredStocks.value.forEach(row => {
    const categoryName = row.category_name || null;
    if (!grouped[categoryName]) {
      grouped[categoryName] = [];
    }
    grouped[categoryName].push(row);
  });
  return grouped;
});

// Expanded categories state
const expandedCategories = ref([]);

// Expanded items state (for stock card detail)
const expandedItems = ref([]);
const itemDetails = ref({});
const saldoAwalItems = ref({});
const loadingItems = ref({});

// Get unique key for item (item_id-outlet_id-warehouse_outlet_id)
function getItemKey(row) {
  return `${row.item_id}-${row.outlet_id}-${row.warehouse_outlet_id || 'null'}`;
}

// Toggle category expansion
function toggleCategory(categoryName) {
  const index = expandedCategories.value.indexOf(categoryName);
  if (index > -1) {
    expandedCategories.value.splice(index, 1);
  } else {
    expandedCategories.value.push(categoryName);
  }
}

// Toggle item detail expansion
async function toggleItemDetail(row) {
  const itemKey = getItemKey(row);
  const index = expandedItems.value.indexOf(itemKey);
  
  if (index > -1) {
    // Collapse
    expandedItems.value.splice(index, 1);
  } else {
    // Expand - fetch detail if not already loaded
    expandedItems.value.push(itemKey);
    
    if (!itemDetails.value[itemKey] && !loadingItems.value[itemKey]) {
      await fetchItemDetail(row);
    }
  }
}

// Fetch stock card detail for an item
async function fetchItemDetail(row) {
  const itemKey = getItemKey(row);
  loadingItems.value[itemKey] = true;
  
  try {
    const params = {
      item_id: row.item_id,
      outlet_id: row.outlet_id,
    };
    
    if (row.warehouse_outlet_id) {
      params.warehouse_outlet_id = row.warehouse_outlet_id;
    }
    
    console.log('Fetching item detail with params:', params);
    // Use web route instead of API route for session-based auth
    const response = await axios.get('/outlet-inventory/stock-card/detail', { 
      params,
      withCredentials: true,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    console.log('Response received:', response.data);
    
    if (response.data && response.data.cards) {
      // Use Vue's reactivity by directly assigning to ensure reactivity
      const cards = Array.isArray(response.data.cards) ? response.data.cards : [];
      itemDetails.value = {
        ...itemDetails.value,
        [itemKey]: cards
      };
      
      if (response.data.saldo_awal) {
        saldoAwalItems.value = {
          ...saldoAwalItems.value,
          [itemKey]: response.data.saldo_awal
        };
      }
      console.log('Item details set for key:', itemKey, 'Count:', cards.length);
      console.log('All itemDetails:', itemDetails.value);
    } else {
      console.warn('No cards data in response:', response.data);
      itemDetails.value = {
        ...itemDetails.value,
        [itemKey]: []
      };
    }
  } catch (error) {
    console.error('Error fetching item detail:', error);
    console.error('Error response:', error.response?.data);
    console.error('Error status:', error.response?.status);
    itemDetails.value[itemKey] = [];
  } finally {
    loadingItems.value[itemKey] = false;
  }
}

// Format quantity (for in/out)
function formatQty(card, type = null) {
  if (type === 'in') {
    const parts = [];
    if (card.in_qty_small > 0) parts.push(`${formatNumber(card.in_qty_small)} ${card.small_unit_name || ''}`);
    if (card.in_qty_medium > 0) parts.push(`${formatNumber(card.in_qty_medium)} ${card.medium_unit_name || ''}`);
    if (card.in_qty_large > 0) parts.push(`${formatNumber(card.in_qty_large)} ${card.large_unit_name || ''}`);
    return parts.length > 0 ? parts.join(' | ') : '-';
  } else if (type === 'out') {
    const parts = [];
    if (card.out_qty_small > 0) parts.push(`${formatNumber(card.out_qty_small)} ${card.small_unit_name || ''}`);
    if (card.out_qty_medium > 0) parts.push(`${formatNumber(card.out_qty_medium)} ${card.medium_unit_name || ''}`);
    if (card.out_qty_large > 0) parts.push(`${formatNumber(card.out_qty_large)} ${card.large_unit_name || ''}`);
    return parts.length > 0 ? parts.join(' | ') : '-';
  } else {
    return '-';
  }
}

// Format saldo quantity
function formatSaldoQty(card) {
  if (!card) return '-';
  
  // Handle saldo awal format (object with small, medium, large)
  if (card.small !== undefined || card.medium !== undefined || card.large !== undefined) {
    const parts = [];
    if (card.small !== undefined && card.small !== null) {
      parts.push(`${formatNumber(card.small)} ${card.small_unit_name || ''}`);
    }
    if (card.medium !== undefined && card.medium !== null) {
      parts.push(`${formatNumber(card.medium)} ${card.medium_unit_name || ''}`);
    }
    if (card.large !== undefined && card.large !== null) {
      parts.push(`${formatNumber(card.large)} ${card.large_unit_name || ''}`);
    }
    return parts.length > 0 ? parts.join(' | ') : '-';
  }
  
  // Handle card format (saldo_qty_small, saldo_qty_medium, saldo_qty_large)
  const parts = [];
  if (card.saldo_qty_small !== undefined && card.saldo_qty_small !== null) {
    parts.push(`${formatNumber(card.saldo_qty_small)} ${card.small_unit_name || ''}`);
  }
  if (card.saldo_qty_medium !== undefined && card.saldo_qty_medium !== null) {
    parts.push(`${formatNumber(card.saldo_qty_medium)} ${card.medium_unit_name || ''}`);
  }
  if (card.saldo_qty_large !== undefined && card.saldo_qty_large !== null) {
    parts.push(`${formatNumber(card.saldo_qty_large)} ${card.large_unit_name || ''}`);
  }
  return parts.length > 0 ? parts.join(' | ') : '-';
}

// Format number helper
function formatNumber(val) {
  if (val == null) return '0';
  if (Number(val) % 1 === 0) return Number(val).toLocaleString('id-ID');
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// Get current month first date formatted
function getCurrentMonthFirstDate() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const firstDay = new Date(year, month, 1);
  return firstDay.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
}

// Auto-expand all categories on mount or when data changes
watch(() => props.stocks, () => {
  if (stocks.value.length > 0) {
    const categories = Object.keys(groupedStocks.value);
    expandedCategories.value = categories;
  }
}, { immediate: true });

// Expand/Collapse all functions
function expandAll() {
  const categories = Object.keys(groupedStocks.value);
  expandedCategories.value = categories;
}

function collapseAll() {
  expandedCategories.value = [];
}

// Clear warehouse outlet selection when outlet changes
watch(selectedOutlet, () => {
  selectedWarehouseOutlet.value = '';
});

function reloadData() {
  // Validasi: harus ada minimal satu filter yang dipilih
  if (!hasAnySelection.value) {
    alert('Silakan pilih minimal satu filter terlebih dahulu!');
    return;
  }
  
  loadingReload.value = true
  
  // Saat pencarian berubah, reset ke halaman 1
  if (search.value?.trim()) {
    page.value = 1
  }
  
  // Prepare parameters (termasuk search agar filter di server)
  const params = {
    outlet_id: selectedOutlet.value || '',
    warehouse_outlet_id: selectedWarehouseOutlet.value || '',
    search: search.value?.trim() || '',
    per_page: perPage.value,
    page: page.value
  }
  
  // Remove empty parameters
  Object.keys(params).forEach(key => {
    if (params[key] === '' || params[key] == null) {
      delete params[key]
    }
  })
  
  // Make request to server
  router.get('/outlet-inventory/stock-position', params, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      loadingReload.value = false
    },
    onError: (errors) => {
      loadingReload.value = false
      console.error('Error loading data:', errors)
    }
  })
}

function exportToExcel() {
  // Validasi: harus ada minimal satu filter yang dipilih
  if (!hasAnySelection.value) {
    alert('Silakan pilih minimal satu filter terlebih dahulu!');
    return;
  }
  
  // Validasi: harus ada data untuk di-export
  if (stocks.value.length === 0) {
    alert('Tidak ada data untuk di-export. Silakan load data terlebih dahulu!');
    return;
  }
  
  exporting.value = true;
  
  // Prepare parameters (sama dengan reload: outlet, warehouse, search)
  const params = new URLSearchParams();
  if (selectedOutlet.value) params.set('outlet_id', selectedOutlet.value);
  if (selectedWarehouseOutlet.value) params.set('warehouse_outlet_id', selectedWarehouseOutlet.value);
  if (search.value?.trim()) params.set('search', search.value.trim());
  
  // Create download link
  const url = `/outlet-inventory/stock-position/export?${params.toString()}`;
  const link = document.createElement('a');
  link.href = url;
  link.download = '';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  
  // Reset exporting state after a delay
  setTimeout(() => {
    exporting.value = false;
  }, 2000);
}
</script> 