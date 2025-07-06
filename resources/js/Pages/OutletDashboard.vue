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
import { ref, onMounted } from 'vue'
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

const page = usePage()
const user = page.props.auth?.user || { id_outlet: 1, nama_outlet: 'Outlet Demo' }
const outlets = ref([])
const selectedOutlet = ref(user.id_outlet)
const dateFrom = ref(getFirstDayOfMonth())
const dateTo = ref(getLastDayOfMonth())

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

async function fetchDashboard() {
  loading.value = true
  try {
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
  } finally {
    loading.value = false
  }
}

function formatRupiah(val) {
  if (typeof val !== 'number') val = Number(val) || 0
  return 'Rp ' + val.toLocaleString('id-ID')
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