<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-file text-blue-500"></i> Asset Documents
        </h1>
        <button @click="openCreate" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Upload Document
        </button>
      </div>

      <!-- Search and Filter -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Asset</label>
            <select
              v-model="assetId"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Asset</option>
              <option v-for="asset in assets" :key="asset.id" :value="asset.id">
                {{ asset.asset_code }} - {{ asset.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
            <select
              v-model="documentType"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Tipe</option>
              <option value="Invoice">Invoice</option>
              <option value="Warranty">Warranty</option>
              <option value="Manual">Manual</option>
              <option value="Maintenance Record">Maintenance Record</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
            <select
              v-model="perPage"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="25">25</option>
              <option value="50">50</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Size</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded At</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="documents.data && documents.data.length === 0">
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="(item, index) in documents.data" :key="item.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ (documents.current_page - 1) * documents.per_page + index + 1 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <p class="text-sm font-medium text-blue-600">{{ item.asset?.asset_code }}</p>
                    <p class="text-xs text-gray-500">{{ item.asset?.name }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ item.document_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">
                    {{ item.document_type }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatFileSize(item.file_size) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ item.uploader?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(item.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center gap-2">
                    <a
                      :href="`/asset-management/documents/${item.id}/download`"
                      class="text-blue-600 hover:text-blue-900"
                      title="Download"
                    >
                      <i class="fa-solid fa-download"></i>
                    </a>
                    <button
                      @click="hapus(item)"
                      class="text-red-600 hover:text-red-900"
                      title="Hapus"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="documents.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                @click="goToPage(documents.prev_page_url)"
                :disabled="!documents.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button
                @click="goToPage(documents.next_page_url)"
                :disabled="!documents.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ documents.from }}</span>
                  sampai
                  <span class="font-medium">{{ documents.to }}</span>
                  dari
                  <span class="font-medium">{{ documents.total }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <button
                    @click="goToPage(documents.prev_page_url)"
                    :disabled="!documents.prev_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    Page {{ documents.current_page }} of {{ documents.last_page }}
                  </span>
                  <button
                    @click="goToPage(documents.next_page_url)"
                    :disabled="!documents.next_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  documents: Object,
  assets: Array,
  filters: Object,
});

const assetId = ref(props.filters?.asset_id || '');
const documentType = ref(props.filters?.document_type || '');
const perPage = ref(props.filters?.per_page || 15);

function goToPage(url) {
  if (url) {
    const urlObj = new URL(url);
    urlObj.searchParams.set('asset_id', assetId.value);
    urlObj.searchParams.set('document_type', documentType.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/asset-management/documents/create');
}

async function hapus(item) {
  const result = await Swal.fire({
    title: 'Apakah Anda yakin?',
    text: `Document "${item.document_name}" akan dihapus`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/asset-management/documents/${item.id}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success || response.status === 200) {
        Swal.fire('Berhasil', 'Document berhasil dihapus', 'success');
        reload();
      }
    } catch (error) {
      Swal.fire('Error', 'Gagal menghapus document', 'error');
    }
  }
}

function formatFileSize(bytes) {
  if (!bytes) return '-';
  const units = ['B', 'KB', 'MB', 'GB'];
  let size = bytes;
  let unitIndex = 0;
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex++;
  }
  return `${size.toFixed(2)} ${units[unitIndex]}`;
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

watch([assetId, documentType, perPage], () => {
  router.get('/asset-management/documents', {
    asset_id: assetId.value,
    document_type: documentType.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});
</script>

