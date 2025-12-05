<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  data: Array,
  outlets: Array,
  divisions: Array,
  filter: Object,
  summary: Object,
});

const outletId = ref('');
const divisionId = ref('');
const selectedEmployee = ref(null);
const employees = ref([]);
const loadingEmployees = ref(false);
const bulan = ref(new Date().getMonth() + 1);
const tahun = ref(new Date().getFullYear());

const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const tahunOptions = Array.from({length: 5}, (_,i) => new Date().getFullYear() - i);

const showDetail = ref(false);
const detailRows = ref([]);
const detailUser = ref('');
const detailTanggal = ref('');

const showShiftModal = ref(false);
const shiftInfo = ref({});

const isLoading = ref(false);
const isExporting = ref(false);

// Initialize filter values from props
if (props.filter) {
  outletId.value = props.filter.outlet_id || '';
  divisionId.value = props.filter.division_id || '';
  bulan.value = props.filter.bulan || new Date().getMonth() + 1;
  tahun.value = props.filter.tahun || new Date().getFullYear();
  
  // If there's a search value, try to find the employee in the list
  if (props.filter.search) {
    // We'll set this after fetching employees
  }
}

// Watch for changes in outlet and division to refetch employees
watch([outletId, divisionId], async (newValues, oldValues) => {
  // Only fetch if this is not the initial load (oldValues will be undefined on first load)
  if (oldValues && (oldValues[0] !== newValues[0] || oldValues[1] !== newValues[1])) {
    await fetchEmployees();
    // Reset selected employee when filters change
    selectedEmployee.value = null;
  }
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
    
    // If there's a search filter, try to find and set the employee
    if (props.filter && props.filter.search && employees.value.length > 0) {
      const foundEmployee = employees.value.find(emp => emp.name === props.filter.search);
      if (foundEmployee) {
        selectedEmployee.value = foundEmployee;
      }
    }
  } catch (error) {
    console.error('Error fetching employees:', error);
    employees.value = [];
  } finally {
    loadingEmployees.value = false;
  }
}

// Initialize on component mount
onMounted(async () => {
  await fetchEmployees();
});

function applyFilter() {
  isLoading.value = true;
  router.get('/attendance-report', {
    outlet_id: outletId.value,
    division_id: divisionId.value,
    search: selectedEmployee.value ? selectedEmployee.value.name : '',
    bulan: bulan.value,
    tahun: tahun.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false; },
  });
}

async function openDetail(row) {
  showDetail.value = true;
  detailUser.value = row.nama_lengkap;
  detailTanggal.value = row.tanggal;
  detailRows.value = [];
  const res = await axios.get('/attendance-report/detail', { params: { user_id: row.user_id, tanggal: row.tanggal } });
  detailRows.value = res.data;
}

function closeDetail() {
  showDetail.value = false;
  detailRows.value = [];
}

async function openShiftModal(row) {
  showShiftModal.value = true;
  shiftInfo.value = {};
  // Fetch shift info from backend
  const res = await axios.get('/attendance-report/shift-info', { params: { user_id: row.user_id, tanggal: row.tanggal } });
  shiftInfo.value = res.data;
}

function closeShiftModal() {
  showShiftModal.value = false;
  shiftInfo.value = {};
}

const exportExcel = () => {
  isExporting.value = true;
  const params = new URLSearchParams();
  if (outletId.value) params.append('outlet_id', outletId.value);
  if (divisionId.value) params.append('division_id', divisionId.value);
  if (selectedEmployee.value) params.append('search', selectedEmployee.value.name);
  if (bulan.value) params.append('bulan', bulan.value);
  if (tahun.value) params.append('tahun', tahun.value);
  // Trik: loading minimal 2 detik, lalu reset
  setTimeout(() => { isExporting.value = false; }, 2000);
  window.location.href = `/attendance-report/export?${params.toString()}`;
};

const getRowTooltip = (row) => {
  if (row.is_holiday) {
    return row.holiday_name;
  }
  if (row.is_approved_absent) {
    return row.approved_absent_name;
  }
  if (row.has_no_checkout) {
    return 'Karyawan tidak melakukan checkout pada hari ini';
  }
  return '';
};
</script>

