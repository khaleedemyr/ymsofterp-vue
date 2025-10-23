<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-4 px-0">
      <h1 class="text-2xl font-bold mb-6">Report Penjualan per Outlet per Sub Kategori</h1>
      <div v-if="dateRangeInfo" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-center gap-2 text-blue-800">
          <i class="fa fa-calendar text-blue-600"></i>
          <span class="font-medium">Rentang Tanggal:</span>
          <span>{{ dateRangeInfo }}</span>
        </div>
      </div>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Dari:</label>
          <input v-model="from" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Sampai:</label>
          <input v-model="to" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        </div>
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
        <button @click="setTodayRange" class="inline-flex items-center px-3 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 mr-2">
          <span class="mr-2"><i class="fas fa-calendar-day"></i></span>
          Hari Ini
        </button>
        <button @click="setThisWeekRange" class="inline-flex items-center px-3 py-2 bg-purple-600 text-white rounded-md font-semibold hover:bg-purple-700 mr-2">
          <span class="mr-2"><i class="fas fa-calendar-week"></i></span>
          Minggu Ini
        </button>
        <button @click="setThisMonthRange" class="inline-flex items-center px-3 py-2 bg-orange-600 text-white rounded-md font-semibold hover:bg-orange-700 mr-2">
          <span class="mr-2"><i class="fas fa-calendar-alt"></i></span>
          Bulan Ini
        </button>
        <button @click="reloadData" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
          <span class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Load Data
        </button>
        <button @click="exportToExcel" :disabled="!from || !to || !dataLoaded || !allReport.length" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
          <span class="mr-2"><i class="fas fa-file-excel"></i></span>
          Export Excel
        </button>
      </div>
      <div v-if="!from || !to" class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500 font-bold">
        Silakan pilih rentang tanggal terlebih dahulu
      </div>
      <div v-else-if="!dataLoaded" class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500 font-bold">
        Silakan klik "Load Data" untuk menampilkan laporan
      </div>
      <div v-else class="bg-white rounded-xl shadow-lg overflow-x-auto relative">
        <div v-if="loading" class="absolute inset-0 bg-white/70 z-20 flex items-center justify-center">
          <svg class="animate-spin h-12 w-12 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
        </div>
        <table class="min-w-full border border-gray-300">
          <thead>
            <tr class="bg-yellow-300 text-gray-900">
              <th class="px-4 py-2 border border-gray-300">Customer</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Line Total</th>
              <th v-for="sc in subCategories" :key="sc.id" class="px-4 py-2 border border-gray-300 text-right">{{ sc.name }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!allReport.length">
              <td :colspan="2 + subCategories.length" class="text-center py-10 text-gray-400">Tidak ada data.</td>
            </tr>
            
            <!-- Outlet Group Header -->
            <tr v-if="report.outlets && report.outlets.length > 0" class="bg-blue-100 font-bold">
              <td :colspan="2 + subCategories.length" class="px-4 py-2 border border-gray-300 text-center text-blue-800">
                <i class="fas fa-store mr-2"></i>OUTLET (is_outlet = 1)
              </td>
            </tr>
            <tr v-for="row in report.outlets" :key="'outlet-' + row.customer">
              <td class="px-4 py-2 border border-gray-200 flex items-center gap-2">
                {{ row.customer }}
                <button @click="showDetail(row.customer)" class="ml-2 bg-gradient-to-br from-yellow-400 to-yellow-600 text-white rounded-full shadow-lg px-2 py-1 hover:scale-110 transition-all font-bold" title="Lihat Detail">
                  <i class="fas fa-search-plus"></i>
                </button>
              </td>
              <td class="px-4 py-2 border border-gray-200 text-right font-bold">{{ formatRupiah(row.line_total) }}</td>
              <td v-for="sc in subCategories" :key="sc.id" class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row[sc.name]) }}</td>
            </tr>
            
            <!-- Outlet Group Subtotal -->
            <tr v-if="report.outlets && report.outlets.length > 0" class="bg-blue-50 font-semibold">
              <td class="px-4 py-2 border border-gray-300 text-right">SUBTOTAL OUTLET</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('line_total', report.outlets)) }}</td>
              <td v-for="sc in subCategories" :key="sc.id" class="px-4 py-2 border border-gray-300 text-right">
                {{ formatRupiah(totalColGroup(sc.name, report.outlets)) }}
              </td>
            </tr>
            
            <!-- Non-Outlet Group Header -->
            <tr v-if="report.nonOutlets && report.nonOutlets.length > 0" class="bg-green-100 font-bold">
              <td :colspan="2 + subCategories.length" class="px-4 py-2 border border-gray-300 text-center text-green-800">
                <i class="fas fa-building mr-2"></i>NON-OUTLET (is_outlet != 1)
              </td>
            </tr>
            <tr v-for="row in report.nonOutlets" :key="'non-outlet-' + row.customer">
              <td class="px-4 py-2 border border-gray-200 flex items-center gap-2">
                {{ row.customer }}
                <button @click="showDetail(row.customer)" class="ml-2 bg-gradient-to-br from-yellow-400 to-yellow-600 text-white rounded-full shadow-lg px-2 py-1 hover:scale-110 transition-all font-bold" title="Lihat Detail">
                  <i class="fas fa-search-plus"></i>
                </button>
              </td>
              <td class="px-4 py-2 border border-gray-200 text-right font-bold">{{ formatRupiah(row.line_total) }}</td>
              <td v-for="sc in subCategories" :key="sc.id" class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row[sc.name]) }}</td>
            </tr>
            
            <!-- Non-Outlet Group Subtotal -->
            <tr v-if="report.nonOutlets && report.nonOutlets.length > 0" class="bg-green-50 font-semibold">
              <td class="px-4 py-2 border border-gray-300 text-right">SUBTOTAL NON-OUTLET</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('line_total', report.nonOutlets)) }}</td>
              <td v-for="sc in subCategories" :key="sc.id" class="px-4 py-2 border border-gray-300 text-right">
                {{ formatRupiah(totalColGroup(sc.name, report.nonOutlets)) }}
              </td>
            </tr>
          </tbody>
          <tfoot v-if="allReport.length">
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
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  subCategories: Array,
  report: Object,
  filters: Object
});
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const tanggal = ref(props.filters?.tanggal || ''); // Backward compatibility
const search = ref('');
const selectedOutlet = ref('');
const perPage = ref(25);
const page = ref(1);
const dataLoaded = ref(false);

