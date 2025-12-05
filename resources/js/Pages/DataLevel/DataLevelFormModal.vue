<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  dataLevel: Object, // untuk edit
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  nama_level: '',
  nilai_level: '',
  nilai_public_holiday: 0,
  nilai_dasar_potongan_bpjs: 0,
  nilai_point: 0,
  status: 'A',
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.dataLevel) {
    form.nama_level = props.dataLevel.nama_level;
    form.nilai_level = props.dataLevel.nilai_level;
    form.nilai_public_holiday = props.dataLevel.nilai_public_holiday;
    form.nilai_dasar_potongan_bpjs = props.dataLevel.nilai_dasar_potongan_bpjs;
    form.nilai_point = props.dataLevel.nilai_point;
    // Don't change status in edit mode
  } else if (val && props.mode === 'create') {
    form.nama_level = '';
    form.nilai_level = '';
    form.nilai_public_holiday = 0;
    form.nilai_dasar_potongan_bpjs = 0;
    form.nilai_point = 0;
    form.status = 'A'; // Always set to active for new records
  }
});

const isSubmitting = ref(false);

async function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('data-levels.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Data Level berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.dataLevel) {
    form._method = 'PUT';
    form.post(route('data-levels.update', props.dataLevel.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Data Level berhasil diupdate!', 'success');
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
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-auto p-0 animate-fade-in">
      <div class="px-8 pt-8 pb-2">
        <div class="flex items-center gap-2 mb-6">
          <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M16 7a4 4 0 01-8 0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 3v4m0 0a4 4 0 01-4 4H4m8-4a4 4 0 014 4h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Data Level</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nama Level</label>
              <input v-model="form.nama_level" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
              <div v-if="form.errors.nama_level" class="text-xs text-red-500 mt-1">{{ form.errors.nama_level }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Nilai Level</label>
              <input v-model="form.nilai_level" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
              <div v-if="form.errors.nilai_level" class="text-xs text-red-500 mt-1">{{ form.errors.nilai_level }}</div>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nilai Public Holiday</label>
              <input v-model="form.nilai_public_holiday" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
              <div v-if="form.errors.nilai_public_holiday" class="text-xs text-red-500 mt-1">{{ form.errors.nilai_public_holiday }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Nilai Dasar Potongan BPJS</label>
              <input v-model="form.nilai_dasar_potongan_bpjs" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
              <div v-if="form.errors.nilai_dasar_potongan_bpjs" class="text-xs text-red-500 mt-1">{{ form.errors.nilai_dasar_potongan_bpjs }}</div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nilai Point</label>
            <input v-model="form.nilai_point" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
            <div v-if="form.errors.nilai_point" class="text-xs text-red-500 mt-1">{{ form.errors.nilai_point }}</div>
          </div>

        </form>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-2xl">
        <button type="button" @click="closeModal" class="inline-flex items-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition sm:w-auto sm:text-sm mr-2">
          Batal
        </button>
        <button type="button" @click="submit" :disabled="isSubmitting" class="inline-flex items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
          <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isSubmitting ? (mode === 'edit' ? 'Menyimpan...' : 'Menambah...') : (mode === 'edit' ? 'Simpan' : 'Tambah') }}
        </button>
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