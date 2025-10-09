<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  payment: Object,
});

const showUploadModal = ref(false);
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const approvalComments = ref('');
const rejectComments = ref('');
const fileInput = ref(null);

const canApprove = computed(() => {
  // Check if current user can approve this payment
  return props.payment.approval_flows?.some(flow => 
    flow.status === 'PENDING' && flow.approver_id === window.auth?.user?.id
  );
});

const getStatusColor = (status) => {
  const colors = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    paid: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
};

const getApprovalStatusColor = (status) => {
  const colors = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    APPROVED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
};

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

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const getFileIcon = (mimeType) => {
  if (mimeType.startsWith('image/')) return 'fas fa-image';
  if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
  if (mimeType.includes('word')) return 'fas fa-file-word';
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fas fa-file-excel';
  return 'fas fa-file';
};

const handleFileUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  try {
    const response = await axios.post(route('payments.attachments.store', props.payment.id), formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (response.data.success) {
      // Refresh the page to show new attachment
      router.reload();
      showUploadModal.value = false;
    }
  } catch (error) {
    console.error('Upload error:', error);
    alert('Failed to upload file');
  }
};

const downloadAttachment = (attachment) => {
  window.open(route('payments.attachments.download', attachment.id), '_blank');
};

const deleteAttachment = async (attachment) => {
  if (!confirm('Are you sure you want to delete this attachment?')) return;

  try {
    const response = await axios.delete(route('payments.attachments.destroy', attachment.id));
    if (response.data.success) {
      router.reload();
    }
  } catch (error) {
    console.error('Delete error:', error);
    alert('Failed to delete attachment');
  }
};

const approvePayment = async () => {
  try {
    const response = await axios.post(route('payments.approve', props.payment.id), {
      comments: approvalComments.value,
    });

    if (response.data.success) {
      router.reload();
      showApproveModal.value = false;
      approvalComments.value = '';
    }
  } catch (error) {
    console.error('Approval error:', error);
    alert('Failed to approve payment');
  }
};

const rejectPayment = async () => {
  if (!rejectComments.value.trim()) {
    alert('Please provide a reason for rejection');
    return;
  }

  try {
    const response = await axios.post(route('payments.reject', props.payment.id), {
      comments: rejectComments.value,
    });

    if (response.data.success) {
      router.reload();
      showRejectModal.value = false;
      rejectComments.value = '';
    }
  } catch (error) {
    console.error('Rejection error:', error);
    alert('Failed to reject payment');
  }
};
</script>

