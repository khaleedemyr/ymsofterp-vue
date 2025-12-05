<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
          <div class="flex items-center gap-3 mb-2">
            <Link
              href="/master-soal-new"
              class="text-gray-600 hover:text-gray-800"
            >
              <i class="fa-solid fa-arrow-left"></i>
            </Link>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <i class="fa-solid fa-eye text-blue-600"></i>
              Detail Soal
            </h1>
          </div>
          <p class="text-gray-600">Lihat detail soal dan pertanyaan</p>
        </div>

        <!-- Master Soal Info -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
          <h2 class="text-xl font-semibold text-gray-900 mb-4">Informasi Soal</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Judul -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Judul Soal
              </label>
              <p class="text-lg font-medium text-gray-900">{{ masterSoal?.judul || 'Tidak ada judul' }}</p>
            </div>

            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Status
              </label>
              <span 
                :class="(masterSoal?.status || 'inactive') === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
              >
                <i :class="(masterSoal?.status || 'inactive') === 'active' ? 'fa-solid fa-check-circle' : 'fa-solid fa-times-circle'" class="mr-1"></i>
                {{ (masterSoal?.status || 'inactive') === 'active' ? 'Aktif' : 'Tidak Aktif' }}
              </span>
            </div>

            <!-- Deskripsi -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Deskripsi
              </label>
              <p class="text-gray-900">{{ masterSoal?.deskripsi || 'Tidak ada deskripsi' }}</p>
            </div>

            <!-- Total Skor -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Total Skor
              </label>
              <p class="text-lg font-medium text-blue-600">{{ masterSoal?.skor_total || 0 }} poin</p>
            </div>

            <!-- Jumlah Pertanyaan -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Jumlah Pertanyaan
              </label>
              <p class="text-lg font-medium text-green-600">{{ masterSoal?.pertanyaans?.length || 0 }} pertanyaan</p>
            </div>
          </div>
        </div>

        <!-- Pertanyaan Section -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Pertanyaan</h2>
            <div class="flex gap-2">
              <Link
                :href="`/master-soal-new/${masterSoal?.id}/edit`"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center gap-2"
              >
                <i class="fa-solid fa-edit"></i>
                Edit
              </Link>
            </div>
          </div>

          <!-- Pertanyaan Cards -->
          <div v-if="!masterSoal?.pertanyaans || masterSoal?.pertanyaans?.length === 0" class="text-center py-8 text-gray-500">
            <i class="fa-solid fa-question-circle text-4xl mb-4 block"></i>
            <p>Belum ada pertanyaan.</p>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="(pertanyaan, index) in masterSoal?.pertanyaans || []"
              :key="index"
              class="border border-gray-200 rounded-lg p-4 bg-gray-50"
            >
              <!-- Pertanyaan Header -->
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                  Pertanyaan {{ index + 1 }}
                </h3>
                <div class="flex items-center gap-2">
                  <span 
                    :class="getTipeSoalClass(pertanyaan.tipe_soal)"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  >
                    <i :class="getTipeSoalIcon(pertanyaan.tipe_soal)" class="mr-1"></i>
                    {{ getTipeSoalLabel(pertanyaan.tipe_soal) }}
                  </span>
                  <span class="text-sm text-gray-500">
                    {{ pertanyaan.waktu_detik }} detik
                  </span>
                </div>
              </div>

              <!-- Pertanyaan Text -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Pertanyaan
                </label>
                <p class="text-gray-900 whitespace-pre-wrap">{{ pertanyaan.pertanyaan }}</p>
                
                <!-- Pertanyaan Images -->
                <div v-if="getPertanyaanImages(pertanyaan).length > 0" class="mt-3">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Gambar Pertanyaan
                  </label>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div
                      v-for="(image, imgIndex) in getPertanyaanImages(pertanyaan)"
                      :key="imgIndex"
                      class="bg-white rounded border p-2"
                    >
                      <img
                        :src="getImageUrl(image)"
                        :alt="`Pertanyaan ${index + 1} - Image ${imgIndex + 1}`"
                        class="w-full h-20 object-cover rounded cursor-pointer hover:opacity-80 transition"
                        @click="showLightbox(getPertanyaanImages(pertanyaan).map(img => getImageUrl(img)), imgIndex)"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Pilihan Ganda Options -->
              <div v-if="pertanyaan.tipe_soal === 'pilihan_ganda'" class="space-y-2">
                <h4 class="text-sm font-medium text-gray-700">Pilihan Jawaban:</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                  <!-- Pilihan A -->
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-700">A.</span>
                    <span class="text-gray-900">{{ pertanyaan.pilihan_a }}</span>
                    <div v-if="pertanyaan.pilihan_a_gambar" class="ml-2">
                      <img
                        :src="getImageUrl(pertanyaan.pilihan_a_gambar)"
                        alt="Pilihan A"
                        class="w-12 h-12 object-cover rounded border cursor-pointer hover:opacity-80 transition"
                        @click="showLightbox([getImageUrl(pertanyaan.pilihan_a_gambar)], 0)"
                      />
                    </div>
                  </div>

                  <!-- Pilihan B -->
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-700">B.</span>
                    <span class="text-gray-900">{{ pertanyaan.pilihan_b }}</span>
                    <div v-if="pertanyaan.pilihan_b_gambar" class="ml-2">
                      <img
                        :src="getImageUrl(pertanyaan.pilihan_b_gambar)"
                        alt="Pilihan B"
                        class="w-12 h-12 object-cover rounded border cursor-pointer hover:opacity-80 transition"
                        @click="showLightbox([getImageUrl(pertanyaan.pilihan_b_gambar)], 0)"
                      />
                    </div>
                  </div>

                  <!-- Pilihan C -->
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-700">C.</span>
                    <span class="text-gray-900">{{ pertanyaan.pilihan_c }}</span>
                    <div v-if="pertanyaan.pilihan_c_gambar" class="ml-2">
                      <img
                        :src="getImageUrl(pertanyaan.pilihan_c_gambar)"
                        alt="Pilihan C"
                        class="w-12 h-12 object-cover rounded border cursor-pointer hover:opacity-80 transition"
                        @click="showLightbox([getImageUrl(pertanyaan.pilihan_c_gambar)], 0)"
                      />
                    </div>
                  </div>

                  <!-- Pilihan D -->
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-700">D.</span>
                    <span class="text-gray-900">{{ pertanyaan.pilihan_d }}</span>
                    <div v-if="pertanyaan.pilihan_d_gambar" class="ml-2">
                      <img
                        :src="getImageUrl(pertanyaan.pilihan_d_gambar)"
                        alt="Pilihan D"
                        class="w-12 h-12 object-cover rounded border cursor-pointer hover:opacity-80 transition"
                        @click="showLightbox([getImageUrl(pertanyaan.pilihan_d_gambar)], 0)"
                      />
                    </div>
                  </div>
                </div>

                <!-- Jawaban Benar -->
                <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-md">
                  <div class="flex items-center gap-2 text-green-700">
                    <i class="fa-solid fa-check-circle"></i>
                    <span class="font-medium">Jawaban Benar: {{ pertanyaan.jawaban_benar }}</span>
                  </div>
                </div>
              </div>

              <!-- Yes/No Options -->
              <div v-if="pertanyaan.tipe_soal === 'yes_no'" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
                <div class="flex items-center gap-2 text-blue-700">
                  <i class="fa-solid fa-check-circle"></i>
                  <span class="font-medium">Jawaban Benar: {{ pertanyaan.jawaban_benar === 'yes' ? 'Ya' : 'Tidak' }}</span>
                </div>
              </div>

              <!-- Essay Info -->
              <div v-if="pertanyaan.tipe_soal === 'essay'" class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                <div class="flex items-center gap-2 text-yellow-700">
                  <i class="fa-solid fa-info-circle"></i>
                  <span class="font-medium">Essay - Akan dinilai manual</span>
                </div>
              </div>

              <!-- Skor (hanya untuk pilihan ganda dan yes/no) -->
              <div v-if="pertanyaan.skor && pertanyaan.tipe_soal !== 'essay'" class="mt-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Skor
                </label>
                <p class="text-lg font-medium text-blue-600">{{ pertanyaan.skor }} poin</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-4 mt-6">
          <Link
            href="/master-soal-new"
            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            Kembali
          </Link>
          <Link
            :href="`/master-soal-new/${masterSoal?.id}/edit`"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <i class="fa-solid fa-edit mr-2"></i>
            Edit Soal
          </Link>
        </div>
      </div>
    </div>

    <!-- Lightbox Modal -->
    <div
      v-if="showLightboxModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75"
      @click="closeLightbox"
    >
      <div class="relative max-w-4xl max-h-full p-4">
        <!-- Close button -->
        <button
          @click="closeLightbox"
          class="absolute top-4 right-4 z-10 bg-white bg-opacity-80 hover:bg-opacity-100 text-gray-800 rounded-full w-10 h-10 flex items-center justify-center transition"
        >
          <i class="fa-solid fa-times"></i>
        </button>

        <!-- Navigation buttons -->
        <button
          v-if="lightboxImages.length > 1 && currentImageIndex > 0"
          @click.stop="previousImage"
          class="absolute left-4 top-1/2 transform -translate-y-1/2 z-10 bg-white bg-opacity-80 hover:bg-opacity-100 text-gray-800 rounded-full w-10 h-10 flex items-center justify-center transition"
        >
          <i class="fa-solid fa-chevron-left"></i>
        </button>

        <button
          v-if="lightboxImages.length > 1 && currentImageIndex < lightboxImages.length - 1"
          @click.stop="nextImage"
          class="absolute right-4 top-1/2 transform -translate-y-1/2 z-10 bg-white bg-opacity-80 hover:bg-opacity-100 text-gray-800 rounded-full w-10 h-10 flex items-center justify-center transition"
        >
          <i class="fa-solid fa-chevron-right"></i>
        </button>

        <!-- Image -->
        <img
          v-if="lightboxImages[currentImageIndex]"
          :src="lightboxImages[currentImageIndex]"
          :alt="`Image ${currentImageIndex + 1}`"
          class="max-w-full max-h-full object-contain rounded-lg"
          @click.stop
        />

        <!-- Image counter -->
        <div
          v-if="lightboxImages.length > 1"
          class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-white bg-opacity-80 text-gray-800 px-3 py-1 rounded-full text-sm"
        >
          {{ currentImageIndex + 1 }} / {{ lightboxImages.length }}
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  masterSoal: {
    type: Object,
    default: () => ({
      id: null,
      judul: '',
      deskripsi: '',
      status: 'inactive',
      skor_total: 0,
      pertanyaans: []
    })
  }
});

