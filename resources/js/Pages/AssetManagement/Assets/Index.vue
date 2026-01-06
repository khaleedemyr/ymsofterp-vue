<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-boxes text-blue-500"></i> Assets
        </h1>
        <button @click="openCreate" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Tambah Asset
        </button>
      </div>

      <!-- Search and Filter -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              type="text"
              v-model="search"
              @input="onSearchInput"
              placeholder="Cari asset code, nama, brand..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select
              v-model="categoryId"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Kategori</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select
              v-model="outletId"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Outlet</option>
              <option value="null">Tidak Terikat Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="status"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">Semua Status</option>
              <option value="Active">Active</option>
              <option value="Maintenance">Maintenance</option>
              <option value="Disposed">Disposed</option>
              <option value="Lost">Lost</option>
              <option value="Transfer">Transfer</option>
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand/Model</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="assets.data && assets.data.length === 0">
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="(item, index) in assets.data" :key="item.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ (assets.current_page - 1) * assets.per_page + index + 1 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                  {{ item.asset_code }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ item.name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span v-if="item.category" class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">
                    {{ item.category.name }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <div v-if="item.brand || item.model">
                    <div v-if="item.brand" class="font-medium">{{ item.brand }}</div>
                    <div v-if="item.model" class="text-xs text-gray-400">{{ item.model }}</div>
                  </div>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span v-if="item.current_outlet" class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                    {{ item.current_outlet.name }}
                  </span>
                  <span v-else class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                    Tidak Terikat
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span 
                    :class="getStatusBadgeClass(item.status)"
                    class="px-2 py-1 rounded-full text-xs font-medium"
                  >
                    {{ item.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center gap-2">
                    <button
                      @click="viewAsset(item)"
                      class="text-green-600 hover:text-green-900"
                      title="View"
                    >
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <button
                      @click="openEdit(item)"
                      class="text-blue-600 hover:text-blue-900"
                      title="Edit"
                    >
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button
                      @click="generateQrCode(item)"
                      class="text-purple-600 hover:text-purple-900"
                      title="Generate QR Code"
                    >
                      <i class="fa-solid fa-qrcode"></i>
                    </button>
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
        <div v-if="assets.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                @click="goToPage(assets.prev_page_url)"
                :disabled="!assets.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button
                @click="goToPage(assets.next_page_url)"
                :disabled="!assets.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ assets.from }}</span>
                  sampai
                  <span class="font-medium">{{ assets.to }}</span>
                  dari
                  <span class="font-medium">{{ assets.total }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <button
                    @click="goToPage(assets.prev_page_url)"
                    :disabled="!assets.prev_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    Page {{ assets.current_page }} of {{ assets.last_page }}
                  </span>
                  <button
                    @click="goToPage(assets.next_page_url)"
                    :disabled="!assets.next_page_url"
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
import { router, Link } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  assets: Object,
  categories: Array,
  outlets: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const categoryId = ref(props.filters?.category_id || '');
const outletId = ref(props.filters?.outlet_id || '');
const status = ref(props.filters?.status || 'all');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/asset-management/assets', {
    search: search.value,
    category_id: categoryId.value,
    outlet_id: outletId.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) {
    const urlObj = new URL(url);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('category_id', categoryId.value);
    urlObj.searchParams.set('outlet_id', outletId.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function viewAsset(item) {
  router.visit(`/asset-management/assets/${item.id}`);
}

function openCreate() {
  router.visit('/asset-management/assets/create');
}

function openEdit(item) {
  router.visit(`/asset-management/assets/${item.id}/edit`);
}

async function generateQrCode(item) {
  try {
    const response = await axios.post(`/asset-management/assets/${item.id}/generate-qr-code`, {}, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });
    
    if (response.data?.success) {
      Swal.fire('Berhasil', 'QR Code berhasil di-generate', 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal generate QR Code', 'error');
  }
}

async function hapus(item) {
  const result = await Swal.fire({
    title: 'Apakah Anda yakin?',
    text: `Asset "${item.name}" akan dihapus`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/asset-management/assets/${item.id}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success || response.status === 200) {
        Swal.fire('Berhasil', 'Asset berhasil dihapus', 'success');
        reload();
      }
    } catch (error) {
      let errorMessage = 'Gagal menghapus data';
      if (error.response?.data?.message) {
        errorMessage = error.response.data.message;
      }
      Swal.fire('Error', errorMessage, 'error');
    }
  }
}

function getStatusBadgeClass(status) {
  const classes = {
    'Active': 'bg-green-100 text-green-800',
    'Maintenance': 'bg-yellow-100 text-yellow-800',
    'Disposed': 'bg-red-100 text-red-800',
    'Lost': 'bg-gray-100 text-gray-800',
    'Transfer': 'bg-blue-100 text-blue-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

watch([categoryId, outletId, status, perPage], () => {
  router.get('/asset-management/assets', {
    search: search.value,
    category_id: categoryId.value,
    outlet_id: outletId.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});
</script>