<template>
  <AppLayout>
    <div class="p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Payment Details</h1>
          <p class="text-gray-600">Payment Number: {{ payment.payment_number }}</p>
        </div>
        <div class="flex space-x-2">
          <Link
            v-if="payment.status === 'pending'"
            :href="route('payments.edit', payment.id)"
            class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
          >
            <i class="fas fa-edit"></i>
            Edit Payment
          </Link>
          <Link
            :href="route('payments.index')"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
          >
            <i class="fas fa-arrow-left"></i>
            Back to Payments
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Payment Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Payment Information -->
          <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-600">Payment Number</label>
                <p class="text-lg font-semibold text-gray-900">{{ payment.payment_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Status</label>
                <span :class="getStatusColor(payment.status)" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full">
                  {{ payment.status }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Amount</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(payment.amount) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Payment Method</label>
                <p class="text-lg font-semibold text-gray-900 capitalize">{{ payment.payment_method }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Payment Date</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatDate(payment.payment_date) }}</p>
              </div>
              <div v-if="payment.due_date">
                <label class="block text-sm font-medium text-gray-600">Due Date</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatDate(payment.due_date) }}</p>
              </div>
              <div v-if="payment.reference_number">
                <label class="block text-sm font-medium text-gray-600">Reference Number</label>
                <p class="text-lg font-semibold text-gray-900">{{ payment.reference_number }}</p>
              </div>
              <div v-if="payment.description">
                <label class="block text-sm font-medium text-gray-600">Description</label>
                <p class="text-lg font-semibold text-gray-900">{{ payment.description }}</p>
              </div>
            </div>
          </div>

          <!-- Purchase Requisition Info -->
          <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Purchase Requisition Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-600">PR Number</label>
                <p class="text-lg font-semibold text-gray-900">{{ payment.purchase_requisition?.number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Division</label>
                <p class="text-lg font-semibold text-gray-900">{{ payment.purchase_requisition?.division?.nama_divisi }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Created By</label>
                <p class="text-lg font-semibold text-gray-900">{{ payment.purchase_requisition?.creator?.nama_lengkap }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Created Date</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatDate(payment.purchase_requisition?.created_at) }}</p>
              </div>
            </div>
          </div>

          <!-- Purchase Order Info (if exists) -->
          <div v-if="payment.purchase_order" class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Purchase Order Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-600">PO Number</label>
                <p class="text-lg font-semibold text-gray-900">{{ payment.purchase_order?.number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">PO Status</label>
                <span :class="getStatusColor(payment.purchase_order?.status)" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full">
                  {{ payment.purchase_order?.status }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">PO Created Date</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatDate(payment.purchase_order?.created_at) }}</p>
              </div>
            </div>
          </div>

          <!-- Attachments -->
          <div class="bg-white rounded-lg shadow-sm border p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-lg font-semibold text-gray-900">Attachments</h2>
              <button
                v-if="payment.status === 'pending'"
                @click="showUploadModal = true"
                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-md text-sm flex items-center gap-2"
              >
                <i class="fas fa-plus"></i>
                Upload File
              </button>
            </div>
            
            <div v-if="payment.attachments && payment.attachments.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div
                v-for="attachment in payment.attachments"
                :key="attachment.id"
                class="border rounded-lg p-4 hover:shadow-md transition-shadow"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="flex items-center gap-2">
                    <i :class="getFileIcon(attachment.mime_type)" class="text-blue-600"></i>
                    <span class="text-sm font-medium text-gray-900 truncate">{{ attachment.file_name }}</span>
                  </div>
                  <div class="flex space-x-1">
                    <button
                      @click="downloadAttachment(attachment)"
                      class="text-blue-600 hover:text-blue-800"
                      title="Download"
                    >
                      <i class="fas fa-download"></i>
                    </button>
                    <button
                      v-if="payment.status === 'pending'"
                      @click="deleteAttachment(attachment)"
                      class="text-red-600 hover:text-red-800"
                      title="Delete"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </div>
                <div class="text-xs text-gray-500">
                  <p>Size: {{ formatFileSize(attachment.file_size) }}</p>
                  <p>Uploaded by: {{ attachment.uploader?.nama_lengkap }}</p>
                  <p>Date: {{ formatDate(attachment.created_at) }}</p>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500">
              <i class="fas fa-file text-4xl mb-2"></i>
              <p>No attachments uploaded</p>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Supplier Info -->
          <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Supplier Information</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600">Supplier Name</label>
                <p class="text-lg font-semibold text-gray-900">{{ payment.supplier?.nama_supplier }}</p>
              </div>
              <div v-if="payment.supplier?.alamat">
                <label class="block text-sm font-medium text-gray-600">Address</label>
                <p class="text-sm text-gray-900">{{ payment.supplier.alamat }}</p>
              </div>
              <div v-if="payment.supplier?.telepon">
                <label class="block text-sm font-medium text-gray-600">Phone</label>
                <p class="text-sm text-gray-900">{{ payment.supplier.telepon }}</p>
              </div>
            </div>
          </div>

          <!-- Approval Status -->
          <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Approval Status</h2>
            <div v-if="payment.approval_flows && payment.approval_flows.length > 0" class="space-y-3">
              <div
                v-for="flow in payment.approval_flows"
                :key="flow.id"
                class="flex items-center justify-between p-3 border rounded-lg"
              >
                <div>
                  <p class="font-medium text-gray-900">Level {{ flow.approval_level }}</p>
                  <p class="text-sm text-gray-600">{{ flow.approver?.nama_lengkap }}</p>
                </div>
                <span :class="getApprovalStatusColor(flow.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                  {{ flow.status }}
                </span>
              </div>
            </div>
            <div v-else class="text-center py-4 text-gray-500">
              <p>No approval flows configured</p>
            </div>
          </div>

          <!-- Payment Actions -->
          <div v-if="canApprove" class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Actions</h2>
            <div class="space-y-3">
              <button
                @click="showApproveModal = true"
                class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center justify-center gap-2"
              >
                <i class="fas fa-check"></i>
                Approve Payment
              </button>
              <button
                @click="showRejectModal = true"
                class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md flex items-center justify-center gap-2"
              >
                <i class="fas fa-times"></i>
                Reject Payment
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Upload Modal -->
      <div v-if="showUploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Upload Attachment</h3>
            <input
              ref="fileInput"
              type="file"
              @change="handleFileUpload"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <div class="flex justify-end space-x-2 mt-4">
              <button
                @click="showUploadModal = false"
                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Approve Modal -->
      <div v-if="showApproveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Approve Payment</h3>
            <textarea
              v-model="approvalComments"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Add comments (optional)"
            ></textarea>
            <div class="flex justify-end space-x-2 mt-4">
              <button
                @click="showApproveModal = false"
                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                @click="approvePayment"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
              >
                Approve
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Reject Modal -->
      <div v-if="showRejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Reject Payment</h3>
            <textarea
              v-model="rejectComments"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Reason for rejection (required)"
              required
            ></textarea>
            <div class="flex justify-end space-x-2 mt-4">
              <button
                @click="showRejectModal = false"
                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                @click="rejectPayment"
                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>