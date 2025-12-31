<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  pages: {
    type: Object,
    default: () => ({ data: [] })
  }
});

const search = ref('');

function handleSearch() {
  router.get('/web-profile', { search: search.value }, {
    preserveState: true,
    replace: true
  });
}

async function deletePage(id) {
  const page = props.pages.data.find(p => p.id === id);
  const result = await Swal.fire({
    title: 'Hapus Page?',
    text: `Yakin ingin menghapus page "${page?.title || ''}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    router.delete(`/web-profile/${id}`, {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire('Berhasil!', 'Page berhasil dihapus.', 'success');
      },
      onError: () => {
        Swal.fire('Error!', 'Gagal menghapus page.', 'error');
      }
    });
  }
}
</script>

<template>
  <AppLayout title="Web Profile - Pages">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
          Web Profile Pages
        </h1>
        <div class="flex gap-2">
          <Link href="/web-profile/banners" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
            <i class="fa-solid fa-images mr-2"></i> Manage Banners
          </Link>
          <Link href="/web-profile/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
            <i class="fa-solid fa-plus mr-2"></i> Create New Page
          </Link>
        </div>
      </div>

      <!-- Search -->
      <div class="mb-4">
        <div class="flex gap-2">
          <TextInput
            v-model="search"
            type="text"
            placeholder="Search pages..."
            class="flex-1"
            @keyup.enter="handleSearch"
          />
          <PrimaryButton @click="handleSearch">Search</PrimaryButton>
        </div>
      </div>

      <!-- Pages Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="page in pages.data" :key="page.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ page.title }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-500">{{ page.slug }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  :class="page.is_published ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                >
                  {{ page.is_published ? 'Published' : 'Draft' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ page.order }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <Link :href="`/web-profile/${page.id}/edit`" class="text-blue-600 hover:text-blue-900 mr-3">
                  <i class="fa-solid fa-edit"></i> Edit
                </Link>
                <button @click="deletePage(page.id)" class="text-red-600 hover:text-red-900">
                  <i class="fa-solid fa-trash"></i> Delete
                </button>
              </td>
            </tr>
            <tr v-if="pages.data.length === 0">
              <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                No pages found
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pages.links && pages.links.length > 3" class="mt-4 flex justify-center">
        <div class="flex gap-2">
          <Link
            v-for="link in pages.links"
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

