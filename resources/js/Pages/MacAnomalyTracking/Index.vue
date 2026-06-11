<template>
  <AppLayout>
    <div class="w-full py-8 px-4 md:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2 text-gray-900">
          <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
          Pelacakan Anomali MAC Outlet
        </h1>
        <div class="flex rounded-lg border border-gray-200 overflow-hidden bg-white shadow-sm">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'px-4 py-2 text-sm font-medium transition',
              activeTab === tab.id ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'
            ]"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!-- ── TAB: Scan Anomali ── -->
      <template v-if="activeTab === 'scan'">
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-900">
          <p class="font-semibold mb-2"><i class="fa-solid fa-circle-info mr-1"></i> Cara pakai</p>
          <ul class="list-disc pl-5 space-y-1 text-blue-800">
            <li><strong>Scan Anomali</strong> hanya menampilkan MAC yang <em>dicurigai bermasalah</em> (minus, lonjakan besar, dll.) — bukan semua riwayat.</li>
            <li>Kalau mau lihat <strong>semua perubahan MAC</strong> satu barang, pakai tab <button type="button" class="underline font-medium" @click="activeTab = 'detail'">Detail per Barang</button>.</li>
            <li>Mulai dengan <strong>Semua outlet</strong> + rentang tanggal lebih lebar (3–6 bulan). Warehouse boleh dikosongkan.</li>
            <li>Kalau hasil kosong tapi ada riwayat MAC, turunkan <strong>Lonjakan min.</strong> ke 50% atau klik preset <strong>Sensitif</strong> di bawah.</li>
          </ul>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <select v-model="scanFilters.outlet_id" @change="handleScanOutletChange" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse</label>
              <select v-model="scanFilters.warehouse_outlet_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua warehouse</option>
                <option v-for="wh in scanWarehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Dari tanggal</label>
              <input v-model="scanFilters.date_from" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Sampai tanggal</label>
              <input v-model="scanFilters.date_to" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Lonjakan min. (%)</label>
              <input v-model.number="scanFilters.min_spike_percent" type="number" min="0" step="10" class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Kelipatan MAC (×)</label>
              <input v-model.number="scanFilters.spike_multiplier" type="number" min="1.1" step="0.5" class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">MAC maks. (Rp/unit kecil)</label>
              <input v-model.number="scanFilters.max_mac" type="number" min="0" step="100000" class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div class="flex items-end gap-2">
              <button @click="runScan(1)" :disabled="scanLoading" class="flex-1 bg-amber-600 disabled:bg-amber-300 text-white px-4 py-2 rounded-md hover:bg-amber-700 transition">
                <i class="fa-solid fa-radar mr-1"></i> Scan Anomali
              </button>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs text-gray-500 self-center mr-1">Preset:</span>
            <button type="button" @click="applyPreset('sensitive')" class="text-xs px-3 py-1 rounded-full border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">Sensitif (50%, ×2)</button>
            <button type="button" @click="applyPreset('default')" class="text-xs px-3 py-1 rounded-full border border-gray-300 bg-gray-50 hover:bg-gray-100">Default (100%, ×5)</button>
            <button type="button" @click="applyPreset('wide')" class="text-xs px-3 py-1 rounded-full border border-gray-300 bg-gray-50 hover:bg-gray-100">Rentang 6 bulan</button>
          </div>

          <div class="flex flex-wrap gap-3">
            <label v-for="opt in anomalyTypeOptions" :key="opt.value" class="inline-flex items-center gap-2 text-sm text-gray-700">
              <input v-model="scanFilters.types" type="checkbox" :value="opt.value" class="rounded border-gray-300 text-blue-600">
              {{ opt.label }}
            </label>
          </div>
        </div>

        <div v-if="scanSummary" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
          <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <p class="text-xs text-red-600">Total anomali</p>
            <p class="text-2xl font-bold text-red-900">{{ scanSummary.total }}</p>
          </div>
          <div v-for="(count, type) in scanSummary.type_breakdown" :key="type" class="bg-gray-50 border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-600">{{ typeLabel(type) }}</p>
            <p class="text-xl font-bold text-gray-900">{{ count }}</p>
          </div>
        </div>

        <div v-if="moduleBreakdown.length > 0" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Sumber modul penyebab anomali</h2>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Modul</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jenis transaksi</th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cara perbaikan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="mod in moduleBreakdown" :key="mod.reference_type || mod.module_name" class="hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium text-gray-900">{{ mod.module_name }}</td>
                  <td class="px-4 py-3 text-gray-700">{{ mod.reference_label }}</td>
                  <td class="px-4 py-3 text-right font-semibold text-red-600">{{ mod.count }}</td>
                  <td class="px-4 py-3 text-gray-600 text-xs">{{ mod.fix_hint }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="anomalies.length > 0" class="bg-white rounded-2xl border border-gray-200 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Daftar anomali terdeteksi</h2>
            <span class="text-sm text-gray-500">{{ scanPagination.total }} baris</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[1400px] divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Outlet / WH</th>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Barang</th>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jenis anomali</th>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">MAC sebelum</th>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">MAC sesudah</th>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Δ%</th>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modul sumber</th>
                  <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="row in anomalies" :key="row.history_id || `${row.item_id}-${row.warehouse_outlet_id}-stock`" class="hover:bg-gray-50">
                  <td class="px-3 py-3">{{ row.date || '-' }}</td>
                  <td class="px-3 py-3">
                    <div class="font-medium">{{ row.outlet_name }}</div>
                    <div class="text-xs text-gray-500">{{ row.warehouse_name }}</div>
                  </td>
                  <td class="px-3 py-3">
                    <div class="font-medium">{{ row.item_name }}</div>
                    <div v-if="row.item_code" class="text-xs text-gray-500">{{ row.item_code }}</div>
                  </td>
                  <td class="px-3 py-3">
                    <span v-for="label in row.anomaly_labels" :key="label" class="inline-block mr-1 mb-1 px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">{{ label }}</span>
                  </td>
                  <td class="px-3 py-3">{{ row.prev_mac ?? '-' }}</td>
                  <td class="px-3 py-3 font-semibold" :class="macClass(row.mac)">{{ row.mac }}</td>
                  <td class="px-3 py-3" :class="changeClass(row.change_percent)">{{ row.change_percent !== null ? row.change_percent + '%' : '-' }}</td>
                  <td class="px-3 py-3">
                    <div class="font-medium">{{ row.module_name }}</div>
                    <div class="text-xs text-gray-500">{{ row.transaction_number || row.reference_label }}</div>
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap">
                    <a v-if="row.source_url" :href="row.source_url" target="_blank" class="text-blue-600 hover:underline text-xs mr-2">
                      <i class="fa-solid fa-arrow-up-right-from-square"></i> Transaksi
                    </a>
                    <button v-if="row.item_id" @click="openDetailForRow(row)" class="text-indigo-600 hover:underline text-xs">
                      <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="px-6 py-4 border-t flex justify-between items-center">
            <p class="text-sm text-gray-600">Halaman {{ scanPagination.current_page }} / {{ scanPagination.last_page }}</p>
            <div class="flex gap-2">
              <button @click="runScan(scanPagination.current_page - 1)" :disabled="scanPagination.current_page <= 1 || scanLoading" class="px-3 py-1.5 text-sm border rounded-md disabled:opacity-50">Prev</button>
              <button @click="runScan(scanPagination.current_page + 1)" :disabled="scanPagination.current_page >= scanPagination.last_page || scanLoading" class="px-3 py-1.5 text-sm border rounded-md disabled:opacity-50">Next</button>
            </div>
          </div>
        </div>

        <div v-if="!scanLoading && scanSearched && anomalies.length === 0" class="bg-white rounded-2xl border p-8 text-center text-gray-600">
          <i :class="emptyStateIcon" class="text-4xl mb-3"></i>
          <p class="text-lg font-medium text-gray-800 mb-2">{{ emptyStateTitle }}</p>
          <p class="text-sm max-w-xl mx-auto mb-4">{{ emptyStateMessage }}</p>
          <div v-if="scanSummary" class="inline-flex flex-col gap-1 text-left text-sm bg-gray-50 border rounded-lg px-4 py-3 mb-4">
            <span>Riwayat MAC dalam periode: <strong>{{ scanSummary.history_rows_in_period ?? 0 }}</strong> baris</span>
            <span>Total histori (scope filter): <strong>{{ scanSummary.history_rows_total_scope ?? 0 }}</strong> baris</span>
            <span v-if="scanSummary.stock_rows_checked">Baris stok dicek: <strong>{{ scanSummary.stock_rows_checked }}</strong></span>
          </div>
          <div class="flex flex-wrap justify-center gap-2">
            <button type="button" @click="applyPreset('sensitive'); runScan(1)" class="px-4 py-2 text-sm bg-amber-600 text-white rounded-md hover:bg-amber-700">Coba preset Sensitif</button>
            <button type="button" @click="activeTab = 'detail'" class="px-4 py-2 text-sm border border-blue-300 text-blue-700 rounded-md hover:bg-blue-50">Buka Detail per Barang</button>
          </div>
        </div>
        <div v-if="scanLoading" class="bg-white rounded-2xl border p-8 text-center text-gray-500">
          <i class="fa-solid fa-spinner fa-spin text-4xl mb-3"></i>
          <p>Memindai anomali MAC...</p>
        </div>
      </template>

      <!-- ── TAB: Detail per Barang ── -->
      <template v-if="activeTab === 'detail'">
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6 text-sm text-indigo-900">
          <p><strong>Detail per Barang</strong> — pilih <em>Outlet + Warehouse + Barang</em>, lalu klik <strong>Lihat Riwayat MAC</strong> untuk melihat semua perubahan MAC barang tersebut (termasuk transaksi normal).</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <select v-model="filters.outlet_id" @change="handleOutletChange" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Pilih Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse Outlet</label>
              <select v-model="filters.warehouse_outlet_id" @change="handleWarehouseChange" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Pilih Warehouse</option>
                <option v-for="warehouse in warehouseOutlets" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Barang</label>
              <input v-model="itemSearch" type="text" placeholder="Cari nama / kode..." class="w-full border border-gray-300 rounded-md px-3 py-2 mb-2">
              <select v-model="filters.item_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                <option value="">Pilih Barang</option>
                <option v-for="item in filteredItems" :key="item.item_id" :value="item.item_id">{{ item.item_name }}{{ item.item_code ? ' (' + item.item_code + ')' : '' }}</option>
              </select>
            </div>
            <div class="flex items-end">
              <button @click="loadData()" :disabled="loading" class="w-full bg-blue-600 disabled:bg-blue-300 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fa-solid fa-magnifying-glass mr-1"></i> Lihat Riwayat MAC
              </button>
            </div>
          </div>
        </div>

        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
          <div class="bg-gray-50 rounded-xl p-4 border"><p class="text-xs text-gray-600">Total Update</p><p class="text-xl font-bold">{{ summary.total_updates }}</p></div>
          <div class="bg-blue-50 rounded-xl p-4 border border-blue-200"><p class="text-xs text-blue-600">MAC Saat Ini</p><p class="text-xl font-bold text-blue-900">{{ summary.current_mac ?? '-' }}</p></div>
          <div class="bg-indigo-50 rounded-xl p-4 border"><p class="text-xs text-indigo-600">MAC Sebelumnya</p><p class="text-xl font-bold">{{ summary.previous_mac ?? '-' }}</p></div>
          <div class="bg-emerald-50 rounded-xl p-4 border"><p class="text-xs text-emerald-700">Qty Small</p><p class="text-xl font-bold">{{ summary.current_qty_small }}</p></div>
          <div class="bg-amber-50 rounded-xl p-4 border"><p class="text-xs text-amber-700">Update Terakhir</p><p class="text-xl font-bold">{{ summary.last_update_date ?? '-' }}</p></div>
        </div>

        <div v-if="macChanges.length > 0" class="bg-white rounded-2xl border shadow-sm overflow-x-auto">
          <table class="w-full min-w-[1200px] text-sm divide-y">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">old_cost</th>
                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">new_cost</th>
                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Δ%</th>
                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">MAC</th>
                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Reference</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="row in macChanges" :key="row.history_id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ row.date }}</td>
                <td class="px-4 py-3">{{ row.old_cost }}</td>
                <td class="px-4 py-3 font-semibold">{{ row.new_cost }}</td>
                <td class="px-4 py-3" :class="changeClass(row.change_percent)">{{ row.change_percent !== null ? row.change_percent + '%' : '-' }}</td>
                <td class="px-4 py-3" :class="macClass(row.mac)">{{ row.mac }}</td>
                <td class="px-4 py-3">
                  <div>{{ row.transaction_number || '-' }}</div>
                  <div class="text-xs text-gray-500">{{ row.reference_type }} #{{ row.reference_id }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="!loading && hasSearched && macChanges.length === 0" class="bg-white rounded-2xl border p-8 text-center text-gray-500">Belum ada riwayat MAC.</div>
        <div v-if="loading" class="bg-white rounded-2xl border p-8 text-center"><i class="fa-solid fa-spinner fa-spin text-4xl"></i></div>
      </template>

      <!-- ── TAB: Modul Sumber MAC ── -->
      <template v-if="activeTab === 'modules'">
        <div class="bg-white rounded-2xl border shadow-sm p-6">
          <p class="text-sm text-gray-600 mb-6">Daftar modul ERP yang menulis perubahan MAC ke <code class="text-xs bg-gray-100 px-1 rounded">outlet_food_inventory_cost_histories</code>. Gunakan ini untuk mengetahui transaksi mana yang perlu dicek saat MAC abnormal.</p>
          <div class="grid gap-4 md:grid-cols-2">
            <div v-for="mod in referenceModules" :key="mod.reference_type" class="border border-gray-200 rounded-xl p-4 hover:border-blue-300 transition">
              <h3 class="font-semibold text-gray-900">{{ mod.module }}</h3>
              <p class="text-xs text-gray-500 mb-2">{{ mod.label }} · <code>{{ mod.reference_type }}</code></p>
              <p class="text-sm text-gray-700">{{ mod.fix_hint }}</p>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  referenceModules: { type: Array, default: () => [] }
})

