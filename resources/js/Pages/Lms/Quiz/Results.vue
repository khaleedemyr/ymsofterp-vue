<template>
    <AppLayout title="Quiz Results">
        <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-8">
            <div class="max-w-4xl mx-auto px-4">
                <!-- Results Header -->
                <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                            <h1 class="text-3xl font-bold text-slate-800 mb-2">Quiz Selesai!</h1>
                            <p class="text-slate-600">{{ quiz.title }}</p>
                        </div>
                        
                        <!-- Score Display -->
                        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl p-6 text-white mb-6">
                            <div class="text-4xl font-bold mb-2">{{ attempt.score }}%</div>
                            <div class="text-lg opacity-90">Skor Anda</div>
                            <div class="mt-4 text-sm opacity-75">
                                {{ attempt.correct_answers }} dari {{ quiz.questions.length }} pertanyaan benar
                            </div>
                        </div>
                        
                        <!-- Pass/Fail Status -->
                        <div class="mb-6">
                            <div 
                                :class="[
                                    'inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold',
                                    attempt.score >= quiz.passing_score 
                                        ? 'bg-green-100 text-green-800' 
                                        : 'bg-red-100 text-red-800'
                                ]"
                            >
                                <i 
                                    :class="[
                                        'fas mr-2',
                                        attempt.score >= quiz.passing_score ? 'fa-check' : 'fa-times'
                                    ]"
                                ></i>
                                {{ attempt.score >= quiz.passing_score ? 'Lulus' : 'Tidak Lulus' }}
                                (Minimum: {{ quiz.passing_score }}%)
                            </div>
                        </div>
                        
                        <!-- Quiz Info -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-slate-600">
                            <div class="flex items-center justify-center">
                                <i class="fas fa-clock text-slate-500 mr-2"></i>
                                <span>Waktu: {{ formatDuration(attempt.duration_minutes) }}</span>
                            </div>
                            <div class="flex items-center justify-center">
                                <i class="fas fa-calendar text-slate-500 mr-2"></i>
                                <span>Selesai: {{ formatDate(attempt.completed_at) }}</span>
                            </div>
                            <div class="flex items-center justify-center">
                                <i class="fas fa-redo text-slate-500 mr-2"></i>
                                <span>Percobaan #{{ attempt.attempt_number }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Results (if show_results is enabled) -->
                <div v-if="showResults" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-2xl font-bold text-slate-800 mb-6">Detail Jawaban</h2>
                    
                    <div v-for="(question, index) in quiz.questions" :key="question.id" class="mb-8">
                        <div class="border border-slate-200 rounded-lg p-4">
                            <!-- Question Header -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-slate-800 mb-2">
                                        Pertanyaan {{ index + 1 }}
                                    </h3>
                                    <p class="text-slate-700">{{ question.question_text }}</p>
                                </div>
                                <div class="ml-4">
                                    <div 
                                        :class="[
                                            'inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold',
                                            isQuestionCorrect(question.id) 
                                                ? 'bg-green-100 text-green-800' 
                                                : 'bg-red-100 text-red-800'
                                        ]"
                                    >
                                        <i 
                                            :class="[
                                                'fas mr-1',
                                                isQuestionCorrect(question.id) ? 'fa-check' : 'fa-times'
                                            ]"
                                        ></i>
                                        {{ isQuestionCorrect(question.id) ? 'Benar' : 'Salah' }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Options -->
                            <div class="space-y-2">
                                <div 
                                    v-for="option in question.options" 
                                    :key="option.id" 
                                    :class="[
                                        'p-3 rounded-lg border-2 transition-colors',
                                        getOptionClass(question.id, option.id)
                                    ]"
                                >
                                    <div class="flex items-center">
                                        <div 
                                            :class="[
                                                'w-4 h-4 rounded-full mr-3 flex items-center justify-center text-xs font-bold',
                                                getOptionIndicatorClass(question.id, option.id)
                                            ]"
                                        >
                                            {{ getOptionLetter(option.id) }}
                                        </div>
                                        <span class="text-slate-700">{{ option.option_text }}</span>
                                        <div class="ml-auto flex items-center space-x-2">
                                            <span 
                                                v-if="option.is_correct" 
                                                class="text-green-600 text-sm font-semibold"
                                            >
                                                <i class="fas fa-check mr-1"></i>Jawaban Benar
                                            </span>
                                            <span 
                                                v-if="isUserAnswer(question.id, option.id)" 
                                                class="text-blue-600 text-sm font-semibold"
                                            >
                                                <i class="fas fa-user mr-1"></i>Jawaban Anda
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="text-center mt-8">
                    <button 
                        @click="goBack"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors mr-4"
                    >
                        <i class="fas fa-home mr-2"></i>
                        Kembali ke Home
                    </button>
                    <button 
                        v-if="canRetake"
                        @click="retakeQuiz"
                        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                    >
                        <i class="fas fa-redo mr-2"></i>
                        Kembali ke Home
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    attempt: Object,
    quiz: Object,
    showResults: Boolean
})

