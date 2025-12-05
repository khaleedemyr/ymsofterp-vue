<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-receipt"></i> Receiving Sheet Report
      </h1>

      <!-- Filters -->
      <div class="bg-gray-50 rounded-xl p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Outlet Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select 
              v-model="filters.outlet" 
              :disabled="user.id_outlet != 1"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              @change="loadReport"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>

          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
            <input 
              type="date" 
              v-model="filters.date_from"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              @change="loadReport"
            >
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
            <input 
              type="date" 
              v-model="filters.date_to"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              @change="loadReport"
            >
          </div>

          <!-- Search Button -->
          <div class="flex items-end">
            <button 
              @click="loadReport"
              class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium"
            >
              <i class="fa-solid fa-search mr-2"></i> Cari
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
      <div class="overflow-x-auto">
        <table class="min-w-full rounded-2xl overflow-hidden shadow-lg">
          <thead>
            <tr class="bg-[#2563eb] text-white font-bold text-base">
              <th class="px-6 py-3 text-left">No</th>
              <th class="px-6 py-3 text-left">Tanggal</th>
              <th class="px-6 py-3 text-right">Omzet</th>
              <th class="px-6 py-3 text-right">% Cost</th>
              <th class="px-6 py-3 text-right">Cost</th>
              <th v-for="wh in warehouses" :key="'wh-'+wh.id" class="px-6 py-3 text-right">{{ wh.name }}</th>
              <th class="px-6 py-3 text-right">Retail</th>
              <th v-for="sp in suppliers" :key="'sp-'+sp.id" class="px-6 py-3 text-right">{{ sp.name }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in report" :key="row.tanggal" class="bg-white border-b last:border-b-0 hover:bg-blue-50">
              <td class="px-6 py-4 text-gray-700">{{ index + 1 }}</td>
              <td class="px-6 py-4 text-gray-700 font-medium">{{ formatDate(row.tanggal) }}</td>
              <td class="px-6 py-4 text-right text-gray-700 font-medium">{{ formatCurrency(row.omzet) }}</td>
              <td class="px-6 py-4 text-right">
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
              <td class="px-6 py-4 text-right text-gray-700 font-medium">{{ formatCurrency(row.cost) }}</td>
              <td v-for="wh in warehouses" :key="'wh-'+wh.id" class="px-6 py-4 text-right text-gray-700 font-medium">
                {{ formatCurrency(row['warehouse_' + wh.id]) }}
              </td>
              <td class="px-6 py-4 text-right text-gray-700 font-medium">{{ formatCurrency(row.retail_food) }}</td>
              <td v-for="sp in suppliers" :key="'sp-'+sp.id" class="px-6 py-4 text-right text-gray-700 font-medium">
                {{ formatCurrency(row['supplier_' + sp.id]) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty State -->
      <div v-if="report.length === 0" class="text-center py-12">
        <div class="text-gray-400 text-lg">
          <i class="fa-solid fa-inbox text-4xl mb-4"></i>
          <p>Tidak ada data untuk ditampilkan</p>
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

const props = defineProps({
  report: {
    type: Array,
    default: () => []
  },
  outlets: {
    type: Array,
    default: () => []
  },
  warehouses: {
    type: Array,
    default: () => []
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

const loadReport = () => {
  router.get('/report-receiving-sheet', filters.value, {
    preserveState: true,
    preserveScroll: true
  })
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