<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  region: Object, // untuk edit
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  code: '',
  name: '',
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.region) {
    form.code = props.region.code;
    form.name = props.region.name;
  } else if (val && props.mode === 'create') {
    form.code = '';
    form.name = '';
  }
});

const isSubmitting = ref(false);

async function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('regions.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Region berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.region) {
    form._method = 'PUT';
    form.post(route('regions.update', props.region.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Region berhasil diupdate!', 'success');
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
            <path d="M16 7a4 4 0 01-8 0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 3v4m0 0a4 4 0 01-4 4H4m8-4a4 4 0 014 4h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Region</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700">Kode</label>
            <input v-model="form.code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="50" />
            <div v-if="form.errors.code" class="text-xs text-red-500 mt-1">{{ form.errors.code }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama</label>
            <input v-model="form.name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
            <div v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</div>
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