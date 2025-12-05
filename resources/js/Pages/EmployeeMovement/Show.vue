<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  movement: Object,
  user: Object,
});

const isEditingSalary = ref(false);
const salaryForm = useForm({
  gaji_pokok_to: props.movement.gaji_pokok_to || '',
  tunjangan_to: props.movement.tunjangan_to || '',
});

// Check if user can edit salary (division_id = 6)
const canEditSalary = computed(() => {
  return props.user?.division_id === 6;
});

// Check if user can execute movement (HR or superadmin)
const canExecuteMovement = computed(() => {
  return props.user?.division_id === 6 || props.user?.id_role === '5af56935b011a';
});

// Check if user can approve and what level
const canApprove = computed(() => {
  const userId = props.user?.id;
  if (!userId) return { canApprove: false, level: null };
  
  // Check HOD approval
  if (props.movement.hod_approver_id == userId && !props.movement.hod_approval) {
    return { canApprove: true, level: 'hod' };
  }
  
  // Check GM approval (only if HOD is approved)
  if (props.movement.gm_approver_id == userId && 
      props.movement.hod_approval === 'approved' && 
      !props.movement.gm_approval) {
    return { canApprove: true, level: 'gm' };
  }
  
  // Check GM HR approval (only if GM is approved)
  if (props.movement.gm_hr_approver_id == userId && 
      props.movement.gm_approval === 'approved' && 
      !props.movement.gm_hr_approval) {
    return { canApprove: true, level: 'gm_hr' };
  }
  
  // Check BOD approval (only if GM HR is approved)
  if (props.movement.bod_approver_id == userId && 
      props.movement.gm_hr_approval === 'approved' && 
      !props.movement.bod_approval) {
    return { canApprove: true, level: 'bod' };
  }
  
  return { canApprove: false, level: null };
});

// Check if movement is pending approval
const isPendingApproval = computed(() => {
  return props.movement.status === 'pending' || props.movement.status === 'draft';
});

// Check if salary change is allowed for this employment type
const isSalaryChangeAllowed = computed(() => {
  return props.movement.employment_type && 
         props.movement.employment_type !== 'extend_contract_without_adjustment' &&
         props.movement.employment_type !== 'termination';
});

function goBack() {
  router.visit('/employee-movements');
}

function openEdit() {
  router.visit(`/employee-movements/${props.movement.id}/edit`);
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'draft':
      return 'bg-gray-100 text-gray-800';
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    case 'approved':
      return 'bg-green-100 text-green-800';
    case 'rejected':
      return 'bg-red-100 text-red-800';
    case 'executed':
      return 'bg-blue-100 text-blue-800';
    case 'error':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
}

