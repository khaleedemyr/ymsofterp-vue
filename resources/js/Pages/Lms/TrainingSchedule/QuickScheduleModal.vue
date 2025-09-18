<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    
    <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-white">Jadwalkan Training Baru</h3>
        <button @click="$emit('close')" class="text-white/70 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <form @submit.prevent="createSchedule" class="space-y-6">
        <!-- Course Selection -->
        <div>
          <label class="block text-white font-semibold mb-2">Pilih Course</label>
          <Multiselect
            v-model="form.course_id"
            :options="props.courses"
            :searchable="true"
            :close-on-select="true"
            :clear-on-select="false"
            :preserve-search="true"
            placeholder="Pilih atau cari course..."
            track-by="id"
            label="title"
            :preselect-first="false"
            class="w-full"
          />
        </div>
        
        <!-- Date & Time -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-white font-semibold mb-2">Tanggal</label>
            <div class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white flex items-center justify-between">
              <span class="text-white">{{ formatDisplayDate(form.scheduled_date) }}</span>
              <button type="button" @click="openDatePicker" 
                      class="text-blue-400 hover:text-blue-300 transition-colors">
                <i class="fas fa-calendar-alt"></i>
              </button>
            </div>
            <!-- Hidden date input untuk menyimpan nilai -->
            <input type="hidden" v-model="form.scheduled_date">
          </div>
          <div>
            <label class="block text-white font-semibold mb-2">Waktu</label>
            <div class="grid grid-cols-2 gap-2">
              <input type="time" v-model="form.start_time" 
                     class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <input type="time" v-model="form.end_time" 
                     class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
          </div>
        </div>
        
        <!-- Venue -->
        <div>
          <label class="block text-white font-semibold mb-2">Venue</label>
          <Multiselect
            v-model="form.outlet_id"
            :options="props.outlets"
            :searchable="true"
            :close-on-select="true"
            :clear-on-select="false"
            :preserve-search="true"
            placeholder="Pilih atau cari venue..."
            track-by="id_outlet"
            label="nama_outlet"
            :preselect-first="false"
            class="w-full"
          />
        </div>
        

        
        <!-- Notes -->
        <div>
          <label class="block text-white font-semibold mb-2">Catatan (Opsional)</label>
          <textarea v-model="form.notes" rows="3"
                    class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Tambahkan catatan untuk training ini..."></textarea>
        </div>
        
        <!-- Actions -->
        <div class="flex items-center justify-end space-x-4 pt-4">
          <button type="button" @click="$emit('close')"
                  class="px-6 py-3 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all">
            Batal
          </button>
          <button type="submit" :disabled="loading"
                  class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl font-semibold hover:from-green-600 hover:to-green-700 transition-all disabled:opacity-50">
            <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-calendar-plus mr-2"></i>
            {{ loading ? 'Menyimpan...' : 'Jadwalkan Training' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  courses: {
    type: Array,
    default: () => []
  },
  outlets: {
    type: Array,
    default: () => []
  },
  selectedDate: {
    type: String,
    default: ''
  }
})

// Debug: Log props
console.log('Courses props:', props.courses)
console.log('Outlets props:', props.outlets)

const emit = defineEmits(['close', 'created'])

const loading = ref(false)

const form = reactive({
  course_id: null,
  scheduled_date: '',
  start_time: '',
  end_time: '',
  outlet_id: null,
  notes: ''
})

