<template>
    <Head title="Employee Survey" />
    
    <AppLayout>
        <div class="max-w-7xl w-full mx-auto py-8 px-2">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-list text-blue-500"></i> Employee Survey
                </h1>
                <Link 
                    :href="route('employee-survey.create')"
                    class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
                >
                    <i class="fas fa-plus mr-2"></i>
                    Buat Survey Baru
                </Link>
            </div>


            <!-- Filters -->
            <div class="mb-6 bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                <div class="flex flex-wrap gap-4 items-center">
                    <!-- Search -->
                    <div class="flex-1 min-w-64">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-search text-gray-400"></i>
                            </div>
                            <input
                                v-model="search"
                                @input="onSearchInput"
                                type="text"
                                placeholder="Cari surveyor, jabatan, divisi, outlet..."
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="min-w-48">
                        <select v-model="status" class="form-input rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                        </select>
                    </div>

                    <!-- Per Page -->
                    <div class="min-w-32">
                        <select v-model="perPage" class="form-input rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="5">5 per halaman</option>
                            <option value="10">10 per halaman</option>
                            <option value="15">15 per halaman</option>
                            <option value="25">25 per halaman</option>
                            <option value="50">50 per halaman</option>
                        </select>
                    </div>
                </div>

                <!-- Date Filters -->
                <div class="mt-4 flex flex-wrap gap-4 items-center">
                    <!-- Date From -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Dari:</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-calendar text-gray-400"></i>
                            </div>
                            <input
                                v-model="dateFrom"
                                @input="onDateInput"
                                type="date"
                                class="pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Date To -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Sampai:</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-calendar text-gray-400"></i>
                            </div>
                            <input
                                v-model="dateTo"
                                @input="onDateInput"
                                type="date"
                                class="pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Quick Date Range Buttons -->
                    <div class="flex gap-2">
                        <button
                            @click="setDateRange('today')"
                            class="px-3 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition-all duration-300 text-sm"
                        >
                            Hari Ini
                        </button>
                        <button
                            @click="setDateRange('week')"
                            class="px-3 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition-all duration-300 text-sm"
                        >
                            Minggu Ini
                        </button>
                        <button
                            @click="setDateRange('month')"
                            class="px-3 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition-all duration-300 text-sm"
                        >
                            Bulan Ini
                        </button>
                        <button
                            @click="clearDateFilters"
                            class="px-3 py-2 bg-gray-500 text-white rounded-lg shadow hover:bg-gray-600 transition-all duration-300 text-sm flex items-center gap-1"
                        >
                            <i class="fas fa-times"></i>
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Survey Cards -->
            <div v-if="surveys.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div 
                    v-for="survey in surveys.data" 
                    :key="survey.id" 
                    class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100"
                >
                    <!-- Card Header -->
                    <div class="p-6 pb-4">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ survey.surveyor_name }}</h3>
                                <p class="text-sm text-gray-600">{{ survey.surveyor_position }}</p>
                            </div>
                            <span :class="[
                                'px-3 py-1 rounded-full text-xs font-semibold',
                                getStatusClass(survey.status)
                            ]">
                                {{ getStatusText(survey.status) }}
                            </span>
                        </div>

                        <!-- User Info with Avatar -->
                        <div class="flex items-center gap-4 mb-4">
                            <div v-if="survey.surveyor?.avatar" class="w-16 h-16 rounded-full overflow-hidden border-3 border-white shadow-xl cursor-pointer hover:shadow-2xl transition-all" @click="openImageModal(getImageUrl(survey.surveyor.avatar))">
                                <img :src="getImageUrl(survey.surveyor.avatar)" :alt="survey.surveyor.nama_lengkap" class="w-full h-full object-cover hover:scale-105 transition-transform" />
                            </div>
                            <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-lg font-bold border-3 border-white shadow-xl">
                                {{ getInitials(survey.surveyor?.nama_lengkap || survey.surveyor_name) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 text-lg">{{ survey.surveyor?.nama_lengkap || survey.surveyor_name }}</p>
                                <p class="text-sm text-gray-500">{{ survey.surveyor_position }}</p>
                                <p class="text-xs text-gray-400">{{ survey.surveyor_division }}</p>
                                <p class="text-xs text-gray-400">{{ survey.surveyor_outlet }}</p>
                            </div>
                        </div>

                        <!-- Survey Score -->
                        <div v-if="survey.status === 'submitted'" class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Survey Score</span>
                                <span class="text-2xl font-bold text-blue-600">{{ getSurveyScore(survey) }}%</span>
                            </div>
                            <!-- Star Rating -->
                            <div class="flex items-center gap-2 mb-2">
                                <div class="flex space-x-1">
                                    <i 
                                        v-for="star in 5" 
                                        :key="star"
                                        :class="[
                                            'fa-star text-sm',
                                            star <= getSurveyStarRating(survey) ? 'fa-solid text-yellow-400' : 'fa-regular text-gray-300'
                                        ]"
                                    ></i>
                                </div>
                                <span class="text-xs text-gray-500">{{ getSurveyStarRating(survey) }}/5</span>
                            </div>
                            <!-- Progress Bar -->
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-500" 
                                    :style="{ width: getSurveyScore(survey) + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Survey Date -->
                        <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
                            <i class="fa-solid fa-calendar"></i>
                            <span>{{ formatDate(survey.survey_date) }}</span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-between">
                            <div class="flex space-x-2">
                                <Link 
                                    :href="route('employee-survey.show', survey.id)"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors"
                                >
                                    <i class="fas fa-eye mr-1"></i>
                                    Lihat
                                </Link>
                                <Link 
                                    v-if="canEditOrDelete(survey)"
                                    :href="route('employee-survey.edit', survey.id)"
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors"
                                >
                                    <i class="fas fa-edit mr-1"></i>
                                    Edit
                                </Link>
                                <button 
                                    v-if="canEditOrDelete(survey)"
                                    @click="deleteSurvey(survey.id)"
                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors"
                                >
                                    <i class="fas fa-trash mr-1"></i>
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12">
                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fa-solid fa-clipboard-list text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada survey</h3>
                <p class="text-gray-500 mb-6">Mulai buat survey karyawan pertama Anda</p>
                <Link 
                    :href="route('employee-survey.create')"
                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                >
                    <i class="fas fa-plus mr-2"></i>
                    Buat Survey Baru
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="surveys.data.length > 0" class="mt-8">
                <nav class="flex items-center justify-between">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <Link 
                            v-if="surveys.prev_page_url"
                            :href="surveys.prev_page_url"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                        >
                            Previous
                        </Link>
                        <Link 
                            v-if="surveys.next_page_url"
                            :href="surveys.next_page_url"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                        >
                            Next
                        </Link>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing {{ surveys.from }} to {{ surveys.to }} of {{ surveys.total }} results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <Link 
                                    v-if="surveys.prev_page_url"
                                    :href="surveys.prev_page_url"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                                >
                                    Previous
                                </Link>
                                <Link 
                                    v-if="surveys.next_page_url"
                                    :href="surveys.next_page_url"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                                >
                                    Next
                                </Link>
                            </nav>
                        </div>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Image Modal -->
        <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeImageModal">
            <div class="relative max-w-4xl max-h-4xl p-4" @click.stop>
                <button @click="closeImageModal" class="absolute top-2 right-2 text-white text-2xl hover:text-gray-300 transition-colors z-10">
                    <i class="fa-solid fa-times"></i>
                </button>
                <img :src="selectedImageUrl" alt="Profile Photo" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, onMounted } from 'vue'
