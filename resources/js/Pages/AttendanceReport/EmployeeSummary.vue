<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

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
  router.get('/attendance-report/employee-summary', {
    outlet_id: outletId.value || '',
    division_id: divisionId.value || '',
    bulan: bulan.value,
    tahun: tahun.value,
  }, { preserveState: true, replace: true })
}

function exportExcel() {
  // Buat URL dengan parameter yang sama seperti filter
  const params = new URLSearchParams({
    outlet_id: outletId.value || '',
    division_id: divisionId.value || '',
    bulan: bulan.value,
    tahun: tahun.value,
  })
  
  // Redirect ke URL export
  window.location.href = `/attendance-report/employee-summary/export?${params.toString()}`
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
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Outlet</th>
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
              <tr v-for="(row, index) in props.rows" :key="row.user_id" 
                  :class="index % 2 === 0 ? 'bg-white' : 'bg-blue-50'">
                <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ index + 1 }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ row.nama_outlet || '-' }}</td>
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
              </tr>
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
</style>
