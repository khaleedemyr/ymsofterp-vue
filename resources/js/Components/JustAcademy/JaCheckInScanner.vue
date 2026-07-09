<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { jaUi, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({
  scheduleId: { type: Number, required: true },
});

const manualPayload = ref('');
const statusMessage = ref('');
const isError = ref(false);
const cameras = ref([]);
const selectedCameraId = ref('');
let html5QrCode = null;
let processing = false;

const form = useForm({ qr_payload: '' });

onMounted(async () => {
  await loadScannerLibrary();
  await setupCameras();
});

onBeforeUnmount(() => {
  stopScanner();
});

async function loadScannerLibrary() {
  if (window.Html5Qrcode) return;
  await new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    script.onload = resolve;
    script.onerror = reject;
    document.body.appendChild(script);
  });
}

async function setupCameras() {
  if (!window.Html5Qrcode) return;
  try {
    cameras.value = await window.Html5Qrcode.getCameras();
    selectedCameraId.value = cameras.value[0]?.id || '';
    if (selectedCameraId.value) {
      await startScanner();
    } else {
      statusMessage.value = 'Kamera tidak ditemukan. Gunakan input manual di bawah.';
      isError.value = true;
    }
  } catch (e) {
    statusMessage.value = 'Tidak dapat mengakses kamera. Paste link/token QR secara manual.';
    isError.value = true;
  }
}

async function startScanner() {
  if (!window.Html5Qrcode || !selectedCameraId.value) return;
  await stopScanner();

  html5QrCode = new window.Html5Qrcode('ja-checkin-qr-reader');
  try {
    await html5QrCode.start(
      selectedCameraId.value,
      { fps: 10, qrbox: { width: 240, height: 240 } },
      (decodedText) => submitPayload(decodedText),
      () => {},
    );
  } catch (e) {
    statusMessage.value = 'Gagal memulai scanner kamera.';
    isError.value = true;
  }
}

async function stopScanner() {
  if (!html5QrCode) return;
  try {
    await html5QrCode.stop();
    html5QrCode.clear();
  } catch (e) {
    // ignore cleanup errors
  }
  html5QrCode = null;
  const el = document.getElementById('ja-checkin-qr-reader');
  if (el) el.innerHTML = '';
}

function submitManual() {
  if (!manualPayload.value.trim()) return;
  submitPayload(manualPayload.value.trim());
}

function submitPayload(payload) {
  if (processing || form.processing) return;
  processing = true;
  statusMessage.value = 'Memproses check-in...';
  isError.value = false;

  form.qr_payload = payload;
  form.post(route('just-academy.my-training.check-in', props.scheduleId), {
    onError: (errors) => {
      processing = false;
      isError.value = true;
      statusMessage.value = errors.qr_payload || errors.qr_token || errors.check_in || 'Check-in gagal.';
      jaFormErrors(errors);
    },
    onFinish: () => {
      processing = false;
    },
  });
}
</script>

<template>
  <div :class="[jaUi.card, jaUi.cardBody]">
    <div class="mb-4 text-center">
      <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
        <i class="fa-solid fa-qrcode text-2xl" />
      </div>
      <h2 class="text-lg font-semibold text-slate-800">Scan QR Check-in</h2>
      <p class="mt-1 text-sm text-slate-600">
        Scan QR code dari halaman trainer untuk check-in. Setelah berhasil, daftar materi dan quiz akan tampil.
      </p>
    </div>

    <div id="ja-checkin-qr-reader" class="mx-auto w-full max-w-sm overflow-hidden rounded-xl bg-slate-100" />

    <div v-if="cameras.length > 1" class="mt-3">
      <label class="text-xs text-slate-500">Kamera</label>
      <select v-model="selectedCameraId" class="mt-1 w-full text-sm" :class="jaUi.input" @change="startScanner">
        <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label || cam.id }}</option>
      </select>
    </div>

    <div class="mt-4">
      <label class="text-sm text-slate-600">Atau paste link / token QR manual</label>
      <div class="mt-2 flex gap-2">
        <input
          v-model="manualPayload"
          type="text"
          :class="jaUi.input"
          class="flex-1"
          placeholder="https://.../check-in?token=...&schedule_id=..."
          @keyup.enter="submitManual"
        />
        <button type="button" :class="jaUi.btnPrimary" :disabled="form.processing" @click="submitManual">
          Check-in
        </button>
      </div>
    </div>

    <div
      v-if="statusMessage"
      class="mt-4 rounded-lg px-3 py-2 text-sm"
      :class="isError ? 'bg-rose-50 text-rose-800 border border-rose-200' : 'bg-blue-50 text-blue-800 border border-blue-200'"
    >
      {{ statusMessage }}
    </div>
  </div>
</template>

<style scoped>
#ja-checkin-qr-reader :deep(video) {
  border-radius: 0.75rem;
}
</style>
