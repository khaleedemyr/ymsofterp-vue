<template>
  <AppLayout>
    <div class="max-w-xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fa-solid fa-recycle text-green-500"></i> Detail Internal Use & Waste
      </h1>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="mb-4">
          <div class="mb-2"><b>ID:</b> {{ data.id }}</div>
          <div class="mb-2"><b>Tipe:</b> {{ typeLabel(data.type) }}</div>
          <div class="mb-2"><b>Tanggal:</b> {{ formatDate(data.date) }}</div>
          <div class="mb-2"><b>Warehouse:</b> {{ data.warehouse_name }}</div>
          <div v-if="data.type === 'internal_use' && data.nama_ruko" class="mb-2"><b>Ruko:</b> {{ data.nama_ruko }}</div>
        </div>
        <div>
          <b>Detail Item:</b>
          <table class="w-full mt-2 border text-sm">
            <thead>
              <tr class="bg-gray-100">
                <th class="px-2 py-1 border">Item</th>
                <th class="px-2 py-1 border">Qty</th>
                <th class="px-2 py-1 border">Unit</th>
                <th class="px-2 py-1 border">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="px-2 py-1 border">{{ data.item_name }}</td>
                <td class="px-2 py-1 border">{{ formatNumber(data.qty) }}</td>
                <td class="px-2 py-1 border">{{ data.unit_name }}</td>
                <td class="px-2 py-1 border">{{ data.notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-6 flex justify-end">
          <button @click="goBack" class="btn btn-ghost px-6 py-2 rounded-lg">Kembali</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  id: [String, Number],
  data: Object
})

function typeLabel(type) {
  if (type === 'internal_use') return 'Internal Use';
  if (type === 'spoil') return 'Spoil';
  if (type === 'waste') return 'Waste';
  return type;
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
function goBack() {
  router.visit(route('internal-use-waste.index'))
}
</script> 