<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-globe"></i>
          Regional Management
        </h1>
        <div class="flex gap-2 items-center">
          <Link :href="route('regional.visit-report.index')" class="bg-gradient-to-r from-indigo-500 to-indigo-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa-solid fa-chart-bar mr-1"></i> Rekap Kunjungan
          </Link>
          <Link :href="route('regional.create')" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-plus mr-1"></i> Assign Regional
          </Link>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="flex flex-wrap gap-3 mb-6 items-center">
        <select v-model="filters.status" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="A">Active</option>
          <option value="N">Inactive</option>
        </select>
        
        <input 
          v-model="filters.search" 
          @input="onFilterChange"
          type="text" 
          placeholder="Cari user..." 
          class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition w-64"
        />
      </div>

      <!-- Cards Grid -->
      <div v-if="users && users.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div 
          v-for="user in users" 
          :key="user.id" 
          class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 hover:border-blue-200"
        >
          <!-- Card Header -->
          <div class="p-6 pb-4">
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ user.name }}</h3>
                <p class="text-sm text-gray-600">{{ user.email }}</p>
              </div>
              <span :class="getStatusClass(user.status)" class="px-3 py-1 rounded-full text-xs font-semibold shadow">
                {{ user.status === 'A' ? 'Active' : 'Inactive' }}
              </span>
            </div>

            <!-- User Info with Avatar -->
            <div class="flex items-center gap-4 mb-4">
              <div v-if="user.avatar" class="w-16 h-16 rounded-full overflow-hidden border-3 border-white shadow-xl">
                <img :src="getImageUrl(user.avatar)" :alt="user.name" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-lg font-bold border-3 border-white shadow-xl">
                {{ getInitials(user.name) }}
              </div>
              <div class="flex-1">
                <p v-if="user.nama_jabatan" class="font-medium text-gray-800 text-sm">{{ user.nama_jabatan }}</p>
                <p v-if="user.nama_divisi" class="text-xs text-gray-500">{{ user.nama_divisi }}</p>
                <p v-else class="text-xs text-gray-400">Jabatan/Divisi tidak tersedia</p>
              </div>
            </div>

            <!-- Area Info -->
            <div class="bg-indigo-50 rounded-lg p-4 mb-4">
              <span class="text-sm font-semibold text-indigo-800 block mb-2">Regional Area</span>
              <div v-if="resolveUserAreas(user).length" class="flex flex-wrap gap-2">
                <span
                  v-for="area in resolveUserAreas(user)"
                  :key="area"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-indigo-200 text-indigo-800 font-semibold text-sm"
                >
                  <i :class="['fas', getAreaIcon(area)]"></i>
                  {{ getAreaLabel(area) }}
                </span>
              </div>
              <span v-else class="text-gray-400 italic text-sm">Belum ada area</span>
            </div>

            <div class="bg-amber-50 rounded-lg p-4">
              <span class="text-sm font-semibold text-amber-900 block mb-1">Target Kunjungan / Bulan</span>
              <span v-if="user.target_outlet_visits != null" class="text-lg font-bold text-amber-800">
                {{ user.target_outlet_visits }} kunjungan
              </span>
              <span v-else class="text-gray-400 italic text-sm">Belum di-set</span>
              <p class="text-xs text-amber-700 mt-1">
                Outlet ditargetkan: {{ getTargetOutletCount(user) }}
              </p>
              <div v-if="user.outlet_visit_targets?.length" class="mt-2 space-y-1 max-h-24 overflow-y-auto">
                <div v-for="(target, idx) in user.outlet_visit_targets" :key="idx" class="text-xs text-amber-900 bg-white border border-amber-100 rounded px-2 py-1 flex justify-between gap-2">
                  <span class="truncate">{{ target.outlet_name }}</span>
                  <span class="font-semibold shrink-0">{{ target.target_visits }}</span>
                </div>
              </div>
            </div>

            <div class="bg-sky-50 rounded-lg p-4 mt-3">
              <span class="text-sm font-semibold text-sky-900 block mb-1">Atasan (Jabatan)</span>
              <span v-if="user.supervisor_position_name" class="text-sm font-semibold text-sky-800">
                {{ user.supervisor_position_name }}
              </span>
              <span v-else class="text-gray-400 italic text-sm">Belum dipilih</span>
            </div>
          </div>

          <!-- Card Footer with Actions -->
          <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <div class="flex justify-end gap-2">
              <button 
                @click="editUser(user.id)" 
                class="bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 transition-all shadow-sm text-sm flex items-center gap-1"
                title="Edit Regional"
              >
                <i class="fas fa-edit text-xs"></i>
                Edit
              </button>
              <button 
                @click="deleteUser(user.id, user.name)" 
                class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 transition-all shadow-sm text-sm flex items-center gap-1"
                title="Hapus Regional"
              >
                <i class="fas fa-trash text-xs"></i>
                Hapus
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12">
        <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md mx-auto">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-globe text-gray-400 text-2xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-800 mb-2">Belum ada Regional Assignment</h3>
          <p class="text-gray-600 mb-4">Mulai dengan membuat assignment regional untuk user</p>
          <Link :href="route('regional.create')" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold inline-flex items-center gap-2">
            <i class="fa fa-plus"></i>
            Buat Assignment Pertama
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { REGIONAL_DEPARTMENTS, getAreaLabel, resolveUserAreas } from './regionalOutletUtils';

const props = defineProps({
  users: Array,
  filters: Object
});

const filters = ref({
  status: props.filters?.status || '',
  search: props.filters?.search || ''
});

function getAreaIcon(area) {
  return REGIONAL_DEPARTMENTS.find((d) => d.key === area)?.icon || 'fa-layer-group'
}

function getStatusClass(status) {
  return status === 'A' 
    ? 'bg-green-100 text-green-800' 
    : 'bg-red-100 text-red-800';
}

function getImageUrl(avatar) {
  return `/storage/${avatar}`;
}

function getInitials(name) {
  if (!name) return 'U';
  return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().substring(0, 2);
}

function getTargetOutletCount(user) {
  if (!Array.isArray(user?.outlet_visit_targets)) return 0
  return user.outlet_visit_targets.length
}

function editUser(userId) {
  router.get(`/regional/${userId}/edit`);
}

function deleteUser(userId, userName) {
  Swal.fire({
    title: 'Hapus Regional Assignment?',
    text: `Apakah Anda yakin ingin menghapus regional assignment untuk ${userName}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/regional/${userId}`, {
        onSuccess: () => {
          Swal.fire('Berhasil', 'Regional assignment berhasil dihapus!', 'success');
        },
        onError: () => {
          Swal.fire('Gagal', 'Gagal menghapus regional assignment', 'error');
        }
      });
    }
  });
}

function onFilterChange() {
  router.get('/regional', { ...filters.value }, { preserveState: true, replace: true });
}
</script>

<style scoped>
/* Custom scrollbar for outlets */
.overflow-y-auto::-webkit-scrollbar {
  width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>