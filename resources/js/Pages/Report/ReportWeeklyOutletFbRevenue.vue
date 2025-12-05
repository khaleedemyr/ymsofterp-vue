<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-chart-line"></i> Weekly Outlet FB Revenue Report
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
        <!-- Header Information -->
        <div class="bg-blue-50 p-6 rounded-lg mb-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <h3 class="font-bold text-blue-800 mb-2">Outlet</h3>
              <p class="text-lg">{{ report.outlet_name }}</p>
            </div>
            <div>
              <h3 class="font-bold text-blue-800 mb-2">Monthly Budget</h3>
              <div v-if="report.monthly_budget === null || report.monthly_budget === undefined" class="flex items-center gap-2">
                <input 
                  v-model="budgetInput" 
                  type="number" 
                  placeholder="Input budget bulanan"
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
                <button 
                  @click="saveBudget" 
                  class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition"
                >
                  Simpan
                </button>
              </div>
              <p v-else class="text-lg font-semibold">{{ formatCurrency(report.monthly_budget) }}</p>
            </div>
            <div>
              <h3 class="font-bold text-blue-800 mb-2">MTD Performance</h3>
              <p class="text-lg font-semibold" :class="getPerformanceColor(report.mtd_performance)">
                {{ report.mtd_performance }}%
              </p>
            </div>
            <div>
              <h3 class="font-bold text-blue-800 mb-2">Periode</h3>
              <p class="text-lg">{{ getMonthName(filters.month) }} {{ filters.year }}</p>
            </div>
          </div>
          
          <!-- Day Counts -->
          <div class="mt-4 grid grid-cols-2 md:grid-cols-6 gap-4 text-sm">
            <div>
              <span class="font-semibold">No. of Days:</span> {{ report.day_counts.total_days }}
            </div>
            <div>
              <span class="font-semibold">No. of Weekdays:</span> {{ report.day_counts.weekdays }}
            </div>
            <div>
              <span class="font-semibold">No. of Weekends:</span> {{ report.day_counts.weekends }}
            </div>
            <div>
              <span class="font-semibold">Day to Date:</span> {{ report.day_counts.days_to_date }}
            </div>
            <div>
              <span class="font-semibold">Weekdays to Date:</span> {{ report.day_counts.weekdays_to_date }}
            </div>
            <div>
              <span class="font-semibold">Weekends to Date:</span> {{ report.day_counts.weekends_to_date }}
            </div>
          </div>
        </div>

        <!-- Weekly Tables -->
        <div v-for="(weekData, weekNum) in report.weekly_data" :key="weekNum" class="mb-8">
          <h3 class="text-xl font-bold mb-4 text-blue-800">WEEK {{ weekNum }}</h3>
          <div class="overflow-x-auto mb-4">
            <table class="min-w-full rounded-2xl overflow-hidden shadow-lg">
              <thead>
                <tr class="bg-[#2563eb] text-white font-bold text-sm">
                  <th class="px-3 py-3 text-center border-r border-blue-400" rowspan="2">WEEK</th>
                  <th class="px-3 py-3 text-center border-r border-blue-400">DATE</th>
                  <th class="px-3 py-3 text-center border-r border-blue-400">DAY</th>
                  <th class="px-3 py-3 text-center border-r border-blue-400">FB REVENUE</th>
                  <th class="px-3 py-3 text-center border-r border-blue-400">COVER</th>
                  <th class="px-3 py-3 text-center">A/C</th>
                </tr>
              </thead>
                            <tbody>
                <tr 
                  v-for="(day, index) in weekData" 
                  :key="day.date"
                  :class="getRowClass(day)"
                  class="border-b last:border-b-0"
                >
                  <td v-if="index === 0" :rowspan="weekData.length" class="px-3 py-3 text-center font-semibold text-gray-800 border-r border-gray-200 align-middle">{{ day.week }}</td>
                  <td class="px-3 py-3 text-center font-semibold text-gray-800 border-r border-gray-200">{{ formatDate(day.date) }}</td>
                  <td class="px-3 py-3 text-center font-semibold text-gray-800 border-r border-gray-200">
                    {{ day.day }}
                    <div v-if="day.holiday_description" class="text-xs text-red-600 font-normal mt-1">
                      {{ day.holiday_description }}
                    </div>
                  </td>
                  <td class="px-3 py-3 text-right border-r border-gray-200">{{ formatNumber(day.revenue) }}</td>
                  <td class="px-3 py-3 text-center border-r border-gray-200">{{ day.cover }}</td>
                  <td class="px-3 py-3 text-right">{{ formatNumber(day.avg_check) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Week Summary -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <span class="font-semibold">Total FB Revenue:</span> {{ formatCurrency(report.weekly_summaries[weekNum].total_revenue) }}
              </div>
              <div>
                <span class="font-semibold">Average FB Revenue per Day:</span> {{ formatCurrency(report.weekly_summaries[weekNum].avg_revenue_per_day) }}
              </div>
              <div>
                <span class="font-semibold">Weekdays FB Revenue:</span> {{ formatCurrency(report.weekly_summaries[weekNum].weekdays_revenue) }}
              </div>
              <div>
                <span class="font-semibold">Average FB Revenue per Day (Weekdays):</span> {{ formatCurrency(report.weekly_summaries[weekNum].avg_weekdays_revenue) }}
              </div>
              <div>
                <span class="font-semibold">Weekends FB Revenue:</span> {{ formatCurrency(report.weekly_summaries[weekNum].weekends_revenue) }}
              </div>
              <div>
                <span class="font-semibold">Average FB Revenue per Day (Weekends):</span> {{ formatCurrency(report.weekly_summaries[weekNum].avg_weekends_revenue) }}
              </div>
            </div>
          </div>
        </div>

        <!-- MTD Summary -->
        <div class="bg-[#1e3a8a] text-white p-6 rounded-lg">
          <h3 class="text-xl font-bold mb-4">MTD Summary</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <span class="font-semibold">Total MTD FB Revenue:</span> {{ formatCurrency(report.monthly_summary.total_revenue) }}
            </div>
            <div>
              <span class="font-semibold">Average FB Revenue / Day:</span> {{ formatCurrency(report.monthly_summary.total_revenue / report.day_counts.days_to_date || 0) }}
            </div>
            <div>
              <span class="font-semibold">Weekdays FB Revenue:</span> {{ formatCurrency(report.monthly_summary.weekdays_revenue) }}
            </div>
            <div>
              <span class="font-semibold">Average FB Revenue / Day (Weekdays):</span> {{ formatCurrency(report.monthly_summary.weekdays_revenue / report.day_counts.weekdays_to_date || 0) }}
            </div>
            <div>
              <span class="font-semibold">Weekends FB Revenue:</span> {{ formatCurrency(report.monthly_summary.weekends_revenue) }}
            </div>
            <div>
              <span class="font-semibold">Average FB Revenue / Day (Weekends):</span> {{ formatCurrency(report.monthly_summary.weekends_revenue / report.day_counts.weekends_to_date || 0) }}
            </div>
          </div>
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
  weekly_data: {},
  weekly_summaries: {},
  monthly_summary: {},
  monthly_budget: null,
  mtd_performance: 0,
  day_counts: {},
  outlet_name: ''
});
const loading = ref(false);
const showReport = ref(false);
const user = usePage().props.auth?.user || {};
const budgetInput = ref('');

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
    
    const res = await axios.get('/api/report/weekly-outlet-fb-revenue', { params });
    Object.assign(report, res.data);
    showReport.value = true;
  } catch (error) {
    console.error('Error fetching report:', error);
    alert('Terjadi kesalahan saat mengambil data report');
  } finally {
    loading.value = false;
  }
};

