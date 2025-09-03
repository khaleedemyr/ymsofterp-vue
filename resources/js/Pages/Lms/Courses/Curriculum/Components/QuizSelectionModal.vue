<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-slate-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-white/20">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600/20 via-purple-600/20 to-pink-600/20 px-6 pt-6 pb-4 border-b border-white/20">
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 sm:mx-0 sm:h-10 sm:w-10">
              <i class="fas fa-question-circle text-white text-lg"></i>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-xl leading-6 font-bold text-white">
                Pilih Quiz untuk Sesi
              </h3>
              <p class="mt-2 text-sm text-white/80">
                Pilih quiz yang akan digunakan dalam sesi training ini.
              </p>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="bg-slate-800 px-6 pt-6 pb-6">
          <!-- Search -->
          <div class="mb-4">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari quiz..."
              class="block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
            />
          </div>

          <!-- Quiz List -->
          <div class="max-h-96 overflow-y-auto space-y-3">
            <div
              v-for="quiz in filteredQuizzes"
              :key="quiz.id"
              @click="selectQuiz(quiz)"
              class="cursor-pointer p-4 border border-white/20 rounded-xl hover:border-blue-300 hover:bg-slate-700 transition-colors"
              :class="{ 'border-blue-500 bg-blue-500/20': selectedQuiz?.id === quiz.id }"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h4 class="text-sm font-medium text-white">{{ quiz.title }}</h4>
                  <p v-if="quiz.description" class="text-sm text-white/70 mt-1">
                    {{ quiz.description }}
                  </p>
                  <div class="flex items-center space-x-4 mt-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-200 border border-blue-500/30">
                      Quiz
                    </span>
                    <span class="text-xs text-white/60">
                      {{ quiz.questions_count || 0 }} pertanyaan
                    </span>
                    <span v-if="quiz.estimated_duration_minutes" class="text-xs text-white/60">
                      {{ quiz.estimated_duration_minutes }} menit
                    </span>
                  </div>
                </div>
                <div class="ml-4">
                  <input
                    type="radio"
                    :name="'quiz_' + quiz.id"
                    :value="quiz.id"
                    :checked="selectedQuiz?.id === quiz.id"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-white/20 bg-slate-700"
                  />
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredQuizzes.length === 0" class="text-center py-8">
              <i class="fas fa-search text-4xl text-white/40 mx-auto"></i>
              <h3 class="mt-2 text-sm font-medium text-white">Tidak ada quiz tersedia</h3>
              <p class="mt-1 text-sm text-white/60">
                {{ searchQuery ? 'Tidak ada quiz yang cocok dengan pencarian.' : 'Belum ada quiz yang dibuat.' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-slate-700 px-6 py-4 sm:flex sm:flex-row-reverse border-t border-white/20">
          <button
            type="button"
            @click="confirmSelection"
            :disabled="!selectedQuiz"
            class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-lg px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-base font-medium text-white hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 transform hover:scale-105"
          >
            Pilih Quiz
          </button>
          <button
            type="button"
            @click="close"
            class="mt-3 w-full inline-flex justify-center rounded-xl border border-white/20 shadow-lg px-6 py-3 bg-slate-600 text-base font-medium text-white hover:bg-slate-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-300"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  availableQuizzes: {
    type: Array,
    default: () => []
  },
  selectedQuiz: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'selected'])

const searchQuery = ref('')
const selectedQuiz = ref(props.selectedQuiz)

// Watch for prop changes
watch(() => props.selectedQuiz, (newQuiz) => {
  selectedQuiz.value = newQuiz
})

const filteredQuizzes = computed(() => {
  if (!searchQuery.value) return props.availableQuizzes
  
  const query = searchQuery.value.toLowerCase()
  return props.availableQuizzes.filter(quiz => 
    quiz.title.toLowerCase().includes(query) ||
    (quiz.description && quiz.description.toLowerCase().includes(query))
  )
})

const selectQuiz = (quiz) => {
  selectedQuiz.value = quiz
}

const confirmSelection = () => {
  if (selectedQuiz.value) {
    emit('selected', selectedQuiz.value)
  }
}

const close = () => {
  searchQuery.value = ''
  selectedQuiz.value = props.selectedQuiz
  emit('close')
}
</script>
