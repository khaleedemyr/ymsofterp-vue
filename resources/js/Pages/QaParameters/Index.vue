<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  parameters: Object, // { data, links, meta }
  filters: Object,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      active: 0,
      inactive: 0
    })
  },
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'A');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/qa-parameters', {
    search: search.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

// Watch for filter changes
watch([status, perPage], () => {
  router.get('/qa-parameters', {
    search: search.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});

function goToPage(url) {
  if (url) {
    // Parse URL to add current filter parameters
    const urlObj = new URL(url, window.location.origin);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/qa-parameters/create');
}

function openEdit(parameter) {
  router.visit(`/qa-parameters/${parameter.id}/edit`);
}

function openShow(parameter) {
  router.visit(`/qa-parameters/${parameter.id}`);
}

async function hapus(parameter) {
  const result = await Swal.fire({
    title: 'Nonaktifkan QA Parameter?',
    text: `Yakin ingin menonaktifkan QA Parameter "${parameter.parameter}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Nonaktifkan!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('qa-parameters.destroy', parameter.id), {
    onSuccess: () => Swal.fire('Berhasil', 'QA Parameter berhasil dinonaktifkan!', 'success'),
  });
}

async function toggleStatus(parameter) {
  const action = parameter.status === 'A' ? 'menonaktifkan' : 'mengaktifkan';
  const result = await Swal.fire({
    title: `${parameter.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'} QA Parameter?`,
    text: `Yakin ingin ${action} QA Parameter "${parameter.parameter}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: `Ya, ${parameter.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'}!`,
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.patch(route('qa-parameters.toggle-status', parameter.id));
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal mengubah status QA Parameter', 'error');
  }
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function filterByStatus(newStatus) {
  status.value = newStatus;
}
</script>

<template>
  <AppLayout title="QA Parameters">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-cogs text-blue-500"></i> QA Parameters
        </h1>
        <div class="flex gap-2">
          <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Tambah QA Parameter Baru
          </button>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <!-- Total Parameters -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'all' ? 'bg-blue-50 border-blue-500 shadow-xl' : 'bg-white border-blue-500 hover:shadow-xl'
        ]" @click="filterByStatus('all')" title="Klik untuk melihat semua parameters">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Parameters</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
              <p class="text-xs text-gray-500">100% dari total</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
              <i class="fa-solid fa-cogs text-blue-600 text-xl"></i>
            </div>
          </div>
          <div class="absolute top-2 right-2 text-xs text-gray-400">
            <i class="fa-solid fa-mouse-pointer"></i>
          </div>
        </div>

        <!-- Parameters Aktif -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'A' ? 'bg-green-50 border-green-500 shadow-xl' : 'bg-white border-green-500 hover:shadow-xl'
        ]" @click="filterByStatus('A')" title="Klik untuk melihat parameters aktif">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Parameters Aktif</p>
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

        <!-- Parameters Non-Aktif -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'N' ? 'bg-red-50 border-red-500 shadow-xl' : 'bg-white border-red-500 hover:shadow-xl'
        ]" @click="filterByStatus('N')" title="Klik untuk melihat parameters non-aktif">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Parameters Non-Aktif</p>
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

      <div class="mb-4 flex gap-4 flex-wrap">
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
          placeholder="Cari Kode Parameter atau Parameter..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition min-w-64"
        />
      </div>

      <!-- List View -->
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Kode Parameter</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Parameter</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="parameter in parameters.data" :key="parameter.id" class="hover:bg-blue-50 transition">
              <td class="px-4 py-2 whitespace-nowrap font-mono">{{ parameter.kode_parameter }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ parameter.parameter }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-semibold',
                  parameter.status === 'A' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                ]">
                  {{ parameter.status === 'A' ? 'Aktif' : 'Non-Aktif' }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(parameter)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(parameter)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="toggleStatus(parameter)" :class="[
                  'px-2 py-1 rounded transition',
                  parameter.status === 'A' ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-green-500 text-white hover:bg-green-600'
                ]" :title="parameter.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'">
                  <i :class="parameter.status === 'A' ? 'fa-solid fa-times-circle' : 'fa-solid fa-check-circle'"></i>
                </button>
              </td>
            </tr>
            <tr v-if="parameters.data.length === 0">
              <td colspan="4" class="text-center py-8 text-gray-400">Tidak ada data QA Parameters</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex justify-between items-center">
        <div class="text-sm text-gray-600">
          Menampilkan {{ parameters.from || 0 }} - {{ parameters.to || 0 }} dari {{ parameters.total || 0 }} data
        </div>
        <nav v-if="parameters.links && parameters.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in parameters.links" :key="i">
            <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 hover:bg-blue-50']" v-html="link.label"></button>
            <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>
