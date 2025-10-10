<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <i class="fa-solid fa-clipboard-question text-blue-600"></i>
              Master Data Soal
            </h1>
          </div>
          <Link
            href="/master-soal-new/create"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
          >
            <i class="fa-solid fa-plus"></i>
            Tambah Soal
          </Link>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Search -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
              <input
                v-model="filters.search"
                type="text"
                placeholder="Cari judul atau deskripsi..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="applyFilters"
              />
            </div>


            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
              <select
                v-model="filters.status"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="applyFilters"
              >
                <option
                  v-for="option in statusOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </div>
          </div>

          <!-- Per Page -->
          <div class="mt-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
              <label class="text-sm font-medium text-gray-700">Per halaman:</label>
              <select
                v-model="filters.per_page"
                class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="applyFilters"
              >
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
            </div>

            <button
              @click="resetFilters"
              class="text-sm text-gray-600 hover:text-gray-800 flex items-center gap-1"
            >
              <i class="fa-solid fa-rotate-left"></i>
              Reset Filter
            </button>
          </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    No
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Judul Soal
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Jumlah Pertanyaan
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Waktu Total
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Total Skor
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Aksi
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="soals.data.length === 0">
                  <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                    <i class="fa-solid fa-inbox text-4xl mb-4 block"></i>
                    Tidak ada data soal
                  </td>
                </tr>
                <tr v-for="(soal, index) in soals.data" :key="soal.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ (soals.current_page - 1) * soals.per_page + index + 1 }}
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900">{{ soal.judul }}</div>
                    <div class="text-sm text-gray-500 truncate max-w-xs">
                      {{ soal.deskripsi || 'Tidak ada deskripsi' }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      {{ soal.pertanyaans?.length || 0 }} pertanyaan
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span v-if="soal.pertanyaans && soal.pertanyaans.length > 0">
                      {{ formatWaktu(getTotalWaktu(soal)) }}
                    </span>
                    <span v-else class="text-gray-400 italic">
                      Belum ada pertanyaan
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ soal.skor_total }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="soal.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                      {{ soal.status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center gap-2">
                      <Link
                        :href="`/master-soal-new/${soal.id}`"
                        class="text-blue-600 hover:text-blue-900"
                        title="Lihat Detail"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </Link>
                      <Link
                        :href="`/master-soal-new/${soal.id}/edit`"
                        class="text-yellow-600 hover:text-yellow-900"
                        title="Edit"
                      >
                        <i class="fa-solid fa-edit"></i>
                      </Link>
                      <button
                        @click="duplicateSoal(soal)"
                        class="text-purple-600 hover:text-purple-900"
                        title="Duplicate"
                      >
                        <i class="fa-solid fa-copy"></i>
                      </button>
                      <button
                        @click="toggleStatus(soal)"
                        :class="soal.status === 'active' ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'"
                        :title="soal.status === 'active' ? 'Nonaktifkan' : 'Aktifkan'"
                      >
                        <i :class="soal.status === 'active' ? 'fa-solid fa-ban' : 'fa-solid fa-check'"></i>
                      </button>
                      <button
                        @click="deleteSoal(soal)"
                        class="text-red-600 hover:text-red-900"
                        title="Hapus"
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="soals.data.length > 0" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
              <button
                v-if="soals.prev_page_url"
                @click="goToPage(soals.current_page - 1)"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                Previous
              </button>
              <button
                v-if="soals.next_page_url"
                @click="goToPage(soals.current_page + 1)"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                Next
              </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Menampilkan
                  <span class="font-medium">{{ soals.from }}</span>
                  sampai
                  <span class="font-medium">{{ soals.to }}</span>
                  dari
                  <span class="font-medium">{{ soals.total }}</span>
                  hasil
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  <button
                    v-if="soals.prev_page_url"
                    @click="goToPage(soals.current_page - 1)"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <button
                    v-for="page in getPageNumbers()"
                    :key="page"
                    @click="goToPage(page)"
                    :class="[
                      'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                      page === soals.current_page
                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                    ]"
                  >
                    {{ page }}
                  </button>
                  <button
                    v-if="soals.next_page_url"
                    @click="goToPage(soals.current_page + 1)"
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                  >
                    <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  soals: Object,
  statusOptions: Array,
  filters: Object
});

const filters = reactive({
  search: props.filters.search || '',
  status: props.filters.status || 'all',
  per_page: props.filters.per_page || 10
});

const applyFilters = () => {
  router.get('/master-soal-new', filters, {
    preserveState: true,
    replace: true
  });
};

const resetFilters = () => {
  Object.assign(filters, {
    search: '',
    status: 'all',
    per_page: 10
  });
  applyFilters();
};

const goToPage = (page) => {
  router.get('/master-soal-new', { ...filters, page }, {
    preserveState: true,
    replace: true
  });
};

const getPageNumbers = () => {
  const current = props.soals.current_page;
  const last = props.soals.last_page;
  const delta = 2;
  const range = [];
  const rangeWithDots = [];

  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i);
  }

  if (current - delta > 2) {
    rangeWithDots.push(1, '...');
  } else {
    rangeWithDots.push(1);
  }

  rangeWithDots.push(...range);

  if (current + delta < last - 1) {
    rangeWithDots.push('...', last);
  } else if (last > 1) {
    rangeWithDots.push(last);
  }

  return rangeWithDots;
};

const formatWaktu = (detik) => {
  if (detik < 60) {
    return `${detik} detik`;
  }
  const menit = Math.floor(detik / 60);
  const sisaDetik = detik % 60;
  if (sisaDetik === 0) {
    return `${menit} menit`;
  }
  return `${menit}m ${sisaDetik}s`;
};

const getTotalWaktu = (soal) => {
  if (!soal.pertanyaans || !Array.isArray(soal.pertanyaans)) {
    return 0;
  }
  
  const totalWaktu = soal.pertanyaans.reduce((total, pertanyaan) => {
    return total + (pertanyaan.waktu_detik || 0);
  }, 0);
  
  return totalWaktu;
};

const toggleStatus = async (soal) => {
  const action = soal.status === 'active' ? 'nonaktifkan' : 'aktifkan';
  const result = await Swal.fire({
    title: `Apakah Anda yakin ingin ${action} soal ini?`,
    text: `Soal "${soal.judul}" akan ${action}.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: `Ya, ${action}!`,
    cancelButtonText: 'Batal'
  });

  if (result.isConfirmed) {
    window.location.href = `/master-soal-new/${soal.id}/toggle-status`;
  }
};

const duplicateSoal = async (soal) => {
  const result = await Swal.fire({
    title: 'Duplicate Soal',
    text: `Apakah Anda yakin ingin menduplikasi soal "${soal.judul}"?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#7c3aed',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, duplicate!',
    cancelButtonText: 'Batal'
  });

  if (result.isConfirmed) {
    window.location.href = `/master-soal-new/${soal.id}/duplicate`;
  }
};

const deleteSoal = async (soal) => {
  const result = await Swal.fire({
    title: 'Apakah Anda yakin ingin menghapus soal ini?',
    text: `Soal "${soal.judul}" akan dihapus secara permanen.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  });

  if (result.isConfirmed) {
    // Use router.delete for proper DELETE request
    router.delete(`/master-soal-new/${soal.id}`, {
      onSuccess: () => {
        Swal.fire('Berhasil!', 'Soal berhasil dihapus!', 'success');
      },
      onError: (errors) => {
        Swal.fire('Error!', 'Gagal menghapus soal: ' + (errors.message || 'Unknown error'), 'error');
      }
    });
  }
};
</script>
