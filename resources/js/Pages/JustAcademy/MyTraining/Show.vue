<script setup>
import { useForm } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({
  schedule: Object,
  attendance: Object,
  curriculum: Array,
});

const feedbackForm = useForm({ rating: 5, comment: '', trainer_id: '' });
const quizAnswers = useForm({ answers: {} });

function completeMaterial(materialId) {
  useForm({}).post(route('just-academy.my-training.materials.complete', [props.schedule.id, materialId]), {
    onError: (e) => jaFormErrors(e),
  });
}

function submitQuiz(quizId) {
  quizAnswers.post(route('just-academy.my-training.quizzes.submit', [props.schedule.id, quizId]), {
    onError: (e) => jaFormErrors(e),
  });
}

function submitFeedback() {
  feedbackForm.post(route('just-academy.my-training.feedback', props.schedule.id), {
    onError: (e) => jaFormErrors(e),
  });
}

function setAnswer(questionId, optionId) {
  quizAnswers.answers[questionId] = optionId;
}
</script>

<template>
  <JaLayout :title="schedule.title" subtitle="Ikuti materi dan quiz sesuai urutan program" icon="fa-solid fa-user-graduate" narrow>
    <div class="mb-6 text-sm text-slate-600">
      <p>{{ schedule.program?.title }}</p>
      <p class="text-slate-500">{{ schedule.start_at }} — {{ schedule.end_at }} · {{ schedule.location }}</p>
      <p v-if="attendance?.check_in_at" class="mt-2 text-emerald-600">✓ Check-in: {{ attendance.check_in_at }}</p>
    </div>

    <div class="space-y-4">
      <template v-for="(item, index) in curriculum" :key="item.item_type + '-' + item.id">
        <div v-if="item.item_type === 'material'" :class="[jaUi.card, jaUi.cardBody]">
          <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Langkah {{ index + 1 }} · Materi</p>
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="font-semibold text-slate-800">{{ item.title }}</p>
              <a v-if="item.file_path || item.url" :href="item.file_path || item.url" target="_blank" :class="jaUi.btnLink">Buka materi</a>
            </div>
            <button v-if="!item.completed" type="button" :class="jaUi.btnSuccess" @click="completeMaterial(item.id)">Selesai</button>
            <span v-else class="text-sm font-medium text-emerald-600">Selesai</span>
          </div>
        </div>

        <div v-else-if="item.item_type === 'quiz'" :class="[jaUi.card, jaUi.cardBody]">
          <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Langkah {{ index + 1 }} · Quiz</p>
          <h2 class="mb-2 font-semibold text-slate-800">{{ item.title }}</h2>
          <p v-if="item.attempt" class="mb-4 text-sm text-slate-600">Nilai: {{ item.attempt.score }} — {{ item.attempt.passed ? 'Lulus' : 'Belum lulus' }}</p>
          <template v-else>
            <div v-for="q in item.questions" :key="q.id" class="mb-4">
              <p class="mb-2 font-medium text-slate-800">{{ q.question }}</p>
              <label v-for="opt in q.options" :key="opt.id" class="mb-1 flex items-center gap-2 text-sm text-slate-600">
                <input type="radio" :name="'q'+q.id" class="text-indigo-600" @change="setAnswer(q.id, opt.id)" /> {{ opt.option_text }}
              </label>
            </div>
            <button type="button" :class="jaUi.btnPrimary" @click="submitQuiz(item.id)">Kirim Quiz</button>
          </template>
        </div>
      </template>
    </div>

    <div :class="[jaUi.card, jaUi.cardBody, 'mt-6']">
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
