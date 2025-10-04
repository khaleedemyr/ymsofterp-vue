<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import PrintPreviewModal from './PrintPreviewModal.vue';

const props = defineProps({
  po: Object,
  user: Object,
});

const showApprovalModal = ref(false);
const showRejectModal = ref(false);
const showApproverModal = ref(false);
const approvalNote = ref('');
const rejectionReason = ref('');
const approvers = ref([]);
const selectedApprovers = ref([]);
const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);
const showPreview = ref(false);

const approvalForm = useForm({
  approved: true,
  note: ''
});

const rejectionForm = useForm({
  approved: false,
  note: ''
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID');
};

const getStatusColor = (status) => {
  const colors = {
    'draft': 'bg-gray-100 text-gray-800',
    'approved': 'bg-green-100 text-green-800',
    'received': 'bg-blue-100 text-blue-800',
    'rejected': 'bg-red-100 text-red-800',
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
};

const canApprove = computed(() => {
  return props.po.status === 'draft' && 
         (props.user.id_jabatan === 167 || props.user.id_role === 1); // Purchasing Manager or Admin
});

const canApproveGM = computed(() => {
  return props.po.status === 'draft' && 
         props.po.purchasing_manager_approved_at && 
         (props.user.id_jabatan === 152 || props.user.id_jabatan === 381); // GM Finance
});

const canEdit = computed(() => {
  return ['draft', 'approved'].includes(props.po.status);
});

const canDelete = computed(() => {
  return ['draft', 'approved'].includes(props.po.status);
});

// New approval flow computed properties
const canSubmitForApproval = computed(() => {
  return props.po.status === 'draft';
});

const canApproveFlow = computed(() => {
  // Check if current user has pending approval for this PO
  return props.po.approval_flows && props.po.approval_flows.some(flow => 
    flow.approver_id === props.user.id && flow.status === 'PENDING'
  );
});

const currentApprovalFlow = computed(() => {
  if (!props.po.approval_flows) return null;
  return props.po.approval_flows.find(flow => 
    flow.approver_id === props.user.id && flow.status === 'PENDING'
  );
});

const handleApproval = async (isApproved) => {
  try {
    // Use new approval flow if available, otherwise use old method
    if (canApproveFlow.value) {
      await handleApprovalFlow(isApproved);
    } else {
      const form = isApproved ? approvalForm : rejectionForm;
      form.note = isApproved ? approvalNote.value : rejectionReason.value;
      
      const endpoint = isApproved ? 'approve-pm' : 'approve-pm';
      const response = await axios.post(`/po-ops/${props.po.id}/${endpoint}`, form.data());
      
      if (response.data.success) {
        Swal.fire('Success', response.data.message, 'success');
        router.reload();
      } else {
        Swal.fire('Error', response.data.message || 'Failed to process approval', 'error');
      }
    }
  } catch (error) {
    console.error('Error processing approval:', error);
    Swal.fire('Error', error.response?.data?.message || 'Failed to process approval', 'error');
  }
};

const handleGMApproval = async (isApproved) => {
  try {
    const form = isApproved ? approvalForm : rejectionForm;
    form.note = isApproved ? approvalNote.value : rejectionReason.value;
    
    const endpoint = isApproved ? 'approve-gm' : 'approve-gm';
    const response = await axios.post(`/po-ops/${props.po.id}/${endpoint}`, form.data());
    
    if (response.data.success) {
      Swal.fire('Success', response.data.message, 'success');
      router.reload();
    } else {
      Swal.fire('Error', response.data.message || 'Failed to process approval', 'error');
    }
  } catch (error) {
    console.error('Error processing GM approval:', error);
    Swal.fire('Error', error.response?.data?.message || 'Failed to process approval', 'error');
  }
};

// New approval flow methods
const fetchApprovers = async () => {
  try {
    const response = await axios.get('/po-ops/approvers');
    if (response.data.success) {
      approvers.value = response.data.approvers;
    }
  } catch (error) {
    console.error('Error fetching approvers:', error);
    Swal.fire('Error', 'Failed to fetch approvers', 'error');
  }
};

const loadApprovers = async (search = '') => {
  try {
    const response = await axios.get('/po-ops/approvers', {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    });
    
    if (response.data.success) {
      approverResults.value = response.data.users;
      showApproverDropdown.value = true;
    }
  } catch (error) {
    console.error('Failed to load approvers:', error);
    approverResults.value = [];
  }
};

const addApprover = (user) => {
  // Check if user already exists
  if (!selectedApprovers.value.find(approver => approver.id === user.id)) {
    selectedApprovers.value.push(user);
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
};

const removeApprover = (index) => {
  selectedApprovers.value.splice(index, 1);
};

const reorderApprover = (fromIndex, toIndex) => {
  const approver = selectedApprovers.value.splice(fromIndex, 1)[0];
  selectedApprovers.value.splice(toIndex, 0, approver);
};

const submitForApproval = async () => {
  if (selectedApprovers.value.length === 0) {
    Swal.fire('Error', 'Please select at least one approver', 'error');
    return;
  }

  try {
    const response = await axios.post(`/po-ops/${props.po.id}/submit-approval`, {
      approvers: selectedApprovers.value.map(approver => approver.id)
    });

    if (response.data.success) {
      Swal.fire('Success', response.data.message, 'success');
      router.reload();
    } else {
      Swal.fire('Error', response.data.message || 'Failed to submit for approval', 'error');
    }
  } catch (error) {
    console.error('Error submitting for approval:', error);
    Swal.fire('Error', error.response?.data?.message || 'Failed to submit for approval', 'error');
  }
};

const handleApprovalFlow = async (isApproved) => {
  try {
    const form = isApproved ? approvalForm : rejectionForm;
    form.comments = isApproved ? approvalNote.value : rejectionReason.value;
    
    const response = await axios.post(`/po-ops/${props.po.id}/approve`, form.data());
    
    if (response.data.success) {
      Swal.fire('Success', response.data.message, 'success');
      router.reload();
    } else {
      Swal.fire('Error', response.data.message || 'Failed to process approval', 'error');
    }
  } catch (error) {
    console.error('Error processing approval:', error);
    Swal.fire('Error', error.response?.data?.message || 'Failed to process approval', 'error');
  }
};

const deletePO = () => {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    text: `Apakah Anda yakin ingin menghapus PO ${props.po.number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/po-ops/${props.po.id}`, {
        onSuccess: () => {
          Swal.fire('Berhasil!', 'PO berhasil dihapus.', 'success');
          router.visit('/po-ops');
        },
        onError: () => {
          Swal.fire('Error!', 'Gagal menghapus PO.', 'error');
        }
      });
    }
  });
};

