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
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-br from-green-500 to-emerald-500 sm:mx-0 sm:h-10 sm:w-10">
              <i class="fas fa-clipboard-list text-white text-lg"></i>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-xl leading-6 font-bold text-white">
                Pilih Kuesioner untuk Sesi
              </h3>
              <p class="mt-2 text-sm text-white/80">
                Pilih kuesioner yang akan digunakan dalam sesi training ini.
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
              placeholder="Cari kuesioner..."
              class="block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm text-white placeholder-white/50"
            />
          </div>

          <!-- Questionnaire List -->
          <div class="max-h-96 overflow-y-auto space-y-3">
            <div
              v-for="questionnaire in filteredQuestionnaires"
              :key="questionnaire.id"
              @click="selectQuestionnaire(questionnaire)"
              class="cursor-pointer p-4 border border-white/20 rounded-xl hover:border-green-300 hover:bg-slate-700 transition-colors"
              :class="{ 'border-green-500 bg-green-500/20': selectedQuestionnaire?.id === questionnaire.id }"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h4 class="text-sm font-medium text-white">{{ questionnaire.title }}</h4>
                  <p v-if="questionnaire.description" class="text-sm text-white/70 mt-1">
                    {{ questionnaire.description }}
                  </p>
                  <div class="flex items-center space-x-4 mt-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-200 border border-green-500/30">
                      Kuesioner
                    </span>
                    <span class="text-xs text-white/60">
                      {{ questionnaire.questions_count || 0 }} pertanyaan
                    </span>
                    <span v-if="questionnaire.estimated_duration_minutes" class="text-xs text-white/60">
                      {{ questionnaire.estimated_duration_minutes }} menit
                    </span>
                  </div>
                </div>
                <div class="ml-4">
                  <input
                    type="radio"
                    :name="'questionnaire_' + questionnaire.id"
                    :value="questionnaire.id"
                    :checked="selectedQuestionnaire?.id === questionnaire.id"
                    class="h-4 w-4 text-green-600 focus:ring-green-500 border-white/20 bg-slate-700"
                  />
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredQuestionnaires.length === 0" class="text-center py-8">
              <i class="fas fa-search text-4xl text-white/40 mx-auto"></i>
              <h3 class="mt-2 text-sm font-medium text-white">Tidak ada kuesioner tersedia</h3>
              <p class="mt-1 text-sm text-white/60">
                {{ searchQuery ? 'Tidak ada kuesioner yang cocok dengan pencarian.' : 'Belum ada kuesioner yang dibuat.' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-slate-700 px-6 py-4 sm:flex sm:flex-row-reverse border-t border-white/20">
          <button
            type="button"
            @click="confirmSelection"
            :disabled="!selectedQuestionnaire"
            class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-lg px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-base font-medium text-white hover:from-green-600 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 transform hover:scale-105"
          >
            Pilih Kuesioner
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
  availableQuestionnaires: {
    type: Array,
    default: () => []
  },
  selectedQuestionnaire: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'selected'])

const searchQuery = ref('')
const selectedQuestionnaire = ref(props.selectedQuestionnaire)

// Watch for prop changes
watch(() => props.selectedQuestionnaire, (newQuestionnaire) => {
  selectedQuestionnaire.value = newQuestionnaire
})

const filteredQuestionnaires = computed(() => {
  if (!searchQuery.value) return props.availableQuestionnaires
  
  const query = searchQuery.value.toLowerCase()
  return props.availableQuestionnaires.filter(questionnaire => 
    questionnaire.title.toLowerCase().includes(query) ||
    (questionnaire.description && questionnaire.description.toLowerCase().includes(query))
  )
})

const selectQuestionnaire = (questionnaire) => {
  selectedQuestionnaire.value = questionnaire
}

const confirmSelection = () => {
  if (selectedQuestionnaire.value) {
    emit('selected', selectedQuestionnaire.value)
  }
}

const close = () => {
  searchQuery.value = ''
  selectedQuestionnaire.value = props.selectedQuestionnaire
  emit('close')
}
</script>