// Function untuk mendapatkan tanggal hari ini dalam format YYYY-MM-DD
const getTodayDate = () => {
  const today = new Date()
  const year = today.getFullYear()
  const month = String(today.getMonth() + 1).padStart(2, '0')
  const day = String(today.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// Function untuk format display date (DD/MM/YYYY)
const formatDisplayDate = (dateString) => {
  if (!dateString) return 'Pilih tanggal...'
  // Split manual, jangan pakai new Date()
  const [year, month, day] = dateString.split('-')
  return `${day}/${month}/${year}`
}

// Function untuk membuka date picker
const openDatePicker = () => {
  // Buat input date temporary untuk membuka date picker
  const tempInput = document.createElement('input')
  tempInput.type = 'date'
  tempInput.style.position = 'absolute'
  tempInput.style.left = '-9999px'
  tempInput.style.opacity = '0'
  
  tempInput.addEventListener('change', (e) => {
    form.scheduled_date = e.target.value
    document.body.removeChild(tempInput)
  })
  
  tempInput.addEventListener('blur', () => {
    document.body.removeChild(tempInput)
  })
  
  document.body.appendChild(tempInput)
  tempInput.focus()
  tempInput.showPicker?.() || tempInput.click()
}

// Set default date to today when modal opens
onMounted(() => {
  // Jika ada selectedDate dari props, gunakan itu, jika tidak gunakan tanggal hari ini
  if (props.selectedDate) {
    form.scheduled_date = props.selectedDate
  } else {
    form.scheduled_date = getTodayDate()
  }
  
  // Jam tidak diisi otomatis, biarkan user pilih sendiri
  form.start_time = ''
  form.end_time = ''
})





const createSchedule = async () => {
  // Validation
  if (!form.course_id) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Pilih course terlebih dahulu'
    })
    return
  }
  
  if (!form.scheduled_date) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Pilih tanggal training'
    })
    return
  }
  
  if (!form.start_time || !form.end_time) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Pilih waktu mulai dan selesai'
    })
    return
  }
  
  if (!form.outlet_id) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Pilih venue training'
    })
    return
  }
  
  // Prepare data for submission
  const submitData = {
    course_id: form.course_id?.id || form.course_id,
    outlet_id: form.outlet_id?.id_outlet || form.outlet_id,
    scheduled_date: form.scheduled_date,
    start_time: form.start_time,
    end_time: form.end_time,
    notes: form.notes
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
    console.log('Submitting data:', submitData)
    
    await router.post(route('lms.schedules.store'), submitData, {
      onSuccess: (page) => {
        console.log('Success response:', page)
        
        // Close loading modal first
        Swal.close()
        
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Training berhasil dijadwalkan'
        })
        emit('created')
        emit('close')
      },
      onError: (errors) => {
        console.error('Error response:', errors)
        
        // Close loading modal first
        Swal.close()
        
        let errorMessage = 'Terjadi kesalahan'
        
        if (errors && typeof errors === 'object') {
          // Get first error message
          const firstError = Object.values(errors)[0]
          if (Array.isArray(firstError)) {
            errorMessage = firstError[0]
          } else if (typeof firstError === 'string') {
            errorMessage = firstError
          }
        }
        
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: errorMessage
        })
      }
    })
  } catch (error) {
    console.error('Exception caught:', error)
    
    // Close loading modal first
    Swal.close()
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat menyimpan: ' + (error.message || 'Unknown error')
    })
  } finally {
    loading.value = false
  }
}
</script>

<style>
/* Vue Multiselect Styling untuk Glassmorphism */
.multiselect {
  background: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  border-radius: 12px !important;
  color: white !important;
  min-height: 48px !important;
}

.multiselect__tags {
  background: transparent !important;
  border: none !important;
  padding: 8px 12px !important;
}

.multiselect__input,
.multiselect__single {
  background: transparent !important;
  color: white !important;
  border: none !important;
}

.multiselect__input::placeholder {
  color: rgba(255, 255, 255, 0.7) !important;
}

.multiselect__content-wrapper {
  background: rgba(0, 0, 0, 0.8) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  border-radius: 8px !important;
  backdrop-filter: blur(10px) !important;
  max-height: 300px !important;
}

.multiselect__option {
  color: white !important;
  background: transparent !important;
  padding: 8px 12px !important;
}

.multiselect__option:hover {
  background: rgba(255, 255, 255, 0.1) !important;
}

.multiselect__option--highlight {
  background: rgba(59, 130, 246, 0.5) !important;
  color: white !important;
}

.multiselect__option--selected {
  background: rgba(59, 130, 246, 0.3) !important;
  color: white !important;
}

.multiselect__placeholder {
  color: rgba(255, 255, 255, 0.7) !important;
}

.multiselect__single {
  color: white !important;
}

.multiselect__input {
  color: white !important;
}

.multiselect__input::placeholder {
  color: rgba(255, 255, 255, 0.5) !important;
}

.multiselect__clear {
  color: rgba(255, 255, 255, 0.7) !important;
}

.multiselect__clear:hover {
  color: white !important;
}

.multiselect--active {
  border-color: rgba(59, 130, 246, 0.5) !important;
}
</style>
