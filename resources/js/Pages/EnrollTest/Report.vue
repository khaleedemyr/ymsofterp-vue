<template>
  <AppLayout title="Report Hasil Test">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Report Hasil Test
        </h2>
      </div>
    </template>

    <div>
      <!-- Filter Section -->
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-4">
          <h3 class="text-lg font-semibold mb-4">Filter Report</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Cari User</label>
              <input type="text" v-model="filters.search" 
                     placeholder="Cari nama, email, jabatan, outlet, divisi..."
                     class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            
            <!-- Master Soal -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Master Soal</label>
              <select v-model="filters.master_soal_id" 
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua Master Soal</option>
                <option v-for="soal in masterSoals" :key="soal.id" :value="soal.id">
                  {{ soal.judul }}
                </option>
              </select>
            </div>
            
            <!-- Date From -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
              <input type="date" v-model="filters.date_from" 
                     class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            
            <!-- Date To -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
              <input type="date" v-model="filters.date_to" 
                     class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
            <button @click="recalculateAllScores" 
                    :disabled="isRecalculating"
                    class="bg-green-500 hover:bg-green-600 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg flex items-center gap-2">
              <i v-if="isRecalculating" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-calculator"></i>
              {{ isRecalculating ? 'Recalculating...' : 'Recalculate Semua Skor' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Report Data -->
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
          <div class="overflow-x-auto">
            <div v-if="testResults.data.length === 0" class="text-center py-12 text-gray-500">
              <i class="fa-solid fa-inbox text-4xl mb-4 block"></i>
              Tidak ada data hasil test
            </div>
            
            <div v-else class="space-y-4">
              <!-- Group by User -->
              <div v-for="userGroup in groupedResults" :key="userGroup.user.id" class="border border-gray-200 rounded-lg">
                <!-- User Header -->
                <div class="bg-gray-50 p-4 border-b border-gray-200">
                  <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                      <!-- Avatar -->
                      <div v-if="userGroup.user.avatar" class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-200 shadow-md cursor-pointer hover:shadow-lg transition-all" @click="openImageModal(getImageUrl(userGroup.user.avatar))">
                        <img :src="getImageUrl(userGroup.user.avatar)" :alt="userGroup.user.nama_lengkap" class="w-full h-full object-cover hover:scale-105 transition-transform" />
                      </div>
                      <div v-else class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold border-2 border-blue-200 shadow-md">
                        {{ getInitials(userGroup.user.nama_lengkap) }}
                      </div>
                      <div>
                        <h3 class="font-semibold text-gray-900">{{ userGroup.user.nama_lengkap }}</h3>
                        <p class="text-sm text-gray-500">{{ userGroup.user.email }}</p>
                        <div class="flex items-center space-x-4 mt-1">
                          <span v-if="userGroup.user.jabatan?.nama_jabatan" class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
                            <i class="fa-solid fa-briefcase mr-1"></i>{{ userGroup.user.jabatan.nama_jabatan }}
                          </span>
                          <span v-if="userGroup.user.divisi?.nama_divisi" class="text-xs text-green-600 bg-green-100 px-2 py-1 rounded-full">
                            <i class="fa-solid fa-building mr-1"></i>{{ userGroup.user.divisi.nama_divisi }}
                          </span>
                          <span v-if="userGroup.user.outlet?.nama_outlet" class="text-xs text-purple-600 bg-purple-100 px-2 py-1 rounded-full">
                            <i class="fa-solid fa-store mr-1"></i>{{ userGroup.user.outlet.nama_outlet }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <button @click="toggleUser(userGroup.user.id)" 
                            class="text-gray-500 hover:text-gray-700">
                      <i :class="expandedUsers.includes(userGroup.user.id) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'"></i>
                    </button>
                  </div>
                </div>

                <!-- User Test Results -->
                <div v-if="expandedUsers.includes(userGroup.user.id)" class="p-4 space-y-3">
                  <div v-for="testResult in userGroup.testResults" :key="testResult.id" 
                       class="border border-gray-200 rounded-lg">
                    <!-- Test Result Header -->
                    <div class="bg-blue-50 p-3 border-b border-gray-200">
                      <div class="flex justify-between items-center">
                        <div>
                          <h4 class="font-medium text-gray-900">{{ testResult.enroll_test?.master_soal?.judul || 'Judul tidak tersedia' }}</h4>
                          <p class="text-sm text-gray-600">
                            Selesai: {{ formatDateTime(testResult.completed_at) }}
                          </p>
                        </div>
                        <div class="flex items-center space-x-4">
                          <div class="text-right">
                            <div class="text-sm text-gray-500">Skor</div>
                            <div class="font-semibold text-lg" 
                                 :class="getGPAColor(testResult.gpa_score)">
                              GPA: {{ testResult.gpa_score || 0 }}
                            </div>
                            <div class="text-xs text-gray-400">
                              {{ testResult.grade_description || 'Belum dinilai' }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                              ({{ Math.round(testResult.percentage || 0) }}%)
                            </div>
                          </div>
                          <button @click="toggleTestResult(testResult.id)" 
                                  class="text-gray-500 hover:text-gray-700">
                            <i :class="expandedTestResults.includes(testResult.id) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'"></i>
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- Test Result Details -->
                    <div v-if="expandedTestResults.includes(testResult.id)" class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                          <div class="bg-blue-50 p-3 rounded border border-blue-200">
                            <div class="text-sm text-blue-600 font-medium">GPA Score</div>
                            <div class="font-bold text-2xl" :class="getGPAColor(testResult.gpa_score)">
                              {{ testResult.gpa_score || 0 }}
                            </div>
                            <div class="text-xs text-blue-500">{{ testResult.grade_description || 'Belum dinilai' }}</div>
                          </div>
                          <div class="bg-gray-50 p-3 rounded">
                            <div class="text-sm text-gray-500">Total Skor</div>
                            <div class="font-semibold">{{ testResult.total_score || 0 }} / {{ (testResult.test_answers?.length || 0) * 4 }}</div>
                            <div class="text-xs text-gray-400">({{ Math.round(testResult.percentage || 0) }}%)</div>
                          </div>
                          <div class="bg-gray-50 p-3 rounded">
                            <div class="text-sm text-gray-500">Durasi</div>
                            <div class="font-semibold">{{ formatDuration(testResult.time_taken_seconds) }}</div>
                          </div>
                          <div class="bg-gray-50 p-3 rounded">
                            <div class="text-sm text-gray-500">Status</div>
                            <div class="font-semibold" :class="getStatusColor(testResult.status)">
                              {{ getStatusText(testResult.status) }}
                            </div>
                          </div>
                        </div>

                        <!-- GPA Information -->
                        <div class="bg-gradient-to-r from-blue-50 to-green-50 border border-blue-200 rounded-lg p-4 mb-4">
                          <div class="flex items-center mb-3">
                            <i class="fa-solid fa-graduation-cap text-blue-600 mr-2"></i>
                            <h6 class="font-medium text-blue-900">Sistem Penilaian GPA (Grade Point Average)</h6>
                          </div>
                          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="text-sm text-blue-800">
                              <p class="mb-2 font-semibold">Sistem Bobot:</p>
                              <p class="mb-1">• <strong>Essay:</strong> 70% dari total nilai</p>
                              <p class="mb-1">• <strong>Pilihan Ganda & Ya/Tidak:</strong> 30% dari total nilai</p>
                            </div>
                            <div class="text-sm text-green-800">
                              <p class="mb-2 font-semibold">Konversi GPA:</p>
                              <p class="mb-1">• <strong>4.0:</strong> A (90-100%) - Sempurna</p>
                              <p class="mb-1">• <strong>3.5:</strong> A- (80-89%) - Sangat Baik</p>
                              <p class="mb-1">• <strong>3.0:</strong> B+ (75-79%) - Baik Sekali</p>
                              <p class="mb-1">• <strong>2.5:</strong> B (70-74%) - Baik</p>
                              <p class="mb-1">• <strong>2.0:</strong> B- (65-69%) - Cukup Baik</p>
                              <p class="mb-1">• <strong>1.5:</strong> C+ (60-64%) - Cukup</p>
                              <p class="mb-1">• <strong>1.0:</strong> C (55-59%) - Kurang</p>
                              <p class="mb-1">• <strong>0.5:</strong> D (50-54%) - Sangat Kurang</p>
                              <p class="mb-1">• <strong>0.0:</strong> E (<50%) - Tidak Lulus</p>
                            </div>
                          </div>
                        </div>

                      <!-- Questions and Answers -->
                      <div class="space-y-4">
                        <h5 class="font-medium text-gray-900">Detail Jawaban:</h5>
                        <div v-for="(answer, index) in testResult.test_answers" :key="answer.id" 
                             class="border border-gray-200 rounded-lg p-4">
                          <div class="flex justify-between items-start mb-3">
                            <h6 class="font-medium text-gray-900">Soal {{ index + 1 }} - {{ getTipeSoalText(answer.soal_pertanyaan?.tipe_soal) }}</h6>
                            <div class="flex items-center space-x-2">
                              <!-- Badge untuk PG dan Yes/No -->
                              <template v-if="answer.soal_pertanyaan?.tipe_soal !== 'essay'">
                                <span v-if="answer.is_correct" class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                  <i class="fa-solid fa-check mr-1"></i>Benar
                                </span>
                                <span v-else class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">
                                  <i class="fa-solid fa-times mr-1"></i>Salah
                                </span>
                              </template>
                              <!-- Status untuk Essay -->
                              <template v-else>
                                <span v-if="answer.score > 0" class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                                  <i class="fa-solid fa-edit mr-1"></i>Dinilai
                                </span>
                                <span v-else class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                                  <i class="fa-solid fa-clock mr-1"></i>Belum Dinilai
                                </span>
                              </template>
                              <!-- Score untuk semua tipe -->
                              <span class="text-sm text-gray-500">{{ answer.score }}/4</span>
                              <span class="text-xs text-gray-400">({{ Math.round((answer.score / 4) * 100) }}%)</span>
                            </div>
                          </div>

                          <!-- Question -->
                          <div class="mb-3">
                            <div class="text-sm text-gray-500 mb-1">Pertanyaan:</div>
                            <div class="text-gray-800 bg-gray-50 p-3 rounded">{{ answer.soal_pertanyaan?.pertanyaan || 'Pertanyaan tidak tersedia' }}</div>
                          </div>

                          <!-- User Answer -->
                          <div class="mb-3">
                            <div class="text-sm text-gray-500 mb-1">Jawaban User:</div>
                            <div class="text-gray-800 bg-blue-50 p-3 rounded">{{ answer.user_answer || 'Tidak dijawab' }}</div>
                          </div>

                          <!-- Correct Answer (for non-essay) -->
                          <div v-if="answer.soal_pertanyaan?.tipe_soal !== 'essay'" class="mb-3">
                            <div class="text-sm text-gray-500 mb-1">Jawaban Benar:</div>
                            <div class="text-gray-800 bg-green-50 p-3 rounded">{{ getCorrectAnswerText(answer.soal_pertanyaan) }}</div>
                          </div>

                          <!-- Essay Score Input -->
                          <div v-if="answer.soal_pertanyaan?.tipe_soal === 'essay'" class="flex items-center space-x-4">
                            <div class="flex-1">
                              <label class="block text-sm font-medium text-gray-700 mb-1">Input Score Essay:</label>
                              <div class="flex items-center space-x-2">
                                <input type="number" 
                                       v-model="essayScores[answer.id]"
                                       min="1" 
                                       max="4"
                                       step="1"
                                       class="w-24 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-500">/ 4</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Bulk Update Button for this Test Result -->
                      <div v-if="getTestResultEssayAnswers(testResult).length > 0" class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                          <div class="text-sm text-gray-500">
                            {{ getTestResultEssayAnswers(testResult).length }} essay dalam test ini
                          </div>
                          <button @click="bulkUpdateTestResultEssayScores(testResult.id)" 
                                  :disabled="isBulkUpdating || !hasValidTestResultScores(testResult.id)"
                                  class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-semibold">
                            <i v-if="isBulkUpdating" class="fa-solid fa-spinner fa-spin"></i>
                            <i v-else class="fa-solid fa-save"></i>
                            Update Semua Essay Test Ini
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Pagination -->
          <div v-if="testResults.total > 0" class="px-4 py-3 border-t border-gray-200 bg-white">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="text-sm text-gray-700">
                  Menampilkan {{ testResults.from }} sampai {{ testResults.to }} dari {{ testResults.total }} data
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
                          page === testResults.current_page
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

    <!-- Lightbox Modal -->
    <div v-if="lightboxVisible" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" @click="closeImageModal">
      <div class="relative max-w-4xl max-h-full p-4" @click.stop>
        <!-- Close Button -->
        <button @click="closeImageModal" 
                class="absolute top-2 right-2 text-white bg-black bg-opacity-50 hover:bg-opacity-75 rounded-full p-2 z-10">
          <i class="fa-solid fa-times text-xl"></i>
        </button>
        
        <!-- Navigation Buttons -->
        <button v-if="lightboxImages.length > 1" 
                @click="previousImage" 
                :disabled="lightboxIndex === 0"
                class="absolute left-2 top-1/2 transform -translate-y-1/2 text-white bg-black bg-opacity-50 hover:bg-opacity-75 rounded-full p-2 z-10 disabled:opacity-50 disabled:cursor-not-allowed">
          <i class="fa-solid fa-chevron-left text-xl"></i>
        </button>
        
        <button v-if="lightboxImages.length > 1" 
                @click="nextImage" 
                :disabled="lightboxIndex === lightboxImages.length - 1"
                class="absolute right-2 top-1/2 transform -translate-y-1/2 text-white bg-black bg-opacity-50 hover:bg-opacity-75 rounded-full p-2 z-10 disabled:opacity-50 disabled:cursor-not-allowed">
          <i class="fa-solid fa-chevron-right text-xl"></i>
        </button>
        
        <!-- Image -->
        <img :src="lightboxImages[lightboxIndex]" 
             :alt="'Avatar'" 
             class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
        
        <!-- Image Counter -->
        <div v-if="lightboxImages.length > 1" 
             class="absolute bottom-2 left-1/2 transform -translate-x-1/2 text-white bg-black bg-opacity-50 px-3 py-1 rounded-full text-sm">
          {{ lightboxIndex + 1 }} / {{ lightboxImages.length }}
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  testResults: Object,
  masterSoals: Array,
  filters: Object
});

// Reactive data
const filters = ref({
  search: props.filters?.search || '',
  master_soal_id: props.filters?.master_soal_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  per_page: props.filters?.per_page || 10
});

const expandedUsers = ref([]);
const expandedTestResults = ref([]);
const essayScores = ref({});
const loadingScores = ref({});

// Bulk update variables
const isBulkUpdating = ref(false);
const isRecalculating = ref(false);

// Lightbox variables
const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

// Computed
const groupedResults = computed(() => {
  const groups = {};
  
  props.testResults.data.forEach(testResult => {
    // Check if enroll_test and user exist
    if (testResult.enroll_test?.user?.id) {
      const userId = testResult.enroll_test.user.id;
      if (!groups[userId]) {
        groups[userId] = {
          user: testResult.enroll_test.user,
          testResults: []
        };
      }
      groups[userId].testResults.push(testResult);
    }
  });
  
  return Object.values(groups);
});

// Get essay answers for a specific test result
function getTestResultEssayAnswers(testResult) {
  return testResult.test_answers?.filter(answer => 
    answer.soal_pertanyaan?.tipe_soal === 'essay'
  ) || [];
}

// Check if there are valid scores for a specific test result
function hasValidTestResultScores(testResultId) {
  const testResult = props.testResults.data.find(tr => tr.id === testResultId);
  if (!testResult) return false;
  
  const essayAnswers = getTestResultEssayAnswers(testResult);
  return essayAnswers.some(answer => {
    const score = essayScores.value[answer.id];
    return score && score >= 1 && score <= 4;
  });
}

// Initialize essay scores
const initializeEssayScores = () => {
  props.testResults.data.forEach(testResult => {
    testResult.test_answers?.forEach(answer => {
      if (answer.soal_pertanyaan?.tipe_soal === 'essay') {
        essayScores.value[answer.id] = answer.score || 0;
      }
    });
  });
};

// Methods
function applyFilters() {
  router.get(route('enroll-test.report'), filters.value, {
    preserveState: true,
    replace: true
  });
}

function resetFilters() {
  filters.value = {
    search: '',
    master_soal_id: '',
    date_from: '',
    date_to: '',
    per_page: 10
  };
  applyFilters();
}

function changePage(page) {
  if (page >= 1 && page <= props.testResults.last_page) {
    router.get(route('enroll-test.report'), { ...filters.value, page }, {
      preserveState: true,
      replace: true
    });
  }
}

function getVisiblePages() {
  const current = props.testResults.current_page;
  const last = props.testResults.last_page;
  const pages = [];
  
  const start = Math.max(1, current - 2);
  const end = Math.min(last, current + 2);
  
  for (let i = start; i <= end; i++) {
    pages.push(i);
  }
  
  return pages;
}

function toggleUser(userId) {
  const index = expandedUsers.value.indexOf(userId);
  if (index > -1) {
    expandedUsers.value.splice(index, 1);
  } else {
    expandedUsers.value.push(userId);
  }
}

function toggleTestResult(testResultId) {
  const index = expandedTestResults.value.indexOf(testResultId);
  if (index > -1) {
    expandedTestResults.value.splice(index, 1);
  } else {
    expandedTestResults.value.push(testResultId);
  }
}

async function updateEssayScore(answerId) {
  if (loadingScores.value[answerId]) return;
  
  const score = essayScores.value[answerId];
  if (score === undefined || score === null) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Masukkan score terlebih dahulu',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  if (score < 1 || score > 4) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Score essay harus antara 1-4',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  loadingScores.value[answerId] = true;
  
  try {
    const response = await axios.post(route('enroll-test.update-essay-score', answerId), {
      score: score
    });
    
    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: 'Score berhasil diupdate',
        confirmButtonText: 'OK'
      }).then(() => {
        // Reload page to get updated data
        router.reload();
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal update score: ' + response.data.message,
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error updating essay score:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat update score',
      confirmButtonText: 'OK'
    });
  } finally {
    loadingScores.value[answerId] = false;
  }
}

