<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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
  if (props.material) {
    form.transform(data => ({ ...data, _method: 'put' })).post(route('just-academy.materials.update', props.material.id), {
      forceFormData: true,
    });
  } else {
    form.post(route('just-academy.materials.store'), { forceFormData: true });
  }
}
</script>

<template>
  <AppLayout :title="material ? 'Edit Materi' : 'Materi Baru'">
    <div class="max-w-2xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6">{{ material ? 'Edit Materi' : 'Materi Baru' }}</h1>
      <form class="bg-white rounded-2xl shadow p-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium mb-1">Judul</label>
          <input v-model="form.title" class="w-full border rounded-xl px-3 py-2" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tipe</label>
          <select v-model="form.type" class="w-full border rounded-xl px-3 py-2">
            <option value="pdf">PDF</option>
            <option value="video">Video</option>
            <option value="link">Link</option>
            <option value="doc">Dokumen</option>
            <option value="other">Lainnya</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">File</label>
          <input type="file" class="w-full border rounded-xl px-3 py-2" @change="e => form.file = e.target.files[0]" />
          <p v-if="material?.file_path" class="text-xs text-gray-500 mt-1">File saat ini: {{ material.file_path }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">URL (opsional)</label>
          <input v-model="form.url" class="w-full border rounded-xl px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Deskripsi</label>
          <textarea v-model="form.description" rows="3" class="w-full border rounded-xl px-3 py-2"></textarea>
        </div>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Aktif</label>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl" :disabled="form.processing">Simpan</button>
      </form>
    </div>
  </AppLayout>
</template>
