<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-4 px-0">
      <h1 class="text-2xl font-bold mb-6">Report Penjualan per Category</h1>
      <div class="w-full flex flex-wrap gap-3 items-center mb-4 px-2">
        <input v-model="search" type="text" placeholder="Cari gudang, kategori..." class="px-4 py-2 border border-gray-300 rounded-lg flex-1 min-w-[180px] focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Gudang</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[140px]">
            <option value="">Semua Gudang</option>
            <option v-for="w in warehouses" :key="w.name" :value="w.name">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Kategori</label>
          <select v-model="selectedCategory" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[140px]">
            <option value="">Semua Kategori</option>
            <option v-for="c in categories" :key="c.name" :value="c.name">{{ c.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tahun</label>
          <select v-model="selectedYear" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[100px]">
            <option value="">Semua Tahun</option>
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Bulan</label>
          <select v-model="selectedMonth" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[100px]">
            <option value="">Semua Bulan</option>
            <option v-for="(m, idx) in months" :key="idx" :value="idx+1">{{ m }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[80px]">
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
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto w-full px-2">
        <table class="min-w-full border border-gray-300">
          <thead>
            <tr class="bg-yellow-300 text-gray-900">
              <th class="px-4 py-2 border border-gray-300">Gudang</th>
              <th class="px-4 py-2 border border-gray-300">Bulan</th>
              <th class="px-4 py-2 border border-gray-300">Tahun</th>
              <th class="px-4 py-2 border border-gray-300">Kategori</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Nilai</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!report.length">
              <td colspan="5" class="text-center py-10 text-gray-400">Tidak ada data.</td>
            </tr>
            <tr v-for="(row, idx) in report" :key="row.gudang + '-' + row.bulan + '-' + row.tahun + '-' + row.category">
              <td class="px-4 py-2 border border-gray-200">{{ row.gudang }}</td>
              <td v-if="isFirstOfGroup(report, idx, 'bulan', 'tahun')" :rowspan="rowspanCount(report, idx, 'bulan', 'tahun')" class="px-4 py-2 border border-gray-200 align-top">{{ months[row.bulan-1] }}</td>
              <td v-else style="display:none"></td>
              <td v-if="isFirstOfGroup(report, idx, 'tahun', 'bulan')" :rowspan="rowspanCount(report, idx, 'tahun', 'bulan')" class="px-4 py-2 border border-gray-200 align-top">{{ row.tahun }}</td>
              <td v-else style="display:none"></td>
              <td class="px-4 py-2 border border-gray-200">{{ row.category }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.nilai) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="report.length">
            <tr class="bg-gray-50 font-bold">
              <td colspan="4" class="text-right px-6 py-3">Grand Total</td>
              <td class="px-6 py-3 text-right">{{ formatRupiah(grandTotal) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4" v-if="total > 0">
        <div class="text-sm text-gray-600">
          Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ total }} data
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
  report: Array,
  warehouses: Array,
  categories: Array,
  years: Array,
  filters: Object,
  total: Number,
  perPage: Number,
  page: Number
});
const months = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const search = ref(props.filters.search || '');
const selectedWarehouse = ref(props.filters.warehouse || '');
const selectedCategory = ref(props.filters.category || '');
const selectedYear = ref(props.filters.tahun || '');
const selectedMonth = ref(props.filters.bulan || '');
const perPage = ref(props.perPage || 25);
const page = ref(props.page || 1);
const loadingReload = ref(false);

const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + props.report.length, props.total));
const totalPages = computed(() => Math.ceil(props.total / perPage.value) || 1);

function prevPage() {
  if (page.value > 1) {
    page.value--;
    reloadData();
  }
}
function nextPage() {
  if (page.value < totalPages.value) {
    page.value++;
    reloadData();
  }
}

watch([search, selectedWarehouse, selectedCategory, selectedYear, selectedMonth, perPage], () => {
  page.value = 1;
});

function reloadData() {
  loadingReload.value = true;
  router.get(
    '/report-sales-per-category',
    {
      search: search.value,
      warehouse: selectedWarehouse.value,
      category: selectedCategory.value,
      tahun: selectedYear.value,
      bulan: selectedMonth.value,
      perPage: perPage.value,
      page: page.value
    },
    {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => loadingReload.value = false
    }
  );
}

const grandTotal = computed(() => props.report.reduce((sum, row) => sum + (Number(row.nilai) || 0), 0));

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value || 0);
}

function isFirstOfGroup(arr, idx, key, key2) {
  if (idx === 0) return true;
  return arr[idx][key] !== arr[idx - 1][key] || arr[idx][key2] !== arr[idx - 1][key2];
}
function rowspanCount(arr, idx, key, key2) {
  const val = arr[idx][key];
  const val2 = arr[idx][key2];
  let count = 1;
  for (let i = idx + 1; i < arr.length; i++) {
    if (arr[i][key] === val && arr[i][key2] === val2) count++;
    else break;
  }
  return count;
}
</script> 