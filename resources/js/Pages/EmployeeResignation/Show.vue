<template>
  <AppLayout title="Employee Resignation Detail">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user-minus text-red-500"></i> Employee Resignation Detail
        </h1>
        <div class="flex gap-3">
          <Link
            :href="'/employee-resignations'"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-arrow-left mr-2"></i>
            Back to List
          </Link>
          <button
            v-if="canEdit"
            @click="openEdit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-edit mr-2"></i>
            Edit
          </button>
          <button
            v-if="canDelete"
            @click="confirmDelete"
            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-trash mr-2"></i>
            Delete
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <!-- Basic Information -->
        <div class="mb-8">
          <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Basic Information</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Resignation Number</label>
              <div class="p-3 bg-gray-50 rounded-md font-mono">{{ resignation.resignation_number || '-' }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
              <div class="p-3">
                <span
                  :class="[
                    'px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full',
                    getStatusBadgeClass(resignation.status)
                  ]"
                >
                  {{ getStatusText(resignation.status) }}
                </span>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <div class="p-3 bg-gray-50 rounded-md">
                {{ resignation.outlet?.nama_outlet || '-' }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Employee</label>
              <div class="p-3 bg-gray-50 rounded-md">
                <div class="font-medium">{{ resignation.employee?.nama_lengkap || '-' }}</div>
                <div class="text-sm text-gray-500">{{ resignation.employee?.nik || '-' }}</div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Resignation Date</label>
              <div class="p-3 bg-gray-50 rounded-md">
                {{ formatDate(resignation.resignation_date) }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Resignation Type</label>
              <div class="p-3">
                <span
                  :class="[
                    'px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full',
                    resignation.resignation_type === 'prosedural' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                  ]"
                >
                  {{ resignation.resignation_type === 'prosedural' ? 'Prosedural' : 'Non Prosedural' }}
                </span>
              </div>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
              <div class="p-3 bg-gray-50 rounded-md min-h-[100px] whitespace-pre-wrap">
                {{ resignation.notes || '-' }}
              </div>
            </div>
          </div>
        </div>

        <!-- Approval Flow Section -->
        <div v-if="resignation.approval_flows && resignation.approval_flows.length > 0" class="mb-8">
          <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md flex items-center">
            <i class="fa fa-users mr-2 text-blue-500"></i>
            Approval Flow
          </h3>
          <div class="bg-gray-50 p-4 rounded-md">
            <div class="space-y-4">
              <div
                v-for="flow in sortedApprovalFlows"
                :key="flow.id"
                class="flex items-center justify-between p-4 border border-gray-200 rounded-lg"
                :class="getApprovalFlowClass(flow.status)"
              >
                <div class="flex items-center space-x-4">
                  <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      <i class="fa fa-layer-group mr-1"></i>
                      Level {{ flow.approval_level }}
                    </span>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shadow-md"
                         :class="getApprovalStatusIconClass(flow.status)">
                      <i :class="getApprovalStatusIcon(flow.status)"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="font-semibold text-gray-900 text-lg">
                      {{ flow.approver?.nama_lengkap || flow.approver?.name || 'Unknown' }}
                    </div>
                    <div class="text-sm text-gray-600 flex items-center mt-1">
                      <i class="fa fa-envelope mr-2"></i>{{ flow.approver?.email || '-' }}
                    </div>
                    <div v-if="flow.approver?.jabatan?.nama_jabatan" class="text-sm text-blue-600 font-medium mt-1 flex items-center">
                      <i class="fa fa-briefcase mr-2"></i>{{ flow.approver.jabatan.nama_jabatan }}
                    </div>
                    <div v-if="flow.comments" class="text-sm text-gray-600 mt-2 p-2 bg-gray-100 rounded border-l-4 border-blue-400">
                      <strong class="text-gray-800">Comments:</strong> {{ flow.comments }}
                    </div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm font-medium px-3 py-1 rounded-full"
                       :class="getApprovalStatusTextClass(flow.status)">
                    <i class="fa fa-circle mr-1 text-xs"></i>{{ flow.status }}
                  </div>
                  <div v-if="flow.approved_at" class="text-xs text-gray-500 mt-1">
                    <i class="fa fa-check-circle mr-1 text-green-500"></i>Approved: {{ formatDateTime(flow.approved_at) }}
                  </div>
                  <div v-if="flow.rejected_at" class="text-xs text-gray-500 mt-1">
                    <i class="fa fa-times-circle mr-1 text-red-500"></i>Rejected: {{ formatDateTime(flow.rejected_at) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Creator Information -->
        <div class="mb-8">
          <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Creator Information</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Created By</label>
              <div class="p-3 bg-gray-50 rounded-md">
                {{ resignation.creator?.nama_lengkap || '-' }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Created At</label>
              <div class="p-3 bg-gray-50 rounded-md">
                {{ formatDateTime(resignation.created_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  resignation: Object,
  auth: {
    type: Object,
    default: () => ({ user: null })
  }
});

const user = computed(() => props.auth?.user || null);

// Check if user can edit (only draft/rejected status and creator or superadmin)
const canEdit = computed(() => {
  if (!user.value || !props.resignation) return false;
  const isSuperadmin = user.value.id_role === '5af56935b011a';
  const createdById = props.resignation.created_by || (props.resignation.creator && props.resignation.creator.id);
  const isCreator = createdById == user.value.id;
  return (props.resignation.status === 'draft' || props.resignation.status === 'rejected') && (isCreator || isSuperadmin);
});

// Check if user can delete (only draft/rejected status and creator or superadmin)
const canDelete = computed(() => {
  if (!user.value || !props.resignation) return false;
  const isSuperadmin = user.value.id_role === '5af56935b011a';
  const createdById = props.resignation.created_by || (props.resignation.creator && props.resignation.creator.id);
  const isCreator = createdById == user.value.id;
  return (props.resignation.status === 'draft' || props.resignation.status === 'rejected') && (isCreator || isSuperadmin);
});

// Sort approval flows by approval_level
const sortedApprovalFlows = computed(() => {
  if (!props.resignation?.approval_flows || props.resignation.approval_flows.length === 0) {
    return [];
  }
  return [...props.resignation.approval_flows].sort((a, b) => (a.approval_level || 0) - (b.approval_level || 0));
});

function openEdit() {
  router.visit(`/employee-resignations/${props.resignation.id}/edit`);
}

function confirmDelete() {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/employee-resignations/${props.resignation.id}`, {
        onSuccess: () => {
          Swal.fire(
            'Deleted!',
            'Employee resignation has been deleted.',
            'success'
          ).then(() => {
            router.visit('/employee-resignations');
          });
        },
        onError: (errors) => {
          Swal.fire(
            'Error!',
            errors.message || 'Failed to delete employee resignation.',
            'error'
          );
        }
      });
    }
  });
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'draft':
      return 'bg-gray-100 text-gray-800';
    case 'submitted':
      return 'bg-yellow-100 text-yellow-800';
    case 'approved':
      return 'bg-green-100 text-green-800';
    case 'rejected':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
}

function getStatusText(status) {
  switch (status) {
    case 'draft':
      return 'Draft';
    case 'submitted':
      return 'Submitted';
    case 'approved':
      return 'Approved';
    case 'rejected':
      return 'Rejected';
    default:
      return status;
  }
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
}

function formatDateTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function getApprovalFlowClass(status) {
  switch (status) {
    case 'APPROVED':
      return 'border-green-300 bg-green-50';
    case 'REJECTED':
      return 'border-red-300 bg-red-50';
    case 'PENDING':
      return 'border-yellow-300 bg-yellow-50';
    default:
      return 'border-gray-300 bg-gray-50';
  }
}

function getApprovalStatusIcon(status) {
  switch (status) {
    case 'APPROVED':
      return 'fa fa-check-circle text-green-600';
    case 'REJECTED':
      return 'fa fa-times-circle text-red-600';
    case 'PENDING':
      return 'fa fa-clock text-yellow-600';
    default:
      return 'fa fa-question-circle text-gray-600';
  }
}

function getApprovalStatusIconClass(status) {
  switch (status) {
    case 'APPROVED':
      return 'bg-green-100';
    case 'REJECTED':
      return 'bg-red-100';
    case 'PENDING':
      return 'bg-yellow-100';
    default:
      return 'bg-gray-100';
  }
}

function getApprovalStatusTextClass(status) {
  switch (status) {
    case 'APPROVED':
      return 'bg-green-100 text-green-800';
    case 'REJECTED':
      return 'bg-red-100 text-red-800';
    case 'PENDING':
      return 'bg-yellow-100 text-yellow-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
}
</script>
