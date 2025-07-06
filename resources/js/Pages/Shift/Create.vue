<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  divisions: Array,
});

const form = useForm({
  division_id: '',
  shift_name: '',
  time_start: '',
  time_end: '',
});

function submit() {
  form.post('/shifts', {
    onSuccess: () => form.reset(),
  });
}
</script>

<template>
  <AppLayout title="Tambah Shift">
    <div class="max-w-xl mx-auto py-8">
      <h1 class="text-2xl font-bold text-blue-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-clock text-blue-500"></i> Tambah Shift
      </h1>
      <form @submit.prevent="submit" class="bg-white rounded-xl shadow p-6 space-y-5">
        <div>
          <label class="block text-sm font-medium mb-1">Divisi</label>
          <select v-model="form.division_id" class="form-input w-full" required>
            <option value="">Pilih Divisi</option>
            <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.nama_divisi }}</option>
          </select>
          <div v-if="form.errors.division_id" class="text-xs text-red-500 mt-1">{{ form.errors.division_id }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Nama Shift</label>
          <input v-model="form.shift_name" class="form-input w-full" maxlength="100" required />
          <div v-if="form.errors.shift_name" class="text-xs text-red-500 mt-1">{{ form.errors.shift_name }}</div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Jam Mulai</label>
            <input v-model="form.time_start" type="time" class="form-input w-full" required />
            <div v-if="form.errors.time_start" class="text-xs text-red-500 mt-1">{{ form.errors.time_start }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Jam Selesai</label>
            <input v-model="form.time_end" type="time" class="form-input w-full" required />
            <div v-if="form.errors.time_end" class="text-xs text-red-500 mt-1">{{ form.errors.time_end }}</div>
          </div>
        </div>
        <div class="flex gap-2 justify-end pt-4">
          <a href="/shifts" class="px-4 py-2 rounded bg-gray-100 text-gray-700 hover:bg-gray-200">Batal</a>
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template> 