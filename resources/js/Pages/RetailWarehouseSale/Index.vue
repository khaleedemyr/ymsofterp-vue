<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  sales: Array,
});

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