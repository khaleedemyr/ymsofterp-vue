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
  content: '',
  meta_title: '',
  meta_description: '',
  is_published: false,
  order: 0
});

const errors = ref({});
const isSubmitting = ref(false);

function generateSlug() {
  if (form.value.title) {
    form.value.slug = form.value.title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');
  }
}

function submit() {
  isSubmitting.value = true;
  router.post('/web-profile', form.value, {
    onSuccess: () => {
      Swal.fire('Berhasil!', 'Page berhasil dibuat.', 'success');
      // Redirect handled by controller
    },
    onError: (validationErrors) => {
      errors.value = validationErrors;
      isSubmitting.value = false;
      Swal.fire('Error!', 'Gagal membuat page. Silakan periksa form yang diisi.', 'error');
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function cancel() {
  router.visit('/web-profile');
}
</script>

<template>
  <AppLayout title="Create Web Profile Page">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Create New Page</h1>
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

          <!-- Content -->
          <div>
            <InputLabel for="content" value="Content" />
            <textarea
              id="content"
              v-model="form.content"
              rows="10"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              :class="{ 'border-red-500': errors.content }"
            ></textarea>
            <InputError :message="errors.content" class="mt-2" />
          </div>

          <!-- Meta Title -->
          <div>
            <InputLabel for="meta_title" value="Meta Title (SEO)" />
            <TextInput
              id="meta_title"
              v-model="form.meta_title"
              type="text"
              class="mt-1 block w-full"
              :class="{ 'border-red-500': errors.meta_title }"
            />
            <InputError :message="errors.meta_title" class="mt-2" />
          </div>

          <!-- Meta Description -->
          <div>
            <InputLabel for="meta_description" value="Meta Description (SEO)" />
            <textarea
              id="meta_description"
              v-model="form.meta_description"
              rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              :class="{ 'border-red-500': errors.meta_description }"
            ></textarea>
            <InputError :message="errors.meta_description" class="mt-2" />
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
            />
            <InputError :message="errors.order" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Lower numbers appear first</p>
          </div>

          <!-- Published -->
          <div class="flex items-center">
            <input
              id="is_published"
              v-model="form.is_published"
              type="checkbox"
              class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
            />
            <InputLabel for="is_published" value="Publish this page" class="ml-2" />
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
              {{ isSubmitting ? 'Creating...' : 'Create Page' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