const saveBudget = async () => {
  if (!budgetInput.value || budgetInput.value <= 0) {
    alert('Masukkan budget yang valid');
    return;
  }

  try {
    await axios.post('/api/report/weekly-outlet-fb-revenue/budget', {
      outlet: filters.outlet,
      month: filters.month,
      year: filters.year,
      budget_amount: budgetInput.value
    });
    
    // Refresh report to show updated budget
    await fetchReport();
    budgetInput.value = '';
  } catch (error) {
    console.error('Error saving budget:', error);
    alert('Terjadi kesalahan saat menyimpan budget');
  }
};

const formatDate = (dateStr) => {
  const date = new Date(dateStr);
  return date.getDate();
};

const formatNumber = (num) => {
  if (typeof num !== 'number') return '0';
  return num.toLocaleString('id-ID');
};

const formatCurrency = (num) => {
  if (typeof num !== 'number') return 'Rp 0';
  return `Rp ${num.toLocaleString('id-ID')}`;
};

const getMonthName = (month) => {
  const months = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];
  return months[parseInt(month)] || '';
};

const getRowClass = (day) => {
  if (day.is_holiday) {
    return 'bg-red-100 hover:bg-red-200';
  }
  if (day.is_weekend) {
    return 'bg-orange-100 hover:bg-orange-200';
  }
  return 'bg-white hover:bg-blue-50';
};

const getPerformanceColor = (performance) => {
  if (performance >= 100) return 'text-green-600';
  if (performance >= 80) return 'text-yellow-600';
  return 'text-red-600';
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