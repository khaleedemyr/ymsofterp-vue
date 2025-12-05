<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps({
  isOpen: Boolean,
  selectedDate: String
})

const emit = defineEmits(['close'])

const loading = ref(false)
const outletData = ref(null)
const error = ref(null)
const expandedOutlets = ref(new Set())
const loadingOrders = ref(new Set())
const outletOrders = ref({})

// Watch for date changes and fetch data
watch(() => props.selectedDate, (newDate) => {
  if (newDate && props.isOpen) {
    fetchOutletData(newDate)
  }
}, { immediate: true })

// Watch for modal open/close
watch(() => props.isOpen, (isOpen) => {
  if (isOpen && props.selectedDate) {
    fetchOutletData(props.selectedDate)
  } else if (!isOpen) {
    // Reset data when modal closes
    outletData.value = null
    error.value = null
  }
})

async function fetchOutletData(date) {
  if (!date) return
  
  loading.value = true
  error.value = null
  
  try {
    const response = await axios.get('/sales-outlet-dashboard/outlet-details', {
      params: { date }
    })
    outletData.value = response.data
  } catch (err) {
    error.value = err.response?.data?.error || 'Gagal mengambil data outlet'
    console.error('Error fetching outlet data:', err)
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

function toggleExpand(outletCode) {
  if (expandedOutlets.value.has(outletCode)) {
    expandedOutlets.value.delete(outletCode)
  } else {
    expandedOutlets.value.add(outletCode)
    // Fetch orders if not already loaded
    if (!outletOrders.value[outletCode]) {
      fetchOutletOrders(outletCode)
    }
  }
}

async function fetchOutletOrders(outletCode) {
  if (!props.selectedDate) return
  
  loadingOrders.value.add(outletCode)
  
  try {
    const response = await axios.get('/sales-outlet-dashboard/outlet-orders', {
      params: { 
        outlet_code: outletCode,
        date: props.selectedDate
      }
    })
    outletOrders.value[outletCode] = response.data.orders
  } catch (err) {
    console.error('Error fetching outlet orders:', err)
    outletOrders.value[outletCode] = []
  } finally {
    loadingOrders.value.delete(outletCode)
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

// Computed properties for revenue chart
const revenueChartSeries = computed(() => {
  if (!outletData.value?.outlets) return []
  
  // Get all unique outlet names (sorted by revenue descending)
  const allOutlets = [...outletData.value.outlets].sort((a, b) => b.revenue - a.revenue)
  const allOutletNames = allOutlets.map(outlet => outlet.outlet_name)
  
  // Group outlets by region
  const regionGroups = {}
  outletData.value.outlets.forEach(outlet => {
    if (!regionGroups[outlet.region_name]) {
      regionGroups[outlet.region_name] = {}
    }
    regionGroups[outlet.region_name][outlet.outlet_name] = outlet.revenue
  })
  
  // Create series for each region with data for all outlets
  const series = []
  const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4']
  let colorIndex = 0
  
  Object.keys(regionGroups).forEach(regionName => {
    const regionData = regionGroups[regionName]
    const data = allOutletNames.map(outletName => {
      return regionData[outletName] || 0 // 0 for outlets not in this region
    })
    
    series.push({
      name: regionName,
      data: data
    })
    colorIndex++
  })
  
  return series
})

const revenueChartOptions = computed(() => {
  if (!outletData.value?.outlets) return {}
  
  // Get all outlet names for x-axis (sorted by revenue descending)
  const allOutlets = [...outletData.value.outlets].sort((a, b) => b.revenue - a.revenue)
  const outletNames = allOutlets.map(outlet => outlet.outlet_name)
  
  return {
    chart: {
      type: 'bar',
      height: 300,
      toolbar: { show: true },
      dropShadow: {
        enabled: true,
        top: 4,
        left: 2,
        blur: 8,
        opacity: 0.18
      }
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        columnWidth: '60%',
        dataLabels: {
          enabled: false
        },
        horizontal: false
      }
    },
    xaxis: {
      categories: outletNames,
      labels: {
        rotate: -45,
        style: {
          fontSize: '12px'
        }
      }
    },
    yaxis: {
      title: {
        text: 'Revenue (Rp)'
      },
      labels: {
        formatter: function(value) {
          return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
          }).format(value)
        }
      }
    },
    colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'],
    legend: {
      position: 'top'
    },
    grid: {
      borderColor: '#e5e7eb'
    },
    tooltip: {
      shared: true,
      intersect: false,
      y: {
        formatter: function(value) {
          if (value === 0) return 'N/A'
          return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
          }).format(value)
        }
      }
    },
    dataLabels: {
      enabled: false
    }
  }
})
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
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-7xl sm:w-full max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="bg-white px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">
                Detail Outlet per Hari
              </h3>
              <p v-if="outletData" class="text-sm text-gray-600 mt-1">
                {{ outletData.date_formatted }}
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
        <div class="bg-white px-6 py-4 flex-1 overflow-y-auto">
          <!-- Loading State -->
          <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Memuat data outlet...</p>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="text-center py-8">
            <div class="text-red-500 mb-4">
              <i class="fa-solid fa-exclamation-triangle text-4xl"></i>
            </div>
            <p class="text-red-600">{{ error }}</p>
          </div>

          <!-- Data Content -->
          <div v-else-if="outletData">
            <!-- Revenue Chart -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
              <h4 class="text-lg font-semibold text-gray-900 mb-4">Revenue per Outlet by Region</h4>
              <div class="h-80">
                <apexchart
                  type="bar"
                  height="100%"
                  :options="revenueChartOptions"
                  :series="revenueChartSeries"
                ></apexchart>
              </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex items-center">
                  <div class="bg-blue-100 p-2 rounded-lg">
                    <i class="fa-solid fa-store text-blue-600"></i>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-blue-600 font-medium">Total Outlet</p>
                    <p class="text-2xl font-bold text-blue-800">{{ outletData.summary.total_outlets }}</p>
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
                    <p class="text-lg font-bold text-green-800">{{ outletData.summary.total_revenue_formatted }}</p>
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
                    <p class="text-2xl font-bold text-orange-800">{{ formatNumber(outletData.summary.total_orders) }}</p>
                  </div>
                </div>
              </div>

              <div class="bg-purple-50 rounded-lg p-4">
                <div class="flex items-center">
                  <div class="bg-purple-100 p-2 rounded-lg">
                    <i class="fa-solid fa-users text-purple-600"></i>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-purple-600 font-medium">Total Customers</p>
                    <p class="text-2xl font-bold text-purple-800">{{ formatNumber(outletData.summary.total_customers) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Outlet Details Table -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
              <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900">Detail per Outlet</h4>
                <p class="text-sm text-gray-600">Rata-rata revenue per outlet: {{ outletData.summary.avg_revenue_per_outlet_formatted }}</p>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Outlet
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Region
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
                      <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <template v-for="outlet in outletData.outlets" :key="outlet.outlet_code">
                      <!-- Main Row -->
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div>
                            <div class="text-sm font-medium text-gray-900">{{ outlet.outlet_name }}</div>
                            <div class="text-sm text-gray-500">{{ outlet.outlet_code }}</div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            {{ outlet.region_name }}
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                          {{ formatNumber(outlet.orders) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                          {{ outlet.revenue_formatted }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                          {{ formatNumber(outlet.customers) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                          {{ outlet.avg_order_value_formatted }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                          {{ outlet.cover_formatted }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                          <button
                            @click="toggleExpand(outlet.outlet_code)"
                            class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                          >
                            <i 
                              :class="expandedOutlets.has(outlet.outlet_code) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'"
                              class="mr-1"
                            ></i>
                            {{ expandedOutlets.has(outlet.outlet_code) ? 'Tutup' : 'Lihat Orders' }}
                          </button>
                        </td>
                      </tr>
                      
                      <!-- Expanded Row -->
                      <tr v-if="expandedOutlets.has(outlet.outlet_code)" class="bg-gray-50">
                        <td colspan="8" class="px-6 py-4">
                          <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <h5 class="text-sm font-semibold text-gray-900 mb-3">
                              Detail Orders - {{ outlet.outlet_name }}
                            </h5>
                            
                            <!-- Loading State -->
                            <div v-if="loadingOrders.has(outlet.outlet_code)" class="text-center py-4">
                              <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                              <p class="mt-2 text-sm text-gray-600">Memuat data orders...</p>
                            </div>
                            
                            <!-- Orders Table -->
                            <div v-else-if="outletOrders[outlet.outlet_code] && outletOrders[outlet.outlet_code].length > 0" class="overflow-x-auto">
                              <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                  <tr>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      ID
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Paid Number
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Table
                                    </th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Total
                                    </th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      PB1
                                    </th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Service
                                    </th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Grand Total
                                    </th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Pax
                                    </th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Comm Fee
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Waiters
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Kasir
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Customer
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Payment
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                      Waktu
                                    </th>
                                  </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                  <tr v-for="order in outletOrders[outlet.outlet_code]" :key="order.id" class="hover:bg-gray-50">
                                    <td class="px-2 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                      {{ order.order_id }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-gray-900">
                                      {{ order.paid_number }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-gray-900">
                                      {{ order.table }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-right text-gray-900">
                                      {{ formatCurrency(order.total) }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-right text-gray-900">
                                      {{ formatCurrency(order.pb1) }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-right text-gray-900">
                                      {{ formatCurrency(order.service) }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-right font-medium text-gray-900">
                                      {{ formatCurrency(order.grand_total) }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-right text-gray-900">
                                      {{ order.pax }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-right text-gray-900">
                                      {{ formatCurrency(order.commfee) }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-gray-900">
                                      {{ order.waiters }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-gray-900">
                                      {{ order.kasir }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-gray-900">
                                      {{ order.customer }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-gray-900">
                                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ order.payment_method }}
                                      </span>
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-sm text-gray-900">
                                      {{ order.created_at_formatted }}
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                            
                            <!-- Empty State -->
                            <div v-else class="text-center py-4">
                              <i class="fa-solid fa-receipt text-2xl text-gray-400 mb-2"></i>
                              <p class="text-sm text-gray-500">Tidak ada data orders untuk outlet ini</p>
                            </div>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>

              <!-- Empty State -->
              <div v-if="outletData.outlets.length === 0" class="text-center py-8">
                <i class="fa-solid fa-store text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-500">Tidak ada data outlet untuk tanggal ini</p>
              </div>
            </div>
          </div>

          <!-- No Data State -->
          <div v-else class="text-center py-8">
            <i class="fa-solid fa-calendar text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-500">Pilih tanggal untuk melihat detail outlet</p>
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
