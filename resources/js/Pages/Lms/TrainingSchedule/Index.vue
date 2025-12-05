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
              <Link :href="route('lms.courses.index')" 
                    class="px-4 py-2 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>
                Kembali ke Training
              </Link>
              <h1 class="text-3xl font-bold text-white">Jadwal Training</h1>
            </div>
            
            <div class="flex items-center space-x-4">
              <!-- Month Navigation -->
              <div class="flex items-center space-x-2">
                <button @click="goPrevious" class="p-2 bg-white/20 rounded-lg hover:bg-white/30 transition-all">
                  <i class="fas fa-chevron-left text-white"></i>
                </button>
                <span class="text-xl font-bold text-white px-4">{{ headerTitle }}</span>
                <button @click="goNext" class="p-2 bg-white/20 rounded-lg hover:bg-white/30 transition-all">
                  <i class="fas fa-chevron-right text-white"></i>
                </button>
              </div>
              
              <!-- View Toggle -->
              <div class="flex bg-white/10 rounded-lg p-1">
                <button v-for="view in views" :key="view"
                        @click="currentView = view"
                        :class="currentView === view ? 'bg-white/20 text-white' : 'text-white/70'"
                        class="px-3 py-1 rounded-md transition-all">
                  {{ view }}
                </button>
              </div>
              
              <!-- Quick Schedule Button -->
              <button v-if="canCreateSchedule" @click="openQuickSchedule" 
                      class="px-6 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl font-semibold hover:from-green-600 hover:to-green-700 transition-all">
                <i class="fas fa-plus mr-2"></i>
                Jadwalkan Training
              </button>
            </div>
          </div>
        </div>

        <!-- Calendar Container -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6">
          <!-- Month View -->
          <template v-if="currentView === 'Month'">
            <!-- Week Days Header -->
            <div class="grid grid-cols-7 gap-2 mb-4">
              <div v-for="day in weekDays" :key="day" 
                   class="text-center text-white/70 font-semibold py-2">
                {{ day }}
              </div>
            </div>
            <!-- Calendar Days -->
            <div class="grid grid-cols-7 gap-2">
              <div v-for="date in calendarDates" :key="date.date"
                   @click="selectDate(date)"
                   :class="getDateClasses(date)"
                   class="min-h-32 p-2 rounded-lg cursor-pointer transition-all hover:bg-white/10">
                <!-- Date Number -->
                <div class="text-right mb-2 relative">
                  <span :class="getDateNumberClasses(date)" class="text-sm font-semibold">
                    {{ date.dayNumber }}
                  </span>
                  <!-- Holiday Indicator -->
                  <div v-if="date.isHoliday" class="absolute -top-1 -right-1">
                    <i class="fas fa-calendar-times text-red-400 text-xs" 
                       :title="getHolidayDescription(date.date)"></i>
                  </div>
                </div>
                <!-- Training Events -->
                <div class="space-y-1">
                  <div v-for="training in getTrainingsForDate(date.date)" :key="training.id"
                       @click.stop="openTrainingDetail(training)"
                       :class="getTrainingColor(training.status)"
                       class="px-2 py-1 rounded text-xs text-white font-medium truncate hover:bg-white/20 transition-all">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-2">
                        <span class="truncate">{{ training.course?.title || 'Training' }}</span>
                        <span v-if="training.course?.type" :class="{
                          'px-1 py-0.5 text-xs rounded-full': true,
                          'bg-blue-500/20 text-blue-200 border border-blue-500/30': training.course.type === 'online',
                          'bg-green-500/20 text-green-200 border border-green-500/30': training.course.type === 'offline'
                        }">
                          <i :class="{
                            'fas fa-video': training.course.type === 'online',
                            'fas fa-users': training.course.type === 'offline'
                          }"></i>
                        </span>
                      </div>
                      <div class="flex items-center space-x-1">
                        <span class="text-xs opacity-75">{{ getParticipantCount(training) }} peserta</span>
                        <button @click.stop="showQRCode(training)" 
                                class="text-xs opacity-75 hover:opacity-100 transition-opacity"
                                title="Lihat QR Code">
                          <i class="fas fa-qrcode"></i>
                        </button>
                      </div>
                    </div>
                    <div class="text-xs opacity-75 mt-1">
                      {{ training.start_time }} - {{ training.end_time }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Week View -->
          <template v-else-if="currentView === 'Week'">
            <div class="grid grid-cols-7 gap-2 mb-4">
              <div v-for="d in weekDays" :key="d" class="text-center text-white/70 font-semibold py-2">{{ d }}</div>
            </div>
            <div class="grid grid-cols-7 gap-2">
              <div v-for="date in currentWeekDates" :key="date.date" class="min-h-32 p-2 rounded-lg bg-white/5 border border-white/10">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-semibold text-white">{{ date.dayNumber }}</span>
                </div>
                <div class="space-y-1">
                  <div v-for="training in getTrainingsForDate(date.date)" :key="training.id"
                       @click.stop="openTrainingDetail(training)"
                       :class="getTrainingColor(training.status)"
                       class="px-2 py-1 rounded text-xs text-white font-medium truncate hover:bg-white/20 transition-all">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-2">
                        <span class="truncate">{{ training.course?.title || 'Training' }}</span>
                        <span v-if="training.course?.type" :class="{
                          'px-1 py-0.5 text-xs rounded-full': true,
                          'bg-blue-500/20 text-blue-200 border border-blue-500/30': training.course.type === 'online',
                          'bg-green-500/20 text-green-200 border border-green-500/30': training.course.type === 'offline'
                        }">
                          <i :class="{
                            'fas fa-video': training.course.type === 'online',
                            'fas fa-users': training.course.type === 'offline'
                          }"></i>
                        </span>
                      </div>
                      <span class="text-xs opacity-75">{{ getParticipantCount(training) }} peserta</span>
                    </div>
                    <div class="text-xs opacity-75 mt-1">{{ training.start_time }} - {{ training.end_time }}</div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Day View -->
          <template v-else-if="currentView === 'Day'">
            <div class="text-white/80 mb-3">{{ selectedDateLabel }}</div>
            <div class="space-y-2">
              <div v-for="training in getTrainingsForDate(selectedDateForSchedule || todayDate)" :key="training.id"
                   @click.stop="openTrainingDetail(training)"
                   :class="getTrainingColor(training.status)"
                   class="px-3 py-2 rounded text-sm text-white font-medium hover:bg-white/20 transition-all border border-white/10">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <span class="truncate">{{ training.course?.title || 'Training' }}</span>
                    <span v-if="training.course?.type" :class="{
                      'px-1 py-0.5 text-xs rounded-full': true,
                      'bg-blue-500/20 text-blue-200 border border-blue-500/30': training.course.type === 'online',
                      'bg-green-500/20 text-green-200 border border-green-500/30': training.course.type === 'offline'
                    }">
                      <i :class="{
                        'fas fa-video': training.course.type === 'online',
                        'fas fa-users': training.course.type === 'offline'
                      }"></i>
                    </span>
                  </div>
                  <span class="text-xs opacity-75">{{ getParticipantCount(training) }} peserta</span>
                </div>
                <div class="text-xs opacity-75 mt-1">{{ training.start_time }} - {{ training.end_time }}</div>
              </div>
              <div v-if="getTrainingsForDate(selectedDateForSchedule || todayDate).length === 0" class="text-white/60">Tidak ada training.</div>
            </div>
          </template>

          <!-- List View -->
          <template v-else>
            <div class="space-y-2">
              <div v-for="item in schedulesSorted" :key="item.id" 
                   @click.stop="openTrainingDetail(item)"
                   :class="getTrainingColor(item.status)"
                   class="px-3 py-2 rounded text-sm text-white font-medium hover:bg-white/20 transition-all border border-white/10">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-3">
                    <span class="text-xs opacity-75">{{ item.scheduled_date }}</span>
                    <div class="flex items-center space-x-2">
                      <span class="truncate">{{ item.course?.title || 'Training' }}</span>
                      <span v-if="item.course?.type" :class="{
                        'px-1 py-0.5 text-xs rounded-full': true,
                        'bg-blue-500/20 text-blue-200 border border-blue-500/30': item.course.type === 'online',
                        'bg-green-500/20 text-green-200 border border-green-500/30': item.course.type === 'offline'
                      }">
                        <i :class="{
                          'fas fa-video': item.course.type === 'online',
                          'fas fa-users': item.course.type === 'offline'
                        }"></i>
                      </span>
                    </div>
                  </div>
                  <span class="text-xs opacity-75">{{ getParticipantCount(item) }} peserta • {{ item.start_time }} - {{ item.end_time }}</span>
                </div>
              </div>
              <div v-if="schedulesSorted.length === 0" class="text-white/60">Belum ada training.</div>
            </div>
          </template>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-xl p-4">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar text-blue-400"></i>
              </div>
              <div>
                <div class="text-2xl font-bold text-white">{{ totalSchedules }}</div>
                <div class="text-sm text-white/70">Total Training</div>
              </div>
            </div>
          </div>
          
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-xl p-4">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-play text-green-400"></i>
              </div>
              <div>
                <div class="text-2xl font-bold text-white">{{ ongoingSchedules }}</div>
                <div class="text-sm text-white/70">Sedang Berlangsung</div>
              </div>
            </div>
          </div>
          
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-xl p-4">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-check text-purple-400"></i>
              </div>
              <div>
                <div class="text-2xl font-bold text-white">{{ completedSchedules }}</div>
                <div class="text-sm text-white/70">Selesai</div>
              </div>
            </div>
          </div>
          
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-xl p-4">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-yellow-400"></i>
              </div>
              <div>
                <div class="text-2xl font-bold text-white">{{ totalParticipants }}</div>
                <div class="text-sm text-white/70">Total Peserta</div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Calendar Legend -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-xl p-4 mt-6">
          <h3 class="text-white font-medium mb-3">Keterangan Kalender</h3>
          <div class="flex flex-wrap items-center gap-6">
            <div class="flex items-center gap-2">
              <div class="w-4 h-4 bg-blue-500/20 border border-blue-500/30 rounded"></div>
              <span class="text-white/70 text-sm">Hari Ini</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-4 h-4 bg-red-500/20 border border-red-500/30 rounded"></div>
              <span class="text-white/70 text-sm">Hari Libur</span>
            </div>
            <div class="flex items-center gap-2">
              <i class="fas fa-calendar-times text-red-400 text-sm"></i>
              <span class="text-white/70 text-sm">Indikator Libur (hover untuk detail)</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-4 h-4 bg-green-500/20 border border-green-500/30 rounded"></div>
              <span class="text-white/70 text-sm">Training Sedang Berlangsung</span>
            </div>
          </div>
        </div>
      </div>

                     <!-- Training Detail Modal -->
        <TrainingDetailModal 
          v-if="selectedTraining"
          :training="selectedTraining"
          :available-participants="availableParticipants"
          :divisions="divisions"
          :jabatans="jabatans"
          :levels="levels"
          :can-invite="true"
          :can-edit="true"
          @close="selectedTraining = null"
          @edit="editTraining"
          @invite="openInviteModal"
          @invite-trainer="openTrainerInviteModal"
          @qr-code="showQRCode"
        />

       <!-- Invitation Modal -->
       <InvitationModal 
         v-if="showInviteModal"
         :training="selectedTraining"
         :divisions="divisions"
         :jabatans="jabatans"
         :levels="levels"
         :invited-participants="selectedTraining?.invitations || []"
         @close="showInviteModal = false"
         @invited="refreshCalendar"
       />

       <!-- Trainer Invitation Modal -->
       <TrainerInvitationModal 
         v-if="showTrainerInviteModal"
         :training="selectedTraining"
         :available-trainers="availableTrainers"
         :divisions="divisions"
         :jabatans="jabatans"
         :invited-trainers="selectedTraining?.scheduleTrainers || []"
         @close="showTrainerInviteModal = false"
         @invited="refreshCalendar"
       />

               <!-- Quick Schedule Modal -->
        <QuickScheduleModal 
          v-if="showQuickSchedule"
          :courses="courses"
          :outlets="outlets"
          :selected-date="selectedDateForSchedule"
          @close="closeQuickSchedule"
          @created="refreshCalendar"
        />

        <!-- QR Code Modal -->
        <QRCodeModal 
          v-if="showQRCodeModal"
          :training="selectedQRTraining"
          @close="showQRCodeModal = false"
        />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import TrainingDetailModal from './TrainingDetailModal.vue'
