<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

// State untuk expandable rows
const expandedRows = ref(new Set())

const props = defineProps({
  rows: Array,
  outlets: Array,
  divisions: Array,
  filter: Object,
  period: Object,
  summary: Object,
  user: Object,
})

// Auto-select outlet if user is not from outlet 1
const initialOutletId = () => {
  if (props.user?.id_outlet && props.user.id_outlet !== 1) {
    return props.user.id_outlet.toString()
  }
  return props.filter?.outlet_id || ''
}

const outletId = ref(initialOutletId())
const divisionId = ref(props.filter?.division_id || '')
const bulan = ref(props.filter?.bulan || (new Date().getMonth() + 1))
const tahun = ref(props.filter?.tahun || new Date().getFullYear())

const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
const tahunOptions = Array.from({length: 5}, (_,i) => new Date().getFullYear() - i)

// Filter outlets based on user's outlet access
const availableOutlets = computed(() => {
  if (props.user?.id_outlet && props.user.id_outlet !== 1) {
    // If user is not from outlet 1 (head office), only show their outlet
    return props.outlets?.filter(outlet => outlet.id == props.user.id_outlet) || []
  }
  // If user is from outlet 1 (head office), show all outlets
  return props.outlets || []
})

function applyFilter() {
  // Show loading spinner
  Swal.fire({
    title: 'Loading...',
    text: 'Memproses data absensi karyawan',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })
  
  router.get('/attendance-report/employee-summary', {
    outlet_id: outletId.value || '',
    division_id: divisionId.value || '',
    bulan: bulan.value,
    tahun: tahun.value,
  }, { 
    preserveState: true, 
    replace: true,
    onFinish: () => {
      // Close loading spinner
      Swal.close()
    }
  })
}

function exportExcel() {
  // Show loading spinner
  Swal.fire({
    title: 'Exporting...',
    text: 'Menyiapkan file Excel',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })
  
  // Buat URL dengan parameter yang sama seperti filter
  const params = new URLSearchParams({
    outlet_id: outletId.value || '',
    division_id: divisionId.value || '',
    bulan: bulan.value,
    tahun: tahun.value,
  })
  
  // Create a temporary link element to trigger download
  const link = document.createElement('a')
  link.href = `/attendance-report/employee-summary/export?${params.toString()}`
  link.style.display = 'none'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  
  // Close loading spinner after a short delay
  setTimeout(() => {
    Swal.close()
  }, 2000)
}

// Computed untuk total summary
const totalSummary = computed(() => {
  if (!props.rows || props.rows.length === 0) return null
  
  return {
    total_hari_kerja: props.rows.reduce((sum, row) => sum + (row.hari_kerja || 0), 0),
    total_off_days: props.rows.reduce((sum, row) => sum + (row.off_days || 0), 0),
    total_ph_days: props.rows.reduce((sum, row) => sum + (row.ph_days || 0), 0),
    total_ph_bonus: props.rows.reduce((sum, row) => sum + (row.ph_bonus || 0), 0),
    total_cuti_days: props.rows.reduce((sum, row) => sum + (row.cuti_days || 0), 0),
    total_extra_off_days: props.rows.reduce((sum, row) => sum + (row.extra_off_days || 0), 0),
    total_sakit_days: props.rows.reduce((sum, row) => sum + (row.sakit_days || 0), 0),
    total_alpa_days: props.rows.reduce((sum, row) => sum + (row.alpa_days || 0), 0),
    total_ot_full_days: props.rows.reduce((sum, row) => sum + (row.ot_full_days || 0), 0),
    total_telat: props.rows.reduce((sum, row) => sum + (row.total_telat || 0), 0),
    total_days: props.rows.reduce((sum, row) => sum + (row.total_days || 0), 0),
  }
})

// Format angka untuk display
function formatNumber(num) {
  return num ? num.toLocaleString('id-ID') : '0'
}

function formatDecimal(num) {
  return num ? num.toFixed(2) : '0.00'
}

function formatCurrency(num) {
  return num ? 'Rp ' + num.toLocaleString('id-ID') : 'Rp 0'
}

