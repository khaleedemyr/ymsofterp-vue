<script setup>
import { ref, watch, onMounted, nextTick, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  outlets: Array,
  divisions: Array,
  users: Array,
  filter: Object,
  payrollMaster: Object,
});

const outletId = ref(props.filter?.outlet_id || '');
const divisionId = ref(props.filter?.division_id || '');
const users = ref(props.users || []);
const loading = ref(false);

// State payroll per karyawan
const payrollData = ref([]);

// State untuk 'isi semua' per kolom
const fillAll = ref({
  gaji: '',
  tunjangan: '',
  ot: false,
  um: false,
  ph: false,
  sc: false,
  bpjs_jkn: false,
  bpjs_tk: false,
  lb: false,
});

const saving = ref(false);
const importing = ref(false);

// File input ref
const importFileInput = ref(null);
const selectedFileName = ref('');
const hasSelectedFile = ref(false);

// Utility functions for number formatting
function formatNumber(value) {
  if (!value) return '';
  // Remove all non-digit characters
  const num = value.toString().replace(/\D/g, '');
  // Add thousand separators
  return num.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function parseNumber(value) {
  if (!value) return '';
  // Remove all non-digit characters and return as number
  return value.toString().replace(/\D/g, '');
}

function handleNumberInput(event, field, userId) {
  const input = event.target;
  const rawValue = input.value;
  const numericValue = parseNumber(rawValue);
  
  // Update the model with numeric value
  if (userId) {
    // For individual user inputs
    const userData = payrollData.value.find(item => item.user_id === userId);
    if (userData) {
      userData[field] = numericValue;
    }
  } else {
    // For fill all inputs
    fillAll.value[field] = numericValue;
  }
  
  // Format the display value
  input.value = formatNumber(numericValue);
}

function handleNumberFocus(event) {
  const input = event.target;
  const rawValue = input.value;
  const numericValue = parseNumber(rawValue);
  input.value = numericValue; // Show raw number when focused
}

function handleNumberBlur(event, field, userId) {
  const input = event.target;
  const rawValue = input.value;
  const numericValue = parseNumber(rawValue);
  
  // Update the model with numeric value
  if (userId) {
    // For individual user inputs
    const userData = payrollData.value.find(item => item.user_id === userId);
    if (userData) {
      userData[field] = numericValue;
    }
  } else {
    // For fill all inputs
    fillAll.value[field] = numericValue;
  }
  
  // Format the display value
  input.value = formatNumber(numericValue);
}

function onFileChange() {
  selectedFileName.value = importFileInput.value?.files[0]?.name || '';
  hasSelectedFile.value = !!importFileInput.value?.files.length;
}

function initPayrollData(val) {
  if (!val || !Array.isArray(val)) {
    payrollData.value = [];
    return;
  }
  
  payrollData.value = val.map(u => {
    const p = props.payrollMaster?.[u.id] || {};
    return {
      user_id: u.id,
      gaji: p.gaji ?? '',
      tunjangan: p.tunjangan ?? '',
      ot: p.ot == 1,
      um: p.um == 1,
      ph: p.ph == 1,
      sc: p.sc == 1,
      bpjs_jkn: p.bpjs_jkn == 1,
      bpjs_tk: p.bpjs_tk == 1,
      lb: p.lb == 1,
    };
  });
}

// Computed property to get payroll data by user ID for safer access
const getPayrollDataByUserId = computed(() => {
  const dataMap = {};
  payrollData.value.forEach(item => {
    dataMap[item.user_id] = item;
  });
  return dataMap;
});

watch(() => props.users, (val) => {
  users.value = val || [];
  initPayrollData(val);
}, { immediate: true });

onMounted(() => {
  initPayrollData(props.users);
});

function lihatData() {
  if (!outletId.value && !divisionId.value) return;
  loading.value = true;
  router.get('/payroll/master', {
    outlet_id: outletId.value,
    division_id: divisionId.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => nextTick(() => { loading.value = false; })
  });
}

function downloadTemplate() {
  if (!outletId.value || !divisionId.value) return;
  const url = `/payroll/master/template?outlet_id=${outletId.value}&division_id=${divisionId.value}`;
  window.open(url, '_blank');
}

// Handler untuk isi semua kolom
function fillAllColumn(key, value) {
  fillAll.value[key] = value;
  payrollData.value.forEach(row => {
    row[key] = value;
  });
}

function resetFillAll() {
  Object.keys(fillAll.value).forEach(k => {
    fillAll.value[k] = (typeof fillAll.value[k] === 'boolean') ? false : '';
  });
}

async function simpanPayroll() {
  if (!users.value.length || !payrollData.value.length) {
    Swal.fire('Peringatan', 'Tidak ada data untuk disimpan', 'warning');
    return;
  }

  const confirm = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin menyimpan data payroll ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
    reverseButtons: true,
  });
  if (!confirm.isConfirmed) return;
  
  saving.value = true;
  Swal.fire({
    title: 'Menyimpan...',
    text: 'Mohon tunggu, data sedang diproses',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => { Swal.showLoading(); }
  });
  
  try {
    const res = await fetch('/payroll/master', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
      body: JSON.stringify({
        outlet_id: outletId.value,
        division_id: divisionId.value,
        payrollData: payrollData.value,
      })
    });
    const data = await res.json();
    if (data.success) {
      Swal.fire('Berhasil', data.message || 'Data payroll berhasil disimpan', 'success');
    } else {
      Swal.fire('Gagal', data.message || 'Gagal menyimpan data payroll', 'error');
    }
  } catch (e) {
    console.error('Error saving payroll:', e);
    Swal.fire('Error', 'Terjadi kesalahan saat menyimpan data payroll', 'error');
  } finally {
    saving.value = false;
  }
}

