<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Laporan Stok Akhir</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="search" type="text" placeholder="Cari nama barang atau warehouse..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nama Barang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Small</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Medium</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Large</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal Update</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!filteredStocks.length">
              <td colspan="6" class="text-center py-10 text-gray-400">Tidak ada data stok.</td>
            </tr>
            <tr v-for="row in paginatedStocks" :key="row.item_id + '-' + row.warehouse_id" class="hover:bg-gray-50 transition">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.item_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                <span v-if="row.display_small > 0">{{ displayQty(row.display_small) }} <span v-if="row.small_unit_name">{{ row.small_unit_name }}</span></span>
                <span v-else>-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                <span v-if="row.display_medium > 0">{{ displayQty(row.display_medium) }} <span v-if="row.medium_unit_name">{{ row.medium_unit_name }}</span></span>
                <span v-else>-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                <span v-if="row.display_large > 0">{{ displayQty(row.display_large) }} <span v-if="row.large_unit_name">{{ row.large_unit_name }}</span></span>
                <span v-else>-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.updated_at ? new Date(row.updated_at).toLocaleString() : '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4" v-if="filteredStocks.length">
        <div class="text-sm text-gray-600">
          Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ filteredStocks.length }} data
        </div>
        <div class="flex gap-1">
          <button @click="prevPage" :disabled="page === 1" class="px-3 py-1 rounded border text-sm" :class="page === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&lt;</button>
          <span class="px-2">Halaman {{ page }} / {{ totalPages }}</span>
          <button @click="nextPage" :disabled="page === totalPages" class="px-3 py-1 rounded border text-sm" :class="page === totalPages ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&gt;</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
const props = defineProps({
  stocks: Array,
  warehouses: Array
});
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedWarehouse = ref('');

const filteredStocks = computed(() => {
  let data = props.stocks;
  if (selectedWarehouse.value) {
    data = data.filter(row => String(row.warehouse_id) === String(selectedWarehouse.value));
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.warehouse_name && row.warehouse_name.toLowerCase().includes(s))
  );
});

const totalPages = computed(() => Math.ceil(filteredStocks.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, filteredStocks.value.length));
const paginatedStocks = computed(() => filteredStocks.value.slice(startIndex.value, endIndex.value));

function prevPage() {
  if (page.value > 1) page.value--;
}
function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
watch([perPage, search], () => { page.value = 1; });

function displayQty(val) {
  if (!val || Number(val) === 0) return '-';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
function displayValue(val) {
  if (!val || Number(val) === 0) return '-';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script> 