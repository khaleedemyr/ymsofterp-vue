<template>
  <AppLayout title="Training Report">
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
      <!-- Header -->
      <div class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-600/20 to-purple-600/20"></div>
        <div class="relative px-6 py-8">
          <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-3xl font-bold text-white mb-2">Training Report</h1>
                <p class="text-white/70">Laporan statistik training berdasarkan filter yang dipilih</p>
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
          <!-- Filters Section -->
          <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-6 mb-6">
            <h3 class="text-xl font-semibold text-white mb-4">
              <i class="fas fa-filter mr-2"></i>
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

              <!-- By Kategory Training -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">By Kategory Training</label>
                <select v-model="filters.category_id" 
                        class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="" class="bg-gray-800 text-white">Semua Kategori</option>
                  <option v-for="category in filterOptions.categories" :key="category.id" :value="category.id" class="bg-gray-800 text-white">
                    {{ category.name }}
                  </option>
                </select>
              </div>

              <!-- By Spesifikasi Training -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">By Spesifikasi Training</label>
                <select v-model="filters.specification" 
                        class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="" class="bg-gray-800 text-white">Semua Spesifikasi</option>
                  <option value="generic" class="bg-gray-800 text-white">Generic</option>
                  <option value="departemental" class="bg-gray-800 text-white">Departemental</option>
                </select>
              </div>

              <!-- By Trainer -->
              <div>
                <label class="block text-sm font-medium text-white/80 mb-2">By Trainer</label>
                <select v-model="filters.trainer_type" 
                        class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="" class="bg-gray-800 text-white">Semua Trainer</option>
                  <option value="internal" class="bg-gray-800 text-white">Internal</option>
                  <option value="external" class="bg-gray-800 text-white">External</option>
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

          <!-- Loading State -->
          <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
            <span class="ml-3 text-white">Memuat data training report...</span>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="bg-red-500/10 border border-red-500/20 rounded-lg p-6 text-center">
            <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-3"></i>
            <p class="text-red-300">{{ error }}</p>
            <button @click="fetchTrainingReport" 
                    class="mt-4 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
              Coba Lagi
            </button>
          </div>

          <!-- Report Data -->
          <div v-else-if="reportData" class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden">
            <!-- Report Header -->
            <div class="p-6 border-b border-white/10">
              <h2 class="text-2xl font-bold text-white mb-2">Training Statistics</h2>
              <p class="text-white/70">Data berdasarkan filter yang dipilih</p>
            </div>

            <!-- Statistics Cards -->
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <!-- MP (Man Power) -->
                <div @click="openMPModal" 
                     class="bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-500/30 rounded-xl p-6 text-center cursor-pointer hover:from-blue-500/30 hover:to-blue-600/30 transition-all duration-200">
                  <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-users text-white text-xl"></i>
                  </div>
                  <div class="text-3xl font-bold text-white mb-2">{{ reportData.man_power }}</div>
                  <div class="text-blue-200 text-sm font-medium">MP</div>
                  <div class="text-white/60 text-xs mt-1">Man Power</div>
                </div>

                <!-- QTY (Jumlah Training) -->
                <div @click="openQTYModal" 
                     class="bg-gradient-to-br from-green-500/20 to-green-600/20 border border-green-500/30 rounded-xl p-6 text-center cursor-pointer hover:from-green-500/30 hover:to-green-600/30 transition-all duration-200">
                  <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                  </div>
                  <div class="text-3xl font-bold text-white mb-2">{{ reportData.qty }}</div>
                  <div class="text-green-200 text-sm font-medium">QTY</div>
                  <div class="text-white/60 text-xs mt-1">Jumlah Training</div>
                </div>

                <!-- Pax (Peserta Training) -->
                <div @click="openPaxModal" 
                     class="bg-gradient-to-br from-purple-500/20 to-purple-600/20 border border-purple-500/30 rounded-xl p-6 text-center cursor-pointer hover:from-purple-500/30 hover:to-purple-600/30 transition-all duration-200">
                  <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-graduate text-white text-xl"></i>
                  </div>
                  <div class="text-3xl font-bold text-white mb-2">{{ reportData.pax }}</div>
                  <div class="text-purple-200 text-sm font-medium">Pax</div>
                  <div class="text-white/60 text-xs mt-1">Peserta Training</div>
                </div>

                <!-- Hours (Jam Training) -->
                <div @click="openHoursModal" 
                     class="bg-gradient-to-br from-orange-500/20 to-orange-600/20 border border-orange-500/30 rounded-xl p-6 text-center cursor-pointer hover:from-orange-500/30 hover:to-orange-600/30 transition-all duration-200">
                  <div class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-clock text-white text-xl"></i>
                  </div>
                  <div class="text-3xl font-bold text-white mb-2">{{ reportData.hours }}</div>
                  <div class="text-orange-200 text-sm font-medium">Hours</div>
                  <div class="text-white/60 text-xs mt-1">Jam Training</div>
                </div>

                <!-- Percentage -->
                <div class="bg-gradient-to-br from-pink-500/20 to-pink-600/20 border border-pink-500/30 rounded-xl p-6 text-center">
                  <div class="w-12 h-12 bg-pink-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-percentage text-white text-xl"></i>
                  </div>
                  <div class="text-3xl font-bold text-white mb-2">{{ reportData.percentage }}%</div>
                  <div class="text-pink-200 text-sm font-medium">%</div>
                  <div class="text-white/60 text-xs mt-1">MP Ikut Training</div>
                </div>
              </div>

              <!-- Summary -->
              <div class="mt-8 p-6 bg-white/5 rounded-xl border border-white/10">
                <h3 class="text-lg font-semibold text-white mb-4">Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <p class="text-white/80 text-sm mb-2">
                      <strong>Man Power:</strong> {{ reportData.man_power }} karyawan aktif
                    </p>
                    <p class="text-white/80 text-sm mb-2">
                      <strong>Training Dilaksanakan:</strong> {{ reportData.qty }} sesi training
                    </p>
                    <p class="text-white/80 text-sm mb-2">
                      <strong>Peserta Training:</strong> {{ reportData.pax }} karyawan
                    </p>
                  </div>
                  <div>
                    <p class="text-white/80 text-sm mb-2">
                      <strong>Total Jam Training:</strong> {{ reportData.hours }} jam
                    </p>
                    <p class="text-white/80 text-sm mb-2">
                      <strong>Persentase Partisipasi:</strong> {{ reportData.percentage }}% dari total MP
                    </p>
                    <p class="text-white/80 text-sm">
                      <strong>Rata-rata Jam per Peserta:</strong> {{ reportData.pax > 0 ? (reportData.hours / reportData.pax).toFixed(2) : 0 }} jam
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-12">
            <i class="fas fa-chart-bar text-white/30 text-6xl mb-4"></i>
            <h3 class="text-xl font-semibold text-white mb-2">Belum Ada Data</h3>
            <p class="text-white/60">Tidak ada data training yang ditemukan berdasarkan filter yang dipilih.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- MP Modal -->
    <div v-if="showMPModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click="closeModals">
      <div class="bg-gray-800 rounded-xl p-6 max-w-4xl w-full mx-4 max-h-[80vh] overflow-y-auto" @click.stop>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-white">Man Power Details</h3>
          <button @click="closeModals" class="text-white/60 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="space-y-3">
          <div v-for="employee in modalData" :key="employee.id" 
               class="bg-white/5 rounded-lg p-4 border border-white/10">
            <div class="flex items-center gap-3">
              <div v-if="employee.avatar" class="w-12 h-12 rounded-full overflow-hidden border-2 border-blue-500/30">
                <img :src="`/storage/${employee.avatar}`" alt="Avatar" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold border-2 border-blue-500/30">
                {{ getInitials(employee.nama_lengkap) }}
              </div>
              <div>
                <div class="text-white font-medium">{{ employee.nama_lengkap }}</div>
                <div class="text-white/60 text-sm">{{ employee.nama_divisi }} - {{ employee.nama_outlet }}</div>
                <div class="text-white/60 text-sm">{{ employee.nama_jabatan }} ({{ employee.nama_level }})</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- QTY Modal -->
    <div v-if="showQTYModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click="closeModals">
      <div class="bg-gray-800 rounded-xl p-6 max-w-4xl w-full mx-4 max-h-[80vh] overflow-y-auto" @click.stop>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-white">Training Details (QTY)</h3>
          <button @click="closeModals" class="text-white/60 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="space-y-3">
          <div v-for="training in modalData" :key="training.course_id" 
               class="bg-white/5 rounded-lg p-4 border border-white/10">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-white font-medium">{{ training.course_title }}</div>
                <div class="text-white/60 text-sm">{{ training.category_name }}</div>
                <div class="text-white/60 text-sm">Durasi: {{ training.duration_minutes }} menit</div>
              </div>
              <div class="text-right">
                <div class="text-green-400 font-bold text-lg">{{ training.count }}x</div>
                <div class="text-white/60 text-sm">dilaksanakan</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pax Modal -->
    <div v-if="showPaxModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click="closeModals">
      <div class="bg-gray-800 rounded-xl p-6 max-w-4xl w-full mx-4 max-h-[80vh] overflow-y-auto" @click.stop>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-white">Peserta Training Details</h3>
          <button @click="closeModals" class="text-white/60 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="space-y-3">
          <div v-for="participant in modalData" :key="participant.user_id" 
               class="bg-white/5 rounded-lg border border-white/10">
            <div class="p-4 cursor-pointer" @click="togglePaxExpand(participant.user_id)">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div v-if="participant.avatar" class="w-12 h-12 rounded-full overflow-hidden border-2 border-purple-500/30">
                    <img :src="`/storage/${participant.avatar}`" alt="Avatar" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center text-white font-bold border-2 border-purple-500/30">
                    {{ getInitials(participant.nama_lengkap) }}
                  </div>
                  <div>
                    <div class="text-white font-medium">{{ participant.nama_lengkap }}</div>
                    <div class="text-white/60 text-sm">{{ participant.nama_divisi }} - {{ participant.nama_outlet }}</div>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-purple-400 text-sm">{{ participant.training_count }} training</span>
                  <i class="fas fa-chevron-down text-white/60 transition-transform" 
                     :class="{ 'rotate-180': expandedPax.has(participant.user_id) }"></i>
                </div>
              </div>
            </div>
            <div v-if="expandedPax.has(participant.user_id)" class="px-4 pb-4 border-t border-white/10">
              <div class="mt-3 space-y-2">
                <div v-for="training in participant.trainings" :key="training.schedule_id" 
                     class="bg-white/5 rounded p-3">
                  <div class="text-white font-medium">{{ training.course_title }}</div>
                  <div class="text-white/60 text-sm">{{ training.category_name }}</div>
                  <div class="text-white/60 text-sm">Tanggal: {{ new Date(training.scheduled_date).toLocaleDateString('id-ID') }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Hours Modal -->
    <div v-if="showHoursModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click="closeModals">
      <div class="bg-gray-800 rounded-xl p-6 max-w-4xl w-full mx-4 max-h-[80vh] overflow-y-auto" @click.stop>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-white">Training Hours Details</h3>
          <button @click="closeModals" class="text-white/60 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="space-y-3">
          <div v-for="training in modalData" :key="training.course_id" 
               class="bg-white/5 rounded-lg p-4 border border-white/10">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-white font-medium">{{ training.course_title }}</div>
                <div class="text-white/60 text-sm">{{ training.category_name }}</div>
                <div class="text-white/60 text-sm">Durasi: {{ training.duration_minutes }} menit ({{ (training.duration_minutes / 60).toFixed(1) }} jam)</div>
              </div>
              <div class="text-right">
                <div class="text-orange-400 font-bold text-lg">{{ training.count }}x</div>
                <div class="text-white/60 text-sm">dilaksanakan</div>
                <div class="text-orange-300 text-sm">{{ (training.duration_minutes * training.count / 60).toFixed(1) }} jam total</div>
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
import AppLayout from '@/Layouts/AppLayout.vue'

// Reactive data
const loading = ref(false)
const error = ref(null)
const reportData = ref(null)
const filterOptions = ref({
  divisions: [],
  outlets: [],
  jabatans: [],
  levels: [],
  categories: []
})

// Modal states
const showMPModal = ref(false)
const showQTYModal = ref(false)
const showPaxModal = ref(false)
const showHoursModal = ref(false)
const modalData = ref(null)
const expandedPax = ref(new Set())

// Filters
const filters = ref({
  division_id: '',
  outlet_id: '',
  jabatan_id: '',
  level_id: '',
  category_id: '',
  specification: '',
  trainer_type: '',
  from_date: '',
  to_date: ''
})

// Methods
const fetchTrainingReport = async () => {
  loading.value = true
  error.value = null
  
  try {
    const params = new URLSearchParams()
    Object.keys(filters.value).forEach(key => {
      if (filters.value[key]) {
        params.append(key, filters.value[key])
      }
    })

    const response = await fetch(route('lms.training-report') + '?' + params.toString())
    const data = await response.json()
    
    if (data.success) {
      reportData.value = data.data
      filterOptions.value = data.data.filter_options
    } else {
      error.value = data.message || 'Gagal memuat data training report'
    }
  } catch (err) {
    console.error('Error fetching training report:', err)
    error.value = 'Terjadi kesalahan saat memuat data training report'
  } finally {
    loading.value = false
  }
}

const loadData = () => {
  fetchTrainingReport()
}

const clearFilters = () => {
  filters.value = {
    division_id: '',
    outlet_id: '',
    jabatan_id: '',
    level_id: '',
    category_id: '',
    specification: '',
    trainer_type: '',
    from_date: '',
    to_date: ''
  }
  // Tidak auto-refresh setelah clear, user harus klik Load Data
}

// Modal functions
const openMPModal = () => {
  modalData.value = reportData.value?.man_power_details || []
  showMPModal.value = true
}

const openQTYModal = () => {
  modalData.value = reportData.value?.qty_details || []
  showQTYModal.value = true
}

const openPaxModal = () => {
  modalData.value = reportData.value?.pax_details || []
  showPaxModal.value = true
}

const openHoursModal = () => {
  modalData.value = reportData.value?.hours_details || []
  showHoursModal.value = true
}

const togglePaxExpand = (userId) => {
  if (expandedPax.value.has(userId)) {
    expandedPax.value.delete(userId)
  } else {
    expandedPax.value.add(userId)
  }
}

const closeModals = () => {
  showMPModal.value = false
  showQTYModal.value = false
  showPaxModal.value = false
  showHoursModal.value = false
  modalData.value = null
  expandedPax.value.clear()
}

const getInitials = (name) => {
  if (!name) return '';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
}

// Lifecycle
onMounted(() => {
  fetchTrainingReport()
})
</script>
