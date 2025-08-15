<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-4 px-0">
      <h1 class="text-2xl font-bold mb-6">Report Rekap FJ</h1>
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
        <button @click="exportToExcel" :disabled="!from || !to || !dataLoaded || !report.length" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
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
              <th class="px-4 py-2 border border-gray-300 text-right">Main Kitchen</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Main Store</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Chemical</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Stationary</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Marketing</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Line Total</th>
            </tr>
          </thead>
          <tbody>
            <!-- Outlet Group Header -->
            <tr v-if="groupedReport.outlets.length > 0" class="bg-blue-100 font-bold">
              <td colspan="7" class="px-4 py-2 border border-gray-300 text-center text-blue-800">
                <i class="fas fa-store mr-2"></i>OUTLET (is_outlet = 1)
              </td>
            </tr>
            <tr v-for="row in groupedReport.outlets" :key="'outlet-' + row.customer">
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
            
            <!-- Outlet Group Subtotal -->
            <tr v-if="groupedReport.outlets.length > 0" class="bg-blue-50 font-semibold">
              <td class="px-4 py-2 border border-gray-300 text-right">SUBTOTAL OUTLET</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('main_kitchen', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('main_store', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('chemical', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('stationary', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('marketing', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('line_total', groupedReport.outlets)) }}</td>
            </tr>
            
            <!-- Non-Outlet Group Header -->
            <tr v-if="groupedReport.nonOutlets.length > 0" class="bg-green-100 font-bold">
              <td colspan="7" class="px-4 py-2 border border-gray-300 text-center text-green-800">
                <i class="fas fa-building mr-2"></i>NON-OUTLET (is_outlet = 0)
              </td>
            </tr>
            <tr v-for="row in groupedReport.nonOutlets" :key="'nonoutlet-' + row.customer">
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
            
            <!-- Non-Outlet Group Subtotal -->
            <tr v-if="groupedReport.nonOutlets.length > 0" class="bg-green-50 font-semibold">
              <td class="px-4 py-2 border border-gray-300 text-right">SUBTOTAL NON-OUTLET</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('main_kitchen', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('main_store', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('chemical', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('stationary', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('marketing', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('line_total', groupedReport.nonOutlets)) }}</td>
            </tr>
            
            <!-- No Data Message -->
            <tr v-if="!groupedReport.outlets.length && !groupedReport.nonOutlets.length">
              <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data.</td>
            </tr>
          </tbody>
          <tfoot v-if="filteredReport.length">
            <tr class="bg-gray-100 font-bold">
              <td class="px-4 py-2 border border-gray-300 text-right">TOTAL KESELURUHAN</td>
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
      <div v-if="from && to && dataLoaded && filteredReport.length" class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-600">
          Total: {{ filteredReport.length }} data ({{ groupedReport.outlets.length }} Outlet, {{ groupedReport.nonOutlets.length }} Non-Outlet)
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
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const search = ref('');
const dataLoaded = ref(false);

// Computed untuk menampilkan info rentang tanggal
const dateRangeInfo = computed(() => {
  if (!from.value || !to.value) return '';
  
  const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  };
  
  return `${formatDate(from.value)} - ${formatDate(to.value)}`;
});

const filteredReport = computed(() => {
  let data = props.report;
  if (search.value) {
    const s = search.value.toLowerCase();
    data = data.filter(row => row.customer && row.customer.toLowerCase().includes(s));
  }
  return data;
});

// Computed untuk mengelompokkan data berdasarkan is_outlet
const groupedReport = computed(() => {
  const groups = {
    outlets: [],
    nonOutlets: []
  };
  
  filteredReport.value.forEach(row => {
    if (row.is_outlet == 1) {
      groups.outlets.push(row);
    } else {
      groups.nonOutlets.push(row);
    }
  });
  
  return groups;
});

watch([from, to], ([newFrom, newTo], [oldFrom, oldTo]) => { 
  // Reset dataLoaded when date range changes, unless it's the initial load
  if (oldFrom !== undefined || oldTo !== undefined) {
    dataLoaded.value = false; 
  }
});

function setTodayRange() {
  const today = new Date();
  const todayStr = today.toISOString().split('T')[0];
  from.value = todayStr;
  to.value = todayStr;
}

function setThisWeekRange() {
  const today = new Date();
  const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, etc.
  const monday = new Date(today);
  monday.setDate(today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1)); // Monday
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6); // Sunday
  
  from.value = monday.toISOString().split('T')[0];
  to.value = sunday.toISOString().split('T')[0];
}

function setThisMonthRange() {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  
  from.value = firstDay.toISOString().split('T')[0];
  to.value = lastDay.toISOString().split('T')[0];
}

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
  // Validasi tanggal
  if (!from.value || !to.value) {
    alert('Silakan pilih rentang tanggal terlebih dahulu');
    return;
  }
  
  if (new Date(from.value) > new Date(to.value)) {
    alert('Tanggal "Sampai" tidak boleh lebih kecil dari tanggal "Dari"');
    return;
  }
  
  dataLoaded.value = true;
  router.get('/report-rekap-fj', { from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });
}

function totalCol(key) {
  return filteredReport.value.reduce((sum, row) => sum + (Number(row[key]) || 0), 0);
}

function totalColGroup(key, groupData) {
  return groupData.reduce((sum, row) => sum + (Number(row[key]) || 0), 0);
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
      from: from.value,
      to: to.value
    });
    detailData.value = data;
  } catch (e) {
    detailData.value = {};
  } finally {
    loadingDetail.value = false;
  }
}

async function exportToExcel() {
  if (!from.value || !to.value) {
    alert('Silakan pilih rentang tanggal terlebih dahulu');
    return;
  }
  
  if (!dataLoaded.value) {
    alert('Silakan load data terlebih dahulu');
    return;
  }
  
  try {
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="mr-2"><i class="fas fa-spinner fa-spin"></i></span>Exporting...';
    button.disabled = true;
    
    // Use axios to download the file
    const response = await axios.get(route('report.rekap-fj.export'), {
      params: { from: from.value, to: to.value },
      responseType: 'blob'
    });
    
    // Create blob and download
    const blob = new Blob([response.data], { 
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sales_pivot_special_${from.value}_to_${to.value}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    // Reset button
    button.innerHTML = originalText;
    button.disabled = false;
    
  } catch (error) {
    console.error('Export error:', error);
    alert('Terjadi kesalahan saat export. Silakan coba lagi.');
    
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