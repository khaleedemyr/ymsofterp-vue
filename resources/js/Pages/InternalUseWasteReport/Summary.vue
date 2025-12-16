<template>
  <AppLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">
          <i class="fa-solid fa-chart-pie mr-2"></i>
          Summary Report RnD, BM, WM
        </h1>
        <div class="flex gap-2">
          <button 
            @click="exportToExcel" 
            :disabled="loading || exporting || !canExport"
            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i v-if="exporting" class="fa-solid fa-spinner fa-spin mr-1"></i>
            <i v-else class="fa-solid fa-file-excel mr-1"></i> 
            {{ exporting ? 'Exporting...' : 'Export Excel' }}
          </button>
          <button 
            @click="goBack"
            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition"
          >
            <i class="fa-solid fa-arrow-left mr-1"></i>
            Kembali
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-xl p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
            <input
              type="date"
              v-model="filters.start_date"
              @change="loadSummary"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
            <input
              type="date"
              v-model="filters.end_date"
              @change="loadSummary"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>
      </div>

      <!-- Summary Table -->
      <div v-if="loading" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-blue-500">
          <i class="fa-solid fa-spinner fa-spin text-4xl mb-4"></i>
          <p class="text-lg font-medium">Memuat data...</p>
        </div>
      </div>

      <div v-else-if="summaryData && summaryData.length > 0" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total MAC</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(item, index) in summaryData" :key="item.outlet_id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ index + 1 }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.outlet_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-blue-600">{{ formatNumber(item.total_mac) }}</td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50">
            <tr>
              <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">Grand Total:</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-right text-blue-600">{{ formatNumber(grandTotal) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div v-else-if="!loading" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-gray-500">
          <i class="fa-solid fa-inbox text-4xl mb-4"></i>
          <p class="text-lg font-medium">Tidak ada data</p>
          <p class="text-sm">Silakan pilih periode tanggal untuk melihat summary</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  summaryData: {
    type: Array,
    default: () => []
  },
  filters: {
    type: Object,
    default: () => ({
      start_date: '',
      end_date: ''
    })
  }
});

const loading = ref(false);
const exporting = ref(false);
const filters = ref({
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || ''
});

const grandTotal = computed(() => {
  return props.summaryData.reduce((sum, item) => sum + (item.total_mac || 0), 0);
});

const canExport = computed(() => {
  return (filters.value.start_date && filters.value.end_date) || (props.filters.start_date && props.filters.end_date);
});

const formatNumber = (value) => {
  if (!value && value !== 0) return '0,00';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value);
};

const loadSummary = () => {
  if (!filters.value.start_date || !filters.value.end_date) {
    return;
  }
  
  loading.value = true;
  router.get('/internal-use-waste-report/summary', {
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
  }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
};

const exportToExcel = () => {
  if (!canExport.value) {
    return;
  }
  
  exporting.value = true;
  const params = new URLSearchParams({
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
  });
  
  window.location.href = '/internal-use-waste-report/summary/export?' + params.toString();
  
  // Reset exporting after a delay (in case download doesn't trigger)
  setTimeout(() => {
    exporting.value = false;
  }, 2000);
};

const goBack = () => {
  window.close();
};
</script>

