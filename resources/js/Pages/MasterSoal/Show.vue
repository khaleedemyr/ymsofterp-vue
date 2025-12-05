<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
          <div class="flex items-center gap-3 mb-2">
            <Link
              :href="route('master-soal.index')"
              class="text-gray-600 hover:text-gray-800"
            >
              <i class="fa-solid fa-arrow-left"></i>
            </Link>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <i class="fa-solid fa-eye text-blue-600"></i>
              Detail Soal
            </h1>
          </div>
          <p class="text-gray-600">{{ soal.judul }}</p>
        </div>

        <!-- Detail Card -->
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
          <!-- Header Card -->
          <div class="bg-gray-50 px-6 py-4 border-b">
            <div class="flex justify-between items-start">
              <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ soal.judul }}</h2>
                <div class="flex items-center gap-4 mt-2">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getTipeSoalBadgeClass(soal.tipe_soal)">
                    {{ getTipeSoalText(soal.tipe_soal) }}
                  </span>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="soal.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                    {{ soal.status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                  </span>
                </div>
              </div>
              <div class="flex gap-2">
                <Link
                  :href="route('master-soal.edit', soal.id)"
                  class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-sm flex items-center gap-1"
                >
                  <i class="fa-solid fa-edit"></i>
                  Edit
                </Link>
                <button
                  @click="toggleStatus"
                  :class="soal.status === 'active' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                  class="text-white px-3 py-1 rounded text-sm flex items-center gap-1"
                >
                  <i :class="soal.status === 'active' ? 'fa-solid fa-ban' : 'fa-solid fa-check'"></i>
                  {{ soal.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Content -->
          <div class="p-6 space-y-6">
            <!-- Basic Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                <p class="text-gray-900">{{ soal.kategori?.nama_kategori || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Waktu Pengerjaan</label>
                <p class="text-gray-900">{{ formatWaktu(soal.waktu_detik) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Skor</label>
                <p class="text-gray-900">{{ soal.skor }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dibuat Oleh</label>
                <p class="text-gray-900">{{ soal.creator?.nama_lengkap || '-' }}</p>
              </div>
            </div>

            <!-- Pertanyaan -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Pertanyaan</label>
              <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-900 whitespace-pre-wrap">{{ soal.pertanyaan }}</p>
              </div>
            </div>

            <!-- Pilihan Ganda -->
            <div v-if="soal.tipe_soal === 'pilihan_ganda'">
              <label class="block text-sm font-medium text-gray-700 mb-3">Pilihan Jawaban</label>
              <div class="space-y-3">
                <div v-if="soal.pilihan_a" class="flex items-center gap-3 p-3 rounded-lg"
                     :class="soal.jawaban_benar === 'A' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'">
                  <span class="font-medium text-gray-700 w-8">A.</span>
                  <span class="text-gray-900 flex-1">{{ soal.pilihan_a }}</span>
                  <i v-if="soal.jawaban_benar === 'A'" class="fa-solid fa-check text-green-600"></i>
                </div>
                <div v-if="soal.pilihan_b" class="flex items-center gap-3 p-3 rounded-lg"
                     :class="soal.jawaban_benar === 'B' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'">
                  <span class="font-medium text-gray-700 w-8">B.</span>
                  <span class="text-gray-900 flex-1">{{ soal.pilihan_b }}</span>
                  <i v-if="soal.jawaban_benar === 'B'" class="fa-solid fa-check text-green-600"></i>
                </div>
                <div v-if="soal.pilihan_c" class="flex items-center gap-3 p-3 rounded-lg"
                     :class="soal.jawaban_benar === 'C' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'">
                  <span class="font-medium text-gray-700 w-8">C.</span>
                  <span class="text-gray-900 flex-1">{{ soal.pilihan_c }}</span>
                  <i v-if="soal.jawaban_benar === 'C'" class="fa-solid fa-check text-green-600"></i>
                </div>
                <div v-if="soal.pilihan_d" class="flex items-center gap-3 p-3 rounded-lg"
                     :class="soal.jawaban_benar === 'D' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'">
                  <span class="font-medium text-gray-700 w-8">D.</span>
                  <span class="text-gray-900 flex-1">{{ soal.pilihan_d }}</span>
                  <i v-if="soal.jawaban_benar === 'D'" class="fa-solid fa-check text-green-600"></i>
                </div>
              </div>
            </div>

            <!-- Yes/No -->
            <div v-if="soal.tipe_soal === 'yes_no'">
              <label class="block text-sm font-medium text-gray-700 mb-3">Jawaban Benar</label>
              <div class="flex gap-4">
                <div class="flex items-center gap-2 p-3 rounded-lg"
                     :class="soal.jawaban_benar === 'yes' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'">
                  <span class="text-gray-900">Ya</span>
                  <i v-if="soal.jawaban_benar === 'yes'" class="fa-solid fa-check text-green-600"></i>
                </div>
                <div class="flex items-center gap-2 p-3 rounded-lg"
                     :class="soal.jawaban_benar === 'no' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'">
                  <span class="text-gray-900">Tidak</span>
                  <i v-if="soal.jawaban_benar === 'no'" class="fa-solid fa-check text-green-600"></i>
                </div>
              </div>
            </div>

            <!-- Essay -->
            <div v-if="soal.tipe_soal === 'essay'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Jawaban</label>
              <div class="bg-blue-50 p-4 rounded-lg">
                <p class="text-blue-800">
                  <i class="fa-solid fa-info-circle mr-2"></i>
                  Soal essay memerlukan penilaian manual oleh pengajar
                </p>
              </div>
            </div>

            <!-- Metadata -->
            <div class="border-t pt-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Informasi Tambahan</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="text-gray-600">Dibuat:</span>
                  <span class="ml-2 text-gray-900">{{ formatDate(soal.created_at) }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Diupdate:</span>
                  <span class="ml-2 text-gray-900">{{ formatDate(soal.updated_at) }}</span>
                </div>
                <div v-if="soal.updater">
                  <span class="text-gray-600">Terakhir diupdate oleh:</span>
                  <span class="ml-2 text-gray-900">{{ soal.updater.nama_lengkap }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  soal: Object
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

const formatDate = (date) => {
  return new Date(date).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const toggleStatus = async () => {
  const action = props.soal.status === 'active' ? 'nonaktifkan' : 'aktifkan';
  const result = await Swal.fire({
    title: `Apakah Anda yakin ingin ${action} soal ini?`,
    text: `Soal "${props.soal.judul}" akan ${action}.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: `Ya, ${action}!`,
    cancelButtonText: 'Batal'
  });

  if (result.isConfirmed) {
    router.patch(route('master-soal.toggle-status', props.soal.id), {}, {
      onSuccess: () => {
        Swal.fire('Berhasil!', `Soal berhasil ${action}.`, 'success');
      },
      onError: () => {
        Swal.fire('Error!', 'Terjadi kesalahan saat mengubah status.', 'error');
      }
    });
  }
};
</script>
