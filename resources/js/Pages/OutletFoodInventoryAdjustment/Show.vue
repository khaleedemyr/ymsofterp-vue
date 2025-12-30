<template>
  <Head :title="'Outlet Stock Adjustment #' + adjustment.number" />
  <AppLayout>
    <div class="w-full py-8 px-4 md:px-6">
      <!-- Header -->
      <div class="mb-6">
        <button 
          @click="$inertia.visit('/outlet-food-inventory-adjustment')" 
          class="group mb-4 inline-flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-blue-600 transition-colors duration-200"
        >
          <i class="fa fa-arrow-left group-hover:-translate-x-1 transition-transform duration-200"></i>
          <span class="font-medium">Kembali</span>
        </button>
        <div class="flex items-center gap-4">
          <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg">
            <i class="fa-solid fa-store text-white text-2xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Outlet Stock Adjustment Detail</h1>
            <p class="text-gray-600 mt-1">Nomor: <span class="font-mono font-semibold text-blue-600">{{ adjustment.number }}</span></p>
          </div>
        </div>
      </div>

      <!-- Main Info Card -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Left Column -->
          <div class="space-y-4">
            <div class="p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200">
              <div class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-1">Nomor Adjustment</div>
              <div class="text-lg font-bold text-gray-900 font-mono">{{ adjustment.number }}</div>
            </div>
            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
              <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Tanggal</div>
              <div class="text-lg font-semibold text-gray-900">{{ formatDate(adjustment.date) }}</div>
            </div>
            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
              <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Outlet</div>
              <div class="text-lg font-semibold text-gray-900">{{ adjustment.nama_outlet || '-' }}</div>
            </div>
            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
              <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Warehouse Outlet</div>
              <div class="text-lg font-semibold text-gray-900">{{ adjustment.warehouse_outlet_name || '-' }}</div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="space-y-4">
            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
              <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Tipe</div>
              <div>
                <span :class="[
                  'inline-block px-4 py-2 rounded-full text-sm font-bold shadow-sm',
                  adjustment.type === 'in' 
                    ? 'bg-gradient-to-r from-green-100 to-green-50 text-green-800 border border-green-200' 
                    : 'bg-gradient-to-r from-red-100 to-red-50 text-red-800 border border-red-200'
                ]">
                  <i :class="adjustment.type === 'in' ? 'fa fa-arrow-down' : 'fa fa-arrow-up'" class="mr-2"></i>
                  {{ adjustment.type === 'in' ? 'Stock In' : 'Stock Out' }}
                </span>
              </div>
            </div>
            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
              <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Status</div>
              <div>
                <span :class="[
                  'inline-block px-4 py-2 rounded-full text-sm font-bold shadow-sm',
                  statusClass(adjustment.status)
                ]">
                  {{ statusLabel(adjustment.status) }}
                </span>
              </div>
            </div>
            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
              <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Dibuat Oleh</div>
              <div class="text-lg font-semibold text-gray-900">{{ adjustment.creator_nama_lengkap || '-' }}</div>
            </div>
            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
              <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Dibuat Pada</div>
              <div class="text-sm font-medium text-gray-700">{{ formatDateTime(adjustment.created_at) }}</div>
            </div>
          </div>
        </div>

        <!-- Reason -->
        <div class="mt-6 p-4 bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl border border-amber-200">
          <div class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2">
            <i class="fa fa-info-circle mr-2"></i>Alasan
          </div>
          <div class="text-gray-900 font-medium">{{ adjustment.reason }}</div>
        </div>
      </div>

      <!-- Items Table -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <div class="p-2 bg-blue-100 rounded-lg">
              <i class="fa fa-boxes text-blue-600"></i>
            </div>
            Items ({{ adjustment.items?.length || 0 }})
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Quantity</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Note</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr 
                v-for="(item, index) in adjustment.items" 
                :key="item.id"
                class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent transition-all duration-200"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3 shadow-md">
                      <span class="text-white font-bold text-sm">{{ index + 1 }}</span>
                    </div>
                    <div class="text-sm font-semibold text-gray-900">{{ item.item_name || item.item?.name || '-' }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-bold text-gray-900">{{ formatNumber(item.qty) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium">{{ item.unit || '-' }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-600">{{ item.note || '-' }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Approval Section -->
      <div v-if="adjustment.status !== 'approved' && adjustment.status !== 'rejected'" class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2 mb-4">
          <div class="p-2 bg-green-100 rounded-lg">
            <i class="fa fa-check-circle text-green-600"></i>
          </div>
          Approval Action
        </h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa fa-comment text-gray-400 mr-2"></i>Note
            </label>
            <textarea
              v-model="approvalNote"
              rows="4"
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md resize-none"
              placeholder="Enter approval/rejection note..."
            ></textarea>
          </div>
          <div class="flex gap-3">
            <button
              v-if="canApprove"
              @click="approve"
              :disabled="loading"
              class="group relative px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 font-semibold flex items-center gap-2 overflow-hidden disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="absolute inset-0 bg-gradient-to-r from-green-600 to-green-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
              <i v-if="!loading" class="fa fa-check relative z-10"></i>
              <i v-else class="fa fa-spinner fa-spin relative z-10"></i>
              <span class="relative z-10">{{ loading ? 'Processing...' : 'Approve' }}</span>
            </button>
            <button
              v-if="canReject"
              @click="reject"
              :disabled="loading"
              class="group relative px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 font-semibold flex items-center gap-2 overflow-hidden disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="absolute inset-0 bg-gradient-to-r from-red-600 to-red-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
              <i v-if="!loading" class="fa fa-times relative z-10"></i>
              <i v-else class="fa fa-spinner fa-spin relative z-10"></i>
              <span class="relative z-10">{{ loading ? 'Processing...' : 'Reject' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Approval History -->
      <div v-if="(adjustment.status === 'approved' || adjustment.status === 'rejected') && adjustment.approval_flows && adjustment.approval_flows.length > 0" class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2 mb-4">
          <div class="p-2 bg-purple-100 rounded-lg">
            <i class="fa fa-history text-purple-600"></i>
          </div>
          Approval History
        </h3>
        <div class="space-y-4">
          <div 
            v-for="(flow, index) in adjustment.approval_flows" 
            :key="flow.id"
            :class="[
              'p-5 rounded-xl border hover:shadow-md transition-shadow duration-200',
              flow.status === 'APPROVED' 
                ? 'bg-gradient-to-br from-green-50 to-green-100 border-green-200' 
                : flow.status === 'REJECTED'
                ? 'bg-gradient-to-br from-red-50 to-red-100 border-red-200'
                : 'bg-gradient-to-br from-yellow-50 to-yellow-100 border-yellow-200'
            ]"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <div :class="[
                    'p-2 rounded-lg',
                    flow.status === 'APPROVED' 
                      ? 'bg-green-500' 
                      : flow.status === 'REJECTED'
                      ? 'bg-red-500'
                      : 'bg-yellow-500'
                  ]">
                    <i :class="[
                      'text-white text-sm',
                      flow.status === 'APPROVED' 
                        ? 'fa fa-check' 
                        : flow.status === 'REJECTED'
                        ? 'fa fa-times'
                        : 'fa fa-clock'
                    ]"></i>
                  </div>
                  <div>
                    <div class="font-bold text-gray-900">{{ flow.nama_lengkap || flow.nama_jabatan || 'Approver ' + (index + 1) }}</div>
                    <div v-if="flow.nama_jabatan" class="text-xs text-gray-500">{{ flow.nama_jabatan }}</div>
                  </div>
                </div>
                <div v-if="flow.comments" class="text-sm text-gray-700 ml-12 mt-2">{{ flow.comments }}</div>
                <div v-else-if="flow.status === 'PENDING'" class="text-sm text-gray-500 ml-12 mt-2 italic">Menunggu approval...</div>
              </div>
              <div class="text-right">
                <div v-if="flow.approved_at" class="text-xs text-gray-500 font-medium">{{ formatDateTime(flow.approved_at) }}</div>
                <div v-else class="text-xs text-gray-400 italic">Pending</div>
                <div :class="[
                  'mt-2 px-2 py-1 rounded-full text-xs font-semibold',
                  flow.status === 'APPROVED' 
                    ? 'bg-green-200 text-green-800' 
                    : flow.status === 'REJECTED'
                    ? 'bg-red-200 text-red-800'
                    : 'bg-yellow-200 text-yellow-800'
                ]">
                  {{ flow.status }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div v-else-if="adjustment.status === 'approved' || adjustment.status === 'rejected'" class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2 mb-4">
          <div class="p-2 bg-purple-100 rounded-lg">
            <i class="fa fa-history text-purple-600"></i>
          </div>
          Approval History
        </h3>
        <div class="text-center py-8 text-gray-500">
          <i class="fa fa-info-circle text-3xl mb-3"></i>
          <p>Tidak ada approval history tersedia</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  adjustment: Object,
  user: Object
})

