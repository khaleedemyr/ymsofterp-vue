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
        <!-- Header -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl shadow-2xl mb-8">
          <div class="p-8 bg-gradient-to-r from-blue-600/80 via-purple-600/80 to-pink-600/80 rounded-3xl">
            <div class="flex items-center justify-between">
              <div class="space-y-4">
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  Training Compliance Dashboard
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Monitoring dan tracking compliance training karyawan
                </p>
              </div>
              <div class="text-center">
                <div class="w-24 h-24 bg-white/20 backdrop-blur-sm border border-white/30 rounded-full flex items-center justify-center">
                  <i class="fas fa-chart-line text-4xl text-white"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Total Karyawan</p>
                <p class="text-3xl font-bold text-white">{{ stats.total_users }}</p>
              </div>
              <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-2xl text-blue-300"></i>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Total Jabatan</p>
                <p class="text-3xl font-bold text-white">{{ stats.total_jabatans }}</p>
              </div>
              <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-briefcase text-2xl text-green-300"></i>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Training Wajib</p>
                <p class="text-3xl font-bold text-white">{{ stats.total_mandatory_trainings }}</p>
              </div>
              <div class="w-12 h-12 bg-red-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-2xl text-red-300"></i>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Total Program</p>
                <p class="text-3xl font-bold text-white">{{ stats.total_courses }}</p>
              </div>
              <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-graduation-cap text-2xl text-purple-300"></i>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Jam Training</p>
                <p class="text-3xl font-bold text-white">{{ Math.round(stats.total_training_hours) }}</p>
              </div>
              <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-clock text-2xl text-yellow-300"></i>
              </div>
            </div>
          </div>

          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 transform hover:scale-105 transition-all duration-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/70 text-sm font-medium">Jam Mengajar</p>
                <p class="text-3xl font-bold text-white">{{ Math.round(stats.total_teaching_hours) }}</p>
              </div>
              <div class="w-12 h-12 bg-indigo-500/20 rounded-xl flex items-center justify-center">
                <i class="fas fa-chalkboard-teacher text-2xl text-indigo-300"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
          <!-- Compliance by Jabatan -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
            <div class="p-6 border-b border-white/20">
              <h3 class="text-2xl font-bold text-white drop-shadow-lg">Compliance per Jabatan</h3>
            </div>
            <div class="p-6">
              <div class="space-y-4">
                <div v-for="item in complianceByJabatan" :key="item.jabatan.id_jabatan" 
                     class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
                  <div class="flex items-center justify-between mb-3">
                    <div>
                      <h4 class="font-semibold text-white">{{ item.jabatan.nama_jabatan }}</h4>
                      <p class="text-sm text-white/60">{{ item.jabatan.divisi?.nama_divisi }}</p>
                    </div>
                    <div class="text-right">
                      <div class="text-2xl font-bold text-white">{{ item.compliance_percentage }}%</div>
                      <div class="text-sm text-white/60">{{ item.compliant_users }}/{{ item.total_users }} users</div>
                    </div>
                  </div>
                  <div class="w-full bg-white/20 rounded-full h-3 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-400 to-blue-500 h-3 rounded-full transition-all duration-1000 ease-out"
                         :style="{ width: item.compliance_percentage + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Top Trainers -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
            <div class="p-6 border-b border-white/20">
              <h3 class="text-2xl font-bold text-white drop-shadow-lg">Top Trainers</h3>
            </div>
            <div class="p-6">
              <div class="space-y-4">
                <div v-for="(trainer, index) in topTrainers" :key="trainer.trainer_id" 
                     class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                      <div class="w-8 h-8 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-sm">{{ index + 1 }}</span>
                      </div>
                      <div>
                        <h4 class="font-semibold text-white">{{ trainer.trainer?.nama_lengkap }}</h4>
                        <p class="text-sm text-white/60">{{ trainer.trainer?.jabatan?.nama_jabatan }}</p>
                      </div>
                    </div>
                    <div class="text-right">
                      <div class="text-xl font-bold text-white">{{ Math.round(trainer.total_hours) }}h</div>
                      <div class="text-sm text-white/60">{{ trainer.session_count }} sessions</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Recent Activities -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
            <div class="p-6 border-b border-white/20">
              <h3 class="text-2xl font-bold text-white drop-shadow-lg">Aktivitas Terbaru</h3>
            </div>
            <div class="p-6">
              <div class="space-y-4">
                <div v-for="activity in recentActivities" :key="activity.id" 
                     class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
                  <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-500 rounded-full flex items-center justify-center">
                      <i class="fas fa-check text-white"></i>
                    </div>
                    <div class="flex-1">
                      <h4 class="font-semibold text-white">{{ activity.user?.nama_lengkap }}</h4>
                      <p class="text-sm text-white/60">{{ activity.course?.title }}</p>
                      <p class="text-xs text-white/50">{{ formatDate(activity.completion_date) }}</p>
                    </div>
                    <div class="text-right">
                      <div class="text-sm font-semibold text-green-400">{{ Math.round(activity.hours_completed) }}h</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Low Compliance Users -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
            <div class="p-6 border-b border-white/20">
              <h3 class="text-2xl font-bold text-white drop-shadow-lg">Perhatian Khusus</h3>
            </div>
            <div class="p-6">
              <div class="space-y-4">
                <div v-for="item in lowComplianceUsers" :key="item.user.id" 
                     class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
                  <div class="flex items-center justify-between">
                    <div>
                      <h4 class="font-semibold text-white">{{ item.user.nama_lengkap }}</h4>
                      <p class="text-sm text-white/60">{{ item.user.jabatan?.nama_jabatan }}</p>
                      <p class="text-xs text-white/50">{{ item.missing_trainings }} training belum selesai</p>
                    </div>
                    <div class="text-right">
                      <div class="text-xl font-bold text-red-400">{{ item.compliance_percentage }}%</div>
                      <div class="text-sm text-white/60">Compliance</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 mt-8">
          <h3 class="text-2xl font-bold text-white drop-shadow-lg mb-6">Aksi Cepat</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Link :href="route('training.compliance.report')" 
                  class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 text-center">
              <i class="fas fa-chart-bar mr-2"></i>
              Laporan Compliance
            </Link>
            <Link :href="route('training.compliance.trainer-report')" 
                  class="px-6 py-3 bg-gradient-to-r from-green-500 to-teal-600 text-white rounded-xl font-semibold hover:from-green-600 hover:to-teal-700 transform hover:scale-105 transition-all duration-300 text-center">
              <i class="fas fa-chalkboard-teacher mr-2"></i>
              Laporan Trainer
            </Link>
            <Link :href="route('training.compliance.course-report')" 
                  class="px-6 py-3 bg-gradient-to-r from-yellow-500 to-orange-600 text-white rounded-xl font-semibold hover:from-yellow-600 hover:to-orange-700 transform hover:scale-105 transition-all duration-300 text-center">
              <i class="fas fa-graduation-cap mr-2"></i>
              Laporan Course
            </Link>
            <Link :href="route('jabatan-training.index')" 
                  class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl font-semibold hover:from-purple-600 hover:to-pink-700 transform hover:scale-105 transition-all duration-300 text-center">
              <i class="fas fa-cog mr-2"></i>
              Kelola Training
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  stats: Object,
  complianceByJabatan: Array,
  topTrainers: Array,
  recentActivities: Array,
  lowComplianceUsers: Array,
})

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('id-ID', { 
    day: 'numeric', 
    month: 'short',
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
