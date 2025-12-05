<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Laporan Umur Persediaan (Aging Report)</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="search" type="text" placeholder="Cari barang, warehouse, kategori..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.name">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Kategori</label>
          <select v-model="selectedCategory" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Kategori</option>
            <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Barang</label>
          <select v-model="selectedItem" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Barang</option>
            <option v-for="i in items" :key="i.id" :value="i.name">{{ i.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Moving</label>
          <select v-model="selectedMoving" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua</option>
            <option value="Fast Moving">Fast Moving</option>
            <option value="Slow Moving">Slow Moving</option>
            <option value="Normal">Normal</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
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
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Barang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Kategori</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Stok</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Umur (hari)</th>
              <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Moving</th>
              <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Tgl Masuk</th>
              <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Tgl Keluar</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!filteredAgings.length">
              <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data umur persediaan.</td>
            </tr>
            <tr v-for="row in paginatedAgings" :key="row.item_name + row.warehouse_name" class="hover:bg-gray-50 transition">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.item_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.category_name || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayQty(row.stock) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ row.days_in_stock }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                <span :class="{
                  'text-green-600 font-bold': row.moving_category === 'Fast Moving',
                  'text-red-600 font-bold': row.moving_category === 'Slow Moving',
                  'text-gray-700': row.moving_category === 'Normal'
                }">{{ row.moving_category }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-center">{{ formatDate(row.first_in_date) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-center">{{ formatDate(row.last_out_date) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4" v-if="filteredAgings.length">
        <div class="text-sm text-gray-600">
          Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ filteredAgings.length }} data
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
  agings: Array,
  warehouses: Array,
  categories: Array,
  items: Array
});
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedWarehouse = ref('');
const selectedCategory = ref('');
const selectedItem = ref('');
const selectedMoving = ref('');
const loadingReload = ref(false)

const filteredAgings = computed(() => {
  let data = props.agings;
  if (selectedWarehouse.value) {
    data = data.filter(row => String(row.warehouse_name) === String(selectedWarehouse.value));
  }
  if (selectedCategory.value) {
    data = data.filter(row => row.category_name === selectedCategory.value);
  }
  if (selectedItem.value) {
    data = data.filter(row => row.item_name === selectedItem.value);
  }
  if (selectedMoving.value) {
    data = data.filter(row => row.moving_category === selectedMoving.value);
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.warehouse_name && row.warehouse_name.toLowerCase().includes(s)) ||
    (row.category_name && row.category_name.toLowerCase().includes(s))
  );
});

const totalPages = computed(() => Math.ceil(filteredAgings.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, filteredAgings.value.length));
const paginatedAgings = computed(() => filteredAgings.value.slice(startIndex.value, endIndex.value));

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
function formatDate(val) {
  if (!val) return '-';
  const d = new Date(val);
  if (isNaN(d)) return '-';
  return d.toLocaleDateString('id-ID');
}

function reloadData() {
  loadingReload.value = true
  window.location.reload()
}
</script> 