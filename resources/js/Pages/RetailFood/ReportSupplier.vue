<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-blue-500"></i> Report Retail Food per Supplier
        </h1>
        <div class="flex gap-2">
          <button 
            @click="exportToExcel" 
            class="bg-green-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
            :disabled="exporting"
          >
            <i class="fa-solid fa-file-excel mr-2"></i>
            <span v-if="!exporting">Export Excel</span>
            <span v-else>Exporting...</span>
          </button>
          <button 
            @click="goBack" 
            class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa-solid fa-arrow-left mr-2"></i>Kembali
          </button>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-filter text-blue-500"></i> Filter Data
        </h3>
        
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cari (Semua Kolom)</label>
            <input 
              type="text" 
              v-model="filters.search" 
              placeholder="Supplier, outlet, item, no. transaksi..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
            <input 
              type="date" 
              v-model="filters.date_from" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
            <input 
              type="date" 
              v-model="filters.date_to" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Action Buttons -->
          <div class="flex items-end gap-2">
            <button 
              type="submit"
              class="flex-1 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors font-semibold"
            >
              <i class="fa-solid fa-search mr-2"></i>Filter
            </button>
            <button 
              type="button"
              @click="resetFilters"
              class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors"
            >
              <i class="fa-solid fa-refresh"></i>
            </button>
          </div>
        </form>
      </div>

      <!-- Summary Card -->
      <div class="bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-xl shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold mb-2">Total Supplier</h3>
            <p class="text-3xl font-bold">{{ suppliers.length }}</p>
          </div>
          <div class="text-right">
            <h3 class="text-lg font-semibold mb-2">Total Belanja</h3>
            <p class="text-3xl font-bold">Rp {{ formatCurrency(totalBelanja) }}</p>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        <p class="mt-4 text-gray-600">Memuat data...</p>
      </div>

      <!-- Supplier List -->
      <div v-else class="space-y-4">
        <div 
          v-for="supplier in filteredSuppliers" 
          :key="supplier.id"
          class="bg-white rounded-xl shadow-lg overflow-hidden"
        >
          <!-- Supplier Header -->
          <div 
            @click="toggleSupplier(supplier.id)"
            class="p-4 cursor-pointer hover:bg-gray-50 transition-colors border-b border-gray-200"
            :class="{ 'bg-blue-50': expandedSuppliers[supplier.id] }"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3 flex-1">
                <i 
                  class="fa-solid transition-transform duration-200"
                  :class="expandedSuppliers[supplier.id] ? 'fa-chevron-down' : 'fa-chevron-right'"
                ></i>
                <div>
                  <h3 class="text-lg font-semibold text-gray-800">
                    {{ supplier.name }}
                    <span v-if="supplier.code" class="text-sm text-gray-500">({{ supplier.code }})</span>
                  </h3>
                  <p class="text-sm text-gray-600">
                    {{ supplier.outlets.length }} Outlet • {{ getTotalTransactions(supplier) }} Transaksi
                  </p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-lg font-bold text-blue-600">Rp {{ formatCurrency(supplier.total_amount) }}</p>
              </div>
            </div>
          </div>

          <!-- Outlets (Expanded) -->
          <div v-if="expandedSuppliers[supplier.id]" class="bg-gray-50">
            <div 
              v-for="outlet in supplier.outlets" 
              :key="outlet.id"
              class="border-b border-gray-200 last:border-b-0"
            >
              <!-- Outlet Header -->
              <div 
                @click="toggleOutlet(supplier.id, outlet.id)"
                class="p-4 pl-12 cursor-pointer hover:bg-gray-100 transition-colors"
                :class="{ 'bg-gray-100': expandedOutlets[`${supplier.id}-${outlet.id}`] }"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3 flex-1">
                    <i 
                      class="fa-solid transition-transform duration-200 text-sm"
                      :class="expandedOutlets[`${supplier.id}-${outlet.id}`] ? 'fa-chevron-down' : 'fa-chevron-right'"
                    ></i>
                    <div>
                      <h4 class="font-semibold text-gray-700">{{ outlet.name }}</h4>
                      <p class="text-sm text-gray-600">{{ outlet.transactions.length }} Transaksi</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="font-bold text-green-600">Rp {{ formatCurrency(outlet.total_amount) }}</p>
                  </div>
                </div>
              </div>

              <!-- Transactions (Expanded) -->
              <div v-if="expandedOutlets[`${supplier.id}-${outlet.id}`]" class="bg-white">
                <div 
                  v-for="transaction in outlet.transactions" 
                  :key="transaction.id"
                  class="border-b border-gray-200 last:border-b-0"
                >
                  <!-- Transaction Header -->
                  <div 
                    @click="toggleTransaction(transaction.id)"
                    class="p-4 pl-20 cursor-pointer hover:bg-blue-50 transition-colors"
                    :class="{ 'bg-blue-50': expandedTransactions[transaction.id] }"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3 flex-1">
                        <i 
                          class="fa-solid transition-transform duration-200 text-sm"
                          :class="expandedTransactions[transaction.id] ? 'fa-chevron-down' : 'fa-chevron-right'"
                        ></i>
                        <div>
                          <h5 class="font-medium text-gray-800">{{ transaction.retail_number }}</h5>
                          <p class="text-sm text-gray-600">
                            {{ formatDate(transaction.transaction_date) }}
                            <span v-if="transaction.notes" class="ml-2">• {{ transaction.notes }}</span>
                          </p>
                        </div>
                      </div>
                      <div class="text-right">
                        <p class="font-bold text-purple-600">Rp {{ formatCurrency(transaction.total_amount) }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- Items (Expanded) -->
                  <div v-if="expandedTransactions[transaction.id]" class="bg-gray-50">
                    <div class="overflow-x-auto">
                      <table class="w-full">
                        <thead class="bg-gray-100">
                          <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Item</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Qty</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Harga</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                          <tr v-for="item in transaction.items" :key="item.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-800">{{ item.item_name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 text-right">
                              {{ formatNumber(item.qty) }} {{ item.unit }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600 text-right">
                              Rp {{ formatCurrency(item.price) }}
                            </td>
                            <td class="px-4 py-2 text-sm font-semibold text-gray-800 text-right">
                              Rp {{ formatCurrency(item.subtotal) }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="filteredSuppliers.length === 0" class="text-center py-12 bg-white rounded-xl shadow-lg">
          <i class="fa-solid fa-inbox text-4xl text-gray-400 mb-4"></i>
          <p class="text-gray-600">Tidak ada data ditemukan</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  suppliers: {
    type: Array,
    default: () => []
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      date_from: '',
      date_to: ''
    })
  },
  user: {
    type: Object,
    required: true
  }
});

const loading = ref(false);
const exporting = ref(false);
const expandedSuppliers = ref({});
const expandedOutlets = ref({});
const expandedTransactions = ref({});

// Function to get first and last day of current month
const getCurrentMonthRange = () => {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  
  // First day of current month
  const firstDay = new Date(year, month, 1);
  const dateFrom = firstDay.toISOString().split('T')[0];
  
  // Last day of current month
  const lastDay = new Date(year, month + 1, 0);
  const dateTo = lastDay.toISOString().split('T')[0];
  
  return { dateFrom, dateTo };
};

const { dateFrom: defaultDateFrom, dateTo: defaultDateTo } = getCurrentMonthRange();

const filters = ref({
  search: props.filters.search || '',
  date_from: props.filters.date_from || defaultDateFrom,
  date_to: props.filters.date_to || defaultDateTo
});

const totalBelanja = computed(() => {
  return props.suppliers.reduce((sum, supplier) => sum + (parseFloat(supplier.total_amount) || 0), 0);
});

const filteredSuppliers = computed(() => {
  if (!filters.value.search) {
    return props.suppliers;
  }
  
  const searchLower = filters.value.search.toLowerCase();
  return props.suppliers.filter(supplier => {
    // Search in supplier name/code
    if (supplier.name.toLowerCase().includes(searchLower) || 
        (supplier.code && supplier.code.toLowerCase().includes(searchLower))) {
      return true;
    }
    
    // Search in outlets
    return supplier.outlets.some(outlet => {
      // Search in outlet name
      if (outlet.name.toLowerCase().includes(searchLower)) {
        return true;
      }
      
      // Search in transactions
      return outlet.transactions.some(transaction => {
        // Search in transaction number/notes
        if (transaction.retail_number.toLowerCase().includes(searchLower) ||
            (transaction.notes && transaction.notes.toLowerCase().includes(searchLower))) {
          return true;
        }
        
        // Search in items
        return transaction.items.some(item => 
          item.item_name.toLowerCase().includes(searchLower)
        );
      });
    });
  });
});

const toggleSupplier = (supplierId) => {
  expandedSuppliers.value[supplierId] = !expandedSuppliers.value[supplierId];
};

const toggleOutlet = (supplierId, outletId) => {
  const key = `${supplierId}-${outletId}`;
  expandedOutlets.value[key] = !expandedOutlets.value[key];
};

const toggleTransaction = (transactionId) => {
  expandedTransactions.value[transactionId] = !expandedTransactions.value[transactionId];
};

const getTotalTransactions = (supplier) => {
  return supplier.outlets.reduce((sum, outlet) => sum + outlet.transactions.length, 0);
};

const applyFilters = () => {
  loading.value = true;
  router.get(route('retail-food.report-supplier'), filters.value, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
};

const resetFilters = () => {
  const { dateFrom, dateTo } = getCurrentMonthRange();
  filters.value = {
    search: '',
    date_from: dateFrom,
    date_to: dateTo
  };
  applyFilters();
};

const goBack = () => {
  router.visit(route('retail-food.index'));
};

const exportToExcel = () => {
  exporting.value = true;
  
  // Build query string from filters
  const params = new URLSearchParams();
  if (filters.value.search) params.append('search', filters.value.search);
  if (filters.value.date_from) params.append('date_from', filters.value.date_from);
  if (filters.value.date_to) params.append('date_to', filters.value.date_to);
  
  // Open export URL in new window
  const exportUrl = route('retail-food.report-supplier.export') + '?' + params.toString();
  window.open(exportUrl, '_blank');
  
  // Reset exporting state after a delay
  setTimeout(() => {
    exporting.value = false;
  }, 2000);
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID').format(value || 0);
};

const formatNumber = (value) => {
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value || 0);
};

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};

onMounted(() => {
  // Auto expand first supplier for better UX
  if (props.suppliers.length > 0) {
    expandedSuppliers.value[props.suppliers[0].id] = true;
  }
  
  // If no date filters from props, apply current month range and load data
  if (!props.filters.date_from || !props.filters.date_to) {
    const { dateFrom, dateTo } = getCurrentMonthRange();
    filters.value.date_from = dateFrom;
    filters.value.date_to = dateTo;
    // Auto load data with current month range
    applyFilters();
  }
});
</script>

<style scoped>
/* Add any custom styles here */
</style>

