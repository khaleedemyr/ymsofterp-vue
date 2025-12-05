<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-recycle text-green-500"></i> Category Cost Outlet
        </h1>
        <div class="flex gap-2">
          <button @click="goReportUniversal" class="bg-blue-500 text-white px-4 py-2 rounded-xl shadow-lg hover:bg-blue-600 transition-all font-semibold">
            <i class="fa fa-file-lines mr-1"></i> Laporan
          </button>
          <button @click="goCreate" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Tambah Baru
          </button>
        </div>
      </div>

      <!-- Filters and Search -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          <!-- Search -->
          <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Search by number, outlet, warehouse, or creator..."
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
              @keyup.enter="applyFilters"
            />
          </div>

          <!-- Filter Outlet (only for admin - id_outlet == 1) -->
          <div v-if="isAdmin && outlets && outlets.length > 0">
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select
              v-model="filters.outlet_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              <option value="">All Outlets</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>

          <!-- Filter Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
            <select
              v-model="filters.type"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              <option value="">All Types</option>
              <option value="internal_use">Internal Use</option>
              <option value="spoil">Spoil</option>
              <option value="waste">Waste</option>
              <option value="r_and_d">R & D</option>
              <option value="marketing">Marketing</option>
              <option value="non_commodity">Non Commodity</option>
              <option value="guest_supplies">Guest Supplies</option>
              <option value="wrong_maker">Wrong Maker</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
            <input
              v-model="filters.date_from"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
            <input
              v-model="filters.date_to"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
            />
          </div>

          <!-- Per Page -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
            <select
              v-model="filters.per_page"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-2">
          <button
            @click="resetFilters"
            class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition"
          >
            <i class="fa fa-refresh mr-1"></i> Reset
          </button>
          <button
            @click="applyFilters"
            class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition"
          >
            <i class="fa fa-search mr-1"></i> Apply Filters
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nomor</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Jam</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipe</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Approval</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Creator</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!props.data || !props.data.data || props.data.data.length === 0">
                <td colspan="10" class="text-center py-10 text-green-300">Tidak ada data.</td>
              </tr>
              <tr v-for="row in props.data.data" :key="row.id">
                <td class="px-6 py-3">
                  <span class="font-semibold" :class="row.number && row.number.startsWith('DRAFT-') ? 'text-orange-600' : 'text-blue-600'">
                    {{ row.number || '-' }}
                  </span>
                </td>
                <td class="px-6 py-3">{{ formatDate(row.date) }}</td>
                <td class="px-6 py-3">{{ formatTime(row.created_at) }}</td>
                <td class="px-6 py-3">{{ typeLabel(row.type) }}</td>
                <td class="px-6 py-3">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusBadgeClass(row.status)">
                    {{ getStatusLabel(row.status) }}
                  </span>
                </td>
                <td class="px-6 py-3">
                  <div v-if="row.approval_flows && row.approval_flows.length > 0" class="space-y-1">
                    <div v-for="flow in row.approval_flows.slice(0, 2)" :key="flow.approval_level" class="text-xs">
                      <div class="flex items-center gap-1">
                        <span class="text-gray-500">L{{ flow.approval_level }}:</span>
                        <span class="font-medium" :class="getApprovalStatusColor(flow.status)">
                          {{ flow.approver_name }}
                        </span>
                        <span :class="getApprovalStatusBadgeClass(flow.status)" class="px-1.5 py-0.5 rounded text-[10px]">
                          {{ flow.status }}
                        </span>
                      </div>
                    </div>
                    <div v-if="row.approval_flows.length > 2" class="text-xs text-gray-500">
                      +{{ row.approval_flows.length - 2 }} more
                    </div>
                  </div>
                  <span v-else class="text-xs text-gray-400">-</span>
                </td>
                <td class="px-6 py-3">{{ row.outlet_name }}</td>
                <td class="px-6 py-3">{{ row.warehouse_outlet_name || '-' }}</td>
                <td class="px-6 py-3">{{ row.creator_name || '-' }}</td>
                <td class="px-6 py-3">
                  <div class="flex flex-col gap-1">
                    <button class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition" @click="goDetail(row.id)">
                      <i class="fa fa-eye mr-1"></i> Detail
                    </button>
                    <button v-if="row.status === 'DRAFT'" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition" @click="goEdit(row.id)">
                      <i class="fa fa-edit mr-1"></i> Edit
                    </button>
                    <button class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition" @click="onDelete(row.id)" :disabled="loadingId === row.id">
                      <span v-if="loadingId === row.id"><i class="fa fa-spinner fa-spin mr-1"></i> Menghapus...</span>
                      <span v-else><i class="fa fa-trash mr-1"></i> Hapus</span>
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
            <div class="text-sm text-gray-700">
              Showing {{ props.data.from || 0 }} to {{ props.data.to || 0 }} of {{ props.data.total || 0 }} results
            </div>
            <div class="flex items-center space-x-2">
              <Link
                v-for="link in props.data.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="[
                  'px-3 py-2 text-sm rounded-md transition',
                  link.active 
                    ? 'bg-green-500 text-white font-semibold' 
                    : link.url
                    ? 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                ]"
                v-html="link.label"
              />
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

