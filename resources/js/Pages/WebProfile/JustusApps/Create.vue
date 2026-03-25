<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const form = ref({
  title: '',
  body: '',
  sort_order: 0,
  image: null,
  is_active: true,
});

const errors = ref({});
const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  errors.value = {};

  const fd = new FormData();
  fd.append('title', form.value.title || '');
  fd.append('body', form.value.body || '');
  fd.append('sort_order', String(form.value.sort_order || 0));
  fd.append('is_active', form.value.is_active ? '1' : '0');
  if (form.value.image) fd.append('image', form.value.image);

  router.post('/web-profile/justus-apps', fd, {
    forceFormData: true,
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
  <AppLayout title="Tambah Blok Justus Apps">
    <div class="max-w-3xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Tambah Blok Justus Apps</h1>
      <form class="bg-white rounded-lg shadow p-6 space-y-4" @submit.prevent="submit">
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
          <InputLabel value="Gambar blok *" />
          <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm" @change="(e) => form.image = e.target.files?.[0] || null" />
          <p class="mt-1 max-w-3xl text-xs text-gray-500">
            Justus Nest — blok gambar: di desktop kolom ±setengah lebar layar, tinggi baris 340px (md) / 380px (lg); di mobile tinggi 260px, lebar penuh. Ditampilkan memenuhi kotak (crop dari tengah).
            Disarankan landscape min. 1600×900 atau 1920×1080; hindari portrait; fokus di tengah. JPG/PNG/WEBP, maks. 5 MB (batas upload).
          </p>
          <InputError class="mt-1" :message="errors.image" />
        </div>
        <div class="flex items-center gap-2">
          <input id="active" v-model="form.is_active" type="checkbox" class="rounded" />
          <label for="active">Aktif</label>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-4 py-2 border rounded" @click="router.visit('/web-profile/justus-apps')">Batal</button>
          <PrimaryButton type="submit" :disabled="isSubmitting">Simpan</PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

