<script setup>
import { ref, onMounted } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ schedule: Object, qrUrl: String });

const userSearch = ref('');
const userResults = ref([]);
const selectedUsers = ref([]);
const jabatanList = ref([]);
const outletList = ref([]);
const selectedJabatan = ref([]);
const selectedOutlets = ref([]);

const inviteForm = useForm({
  user_ids: [],
  jabatan_ids: [],
  outlet_ids: [],
});

const trainerForm = useForm({ user_id: '', role: 'assistant', hours: '' });
const attendanceForm = useForm({ user_id: '', notes: '' });

onMounted(async () => {
  const [jRes, oRes] = await Promise.all([
    axios.get(route('just-academy.api.jabatan')),
    axios.get(route('just-academy.api.outlets')),
  ]);
  jabatanList.value = jRes.data.jabatan || [];
  outletList.value = oRes.data.outlets || [];
});

async function searchUsers() {
  if (userSearch.value.length < 2) return;
  const res = await axios.get(route('just-academy.api.users.search'), { params: { q: userSearch.value } });
  userResults.value = res.data.users || [];
}

function addUser(u) {
  if (!selectedUsers.value.find(x => x.id === u.id)) selectedUsers.value.push(u);
  userSearch.value = '';
  userResults.value = [];
}

function submitInvite() {
  inviteForm.user_ids = selectedUsers.value.map(u => u.id);
  inviteForm.jabatan_ids = selectedJabatan.value;
  inviteForm.outlet_ids = selectedOutlets.value;
  inviteForm.post(route('just-academy.schedules.invite', props.schedule.id));
}

function submitTrainer() {
  trainerForm.post(route('just-academy.schedules.trainers.store', props.schedule.id), {
    onSuccess: () => trainerForm.reset(),
  });
}

function submitAttendance() {
  attendanceForm.post(route('just-academy.schedules.attendance.manual', props.schedule.id), {
    onSuccess: () => attendanceForm.reset(),
  });
}

function removeParticipant(id) {
  if (!confirm('Hapus peserta?')) return;
  useForm({}).delete(route('just-academy.schedules.participants.destroy', [props.schedule.id, id]));
}
</script>

<template>
  <AppLayout title="Detail Jadwal">
    <div class="max-w-5xl mx-auto py-8 px-2 space-y-6">
      <div class="flex justify-between items-start">
        <div>
          <h1 class="text-2xl font-bold">{{ schedule.title }}</h1>
          <p class="text-gray-600">{{ schedule.program?.title }} · {{ schedule.start_at }} — {{ schedule.end_at }}</p>
          <p class="text-sm text-gray-500">{{ schedule.location }} <span v-if="schedule.outlet">· {{ schedule.outlet.nama_outlet }}</span></p>
        </div>
        <Link :href="route('just-academy.schedules.edit', schedule.id)" class="text-indigo-600">Edit</Link>
      </div>

      <div v-if="qrUrl" class="bg-white rounded-2xl shadow p-4">
        <p class="font-medium mb-2">QR Check-in</p>
        <p class="text-xs break-all text-gray-600">{{ qrUrl }}</p>
      </div>

      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-semibold mb-4">Undang Peserta</h2>
        <div class="space-y-4">
          <div>
            <input v-model="userSearch" type="text" placeholder="Cari nama user..." class="border rounded-xl px-3 py-2 w-full max-w-md" @input="searchUsers" />
            <ul v-if="userResults.length" class="border rounded-xl mt-1 max-w-md bg-white shadow">
              <li v-for="u in userResults" :key="u.id" class="px-3 py-2 hover:bg-gray-50 cursor-pointer" @click="addUser(u)">{{ u.name }} — {{ u.email }}</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-2">
              <span v-for="u in selectedUsers" :key="u.id" class="bg-indigo-100 text-indigo-800 px-2 py-1 rounded-lg text-sm">{{ u.name }}</span>
            </div>
          </div>
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-medium mb-2">By Jabatan</p>
              <label v-for="j in jabatanList" :key="j.id" class="flex items-center gap-2 text-sm mb-1">
                <input v-model="selectedJabatan" type="checkbox" :value="j.id" /> {{ j.name }}
              </label>
            </div>
            <div>
              <p class="text-sm font-medium mb-2">By Outlet</p>
              <label v-for="o in outletList" :key="o.id" class="flex items-center gap-2 text-sm mb-1">
                <input v-model="selectedOutlets" type="checkbox" :value="o.id" /> {{ o.name }}
              </label>
            </div>
          </div>
          <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded-xl" @click="submitInvite">Kirim Undangan</button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-semibold mb-4">Peserta ({{ schedule.participants?.length || 0 }})</h2>
        <ul class="space-y-2 mb-4">
          <li v-for="p in schedule.participants" :key="p.id" class="flex justify-between border rounded-lg px-3 py-2">
            <span>{{ p.user?.name }} <span class="text-xs text-gray-500">({{ p.invite_source }})</span></span>
            <button type="button" class="text-red-600 text-sm" @click="removeParticipant(p.id)">Hapus</button>
          </li>
        </ul>
        <form class="flex gap-2 items-end" @submit.prevent="submitAttendance">
          <div>
            <label class="text-sm">Mark hadir (user ID)</label>
            <input v-model="attendanceForm.user_id" type="number" class="border rounded-xl px-3 py-2" placeholder="User ID" />
          </div>
          <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded-xl">Mark Manual</button>
        </form>
      </div>

      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-semibold mb-4">Trainer</h2>
        <ul class="mb-4 space-y-1">
          <li v-for="t in schedule.trainers" :key="t.id">{{ t.user?.name }} ({{ t.role }})</li>
        </ul>
        <form class="flex gap-2" @submit.prevent="submitTrainer">
          <input v-model="trainerForm.user_id" type="number" placeholder="User ID trainer" class="border rounded-xl px-3 py-2" required />
          <select v-model="trainerForm.role" class="border rounded-xl px-3 py-2">
            <option value="primary">Primary</option>
            <option value="assistant">Assistant</option>
          </select>
          <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl">Tambah</button>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
