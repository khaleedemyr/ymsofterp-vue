<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-bar text-orange-500"></i> Report Lost &amp; Breakage
        </h1>
        <div class="flex items-center gap-2">
          <a :href="exportUrl" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-xl shadow hover:bg-green-700 transition font-semibold text-sm" :class="{ 'opacity-50 pointer-events-none': !filters.date_from || !filters.date_to }">
            <i class="fa fa-file-excel"></i> Export Excel
          </a>
          <button @click="goBack" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition shadow-sm">
            <i class="fa fa-arrow-left text-xs"></i> Kembali
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
          <div v-if="isAdmin">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Outlet</label>
            <select v-model="filters.outlet_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">Semua Outlet</option>
              <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Status</label>
            <select v-model="filters.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">Semua</option>
              <option value="DRAFT">Draft</option>
              <option value="SUBMITTED">Menunggu Approval</option>
              <option value="APPROVED">Disetujui</option>
              <option value="REJECTED">Ditolak</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Tipe</label>
            <select v-model="filters.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">Semua</option>
              <option value="lost">Lost</option>
              <option value="breakage">Breakage</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Dari</label>
            <input v-model="filters.date_from" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Sampai</label>
            <input v-model="filters.date_to" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" />
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="resetFilters" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition text-sm"><i class="fa fa-refresh mr-1"></i> Reset</button>
          <button @click="applyFilters" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition text-sm"><i class="fa fa-search mr-1"></i> Filter</button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-orange-500 to-amber-500">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase w-10"></th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Nomor</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Tanggal</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Outlet</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Creator</th>
                <th class="px-3 py-3 text-center text-xs font-bold text-white uppercase">Items</th>
                <th class="px-3 py-3 text-center text-xs font-bold text-white uppercase">Lost</th>
                <th class="px-3 py-3 text-center text-xs font-bold text-white uppercase">Breakage</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Status</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Approval</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!props.data?.data?.length">
                <td colspan="10" class="text-center py-10 text-gray-400">Tidak ada data. Silakan atur filter terlebih dahulu.</td>
              </tr>
              <template v-for="row in props.data.data" :key="row.id">
                <tr class="hover:bg-orange-50/40 transition cursor-pointer" @click="toggleExpand(row.id)">
                  <td class="px-3 py-3 text-center">
                    <i :class="expanded[row.id] ? 'fa fa-chevron-down text-orange-500' : 'fa fa-chevron-right text-gray-400'" class="text-xs transition-transform"></i>
                  </td>
                  <td class="px-3 py-3">
                    <span class="text-sm font-semibold text-blue-600">{{ row.number || '-' }}</span>
                  </td>
                  <td class="px-3 py-3 text-sm text-gray-700">{{ formatDate(row.date) }}</td>
                  <td class="px-3 py-3 text-sm font-medium text-gray-900">{{ row.outlet_name }}</td>
                  <td class="px-3 py-3 text-sm text-gray-700">{{ row.creator_name || '-' }}</td>
                  <td class="px-3 py-3 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700">{{ row.item_count }}</span>
                  </td>
                  <td class="px-3 py-3 text-center">
                    <span v-if="row.type_summary.lost > 0" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-yellow-100 text-yellow-700">{{ row.type_summary.lost }}</span>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-3 py-3 text-center">
                    <span v-if="row.type_summary.breakage > 0" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">{{ row.type_summary.breakage }}</span>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-3 py-3">
                    <span :class="statusBadgeClass(row.status)" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">{{ statusLabel(row.status) }}</span>
                  </td>
                  <td class="px-3 py-3">
                    <div v-if="row.approval_flows?.length > 0" class="space-y-0.5">
                      <div v-for="f in row.approval_flows" :key="f.id" class="flex items-center gap-1">
                        <span :class="approvalBadgeClass(f.status)" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium">L{{ f.approval_level }}</span>
                        <span class="text-xs text-gray-700 truncate max-w-[100px]">{{ f.approver_name }}</span>
                      </div>
                    </div>
                    <span v-else class="text-xs text-gray-400">-</span>
                  </td>
                </tr>
                <!-- Expanded Detail Row -->
                <tr v-if="expanded[row.id]">
                  <td colspan="10" class="bg-slate-50 px-6 py-4">
                    <div v-if="loadingDetails[row.id]" class="text-center py-4">
                      <i class="fa fa-spinner fa-spin text-orange-500"></i> Memuat detail...
                    </div>
                    <div v-else-if="details[row.id]?.length > 0">
                      <div class="text-xs font-bold text-gray-500 uppercase mb-2">Detail Items</div>
                      <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                          <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">#</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Item</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Tipe</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Unit</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Keterangan</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Foto</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                          <tr v-for="(d, i) in details[row.id]" :key="d.id" class="hover:bg-white">
                            <td class="px-3 py-2 text-gray-500">{{ i + 1 }}</td>
                            <td class="px-3 py-2 font-medium text-gray-800">{{ d.item_name }}</td>
                            <td class="px-3 py-2 text-center">
                              <span :class="d.type === 'breakage' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'" class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize">{{ d.type || 'lost' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-medium">{{ d.qty }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ d.unit_name }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ d.note || '-' }}</td>
                            <td class="px-3 py-2 text-center">
                              <a v-if="d.photo" :href="`/storage/${d.photo}`" target="_blank" class="text-blue-500 hover:underline text-xs"><i class="fa fa-image mr-1"></i>Lihat</a>
                              <span v-else class="text-gray-300">-</span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else class="text-center py-4 text-gray-400 text-sm">Tidak ada detail item.</div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="props.data?.links?.length > 3" class="px-6 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">Showing {{ props.data.from || 0 }} to {{ props.data.to || 0 }} of {{ props.data.total || 0 }}</div>
            <div class="flex items-center space-x-2">
              <Link v-for="link in props.data.links" :key="link.label" :href="link.url || '#'" :class="['px-3 py-2 text-sm rounded-md transition', link.active ? 'bg-orange-500 text-white font-semibold' : link.url ? 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed']" v-html="link.label" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import { ref, reactive, computed } from 'vue'
