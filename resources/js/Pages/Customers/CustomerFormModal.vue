<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  customer: Object, // untuk edit
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  code: '',
  name: '',
  type: 'branch',
  region: '',
  status: 'active',
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.customer) {
    form.code = props.customer.code;
    form.name = props.customer.name;
    form.type = props.customer.type;
    form.region = props.customer.region;
    form.status = props.customer.status;
  } else if (val && props.mode === 'create') {
    form.code = '';
    form.name = '';
    form.type = 'branch';
    form.region = '';
    form.status = 'active';
  }
});

const isSubmitting = ref(false);

async function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('customers.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Customer berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.customer) {
    form._method = 'PUT';
    form.post(route('customers.update', props.customer.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Customer berhasil diupdate!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  }
}

function closeModal() {
  emit('close');
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto p-0 animate-fade-in">
      <div class="px-8 pt-8 pb-2">
        <div class="flex items-center gap-2 mb-6">
          <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Customer</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700">Kode</label>
            <input v-model="form.code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="20" />
            <div v-if="form.errors.code" class="text-xs text-red-500 mt-1">{{ form.errors.code }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama</label>
            <input v-model="form.name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
            <div v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tipe</label>
            <select v-model="form.type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="branch">Branch</option>
              <option value="customer">Customer</option>
            </select>
            <div v-if="form.errors.type" class="text-xs text-red-500 mt-1">{{ form.errors.type }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Region</label>
            <input v-model="form.region" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="20" />
            <div v-if="form.errors.region" class="text-xs text-red-500 mt-1">{{ form.errors.region }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select v-model="form.status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <div v-if="form.errors.status" class="text-xs text-red-500 mt-1">{{ form.errors.status }}</div>
          </div>
          <div class="flex justify-end gap-2 pt-4">
            <button type="button" @click="closeModal" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Batal</button>
            <button type="submit" :disabled="isSubmitting" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-60">
              {{ mode === 'edit' ? 'Update' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px);}
  to { opacity: 1; transform: translateY(0);}
}
.animate-fade-in {
  animation: fade-in 0.3s cubic-bezier(.4,0,.2,1);
}
</style> 