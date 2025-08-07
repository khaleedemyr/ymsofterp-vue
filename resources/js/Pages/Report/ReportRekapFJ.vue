<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
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
        <input v-model="search" type="text" placeholder="Cari item/unit..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
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
      </div>
      <div v-if="!from || !to" class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500 font-bold">
        Silakan pilih rentang tanggal terlebih dahulu
      </div>
      <div v-else-if="!dataLoaded" class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500 font-bold">
        Silakan klik "Load Data" untuk menampilkan laporan
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
      <div v-if="from && to && dataLoaded && filteredItems.length" class="flex justify-between items-center mt-4">
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
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const dataLoaded = ref(false);

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

function prevPage() {
  if (page.value > 1) page.value--;
}
function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
watch([perPage, search], () => { page.value = 1; });
watch([from, to], ([newFrom, newTo], [oldFrom, oldTo]) => { 
  // Reset dataLoaded when date range changes, unless it's the initial load
  if (oldFrom !== undefined || oldTo !== undefined) {
    dataLoaded.value = false; 
  }
  page.value = 1; 
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
function formatQty(val) {
  if (val == null) return '';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script> 