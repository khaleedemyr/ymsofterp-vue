<script setup>
import { useForm } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({ material: Object });

const form = useForm({
  title: props.material?.title || '',
  type: props.material?.type || 'pdf',
  url: props.material?.url || '',
  description: props.material?.description || '',
  file: null,
  is_active: props.material?.is_active ?? true,
});

function submit() {
  const opts = { forceFormData: true, onError: (e) => jaFormErrors(e) };
  if (props.material) {
    form.transform(data => ({ ...data, _method: 'put' })).post(route('just-academy.materials.update', props.material.id), opts);
  } else {
    form.post(route('just-academy.materials.store'), opts);
  }
}
</script>

<template>
  <JaLayout
    :title="material ? 'Edit Materi' : 'Materi Baru'"
    subtitle="Upload file atau tautan untuk pustaka training"
    icon="fa-solid fa-file-lines"
    narrow
  >
    <form :class="[jaUi.card, jaUi.cardBody, 'space-y-4']" @submit.prevent="submit">
      <div>
        <label :class="jaUi.label">Judul</label>
        <input v-model="form.title" :class="jaUi.input" required />
      </div>
      <div>
        <label :class="jaUi.label">Tipe</label>
        <select v-model="form.type" :class="jaUi.input">
          <option value="pdf">PDF</option>
          <option value="video">Video</option>
          <option value="link">Link</option>
          <option value="doc">Dokumen</option>
          <option value="other">Lainnya</option>
        </select>
      </div>
      <div>
        <label :class="jaUi.label">File</label>
        <input type="file" :class="jaUi.input" @change="e => form.file = e.target.files[0]" />
        <p v-if="material?.file_path" class="mt-1 text-xs text-slate-500">File saat ini: {{ material.file_path }}</p>
      </div>
      <div>
        <label :class="jaUi.label">URL (opsional)</label>
        <input v-model="form.url" :class="jaUi.input" />
      </div>
      <div>
        <label :class="jaUi.label">Deskripsi</label>
        <textarea v-model="form.description" rows="3" :class="jaUi.input" />
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600" /> Aktif
      </label>
      <button type="submit" :class="jaUi.btnPrimary" :disabled="form.processing">Simpan</button>
    </form>
  </JaLayout>
</template>
