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
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 mb-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
              <Link :href="route('lms.schedules.index')" 
                    class="px-4 py-2 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>
                Kembali ke Jadwal
              </Link>
              <h1 class="text-3xl font-bold text-white">Detail Training</h1>
            </div>
            
            <div class="flex items-center space-x-4">
              <!-- Edit Button -->
              <button v-if="canEdit" @click="editSchedule" 
                      class="px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-blue-700 transition-all">
                <i class="fas fa-edit mr-2"></i>
                Edit Training
              </button>
            </div>
          </div>
        </div>

        <!-- Training Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Content -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Course Information -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6">
              <h2 class="text-2xl font-bold text-white mb-4">
                <i class="fas fa-book mr-2"></i>
                Informasi Course
              </h2>
              <div class="space-y-4">
                <div>
                  <label class="text-white/70 text-sm font-medium">Judul Course</label>
                  <p class="text-white text-lg font-semibold">{{ schedule.course?.title }}</p>
                </div>
                <div v-if="schedule.course?.description">
                  <label class="text-white/70 text-sm font-medium">Deskripsi</label>
                  <p class="text-white/90">{{ schedule.course.description }}</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="text-white/70 text-sm font-medium">Tipe Course</label>
                    <span :class="schedule.course?.course_type === 'mandatory' ? 'bg-red-500' : 'bg-blue-500'" 
                          class="inline-block px-3 py-1 rounded-full text-white text-sm font-semibold">
                      {{ schedule.course?.course_type === 'mandatory' ? 'Mandatory' : 'Optional' }}
                    </span>
                  </div>
                  <div>
                    <label class="text-white/70 text-sm font-medium">Durasi</label>
                    <p class="text-white">{{ schedule.course?.duration || '-' }} menit</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Training Schedule -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6">
              <h2 class="text-2xl font-bold text-white mb-4">
                <i class="fas fa-calendar-alt mr-2"></i>
                Jadwal Training
              </h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="text-white/70 text-sm font-medium">Tanggal</label>
                  <p class="text-white text-lg font-semibold">{{ formatDate(schedule.scheduled_date) }}</p>
                </div>
                <div>
                  <label class="text-white/70 text-sm font-medium">Waktu</label>
                  <p class="text-white text-lg font-semibold">{{ schedule.start_time }} - {{ schedule.end_time }}</p>
                </div>
                <div>
                  <label class="text-white/70 text-sm font-medium">Lokasi</label>
                  <p class="text-white">{{ schedule.outlet?.nama_outlet || 'Head Office' }}</p>
                </div>
                <div>
                  <label class="text-white/70 text-sm font-medium">Status</label>
                  <span :class="getStatusColor(schedule.status)" 
                        class="inline-block px-3 py-1 rounded-full text-white text-sm font-semibold">
                    {{ getStatusText(schedule.status) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Trainers -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6">
              <h2 class="text-2xl font-bold text-white mb-4">
                <i class="fas fa-chalkboard-teacher mr-2"></i>
                Trainer
              </h2>
              <div v-if="schedule.schedule_trainers && schedule.schedule_trainers.length > 0" class="space-y-3">
                <div v-for="trainer in schedule.schedule_trainers" :key="trainer.id" 
                     class="flex items-center justify-between p-4 bg-white/5 rounded-xl border border-white/10">
                  <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                      <i class="fas fa-user text-white"></i>
                    </div>
                    <div>
                      <p class="text-white font-semibold">{{ trainer.trainer?.nama_lengkap || trainer.external_trainer_name }}</p>
                      <p class="text-white/70 text-sm">
                        {{ trainer.trainer_type === 'internal' ? 'Internal Trainer' : 'External Trainer' }}
                        <span v-if="trainer.is_primary_trainer" class="ml-2 px-2 py-1 bg-yellow-500 text-black text-xs rounded-full font-semibold">
                          Primary
                        </span>
                      </p>
                      <p v-if="trainer.trainer?.jabatan" class="text-white/60 text-xs">
                        {{ trainer.trainer.jabatan.nama_jabatan }} - {{ trainer.trainer.divisi?.nama_divisi }}
                      </p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-white/70 text-sm">Jam Training</p>
                    <p class="text-white font-semibold">{{ trainer.hours || 0 }} jam</p>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8">
                <i class="fas fa-user-slash text-white/50 text-4xl mb-4"></i>
                <p class="text-white/70">Belum ada trainer yang ditugaskan</p>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Participants -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6">
              <h2 class="text-xl font-bold text-white mb-4">
                <i class="fas fa-users mr-2"></i>
                Peserta
              </h2>
              <div v-if="schedule.invitations && schedule.invitations.length > 0" class="space-y-2">
                <div v-for="invitation in schedule.invitations.slice(0, 5)" :key="invitation.id" 
                     class="flex items-center space-x-3 p-2 bg-white/5 rounded-lg">
                  <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-white text-xs"></i>
                  </div>
                  <div class="flex-1">
                    <p class="text-white text-sm font-medium">{{ invitation.user?.nama_lengkap }}</p>
                    <p class="text-white/60 text-xs">{{ invitation.user?.jabatan?.nama_jabatan }}</p>
                  </div>
                  <span :class="getInvitationStatusColor(invitation.status)" 
                        class="px-2 py-1 rounded-full text-xs font-semibold">
                    {{ getInvitationStatusText(invitation.status) }}
                  </span>
                </div>
                <div v-if="schedule.invitations.length > 5" class="text-center pt-2">
                  <p class="text-white/70 text-sm">
                    dan {{ schedule.invitations.length - 5 }} peserta lainnya
                  </p>
                </div>
              </div>
              <div v-else class="text-center py-4">
                <i class="fas fa-user-plus text-white/50 text-2xl mb-2"></i>
                <p class="text-white/70 text-sm">Belum ada peserta yang diundang</p>
              </div>
            </div>

            <!-- Quick Actions -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6">
              <h2 class="text-xl font-bold text-white mb-4">
                <i class="fas fa-bolt mr-2"></i>
                Quick Actions
              </h2>
              <div class="space-y-3">
                <button v-if="canInvite" @click="inviteParticipants" 
                        class="w-full px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg font-semibold hover:from-green-600 hover:to-green-700 transition-all">
                  <i class="fas fa-user-plus mr-2"></i>
                  Undang Peserta
                </button>
                <button @click="inviteTrainers" 
                        class="w-full px-4 py-2 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg font-semibold hover:from-purple-600 hover:to-purple-700 transition-all">
                  <i class="fas fa-chalkboard-teacher mr-2"></i>
                  Undang Trainer
                </button>
                <button @click="viewQRCode" 
                        class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg font-semibold hover:from-blue-600 hover:to-blue-700 transition-all">
                  <i class="fas fa-qrcode mr-2"></i>
                  QR Code
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <InvitationModal 
      v-if="showInvitationModal" 
      :schedule="schedule" 
      @close="showInvitationModal = false" 
      @success="handleInvitationSuccess" 
    />
    
    <TrainerInvitationModal 
      v-if="showTrainerModal" 
      :schedule="schedule" 
      @close="showTrainerModal = false" 
      @success="handleTrainerSuccess" 
    />
    
    <QRCodeModal 
      v-if="showQRModal" 
      :schedule="schedule" 
      @close="showQRModal = false" 
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InvitationModal from './InvitationModal.vue'
import TrainerInvitationModal from './TrainerInvitationModal.vue'
import QRCodeModal from './QRCodeModal.vue'

const props = defineProps({
  schedule: Object,
  canEdit: Boolean,
  canInvite: Boolean,
  certificateTemplates: Array
})

// Reactive data
const showInvitationModal = ref(false)
const showTrainerModal = ref(false)
const showQRModal = ref(false)

// Methods
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const getStatusColor = (status) => {
  const colors = {
    'scheduled': 'bg-blue-500',
    'ongoing': 'bg-yellow-500',
    'completed': 'bg-green-500',
    'cancelled': 'bg-red-500'
  }
  return colors[status] || 'bg-gray-500'
}

const getStatusText = (status) => {
  const texts = {
    'scheduled': 'Terjadwal',
    'ongoing': 'Berlangsung',
    'completed': 'Selesai',
    'cancelled': 'Dibatalkan'
  }
  return texts[status] || status
}

const getInvitationStatusColor = (status) => {
  const colors = {
    'invited': 'bg-yellow-500',
    'confirmed': 'bg-green-500',
    'attended': 'bg-blue-500',
    'declined': 'bg-red-500'
  }
  return colors[status] || 'bg-gray-500'
}

const getInvitationStatusText = (status) => {
  const texts = {
    'invited': 'Diundang',
    'confirmed': 'Konfirmasi',
    'attended': 'Hadir',
    'declined': 'Tolak'
  }
  return texts[status] || status
}

const editSchedule = () => {
  router.visit(route('lms.schedules.edit', props.schedule.id))
}

const inviteParticipants = () => {
  showInvitationModal.value = true
}

const inviteTrainers = () => {
  showTrainerModal.value = true
}

const viewQRCode = () => {
  showQRModal.value = true
}

const handleInvitationSuccess = () => {
  showInvitationModal.value = false
  // Refresh the page to show updated data
  router.reload()
}

const handleTrainerSuccess = () => {
  showTrainerModal.value = false
  // Refresh the page to show updated data
  router.reload()
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
</style>
