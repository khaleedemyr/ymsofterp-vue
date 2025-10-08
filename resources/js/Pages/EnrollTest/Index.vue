<template>
  <AppLayout title="Enroll Test">
    <template #header>
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Enroll Test
        </h2>
        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
          <button @click="createEnrollTest" 
                  class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 text-sm sm:text-base">
            <i class="fa-solid fa-plus"></i>
            <span class="hidden sm:inline">Tambah Enrollment</span>
            <span class="sm:hidden">Tambah</span>
          </button>
          <a href="/enroll-test/create" 
             class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 text-sm sm:text-base">
            <i class="fa-solid fa-user-plus"></i>
            <span class="hidden sm:inline">Enroll Test</span>
            <span class="sm:hidden">Enroll</span>
          </a>
        </div>
      </div>
    </template>

    <div>
      <!-- Filter Section -->
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-4">
          <h3 class="text-lg font-semibold mb-4">Filter Enrollment</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
              <input type="text" v-model="filters.search" 
                     placeholder="Cari judul soal atau nama user..."
                     class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            
            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="filters.status" 
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>
            
            <!-- Master Soal -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Master Soal</label>
              <select v-model="filters.master_soal_id" 
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua Soal</option>
                <option v-for="soal in masterSoals" :key="soal.id" :value="soal.id">
                  {{ soal.judul }}
                </option>
              </select>
            </div>
            
            <!-- User -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
              <select v-model="filters.user_id" 
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua User</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                  {{ user.nama_lengkap }}
                </option>
              </select>
            </div>
          </div>
          
          <div class="flex gap-2 mt-4">
            <button @click="applyFilters" 
                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
              <i class="fa-solid fa-search"></i>
              Cari
            </button>
            <button @click="resetFilters" 
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
              <i class="fa-solid fa-refresh"></i>
              Reset
            </button>
          </div>
        </div>
      </div>

      <!-- Data Table -->
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Master Soal</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempt</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled At</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expired At</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="enrollTests.data.length === 0">
                  <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                    <i class="fa-solid fa-inbox text-4xl mb-4 block"></i>
                    Tidak ada data enrollment
                    <div class="mt-4">
                      <button @click="createEnrollTest" 
                              class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg flex items-center gap-2 mx-auto">
                        <i class="fa-solid fa-plus"></i>
                        Buat Enrollment Pertama
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-for="(enrollTest, index) in enrollTests.data" :key="enrollTest.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ (enrollTests.current_page - 1) * enrollTests.per_page + index + 1 }}
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900">{{ enrollTest.master_soal?.judul }}</div>
                    <div class="text-sm text-gray-500">{{ enrollTest.master_soal?.deskripsi }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ enrollTest.user?.nama_lengkap }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="getStatusBadgeClass(enrollTest.status)">
                      {{ getStatusText(enrollTest.status) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ enrollTest.current_attempt }} / {{ enrollTest.max_attempts }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDate(enrollTest.enrolled_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span v-if="enrollTest.expired_at" :class="isExpired(enrollTest.expired_at) ? 'text-red-600' : 'text-gray-900'">
                      {{ formatDate(enrollTest.expired_at) }}
                    </span>
                    <span v-else class="text-gray-400">Tidak ada</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center gap-2">
                      <button @click="viewEnrollTest(enrollTest)" 
                              class="text-blue-600 hover:text-blue-900">
                        <i class="fa-solid fa-eye"></i>
                      </button>
                      <button @click="editEnrollTest(enrollTest)" 
                              class="text-yellow-600 hover:text-yellow-900">
                        <i class="fa-solid fa-edit"></i>
                      </button>
                      <button v-if="enrollTest.status === 'enrolled'" 
                              @click="cancelEnrollTest(enrollTest)" 
                              class="text-red-600 hover:text-red-900">
                        <i class="fa-solid fa-times"></i>
                      </button>
                      <button @click="deleteEnrollTest(enrollTest)" 
                              class="text-red-600 hover:text-red-900">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <div v-if="enrollTests.total > 0" class="px-4 py-3 border-t border-gray-200 bg-white">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="text-sm text-gray-700">
                  Menampilkan {{ enrollTests.from }} sampai {{ enrollTests.to }} dari {{ enrollTests.total }} data
                </div>
                <div class="flex items-center gap-2">
                  <label class="text-sm text-gray-700">Per halaman:</label>
                  <select v-model="filters.per_page" @change="applyFilters"
                          class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                  </select>
                </div>
              </div>
              
              <div class="flex items-center gap-2">
                <button v-for="page in getVisiblePages()" :key="page"
                        @click="changePage(page)"
                        :class="[
                          'px-3 py-1 text-sm border rounded',
                          page === enrollTests.current_page
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'border-gray-300 hover:bg-gray-50'
                        ]">
                  {{ page }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Floating Action Button -->
    <div class="fixed bottom-6 right-6 z-50">
      <button @click="createEnrollTest" 
              class="bg-black hover:bg-gray-800 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 text-sm font-medium transition-all duration-200 hover:shadow-xl">
        <i class="fa-solid fa-plus"></i>
        Enroll Test
      </button>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  enrollTests: Object,
  statusOptions: Array,
  masterSoals: Array,
  users: Array,
  filters: Object
});

// Reactive data
const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || 'all',
  master_soal_id: props.filters?.master_soal_id || '',
  user_id: props.filters?.user_id || '',
  per_page: props.filters?.per_page || 10
});

// Methods
function applyFilters() {
  router.get(route('enroll-test.index'), filters.value, {
    preserveState: true,
    replace: true
  });
}

function resetFilters() {
  filters.value = {
    search: '',
    status: 'all',
    master_soal_id: '',
    user_id: '',
    per_page: 10
  };
  applyFilters();
}

function changePage(page) {
  if (page >= 1 && page <= props.enrollTests.last_page) {
    router.get(route('enroll-test.index'), { ...filters.value, page }, {
      preserveState: true,
      replace: true
    });
  }
}

function getVisiblePages() {
  const current = props.enrollTests.current_page;
  const last = props.enrollTests.last_page;
  const pages = [];
  
  const start = Math.max(1, current - 2);
  const end = Math.min(last, current + 2);
  
  for (let i = start; i <= end; i++) {
    pages.push(i);
  }
  
  return pages;
}

function createEnrollTest() {
  router.visit(route('enroll-test.create'));
}

function viewEnrollTest(enrollTest) {
  router.visit(route('enroll-test.show', enrollTest.id));
}

function editEnrollTest(enrollTest) {
  router.visit(route('enroll-test.edit', enrollTest.id));
}

function cancelEnrollTest(enrollTest) {
  Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin membatalkan enrollment ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Batalkan!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('enroll-test.cancel', enrollTest.id));
    }
  });
}

function deleteEnrollTest(enrollTest) {
  Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin menghapus enrollment ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('enroll-test.destroy', enrollTest.id));
    }
  });
}

function getStatusText(status) {
  const statuses = {
    'enrolled': 'Terdaftar',
    'in_progress': 'Sedang Test',
    'completed': 'Selesai',
    'expired': 'Kedaluwarsa',
    'cancelled': 'Dibatalkan'
  };
  return statuses[status] || status;
}

function getStatusBadgeClass(status) {
  const classes = {
    'enrolled': 'bg-blue-100 text-blue-800',
    'in_progress': 'bg-yellow-100 text-yellow-800',
    'completed': 'bg-green-100 text-green-800',
    'expired': 'bg-red-100 text-red-800',
    'cancelled': 'bg-gray-100 text-gray-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function isExpired(expiredAt) {
  return new Date(expiredAt) < new Date();
}
</script>
