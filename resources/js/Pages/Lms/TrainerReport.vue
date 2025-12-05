<template>
  <AppLayout title="Trainer Report">
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
      <!-- Header -->
      <div class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-600/20 to-purple-600/20"></div>
        <div class="relative px-6 py-8">
          <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-3xl font-bold text-white mb-2">Trainer Report</h1>
                <p class="text-white/70">Laporan performa dan aktivitas trainer</p>
              </div>
              <div class="flex items-center space-x-4">
                <button @click="refreshData" 
                        :disabled="loading"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-600/50 text-white rounded-lg transition-colors flex items-center space-x-2">
                  <i class="fas fa-sync-alt" :class="{ 'animate-spin': loading }"></i>
                  <span>Refresh</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="px-6 pb-8">
        <div class="max-w-7xl mx-auto">
          <!-- Loading State -->
          <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
            <span class="ml-3 text-white">Memuat data trainer...</span>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="bg-red-500/10 border border-red-500/20 rounded-lg p-6 text-center">
            <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-3"></i>
            <p class="text-red-300">{{ error }}</p>
            <button @click="fetchTrainerReport" 
                    class="mt-4 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
              Coba Lagi
            </button>
          </div>

          <!-- Trainer List -->
          <div v-else-if="trainers.length > 0" class="space-y-4">
            <div v-for="trainer in trainers" :key="trainer.trainer_id" 
                 class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden">
              
              <!-- Trainer Header -->
              <div class="p-6">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-4">
                    <!-- Avatar for internal trainer, icon for external -->
                    <div v-if="trainer.trainer_type === 'internal'" class="w-12 h-12 rounded-full overflow-hidden border-2 border-white/20 shadow-lg">
                      <img v-if="trainer.trainer_avatar" 
                           :src="`/storage/${trainer.trainer_avatar}`" 
                           :alt="trainer.trainer_name" 
                           class="w-full h-full object-cover" />
                      <div v-else class="w-full h-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold">
                        {{ getInitials(trainer.trainer_name) }}
                      </div>
                    </div>
                    <div v-else class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                      <i class="fas fa-chalkboard-teacher text-white text-lg"></i>
                    </div>
                    <div>
                      <h3 class="text-xl font-semibold text-white">{{ trainer.trainer_name }}</h3>
                      <p class="text-white/60">{{ trainer.trainer_position }} • {{ trainer.trainer_division }}</p>
                      <p class="text-white/50 text-sm">{{ trainer.trainer_email }}</p>
                    </div>
                  </div>
                  
                  <div class="flex items-center space-x-6">
                    <!-- Rating -->
                    <div class="text-center">
                      <div class="flex items-center space-x-1 mb-1">
                        <i class="fas fa-star text-yellow-400"></i>
                        <span class="text-white font-semibold">{{ trainer.average_rating || '0.0' }}</span>
                      </div>
                      <p class="text-white/60 text-sm">{{ trainer.total_ratings }} rating</p>
                    </div>
                    
                    <!-- Total Training -->
                    <div class="text-center">
                      <div class="text-white font-semibold text-lg">{{ trainer.total_trainings }}</div>
                      <p class="text-white/60 text-sm">Total Training</p>
                    </div>
                    
                    <!-- Total Duration -->
                    <div class="text-center">
                      <div class="text-white font-semibold text-lg">{{ trainer.total_duration_hours }}h</div>
                      <p class="text-white/60 text-sm">Total Durasi</p>
                    </div>
                    
                    <!-- Expand Button -->
                    <button @click="toggleTrainerDetails(trainer.trainer_id)"
                            class="p-2 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
                      <i class="fas" :class="expandedTrainers.includes(trainer.trainer_id) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Trainer Details (Expandable) -->
              <div v-if="expandedTrainers.includes(trainer.trainer_id)" 
                   class="border-t border-white/10 bg-white/5">
                <div class="p-6">
                  <!-- Training Statistics -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white/5 rounded-lg p-4 border border-white/10">
                      <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
                          <i class="fas fa-check text-green-400"></i>
                        </div>
                        <div>
                          <div class="text-white font-semibold">{{ trainer.completed_trainings }}</div>
                          <p class="text-white/60 text-sm">Training Selesai</p>
                        </div>
                      </div>
                    </div>
                    
                    <div class="bg-white/5 rounded-lg p-4 border border-white/10">
                      <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-red-500/20 rounded-lg flex items-center justify-center">
                          <i class="fas fa-times text-red-400"></i>
                        </div>
                        <div>
                          <div class="text-white font-semibold">{{ trainer.cancelled_trainings }}</div>
                          <p class="text-white/60 text-sm">Training Dibatalkan</p>
                        </div>
                      </div>
                    </div>
                    
                    <div class="bg-white/5 rounded-lg p-4 border border-white/10">
                      <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                          <i class="fas fa-clock text-blue-400"></i>
                        </div>
                        <div>
                          <div class="text-white font-semibold">{{ trainer.total_duration_minutes }}m</div>
                          <p class="text-white/60 text-sm">Total Menit</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Training Details Table -->
                  <div class="bg-white/5 rounded-lg border border-white/10 overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/10">
                      <h4 class="text-lg font-semibold text-white">Detail Training</h4>
                    </div>
                    
                    <div class="overflow-x-auto">
                      <table class="w-full">
                        <thead class="bg-white/5">
                          <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/60 uppercase tracking-wider">Course</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/60 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/60 uppercase tracking-wider">Waktu</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/60 uppercase tracking-wider">Durasi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/60 uppercase tracking-wider">Outlet</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-white/60 uppercase tracking-wider">Status</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                          <tr v-for="training in getTrainerDetails(trainer.trainer_id)" :key="training.schedule_id"
                              class="hover:bg-white/5 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                              <div class="text-white font-medium">{{ training.course_title }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-white/80">
                              {{ new Date(training.scheduled_date).toLocaleDateString('id-ID') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-white/80">
                              {{ training.start_time }} - {{ training.end_time }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-white/80">
                              {{ training.duration_minutes }} menit
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-white/80">
                              {{ training.outlet_name || 'Tidak ditentukan' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="getStatusClass(training.status)">
                                {{ getStatusText(training.status) }}
                              </span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-12">
            <i class="fas fa-chalkboard-teacher text-white/30 text-6xl mb-4"></i>
            <h3 class="text-xl font-semibold text-white mb-2">Belum Ada Data Trainer</h3>
            <p class="text-white/60">Tidak ada data trainer yang ditemukan.</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

// Reactive data
const loading = ref(false)
const error = ref(null)
const trainers = ref([])
const trainerDetails = ref({})
const expandedTrainers = ref([])

// Methods
const fetchTrainerReport = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await fetch(route('lms.trainer-report'))
    const data = await response.json()
    
    if (data.success) {
      trainers.value = data.trainers
      trainerDetails.value = data.trainer_details
    } else {
      error.value = data.message || 'Gagal memuat data trainer'
    }
  } catch (err) {
    console.error('Error fetching trainer report:', err)
    error.value = 'Terjadi kesalahan saat memuat data trainer'
  } finally {
    loading.value = false
  }
}

const refreshData = () => {
  fetchTrainerReport()
}

// Function to get initials from name
const getInitials = (name) => {
  if (!name) return ''
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

const toggleTrainerDetails = (trainerId) => {
  const index = expandedTrainers.value.indexOf(trainerId)
  if (index > -1) {
    expandedTrainers.value.splice(index, 1)
  } else {
    expandedTrainers.value.push(trainerId)
  }
}

const getTrainerDetails = (trainerId) => {
  return trainerDetails.value[trainerId] || []
}

const getStatusClass = (status) => {
  switch (status) {
    case 'completed':
      return 'bg-green-500/20 text-green-300'
    case 'cancelled':
      return 'bg-red-500/20 text-red-300'
    case 'scheduled':
      return 'bg-blue-500/20 text-blue-300'
    case 'in_progress':
      return 'bg-yellow-500/20 text-yellow-300'
    default:
      return 'bg-gray-500/20 text-gray-300'
  }
}

const getStatusText = (status) => {
  switch (status) {
    case 'completed':
      return 'Selesai'
    case 'cancelled':
      return 'Dibatalkan'
    case 'scheduled':
      return 'Terjadwal'
    case 'in_progress':
      return 'Berlangsung'
    default:
      return status
  }
}

// Lifecycle
onMounted(() => {
  fetchTrainerReport()
})
</script>

<style scoped>
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
