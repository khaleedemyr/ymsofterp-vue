<template>
  <AppLayout :title="`Edit Category Budget: ${category?.name || 'Loading...'}`">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Edit Category Budget: {{ category?.name || 'Loading...' }}
        </h2>
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
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <!-- Loading State -->
            <div v-if="!category" class="text-center py-8">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
              <p class="mt-2 text-gray-600">Loading category data...</p>
            </div>
            
            <!-- Main Content -->
            <div v-else>
            <!-- Category Info -->
            <div class="mb-6">
              <h3 class="text-lg font-medium text-gray-900 mb-2">Category Information</h3>
              <div class="bg-gray-50 p-4 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Category Name</label>
                    <p class="mt-1 text-sm text-gray-900">{{ category.name }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Division</label>
                    <p class="mt-1 text-sm text-gray-900">{{ category.division }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Subcategory</label>
                    <p class="mt-1 text-sm text-gray-900">{{ category.subcategory }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <p class="mt-1 text-sm text-gray-900">{{ category.description || '-' }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Budget Configuration Form -->
            <form @submit.prevent="submitForm">
              <div class="space-y-6">
                <!-- Budget Type -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Budget Type <span class="text-red-500">*</span>
                  </label>
                  <div class="space-y-3">
                    <div class="flex items-center">
                      <input
                        id="budget_type_global"
                        v-model="form.budget_type"
                        type="radio"
                        value="GLOBAL"
                        class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                      />
                      <label for="budget_type_global" class="ml-3 block text-sm font-medium text-gray-700">
                        <div class="flex items-center">
                          <i class="fa fa-globe text-blue-600 mr-2"></i>
                          <div>
                            <div class="font-medium">Global Budget</div>
                            <div class="text-xs text-gray-500">Budget dihitung total semua outlet dalam kategori</div>
                          </div>
                        </div>
                      </label>
                    </div>
                    <div class="flex items-center">
                      <input
                        id="budget_type_per_outlet"
                        v-model="form.budget_type"
                        type="radio"
                        value="PER_OUTLET"
                        class="focus:ring-orange-500 h-4 w-4 text-orange-600 border-gray-300"
                      />
                      <label for="budget_type_per_outlet" class="ml-3 block text-sm font-medium text-gray-700">
                        <div class="flex items-center">
                          <i class="fa fa-building text-orange-600 mr-2"></i>
                          <div>
                            <div class="font-medium">Per-Outlet Budget</div>
                            <div class="text-xs text-gray-500">Setiap outlet memiliki budget terpisah dalam kategori</div>
                          </div>
                        </div>
                      </label>
                    </div>
                  </div>
                  <div v-if="errors.budget_type" class="mt-1 text-sm text-red-600">
                    {{ errors.budget_type }}
                  </div>
                </div>

                <!-- Budget Limit -->
                <div>
                  <label for="budget_limit" class="block text-sm font-medium text-gray-700 mb-2">
                    Budget Limit <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 sm:text-sm">Rp</span>
                    </div>
                    <input
                      id="budget_limit"
                      v-model="form.budget_limit"
                      type="number"
                      step="0.01"
                      min="0"
                      class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                      placeholder="0.00"
                    />
                  </div>
                  <div v-if="errors.budget_limit" class="mt-1 text-sm text-red-600">
                    {{ errors.budget_limit }}
                  </div>
                  <p class="mt-1 text-xs text-gray-500">
                    {{ form.budget_type === 'GLOBAL' ? 'Total budget untuk semua outlet' : 'Budget referensi untuk alokasi per outlet' }}
                  </p>
                </div>

                <!-- Warning for Budget Type Change -->
                <div v-if="category && form.budget_type !== category.budget_type" class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                  <div class="flex">
                    <div class="flex-shrink-0">
                      <i class="fa fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                      <h3 class="text-sm font-medium text-yellow-800">
                        Budget Type Change Warning
                      </h3>
                      <div class="mt-2 text-sm text-yellow-700">
                        <p v-if="category && category.budget_type === 'GLOBAL' && form.budget_type === 'PER_OUTLET'">
                          Changing from Global to Per-Outlet will require you to set up individual outlet budgets.
                          All existing outlet budget allocations will be removed.
                        </p>
                        <p v-else-if="category && category.budget_type === 'PER_OUTLET' && form.budget_type === 'GLOBAL'">
                          Changing from Per-Outlet to Global will remove all existing outlet budget allocations.
                          The budget will be calculated across all outlets.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Current Outlet Budgets (if PER_OUTLET) -->
                <div v-if="category && category.budget_type === 'PER_OUTLET' && category.outlet_budgets?.length > 0">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Current Outlet Budget Allocations
                  </label>
                  <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="overflow-x-auto">
                      <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                          <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Allocated Budget</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Used Budget</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Remaining</th>
                          </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                          <tr v-for="budget in category.outlet_budgets" :key="budget.id">
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                              {{ budget.outlet?.nama_outlet || 'Unknown Outlet' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                              {{ formatCurrency(budget.allocated_budget) }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                              {{ formatCurrency(budget.used_budget) }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                              {{ formatCurrency(budget.allocated_budget - budget.used_budget) }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="mt-3 text-sm text-gray-600">
                      <strong>Total Allocated:</strong> {{ formatCurrency(getTotalAllocatedBudget()) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Form Actions -->
              <div class="mt-8 flex justify-end space-x-3">
                <Link
                  href="/budget-management"
                  class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
                >
                  Cancel
                </Link>
                <button
                  type="submit"
                  :disabled="loading"
                  class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                >
                  <span v-if="loading">Updating...</span>
                  <span v-else>Update Category</span>
                </button>
              </div>
            </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  category: {
    type: Object,
    default: null
  },
  outlets: {
    type: Array,
    default: () => []
  },
  errors: {
    type: Object,
    default: () => ({})
  },
})

const loading = ref(false)

const form = reactive({
  budget_type: 'GLOBAL',
  budget_limit: 0,
})

// Initialize form when category is loaded
if (props.category) {
  form.budget_type = props.category.budget_type || 'GLOBAL'
  form.budget_limit = props.category.budget_limit || 0
}

// Watch for changes in category prop
watch(() => props.category, (newCategory) => {
  if (newCategory) {
    form.budget_type = newCategory.budget_type || 'GLOBAL'
    form.budget_limit = newCategory.budget_limit || 0
  }
}, { immediate: true })

const getTotalAllocatedBudget = () => {
  if (!props.category?.outlet_budgets) return 0
  return props.category.outlet_budgets.reduce((sum, budget) => sum + parseFloat(budget.allocated_budget), 0)
}

const formatCurrency = (amount) => {
  if (!amount) return 'Rp 0'
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount)
}

const submitForm = () => {
  if (!props.category?.id) {
    console.error('Category ID is missing')
    return
  }
  
  loading.value = true
  
  router.put(`/budget-management/category/${props.category.id}`, form, {
    onFinish: () => {
      loading.value = false
    },
    onError: (errors) => {
      console.error('Form errors:', errors)
    }
  })
}
</script>
