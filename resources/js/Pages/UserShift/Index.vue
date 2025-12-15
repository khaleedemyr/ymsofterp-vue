<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  outlets: Array,
  divisions: Array,
  users: Array,
  shifts: Array,
  dates: Array,
  userShifts: Array,
  filter: Object,
  holidays: Array,
  approvedAbsents: Array,
});

const page = usePage();
const userOutletId = page.props.auth?.user?.id_outlet || '';

const outletId = ref(props.filter?.outlet_id || '');
const divisionId = ref(props.filter?.division_id || '');
const startDate = ref(props.filter?.start_date || '');

const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function getDayName(dateStr) {
  const d = new Date(dateStr);
  return days[d.getDay()];
}

function formatDateLocal(dateStr) {
  const d = new Date(dateStr);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

// Map userShifts: user_id + tanggal => shift_id
const userShiftMap = computed(() => {
  const map = {};
  (props.userShifts || []).forEach(s => {
    if (!map[s.user_id]) map[s.user_id] = {};
    map[s.user_id][s.tanggal] = s.shift_id;
  });
  return map;
});

const loading = ref(false);

// Bulk input functionality
const showBulkInput = ref(false);
const bulkInput = ref({
  shift_id: null,
  selectedUsers: [],
  selectedDates: [],
  applyToAllUsers: false,
  applyToAllDates: false
});

const holidayMap = computed(() => {
  const map = {};
  (props.holidays || []).forEach(h => {
    const key = String(h.date).trim();
    map[key] = h.name;
  });
  return map;
});

const tglKey = (tgl) => String(formatDateLocal(tgl)).trim();

// Computed property untuk mendapatkan approved absent berdasarkan tanggal dan user
const getApprovedAbsentForDate = computed(() => {
  return (dateString, userId) => {
    if (!props.approvedAbsents) return null
    
    const date = new Date(dateString).toISOString().split('T')[0]
    
    return props.approvedAbsents.find(absent => {
      const fromDate = new Date(absent.date_from).toISOString().split('T')[0]
      const toDate = new Date(absent.date_to).toISOString().split('T')[0]
      
      return absent.user_id === userId && date >= fromDate && date <= toDate
    })
  }
});

// Computed property untuk mengecek apakah tanggal sudah lewat
const isPastDate = computed(() => {
  return (dateString) => {
    const today = new Date()
    const date = new Date(dateString)
    
    // Set time to start of day for accurate comparison
    today.setHours(0, 0, 0, 0)
    date.setHours(0, 0, 0, 0)
    
    return date < today
  }
});

console.log('dates:', props.dates);
console.log('holidays:', props.holidays);
console.log('holidayMap:', holidayMap.value);
console.log('approvedAbsents:', props.approvedAbsents);

const form = useForm({
  outlet_id: outletId.value,
  division_id: divisionId.value,
  start_date: startDate.value,
  shifts: {}, // {user_id: {tanggal: shift_id/null}}
  explicit_off: {}, // AGAR SELALU IKUT POST!!
});

// Inisialisasi form.shifts dari userShiftMap
watch(() => [props.users, props.dates], () => {
  form.shifts = {};
  props.users.forEach(u => {
    form.shifts[u.id] = {};
    props.dates.forEach((tgl, idx) => {
      form.shifts[u.id][tgl] = userShiftMap.value[u.id]?.[tgl] ?? null;
    });
  });
}, { immediate: true });

function reloadFilter() {
  if (!outletId.value || !divisionId.value || !startDate.value) {
    Swal.fire('Lengkapi Filter', 'Silakan pilih outlet, divisi, dan tanggal mulai!', 'warning');
    return;
  }
  loading.value = true;
  router.get('/user-shifts', {
    outlet_id: outletId.value,
    division_id: divisionId.value,
    start_date: startDate.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => {
      nextTick(() => { loading.value = false; });
    }
  });
}

// --- BEGIN: Explicit OFF flag builder - patch perbandingan nilai awal
function buildExplicitOffFlags() {
  const explicitOff = {};
  props.users.forEach(user => {
    explicitOff[user.id] = {};
    props.dates.forEach(date => {
      // Cek: hanya flag cell jika AWAL-nya ADA isi, dan AKHIR-nya (hasil edit) null
      const awal = userShiftMap.value[user.id]?.[date] ?? null;
      const akhir = form.shifts[user.id][date];
      if (awal !== null && akhir === null) {
        explicitOff[user.id][date] = true;
      }
    });
    if (Object.keys(explicitOff[user.id]).length === 0) {
      delete explicitOff[user.id];
    }
  });
  return explicitOff;
}
// --- END explicit OFF flag builder

function submit() {
  form.outlet_id = outletId.value;
  form.division_id = divisionId.value;
  form.start_date = startDate.value;

  // PATCH: ubah ke plain JS object agar inertia/axios tidak kirim Proxy
  const explicitOffObj = buildExplicitOffFlags();
  form.explicit_off = JSON.parse(JSON.stringify(explicitOffObj));
  console.log('DEBUG explicit_off', form.explicit_off);

  form.post('/user-shifts', {
    onSuccess: () => Swal.fire('Berhasil', 'Jadwal shift berhasil disimpan!', 'success'),
  });
}

const isLibur = (tgl) => {
  const key = tglKey(tgl);
  return Object.keys(holidayMap.value).some(k => k === key);
};

const getKeteranganLibur = (tgl) => {
  const key = tglKey(tgl);
  const found = Object.entries(holidayMap.value).find(([k]) => k === key);
  return found ? found[1] : '';
};

const tooltipText = ref('');
const tooltipVisible = ref(false);
const tooltipX = ref(0);
const tooltipY = ref(0);

function showTooltip(text, event) {
  tooltipText.value = text;
  tooltipVisible.value = true;
  tooltipX.value = event.clientX + 10;
  tooltipY.value = event.clientY + 10;
}
function hideTooltip() {
  tooltipVisible.value = false;
}

// Bulk input functions
function toggleBulkInput() {
  showBulkInput.value = !showBulkInput.value;
  if (!showBulkInput.value) {
    resetBulkInput();
  }
}

function resetBulkInput() {
  bulkInput.value = {
    shift_id: null,
    selectedUsers: [],
    selectedDates: [],
    applyToAllUsers: false,
    applyToAllDates: false
  };
}

function applyBulkInput() {
  if (!bulkInput.value.shift_id) {
    Swal.fire('Pilih Shift', 'Silakan pilih shift yang akan diterapkan!', 'warning');
    return;
  }

  if (!bulkInput.value.applyToAllUsers && bulkInput.value.selectedUsers.length === 0) {
    Swal.fire('Pilih Karyawan', 'Silakan pilih karyawan atau centang "Terapkan ke Semua Karyawan"!', 'warning');
    return;
  }

  if (!bulkInput.value.applyToAllDates && bulkInput.value.selectedDates.length === 0) {
    Swal.fire('Pilih Tanggal', 'Silakan pilih tanggal atau centang "Terapkan ke Semua Tanggal"!', 'warning');
    return;
  }

  // Apply bulk input
  const usersToApply = bulkInput.value.applyToAllUsers ? props.users : props.users.filter(u => bulkInput.value.selectedUsers.includes(u.id));
  const datesToApply = bulkInput.value.applyToAllDates ? props.dates : bulkInput.value.selectedDates;

  let appliedCount = 0;
  let skippedCount = 0;

  usersToApply.forEach(user => {
    datesToApply.forEach(date => {
      // Skip if date is past or user has approved absent
      if (isPastDate.value(date) || getApprovedAbsentForDate.value(date, user.id)) {
        skippedCount++;
        return;
      }

      // Apply shift (convert 'OFF' string to null)
      form.shifts[user.id][date] = bulkInput.value.shift_id === 'OFF' ? null : bulkInput.value.shift_id;
      appliedCount++;
    });
  });

  // Show result
  const shiftName = bulkInput.value.shift_id === 'OFF' ? 'OFF (Tidak Masuk Kerja)' : 
    props.shifts.find(s => s.id == bulkInput.value.shift_id)?.shift_name || 'Shift';
  let message = `Berhasil menerapkan ${shiftName} ke ${appliedCount} slot jadwal.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} slot dilewati (tanggal lewat atau ada absen).`;
  }

  Swal.fire('Bulk Input Berhasil', message, 'success');
  resetBulkInput();
  showBulkInput.value = false;
}

