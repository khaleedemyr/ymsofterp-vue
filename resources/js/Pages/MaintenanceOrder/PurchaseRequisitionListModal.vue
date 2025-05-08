<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-4xl p-6 relative flex flex-col" style="max-height: 80vh;">
        <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <h2 class="text-xl font-bold mb-4 text-center">Purchase Requisition (PR)</h2>
        <button @click="showForm = !showForm" class="mb-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 w-max self-end">
          <i class="fas fa-plus"></i> Buat PR Baru
        </button>
        <PurchaseRequisitionForm v-if="showForm" :task-id="taskId" :on-saved="onPrSaved" :edit-pr="editPr" />
        <div class="flex-1 overflow-y-auto" style="max-height: 50vh;">
          <div v-if="loading" class="text-center py-8">Loading...</div>
          <div v-else-if="prs.length === 0" class="text-center text-gray-400 py-8">Belum ada PR.</div>
          <div v-else>
            <div v-for="pr in prs" :key="pr.id" class="border rounded p-4 mb-4 bg-gray-50">
              <div class="flex justify-between items-start mb-2">
                <div>
                  <div class="font-bold text-blue-700 cursor-pointer hover:underline" @click="showDetailPR(pr)">{{ pr.pr_number }}</div>
                  <div class="text-xs text-gray-500">Status: {{ pr.status }} | Total: {{ formatCurrency(pr.total_amount) }}</div>
                  <div class="text-xs text-gray-400">{{ formatDate(pr.created_at) }}</div>
                </div>
                <div class="flex gap-2">
                  <button @click="showDetailPR(pr)" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">
                    Detail
                  </button>
                  <button @click="previewBA(pr)" 
                          class="text-xs px-2 py-1 bg-yellow-100 hover:bg-yellow-200 rounded">
                    Preview BA
                  </button>
                  <button @click="previewPR(pr)" 
                          class="text-xs px-2 py-1 bg-yellow-100 hover:bg-yellow-200 rounded">
                    Preview PR
                  </button>
                  <button @click="editPR(pr)" class="px-2 py-1 text-xs bg-yellow-200 rounded hover:bg-yellow-300">Edit</button>
                  <button @click="deletePR(pr)" class="px-2 py-1 text-xs bg-red-200 text-red-700 rounded hover:bg-red-300">Hapus</button>
                </div>
              </div>
              <div class="text-sm mb-2">{{ pr.description }}</div>
              <div v-if="pr.items && pr.items.length" class="mt-2">
                <div class="font-semibold text-xs mb-1">Items:</div>
                <ul class="text-xs ml-4 list-disc">
                  <li v-for="item in pr.items" :key="item.id">
                    {{ item.item_name }} ({{ item.quantity }} x {{ formatCurrency(item.price) }}) = {{ formatCurrency(item.subtotal) }}
                  </li>
                </ul>
              </div>
              <!-- Approval Status Section -->
              <div class="mt-3 border-t pt-2">
                <div class="text-xs font-semibold mb-2">Approval Status:</div>
                <div class="grid grid-cols-1 gap-2">
                  <!-- Chief Engineering -->
                  <div v-if="pr.chief_engineering_approval" class="text-xs">
                    <span class="font-medium">CHIEF ENGINEERING:</span>
                    <span :class="{
                      'text-green-600': pr.chief_engineering_approval?.toUpperCase() === 'APPROVED',
                      'text-red-600': pr.chief_engineering_approval?.toUpperCase() === 'REJECTED',
                      'text-gray-600': pr.chief_engineering_approval?.toUpperCase() === 'PENDING'
                    }">
                      {{ pr.chief_engineering_approval }}
                    </span>
                    <span v-if="pr.chief_engineering_approval_date" class="text-gray-400 ml-1">
                      ({{ formatDate(pr.chief_engineering_approval_date) }})
                    </span>
                    <span v-if="pr.chief_engineering_approval_by_user" class="text-gray-600 ml-1">
                      - by {{ pr.chief_engineering_approval_by_user.nama_lengkap }}
                    </span>
                    <div v-if="pr.chief_engineering_approval_notes" class="text-gray-500 ml-2 mt-0.5">
                      {{ pr.chief_engineering_approval_notes }}
                    </div>
                  </div>

                  <!-- COO -->
                  <div v-if="pr.coo_approval" class="text-xs">
                    <span class="font-medium">COO:</span>
                    <span :class="{
                      'text-green-600': pr.coo_approval?.toUpperCase() === 'APPROVED',
                      'text-red-600': pr.coo_approval?.toUpperCase() === 'REJECTED',
                      'text-gray-600': pr.coo_approval?.toUpperCase() === 'PENDING'
                    }">
                      {{ pr.coo_approval }}
                    </span>
                    <span v-if="pr.coo_approval_date" class="text-gray-400 ml-1">
                      ({{ formatDate(pr.coo_approval_date) }})
                    </span>
                    <span v-if="pr.coo_approval_by_user" class="text-gray-600 ml-1">
                      - by {{ pr.coo_approval_by_user.nama_lengkap }}
                    </span>
                    <div v-if="pr.coo_approval_notes" class="text-gray-500 ml-2 mt-0.5">
                      {{ pr.coo_approval_notes }}
                    </div>
                  </div>

                  <!-- CEO -->
                  <div v-if="pr.ceo_approval && pr.total_amount >= 5000000" class="text-xs">
                    <span class="font-medium">CEO:</span>
                    <span :class="{
                      'text-green-600': pr.ceo_approval?.toUpperCase() === 'APPROVED',
                      'text-red-600': pr.ceo_approval?.toUpperCase() === 'REJECTED',
                      'text-gray-600': pr.ceo_approval?.toUpperCase() === 'PENDING'
                    }">
                      {{ pr.ceo_approval }}
                    </span>
                    <span v-if="pr.ceo_approval_date" class="text-gray-400 ml-1">
                      ({{ formatDate(pr.ceo_approval_date) }})
                    </span>
                    <span v-if="pr.ceo_approval_by_user" class="text-gray-600 ml-1">
                      - by {{ pr.ceo_approval_by_user.nama_lengkap }}
                    </span>
                    <div v-if="pr.ceo_approval_notes" class="text-gray-500 ml-2 mt-0.5">
                      {{ pr.ceo_approval_notes }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </teleport>
  <teleport to="body">
    <div v-if="showDetail" class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative">
        <button @click="showDetail = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <h2 class="text-xl font-bold mb-4 text-center">Detail PR</h2>
        <div v-if="detailPr">
          <div class="mb-2 font-bold text-blue-700">{{ detailPr.pr_number }}</div>
          <div class="mb-2 text-xs text-gray-500">Status: {{ detailPr.status }} | Total: {{ detailPr.total_amount }}</div>
          <div class="mb-2 text-xs text-gray-400">{{ formatDate(detailPr.created_at) }}</div>
          <div class="mb-2">{{ detailPr.description }}</div>
          <div v-if="detailPr.items && detailPr.items.length">
            <div class="font-semibold text-xs mb-1">Items:</div>
            <ul class="text-xs ml-4 list-disc">
              <li v-for="item in detailPr.items" :key="item.id">
                {{ item.item_name }} ({{ item.quantity }} x {{ item.price }}) = {{ item.subtotal }}
              </li>
            </ul>
          </div>

          <!-- Detail Modal Approval Section -->
          <div class="mt-4">
            <!-- Chief Engineering Approval -->
            <div class="mb-4">
                <h3 class="text-lg font-semibold mb-2">Chief Engineering Approval</h3>
                <div class="flex items-center space-x-4">
                    <div class="flex-1">
                        <div v-if="detailPr.chief_engineering_approval?.toLowerCase() === 'approved'" class="text-green-600">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span>Approved</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ formatDate(detailPr.chief_engineering_approval_date) }}
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ detailPr.chief_engineering_approval_by_name }}
                            </div>
                            <div v-if="detailPr.chief_engineering_approval_notes" class="text-sm text-gray-600 mt-1">
                                Notes: {{ detailPr.chief_engineering_approval_notes }}
                            </div>
                        </div>
                        <div v-else-if="detailPr.chief_engineering_approval?.toLowerCase() === 'rejected'" class="text-red-600">
                            <div class="flex items-center">
                                <i class="fas fa-times-circle mr-2"></i>
                                <span>Rejected</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ formatDate(detailPr.chief_engineering_approval_date) }}
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ detailPr.chief_engineering_approval_by_name }}
                            </div>
                            <div v-if="detailPr.chief_engineering_approval_notes" class="text-sm text-gray-600 mt-1">
                                Notes: {{ detailPr.chief_engineering_approval_notes }}
                            </div>
                        </div>
                        <div v-else class="text-yellow-600">
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-2"></i>
                                <span>{{ detailPr.chief_engineering_approval || 'Pending' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COO Approval -->
            <div class="mb-4">
                <h3 class="text-lg font-semibold mb-2">COO Approval</h3>
                <div class="flex items-center space-x-4">
                    <div class="flex-1">
                        <div v-if="detailPr.coo_approval?.toLowerCase() === 'approved'" class="text-green-600">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span>Approved</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ formatDate(detailPr.coo_approval_date) }}
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ detailPr.coo_approval_by_name }}
                            </div>
                            <div v-if="detailPr.coo_approval_notes" class="text-sm text-gray-600 mt-1">
                                Notes: {{ detailPr.coo_approval_notes }}
                            </div>
                        </div>
                        <div v-else-if="detailPr.coo_approval?.toLowerCase() === 'rejected'" class="text-red-600">
                            <div class="flex items-center">
                                <i class="fas fa-times-circle mr-2"></i>
                                <span>Rejected</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ formatDate(detailPr.coo_approval_date) }}
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ detailPr.coo_approval_by_name }}
                            </div>
                            <div v-if="detailPr.coo_approval_notes" class="text-sm text-gray-600 mt-1">
                                Notes: {{ detailPr.coo_approval_notes }}
                            </div>
                        </div>
                        <div v-else class="text-yellow-600">
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-2"></i>
                                <span>{{ detailPr.coo_approval || 'Pending' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CEO Approval (only for PR >= 5 million) -->
            <div v-if="detailPr.total_amount >= 5000000" class="mb-4">
                <h3 class="text-lg font-semibold mb-2">CEO Approval</h3>
                <div class="flex items-center space-x-4">
                    <div class="flex-1">
                        <div v-if="detailPr.ceo_approval?.toLowerCase() === 'approved'" class="text-green-600">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span>Approved</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ formatDate(detailPr.ceo_approval_date) }}
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ detailPr.ceo_approval_by_name }}
                            </div>
                            <div v-if="detailPr.ceo_approval_notes" class="text-sm text-gray-600 mt-1">
                                Notes: {{ detailPr.ceo_approval_notes }}
                            </div>
                        </div>
                        <div v-else-if="detailPr.ceo_approval?.toLowerCase() === 'rejected'" class="text-red-600">
                            <div class="flex items-center">
                                <i class="fas fa-times-circle mr-2"></i>
                                <span>Rejected</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ formatDate(detailPr.ceo_approval_date) }}
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ detailPr.ceo_approval_by_name }}
                            </div>
                            <div v-if="detailPr.ceo_approval_notes" class="text-sm text-gray-600 mt-1">
                                Notes: {{ detailPr.ceo_approval_notes }}
                            </div>
                        </div>
                        <div v-else class="text-yellow-600">
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-2"></i>
                                <span>{{ detailPr.ceo_approval || 'Pending' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
          </div>

          <!-- Approval Buttons -->
          <div class="mt-4 flex flex-wrap gap-2">
            <!-- Chief Engineering -->
            <div v-if="canShowApprovalButton('chief_engineering', detailPr)" class="flex gap-2">
              <button 
                @click="approvePR('chief_engineering', 'approved')"
                class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-xs"
              >
                Approve CHIEF ENGINEERING
              </button>
              <button 
                @click="rejectPR('chief_engineering')"
                class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-xs"
              >
                Reject
              </button>
            </div>

            <!-- COO -->
            <div v-if="canShowApprovalButton('coo', detailPr)" class="flex gap-2">
              <button 
                @click="approvePR('coo', 'approved')"
                class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-xs"
              >
                Approve COO
              </button>
              <button 
                @click="rejectPR('coo')"
                class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-xs"
              >
                Reject
              </button>
            </div>

            <!-- CEO -->
            <div v-if="canShowApprovalButton('ceo', detailPr)" class="flex gap-2">
              <button 
                @click="approvePR('ceo', 'approved')"
                class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-xs"
              >
                Approve CEO
              </button>
              <button 
                @click="rejectPR('ceo')"
                class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-xs"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import axios from 'axios';
import PurchaseRequisitionForm from './pr/PurchaseRequisitionForm.vue';
import Swal from 'sweetalert2';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  show: Boolean,
  taskId: [String, Number],
});