const loading = ref(false)
const approvalNote = ref('')

const canApprove = computed(() => {
  const user = props.user
  const isSuperadmin = user && user.id_role === '5af56935b011a' && user.status === 'A'
  if (isSuperadmin) {
    return props.adjustment.status === 'waiting_approval' || props.adjustment.status === 'waiting_cost_control'
  }
  if (user && user.id_jabatan == 161) {
    return props.adjustment.status === 'waiting_approval'
  }
  if (user && user.id_jabatan == 167) {
    return props.adjustment.status === 'waiting_cost_control'
  }
  return false
})

const canReject = computed(() => {
  const user = props.user
  const isSuperadmin = user && user.id_role === '5af56935b011a' && user.status === 'A'
  if (isSuperadmin) {
    return props.adjustment.status === 'waiting_approval' || props.adjustment.status === 'waiting_cost_control'
  }
  if (user && user.id_jabatan == 161) {
    return props.adjustment.status === 'waiting_approval'
  }
  if (user && user.id_jabatan == 167) {
    return props.adjustment.status === 'waiting_cost_control'
  }
  return false
})

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

const formatDate = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  if (isNaN(d)) return '-';
  return d.toLocaleDateString('id-ID', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
}

const formatDateTime = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  if (isNaN(d)) return '-';
  return d.toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0';
  return Number(num).toLocaleString('id-ID', { 
    minimumFractionDigits: 2, 
    maximumFractionDigits: 2 
  });
}

