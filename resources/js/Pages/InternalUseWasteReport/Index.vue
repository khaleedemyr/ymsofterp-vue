<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-blue-500"></i> Report RnD, BM, WM
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
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
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
              <option value="all">Semua Warehouse</option>
              <option v-for="warehouse in warehouseOutlets" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
            <input 
              type="date" 
              v-model="filters.start_date" 
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
            <input 
              type="date" 
              v-model="filters.end_date" 
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
        
        <!-- Search and Per Page -->
        <div v-if="filters.outlet_id && filters.start_date && filters.end_date" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Cari (Nama Item / Type)</label>
            <input 
              type="text" 
              v-model="filters.search" 
              @input="debouncedSearch"
              placeholder="Cari berdasarkan nama item atau type..."
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
              <option :value="500">500</option>
            </select>
          </div>
        </div>
        
        <div class="mt-4 flex gap-2">
          <button 
            @click="loadReport" 
            :disabled="loading"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-1"></i>
            <i v-else class="fa-solid fa-search mr-1"></i> 
            {{ loading ? 'Loading...' : 'Load Data' }}
          </button>
          <button 
            @click="showSummary" 
            :disabled="loading || !canExport"
            class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i class="fa-solid fa-chart-pie mr-1"></i> 
            Summary
          </button>
          <button 
            @click="exportToExcel" 
            :disabled="loading || exporting || !canExport"
            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i v-if="exporting" class="fa-solid fa-spinner fa-spin mr-1"></i>
            <i v-else class="fa-solid fa-file-excel mr-1"></i> 
            {{ exporting ? 'Exporting...' : 'Export Excel' }}
          </button>
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
      <div v-if="!loading && reportData && reportData.length > 0" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse Outlet</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Item</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty (Konversi)</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Per Unit</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty x MAC</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approver</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(item, index) in reportData" :key="index" class="hover:bg-gray-50">
              <td class="px-4 py-4 text-sm text-gray-900">{{ getItemNumber(index) }}</td>
              <td class="px-4 py-4 text-sm text-gray-900">{{ formatDate(item.date) }}</td>
              <td class="px-4 py-4 text-sm text-gray-900">
                <div class="flex flex-col gap-1">
                  <span class="px-2 py-1 rounded text-xs font-medium"
                    :class="{
                      'bg-purple-100 text-purple-800': item.type_code === 'r_and_d',
                      'bg-blue-100 text-blue-800': item.type_code === 'marketing',
                      'bg-red-100 text-red-800': item.type_code === 'wrong_maker'
                    }">
                    {{ item.type }}
                  </span>
                  <a 
                    :href="route('outlet-internal-use-waste.show', item.header_id)" 
                    target="_blank"
                    class="text-xs text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                    title="Klik untuk melihat detail transaksi"
                  >
                    {{ item.header_number }}
                  </a>
                </div>
              </td>
              <td class="px-4 py-4 text-sm text-gray-900">
                {{ item.warehouse_outlet_name || '-' }}
              </td>
              <td class="px-4 py-4 text-sm text-gray-900">
                <div class="font-medium">{{ item.item_name }}</div>
                <div class="text-xs text-gray-500">{{ item.item_code }}</div>
              </td>
              <td class="px-4 py-4 text-sm text-gray-900">
                <div v-if="Array.isArray(item.qty)" class="flex flex-col">
                  <div v-for="(q, idx) in item.qty" :key="idx">{{ q }}</div>
                </div>
                <div v-else>{{ item.qty || '0' }}</div>
              </td>
              <td class="px-4 py-4 text-sm text-gray-900 font-medium">
                {{ formatNumber(item.mac_per_unit) }}
              </td>
              <td class="px-4 py-4 text-sm text-gray-900 font-bold">
                {{ formatNumber(item.subtotal_mac) }}
              </td>
              <td class="px-4 py-4 text-sm text-gray-900">
                <div v-if="Array.isArray(item.approvers)" class="flex flex-col">
                  <div v-for="(approver, idx) in item.approvers" :key="idx" class="text-xs">
                    {{ approver }}
                  </div>
                </div>
                <div v-else>{{ item.approvers || '-' }}</div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination && pagination.total_pages > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Menampilkan {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} - 
              {{ Math.min(pagination.current_page * pagination.per_page, pagination.total_items) }} 
              dari {{ pagination.total_items }} item
            </div>
            <div class="flex items-center gap-2">
              <button 
                @click="goToPage(pagination.current_page - 1)"
                :disabled="pagination.current_page <= 1"
                class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <span class="text-sm text-gray-700">
                Halaman {{ pagination.current_page }} dari {{ pagination.total_pages }}
              </span>
              <button 
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.total_pages"
                class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading && filters.outlet_id && filters.start_date && filters.end_date" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-gray-500">
          <i class="fa-solid fa-inbox text-4xl mb-4"></i>
          <p class="text-lg font-medium">Tidak ada data</p>
          <p class="text-sm">Tidak ada data untuk filter yang dipilih</p>
        </div>
      </div>

      <!-- Initial State -->
      <div v-else-if="!loading" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-gray-500">
          <i class="fa-solid fa-filter text-4xl mb-4"></i>
          <p class="text-lg font-medium">Pilih Filter</p>
          <p class="text-sm">Silakan pilih outlet, periode tanggal, dan warehouse untuk melihat report</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  reportData: {
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
  pagination: {
    type: Object,
    default: () => null
  },
  filters: {
    type: Object,
    default: () => ({
      outlet_id: '',
      warehouse_outlet_id: '',
      start_date: '',
      end_date: '',
      search: '',
      per_page: 50,
      page: 1
    })
  }
});