// Bulk update essay scores for a specific test result
async function bulkUpdateTestResultEssayScores(testResultId) {
  if (isBulkUpdating.value) return;
  
  const testResult = props.testResults.data.find(tr => tr.id === testResultId);
  if (!testResult) return;
  
  const essayAnswers = getTestResultEssayAnswers(testResult);
  if (essayAnswers.length === 0) return;
  
  // Validate scores for this test result
  const validScores = [];
  const invalidScores = [];
  
  essayAnswers.forEach(answer => {
    const score = essayScores.value[answer.id];
    if (score && score >= 1 && score <= 4) {
      validScores.push({
        answer_id: answer.id,
        score: parseInt(score)
      });
    } else if (score && (score < 1 || score > 4)) {
      invalidScores.push(answer.id);
    }
  });
  
  if (invalidScores.length > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Beberapa nilai tidak valid. Pastikan semua nilai antara 1-4.',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  if (validScores.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Tidak ada nilai yang valid untuk diupdate.',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  isBulkUpdating.value = true;
  
  try {
    const response = await axios.post(route('enroll-test.bulk-update-essay-scores'), {
      scores: validScores
    });
    
    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: response.data.message,
        confirmButtonText: 'OK'
      }).then(() => {
        // Reload page to get updated data
        router.reload();
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal update scores: ' + response.data.message,
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error bulk updating essay scores:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat update scores',
      confirmButtonText: 'OK'
    });
  } finally {
    isBulkUpdating.value = false;
  }
}

