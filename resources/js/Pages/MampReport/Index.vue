<template>
  <AppLayout title="Report MAMP">
    <div class="w-full py-8 px-4 max-w-[1600px] mx-auto">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-table-list text-indigo-600"></i>
          Report MAMP
        </h1>
        <button
          v-if="report"
          type="button"
          class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow font-semibold"
          @click="exportExcel"
        >
          <i class="fa fa-download mr-2"></i> Export Excel
        </button>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select
              v-model="filters.category_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2"
              @change="applyFilters"
            >
              <option :value="null">— Pilih category —</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">
                {{ c.name }} ({{ c.division }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
            <select v-model="filters.month" class="w-full border border-gray-300 rounded-lg px-3 py-2" @change="applyFilters">
              <option v-for="m in 12" :key="m" :value="m">{{ monthLabel(m) }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
            <input
              v-model.number="filters.year"
              type="number"
              min="2000"
              max="2100"
              class="w-full border border-gray-300 rounded-lg px-3 py-2"
              @change="applyFilters"
            />
          </div>
          <div>
            <button
              type="button"
              class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-indigo-700"
              @click="applyFilters"
            >
              Tampilkan
            </button>
          </div>
        </div>
      </div>

      <div v-if="!report && filters.category_id" class="text-center text-gray-500 py-12">Memuat...</div>
      <div v-else-if="!filters.category_id" class="text-center text-gray-500 py-12">Pilih category untuk menampilkan report.</div>

      <template v-else-if="report">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
            <p class="text-xs text-gray-500">Budget bulan ini (Db)</p>
            <p class="text-lg font-bold">{{ formatCurrency(report.summary.monthly_budget) }}</p>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-amber-500">
            <p class="text-xs text-gray-500">Sisa saldo bulan sebelumnya</p>
            <p class="text-lg font-bold">{{ formatCurrency(report.summary.opening_carry) }}</p>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-red-500">
            <p class="text-xs text-gray-500">Total Cr (keluar)</p>
            <p class="text-lg font-bold">{{ formatCurrency(report.summary.total_credit) }}</p>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-green-600">
            <p class="text-xs text-gray-500">Sisa saldo akhir</p>
            <p class="text-lg font-bold">{{ formatCurrency(report.summary.ending_balance) }}</p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
          <table class="min-w-full text-sm border-collapse">
            <thead>
              <tr class="bg-slate-700 text-white">
                <th class="px-3 py-2 text-left border">NO.</th>
                <th class="px-3 py-2 text-left border">TANGGAL</th>
                <th class="px-3 py-2 text-left border">OUTLET</th>
                <th class="px-3 py-2 text-left border">{{ report.category.name }}</th>
                <th class="px-3 py-2 text-right border">Db</th>
                <th class="px-3 py-2 text-right border">Cr</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in report.rows"
                :key="row.no"
                :class="row.row_type === 'debit' ? 'bg-blue-50/40' : ''"
              >
                <td class="px-3 py-1.5 border text-center">{{ row.no }}</td>
                <td class="px-3 py-1.5 border whitespace-nowrap">{{ row.date_label }}</td>
                <td class="px-3 py-1.5 border">{{ row.outlet }}</td>
                <td class="px-3 py-1.5 border">{{ row.description }}</td>
                <td class="px-3 py-1.5 border text-right font-mono">{{ formatDebit(row.debit) }}</td>
                <td class="px-3 py-1.5 border text-right font-mono">{{ formatCredit(row.credit) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-gray-100 font-semibold">
                <td colspan="4" class="px-3 py-2 border text-right">TOTAL</td>
                <td class="px-3 py-2 border text-right font-mono">{{ formatDebit(report.summary.total_debit) }}</td>
                <td class="px-3 py-2 border text-right font-mono">{{ formatCredit(report.summary.total_credit) }}</td>
              </tr>
              <tr class="bg-green-50 font-bold">
                <td colspan="4" class="px-3 py-2 border text-right">SISA SALDO</td>
                <td class="px-3 py-2 border text-right font-mono" colspan="2">{{ formatCurrency(report.summary.ending_balance) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({ category_id: null, year: new Date().getFullYear(), month: new Date().getMonth() + 1 }),
  },
  report: { type: Object, default: null },
})

const filters = reactive({
  category_id: props.filters.category_id ?? null,
  year: props.filters.year ?? new Date().getFullYear(),
  month: props.filters.month ?? new Date().getMonth() + 1,
})

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

function monthLabel(m) {
  return monthNames[m - 1] || m
}

function applyFilters() {
  if (!filters.category_id) return
  router.get('/mamp-report', {
    category_id: filters.category_id,
    year: filters.year,
    month: filters.month,
  }, { preserveState: true, preserveScroll: true })
}

function exportExcel() {
  if (!filters.category_id) return
  const params = new URLSearchParams({
    category_id: String(filters.category_id),
    year: String(filters.year),
    month: String(filters.month),
  })
  window.location.href = `/mamp-report/export?${params.toString()}`
}

function formatCurrency(value) {
  const n = Number(value || 0)
  return 'Rp ' + n.toLocaleString('id-ID', { maximumFractionDigits: 0 })
}

function formatDebit(value) {
  if (value === null || value === undefined || value === '') return ''
  const n = Number(value)
  if (n < 0) {
    return '(' + Math.abs(n).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ')'
  }
  return n.toLocaleString('id-ID', { maximumFractionDigits: 0 })
}

function formatCredit(value) {
  if (value === null || value === undefined || value === '') return ''
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 0 })
}
</script>
