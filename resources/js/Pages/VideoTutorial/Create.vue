<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  groups: Array,
});

const form = ref({
  group_id: '',
  title: '',
  description: '',
  video: null,
  thumbnail: null,
});

const errors = ref({});
const isSubmitting = ref(false);
const uploadProgress = ref(0);

function handleVideoChange(event) {
  const file = event.target.files[0];
  if (file) {
    // Validate file size (100MB max)
    if (file.size > 100 * 1024 * 1024) {
      Swal.fire({
        title: 'Error!',
        text: 'Ukuran file video maksimal 100MB',
        icon: 'error',
        confirmButtonColor: '#dc2626'
      });
      event.target.value = '';
      return;
    }
    
    // Validate file type
    const allowedTypes = ['video/mp4', 'video/webm', 'video/avi', 'video/mov'];
    if (!allowedTypes.includes(file.type)) {
      Swal.fire({
        title: 'Error!',
        text: 'Tipe file video tidak didukung. Gunakan MP4, WebM, AVI, atau MOV',
        icon: 'error',
        confirmButtonColor: '#dc2626'
      });
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
      Swal.fire({
        title: 'Error!',
        text: 'Ukuran file thumbnail maksimal 2MB',
        icon: 'error',
        confirmButtonColor: '#dc2626'
      });
      event.target.value = '';
      return;
    }
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
      Swal.fire({
        title: 'Error!',
        text: 'Tipe file thumbnail tidak didukung. Gunakan JPEG, PNG, atau JPG',
        icon: 'error',
        confirmButtonColor: '#dc2626'
      });
      event.target.value = '';
      return;
    }
    
    form.value.thumbnail = file;
  }
}

async function submit() {
  // Clear previous errors
  errors.value = {};
  
  // Client-side validation
  if (!form.value.group_id) {
    errors.value.group_id = 'Group harus dipilih';
    return;
  }
  
  if (!form.value.title.trim()) {
    errors.value.title = 'Judul harus diisi';
    return;
  }
  
  if (!form.value.video) {
    errors.value.video = 'File video harus dipilih';
    return;
  }
  
  // Show confirmation dialog
  const confirmed = await Swal.fire({
    title: 'Konfirmasi Upload',
    text: `Apakah Anda yakin ingin mengupload video "${form.value.video.name}"? Proses ini mungkin memakan waktu beberapa menit.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Upload!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  });
  
  if (!confirmed.isConfirmed) {
    return;
  }
  
  isSubmitting.value = true;
  uploadProgress.value = 0;
  
  // Show progress dialog
  Swal.fire({
    title: 'Mengupload Video...',
    html: `
      <div class="mb-4">
        <div class="w-full bg-gray-200 rounded-full h-2.5">
          <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <p class="mt-2 text-sm text-gray-600">Mohon tunggu, video sedang diproses...</p>
      </div>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      const progressBar = Swal.getHtmlContainer().querySelector('.bg-blue-600');
      const progressText = Swal.getHtmlContainer().querySelector('p');
      
      // Simulate progress
      let progress = 0;
      const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90;
        
        progressBar.style.width = progress + '%';
        uploadProgress.value = progress;
        
        if (progress >= 90) {
          clearInterval(interval);
        }
      }, 500);
    }
  });
  
  const formData = new FormData();
  formData.append('group_id', form.value.group_id);
  formData.append('title', form.value.title);
  formData.append('description', form.value.description || '');
  formData.append('video', form.value.video);
  if (form.value.thumbnail) {
    formData.append('thumbnail', form.value.thumbnail);
  }
  
  try {
    await router.post('/video-tutorials', formData, {
      onSuccess: (page) => {
        isSubmitting.value = false;
        uploadProgress.value = 100;
        
        // Close progress dialog and show success
        Swal.close();
        Swal.fire({
          title: 'Berhasil!',
          text: 'Video tutorial berhasil diupload',
          icon: 'success',
          confirmButtonColor: '#16a34a',
          timer: 2000,
          showConfirmButton: false
        });
      },
      onError: (err) => {
        isSubmitting.value = false;
        errors.value = err;
        
        // Close progress dialog and show error
        Swal.close();
        Swal.fire({
          title: 'Error!',
          text: 'Terjadi kesalahan saat mengupload video',
          icon: 'error',
          confirmButtonColor: '#dc2626'
        });
        
        console.error('Upload error:', err);
      },
      onFinish: () => {
        isSubmitting.value = false;
        Swal.close();
      }
    });
  } catch (error) {
    isSubmitting.value = false;
    console.error('Unexpected error:', error);
    
    Swal.close();
    Swal.fire({
      title: 'Error!',
      text: 'Terjadi kesalahan yang tidak terduga',
      icon: 'error',
      confirmButtonColor: '#dc2626'
    });
  }
}

