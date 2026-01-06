<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-trash text-blue-500"></i> Asset Disposals
        </h1>
        <button @click="openCreate" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Request Disposal
        </button>
      </div>

      <!-- Search and Filter -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              type="text"
              v-model="search"
              @input="onSearchInput"
              placeholder="Cari asset..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Method</label>
            <select
              v-model="disposalMethod"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Method</option>
              <option value="Sold">Sold</option>
              <option value="Broken">Broken</option>
              <option value="Donated">Donated</option>
              <option value="Scrapped">Scrapped</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="status"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">Semua Status</option>
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Completed">Completed</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input
              type="date"
              v-model="dateFrom"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Disposal Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested By</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="disposals.data && disposals.data.length === 0">
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="(item, index) in disposals.data" :key="item.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ (disposals.current_page - 1) * disposals.per_page + index + 1 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <p class="text-sm font-medium text-blue-600">{{ item.asset?.asset_code }}</p>
                    <p class="text-xs text-gray-500">{{ item.asset?.name }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(item.disposal_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ item.disposal_method }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                  {{ formatCurrency(item.disposal_value) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ item.requester?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadgeClass(item.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ item.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center gap-2">
                    <button
                      @click="viewDisposal(item)"
                      class="text-green-600 hover:text-green-900"
                      title="View"
                    >
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <button
                      v-if="item.status === 'Pending'"
                      @click="approveDisposal(item)"
                      class="text-blue-600 hover:text-blue-900"
                      title="Approve"
                    >
                      <i class="fa-solid fa-check"></i>
                    </button>
                    <button
                      v-if="item.status === 'Approved'"
                      @click="completeDisposal(item)"
                      class="text-green-600 hover:text-green-900"
                      title="Complete"
                    >
                      <i class="fa-solid fa-check-circle"></i>
                    </button>
                    <button
                      v-if="item.status === 'Pending'"
                      @click="rejectDisposal(item)"
                      class="text-red-600 hover:text-red-900"
                      title="Reject"
                    >
                      <i class="fa-solid fa-times"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="disposals.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                @click="goToPage(disposals.prev_page_url)"
                :disabled="!disposals.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button
                @click="goToPage(disposals.next_page_url)"
                :disabled="!disposals.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ disposals.from }}</span>
                  sampai
                  <span class="font-medium">{{ disposals.to }}</span>
                  dari
                  <span class="font-medium">{{ disposals.total }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <button
                    @click="goToPage(disposals.prev_page_url)"
                    :disabled="!disposals.prev_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    Page {{ disposals.current_page }} of {{ disposals.last_page }}
                  </span>
                  <button
                    @click="goToPage(disposals.next_page_url)"
                    :disabled="!disposals.next_page_url"
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
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  disposals: Object,
  assets: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const assetId = ref(props.filters?.asset_id || '');
const disposalMethod = ref(props.filters?.disposal_method || '');
const status = ref(props.filters?.status || 'all');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/asset-management/disposals', {
    search: search.value,
    asset_id: assetId.value,
    disposal_method: disposalMethod.value,
    status: status.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
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
    urlObj.searchParams.set('asset_id', assetId.value);
    urlObj.searchParams.set('disposal_method', disposalMethod.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('date_from', dateFrom.value);
    urlObj.searchParams.set('date_to', dateTo.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/asset-management/disposals/create');
}

function viewDisposal(item) {
  router.visit(`/asset-management/disposals/${item.id}`);
}

async function approveDisposal(item) {
  const result = await Swal.fire({
    title: 'Approve Disposal?',
    text: `Approve disposal untuk asset "${item.asset?.name}"?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Approve',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/asset-management/disposals/${item.id}/approve`, {}, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success) {
        Swal.fire('Berhasil', 'Disposal berhasil di-approve', 'success');
        reload();
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal approve disposal', 'error');
    }
  }
}

async function rejectDisposal(item) {
  const { value: reason } = await Swal.fire({
    title: 'Reject Disposal?',
    input: 'textarea',
    inputLabel: 'Alasan Rejection',
    inputPlaceholder: 'Masukkan alasan rejection...',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Reject',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      if (!value) {
        return 'Alasan rejection harus diisi!'
      }
    }
  });

  if (reason) {
    try {
      const response = await axios.post(`/asset-management/disposals/${item.id}/reject`, {
        rejection_reason: reason
      }, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success) {
        Swal.fire('Berhasil', 'Disposal berhasil di-reject', 'success');
        reload();
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal reject disposal', 'error');
    }
  }
}

async function completeDisposal(item) {
  const result = await Swal.fire({
    title: 'Complete Disposal?',
    text: `Konfirmasi bahwa asset sudah di-dispose?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Complete',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/asset-management/disposals/${item.id}/complete`, {}, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success) {
        Swal.fire('Berhasil', 'Disposal berhasil di-complete', 'success');
        reload();
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal complete disposal', 'error');
    }
  }
}

function getStatusBadgeClass(status) {
  const classes = {
    'Pending': 'bg-yellow-100 text-yellow-800',
    'Approved': 'bg-green-100 text-green-800',
    'Completed': 'bg-blue-100 text-blue-800',
    'Rejected': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function formatCurrency(value) {
  if (value == null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', { 
    style: 'currency', 
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

watch([assetId, disposalMethod, status, dateFrom, dateTo, perPage], () => {
  router.get('/asset-management/disposals', {
    search: search.value,
    asset_id: assetId.value,
    disposal_method: disposalMethod.value,
    status: status.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});
</script>

