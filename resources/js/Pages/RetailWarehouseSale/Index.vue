<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  sales: Object, // Changed from Array to Object for pagination
  filters: Object,
});

const search = ref(props.filters?.search || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const perPage = ref(props.filters?.per_page || 15);

function goToCreate() {
  router.visit(route('retail-warehouse-sale.create'));
}

function goToShow(id) {
  router.visit(route('retail-warehouse-sale.show', id));
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID');
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(amount);
}

// Watch untuk auto search
watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    from.value = filters?.from || '';
    to.value = filters?.to || '';
    perPage.value = filters?.per_page || 15;
  },
  { immediate: true }
);

function debouncedSearch() {
  router.get('/retail-warehouse-sale', { 
    search: search.value, 
    from: from.value, 
    to: to.value,
    per_page: perPage.value
  }, { preserveState: true, replace: true });
}

function onSearchInput() {
  debouncedSearch();
}

function onDateChange() {
  debouncedSearch();
}

function clearFilters() {
  search.value = '';
  from.value = '';
  to.value = '';
  perPage.value = 15;
  debouncedSearch();
}

function onPerPageChange() {
  debouncedSearch();
}

function goToPage(page) {
  router.get('/retail-warehouse-sale', { 
    search: search.value, 
    from: from.value, 
    to: to.value,
    per_page: perPage.value,
    page: page
  }, { preserveState: true, replace: true });
}

function getVisiblePages() {
  const current = props.sales.current_page;
  const last = props.sales.last_page;
  const pages = [];
  
  if (last <= 7) {
    // Show all pages if total pages <= 7
    for (let i = 1; i <= last; i++) {
      pages.push(i);
    }
  } else {
    // Show first page
    pages.push(1);
    
    if (current > 4) {
      pages.push('...');
    }
    
    // Show pages around current page
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);
    
    for (let i = start; i <= end; i++) {
      if (i !== 1 && i !== last) {
        pages.push(i);
      }
    }
    
    if (current < last - 3) {
      pages.push('...');
    }
    
    // Show last page
    if (last > 1) {
      pages.push(last);
    }
  }
  
  return pages;
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-store text-blue-500"></i>
          Penjualan Warehouse Retail
        </h1>
        <button @click="goToCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Penjualan Baru
        </button>
      </div>

      <!-- Search dan Filter -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
            <input
              v-model="search"
              @input="onSearchInput"
              type="text"
              placeholder="Cari nomor, customer, warehouse..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          
          <!-- Filter Tanggal Dari -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
            <input
              v-model="from"
              @change="onDateChange"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          
          <!-- Filter Tanggal Sampai -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
            <input
              v-model="to"
              @change="onDateChange"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          
          <!-- Per Page -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Halaman</label>
            <select
              v-model="perPage"
              @change="onPerPageChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
          
          <!-- Tombol Clear -->
          <div>
            <button
              @click="clearFilters"
              class="w-full px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
            >
              Clear Filter
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No. Penjualan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Customer</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="sales.data.length === 0">
              <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data penjualan.</td>
            </tr>
            <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-semibold">{{ sale.number }}</td>
              <td class="px-6 py-3">
                <div>
                  <div class="font-semibold">{{ sale.customer_name }}</div>
                  <div class="text-sm text-gray-500">{{ sale.customer_code }}</div>
                </div>
              </td>
              <td class="px-6 py-3">
                <div>
                  <div class="font-semibold">{{ sale.warehouse_name }}</div>
                  <div class="text-sm text-gray-500">{{ sale.division_name }}</div>
                </div>
              </td>
              <td class="px-6 py-3 font-semibold">{{ formatCurrency(sale.total_amount) }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-green-100 text-green-700': sale.status === 'completed',
                  'bg-yellow-100 text-yellow-700': sale.status === 'draft',
                  'bg-red-100 text-red-700': sale.status === 'cancelled'
                }" class="px-2 py-1 rounded-full text-xs font-semibold">
                  {{ sale.status === 'completed' ? 'Selesai' : sale.status === 'draft' ? 'Draft' : 'Dibatalkan' }}
                </span>
              </td>
              <td class="px-6 py-3 text-sm text-gray-500">{{ formatDate(sale.created_at) }}</td>
              <td class="px-6 py-3">
                <button @click="goToShow(sale.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  Detail
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="sales.last_page > 1" class="bg-white rounded-xl shadow-lg p-4 mt-6">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
          <!-- Info Pagination -->
          <div class="text-sm text-gray-700">
            Menampilkan {{ sales.from || 0 }} sampai {{ sales.to || 0 }} dari {{ sales.total }} data
          </div>
          
          <!-- Pagination Controls -->
          <div class="flex items-center gap-2">
            <!-- Previous Button -->
            <button
              @click="goToPage(sales.current_page - 1)"
              :disabled="!sales.prev_page_url"
              :class="[
                'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                sales.prev_page_url 
                  ? 'bg-blue-500 text-white hover:bg-blue-600' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              ]"
            >
              <i class="fa-solid fa-chevron-left mr-1"></i>
              Sebelumnya
            </button>
            
            <!-- Page Numbers -->
            <div class="flex items-center gap-1">
              <template v-for="page in getVisiblePages()" :key="page">
                <button
                  v-if="page !== '...'"
                  @click="goToPage(page)"
                  :class="[
                    'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                    page === sales.current_page
                      ? 'bg-blue-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  ]"
                >
                  {{ page }}
                </button>
                <span v-else class="px-2 text-gray-400">...</span>
              </template>
            </div>
            
            <!-- Next Button -->
            <button
              @click="goToPage(sales.current_page + 1)"
              :disabled="!sales.next_page_url"
              :class="[
                'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                sales.next_page_url 
                  ? 'bg-blue-500 text-white hover:bg-blue-600' 
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              ]"
            >
              Selanjutnya
              <i class="fa-solid fa-chevron-right ml-1"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 