import QuickScheduleModal from './QuickScheduleModal.vue'
import InvitationModal from './InvitationModal.vue'
import TrainerInvitationModal from './TrainerInvitationModal.vue'
import QRCodeModal from './QRCodeModal.vue'

const props = defineProps({
  schedules: Array,
  canCreateSchedule: Boolean,
  currentMonth: Number,
  currentYear: Number,
  availableParticipants: Array,
  availableTrainers: Array,
  divisions: Array,
  jabatans: Array,
  levels: Array,
  courses: Array,
  outlets: Array,
  holidays: Array
})

const currentView = ref('Month')
const currentDate = ref(new Date(props.currentYear, props.currentMonth - 1, 1))
const selectedTraining = ref(null)
const showQuickSchedule = ref(false)
const showInviteModal = ref(false)
const showTrainerInviteModal = ref(false)
const showQRCodeModal = ref(false)
const selectedQRTraining = ref(null)
const selectedDateForSchedule = ref('')

const views = ['Month', 'Week', 'Day', 'List']

const weekDays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']

const currentMonthYear = computed(() => {
  return currentDate.value.toLocaleDateString('id-ID', { 
    month: 'long', 
    year: 'numeric' 
  })
})

const headerTitle = computed(() => {
  if (currentView.value === 'Month') return currentMonthYear.value
  if (currentView.value === 'Week') {
    const week = currentWeekDates.value
    return `${formatDateLabel(week[0]?.date)} – ${formatDateLabel(week[6]?.date)}`
  }
  if (currentView.value === 'Day') return formatDateLabel(selectedDateForSchedule.value || todayDate.value)
  return `${currentMonthYear.value}`
})

