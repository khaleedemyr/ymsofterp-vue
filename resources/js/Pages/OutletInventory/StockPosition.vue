<template>
  <AppLayout>
    <InventoryReportPage
      eyebrow="Outlet Inventory"
      title="Laporan Stok Akhir Outlet"
      subtitle="Ringkasan stok per outlet & warehouse. Klik barang untuk melihat kartu stok — transaksi serial bisa di-expand per kedatangan."
      variant="outlet"
    >
      <template #badges>
        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur">
          <i class="fa-solid fa-bolt mr-1.5 opacity-80"></i> Real-time
        </span>
        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur">
          {{ new Date().toLocaleDateString('id-ID') }}
        </span>
      </template>

      <template v-if="stocks.length > 0 && hasAnySelection" #stats>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-sky-200/80">Total Item</p>
          <p class="mt-1 text-lg font-bold tabular-nums">{{ filteredStocks.length }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-sky-200/80">Kategori</p>
          <p class="mt-1 text-lg font-bold tabular-nums">{{ Object.keys(groupedStocks).length }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur xl:col-span-2">
          <p class="text-[11px] uppercase tracking-wide text-sky-200/80">Tips</p>
          <p class="mt-1 text-xs font-medium leading-snug">Expand kategori → klik barang → lihat mutasi & nomor seri</p>
        </div>
      </template>

      <template #filters>
      <div>
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
      </template>

      <div v-if="props.error" class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-center text-sm font-semibold text-rose-900">
        {{ props.error }}
      </div>
      <div v-else-if="!hasAnySelection" class="mb-5 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-center text-sm font-semibold text-sky-900">
        <i class="fas fa-info-circle mr-2"></i>
        Silakan pilih minimal satu filter (Outlet atau Warehouse Outlet), kemudian klik tombol "Load Data" untuk melihat laporan stok akhir.
      </div>
      <div v-else-if="stocks.length === 0" class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-center text-sm font-semibold text-amber-900">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Tidak ada data stok untuk filter yang dipilih. Coba ubah filter atau pilih kombinasi filter yang berbeda.
      </div>
      <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-6">
          <p class="text-sm text-slate-600">Total <span class="font-bold text-slate-900">{{ filteredStocks.length }}</span> item</p>
          <p class="text-xs text-slate-500">Klik kategori / barang untuk expand</p>
        </div>
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="sticky top-0 z-10 bg-slate-800 text-white">
            <tr>
              <th class="th-cell">Kategori</th>
              <th class="th-cell">Nama Barang</th>
              <th class="th-cell">Outlet</th>
              <th class="th-cell">Warehouse</th>
              <th class="th-cell text-right">Qty Small</th>
              <th class="th-cell text-right">Qty Medium</th>
              <th class="th-cell text-right">Qty Large</th>
              <th class="th-cell">Update</th>
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
                <tr class="cursor-pointer bg-sky-50 hover:bg-sky-100/80" @click="toggleCategory(categoryName || 'no-category')">
                  <td class="px-5 py-3 font-semibold text-sky-800" colspan="8">
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
                      :class="{ 'bg-sky-50 ring-1 ring-inset ring-sky-200': expandedItems.includes(getItemKey(row)) }"
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
                              <GroupedStockCardBody
                                :cards="itemDetails[getItemKey(row)]"
                                :saldo-awal="saldoAwalItems[getItemKey(row)]"
                                :saldo-awal-date="getCurrentMonthFirstDate()"
                                :format-qty="formatQty"
                                :format-saldo-qty="formatSaldoQty"
                                compact
                              />
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
      </div>
      <div class="mt-4 flex items-center justify-between" v-if="Object.keys(groupedStocks).length > 0">
        <div class="text-sm text-gray-600">
          Total {{ Object.keys(groupedStocks).length }} kategori, {{ filteredStocks.length }} item
          <button @click="expandAll" class="ml-4 text-blue-600 hover:text-blue-800 text-sm">Expand All</button>
          <button @click="collapseAll" class="ml-2 text-blue-600 hover:text-blue-800 text-sm">Collapse All</button>
        </div>
      </div>
    </InventoryReportPage>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InventoryReportPage from '@/Components/Inventory/InventoryReportPage.vue';
import GroupedStockCardBody from '@/Components/Inventory/GroupedStockCardBody.vue';
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

<style scoped>
.th-cell { @apply px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider sm:px-5; }
</style>