<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, reactive, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  availablePOs: Array,
});

const loading = ref(false);
const selectedPO = ref(null);

const form = reactive({
  purchase_requisition_id: '',
  purchase_order_id: '',
  supplier_id: '',
  amount: '',
  payment_method: '',
  payment_date: new Date().toISOString().split('T')[0],
  description: '',
  reference_number: '',
});

const onPOChange = () => {
  if (form.purchase_order_id) {
    selectedPO.value = props.availablePOs.find(po => po.id == form.purchase_order_id);
    if (selectedPO.value) {
      form.purchase_requisition_id = selectedPO.value.source_pr?.id;
      form.supplier_id = selectedPO.value.supplier_id;
      form.amount = selectedPO.value.grand_total;
    }
  } else {
    selectedPO.value = null;
    form.purchase_requisition_id = '';
    form.supplier_id = '';
    form.amount = '';
  }
};

const submitForm = () => {
  loading.value = true;
  
  router.post(route('payments.store'), form, {
    onFinish: () => {
      loading.value = false;
    },
  });
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
};
</script>

<template>
  <AppLayout>
    <div class="p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Create Payment</h1>
          <p class="text-gray-600">Create a new payment for approved Purchase Requisition</p>
        </div>
        <Link
          :href="route('payments.index')"
          class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <i class="fas fa-arrow-left"></i>
          Back to Payments
        </Link>
      </div>

      <form @submit.prevent="submitForm" class="max-w-4xl">
        <div class="bg-white rounded-lg shadow-sm border p-6">
          <!-- PO Selection -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Purchase Order <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.purchase_order_id"
              @change="onPOChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="">Select Purchase Order</option>
              <option
                v-for="po in availablePOs"
                :key="po.id"
                :value="po.id"
              >
                {{ po.number }} - {{ po.supplier?.name }} ({{ po.source_pr?.division?.nama_divisi }})
              </option>
            </select>
          </div>

          <!-- Selected PO Info -->
          <div v-if="selectedPO" class="mb-6 p-4 bg-blue-50 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Selected Purchase Order</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-600">PO Number:</p>
                <p class="font-medium">{{ selectedPO.number }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">PR Number:</p>
                <p class="font-medium">{{ selectedPO.source_pr?.pr_number }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Division:</p>
                <p class="font-medium">{{ selectedPO.source_pr?.division?.nama_divisi }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Supplier:</p>
                <p class="font-medium">{{ selectedPO.supplier?.name }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">PO Total Amount:</p>
                <p class="font-medium">{{ formatCurrency(selectedPO.grand_total) }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">PO Status:</p>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                  {{ selectedPO.status }}
                </span>
              </div>
            </div>
          </div>

          <!-- Payment Details -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Supplier
              </label>
              <input
                :value="selectedPO?.supplier?.name || ''"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-100"
                readonly
              />
            </div>
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
              :href="route('payments.index')"
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
              {{ loading ? 'Creating...' : 'Create Payment' }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>