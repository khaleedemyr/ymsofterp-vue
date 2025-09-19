<template>
    <AppLayout title="Quiz Attempt">
        <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-8">
            <div class="max-w-4xl mx-auto px-4">
                <!-- Quiz Header -->
                <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800">{{ quiz.title }}</h1>
                            <p class="text-slate-600 mt-1">{{ quiz.description }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-slate-500">Attempt #{{ attempt.attempt_number }}</div>
                            <div class="text-sm text-slate-500">{{ formatTime(startTime) }}</div>
                        </div>
                    </div>
                    
                    <!-- Quiz Instructions -->
                    <div v-if="quiz.instructions" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h3 class="font-semibold text-blue-800 mb-2">Instruksi:</h3>
                        <p class="text-blue-700 text-sm">{{ quiz.instructions }}</p>
                    </div>
                    
                    <!-- Quiz Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-clock text-slate-500 mr-2"></i>
                            <span class="text-slate-600">
                                <span v-if="quiz.time_limit_type === 'total'">
                                    Waktu: {{ quiz.time_limit_minutes }} menit
                                </span>
                                <span v-else-if="quiz.time_limit_type === 'per_question'">
                                    {{ quiz.time_per_question_seconds }} detik/pertanyaan
                                </span>
                                <span v-else>Tidak ada batas waktu</span>
                            </span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-target text-slate-500 mr-2"></i>
                            <span class="text-slate-600">Nilai minimum: {{ quiz.passing_score }}%</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-redo text-slate-500 mr-2"></i>
                            <span class="text-slate-600">
                                <span v-if="quiz.max_attempts">Maksimal {{ quiz.max_attempts }} percobaan</span>
                                <span v-else>Percobaan tidak terbatas</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Quiz Questions -->
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <form @submit.prevent="submitQuiz">
                        <!-- Timer Display -->
                        <div v-if="isPerQuestionTimer" class="mb-6">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-slate-600">
                                    Pertanyaan {{ currentQuestionIndex + 1 }} dari {{ quiz.questions.length }}
                                </div>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-clock text-slate-500"></i>
                                    <span 
                                        :class="[
                                            'text-lg font-bold',
                                            timeLeft <= 10 ? 'text-red-600' : 'text-slate-800'
                                        ]"
                                    >
                                        {{ formatTimer(timeLeft) }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="isTimeUp" class="mt-2 text-center text-red-600 font-semibold">
                                Waktu habis! Pindah ke pertanyaan berikutnya...
                            </div>
                        </div>

                        <!-- Current Question -->
                        <div v-if="currentQuestion" class="mb-8">
                            <div class="border-b border-slate-200 pb-4 mb-4">
                                <div class="flex items-start justify-between mb-3">
                                    <h3 class="text-lg font-semibold text-slate-800">
                                        Pertanyaan {{ currentQuestionIndex + 1 }}
                                    </h3>
                                    <span class="text-sm text-slate-500">{{ currentQuestion.points }} poin</span>
                                </div>
                                <p class="text-slate-700 mb-4">{{ currentQuestion.question_text }}</p>
                            </div>
                            
                            <!-- Question Options -->
                            <div class="space-y-3">
                                <div v-for="option in currentQuestion.options" :key="option.id" class="flex items-center">
                                    <input 
                                        :id="`question_${currentQuestion.id}_option_${option.id}`"
                                        :name="`question_${currentQuestion.id}`"
                                        :value="option.id"
                                        v-model="answers[currentQuestion.id]"
                                        type="radio"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 focus:ring-2"
                                    />
                                    <label 
                                        :for="`question_${currentQuestion.id}_option_${option.id}`"
                                        class="ml-3 text-sm text-slate-700 cursor-pointer"
                                    >
                                        {{ option.option_text }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Navigation and Submit -->
                        <div class="flex justify-between items-center pt-6 border-t border-slate-200">
                            <div class="flex items-center space-x-4">
                                <!-- Only show navigation buttons if not per-question timer -->
                                <template v-if="!isPerQuestionTimer">
                                    <button 
                                        type="button"
                                        @click="previousQuestion"
                                        :disabled="currentQuestionIndex === 0"
                                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Sebelumnya
                                    </button>
                                    
                                    <button 
                                        v-if="currentQuestionIndex < quiz.questions.length - 1"
                                        type="button"
                                        @click="nextQuestion"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        Selanjutnya
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </button>
                                </template>
                                
                                <!-- For per-question timer, only show next button if not last question -->
                                <button 
                                    v-if="isPerQuestionTimer && currentQuestionIndex < quiz.questions.length - 1"
                                    type="button"
                                    @click="nextQuestion"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                >
                                    Selanjutnya
                                    <i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <div class="text-sm text-slate-500">
                                    {{ Object.keys(answers).length }} dari {{ quiz.questions.length }} pertanyaan dijawab
                                </div>
                                <button 
                                    type="submit"
                                    :disabled="isSubmitting"
                                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i v-if="isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
                                    <i v-else class="fas fa-check mr-2"></i>
                                    {{ isSubmitting ? 'Mengirim...' : 'Selesai Quiz' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
    attempt: Object,
    quiz: Object
})

const answers = ref({})
const isSubmitting = ref(false)
const startTime = ref(new Date())
const timer = ref(null)
const currentQuestionIndex = ref(0)
const questionTimer = ref(null)
const timeLeft = ref(0)
const isTimeUp = ref(false)

// Computed properties
const currentQuestion = computed(() => {
    return props.quiz.questions[currentQuestionIndex.value]
})

const isPerQuestionTimer = computed(() => {
    return props.quiz.time_limit_type === 'per_question'
})

const timePerQuestion = computed(() => {
    return props.quiz.time_per_question_seconds || 10
})

// Format time display
const formatTime = (time) => {
    return time.toLocaleTimeString('id-ID')
}

// Format timer display
const formatTimer = (seconds) => {
    const mins = Math.floor(seconds / 60)
    const secs = seconds % 60
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

// Start question timer
const startQuestionTimer = () => {
    if (!isPerQuestionTimer.value) return
    
    timeLeft.value = timePerQuestion.value
    isTimeUp.value = false
    
    questionTimer.value = setInterval(() => {
        timeLeft.value--
        
        if (timeLeft.value <= 0) {
            isTimeUp.value = true
            clearInterval(questionTimer.value)
            
            // Auto move to next question or submit if last question
            setTimeout(() => {
                if (currentQuestionIndex.value < props.quiz.questions.length - 1) {
                    nextQuestion()
                } else {
                    submitQuiz()
                }
            }, 1000)
        }
    }, 1000)
}

// Stop question timer
const stopQuestionTimer = () => {
    if (questionTimer.value) {
        clearInterval(questionTimer.value)
        questionTimer.value = null
    }
}

// Next question
const nextQuestion = () => {
    if (currentQuestionIndex.value < props.quiz.questions.length - 1) {
        stopQuestionTimer()
        currentQuestionIndex.value++
        startQuestionTimer()
    }
}

// Previous question
const previousQuestion = () => {
    if (currentQuestionIndex.value > 0) {
        stopQuestionTimer()
        currentQuestionIndex.value--
        startQuestionTimer()
    }
}

// Submit quiz
const submitQuiz = async () => {
    if (isSubmitting.value) return
    
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
    
    isSubmitting.value = true
    
    try {
        console.log('Submitting quiz with answers:', answers.value)
        console.log('Attempt ID:', props.attempt.id)
        
        const response = await axios.post('/api/quiz/submit-attempt', {
            attempt_id: props.attempt.id,
            answers: answers.value
        })
        
        console.log('Submit response:', response.data)
        
        if (response.data.result) {
            console.log('Quiz submitted successfully, redirecting to results...')
            Swal.close()
            // Redirect to results page
            router.visit(`/lms/quiz/results/${props.attempt.id}`)
        } else {
            console.error('No result in response:', response.data)
            Swal.close()
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat mengirim quiz'
            })
        }
    } catch (error) {
        console.error('Error submitting quiz:', error)
        console.error('Error response:', error.response?.data)
        Swal.close()
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Terjadi kesalahan saat mengirim quiz'
        })
    } finally {
        isSubmitting.value = false
    }
}

// Load existing answers if any
onMounted(() => {
    // Load any existing answers from the attempt
    if (props.attempt.answers) {
        props.attempt.answers.forEach(answer => {
            answers.value[answer.question_id] = answer.selected_option_id
        })
    }
    
    // Start question timer if per-question timer is enabled
    if (isPerQuestionTimer.value) {
        startQuestionTimer()
    }
})

// Cleanup timer on unmount
onUnmounted(() => {
    if (timer.value) {
        clearInterval(timer.value)
    }
    if (questionTimer.value) {
        clearInterval(questionTimer.value)
    }
})
</script>
