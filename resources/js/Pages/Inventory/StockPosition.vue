<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Laporan Stok Akhir</h1>
        <button 
          @click="exportToExcel" 
          :disabled="isExporting"
          class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
        >
          <i v-if="isExporting" class="fas fa-spinner fa-spin"></i>
          <i v-else class="fas fa-file-excel"></i>
          {{ isExporting ? 'Exporting...' : 'Export Excel' }}
        </button>
      </div>
      
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="search" type="text" placeholder="Cari nama barang atau warehouse..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
       
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nama Barang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Small</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Medium</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Large</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal Update</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!filteredStocks.length">
              <td colspan="6" class="text-center py-10 text-gray-400">Tidak ada data stok.</td>
            </tr>
            <template v-for="row in paginatedStocks" :key="row.item_id + '-' + row.warehouse_id">
              <!-- Item Row (clickable to expand) -->
              <tr 
                class="hover:bg-gray-50 transition cursor-pointer" 
                @click="toggleItemDetail(row)"
                :class="{ 'bg-blue-50': expandedItems.includes(getItemKey(row)) }"
              >
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  <div class="flex items-center gap-2">
                    <i :class="expandedItems.includes(getItemKey(row)) ? 'fa-solid fa-chevron-down text-blue-600' : 'fa-solid fa-chevron-right text-gray-400'"></i>
                    <span>{{ row.item_name }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                  <span v-if="row.display_small > 0">{{ displayQty(row.display_small) }} <span v-if="row.small_unit_name">{{ row.small_unit_name }}</span></span>
                  <span v-else>-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                  <span v-if="row.display_medium > 0">{{ displayQty(row.display_medium, 2) }} <span v-if="row.medium_unit_name">{{ row.medium_unit_name }}</span></span>
                  <span v-else>-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                  <span v-if="row.display_large > 0">{{ displayQty(row.display_large, 2) }} <span v-if="row.large_unit_name">{{ row.large_unit_name }}</span></span>
                  <span v-else>-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.updated_at ? new Date(row.updated_at).toLocaleString() : '-' }}</td>
              </tr>
              <!-- Stock Card Detail Row (shown when expanded) -->
              <tr v-if="expandedItems.includes(getItemKey(row))" class="bg-gray-50">
                <td colspan="6" class="px-6 py-4">
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
                            {{ formatReference(card) }}
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
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4" v-if="filteredStocks.length">
        <div class="text-sm text-gray-600">
          Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ filteredStocks.length }} data
        </div>
        <div class="flex gap-1">
          <button @click="prevPage" :disabled="page === 1" class="px-3 py-1 rounded border text-sm" :class="page === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&lt;</button>
          <span class="px-2">Halaman {{ page }} / {{ totalPages }}</span>
          <button @click="nextPage" :disabled="page === totalPages" class="px-3 py-1 rounded border text-sm" :class="page === totalPages ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&gt;</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  stocks: Array,
  warehouses: Array,
  warehouse_outlets: Array
});

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
  let data = props.stocks;
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