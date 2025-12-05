<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps({
  isOpen: Boolean,
  selectedOutlet: Object,
  selectedRegion: String,
  selectedMealPeriod: String,
  dateFrom: String,
  dateTo: String
})

const emit = defineEmits(['close'])

const loading = ref(false)
const lunchDinnerData = ref(null)
const error = ref(null)

// Watch for changes and fetch data
watch(() => [props.selectedOutlet, props.selectedMealPeriod, props.dateFrom, props.dateTo], ([outlet, mealPeriod, from, to]) => {
  if (outlet && mealPeriod && from && to && props.isOpen) {
    fetchLunchDinnerData(outlet.outlet_code, mealPeriod, from, to)
  }
}, { immediate: true })

// Watch for modal open/close
watch(() => props.isOpen, (isOpen) => {
  if (isOpen && props.selectedOutlet && props.selectedMealPeriod && props.dateFrom && props.dateTo) {
    fetchLunchDinnerData(props.selectedOutlet.outlet_code, props.selectedMealPeriod, props.dateFrom, props.dateTo)
  } else if (!isOpen) {
    // Reset data when modal closes
    lunchDinnerData.value = null
    error.value = null
  }
})

async function fetchLunchDinnerData(outletCode, mealPeriod, dateFrom, dateTo) {
  if (!outletCode || !mealPeriod || !dateFrom || !dateTo) return
  
  loading.value = true
  error.value = null
  
  try {
    const response = await axios.get('/sales-outlet-dashboard/outlet-lunch-dinner-detail', {
      params: { 
        outlet_code: outletCode,
        meal_period: mealPeriod,
        date_from: dateFrom,
        date_to: dateTo
      }
    })
    lunchDinnerData.value = response.data
  } catch (err) {
    console.error('Error fetching lunch/dinner data:', err)
    error.value = 'Gagal memuat data lunch/dinner'
    lunchDinnerData.value = null
  } finally {
    loading.value = false
  }
}

