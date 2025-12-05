<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden">
      <!-- Animated Background Elements -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute top-40 left-40 w-80 h-80 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
      </div>

      <div class="relative z-10 py-8 px-6">
        <!-- Header Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl shadow-2xl mb-8">
          <div class="p-8 bg-gradient-to-r from-blue-600/80 via-purple-600/80 to-pink-600/80 rounded-3xl">
            <div class="flex items-center justify-between">
              <div class="space-y-4">
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  Buat Quiz Baru
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Buat quiz untuk evaluasi pembelajaran
                </p>
              </div>
              <div class="flex items-center space-x-4">
                <Link
                  :href="route('lms.quizzes.index')"
                  class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                >
                  <i class="fas fa-arrow-left mr-2"></i>
                  Kembali ke Quiz
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Create Form -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
          <form @submit.prevent="createQuiz" class="space-y-8">
            <!-- Basic Information Section -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-info-circle mr-2 text-blue-400"></i>
                Informasi Dasar
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Judul Quiz <span class="text-red-400">*</span></label>
                  <input
                    v-model="form.title"
                    type="text"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Masukkan judul quiz"
                    :disabled="loading"
                  />
                </div>
                

                
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi</label>
                  <textarea
                    v-model="form.description"
                    rows="3"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Deskripsi quiz (opsional)"
                    :disabled="loading"
                  ></textarea>
                </div>
                
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-300 mb-2">Instruksi</label>
                  <textarea
                    v-model="form.instructions"
                    rows="3"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Instruksi untuk peserta quiz"
                    :disabled="loading"
                  ></textarea>
                </div>
              </div>
            </div>

            <!-- Quiz Settings Section -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-cog mr-2 text-green-400"></i>
                Pengaturan Quiz
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Nilai Minimum Lulus <span class="text-red-400">*</span></label>
                  <div class="relative">
                    <input
                      v-model="form.passing_score"
                      type="number"
                      min="0"
                      max="100"
                      required
                      class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-12"
                      placeholder="70"
                      :disabled="loading"
                    />
                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white/60">%</span>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Batas Waktu</label>
                                     <select
                     v-model="form.time_limit_type"
                     class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent mb-2 [&>option]:bg-slate-800 [&>option]:text-white"
                     :disabled="loading"
                   >
                     <option value="">Tidak ada batas waktu</option>
                     <option value="total">Total waktu quiz</option>
                     <option value="per_question">Waktu per pertanyaan</option>
                   </select>
                  
                  <div v-if="form.time_limit_type === 'total'" class="mt-2">
                    <input
                      v-model="form.time_limit_minutes"
                      type="number"
                      min="1"
                      class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="60"
                      :disabled="loading"
                    />
                    <p class="text-xs text-gray-400 mt-1">Total waktu dalam menit</p>
                  </div>
                  
                  <div v-if="form.time_limit_type === 'per_question'" class="mt-2">
                    <input
                      v-model="form.time_per_question_seconds"
                      type="number"
                      min="1"
                      class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="30"
                      :disabled="loading"
                    />
                    <p class="text-xs text-gray-400 mt-1">Waktu per pertanyaan dalam detik</p>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Maksimal Percobaan</label>
                  <input
                    v-model="form.max_attempts"
                    type="number"
                    min="1"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="3"
                    :disabled="loading"
                  />
                  <p class="text-xs text-gray-400 mt-1">Kosongkan jika tidak terbatas</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                                     <select
                     v-model="form.status"
                     required
                     class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent [&>option]:bg-slate-800 [&>option]:text-white"
                     :disabled="loading"
                   >
                     <option value="draft">Draft</option>
                     <option value="published">Published</option>
                     <option value="archived">Archived</option>
                   </select>
                </div>
                
                <div class="flex items-center space-x-3">
                  <input
                    v-model="form.is_randomized"
                    type="checkbox"
                    id="is_randomized"
                    class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-2"
                    :disabled="loading"
                  />
                  <label for="is_randomized" class="text-sm font-medium text-gray-300">
                    Acak urutan pertanyaan
                  </label>
                </div>
                
                <div class="flex items-center space-x-3">
                  <input
                    v-model="form.show_results"
                    type="checkbox"
                    id="show_results"
                    class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-2"
                    :disabled="loading"
                  />
                  <label for="show_results" class="text-sm font-medium text-gray-300">
                    Tampilkan hasil setelah selesai
                  </label>
                </div>
              </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
              <Link
                :href="route('lms.quizzes.index')"
                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg transition-colors duration-200 text-center"
                :disabled="loading"
              >
                Batal
              </Link>
              <button
                type="submit"
                :disabled="loading || !isFormValid"
                class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed relative overflow-hidden"
              >
                <span v-if="loading" class="flex items-center justify-center">
                  <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                  Menyimpan...
                </span>
                <span v-else class="flex items-center justify-center">
                  <i class="fas fa-save mr-2"></i>
                  Simpan Quiz
                </span>
                <!-- Loading overlay -->
                <div v-if="loading" class="absolute inset-0 bg-green-600/20 backdrop-blur-sm"></div>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({})

const loading = ref(false)

// Form data
const form = ref({
  title: '',
  description: '',
  instructions: '',
  time_limit_type: '',
  time_limit_minutes: '',
  time_per_question_seconds: '',
  passing_score: 70,
  max_attempts: '',
  is_randomized: false,
  show_results: true,
  status: 'draft'
})

// Form validation
const isFormValid = computed(() => {
  return form.value.title.trim() && 
         form.value.passing_score >= 0 && 
         form.value.passing_score <= 100 &&
         form.value.status
})

const createQuiz = async () => {
  if (!isFormValid.value) {
    Swal.fire({
      icon: 'error',
      title: 'Validasi Error',
      text: 'Mohon lengkapi semua field yang wajib diisi!'
    })
    return
  }

  // Show loading modal
  Swal.fire({
    title: 'Sabar Bu Ghea....',
    text: 'Antosan sakedap Bu Ghea, Nuju loding',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })

  loading.value = true

  try {
    await router.post(route('lms.quizzes.store'), form.value, {
      onSuccess: () => {
        Swal.close()
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Quiz berhasil dibuat!'
        })
      },
      onError: (errors) => {
        Swal.close()
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: Object.values(errors)[0] || 'Terjadi kesalahan saat membuat quiz'
        })
      },
      onFinish: () => {
        loading.value = false
      }
    })
  } catch (error) {
    Swal.close()
    loading.value = false
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat membuat quiz'
    })
  }
}
</script>

<style scoped>
.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

@keyframes blob {
  0% {
    transform: translate(0px, 0px) scale(1);
  }
  33% {
    transform: translate(30px, -50px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  100% {
    transform: translate(0px, 0px) scale(1);
  }
}

/* Global dropdown styling */
:deep(select option) {
  background-color: #1e293b !important;
  color: white !important;
}

:deep(select option:hover) {
  background-color: #334155 !important;
}

:deep(select option:checked) {
  background-color: #3b82f6 !important;
}
</style>
