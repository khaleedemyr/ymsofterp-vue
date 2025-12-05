<template>
  <AppLayout title="Budget Management">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Budget Management
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fa fa-chart-pie text-blue-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-500">Total Categories</p>
                  <p class="text-2xl font-semibold text-gray-900">{{ categories.length }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fa fa-globe text-green-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-500">Global Budgets</p>
                  <p class="text-2xl font-semibold text-gray-900">{{ globalCategoriesCount }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fa fa-building text-orange-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-500">Per-Outlet Budgets</p>
                  <p class="text-2xl font-semibold text-gray-900">{{ perOutletCategoriesCount }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fa fa-store text-purple-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-500">Total Outlets</p>
                  <p class="text-2xl font-semibold text-gray-900">{{ outlets.length }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Categories by Division -->
        <div v-for="(divisionCategories, division) in categoriesByDivision" :key="division" class="mb-8">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">{{ division }}</h3>
                <span class="text-sm text-gray-500">{{ divisionCategories.length }} categories</span>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Category Name
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Subcategory
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Budget Type
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Budget Limit
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Outlet Allocations
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="category in divisionCategories" :key="category.id">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ category.name }}</div>
                        <div class="text-sm text-gray-500">{{ category.description }}</div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ category.subcategory }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span :class="getBudgetTypeBadgeClass(category.budget_type)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                          {{ getBudgetTypeLabel(category.budget_type) }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ formatCurrency(category.budget_limit) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div v-if="category.budget_type === 'PER_OUTLET'">
                          <span class="text-green-600 font-medium">{{ category.outlet_budgets?.length || 0 }} outlets</span>
                          <div class="text-xs text-gray-500">
                            Total: {{ formatCurrency(getTotalOutletBudget(category)) }}
                          </div>
                        </div>
                        <span v-else class="text-gray-500">-</span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                          <Link
                            :href="`/budget-management/category/${category.id}/edit`"
                            class="text-blue-600 hover:text-blue-900"
                          >
                            <i class="fa fa-edit"></i> Edit
                          </Link>
                          <Link
                            v-if="category.budget_type === 'PER_OUTLET'"
                            :href="`/budget-management/category/${category.id}/outlet-budgets`"
                            class="text-green-600 hover:text-green-900"
                          >
                            <i class="fa fa-cog"></i> Manage
                          </Link>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
              <Link
                href="/budget-management/category/create"
                class="flex items-center justify-center px-4 py-2 border border-green-300 rounded-md shadow-sm text-sm font-medium text-green-700 bg-green-50 hover:bg-green-100"
              >
                <i class="fa fa-plus mr-2"></i>
                Create Category
              </Link>
              <button
                @click="showDeleteModal = true"
                class="flex items-center justify-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100"
              >
                <i class="fa fa-trash mr-2"></i>
                Delete Category
              </button>
              <button
                @click="refreshData"
                class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
              >
                <i class="fa fa-refresh mr-2"></i>
                Refresh Data
              </button>
              <Link
                href="/budget-management/budget-summary"
                class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
              >
                <i class="fa fa-chart-bar mr-2"></i>
                View Summary
              </Link>
              <button
                @click="exportData"
                class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
              >
                <i class="fa fa-download mr-2"></i>
                Export Data
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Category Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Budget Category</h3>
          <p class="text-sm text-gray-600 mb-4">
            Select a category to delete. Note: Categories with existing purchase requisitions cannot be deleted.
          </p>
          <div class="mb-4">
            <label for="category_to_delete" class="block text-sm font-medium text-gray-700 mb-2">
              Select Category to Delete
            </label>
            <select
              id="category_to_delete"
              v-model="selectedCategoryToDelete"
              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              <option value="">Select a category</option>
              <option
                v-for="category in categories"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }} ({{ category.division }})
              </option>
            </select>
          </div>
          <div class="flex justify-end space-x-3">
            <button
              @click="showDeleteModal = false; selectedCategoryToDelete = ''"
              class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
            >
              Cancel
            </button>
            <button
              @click="deleteCategory"
              :disabled="!selectedCategoryToDelete || deleting"
              class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
            >
              <span v-if="deleting">Deleting...</span>
              <span v-else>Delete Category</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  categories: Array,
  categoriesByDivision: Object,
  outlets: Array,
})

const globalCategoriesCount = computed(() => {
  return props.categories.filter(cat => cat.budget_type === 'GLOBAL').length
})

const perOutletCategoriesCount = computed(() => {
  return props.categories.filter(cat => cat.budget_type === 'PER_OUTLET').length
})

const getBudgetTypeLabel = (budgetType) => {
  return budgetType === 'GLOBAL' ? 'Global' : 'Per Outlet'
}

const getBudgetTypeBadgeClass = (budgetType) => {
  return budgetType === 'GLOBAL' 
    ? 'bg-blue-100 text-blue-800' 
    : 'bg-orange-100 text-orange-800'
}

const getTotalOutletBudget = (category) => {
  if (!category.outlet_budgets) return 0
  return category.outlet_budgets.reduce((sum, budget) => sum + parseFloat(budget.allocated_budget), 0)
}

const formatCurrency = (amount) => {
  if (!amount) return 'Rp 0'
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount)
}

const refreshData = () => {
  router.reload()
}

const showDeleteModal = ref(false)
const selectedCategoryToDelete = ref('')
const deleting = ref(false)

const deleteCategory = () => {
  if (!selectedCategoryToDelete.value) return
  
  deleting.value = true
  
  router.delete(`/budget-management/category/${selectedCategoryToDelete.value}`, {
    onFinish: () => {
      deleting.value = false
      showDeleteModal.value = false
      selectedCategoryToDelete.value = ''
    },
    onError: (errors) => {
      console.error('Delete errors:', errors)
    }
  })
}

const exportData = () => {
  // TODO: Implement export functionality
  alert('Export functionality will be implemented')
}
</script>
