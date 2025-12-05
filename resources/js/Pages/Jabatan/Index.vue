<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Switch } from '@headlessui/vue';
import JabatanFormModal from './JabatanFormModal.vue';
import axios from 'axios';

const props = defineProps({
  jabatans: Object, // { data, links, meta }
  filters: Object,
});

const search = ref(props.filters?.search || '');
const showInactive = ref(false);
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedJabatan = ref(null);
const dropdownData = ref({
  jabatans: [],
  divisis: [],
  subDivisis: [],
  levels: []
});
const isLoadingDropdown = ref(false);

const debouncedSearch = debounce(() => {
  router.get('/jabatans', { search: search.value, status: showInactive.value ? 'inactive' : 'active' }, { preserveState: true, replace: true });
}, 400);

watch(showInactive, (val) => {
  router.get('/jabatans', { search: search.value, status: val ? 'inactive' : 'active' }, { preserveState: true, replace: true });
});

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  modalMode.value = 'create';
  selectedJabatan.value = null;
  fetchDropdownData();
  showModal.value = true;
}

function openEdit(jabatan) {
  modalMode.value = 'edit';
  selectedJabatan.value = jabatan;
  fetchDropdownData();
  showModal.value = true;
}

async function hapus(jabatan) {
  const result = await Swal.fire({
    title: 'Hapus Jabatan?',
    text: `Yakin ingin menghapus jabatan "${jabatan.nama_jabatan}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('jabatans.destroy', jabatan.id_jabatan), {
    onSuccess: () => Swal.fire('Berhasil', 'Jabatan berhasil dihapus!', 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

function toggleStatus(jabatan) {
  router.patch(route('jabatans.toggle-status', jabatan.id_jabatan), {}, {
    preserveState: true,
    onSuccess: reload,
  });
}

async function fetchDropdownData() {
  isLoadingDropdown.value = true;
  try {
    const response = await axios.get(route('jabatans.dropdown-data'));
    
    if (response.data.success) {
      dropdownData.value = {
        jabatans: response.data.jabatans || [],
        divisis: response.data.divisis || [],
        subDivisis: response.data.subDivisis || [],
        levels: response.data.levels || []
      };
      console.log('Dropdown data loaded:', dropdownData.value);
    } else {
      console.error('Failed to load dropdown data:', response.data.message);
      // Show error message to user
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Gagal memuat data dropdown: ' + (response.data.message || 'Unknown error')
      });
    }
  } catch (error) {
    console.error('Error fetching dropdown data:', error);
    // Show error message to user
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Gagal memuat data dropdown. Silakan coba lagi.'
    });
  } finally {
    isLoadingDropdown.value = false;
  }
}

async function testDropdownData() {
  try {
    console.log('Testing dropdown data...');
    
    // Test the test endpoint first
    const testResponse = await axios.get(route('jabatans.test-dropdown'));
    console.log('Test endpoint response:', testResponse.data);
    
    // Test the actual dropdown endpoint
    const dropdownResponse = await axios.get(route('jabatans.dropdown-data'));
    console.log('Dropdown endpoint response:', dropdownResponse.data);
    
    // Show results to user
    Swal.fire({
      icon: 'info',
      title: 'Test Results',
      html: `
        <div class="text-left">
          <p><strong>Test Endpoint:</strong></p>
          <pre>${JSON.stringify(testResponse.data, null, 2)}</pre>
          <p><strong>Dropdown Endpoint:</strong></p>
          <pre>${JSON.stringify(dropdownResponse.data, null, 2)}</pre>
        </div>
      `,
      width: '800px'
    });
  } catch (error) {
    console.error('Test failed:', error);
    Swal.fire({
      icon: 'error',
      title: 'Test Failed',
      text: 'Error: ' + error.message
    });
  }
}

async function debugDatabase() {
  try {
    console.log('Debugging database...');
    
    const response = await axios.get(route('jabatans.debug-database'));
    console.log('Debug response:', response.data);
    
    // Show results to user
    Swal.fire({
      icon: 'info',
      title: 'Database Debug Results',
      html: `
        <div class="text-left text-sm">
          <h3 class="font-bold mb-2">Database Connection:</h3>
          <p class="mb-2">${response.data.debug.connection}</p>
          
          <h3 class="font-bold mb-2">Tables Status:</h3>
          <div class="mb-2">
            ${Object.entries(response.data.debug.tables).map(([table, info]) => `
              <div class="mb-1">
                <strong>${table}:</strong> 
                ${info.exists ? 
                  `Exists (Total: ${info.total_count}, Active: ${info.active_count})` : 
                  `Error: ${info.error}`
                }
              </div>
            `).join('')}
          </div>
          
          <h3 class="font-bold mb-2">Model Queries:</h3>
          <div class="mb-2">
            ${Object.entries(response.data.debug.models).map(([model, info]) => `
              <div class="mb-1">
                <strong>${model}:</strong> 
                ${info.error ? 
                  `Error: ${info.error}` : 
                  `Total: ${info.total}, Active: ${info.active}`
                }
              </div>
            `).join('')}
          </div>
        </div>
      `,
      width: '800px'
    });
  } catch (error) {
    console.error('Debug failed:', error);
    Swal.fire({
      icon: 'error',
      title: 'Debug Failed',
      text: 'Error: ' + error.message
    });
  }
}

onMounted(() => {
  console.log('Component mounted, fetching dropdown data...');
  fetchDropdownData();
});

// Add watcher to monitor dropdown data changes
watch(dropdownData, (newData) => {
  console.log('Dropdown data changed:', newData);
}, { deep: true });
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user-tie text-blue-500"></i> Master Data Jabatan
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Jabatan Baru
        </button>
      </div>
      <div class="flex items-center gap-3 mb-4">
        <Switch
          v-model="showInactive"
          :class="showInactive ? 'bg-blue-600' : 'bg-gray-200'"
          class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
        >
          <span
            :class="showInactive ? 'translate-x-6' : 'translate-x-1'"
            class="inline-block h-4 w-4 transform rounded-full bg-white transition"
          />
        </Switch>
        <span class="ml-2 text-sm text-gray-700">Tampilkan Inactive</span>
      </div>
      <div class="mb-4">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nama jabatan..."
          class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-500 to-blue-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Nama Jabatan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Atasan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Divisi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Sub Divisi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Level</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="jabatan in jabatans.data" :key="jabatan.id_jabatan" class="hover:bg-blue-50 transition shadow-sm">
                <td class="px-6 py-3 font-semibold">{{ jabatan.nama_jabatan }}</td>
                <td class="px-6 py-3">{{ jabatan.atasan?.nama_jabatan || '-' }}</td>
                <td class="px-6 py-3">{{ jabatan.divisi?.nama_divisi || '-' }}</td>
                <td class="px-6 py-3">{{ jabatan.sub_divisi?.nama_sub_divisi || '-' }}</td>
                <td class="px-6 py-3">{{ jabatan.level?.nama_level || '-' }}</td>
                <td class="px-6 py-3">
                  <button
                    :class="jabatan.status === 'A' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                    class="px-2 py-1 rounded-full text-xs font-semibold shadow hover:opacity-80 transition"
                    @click="toggleStatus(jabatan)"
                  >
                    {{ jabatan.status === 'A' ? 'Active' : 'Inactive' }}
                  </button>
                </td>
                <td class="px-6 py-3">
                  <div class="flex gap-2">
                    <button @click="openEdit(jabatan)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                      Edit
                    </button>
                    <button @click="hapus(jabatan)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in jabatans.links"
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
      <JabatanFormModal
        :show="showModal"
        :mode="modalMode"
        :jabatan="selectedJabatan"
        :dropdown-data="dropdownData"
        :is-loading-dropdown="isLoadingDropdown"
        @close="closeModal"
        @success="reload"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
.bg-3d {
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15), 0 1.5px 4px 0 rgba(31, 38, 135, 0.08);
}
</style> 