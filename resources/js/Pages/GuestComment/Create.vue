<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
  image: null,
});

function onFile(e) {
  const f = e.target.files?.[0];
  form.image = f || null;
}

function submit() {
  form.post(route('guest-comment-forms.store'), {
    forceFormData: true,
    preserveScroll: true,
  });
}
</script>

<template>
  <AppLayout>
    <div class="w-full py-8 px-4 max-w-xl mx-auto">
      <div class="mb-6">
        <Link :href="route('guest-comment-forms.index')" class="text-blue-600 hover:underline text-sm font-semibold">
          ← Kembali ke daftar
        </Link>
      </div>
      <h1 class="text-2xl font-bold text-gray-800 mb-2 flex items-center gap-2">
        <i class="fa-solid fa-camera text-blue-500"></i>
        Unggah foto formulir guest comment
      </h1>
      <p class="text-gray-600 text-sm mb-6">
        Unggah foto kertas formulir. OCR (saat ini stub) akan mengisi draf; staff memverifikasi di langkah berikutnya.
      </p>

      <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
        <label class="block text-sm font-semibold text-gray-700 mb-2">File gambar (JPG / PNG / WebP, maks. 8 MB)</label>
        <input
          type="file"
          accept="image/jpeg,image/png,image/jpg,image/webp"
          class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
          @change="onFile"
        />
        <p v-if="form.errors.image" class="mt-2 text-sm text-red-600">{{ form.errors.image }}</p>

        <button
          type="button"
          class="mt-6 w-full py-3 rounded-xl bg-gradient-to-r from-blue-500 to-blue-700 text-white font-bold shadow-lg disabled:opacity-50"
          :disabled="form.processing || !form.image"
          @click="submit"
        >
          {{ form.processing ? 'Mengunggah…' : 'Unggah & lanjut verifikasi' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>
