<template>
        <AppLayout>
     <div class="w-full h-full p-4 flex flex-col">
       <!-- Header -->
       <div class="flex justify-between items-center mb-6 flex-shrink-0">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <i class="fa-solid fa-chart-line text-blue-600"></i>
            MAC Report (Moving Average Cost)
          </h1>
          <p class="text-gray-600 mt-2">Laporan Moving Average Cost untuk semua items dari food inventory</p>
        </div>
        <div class="flex gap-3">
          <button 
            @click="exportReport"
            :disabled="exporting"
            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center gap-2"
          >
            <i class="fa-solid fa-download"></i>
            {{ exporting ? 'Exporting...' : 'Export Excel' }}
          </button>
        </div>
      </div>

                           <!-- Summary Cards -->
                 <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 flex-shrink-0">
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center">
              <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fa-solid fa-boxes text-xl"></i>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Items</p>
                <p class="text-2xl font-bold text-gray-900">{{ summary.total_items || 0 }}</p>
              </div>
            </div>
          </div>

          

          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-600">
            <div class="flex items-center">
              <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fa-solid fa-arrow-up text-xl"></i>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Profit Items</p>
                <p class="text-2xl font-bold text-green-600">{{ summary.profit_items || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center">
              <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fa-solid fa-arrow-down text-xl"></i>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Loss Items</p>
                <p class="text-2xl font-bold text-red-600">{{ summary.loss_items || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
            <div class="flex items-center">
              <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fa-solid fa-equals text-xl"></i>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Break Even</p>
                <p class="text-2xl font-bold text-yellow-600">{{ summary.break_even_items || 0 }}</p>
              </div>
            </div>
          </div>
        </div>

                    <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6 flex-shrink-0">
         <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
         <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
           <div>
             <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
             <input 
               v-model="filters.search" 
               @input="debounceApplyFilters"
               type="text" 
               placeholder="Search items, code, warehouse..."
               class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
             />
           </div>
           
           <div>
             <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse</label>
             <select 
               v-model="filters.warehouse_id" 
               @change="applyFilters"
               class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
             >
               <option value="">All Warehouses</option>
               <option v-for="warehouse in (warehouses || [])" :key="warehouse.id" :value="warehouse.id">
                 {{ warehouse.name }}
               </option>
             </select>
           </div>

           <div>
             <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
             <select 
               v-model="filters.per_page" 
               @change="applyFilters"
               class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
             >
               <option value="10">10</option>
               <option value="25">25</option>
               <option value="50">50</option>
               <option value="100">100</option>
               <option value="200">200</option>
             </select>
           </div>

           <div class="flex items-end">
             <button 
               @click="clearFilters"
               class="w-full bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600"
             >
               Clear Filters
             </button>
           </div>
         </div>
       </div>

                    <!-- Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden flex-1 flex flex-col">
         <div class="px-6 py-4 border-b border-gray-200 flex-shrink-0">
           <h3 class="text-lg font-semibold text-gray-900">MAC Data</h3>
         </div>
         
         <div class="overflow-x-auto flex-1">
           <table class="min-w-full divide-y divide-gray-200">
                         <thead class="bg-gray-50">
               <tr>
                 <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Code</th>
                 <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                 <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Small</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Medium</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Large</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Price</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Medium+12%</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
               </tr>
             </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
                               <tr v-for="item in (items.data || [])" :key="`${item.item_id}-${item.warehouse_name}`" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ item.item_code || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ item.item_name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ item.warehouse_name || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                    {{ formatMacWithUnit(item.current_cost || 0, item.small_unit_name) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                    {{ formatMacWithUnit(calculateMediumCost(item), item.medium_unit_name) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                    {{ formatMacWithUnit(calculateLargeCost(item), item.large_unit_name) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                    {{ formatRupiah(item.item_price || 0) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                    {{ formatRupiah(item.mac_plus_12 || 0) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span :class="getStatusClass(item)" class="px-2 py-1 rounded-full text-xs font-medium">
                      {{ getStatusText(item) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatDate(item.last_updated) }}
                  </td>
                </tr>
             </tbody>
          </table>
        </div>

                                   <!-- Pagination -->
          <div v-if="items && items.links && items.links.length > 3" class="px-6 py-4 border-t border-gray-200 flex-shrink-0">
           <div class="flex items-center justify-between">
             <div class="text-sm text-gray-700">
               Showing {{ items.from || 0 }} to {{ items.to || 0 }} of {{ items.total || 0 }} results
             </div>
                           <div class="flex space-x-2">
                <template v-for="(link, index) in items.links" :key="index">
                  <a 
                    v-if="link && link.url && !link.active"
                    @click.prevent="goToPage(link.url)"
                    href="#"
                    class="px-3 py-2 text-sm rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 cursor-pointer"
                    v-html="link.label"
                  />
                  <span 
                    v-else-if="link && link.active"
                    class="px-3 py-2 text-sm rounded-lg bg-blue-600 text-white"
                    v-html="link.label"
                  />
                  <span 
                    v-else-if="link"
                    class="px-3 py-2 text-sm rounded-lg cursor-not-allowed opacity-50 bg-gray-200 text-gray-500"
                    v-html="link.label"
                  />
                </template>
              </div>
           </div>
         </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  items: {
    type: Object,
    default: () => ({ data: [], links: [] })
  },
  warehouses: {
    type: Array,
    default: () => []
  },
  summary: {
    type: Object,
    default: () => ({ total_items: 0, avg_cost: 0 })
  },
  filters: {
    type: Object,
    default: () => ({ search: '', warehouse_id: '', per_page: 50 })
  }
});

const filters = ref({
  search: props.filters?.search || '',
  warehouse_id: props.filters?.warehouse_id || '',
  per_page: props.filters?.per_page || 50
});

let searchTimeout = null;

const exporting = ref(false);

const formatRupiah = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount || 0);
};

const formatMacWithUnit = (amount, unitName) => {
  if (!amount || amount === 0) return '-';
  const formattedAmount = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount);
  return `${formattedAmount}/${unitName || 'unit'}`;
};

const formatNumber = (number) => {
  return new Intl.NumberFormat('id-ID').format(number || 0);
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const calculateMediumCost = (item) => {
  if (!item.current_cost || !item.small_conversion_qty || !item.medium_conversion_qty) return 0;
  // MAC per small unit * small_conversion_qty / medium_conversion_qty = MAC per medium unit
  return (item.current_cost * item.small_conversion_qty) / item.medium_conversion_qty;
};

const calculateLargeCost = (item) => {
  if (!item.current_cost || !item.small_conversion_qty) return 0;
  // MAC per small unit * small_conversion_qty = MAC per large unit (assuming large = 1)
  return item.current_cost * item.small_conversion_qty;
};

const getStatusText = (item) => {
  if (!item.item_price || !item.mac_plus_12) return 'N/A';
  
  // Add tolerance for floating point comparison (1 rupiah difference)
  const tolerance = 1;
  const difference = Math.abs(item.item_price - item.mac_plus_12);
  
  if (difference <= tolerance) {
    return 'Break Even';
  } else if (item.item_price > item.mac_plus_12) {
    return 'Profit';
  } else {
    return 'Loss';
  }
};

const getStatusClass = (item) => {
  if (!item.item_price || !item.mac_plus_12) return 'bg-gray-100 text-gray-800';
  
  // Add tolerance for floating point comparison (1 rupiah difference)
  const tolerance = 1;
  const difference = Math.abs(item.item_price - item.mac_plus_12);
  
  if (difference <= tolerance) {
    return 'bg-yellow-100 text-yellow-800';
  } else if (item.item_price > item.mac_plus_12) {
    return 'bg-green-100 text-green-800';
  } else {
    return 'bg-red-100 text-red-800';
  }
};

const applyFilters = () => {
  router.get('/mac-report', filters.value, {
    preserveState: true,
    preserveScroll: true
  });
};

const debounceApplyFilters = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 500);
};

const clearFilters = () => {
  filters.value = {
    search: '',
    warehouse_id: '',
    per_page: 50
  };
  applyFilters();
};

const goToPage = (url) => {
  // Extract page parameter from URL
  const urlParams = new URLSearchParams(url.split('?')[1]);
  const page = urlParams.get('page');
  
  // Create new filters object with current filters + page
  const newFilters = { ...filters.value };
  if (page) {
    newFilters.page = page;
  }
  
  // Navigate with filters preserved
  router.get('/mac-report', newFilters, {
    preserveState: true,
    preserveScroll: true
  });
};

const exportReport = async () => {
  try {
    exporting.value = true;
    
    const response = await axios.post('/mac-report/export', filters.value);
    
    if (response.data.success) {
      // Create and download Excel file
      const data = response.data.data;
      const filename = response.data.filename;
      
                                       // Convert to CSV for now (you can implement Excel export later)
          let csv = 'Item Code,Item Name,Warehouse,MAC Small,MAC Medium,MAC Large,Item Price,MAC Medium+12%,Status,Last Updated\n';
          
          data.forEach(item => {
            const mediumCost = calculateMediumCost(item);
            const largeCost = calculateLargeCost(item);
            const macSmall = formatMacWithUnit(item.current_cost || 0, item.small_unit_name);
            const macMedium = formatMacWithUnit(mediumCost, item.medium_unit_name);
            const macLarge = formatMacWithUnit(largeCost, item.large_unit_name);
            const status = getStatusText(item);
            csv += `"${item.item_code || ''}","${item.item_name}","${item.warehouse_name || ''}","${macSmall}","${macMedium}","${macLarge}","${item.item_price || 0}","${item.mac_plus_12 || 0}","${status}","${item.last_updated || ''}"\n`;
          });
      
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename.replace('.xlsx', '.csv');
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      
      Swal.fire({
        icon: 'success',
        title: 'Export Berhasil!',
        text: 'File telah berhasil diunduh.',
        confirmButtonColor: '#3085d6',
      });
    }
  } catch (error) {
    console.error('Export error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Export Gagal!',
      text: 'Terjadi kesalahan saat mengexport data.',
      confirmButtonColor: '#3085d6',
    });
  } finally {
    exporting.value = false;
  }
};
</script>
