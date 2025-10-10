<template>
  <AppLayout title="Take Test">
    <div class="max-w-4xl mx-auto">
      <!-- Test Header -->
      <div class="bg-white shadow-sm rounded-lg mb-6">
        <div class="p-6">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h1 class="text-2xl font-bold text-gray-900">{{ enrollTest.master_soal.judul }}</h1>
              <p class="text-gray-600 mt-1">{{ enrollTest.master_soal.deskripsi }}</p>
            </div>
            <div class="text-right">
              <div class="text-sm text-gray-500">Percobaan</div>
              <div class="text-lg font-semibold">{{ enrollTest.current_attempt }}/{{ enrollTest.max_attempts }}</div>
            </div>
          </div>
          
          <!-- Progress Bar -->
          <div class="mb-4">
            <div class="flex justify-between text-sm text-gray-600 mb-2">
              <span>Soal {{ currentIndex + 1 }} dari {{ totalQuestions }}</span>
              <span>{{ Math.round(((currentIndex + 1) / totalQuestions) * 100) }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                   :style="{ width: ((currentIndex + 1) / totalQuestions) * 100 + '%' }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Question Card -->
      <div class="bg-white shadow-sm rounded-lg mb-6">
        <div class="p-6">
          <!-- Timer -->
          <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-2">
              <i class="fa-solid fa-clock text-gray-500"></i>
              <span class="text-sm text-gray-600">Waktu tersisa:</span>
              <div class="text-lg font-mono font-bold" 
                   :class="timeLeft <= 30 ? 'text-red-600' : 'text-gray-900'">
                {{ formatTime(timeLeft) }}
              </div>
            </div>
            <div class="text-sm text-gray-500">
              Soal {{ currentIndex + 1 }} dari {{ totalQuestions }}
            </div>
          </div>

          <!-- Question -->
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
              Soal {{ currentIndex + 1 }} - {{ getTipeSoalText(currentQuestion.tipe_soal) }}
            </h2>
            <div class="bg-gray-50 p-4 rounded-lg">
              <p class="text-gray-800 leading-relaxed mb-4">{{ currentQuestion.pertanyaan }}</p>
              
              <!-- Question Images -->
              <div v-if="getPertanyaanImages(currentQuestion).length > 0" class="mt-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Gambar Pertanyaan
                </label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                  <div
                    v-for="(image, imgIndex) in getPertanyaanImages(currentQuestion)"
                    :key="imgIndex"
                    class="bg-white rounded border p-2"
                  >
                    <img
                      :src="getImageUrl(image)"
                      :alt="`Pertanyaan ${currentIndex + 1} - Image ${imgIndex + 1}`"
                      class="w-full h-20 object-cover rounded cursor-pointer hover:opacity-80 transition"
                      @click="showLightbox(getPertanyaanImages(currentQuestion).map(img => getImageUrl(img)), imgIndex)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Answer Input -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Jawaban Anda:
            </label>
            
            <!-- Essay Answer -->
            <textarea 
              v-if="currentQuestion.tipe_soal === 'essay'"
              v-model="currentAnswer"
              rows="6"
              class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              placeholder="Tulis jawaban Anda di sini..."
              @input="saveAnswer"
            ></textarea>

            <!-- Multiple Choice Answer -->
            <div v-else-if="currentQuestion.tipe_soal === 'pilihan_ganda'" class="space-y-3">
              <div v-for="(option, key) in getPilihanArray()" :key="key" 
                   class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer"
                   :class="{ 'bg-blue-50 border-blue-300': currentAnswer === key }"
                   @click="selectAnswer(key)">
                <input type="radio" 
                       :id="`option-${key}`"
                       :value="key"
                       v-model="currentAnswer"
                       class="mt-1 text-blue-600 focus:ring-blue-500"
                       @change="saveAnswer">
                <label :for="`option-${key}`" class="flex-1 cursor-pointer">
                  <div class="flex items-start space-x-2">
                    <span class="font-medium text-gray-700">{{ key }}.</span>
                    <div class="flex-1">
                      <span class="text-gray-800">{{ option }}</span>
                      <!-- Option Image -->
                      <div v-if="getOptionImage(key)" class="ml-2">
                        <img
                          :src="getImageUrl(getOptionImage(key))"
                          :alt="`Pilihan ${key}`"
                          class="w-12 h-12 object-cover rounded border cursor-pointer hover:opacity-80 transition"
                          @click="showLightbox([getImageUrl(getOptionImage(key))], 0)"
                        />
                      </div>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <!-- Yes/No Answer -->
            <div v-else-if="currentQuestion.tipe_soal === 'yes_no'" class="space-y-3">
              <div class="flex space-x-4">
                <label class="flex items-center space-x-2 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer"
                       :class="{ 'bg-green-50 border-green-300': currentAnswer === 'yes' }"
                       @click="selectAnswer('yes')">
                  <input type="radio" 
                         value="yes"
                         v-model="currentAnswer"
                         class="text-green-600 focus:ring-green-500"
                         @change="saveAnswer">
                  <span class="text-gray-800 font-medium">Ya</span>
                </label>
                <label class="flex items-center space-x-2 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer"
                       :class="{ 'bg-red-50 border-red-300': currentAnswer === 'no' }"
                       @click="selectAnswer('no')">
                  <input type="radio" 
                         value="no"
                         v-model="currentAnswer"
                         class="text-red-600 focus:ring-red-500"
                         @change="saveAnswer">
                  <span class="text-gray-800 font-medium">Tidak</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Navigation -->
          <div class="flex justify-between items-center">
            <div class="text-sm text-gray-500">
              <span v-if="currentAnswer.trim()">Jawaban tersimpan</span>
              <span v-else>Belum dijawab</span>
            </div>
            <div class="flex space-x-3">
              <button 
                @click="nextQuestion"
                :disabled="loading"
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-6 py-2 rounded-lg flex items-center gap-2">
                <i v-if="loading" class="fa-solid fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-arrow-right"></i>
                {{ currentIndex + 1 === totalQuestions ? 'Selesai' : 'Lanjut' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Warning Modal -->
      <div v-if="showWarning" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md mx-4">
          <div class="flex items-center mb-4">
            <i class="fa-solid fa-exclamation-triangle text-yellow-500 text-xl mr-3"></i>
            <h3 class="text-lg font-semibold">Peringatan</h3>
          </div>
          <p class="text-gray-600 mb-4">
            Waktu untuk soal ini akan segera habis. Jawaban akan otomatis disimpan dan pindah ke soal berikutnya.
          </p>
          <div class="flex justify-end">
            <button @click="showWarning = false" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
              OK
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox Modal -->
    <div
      v-if="showLightboxModal"
      class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
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

        <!-- Previous button -->
        <button
          v-if="lightboxImages.length > 1 && currentImageIndex > 0"
          @click.stop="previousImage"
          class="absolute left-4 top-1/2 transform -translate-y-1/2 z-10 bg-white bg-opacity-80 hover:bg-opacity-100 text-gray-800 rounded-full w-10 h-10 flex items-center justify-center transition"
        >
          <i class="fa-solid fa-chevron-left"></i>
        </button>

        <!-- Next button -->
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
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  enrollTest: Object,
  testResult: Object,
  currentQuestion: Object,
  currentIndex: Number,
  totalQuestions: Number
});

// Reactive data
const loading = ref(false);
const currentAnswer = ref('');
const timeLeft = ref(0);
const showWarning = ref(false);
const timer = ref(null);
const startTime = ref(null);

// Lightbox functionality
const showLightboxModal = ref(false);
const lightboxImages = ref([]);
const currentImageIndex = ref(0);

// Computed
const isLastQuestion = computed(() => props.currentIndex + 1 === props.totalQuestions);

// Methods
function formatTime(seconds) {
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function getTipeSoalText(tipeSoal) {
  const types = {
    'essay': 'Essay',
    'pilihan_ganda': 'Pilihan Ganda',
    'yes_no': 'Ya/Tidak'
  };
  return types[tipeSoal] || 'Unknown';
}

function getPilihanArray() {
  if (props.currentQuestion.tipe_soal === 'pilihan_ganda') {
    const pilihan = {};
    if (props.currentQuestion.pilihan_a) pilihan['A'] = props.currentQuestion.pilihan_a;
    if (props.currentQuestion.pilihan_b) pilihan['B'] = props.currentQuestion.pilihan_b;
    if (props.currentQuestion.pilihan_c) pilihan['C'] = props.currentQuestion.pilihan_c;
    if (props.currentQuestion.pilihan_d) pilihan['D'] = props.currentQuestion.pilihan_d;
    return pilihan;
  }
  return {};
}

function getOptionImage(optionKey) {
  const imageMap = {
    'A': props.currentQuestion.pilihan_a_gambar,
    'B': props.currentQuestion.pilihan_b_gambar,
    'C': props.currentQuestion.pilihan_c_gambar,
    'D': props.currentQuestion.pilihan_d_gambar
  };
  return imageMap[optionKey];
}

function getImageUrl(imagePath) {
  if (!imagePath) return '';
  
  // Convert backslashes to forward slashes and create full URL
  const normalizedPath = imagePath.replace(/\\/g, '/');
  return `/storage/${normalizedPath}`;
}

// Parse pertanyaan_gambar JSON string to array
function getPertanyaanImages(pertanyaan) {
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
}

function handleImageError(event) {
  console.log('Image failed to load:', event.target.src);
  event.target.style.display = 'none';
}

// Lightbox functions
function showLightbox(images, index) {
  lightboxImages.value = images;
  currentImageIndex.value = index;
  showLightboxModal.value = true;
}

function closeLightbox() {
  showLightboxModal.value = false;
  lightboxImages.value = [];
  currentImageIndex.value = 0;
}

function previousImage() {
  if (currentImageIndex.value > 0) {
    currentImageIndex.value--;
  }
}

function nextImage() {
  if (currentImageIndex.value < lightboxImages.value.length - 1) {
    currentImageIndex.value++;
  }
}

function selectAnswer(answer) {
  currentAnswer.value = answer;
  saveAnswer();
}

function getPlaceholder() {
  if (props.currentQuestion.tipe_soal === 'essay') {
    return 'Tulis jawaban essay Anda di sini...';
  } else if (props.currentQuestion.tipe_soal === 'pilihan_ganda') {
    return 'Pilih jawaban yang paling tepat...';
  } else if (props.currentQuestion.tipe_soal === 'yes_no') {
    return 'Pilih Ya atau Tidak...';
  }
  return 'Tulis jawaban Anda di sini...';
}

function startTimer() {
  // Clear timer yang mungkin masih berjalan
  if (timer.value) {
    clearInterval(timer.value);
    timer.value = null;
  }
  
  // Reset state timer
  timeLeft.value = props.currentQuestion.waktu_detik || 180; // Default 3 menit
  startTime.value = Date.now();
  showWarning.value = false;
  
  console.log(`Starting timer for question ${props.currentIndex + 1}: ${timeLeft.value} seconds`);
  
  timer.value = setInterval(() => {
    timeLeft.value--;
    
    // Warning pada 30 detik terakhir
    if (timeLeft.value === 30 && !showWarning.value) {
      showWarning.value = true;
      console.log('Timer warning: 30 seconds left');
    }
    
    // Auto next ketika waktu habis
    if (timeLeft.value <= 0) {
      clearInterval(timer.value);
      timer.value = null;
      console.log('Timer expired, auto moving to next question');
      // Auto save dan next
      saveAnswer();
      nextQuestion(true); // Auto next
    }
  }, 1000);
}

function saveAnswer() {
  // Auto save ke localStorage untuk backup
  localStorage.setItem(`answer_${props.testResult.id}_${props.currentQuestion.id}`, currentAnswer.value);
}

function nextQuestion(isAutoNext = false) {
  if (loading.value) return;
  
  loading.value = true;
  
  // Hitung waktu yang digunakan
  const timeUsed = startTime.value ? Math.floor((Date.now() - startTime.value) / 1000) : 0;
  
  // Clear timer dan reset state
  if (timer.value) {
    clearInterval(timer.value);
    timer.value = null;
  }
  
  // Reset timer state
  timeLeft.value = 0;
  showWarning.value = false;
  startTime.value = null;
  
  const data = {
    answer: currentAnswer.value,
    time_taken: timeUsed
  };
  
  // Jika auto next, tambahkan flag
  if (isAutoNext) {
    data.auto_next = true;
  }
  
  // Show loading message
  if (isAutoNext) {
    console.log('Waktu habis, otomatis pindah ke soal berikutnya...');
  }
  
  // Clear current answer untuk soal berikutnya
  currentAnswer.value = '';
  
  // Clear localStorage untuk soal saat ini
  localStorage.removeItem(`answer_${props.testResult.id}_${props.currentQuestion.id}`);
  
  router.post(route('enroll-test.next', props.enrollTest.id), data, {
    onSuccess: (page) => {
      // Halaman akan otomatis reload dengan soal berikutnya
      // atau redirect ke result jika test selesai
    },
    onError: (errors) => {
      console.error('Error:', errors);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Terjadi kesalahan: ' + (errors.message || 'Unknown error'),
        confirmButtonText: 'OK'
      });
      loading.value = false;
    },
    onFinish: () => {
      loading.value = false;
    }
  });
}

