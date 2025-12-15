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
const serviceCharge = ref(props.filter?.service_charge || '');
const loading = ref(false);
const exporting = ref(false);

// Expandable rows state
const expandedRows = ref(new Set());
const attendanceDetails = ref({});
const loadingDetails = ref({});

// Custom items state
const showAddCustomItemModal = ref(false);
const showCustomItemsModal = ref(false);
const selectedCustomItems = ref([]);
const selectedItemType = ref('');
const selectedUserId = ref(null);
const customItemForm = ref({
  user_id: '',
  outlet_id: '',
  payroll_period_month: '',
  payroll_period_year: '',
  item_type: 'earn',
  item_name: '',
  item_amount: '',
  item_description: ''
});

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
    service_charge: serviceCharge.value || null,
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
    const url = `/payroll/report/export?outlet_id=${outletId.value}&month=${formatMonth(month.value)}&year=${year.value}&service_charge=${serviceCharge.value || 0}`;
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
      totalUangMakan: 0,
      totalServiceChargeByPoint: 0,
      totalServiceChargeProRate: 0,
      totalServiceCharge: 0,
      totalBPJSJKN: 0,
      totalBPJSTK: 0,
      totalPotonganTelat: 0,
      totalGaji: 0,
    };
  }

  return {
    totalGajiPokok: props.payrollData.reduce((sum, item) => sum + Number(item.gaji_pokok), 0),
    totalTunjangan: props.payrollData.reduce((sum, item) => sum + Number(item.tunjangan), 0),
    totalGajiLembur: props.payrollData.reduce((sum, item) => sum + Number(item.gaji_lembur), 0),
    totalUangMakan: props.payrollData.reduce((sum, item) => sum + Number(item.uang_makan), 0),
    totalServiceChargeByPoint: props.payrollData.reduce((sum, item) => sum + Number(item.service_charge_by_point || 0), 0),
    totalServiceChargeProRate: props.payrollData.reduce((sum, item) => sum + Number(item.service_charge_pro_rate || 0), 0),
    totalServiceCharge: props.payrollData.reduce((sum, item) => sum + Number(item.service_charge || 0), 0),
    totalBPJSJKN: props.payrollData.reduce((sum, item) => sum + Number(item.bpjs_jkn), 0),
    totalBPJSTK: props.payrollData.reduce((sum, item) => sum + Number(item.bpjs_tk), 0),
    totalPotonganTelat: props.payrollData.reduce((sum, item) => sum + Number(item.potongan_telat), 0),
    totalGaji: props.payrollData.reduce((sum, item) => sum + Number(item.total_gaji), 0),
  };
});

watch(() => props.filter, (newFilter) => {
  outletId.value = newFilter?.outlet_id || '';
  month.value = newFilter?.month || new Date().getMonth() + 1;
  year.value = newFilter?.year || new Date().getFullYear();
  serviceCharge.value = newFilter?.service_charge || '';
}, { immediate: true });

// Toggle expand row
async function toggleExpand(userId) {
  if (expandedRows.value.has(userId)) {
    expandedRows.value.delete(userId);
    delete attendanceDetails.value[userId];
  } else {
    expandedRows.value.add(userId);
    await fetchAttendanceDetail(userId);
  }
}

