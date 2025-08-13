<script setup>
import { ref, watch, onMounted, nextTick, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  outlets: Array,
  months: Array,
  years: Array,
  payrollData: Array,
  filter: Object,
});

const outletId = ref(props.filter?.outlet_id || '');
const month = ref(props.filter?.month || new Date().getMonth() + 1);
const year = ref(props.filter?.year || new Date().getFullYear());
const loading = ref(false);
const exporting = ref(false);

// Format month to 2 digits
const formatMonth = (month) => {
  return month.toString().padStart(2, '0');
};

function lihatData() {
  if (!outletId.value || !month.value || !year.value) return;
  loading.value = true;
  router.get('/payroll/report', {
    outlet_id: outletId.value,
    month: formatMonth(month.value),
    year: year.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => nextTick(() => { loading.value = false; })
  });
}

async function exportData() {
  if (!outletId.value || !month.value || !year.value) {
    Swal.fire('Peringatan', 'Pilih outlet, bulan, dan tahun terlebih dahulu', 'warning');
    return;
  }

  exporting.value = true;
  try {
    const url = `/payroll/report/export?outlet_id=${outletId.value}&month=${formatMonth(month.value)}&year=${year.value}`;
    window.open(url, '_blank');
  } catch (error) {
    console.error('Error exporting:', error);
    Swal.fire('Error', 'Terjadi kesalahan saat export data', 'error');
  } finally {
    exporting.value = false;
  }
}

// Format currency
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID').format(amount);
};

// Calculate summary
const summary = computed(() => {
  if (!props.payrollData || props.payrollData.length === 0) {
    return {
      totalGajiPokok: 0,
      totalTunjangan: 0,
      totalGajiLembur: 0,
      totalPotonganTelat: 0,
      totalGaji: 0,
    };
  }

  return {
    totalGajiPokok: props.payrollData.reduce((sum, item) => sum + item.gaji_pokok, 0),
    totalTunjangan: props.payrollData.reduce((sum, item) => sum + item.tunjangan, 0),
    totalGajiLembur: props.payrollData.reduce((sum, item) => sum + item.gaji_lembur, 0),
    totalPotonganTelat: props.payrollData.reduce((sum, item) => sum + item.potongan_telat, 0),
    totalGaji: props.payrollData.reduce((sum, item) => sum + item.total_gaji, 0),
    avgGajiPerMenit: props.payrollData.length > 0 ? 
      props.payrollData.reduce((sum, item) => sum + item.gaji_per_menit, 0) / props.payrollData.length : 0,
    totalHariKerja: props.payrollData.reduce((sum, item) => sum + item.hari_kerja, 0),
  };
});

watch(() => props.filter, (newFilter) => {
  outletId.value = newFilter?.outlet_id || '';
  month.value = newFilter?.month || new Date().getMonth() + 1;
  year.value = newFilter?.year || new Date().getFullYear();
}, { immediate: true });
</script>

