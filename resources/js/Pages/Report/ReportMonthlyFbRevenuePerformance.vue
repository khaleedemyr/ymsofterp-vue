<template>
  <AppLayout title="Monthly FB Revenue Performance">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Monthly FB Revenue Performance
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Filters -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                         <!-- Outlet Filter -->
             <div v-if="user.id_outlet == 1">
               <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
               <select
                 v-model="selectedOutlet"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
               >
                 <option value="">Pilih Outlet</option>
                 <option v-for="outlet in outlets" :key="outlet.qr_code" :value="outlet.qr_code">
                   {{ outlet.name }}
                 </option>
               </select>
             </div>

            <!-- Month Filter -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
              <select
                v-model="selectedMonth"
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
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option v-for="year in years" :key="year" :value="year">
                  {{ year }}
                </option>
              </select>
            </div>

            <!-- Load Button -->
            <div class="flex items-end">
              <button
                @click="loadReport"
                                 :disabled="(user.id_outlet == 1 && !selectedOutlet) || !selectedMonth || !selectedYear || loading"
                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
              >
                <span v-if="loading">Loading...</span>
                <span v-else>Load Data</span>
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
          <!-- Header -->
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">JUSTUS GROUP</h1>
            <h2 class="text-xl font-semibold text-gray-700">{{ report.outlet_name }}</h2>
          </div>

          <!-- FB Revenue Performance -->
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">FB Revenue Performance</h3>
            
            <!-- Weekdays & Weekends -->
            <div class="mb-8">
              <h4 class="text-md font-semibold text-gray-700 mb-3">Weekdays & Weekends - FB Revenue Performance</h4>
              <div class="overflow-x-auto mb-4">
                <table class="min-w-full bg-gray-800 text-white">
                  <thead>
                    <tr class="bg-gray-700">
                      <th class="px-4 py-3 text-left">Week</th>
                      <th class="px-4 py-3 text-right">Weekdays</th>
                      <th class="px-4 py-3 text-right">Weekends</th>
                      <th class="px-4 py-3 text-right">Total FB Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(weekData, weekNum) in report.weekly_data" :key="weekNum" class="border-b border-gray-700">
                      <td class="px-4 py-3 font-semibold">Week {{ weekNum }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.weekdays_revenue) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.weekends_revenue) }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ formatNumber(weekData.total_revenue) }}</td>
                    </tr>
                    <tr class="bg-gray-600 font-bold">
                      <td class="px-4 py-3">TOTAL MTD</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalWeekdaysRevenue) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalWeekendsRevenue) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalRevenue) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Chart -->
              <div class="h-64">
                <apexchart 
                  type="bar" 
                  :options="weekdaysWeekendsRevenueChartOptions" 
                  :series="weekdaysWeekendsRevenueSeries" 
                  height="100%" 
                  width="100%" 
                />
              </div>
            </div>

            <!-- Lunch & Dinner -->
            <div>
              <h4 class="text-md font-semibold text-gray-700 mb-3">Lunch & Dinner - FB Revenue Performance</h4>
              <div class="overflow-x-auto mb-4">
                <table class="min-w-full bg-gray-800 text-white">
                  <thead>
                    <tr class="bg-gray-700">
                      <th class="px-4 py-3 text-left">Week</th>
                      <th class="px-4 py-3 text-right">Lunch</th>
                      <th class="px-4 py-3 text-right">Dinner</th>
                      <th class="px-4 py-3 text-right">Total FB Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(weekData, weekNum) in report.weekly_data" :key="weekNum" class="border-b border-gray-700">
                      <td class="px-4 py-3 font-semibold">Week {{ weekNum }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.lunch_revenue) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.dinner_revenue) }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ formatNumber(weekData.total_revenue) }}</td>
                    </tr>
                    <tr class="bg-gray-600 font-bold">
                      <td class="px-4 py-3">TOTAL MTD</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalLunchRevenue) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalDinnerRevenue) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalRevenue) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Chart -->
              <div class="h-64">
                <apexchart 
                  type="bar" 
                  :options="lunchDinnerRevenueChartOptions" 
                  :series="lunchDinnerRevenueSeries" 
                  height="100%" 
                  width="100%" 
                />
              </div>
            </div>
          </div>

          <!-- FB Cover Performance -->
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">FB Cover Performance</h3>
            
            <!-- Weekdays & Weekends -->
            <div class="mb-8">
              <h4 class="text-md font-semibold text-gray-700 mb-3">Weekdays & Weekends - FB Cover Performance</h4>
              <div class="overflow-x-auto mb-4">
                <table class="min-w-full bg-gray-800 text-white">
                  <thead>
                    <tr class="bg-gray-700">
                      <th class="px-4 py-3 text-left">Week</th>
                      <th class="px-4 py-3 text-right">Weekdays</th>
                      <th class="px-4 py-3 text-right">Weekends</th>
                      <th class="px-4 py-3 text-right">Total FB Cover</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(weekData, weekNum) in report.weekly_data" :key="weekNum" class="border-b border-gray-700">
                      <td class="px-4 py-3 font-semibold">Week {{ weekNum }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.weekdays_cover) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.weekends_cover) }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ formatNumber(weekData.total_cover) }}</td>
                    </tr>
                    <tr class="bg-gray-600 font-bold">
                      <td class="px-4 py-3">TOTAL MTD</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalWeekdaysCover) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalWeekendsCover) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalCover) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Chart -->
              <div class="h-64">
                <apexchart 
                  type="bar" 
                  :options="weekdaysWeekendsCoverChartOptions" 
                  :series="weekdaysWeekendsCoverSeries" 
                  height="100%" 
                  width="100%" 
                />
              </div>
            </div>

            <!-- Lunch & Dinner -->
            <div>
              <h4 class="text-md font-semibold text-gray-700 mb-3">Lunch & Dinner - FB Cover Performance</h4>
              <div class="overflow-x-auto mb-4">
                <table class="min-w-full bg-gray-800 text-white">
                  <thead>
                    <tr class="bg-gray-700">
                      <th class="px-4 py-3 text-left">Week</th>
                      <th class="px-4 py-3 text-right">Lunch</th>
                      <th class="px-4 py-3 text-right">Dinner</th>
                      <th class="px-4 py-3 text-right">Total FB Cover</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(weekData, weekNum) in report.weekly_data" :key="weekNum" class="border-b border-gray-700">
                      <td class="px-4 py-3 font-semibold">Week {{ weekNum }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.lunch_cover) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.dinner_cover) }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ formatNumber(weekData.total_cover) }}</td>
                    </tr>
                    <tr class="bg-gray-600 font-bold">
                      <td class="px-4 py-3">TOTAL MTD</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalLunchCover) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalDinnerCover) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(totalCover) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Chart -->
              <div class="h-64">
                <apexchart 
                  type="bar" 
                  :options="lunchDinnerCoverChartOptions" 
                  :series="lunchDinnerCoverSeries" 
                  height="100%" 
                  width="100%" 
                />
              </div>
            </div>
          </div>

          <!-- Average FB Check -->
          <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Average FB Check</h3>
            
            <!-- Weekdays & Weekends -->
            <div class="mb-8">
              <h4 class="text-md font-semibold text-gray-700 mb-3">Weekdays & Weekends - Average FB Check</h4>
              <div class="overflow-x-auto mb-4">
                <table class="min-w-full bg-gray-800 text-white">
                  <thead>
                    <tr class="bg-gray-700">
                      <th class="px-4 py-3 text-left">Week</th>
                      <th class="px-4 py-3 text-right">Weekdays</th>
                      <th class="px-4 py-3 text-right">Weekends</th>
                      <th class="px-4 py-3 text-right">Average FB Check</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(weekData, weekNum) in report.weekly_data" :key="weekNum" class="border-b border-gray-700">
                      <td class="px-4 py-3 font-semibold">Week {{ weekNum }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.weekdays_avg_check) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.weekends_avg_check) }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ formatNumber(weekData.avg_check) }}</td>
                    </tr>
                    <tr class="bg-gray-600 font-bold">
                      <td class="px-4 py-3">A/C MTD</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(avgWeekdaysCheck) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(avgWeekendsCheck) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(avgCheck) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Chart -->
              <div class="h-64">
                <apexchart 
                  type="bar" 
                  :options="weekdaysWeekendsAvgCheckChartOptions" 
                  :series="weekdaysWeekendsAvgCheckSeries" 
                  height="100%" 
                  width="100%" 
                />
              </div>
            </div>

            <!-- Lunch & Dinner -->
            <div>
              <h4 class="text-md font-semibold text-gray-700 mb-3">Lunch & Dinner - Average FB Check</h4>
              <div class="overflow-x-auto mb-4">
                <table class="min-w-full bg-gray-800 text-white">
                  <thead>
                    <tr class="bg-gray-700">
                      <th class="px-4 py-3 text-left">Week</th>
                      <th class="px-4 py-3 text-right">Lunch</th>
                      <th class="px-4 py-3 text-right">Dinner</th>
                      <th class="px-4 py-3 text-right">Average FB Check</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(weekData, weekNum) in report.weekly_data" :key="weekNum" class="border-b border-gray-700">
                      <td class="px-4 py-3 font-semibold">Week {{ weekNum }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.lunch_avg_check) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(weekData.dinner_avg_check) }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ formatNumber(weekData.avg_check) }}</td>
                    </tr>
                    <tr class="bg-gray-600 font-bold">
                      <td class="px-4 py-3">A/C MTD</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(avgLunchCheck) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(avgDinnerCheck) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatNumber(avgCheck) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Chart -->
              <div class="h-64">
                <apexchart 
                  type="bar" 
                  :options="lunchDinnerAvgCheckChartOptions" 
                  :series="lunchDinnerAvgCheckSeries" 
                  height="100%" 
                  width="100%" 
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import VueApexCharts from 'vue3-apexcharts'

