<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  brands: {
    type: Object,
    default: () => ({ data: [] })
  }
});

const search = ref('');

function handleSearch() {
  router.get('/web-profile/brands', { search: search.value }, {
    preserveState: true,
    replace: true
  });
}

async function deleteBrand(id) {
  const brand = props.brands.data.find(b => b.id === id);
  const result = await Swal.fire({
    title: 'Hapus Brand?',
    text: `Yakin ingin menghapus brand "${brand?.title || ''}"? Tindakan ini juga akan menghapus semua file yang terkait.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    router.delete(`/web-profile/brands/${id}`, {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire('Berhasil!', 'Brand berhasil dihapus.', 'success');
      },
      onError: () => {
        Swal.fire('Error!', 'Gagal menghapus brand.', 'error');
      }
    });
  }
}
</script>

<template>
  <AppLayout title="Web Profile - Brands">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
          Brand Management
        </h1>
        <Link href="/web-profile/brands/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
          <i class="fa-solid fa-plus mr-2"></i> Create New Brand
        </Link>
      </div>

      <!-- Search -->
      <div class="mb-4">
        <div class="flex gap-2">
          <TextInput
            v-model="search"
            type="text"
            placeholder="Search brands..."
            class="flex-1"
            @keyup.enter="handleSearch"
          />
          <PrimaryButton @click="handleSearch">Search</PrimaryButton>
        </div>
      </div>

      <!-- Brands Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="brand in props.brands.data" :key="brand.id" class="bg-white rounded-lg shadow overflow-hidden hover:shadow-lg transition-shadow">
          <!-- Thumbnail -->
          <div class="relative h-48 bg-gray-200">
            <img 
              v-if="brand.thumbnail_url"
              :src="brand.thumbnail_url" 
              :alt="brand.title"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <i class="fa-solid fa-image text-gray-400 text-4xl"></i>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ brand.title }}</h3>
            <p v-if="brand.slug" class="text-sm text-gray-500 mb-3">{{ brand.slug }}</p>
            
            <!-- Actions -->
            <div class="flex gap-2 mt-4">
              <Link :href="`/web-profile/brands/${brand.id}/edit`" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-center text-sm">
                <i class="fa-solid fa-edit mr-2"></i> Edit
              </Link>
              <button @click="deleteBrand(brand.id)" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">
                <i class="fa-solid fa-trash mr-2"></i> Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="props.brands.data.length === 0" class="text-center py-12">
        <i class="fa-solid fa-store text-gray-400 text-6xl mb-4"></i>
        <p class="text-gray-500 text-lg">No brands found</p>
      </div>

      <!-- Pagination -->
      <div v-if="props.brands.links && props.brands.links.length > 3" class="mt-6 flex justify-center">
        <div class="flex gap-2">
          <Link
            v-for="link in props.brands.links"
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
  </AppLayout>
</template>