function approve() {
  Swal.fire({
    title: 'Approve Outlet Stock Adjustment?',
    text: 'Setelah approve, data akan diproses ke inventory jika sudah final. Lanjutkan?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Approve',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#10B981',
    cancelButtonColor: '#6B7280',
    preConfirm: () => {
      loading.value = true
      return router.post(`/outlet-food-inventory-adjustment/${props.adjustment.id}/approve`, { note: approvalNote.value }, {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Berhasil', 'Outlet stock adjustment berhasil di-approve!', 'success')
          router.reload()
        },
        onError: (err) => {
          Swal.fire('Gagal', err?.error || 'Gagal approve', 'error')
        },
        onFinish: () => {
          loading.value = false
        }
      })
    },
    allowOutsideClick: () => !Swal.isLoading()
  })
}

function reject() {
  Swal.fire({
    title: 'Reject Outlet Stock Adjustment?',
    text: 'Data akan ditolak dan tidak diproses ke inventory. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Reject',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#6B7280',
    preConfirm: () => {
      loading.value = true
      return router.post(`/outlet-food-inventory-adjustment/${props.adjustment.id}/reject`, { note: approvalNote.value }, {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Ditolak', 'Outlet stock adjustment berhasil direject.', 'success')
          router.reload()
        },
        onError: (err) => {
          Swal.fire('Gagal', err?.error || 'Gagal reject', 'error')
        },
        onFinish: () => {
          loading.value = false
        }
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
