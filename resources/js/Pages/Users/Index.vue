<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import UserFormModal from './UserFormModal.vue';
import axios from 'axios';
import PinManagementModal from './PinManagementModal.vue';
import ActivationModal from './ActivationModal.vue';

const props = defineProps({
  users: Object, // { data, links, meta }
  filters: Object,
  outlets: Array,
  divisions: Array,
  jabatans: Array,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      active: 0,
      inactive: 0,
      new: 0
    })
  },
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
const showActivationModal = ref(false);
const selectedUserForActivation = ref(null);
const outletId = ref(props.filters?.outlet_id || '');
const divisionId = ref(props.filters?.division_id || '');
const status = ref(props.filters?.status || 'A');

const debouncedSearch = debounce(() => {
  router.get('/users', {
    search: search.value,
    outlet_id: outletId.value,
    division_id: divisionId.value,
    status: status.value,
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
    title: 'Nonaktifkan Karyawan?',
    text: `Yakin ingin menonaktifkan karyawan "${user.nama_lengkap}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Nonaktifkan!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('users.destroy', user.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Karyawan berhasil dinonaktifkan!', 'success'),
  });
}

async function toggleStatus(user) {
  // Jika status 'B' (Baru), tampilkan modal aktivasi
  if (user.status === 'B') {
    selectedUserForActivation.value = user;
    showActivationModal.value = true;
    return;
  }

  // Untuk status lain, gunakan logika lama
  const action = user.status === 'A' ? 'menonaktifkan' : 'mengaktifkan';
  const result = await Swal.fire({
    title: `${user.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'} Karyawan?`,
    text: `Yakin ingin ${action} karyawan "${user.nama_lengkap}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: `Ya, ${user.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'}!`,
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.patch(route('users.toggle-status', user.id));
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal mengubah status karyawan', 'error');
  }
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

function closeActivationModal() {
  showActivationModal.value = false;
  selectedUserForActivation.value = null;
}

function onActivationSuccess(message) {
  Swal.fire('Berhasil', message, 'success');
  reload();
}

function filterByStatus(newStatus) {
  status.value = newStatus;
}

watch([outletId, divisionId, status], () => {
  router.get('/users', {
    search: search.value,
    outlet_id: outletId.value,
    division_id: divisionId.value,
    status: status.value,
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

       <!-- Statistics Cards -->
       <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
         <!-- Total Karyawan -->
         <div :class="[
           'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
           status === 'all' ? 'bg-blue-50 border-blue-500 shadow-xl' : 'bg-white border-blue-500 hover:shadow-xl'
         ]" @click="filterByStatus('all')" title="Klik untuk melihat semua karyawan">
           <div class="flex items-center justify-between">
             <div>
               <p class="text-sm font-medium text-gray-600">Total Karyawan</p>
               <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
               <p class="text-xs text-gray-500">100% dari total</p>
             </div>
             <div class="bg-blue-100 p-3 rounded-full">
               <i class="fa-solid fa-users text-blue-600 text-xl"></i>
             </div>
           </div>
           <div class="absolute top-2 right-2 text-xs text-gray-400">
             <i class="fa-solid fa-mouse-pointer"></i>
           </div>
         </div>

         <!-- Karyawan Aktif -->
         <div :class="[
           'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
           status === 'A' ? 'bg-green-50 border-green-500 shadow-xl' : 'bg-white border-green-500 hover:shadow-xl'
         ]" @click="filterByStatus('A')" title="Klik untuk melihat karyawan aktif">
           <div class="flex items-center justify-between">
             <div>
               <p class="text-sm font-medium text-gray-600">Karyawan Aktif</p>
               <p class="text-2xl font-bold text-gray-900">{{ statistics.active }}</p>
               <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.active / statistics.total) * 100) : 0 }}% dari total</p>
             </div>
             <div class="bg-green-100 p-3 rounded-full">
               <i class="fa-solid fa-user-check text-green-600 text-xl"></i>
             </div>
           </div>
           <div class="absolute top-2 right-2 text-xs text-gray-400">
             <i class="fa-solid fa-mouse-pointer"></i>
           </div>
         </div>

         <!-- Karyawan Non-Aktif -->
         <div :class="[
           'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
           status === 'N' ? 'bg-red-50 border-red-500 shadow-xl' : 'bg-white border-red-500 hover:shadow-xl'
         ]" @click="filterByStatus('N')" title="Klik untuk melihat karyawan non-aktif">
           <div class="flex items-center justify-between">
             <div>
               <p class="text-sm font-medium text-gray-600">Karyawan Non-Aktif</p>
               <p class="text-2xl font-bold text-gray-900">{{ statistics.inactive }}</p>
               <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.inactive / statistics.total) * 100) : 0 }}% dari total</p>
             </div>
             <div class="bg-red-100 p-3 rounded-full">
               <i class="fa-solid fa-user-slash text-red-600 text-xl"></i>
             </div>
           </div>
           <div class="absolute top-2 right-2 text-xs text-gray-400">
             <i class="fa-solid fa-mouse-pointer"></i>
           </div>
         </div>

         <!-- Karyawan Baru -->
         <div :class="[
           'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
           status === 'B' ? 'bg-yellow-50 border-yellow-500 shadow-xl' : 'bg-white border-yellow-500 hover:shadow-xl'
         ]" @click="filterByStatus('B')" title="Klik untuk melihat karyawan baru">
           <div class="flex items-center justify-between">
             <div>
               <p class="text-sm font-medium text-gray-600">Karyawan Baru</p>
               <p class="text-2xl font-bold text-gray-900">{{ statistics.new }}</p>
               <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.new / statistics.total) * 100) : 0 }}% dari total</p>
             </div>
             <div class="bg-yellow-100 p-3 rounded-full">
               <i class="fa-solid fa-user-plus text-yellow-600 text-xl"></i>
             </div>
           </div>
           <div class="absolute top-2 right-2 text-xs text-gray-400">
             <i class="fa-solid fa-mouse-pointer"></i>
           </div>
         </div>
       </div>
      <div class="mb-4 flex gap-4">
        <select v-model="status" class="form-input rounded-xl">
          <option value="A">Aktif</option>
          <option value="N">Non-Aktif</option>
          <option value="B">Baru</option>
          <option value="all">Semua Status</option>
        </select>
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
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
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
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-semibold',
                  user.status === 'A' ? 'bg-green-100 text-green-800' : 
                  user.status === 'N' ? 'bg-red-100 text-red-800' : 
                  'bg-yellow-100 text-yellow-800'
                ]">
                  {{ user.status === 'A' ? 'Aktif' : user.status === 'N' ? 'Non-Aktif' : 'Baru' }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(user)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(user)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="toggleStatus(user)" :class="[
                  'px-2 py-1 rounded transition',
                  user.status === 'A' ? 'bg-red-500 text-white hover:bg-red-600' : 
                  user.status === 'B' ? 'bg-blue-500 text-white hover:bg-blue-600' : 
                  'bg-green-500 text-white hover:bg-green-600'
                ]" :title="user.status === 'A' ? 'Nonaktifkan' : user.status === 'B' ? 'Aktifkan' : 'Aktifkan'">
                  <i :class="user.status === 'A' ? 'fa-solid fa-user-slash' : 'fa-solid fa-user-check'"></i>
                </button>
                <button @click="openPinModal(user)" class="px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 transition" title="Kelola PIN">
                  <i class="fa-solid fa-key"></i>
                </button>
              </td>
            </tr>
            <tr v-if="users.data.length === 0">
              <td colspan="9" class="text-center py-8 text-gray-400">Tidak ada data karyawan</td>
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
    <ActivationModal 
      :show="showActivationModal" 
      :user="selectedUserForActivation" 
      :jabatans="jabatans" 
      :divisions="divisions" 
      :outlets="outlets" 
      @close="closeActivationModal" 
      @success="onActivationSuccess" 
    />
  </AppLayout>
</template> 