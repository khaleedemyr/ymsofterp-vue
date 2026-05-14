<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-sack-dollar text-blue-500"></i>
          Report Pembelanjaan Supplier (Warehouse GR)
        </h1>
        <button
          type="button"
          class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="!data_loaded"
          @click="exportToExcel"
        >
          <i class="fa fa-file-excel"></i>
          Export Excel
        </button>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <p class="text-sm text-gray-500 mb-4">
          Isi filter (tanggal wajib), lalu klik <strong>Muat data</strong>. Halaman pertama tidak memanggil query sampai Anda memuat data.
        </p>
        <form @submit.prevent="loadData" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
            <input
              v-model="filters.date_from"
              type="date"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
            <input
              v-model="filters.date_to"
              type="date"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Gudang</label>
            <select
              v-model="filters.warehouse_id"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
            >
              <option value="">Semua gudang</option>
              <option
                v-for="w in (warehouses || [])"
                :key="w.id"
                :value="String(w.id)"
              >
                {{ w.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Divisi gudang</label>
            <select
              v-model="filters.warehouse_division_id"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2"
            >
              <option value="">Semua divisi</option>
              <option
                v-for="wd in filteredWarehouseDivisions"
                :key="wd.id"
                :value="String(wd.id)"
              >
                {{ wd.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
            <Multiselect
              v-model="filters.supplier_id"
              :options="suppliers"
              :searchable="true"
              :create-option="false"
              placeholder="Semua supplier"
              track-by="id"
              label="name"
            />
          </div>
          <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="GR Number, PO Number, Supplier..."
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>

          <div class="md:col-span-2 lg:col-span-7 flex gap-2">
            <button
              type="submit"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              <i class="fa fa-download mr-2"></i>Muat data
            </button>
            <button
              type="button"
              class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
              @click="clearFilters"
            >
              <i class="fa fa-times mr-2"></i>Reset
            </button>
          </div>
        </form>
      </div>

      <div
        v-if="!data_loaded"
        class="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center text-amber-900 mb-6"
      >
        <i class="fa-solid fa-filter text-3xl mb-3 text-amber-600"></i>
        <p class="font-medium">Belum ada data dimuat.</p>
        <p class="text-sm mt-1 text-amber-800">Pilih tanggal dari & sampai, lalu klik <strong>Muat data</strong>.</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-2xl p-5">
          <p class="text-sm text-gray-500">Total Supplier</p>
          <p class="text-2xl font-bold text-gray-800">{{ summary.total_suppliers || 0 }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-2xl p-5">
          <p class="text-sm text-gray-500">Total Transaksi GR</p>
          <p class="text-2xl font-bold text-gray-800">{{ summary.total_transactions || 0 }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-2xl p-5">
          <p class="text-sm text-gray-500">Grand Total Belanja</p>
          <p class="text-2xl font-bold text-blue-700">{{ formatCurrency(summary.grand_total_amount) }}</p>
        </div>
      </div>

      <div v-if="data_loaded" class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-800">Ringkasan Supplier</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total GR</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Belanja</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <template v-for="supplier in supplierReports" :key="supplier.supplier_id || supplier.supplier_name">
                <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleExpand(supplier.supplier_id)">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <i
                        class="fa-solid text-sm transition-transform duration-200"
                        :class="isExpanded(supplier.supplier_id) ? 'fa-chevron-down' : 'fa-chevron-right'"
                      ></i>
                      <div>
                        <p class="font-semibold text-gray-800">{{ supplier.supplier_name }}</p>
                        <p class="text-xs text-gray-500">{{ supplier.supplier_code }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-right">{{ supplier.total_transactions }}</td>
                  <td class="px-4 py-3 text-right font-semibold text-blue-700">{{ formatCurrency(supplier.total_amount) }}</td>
                </tr>
                <tr v-if="isExpanded(supplier.supplier_id)" class="bg-gray-50">
                  <td colspan="3" class="px-6 py-4">
                    <div class="space-y-3">
                      <p class="text-sm font-medium text-gray-600">Nilai per hari — klik baris untuk detail transaksi</p>
                      <div
                        v-for="day in (supplier.days || [])"
                        :key="day.date"
                        class="rounded-xl border border-gray-200 bg-white overflow-hidden"
                      >
                        <div
                          class="flex flex-wrap items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50"
                          @click.stop="toggleDay(supplier.supplier_id, day.date)"
                        >
                          <i
                            class="fa-solid text-sm text-gray-500 transition-transform duration-200"
                            :class="isDayExpanded(supplier.supplier_id, day.date) ? 'fa-chevron-down' : 'fa-chevron-right'"
                          ></i>
                          <div class="flex-1 min-w-[200px]">
                            <p class="font-semibold text-gray-800">{{ formatDayHeading(day.date) }}</p>
                            <p class="text-xs text-gray-500">{{ day.transaction_count }} transaksi GR</p>
                          </div>
                          <div class="text-right">
                            <p class="text-xs text-gray-500">Belanja</p>
                            <p class="font-bold text-blue-700">{{ formatCurrency(day.total_amount) }}</p>
                          </div>
                        </div>
                        <div
                          v-if="isDayExpanded(supplier.supplier_id, day.date)"
                          class="border-t border-gray-100 px-2 py-3 bg-gray-50/80"
                        >
                          <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                              <thead class="bg-gray-100">
                                <tr>
                                  <th class="px-2 py-2 w-8"></th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">GR</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">PO</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">PR</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">RO</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap min-w-[100px]">Outlet FO</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap min-w-[120px]">Pembuat FO</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase min-w-[120px]">Buat PO</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase min-w-[120px]">Terima GR</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase min-w-[120px]">Request PR</th>
                                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Total</th>
                                </tr>
                              </thead>
                              <tbody class="divide-y divide-gray-200">
                                <template v-for="trx in day.transactions" :key="trx.good_receive_id">
                                  <tr class="hover:bg-gray-50">
                                    <td
                                      class="px-2 py-2 align-top cursor-pointer text-gray-500"
                                      title="Detail item"
                                      @click.stop="toggleTrxExpand(trx.good_receive_id)"
                                    >
                                      <i
                                        class="fa-solid text-xs transition-transform duration-200"
                                        :class="isTrxExpanded(trx.good_receive_id) ? 'fa-chevron-down' : 'fa-chevron-right'"
                                      ></i>
                                    </td>
                                    <td class="px-3 py-2 text-gray-800 align-top">
                                      <div class="font-medium whitespace-nowrap">{{ trx.gr_number }}</div>
                                      <div class="text-[11px] text-gray-500 whitespace-nowrap">{{ formatDateTime(trx.gr_created_at) }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 align-top whitespace-nowrap">
                                      <div class="font-medium">{{ trx.po_number || '—' }}</div>
                                      <div class="text-[11px] text-gray-500">{{ formatDateTime(trx.po_created_at) }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 max-w-[200px] align-top" :title="trx.pr_numbers || ''">
                                      {{ trx.pr_numbers || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 max-w-[200px] align-top" :title="trx.ro_order_numbers || ''">
                                      {{ trx.ro_order_numbers || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 max-w-[160px] align-top" :title="trx.fo_outlet_names || ''">
                                      {{ trx.fo_outlet_names || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 max-w-[180px] align-top" :title="trx.fo_creator_names || ''">
                                      {{ trx.fo_creator_names || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ trx.po_created_by_name || '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ trx.gr_received_by_name || '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ trx.pr_requester_names || '—' }}</td>
                                    <td class="px-3 py-2 text-right font-semibold text-gray-900 whitespace-nowrap align-top">{{ formatCurrency(trx.total_amount) }}</td>
                                  </tr>
                                  <tr v-if="isTrxExpanded(trx.good_receive_id)" class="bg-slate-50/90">
                                    <td colspan="11" class="px-3 py-2 border-t border-gray-100">
                                      <p class="text-xs font-medium text-gray-600 mb-2">Rincian item</p>
                                      <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                                          <thead class="bg-gray-100">
                                            <tr>
                                              <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase">Item</th>
                                              <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase">Satuan</th>
                                              <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase whitespace-nowrap">GR</th>
                                              <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase whitespace-nowrap">PO</th>
                                              <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase whitespace-nowrap">Qty PR</th>
                                              <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase whitespace-nowrap">Qty PO</th>
                                              <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase whitespace-nowrap">Qty GR</th>
                                              <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase min-w-[100px]">PR</th>
                                              <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase min-w-[100px]">RO</th>
                                              <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase min-w-[90px]">Outlet</th>
                                              <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase min-w-[100px]">Pembuat FO</th>
                                              <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase whitespace-nowrap">Subtotal</th>
                                            </tr>
                                          </thead>
                                          <tbody class="divide-y divide-gray-100">
                                            <tr v-for="(line, idx) in (trx.items || [])" :key="idx">
                                              <td class="px-2 py-1.5 text-gray-800">{{ line.item_name || '—' }}</td>
                                              <td class="px-2 py-1.5 text-gray-600">{{ line.unit_name || '—' }}</td>
                                              <td class="px-2 py-1.5 text-gray-700 whitespace-nowrap">
                                                <div class="font-medium">{{ line.gr_number || '—' }}</div>
                                                <div class="text-[10px] text-gray-500">{{ formatDateTime(line.gr_created_at) }}</div>
                                              </td>
                                              <td class="px-2 py-1.5 text-gray-700 whitespace-nowrap">
                                                <div class="font-medium">{{ line.po_number || '—' }}</div>
                                                <div class="text-[10px] text-gray-500">{{ formatDateTime(line.po_created_at) }}</div>
                                              </td>
                                              <td class="px-2 py-1.5 text-right text-gray-700">{{ formatOptionalQty(line.qty_pr) }}</td>
                                              <td class="px-2 py-1.5 text-right text-gray-700">{{ formatOptionalQty(line.qty_po) }}</td>
                                              <td class="px-2 py-1.5 text-right text-gray-800 font-medium">{{ formatNumber(line.qty_gr) }}</td>
                                              <td class="px-2 py-1.5 text-gray-700">
                                                <div class="font-medium">{{ line.pr_number || '—' }}</div>
                                                <div class="text-[10px] text-gray-500">{{ formatDateTime(line.pr_created_at) }}</div>
                                              </td>
                                              <td class="px-2 py-1.5 text-gray-700">
                                                <div class="font-medium">{{ line.ro_number || '—' }}</div>
                                                <div class="text-[10px] text-gray-500">{{ formatDateTime(line.ro_created_at) }}</div>
                                              </td>
                                              <td class="px-2 py-1.5 text-gray-700">{{ line.fo_outlet_name || '—' }}</td>
                                              <td class="px-2 py-1.5 text-gray-700">{{ line.fo_creator_name || '—' }}</td>
                                              <td class="px-2 py-1.5 text-right font-medium text-gray-900">{{ formatCurrency(line.line_amount) }}</td>
                                            </tr>
                                            <tr v-if="!(trx.items && trx.items.length)">
                                              <td colspan="12" class="px-2 py-3 text-center text-gray-500">Tidak ada baris item.</td>
                                            </tr>
                                          </tbody>
                                        </table>
                                      </div>
                                    </td>
                                  </tr>
                                </template>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="supplierReports.length === 0">
                <td colspan="3" class="text-center py-10 text-gray-500">
                  Tidak ada data pembelanjaan supplier untuk filter ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  supplierReports: {
    type: Array,
    default: () => []
  },
  suppliers: {
    type: Array,
    default: () => []
  },
  warehouses: {
    type: Array,
    default: () => []
  },
  warehouse_divisions: {
    type: Array,
    default: () => []
  },
  summary: {
    type: Object,
    default: () => ({})
  },
  filters: {
    type: Object,
    default: () => ({})
  },
  data_loaded: {
    type: Boolean,
    default: false
  }
})

const filters = ref({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  warehouse_id:
    props.filters.warehouse_id != null && props.filters.warehouse_id !== ''
      ? String(props.filters.warehouse_id)
      : '',
  warehouse_division_id:
    props.filters.warehouse_division_id != null && props.filters.warehouse_division_id !== ''
      ? String(props.filters.warehouse_division_id)
      : '',
  supplier_id: null,
  search: props.filters.search || ''
})

const filteredWarehouseDivisions = computed(() => {
  const all = props.warehouse_divisions || []
  if (!filters.value.warehouse_id) {
    return all
  }
  return all.filter((d) => String(d.warehouse_id) === String(filters.value.warehouse_id))
})

const syncFiltersFromProps = () => {
  const f = props.filters || {}
  filters.value.date_from = f.date_from || ''
  filters.value.date_to = f.date_to || ''
  filters.value.search = f.search || ''
  filters.value.warehouse_id =
    f.warehouse_id != null && f.warehouse_id !== '' ? String(f.warehouse_id) : ''
  filters.value.warehouse_division_id =
    f.warehouse_division_id != null && f.warehouse_division_id !== '' ? String(f.warehouse_division_id) : ''
  const sid = f.supplier_id
  if (sid == null || sid === '') {
    filters.value.supplier_id = null
  } else if (typeof sid === 'object' && sid !== null && 'id' in sid) {
    filters.value.supplier_id = sid
  } else {
    const u = props.suppliers.find((s) => Number(s.id) === Number(sid))
    filters.value.supplier_id = u || null
  }
}

watch(
  () => [props.filters, props.suppliers],
  () => syncFiltersFromProps(),
  { deep: true, immediate: true }
)

watch(
  () => filters.value.warehouse_id,
  (newWh) => {
    if (!newWh || !filters.value.warehouse_division_id) return
    const div = (props.warehouse_divisions || []).find(
      (d) => String(d.id) === String(filters.value.warehouse_division_id)
    )
    if (div && String(div.warehouse_id) !== String(newWh)) {
      filters.value.warehouse_division_id = ''
    }
  }
)

const expandedSuppliers = ref({})
const expandedDays = ref({})
const expandedTrx = ref({})

const supplierKey = (supplierId) => `supplier-${supplierId ?? 'unknown'}`

const dayKey = (supplierId, dateStr) => `${supplierKey(supplierId)}-day-${dateStr ?? 'unknown'}`

const toggleExpand = (supplierId) => {
  const key = supplierKey(supplierId)
  expandedSuppliers.value[key] = !expandedSuppliers.value[key]
}

const isExpanded = (supplierId) => {
  return !!expandedSuppliers.value[supplierKey(supplierId)]
}

const toggleDay = (supplierId, dateStr) => {
  const key = dayKey(supplierId, dateStr)
  expandedDays.value[key] = !expandedDays.value[key]
}

const isDayExpanded = (supplierId, dateStr) => {
  return !!expandedDays.value[dayKey(supplierId, dateStr)]
}

const trxExpandKey = (goodReceiveId) => `trx-${goodReceiveId ?? 'unknown'}`

const toggleTrxExpand = (goodReceiveId) => {
  const key = trxExpandKey(goodReceiveId)
  expandedTrx.value[key] = !expandedTrx.value[key]
}

const isTrxExpanded = (goodReceiveId) => {
  return !!expandedTrx.value[trxExpandKey(goodReceiveId)]
}

const loadData = () => {
  if (!filters.value.date_from || !filters.value.date_to) {
    window.alert('Tanggal dari dan tanggal sampai wajib diisi sebelum memuat data.')
    return
  }
  expandedSuppliers.value = {}
  expandedDays.value = {}
  expandedTrx.value = {}
  router.get(route('food-good-receive.report-supplier-spending'), {
    load: 1,
    date_from: filters.value.date_from,
    date_to: filters.value.date_to,
    supplier_id: filters.value.supplier_id?.id || filters.value.supplier_id,
    search: filters.value.search,
    warehouse_id: filters.value.warehouse_id || undefined,
    warehouse_division_id: filters.value.warehouse_division_id || undefined
  }, {
    preserveState: true,
    preserveScroll: true
  })
}

const clearFilters = () => {
  filters.value = {
    date_from: '',
    date_to: '',
    warehouse_id: '',
    warehouse_division_id: '',
    supplier_id: null,
    search: ''
  }
  expandedSuppliers.value = {}
  expandedDays.value = {}
  expandedTrx.value = {}
  router.get(route('food-good-receive.report-supplier-spending'), {}, {
    preserveState: true,
    preserveScroll: true
  })
}

const exportToExcel = () => {
  if (!props.data_loaded) return
  if (!filters.value.date_from || !filters.value.date_to) {
    window.alert('Tanggal dari dan tanggal sampai wajib untuk export.')
    return
  }
  const params = new URLSearchParams()
  params.append('date_from', filters.value.date_from)
  params.append('date_to', filters.value.date_to)
  const supplierId = filters.value.supplier_id?.id ?? filters.value.supplier_id
  if (supplierId) params.append('supplier_id', supplierId)
  if (filters.value.search) params.append('search', filters.value.search)
  if (filters.value.warehouse_id) params.append('warehouse_id', filters.value.warehouse_id)
  if (filters.value.warehouse_division_id) params.append('warehouse_division_id', filters.value.warehouse_division_id)
  const url = route('food-good-receive.report-supplier-spending.export') + '?' + params.toString()
  window.open(url, '_blank')
}

const formatDayHeading = (dateKey) => {
  if (!dateKey || dateKey === 'unknown') return 'Tanggal tidak diketahui'
  const d = new Date(dateKey + 'T12:00:00')
  if (Number.isNaN(d.getTime())) return dateKey
  return d.toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatNumber = (value) => {
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  }).format(Number(value) || 0)
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(Number(value) || 0)
}

const formatDateTime = (value) => {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatOptionalQty = (value) => {
  if (value === null || value === undefined || value === '') return '—'
  return formatNumber(value)
}
</script>
