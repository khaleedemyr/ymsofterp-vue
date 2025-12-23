<template>
  <Head title="Stock Adjustment" />
  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
            <i class="fa-solid fa-boxes-stacked text-white text-xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
              <span>Stock Adjustment</span>
            </h1>
            <p class="text-gray-600 ml-16">Manajemen penyesuaian stok inventory warehouse</p>
          </div>
        </div>
        <Link
          :href="route('food-inventory-adjustment.create')"
          class="group inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold transform hover:-translate-y-0.5"
        >
          <i class="fa-solid fa-plus-circle text-lg"></i>
          <span>Create New Adjustment</span>
        </Link>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow-md p-6 border border-blue-200 flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-blue-700 mb-1">Total Adjustments</div>
            <div class="text-3xl font-bold text-blue-900">{{ adjustments.total || 0 }}</div>
          </div>
          <i class="fa-solid fa-list-check text-blue-400 text-4xl opacity-50"></i>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow-md p-6 border border-green-200 flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-green-700 mb-1">Approved</div>
            <div class="text-3xl font-bold text-green-900">{{ getApprovedCount() }}</div>
          </div>
          <i class="fa-solid fa-check-circle text-green-400 text-4xl opacity-50"></i>
        </div>
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl shadow-md p-6 border border-orange-200 flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-orange-700 mb-1">Pending Approval</div>
            <div class="text-3xl font-bold text-orange-900">{{ getPendingCount() }}</div>
          </div>
          <i class="fa-solid fa-clock text-orange-400 text-4xl opacity-50"></i>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-filter text-blue-500"></i> Filter & Pencarian
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-1">
              <i class="fa-solid fa-magnifying-glass text-gray-400"></i> Cari
            </label>
            <div class="relative">
              <input
                v-model="search"
                type="text"
                placeholder="Number, warehouse, item..."
                class="w-full px-4 py-2.5 pl-10 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              />
              <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-1">
              <i class="fa-solid fa-calendar-alt text-gray-400"></i> Dari Tanggal
            </label>
            <input
              type="date"
              v-model="filters.from"
              class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-1">
              <i class="fa-solid fa-calendar-alt text-gray-400"></i> Sampai Tanggal
            </label>
            <input
              type="date"
              v-model="filters.to"
              class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
            />
          </div>
          <div>
            <button
              @click="clearFilters"
              class="w-full px-4 py-2.5 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition-colors shadow-md hover:shadow-lg font-semibold"
            >
              <i class="fa-solid fa-eraser mr-2"></i> Clear Filter
            </button>
          </div>
        </div>
      </div>

      <!-- Table Section -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-200">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-hashtag text-blue-200"></i>
                  <span>Number</span>
                </div>
              </th>
              <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-calendar-alt text-blue-200"></i>
                  <span>Date</span>
                </div>
              </th>
              <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-warehouse text-blue-200"></i>
                  <span>Warehouse</span>
                </div>
              </th>
              <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-tag text-blue-200"></i>
                  <span>Type</span>
                </div>
              </th>
              <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-info-circle text-blue-200"></i>
                  <span>Status</span>
                </div>
              </th>
              <th class="px-6 py-4 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-user text-blue-200"></i>
                  <span>Created By</span>
                </div>
              </th>
              <th class="px-6 py-4 text-center text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">
                <div class="flex items-center justify-center gap-2">
                  <i class="fa-solid fa-cogs text-blue-200"></i>
                  <span>Actions</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            <tr v-if="adjustments.data.length === 0">
              <td colspan="7" class="text-center py-12 text-gray-400">
                <i class="fa-solid fa-box-open text-5xl mb-3"></i>
                <p class="text-lg font-medium">Tidak ada data adjustment yang ditemukan.</p>
                <p class="text-sm">Coba sesuaikan filter pencarian Anda.</p>
              </td>
            </tr>
            <tr
              v-for="adjustment in adjustments.data"
              :key="adjustment.id"
              class="hover:bg-blue-50 transition-all duration-200"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-blue-700 font-mono">{{ adjustment.number }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ formatDate(adjustment.date) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ adjustment.warehouse?.name || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="[
                    'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold',
                    adjustment.type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                  ]"
                >
                  <i :class="adjustment.type === 'in' ? 'fa-solid fa-arrow-down mr-1.5' : 'fa-solid fa-arrow-up mr-1.5'"></i>
                  {{ adjustment.type === 'in' ? 'Stock In' : 'Stock Out' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getStatusClass(adjustment.status)">
                  {{ getStatusLabel(adjustment.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 bg-blue-200 rounded-full flex items-center justify-center text-blue-800 font-bold text-xs">
                    {{ adjustment.creator?.nama_lengkap ? adjustment.creator.nama_lengkap.charAt(0).toUpperCase() : '?' }}
                  </div>
                  <span class="text-sm text-gray-700">{{ adjustment.creator?.nama_lengkap || '-' }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex items-center justify-center gap-2">
                  <Link
                    :href="route('food-inventory-adjustment.show', adjustment.id)"
                    class="inline-flex items-center px-3 py-1.5 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-all shadow-md hover:shadow-lg text-sm font-medium"
                  >
                    <i class="fa-solid fa-eye mr-1"></i>
                    Detail
                  </Link>
                  <button
                    @click="confirmDelete(adjustment.id)"
                    class="inline-flex items-center px-3 py-1.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all shadow-md hover:shadow-lg text-sm font-medium"
                  >
                    <i class="fa-solid fa-trash mr-1"></i>
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="adjustments.last_page > 1" class="bg-white rounded-xl shadow-lg p-4 mt-6 border border-gray-200">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
          <div class="text-sm text-gray-700 flex items-center gap-1">
            Menampilkan <span class="font-semibold text-blue-700">{{ adjustments.from || 0 }}</span> sampai
            <span class="font-semibold text-blue-700">{{ adjustments.to || 0 }}</span> dari
            <span class="font-semibold text-blue-700">{{ adjustments.total }}</span> data
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="() => adjustments.prev_page_url && router.visit(adjustments.prev_page_url, { preserveState: true, replace: true })"
              :disabled="!adjustments.prev_page_url"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 shadow-md',
                adjustments.prev_page_url
                  ? 'bg-blue-500 text-white hover:bg-blue-600 hover:shadow-lg'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              ]"
            >
              <i class="fa-solid fa-chevron-left mr-1"></i>
              Sebelumnya
            </button>
            <div class="flex items-center gap-1">
              <button
                v-for="link in adjustments.links"
                :key="link.label"
                :disabled="!link.url"
                @click="() => link.url && router.visit(link.url, { preserveState: true, replace: true })"
                v-html="link.label"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200"
                :class="[
                  link.active ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 hover:scale-105',
                  !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                ]"
              />
            </div>
            <button
              @click="() => adjustments.next_page_url && router.visit(adjustments.next_page_url, { preserveState: true, replace: true })"
              :disabled="!adjustments.next_page_url"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 shadow-md',
                adjustments.next_page_url
                  ? 'bg-blue-500 text-white hover:bg-blue-600 hover:shadow-lg'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              ]"
            >
              Selanjutnya
              <i class="fa-solid fa-chevron-right ml-1"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  adjustments: Object,
  filters: Object
})

const search = ref(props.filters?.search || '')
const filters = ref({
  from: props.filters?.from || '',
  to: props.filters?.to || ''
})

const adjustments = computed(() => props.adjustments || {
  data: [],
  total: 0,
  from: 0,
  to: 0,
  last_page: 1,
  links: [],
  prev_page_url: null,
  next_page_url: null,
})

const debouncedSearch = debounce(() => {
  router.get(
    route('food-inventory-adjustment.index'),
    { search: search.value, from: filters.value.from, to: filters.value.to },
    { preserveState: true, replace: true }
  )
}, 400)

watch([search, filters], () => {
  debouncedSearch()
})

function clearFilters() {
  search.value = ''
  filters.value.from = ''
  filters.value.to = ''
  debouncedSearch()
}

function formatDate(date) {
  if (!date) return '-'
  const d = new Date(date)
  if (isNaN(d)) return '-'
  return d.toLocaleDateString('id-ID')
}

function getStatusLabel(status) {
  switch (status) {
    case 'waiting_approval':
      return 'Menunggu Approval'
    case 'waiting_ssd_manager':
      return 'Menunggu SSD Manager'
    case 'waiting_cost_control':
      return 'Menunggu Cost Control'
    case 'approved':
      return 'Approved'
    case 'rejected':
      return 'Rejected'
    default:
      return status
  }
}

function getStatusClass(status) {
  const baseClass = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold'
  switch (status) {
    case 'approved':
      return `${baseClass} bg-green-100 text-green-800`
    case 'rejected':
      return `${baseClass} bg-red-100 text-red-800`
    case 'waiting_approval':
    case 'waiting_ssd_manager':
    case 'waiting_cost_control':
      return `${baseClass} bg-yellow-100 text-yellow-800`
    default:
      return `${baseClass} bg-gray-100 text-gray-800`
  }
}

function getApprovedCount() {
  if (!adjustments.value.data) return 0
  return adjustments.value.data.filter(a => a.status === 'approved').length
}

function getPendingCount() {
  if (!adjustments.value.data) return 0
  return adjustments.value.data.filter(a => ['waiting_approval', 'waiting_ssd_manager', 'waiting_cost_control'].includes(a.status)).length
}

const loadingDelete = ref(false)

function confirmDelete(id) {
  Swal.fire({
    title: 'Hapus Stock Adjustment?',
    text: 'Data dan inventory akan di-rollback. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    reverseButtons: true,
    focusCancel: true,
    showLoaderOnConfirm: true,
    preConfirm: () => {
      loadingDelete.value = true
      return axios.delete(`/food-inventory-adjustment/${id}`)
        .then(res => {
          if (res.data.success) {
            Swal.fire('Dihapus!', 'Data berhasil dihapus dan inventory di-rollback.', 'success')
            router.reload()
          } else {
            Swal.fire('Gagal', res.data.message || 'Gagal menghapus data', 'error')
          }
        })
        .catch(err => {
          Swal.fire('Gagal', err.response?.data?.message || 'Gagal menghapus data', 'error')
        })
        .finally(() => {
          loadingDelete.value = false
        })
    },
    allowOutsideClick: () => !Swal.isLoading()
  })
}
</script>
