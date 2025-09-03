<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    
    <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-white">{{ training.course?.title || 'Training Detail' }}</h3>
        <button @click="$emit('close')" class="text-white/70 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <!-- Training Info Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Left Column -->
        <div class="space-y-4">
          <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
            <h4 class="text-lg font-semibold text-white mb-3">Informasi Training</h4>
            <div class="space-y-3">
              <div class="flex items-center space-x-3">
                <i class="fas fa-calendar text-blue-400 w-5"></i>
                <span class="text-white">{{ formatDate(training.scheduled_date) }}</span>
              </div>
              <div class="flex items-center space-x-3">
                <i class="fas fa-clock text-green-400 w-5"></i>
                <span class="text-white">{{ training.start_time }} - {{ training.end_time }}</span>
              </div>
              <div class="flex items-center space-x-3">
                <i class="fas fa-map-marker-alt text-red-400 w-5"></i>
                <span class="text-white">{{ training.outlet?.nama_outlet || 'Venue tidak ditentukan' }}</span>
              </div>
              <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-3">
                  <!-- Avatar untuk internal trainer, icon untuk external -->
                  <div v-if="!training.external_trainer_name && training.course?.instructor?.avatar" 
                       class="w-8 h-8 rounded-full overflow-hidden">
                    <img :src="'/storage/' + training.course.instructor.avatar" 
                         :alt="training.trainer_name"
                         class="w-full h-full object-cover" />
                  </div>
                  <i v-else class="fas fa-user text-purple-400 w-5"></i>
                </div>
                <div class="flex flex-col">
                  <span class="text-white font-medium">{{ training.trainer_name }}</span>
                  <span class="text-white/70 text-sm">{{ training.external_trainer_name ? 'External Trainer' : 'Internal Trainer' }}</span>
                </div>
              </div>
            </div>
          </div>
          
                     <!-- Status & Actions -->
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             <div class="flex items-center justify-between mb-3">
               <h4 class="text-lg font-semibold text-white">Status Training</h4>
               <span :class="getStatusColor(training.status)" 
                     class="px-3 py-1 rounded-full text-sm font-medium">
                 {{ training.status_text }}
               </span>
             </div>
             
                           <!-- Training Status Stats -->
              <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="text-center">
                  <div class="text-2xl font-bold text-blue-300">{{ training.invitations?.length || 0 }}</div>
                  <div class="text-xs text-white/70">Terdaftar</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-green-300">{{ training.invitations?.filter(inv => inv.status === 'attended').length || 0 }}</div>
                  <div class="text-xs text-white/70">Hadir</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-red-300">{{ training.invitations?.filter(inv => inv.status === 'absent').length || 0 }}</div>
                  <div class="text-xs text-white/70">Tidak Hadir</div>
                </div>
              </div>
             
                           <!-- Progress Bar -->
              <div class="space-y-2">
                <div class="flex justify-between text-xs text-white/70">
                  <span>Kehadiran</span>
                  <span>{{ training.invitations?.length > 0 ? Math.round((training.invitations.filter(inv => inv.status === 'attended').length / training.invitations.length) * 100) : 0 }}%</span>
                </div>
                <div class="w-full bg-white/10 rounded-full h-2">
                  <div class="bg-gradient-to-r from-green-400 to-green-500 h-2 rounded-full transition-all duration-300"
                       :style="{ width: training.invitations?.length > 0 ? (training.invitations.filter(inv => inv.status === 'attended').length / training.invitations.length) * 100 + '%' : '0%' }">
                  </div>
                </div>
              </div>
           </div>
        </div>
        
        <!-- Right Column -->
        <div class="space-y-4">
                     <!-- QR Code Section -->
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             <h4 class="text-lg font-semibold text-white mb-3">QR Code Training</h4>
                           <div class="text-center">
                <div class="bg-white p-4 rounded-lg inline-block cursor-pointer hover:scale-105 transition-transform duration-200"
                     @click="showQRCodeModal = true">
                  <div class="w-32 h-32">
                    <img v-if="qrCodeDataUrl && !qrCodeError" 
                         :src="qrCodeDataUrl" 
                         alt="QR Code" 
                         class="w-full h-full"
                         @error="handleQRCodeError"
                         @load="handleQRCodeLoad" />
                    <div v-else class="w-full h-full bg-gray-200 rounded flex items-center justify-center">
                      <div class="text-center">
                        <i class="fas fa-qrcode text-gray-400 text-4xl mb-2"></i>
                        <p class="text-gray-500 text-xs">QR Code</p>
                        <p class="text-gray-400 text-xs">Training #{{ training.id }}</p>
                      </div>
                    </div>
                  </div>
                </div>
                <p class="text-white/70 text-sm mt-2">QR Code untuk training ini</p>
                <p class="text-white/50 text-xs mt-1">Klik untuk memperbesar</p>
              </div>
           </div>
          
                     <!-- Quick Actions -->
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             <h4 class="text-lg font-semibold text-white mb-3">Aksi Cepat</h4>
             <div class="grid grid-cols-2 gap-3">
               <button v-if="canInvite" @click="$emit('invite')"
                       class="px-4 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all">
                 <i class="fas fa-user-plus mr-2"></i>
                 Undang Peserta
               </button>

             </div>
           </div>
        </div>
      </div>
      
      <!-- Participants List -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
        <h4 class="text-lg font-semibold text-white mb-3">Daftar Peserta</h4>
        <div class="overflow-x-auto">
          <table class="w-full text-white">
            <thead>
              <tr class="border-b border-white/20">
                <th class="text-left py-2 px-2 min-w-[120px]">Nama</th>
                <th class="text-left py-2 px-2 min-w-[150px]">Jabatan</th>
                <th class="text-left py-2 px-2 min-w-[180px]">Divisi</th>
                <th class="text-left py-2 px-2 min-w-[80px]">Status</th>
                <th class="text-left py-2 px-2 min-w-[100px]">Check-in</th>
                <th class="text-left py-2 px-2 min-w-[120px]">Aksi</th>
              </tr>
            </thead>
                          <tbody>
                <tr v-for="participant in training.invitations" :key="participant.id" 
                    class="border-b border-white/10">
                  <td class="py-2 px-2">{{ participant.user?.nama_lengkap || 'User tidak ditemukan' }}</td>
                  <td class="py-2 px-2">{{ participant.user?.jabatan?.nama_jabatan || '-' }}</td>
                  <td class="py-2 px-2">{{ participant.user?.divisi?.nama_divisi || '-' }}</td>
                  <td class="py-2 px-2">
                    <span :class="getParticipantStatusColor(participant.status)" 
                          class="px-2 py-1 rounded-full text-xs">
                      {{ participant.status_text }}
                    </span>
                  </td>
                  <td class="py-2 px-2">{{ participant.check_in_time_formatted || '-' }}</td>
                  <td class="py-2 px-2">
                    <div class="flex items-center space-x-2">
                      <button @click="issueCertificates"
                              class="px-2 py-1 bg-blue-500/20 border border-blue-500/30 rounded text-xs text-blue-200 hover:bg-blue-500/30 transition-colors">
                        Terbitkan Sertifikat
                      </button>
                      <button v-if="participant.status === 'invited' && canInvite" 
                              @click="markAttended(participant.id)"
                              class="px-2 py-1 bg-green-500/20 border border-green-500/30 rounded text-xs text-green-200 hover:bg-green-500/30 transition-colors">
                        Hadir
                      </button>
                      <button v-if="canInvite" 
                              @click="removeParticipant(participant.id)"
                              class="px-2 py-1 bg-red-500/20 border border-red-500/30 rounded text-xs text-red-200 hover:bg-red-500/30 transition-colors">
                        Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
          </table>
        </div>
             </div>
     </div>
     
     <!-- QR Code Modal -->
     <div v-if="showQRCodeModal" class="fixed inset-0 z-[60] flex items-center justify-center">
       <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showQRCodeModal = false"></div>
       
       <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-8 max-w-md w-full mx-4 relative">
         <!-- Close Button -->
         <button @click="showQRCodeModal = false" 
                 class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors">
           <i class="fas fa-times text-xl"></i>
         </button>
         
         <!-- QR Code Title -->
         <h4 class="text-xl font-bold text-white text-center mb-6">QR Code Training</h4>
         
         <!-- Large QR Code -->
         <div class="text-center">
           <div class="bg-white p-6 rounded-lg inline-block">
             <div class="w-64 h-64">
               <img v-if="qrCodeDataUrl && !qrCodeError" 
                    :src="qrCodeDataUrl" 
                    alt="QR Code" 
                    class="w-full h-full"
                    @error="handleQRCodeError"
                    @load="handleQRCodeLoad" />
               <div v-else class="w-full h-full bg-gray-200 rounded flex items-center justify-center">
                 <div class="text-center">
                   <i class="fas fa-qrcode text-gray-400 text-6xl mb-4"></i>
                   <p class="text-gray-500 text-sm">QR Code</p>
                   <p class="text-gray-400 text-xs">Training #{{ training.id }}</p>
                 </div>
               </div>
             </div>
           </div>
           
                       <!-- Training Info -->
            <div class="mt-6 text-white/80 text-center">
              <p class="font-medium">{{ training.course?.title || 'Training' }}</p>
              <p class="text-sm">{{ formatDate(training.scheduled_date) }}</p>
              <p class="text-xs text-white/60">{{ training.start_time }} - {{ training.end_time }}</p>
              <p class="text-xs text-white/40 mt-2">YMSoft ERP</p>
            </div>
           
           
         </div>
       </div>
     </div>
   </div>
 </template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import Swal from 'sweetalert2'