async function importPayroll() {
  const file = importFileInput.value && importFileInput.value.files && importFileInput.value.files[0];
  if (!file || !outletId.value || !divisionId.value) return;
  importing.value = true;
  const formData = new FormData();
  formData.append('file', file);
  formData.append('outlet_id', outletId.value);
  formData.append('division_id', divisionId.value);
  try {
    const res = await fetch('/payroll/master/import', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
    });
    const data = await res.json();
    if (data.success) {
      Swal.fire('Berhasil', data.message || 'Import payroll berhasil', 'success');
      // Reload data payroll
      lihatData();
    } else {
      Swal.fire('Gagal', data.message || 'Import payroll gagal', 'error');
    }
  } catch (e) {
    console.error('Error importing payroll:', e);
    Swal.fire('Error', 'Terjadi kesalahan saat import payroll', 'error');
  } finally {
    importing.value = false;
    if (importFileInput.value) importFileInput.value.value = '';
    selectedFileName.value = '';
    hasSelectedFile.value = false;
  }
}
</script>

<template>
  <AppLayout title="Master Payroll">
    <div class="w-full min-h-[60vh] h-[calc(100vh-150px)] flex flex-col justify-start items-stretch py-4">
      <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-20 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-blue-500"></div>
      </div>
      <h1 class="text-2xl font-bold text-blue-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-money-check-dollar text-green-500"></i> Master Payroll
      </h1>
      <div class="flex gap-4 mb-6 items-center">
        <select v-model="outletId" class="form-input rounded-xl shadow-lg w-80" autofocus>
          <option value="">Pilih Outlet</option>
          <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
        </select>
        <select v-model="divisionId" class="form-input rounded-xl shadow-lg w-80">
          <option value="">Pilih Divisi</option>
          <option v-for="d in props.divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <button
          @click="lihatData"
          class="bg-gradient-to-br from-green-400 to-blue-500 text-white px-6 py-2 rounded-xl shadow-xl hover:scale-105 hover:shadow-2xl transition-all duration-300 font-bold"
          :disabled="!outletId && !divisionId"
        >
          <i class="fa-solid fa-magnifying-glass"></i> Lihat Data
        </button>
        <button
          @click="downloadTemplate"
          class="bg-gradient-to-br from-blue-500 to-green-400 text-white px-6 py-2 rounded-xl shadow-xl hover:scale-105 hover:shadow-2xl transition-all duration-300 font-bold"
          :disabled="!outletId && !divisionId"
        >
          <i class="fa-solid fa-file-arrow-down"></i> Download Template
        </button>
        <label class="bg-gradient-to-br from-yellow-400 to-orange-400 text-white px-6 py-2 rounded-xl shadow-xl hover:scale-105 hover:shadow-2xl transition-all duration-300 font-bold cursor-pointer flex items-center gap-2 mb-0" :class="{'opacity-60 pointer-events-none': !outletId || !divisionId || importing}">
          <i class="fa-solid fa-file-arrow-up"></i> Pilih File
          <input ref="importFileInput" type="file" accept=".xlsx,.xls" class="hidden" :disabled="!outletId || !divisionId || importing" @change="onFileChange" />
        </label>
        <span v-if="selectedFileName" class="ml-2 text-sm text-gray-700 font-semibold truncate max-w-[180px]">{{ selectedFileName }}</span>
        <button @click="importPayroll" :disabled="importing || !outletId || !divisionId || !hasSelectedFile" class="bg-orange-500 text-white px-6 py-2 rounded-xl shadow-xl hover:bg-orange-600 transition-all duration-300 font-bold flex items-center gap-2">
          <span v-if="importing"><i class="fa fa-spinner fa-spin"></i></span>
          <span v-else><i class="fa-solid fa-cloud-arrow-up"></i></span>
          Import
        </button>
      </div>
      <div class="flex-1 w-full overflow-auto">
        <div v-if="users.length" class="w-full h-full">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px] divide-y divide-blue-200 bg-white rounded-2xl shadow-2xl animate-fade-in-up">
              <thead class="bg-gradient-to-r from-blue-600 to-green-400 text-white sticky top-0 z-10">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">NIK</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jabatan</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Point</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">GAJI<br/>(EARN)</th>
                  <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">TUNJANGAN<br/>(EARN)</th>
                  <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider">OT<br/>(EARN)</th>
                  <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider">UM<br/>(EARN)</th>
                  <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider">PH<br/>(EARN)</th>
                  <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider">SC<br/>(EARN)</th>
                  <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider">BPJS JKN<br/>(DEDUCTION)</th>
                  <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider">BPJS TK<br/>(DEDUCTION)</th>
                  <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider">L & B<br/>(DEDUCTION)</th>
                </tr>
                <!-- Baris isi semua -->
                <tr class="bg-gradient-to-r from-blue-100 to-green-100 text-blue-900 text-xs">
                  <td></td>
                  <td class="text-right pr-2 font-bold">Isi Semua:</td>
                  <td></td>
                  <td></td>
                  <td class="px-2 py-1 text-center">
                    <input 
                      type="text" 
                      :value="formatNumber(fillAll.gaji)" 
                      class="form-input text-blue-900 font-bold text-center w-24" 
                      @input="handleNumberInput($event, 'gaji')"
                      @focus="handleNumberFocus($event)"
                      @blur="handleNumberBlur($event, 'gaji')"
                      @change="fillAllColumn('gaji', fillAll.gaji)" 
                      placeholder="0"
                    />
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input 
                      type="text" 
                      :value="formatNumber(fillAll.tunjangan)" 
                      class="form-input text-blue-900 font-bold text-center w-24" 
                      @input="handleNumberInput($event, 'tunjangan')"
                      @focus="handleNumberFocus($event)"
                      @blur="handleNumberBlur($event, 'tunjangan')"
                      @change="fillAllColumn('tunjangan', fillAll.tunjangan)" 
                      placeholder="0"
                    />
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input type="checkbox" v-model="fillAll.ot" @change="fillAllColumn('ot', fillAll.ot)" />
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input type="checkbox" v-model="fillAll.um" @change="fillAllColumn('um', fillAll.um)" />
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input type="checkbox" v-model="fillAll.ph" @change="fillAllColumn('ph', fillAll.ph)" />
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input type="checkbox" v-model="fillAll.sc" @change="fillAllColumn('sc', fillAll.sc)" />
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input type="checkbox" v-model="fillAll.bpjs_jkn" @change="fillAllColumn('bpjs_jkn', fillAll.bpjs_jkn)" />
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input type="checkbox" v-model="fillAll.bpjs_tk" @change="fillAllColumn('bpjs_tk', fillAll.bpjs_tk)" />
                  </td>
                  <td class="px-2 py-1 text-center flex items-center justify-center gap-2">
                    <input type="checkbox" v-model="fillAll.lb" @change="fillAllColumn('lb', fillAll.lb)" />
                    <button @click="resetFillAll" class="ml-2 text-red-500 hover:text-red-700" title="Reset Isi Semua"><i class="fa fa-eraser"></i></button>
                  </td>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in users" :key="user.id" class="hover:scale-[1.01] hover:shadow-xl transition-all duration-200 bg-white/80 hover:bg-blue-50 cursor-pointer border-b border-blue-100">
                  <td class="px-4 py-2">{{ user.nik }}</td>
                  <td class="px-4 py-2 font-semibold">{{ user.nama_lengkap }}</td>
                  <td class="px-4 py-2">{{ user.jabatan }}</td>
                  <td class="px-4 py-2 text-center font-bold text-purple-600">{{ user.point || 0 }}</td>
                  <template v-if="getPayrollDataByUserId[user.id]">
                    <td class="px-2 py-2 text-center">
                      <input 
                        type="text" 
                        :value="formatNumber(getPayrollDataByUserId[user.id].gaji)" 
                        class="form-input text-blue-900 font-bold text-center w-32" 
                        @input="handleNumberInput($event, 'gaji', user.id)"
                        @focus="handleNumberFocus($event)"
                        @blur="handleNumberBlur($event, 'gaji', user.id)"
                        placeholder="0"
                      />
                    </td>
                    <td class="px-2 py-2 text-center">
                      <input 
                        type="text" 
                        :value="formatNumber(getPayrollDataByUserId[user.id].tunjangan)" 
                        class="form-input text-blue-900 font-bold text-center w-32" 
                        @input="handleNumberInput($event, 'tunjangan', user.id)"
                        @focus="handleNumberFocus($event)"
                        @blur="handleNumberBlur($event, 'tunjangan', user.id)"
                        placeholder="0"
                      />
                    </td>
                    <td class="px-2 py-2 text-center">
                      <input type="checkbox" v-model="getPayrollDataByUserId[user.id].ot" />
                    </td>
                    <td class="px-2 py-2 text-center">
                      <input type="checkbox" v-model="getPayrollDataByUserId[user.id].um" />
                    </td>
                    <td class="px-2 py-2 text-center">
                      <input type="checkbox" v-model="getPayrollDataByUserId[user.id].ph" />
                    </td>
                    <td class="px-2 py-2 text-center">
                      <input type="checkbox" v-model="getPayrollDataByUserId[user.id].sc" />
                    </td>
                    <td class="px-2 py-2 text-center">
                      <input type="checkbox" v-model="getPayrollDataByUserId[user.id].bpjs_jkn" />
                    </td>
                    <td class="px-2 py-2 text-center">
                      <input type="checkbox" v-model="getPayrollDataByUserId[user.id].bpjs_tk" />
                    </td>
                    <td class="px-2 py-2 text-center">
                      <input type="checkbox" v-model="getPayrollDataByUserId[user.id].lb" />
                    </td>
                  </template>
                  <template v-else>
                    <td colspan="9" class="text-center text-gray-400">Data tidak tersedia</td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="text-center text-gray-400 py-12">
          Silakan pilih outlet atau divisi, lalu klik <b>Lihat Data</b>.
        </div>
      </div>
      
      <!-- Tombol Simpan di luar list -->
      <div class="flex justify-end mt-6" v-if="users.length && payrollData.length">
        <button 
          @click="simpanPayroll" 
          :disabled="saving" 
          class="bg-blue-600 text-white px-8 py-2 rounded-xl shadow hover:bg-blue-700 font-bold text-lg flex items-center gap-2 transition-all duration-300"
          :class="{ 'opacity-50 cursor-not-allowed': saving }"
        >
          <span v-if="saving"><i class="fa fa-spinner fa-spin"></i></span>
          <span v-else><i class="fa-solid fa-floppy-disk"></i></span>
          Simpan
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
@keyframes fade-in-up {
  0% { opacity: 0; transform: translateY(40px) scale(0.98); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-fade-in-up {
  animation: fade-in-up 0.6s cubic-bezier(0.23, 1, 0.32, 1);
}

/* Fix table layout issues */
.overflow-x-auto {
  overflow-x: auto;
  overflow-y: visible;
}

/* Ensure form inputs don't break layout */
.form-input {
  box-sizing: border-box;
  max-width: 100%;
}

/* Fix sticky header issues */
thead {
  position: sticky;
  top: 0;
  z-index: 10;
  background: linear-gradient(to right, #2563eb, #22c55e);
}

/* Ensure save button is always visible when there's data */
.flex.justify-end {
  position: relative;
  z-index: 5;
}

/* Number input formatting */
.form-input[type="text"] {
  font-family: 'Courier New', monospace;
  font-weight: bold;
  text-align: center;
}

/* Ensure proper spacing for formatted numbers */
.form-input[type="text"]:focus {
  outline: 2px solid #3b82f6;
  outline-offset: -2px;
}
</style> 