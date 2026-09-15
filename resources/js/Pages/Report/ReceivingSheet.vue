<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-receipt"></i> Receiving Sheet Report
      </h1>

      <!-- Filters -->
      <div class="bg-gray-50 rounded-xl p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select 
              v-model="filters.outlet" 
              :disabled="user.id_outlet != 1"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
              @change="loadReport"
            >
              <option v-if="user.id_outlet == 1" value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
            <input 
              type="date" 
              v-model="filters.date_from"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              @change="loadReport"
            >
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
            <input 
              type="date" 
              v-model="filters.date_to"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              @change="loadReport"
            >
          </div>
          <div class="flex items-end">
            <button 
              @click="loadReport"
              class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium"
            >
              <i class="fa-solid fa-search mr-2"></i> Cari
            </button>
          </div>
          <div class="flex items-end">
            <button 
              @click="exportExcel"
              class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium"
            >
              <i class="fa-solid fa-file-excel mr-2"></i> Export Excel
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 mb-8">
        <div class="summary-card gradient-blue">
          <div class="summary-label">Total Omzet</div>
          <div class="summary-value">{{ formatCurrency(summary.total_omzet) }}</div>
        </div>
        <div class="summary-card gradient-green">
          <div class="summary-label">Total Cost</div>
          <div class="summary-value">{{ formatCurrency(summary.total_cost) }}</div>
        </div>
        <div class="summary-card gradient-yellow">
          <div class="summary-label">Rata-rata % Cost</div>
          <div class="summary-value">{{ summary.avg_persentase_cost.toFixed(2) }}%</div>
        </div>
        <div class="summary-card gradient-purple">
          <div class="summary-label">Total Hari</div>
          <div class="summary-value">{{ report.length }}</div>
        </div>
      </div>

      <!-- Report Table -->
      <div v-if="report.length > 0" class="overflow-x-auto mb-8">
        <table class="min-w-full rounded-2xl overflow-hidden shadow-lg text-sm">
          <thead>
            <tr class="font-bold">
              <th class="px-4 py-3 text-left bg-slate-600 text-white">No</th>
              <th class="px-4 py-3 text-left bg-sky-600 text-white">Tanggal</th>
              <th class="px-4 py-3 text-right bg-emerald-600 text-white">Omzet</th>
              <th
                v-for="wh in warehouseColumns"
                :key="'wh-'+wh.key"
                class="px-4 py-3 text-right bg-indigo-600 text-white"
              >
                {{ wh.name }}
              </th>
              <th
                v-for="sp in suppliers"
                :key="'sp-'+sp.id"
                class="px-4 py-3 text-right bg-amber-600 text-white"
              >
                {{ sp.name }}
              </th>
              <th class="px-4 py-3 text-right bg-rose-600 text-white">Cost</th>
              <th class="px-4 py-3 text-right bg-violet-600 text-white">% Cost</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, index) in report"
              :key="row.tanggal"
              class="border-b last:border-b-0 hover:bg-blue-50/60"
            >
              <td class="px-4 py-3 bg-slate-50 text-slate-700">{{ index + 1 }}</td>
              <td class="px-4 py-3 bg-sky-50 text-sky-900 font-medium">{{ formatDate(row.tanggal) }}</td>
              <td class="px-4 py-3 bg-emerald-50 text-emerald-900 text-right font-medium">
                {{ formatCurrency(row.omzet) }}
              </td>
              <td
                v-for="wh in warehouseColumns"
                :key="'wh-'+wh.key"
                class="px-4 py-3 bg-indigo-50 text-indigo-900 text-right font-medium"
              >
                <button
                  v-if="Number(row[wh.key]) > 0"
                  type="button"
                  class="underline decoration-dotted underline-offset-2 hover:text-indigo-700"
                  @click="openDetail('warehouse', wh.key, wh.name, row.tanggal, row[wh.key])"
                >
                  {{ formatCurrency(row[wh.key]) }}
                </button>
                <span v-else>{{ formatCurrency(0) }}</span>
              </td>
              <td
                v-for="sp in suppliers"
                :key="'sp-'+sp.id"
                class="px-4 py-3 bg-amber-50 text-amber-900 text-right font-medium"
              >
                <button
                  v-if="Number(row['supplier_' + sp.id]) > 0"
                  type="button"
                  class="underline decoration-dotted underline-offset-2 hover:text-amber-700"
                  @click="openDetail('supplier', String(sp.id), sp.name, row.tanggal, row['supplier_' + sp.id])"
                >
                  {{ formatCurrency(row['supplier_' + sp.id]) }}
                </button>
                <span v-else>{{ formatCurrency(0) }}</span>
              </td>
              <td class="px-4 py-3 bg-rose-50 text-rose-900 text-right font-semibold">
                {{ formatCurrency(row.cost) }}
              </td>
              <td class="px-4 py-3 bg-violet-50 text-right">
                <span 
                  :class="[
                    'font-bold px-2 py-1 rounded text-sm',
                    row.persentase_cost > 50 ? 'bg-red-100 text-red-800' : 
                    row.persentase_cost > 30 ? 'bg-yellow-100 text-yellow-800' : 
                    'bg-green-100 text-green-800'
                  ]"
                >
                  {{ (Number(row.persentase_cost) || 0).toFixed(2) }}%
                </span>
              </td>
            </tr>

            <!-- Grand Total -->
            <tr class="border-t-2 border-slate-400 font-bold">
              <td class="px-4 py-3 bg-slate-700 text-white" colspan="2">GRAND TOTAL</td>
              <td class="px-4 py-3 bg-emerald-700 text-white text-right">{{ formatCurrency(grandTotal.omzet) }}</td>
              <td
                v-for="wh in warehouseColumns"
                :key="'gt-wh-'+wh.key"
                class="px-4 py-3 bg-indigo-700 text-white text-right"
              >
                {{ formatCurrency(grandTotal.warehouses[wh.key] || 0) }}
              </td>
              <td
                v-for="sp in suppliers"
                :key="'gt-sp-'+sp.id"
                class="px-4 py-3 bg-amber-700 text-white text-right"
              >
                {{ formatCurrency(grandTotal.suppliers[sp.id] || 0) }}
              </td>
              <td class="px-4 py-3 bg-rose-700 text-white text-right">{{ formatCurrency(grandTotal.cost) }}</td>
              <td class="px-4 py-3 bg-violet-700 text-white text-right">
                <span class="font-bold px-2 py-1 rounded text-sm bg-white/20">
                  {{ grandTotal.avg_persentase_cost.toFixed(2) }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Chart -->
      <div v-if="report.length > 0" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg mb-4">
        <div class="mb-4">
          <h3 class="text-lg font-bold text-slate-800">Omzet vs Cost Trend</h3>
          <p class="text-sm text-slate-500">Perbandingan harian omzet, cost, dan % cost</p>
        </div>
        <VueApexCharts type="line" height="380" :options="chartOptions" :series="chartSeries" />
      </div>

      <div v-if="report.length === 0" class="text-center py-12">
        <div class="text-gray-400 text-lg">
          <i class="fa-solid fa-inbox text-4xl mb-4"></i>
          <p>Tidak ada data untuk ditampilkan</p>
        </div>
      </div>
    </div>

    <!-- Detail Modal (lazy load) -->
    <div
      v-if="detailOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="closeDetail"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-gray-900">{{ detailMeta.title }}</h2>
            <p class="text-sm text-gray-500 mt-1">
              {{ formatDate(detailMeta.date) }}
              <span v-if="detailMeta.amount != null"> · {{ formatCurrency(detailMeta.amount) }}</span>
            </p>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700 text-xl" @click="closeDetail">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="px-6 py-4 overflow-y-auto flex-1">
          <div v-if="detailLoading" class="py-12 text-center text-gray-500">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat detail...
          </div>
          <div v-else-if="detailError" class="py-8 text-center text-red-600">
            {{ detailError }}
          </div>
          <div v-else-if="!detailData?.transactions?.length" class="py-8 text-center text-gray-500">
            Tidak ada transaksi.
          </div>
          <div v-else class="space-y-5">
            <div
              v-for="(txn, idx) in detailData.transactions"
              :key="idx"
              class="border border-gray-200 rounded-xl overflow-hidden"
            >
              <div class="bg-gray-50 px-4 py-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <div><span class="text-gray-500">Tipe:</span> <strong>{{ txn.source }}</strong></div>
                <div><span class="text-gray-500">No. Transaksi:</span> <strong>{{ txn.number || '-' }}</strong></div>
                <div v-if="txn.ro_number"><span class="text-gray-500">No. RO/FO:</span> <strong>{{ txn.ro_number }}</strong></div>
                <div><span class="text-gray-500">Order/Belanja oleh:</span> <strong>{{ txn.ordered_by || '-' }}</strong></div>
                <div class="ml-auto"><span class="text-gray-500">Total:</span> <strong>{{ formatCurrency(txn.total) }}</strong></div>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="bg-white border-b text-gray-600">
                      <th class="px-4 py-2 text-left">Item</th>
                      <th class="px-4 py-2 text-right">Qty</th>
                      <th class="px-4 py-2 text-left">Unit</th>
                      <th class="px-4 py-2 text-right">Harga</th>
                      <th class="px-4 py-2 text-right">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, i) in txn.items" :key="i" class="border-b last:border-b-0">
                      <td class="px-4 py-2">{{ item.name }}</td>
                      <td class="px-4 py-2 text-right">{{ item.qty }}</td>
                      <td class="px-4 py-2">{{ item.unit }}</td>
                      <td class="px-4 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                      <td class="px-4 py-2 text-right font-medium">{{ formatCurrency(item.subtotal) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="px-6 py-3 border-t bg-gray-50 flex justify-between items-center">
          <div class="text-sm text-gray-600">
            Grand total: <strong>{{ formatCurrency(detailData?.grand_total || 0) }}</strong>
          </div>
          <button
            type="button"
            class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-black"
            @click="closeDetail"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps({
  report: {
    type: Array,
    default: () => []
  },
  outlets: {
    type: Array,
    default: () => []
  },
  warehouseColumns: {
    type: Array,
    default: () => [
      { key: 'main_store', name: 'Main Store' },
      { key: 'mk1', name: 'MK1 Hot Kitchen' },
      { key: 'mk2', name: 'MK2 Cold Kitchen' },
    ]
  },
  suppliers: {
    type: Array,
    default: () => []
  },
  filters: {
    type: Object,
    default: () => ({})
  },
  user: {
    type: Object,
    required: true
  }
})

