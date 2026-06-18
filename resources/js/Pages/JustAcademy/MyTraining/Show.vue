<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import JaQuizTaking from '@/Components/JustAcademy/JaQuizTaking.vue';
import { jaUi, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({
  schedule: Object,
  attendance: Object,
  curriculum: Array,
  trainingStarted: { type: Boolean, default: true },
  trainingStartsAt: String,
});

const feedbackForm = useForm({ rating: 5, comment: '', trainer_id: '' });

const startsAtLabel = computed(() => formatDateTime(props.trainingStartsAt || props.schedule?.start_at));

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

function completeMaterial(materialId) {
  useForm({}).post(route('just-academy.my-training.materials.complete', [props.schedule.id, materialId]), {
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
      <p v-if="attendance?.check_in_at" class="mt-2 text-emerald-600">✓ Check-in: {{ formatDateTime(attendance.check_in_at) }}</p>
    </div>

    <div
      v-if="!trainingStarted"
      class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
    >
      <p class="font-medium">Training belum dimulai</p>
      <p class="mt-1 text-amber-800">
        Materi dan quiz akan tersedia pada <strong>{{ startsAtLabel }}</strong>.
      </p>
    </div>

    <div class="space-y-4">
      <template v-for="(item, index) in curriculum" :key="item.item_type + '-' + item.id">
        <div v-if="item.item_type === 'material'" :class="[jaUi.card, jaUi.cardBody, item.locked ? 'opacity-80' : '']">
          <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Langkah {{ index + 1 }} · Materi</p>
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="font-semibold text-slate-800">{{ item.title }}</p>
              <a
                v-if="!item.locked && (item.file_path || item.url)"
                :href="item.file_path || item.url"
                target="_blank"
                rel="noopener noreferrer"
                :class="jaUi.btnLink"
              >
                Buka materi
              </a>
              <p v-else-if="item.locked" class="mt-1 text-xs text-amber-700">Terkunci sampai training dimulai</p>
            </div>
            <button
              v-if="!item.locked && !item.completed"
              type="button"
              :class="jaUi.btnSuccess"
              @click="completeMaterial(item.id)"
            >
              Selesai
            </button>
            <span v-else-if="item.completed" class="text-sm font-medium text-emerald-600">Selesai</span>
            <span v-else-if="item.locked" class="text-xs text-slate-400"><i class="fa-solid fa-lock" /></span>
          </div>
        </div>

        <div v-else-if="item.item_type === 'quiz'" :class="[jaUi.card, jaUi.cardBody, item.locked ? 'opacity-80' : '']">
          <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Langkah {{ index + 1 }} · Quiz</p>
          <h2 class="mb-2 font-semibold text-slate-800">{{ item.title }}</h2>
          <template v-if="item.locked">
            <p class="text-xs text-amber-700">Quiz akan tersedia saat training dimulai.</p>
          </template>
          <template v-else>
            <p v-if="item.time_limit?.mode === 'quiz'" class="mb-2 text-xs text-amber-700">
              Batas waktu: {{ item.time_limit.quiz_minutes }} menit (total quiz)
            </p>
            <p v-else-if="item.time_limit?.mode === 'question'" class="mb-2 text-xs text-amber-700">
              Batas waktu: {{ item.time_limit.question_seconds }} detik per soal
            </p>
            <p v-if="item.attempt" class="mb-4 text-sm text-slate-600">
              Nilai: {{ item.attempt.score }} — {{ item.attempt.passed ? 'Lulus' : 'Belum lulus' }}
            </p>
            <JaQuizTaking v-else :item="item" :schedule-id="schedule.id" />
          </template>
        </div>
      </template>
    </div>

    <div v-if="trainingStarted" :class="[jaUi.card, jaUi.cardBody, 'mt-6']">
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
  </JaLayout>
</template>
