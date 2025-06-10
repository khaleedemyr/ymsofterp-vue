<template>
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fas fa-cut"></i> Summary Hasil Butcher
      </h1>
      <div class="flex flex-wrap gap-4 mb-6 items-end">
        <div>
          <label class="block text-sm font-medium text-gray-700">Tanggal Dari</label>
          <input type="date" v-model="from" class="mt-1 block w-full rounded border-gray-300" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Tanggal Sampai</label>
          <input type="date" v-model="to" class="mt-1 block w-full rounded border-gray-300" />
        </div>
        <div>
          <button @click="applyFilter" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 mt-6">Terapkan</button>
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700">Cari Item</label>
          <input type="text" v-model="search" placeholder="Cari nama item..." class="mt-1 block w-full rounded border-gray-300" />
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
              <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Qty (pcs)</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Satuan</th>
              <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Qty (kg)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!data.length">
              <td colspan="5" class="text-center py-10 text-gray-400">Tidak ada data hasil butcher.</td>
            </tr>
            <tr v-for="row in data" :key="row.process_date + row.item_name">
              <td class="px-4 py-2">{{ formatDate(row.process_date) }}</td>
              <td class="px-4 py-2">{{ row.item_name }}</td>
              <td class="px-4 py-2 text-right">{{ formatNumber(row.total_pcs_qty) }}</td>
              <td class="px-4 py-2">{{ row.unit_name }}</td>
              <td class="px-4 py-2 text-right">{{ formatNumber(row.total_qty_kg) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({ data: Array, filters: Object });
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const search = ref(props.filters.search || '');

function applyFilter() {
  router.get(route('butcher-summary-report.index'), { from: from.value, to: to.value, search: search.value }, { preserveState: true, replace: true });
}

let debounceTimeout = null;
watch(search, (val) => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    applyFilter();
  }, 400);
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
function formatNumber(val) {
  if (val == null) return 0;
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script> 