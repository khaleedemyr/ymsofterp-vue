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
                  Laporan Training Center
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Analisis dan statistik program pengembangan kompetensi
                </p>
              </div>
              <div class="text-center">
                <div class="w-24 h-24 bg-white/20 backdrop-blur-sm border border-white/30 rounded-full flex items-center justify-center">
                  <i class="fas fa-chart-bar text-4xl text-white"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Overall Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Total Program</p>
                <p class="text-3xl font-bold text-white">{{ overallStats.total_courses }}</p>
              </div>
              <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-book text-2xl text-blue-300"></i>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex items-center text-green-400 text-sm">
                <i class="fas fa-arrow-up mr-1"></i>
                <span>{{ overallStats.published_courses }} Aktif</span>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Total Peserta</p>
                <p class="text-3xl font-bold text-white">{{ overallStats.total_enrollments }}</p>
              </div>
              <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-2xl text-green-300"></i>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex items-center text-blue-400 text-sm">
                <i class="fas fa-chart-line mr-1"></i>
                <span>{{ overallStats.active_enrollments }} Aktif</span>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Selesai</p>
                <p class="text-3xl font-bold text-white">{{ overallStats.completed_enrollments }}</p>
              </div>
              <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-certificate text-2xl text-yellow-300"></i>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex items-center text-yellow-400 text-sm">
                <i class="fas fa-star mr-1"></i>
                <span>{{ Math.round((overallStats.completed_enrollments / overallStats.total_enrollments) * 100) || 0 }}% Rate</span>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Kategori</p>
                <p class="text-3xl font-bold text-white">{{ overallStats.total_categories }}</p>
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
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Enrollment Trends -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
            <div class="p-6 border-b border-white/20">
              <h3 class="text-2xl font-bold text-white drop-shadow-lg">Tren Pendaftaran (6 Bulan Terakhir)</h3>
            </div>
            <div class="p-6">
              <div v-if="enrollmentTrends.length > 0" class="space-y-4">
                <div v-for="trend in enrollmentTrends" :key="trend.month" 
                     class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
                  <div class="flex items-center justify-between">
                    <div>
                      <h4 class="font-semibold text-white drop-shadow-md">{{ formatMonth(trend.month) }}</h4>
                      <p class="text-sm text-white/60">{{ trend.enrollments }} pendaftaran</p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center">
                      <span class="text-white font-bold">{{ trend.enrollments }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-12">
                <div class="w-24 h-24 mx-auto mb-6 bg-white/10 rounded-full flex items-center justify-center">
                  <i class="fas fa-chart-line text-4xl text-white/50"></i>
                </div>
                <p class="text-white/70 text-lg">Belum ada data tren</p>
              </div>
            </div>
          </div>

          <!-- Top Courses -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
            <div class="p-6 border-b border-white/20">
              <h3 class="text-2xl font-bold text-white drop-shadow-lg">Program Training Terpopuler</h3>
            </div>
            <div class="p-6">
              <div v-if="topCourses.length > 0" class="space-y-4">
                <div v-for="(course, index) in topCourses" :key="course.id" 
                     class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
                  <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center">
                      <span class="text-white font-bold text-lg">{{ index + 1 }}</span>
                    </div>
                    <div class="flex-1">
                      <h4 class="font-semibold text-white drop-shadow-md">{{ course.title }}</h4>
                      <p class="text-sm text-white/60">{{ course.category?.name }}</p>
                      <div class="flex items-center space-x-4 mt-2">
                        <span class="text-sm text-white/60">
                          <i class="fas fa-users mr-1"></i>{{ course.enrollments_count }} peserta
                        </span>
                        <span class="text-sm text-white/60">
                          <i class="fas fa-star mr-1"></i>{{ course.instructor_name }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-12">
                <div class="w-24 h-24 mx-auto mb-6 bg-white/10 rounded-full flex items-center justify-center">
                  <i class="fas fa-trophy text-4xl text-white/50"></i>
                </div>
                <p class="text-white/70 text-lg">Belum ada data program</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Category Statistics -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl mt-8">
          <div class="p-6 border-b border-white/20">
            <h3 class="text-2xl font-bold text-white drop-shadow-lg">Statistik per Kategori</h3>
          </div>
          <div class="p-6">
            <div v-if="categoryStats.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div v-for="category in categoryStats" :key="category.id" 
                   class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-6 hover:bg-white/10 transition-all duration-300">
                <div class="flex items-center space-x-4 mb-4">
                  <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center">
                    <i class="fas fa-folder text-2xl text-white"></i>
                  </div>
                  <div>
                    <h4 class="font-semibold text-white drop-shadow-md">{{ category.name }}</h4>
                    <p class="text-sm text-white/60">{{ category.courses_count }} program</p>
                  </div>
                </div>
                <div class="space-y-2">
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-white/70">Total Program</span>
                    <span class="text-white font-semibold">{{ category.courses_count }}</span>
                  </div>
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-white/70">Status</span>
                    <span class="px-2 py-1 text-xs bg-green-500/20 text-green-200 border border-green-500/30 rounded-full">
                      Aktif
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12">
              <div class="w-24 h-24 mx-auto mb-6 bg-white/10 rounded-full flex items-center justify-center">
                <i class="fas fa-tags text-4xl text-white/50"></i>
              </div>
              <p class="text-white/70 text-lg">Belum ada data kategori</p>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 flex justify-center space-x-4">
          <button class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
            <i class="fas fa-download mr-2"></i>
            Export Laporan
          </button>
          <button class="px-6 py-3 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300">
            <i class="fas fa-print mr-2"></i>
            Cetak Laporan
          </button>
          <Link :href="route('lms.dashboard')" 
                class="px-6 py-3 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300">
            <i class="fas fa-arrow-left mr-2"></i>
            Kembali ke Dashboard
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  overallStats: Object,
  enrollmentTrends: Array,
  topCourses: Array,
  categoryStats: Array,
})

const formatMonth = (monthString) => {
  const [year, month] = monthString.split('-')
  const date = new Date(parseInt(year), parseInt(month) - 1)
  return date.toLocaleDateString('id-ID', { 
    month: 'long', 
    year: 'numeric' 
  })
}
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