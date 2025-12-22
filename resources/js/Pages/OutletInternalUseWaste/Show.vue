<template>
  <AppLayout>
    <div class="max-w-xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fa-solid fa-recycle text-green-500"></i> Detail Category Cost Outlet
      </h1>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="mb-4">
          <div class="mb-2">
            <b>Nomor:</b> 
            <span class="font-semibold" :class="header.number && header.number.startsWith('DRAFT-') ? 'text-orange-600' : 'text-blue-600'">
              {{ header.number || '-' }}
            </span>
          </div>
          <div class="mb-2"><b>ID:</b> {{ header.id }}</div>
          <div class="mb-2"><b>Tipe:</b> {{ typeLabel(header.type) }}</div>
          <div class="mb-2"><b>Tanggal:</b> {{ formatDate(header.date) }}</div>
          <div class="mb-2"><b>Outlet:</b> {{ header.outlet_name }}</div>
          <div v-if="header.type === 'internal_use' && header.nama_ruko" class="mb-2"><b>Ruko:</b> {{ header.nama_ruko }}</div>
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
              <tr v-for="item in details" :key="item.id">
                <td class="px-2 py-1 border">{{ item.item_name }}</td>
                <td class="px-2 py-1 border">{{ formatNumber(item.qty) }}</td>
                <td class="px-2 py-1 border">{{ item.unit_name }}</td>
                <td class="px-2 py-1 border">{{ item.note || '-' }}</td>
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
  header: Object,
  details: Array
})

function typeLabel(type) {
  if (type === 'internal_use') return 'Internal Use';
  if (type === 'spoil') return 'Spoil';
  if (type === 'waste') return 'Waste';
  if (type === 'r_and_d') return 'R & D';
  if (type === 'marketing') return 'Marketing';
  if (type === 'non_commodity') return 'Non Commodity';
  if (type === 'guest_supplies') return 'Guest Supplies';
  if (type === 'wrong_maker') return 'Wrong Maker';
  if (type === 'training') return 'Training';
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
  router.visit(route('outlet-internal-use-waste.index'))
}
</script> 