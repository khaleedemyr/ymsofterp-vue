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
            href="/master-soal/create"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
          >
            <i class="fa-solid fa-plus"></i>
            Tambah Soal
          </Link>
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
                    Kategori
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
                  <td colspan="8" class="px-6 py-12 text-center text-gray-500">
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
                    {{ soal.kategori?.nama_kategori || '-' }}
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
                        :href="`/master-soal/${soal.id}`"
                        class="text-blue-600 hover:text-blue-900"
                        title="Lihat Detail"
                      >
                        <i class="fa-solid fa-eye"></i>
                      </Link>
                      <Link
                        :href="`/master-soal/${soal.id}/edit`"
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
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  soals: Object,
  kategoris: Array,
  tipeSoalOptions: Array,
  statusOptions: Array,
  filters: Object
});

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
    // Use hardcoded URL instead of route helper
    window.location.href = `/master-soal/${soal.id}/toggle-status`;
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
    // Use hardcoded URL instead of route helper
    window.location.href = `/master-soal/${soal.id}`;
  }
};
</script>
