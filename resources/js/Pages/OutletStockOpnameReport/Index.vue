<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-file-lines text-blue-500"></i> Outlet Stock Opname Report
        </h1>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select 
              v-model="filters.outlet_id" 
              @change="loadWarehouseOutlets"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              :disabled="!outletSelectable"
              :class="{ 'bg-gray-100 cursor-not-allowed': !outletSelectable }"
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
            <p v-if="!outletSelectable && filters.outlet_id" class="text-xs text-gray-500 mt-1">
              <i class="fa-solid fa-info-circle mr-1"></i>
              Outlet: {{ getOutletName(filters.outlet_id) }}
            </p>
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
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal</label>
            <input 
              type="date" 
              v-model="filters.date" 
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
              placeholder="Cari berdasarkan nomor opname, nama item, atau notes..."
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
      <div v-else-if="stockOpnames.length > 0" class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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

        <!-- Stock Opnames List -->
        <div v-for="opname in stockOpnames" :key="opname.id" class="bg-white rounded-xl shadow-xl overflow-hidden">
          <!-- Header -->
          <div class="bg-gray-50 px-6 py-4 border-b">
            <div class="flex justify-between items-start">
              <div>
                <h3 class="text-lg font-bold text-gray-900">{{ opname.opname_number }}</h3>
                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                  <div>
                    <span class="text-gray-600">Tanggal:</span>
                    <span class="ml-2 font-medium">{{ opname.opname_date }}</span>
                  </div>
                  <div>
                    <span class="text-gray-600">Outlet:</span>
                    <span class="ml-2 font-medium">{{ opname.outlet }}</span>
                  </div>
                  <div>
                    <span class="text-gray-600">Warehouse:</span>
                    <span class="ml-2 font-medium">{{ opname.warehouse_outlet }}</span>
                  </div>
                  <div>
                    <span class="text-gray-600">Items:</span>
                    <span class="ml-2 font-medium">{{ opname.items_count }}</span>
                  </div>
                </div>
                <div v-if="opname.notes" class="mt-2 text-sm text-gray-600">
                  <span class="font-medium">Notes:</span> {{ opname.notes }}
                </div>
                <div class="mt-2 text-sm text-gray-600">
                  <span class="font-medium">Created By:</span> {{ opname.created_by }} 
                  <span class="ml-4 font-medium">Created At:</span> {{ formatDateTime(opname.created_at) }}
                </div>
                <div v-if="opname.approvers && opname.approvers.length > 0" class="mt-2">
                  <span class="text-sm font-medium text-gray-600">Approvers:</span>
                  <div class="mt-1 flex flex-wrap gap-2">
                    <span 
                      v-for="approver in opname.approvers" 
                      :key="approver.level"
                      class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                      :class="getApproverStatusClass(approver.status)"
                    >
                      Level {{ approver.level }}: {{ approver.name }} 
                      <span v-if="approver.approved_at" class="ml-1">({{ formatDate(approver.approved_at) }})</span>
                    </span>
                  </div>
                </div>
              </div>
              <div class="text-right">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="getStatusClass(opname.status)">
                  {{ opname.status }}
                </span>
                <div class="mt-2 text-sm font-bold text-blue-600">
                  Total Adjustment: {{ formatCurrency(opname.total_value_adjustment) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Code</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty System</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Physical</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Selisih</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">MAC</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Value Adjustment</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(item, index) in opname.items" :key="item.id" class="hover:bg-gray-50">
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ index + 1 }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ item.item_code }}
                  </td>
                  <td class="px-4 py-4 text-sm text-gray-900">
                    {{ item.item_name }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ item.category_name }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                    <div>S: {{ formatNumber(item.qty_system_small) }} {{ item.small_unit_name }}</div>
                    <div>M: {{ formatNumber(item.qty_system_medium) }} {{ item.medium_unit_name }}</div>
                    <div>L: {{ formatNumber(item.qty_system_large) }} {{ item.large_unit_name }}</div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                    <div>S: {{ formatNumber(item.qty_physical_small) }} {{ item.small_unit_name }}</div>
                    <div>M: {{ formatNumber(item.qty_physical_medium) }} {{ item.medium_unit_name }}</div>
                    <div>L: {{ formatNumber(item.qty_physical_large) }} {{ item.large_unit_name }}</div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                    <div :class="getDiffClass(item.qty_diff_small)">
                      S: {{ formatNumberWithSign(item.qty_diff_small) }} {{ item.small_unit_name }}
                    </div>
                    <div :class="getDiffClass(item.qty_diff_medium)">
                      M: {{ formatNumberWithSign(item.qty_diff_medium) }} {{ item.medium_unit_name }}
                    </div>
                    <div :class="getDiffClass(item.qty_diff_large)">
                      L: {{ formatNumberWithSign(item.qty_diff_large) }} {{ item.large_unit_name }}
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                    {{ formatCurrency(item.mac_before) }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-right" :class="getValueClass(item.value_adjustment)">
                    {{ formatCurrencyWithSign(item.value_adjustment) }}
                  </td>
                  <td class="px-4 py-4 text-sm text-gray-900 max-w-xs truncate" :title="item.reason">
                    {{ item.reason || '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination -->
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 p-6 bg-white rounded-xl shadow-xl border-t">
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
        <p class="text-gray-500 text-lg font-medium">Tidak ada data stock opname</p>
        <p class="text-gray-400 text-sm mt-2">Pilih filter dan klik "Load Data" untuk melihat data</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  stockOpnames: {
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
      date: '',
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
const stockOpnames = ref(props.stockOpnames || []);
const totals = ref(props.totals || {});
const pagination = ref(props.pagination || {});
const filters = ref({ ...props.filters });

// Check if user can select outlet (only superadmin with id_outlet = 1)
const outletSelectable = computed(() => {
  return props.user_outlet_id && String(props.user_outlet_id) === '1';
});

function getOutletName(outletId) {
  const outlet = outlets.value.find(o => String(o.id) === String(outletId));
  return outlet ? outlet.name : '';
}

function loadWarehouseOutlets() {
  if (!filters.value.outlet_id) {
    warehouseOutlets.value = [];
    filters.value.warehouse_outlet_id = '';
    return;
  }

  // Reload page to get updated warehouse outlets (but don't load data yet)
  router.get(route('outlet-stock-opname-report.index'), {
    ...filters.value,
    outlet_id: filters.value.outlet_id,
    load: false
  }, {
    preserveState: true,
    preserveScroll: true,
    only: ['warehouseOutlets', 'filters']
  });
}

function loadReport() {
  loading.value = true;
  filters.value.page = 1; // Reset to first page
  
  // Add load=true parameter to explicitly request data
  const params = {
    ...filters.value,
    load: true
  };
  
  router.get(route('outlet-stock-opname-report.index'), params, {
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

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return date.toLocaleDateString('id-ID');
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
  if (value > 0) return 'text-green-600 font-semibold';
  if (value < 0) return 'text-red-600 font-semibold';
  return 'text-gray-900';
}

function getValueClass(value) {
  if (value > 0) return 'text-green-600';
  if (value < 0) return 'text-red-600';
  return 'text-gray-900';
}

function getStatusClass(status) {
  const classes = {
    DRAFT: 'bg-gray-100 text-gray-700 border border-gray-300',
    SUBMITTED: 'bg-yellow-100 text-yellow-700 border border-yellow-300',
    APPROVED: 'bg-green-100 text-green-700 border border-green-300',
    REJECTED: 'bg-red-100 text-red-700 border border-red-300',
    COMPLETED: 'bg-blue-100 text-blue-700 border border-blue-300',
  };
  return classes[status] || 'bg-gray-100 text-gray-700 border border-gray-300';
}

function getApproverStatusClass(status) {
  if (status === 'APPROVED') return 'bg-green-100 text-green-800';
  if (status === 'REJECTED') return 'bg-red-100 text-red-800';
  return 'bg-yellow-100 text-yellow-800';
}

// Search will only work when user clicks "Load Data" button
// No automatic search on input

// Watch for props changes
watch(() => props.stockOpnames, (newVal) => {
  stockOpnames.value = newVal || [];
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
  // Ensure outlet_id is set for non-superadmin users
  if (props.user_outlet_id && String(props.user_outlet_id) !== '1' && !filters.value.outlet_id) {
    filters.value.outlet_id = String(props.user_outlet_id);
  }
}, { immediate: true });

onMounted(() => {
  // Initialize data from props
  stockOpnames.value = props.stockOpnames || [];
  totals.value = props.totals || {};
  pagination.value = props.pagination || {};
  warehouseOutlets.value = props.warehouseOutlets || [];
  filters.value = { ...props.filters };
  
  // Outlet_id is already set by controller for non-superadmin users
  // No need to load warehouse outlets automatically - user must click "Load Data"
});
</script>

