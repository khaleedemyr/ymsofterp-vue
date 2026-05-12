<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Asset Inventory Report - Stok Akhir</h1>
      </div>

      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4 flex-wrap">
        <input v-model="search" type="text" placeholder="Cari nama barang atau warehouse..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />

        <div v-if="isHQ" class="flex items-center gap-2">
          <label class="text-sm">Outlet</label>
          <select v-model="selectedOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Outlet</option>
            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in filteredWarehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-sm">Periode Kartu Stok</label>
          <input type="date" v-model="fromDate" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
          <span>-</span>
          <input type="date" v-model="toDate" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
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
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nama Barang</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Small</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Medium</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Large</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Value</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Update</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!filteredStocks.length">
                <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data stok asset.</td>
              </tr>
              <template v-for="row in paginatedStocks" :key="getItemKey(row)">
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
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.outlet_name || '-' }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_name }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <span v-if="Number(row.qty_small) > 0">{{ displayQty(row.qty_small) }} <span class="text-gray-400">{{ row.small_unit_name }}</span></span>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <span v-if="Number(row.qty_medium) > 0">{{ displayQty(row.qty_medium, 2) }} <span class="text-gray-400">{{ row.medium_unit_name }}</span></span>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <span v-if="Number(row.qty_large) > 0">{{ displayQty(row.qty_large, 2) }} <span class="text-gray-400">{{ row.large_unit_name }}</span></span>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">{{ displayValue(row.value) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ row.updated_at ? new Date(row.updated_at).toLocaleString('id-ID') : '-' }}</td>
                </tr>

                <!-- Stock Card Detail Row -->
                <tr v-if="expandedItems.includes(getItemKey(row))" class="bg-gray-50">
                  <td colspan="8" class="px-6 py-4">
                    <div v-if="loadingItems[getItemKey(row)]" class="text-center py-4">
                      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                      <p class="mt-2 text-gray-600 text-sm">Memuat kartu stok...</p>
                    </div>
                    <div v-else-if="itemDetails[getItemKey(row)] && itemDetails[getItemKey(row)].length > 0" class="overflow-x-auto">
                      <div class="text-xs text-gray-500 mb-2">
                        Periode: {{ fromDate || 'awal bulan' }} s/d {{ toDate || 'akhir bulan' }}
                      </div>
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
                          <!-- Saldo Awal -->
                          <tr v-if="saldoAwalItems[getItemKey(row)]" class="bg-yellow-50 font-semibold">
                            <td class="px-4 py-2 text-gray-700">{{ fromDate || getCurrentMonthFirstDate() }}</td>
                            <td class="px-4 py-2 text-right text-gray-700">-</td>
                            <td class="px-4 py-2 text-right text-gray-700">-</td>
                            <td class="px-4 py-2 text-right text-gray-700">{{ formatSaldoQty(saldoAwalItems[getItemKey(row)]) }}</td>
                            <td class="px-4 py-2 text-gray-700">Saldo Awal</td>
                            <td class="px-4 py-2 text-gray-700">Saldo sebelum periode</td>
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
                            <td class="px-4 py-2 text-right text-gray-700">{{ formatSaldoQtyCard(card) }}</td>
                            <td class="px-4 py-2 text-gray-700">
                              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="refBadgeClass(card.reference_type)">
                                {{ card.reference_label || formatReference(card) }}
                              </span>
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ card.description || '-' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else class="text-center py-4 text-gray-500 text-sm">
                      Tidak ada transaksi dalam periode ini.
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
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
  outlets: Array,
  warehouseOutlets: Array,
  user: Object,
});

const stocks = computed(() => (Array.isArray(props.stocks) ? props.stocks : []));
const outlets = computed(() => (Array.isArray(props.outlets) ? props.outlets : []));
const isHQ = computed(() => String(props.user?.id_outlet) === '1');

const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedOutlet = ref('');
const selectedWarehouse = ref('');

const now = new Date();
const firstOfMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
const lastOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
const endOfMonth = `${lastOfMonth.getFullYear()}-${String(lastOfMonth.getMonth() + 1).padStart(2, '0')}-${String(lastOfMonth.getDate()).padStart(2, '0')}`;
const fromDate = ref(firstOfMonth);
const toDate = ref(endOfMonth);

const expandedItems = ref([]);
const itemDetails = ref({});
const saldoAwalItems = ref({});
const loadingItems = ref({});

const filteredWarehouses = computed(() => {
  const all = Array.isArray(props.warehouseOutlets) ? props.warehouseOutlets : [];
  if (!selectedOutlet.value) return all;
  return all.filter(w => String(w.outlet_id) === String(selectedOutlet.value));
});

const filteredStocks = computed(() => {
  let data = stocks.value;
  if (selectedOutlet.value) {
    data = data.filter(row => String(row.outlet_id) === String(selectedOutlet.value));
  }
  if (selectedWarehouse.value) {
    data = data.filter(row => String(row.warehouse_outlet_id) === String(selectedWarehouse.value));
  }
  if (search.value) {
    const s = search.value.toLowerCase();
    data = data.filter(row =>
      (row.item_name && row.item_name.toLowerCase().includes(s)) ||
      (row.warehouse_name && row.warehouse_name.toLowerCase().includes(s))
    );
  }
  return data;
});

