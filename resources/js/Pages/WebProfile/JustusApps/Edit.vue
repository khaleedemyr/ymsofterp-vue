<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  block: { type: Object, required: true },
});

const form = ref({
  title: props.block.title || '',
  body: props.block.body || '',
  sort_order: props.block.sort_order || 0,
  image: null,
  is_active: !!props.block.is_active,
});

const errors = ref({});
const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  errors.value = {};

  const fd = new FormData();
  fd.append('_method', 'PUT');
  fd.append('title', form.value.title || '');
  fd.append('body', form.value.body || '');
  fd.append('sort_order', String(form.value.sort_order || 0));
  fd.append('is_active', form.value.is_active ? '1' : '0');
  if (form.value.image) fd.append('image', form.value.image);

  router.post(`/web-profile/justus-apps/${props.block.id}`, fd, {
    forceFormData: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = e || {};
      isSubmitting.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}
</script>

<template>
  <AppLayout title="Edit Blok Justus Apps">
    <div class="max-w-3xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Edit Blok Justus Apps</h1>
      <form class="bg-white rounded-lg shadow p-6 space-y-4" @submit.prevent="submit">
        <div v-if="block.image_url" class="text-sm text-gray-600">
          <p class="mb-2">Gambar saat ini:</p>
          <img :src="block.image_url" alt="" class="max-h-48 w-full rounded border object-cover" />
        </div>
        <div>
          <InputLabel value="Urutan" />
          <TextInput v-model="form.sort_order" type="number" min="0" class="mt-1 w-full" />
        </div>
        <div>
          <InputLabel value="Judul" />
          <TextInput v-model="form.title" class="mt-1 w-full" />
          <InputError class="mt-1" :message="errors.title" />
        </div>
        <div>
          <InputLabel value="Isi konten" />
          <textarea v-model="form.body" rows="5" class="mt-1 w-full rounded-md border-gray-300"></textarea>
          <InputError class="mt-1" :message="errors.body" />
        </div>
        <div>
          <InputLabel value="Ganti gambar (opsional)" />
          <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm" @change="(e) => form.image = e.target.files?.[0] || null" />
          <p class="mt-1 text-xs text-gray-500">
            Rekomendasi ukuran gambar blok: <strong>1200x900</strong> (rasio 4:3), minimal 1000x750.
          </p>
          <InputError class="mt-1" :message="errors.image" />
        </div>
        <div class="flex items-center gap-2">
          <input id="active" v-model="form.is_active" type="checkbox" class="rounded" />
          <label for="active">Aktif</label>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-4 py-2 border rounded" @click="router.visit('/web-profile/justus-apps')">Kembali</button>
          <PrimaryButton type="submit" :disabled="isSubmitting">Simpan</PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

