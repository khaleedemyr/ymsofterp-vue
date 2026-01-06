<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-calendar-check text-blue-500"></i> Maintenance Schedules
        </h1>
        <button @click="openCreate" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Tambah Schedule
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Maintenance Type</label>
            <select
              v-model="maintenanceType"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Tipe</option>
              <option value="Cleaning">Cleaning</option>
              <option value="Service">Service</option>
              <option value="Repair">Repair</option>
              <option value="Inspection">Inspection</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="isActive"
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequency</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="schedules.data && schedules.data.length === 0">
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="(item, index) in schedules.data" :key="item.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ (schedules.current_page - 1) * schedules.per_page + index + 1 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <p class="text-sm font-medium text-blue-600">{{ item.asset?.asset_code }}</p>
                    <p class="text-xs text-gray-500">{{ item.asset?.name }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ item.maintenance_type }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ item.frequency }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span :class="isDue(item.next_maintenance_date) ? 'text-red-600 font-semibold' : ''">
                    {{ formatDate(item.next_maintenance_date) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(item.last_maintenance_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="item.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 rounded-full text-xs font-medium">
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
        <div v-if="schedules.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                @click="goToPage(schedules.prev_page_url)"
                :disabled="!schedules.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button
                @click="goToPage(schedules.next_page_url)"
                :disabled="!schedules.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ schedules.from }}</span>
                  sampai
                  <span class="font-medium">{{ schedules.to }}</span>
                  dari
                  <span class="font-medium">{{ schedules.total }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <button
                    @click="goToPage(schedules.prev_page_url)"
                    :disabled="!schedules.prev_page_url"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    Page {{ schedules.current_page }} of {{ schedules.last_page }}
                  </span>
                  <button
                    @click="goToPage(schedules.next_page_url)"
                    :disabled="!schedules.next_page_url"
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
  schedules: Object,
  assets: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const assetId = ref(props.filters?.asset_id || '');
const maintenanceType = ref(props.filters?.maintenance_type || '');
const isActive = ref(props.filters?.is_active || 'all');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/asset-management/maintenance-schedules', {
    search: search.value,
    asset_id: assetId.value,
    maintenance_type: maintenanceType.value,
    is_active: isActive.value,
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
    urlObj.searchParams.set('maintenance_type', maintenanceType.value);
    urlObj.searchParams.set('is_active', isActive.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/asset-management/maintenance-schedules/create');
}

function openEdit(item) {
  router.visit(`/asset-management/maintenance-schedules/${item.id}/edit`);
}

async function toggleStatus(item) {
  try {
    const response = await axios.patch(`/asset-management/maintenance-schedules/${item.id}/toggle-status`, {}, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });
    
    if (response.data?.success) {
      Swal.fire('Berhasil', 'Status berhasil diubah', 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal mengubah status', 'error');
  }
}

async function hapus(item) {
  const result = await Swal.fire({
    title: 'Apakah Anda yakin?',
    text: `Schedule maintenance untuk "${item.asset?.name}" akan dihapus`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/asset-management/maintenance-schedules/${item.id}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success || response.status === 200) {
        Swal.fire('Berhasil', 'Schedule berhasil dihapus', 'success');
        reload();
      }
    } catch (error) {
      Swal.fire('Error', 'Gagal menghapus schedule', 'error');
    }
  }
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function isDue(date) {
  if (!date) return false;
  const dueDate = new Date(date);
  const today = new Date();
  return dueDate <= today;
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

watch([assetId, maintenanceType, isActive, perPage], () => {
  router.get('/asset-management/maintenance-schedules', {
    search: search.value,
    asset_id: assetId.value,
    maintenance_type: maintenanceType.value,
    is_active: isActive.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});
</script>