// Computed properties
const canRetake = computed(() => {
    if (!props.quiz.max_attempts) return true
    return props.attempt.attempt_number < props.quiz.max_attempts
})

// Helper functions
const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const formatDuration = (minutes) => {
    if (!minutes) return 'N/A'
    const hours = Math.floor(minutes / 60)
    const mins = minutes % 60
    if (hours > 0) {
        return `${hours} jam ${mins} menit`
    }
    return `${mins} menit`
}

const isQuestionCorrect = (questionId) => {
    const userAnswer = props.attempt.answers.find(answer => answer.question_id === questionId)
    if (!userAnswer) return false
    
    const correctOption = props.quiz.questions
        .find(q => q.id === questionId)
        ?.options.find(opt => opt.is_correct)
    
    return userAnswer.selected_option_id === correctOption?.id
}

const isUserAnswer = (questionId, optionId) => {
    const userAnswer = props.attempt.answers.find(answer => answer.question_id === questionId)
    return userAnswer?.selected_option_id === optionId
}

const getOptionClass = (questionId, optionId) => {
    const isCorrect = props.quiz.questions
        .find(q => q.id === questionId)
        ?.options.find(opt => opt.id === optionId)?.is_correct
    
    const isUserSelected = isUserAnswer(questionId, optionId)
    
    if (isCorrect && isUserSelected) {
        return 'bg-green-50 border-green-300'
    } else if (isCorrect) {
        return 'bg-green-50 border-green-200'
    } else if (isUserSelected) {
        return 'bg-red-50 border-red-300'
    } else {
        return 'bg-gray-50 border-gray-200'
    }
}

const getOptionIndicatorClass = (questionId, optionId) => {
    const isCorrect = props.quiz.questions
        .find(q => q.id === questionId)
        ?.options.find(opt => opt.id === optionId)?.is_correct
    
    const isUserSelected = isUserAnswer(questionId, optionId)
    
    if (isCorrect && isUserSelected) {
        return 'bg-green-500 text-white'
    } else if (isCorrect) {
        return 'bg-green-200 text-green-800'
    } else if (isUserSelected) {
        return 'bg-red-500 text-white'
    } else {
        return 'bg-gray-200 text-gray-600'
    }
}

const getOptionLetter = (optionId) => {
    // Get the index of this option in the question's options
    const question = props.quiz.questions.find(q => 
        q.options.some(opt => opt.id === optionId)
    )
    if (!question) return '?'
    
    const optionIndex = question.options.findIndex(opt => opt.id === optionId)
    return String.fromCharCode(65 + optionIndex) // A, B, C, D, etc.
}

// Actions
const goBack = () => {
    router.visit(route('home'))
}

const retakeQuiz = () => {
    // Redirect to home page where user can start a new quiz attempt
    router.visit(route('home'))
}
</script>