// Handle flash messages from backend
watch(() => page.props.flash, (flash) => {
  if (flash?.error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      html: flash.error,
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444'
    })
  }
  if (flash?.success) {
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: flash.success,
      timer: 2000,
      showConfirmButton: false
    })
  }
}, { immediate: true })

const props = defineProps({
  data: Object,
  outlets: Array,
  filters: Object
})

const loadingId = ref(null)

const filters = reactive({
  search: props.filters?.search || '',
  outlet_id: props.filters?.outlet_id || '',
  type: props.filters?.type || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  per_page: props.filters?.per_page || 10
})

function applyFilters() {
  router.get('/outlet-internal-use-waste', filters, {
    preserveState: true,
    preserveScroll: true
  })
}

function resetFilters() {
  filters.search = ''
  filters.outlet_id = '' // Reset outlet_id, but if not admin, it will be ignored
  filters.type = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.per_page = 10
  applyFilters()
}

function goCreate() {
  router.visit(route('outlet-internal-use-waste.create'))
}

function goReportUniversal() {
  router.visit(route('outlet-internal-use-waste.report-universal'))
}

function goReportWasteSpoil() {
  router.visit(route('outlet-internal-use-waste.report-waste-spoil'))
}

function goDetail(id) {
  router.visit(route('outlet-internal-use-waste.show', id))
}

function goEdit(id) {
  router.visit(route('outlet-internal-use-waste.edit', id))
}

function onDelete(id) {
  Swal.fire({
    title: 'Yakin hapus data ini?',
    text: 'Stok akan di-rollback otomatis!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(async (result) => {
    if (result.isConfirmed) {
      loadingId.value = id
      try {
        const res = await axios.delete(`/outlet-internal-use-waste/${id}`)
        if (res.data && res.data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: 'Data berhasil dihapus dan stok di-rollback!',
            timer: 1500,
            showConfirmButton: false
          })
          setTimeout(() => router.reload(), 1200)
        } else {
          throw new Error('Gagal menghapus data')
        }
      } catch (e) {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: e.response?.data?.message || e.message || 'Gagal menghapus data',
        })
      } finally {
        loadingId.value = null
      }
    }
  })
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}

function formatTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
}

function formatNumber(val) {
  if (val == null) return '-';
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function typeLabel(type) {
  if (type === 'internal_use') return 'Internal Use';
  if (type === 'spoil') return 'Spoil';
  if (type === 'waste') return 'Waste';
  if (type === 'r_and_d') return 'R & D';
  if (type === 'marketing') return 'Marketing';
  if (type === 'non_commodity') return 'Non Commodity';
  if (type === 'guest_supplies') return 'Guest Supplies';
  if (type === 'wrong_maker') return 'Wrong Maker';
  return type;
}

function getStatusLabel(status) {
  const statusMap = {
    'DRAFT': 'Draft',
    'SUBMITTED': 'Menunggu Approval',
    'APPROVED': 'Disetujui',
    'REJECTED': 'Ditolak',
    'PROCESSED': 'Diproses'
  };
  return statusMap[status] || status;
}

function getStatusBadgeClass(status) {
  const classMap = {
    'DRAFT': 'bg-gray-100 text-gray-800',
    'SUBMITTED': 'bg-yellow-100 text-yellow-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'REJECTED': 'bg-red-100 text-red-800',
    'PROCESSED': 'bg-blue-100 text-blue-800'
  };
  return classMap[status] || 'bg-gray-100 text-gray-800';
}

function getApprovalStatusColor(status) {
  if (status === 'APPROVED') return 'text-green-600';
  if (status === 'REJECTED') return 'text-red-600';
  if (status === 'PENDING') return 'text-yellow-600';
  return 'text-gray-600';
}

function getApprovalStatusBadgeClass(status) {
  if (status === 'APPROVED') return 'bg-green-100 text-green-700';
  if (status === 'REJECTED') return 'bg-red-100 text-red-700';
  if (status === 'PENDING') return 'bg-yellow-100 text-yellow-700';
  return 'bg-gray-100 text-gray-700';
}
</script> 