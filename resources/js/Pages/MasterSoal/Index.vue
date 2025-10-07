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
            <p class="text-gray-600 mt-1">Kelola soal untuk sistem testing</p>
          </div>
          <Link
            :href="route('master-soal.create')"
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
                placeholder="Cari judul atau pertanyaan..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="applyFilters"
              />
            </div>

            <!-- Tipe Soal -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Soal</label>
              <select
                v-model="filters.tipe_soal"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="applyFilters"
              >
                <option
                  v-for="option in tipeSoalOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
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
                    Judul
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tipe
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Waktu
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Skor
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
                      {{ soal.pertanyaan }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="getTipeSoalBadgeClass(soal.tipe_soal)">
                      {{ getTipeSoalText(soal.tipe_soal) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatWaktu(soal.waktu_detik) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ soal.skor }}
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
                        :href="route('master-soal.show', soal.id)"
                        class="text-blue-600 hover:text-blue-900"
                        title="Lihat Detail"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </Link>
                      <Link
                        :href="route('master-soal.edit', soal.id)"
                        class="text-yellow-600 hover:text-yellow-900"
                        title="Edit"
                      >
                        <i class="fa-solid fa-edit"></i>
                      </Link>
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
import { ref, reactive, onMounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  soals: Object,
  tipeSoalOptions: Array,
  statusOptions: Array,
  filters: Object
});

const filters = reactive({
  search: props.filters.search || '',
  tipe_soal: props.filters.tipe_soal || 'all',
  status: props.filters.status || 'all',
  per_page: props.filters.per_page || 10
});

const applyFilters = () => {
  router.get(route('master-soal.index'), filters, {
    preserveState: true,
    replace: true
  });
};

const resetFilters = () => {
  Object.assign(filters, {
    search: '',
    tipe_soal: 'all',
    status: 'all',
    per_page: 10
  });
  applyFilters();
};

const goToPage = (page) => {
  router.get(route('master-soal.index'), { ...filters, page }, {
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

const getTipeSoalText = (tipe) => {
  const types = {
    'essay': 'Essay',
    'pilihan_ganda': 'Pilihan Ganda',
    'yes_no': 'Ya/Tidak'
  };
  return types[tipe] || tipe;
};

const getTipeSoalBadgeClass = (tipe) => {
  const classes = {
    'essay': 'bg-purple-100 text-purple-800',
    'pilihan_ganda': 'bg-blue-100 text-blue-800',
    'yes_no': 'bg-green-100 text-green-800'
  };
  return classes[tipe] || 'bg-gray-100 text-gray-800';
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
    router.patch(route('master-soal.toggle-status', soal.id), {}, {
      onSuccess: () => {
        Swal.fire('Berhasil!', `Soal berhasil ${action}.`, 'success');
      },
      onError: () => {
        Swal.fire('Error!', 'Terjadi kesalahan saat mengubah status.', 'error');
      }
    });
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
    router.delete(route('master-soal.destroy', soal.id), {
      onSuccess: () => {
        Swal.fire('Berhasil!', 'Soal berhasil dihapus.', 'success');
      },
      onError: () => {
        Swal.fire('Error!', 'Terjadi kesalahan saat menghapus soal.', 'error');
      }
    });
  }
};
</script>