import { debounce } from 'lodash'
import Swal from 'sweetalert2'

const props = defineProps({
    surveys: Object,
    user: Object,
    filters: Object
})

// Search and filter states
const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || 'all')
const perPage = ref(props.filters?.per_page || 10)
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')

// Lightbox functionality
const showImageModal = ref(false)
const selectedImageUrl = ref('')

// Debounced search
const debouncedSearch = debounce(() => {
    router.get('/employee-survey', {
        search: search.value,
        status: status.value,
        per_page: perPage.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }, { preserveState: true, replace: true })
}, 400)

// Watch for changes
watch([search, status, perPage, dateFrom, dateTo], () => {
    debouncedSearch()
})

// Handle flash messages with SweetAlert
onMounted(() => {
    if (props.surveys?.flash?.success) {
        Swal.fire({
            title: 'Berhasil!',
            text: props.surveys.flash.success,
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        })
    }
    
    if (props.surveys?.flash?.error) {
        Swal.fire({
            title: 'Error!',
            text: props.surveys.flash.error,
            icon: 'error'
        })
    }
})

// Filter functions
function onSearchInput() {
    debouncedSearch()
}

function onDateInput() {
    // Validate date range
    if (dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) {
        // Swap dates if from date is after to date
        const temp = dateFrom.value
        dateFrom.value = dateTo.value
        dateTo.value = temp
    }
    debouncedSearch()
}

function clearDateFilters() {
    dateFrom.value = ''
    dateTo.value = ''
    debouncedSearch()
}

function setDateRange(range) {
    const today = new Date()
    const todayStr = today.toISOString().split('T')[0]
    
    switch (range) {
        case 'today':
            dateFrom.value = todayStr
            dateTo.value = todayStr
            break
        case 'week':
            const weekAgo = new Date()
            weekAgo.setDate(weekAgo.getDate() - 7)
            dateFrom.value = weekAgo.toISOString().split('T')[0]
            dateTo.value = todayStr
            break
        case 'month':
            const monthAgo = new Date()
            monthAgo.setDate(monthAgo.getDate() - 30)
            dateFrom.value = monthAgo.toISOString().split('T')[0]
            dateTo.value = todayStr
            break
    }
    debouncedSearch()
}

// Image modal functions
function getImageUrl(avatar) {
    if (!avatar) return ''
    if (avatar.startsWith('http')) return avatar
    return `/storage/${avatar}`
}

function openImageModal(imageUrl) {
    selectedImageUrl.value = imageUrl
    showImageModal.value = true
}

function closeImageModal() {
    showImageModal.value = false
    selectedImageUrl.value = ''
}

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

function canEditOrDelete(survey) {
    // Check if user is the creator or has specific role
    return survey.surveyor_id === props.user.id || props.user.id_role === '5af56935b011a'
}

function getInitials(name) {
    if (!name) return 'U'
    const words = name.split(' ')
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase()
    }
    return words[0][0].toUpperCase()
}

function getSurveyScore(survey) {
    if (!survey.responses || survey.responses.length === 0) return 0
    
    const totalScore = survey.responses.reduce((sum, response) => sum + response.score, 0)
    const maxScore = survey.responses.length * 5
    return Math.round((totalScore / maxScore) * 100)
}

function getSurveyStarRating(survey) {
    const score = getSurveyScore(survey)
    
    if (score >= 80) return 5
    if (score >= 60) return 4
    if (score >= 40) return 3
    if (score >= 20) return 2
    if (score >= 1) return 1
    return 0
}

function deleteSurvey(id) {
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Apakah Anda yakin ingin menghapus survey ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('employee-survey.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Survey berhasil dihapus.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    })
                },
                onError: () => {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Gagal menghapus survey.',
                        icon: 'error'
                    })
                }
            })
        }
    })
}
</script>
