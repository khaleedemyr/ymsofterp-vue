<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Detail Warehouse Stock Opname
        </h1>
        <Link
          :href="route('warehouse-stock-opnames.index')"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i> Kembali
        </Link>
      </div>

      <!-- Header Info -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Warehouse Stock Opname</h3>
            <div class="space-y-2">
              <div>
                <span class="text-sm font-medium text-gray-600">Opname Number:</span>
                <span class="ml-2 text-sm font-bold text-gray-900">{{ stockOpname.opname_number }}</span>
              </div>
              <div>
                <span class="text-sm font-medium text-gray-600">Warehouse:</span>
                <span class="ml-2 text-sm text-gray-900">{{ stockOpname.warehouse?.name || '-' }}</span>
              </div>
              <div>
                <span class="text-sm font-medium text-gray-600">Warehouse Division:</span>
                <span class="ml-2 text-sm text-gray-900">{{ stockOpname.warehouse_division?.name || '-' }}</span>
              </div>
              <div>
                <span class="text-sm font-medium text-gray-600">Tanggal Opname:</span>
                <span class="ml-2 text-sm text-gray-900">{{ formatDate(stockOpname.opname_date) }}</span>
              </div>
              <div>
                <span class="text-sm font-medium text-gray-600">Status:</span>
                <span :class="getStatusClass(stockOpname.status)" class="ml-2 px-2 py-1 rounded-full text-xs font-semibold">
                  {{ stockOpname.status }}
                </span>
              </div>
              <div>
                <span class="text-sm font-medium text-gray-600">Created By:</span>
                <span class="ml-2 text-sm text-gray-900">{{ stockOpname.creator?.nama_lengkap || '-' }}</span>
              </div>
              <div v-if="stockOpname.notes">
                <span class="text-sm font-medium text-gray-600">Catatan:</span>
                <p class="ml-2 text-sm text-gray-900 mt-1">{{ stockOpname.notes }}</p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex flex-col gap-3">
            <div v-if="stockOpname.status === 'DRAFT'" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <h4 class="font-semibold text-yellow-800 mb-2">Aksi Tersedia</h4>
              <div class="flex flex-col gap-2">
                <Link
                  :href="route('warehouse-stock-opnames.edit', stockOpname.id)"
                  class="w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 font-semibold text-center"
                >
                  <i class="fa-solid fa-edit mr-2"></i> Edit
                </Link>
                <button
                  @click="submitForApprovalDirect"
                  :disabled="!stockOpname.approvers || stockOpname.approvers.length === 0"
                  class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                  :title="(!stockOpname.approvers || stockOpname.approvers.length === 0) ? 'Tambahkan approvers di form edit terlebih dahulu' : 'Submit untuk Approval'"
                >
                  Submit untuk Approval
                </button>
                <button
                  @click="showSubmitModal = true"
                  class="w-full px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 font-semibold"
                >
                  <i class="fa-solid fa-user-plus mr-2"></i> Submit dengan Approvers Baru
                </button>
                <button
                  @click="deleteStockOpname"
                  class="w-full px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-semibold"
                >
                  <i class="fa-solid fa-trash mr-2"></i> Delete
                </button>
              </div>
            </div>

            <div v-if="canApprove && stockOpname.status === 'SUBMITTED'" class="bg-green-50 border border-green-200 rounded-lg p-4">
              <h4 class="font-semibold text-green-800 mb-2">Approval</h4>
              <div class="flex gap-2">
                <button
                  @click="showApproveModal = true"
                  class="flex-1 px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 font-semibold"
                >
                  Approve
                </button>
                <button
                  @click="showRejectModal = true"
                  class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-semibold"
                >
                  Reject
                </button>
              </div>
            </div>

            <div v-if="stockOpname.status === 'APPROVED'" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h4 class="font-semibold text-blue-800 mb-2">Process Stock Opname</h4>
              <button
                @click="processStockOpname"
                :disabled="processing"
                class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 font-semibold disabled:opacity-50"
              >
                <i v-if="processing" class="fa fa-spinner fa-spin mr-2"></i>
                Process & Update Inventory
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Approval Flow -->
      <div v-if="stockOpname.approval_flows && stockOpname.approval_flows.length > 0" class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Approval Flow</h3>
        <div class="space-y-3">
          <div
            v-for="flow in stockOpname.approval_flows"
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
              {{ formatDateTime(flow.approved_at) }}
            </div>
            <div v-if="flow.rejected_at" class="flex-shrink-0 text-xs text-gray-500">
              {{ formatDateTime(flow.rejected_at) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Items Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <h3 class="text-lg font-semibold text-gray-800 p-6 mb-0 border-b">Items ({{ stockOpname.items?.length || 0 }})</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Item</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Qty System</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Qty Physical</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Selisih</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">MAC</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Value Adjustment</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Alasan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr
                v-for="item in stockOpname.items"
                :key="item.id"
                :class="{ 'bg-yellow-50': hasDifference(item) }"
              >
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ item.inventory_item?.item?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">
                  <div>S: {{ formatNumber(item.qty_system_small) }}</div>
                  <div>M: {{ formatNumber(item.qty_system_medium) }}</div>
                  <div>L: {{ formatNumber(item.qty_system_large) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">
                  <div>S: {{ formatNumber(item.qty_physical_small) }}</div>
                  <div>M: {{ formatNumber(item.qty_physical_medium) }}</div>
                  <div>L: {{ formatNumber(item.qty_physical_large) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                  <span :class="getDifferenceClass(item)" class="px-2 py-1 rounded font-semibold">
                    <div>S: {{ formatNumber(item.qty_diff_small) }}</div>
                    <div>M: {{ formatNumber(item.qty_diff_medium) }}</div>
                    <div>L: {{ formatNumber(item.qty_diff_large) }}</div>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">
                  {{ formatCurrency(item.mac_before) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                  <span :class="item.value_adjustment >= 0 ? 'text-green-600' : 'text-red-600'" class="font-semibold">
                    {{ formatCurrency(item.value_adjustment) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-700">
                  {{ item.reason || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Submit Modal -->
      <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Submit untuk Approval</h3>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Approvers (berurutan dari terendah ke tertinggi)</label>
            <select
              v-model="selectedApprovers"
              multiple
              size="5"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.nama_lengkap }} - {{ user.jabatan?.nama_jabatan || '-' }}
              </option>
            </select>
            <p class="mt-2 text-xs text-gray-500">Pilih approvers secara berurutan. Yang dipilih pertama = approver terendah, yang dipilih terakhir = approver tertinggi.</p>
          </div>
          <div class="flex justify-end gap-3">
            <button @click="showSubmitModal = false" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
              Batal
            </button>
            <button @click="submitForApproval" :disabled="submitting || selectedApprovers.length === 0" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50">
              <i v-if="submitting" class="fa fa-spinner fa-spin mr-2"></i>
              Submit
            </button>
          </div>
        </div>
      </div>

      <!-- Approve Modal -->
      <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Approve Warehouse Stock Opname</h3>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Comments (optional)</label>
            <textarea
              v-model="approvalComments"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Tambahkan komentar..."
            ></textarea>
          </div>
          <div class="flex justify-end gap-3">
            <button @click="showApproveModal = false" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
              Batal
            </button>
            <button @click="approveStockOpname('approve')" :disabled="approving" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 disabled:opacity-50">
              <i v-if="approving" class="fa fa-spinner fa-spin mr-2"></i>
              Approve
            </button>
          </div>
        </div>
      </div>

      <!-- Reject Modal -->
      <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Reject Warehouse Stock Opname</h3>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Reject *</label>
            <textarea
              v-model="rejectComments"
              rows="3"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
              placeholder="Masukkan alasan reject..."
            ></textarea>
          </div>
          <div class="flex justify-end gap-3">
            <button @click="showRejectModal = false" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
              Batal
            </button>
            <button @click="approveStockOpname('reject')" :disabled="approving || !rejectComments" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 disabled:opacity-50">
              <i v-if="approving" class="fa fa-spinner fa-spin mr-2"></i>
              Reject
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  stockOpname: Object,
  canApprove: Boolean,
  pendingFlow: Object,
  users: Array,
});

const showSubmitModal = ref(false);
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const selectedApprovers = ref([]);
const approvalComments = ref('');
const rejectComments = ref('');
const submitting = ref(false);
const approving = ref(false);
const processing = ref(false);

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function formatDateTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleString('id-ID');
}

function formatNumber(val) {
  if (val == null) return '0';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCurrency(val) {
  if (val == null) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getStatusClass(status) {
  const classes = {
    DRAFT: 'bg-gray-200 text-gray-800',
    SUBMITTED: 'bg-yellow-200 text-yellow-800',
    APPROVED: 'bg-green-200 text-green-800',
    REJECTED: 'bg-red-200 text-red-800',
    COMPLETED: 'bg-blue-200 text-blue-800',
  };
  return classes[status] || 'bg-gray-200 text-gray-800';
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

function hasDifference(item) {
  return item.qty_diff_small !== 0 || item.qty_diff_medium !== 0 || item.qty_diff_large !== 0;
}

function getDifferenceClass(item) {
  if (item.qty_diff_small > 0) return 'text-green-600';
  if (item.qty_diff_small < 0) return 'text-red-600';
  return 'text-gray-600';
}

async function submitForApproval() {
  // Use approvers from modal if available, otherwise use empty array
  const approvers = selectedApprovers.value.length > 0 
    ? selectedApprovers.value 
    : (props.stockOpname.approvers || []).map(a => a.id || a);

  if (approvers.length === 0) {
    Swal.fire({
      title: 'Error',
      text: 'Pilih minimal 1 approver.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
    return;
  }

  submitting.value = true;
  try {
    await axios.post(route('warehouse-stock-opnames.submit-approval', props.stockOpname.id), {
      approvers: approvers,
    });

    showSubmitModal.value = false;
    router.reload();
  } catch (error) {
    console.error('Error submitting:', error);
    Swal.fire({
      title: 'Error',
      text: 'Gagal submit untuk approval. Silakan coba lagi.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
  } finally {
    submitting.value = false;
  }
}

async function approveStockOpname(action) {
  if (action === 'reject' && !rejectComments.value) {
    Swal.fire({
      title: 'Error',
      text: 'Alasan reject wajib diisi.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
    return;
  }

  approving.value = true;
  try {
    await axios.post(route('warehouse-stock-opnames.approve', props.stockOpname.id), {
      action: action,
      comments: action === 'approve' ? approvalComments.value : rejectComments.value,
    });

    showApproveModal.value = false;
    showRejectModal.value = false;
    approvalComments.value = '';
    rejectComments.value = '';
    router.reload();
  } catch (error) {
    console.error('Error approving:', error);
    Swal.fire({
      title: 'Error',
      text: 'Gagal proses approval. Silakan coba lagi.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
  } finally {
    approving.value = false;
  }
}

async function processStockOpname() {
  const result = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Yakin ingin process stock opname ini? Inventory akan di-update.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Process',
    cancelButtonText: 'Batal'
  });

  if (!result.isConfirmed) {
    return;
  }

  processing.value = true;
  try {
    await axios.post(route('warehouse-stock-opnames.process', props.stockOpname.id));
    router.reload();
  } catch (error) {
    console.error('Error processing:', error);
    Swal.fire({
      title: 'Error',
      text: 'Gagal process stock opname. Silakan coba lagi.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
  } finally {
    processing.value = false;
  }
}

async function submitForApprovalDirect() {
  // Submit using approvers from form (if available in stockOpname object)
  const approvers = props.stockOpname.approvers 
    ? props.stockOpname.approvers.map(a => a.id || a)
    : [];

  if (approvers.length === 0) {
    Swal.fire({
      title: 'Error',
      text: 'Tidak ada approvers. Silakan edit stock opname dan tambahkan approvers terlebih dahulu, atau gunakan tombol "Submit dengan Approvers Baru".',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
    return;
  }

  const result = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Yakin ingin submit stock opname untuk approval?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Submit',
    cancelButtonText: 'Batal'
  });

  if (!result.isConfirmed) {
    return;
  }

  submitting.value = true;
  try {
    await axios.post(route('warehouse-stock-opnames.submit-approval', props.stockOpname.id), {
      approvers: approvers,
    });

    router.reload();
  } catch (error) {
    console.error('Error submitting:', error);
    Swal.fire({
      title: 'Error',
      text: 'Gagal submit untuk approval. Silakan coba lagi.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
  } finally {
    submitting.value = false;
  }
}

async function deleteStockOpname() {
  const result = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Yakin ingin menghapus stock opname ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal'
  });

  if (!result.isConfirmed) {
    return;
  }

  try {
    await axios.delete(route('warehouse-stock-opnames.destroy', props.stockOpname.id));
    router.visit(route('warehouse-stock-opnames.index'));
  } catch (error) {
    console.error('Error deleting:', error);
    Swal.fire({
      title: 'Error',
      text: 'Gagal menghapus stock opname. Silakan coba lagi.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
  }
}
</script>

