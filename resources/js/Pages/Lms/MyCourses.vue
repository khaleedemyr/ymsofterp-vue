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
                  Program Training Saya
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Kelola dan pantau progress training Anda
                </p>
              </div>
              <div class="text-center">
                <div class="text-6xl font-bold text-white drop-shadow-lg animate-pulse">
                  {{ enrollments.total }}
                </div>
                <div class="text-lg text-white/90 drop-shadow-md">Program Terdaftar</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl mb-8 p-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

            <!-- Status Filter -->
            <select 
              v-model="filters.status" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Status</option>
              <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                {{ status.label }}
              </option>
            </select>

            <!-- Sort By -->
            <select 
              v-model="filters.sort" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="updated_at">Terbaru</option>
              <option value="progress_percentage">Progress Tertinggi</option>
              <option value="course.title">Nama Program</option>
              <option value="created_at">Tanggal Daftar</option>
            </select>
          </div>
        </div>

        <!-- Enrollments Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
          <div v-for="enrollment in enrollments.data" :key="enrollment.id" 
               class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl transform hover:scale-105 hover:rotate-1 transition-all duration-500">
            
            <!-- Course Header -->
            <div class="relative h-48 rounded-t-2xl overflow-hidden">
              <img 
                v-if="enrollment.course.thumbnail_url" 
                :src="enrollment.course.thumbnail_url" 
                :alt="enrollment.course.title" 
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
              />
              <div 
                v-else
                class="w-full h-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center"
              >
                <i class="fas fa-graduation-cap text-6xl text-white/50 group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              <div class="absolute top-4 right-4">
                <span :class="{
                  'px-3 py-1 text-xs rounded-full font-semibold backdrop-blur-sm': true,
                  'bg-blue-500/20 text-blue-200 border border-blue-500/30': enrollment.status === 'enrolled',
                  'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30': enrollment.status === 'in_progress',
                  'bg-green-500/20 text-green-200 border border-green-500/30': enrollment.status === 'completed',
                  'bg-red-500/20 text-red-200 border border-red-500/30': enrollment.status === 'dropped'
                }">
                  {{ getStatusText(enrollment.status) }}
                </span>
              </div>
              <div class="absolute bottom-4 left-4">
                <span class="px-3 py-1 text-xs rounded-full font-semibold backdrop-blur-sm bg-white/20 text-white border border-white/30">
                  {{ enrollment.course.category?.name }}
                </span>
              </div>
            </div>

            <!-- Course Content -->
            <div class="p-6">
              <h3 class="text-xl font-bold text-white drop-shadow-md mb-2 group-hover:text-blue-300 transition-colors duration-300">
                {{ enrollment.course.title }}
              </h3>

              <p class="text-white/70 text-sm mb-4 line-clamp-2">
                {{ enrollment.course.description }}
              </p>

              <!-- Progress Bar -->
              <div class="mb-4">
                <div class="flex items-center justify-between text-sm text-white/60 mb-2">
                  <span>Progress</span>
                  <span>{{ Math.round(enrollment.progress_percentage) }}%</span>
                </div>
                <div class="w-full bg-white/20 rounded-full h-3 overflow-hidden">
                  <div class="bg-gradient-to-r from-blue-400 to-purple-500 h-3 rounded-full transition-all duration-1000 ease-out"
                       :style="{ width: enrollment.progress_percentage + '%' }"></div>
                </div>
              </div>

              <div class="flex items-center justify-between text-sm text-white/60 mb-4">
                <span><i class="fas fa-clock mr-1"></i>{{ enrollment.course.duration_formatted }}</span>
                <span><i class="fas fa-calendar mr-1"></i>{{ formatDate(enrollment.updated_at) }}</span>
              </div>

              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                  <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                    <i class="fas fa-user text-white text-sm"></i>
                  </div>
                  <span class="text-sm text-white/80">{{ enrollment.course.instructor_name }}</span>
                </div>
                <Link :href="route('lms.courses.show', enrollment.course.id)" 
                      class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
                  {{ enrollment.status === 'completed' ? 'Lihat Detail' : 'Lanjutkan' }}
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="enrollments.data.length === 0" class="text-center py-16">
          <div class="w-32 h-32 mx-auto mb-8 bg-white/10 rounded-full flex items-center justify-center">
            <i class="fas fa-book-open text-6xl text-white/50"></i>
          </div>
          <h3 class="text-2xl font-bold text-white mb-4">Belum ada program training</h3>
          <p class="text-white/70 text-lg mb-8">Mulai jelajahi program training yang tersedia</p>
          <Link :href="route('lms.courses.index')" 
                class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
            Jelajahi Training
          </Link>
        </div>

        <!-- Pagination -->
        <div v-if="enrollments.data.length > 0" class="flex justify-center">
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-4">
            <div class="flex items-center space-x-2">
              <Link 
                v-for="link in enrollments.links" 
                :key="link.label"
                :href="link.url"
                :class="{
                  'px-4 py-2 rounded-lg font-semibold transition-all duration-300': true,
                  'bg-gradient-to-r from-blue-500 to-purple-600 text-white hover:from-blue-600 hover:to-purple-700': link.active,
                  'bg-white/10 border border-white/20 text-white hover:bg-white/20': !link.active && link.url,
                  'bg-white/5 border border-white/10 text-white/50 cursor-not-allowed': !link.url
                }"
                v-html="link.label"
              ></Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  enrollments: Object,
  statusOptions: Array,
  filters: Object,
})

// Reactive filters
const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
  sort: props.filters?.sort || 'updated_at'
})

const getStatusText = (status) => {
  const statusMap = {
    'enrolled': 'Terdaftar',
    'in_progress': 'Sedang Belajar',
    'completed': 'Selesai',
    'dropped': 'Dibatalkan'
  }
  return statusMap[status] || status
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('id-ID', { 
    day: 'numeric', 
    month: 'short',
    year: 'numeric'
  })
}

// Watch for filter changes and update URL
watch(filters, (newFilters) => {
  router.get(route('lms.my-courses'), newFilters, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
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
  width: 8px;
}

::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Smooth animations */
* {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Glassmorphism effect */
.backdrop-blur-xl {
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

/* 3D hover effects */
.transform:hover\:scale-105:hover {
  transform: scale(1.05) translateZ(10px);
}

.transform:hover\:rotate-1:hover {
  transform: rotate(1deg) translateZ(5px);
}
</style> 