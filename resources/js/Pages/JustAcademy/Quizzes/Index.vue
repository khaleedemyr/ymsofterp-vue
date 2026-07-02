<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import axios from 'axios';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaConfirmDelete, jaDelete } from '@/composables/useJustAcademyUi';

const props = defineProps({ quizzes: Object, filters: Object });
const search = ref(props.filters?.search || '');
const uploadInput = ref(null);
const uploading = ref(false);

const debounced = debounce(() => {
  router.get(route('just-academy.quizzes.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
}, 400);

watch(search, debounced);

async function remove(q) {
  const result = await jaConfirmDelete({
    title: 'Hapus quiz?',
    text: `"${q.title}" akan dihapus dari pustaka.`,
  });
  if (!result.isConfirmed) return;
  jaDelete(route('just-academy.quizzes.destroy', q.id));
}

async function downloadTemplate() {
  try {
    const response = await axios.get(route('just-academy.quizzes.template.download'), {
      responseType: 'blob',
    });
    const contentDisposition = response.headers['content-disposition'] || '';
    const matched = contentDisposition.match(/filename="?([^"]+)"?/);
    const fileName = matched?.[1] || 'quizzes_template.xlsx';

    const blob = new Blob([response.data], {
      type: response.headers['content-type'] || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', fileName);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    // silent fallback via backend flash not available for download errors
    console.error('Failed to download quiz template', error);
  }
}

function triggerUpload() {
  if (!uploadInput.value) return;
  uploadInput.value.value = '';
  uploadInput.value.click();
}

function importExcel(event) {
  const file = event?.target?.files?.[0];
  if (!file) return;
  uploading.value = true;
  const formData = new FormData();
  formData.append('file', file);
  router.post(route('just-academy.quizzes.template.import'), formData, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => {
      uploading.value = false;
    },
  });
}
</script>

<template>
  <JaLayout title="Pustaka Quiz" subtitle="Buat pre-test, post-test, dan evaluasi" icon="fa-solid fa-circle-question">
    <template #actions>
      <div class="flex flex-wrap items-center gap-2">
        <button type="button" :class="jaUi.btnSecondary" @click="downloadTemplate">
          <i class="fa-solid fa-download text-xs" /> Download Template
        </button>
        <button type="button" :class="jaUi.btnSecondary" :disabled="uploading" @click="triggerUpload">
          <i class="fa-solid fa-file-arrow-up text-xs" />
          {{ uploading ? 'Uploading...' : 'Upload from Excel' }}
        </button>
        <input
          ref="uploadInput"
          type="file"
          accept=".xlsx,.xls"
          class="hidden"
          @change="importExcel"
        />
        <Link :href="route('just-academy.quizzes.create')" :class="jaUi.btnPrimary">
          <i class="fa-solid fa-plus text-xs" /> Quiz Baru
        </Link>
      </div>
    </template>

    <div v-if="$page.props.errors?.file" class="mb-4 whitespace-pre-line rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {{ $page.props.errors.file }}
    </div>

    <input v-model="search" type="text" placeholder="Cari quiz..." :class="[jaUi.search, 'mb-5']" @input="debounced" />

    <div :class="jaUi.tableWrap">
      <table :class="jaUi.table">
        <thead :class="jaUi.thead">
          <tr>
            <th :class="jaUi.th">Judul</th>
            <th :class="jaUi.th">Pass Score</th>
            <th :class="jaUi.th">Waktu</th>
            <th :class="jaUi.th">Bank / Tes</th>
            <th :class="jaUi.th"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in quizzes.data" :key="q.id" :class="jaUi.tr">
            <td :class="[jaUi.td, 'font-semibold text-slate-800']">{{ q.title }}</td>
            <td :class="jaUi.td">{{ q.pass_score }}%</td>
            <td :class="jaUi.td">
              <span v-if="q.time_limit_mode === 'quiz' || (!q.time_limit_mode && q.time_limit_min)">{{ q.time_limit_min }} mnt</span>
              <span v-else-if="q.time_limit_mode === 'question'">{{ q.time_limit_question_sec }} dtk/soal</span>
              <span v-else class="text-slate-400">—</span>
            </td>
            <td :class="jaUi.td">
              <span>{{ q.questions_count }} soal</span>
              <span v-if="q.questions_per_attempt" class="text-slate-500">
                · tampil {{ q.questions_per_attempt }}/tes
              </span>
              <span v-if="q.randomize_questions" class="ml-1 inline-flex rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700">Acak soal</span>
              <span v-if="q.randomize_options" class="ml-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Acak opsi</span>
            </td>
            <td :class="[jaUi.td, 'text-right space-x-4']">
              <Link :href="route('just-academy.quizzes.edit', q.id)" :class="jaUi.btnLink">Edit</Link>
              <button type="button" :class="jaUi.btnDanger" @click="remove(q)">Hapus</button>
            </td>
          </tr>
          <tr v-if="!quizzes.data?.length">
            <td colspan="5" :class="jaUi.empty">Belum ada quiz.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </JaLayout>
</template>