// Recalculate all scores with new weighted system
async function recalculateAllScores() {
  if (isRecalculating.value) return;
  
  const result = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin recalculate semua skor dengan sistem bobot baru? (Essay 70%, PG+Yes/No 30%)',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Recalculate',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#6b7280'
  });
  
  if (!result.isConfirmed) return;
  
  isRecalculating.value = true;
  
  try {
    const response = await axios.post(route('enroll-test.recalculate-all-scores'));
    
    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: response.data.message,
        confirmButtonText: 'OK'
      }).then(() => {
        // Reload page to get updated data
        router.reload();
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: 'Gagal recalculate: ' + response.data.message,
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error recalculating scores:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat recalculate scores',
      confirmButtonText: 'OK'
    });
  } finally {
    isRecalculating.value = false;
  }
}

function getTipeSoalText(tipeSoal) {
  const types = {
    'essay': 'Essay',
    'pilihan_ganda': 'Pilihan Ganda',
    'yes_no': 'Ya/Tidak'
  };
  return types[tipeSoal] || 'Unknown';
}

function getCorrectAnswerText(soal) {
  if (!soal) return 'Data tidak tersedia';
  
  if (soal.tipe_soal === 'yes_no') {
    return soal.jawaban_benar === 'yes' ? 'Ya' : 'Tidak';
  }
  
  if (soal.tipe_soal === 'pilihan_ganda') {
    const pilihan = {
      'A': soal.pilihan_a,
      'B': soal.pilihan_b,
      'C': soal.pilihan_c,
      'D': soal.pilihan_d
    };
    return pilihan[soal.jawaban_benar] || soal.jawaban_benar;
  }
  
  return soal.jawaban_benar || 'Data tidak tersedia';
}

