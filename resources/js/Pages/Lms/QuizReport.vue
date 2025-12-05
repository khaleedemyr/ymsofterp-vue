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
                  Quiz Report
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Data berdasarkan filter yang dipilih
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl mb-8">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
              <i class="fas fa-filter mr-2 text-blue-400"></i>
              Filter Data
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4">
              <!-- By Divisi -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">By Divisi</label>
                <select v-model="filters.division_id" 
                        class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="" class="bg-gray-800 text-white">Semua Divisi</option>
                  <option v-for="division in filterOptions.divisions" :key="division.id" :value="division.id" class="bg-gray-800 text-white">
                    {{ division.nama_divisi }}
                  </option>
                </select>
              </div>

              <!-- By Outlet -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">By Outlet</label>
                <select v-model="filters.outlet_id" 
                        class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="" class="bg-gray-800 text-white">Semua Outlet</option>
                  <option v-for="outlet in filterOptions.outlets" :key="outlet.id_outlet" :value="outlet.id_outlet" class="bg-gray-800 text-white">
                    {{ outlet.nama_outlet }}
                  </option>
                </select>
              </div>

              <!-- By Jabatan -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">By Jabatan</label>
                <select v-model="filters.jabatan_id" 
                        class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="" class="bg-gray-800 text-white">Semua Jabatan</option>
                  <option v-for="jabatan in filterOptions.jabatans" :key="jabatan.id_jabatan" :value="jabatan.id_jabatan" class="bg-gray-800 text-white">
                    {{ jabatan.nama_jabatan }}
                  </option>
                </select>
              </div>

              <!-- By Level -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">By Level</label>
                <select v-model="filters.level_id" 
                        class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="" class="bg-gray-800 text-white">Semua Level</option>
                  <option v-for="level in filterOptions.levels" :key="level.id_level" :value="level.id_level" class="bg-gray-800 text-white">
                    {{ level.nama_level }}
                  </option>
                </select>
              </div>

              <!-- Date From -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">Date From</label>
                <input v-model="filters.from_date" 
                       type="date"
                       class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
              </div>

              <!-- Date To -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">Date To</label>
                <input v-model="filters.to_date" 
                       type="date"
                       class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-3">
              <button @click="loadData" 
                      :disabled="loading"
                      class="px-4 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 disabled:bg-gray-500/20 disabled:border-gray-500/30 disabled:text-gray-400 transition-all flex items-center gap-2">
                <i class="fas fa-sync-alt" :class="{ 'animate-spin': loading }"></i>
                Load Data
              </button>
              <button @click="clearFilters" 
                      class="px-4 py-2 bg-red-500/20 border border-red-500/30 rounded-lg text-red-200 hover:bg-red-500/30 transition-all">
                <i class="fas fa-times mr-2"></i>
                Clear Filters
              </button>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
          <span class="ml-3 text-white">Memuat data quiz report...</span>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="text-center py-12">
          <i class="fas fa-exclamation-triangle text-red-400 text-6xl mb-4"></i>
          <h3 class="text-xl font-semibold text-white mb-2">Terjadi Kesalahan</h3>
          <p class="text-white/60">{{ error }}</p>
        </div>

        <!-- Summary Cards -->
        <div v-else-if="quizAttempts && quizAttempts.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Total Attempts -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/60 text-sm font-medium">Total Attempts</p>
                <p class="text-3xl font-bold text-white">{{ quizAttempts.length }}</p>
              </div>
              <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                <i class="fas fa-question-circle text-blue-400 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Passed -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/60 text-sm font-medium">Lolos</p>
                <p class="text-3xl font-bold text-green-400">{{ passedCount }}</p>
              </div>
              <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center">
                <i class="fas fa-check-circle text-green-400 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Failed -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/60 text-sm font-medium">Tidak Lolos</p>
                <p class="text-3xl font-bold text-red-400">{{ failedCount }}</p>
              </div>
              <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                <i class="fas fa-times-circle text-red-400 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Pass Rate -->
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white/60 text-sm font-medium">Pass Rate</p>
                <p class="text-3xl font-bold text-yellow-400">{{ passRate }}%</p>
              </div>
              <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                <i class="fas fa-chart-line text-yellow-400 text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Data Table -->
        <div v-if="quizAttempts && quizAttempts.length > 0" class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
              <i class="fas fa-table mr-2 text-blue-400"></i>
              Quiz Attempts
            </h3>
            
            <div class="overflow-x-auto">
              <table class="w-full text-white">
                <thead>
                  <tr class="border-b border-white/20">
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Divisi</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Outlet</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Jabatan</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Level</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Quiz</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Score</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Tanggal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                  <tr v-for="attempt in quizAttempts" :key="attempt.attempt_id" class="hover:bg-white/5">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div v-if="attempt.avatar" class="w-10 h-10 rounded-full overflow-hidden border-2 border-blue-500/30 mr-3">
                          <img :src="`/storage/${attempt.avatar}`" alt="Avatar" class="w-full h-full object-cover" />
                        </div>
                        <div v-else class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold border-2 border-blue-500/30 mr-3">
                          {{ getInitials(attempt.nama_lengkap) }}
                        </div>
                        <div class="text-sm font-medium text-white">{{ attempt.nama_lengkap }}</div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white/80">{{ attempt.nama_divisi }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white/80">{{ attempt.nama_outlet }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white/80">{{ attempt.nama_jabatan }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white/80">{{ attempt.nama_level }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white/80">{{ attempt.quiz_title }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white/80">{{ attempt.score }}%</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span v-if="attempt.is_passed" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <i class="fas fa-check mr-1"></i>
                        Lolos
                      </span>
                      <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        <i class="fas fa-times mr-1"></i>
                        Tidak Lolos
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white/80">
                      {{ new Date(attempt.completed_at).toLocaleDateString('id-ID') }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- No Data State -->
        <div v-else class="text-center py-12">
          <i class="fas fa-question-circle text-white/30 text-6xl mb-4"></i>
          <h3 class="text-xl font-semibold text-white mb-2">Belum Ada Data</h3>
          <p class="text-white/60">Tidak ada data quiz yang ditemukan berdasarkan filter yang dipilih.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

// Reactive data
const loading = ref(false)
const error = ref(null)
const quizAttempts = ref([])
const filterOptions = ref({
  divisions: [],
  outlets: [],
  jabatans: [],
  levels: []
})

// Filters
const filters = ref({
  division_id: '',
  outlet_id: '',
  jabatan_id: '',
  level_id: '',
  from_date: '',
  to_date: ''
})

// Methods
const fetchQuizReport = async () => {
  loading.value = true
  error.value = null
  
  try {
    const params = new URLSearchParams()
    Object.keys(filters.value).forEach(key => {
      if (filters.value[key]) {
        params.append(key, filters.value[key])
      }
    })

    const response = await fetch(route('lms.quiz-report') + '?' + params.toString())
    const data = await response.json()
    
    if (data.success) {
      quizAttempts.value = data.data.quiz_attempts
      filterOptions.value = data.data.filter_options
    } else {
      error.value = data.message || 'Gagal memuat data quiz report'
    }
  } catch (err) {
    console.error('Error fetching quiz report:', err)
    error.value = 'Terjadi kesalahan saat memuat data quiz report'
  } finally {
    loading.value = false
  }
}

const loadData = () => {
  fetchQuizReport()
}

const clearFilters = () => {
  filters.value = {
    division_id: '',
    outlet_id: '',
    jabatan_id: '',
    level_id: '',
    from_date: '',
    to_date: ''
  }
  // Tidak auto-refresh setelah clear, user harus klik Load Data
}

const getInitials = (name) => {
  if (!name) return '';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
}

// Computed properties for summary
const passedCount = computed(() => {
  return quizAttempts.value.filter(attempt => attempt.is_passed).length;
})

const failedCount = computed(() => {
  return quizAttempts.value.filter(attempt => !attempt.is_passed).length;
})

const passRate = computed(() => {
  if (quizAttempts.value.length === 0) return 0;
  return Math.round((passedCount.value / quizAttempts.value.length) * 100);
})

// Lifecycle
onMounted(() => {
  fetchQuizReport()
})
</script>

<style scoped>
.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
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
</style>
