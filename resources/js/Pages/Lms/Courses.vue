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
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  Training & Development
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Program pelatihan internal untuk pengembangan kompetensi karyawan
                </p>
              </div>
              <div class="flex items-center space-x-4">
                <div class="text-center">
                  <div class="text-6xl font-bold text-white drop-shadow-lg animate-pulse">
                    {{ courses.length }}
                  </div>
                  <div class="text-lg text-white/90 drop-shadow-md">Program Training</div>
                </div>
                <button
                  @click="showCreateModal = true"
                  class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Tambah Course
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl mb-8 p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="relative">
              <input 
                v-model="filters.search" 
                type="text" 
                placeholder="Cari program training..."
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/50 backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
              <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50"></i>
            </div>

            <!-- Category Filter -->
            <select 
              v-model="filters.category" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Kategori</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.name }}
              </option>
            </select>

            <!-- Difficulty Filter -->
            <select 
              v-model="filters.difficulty" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Level</option>
              <option value="beginner">Pemula</option>
              <option value="intermediate">Menengah</option>
              <option value="advanced">Lanjutan</option>
            </select>

            <!-- Division Filter -->
            <select 
              v-model="filters.division" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Divisi</option>
              <option v-for="division in divisions" :key="division.id" :value="division.id">
                {{ division.nama_divisi }}
              </option>
            </select>
          </div>
        </div>

        <!-- Courses Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <div v-for="course in filteredCourses" :key="course.id" 
               class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl transform hover:scale-105 hover:rotate-1 transition-all duration-500 cursor-pointer group">
            
            <!-- Course Image -->
            <div class="relative h-48 rounded-t-2xl overflow-hidden">
              <div class="w-full h-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                <i class="fas fa-graduation-cap text-6xl text-white/50 group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              <div class="absolute top-4 right-4">
                <span class="px-3 py-1 text-xs rounded-full font-semibold backdrop-blur-sm bg-green-500/20 text-green-200 border border-green-500/30">
                  Gratis
                </span>
              </div>
              <div class="absolute bottom-4 left-4">
                <span :class="{
                  'px-3 py-1 text-xs rounded-full font-semibold backdrop-blur-sm': true,
                  'bg-blue-500/20 text-blue-200 border border-blue-500/30': course.difficulty === 'beginner',
                  'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30': course.difficulty === 'intermediate',
                  'bg-red-500/20 text-red-200 border border-red-500/30': course.difficulty === 'advanced'
                }">
                  {{ course.difficulty_text }}
                </span>
              </div>
            </div>

            <!-- Course Content -->
            <div class="p-6">
              <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-white/60">{{ course.category?.name }}</span>
                <div class="flex items-center space-x-1">
                  <i class="fas fa-star text-yellow-400 text-sm"></i>
                  <span class="text-sm text-white/80">4.5</span>
                </div>
              </div>

              <h3 class="text-xl font-bold text-white drop-shadow-md mb-2 group-hover:text-blue-300 transition-colors duration-300">
                {{ course.title }}
              </h3>

              <p class="text-white/70 text-sm mb-4 line-clamp-2">
                {{ course.description }}
              </p>

              <div class="flex items-center justify-between text-sm text-white/60 mb-4">
                <span><i class="fas fa-clock mr-1"></i>{{ course.duration_formatted }}</span>
                <span><i class="fas fa-users mr-1"></i>{{ course.enrollments_count }} peserta</span>
              </div>

              <!-- Target Division Display -->
              <div class="mb-4">
                <div class="flex items-center text-sm text-white/60 mb-2">
                  <i class="fas fa-bullseye mr-2"></i>
                  <span>Target:</span>
                </div>
                <div class="flex flex-wrap gap-1">
                  <span v-if="course.target_type === 'all'" 
                        class="px-2 py-1 text-xs rounded-full bg-blue-500/20 text-blue-200 border border-blue-500/30">
                    Semua Divisi
                  </span>
                  <span v-else-if="course.target_type === 'single' && course.target_division" 
                        class="px-2 py-1 text-xs rounded-full bg-green-500/20 text-green-200 border border-green-500/30">
                    {{ course.target_division.nama_divisi }}
                  </span>
                  <span v-else-if="course.target_type === 'multiple' && course.target_divisions" 
                        class="px-2 py-1 text-xs rounded-full bg-purple-500/20 text-purple-200 border border-purple-500/30">
                    {{ course.target_divisions.length }} Divisi
                  </span>
                  <span v-else 
                        class="px-2 py-1 text-xs rounded-full bg-gray-500/20 text-gray-200 border border-gray-500/30">
                    Divisi tidak ditentukan
                  </span>
                </div>
              </div>

              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                  <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                    <i class="fas fa-user text-white text-sm"></i>
                  </div>
                  <span class="text-sm text-white/80">{{ course.instructor_name }}</span>
                </div>
                <Link :href="route('lms.courses.show', course.id)" 
                      class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
                  Lihat Detail
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="filteredCourses.length === 0" class="text-center py-16">
          <div class="w-32 h-32 mx-auto mb-8 bg-white/10 rounded-full flex items-center justify-center">
            <i class="fas fa-search text-6xl text-white/50"></i>
          </div>
          <h3 class="text-2xl font-bold text-white mb-4">Tidak ada program training ditemukan</h3>
          <p class="text-white/70 text-lg mb-8">Coba ubah filter pencarian Anda atau buat program training baru</p>
          <div class="flex justify-center space-x-4">
            <button @click="clearFilters" 
                    class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
              Reset Filter
            </button>
            <button @click="showCreateModal = true"
                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-semibold hover:from-green-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
              <i class="fas fa-plus mr-2"></i>
              Buat Course Pertama
            </button>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="filteredCourses.length > 0" class="mt-8 flex justify-center">
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-4">
            <div class="flex items-center space-x-2">
              <button 
                @click="currentPage--" 
                :disabled="currentPage === 1"
                class="px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
              >
                <i class="fas fa-chevron-left"></i>
              </button>
              
              <span class="px-4 py-2 text-white font-semibold">
                Halaman {{ currentPage }} dari {{ totalPages }}
              </span>
              
              <button 
                @click="currentPage++" 
                :disabled="currentPage === totalPages"
                class="px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
              >
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Course Modal -->
      <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="modal bg-white/10 backdrop-blur-md rounded-xl p-6 w-full max-w-2xl mx-4 border border-white/20 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-semibold text-white">Tambah Course Baru</h3>
            <button @click="closeModal" class="text-white/70 hover:text-white">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
          
          <form @submit.prevent="createCourse">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Basic Information -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Judul Course <span class="text-red-400">*</span></label>
                  <input
                    v-model="form.title"
                    type="text"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Masukkan judul course"
                    :disabled="loading"
                  />
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi Singkat</label>
                  <textarea
                    v-model="form.short_description"
                    rows="3"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Deskripsi singkat course (opsional)"
                    :disabled="loading"
                  ></textarea>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi Lengkap</label>
                  <textarea
                    v-model="form.description"
                    rows="4"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Deskripsi lengkap course"
                    :disabled="loading"
                  ></textarea>
                </div>
              </div>
              
              <!-- Course Settings -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Kategori <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.category_id"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Kategori</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                      {{ category.name }}
                    </option>
                  </select>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Target Divisi <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.target_type"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent mb-3"
                    :disabled="loading"
                  >
                    <option value="">Pilih Tipe Target</option>
                    <option value="single">1 Divisi</option>
                    <option value="multiple">Multi Divisi</option>
                    <option value="all">Semua Divisi</option>
                  </select>
                  
                  <!-- Single Division Selection -->
                  <select
                    v-if="form.target_type === 'single'"
                    v-model="form.target_division_id"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Divisi</option>
                    <option v-for="division in divisions" :key="division.id" :value="division.id">
                      {{ division.nama_divisi }}
                    </option>
                  </select>
                  
                  <!-- Multiple Divisions Selection -->
                  <div v-if="form.target_type === 'multiple'" class="space-y-2">
                    <div class="max-h-32 overflow-y-auto bg-white/5 rounded-lg p-3 border border-white/10">
                      <div v-for="division in divisions" :key="division.id" class="flex items-center mb-2">
                        <input
                          type="checkbox"
                          :id="'division-' + division.id"
                          :value="division.id"
                          v-model="form.target_divisions"
                          class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-2"
                        >
                        <label :for="'division-' + division.id" class="ml-2 text-sm text-white/80 cursor-pointer">
                          {{ division.nama_divisi }}
                        </label>
                      </div>
                    </div>
                    <p class="text-xs text-white/60">Pilih satu atau lebih divisi yang ditarget</p>
                  </div>
                  
                  <!-- All Divisions Info -->
                  <div v-if="form.target_type === 'all'" class="px-4 py-3 bg-blue-500/20 border border-blue-500/30 rounded-lg">
                    <p class="text-sm text-blue-200">
                      <i class="fas fa-info-circle mr-2"></i>
                      Course ini akan tersedia untuk semua divisi
                    </p>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Level Kesulitan <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.difficulty_level"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Level</option>
                    <option value="beginner">Pemula</option>
                    <option value="intermediate">Menengah</option>
                    <option value="advanced">Lanjutan</option>
                  </select>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Durasi (menit) <span class="text-red-400">*</span></label>
                  <input
                    v-model="form.duration_minutes"
                    type="number"
                    required
                    min="1"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Durasi dalam menit"
                    :disabled="loading"
                  />
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Status <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.status"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                  </select>
                </div>
              </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
              <button
                type="button"
                @click="closeModal"
                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg transition-colors duration-200"
                :disabled="loading"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="loading || !isFormValid"
                class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed relative overflow-hidden"
              >
                <span v-if="loading" class="flex items-center justify-center">
                  <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                  Menyimpan...
                </span>
                <span v-else class="flex items-center justify-center">
                  <i class="fas fa-save mr-2"></i>
                  Simpan Course
                </span>
                <!-- Loading overlay -->
                <div v-if="loading" class="absolute inset-0 bg-green-600/20 backdrop-blur-sm"></div>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  courses: Array,
  categories: Array,
  divisions: Array,
})

