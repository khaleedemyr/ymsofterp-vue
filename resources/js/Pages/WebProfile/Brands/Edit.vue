<script setup>
import { ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
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
  logo_cp: null,
  image: null,
  content: props.brand.content || '',
  hero_title: props.brand.hero_title || '',
  hero_subtitle: props.brand.hero_subtitle || '',
  hero_media: null,
  remove_hero_media: false,
});

const page = usePage();
const errors = ref({});
const isSubmitting = ref(false);

onMounted(() => {
  const flash = page.props.flash || {};
  if (flash.success) {
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: flash.success,
      confirmButtonText: 'OK',
    });
  }
  if (flash.error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: flash.error,
      confirmButtonText: 'OK',
    });
  }
});
const thumbnailPreview = ref(props.brand.thumbnail_url || null);
const logoCpPreview = ref(props.brand.logo_cp_url || null);
const imagePreview = ref(props.brand.image_url || null);
const heroMediaPreview = ref(props.brand.hero_media_url || null);
const heroMediaType = ref(props.brand.hero_media_type || 'image');

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

function handleLogoCpChange(event) {
  const file = event.target.files[0];
  if (file) {
    form.value.logo_cp = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      logoCpPreview.value = e.target.result;
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

function handleHeroMediaChange(event) {
  const file = event.target.files[0];
  if (file) {
    form.value.hero_media = file;
    form.value.remove_hero_media = false;
    heroMediaType.value = file.type.startsWith('video/') ? 'video' : 'image';
    heroMediaPreview.value = URL.createObjectURL(file);
  }
}

function submit() {
  isSubmitting.value = true;
  
  const formData = new FormData();
  formData.append('title', form.value.title);
  formData.append('slug', form.value.slug);
  formData.append('link_menu', form.value.link_menu || '');
  formData.append('content', form.value.content || '');
  formData.append('hero_title', form.value.hero_title || '');
  formData.append('hero_subtitle', form.value.hero_subtitle || '');
  formData.append('remove_hero_media', form.value.remove_hero_media ? '1' : '0');
  formData.append('_method', 'PUT');
  
  if (form.value.thumbnail) {
    formData.append('thumbnail', form.value.thumbnail);
  }
  if (form.value.image) {
    formData.append('image', form.value.image);
  }
  if (form.value.logo_cp) {
    formData.append('logo_cp', form.value.logo_cp);
  }
  if (form.value.menu_pdf) {
    formData.append('menu_pdf', form.value.menu_pdf);
  }
  if (form.value.hero_media) {
    formData.append('hero_media', form.value.hero_media);
  }

  router.post(`/web-profile/brands/${props.brand.id}`, formData, {
    forceFormData: true,
    preserveScroll: true,
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

          <!-- Logo Company Profile -->
          <div>
            <InputLabel for="logo_cp" value="Logo Company Profile (Optional)" />
            <input
              id="logo_cp"
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              @change="handleLogoCpChange"
            />
            <InputError :message="errors.logo_cp" class="mt-2" />
            <p class="mt-1 text-sm text-gray-500">Leave empty to keep current logo company profile.</p>
            <p class="mt-1 text-sm text-gray-500">
              Justus Nest (navbar BRAND hover): lingkaran ~130×130 px di layar; area gambar terlihat ~90×90 px (object-contain). Disarankan persegi min. 256×256 px (512×512 untuk retina), PNG transparan, logo di tengah dengan margin. Max 5MB.
            </p>
            <div v-if="logoCpPreview" class="mt-4">
              <img :src="logoCpPreview" alt="Logo company profile preview" class="w-40 h-40 object-contain rounded-lg border-2 border-gray-300 bg-white p-2" />
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

          <div class="rounded-lg border border-gray-200 p-4 space-y-4">
            <h3 class="text-base font-semibold text-gray-800">Brand Page Header (Optional)</h3>
            <div>
              <InputLabel for="hero_media" value="Header Media (Image/Video)" />
              <input
                id="hero_media"
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/webp,video/mp4,video/webm"
                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                @change="handleHeroMediaChange"
              />
              <InputError :message="errors.hero_media" class="mt-2" />
              <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                <input v-model="form.remove_hero_media" type="checkbox" class="rounded border-gray-300" />
                Hapus media header saat simpan
              </label>
              <div v-if="heroMediaPreview && !form.remove_hero_media" class="mt-3">
                <video v-if="heroMediaType === 'video'" :src="heroMediaPreview" class="w-full max-w-lg rounded-lg border" controls muted playsinline />
                <img v-else :src="heroMediaPreview" alt="Hero media preview" class="w-full max-w-lg rounded-lg border object-cover" />
              </div>
            </div>

            <div>
              <InputLabel for="hero_title" value="Header Title (Optional)" />
              <TextInput
                id="hero_title"
                v-model="form.hero_title"
                type="text"
                class="mt-1 block w-full"
                :class="{ 'border-red-500': errors.hero_title }"
              />
              <InputError :message="errors.hero_title" class="mt-2" />
            </div>

            <div>
              <InputLabel for="hero_subtitle" value="Header Subtitle (Optional)" />
              <textarea
                id="hero_subtitle"
                v-model="form.hero_subtitle"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.hero_subtitle }"
              ></textarea>
              <InputError :message="errors.hero_subtitle" class="mt-2" />
            </div>
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

