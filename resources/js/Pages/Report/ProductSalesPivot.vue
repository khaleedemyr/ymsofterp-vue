<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
  canSelectOutlet: { type: Boolean, default: false },
  defaultDateFrom: { type: String, default: '' },
  defaultDateTo: { type: String, default: '' },
})

const page = usePage()
const user = computed(() => page.props.auth?.user || {})

const filters = reactive({
  date_from: props.defaultDateFrom || '',
  date_to: props.defaultDateTo || '',
  outlets: [],
  products: [],
})

const outletOptions = ref([])
const productOptions = ref([])
const loading = ref(false)
const exporting = ref(false)
const search = ref('')
const hasLoaded = ref(false)
const errorMessage = ref('')

const report = ref({
  outlets: [],
  rows: [],
  outlet_totals: {},
  grand_total_qty: 0,
  grand_total_revenue: 0,
})

function formatNumber(value, fraction = 0) {
  const num = Number(value)
  if (!Number.isFinite(num)) return '-'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: fraction,
    maximumFractionDigits: fraction,
  }).format(num)
}

function formatCurrency(value) {
  return formatNumber(value, 0)
}

const filteredRows = computed(() => {
  if (!search.value.trim()) return report.value.rows
  const q = search.value.trim().toLowerCase()
  return report.value.rows.filter((row) => row.product_name.toLowerCase().includes(q))
})

const selectedOutletIds = computed(() => filters.outlets.map((o) => o.id))
const selectedProductNames = computed(() => filters.products.map((p) => p.name))

async function fetchOutlets() {
  const { data } = await axios.get('/api/outlets/report')
  outletOptions.value = (data.outlets || []).map((o) => ({
    id: Number(o.id),
    name: o.name,
    qr_code: o.qr_code,
  }))

  if (!props.canSelectOutlet && outletOptions.value.length === 1) {
    filters.outlets = [outletOptions.value[0]]
  }
}

let productSearchTimer = null

async function fetchProducts(query = '') {
  const { data } = await axios.get('/api/report/product-sales-pivot/products', {
    params: { q: query },
  })
  productOptions.value = (data.products || []).map((p) => ({
    name: p.name,
    item_id: p.item_id,
  }))
}

function onProductSearch(query) {
  clearTimeout(productSearchTimer)
  productSearchTimer = setTimeout(() => fetchProducts(query), 300)
}

async function loadReport() {
  if (!filters.date_from || !filters.date_to) {
    errorMessage.value = 'Tanggal from dan to wajib diisi.'
    return
  }

  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.get('/api/report/product-sales-pivot', {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        outlet_ids: selectedOutletIds.value,
        product_names: selectedProductNames.value,
      },
    })
    report.value = data
    hasLoaded.value = true
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'Gagal memuat report.'
  } finally {
    loading.value = false
  }
}

async function exportExcel() {
  if (!hasLoaded.value) return
  exporting.value = true
  try {
    const params = new URLSearchParams({
      date_from: filters.date_from,
      date_to: filters.date_to,
      outlet_ids: JSON.stringify(selectedOutletIds.value),
      product_names: JSON.stringify(selectedProductNames.value),
    })
    window.open(`/report/product-sales-pivot/export?${params.toString()}`, '_blank')
  } finally {
    exporting.value = false
  }
}

function cellValue(row, outletId, field) {
  return row.outlets?.[outletId]?.[field] ?? 0
}

function outletTotal(outletId, field) {
  return report.value.outlet_totals?.[outletId]?.[field] ?? 0
}

onMounted(async () => {
  await fetchOutlets()
  await fetchProducts()
})
</script>

