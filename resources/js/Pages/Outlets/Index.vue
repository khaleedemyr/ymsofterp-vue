<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Switch } from '@headlessui/vue';
import OutletFormModal from './OutletFormModal.vue';
import axios from 'axios';
import OutletMapModal from './OutletMapModal.vue';

const props = defineProps({
  outlets: Object, // { data, links, meta }
  filters: Object,
});

const search = ref(props.filters?.search || '');
const showInactive = ref(false);
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedOutlet = ref(null);
const dropdownData = ref({
  regions: []
});
const isLoadingDropdown = ref(false);
const showMapModal = ref(false);
const mapLat = ref(null);
const mapLong = ref(null);
const mapAlamat = ref('');

const debouncedSearch = debounce(() => {
  router.get('/outlets', { search: search.value, status: showInactive.value ? 'inactive' : 'active' }, { preserveState: true, replace: true });
}, 400);

watch(showInactive, (val) => {
  router.get('/outlets', { search: search.value, status: val ? 'inactive' : 'active' }, { preserveState: true, replace: true });
});

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  modalMode.value = 'create';
  selectedOutlet.value = null;
  fetchDropdownData();
  showModal.value = true;
}

function openEdit(outlet) {
  modalMode.value = 'edit';
  selectedOutlet.value = outlet;
  fetchDropdownData();
  showModal.value = true;
}

async function hapus(outlet) {
  const result = await Swal.fire({
    title: 'Hapus Outlet?',
    text: `Yakin ingin menghapus outlet "${outlet.nama_outlet}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('outlets.destroy', outlet.id_outlet), {
    onSuccess: () => Swal.fire('Berhasil', 'Outlet berhasil dihapus!', 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

function toggleStatus(outlet) {
  router.patch(route('outlets.toggle-status', outlet.id_outlet), {}, {
    preserveState: true,
    onSuccess: reload,
  });
}

async function fetchDropdownData() {
  isLoadingDropdown.value = true;
  try {
    const response = await axios.get(route('outlets.dropdown-data'));
    
    if (response.data.success) {
      dropdownData.value = {
        regions: response.data.regions || []
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

// Debug function
async function debugDatabase() {
  try {
    const response = await axios.get(route('outlets.debug-database'));
    console.log('Debug data:', response.data);
    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Debug Info',
        html: `
          <div class="text-left">
            <p><strong>Connection:</strong> ${response.data.debug.connection}</p>
            <p><strong>Tables:</strong></p>
            <ul class="list-disc list-inside">
              ${Object.entries(response.data.debug.tables || {}).map(([table, info]) => 
                `<li>${table}: ${info.exists ? `Exists (${info.total_count} records, ${info.active_count} active)` : `Error: ${info.error}`}</li>`
              ).join('')}
            </ul>
            <p><strong>Models:</strong></p>
            <ul class="list-disc list-inside">
              ${Object.entries(response.data.debug.models || {}).map(([model, info]) => 
                `<li>${model}: ${info.error ? `Error: ${info.error}` : `${info.total} total, ${info.active} active`}</li>`
              ).join('')}
            </ul>
          </div>
        `,
        width: '600px'
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Debug Error',
        text: response.data.error || 'Unknown error'
      });
    }
  } catch (error) {
    console.error('Debug error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Debug Error',
      text: 'Failed to get debug information'
    });
  }
}

// Watch for dropdown data changes
watch(dropdownData, (newData) => {
  console.log('Dropdown data changed:', newData);
}, { deep: true });

function openMapModal(outlet) {
  mapLat.value = outlet.lat;
  mapLong.value = outlet.long;
  mapAlamat.value = outlet.lokasi || outlet.nama_outlet;
  showMapModal.value = true;
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-store text-blue-500"></i> Master Data Outlet
        </h1>
        <div class="flex gap-2">
          <button @click="debugDatabase" class="bg-gray-500 text-white px-3 py-2 rounded-lg shadow hover:bg-gray-600 transition-all font-semibold text-sm">
            Debug DB
          </button>
          <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Outlet Baru
          </button>
        </div>
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
          placeholder="Cari nama outlet, lokasi, atau QR code..."
          class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-500 to-blue-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Nama Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Lokasi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Region</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">QR Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Koordinat</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="outlet in outlets.data" :key="outlet.id_outlet" class="hover:bg-blue-50 transition shadow-sm">
                <td class="px-6 py-3 font-semibold">{{ outlet.nama_outlet }}</td>
                <td class="px-6 py-3">
                  <div class="max-w-xs">
                    <div class="text-sm">{{ outlet.lokasi }}</div>
                    <div v-if="outlet.keterangan" class="text-xs text-gray-500 mt-1">{{ outlet.keterangan }}</div>
                  </div>
                </td>
                <td class="px-6 py-3">{{ outlet.region?.name || '-' }}</td>
                <td class="px-6 py-3">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded">{{ outlet.qr_code }}</span>
                    <a v-if="outlet.url_places" :href="outlet.url_places" target="_blank" class="text-blue-500 hover:text-blue-700">
                      <i class="fa-solid fa-external-link-alt text-xs"></i>
                    </a>
                  </div>
                </td>
                <td class="px-6 py-3">
                  <div v-if="outlet.lat && outlet.long && outlet.lat !== '-' && outlet.long !== '-'">
                    <span class="text-blue-600 cursor-pointer hover:underline flex items-center gap-1" @click="openMapModal(outlet)">
                      <i class="fas fa-map-marker-alt"></i> Lihat di Maps
                    </span>
                  </div>
                  <div v-else>-</div>
                </td>
                <td class="px-6 py-3">
                  <button
                    :class="outlet.status === 'A' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                    class="px-2 py-1 rounded-full text-xs font-semibold shadow hover:opacity-80 transition"
                    @click="toggleStatus(outlet)"
                  >
                    {{ outlet.status === 'A' ? 'Active' : 'Inactive' }}
                  </button>
                </td>
                <td class="px-6 py-3">
                  <div class="flex gap-2">
                    <button @click="openEdit(outlet)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                      Edit
                    </button>
                    <button @click="hapus(outlet)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
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
          v-for="link in outlets.links"
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
      <OutletFormModal
        :show="showModal"
        :mode="modalMode"
        :outlet="selectedOutlet"
        :dropdown-data="dropdownData"
        :is-loading-dropdown="isLoadingDropdown"
        @close="closeModal"
        @success="reload"
      />
      <OutletMapModal
        :show="showMapModal"
        :lat="mapLat"
        :long="mapLong"
        :alamat="mapAlamat"
        @close="showMapModal = false"
      />
    </div>
  </AppLayout>
</template>

<style scoped>
.bg-3d {
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15), 0 1.5px 4px 0 rgba(31, 38, 135, 0.08);
}
</style> 