const tabs = [
  { id: 'scan', label: 'Scan Anomali' },
  { id: 'detail', label: 'Detail per Barang' },
  { id: 'modules', label: 'Modul Sumber' },
]
const activeTab = ref('scan')

const anomalyTypeOptions = [
  { value: 'negative_mac', label: 'MAC minus' },
  { value: 'negative_new_cost', label: 'Biaya masuk minus' },
  { value: 'spike_percent', label: 'Lonjakan %' },
  { value: 'spike_multiplier', label: 'Lonjakan kelipatan' },
  { value: 'absolute_high', label: 'MAC terlalu besar' },
  { value: 'current_stock', label: 'Stok saat ini abnormal' },
]

const today = new Date().toISOString().slice(0, 10)
const threeMonthsAgo = new Date(Date.now() - 90 * 86400000).toISOString().slice(0, 10)
const sixMonthsAgo = new Date(Date.now() - 180 * 86400000).toISOString().slice(0, 10)

const scanFilters = ref({
  outlet_id: '',
  warehouse_outlet_id: '',
  date_from: threeMonthsAgo,
  date_to: today,
  min_spike_percent: 100,
  spike_multiplier: 5,
  max_mac: 10000000,
  types: ['negative_mac', 'negative_new_cost', 'spike_percent', 'spike_multiplier', 'absolute_high', 'current_stock'],
})

