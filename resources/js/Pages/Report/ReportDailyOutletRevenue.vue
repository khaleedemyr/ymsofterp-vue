<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-chart-line"></i> Daily Outlet Revenue Report
      </h1>
      
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 items-end">
        <div v-if="user.id_outlet == 1">
          <label class="block text-sm font-medium mb-1">Outlet</label>
          <select v-model="filters.outlet" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" required>
            <option value="">Pilih Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.qr_code">{{ outlet.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Bulan</label>
          <select v-model="filters.month" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" required>
            <option value="">Pilih Bulan</option>
            <option value="1">Januari</option>
            <option value="2">Februari</option>
            <option value="3">Maret</option>
            <option value="4">April</option>
            <option value="5">Mei</option>
            <option value="6">Juni</option>
            <option value="7">Juli</option>
            <option value="8">Agustus</option>
            <option value="9">September</option>
            <option value="10">Oktober</option>
            <option value="11">November</option>
            <option value="12">Desember</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tahun</label>
          <select v-model="filters.year" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" required>
            <option value="">Pilih Tahun</option>
            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>
        <div class="flex items-end h-full">
          <button @click="fetchReport" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">Tampilkan</button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-10">
        <span class="text-gray-500">Loading...</span>
      </div>

      <div v-else-if="showReport">
        <h2 class="font-semibold mb-2 text-lg text-gray-700">Daily Outlet Revenue Report - {{ getMonthName(filters.month) }} {{ filters.year }}</h2>
        <div class="overflow-x-auto mb-8">
          <table class="min-w-full rounded-2xl overflow-hidden shadow-lg">
            <thead>
              <tr class="bg-[#2563eb] text-white font-bold text-sm">
                <th class="px-3 py-3 text-center border-r border-blue-400">DATE</th>
                <th class="px-3 py-3 text-center border-r border-blue-400">DAY</th>
                <th class="px-3 py-3 text-center border-r border-blue-400" colspan="4">LUNCH</th>
                <th class="px-3 py-3 text-center border-r border-blue-400" colspan="4">DINNER</th>
                <th class="px-3 py-3 text-center" colspan="4">TOTAL FB REVENUE</th>
              </tr>
              <tr class="bg-[#1e40af] text-white font-bold text-xs">
                <th class="px-3 py-2 text-center border-r border-blue-400"></th>
                <th class="px-3 py-2 text-center border-r border-blue-400"></th>
                <th class="px-3 py-2 text-center border-r border-blue-400">COVER</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">REVENUE</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">A/C</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">DISC</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">COVER</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">REVENUE</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">A/C</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">DISC</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">COVER</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">REVENUE</th>
                <th class="px-3 py-2 text-center border-r border-blue-400">A/C</th>
                <th class="px-3 py-2 text-center">DISC</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(dayData, date) in report.daily_data" :key="date">
                <tr :class="getRowClass(dayData.day_name)" class="border-b last:border-b-0">
                  <td class="px-3 py-3 text-center font-semibold text-gray-800 border-r border-gray-200">{{ formatDate(date) }}</td>
                  <td class="px-3 py-3 text-center font-semibold text-gray-800 border-r border-gray-200">{{ dayData.day_name }}</td>
                  
                  <!-- Lunch Data -->
                  <td class="px-3 py-3 text-center border-r border-gray-200">{{ dayData.lunch.cover || 0 }}</td>
                  <td class="px-3 py-3 text-right border-r border-gray-200">{{ formatNumber(dayData.lunch.revenue || 0) }}</td>
                  <td class="px-3 py-3 text-right border-r border-gray-200">{{ formatNumber(dayData.lunch.avg_check || 0) }}</td>
                  <td class="px-3 py-3 text-right border-r border-gray-200">{{ formatNumber(dayData.lunch.disc || 0) }}</td>
                  
                  <!-- Dinner Data -->
                  <td class="px-3 py-3 text-center border-r border-gray-200">{{ dayData.dinner.cover || 0 }}</td>
                  <td class="px-3 py-3 text-right border-r border-gray-200">{{ formatNumber(dayData.dinner.revenue || 0) }}</td>
                  <td class="px-3 py-3 text-right border-r border-gray-200">{{ formatNumber(dayData.dinner.avg_check || 0) }}</td>
                  <td class="px-3 py-3 text-right border-r border-gray-200">{{ formatNumber(dayData.dinner.disc || 0) }}</td>
                  
                  <!-- Total FB Revenue -->
                  <td class="px-3 py-3 text-center border-r border-gray-200 font-semibold">{{ dayData.total.cover || 0 }}</td>
                  <td class="px-3 py-3 text-right border-r border-gray-200 font-semibold">{{ formatNumber(dayData.total.revenue || 0) }}</td>
                  <td class="px-3 py-3 text-right border-r border-gray-200 font-semibold">{{ formatNumber(dayData.total.avg_check || 0) }}</td>
                  <td class="px-3 py-3 text-right font-semibold">{{ formatNumber(dayData.total.disc || 0) }}</td>
                </tr>
              </template>
              
              <!-- Month to Date Summary -->
              <tr class="bg-[#1e3a8a] text-white font-bold">
                <td class="px-3 py-3 text-center border-r border-blue-400" colspan="2">MONTH TO DATE</td>
                <td class="px-3 py-3 text-center border-r border-blue-400">{{ report.summary.lunch.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-blue-400">{{ formatNumber(report.summary.lunch.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-blue-400">{{ formatNumber(report.summary.lunch.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-blue-400">{{ formatNumber(report.summary.lunch.disc || 0) }}</td>
                <td class="px-3 py-3 text-center border-r border-blue-400">{{ report.summary.dinner.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-blue-400">{{ formatNumber(report.summary.dinner.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-blue-400">{{ formatNumber(report.summary.dinner.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-blue-400">{{ formatNumber(report.summary.dinner.disc || 0) }}</td>
                <td class="px-3 py-3 text-center border-r border-blue-400">{{ report.summary.total.cover || 0 }}</td>
                <td class="px-3 py-3 text-right border-r border-blue-400">{{ formatNumber(report.summary.total.revenue || 0) }}</td>
                <td class="px-3 py-3 text-right border-r border-blue-400">{{ formatNumber(report.summary.total.avg_check || 0) }}</td>
                <td class="px-3 py-3 text-right">{{ formatNumber(report.summary.total.disc || 0) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const filters = reactive({
  outlet: '',
  month: '',
  year: '',
});

const outlets = ref([]);
const report = reactive({
  summary: {
    lunch: { cover: 0, revenue: 0, avg_check: 0, disc: 0 },
    dinner: { cover: 0, revenue: 0, avg_check: 0, disc: 0 },
    total: { cover: 0, revenue: 0, avg_check: 0, disc: 0 }
  },
  daily_data: {},
});
const loading = ref(false);
const showReport = ref(false);
const user = usePage().props.auth?.user || {};

// Generate available years (current year + 5 years back)
const currentYear = new Date().getFullYear();
const availableYears = computed(() => {
  const years = [];
  for (let i = currentYear; i >= currentYear - 5; i--) {
    years.push(i);
  }
  return years;
});

const fetchOutlets = async () => {
  const res = await axios.get('/api/outlets/report');
  outlets.value = res.data.outlets || [];
};

const fetchMyOutletQr = async () => {
  const res = await axios.get('/api/my-outlet-qr');
  if (res.data.qr_code) {
    filters.outlet = res.data.qr_code;
  }
};

const fetchReport = async () => {
  if (!filters.month || !filters.year) {
    alert('Pilih bulan dan tahun terlebih dahulu');
    return;
  }
  
  if (user.id_outlet == 1 && !filters.outlet) {
    alert('Pilih outlet terlebih dahulu');
    return;
  }

  loading.value = true;
  try {
    const params = {
      month: filters.month,
      year: filters.year,
      ...(user.id_outlet == 1 && { outlet: filters.outlet })
    };
    
    const res = await axios.get('/api/report/daily-outlet-revenue', { params });
    report.daily_data = res.data.daily_data || {};
    report.summary = res.data.summary || {
      lunch: { cover: 0, revenue: 0, avg_check: 0, disc: 0 },
      dinner: { cover: 0, revenue: 0, avg_check: 0, disc: 0 },
      total: { cover: 0, revenue: 0, avg_check: 0, disc: 0 }
    };
    showReport.value = true;
  } catch (error) {
    console.error('Error fetching report:', error);
    alert('Terjadi kesalahan saat mengambil data report');
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateStr) => {
  const date = new Date(dateStr);
  const day = date.getDate();
  const month = date.toLocaleDateString('en-US', { month: 'short' });
  const year = date.getFullYear().toString().slice(-2);
  return `${day}-${month}-${year}`;
};

const formatNumber = (num) => {
  if (typeof num !== 'number') return '0';
  return num.toLocaleString('id-ID');
};

const getMonthName = (month) => {
  const months = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];
  return months[parseInt(month)] || '';
};

const getRowClass = (dayName) => {
  const lowerDay = dayName.toLowerCase();
  if (lowerDay === 'sabtu' || lowerDay === 'minggu') {
    return 'bg-orange-100 hover:bg-orange-200';
  }
  return 'bg-white hover:bg-blue-50';
};

onMounted(async () => {
  // Set default month and year to current
  const now = new Date();
  filters.month = (now.getMonth() + 1).toString();
  filters.year = now.getFullYear().toString();
  
  if (user.id_outlet == 1) {
    await fetchOutlets();
  } else {
    await fetchMyOutletQr();
  }
});
</script>

<style scoped>
/* Additional styles if needed */
</style> 