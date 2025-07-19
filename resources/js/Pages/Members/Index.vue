<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  members: Object, // { data, links, meta }
  filters: Object,
  stats: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');



const debouncedSearch = debounce(() => {
  router.get('/members', {
    search: search.value,
    status: statusFilter.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  router.visit('/members/create');
}

function openEdit(member) {
  router.visit(`/members/${member.id}/edit`);
}

function openShow(member) {
  router.visit(`/members/${member.id}`);
}



async function toggleStatus(member) {
  const action = member.status_aktif === 'Y' ? 'nonaktifkan' : 'aktifkan';
  const result = await Swal.fire({
    title: `${action.charAt(0).toUpperCase() + action.slice(1)} Member?`,
    text: `Yakin ingin ${action} member "${member.name}"?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  router.patch(route('members.toggle-status', member.id), {}, {
    onSuccess: () => {
      Swal.fire('Berhasil', `Member berhasil ${action}!`, 'success');
    },
  });
}



function reload() {
  router.reload({ preserveState: true, replace: true });
}

watch([statusFilter], () => {
  router.get('/members', {
    search: search.value,
    status: statusFilter.value,
  }, { preserveState: true, replace: true });
});



/**
 * Format angka dengan pemisah ribuan menggunakan format Indonesia
 * Contoh: 125836 -> 125.836
 * @param {number} number - Angka yang akan diformat
 * @returns {string} Angka yang sudah diformat
 */
function formatNumber(number) {
  return number.toLocaleString('id-ID');
}
</script>

<template>
  <AppLayout title="Data Member">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-users text-purple-500"></i> Data Member
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Member Baru
        </button>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
              <i class="fa-solid fa-users text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Total Member</p>
                             <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.total_members) }}</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
              <i class="fa-solid fa-user-check text-green-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Member Aktif</p>
                             <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.active_members) }}</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-orange-500">
          <div class="flex items-center">
            <div class="p-2 bg-orange-100 rounded-lg">
              <i class="fa-solid fa-user-clock text-orange-600 text-xl"></i>
            </div>
                        <div class="ml-4">
              <p class="text-sm text-gray-600">Member Nonaktif</p>
              <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.inactive_members) }}</p>
            </div>
          </div>
        </div>
        
      </div>

      <!-- Filters -->
      <div class="mb-4 flex gap-4 flex-wrap">
        <select v-model="statusFilter" class="form-input rounded-xl">
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="inactive">Tidak Aktif</option>
        </select>


        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari ID, NIK, Nama, Email, Telepon..."
          class="flex-1 px-4 py-2 rounded-xl border border-purple-200 shadow focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
        />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-purple-200">
          <thead class="bg-purple-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">ID Member</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">NIK</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Email</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Telepon</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Eksklusif</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in members.data" :key="member.id" class="hover:bg-purple-50 transition">
              <td class="px-4 py-2 whitespace-nowrap font-mono text-sm">{{ member.costumers_id }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ member.nik }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ member.name }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ member.email || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ member.telepon || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="flex items-center">
                  <div :class="[
                    'w-3 h-3 rounded-full mr-2',
                    member.status_aktif === '1' ? 'bg-green-500' : 'bg-orange-500'
                  ]"></div>
                  <span :class="[
                    'text-sm font-medium',
                    member.status_aktif === '1' ? 'text-green-700' : 'text-orange-700'
                  ]">
                    {{ member.status_aktif === '1' ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="flex items-center">
                  <div :class="[
                    'w-3 h-3 rounded-full mr-2',
                    member.exclusive_member === 'Y' ? 'bg-purple-500' : 'bg-gray-400'
                  ]"></div>
                  <span :class="[
                    'text-sm font-medium',
                    member.exclusive_member === 'Y' ? 'text-purple-700' : 'text-gray-600'
                  ]">
                    {{ member.exclusive_member === 'Y' ? 'Ya' : 'Tidak' }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(member)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(member)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="toggleStatus(member)" :class="[
                  'px-2 py-1 rounded transition',
                  member.status_aktif === '1' 
                    ? 'bg-orange-100 text-orange-700 hover:bg-orange-200' 
                    : 'bg-green-100 text-green-700 hover:bg-green-200'
                ]" :title="member.status_aktif === '1' ? 'Nonaktifkan' : 'Aktifkan'">
                  <i :class="member.status_aktif === '1' ? 'fa-solid fa-user-slash' : 'fa-solid fa-user-check'"></i>
                </button>

              </td>
            </tr>
            <tr v-if="members.data.length === 0">
              <td colspan="6" class="text-center py-8 text-gray-400">Tidak ada data member</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex justify-end">
        <nav v-if="members.links && members.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in members.links" :key="i">
            <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-purple-600 text-white' : 'bg-white text-purple-700 hover:bg-purple-50']" v-html="link.label"></button>
            <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template> 