<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

const form = ref({
  title: '',
  image: null,
  link_url: '',
  order: 0,
  is_active: true,
});

const errors = ref({});
const isSubmitting = ref(false);
const preview = ref(null);

function onFile(e) {
  const file = e.target.files[0];
  if (!file) return;
  form.value.image = file;
  const reader = new FileReader();
  reader.onload = (ev) => {
    preview.value = ev.target.result;
  };
  reader.readAsDataURL(file);
}

function submit() {
  if (!form.value.image) {
    Swal.fire({ icon: 'warning', title: 'Gambar wajib diisi' });
    return;
  }
  isSubmitting.value = true;
  const fd = new FormData();
  fd.append('title', form.value.title || '');
  fd.append('link_url', form.value.link_url || '');
  fd.append('order', form.value.order);
  fd.append('is_active', form.value.is_active ? 1 : 0);
  fd.append('image', form.value.image);

  router.post('/web-profile/promo-slides', fd, {
    forceFormData: true,
    onSuccess: () => {
      Swal.fire('Berhasil', 'Slide promo ditambahkan.', 'success');
    },
    onError: (e) => {
      errors.value = e || {};
      isSubmitting.value = false;
      Swal.fire({ icon: 'error', title: 'Validasi gagal', text: Object.values(e || {}).flat().join('\n') });
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}
</script>

<template>
  <AppLayout title="Tambah Promo Slide">
    <div class="max-w-3xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Tambah Promo Slide</h1>

      <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        <p class="font-semibold text-amber-900">Rekomendasi file gambar</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
          <li><strong>Ideal:</strong> 1920 × 600 px (banner landscape); alternatif 1600 × 500 px.</li>
          <li>Satu file = satu slide; full width di web — hindari gambar sangat tinggi (portrait).</li>
          <li>Agar rapi di HP: jangan taruh tegas penting terlalu pinggir; fokus di area tengah.</li>
          <li>Sistem menolak rasio ekstrem di luar rentang yang disarankan (pesan error menjelaskan).</li>
        </ul>
      </div>

      <div class="bg-white rounded-lg shadow p-6 space-y-6">
        <div>
          <InputLabel for="title" value="Judul (opsional)" />
          <TextInput id="title" v-model="form.title" type="text" class="mt-1 block w-full" />
          <InputError :message="errors.title" class="mt-2" />
        </div>

        <div>
          <InputLabel for="link_url" value="Link URL (opsional)" />
          <TextInput id="link_url" v-model="form.link_url" type="text" class="mt-1 block w-full" placeholder="https://..." />
          <InputError :message="errors.link_url" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <InputLabel for="order" value="Urutan" />
            <TextInput id="order" v-model.number="form.order" type="number" min="0" class="mt-1 block w-full" />
            <InputError :message="errors.order" class="mt-2" />
          </div>
          <div class="flex items-end pb-2">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
              <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300">
              Aktif
            </label>
          </div>
        </div>

        <div>
          <InputLabel for="image" value="Gambar *" />
          <input
            id="image"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="mt-1 block w-full text-sm"
            @change="onFile"
          >
          <InputError :message="errors.image" class="mt-2" />
          <img v-if="preview" :src="preview" alt="" class="mt-3 max-h-48 rounded border border-gray-200">
        </div>

        <div class="flex gap-3">
          <PrimaryButton type="button" :disabled="isSubmitting" @click="submit">
            Simpan
          </PrimaryButton>
          <button
            type="button"
            class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg"
            @click="router.visit('/web-profile/promo-slides')"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
