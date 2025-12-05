<template>
  <AppLayout title="Archived Courses">
    <!-- Hero Section with Background -->
    <div class="relative min-h-screen bg-gradient-to-br from-orange-50 via-red-50 to-pink-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
      <!-- Animated Background Elements -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-orange-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-red-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute top-40 left-40 w-80 h-80 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
      </div>

      <div class="relative z-10 py-8 px-6">
        <!-- Header Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl shadow-2xl mb-8">
          <div class="p-8 bg-gradient-to-r from-orange-600/80 via-red-600/80 to-pink-600/80 rounded-3xl">
            <div class="flex items-center justify-between">
              <div class="space-y-4">
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  <i class="fas fa-archive mr-3"></i>
                  Archived Courses
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Course yang telah diarchive dan dapat dipublish kembali
                </p>
              </div>
              <div class="flex items-center space-x-4">
                <div class="text-center">
                  <div class="text-6xl font-bold text-white drop-shadow-lg animate-pulse">
                    {{ courses.length }}
                  </div>
                  <div class="text-lg text-white/90 drop-shadow-md">Course Archived</div>
                </div>
                <Link 
                  :href="route('lms.courses.index')"
                  class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                >
                  <i class="fas fa-arrow-left mr-2"></i>
                  Back to Active
                </Link>
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
                placeholder="Cari course archived..."
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/50 backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
              >
              <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50"></i>
            </div>

            <!-- Category Filter -->
            <select 
              v-model="filters.category" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
            >
              <option value="">Semua Kategori</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.name }}
              </option>
            </select>

            <!-- Difficulty Filter -->
            <select 
              v-model="filters.difficulty" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
            >
              <option value="">Semua Level</option>
              <option value="beginner">Pemula</option>
              <option value="intermediate">Menengah</option>
              <option value="advanced">Lanjutan</option>
            </select>

            <!-- Division Filter -->
            <select 
              v-model="filters.division" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
            >
              <option value="">Semua Divisi</option>
              <option v-for="division in divisions" :key="division.id" :value="division.id">
                {{ division.nama_divisi }}
              </option>
            </select>
          </div>
        </div>

        <!-- Courses Grid -->
        <div v-if="filteredCourses.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <div v-for="course in paginatedCourses" :key="course.id" 
               class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl transform hover:scale-105 hover:rotate-1 transition-all duration-500 cursor-pointer group">
            
            <!-- Course Image -->
            <div class="relative h-48 rounded-t-2xl overflow-hidden">
              <img 
                v-if="course.thumbnail_url" 
                :src="course.thumbnail_url" 
                :alt="course.title" 
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
              />
              <div 
                v-else
                class="w-full h-full bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center"
              >
                <i class="fas fa-archive text-6xl text-white/50 group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              
              <!-- Difficulty Badge -->
              <div class="absolute bottom-4 left-4">
                <span :class="{
                  'px-3 py-1 text-xs rounded-full font-semibold backdrop-blur-sm': true,
                  'bg-blue-500/20 text-blue-200 border border-blue-500/30': course.difficulty_level === 'beginner',
                  'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30': course.difficulty_level === 'intermediate',
                  'bg-red-500/20 text-red-200 border border-red-500/30': course.difficulty_level === 'advanced'
                }">
                  {{ course.difficulty_text }}
                </span>
              </div>

              <!-- Status Badge -->
              <div class="absolute bottom-4 right-4">
                <span :class="{
                  'px-3 py-1 text-xs rounded-full font-semibold backdrop-blur-sm': true,
                  'bg-green-500/20 text-green-200 border border-green-500/30': course.status === 'published',
                  'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30': course.status === 'draft',
                  'bg-red-500/20 text-red-200 border border-red-500/30': course.status === 'archived'
                }">
                  <i :class="{
                    'fas fa-check-circle mr-1': course.status === 'published',
                    'fas fa-edit mr-1': course.status === 'draft',
                    'fas fa-archive mr-1': course.status === 'archived'
                  }"></i>
                  {{ course.status === 'published' ? 'Published' : course.status === 'draft' ? 'Draft' : 'Archived' }}
                </span>
              </div>
              
              <!-- Action Buttons (Edit & Publish) -->
              <div v-if="canManageCourse" class="absolute top-4 right-4 flex space-x-2">
                <button 
                  @click.stop="editCourse(course.id)"
                  class="w-8 h-8 bg-blue-500/80 backdrop-blur-sm border border-blue-400/30 rounded-full text-white hover:bg-blue-600/80 transition-all duration-300 transform hover:scale-105 flex items-center justify-center"
                  title="Edit Course"
                >
                  <i class="fas fa-edit text-xs"></i>
                </button>
                <button 
                  @click.stop="publishCourse(course.id)"
                  class="w-8 h-8 bg-green-500/80 backdrop-blur-sm border border-green-400/30 rounded-full text-white hover:bg-green-600/80 transition-all duration-300 transform hover:scale-105 flex items-center justify-center"
                  title="Publish Course"
                >
                  <i class="fas fa-upload text-xs"></i>
                </button>
              </div>
            </div>

            <!-- Course Content -->
            <div class="p-6">
              <!-- Category Badge -->
              <div class="mb-3">
                <span class="px-3 py-1 text-xs rounded-full bg-orange-500/20 text-orange-200 border border-orange-500/30 backdrop-blur-sm">
                  {{ course.category?.name || 'Uncategorized' }}
                </span>
              </div>

              <!-- Title -->
              <h3 class="text-lg font-semibold text-white mb-2 line-clamp-2 group-hover:text-orange-300 transition-colors drop-shadow-lg">
                {{ course.title }}
              </h3>

              <!-- Description -->
              <p class="text-white/80 text-sm mb-4 line-clamp-2 drop-shadow-md">
                {{ course.description }}
              </p>

              <!-- Course Info -->
              <div class="space-y-2 mb-4">
                <!-- Duration -->
                <div class="flex items-center text-sm text-white/70">
                  <i class="fas fa-clock mr-2 text-orange-400"></i>
                  <span>{{ course.duration_formatted }}</span>
                </div>

                <!-- Instructor -->
                <div class="flex items-center text-sm text-white/70">
                  <i class="fas fa-user mr-2 text-green-400"></i>
                  <span>{{ course.instructor_name }}</span>
                </div>

                <!-- Target Information Display -->
                <div class="flex items-center text-sm text-white/70">
                  <i class="fas fa-users mr-2 text-purple-400"></i>
                  <div class="flex flex-wrap gap-1">
                    <span v-if="course.target_type === 'all'" 
                          class="px-2 py-1 text-xs rounded-full bg-purple-500/20 text-purple-200 border border-purple-500/30 backdrop-blur-sm">
                      Semua Divisi
                    </span>
                    <span v-else-if="course.target_type === 'single' && course.target_division" 
                          class="px-2 py-1 text-xs rounded-full bg-purple-500/20 text-purple-200 border border-purple-500/30 backdrop-blur-sm">
                      {{ course.target_division.nama_divisi }}
                    </span>
                    <span v-else-if="course.target_type === 'multiple'" 
                          class="px-2 py-1 text-xs rounded-full bg-purple-500/20 text-purple-200 border border-purple-500/30 backdrop-blur-sm">
                      {{ course.target_division_name }}
                    </span>
                  </div>
                </div>

                <!-- Jabatan Targets -->
                <div v-if="course.target_jabatan_names && course.target_jabatan_names !== 'Jabatan tidak ditentukan'" class="flex items-center text-sm text-white/70">
                  <i class="fas fa-id-badge mr-2 text-indigo-400"></i>
                  <span class="px-2 py-1 text-xs rounded-full bg-indigo-500/20 text-indigo-200 border border-indigo-500/30 backdrop-blur-sm">
                    {{ course.target_jabatan_names }}
                  </span>
                </div>

                <!-- Level Targets -->
                <div v-if="course.target_outlet_names && course.target_outlet_names !== 'Outlet tidak ditentukan'" class="flex items-center text-sm text-white/70">
                  <i class="fas fa-store mr-2 text-teal-400"></i>
                  <span class="px-2 py-1 text-xs rounded-full bg-teal-500/20 text-teal-200 border border-teal-500/30 backdrop-blur-sm">
                    {{ course.target_outlet_names }}
                  </span>
                </div>
              </div>

              <!-- Archived Badge -->
              <div class="flex items-center justify-between">
                <span class="px-3 py-1 text-xs rounded-full bg-red-500/20 text-red-200 border border-red-500/30 backdrop-blur-sm font-semibold">
                  <i class="fas fa-archive mr-1"></i>
                  Archived
                </span>
                <span class="text-xs text-white/60">
                  {{ formatDate(course.updated_at) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
          <div class="max-w-md mx-auto">
            <div class="w-24 h-24 mx-auto mb-6 bg-orange-500/20 rounded-full flex items-center justify-center backdrop-blur-sm border border-orange-500/30">
              <i class="fas fa-archive text-3xl text-orange-400"></i>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2 drop-shadow-lg">
              Tidak Ada Course Archived
            </h3>
            <p class="text-white/80 mb-6 drop-shadow-md">
              Tidak ada course archived yang sesuai dengan filter saat ini.
            </p>
            <button
              @click="clearFilters"
              class="px-6 py-3 bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 text-white rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
            >
              Clear Filters
            </button>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="mt-8 flex justify-center">
          <nav class="flex items-center space-x-2 backdrop-blur-xl bg-white/10 border border-white/20 rounded-xl p-2">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-4 py-2 text-sm font-medium text-white bg-white/10 border border-white/20 rounded-lg hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
            >
              Previous
            </button>
            
            <span class="px-4 py-2 text-sm text-white font-semibold">
              Page {{ currentPage }} of {{ totalPages }}
            </span>
            
            <button
              @click="currentPage = Math.min(totalPages, currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="px-4 py-2 text-sm font-medium text-white bg-white/10 border border-white/20 rounded-lg hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
            >
              Next
            </button>
          </nav>
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
  jabatans: Array,
  levels: Array,
  internalTrainers: Array,
  user: Object,
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

// Computed properties
const canManageCourse = computed(() => {
  if (!props.user) return false
  
  // Check if user has role 5af56935b011a and status A
  if (props.user.id_role === '5af56935b011a' && props.user.status === 'A') {
    return true
  }
  
  // Check if user has jabatan 170 and status A
  if (props.user.id_jabatan === 170 && props.user.status === 'A') {
    return true
  }
  
  return false
})

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

  // Division filter
  if (filters.value.division) {
    filtered = filtered.filter(course => {
      if (course.target_type === 'all') {
        return true
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

const totalPages = computed(() => Math.ceil(filteredCourses.value.length / itemsPerPage))

const paginatedCourses = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredCourses.value.slice(start, end)
})

// Methods
const viewCourse = (courseId) => {
  router.visit(route('lms.courses.show', courseId))
}

const editCourse = (courseId) => {
  router.get(route('lms.courses.edit', courseId))
}

const publishCourse = async (courseId) => {
  const result = await Swal.fire({
    title: 'Konfirmasi Publish',
    text: 'Apakah Anda yakin ingin mempublish kembali course ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10B981',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Publish!',
    cancelButtonText: 'Batal'
  })

  if (result.isConfirmed) {
    try {
      await router.put(route('lms.courses.publish', courseId), {}, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Course berhasil dipublish kembali!',
            confirmButtonColor: '#10B981'
          })
        },
        onError: (errors) => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Gagal mempublish course. Silakan coba lagi.',
            confirmButtonColor: '#EF4444'
          })
        }
      })
    } catch (error) {
      console.error('Error publishing course:', error)
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Terjadi kesalahan. Silakan coba lagi.',
        confirmButtonColor: '#EF4444'
      })
    }
  }
}

const clearFilters = () => {
  filters.value = {
    search: '',
    category: '',
    difficulty: '',
    division: ''
  }
  currentPage.value = 1
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Watch for filter changes to reset pagination
watch(filters, () => {
  currentPage.value = 1
}, { deep: true })
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

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
