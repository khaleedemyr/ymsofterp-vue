<template>
  <AppLayout title="Manage Outlet Budgets">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Manage Outlet Budgets
          </h2>
          <p class="text-sm text-gray-600 mt-1">{{ category.name }} - {{ category.division }}</p>
        </div>
        <Link
          href="/budget-management"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fa fa-arrow-left mr-2"></i>
          Back to Budget Management
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Category Summary -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Category Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-sm font-medium text-blue-600">Total Budget</div>
                <div class="text-2xl font-bold text-blue-800">{{ formatCurrency(category.budget_limit) }}</div>
              </div>
              <div class="bg-green-50 p-4 rounded-lg">
                <div class="text-sm font-medium text-green-600">Allocated</div>
                <div class="text-2xl font-bold text-green-800">{{ formatCurrency(totalAllocated) }}</div>
              </div>
              <div class="bg-orange-50 p-4 rounded-lg">
                <div class="text-sm font-medium text-orange-600">Used</div>
                <div class="text-2xl font-bold text-orange-800">{{ formatCurrency(totalUsed) }}</div>
              </div>
              <div class="bg-purple-50 p-4 rounded-lg">
                <div class="text-sm font-medium text-purple-600">Remaining</div>
                <div class="text-2xl font-bold text-purple-800">{{ formatCurrency(totalRemaining) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Bulk Actions</h3>
            <div class="flex flex-wrap gap-4">
              <button
                @click="showBulkCreateModal = true"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
              >
                <i class="fa fa-plus mr-2"></i>
                Create All Outlet Budgets
              </button>
              <button
                @click="exportOutletBudgets"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
              >
                <i class="fa fa-download mr-2"></i>
                Export Data
              </button>
            </div>
          </div>
        </div>

        <!-- Add New Outlet Budget -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Outlet Budget</h3>
            <form @submit.prevent="addOutletBudget" class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label for="outlet_id" class="block text-sm font-medium text-gray-700 mb-2">
                  Select Outlet <span class="text-red-500">*</span>
                </label>
                <select
                  id="outlet_id"
                  v-model="newBudgetForm.outlet_id"
                  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  required
                >
                  <option value="">Select an outlet</option>
                  <option
                    v-for="outlet in unallocatedOutlets"
                    :key="outlet.id_outlet"
                    :value="outlet.id_outlet"
                  >
                    {{ outlet.nama_outlet }}
                  </option>
                </select>
                <div v-if="errors.outlet_id" class="mt-1 text-sm text-red-600">
                  {{ errors.outlet_id }}
                </div>
              </div>
              <div>
                <label for="allocated_budget" class="block text-sm font-medium text-gray-700 mb-2">
                  Allocated Budget <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 sm:text-sm">Rp</span>
                  </div>
                  <input
                    id="allocated_budget"
                    v-model="newBudgetForm.allocated_budget"
                    type="number"
                    step="0.01"
                    min="0"
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="0.00"
                    required
                  />
                </div>
                <div v-if="errors.allocated_budget" class="mt-1 text-sm text-red-600">
                  {{ errors.allocated_budget }}
                </div>
              </div>
              <div class="flex items-end">
                <button
                  type="submit"
                  :disabled="loading"
                  class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                >
                  <span v-if="loading">Adding...</span>
                  <span v-else>Add Budget</span>
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Outlet Budgets Table -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Outlet Budget Allocations</h3>
            <div v-if="outletBudgets.length === 0" class="text-center py-8">
              <i class="fa fa-inbox text-gray-400 text-4xl mb-4"></i>
              <p class="text-gray-500">No outlet budgets configured yet.</p>
              <p class="text-sm text-gray-400">Use the form above to add outlet budgets.</p>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Outlet
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Allocated Budget
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Used Budget
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Remaining
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Usage %
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="budget in outletBudgets" :key="budget.id">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">
                        {{ budget.outlet?.nama_outlet || 'Unknown Outlet' }}
                      </div>
                      <div class="text-sm text-gray-500">ID: {{ budget.outlet_id }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">{{ formatCurrency(budget.allocated_budget) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">{{ formatCurrency(budget.used_budget) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">{{ formatCurrency(budget.allocated_budget - budget.used_budget) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                          <div
                            class="h-2 rounded-full"
                            :class="getUsageBarClass(budget)"
                            :style="{ width: getUsagePercentage(budget) + '%' }"
                          ></div>
                        </div>
                        <span class="text-sm text-gray-900">{{ getUsagePercentage(budget).toFixed(1) }}%</span>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span :class="getStatusBadgeClass(budget)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                        {{ getStatusLabel(budget) }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <div class="flex space-x-2">
                        <button
                          @click="editBudget(budget)"
                          class="text-blue-600 hover:text-blue-900"
                        >
                          <i class="fa fa-edit"></i> Edit
                        </button>
                        <button
                          @click="deleteBudget(budget)"
                          class="text-red-600 hover:text-red-900"
                        >
                          <i class="fa fa-trash"></i> Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Create Modal -->
    <div v-if="showBulkCreateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Create All Outlet Budgets</h3>
          <p class="text-sm text-gray-600 mb-4">
            This will create budget allocations for all outlets that don't have one yet.
          </p>
          <form @submit.prevent="bulkCreateBudgets">
            <div class="mb-4">
              <label for="default_budget" class="block text-sm font-medium text-gray-700 mb-2">
                Default Budget Amount <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 sm:text-sm">Rp</span>
                </div>
                <input
                  id="default_budget"
                  v-model="bulkCreateForm.default_budget"
                  type="number"
                  step="0.01"
                  min="0"
                  class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="0.00"
                  required
                />
              </div>
            </div>
            <div class="flex justify-end space-x-3">
              <button
                type="button"
                @click="showBulkCreateModal = false"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="loading"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
              >
                <span v-if="loading">Creating...</span>
                <span v-else>Create All</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Budget Modal -->
    <div v-if="showEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Outlet Budget</h3>
          <form @submit.prevent="updateBudget">
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <p class="text-sm text-gray-900">{{ editingBudget?.outlet?.nama_outlet }}</p>
            </div>
            <div class="mb-4">
              <label for="edit_allocated_budget" class="block text-sm font-medium text-gray-700 mb-2">
                Allocated Budget <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 sm:text-sm">Rp</span>
                </div>
                <input
                  id="edit_allocated_budget"
                  v-model="editForm.allocated_budget"
                  type="number"
                  step="0.01"
                  min="0"
                  class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="0.00"
                  required
                />
              </div>
            </div>
            <div class="flex justify-end space-x-3">
              <button
                type="button"
                @click="showEditModal = false"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="loading"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
              >
                <span v-if="loading">Updating...</span>
                <span v-else>Update</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  category: Object,
  outletBudgets: Array,
  outlets: Array,
  unallocatedOutlets: Array,
  errors: Object,
})

const loading = ref(false)
const showBulkCreateModal = ref(false)
const showEditModal = ref(false)
const editingBudget = ref(null)

const newBudgetForm = reactive({
  outlet_id: '',
  allocated_budget: '',
})

const bulkCreateForm = reactive({
  default_budget: '',
})

const editForm = reactive({
  allocated_budget: '',
})

const totalAllocated = computed(() => {
  return props.outletBudgets.reduce((sum, budget) => sum + parseFloat(budget.allocated_budget), 0)
})

const totalUsed = computed(() => {
  return props.outletBudgets.reduce((sum, budget) => sum + parseFloat(budget.used_budget), 0)
})

const totalRemaining = computed(() => {
  return totalAllocated.value - totalUsed.value
})

const formatCurrency = (amount) => {
  if (!amount) return 'Rp 0'
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount)
}

const getUsagePercentage = (budget) => {
  if (budget.allocated_budget <= 0) return 0
  return (budget.used_budget / budget.allocated_budget) * 100
}

const getUsageBarClass = (budget) => {
  const percentage = getUsagePercentage(budget)
  if (percentage >= 100) return 'bg-red-500'
  if (percentage >= 90) return 'bg-red-400'
  if (percentage >= 75) return 'bg-yellow-500'
  return 'bg-green-500'
}

const getStatusLabel = (budget) => {
  const percentage = getUsagePercentage(budget)
  if (percentage >= 100) return 'Exceeded'
  if (percentage >= 90) return 'Critical'
  if (percentage >= 75) return 'Warning'
  return 'Safe'
}

const getStatusBadgeClass = (budget) => {
  const percentage = getUsagePercentage(budget)
  if (percentage >= 100) return 'bg-red-100 text-red-800'
  if (percentage >= 90) return 'bg-red-100 text-red-800'
  if (percentage >= 75) return 'bg-yellow-100 text-yellow-800'
  return 'bg-green-100 text-green-800'
}

const addOutletBudget = () => {
  loading.value = true
  
  router.post(`/budget-management/category/${props.category.id}/outlet-budgets`, newBudgetForm, {
    onFinish: () => {
      loading.value = false
      newBudgetForm.outlet_id = ''
      newBudgetForm.allocated_budget = ''
    },
    onError: (errors) => {
      console.error('Form errors:', errors)
    }
  })
}

const bulkCreateBudgets = () => {
  loading.value = true
  
  router.post(`/budget-management/category/${props.category.id}/bulk-create`, bulkCreateForm, {
    onFinish: () => {
      loading.value = false
      showBulkCreateModal.value = false
      bulkCreateForm.default_budget = ''
    },
    onError: (errors) => {
      console.error('Form errors:', errors)
    }
  })
}

const editBudget = (budget) => {
  editingBudget.value = budget
  editForm.allocated_budget = budget.allocated_budget
  showEditModal.value = true
}

const updateBudget = () => {
  loading.value = true
  
  router.put(`/budget-management/category/${props.category.id}/outlet-budgets/${editingBudget.value.id}`, editForm, {
    onFinish: () => {
      loading.value = false
      showEditModal.value = false
      editingBudget.value = null
    },
    onError: (errors) => {
      console.error('Form errors:', errors)
    }
  })
}

const deleteBudget = (budget) => {
  if (confirm('Are you sure you want to delete this outlet budget allocation?')) {
    router.delete(`/budget-management/category/${props.category.id}/outlet-budgets/${budget.id}`)
  }
}

const exportOutletBudgets = () => {
  // TODO: Implement export functionality
  alert('Export functionality will be implemented')
}
</script>
