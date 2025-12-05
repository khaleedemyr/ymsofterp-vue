<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  users: Object,
  totalMP: Number,
  divisiSummary: Array,
  dropdownData: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedDivisi = ref(props.filters?.divisi_id || '');
const selectedJabatan = ref(props.filters?.jabatan_id || '');
// const selectedLevel = ref(props.filters?.level_id || ''); // Removed karena tidak ada level di users
const selectedOutlet = ref(props.filters?.outlet_id || '');

// Debug: Log dropdown data to see outlet structure
console.log('Dropdown data outlets:', props.dropdownData?.outlets);
console.log('Selected outlet value:', selectedOutlet.value);
console.log('Filters from props:', props.filters);

const debouncedSearch = debounce(() => {
  applyFilters();
}, 400);

watch([selectedDivisi, selectedJabatan, selectedOutlet], () => {
  applyFilters();
});

function onSearchInput() {
  debouncedSearch();
}

function applyFilters() {
  const params = {
    search: search.value,
    divisi_id: selectedDivisi.value,
    jabatan_id: selectedJabatan.value,
    outlet_id: selectedOutlet.value,
  };
  
  // Debug: Log the parameters being sent
  console.log('Applying filters:', params);
  
  router.get('/man-power-outlet', params, { preserveState: true, replace: true });
}

function clearFilters() {
  search.value = '';
  selectedDivisi.value = '';
  selectedJabatan.value = '';
  selectedOutlet.value = '';
  applyFilters();
}

function goToPage(url) {
  if (url) {
    const params = {
      search: search.value,
      divisi_id: selectedDivisi.value,
      jabatan_id: selectedJabatan.value,
      outlet_id: selectedOutlet.value,
    };
    router.visit(url, { data: params, preserveState: true, replace: true });
  }
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-users text-blue-500"></i> Report Man Power Outlet
        </h1>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total MP Card -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-700 rounded-xl p-6 text-white shadow-lg">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-blue-100 text-sm font-medium">Total Man Power</p>
              <p class="text-3xl font-bold">{{ totalMP }}</p>
            </div>
            <div class="text-blue-200">
              <i class="fa-solid fa-users text-4xl"></i>
            </div>
          </div>
        </div>

        <!-- Divisi Summary Cards -->
        <div v-for="divisi in divisiSummary" :key="divisi.nama_divisi" 
             class="bg-gradient-to-r from-green-500 to-green-700 rounded-xl p-6 text-white shadow-lg">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-green-100 text-sm font-medium">{{ divisi.nama_divisi }}</p>
              <p class="text-3xl font-bold">{{ divisi.total_karyawan }}</p>
            </div>
            <div class="text-green-200">
              <i class="fa-solid fa-building text-4xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-filter text-blue-500"></i> Filter Data
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
            <input
              v-model="search"
              @input="onSearchInput"
              type="text"
              placeholder="NIK, Nama, Email..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Divisi Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
            <select
              v-model="selectedDivisi"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Divisi</option>
              <option v-for="divisi in dropdownData.divisis" :key="divisi.id" :value="divisi.id">
                {{ divisi.nama_divisi }}
              </option>
            </select>
          </div>

          <!-- Jabatan Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
            <select
              v-model="selectedJabatan"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Jabatan</option>
              <option v-for="jabatan in dropdownData.jabatans" :key="jabatan.id_jabatan" :value="jabatan.id_jabatan">
                {{ jabatan.nama_jabatan }}
              </option>
            </select>
          </div>

          <!-- Level Filter - Removed karena tidak ada level di users -->

          <!-- Outlet Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select
              v-model="selectedOutlet"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in dropdownData.outlets" :key="outlet.id_outlet || outlet.id" :value="outlet.id_outlet || outlet.id">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
        </div>

        <!-- Clear Filters Button -->
        <div class="mt-4 flex justify-end">
          <button
            @click="clearFilters"
            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
          >
            <i class="fa-solid fa-eraser mr-2"></i>Clear Filters
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-500 to-blue-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">NIK</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Jabatan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Divisi</th>
                                 <!-- <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Level</th> -->
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">No HP</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Outlet</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="user in users.data" :key="user.id" class="hover:bg-blue-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ user.nik }}</td>
                                 <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.nama_lengkap }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.jabatan?.nama_jabatan || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.divisi?.nama_divisi || '-' }}</td>
                                 <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.level?.nama_level || '-' }}</td> -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.email }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.no_hp || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.outlet?.nama_outlet || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-700">
          Menampilkan {{ users.from || 0 }} - {{ users.to || 0 }} dari {{ users.total || 0 }} data
        </div>
        <div class="flex gap-2">
          <button
            v-for="link in users.links"
            :key="link.label"
            :disabled="!link.url"
            @click="goToPage(link.url)"
            v-html="link.label"
            class="px-3 py-1 rounded-lg border text-sm font-semibold"
            :class="[
              link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
              !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
            ]"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.bg-3d {
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15), 0 1.5px 4px 0 rgba(31, 38, 135, 0.08);
}
</style> 