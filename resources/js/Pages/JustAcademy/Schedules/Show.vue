<script setup>
import { ref, onMounted } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaConfirmDelete, jaDelete, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({ schedule: Object, qrUrl: String });

const userSearch = ref('');
const userResults = ref([]);
const selectedUsers = ref([]);
const jabatanList = ref([]);
const outletList = ref([]);
const selectedJabatan = ref([]);
const selectedOutlets = ref([]);

const inviteForm = useForm({ user_ids: [], jabatan_ids: [], outlet_ids: [] });
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
  inviteForm.post(route('just-academy.schedules.invite', props.schedule.id), {
    onError: (e) => jaFormErrors(e),
  });
}

function submitTrainer() {
  trainerForm.post(route('just-academy.schedules.trainers.store', props.schedule.id), {
    onSuccess: () => trainerForm.reset(),
    onError: (e) => jaFormErrors(e),
  });
}

function submitAttendance() {
  attendanceForm.post(route('just-academy.schedules.attendance.manual', props.schedule.id), {
    onSuccess: () => attendanceForm.reset(),
    onError: (e) => jaFormErrors(e),
  });
}

async function removeParticipant(id) {
  const result = await jaConfirmDelete({
    title: 'Hapus peserta?',
    text: 'Peserta akan dihapus dari jadwal training ini.',
    confirmText: 'Ya, hapus',
  });
  if (!result.isConfirmed) return;
  jaDelete(route('just-academy.schedules.participants.destroy', [props.schedule.id, id]));
}
</script>

<template>
  <JaLayout :title="schedule.title" subtitle="Kelola peserta, trainer, dan kehadiran" icon="fa-solid fa-calendar-check">
    <template #actions>
      <Link :href="route('just-academy.schedules.edit', schedule.id)" :class="jaUi.btnSecondary">Edit Jadwal</Link>
    </template>

    <div class="mb-6 text-sm text-slate-600">
      <p>{{ schedule.program?.title }} · {{ schedule.start_at }} — {{ schedule.end_at }}</p>
      <p class="text-slate-500">{{ schedule.location }} <span v-if="schedule.outlet">· {{ schedule.outlet.nama_outlet }}</span></p>
    </div>

    <div class="space-y-6">
      <div v-if="qrUrl" :class="[jaUi.card, jaUi.cardBody]">
        <p class="mb-2 font-medium text-slate-800">QR Check-in</p>
        <p class="break-all text-xs text-slate-500">{{ qrUrl }}</p>
      </div>

      <div :class="[jaUi.card, jaUi.cardBody]">
        <h2 class="mb-4 font-semibold text-slate-800">Undang Peserta</h2>
        <div class="space-y-4">
          <div>
            <input v-model="userSearch" type="text" placeholder="Cari nama user..." :class="[jaUi.input, 'max-w-md']" @input="searchUsers" />
            <ul v-if="userResults.length" class="mt-1 max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
              <li v-for="u in userResults" :key="u.id" class="cursor-pointer px-3 py-2 text-sm hover:bg-indigo-50" @click="addUser(u)">{{ u.name }} — {{ u.email }}</li>
            </ul>
            <div class="mt-2 flex flex-wrap gap-2">
              <span v-for="u in selectedUsers" :key="u.id" class="rounded-lg bg-indigo-50 px-2.5 py-1 text-sm text-indigo-800">{{ u.name }}</span>
            </div>
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <p class="mb-2 text-sm font-medium text-slate-700">By Jabatan</p>
              <label v-for="j in jabatanList" :key="j.id" class="mb-1 flex items-center gap-2 text-sm text-slate-600">
                <input v-model="selectedJabatan" type="checkbox" :value="j.id" class="rounded border-slate-300 text-indigo-600" /> {{ j.name }}
              </label>
            </div>
            <div>
              <p class="mb-2 text-sm font-medium text-slate-700">By Outlet</p>
              <label v-for="o in outletList" :key="o.id" class="mb-1 flex items-center gap-2 text-sm text-slate-600">
                <input v-model="selectedOutlets" type="checkbox" :value="o.id" class="rounded border-slate-300 text-indigo-600" /> {{ o.name }}
              </label>
            </div>
          </div>
          <button type="button" :class="jaUi.btnPrimary" @click="submitInvite">Kirim Undangan</button>
        </div>
      </div>

      <div :class="[jaUi.card, jaUi.cardBody]">
        <h2 class="mb-4 font-semibold text-slate-800">Peserta ({{ schedule.participants?.length || 0 }})</h2>
        <ul class="mb-4 space-y-2">
          <li v-for="p in schedule.participants" :key="p.id" class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 px-3 py-2">
            <span class="text-sm">{{ p.user?.name }} <span class="text-xs text-slate-400">({{ p.invite_source }})</span></span>
            <button type="button" :class="jaUi.btnDanger" @click="removeParticipant(p.id)">Hapus</button>
          </li>
        </ul>
        <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitAttendance">
          <div>
            <label class="text-sm text-slate-600">Mark hadir (user ID)</label>
            <input v-model="attendanceForm.user_id" type="number" :class="jaUi.input" placeholder="User ID" />
          </div>
          <button type="submit" :class="jaUi.btnSuccess">Mark Manual</button>
        </form>
      </div>

      <div :class="[jaUi.card, jaUi.cardBody]">
        <h2 class="mb-4 font-semibold text-slate-800">Trainer</h2>
        <ul class="mb-4 space-y-1 text-sm text-slate-700">
          <li v-for="t in schedule.trainers" :key="t.id">{{ t.user?.name }} ({{ t.role }})</li>
        </ul>
        <form class="flex flex-wrap gap-2" @submit.prevent="submitTrainer">
          <input v-model="trainerForm.user_id" type="number" placeholder="User ID trainer" :class="jaUi.input" required />
          <select v-model="trainerForm.role" :class="jaUi.select">
            <option value="primary">Primary</option>
            <option value="assistant">Assistant</option>
          </select>
          <button type="submit" :class="jaUi.btnPrimary">Tambah</button>
        </form>
      </div>
    </div>
  </JaLayout>
</template>
