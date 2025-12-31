<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  brand: {
    type: Object,
    required: true
  }
});

const form = ref({
  title: props.brand.title || '',
  slug: props.brand.slug || '',
  link_menu: props.brand.link_menu || '',
  menu_pdf: null,
  thumbnail: null,
  image: null,
  content: props.brand.content || ''
});

const errors = ref({});
const isSubmitting = ref(false);
const thumbnailPreview = ref(props.brand.thumbnail_url || null);
const imagePreview = ref(props.brand.image_url || null);

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
  formData.append('slug', form.value.slug);
  formData.append('link_menu', form.value.link_menu || '');
  formData.append('content', form.value.content || '');
  formData.append('_method', 'PUT');
  
  if (form.value.thumbnail) {
    formData.append('thumbnail', form.value.thumbnail);
  }
  if (form.value.image) {
    formData.append('image', form.value.image);
  }
  if (form.value.menu_pdf) {
    formData.append('menu_pdf', form.value.menu_pdf);
  }

  router.post(`/web-profile/brands/${props.brand.id}`, formData, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire('Berhasil!', 'Brand berhasil diperbarui.', 'success');
    },
    onError: (validationErrors) => {
      errors.value = validationErrors || {};
      isSubmitting.value = false;
      
      let errorMessage = 'Gagal memperbarui brand. Silakan periksa form yang diisi.';
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
  <AppLayout title="Edit Brand">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit Brand: {{ brand.title }}</h1>
        <button @click="cancel" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
          <i class="fa-solid fa-arrow-left mr-2"></i> Back
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
              required
            />
            <InputError :message="errors.title" class="mt-2" />
          </div>

          <!-- Slug -->
          <div>
            <InputLabel for="slug" value="Slug *" />
            <TextInput
              id="slug"
              v-model="form.slug"
              type="text"
              class="mt-1 block w-full"
              :class="{ 'border-red-500': errors.slug }"
              required
            />
            <InputError :message="errors.slug" class="mt-2" />
          </div>

          <!-- Thumbnail -->
          <div>
            <InputLabel for="thumbnail" value="Thumbnail Image" />
            <input
              id="thumbnail"
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              @change="handleThumbnailChange"
            />
            <InputError :message="errors.thumbnail" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Leave empty to keep current image</p>
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
            <p class="mt-1 text-sm text-gray-500">Leave empty to keep current image</p>
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
            <p class="mt-1 text-sm text-gray-500">Leave empty to keep current PDF</p>
            <div v-if="brand.menu_pdf_url" class="mt-2">
              <a :href="brand.menu_pdf_url" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fa-solid fa-file-pdf mr-2"></i> View Current PDF
              </a>
            </div>
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
              {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