const totalPages = computed(() => Math.ceil(filteredStocks.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, filteredStocks.value.length));
const paginatedStocks = computed(() => filteredStocks.value.slice(startIndex.value, endIndex.value));

function prevPage() { if (page.value > 1) page.value--; }
function nextPage() { if (page.value < totalPages.value) page.value++; }
watch([perPage, search, selectedOutlet, selectedWarehouse], () => { page.value = 1; });

// When date range changes, clear cached stock card details so they reload with new dates
watch([fromDate, toDate], () => {
  expandedItems.value = [];
  itemDetails.value = {};
  saldoAwalItems.value = {};
});

function getItemKey(row) {
  return `${row.inventory_item_id}-${row.warehouse_outlet_id}`;
}

async function toggleItemDetail(row) {
  const key = getItemKey(row);
  const idx = expandedItems.value.indexOf(key);
  if (idx > -1) {
    expandedItems.value.splice(idx, 1);
  } else {
    expandedItems.value.push(key);
    if (!itemDetails.value[key] && !loadingItems.value[key]) {
      await fetchItemDetail(row);
    }
  }
}

async function fetchItemDetail(row) {
  const key = getItemKey(row);
  loadingItems.value[key] = true;
  try {
    const response = await axios.get('/asset-inventory-report/stock-card/detail', {
      params: {
        inventory_item_id: row.inventory_item_id,
        warehouse_outlet_id: row.warehouse_outlet_id,
        from: fromDate.value || undefined,
        to: toDate.value || undefined,
      },
      withCredentials: true,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (response.data && response.data.cards) {
      itemDetails.value = { ...itemDetails.value, [key]: response.data.cards };
      if (response.data.saldo_awal) {
        saldoAwalItems.value = { ...saldoAwalItems.value, [key]: response.data.saldo_awal };
      }
    } else {
      itemDetails.value = { ...itemDetails.value, [key]: [] };
    }
  } catch {
    itemDetails.value = { ...itemDetails.value, [key]: [] };
  } finally {
    loadingItems.value[key] = false;
  }
}

function displayQty(val, digits = 0) {
  if (!val || Number(val) === 0) return '-';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits });
}

function displayValue(val) {
  if (!val || Number(val) === 0) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatNumber(val) {
  if (val == null) return '0';
  if (Number(val) % 1 === 0) return Number(val).toLocaleString('id-ID');
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatQty(card, type) {
  const prefix = type === 'in' ? 'in_qty_' : 'out_qty_';
  const parts = [];
  if (Number(card[prefix + 'small']) > 0) parts.push(`${formatNumber(card[prefix + 'small'])} ${card.small_unit_name || ''}`);
  if (Number(card[prefix + 'medium']) > 0) parts.push(`${formatNumber(card[prefix + 'medium'])} ${card.medium_unit_name || ''}`);
  if (Number(card[prefix + 'large']) > 0) parts.push(`${formatNumber(card[prefix + 'large'])} ${card.large_unit_name || ''}`);
  return parts.length > 0 ? parts.join(' | ') : '-';
}

function formatSaldoQty(saldo) {
  if (!saldo) return '-';
  const parts = [];
  if (saldo.small != null && Number(saldo.small) !== 0) parts.push(`${formatNumber(saldo.small)} ${saldo.small_unit_name || ''}`);
  if (saldo.medium != null && Number(saldo.medium) !== 0) parts.push(`${formatNumber(saldo.medium)} ${saldo.medium_unit_name || ''}`);
  if (saldo.large != null && Number(saldo.large) !== 0) parts.push(`${formatNumber(saldo.large)} ${saldo.large_unit_name || ''}`);
  return parts.length > 0 ? parts.join(' | ') : '0';
}

function formatSaldoQtyCard(card) {
  const parts = [];
  if (card.saldo_qty_small != null) parts.push(`${formatNumber(card.saldo_qty_small)} ${card.small_unit_name || ''}`);
  if (card.saldo_qty_medium != null) parts.push(`${formatNumber(card.saldo_qty_medium)} ${card.medium_unit_name || ''}`);
  if (card.saldo_qty_large != null) parts.push(`${formatNumber(card.saldo_qty_large)} ${card.large_unit_name || ''}`);
  return parts.length > 0 ? parts.join(' | ') : '-';
}

function formatReference(card) {
  if (!card.reference_type) return '-';
  const labels = {
    'asset_good_receive': 'Good Receive',
    'asset_inventory_transfer': 'Transfer',
    'asset_stock_adjustment': 'Stock Adjustment',
    'asset_service_order': 'Service Order',
    'asset_disposal': 'Disposal',
  };
  return labels[card.reference_type] || card.reference_type;
}

function refBadgeClass(refType) {
  const map = {
    'asset_good_receive': 'bg-green-100 text-green-800',
    'asset_inventory_transfer': 'bg-blue-100 text-blue-800',
    'asset_stock_adjustment': 'bg-yellow-100 text-yellow-800',
    'asset_service_order': 'bg-purple-100 text-purple-800',
    'asset_disposal': 'bg-red-100 text-red-800',
  };
  return map[refType] || 'bg-gray-100 text-gray-800';
}

function getCurrentMonthFirstDate() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toLocaleDateString('id-ID');
}
</script>
