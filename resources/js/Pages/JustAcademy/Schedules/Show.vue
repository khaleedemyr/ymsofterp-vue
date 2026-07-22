<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import QRCode from 'qrcode';
import VueEasyLightbox from 'vue-easy-lightbox';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import JaUserMultiselect from '@/Components/JustAcademy/JaUserMultiselect.vue';
import { jaUi, jaConfirmDelete, jaDelete, jaFormErrors, jaToastSuccess } from '@/composables/useJustAcademyUi';

const props = defineProps({
  schedule: Object,
  curriculum: { type: Array, default: () => [] },
  quizResults: {
    type: Object,
    default: () => ({ quizzes: [], rows: [] }),
  },
  qrUrl: String,
  jabatanList: { type: Array, default: () => [] },
  divisions: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
});

const showAddParticipants = ref(false);
const qrCodeDataUrl = ref('');
const qrLightboxVisible = ref(false);
const markingUserId = ref(null);
const selectedInviteUsers = ref([]);
const selectedQuizFilter = ref('all');
const attemptDetailOpen = ref(false);
const attemptDetailLoading = ref(false);
const attemptDetailError = ref('');
const attemptDetail = ref(null);

const inviteForm = useForm({ user_ids: [], jabatan_ids: [], outlet_ids: [] });
const attendanceForm = useForm({ user_id: '', notes: '' });

const participantCount = () => props.schedule.participants?.length || 0;
const trainerCount = () => props.schedule.trainers?.length || 0;

const quizResultQuizzes = computed(() => props.quizResults?.quizzes || []);
const quizResultRows = computed(() => props.quizResults?.rows || []);

const filteredQuizRows = computed(() => {
  const rows = quizResultRows.value;
  if (selectedQuizFilter.value === 'all') return rows;
  return rows.filter((r) => String(r.quiz_id) === String(selectedQuizFilter.value));
});

const quizResultSummary = computed(() => {
  const rows = filteredQuizRows.value;
  const submitted = rows.filter((r) => r.status === 'submitted');
  const passed = submitted.filter((r) => r.passed);
  return {
    total: rows.length,
    submitted: submitted.length,
    passed: passed.length,
    avgScore: submitted.length
      ? Math.round((submitted.reduce((sum, r) => sum + (Number(r.score) || 0), 0) / submitted.length) * 10) / 10
      : null,
  };
});

const attendedUserIds = computed(() => {
  const ids = new Set();
  (props.schedule.attendances || []).forEach((a) => {
    if (a.user_id) ids.add(a.user_id);
  });
  return ids;
});

const existingParticipantIds = computed(() => {
  const ids = new Set();
  (props.schedule.participants || []).forEach((p) => {
    if (p.user_id) ids.add(p.user_id);
  });
  return ids;
});

const qrLightboxImages = computed(() => (qrCodeDataUrl.value ? [qrCodeDataUrl.value] : []));

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

function formatScore(score) {
  if (score === null || score === undefined || score === '') return '—';
  const n = Number(score);
  if (Number.isNaN(n)) return '—';
  return Number.isInteger(n) ? `${n}` : n.toFixed(1);
}

function statusLabel(status) {
  if (status === 'submitted') return 'Selesai';
  if (status === 'in_progress') return 'Sedang dikerjakan';
  return 'Belum mengerjakan';
}

