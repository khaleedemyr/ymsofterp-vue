<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <i class="fa-solid fa-users text-blue-600"></i>
            Monitoring User Aktif
          </h1>
          <p class="text-gray-600 mt-1">Real-time monitoring aktifitas user di sistem</p>
        </div>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2 bg-green-50 px-4 py-2 rounded-lg">
            <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
            <span class="text-sm font-semibold text-green-700">Live</span>
          </div>
          <select 
            v-model="timeWindow" 
            @change="loadStats"
            class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option :value="15">15 Menit</option>
            <option :value="30">30 Menit</option>
            <option :value="60">1 Jam</option>
            <option :value="120">2 Jam</option>
          </select>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Active Sessions -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Total Active Sessions</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.total_active_sessions || 0 }}</p>
              <p class="text-xs text-gray-500 mt-1">Sesi aktif dalam {{ timeWindow }} menit terakhir</p>
            </div>
            <div class="bg-blue-100 p-4 rounded-full">
              <i class="fa-solid fa-network-wired text-blue-600 text-2xl"></i>
            </div>
          </div>
        </div>

        <!-- Unique Active Users -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Unique Active Users</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ stats.unique_active_users || 0 }}</p>
              <p class="text-xs text-gray-500 mt-1">User unik yang sedang aktif</p>
            </div>
            <div class="bg-green-100 p-4 rounded-full">
              <i class="fa-solid fa-user-check text-green-600 text-2xl"></i>
            </div>
          </div>
        </div>

        <!-- Peak Time -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Peak Time Today</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">
                {{ peakTime ? peakTime.formatted : '-' }}
              </p>
              <p class="text-xs text-gray-500 mt-1">
                {{ peakTime ? `${peakTime.count} sessions` : 'No data' }}
              </p>
            </div>
            <div class="bg-purple-100 p-4 rounded-full">
              <i class="fa-solid fa-clock text-purple-600 text-2xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold text-gray-900">Trend Active Users (Real-time)</h2>
          <span class="text-sm text-gray-500">Updated: {{ lastUpdate || 'Loading...' }}</span>
        </div>
        <div class="mt-4">
          <apexchart 
            type="line" 
            :options="chartOptions" 
            :series="chartSeries" 
            height="350"
          />
        </div>
      </div>

      <!-- Active Sessions Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-900">Daftar User Aktif</h2>
          <p class="text-sm text-gray-600 mt-1">User yang sedang aktif dalam {{ timeWindow }} menit terakhir</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Browser</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">IP Address</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Last Activity</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="loading">
                <td colspan="5" class="px-6 py-8 text-center">
                  <div class="flex justify-center items-center">
                    <i class="fa fa-spinner fa-spin text-blue-600 text-2xl mr-3"></i>
                    <span class="text-gray-600">Loading...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="activeSessions.length === 0">
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                  Tidak ada user aktif
                </td>
              </tr>
              <tr v-for="session in activeSessions" :key="session.session_id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <img 
                      :src="session.user_avatar || '/img/default-avatar.png'" 
                      :alt="session.user_name"
                      class="h-10 w-10 rounded-full mr-3 object-cover"
                      @error="handleImageError"
                    />
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ session.user_name }}</div>
                      <div class="text-xs text-gray-500">ID: {{ session.user_id }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ session.user_email }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                    {{ session.user_agent }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ session.ip_address }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ formatTime(session.last_activity) }}</div>
                  <div class="text-xs text-gray-500">{{ getTimeAgo(session.last_activity_timestamp) }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const stats = ref({
  total_active_sessions: 0,
  unique_active_users: 0,
  time_window_minutes: 30
})

const activeSessions = ref([])
const chartData = ref([])
const peakTime = ref(null)
const timeWindow = ref(30)
const loading = ref(true)
const lastUpdate = ref('')
let pollInterval = null

// Chart Series
const chartSeries = computed(() => [{
  name: 'Active Users',
  data: chartData.value.map(item => item.count)
}])

// Chart Options
const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 350,
    toolbar: { 
      show: true,
      tools: {
        download: true,
        selection: true,
        zoom: true,
        zoomin: true,
        zoomout: true,
        pan: true,
        reset: true
      }
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
    },
    dropShadow: {
      enabled: true,
      top: 4,
      left: 2,
      blur: 8,
      opacity: 0.18
    }
  },
  stroke: {
    width: 4,
    curve: 'smooth',
    colors: ['#3B82F6']
  },
  markers: {
    size: 5,
    colors: ['#fff'],
    strokeColors: ['#3B82F6'],
    strokeWidth: 3,
    hover: {
      size: 8
    }
  },
  fill: {
    type: 'gradient',
    gradient: {
      shade: 'light',
      type: 'vertical',
      shadeIntensity: 0.5,
      gradientToColors: ['#3B82F6'],
      inverseColors: false,
      opacityFrom: 0.8,
      opacityTo: 0.3,
      stops: [0, 90, 100]
    }
  },
  xaxis: {
    categories: chartData.value.map(item => item.time),
    labels: {
      style: {
        fontSize: '12px',
        fontWeight: 600
      }
    }
  },
  yaxis: {
    title: {
      text: 'Jumlah User'
    },
    min: 0,
    labels: {
      style: {
        fontSize: '12px',
        fontWeight: 600
      },
      formatter: (value) => {
        return value.toLocaleString('id-ID')
      }
    }
  },
  colors: ['#3B82F6'],
  grid: {
    borderColor: '#E5E7EB',
    strokeDashArray: 4,
    xaxis: {
      lines: {
        show: false
      }
    },
    yaxis: {
      lines: {
        show: true
      }
    }
  },
  tooltip: {
    theme: 'light',
    shared: true,
    intersect: false,
    y: {
      formatter: (value) => {
        return value + ' user'
      }
    }
  },
  dataLabels: {
    enabled: true,
    style: {
      fontSize: '12px',
      fontWeight: 600,
      colors: ['#3B82F6']
    },
    offsetY: -5
  },
  legend: {
    show: false
  },
  noData: {
    text: 'Tidak ada data',
    align: 'center',
    verticalAlign: 'middle',
    style: {
      color: '#999',
      fontSize: '14px',
      fontFamily: undefined
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
  },
  responsive: [{
    breakpoint: 480,
    options: {
      chart: {
        height: 300
      }
    }
  }]
}))

onMounted(() => {
  loadStats()
  // Start polling every 5 seconds for real-time updates
  pollInterval = setInterval(() => {
    loadStats()
  }, 5000)
})

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval)
  }
})

function loadStats() {
  loading.value = true
  axios.get('/api/monitoring/active-users/stats', {
    params: {
      time_window: timeWindow.value
    }
  })
    .then(response => {
      if (response.data.success) {
        const data = response.data.data
        stats.value = {
          total_active_sessions: data.total_active_sessions,
          unique_active_users: data.unique_active_users,
          time_window_minutes: data.time_window_minutes
        }
        activeSessions.value = data.active_sessions || []
        chartData.value = data.chart_data || []
        peakTime.value = data.peak_time
        lastUpdate.value = data.updated_at
      }
    })
    .catch(error => {
      console.error('Error loading stats:', error)
    })
    .finally(() => {
      loading.value = false
    })
}

function formatTime(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

function getTimeAgo(timestamp) {
  if (!timestamp) return '-'
  const now = Math.floor(Date.now() / 1000)
  const diff = now - timestamp
  
  if (diff < 60) return `${diff} detik lalu`
  if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
  return `${Math.floor(diff / 86400)} hari lalu`
}

function handleImageError(event) {
  event.target.src = '/img/default-avatar.png'
}
</script>

