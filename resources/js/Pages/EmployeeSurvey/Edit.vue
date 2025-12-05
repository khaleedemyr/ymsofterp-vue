<template>
    <Head title="Edit Employee Survey" />
    
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Edit Employee Survey</h2>
                                <p class="text-gray-600">Edit survey karyawan</p>
                            </div>
                            <Link 
                                :href="route('employee-survey.index')"
                                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                            >
                                <i class="fas fa-arrow-left mr-2"></i>
                                Kembali
                            </Link>
                        </div>


                        <form @submit.prevent="submitForm">
                            <!-- Surveyor Information -->
                            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                <h3 class="text-lg font-semibold mb-4">Informasi Surveyor</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Nama</label>
                                        <input 
                                            type="text" 
                                            :value="survey.surveyor_name" 
                                            disabled
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-100"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Jabatan</label>
                                        <input 
                                            type="text" 
                                            :value="survey.surveyor_position" 
                                            disabled
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-100"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Divisi</label>
                                        <input 
                                            type="text" 
                                            :value="survey.surveyor_division" 
                                            disabled
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-100"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Outlet</label>
                                        <input 
                                            type="text" 
                                            :value="survey.surveyor_outlet" 
                                            disabled
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-100"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Survey Date -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700">Tanggal Survey</label>
                                <input 
                                    type="date" 
                                    v-model="form.survey_date"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                            </div>

                            <!-- Survey Questions -->
                            <div class="space-y-8">
                                <div v-for="(questions, category) in surveyQuestions" :key="category" class="border rounded-lg p-6">
                                    <h3 class="text-lg font-semibold mb-4 text-blue-600">{{ category }}</h3>
                                    
                                    <div class="space-y-4">
                                        <div v-for="(question, index) in questions" :key="index" class="border-l-4 border-blue-200 pl-4">
                                            <div class="mb-2">
                                                <p class="text-sm font-medium text-gray-900">{{ question }}</p>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Score</label>
                                                <div class="flex space-x-4">
                                                    <label v-for="score in [1, 2, 3, 4, 5]" :key="score" class="flex items-center">
                                                        <input 
                                                            type="radio" 
                                                            :name="`score_${category}_${index}`"
                                                            :value="score"
                                                            v-model="form.responses[getResponseKey(category, index)].score"
                                                            class="mr-2"
                                                            required
                                                        >
                                                        <span class="text-sm">{{ score }}</span>
                                                    </label>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    1 = Sangat Tidak Setuju, 2 = Tidak Setuju, 3 = Netral, 4 = Setuju, 5 = Sangat Setuju
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Komentar (Opsional)</label>
                                                <textarea 
                                                    v-model="form.responses[getResponseKey(category, index)].comment"
                                                    rows="2"
                                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Masukkan komentar..."
                                                ></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="mt-8 flex justify-end space-x-4">
                                <Link 
                                    :href="route('employee-survey.index')"
                                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                >
                                    Batal
                                </Link>
                                <button 
                                    type="submit"
                                    :disabled="isSubmitting"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                                >
                                    <i v-if="isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
                                    {{ isSubmitting ? 'Menyimpan...' : 'Update Survey' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive, onMounted } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
    survey: Object,
    surveyQuestions: Object
})

const isSubmitting = ref(false)

// Initialize responses with existing data
const initializeResponses = () => {
    const responses = {}
    
    // Initialize all questions
    Object.keys(props.surveyQuestions).forEach(category => {
        props.surveyQuestions[category].forEach((question, index) => {
            const key = getResponseKey(category, index)
            responses[key] = {
                question_category: category,
                question_text: question,
                score: null,
                comment: ''
            }
        })
    })
    
    // Fill with existing responses
    props.survey.responses.forEach(response => {
        const key = getResponseKey(response.question_category, 
            props.surveyQuestions[response.question_category].indexOf(response.question_text))
        if (responses[key]) {
            responses[key].score = response.score
            responses[key].comment = response.comment || ''
        }
    })
    
    return responses
}

// Initialize form with responses
const form = useForm({
    survey_date: props.survey.survey_date,
    responses: initializeResponses()
})

function getResponseKey(category, index) {
    return `${category}_${index}`
}

function submitForm() {
    isSubmitting.value = true
    
    // Convert responses object to array
    const responsesArray = Object.values(form.responses).filter(response => response.score !== null)
    
    form.transform((data) => ({
        ...data,
        responses: responsesArray
    })).put(route('employee-survey.update', props.survey.id), {
        onSuccess: () => {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Survey berhasil diperbarui.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Redirect will be handled by Inertia
            })
        },
        onError: (errors) => {
            let errorMessage = 'Terjadi kesalahan saat memperbarui survey.'
            if (errors.responses) {
                errorMessage = 'Pastikan semua pertanyaan telah dijawab.'
            }
            Swal.fire({
                title: 'Error!',
                text: errorMessage,
                icon: 'error'
            })
        },
        onFinish: () => {
            isSubmitting.value = false
        }
    })
}
</script>
