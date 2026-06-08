<template>
  <AppLayout>
    <InventoryReportPage
      eyebrow="Warehouse Inventory"
      title="Laporan Stok Akhir Gudang"
      subtitle="Posisi stok per warehouse. Klik barang untuk kartu stok lengkap — grup serial per DO/kedatangan bisa di-expand."
      variant="warehouse"
    >
      <template #badges>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-emerald-500/90 px-4 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-50"
          :disabled="isExporting"
          @click="exportToExcel"
        >
          <i :class="isExporting ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-file-excel'"></i>
          {{ isExporting ? 'Exporting...' : 'Export Excel' }}
        </button>
      </template>

      <template v-if="filteredStocks.length" #stats>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-indigo-200/80">Total Item</p>
          <p class="mt-1 text-lg font-bold tabular-nums">{{ filteredStocks.length }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur xl:col-span-3">
          <p class="text-[11px] uppercase tracking-wide text-indigo-200/80">Navigasi</p>
          <p class="mt-1 text-xs font-medium">Klik nama barang → mutasi stok → expand baris SN untuk nomor seri</p>
        </div>
      </template>

      <template #filters>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="xl:col-span-2 field">
            <label>Pencarian</label>
            <input v-model="search" type="text" placeholder="Cari nama barang atau warehouse..." class="field-input" />
          </div>
          <div class="field">
            <label>Warehouse</label>
            <select v-model="selectedWarehouse" class="field-input">
              <option value="">Semua Warehouse</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="field">
            <label>Per Halaman</label>
            <select v-model="perPage" class="field-input">
              <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
        </div>
      </template>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-800 text-white">
            <tr>
              <th class="th-cell">Nama Barang</th>
              <th class="th-cell">Warehouse</th>
              <th class="th-cell text-right">Qty Small</th>
              <th class="th-cell text-right">Qty Medium</th>
              <th class="th-cell text-right">Qty Large</th>
              <th class="th-cell">Update</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="!filteredStocks.length">
              <td colspan="6" class="px-6 py-12 text-center text-slate-400">Tidak ada data stok.</td>
            </tr>
            <template v-for="row in paginatedStocks" :key="row.item_id + '-' + row.warehouse_id">
              <tr
                class="cursor-pointer transition hover:bg-slate-50/80"
                @click="toggleItemDetail(row)"
                :class="{ 'bg-indigo-50 ring-1 ring-inset ring-indigo-200': expandedItems.includes(getItemKey(row)) }"
              >
                <td class="td-cell font-medium text-slate-900">
                  <div class="flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-100 text-indigo-700">
                      <i :class="expandedItems.includes(getItemKey(row)) ? 'fa-solid fa-chevron-down text-[10px]' : 'fa-solid fa-chevron-right text-[10px]'"></i>
                    </span>
                    <span>{{ row.item_name }}</span>
                  </div>
                </td>
                <td class="td-cell">{{ row.warehouse_name }}</td>
                <td class="td-cell text-right tabular-nums">
                  <span v-if="row.display_small > 0">{{ displayQty(row.display_small) }} <span v-if="row.small_unit_name" class="text-slate-500">{{ row.small_unit_name }}</span></span>
                  <span v-else>-</span>
                </td>
                <td class="td-cell text-right tabular-nums">
                  <span v-if="row.display_medium > 0">{{ displayQty(row.display_medium, 2) }} <span v-if="row.medium_unit_name" class="text-slate-500">{{ row.medium_unit_name }}</span></span>
                  <span v-else>-</span>
                </td>
                <td class="td-cell text-right tabular-nums">
                  <span v-if="row.display_large > 0">{{ displayQty(row.display_large, 2) }} <span v-if="row.large_unit_name" class="text-slate-500">{{ row.large_unit_name }}</span></span>
                  <span v-else>-</span>
                </td>
                <td class="td-cell text-slate-500">{{ row.updated_at ? new Date(row.updated_at).toLocaleString('id-ID') : '-' }}</td>
              </tr>
              <tr v-if="expandedItems.includes(getItemKey(row))" class="bg-indigo-50/40">
                <td colspan="6" class="px-4 py-4 sm:px-6">
                  <div v-if="loadingItems[getItemKey(row)]" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <p class="mt-2 text-gray-600 text-sm">Memuat detail transaksi...</p>
                  </div>
                  <div v-else-if="itemDetails[getItemKey(row)] && Array.isArray(itemDetails[getItemKey(row)]) && itemDetails[getItemKey(row)].length > 0" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-800 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white">Kartu Stok — {{ row.item_name }}</div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                      <thead class="bg-slate-100">
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
                          :format-reference="formatReference"
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
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        </div>
      </div>
      <div class="mt-4 flex items-center justify-between" v-if="filteredStocks.length">
        <p class="text-sm text-slate-600">{{ startIndex + 1 }}–{{ endIndex }} dari {{ filteredStocks.length }}</p>
        <div class="flex gap-2">
          <button type="button" class="pager-btn" :disabled="page === 1" @click="prevPage"><i class="fa-solid fa-chevron-left"></i></button>
          <span class="px-2 text-sm text-slate-600">{{ page }} / {{ totalPages }}</span>
          <button type="button" class="pager-btn" :disabled="page === totalPages" @click="nextPage"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
    </InventoryReportPage>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InventoryReportPage from '@/Components/Inventory/InventoryReportPage.vue';
import GroupedStockCardBody from '@/Components/Inventory/GroupedStockCardBody.vue';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  stocks: Array,
  warehouses: Array,
  warehouse_outlets: Array
});
const stocks = computed(() => (Array.isArray(props.stocks) ? props.stocks : []));
const warehouses = computed(() => (Array.isArray(props.warehouses) ? props.warehouses : []));

