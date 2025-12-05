<template>
  <Head :title="'Outlet Stock Adjustment #' + adjustment.number" />
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="$inertia.visit('/outlet-food-inventory-adjustment')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold flex items-center gap-2 ml-4">
          <i class="fa-solid fa-store text-blue-500"></i> Outlet Stock Adjustment Detail
        </h1>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div><b>Nomor:</b> {{ adjustment.number }}</div>
            <div><b>Tanggal:</b> {{ adjustment.date }}</div>
            <div><b>Outlet:</b> {{ adjustment.outlet?.nama_outlet }}</div>
            <div><b>Warehouse Outlet:</b> {{ adjustment.warehouse_outlet_name || '-' }} <span v-if="adjustment.warehouse_outlet_id">(ID: {{ adjustment.warehouse_outlet_id }})</span></div>
            <div><b>Tipe:</b> {{ adjustment.type }}</div>
            <div><b>Alasan:</b> {{ adjustment.reason }}</div>
          </div>
          <div>
            <div><b>Status:</b> <span :class="statusClass(adjustment.status)">{{ statusLabel(adjustment.status) }}</span></div>
            <div><b>Dibuat oleh:</b> {{ adjustment.creator?.nama_lengkap }}</div>
            <div><b>Dibuat pada:</b> {{ formatDateTime(adjustment.created_at) }}</div>
          </div>
        </div>

        <div class="mt-6">
          <h3 class="text-lg font-semibold mb-3">Items</h3>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in adjustment.items" :key="item.id">
                  <td class="px-3 py-2">{{ item.item_name || item.item?.name }}</td>
                  <td class="px-3 py-2">{{ item.qty }}</td>
                  <td class="px-3 py-2">{{ item.unit }}</td>
                  <td class="px-3 py-2">{{ item.note }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="adjustment.status !== 'approved' && adjustment.status !== 'rejected'" class="mt-6">
          <h3 class="text-lg font-semibold mb-3">Approval</h3>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Note</label>
            <textarea
              v-model="approvalNote"
              rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              placeholder="Enter approval/rejection note..."
            ></textarea>
          </div>
          <div class="flex gap-3">
            <button
              v-if="canApprove"
              @click="approve"
              class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 font-semibold"
              :disabled="loading"
            >
              <span v-if="loading" class="flex items-center gap-2">
                <div class="loader"></div>
                Processing...
              </span>
              <span v-else>Approve</span>
            </button>
            <button
              v-if="canReject"
              @click="reject"
              class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 font-semibold"
              :disabled="loading"
            >
              <span v-if="loading" class="flex items-center gap-2">
                <div class="loader"></div>
                Processing...
              </span>
              <span v-else>Reject</span>
            </button>
          </div>
        </div>

        <div v-if="adjustment.status === 'approved' || adjustment.status === 'rejected'" class="mt-6">
          <h3 class="text-lg font-semibold mb-3">Approval History</h3>
          <div class="space-y-4">
            <div v-if="adjustment.approved_by_ssd_manager" class="p-4 bg-gray-50 rounded-lg">
              <div class="flex justify-between items-start">
                <div>
                  <div class="font-medium">SSD Manager</div>
                  <div class="text-sm text-gray-600">{{ adjustment.ssd_manager_note }}</div>
                </div>
                <div class="text-sm text-gray-500">{{ formatDateTime(adjustment.approved_at_ssd_manager) }}</div>
              </div>
            </div>
            <div v-if="adjustment.approved_by_cost_control_manager" class="p-4 bg-gray-50 rounded-lg">
              <div class="flex justify-between items-start">
                <div>
                  <div class="font-medium">Cost Control Manager</div>
                  <div class="text-sm text-gray-600">{{ adjustment.cost_control_manager_note }}</div>
                </div>
                <div class="text-sm text-gray-500">{{ formatDateTime(adjustment.approved_at_cost_control_manager) }}</div>
              </div>
            </div>
          </div>
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

const formatDateTime = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  if (isNaN(d)) return '-';
  return d.toLocaleString('id-ID');
}

function approve() {
  Swal.fire({
    title: 'Approve Outlet Stock Adjustment?',
    text: 'Setelah approve, data akan diproses ke inventory jika sudah final. Lanjutkan?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Approve',
    cancelButtonText: 'Batal',
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
.loader {
  border-width: 2px;
  border-style: solid;
  border-radius: 9999px;
  width: 1.5rem;
  height: 1.5rem;
  border-top-color: transparent;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style> 