const getTipeSoalClass = (tipe) => {
  const classes = {
    'essay': 'bg-yellow-100 text-yellow-800',
    'pilihan_ganda': 'bg-blue-100 text-blue-800',
    'yes_no': 'bg-green-100 text-green-800'
  };
  return classes[tipe] || 'bg-gray-100 text-gray-800';
};

const getTipeSoalIcon = (tipe) => {
  const icons = {
    'essay': 'fa-solid fa-pen',
    'pilihan_ganda': 'fa-solid fa-list',
    'yes_no': 'fa-solid fa-check'
  };
  return icons[tipe] || 'fa-solid fa-question';
};

const getTipeSoalLabel = (tipe) => {
  const labels = {
    'essay': 'Essay',
    'pilihan_ganda': 'Pilihan Ganda',
    'yes_no': 'Yes/No'
  };
  return labels[tipe] || 'Unknown';
};

const getImageUrl = (imagePath) => {
  if (!imagePath) return '';
  
  // Convert backslashes to forward slashes and create full URL
  const normalizedPath = imagePath.replace(/\\/g, '/');
  return `/storage/${normalizedPath}`;
};

// Parse pertanyaan_gambar JSON string to array
const getPertanyaanImages = (pertanyaan) => {
  if (!pertanyaan.pertanyaan_gambar) return [];
  
  try {
    // If it's already an array, return it
    if (Array.isArray(pertanyaan.pertanyaan_gambar)) {
      return pertanyaan.pertanyaan_gambar;
    }
    
    // If it's a JSON string, parse it
    if (typeof pertanyaan.pertanyaan_gambar === 'string') {
      return JSON.parse(pertanyaan.pertanyaan_gambar);
    }
    
    return [];
  } catch (error) {
    console.error('Error parsing pertanyaan_gambar:', error);
    return [];
  }
};

// Lightbox functionality
const showLightboxModal = ref(false);
const lightboxImages = ref([]);
const currentImageIndex = ref(0);

// Show lightbox
const showLightbox = (images, index) => {
  lightboxImages.value = images;
  currentImageIndex.value = index;
  showLightboxModal.value = true;
};

// Close lightbox
const closeLightbox = () => {
  showLightboxModal.value = false;
  lightboxImages.value = [];
  currentImageIndex.value = 0;
};

// Navigate images
const previousImage = () => {
  if (currentImageIndex.value > 0) {
    currentImageIndex.value--;
  }
};

const nextImage = () => {
  if (currentImageIndex.value < lightboxImages.value.length - 1) {
    currentImageIndex.value++;
  }
};
</script>