// Reactive filters
const filters = ref({
  search: '',
  category: '',
  difficulty: '',
  division: ''
})

const currentPage = ref(1)
const itemsPerPage = 12

// Modal state
const showCreateModal = ref(false)
const loading = ref(false)

// Form data
const form = ref({
  title: '',
  short_description: '',
  description: '',
  category_id: '',
  target_type: '', // 'single', 'multiple', 'all'
  target_division_id: '', // For single division
  target_divisions: [], // For multiple divisions
  difficulty_level: '',
  duration_minutes: '',
  status: 'draft'
})

// Computed properties
const filteredCourses = computed(() => {
  let filtered = props.courses

  // Search filter
  if (filters.value.search) {
    filtered = filtered.filter(course => 
      course.title.toLowerCase().includes(filters.value.search.toLowerCase()) ||
      course.description.toLowerCase().includes(filters.value.search.toLowerCase())
    )
  }

  // Category filter
  if (filters.value.category) {
    filtered = filtered.filter(course => course.category_id == filters.value.category)
  }

  // Difficulty filter
  if (filters.value.difficulty) {
    filtered = filtered.filter(course => course.difficulty_level === filters.value.difficulty)
  }

  // Division filter - updated to support new target division structure
  if (filters.value.division) {
    filtered = filtered.filter(course => {
      if (course.target_type === 'all') {
        return true // All divisions can access
      } else if (course.target_type === 'single') {
        return course.target_division_id == filters.value.division
      } else if (course.target_type === 'multiple') {
        return course.target_divisions && course.target_divisions.includes(parseInt(filters.value.division))
      }
      return false
    })
  }

  return filtered
})

