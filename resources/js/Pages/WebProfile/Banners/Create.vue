<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const form = ref({
  title: '',
  subtitle: '',
  description: '',
  background_image: null,
  content_image: null,
  order: 0,
  is_active: true
});

const errors = ref({});
const isSubmitting = ref(false);
const backgroundImagePreview = ref(null);
const backgroundMediaType = ref('image');
const contentImagePreview = ref(null);

function handleBackgroundImageChange(event) {
  const file = event.target.files[0];
  if (file) {
    form.value.background_image = file;
    backgroundMediaType.value = file.type.startsWith('video/') ? 'video' : 'image';
    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      backgroundImagePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function handleContentImageChange(event) {
  const file = event.target.files[0];
  if (file) {
    form.value.content_image = file;
    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      contentImagePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function submit() {
  if (!form.value.background_image) {
    if (form.value.content_image) {
      Swal.fire({
        icon: 'question',
        title: 'Gunakan file Content sebagai Head Banner?',
        text: 'Head Banner kosong, tapi Content Overlay terisi. Mau pakai file Content sebagai Head Banner utama?',
        showCancelButton: true,
        confirmButtonText: 'Ya, gunakan',
        cancelButtonText: 'Tidak',
      }).then((result) => {
        if (result.isConfirmed) {
          form.value.background_image = form.value.content_image;
          backgroundMediaType.value = 'image';
          submit();
        }
      });
      return;
    }

    Swal.fire({
      icon: 'warning',
      title: 'Head Banner wajib diisi',
      text: 'Pilih 1 file untuk Head Banner utama (boleh image ATAU video).',
    });
    return;
  }

  isSubmitting.value = true;
  
  const formData = new FormData();
  formData.append('title', form.value.title);
  formData.append('subtitle', form.value.subtitle || '');
  formData.append('description', form.value.description || '');
  formData.append('order', form.value.order);
  formData.append('is_active', form.value.is_active ? 1 : 0);
  
  if (form.value.background_image) {
    formData.append('background_image', form.value.background_image);
  }
  if (form.value.content_image) {
    formData.append('content_image', form.value.content_image);
  }

  router.post('/web-profile/banners', formData, {
    forceFormData: true,
    onSuccess: () => {
      Swal.fire('Berhasil!', 'Banner berhasil dibuat.', 'success');
      // Redirect handled by controller
    },
    onError: (validationErrors) => {
      // Handle validation errors
      errors.value = validationErrors || {};
      isSubmitting.value = false;
      
      // Get error messages
      let errorMessage = 'Gagal membuat banner. Silakan periksa form yang diisi.';
      if (validationErrors && Object.keys(validationErrors).length > 0) {
        const errorMessages = Object.values(validationErrors).flat();
        if (errorMessages.length > 0) {
          errorMessage = errorMessages.join('<br>');
        }
      }
      
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        html: errorMessage,
      });
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function cancel() {
  router.visit('/web-profile/banners');
}
</script>

<template>
  <AppLayout title="Create Banner">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Create New Banner</h1>
        <button @click="cancel" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
          <i class="fa-solid fa-arrow-left mr-2"></i> Cancel
        </button>
      </div>

      <!-- Requirements Info -->
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
        <h3 class="font-semibold text-yellow-800 mb-2">
          <i class="fa-solid fa-exclamation-triangle mr-2"></i> Head Banner Requirements
        </h3>
        <div class="text-sm text-yellow-700 space-y-2">
          <div>
            <strong>Head Banner Utama (Wajib - pilih salah satu):</strong>
            <ul class="ml-4 mt-1 list-disc">
              <li>Image atau video (cukup salah satu, tidak perlu keduanya)</li>
              <li>Formats: JPG, PNG, WEBP, MP4, WEBM</li>
              <li>Landscape 16:9 agar fit full screen website</li>
              <li>Image minimum 1920 x 1080 pixels</li>
              <li>Video recommended 1920 x 1080 pixels (durasi pendek 10-30 detik)</li>
              <li>Maximum file size: 50MB</li>
            </ul>
          </div>
          <div>
            <strong>Content Image (Optional):</strong>
            <ul class="ml-4 mt-1 list-disc">
              <li>Minimum dimensions: 800 x 600 pixels</li>
              <li>Maximum file size: 5MB</li>
              <li>Formats: JPG, PNG, WEBP</li>
              <li>Recommended: 1200x800px for best quality</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Title -->
          <div>
            <InputLabel for="title" value="Title *" />
            <TextInput
              id="title"
              v-model="form.title"
              type="text"
              class="mt-1 block w-full"
              :class="{ 'border-red-500': errors.title }"
              required
            />
            <InputError :message="errors.title" class="mt-2" />
          </div>

          <!-- Subtitle -->
          <div>
            <InputLabel for="subtitle" value="Subtitle" />
            <TextInput
              id="subtitle"
              v-model="form.subtitle"
              type="text"
              class="mt-1 block w-full"
              :class="{ 'border-red-500': errors.subtitle }"
            />
            <InputError :message="errors.subtitle" class="mt-2" />
          </div>

          <!-- Description -->
          <div>
            <InputLabel for="description" value="Description" />
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              :class="{ 'border-red-500': errors.description }"
            ></textarea>
            <InputError :message="errors.description" class="mt-2" />
          </div>

          <!-- Head Banner -->
          <div>
            <InputLabel for="background_image" value="Head Banner Utama * (Image ATAU Video)" />
            <input
              id="background_image"
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp,video/mp4,video/webm"
              @change="handleBackgroundImageChange"
              class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              :class="{ 'border-red-500': errors.background_image }"
            />
            <InputError :message="errors.background_image" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500">Wajib isi 1 file (image atau video). Recommended: 1920x1080 (16:9), Max: 50MB</p>
            
            <!-- Preview -->
            <div v-if="backgroundImagePreview" class="mt-4">
              <p class="text-sm font-medium text-gray-700 mb-2">Preview:</p>
              <img
                v-if="backgroundMediaType === 'image'"
                :src="backgroundImagePreview"
                alt="Head banner preview"
                class="max-w-full h-48 object-cover rounded-lg border border-gray-300"
              />
              <video
                v-else
                :src="backgroundImagePreview"
                controls
                class="max-w-full h-48 rounded-lg border border-gray-300 bg-black"
              />
            </div>
          </div>

          <!-- Content Image -->
          <div>
            <InputLabel for="content_image" value="Content Overlay Image (Optional)" />
            <input
              id="content_image"
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              @change="handleContentImageChange"
              class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              :class="{ 'border-red-500': errors.content_image }"
            />
            <InputError :message="errors.content_image" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500">Opsional. Ini bukan pengganti Head Banner. Min: 800x600px, Max: 5MB</p>
            
            <!-- Preview -->
            <div v-if="contentImagePreview" class="mt-4">
              <p class="text-sm font-medium text-gray-700 mb-2">Preview:</p>
              <img :src="contentImagePreview" alt="Content preview" class="max-w-full h-48 object-cover rounded-lg border border-gray-300" />
            </div>
          </div>

          <!-- Order -->
          <div>
            <InputLabel for="order" value="Order" />
            <TextInput
              id="order"
              v-model.number="form.order"
              type="number"
              class="mt-1 block w-full"
              :class="{ 'border-red-500': errors.order }"
              min="0"
            />
            <InputError :message="errors.order" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Lower numbers appear first (max 5 banners will be shown)</p>
          </div>

          <!-- Active -->
          <div class="flex items-center">
            <input
              id="is_active"
              v-model="form.is_active"
              type="checkbox"
              class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
            />
            <InputLabel for="is_active" value="Activate this banner" class="ml-2" />
          </div>

          <!-- Submit -->
          <div class="flex justify-end gap-4">
            <button
              type="button"
              @click="cancel"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </button>
            <PrimaryButton :disabled="isSubmitting">
              {{ isSubmitting ? 'Creating...' : 'Create Banner' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

