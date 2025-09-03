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
                  Kelola Pertanyaan Kuesioner
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  {{ questionnaire.title }}
                </p>
              </div>
              <div class="flex items-center space-x-4">
                <button
                  @click="showAddQuestionModal = true"
                  class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="loading"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Tambah Pertanyaan
                </button>
                                 <Link
                   :href="route('lms.questionnaires.show', questionnaire.id)"
                   class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                   :class="{ 'opacity-50': loading }"
                 >
                  <i class="fas fa-arrow-left mr-2"></i>
                  Kembali ke Kuesioner
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

        <!-- Questions List -->
        <div class="space-y-6">
          <div
            v-for="(question, index) in questions"
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
              <div class="flex items-center space-x-2">
                <button
                  @click="editQuestion(question)"
                  class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="loading"
                >
                  <i class="fas fa-edit mr-1"></i>
                  Edit
                </button>
                <button
                  @click="deleteQuestion(question.id)"
                  class="bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="loading"
                >
                  <i class="fas fa-trash mr-1"></i>
                  Hapus
                </button>
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
            v-if="questions.length === 0"
            class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-12 text-center"
          >
            <div class="text-6xl text-white/30 mb-4">
              <i class="fas fa-question-circle"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-2">Belum ada pertanyaan</h3>
            <p class="text-white/70 mb-6">
              Mulai menambahkan pertanyaan untuk kuesioner ini
            </p>
            <button
              @click="showAddQuestionModal = true"
              class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="loading"
            >
              <i class="fas fa-plus mr-2"></i>
              Tambah Pertanyaan Pertama
            </button>
          </div>
        </div>
      </div>

      <!-- Add/Edit Question Modal -->
      <div
        v-if="showAddQuestionModal || showEditQuestionModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto relative">
          <!-- Loading Overlay -->
          <div v-if="loading" class="absolute inset-0 bg-black/30 backdrop-blur-sm rounded-2xl flex items-center justify-center z-10">
            <div class="text-center">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white mx-auto mb-4"></div>
              <p class="text-white font-semibold">Menyimpan pertanyaan...</p>
            </div>
          </div>
          
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-white">
              {{ showEditQuestionModal ? 'Edit Pertanyaan' : 'Tambah Pertanyaan Baru' }}
            </h3>
            <button
              @click="closeQuestionModal"
              class="text-white/60 hover:text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="loading"
            >
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <form @submit.prevent="saveQuestion" class="space-y-6">
            <!-- Question Type -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Tipe Pertanyaan <span class="text-red-400">*</span></label>
              <select
                v-model="questionForm.question_type"
                required
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent [&>option]:bg-slate-800 [&>option]:text-white disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading"
              >
                <option value="multiple_choice">Pilihan Ganda</option>
                <option value="essay">Essay</option>
                <option value="true_false">Benar/Salah</option>
                <option value="rating">Rating</option>
                <option value="checkbox">Checkbox</option>
              </select>
            </div>

            <!-- Question Text -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Pertanyaan <span class="text-red-400">*</span></label>
              <textarea
                v-model="questionForm.question_text"
                rows="3"
                required
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                placeholder="Masukkan pertanyaan..."
                :disabled="loading"
              ></textarea>
            </div>

            <!-- Required -->
            <div>
              <label class="flex items-center space-x-3">
                <input
                  v-model="questionForm.is_required"
                  type="checkbox"
                  class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="loading"
                />
                <span class="text-sm font-medium text-gray-300">Pertanyaan Wajib</span>
              </label>
            </div>

            <!-- Options for Multiple Choice/Checkbox -->
            <div v-if="['multiple_choice', 'checkbox'].includes(questionForm.question_type)">
              <label class="block text-sm font-medium text-gray-300 mb-2">Opsi Jawaban <span class="text-red-400">*</span></label>
              <div class="space-y-3">
                <div
                  v-for="(option, index) in questionForm.options"
                  :key="index"
                  class="flex items-center space-x-3"
                >
                  <input
                    v-model="option.option_text"
                    type="text"
                    required
                    class="flex-1 px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                    :placeholder="`Opsi ${index + 1}`"
                    :disabled="loading"
                  />
                  <button
                    v-if="questionForm.options.length > 2"
                    @click="removeOption(index)"
                    type="button"
                    class="text-red-400 hover:text-red-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="loading"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
                <button
                  @click="addOption"
                  type="button"
                  class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="loading"
                >
                  <i class="fas fa-plus mr-1"></i>
                  Tambah Opsi
                </button>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-3 pt-6">
              <button
                type="button"
                @click="closeQuestionModal"
                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg transition-colors duration-200 text-center disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="loading || !isQuestionFormValid"
                class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed relative overflow-hidden"
              >
                <span v-if="loading" class="flex items-center justify-center">
                  <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                  Menyimpan...
                </span>
                <span v-else class="flex items-center justify-center">
                  <i class="fas fa-save mr-2"></i>
                  {{ showEditQuestionModal ? 'Update Pertanyaan' : 'Simpan Pertanyaan' }}
                </span>
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