const loading = ref(false)
const report = ref(null)
const outlets = ref([])
const selectedOutlet = ref('')
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())
const user = usePage().props.auth?.user || {}

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

// Computed properties for totals
const totalWeekdaysRevenue = computed(() => {
  if (!report.value?.weekly_data) return 0
  return Object.values(report.value.weekly_data).reduce((sum, week) => sum + week.weekdays_revenue, 0)
})

const totalWeekendsRevenue = computed(() => {
  if (!report.value?.weekly_data) return 0
  return Object.values(report.value.weekly_data).reduce((sum, week) => sum + week.weekends_revenue, 0)
})

const totalRevenue = computed(() => totalWeekdaysRevenue.value + totalWeekendsRevenue.value)

const totalLunchRevenue = computed(() => {
  if (!report.value?.weekly_data) return 0
  return Object.values(report.value.weekly_data).reduce((sum, week) => sum + week.lunch_revenue, 0)
})

const totalDinnerRevenue = computed(() => {
  if (!report.value?.weekly_data) return 0
  return Object.values(report.value.weekly_data).reduce((sum, week) => sum + week.dinner_revenue, 0)
})

const totalWeekdaysCover = computed(() => {
  if (!report.value?.weekly_data) return 0
  return Object.values(report.value.weekly_data).reduce((sum, week) => sum + week.weekdays_cover, 0)
})