function cancel() {
  Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin keluar? Data yang belum disimpan akan hilang.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Keluar',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.visit('/video-tutorials');
    }
  });
}
</script>

<template>
  <AppLayout title="Upload Video Tutorial">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-upload text-blue-500"></i> Upload Video Tutorial
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
              :disabled="isSubmitting"
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
              :disabled="isSubmitting"
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
              :disabled="isSubmitting"
            ></textarea>
            <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description }}</p>
          </div>
          
          <!-- Video Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              File Video <span class="text-red-500">*</span>
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition">
              <input 
                type="file" 
                @change="handleVideoChange"
                accept="video/mp4,video/webm,video/avi,video/mov"
                class="hidden" 
                id="video-upload"
                :disabled="isSubmitting"
              />
              <label for="video-upload" class="cursor-pointer" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                <i class="fa-solid fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                <p class="text-lg font-medium text-gray-700 mb-2">
                  {{ form.video ? form.video.name : 'Klik untuk memilih file video' }}
                </p>
                <p class="text-sm text-gray-500">
                  MP4, WebM, AVI, atau MOV (maksimal 100MB)
                </p>
              </label>
            </div>
            <p v-if="errors.video" class="mt-1 text-sm text-red-600">{{ errors.video }}</p>
          </div>
          
          <!-- Thumbnail Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Thumbnail (Opsional)
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition">
              <input 
                type="file" 
                @change="handleThumbnailChange"
                accept="image/jpeg,image/png,image/jpg"
                class="hidden" 
                id="thumbnail-upload"
                :disabled="isSubmitting"
              />
              <label for="thumbnail-upload" class="cursor-pointer" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                <i class="fa-solid fa-image text-4xl text-gray-400 mb-4"></i>
                <p class="text-lg font-medium text-gray-700 mb-2">
                  {{ form.thumbnail ? form.thumbnail.name : 'Klik untuk memilih thumbnail' }}
                </p>
                <p class="text-sm text-gray-500">
                  JPEG, PNG, atau JPG (maksimal 2MB). Jika tidak dipilih, thumbnail akan dibuat otomatis dari video.
                </p>
              </label>
            </div>
            <p v-if="errors.thumbnail" class="mt-1 text-sm text-red-600">{{ errors.thumbnail }}</p>
          </div>
          
          <!-- Debug Info (temporary) -->
          <div v-if="Object.keys(errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-red-800 mb-2">Debug Info:</h3>
            <pre class="text-xs text-red-600">{{ JSON.stringify(errors, null, 2) }}</pre>
          </div>
          
          <!-- Submit Button -->
          <div class="flex justify-end gap-4 pt-6">
            <button 
              type="button" 
              @click="cancel"
              :disabled="isSubmitting"
              class="px-6 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Batal
            </button>
            <button 
              type="submit" 
              :disabled="isSubmitting || !form.group_id || !form.title.trim() || !form.video"
              class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-upload"></i>
              {{ isSubmitting ? 'Mengupload...' : 'Upload Video' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template> 