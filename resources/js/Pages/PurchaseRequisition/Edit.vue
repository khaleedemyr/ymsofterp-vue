<template>
  <AppLayout title="Edit Purchase Requisition">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-blue-500"></i> Edit Purchase Requisition
        </h1>
        <div class="flex space-x-2">
          <Link
            :href="`/purchase-requisitions/${purchaseRequisition.id}`"
            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-eye mr-2"></i>
            View
          </Link>
          <Link
            :href="'/purchase-requisitions'"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-arrow-left mr-2"></i>
            Back to List
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Title -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
              <input
                v-model="form.title"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter purchase requisition title"
              />
            </div>

            <!-- Division -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Division *</label>
              <select
                v-model="form.division_id"
                required
                @change="loadBudgetInfo"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Division</option>
                <option v-for="division in divisions" :key="division.id" :value="division.id">
                  {{ division.nama_divisi }}
                </option>
              </select>
            </div>

            <!-- Category -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
              <select
                v-model="form.category_id"
                @change="loadBudgetInfo"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
            </div>

            <!-- Outlet -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <select
                v-model="form.outlet_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
            </div>

            <!-- Ticket -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Related Ticket</label>
              <select
                v-model="form.ticket_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Ticket</option>
                <option v-for="ticket in tickets" :key="ticket.id" :value="ticket.id">
                  {{ ticket.ticket_number }} - {{ ticket.title }} ({{ ticket.outlet?.nama_outlet }})
                </option>
              </select>
            </div>

            <!-- Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Amount *</label>
              <input
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="0.00"
              />
            </div>

            <!-- Currency -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
              <select
                v-model="form.currency"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="IDR">IDR</option>
                <option value="USD">USD</option>
              </select>
            </div>

            <!-- Priority -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
              <select
                v-model="form.priority"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="LOW">Low</option>
                <option value="MEDIUM">Medium</option>
                <option value="HIGH">High</option>
                <option value="URGENT">Urgent</option>
              </select>
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea
                v-model="form.description"
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter description..."
              ></textarea>
            </div>

            <!-- Budget Info -->
            <div v-if="budgetInfo" class="md:col-span-2">
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="text-lg font-medium text-blue-800 mb-2">Category Budget Information</h3>
                
                <!-- Category Budget -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                  <div>
                    <p class="text-sm text-blue-600">Category Budget</p>
                    <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.category_budget) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-blue-600">Used This Month</p>
                    <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.category_used_amount) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-blue-600">Current Input</p>
                    <p class="text-lg font-semibold text-green-800">{{ formatCurrency(budgetInfo.current_amount) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-blue-600">Total After Input</p>
                    <p class="text-lg font-semibold" :class="budgetInfo.exceeds_budget ? 'text-red-800' : 'text-blue-800'">
                      {{ formatCurrency(budgetInfo.total_with_current) }}
                    </p>
                  </div>
                </div>
                
                <!-- Remaining Budget -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p class="text-sm text-blue-600">Remaining Before Input</p>
                    <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.category_remaining_amount) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-blue-600">Remaining After Input</p>
                    <p class="text-lg font-semibold" :class="budgetInfo.remaining_after_current < 0 ? 'text-red-800' : 'text-blue-800'">
                      {{ formatCurrency(budgetInfo.remaining_after_current) }}
                    </p>
                  </div>
                </div>
                
                <!-- Warning Messages -->
                <div v-if="budgetInfo.exceeds_budget" class="mt-4 p-3 bg-red-100 border border-red-300 rounded text-red-800 text-sm">
                  <i class="fa fa-exclamation-triangle mr-2"></i>
                  <strong>Budget Exceeded!</strong> Total amount ({{ formatCurrency(budgetInfo.total_with_current) }}) exceeds category budget limit ({{ formatCurrency(budgetInfo.category_budget) }}) for this month. You cannot save this purchase requisition.
                </div>
                <div v-else-if="budgetInfo.remaining_after_current < (budgetInfo.category_budget * 0.1)" class="mt-4 p-3 bg-yellow-100 border border-yellow-300 rounded text-yellow-800 text-sm">
                  <i class="fa fa-exclamation-circle mr-2"></i>
                  <strong>Budget Warning!</strong> Only {{ formatCurrency(budgetInfo.remaining_after_current) }} remaining after this input.
                </div>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="mt-6 flex justify-end space-x-3">
            <Link
              :href="`/purchase-requisitions/${purchaseRequisition.id}`"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="loading || (budgetInfo && budgetInfo.exceeds_budget)"
              class="px-4 py-2 text-white rounded-md disabled:opacity-50"
              :class="(budgetInfo && budgetInfo.exceeds_budget) ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
            >
              <span v-if="loading">Updating...</span>
              <span v-else-if="budgetInfo && budgetInfo.exceeds_budget">Budget Exceeded - Cannot Save</span>
              <span v-else>Update Purchase Requisition</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  purchaseRequisition: Object,
  categories: Array,
  outlets: Array,
  tickets: Array,
  divisions: Array,
})

const loading = ref(false)
const budgetInfo = ref(null)

const form = reactive({
  title: props.purchaseRequisition.title || '',
  description: props.purchaseRequisition.description || '',
  division_id: props.purchaseRequisition.division_id || '',
  category_id: props.purchaseRequisition.category_id || '',
  outlet_id: props.purchaseRequisition.outlet_id || '',
  ticket_id: props.purchaseRequisition.ticket_id || '',
  amount: props.purchaseRequisition.amount || '',
  currency: props.purchaseRequisition.currency || 'IDR',
  priority: props.purchaseRequisition.priority || 'MEDIUM',
})

const totalAmount = computed(() => {
  return form.amount || 0
})

// Watch totalAmount changes to update budget info
watch(totalAmount, (newTotal) => {
  // Reload budget info when total amount changes
  if (form.category_id) {
    loadBudgetInfo()
  }
})

const loadBudgetInfo = async () => {
  if (!form.category_id) {
    budgetInfo.value = null
    return
  }
  
  try {
    console.log('Loading budget info for category:', form.category_id, 'current amount:', totalAmount.value)
    const response = await axios.get('/purchase-requisitions/budget-info', {
      params: {
        category_id: form.category_id,
        current_amount: totalAmount.value,
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1
      },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    console.log('Budget info response:', response.data)
    
    if (response.data.success) {
      budgetInfo.value = response.data
    } else {
      console.error('Budget info API returned error:', response.data.message)
      budgetInfo.value = null
    }
  } catch (error) {
    console.error('Failed to load budget info:', error)
    console.error('Error details:', error.response?.data)
    budgetInfo.value = null
  }
}

const submitForm = () => {
  loading.value = true
  
  router.put(`/purchase-requisitions/${props.purchaseRequisition.id}`, form, {
    onFinish: () => {
      loading.value = false
    },
    onError: (errors) => {
      console.error('Form errors:', errors)
    }
  })
}

const formatCurrency = (amount) => {
  if (!amount) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount)
}

// Load budget info on mount if division is selected
onMounted(() => {
  if (form.division_id) {
    loadBudgetInfo()
  }
})
</script>