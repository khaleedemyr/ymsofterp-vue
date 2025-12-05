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
                  Quiz Management
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Kelola quiz untuk evaluasi pembelajaran
                </p>
              </div>
              <div class="flex items-center space-x-4">
                <div class="text-center">
                  <div class="text-6xl font-bold text-white drop-shadow-lg animate-pulse">
                    {{ quizzes.data.length }}
                  </div>
                  <div class="text-lg text-white/90 drop-shadow-md">Total Quiz</div>
                </div>
                <div class="flex items-center space-x-3">
                  <Link
                    :href="route('lms.quizzes.create')"
                    class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                  >
                    <i class="fas fa-plus mr-2"></i>
                    Buat Quiz
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quiz Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <div
            v-for="quiz in quizzes.data"
            :key="quiz.id"
            class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl hover:shadow-3xl transition-all duration-300 transform hover:scale-105 group"
          >
            <!-- Quiz Header -->
            <div class="p-6 border-b border-white/10">
              <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                  <h3 class="text-xl font-bold text-white mb-2 group-hover:text-blue-300 transition-colors">
                    {{ quiz.title }}
                  </h3>
                  <p class="text-white/70 text-sm line-clamp-2">
                    {{ quiz.description || 'Tidak ada deskripsi' }}
                  </p>
                </div>
                <div class="flex items-center space-x-2">
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
              </div>
              

            </div>

            <!-- Quiz Stats -->
            <div class="p-6">
              <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="text-center">
                  <div class="text-2xl font-bold text-white">{{ quiz.questions_count || 0 }}</div>
                  <div class="text-xs text-white/60">Pertanyaan</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-white">{{ quiz.attempts_count || 0 }}</div>
                  <div class="text-xs text-white/60">Percobaan</div>
                </div>
              </div>

              <!-- Quiz Settings -->
              <div class="space-y-2 text-sm text-white/70">
                <div class="flex items-center justify-between">
                  <span>Nilai Minimum:</span>
                  <span class="font-semibold text-white">{{ quiz.passing_score }}%</span>
                </div>
                <div class="flex items-center justify-between">
                  <span>Batas Waktu:</span>
                  <span class="font-semibold text-white">
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
                  <span>Maks Percobaan:</span>
                  <span class="font-semibold text-white">
                    {{ quiz.max_attempts || 'Tidak terbatas' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Quiz Actions -->
            <div class="p-6 pt-0">
                             <div class="flex space-x-2">
                 <Link
                   :href="route('lms.quizzes.questions.index', quiz.id)"
                   class="flex-1 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200 text-center"
                 >
                   <i class="fas fa-question-circle mr-1"></i>
                   Pertanyaan
                 </Link>
                 <Link
                   :href="route('lms.quizzes.show', quiz.id)"
                   class="flex-1 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200 text-center"
                 >
                   <i class="fas fa-eye mr-1"></i>
                   Lihat
                 </Link>
                <Link
                  :href="route('lms.quizzes.edit', quiz.id)"
                  class="flex-1 bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200 text-center"
                >
                  <i class="fas fa-edit mr-1"></i>
                  Edit
                </Link>
                <button
                  @click="deleteQuiz(quiz.id)"
                  class="flex-1 bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200"
                >
                  <i class="fas fa-trash mr-1"></i>
                  Hapus
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="quizzes.data.length === 0"
          class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-12 text-center"
        >
          <div class="text-6xl text-white/30 mb-4">
            <i class="fas fa-question-circle"></i>
          </div>
          <h3 class="text-2xl font-bold text-white mb-2">Belum ada Quiz</h3>
          <p class="text-white/70 mb-6">
            Mulai membuat quiz pertama untuk evaluasi pembelajaran
          </p>
          <Link
            :href="route('lms.quizzes.create')"
            class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
          >
            <i class="fas fa-plus mr-2"></i>
            Buat Quiz Pertama
          </Link>
        </div>

        <!-- Pagination -->
        <div
          v-if="quizzes.data.length > 0"
          class="mt-8 flex justify-center"
        >
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-xl shadow-2xl p-4">
            <div class="flex items-center space-x-2">
              <Link
                v-if="quizzes.prev_page_url"
                :href="quizzes.prev_page_url"
                class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg transition-colors"
              >
                <i class="fas fa-chevron-left"></i>
              </Link>
              <span class="px-4 py-2 text-white">
                Halaman {{ quizzes.current_page }} dari {{ quizzes.last_page }}
              </span>
              <Link
                v-if="quizzes.next_page_url"
                :href="quizzes.next_page_url"
                class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg transition-colors"
              >
                <i class="fas fa-chevron-right"></i>
              </Link>
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
  quizzes: Object
})

const getStatusText = (status) => {
  const statusMap = {
    'published': 'Published',
    'draft': 'Draft',
    'archived': 'Archived'
  }
  return statusMap[status] || status
}

const deleteQuiz = (quizId) => {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus quiz ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('lms.quizzes.destroy', quizId), {
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

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