// Functions untuk expandable rows
function toggleRow(userId) {
  if (expandedRows.value.has(userId)) {
    expandedRows.value.delete(userId)
  } else {
    expandedRows.value.add(userId)
  }
}

function isRowExpanded(userId) {
  return expandedRows.value.has(userId)
}

// Format tanggal untuk display
function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

// Format waktu untuk display
function formatTime(timeString) {
  if (!timeString) return '-'
  const time = new Date(timeString)
  return time.toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

// Hitung total lembur dari detail absensi
function calculateTotalLemburFromDetails(dailyAttendance) {
  if (!dailyAttendance || !Array.isArray(dailyAttendance)) return 0
  return dailyAttendance.reduce((sum, day) => sum + (day.lembur || 0), 0)
}

// Hitung total telat dari detail absensi
function calculateTotalTelatFromDetails(dailyAttendance) {
  if (!dailyAttendance || !Array.isArray(dailyAttendance)) return 0
  return dailyAttendance.reduce((sum, day) => sum + (day.telat || 0), 0)
}

// Modal state untuk detail dan shift
const showDetail = ref(false)
const detailRows = ref([])
const detailUser = ref('')
const detailTanggal = ref('')

const showShiftModal = ref(false)
const shiftInfo = ref({})

// Functions untuk modal detail dan shift
async function openDetail(day) {
  showDetail.value = true
  detailUser.value = day.nama_lengkap || 'Karyawan'
  detailTanggal.value = day.tanggal
  detailRows.value = []
  
  // Simulasi data detail - dalam implementasi nyata, ini akan memanggil API
  detailRows.value = [
    {
      jam_in: day.jam_masuk || '-',
      jam_out: day.jam_keluar || '-',
      total_in: day.total_masuk || 0,
      total_out: day.total_keluar || 0
    }
  ]
}

function closeDetail() {
  showDetail.value = false
  detailRows.value = []
}

async function openShiftModal(day) {
  showShiftModal.value = true
  shiftInfo.value = {}
  
  // Simulasi data shift - dalam implementasi nyata, ini akan memanggil API
  shiftInfo.value = {
    shift_name: day.shift_name || 'Shift Normal',
    time_start: day.shift_start || '08:00:00',
    time_end: day.shift_end || '17:00:00'
  }
}

function closeShiftModal() {
  showShiftModal.value = false
  shiftInfo.value = {}
}
</script>

<template>
  <AppLayout title="Employee Attendance Summary">
    <div class="w-full px-2 md:px-4 py-8">
      <div class="text-2xl font-bold text-gray-800 mb-2">Employee Attendance Summary</div>
      <div v-if="period" class="text-sm text-gray-500 mb-6">Periode: {{ period.start }} s.d. {{ period.end }}</div>
      
      <!-- Summary Cards -->
      <div v-if="summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 p-4 rounded-lg border">
          <div class="text-sm font-medium text-blue-600">Total Lembur</div>
          <div class="text-2xl font-bold text-blue-800">{{ summary.total_lembur || 0 }} jam</div>
        </div>
        <div class="bg-green-50 p-4 rounded-lg border">
          <div class="text-sm font-medium text-green-600">Rata-rata Lembur/Orang</div>
          <div class="text-2xl font-bold text-green-800">{{ summary.avg_lembur_per_employee || 0 }} jam</div>
        </div>
        <div class="bg-orange-50 p-4 rounded-lg border">
          <div class="text-sm font-medium text-orange-600">Total Telat</div>
          <div class="text-2xl font-bold text-orange-800">{{ summary.total_telat || 0 }} menit</div>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg border">
          <div class="text-sm font-medium text-purple-600">Rata-rata Telat/Orang</div>
          <div class="text-2xl font-bold text-purple-800">{{ summary.avg_telat_per_employee || 0 }} menit</div>
        </div>
      </div>
      
      <!-- Navigation Links -->
      <div class="flex gap-2 mb-4">
        <a href="/attendance-report" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-xl shadow flex items-center gap-2">
          <i class="fa fa-calendar"></i> Detail Report
        </a>
        <a href="/attendance-report/outlet-summary" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-xl shadow flex items-center gap-2">
          <i class="fa fa-building"></i> Outlet Summary
        </a>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="text-lg font-semibold text-gray-700 mb-4">Filter Report</div>
        <div class="flex flex-col md:flex-row md:items-end gap-4">
          <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select v-model="outletId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" :disabled="availableOutlets.length === 1">
              <option value="">Semua Outlet</option>
              <option v-for="o in availableOutlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
            <select v-model="divisionId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="">Semua Divisi</option>
              <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="flex-1 min-w-[120px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
            <select v-model="bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option v-for="(m, idx) in monthNames" :key="idx+1" :value="idx+1">{{ m }}</option>
            </select>
          </div>
          <div class="flex-1 min-w-[100px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
            <select v-model="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option v-for="t in tahunOptions" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div class="flex gap-2">
            <button @click="applyFilter" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg">
              <i class="fa fa-search mr-2"></i> Tampilkan
            </button>
            <button @click="exportExcel" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg">
              <i class="fa fa-file-excel mr-2"></i> Export Excel
            </button>
          </div>
        </div>
      </div>


      <!-- Data Table -->
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">Data Karyawan</h3>
            <div class="text-sm text-gray-500">
              Total: {{ props.rows ? props.rows.length : 0 }} karyawan
            </div>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider w-12">No</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider w-12">Expand</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">NIK</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jabatan</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Hari Kerja</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Off</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">PH (Bonus)</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Cuti</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Extra Off</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Sakit</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Alpa</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">OT Full</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Telat</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Total Days</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, index) in props.rows" :key="row.user_id">
                <!-- Main Row -->
                <tr :class="index % 2 === 0 ? 'bg-white' : 'bg-blue-50'">
                  <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ index + 1 }}</td>
                  <td class="px-4 py-3 text-center">
                    <button @click="toggleRow(row.user_id)" 
                            class="p-1 rounded-full hover:bg-gray-200 transition-colors"
                            :title="isRowExpanded(row.user_id) ? 'Tutup detail' : 'Lihat detail'">
                      <i :class="isRowExpanded(row.user_id) ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" 
                         class="text-gray-600"></i>
                    </button>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ row.nik || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ row.nama_lengkap }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ row.jabatan || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-blue-600 font-semibold">
                    {{ formatNumber(row.hari_kerja || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-gray-600">
                    {{ formatNumber(row.off_days || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center">
                    <div class="font-mono text-gray-400">{{ formatNumber(row.ph_days || 0) }} hari</div>
                    <div class="font-mono text-green-600 font-semibold text-xs">{{ formatCurrency(row.ph_bonus || 0) }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-purple-600">
                    {{ formatNumber(row.cuti_days || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-orange-600">
                    {{ formatNumber(row.extra_off_days || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-red-600">
                    {{ formatNumber(row.sakit_days || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-red-700">
                    {{ formatNumber(row.alpa_days || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-green-600">
                    <span class="font-bold" :title="'Dari detail: ' + calculateTotalLemburFromDetails(row.daily_attendance) + ' jam'">
                      {{ formatNumber(row.ot_full_days || 0) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono">
                    <span :class="row.total_telat > 0 ? 'text-red-600 font-bold' : 'text-gray-500'"
                          :title="'Dari detail: ' + calculateTotalTelatFromDetails(row.daily_attendance) + ' menit'">
                      {{ formatNumber(row.total_telat || 0) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-blue-600 font-semibold">
                    {{ formatNumber(row.total_days || 0) }}
                  </td>
                </tr>
                
                <!-- Expanded Detail Row -->
                <tr v-if="isRowExpanded(row.user_id)" class="bg-gray-50">
                  <td colspan="15" class="px-4 py-4">
                    <div class="bg-white rounded-lg shadow-sm border p-4">
                      <h4 class="text-lg font-semibold text-gray-800 mb-4">
                        Detail Absensi Harian - {{ row.nama_lengkap }}
                      </h4>
                      
                      <!-- Summary dari detail -->
                      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 p-3 bg-blue-50 rounded-lg">
                        <div class="text-center">
                          <div class="text-sm text-gray-600">Total Lembur (dari detail)</div>
                          <div class="text-xl font-bold text-green-600">
                            {{ calculateTotalLemburFromDetails(row.daily_attendance) }} jam
                          </div>
                        </div>
                        <div class="text-center">
                          <div class="text-sm text-gray-600">Total Telat (dari detail)</div>
                          <div class="text-xl font-bold text-red-600">
                            {{ calculateTotalTelatFromDetails(row.daily_attendance) }} menit
                          </div>
                        </div>
                        <div class="text-center">
                          <div class="text-sm text-gray-600">Total Hari</div>
                          <div class="text-xl font-bold text-blue-600">
                            {{ row.daily_attendance ? row.daily_attendance.length : 0 }} hari
                          </div>
                        </div>
                      </div>
                      
                      <!-- Detail tabel - persis seperti Report Attendance -->
                      <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-blue-200">
                          <thead class="bg-blue-600 text-white">
                            <tr>
                              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Tanggal</th>
                              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Jam Masuk</th>
                              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Jam Keluar</th>
                              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">IN/OUT</th>
                              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Telat (menit)</th>
                              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Lembur (jam)</th>
                              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
                              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Shift</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(day, dayIndex) in row.daily_attendance" :key="dayIndex"
                                :class="[
                                  day.is_holiday ? 'bg-red-100 text-red-700 font-bold' : '',
                                  !day.is_holiday && day.is_approved_absent ? 'bg-green-100 text-green-700 font-bold' : '',
                                  !day.is_holiday && !day.is_approved_absent && day.is_off ? 'bg-gray-200 text-gray-500 font-bold italic' : '',
                                  !day.is_holiday && !day.is_approved_absent && !day.is_off && dayIndex % 2 === 1 ? 'bg-blue-50' : ''
                                ]">
                              <td class="px-4 py-2 whitespace-nowrap font-mono" :title="day.is_holiday ? day.holiday_name : (day.is_approved_absent ? day.approved_absent_name : '')">
                                {{ day.tanggal }}
                                <span v-if="day.is_holiday && day.holiday_name" class="ml-1 text-xs font-semibold">({{ day.holiday_name }})</span>
                                <span v-if="!day.is_holiday && day.is_approved_absent && day.approved_absent_name" class="ml-1 text-xs font-semibold">({{ day.approved_absent_name }})</span>
                              </td>
                              <td class="px-4 py-2 whitespace-nowrap">{{ row.nama_lengkap }}</td>
                              <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                                <span v-if="day.is_off">OFF</span>
                                <span v-else-if="day.approved_absent" class="text-green-600 font-semibold">
                                  <i class="fa-solid fa-check-circle mr-1"></i>{{ day.approved_absent.leave_type_name }}
                                </span>
                                <span v-else>{{ day.jam_masuk || '-' }}</span>
                              </td>
                              <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                                <span v-if="day.is_off">OFF</span>
                                <span v-else-if="day.approved_absent" class="text-green-600 font-semibold">
                                  <i class="fa-solid fa-check-circle mr-1"></i>{{ day.approved_absent.leave_type_name }}
                                </span>
                                <span v-else>{{ day.jam_keluar || '-' }}</span>
                              </td>
                              <td class="px-4 py-2 whitespace-nowrap text-center font-mono text-xs">
                                <span v-if="day.is_off">OFF</span>
                                <span v-else class="flex flex-col">
                                  <span class="text-green-600 font-semibold">{{ day.total_masuk || 0 }} IN</span>
                                  <span class="text-red-600 font-semibold">{{ day.total_keluar || 0 }} OUT</span>
                                </span>
                              </td>
                              <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                                <span v-if="day.is_off">OFF</span>
                                <span v-else>{{ day.telat || 0 }}</span>
                              </td>
                              <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                                <span v-if="day.is_off">OFF</span>
                                <span v-else>
                                  {{ day.lembur || 0 }}
                                  <span v-if="day.is_cross_day" 
                                        class="text-xs text-orange-600 font-semibold ml-1" title="Cross-day overtime">
                                    🌙
                                  </span>
                                </span>
                              </td>
                              <td class="px-4 py-2 whitespace-nowrap text-center">
                                <button v-if="!day.is_off" @click="openDetail(day)" class="px-3 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition text-xs font-semibold">
                                  <i class="fa fa-list mr-1"></i> Detail
                                </button>
                              </td>
                              <td class="px-4 py-2 whitespace-nowrap text-center">
                                <button v-if="!day.is_off" @click="openShiftModal(day)" class="px-3 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 transition text-xs font-semibold">
                                  <i class="fa fa-clock mr-1"></i> Shift
                                </button>
                                <span v-else>-</span>
                              </td>
                            </tr>
                            <tr v-if="!row.daily_attendance || row.daily_attendance.length === 0">
                              <td colspan="9" class="text-center py-8 text-gray-400">
                                <div class="flex flex-col items-center gap-2">
                                  <i class="fa fa-search text-4xl text-gray-300"></i>
                                  <div class="text-lg font-medium">Tidak ada data absensi</div>
                                  <div class="text-sm text-gray-500">Tidak ada data untuk periode yang dipilih</div>
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="!props.rows || props.rows.length === 0">
                <td colspan="15" class="text-center py-8 text-gray-400">
                  <div class="flex flex-col items-center gap-2">
                    <i class="fa fa-search text-4xl text-gray-300"></i>
                    <div class="text-lg font-medium">Tidak ada data karyawan</div>
                    <div class="text-sm text-gray-500">Silakan pilih filter dan klik tombol "Tampilkan" untuk melihat data</div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer Summary -->
      <div v-if="totalSummary && props.rows && props.rows.length > 0" class="mt-6">
        <div class="bg-gray-100 rounded-xl p-4">
          <div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Keseluruhan:</div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
              <span class="font-medium">Total Karyawan:</span> 
              <span class="font-bold text-blue-600">{{ props.rows.length }}</span>
            </div>
            <div>
              <span class="font-medium">Total Hari Kerja:</span> 
              <span class="font-bold text-blue-600">{{ formatNumber(totalSummary.total_hari_kerja || 0) }} hari</span>
            </div>
            <div>
              <span class="font-medium">Total Off:</span> 
              <span class="font-bold text-gray-600">{{ formatNumber(totalSummary.total_off_days || 0) }} hari</span>
            </div>
            <div>
              <span class="font-medium">Total PH:</span> 
              <span class="font-bold text-gray-400">{{ formatNumber(totalSummary.total_ph_days || 0) }} hari</span>
              <span class="font-bold text-green-600 ml-2">{{ formatCurrency(totalSummary.total_ph_bonus || 0) }}</span>
            </div>
            <div>
              <span class="font-medium">Total Cuti:</span> 
              <span class="font-bold text-purple-600">{{ formatNumber(totalSummary.total_cuti_days || 0) }} hari</span>
            </div>
            <div>
              <span class="font-medium">Total Sakit:</span> 
              <span class="font-bold text-red-600">{{ formatNumber(totalSummary.total_sakit_days || 0) }} hari</span>
            </div>
            <div>
              <span class="font-medium">Total Alpa:</span> 
              <span class="font-bold text-red-700">{{ formatNumber(totalSummary.total_alpa_days || 0) }} hari</span>
            </div>
            <div>
              <span class="font-medium">Total Telat:</span> 
              <span class="font-bold text-red-600">{{ formatNumber(totalSummary.total_telat || 0) }} menit</span>
            </div>
            <div>
              <span class="font-medium">Total Days:</span> 
              <span class="font-bold text-blue-600">{{ formatNumber(totalSummary.total_days || 0) }} hari</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Modal Detail - persis seperti Report Attendance -->
      <div v-if="showDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
        <div class="bg-white rounded-2xl shadow-2xl p-10 w-full max-w-4xl min-w-[800px] relative animate-fade-in">
          <div class="modal-detail-header">
            <i class="fa fa-list text-blue-500"></i>
            Detail Absensi
          </div>
          <div class="modal-detail-user">{{ detailUser }} | {{ detailTanggal }}</div>
          <table class="w-full divide-y divide-blue-200 mb-4 modal-detail-table">
            <thead>
              <tr>
                <th class="px-4 py-2 text-center">Jam In</th>
                <th class="px-4 py-2 text-center">Jam Out</th>
                <th class="px-4 py-2 text-center">Total IN</th>
                <th class="px-4 py-2 text-center">Total OUT</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(d, i) in detailRows" :key="i">
                <td class="px-4 py-2 whitespace-nowrap text-center font-mono">{{ d.jam_in }}</td>
                <td class="px-4 py-2 whitespace-nowrap text-center font-mono">{{ d.jam_out }}</td>
                <td class="px-4 py-2 whitespace-nowrap text-center font-mono text-green-600 font-semibold">{{ d.total_in }}</td>
                <td class="px-4 py-2 whitespace-nowrap text-center font-mono text-red-600 font-semibold">{{ d.total_out }}</td>
              </tr>
              <tr v-if="!detailRows.length">
                <td colspan="4" class="text-center py-6 text-gray-400">Tidak ada data</td>
              </tr>
            </tbody>
          </table>
          <div class="modal-detail-footer">
            <button @click="closeDetail" class="modal-detail-btn">Tutup</button>
          </div>
          <button @click="closeDetail" class="modal-detail-close"><i class="fa fa-times"></i></button>
        </div>
      </div>
      
      <!-- Modal Shift - persis seperti Report Attendance -->
      <div v-if="showShiftModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md relative animate-fade-in">
          <div class="flex items-center gap-2 mb-4">
            <i class="fa fa-clock text-green-500"></i>
            <span class="font-bold text-lg">Info Shift Karyawan</span>
          </div>
          <div v-if="shiftInfo && shiftInfo.shift_name">
            <div class="mb-2"><b>Shift:</b> {{ shiftInfo.shift_name }}</div>
            <div class="mb-2"><b>Jam Masuk:</b> {{ shiftInfo.time_start }}</div>
            <div class="mb-2"><b>Jam Keluar:</b> {{ shiftInfo.time_end }}</div>
          </div>
          <div v-else class="text-gray-500">Tidak ada data shift untuk hari ini.</div>
          <div class="flex justify-end mt-6">
            <button @click="closeShiftModal" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300">Tutup</button>
          </div>
          <button @click="closeShiftModal" class="absolute top-3 right-3 text-gray-400 hover:text-red-500"><i class="fa fa-times"></i></button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Animation untuk modal */
.animate-fade-in {
  animation: fadeIn 0.4s cubic-bezier(.4,0,.2,1);
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: none; }
}

/* Modal detail styling - persis seperti Report Attendance */
.modal-detail-header {
  font-size: 1.25rem;
  font-weight: 700;
  color: #2563eb;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}
.modal-detail-table th {
  background: #e0e7ff;
  color: #1e293b;
  font-weight: 700;
  font-size: 0.95rem;
  letter-spacing: 1px;
}
.modal-detail-table td {
  font-size: 1rem;
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
}
.modal-detail-table tr:nth-child(even) {
  background: #f1f5f9;
}
.modal-detail-user {
  font-weight: 600;
  color: #334155;
  margin-bottom: 0.5rem;
}
.modal-detail-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 1.5rem;
}
.modal-detail-close {
  position: absolute;
  top: 1.2rem;
  right: 1.2rem;
  color: #94a3b8;
  font-size: 1.3rem;
  cursor: pointer;
  transition: color 0.2s;
}
.modal-detail-close:hover {
  color: #ef4444;
}
.modal-detail-btn {
  background: #e5e7eb;
  color: #334155;
  border-radius: 0.5rem;
  padding: 0.5rem 1.5rem;
  font-weight: 600;
  font-size: 1rem;
  transition: background 0.2s;
}
.modal-detail-btn:hover {
  background: #cbd5e1;
}

/* Responsive table */
@media (max-width: 768px) {
  table {
    font-size: 0.875rem;
  }
  
  th, td {
    padding: 0.5rem 0.25rem;
  }
}

/* Hover effect for table rows */
tbody tr:hover {
  background-color: #f3f4f6;
}

/* Custom scrollbar for table */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>
