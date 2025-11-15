<template>
  <Head title="Outlet Stock Adjustment" />

  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-store text-blue-500"></i> Outlet Stock Adjustment
        </h1>
        <Link
          :href="route('outlet-food-inventory-adjustment.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Create New
        </Link>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          type="text"
          placeholder="Search by number, outlet, or item..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <input type="date" v-model="filters.from" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="From date" />
        <span>-</span>
        <input type="date" v-model="filters.to" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="To date" />
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Number</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="adjustments.data.length === 0">
              <td colspan="7" class="text-center py-10 text-blue-300">No outlet stock adjustment data.</td>
            </tr>
            <tr v-for="adjustment in adjustments.data" :key="adjustment.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ adjustment.number }}</td>
              <td class="px-6 py-3">{{ formatDate(adjustment.date) }}</td>
              <td class="px-6 py-3">{{ adjustment.outlet?.nama_outlet }}</td>
              <td class="px-6 py-3">{{ adjustment.warehouse_outlet_name || '-' }}</td>
              <td class="px-6 py-3">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-semibold',
                  adjustment.type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                ]">
                  {{ adjustment.type === 'in' ? 'Stock In' : 'Stock Out' }}
                </span>
              </td>
              <td class="px-6 py-3">{{ adjustment.creator?.nama_lengkap }}</td>
              <td class="px-6 py-3">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-semibold',
                  statusClass(adjustment.status)
                ]">
                  {{ statusLabel(adjustment.status) }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="flex items-center gap-2">
                  <Link
                    :href="route('outlet-food-inventory-adjustment.show', adjustment.id)"
                    class="text-blue-600 hover:text-blue-800"
                    title="View Details"
                  >
                    <i class="fa fa-eye"></i>
                  </Link>
                  <button
                    v-if="canDelete(adjustment)"
                    @click="confirmDelete(adjustment.id)"
                    :disabled="!canDelete(adjustment)"
                    :class="[
                      'text-red-600 hover:text-red-800',
                      !canDelete(adjustment) ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                    :title="canDelete(adjustment) ? 'Delete' : getDeleteTooltip(adjustment)"
                  >
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4">
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
  return d.toLocaleDateString('id-ID');
}

const statusClass = (status) => {
  switch (status) {
    case 'waiting_approval':
      return 'bg-yellow-100 text-yellow-800'
    case 'waiting_cost_control':
      return 'bg-blue-100 text-blue-800'
    case 'approved':
      return 'bg-green-100 text-green-800'
    case 'rejected':
      return 'bg-red-100 text-red-800'
    default:
      return 'bg-gray-100 text-gray-800'
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
  // Allow delete for waiting_approval and waiting_cost_control status
  const deletableStatuses = ['waiting_approval', 'waiting_cost_control'];
  const isDeletableStatus = deletableStatuses.includes(adjustment.status);
  
  // Allow delete for approved status if user has special role
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