const filters = ref({
  outlet: props.filters.outlet || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || ''
})

if (props.user.id_outlet != 1) {
  filters.value.outlet = props.user.id_outlet
}

const summary = computed(() => {
  const total_omzet = props.report.reduce((sum, row) => sum + (Number(row.omzet) || 0), 0)
  const total_cost = props.report.reduce((sum, row) => sum + (Number(row.cost) || 0), 0)
  const avg_persentase_cost = props.report.length > 0 
    ? props.report.reduce((sum, row) => sum + (Number(row.persentase_cost) || 0), 0) / props.report.length 
    : 0
  return {
    total_omzet,
    total_cost,
    avg_persentase_cost
  }
})

const grandTotal = computed(() => {
  const warehouses = {}
  props.warehouseColumns.forEach((wh) => {
    warehouses[wh.key] = props.report.reduce((sum, row) => sum + (Number(row[wh.key]) || 0), 0)
  })
  const suppliers = {}
  props.suppliers.forEach((sp) => {
    suppliers[sp.id] = props.report.reduce((sum, row) => sum + (Number(row['supplier_' + sp.id]) || 0), 0)
  })
  return {
    omzet: summary.value.total_omzet,
    cost: summary.value.total_cost,
    avg_persentase_cost: summary.value.avg_persentase_cost,
    warehouses,
    suppliers,
  }
})

