<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  outlets: Array,
  divisions: Array,
  users: Array,
  shifts: Array,
  scheduleData: Array,
  attendanceData: Array,
  filters: Object,
});

const page = usePage();
const userOutletId = page.props.auth?.user?.id_outlet || '';

// Check if user can select outlet (only Head Office users with id_outlet = 1)
const canSelectOutlet = computed(() => userOutletId == 1);

// Form data
const outletId = ref(props.filters?.outlet_id || '');
const divisionId = ref(props.filters?.division_id || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const selectedEmployee = ref(null);
const employees = ref([]);
const loadingEmployees = ref(false);
const correctionType = ref(props.filters?.correction_type || 'schedule');

const loading = ref(false);
const submittingCorrection = ref(false);

// Manual attendance form data
const manualAttendanceForm = ref({
  outletId: '',
  scanDate: '',
  scanTime: '',
  inoutmode: '1',
  reason: ''
});

// Manual attendance limit data
const manualAttendanceLimit = ref({
  canSubmit: true,
  remaining: 2,
  used: 0,
  period: null
});
const checkingLimit = ref(false);

// Correction modal data
const showCorrectionModal = ref(false);
const correctionData = ref({
  id: null,
  type: 'schedule',
  currentValue: null,
  newValue: null,
  newDate: '',
  newTime: '',
  reason: '',
  userInfo: null,
  dateInfo: null,
});

// Computed properties
const filteredUsers = computed(() => {
  if (!outletId.value) return [];
  return props.users || [];
});

const filteredShifts = computed(() => {
  if (!divisionId.value) return [];
  return props.shifts || [];
});

// Get shifts for the user being corrected
const userShifts = computed(() => {
  if (!correctionData.value.userInfo) {
    console.log('No user info found, returning all shifts');
    return props.shifts || [];
  }
  
  // Use schedule_division_id if available, otherwise use user_division_id
  const divisionId = correctionData.value.userInfo.schedule_division_id || correctionData.value.userInfo.user_division_id;
  
  if (!divisionId) {
    console.log('No division ID found, returning all shifts');
    return props.shifts || [];
  }
  
  console.log('Division ID:', divisionId);
  console.log('All shifts:', props.shifts);
  
  // Filter shifts by division
  const filteredShifts = (props.shifts || []).filter(shift => 
    shift.division_id == divisionId
  );
  
  console.log('Filtered shifts:', filteredShifts);
  return filteredShifts;
});

const filteredScheduleData = computed(() => {
  if (!selectedEmployee.value) return props.scheduleData || [];
  return (props.scheduleData || []).filter(item => item.user_id === selectedEmployee.value.id);
});

const filteredAttendanceData = computed(() => {
  if (!selectedEmployee.value) return props.attendanceData || [];
  return (props.attendanceData || []).filter(item => item.user_id === selectedEmployee.value.id);
});

// Fetch employees based on current filters
async function fetchEmployees() {
  loadingEmployees.value = true;
  try {
    const params = {};
    if (outletId.value) params.outlet_id = outletId.value;
    if (divisionId.value) params.division_id = divisionId.value;
    
    const res = await axios.get('/api/attendance-report/employees', { params });
    employees.value = res.data;
  } catch (error) {
    console.error('Error fetching employees:', error);
    employees.value = [];
  } finally {
    loadingEmployees.value = false;
  }
}

// Watch for outlet and division changes to fetch employees
watch([outletId, divisionId], async () => {
  if (outletId.value) {
    await fetchEmployees();
  } else {
    employees.value = [];
    selectedEmployee.value = null;
  }
});

// Watch for manual attendance form changes to check limit
watch([selectedEmployee, () => manualAttendanceForm.value.scanDate], async () => {
  if (correctionType.value === 'manual_attendance' && selectedEmployee.value && manualAttendanceForm.value.scanDate) {
    await checkManualAttendanceLimit();
  }
});

// Initialize on component mount
onMounted(async () => {
  if (outletId.value) {
    await fetchEmployees();
  }
});

// Functions
function reloadData() {
  if (!outletId.value || !startDate.value || !endDate.value) {
    Swal.fire('Lengkapi Filter', 'Silakan lengkapi outlet, tanggal mulai, dan tanggal akhir!', 'warning');
    return;
  }
  
  loading.value = true;
  router.get('/schedule-attendance-correction', {
    outlet_id: outletId.value,
    division_id: divisionId.value,
    start_date: startDate.value,
    end_date: endDate.value,
    user_id: selectedEmployee.value ? selectedEmployee.value.id : '',
    correction_type: correctionType.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

function formatDateTimeForInput(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function openCorrectionModal(record, type) {
  let dateStr = '';
  let timeStr = '';
  
  // Only parse date for attendance records that have scan_date
  if (type === 'attendance' && record.scan_date) {
    const date = new Date(record.scan_date);
    dateStr = date.toISOString().split('T')[0]; // YYYY-MM-DD
    timeStr = date.toTimeString().split(' ')[0].slice(0, 5); // HH:MM
  }
  
  correctionData.value = {
    id: type === 'schedule' ? record.id : `${record.sn}_${record.pin}_${record.scan_date}`,
    type: type,
    currentValue: type === 'schedule' ? record.shift_name || 'OFF' : record.scan_date,
    newValue: type === 'schedule' ? record.shift_id : (record.scan_date ? formatDateTimeForInput(record.scan_date) : ''),
    newDate: type === 'schedule' ? '' : dateStr,
    newTime: type === 'schedule' ? '' : timeStr,
    reason: '',
    userInfo: record,
    dateInfo: type === 'schedule' ? record.tanggal : record.scan_date,
  };
  showCorrectionModal.value = true;
}

function closeCorrectionModal() {
  showCorrectionModal.value = false;
  submittingCorrection.value = false; // Reset loading state
  correctionData.value = {
    id: null,
    type: 'schedule',
    currentValue: null,
    newValue: null,
    newDate: '',
    newTime: '',
    reason: '',
    userInfo: null,
    dateInfo: null,
  };
}

async function submitManualAttendance() {
  if (!manualAttendanceForm.value.outletId || !manualAttendanceForm.value.scanDate || !manualAttendanceForm.value.scanTime || !manualAttendanceForm.value.reason.trim()) {
    Swal.fire('Error', 'Outlet absen, tanggal, waktu, dan alasan harus diisi!', 'error');
    return;
  }
  
  // Validate time format (24 hour)
  const timePattern = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
  if (!timePattern.test(manualAttendanceForm.value.scanTime)) {
    Swal.fire('Error', 'Format waktu tidak valid! Gunakan format HH:MM (00:00 - 23:59)', 'error');
    return;
  }
  
  if (!selectedEmployee.value) {
    Swal.fire('Error', 'Pilih karyawan terlebih dahulu!', 'error');
    return;
  }
  
  // Prevent double click
  if (submittingCorrection.value) {
    return;
  }
  
  submittingCorrection.value = true;
  
  try {
    // Convert date format from MM/DD/YYYY to YYYY-MM-DD if needed
    let scanDate = manualAttendanceForm.value.scanDate;
    if (scanDate.includes('/')) {
      const [month, day, year] = scanDate.split('/');
      scanDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    }
    
    // Combine date and time
    const scanDateTime = `${scanDate} ${manualAttendanceForm.value.scanTime}:00`;
    
    const payload = {
      user_id: selectedEmployee.value.id,
      outlet_id: manualAttendanceForm.value.outletId,
      scan_date: scanDateTime,
      inoutmode: parseInt(manualAttendanceForm.value.inoutmode),
      reason: manualAttendanceForm.value.reason
    };
    
    const response = await axios.post('/schedule-attendance-correction/manual-attendance', payload);
    
    if (response.data.success) {
      await Swal.fire('Berhasil!', response.data.message, 'success');
      // Reset form
      manualAttendanceForm.value = {
        outletId: '',
        scanDate: '',
        scanTime: '',
        inoutmode: '1',
        reason: ''
      };
      selectedEmployee.value = null;
      // Reset limit info
      manualAttendanceLimit.value = {
        canSubmit: true,
        remaining: 5,
        used: 0,
        period: null
      };
    } else {
      Swal.fire('Error', response.data.message || 'Terjadi kesalahan', 'error');
    }
  } catch (error) {
    console.error('Manual attendance error:', error);
    Swal.fire('Error', error.response?.data?.message || 'Terjadi kesalahan', 'error');
  } finally {
    submittingCorrection.value = false;
  }
}

async function submitCorrection() {
  if (!correctionData.value.reason.trim()) {
    Swal.fire('Error', 'Alasan koreksi harus diisi!', 'error');
    return;
  }
  
  // Validate time format for attendance correction
  if (correctionData.value.type === 'attendance') {
    const timePattern = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
    if (!timePattern.test(correctionData.value.newTime)) {
      Swal.fire('Error', 'Format waktu tidak valid! Gunakan format HH:MM (00:00 - 23:59)', 'error');
      return;
    }
  }
  
  // Prevent double click
  if (submittingCorrection.value) {
    return;
  }
  
  submittingCorrection.value = true;
  
  try {
    const endpoint = correctionData.value.type === 'schedule'
      ? '/schedule-attendance-correction/schedule'
      : '/schedule-attendance-correction/attendance';
        
    const payload = {
      reason: correctionData.value.reason,
    };
    
    if (correctionData.value.type === 'schedule') {
      payload.schedule_id = correctionData.value.id;
      payload.shift_id = correctionData.value.newValue;
    } else {
      payload.sn = correctionData.value.userInfo.sn;
      payload.pin = correctionData.value.userInfo.pin;
      // Combine date and time to create proper datetime format
      const newDateTime = `${correctionData.value.newDate} ${correctionData.value.newTime}:00`;
      payload.scan_date = newDateTime;
      payload.inoutmode = correctionData.value.userInfo.inoutmode;
      payload.old_scan_date = correctionData.value.userInfo.scan_date;
    }
    
    const response = await axios.post(endpoint, payload);
    
    if (response.data.success) {
      await Swal.fire('Berhasil!', response.data.message, 'success');
      closeCorrectionModal();
      reloadData();
    } else {
      Swal.fire('Error', response.data.message || 'Terjadi kesalahan', 'error');
    }
  } catch (error) {
    console.error('Correction error:', error);
    Swal.fire('Error', error.response?.data?.message || 'Terjadi kesalahan', 'error');
  } finally {
    submittingCorrection.value = false;
  }
}

function formatDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('id-ID');
}

function formatDateTime(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  });
}

function getShiftName(shiftId) {
  if (!shiftId) return 'OFF';
  const shift = filteredShifts.value.find(s => s.id === shiftId);
  return shift ? `${shift.shift_name} (${shift.time_start} - ${shift.time_end})` : 'Unknown';
}

function validateTimeInput(event) {
  const value = event.target.value;
  // Auto-format: add colon after 2 digits
  if (value.length === 2 && !value.includes(':')) {
    correctionData.value.newTime = value + ':';
  }
  // Limit to HH:MM format
  if (value.length > 5) {
    correctionData.value.newTime = value.slice(0, 5);
  }
}

function validateManualTimeInput(event) {
  const value = event.target.value;
  // Auto-format: add colon after 2 digits
  if (value.length === 2 && !value.includes(':')) {
    manualAttendanceForm.value.scanTime = value + ':';
  }
  // Limit to HH:MM format
  if (value.length > 5) {
    manualAttendanceForm.value.scanTime = value.slice(0, 5);
  }
}

// Check manual attendance limit
async function checkManualAttendanceLimit() {
  if (!selectedEmployee.value || !manualAttendanceForm.value.scanDate) {
    return;
  }
  
  checkingLimit.value = true;
  try {
    // Convert date format from MM/DD/YYYY to YYYY-MM-DD if needed
    let scanDate = manualAttendanceForm.value.scanDate;
    if (scanDate.includes('/')) {
      const [month, day, year] = scanDate.split('/');
      scanDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    }
    
    
    const response = await axios.get('/api/schedule-attendance-correction/check-manual-limit', {
      params: {
        user_id: selectedEmployee.value.id,
        scan_date: scanDate
      }
    });
    
    if (response.data.success) {
      // Map snake_case to camelCase
      manualAttendanceLimit.value = {
        canSubmit: response.data.can_submit,
        remaining: response.data.remaining,
        used: response.data.used,
        period: response.data.period
      };
    }
  } catch (error) {
    console.error('Error checking manual attendance limit:', error);
    manualAttendanceLimit.value = {
      canSubmit: true,
      remaining: 5,
      used: 0,
      period: null
    };
  } finally {
    checkingLimit.value = false;
  }
}


// Initialize with user's outlet if not admin
if (userOutletId && userOutletId != 1) {
  outletId.value = userOutletId;
}
</script>

<template>
  <AppLayout title="Schedule/Attendance Correction">
    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-gray-900 flex items-center">
            <i class="fa-solid fa-edit text-blue-500 mr-3"></i>
            Schedule/Attendance Correction
          </h1>
          <p class="text-gray-600 mt-2">
            Koreksi jadwal shift dan data kehadiran yang sudah tercatat
          </p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Data</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Outlet -->
            <div v-if="canSelectOutlet">
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <select 
                v-model="outletId" 
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="">-- Pilih Outlet --</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                  {{ outlet.name }}
                </option>
              </select>
            </div>
            
            <!-- Show current outlet for non-Head Office users -->
            <div v-else>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <div class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700">
                {{ outlets.find(o => o.id == userOutletId)?.name || 'Outlet tidak ditemukan' }}
              </div>
            </div>

            <!-- Division -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Divisi</label>
              <select 
                v-model="divisionId"
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="">-- Pilih Divisi --</option>
                <option v-for="division in divisions" :key="division.id" :value="division.id">
                  {{ division.name }}
                </option>
              </select>
            </div>

            <!-- Date Range -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
              <input 
                type="date" 
                v-model="startDate"
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
              <input 
                type="date" 
                v-model="endDate"
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <!-- User Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Nama Karyawan
              </label>
              <Multiselect
                v-model="selectedEmployee"
                :options="employees"
                :loading="loadingEmployees"
                :searchable="true"
                :clear-on-select="false"
                :close-on-select="true"
                :show-labels="false"
                track-by="id"
                label="name"
                placeholder="Pilih atau cari karyawan..."
                :disabled="!outletId"
              >
                <template #noOptions>
                  <div class="text-center py-2 text-gray-500">
                    Pilih outlet terlebih dahulu
                  </div>
                </template>
                <template #noResult>
                  <div class="text-center py-2 text-gray-500">
                    Tidak ada karyawan ditemukan
                  </div>
                </template>
              </Multiselect>
            </div>

            <!-- Correction Type -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Koreksi</label>
              <select 
                v-model="correctionType"
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="schedule">Schedule Correction</option>
                <option value="attendance">Attendance Correction</option>
                <option value="manual_attendance">Manual Attendance Entry</option>
              </select>
            </div>
          </div>

          <div class="flex justify-end mt-6">
            <button 
              @click="reloadData"
              :disabled="loading"
              class="bg-blue-600 text-white px-6 py-2 rounded-xl shadow hover:bg-blue-700 disabled:opacity-50"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-2"></i>
              <i v-else class="fa-solid fa-search mr-2"></i>
              {{ loading ? 'Loading...' : 'Tampilkan Data' }}
            </button>
          </div>
        </div>

        <!-- Data Display -->
        <div v-if="correctionType === 'schedule' && scheduleData && scheduleData.length" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 bg-blue-600 text-white">
            <h3 class="text-lg font-semibold">Schedule Data</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Karyawan</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="schedule in filteredScheduleData" :key="schedule.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDate(schedule.tanggal) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ schedule.nama_lengkap }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ schedule.shift_name || 'OFF' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button 
                      @click="openCorrectionModal(schedule, 'schedule')"
                      class="text-blue-600 hover:text-blue-900"
                    >
                      <i class="fa-solid fa-edit mr-1"></i>
                      Koreksi
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Attendance Data -->
        <div v-if="correctionType === 'attendance' && attendanceData && attendanceData.length" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 bg-green-600 text-white">
            <h3 class="text-lg font-semibold">Attendance Data</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Karyawan</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal/Waktu</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="attendance in filteredAttendanceData" :key="`${attendance.sn}_${attendance.pin}_${attendance.scan_date}`">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ attendance.nama_lengkap }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDateTime(attendance.scan_date) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="attendance.inoutmode == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                          class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                      {{ attendance.inoutmode == 1 ? 'IN' : 'OUT' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button 
                      @click="openCorrectionModal(attendance, 'attendance')"
                      class="text-blue-600 hover:text-blue-900"
                    >
                      <i class="fa-solid fa-edit mr-1"></i>
                      Koreksi
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Manual Attendance Entry -->
        <div v-if="correctionType === 'manual_attendance'" class="bg-white rounded-2xl shadow-lg p-6">
          <div class="px-6 py-4 bg-purple-600 text-white -m-6 mb-6">
            <h3 class="text-lg font-semibold">Manual Attendance Entry</h3>
            <p class="text-sm opacity-90">Input absen manual untuk karyawan yang lupa absen</p>
          </div>
          
          <div class="space-y-6">
            <!-- Employee Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Karyawan</label>
              <Multiselect
                v-model="selectedEmployee"
                :options="employees"
                :loading="loadingEmployees"
                :searchable="true"
                :clear-on-select="false"
                :close-on-select="true"
                :show-labels="false"
                track-by="id"
                label="name"
                placeholder="Pilih atau cari karyawan..."
                :disabled="!outletId"
              >
                <template #noOptions>
                  <div class="text-center py-2 text-gray-500">
                    Pilih outlet terlebih dahulu
                  </div>
                </template>
                <template #noResult>
                  <div class="text-center py-2 text-gray-500">
                    Tidak ada karyawan ditemukan
                  </div>
                </template>
              </Multiselect>
            </div>

            <!-- Manual Attendance Form -->
            <div v-if="selectedEmployee" class="space-y-4">
              <!-- Limit Info -->
              <div v-if="manualAttendanceLimit.period" class="p-3 rounded-lg" :class="manualAttendanceLimit.canSubmit ? 'bg-blue-50 border border-blue-200' : 'bg-red-50 border border-red-200'">
                <div class="flex items-center justify-between">
                  <div>
                    <div class="text-sm font-medium" :class="manualAttendanceLimit.canSubmit ? 'text-blue-800' : 'text-red-800'">
                      <i class="fa-solid fa-info-circle mr-1"></i>
                      Limit Input Absen Manual
                    </div>
                    <div class="text-xs mt-1" :class="manualAttendanceLimit.canSubmit ? 'text-blue-600' : 'text-red-600'">
                      Periode: {{ manualAttendanceLimit.period.start_formatted }} - {{ manualAttendanceLimit.period.end_formatted }}
                    </div>
                    <div class="text-xs" :class="manualAttendanceLimit.canSubmit ? 'text-blue-600' : 'text-red-600'">
                      Digunakan: {{ manualAttendanceLimit.used }}/5 • Sisa: {{ manualAttendanceLimit.remaining }}
                    </div>
                  </div>
                  <div v-if="checkingLimit" class="text-xs text-gray-500">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                  </div>
                </div>
              </div>

              <!-- Outlet Absen -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Outlet Absen *</label>
                <select 
                  v-model="manualAttendanceForm.outletId"
                  class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                >
                  <option value="">-- Pilih Outlet Absen --</option>
                  <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                    {{ outlet.name }}
                  </option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Pilih outlet dimana karyawan melakukan absen</p>
              </div>

              <!-- Tanggal & Waktu dan Status -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal & Waktu</label>
                  <div class="space-y-2">
                    <input 
                      type="date" 
                      v-model="manualAttendanceForm.scanDate"
                      class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    >
                    <input 
                      type="text" 
                      v-model="manualAttendanceForm.scanTime"
                      placeholder="HH:MM (24 jam)"
                      pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$"
                      class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                      @input="validateManualTimeInput"
                    >
                  </div>
                  <p class="text-xs text-gray-500 mt-1">Format: YYYY-MM-DD HH:MM (24 jam) - Contoh: 14:30, 09:15, 23:45</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                  <select 
                    v-model="manualAttendanceForm.inoutmode"
                    class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                  >
                    <option value="1">Check In</option>
                    <option value="2">Check Out</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Reason -->
            <div v-if="selectedEmployee">
              <label class="block text-sm font-medium text-gray-700 mb-2">Alasan</label>
              <textarea 
                v-model="manualAttendanceForm.reason"
                rows="3"
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                placeholder="Jelaskan alasan input absen manual..."
              ></textarea>
            </div>

            <!-- Submit Button -->
            <div v-if="selectedEmployee" class="flex justify-end">
              <button 
                @click="submitManualAttendance"
                :disabled="submittingCorrection || !manualAttendanceForm.outletId || !manualAttendanceForm.scanDate || !manualAttendanceForm.scanTime || !manualAttendanceForm.reason || !manualAttendanceLimit.canSubmit"
                class="bg-purple-600 text-white px-6 py-2 rounded-xl shadow hover:bg-purple-700 disabled:opacity-50"
              >
                <i v-if="submittingCorrection" class="fa-solid fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-plus mr-2"></i>
                {{ submittingCorrection ? 'Mengirim...' : 'Kirim Permohonan' }}
              </button>
            </div>

            <!-- No Employee Selected -->
            <div v-else class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-user-plus text-4xl mb-4"></i>
              <p>Pilih karyawan terlebih dahulu untuk input absen manual</p>
            </div>
          </div>
        </div>

        <!-- No Data -->
        <div v-if="!loading && ((correctionType === 'schedule' && (!scheduleData || scheduleData.length === 0)) || (correctionType === 'attendance' && (!attendanceData || attendanceData.length === 0)))" 
             class="bg-white rounded-2xl shadow-lg p-8 text-center">
          <i class="fa-solid fa-inbox text-gray-400 text-4xl mb-4"></i>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada data</h3>
          <p class="text-gray-500">Silakan pilih filter dan klik "Tampilkan Data" untuk melihat data yang tersedia.</p>
        </div>
      </div>
    </div>

    <!-- Correction Modal -->
    <div v-if="showCorrectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              Koreksi {{ correctionData.type === 'schedule' ? 'Schedule' : 'Attendance' }}
            </h3>
            <button @click="closeCorrectionModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times"></i>
            </button>
          </div>

          <div class="space-y-4">
            <!-- User Info -->
            <div class="bg-gray-50 p-3 rounded-lg">
              <p class="text-sm text-gray-600">
                <strong>Karyawan:</strong> {{ correctionData.userInfo?.nama_lengkap || 'Unknown' }}
              </p>
              <p class="text-sm text-gray-600">
                <strong>Tanggal:</strong> {{ formatDate(correctionData.dateInfo) }}
              </p>
            </div>

            <!-- Current Value -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nilai Saat Ini</label>
              <div class="p-3 bg-gray-100 rounded-lg">
                <span v-if="correctionData.type === 'schedule'">
                  {{ correctionData.currentValue || 'OFF' }}
                </span>
                <span v-else class="text-sm text-gray-700">
                  {{ formatDateTime(correctionData.currentValue) }}
                </span>
              </div>
            </div>

            <!-- New Value -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nilai Baru</label>
              <select 
                v-if="correctionData.type === 'schedule'"
                v-model="correctionData.newValue"
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option :value="null">OFF</option>
                <option v-for="shift in userShifts" :key="shift.id" :value="shift.id">
                  {{ shift.shift_name }} ({{ shift.time_start }} - {{ shift.time_end }})
                </option>
              </select>
              <div v-else class="space-y-2">
                <input 
                  type="date"
                  v-model="correctionData.newDate"
                  class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                >
                <input 
                  type="text"
                  v-model="correctionData.newTime"
                  placeholder="HH:MM (24 jam format)"
                  pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$"
                  class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                  @input="validateTimeInput"
                >
                <p class="text-xs text-gray-500">Format: 00:00 - 23:59 (24 jam)</p>
              </div>
            </div>

            <!-- Reason -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Koreksi *</label>
              <textarea 
                v-model="correctionData.reason"
                rows="3"
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                placeholder="Jelaskan alasan koreksi..."
              ></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 pt-4">
              <button 
                @click="closeCorrectionModal"
                class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200"
              >
                Batal
              </button>
              <button 
                @click="submitCorrection"
                :disabled="submittingCorrection"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed flex items-center"
              >
                <i v-if="submittingCorrection" class="fa-solid fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-check mr-2"></i>
                {{ submittingCorrection ? 'Menyimpan...' : 'Koreksi' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Multiselect styling */
::deep(.multiselect) {
  min-height: 42px;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
}

::deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

::deep(.multiselect__input) {
  background: transparent;
  border: none;
  outline: none;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

::deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

::deep(.multiselect__single) {
  background: transparent;
  padding: 0.5rem 0;
  font-size: 0.875rem;
  color: #374151;
}

::deep(.multiselect__option) {
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  color: #374151;
}

::deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

::deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}

::deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>
