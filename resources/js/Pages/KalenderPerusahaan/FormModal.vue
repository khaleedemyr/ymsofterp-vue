<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
  show: Boolean,
  editData: Object,
});
const emit = defineEmits(['close']);

const form = useForm({
  tgl_libur: '',
  keterangan: '',
});

const isEdit = computed(() => !!props.editData);

watch(() => props.editData, (val) => {
  if (val) {
    form.tgl_libur = val.tgl_libur;
    form.keterangan = val.keterangan;
  } else {
    form.tgl_libur = '';
    form.keterangan = '';
  }
});

function close() {
  emit('close');
  form.reset();
  form.clearErrors();
}

function submit() {
  if (isEdit.value) {
    form.put(`/kalender-perusahaan/${props.editData.id}`, {
      onSuccess: close,
    });
  } else {
    form.post('/kalender-perusahaan', {
      onSuccess: close,
    });
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md relative animate-fade-in">
      <h2 class="text-xl font-bold text-blue-700 mb-4 flex items-center gap-2">
        <i class="fa-solid fa-calendar-day text-blue-500"></i>
        {{ isEdit ? 'Edit Libur Nasional' : 'Tambah Libur Nasional' }}
      </h2>
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Libur <span class="text-red-500">*</span></label>
          <input type="date" v-model="form.tgl_libur" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" :class="form.errors.tgl_libur ? 'border-red-500' : ''" required />
          <div v-if="form.errors.tgl_libur" class="text-red-500 text-xs mt-1">{{ form.errors.tgl_libur }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan <span class="text-red-500">*</span></label>
          <input type="text" v-model="form.keterangan" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" :class="form.errors.keterangan ? 'border-red-500' : ''" required />
          <div v-if="form.errors.keterangan" class="text-red-500 text-xs mt-1">{{ form.errors.keterangan }}</div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button type="button" @click="close" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 shadow disabled:opacity-60">
            {{ isEdit ? 'Simpan Perubahan' : 'Tambah' }}
          </button>
        </div>
      </form>
      <button @click="close" class="absolute top-3 right-3 text-gray-400 hover:text-red-500"><i class="fa fa-times text-lg"></i></button>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.4s cubic-bezier(.4,0,.2,1);
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: none; }
}
</style> 