const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedWarehouse = ref('');
const isExporting = ref(false);

// Expanded items state (for stock card detail)
const expandedItems = ref([]);
const itemDetails = ref({});
const saldoAwalItems = ref({});
const loadingItems = ref({});

const filteredStocks = computed(() => {
  let data = stocks.value;
  if (selectedWarehouse.value) {
    data = data.filter(row => String(row.warehouse_id) === String(selectedWarehouse.value));
  }

  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.warehouse_name && row.warehouse_name.toLowerCase().includes(s))
  );
});

const totalPages = computed(() => Math.ceil(filteredStocks.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, filteredStocks.value.length));
const paginatedStocks = computed(() => filteredStocks.value.slice(startIndex.value, endIndex.value));

function prevPage() {
  if (page.value > 1) page.value--;
}
function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
watch([perPage, search], () => { page.value = 1; });

function displayQty(val, digits = 0) {
  if (!val || Number(val) === 0) return '-';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits });
}
function displayValue(val) {
  if (!val || Number(val) === 0) return '-';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function exportToExcel() {
  if (isExporting.value) return;
  
  try {
    isExporting.value = true;
    
    const params = new URLSearchParams();
    if (selectedWarehouse.value) {
      params.append('warehouse_id', selectedWarehouse.value);
    }
    
    const response = await axios.get(route('inventory.stock-position.export'), {
      params: params,
      responseType: 'blob'
    });
    
    // Create blob and download
    const blob = new Blob([response.data], { 
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `laporan_stok_akhir_${new Date().toISOString().split('T')[0]}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
  } catch (error) {
    console.error('Export error:', error);
    alert('Terjadi kesalahan saat export. Silakan coba lagi.');
  } finally {
    isExporting.value = false;
  }
}

// Get unique key for item (item_id-warehouse_id)
function getItemKey(row) {
  return `${row.item_id}-${row.warehouse_id}`;
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
      warehouse_id: row.warehouse_id,
    };
    
    console.log('Fetching item detail with params:', params);
    const response = await axios.get('/inventory/stock-card/detail', { 
      params,
      withCredentials: true,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    console.log('Response received:', response.data);
    
    if (response.data && response.data.cards) {
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
    itemDetails.value = {
      ...itemDetails.value,
      [itemKey]: []
    };
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

// Format reference
function formatReference(card) {
  if (!card.reference_type) return '-';
  
  let ref = card.reference_type;
  if (card.reference_type === 'good_receive' && card.reference_number) {
    ref += ' #' + card.reference_number;
  } else if (card.reference_type === 'warehouse_transfer' && card.transfer_number) {
    ref += ' #' + card.transfer_number;
  } else if (card.reference_type === 'delivery_order' && card.do_number) {
    ref += ' #' + card.do_number;
  } else if (card.reference_id) {
    ref += ' #' + card.reference_id;
  }
  
  return ref;
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
</script>

<style scoped>
.field label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500; }
.field-input { @apply w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100; }
.th-cell { @apply px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider sm:px-5; }
.td-cell { @apply whitespace-nowrap px-4 py-3.5 text-sm text-slate-700 sm:px-5; }
.pager-btn { @apply inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40; }
</style>