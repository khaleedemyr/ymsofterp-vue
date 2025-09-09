<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  outlets: Array,
  divisions: Array,
  users: Array,
  calendar: Object,
  filter: Object,
  holidays: Array,
  absentCalendar: Object,
});

const outletId = ref(props.filter?.outlet_id || '');
const divisionId = ref(props.filter?.division_id || '');
const month = ref(props.filter?.month || new Date().getMonth() + 1);
const year = ref(props.filter?.year || new Date().getFullYear());

const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

const shifts = ref([]);
const activeTooltip = ref(null);
const isLoading = ref(false);
const hasLoaded = ref(false);

const holidays = computed(() => Array.isArray(props.holidays) ? props.holidays : []);
function isHoliday(date) {
  const d = formatDateLocal(date);
  return holidays.value.find(h => h.date === d);
}

function showTooltip(key) { activeTooltip.value = key; }
function hideTooltip() { activeTooltip.value = null; }
function getDayName(dateStr) {
  const d = new Date(dateStr);
  return days[d.getDay()];
}

function formatDateLocal(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function reload() {
  isLoading.value = true;
  hasLoaded.value = true;
  router.get('/user-shifts/calendar', {
    outlet_id: outletId.value,
    division_id: divisionId.value,
    month: month.value,
    year: year.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false; },
  });
}

function prevMonth() {
  if (month.value === 1) {
    month.value = 12;
    year.value--;
  } else {
    month.value--;
  }
  if (hasLoaded.value) {
    reload();
  }
}
function nextMonth() {
  if (month.value === 12) {
    month.value = 1;
    year.value++;
  } else {
    month.value++;
  }
  if (hasLoaded.value) {
    reload();
  }
}

const calendarGrid = computed(() => {
  const firstDay = new Date(year.value, month.value - 1, 1);
  const lastDay = new Date(year.value, month.value, 0);
  const startDay = firstDay.getDay();
  const daysInMonth = lastDay.getDate();
  const grid = [];
  let day = 1;
  for (let row = 0; row < 6; row++) {
    const week = [];
    for (let col = 0; col < 7; col++) {
      if ((row === 0 && col < startDay) || day > daysInMonth) {
        week.push(null);
      } else {
        week.push(new Date(year.value, month.value - 1, day));
        day++;
      }
    }
    grid.push(week);
  }
  return grid;
});

function getShiftsForDay(dateStr) {
  const dayData = props.calendar?.[dateStr] || {};
  return Object.values(dayData);
}

function getAbsentForDay(dateStr) {
  return props.absentCalendar?.[dateStr] || [];
}

function isUserAbsent(dateStr, userId) {
  const absentData = getAbsentForDay(dateStr);
  return absentData.some(absent => absent.user_id === userId);
}

function getAbsentInfo(dateStr, userId) {
  const absentData = getAbsentForDay(dateStr);
  return absentData.find(absent => absent.user_id === userId);
}

function getBadgeColor(shiftName, isAbsent = false) {
  if (isAbsent) return 'bg-red-500 text-white border-2 border-red-600';
  if (!shiftName) return 'bg-gray-300 text-gray-600';
  if (shiftName.toLowerCase().includes('off')) return 'bg-gray-400 text-white';
  if (shiftName.toLowerCase().includes('malam')) return 'bg-blue-600 text-white';
  if (shiftName.toLowerCase().includes('pagi')) return 'bg-yellow-400 text-gray-900';
  if (shiftName.toLowerCase().includes('siang')) return 'bg-green-400 text-white';
  return 'bg-blue-200 text-blue-900';
}

function exportExcel() {
  const params = {
    outlet_id: outletId.value,
    division_id: divisionId.value,
    month: month.value,
    year: year.value,
  };
  const query = Object.entries(params).map(([k, v]) => `${k}=${encodeURIComponent(v||'')}`).join('&');
  window.open(`/user-shifts/calendar/export-excel?${query}`, '_blank');
}
</script>

