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

const holidayMap = computed(() => {
  const map = {};
  (props.holidays || []).forEach(h => {
    const key = String(h.date).trim();
    map[key] = h.name;
  });
  return map;
});

const tglKey = (tgl) => String(formatDateLocal(tgl)).trim();

console.log('dates:', props.dates);
console.log('holidays:', props.holidays);
console.log('holidayMap:', holidayMap.value);

const form = useForm({
  outlet_id: outletId.value,
  division_id: divisionId.value,
  start_date: startDate.value,
  shifts: {}, // {user_id: {tanggal: shift_id/null}}
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

function submit() {
  form.outlet_id = outletId.value;
  form.division_id = divisionId.value;
  form.start_date = startDate.value;
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
                <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ user.nama_lengkap }}</td>
                <td v-for="tgl in dates" :key="tgl"
                  :class="[
                    'px-4 py-2 text-center',
                    isLibur(tgl) ? 'holiday-cell' : ''
                  ]"
                  :title="holidayMap.value && holidayMap.value[tglKey(tgl)] ? holidayMap.value[tglKey(tgl)] : ''"
                >
                  <select v-model="form.shifts[user.id][tgl]" class="form-input rounded">
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