<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  group: Object,
});

const form = ref({
  name: props.group.name || '',
  description: props.group.description || '',
  status: props.group.status || 'A',
});

const errors = ref({});
const isSubmitting = ref(false);

function submit() {
  if (!form.value.name) {
    errors.value.name = 'Nama group harus diisi';
    return;
  }
  
  isSubmitting.value = true;
  errors.value = {};
  
  // Use PUT method for update
  const formData = new FormData();
  formData.append('name', form.value.name);
  formData.append('description', form.value.description || '');
  formData.append('status', form.value.status);
  formData.append('_method', 'PUT');
  
  router.post(`/video-tutorial-groups/${props.group.id}`, formData, {
    onSuccess: () => {
      isSubmitting.value = false;
    },
    onError: (err) => {
      errors.value = err;
      isSubmitting.value = false;
    },
  });
}

function cancel() {
  router.visit('/video-tutorial-groups');
}
</script>

<template>
  <AppLayout title="Edit Group Video Tutorial">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-green-500"></i> Edit Group Video Tutorial
        </h1>
        <button @click="cancel" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>
      
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Nama Group <span class="text-red-500">*</span>
            </label>
            <input 
              v-model="form.name" 
              type="text" 
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-400 focus:border-green-400 transition"
              :class="{ 'border-red-500': errors.name }"
              placeholder="Masukkan nama group video tutorial"
            />
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
          </div>
          
          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Deskripsi
            </label>
            <textarea 
              v-model="form.description" 
              rows="4"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-400 focus:border-green-400 transition"
              :class="{ 'border-red-500': errors.description }"
              placeholder="Masukkan deskripsi group video tutorial (opsional)"
            ></textarea>
            <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description }}</p>
          </div>
          
          <!-- Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Status <span class="text-red-500">*</span>
            </label>
            <select 
              v-model="form.status" 
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-400 focus:border-green-400 transition"
              :class="{ 'border-red-500': errors.status }"
            >
              <option value="A">Active</option>
              <option value="N">Inactive</option>
            </select>
            <p v-if="errors.status" class="mt-1 text-sm text-red-600">{{ errors.status }}</p>
          </div>
          
          <!-- Group Stats -->
          <div class="bg-gray-50 rounded-xl p-4">
            <h3 class="text-lg font-medium text-gray-800 mb-3">Statistik Group</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              <div>
                <span class="font-medium text-gray-600">Total Video:</span>
                <p class="text-gray-900">{{ group.videos_count || 0 }} video</p>
              </div>
              <div>
                <span class="font-medium text-gray-600">Video Aktif:</span>
                <p class="text-gray-900">{{ group.active_videos_count || 0 }} video</p>
              </div>
              <div>
                <span class="font-medium text-gray-600">Dibuat Oleh:</span>
                <p class="text-gray-900">{{ group.creator?.name || '-' }}</p>
              </div>
              <div>
                <span class="font-medium text-gray-600">Tanggal Dibuat:</span>
                <p class="text-gray-900">{{ new Date(group.created_at).toLocaleDateString('id-ID') }}</p>
              </div>
            </div>
          </div>
          
          <!-- Submit Button -->
          <div class="flex justify-end gap-4 pt-6">
            <button 
              type="button" 
              @click="cancel"
              class="px-6 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition"
            >
              Batal
            </button>
            <button 
              type="submit" 
              :disabled="isSubmitting"
              class="px-6 py-2 bg-green-600 text-white rounded-xl hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
              {{ isSubmitting ? 'Menyimpan...' : 'Update Group' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template> 