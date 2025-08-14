<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  movement: Object,
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
    default:
      return status;
  }
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function formatCurrency(amount) {
  if (!amount) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(amount);
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                  <div class="flex items-center">
                    <input
                      :checked="movement.extend_contract_without_adjustment"
                      type="checkbox"
                      disabled
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label class="ml-2 text-sm text-gray-700">Extend contract without adjustment</label>
                  </div>
                  <div class="flex items-center">
                    <input
                      :checked="movement.extend_contract_with_adjustment"
                      type="checkbox"
                      disabled
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label class="ml-2 text-sm text-gray-700">Extend contract with adjustment</label>
                  </div>
                  <div class="flex items-center">
                    <input
                      :checked="movement.promotion"
                      type="checkbox"
                      disabled
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label class="ml-2 text-sm text-gray-700">Promotion</label>
                  </div>
                  <div class="flex items-center">
                    <input
                      :checked="movement.demotion"
                      type="checkbox"
                      disabled
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label class="ml-2 text-sm text-gray-700">Demotion</label>
                  </div>
                  <div class="flex items-center">
                    <input
                      :checked="movement.mutation"
                      type="checkbox"
                      disabled
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label class="ml-2 text-sm text-gray-700">Mutation</label>
                  </div>
                  <div class="flex items-center">
                    <input
                      :checked="movement.termination"
                      type="checkbox"
                      disabled
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label class="ml-2 text-sm text-gray-700">Termination</label>
                  </div>
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
                <div class="space-y-4">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center">
                      <input
                        :checked="movement.kpi_required"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Key Performance Indicators (KPI)</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ formatDate(movement.kpi_date) }}</div>
                  </div>
                  <div class="flex items-center justify-between">
                    <div class="flex items-center">
                      <input
                        :checked="movement.psikotest_required"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Psikotest Result by Training Manager: score = {{ movement.psikotest_score || '-' }}</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ formatDate(movement.psikotest_date) }}</div>
                  </div>
                  <div class="flex items-center justify-between">
                    <div class="flex items-center">
                      <input
                        :checked="movement.training_attendance_required"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Training Attendance Record by Training Manager</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ formatDate(movement.training_attendance_date) }}</div>
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
                    <div class="p-2 bg-white rounded-md">{{ formatCurrency(movement.salary_to) }}</div>
                    <div></div>
                    <div></div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        :checked="movement.department_change"
                        type="checkbox"
                        disabled
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Department</label>
                    </div>
                    <div class="p-2 bg-white rounded-md">{{ movement.department_from || '-' }}</div>
                    <div class="p-2 bg-white rounded-md">{{ movement.department_to || '-' }}</div>
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

            <!-- Approval Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4 bg-gray-100 p-3 rounded-md">Acknowledgement & Approval</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">HOD</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px] flex items-center justify-center">
                      {{ movement.hod_approval || 'Not signed' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ formatDate(movement.hod_approval_date) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">GM</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px] flex items-center justify-center">
                      {{ movement.gm_approval || 'Not signed' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ formatDate(movement.gm_approval_date) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">GM HR</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px] flex items-center justify-center">
                      {{ movement.gm_hr_approval || 'Not signed' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ formatDate(movement.gm_hr_approval_date) }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">BOD</label>
                    <div class="p-2 bg-white rounded-md min-h-[60px] flex items-center justify-center">
                      {{ movement.bod_approval || 'Not signed' }}
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
