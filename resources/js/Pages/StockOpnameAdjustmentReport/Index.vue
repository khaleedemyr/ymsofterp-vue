<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-chart-bar text-blue-500"></i> Stock Opname Adjustment Report
        </h1>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select 
              v-model="filters.outlet_id" 
              @change="loadWarehouseOutlets"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse Outlet</label>
            <select 
              v-model="filters.warehouse_outlet_id" 
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              :disabled="!filters.outlet_id"
            >
              <option value="">Pilih Warehouse</option>
              <option v-for="warehouse in warehouseOutlets" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Dari</label>
            <input 
              type="date" 
              v-model="filters.date_from" 
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Sampai</label>
            <input 
              type="date" 
              v-model="filters.date_to" 
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div class="flex items-end">
            <button 
              @click="loadReport" 
              :disabled="loading"
              class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-1"></i>
              <i v-else class="fa-solid fa-search mr-1"></i> 
              {{ loading ? 'Loading...' : 'Load Data' }}
            </button>
          </div>
        </div>
        
        <!-- Search and Per Page -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
            <input 
              type="text" 
              v-model="filters.search" 
              @input="debouncedSearch"
              placeholder="Cari berdasarkan nomor opname, nama item, atau reason..."
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Halaman</label>
            <select 
              v-model="filters.per_page" 
              @change="loadReport"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="200">200</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading Spinner -->
      <div v-if="loading" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-blue-500">
          <i class="fa-solid fa-spinner fa-spin text-4xl mb-4"></i>
          <p class="text-lg font-medium">Memuat data...</p>
        </div>
      </div>

      <!-- Report Table -->
      <div v-else-if="adjustments.length > 0" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-6 border-b">
          <div class="bg-blue-50 p-4 rounded-lg">
            <div class="text-sm text-gray-600 mb-1">Total Selisih Qty Small</div>
            <div class="text-2xl font-bold text-blue-600">{{ formatNumber(totals.total_qty_diff_small) }}</div>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <div class="text-sm text-gray-600 mb-1">Total Selisih Qty Medium</div>
            <div class="text-2xl font-bold text-green-600">{{ formatNumber(totals.total_qty_diff_medium) }}</div>
          </div>
          <div class="bg-purple-50 p-4 rounded-lg">
            <div class="text-sm text-gray-600 mb-1">Total Selisih Qty Large</div>
            <div class="text-2xl font-bold text-purple-600">{{ formatNumber(totals.total_qty_diff_large) }}</div>
          </div>
          <div class="bg-orange-50 p-4 rounded-lg">
            <div class="text-sm text-gray-600 mb-1">Total Value Adjustment</div>
            <div class="text-2xl font-bold text-orange-600">{{ formatCurrency(totals.total_value_adjustment) }}</div>
          </div>
        </div>

        <!-- Table -->
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Process</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Opname</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Code</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selisih Small</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selisih Medium</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selisih Large</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Before</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC After</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value Adjustment</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed By</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(adj, index) in adjustments" :key="adj.id" class="hover:bg-gray-50">
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ (pagination.current_page - 1) * pagination.per_page + index + 1 }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatDateTime(adj.processed_at) }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ adj.opname_number }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ adj.outlet }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ adj.warehouse_outlet }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ adj.item_code }}
              </td>
              <td class="px-4 py-4 text-sm text-gray-900">
                {{ adj.item_name }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ adj.category_name }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold" :class="getDiffClass(adj.qty_diff_small)">
                {{ formatNumberWithSign(adj.qty_diff_small) }} {{ adj.small_unit_name }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold" :class="getDiffClass(adj.qty_diff_medium)">
                {{ formatNumberWithSign(adj.qty_diff_medium) }} {{ adj.medium_unit_name }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold" :class="getDiffClass(adj.qty_diff_large)">
                {{ formatNumberWithSign(adj.qty_diff_large) }} {{ adj.large_unit_name }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatCurrency(adj.mac_before) }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatCurrency(adj.mac_after) }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold" :class="getValueClass(adj.value_adjustment)">
                {{ formatCurrencyWithSign(adj.value_adjustment) }}
              </td>
              <td class="px-4 py-4 text-sm text-gray-900 max-w-xs truncate" :title="adj.reason">
                {{ adj.reason || '-' }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ adj.processed_by }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 p-6 border-t">
          <div class="text-sm text-gray-700">
            Menampilkan <span class="font-semibold text-gray-900">{{ ((pagination.current_page - 1) * pagination.per_page) + 1 }}</span> - 
            <span class="font-semibold text-gray-900">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total_items) }}</span> dari 
            <span class="font-semibold text-gray-900">{{ pagination.total_items }}</span> data
          </div>
          <div class="flex gap-2">
            <button 
              v-if="pagination.current_page > 1"
              @click="goToPage(pagination.current_page - 1)"
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition"
            >
              <i class="fa-solid fa-chevron-left mr-1"></i> Sebelumnya
            </button>
            <button 
              v-if="pagination.current_page < pagination.total_pages"
              @click="goToPage(pagination.current_page + 1)"
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition"
            >
              Selanjutnya <i class="fa-solid fa-chevron-right ml-1"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-white rounded-xl shadow-xl p-12 text-center">
        <i class="fa-solid fa-inbox text-gray-400 text-6xl mb-4"></i>
        <p class="text-gray-500 text-lg font-medium">Tidak ada data adjustment</p>
        <p class="text-gray-400 text-sm mt-2">Pilih filter dan klik "Load Data" untuk melihat data</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash';

