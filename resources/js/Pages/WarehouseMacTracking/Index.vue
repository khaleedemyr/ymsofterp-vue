<template>
  <AppLayout>
    <div class="w-full py-8 px-4 md:px-6 lg:px-8">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2 text-gray-900">
          <i class="fa-solid fa-clock-rotate-left text-blue-500"></i> Warehouse MAC Tracking
        </h1>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse</label>
            <select v-model="filters.warehouse_id" @change="handleWarehouseChange" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Pilih Warehouse</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Barang</label>
            <input
              v-model="itemSearch"
              type="text"
              placeholder="Cari nama / kode barang..."
              class="w-full border border-gray-300 rounded-md px-3 py-2 mb-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <select v-model="filters.item_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Pilih Barang</option>
              <option v-for="item in filteredItems" :key="item.item_id" :value="item.item_id">{{ item.item_name }}{{ item.item_code ? ' (' + item.item_code + ')' : '' }}</option>
            </select>
            <p v-if="items.length > 0 && filteredItems.length === 0" class="text-xs text-gray-500 mt-1">Tidak ada barang yang cocok dengan kata kunci.</p>
          </div>
          <div class="flex items-end md:col-span-2">
            <button
              @click="loadData"
              :disabled="loading"
              class="w-full bg-blue-600 disabled:bg-blue-300 disabled:cursor-not-allowed text-white px-4 py-2 rounded-md hover:bg-blue-700 transition"
            >
              <i class="fa-solid fa-magnifying-glass mr-1"></i> Lihat Perubahan MAC
            </button>
          </div>
        </div>
      </div>

      <div v-if="summary" class="grid grid-cols-2 md:grid-cols-5 gap-3 md:gap-4 mb-6 w-full">
        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 shadow-sm">
          <p class="text-xs text-gray-600">Total Update MAC</p>
          <p class="text-xl font-bold text-gray-900">{{ summary.total_updates }}</p>
        </div>
        <div class="bg-blue-50 rounded-xl p-4 border border-blue-200 shadow-sm">
          <p class="text-xs text-blue-600">MAC Saat Ini</p>
          <p class="text-xl font-bold text-blue-900">{{ summary.current_mac ?? '-' }}</p>
        </div>
        <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-200 shadow-sm">
          <p class="text-xs text-indigo-600">MAC Sebelumnya</p>
          <p class="text-xl font-bold text-indigo-900">{{ summary.previous_mac ?? '-' }}</p>
        </div>
        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-200 shadow-sm">
          <p class="text-xs text-emerald-700">Qty Small Saat Ini ({{ summary.current_qty_small_unit || '-' }})</p>
          <p class="text-xl font-bold text-emerald-900">{{ summary.current_qty_small }}</p>
        </div>
        <div class="bg-amber-50 rounded-xl p-4 border border-amber-200 shadow-sm">
          <p class="text-xs text-amber-700">Tanggal Update Terakhir</p>
          <p class="text-xl font-bold text-amber-900">{{ summary.last_update_date ?? '-' }}</p>
        </div>
      </div>

      <div v-if="selectedInfo" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 mb-6 w-full">
        <p class="text-sm text-gray-600">
          Barang:
          <span class="font-semibold text-gray-900">{{ selectedInfo.item_name }}</span>
          <span v-if="selectedInfo.item_code" class="text-gray-500">({{ selectedInfo.item_code }})</span>
          <span class="text-gray-500"> • Unit kecil: {{ selectedInfo.small_unit_name || '-' }}</span>
          • Warehouse:
          <span class="font-semibold text-gray-900">{{ selectedInfo.warehouse_name }}</span>
        </p>
      </div>

      <div v-if="macChanges.length > 0" class="bg-white rounded-2xl border border-gray-200 shadow-sm w-full">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Riwayat Perubahan MAC</h2>
            <p class="text-xs text-gray-500 mt-1">MAC (Weighted) = harga rata-rata tertimbang setelah transaksi (moving average cost).</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600">Per Page</span>
            <select v-model.number="pagination.per_page" @change="handlePerPageChange" class="border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto w-full">
          <table class="w-full min-w-[1300px] divide-y divide-gray-200">
            <thead class="bg-gray-50 sticky top-0">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Lama</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Baru</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perubahan</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC (Weighted)</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="row in macChanges" :key="row.history_id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.date || '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.old_cost }}</td>
                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ row.new_cost }}</td>
                <td class="px-4 py-3 text-sm" :class="changeClass(row.change_percent)">
                  {{ row.change_percent !== null ? row.change_percent + '%' : '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.mac }}</td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.type || '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-900">
                  <div class="font-medium text-gray-900">{{ row.transaction_number || '-' }}</div>
                  <div class="text-xs text-gray-500">{{ row.reference_type || '-' }}{{ row.reference_id ? ' #' + row.reference_id : '' }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-900">{{ row.created_at || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <p class="text-sm text-gray-600">
            Menampilkan {{ pageRange.start }} - {{ pageRange.end }} dari {{ pagination.total }} data
          </p>
          <div class="flex items-center gap-2">
            <button
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1 || loading"
              class="px-3 py-1.5 text-sm rounded-md border border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              Prev
            </button>
            <button
              v-for="pageItem in visiblePages"
              :key="`page-${pageItem}`"
              @click="typeof pageItem === 'number' ? changePage(pageItem) : null"
              :disabled="loading || pageItem === '...'"
              :class="[
                'px-3 py-1.5 text-sm rounded-md border disabled:opacity-50 disabled:cursor-not-allowed',
                pageItem === pagination.current_page
                  ? 'bg-blue-600 border-blue-600 text-white'
                  : 'border-gray-300 text-gray-700 hover:bg-gray-50'
              ]"
            >
              {{ pageItem }}
            </button>
            <button
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page || loading"
              class="px-3 py-1.5 text-sm rounded-md border border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <div v-if="!loading && hasSearched && macChanges.length === 0" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center text-gray-500 w-full">
        <i class="fa-solid fa-circle-check text-4xl mb-3 text-green-500"></i>
        <p class="text-lg font-medium">Belum ada riwayat perubahan MAC untuk barang yang dipilih</p>
      </div>

      <div v-if="loading" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center text-gray-500 w-full">
        <i class="fa-solid fa-spinner fa-spin text-4xl mb-3"></i>
        <p class="text-lg font-medium">Memuat riwayat MAC...</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'

const filters = ref({
  warehouse_id: '',
  item_id: ''
})

const warehouses = ref([])
const items = ref([])
const macChanges = ref([])
const summary = ref(null)
const loading = ref(false)
const hasSearched = ref(false)
const itemSearch = ref('')
const selectedInfo = ref(null)
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1
})

