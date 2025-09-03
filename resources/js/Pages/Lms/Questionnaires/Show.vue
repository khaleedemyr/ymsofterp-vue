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
                  Detail Kuesioner
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  {{ questionnaire.title }}
                </p>
              </div>
              <div class="flex items-center space-x-4">
                <Link
                  :href="route('lms.questionnaires.questions.index', questionnaire.id)"
                  class="bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                >
                  <i class="fas fa-question-circle mr-2"></i>
                  Kelola Pertanyaan
                </Link>
                <Link
                  :href="route('lms.questionnaires.edit', questionnaire.id)"
                  class="bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                >
                  <i class="fas fa-edit mr-2"></i>
                  Edit Kuesioner
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Questionnaire Statistics -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 mb-6">
          <h3 class="text-xl font-bold text-white mb-4 flex items-center">
            <i class="fas fa-chart-bar mr-2 text-green-400"></i>
            Statistik Kuesioner
          </h3>
          
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center">
              <div class="text-3xl font-bold text-white">{{ questionnaire.questions_count || 0 }}</div>
              <div class="text-sm text-white/60">Pertanyaan</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-white">{{ questionnaire.responses_count || 0 }}</div>
              <div class="text-sm text-white/60">Respons</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-white">{{ questionnaire.completion_rate || 0 }}%</div>
              <div class="text-sm text-white/60">Tingkat Penyelesaian</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-white">
                {{ questionnaire.is_active ? 'Aktif' : 'Tidak Aktif' }}
              </div>
              <div class="text-sm text-white/60">Status</div>
            </div>
          </div>
        </div>

        <!-- Questionnaire Details -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 mb-6">
          <h3 class="text-xl font-bold text-white mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-blue-400"></i>
            Informasi Kuesioner
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 class="text-lg font-semibold text-white mb-3">Detail Umum</h4>
              <div class="space-y-3">
                <div class="flex items-center space-x-3">
                  <span class="text-white/60">Judul:</span>
                  <span class="text-white font-medium">{{ questionnaire.title }}</span>
                </div>
                <div v-if="questionnaire.description" class="flex items-start space-x-3">
                  <span class="text-white/60">Deskripsi:</span>
                  <span class="text-white font-medium">{{ questionnaire.description }}</span>
                </div>
                <div class="flex items-center space-x-3">
                  <span class="text-white/60">Status:</span>
                  <span
                    :class="{
                      'bg-green-500/20 text-green-300': questionnaire.status === 'published',
                      'bg-yellow-500/20 text-yellow-300': questionnaire.status === 'draft',
                      'bg-red-500/20 text-red-300': questionnaire.status === 'archived'
                    }"
                    class="px-2 py-1 rounded-full text-xs font-semibold"
                  >
                    {{ getStatusText(questionnaire.status) }}
                  </span>
                </div>
                <div class="flex items-center space-x-3">
                  <span class="text-white/60">Anonim:</span>
                  <span class="text-white font-medium">{{ questionnaire.is_anonymous ? 'Ya' : 'Tidak' }}</span>
                </div>
                <div class="flex items-center space-x-3">
                  <span class="text-white/60">Multiple Response:</span>
                  <span class="text-white font-medium">{{ questionnaire.allow_multiple_responses ? 'Ya' : 'Tidak' }}</span>
                </div>
              </div>
            </div>
            
            <div>
              <h4 class="text-lg font-semibold text-white mb-3">Periode & Pembuat</h4>
              <div class="space-y-3">
                <div v-if="questionnaire.start_date" class="flex items-center space-x-3">
                  <span class="text-white/60">Tanggal Mulai:</span>
                  <span class="text-white font-medium">{{ formatDate(questionnaire.start_date) }}</span>
                </div>
                <div v-if="questionnaire.end_date" class="flex items-center space-x-3">
                  <span class="text-white/60">Tanggal Berakhir:</span>
                  <span class="text-white font-medium">{{ formatDate(questionnaire.end_date) }}</span>
                </div>
                <div class="flex items-center space-x-3">
                  <span class="text-white/60">Dibuat oleh:</span>
                  <span class="text-white font-medium">{{ questionnaire.creator?.nama_lengkap || 'Unknown' }}</span>
                </div>
                <div class="flex items-center space-x-3">
                  <span class="text-white/60">Tanggal Dibuat:</span>
                  <span class="text-white font-medium">{{ formatDate(questionnaire.created_at) }}</span>
                </div>
                <div v-if="questionnaire.instructions" class="flex items-start space-x-3">
                  <span class="text-white/60">Instruksi:</span>
                  <span class="text-white font-medium">{{ questionnaire.instructions }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

                 <!-- Questions List -->
         <div class="space-y-6">
           <div
             v-for="(question, index) in (questions || [])"
             :key="question.id"
             class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6"
           >
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <div class="flex items-center space-x-3 mb-2">
                  <span class="bg-blue-500/20 text-blue-300 px-3 py-1 rounded-full text-sm font-semibold">
                    Pertanyaan {{ index + 1 }}
                  </span>
                  <span
                    :class="{
                      'bg-green-500/20 text-green-300': question.question_type === 'multiple_choice',
                      'bg-purple-500/20 text-purple-300': question.question_type === 'essay',
                      'bg-orange-500/20 text-orange-300': question.question_type === 'true_false',
                      'bg-pink-500/20 text-pink-300': question.question_type === 'rating',
                      'bg-indigo-500/20 text-indigo-300': question.question_type === 'checkbox'
                    }"
                    class="px-2 py-1 rounded-full text-xs font-semibold"
                  >
                    {{ getQuestionTypeText(question.question_type) }}
                  </span>
                  <span v-if="question.is_required" class="bg-red-500/20 text-red-300 px-2 py-1 rounded-full text-xs font-semibold">
                    Wajib
                  </span>
                  <span v-else class="bg-gray-500/20 text-gray-300 px-2 py-1 rounded-full text-xs font-semibold">
                    Opsional
                  </span>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ question.question_text }}</h3>
              </div>
            </div>

            <!-- Options for Multiple Choice -->
            <div v-if="question.question_type === 'multiple_choice' && question.options && question.options.length > 0" class="space-y-2">
              <div
                v-for="option in question.options"
                :key="option.id"
                class="flex items-center space-x-3 p-3 rounded-lg bg-white/5"
              >
                <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                  <div class="w-2 h-2 bg-blue-400 rounded-full"></div>
                </div>
                <span class="text-white/80 flex-1">{{ option.option_text }}</span>
              </div>
            </div>

            <!-- Options for Checkbox -->
            <div v-else-if="question.question_type === 'checkbox' && question.options && question.options.length > 0" class="space-y-2">
              <div
                v-for="option in question.options"
                :key="option.id"
                class="flex items-center space-x-3 p-3 rounded-lg bg-white/5"
              >
                <div class="w-4 h-4 rounded border-2 border-white/30 flex items-center justify-center">
                  <div class="w-2 h-2 bg-indigo-400 rounded"></div>
                </div>
                <span class="text-white/80 flex-1">{{ option.option_text }}</span>
              </div>
            </div>

            <!-- Essay Answer Preview -->
            <div v-else-if="question.question_type === 'essay'" class="bg-white/5 p-3 rounded-lg">
              <p class="text-white/60 text-sm">Jawaban essay akan ditampilkan sebagai teks</p>
            </div>

            <!-- True/False Options -->
            <div v-else-if="question.question_type === 'true_false'" class="space-y-2">
              <div class="flex items-center space-x-3 p-3 rounded-lg bg-white/5">
                <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                  <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                </div>
                <span class="text-white/80">Benar</span>
              </div>
              <div class="flex items-center space-x-3 p-3 rounded-lg bg-white/5">
                <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                  <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                </div>
                <span class="text-white/80">Salah</span>
              </div>
            </div>

            <!-- Rating Preview -->
            <div v-else-if="question.question_type === 'rating'" class="bg-white/5 p-3 rounded-lg">
              <div class="flex items-center space-x-2">
                <span class="text-white/60 text-sm">Rating 1-5:</span>
                <div class="flex space-x-1">
                  <i class="fas fa-star text-yellow-400"></i>
                  <i class="fas fa-star text-yellow-400"></i>
                  <i class="fas fa-star text-yellow-400"></i>
                  <i class="fas fa-star text-yellow-400"></i>
                  <i class="fas fa-star text-yellow-400"></i>
                </div>
              </div>
            </div>
          </div>

                     <!-- Empty State -->
           <div
             v-if="!questions || questions.length === 0"
             class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-12 text-center"
           >
            <div class="text-6xl text-white/30 mb-4">
              <i class="fas fa-question-circle"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-2">Belum ada pertanyaan</h3>
            <p class="text-white/70 mb-6">
              Mulai menambahkan pertanyaan untuk kuesioner ini
            </p>
            <Link
              :href="route('lms.questionnaires.questions.index', questionnaire.id)"
              class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
            >
              <i class="fas fa-plus mr-2"></i>
              Tambah Pertanyaan Pertama
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  questionnaire: Object,
  questions: {
    type: Array,
    default: () => []
  }
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
    'true_false': 'Benar/Salah',
    'rating': 'Rating',
    'checkbox': 'Checkbox'
  }
  return typeMap[type] || type
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
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
