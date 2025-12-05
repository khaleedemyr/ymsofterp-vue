<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    
    <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-4 md:p-6 max-w-7xl w-full mx-2 md:mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-2xl font-bold text-white">Undang Peserta</h3>
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
            <i class="fas fa-users text-purple-400"></i>
            <span>{{ training.participant_count }} peserta terdaftar</span>
          </div>
        </div>
      </div>
      
      <!-- Training Target Info -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <h4 class="text-lg font-semibold text-white mb-3 flex items-center">
          <i class="fas fa-bullseye text-yellow-400 mr-2"></i>
          Target Training
        </h4>
        
        <div v-if="courseTargets" class="space-y-3">
          <!-- Target Type -->
          <div class="flex items-center space-x-2">
            <i class="fas fa-info-circle text-blue-400"></i>
            <span class="text-white/80">Tipe Target:</span>
            <span class="px-2 py-1 bg-blue-500/20 text-blue-200 rounded text-sm">
              {{ getTargetTypeText(courseTargets.target_type) }}
            </span>
          </div>
          
          <!-- Target Divisions -->
          <div v-if="(courseTargets.target_division_id || (courseTargets.target_divisions && courseTargets.target_divisions.length > 0))" class="flex items-start space-x-2">
            <i class="fas fa-building text-green-400 mt-1"></i>
            <div class="flex-1">
              <span class="text-white/80 block mb-1">Target Divisi:</span>
              <div class="flex flex-wrap gap-2">
                <!-- Single division target -->
                <span v-if="courseTargets.target_division_id && courseTargets.target_divisions && courseTargets.target_divisions.length > 0" 
                      class="px-2 py-1 bg-green-500/20 text-green-200 rounded text-sm">
                  {{ courseTargets.target_divisions[0]?.nama_divisi || 'Divisi ID: ' + courseTargets.target_division_id }}
                </span>
                <!-- Multiple divisions target -->
                <span v-else-if="courseTargets.target_divisions && courseTargets.target_divisions.length > 0" 
                      v-for="division in courseTargets.target_divisions" :key="division.id"
                      class="px-2 py-1 bg-green-500/20 text-green-200 rounded text-sm">
                  {{ division.nama_divisi }}
                </span>
                <!-- Fallback for single division without relationship loaded -->
                <span v-else-if="courseTargets.target_division_id" 
                      class="px-2 py-1 bg-green-500/20 text-green-200 rounded text-sm">
                  Divisi ID: {{ courseTargets.target_division_id }}
                </span>
              </div>
            </div>
          </div>
          
          <!-- Target Jabatans -->
          <div v-if="(courseTargets.target_jabatan_ids && courseTargets.target_jabatan_ids.length > 0) || (courseTargets.target_jabatans && courseTargets.target_jabatans.length > 0)" class="flex items-start space-x-2">
            <i class="fas fa-user-tie text-purple-400 mt-1"></i>
            <div class="flex-1">
              <span class="text-white/80 block mb-1">Target Jabatan:</span>
              <div class="flex flex-wrap gap-2">
                <!-- From relationship -->
                <span v-if="courseTargets.target_jabatans && courseTargets.target_jabatans.length > 0" 
                      v-for="jabatan in courseTargets.target_jabatans" :key="jabatan.id_jabatan"
                      class="px-2 py-1 bg-purple-500/20 text-purple-200 rounded text-sm">
                  {{ jabatan.nama_jabatan }}
                </span>
                <!-- From array IDs -->
                <span v-else-if="courseTargets.target_jabatan_ids && courseTargets.target_jabatan_ids.length > 0" 
                      v-for="jabatanId in courseTargets.target_jabatan_ids" :key="jabatanId"
                      class="px-2 py-1 bg-purple-500/20 text-purple-200 rounded text-sm">
                  Jabatan ID: {{ jabatanId }}
                </span>
              </div>
            </div>
          </div>
          
          <!-- Target Outlets -->
          <div v-if="(courseTargets.target_outlet_ids && courseTargets.target_outlet_ids.length > 0) || (courseTargets.target_outlets && courseTargets.target_outlets.length > 0)" class="flex items-start space-x-2">
            <i class="fas fa-store text-orange-400 mt-1"></i>
            <div class="flex-1">
              <span class="text-white/80 block mb-1">Target Outlet:</span>
              <div class="flex flex-wrap gap-2">
                <!-- From relationship -->
                <span v-if="courseTargets.target_outlets && courseTargets.target_outlets.length > 0" 
                      v-for="outlet in courseTargets.target_outlets" :key="outlet.id_outlet"
                      class="px-2 py-1 bg-orange-500/20 text-orange-200 rounded text-sm">
                  {{ outlet.nama_outlet }}
                </span>
                <!-- From array IDs -->
                <span v-else-if="courseTargets.target_outlet_ids && courseTargets.target_outlet_ids.length > 0" 
                      v-for="outletId in courseTargets.target_outlet_ids" :key="outletId"
                      class="px-2 py-1 bg-orange-500/20 text-orange-200 rounded text-sm">
                  Outlet ID: {{ outletId }}
                </span>
              </div>
            </div>
          </div>
          
          <!-- Filter Status -->
          <div class="flex items-center space-x-2 pt-2 border-t border-white/10">
            <i class="fas fa-filter text-cyan-400"></i>
            <span class="text-white/80">Filter yang diterapkan:</span>
            <span class="px-2 py-1 bg-cyan-500/20 text-cyan-200 rounded text-sm">
              {{ filterApplied || 'Sedang memuat...' }}
            </span>
          </div>
        </div>
        
        <div v-else class="text-white/60 text-center py-4">
          <i class="fas fa-spinner fa-spin mr-2"></i>
          Memuat informasi target training...
        </div>
      </div>
      
      <!-- Search -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <div class="flex flex-col lg:flex-row items-start lg:items-center space-y-4 lg:space-y-0 lg:space-x-4">
          <div class="flex-1 w-full">
            <label class="block text-white/70 text-sm mb-2">Cari Peserta</label>
            <div class="relative">
              <input v-model="searchQuery" 
                     type="text" 
                     placeholder="Nama, email, atau NIK..."
                     class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 pl-10">
              <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-white/50"></i>
            </div>
          </div>
          <div class="flex items-center space-x-2 text-white/70 text-sm">
            <i class="fas fa-info-circle"></i>
            <span class="hidden sm:inline">Peserta sudah difilter sesuai target training</span>
            <span class="sm:hidden">Sudah difilter</span>
          </div>
        </div>
      </div>
      
      <!-- Participants List -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 space-y-2 sm:space-y-0">
          <h4 class="text-lg font-semibold text-white">Daftar Peserta</h4>
          <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
            <span v-if="loadingParticipants" class="text-white/70 text-sm">
              <i class="fas fa-spinner fa-spin mr-1"></i>Memuat peserta...
            </span>
            <span v-else class="text-white/70 text-sm">{{ selectedParticipants.length }} dipilih</span>
            <div class="flex space-x-2">
              <button v-if="!loadingParticipants"
                      @click="selectAll" 
                      class="px-3 py-1 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all text-sm whitespace-nowrap">
                Pilih Semua
              </button>
              <button v-if="!loadingParticipants"
                      @click="clearSelection" 
                      class="px-3 py-1 bg-gray-500/20 border border-gray-500/30 rounded-lg text-gray-200 hover:bg-gray-500/30 transition-all text-sm whitespace-nowrap">
                Hapus Pilihan
              </button>
            </div>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-white min-w-[1400px]">
            <thead>
              <tr class="border-b border-white/20">
                <th class="text-left py-4 px-3 w-16">
                  <input type="checkbox" 
                         :checked="isAllSelected" 
                         @change="toggleSelectAll"
                         class="rounded border-white/30 bg-white/10 text-blue-500 focus:ring-blue-500">
                </th>
                <th class="text-left py-4 px-4 w-56">Nama</th>
                <th class="text-left py-4 px-3 w-32">NIK</th>
                <th class="text-left py-4 px-4 w-64">Email</th>
                <th class="text-left py-4 px-4 w-56">Divisi</th>
                <th class="text-left py-4 px-4 w-56">Jabatan</th>
                <th class="text-left py-4 px-4 w-64">Outlet</th>
                <th class="text-left py-4 px-3 w-40">Level</th>
                <th class="text-left py-4 px-3 w-40">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="participant in paginatedParticipants" :key="participant.id" 
                  class="border-b border-white/10 hover:bg-white/5">
                <td class="py-4 px-3">
                  <input type="checkbox" 
                         :value="participant.id"
                         v-model="selectedParticipants"
                         :disabled="isAlreadyInvited(participant.id)"
                         class="rounded border-white/30 bg-white/10 text-blue-500 focus:ring-blue-500">
                </td>
                <td class="py-4 px-4">
                  <div class="font-medium truncate" :title="participant.nama_lengkap">
                    {{ participant.nama_lengkap }}
                  </div>
                </td>
                <td class="py-4 px-3">
                  <div class="text-sm truncate" :title="participant.nik || '-'">
                    {{ participant.nik || '-' }}
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="text-sm truncate" :title="participant.email">
                    {{ participant.email }}
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="text-sm truncate" :title="participant.divisi?.nama_divisi || '-'">
                    {{ participant.divisi?.nama_divisi || '-' }}
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="text-sm truncate" :title="participant.jabatan?.nama_jabatan || '-'">
                    {{ participant.jabatan?.nama_jabatan || '-' }}
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="text-sm truncate" :title="participant.outlet?.nama_outlet || '-'">
                    {{ participant.outlet?.nama_outlet || '-' }}
                  </div>
                </td>
                <td class="py-4 px-3">
                  <div class="text-sm truncate" :title="participant.jabatan?.level?.nama_level || '-'">
                    {{ participant.jabatan?.level?.nama_level || '-' }}
                  </div>
                </td>
                <td class="py-4 px-3">
                  <span v-if="isAlreadyInvited(participant.id)" 
                        class="inline-block px-3 py-1 bg-green-500/20 border border-green-500/30 rounded text-xs text-green-200 whitespace-nowrap">
                    Sudah Diundang
                  </span>
                  <span v-else 
                        class="inline-block px-3 py-1 bg-gray-500/20 border border-gray-500/30 rounded text-xs text-gray-200 whitespace-nowrap">
                    Belum Diundang
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between mt-4">
          <div class="text-white/70 text-sm">
            Menampilkan {{ (currentPage - 1) * perPage + 1 }} - {{ Math.min(currentPage * perPage, totalParticipants) }} dari {{ totalParticipants }} peserta
          </div>
          <div class="flex items-center space-x-2">
            <button @click="previousPage" 
                    :disabled="currentPage === 1"
                    class="px-3 py-1 bg-white/10 border border-white/20 rounded text-white disabled:opacity-50 disabled:cursor-not-allowed">
              <i class="fas fa-chevron-left"></i>
            </button>
            <span class="text-white px-3 py-1">{{ currentPage }} / {{ totalPages }}</span>
            <button @click="nextPage" 
                    :disabled="currentPage === totalPages"
                    class="px-3 py-1 bg-white/10 border border-white/20 rounded text-white disabled:opacity-50 disabled:cursor-not-allowed">
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
      
      <!-- Actions -->
      <div class="flex items-center justify-end space-x-4">
        <button @click="$emit('close')"
                class="px-6 py-3 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all">
          Batal
        </button>
        <button @click="sendInvitations" 
                :disabled="selectedParticipants.length === 0 || loading"
                class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-blue-700 transition-all disabled:opacity-50">
          <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
          <i v-else class="fas fa-paper-plane mr-2"></i>
          {{ loading ? 'Mengirim Undangan...' : `Kirim Undangan (${selectedParticipants.length})` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  training: Object,
  divisions: Array,
  jabatans: Array,
  levels: Array,
  invitedParticipants: Array
})

