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
  slug: '',
  link_menu: '',
  menu_pdf: null,
  thumbnail: null,
  image: null,
  content: ''
});

const errors = ref({});
const isSubmitting = ref(false);
const thumbnailPreview = ref(null);
const imagePreview = ref(null);

function generateSlug() {
  if (form.value.title) {
    form.value.slug = form.value.title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');
  }
}

function handleThumbnailChange(event) {
  const file = event.target.files[0];
  if (file) {
    form.value.thumbnail = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      thumbnailPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function handleImageChange(event) {
  const file = event.target.files[0];
  if (file) {
    form.value.image = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function handleMenuPdfChange(event) {
  const file = event.target.files[0];
  if (file) {
    form.value.menu_pdf = file;
  }
}

function submit() {
  isSubmitting.value = true;
  
  const formData = new FormData();
  formData.append('title', form.value.title);
  formData.append('slug', form.value.slug || '');
  formData.append('link_menu', form.value.link_menu || '');
  formData.append('content', form.value.content || '');
  
  if (form.value.thumbnail) {
    formData.append('thumbnail', form.value.thumbnail);
  }
  if (form.value.image) {
    formData.append('image', form.value.image);
  }
  if (form.value.menu_pdf) {
    formData.append('menu_pdf', form.value.menu_pdf);
  }

  router.post('/web-profile/brands', formData, {
    forceFormData: true,
    onSuccess: () => {
      Swal.fire('Berhasil!', 'Brand berhasil dibuat.', 'success');
    },
    onError: (validationErrors) => {
      errors.value = validationErrors || {};
      isSubmitting.value = false;
      
      let errorMessage = 'Gagal membuat brand. Silakan periksa form yang diisi.';
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
  router.visit('/web-profile/brands');
}
</script>

<template>
  <AppLayout title="Create Brand">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Create New Brand</h1>
        <button @click="cancel" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
          <i class="fa-solid fa-arrow-left mr-2"></i> Cancel
        </button>
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
              @input="generateSlug"
              required
            />
            <InputError :message="errors.title" class="mt-2" />
          </div>

          <!-- Slug -->
          <div>
            <InputLabel for="slug" value="Slug" />
            <TextInput
              id="slug"
              v-model="form.slug"
              type="text"
              class="mt-1 block w-full"
              :class="{ 'border-red-500': errors.slug }"
              placeholder="Auto-generated from title"
            />
            <InputError :message="errors.slug" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">URL-friendly version of the title</p>
          </div>

          <!-- Thumbnail -->
          <div>
            <InputLabel for="thumbnail" value="Thumbnail Image *" />
            <input
              id="thumbnail"
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              @change="handleThumbnailChange"
              required
            />
            <InputError :message="errors.thumbnail" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Recommended: 400x300px, Max 5MB, Format: JPG/PNG/WEBP</p>
            <div v-if="thumbnailPreview" class="mt-4">
              <img :src="thumbnailPreview" alt="Thumbnail preview" class="w-64 h-48 object-cover rounded-lg border-2 border-gray-300" />
            </div>
          </div>

          <!-- Image -->
          <div>
            <InputLabel for="image" value="Image (Optional)" />
            <input
              id="image"
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              @change="handleImageChange"
            />
            <InputError :message="errors.image" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Recommended: 800x600px, Max 5MB, Format: JPG/PNG/WEBP</p>
            <div v-if="imagePreview" class="mt-4">
              <img :src="imagePreview" alt="Image preview" class="w-64 h-48 object-cover rounded-lg border-2 border-gray-300" />
            </div>
          </div>

          <!-- Link Menu -->
          <div>
            <InputLabel for="link_menu" value="Menu Link (Optional)" />
            <TextInput
              id="link_menu"
              v-model="form.link_menu"
              type="url"
              class="mt-1 block w-full"
              :class="{ 'border-red-500': errors.link_menu }"
              placeholder="https://example.com/menu"
            />
            <InputError :message="errors.link_menu" class="mt-2" />
          </div>

          <!-- Menu PDF -->
          <div>
            <InputLabel for="menu_pdf" value="Menu PDF (Optional)" />
            <input
              id="menu_pdf"
              type="file"
              accept="application/pdf"
              class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              @change="handleMenuPdfChange"
            />
            <InputError :message="errors.menu_pdf" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Max 10MB, Format: PDF</p>
          </div>

          <!-- Content -->
          <div>
            <InputLabel for="content" value="Content (Optional)" />
            <textarea
              id="content"
              v-model="form.content"
              rows="6"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              :class="{ 'border-red-500': errors.content }"
            ></textarea>
            <InputError :message="errors.content" class="mt-2" />
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
              {{ isSubmitting ? 'Creating...' : 'Create Brand' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