// Computed properties for bulk input
const availableDates = computed(() => {
  return props.dates.filter(date => !isPastDate.value(date));
});

const availableUsers = computed(() => {
  return props.users;
});

// Jika user bukan HO, set outletId ke outlet sendiri dan disable select
if (userOutletId && userOutletId != 1) {
  outletId.value = userOutletId;
}
</script>

<template>
  <AppLayout title="Input Shift Mingguan Karyawan">
    <div class="max-w-6xl mx-auto py-8">
      <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-20 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-blue-500"></div>
      </div>
      <h1 class="text-2xl font-bold text-blue-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-calendar-days text-blue-500"></i> Input Shift Mingguan Karyawan
      </h1>
      <div class="flex gap-4 mb-6">
        <select v-model="outletId" class="form-input rounded-xl" :disabled="userOutletId != 1" autofocus>
          <option v-if="userOutletId == 1" value="">Pilih Outlet</option>
          <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
        </select>
        <select v-model="divisionId" class="form-input rounded-xl">
          <option value="">Pilih Divisi</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <input v-model="startDate" type="date" class="form-input rounded-xl" placeholder="Tanggal Mulai (Senin)" />
        <button
          @click="reloadFilter"
          class="bg-blue-600 text-white px-4 py-2 rounded-xl shadow hover:bg-blue-700"
          :disabled="!outletId || !divisionId || !startDate"
        >
          Tampilkan
        </button>
      </div>
      
      <!-- Bulk Input Section -->
      <div v-if="users.length && dates.length" class="mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
              <i class="fa-solid fa-layer-group mr-2 text-blue-600"></i>
              Bulk Input Shift
            </h3>
            <button 
              type="button"
              @click="toggleBulkInput"
              :class="[
                'px-4 py-2 rounded-lg font-medium transition-colors',
                showBulkInput ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
              ]"
            >
              <i :class="showBulkInput ? 'fa-solid fa-times' : 'fa-solid fa-plus'" class="mr-2"></i>
              {{ showBulkInput ? 'Tutup' : 'Bulk Input' }}
            </button>
          </div>
          
          <div v-if="showBulkInput" class="space-y-4">
            <!-- Shift Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Pilih Shift <span class="text-red-500">*</span>
              </label>
              <select 
                v-model="bulkInput.shift_id"
                class="w-full form-input rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option :value="null">-- Pilih Shift --</option>
                <option :value="'OFF'">OFF (Tidak Masuk Kerja)</option>
                <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                  {{ shift.shift_name }} ({{ shift.time_start }} - {{ shift.time_end }})
                </option>
              </select>
            </div>
            
            <!-- User Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Pilih Karyawan
              </label>
              <div class="space-y-2">
                <label class="flex items-center">
                  <input 
                    type="checkbox" 
                    v-model="bulkInput.applyToAllUsers"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  >
                  <span class="ml-2 text-sm text-gray-700">Terapkan ke Semua Karyawan</span>
                </label>
                <div v-if="!bulkInput.applyToAllUsers" class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg p-2">
                  <label 
                    v-for="user in availableUsers" 
                    :key="user.id"
                    class="flex items-center py-1"
                  >
                    <input 
                      type="checkbox" 
                      :value="user.id"
                      v-model="bulkInput.selectedUsers"
                      class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="ml-2 text-sm text-gray-700">{{ user.nama_lengkap }}</span>
                  </label>
                </div>
              </div>
            </div>
            
            <!-- Date Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Pilih Tanggal
              </label>
              <div class="space-y-2">
                <label class="flex items-center">
                  <input 
                    type="checkbox" 
                    v-model="bulkInput.applyToAllDates"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  >
                  <span class="ml-2 text-sm text-gray-700">Terapkan ke Semua Tanggal (kecuali yang sudah lewat)</span>
                </label>
                <div v-if="!bulkInput.applyToAllDates" class="flex flex-wrap gap-2">
                  <label 
                    v-for="date in availableDates" 
                    :key="date"
                    class="flex items-center"
                  >
                    <input 
                      type="checkbox" 
                      :value="date"
                      v-model="bulkInput.selectedDates"
                      class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="ml-2 text-sm text-gray-700">{{ getDayName(date) }} ({{ date }})</span>
                  </label>
                </div>
              </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 pt-4 border-t">
              <button 
                type="button"
                @click="resetBulkInput"
                class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
              >
                <i class="fa-solid fa-undo mr-2"></i>
                Reset
              </button>
              <button 
                type="button"
                @click="applyBulkInput"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                <i class="fa-solid fa-check mr-2"></i>
                Terapkan
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <form @submit.prevent="submit">
        <div v-if="users.length && dates.length" class="bg-white rounded-2xl shadow-lg overflow-x-auto">
          <table class="min-w-full divide-y divide-blue-200">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                <th v-for="tgl in dates" :key="tgl"
                  :class="[
                    'px-4 py-3 text-center text-xs font-bold uppercase tracking-wider',
                    isLibur(tgl) ? 'holiday-header' : 'bg-blue-600 text-white'
                  ]"
                  @mouseenter="holidayMap.value && holidayMap.value[tglKey(tgl)] ? showTooltip(getKeteranganLibur(tgl), $event) : null"
                  @mousemove="holidayMap.value && holidayMap.value[tglKey(tgl)] ? showTooltip(getKeteranganLibur(tgl), $event) : null"
                  @mouseleave="hideTooltip"
                >
                  <div v-if="isLibur(tgl)" class="block text-xs font-bold mb-1" style="white-space:normal;line-height:1.2;">
                    {{ getKeteranganLibur(tgl) }}
                  </div>
                  {{ getDayName(tgl) }}<br/>
                  <span class="text-xs font-normal">{{ tgl }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id" class="hover:bg-blue-50 transition">
                <td class="px-4 py-2 whitespace-nowrap">
                  <div class="font-semibold text-gray-900">{{ user.nama_lengkap }}</div>
                  <div class="text-gray-500 text-xs mt-1">{{ user.jabatan || '-' }}</div>
                </td>
                <td v-for="tgl in dates" :key="tgl"
                  :class="[
                    'px-4 py-2 text-center',
                    isLibur(tgl) ? 'holiday-cell' : '',
                    getApprovedAbsentForDate(tgl, user.id) ? 'bg-green-50' : '',
                    isPastDate(tgl) ? 'bg-gray-100' : ''
                  ]"
                  :title="getApprovedAbsentForDate(tgl, user.id) ? 
                    `Sudah ada absen: ${getApprovedAbsentForDate(tgl, user.id).leave_type_name} - ${getApprovedAbsentForDate(tgl, user.id).reason}` : 
                    isPastDate(tgl) ? 'Tanggal sudah lewat - tidak dapat diedit' :
                    (holidayMap.value && holidayMap.value[tglKey(tgl)] ? holidayMap.value[tglKey(tgl)] : '')"
                >
                  <!-- Past Date Indicator -->
                  <div v-if="isPastDate(tgl)" class="mb-1">
                    <div class="w-full text-xs bg-gray-500 text-white px-1 py-0.5 rounded">
                      <i class="fa-solid fa-clock sm:mr-1"></i>
                      <span class="hidden sm:inline">Tanggal Lewat</span>
                      <span class="sm:hidden">⏰</span>
                    </div>
                    <div class="text-xs text-gray-600 mt-0.5">
                      Tidak dapat diedit
                    </div>
                  </div>
                  
                  <!-- Approved Absent Indicator -->
                  <div v-else-if="getApprovedAbsentForDate(tgl, user.id)" class="mb-1">
                    <div class="w-full text-xs bg-green-500 text-white px-1 py-0.5 rounded">
                      <i class="fa-solid fa-check-circle sm:mr-1"></i>
                      <span class="hidden sm:inline">{{ getApprovedAbsentForDate(tgl, user.id).leave_type_name }}</span>
                      <span class="sm:hidden">✓</span>
                    </div>
                    <div class="text-xs text-green-600 mt-0.5">
                      {{ getApprovedAbsentForDate(tgl, user.id).reason }}
                    </div>
                  </div>
                  
                  <!-- Shift Select - Disabled if has approved absent or is past date -->
                  <select 
                    v-model="form.shifts[user.id][tgl]" 
                    :disabled="getApprovedAbsentForDate(tgl, user.id) || isPastDate(tgl)"
                    :class="[
                      'form-input rounded',
                      (getApprovedAbsentForDate(tgl, user.id) || isPastDate(tgl)) ? 'bg-gray-100 cursor-not-allowed opacity-50' : ''
                    ]"
                  >
                    <option :value="null">OFF</option>
                    <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.shift_name }}</option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center text-gray-400 py-12">
          Silakan pilih outlet, divisi, dan tanggal mulai minggu (Senin), lalu klik <b>Tampilkan</b>.
        </div>
        <div class="flex justify-end mt-6">
          <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-xl shadow hover:bg-blue-700" :disabled="!users.length || !dates.length">Simpan Jadwal</button>
        </div>
      </form>
      <div v-if="tooltipVisible"
           :style="{
             position: 'fixed',
             left: tooltipX + 'px',
             top: tooltipY + 'px',
             background: 'linear-gradient(135deg, #fff 60%, #f87171 100%)',
             color: '#b91c1c',
             padding: '16px 24px',
             borderRadius: '12px',
             fontSize: '18px',
             fontWeight: 'bold',
             boxShadow: '0 8px 32px 0 rgba(31, 38, 135, 0.37), 0 2px 8px 0 #f87171',
             border: '2px solid #f87171',
             zIndex: 9999,
             textShadow: '1px 1px 2px #fff'
           }"
      >
        {{ tooltipText }}
      </div>
    </div>
  </AppLayout>
</template>

<!-- tailwind safelist: -->
<span class="bg-red-600 bg-blue-600 bg-red-100 text-red-700 border border-red-300 text-white"></span> 