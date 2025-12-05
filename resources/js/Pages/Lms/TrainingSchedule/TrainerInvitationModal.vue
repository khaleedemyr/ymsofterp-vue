<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    
    <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-2xl font-bold text-white">Undang Trainer</h3>
          <p class="text-white/70 mt-1">{{ training.course?.title }}</p>
        </div>
        <button @click="$emit('close')" class="text-white/70 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <!-- Training Info -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-white">
          <div class="flex items-center space-x-2">
            <i class="fas fa-calendar text-blue-400"></i>
            <span>{{ formatDate(training.scheduled_date) }}</span>
          </div>
          <div class="flex items-center space-x-2">
            <i class="fas fa-clock text-green-400"></i>
            <span>{{ training.start_time }} - {{ training.end_time }}</span>
          </div>
          <div class="flex items-center space-x-2">
            <i class="fas fa-chalkboard-teacher text-purple-400"></i>
            <span>{{ training.scheduleTrainers?.length || 0 }} trainer terdaftar</span>
          </div>
        </div>
      </div>
      
      <!-- Search -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <div class="relative">
          <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-white/50"></i>
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Cari trainer berdasarkan nama, jabatan, atau divisi..." 
            class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-500/50"
          />
        </div>
      </div>
      
      <!-- Trainer Type Selection -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <h4 class="text-lg font-semibold text-white mb-4">Tipe Trainer</h4>
        <div class="flex space-x-4">
          <label class="flex items-center space-x-2 cursor-pointer">
            <input 
              v-model="trainerType" 
              type="radio" 
              value="internal" 
              class="text-blue-500 focus:ring-blue-500"
            />
            <span class="text-white">Internal Trainer</span>
          </label>
          <label class="flex items-center space-x-2 cursor-pointer">
            <input 
              v-model="trainerType" 
              type="radio" 
              value="external" 
              class="text-purple-500 focus:ring-purple-500"
            />
            <span class="text-white">External Trainer</span>
          </label>
        </div>
      </div>

      <!-- External Trainer Form -->
      <div v-if="trainerType === 'external'" class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <h4 class="text-lg font-semibold text-white mb-4">Data External Trainer</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-white/70 text-sm mb-2">Nama Lengkap *</label>
            <input 
              v-model="externalTrainer.name"
              type="text" 
              placeholder="Nama lengkap trainer"
              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-purple-500/50"
            />
          </div>
          <div>
            <label class="block text-white/70 text-sm mb-2">Email</label>
            <input 
              v-model="externalTrainer.email"
              type="email" 
              placeholder="email@example.com"
              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-purple-500/50"
            />
          </div>
          <div>
            <label class="block text-white/70 text-sm mb-2">No. Telepon</label>
            <input 
              v-model="externalTrainer.phone"
              type="tel" 
              placeholder="08123456789"
              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-purple-500/50"
            />
          </div>
          <div>
            <label class="block text-white/70 text-sm mb-2">Perusahaan</label>
            <input 
              v-model="externalTrainer.company"
              type="text" 
              placeholder="Nama perusahaan"
              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-purple-500/50"
            />
          </div>
        </div>
        <div class="mt-4">
          <button 
            @click="addExternalTrainer"
            :disabled="!externalTrainer.name"
            class="px-4 py-2 bg-purple-500/20 border border-purple-500/30 rounded-lg text-purple-200 hover:bg-purple-500/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
          >
            <i class="fas fa-plus mr-2"></i>Tambah External Trainer
          </button>
        </div>
      </div>

      <!-- Available Trainers (Internal Only) -->
      <div v-if="trainerType === 'internal'" class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <h4 class="text-lg font-semibold text-white mb-4">Internal Trainer Tersedia</h4>
        
        <!-- Pagination Info -->
        <div class="flex justify-between items-center mb-4">
          <p class="text-white/70 text-sm">
            Menampilkan {{ filteredTrainers.length }} dari {{ availableTrainers.length }} trainer
          </p>
          <div class="flex items-center space-x-2">
            <span class="text-white/70 text-sm">Per halaman:</span>
            <select 
              v-model="itemsPerPage" 
              class="px-2 py-1 bg-white/10 border border-white/20 rounded text-white text-sm"
            >
              <option value="10" class="bg-gray-800">10</option>
              <option value="25" class="bg-gray-800">25</option>
              <option value="50" class="bg-gray-800">50</option>
            </select>
          </div>
        </div>
        
        <!-- Trainers List -->
        <div class="space-y-3 max-h-96 overflow-y-auto">
          <div 
            v-for="trainer in paginatedTrainers" 
            :key="trainer.id"
            class="flex items-center justify-between p-3 bg-white/5 border border-white/10 rounded-lg hover:bg-white/10 transition-all"
          >
            <div class="flex items-center space-x-3">
              <!-- Avatar -->
              <div class="w-10 h-10 rounded-full overflow-hidden bg-white/10 flex items-center justify-center">
                <img 
                  v-if="trainer.avatar" 
                  :src="'/storage/' + trainer.avatar" 
                  :alt="trainer.nama_lengkap"
                  class="w-full h-full object-cover"
                />
                <i v-else class="fas fa-user text-white/50"></i>
              </div>
              
              <!-- Trainer Info -->
              <div>
                <h5 class="text-white font-medium">{{ trainer.nama_lengkap }}</h5>
                <p class="text-white/70 text-sm">{{ trainer.jabatan?.nama_jabatan || '-' }}</p>
                <p class="text-white/50 text-xs">{{ trainer.divisi?.nama_divisi || '-' }}</p>
              </div>
            </div>
            
            <!-- Action Button -->
            <div class="flex items-center space-x-2">
              <span v-if="isTrainerInvited(trainer.id)" class="text-green-400 text-sm">
                <i class="fas fa-check mr-1"></i>Sudah diundang
              </span>
              <button 
                v-else
                @click="selectTrainer(trainer)"
                class="px-4 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all"
              >
                <i class="fas fa-plus mr-2"></i>Undang
              </button>
            </div>
          </div>
        </div>
        
        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex justify-center items-center space-x-2 mt-4">
          <button 
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-white/10 border border-white/20 rounded text-white/70 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white/20"
          >
            <i class="fas fa-chevron-left"></i>
          </button>
          
          <span class="text-white/70 text-sm">
            {{ currentPage }} dari {{ totalPages }}
          </span>
          
          <button 
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 bg-white/10 border border-white/20 rounded text-white/70 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white/20"
          >
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
      
      <!-- Selected Trainers -->
      <div v-if="selectedTrainers.length > 0" class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <h4 class="text-lg font-semibold text-white mb-4">Trainer yang Akan Diundang</h4>
        <div class="space-y-2">
          <div 
            v-for="trainer in selectedTrainers" 
            :key="trainer.id"
            class="flex items-center justify-between p-3 bg-white/10 border border-white/20 rounded-lg"
          >
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 rounded-full overflow-hidden bg-white/10 flex items-center justify-center">
                <img 
                  v-if="trainer.avatar" 
                  :src="'/storage/' + trainer.avatar" 
                  :alt="trainer.nama_lengkap"
                  class="w-full h-full object-cover"
                />
                <i v-else class="fas fa-user text-white/50"></i>
              </div>
              <span class="text-white">{{ trainer.nama_lengkap }}</span>
            </div>
            <button 
              @click="removeSelectedTrainer(trainer.id)"
              class="text-red-400 hover:text-red-300"
            >
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>
      
      <!-- Actions -->
      <div class="flex justify-end space-x-3">
        <button 
          @click="$emit('close')"
          class="px-6 py-2 bg-white/10 border border-white/20 rounded-lg text-white/70 hover:text-white hover:bg-white/20 transition-all"
        >
          Batal
        </button>
        <button 
          @click="inviteTrainers"
          :disabled="selectedTrainers.length === 0 || loading"
          class="px-6 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
        >
          <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
          <i v-else class="fas fa-paper-plane mr-2"></i>
          Undang {{ selectedTrainers.length }} Trainer
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  training: Object,
  availableTrainers: {
    type: Array,
    default: () => []
  },
  divisions: {
    type: Array,
    default: () => []
  },
  jabatans: {
    type: Array,
    default: () => []
  },
  invitedTrainers: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'invited'])

