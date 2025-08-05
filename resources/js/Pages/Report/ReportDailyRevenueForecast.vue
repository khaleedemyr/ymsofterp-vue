<template>
  <AppLayout title="Daily Revenue Forecast & Actual MTD">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Daily Revenue Forecast & Actual MTD
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-full mx-auto sm:px-6 lg:px-8">
        <!-- Filters -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Month Filter -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
              <select
                v-model="selectedMonth"
                @change="loadReport"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option v-for="month in months" :key="month.value" :value="month.value">
                  {{ month.label }}
                </option>
              </select>
            </div>

            <!-- Year Filter -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
              <select
                v-model="selectedYear"
                @change="loadReport"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option v-for="year in years" :key="year" :value="year">
                  {{ year }}
                </option>
              </select>
            </div>

            <!-- Forecast Settings -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Forecast Settings</label>
              <button
                @click="showSettingsModal = true"
                class="w-full px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
              >
                <i class="fas fa-cog mr-2"></i>Settings
              </button>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <div class="flex items-center justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Loading report...</span>
          </div>
        </div>

        <!-- Report Content -->
        <div v-else-if="report" class="space-y-6">
          <!-- MTD Revenue Section -->
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">MTD REVENUE</h3>
              <div class="overflow-x-auto">
                <table class="w-full bg-gray-800 text-white">
                  <thead>
                    <tr class="bg-gray-700">
                      <th class="px-4 py-3 text-left w-12"></th>
                      <th class="px-4 py-3 text-left w-16">NO</th>
                      <th class="px-4 py-3 text-left">OUTLET</th>
                      <th class="px-4 py-3 text-right">ACTUAL MTD</th>
                      <th class="px-4 py-3 text-center">COVER</th>
                      <th class="px-4 py-3 text-right">AVERAGE CHECK</th>
                      <th class="px-4 py-3 text-right">BUDGET MTD</th>
                      <th class="px-4 py-3 text-right">VARIANCE</th>
                      <th class="px-4 py-3 text-right">%</th>
                      <th class="px-4 py-3 text-right">PERF%</th>
                      <th class="px-4 py-3 text-right">AVERAGE REVENUE PER DAY</th>
                      <th class="px-4 py-3 text-right">TO BE ACHIEVED PER DAY</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="(outletData, index) in report.outlets_data" :key="outletData.outlet_name">
                      <!-- Main Outlet Row -->
                      <tr class="border-b border-gray-700 hover:bg-gray-700 cursor-pointer" @click="toggleOutlet(index)">
                        <td class="px-4 py-3 text-center">
                          <i :class="expandedOutlets[index] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-blue-400"></i>
                        </td>
                        <td class="px-4 py-3">{{ index + 1 }}</td>
                        <td class="px-4 py-3 bg-green-600">{{ outletData.outlet_name }}</td>
                        <td class="px-4 py-3 text-right bg-yellow-600">{{ formatNumber(outletData.mtd_data.actual_mtd) }}</td>
                        <td class="px-4 py-3 text-center bg-yellow-600">{{ formatNumber(outletData.mtd_data.cover_mtd) }}</td>
                        <td class="px-4 py-3 text-right">{{ formatNumber(outletData.mtd_data.average_check) }}</td>
                        <td class="px-4 py-3 text-right bg-yellow-600">{{ formatNumber(outletData.monthly_budget) }}</td>
                        <td class="px-4 py-3 text-right">{{ formatNumber(outletData.performance_metrics.variance) }}</td>
                        <td class="px-4 py-3 text-right">{{ outletData.performance_metrics.variance_percentage }}%</td>
                        <td class="px-4 py-3 text-right bg-yellow-600">{{ outletData.performance_metrics.performance_percentage }}%</td>
                        <td class="px-4 py-3 text-right">{{ formatNumber(outletData.performance_metrics.average_revenue_per_day) }}</td>
                        <td class="px-4 py-3 text-right">{{ formatNumber(outletData.performance_metrics.to_be_achieved_per_day) }}</td>
                      </tr>
                      
                      <!-- Expanded Target Row -->
                      <tr v-if="expandedOutlets[index]" class="bg-gray-900 border-b border-gray-700">
                        <td colspan="12" class="px-4 py-4">
                          <div class="bg-gray-800 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-blue-400 mb-4">TARGET - {{ outletData.outlet_name }}</h4>
                            <div class="overflow-x-auto">
                              <table class="w-full bg-gray-700 text-white">
                                <thead>
                                  <tr class="bg-gray-600">
                                    <th class="px-4 py-3 text-center" rowspan="2">WEEKDAYS</th>
                                    <th class="px-4 py-3 text-center" colspan="5">WEEKDAYS</th>
                                    <th class="px-4 py-3 text-center" rowspan="2">TOTAL</th>
                                    <th class="px-4 py-3 text-center" rowspan="2">WEEKENDS</th>
                                    <th class="px-4 py-3 text-center" colspan="2">WEEKENDS</th>
                                    <th class="px-4 py-3 text-center" rowspan="2">TOTAL</th>
                                  </tr>
                                  <tr class="bg-gray-600">
                                    <th class="px-4 py-3 text-center bg-blue-400">SENIN</th>
                                    <th class="px-4 py-3 text-center bg-blue-400">SELASA</th>
                                    <th class="px-4 py-3 text-center bg-blue-400">RABU</th>
                                    <th class="px-4 py-3 text-center bg-blue-400">KAMIS</th>
                                    <th class="px-4 py-3 text-center bg-blue-400">JUMAT</th>
                                    <th class="px-4 py-3 text-center bg-blue-600">SABTU</th>
                                    <th class="px-4 py-3 text-center bg-blue-600">MINGGU</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <!-- Count Row -->
                                  <tr class="border-b border-gray-600">
                                    <td class="px-4 py-3 text-center bg-orange-600 font-semibold">Count</td>
                                    <td v-for="day in getOutletWeekdays(outletData)" :key="day.day_of_week" class="px-4 py-3 text-center bg-orange-600">
                                      {{ day.count }}
                                    </td>
                                    <td class="px-4 py-3 text-center bg-yellow-600 font-semibold">
                                      {{ getOutletWeekdaysTotal(outletData).count }}
                                    </td>
                                    <td class="px-4 py-3 text-center bg-orange-600 font-semibold"></td>
                                    <td v-for="day in getOutletWeekends(outletData)" :key="day.day_of_week" class="px-4 py-3 text-center bg-orange-600">
                                      {{ day.count }}
                                    </td>
                                    <td class="px-4 py-3 text-center bg-yellow-600 font-semibold">
                                      {{ getOutletWeekendsTotal(outletData).count }}
                                    </td>
                                  </tr>
                                  <!-- Target Revenue Row -->
                                  <tr class="border-b border-gray-600">
                                    <td class="px-4 py-3 text-center bg-green-600 font-semibold">Target Revenue</td>
                                    <td v-for="day in getOutletWeekdays(outletData)" :key="day.day_of_week" class="px-4 py-3 text-center bg-green-600">
                                      {{ formatNumber(day.target_revenue) }}
                                    </td>
                                    <td class="px-4 py-3 text-center bg-yellow-600 font-semibold">
                                      {{ formatNumber(getOutletWeekdaysTotal(outletData).target_revenue) }}
                                    </td>
                                    <td class="px-4 py-3 text-center bg-green-600 font-semibold"></td>
                                    <td v-for="day in getOutletWeekends(outletData)" :key="day.day_of_week" class="px-4 py-3 text-center bg-green-600">
                                      {{ formatNumber(day.target_revenue) }}
                                    </td>
                                    <td class="px-4 py-3 text-center bg-yellow-600 font-semibold">
                                      {{ formatNumber(getOutletWeekendsTotal(outletData).target_revenue) }}
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </td>
                      </tr>
                    </template>
                    
                    <!-- Total Row -->
                    <tr class="border-b border-gray-700 bg-gray-600 font-bold">
                      <td class="px-4 py-3" colspan="2">TOTAL FB REVENUE</td>
                      <td class="px-4 py-3 bg-green-600"></td>
                      <td class="px-4 py-3 text-right bg-yellow-600">{{ formatNumber(report.total_actual_mtd) }}</td>
                      <td class="px-4 py-3 text-center bg-yellow-600">{{ formatNumber(report.total_cover_mtd) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(report.total_average_check) }}</td>
                      <td class="px-4 py-3 text-right bg-yellow-600">{{ formatNumber(report.total_budget_mtd) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(report.total_variance) }}</td>
                      <td class="px-4 py-3 text-right">{{ report.total_variance_percentage }}%</td>
                      <td class="px-4 py-3 text-right bg-yellow-600">{{ report.total_performance_percentage }}%</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(report.total_average_revenue_per_day) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(report.total_to_be_achieved_per_day) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- MTD Performance Summary -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">MTD ACTUAL + TARGET</h3>
              <div class="text-2xl font-bold text-red-600 bg-red-100 p-4 rounded-lg text-center">
                {{ formatNumber(report.total_actual_mtd + report.total_budget_mtd) }}
              </div>
            </div>
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">MTD PERF% ACTUAL VS TARGET</h3>
              <div class="text-2xl font-bold text-red-600 bg-red-100 p-4 rounded-lg text-center">
                {{ report.total_performance_percentage }}%
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Forecast Settings Modal -->
    <div v-if="showSettingsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Forecast Settings</h3>
          <form @submit.prevent="saveSettings">
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Weekday Target</label>
              <input
                v-model="settingsForm.weekday_target"
                type="number"
                step="0.01"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                placeholder="Target per hari weekday"
              />
            </div>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Weekend Target</label>
              <input
                v-model="settingsForm.weekend_target"
                type="number"
                step="0.01"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                placeholder="Target per hari weekend"
              />
            </div>
            <div class="mb-4">
              <label class="flex items-center">
                <input
                  v-model="settingsForm.auto_calculate"
                  type="checkbox"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                />
                <span class="ml-2 text-sm text-gray-700">Auto Calculate</span>
              </label>
            </div>
            <div class="flex justify-end space-x-3">
              <button
                type="button"
                @click="showSettingsModal = false"
                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
              >
                Save
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const loading = ref(false)
const report = ref(null)
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())
const showSettingsModal = ref(false)
const expandedOutlets = ref({})
const settingsForm = ref({
  weekday_target: 0,
  weekend_target: 0,
  auto_calculate: true
})

