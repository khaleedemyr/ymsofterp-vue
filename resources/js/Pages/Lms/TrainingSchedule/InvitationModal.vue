<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    
    <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
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
      
             <!-- Search & Filters -->
       <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
         <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
           <div>
             <label class="block text-white/70 text-sm mb-2">Cari Peserta</label>
             <input v-model="searchQuery" 
                    type="text" 
                    placeholder="Nama, email, atau NIK..."
                    class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
           </div>
           
           <!-- Divisi Filter -->
           <div>
             <label class="block text-white/70 text-sm mb-2">Filter Divisi</label>
             <div class="space-y-2">
               <div class="relative">
                 <input
                   v-model="divisionSearch"
                   type="text"
                   placeholder="Cari divisi..."
                   class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                 >
                 <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50 text-sm"></i>
               </div>
               
               <div class="max-h-24 overflow-y-auto bg-white/5 rounded-lg p-2 border border-white/10">
                 <div v-for="division in filteredDivisions" :key="division.id" class="flex items-center mb-1">
                   <input
                     type="checkbox"
                     :id="'filter-division-' + division.id"
                     :value="division.id"
                     v-model="selectedDivisions"
                     class="w-3 h-3 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-1"
                   >
                   <label :for="'filter-division-' + division.id" class="ml-2 text-xs text-white/80 cursor-pointer">
                     {{ division.nama_divisi }}
                   </label>
                 </div>
                 <div v-if="filteredDivisions.length === 0" class="text-center py-1">
                   <p class="text-xs text-white/50">Tidak ada divisi ditemukan</p>
                 </div>
               </div>
             </div>
           </div>
           
           <!-- Jabatan Filter -->
           <div>
             <label class="block text-white/70 text-sm mb-2">Filter Jabatan</label>
             <div class="space-y-2">
               <div class="relative">
                 <input
                   v-model="jabatanSearch"
                   type="text"
                   placeholder="Cari jabatan..."
                   class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 text-sm"
                 >
                 <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50 text-sm"></i>
               </div>
               
               <div class="max-h-24 overflow-y-auto bg-white/5 rounded-lg p-2 border border-white/10">
                 <div v-for="jabatan in filteredJabatans" :key="jabatan.id_jabatan" class="flex items-center mb-1">
                   <input
                     type="checkbox"
                     :id="'filter-jabatan-' + jabatan.id_jabatan"
                     :value="jabatan.id_jabatan"
                     v-model="selectedJabatans"
                     class="w-3 h-3 text-green-600 bg-white/10 border-white/20 rounded focus:ring-green-500 focus:ring-1"
                   >
                   <label :for="'filter-jabatan-' + jabatan.id_jabatan" class="ml-2 text-xs text-white/80 cursor-pointer">
                     {{ jabatan.nama_jabatan }}
                     <span v-if="jabatan.divisi" class="text-xs text-white/60">({{ jabatan.divisi.nama_divisi }})</span>
                   </label>
                 </div>
                 <div v-if="filteredJabatans.length === 0" class="text-center py-1">
                   <p class="text-xs text-white/50">Tidak ada jabatan ditemukan</p>
                 </div>
               </div>
             </div>
           </div>
           
           <!-- Level Filter -->
           <div>
             <label class="block text-white/70 text-sm mb-2">Filter Level</label>
             <div class="space-y-2">
               <div class="relative">
                 <input
                   v-model="levelSearch"
                   type="text"
                   placeholder="Cari level..."
                   class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                 >
                 <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50 text-sm"></i>
               </div>
               
               <div class="max-h-24 overflow-y-auto bg-white/5 rounded-lg p-2 border border-white/10">
                 <div v-for="level in filteredLevels" :key="level.id" class="flex items-center mb-1">
                   <input
                     type="checkbox"
                     :id="'filter-level-' + level.id"
                     :value="level.id"
                     v-model="selectedLevels"
                     class="w-3 h-3 text-purple-600 bg-white/10 border-white/20 rounded focus:ring-purple-500 focus:ring-1"
                   >
                   <label :for="'filter-level-' + level.id" class="ml-2 text-xs text-white/80 cursor-pointer">
                     {{ level.nama_level }}
                   </label>
                 </div>
                 <div v-if="filteredLevels.length === 0" class="text-center py-1">
                   <p class="text-xs text-white/50">Tidak ada level ditemukan</p>
                 </div>
               </div>
             </div>
           </div>
         </div>
       </div>
      
      <!-- Participants List -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-semibold text-white">Daftar Peserta</h4>
          <div class="flex items-center space-x-4">
            <span class="text-white/70 text-sm">{{ selectedParticipants.length }} dipilih</span>
            <button @click="selectAll" 
                    class="px-3 py-1 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all text-sm">
              Pilih Semua
            </button>
            <button @click="clearSelection" 
                    class="px-3 py-1 bg-gray-500/20 border border-gray-500/30 rounded-lg text-gray-200 hover:bg-gray-500/30 transition-all text-sm">
              Hapus Pilihan
            </button>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-white">
            <thead>
              <tr class="border-b border-white/20">
                <th class="text-left py-2">
                  <input type="checkbox" 
                         :checked="isAllSelected" 
                         @change="toggleSelectAll"
                         class="rounded border-white/30 bg-white/10 text-blue-500 focus:ring-blue-500">
                </th>
                <th class="text-left py-2">Nama</th>
                                 <th class="text-left py-2">NIK</th>
                <th class="text-left py-2">Email</th>
                <th class="text-left py-2">Divisi</th>
                <th class="text-left py-2">Jabatan</th>
                <th class="text-left py-2">Level</th>
                <th class="text-left py-2">Status</th>
              </tr>
            </thead>
                         <tbody>
               <tr v-for="participant in paginatedParticipants" :key="participant.id" 
                   class="border-b border-white/10 hover:bg-white/5">
                <td class="py-2">
                  <input type="checkbox" 
                         :value="participant.id"
                         v-model="selectedParticipants"
                         :disabled="isAlreadyInvited(participant.id)"
                         class="rounded border-white/30 bg-white/10 text-blue-500 focus:ring-blue-500">
                </td>
                <td class="py-2">{{ participant.nama_lengkap }}</td>
                                 <td class="py-2">{{ participant.nik || '-' }}</td>
                <td class="py-2">{{ participant.email }}</td>
                <td class="py-2">{{ participant.divisi?.nama_divisi || '-' }}</td>
                <td class="py-2">{{ participant.jabatan?.nama_jabatan || '-' }}</td>
                <td class="py-2">{{ participant.jabatan?.level?.nama_level || '-' }}</td>
                <td class="py-2">
                  <span v-if="isAlreadyInvited(participant.id)" 
                        class="px-2 py-1 bg-green-500/20 border border-green-500/30 rounded text-xs text-green-200">
                    Sudah Diundang
                  </span>
                  <span v-else 
                        class="px-2 py-1 bg-gray-500/20 border border-gray-500/30 rounded text-xs text-gray-200">
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
  participants: Array,
  divisions: Array,
  jabatans: Array,
  levels: Array,
  invitedParticipants: Array
})

