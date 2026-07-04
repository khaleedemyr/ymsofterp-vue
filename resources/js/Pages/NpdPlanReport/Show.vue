<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <div>
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-lightbulb text-amber-500"></i>
              {{ record.number }}
            </h1>
            <span :class="statusClass(record.status)" class="px-3 py-1 rounded-full text-xs font-semibold capitalize">
              {{ record.status }}
            </span>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            {{ formatMonth(record.report_month) }} · {{ record.outlet_name }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('npd-plan-report.index')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
          >
            <i class="fa-solid fa-arrow-left"></i>
            Kembali
          </Link>
          <Link
            v-if="canEdit"
            :href="route('npd-plan-report.edit', record.id)"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition"
          >
            <i class="fa-solid fa-pen"></i>
            Edit
          </Link>
          <button
            v-if="canDelete"
            type="button"
            @click="deleteReport"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition"
          >
            <i class="fa-solid fa-trash"></i>
            Hapus
          </button>
          <button
            v-if="canSubmitApproval"
            type="button"
            @click="showApproverModal = true"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition"
          >
            <i class="fa-solid fa-paper-plane"></i>
            Submit Approval
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Report</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
              <div class="text-gray-500 text-xs uppercase tracking-wide">Nomor</div>
              <div class="font-semibold text-gray-900 mt-1">{{ record.number }}</div>
            </div>
            <div>
              <div class="text-gray-500 text-xs uppercase tracking-wide">Bulan Report</div>
              <div class="font-semibold text-gray-900 mt-1">{{ formatMonth(record.report_month) }}</div>
            </div>
            <div>
              <div class="text-gray-500 text-xs uppercase tracking-wide">Outlet</div>
              <div class="font-semibold text-gray-900 mt-1">{{ record.outlet_name }}</div>
            </div>
            <div>
              <div class="text-gray-500 text-xs uppercase tracking-wide">Dibuat Oleh</div>
              <div class="font-semibold text-gray-900 mt-1">{{ record.creator?.nama_lengkap || '-' }}</div>
            </div>
            <div v-if="record.notes" class="sm:col-span-2">
              <div class="text-gray-500 text-xs uppercase tracking-wide">Catatan</div>
              <div class="text-gray-900 mt-1">{{ record.notes }}</div>
            </div>
          </div>
        </div>

        <div v-if="canApprove" class="bg-green-50 border border-green-200 rounded-xl shadow p-6">
          <h2 class="text-lg font-semibold text-green-800 mb-3">Approval Diperlukan</h2>
          <p class="text-sm text-green-700 mb-4">Report ini menunggu persetujuan Anda.</p>
          <div class="flex gap-2">
            <button
              type="button"
              @click="showApproveModal = true"
              class="flex-1 px-4 py-2 rounded-lg bg-green-500 text-white hover:bg-green-600 font-semibold"
            >
              Approve
            </button>
            <button
              type="button"
              @click="showRejectModal = true"
              class="flex-1 px-4 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600 font-semibold"
            >
              Reject
            </button>
          </div>
        </div>

        <div
          v-else-if="record.approval_flows?.length"
          class="bg-white rounded-xl shadow p-6"
        >
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Status Approval</h2>
          <div class="space-y-3">
            <div
              v-for="flow in record.approval_flows"
              :key="flow.id"
              class="flex items-center gap-3 p-3 rounded-lg border"
              :class="flowStatusBg(flow.status)"
            >
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                :class="flowNumberClass(flow.status)"
              >
                {{ flow.approval_level }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm truncate">{{ flow.approver?.nama_lengkap || '-' }}</div>
                <div v-if="flow.comments" class="text-xs text-gray-500 mt-0.5 truncate">{{ flow.comments }}</div>
              </div>
              <span :class="flowBadgeClass(flow.status)" class="px-2 py-1 rounded-full text-xs font-semibold">
                {{ flow.status }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b bg-gradient-to-r from-amber-50 to-white">
          <h2 class="text-lg font-semibold text-gray-800">Daftar Produk ({{ record.items?.length || 0 }})</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">No</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Product Name</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Category</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Dev. Date</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Purpose</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Launch Date</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Area / Outlet</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-700">F&B Cost</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-700">Selling Price</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(item, index) in record.items"
                :key="item.id"
                class="border-b hover:bg-amber-50/30 transition-colors"
              >
                <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ item.product_name }}</td>
                <td class="px-4 py-3">{{ item.category || '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(item.development_date) }}</td>
                <td class="px-4 py-3">
                  <span class="px-2 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold">
                    {{ purposeLabel(item.purpose) }}
                  </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(item.proposed_launch_date) }}</td>
                <td class="px-4 py-3">{{ item.proposed_launch_area_outlet || '-' }}</td>
                <td class="px-4 py-3 text-right">{{ formatCurrency(item.fb_cost) }}</td>
                <td class="px-4 py-3 text-right">{{ formatCurrency(item.selling_price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Submit Approval Modal -->
      <div v-if="showApproverModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
          <h3 class="text-lg font-bold text-gray-800 mb-1">Submit untuk Approval</h3>
          <p class="text-sm text-gray-500 mb-4">Pilih approver secara berurutan (level 1 = pertama disetujui)</p>

          <div class="relative mb-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Approver</label>
            <input
              v-model="approverSearch"
              type="text"
              placeholder="Nama, email, atau jabatan..."
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              @input="onApproverSearch"
            />
            <div
              v-if="showApproverDropdown && approverResults.length"
              class="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto"
            >
              <button
                v-for="user in approverResults"
                :key="user.id"
                type="button"
                class="w-full text-left px-4 py-2 hover:bg-blue-50 text-sm border-b last:border-b-0"
                @click="addApprover(user)"
              >
                <div class="font-medium">{{ user.name }}</div>
                <div class="text-xs text-gray-500">{{ user.jabatan || user.email }}</div>
              </button>
            </div>
          </div>

          <div v-if="selectedApprovers.length" class="mb-4 space-y-2">
            <div
              v-for="(approver, index) in selectedApprovers"
              :key="approver.id"
              class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 border border-blue-100"
            >
              <span class="w-7 h-7 rounded-full bg-blue-500 text-white text-xs font-bold flex items-center justify-center">
                {{ index + 1 }}
              </span>
              <div class="flex-1 min-w-0">
                <div class="font-medium text-sm truncate">{{ approver.name }}</div>
                <div class="text-xs text-gray-500 truncate">{{ approver.jabatan || approver.email }}</div>
              </div>
              <button type="button" class="text-red-500 hover:text-red-700" @click="removeApprover(index)">
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 mb-4">Belum ada approver dipilih.</p>

          <div class="flex justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200" @click="closeApproverModal">
              Batal
            </button>
            <button
              type="button"
              :disabled="submitting || selectedApprovers.length === 0"
              class="px-4 py-2 rounded-lg bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50"
              @click="submitForApproval"
            >
              <i v-if="submitting" class="fa fa-spinner fa-spin mr-1"></i>
              Submit
            </button>
          </div>
        </div>
      </div>

      <!-- Approve Modal -->
      <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Approve Report</h3>
          <textarea
            v-model="approvalComments"
            rows="3"
            placeholder="Komentar (opsional)..."
            class="w-full rounded-lg border-gray-300 focus:border-green-500 focus:ring-green-500 mb-4"
          ></textarea>
          <div class="flex justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200" @click="showApproveModal = false">
              Batal
            </button>
            <button
              type="button"
              :disabled="approving"
              class="px-4 py-2 rounded-lg bg-green-500 text-white hover:bg-green-600 disabled:opacity-50"
              @click="processApproval(true)"
            >
              Approve
            </button>
          </div>
        </div>
      </div>

      <!-- Reject Modal -->
      <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Reject Report</h3>
          <textarea
            v-model="rejectComments"
            rows="3"
            required
            placeholder="Alasan reject *"
            class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500 mb-4"
          ></textarea>
          <div class="flex justify-end gap-3">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200" @click="showRejectModal = false">
              Batal
            </button>
            <button
              type="button"
              :disabled="approving || !rejectComments.trim()"
              class="px-4 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600 disabled:opacity-50"
              @click="processApproval(false)"
            >
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
  record: { type: Object, required: true },
  purposeOptions: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  canSubmitApproval: { type: Boolean, default: false },
  canApprove: { type: Boolean, default: false },
});

const showApproverModal = ref(false);
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);
const selectedApprovers = ref([]);
const submitting = ref(false);
const approving = ref(false);
const approvalComments = ref('');
const rejectComments = ref('');

let searchTimer = null;

function formatMonth(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID');
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value || 0));
}