const chartRows = computed(() => {
  // Chronological for chart (table may be descending)
  return [...props.report].sort((a, b) => String(a.tanggal).localeCompare(String(b.tanggal)))
})

const chartCategories = computed(() => {
  return chartRows.value.map((row) => {
    const d = new Date(String(row.tanggal) + 'T12:00:00')
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
  })
})

const chartSeries = computed(() => [
  {
    name: 'Omzet',
    data: chartRows.value.map((row) => Number(row.omzet) || 0),
  },
  {
    name: 'Cost',
    data: chartRows.value.map((row) => Number(row.cost) || 0),
  },
  {
    name: '% Cost',
    data: chartRows.value.map((row) => Number(row.persentase_cost) || 0),
  },
])

const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 380,
    toolbar: { show: true },
    animations: { enabled: true, easing: 'easeinout', speed: 800 },
    zoom: { enabled: false },
    fontFamily: 'inherit',
  },
  stroke: {
    width: [3, 3, 2.5],
    curve: 'smooth',
    dashArray: [0, 0, 5],
  },
  markers: {
    size: 4,
    hover: { size: 7 },
  },
  colors: ['#059669', '#e11d48', '#7c3aed'],
  dataLabels: { enabled: false },
  xaxis: {
    categories: chartCategories.value,
    title: { text: 'Tanggal', style: { fontWeight: 600 } },
    labels: { rotate: -45, style: { fontSize: '11px', fontWeight: 600 } },
  },
  yaxis: [
    {
      seriesName: 'Omzet',
      title: { text: 'Omzet / Cost (Rp)', style: { fontWeight: 600 } },
      labels: {
        style: { fontWeight: 600 },
        formatter: (val) => {
          if (val >= 1_000_000) return (val / 1_000_000).toFixed(1) + ' jt'
          if (val >= 1_000) return (val / 1_000).toFixed(0) + ' rb'
          return Math.round(val).toLocaleString('id-ID')
        },
      },
    },
    {
      seriesName: 'Cost',
      show: false,
    },
    {
      seriesName: '% Cost',
      opposite: true,
      title: { text: '% Cost', style: { fontWeight: 600 } },
      labels: {
        style: { fontWeight: 600 },
        formatter: (val) => `${Number(val || 0).toFixed(0)}%`,
      },
    },
  ],
  legend: {
    position: 'top',
    horizontalAlign: 'left',
    fontWeight: 600,
  },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  tooltip: {
    shared: true,
    intersect: false,
    y: {
      formatter: (val, opts) => {
        const seriesName = opts?.w?.globals?.seriesNames?.[opts.seriesIndex] || ''
        if (seriesName === '% Cost') return `${Number(val || 0).toFixed(2)}%`
        return formatCurrency(val)
      },
    },
  },
}))

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detailData = ref(null)
const detailMeta = ref({ title: '', date: '', amount: null })

