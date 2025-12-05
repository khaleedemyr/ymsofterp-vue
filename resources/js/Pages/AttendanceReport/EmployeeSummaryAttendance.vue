<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  employeeRows: {
    type: Array,
    default: () => []
  },
  outlets: Array,
  divisions: Array,
  filter: Object,
});

const outletId = ref('');
const divisionId = ref('');
const bulan = ref(new Date().getMonth() + 1);
const tahun = ref(new Date().getFullYear());

const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const tahunOptions = Array.from({length: 5}, (_,i) => new Date().getFullYear() - i);

const isLoading = ref(false);
const isExporting = ref(false);
const expandedEmployees = ref(new Set());

// Initialize filter values from props
if (props.filter) {
  outletId.value = props.filter.outlet_id || '';
  divisionId.value = props.filter.division_id || '';
  bulan.value = props.filter.bulan || new Date().getMonth() + 1;
  tahun.value = props.filter.tahun || new Date().getFullYear();
}

const search = () => {
  isLoading.value = true;
  router.get(route('attendance-report.employee-summary-attendance'), {
    outlet_id: outletId.value,
    division_id: divisionId.value,
    bulan: bulan.value,
    tahun: tahun.value,
  }, {
    preserveState: true,
    onFinish: () => {
      isLoading.value = false;
    }
  });
};

const toggleEmployee = (employeeKey) => {
  if (expandedEmployees.value.has(employeeKey)) {
    expandedEmployees.value.delete(employeeKey);
  } else {
    expandedEmployees.value.add(employeeKey);
  }
};

const formatTime = (timeString) => {
  if (!timeString) return '-';
  return new Date(timeString).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
};

const formatDuration = (minutes) => {
  if (!minutes || minutes === 0) return '0 menit';
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  if (hours > 0) {
    return `${hours} jam ${mins} menit`;
  }
  return `${mins} menit`;
};

const formatOvertime = (hours) => {
  if (!hours || hours === 0) return '0 jam';
  return `${hours} jam`;
};

