<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Report Penjualan per Outlet per Sub Kategori</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="tanggal" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        <input v-model="search" type="text" placeholder="Cari outlet..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Outlet</label>
          <select v-model="selectedOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Outlet</option>
            <option v-for="row in allOutlets" :key="row" :value="row">{{ row }}</option>
          </select>
        </div>
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
      <div v-else class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full border border-gray-300">
          <thead>
            <tr class="bg-yellow-300 text-gray-900">
              <th class="px-4 py-2 border border-gray-300">Customer</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Line Total</th>
              <th v-for="sc in subCategories" :key="sc.id" class="px-4 py-2 border border-gray-300 text-right">{{ sc.name }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!paginatedReport.length">
              <td :colspan="2 + subCategories.length" class="text-center py-10 text-gray-400">Tidak ada data.</td>
            </tr>
            <tr v-for="row in paginatedReport" :key="row.customer">
              <td class="px-4 py-2 border border-gray-200">{{ row.customer }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right font-bold">{{ formatRupiah(row.line_total) }}</td>
              <td v-for="sc in subCategories" :key="sc.id" class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row[sc.name]) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="paginatedReport.length">
            <tr class="bg-gray-100 font-bold">
              <td class="px-4 py-2 border border-gray-300 text-right">TOTAL</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalLineTotal) }}</td>
              <td v-for="sc in subCategories" :key="sc.id" class="px-4 py-2 border border-gray-300 text-right">
                {{ formatRupiah(totalPerSubCategory[sc.name] || 0) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div v-if="tanggal && filteredReport.length" class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-600">
          Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ filteredReport.length }} data
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
  subCategories: Array,
  report: Array,
  filters: Object
});
const tanggal = ref(props.filters?.tanggal || '');
const search = ref('');
const selectedOutlet = ref('');
const perPage = ref(25);
const page = ref(1);

const allOutlets = computed(() => {
  return [...new Set(props.report.map(r => r.customer))].sort();
});

const filteredReport = computed(() => {
  let data = props.report;
  if (selectedOutlet.value) {
    data = data.filter(row => row.customer === selectedOutlet.value);
  }
  if (search.value) {
    const s = search.value.toLowerCase();
    data = data.filter(row => row.customer && row.customer.toLowerCase().includes(s));
  }
  return data;
});

const totalPages = computed(() => Math.ceil(filteredReport.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + paginatedReport.value.length, filteredReport.value.length));
const paginatedReport = computed(() => filteredReport.value.slice(startIndex.value, startIndex.value + perPage.value));

function prevPage() {
  if (page.value > 1) page.value--;
}
function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
watch([perPage, search, selectedOutlet], () => { page.value = 1; });
function reloadData() {
  router.get('/report-sales-pivot-per-outlet-sub-category', { tanggal: tanggal.value }, { preserveState: true, preserveScroll: true });
}

const totalLineTotal = computed(() =>
  paginatedReport.value.reduce((sum, row) => sum + (Number(row.line_total) || 0), 0)
);
const totalPerSubCategory = computed(() => {
  const totals = {};
  props.subCategories.forEach(sc => {
    totals[sc.name] = paginatedReport.value.reduce((sum, row) => sum + (Number(row[sc.name]) || 0), 0);
  });
  return totals;
});
function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value || 0);
}
</script> 