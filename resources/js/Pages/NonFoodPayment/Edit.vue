<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-pencil-alt"></i> Edit Non Food Payment
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-1"></i> Kembali
        </button>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Payment Information -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Payment</h2>
          
          <!-- Payment Number (Read-only) -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Number</label>
            <p class="text-lg font-semibold text-gray-900">{{ payment.payment_number }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Supplier -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Supplier *</label>
              <select 
                v-model="form.supplier_id" 
                required 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              >
                <option value="">Pilih Supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
            </div>

            <!-- Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Amount * 
                <span class="text-xs font-normal text-gray-500">(Dapat diubah)</span>
              </label>
              <input 
                type="number" 
                v-model="form.amount" 
                step="0.01"
                min="0"
                required 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                placeholder="0.00"
              />
            </div>

            <!-- Payment Method -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
              <select v-model="form.payment_method" required class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                <option value="">Pilih Payment Method</option>
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="check">Check</option>
              </select>
            </div>

            <!-- Payment Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Payment Date *</label>
              <input 
                type="date" 
                v-model="form.payment_date" 
                required 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>

            <!-- Due Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
              <input 
                type="date" 
                v-model="form.due_date" 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>

            <!-- Reference Number -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
              <input 
                type="text" 
                v-model="form.reference_number" 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                placeholder="Nomor referensi"
              />
            </div>
          </div>

          <!-- Description -->
          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea 
              v-model="form.description" 
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              placeholder="Deskripsi payment"
            ></textarea>
          </div>

          <!-- Notes -->
          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea 
              v-model="form.notes" 
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              placeholder="Catatan tambahan"
            ></textarea>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-4">
          <button type="button" @click="goBack" class="bg-gray-500 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            Batal
          </button>
          <button type="submit" :disabled="isSubmitting" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold disabled:opacity-50">
            <i v-if="isSubmitting" class="fa fa-spinner fa-spin mr-2"></i>
            {{ isSubmitting ? 'Menyimpan...' : 'Update Payment' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  payment: Object,
  suppliers: Array
});

const isSubmitting = ref(false);

const form = reactive({
  supplier_id: '',
  amount: '',
  payment_method: '',
  payment_date: '',
  due_date: '',
  description: '',
  reference_number: '',
  notes: ''
});

onMounted(() => {
  // Pre-fill form with existing payment data
  if (props.payment) {
    form.supplier_id = props.payment.supplier_id || '';
    form.amount = props.payment.amount || '';
    form.payment_method = props.payment.payment_method || '';
    form.payment_date = props.payment.payment_date ? new Date(props.payment.payment_date).toISOString().split('T')[0] : '';
    form.due_date = props.payment.due_date ? new Date(props.payment.due_date).toISOString().split('T')[0] : '';
    form.description = props.payment.description || '';
    form.reference_number = props.payment.reference_number || '';
    form.notes = props.payment.notes || '';
  }
});

function goBack() {
  router.visit(`/non-food-payments/${props.payment.id}`);
}

function submitForm() {
  isSubmitting.value = true;
  
  router.put(`/non-food-payments/${props.payment.id}`, form, {
    onSuccess: () => {
      isSubmitting.value = false;
    },
    onError: (errors) => {
      isSubmitting.value = false;
      console.error('Validation errors:', errors);
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}
</script>