<template>
  <div class="mx-auto max-w-[100%] px-3 py-8 sm:px-4 lg:px-6">
    <div class="mb-6 rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-900 p-6 text-white shadow-lg">
      <p class="text-xs uppercase tracking-[0.2em] text-slate-300">Custom Report</p>
      <h1 class="mt-1 text-2xl font-semibold">Product Sales by Outlet</h1>
      <p class="mt-2 max-w-3xl text-sm text-slate-300">
        Pivot penjualan menu per produk dan outlet. Pilih item, outlet, dan rentang tanggal sesuai kebutuhan.
      </p>
    </div>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <div class="xl:col-span-2">
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date From</label>
          <input
            v-model="filters.date_from"
            type="date"
            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
          />
        </div>
        <div class="xl:col-span-2">
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date To</label>
          <input
            v-model="filters.date_to"
            type="date"
            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
          />
        </div>
        <div class="xl:col-span-4">
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Outlet</label>
          <Multiselect
            v-model="filters.outlets"
            :options="outletOptions"
            :multiple="true"
            :close-on-select="false"
            :clear-on-select="false"
            :preserve-search="true"
            label="name"
            track-by="id"
            placeholder="Semua outlet (kosongkan = tampilkan semua)"
            :disabled="!canSelectOutlet && outletOptions.length <= 1"
            @open="fetchOutlets"
          />
        </div>
        <div class="xl:col-span-4">
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Product</label>
          <Multiselect
            v-model="filters.products"
            :options="productOptions"
            :multiple="true"
            :close-on-select="false"
            :clear-on-select="false"
            :preserve-search="true"
            :internal-search="false"
            label="name"
            track-by="name"
            placeholder="Semua produk (kosongkan = tampilkan semua)"
            @open="fetchProducts('')"
            @search-change="onProductSearch"
          />
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-3">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="loading"
          @click="loadReport"
        >
          <i v-if="loading" class="fa-solid fa-spinner fa-spin"></i>
          <i v-else class="fa-solid fa-table"></i>
          {{ loading ? 'Loading...' : 'Generate Report' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="!hasLoaded || exporting"
          @click="exportExcel"
        >
          <i :class="exporting ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-file-excel'"></i>
          Export Excel
        </button>
        <input
          v-model="search"
          type="text"
          placeholder="Cari produk..."
          class="min-w-[220px] flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
        />
      </div>

      <p v-if="errorMessage" class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ errorMessage }}
      </p>
    </div>

    <div v-if="!hasLoaded && !loading" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center text-slate-500">
      Atur filter lalu klik <strong>Generate Report</strong>.
    </div>

    <div v-else-if="loading" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-slate-500 shadow-sm">
      <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat data penjualan...
    </div>

    <div v-else class="space-y-4">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-500">Total Products</p>
          <p class="mt-1 text-2xl font-semibold text-slate-900">{{ formatNumber(filteredRows.length) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-500">Total Qty Sold</p>
          <p class="mt-1 text-2xl font-semibold text-indigo-700">{{ formatNumber(report.grand_total_qty, 0) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-500">Total Revenue</p>
          <p class="mt-1 text-2xl font-semibold text-emerald-700">Rp {{ formatCurrency(report.grand_total_revenue) }}</p>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-slate-900 text-white">
                <th
                  rowspan="2"
                  class="sticky left-0 z-20 min-w-[240px] border-r border-slate-700 bg-slate-900 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                >
                  Product
                </th>
                <th
                  rowspan="2"
                  class="sticky left-[240px] z-20 min-w-[120px] border-r border-slate-700 bg-slate-900 px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide"
                >
                  Price
                </th>
                <th
                  v-for="outlet in report.outlets"
                  :key="'head-' + outlet.id"
                  colspan="2"
                  class="border-r border-slate-700 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide"
                >
                  {{ outlet.name }}
                </th>
                <th colspan="2" class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide">Total</th>
              </tr>
              <tr class="bg-slate-800 text-slate-100">
                <template v-for="outlet in report.outlets" :key="'sub-' + outlet.id">
                  <th class="min-w-[90px] border-r border-slate-700 px-3 py-2 text-right text-[11px] font-medium uppercase">Qty Sld</th>
                  <th class="min-w-[120px] border-r border-slate-700 px-3 py-2 text-right text-[11px] font-medium uppercase">Revenue</th>
                </template>
                <th class="min-w-[90px] px-3 py-2 text-right text-[11px] font-medium uppercase">Qty Sld</th>
                <th class="min-w-[120px] px-3 py-2 text-right text-[11px] font-medium uppercase">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredRows.length">
                <td :colspan="2 + report.outlets.length * 2 + 2" class="px-4 py-12 text-center text-slate-400">
                  Tidak ada data untuk filter yang dipilih.
                </td>
              </tr>
              <tr
                v-for="(row, idx) in filteredRows"
                :key="row.product_name"
                class="border-t border-slate-100 transition hover:bg-indigo-50/40"
                :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/70'"
              >
                <td class="sticky left-0 z-10 border-r border-slate-100 bg-inherit px-4 py-3 font-medium text-slate-800">
                  {{ row.product_name }}
                </td>
                <td class="sticky left-[240px] z-10 border-r border-slate-100 bg-inherit px-4 py-3 text-right text-slate-700">
                  {{ formatCurrency(row.price) }}
                </td>
                <template v-for="outlet in report.outlets" :key="row.product_name + '-' + outlet.id">
                  <td class="px-3 py-3 text-right tabular-nums text-slate-700">
                    {{ cellValue(row, outlet.id, 'qty') ? formatNumber(cellValue(row, outlet.id, 'qty'), 0) : '-' }}
                  </td>
                  <td class="border-r border-slate-100 px-3 py-3 text-right tabular-nums text-slate-800">
                    {{ cellValue(row, outlet.id, 'revenue') ? formatCurrency(cellValue(row, outlet.id, 'revenue')) : '-' }}
                  </td>
                </template>
                <td class="px-3 py-3 text-right font-medium tabular-nums text-indigo-700">
                  {{ formatNumber(row.total_qty, 0) }}
                </td>
                <td class="px-3 py-3 text-right font-semibold tabular-nums text-emerald-700">
                  {{ formatCurrency(row.total_revenue) }}
                </td>
              </tr>
            </tbody>
            <tfoot v-if="filteredRows.length">
              <tr class="border-t-2 border-slate-300 bg-slate-100 font-semibold text-slate-900">
                <td class="sticky left-0 z-10 border-r border-slate-200 bg-slate-100 px-4 py-3">Grand Total</td>
                <td class="sticky left-[240px] z-10 border-r border-slate-200 bg-slate-100 px-4 py-3"></td>
                <template v-for="outlet in report.outlets" :key="'total-' + outlet.id">
                  <td class="px-3 py-3 text-right tabular-nums">{{ formatNumber(outletTotal(outlet.id, 'qty'), 0) }}</td>
                  <td class="border-r border-slate-200 px-3 py-3 text-right tabular-nums">
                    {{ formatCurrency(outletTotal(outlet.id, 'revenue')) }}
                  </td>
                </template>
                <td class="px-3 py-3 text-right tabular-nums text-indigo-800">{{ formatNumber(report.grand_total_qty, 0) }}</td>
                <td class="px-3 py-3 text-right tabular-nums text-emerald-800">{{ formatCurrency(report.grand_total_revenue) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.multiselect) {
  min-height: 42px;
  border-radius: 0.75rem;
}
:deep(.multiselect__tags) {
  min-height: 42px;
  border-radius: 0.75rem;
  border-color: rgb(203 213 225);
  padding-top: 8px;
}
:deep(.multiselect__option--highlight) {
  background: rgb(79 70 229);
}
</style>
