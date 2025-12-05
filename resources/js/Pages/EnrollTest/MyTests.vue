<template>
  <AppLayout title="My Tests">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          My Tests
        </h2>
      </div>
    </template>

    <div>
      <!-- Filter Section -->
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-4">
          <h3 class="text-lg font-semibold mb-4">Filter Test</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Search -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
              <input type="text" v-model="filters.search" 
                     placeholder="Cari judul soal..."
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
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempt</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled At</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expired At</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="enrollTests.data.length === 0">
                  <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                    <i class="fa-solid fa-inbox text-4xl mb-4 block"></i>
                    Tidak ada test yang tersedia
                  </td>
                </tr>
                <tr v-for="(enrollTest, index) in enrollTests.data" :key="enrollTest.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ (enrollTests.current_page - 1) * enrollTests.per_page + index + 1 }}
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900">{{ enrollTest.master_soal?.judul }}</div>
                    <div class="text-sm text-gray-500">{{ enrollTest.master_soal?.deskripsi }}</div>
                    <div class="text-xs text-gray-400 mt-1">
                      {{ enrollTest.master_soal?.pertanyaans?.length || 0 }} pertanyaan
                    </div>
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
                      <button v-if="enrollTest.status === 'enrolled' && canStartTest(enrollTest)" 
                              @click="startTest(enrollTest)" 
                              class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                        <i class="fa-solid fa-play mr-1"></i>
                        Mulai Test
                      </button>
                      <button v-else-if="enrollTest.status === 'in_progress'" 
                              @click="continueTest(enrollTest)" 
                              class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                        <i class="fa-solid fa-play mr-1"></i>
                        Lanjut Test
                      </button>
                      <span v-else-if="enrollTest.status === 'completed'" class="text-gray-400 text-sm">
                        Test Selesai
                      </span>
                      <span v-else class="text-gray-400 text-sm">
                        {{ getStatusText(enrollTest.status) }}
                      </span>
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
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  enrollTests: Object,
  statusOptions: Array,
  filters: Object
});

// Reactive data
const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || 'all',
  per_page: props.filters?.per_page || 10
});

// Methods
function applyFilters() {
  router.get(route('enroll-test.my-tests'), filters.value, {
    preserveState: true,
    replace: true
  });
}

function resetFilters() {
  filters.value = {
    search: '',
    status: 'all',
    per_page: 10
  };
  applyFilters();
}

function changePage(page) {
  if (page >= 1 && page <= props.enrollTests.last_page) {
    router.get(route('enroll-test.my-tests'), { ...filters.value, page }, {
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

function canStartTest(enrollTest) {
  return enrollTest.status === 'enrolled' && 
         enrollTest.current_attempt < enrollTest.max_attempts &&
         (!enrollTest.expired_at || new Date(enrollTest.expired_at) > new Date());
}

function startTest(enrollTest) {
  router.post(route('enroll-test.start', enrollTest.id));
}

function continueTest(enrollTest) {
  router.visit(route('enroll-test.take', enrollTest.id));
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