const filters = ref({
  outlet_id: props.filters.outlet_id || '',
  warehouse_outlet_id: props.filters.warehouse_outlet_id || '',
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
  search: props.filters.search || '',
  per_page: props.filters.per_page || 50,
  page: props.filters.page || 1
});

const outlets = ref(props.outlets || []);
const warehouseOutlets = ref(props.warehouseOutlets || []);
const reportData = ref(props.reportData || []);
const pagination = ref(props.pagination);
const loading = ref(false);
const exporting = ref(false);
const searchTimeout = ref(null);

watch(() => props.warehouseOutlets, (newVal) => {
  warehouseOutlets.value = newVal || [];
}, { immediate: true });

watch(() => props.reportData, (newVal) => {
  reportData.value = newVal || [];
}, { immediate: true });

watch(() => props.pagination, (newVal) => {
  pagination.value = newVal;
}, { immediate: true });

const loadWarehouseOutlets = () => {
  if (!filters.value.outlet_id) {
    warehouseOutlets.value = [];
    filters.value.warehouse_outlet_id = '';
    return;
  }
  
  loading.value = true;
  
  router.get(route('internal-use-waste-report.index'), {
    outlet_id: filters.value.outlet_id,
    warehouse_outlet_id: '',
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
    search: filters.value.search,
    per_page: filters.value.per_page,
    page: 1
  }, {
    preserveState: false,
    only: ['warehouseOutlets', 'filters'],
    onFinish: () => {
      loading.value = false;
    }
  });
};

const loadReport = () => {
  if (!filters.value.outlet_id || !filters.value.start_date || !filters.value.end_date) {
    return;
  }
  
  loading.value = true;
  filters.value.page = 1;
  
  router.get(route('internal-use-waste-report.index'), {
    outlet_id: filters.value.outlet_id,
    warehouse_outlet_id: filters.value.warehouse_outlet_id || '',
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
    search: filters.value.search,
    per_page: filters.value.per_page,
    page: filters.value.page
  }, {
    preserveState: false,
    onFinish: () => {
      loading.value = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
};

const debouncedSearch = () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }
  
  searchTimeout.value = setTimeout(() => {
    loadReport();
  }, 500);
};

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.total_pages) return;
  
  filters.value.page = page;
  loading.value = true;
  
  router.get(route('internal-use-waste-report.index'), {
    outlet_id: filters.value.outlet_id,
    warehouse_outlet_id: filters.value.warehouse_outlet_id || '',
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
    search: filters.value.search,
    per_page: filters.value.per_page,
    page: filters.value.page
  }, {
    preserveState: false,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
};

const getItemNumber = (index) => {
  if (!pagination.value) return index + 1;
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + index + 1;
};

const formatNumber = (value) => {
  if (!value && value !== 0) return '0';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value);
};

const formatDate = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  });
};

const formatDateTime = (dateTime) => {
  if (!dateTime) return '-';
  const d = new Date(dateTime);
  return d.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }) + ' ' + d.toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit'
  });
};

const canExport = computed(() => {
  return filters.value.outlet_id && filters.value.start_date && filters.value.end_date;
});

const showSummary = () => {
  if (!canExport.value) {
    return;
  }
  
  const params = new URLSearchParams({
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
  });
  
  window.open('/internal-use-waste-report/summary?' + params.toString(), '_blank');
};

const exportToExcel = () => {
  if (!canExport.value) {
    return;
  }

  exporting.value = true;

  const params = new URLSearchParams({
    outlet_id: filters.value.outlet_id,
    warehouse_outlet_id: filters.value.warehouse_outlet_id || '',
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
    search: filters.value.search || '',
  });

  const url = route('internal-use-waste-report.export') + '?' + params.toString();
  
  // Create a temporary link to trigger download
  const link = document.createElement('a');
  link.href = url;
  link.download = '';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  setTimeout(() => {
    exporting.value = false;
  }, 2000);
};

onMounted(() => {
  if (props.filters) {
    filters.value = { ...props.filters };
  }
});
</script>

