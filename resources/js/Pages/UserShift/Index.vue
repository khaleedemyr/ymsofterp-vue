<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
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

const outletId = ref(props.filter?.outlet_id || '');
const divisionId = ref(props.filter?.division_id || '');
const startDate = ref(props.filter?.start_date || '');

const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function getDayName(dateStr) {
  const d = new Date(dateStr);
  return days[d.getDay()];
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

const holidays = props.holidays || [];
const holidayMap = computed(() => {
  const map = {};
  holidays.forEach(h => { map[h.date] = h.name; });
  return map;
});

console.log('holidays:', props.holidays);
console.log('holidayMap:', holidayMap.value);
console.log('dates:', props.dates);

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
  router.get('/user-shifts', {
    outlet_id: outletId.value,
    division_id: divisionId.value,
    start_date: startDate.value,
  }, { preserveState: true, replace: true });
}

function submit() {
  form.outlet_id = outletId.value;
  form.division_id = divisionId.value;
  form.start_date = startDate.value;
  form.post('/user-shifts', {
    onSuccess: () => Swal.fire('Berhasil', 'Jadwal shift berhasil disimpan!', 'success'),
  });
}
</script>

<template>
  <AppLayout title="Input Shift Mingguan Karyawan">
    <div class="max-w-6xl mx-auto py-8">
      <h1 class="text-2xl font-bold text-blue-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-calendar-days text-blue-500"></i> Input Shift Mingguan Karyawan
      </h1>
      <div class="flex gap-4 mb-6">
        <select v-model="outletId" class="form-input rounded-xl">
          <option value="">Pilih Outlet</option>
          <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
        </select>
        <select v-model="divisionId" class="form-input rounded-xl">
          <option value="">Pilih Divisi</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <input v-model="startDate" type="date" class="form-input rounded-xl" placeholder="Tanggal Mulai (Senin)" />
        <button @click="reloadFilter" class="bg-blue-600 text-white px-4 py-2 rounded-xl shadow hover:bg-blue-700">Tampilkan</button>
      </div>
      <form @submit.prevent="submit">
        <div v-if="users.length && dates.length" class="bg-white rounded-2xl shadow-lg overflow-x-auto">
          <table class="min-w-full divide-y divide-blue-200">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                <th v-for="tgl in dates" :key="tgl" :class="holidayMap[tgl]?.trim() ? 'bg-red-600 text-white' : 'bg-blue-600 text-white'" class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">
                  {{ getDayName(tgl) }}<br/>
                  <span class="text-xs font-normal">{{ tgl }}</span>
                  <span v-if="holidayMap[tgl]" class="block text-xs font-bold mt-1">{{ holidayMap[tgl] }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id" class="hover:bg-blue-50 transition">
                <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ user.nama_lengkap }}</td>
                <td v-for="tgl in dates" :key="tgl" :class="holidayMap[tgl]?.trim() ? 'bg-red-100 text-red-700 border border-red-300' : ''" class="px-4 py-2 text-center" :title="holidayMap[tgl] || ''">
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
    </div>
  </AppLayout>
</template> 