const loadReport = () => {
  router.get('/report-receiving-sheet', filters.value, {
    preserveState: true,
    preserveScroll: true
  })
}

const exportExcel = () => {
  const params = new URLSearchParams()
  if (filters.value.outlet) params.set('outlet', filters.value.outlet)
  if (filters.value.date_from) params.set('date_from', filters.value.date_from)
  if (filters.value.date_to) params.set('date_to', filters.value.date_to)
  window.open(`/report-receiving-sheet/export?${params.toString()}`, '_blank')
}

const openDetail = async (type, key, label, date, amount) => {
  if (!filters.value.outlet) {
    alert('Pilih outlet terlebih dahulu')
    return
  }

  detailOpen.value = true
  detailLoading.value = true
  detailError.value = ''
  detailData.value = null
  detailMeta.value = {
    title: label,
    date,
    amount,
  }

  try {
    const res = await axios.get('/api/report/receiving-sheet-detail', {
      params: {
        type,
        key,
        date,
        outlet: filters.value.outlet,
      },
    })
    detailData.value = res.data
    if (res.data?.title) {
      detailMeta.value.title = res.data.title
    }
  } catch (e) {
    detailError.value = e?.response?.data?.error || e?.message || 'Gagal memuat detail'
  } finally {
    detailLoading.value = false
  }
}

const closeDetail = () => {
  detailOpen.value = false
  detailLoading.value = false
  detailError.value = ''
  detailData.value = null
}

const formatCurrency = (value) => {
  const num = Number(value) || 0
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(num)
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>

<style scoped>
.summary-card {
  @apply bg-white rounded-xl p-6 shadow-lg border border-gray-200;
  background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
  position: relative;
  overflow: hidden;
}

.summary-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
  pointer-events: none;
}

.gradient-blue {
  --gradient-start: #3b82f6;
  --gradient-end: #1d4ed8;
}

.gradient-green {
  --gradient-start: #10b981;
  --gradient-end: #059669;
}

.gradient-yellow {
  --gradient-start: #f59e0b;
  --gradient-end: #d97706;
}

.gradient-purple {
  --gradient-start: #8b5cf6;
  --gradient-end: #7c3aed;
}

.summary-label {
  @apply text-white text-sm font-medium mb-2 opacity-90;
}

.summary-value {
  @apply text-white text-2xl font-bold;
  text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
