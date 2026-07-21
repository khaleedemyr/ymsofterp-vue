<template>
  <div>
    <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
      <div
        class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-indigo-500 animate-pulse"></div>
            <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
              <i class="fa-solid fa-business-time mr-2 text-indigo-500"></i>
              Overtime Submission Approval
            </h3>
          </div>
          <div class="bg-indigo-500 text-white text-xs font-bold px-2 py-1 rounded-full">
            {{ approvalCount }}
          </div>
        </div>

        <div v-if="loading" class="text-center py-4">
          <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-500"></div>
          <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="item in pendingApprovals.slice(0, 3)"
            :key="'ot-submission-' + item.id"
            @click="showDetails(item.id)"
            class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
            :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-indigo-50 hover:bg-indigo-100'"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                  {{ item.number }}
                </div>
                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                  <i class="fa fa-user mr-1 text-indigo-500"></i>
                  {{ item.creator?.nama_lengkap || 'Unknown' }}
                </div>
                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                  <i class="fa fa-calendar mr-1 text-indigo-600"></i>
                  {{ formatDate(item.submission_date) }}
                  · {{ item.employee_count || 0 }} karyawan
                  · {{ item.total_hours || 0 }} jam
                </div>
              </div>
              <div class="text-xs text-indigo-500 font-medium whitespace-nowrap">
                <i class="fa fa-user-check mr-1"></i>{{ item.approver_name || `Level ${item.approval_level || 1}` }}
              </div>
            </div>
          </div>

          <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
            <button type="button" @click="openAllModal" class="text-sm text-indigo-500 hover:text-indigo-700 font-medium">
              Lihat {{ pendingApprovals.length - 3 }} pengajuan lainnya...
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div
        v-if="showDetailModal && selectedSubmission"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]"
        @click="closeDetailModal"
      >
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              <i class="fa-solid fa-business-time mr-2 text-indigo-500"></i>
              Detail Overtime Submission
            </h3>
            <button type="button" @click="closeDetailModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>

          <div v-if="loadingDetail" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
          </div>

          <div v-else class="space-y-5">
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Nomor</label>
                <p class="font-semibold text-gray-900 dark:text-white">{{ selectedSubmission.number }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Tanggal Pengajuan</label>
                <p class="text-gray-900 dark:text-white">{{ formatDate(selectedSubmission.submission_date) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Pembuat</label>
                <p class="text-gray-900 dark:text-white">{{ selectedSubmission.creator?.nama_lengkap || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</label>
                <p class="text-gray-900 dark:text-white">{{ selectedSubmission.status }}</p>
              </div>
              <div v-if="selectedSubmission.notes" class="md:col-span-2">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Catatan</label>
                <p class="text-gray-900 dark:text-white">{{ selectedSubmission.notes }}</p>
              </div>
            </div>

            <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg">
              <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Daftar Karyawan</h4>
              <div class="space-y-2 max-h-48 overflow-y-auto">
                <div
                  v-for="row in selectedSubmission.items || []"
                  :key="row.id"
                  class="flex justify-between gap-3 text-sm bg-white dark:bg-gray-800 rounded px-3 py-2"
                >
                  <div>
                    <div class="font-medium text-gray-900 dark:text-white">{{ row.user?.nama_lengkap || '-' }}</div>
                    <div class="text-xs text-gray-500">{{ formatDate(row.overtime_date) }}</div>
                  </div>
                  <div class="font-semibold text-indigo-600">{{ row.requested_hours }} jam</div>
                </div>
              </div>
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
                    <div class="text-xs font-semibold text-indigo-600">Level {{ flow.approval_level }}</div>
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

            <div v-if="selectedSubmission.can_approve" class="flex justify-end gap-3 pt-4 border-t">
              <button
                type="button"
                @click="showRejectModal"
                :disabled="isRejecting || isApproving"
                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 disabled:opacity-50"
              >
                Tolak
              </button>
              <button
                type="button"
                @click="approveSubmission"
                :disabled="isApproving || isRejecting"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
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

    <!-- All Modal -->
    <Teleport to="body">
      <div v-if="showAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]" @click="closeAllModal">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Semua Overtime Submission Pending</h3>
            <button type="button" @click="closeAllModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>
          <div class="space-y-2">
            <div
              v-for="item in pendingApprovals"
              :key="'all-ot-' + item.id"
              @click="showDetails(item.id)"
              class="p-3 rounded-lg cursor-pointer border hover:border-indigo-400 bg-gray-50 hover:bg-indigo-50"
            >
              <div class="font-semibold text-sm">{{ item.number }}</div>
              <div class="text-xs text-gray-500">
                {{ item.creator?.nama_lengkap || '-' }} · {{ formatDate(item.submission_date) }} · Level {{ item.approval_level || 1 }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

defineProps({
  isNight: { type: Boolean, default: false },
});

const emit = defineEmits(['approved', 'rejected']);

const pendingApprovals = ref([]);
const loading = ref(false);
const showDetailModal = ref(false);
const selectedSubmission = ref(null);
const loadingDetail = ref(false);
const isApproving = ref(false);
const isRejecting = ref(false);
const showAllModal = ref(false);

const approvalCount = computed(() => pendingApprovals.value.length);

const sortedApprovalFlows = computed(() => {
  const flows = selectedSubmission.value?.approval_flows;
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

async function loadPendingApprovals() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/overtime-submissions/pending-approvals?t=${Date.now()}`);
    pendingApprovals.value = response.data?.success ? (response.data.submissions || []) : [];
  } catch (error) {
    console.error('Error loading overtime submission approvals:', error);
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
    const response = await axios.get(`/api/overtime-submissions/${id}/approval-details`);
    if (response.data?.success && response.data.submission) {
      selectedSubmission.value = response.data.submission;
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
  selectedSubmission.value = null;
}

function openAllModal() {
  showAllModal.value = true;
}

function closeAllModal() {
  showAllModal.value = false;
}

async function approveSubmission() {
  if (!selectedSubmission.value || isApproving.value) return;
  isApproving.value = true;
  try {
    const id = selectedSubmission.value.id;
    const response = await axios.post(`/api/overtime-submissions/${id}/approve`, { comments: '' });
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
    title: 'Tolak Pengajuan Lembur',
    input: 'textarea',
    inputLabel: 'Alasan Penolakan',
    inputPlaceholder: 'Masukkan alasan penolakan...',
    showCancelButton: true,
    confirmButtonText: 'Tolak',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#ef4444',
    inputValidator: (value) => (!value ? 'Alasan penolakan harus diisi!' : undefined),
  }).then(async (result) => {
    if (!result.isConfirmed || !selectedSubmission.value) return;
    isRejecting.value = true;
    try {
      const id = selectedSubmission.value.id;
      const response = await axios.post(`/api/overtime-submissions/${id}/reject`, {
        rejection_reason: result.value,
      });
      if (response.data?.success) {
        Swal.fire('Success', 'Pengajuan lembur ditolak', 'success');
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

onMounted(() => {
  loadPendingApprovals();
});

defineExpose({ loadPendingApprovals, refresh: loadPendingApprovals });
</script>