const scanLoading = ref(false)
const scanSearched = ref(false)
const anomalies = ref([])
const scanSummary = ref(null)
const moduleBreakdown = ref([])
const scanPagination = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 })
const scanWarehouses = ref([])

const filters = ref({ outlet_id: '', warehouse_outlet_id: '', item_id: '' })
const outlets = ref([])
const warehouseOutlets = ref([])
const items = ref([])
const macChanges = ref([])
const summary = ref(null)
const loading = ref(false)
const hasSearched = ref(false)
const itemSearch = ref('')
const pagination = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 })

const filteredItems = computed(() => {
  const kw = itemSearch.value.toLowerCase().trim()
  if (!kw) return items.value
  return items.value.filter(i => (i.item_name || '').toLowerCase().includes(kw) || (i.item_code || '').toLowerCase().includes(kw))
})

const emptyStateTitle = computed(() => {
  if (!scanSummary.value) return 'Belum ada hasil scan'
  const inPeriod = scanSummary.value.history_rows_in_period ?? 0
  if (inPeriod === 0) return 'Tidak ada riwayat MAC dalam periode ini'
  return 'Tidak ada anomali yang memenuhi kriteria'
})

const emptyStateMessage = computed(() => {
  const inPeriod = scanSummary.value?.history_rows_in_period ?? 0
  const total = scanSummary.value?.history_rows_total_scope ?? 0
  if (inPeriod === 0 && total === 0) {
    return 'Outlet/warehouse yang dipilih belum punya data di outlet_food_inventory_cost_histories. Coba outlet lain atau perlebar tanggal.'
  }
  if (inPeriod === 0 && total > 0) {
    return `Ada ${total} riwayat MAC di luar periode tanggal. Perlebar rentang tanggal atau klik preset "Rentang 6 bulan".`
  }
  return 'MAC dalam periode ini terlihat normal menurut filter (minus / lonjakan / batas maks.). Turunkan threshold atau cek riwayat per barang di tab Detail.'
})