// Watch approver search
watch(approverSearch, (newSearch) => {
  if (newSearch.length >= 2) {
    loadApprovers(newSearch);
  } else {
    showApproverDropdown.value = false;
    approverResults.value = [];
  }
});

const markPrinted = async () => {
  try {
    const response = await axios.post(`/po-ops/${props.po.id}/mark-printed`);
    if (response.data.success) {
      Swal.fire('Success', 'PO marked as printed', 'success');
      router.reload();
    }
  } catch (error) {
    console.error('Error marking as printed:', error);
    Swal.fire('Error', 'Failed to mark as printed', 'error');
  }
};
</script>

<template>
  <AppLayout title="Purchase Order Ops Details">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-shopping-cart text-blue-500"></i> 
          Purchase Order: {{ po.number }}
        </h1>
        <div class="flex space-x-2">
          <Link
            v-if="canEdit"
            :href="`/po-ops/${po.id}/edit`"
            class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-edit mr-2"></i>
            Edit
          </Link>
          <button 
            v-if="po.status === 'approved'"
            @click="showPreview = true" 
            class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mr-2"
            title="Print PO"
          >
            <i class="fas fa-print mr-2"></i>
            Print PO
          </button>
          <Link
            :href="'/po-ops'"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-arrow-left mr-2"></i>
            Back to List
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Basic Information -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-600">PO Number</label>
                <p class="text-lg font-semibold text-gray-900">{{ po.number }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Status</label>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusColor(po.status)">
                  {{ po.status.toUpperCase() }}
                </span>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Date</label>
                <p class="text-gray-900">{{ formatDate(po.date) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Supplier</label>
                <p class="text-gray-900">{{ po.supplier?.name || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Source PR</label>
                <p class="text-gray-900">{{ po.source_pr?.pr_number || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Arrival Date</label>
                <p class="text-gray-900">{{ po.arrival_date ? formatDate(po.arrival_date) : '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Subtotal</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(po.subtotal) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">PPN (11%)</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(po.ppn_amount) }}</p>
              </div>
              <div class="md:col-span-2">
                <label class="text-sm font-medium text-gray-600">Grand Total</label>
                <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(po.grand_total) }}</p>
              </div>
            </div>
            
            <div v-if="po.notes" class="mt-4">
              <label class="text-sm font-medium text-gray-600">Notes</label>
              <p class="text-gray-900 mt-1">{{ po.notes }}</p>
            </div>
          </div>

          <!-- Approval Flow Section (Only for Draft Status) -->
          <div v-if="canSubmitForApproval" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Approval Flow</h2>
            <p class="text-sm text-gray-600 mb-4">Add approvers in order from lowest to highest level. The first approver will be the lowest level, and the last approver will be the highest level.</p>
            
            <!-- Add Approver Input -->
            <div class="mb-4">
              <div class="relative">
                <input
                  v-model="approverSearch"
                  type="text"
                  placeholder="Search users by name, email, or jabatan..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
                />
                
                <!-- Dropdown Results -->
                <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                  <div
                    v-for="user in approverResults"
                    :key="user.id"
                    @click="addApprover(user)"
                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                  >
                    <div class="font-medium">{{ user.name }}</div>
                    <div class="text-sm text-gray-600">{{ user.email }}</div>
                    <div v-if="user.jabatan" class="text-xs text-blue-600 font-medium">{{ user.jabatan }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Approvers List -->
            <div v-if="selectedApprovers.length > 0" class="space-y-2">
              <h4 class="font-medium text-gray-700">Approval Order (Lowest to Highest):</h4>
              <div
                v-for="(approver, index) in selectedApprovers"
                :key="approver.id"
                class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-md"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="index > 0"
                      @click="reorderApprover(index, index - 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Move Up"
                    >
                      <i class="fa fa-arrow-up"></i>
                    </button>
                    <button
                      v-if="index < selectedApprovers.length - 1"
                      @click="reorderApprover(index, index + 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Move Down"
                    >
                      <i class="fa fa-arrow-down"></i>
                    </button>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      Level {{ index + 1 }}
                    </span>
                    <div>
                      <div class="font-medium">{{ approver.name }}</div>
                      <div class="text-sm text-gray-600">{{ approver.email }}</div>
                      <div v-if="approver.jabatan" class="text-xs text-blue-600 font-medium">{{ approver.jabatan }}</div>
                    </div>
                  </div>
                </div>
                <button
                  @click="removeApprover(index)"
                  class="p-1 text-red-500 hover:text-red-700"
                  title="Remove Approver"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Items -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Items</h2>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="item in po.items" :key="item.id">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.item_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.quantity }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(item.price) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatCurrency(item.total) }}</td>
                  </tr>
                  <tr v-if="!po.items || po.items.length === 0">
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                      No items found. Debug: {{ JSON.stringify(po.items) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Actions -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Actions</h2>
            <div class="space-y-3">
              <!-- Purchasing Manager Approval -->
              <div v-if="canApprove" class="space-y-2">
                <button
                  @click="showApprovalModal = true"
                  class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
                >
                  <i class="fas fa-check mr-2"></i>
                  Approve (PM)
                </button>
                <button
                  @click="showRejectModal = true"
                  class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"
                >
                  <i class="fas fa-times mr-2"></i>
                  Reject (PM)
                </button>
              </div>

              <!-- GM Finance Approval -->
              <div v-if="canApproveGM" class="space-y-2">
                <button
                  @click="showApprovalModal = true"
                  class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                >
                  <i class="fas fa-check mr-2"></i>
                  Approve (GM Finance)
                </button>
                <button
                  @click="showRejectModal = true"
                  class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"
                >
                  <i class="fas fa-times mr-2"></i>
                  Reject (GM Finance)
                </button>
              </div>

              <!-- Submit for Approval (New Flow) -->
              <div v-if="canSubmitForApproval" class="space-y-2">
                <button
                  @click="submitForApproval"
                  class="w-full bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700"
                >
                  <i class="fas fa-paper-plane mr-2"></i>
                  Submit for Approval
                </button>
              </div>

              <!-- Approval Flow Actions -->
              <div v-if="canApproveFlow" class="space-y-2">
                <button
                  @click="showApprovalModal = true"
                  class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
                >
                  <i class="fas fa-check mr-2"></i>
                  Approve
                </button>
                <button
                  @click="showRejectModal = true"
                  class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"
                >
                  <i class="fas fa-times mr-2"></i>
                  Reject
                </button>
              </div>

              <!-- Print -->
              <button
                @click="markPrinted"
                class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700"
              >
                <i class="fas fa-print mr-2"></i>
                Mark as Printed
              </button>

              <!-- Delete -->
              <button
                v-if="canDelete"
                @click="deletePO"
                class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"
              >
                <i class="fas fa-trash mr-2"></i>
                Delete
              </button>
            </div>
          </div>

          <!-- Approval History -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Approval History</h2>
            <div class="space-y-4">
              <!-- Purchasing Manager -->
              <div v-if="po.purchasing_manager_approved_at">
                <div class="flex items-center space-x-2">
                  <i class="fas fa-user-tie text-blue-500"></i>
                  <span class="font-medium">Purchasing Manager</span>
                  <span class="text-green-600 text-sm">✓ Approved</span>
                </div>
                <p class="text-sm text-gray-600 ml-6">{{ po.purchasing_manager?.nama_lengkap }}</p>
                <p class="text-xs text-gray-500 ml-6">{{ formatDate(po.purchasing_manager_approved_at) }}</p>
                <p v-if="po.purchasing_manager_note" class="text-sm text-gray-600 ml-6 mt-1">{{ po.purchasing_manager_note }}</p>
              </div>

              <!-- GM Finance -->
              <div v-if="po.gm_finance_approved_at">
                <div class="flex items-center space-x-2">
                  <i class="fas fa-user-shield text-purple-500"></i>
                  <span class="font-medium">GM Finance</span>
                  <span class="text-green-600 text-sm">✓ Approved</span>
                </div>
                <p class="text-sm text-gray-600 ml-6">{{ po.gm_finance?.nama_lengkap }}</p>
                <p class="text-xs text-gray-500 ml-6">{{ formatDate(po.gm_finance_approved_at) }}</p>
                <p v-if="po.gm_finance_note" class="text-sm text-gray-600 ml-6 mt-1">{{ po.gm_finance_note }}</p>
              </div>
            </div>
          </div>

          <!-- Creator Info -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Creator Info</h2>
            <div class="space-y-2">
              <div>
                <span class="text-sm font-medium text-gray-600">Created by:</span>
                <p class="text-gray-900">{{ po.creator?.nama_lengkap || '-' }}</p>
              </div>
              <div>
                <span class="text-sm font-medium text-gray-600">Created at:</span>
                <p class="text-gray-900">{{ formatDate(po.created_at) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Approval Modal -->
      <div v-if="showApprovalModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Note</h3>
            <textarea
              v-model="approvalNote"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter approval note..."
            ></textarea>
            <div class="flex justify-end space-x-2 mt-4">
              <button
                @click="showApprovalModal = false"
                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
              >
                Cancel
              </button>
              <button
                @click="handleApproval(true)"
                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
              >
                Approve
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Rejection Modal -->
      <div v-if="showRejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Rejection Reason</h3>
            <textarea
              v-model="rejectionReason"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
              placeholder="Enter rejection reason..."
            ></textarea>
            <div class="flex justify-end space-x-2 mt-4">
              <button
                @click="showRejectModal = false"
                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
              >
                Cancel
              </button>
              <button
                @click="handleApproval(false)"
                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
    
    <!-- Print Preview Modal -->
    <PrintPreviewModal 
      :show="showPreview"
      :po="po"
      @close="showPreview = false"
    />
  </AppLayout>
</template>
