<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import QRCode from 'qrcode';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaConfirmDelete, jaDelete, jaFormErrors, jaToastSuccess } from '@/composables/useJustAcademyUi';

const props = defineProps({ schedule: Object, qrUrl: String });

const showBulkInvite = ref(false);
const qrCodeDataUrl = ref('');
const markingUserId = ref(null);
const userSearch = ref('');
const userResults = ref([]);
const selectedUsers = ref([]);
const jabatanList = ref([]);
const outletList = ref([]);
const selectedJabatan = ref([]);
const selectedOutlets = ref([]);

const inviteForm = useForm({ user_ids: [], jabatan_ids: [], outlet_ids: [] });
const attendanceForm = useForm({ user_id: '', notes: '' });

const participantCount = () => props.schedule.participants?.length || 0;
const trainerCount = () => props.schedule.trainers?.length || 0;

const attendedUserIds = computed(() => {
  const ids = new Set();
  (props.schedule.attendances || []).forEach((a) => {
    if (a.user_id) ids.add(a.user_id);
  });
  return ids;
});

const schedulePeriod = computed(() => {
  const start = formatDateTime(props.schedule.start_at);
  const end = formatDateTime(props.schedule.end_at);
  return start && end ? `${start} — ${end}` : '—';
});

function formatDateTime(value) {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

async function generateQrCode() {
  if (!props.qrUrl) return;
  try {
    qrCodeDataUrl.value = await QRCode.toDataURL(props.qrUrl, {
      width: 280,
      margin: 2,
      color: { dark: '#1e293b', light: '#ffffff' },
    });
  } catch (error) {
    console.error('QR generation failed', error);
  }
}

onMounted(async () => {
  await generateQrCode();
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
  if (!selectedUsers.value.find((x) => x.id === u.id)) selectedUsers.value.push(u);
  userSearch.value = '';
  userResults.value = [];
}

function submitInvite() {
  inviteForm.user_ids = selectedUsers.value.map((u) => u.id);
  inviteForm.jabatan_ids = selectedJabatan.value;
  inviteForm.outlet_ids = selectedOutlets.value;
  inviteForm.post(route('just-academy.schedules.invite', props.schedule.id), {
    onSuccess: () => {
      selectedUsers.value = [];
      selectedJabatan.value = [];
      selectedOutlets.value = [];
      showBulkInvite.value = false;
    },
    onError: (e) => jaFormErrors(e),
  });
}

function hasAttendance(userId) {
  return userId && attendedUserIds.value.has(userId);
}

function markParticipantPresent(participant) {
  const userId = participant?.user?.id;
  if (!userId || hasAttendance(userId) || markingUserId.value) return;

  markingUserId.value = userId;
  attendanceForm.user_id = userId;
  attendanceForm.notes = '';
  attendanceForm.post(route('just-academy.schedules.attendance.manual', props.schedule.id), {
    preserveScroll: true,
    onSuccess: () => {
      jaToastSuccess(`${userLabel(participant.user)} ditandai hadir.`);
    },
    onError: (e) => jaFormErrors(e),
    onFinish: () => {
      markingUserId.value = null;
      attendanceForm.reset();
    },
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

async function removeSchedule() {
  const result = await jaConfirmDelete({
    title: 'Hapus training plan?',
    html: `Training plan <strong>${props.schedule.title}</strong> akan dihapus permanen.`,
    confirmText: 'Ya, hapus',
  });
  if (!result.isConfirmed) return;
  jaDelete(route('just-academy.schedules.destroy', props.schedule.id));
}

function userLabel(user) {
  return user?.nama_lengkap || user?.name || '—';
}
</script>

<template>
  <JaLayout :title="schedule.title" subtitle="Detail training plan" icon="fa-solid fa-calendar-check">
    <template #actions>
      <button type="button" :class="jaUi.btnDanger" @click="removeSchedule">Hapus</button>
      <Link :href="route('just-academy.schedules.index')" :class="jaUi.btnSecondary">Kalender</Link>
      <Link :href="route('just-academy.schedules.edit', schedule.id)" :class="jaUi.btnPrimary">Edit Training Plan</Link>
    </template>

    <div class="mb-6 text-sm text-slate-600">
      <p>{{ schedule.program?.title }} · {{ schedulePeriod }}</p>
      <p class="text-slate-500">
        {{ schedule.location || '—' }}
        <span v-if="schedule.outlet"> · {{ schedule.outlet.nama_outlet }}</span>
        <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs capitalize text-slate-600">{{ schedule.status }}</span>
      </p>
    </div>

    <div class="space-y-6">
      <div v-if="qrUrl" :class="[jaUi.card, jaUi.cardBody]">
        <p class="mb-4 font-medium text-slate-800">QR Check-in</p>
        <div class="flex flex-col items-center gap-4 md:flex-row md:items-start">
          <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <img
              v-if="qrCodeDataUrl"
              :src="qrCodeDataUrl"
              :alt="`QR Check-in ${schedule.title}`"
              class="h-64 w-64"
            />
            <div v-else class="flex h-64 w-64 items-center justify-center text-sm text-slate-400">
              Memuat QR code...
            </div>
          </div>
          <div class="flex-1 space-y-2 text-sm text-slate-600">
            <p class="font-medium text-slate-800">Peserta scan QR ini untuk check-in</p>
            <p class="text-xs text-slate-500 break-all">{{ qrUrl }}</p>
          </div>
        </div>
      </div>

      <div :class="[jaUi.card, jaUi.cardBody]">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="font-semibold text-slate-800">Peserta ({{ participantCount() }})</h2>
          <button type="button" :class="jaUi.btnLink" class="!text-xs" @click="showBulkInvite = !showBulkInvite">
            {{ showBulkInvite ? 'Tutup tambah peserta' : '+ Tambah peserta massal' }}
          </button>
        </div>

        <p class="mb-3 text-xs text-slate-500">Klik nama peserta untuk tandai hadir manual.</p>

        <ul v-if="participantCount()" class="mb-4 space-y-2">
          <li
            v-for="p in schedule.participants"
            :key="p.id"
            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 px-3 py-2"
          >
            <button
              type="button"
              class="min-w-0 flex-1 text-left text-sm transition"
              :class="[
                hasAttendance(p.user?.id)
                  ? 'cursor-default text-emerald-700'
                  : 'cursor-pointer text-indigo-700 hover:underline',
                markingUserId === p.user?.id ? 'opacity-60' : '',
              ]"
              :disabled="hasAttendance(p.user?.id) || markingUserId === p.user?.id"
              @click="markParticipantPresent(p)"
            >
              {{ userLabel(p.user) }}
              <span class="text-xs text-slate-400">({{ p.invite_source }})</span>
              <span
                v-if="hasAttendance(p.user?.id)"
                class="ml-2 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700"
              >
                Hadir
              </span>
              <span
                v-else-if="markingUserId === p.user?.id"
                class="ml-2 text-xs text-slate-400"
              >
                Memproses...
              </span>
            </button>
            <button type="button" :class="jaUi.btnDanger" class="ml-3 shrink-0" @click="removeParticipant(p.id)">
              Hapus
            </button>
          </li>
        </ul>
        <p v-else class="mb-4 text-sm text-slate-500">Belum ada peserta. Tambahkan lewat Edit Training Plan atau undang massal di bawah.</p>

        <div v-if="showBulkInvite" class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-4">
          <p class="mb-3 text-xs text-slate-500">Opsional: undang tambahan per user, jabatan, atau outlet.</p>
          <div class="space-y-4">
            <div>
              <input
                v-model="userSearch"
                type="text"
                placeholder="Cari nama user..."
                :class="[jaUi.input, 'max-w-md']"
                @input="searchUsers"
              />
              <ul v-if="userResults.length" class="mt-1 max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                <li
                  v-for="u in userResults"
                  :key="u.id"
                  class="cursor-pointer px-3 py-2 text-sm hover:bg-indigo-50"
                  @click="addUser(u)"
                >
                  {{ u.nama_lengkap || u.name }} — {{ u.email }}
                </li>
              </ul>
              <div class="mt-2 flex flex-wrap gap-2">
                <span v-for="u in selectedUsers" :key="u.id" class="rounded-lg bg-indigo-50 px-2.5 py-1 text-sm text-indigo-800">
                  {{ u.nama_lengkap || u.name }}
                </span>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <p class="mb-2 text-sm font-medium text-slate-700">By Jabatan</p>
                <label v-for="j in jabatanList" :key="j.id" class="mb-1 flex items-center gap-2 text-sm text-slate-600">
                  <input v-model="selectedJabatan" type="checkbox" :value="j.id" class="rounded border-slate-300 text-indigo-600" />
                  {{ j.name }}
                </label>
              </div>
              <div>
                <p class="mb-2 text-sm font-medium text-slate-700">By Outlet</p>
                <label v-for="o in outletList" :key="o.id" class="mb-1 flex items-center gap-2 text-sm text-slate-600">
                  <input v-model="selectedOutlets" type="checkbox" :value="o.id" class="rounded border-slate-300 text-indigo-600" />
                  {{ o.name }}
                </label>
              </div>
            </div>
            <button type="button" :class="jaUi.btnPrimary" @click="submitInvite">Kirim Undangan Tambahan</button>
          </div>
        </div>
      </div>

      <div :class="[jaUi.card, jaUi.cardBody]">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="font-semibold text-slate-800">Trainer ({{ trainerCount() }})</h2>
          <Link :href="route('just-academy.schedules.edit', schedule.id)" :class="jaUi.btnLink" class="!text-xs">
            Edit trainer
          </Link>
        </div>
        <ul v-if="trainerCount()" class="space-y-2 text-sm text-slate-700">
          <li
            v-for="t in schedule.trainers"
            :key="t.id"
            class="rounded-xl border border-slate-100 bg-slate-50/50 px-3 py-2"
          >
            <span v-if="t.trainer_type === 'external'">{{ t.external_name }}</span>
            <span v-else>{{ userLabel(t.user) }}</span>
            <span class="text-slate-400"> · {{ t.trainer_type === 'external' ? 'Eksternal' : 'Internal' }} ({{ t.role }})</span>
          </li>
        </ul>
        <p v-else class="text-sm text-slate-500">Belum ada trainer. Atur lewat Edit Training Plan.</p>
      </div>
    </div>
  </JaLayout>
</template>
