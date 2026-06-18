<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { jaUi, jaFormErrors, jaToastSuccess } from '@/composables/useJustAcademyUi';

const props = defineProps({
  item: { type: Object, required: true },
  scheduleId: { type: Number, required: true },
});

const quizAnswers = useForm({ answers: {} });
const currentIndex = ref(props.item.session?.quiz_progress?.current_index ?? 0);
const questionStartedAt = ref(
  props.item.session?.quiz_progress?.question_started_at ?? props.item.session?.started_at,
);
const remainingSeconds = ref(0);
const handlingExpiry = ref(false);
let tickTimer = null;

const isPerQuestion = computed(() => props.item.time_limit?.mode === 'question');
const isQuizTotal = computed(() => props.item.time_limit?.mode === 'quiz');
const hasTimer = computed(() => isPerQuestion.value || isQuizTotal.value);
const totalQuestions = computed(() => props.item.questions?.length ?? 0);
const isLastQuestion = computed(() => currentIndex.value >= totalQuestions.value - 1);

const visibleQuestions = computed(() => {
  if (!isPerQuestion.value) {
    return props.item.questions ?? [];
  }
  const q = props.item.questions?.[currentIndex.value];
  return q ? [q] : [];
});

const displayQuestionNumber = computed(() => (isPerQuestion.value ? currentIndex.value + 1 : null));

function formatClock(total) {
  const seconds = Math.max(0, total);
  const minutes = Math.floor(seconds / 60);
  const rest = seconds % 60;
  return `${minutes}:${String(rest).padStart(2, '0')}`;
}

function calcRemaining() {
  if (!hasTimer.value) {
    return 0;
  }

  const now = Date.now();

  if (isQuizTotal.value) {
    const start = new Date(props.item.session?.started_at).getTime();
    const limit = (props.item.time_limit?.quiz_minutes || 0) * 60;
    return Math.max(0, limit - Math.floor((now - start) / 1000));
  }

  if (isPerQuestion.value) {
    const start = new Date(questionStartedAt.value).getTime();
    const limit = props.item.time_limit?.question_seconds || 0;
    return Math.max(0, limit - Math.floor((now - start) / 1000));
  }

  return 0;
}

function tick() {
  remainingSeconds.value = calcRemaining();
  if (remainingSeconds.value <= 0 && hasTimer.value && !handlingExpiry.value) {
    handleTimeExpired();
  }
}

function handleTimeExpired() {
  handlingExpiry.value = true;
  if (isPerQuestion.value && !isLastQuestion.value) {
    goNext(true);
    handlingExpiry.value = false;
    return;
  }
  submitQuiz(true);
}

function syncProgress() {
  router.post(
    route('just-academy.my-training.quizzes.progress', [props.scheduleId, props.item.id]),
    { current_index: currentIndex.value },
    { preserveScroll: true, preserveState: true },
  );
}

function goNext(fromTimer = false) {
  if (isLastQuestion.value) {
    submitQuiz(fromTimer);
    return;
  }

  currentIndex.value += 1;
  questionStartedAt.value = new Date().toISOString();
  handlingExpiry.value = false;
  syncProgress();
  tick();
}

function goPrev() {
  if (currentIndex.value <= 0) {
    return;
  }
  currentIndex.value -= 1;
  questionStartedAt.value = new Date().toISOString();
  handlingExpiry.value = false;
  syncProgress();
  tick();
}

function setAnswer(questionId, optionId) {
  quizAnswers.answers[questionId] = optionId;
}

function setEssayAnswer(questionId, text) {
  quizAnswers.answers[questionId] = text;
}

function submitQuiz(auto = false) {
  quizAnswers.post(route('just-academy.my-training.quizzes.submit', [props.scheduleId, props.item.id]), {
    onSuccess: () => {
      if (auto) {
        jaToastSuccess('Waktu habis — jawaban terakhir dikirim otomatis.');
      }
    },
    onError: (e) => jaFormErrors(e),
  });
}

onMounted(() => {
  tick();
  tickTimer = window.setInterval(tick, 1000);
});

onUnmounted(() => {
  if (tickTimer) {
    window.clearInterval(tickTimer);
  }
});
</script>

<template>
  <div>
    <p
      v-if="item.question_pool_size > item.questions_shown"
      class="mb-3 text-xs text-slate-500"
    >
      Menampilkan {{ item.questions_shown }} dari {{ item.question_pool_size }} soal di bank
      <span v-if="item.randomize_questions">· soal diacak</span>
      <span v-if="item.randomize_options">· opsi jawaban diacak</span>
    </p>

    <div
      v-if="hasTimer"
      class="mb-4 flex items-center justify-between rounded-xl border px-4 py-3"
      :class="remainingSeconds <= 30 ? 'border-rose-200 bg-rose-50' : 'border-indigo-200 bg-indigo-50'"
    >
      <div class="flex items-center gap-2 text-sm font-medium" :class="remainingSeconds <= 30 ? 'text-rose-800' : 'text-indigo-900'">
        <i class="fa-regular fa-clock" />
        <span v-if="isQuizTotal">Sisa waktu quiz</span>
        <span v-else>Soal {{ displayQuestionNumber }}/{{ totalQuestions }}</span>
      </div>
      <span class="font-mono text-lg font-bold" :class="remainingSeconds <= 30 ? 'text-rose-700' : 'text-indigo-700'">
        {{ formatClock(remainingSeconds) }}
      </span>
    </div>

    <div v-if="isPerQuestion" class="mb-4 h-1.5 overflow-hidden rounded-full bg-slate-100">
      <div
        class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-600 transition-all"
        :style="{ width: `${((currentIndex + 1) / totalQuestions) * 100}%` }"
      />
    </div>

    <div v-for="(q, qi) in visibleQuestions" :key="q.id" class="mb-4">
      <p class="mb-2 font-medium text-slate-800">
        <template v-if="isPerQuestion">{{ displayQuestionNumber }}.</template>
        <template v-else>{{ qi + 1 }}.</template>
        {{ q.question }}
      </p>
      <template v-if="q.type === 'mcq'">
        <label v-for="opt in q.options" :key="opt.id" class="mb-1 flex items-center gap-2 text-sm text-slate-600">
          <input
            type="radio"
            :name="'q' + q.id"
            class="text-indigo-600"
            :checked="quizAnswers.answers[q.id] === opt.id"
            @change="setAnswer(q.id, opt.id)"
          />
          {{ opt.option_text }}
        </label>
      </template>
      <textarea
        v-else
        rows="3"
        :class="jaUi.input"
        placeholder="Jawaban essay"
        :value="quizAnswers.answers[q.id] || ''"
        @input="setEssayAnswer(q.id, $event.target.value)"
      />
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button
        v-if="isPerQuestion && currentIndex > 0"
        type="button"
        :class="jaUi.btnSecondary"
        @click="goPrev"
      >
        Soal sebelumnya
      </button>
      <button
        v-if="isPerQuestion && !isLastQuestion"
        type="button"
        :class="jaUi.btnPrimary"
        @click="goNext(false)"
      >
        Soal berikutnya
      </button>
      <button
        v-if="!isPerQuestion || isLastQuestion"
        type="button"
        :class="jaUi.btnPrimary"
        :disabled="quizAnswers.processing"
        @click="submitQuiz(false)"
      >
        <i v-if="quizAnswers.processing" class="fa-solid fa-spinner fa-spin" />
        Kirim Quiz
      </button>
    </div>
  </div>
</template>
