<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-tags text-blue-500"></i> Asset Categories
        </h1>
        <button @click="openCreate" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Tambah Kategori
        </button>
      </div>

      <!-- Search and Filter -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              type="text"
              v-model="search"
              @input="onSearchInput"
              placeholder="Cari kode atau nama kategori..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="status"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">Semua Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="categories.data && categories.data.length === 0">
                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="(item, index) in categories.data" :key="item.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ (categories.current_page - 1) * categories.per_page + index + 1 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ item.code }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ item.name }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500">
                  {{ item.description || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                    {{ item.assets_count || 0 }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span 
                    :class="item.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                    class="px-2 py-1 rounded-full text-xs font-medium"
                  >
                    {{ item.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center gap-2">
                    <button
                      @click="openEdit(item)"
                      class="text-blue-600 hover:text-blue-900"
                      title="Edit"
                    >
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button
                      @click="toggleStatus(item)"
                      :class="item.is_active ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900'"
                      :title="item.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                    >
                      <i :class="item.is_active ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'"></i>
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
        <div v-if="categories.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                @click="goToPage(categories.prev_page_url)"
                :disabled="!categories.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button
                @click="goToPage(categories.next_page_url)"
                :disabled="!categories.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ categories.from }}</span>
                  sampai
                  <span class="font-medium">{{ categories.to }}</span>
                  dari
                  <span class="font-medium">{{ categories.total }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <button
                    @click="goToPage(categories.prev_page_url)"
                    :disabled="!categories.prev_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    Page {{ categories.current_page }} of {{ categories.last_page }}
                  </span>
                  <button
                    @click="goToPage(categories.next_page_url)"
                    :disabled="!categories.next_page_url"
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

      <!-- Modal Create/Edit -->
      <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="closeModal">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-900">
              {{ modalMode === 'create' ? 'Tambah Kategori' : 'Edit Kategori' }}
            </h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times"></i>
            </button>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Kode
                  <span v-if="modalMode === 'edit'" class="text-red-500">*</span>
                  <span v-else class="text-gray-500 text-xs">(Kosongkan untuk auto-generate)</span>
                </label>
                <input
                  type="text"
                  v-model="formData.code"
                  :placeholder="modalMode === 'create' ? 'Kosongkan untuk auto-generate (CAT001, CAT002, ...)' : 'Masukkan kode kategori'"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                  :disabled="modalMode === 'edit'"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Nama <span class="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="formData.name"
                  placeholder="Masukkan nama kategori"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Deskripsi
                </label>
                <textarea
                  v-model="formData.description"
                  placeholder="Masukkan deskripsi kategori"
                  rows="3"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                ></textarea>
              </div>
              <div>
                <label class="flex items-center gap-2">
                  <input
                    type="checkbox"
                    v-model="formData.is_active"
                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                  />
                  <span class="text-sm font-medium text-gray-700">Active</span>
                </label>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <button
              @click="closeModal"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              @click="save"
              :disabled="isLoading"
              class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center gap-2 disabled:opacity-50"
            >
              <i v-if="isLoading" class="fa-solid fa-spinner fa-spin"></i>
              <span>{{ isLoading ? 'Menyimpan...' : 'Simpan' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  categories: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const perPage = ref(props.filters?.per_page || 15);
const showModal = ref(false);
const modalMode = ref('create');
const selectedItem = ref(null);
const isLoading = ref(false);
const formData = ref({
  code: '',
  name: '',
  description: '',
  is_active: true,
});

const debouncedSearch = debounce(() => {
  router.get('/asset-management/categories', {
    search: search.value,
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
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  modalMode.value = 'create';
  selectedItem.value = null;
  formData.value = {
    code: '',
    name: '',
    description: '',
    is_active: true,
  };
  showModal.value = true;
}

function openEdit(item) {
  modalMode.value = 'edit';
  selectedItem.value = item;
  formData.value = {
    code: item.code,
    name: item.name,
    description: item.description || '',
    is_active: item.is_active,
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedItem.value = null;
  formData.value = {
    code: '',
    name: '',
    description: '',
    is_active: true,
  };
}

async function save() {
  isLoading.value = true;
  try {
    let response;
    if (modalMode.value === 'create') {
      response = await axios.post('/asset-management/categories', formData.value, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
    } else {
      response = await axios.put(`/asset-management/categories/${selectedItem.value.id}`, formData.value, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
    }
    
    if (response.data?.success || response.status === 200 || response.status === 201) {
      Swal.fire('Berhasil', response.data?.message || 'Kategori berhasil disimpan', 'success');
      closeModal();
      reload();
    }
  } catch (error) {
    let errorMessage = 'Gagal menyimpan data';
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    } else if (error.response?.data?.errors) {
      const errors = Object.values(error.response.data.errors).flat();
      errorMessage = errors.join(', ');
    } else if (error.message) {
      errorMessage = error.message;
    }
    Swal.fire('Error', errorMessage, 'error');
  } finally {
    isLoading.value = false;
  }
}

async function hapus(item) {
  const result = await Swal.fire({
    title: 'Apakah Anda yakin?',
    text: `Kategori "${item.name}" akan dihapus`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/asset-management/categories/${item.id}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success || response.status === 200) {
        Swal.fire('Berhasil', 'Kategori berhasil dihapus', 'success');
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

async function toggleStatus(item) {
  try {
    const response = await axios.patch(`/asset-management/categories/${item.id}/toggle-status`, {}, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });
    
    if (response.data?.success || response.status === 200) {
      Swal.fire('Berhasil', 'Status kategori berhasil diubah', 'success');
      reload();
    }
  } catch (error) {
    let errorMessage = 'Gagal mengubah status data';
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    }
    Swal.fire('Error', errorMessage, 'error');
  }
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

watch([status, perPage], () => {
  router.get('/asset-management/categories', {
    search: search.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});
</script>

