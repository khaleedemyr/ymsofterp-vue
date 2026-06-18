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
  time_limit_mode: props.quiz?.time_limit_mode || (props.quiz?.time_limit_min ? 'quiz' : 'none'),
  time_limit_min: props.quiz?.time_limit_min || '',
  time_limit_question_sec: props.quiz?.time_limit_question_sec || '',
  questions_per_attempt: props.quiz?.questions_per_attempt || '',
  randomize_questions: props.quiz?.randomize_questions ?? false,
  randomize_options: props.quiz?.randomize_options ?? false,
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
      </div>

      <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-4 space-y-4">
        <p class="text-sm font-semibold text-amber-900">Batas waktu</p>
        <div>
          <label :class="jaUi.label">Mode waktu</label>
          <select v-model="form.time_limit_mode" :class="jaUi.select">
            <option value="none">Tanpa batas waktu</option>
            <option value="quiz">Total quiz (menit) — untuk pre/post test</option>
            <option value="question">Per soal (detik) — satu soal tampil per layar</option>
          </select>
        </div>
        <div v-if="form.time_limit_mode === 'quiz'">
          <label :class="jaUi.label">Durasi total (menit)</label>
          <input v-model="form.time_limit_min" type="number" min="1" max="600" :class="jaUi.input" placeholder="Contoh: 30" required />
          <p class="mt-1.5 text-xs text-slate-500">Semua soal tampil sekaligus. Timer di atas, auto-kirim saat habis.</p>
        </div>
        <div v-if="form.time_limit_mode === 'question'">
          <label :class="jaUi.label">Durasi per soal (detik)</label>
          <input v-model="form.time_limit_question_sec" type="number" min="5" max="3600" :class="jaUi.input" placeholder="Contoh: 60" required />
          <p class="mt-1.5 text-xs text-slate-500">Satu soal per layar. Waktu habis → lanjut soal berikutnya otomatis.</p>
        </div>
      </div>

      <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-4">
        <p class="text-sm font-semibold text-indigo-900">Bank soal & tampilan tes</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label :class="jaUi.label">Soal per tes</label>
            <input
              v-model="form.questions_per_attempt"
              type="number"
              min="1"
              :max="form.questions.length"
              :class="jaUi.input"
              placeholder="Semua pertanyaan"
            />
            <p class="mt-1.5 text-xs text-slate-500">
              Bank: {{ form.questions.length }} soal. Kosongkan = tampilkan semua.
              Contoh: bank 20, isi 10 → peserta hanya mengerjakan 10 soal.
            </p>
          </div>
          <div class="flex flex-col justify-center gap-4">
            <label class="flex items-start gap-2 text-sm text-slate-700">
              <input v-model="form.randomize_questions" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600" />
              <span>
                <span class="font-medium">Acak soal & nomor urut</span>
                <span class="mt-1 block text-xs text-slate-500">
                  Jika aktif, soal yang muncul dipilih acak dari bank dan urutannya diacak setiap peserta.
                  Jika nonaktif, diambil berurutan dari daftar pertanyaan di bawah.
                </span>
              </span>
            </label>
            <label class="flex items-start gap-2 text-sm text-slate-700">
              <input v-model="form.randomize_options" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600" />
              <span>
                <span class="font-medium">Acak urutan opsi jawaban</span>
                <span class="mt-1 block text-xs text-slate-500">
                  Urutan pilihan A/B/C/D diacak per peserta. Urutan tetap sama jika halaman di-refresh selama tes berlangsung.
                </span>
              </span>
            </label>
          </div>
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
