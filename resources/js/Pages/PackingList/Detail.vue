<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-box text-blue-500"></i> Detail Packing List
        </h1>
      </div>
      <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="mb-2"><span class="font-semibold">No. Packing List:</span> {{ packingList.packing_number }}</div>
            <div class="mb-2"><span class="font-semibold">Tanggal:</span> {{ new Date(packingList.created_at).toLocaleDateString('id-ID') }}</div>
            <div class="mb-2"><span class="font-semibold">Status:</span> <span class="capitalize">{{ packingList.status }}</span></div>
          </div>
          <div>
            <div class="mb-2"><span class="font-semibold">Divisi Gudang Asal:</span> {{ packingList.warehouse_division?.name ?? '-' }}</div>
            <div class="mb-2"><span class="font-semibold">Outlet Tujuan:</span> {{ packingList.floor_order?.outlet?.nama_outlet ?? '-' }}</div>
            <div class="mb-2"><span class="font-semibold">Pemohon FO:</span> {{ packingList.floor_order?.requester?.nama_lengkap ?? '-' }}</div>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-lg font-bold mb-4">Daftar Item</h2>
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Nama Item</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Sumber</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!packingList.items || packingList.items.length === 0">
              <td colspan="5" class="text-center py-6 text-gray-400">Tidak ada item.</td>
            </tr>
            <tr v-for="(item, idx) in packingList.items" :key="item.id" class="hover:bg-blue-50 transition">
              <td class="px-4 py-2">{{ idx + 1 }}</td>
              <td class="px-4 py-2">{{ item.floor_order_item?.item?.name ?? '-' }}</td>
              <td class="px-4 py-2">{{ item.qty }}</td>
              <td class="px-4 py-2">{{ item.unit }}</td>
              <td class="px-4 py-2 capitalize">{{ item.source }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-6">
        <button @click="goToIndex" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-xl font-semibold shadow">Kembali</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage, router } from '@inertiajs/vue3';
const props = defineProps({
  packingList: Object
});
function goToIndex() {
  router.visit('/packing-list');
}
</script> 