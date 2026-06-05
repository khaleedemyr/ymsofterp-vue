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
              <option :value="null">— Semua (hanya rekap outlet) —</option>
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

      <template v-if="outlet_summary">
        <div v-if="report && activeTab === 'detail'" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
            <p class="text-xs text-gray-500">Budget bulan ini (Db)</p>
            <p class="text-lg font-bold">{{ formatCurrency(report.summary.monthly_budget) }}</p>
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

        <div class="flex flex-wrap gap-2 mb-4">
          <button
            type="button"
            class="px-4 py-2 rounded-lg text-sm font-semibold border transition-colors"
            :class="activeTab === 'detail' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            @click="activeTab = 'detail'"
          >
            Detail Transaksi
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-lg text-sm font-semibold border transition-colors"
            :class="activeTab === 'outlet' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            @click="activeTab = 'outlet'"
          >
            Rekap per Outlet
          </button>
        </div>

        <div v-show="activeTab === 'detail'">
          <div v-if="!report" class="text-center text-gray-500 py-12 bg-white rounded-xl shadow-lg">
            Pilih category untuk menampilkan detail transaksi per kategori.
          </div>
          <div v-else class="bg-white rounded-xl shadow-lg overflow-x-auto">
            <table class="min-w-full text-sm border-collapse">
              <thead>
                <tr class="bg-slate-700 text-white">
                  <th class="px-2 py-2 text-center border w-10"></th>
                  <th class="px-3 py-2 text-left border">NO.</th>
                  <th class="px-3 py-2 text-left border">TANGGAL</th>
                  <th class="px-3 py-2 text-left border">OUTLET</th>
                  <th class="px-3 py-2 text-left border">{{ report.category.name }}</th>
                  <th class="px-3 py-2 text-right border">Db</th>
                  <th class="px-3 py-2 text-right border">Cr</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="row in report.rows" :key="row.row_key || row.no">
                  <tr
                    :class="[
                      row.row_type === 'debit' ? 'bg-blue-50/40' : '',
                      isExpandableRow(row) ? 'cursor-pointer hover:bg-slate-50' : '',
                    ]"
                    @click="isExpandableRow(row) ? toggleExpand(row) : null"
                  >
                    <td class="px-2 py-1.5 border text-center">
                      <i
                        v-if="isExpandableRow(row)"
                        :class="[
                          'fas text-xs transition-transform duration-200',
                          isExpanded(row.row_key) ? 'fa-chevron-down text-indigo-600' : 'fa-chevron-right text-gray-400',
                        ]"
                      ></i>
                    </td>
                    <td class="px-3 py-1.5 border text-center">{{ row.no }}</td>
                    <td class="px-3 py-1.5 border whitespace-nowrap">{{ row.date_label }}</td>
                    <td class="px-3 py-1.5 border">{{ row.outlet }}</td>
                    <td class="px-3 py-1.5 border">{{ row.description }}</td>
                    <td class="px-3 py-1.5 border text-right font-mono">{{ formatDebit(row.debit) }}</td>
                    <td class="px-3 py-1.5 border text-right font-mono">{{ formatCredit(row.credit) }}</td>
                  </tr>
                  <tr v-if="isExpandableRow(row) && isExpanded(row.row_key)" class="bg-gray-50">
                    <td colspan="7" class="px-4 py-3 border">
                      <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-4 py-2 bg-gray-100 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase">
                          Detail Item
                        </div>
                        <div v-if="isRowItemsLoading(row.row_key)" class="px-4 py-6 text-center text-gray-500 text-sm">
                          Memuat detail item...
                        </div>
                        <table v-else class="min-w-full text-sm">
                          <thead>
                            <tr class="bg-gray-50 text-gray-600">
                              <th class="px-4 py-2 text-left font-medium">Item</th>
                              <th class="px-4 py-2 text-right font-medium">Qty</th>
                              <th class="px-4 py-2 text-left font-medium">Unit</th>
                              <th class="px-4 py-2 text-right font-medium">Harga</th>
                              <th class="px-4 py-2 text-right font-medium">Subtotal</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(item, idx) in getRowItems(row)"
                              :key="`${row.row_key}-item-${idx}`"
                              class="border-t border-gray-100"
                            >
                              <td class="px-4 py-2">{{ item.item }}</td>
                              <td class="px-4 py-2 text-right font-mono">{{ formatQty(item.qty) }}</td>
                              <td class="px-4 py-2">{{ item.unit || '-' }}</td>
                              <td class="px-4 py-2 text-right font-mono">{{ formatCurrency(item.price) }}</td>
                              <td class="px-4 py-2 text-right font-mono">{{ formatCurrency(item.subtotal) }}</td>
                            </tr>
                            <tr v-if="!getRowItems(row).length" class="border-t border-gray-100">
                              <td colspan="5" class="px-4 py-3 text-center text-gray-400">Tidak ada detail item.</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
              <tfoot>
                <tr class="bg-gray-100 font-semibold">
                  <td colspan="5" class="px-3 py-2 border text-right">TOTAL</td>
                  <td class="px-3 py-2 border text-right font-mono">{{ formatDebit(report.summary.total_debit) }}</td>
                  <td class="px-3 py-2 border text-right font-mono">{{ formatCredit(report.summary.total_credit) }}</td>
                </tr>
                <tr class="bg-green-50 font-bold">
                  <td colspan="5" class="px-3 py-2 border text-right">SISA SALDO</td>
                  <td class="px-3 py-2 border text-right font-mono" colspan="2">{{ formatCurrency(report.summary.ending_balance) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div v-show="activeTab === 'outlet'">
          <p class="text-sm text-gray-500 mb-3">
            Semua kategori — {{ outlet_summary.period?.label }}
          </p>
          <div class="bg-white rounded-xl shadow-lg overflow-x-auto max-w-xl">
            <table class="min-w-full text-sm border-collapse">
              <thead>
                <tr class="bg-slate-700 text-white">
                  <th class="px-4 py-2 text-left border">Outlet</th>
                  <th class="px-4 py-2 text-right border">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in outlet_summary.rows"
                  :key="row.outlet_id ?? row.outlet"
                  class="hover:bg-gray-50"
                >
                  <td class="px-4 py-1.5 border">{{ row.outlet }}</td>
                  <td class="px-4 py-1.5 border text-right font-mono">{{ formatCredit(row.total) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="bg-gray-100 font-bold">
                  <td class="px-4 py-2 border"></td>
                  <td class="px-4 py-2 border text-right font-mono">{{ formatCredit(outlet_summary.total) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({ category_id: null, year: new Date().getFullYear(), month: new Date().getMonth() + 1 }),
  },
  outlet_summary: { type: Object, default: null },
  report: { type: Object, default: null },
})

const filters = reactive({
  category_id: props.filters.category_id ?? null,
  year: props.filters.year ?? new Date().getFullYear(),
  month: props.filters.month ?? new Date().getMonth() + 1,
})

const expandedRows = ref([])
const rowItemsByKey = ref({})
const rowItemsLoading = ref({})
const activeTab = ref('outlet')

watch(() => props.report, () => {
  expandedRows.value = []
  rowItemsByKey.value = {}
  rowItemsLoading.value = {}
})

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

function monthLabel(m) {
  return monthNames[m - 1] || m
}

function applyFilters() {
  const params = {
    year: filters.year,
    month: filters.month,
  }
  if (filters.category_id) {
    params.category_id = filters.category_id
  }
  router.get('/mamp-report', params, { preserveState: true, preserveScroll: true })
}

function isExpandableRow(row) {
  return row?.row_type === 'credit'
}

function getRowItems(row) {
  if (rowItemsByKey.value[row.row_key]) {
    return rowItemsByKey.value[row.row_key]
  }

  return row.items || []
}

function isRowItemsLoading(rowKey) {
  return !!rowItemsLoading.value[rowKey]
}

async function loadRowItems(row) {
  if (!row?.row_key || !filters.category_id) {
    return
  }

  if (rowItemsByKey.value[row.row_key] || rowItemsLoading.value[row.row_key]) {
    return
  }

  if ((row.items || []).length > 0) {
    rowItemsByKey.value[row.row_key] = row.items
    return
  }

  rowItemsLoading.value[row.row_key] = true

  try {
    const { data } = await axios.get('/mamp-report/row-items', {
      params: {
        row_key: row.row_key,
        category_id: filters.category_id,
      },
    })
    rowItemsByKey.value[row.row_key] = data.items || []
  } catch (error) {
    console.error('Failed to load MAMP row items', error)
    rowItemsByKey.value[row.row_key] = []
  } finally {
    rowItemsLoading.value[row.row_key] = false
  }
}

async function toggleExpand(row) {
  const rowKey = row.row_key

  if (expandedRows.value.includes(rowKey)) {
    expandedRows.value = expandedRows.value.filter((key) => key !== rowKey)
    return
  }

  expandedRows.value.push(rowKey)
  await loadRowItems(row)
}

function isExpanded(rowKey) {
  return expandedRows.value.includes(rowKey)
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

function formatQty(value) {
  const n = Number(value || 0)
  return n.toLocaleString('id-ID', { maximumFractionDigits: 2 })
}
</script>
