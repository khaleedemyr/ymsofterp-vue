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
                <div class="flex items-center space-x-4">
                  <button @click="$router.go(-1)" class="text-white/80 hover:text-white transition-colors">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                  </button>
                  <div>
                    <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                      Kurikulum Training
                    </h1>
                    <p class="text-xl text-white/90 drop-shadow-md">
                      {{ course?.title || courseId }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="flex items-center space-x-4">
                <div class="text-center">
                  <div class="text-6xl font-bold text-white drop-shadow-lg animate-pulse">
                    {{ curriculum.length }}
                  </div>
                  <div class="text-lg text-white/90 drop-shadow-md">Sesi Training</div>
                </div>
                <button
                  @click="showAddSessionModal = true"
                  class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Tambah Sesi
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto">
          <!-- Loading State -->
          <div v-if="loading" class="flex justify-center items-center py-16">
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-white"></div>
          </div>

          <!-- Empty State -->
          <div v-else-if="curriculum.length === 0" class="text-center py-16">
            <div class="w-32 h-32 mx-auto mb-8 bg-white/10 rounded-full flex items-center justify-center backdrop-blur-xl border border-white/20">
              <i class="fas fa-book-open text-6xl text-white/50"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Belum ada sesi training tersedia</h3>
            <p class="text-white/70 text-lg mb-8">Mulai dengan menambahkan sesi pertama untuk course ini</p>
            <div class="flex justify-center">
              <button
                @click="showAddSessionModal = true"
                class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-8 py-4 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
              >
                <i class="fas fa-plus mr-2"></i>
                Tambah Sesi Pertama
              </button>
            </div>
          </div>

          <!-- Curriculum List -->
          <div v-else class="space-y-6">
            <div
              v-for="(session, index) in curriculum"
              :key="session.id"
              class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl overflow-hidden"
            >
              <!-- Session Header -->
              <div class="px-8 py-6 bg-gradient-to-r from-blue-600/20 via-purple-600/20 to-pink-600/20 border-b border-white/20">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                      <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center shadow-lg">
                        <span class="text-lg font-bold text-white">{{ session.session_number }}</span>
                      </div>
                    </div>
                    <div>
                      <h3 class="text-2xl font-bold text-white drop-shadow-lg">{{ session.session_title }}</h3>
                      <p v-if="session.session_description" class="text-lg text-white/80 mt-2 drop-shadow-md">
                        {{ session.session_description }}
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center space-x-4">
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-green-500/20 text-green-200 border border-green-500/30">
                      <i class="fas fa-clock mr-2"></i>
                      {{ session.total_duration_minutes }} menit
                    </span>
                    <button
                      @click="editSession(session)"
                      class="text-white/80 hover:text-white transition-colors p-2 rounded-lg hover:bg-white/10"
                      title="Edit Sesi"
                    >
                      <i class="fas fa-edit text-xl"></i>
                    </button>
                    <button
                      @click="deleteSession(session.id)"
                      class="text-white/80 hover:text-red-300 transition-colors p-2 rounded-lg hover:bg-red-500/10"
                      title="Hapus Sesi"
                    >
                      <i class="fas fa-trash text-xl"></i>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Session Content -->
              <div class="px-8 py-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <!-- Quiz Section -->
                  <div class="backdrop-blur-xl bg-white/5 border border-white/20 rounded-xl p-6 hover:bg-white/10 transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                      <h4 class="text-lg font-semibold text-white">Quiz</h4>
                      <button
                        @click="selectQuiz(session)"
                        class="text-blue-300 hover:text-blue-200 text-sm font-medium px-3 py-1 rounded-lg hover:bg-blue-500/20 transition-all duration-300"
                      >
                        {{ session.quiz ? 'Ganti' : 'Pilih' }}
                      </button>
                    </div>
                    <div v-if="session.quiz" class="space-y-3">
                      <div class="text-base font-medium text-white">{{ session.quiz.title }}</div>
                      <div class="text-sm text-white/70">{{ session.quiz.description }}</div>
                      <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-200 border border-blue-500/30">
                          Quiz
                        </span>
                        <span class="text-xs text-white/60">{{ session.quiz.questions_count || 0 }} pertanyaan</span>
                      </div>
                    </div>
                    <div v-else class="text-sm text-white/60 italic">Belum ada quiz dipilih</div>
                  </div>

                  <!-- Materials Section -->
                  <div class="backdrop-blur-xl bg-white/5 border border-white/20 rounded-xl p-6 hover:bg-white/10 transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                      <h4 class="text-lg font-semibold text-white">Materi ({{ session.materials.length }})</h4>
                      <button
                        @click="openAddMaterialModal(session)"
                        class="text-green-300 hover:text-green-200 text-sm font-medium px-3 py-1 rounded-lg hover:bg-green-500/20 transition-all duration-300"
                      >
                        Tambah
                      </button>
                    </div>
                    <div v-if="session.materials.length > 0" class="space-y-3">
                      <div
                        v-for="material in session.materials.slice(0, 2)"
                        :key="material.id"
                        class="flex items-center space-x-3 text-sm"
                      >
                        <div class="w-5 h-5">
                          <MaterialIcon :type="material.material_type" />
                        </div>
                        <span class="text-white truncate">{{ material.title }}</span>
                      </div>
                      <div v-if="session.materials.length > 2" class="text-xs text-white/60">
                        +{{ session.materials.length - 2 }} materi lainnya
                      </div>
                    </div>
                    <div v-else class="text-sm text-white/60 italic">Belum ada materi</div>
                  </div>

                  <!-- Questionnaire Section -->
                  <div class="backdrop-blur-xl bg-white/5 border border-white/20 rounded-xl p-6 hover:bg-white/10 transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                      <h4 class="text-lg font-semibold text-white">Kuesioner</h4>
                      <button
                        @click="selectQuestionnaire(session)"
                        class="text-purple-300 hover:text-purple-200 text-sm font-medium px-3 py-1 rounded-lg hover:bg-purple-500/20 transition-all duration-300"
                      >
                        {{ session.questionnaire ? 'Ganti' : 'Pilih' }}
                      </button>
                    </div>
                    <div v-if="session.questionnaire" class="space-y-3">
                      <div class="text-base font-medium text-white">{{ session.questionnaire.title }}</div>
                      <div class="text-sm text-white/70">{{ session.questionnaire.description }}</div>
                      <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-500/20 text-purple-200 border border-purple-500/30">
                          Kuesioner
                        </span>
                        <span class="text-xs text-white/60">{{ session.questionnaire.questions_count || 0 }} pertanyaan</span>
                      </div>
                    </div>
                    <div v-else class="text-sm text-white/60 italic">Belum ada kuesioner dipilih</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Session Modal -->
    <SessionModal
      v-if="showAddSessionModal"
      :show="showAddSessionModal"
      :session="editingSession"
      :available-quizzes="availableQuizzes"
      :available-questionnaires="availableQuestionnaires"
      :course-id="courseId"
      @close="closeSessionModal"
      @saved="onSessionSaved"
    />

    <!-- Add Material Modal -->
    <MaterialModal
      v-if="showAddMaterialModal"
      :show="showAddMaterialModal"
      :curriculum-item="selectedSession"
      :course-id="courseId"
      @close="closeMaterialModal"
      @saved="onMaterialSaved"
    />

    <!-- Quiz Selection Modal -->
    <QuizSelectionModal
      v-if="showQuizSelectionModal"
      :show="showQuizSelectionModal"
      :available-quizzes="availableQuizzes"
      :selected-quiz="selectedSession?.quiz"
      @close="showQuizSelectionModal = false"
      @selected="onQuizSelected"
    />

    <!-- Questionnaire Selection Modal -->
    <QuestionnaireSelectionModal
      v-if="showQuestionnaireSelectionModal"
      :show="showQuestionnaireSelectionModal"
      :available-questionnaires="availableQuestionnaires"
      :selected-questionnaire="selectedSession?.questionnaire"
      @close="showQuestionnaireSelectionModal = false"
      @selected="onQuestionnaireSelected"
    />
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from '@/Composables/useToast'
import AppLayout from '@/Layouts/AppLayout.vue'
import SessionModal from './Components/SessionModal.vue'
import MaterialModal from './Components/MaterialModal.vue'
import QuizSelectionModal from './Components/QuizSelectionModal.vue'
import QuestionnaireSelectionModal from './Components/QuestionnaireSelectionModal.vue'
import MaterialIcon from './Components/MaterialIcon.vue'

const { showToast } = useToast()

// Props & Data
const props = defineProps({
  course: {
    type: [String, Number],
    required: true
  }
})

const courseId = typeof props.course === 'object' ? props.course.id : props.course
const loading = ref(true)
const curriculum = ref([])
const availableQuizzes = ref([])
const availableQuestionnaires = ref([])
const course = ref(typeof props.course === 'object' ? props.course : null)

// Modal states
const showAddSessionModal = ref(false)
const showAddMaterialModal = ref(false)
const showQuizSelectionModal = ref(false)
const showQuestionnaireSelectionModal = ref(false)
const editingSession = ref(null)
const selectedSession = ref(null)

// Methods
const loadCurriculum = async () => {
  try {
    loading.value = true
    console.log('Loading curriculum for course:', courseId)
    console.log('Course ID type:', typeof courseId)
    console.log('Course ID value:', courseId)
    
    const url = `/lms/courses/${courseId}/curriculum`
    console.log('Fetching from URL:', url)
    
    const response = await fetch(url)
    console.log('Response status:', response.status)
    console.log('Response headers:', response.headers)
    
    if (!response.ok) {
      const errorText = await response.text()
      console.error('Response not OK. Status:', response.status)
      console.error('Error response:', errorText)
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    console.log('Curriculum data received:', data)
    
    if (data.success) {
      curriculum.value = data.curriculum || []
      availableQuizzes.value = data.availableQuizzes || []
      availableQuestionnaires.value = data.availableQuestionnaires || []
      
      console.log('Curriculum items loaded:', curriculum.value.length)
      console.log('Available quizzes loaded:', availableQuizzes.value.length)
      console.log('Available questionnaires loaded:', availableQuestionnaires.value.length)
      
      if (availableQuizzes.value.length > 0) {
        console.log('First quiz:', availableQuizzes.value[0])
      }
      if (availableQuestionnaires.value.length > 0) {
        console.log('First questionnaire:', availableQuestionnaires.value[0])
      }
    } else {
      console.error('API returned success: false')
      console.error('API message:', data.message)
      showToast('Error loading curriculum', 'error')
    }
  } catch (error) {
    console.error('Error loading curriculum:', error)
    showToast('Error loading curriculum', 'error')
  } finally {
    loading.value = false
  }
}

const loadCourse = async () => {
  try {
    const response = await fetch(`/lms/courses/${courseId}`)
    const data = await response.json()
    course.value = data.course
  } catch (error) {
    console.error('Error loading course:', error)
  }
}

const editSession = (session) => {
  editingSession.value = { ...session }
  showAddSessionModal.value = true
}

const deleteSession = async (sessionId) => {
  if (!confirm('Apakah Anda yakin ingin menghapus sesi ini?')) return

  try {
    const response = await fetch(`/lms/courses/${courseId}/curriculum/sessions/${sessionId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })

    const data = await response.json()
    
    if (data.success) {
      showToast('Sesi berhasil dihapus', 'success')
      await loadCurriculum()
    } else {
      showToast('Error deleting session', 'error')
    }
  } catch (error) {
    console.error('Error deleting session:', error)
    showToast('Error deleting session', 'error')
  }
}

const openAddMaterialModal = (session) => {
  selectedSession.value = session
  showAddMaterialModal.value = true
}

const selectQuiz = (session) => {
  selectedSession.value = session
  showQuizSelectionModal.value = true
}

const selectQuestionnaire = (session) => {
  selectedSession.value = session
  showQuestionnaireSelectionModal.value = true
}

const closeSessionModal = () => {
  showAddSessionModal.value = false
  editingSession.value = null
}

const closeMaterialModal = () => {
  showAddMaterialModal.value = false
  selectedSession.value = null
}

const onSessionSaved = async () => {
  await loadCurriculum()
  closeSessionModal()
}

const onMaterialSaved = async () => {
  await loadCurriculum()
  closeMaterialModal()
}

const onQuizSelected = async (quiz) => {
  console.log('=== QUIZ SELECTION STARTED ===');
  console.log('Selected quiz:', quiz);
  console.log('Selected session:', selectedSession.value);
  
  if (!selectedSession.value) {
    console.error('No session selected');
    showToast('No session selected', 'error');
    return;
  }

  try {
    console.log('Preparing request data...');
    const requestData = {
      ...selectedSession.value,
      quiz_id: quiz.id
    };
    console.log('Request data:', requestData);
    
    const url = `/lms/courses/${courseId}/curriculum/sessions/${selectedSession.value.id}`;
    console.log('Request URL:', url);
    console.log('Request method: PUT');
    
    const response = await fetch(url, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(requestData)
    });

    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Response not OK. Status:', response.status);
      console.error('Error response:', errorText);
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    console.log('Response data:', data);
    
    if (data.success) {
      console.log('Quiz selection successful');
      showToast('Quiz berhasil dipilih', 'success');
      await loadCurriculum();
      showQuizSelectionModal.value = false;
    } else {
      console.error('API returned success: false');
      console.error('API message:', data.message);
      showToast('Error selecting quiz: ' + (data.message || 'Unknown error'), 'error');
    }
  } catch (error) {
    console.error('Error selecting quiz:', error);
    showToast('Error selecting quiz: ' + error.message, 'error');
  }
}

const onQuestionnaireSelected = async (questionnaire) => {
  console.log('=== QUESTIONNAIRE SELECTION STARTED ===');
  console.log('Selected questionnaire:', questionnaire);
  console.log('Selected session:', selectedSession.value);
  
  if (!selectedSession.value) {
    console.error('No session selected');
    showToast('No session selected', 'error');
    return;
  }

  try {
    console.log('Preparing request data...');
    const requestData = {
      ...selectedSession.value,
      questionnaire_id: questionnaire.id
    };
    console.log('Request data:', requestData);
    
    const url = `/lms/courses/${courseId}/curriculum/sessions/${selectedSession.value.id}`;
    console.log('Request URL:', url);
    console.log('Request method: PUT');
    
    const response = await fetch(url, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(requestData)
    });

    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Response not OK. Status:', response.status);
      console.error('Error response:', errorText);
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    console.log('Response data:', data);
    
    if (data.success) {
      console.log('Questionnaire selection successful');
      showToast('Kuesioner berhasil dipilih', 'success');
      await loadCurriculum();
      showQuestionnaireSelectionModal.value = false;
    } else {
      console.error('API returned success: false');
      console.error('API message:', data.message);
      showToast('Error selecting questionnaire: ' + (data.message || 'Unknown error'), 'error');
    }
  } catch (error) {
    console.error('Error selecting questionnaire:', error);
    showToast('Error selecting questionnaire: ' + error.message, 'error');
  }
}

// Lifecycle
onMounted(async () => {
  await loadCurriculum()
})
</script>

<style scoped>
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

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}
</style>