function statusClass(status) {
  if (status === 'submitted') return 'bg-emerald-100 text-emerald-700';
  if (status === 'in_progress') return 'bg-amber-100 text-amber-700';
  return 'bg-slate-100 text-slate-500';
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

function openQrLightbox() {
  if (!qrCodeDataUrl.value) return;
  qrLightboxVisible.value = true;
}

onMounted(() => {
  generateQrCode();
});

function submitInvite() {
  const userIds = selectedInviteUsers.value
    .map((u) => u.id)
    .filter((id) => !existingParticipantIds.value.has(id));

  if (!userIds.length) {
    jaFormErrors({ invite: 'Pilih minimal satu peserta baru.' });
    return;
  }

  inviteForm.user_ids = userIds;
  inviteForm.jabatan_ids = [];
  inviteForm.outlet_ids = [];
  inviteForm.post(route('just-academy.schedules.invite', props.schedule.id), {
    onSuccess: () => {
      selectedInviteUsers.value = [];
      showAddParticipants.value = false;
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

function materialTypeLabel(type) {
  const map = {
    pdf: 'PDF',
    video: 'Video',
    link: 'Link',
    document: 'Dokumen',
  };
  return map[type] || type || 'Materi';
}

function materialLink(item) {
  return item.file_path || item.url || null;
}

async function openAttemptDetail(row) {
  if (!row?.attempt_id || row.status !== 'submitted') return;

  attemptDetailOpen.value = true;
  attemptDetailLoading.value = true;
  attemptDetailError.value = '';
  attemptDetail.value = null;

  try {
    const { data } = await axios.get(
      route('just-academy.schedules.quiz-attempts.show', [props.schedule.id, row.attempt_id]),
    );
    attemptDetail.value = data.data;
  } catch (error) {
    attemptDetailError.value =
      error?.response?.data?.message || error?.message || 'Gagal memuat detail jawaban.';
  } finally {
    attemptDetailLoading.value = false;
  }
}

function closeAttemptDetail() {
  attemptDetailOpen.value = false;
  attemptDetailLoading.value = false;
  attemptDetailError.value = '';
  attemptDetail.value = null;
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
      <div v-if="curriculum.length" :class="[jaUi.card, jaUi.cardBody]">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="font-semibold text-slate-800">Materi & Quiz Program</h2>
          <Link
            v-if="schedule.program?.id"
            :href="route('just-academy.programs.edit', schedule.program.id)"
            :class="jaUi.btnLink"
            class="!text-xs"
          >
            Edit curriculum
          </Link>
        </div>

        <ul class="space-y-2">
          <li
            v-for="(item, index) in curriculum"
            :key="`${item.item_type}-${item.id}`"
            class="rounded-xl border border-slate-100 bg-slate-50/50 px-3 py-2 text-sm text-slate-700"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                  Langkah {{ index + 1 }} · {{ item.item_type === 'material' ? 'Materi' : 'Quiz' }}
                </p>
                <p class="font-medium text-slate-800">{{ item.title }}</p>
                <p v-if="item.item_type === 'material'" class="text-xs text-slate-500">
                  {{ materialTypeLabel(item.type) }}
                  <span v-if="item.is_required"> · Wajib</span>
                </p>
                <p v-else class="text-xs text-slate-500">
                  Pass score {{ item.pass_score }}%
                  <span v-if="item.question_count"> · {{ item.question_count }} soal</span>
                  <span v-if="item.is_required"> · Wajib</span>
                </p>
                <p v-if="item.description" class="mt-1 text-xs text-slate-500">{{ item.description }}</p>
              </div>
              <a
                v-if="item.item_type === 'material' && materialLink(item)"
                :href="materialLink(item)"
                target="_blank"
                rel="noopener noreferrer"
                :class="jaUi.btnLink"
                class="shrink-0 !text-xs"
              >
                Buka
              </a>
            </div>
          </li>
        </ul>
      </div>

      <div v-if="quizResultQuizzes.length" :class="[jaUi.card, jaUi.cardBody]">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="font-semibold text-slate-800">Hasil Quiz Peserta</h2>
            <p class="mt-1 text-xs text-slate-500">
              {{ quizResultSummary.submitted }}/{{ quizResultSummary.total }} selesai
              <span v-if="quizResultSummary.avgScore !== null">
                · rata-rata {{ formatScore(quizResultSummary.avgScore) }}
              </span>
              <span v-if="quizResultSummary.submitted">
                · lulus {{ quizResultSummary.passed }}/{{ quizResultSummary.submitted }}
              </span>
            </p>
          </div>
          <select
            v-if="quizResultQuizzes.length > 1"
            v-model="selectedQuizFilter"
            :class="jaUi.select"
            class="sm:w-64"
          >
            <option value="all">Semua quiz</option>
            <option v-for="q in quizResultQuizzes" :key="q.id" :value="String(q.id)">
              {{ q.title }}
            </option>
          </select>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-100">
          <table :class="jaUi.table">
            <thead :class="jaUi.thead">
              <tr>
                <th :class="jaUi.th">Peserta</th>
                <th :class="jaUi.th">Quiz</th>
                <th :class="jaUi.th">Status</th>
                <th :class="jaUi.th">Nilai</th>
                <th :class="jaUi.th">Hasil</th>
                <th :class="jaUi.th">Submit</th>
                <th :class="jaUi.th"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in filteredQuizRows" :key="`${row.user_id}-${row.quiz_id}-${idx}`" :class="jaUi.tr">
                <td :class="jaUi.td" class="font-medium text-slate-800">{{ row.user_name }}</td>
                <td :class="jaUi.td">{{ row.quiz_title }}</td>
                <td :class="jaUi.td">
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(row.status)">
                    {{ statusLabel(row.status) }}
                  </span>
                </td>
                <td :class="jaUi.td">
                  <span v-if="row.status === 'submitted'">{{ formatScore(row.score) }}</span>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td :class="jaUi.td">
                  <span
                    v-if="row.status === 'submitted' && row.passed"
                    class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700"
                  >
                    Lulus
                  </span>
                  <span
                    v-else-if="row.status === 'submitted'"
                    class="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700"
                  >
                    Belum lulus
                  </span>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td :class="jaUi.td" class="whitespace-nowrap text-xs text-slate-500">
                  {{ row.submitted_at ? formatDateTime(row.submitted_at) : '—' }}
                </td>
                <td :class="jaUi.td" class="text-right">
                  <button
                    v-if="row.status === 'submitted' && row.attempt_id"
                    type="button"
                    :class="jaUi.btnLink"
                    class="!text-xs"
                    @click="openAttemptDetail(row)"
                  >
                    Lihat jawaban
                  </button>
                  <span v-else class="text-xs text-slate-300">—</span>
                </td>
              </tr>
              <tr v-if="!filteredQuizRows.length">
                <td :class="jaUi.td" colspan="7" class="text-center text-slate-500">
                  Belum ada data hasil quiz untuk filter ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="qrUrl" :class="[jaUi.card, jaUi.cardBody]">
        <p class="mb-4 font-medium text-slate-800">QR Check-in</p>
        <div class="flex flex-col items-center gap-4 md:flex-row md:items-start">
          <button
            type="button"
            class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :disabled="!qrCodeDataUrl"
            title="Klik untuk perbesar"
            @click="openQrLightbox"
          >
            <img
              v-if="qrCodeDataUrl"
              :src="qrCodeDataUrl"
              :alt="`QR Check-in ${schedule.title}`"
              class="h-64 w-64 cursor-zoom-in"
            />
            <div v-else class="flex h-64 w-64 items-center justify-center text-sm text-slate-400">
              Memuat QR code...
            </div>
          </button>
          <div class="flex-1 space-y-2 text-sm text-slate-600">
            <p class="font-medium text-slate-800">Peserta scan QR ini untuk check-in</p>
            <p class="text-xs text-slate-500">Klik QR untuk memperbesar.</p>
            <p class="text-xs text-slate-500 break-all">{{ qrUrl }}</p>
          </div>
        </div>
      </div>

      <div :class="[jaUi.card, jaUi.cardBody]">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="font-semibold text-slate-800">Peserta ({{ participantCount() }})</h2>
          <button type="button" :class="jaUi.btnLink" class="!text-xs" @click="showAddParticipants = !showAddParticipants">
            {{ showAddParticipants ? 'Tutup tambah peserta' : '+ Tambah peserta' }}
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
        <p v-else class="mb-4 text-sm text-slate-500">Belum ada peserta. Tambahkan lewat Edit Training Plan atau form di bawah.</p>

        <div v-if="showAddParticipants" class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-4">
          <label :class="jaUi.label">Tambah peserta</label>
          <JaUserMultiselect
            v-model="selectedInviteUsers"
            :jabatan-list="jabatanList"
            :divisions="divisions"
            :outlets="outlets"
            show-filters
            placeholder="Cari nama, jabatan, divisi, atau outlet..."
          />
          <div class="mt-4 flex justify-end">
            <button
              type="button"
              :class="jaUi.btnPrimary"
              :disabled="inviteForm.processing || !selectedInviteUsers.length"
              @click="submitInvite"
            >
              {{ inviteForm.processing ? 'Menyimpan...' : 'Tambah Peserta' }}
            </button>
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

    <VueEasyLightbox
      :visible="qrLightboxVisible"
      :imgs="qrLightboxImages"
      :index="0"
      :move-disabled="false"
      :rotate-disabled="true"
      @hide="qrLightboxVisible = false"
    />

    <div
      v-if="attemptDetailOpen"
      :class="jaUi.modalOverlay"
      @click.self="closeAttemptDetail"
    >
      <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-4">
          <div>
            <h3 class="font-semibold text-slate-800">Detail Jawaban Quiz</h3>
            <p v-if="attemptDetail?.attempt" class="mt-1 text-sm text-slate-500">
              {{ attemptDetail.attempt.user_name }} · {{ attemptDetail.attempt.quiz_title }}
            </p>
          </div>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeAttemptDetail">
            ✕
          </button>
        </div>

        <div class="overflow-y-auto px-6 py-4">
          <div v-if="attemptDetailLoading" class="py-10 text-center text-sm text-slate-500">Memuat jawaban...</div>
          <div v-else-if="attemptDetailError" class="py-10 text-center text-sm text-rose-600">{{ attemptDetailError }}</div>
          <template v-else-if="attemptDetail">
            <div class="mb-4 flex flex-wrap gap-3 text-sm">
              <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                Nilai: <strong>{{ formatScore(attemptDetail.attempt.score) }}</strong>
              </span>
              <span
                class="rounded-full px-3 py-1"
                :class="attemptDetail.attempt.passed ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
              >
                {{ attemptDetail.attempt.passed ? 'Lulus' : 'Belum lulus' }}
                (pass {{ formatScore(attemptDetail.attempt.pass_score) }})
              </span>
              <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-600">
                Submit: {{ formatDateTime(attemptDetail.attempt.submitted_at) || '—' }}
              </span>
            </div>

            <ul class="space-y-3">
              <li
                v-for="ans in attemptDetail.answers"
                :key="ans.question_id"
                class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 text-sm"
              >
                <div class="mb-2 flex items-start justify-between gap-3">
                  <p class="font-medium text-slate-800">
                    {{ ans.number }}. {{ ans.question }}
                  </p>
                  <span
                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="ans.is_correct ? 'bg-emerald-100 text-emerald-700' : ans.type === 'essay' ? 'bg-slate-100 text-slate-500' : 'bg-rose-100 text-rose-700'"
                  >
                    <template v-if="ans.type === 'essay'">Essay</template>
                    <template v-else>{{ ans.is_correct ? 'Benar' : 'Salah' }}</template>
                  </span>
                </div>
                <p class="text-slate-700">
                  Jawaban:
                  <span class="font-medium">
                    {{ ans.type === 'essay' ? (ans.answer_text || '—') : (ans.selected_option || '—') }}
                  </span>
                </p>
                <p v-if="ans.type !== 'essay' && !ans.is_correct && ans.correct_option" class="mt-1 text-xs text-emerald-700">
                  Kunci: {{ ans.correct_option }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                  Poin {{ formatScore(ans.points_earned) }} / {{ formatScore(ans.points) }}
                </p>
              </li>
            </ul>
            <p v-if="!attemptDetail.answers?.length" class="py-6 text-center text-sm text-slate-500">
              Tidak ada jawaban tersimpan.
            </p>
          </template>
        </div>
      </div>
    </div>
  </JaLayout>
</template>