const emit = defineEmits(['close', 'invited'])

const loading = ref(false)
const searchQuery = ref('')
const divisionSearch = ref('')
const jabatanSearch = ref('')
const levelSearch = ref('')
const selectedDivisions = ref([])
const selectedJabatans = ref([])
const selectedLevels = ref([])
const selectedParticipants = ref([])
const currentPage = ref(1)
const perPage = 20

// Filtered data for target selection
const filteredDivisions = computed(() => {
  if (!divisionSearch.value) return props.divisions
  return props.divisions.filter(division => 
    division.nama_divisi.toLowerCase().includes(divisionSearch.value.toLowerCase())
  )
})

const filteredJabatans = computed(() => {
  if (!jabatanSearch.value) return props.jabatans
  return props.jabatans.filter(jabatan => 
    jabatan.nama_jabatan.toLowerCase().includes(jabatanSearch.value.toLowerCase()) ||
    (jabatan.divisi && jabatan.divisi.nama_divisi.toLowerCase().includes(jabatanSearch.value.toLowerCase()))
  )
})

const filteredLevels = computed(() => {
  if (!levelSearch.value) return props.levels
  return props.levels.filter(level => 
    level.nama_level.toLowerCase().includes(levelSearch.value.toLowerCase())
  )
})

const filteredParticipants = computed(() => {
  let filtered = props.participants

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(participant => 
      participant.nama_lengkap?.toLowerCase().includes(query) ||
      participant.email?.toLowerCase().includes(query) ||
      participant.nik?.toLowerCase().includes(query)
    )
  }

  // Division filter (multiple selection)
  if (selectedDivisions.value.length > 0) {
    filtered = filtered.filter(participant => 
      participant.divisi && selectedDivisions.value.includes(participant.divisi.id)
    )
  }

  // Jabatan filter (multiple selection)
  if (selectedJabatans.value.length > 0) {
    filtered = filtered.filter(participant => 
      participant.jabatan && selectedJabatans.value.includes(participant.jabatan.id_jabatan)
    )
  }

  // Level filter (multiple selection)
  if (selectedLevels.value.length > 0) {
    filtered = filtered.filter(participant => 
      participant.jabatan?.level && selectedLevels.value.includes(participant.jabatan.level.id)
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

  try {
    await router.post(route('lms.schedules.invite', props.training.id), {
      user_ids: selectedParticipants.value
    }, {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Undangan berhasil dikirim'
        })
        emit('invited')
        emit('close')
      },
      onError: (errors) => {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: Object.values(errors)[0] || 'Terjadi kesalahan saat mengirim undangan'
        })
      }
    })
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat mengirim undangan'
    })
  } finally {
    loading.value = false
  }
}

// Reset page when filters change
watch([searchQuery, selectedDivisions, selectedJabatans, selectedLevels], () => {
  currentPage.value = 1
})
</script>
