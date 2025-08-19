<template>
  <AppLayout>
    <div class="py-12">
      <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
                         <div class="flex justify-between items-center mb-6">
               <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                 Report Item PO yang Sudah di-GR
               </h2>
               <div class="flex items-center gap-2">
                 <div v-if="loading" class="flex items-center text-blue-600">
                   <i class="fas fa-spinner fa-spin mr-2"></i>
                   <span class="text-sm">Loading...</span>
                 </div>
                 <button 
                   @click="exportReport"
                   class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                   :disabled="loading"
                 >
                   <i class="fas fa-download mr-2"></i> Export CSV
                 </button>
               </div>
             </div>

            <!-- Filters -->
            <div class="mb-6 bg-gray-50 p-4 rounded-lg">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                     <input 
                     v-model="filters.search" 
                     type="text" 
                     placeholder="Cari PO Number, GR Number, Supplier, Item..."
                     class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                     @input="debounceSearch"
                     :disabled="loading"
                   />
                </div>

                <!-- Date Range -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                                     <input 
                     v-model="filters.from" 
                     type="date" 
                     class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                     @change="applyFilters"
                     :disabled="loading"
                   />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                                     <input 
                     v-model="filters.to" 
                     type="date" 
                     class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                     @change="applyFilters"
                     :disabled="loading"
                   />
                </div>

                <!-- Per Page -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Tampilkan</label>
                                     <select 
                     v-model="filters.perPage" 
                     class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                     @change="applyFilters"
                     :disabled="loading"
                   >
                    <option value="15">15 data</option>
                    <option value="25">25 data</option>
                    <option value="50">50 data</option>
                    <option value="100">100 data</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <!-- Supplier Filter -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                                     <div :class="{ 'opacity-50 pointer-events-none': loading }">
                     <Multiselect
                       v-model="filters.supplier_id"
                       :options="suppliers"
                       :searchable="true"
                       :close-on-select="true"
                       :clear-on-select="false"
                       :preserve-search="true"
                       placeholder="Pilih atau cari supplier..."
                       track-by="id"
                       label="name"
                       :preselect-first="false"
                       @input="applyFilters"
                       class="w-full"
                     />
                   </div>
                </div>

                <!-- Item Filter -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
                                     <div :class="{ 'opacity-50 pointer-events-none': loading }">
                     <Multiselect
                       v-model="filters.item_id"
                       :options="items"
                       :searchable="true"
                       :close-on-select="true"
                       :clear-on-select="false"
                       :preserve-search="true"
                       placeholder="Pilih atau cari item..."
                       track-by="id"
                       label="name"
                       :preselect-first="false"
                       @input="applyFilters"
                       class="w-full"
                     />
                   </div>
                </div>
              </div>

              <!-- Clear Filters Button -->
              <div class="mt-4 flex justify-end">
                                 <button 
                   @click="clearFilters"
                   class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
                   :disabled="loading"
                 >
                   <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
                   Clear Filters
                 </button>
              </div>
            </div>

                         <!-- Table -->
             <div class="overflow-x-auto relative">
               <!-- Loading Overlay -->
               <div v-if="loading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
                 <div class="flex items-center text-blue-600">
                   <i class="fas fa-spinner fa-spin mr-2"></i>
                   <span>Loading data...</span>
                 </div>
               </div>
               
               <table class="min-w-full divide-y divide-gray-200">
                 <thead class="bg-gray-50">
                   <tr>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GR Number</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receive Date</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Date</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty PO</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Received</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Price</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Previous Price</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price Change</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Creator</th>
                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Received By</th>
                   </tr>
                 </thead>
                 <tbody class="bg-white divide-y divide-gray-200">
                   <tr v-for="(report, index) in (reports?.data || [])" :key="report.gr_id || index" class="hover:bg-gray-50">
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ report.gr_number || '-' }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(report.receive_date) }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 hover:text-blue-800">
                       <a v-if="report.po_id" :href="`/po-foods/${report.po_id}`" class="underline">{{ report.po_number || '-' }}</a>
                       <span v-else class="text-gray-500">{{ report.po_number || '-' }}</span>
                     </td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(report.po_date) }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ report.supplier_name || '-' }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ report.item_name || '-' }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatNumber(report.po_qty) }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatNumber(report.qty_received) }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ report.unit_name || '-' }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatRupiah(report.po_price) }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                       {{ report.previous_price && report.previous_price !== null ? formatRupiah(report.previous_price) : '-' }}
                     </td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm">
                       <span v-if="report.price_change !== 0 && report.price_change !== null" 
                             :class="report.price_change > 0 ? 'text-red-600' : 'text-green-600'">
                         {{ formatRupiah(report.price_change) }}
                         <span class="text-xs">({{ report.price_change_percentage || 0 }}%)</span>
                       </span>
                       <span v-else class="text-gray-500">-</span>
                     </td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ report.po_creator_name || '-' }}</td>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ report.received_by_name || '-' }}</td>
                   </tr>
                 </tbody>
               </table>
             </div>

                         <!-- Pagination -->
             <div v-if="(reports?.data || []).length > 0" class="mt-6 flex items-center justify-between">
               <div class="text-sm text-gray-700">
                 Menampilkan {{ reports?.from || 0 }} - {{ reports?.to || 0 }} dari {{ reports?.total || 0 }} data
               </div>
                                <div class="flex space-x-2">
                   <template v-for="(link, index) in (reports?.links || [])" :key="link.label || index">
                     <Link 
                       v-if="link.url && !loading"
                       :href="link.url"
                       :class="[
                         'px-3 py-2 text-sm rounded-md',
                         link.active 
                           ? 'bg-blue-600 text-white' 
                           : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                       ]"
                       v-html="link.label || ''"
                     />
                     <span 
                       v-else
                       :class="[
                         'px-3 py-2 text-sm rounded-md',
                         link.active 
                           ? 'bg-blue-600 text-white' 
                           : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                       ]"
                       v-html="link.label || ''"
                     />
                   </template>
                 </div>
             </div>

             <!-- No Data -->
             <div v-if="(reports?.data || []).length === 0" class="text-center py-8">
               <p class="text-gray-500">Tidak ada data yang ditemukan</p>
             </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  reports: {
    type: Object,
    default: () => ({ data: [], links: [] })
  },
  suppliers: {
    type: Array,
    default: () => []
  },
  items: {
    type: Array,
    default: () => []
  },
  filters: {
    type: Object,
    default: () => ({})
  }
});