const totalWeekendsCover = computed(() => {
  if (!report.value?.weekly_data) return 0
  return Object.values(report.value.weekly_data).reduce((sum, week) => sum + week.weekends_cover, 0)
})

const totalCover = computed(() => totalWeekdaysCover.value + totalWeekendsCover.value)

const totalLunchCover = computed(() => {
  if (!report.value?.weekly_data) return 0
  return Object.values(report.value.weekly_data).reduce((sum, week) => sum + week.lunch_cover, 0)
})

const totalDinnerCover = computed(() => {
  if (!report.value?.weekly_data) return 0
  return Object.values(report.value.weekly_data).reduce((sum, week) => sum + week.dinner_cover, 0)
})

const avgWeekdaysCheck = computed(() => {
  return totalWeekdaysCover.value > 0 ? Math.round(totalWeekdaysRevenue.value / totalWeekdaysCover.value) : 0
})

const avgWeekendsCheck = computed(() => {
  return totalWeekendsCover.value > 0 ? Math.round(totalWeekendsRevenue.value / totalWeekendsCover.value) : 0
})

const avgCheck = computed(() => {
  return totalCover.value > 0 ? Math.round(totalRevenue.value / totalCover.value) : 0
})

const avgLunchCheck = computed(() => {
  return totalLunchCover.value > 0 ? Math.round(totalLunchRevenue.value / totalLunchCover.value) : 0
})

const avgDinnerCheck = computed(() => {
  return totalDinnerCover.value > 0 ? Math.round(totalDinnerRevenue.value / totalDinnerCover.value) : 0
})

const formatNumber = (number) => {
  return new Intl.NumberFormat('id-ID').format(number || 0)
}

// Chart data computed properties
const weekdaysWeekendsRevenueSeries = computed(() => {
  if (!report.value?.weekly_data) return []
  const weeks = Object.keys(report.value.weekly_data).sort()
  return [
    {
      name: 'Weekdays',
      data: weeks.map(week => report.value.weekly_data[week].weekdays_revenue)
    },
    {
      name: 'Weekends',
      data: weeks.map(week => report.value.weekly_data[week].weekends_revenue)
    }
  ]
})

const weekdaysWeekendsRevenueChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
  },
  plotOptions: {
    bar: {
      horizontal: false,
      borderRadius: 8,
      columnWidth: '60%',
      dataLabels: { position: 'top' },
    },
  },
  colors: ['#3b82f6', '#ef4444'],
  xaxis: {
    categories: Object.keys(report.value?.weekly_data || {}).sort().map(week => `Week ${week}`),
    labels: { style: { fontWeight: 600 } },
  },
  yaxis: {
    title: { text: 'Revenue (Rp)' },
    labels: { 
      style: { fontWeight: 600 },
      formatter: (value) => formatNumber(value)
    },
  },
  legend: { position: 'top', fontWeight: 700 },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  dataLabels: { enabled: true, formatter: (value) => formatNumber(value) },
  theme: { mode: 'light' },
}))

const lunchDinnerRevenueSeries = computed(() => {
  if (!report.value?.weekly_data) return []
  const weeks = Object.keys(report.value.weekly_data).sort()
  return [
    {
      name: 'Lunch',
      data: weeks.map(week => report.value.weekly_data[week].lunch_revenue)
    },
    {
      name: 'Dinner',
      data: weeks.map(week => report.value.weekly_data[week].dinner_revenue)
    }
  ]
})

const lunchDinnerRevenueChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
  },
  plotOptions: {
    bar: {
      horizontal: false,
      borderRadius: 8,
      columnWidth: '60%',
      dataLabels: { position: 'top' },
    },
  },
  colors: ['#10b981', '#f59e0b'],
  xaxis: {
    categories: Object.keys(report.value?.weekly_data || {}).sort().map(week => `Week ${week}`),
    labels: { style: { fontWeight: 600 } },
  },
  yaxis: {
    title: { text: 'Revenue (Rp)' },
    labels: { 
      style: { fontWeight: 600 },
      formatter: (value) => formatNumber(value)
    },
  },
  legend: { position: 'top', fontWeight: 700 },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  dataLabels: { enabled: true, formatter: (value) => formatNumber(value) },
  theme: { mode: 'light' },
}))

const weekdaysWeekendsCoverSeries = computed(() => {
  if (!report.value?.weekly_data) return []
  const weeks = Object.keys(report.value.weekly_data).sort()
  return [
    {
      name: 'Weekdays',
      data: weeks.map(week => report.value.weekly_data[week].weekdays_cover)
    },
    {
      name: 'Weekends',
      data: weeks.map(week => report.value.weekly_data[week].weekends_cover)
    }
  ]
})

const weekdaysWeekendsCoverChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
  },
  plotOptions: {
    bar: {
      horizontal: false,
      borderRadius: 8,
      columnWidth: '60%',
      dataLabels: { position: 'top' },
    },
  },
  colors: ['#3b82f6', '#ef4444'],
  xaxis: {
    categories: Object.keys(report.value?.weekly_data || {}).sort().map(week => `Week ${week}`),
    labels: { style: { fontWeight: 600 } },
  },
  yaxis: {
    title: { text: 'Cover (Pax)' },
    labels: { 
      style: { fontWeight: 600 },
      formatter: (value) => formatNumber(value)
    },
  },
  legend: { position: 'top', fontWeight: 700 },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  dataLabels: { enabled: true, formatter: (value) => formatNumber(value) },
  theme: { mode: 'light' },
}))

const lunchDinnerCoverSeries = computed(() => {
  if (!report.value?.weekly_data) return []
  const weeks = Object.keys(report.value.weekly_data).sort()
  return [
    {
      name: 'Lunch',
      data: weeks.map(week => report.value.weekly_data[week].lunch_cover)
    },
    {
      name: 'Dinner',
      data: weeks.map(week => report.value.weekly_data[week].dinner_cover)
    }
  ]
})

const lunchDinnerCoverChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
  },
  plotOptions: {
    bar: {
      horizontal: false,
      borderRadius: 8,
      columnWidth: '60%',
      dataLabels: { position: 'top' },
    },
  },
  colors: ['#10b981', '#f59e0b'],
  xaxis: {
    categories: Object.keys(report.value?.weekly_data || {}).sort().map(week => `Week ${week}`),
    labels: { style: { fontWeight: 600 } },
  },
  yaxis: {
    title: { text: 'Cover (Pax)' },
    labels: { 
      style: { fontWeight: 600 },
      formatter: (value) => formatNumber(value)
    },
  },
  legend: { position: 'top', fontWeight: 700 },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  dataLabels: { enabled: true, formatter: (value) => formatNumber(value) },
  theme: { mode: 'light' },
}))

const weekdaysWeekendsAvgCheckSeries = computed(() => {
  if (!report.value?.weekly_data) return []
  const weeks = Object.keys(report.value.weekly_data).sort()
  return [
    {
      name: 'Weekdays',
      data: weeks.map(week => report.value.weekly_data[week].weekdays_avg_check)
    },
    {
      name: 'Weekends',
      data: weeks.map(week => report.value.weekly_data[week].weekends_avg_check)
    }
  ]
})

const weekdaysWeekendsAvgCheckChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
  },
  plotOptions: {
    bar: {
      horizontal: false,
      borderRadius: 8,
      columnWidth: '60%',
      dataLabels: { position: 'top' },
    },
  },
  colors: ['#3b82f6', '#ef4444'],
  xaxis: {
    categories: Object.keys(report.value?.weekly_data || {}).sort().map(week => `Week ${week}`),
    labels: { style: { fontWeight: 600 } },
  },
  yaxis: {
    title: { text: 'Average Check (Rp)' },
    labels: { 
      style: { fontWeight: 600 },
      formatter: (value) => formatNumber(value)
    },
  },
  legend: { position: 'top', fontWeight: 700 },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  dataLabels: { enabled: true, formatter: (value) => formatNumber(value) },
  theme: { mode: 'light' },
}))

const lunchDinnerAvgCheckSeries = computed(() => {
  if (!report.value?.weekly_data) return []
  const weeks = Object.keys(report.value.weekly_data).sort()
  return [
    {
      name: 'Lunch',
      data: weeks.map(week => report.value.weekly_data[week].lunch_avg_check)
    },
    {
      name: 'Dinner',
      data: weeks.map(week => report.value.weekly_data[week].dinner_avg_check)
    }
  ]
})

const lunchDinnerAvgCheckChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
  },
  plotOptions: {
    bar: {
      horizontal: false,
      borderRadius: 8,
      columnWidth: '60%',
      dataLabels: { position: 'top' },
    },
  },
  colors: ['#10b981', '#f59e0b'],
  xaxis: {
    categories: Object.keys(report.value?.weekly_data || {}).sort().map(week => `Week ${week}`),
    labels: { style: { fontWeight: 600 } },
  },
  yaxis: {
    title: { text: 'Average Check (Rp)' },
    labels: { 
      style: { fontWeight: 600 },
      formatter: (value) => formatNumber(value)
    },
  },
  legend: { position: 'top', fontWeight: 700 },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  dataLabels: { enabled: true, formatter: (value) => formatNumber(value) },
  theme: { mode: 'light' },
}))

const loadOutlets = async () => {
  try {
    const response = await axios.get('/api/outlets/report')
    outlets.value = response.data.outlets || []
  } catch (error) {
    console.error('Error loading outlets:', error)
  }
}

const fetchMyOutletQr = async () => {
  try {
    const res = await axios.get('/api/my-outlet-qr')
    if (res.data.qr_code) {
      selectedOutlet.value = res.data.qr_code
    }
  } catch (error) {
    console.error('Error fetching my outlet QR:', error)
  }
}

const loadReport = async () => {
  if (!selectedMonth.value || !selectedYear.value) {
    alert('Pilih bulan dan tahun terlebih dahulu')
    return
  }
  
  if (user.id_outlet == 1 && !selectedOutlet.value) {
    alert('Pilih outlet terlebih dahulu')
    return
  }

  loading.value = true
  try {
    const params = {
      month: selectedMonth.value,
      year: selectedYear.value,
      ...(user.id_outlet == 1 && { outlet: selectedOutlet.value })
    }
    
    const response = await axios.get('/api/report/monthly-fb-revenue-performance', { params })
    report.value = response.data
  } catch (error) {
    console.error('Error loading report:', error)
    alert('Terjadi kesalahan saat mengambil data report')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (user.id_outlet == 1) {
    await loadOutlets()
  } else {
    await fetchMyOutletQr()
  }
})

// Define components
const components = {
  apexchart: VueApexCharts
}
</script> 