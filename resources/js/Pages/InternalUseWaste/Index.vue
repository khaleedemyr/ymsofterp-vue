<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-recycle text-green-500"></i> Internal Use & Waste
        </h1>
        <button @click="goCreate" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Baru
        </button>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-green-50 to-green-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider rounded-tl-2xl">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Tipe</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Item</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Qty</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Unit</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider">Catatan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!props.data.length">
              <td colspan="8" class="text-center py-10 text-green-300">Tidak ada data.</td>
            </tr>
            <tr v-for="row in props.data" :key="row.id">
              <td class="px-6 py-3">{{ formatDate(row.date) }}</td>
              <td class="px-6 py-3">{{ typeLabel(row.type) }}</td>
              <td class="px-6 py-3">{{ row.warehouse_name }}</td>
              <td class="px-6 py-3">{{ row.item_name }}</td>
              <td class="px-6 py-3">{{ formatNumber(row.qty) }}</td>
              <td class="px-6 py-3">{{ row.unit_name }}</td>
              <td class="px-6 py-3">{{ row.notes }}</td>
              <td class="px-6 py-3">
                <button class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </button>
                <button class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition ml-2">
                  <i class="fa fa-trash mr-1"></i> Hapus
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  data: Array
})

function goCreate() {
  router.visit(route('internal-use-waste.create'))
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}

function formatNumber(val) {
  if (val == null) return '-';
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function typeLabel(type) {
  if (type === 'internal_use') return 'Internal Use';
  if (type === 'spoil') return 'Spoil';
  if (type === 'waste') return 'Waste';
  return type;
}
</script> 