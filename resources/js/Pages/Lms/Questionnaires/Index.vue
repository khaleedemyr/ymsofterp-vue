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
                  Kuesioner
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Kelola dan buat kuesioner untuk mengumpulkan feedback
                </p>
              </div>
              <Link
                :href="route('lms.questionnaires.create')"
                class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
              >
                <i class="fas fa-plus mr-2"></i>
                Buat Kuesioner
              </Link>
            </div>
          </div>
        </div>

        <!-- Questionnaires List -->
        <div class="space-y-6">
          <div
            v-for="questionnaire in questionnaires.data"
            :key="questionnaire.id"
            class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6"
          >
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <div class="flex items-center space-x-3 mb-2">
                  <h3 class="text-xl font-bold text-white">{{ questionnaire.title }}</h3>
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
                  <span v-if="questionnaire.is_anonymous" class="bg-blue-500/20 text-blue-300 px-2 py-1 rounded-full text-xs font-semibold">
                    Anonymous
                  </span>
                </div>
                
                <p v-if="questionnaire.description" class="text-white/70 mb-3">{{ questionnaire.description }}</p>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ questionnaire.questions_count || 0 }}</div>
                    <div class="text-sm text-white/60">Pertanyaan</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ questionnaire.responses_count || 0 }}</div>
                    <div class="text-sm text-white/60">Respons</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ questionnaire.completion_rate || 0 }}%</div>
                    <div class="text-sm text-white/60">Tingkat Penyelesaian</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-white">
                      {{ questionnaire.is_active ? 'Aktif' : 'Tidak Aktif' }}
                    </div>
                    <div class="text-sm text-white/60">Status</div>
                  </div>
                </div>

                <div class="flex items-center space-x-2 text-sm text-white/60">
                  <span>Dibuat oleh: {{ questionnaire.creator?.nama_lengkap || 'Unknown' }}</span>
                  <span>•</span>
                  <span>{{ formatDate(questionnaire.created_at) }}</span>
                  <span v-if="questionnaire.start_date">•</span>
                  <span v-if="questionnaire.start_date">Mulai: {{ formatDate(questionnaire.start_date) }}</span>
                  <span v-if="questionnaire.end_date">•</span>
                  <span v-if="questionnaire.end_date">Berakhir: {{ formatDate(questionnaire.end_date) }}</span>
                </div>
              </div>
              
              <div class="flex items-center space-x-2">
                <Link
                  :href="route('lms.questionnaires.show', questionnaire.id)"
                  class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200"
                >
                  <i class="fas fa-eye mr-1"></i>
                  Lihat
                </Link>
                <Link
                  :href="route('lms.questionnaires.questions.index', questionnaire.id)"
                  class="bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200"
                >
                  <i class="fas fa-question-circle mr-1"></i>
                  Pertanyaan
                </Link>
                <Link
                  :href="route('lms.questionnaires.edit', questionnaire.id)"
                  class="bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200"
                >
                  <i class="fas fa-edit mr-1"></i>
                  Edit
                </Link>
                <button
                  @click="deleteQuestionnaire(questionnaire.id)"
                  class="bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200"
                >
                  <i class="fas fa-trash mr-1"></i>
                  Hapus
                </button>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div
            v-if="questionnaires.data.length === 0"
            class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-12 text-center"
          >
            <div class="text-6xl text-white/30 mb-4">
              <i class="fas fa-clipboard-list"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-2">Belum ada kuesioner</h3>
            <p class="text-white/70 mb-6">
              Mulai membuat kuesioner pertama Anda untuk mengumpulkan feedback
            </p>
            <Link
              :href="route('lms.questionnaires.create')"
              class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
            >
              <i class="fas fa-plus mr-2"></i>
              Buat Kuesioner Pertama
            </Link>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="questionnaires.data.length > 0" class="mt-8">
          <Pagination :links="questionnaires.links" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  questionnaires: Object
})

const getStatusText = (status) => {
  const statusMap = {
    'published': 'Published',
    'draft': 'Draft',
    'archived': 'Archived'
  }
  return statusMap[status] || status
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

const deleteQuestionnaire = (questionnaireId) => {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus kuesioner ini? Tindakan ini tidak dapat dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('lms.questionnaires.destroy', questionnaireId), {
        onSuccess: () => {
          Swal.fire(
            'Terhapus!',
            'Kuesioner berhasil dihapus.',
            'success'
          )
        },
        onError: () => {
          Swal.fire(
            'Error!',
            'Gagal menghapus kuesioner.',
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
