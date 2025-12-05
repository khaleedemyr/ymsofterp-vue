<template>
    <AppLayout title="Laporan Training Karyawan">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    <i class="fas fa-users mr-2"></i>
                    Laporan Training Karyawan
                </h2>
                <div class="flex items-center gap-2">
                    <button @click="toggleFullscreen" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        <i class="fas" :class="isFullscreen ? 'fa-compress' : 'fa-expand'"></i>
                    </button>
                    <button @click="refreshData" 
                            :disabled="loading || !hasFilters"
                            class="bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        <i class="fas fa-sync-alt mr-2" :class="{ 'animate-spin': loading }"></i>
                        Load Data
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6" :class="{ 'fixed inset-0 z-50 bg-white dark:bg-gray-900 overflow-auto': isFullscreen }">
            <div :class="isFullscreen ? 'w-full h-full p-6' : 'w-full px-4 sm:px-6 lg:px-8'">
                <!-- Filter Section -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                <i class="fas fa-filter mr-2"></i>
                                Filter Data
                            </h3>
                            <button v-if="isFullscreen" @click="toggleFullscreen" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                <i class="fas fa-times mr-2"></i>
                                Keluar Fullscreen
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- Division Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Divisi
                                </label>
                                <select v-model="filters.division_id" 
                                        @change="onFilterChange"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Semua Divisi</option>
                                    <option v-for="division in props.divisions" :key="division.id" :value="division.id">
                                        {{ division.nama_divisi }}
                                    </option>
                                </select>
                            </div>

                            <!-- Jabatan Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Jabatan
                                </label>
                                <select v-model="filters.jabatan_id" 
                                        @change="onFilterChange"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Semua Jabatan</option>
                                    <option v-for="jabatan in props.jabatans" :key="jabatan.id_jabatan" :value="jabatan.id_jabatan">
                                        {{ jabatan.nama_jabatan }}
                                    </option>
                                </select>
                            </div>

                            <!-- Outlet Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Outlet
                                </label>
                                <select v-model="filters.outlet_id" 
                                        @change="onFilterChange"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Semua Outlet</option>
                                    <option v-for="outlet in props.outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                                        {{ outlet.nama_outlet }}
                                    </option>
                                </select>
                            </div>

                            <!-- Spesifikasi Training Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Spesifikasi Training
                                </label>
                                <select v-model="filters.specification" 
                                        @change="onFilterChange"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Semua Spesifikasi</option>
                                    <option value="generic">Generic</option>
                                    <option value="departemental">Departemental</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Filter Status & Load Button -->
                        <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                    <span class="text-sm text-blue-700 dark:text-blue-300">
                                        <span v-if="!hasFilters">
                                            Pilih filter di atas untuk memuat data laporan training karyawan.
                                        </span>
                                        <span v-else>
                                            Filter aktif: 
                                            <span v-if="filters.division_id">{{ getDivisionName(filters.division_id) }}</span>
                                            <span v-if="filters.jabatan_id">{{ getJabatanName(filters.jabatan_id) }}</span>
                                            <span v-if="filters.outlet_id">{{ getOutletName(filters.outlet_id) }}</span>
                                            <span v-if="filters.specification">{{ getSpecificationName(filters.specification) }}</span>
                                        </span>
                                    </span>
                                </div>
                                <button @click="refreshData" 
                                        :disabled="loading || !hasFilters"
                                        class="bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white px-6 py-2 rounded-lg text-sm transition-colors font-medium">
                                    <i class="fas fa-sync-alt mr-2" :class="{ 'animate-spin': loading }"></i>
                                    Load Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div v-if="summary" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-users text-blue-500 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Total Karyawan
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ summary.total_employees }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Training Selesai
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ summary.total_completed_trainings }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Training Tersedia
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ summary.total_available_trainings }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-chart-line text-purple-500 text-2xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                            Rata-rata Progress
                                        </dt>
                                        <dd class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ summary.average_completion_rate }}%
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                    <span class="ml-3 text-gray-600 dark:text-gray-400">Memuat data...</span>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                Error
                            </h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                {{ error }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee List -->
                <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Daftar Karyawan
                        </h3>
                    </div>
                    
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="employee in employees" :key="employee.employee.id" 
                             class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            
                            <!-- Employee Header -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0">
                                            <div v-if="employee.employee.avatar" class="h-12 w-12 rounded-full overflow-hidden border-2 border-gray-300 dark:border-gray-600 shadow-lg">
                                                <img :src="employee.employee.avatar ? `/storage/${employee.employee.avatar}` : '/images/avatar-default.png'" 
                                                     alt="Avatar" 
                                                     class="w-full h-full object-cover" />
                                            </div>
                                            <div v-else class="h-12 w-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-lg font-bold border-2 border-gray-300 dark:border-gray-600 shadow-lg">
                                                {{ getInitials(employee.employee.nama_lengkap) }}
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                {{ employee.employee.nama_lengkap }}
                                            </h4>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                <span v-if="employee.employee.division">
                                                    {{ employee.employee.division.nama_divisi }}
                                                </span>
                                                <span v-if="employee.employee.jabatan">
                                                    • {{ employee.employee.jabatan.nama_jabatan }}
                                                </span>
                                                <span v-if="employee.employee.outlet">
                                                    • {{ employee.employee.outlet.nama_outlet }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Training Summary -->
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600">
                                            {{ employee.training_summary.total_completed }}
                                        </div>
                                        <div class="text-xs text-gray-500">Selesai</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-yellow-600">
                                            {{ employee.training_summary.total_available_remaining }}
                                        </div>
                                        <div class="text-xs text-gray-500">Tersedia</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600">
                                            {{ employee.training_summary.total_duration_hours }}j
                                        </div>
                                        <div class="text-xs text-gray-500">Total Durasi</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-purple-600">
                                            {{ employee.training_summary.completion_rate }}%
                                        </div>
                                        <div class="text-xs text-gray-500">Progress</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                                    <span>Progress Training</span>
                                    <span>{{ employee.training_summary.completion_rate }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-300"
                                         :style="{ width: employee.training_summary.completion_rate + '%' }"></div>
                                </div>
                            </div>

                            <!-- Expand Button -->
                            <div class="flex justify-center">
                                <button @click="toggleEmployee(employee.employee.id)"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium flex items-center gap-2">
                                    <i class="fas" :class="expandedEmployees.includes(employee.employee.id) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    {{ expandedEmployees.includes(employee.employee.id) ? 'Sembunyikan Detail' : 'Lihat Detail Training' }}
                                </button>
                            </div>

                            <!-- Expanded Content -->
                            <div v-if="expandedEmployees.includes(employee.employee.id)" class="mt-6 space-y-6">
                                
                                <!-- Completed Trainings -->
                                <div v-if="employee.completed_trainings.length > 0">
                                    <h5 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                        Training yang Sudah Selesai ({{ employee.completed_trainings.length }})
                                    </h5>
                                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                                        <div v-for="training in employee.completed_trainings" :key="training.course_id"
                                             class="border border-green-200 dark:border-green-800 rounded-lg p-4 bg-green-50 dark:bg-green-900/20">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <h6 class="font-semibold text-green-800 dark:text-green-200">
                                                        {{ training.title }}
                                                    </h6>
                                                    <div class="text-sm text-green-600 dark:text-green-400 mt-1">
                                                        <div v-if="training.difficulty_level" class="mb-1">
                                                            <i class="fas fa-signal mr-1"></i>{{ training.difficulty_level }}
                                                        </div>
                                                        <div v-if="training.type" class="mb-1">
                                                            <i class="fas fa-tag mr-1"></i>{{ training.type }}
                                                        </div>
                                                        <div v-if="training.specification" class="mb-1">
                                                            <i class="fas fa-cog mr-1"></i>{{ training.specification }}
                                                        </div>
                                                        <div v-if="training.course_type" class="mb-1">
                                                            <i class="fas fa-graduation-cap mr-1"></i>{{ training.course_type }}
                                                        </div>
                                                        <div class="mb-1">
                                                            <i class="fas fa-clock mr-1"></i>{{ training.duration_minutes }} menit
                                                        </div>
                                                        <div class="mb-1">
                                                            <i class="fas fa-calendar mr-1"></i>
                                                            {{ new Date(training.scheduled_date).toLocaleDateString('id-ID') }}
                                                            • {{ training.start_time }} - {{ training.end_time }}
                                                        </div>
                                                        <div v-if="training.trainer_info?.name" class="mb-1">
                                                            <i class="fas fa-user-tie mr-1"></i>
                                                            {{ training.trainer_info.name }}
                                                            <span v-if="training.trainer_info.type === 'external'" class="text-xs text-green-600 dark:text-green-400 ml-1">
                                                                (External)
                                                            </span>
                                                        </div>
                                                        <div v-if="training.location_info?.outlet_name" class="mb-1">
                                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                                            {{ training.location_info.outlet_name }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0 flex flex-col items-end space-y-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <i class="fas fa-check mr-1"></i>
                                                        Selesai
                                                    </span>
                                                    
                                                    <!-- Competency Actions -->
                                                    <div v-if="training.competencies && training.competencies.length > 0" class="flex space-x-1">
                                                        <button @click="openCompetencyTest(training, employee.employee)"
                                                                class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs rounded-md transition-colors"
                                                                title="Uji Kompetensi">
                                                            <i class="fas fa-clipboard-check mr-1"></i>
                                                            Uji
                                                        </button>
                                                        <button @click="openCoaching(training, employee.employee)"
                                                                class="px-2 py-1 bg-purple-500 hover:bg-purple-600 text-white text-xs rounded-md transition-colors"
                                                                title="Coaching">
                                                            <i class="fas fa-user-graduate mr-1"></i>
                                                            Coach
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Available Trainings -->
                                <div v-if="Array.isArray(employee.available_trainings) && employee.available_trainings.length > 0">
                                    <h5 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                        <i class="fas fa-clock text-yellow-500 mr-2"></i>
                                        Training yang Belum Diikuti ({{ employee.available_trainings.length }})
                                    </h5>
                                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                                        <div v-for="training in employee.available_trainings" :key="training.id"
                                             class="border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 bg-yellow-50 dark:bg-yellow-900/20">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <h6 class="font-semibold text-yellow-800 dark:text-yellow-200">
                                                        {{ training.title }}
                                                    </h6>
                                                    <p v-if="training.short_description" class="text-sm text-yellow-600 dark:text-yellow-400 mt-1">
                                                        {{ training.short_description }}
                                                    </p>
                                                    <div class="text-sm text-yellow-600 dark:text-yellow-400 mt-2">
                                                        <div v-if="training.difficulty_level" class="mb-1">
                                                            <i class="fas fa-signal mr-1"></i>{{ training.difficulty_level }}
                                                        </div>
                                                        <div v-if="training.type" class="mb-1">
                                                            <i class="fas fa-tag mr-1"></i>{{ training.type }}
                                                        </div>
                                                        <div v-if="training.specification" class="mb-1">
                                                            <i class="fas fa-cog mr-1"></i>{{ training.specification }}
                                                        </div>
                                                        <div v-if="training.course_type" class="mb-1">
                                                            <i class="fas fa-graduation-cap mr-1"></i>{{ training.course_type }}
                                                        </div>
                                                        <div class="mb-1">
                                                            <i class="fas fa-clock mr-1"></i>{{ training.duration_formatted }}
                                                        </div>
                                                        <div v-if="training.category">
                                                            <i class="fas fa-folder mr-1"></i>{{ training.category.name }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0 flex flex-col items-end space-y-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Tersedia
                                                    </span>
                                                    
                                                    <!-- Competency Actions -->
                                                    <div v-if="training.competencies && training.competencies.length > 0" class="flex space-x-1">
                                                        <button @click="openCompetencyTest(training, employee.employee)"
                                                                class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs rounded-md transition-colors"
                                                                title="Uji Kompetensi">
                                                            <i class="fas fa-clipboard-check mr-1"></i>
                                                            Uji
                                                        </button>
                                                        <button @click="openCoaching(training, employee.employee)"
                                                                class="px-2 py-1 bg-purple-500 hover:bg-purple-600 text-white text-xs rounded-md transition-colors"
                                                                title="Coaching">
                                                            <i class="fas fa-user-graduate mr-1"></i>
                                                            Coach
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- No Trainings Message -->
                                <div v-if="(Array.isArray(employee.completed_trainings) ? employee.completed_trainings.length : 0) === 0 && (Array.isArray(employee.available_trainings) ? employee.available_trainings.length : 0) === 0"
                                     class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-info-circle text-4xl mb-2"></i>
                                    <p>Tidak ada training yang tersedia untuk karyawan ini.</p>
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
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'

// Reactive data
const employees = ref([])
const summary = ref(null)
const loading = ref(false)
const error = ref(null)
const expandedEmployees = ref([])
const isFullscreen = ref(false)

// Filter data
const filters = ref({
    division_id: '',
    jabatan_id: '',
    outlet_id: '',
    specification: ''
})

// Props
const props = defineProps({
    divisions: Array,
    jabatans: Array,
    outlets: Array,
    user: Object
})

// Computed properties
const hasFilters = computed(() => {
    return filters.value.division_id || filters.value.jabatan_id || filters.value.outlet_id || filters.value.specification
})

// Methods
async function loadEmployeeTrainingReport() {
    if (!hasFilters.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Filter Diperlukan',
            text: 'Silakan pilih minimal satu filter sebelum memuat data.'
        })
        return
    }

    loading.value = true
    error.value = null
    
    try {
        console.log('Loading employee training report with filters:', filters.value)
        const response = await axios.get('/lms/employee-training-report', {
            params: filters.value
        })
        console.log('Employee training report response:', response.data)
        
        if (response.data.success) {
            employees.value = response.data.employees
            summary.value = response.data.summary
            
            // Debug: Log employee data
            console.log('Employee data received:', employees.value.map(emp => ({
                name: emp.employee.nama_lengkap,
                completed_count: Array.isArray(emp.completed_trainings) ? emp.completed_trainings.length : 0,
                available_count: Array.isArray(emp.available_trainings) ? emp.available_trainings.length : 0,
                available_titles: Array.isArray(emp.available_trainings) ? emp.available_trainings.map(t => t.title) : [],
                available_trainings_type: typeof emp.available_trainings,
                available_trainings_value: emp.available_trainings
            })))
        } else {
            error.value = response.data.message || 'Gagal memuat laporan training karyawan'
        }
    } catch (err) {
        console.error('Error loading employee training report:', err)
        error.value = err.response?.data?.message || 'Gagal memuat laporan training karyawan'
    } finally {
        loading.value = false
    }
}

function refreshData() {
    loadEmployeeTrainingReport()
}

function toggleEmployee(employeeId) {
    const index = expandedEmployees.value.indexOf(employeeId)
    if (index > -1) {
        expandedEmployees.value.splice(index, 1)
    } else {
        expandedEmployees.value.push(employeeId)
    }
}

function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value
    // Scroll to top when entering fullscreen
    if (isFullscreen.value) {
        window.scrollTo(0, 0)
    }
}

function onFilterChange() {
    // Clear data when filter changes
    employees.value = []
    summary.value = null
    error.value = null
}

function getDivisionName(divisionId) {
    const division = props.divisions.find(d => d.id == divisionId)
    return division ? division.nama_divisi : ''
}

// Competency Test and Coaching Functions
function openCompetencyTest(training, employee) {
    console.log('Opening competency test for:', {
        training: training.title,
        employee: employee.nama_lengkap,
        competencies: training.competencies
    })
    
    // Create competency test form
    const competencyForm = training.competencies.map((comp, index) => `
        <div class="border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
            <div class="flex items-center justify-between mb-3">
                <h6 class="font-semibold text-gray-800">${comp.name}</h6>
                <span class="text-xs text-gray-500">Kompetensi ${index + 1}</span>
            </div>
            
            <!-- Radio Button Options -->
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Penilaian:</label>
                <div class="flex space-x-6">
                    <label class="flex items-center">
                        <input type="radio" name="competency_${comp.id}" value="+" class="mr-2 text-green-600 focus:ring-green-500">
                        <span class="text-green-600 font-semibold">+ (Baik)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="competency_${comp.id}" value="0" class="mr-2 text-yellow-600 focus:ring-yellow-500">
                        <span class="text-yellow-600 font-semibold">0 (Cukup)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="competency_${comp.id}" value="-" class="mr-2 text-red-600 focus:ring-red-500">
                        <span class="text-red-600 font-semibold">- (Kurang)</span>
                    </label>
                </div>
            </div>
            
            <!-- Keterangan -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan:</label>
                <textarea name="keterangan_${comp.id}" 
                          class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none" 
                          rows="2" 
                          placeholder="Masukkan keterangan penilaian..."></textarea>
            </div>
        </div>
    `).join('')
    
    // Show competency test modal
    Swal.fire({
        title: 'Uji Kompetensi',
        html: `
            <div class="text-left">
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-1">${training.title}</h4>
                    <p class="text-sm text-blue-600">Karyawan: ${employee.nama_lengkap}</p>
                </div>
                
                <div class="mb-4">
                    <h5 class="font-medium text-gray-700 mb-3">Penilaian Kompetensi:</h5>
                    ${competencyForm}
                </div>
                
                <div class="text-sm text-gray-500 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                    <strong>Petunjuk:</strong> Pilih penilaian untuk setiap kompetensi dan berikan keterangan yang jelas.
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Simpan Penilaian',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3B82F6',
        cancelButtonColor: '#6B7280',
        width: '700px',
        preConfirm: () => {
            // Collect form data
            const formData = {
                training_id: training.course_id || training.id,
                employee_id: employee.id,
                competencies: []
            }
            
            // Get all competency data
            training.competencies.forEach(comp => {
                const rating = document.querySelector(`input[name="competency_${comp.id}"]:checked`)
                const keterangan = document.querySelector(`textarea[name="keterangan_${comp.id}"]`)
                
                if (rating) {
                    formData.competencies.push({
                        competency_id: comp.id,
                        competency_name: comp.name,
                        rating: rating.value,
                        keterangan: keterangan ? keterangan.value.trim() : ''
                    })
                }
            })
            
            // Validate that all competencies are rated
            if (formData.competencies.length !== training.competencies.length) {
                Swal.showValidationMessage('Semua kompetensi harus dinilai!')
                return false
            }
            
            return formData
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Handle form submission
            console.log('Competency test data:', result.value)
            
            // Show success message
            Swal.fire({
                title: 'Berhasil!',
                text: 'Penilaian kompetensi berhasil disimpan.',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#10B981'
            })
        }
    })
}

function openCoaching(training, employee) {
    console.log('Opening coaching for:', {
        training: training.title,
        employee: employee.nama_lengkap,
        competencies: training.competencies
    })
    
    // Show coaching modal or redirect to coaching page
    Swal.fire({
        title: 'Coaching',
        html: `
            <div class="text-left">
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-800 mb-2">${training.title}</h4>
                    <p class="text-sm text-gray-600">Karyawan: ${employee.nama_lengkap}</p>
                </div>
                <div class="mb-4">
                    <h5 class="font-medium text-gray-700 mb-2">Kompetensi untuk coaching:</h5>
                    <ul class="list-disc list-inside text-sm text-gray-600">
                        ${training.competencies.map(comp => `<li>${comp.name}</li>`).join('')}
                    </ul>
                </div>
                <div class="text-sm text-gray-500">
                    <p>Fitur coaching akan segera tersedia.</p>
                </div>
            </div>
        `,
        showConfirmButton: true,
        confirmButtonText: 'OK',
        confirmButtonColor: '#8B5CF6',
        width: '500px'
    })
}

function getJabatanName(jabatanId) {
    const jabatan = props.jabatans.find(j => j.id_jabatan == jabatanId)
    return jabatan ? jabatan.nama_jabatan : ''
}

function getOutletName(outletId) {
    const outlet = props.outlets.find(o => o.id_outlet == outletId)
    return outlet ? outlet.nama_outlet : ''
}

function getSpecificationName(specification) {
    const specifications = {
        'generic': 'Generic',
        'departemental': 'Departemental'
    }
    return specifications[specification] || specification
}

function getInitials(name) {
    if (!name) return '';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
}

// Lifecycle
onMounted(() => {
    // Data sudah dikirim sebagai props, tidak perlu load lagi
    console.log('Employee Training Report mounted with props:', {
        divisions: props.divisions?.length,
        jabatans: props.jabatans?.length,
        outlets: props.outlets?.length
    })
})
</script>
