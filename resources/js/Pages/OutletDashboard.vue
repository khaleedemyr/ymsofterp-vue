<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4 relative">
      <div v-if="loading" class="absolute inset-0 z-50 flex items-center justify-center bg-white/70">
        <svg class="animate-spin h-12 w-12 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
      </div>
      <h1 class="text-3xl font-bold mb-6 flex items-center gap-2">
        <i class="fa-solid fa-store text-blue-500"></i> Dashboard Sales Outlet
      </h1>
      <div class="flex gap-4 mb-6 items-end">
        <div v-if="user.id_outlet == 1">
          <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
          <select v-model="selectedOutlet" class="rounded border-gray-300 px-2 py-1">
            <option value="">Pilih Outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <div v-else>
          <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
          <div class="rounded border border-gray-300 px-2 py-1 bg-gray-100 text-gray-700 min-w-[180px]">{{ user.nama_outlet }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
          <input type="date" v-model="dateFrom" class="rounded border-gray-300 px-2 py-1" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
          <input type="date" v-model="dateTo" class="rounded border-gray-300 px-2 py-1" />
        </div>
        <button @click="fetchDashboard" :disabled="loading" class="ml-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition flex items-center gap-2">
          <span v-if="loading" class="animate-spin"><svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg></span>
          <span v-else>Tampilkan</span>
        </button>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div v-for="(val, key, idx) in summaryCards" :key="key" :class="`bg-gradient-to-br ${val.color} rounded-xl shadow-xl p-4 flex flex-col items-center transform transition duration-300 hover:scale-105 hover:shadow-2xl animate-fade-in`" style="min-height:110px; box-shadow: 0 6px 24px 0 rgba(0,0,0,0.08), 0 1.5px 4px 0 rgba(0,0,0,0.08);">
          <div class="text-2xl font-extrabold text-white drop-shadow-lg">{{ val.value }}</div>
          <div class="text-white text-sm mt-1 font-semibold tracking-wide uppercase drop-shadow">{{ val.label }}</div>
        </div>
      </div>

      <!-- Rolling Auto Forecast -->
      <div class="mb-8 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 px-5 py-4 border-b border-slate-100 bg-slate-50/80">
          <div>
            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
              <i class="fa-solid fa-chart-line text-blue-600"></i>
              Rolling Auto Forecast
            </h2>
            <p class="text-sm text-slate-500 mt-0.5">
              Proyeksi bergerak (tidak mengubah Revenue Target). Acuan awal dari monthly target, menyesuaikan MTD + sisa weekday/weekend/libur.
            </p>
          </div>
          <div class="flex items-end gap-2">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Bulan Forecast</label>
              <input
                type="month"
                v-model="forecastMonth"
                class="rounded border-gray-300 px-2 py-1 text-sm"
                @change="fetchRollingForecast"
              />
            </div>
          </div>
        </div>

        <div class="p-5">
          <div v-if="forecastLoading" class="py-10 text-center text-slate-400 text-sm">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Menghitung forecast…
          </div>

          <div v-else-if="forecastError" class="rounded-xl bg-rose-50 border border-rose-100 px-4 py-3 text-sm text-rose-700">
            {{ forecastError }}
          </div>

          <div v-else-if="!rollingForecast?.has_target" class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-800">
            {{ rollingForecast?.message || 'Belum ada monthly target di menu Revenue Target untuk bulan ini.' }}
          </div>

          <template v-else>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
              <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
                <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Monthly Target</div>
                <div class="mt-1 text-xl font-bold text-slate-900">{{ formatRupiah(rollingForecast.monthly_target) }}</div>
              </div>
              <div class="rounded-xl bg-blue-50 border border-blue-100 p-4">
                <div class="text-xs font-medium text-blue-600 uppercase tracking-wide">Actual MTD</div>
                <div class="mt-1 text-xl font-bold text-blue-900">{{ formatRupiah(rollingForecast.actual_mtd) }}</div>
                <div class="text-xs text-blue-500 mt-1">s/d {{ rollingForecast.as_of }}</div>
              </div>
              <div class="rounded-xl border p-4" :class="projectedCardClass">
                <div class="text-xs font-medium uppercase tracking-wide" :class="gapPositive ? 'text-emerald-700' : 'text-rose-700'">Projected EOM</div>
                <div class="mt-1 text-xl font-bold" :class="gapPositive ? 'text-emerald-900' : 'text-rose-900'">{{ formatRupiah(rollingForecast.projected_eom) }}</div>
                <div class="text-xs mt-1" :class="gapPositive ? 'text-emerald-600' : 'text-rose-600'">
                  {{ rollingForecast.pct_of_target }}% dari target · pace {{ rollingForecast.pace_factor }}
                </div>
              </div>
              <div class="rounded-xl border p-4" :class="gapPositive ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100'">
                <div class="flex items-center justify-between">
                  <div class="text-xs font-medium uppercase tracking-wide" :class="gapPositive ? 'text-emerald-700' : 'text-rose-700'">Gap vs Target</div>
                  <span
                    class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"
                    :class="gapPositive ? 'bg-emerald-200 text-emerald-800' : 'bg-rose-200 text-rose-800'"
                  >
                    {{ gapPositive ? 'On track+' : 'Under' }}
                  </span>
                </div>
                <div class="mt-1 text-xl font-bold" :class="gapPositive ? 'text-emerald-900' : 'text-rose-900'">
                  {{ formatRupiah(rollingForecast.gap_vs_target) }}
                </div>
                <div class="text-xs text-slate-500 mt-1">mode: {{ rollingForecast.mode }}</div>
              </div>
            </div>

            <div class="flex flex-wrap gap-3 mb-5 text-sm text-slate-600">
              <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5">
                <span class="font-semibold text-slate-800">{{ rollingForecast.remaining_weekdays }}</span> weekday sisa
              </span>
              <span class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-3 py-1.5 text-indigo-800">
                <span class="font-semibold">{{ rollingForecast.remaining_weekends }}</span> weekend sisa
              </span>
              <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-amber-800">
                <span class="font-semibold">{{ rollingForecast.remaining_holidays }}</span> libur/event sisa
              </span>
            </div>

            <div class="mb-6">
              <h3 class="font-semibold text-slate-800 mb-2">Actual vs Projected vs Baseline</h3>
              <apexchart
                v-if="forecastChartSeries.length"
                type="line"
                height="280"
                :options="forecastChartOptions"
                :series="forecastChartSeries"
              />
            </div>

            <div v-if="rollingForecast.history_compare?.length" class="mb-6">
              <h3 class="font-semibold text-slate-800 mb-2">Compare 3 Bulan Belakang</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div
                  v-for="h in rollingForecast.history_compare"
                  :key="h.month"
                  class="rounded-xl border border-slate-100 bg-slate-50 p-4"
                >
                  <div class="font-semibold text-slate-800">{{ h.label }}</div>
                  <div class="text-lg font-bold text-slate-900 mt-1">{{ formatRupiah(h.total) }}</div>
                  <div class="mt-2 grid grid-cols-3 gap-1 text-xs text-slate-500">
                    <div>WD avg<br><span class="font-medium text-slate-700">{{ formatRupiahShort(h.avg_weekday) }}</span></div>
                    <div>WE avg<br><span class="font-medium text-slate-700">{{ formatRupiahShort(h.avg_weekend) }}</span></div>
                    <div>Libur avg<br><span class="font-medium text-slate-700">{{ formatRupiahShort(h.avg_holiday) }}</span></div>
                  </div>
                </div>
              </div>
            </div>

            <div>
              <h3 class="font-semibold text-slate-800 mb-2">Day-by-day</h3>
              <div class="overflow-x-auto max-h-80 overflow-y-auto rounded-xl border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50 sticky top-0">
                    <tr class="text-left text-xs uppercase text-slate-500">
                      <th class="px-3 py-2">Tanggal</th>
                      <th class="px-3 py-2">Hari</th>
                      <th class="px-3 py-2">Tipe</th>
                      <th class="px-3 py-2 text-right">Actual</th>
                      <th class="px-3 py-2 text-right">Projected</th>
                      <th class="px-3 py-2 text-right">Baseline</th>
                      <th class="px-3 py-2 text-right">Hist Avg</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="d in rollingForecast.days"
                      :key="d.forecast_date"
                      class="border-t border-slate-50"
                      :class="d.status === 'forecast' ? 'bg-blue-50/40' : ''"
                    >
                      <td class="px-3 py-1.5 tabular-nums">{{ d.forecast_date }}</td>
                      <td class="px-3 py-1.5">{{ d.day_name }}</td>
                      <td class="px-3 py-1.5">
                        <span
                          class="inline-block text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded"
                          :class="dayTypeBadge(d.day_type)"
                        >{{ d.day_type }}</span>
                        <span v-if="d.holiday_name" class="ml-1 text-xs text-slate-400">{{ d.holiday_name }}</span>
                      </td>
                      <td class="px-3 py-1.5 text-right tabular-nums">{{ d.actual != null ? formatRupiahShort(d.actual) : '—' }}</td>
                      <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ formatRupiahShort(d.projected) }}</td>
                      <td class="px-3 py-1.5 text-right tabular-nums text-slate-500">{{ formatRupiahShort(d.baseline) }}</td>
                      <td class="px-3 py-1.5 text-right tabular-nums text-slate-500">{{ formatRupiahShort(d.hist_avg) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow p-4 md:col-span-2">
          <h2 class="font-bold mb-2">Grafik Penjualan Harian</h2>
          <apexchart type="line" height="250" :options="salesChartOptions" :series="salesChartSeries" />
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow p-4">
          <h2 class="font-bold mb-2">Pie Chart Pembayaran</h2>
          <apexchart type="pie" height="250" :options="paymentPieOptions" :series="paymentPieSeries" />
        </div>
        <div class="bg-white rounded-xl shadow p-4">
          <h2 class="font-bold mb-2">Pie Chart Penjualan per Mode Transaksi</h2>
          <apexchart v-if="salesPerModeSeries.length" type="pie" height="250" :options="salesPerModeOptions" :series="salesPerModeSeries" />
          <div v-else class="text-gray-400 text-center py-8">Tidak ada data</div>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow p-4">
          <h2 class="font-bold mb-2">Top 10 Item Terlaris</h2>
          <table class="min-w-full divide-y divide-gray-200">
            <thead><tr><th class="text-left px-2 py-1">Item</th><th class="text-right px-2 py-1">Qty</th></tr></thead>
            <tbody>
              <tr v-for="item in topItems" :key="item.item_name">
                <td class="px-2 py-1">{{ item.item_name }}</td>
                <td class="px-2 py-1 text-right">{{ item.total_qty }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
          <h2 class="font-bold mb-2">Promo Terpakai</h2>
          <table class="min-w-full divide-y divide-gray-200">
            <thead><tr><th class="text-left px-2 py-1">Promo</th><th class="text-right px-2 py-1">Dipakai</th></tr></thead>
            <tbody>
              <tr v-for="promo in promoUsage" :key="promo.name">
                <td class="px-2 py-1">{{ promo.name }}</td>
                <td class="px-2 py-1 text-right">{{ promo.used_count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow p-4">
          <h2 class="font-bold mb-2">Transaksi Terakhir</h2>
          <table class="min-w-full divide-y divide-gray-200">
            <thead><tr><th class="text-left px-2 py-1">Nomor</th><th class="text-right px-2 py-1">Total</th></tr></thead>
            <tbody>
              <tr v-for="order in lastOrders" :key="order.id">
                <td class="px-2 py-1">{{ order.nomor }}</td>
                <td class="px-2 py-1 text-right">{{ formatRupiah(order.grand_total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
          <h2 class="font-bold mb-2">Officer Check Terakhir</h2>
          <table class="min-w-full divide-y divide-gray-200">
            <thead><tr><th class="text-left px-2 py-1">Nama</th><th class="text-right px-2 py-1">Nilai</th><th class="text-right px-2 py-1">Sales</th></tr></thead>
            <tbody>
              <tr v-for="oc in officerChecks.filter(oc => Number(oc.transaksi) > 0)" :key="oc.id">
                <td class="px-2 py-1">{{ oc.user_name }}</td>
                <td class="px-2 py-1 text-right">{{ formatRupiah(oc.nilai) }}</td>
                <td class="px-2 py-1 text-right">{{ formatRupiah(oc.transaksi) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow p-4">
          <h2 class="font-bold mb-2">Promo Aktif</h2>
          <ul class="list-disc ml-6">
            <li v-for="promo in activePromosList" :key="promo.id">{{ promo.name }}</li>
          </ul>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
          <h2 class="font-bold mb-2">Investor</h2>
          <table class="min-w-full divide-y divide-gray-200">
            <thead><tr><th class="text-left px-2 py-1">Nama</th><th class="text-right px-2 py-1">Sales</th></tr></thead>
            <tbody>
              <tr v-for="inv in investors.filter(inv => Number(inv.transaksi) > 0)" :key="inv.id">
                <td class="px-2 py-1">{{ inv.name }}</td>
                <td class="px-2 py-1 text-right">{{ formatRupiah(inv.transaksi) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="mb-10">
        <div class="bg-gradient-to-br from-blue-500 via-purple-500 to-pink-500 rounded-2xl shadow-2xl p-6 pb-2 relative overflow-hidden animate-fade-in">
          <h2 class="text-white text-2xl font-bold mb-6 text-center tracking-wide drop-shadow">Leaderboard Sales by Waiters</h2>
          <div class="flex justify-center gap-6 mb-8">
            <div v-for="(w, i) in waiterLeaderboard.slice(0,3)" :key="w.waiters" :class="['flex flex-col items-center', i===1 ? 'scale-110 z-10' : 'opacity-80']">
              <div class="w-20 h-20 rounded-full bg-white border-4 border-white shadow-lg flex items-center justify-center mb-2">
                <img v-if="w.avatar" :src="w.avatar" class="w-full h-full rounded-full object-cover" />
                <span v-else class="text-3xl font-bold text-blue-500">{{ w.waiters?.charAt(0) || '?' }}</span>
              </div>
              <div class="text-white font-bold text-lg drop-shadow">{{ w.waiters || '-' }}</div>
              <div class="text-white text-sm font-semibold drop-shadow">{{ formatRupiah(w.total_sales) }}</div>
              <div v-if="i===1" class="mt-2"><span class="inline-block bg-yellow-300 text-yellow-900 px-3 py-1 rounded-full font-bold shadow">TOP 1</span></div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-4">
            <h3 class="font-bold mb-2 text-gray-700">Leaderboard</h3>
            <div v-for="(w, i) in waiterLeaderboard" :key="w.waiters" class="flex items-center gap-3 mb-3">
              <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center font-bold text-blue-500">{{ w.waiters?.charAt(0) || '?' }}</div>
              <div class="flex-1">
                <div class="font-semibold text-gray-800">{{ w.waiters || '-' }}</div>
                <div class="w-full bg-gray-100 rounded h-2 mt-1">
                  <div class="h-2 rounded bg-gradient-to-r from-blue-400 to-purple-500" :style="`width: ${(w.total_sales / (waiterLeaderboard[0]?.total_sales||1))*100}%`"></div>
                </div>
              </div>
              <div class="font-bold text-gray-700 ml-2">{{ formatRupiah(w.total_sales) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import VueApexCharts from 'vue3-apexcharts'
import { usePage } from '@inertiajs/vue3'

function getFirstDayOfMonth() {
  const now = new Date()
  return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
}
function getLastDayOfMonth() {
  const now = new Date()
  return new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
}
function getCurrentMonth() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

const page = usePage()
const user = page.props.auth?.user || { id_outlet: 1, nama_outlet: 'Outlet Demo' }
const outlets = ref([])
const selectedOutlet = ref(user.id_outlet)
const dateFrom = ref(getFirstDayOfMonth())
const dateTo = ref(getLastDayOfMonth())
const forecastMonth = ref(getCurrentMonth())

const summary = ref({})
const salesChart = ref([])
const paymentPie = ref([])
const topItems = ref([])
const lastOrders = ref([])
const promoUsage = ref([])
const officerChecks = ref([])
const activePromosList = ref([])
const investors = ref([])
const salesPerMode = ref([])
const waiterLeaderboard = ref([])

const rollingForecast = ref(null)
const forecastLoading = ref(false)
const forecastError = ref(null)
const forecastChartOptions = ref({
  chart: { id: 'rolling-forecast', toolbar: { show: true } },
  stroke: { width: [3, 3, 2], curve: 'smooth', dashArray: [0, 4, 6] },
  colors: ['#2563eb', '#f59e0b', '#94a3b8'],
  xaxis: { categories: [] },
  legend: { position: 'top' },
  tooltip: { y: { formatter: (val) => formatRupiah(val) } },
  yaxis: {
    labels: {
      formatter: (val) => formatRupiahShort(val),
    },
  },
})
const forecastChartSeries = ref([])

const gapPositive = computed(() => (rollingForecast.value?.gap_vs_target ?? 0) >= 0)
const projectedCardClass = computed(() =>
  gapPositive.value ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100'
)

const summaryCards = ref({})
const salesChartOptions = ref({ chart: { id: 'sales' }, xaxis: { categories: [] } })
const salesChartSeries = ref([])
const paymentPieOptions = ref({ labels: [] })
const paymentPieSeries = ref([])
const salesPerModeOptions = ref({ labels: [] })
const salesPerModeSeries = ref([])
const loading = ref(false)

onMounted(async () => {
  if (user.id_outlet == 1) {
    const res = await axios.get('/api/outlets')
    outlets.value = res.data.map(o => ({ id: o.id_outlet || o.id, name: o.nama_outlet || o.name }))
    if (!selectedOutlet.value && outlets.value.length) selectedOutlet.value = outlets.value[0].id
  } else {
    selectedOutlet.value = user.id_outlet
  }
  await fetchDashboard()
  paymentPieOptions.value = {
    labels: Object.keys(summary.value.payment_methods || {})
  }
  paymentPieSeries.value = Object.values(summary.value.payment_methods || {})
  // Efek 3D untuk line chart
  salesChartOptions.value = {
    ...salesChartOptions.value,
    chart: {
      id: 'sales',
      dropShadow: {
        enabled: true,
        top: 4,
        left: 2,
        blur: 8,
        opacity: 0.18
      },
      toolbar: { show: true }
    },
    stroke: { width: 4, curve: 'smooth' },
    markers: { size: 5, colors: ['#fff'], strokeColors: ['#2563eb'], strokeWidth: 3, hover: { size: 8 } },
    grid: { borderColor: '#eee' },
    xaxis: { categories: [] },
    tooltip: {
      y: {
        formatter: val => formatRupiah(val)
      }
    }
  }
  // Efek 3D donut untuk pie chart pembayaran
  paymentPieOptions.value = {
    ...paymentPieOptions.value,
    chart: {
      type: 'donut',
      dropShadow: {
        enabled: true,
        top: 4,
        left: 2,
        blur: 8,
        opacity: 0.18
      }
    },
    plotOptions: {
      pie: {
        donut: { size: '65%', labels: { show: true } }
      }
    },
    legend: { position: 'right' },
    tooltip: {
      y: {
        formatter: val => formatRupiah(val)
      },
      custom: function({ series, seriesIndex, w }) {
        return `<div class='px-3 py-2 rounded bg-yellow-400 text-white font-bold'>${w.globals.labels[seriesIndex]}: ${formatRupiah(series[seriesIndex])}</div>`;
      }
    }
  }
  // Efek 3D donut untuk pie chart per mode
  salesPerModeOptions.value = {
    ...salesPerModeOptions.value,
    chart: {
      type: 'donut',
      dropShadow: {
        enabled: true,
        top: 4,
        left: 2,
        blur: 8,
        opacity: 0.18
      }
    },
    plotOptions: {
      pie: {
        donut: { size: '65%', labels: { show: true } }
      }
    },
    legend: { position: 'right' },
    tooltip: {
      y: {
        formatter: val => formatRupiah(val)
      },
      custom: function({ series, seriesIndex, w }) {
        return `<div class='px-3 py-2 rounded bg-yellow-400 text-white font-bold'>${w.globals.labels[seriesIndex]}: ${formatRupiah(series[seriesIndex])}</div>`;
      }
    }
  }
})

function getDateRange(from, to) {
  const arr = []
  let dt = new Date(from)
  const end = new Date(to)
  while (dt <= end) {
    arr.push(dt.toISOString().slice(0, 10))
    dt.setDate(dt.getDate() + 1)
  }
  return arr
}

function syncForecastMonthFromRange() {
  if (dateFrom.value && /^\d{4}-\d{2}/.test(dateFrom.value)) {
    forecastMonth.value = dateFrom.value.slice(0, 7)
  }
}

async function fetchRollingForecast() {
  if (!selectedOutlet.value) {
    rollingForecast.value = null
    forecastError.value = 'Pilih outlet terlebih dahulu.'
    return
  }

  forecastLoading.value = true
  forecastError.value = null
  try {
    const res = await axios.get('/api/outlet-dashboard/rolling-forecast', {
      params: {
        id_outlet: selectedOutlet.value,
        month: forecastMonth.value,
      },
    })
    rollingForecast.value = res.data
    buildForecastChart(res.data)
  } catch (err) {
    const msg = err?.response?.data?.message || err?.message || 'Gagal memuat rolling forecast.'
    forecastError.value = msg
    rollingForecast.value = err?.response?.data || null
    forecastChartSeries.value = []
  } finally {
    forecastLoading.value = false
  }
}

function buildForecastChart(data) {
  const days = data?.days || []
  if (!days.length) {
    forecastChartSeries.value = []
    return
  }

  const categories = days.map((d) => d.forecast_date)
  const actualSeries = days.map((d) => (d.actual != null ? Number(d.actual) : null))
  const projectedSeries = days.map((d) => Number(d.projected) || 0)
  const baselineSeries = days.map((d) => Number(d.baseline) || 0)
  const histSeries = days.map((d) => Number(d.hist_avg) || 0)

  forecastChartOptions.value = {
    ...forecastChartOptions.value,
    xaxis: {
      categories,
      labels: { rotate: -45, hideOverlappingLabels: true },
    },
    tooltip: { y: { formatter: (val) => (val == null ? '—' : formatRupiah(val)) } },
  }

  forecastChartSeries.value = [
    { name: 'Actual / Projected', data: days.map((d) => Number(d.projected) || 0) },
    { name: 'Baseline Target', data: baselineSeries },
    { name: 'Hist Avg 3bln', data: histSeries },
  ]

  // Keep actualSeries available conceptually; chart uses continuous projected line
  // (past = actual, future = forecast) which is already in `projected`.
  void actualSeries
  void projectedSeries
}

function dayTypeBadge(type) {
  switch (type) {
    case 'weekend':
      return 'bg-indigo-100 text-indigo-800'
    case 'holiday':
      return 'bg-amber-100 text-amber-800'
    case 'ramadan':
      return 'bg-violet-100 text-violet-800'
    default:
      return 'bg-slate-100 text-slate-700'
  }
}

async function fetchDashboard() {
  loading.value = true
  try {
    syncForecastMonthFromRange()
    const params = {
      id_outlet: selectedOutlet.value,
      from: dateFrom.value,
      to: dateTo.value
    }
    const res = await axios.get('/api/outlet-dashboard', { params })
    summary.value = res.data.summary
    salesChart.value = res.data.sales_chart
    paymentPie.value = res.data.payment_pie
    topItems.value = res.data.top_items
    lastOrders.value = res.data.last_orders
    promoUsage.value = res.data.promo_usage
    officerChecks.value = res.data.officer_checks
    activePromosList.value = res.data.active_promos_list
    investors.value = res.data.investors
    salesPerMode.value = res.data.sales_per_mode || []
    waiterLeaderboard.value = res.data.waiter_leaderboard || []

    summaryCards.value = {
      total_orders: { label: 'Transaksi', value: summary.value.total_orders, color: 'from-blue-400 to-blue-600' },
      total_sales: { label: 'Penjualan', value: formatRupiah(summary.value.total_sales), color: 'from-green-400 to-green-600' },
      total_pax: { label: 'Pax', value: summary.value.total_pax, color: 'from-yellow-400 to-yellow-600' },
      avg_order: { label: 'Average Check', value: summary.value.total_pax > 0 ? formatRupiah(Math.round(summary.value.total_sales / summary.value.total_pax)) : 'Rp 0', color: 'from-purple-400 to-purple-600' },
      total_discount: { label: 'Diskon', value: formatRupiah(summary.value.total_discount), color: 'from-pink-400 to-pink-600' },
      total_cashback: { label: 'Cashback', value: formatRupiah(summary.value.total_cashback), color: 'from-cyan-400 to-cyan-600' },
      total_commfee: { label: 'Commfee', value: formatRupiah(summary.value.total_commfee), color: 'from-indigo-400 to-indigo-600' },
      active_promos: { label: 'Promo Aktif', value: summary.value.active_promos, color: 'from-teal-400 to-teal-600' },
      investor_count: { label: 'Investor', value: summary.value.investor_count, color: 'from-gray-400 to-gray-600' },
    }

    const dateArr = getDateRange(dateFrom.value, dateTo.value)
    const salesMap = Object.fromEntries((salesChart.value || []).map(x => [x.tgl, Number(x.total) || 0]))
    salesChartOptions.value.xaxis.categories = dateArr
    salesChartSeries.value = [{ name: 'Penjualan', data: dateArr.map(tgl => salesMap[tgl] || 0) }]

    paymentPieOptions.value = {
      ...paymentPieOptions.value,
      labels: Object.keys(res.data.payment_pie || {}),
      tooltip: {
        y: { formatter: val => formatRupiah(val) },
        custom: function({ series, seriesIndex, w }) {
          return `<div class='px-3 py-2 rounded bg-yellow-400 text-white font-bold'>${w.globals.labels[seriesIndex]}: ${formatRupiah(series[seriesIndex])}</div>`;
        }
      }
    }
    paymentPieSeries.value = Object.values(res.data.payment_pie || {})

    salesPerModeOptions.value = {
      ...salesPerModeOptions.value,
      labels: salesPerMode.value.map(x => x.mode || 'Tanpa Mode'),
      tooltip: {
        y: { formatter: val => formatRupiah(val) },
        custom: function({ series, seriesIndex, w }) {
          return `<div class='px-3 py-2 rounded bg-yellow-400 text-white font-bold'>${w.globals.labels[seriesIndex]}: ${formatRupiah(series[seriesIndex])}</div>`;
        }
      }
    }
    salesPerModeSeries.value = salesPerMode.value.map(x => Number(x.total) || 0)

    await fetchRollingForecast()
  } finally {
    loading.value = false
  }
}

function formatRupiah(val) {
  if (typeof val !== 'number') val = Number(val) || 0
  return 'Rp ' + val.toLocaleString('id-ID')
}

function formatRupiahShort(val) {
  if (typeof val !== 'number') val = Number(val) || 0
  if (Math.abs(val) >= 1_000_000) {
    return 'Rp ' + (val / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'jt'
  }
  return 'Rp ' + val.toLocaleString('id-ID', { maximumFractionDigits: 0 })
}
</script>

<script>
export default {
  components: { apexchart: VueApexCharts }
}
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(30px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-fade-in {
  animation: fade-in 0.7s cubic-bezier(.4,2,.3,1) both;
}
</style> 