<script setup>
import { ref, onMounted, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  po: Object,
  suppliers: Array,
  budgetInfo: Object,
});

const form = useForm({
  notes: props.po.notes || '',
  ppn_enabled: props.po.ppn_enabled || false,
  items: props.po.items.map(item => ({
    id: item.id,
    price: item.price,
    total: item.total,
  })),
  new_items: [],
  deleted_items: [],
});

const newItem = ref({
  item_name: '',
  quantity: 0,
  unit: '',
  price: 0,
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
};

const calculateItemTotal = (item) => {
  if (item.price && item.quantity) {
    return parseFloat(item.price) * parseFloat(item.quantity);
  }
  return 0;
};

const grandTotal = computed(() => {
  let total = 0;
  
  // Calculate from existing items
  form.items.forEach(item => {
    if (item.price && item.quantity) {
      total += parseFloat(item.price) * parseFloat(item.quantity);
    }
  });
  
  // Calculate from new items
  form.new_items.forEach(item => {
    if (item.price && item.quantity) {
      total += parseFloat(item.price) * parseFloat(item.quantity);
    }
  });
  
  // Add PPN if enabled
  if (form.ppn_enabled) {
    total += total * 0.11; // 11% PPN
  }
  
  return total;
});

const addNewItem = () => {
  if (newItem.value.item_name && newItem.value.quantity && newItem.value.unit && newItem.value.price) {
    form.new_items.push({
      item_name: newItem.value.item_name,
      quantity: parseFloat(newItem.value.quantity),
      unit: newItem.value.unit,
      price: parseFloat(newItem.value.price),
    });
    
    // Reset form
    newItem.value = {
      item_name: '',
      quantity: 0,
      unit: '',
      price: 0,
    };
  } else {
    Swal.fire('Error', 'Please fill all fields for new item', 'error');
  }
};

const removeNewItem = (index) => {
  form.new_items.splice(index, 1);
};

const removeExistingItem = (itemId) => {
  form.deleted_items.push(itemId);
  form.items = form.items.filter(item => item.id !== itemId);
};

const submitForm = async () => {
  try {
    const response = await axios.put(`/po-ops/${props.po.id}`, form.data());
    
    if (response.data.success) {
      Swal.fire('Success', 'PO updated successfully!', 'success');
      window.location.href = `/po-ops/${props.po.id}`;
    } else {
      Swal.fire('Error', response.data.message || 'Failed to update PO', 'error');
    }
  } catch (error) {
    console.error('Error updating PO:', error);
    Swal.fire('Error', error.response?.data?.message || 'Failed to update PO', 'error');
  }
};

// Helper functions for budget calculations
function getTotalBudget() {
  if (!props.budgetInfo) return 0
  return props.budgetInfo.budget_type === 'PER_OUTLET' 
    ? props.budgetInfo.outlet_budget 
    : props.budgetInfo.category_budget
}

function getUsedAmount() {
  if (!props.budgetInfo) return 0
  return props.budgetInfo.budget_type === 'PER_OUTLET' 
    ? props.budgetInfo.outlet_used_amount 
    : props.budgetInfo.category_used_amount
}

function getRemainingAmount() {
  if (!props.budgetInfo) return 0
  return props.budgetInfo.budget_type === 'PER_OUTLET' 
    ? props.budgetInfo.outlet_remaining_amount 
    : props.budgetInfo.category_remaining_amount
}

function getUsagePercentage() {
  const used = getUsedAmount()
  const total = getTotalBudget()
  if (total === 0) return 0
  return (used / total) * 100
}

function getBudgetProgressColor(usedAmount, totalBudget) {
  const percentage = (usedAmount / totalBudget) * 100
  if (percentage >= 100) return 'bg-red-500'
  if (percentage >= 80) return 'bg-yellow-500'
  if (percentage >= 60) return 'bg-orange-500'
  return 'bg-green-500'
}

function getMonthName(monthNumber) {
  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ]
  return months[monthNumber - 1] || 'Unknown'
}
</script>

