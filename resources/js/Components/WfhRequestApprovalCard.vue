<template>
  <div>
    <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
      <div
        class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-teal-500 animate-pulse"></div>
            <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
              <i class="fa-solid fa-house-laptop mr-2 text-teal-500"></i>
              WFH Approval
            </h3>
          </div>
          <div class="bg-teal-500 text-white text-xs font-bold px-2 py-1 rounded-full">
            {{ approvalCount }}
          </div>
        </div>

        <div v-if="loading" class="text-center py-4">
          <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-teal-500"></div>
          <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="item in pendingApprovals.slice(0, 3)"
            :key="'wfh-' + item.id"
            class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
            :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-teal-50 hover:bg-teal-100'"
            @click="showDetails(item.id)"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                  {{ item.number }}
                </div>
                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                  <i class="fa fa-user mr-1 text-teal-500"></i>
                  {{ item.user?.nama_lengkap || item.creator?.nama_lengkap || 'Unknown' }}
                </div>
                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                  <i class="fa fa-calendar mr-1 text-teal-600"></i>
                  {{ formatDate(item.wfh_date) }}
                  · {{ item.shift_name || '-' }}
                </div>
              </div>
              <div class="text-xs text-teal-500 font-medium whitespace-nowrap">
                <i class="fa fa-user-check mr-1"></i>{{ item.approver_name || `Level ${item.approval_level || 1}` }}
              </div>
            </div>
          </div>

          <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
            <button type="button" class="text-sm text-teal-500 hover:text-teal-700 font-medium" @click="openAllModal">
              Lihat {{ pendingApprovals.length - 3 }} pengajuan lainnya...
            </button>
          </div>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="showDetailModal && selectedRequest"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]"
        @click="closeDetailModal"
      >
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              <i class="fa-solid fa-house-laptop mr-2 text-teal-500"></i>
              Detail Pengajuan WFH
            </h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeDetailModal">
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>

          <div v-if="loadingDetail" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500"></div>
          </div>

          <div v-else class="space-y-5">
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Nomor</label>
                <p class="font-semibold text-gray-900 dark:text-white">{{ selectedRequest.number }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Tanggal WFH</label>
                <p class="text-gray-900 dark:text-white">{{ formatDate(selectedRequest.wfh_date) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Karyawan</label>
                <p class="text-gray-900 dark:text-white">{{ selectedRequest.user?.nama_lengkap || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Shift</label>
                <p class="text-gray-900 dark:text-white">
                  {{ selectedRequest.shift_name || '-' }}
                  ({{ formatTime(selectedRequest.time_start) }} – {{ formatTime(selectedRequest.time_end) }})
                </p>
              </div>
              <div class="md:col-span-2">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Alasan</label>
                <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ selectedRequest.reason }}</p>
              </div>
            </div>

            <div class="bg-teal-50 dark:bg-teal-900/20 p-4 rounded-lg">
              <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">List Pekerjaan</h4>
              <ol class="list-decimal list-inside space-y-1 text-sm text-gray-800 dark:text-gray-200">
                <li v-for="task in selectedRequest.tasks || []" :key="task.id">{{ task.description }}</li>
              </ol>
            </div>

            <div v-if="sortedApprovalFlows.length > 0" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
              <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Approval Flow</h4>
              <div class="space-y-2">
                <div
                  v-for="flow in sortedApprovalFlows"
                  :key="flow.id"
                  class="flex items-center justify-between p-3 rounded-lg"
                  :class="flow.status === 'APPROVED' ? 'bg-green-100' : flow.status === 'REJECTED' ? 'bg-red-100' : 'bg-gray-100'"
                >
                  <div>
                    <div class="text-xs font-semibold text-teal-600">Level {{ flow.approval_level }}</div>
                    <div class="font-medium text-gray-900">{{ flow.approver?.nama_lengkap || '-' }}</div>
                  </div>
                  <span
                    class="px-2 py-1 rounded text-xs font-medium text-white"
                    :class="flow.status === 'APPROVED' ? 'bg-green-500' : flow.status === 'REJECTED' ? 'bg-red-500' : 'bg-amber-500'"
                  >
                    {{ flow.status }}
                  </span>
                </div>
              </div>
            </div>

            <div v-if="selectedRequest.can_approve" class="flex justify-end gap-3 pt-4 border-t">
              <button
                type="button"
                :disabled="isRejecting || isApproving"
                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 disabled:opacity-50"
                @click="showRejectModal"
              >
                Tolak
              </button>
              <button
                type="button"
                :disabled="isApproving || isRejecting"
                class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 disabled:opacity-50"
                @click="approveRequest"
              >
                {{ isApproving ? 'Memproses...' : 'Setujui' }}
              </button>
            </div>
            <div v-else class="pt-4 border-t text-sm text-amber-600">
              <i class="fa fa-clock mr-1"></i>
              Menunggu persetujuan level sebelumnya.
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]" @click="closeAllModal">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Semua WFH Pending</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" @click="closeAllModal">
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>
          <div class="space-y-2">
            <div
              v-for="item in pendingApprovals"
              :key="'all-wfh-' + item.id"
              class="p-3 rounded-lg cursor-pointer border hover:border-teal-400 bg-gray-50 hover:bg-teal-50"
              @click="showDetails(item.id)"
            >
              <div class="font-semibold text-sm">{{ item.number }}</div>
              <div class="text-xs text-gray-500">
                {{ item.user?.nama_lengkap || '-' }} · {{ formatDate(item.wfh_date) }} · Level {{ item.approval_level || 1 }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

defineProps({
  isNight: { type: Boolean, default: false },
});

const emit = defineEmits(['approved', 'rejected']);

const pendingApprovals = ref([]);
const loading = ref(false);
const showDetailModal = ref(false);
const selectedRequest = ref(null);
const loadingDetail = ref(false);
const isApproving = ref(false);
const isRejecting = ref(false);
const showAllModal = ref(false);

const approvalCount = computed(() => pendingApprovals.value.length);

const sortedApprovalFlows = computed(() => {
  const flows = selectedRequest.value?.approval_flows;
  if (!Array.isArray(flows)) return [];
  return [...flows].sort((a, b) => (Number(a.approval_level) || 0) - (Number(b.approval_level) || 0));
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatTime(value) {
  if (!value) return '-';
  return String(value).substring(0, 5);
}

async function loadPendingApprovals() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/wfh-requests/pending-approvals?t=${Date.now()}`);
    pendingApprovals.value = response.data?.success ? (response.data.requests || []) : [];
  } catch (error) {
    console.error('Error loading WFH approvals:', error);
    pendingApprovals.value = [];
  } finally {
    loading.value = false;
  }
}

async function showDetails(id) {
  try {
    if (showAllModal.value) showAllModal.value = false;
    loadingDetail.value = true;
    showDetailModal.value = true;
    const response = await axios.get(`/api/wfh-requests/${id}/approval-details`);
    if (response.data?.success && response.data.request) {
      selectedRequest.value = response.data.request;
    } else {
      Swal.fire('Error', response.data?.message || 'Gagal memuat detail', 'error');
      closeDetailModal();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal memuat detail', 'error');
    closeDetailModal();
  } finally {
    loadingDetail.value = false;
  }
}

function closeDetailModal() {
  showDetailModal.value = false;
  selectedRequest.value = null;
}

function openAllModal() {
  showAllModal.value = true;
}

function closeAllModal() {
  showAllModal.value = false;
}

async function approveRequest() {
  if (!selectedRequest.value || isApproving.value) return;
  isApproving.value = true;
  try {
    const id = selectedRequest.value.id;
    const response = await axios.post(`/api/wfh-requests/${id}/approve`, { comments: '' });
    if (response.data?.success) {
      Swal.fire('Success', response.data.message || 'Berhasil disetujui', 'success');
      closeDetailModal();
      await loadPendingApprovals();
      emit('approved', id);
    } else {
      Swal.fire('Error', response.data?.message || 'Gagal approve', 'error');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal approve', 'error');
  } finally {
    isApproving.value = false;
  }
}

function showRejectModal() {
  Swal.fire({
    title: 'Tolak Pengajuan WFH',
    input: 'textarea',
    inputLabel: 'Alasan Penolakan',
    inputPlaceholder: 'Masukkan alasan penolakan...',
    showCancelButton: true,
    confirmButtonText: 'Tolak',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#ef4444',
  }).then(async (result) => {
    if (!result.isConfirmed || !selectedRequest.value) return;
    isRejecting.value = true;
    try {
      const id = selectedRequest.value.id;
      const response = await axios.post(`/api/wfh-requests/${id}/reject`, {
        rejection_reason: result.value || '',
      });
      if (response.data?.success) {
        Swal.fire('Success', response.data.message || 'Pengajuan ditolak', 'success');
        closeDetailModal();
        await loadPendingApprovals();
        emit('rejected', id);
      } else {
        Swal.fire('Error', response.data?.message || 'Gagal reject', 'error');
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal reject', 'error');
    } finally {
      isRejecting.value = false;
    }
  });
}

onMounted(loadPendingApprovals);
</script>
