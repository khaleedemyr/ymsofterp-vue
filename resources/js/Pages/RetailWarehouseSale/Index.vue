<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  sales: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

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
  },
  { immediate: true }
);

function debouncedSearch() {
  router.get('/retail-warehouse-sale', { 
    search: search.value, 
    from: from.value, 
    to: to.value 
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
  debouncedSearch();
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
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
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
            <tr v-if="sales.length === 0">
              <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data penjualan.</td>
            </tr>
            <tr v-for="sale in sales" :key="sale.id" class="hover:bg-blue-50 transition shadow-sm">
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
    </div>
  </AppLayout>
</template> 