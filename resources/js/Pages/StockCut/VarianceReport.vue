<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-minus-circle text-amber-500"></i> Laporan Qty Minus Stock Cut
        </h1>
        <div class="flex gap-2 flex-wrap">
          <Link :href="route('stock-cut.form')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition text-sm">
            <i class="fa-solid fa-scissors mr-1"></i> Stock Cut
          </Link>
          <Link :href="route('stock-cut.index')" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition text-sm">
            <i class="fa-solid fa-list mr-1"></i> Log Stock Cut
          </Link>
        </div>
      </div>

      <!-- Summary -->
      <div v-if="summary" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <p class="text-sm text-amber-700 font-medium">Minus Open</p>
          <p class="text-2xl font-bold text-amber-900">{{ summary.total_open }}</p>
        </div>
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
          <p class="text-sm text-orange-700 font-medium">Total Qty Minus (Open)</p>
          <p class="text-2xl font-bold text-orange-900">{{ formatQty(summary.total_open_shortfall_qty) }}</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
          <p class="text-sm text-red-700 font-medium">Nilai Info Minus (Open)</p>
          <p class="text-2xl font-bold text-red-900">{{ formatRp(summary.total_open_shortfall_value) }}</p>
          <p class="text-xs text-red-600 mt-1">Bukan pengurang cost harian — hanya info hutang qty</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select v-model="filters.status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="">Semua</option>
              <option value="open">Open</option>
              <option value="closed">Closed</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select v-model="filters.outlet_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
            <input type="date" v-model="filters.date_from" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
            <input type="date" v-model="filters.date_to" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div class="flex items-end gap-2">
            <button @click="loadData(1)" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm w-full">
              <i class="fa-solid fa-search mr-1"></i> Cari
            </button>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-3 text-left font-semibold text-gray-600">Tanggal Cut</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-600">Outlet</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-600">Item</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-600">Gudang</th>
                <th class="px-3 py-3 text-right font-semibold text-gray-600">Kebutuhan</th>
                <th class="px-3 py-3 text-right font-semibold text-gray-600">Stok Sebelum</th>
                <th class="px-3 py-3 text-right font-semibold text-gray-600">Minus Qty</th>
                <th class="px-3 py-3 text-right font-semibold text-gray-600">Saldo Setelah</th>
                <th class="px-3 py-3 text-right font-semibold text-gray-600">Cost Dibooking</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-600">Eksekutor</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-600">Status</th>
                <th class="px-3 py-3 text-left font-semibold text-gray-600">Ditutup Via</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="loading">
                <td colspan="12" class="px-4 py-10 text-center text-gray-500">
                  <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat data...
                </td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td colspan="12" class="px-4 py-10 text-center text-gray-500">Tidak ada data</td>
              </tr>
              <tr v-else v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
                <td class="px-3 py-2 whitespace-nowrap">{{ row.tanggal }}</td>
                <td class="px-3 py-2">{{ row.outlet_name }}</td>
                <td class="px-3 py-2 font-medium">{{ row.item_name }}</td>
                <td class="px-3 py-2">{{ row.warehouse_name }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ formatQty(row.qty_needed) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ formatQty(row.qty_available_before) }}</td>
                <td class="px-3 py-2 text-right tabular-nums font-semibold text-amber-700">{{ formatQty(row.qty_shortfall) }}</td>
                <td class="px-3 py-2 text-right tabular-nums" :class="row.qty_after < 0 ? 'text-red-600 font-semibold' : ''">{{ formatQty(row.qty_after) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ formatRp(row.value_booked) }}</td>
                <td class="px-3 py-2">
                  <div>{{ row.executed_by_name || '-' }}</div>
                  <div class="text-xs text-gray-500">{{ formatDateTime(row.stock_cut_executed_at || row.created_at) }}</div>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="row.status === 'open' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'">
                    {{ row.status === 'open' ? 'Open' : 'Closed' }}
                  </span>
                  <div v-if="row.status === 'open' && row.age_hours != null" class="text-xs text-gray-500 mt-0.5">{{ row.age_hours }}j</div>
                </td>
                <td class="px-3 py-2">
                  <template v-if="row.status === 'closed'">
                    <div>{{ row.closed_via_label || row.closed_via || '-' }}</div>
                    <div class="text-xs text-gray-500">{{ row.closed_by_name || '' }}</div>
                    <div class="text-xs text-gray-500">{{ formatDateTime(row.closed_at) }}</div>
                  </template>
                  <span v-else class="text-gray-400">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-600">
          <span>Total {{ total }} data</span>
          <div class="flex gap-2">
            <button @click="loadData(currentPage - 1)" :disabled="currentPage <= 1 || loading" class="px-3 py-1 border rounded disabled:opacity-50">Prev</button>
            <span class="px-2 py-1">{{ currentPage }} / {{ lastPage }}</span>
            <button @click="loadData(currentPage + 1)" :disabled="currentPage >= lastPage || loading" class="px-3 py-1 border rounded disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const rows = ref([])
const summary = ref(null)
const outlets = ref([])
const loading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

const filters = ref({
  status: '',
  outlet_id: '',
  date_from: '',
  date_to: '',
})

onMounted(async () => {
  try {
    const res = await axios.get('/api/outlets/report')
    outlets.value = res.data.outlets || []
  } catch (e) {
    console.error(e)
  }
  await loadData(1)
})

async function loadData(page = 1) {
  loading.value = true
  try {
    const res = await axios.get('/api/stock-cut/variance-report', {
      params: {
        page,
        per_page: 25,
        status: filters.value.status || undefined,
        outlet_id: filters.value.outlet_id || undefined,
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
      },
    })
    rows.value = res.data.data || []
    summary.value = res.data.summary || null
    currentPage.value = res.data.current_page || 1
    lastPage.value = res.data.last_page || 1
    total.value = res.data.total || 0
  } catch (e) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal memuat laporan minus',
    })
  } finally {
    loading.value = false
  }
}

function formatQty(v) {
  return parseFloat(v || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatRp(v) {
  return 'Rp ' + parseFloat(v || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function formatDateTime(v) {
  if (!v) return '-'
  return new Date(v).toLocaleString('id-ID')
}
</script>
