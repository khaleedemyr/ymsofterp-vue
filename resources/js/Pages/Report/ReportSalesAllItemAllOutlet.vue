<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-4 px-0">
      <h1 class="text-2xl font-bold mb-6">Report Penjualan All Item ke All Outlet</h1>
      <div class="w-full flex flex-wrap gap-3 items-center mb-4 px-2">
        <input v-model="search" type="text" placeholder="Cari gudang, outlet, barang..." class="px-4 py-2 border border-gray-300 rounded-lg flex-1 min-w-[180px] focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Gudang</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[140px]">
            <option value="">Semua Gudang</option>
            <option v-for="w in warehouses" :key="w.name" :value="w.name">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Outlet</label>
          <select v-model="selectedOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[160px]">
            <option value="">Semua Outlet</option>
            <option v-for="o in outlets" :key="o.nama_outlet" :value="o.nama_outlet">{{ o.nama_outlet }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Dari</label>
          <input v-model="dateFrom" type="date" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[120px]" />
          <label class="text-sm">s/d</label>
          <input v-model="dateTo" type="date" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[120px]" />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[80px]">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
        </div>
        <button @click="exportToExcel" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 mr-2">
          <i class="fas fa-file-excel mr-2"></i>
          Export Excel
        </button>
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
              <th class="px-4 py-2 border border-gray-300">Tanggal</th>
              <th class="px-4 py-2 border border-gray-300">Gudang</th>
              <th class="px-4 py-2 border border-gray-300">Outlet</th>
              <th class="px-4 py-2 border border-gray-300">Nama Barang</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Qty</th>
              <th class="px-4 py-2 border border-gray-300">Unit</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Harga</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!report.length">
              <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data.</td>
            </tr>
            <tr v-for="row in report" :key="row.tanggal + '-' + row.gudang + '-' + row.outlet + '-' + row.nama_barang">
              <td class="px-4 py-2 border border-gray-200">{{ row.tanggal }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ row.gudang }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ row.outlet }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ row.nama_barang }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ row.qty }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ row.unit }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.harga) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.subtotal) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="report.length">
            <tr class="bg-gray-50 font-bold">
              <td colspan="7" class="text-right px-6 py-3">Grand Total</td>
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
  outlets: Array,
  filters: Object,
  total: Number,
  perPage: Number,
  page: Number
});
const search = ref(props.filters.search || '');
const selectedWarehouse = ref(props.filters.gudang || '');
const selectedOutlet = ref(props.filters.outlet || '');
const perPage = ref(props.perPage || 25);
const page = ref(props.page || 1);
const loadingReload = ref(false);
const dateFrom = ref(props.filters?.dateFrom || '');
const dateTo = ref(props.filters?.dateTo || '');

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

watch([search, selectedWarehouse, selectedOutlet, perPage], () => {
  page.value = 1;
});

function reloadData() {
  loadingReload.value = true;
  router.get(
    '/report-sales-all-item-all-outlet',
    {
      search: search.value,
      gudang: selectedWarehouse.value,
      outlet: selectedOutlet.value,
      perPage: perPage.value,
      page: page.value,
      dateFrom: dateFrom.value,
      dateTo: dateTo.value
    },
    {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => loadingReload.value = false
    }
  );
}

const grandTotal = computed(() => props.report.reduce((sum, row) => sum + (Number(row.subtotal) || 0), 0));

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value || 0);
}

function exportToExcel() {
  const params = new URLSearchParams({
    search: search.value,
    gudang: selectedWarehouse.value,
    outlet: selectedOutlet.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value
  });
  
  window.open(`/report-sales-all-item-all-outlet/export?${params.toString()}`, '_blank');
}
</script> 