// Load saved answer
function loadSavedAnswer() {
  const saved = localStorage.getItem(`answer_${props.testResult.id}_${props.currentQuestion.id}`);
  if (saved) {
    currentAnswer.value = saved;
  } else {
    // Reset jawaban jika tidak ada yang tersimpan
    currentAnswer.value = '';
  }
  
  // Auto save untuk backup
  saveAnswer();
}

// Prevent back navigation
function preventBackNavigation() {
  window.addEventListener('beforeunload', (e) => {
    e.preventDefault();
    e.returnValue = '';
  });
  
  // Disable browser back button
  window.addEventListener('popstate', (e) => {
    e.preventDefault();
    window.history.pushState(null, '', window.location.href);
  });
}

// Clear all localStorage for this test
function clearAllTestData() {
  const keys = Object.keys(localStorage);
  keys.forEach(key => {
    if (key.startsWith(`answer_${props.testResult.id}_`)) {
      localStorage.removeItem(key);
    }
  });
}

// Watchers untuk reset timer saat soal berubah
watch(() => props.currentQuestion, (newQuestion, oldQuestion) => {
  if (newQuestion && newQuestion.id !== oldQuestion?.id) {
    console.log('Question changed, restarting timer');
    startTimer();
    loadSavedAnswer();
  }
}, { immediate: false });

watch(() => props.currentIndex, (newIndex, oldIndex) => {
  if (newIndex !== oldIndex) {
    console.log('Question index changed, restarting timer');
    startTimer();
    loadSavedAnswer();
  }
}, { immediate: false });

// Lifecycle
onMounted(() => {
  console.log('TakeTest component mounted for question:', props.currentIndex + 1);
  
  loadSavedAnswer();
  startTimer();
  preventBackNavigation();
  
  // Push state to prevent back
  window.history.pushState(null, '', window.location.href);
});

onUnmounted(() => {
  console.log('TakeTest component unmounting, clearing timer');
  
  if (timer.value) {
    clearInterval(timer.value);
    timer.value = null;
  }
  
  // Clear localStorage saat component di-unmount (test selesai)
  clearAllTestData();
});
</script>