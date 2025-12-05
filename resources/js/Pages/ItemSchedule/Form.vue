<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  items: Array,
  editData: Object
});

const isEdit = computed(() => !!props.editData);
const form = useForm({
  item_id: props.editData?.item_id || '',
  arrival_day: props.editData?.arrival_day || '',
  notes: props.editData?.notes || ''
});

const days = [
  'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
];

function onSubmit() {
  if (isEdit.value) {
    form.put(`/item-schedules/${props.editData.id}`, {
      onSuccess: () => router.visit('/item-schedules')
    });
  } else {
    form.post('/item-schedules', {
      onSuccess: () => router.visit('/item-schedules')
    });
  }
}
function goBack() {
  router.visit('/item-schedules');
}
</script>
<template>
  <AppLayout>
    <div class="max-w-xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-calendar-days text-blue-500"></i>
          <span v-if="isEdit">Edit Jadwal Item</span>
          <span v-else>Buat Jadwal Item</span>
        </h1>
      </div>
      <form @submit.prevent="onSubmit" class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">Item</label>
          <select v-model="form.item_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
            <option value="">Pilih Item</option>
            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
          <div v-if="form.errors.item_id" class="text-xs text-red-500 mt-1">{{ form.errors.item_id }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Hari Kedatangan</label>
          <select v-model="form.arrival_day" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
            <option value="">Pilih Hari</option>
            <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
          </select>
          <div v-if="form.errors.arrival_day" class="text-xs text-red-500 mt-1">{{ form.errors.arrival_day }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Catatan</label>
          <input v-model="form.notes" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
          <div v-if="form.errors.notes" class="text-xs text-red-500 mt-1">{{ form.errors.notes }}</div>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template> 