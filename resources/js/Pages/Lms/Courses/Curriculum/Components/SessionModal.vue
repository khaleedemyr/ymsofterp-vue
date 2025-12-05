<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-slate-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-white/20">
        <form @submit.prevent="saveSession">
          <!-- Header -->
          <div class="bg-gradient-to-r from-blue-600/20 via-purple-600/20 to-pink-600/20 px-6 pt-6 pb-4 border-b border-white/20">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 sm:mx-0 sm:h-10 sm:w-10">
                <i class="fas fa-book-open text-white text-lg"></i>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-xl leading-6 font-bold text-white">
                  {{ session ? 'Edit Sesi' : 'Tambah Sesi Baru' }}
                </h3>
                <p class="mt-2 text-sm text-white/80">
                  Buat sesi training dengan quiz, materi, dan kuesioner.
                </p>
              </div>
            </div>
          </div>

          <!-- Form Content -->
          <div class="bg-slate-800 px-6 pt-6 pb-6">
            <div class="space-y-4">
              <!-- Session Number -->
              <div>
                <label for="session_number" class="block text-sm font-medium text-white mb-2">
                  Nomor Sesi *
                </label>
                <input
                  id="session_number"
                  v-model="form.session_number"
                  type="number"
                  min="1"
                  required
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="1"
                />
              </div>

              <!-- Session Title -->
              <div>
                <label for="session_title" class="block text-sm font-medium text-white mb-2">
                  Judul Sesi *
                </label>
                <input
                  id="session_title"
                  v-model="form.session_title"
                  type="text"
                  required
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="Contoh: Pengenalan Dasar"
                />
              </div>

              <!-- Session Description -->
              <div>
                <label for="session_description" class="block text-sm font-medium text-white mb-2">
                  Deskripsi Sesi
                </label>
                <textarea
                  id="session_description"
                  v-model="form.session_description"
                  rows="3"
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="Jelaskan apa yang akan dipelajari dalam sesi ini..."
                ></textarea>
              </div>

              <!-- Order Number -->
              <div>
                <label for="order_number" class="block text-sm font-medium text-white mb-2">
                  Urutan *
                </label>
                <input
                  id="order_number"
                  v-model="form.order_number"
                  type="number"
                  min="1"
                  required
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="1"
                />
              </div>

              <!-- Estimated Duration -->
              <div>
                <label for="estimated_duration_minutes" class="block text-sm font-medium text-white mb-2">
                  Durasi Estimasi (menit)
                </label>
                <input
                  id="estimated_duration_minutes"
                  v-model="form.estimated_duration_minutes"
                  type="number"
                  min="1"
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="60"
                />
              </div>

              <!-- Quiz Selection -->
              <div>
                <label for="quiz_id" class="block text-sm font-medium text-white mb-2">
                  Quiz (Opsional)
                </label>
                <select
                  id="quiz_id"
                  v-model="form.quiz_id"
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white"
                >
                  <option value="" class="bg-slate-700 text-white">Pilih Quiz</option>
                  <option
                    v-for="quiz in availableQuizzes"
                    :key="quiz.id"
                    :value="quiz.id"
                    class="bg-slate-700 text-white"
                  >
                    {{ quiz.title }}
                  </option>
                </select>
              </div>

              <!-- Questionnaire Selection -->
              <div>
                <label for="questionnaire_id" class="block text-sm font-medium text-white mb-2">
                  Kuesioner (Opsional)
                </label>
                <select
                  id="questionnaire_id"
                  v-model="form.questionnaire_id"
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white"
                >
                  <option value="" class="bg-slate-700 text-white">Pilih Kuesioner</option>
                  <option
                    v-for="questionnaire in availableQuestionnaires"
                    :key="questionnaire.id"
                    :value="questionnaire.id"
                    class="bg-slate-700 text-white"
                  >
                    {{ questionnaire.title }}
                  </option>
                </select>
              </div>

              <!-- Required Checkbox -->
              <div class="flex items-center">
                <input
                  id="is_required"
                  v-model="form.is_required"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-white/20 rounded bg-slate-700"
                />
                <label for="is_required" class="ml-2 block text-sm text-white">
                  Sesi wajib diselesaikan
                </label>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-slate-700 px-6 py-4 sm:flex sm:flex-row-reverse border-t border-white/20">
            <button
              type="submit"
              :disabled="saving"
              class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-lg px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-base font-medium text-white hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 transform hover:scale-105"
            >
              <i v-if="saving" class="fas fa-spinner fa-spin mr-2"></i>
              {{ saving ? 'Menyimpan...' : (session ? 'Update Sesi' : 'Buat Sesi') }}
            </button>
            <button
              type="button"
              @click="close"
              class="mt-3 w-full inline-flex justify-center rounded-xl border border-white/20 shadow-lg px-6 py-3 bg-slate-600 text-base font-medium text-white hover:bg-slate-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-300"
            >
              Batal
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  session: {
    type: Object,
    default: null
  },
  availableQuizzes: {
    type: Array,
    default: () => []
  },
  availableQuestionnaires: {
    type: Array,
    default: () => []
  },
  courseId: {
    type: [String, Number],
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])
const { showToast } = useToast()

const saving = ref(false)
const form = ref({
  session_number: 1,
  session_title: '',
  session_description: '',
  order_number: 1,
  estimated_duration_minutes: 60,
  quiz_id: '',
  questionnaire_id: '',
  is_required: true
})

// Watch for session changes to populate form
watch(() => props.session, (newSession) => {
  if (newSession) {
    form.value = {
      session_number: newSession.session_number || 1,
      session_title: newSession.session_title || '',
      session_description: newSession.session_description || '',
      order_number: newSession.order_number || 1,
      estimated_duration_minutes: newSession.estimated_duration_minutes || 60,
      quiz_id: newSession.quiz_id || '',
      questionnaire_id: newSession.questionnaire_id || '',
      is_required: newSession.is_required !== false
    }
  } else {
    // Reset form for new session
    form.value = {
      session_number: 1,
      session_title: '',
      session_description: '',
      order_number: 1,
      estimated_duration_minutes: 60,
      quiz_id: '',
      questionnaire_id: '',
      is_required: true
    }
  }
}, { immediate: true })

const saveSession = async () => {
  try {
    saving.value = true

    const url = props.session
      ? `/lms/courses/${props.courseId}/curriculum/sessions/${props.session.id}`
      : `/lms/courses/${props.courseId}/curriculum/sessions`

    const method = props.session ? 'PUT' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(form.value)
    })

    const data = await response.json()

    if (data.success) {
      showToast(
        props.session ? 'Sesi berhasil diperbarui' : 'Sesi berhasil dibuat',
        'success'
      )
      emit('saved', data.curriculum_item)
    } else {
      showToast(data.message || 'Error saving session', 'error')
    }
  } catch (error) {
    console.error('Error saving session:', error)
    showToast('Error saving session', 'error')
  } finally {
    saving.value = false
  }
}

const close = () => {
  emit('close')
}
</script>
