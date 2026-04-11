<script setup>
import { ref, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
  image: null,
});

const mode = ref('file');
const videoRef = ref(null);
const stream = ref(null);
const cameraError = ref('');
const cameraStarting = ref(false);

function stopCamera() {
  if (stream.value) {
    stream.value.getTracks().forEach((t) => t.stop());
    stream.value = null;
  }
  cameraError.value = '';
}

async function startCamera() {
  stopCamera();
  cameraStarting.value = true;
  cameraError.value = '';
  try {
    if (!navigator.mediaDevices?.getUserMedia) {
      cameraError.value = 'Browser tidak mendukung akses kamera (HTTPS diperlukan, kecuali localhost).';
      cameraStarting.value = false;
      return;
    }
    const s = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
      audio: false,
    });
    stream.value = s;
    await new Promise((r) => requestAnimationFrame(r));
    if (videoRef.value) {
      videoRef.value.srcObject = s;
      await videoRef.value.play().catch(() => {});
    }
  } catch (e) {
    cameraError.value = e?.name === 'NotAllowedError'
      ? 'Akses kamera ditolak. Izinkan kamera di pengaturan browser.'
      : 'Tidak bisa membuka kamera: ' + (e?.message || 'error');
    stopCamera();
  } finally {
    cameraStarting.value = false;
  }
}

function setMode(m) {
  mode.value = m;
  if (m === 'camera') {
    startCamera();
  } else {
    stopCamera();
  }
}

function captureFromCamera() {
  const video = videoRef.value;
  if (!video || !stream.value || video.readyState < 2) {
    cameraError.value = 'Tunggu kamera siap, lalu coba lagi.';
    return;
  }
  const w = video.videoWidth;
  const h = video.videoHeight;
  if (!w || !h) {
    cameraError.value = 'Video belum siap.';
    return;
  }
  const canvas = document.createElement('canvas');
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  ctx.drawImage(video, 0, 0, w, h);
  canvas.toBlob(
    (blob) => {
      if (!blob) {
        cameraError.value = 'Gagal membuat gambar.';
        return;
      }
      const file = new File([blob], `guest-comment-${Date.now()}.jpg`, { type: 'image/jpeg' });
      form.image = file;
      cameraError.value = '';
    },
    'image/jpeg',
    0.92
  );
}

function onFile(e) {
  const f = e.target.files?.[0];
  form.image = f || null;
}

function submit() {
  stopCamera();
  form.post(route('guest-comment-forms.store'), {
    forceFormData: true,
    preserveScroll: true,
  });
}

onUnmounted(() => {
  stopCamera();
});
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
        Foto formulir guest comment
      </h1>
      <p class="text-gray-600 text-sm mb-4">
        Pilih unggah file atau ambil langsung dari kamera. Setelah itu lanjut verifikasi & OCR.
      </p>

      <div class="flex rounded-xl border border-gray-200 overflow-hidden mb-4">
        <button
          type="button"
          class="flex-1 py-2.5 text-sm font-semibold transition-colors"
          :class="mode === 'file' ? 'bg-blue-600 text-white' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'"
          @click="setMode('file')"
        >
          Unggah file
        </button>
        <button
          type="button"
          class="flex-1 py-2.5 text-sm font-semibold transition-colors"
          :class="mode === 'camera' ? 'bg-blue-600 text-white' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'"
          @click="setMode('camera')"
        >
          Kamera
        </button>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
        <template v-if="mode === 'file'">
          <label class="block text-sm font-semibold text-gray-700 mb-2">File gambar (JPG / PNG / WebP, maks. 8 MB)</label>
          <input
            type="file"
            accept="image/jpeg,image/png,image/jpg,image/webp"
            class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
            @change="onFile"
          />
          <p class="text-xs text-gray-500 mt-2">Di ponsel Anda juga bisa memilih “Kamera” dari opsi unggah.</p>
        </template>

        <template v-else>
          <p class="text-sm font-semibold text-gray-700 mb-2">Pratinjau kamera</p>
          <div class="relative rounded-xl overflow-hidden bg-black aspect-[4/3] max-h-[360px]">
            <video
              ref="videoRef"
              class="w-full h-full object-cover"
              playsinline
              muted
              autoplay
            />
            <div v-if="cameraStarting" class="absolute inset-0 flex items-center justify-center bg-black/60 text-white text-sm">
              Membuka kamera…
            </div>
          </div>
          <p v-if="cameraError" class="mt-2 text-sm text-red-600">{{ cameraError }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-bold hover:bg-emerald-700 disabled:opacity-50"
              :disabled="cameraStarting || !!cameraError"
              @click="captureFromCamera"
            >
              Ambil foto
            </button>
            <button
              type="button"
              class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
              @click="startCamera"
            >
              Muat ulang kamera
            </button>
          </div>
          <p v-if="form.image && mode === 'camera'" class="mt-3 text-sm text-green-700 font-medium">
            Foto siap diunggah ({{ form.image.name }})
          </p>
        </template>

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