const emit = defineEmits(['close', 'invited'])

const loading = ref(false)
const searchQuery = ref('')
const selectedParticipants = ref([])
const currentPage = ref(1)
const perPage = 20
const relevantParticipants = ref([])
const loadingParticipants = ref(false)
const courseTargets = ref(null)
const filterApplied = ref('')


const filteredParticipants = computed(() => {
  // Use relevant participants loaded dynamically (already filtered by target training)
  let filtered = relevantParticipants.value

  // Only apply search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(participant => 
      participant.nama_lengkap?.toLowerCase().includes(query) ||
      participant.email?.toLowerCase().includes(query) ||
      participant.nik?.toLowerCase().includes(query)
    )
  }

  return filtered
})

const totalParticipants = computed(() => filteredParticipants.value.length)
const totalPages = computed(() => Math.ceil(totalParticipants.value / perPage))

const paginatedParticipants = computed(() => {
  const start = (currentPage.value - 1) * perPage
  const end = start + perPage
  return filteredParticipants.value.slice(start, end)
})

const isAllSelected = computed(() => {
  const availableParticipants = paginatedParticipants.value.filter(p => !isAlreadyInvited(p.id))
  return availableParticipants.length > 0 && 
         availableParticipants.every(p => selectedParticipants.value.includes(p.id))
})