const months = [
  { value: 1, label: 'Januari' },
  { value: 2, label: 'Februari' },
  { value: 3, label: 'Maret' },
  { value: 4, label: 'April' },
  { value: 5, label: 'Mei' },
  { value: 6, label: 'Juni' },
  { value: 7, label: 'Juli' },
  { value: 8, label: 'Agustus' },
  { value: 9, label: 'September' },
  { value: 10, label: 'Oktober' },
  { value: 11, label: 'November' },
  { value: 12, label: 'Desember' }
]

const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i)

const formatNumber = (number) => {
  return new Intl.NumberFormat('id-ID').format(number || 0)
}

const toggleOutlet = (index) => {
  expandedOutlets.value[index] = !expandedOutlets.value[index]
}

const getOutletWeekdays = (outletData) => {
  if (!outletData.daily_targets) return []
  // Return weekdays in order: Senin (1), Selasa (2), Rabu (3), Kamis (4), Jumat (5)
  return [
    outletData.daily_targets[1], // Senin
    outletData.daily_targets[2], // Selasa
    outletData.daily_targets[3], // Rabu
    outletData.daily_targets[4], // Kamis
    outletData.daily_targets[5]  // Jumat
  ].filter(day => day && !day.is_weekend)
}

const getOutletWeekends = (outletData) => {
  if (!outletData.daily_targets) return []
  // Return weekends in order: Sabtu (6), Minggu (0)
  return [
    outletData.daily_targets[6], // Sabtu
    outletData.daily_targets[0]  // Minggu
  ].filter(day => day && day.is_weekend)
}

