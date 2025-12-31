<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import Swal from 'sweetalert2';

const props = defineProps({
  banners: {
    type: Object,
    default: () => ({ data: [] })
  }
});

// Debug: Log banner data
if (import.meta.env.DEV) {
  console.log('Banners data:', props.banners);
  if (props.banners.data && props.banners.data.length > 0) {
    console.log('First banner:', props.banners.data[0]);
  }
}

const search = ref('');
const showLightbox = ref(false);
const lightboxImages = ref([]);
const currentImageIndex = ref(0);

function handleSearch() {
  router.get('/web-profile/banners', { search: search.value }, {
    preserveState: true,
    replace: true
  });
}

async function deleteBanner(id) {
  const banner = props.banners.data.find(b => b.id === id);
  const result = await Swal.fire({
    title: 'Hapus Banner?',
    text: `Yakin ingin menghapus banner "${banner?.title || ''}"? Tindakan ini juga akan menghapus semua gambar yang terkait.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    router.delete(`/web-profile/banners/${id}`, {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire('Berhasil!', 'Banner berhasil dihapus.', 'success');
      },
      onError: () => {
        Swal.fire('Error!', 'Gagal menghapus banner.', 'error');
      }
    });
  }
}

function openLightbox(banner, imageType = 'background') {
  const images = [];
  
  // Add background image
  const bgSrc = getImageSrc(banner, 'background');
  if (bgSrc) {
    images.push(bgSrc);
  }
  
  // Add content image if exists
  const contentSrc = getImageSrc(banner, 'content');
  if (contentSrc) {
    images.push(contentSrc);
  }
  
  if (images.length > 0) {
    // Set current index based on image type
    if (imageType === 'content' && contentSrc) {
      currentImageIndex.value = images.indexOf(contentSrc);
    } else {
      currentImageIndex.value = 0;
    }
    
    lightboxImages.value = images;
    showLightbox.value = true;
  }
}

function getImageSrc(banner, type = 'background') {
  if (type === 'background') {
    // Prioritize accessor URL
    if (banner.background_image_url && banner.background_image_url !== '') {
      return banner.background_image_url;
    }
    // Fallback to path
    if (banner.background_image && banner.background_image !== '' && banner.background_image.includes('.')) {
      return `/storage/${banner.background_image}`;
    }
    return null;
  } else {
    // Content image
    if (banner.content_image_url && banner.content_image_url !== '') {
      return banner.content_image_url;
    }
    if (banner.content_image && banner.content_image !== '' && banner.content_image.includes('.')) {
      return `/storage/${banner.content_image}`;
    }
    return null;
  }
}

function getImageUrl(banner, type = 'background') {
  const src = getImageSrc(banner, type);
  return src || '/images/placeholder.jpg';
}

function handleImageError(event) {
  console.error('Image load error:', event.target.src);
  // Try to use a data URL placeholder instead
  event.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2U1ZTdlYiIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5Y2EzYWYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4=';
  event.target.onerror = null; // Prevent infinite loop
}
</script>

<template>
  <AppLayout title="Web Profile - Banners">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
          Banner Management
        </h1>
        <Link href="/web-profile/banners/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
          <i class="fa-solid fa-plus mr-2"></i> Create New Banner
        </Link>
      </div>

      <!-- Info Box -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h3 class="font-semibold text-blue-800 mb-2">
          <i class="fa-solid fa-info-circle mr-2"></i> Banner Requirements
        </h3>
        <ul class="text-sm text-blue-700 space-y-1">
          <li>• <strong>Background Image:</strong> Min 1920x1080px (16:9), Max 5MB, Format: JPG/PNG/WEBP</li>
          <li>• <strong>Content Image:</strong> Min 800x600px, Max 5MB, Format: JPG/PNG/WEBP</li>
          <li>• <strong>Maximum:</strong> 5 active banners will be displayed on homepage</li>
        </ul>
      </div>

      <!-- Search -->
      <div class="mb-4">
        <div class="flex gap-2">
          <TextInput
            v-model="search"
            type="text"
            placeholder="Search banners..."
            class="flex-1"
            @keyup.enter="handleSearch"
          />
          <PrimaryButton @click="handleSearch">Search</PrimaryButton>
        </div>
      </div>

      <!-- Banners Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preview</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtitle</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="banner in props.banners.data" :key="banner.id">
              <td class="px-6 py-4">
                <div class="flex gap-2 items-center">
                  <!-- Background Image Preview -->
                  <div v-if="(banner.background_image_url && banner.background_image_url !== '') || (banner.background_image && banner.background_image !== '')" class="relative group">
                    <img 
                      :src="getImageSrc(banner, 'background')" 
                      :alt="`${banner.title} - Background`"
                      class="w-24 h-16 object-cover rounded cursor-pointer hover:opacity-90 transition-opacity border-2 border-gray-300 shadow-sm"
                      @click="openLightbox(banner, 'background')"
                      @error="handleImageError($event)"
                    />
                    <span class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-70 text-white text-xs px-1 py-0.5 text-center rounded-b font-semibold">BG</span>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all rounded flex items-center justify-center">
                      <i class="fa-solid fa-expand text-white opacity-0 group-hover:opacity-100 transition-opacity text-sm"></i>
                    </div>
                  </div>
                  <!-- Content Image Preview -->
                  <div v-if="(banner.content_image_url && banner.content_image_url !== '') || (banner.content_image && banner.content_image !== '')" class="relative group">
                    <img 
                      :src="getImageSrc(banner, 'content')" 
                      :alt="`${banner.title} - Content`"
                      class="w-24 h-16 object-cover rounded cursor-pointer hover:opacity-90 transition-opacity border-2 border-gray-300 shadow-sm"
                      @click="openLightbox(banner, 'content')"
                      @error="handleImageError($event)"
                    />
                    <span class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-70 text-white text-xs px-1 py-0.5 text-center rounded-b font-semibold">CT</span>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all rounded flex items-center justify-center">
                      <i class="fa-solid fa-expand text-white opacity-0 group-hover:opacity-100 transition-opacity text-sm"></i>
                    </div>
                  </div>
                  <!-- Placeholder jika tidak ada image -->
                  <div v-if="!getImageSrc(banner, 'background') && !getImageSrc(banner, 'content')" class="w-24 h-16 bg-gray-100 rounded flex flex-col items-center justify-center border-2 border-dashed border-gray-300">
                    <i class="fa-solid fa-image text-gray-400 text-xl mb-1"></i>
                    <span class="text-xs text-gray-500">No Image</span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ banner.title }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-500">{{ banner.subtitle || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  :class="banner.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                >
                  {{ banner.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ banner.order }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <Link :href="`/web-profile/banners/${banner.id}/edit`" class="text-blue-600 hover:text-blue-900 mr-3">
                  <i class="fa-solid fa-edit"></i> Edit
                </Link>
                <button @click="deleteBanner(banner.id)" class="text-red-600 hover:text-red-900">
                  <i class="fa-solid fa-trash"></i> Delete
                </button>
              </td>
            </tr>
            <tr v-if="props.banners.data.length === 0">
              <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                No banners found
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="props.banners.links && props.banners.links.length > 3" class="mt-4 flex justify-center">
        <div class="flex gap-2">
          <Link
            v-for="link in props.banners.links"
            :key="link.label"
            :href="link.url || '#'"
            :class="[
              'px-4 py-2 rounded-lg',
              link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
              !link.url ? 'opacity-50 cursor-not-allowed' : ''
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>

    <!-- Lightbox -->
    <VueEasyLightbox
      :visible="showLightbox"
      :imgs="lightboxImages"
      :index="currentImageIndex"
      @hide="showLightbox = false"
    />
  </AppLayout>
</template>