<template>
  <AppLayout title="Edit Purchase Order Ops">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-blue-500"></i> Edit Purchase Order: {{ po.number }}
        </h1>
        <div class="flex space-x-2">
          <Link
            :href="`/po-ops/${po.id}`"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Detail
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <!-- PPN Toggle -->
          <div class="mb-6">
            <label class="flex items-center">
              <input
                type="checkbox"
                v-model="form.ppn_enabled"
                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
              />
              <span class="ml-2 text-sm text-gray-700">Enable PPN (11%)</span>
            </label>
          </div>

          <!-- Budget Information -->
          <div v-if="budgetInfo" class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
              <i class="fa fa-chart-pie mr-2 text-blue-500"></i>
              {{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Informasi Budget Outlet' : 'Informasi Budget Category' }} - {{ getMonthName(budgetInfo.current_month) }} {{ budgetInfo.current_year }}
              <span class="ml-2 text-sm font-normal text-gray-600">
                ({{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Per Outlet' : 'Global' }})
              </span>
            </h3>
            
            <!-- Outlet Info for PER_OUTLET -->
            <div v-if="budgetInfo.budget_type === 'PER_OUTLET' && budgetInfo.outlet_info" class="mb-4 p-3 bg-blue-100 border border-blue-200 rounded-lg">
              <p class="text-sm text-blue-600">
                <i class="fa fa-store mr-2"></i>
                <strong>Outlet:</strong> {{ budgetInfo.outlet_info.name }}
              </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="bg-white p-3 rounded-lg border border-blue-200">
                <div class="text-sm font-medium text-blue-600">
                  {{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Outlet Budget' : 'Total Budget' }}
                </div>
                <div class="text-lg font-bold text-blue-800">
                  {{ formatCurrency(budgetInfo.budget_type === 'PER_OUTLET' ? budgetInfo.outlet_budget : budgetInfo.category_budget) }}
                </div>
                <div v-if="budgetInfo.budget_type === 'PER_OUTLET'" class="text-xs text-gray-500 mt-1">
                  Global: {{ formatCurrency(budgetInfo.category_budget) }}
                </div>
              </div>
              <div class="bg-white p-3 rounded-lg border border-orange-200">
                <div class="text-sm font-medium text-orange-600">Used This Month</div>
                <div class="text-lg font-bold text-orange-800">
                  {{ formatCurrency(budgetInfo.budget_type === 'PER_OUTLET' ? budgetInfo.outlet_used_amount : budgetInfo.category_used_amount) }}
                </div>
              </div>
              <div class="bg-white p-3 rounded-lg border border-green-200">
                <div class="text-sm font-medium text-green-600">Remaining Budget</div>
                <div class="text-lg font-bold" :class="getRemainingAmount() < 0 ? 'text-red-800' : 'text-green-800'">
                  {{ formatCurrency(getRemainingAmount()) }}
                </div>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex justify-between text-sm text-gray-600 mb-2">
                <span>Budget Usage</span>
                <span>{{ Math.round(getUsagePercentage()) }}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="h-3 rounded-full transition-all duration-300"
                     :class="getBudgetProgressColor(getUsedAmount(), getTotalBudget())"
                     :style="{ width: Math.min(getUsagePercentage(), 100) + '%' }">
                </div>
              </div>
            </div>

            <!-- Budget Breakdown Detail -->
            <div v-if="budgetInfo.breakdown" class="mt-4 pt-4 border-t border-gray-200">
              <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa fa-list-ul text-blue-500"></i>Budget Breakdown Detail
              </h5>
              <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="p-3 bg-white rounded-lg border border-blue-100 shadow-sm">
                  <p class="text-blue-600 font-medium text-xs mb-1">PR Unpaid</p>
                  <p class="text-base font-bold text-blue-800">{{ formatCurrency(budgetInfo.breakdown.pr_unpaid || 0) }}</p>
                  <p class="text-xs text-gray-500 mt-1">PR Submitted & Approved<br>yang belum dibuat PO</p>
                </div>
                <div class="p-3 bg-white rounded-lg border border-blue-100 shadow-sm">
                  <p class="text-blue-600 font-medium text-xs mb-1">PO Unpaid</p>
                  <p class="text-base font-bold text-blue-800">{{ formatCurrency(budgetInfo.breakdown.po_unpaid || 0) }}</p>
                  <p class="text-xs text-gray-500 mt-1">PO Submitted & Approved<br>yang belum dibuat NFP</p>
                </div>
                <div class="p-3 bg-white rounded-lg border border-orange-100 shadow-sm">
                  <p class="text-orange-600 font-medium text-xs mb-1">NFP Submitted</p>
                  <p class="text-base font-bold text-orange-600">{{ formatCurrency(budgetInfo.breakdown.nfp_submitted || 0) }}</p>
                </div>
                <div class="p-3 bg-white rounded-lg border border-yellow-100 shadow-sm">
                  <p class="text-yellow-600 font-medium text-xs mb-1">NFP Approved</p>
                  <p class="text-base font-bold text-yellow-600">{{ formatCurrency(budgetInfo.breakdown.nfp_approved || 0) }}</p>
                </div>
                <div class="p-3 bg-white rounded-lg border border-green-100 shadow-sm">
                  <p class="text-green-600 font-medium text-xs mb-1">NFP Paid</p>
                  <p class="text-base font-bold text-green-600">{{ formatCurrency(budgetInfo.breakdown.nfp_paid || 0) }}</p>
                </div>
                <div class="p-3 bg-white rounded-lg border border-purple-100 shadow-sm">
                  <p class="text-purple-600 font-medium text-xs mb-1">Retail Non Food</p>
                  <p class="text-base font-bold text-purple-600">{{ formatCurrency(budgetInfo.breakdown.retail_non_food || 0) }}</p>
                  <p class="text-xs text-gray-500 mt-1">Status: Approved</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea
              v-model="form.notes"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter notes..."
            ></textarea>
          </div>

          <!-- Existing Items -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Existing Items</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="item in form.items" :key="item.id">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.item_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.quantity }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <input
                        type="number"
                        v-model.number="item.price"
                        min="0"
                        step="0.01"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                      {{ formatCurrency(calculateItemTotal(item)) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                      <button
                        type="button"
                        @click="removeExistingItem(item.id)"
                        class="text-red-600 hover:text-red-900"
                      >
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Add New Items -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Items</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                <input
                  type="text"
                  v-model="newItem.item_name"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter item name"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input
                  type="number"
                  v-model.number="newItem.quantity"
                  min="0"
                  step="0.01"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                <input
                  type="text"
                  v-model="newItem.unit"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="e.g., pcs, kg"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                <input
                  type="number"
                  v-model.number="newItem.price"
                  min="0"
                  step="0.01"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div class="flex items-end">
                <button
                  type="button"
                  @click="addNewItem"
                  class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Add Item
                </button>
              </div>
            </div>

            <!-- New Items List -->
            <div v-if="form.new_items.length > 0" class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(item, index) in form.new_items" :key="index">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.item_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.quantity }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(item.price) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatCurrency(calculateItemTotal(item)) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                      <button
                        type="button"
                        @click="removeNewItem(index)"
                        class="text-red-600 hover:text-red-900"
                      >
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Grand Total -->
          <div class="mt-6 p-4 bg-gray-50 rounded-lg">
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold">Grand Total:</span>
              <span class="text-xl font-bold text-blue-600">
                {{ formatCurrency(grandTotal) }}
              </span>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="mt-6 flex justify-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="form.processing" class="fas fa-spinner fa-spin mr-2"></i>
              Update Purchase Order
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
