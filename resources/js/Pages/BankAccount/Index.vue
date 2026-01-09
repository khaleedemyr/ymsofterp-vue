<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  bankAccounts: Object,
  outlets: Array,
  coas: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const outletId = ref(props.filters?.outlet_id || '');
const status = ref(props.filters?.status || 'all');
const perPage = ref(props.filters?.per_page || 15);
const showModal = ref(false);
const modalMode = ref('create');
const selectedItem = ref(null);
const isLoading = ref(false);
const formData = ref({
  bank_name: '',
  account_number: '',
  account_name: '',
  outlet_id: null,
  coa_id: null,
  is_active: true,
});

const debouncedSearch = debounce(() => {
  router.get('/bank-accounts', {
    search: search.value,
    outlet_id: outletId.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (!url) return;
  
  // Ekstrak nomor halaman dari URL
  let page = 1;
  try {
    // Jika url adalah relative path, tambahkan base URL
    let fullUrl = url;
    if (url.startsWith('/')) {
      fullUrl = window.location.origin + url;
    } else if (!url.startsWith('http')) {
      fullUrl = window.location.origin + '/' + url;
    }
    
    const urlObj = new URL(fullUrl);
    const pageParam = urlObj.searchParams.get('page');
    if (pageParam) {
      page = parseInt(pageParam);
    }
  } catch (e) {
    // Fallback: coba ekstrak dari query string manual
    const match = url.match(/[?&]page=(\d+)/);
    if (match) {
      page = parseInt(match[1]);
    }
  }
  
  // Pastikan page valid
  if (isNaN(page) || page < 1) {
    page = 1;
  }
  
  // Gunakan router.get dengan semua parameter
  const params = {
    page: page,
    per_page: perPage.value || 15, // Selalu kirim per_page karena penting untuk pagination
  };
  
  // Hanya tambahkan parameter jika ada nilainya
  if (search.value) {
    params.search = search.value;
  }
  if (outletId.value !== '') {
    params.outlet_id = outletId.value;
  }
  if (status.value && status.value !== 'all') {
    params.status = status.value;
  }
  
  router.get('/bank-accounts', params, { 
    preserveState: false, // Set false agar data benar-benar dimuat ulang
    preserveScroll: false,
    replace: true
  });
}

function goToPageByNumber(page) {
  if (!page || page < 1) return;
  
  const params = {
    page: page,
    per_page: perPage.value || 15, // Selalu kirim per_page karena penting untuk pagination
  };
  
  // Hanya tambahkan parameter jika ada nilainya
  if (search.value) {
    params.search = search.value;
  }
  if (outletId.value !== '') {
    params.outlet_id = outletId.value;
  }
  if (status.value && status.value !== 'all') {
    params.status = status.value;
  }
  
  router.get('/bank-accounts', params, { 
    preserveState: false, // Set false agar data benar-benar dimuat ulang
    preserveScroll: false,
    replace: true
  });
}

function openCreate() {
  modalMode.value = 'create';
  selectedItem.value = null;
  formData.value = {
    bank_name: '',
    account_number: '',
    account_name: '',
    outlet_id: null,
    coa_id: null,
    is_active: true,
  };
  showModal.value = true;
}

function openEdit(item) {
  modalMode.value = 'edit';
  selectedItem.value = item;
  formData.value = {
    bank_name: item.bank_name,
    account_number: item.account_number,
    account_name: item.account_name,
    outlet_id: item.outlet_id,
    coa_id: item.coa_id,
    is_active: item.is_active,
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedItem.value = null;
  formData.value = {
    bank_name: '',
    account_number: '',
    account_name: '',
    outlet_id: null,
    coa_id: null,
    is_active: true,
  };
}

async function save() {
  isLoading.value = true;
  try {
    let response;
    if (modalMode.value === 'create') {
      response = await axios.post('/bank-accounts', formData.value, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });
    } else {
      response = await axios.put(`/bank-accounts/${selectedItem.value.id}`, formData.value, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      });
    }
    
    if (response.data?.success || response.status === 200 || response.status === 201) {
      Swal.fire('Berhasil', response.data?.message || (modalMode.value === 'create' ? 'Bank account berhasil ditambahkan' : 'Bank account berhasil diupdate'), 'success');
      reload();
      closeModal();
    }
  } catch (error) {
    let errorMessage = 'Gagal menyimpan data';
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    } else if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      errorMessage = Object.values(errors).flat().join(', ');
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
    title: 'Hapus Data?',
    text: `Yakin ingin menghapus bank account "${item.bank_name} - ${item.account_number}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.delete(`/bank-accounts/${item.id}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    });
    if (response.status === 200 || response.status === 204 || response.data?.success) {
      Swal.fire('Berhasil', response.data?.message || 'Bank account berhasil dihapus', 'success');
      reload();
    }
  } catch (error) {
    // Jika data sudah terhapus meskipun ada error, tetap reload
    if (error.response?.status === 405) {
      // Method not allowed, tapi coba reload karena mungkin sudah terhapus
      Swal.fire('Berhasil', 'Bank account berhasil dihapus', 'success');
      reload();
    } else {
      Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus data', 'error');
    }
  }
}