function purposeLabel(value) {
  const found = props.purposeOptions.find((opt) => opt.value === value);
  return found?.label || value;
}

function statusClass(status) {
  const map = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
  };
  return map[status] || 'bg-gray-100 text-gray-700';
}

function flowStatusBg(status) {
  if (status === 'APPROVED') return 'border-green-200 bg-green-50';
  if (status === 'REJECTED') return 'border-red-200 bg-red-50';
  return 'border-blue-200 bg-blue-50';
}

function flowNumberClass(status) {
  if (status === 'APPROVED') return 'bg-green-500 text-white';
  if (status === 'REJECTED') return 'bg-red-500 text-white';
  return 'bg-blue-500 text-white';
}

function flowBadgeClass(status) {
  if (status === 'APPROVED') return 'bg-green-100 text-green-700';
  if (status === 'REJECTED') return 'bg-red-100 text-red-700';
  return 'bg-amber-100 text-amber-700';
}

function onApproverSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadApprovers(approverSearch.value), 300);
}

async function loadApprovers(search = '') {
  try {
    const { data } = await axios.get(route('npd-plan-report.approvers'), { params: { search } });
    approverResults.value = data.users || [];
    showApproverDropdown.value = approverResults.value.length > 0;
  } catch {
    approverResults.value = [];
    showApproverDropdown.value = false;
  }
}