import axios from 'axios'

const page = usePage()
const user = computed(() => page.props.auth?.user || {})
const isAdmin = computed(() => user.value.id_outlet == 1)

const props = defineProps({ data: Object, outlets: Array, filters: Object })

const filters = reactive({
  outlet_id: props.filters?.outlet_id || '',
  status: props.filters?.status || '',
  type: props.filters?.type || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
})

const expanded = ref({})
const details = ref({})
const loadingDetails = ref({})

const exportUrl = computed(() => {
  const params = new URLSearchParams()
  if (filters.outlet_id) params.set('outlet_id', filters.outlet_id)
  if (filters.status) params.set('status', filters.status)
  if (filters.type) params.set('type', filters.type)
  if (filters.date_from) params.set('date_from', filters.date_from)
  if (filters.date_to) params.set('date_to', filters.date_to)
  return `/lost-breakage-report/export?${params.toString()}`
})

function applyFilters() { router.get('/lost-breakage-report', filters, { preserveState: true, preserveScroll: true }) }
function resetFilters() { Object.assign(filters, { outlet_id: '', status: '', type: '', date_from: '', date_to: '' }); applyFilters() }
function goBack() { router.visit('/lost-breakage') }

async function toggleExpand(id) {
  if (expanded.value[id]) {
    expanded.value[id] = false
    return
  }
  expanded.value[id] = true
  if (details.value[id]) return
  loadingDetails.value[id] = true
  try {
    const res = await axios.get(`/lost-breakage-report/details/${id}`)
    if (res.data.success) details.value[id] = res.data.details
  } catch (e) { console.error(e) }
  finally { loadingDetails.value[id] = false }
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString('id-ID') : '-' }
function statusLabel(s) { return { DRAFT: 'Draft', SUBMITTED: 'Menunggu Approval', APPROVED: 'Disetujui', REJECTED: 'Ditolak' }[s] || s }
function statusBadgeClass(s) { return { DRAFT: 'bg-gray-100 text-gray-800', SUBMITTED: 'bg-yellow-100 text-yellow-800', APPROVED: 'bg-green-100 text-green-800', REJECTED: 'bg-red-100 text-red-800' }[s] || 'bg-gray-100 text-gray-800' }
function approvalBadgeClass(s) { return { APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-700', PENDING: 'bg-yellow-100 text-yellow-700' }[s] || 'bg-gray-100 text-gray-700' }
</script>