const props = defineProps({
  questionnaire: Object,
  questions: Array
})

const loading = ref(false)
const showAddQuestionModal = ref(false)
const showEditQuestionModal = ref(false)

// Question form data
const questionForm = ref({
  id: null,
  question_text: '',
  question_type: 'multiple_choice',
  is_required: true,
  options: [
    { option_text: '' },
    { option_text: '' }
  ]
})

// Form validation
const isQuestionFormValid = computed(() => {
  if (!questionForm.value.question_text.trim()) {
    return false
  }

  if (['multiple_choice', 'checkbox'].includes(questionForm.value.question_type)) {
    return questionForm.value.options.length >= 2 && 
           questionForm.value.options.every(opt => opt.option_text.trim())
  }

  return true
})

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

const addOption = () => {
  questionForm.value.options.push({ option_text: '' })
}

const removeOption = (index) => {
  questionForm.value.options.splice(index, 1)
}

const editQuestion = (question) => {
  questionForm.value = {
    id: question.id,
    question_text: question.question_text,
    question_type: question.question_type,
    is_required: question.is_required,
    options: question.options ? [...question.options] : [
      { option_text: '' },
      { option_text: '' }
    ]
  }
  showEditQuestionModal.value = true
}

const closeQuestionModal = () => {
  showAddQuestionModal.value = false
  showEditQuestionModal.value = false
  resetQuestionForm()
}

const resetQuestionForm = () => {
  questionForm.value = {
    id: null,
    question_text: '',
    question_type: 'multiple_choice',
    is_required: true,
    options: [
      { option_text: '' },
      { option_text: '' }
    ]
  }
}

const saveQuestion = async () => {
  if (!isQuestionFormValid.value) {
    Swal.fire({
      icon: 'error',
      title: 'Validasi Error',
      text: 'Mohon lengkapi semua field yang wajib diisi!'
    })
    return
  }

  loading.value = true

  try {
    const formData = {
      question_text: questionForm.value.question_text,
      question_type: questionForm.value.question_type,
      is_required: questionForm.value.is_required,
      options: ['multiple_choice', 'checkbox'].includes(questionForm.value.question_type) ? questionForm.value.options : null
    }

    if (showEditQuestionModal.value) {
      await router.put(route('lms.questionnaires.questions.update', [props.questionnaire.id, questionForm.value.id]), formData, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Pertanyaan berhasil diperbarui!'
          })
          closeQuestionModal()
        },
        onError: (errors) => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: Object.values(errors)[0] || 'Terjadi kesalahan saat memperbarui pertanyaan'
          })
        }
      })
    } else {
      await router.post(route('lms.questionnaires.questions.store', props.questionnaire.id), formData, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Pertanyaan berhasil ditambahkan!'
          })
          closeQuestionModal()
        },
        onError: (errors) => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: Object.values(errors)[0] || 'Terjadi kesalahan saat menambahkan pertanyaan'
          })
        }
      })
    }
  } catch (error) {
    console.error('Error saving question:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menyimpan pertanyaan'
    })
  } finally {
    loading.value = false
  }
}

const deleteQuestion = (questionId) => {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus pertanyaan ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      loading.value = true
      
      router.delete(route('lms.questionnaires.questions.destroy', [props.questionnaire.id, questionId]), {
        onSuccess: () => {
          loading.value = false
          Swal.fire(
            'Terhapus!',
            'Pertanyaan berhasil dihapus.',
            'success'
          )
        },
        onError: () => {
          loading.value = false
          Swal.fire(
            'Error!',
            'Gagal menghapus pertanyaan.',
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

/* Loading state styling */
.disabled\:opacity-50:disabled {
  opacity: 0.5;
}

.disabled\:cursor-not-allowed:disabled {
  cursor: not-allowed;
}

.pointer-events-none {
  pointer-events: none;
}
</style>
