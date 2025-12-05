<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline">
          <i class="fa fa-arrow-left"></i> Kembali
        </button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fas fa-exchange-alt text-blue-500"></i>
          Detail Penjualan Antar Gudang
        </h1>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Informasi Transaksi</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-500">Nomor</label>
                <div class="mt-1 text-gray-900">{{ sale.number }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500">Tanggal</label>
                <div class="mt-1 text-gray-900">{{ formatDate(sale.date) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500">Status</label>
                <div class="mt-1">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                    {{ sale.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Informasi Gudang</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-500">Gudang Asal</label>
                <div class="mt-1 text-gray-900">{{ sale.source_warehouse?.name }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500">Gudang Tujuan</label>
                <div class="mt-1 text-gray-900">{{ sale.target_warehouse?.name }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500">Dibuat Oleh</label>
                <div class="mt-1 text-gray-900">{{ sale.creator?.name }}</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="sale.note" class="mt-6">
          <label class="block text-sm font-medium text-gray-500">Keterangan</label>
          <div class="mt-1 text-gray-900">{{ sale.note }}</div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Detail Item</h2>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty Small</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty Medium</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty Large</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Harga</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="item in sale.items" :key="item.id">
                <td class="px-3 py-2">
                  <div class="text-sm font-medium text-gray-900">{{ item.item?.name }}</div>
                  <div class="text-xs text-gray-500">{{ item.item?.sku }}</div>
                </td>
                <td class="px-3 py-2 text-sm text-gray-900">{{ formatNumber(item.qty_small) }}</td>
                <td class="px-3 py-2 text-sm text-gray-900">{{ formatNumber(item.qty_medium) }}</td>
                <td class="px-3 py-2 text-sm text-gray-900">{{ formatNumber(item.qty_large) }}</td>
                <td class="px-3 py-2 text-sm text-gray-900">{{ formatNumber(item.price) }}</td>
                <td class="px-3 py-2 text-sm text-gray-900">{{ formatNumber(item.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  sale: Object
});

function goBack() {
  router.visit('/warehouse-sales');
}

function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  });
}

function formatNumber(val) {
  if (val == null) return 0;
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  });
}
</script> 