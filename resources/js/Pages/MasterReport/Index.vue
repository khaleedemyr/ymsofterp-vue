<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import MasterReportFormModal from './MasterReportFormModal.vue';
import axios from 'axios';

const props = defineProps({
  data: Object, // { data, links, meta }
  filters: Object,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      active: 0,
      inactive: 0
    })
  },
  departemens: Array,
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedItem = ref(null);
const type = ref(props.filters?.type || 'departemen'); // 'departemen' | 'area'
const status = ref(props.filters?.status || 'A');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/master-report', {
    search: search.value,
    type: type.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) {
    // Parse URL untuk menambahkan parameter filter yang hilang
    const urlObj = new URL(url);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('type', type.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('per_page', perPage.value);
    
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  modalMode.value = 'create';
  selectedItem.value = null;
  showModal.value = true;
}

function openEdit(item) {
  modalMode.value = 'edit';
  selectedItem.value = item;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedItem.value = null;
}

async function hapus(item) {
  const itemName = type.value === 'departemen' ? item.nama_departemen : item.nama_area;
  const result = await Swal.fire({
    title: 'Hapus Data?',
    text: `Yakin ingin menghapus ${type.value === 'departemen' ? 'departemen' : 'area'} "${itemName}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.delete(`/master-report/${item.id}?type=${type.value}`);
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus data', 'error');
  }
}

async function toggleStatus(item) {
  const action = item.status === 'A' ? 'menonaktifkan' : 'mengaktifkan';
  const itemName = type.value === 'departemen' ? item.nama_departemen : item.nama_area;
  const result = await Swal.fire({
    title: `${item.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'} Data?`,
    text: `Yakin ingin ${action} ${type.value === 'departemen' ? 'departemen' : 'area'} "${itemName}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: `Ya, ${item.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'}!`,
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.patch(`/master-report/${item.id}/toggle-status?type=${type.value}`);
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal mengubah status data', 'error');
  }
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function filterByStatus(newStatus) {
  status.value = newStatus;
}

function onModalSuccess(message) {
  Swal.fire('Berhasil', message, 'success');
  reload();
  closeModal();
}

watch([type, status, perPage], () => {
  router.get('/master-report', {
    search: search.value,
    type: type.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});
</script>

<template>
  <AppLayout title="Master Report">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-blue-500"></i> Master Report
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah {{ type === 'departemen' ? 'Departemen' : 'Area' }} Baru
        </button>
      </div>

      <!-- Type Toggle -->
      <div class="mb-6">
        <div class="flex bg-gray-100 rounded-lg p-1 w-fit">
          <button 
            @click="type = 'departemen'"
            :class="[
              'px-4 py-2 rounded-md font-medium transition-all',
              type === 'departemen' 
                ? 'bg-white text-blue-600 shadow-sm' 
                : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            <i class="fa-solid fa-building mr-2"></i>Departemen
          </button>
          <button 
            @click="type = 'area'"
            :class="[
              'px-4 py-2 rounded-md font-medium transition-all',
              type === 'area' 
                ? 'bg-white text-blue-600 shadow-sm' 
                : 'text-gray-600 hover:text-gray-800'
            ]"
          >
            <i class="fa-solid fa-map-marker-alt mr-2"></i>Area
          </button>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <!-- Total -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'all' ? 'bg-blue-50 border-blue-500 shadow-xl' : 'bg-white border-blue-500 hover:shadow-xl'
        ]" @click="filterByStatus('all')" :title="`Klik untuk melihat semua ${type === 'departemen' ? 'departemen' : 'area'}`">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total {{ type === 'departemen' ? 'Departemen' : 'Area' }}</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
              <p class="text-xs text-gray-500">100% dari total</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
              <i :class="type === 'departemen' ? 'fa-solid fa-building text-blue-600 text-xl' : 'fa-solid fa-map-marker-alt text-blue-600 text-xl'"></i>
            </div>
          </div>
          <div class="absolute top-2 right-2 text-xs text-gray-400">
            <i class="fa-solid fa-mouse-pointer"></i>
          </div>
        </div>

        <!-- Aktif -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'A' ? 'bg-green-50 border-green-500 shadow-xl' : 'bg-white border-green-500 hover:shadow-xl'
        ]" @click="filterByStatus('A')" :title="`Klik untuk melihat ${type === 'departemen' ? 'departemen' : 'area'} aktif`">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">{{ type === 'departemen' ? 'Departemen' : 'Area' }} Aktif</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.active }}</p>
              <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.active / statistics.total) * 100) : 0 }}% dari total</p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
              <i class="fa-solid fa-check-circle text-green-600 text-xl"></i>
            </div>
          </div>
          <div class="absolute top-2 right-2 text-xs text-gray-400">
            <i class="fa-solid fa-mouse-pointer"></i>
          </div>
        </div>

        <!-- Non-Aktif -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'N' ? 'bg-red-50 border-red-500 shadow-xl' : 'bg-white border-red-500 hover:shadow-xl'
        ]" @click="filterByStatus('N')" :title="`Klik untuk melihat ${type === 'departemen' ? 'departemen' : 'area'} non-aktif`">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">{{ type === 'departemen' ? 'Departemen' : 'Area' }} Non-Aktif</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.inactive }}</p>
              <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.inactive / statistics.total) * 100) : 0 }}% dari total</p>
            </div>
            <div class="bg-red-100 p-3 rounded-full">
              <i class="fa-solid fa-times-circle text-red-600 text-xl"></i>
            </div>
          </div>
          <div class="absolute top-2 right-2 text-xs text-gray-400">
            <i class="fa-solid fa-mouse-pointer"></i>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="mb-4 flex gap-4 items-center">
        <select v-model="status" class="form-input rounded-xl">
          <option value="A">Aktif</option>
          <option value="N">Non-Aktif</option>
          <option value="all">Semua Status</option>
        </select>
        
        <select v-model="perPage" class="form-input rounded-xl">
          <option value="10">10 per halaman</option>
          <option value="15">15 per halaman</option>
          <option value="25">25 per halaman</option>
          <option value="50">50 per halaman</option>
          <option value="100">100 per halaman</option>
        </select>
        
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          :placeholder="`Cari ${type === 'departemen' ? 'nama departemen, kode departemen, deskripsi...' : 'nama area, kode area, departemen, deskripsi...'}`"
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Kode</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama</th>
              <th v-if="type === 'area'" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Departemen</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Deskripsi</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in data.data" :key="item.id" class="hover:bg-blue-50 transition">
              <td class="px-4 py-2 whitespace-nowrap font-mono text-sm">{{ type === 'departemen' ? item.kode_departemen : item.kode_area }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ type === 'departemen' ? item.nama_departemen : item.nama_area }}</td>
              <td v-if="type === 'area'" class="px-4 py-2 whitespace-nowrap">{{ item.departemen?.nama_departemen || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ item.deskripsi || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-semibold',
                  item.status === 'A' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                ]">
                  {{ item.status === 'A' ? 'Aktif' : 'Non-Aktif' }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openEdit(item)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="toggleStatus(item)" :class="[
                  'px-2 py-1 rounded transition',
                  item.status === 'A' ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-green-500 text-white hover:bg-green-600'
                ]" :title="item.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'">
                  <i :class="item.status === 'A' ? 'fa-solid fa-times' : 'fa-solid fa-check'"></i>
                </button>
                <button @click="hapus(item)" class="px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 transition" title="Hapus">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="data.data.length === 0">
              <td :colspan="type === 'area' ? 6 : 5" class="text-center py-8 text-gray-400">
                Tidak ada data {{ type === 'departemen' ? 'departemen' : 'area' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <!-- Pagination Info -->
        <div class="text-sm text-gray-600">
          Menampilkan {{ data.from || 0 }} sampai {{ data.to || 0 }} dari {{ data.total || 0 }} 
          {{ type === 'departemen' ? 'departemen' : 'area' }}
        </div>
        
        <!-- Pagination Navigation -->
        <nav v-if="data.links && data.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in data.links" :key="i">
            <button 
              v-if="link.url" 
              @click="goToPage(link.url)" 
              :class="[
                'px-3 py-2 text-sm border border-gray-300 transition-colors',
                link.active 
                  ? 'bg-blue-600 text-white border-blue-600' 
                  : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'
              ]" 
              v-html="link.label"
            ></button>
            <span 
              v-else 
              class="px-3 py-2 text-sm border border-gray-200 text-gray-400 bg-gray-50" 
              v-html="link.label"
            ></span>
          </template>
        </nav>
      </div>
    </div>

    <!-- Modal -->
    <MasterReportFormModal 
      :show="showModal" 
      :mode="modalMode" 
      :item="selectedItem" 
      :type="type"
      :departemens="departemens"
      @close="closeModal" 
      @success="onModalSuccess" 
    />
  </AppLayout>
</template>
