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
        <!-- Welcome Header -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl shadow-2xl mb-8">
          <div class="p-8 bg-gradient-to-r from-blue-600/80 via-purple-600/80 to-pink-600/80 rounded-3xl">
            <div class="flex items-center justify-between">
              <div class="space-y-4">
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  Selamat Datang di Training Center
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Platform pengembangan kompetensi karyawan perusahaan
                </p>
                <div class="flex items-center space-x-4">
                  <div class="flex items-center space-x-2">
                    <i class="fas fa-calendar text-white/80"></i>
                    <span class="text-white/80">{{ currentDate }}</span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fas fa-clock text-white/80"></i>
                    <span class="text-white/80">{{ currentTime }}</span>
                  </div>
                </div>
              </div>
              <div class="text-center">
                <div class="w-24 h-24 bg-white/20 backdrop-blur-sm border border-white/30 rounded-full flex items-center justify-center">
                  <i class="fas fa-graduation-cap text-4xl text-white"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Total Program</p>
                <p class="text-3xl font-bold text-white">{{ courseStats.total_courses }}</p>
              </div>
              <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-book text-2xl text-blue-300"></i>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex items-center text-green-400 text-sm">
                <i class="fas fa-arrow-up mr-1"></i>
                <span>{{ courseStats.published_courses }} Aktif</span>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Peserta Terdaftar</p>
                <p class="text-3xl font-bold text-white">{{ courseStats.total_enrollments }}</p>
              </div>
              <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-2xl text-green-300"></i>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex items-center text-blue-400 text-sm">
                <i class="fas fa-chart-line mr-1"></i>
                <span>+12% dari bulan lalu</span>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Kategori Training</p>
                <p class="text-3xl font-bold text-white">{{ courseStats.total_categories }}</p>
              </div>
              <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-tags text-2xl text-purple-300"></i>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex items-center text-purple-400 text-sm">
                <i class="fas fa-layer-group mr-1"></i>
                <span>Berbagai bidang</span>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Progress Saya</p>
                <p class="text-3xl font-bold text-white">{{ Math.round(userProgress.average_progress || 0) }}%</p>
              </div>
              <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-pie text-2xl text-yellow-300"></i>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex items-center text-yellow-400 text-sm">
                <i class="fas fa-star mr-1"></i>
                <span>{{ userProgress.completed_courses || 0 }} Selesai</span>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- My Enrolled Courses -->
          <div class="lg:col-span-2 space-y-6">
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
              <div class="p-6 border-b border-white/20">
                <div class="flex items-center justify-between">
                  <h3 class="text-2xl font-bold text-white drop-shadow-lg">Program Training Saya</h3>
                  <Link :href="route('lms.courses.index')" 
                        class="px-4 py-2 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300">
                    Lihat Semua
                  </Link>
                </div>
              </div>
              <div class="p-6">
                <div v-if="enrolledCourses.length > 0" class="space-y-4">
                  <div v-for="enrollment in enrolledCourses" :key="enrollment.id" 
                       class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-all duration-300">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center">
                          <i class="fas fa-graduation-cap text-white"></i>
                        </div>
                        <div>
                          <h4 class="font-semibold text-white drop-shadow-md">{{ enrollment.course.title }}</h4>
                          <p class="text-sm text-white/60">{{ enrollment.course.category?.name }}</p>
                          <div class="flex items-center space-x-4 mt-2">
                            <span :class="{
                              'px-2 py-1 text-xs rounded-full font-semibold': true,
                              'bg-blue-500/20 text-blue-200 border border-blue-500/30': enrollment.status === 'enrolled',
                              'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30': enrollment.status === 'in_progress',
                              'bg-green-500/20 text-green-200 border border-green-500/30': enrollment.status === 'completed'
                            }">
                              {{ getStatusText(enrollment.status) }}
                            </span>
                            <span class="text-sm text-white/60">{{ enrollment.progress_percentage }}% selesai</span>
                          </div>
                        </div>
                      </div>
                      <Link :href="route('lms.courses.show', enrollment.course.id)" 
                            class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300">
                        Lanjutkan
                      </Link>
                    </div>
                  </div>
                </div>
                <div v-else class="text-center py-12">
                  <div class="w-24 h-24 mx-auto mb-6 bg-white/10 rounded-full flex items-center justify-center">
                    <i class="fas fa-book-open text-4xl text-white/50"></i>
                  </div>
                  <h4 class="text-xl font-bold text-white mb-2">Belum ada program training</h4>
                  <p class="text-white/70 mb-6">Mulai jelajahi program training yang tersedia</p>
                  <Link :href="route('lms.courses.index')" 
                        class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300">
                    Jelajahi Training
                  </Link>
                </div>
              </div>
            </div>

            <!-- Recent Courses -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
              <div class="p-6 border-b border-white/20">
                <h3 class="text-2xl font-bold text-white drop-shadow-lg">Program Training Terbaru</h3>
              </div>
              <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div v-for="course in recentCourses" :key="course.id" 
                       class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-all duration-300 cursor-pointer">
                    <div class="flex items-center space-x-3">
                      <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0">
                        <img 
                          v-if="course.thumbnail_url" 
                          :src="course.thumbnail_url" 
                          :alt="course.title" 
                          class="w-full h-full object-cover"
                        />
                        <div 
                          v-else
                          class="w-full h-full bg-gradient-to-br from-green-500 to-blue-500 flex items-center justify-center"
                        >
                          <i class="fas fa-play text-white text-sm"></i>
                        </div>
                      </div>
                      <div class="flex-1">
                        <h4 class="font-semibold text-white drop-shadow-md text-sm">{{ course.title }}</h4>
                        <p class="text-xs text-white/60">{{ course.category?.name }}</p>
                      </div>
                      <Link :href="route('lms.courses.show', course.id)" 
                            class="px-3 py-1 bg-white/20 backdrop-blur-sm border border-white/30 rounded-lg text-white text-xs font-semibold hover:bg-white/30 transition-all duration-300">
                        Detail
                      </Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h4 class="text-xl font-bold text-white drop-shadow-lg mb-4">Aksi Cepat</h4>
              <div class="space-y-3">
                <Link :href="route('lms.courses.index')" 
                      class="block w-full px-4 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 text-center">
                  <i class="fas fa-search mr-2"></i>
                  Cari Training
                </Link>
                <Link :href="route('lms.my-courses')" 
                      class="block w-full px-4 py-3 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300 text-center">
                  <i class="fas fa-book mr-2"></i>
                  Training Saya
                </Link>
                <button class="w-full px-4 py-3 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300">
                  <i class="fas fa-calendar mr-2"></i>
                  Jadwal Training
                </button>
              </div>
            </div>

            <!-- Top Categories -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h4 class="text-xl font-bold text-white drop-shadow-lg mb-4">Kategori Populer</h4>
              <div class="space-y-3">
                <div v-for="category in topCategories" :key="category.id" 
                     class="flex items-center justify-between backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-3 hover:bg-white/10 transition-all duration-300">
                  <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                      <i class="fas fa-tag text-white text-xs"></i>
                    </div>
                    <span class="text-white font-medium">{{ category.name }}</span>
                  </div>
                  <span class="text-white/60 text-sm">{{ category.courses_count }} program</span>
                </div>
              </div>
            </div>

            <!-- Learning Activity -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h4 class="text-xl font-bold text-white drop-shadow-lg mb-4">Aktivitas Belajar</h4>
              <div class="space-y-3">
                <div v-for="activity in learningActivity.slice(0, 5)" :key="activity.id" 
                     class="flex items-center space-x-3 backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-3">
                  <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-blue-500 rounded-lg flex items-center justify-center">
                    <i class="fas fa-play text-white text-xs"></i>
                  </div>
                  <div class="flex-1">
                    <p class="text-white text-sm font-medium">{{ activity.course.title }}</p>
                    <p class="text-white/60 text-xs">{{ formatDate(activity.updated_at) }}</p>
                  </div>
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
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  enrolledCourses: Array,
  userProgress: Object,
  recentCourses: Array,
  courseStats: Object,
  topCategories: Array,
  learningActivity: Array,
})

const currentDate = ref('')
const currentTime = ref('')

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
    hour: '2-digit',
    minute: '2-digit'
  })
}

const updateDateTime = () => {
  const now = new Date()
  currentDate.value = now.toLocaleDateString('id-ID', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  })
  currentTime.value = now.toLocaleTimeString('id-ID', { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}

onMounted(() => {
  updateDateTime()
  setInterval(updateDateTime, 1000)
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
</style> 