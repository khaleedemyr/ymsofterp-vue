<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  video: Object,
  groups: Array,
});

const form = ref({
  group_id: props.video.group_id || '',
  title: props.video.title || '',
  description: props.video.description || '',
  video: null,
  thumbnail: null,
  status: props.video.status || 'A',
});

const errors = ref({});
const isSubmitting = ref(false);

function handleVideoChange(event) {
  const file = event.target.files[0];
  if (file) {
    // Validate file size (100MB max)
    if (file.size > 100 * 1024 * 1024) {
      alert('Ukuran file video maksimal 100MB');
      event.target.value = '';
      return;
    }
    
    // Validate file type
    const allowedTypes = ['video/mp4', 'video/webm', 'video/avi', 'video/mov'];
    if (!allowedTypes.includes(file.type)) {
      alert('Tipe file video tidak didukung. Gunakan MP4, WebM, AVI, atau MOV');
      event.target.value = '';
      return;
    }
    
    form.value.video = file;
  }
}

function handleThumbnailChange(event) {
  const file = event.target.files[0];
  if (file) {
    // Validate file size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
      alert('Ukuran file thumbnail maksimal 2MB');
      event.target.value = '';
      return;
    }
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
      alert('Tipe file thumbnail tidak didukung. Gunakan JPEG, PNG, atau JPG');
      event.target.value = '';
      return;
    }
    
    form.value.thumbnail = file;
  }
}

function submit() {
  if (!form.value.group_id) {
    errors.value.group_id = 'Group harus dipilih';
    return;
  }
  
  if (!form.value.title) {
    errors.value.title = 'Judul harus diisi';
    return;
  }
  
  isSubmitting.value = true;
  errors.value = {};
  
  const formData = new FormData();
  formData.append('group_id', form.value.group_id);
  formData.append('title', form.value.title);
  formData.append('description', form.value.description || '');
  formData.append('status', form.value.status);
  if (form.value.video) {
    formData.append('video', form.value.video);
  }
  if (form.value.thumbnail) {
    formData.append('thumbnail', form.value.thumbnail);
  }
  
  // Use PUT method for update
  formData.append('_method', 'PUT');
  
  router.post(`/video-tutorials/${props.video.id}`, formData, {
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
  router.visit('/video-tutorials');
}
</script>

<template>
  <AppLayout title="Edit Video Tutorial">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-blue-500"></i> Edit Video Tutorial
        </h1>
        <button @click="cancel" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>
      
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Group Selection -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Group Video Tutorial <span class="text-red-500">*</span>
            </label>
            <select 
              v-model="form.group_id" 
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              :class="{ 'border-red-500': errors.group_id }"
            >
              <option value="">Pilih Group</option>
              <option v-for="group in groups" :key="group.id" :value="group.id">
                {{ group.name }}
              </option>
            </select>
            <p v-if="errors.group_id" class="mt-1 text-sm text-red-600">{{ errors.group_id }}</p>
          </div>
          
          <!-- Title -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Judul Video <span class="text-red-500">*</span>
            </label>
            <input 
              v-model="form.title" 
              type="text" 
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              :class="{ 'border-red-500': errors.title }"
              placeholder="Masukkan judul video tutorial"
            />
            <p v-if="errors.title" class="mt-1 text-sm text-red-600">{{ errors.title }}</p>
          </div>
          
          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Deskripsi
            </label>
            <textarea 
              v-model="form.description" 
              rows="4"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              :class="{ 'border-red-500': errors.description }"
              placeholder="Masukkan deskripsi video tutorial (opsional)"
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
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              :class="{ 'border-red-500': errors.status }"
            >
              <option value="A">Active</option>
              <option value="N">Inactive</option>
            </select>
            <p v-if="errors.status" class="mt-1 text-sm text-red-600">{{ errors.status }}</p>
          </div>
          
          <!-- Current Video Info -->
          <div class="bg-gray-50 rounded-xl p-4">
            <h3 class="text-lg font-medium text-gray-800 mb-3">Video Saat Ini</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              <div>
                <span class="font-medium text-gray-600">Nama File:</span>
                <p class="text-gray-900">{{ video.video_name }}</p>
              </div>
              <div>
                <span class="font-medium text-gray-600">Ukuran:</span>
                <p class="text-gray-900">{{ video.video_size_formatted }}</p>
              </div>
              <div>
                <span class="font-medium text-gray-600">Durasi:</span>
                <p class="text-gray-900">{{ video.duration_formatted }}</p>
              </div>
              <div>
                <span class="font-medium text-gray-600">Tipe:</span>
                <p class="text-gray-900">{{ video.video_type }}</p>
              </div>
            </div>
          </div>
          
          <!-- Video Upload (Optional) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Ganti File Video (Opsional)
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition">
              <input 
                type="file" 
                @change="handleVideoChange"
                accept="video/mp4,video/webm,video/avi,video/mov"
                class="hidden" 
                id="video-upload"
              />
              <label for="video-upload" class="cursor-pointer">
                <i class="fa-solid fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                <p class="text-lg font-medium text-gray-700 mb-2">
                  {{ form.video ? form.video.name : 'Klik untuk memilih file video baru' }}
                </p>
                <p class="text-sm text-gray-500">
                  MP4, WebM, AVI, atau MOV (maksimal 100MB). Kosongkan jika tidak ingin mengganti video.
                </p>
              </label>
            </div>
            <p v-if="errors.video" class="mt-1 text-sm text-red-600">{{ errors.video }}</p>
          </div>
          
          <!-- Current Thumbnail -->
          <div v-if="video.thumbnail_url" class="bg-gray-50 rounded-xl p-4">
            <h3 class="text-lg font-medium text-gray-800 mb-3">Thumbnail Saat Ini</h3>
            <div class="max-w-xs">
              <img :src="video.thumbnail_url" :alt="video.title" class="w-full h-auto rounded-lg shadow">
            </div>
          </div>
          
          <!-- Thumbnail Upload (Optional) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Ganti Thumbnail (Opsional)
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition">
              <input 
                type="file" 
                @change="handleThumbnailChange"
                accept="image/jpeg,image/png,image/jpg"
                class="hidden" 
                id="thumbnail-upload"
              />
              <label for="thumbnail-upload" class="cursor-pointer">
                <i class="fa-solid fa-image text-4xl text-gray-400 mb-4"></i>
                <p class="text-lg font-medium text-gray-700 mb-2">
                  {{ form.thumbnail ? form.thumbnail.name : 'Klik untuk memilih thumbnail baru' }}
                </p>
                <p class="text-sm text-gray-500">
                  JPEG, PNG, atau JPG (maksimal 2MB). Kosongkan jika tidak ingin mengganti thumbnail.
                </p>
              </label>
            </div>
            <p v-if="errors.thumbnail" class="mt-1 text-sm text-red-600">{{ errors.thumbnail }}</p>
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
              class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
              {{ isSubmitting ? 'Menyimpan...' : 'Update Video' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template> 