<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  show: Boolean,
  mode: String,
  row: Object,
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  code: '',
  name: '',
  description: '',
  sort_order: 0,
  status: 'A',
});

watch(
  () => props.show,
  (val) => {
    if (!val) return;
    if (props.mode === 'edit' && props.row) {
      form.code = props.row.code;
      form.name = props.row.name;
      form.description = props.row.description || '';
      form.sort_order = props.row.sort_order || 0;
      form.status = props.row.status;
    } else {
      form.reset();
      form.sort_order = 0;
      form.status = 'A';
    }
  },
);

const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  const opts = {
    onSuccess: () => { emit('success'); emit('close'); },
    onFinish: () => { isSubmitting.value = false; },
  };
  if (props.mode === 'create') {
    form.post(route('kpi-key-strategies.store'), opts);
  } else if (props.row) {
    form.put(route('kpi-key-strategies.update', props.row.id), opts);
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
      <div class="px-6 pt-6 pb-2 border-b">
        <h3 class="text-xl font-bold">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Key Strategy</h3>
      </div>
      <div class="px-6 py-4 space-y-4">
        <div>
          <label class="block text-sm font-medium">Kode *</label>
          <input v-model="form.code" class="mt-1 w-full rounded-lg border-gray-300" placeholder="KS01" />
          <div v-if="form.errors.code" class="text-xs text-red-500">{{ form.errors.code }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium">Nama *</label>
          <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-300" />
          <div v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-lg border-gray-300" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium">Sort Order</label>
            <input v-model.number="form.sort_order" type="number" min="0" class="mt-1 w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="block text-sm font-medium">Status</label>
            <select v-model="form.status" class="mt-1 w-full rounded-lg border-gray-300">
              <option value="A">Aktif</option>
              <option value="N">Nonaktif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end gap-2 rounded-b-2xl">
        <button type="button" class="px-4 py-2 rounded-lg border" @click="$emit('close')">Batal</button>
        <button type="button" :disabled="isSubmitting" class="px-4 py-2 rounded-lg bg-violet-600 text-white" @click="submit">Simpan</button>
      </div>
    </div>
  </div>
</template>