function getStatusText(status) {
  switch (status) {
    case 'draft':
      return 'Draft';
    case 'pending':
      return 'Pending';
    case 'approved':
      return 'Approved';
    case 'rejected':
      return 'Rejected';
    case 'executed':
      return 'Executed';
    case 'error':
      return 'Error';
    default:
      return status;
  }
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
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

function formatCurrency(amount) {
  if (!amount) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(amount);
}

function getEmploymentTypeText(type) {
  switch (type) {
    case 'extend_contract_without_adjustment':
      return 'Extend contract without adjustment';
    case 'extend_contract_with_adjustment':
      return 'Extend contract with adjustment';
    case 'promotion':
      return 'Promotion';
    case 'demotion':
      return 'Demotion';
    case 'mutation':
      return 'Mutation';
    case 'termination':
      return 'Termination';
    default:
      return type || '-';
  }
}

function getFileName(path) {
  if (!path) return '-';
  return path.split('/').pop();
}

function downloadFile(path) {
  if (!path) return;
  window.open(`/storage/${path}`, '_blank');
}

function startEditSalary() {
  isEditingSalary.value = true;
  salaryForm.gaji_pokok_to = props.movement.gaji_pokok_to || '';
  salaryForm.tunjangan_to = props.movement.tunjangan_to || '';
}

function cancelEditSalary() {
  isEditingSalary.value = false;
  salaryForm.reset();
}

function saveSalary() {
  salaryForm.put(`/employee-movements/${props.movement.id}/salary`, {
    onSuccess: () => {
      isEditingSalary.value = false;
      // Refresh the page to get updated data
      router.reload();
    },
    onError: (errors) => {
      console.error('Error updating salary:', errors);
    }
  });
}

function formatCurrencyInput(value) {
  if (!value) return '';
  return new Intl.NumberFormat('id-ID').format(value);
}

function unformatCurrency(value) {
  if (!value) return '';
  return value.replace(/[^\d]/g, '');
}

function executeMovement() {
  if (confirm('Apakah Anda yakin ingin mengeksekusi perubahan employee movement ini?')) {
    axios.post(`/employee-movements/${props.movement.id}/execute`)
      .then(response => {
        if (response.data.success) {
          alert('Employee movement berhasil dieksekusi!');
          router.reload();
        } else {
          alert('Error: ' + response.data.message);
        }
      })
      .catch(error => {
        console.error('Error executing movement:', error);
        alert('Terjadi kesalahan saat mengeksekusi movement');
      });
  }
}

function approveMovement(status) {
  const approvalLevel = canApprove.value.level;
  const action = status === 'approved' ? 'approve' : 'reject';
  const actionText = status === 'approved' ? 'menyetujui' : 'menolak';
  
  Swal.fire({
    title: `Confirm ${action === 'approve' ? 'Approval' : 'Rejection'}`,
    html: `
      <div class="text-left">
        <p>Apakah Anda yakin ingin <strong>${actionText}</strong> employee movement ini?</p>
        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional):</label>
          <textarea id="approval-notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter notes here..."></textarea>
        </div>
      </div>
    `,
    icon: action === 'approve' ? 'question' : 'warning',
    showCancelButton: true,
    confirmButtonColor: action === 'approve' ? '#10b981' : '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: action === 'approve' ? 'Yes, Approve' : 'Yes, Reject',
    cancelButtonText: 'Cancel',
    showLoaderOnConfirm: true,
    preConfirm: () => {
      const notes = document.getElementById('approval-notes').value;
      
      return axios.post(`/employee-movements/${props.movement.id}/approve`, {
        approval_level: approvalLevel,
        status: status,
        notes: notes
      }).then(response => {
        if (response.data.success) {
          return response.data;
        } else {
          throw new Error(response.data.message || 'Approval failed');
        }
      }).catch(error => {
        Swal.showValidationMessage(`Request failed: ${error.response?.data?.message || error.message}`);
        return false;
      });
    },
    allowOutsideClick: () => !Swal.isLoading()
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Success!',
        text: `Employee movement ${action === 'approve' ? 'approved' : 'rejected'} successfully.`,
        icon: 'success',
        confirmButtonText: 'OK'
      }).then(() => {
        router.reload();
      });
    }
  });
}
</script>

