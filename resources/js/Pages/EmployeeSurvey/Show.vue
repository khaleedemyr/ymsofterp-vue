<template>
    <Head title="Detail Employee Survey" />
    
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Detail Employee Survey</h2>
                                <p class="text-gray-600">Lihat detail survey karyawan</p>
                            </div>
                            <div class="flex space-x-2">
                                <Link 
                                    :href="route('employee-survey.edit', survey.id)"
                                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
                                >
                                    <i class="fas fa-edit mr-2"></i>
                                    Edit
                                </Link>
                                <Link 
                                    :href="route('employee-survey.index')"
                                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                >
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Kembali
                                </Link>
                            </div>
                        </div>

                        <!-- Surveyor Information -->
                        <div class="bg-gray-50 p-4 rounded-lg mb-6">
                            <h3 class="text-lg font-semibold mb-4">Informasi Surveyor</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nama</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ survey.surveyor_name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Jabatan</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ survey.surveyor_position }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Divisi</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ survey.surveyor_division }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Outlet</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ survey.surveyor_outlet }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tanggal Survey</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ formatDate(survey.survey_date) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <span :class="getStatusClass(survey.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ getStatusText(survey.status) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Survey Responses -->
                        <div class="space-y-6">
                            <div v-for="category in groupedResponses" :key="category.name" class="border rounded-lg p-6">
                                <h3 class="text-lg font-semibold mb-4 text-blue-600">{{ category.name }}</h3>
                                
                                <div class="space-y-4">
                                    <div v-for="response in category.responses" :key="response.id" class="border-l-4 border-blue-200 pl-4">
                                        <div class="mb-2">
                                            <p class="text-sm font-medium text-gray-900">{{ response.question_text }}</p>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getScoreClass(response.score)">
                                                Score: {{ response.score }} - {{ getScoreText(response.score) }}
                                            </span>
                                        </div>
                                        
                                        <div v-if="response.comment" class="mt-2">
                                            <label class="block text-sm font-medium text-gray-700">Komentar</label>
                                            <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded">{{ response.comment }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="mt-8 bg-blue-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4 text-blue-600">Ringkasan Survey</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">{{ averageScore.toFixed(2) }}</div>
                                    <div class="text-sm text-gray-600">Rata-rata Score</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">{{ totalResponses }}</div>
                                    <div class="text-sm text-gray-600">Total Pertanyaan</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-purple-600">{{ getScorePercentage(averageScore) }}%</div>
                                    <div class="text-sm text-gray-600">Tingkat Kepuasan</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

const props = defineProps({
    survey: Object
})

const groupedResponses = computed(() => {
    const groups = {}
    props.survey.responses.forEach(response => {
        if (!groups[response.question_category]) {
            groups[response.question_category] = {
                name: response.question_category,
                responses: []
            }
        }
        groups[response.question_category].responses.push(response)
    })
    return Object.values(groups)
})

const totalResponses = computed(() => {
    return props.survey.responses.length
})

const averageScore = computed(() => {
    if (props.survey.responses.length === 0) return 0
    const total = props.survey.responses.reduce((sum, response) => sum + response.score, 0)
    return total / props.survey.responses.length
})

function formatDate(date) {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    })
}

function getStatusClass(status) {
    switch (status) {
        case 'draft':
            return 'bg-yellow-100 text-yellow-800'
        case 'submitted':
            return 'bg-green-100 text-green-800'
        default:
            return 'bg-gray-100 text-gray-800'
    }
}

function getStatusText(status) {
    switch (status) {
        case 'draft':
            return 'Draft'
        case 'submitted':
            return 'Submitted'
        default:
            return 'Unknown'
    }
}

function getScoreClass(score) {
    if (score >= 4) return 'bg-green-100 text-green-800'
    if (score >= 3) return 'bg-yellow-100 text-yellow-800'
    return 'bg-red-100 text-red-800'
}

function getScoreText(score) {
    switch (score) {
        case 1: return 'Sangat Tidak Setuju'
        case 2: return 'Tidak Setuju'
        case 3: return 'Netral'
        case 4: return 'Setuju'
        case 5: return 'Sangat Setuju'
        default: return 'Unknown'
    }
}

function getScorePercentage(score) {
    return Math.round((score / 5) * 100)
}
</script>
