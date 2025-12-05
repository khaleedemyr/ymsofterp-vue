<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Laporan Rekap Persediaan per Kategori</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="search" type="text" placeholder="Cari kategori/gudang..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Kategori</label>
          <select v-model="selectedCategory" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Kategori</option>
            <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.name">{{ w.name }}</option>
          </select>
        </div>
        <button @click="reloadData" :disabled="loadingReload" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
          <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
          <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Load Data
        </button>
      </div>
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Kategori</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total Value</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!filteredRecaps.length">
              <td colspan="3" class="text-center py-10 text-gray-400">Tidak ada data rekap kategori.</td>
            </tr>
            <tr v-for="row in filteredRecaps" :key="row.category_name + '-' + row.warehouse_name" class="hover:bg-gray-50 transition">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.category_name || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ row.warehouse_name || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayValue(row.total_value) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="filteredRecaps.length">
            <tr class="bg-gray-50 font-bold">
              <td class="text-right px-6 py-3" colspan="2">Grand Total</td>
              <td class="px-6 py-3 text-right">{{ displayValue(grandTotalValue) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
  recaps: Array,
  categories: Array,
  warehouses: Array
});

const search = ref('');
const selectedCategory = ref('');
const selectedWarehouse = ref('');
const loadingReload = ref(false)

const filteredRecaps = computed(() => {
  let data = props.recaps;
  if (selectedCategory.value) {
    data = data.filter(row => row.category_name === selectedCategory.value);
  }
  if (selectedWarehouse.value) {
    data = data.filter(row => row.warehouse_name === selectedWarehouse.value);
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.category_name && row.category_name.toLowerCase().includes(s)) ||
    (row.warehouse_name && row.warehouse_name.toLowerCase().includes(s))
  );
});

const grandTotalValue = computed(() => filteredRecaps.value.reduce((sum, row) => sum + (Number(row.total_value) || 0), 0));

function displayValue(val) {
  if (!val || Number(val) === 0) return '-';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function reloadData() {
  loadingReload.value = true
  window.location.reload()
}
</script> 