<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  user: Object,
  butchers: Object,
});
</script>
<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-cut text-red-500"></i> Butcher
        </h1>
        <div class="flex gap-2">
          <button class="bg-gradient-to-r from-red-500 to-red-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Butcher
          </button>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-red-50 to-red-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase tracking-wider rounded-tl-2xl">No. Butcher</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase tracking-wider">Divisi Gudang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase tracking-wider">Pembuat</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="6" class="text-center py-10 text-gray-400">Belum ada data Butcher.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template> 