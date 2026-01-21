<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-right-left"></i> Detail Pindah Outlet
        </h1>
        <div class="flex gap-2">
          <Link
            :href="route('outlet-transfer.index')"
            class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-arrow-left mr-2"></i> Kembali
          </Link>
          <button
            v-if="transfer.status === 'draft'"
            @click="submitTransfer"
            class="bg-green-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-paper-plane mr-2"></i> Submit
          </button>
          <button
            v-if="transfer.status === 'submitted' && canApprove"
            @click="approveTransfer('approve')"
            class="bg-yellow-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-check mr-2"></i> Approve
          </button>
          <button
            v-if="transfer.status === 'submitted' && canApprove"
            @click="approveTransfer('reject')"
            class="bg-red-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-times mr-2"></i> Reject
          </button>
          <button
            v-if="transfer.status === 'draft'"
            @click="confirmDelete"
            class="bg-red-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa fa-trash mr-2"></i> Hapus
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <div class="grid grid-cols-2 gap-6">
          <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Transfer</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600">Nomor Transfer</label>
                <div class="mt-1 font-mono font-semibold text-blue-700">{{ transfer.transfer_number }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Tanggal Transfer</label>
                <div class="mt-1">{{ formatDate(transfer.transfer_date) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Status</label>
                <div class="mt-1">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full" :class="getStatusClass(transfer.status)">
                    {{ getStatusText(transfer.status) }}
                  </span>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Dibuat Oleh</label>
                <div class="mt-1">{{ transfer.creator?.nama_lengkap }}</div>
              </div>
              <div v-if="transfer.approver">
                <label class="block text-sm font-medium text-gray-600">Disetujui Oleh</label>
                <div class="mt-1">{{ transfer.approver?.nama_lengkap }}</div>
              </div>
              <div v-if="transfer.approval_at">
                <label class="block text-sm font-medium text-gray-600">Tanggal Approval</label>
                <div class="mt-1">{{ formatDate(transfer.approval_at) }}</div>
              </div>
              <div v-if="transfer.approval_notes">
                <label class="block text-sm font-medium text-gray-600">Catatan Approval</label>
                <div class="mt-1">{{ transfer.approval_notes }}</div>
              </div>
            </div>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Gudang</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600">Outlet Asal</label>
                <div class="mt-1">{{ getOutletName(transfer.warehouse_outlet_from?.outlet_id) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Warehouse Outlet Asal</label>
                <div class="mt-1">{{ transfer.warehouse_outlet_from?.name }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Outlet Tujuan</label>
                <div class="mt-1">{{ getOutletName(transfer.warehouse_outlet_to?.outlet_id) }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Warehouse Outlet Tujuan</label>
                <div class="mt-1">{{ transfer.warehouse_outlet_to?.name }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Keterangan</label>
                <div class="mt-1">{{ transfer.notes || '-' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Approval Flow -->
      <div v-if="(transfer.approval_flows && transfer.approval_flows.length > 0)" class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Approval Flow</h2>
        <div class="space-y-3">
          <div
            v-for="flow in transfer.approval_flows"
            :key="flow.id"
            class="flex items-center gap-4 p-3 rounded-lg border"
            :class="getFlowClass(flow)"
          >
            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold"
                 :class="getFlowNumberClass(flow)">
              {{ flow.approval_level }}
            </div>
            <div class="flex-1">
              <div class="font-semibold">{{ flow.approver?.nama_lengkap || '-' }}</div>
              <div class="text-sm text-gray-600">{{ flow.approver?.jabatan?.nama_jabatan || '-' }}</div>
            </div>
            <div class="flex-shrink-0">
              <span :class="getFlowStatusClass(flow.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                {{ flow.status }}
              </span>
            </div>
            <div v-if="flow.comments" class="flex-shrink-0 text-sm text-gray-600">
              <i class="fa fa-comment mr-1"></i>
              {{ flow.comments }}
            </div>
            <div v-if="flow.approved_at" class="flex-shrink-0 text-xs text-gray-500">
              {{ formatDate(flow.approved_at) }}
            </div>
            <div v-if="flow.rejected_at" class="flex-shrink-0 text-xs text-gray-500">
              {{ formatDate(flow.rejected_at) }}
            </div>
          </div>
        </div>
        <div v-if="pendingFlow" class="mt-4 text-sm text-gray-600">
          Next approver: <span class="font-semibold">{{ pendingFlow.approver?.nama_lengkap || pendingFlow.approver_id }}</span>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-6 border-b">
          <h2 class="text-lg font-semibold text-gray-800">Detail Item</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Keterangan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="item in transfer.items" :key="item.id" class="hover:bg-blue-50">
                <td class="px-6 py-4">
                  <div class="font-medium text-gray-900">{{ item.item?.name }}</div>
                  <div class="text-sm text-gray-500">{{ item.item?.sku }}</div>
                </td>
                <td class="px-6 py-4">{{ item.quantity }}</td>
                <td class="px-6 py-4">{{ item.unit?.name || '-' }}</td>
                <td class="px-6 py-4">{{ item.note || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Submit Modal (pilih approvers) -->
    <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Submit untuk Approval</h3>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Approvers (berurutan dari terendah ke tertinggi)</label>
          <select
            v-model="selectedApprovers"
            multiple
            size="8"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option v-for="u in (users || [])" :key="u.id" :value="u.id">
              {{ u.nama_lengkap }} - {{ u.jabatan?.nama_jabatan || '-' }}
            </option>
          </select>
          <p class="mt-2 text-xs text-gray-500">Approver level tertinggi akan eksekusi pindah stok saat approve terakhir. Jika ada reject, proses berhenti.</p>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="closeSubmitModal" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
            Batal
          </button>
          <button @click="submitForApproval" :disabled="submitting || selectedApprovers.length === 0" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50">
            <i v-if="submitting" class="fa fa-spinner fa-spin mr-2"></i>
            Submit
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  transfer: Object,
  outlets: Object,
  user: Object,
  canApprove: Boolean,
  pendingFlow: Object,
  users: Array,
});

const showSubmitModal = ref(false);
const selectedApprovers = ref([]);
const submitting = ref(false);

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getOutletName(outletId) {
  if (!outletId || !props.outlets) return '-';
  return props.outlets[outletId]?.nama_outlet || '-';
}

function getStatusText(status) {
  const statusMap = {
    'draft': 'Draft',
    'submitted': 'Menunggu Approval',
    'approved': 'Disetujui',
    'rejected': 'Ditolak'
  };
  return statusMap[status] || status;
}

function getStatusClass(status) {
  const classMap = {
    'draft': 'bg-gray-100 text-gray-800',
    'submitted': 'bg-yellow-100 text-yellow-800',
    'approved': 'bg-green-100 text-green-800',
    'rejected': 'bg-red-100 text-red-800'
  };
  return classMap[status] || 'bg-gray-100 text-gray-800';
}

const canApprove = computed(() => {
  return !!props.canApprove;
});

function submitTransfer() {
  selectedApprovers.value = [];
  showSubmitModal.value = true;
}

function closeSubmitModal() {
  showSubmitModal.value = false;
  selectedApprovers.value = [];
}

async function submitForApproval() {
  if (!selectedApprovers.value || selectedApprovers.value.length === 0) {
    Swal.fire('Error!', 'Pilih minimal 1 approver.', 'error');
    return;
  }
  submitting.value = true;
  try {
    await axios.post(route('outlet-transfer.submit', props.transfer.id), {
      approvers: selectedApprovers.value,
    });
    closeSubmitModal();
    Swal.fire('Berhasil!', 'Transfer berhasil di-submit untuk approval.', 'success');
    router.reload({ preserveScroll: true });
  } catch (e) {
    Swal.fire('Error!', 'Terjadi kesalahan saat submit transfer.', 'error');
  } finally {
    submitting.value = false;
  }
}

function approveTransfer(action) {
  const isReject = action === 'reject';
  Swal.fire({
    title: isReject ? 'Reject Transfer?' : 'Approve Transfer?',
    text: isReject
      ? 'Transfer akan ditolak dan proses approval berhenti.'
      : 'Transfer akan di-approve. Jika ini approval terakhir, stock akan dipindahkan.',
    icon: isReject ? 'warning' : 'question',
    showCancelButton: true,
    confirmButtonColor: isReject ? '#d33' : '#28a745',
    cancelButtonColor: '#6b7280',
    confirmButtonText: isReject ? 'Ya, Reject!' : 'Ya, Approve!',
    cancelButtonText: 'Batal',
    input: 'textarea',
    inputPlaceholder: isReject ? 'Alasan reject (wajib)' : 'Catatan approval (opsional)',
    inputAttributes: { 'aria-label': 'Catatan' },
    preConfirm: (value) => {
      if (isReject && (!value || !value.trim())) {
        Swal.showValidationMessage('Alasan reject wajib diisi.');
      }
      return value || '';
    }
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route('outlet-transfer.approve', props.transfer.id), {
        action,
        comments: result.value || '',
      }, {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Berhasil!', isReject ? 'Transfer telah di-reject.' : 'Approval berhasil diproses.', 'success');
        },
        onError: () => {
          Swal.fire('Error!', 'Terjadi kesalahan saat proses approval.', 'error');
        }
      });
    }
  });
}

function getFlowClass(flow) {
  if (flow.status === 'APPROVED') return 'bg-green-50 border-green-200';
  if (flow.status === 'REJECTED') return 'bg-red-50 border-red-200';
  if (flow.status === 'PENDING') return 'bg-yellow-50 border-yellow-200';
  return 'bg-gray-50 border-gray-200';
}

function getFlowNumberClass(flow) {
  if (flow.status === 'APPROVED') return 'bg-green-500 text-white';
  if (flow.status === 'REJECTED') return 'bg-red-500 text-white';
  if (flow.status === 'PENDING') return 'bg-yellow-500 text-white';
  return 'bg-gray-500 text-white';
}

function getFlowStatusClass(status) {
  if (status === 'APPROVED') return 'bg-green-200 text-green-800';
  if (status === 'REJECTED') return 'bg-red-200 text-red-800';
  return 'bg-yellow-200 text-yellow-800';
}

function confirmDelete() {
  Swal.fire({
    title: 'Apakah Anda yakin?',
    text: "Data yang dihapus tidak dapat dikembalikan!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('outlet-transfer.destroy', props.transfer.id), {
        onSuccess: () => {
          Swal.fire(
            'Terhapus!',
            'Data berhasil dihapus.',
            'success'
          );
        },
        onError: () => {
          Swal.fire(
            'Error!',
            'Terjadi kesalahan saat menghapus data.',
            'error'
          );
        }
      });
    }
  });
}
</script> 