const filteredItems = computed(() => {
  const keyword = itemSearch.value.toLowerCase().trim()
  if (!keyword) {
    return items.value
  }

  return items.value.filter((item) => {
    const itemName = (item.item_name || '').toLowerCase()
    const itemCode = (item.item_code || '').toLowerCase()
    return itemName.includes(keyword) || itemCode.includes(keyword)
  })
})

const pageRange = computed(() => {
  if (pagination.value.total === 0) {
    return { start: 0, end: 0 }
  }

  const start = (pagination.value.current_page - 1) * pagination.value.per_page + 1
  const end = Math.min(pagination.value.current_page * pagination.value.per_page, pagination.value.total)
  return { start, end }
})

const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const last = pagination.value.last_page

  if (last <= 7) {
    return Array.from({ length: last }, (_, index) => index + 1)
  }

  if (current <= 4) {
    return [1, 2, 3, 4, 5, '...', last]
  }

  if (current >= last - 3) {
    return [1, '...', last - 4, last - 3, last - 2, last - 1, last]
  }

  return [1, '...', current - 1, current, current + 1, '...', last]
})

onMounted(async () => {
  await loadOptions()
})

async function loadData(page = 1) {
  if (!filters.value.warehouse_id || !filters.value.item_id) {
    alert('Silakan pilih warehouse dan barang terlebih dahulu')
    return
  }

  loading.value = true
  hasSearched.value = true

  try {
    const response = await axios.get('/api/warehouse-mac-tracking', {
      params: {
        warehouse_id: filters.value.warehouse_id,
        item_id: filters.value.item_id,
        page,
        per_page: pagination.value.per_page
      }
    })

    if (response.data.status === 'success') {
      macChanges.value = response.data.mac_changes || []
      summary.value = response.data.summary || null
      pagination.value = {
        current_page: response.data.pagination?.current_page || 1,
        per_page: response.data.pagination?.per_page || pagination.value.per_page,
        total: response.data.pagination?.total || 0,
        last_page: response.data.pagination?.last_page || 1
      }
      selectedInfo.value = {
        item_name: response.data.item?.item_name || '-',
        item_code: response.data.item?.item_code || null,
        small_unit_name: response.data.item?.small_unit_name || null,
        warehouse_name: response.data.warehouse?.warehouse_name || '-'
      }
    } else {
      macChanges.value = []
      summary.value = null
      selectedInfo.value = null
      pagination.value = { current_page: 1, per_page: pagination.value.per_page, total: 0, last_page: 1 }
    }
  } catch (error) {
    console.error('Error loading warehouse MAC tracking:', error)
    macChanges.value = []
    summary.value = null
    selectedInfo.value = null
    pagination.value = { current_page: 1, per_page: pagination.value.per_page, total: 0, last_page: 1 }
  } finally {
    loading.value = false
  }
}

function changePage(page) {
  if (page < 1 || page > pagination.value.last_page) {
    return
  }
  loadData(page)
}

function handlePerPageChange() {
  loadData(1)
}

async function loadOptions() {
  try {
    const response = await axios.get('/api/warehouse-mac-tracking/options', {
      params: {
        warehouse_id: filters.value.warehouse_id || undefined
      }
    })

    if (response.data.status === 'success') {
      warehouses.value = response.data.warehouses || []
      items.value = response.data.items || []
    } else {
      warehouses.value = []
      items.value = []
    }
  } catch (error) {
    console.error('Error loading options:', error)
    warehouses.value = []
    items.value = []
  }
}

async function handleWarehouseChange() {
  filters.value.item_id = ''
  itemSearch.value = ''
  macChanges.value = []
  summary.value = null
  selectedInfo.value = null
  pagination.value = { current_page: 1, per_page: pagination.value.per_page, total: 0, last_page: 1 }
  await loadOptions()
}

function changeClass(changePercent) {
  if (changePercent === null || changePercent === undefined) return 'text-gray-700'
  const value = Number(changePercent)
  if (value > 0) return 'text-red-600 font-medium'
  if (value < 0) return 'text-green-600 font-medium'
  return 'text-gray-700'
}
</script>