// Date range info
const dateRangeInfo = computed(() => {
  if (from.value && to.value) {
    const fromDate = new Date(from.value).toLocaleDateString('id-ID');
    const toDate = new Date(to.value).toLocaleDateString('id-ID');
    return `${fromDate} - ${toDate}`;
  }
  return null;
});

// All outlets from both groups
const allOutlets = computed(() => {
  const outlets = [];
  if (props.report?.outlets) {
    outlets.push(...props.report.outlets.map(r => r.customer));
  }
  if (props.report?.nonOutlets) {
    outlets.push(...props.report.nonOutlets.map(r => r.customer));
  }
  return [...new Set(outlets)].sort();
});

// All report data combined
const allReport = computed(() => {
  const data = [];
  if (props.report?.outlets) {
    data.push(...props.report.outlets);
  }
  if (props.report?.nonOutlets) {
    data.push(...props.report.nonOutlets);
  }
  return data;
});

const filteredReport = computed(() => {
  let data = allReport.value;
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
const loading = ref(false);

// Quick date range functions
function setTodayRange() {
  const today = new Date();
  from.value = today.toISOString().split('T')[0];
  to.value = today.toISOString().split('T')[0];
}

function setThisWeekRange() {
  const today = new Date();
  const firstDay = new Date(today.setDate(today.getDate() - today.getDay()));
  const lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 6));
  from.value = firstDay.toISOString().split('T')[0];
  to.value = lastDay.toISOString().split('T')[0];
}

function setThisMonthRange() {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  from.value = firstDay.toISOString().split('T')[0];
  to.value = lastDay.toISOString().split('T')[0];
}

async function reloadData() {
  // Validasi tanggal
  if (!from.value || !to.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Silakan pilih rentang tanggal terlebih dahulu',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  if (new Date(from.value) > new Date(to.value)) {
    Swal.fire({
      icon: 'error',
      title: 'Kesalahan',
      text: 'Tanggal "Sampai" tidak boleh lebih kecil dari tanggal "Dari"',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  loading.value = true;
  dataLoaded.value = true;
  
  // Gunakan date range jika ada, fallback ke tanggal tunggal
  const params = {};
  if (from.value && to.value) {
    params.from = from.value;
    params.to = to.value;
  } else if (tanggal.value) {
    params.tanggal = tanggal.value;
  }
  
  await router.get('/report-sales-pivot-per-outlet-sub-category', params, { 
    preserveState: true, 
    preserveScroll: true, 
    onFinish: () => { loading.value = false; } 
  });
}

const totalLineTotal = computed(() =>
  allReport.value.reduce((sum, row) => sum + (Number(row.line_total) || 0), 0)
);
const totalPerSubCategory = computed(() => {
  const totals = {};
  props.subCategories.forEach(sc => {
    totals[sc.name] = allReport.value.reduce((sum, row) => sum + (Number(row[sc.name]) || 0), 0);
  });
  return totals;
});

// Function untuk menghitung total per group
function totalColGroup(column, group) {
  return group.reduce((sum, row) => sum + (Number(row[column]) || 0), 0);
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
    // Gunakan date range jika ada, fallback ke tanggal tunggal
    const params = {
      outlet: customer
    };
    
    if (from.value && to.value) {
      params.from = from.value;
      params.to = to.value;
    } else if (tanggal.value) {
      // Jika menggunakan tanggal tunggal, gunakan tanggal yang sama untuk from dan to
      params.from = tanggal.value;
      params.to = tanggal.value;
    } else {
      throw new Error('No date range or single date provided');
    }
    
    const { data } = await axios.post(route('report.sales-pivot-outlet-detail'), params);
    detailData.value = data;
  } catch (e) {
    console.error('Detail error:', e);
    detailData.value = {};
  } finally {
    loadingDetail.value = false;
  }
}

async function exportToExcel() {
  if (!from.value || !to.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Silakan pilih rentang tanggal terlebih dahulu',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  try {
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="mr-2"><i class="fas fa-spinner fa-spin"></i></span>Exporting...';
    button.disabled = true;
    
    // Use axios to download the file
    const response = await axios.get(route('report.sales-pivot-per-outlet-sub-category.export'), {
      params: { 
        from: from.value,
        to: to.value,
        tanggal: tanggal.value // Backward compatibility
      },
      responseType: 'blob'
    });
    
    // Create blob and download
    const blob = new Blob([response.data], { 
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sales_pivot_per_outlet_sub_category_${from.value}_to_${to.value}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    // Reset button
    button.innerHTML = originalText;
    button.disabled = false;
    
  } catch (error) {
    console.error('Export error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Kesalahan Export',
      text: 'Terjadi kesalahan saat export. Silakan coba lagi.',
      confirmButtonText: 'OK'
    });
    
    // Reset button
    const button = event.target;
    button.innerHTML = '<span class="mr-2"><i class="fas fa-file-excel"></i></span>Export Excel';
    button.disabled = false;
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