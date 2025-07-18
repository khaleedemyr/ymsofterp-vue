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
        <!-- Course Header -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl shadow-2xl mb-8">
          <div class="p-8 bg-gradient-to-r from-blue-600/80 via-purple-600/80 to-pink-600/80 rounded-3xl">
            <div class="flex items-start justify-between">
              <div class="flex-1 space-y-4">
                <div class="flex items-center space-x-4 mb-4">
                  <Link :href="route('lms.courses.index')" 
                        class="px-4 py-2 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali ke Training
                  </Link>
                  <span class="px-3 py-1 text-sm rounded-full font-semibold backdrop-blur-sm bg-green-500/20 text-green-200 border border-green-500/30">
                    Program Internal
                  </span>
                </div>
                
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  {{ course.title }}
                </h1>
                
                <p class="text-xl text-white/90 drop-shadow-md max-w-3xl">
                  {{ course.description }}
                </p>
                
                <div class="flex items-center space-x-6 text-white/80">
                  <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                      <i class="fas fa-user text-white"></i>
                    </div>
                    <span class="font-semibold">{{ course.instructor_name }}</span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fas fa-clock"></i>
                    <span>{{ course.duration_formatted }}</span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fas fa-users"></i>
                    <span>{{ course.enrollments_count }} peserta terdaftar</span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fas fa-star text-yellow-400"></i>
                    <span>4.5 (120 ulasan)</span>
                  </div>
                </div>
              </div>
              
              <div class="text-right space-y-4">
                <div class="text-center">
                  <div class="text-6xl font-bold text-white drop-shadow-lg animate-pulse">
                    {{ course.lessons_count || 0 }}
                  </div>
                  <div class="text-lg text-white/90 drop-shadow-md">Sesi Training</div>
                </div>
                
                <div v-if="!isEnrolled" class="space-y-3">
                  <button @click="enrollCourse" 
                          class="w-full px-8 py-4 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl font-bold hover:from-green-600 hover:to-green-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
                    <i class="fas fa-graduation-cap mr-2"></i>
                    Daftar Training
                  </button>
                  <button class="w-full px-8 py-3 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300">
                    <i class="fas fa-calendar mr-2"></i>
                    Jadwalkan Training
                  </button>
                </div>
                
                <div v-else class="space-y-3">
                  <div class="px-6 py-3 bg-green-500/20 border border-green-500/30 rounded-xl text-green-200 font-semibold text-center">
                    <i class="fas fa-check mr-2"></i>
                    Sudah Terdaftar
                  </div>
                  <Link :href="route('lms.courses.learn', course.id)" 
                        class="block w-full px-8 py-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-bold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg text-center">
                    <i class="fas fa-play mr-2"></i>
                    Lanjutkan Training
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Course Content -->
          <div class="lg:col-span-2 space-y-8">
            <!-- What You'll Learn -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
              <div class="p-6 border-b border-white/20">
                <h3 class="text-2xl font-bold text-white drop-shadow-lg flex items-center">
                  <i class="fas fa-lightbulb mr-3 text-yellow-300"></i>
                  Kompetensi yang Akan Dikembangkan
                </h3>
              </div>
              <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div v-for="(learning, index) in course.learning_objectives" :key="index" 
                       class="flex items-start space-x-3 backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-all duration-300">
                    <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                      <i class="fas fa-check text-white text-xs"></i>
                    </div>
                    <span class="text-white/90">{{ learning }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Course Curriculum -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
              <div class="p-6 border-b border-white/20">
                <h3 class="text-2xl font-bold text-white drop-shadow-lg flex items-center">
                  <i class="fas fa-list mr-3 text-blue-300"></i>
                  Kurikulum Training
                </h3>
              </div>
              <div class="p-6">
                <div v-if="course.lessons && course.lessons.length > 0" class="space-y-4">
                  <div v-for="lesson in course.lessons" :key="lesson.id" 
                       class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-all duration-300">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
                          <i :class="getLessonIcon(lesson.type) + ' text-white'"></i>
                        </div>
                        <div>
                          <h4 class="font-semibold text-white drop-shadow-md">{{ lesson.title }}</h4>
                          <p class="text-sm text-white/60">{{ lesson.duration_formatted }}</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-2">
                        <span v-if="lesson.is_preview" 
                              class="px-2 py-1 text-xs bg-blue-500/20 text-blue-200 border border-blue-500/30 rounded-full">
                          Preview
                        </span>
                        <i class="fas fa-chevron-right text-white/50"></i>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-center py-12">
                  <div class="w-24 h-24 mx-auto mb-6 bg-white/10 rounded-full flex items-center justify-center">
                    <i class="fas fa-book text-4xl text-white/50"></i>
                  </div>
                  <p class="text-white/70 text-lg">Belum ada sesi training tersedia</p>
                </div>
              </div>
            </div>

            <!-- Requirements -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
              <div class="p-6 border-b border-white/20">
                <h3 class="text-2xl font-bold text-white drop-shadow-lg flex items-center">
                  <i class="fas fa-clipboard-list mr-3 text-green-300"></i>
                  Persyaratan Peserta
                </h3>
              </div>
              <div class="p-6">
                <div v-if="course.requirements && course.requirements.length > 0" class="space-y-3">
                  <div v-for="(requirement, index) in course.requirements" :key="index" 
                       class="flex items-start space-x-3 backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                      <i class="fas fa-info text-white text-xs"></i>
                    </div>
                    <span class="text-white/90">{{ requirement }}</span>
                  </div>
                </div>
                <div v-else class="text-center py-8">
                  <p class="text-white/70">Tidak ada persyaratan khusus</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Course Info Card -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h4 class="text-xl font-bold text-white drop-shadow-lg mb-4">Informasi Training</h4>
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Kategori</span>
                  <span class="text-white font-semibold">{{ course.category?.name }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Level</span>
                  <span :class="{
                    'px-2 py-1 text-xs rounded-full font-semibold': true,
                    'bg-blue-500/20 text-blue-200 border border-blue-500/30': course.difficulty === 'beginner',
                    'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30': course.difficulty === 'intermediate',
                    'bg-red-500/20 text-red-200 border border-red-500/30': course.difficulty === 'advanced'
                  }">
                    {{ course.difficulty_text }}
                  </span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Durasi</span>
                  <span class="text-white font-semibold">{{ course.duration_formatted }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Sesi</span>
                  <span class="text-white font-semibold">{{ course.lessons_count || 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-white/70">Peserta</span>
                  <span class="text-white font-semibold">{{ course.enrollments_count }}</span>
                </div>
                                  <div class="flex items-center justify-between">
                    <span class="text-white/70">Divisi</span>
                    <span class="text-white font-semibold">{{ course.target_division_name }}</span>
                  </div>
              </div>
            </div>

            <!-- Instructor Card -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h4 class="text-xl font-bold text-white drop-shadow-lg mb-4">Tentang Trainer</h4>
              <div class="text-center space-y-4">
                <div class="w-20 h-20 mx-auto bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                  <i class="fas fa-user text-2xl text-white"></i>
                </div>
                <div>
                  <h5 class="text-lg font-bold text-white">{{ course.instructor_name }}</h5>
                  <p class="text-white/70 text-sm">Trainer Internal</p>
                </div>
                <p class="text-white/80 text-sm">
                  Trainer berpengalaman dengan keahlian di bidang ini dan telah melatih ribuan karyawan dalam program pengembangan kompetensi perusahaan.
                </p>
                <button class="w-full px-4 py-2 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300">
                  Lihat Profil
                </button>
              </div>
            </div>

            <!-- Certificate Card -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <div class="text-center space-y-4">
                <div class="w-16 h-16 mx-auto bg-gradient-to-br from-yellow-500 to-orange-500 rounded-full flex items-center justify-center">
                  <i class="fas fa-certificate text-2xl text-white"></i>
                </div>
                <div>
                  <h5 class="text-lg font-bold text-white">Sertifikat Penyelesaian</h5>
                  <p class="text-white/70 text-sm">Dapatkan sertifikat setelah menyelesaikan training</p>
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
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  course: Object,
  isEnrolled: Boolean,
})

const getLessonIcon = (type) => {
  const icons = {
    'video': 'fas fa-play',
    'document': 'fas fa-file-alt',
    'quiz': 'fas fa-question-circle',
    'assignment': 'fas fa-tasks',
    'discussion': 'fas fa-comments'
  }
  return icons[type] || 'fas fa-play'
}

const enrollCourse = () => {
  // Implement enrollment logic
  console.log('Enrolling in course:', props.course.id)
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