function getScoreColor(percentage) {
  if (percentage >= 80) return 'text-green-600';
  if (percentage >= 60) return 'text-yellow-600';
  return 'text-red-600';
}

function getGPAColor(gpaScore) {
  if (gpaScore >= 4.0) return 'text-green-600';      // A (Sempurna)
  if (gpaScore >= 3.5) return 'text-green-500';     // A- (Sangat Baik)
  if (gpaScore >= 3.0) return 'text-blue-600';      // B+ (Baik Sekali)
  if (gpaScore >= 2.5) return 'text-blue-500';      // B (Baik)
  if (gpaScore >= 2.0) return 'text-yellow-600';    // B- (Cukup Baik)
  if (gpaScore >= 1.5) return 'text-yellow-500';   // C+ (Cukup)
  if (gpaScore >= 1.0) return 'text-orange-600';    // C (Kurang)
  if (gpaScore >= 0.5) return 'text-red-500';       // D (Sangat Kurang)
  return 'text-red-600';                            // E (Tidak Lulus)
}

function getStatusColor(status) {
  const colors = {
    'completed': 'text-green-600',
    'in_progress': 'text-yellow-600',
    'timeout': 'text-red-600',
    'cancelled': 'text-gray-600'
  };
  return colors[status] || 'text-gray-600';
}

