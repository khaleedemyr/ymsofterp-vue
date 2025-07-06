<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import UserFormModal from './UserFormModal.vue';
import axios from 'axios';
import PinManagementModal from './PinManagementModal.vue';

const props = defineProps({
  users: Object, // { data, links, meta }
  filters: Object,
  outlets: Array,
  divisions: Array,
});

const search = ref(props.filters?.search || '');
const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedUser = ref(null);
const dropdownData = ref({ outlets: [], jabatans: [] });
const isLoadingDropdown = ref(false);
const showPinModal = ref(false);
const pinUserId = ref(null);
const pinUserName = ref('');
const outletId = ref(props.filters?.outlet_id || '');
const divisionId = ref(props.filters?.division_id || '');

const debouncedSearch = debounce(() => {
  router.get('/users', {
    search: search.value,
    outlet_id: outletId.value,
    division_id: divisionId.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

async function fetchDropdownData() {
  isLoadingDropdown.value = true;
  try {
    const response = await axios.get(route('users.dropdown-data'));
    if (response.data.success) {
      dropdownData.value = {
        outlets: response.data.outlets || [],
        jabatans: response.data.jabatans || [],
      };
    }
  } finally {
    isLoadingDropdown.value = false;
  }
}

function openCreate() {
  router.visit('/users/create');
}

function openEdit(user) {
  router.visit(`/users/${user.id}/edit`);
}

function openShow(user) {
  router.visit(`/users/${user.id}`);
}

function openPinModal(user) {
  pinUserId.value = user.id;
  pinUserName.value = user.nama_lengkap;
  showPinModal.value = true;
}

function closePinModal() {
  showPinModal.value = false;
}

async function hapus(user) {
  const result = await Swal.fire({
    title: 'Hapus Karyawan?',
    text: `Yakin ingin menghapus karyawan "${user.nama_lengkap}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('users.destroy', user.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Karyawan berhasil dihapus!', 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

watch([outletId, divisionId], () => {
  router.get('/users', {
    search: search.value,
    outlet_id: outletId.value,
    division_id: divisionId.value,
  }, { preserveState: true, replace: true });
});
</script>

<template>
  <AppLayout title="Data Karyawan">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-users text-blue-500"></i> Data Karyawan
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Karyawan Baru
        </button>
      </div>
      <div class="mb-4 flex gap-4">
        <select v-model="outletId" class="form-input rounded-xl">
          <option value="">Semua Outlet</option>
          <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
        </select>
        <select v-model="divisionId" class="form-input rounded-xl">
          <option value="">Semua Divisi</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
        </select>
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari NIK, No KTP, Nama, Email, No HP..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">NIK</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No KTP</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jabatan</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Email</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No HP</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users.data" :key="user.id" class="hover:bg-blue-50 transition">
              <td class="px-4 py-2 whitespace-nowrap">{{ user.nik }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.no_ktp }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ user.nama_lengkap }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.nama_jabatan || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.nama_outlet || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.email }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ user.no_hp }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(user)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(user)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="hapus(user)" class="px-2 py-1 rounded bg-red-500 text-white hover:bg-red-600 transition" title="Hapus">
                  <i class="fa-solid fa-trash"></i>
                </button>
                <button @click="openPinModal(user)" class="px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 transition" title="Kelola PIN">
                  <i class="fa-solid fa-key"></i>
                </button>
              </td>
            </tr>
            <tr v-if="users.data.length === 0">
              <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada data karyawan</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 flex justify-end">
        <nav v-if="users.links && users.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in users.links" :key="i">
            <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 hover:bg-blue-50']" v-html="link.label"></button>
            <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
    <UserFormModal :show="showModal" :mode="modalMode" :user="selectedUser" :dropdownData="dropdownData" :isLoadingDropdown="isLoadingDropdown" @close="closeModal" />
    <PinManagementModal :show="showPinModal" :user-id="pinUserId" :user-name="pinUserName" @close="closePinModal" />
  </AppLayout>
</template> 