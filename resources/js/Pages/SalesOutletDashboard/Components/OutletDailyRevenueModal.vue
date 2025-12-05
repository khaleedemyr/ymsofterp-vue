<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  isOpen: Boolean,
  selectedOutlet: Object,
  dateFrom: String,
  dateTo: String
})

const emit = defineEmits(['close'])

const loading = ref(false)
const dailyRevenueData = ref(null)
const error = ref(null)

// Watch for changes and fetch data
watch(() => [props.selectedOutlet, props.dateFrom, props.dateTo], ([outlet, from, to]) => {
  if (outlet && from && to && props.isOpen) {
    fetchDailyRevenueData(outlet.outlet_code, from, to)
  }
}, { immediate: true })

// Watch for modal open/close
watch(() => props.isOpen, (isOpen) => {
  if (isOpen && props.selectedOutlet && props.dateFrom && props.dateTo) {
    fetchDailyRevenueData(props.selectedOutlet.outlet_code, props.dateFrom, props.dateTo)
  } else if (!isOpen) {
    // Reset data when modal closes
    dailyRevenueData.value = null
    error.value = null
  }
})

async function fetchDailyRevenueData(outletCode, dateFrom, dateTo) {
  if (!outletCode || !dateFrom || !dateTo) return
  
  loading.value = true
  error.value = null
  
  try {
    const response = await axios.get('/sales-outlet-dashboard/outlet-daily-revenue', {
      params: { 
        outlet_code: outletCode,
        date_from: dateFrom,
        date_to: dateTo
      }
    })
    dailyRevenueData.value = response.data
  } catch (err) {
    error.value = err.response?.data?.error || 'Gagal mengambil data revenue harian'
    console.error('Error fetching daily revenue data:', err)
  } finally {
    loading.value = false
  }
}

function formatNumber(number) {
  return number.toLocaleString('id-ID')
}

function closeModal() {
  emit('close')
}

// Chart data for daily revenue
const chartSeries = computed(() => {
  if (!dailyRevenueData.value?.daily_data) return []
  
  return [{
    name: 'Revenue',
    data: dailyRevenueData.value.daily_data.map(item => item.revenue)
  }]
})

const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 300,
    toolbar: { show: true },
    animations: { enabled: true, easing: 'easeinout', speed: 800 }
  },
  stroke: { 
    width: 3, 
    curve: 'smooth' 
  },
  markers: { 
    size: 5, 
    colors: ['#fff'], 
    strokeColors: ['#3B82F6'], 
    strokeWidth: 3, 
    hover: { size: 8 } 
  },
  xaxis: {
    categories: dailyRevenueData.value?.daily_data?.map(item => {
      return new Date(item.date).toLocaleDateString('id-ID', { 
        day: '2-digit', 
        month: 'short' 
      })
    }) || [],
    title: { text: 'Tanggal' },
    labels: { 
      style: { fontWeight: 600 },
      rotate: -45
    }
  },
  yaxis: {
    title: { text: 'Revenue (Rp)' },
    labels: {
      style: { fontWeight: 600 },
      formatter: function(value) {
        return 'Rp ' + value.toLocaleString('id-ID')
      }
    }
  },
  colors: ['#3B82F6'],
  grid: {
    borderColor: '#e5e7eb',
    strokeDashArray: 4
  },
  tooltip: {
    y: {
      formatter: function(value) {
        return 'Rp ' + value.toLocaleString('id-ID')
      }
    }
  },
  dataLabels: {
    enabled: false
  }
}))
</script>

<template>
  <!-- Modal -->
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
        <!-- Header -->
        <div class="bg-white px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">
                Revenue Harian Outlet
              </h3>
              <p v-if="dailyRevenueData" class="text-sm text-gray-600 mt-1">
                {{ dailyRevenueData.outlet?.outlet_name }} - {{ dailyRevenueData.date_range?.from_formatted }} s/d {{ dailyRevenueData.date_range?.to_formatted }}
              </p>
            </div>
            <button
              @click="closeModal"
              class="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="bg-white px-6 py-4">
          <!-- Loading State -->
          <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Memuat data revenue harian...</p>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="text-center py-8">
            <div class="text-red-500 mb-4">
              <i class="fa-solid fa-exclamation-triangle text-4xl"></i>
            </div>
            <p class="text-red-600">{{ error }}</p>
          </div>

          <!-- Data Content -->
          <div v-else-if="dailyRevenueData">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex items-center">
                  <div class="bg-blue-100 p-2 rounded-lg">
                    <i class="fa-solid fa-calendar-days text-blue-600"></i>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-blue-600 font-medium">Total Hari</p>
                    <p class="text-2xl font-bold text-blue-800">{{ dailyRevenueData.summary.total_days }}</p>
                  </div>
                </div>
              </div>

              <div class="bg-green-50 rounded-lg p-4">
                <div class="flex items-center">
                  <div class="bg-green-100 p-2 rounded-lg">
                    <i class="fa-solid fa-money-bill-wave text-green-600"></i>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-green-600 font-medium">Total Revenue</p>
                    <p class="text-lg font-bold text-green-800">{{ dailyRevenueData.summary.total_revenue_formatted }}</p>
                  </div>
                </div>
              </div>

              <div class="bg-orange-50 rounded-lg p-4">
                <div class="flex items-center">
                  <div class="bg-orange-100 p-2 rounded-lg">
                    <i class="fa-solid fa-receipt text-orange-600"></i>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-orange-600 font-medium">Total Orders</p>
                    <p class="text-2xl font-bold text-orange-800">{{ formatNumber(dailyRevenueData.summary.total_orders) }}</p>
                  </div>
                </div>
              </div>

              <div class="bg-purple-50 rounded-lg p-4">
                <div class="flex items-center">
                  <div class="bg-purple-100 p-2 rounded-lg">
                    <i class="fa-solid fa-chart-line text-purple-600"></i>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-purple-600 font-medium">Rata-rata Harian</p>
                    <p class="text-lg font-bold text-purple-800">{{ dailyRevenueData.summary.avg_daily_revenue_formatted }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Chart -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
              <h4 class="text-lg font-semibold text-gray-900 mb-4">Trend Revenue Harian</h4>
              <apexchart 
                v-if="chartSeries.length > 0" 
                type="line" 
                height="300" 
                :options="chartOptions" 
                :series="chartSeries" 
              />
            </div>

            <!-- Daily Details Table -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
              <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900">Detail per Hari</h4>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tanggal
                      </th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Orders
                      </th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Revenue
                      </th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Customers
                      </th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Avg Order Value
                      </th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Cover
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="day in dailyRevenueData.daily_data" :key="day.date" class="hover:bg-gray-50">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ day.date_formatted }}</div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                        {{ formatNumber(day.orders) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                        {{ day.revenue_formatted }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                        {{ formatNumber(day.customers) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                        {{ day.avg_order_value_formatted }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                        {{ day.cover_formatted }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Empty State -->
              <div v-if="dailyRevenueData.daily_data.length === 0" class="text-center py-8">
                <i class="fa-solid fa-calendar text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-500">Tidak ada data revenue untuk periode ini</p>
              </div>
            </div>
          </div>

          <!-- No Data State -->
          <div v-else class="text-center py-8">
            <i class="fa-solid fa-building text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-500">Pilih outlet untuk melihat revenue harian</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-3 border-t border-gray-200">
          <div class="flex justify-end">
            <button
              @click="closeModal"
              class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition-colors"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom styles if needed */
</style>
