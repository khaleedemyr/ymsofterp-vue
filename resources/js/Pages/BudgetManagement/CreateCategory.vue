<template>
  <AppLayout title="Create Budget Category">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Create Budget Category
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
            <form @submit.prevent="submitForm">
              <div class="space-y-6">
                <!-- Category Name -->
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                    Category Name <span class="text-red-500">*</span>
                  </label>
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="Enter category name"
                    required
                  />
                  <div v-if="errors.name" class="mt-1 text-sm text-red-600">
                    {{ errors.name }}
                  </div>
                </div>

                <!-- Division -->
                <div>
                  <label for="division" class="block text-sm font-medium text-gray-700 mb-2">
                    Division <span class="text-red-500">*</span>
                  </label>
                  <select
                    id="division"
                    v-model="form.division"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    required
                  >
                    <option value="">Select division</option>
                    <option value="GENERAL">General</option>
                    <option value="MARKETING">Marketing</option>
                    <option value="MAINTENANCE">Maintenance</option>
                    <option value="ASSET">Asset</option>
                    <option value="PROJECT_ENHANCEMENT">Project Enhancement</option>
                  </select>
                  <div v-if="errors.division" class="mt-1 text-sm text-red-600">
                    {{ errors.division }}
                  </div>
                </div>

                <!-- Subcategory -->
                <div>
                  <label for="subcategory" class="block text-sm font-medium text-gray-700 mb-2">
                    Subcategory <span class="text-red-500">*</span>
                  </label>
                  <input
                    id="subcategory"
                    v-model="form.subcategory"
                    type="text"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="Enter subcategory"
                    required
                  />
                  <div v-if="errors.subcategory" class="mt-1 text-sm text-red-600">
                    {{ errors.subcategory }}
                  </div>
                </div>

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
                      required
                    />
                  </div>
                  <div v-if="errors.budget_limit" class="mt-1 text-sm text-red-600">
                    {{ errors.budget_limit }}
                  </div>
                  <p class="mt-1 text-xs text-gray-500">
                    {{ form.budget_type === 'GLOBAL' ? 'Total budget untuk semua outlet' : 'Budget referensi untuk alokasi per outlet' }}
                  </p>
                </div>

                <!-- Outlet Selection (only for PER_OUTLET) -->
                <div v-if="form.budget_type === 'PER_OUTLET'">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Select Outlets <span class="text-red-500">*</span>
                  </label>
                  <div class="border border-gray-300 rounded-md p-4 max-h-60 overflow-y-auto">
                    <div class="space-y-2">
                      <div class="flex items-center">
                        <input
                          id="select_all_outlets"
                          v-model="selectAllOutlets"
                          type="checkbox"
                          @change="toggleAllOutlets"
                          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label for="select_all_outlets" class="ml-2 block text-sm font-medium text-gray-700">
                          Select All Outlets
                        </label>
                      </div>
                      <hr class="my-2">
                      <div
                        v-for="outlet in outlets"
                        :key="outlet.id_outlet"
                        class="flex items-center"
                      >
                        <input
                          :id="`outlet_${outlet.id_outlet}`"
                          v-model="form.selected_outlets"
                          :value="outlet.id_outlet"
                          type="checkbox"
                          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label :for="`outlet_${outlet.id_outlet}`" class="ml-2 block text-sm text-gray-700">
                          {{ outlet.nama_outlet }}
                        </label>
                      </div>
                    </div>
                  </div>
                  <div v-if="errors.selected_outlets" class="mt-1 text-sm text-red-600">
                    {{ errors.selected_outlets }}
                  </div>
                  <p class="mt-1 text-xs text-gray-500">
                    Select outlets that will have individual budget allocations for this category.
                  </p>
                </div>

                <!-- Individual Budget per Outlet (only for PER_OUTLET) -->
                <div v-if="form.budget_type === 'PER_OUTLET' && form.selected_outlets.length > 0">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Budget Allocation per Outlet <span class="text-red-500">*</span>
                  </label>
                  <div class="space-y-3">
                    <div
                      v-for="outletId in form.selected_outlets"
                      :key="outletId"
                      class="flex items-center space-x-3 p-3 border border-gray-200 rounded-lg"
                    >
                      <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700">
                          {{ getOutletName(outletId) }}
                        </label>
                      </div>
                      <div class="flex-1">
                        <div class="relative">
                          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">Rp</span>
                          </div>
                          <input
                            :id="`budget_${outletId}`"
                            v-model="form.outlet_budgets[outletId]"
                            type="number"
                            step="0.01"
                            min="0"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="0.00"
                            required
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-if="errors.outlet_budgets" class="mt-1 text-sm text-red-600">
                    {{ errors.outlet_budgets }}
                  </div>
                  <p class="mt-1 text-xs text-gray-500">
                    Set individual budget allocation for each selected outlet.
                  </p>
                </div>

                <!-- Description -->
                <div>
                  <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                  </label>
                  <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="Enter description (optional)"
                  ></textarea>
                  <div v-if="errors.description" class="mt-1 text-sm text-red-600">
                    {{ errors.description }}
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
                  <span v-if="loading">Creating...</span>
                  <span v-else>Create Category</span>
                </button>
              </div>
            </form>
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
  divisions: Array,
  outlets: Array,
  errors: Object,
})

const loading = ref(false)
const selectAllOutlets = ref(false)

const form = reactive({
  name: '',
  division: '',
  subcategory: '',
  budget_type: 'GLOBAL',
  budget_limit: '',
  description: '',
  selected_outlets: [],
  outlet_budgets: {},
})

const getOutletName = (outletId) => {
  const outlet = props.outlets.find(o => o.id_outlet == outletId)
  return outlet ? outlet.nama_outlet : `Outlet ${outletId}`
}

const toggleAllOutlets = () => {
  if (selectAllOutlets.value) {
    form.selected_outlets = props.outlets.map(outlet => outlet.id_outlet)
    // Initialize outlet budgets
    props.outlets.forEach(outlet => {
      form.outlet_budgets[outlet.id_outlet] = ''
    })
  } else {
    form.selected_outlets = []
    form.outlet_budgets = {}
  }
}

// Watch for changes in selected_outlets to update selectAllOutlets
watch(() => form.selected_outlets, (newValue) => {
  selectAllOutlets.value = newValue.length === props.outlets.length && props.outlets.length > 0
  
  // Initialize outlet budgets for newly selected outlets
  newValue.forEach(outletId => {
    if (!(outletId in form.outlet_budgets)) {
      form.outlet_budgets[outletId] = ''
    }
  })
  
  // Remove outlet budgets for unselected outlets
  Object.keys(form.outlet_budgets).forEach(outletId => {
    if (!newValue.includes(parseInt(outletId))) {
      delete form.outlet_budgets[outletId]
    }
  })
}, { deep: true })

const submitForm = () => {
  // Frontend validation
  if (form.budget_type === 'PER_OUTLET') {
    if (!form.selected_outlets || form.selected_outlets.length === 0) {
      alert('Please select at least one outlet for per-outlet budget type.')
      return
    }
    
    // Validate individual outlet budgets
    for (const outletId of form.selected_outlets) {
      const budget = form.outlet_budgets[outletId]
      if (!budget || budget <= 0) {
        alert(`Please enter a valid budget amount for ${getOutletName(outletId)}.`)
        return
      }
    }
  }
  
  loading.value = true
  
  router.post('/budget-management/category', form, {
    onFinish: () => {
      loading.value = false
    },
    onError: (errors) => {
      // Handle form errors
    }
  })
}
</script>
