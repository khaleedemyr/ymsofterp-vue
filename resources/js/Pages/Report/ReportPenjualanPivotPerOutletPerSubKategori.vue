<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <h1 class="text-2xl font-bold mb-6">Report Penjualan Pivot per Outlet per Sub Kategori</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="tanggal" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        <input v-model="search" type="text" placeholder="Cari item/unit..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
        </div>
        <button @click="reloadData" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
          <span class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Load Data
        </button>
      </div>
      <div v-if="!tanggal" class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500 font-bold">
        Silakan pilih tanggal terlebih dahulu
      </div>
      <div v-else class="bg-white rounded-xl shadow-lg overflow-x-auto w-full">
        <table class="w-full min-w-full border border-gray-300">
          <thead>
            <tr class="bg-yellow-300 text-gray-900">
              <th class="px-4 py-2 border border-gray-300">Nama Items</th>
              <th class="px-4 py-2 border border-gray-300">Unit</th>
              <th v-for="outlet in outlets" :key="outlet.id_outlet" class="px-4 py-2 border border-gray-300 text-right">{{ outlet.nama_outlet }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!paginatedItems.length">
              <td :colspan="2 + outlets.length" class="text-center py-10 text-gray-400">Tidak ada data.</td>
            </tr>
            <tr v-for="row in paginatedItems" :key="row.item_name + '-' + row.unit_name">
              <td class="px-4 py-2 border border-gray-200">{{ row.item_name }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ row.unit_name }}</td>
              <td v-for="outlet in outlets" :key="outlet.id_outlet" class="px-4 py-2 border border-gray-200 text-right">
                {{ row[outlet.nama_outlet] ? formatQty(row[outlet.nama_outlet]) : '' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="tanggal && filteredItems.length" class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-600">
          Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ filteredItems.length }} data
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
import { router } from '@inertiajs/vue3';
const props = defineProps({
  outlets: Array,
  items: Array,
  filters: Object
});
const tanggal = ref(props.filters?.tanggal || '');
const search = ref('');
const perPage = ref(25);
const page = ref(1);

const filteredItems = computed(() => {
  if (!search.value) return props.items;
  const s = search.value.toLowerCase();
  return props.items.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.unit_name && row.unit_name.toLowerCase().includes(s))
  );
});
const totalPages = computed(() => Math.ceil(filteredItems.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + paginatedItems.value.length, filteredItems.value.length));
const paginatedItems = computed(() => filteredItems.value.slice(startIndex.value, startIndex.value + perPage.value));

function prevPage() {
  if (page.value > 1) page.value--;
}
function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
watch([perPage, search], () => { page.value = 1; });
function reloadData() {
  router.get('/report-penjualan-pivot-per-outlet-per-sub-kategori', { tanggal: tanggal.value }, { preserveState: true, preserveScroll: true });
}
function formatQty(val) {
  if (val == null) return '';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script> 