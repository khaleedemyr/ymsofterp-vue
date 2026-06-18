<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  schedule: Object,
  attendance: Object,
  materials: Array,
  quizzes: Array,
});

const feedbackForm = useForm({ rating: 5, comment: '', trainer_id: '' });
const quizAnswers = useForm({ answers: {} });

function completeMaterial(materialId) {
  useForm({}).post(route('just-academy.my-training.materials.complete', [props.schedule.id, materialId]));
}

function submitQuiz(quizId) {
  quizAnswers.post(route('just-academy.my-training.quizzes.submit', [props.schedule.id, quizId]));
}

function submitFeedback() {
  feedbackForm.post(route('just-academy.my-training.feedback', props.schedule.id));
}

function setAnswer(questionId, optionId) {
  quizAnswers.answers[questionId] = optionId;
}
</script>

<template>
  <AppLayout :title="schedule.title">
    <div class="max-w-4xl mx-auto py-8 px-2 space-y-6">
      <div>
        <h1 class="text-2xl font-bold">{{ schedule.title }}</h1>
        <p class="text-gray-600">{{ schedule.program?.title }}</p>
        <p class="text-sm text-gray-500">{{ schedule.start_at }} — {{ schedule.end_at }} · {{ schedule.location }}</p>
        <p v-if="attendance?.check_in_at" class="text-emerald-600 text-sm mt-2">✓ Check-in: {{ attendance.check_in_at }}</p>
      </div>

      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-semibold mb-4">Materi</h2>
        <ul class="space-y-3">
          <li v-for="m in materials" :key="m.id" class="border rounded-xl p-3 flex justify-between items-center">
            <div>
              <p class="font-medium">{{ m.title }}</p>
              <a v-if="m.file_path || m.url" :href="m.file_path || m.url" target="_blank" class="text-indigo-600 text-sm">Buka materi</a>
            </div>
            <button v-if="!m.completed" type="button" class="text-sm bg-emerald-600 text-white px-3 py-1 rounded-lg" @click="completeMaterial(m.id)">Selesai</button>
            <span v-else class="text-emerald-600 text-sm">Selesai</span>
          </li>
        </ul>
      </div>

      <div v-for="quiz in quizzes" :key="quiz.id" class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-semibold mb-2">{{ quiz.title }} ({{ quiz.type }})</h2>
        <p v-if="quiz.attempt" class="text-sm text-gray-600 mb-4">Nilai: {{ quiz.attempt.score }} — {{ quiz.attempt.passed ? 'Lulus' : 'Belum lulus' }}</p>
        <template v-else>
          <div v-for="q in quiz.questions" :key="q.id" class="mb-4">
            <p class="font-medium mb-2">{{ q.question }}</p>
            <label v-for="opt in q.options" :key="opt.id" class="flex items-center gap-2 text-sm mb-1">
              <input type="radio" :name="'q'+q.id" @change="setAnswer(q.id, opt.id)" /> {{ opt.option_text }}
            </label>
          </div>
          <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded-xl" @click="submitQuiz(quiz.id)">Kirim Quiz</button>
        </template>
      </div>

      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="font-semibold mb-4">Feedback</h2>
        <form class="space-y-3" @submit.prevent="submitFeedback">
          <div>
            <label class="text-sm">Rating (1-5)</label>
            <input v-model="feedbackForm.rating" type="number" min="1" max="5" class="border rounded-xl px-3 py-2 w-24" />
          </div>
          <textarea v-model="feedbackForm.comment" rows="3" class="w-full border rounded-xl px-3 py-2" placeholder="Komentar"></textarea>
          <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl">Kirim Feedback</button>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