<template>
  <AppLayout title="Employee Movement Detail">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Employee Movement Detail
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <!-- Header -->
          <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900">Employee Movement Details</h3>
              <div class="flex space-x-2">
                <button
                  @click="goBack"
                  class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  Back
                </button>
                
                <!-- Approval Buttons -->
                <template v-if="canApprove.canApprove && isPendingApproval">
                  <button
                    @click="approveMovement('approved')"
                    class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700"
                  >
                    Approve {{ canApprove.level.toUpperCase() }}
                  </button>
                  <button
                    @click="approveMovement('rejected')"
                    class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700"
                  >
                    Reject {{ canApprove.level.toUpperCase() }}
                  </button>
                </template>
                
                <!-- Execute Movement Button -->
                <button
                  v-if="canExecuteMovement && movement.status === 'approved'"
                  @click="executeMovement"
                  class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700"
                >
                  Execute Movement
                </button>
                
                <!-- Edit Button -->
                <button
                  @click="openEdit"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
                >
                  Edit
                </button>
              </div>
            </div>
          </div>

          <div class="p-6">
            <!-- Employee Details Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Employee Details</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Name</label>
                  <div class="mt-1 p-2 bg-gray-50 rounded-md">{{ movement.employee_name || '-' }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Position</label>
                  <div class="mt-1 p-2 bg-gray-50 rounded-md">{{ movement.employee_position || '-' }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Division</label>
                  <div class="mt-1 p-2 bg-gray-50 rounded-md">{{ movement.employee_division || '-' }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Unit/Property</label>
                  <div class="mt-1 p-2 bg-gray-50 rounded-md">{{ movement.employee_unit_property || '-' }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Join Date</label>
                  <div class="mt-1 p-2 bg-gray-50 rounded-md">{{ formatDate(movement.employee_join_date) }}</div>
                </div>
              </div>
            </div>

            <!-- Employment & Renewal Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Employment & Renewal</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Employment Type</label>
                  <div class="p-2 bg-white rounded-md">{{ getEmploymentTypeText(movement.employment_type) }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Effective Date</label>
                  <div class="mt-1 p-2 bg-white rounded-md">{{ formatDate(movement.employment_effective_date) }}</div>
                </div>
              </div>
            </div>

            <!-- Supporting Documents Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Supporting Documents</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <div class="space-y-6">
                  <!-- KPI Section -->
                  <div class="border-b border-gray-200 pb-4">
                    <div class="flex items-center justify-between mb-3">
                      <div class="flex items-center">
                        <input
                          :checked="movement.kpi_required"
                          type="checkbox"
                          disabled
                          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 text-sm font-medium text-gray-700">Key Performance Indicators (KPI)</label>
                      </div>
                      <div class="p-2 bg-white rounded-md">{{ formatDate(movement.kpi_date) }}</div>
                    </div>
                    <div class="ml-6">
                      <label class="block text-sm text-gray-600 mb-2">KPI Document</label>
                      <div v-if="movement.kpi_attachment" class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">{{ getFileName(movement.kpi_attachment) }}</span>
                        <button
                          @click="downloadFile(movement.kpi_attachment)"
                          class="text-blue-600 hover:text-blue-800 text-sm underline"
                        >
                          Download
                        </button>
                      </div>
                      <div v-else class="text-sm text-gray-500">No file uploaded</div>
                    </div>
                  </div>

                  <!-- Psikotest Section -->
                  <div class="border-b border-gray-200 pb-4">
                    <div class="flex items-center justify-between mb-3">
                      <div class="flex items-center">
                        <input
                          :checked="movement.psikotest_required"
                          type="checkbox"
                          disabled
                          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 text-sm font-medium text-gray-700">Psikotest Result by Training Manager: score = {{ movement.psikotest_score || '-' }}</label>
                      </div>
                      <div class="p-2 bg-white rounded-md">{{ formatDate(movement.psikotest_date) }}</div>
                    </div>
                    <div class="ml-6">
                      <label class="block text-sm text-gray-600 mb-2">Psikotest Result</label>
                      <div v-if="movement.psikotest_attachment" class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">{{ getFileName(movement.psikotest_attachment) }}</span>
                        <button
                          @click="downloadFile(movement.psikotest_attachment)"
                          class="text-blue-600 hover:text-blue-800 text-sm underline"
                        >
                          Download
                        </button>
                      </div>
                      <div v-else class="text-sm text-gray-500">No file uploaded</div>
                    </div>
                  </div>

                  <!-- Training Attendance Section -->
                  <div class="border-b border-gray-200 pb-4">
                    <div class="flex items-center justify-between mb-3">
                      <div class="flex items-center">
                        <input
                          :checked="movement.training_attendance_required"
                          type="checkbox"
                          disabled
                          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 text-sm font-medium text-gray-700">Training Attendance Record by Training Manager</label>
                      </div>
                      <div class="p-2 bg-white rounded-md">{{ formatDate(movement.training_attendance_date) }}</div>
                    </div>
                    <div class="ml-6">
                      <label class="block text-sm text-gray-600 mb-2">Training Record</label>
                      <div v-if="movement.training_attachment" class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">{{ getFileName(movement.training_attachment) }}</span>
                        <button
                          @click="downloadFile(movement.training_attachment)"
                          class="text-blue-600 hover:text-blue-800 text-sm underline"
                        >
                          Download
                        </button>
                      </div>
                      <div v-else class="text-sm text-gray-500">No file uploaded</div>
                    </div>
                  </div>

                  <!-- Other Attachments Section -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Other Supporting Documents</label>
                    <div class="ml-6">
                      <div v-if="movement.other_attachments" class="space-y-2">
                        <div v-for="(file, index) in JSON.parse(movement.other_attachments || '[]')" :key="index" class="flex items-center space-x-2">
                          <span class="text-sm text-gray-600">{{ getFileName(file) }}</span>
                          <button
                            @click="downloadFile(file)"
                            class="text-blue-600 hover:text-blue-800 text-sm underline"
                          >
                            Download
                          </button>
                        </div>
                      </div>
                      <div v-else class="text-sm text-gray-500">No files uploaded</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Adjustment & Movement Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Adjustment & Movement</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <div class="space-y-4">
                  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        :checked="movement.position_change"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Position</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ movement.position_from || '-' }}</div>
                    <div class="p-2 bg-white rounded-md">{{ movement.position_to || '-' }}</div>
                    <div></div>
                    <div></div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        :checked="movement.level_change"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Level</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ movement.level_from || '-' }}</div>
                    <div class="p-2 bg-white rounded-md">{{ movement.level_to || '-' }}</div>
                    <div></div>
                    <div></div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        :checked="movement.salary_change"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Salary</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ formatCurrency(movement.salary_from) }}</div>
                    <div class="p-2 bg-white rounded-md">
                      <div v-if="!isEditingSalary">
                        {{ formatCurrency(movement.salary_to) }}
                        <button 
                          v-if="canEditSalary && isSalaryChangeAllowed && movement.salary_change"
                          @click="startEditSalary"
                          class="ml-2 text-blue-600 hover:text-blue-800 text-xs"
                        >
                          Edit
                        </button>
                      </div>
                      <div v-else class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                          <div>
                            <label class="block text-xs text-gray-500 mb-1">Gaji Pokok</label>
                            <input
                              v-model="salaryForm.gaji_pokok_to"
                              @input="salaryForm.gaji_pokok_to = formatCurrencyInput($event.target.value)"
                              type="text"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                          </div>
                          <div>
                            <label class="block text-xs text-gray-500 mb-1">Tunjangan</label>
                            <input
                              v-model="salaryForm.tunjangan_to"
                              @input="salaryForm.tunjangan_to = formatCurrencyInput($event.target.value)"
                              type="text"
                              class="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            />
                          </div>
                        </div>
                        <div class="flex space-x-2">
                          <button
                            @click="saveSalary"
                            :disabled="salaryForm.processing"
                            class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 disabled:opacity-50"
                          >
                            {{ salaryForm.processing ? 'Saving...' : 'Save' }}
                          </button>
                          <button
                            @click="cancelEditSalary"
                            class="px-3 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700"
                          >
                            Cancel
                          </button>
                        </div>
                      </div>
                    </div>
                    <div></div>
                    <div></div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        :checked="movement.division_change"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Division</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ movement.division_from || '-' }}</div>
                    <div class="p-2 bg-white rounded-md">{{ movement.division_to || '-' }}</div>
                    <div></div>
                    <div></div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        :checked="movement.unit_property_change"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Unit/Property</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ movement.unit_property_from || '-' }}</div>
                    <div class="p-2 bg-white rounded-md">{{ movement.unit_property_to || '-' }}</div>
                    <div></div>
                    <div></div>
                  </div>
                </div>
                
                <div class="mt-4">
                  <label class="block text-sm font-medium text-gray-700">Effective Date</label>
                  <div class="mt-1 p-2 bg-white rounded-md">{{ formatDate(movement.adjustment_effective_date) }}</div>
                </div>
              </div>
            </div>

            <!-- Comments Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Comments</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <label class="block text-sm font-medium text-gray-700 mb-2">(Explain reason for movement.)</label>
                <div class="p-3 bg-white rounded-md min-h-[100px] whitespace-pre-wrap">
                  {{ movement.comments || 'No comments provided.' }}
                </div>
              </div>
            </div>

            <!-- Status Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Status</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <span
                  :class="[
                    'px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full',
                    getStatusBadgeClass(movement.status)
                  ]"
                >
                  {{ getStatusText(movement.status) }}
                </span>
              </div>
            </div>

            <!-- Approval Flow Section -->
            <div v-if="movement.approval_flows && movement.approval_flows.length > 0" class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md flex items-center">
                <i class="fa fa-users mr-2 text-blue-500"></i>
                Approval Flow
              </h3>
              <!-- Current Approval Status Info for new approval flow -->
              <div v-if="canApprove.canApprove && isPendingApproval" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-blue-700">
                      <strong>Action Required:</strong> This movement is waiting for your approval at Level {{ canApprove.level }}.
                    </p>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50 p-4 rounded-md">
                <div class="space-y-4">
                  <div
                    v-for="(flow, index) in movement.approval_flows.sort((a, b) => (a.approval_level || 0) - (b.approval_level || 0))"
                    :key="flow.id"
                    class="flex items-center justify-between p-4 border border-gray-200 rounded-lg bg-gradient-to-r from-gray-50 to-white"
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
                        <div class="font-semibold text-gray-900 text-lg">{{ flow.approver?.nama_lengkap || flow.approver?.name || 'Unknown' }}</div>
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

            <!-- Legacy Approval Assignment Section (for backward compatibility) -->
            <div v-else class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Approval Assignment</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">HOD Approver</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px]">
                      <div v-if="movement.hod_approver" class="text-sm">
                        <div class="font-medium">{{ movement.hod_approver.nama_lengkap }}</div>
                        <div class="text-gray-500">{{ movement.hod_approver.nik }}</div>
                      </div>
                      <div v-else class="text-gray-500 text-sm">Not assigned</div>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">GM Approver</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px]">
                      <div v-if="movement.gm_approver" class="text-sm">
                        <div class="font-medium">{{ movement.gm_approver.nama_lengkap }}</div>
                        <div class="text-gray-500">{{ movement.gm_approver.nik }}</div>
                      </div>
                      <div v-else class="text-gray-500 text-sm">Not assigned</div>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">GM HR Approver</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px]">
                      <div v-if="movement.gm_hr_approver" class="text-sm">
                        <div class="font-medium">{{ movement.gm_hr_approver.nama_lengkap }}</div>
                        <div class="text-gray-500">{{ movement.gm_hr_approver.nik }}</div>
                      </div>
                      <div v-else class="text-gray-500 text-sm">Not assigned</div>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">BOD Approver</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px]">
                      <div v-if="movement.bod_approver" class="text-sm">
                        <div class="font-medium">{{ movement.bod_approver.nama_lengkap }}</div>
                        <div class="text-gray-500">{{ movement.bod_approver.nik }}</div>
                      </div>
                      <div v-else class="text-gray-500 text-sm">Not assigned</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Approval Status Section (only show for legacy data without approval flows) -->
            <div v-if="!movement.approval_flows || movement.approval_flows.length === 0" class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Approval Status</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <!-- Current Approval Status Info -->
                <div v-if="canApprove.canApprove && isPendingApproval" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm text-blue-700">
                        <strong>Action Required:</strong> This movement is waiting for your {{ canApprove.level.toUpperCase() }} approval.
                      </p>
                    </div>
                  </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">HOD Approval</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px] flex items-center justify-center">
                      <span :class="{
                        'text-green-600 font-semibold': movement.hod_approval === 'approved',
                        'text-red-600 font-semibold': movement.hod_approval === 'rejected',
                        'text-gray-500': !movement.hod_approval
                      }">
                        {{ movement.hod_approval || 'Not signed' }}
                      </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ formatDate(movement.hod_approval_date) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">GM Approval</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px] flex items-center justify-center">
                      <span :class="{
                        'text-green-600 font-semibold': movement.gm_approval === 'approved',
                        'text-red-600 font-semibold': movement.gm_approval === 'rejected',
                        'text-gray-500': !movement.gm_approval
                      }">
                        {{ movement.gm_approval || 'Not signed' }}
                      </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ formatDate(movement.gm_approval_date) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">GM HR Approval</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px] flex items-center justify-center">
                      <span :class="{
                        'text-green-600 font-semibold': movement.gm_hr_approval === 'approved',
                        'text-red-600 font-semibold': movement.gm_hr_approval === 'rejected',
                        'text-gray-500': !movement.gm_hr_approval
                      }">
                        {{ movement.gm_hr_approval || 'Not signed' }}
                      </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ formatDate(movement.gm_hr_approval_date) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">BOD Approval</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px] flex items-center justify-center">
                      <span :class="{
                        'text-green-600 font-semibold': movement.bod_approval === 'approved',
                        'text-red-600 font-semibold': movement.bod_approval === 'rejected',
                        'text-gray-500': !movement.bod_approval
                      }">
                        {{ movement.bod_approval || 'Not signed' }}
                      </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ formatDate(movement.bod_approval_date) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Timestamps -->
            <div class="text-sm text-gray-500 border-t pt-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>Created: {{ formatDate(movement.created_at) }}</div>
                <div>Last Updated: {{ formatDate(movement.updated_at) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