<template>
  <AppLayout title="Kalender Jadwal Shift Karyawan">
    <div class="max-w-6xl mx-auto py-8 relative">
      <h1 class="text-2xl font-bold text-blue-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-calendar-days text-blue-500"></i> Kalender Jadwal Shift Karyawan
      </h1>
      <div class="flex gap-4 mb-6 items-center">
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
          <select v-model="outletId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
          </select>
        </div>
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
          <select v-model="divisionId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Divisi</option>
            <option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.name }}</option>
          </select>
        </div>
        <div class="flex items-end">
          <button @click="reload" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg">
            <i class="fa-solid fa-eye mr-2"></i>Tampilkan
          </button>
        </div>
      </div>
      <div v-if="!hasLoaded" class="bg-gradient-to-br from-blue-100 to-blue-300 rounded-3xl shadow-2xl p-6 animate-fade-in">
        <div class="text-center py-12">
          <i class="fa-solid fa-calendar-days text-6xl text-blue-500 mb-4"></i>
          <h3 class="text-xl font-bold text-blue-800 mb-2">Kalender Jadwal Shift</h3>
          <p class="text-blue-600">Pilih filter dan klik "Tampilkan" untuk melihat jadwal shift</p>
        </div>
      </div>
      <div v-else class="bg-gradient-to-br from-blue-100 to-blue-300 rounded-3xl shadow-2xl p-6 animate-fade-in">
        <div class="flex items-center justify-between mb-4">
          <button @click="prevMonth" class="bg-blue-100 text-blue-700 px-3 py-2 rounded shadow hover:bg-blue-200 transition"><i class="fa-solid fa-chevron-left"></i></button>
          <div class="font-bold text-lg text-blue-700 min-w-[180px] text-center drop-shadow-md">
            {{ monthNames[month-1] }} {{ year }}
          </div>
          <button @click="nextMonth" class="bg-blue-100 text-blue-700 px-3 py-2 rounded shadow hover:bg-blue-200 transition"><i class="fa-solid fa-chevron-right"></i></button>
          <div class="flex gap-2">
            <button @click="exportExcel" class="bg-green-600 text-white px-4 py-2 rounded-lg shadow hover:bg-green-700 transition font-bold text-sm">
              <i class="fa-solid fa-file-excel"></i> Export to Excel
            </button>
          </div>
        </div>
        <div class="grid grid-cols-7 gap-2 mb-2">
          <div v-for="d in days" :key="d" class="text-center font-bold text-blue-800 text-lg uppercase drop-shadow-sm">{{ d }}</div>
        </div>
        <transition-group name="calendar-fade" tag="div" class="grid grid-cols-7 gap-4">
          <div v-for="(week, rowIdx) in calendarGrid" :key="rowIdx" class="contents">
            <div v-for="(date, colIdx) in week" :key="colIdx"
              class="relative min-h-[110px] rounded-2xl shadow-2xl flex flex-col items-center justify-start p-2 calendar-cell-3d animate-pop-in"
              :class="[
                date ? 'hover:scale-105 transition-transform duration-300' : 'bg-transparent shadow-none',
                date && isHoliday(date) ? 'bg-red-200 border-red-400' : '',
                date && !isHoliday(date) && colIdx === 0 ? 'bg-red-100' : '', // Minggu
                date && !isHoliday(date) && colIdx === 6 ? 'bg-blue-100' : '', // Sabtu
                date && !isHoliday(date) && colIdx !== 0 && colIdx !== 6 ? 'bg-white' : '' // Hari biasa
              ]"
            >
              <div v-if="date" class="font-bold text-blue-700 mb-1 text-lg">{{ date.getDate() }}</div>
              <div v-if="date && isHoliday(date)" class="text-xs text-red-600 font-bold text-center mb-1">{{ isHoliday(date).name }}</div>
              <div v-if="date" class="flex flex-col gap-1 w-full">
                <transition-group name="shift-badge-fade" tag="div">
                  <div
                    v-for="shift in getShiftsForDay(formatDateLocal(date))"
                    :key="shift.user_id"
                    class="rounded-xl px-2 py-1 mb-1 font-semibold text-xs shadow shift-badge-3d animate-bounce-in cursor-pointer relative"
                    :class="getBadgeColor(shift.shift_name, isUserAbsent(formatDateLocal(date), shift.user_id))"
                    @mouseenter="showTooltip(formatDateLocal(date) + '-' + shift.user_id)"
                    @mouseleave="hideTooltip()"
                    @click="showTooltip(formatDateLocal(date) + '-' + shift.user_id)"
                  >
                    <span class="block truncate">{{ shift.nama_lengkap }}</span>
                    <span class="block text-[11px] font-normal">
                      <span v-if="isUserAbsent(formatDateLocal(date), shift.user_id)" class="flex items-center gap-1">
                        <i class="fa-solid fa-user-xmark text-xs"></i>
                        {{ getAbsentInfo(formatDateLocal(date), shift.user_id)?.leave_type_name || 'ABSENT' }}
                      </span>
                      <span v-else>{{ shift.shift_name || 'OFF' }}</span>
                    </span>
                    <!-- Tooltip -->
                    <transition name="fade">
                      <div
                        v-if="activeTooltip === (formatDateLocal(date) + '-' + shift.user_id)"
                        class="absolute z-50 left-1/2 -translate-x-1/2 -top-2 translate-y-[-100%] bg-white text-gray-800 rounded-xl shadow-lg px-4 py-2 text-xs font-normal border border-blue-200 animate-fade-in min-w-[180px]"
                        @click.stop
                      >
                        <div class="font-bold text-blue-700 mb-1">{{ shift.nama_lengkap }}</div>
                        <div>Hari: <b>{{ getDayName(formatDateLocal(date)) }}</b></div>
                        <div>Tanggal: <b>{{ formatDateLocal(date) }}</b></div>
                        <div v-if="isUserAbsent(formatDateLocal(date), shift.user_id)">
                          <div class="text-red-600 font-bold mb-1">
                            <i class="fa-solid fa-user-xmark mr-1"></i>
                            {{ getAbsentInfo(formatDateLocal(date), shift.user_id)?.leave_type_name || 'ABSENT' }}
                          </div>
                          <div v-if="getAbsentInfo(formatDateLocal(date), shift.user_id)?.reason">
                            Alasan: <b>{{ getAbsentInfo(formatDateLocal(date), shift.user_id).reason }}</b>
                          </div>
                          <div v-if="getAbsentInfo(formatDateLocal(date), shift.user_id)?.date_from !== getAbsentInfo(formatDateLocal(date), shift.user_id)?.date_to">
                            Periode: <b>{{ getAbsentInfo(formatDateLocal(date), shift.user_id).date_from }} - {{ getAbsentInfo(formatDateLocal(date), shift.user_id).date_to }}</b>
                          </div>
                        </div>
                        <div v-else>
                          <div v-if="shift.time_start && shift.time_end">Jam Kerja: <b>{{ shift.time_start }} - {{ shift.time_end }}</b></div>
                          <div v-else>Jam Kerja: <b>OFF</b></div>
                        </div>
                      </div>
                    </transition>
                  </div>
                </transition-group>
              </div>
            </div>
          </div>
        </transition-group>
      </div>
      <transition name="fade">
        <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
          <div class="flex flex-col items-center gap-4">
            <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <div class="text-blue-700 font-bold text-lg animate-pulse">Memuat jadwal shift...</div>
          </div>
        </div>
      </transition>
    </div>
  </AppLayout>
