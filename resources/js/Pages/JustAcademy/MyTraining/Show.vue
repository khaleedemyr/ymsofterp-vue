<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import JaCheckInScanner from '@/Components/JustAcademy/JaCheckInScanner.vue';
import JaQuizTaking from '@/Components/JustAcademy/JaQuizTaking.vue';
import { jaUi, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({
  schedule: Object,
  attendance: Object,
  curriculum: Array,
  trainingStarted: { type: Boolean, default: true },
  trainingStartsAt: String,
  checkedIn: { type: Boolean, default: false },
});

const feedbackForm = useForm({ rating: 5, comment: '', trainer_id: '' });

const activeItemKey = ref(null);
const activeQuizPayload = ref(null);
const startingQuiz = ref(false);
const quizStartError = ref('');

const isCheckedIn = computed(() => props.checkedIn || !!props.attendance?.check_in_at);
const startsAtLabel = computed(() => formatDateTime(props.trainingStartsAt || props.schedule?.start_at));

const activeItem = computed(() => {
  if (!activeItemKey.value) return null;
  return props.curriculum.find((item) => itemKey(item) === activeItemKey.value) || null;
});

function itemKey(item) {
  return `${item.item_type}-${item.id}`;
}

function formatDateTime(value) {
  if (!value) return '—';
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

function quizStatusLabel(item) {
  if (item.locked) return 'Terkunci';
  if (item.status === 'completed' || item.attempt) return item.attempt?.passed ? 'Lulus' : 'Selesai';
  if (item.status === 'in_progress') return 'Berlangsung';
  if (item.status === 'expired' || item.time_expired) return 'Waktu habis';
  return 'Belum dimulai';
}

function quizStatusClass(item) {
  if (item.locked) return 'bg-slate-100 text-slate-600';
  if (item.status === 'completed' || item.attempt) {
    return item.attempt?.passed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800';
  }
  if (item.status === 'in_progress') return 'bg-indigo-100 text-indigo-700';
  if (item.status === 'expired' || item.time_expired) return 'bg-rose-100 text-rose-700';
  return 'bg-slate-100 text-slate-600';
}

function materialStatusLabel(item) {
  if (item.locked) return 'Terkunci';
  if (item.completed) return 'Selesai';
  return 'Belum dibuka';
}

function materialStatusClass(item) {
  if (item.locked) return 'bg-slate-100 text-slate-600';
  if (item.completed) return 'bg-emerald-100 text-emerald-700';
  return 'bg-slate-100 text-slate-600';
}

function openMaterial(item) {
  if (item.file_path || item.url) {
    window.open(item.file_path || item.url, '_blank', 'noopener,noreferrer');
  }
  activeItemKey.value = itemKey(item);
  activeQuizPayload.value = null;
  quizStartError.value = '';
}

async function startQuiz(item) {
  startingQuiz.value = true;
  quizStartError.value = '';
  activeQuizPayload.value = null;

  try {
    const response = await axios.post(route('just-academy.my-training.quizzes.start', [props.schedule.id, item.id]));
    if (response.data?.success && response.data.quiz) {
      activeItemKey.value = itemKey(item);
      activeQuizPayload.value = response.data.quiz;
    }
  } catch (error) {
    quizStartError.value = error?.response?.data?.message
      || error?.response?.data?.errors?.quiz?.[0]
      || 'Gagal memulai quiz.';
  } finally {
    startingQuiz.value = false;
  }
}

function backToList() {
  activeItemKey.value = null;
  activeQuizPayload.value = null;
  quizStartError.value = '';
}

function onQuizFinished() {
  backToList();
  router.reload({ only: ['curriculum'] });
}

function completeMaterial(materialId) {
  useForm({}).post(route('just-academy.my-training.materials.complete', [props.schedule.id, materialId]), {
    onSuccess: () => {
      backToList();
      router.reload({ only: ['curriculum'] });
    },
    onError: (e) => jaFormErrors(e),
  });
}

function submitFeedback() {
  feedbackForm.post(route('just-academy.my-training.feedback', props.schedule.id), {
    onError: (e) => jaFormErrors(e),
  });
}
</script>

<template>
  <JaLayout :title="schedule.title" subtitle="Ikuti materi dan quiz sesuai urutan program" icon="fa-solid fa-user-graduate" narrow>
    <div class="mb-6 text-sm text-slate-600">
      <p>{{ schedule.program?.title }}</p>
      <p class="text-slate-500">
        {{ formatDateTime(schedule.start_at) }} — {{ formatDateTime(schedule.end_at) }}
        <span v-if="schedule.location"> · {{ schedule.location }}</span>
      </p>
      <p v-if="isCheckedIn" class="mt-2 text-emerald-600">
        ✓ Check-in: {{ formatDateTime(attendance?.check_in_at) }}
      </p>
    </div>

    <JaCheckInScanner v-if="!isCheckedIn" :schedule-id="schedule.id" class="mb-6" />

    <template v-else>
      <div
        v-if="!trainingStarted"
        class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
      >
        <p class="font-medium">Training belum dimulai</p>
        <p class="mt-1 text-amber-800">
          Materi dan quiz akan tersedia pada <strong>{{ startsAtLabel }}</strong>.
        </p>
      </div>

      <p v-if="quizStartError" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ quizStartError }}
      </p>

      <!-- Daftar langkah -->
      <div v-if="!activeItemKey" class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Daftar Materi & Quiz</h2>

        <div
          v-for="(item, index) in curriculum"
          :key="itemKey(item)"
          :class="[jaUi.card, jaUi.cardBody, item.locked ? 'opacity-80' : '']"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                Langkah {{ index + 1 }} · {{ item.item_type === 'material' ? 'Materi' : 'Quiz' }}
              </p>
              <p class="mt-1 font-semibold text-slate-800">{{ item.title }}</p>

              <template v-if="item.item_type === 'quiz' && !item.locked">
                <p v-if="item.time_limit?.mode === 'quiz'" class="mt-1 text-xs text-slate-500">
                  Batas waktu: {{ item.time_limit.quiz_minutes }} menit
                </p>
                <p v-else-if="item.time_limit?.mode === 'question'" class="mt-1 text-xs text-slate-500">
                  Batas waktu: {{ item.time_limit.question_seconds }} detik per soal
                </p>
                <p v-if="item.attempt || item.status === 'completed'" class="mt-1 text-xs text-slate-600">
                  Nilai: <strong>{{ item.attempt?.score }}</strong>
                  — {{ item.attempt?.passed ? 'Lulus' : 'Belum lulus' }}
                  <span class="text-slate-400">(pass {{ item.pass_score }})</span>
                </p>
              </template>
            </div>

            <span
              class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
              :class="item.item_type === 'material' ? materialStatusClass(item) : quizStatusClass(item)"
            >
              {{ item.item_type === 'material' ? materialStatusLabel(item) : quizStatusLabel(item) }}
            </span>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <template v-if="item.item_type === 'material'">
              <button
                v-if="!item.locked"
                type="button"
                :class="jaUi.btnPrimary"
                class="text-sm"
                @click="openMaterial(item)"
              >
                {{ item.completed ? 'Lihat materi' : 'Buka materi' }}
              </button>
              <span v-else class="text-xs text-slate-400"><i class="fa-solid fa-lock mr-1" />Terkunci</span>
            </template>

            <template v-else-if="item.item_type === 'quiz'">
              <template v-if="item.locked">
                <span class="text-xs text-slate-400"><i class="fa-solid fa-lock mr-1" />Terkunci</span>
              </template>
              <template v-else-if="!(item.attempt || item.status === 'completed')">
                <button
                  type="button"
                  :class="jaUi.btnPrimary"
                  class="text-sm"
                  :disabled="startingQuiz"
                  @click="startQuiz(item)"
                >
                  <i v-if="startingQuiz" class="fa-solid fa-spinner fa-spin mr-1" />
                  {{ item.status === 'in_progress' ? 'Lanjutkan quiz' : (item.status === 'expired' ? 'Mulai ulang quiz' : 'Mulai quiz') }}
                </button>
              </template>
            </template>
          </div>
        </div>
      </div>

      <!-- Quiz aktif (tanpa tombol back) -->
      <div v-else-if="activeQuizPayload" class="space-y-4">
        <div :class="[jaUi.card, jaUi.cardBody]">
          <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Quiz</p>
          <h2 class="mb-2 font-semibold text-slate-800">{{ activeQuizPayload.title }}</h2>

          <p v-if="activeQuizPayload.time_limit?.mode === 'quiz'" class="mb-2 text-xs text-amber-700">
            Batas waktu: {{ activeQuizPayload.time_limit.quiz_minutes }} menit (total quiz)
          </p>
          <p v-else-if="activeQuizPayload.time_limit?.mode === 'question'" class="mb-2 text-xs text-amber-700">
            Batas waktu: {{ activeQuizPayload.time_limit.question_seconds }} detik per soal
          </p>
          <JaQuizTaking
            :item="activeQuizPayload"
            :schedule-id="schedule.id"
            @finished="onQuizFinished"
          />
        </div>
      </div>

      <!-- Detail materi -->
      <div v-else class="space-y-4">
        <div v-if="activeItem?.item_type === 'material'" :class="[jaUi.card, jaUi.cardBody]">
          <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Materi</p>
          <h2 class="mb-4 font-semibold text-slate-800">{{ activeItem.title }}</h2>

          <a
            v-if="activeItem.file_path || activeItem.url"
            :href="activeItem.file_path || activeItem.url"
            target="_blank"
            rel="noopener noreferrer"
            :class="jaUi.btnLink"
          >
            Buka materi
          </a>
          <p v-else class="text-sm text-slate-500">Materi tidak tersedia.</p>

          <div class="mt-4">
            <button
              v-if="!activeItem.completed"
              type="button"
              :class="jaUi.btnSuccess"
              @click="completeMaterial(activeItem.id)"
            >
              Tandai selesai
            </button>
            <span v-else class="text-sm font-medium text-emerald-600">Materi sudah selesai</span>
          </div>
        </div>
      </div>

      <div v-if="trainingStarted && !activeItemKey" :class="[jaUi.card, jaUi.cardBody, 'mt-6']">
        <h2 class="mb-4 font-semibold text-slate-800">Feedback</h2>
        <form class="space-y-3" @submit.prevent="submitFeedback">
          <div>
            <label class="text-sm text-slate-600">Rating (1-5)</label>
            <input v-model="feedbackForm.rating" type="number" min="1" max="5" class="mt-1 w-24" :class="jaUi.input" />
          </div>
          <textarea v-model="feedbackForm.comment" rows="3" :class="jaUi.input" placeholder="Komentar" />
          <button type="submit" :class="jaUi.btnPrimary">Kirim Feedback</button>
        </form>
      </div>
    </template>
  </JaLayout>
</template>