const props = defineProps({
  adjustments: {
    type: Array,
    default: () => []
  },
  outlets: {
    type: Array,
    default: () => []
  },
  warehouseOutlets: {
    type: Array,
    default: () => []
  },
  totals: {
    type: Object,
    default: () => ({
      total_qty_diff_small: 0,
      total_qty_diff_medium: 0,
      total_qty_diff_large: 0,
      total_value_adjustment: 0
    })
  },
  pagination: {
    type: Object,
    default: () => ({
      current_page: 1,
      per_page: 50,
      total_items: 0,
      total_pages: 0
    })
  },
  filters: {
    type: Object,
    default: () => ({
      outlet_id: '',
      warehouse_outlet_id: '',
      date_from: new Date().toISOString().split('T')[0].substring(0, 7) + '-01',
      date_to: new Date().toISOString().split('T')[0].substring(0, 7) + '-' + new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).getDate(),
      search: '',
      per_page: 50,
      page: 1
    })
  },
  user_outlet_id: {
    type: Number,
    default: null
  }
});

const loading = ref(false);
const outlets = ref(props.outlets || []);
const warehouseOutlets = ref(props.warehouseOutlets || []);
const adjustments = ref(props.adjustments || []);
const totals = ref(props.totals || {});
const pagination = ref(props.pagination || {});
const filters = ref({ ...props.filters });

// Set default dates to current month
const now = new Date();
const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];

if (!filters.value.date_from) {
  filters.value.date_from = firstDay;
}
if (!filters.value.date_to) {
  filters.value.date_to = lastDay;
}

function loadWarehouseOutlets() {
  if (!filters.value.outlet_id) {
    warehouseOutlets.value = [];
    filters.value.warehouse_outlet_id = '';
    return;
  }

  // Reload page to get updated warehouse outlets
  loadReport();
}

function loadReport() {
  loading.value = true;
  filters.value.page = 1; // Reset to first page
  
  router.get(route('stock-opname-adjustment-report.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

function goToPage(page) {
  filters.value.page = page;
  loadReport();
}

function formatNumber(value) {
  if (value === null || value === undefined) return '0';
  return parseFloat(value).toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function formatCurrency(value) {
  if (value === null || value === undefined) return 'Rp 0';
  return 'Rp ' + parseFloat(value).toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function formatDateTime(value) {
  if (!value) return '-';
  const date = new Date(value);
  return date.toLocaleString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatNumberWithSign(value) {
  if (value === null || value === undefined) return '0';
  const num = parseFloat(value);
  const formatted = num.toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
  if (num > 0) return '+' + formatted;
  return formatted; // Negative already has minus sign
}

function formatCurrencyWithSign(value) {
  if (value === null || value === undefined) return 'Rp 0';
  const num = parseFloat(value);
  const formatted = 'Rp ' + num.toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
  if (num > 0) return '+' + formatted;
  return formatted; // Negative already has minus sign
}

function getDiffClass(value) {
  if (value > 0) return 'text-green-600 bg-green-50';
  if (value < 0) return 'text-red-600 bg-red-50';
  return 'text-gray-900';
}

function getValueClass(value) {
  if (value > 0) return 'text-green-600 bg-green-50';
  if (value < 0) return 'text-red-600 bg-red-50';
  return 'text-gray-900';
}

const debouncedSearch = debounce(() => {
  loadReport();
}, 500);

// Watch for props changes
watch(() => props.adjustments, (newVal) => {
  adjustments.value = newVal || [];
}, { immediate: true });

watch(() => props.totals, (newVal) => {
  totals.value = newVal || {};
}, { immediate: true });

watch(() => props.pagination, (newVal) => {
  pagination.value = newVal || {};
}, { immediate: true });

watch(() => props.warehouseOutlets, (newVal) => {
  warehouseOutlets.value = newVal || [];
}, { immediate: true });

watch(() => props.filters, (newVal) => {
  filters.value = { ...newVal };
}, { immediate: true });

onMounted(() => {
  // Initialize data from props
  adjustments.value = props.adjustments || [];
  totals.value = props.totals || {};
  pagination.value = props.pagination || {};
  warehouseOutlets.value = props.warehouseOutlets || [];
  filters.value = { ...props.filters };
});
</script>

