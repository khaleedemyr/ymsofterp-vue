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
  leaveTypes: Array,
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

// Format jam untuk display
function formatTime(timeString) {
  if (!timeString) return '-'
  return timeString.substring(11, 19) // Ambil bagian jam:menit:detik
}

// Get leave days for a specific leave type
function getLeaveDays(row, leaveTypeName) {
  const key = leaveTypeName.toLowerCase().replace(/\s+/g, '_') + '_days'
  return row[key] || 0
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
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">NIK</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jabatan</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Hari Kerja</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Off</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">PH (Bonus)</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Extra Off</th>
                <!-- Dynamic Leave Type Columns -->
                <th v-for="leaveType in props.leaveTypes" :key="leaveType.id" class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                  {{ leaveType.name }}
                </th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Alpa</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">OT Full</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Telat</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Total Days</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Detail</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, index) in props.rows" :key="row.user_id">
                <!-- Main Row -->
                <tr :class="index % 2 === 0 ? 'bg-white' : 'bg-blue-50'">
                  <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ index + 1 }}</td>
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
                  <td class="px-4 py-3 text-sm text-center font-mono text-orange-600">
                    {{ formatNumber(row.extra_off_days || 0) }}
                  </td>
                  <!-- Dynamic Leave Type Data -->
                  <td v-for="leaveType in props.leaveTypes" :key="leaveType.id" class="px-4 py-3 text-sm text-center font-mono text-purple-600">
                    {{ formatNumber(getLeaveDays(row, leaveType.name) || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-red-700">
                    {{ formatNumber(row.alpa_days || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-green-600">
                    {{ formatNumber(row.ot_full_days || 0) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono">
                    <span :class="row.total_telat > 0 ? 'text-red-600 font-bold' : 'text-gray-500'">
                      {{ formatNumber(row.total_telat || 0) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-mono text-blue-600 font-semibold">
                    {{ formatNumber(row.total_days || 0) }}
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button 
                      @click="toggleRow(row.user_id)"
                      class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors"
                    >
                      <i :class="isRowExpanded(row.user_id) ? 'fa fa-chevron-up' : 'fa fa-chevron-down'"></i>
                      {{ isRowExpanded(row.user_id) ? 'Tutup' : 'Buka' }}
                    </button>
                  </td>
                </tr>
                
                <!-- Expanded Row - Detail Absensi Harian -->
                <tr v-if="isRowExpanded(row.user_id)" class="bg-gray-50">
                  <td :colspan="13 + (props.leaveTypes ? props.leaveTypes.length : 0)" class="px-4 py-4">
                    <div class="bg-white rounded-lg shadow-sm border">
                      <div class="px-4 py-3 border-b border-gray-200">
                        <h4 class="text-lg font-semibold text-gray-800">Detail Absensi Harian - {{ row.nama_lengkap }}</h4>
                        <p class="text-sm text-gray-600">Total Lembur: <span class="font-bold text-green-600">{{ row.total_lembur || 0 }} jam</span></p>
                      </div>
                      
                      <div class="overflow-x-auto">
                        <table class="w-full">
                          <thead class="bg-gray-100">
                            <tr>
                              <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Tanggal</th>
                              <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Jam Masuk</th>
                              <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Jam Keluar</th>
                              <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">Telat (menit)</th>
                              <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">Lembur (jam)</th>
                              <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">Cross Day</th>
                              <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">Status</th>
                              <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Shift</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(attendance, idx) in row.daily_attendance" :key="idx" 
                                :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                              <td class="px-3 py-2 text-sm text-gray-900">{{ formatDate(attendance.tanggal) }}</td>
                              <td class="px-3 py-2 text-sm text-gray-600 font-mono">{{ formatTime(attendance.jam_masuk) }}</td>
                              <td class="px-3 py-2 text-sm text-gray-600 font-mono">{{ formatTime(attendance.jam_keluar) }}</td>
                              <td class="px-3 py-2 text-sm text-center font-mono">
                                <span :class="attendance.telat > 0 ? 'text-red-600 font-bold' : 'text-gray-500'">
                                  {{ attendance.telat || 0 }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-sm text-center font-mono">
                                <span :class="attendance.lembur > 0 ? 'text-green-600 font-bold' : 'text-gray-500'">
                                  {{ attendance.lembur || 0 }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <span v-if="attendance.is_cross_day" class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-medium">
                                  <i class="fa fa-clock mr-1"></i>Cross Day
                                </span>
                                <span v-else class="text-gray-400 text-xs">-</span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <span v-if="attendance.is_off" class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs">Off</span>
                                <span v-else-if="attendance.is_holiday" class="bg-purple-100 text-purple-600 px-2 py-1 rounded text-xs">
                                  <i class="fa fa-calendar mr-1"></i>{{ attendance.holiday_name || 'Holiday' }}
                                </span>
                                <span v-else class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs">
                                  <i class="fa fa-check mr-1"></i>Hadir
                                </span>
                              </td>
                              <td class="px-3 py-2 text-sm text-gray-600">
                                <div v-if="attendance.shift_start && attendance.shift_end" class="text-xs">
                                  {{ attendance.shift_start }} - {{ attendance.shift_end }}
                                </div>
                                <div v-else class="text-gray-400 text-xs">-</div>
                              </td>
                            </tr>
                            <tr v-if="!row.daily_attendance || row.daily_attendance.length === 0">
                              <td colspan="8" class="text-center py-4 text-gray-400">
                                <i class="fa fa-info-circle mr-2"></i>Tidak ada data absensi
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
    </div>
  </AppLayout>
</template>

<style scoped>
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

/* Animation for expandable rows */
tr {
  transition: all 0.3s ease;
}
</style>