function closeModal() {
  emit('close')
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

function formatNumber(value) {
  return new Intl.NumberFormat('id-ID').format(value)
}

// Computed properties
const mealPeriodIcon = computed(() => {
  return props.selectedMealPeriod === 'Lunch' ? '🍽️' : '🍽️'
})

const mealPeriodColor = computed(() => {
  return props.selectedMealPeriod === 'Lunch' ? 'blue' : 'orange'
})

const mealPeriodBgColor = computed(() => {
  return props.selectedMealPeriod === 'Lunch' ? 'bg-blue-50' : 'bg-orange-50'
})

const mealPeriodTextColor = computed(() => {
  return props.selectedMealPeriod === 'Lunch' ? 'text-blue-800' : 'text-orange-800'
})

const mealPeriodBorderColor = computed(() => {
  return props.selectedMealPeriod === 'Lunch' ? 'border-blue-200' : 'border-orange-200'
})

// Chart data for daily revenue trend
const chartSeries = computed(() => {
  if (!lunchDinnerData.value?.daily_data) return []
  
  return [{
    name: 'Revenue',
    data: lunchDinnerData.value.daily_data.map(item => item.revenue)
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
    strokeColors: [mealPeriodColor.value === 'blue' ? '#3B82F6' : '#F59E0B'], 
    strokeWidth: 3, 
    hover: { size: 8 } 
  },
  xaxis: {
    categories: lunchDinnerData.value?.daily_data?.map(item => {
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
  colors: [mealPeriodColor.value === 'blue' ? '#3B82F6' : '#F59E0B'],
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

<script>
export default {
  components: {
    apexchart: VueApexCharts
  }
}
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
            <div class="flex items-center space-x-3">
              <div :class="['p-2 rounded-lg', mealPeriodBgColor]">
                <span class="text-2xl">{{ mealPeriodIcon }}</span>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">
                  Detail {{ selectedMealPeriod }} - {{ selectedOutlet?.outlet_name }}
                </h3>
                <p class="text-sm text-gray-600">{{ selectedRegion }}</p>
              </div>
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
            <p class="mt-2 text-gray-600">Memuat data...</p>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="text-center py-8">
            <div class="text-red-500 text-4xl mb-2">
              <i class="fa-solid fa-exclamation-triangle"></i>
            </div>
            <p class="text-red-600">{{ error }}</p>
          </div>

          <!-- Data Content -->
          <div v-else-if="lunchDinnerData" class="space-y-6">
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <!-- Total Revenue -->
              <div :class="['p-4 rounded-lg border-2', mealPeriodBorderColor, mealPeriodBgColor]">
                <div class="flex items-center justify-between">
                  <div>
                    <p :class="['text-sm font-medium', mealPeriodTextColor]">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900">
                      {{ formatCurrency(lunchDinnerData.summary?.total_revenue || 0) }}
                    </p>
                  </div>
                  <div :class="['p-2 rounded-lg', mealPeriodBgColor]">
                    <i class="fa-solid fa-money-bill-wave text-xl text-gray-600"></i>
                  </div>
                </div>
              </div>

              <!-- Total Orders -->
              <div class="p-4 rounded-lg border border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-medium text-gray-600">Total Orders</p>
                    <p class="text-2xl font-bold text-gray-900">
                      {{ formatNumber(lunchDinnerData.summary?.total_orders || 0) }}
                    </p>
                  </div>
                  <div class="p-2 rounded-lg bg-gray-100">
                    <i class="fa-solid fa-receipt text-xl text-gray-600"></i>
                  </div>
                </div>
              </div>

              <!-- Total Pax -->
              <div class="p-4 rounded-lg border border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-medium text-gray-600">Total Pax</p>
                    <p class="text-2xl font-bold text-gray-900">
                      {{ formatNumber(lunchDinnerData.summary?.total_pax || 0) }}
                    </p>
                  </div>
                  <div class="p-2 rounded-lg bg-gray-100">
                    <i class="fa-solid fa-users text-xl text-gray-600"></i>
                  </div>
                </div>
              </div>

              <!-- Average Check -->
              <div class="p-4 rounded-lg border border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-medium text-gray-600">Avg Check</p>
                    <p class="text-2xl font-bold text-gray-900">
                      {{ formatCurrency(lunchDinnerData.summary?.avg_check || 0) }}
                    </p>
                  </div>
                  <div class="p-2 rounded-lg bg-gray-100">
                    <i class="fa-solid fa-calculator text-xl text-gray-600"></i>
                  </div>
                </div>
              </div>
            </div>

            <!-- Chart Trend -->
            <div v-if="lunchDinnerData.daily_data && lunchDinnerData.daily_data.length > 0" class="bg-white border border-gray-200 rounded-lg p-6">
              <h4 class="text-lg font-semibold text-gray-900 mb-4">
                Trend Revenue {{ selectedMealPeriod }} Harian
              </h4>
              <div class="h-64">
                <apexchart 
                  type="line" 
                  height="300" 
                  :options="chartOptions" 
                  :series="chartSeries" 
                />
              </div>
            </div>

            <!-- Daily Data Table -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
              <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900">
                  Data Harian {{ selectedMealPeriod }}
                </h4>
                <p class="text-sm text-gray-600">
                  Periode: {{ new Date(dateFrom).toLocaleDateString('id-ID') }} - {{ new Date(dateTo).toLocaleDateString('id-ID') }}
                </p>
              </div>
              
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tanggal
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Orders
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Pax
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Revenue
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Avg Check
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in lunchDinnerData.daily_data" :key="item.date" class="hover:bg-gray-50">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ new Date(item.date).toLocaleDateString('id-ID', { 
                          day: '2-digit', 
                          month: 'short', 
                          year: 'numeric' 
                        }) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ formatNumber(item.orders) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ formatNumber(item.pax) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                        {{ formatCurrency(item.revenue) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ formatCurrency(item.avg_check) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div>

          <!-- No Data State -->
          <div v-else class="text-center py-8">
            <div class="text-gray-400 text-4xl mb-2">
              <i class="fa-solid fa-chart-bar"></i>
            </div>
            <p class="text-gray-600">Tidak ada data {{ selectedMealPeriod }} untuk outlet ini</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-3 border-t border-gray-200">
          <div class="flex justify-end">
            <button 
              @click="closeModal"
              class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