function addApprover(user) {
  if (!selectedApprovers.value.find((a) => a.id === user.id)) {
    selectedApprovers.value.push(user);
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
}

function removeApprover(index) {
  selectedApprovers.value.splice(index, 1);
}

function closeApproverModal() {
  showApproverModal.value = false;
  approverSearch.value = '';
  approverResults.value = [];
  showApproverDropdown.value = false;
}

async function submitForApproval() {
  if (!selectedApprovers.value.length) {
    Swal.fire('Error', 'Pilih minimal satu approver.', 'error');
    return;
  }
  submitting.value = true;
  try {
    const { data } = await axios.post(route('npd-plan-report.submit-approval', props.record.id), {
      approvers: selectedApprovers.value.map((a) => a.id),
    });
    if (data.success) {
      Swal.fire('Berhasil', data.message, 'success');
      closeApproverModal();
      router.reload();
    } else {
      Swal.fire('Error', data.message || 'Gagal submit approval', 'error');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal submit approval', 'error');
  } finally {
    submitting.value = false;
  }
}

async function processApproval(approved) {
  approving.value = true;
  try {
    const { data } = await axios.post(route('npd-plan-report.approve', props.record.id), {
      approved,
      comments: approved ? approvalComments.value : rejectComments.value,
    });
    if (data.success) {
      Swal.fire('Berhasil', data.message, 'success');
      showApproveModal.value = false;
      showRejectModal.value = false;
      router.reload();
    } else {
      Swal.fire('Error', data.message || 'Gagal memproses approval', 'error');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal memproses approval', 'error');
  } finally {
    approving.value = false;
  }
}

function deleteReport() {
  Swal.fire({
    title: 'Hapus Report?',
    text: `Report ${props.record.number} akan dihapus permanen.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('npd-plan-report.destroy', props.record.id));
    }
  });
}
</script>
