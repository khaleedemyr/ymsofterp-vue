<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Swal from 'sweetalert2';

/** Sama dengan Laravel: max:102400 → kilobyte → 100 MB */
const MAX_VIDEO_KB = 102400;
const MAX_VIDEO_BYTES = MAX_VIDEO_KB * 1024;
const ALLOWED_VIDEO_EXT = ['mp4', 'webm'];

const form = ref({
  block_type: 'text',
  sort_order: 0,
  title: '',
  body: '',
  caption: '',
  bg_variant: 'dark',
  video: null,
  is_active: true,
});

const errors = ref({});
const isSubmitting = ref(false);

function showSavingSpinner() {
  Swal.fire({
    title: 'Menyimpan...',
    text: 'Mohon tunggu sebentar.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });
}

function formatFileSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function validateVideoFile(file) {
  const ext = file.name.split('.').pop()?.toLowerCase() || '';
  if (!ALLOWED_VIDEO_EXT.includes(ext)) {
    return {
      ok: false,
      message: 'Format video harus <strong>MP4</strong> atau <strong>WEBM</strong>.',
    };
  }
  if (file.size > MAX_VIDEO_BYTES) {
    return {
      ok: false,
      message: `Ukuran maksimal <strong>100 MB</strong> (sesuai batas server).<br>File Anda: <strong>${formatFileSize(file.size)}</strong>`,
    };
  }
  return { ok: true };
}

function onVideoChange(event) {
  const input = event.target;
  const file = input.files?.[0] || null;
  errors.value = { ...errors.value, video: undefined };

  if (!file) {
    form.value.video = null;
    return;
  }

  const result = validateVideoFile(file);
  if (!result.ok) {
    Swal.fire({
      icon: 'error',
      title: 'File tidak valid',
      html: result.message,
      confirmButtonText: 'OK',
    });
    input.value = '';
    form.value.video = null;
    return;
  }

  form.value.video = file;
}

function submit() {
  if (form.value.block_type === 'video') {
    if (!form.value.video) {
      Swal.fire({
        icon: 'warning',
        title: 'Video wajib',
        text: 'Pilih file video (MP4/WEBM, maks. 100 MB).',
      });
      return;
    }
    const check = validateVideoFile(form.value.video);
    if (!check.ok) {
      Swal.fire({ icon: 'error', title: 'File tidak valid', html: check.message });
      return;
    }
  }

  isSubmitting.value = true;
  errors.value = {};

  const fd = new FormData();
  fd.append('block_type', form.value.block_type);
  fd.append('sort_order', String(form.value.sort_order));
  fd.append('title', form.value.title || '');
  fd.append('body', form.value.body || '');
  fd.append('caption', form.value.caption || '');
  fd.append('bg_variant', form.value.bg_variant);
  fd.append('is_active', form.value.is_active ? '1' : '0');
  if (form.value.video) {
    fd.append('video', form.value.video);
  }

  showSavingSpinner();

  router.post('/web-profile/home-blocks', fd, {
    forceFormData: true,
    onError: (e) => {
      Swal.close();
      errors.value = e || {};
      isSubmitting.value = false;
      const msgs = Object.values(e || {}).flat().filter(Boolean);
      queueMicrotask(() => {
        Swal.fire({
          icon: 'error',
          title: 'Gagal menyimpan',
          html: msgs.length ? msgs.join('<br>') : 'Validasi gagal atau file terlalu besar.',
        });
      });
    },
    onFinish: () => {
      Swal.close();
      isSubmitting.value = false;
    },
  });
}
</script>

<template>
  <AppLayout title="Tambah Home Block">
    <div class="max-w-3xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Tambah Blok (Text / Video)</h1>
      <form class="bg-white rounded-lg shadow p-6 space-y-4" @submit.prevent="submit">
        <div>
          <InputLabel value="Tipe blok *" />
          <select v-model="form.block_type" class="mt-1 block w-full rounded-md border-gray-300">
            <option value="text">Text</option>
            <option value="video">Video + caption</option>
          </select>
          <InputError :message="errors.block_type" class="mt-1" />
        </div>
        <div>
          <InputLabel value="Urutan (sort_order)" />
          <TextInput v-model.number="form.sort_order" type="number" min="0" class="mt-1 w-full" />
        </div>
        <div>
          <InputLabel value="Judul (opsional)" />
          <TextInput v-model="form.title" class="mt-1 w-full" />
        </div>
        <div v-if="form.block_type === 'text'">
          <InputLabel value="Isi teks" />
          <textarea v-model="form.body" rows="5" class="mt-1 w-full rounded-md border-gray-300"></textarea>
        </div>
        <div v-if="form.block_type === 'video'">
          <InputLabel value="Video (mp4/webm) *" />
          <input
            type="file"
            accept="video/mp4,video/webm,.mp4,.webm"
            class="mt-1 block w-full text-sm"
            @change="onVideoChange"
          />
          <InputError :message="errors.video" class="mt-1" />
          <p class="text-xs text-gray-500 mt-1">
            Format: MP4 / WEBM. Maksimal <strong>100 MB</strong> (lebih besar akan ditolak sebelum upload).
          </p>
        </div>
        <div v-if="form.block_type === 'video'">
          <InputLabel value="Caption (teks besar di tengah)" />
          <textarea v-model="form.caption" rows="3" class="mt-1 w-full rounded-md border-gray-300" placeholder="Contoh: VIDEO PRODUCT MAKING PROCESS"></textarea>
        </div>
        <div>
          <InputLabel value="Varian background" />
          <select v-model="form.bg_variant" class="mt-1 block w-full rounded-md border-gray-300">
            <option value="dark">Dark (abu gelap)</option>
            <option value="light">Light (abu terang)</option>
            <option value="video_dark">Video dark (hitam)</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <input id="active" v-model="form.is_active" type="checkbox" class="rounded" />
          <label for="active">Aktif</label>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-4 py-2 border rounded" @click="router.visit('/web-profile/home-blocks')">Batal</button>
          <PrimaryButton type="submit" :disabled="isSubmitting">Simpan</PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