// Fetch attendance detail for specific user
async function fetchAttendanceDetail(userId) {
  if (attendanceDetails.value[userId]) return; // Already loaded
  
  loadingDetails.value[userId] = true;
  try {
    // Calculate payroll period (26th of previous month to 25th of current month)
    let startDate, endDate;
    
    if (month.value === 1) {
      // January: 26 Dec previous year to 25 Jan current year
      startDate = new Date(year.value - 1, 11, 26);
      endDate = new Date(year.value, 0, 25);
    } else {
      // Other months: 26 previous month to 25 current month
      startDate = new Date(year.value, month.value - 2, 26);
      endDate = new Date(year.value, month.value - 1, 25);
    }
    
    console.log('Fetching attendance detail:', {
      userId,
      outletId: outletId.value,
      startDate: startDate.toISOString().split('T')[0],
      endDate: endDate.toISOString().split('T')[0]
    });
    
    const response = await axios.get('/payroll/report/attendance-detail', {
      params: {
        user_id: userId,
        outlet_id: outletId.value,
        start_date: startDate.toISOString().split('T')[0],
        end_date: endDate.toISOString().split('T')[0]
      }
    });
    
    console.log('Attendance detail response:', response.data);
    attendanceDetails.value[userId] = response.data;
  } catch (error) {
    console.error('Error fetching attendance detail:', error);
    console.error('Error details:', {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    attendanceDetails.value[userId] = [];
  } finally {
    loadingDetails.value[userId] = false;
  }
}

// Open add custom item modal with pre-filled user data
function openAddCustomItemModal(userId, userName) {
  customItemForm.value.user_id = userId;
  customItemForm.value.outlet_id = outletId.value;
  customItemForm.value.payroll_period_month = parseInt(month.value);
  customItemForm.value.payroll_period_year = parseInt(year.value);
  showAddCustomItemModal.value = true;
}

// Show custom items modal
function showCustomItems(userId, itemType) {
  const user = props.payrollData.find(item => item.user_id === userId);
  if (user && user.custom_items) {
    selectedCustomItems.value = user.custom_items.filter(item => item.item_type === itemType);
    selectedItemType.value = itemType;
    selectedUserId.value = userId;
    showCustomItemsModal.value = true;
  }
}

// Add custom item
async function addCustomItem() {
  try {
    // Ensure numeric values are properly formatted
    const formData = {
      ...customItemForm.value,
      user_id: parseInt(customItemForm.value.user_id),
      outlet_id: parseInt(customItemForm.value.outlet_id),
      payroll_period_month: parseInt(customItemForm.value.payroll_period_month),
      payroll_period_year: parseInt(customItemForm.value.payroll_period_year),
      item_amount: parseFloat(customItemForm.value.item_amount)
    };

    const response = await axios.post('/payroll/report/custom-item/add', formData);
    
    if (response.data.success) {
      Swal.fire('Sukses', response.data.message, 'success');
      showAddCustomItemModal.value = false;
      resetCustomItemForm();
      // Reload data
      await lihatData();
    } else {
      Swal.fire('Error', response.data.message, 'error');
    }
  } catch (error) {
    console.error('Error adding custom item:', error);
    if (error.response?.data?.message) {
      Swal.fire('Error', error.response.data.message, 'error');
    } else {
      Swal.fire('Error', 'Terjadi kesalahan saat menambah custom item', 'error');
    }
  }
}

// Delete custom item
async function deleteCustomItem(itemId) {
  try {
    const result = await Swal.fire({
      title: 'Konfirmasi',
      text: 'Apakah Anda yakin ingin menghapus item ini?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    });

    if (result.isConfirmed) {
      const response = await axios.delete('/payroll/report/custom-item/delete', {
        data: { item_id: itemId }
      });
      
      if (response.data.success) {
        Swal.fire('Sukses', response.data.message, 'success');
        // Reload data
        await lihatData();
      } else {
        Swal.fire('Error', response.data.message, 'error');
      }
    }
  } catch (error) {
    console.error('Error deleting custom item:', error);
    Swal.fire('Error', 'Terjadi kesalahan saat menghapus custom item', 'error');
  }
}

// Reset custom item form
function resetCustomItemForm() {
  customItemForm.value = {
    user_id: customItemForm.value.user_id, // Keep the selected user
    outlet_id: outletId.value,
    payroll_period_month: parseInt(month.value),
    payroll_period_year: parseInt(year.value),
    item_type: 'earn',
    item_name: '',
    item_amount: '',
    item_description: ''
  };
}

// Print payroll for specific employee
async function printPayroll(employee) {
  try {
    const url = `/payroll/report/print?user_id=${employee.user_id}&outlet_id=${outletId.value}&month=${month.value}&year=${year.value}`;
    window.open(url, '_blank');
  } catch (error) {
    console.error('Error printing payroll:', error);
    Swal.fire('Error', 'Terjadi kesalahan saat print payroll', 'error');
  }
}

// Show payroll as HTML for specific employee
async function showPayroll(employee) {
  try {
    const url = `/payroll/report/show?user_id=${employee.user_id}&outlet_id=${outletId.value}&month=${month.value}&year=${year.value}`;
    window.open(url, '_blank');
  } catch (error) {
    console.error('Error showing payroll:', error);
    Swal.fire('Error', 'Terjadi kesalahan saat menampilkan payroll', 'error');
  }
}
</script>

<template>
  <AppLayout title="Payroll">
    <div class="w-full min-h-[60vh] h-[calc(100vh-150px)] flex flex-col justify-start items-stretch py-4">
      <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-20 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-blue-500"></div>
      </div>
      
      <h1 class="text-2xl font-bold text-blue-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-file-invoice-dollar text-green-500"></i> Payroll
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
          
          <input
            v-model="serviceCharge"
            type="number"
            step="0.01"
            min="0"
            placeholder="Service Charge"
            class="form-input rounded-xl shadow-lg w-48"
          />
          
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
        <div v-if="props.payrollData && props.payrollData.length > 0" class="w-full">
          <!-- Summary Cards -->
                      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-11 gap-4 mb-6">
            <div class="bg-gradient-to-br from-green-400 to-green-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Gaji Pokok</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalGajiPokok) }}</div>
            </div>
            <div class="bg-gradient-to-br from-green-400 to-green-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Tunjangan</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalTunjangan) }}</div>
            </div>
            <div class="bg-gradient-to-br from-green-400 to-green-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Gaji Lembur</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalGajiLembur) }}</div>
            </div>
            <div class="bg-gradient-to-br from-red-400 to-red-600 text-white p-4 rounded-xl shadow-lg">
              <div class="text-sm font-medium">Total Potongan Telat</div>
              <div class="text-2xl font-bold">{{ formatCurrency(summary.totalPotonganTelat) }}</div>
            </div>
                          <div class="bg-gradient-to-br from-green-400 to-green-600 text-white p-4 rounded-xl shadow-lg">
                <div class="text-sm font-medium">Total Uang Makan</div>
                <div class="text-2xl font-bold">{{ formatCurrency(summary.totalUangMakan) }}</div>
              </div>
              <div class="bg-gradient-to-br from-purple-400 to-purple-600 text-white p-4 rounded-xl shadow-lg">
                <div class="text-sm font-medium">Total SC By Point</div>
                <div class="text-2xl font-bold">{{ formatCurrency(summary.totalServiceChargeByPoint) }}</div>
              </div>
              <div class="bg-gradient-to-br from-indigo-400 to-indigo-600 text-white p-4 rounded-xl shadow-lg">
                <div class="text-sm font-medium">Total SC Pro Rate</div>
                <div class="text-2xl font-bold">{{ formatCurrency(summary.totalServiceChargeProRate) }}</div>
              </div>
              <div class="bg-gradient-to-br from-green-400 to-green-600 text-white p-4 rounded-xl shadow-lg">
                <div class="text-sm font-medium">Total Service Charge</div>
                <div class="text-2xl font-bold">{{ formatCurrency(summary.totalServiceCharge) }}</div>
              </div>
              <div class="bg-gradient-to-br from-red-400 to-red-600 text-white p-4 rounded-xl shadow-lg">
                <div class="text-sm font-medium">Total BPJS JKN</div>
                <div class="text-2xl font-bold">{{ formatCurrency(summary.totalBPJSJKN) }}</div>
              </div>
              <div class="bg-gradient-to-br from-red-400 to-red-600 text-white p-4 rounded-xl shadow-lg">
                <div class="text-sm font-medium">Total BPJS TK</div>
                <div class="text-2xl font-bold">{{ formatCurrency(summary.totalBPJSTK) }}</div>
              </div>
              <div class="bg-gradient-to-br from-green-400 to-green-600 text-white p-4 rounded-xl shadow-lg">
                <div class="text-sm font-medium">Total Gaji</div>
                <div class="text-2xl font-bold">{{ formatCurrency(summary.totalGaji) }}</div>
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
          <div class="overflow-x-auto table-container">
            <table class="w-full min-w-[1200px] divide-y divide-blue-200 bg-white rounded-2xl shadow-2xl animate-fade-in-up">
              <thead class="bg-gradient-to-r from-blue-600 to-green-400 text-white sticky top-0 z-10" style="position: sticky; top: 0;">
                <tr>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider w-12"></th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">NIK</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jabatan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Divisi</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Point</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Gaji Pokok</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Tunjangan</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Menit Telat</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Jam Lembur</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Gaji Lembur</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Uang Makan</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Hari Kerja</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">SC By Point</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">SC Pro Rate</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Total SC</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">BPJS JKN</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">BPJS TK</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Custom Earnings</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Custom Deductions</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Potongan Telat</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Total Gaji</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <template v-for="(item, index) in props.payrollData" :key="item.user_id">
                  <!-- Main Row -->
                  <tr :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'" class="hover:bg-blue-50 transition-colors">
                    <td class="px-4 py-3 text-center">
                      <button @click="toggleExpand(item.user_id)" 
                              :class="[
                                'w-6 h-6 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-600 flex items-center justify-center transition-all duration-200 expand-button',
                                { 'expanded': expandedRows.has(item.user_id) }
                              ]">
                        <i :class="expandedRows.has(item.user_id) ? 'fa fa-chevron-up' : 'fa fa-chevron-down'"></i>
                      </button>
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.nik }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ item.nama_lengkap }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ item.jabatan }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ item.divisi }}</td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-purple-600">
                      {{ item.point || 0 }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-green-600">
                      {{ formatCurrency(item.gaji_pokok) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-green-600">
                      {{ formatCurrency(item.tunjangan) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-red-600">
                      {{ item.total_telat }}
                      <div class="text-xs text-gray-600 mt-1">
                        {{ formatCurrency(item.gaji_per_menit) }}/menit
                      </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold">
                      <span v-if="item.master_data && item.master_data.ot == 1" class="text-green-600">
                        {{ item.total_lembur }}
                      </span>
                      <span v-else class="text-gray-400">
                        {{ item.total_lembur }}
                        <span class="text-xs text-red-500 ml-1">(OT Disabled)</span>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold">
                      <span v-if="item.master_data && item.master_data.ot == 1" class="text-green-600">
                        {{ formatCurrency(item.gaji_lembur) }}
                      </span>
                      <span v-else class="text-gray-400">
                        {{ formatCurrency(item.gaji_lembur) }}
                        <span class="text-xs text-red-500 ml-1">(OT Disabled)</span>
                      </span>
                      <div class="text-xs text-blue-600 mt-1">
                        {{ formatCurrency(item.nominal_lembur_per_jam) }}/jam
                      </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold">
                      <span v-if="item.master_data && item.master_data.um == 1" class="text-green-600">
                        {{ formatCurrency(item.uang_makan) }}
                      </span>
                      <span v-else class="text-gray-400">
                        {{ formatCurrency(item.uang_makan) }}
                        <span class="text-xs text-red-500 ml-1">(UM Disabled)</span>
                      </span>
                      <div class="text-xs text-blue-600 mt-1">
                        {{ formatCurrency(item.nominal_uang_makan) }}/hari
                      </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-blue-600">
                      {{ item.hari_kerja || 0 }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold">
                      <span v-if="item.master_data && item.master_data.sc == 1" class="text-green-600">
                        {{ formatCurrency(item.service_charge_by_point || 0) }}
                      </span>
                      <span v-else class="text-gray-400">
                        {{ formatCurrency(0) }}
                        <span class="text-xs text-red-500 ml-1">(SC Disabled)</span>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold">
                      <span v-if="item.master_data && item.master_data.sc == 1" class="text-green-600">
                        {{ formatCurrency(item.service_charge_pro_rate || 0) }}
                      </span>
                      <span v-else class="text-gray-400">
                        {{ formatCurrency(0) }}
                        <span class="text-xs text-red-500 ml-1">(SC Disabled)</span>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold">
                      <span v-if="item.master_data && item.master_data.sc == 1" class="text-green-600 font-bold">
                        {{ formatCurrency(item.service_charge || 0) }}
                      </span>
                      <span v-else class="text-gray-400">
                        {{ formatCurrency(0) }}
                        <span class="text-xs text-red-500 ml-1">(SC Disabled)</span>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold">
                      <span v-if="item.master_data && item.master_data.bpjs_jkn == 1" class="text-red-600">
                        {{ formatCurrency(item.bpjs_jkn) }}
                      </span>
                      <span v-else class="text-gray-400">
                        {{ formatCurrency(item.bpjs_jkn) }}
                        <span class="text-xs text-red-500 ml-1">(JKN Disabled)</span>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold">
                      <span v-if="item.master_data && item.master_data.bpjs_tk == 1" class="text-red-600">
                        {{ formatCurrency(item.bpjs_tk) }}
                      </span>
                      <span v-else class="text-gray-400">
                        {{ formatCurrency(item.bpjs_tk) }}
                        <span class="text-xs text-red-500 ml-1">(TK Disabled)</span>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-green-600">
                      {{ formatCurrency(item.custom_earnings) }}
                      <button v-if="item.custom_items && item.custom_items.length > 0" 
                              @click="showCustomItems(item.user_id, 'earn')"
                              class="ml-2 text-xs text-blue-600 hover:text-blue-800">
                        <i class="fa fa-eye"></i>
                      </button>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-red-600">
                      {{ formatCurrency(item.custom_deductions) }}
                      <button v-if="item.custom_items && item.custom_items.length > 0" 
                              @click="showCustomItems(item.user_id, 'deduction')"
                              class="ml-2 text-xs text-blue-600 hover:text-blue-800">
                        <i class="fa fa-eye"></i>
                      </button>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-red-600">
                      {{ formatCurrency(item.potongan_telat) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-bold text-green-600 bg-green-50 rounded-lg">
                      {{ formatCurrency(item.total_gaji) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center">
                      <div class="flex items-center justify-center gap-2">
                        <button @click="openAddCustomItemModal(item.user_id, item.nama_lengkap)" 
                                class="bg-gradient-to-br from-purple-400 to-purple-600 text-white px-3 py-1 rounded-lg text-xs hover:scale-105 transition-all duration-200 font-bold">
                          <i class="fa fa-plus mr-1"></i> Custom
                        </button>
                        <button @click="toggleExpand(item.user_id)" 
                                class="bg-gradient-to-br from-blue-400 to-blue-600 text-white px-3 py-1 rounded-lg text-xs hover:scale-105 transition-all duration-200 font-bold">
                          <i class="fa fa-eye mr-1"></i> Detail
                        </button>
                        <button @click="showPayroll(item)" 
                                class="bg-gradient-to-br from-blue-400 to-blue-600 text-white px-3 py-1 rounded-lg text-xs hover:scale-105 transition-all duration-200 font-bold mr-1">
                          <i class="fa fa-eye mr-1"></i> View
                        </button>
                        <button @click="printPayroll(item)" 
                                class="bg-gradient-to-br from-green-400 to-green-600 text-white px-3 py-1 rounded-lg text-xs hover:scale-105 transition-all duration-200 font-bold">
                          <i class="fa fa-print mr-1"></i> Print
                        </button>
                      </div>
                    </td>

                  </tr>
                  
                  <!-- Expanded Detail Row -->
                                  <tr v-if="expandedRows.has(item.user_id)" class="bg-blue-50 border-l-4 border-blue-400">
                  <td colspan="17" class="px-4 py-4">
                      <div v-if="loadingDetails[item.user_id]" class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
                        <span class="ml-2 text-blue-600">Loading detail attendance...</span>
                      </div>
                      
                      <div v-else-if="attendanceDetails[item.user_id] && attendanceDetails[item.user_id].length > 0" class="space-y-4">
                                               <div class="flex items-center gap-2 mb-4">
                         <i class="fa fa-calendar-check text-blue-600"></i>
                         <h4 class="text-lg font-semibold text-blue-800">Detail Attendance - {{ item.nama_lengkap }}</h4>
                                                    <div class="ml-auto text-sm text-gray-600">
                             <span v-if="item.master_data && item.master_data.ot == 1" 
                                   class="ml-4 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                               <i class="fa fa-check-circle mr-1"></i> OT Enabled
                             </span>
                             <span v-else 
                                   class="ml-4 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                               <i class="fa fa-times-circle mr-1"></i> OT Disabled
                             </span>
                             <span v-if="item.master_data && item.master_data.um == 1" 
                                   class="ml-2 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                               <i class="fa fa-check-circle mr-1"></i> UM Enabled
                             </span>
                             <span v-else 
                                   class="ml-2 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                               <i class="fa fa-times-circle mr-1"></i> UM Disabled
                             </span>
                             <span v-if="item.master_data && item.master_data.bpjs_jkn == 1" 
                                   class="ml-2 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                               <i class="fa fa-check-circle mr-1"></i> JKN Enabled
                             </span>
                             <span v-else 
                                   class="ml-2 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">
                               <i class="fa fa-times-circle mr-1"></i> JKN Disabled
                             </span>
                             <span v-if="item.master_data && item.master_data.bpjs_tk == 1" 
                                   class="ml-2 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                               <i class="fa fa-check-circle mr-1"></i> TK Enabled
                             </span>
                             <span v-else 
                                   class="ml-2 inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">
                               <i class="fa fa-times-circle mr-1"></i> TK Disabled
                             </span>
                           </div>
                       </div>
                        
                        <!-- Attendance Detail Table -->
                        <div class="overflow-x-auto">
                          <table class="w-full text-sm border border-gray-200 rounded-lg">
                            <thead class="bg-blue-100">
                              <tr>
                                <th class="px-3 py-2 text-left border-b">Tanggal</th>
                                <th class="px-3 py-2 text-center border-b">Jam Masuk</th>
                                <th class="px-3 py-2 text-center border-b">Jam Keluar</th>
                                <th class="px-3 py-2 text-center border-b">IN/OUT</th>
                                <th class="px-3 py-2 text-center border-b">Telat (menit)</th>
                                <th class="px-3 py-2 text-center border-b">Lembur (jam)</th>
                                <th class="px-3 py-2 text-center border-b">OT dari EO</th>
                                <th class="px-3 py-2 text-center border-b">Total Lembur</th>
                                <th class="px-3 py-2 text-center border-b">Shift</th>
                                <th class="px-3 py-2 text-center border-b">Status</th>
                              </tr>
                            </thead>
                            <tbody class="bg-white">
                              <tr v-for="detail in attendanceDetails[item.user_id]" :key="detail.tanggal" 
                                  :class="[
                                    'border-b hover:bg-gray-50',
                                    detail.is_off ? 'bg-gray-100' : ''
                                  ]">
                                <td class="px-3 py-2 font-medium">{{ detail.tanggal }}</td>
                                <td class="px-3 py-2 text-center font-mono">
                                  <span v-if="detail.is_off" class="text-gray-500 font-semibold">OFF</span>
                                  <span v-else>{{ detail.jam_masuk || '-' }}</span>
                                </td>
                                <td class="px-3 py-2 text-center font-mono">
                                  <span v-if="detail.is_off" class="text-gray-500 font-semibold">OFF</span>
                                  <span v-else>{{ detail.jam_keluar || '-' }}</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                  <span v-if="detail.is_off" class="text-gray-500 font-semibold">OFF</span>
                                  <div v-else class="flex flex-col text-xs">
                                    <span class="text-green-600 font-semibold">{{ detail.total_masuk }} IN</span>
                                    <span class="text-red-600 font-semibold">{{ detail.total_keluar }} OUT</span>
                                  </div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                  <span v-if="detail.is_off" class="text-gray-500 font-semibold">OFF</span>
                                  <span v-else-if="detail.telat > 0" class="text-red-600 font-semibold">{{ detail.telat }}</span>
                                  <span v-else class="text-green-600">0</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                  <span v-if="detail.is_off" class="text-gray-500 font-semibold">OFF</span>
                                  <span v-else-if="detail.lembur > 0" class="text-green-600 font-semibold">{{ Math.floor(detail.lembur || 0) }}</span>
                                  <span v-else class="text-gray-500">0</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                  <span v-if="detail.extra_off_overtime > 0" class="text-purple-600 font-semibold">{{ Math.floor(detail.extra_off_overtime || 0) }}</span>
                                  <span v-else-if="detail.is_off" class="text-gray-500 font-semibold">OFF</span>
                                  <span v-else class="text-gray-500">0</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                  <span v-if="detail.is_off" class="text-gray-500 font-semibold">OFF</span>
                                  <span v-else-if="(detail.total_lembur || detail.lembur) > 0" class="text-blue-600 font-bold">{{ Math.floor(detail.total_lembur || detail.lembur || 0) }}</span>
                                  <span v-else class="text-gray-500">0</span>
                                </td>
                                <td class="px-3 py-2 text-center text-xs">
                                  <span v-if="detail.is_off" class="text-gray-500 font-semibold">OFF</span>
                                  <span v-else>{{ detail.shift_name || '-' }}</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                  <span v-if="detail.is_off" 
                                        class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">
                                    📅 OFF
                                  </span>
                                  <span v-else-if="detail.is_cross_day" 
                                        class="px-2 py-1 text-xs bg-orange-100 text-orange-700 rounded-full">
                                    🌙 Cross-day
                                  </span>
                                  <span v-else class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">
                                    ✓ Normal
                                  </span>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                      
                      <div v-else class="text-center py-8 text-gray-500">
                        <i class="fa fa-info-circle text-2xl mb-2"></i>
                        <p>Tidak ada data attendance untuk periode ini</p>
                      </div>
                    </td>
                  </tr>
                </template>
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

    <!-- Modal Tambah Custom Item -->
    <div v-if="showAddCustomItemModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl p-6 w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-gray-800">Tambah Custom Item</h3>
          <button @click="showAddCustomItemModal = false" class="text-gray-500 hover:text-gray-700">
            <i class="fa fa-times text-xl"></i>
          </button>
        </div>

        <form @submit.prevent="addCustomItem" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Karyawan</label>
              <div class="w-full p-3 bg-gray-100 rounded-lg border">
                <span class="font-semibold text-gray-800">
                  {{ props.payrollData.find(u => u.user_id == customItemForm.user_id)?.nama_lengkap || 'Karyawan tidak ditemukan' }}
                </span>
                <span class="text-gray-600 ml-2">
                  ({{ props.payrollData.find(u => u.user_id == customItemForm.user_id)?.nik || 'N/A' }})
                </span>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Item</label>
              <select v-model="customItemForm.item_type" required class="w-full form-input rounded-lg">
                <option value="earn">Earnings (Penghasilan)</option>
                <option value="deduction">Deduction (Pengurangan)</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nama Item</label>
              <input v-model="customItemForm.item_name" type="text" required 
                     class="w-full form-input rounded-lg" placeholder="Contoh: Bonus, Potongan, dll">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah</label>
              <input v-model.number="customItemForm.item_amount" type="number" required min="0" step="0.01"
                     class="w-full form-input rounded-lg" placeholder="0">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi (Opsional)</label>
            <textarea v-model="customItemForm.item_description" rows="3" 
                      class="w-full form-input rounded-lg" placeholder="Deskripsi item..."></textarea>
          </div>

          <div class="flex justify-end gap-3 pt-4">
            <button type="button" @click="showAddCustomItemModal = false" 
                    class="px-4 py-2 text-gray-600 bg-gray-200 rounded-lg hover:bg-gray-300">
              Batal
            </button>
            <button type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              Tambah Item
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Detail Custom Items -->
    <div v-if="showCustomItemsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl p-6 w-full max-w-4xl mx-4">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-gray-800">
            Detail {{ selectedItemType === 'earn' ? 'Earnings' : 'Deductions' }}
          </h3>
          <button @click="showCustomItemsModal = false" class="text-gray-500 hover:text-gray-700">
            <i class="fa fa-times text-xl"></i>
          </button>
        </div>

        <div v-if="selectedCustomItems.length > 0" class="overflow-x-auto">
          <table class="min-w-full bg-white border border-gray-200 rounded-lg">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Item</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Jumlah</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Deskripsi</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="item in selectedCustomItems" :key="item.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-900">{{ item.item_name }}</td>
                <td class="px-4 py-3 text-sm text-center font-bold" 
                    :class="selectedItemType === 'earn' ? 'text-green-600' : 'text-red-600'">
                  {{ formatCurrency(item.item_amount) }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ item.item_description || '-' }}</td>
                <td class="px-4 py-3 text-sm text-center">
                  <button @click="deleteCustomItem(item.id)" 
                          class="text-red-600 hover:text-red-800">
                    <i class="fa fa-trash"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-8 text-gray-500">
          <i class="fa fa-inbox text-4xl mb-4"></i>
          <p>Tidak ada {{ selectedItemType === 'earn' ? 'earnings' : 'deductions' }} untuk ditampilkan</p>
        </div>

        <div class="flex justify-end pt-4">
          <button @click="showCustomItemsModal = false" 
                  class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
            Tutup
          </button>
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

/* Freeze header styles */
.table-container {
  max-height: calc(100vh - 400px);
  overflow-y: auto;
  border-radius: 1rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.overflow-x-auto {
  overflow-x: auto;
}

.table-container thead th {
  position: sticky;
  top: 0;
  z-index: 20;
  background: linear-gradient(to right, #2563eb, #10b981) !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  border-bottom: 2px solid rgba(255, 255, 255, 0.2);
}

/* Ensure header stays on top when scrolling */
.table-container thead {
  position: sticky;
  top: 0;
  z-index: 20;
}

/* Add shadow effect for better visual separation */
.table-container thead::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -2px;
  height: 2px;
  background: linear-gradient(to right, rgba(37, 99, 235, 0.3), rgba(16, 185, 129, 0.3));
}

/* Smooth scrolling */
.table-container {
  scroll-behavior: smooth;
}

/* Custom scrollbar */
.table-container::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.table-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Expandable row animations */
.expandable-row-enter-active,
.expandable-row-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.expandable-row-enter-from,
.expandable-row-leave-to {
  opacity: 0;
  max-height: 0;
  padding: 0;
}

.expandable-row-enter-to,
.expandable-row-leave-from {
  opacity: 1;
  max-height: 500px;
}

/* Expand button animations */
.expand-button {
  transition: transform 0.2s ease;
}

.expand-button:hover {
  transform: scale(1.1);
}

.expand-button.expanded {
  transform: rotate(180deg);
}
</style>
