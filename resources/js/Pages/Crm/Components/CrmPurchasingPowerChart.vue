<script setup>
import { ref, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  data: Array,
  filters: Object,
})

const chartContainer = ref(null)
let chart = null

// Date filter state
const dateFilters = ref({
  start_date: props.filters?.start_date || '',
  end_date: props.filters?.end_date || '',
})

// Age group colors
const ageGroupColors = {
  'Anak-anak': '#8B5CF6',
  'Remaja': '#EF4444',
  'Dewasa Muda': '#F97316',
  'Dewasa Produktif': '#EAB308',
  'Dewasa Matang': '#22C55E',
  'Usia Tua': '#3B82F6',
  'Tidak Diketahui': '#6B7280'
}

onMounted(() => {
  if (typeof ApexCharts !== 'undefined') {
    createChart()
  } else {
    // Load ApexCharts if not available
    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/apexcharts'
    script.onload = createChart
    document.head.appendChild(script)
  }
})

watch(() => props.data, () => {
  if (chart && props.data && props.data.length > 0) {
    const labels = props.data.map(item => item.age_group)
    const totalSpending = props.data.map(item => item.total_spending)
    const avgSpendingPerCustomer = props.data.map(item => item.avg_spending_per_customer)
    
    chart.updateSeries([
      {
        name: 'Total Pengeluaran',
        type: 'column',
        data: totalSpending
      },
      {
        name: 'Rata-rata per Customer',
        type: 'line',
        data: avgSpendingPerCustomer
      }
    ])
    
    chart.updateOptions({
      xaxis: {
        categories: labels
      }
    })
  } else if (chart && (!props.data || props.data.length === 0)) {
    // Clear chart if no data
    chart.updateSeries([])
  }
}, { deep: true })

function createChart() {
  if (!props.data || !chartContainer.value || props.data.length === 0) return

  const labels = props.data.map(item => item.age_group)
  const totalSpending = props.data.map(item => item.total_spending)
  const avgSpendingPerCustomer = props.data.map(item => item.avg_spending_per_customer)
  const colors = labels.map(label => ageGroupColors[label])

  const options = {
    series: [
      {
        name: 'Total Pengeluaran',
        type: 'column',
        data: totalSpending
      },
      {
        name: 'Rata-rata per Customer',
        type: 'line',
        data: avgSpendingPerCustomer
      }
    ],
    chart: {
      type: 'line',
      height: 400,
      stacked: false,
      toolbar: {
        show: false
      },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800,
        animateGradually: {
          enabled: true,
          delay: 150
        },
        dynamicAnimation: {
          enabled: true,
          speed: 350
        }
      }
    },
    colors: ['#3B82F6', '#EF4444'],
    stroke: {
      curve: 'smooth',
      width: [0, 3]
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        columnWidth: '60%',
        dataLabels: {
          position: 'top'
        }
      }
    },
    fill: {
      type: ['solid', 'gradient'],
      gradient: {
        shade: 'light',
        type: 'vertical',
        shadeIntensity: 0.25,
        gradientToColors: ['#3B82F6'],
        inverseColors: false,
        opacityFrom: 0.8,
        opacityTo: 0.1,
        stops: [0, 100]
      }
    },
    grid: {
      borderColor: '#e2e8f0',
      strokeDashArray: 4,
    },
    xaxis: {
      categories: labels,
      labels: {
        style: {
          colors: '#6b7280',
          fontSize: '12px'
        }
      }
    },
    yaxis: [
      {
        title: {
          text: 'Total Pengeluaran (Rp)',
          style: {
            color: '#3B82F6',
            fontSize: '14px',
            fontWeight: 600
          }
        },
        labels: {
          style: {
            colors: '#6b7280',
            fontSize: '12px'
          },
          formatter: function(value) {
            return 'Rp ' + value.toLocaleString('id-ID')
          }
        }
      },
      {
        opposite: true,
        title: {
          text: 'Rata-rata per Customer (Rp)',
          style: {
            color: '#EF4444',
            fontSize: '14px',
            fontWeight: 600
          }
        },
        labels: {
          style: {
            colors: '#6b7280',
            fontSize: '12px'
          },
          formatter: function(value) {
            return 'Rp ' + value.toLocaleString('id-ID')
          }
        }
      }
    ],
    tooltip: {
      shared: true,
      intersect: false,
      y: {
        formatter: function(value, { seriesIndex }) {
          if (seriesIndex === 0) {
            return 'Rp ' + value.toLocaleString('id-ID')
          } else {
            return 'Rp ' + value.toLocaleString('id-ID')
          }
        }
      }
    },
    legend: {
      position: 'top',
      horizontalAlign: 'right',
      fontSize: '14px',
      markers: {
        radius: 12
      }
    },
    dataLabels: {
      enabled: false
    },
    markers: {
      size: 5,
      hover: {
        size: 7
      }
    },
    states: {
      hover: {
        filter: {
          type: 'lighten',
          value: 0.15
        }
      },
      active: {
        allowMultipleDataPointsSelection: false,
        filter: {
          type: 'darken',
          value: 0.35
        }
      }
    }
  }

  chart = new ApexCharts(chartContainer.value, options)
  chart.render()
}

