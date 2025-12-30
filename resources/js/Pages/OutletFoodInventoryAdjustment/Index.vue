<template>
  <Head title="Outlet Stock Adjustment" />

  <AppLayout>
    <div class="w-full py-8 px-4 md:px-6">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <div class="p-2 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-store text-white text-xl"></i>
              </div>
              <span>Outlet Stock Adjustment</span>
            </h1>
            <p class="text-gray-600 mt-2 ml-14">Kelola penyesuaian stok outlet dengan mudah</p>
          </div>
          <div class="flex gap-3">
            <Link
              :href="route('outlet-food-inventory-adjustment.report-universal')"
              class="group relative px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 font-semibold flex items-center gap-2 overflow-hidden"
            >
              <span class="absolute inset-0 bg-gradient-to-r from-emerald-600 to-emerald-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
              <i class="fa fa-chart-bar relative z-10"></i>
              <span class="relative z-10">Report</span>
            </Link>
            <Link
              :href="route('outlet-food-inventory-adjustment.create')"
              class="group relative px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 font-semibold flex items-center gap-2 overflow-hidden"
            >
              <span class="absolute inset-0 bg-gradient-to-r from-blue-600 to-blue-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
              <i class="fa fa-plus relative z-10"></i>
              <span class="relative z-10">Create New</span>
            </Link>
          </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
          <div class="flex flex-col md:flex-row md:items-end gap-4">
            <div class="flex-1">
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa fa-search text-gray-400 mr-2"></i>Search
              </label>
              <input
                v-model="search"
                type="text"
                placeholder="Search by number, outlet, or item..."
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md"
              />
            </div>
            <div class="flex-1">
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa fa-calendar text-gray-400 mr-2"></i>From Date
              </label>
              <input 
                type="date" 
                v-model="filters.from" 
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md"
              />
            </div>
            <div class="flex-1">
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa fa-calendar text-gray-400 mr-2"></i>To Date
              </label>
              <input 
                type="date" 
                v-model="filters.to" 
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Data Table Section -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Number</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Date</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Type</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Created By</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="adjustments.data.length === 0" class="hover:bg-gray-50">
                <td colspan="8" class="px-6 py-16 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="p-4 bg-gray-100 rounded-full mb-4">
                      <i class="fa fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-lg font-medium">No outlet stock adjustment data found</p>
                    <p class="text-gray-400 text-sm mt-2">Try adjusting your search or filters</p>
                  </div>
                </td>
              </tr>
              <tr 
                v-for="adjustment in adjustments.data" 
                :key="adjustment.id" 
                class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent transition-all duration-200 group"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3 shadow-md">
                      <i class="fa fa-file-alt text-white text-sm"></i>
                    </div>
                    <span class="font-mono font-bold text-blue-700">{{ adjustment.number }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ formatDate(adjustment.date) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ adjustment.outlet?.nama_outlet || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-600">{{ adjustment.warehouse_outlet_name || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'px-3 py-1.5 rounded-full text-xs font-bold shadow-sm',
                    adjustment.type === 'in' 
                      ? 'bg-gradient-to-r from-green-100 to-green-50 text-green-800 border border-green-200' 
                      : 'bg-gradient-to-r from-red-100 to-red-50 text-red-800 border border-red-200'
                  ]">
                    <i :class="adjustment.type === 'in' ? 'fa fa-arrow-down' : 'fa fa-arrow-up'" class="mr-1"></i>
                    {{ adjustment.type === 'in' ? 'Stock In' : 'Stock Out' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ adjustment.creator?.nama_lengkap || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'px-3 py-1.5 rounded-full text-xs font-bold shadow-sm',
                    statusClass(adjustment.status)
                  ]">
                    {{ statusLabel(adjustment.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="route('outlet-food-inventory-adjustment.show', adjustment.id)"
                      class="group relative p-2.5 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md"
                      title="View Details"
                    >
                      <i class="fa fa-eye"></i>
                      <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
                        View Details
                      </span>
                    </Link>
                    <button
                      v-if="canDelete(adjustment)"
                      @click="confirmDelete(adjustment.id)"
                      :disabled="!canDelete(adjustment)"
                      :class="[
                        'group relative p-2.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md',
                        !canDelete(adjustment) ? 'opacity-50 cursor-not-allowed' : ''
                      ]"
                      :title="canDelete(adjustment) ? 'Delete' : getDeleteTooltip(adjustment)"
                    >
                      <i class="fa fa-trash"></i>
                      <span v-if="canDelete(adjustment)" class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
                        Delete
                      </span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-6">
        <Pagination :links="adjustments.links" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, onMounted, computed as vComputed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  adjustments: Object,
  filters: Object,
  user_outlet_id: [String, Number],
  auth: Object,
})

const search = ref(props.filters.search || '')
const filters = ref({
  from: props.filters.from || '',
  to: props.filters.to || ''
})

const selectedOutlet = ref('')

onMounted(() => {
  if (props.user_outlet_id && String(props.user_outlet_id) !== '1') {
    selectedOutlet.value = String(props.user_outlet_id);
  }
})

const debouncedSearch = debounce(() => {
  router.get(
    route('outlet-food-inventory-adjustment.index'),
    { search: search.value, from: filters.value.from, to: filters.value.to },
    { preserveState: true, replace: true }
  )
}, 400)

watch([search, filters], () => {
  debouncedSearch()
})

const formatDate = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  if (isNaN(d)) return '-';
  return d.toLocaleDateString('id-ID', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  });
}