const props = defineProps({
  training: Object,
  canInvite: {
    type: Boolean,
    default: true
  },
  canEdit: {
    type: Boolean,
    default: true
  },
  availableParticipants: {
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
  levels: {
    type: Array,
    default: () => []
  },
  certificateTemplates: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'edit', 'invite', 'qr-code', 'refresh'])

const qrCodeError = ref(false)
const qrCodeDataUrl = ref('')
const showQRCodeModal = ref(false)

const getStatusColor = (status) => {
  const colors = {
    'draft': 'bg-gray-500/20 text-gray-200 border-gray-500/30',
    'published': 'bg-blue-500/20 text-blue-200 border-blue-500/30',
    'ongoing': 'bg-green-500/20 text-green-200 border-green-500/30',
    'completed': 'bg-purple-500/20 text-purple-200 border-purple-500/30',
    'cancelled': 'bg-red-500/20 text-red-200 border-red-500/30'
  }
  return colors[status] || colors['draft']
}

const getParticipantStatusColor = (status) => {
  const colors = {
    'invited': 'bg-blue-500/20 text-blue-200 border-blue-500/30',
    'confirmed': 'bg-yellow-500/20 text-yellow-200 border-yellow-500/30',
    'attended': 'bg-green-500/20 text-green-200 border-green-500/30',
    'absent': 'bg-red-500/20 text-red-200 border-red-500/30'
  }
  return colors[status] || colors['invited']
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const issueCertificates = async () => {
  const templates = props.certificateTemplates || []
  
  if (templates.length === 0) {
    Swal.fire({ 
      icon: 'warning', 
      title: 'Tidak Ada Template', 
      text: 'Buat template sertifikat terlebih dahulu di menu Certificate Templates.' 
    })
    return
  }

  // Check if course has default template
  const courseDefaultTemplate = props.training.course?.certificate_template_id
  const courseDefaultTemplateName = courseDefaultTemplate 
    ? templates.find(t => t.id == courseDefaultTemplate)?.name 
    : null

  let templateId = null
  let shouldSelectTemplate = true

  // If course has default template, show option to use it or select different one
  if (courseDefaultTemplate && courseDefaultTemplateName) {
    const useDefaultResult = await Swal.fire({
      title: 'Template Sertifikat',
      html: `
        <p class="mb-4">Course ini memiliki template default:</p>
        <p class="font-semibold text-blue-600 mb-4">${courseDefaultTemplateName}</p>
        <p>Gunakan template default atau pilih template lain?</p>
      `,
      icon: 'question',
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: 'Gunakan Default',
      denyButtonText: 'Pilih Template Lain',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#3B82F6',
      denyButtonColor: '#F59E0B',
      cancelButtonColor: '#6B7280'
    })

    if (useDefaultResult.isDismissed) return // User cancelled

    if (useDefaultResult.isConfirmed) {
      // Use default template
      templateId = courseDefaultTemplate
      shouldSelectTemplate = false
    }
    // If isDenied, proceed to template selection
  }

  // Show template selection if needed
  if (shouldSelectTemplate) {
    const templateOptions = templates.reduce((acc, template) => {
      acc[template.id] = template.name
      return acc
    }, {})

    const { value: selectedTemplateId } = await Swal.fire({
      title: 'Pilih Template Sertifikat',
      input: 'select',
      inputOptions: templateOptions,
      inputPlaceholder: 'Pilih template...',
      inputValue: courseDefaultTemplate || '', // Pre-select course default if available
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Pilih template terlebih dahulu!'
        }
      }
    })

    if (!selectedTemplateId) return
    templateId = selectedTemplateId
  }

  // Final confirmation
  const selectedTemplateName = templates.find(t => t.id == templateId)?.name || 'Template'
  const result = await Swal.fire({
    title: 'Terbitkan Sertifikat?',
    html: `
      <p class="mb-2">Sertifikat akan diterbitkan untuk peserta berstatus Hadir.</p>
      <p class="text-sm text-gray-600">Template: <span class="font-semibold">${selectedTemplateName}</span></p>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3B82F6',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Terbitkan'
  })
  if (!result.isConfirmed) return

  // Issue certificates
  const requestData = templateId ? { template_id: templateId } : {}
  
  await router.post(route('lms.schedules.issue-certificates', { schedule: props.training.id }), requestData, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Sertifikat diterbitkan.' })
      emit('refresh')
    },
    onError: (e) => {
      Swal.fire({ icon: 'error', title: 'Gagal', text: Object.values(e)[0] || 'Gagal menerbitkan' })
    }
  })
}

const markAttended = async (participantId) => {
  try {
    console.log('Mark attended called for participant:', participantId);
    console.log('Training ID:', props.training.id);
    
    const result = await Swal.fire({
      title: 'Konfirmasi Kehadiran',
      text: 'Apakah Anda yakin ingin menandai peserta ini sebagai hadir?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#10B981',
      cancelButtonColor: '#6B7280',
      confirmButtonText: 'Ya, Tandai Hadir',
      cancelButtonText: 'Batal'
    })

    if (result.isConfirmed) {
      console.log('User confirmed, calling API...');
      // Call backend API to mark as attended
      await router.put(route('lms.schedules.mark-attended', {
        schedule: props.training.id,
        invitation: participantId
      }), {}, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Peserta berhasil ditandai sebagai hadir',
            timer: 2000,
            showConfirmButton: false
          })
          // Refresh the page to update status
          window.location.reload()
        },
        onError: (errors) => {
          console.error('API Error for mark attended:', errors);
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: 'Gagal menandai peserta sebagai hadir: ' + Object.values(errors)[0]
          })
        }
      })
    }
  } catch (error) {
    console.error('Error marking attended:', error)
    console.error('Error details:', {
      message: error.message,
      stack: error.stack,
      name: error.name
    })
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menandai peserta sebagai hadir'
    })
  }
}

const removeParticipant = async (participantId) => {
  try {
    console.log('Remove participant called for participant:', participantId);
    console.log('Training ID:', props.training.id);
    
    const result = await Swal.fire({
      title: 'Konfirmasi Hapus',
      text: 'Apakah Anda yakin ingin menghapus peserta ini dari training?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#EF4444',
      cancelButtonColor: '#6B7280',
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal'
    })

    if (result.isConfirmed) {
      console.log('User confirmed, calling API...');
      await router.delete(route('lms.schedules.remove-participant', {
        schedule: props.training.id,
        invitation: participantId
      }), {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Peserta berhasil dihapus dari training',
            timer: 2000,
            showConfirmButton: false
          })
          // Refresh the page to update data
          window.location.reload()
        },
        onError: (errors) => {
          console.error('API Error for remove participant:', errors);
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: 'Gagal menghapus peserta: ' + Object.values(errors)[0]
          })
        }
      })
    }
  } catch (error) {
    console.error('Error removing participant:', error)
    console.error('Error details:', {
      message: error.message,
      stack: error.stack,
      name: error.name
    })
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menghapus peserta'
    })
  }
}



const handleQRCodeError = (event) => {
  console.error('QR Code failed to load:', event.target.src)
  qrCodeError.value = true
}

const handleQRCodeLoad = (event) => {
  console.log('QR Code loaded successfully:', event.target.src)
}



const generateQRCode = async () => {
  try {
    if (props.training) {
      const data = {
        schedule_id: props.training.id,
        course_id: props.training.course_id,
        scheduled_date: props.training.scheduled_date,
        hash: btoa(props.training.id + props.training.course_id + props.training.scheduled_date)
      }
      
      const qrData = JSON.stringify(data)
      qrCodeDataUrl.value = await QRCode.toDataURL(qrData, {
        width: 300,
        margin: 2,
        color: {
          dark: '#000000',
          light: '#FFFFFF'
        }
      })
      
      console.log('QR Code generated successfully:', qrCodeDataUrl.value)
    }
  } catch (error) {
    console.error('Error generating QR code:', error)
    qrCodeError.value = true
  }
}

onMounted(() => {
  generateQRCode()
  
  // Debug: Log training data
  console.log('Training data:', props.training)
  console.log('Invitations:', props.training?.invitations)
  console.log('Participant count:', props.training?.invitations?.length)
  console.log('Attended count:', props.training?.invitations?.filter(inv => inv.status === 'attended').length)
  console.log('Absent count:', props.training?.invitations?.filter(inv => inv.status === 'absent').length)
})
</script>
