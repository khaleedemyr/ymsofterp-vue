<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-bar text-blue-500"></i> Laporan Produksi WIP
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Filter Laporan</h2>
        <form @submit.prevent="applyFilter" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
            <input type="date" v-model="filters.start_date" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
            <input type="date" v-model="filters.end_date" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
              <i class="fa fa-search mr-2"></i> Filter
            </button>
          </div>
        </form>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
              <i class="fa-solid fa-industry text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Total Produksi</p>
              <p class="text-2xl font-bold text-gray-900">{{ productions.length }}</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
              <i class="fa-solid fa-box text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Total Qty Jadi</p>
              <p class="text-2xl font-bold text-gray-900">{{ totalQtyJadi }}</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
              <i class="fa-solid fa-calendar text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Periode</p>
              <p class="text-lg font-bold text-gray-900">{{ periodText }}</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
              <i class="fa-solid fa-store text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Outlet Aktif</p>
              <p class="text-2xl font-bold text-gray-900">{{ uniqueOutlets }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Production Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-800">Data Produksi WIP</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Jadi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exp Date</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="production in productions" :key="production.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(production.production_date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.batch_number || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.warehouse_outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ production.item_name }}</td>
                                 <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatNumber(production.qty) }}</td>
                 <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatNumber(production.qty_jadi) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ getUnitName(production.unit_id) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span v-if="production.exp_date" :class="isExpired(production.exp_date) ? 'text-red-600' : 'text-green-600'" class="font-semibold">
                    {{ formatDate(production.exp_date) }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Export Button -->
      <div class="mt-6 flex justify-end">
        <button @click="exportToExcel" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
          <i class="fa fa-file-excel mr-2"></i> Export Excel
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  productions: Array,
  start_date: String,
  end_date: String,
})

const filters = ref({
  start_date: props.start_date || '',
  end_date: props.end_date || '',
})

const totalQtyJadi = computed(() => {
  return props.productions.reduce((total, prod) => total + parseFloat(prod.qty_jadi || 0), 0)
})

const periodText = computed(() => {
  if (filters.value.start_date && filters.value.end_date) {
    return `${formatDate(filters.value.start_date)} - ${formatDate(filters.value.end_date)}`
  }
  return 'Semua Periode'
})

const uniqueOutlets = computed(() => {
  const outlets = new Set(props.productions.map(prod => prod.outlet_name))
  return outlets.size
})

function goBack() {
  router.visit(route('outlet-wip.index'))
}

function applyFilter() {
  router.get(route('outlet-wip.report'), {
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
  }, {
    preserveState: true,
    replace: true
  })
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID')
}

function formatNumber(value) {
  if (value === null || value === undefined) return '0.00'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

function getUnitName(unitId) {
  // This would need to be implemented based on your unit data structure
  return 'Unit'
}

function isExpired(expDate) {
  return new Date(expDate) < new Date()
}

function exportToExcel() {
  // Implement Excel export functionality
  alert('Export Excel functionality would be implemented here')
}
</script>