const paginatedCourses = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredCourses.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredCourses.value.length / itemsPerPage)
})

// Form validation
const isFormValid = computed(() => {
  const basicValidation = form.value.title.trim() && 
                         form.value.category_id && 
                         form.value.difficulty_level && 
                         form.value.duration_minutes && 
                         form.value.target_type

  if (!basicValidation) return false

  // Target division validation
  if (form.value.target_type === 'single' && !form.value.target_division_id) {
    return false
  }

  if (form.value.target_type === 'multiple' && (!form.value.target_divisions || form.value.target_divisions.length === 0)) {
    return false
  }

  return true
})

// Methods
const clearFilters = () => {
  filters.value = {
    search: '',
    category: '',
    difficulty: '',
    division: ''
  }
  currentPage.value = 1
}

const closeModal = () => {
  showCreateModal.value = false
  form.value = {
    title: '',
    short_description: '',
    description: '',
    category_id: '',
    target_type: '', // 'single', 'multiple', 'all'
    target_division_id: '', // For single division
    target_divisions: [], // For multiple divisions
    difficulty_level: '',
    duration_minutes: '',
    status: 'draft'
  }
}

const createCourse = async () => {
  // Validation
  if (!form.value.title.trim() || !form.value.category_id || !form.value.difficulty_level || !form.value.duration_minutes || !form.value.target_type) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon lengkapi semua field yang wajib diisi!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  // Additional validation for target divisions
  if (form.value.target_type === 'single' && !form.value.target_division_id) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon pilih divisi target!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  if (form.value.target_type === 'multiple' && (!form.value.target_divisions || form.value.target_divisions.length === 0)) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon pilih minimal satu divisi target!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  // Confirmation dialog
  const result = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin menyimpan course ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10B981',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Simpan!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  })

  if (!result.isConfirmed) {
    return
  }

  // Show loading state
  loading.value = true
  
  // Show loading animation
  Swal.fire({
    title: 'Menyimpan Course...',
    html: `
      <div class="flex flex-col items-center space-y-4">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-500"></div>
        <p class="text-gray-600">Mohon tunggu sebentar...</p>
      </div>
    `,
    showConfirmButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false
  })
  
  try {
    await router.post('/lms/courses', form.value, {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Course berhasil dibuat dan tersimpan!',
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: false,
          toast: true,
          position: 'top-end',
          background: '#10B981',
          color: '#ffffff'
        })
        closeModal()
        // Reload the page to show new course
        router.reload()
      },
      onError: (errors) => {
        const errorMessage = Object.values(errors).flat().join(', ')
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: errorMessage || 'Terjadi kesalahan saat membuat course',
          confirmButtonColor: '#EF4444',
          background: '#FEF2F2',
          color: '#DC2626'
        })
      }
    })
  } catch (error) {
    console.error('Error creating course:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat membuat course',
      confirmButtonColor: '#EF4444',
      background: '#FEF2F2',
      color: '#DC2626'
    })
  } finally {
    loading.value = false
  }
}