<template>
  <AppLayout title="Report Attendance">
    <div class="w-full px-2 md:px-4 py-8">
      <div v-if="isLoading || isExporting" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
        <div class="flex flex-col items-center gap-4">
          <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
          </svg>
          <div class="text-lg font-bold text-blue-700">
            {{ isExporting ? 'Mengekspor ke Excel...' : 'Memuat data...' }}
          </div>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="text-lg font-semibold text-gray-700 mb-4">Filter Report</div>
        <div class="flex flex-col md:flex-row md:items-end gap-4">
        <div class="flex-1 min-w-[180px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
          <select v-model="outletId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
          <select v-model="divisionId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Divisi</option>
            <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Karyawan</label>
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
            class="w-full"
          />
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
          <div>
            <button @click="applyFilter" :disabled="isLoading || isExporting" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg">
              <i class="fa fa-search mr-2"></i> Tampilkan
            </button>
          </div>
        </div>
        <div class="flex justify-end items-center mt-4">
          <button @click="exportExcel" :disabled="isLoading || isExporting" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-xl shadow flex items-center gap-2">
            <i class="fa fa-file-excel-o"></i> Export to Excel
          </button>
        </div>
      </div>
      
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">Data Absensi</h3>
            <div class="text-sm text-gray-500">
              Total: {{ props.data ? props.data.length : 0 }} record
            </div>
          </div>
        </div>
        
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
            <tr v-for="(row, idx) in props.data" :key="row.tanggal + '-' + row.nama_lengkap + '-' + (row.outlet_id || 'no-outlet')"
              :class="[
                row.is_holiday ? 'bg-red-100 text-red-700 font-bold' : '',
                !row.is_holiday && row.is_approved_absent ? 'bg-green-100 text-green-700 font-bold' : '',
                !row.is_holiday && !row.is_approved_absent && row.is_off ? 'bg-gray-200 text-gray-500 font-bold italic' : '',
                !row.is_holiday && !row.is_approved_absent && !row.is_off && row.has_no_checkout ? 'bg-red-200 text-red-800 font-semibold' : '',
                !row.is_holiday && !row.is_approved_absent && !row.is_off && !row.has_no_checkout && idx % 2 === 1 ? 'bg-blue-50' : ''
              ]">
              <td class="px-4 py-2 whitespace-nowrap font-mono" :title="getRowTooltip(row)">
                {{ row.tanggal }}
                <span v-if="row.is_holiday && row.holiday_name" class="ml-1 text-xs font-semibold">({{ row.holiday_name }})</span>
                <span v-if="!row.is_holiday && row.is_approved_absent && row.approved_absent_name" class="ml-1 text-xs font-semibold">({{ row.approved_absent_name }})</span>
                <span v-if="row.has_no_checkout" class="ml-1 text-xs font-semibold text-red-600">(TIDAK CHECKOUT)</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">{{ row.nama_lengkap }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                <span v-if="row.is_off">OFF</span>
                <span v-else-if="row.approved_absent" class="text-green-600 font-semibold">
                  <i class="fa-solid fa-check-circle mr-1"></i>{{ row.approved_absent.leave_type_name }}
                </span>
                <span v-else>{{ row.jam_masuk || '-' }}</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                <span v-if="row.is_off">OFF</span>
                <span v-else-if="row.approved_absent" class="text-green-600 font-semibold">
                  <i class="fa-solid fa-check-circle mr-1"></i>{{ row.approved_absent.leave_type_name }}
                </span>
                <span v-else-if="row.has_no_checkout" class="text-red-600 font-bold">
                  <i class="fa-solid fa-exclamation-triangle mr-1"></i>TIDAK CHECKOUT
                </span>
                <span v-else>{{ row.jam_keluar || '-' }}</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center font-mono text-xs">
                <span v-if="row.is_off">OFF</span>
                <span v-else class="flex flex-col">
                  <span class="text-green-600 font-semibold">{{ row.total_masuk || 0 }} IN</span>
                  <span class="text-red-600 font-semibold">{{ row.total_keluar || 0 }} OUT</span>
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                <span v-if="row.is_off">OFF</span>
                <span v-else>{{ row.telat }}</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                <span v-if="row.is_off">OFF</span>
                <span v-else>
                  {{ row.lembur }}
                  <span v-if="row.is_cross_day" 
                        class="text-xs text-orange-600 font-semibold ml-1" title="Cross-day overtime">
                    🌙
                  </span>
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <button v-if="!row.is_off" @click="openDetail(row)" class="px-3 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition text-xs font-semibold">
                  <i class="fa fa-list mr-1"></i> Detail
                </button>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <button v-if="!row.is_off" @click="openShiftModal(row)" class="px-3 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 transition text-xs font-semibold">
                  <i class="fa fa-clock mr-1"></i> Shift
                </button>
                <span v-else>-</span>
              </td>
            </tr>
            <tr v-if="!props.data || props.data.length === 0">
              <td colspan="10" class="text-center py-8 text-gray-400">
                <div class="flex flex-col items-center gap-2">
                  <i class="fa fa-search text-4xl text-gray-300"></i>
                  <div class="text-lg font-medium">Tidak ada data absensi</div>
                  <div class="text-sm text-gray-500">Silakan pilih filter dan klik tombol "Tampilkan" untuk melihat data</div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
      <div v-if="props.summary" class="flex gap-6 mt-6 mb-10">
        <div class="bg-blue-100 text-blue-800 rounded-xl px-6 py-4 font-bold text-lg shadow flex items-center gap-2">
          <i class="fa fa-clock"></i> Total Telat: <span class="ml-2">{{ props.summary.total_telat }} menit</span>
        </div>
        <div class="bg-green-100 text-green-800 rounded-xl px-6 py-4 font-bold text-lg shadow flex items-center gap-2">
          <i class="fa fa-business-time"></i> Total Lembur: <span class="ml-2">{{ props.summary.total_lembur }} jam</span>
        </div>
      </div>
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
                <th class="px-4 py-2 text-center">Outlet</th>
                <th class="px-4 py-2 text-center">Jam In</th>
                <th class="px-4 py-2 text-center">Jam Out</th>
                <th class="px-4 py-2 text-center">Total IN</th>
                <th class="px-4 py-2 text-center">Total OUT</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(d, i) in detailRows" :key="i">
                <td class="px-4 py-2 whitespace-nowrap text-center font-medium text-blue-600">{{ d.nama_outlet }}</td>
                <td class="px-4 py-2 whitespace-nowrap text-center font-mono">{{ d.jam_in }}</td>
                <td class="px-4 py-2 whitespace-nowrap text-center font-mono">{{ d.jam_out }}</td>
                <td class="px-4 py-2 whitespace-nowrap text-center font-mono text-green-600 font-semibold">{{ d.total_in }}</td>
                <td class="px-4 py-2 whitespace-nowrap text-center font-mono text-red-600 font-semibold">{{ d.total_out }}</td>
              </tr>
              <tr v-if="!detailRows.length">
                <td colspan="5" class="text-center py-6 text-gray-400">Tidak ada data</td>
              </tr>
            </tbody>
          </table>
          <div class="modal-detail-footer">
            <button @click="closeDetail" class="modal-detail-btn">Tutup</button>
          </div>
          <button @click="closeDetail" class="modal-detail-close"><i class="fa fa-times"></i></button>
        </div>
      </div>
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
.animate-fade-in {
  animation: fadeIn 0.4s cubic-bezier(.4,0,.2,1);
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: none; }
}

/* Multiselect styling */
:deep(.multiselect) {
  min-height: 42px;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__input) {
  background: transparent;
  border: none;
  outline: none;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

:deep(.multiselect__single) {
  background: transparent;
  padding: 0.5rem 0;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__option) {
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

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

/* Ensure table is full width */
table {
  table-layout: auto;
  width: 100%;
}

/* Main attendance table specific styles */
.bg-white.rounded-2xl.shadow-lg table {
  width: 100%;
  border-collapse: collapse;
}

.bg-white.rounded-2xl.shadow-lg th,
.bg-white.rounded-2xl.shadow-lg td {
  padding: 0.75rem 1rem;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

/* Modal table specific styles */
.modal-detail-table {
  width: 100%;
  border-collapse: collapse;
}

.modal-detail-table th,
.modal-detail-table td {
  padding: 0.75rem 1rem;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
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
</style> 