const emptyStateIcon = computed(() => {
  const inPeriod = scanSummary.value?.history_rows_in_period ?? 0
  if (inPeriod === 0) return 'fa-solid fa-database text-gray-400'
  return 'fa-solid fa-circle-check text-green-500'
})

function applyPreset(name) {
  if (name === 'sensitive') {
    scanFilters.value.min_spike_percent = 50
    scanFilters.value.spike_multiplier = 2
    scanFilters.value.max_mac = 10000000
  } else if (name === 'default') {
    scanFilters.value.min_spike_percent = 100
    scanFilters.value.spike_multiplier = 5
    scanFilters.value.max_mac = 10000000
  } else if (name === 'wide') {
    scanFilters.value.date_from = sixMonthsAgo
    scanFilters.value.date_to = today
  }
}

onMounted(async () => {
  await loadOutlets()
})

async function loadOutlets() {
  try {
    const res = await axios.get('/api/outlets/report')
    outlets.value = res.data.outlets || []
  } catch {
    outlets.value = []
  }
}

async function handleScanOutletChange() {
  scanFilters.value.warehouse_outlet_id = ''
  if (!scanFilters.value.outlet_id) {
    scanWarehouses.value = []
    return
  }
  const res = await axios.get('/api/mac-anomaly-tracking/options', { params: { id_outlet: scanFilters.value.outlet_id } })
  scanWarehouses.value = res.data.warehouses || []
}