function getStatusText(status) {
  const statuses = {
    'completed': 'Selesai',
    'in_progress': 'Sedang Test',
    'timeout': 'Timeout',
    'cancelled': 'Dibatalkan'
  };
  return statuses[status] || status;
}

function formatDateTime(dateTime) {
  if (!dateTime) return '-';
  return new Date(dateTime).toLocaleString('id-ID');
}

function formatDuration(seconds) {
  if (!seconds) return '-';
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  return `${minutes}m ${remainingSeconds}s`;
}

// Avatar and image helper functions
function getInitials(name) {
  if (!name) return '?';
  return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().substring(0, 2);
}

function getImageUrl(imagePath) {
  if (!imagePath) return null;
  try {
    return `/storage/${imagePath}`;
  } catch (error) {
    console.error('Error processing image:', error);
    return null;
  }
}

// Lightbox functions
function openImageModal(imageUrl) {
  if (!imageUrl) return;
  
  lightboxImages.value = [imageUrl];
  lightboxIndex.value = 0;
  lightboxVisible.value = true;
}

function closeImageModal() {
  lightboxVisible.value = false;
  lightboxImages.value = [];
  lightboxIndex.value = 0;
}

function previousImage() {
  if (lightboxIndex.value > 0) {
    lightboxIndex.value--;
  }
}

function nextImage() {
  if (lightboxIndex.value < lightboxImages.value.length - 1) {
    lightboxIndex.value++;
  }
}

// Watch for changes in testResults and initialize essay scores
watch(() => props.testResults, () => {
  initializeEssayScores();
}, { immediate: true, deep: true });

// Initialize on mount
onMounted(() => {
  initializeEssayScores();
});
</script>

