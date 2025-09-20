<template>
  <AppLayout title="Create Purchase Requisition">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-plus text-blue-500"></i> Create Purchase Requisition
        </h1>
        <Link
          :href="'/purchase-requisitions'"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i>
          Back to List
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <!-- Basic Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
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
          </div>

          <!-- Items Section -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Items *</label>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item Name</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                    <th class="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(item, idx) in form.items" :key="idx">
                    <td class="px-3 py-2 min-w-[200px]">
                      <input
                        type="text"
                        v-model="item.item_name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                        placeholder="Enter item name..."
                      />
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <input 
                        type="number" 
                        min="0.01" 
                        step="0.01" 
                        v-model.number="item.qty" 
                        @input="calculateSubtotal(idx)" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        required 
                      />
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <input 
                        type="text" 
                        v-model="item.unit" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        required 
                        placeholder="pcs, kg, etc" 
                      />
                    </td>
                    <td class="px-3 py-2 min-w-[150px]">
                      <input 
                        type="number" 
                        min="0" 
                        step="0.01" 
                        v-model.number="item.unit_price" 
                        @input="calculateSubtotal(idx)" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        required 
                      />
                    </td>
                    <td class="px-3 py-2 min-w-[150px] text-right">
                      {{ formatCurrency(item.subtotal) }}
                    </td>
                    <td class="px-3 py-2">
                      <button 
                        type="button" 
                        @click="removeItem(idx)" 
                        class="text-red-500 hover:text-red-700" 
                        :disabled="form.items.length === 1"
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="4" class="px-3 py-2 text-right font-bold">Total Amount:</td>
                    <td class="px-3 py-2 text-right font-bold">{{ formatCurrency(totalAmount) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div class="mt-2">
              <button type="button" @click="addItem" class="text-blue-500 hover:text-blue-700">
                <i class="fa fa-plus mr-1"></i> Add Item
              </button>
            </div>
          </div>

          <!-- Description -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea
              v-model="form.description"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter description..."
            ></textarea>
          </div>

          <!-- Approval Flow Section -->
          <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Flow</h3>
            <p class="text-sm text-gray-600 mb-4">Add approvers in order from lowest to highest level. The first approver will be the lowest level, and the last approver will be the highest level.</p>
            
            <!-- Add Approver Input -->
            <div class="mb-4">
              <div class="relative">
                <input
                  v-model="approverSearch"
                  type="text"
                  placeholder="Search users by name, email, or jabatan..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
                />
                
                <!-- Dropdown Results -->
                <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                  <div
                    v-for="user in approverResults"
                    :key="user.id"
                    @click="addApprover(user)"
                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                  >
                    <div class="font-medium">{{ user.name }}</div>
                    <div class="text-sm text-gray-600">{{ user.email }}</div>
                    <div v-if="user.jabatan" class="text-xs text-blue-600 font-medium">{{ user.jabatan }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Approvers List -->
            <div v-if="form.approvers.length > 0" class="space-y-2">
              <h4 class="font-medium text-gray-700">Approval Order (Lowest to Highest):</h4>
              <div
                v-for="(approver, index) in form.approvers"
                :key="approver.id"
                class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-md"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="index > 0"
                      @click="reorderApprover(index, index - 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Move Up"
                    >
                      <i class="fa fa-arrow-up"></i>
                    </button>
                    <button
                      v-if="index < form.approvers.length - 1"
                      @click="reorderApprover(index, index + 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Move Down"
                    >
                      <i class="fa fa-arrow-down"></i>
                    </button>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      Level {{ index + 1 }}
                    </span>
                    <div>
                      <div class="font-medium">{{ approver.name }}</div>
                      <div class="text-sm text-gray-600">{{ approver.email }}</div>
                      <div v-if="approver.jabatan" class="text-xs text-blue-600 font-medium">{{ approver.jabatan }}</div>
                    </div>
                  </div>
                </div>
                <button
                  @click="removeApprover(index)"
                  class="p-1 text-red-500 hover:text-red-700"
                  title="Remove Approver"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Budget Info -->
          <div v-if="budgetInfo" class="mb-6">
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

          <!-- Submit Button -->
          <div class="flex justify-end space-x-3">
            <Link
              :href="'/purchase-requisitions'"
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
              <span v-if="loading">Creating...</span>
              <span v-else-if="budgetInfo && budgetInfo.exceeds_budget">Budget Exceeded - Cannot Save</span>
              <span v-else>Create Purchase Requisition</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  categories: Array,
  outlets: Array,
  tickets: Array,
  divisions: Array,
})

const loading = ref(false)
const budgetInfo = ref(null)
const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)

function newItem() {
  return {
    item_name: '',
    qty: '',
    unit: '',
    unit_price: 0,
    subtotal: 0
  }
}

const form = reactive({
  title: '',
  description: '',
  division_id: '',
  category_id: '',
  outlet_id: '',
  ticket_id: '',
  currency: 'IDR',
  priority: 'MEDIUM',
  items: [newItem()],
  approvers: []
})

const totalAmount = computed(() => {
  return form.items.reduce((sum, item) => sum + (item.subtotal || 0), 0)
})

// Watch totalAmount changes to update budget info
watch(totalAmount, (newTotal) => {
  form.amount = newTotal
  // Reload budget info when total amount changes
  if (form.category_id) {
    loadBudgetInfo()
  }
})

function addItem() {
  form.items.push(newItem())
}

function removeItem(idx) {
  if (form.items.length === 1) return
  form.items.splice(idx, 1)
}

function calculateSubtotal(idx) {
  const item = form.items[idx]
  item.subtotal = (item.qty || 0) * (item.unit_price || 0)
}

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

const loadApprovers = async (search = '') => {
  try {
    const response = await axios.get('/purchase-requisitions/approvers', {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    if (response.data.success) {
      approverResults.value = response.data.users
      showApproverDropdown.value = true
    }
  } catch (error) {
    console.error('Failed to load approvers:', error)
    approverResults.value = []
  }
}

const addApprover = (user) => {
  // Check if user already exists
  if (!form.approvers.find(approver => approver.id === user.id)) {
    form.approvers.push(user)
  }
  approverSearch.value = ''
  showApproverDropdown.value = false
}

const removeApprover = (index) => {
  form.approvers.splice(index, 1)
}

const reorderApprover = (fromIndex, toIndex) => {
  const approver = form.approvers.splice(fromIndex, 1)[0]
  form.approvers.splice(toIndex, 0, approver)
}

const submitForm = () => {
  loading.value = true
  
  // Calculate total amount from items
  form.amount = totalAmount.value
  
  // Prepare form data with approver IDs only
  const formData = {
    ...form,
    approvers: form.approvers.map(approver => approver.id)
  }
  
  router.post('/purchase-requisitions', formData, {
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

// Watch approver search
watch(approverSearch, (newSearch) => {
  if (newSearch.length >= 2) {
    loadApprovers(newSearch)
  } else {
    showApproverDropdown.value = false
    approverResults.value = []
  }
})
</script>