const calendarDates = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startDate = new Date(firstDay)
  startDate.setDate(startDate.getDate() - firstDay.getDay())
  
  const dates = []
  const current = new Date(startDate)
  
  while (current.getMonth() <= month && dates.length < 42) {
    // Hindari toISOString() karena akan bergeser ke hari sebelumnya di zona WIB
    const y = current.getFullYear()
    const m = String(current.getMonth() + 1).padStart(2, '0')
    const d = String(current.getDate()).padStart(2, '0')
    dates.push({
      date: `${y}-${m}-${d}`,
      dayNumber: current.getDate(),
      isCurrentMonth: current.getMonth() === month,
      isToday: current.toDateString() === new Date().toDateString(),
      isPast: current < new Date(new Date().setHours(0, 0, 0, 0)),
      isHoliday: isHoliday(`${y}-${m}-${d}`)
    })
    current.setDate(current.getDate() + 1)
  }
  
  return dates
})

const todayDate = computed(() => {
  const t = new Date()
  const y = t.getFullYear(); const m = String(t.getMonth() + 1).padStart(2, '0'); const d = String(t.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
})

const currentWeekDates = computed(() => {
  const base = selectedDateForSchedule.value ? new Date(selectedDateForSchedule.value) : new Date()
  const start = new Date(base)
  start.setDate(start.getDate() - start.getDay())
  const days = []
  for (let i = 0; i < 7; i++) {
    const dt = new Date(start)
    dt.setDate(start.getDate() + i)
    const y = dt.getFullYear(); const m = String(dt.getMonth() + 1).padStart(2, '0'); const d = String(dt.getDate()).padStart(2, '0')
    days.push({ date: `${y}-${m}-${d}`, dayNumber: dt.getDate() })
  }
  return days
})

const schedulesSorted = computed(() => {
  return [...props.schedules].sort((a, b) => {
    const aKey = `${a.scheduled_date} ${a.start_time}`
    const bKey = `${b.scheduled_date} ${b.start_time}`
    return aKey.localeCompare(bKey)
  })
})

// Helper function to check if a date is a holiday
const isHoliday = (dateString) => {
  return props.holidays.some(holiday => holiday.date === dateString)
}

// Helper function to get holiday description
const getHolidayDescription = (dateString) => {
  const holiday = props.holidays.find(h => h.date === dateString)
  return holiday ? holiday.description : null
}

const formatDateLabel = (dateStr) => {
  if (!dateStr) return ''
  const [y, m, d] = dateStr.split('-')
  return `${d}/${m}/${y}`
}

const totalSchedules = computed(() => props.schedules.length)
const ongoingSchedules = computed(() => props.schedules.filter(s => s.status === 'ongoing').length)
const completedSchedules = computed(() => props.schedules.filter(s => s.status === 'completed').length)
const totalParticipants = computed(() => {
  if (!Array.isArray(props.schedules)) return 0
  return props.schedules.reduce((total, schedule) => {
    const count = typeof schedule.participant_count === 'number'
      ? schedule.participant_count
      : (Array.isArray(schedule.invitations) ? schedule.invitations.length : 0)
    return total + (Number.isFinite(count) ? count : 0)
  }, 0)
})

const getDateClasses = (date) => {
  const classes = ['border border-white/10']
  if (date.isHoliday) classes.push('bg-red-500/20 border-red-500/30')
  else if (date.isToday) classes.push('bg-blue-500/20 border-blue-500/30')
  else if (date.isCurrentMonth) classes.push('bg-white/5')
  if (!date.isCurrentMonth) classes.push('opacity-50')
  return classes.join(' ')
}

const getDateNumberClasses = (date) => {
  const classes = []
  if (date.isHoliday) classes.push('text-red-300 font-bold')
  else if (date.isToday) classes.push('text-blue-300 font-bold')
  else if (date.isCurrentMonth) classes.push('text-white')
  else classes.push('text-white/50')
  return classes.join(' ')
}

const getTrainingColor = (status) => {
  const colors = {
    'draft': 'bg-gray-500/20 border-gray-500/30',
    'published': 'bg-blue-500/20 border-blue-500/30',
    'ongoing': 'bg-green-500/20 border-green-500/30',
    'completed': 'bg-purple-500/20 border-purple-500/30',
    'cancelled': 'bg-red-500/20 border-red-500/30'
  }
  return colors[status] || colors['draft']
}

const getParticipantCount = (training) => {
  if (typeof training.participant_count === 'number') return training.participant_count
  return Array.isArray(training.invitations) ? training.invitations.length : 0
}

const getTrainingsForDate = (date) => {
  console.log('Looking for trainings on date:', date)
  console.log('Available schedules:', props.schedules)
  
  return props.schedules.filter(schedule => {
    // Convert schedule date to YYYY-MM-DD format for comparison
    const scheduleDate = schedule.scheduled_date ? schedule.scheduled_date.split('T')[0] : null
    console.log('Schedule date (formatted):', scheduleDate, 'Looking for:', date, 'Match:', scheduleDate === date)
    
    // Also check if the date is in the same month and year
    if (scheduleDate) {
      const scheduleDateObj = new Date(scheduleDate)
      const targetDateObj = new Date(date)
      const sameMonth = scheduleDateObj.getMonth() === targetDateObj.getMonth()
      const sameYear = scheduleDateObj.getFullYear() === targetDateObj.getFullYear()
      console.log('Same month:', sameMonth, 'Same year:', sameYear)
    }
    
    return scheduleDate === date
  })
}

const selectDate = (date) => {
  if (date.isCurrentMonth) {
    // Simpan tanggal yang dipilih dan buka modal
    selectedDateForSchedule.value = date.date
    showQuickSchedule.value = true
  }
}

const openTrainingDetail = (training) => {
  selectedTraining.value = training
}

const openQuickSchedule = () => {
  // Reset tanggal ke hari ini jika tidak ada tanggal yang dipilih
  selectedDateForSchedule.value = ''
  showQuickSchedule.value = true
}

const closeQuickSchedule = () => {
  showQuickSchedule.value = false
  selectedDateForSchedule.value = ''
}

const onTrainingCreated = () => {
  // Refresh the page to get updated data
  window.location.reload()
}

const goPrevious = () => {
  if (currentView.value === 'Month' || currentView.value === 'List') {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
    router.get(route('lms.schedules.index'), { year: currentDate.value.getFullYear(), month: currentDate.value.getMonth() + 1 })
  } else if (currentView.value === 'Week' || currentView.value === 'Day') {
    const base = selectedDateForSchedule.value ? new Date(selectedDateForSchedule.value) : new Date()
    base.setDate(base.getDate() - (currentView.value === 'Day' ? 1 : 7))
    const y = base.getFullYear(); const m = String(base.getMonth() + 1).padStart(2, '0'); const d = String(base.getDate()).padStart(2, '0')
    selectedDateForSchedule.value = `${y}-${m}-${d}`
  }
}

const goNext = () => {
  if (currentView.value === 'Month' || currentView.value === 'List') {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
    router.get(route('lms.schedules.index'), { year: currentDate.value.getFullYear(), month: currentDate.value.getMonth() + 1 })
  } else if (currentView.value === 'Week' || currentView.value === 'Day') {
    const base = selectedDateForSchedule.value ? new Date(selectedDateForSchedule.value) : new Date()
    base.setDate(base.getDate() + (currentView.value === 'Day' ? 1 : 7))
    const y = base.getFullYear(); const m = String(base.getMonth() + 1).padStart(2, '0'); const d = String(base.getDate()).padStart(2, '0')
    selectedDateForSchedule.value = `${y}-${m}-${d}`
  }
}

const editTraining = (training) => {
  router.visit(route('lms.schedules.edit', training.id))
}

const openInviteModal = (training) => {
  showInviteModal.value = true
}

const openTrainerInviteModal = (training) => {
  showTrainerInviteModal.value = true
}

const showQRCode = (training) => {
  selectedQRTraining.value = training
  showQRCodeModal.value = true
}

const refreshCalendar = () => {
  // Add small delay to ensure modal is closed first
  setTimeout(() => {
    window.location.reload()
  }, 100)
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