<template>
  <AppLayout title="Laporan Payroll">
    <div class="w-full min-h-[60vh] h-[calc(100vh-150px)] flex flex-col justify-start items-stretch py-4">
      <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-20 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-blue-500"></div>
      </div>
      
      <h1 class="text-2xl font-bold text-blue-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-file-invoice-dollar text-green-500"></i> Laporan Payroll
      </h1>
      
      <!-- Filter Section -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <div class="flex gap-4 items-center flex-wrap">
          <select v-model="outletId" class="form-input rounded-xl shadow-lg w-80" autofocus>
            <option value="">Pilih Outlet</option>
            <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
          
          <select v-model="month" class="form-input rounded-xl shadow-lg w-60">
            <option value="">Pilih Bulan</option>
            <option v-for="m in props.months" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
          
          <select v-model="year" class="form-input rounded-xl shadow-lg w-40">
            <option value="">Pilih Tahun</option>
            <option v-for="y in props.years" :key="y.id" :value="y.id">{{ y.name }}</option>
          </select>
          
          <button
            @click="lihatData"
            class="bg-gradient-to-br from-green-400 to-blue-500 text-white px-6 py-2 rounded-xl shadow-xl hover:scale-105 hover:shadow-2xl transition-all duration-300 font-bold"
            :disabled="!outletId || !month || !year"
          >
            <i class="fa-solid fa-magnifying-glass"></i> Lihat Data
          </button>
          
          <button
            @click="exportData"
            class="bg-gradient-to-br from-yellow-400 to-orange-400 text-white px-6 py-2 rounded-xl shadow-xl hover:scale-105 hover:shadow-2xl transition-all duration-300 font-bold"
            :disabled="!outletId || !month || !year || exporting"
          >
            <i class="fa-solid fa-file-export"></i> {{ exporting ? 'Exporting...' : 'Export Excel' }}
          </button>
        </div>
      </div>

             <!-- Data Section -->
       <div class="flex-1 w-full">
        <div v-if="props.payrollData && props.payrollData.length > 0" class="w-full h-full">
          <!-- Summary Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-400 to-blue-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Gaji Pokok</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalGajiPokok) }}</div>
            </div>
            <div class="bg-gradient-to-br from-green-400 to-green-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Tunjangan</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalTunjangan) }}</div>
            </div>
            <div class="bg-gradient-to-br from-yellow-400 to-yellow-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Gaji Lembur</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalGajiLembur) }}</div>
            </div>
            <div class="bg-gradient-to-br from-red-400 to-red-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Potongan Telat</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalPotonganTelat) }}</div>
            </div>
            <div class="bg-gradient-to-br from-purple-400 to-purple-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Gaji</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalGaji) }}</div>
            </div>
            <div class="bg-gradient-to-br from-indigo-400 to-indigo-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Rata-rata Gaji/Menit</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.avgGajiPerMenit) }}</div>
            </div>
            <div class="bg-gradient-to-br from-teal-400 to-teal-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Hari Kerja</div>
              <div class="text-2xl font-bold">{{ summary.totalHariKerja }}</div>
            </div>
          </div>

          <!-- Period Info -->
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="text-blue-800 font-semibold">
              <i class="fa-solid fa-calendar-days mr-2"></i>
              Periode: {{ props.payrollData[0]?.periode }}
            </div>
          </div>

                     <!-- Table -->
           <div class="table-container overflow-x-auto overflow-y-auto max-h-[60vh] border border-gray-200 rounded-2xl">
             <table class="w-full min-w-[1200px] divide-y divide-blue-200 bg-white rounded-2xl shadow-2xl animate-fade-in-up">
               <thead class="bg-gradient-to-r from-blue-600 to-green-400 text-white sticky top-0 z-20">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">NIK</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jabatan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Divisi</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Gaji Pokok</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Tunjangan</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Menit Telat</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Jam Lembur</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Gaji Lembur</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Potongan Telat</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Hari Kerja</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Total Gaji</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Gaji/Menit</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in props.payrollData" :key="item.user_id" class="hover:bg-blue-50 transition-colors">
                  <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.nik }}</td>
                  <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ item.nama_lengkap }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ item.jabatan }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ item.divisi }}</td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-blue-600">
                    {{ formatCurrency(item.gaji_pokok) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-green-600">
                    {{ formatCurrency(item.tunjangan) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-red-600">
                    {{ item.total_telat }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-yellow-600">
                    {{ item.total_lembur }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-yellow-700">
                    {{ formatCurrency(item.gaji_lembur) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-red-700">
                    {{ formatCurrency(item.potongan_telat) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-blue-600">
                    {{ item.hari_kerja }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-purple-600 bg-purple-50 rounded-lg">
                    {{ formatCurrency(item.total_gaji) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-center font-bold text-gray-600">
                    {{ formatCurrency(item.gaji_per_menit) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Empty State -->
        <div v-else-if="outletId && month && year" class="flex flex-col items-center justify-center h-full text-gray-500">
          <i class="fa-solid fa-file-invoice-dollar text-6xl mb-4 text-gray-300"></i>
          <h3 class="text-xl font-semibold mb-2">Tidak ada data payroll</h3>
          <p class="text-gray-400">Data payroll untuk outlet, bulan, dan tahun yang dipilih tidak ditemukan.</p>
        </div>
        
        <!-- Initial State -->
        <div v-else class="flex flex-col items-center justify-center h-full text-gray-500">
          <i class="fa-solid fa-filter text-6xl mb-4 text-gray-300"></i>
          <h3 class="text-xl font-semibold mb-2">Pilih Filter</h3>
          <p class="text-gray-400">Silakan pilih outlet, bulan, dan tahun untuk melihat data payroll.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.animate-fade-in-up {
  animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Sticky header styles */
.table-container {
  position: relative;
}

.table-container thead th {
  position: sticky;
  top: 0;
  z-index: 20;
  background: linear-gradient(to right, #2563eb, #4ade80) !important;
  border-bottom: 2px solid #1e40af;
}

/* Ensure header cells have proper background */
.table-container thead th::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(to right, #2563eb, #4ade80);
  z-index: -1;
}

/* Add shadow to sticky header */
.table-container thead {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Ensure table body scrolls properly */
.table-container tbody {
  background: white;
}
</style>