const statusClass = (status) => {
  switch (status) {
    case 'waiting_approval':
      return 'bg-gradient-to-r from-yellow-100 to-yellow-50 text-yellow-800 border border-yellow-200'
    case 'waiting_cost_control':
      return 'bg-gradient-to-r from-blue-100 to-blue-50 text-blue-800 border border-blue-200'
    case 'approved':
      return 'bg-gradient-to-r from-green-100 to-green-50 text-green-800 border border-green-200'
    case 'rejected':
      return 'bg-gradient-to-r from-red-100 to-red-50 text-red-800 border border-red-200'
    default:
      return 'bg-gradient-to-r from-gray-100 to-gray-50 text-gray-800 border border-gray-200'
  }
}

const statusLabel = (status) => {
  switch (status) {
    case 'waiting_approval':
      return 'Waiting Approval'
    case 'waiting_cost_control':
      return 'Waiting Cost Control'
    case 'approved':
      return 'Approved'
    case 'rejected':
      return 'Rejected'
    default:
      return status
  }
}

const loadingDelete = ref(false)

function canDelete(adjustment) {
  const deletableStatuses = ['waiting_approval', 'waiting_cost_control'];
  const isDeletableStatus = deletableStatuses.includes(adjustment.status);
  
  const isApprovedStatus = adjustment.status === 'approved';
  const hasSpecialRole = props.auth?.user?.id_role === '5af56935b011a';
  
  if (isApprovedStatus) {
    return hasSpecialRole;
  }
  
  return isDeletableStatus;
}

function getDeleteTooltip(adjustment) {
  if (adjustment.status === 'approved') {
    return 'Hanya bisa dihapus oleh user dengan role khusus';
  }
  return 'Hanya bisa dihapus jika status waiting_approval atau waiting_cost_control';
}

function confirmDelete(id) {
  const adjustment = props.adjustments.data.find(adj => adj.id === id)
  const statusText = adjustment?.status === 'approved' 
    ? 'Approved (Hanya bisa dihapus oleh user dengan role khusus)' 
    : adjustment?.status === 'waiting_approval' 
    ? 'Waiting Approval' 
    : 'Waiting Cost Control'
  
  Swal.fire({
    title: 'Hapus Outlet Stock Adjustment?',
    html: `
      <div class="text-left">
        <p class="mb-2"><strong>Number:</strong> ${adjustment?.number || 'N/A'}</p>
        <p class="mb-2"><strong>Date:</strong> ${adjustment?.date ? formatDate(adjustment.date) : 'N/A'}</p>
        <p class="mb-2"><strong>Outlet:</strong> ${adjustment?.outlet?.nama_outlet || 'N/A'}</p>
        <p class="mb-2"><strong>Status:</strong> ${statusText}</p>
        <p class="text-red-600 font-semibold">Tindakan ini tidak dapat dibatalkan!</p>
        ${adjustment?.status === 'approved' ? '<p class="text-red-600 font-semibold">Data approved akan di-rollback dari inventory.</p>' : ''}
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    reverseButtons: true,
    showLoaderOnConfirm: true,
    preConfirm: () => {
      loadingDelete.value = true
      return axios.delete(`/outlet-food-inventory-adjustment/${id}`)
        .then(res => {
          if (res.data.success) {
            Swal.fire('Deleted!', 'Data berhasil dihapus dan inventory di-rollback.', 'success')
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

<style scoped>
/* Smooth transitions */
* {
  transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
