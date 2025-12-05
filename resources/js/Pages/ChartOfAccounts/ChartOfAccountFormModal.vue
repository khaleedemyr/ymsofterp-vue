<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  chartOfAccount: Object,
  parents: Array,
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  code: '',
  name: '',
  type: 'Asset',
  parent_id: null,
  description: '',
  is_active: true,
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.chartOfAccount) {
    form.code = props.chartOfAccount.code || '';
    form.name = props.chartOfAccount.name || '';
    form.type = props.chartOfAccount.type || 'Asset';
    form.parent_id = props.chartOfAccount.parent_id || null;
    form.description = props.chartOfAccount.description || '';
    form.is_active = props.chartOfAccount.is_active == 1;
  } else if (val && props.mode === 'create') {
    form.reset();
    form.type = 'Asset';
    // Jika ada parent_id dari props (untuk create child), set parent_id
    form.parent_id = props.chartOfAccount?.parent_id || null;
    form.is_active = true;
  }
});

const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  
  if (props.mode === 'create') {
    form.post('/chart-of-accounts', {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Chart of Account berhasil dibuat!', 'success');
        emit('success');
      },
      onError: (errors) => {
        Swal.fire('Error', Object.values(errors).flat().join(', '), 'error');
      },
      onFinish: () => {
        isSubmitting.value = false;
      },
    });
  } else {
    form.put(`/chart-of-accounts/${props.chartOfAccount.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Chart of Account berhasil diperbarui!', 'success');
        emit('success');
      },
      onError: (errors) => {
        Swal.fire('Error', Object.values(errors).flat().join(', '), 'error');
      },
      onFinish: () => {
        isSubmitting.value = false;
      },
    });
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
      <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-900">
          {{ mode === 'create' ? 'Buat Chart of Account Baru' : 'Edit Chart of Account' }}
        </h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
          <i class="fa-solid fa-times text-xl"></i>
        </button>
      </div>
      
      <form @submit.prevent="submit" class="p-6">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Code *</label>
            <input
              v-model="form.code"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Contoh: 1000"
            />
            <div v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Nama Chart of Account"
            />
            <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
            <select
              v-model="form.type"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="Asset">Asset</option>
              <option value="Liability">Liability</option>
              <option value="Equity">Equity</option>
              <option value="Revenue">Revenue</option>
              <option value="Expense">Expense</option>
            </select>
            <div v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Parent Account (Optional)</label>
            <select
              v-model="form.parent_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option :value="null">Tidak ada parent (Root Account)</option>
              <option v-for="parent in parents" :key="parent.id" :value="parent.id">
                {{ parent.display || `${parent.code} - ${parent.name} (${parent.type})` }}
              </option>
            </select>
            <p class="mt-1 text-xs text-gray-500">Pilih parent jika ini adalah child account</p>
            <div v-if="form.errors.parent_id" class="mt-1 text-sm text-red-600">{{ form.errors.parent_id }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Deskripsi Chart of Account"
            ></textarea>
            <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
          </div>
          
          <div>
            <label class="flex items-center gap-2">
              <input
                v-model="form.is_active"
                type="checkbox"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <span class="text-sm font-medium text-gray-700">Active</span>
            </label>
          </div>
        </div>
        
        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin"></i>
            {{ isSubmitting ? 'Menyimpan...' : (mode === 'create' ? 'Simpan' : 'Update') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

