<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  payment: Object,
});

const loading = ref(false);

const form = reactive({
  amount: props.payment.amount,
  payment_method: props.payment.payment_method,
  payment_date: props.payment.payment_date,
  due_date: props.payment.due_date || '',
  description: props.payment.description || '',
  reference_number: props.payment.reference_number || '',
});

const submitForm = () => {
  loading.value = true;
  
  router.put(route('payments.update', props.payment.id), form, {
    onFinish: () => {
      loading.value = false;
    },
  });
};

const getStatusColor = (status) => {
  const colors = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    paid: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
  <AppLayout>
    <div class="p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Edit Payment</h1>
          <p class="text-gray-600">Payment Number: {{ payment.payment_number }}</p>
        </div>
        <Link
          :href="route('payments.show', payment.id)"
          class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <i class="fas fa-arrow-left"></i>
          Back to Payment Details
        </Link>
      </div>

      <form @submit.prevent="submitForm" class="max-w-4xl">
        <div class="bg-white rounded-lg shadow-sm border p-6">
          <!-- Payment Info (Read Only) -->
          <div class="mb-6 p-4 bg-gray-50 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Payment Information (Read Only)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-600">Payment Number:</p>
                <p class="font-medium">{{ payment.payment_number }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Purchase Requisition:</p>
                <p class="font-medium">{{ payment.purchase_requisition?.number }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Supplier:</p>
                <p class="font-medium">{{ payment.supplier?.nama_supplier }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Status:</p>
                <span :class="getStatusColor(payment.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                  {{ payment.status }}
                </span>
              </div>
            </div>
          </div>

          <!-- Editable Fields -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Amount <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Payment Method <span class="text-red-500">*</span>
              </label>
              <select
                v-model="form.payment_method"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="">Select Payment Method</option>
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="check">Check</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Payment Date <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.payment_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Due Date
              </label>
              <input
                v-model="form.due_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Reference Number
            </label>
            <input
              v-model="form.reference_number"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter reference number (optional)"
            />
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Description
            </label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter payment description (optional)"
            ></textarea>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end space-x-4">
            <Link
              :href="route('payments.show', payment.id)"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="loading"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
            >
              <i v-if="loading" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-save"></i>
              {{ loading ? 'Updating...' : 'Update Payment' }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>