// Reactive data
const searchQuery = ref('')
const selectedTrainers = ref([])
const currentPage = ref(1)
const itemsPerPage = ref(10)
const loading = ref(false)
const trainerType = ref('internal')
const externalTrainer = ref({
  name: '',
  email: '',
  phone: '',
  company: ''
})

// Computed properties
const filteredTrainers = computed(() => {
  if (!searchQuery.value) return props.availableTrainers
  
  const search = searchQuery.value.toLowerCase()
  return props.availableTrainers.filter(trainer => {
    return trainer.nama_lengkap.toLowerCase().includes(search) ||
           trainer.jabatan?.nama_jabatan?.toLowerCase().includes(search) ||
           trainer.divisi?.nama_divisi?.toLowerCase().includes(search)
  })
})

const totalPages = computed(() => {
  return Math.ceil(filteredTrainers.value.length / itemsPerPage.value)
})

const paginatedTrainers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredTrainers.value.slice(start, end)
})

// Methods
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const isTrainerInvited = (trainerId) => {
  return props.invitedTrainers.some(trainer => trainer.trainer_id === trainerId)
}

const selectTrainer = (trainer) => {
  if (!selectedTrainers.value.find(t => t.id === trainer.id)) {
    selectedTrainers.value.push(trainer)
  }
}

