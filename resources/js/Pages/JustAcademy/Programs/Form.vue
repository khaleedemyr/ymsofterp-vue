<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ program: Object, categories: Array });

const form = useForm({
  category_id: props.program?.category_id || '',
  code: props.program?.code || '',
  title: props.program?.title || '',
  description: props.program?.description || '',
  duration_hours: props.program?.duration_hours || '',
  status: props.program?.status || 'draft',
});

const materialForm = useForm({
  title: '',
  type: 'pdf',
  url: '',
  file: null,
  sort_order: 0,
  is_pre_read: false,
});

const quizForm = useForm({
  title: '',
  type: 'post',
  pass_score: 70,
  time_limit_min: '',
  questions: [{ question: '', type: 'mcq', points: 1, options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }] }],
});

function submit() {
  if (props.program) {
    form.put(route('just-academy.programs.update', props.program.id));
  } else {
    form.post(route('just-academy.programs.store'));
  }
}

function submitMaterial() {
  materialForm.post(route('just-academy.programs.materials.store', props.program.id), {
    forceFormData: true,
    onSuccess: () => materialForm.reset('title', 'url', 'file'),
  });
}

function submitQuiz() {
  quizForm.post(route('just-academy.programs.quizzes.store', props.program.id), {
    onSuccess: () => quizForm.reset(),
  });
}

function removeMaterial(id) {
  if (!confirm('Hapus materi?')) return;
  useForm({}).delete(route('just-academy.programs.materials.destroy', [props.program.id, id]));
}

function addQuestion() {
  quizForm.questions.push({ question: '', type: 'mcq', points: 1, options: [{ option_text: '', is_correct: false }, { option_text: '', is_correct: false }] });
}
</script>

<template>
  <AppLayout :title="program ? 'Edit Program' : 'Program Baru'">
    <div class="max-w-4xl mx-auto py-8 px-2 space-y-8">
      <h1 class="text-2xl font-bold">{{ program ? 'Edit Program' : 'Program Baru' }}</h1>

      <form class="bg-white rounded-2xl shadow p-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium mb-1">Judul</label>
          <input v-model="form.title" class="w-full border rounded-xl px-3 py-2" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Kode</label>
            <input v-model="form.code" class="w-full border rounded-xl px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Kategori</label>
            <select v-model="form.category_id" class="w-full border rounded-xl px-3 py-2">
              <option value="">— Pilih —</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Deskripsi</label>
          <textarea v-model="form.description" rows="3" class="w-full border rounded-xl px-3 py-2"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Durasi (jam)</label>
            <input v-model="form.duration_hours" type="number" step="0.5" class="w-full border rounded-xl px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select v-model="form.status" class="w-full border rounded-xl px-3 py-2">
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
            </select>
          </div>
        </div>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl" :disabled="form.processing">Simpan</button>
      </form>

      <template v-if="program">
        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-semibold mb-4">Materi</h2>
          <ul class="mb-4 space-y-2">
            <li v-for="m in program.materials" :key="m.id" class="flex justify-between border rounded-lg px-3 py-2">
              <span>{{ m.title }} <span class="text-xs text-gray-500">({{ m.type }})</span></span>
              <button type="button" class="text-red-600 text-sm" @click="removeMaterial(m.id)">Hapus</button>
            </li>
          </ul>
          <form class="grid gap-3" @submit.prevent="submitMaterial">
            <input v-model="materialForm.title" placeholder="Judul materi" class="border rounded-xl px-3 py-2" required />
            <select v-model="materialForm.type" class="border rounded-xl px-3 py-2">
              <option value="pdf">PDF</option>
              <option value="video">Video</option>
              <option value="link">Link</option>
              <option value="doc">Dokumen</option>
            </select>
            <input type="file" class="border rounded-xl px-3 py-2" @change="e => materialForm.file = e.target.files[0]" />
            <input v-model="materialForm.url" placeholder="URL (opsional)" class="border rounded-xl px-3 py-2" />
            <label class="flex items-center gap-2 text-sm"><input v-model="materialForm.is_pre_read" type="checkbox" /> Pre-read wajib</label>
            <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded-xl w-fit">Tambah Materi</button>
          </form>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-semibold mb-4">Quiz</h2>
          <ul class="mb-4 space-y-2">
            <li v-for="q in program.quizzes" :key="q.id" class="border rounded-lg px-3 py-2">{{ q.title }} ({{ q.type }})</li>
          </ul>
          <form class="space-y-4" @submit.prevent="submitQuiz">
            <input v-model="quizForm.title" placeholder="Judul quiz" class="w-full border rounded-xl px-3 py-2" required />
            <select v-model="quizForm.type" class="border rounded-xl px-3 py-2">
              <option value="pre">Pre-test</option>
              <option value="post">Post-test</option>
            </select>
            <input v-model="quizForm.pass_score" type="number" placeholder="Pass score %" class="border rounded-xl px-3 py-2" />
            <div v-for="(q, qi) in quizForm.questions" :key="qi" class="border rounded-xl p-4 space-y-2">
              <input v-model="q.question" placeholder="Pertanyaan" class="w-full border rounded-xl px-3 py-2" required />
              <div v-for="(opt, oi) in q.options" :key="oi" class="flex gap-2">
                <input v-model="opt.option_text" class="flex-1 border rounded-xl px-3 py-2" placeholder="Opsi" />
                <label class="text-sm flex items-center gap-1"><input v-model="opt.is_correct" type="checkbox" /> Benar</label>
              </div>
            </div>
            <button type="button" class="text-indigo-600 text-sm" @click="addQuestion">+ Pertanyaan</button>
            <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded-xl block">Tambah Quiz</button>
          </form>
        </div>
      </template>
    </div>
  </AppLayout>
</template>
