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
                  {{ quiz.title }}
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Detail Quiz
                </p>
              </div>
              <div class="flex items-center space-x-4">
                                 <Link
                   :href="route('lms.quizzes.questions.index', quiz.id)"
                   class="bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                 >
                   <i class="fas fa-question-circle mr-2"></i>
                   Kelola Pertanyaan
                 </Link>
                 <Link
                   :href="route('lms.quizzes.edit', quiz.id)"
                   class="bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                 >
                   <i class="fas fa-edit mr-2"></i>
                   Edit Quiz
                 </Link>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Main Content -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Quiz Information -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                <i class="fas fa-info-circle mr-2 text-blue-400"></i>
                Informasi Quiz
              </h3>
              
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">Judul</label>
                  <p class="text-white font-semibold">{{ quiz.title }}</p>
                </div>
                
                <div v-if="quiz.description">
                  <label class="block text-sm font-medium text-gray-300 mb-1">Deskripsi</label>
                  <p class="text-white/80">{{ quiz.description }}</p>
                </div>
                
                <div v-if="quiz.instructions">
                  <label class="block text-sm font-medium text-gray-300 mb-1">Instruksi</label>
                  <p class="text-white/80">{{ quiz.instructions }}</p>
                </div>
                

              </div>
            </div>

            <!-- Quiz Statistics -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                <i class="fas fa-chart-bar mr-2 text-green-400"></i>
                Statistik Quiz
              </h3>
              
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center">
                  <div class="text-3xl font-bold text-white">{{ quiz.questions_count || 0 }}</div>
                  <div class="text-sm text-white/60">Pertanyaan</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold text-white">{{ quiz.attempts_count || 0 }}</div>
                  <div class="text-sm text-white/60">Percobaan</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold text-white">{{ quiz.average_score || 0 }}%</div>
                  <div class="text-sm text-white/60">Rata-rata Nilai</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold text-white">{{ quiz.pass_rate || 0 }}%</div>
                  <div class="text-sm text-white/60">Tingkat Kelulusan</div>
                </div>
              </div>
            </div>

            <!-- Questions List -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-white flex items-center">
                  <i class="fas fa-question-circle mr-2 text-purple-400"></i>
                  Daftar Pertanyaan
                </h3>
                <Link
                  :href="route('lms.quizzes.questions.index', quiz.id)"
                  class="bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200"
                >
                  <i class="fas fa-plus mr-1"></i>
                  Tambah Pertanyaan
                </Link>
              </div>
              
              <div v-if="quiz.questions && quiz.questions.length > 0" class="space-y-4">
                <div
                  v-for="(question, index) in quiz.questions"
                  :key="question.id"
                  class="backdrop-blur-xl bg-white/5 border border-white/10 rounded-xl p-4"
                >
                  <div class="flex items-start justify-between mb-3">
                    <h4 class="text-lg font-semibold text-white">
                      Pertanyaan {{ index + 1 }}
                    </h4>
                    <span class="px-2 py-1 bg-blue-500/20 text-blue-300 rounded-full text-xs font-semibold">
                      {{ getQuestionTypeText(question.question_type) }}
                    </span>
                  </div>
                  
                  <p class="text-white/80 mb-3">{{ question.question_text }}</p>
                  
                  <!-- Question Image -->
                  <div v-if="question.image_path" class="mb-3">
                    <img 
                      :src="question.image_url" 
                      :alt="question.image_alt_text || 'Gambar pertanyaan'"
                      class="w-full max-w-md h-auto rounded-lg border border-white/20"
                    />
                    <p v-if="question.image_alt_text" class="text-white/60 text-sm mt-1">{{ question.image_alt_text }}</p>
                  </div>
                  
                  <!-- Show points -->
                  <div class="mb-3">
                    <span class="bg-yellow-500/20 text-yellow-300 px-2 py-1 rounded-full text-xs font-semibold">
                      {{ question.points }} poin
                    </span>
                  </div>
                  
                  <!-- Options for Multiple Choice -->
                  <div v-if="question.question_type === 'multiple_choice' && question.options && question.options.length > 0" class="space-y-2">
                    <div
                      v-for="option in question.options"
                      :key="option.id"
                      class="flex items-center space-x-3 p-2 rounded-lg"
                      :class="option.is_correct ? 'bg-green-500/20 border border-green-500/30' : 'bg-white/5'"
                    >
                      <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                        <div v-if="option.is_correct" class="w-2 h-2 bg-green-400 rounded-full"></div>
                      </div>
                      <span class="text-white/80">{{ option.option_text }}</span>
                      <span v-if="option.is_correct" class="ml-auto text-green-400 text-xs font-semibold">
                        Benar
                      </span>
                    </div>
                  </div>

                  <!-- Essay Answer Preview -->
                  <div v-else-if="question.question_type === 'essay'" class="bg-white/5 p-3 rounded-lg">
                    <p class="text-white/60 text-sm">Jawaban essay akan dinilai secara manual</p>
                  </div>

                  <!-- True/False Options -->
                  <div v-else-if="question.question_type === 'true_false'" class="space-y-2">
                    <div class="flex items-center space-x-3 p-2 rounded-lg bg-white/5">
                      <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                        <div v-if="question.options && question.options.find(o => o.option_text === 'Benar' && o.is_correct)" class="w-2 h-2 bg-green-400 rounded-full"></div>
                      </div>
                      <span class="text-white/80">Benar</span>
                      <span v-if="question.options && question.options.find(o => o.option_text === 'Benar' && o.is_correct)" class="ml-auto text-green-400 text-xs font-semibold">
                        Benar
                      </span>
                    </div>
                    <div class="flex items-center space-x-3 p-2 rounded-lg bg-white/5">
                      <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                        <div v-if="question.options && question.options.find(o => o.option_text === 'Salah' && o.is_correct)" class="w-2 h-2 bg-green-400 rounded-full"></div>
                      </div>
                      <span class="text-white/80">Salah</span>
                      <span v-if="question.options && question.options.find(o => o.option_text === 'Salah' && o.is_correct)" class="ml-auto text-green-400 text-xs font-semibold">
                        Benar
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              
              <div v-else class="text-center py-8">
                <div class="text-4xl text-white/30 mb-4">
                  <i class="fas fa-question-circle"></i>
                </div>
                <h4 class="text-lg font-semibold text-white mb-2">Belum ada pertanyaan</h4>
                <p class="text-white/60 mb-4">
                  Tambahkan pertanyaan untuk quiz ini
                </p>
                <Link
                  :href="route('lms.quizzes.questions.index', quiz.id)"
                  class="bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Tambah Pertanyaan Pertama
                </Link>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Quiz Settings -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                <i class="fas fa-cog mr-2 text-yellow-400"></i>
                Pengaturan Quiz
              </h3>
              
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Status:</span>
                  <span
                    :class="{
                      'bg-green-500/20 text-green-300': quiz.status === 'published',
                      'bg-yellow-500/20 text-yellow-300': quiz.status === 'draft',
                      'bg-red-500/20 text-red-300': quiz.status === 'archived'
                    }"
                    class="px-2 py-1 rounded-full text-xs font-semibold"
                  >
                    {{ getStatusText(quiz.status) }}
                  </span>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Nilai Minimum:</span>
                  <span class="text-white font-semibold">{{ quiz.passing_score }}%</span>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Batas Waktu:</span>
                  <span class="text-white font-semibold">
                    <span v-if="quiz.time_limit_type === 'total'">
                      {{ quiz.time_limit_minutes ? `${quiz.time_limit_minutes} menit` : 'Tidak ada' }}
                    </span>
                    <span v-else-if="quiz.time_limit_type === 'per_question'">
                      {{ quiz.time_per_question_seconds ? `${quiz.time_per_question_seconds} detik per pertanyaan` : 'Tidak ada' }}
                    </span>
                    <span v-else>
                      Tidak ada
                    </span>
                  </span>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Maks Percobaan:</span>
                  <span class="text-white font-semibold">
                    {{ quiz.max_attempts || 'Tidak terbatas' }}
                  </span>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Acak Pertanyaan:</span>
                  <span class="text-white font-semibold">
                    {{ quiz.is_randomized ? 'Ya' : 'Tidak' }}
                  </span>
                </div>
                
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Tampilkan Hasil:</span>
                  <span class="text-white font-semibold">
                    {{ quiz.show_results ? 'Ya' : 'Tidak' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Recent Attempts -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                <i class="fas fa-history mr-2 text-indigo-400"></i>
                Percobaan Terbaru
              </h3>
              
              <div v-if="quiz.attempts && quiz.attempts.length > 0" class="space-y-3">
                <div
                  v-for="attempt in quiz.attempts.slice(0, 5)"
                  :key="attempt.id"
                  class="flex items-center justify-between p-3 bg-white/5 rounded-lg"
                >
                  <div>
                    <p class="text-white font-semibold">{{ attempt.user?.nama_lengkap || 'User tidak ditemukan' }}</p>
                    <p class="text-white/60 text-sm">{{ formatDate(attempt.created_at) }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-white font-bold">{{ attempt.score }}%</p>
                    <p class="text-xs" :class="attempt.score >= quiz.passing_score ? 'text-green-400' : 'text-red-400'">
                      {{ attempt.score >= quiz.passing_score ? 'Lulus' : 'Tidak Lulus' }}
                    </p>
                  </div>
                </div>
              </div>
              
              <div v-else class="text-center py-4">
                <p class="text-white/60">Belum ada percobaan</p>
              </div>
            </div>

            <!-- Quick Actions -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                <i class="fas fa-bolt mr-2 text-orange-400"></i>
                Aksi Cepat
              </h3>
              
                             <div class="space-y-3">
                 <Link
                   :href="route('lms.quizzes.questions.index', quiz.id)"
                   class="w-full bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-4 py-3 rounded-lg font-semibold transition-all duration-200 text-center block"
                 >
                   <i class="fas fa-question-circle mr-2"></i>
                   Kelola Pertanyaan
                 </Link>
                 <Link
                   :href="route('lms.quizzes.edit', quiz.id)"
                   class="w-full bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-4 py-3 rounded-lg font-semibold transition-all duration-200 text-center block"
                 >
                   <i class="fas fa-edit mr-2"></i>
                   Edit Quiz
                 </Link>
                
                <button
                  @click="duplicateQuiz"
                  class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-3 rounded-lg font-semibold transition-all duration-200"
                >
                  <i class="fas fa-copy mr-2"></i>
                  Duplikat Quiz
                </button>
                
                <button
                  @click="deleteQuiz"
                  class="w-full bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-4 py-3 rounded-lg font-semibold transition-all duration-200"
                >
                  <i class="fas fa-trash mr-2"></i>
                  Hapus Quiz
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
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  quiz: Object
})

const getStatusText = (status) => {
  const statusMap = {
    'published': 'Published',
    'draft': 'Draft',
    'archived': 'Archived'
  }
  return statusMap[status] || status
}

const getQuestionTypeText = (type) => {
  const typeMap = {
    'multiple_choice': 'Pilihan Ganda',
    'essay': 'Essay',
    'true_false': 'Benar/Salah'
  }
  return typeMap[type] || type
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const duplicateQuiz = () => {
  Swal.fire({
    title: 'Duplikat Quiz',
    text: 'Apakah Anda yakin ingin menduplikasi quiz ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Duplikasi!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      // Implement duplicate functionality
      Swal.fire(
        'Berhasil!',
        'Quiz berhasil diduplikasi.',
        'success'
      )
    }
  })
}

const deleteQuiz = () => {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus quiz ini? Tindakan ini tidak dapat dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('lms.quizzes.destroy', props.quiz.id), {
        onSuccess: () => {
          Swal.fire(
            'Terhapus!',
            'Quiz berhasil dihapus.',
            'success'
          )
        },
        onError: () => {
          Swal.fire(
            'Error!',
            'Gagal menghapus quiz.',
            'error'
          )
        }
      })
    }
  })
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
</style>