const removeSelectedTrainer = (trainerId) => {
  selectedTrainers.value = selectedTrainers.value.filter(t => t.id !== trainerId)
}

const addExternalTrainer = () => {
  if (!externalTrainer.value.name) return
  
  const externalTrainerData = {
    id: 'external_' + Date.now(), // Temporary ID for external trainer
    nama_lengkap: externalTrainer.value.name,
    email: externalTrainer.value.email,
    phone: externalTrainer.value.phone,
    company: externalTrainer.value.company,
    trainer_type: 'external',
    is_external: true
  }
  
  selectedTrainers.value.push(externalTrainerData)
  
  // Reset form
  externalTrainer.value = {
    name: '',
    email: '',
    phone: '',
    company: ''
  }
}

const clearFilters = () => {
  searchQuery.value = ''
  currentPage.value = 1
}

const inviteTrainers = async () => {
  if (selectedTrainers.value.length === 0) return
  
  loading.value = true
  
  // Show prominent loading modal
  console.log('=== SHOWING LOADING MODAL ===')
  console.log('Swal object:', Swal)
  console.log('Swal.fire method:', typeof Swal.fire)
  
  try {
    // Show loading modal with simple approach
    Swal.fire({
      title: 'Sabar Bu Ghea....',
      text: 'Antosan sakedap Bu Ghea, Nuju loding',
      icon: 'info',
      showConfirmButton: false,
      allowOutsideClick: false,
      allowEscapeKey: false,
      backdrop: true,
      didOpen: () => {
        Swal.showLoading()
      }
    })
    console.log('Loading modal should be visible now')
  } catch (error) {
    console.error('Error showing loading modal:', error)
  }
  
  try {
    // Format waktu ke format H:i
    const formatTime = (time) => {
      if (!time || time === '') return null
      // Jika sudah dalam format H:i, return as is
      if (typeof time === 'string' && /^\d{2}:\d{2}$/.test(time)) {
        return time
      }
      // Jika dalam format lain, convert ke H:i
      try {
        const date = new Date(`2000-01-01T${time}`)
        if (isNaN(date.getTime())) {
          return null
        }
        return date.toTimeString().slice(0, 5)
      } catch (error) {
        console.warn('Error formatting time:', time, error)
        return null
      }
    }
    
    const trainerData = selectedTrainers.value.map(trainer => {
      const baseData = {
        is_primary_trainer: false, // Default to secondary trainer
        start_time: formatTime(props.training.start_time),
        end_time: formatTime(props.training.end_time),
        notes: `Diundang sebagai trainer untuk training ${props.training.course?.title}`
      }
      
      // Handle external trainer
      if (trainer.is_external) {
        return {
          ...baseData,
          trainer_type: 'external',
          external_trainer_name: trainer.nama_lengkap,
          external_trainer_email: trainer.email,
          external_trainer_phone: trainer.phone,
          external_trainer_company: trainer.company
        }
      }
      
      // Handle internal trainer
      return {
        ...baseData,
        trainer_id: trainer.id,
        trainer_type: 'internal'
      }
    })
    
    await router.post(route('lms.schedules.invite-trainers', props.training.id), {
      trainers: trainerData
    }, {
      onSuccess: () => {
        // Close loading modal first
        Swal.close()
        
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: `${selectedTrainers.value.length} trainer berhasil diundang`
        })
        emit('close') // Close modal first
        emit('invited') // Then trigger refresh
      },
      onError: (errors) => {
        // Close loading modal first
        Swal.close()
        
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: Object.values(errors)[0] || 'Terjadi kesalahan saat mengundang trainer'
        })
      }
    })
  } catch (error) {
    // Close loading modal first
    Swal.close()
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat mengundang trainer'
    })
  } finally {
    loading.value = false
  }
}

// Reset page when search changes
watch(searchQuery, () => {
  currentPage.value = 1
})
</script>