</template>

<style scoped>
.calendar-cell-3d {
  box-shadow: 0 6px 24px 0 rgba(30, 64, 175, 0.10), 0 1.5px 4px 0 rgba(30, 64, 175, 0.10);
  border: 1.5px solid #e0e7ff;
  transition: box-shadow 0.3s, transform 0.3s;
}
.calendar-cell-3d:hover {
  box-shadow: 0 12px 32px 0 rgba(30, 64, 175, 0.18), 0 2px 8px 0 rgba(30, 64, 175, 0.12);
  z-index: 2;
}
.shift-badge-3d {
  box-shadow: 0 2px 8px 0 rgba(59, 130, 246, 0.10);
  border: 1px solid #e0e7ff;
  transition: box-shadow 0.2s, transform 0.2s;
}
.animate-fade-in {
  animation: fadeIn 0.7s cubic-bezier(.4,0,.2,1);
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: none; }
}
.animate-pop-in {
  animation: popIn 0.5s cubic-bezier(.4,0,.2,1);
}
@keyframes popIn {
  from { opacity: 0; transform: scale(0.8); }
  to { opacity: 1; transform: scale(1); }
}
.shift-badge-fade-enter-active, .shift-badge-fade-leave-active {
  transition: all 0.3s cubic-bezier(.4,0,.2,1);
}
.shift-badge-fade-enter-from, .shift-badge-fade-leave-to {
  opacity: 0;
  transform: translateY(10px) scale(0.95);
}
.shift-badge-fade-leave-active {
  position: absolute;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style> 