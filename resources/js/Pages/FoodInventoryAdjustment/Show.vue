<template>
  <Head :title="'Stock Adjustment #' + adjustment.number" />
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="$inertia.visit('/food-inventory-adjustment')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold flex items-center gap-2 ml-4">
          <i class="fa-solid fa-boxes-stacked text-blue-500"></i> Stock Adjustment Detail
        </h1>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div><b>Nomor:</b> {{ adjustment.number }}</div>
            <div><b>Tanggal:</b> {{ adjustment.date }}</div>
            <div><b>Gudang:</b> {{ adjustment.warehouse?.name }}</div>
            <div><b>Tipe:</b> {{ adjustment.type }}</div>
            <div><b>Alasan:</b> {{ adjustment.reason }}</div>
          </div>
          <div>
            <div><b>Status:</b> <span :class="statusClass(adjustment.status)">{{ statusLabel(adjustment.status) }}</span></div>
            <div><b>Dibuat oleh:</b> {{ adjustment.creator?.nama_lengkap }}</div>
            <div><b>Dibuat pada:</b> {{ formatDateTime(adjustment.created_at) }}</div>
          </div>
        </div>
        <div class="mb-6">
          <h2 class="font-semibold mb-2">Detail Item</h2>
          <table class="w-full border">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in adjustment.items" :key="item.id">
                <td>{{ item.item?.name }}</td>
                <td>{{ item.qty }}</td>
                <td>{{ item.unit }}</td>
                <td>{{ item.note }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Approval Section -->
        <div v-if="canApprove" class="mb-6 p-4 bg-blue-50 rounded">
          <h3 class="font-semibold mb-2">Approval</h3>
          <textarea v-model="approvalNote" class="w-full border rounded mb-2" placeholder="Catatan approval (opsional)"></textarea>
          <div class="flex gap-2">
            <button @click="approve" :disabled="loading" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Approve</button>
            <button @click="reject" :disabled="loading" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Reject</button>
          </div>
        </div>
        <!-- Riwayat Approval -->
        <div class="mb-4">
          <h3 class="font-semibold mb-2">Riwayat Approval</h3>
          <ul class="text-sm">
            <li v-if="adjustment.approved_by_ssd_manager">
              <b>SSD Manager:</b> {{ adjustment.ssd_manager_note }} ({{ adjustment.approved_at_ssd_manager }})
            </li>
            <li v-if="adjustment.approved_by_cost_control_manager">
              <b>Cost Control Manager:</b> {{ adjustment.cost_control_manager_note }} ({{ adjustment.approved_at_cost_control_manager }})
            </li>
          </ul>
        </div>
      </div>
      <div v-if="loading" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
        <div class="bg-white px-6 py-4 rounded shadow flex items-center gap-2">
          <span class="loader border-2 border-blue-500 border-t-transparent rounded-full w-6 h-6 animate-spin"></span>
          <span>Memproses...</span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  adjustment: Object,
  user: Object
})

const approvalNote = ref('')
const loading = ref(false)

const canApprove = computed(() => {
  if (!props.user) return false
  if (props.adjustment.status === 'waiting_approval' && [161, 172].includes(props.user.id_jabatan)) return true
  if (props.adjustment.status === 'waiting_cost_control' && props.user.id_jabatan === 167) return true
  if (props.user.id_role === '5af56935b011a' && props.user.status === 'A' && ['waiting_approval','waiting_cost_control'].includes(props.adjustment.status)) return true
  return false
})

function approve() {
  Swal.fire({
    title: 'Approve Stock Adjustment?',
    text: 'Setelah approve, data akan diproses ke inventory jika sudah final. Lanjutkan?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Approve',
    cancelButtonText: 'Batal',
    preConfirm: () => {
      loading.value = true
      return router.post(`/food-inventory-adjustment/${props.adjustment.id}/approve`, { note: approvalNote.value }, {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Berhasil', 'Stock adjustment berhasil di-approve!', 'success')
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
    title: 'Reject Stock Adjustment?',
    text: 'Data akan ditolak dan tidak diproses ke inventory. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Reject',
    cancelButtonText: 'Batal',
    preConfirm: () => {
      loading.value = true
      return router.post(`/food-inventory-adjustment/${props.adjustment.id}/reject`, { note: approvalNote.value }, {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Ditolak', 'Stock adjustment berhasil direject.', 'success')
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

function statusLabel(status) {
  switch (status) {
    case 'waiting_approval': return 'Menunggu SSD Manager';
    case 'waiting_cost_control': return 'Menunggu Cost Control Manager';
    case 'approved': return 'Approved';
    case 'rejected': return 'Rejected';
    default: return status;
  }
}
function statusClass(status) {
  switch (status) {
    case 'approved': return 'text-green-600 font-bold';
    case 'rejected': return 'text-red-600 font-bold';
    default: return 'text-yellow-600 font-bold';
  }
}
function formatDateTime(date) {
  return new Date(date).toLocaleString('id-ID')
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