// Watch for filter changes to reset pagination
watch(filters, () => {
  currentPage.value = 1
}, { deep: true })
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

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}
</style>

<style>
/* Global dropdown styling for dark theme */
select {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.5rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
}

select option {
  background-color: rgba(15, 23, 42, 0.95) !important;
  color: white !important;
  border: none;
  padding: 8px 12px;
}

select option:hover {
  background-color: rgba(59, 130, 246, 0.5) !important;
}

select option:checked {
  background-color: rgba(59, 130, 246, 0.8) !important;
}

/* Modal specific styling */
.modal select {
  background-color: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  color: white !important;
}

.modal select:focus {
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important;
  border-color: transparent !important;
}

.modal input,
.modal textarea {
  background-color: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  color: white !important;
}

.modal input::placeholder,
.modal textarea::placeholder {
  color: rgba(156, 163, 175, 0.8) !important;
}

.modal input:focus,
.modal textarea:focus {
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important;
  border-color: transparent !important;
}

/* Override any conflicting styles */
.bg-white\/10 {
  background-color: rgba(255, 255, 255, 0.1) !important;
}

.border-white\/20 {
  border-color: rgba(255, 255, 255, 0.2) !important;
}

.text-white {
  color: white !important;
}

.placeholder-gray-400::placeholder {
  color: rgba(156, 163, 175, 0.8) !important;
}

/* Additional dropdown styling for better appearance */
select:disabled {
  opacity: 0.5 !important;
  cursor: not-allowed !important;
}

/* Ensure dropdown arrow is visible */
select {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") !important;
  background-position: right 0.5rem center !important;
  background-repeat: no-repeat !important;
  background-size: 1.5em 1.5em !important;
  padding-right: 2.5rem !important;
}

/* Firefox specific styling */
select:-moz-focusring {
  color: transparent !important;
  text-shadow: 0 0 0 white !important;
}

/* IE specific styling */
select::-ms-expand {
  display: none !important;
}
</style> 