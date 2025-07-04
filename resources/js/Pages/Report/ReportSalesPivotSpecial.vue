<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-4 px-0">
      <h1 class="text-2xl font-bold mb-6">Report Rekap FJ</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="tanggal" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        <input v-model="search" type="text" placeholder="Cari outlet..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
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
      <div v-else class="bg-white rounded-xl shadow-lg overflow-x-auto relative">
        <div v-if="loading" class="absolute inset-0 bg-white/70 z-20 flex items-center justify-center">
          <svg class="animate-spin h-12 w-12 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
        </div>
        <table class="min-w-full border border-gray-300">
          <thead>
            <tr class="bg-yellow-300 text-gray-900">
              <th class="px-4 py-2 border border-gray-300">Customer</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Main Kitchen</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Main Store</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Chemical</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Stationary</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Marketing</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Line Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!paginatedReport.length">
              <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data.</td>
            </tr>
            <tr v-for="row in paginatedReport" :key="row.customer">
              <td class="px-4 py-2 border border-gray-200 flex items-center gap-2">
                {{ row.customer }}
                <button @click="showDetail(row.customer)" class="ml-2 bg-gradient-to-br from-yellow-400 to-yellow-600 text-white rounded-full shadow-lg px-2 py-1 hover:scale-110 transition-all font-bold" title="Lihat Detail">
                  <i class="fas fa-search-plus"></i>
                </button>
              </td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_kitchen) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_store) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.chemical) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.stationary) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.marketing) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right font-bold">{{ formatRupiah(row.line_total) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="paginatedReport.length">
            <tr class="bg-gray-100 font-bold">
              <td class="px-4 py-2 border border-gray-300 text-right">TOTAL</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('main_kitchen')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('main_store')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('chemical')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('stationary')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('marketing')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('line_total')) }}</td>
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
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
        <div class="bg-gradient-to-br from-yellow-200 via-white to-yellow-100 rounded-3xl shadow-2xl p-8 min-w-[350px] max-w-2xl w-full relative animate-fade-in-3d">
          <button @click="showModal = false" class="absolute top-3 right-4 text-2xl text-yellow-700 hover:text-red-500 font-bold">&times;</button>
          <h2 class="text-xl font-bold mb-4 text-yellow-800 flex items-center gap-2"><i class="fas fa-list-alt"></i> Detail Penjualan: {{ modalCustomer }}</h2>
          <div v-if="loadingDetail" class="text-center py-8 text-yellow-600"><i class="fa fa-spinner fa-spin mr-2"></i>Loading...</div>
          <div v-else-if="Object.keys(detailData).length === 0" class="text-center py-8 text-gray-400">Tidak ada data detail.</div>
          <div v-else class="space-y-6 max-h-[60vh] overflow-y-auto">
            <div v-for="(items, cat) in detailData" :key="cat" class="rounded-xl shadow bg-white/80 p-4 border-l-8 border-yellow-400">
              <div class="font-bold text-yellow-700 text-lg mb-2 flex items-center gap-2"><i class="fa fa-folder-open"></i> {{ cat }}</div>
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-yellow-700">
                    <th class="text-left py-1">Item</th>
                    <th class="text-right py-1">Qty</th>
                    <th class="text-right py-1">Unit</th>
                    <th class="text-right py-1">Harga</th>
                    <th class="text-right py-1">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in items" :key="item.item_name">
                    <td class="py-1">{{ item.item_name }}</td>
                    <td class="py-1 text-right">{{ item.received_qty }}</td>
                    <td class="py-1 text-right">{{ item.unit }}</td>
                    <td class="py-1 text-right">{{ formatRupiah(item.price) }}</td>
                    <td class="py-1 text-right font-bold">{{ formatRupiah(item.subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  report: Array,
  filters: Object
});
const tanggal = ref(props.filters?.tanggal || '');
const search = ref('');
const perPage = ref(25);
const page = ref(1);

const filteredReport = computed(() => {
  let data = props.report;
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
watch([perPage, search], () => { page.value = 1; });
const loading = ref(false);

onMounted(() => {
  router.on('start', () => { loading.value = true; });
  router.on('finish', () => { loading.value = false; });
});
onUnmounted(() => {
  router.off('start');
  router.off('finish');
});

function reloadData() {
  router.get('/report-rekap-fj', { tanggal: tanggal.value }, { preserveState: true, preserveScroll: true });
}

function totalCol(key) {
  return paginatedReport.value.reduce((sum, row) => sum + (Number(row[key]) || 0), 0);
}
function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value || 0);
}

const showModal = ref(false);
const modalCustomer = ref('');
const detailData = ref({});
const loadingDetail = ref(false);

async function showDetail(customer) {
  showModal.value = true;
  modalCustomer.value = customer;
  detailData.value = {};
  loadingDetail.value = true;
  try {
    const { data } = await axios.post(route('report.sales-pivot-outlet-detail'), {
      outlet: customer,
      tanggal: tanggal.value
    });
    detailData.value = data;
  } catch (e) {
    detailData.value = {};
  } finally {
    loadingDetail.value = false;
  }
}
</script>

<style scoped>
@keyframes fade-in-3d {
  from { opacity: 0; transform: scale(0.85) rotateX(10deg); }
  to { opacity: 1; transform: scale(1) rotateX(0); }
}
.animate-fade-in-3d {
  animation: fade-in-3d 0.5s cubic-bezier(.4,2,.3,1) both;
}
</style> 