const getOutletWeekdaysTotal = (outletData) => {
  const weekdays = getOutletWeekdays(outletData)
  if (!weekdays.length) return { count: 0, target_revenue: 0 }
  return {
    count: weekdays.reduce((sum, day) => sum + day.count, 0),
    target_revenue: weekdays.reduce((sum, day) => sum + (day.target_revenue * day.count), 0)
  }
}

const getOutletWeekendsTotal = (outletData) => {
  const weekends = getOutletWeekends(outletData)
  if (!weekends.length) return { count: 0, target_revenue: 0 }
  return {
    count: weekends.reduce((sum, day) => sum + day.count, 0),
    target_revenue: weekends.reduce((sum, day) => sum + (day.target_revenue * day.count), 0)
  }
}

const loadReport = async () => {
  if (!selectedMonth.value || !selectedYear.value) return

  loading.value = true
  try {
    const response = await axios.get('/api/report/daily-revenue-forecast', {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value
      }
    })
    report.value = response.data
    
    // Reset expanded outlets
    expandedOutlets.value = {}
    
    // Load settings form
    if (response.data.forecast_settings) {
      settingsForm.value = {
        weekday_target: response.data.forecast_settings.weekday_target,
        weekend_target: response.data.forecast_settings.weekend_target,
        auto_calculate: response.data.forecast_settings.auto_calculate
      }
    }
  } catch (error) {
    console.error('Error loading report:', error)
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  try {
    await axios.post('/api/report/daily-revenue-forecast/settings', {
      month: selectedMonth.value,
      year: selectedYear.value,
      ...settingsForm.value
    })
    
    showSettingsModal.value = false
    await loadReport()
  } catch (error) {
    console.error('Error saving settings:', error)
  }
}

onMounted(async () => {
  await loadReport()
})
</script> 