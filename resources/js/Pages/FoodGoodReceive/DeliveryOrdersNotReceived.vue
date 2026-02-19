<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-boxes-stacked text-red-500"></i> Delivery Orders Not Received by Outlet
        </h1>
      </div>

      <!-- Info Box -->
      <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <p class="text-sm text-yellow-700">
          <i class="fa-solid fa-info-circle mr-2"></i>
          This report shows Delivery Orders (DO) that have been created but not yet received (Good Receive) by the outlet.
        </p>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden mb-6">
        <div class="p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Report</h3>
          <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Date Range -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
              <input
                type="date"
                v-model="filters.from_date"
                class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
              <input
                type="date"
                v-model="filters.to_date"
                class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
              />
            </div>

            <!-- Outlet Filter -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
              <select
                v-model="filters.outlet_id"
                class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
              >
                <option value="">All Outlets</option>
                <option
                  v-for="outlet in (props.outlets || [])"
                  :key="outlet.id"
                  :value="outlet.id"
                >
                  {{ outlet.name }}
                </option>
              </select>
            </div>

            <!-- Warehouse Outlet Filter -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse Outlet</label>
              <select
                v-model="filters.warehouse_outlet_id"
                class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
              >
                <option value="">All Warehouse Outlets</option>
                <option
                  v-for="wo in filteredWarehouseOutlets"
                  :key="wo.id"
                  :value="wo.id"
                >
                  {{ wo.name }}
                </option>
              </select>
              <small v-if="filters.outlet_id && filteredWarehouseOutlets.length === 0" class="text-gray-500 text-xs">
                No warehouse outlets for this outlet
              </small>
            </div>

            <!-- Minimum Days Not Received -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Min Days Not Received</label>
              <input
                type="number"
                v-model="filters.min_days"
                placeholder="e.g., 7"
                min="0"
                class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
              />
            </div>

            <!-- Search -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
              <input
                type="text"
                v-model="filters.search"
                placeholder="Search DO Number, Outlet, Warehouse Outlet..."
                class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
              />
            </div>

            <!-- Per Page -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Items per Page</label>
              <select
                v-model="filters.per_page"
                class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
              >
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
              </select>
            </div>

            <!-- Action Buttons -->
            <div class="md:col-span-4 flex gap-2">
              <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <i class="fa fa-search mr-2"></i> Apply Filters
              </button>
              <button
                type="button"
                @click="clearFilters"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500"
              >
                <i class="fa fa-times mr-2"></i> Clear
              </button>
              <button
                type="button"
                @click="exportReport"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
              >
                <i class="fa fa-download mr-2"></i> Export Excel
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
          <div class="p-4">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center">
                  <i class="fa-solid fa-box text-white text-lg"></i>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total DO Not Received</p>
                <p class="text-2xl font-bold text-gray-900">{{ summary.total_do_not_received || 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
          <div class="p-4">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center">
                  <i class="fa-solid fa-hourglass-start text-white text-lg"></i>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Min Days Pending</p>
                <p class="text-2xl font-bold text-gray-900">{{ summary.min_days || 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
          <div class="p-4">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
                  <i class="fa-solid fa-fire text-white text-lg"></i>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Max Days Pending</p>
                <p class="text-2xl font-bold text-gray-900">{{ summary.max_days || 0 }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Results Table -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-100 border-b-2 border-gray-200">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">DO Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">DO Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Division</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Days Not Received</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">FO Mode</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Created By</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-if="results.data && results.data.length === 0" class="hover:bg-gray-50">
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                  No delivery orders found.
                </td>
              </tr>
              <tr v-for="item in results.data" :key="item.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="font-medium text-blue-600">{{ item.do_number }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ formatDate(item.do_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ item.outlet_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ item.division_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="[
                      'px-3 py-1 rounded-full text-sm font-medium',
                      item.days_not_received >= 14 ? 'bg-red-100 text-red-800' :
                      item.days_not_received >= 7 ? 'bg-orange-100 text-orange-800' :
                      'bg-yellow-100 text-yellow-800'
                    ]"
                  >
                    {{ item.days_not_received }} days
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ item.warehouse_outlet_name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="getFOModeClass(item.fo_mode)"
                  >
                    {{ item.fo_mode || '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ item.created_by || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="results.data && results.data.length > 0" class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
          <div class="text-sm text-gray-600">
            Showing <span class="font-medium">{{ results.from }}</span> to
            <span class="font-medium">{{ results.to }}</span> of
            <span class="font-medium">{{ results.total }}</span> results
          </div>
          <div class="flex gap-2">
            <template v-for="link in results.links" :key="link.label">
              <Link
                v-if="link.url"
                :href="link.url"
                :class="[
                  'px-3 py-2 text-sm font-medium rounded-md',
                  link.active ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-100'
                ]"
                v-html="link.label"
              />
              <span
                v-else
                :class="[
                  'px-3 py-2 text-sm font-medium rounded-md text-gray-400 bg-gray-100',
                ]"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  results: Object,
  summary: Object,
  outlets: {
    type: Array,
    default: () => []
  },
  warehouse_outlets: {
    type: Array,
    default: () => []
  },
  filters: Object,
});

const filters = reactive({
  from_date: props.filters?.from_date || '',
  to_date: props.filters?.to_date || '',
  outlet_id: props.filters?.outlet_id || '',
  warehouse_outlet_id: props.filters?.warehouse_outlet_id || '',
  min_days: props.filters?.min_days || '',
  search: props.filters?.search || '',
  per_page: props.filters?.per_page || 20,
});

// Computed property untuk filter warehouse outlets berdasarkan outlet yang dipilih
const filteredWarehouseOutlets = computed(() => {
  let warehouseOutlets = props.warehouse_outlets || [];
  
  // Jika outlet dipilih, filter warehouse outlets berdasarkan outlet tersebut
  if (filters.outlet_id) {
    warehouseOutlets = warehouseOutlets.filter(wo => String(wo.outlet_id) === String(filters.outlet_id));
  }
  
  return warehouseOutlets;
});

// Reset warehouse outlet selection ketika outlet berubah
const resetWarehouseOutletOnOutletChange = (newOutlet) => {
  if (filters.outlet_id !== newOutlet) {
    filters.warehouse_outlet_id = '';
  }
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const getFOModeClass = (foMode) => {
  const baseClass = 'px-3 py-1 rounded-full text-sm font-medium';
  const modeClassMap = {
    'RO': 'bg-purple-100 text-purple-800',
    'Regular': 'bg-blue-100 text-blue-800',
    'Express': 'bg-green-100 text-green-800',
    'Urgent': 'bg-red-100 text-red-800'
  };
  const modeClass = modeClassMap[foMode] || 'bg-gray-100 text-gray-800';
  return `${baseClass} ${modeClass}`;
};

const applyFilters = () => {
  router.get(route('delivery-orders-not-received.report'), {
    from_date: filters.from_date,
    to_date: filters.to_date,
    outlet_id: filters.outlet_id,
    warehouse_outlet_id: filters.warehouse_outlet_id,
    min_days: filters.min_days,
    search: filters.search,
    per_page: filters.per_page
  }, {
    preserveScroll: true
  });
};

const clearFilters = () => {
  filters.from_date = '';
  filters.to_date = '';
  filters.outlet_id = '';
  filters.warehouse_outlet_id = '';
  filters.min_days = '';
  filters.search = '';
  filters.per_page = 20;
  router.get(route('delivery-orders-not-received.report'), {}, {
    preserveScroll: true
  });
};

const exportReport = () => {
  router.get(route('delivery-orders-not-received.export'), {
    from_date: filters.from_date,
    to_date: filters.to_date,
    outlet_id: filters.outlet_id,
    warehouse_outlet_id: filters.warehouse_outlet_id,
    min_days: filters.min_days,
    search: filters.search
  });
};

// Watch outlet_id changes and reset warehouse_outlet_id
watch(() => filters.outlet_id, (newValue, oldValue) => {
  if (newValue !== oldValue) {
    filters.warehouse_outlet_id = '';
  }
});
</script>
