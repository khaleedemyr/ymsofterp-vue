<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ quiz: Object });

function defaultQuestion() {
  return {
    question: '',
    type: 'mcq',
    points: 1,
    options: [
      { option_text: '', is_correct: true },
      { option_text: '', is_correct: false },
    ],
  };
}

const form = useForm({
  title: props.quiz?.title || '',
  pass_score: props.quiz?.pass_score ?? 70,
  time_limit_min: props.quiz?.time_limit_min || '',
  is_active: props.quiz?.is_active ?? true,
  questions: props.quiz?.questions?.length
    ? props.quiz.questions.map(q => ({
        question: q.question,
        type: q.type,
        points: q.points,
        options: q.options?.length
          ? q.options.map(o => ({ option_text: o.option_text, is_correct: !!o.is_correct }))
          : [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }],
      }))
    : [defaultQuestion()],
});

function addQuestion() {
  form.questions.push(defaultQuestion());
}

function removeQuestion(qi) {
  if (form.questions.length <= 1) return;
  form.questions.splice(qi, 1);
}

function addOption(qi) {
  form.questions[qi].options.push({ option_text: '', is_correct: false });
}

function removeOption(qi, oi) {
  if (form.questions[qi].options.length <= 2) return;
  form.questions[qi].options.splice(oi, 1);
}

function setCorrect(qi, oi) {
  form.questions[qi].options.forEach((opt, i) => {
    opt.is_correct = i === oi;
  });
}

function submit() {
  if (props.quiz) {
    form.put(route('just-academy.quizzes.update', props.quiz.id));
  } else {
    form.post(route('just-academy.quizzes.store'));
  }
}
</script>

<template>
  <AppLayout :title="quiz ? 'Edit Quiz' : 'Quiz Baru'">
    <div class="max-w-3xl mx-auto py-8 px-2 space-y-6">
      <h1 class="text-2xl font-bold">{{ quiz ? 'Edit Quiz' : 'Quiz Baru' }}</h1>

      <form class="bg-white rounded-2xl shadow p-6 space-y-4" @submit.prevent="submit">
        <input v-model="form.title" placeholder="Judul quiz" class="w-full border rounded-xl px-3 py-2" required />
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Pass score (%)</label>
            <input v-model="form.pass_score" type="number" min="0" max="100" class="w-full border rounded-xl px-3 py-2" required />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Batas waktu (menit)</label>
            <input v-model="form.time_limit_min" type="number" min="1" class="w-full border rounded-xl px-3 py-2" placeholder="Kosongkan = tanpa batas" />
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Aktif</label>

        <div v-for="(q, qi) in form.questions" :key="qi" class="border rounded-xl p-4 space-y-3">
          <div class="flex justify-between items-center">
            <span class="text-sm font-semibold text-gray-600">Pertanyaan {{ qi + 1 }}</span>
            <button v-if="form.questions.length > 1" type="button" class="text-red-600 text-sm" @click="removeQuestion(qi)">Hapus</button>
          </div>
          <textarea v-model="q.question" rows="2" class="w-full border rounded-xl px-3 py-2" placeholder="Teks pertanyaan" required></textarea>
          <select v-model="q.type" class="border rounded-xl px-3 py-2">
            <option value="mcq">Pilihan ganda</option>
            <option value="essay">Essay</option>
          </select>

          <template v-if="q.type === 'mcq'">
            <div v-for="(opt, oi) in q.options" :key="oi" class="flex gap-2 items-center">
              <input v-model="opt.option_text" class="flex-1 border rounded-xl px-3 py-2" placeholder="Opsi jawaban" />
              <label class="text-sm flex items-center gap-1 shrink-0">
                <input type="radio" :name="'correct-'+qi" :checked="opt.is_correct" @change="setCorrect(qi, oi)" /> Benar
              </label>
              <button v-if="q.options.length > 2" type="button" class="text-red-500 text-sm" @click="removeOption(qi, oi)">×</button>
            </div>
            <button type="button" class="text-indigo-600 text-sm" @click="addOption(qi)">+ Opsi jawaban</button>
          </template>
        </div>

        <button type="button" class="text-indigo-600 text-sm" @click="addQuestion">+ Pertanyaan</button>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl block" :disabled="form.processing">Simpan Quiz</button>
      </form>
    </div>
  </AppLayout>
</template>