const exportToExcel = async () => {
  isExporting.value = true;
  try {
    const response = await axios.post(route('attendance-report.employee-summary-attendance.export'), {
      outlet_id: outletId.value,
      division_id: divisionId.value,
      bulan: bulan.value,
      tahun: tahun.value,
    }, {
      responseType: 'blob'
    });
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `employee-summary-attendance-${bulan.value}-${tahun.value}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    console.error('Export error:', error);
    alert('Gagal mengexport data');
  } finally {
    isExporting.value = false;
  }
};

// Watch for changes in filters to reset expanded states
watch([outletId, divisionId, bulan, tahun], () => {
  expandedEmployees.value.clear();
});

onMounted(() => {
  // Auto search if filters are provided
  if (outletId.value || divisionId.value || bulan.value || tahun.value) {
    search();
  }
});
</script>

<template>
  <AppLayout title="Employee Summary Attendance">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Employee Summary Attendance
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Filter Section -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6">
          <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-700">Filter Data</h3>
          </div>
          <div class="p-6">
            <div class="flex flex-wrap gap-4 items-end">

              <!-- Outlet -->
              <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                <Multiselect
                  v-model="outletId"
                  :options="outlets"
                  :searchable="true"
                  placeholder="Pilih Outlet"
                  label="name"
                  track-by="id"
                  :allow-empty="true"
                  :close-on-select="true"
                />
              </div>

              <!-- Division -->
              <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Division</label>
                <Multiselect
                  v-model="divisionId"
                  :options="divisions"
                  :searchable="true"
                  placeholder="Pilih Division"
                  label="name"
                  track-by="id"
                  :allow-empty="true"
                  :close-on-select="true"
                />
              </div>

              <!-- Bulan -->
              <div class="flex-1 min-w-[120px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
                <select 
                  v-model="bulan" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option v-for="(month, index) in monthNames" :key="index" :value="index + 1">
                    {{ month }}
                  </option>
                </select>
              </div>

              <!-- Tahun -->
              <div class="flex-1 min-w-[100px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
                <select 
                  v-model="tahun" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option v-for="year in tahunOptions" :key="year" :value="year">
                    {{ year }}
                  </option>
                </select>
              </div>

              <!-- Action Buttons -->
              <div class="flex gap-2">
                <button
                  @click="search"
                  :disabled="isLoading"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg"
                >
                  <i class="fa fa-search mr-2"></i> Tampilkan
                </button>
                <button
                  @click="exportToExcel"
                  :disabled="isExporting || !employeeRows || employeeRows.length === 0"
                  class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-xl shadow flex items-center gap-2"
                >
                  <i class="fa fa-file-excel-o"></i> Export to Excel
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Results Section -->
        <div v-if="employeeRows.length > 0" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">
                Employee Summary Attendance
              </h3>
              <div class="text-sm text-gray-500">
                Total: {{ employeeRows.length }} employees
              </div>
            </div>
          </div>

          <!-- Summary Cards -->
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <div class="text-sm font-medium text-blue-600">Total Employees</div>
                <div class="text-2xl font-bold text-blue-900">{{ employeeRows.length }}</div>
              </div>
              <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <div class="text-sm font-medium text-green-600">Total Working Days</div>
                <div class="text-2xl font-bold text-green-900">
                  {{ employeeRows.reduce((sum, emp) => sum + emp.total_working_days, 0) }}
                </div>
              </div>
              <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <div class="text-sm font-medium text-yellow-600">Total Telat (menit)</div>
                <div class="text-2xl font-bold text-yellow-900">
                  {{ employeeRows.reduce((sum, emp) => sum + emp.total_telat, 0) }}
                </div>
              </div>
              <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                <div class="text-sm font-medium text-purple-600">Total Lembur (jam)</div>
                <div class="text-2xl font-bold text-purple-900">
                  {{ employeeRows.reduce((sum, emp) => sum + emp.total_lembur, 0) }}
                </div>
              </div>
            </div>

            <!-- Employee Table -->
            <div class="overflow-x-auto">
              <table class="w-full divide-y divide-blue-200">
                <thead class="bg-blue-600 text-white">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">
                      Employee
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                      Outlet
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                      Division
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                      Total Telat
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                      Total Lembur
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                      Total Off
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                      Working Days
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                      Aksi
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-blue-200">
                  <tr v-for="(employee, idx) in employeeRows" :key="employee.user_id" 
                      :class="idx % 2 === 1 ? 'bg-blue-50' : ''" class="hover:bg-blue-100">
                    <td class="px-4 py-2 whitespace-nowrap font-medium text-gray-900">
                      {{ employee.nama_lengkap }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-center font-mono text-sm">
                      {{ employee.nama_outlet || '-' }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-center font-mono text-sm">
                      {{ employee.nama_divisi || '-' }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                      <span :class="employee.total_telat > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'">
                        {{ formatDuration(employee.total_telat) }}
                      </span>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                      <span :class="employee.total_lembur > 0 ? 'text-green-600 font-semibold' : 'text-gray-500'">
                        {{ formatOvertime(employee.total_lembur) }}
                      </span>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                      {{ employee.total_off }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                      {{ employee.total_working_days }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-center">
                      <button
                        @click="toggleEmployee(employee.user_id)"
                        class="px-3 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition text-xs font-semibold"
                      >
                        <i class="fa fa-list mr-1"></i>
                        {{ expandedEmployees.has(employee.user_id) ? 'Collapse' : 'View Details' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Daily Details (Expandable) -->
          <div v-for="employee in employeeRows" :key="`daily-${employee.user_id}`"
               v-show="expandedEmployees.has(employee.user_id)"
               class="border-t border-gray-200">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
              <h5 class="text-md font-semibold text-gray-700">
                Daily Details - {{ employee.nama_lengkap }}
              </h5>
            </div>
            
            <div class="p-6">
              <div class="overflow-x-auto">
                <table class="w-full divide-y divide-blue-200">
                  <thead class="bg-blue-600 text-white">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">
                        Tanggal
                      </th>
                      <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                        Jam Masuk
                      </th>
                      <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                        Jam Keluar
                      </th>
                      <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                        Telat
                      </th>
                      <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                        Lembur
                      </th>
                      <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                        Status
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-blue-200">
                    <tr v-for="(detail, idx) in employee.details" :key="detail.tanggal" 
                        :class="idx % 2 === 1 ? 'bg-blue-50' : ''" class="hover:bg-blue-100">
                      <td class="px-4 py-2 whitespace-nowrap font-mono text-gray-900">
                        {{ formatDate(detail.tanggal) }}
                      </td>
                      <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                        {{ formatTime(detail.jam_masuk) }}
                      </td>
                      <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                        {{ formatTime(detail.jam_keluar) }}
                      </td>
                      <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                        <span :class="detail.telat > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'">
                          {{ formatDuration(detail.telat) }}
                        </span>
                      </td>
                      <td class="px-4 py-2 whitespace-nowrap text-center font-mono">
                        <span :class="detail.lembur > 0 ? 'text-green-600 font-semibold' : 'text-gray-500'">
                          {{ formatOvertime(detail.lembur) }}
                        </span>
                      </td>
                      <td class="px-4 py-2 whitespace-nowrap text-center">
                        <span v-if="detail.is_off" class="text-red-600 font-semibold">OFF</span>
                        <span v-else-if="detail.is_cross_day" class="text-blue-600 font-semibold">Cross Day</span>
                        <span v-else class="text-green-600 font-semibold">Normal</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- No Data Message -->
        <div v-else-if="!isLoading" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="p-6 text-center text-gray-500">
            <i class="fa fa-info-circle text-4xl text-gray-400 mb-4"></i>
            <p class="text-lg font-medium">Tidak ada data untuk ditampilkan</p>
            <p class="text-sm">Silakan pilih filter dan klik Tampilkan</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
