<template>
  <AppLayout title="Pending Approvals">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clock text-orange-500"></i> 
          Pending Approvals
        </h1>
        <Link
          :href="'/po-ops'"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i>
          Back to PO List
        </Link>
      </div>

      <div v-if="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>

      <div v-else-if="pendingPOs.length === 0" class="text-center py-8">
        <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No Pending Approvals</h3>
        <p class="text-gray-600">You have no pending approvals at the moment.</p>
      </div>

      <div v-else class="space-y-6">
        <div v-for="po in pendingPOs" :key="po.id" class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ po.number }}</h3>
              <p class="text-sm text-gray-600">{{ formatDate(po.date) }}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
              Pending Approval
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="text-sm font-medium text-gray-600">Supplier</label>
              <p class="text-gray-900">{{ po.supplier?.name || '-' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-600">Grand Total</label>
              <p class="text-lg font-semibold text-blue-600">{{ formatCurrency(po.grand_total) }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-600">Created By</label>
              <p class="text-gray-900">{{ po.creator?.nama_lengkap || '-' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-600">Your Approval Level</label>
              <p class="text-gray-900">Level {{ currentApprovalLevel(po) }}</p>
            </div>
          </div>

          <!-- Approval Flow Status -->
          <div class="mb-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Approval Flow Status</h4>
            <div class="space-y-2">
              <div v-for="flow in po.approval_flows" :key="flow.id" class="flex items-center justify-between p-2 bg-gray-50 rounded">
                <div class="flex items-center space-x-2">
                  <i :class="getApprovalIcon(flow.status)" class="text-sm"></i>
                  <span class="text-sm font-medium">{{ flow.approver?.nama_lengkap || 'Unknown' }}</span>
                  <span class="text-xs text-gray-500">(Level {{ flow.approval_level }})</span>
                </div>
                <span :class="getApprovalBadgeClass(flow.status)" class="text-xs px-2 py-1 rounded-full">
                  {{ getApprovalStatusText(flow.status) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Items Summary -->
          <div class="mb-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Items ({{ po.items?.length || 0 }})</h4>
            <div class="max-h-32 overflow-y-auto">
              <div v-for="item in po.items" :key="item.id" class="flex justify-between items-center py-1 border-b border-gray-100">
                <span class="text-sm text-gray-900">{{ item.item_name }}</span>
                <span class="text-sm text-gray-600">{{ item.quantity }} {{ item.unit }}</span>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex space-x-2">
            <Link
              :href="`/po-ops/${po.id}`"
              class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-center"
            >
              <i class="fas fa-eye mr-2"></i>
              View Details
            </Link>
            <button
              @click="approvePO(po.id)"
              class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
            >
              <i class="fas fa-check mr-2"></i>
              Approve
            </button>
            <button
              @click="rejectPO(po.id)"
              class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"
            >
              <i class="fas fa-times mr-2"></i>
              Reject
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const pendingPOs = ref([]);
const loading = ref(true);

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

const getApprovalIcon = (status) => {
  const icons = {
    'PENDING': 'fas fa-clock text-orange-500',
    'APPROVED': 'fas fa-check text-green-500',
    'REJECTED': 'fas fa-times text-red-500',
  };
  return icons[status] || 'fas fa-question text-gray-500';
};

const getApprovalBadgeClass = (status) => {
  const classes = {
    'PENDING': 'bg-orange-100 text-orange-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'REJECTED': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
};

const getApprovalStatusText = (status) => {
  const texts = {
    'PENDING': 'Pending',
    'APPROVED': 'Approved',
    'REJECTED': 'Rejected',
  };
  return texts[status] || 'Unknown';
};

const currentApprovalLevel = (po) => {
  const currentUserFlow = po.approval_flows?.find(flow => flow.status === 'PENDING');
  return currentUserFlow?.approval_level || 0;
};

const fetchPendingApprovals = async () => {
  try {
    loading.value = true;
    const response = await axios.get('/po-ops/pending-approvals');
    if (response.data.success) {
      pendingPOs.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching pending approvals:', error);
    Swal.fire('Error', 'Failed to fetch pending approvals', 'error');
  } finally {
    loading.value = false;
  }
};

const approvePO = async (poId) => {
  const { value: comments } = await Swal.fire({
    title: 'Approve Purchase Order',
    input: 'textarea',
    inputLabel: 'Comments (optional)',
    inputPlaceholder: 'Enter approval comments...',
    showCancelButton: true,
    confirmButtonText: 'Approve',
    confirmButtonColor: '#10b981',
    cancelButtonText: 'Cancel',
    inputValidator: (value) => {
      // Comments are optional
      return Promise.resolve();
    }
  });

  if (comments !== undefined) {
    try {
      const response = await axios.post(`/po-ops/${poId}/approve`, {
        approved: true,
        comments: comments || ''
      });

      if (response.data.success) {
        Swal.fire('Success', response.data.message, 'success');
        await fetchPendingApprovals();
      } else {
        Swal.fire('Error', response.data.message || 'Failed to approve PO', 'error');
      }
    } catch (error) {
      console.error('Error approving PO:', error);
      Swal.fire('Error', error.response?.data?.message || 'Failed to approve PO', 'error');
    }
  }
};

const rejectPO = async (poId) => {
  const { value: comments } = await Swal.fire({
    title: 'Reject Purchase Order',
    input: 'textarea',
    inputLabel: 'Rejection Reason',
    inputPlaceholder: 'Enter rejection reason...',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    confirmButtonColor: '#ef4444',
    cancelButtonText: 'Cancel',
    inputValidator: (value) => {
      if (!value || value.trim() === '') {
        return 'Please provide a rejection reason';
      }
      return Promise.resolve();
    }
  });

  if (comments !== undefined) {
    try {
      const response = await axios.post(`/po-ops/${poId}/approve`, {
        approved: false,
        comments: comments
      });

      if (response.data.success) {
        Swal.fire('Success', response.data.message, 'success');
        await fetchPendingApprovals();
      } else {
        Swal.fire('Error', response.data.message || 'Failed to reject PO', 'error');
      }
    } catch (error) {
      console.error('Error rejecting PO:', error);
      Swal.fire('Error', error.response?.data?.message || 'Failed to reject PO', 'error');
    }
  }
};

onMounted(() => {
  fetchPendingApprovals();
});
</script>