const prs = ref([]);
const loading = ref(false);
const showForm = ref(false);
const editPr = ref(null);
const showDetail = ref(false);
const detailPr = ref(null);

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
  });
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
};

async function fetchPRs() {
  if (!props.taskId) return;
  loading.value = true;
  try {
    const res = await axios.get(`/api/maintenance-tasks/${props.taskId}/purchase-requisitions`);
    console.log('Fetched PRs:', res.data);
    prs.value = res.data;
  } catch (e) {
    console.error('Error fetching PRs:', e);
    prs.value = [];
  } finally {
    loading.value = false;
  }
}

async function deletePR(pr) {
  const confirm = await Swal.fire({
    title: 'Hapus PR?',
    text: `Yakin ingin menghapus PR ${pr.pr_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
  });
  if (confirm.isConfirmed) {
    await axios.delete(`/api/maintenance-purchase-requisitions/${pr.id}`);
    fetchPRs();
    Swal.fire('Berhasil', 'PR berhasil dihapus', 'success');
  }
}

function editPR(pr) {
  editPr.value = pr;
  showForm.value = true;
}

async function showDetailPR(pr) {
  console.log('Opening PR detail:', pr);
  detailPr.value = pr;
  showDetail.value = true;
}

function onPrSaved() {
  showForm.value = false;
  editPr.value = null;
  fetchPRs();
}

function canApprove(approvalType, pr) {
  const user = usePage().props.auth.user;
  console.log('Raw user data:', user);
  
  // Extract values from proxy object
  const userRole = user?.id_role;
  const userJabatan = user?.id_jabatan;
  const userStatus = user?.status;
  
  console.log('Extracted values:', {
    userRole,
    userJabatan,
    userStatus
  });

  // Validasi status user harus aktif
  if (userStatus !== 'A') {
    console.log('User not active');
    return false;
  }

  // Superadmin (id_role 5af56935b011a) dan sekretaris (id_jabatan 217) bisa approve semua level
  const isSuperAdmin = userRole === '5af56935b011a';
  const isSecretary = userJabatan === 217;
  
  console.log('Role checks:', {
    isSuperAdmin,
    isSecretary,
    userRole,
    expectedRole: '5af56935b011a',
    roleMatch: userRole === '5af56935b011a'
  });

  if (isSuperAdmin || isSecretary) {
    console.log('User is superadmin or secretary');
    return true;
  }

  // Validasi berdasarkan level approval
  let result = false;
  switch (approvalType) {
    case 'chief_engineering':
      result = userJabatan === 165 || userJabatan === 262;
      break;
    case 'coo':
      result = userJabatan === 151;
      break;
    case 'ceo':
      // CEO hanya untuk PR >= 5 juta
      if (!pr?.total_amount || pr.total_amount < 5000000) {
        result = false;
      } else {
        result = userJabatan === 149;
      }
      break;
    default:
      result = false;
  }

  console.log(`Approval check for ${approvalType}:`, result);
  return result;
}

function canShowApprovalButton(type, pr) {
  console.log(`Checking button visibility for ${type}`, {
    pr_data: {
      chief_eng: pr.chief_engineering_approval,
      coo: pr.coo_approval,
      ceo: pr.ceo_approval
    }
  });

  if (!pr || !canApprove(type, pr)) {
    console.log(`${type}: No PR data or cannot approve`);
    return false;
  }

  // Helper function untuk cek status approval
  const isApproved = (status) => {
    return status?.toLowerCase() === 'approved';
  };

  const isPending = (status) => {
    return !status || status.toLowerCase() === 'pending';
  };

  let result = false;
  switch (type) {
    case 'chief_engineering':
      result = isPending(pr.chief_engineering_approval);
      break;
    case 'coo':
      // COO bisa approve jika chief engineering sudah approve
      result = isApproved(pr.chief_engineering_approval) && isPending(pr.coo_approval);
      break;
    case 'ceo':
      // CEO bisa approve jika PR >= 5jt dan COO sudah approve
      result = pr.total_amount >= 5000000 && 
               isApproved(pr.coo_approval) && 
               isPending(pr.ceo_approval);
      break;
    default:
      result = false;
  }

  console.log(`Button visibility for ${type}:`, result);
  return result;
}

async function updatePRStatus(prId, newStatus) {
  try {
    await axios.post(`/api/maintenance-purchase-requisitions/${prId}/update-status`, {
      status: newStatus
    });
  } catch (error) {
    console.error('Failed to update PR status:', error);
  }
}

async function checkAndUpdatePRStatus(pr) {
  // Jika ada yang rejected, langsung set status REJECTED
  if (pr.chief_engineering_approval?.toLowerCase() === 'rejected' ||
      pr.coo_approval?.toLowerCase() === 'rejected' ||
      (pr.total_amount >= 5000000 && pr.ceo_approval?.toLowerCase() === 'rejected')) {
    await updatePRStatus(pr.id, 'REJECTED');
    return;
  }

  // Cek untuk status APPROVED
  const chiefApproved = pr.chief_engineering_approval?.toLowerCase() === 'approved';
  const cooApproved = pr.coo_approval?.toLowerCase() === 'approved';
  const ceoApproved = pr.ceo_approval?.toLowerCase() === 'approved';

  // Untuk PR < 5jt
  if (pr.total_amount < 5000000) {
    if (chiefApproved && cooApproved) {
      await updatePRStatus(pr.id, 'APPROVED');
    }
  } 
  // Untuk PR >= 5jt
  else {
    if (chiefApproved && cooApproved && ceoApproved) {
      await updatePRStatus(pr.id, 'APPROVED');
    }
  }
}

async function approvePR(approvalType, status) {
  const { value: notes } = await Swal.fire({
    title: 'Approve PR',
    input: 'textarea',
    inputLabel: 'Approval Notes',
    inputPlaceholder: 'Enter approval notes (optional)...',
    showCancelButton: true,
    confirmButtonText: 'Approve',
    cancelButtonText: 'Cancel'
  });

  if (notes !== undefined) {
    try {
      await axios.post(`/api/maintenance-purchase-requisitions/${detailPr.value.id}/approve`, {
        approval_type: approvalType,
        status: status,
        notes: notes
      });

      // Jika COO approve dan PR < 5jt, otomatis approve CEO
      if (approvalType === 'coo' && status === 'approved' && detailPr.value.total_amount < 5000000) {
        await axios.post(`/api/maintenance-purchase-requisitions/${detailPr.value.id}/approve`, {
          approval_type: 'ceo',
          status: 'approved',
          notes: 'Auto approved - PR under 5 million'
        });
      }

      await refreshDetailPr();
      // Check dan update status PR setelah approval
      await checkAndUpdatePRStatus(detailPr.value);
      Swal.fire('Success', 'PR approved successfully', 'success');
    } catch (error) {
      Swal.fire('Error', error.response?.data?.error || 'Failed to approve PR', 'error');
    }
  }
}

async function rejectPR(approvalType) {
  const { value: notes } = await Swal.fire({
    title: 'Reject PR',
    input: 'textarea',
    inputLabel: 'Rejection Notes',
    inputPlaceholder: 'Enter rejection notes (required)...',
    inputValidator: (value) => {
      if (!value) {
        return 'Notes are required for rejection!';
      }
    },
    showCancelButton: true,
    confirmButtonText: 'Reject',
    cancelButtonText: 'Cancel'
  });

  if (notes) {
    try {
      await axios.post(`/api/maintenance-purchase-requisitions/${detailPr.value.id}/approve`, {
        approval_type: approvalType,
        status: 'rejected',
        notes: notes
      });

      await refreshDetailPr();
      // Check dan update status PR setelah rejection
      await checkAndUpdatePRStatus(detailPr.value);
      Swal.fire('Success', 'PR rejected successfully', 'success');
    } catch (error) {
      Swal.fire('Error', error.response?.data?.error || 'Failed to reject PR', 'error');
    }
  }
}

async function refreshDetailPr() {
  try {
    // Ambil ulang data PR dari backend
    const res = await axios.get(`/api/maintenance-tasks/${detailPr.value.task_id}/purchase-requisitions`);
    console.log('Refreshing PR details, response:', res.data);
    // Update data PR yang sedang dibuka di modal
    const updatedPr = res.data.find(pr => pr.id === detailPr.value.id);
    console.log('Updated PR:', updatedPr);
    if (updatedPr) {
      detailPr.value = updatedPr;
      // Update juga di list PR
      const index = prs.value.findIndex(pr => pr.id === updatedPr.id);
      if (index !== -1) {
        prs.value[index] = updatedPr;
      }
    }
  } catch (error) {
    console.error('Failed to refresh PR details:', error);
  }
}

watch(() => props.show, (val) => {
  if (val) fetchPRs();
});

const isSuperAdmin = computed(() => {
  const user = usePage().props.auth.user;
  return (user.id_role === '5af56935b011a' || user.id_jabatan === 217) && user.status === 'A';
});

const previewBA = (pr) => {
  window.open(`/maintenance-ba/${pr.id}/preview`, '_blank');
};

const previewPR = (pr) => {
  window.open(`/maintenance-pr/${pr.id}/preview`, '_blank');
};
</script>

<style scoped>
.bg-blue-50 { background-color: #eff6ff; }
</style>

<style>
.swal2-container {
  z-index: 999999 !important;
}
</style> 