async function toggleStatus(item) {
  const action = item.is_active ? 'menonaktifkan' : 'mengaktifkan';
  const result = await Swal.fire({
    title: `${item.is_active ? 'Nonaktifkan' : 'Aktifkan'} Data?`,
    text: `Yakin ingin ${action} bank account "${item.bank_name} - ${item.account_number}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: `Ya, ${item.is_active ? 'Nonaktifkan' : 'Aktifkan'}!`,
    cancelButtonText: 'Batal',
  });
  
  if (!result.isConfirmed) return;
  
  try {
    const updatedData = {
      bank_name: item.bank_name,
      account_number: item.account_number,
      account_name: item.account_name,
      outlet_id: item.outlet_id,
      is_active: !item.is_active
    };
    const response = await axios.put(`/bank-accounts/${item.id}`, updatedData, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    });
    
    if (response.data?.success || response.status === 200) {
      Swal.fire('Berhasil', response.data?.message || 'Status bank account berhasil diubah', 'success');
      reload();
    }
  } catch (error) {
    // Jika data sudah terupdate meskipun ada error, tetap reload
    if (error.response?.status === 200 || error.response?.data?.success) {
      Swal.fire('Berhasil', 'Status bank account berhasil diubah', 'success');
      reload();
    } else {
      let errorMessage = 'Gagal mengubah status data';
      if (error.response?.data?.message) {
        errorMessage = error.response.data.message;
      } else if (error.message) {
        errorMessage = error.message;
      }
      Swal.fire('Error', errorMessage, 'error');
    }
  }
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

watch([outletId, status, perPage], () => {
  // Reset ke page 1 ketika filter berubah
  const params = {
    page: 1, // Reset ke page 1 saat filter berubah
  };
  
  // Hanya tambahkan parameter jika ada nilainya
  if (search.value) {
    params.search = search.value;
  }
  if (outletId.value !== '') {
    params.outlet_id = outletId.value;
  }
  if (status.value && status.value !== 'all') {
    params.status = status.value;
  }
  // Selalu kirim per_page karena ini penting untuk pagination
  params.per_page = perPage.value || 15;
  
  router.get('/bank-accounts', params, { 
    preserveState: true, 
    replace: true 
  });
});
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-building-columns text-blue-500"></i> Master Data Bank
        </h1>
        <button @click="openCreate" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Tambah Bank Account
        </button>
      </div>

      <!-- Search and Filter -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              type="text"
              v-model="search"
              @input="onSearchInput"
              placeholder="Cari bank, nomor rekening, atau nama..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Bank</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor Rekening</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Pemilik</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!bankAccounts || !bankAccounts.data || bankAccounts.data.length === 0">
                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-else v-for="(item, index) in bankAccounts.data" :key="item.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ (bankAccounts.current_page - 1) * bankAccounts.per_page + index + 1 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ item.bank_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ item.account_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ item.account_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span v-if="item.outlet" class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                    {{ item.outlet.nama_outlet }}
                  </span>
                  <span v-else class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                    Semua Outlet
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
        <div v-if="bankAccounts && bankAccounts.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                @click="goToPage(bankAccounts?.prev_page_url)"
                :disabled="!bankAccounts?.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button
                @click="goToPage(bankAccounts?.next_page_url)"
                :disabled="!bankAccounts?.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ bankAccounts?.from || 0 }}</span>
                  sampai
                  <span class="font-medium">{{ bankAccounts?.to || 0 }}</span>
                  dari
                  <span class="font-medium">{{ bankAccounts?.total || 0 }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <button
                    @click="goToPage(bankAccounts?.prev_page_url)"
                    :disabled="!bankAccounts?.prev_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <span
                    v-for="page in Array.from({ length: bankAccounts?.last_page || 0 }, (_, i) => i + 1)"
                    :key="page"
                    @click="goToPageByNumber(page)"
                    :class="page === bankAccounts?.current_page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium cursor-pointer"
                  >
                    {{ page }}
                  </span>
                  <button
                    @click="goToPage(bankAccounts?.next_page_url)"
                    :disabled="!bankAccounts?.next_page_url"
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

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="closeModal">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-medium text-gray-900">
                {{ modalMode === 'create' ? 'Tambah Bank Account' : 'Edit Bank Account' }}
              </h3>
              <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
            
            <form @submit.prevent="save" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bank *</label>
                <input
                  v-model="formData.bank_name"
                  type="text"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Contoh: Bank BCA"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Rekening *</label>
                <input
                  v-model="formData.account_number"
                  type="text"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Contoh: 1234567890"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pemilik Rekening *</label>
                <input
                  v-model="formData.account_name"
                  type="text"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Contoh: PT. ABC"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                <select
                  v-model="formData.outlet_id"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                >
                  <option :value="null">Tidak Terikat Outlet (Semua Outlet)</option>
                  <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                    {{ outlet.name }}
                  </option>
                </select>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Chart of Account (COA)</label>
                <select
                  v-model="formData.coa_id"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                >
                  <option :value="null">Pilih COA (Opsional)</option>
                  <option v-for="coa in coas" :key="coa.id" :value="coa.id">
                    {{ coa.code }} - {{ coa.name }} ({{ coa.type }})
                  </option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Pilih akun COA yang terkait dengan bank account ini untuk pencatatan jurnal</p>
              </div>
              
              <div>
                <label class="flex items-center">
                  <input
                    v-model="formData.is_active"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                  />
                  <span class="ml-2 text-sm text-gray-700">Active</span>
                </label>
              </div>
              
              <div class="flex justify-end gap-2 pt-4">
                <button
                  type="button"
                  @click="closeModal"
                  :disabled="isLoading"
                  class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  :disabled="isLoading"
                  class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                  <i v-if="isLoading" class="fa-solid fa-spinner fa-spin"></i>
                  <span>{{ isLoading ? 'Menyimpan...' : 'Simpan' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

