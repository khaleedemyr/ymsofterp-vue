<script setup>
import { useForm } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaFormErrors } from '@/composables/useJustAcademyUi';

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
  const opts = { onError: (e) => jaFormErrors(e) };
  if (props.quiz) {
    form.put(route('just-academy.quizzes.update', props.quiz.id), opts);
  } else {
    form.post(route('just-academy.quizzes.store'), opts);
  }
}
</script>

<template>
  <JaLayout
    :title="quiz ? 'Edit Quiz' : 'Quiz Baru'"
    subtitle="Tambah pertanyaan dan opsi jawaban sebanyak yang dibutuhkan"
    icon="fa-solid fa-circle-question"
    narrow
  >
    <form :class="[jaUi.card, jaUi.cardBody, 'space-y-5']" @submit.prevent="submit">
      <input v-model="form.title" placeholder="Judul quiz" :class="jaUi.input" required />
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label :class="jaUi.label">Pass score (%)</label>
          <input v-model="form.pass_score" type="number" min="0" max="100" :class="jaUi.input" required />
        </div>
        <div>
          <label :class="jaUi.label">Batas waktu (menit)</label>
          <input v-model="form.time_limit_min" type="number" min="1" :class="jaUi.input" placeholder="Kosongkan = tanpa batas" />
        </div>
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600" /> Aktif
      </label>

      <div v-for="(q, qi) in form.questions" :key="qi" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/50 p-4">
        <div class="flex items-center justify-between">
          <span class="text-sm font-semibold text-slate-600">Pertanyaan {{ qi + 1 }}</span>
          <button v-if="form.questions.length > 1" type="button" :class="jaUi.btnDanger" @click="removeQuestion(qi)">Hapus</button>
        </div>
        <textarea v-model="q.question" rows="2" :class="jaUi.input" placeholder="Teks pertanyaan" required />
        <select v-model="q.type" :class="jaUi.select">
          <option value="mcq">Pilihan ganda</option>
          <option value="essay">Essay</option>
        </select>
        <template v-if="q.type === 'mcq'">
          <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2">
            <input v-model="opt.option_text" class="flex-1" :class="jaUi.input" placeholder="Opsi jawaban" />
            <label class="flex shrink-0 items-center gap-1 text-sm text-slate-600">
              <input type="radio" :name="'correct-'+qi" :checked="opt.is_correct" @change="setCorrect(qi, oi)" /> Benar
            </label>
            <button v-if="q.options.length > 2" type="button" class="text-rose-500" @click="removeOption(qi, oi)">×</button>
          </div>
          <button type="button" :class="jaUi.btnLink" @click="addOption(qi)">+ Opsi jawaban</button>
        </template>
      </div>

      <button type="button" :class="jaUi.btnLink" @click="addQuestion">+ Pertanyaan</button>
      <button type="submit" :class="jaUi.btnPrimary" :disabled="form.processing">Simpan Quiz</button>
    </form>
  </JaLayout>
</template>