const filters = ref({
  search: props.filters?.search || '',
  from: props.filters?.from || '',
  to: props.filters?.to || '',
  supplier_id: props.filters?.supplier_id ? props.suppliers?.find(s => s.id == props.filters.supplier_id) || null : null,
  item_id: props.filters?.item_id ? props.items?.find(i => i.id == props.filters.item_id) || null : null,
  perPage: props.filters?.perPage || 15
});

const loading = ref(false);
const isUpdatingFromProps = ref(false);

let searchTimeout;

// Watch for props changes to update filters
watch(() => props.filters, (newFilters) => {
  if (newFilters && !loading.value && !isUpdatingFromProps.value) {
    isUpdatingFromProps.value = true;
    filters.value = {
      search: newFilters.search || '',
      from: newFilters.from || '',
      to: newFilters.to || '',
      supplier_id: newFilters.supplier_id ? props.suppliers?.find(s => s.id == newFilters.supplier_id) || null : null,
      item_id: newFilters.item_id ? props.items?.find(i => i.id == newFilters.item_id) || null : null,
      perPage: newFilters.perPage || 15
    };
    isUpdatingFromProps.value = false;
  }
}, { deep: true, immediate: true });

const debounceSearch = () => {
  applyFilters();
};

const applyFilters = () => {
  if (isUpdatingFromProps.value) return;
  
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loading.value = true;
    
    const params = {
      search: filters.value.search || '',
      from: filters.value.from || '',
      to: filters.value.to || '',
      supplier_id: filters.value.supplier_id?.id || '',
      item_id: filters.value.item_id?.id || '',
      perPage: filters.value.perPage || 15
    };
    
    router.visit('/po-report', {
      data: params,
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onFinish: () => {
        loading.value = false;
        isUpdatingFromProps.value = false;
      }
    });
  }, 300);
};

const clearFilters = () => {
  clearTimeout(searchTimeout);
  loading.value = true;
  isUpdatingFromProps.value = true;
  
  filters.value = {
    search: '',
    from: '',
    to: '',
    supplier_id: null,
    item_id: null,
    perPage: 15
  };
  
  router.visit('/po-report', {
    data: {
      search: '',
      from: '',
      to: '',
      supplier_id: '',
      item_id: '',
      perPage: 15
    },
    preserveState: false,
    preserveScroll: false,
    replace: true,
    onFinish: () => {
      loading.value = false;
      isUpdatingFromProps.value = false;
    }
  });
};

const exportReport = () => {
  if (loading.value) return;
  
  const params = new URLSearchParams({
    search: filters.value.search || '',
    from: filters.value.from || '',
    to: filters.value.to || '',
    supplier_id: filters.value.supplier_id?.id || '',
    item_id: filters.value.item_id?.id || '',
  });
  
  window.open(`/po-report/export?${params.toString()}`, '_blank');
};

const formatDate = (date) => {
  if (!date) return '-';
  try {
    return new Date(date).toLocaleDateString('id-ID');
  } catch (error) {
    return '-';
  }
};

const formatNumber = (number) => {
  if (!number && number !== 0) return '0';
  return new Intl.NumberFormat('id-ID').format(Number(number) || 0);
};

const formatRupiah = (amount) => {
  if (!amount && amount !== 0) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(Number(amount) || 0);
};
</script>

<style scoped>
/* Custom styling for vue-multiselect */
:deep(.multiselect) {
  min-height: 38px;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 8px 12px;
}

:deep(.multiselect__single) {
  padding: 8px 12px;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__input) {
  padding: 8px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

:deep(.multiselect__option) {
  padding: 8px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}
</style>