async function runScan(page = 1) {
  scanLoading.value = true
  scanSearched.value = true
  try {
    const res = await axios.get('/api/mac-anomaly-tracking/scan', {
      params: {
        id_outlet: scanFilters.value.outlet_id || undefined,
        warehouse_outlet_id: scanFilters.value.warehouse_outlet_id || undefined,
        date_from: scanFilters.value.date_from,
        date_to: scanFilters.value.date_to,
        min_spike_percent: scanFilters.value.min_spike_percent,
        spike_multiplier: scanFilters.value.spike_multiplier,
        max_mac: scanFilters.value.max_mac,
        types: scanFilters.value.types,
        page,
        per_page: scanPagination.value.per_page,
      }
    })
    if (res.data.status === 'success') {
      anomalies.value = res.data.anomalies || []
      scanSummary.value = res.data.summary || null
      moduleBreakdown.value = res.data.module_breakdown || []
      scanPagination.value = res.data.pagination || scanPagination.value
    }
  } catch (e) {
    console.error(e)
    anomalies.value = []
  } finally {
    scanLoading.value = false
  }
}

function openDetailForRow(row) {
  filters.value.outlet_id = String(row.id_outlet)
  filters.value.warehouse_outlet_id = String(row.warehouse_outlet_id)
  filters.value.item_id = String(row.item_id)
  activeTab.value = 'detail'
  handleOutletChange().then(() => loadData())
}

async function loadData(page = 1) {
  if (!filters.value.outlet_id || !filters.value.warehouse_outlet_id || !filters.value.item_id) {
    alert('Pilih outlet, warehouse, dan barang')
    return
  }
  loading.value = true
  hasSearched.value = true
  try {
    const res = await axios.get('/api/mac-anomaly-tracking', {
      params: {
        id_outlet: filters.value.outlet_id,
        warehouse_outlet_id: filters.value.warehouse_outlet_id,
        item_id: filters.value.item_id,
        page,
        per_page: pagination.value.per_page,
      }
    })
    if (res.data.status === 'success') {
      macChanges.value = res.data.mac_changes || []
      summary.value = res.data.summary || null
      pagination.value = res.data.pagination || pagination.value
    }
  } catch (e) {
    console.error(e)
    macChanges.value = []
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  if (!filters.value.outlet_id) {
    warehouseOutlets.value = []
    items.value = []
    return
  }
  const res = await axios.get('/api/mac-anomaly-tracking/options', {
    params: { id_outlet: filters.value.outlet_id, warehouse_outlet_id: filters.value.warehouse_outlet_id || undefined }
  })
  warehouseOutlets.value = res.data.warehouses || []
  items.value = res.data.items || []
}

async function handleOutletChange() {
  filters.value.warehouse_outlet_id = ''
  filters.value.item_id = ''
  macChanges.value = []
  summary.value = null
  await loadOptions()
}

async function handleWarehouseChange() {
  filters.value.item_id = ''
  macChanges.value = []
  summary.value = null
  await loadOptions()
}

function typeLabel(type) {
  const map = Object.fromEntries(anomalyTypeOptions.map(o => [o.value, o.label]))
  return map[type] || type
}

function changeClass(v) {
  if (v === null || v === undefined) return 'text-gray-700'
  const n = Number(v)
  if (n > 0) return 'text-red-600 font-medium'
  if (n < 0) return 'text-green-600 font-medium'
  return 'text-gray-700'
}

function macClass(mac) {
  const n = Number(mac)
  if (n < 0) return 'text-red-700 font-bold'
  if (n > 1000000) return 'text-amber-700 font-bold'
  return 'text-gray-900'
}
</script>
