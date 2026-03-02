<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-triangle-exclamation text-red-500"></i> MAC Anomaly Tracking
        </h1>
      </div>

      <div class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select v-model="filters.outlet_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Threshold Perubahan MAC (%)</label>
            <input
              type="number"
              min="1"
              step="1"
              v-model.number="filters.jump_threshold_percent"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>
          <div class="md:col-span-2 flex items-end">
            <button @click="loadData" class="w-full bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
              <i class="fa-solid fa-magnifying-glass mr-1"></i> Cek Anomali
            </button>
          </div>
        </div>
      </div>

      <div v-if="summary" class="grid grid-cols-2 md:grid-cols-5 gap-3 md:gap-4 mb-6">
        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
          <p class="text-xs text-gray-600">Total Dicek</p>
          <p class="text-xl font-bold text-gray-900">{{ summary.total_checked }}</p>
        </div>
        <div class="bg-red-50 rounded-xl p-4 border border-red-200">
          <p class="text-xs text-red-600">Critical</p>
          <p class="text-xl font-bold text-red-900">{{ summary.critical }}</p>
        </div>
        <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
          <p class="text-xs text-orange-600">High</p>
          <p class="text-xl font-bold text-orange-900">{{ summary.high }}</p>
        </div>
        <div class="bg-yellow-50 rounded-xl p-4 border border-yellow-200">
          <p class="text-xs text-yellow-700">Medium</p>
          <p class="text-xl font-bold text-yellow-900">{{ summary.medium }}</p>
        </div>
        <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
          <p class="text-xs text-blue-600">Total Anomali</p>
          <p class="text-xl font-bold text-blue-900">{{ summary.total_anomalies }}</p>
        </div>
      </div>

      <div v-if="anomalies.length > 0" class="bg-white rounded-xl shadow-xl p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-semibold text-gray-800">Daftar Anomali MAC</h2>
          <input
            v-model="search"
            type="text"
            placeholder="Cari item / kode item..."
            class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Lama</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Baru</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perubahan</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Small</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alasan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="row in filteredAnomalies" :key="row.history_id" class="hover:bg-gray-50">
                <td class="px-4 py-3 whitespace-nowrap">
                  <span class="px-2 py-1 rounded text-xs font-semibold" :class="severityClass(row.severity)">{{ row.severity.toUpperCase() }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm font-medium text-gray-900">{{ row.item_name }}</div>
                  <div class="text-xs text-gray-500">{{ row.item_code || '-' }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.warehouse_name }}</td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.previous_mac ?? '-' }}</td>
                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ row.current_mac }}</td>
                <td class="px-4 py-3 text-sm" :class="changeClass(row.change_percent)">
                  {{ row.change_percent !== null ? row.change_percent + '%' : '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.current_qty_small }}</td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.reasons.join('; ') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="!loading && hasSearched && anomalies.length === 0" class="bg-white rounded-xl shadow-xl p-6 text-center text-gray-500">
        <i class="fa-solid fa-circle-check text-4xl mb-3 text-green-500"></i>
        <p class="text-lg font-medium">Tidak ada anomali MAC pada outlet ini</p>
      </div>

      <div v-if="loading" class="bg-white rounded-xl shadow-xl p-6 text-center text-gray-500">
        <i class="fa-solid fa-spinner fa-spin text-4xl mb-3"></i>
        <p class="text-lg font-medium">Memuat data anomali...</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'

const filters = ref({
  outlet_id: '',
  jump_threshold_percent: 50
})

const outlets = ref([])
const anomalies = ref([])
const summary = ref(null)
const loading = ref(false)
const hasSearched = ref(false)
const search = ref('')

onMounted(async () => {
  await loadOutlets()
})

async function loadOutlets() {
  try {
    const response = await axios.get('/api/outlets/report')
    outlets.value = response.data.outlets || []
  } catch (error) {
    console.error('Error loading outlets:', error)
    outlets.value = []
  }
}

async function loadData() {
  if (!filters.value.outlet_id) {
    alert('Silakan pilih outlet terlebih dahulu')
    return
  }

  loading.value = true
  hasSearched.value = true

  try {
    const response = await axios.get('/api/mac-anomaly-tracking', {
      params: {
        id_outlet: filters.value.outlet_id,
        jump_threshold_percent: filters.value.jump_threshold_percent
      }
    })

    if (response.data.status === 'success') {
      anomalies.value = response.data.anomalies || []
      summary.value = response.data.summary || null
    } else {
      anomalies.value = []
      summary.value = null
    }
  } catch (error) {
    console.error('Error loading anomaly tracking:', error)
    anomalies.value = []
    summary.value = null
  } finally {
    loading.value = false
  }
}

const filteredAnomalies = computed(() => {
  const keyword = search.value.toLowerCase().trim()
  if (!keyword) {
    return anomalies.value
  }

  return anomalies.value.filter((row) => {
    const itemName = (row.item_name || '').toLowerCase()
    const itemCode = (row.item_code || '').toLowerCase()
    const warehouse = (row.warehouse_name || '').toLowerCase()
    return itemName.includes(keyword) || itemCode.includes(keyword) || warehouse.includes(keyword)
  })
})

function severityClass(severity) {
  if (severity === 'critical') return 'bg-red-100 text-red-700'
  if (severity === 'high') return 'bg-orange-100 text-orange-700'
  return 'bg-yellow-100 text-yellow-800'
}

function changeClass(changePercent) {
  if (changePercent === null || changePercent === undefined) return 'text-gray-700'
  const value = Number(changePercent)
  if (value > 0) return 'text-red-600 font-medium'
  if (value < 0) return 'text-green-600 font-medium'
  return 'text-gray-700'
}
</script>