const isAlreadyInvited = (participantId) => {
  return props.invitedParticipants.some(invited => invited.user_id === participantId)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const getTargetTypeText = (targetType) => {
  switch (targetType) {
    case 'all':
      return 'Semua Divisi'
    case 'single':
      return 'Satu Divisi'
    case 'multiple':
      return 'Multi Divisi'
    default:
      return 'Tidak Ditentukan'
  }
}

const loadRelevantParticipants = async () => {
  if (!props.training?.id) {
    console.error('No training ID provided')
    return
  }
  
  loadingParticipants.value = true
  try {
    console.log('Loading participants for training ID:', props.training.id)
    const response = await fetch(route('lms.schedules.relevant-participants', props.training.id))
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    console.log('API Response:', data)
    
    if (data.success) {
      relevantParticipants.value = data.participants
      courseTargets.value = data.course
      filterApplied.value = data.filter_applied
      console.log('Loaded relevant participants:', data.participants.length)
      console.log('Filter applied:', data.filter_applied)
      console.log('Course targets:', data.course)
    } else {
      console.error('API returned error:', data.message)
      relevantParticipants.value = []
      courseTargets.value = null
      filterApplied.value = ''
    }
  } catch (error) {
    console.error('Error loading relevant participants:', error)
    relevantParticipants.value = []
  } finally {
    loadingParticipants.value = false
  }
}

const selectAll = () => {
  const availableParticipants = paginatedParticipants.value
    .filter(p => !isAlreadyInvited(p.id))
    .map(p => p.id)
  
  selectedParticipants.value = [...new Set([...selectedParticipants.value, ...availableParticipants])]
}

const clearSelection = () => {
  selectedParticipants.value = []
}

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    clearSelection()
  } else {
    selectAll()
  }
}

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const sendInvitations = async () => {
  if (selectedParticipants.value.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih peserta terlebih dahulu'
    })
    return
  }

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
    await router.post(route('lms.schedules.invite', props.training.id), {
      user_ids: selectedParticipants.value
    }, {
      onSuccess: () => {
        // Close loading modal first
        Swal.close()
        
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Undangan berhasil dikirim'
        })
        emit('invited')
        emit('close')
      },
      onError: (errors) => {
        // Close loading modal first
        Swal.close()
        
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: Object.values(errors)[0] || 'Terjadi kesalahan saat mengirim undangan'
        })
      }
    })
  } catch (error) {
    // Close loading modal first
    Swal.close()
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat mengirim undangan'
    })
  } finally {
    loading.value = false
  }
}

// Reset page when search changes
watch([searchQuery], () => {
  currentPage.value = 1
})

// Load relevant participants when modal opens
onMounted(() => {
  loadRelevantParticipants()
})
</script>
