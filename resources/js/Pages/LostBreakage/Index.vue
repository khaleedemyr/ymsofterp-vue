<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-box-open text-orange-500"></i> Lost &amp; Breakage
        </h1>
        <button @click="goCreate" class="bg-gradient-to-r from-orange-500 to-orange-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Baru
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input v-model="filters.search" type="text" placeholder="Cari nomor, outlet, creator..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" @keyup.enter="applyFilters" />
          </div>
          <div v-if="isAdmin && outlets && outlets.length > 0">
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select v-model="filters.outlet_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">All Outlets</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select v-model="filters.status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">All</option>
              <option value="DRAFT">Draft</option>
              <option value="SUBMITTED">Menunggu Approval</option>
              <option value="APPROVED">Disetujui</option>
              <option value="REJECTED">Ditolak</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
            <input v-model="filters.date_from" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
            <input v-model="filters.date_to" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
            <select v-model="filters.per_page" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="resetFilters" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition"><i class="fa fa-refresh mr-1"></i> Reset</button>
          <button @click="applyFilters" class="px-4 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600 transition"><i class="fa fa-search mr-1"></i> Apply</button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Nomor</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Tanggal</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Status</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Approval</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Outlet</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Creator</th>
                <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!props.data || !props.data.data || props.data.data.length === 0">
                <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data.</td>
              </tr>
              <tr v-for="row in props.data.data" :key="row.id" class="hover:bg-gray-50 transition">
                <td class="px-3 py-3 whitespace-nowrap">
                  <span class="text-sm font-semibold" :class="row.number && row.number.startsWith('DRAFT-') ? 'text-orange-600' : 'text-blue-600'">{{ row.number || '-' }}</span>
                </td>
                <td class="px-3 py-3 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ formatDate(row.date) }}</div>
                  <div class="text-xs text-gray-500">{{ formatTime(row.created_at) }}</div>
                </td>
                <td class="px-3 py-3 whitespace-nowrap">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" :class="statusBadgeClass(row.status)">{{ statusLabel(row.status) }}</span>
                </td>
                <td class="px-3 py-3">
                  <div v-if="row.approval_flows && row.approval_flows.length > 0" class="space-y-1 max-w-[250px]">
                    <div v-for="f in row.approval_flows" :key="f.approval_level" class="flex items-start gap-1.5">
                      <span :class="approvalBadgeClass(f.status)" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium whitespace-nowrap shrink-0 mt-0.5">
                        L{{ f.approval_level }}
                      </span>
                      <div class="min-w-0">
                        <div class="text-xs font-medium text-gray-800 truncate">{{ f.approver_name }}</div>
                        <div v-if="f.approved_at" class="text-[10px] text-green-600">{{ formatDateTime(f.approved_at) }}</div>
                        <div v-else-if="f.rejected_at" class="text-[10px] text-red-600">{{ formatDateTime(f.rejected_at) }}</div>
                      </div>
                    </div>
                  </div>
                  <span v-else class="text-xs text-gray-400">-</span>
                </td>
                <td class="px-3 py-3">
                  <div class="text-sm text-gray-900 font-medium">{{ row.outlet_name }}</div>
                </td>
                <td class="px-3 py-3">
                  <span class="text-sm text-gray-900 max-w-[120px] truncate block" :title="row.creator_name || '-'">{{ row.creator_name || '-' }}</span>
                </td>
                <td class="px-3 py-3 whitespace-nowrap">
                  <div class="flex items-center justify-center gap-1">
                    <button class="inline-flex items-center justify-center w-8 h-8 bg-green-100 text-green-700 hover:bg-green-200 rounded transition" @click="goDetail(row.id)" title="Detail"><i class="fa fa-eye text-sm"></i></button>
                    <button v-if="row.status === 'DRAFT'" class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded transition" @click="goEdit(row.id)" title="Edit"><i class="fa fa-edit text-sm"></i></button>
                    <button v-if="row.status === 'DRAFT' || row.status === 'REJECTED' || canForceDelete" class="inline-flex items-center justify-center w-8 h-8 bg-red-100 text-red-700 hover:bg-red-200 rounded transition" @click="onDelete(row.id)" :disabled="loadingId === row.id" title="Hapus">
                      <i :class="loadingId === row.id ? 'fa fa-spinner fa-spin' : 'fa fa-trash'" class="text-sm"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div v-if="props.data && props.data.links && props.data.links.length > 3" class="px-6 py-4 border-t border-gray-200">
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
import Swal from 'sweetalert2'
import { ref, reactive, computed, watch } from 'vue'
import axios from 'axios'

const page = usePage()
const user = computed(() => page.props.auth?.user || {})
const isAdmin = computed(() => user.value.id_outlet == 1)
const canForceDelete = computed(() => user.value.division_id == 13 || user.value.id_role === '5af56935b011a')

watch(() => page.props.flash, (flash) => {
  if (flash?.error) Swal.fire({ icon: 'error', title: 'Error', html: flash.error, confirmButtonColor: '#EF4444' })
  if (flash?.success) Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success, timer: 2000, showConfirmButton: false })
}, { immediate: true })

const props = defineProps({ data: Object, outlets: Array, filters: Object })
const loadingId = ref(null)

const filters = reactive({
  search: props.filters?.search || '',
  outlet_id: props.filters?.outlet_id || '',
  status: props.filters?.status || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  per_page: props.filters?.per_page || 10
})

function applyFilters() { router.get('/lost-breakage', filters, { preserveState: true, preserveScroll: true }) }
function resetFilters() { Object.assign(filters, { search: '', outlet_id: '', status: '', date_from: '', date_to: '', per_page: 10 }); applyFilters() }
function goCreate() { router.visit('/lost-breakage/create') }
function goDetail(id) { router.visit(`/lost-breakage/${id}`) }
function goEdit(id) { router.visit(`/lost-breakage/${id}/edit`) }

function onDelete(id) {
  Swal.fire({ title: 'Yakin hapus data ini?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal', reverseButtons: true }).then(async (r) => {
    if (r.isConfirmed) {
      loadingId.value = id
      try {
        const res = await axios.delete(`/lost-breakage/${id}`)
        if (res.data?.success) { Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data berhasil dihapus.', timer: 1500, showConfirmButton: false }); setTimeout(() => router.reload(), 1200) }
        else throw new Error('Gagal')
      } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.response?.data?.message || e.message }) }
      finally { loadingId.value = null }
    }
  })
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString('id-ID') : '-' }
function formatTime(d) { return d ? new Date(d).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-' }
function formatDateTime(d) { if (!d) return '-'; const dt = new Date(d); return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }

function statusLabel(s) { return { DRAFT: 'Draft', SUBMITTED: 'Menunggu Approval', APPROVED: 'Disetujui', REJECTED: 'Ditolak' }[s] || s }
function statusBadgeClass(s) { return { DRAFT: 'bg-gray-100 text-gray-800', SUBMITTED: 'bg-yellow-100 text-yellow-800', APPROVED: 'bg-green-100 text-green-800', REJECTED: 'bg-red-100 text-red-800' }[s] || 'bg-gray-100 text-gray-800' }
function approvalBadgeClass(s) { return { APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-700', PENDING: 'bg-yellow-100 text-yellow-700' }[s] || 'bg-gray-100 text-gray-700' }
</script>