function formatNumber(number) {
  return number.toLocaleString('id-ID')
}

function applyDateFilter() {
  router.visit('/crm/dashboard', {
    data: {
      start_date: dateFilters.value.start_date,
      end_date: dateFilters.value.end_date,
    },
    preserveState: true,
  })
}

function clearDateFilter() {
  dateFilters.value.start_date = ''
  dateFilters.value.end_date = ''
  router.visit('/crm/dashboard', {
    data: {
      start_date: '',
      end_date: '',
    },
    preserveState: true,
  })
}
</script>

<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="mb-4">
      <div class="flex justify-between items-start mb-2">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Daya Beli per Kelompok Usia</h3>
          <p class="text-sm text-gray-600">Analisis pengeluaran dan daya beli berdasarkan kelompok usia</p>
        </div>
      </div>
      
      <!-- Date Filter -->
      <div class="mt-4 p-4 bg-gray-50 rounded-lg">
        <div class="flex flex-col sm:flex-row gap-4 items-end">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
            <input
              v-model="dateFilters.start_date"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
            <input
              v-model="dateFilters.end_date"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div class="flex gap-2">
            <button
              @click="applyDateFilter"
              class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition flex items-center gap-2"
            >
              <i class="fa-solid fa-filter"></i>
              Filter
            </button>
            <button
              @click="clearDateFilter"
              class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition flex items-center gap-2"
            >
              <i class="fa-solid fa-times"></i>
              Reset
            </button>
          </div>
        </div>
        <div v-if="filters?.start_date || filters?.end_date" class="mt-2 text-sm text-gray-600">
          <i class="fa-solid fa-info-circle"></i>
          Filter aktif: {{ filters.start_date || 'Semua' }} - {{ filters.end_date || 'Semua' }}
        </div>
      </div>
    </div>

    <!-- Chart Container -->
    <div class="mb-6">
      <div ref="chartContainer" class="w-full h-96" v-if="data && data.length > 0"></div>
      <div v-else class="flex items-center justify-center h-96 text-gray-500">
        <div class="text-center">
          <i class="fas fa-chart-bar text-4xl mb-2"></i>
          <p>Tidak ada data untuk ditampilkan</p>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div v-if="data && data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="item in data" :key="item.age_group" class="bg-gray-50 rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <div 
              class="w-3 h-3 rounded-full"
              :style="{ backgroundColor: ageGroupColors[item.age_group] }"
            ></div>
            <span class="text-sm font-semibold text-gray-900">{{ item.age_group }}</span>
          </div>
        </div>
        
        <div class="space-y-1 text-xs">
          <div class="flex justify-between">
            <span class="text-gray-600">Total Customer:</span>
            <span class="font-medium">{{ formatNumber(item.total_customers) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Total Pengeluaran:</span>
            <span class="font-medium">{{ item.total_spending_formatted }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Rata-rata per Customer:</span>
            <span class="font-medium">{{ item.avg_spending_per_customer_formatted }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Total Transaksi:</span>
            <span class="font-medium">{{ formatNumber(item.total_transactions) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Overall Statistics -->
    <div v-if="data && data.length > 0" class="mt-6 pt-4 border-t border-gray-200">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div class="bg-blue-50 rounded-lg p-3">
          <p class="text-sm text-blue-600 font-medium">Total Customer</p>
          <p class="text-lg font-bold text-blue-800">
            {{ formatNumber(data.reduce((sum, item) => sum + item.total_customers, 0)) }}
          </p>
        </div>
        <div class="bg-green-50 rounded-lg p-3">
          <p class="text-sm text-green-600 font-medium">Total Pengeluaran</p>
          <p class="text-lg font-bold text-green-800">
            Rp {{ formatNumber(data.reduce((sum, item) => sum + item.total_spending, 0)) }}
          </p>
        </div>
        <div class="bg-orange-50 rounded-lg p-3">
          <p class="text-sm text-orange-600 font-medium">Total Transaksi</p>
          <p class="text-lg font-bold text-orange-800">
            {{ formatNumber(data.reduce((sum, item) => sum + item.total_transactions, 0)) }}
          </p>
        </div>
        <div class="bg-purple-50 rounded-lg p-3">
          <p class="text-sm text-purple-600 font-medium">Rata-rata Global</p>
          <p class="text-lg font-bold text-purple-800">
            Rp {{ formatNumber(data.reduce((sum, item) => sum + item.total_spending, 0) / data.reduce((sum, item) => sum + item.total_customers, 0)) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom styles if needed */
</style>
