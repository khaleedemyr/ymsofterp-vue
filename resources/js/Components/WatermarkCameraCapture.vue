<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between gap-2">
      <div>
        <p class="text-sm font-semibold text-gray-800">{{ title }}</p>
        <p class="text-xs text-gray-500">{{ subtitle }}</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700"
        @click="openCamera"
      >
        <i class="fa-solid fa-camera"></i>
        Capture
      </button>
    </div>

    <div v-if="!items.length" class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
      Belum ada foto. Gunakan tombol Capture (upload file tidak diizinkan).
    </div>

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
      <div v-for="(item, idx) in items" :key="item.key" class="relative group">
        <img :src="item.previewUrl" :alt="`${title} ${idx + 1}`" class="w-full h-28 object-cover rounded-lg border" />
        <button
          type="button"
          class="absolute top-1 right-1 bg-red-600 text-white w-7 h-7 rounded-full text-xs shadow"
          title="Hapus"
          @click="removeAt(idx)"
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="mt-1 text-[10px] text-gray-500 truncate">{{ item.metaLabel }}</div>
      </div>
    </div>

    <teleport to="body">
      <div v-if="cameraOpen" class="fixed inset-0 z-[80] bg-black/80 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
          <div class="px-4 py-3 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Capture {{ title }}</h3>
            <button type="button" class="text-gray-500 hover:text-gray-800" @click="closeCamera">
              <i class="fa-solid fa-times"></i>
            </button>
          </div>
          <div class="p-4 space-y-3">
            <div class="relative bg-black rounded-xl overflow-hidden aspect-[3/4]">
              <video ref="videoEl" autoplay playsinline muted class="w-full h-full object-cover"></video>
              <div class="absolute left-2 right-2 bottom-2 text-[11px] text-white bg-black/55 rounded px-2 py-1.5 leading-tight">
                <div>{{ liveDateTime }}</div>
                <div>{{ liveGeoLabel }}</div>
              </div>
            </div>
            <p v-if="geoError" class="text-xs text-amber-600">{{ geoError }}</p>
            <div class="flex gap-2">
              <button
                type="button"
                class="flex-1 px-4 py-2.5 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 disabled:opacity-50"
                :disabled="!streamReady"
                @click="capture"
              >
                <i class="fa-solid fa-camera mr-1"></i> Ambil Foto
              </button>
              <button type="button" class="px-4 py-2.5 rounded-lg border text-gray-700" @click="closeCamera">
                Selesai
              </button>
            </div>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  title: { type: String, default: 'Foto' },
  subtitle: { type: String, default: 'Hanya lewat kamera, dengan watermark waktu & lokasi' },
  modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const items = computed({
  get: () => props.modelValue || [],
  set: (val) => emit('update:modelValue', val),
});

const cameraOpen = ref(false);
const streamReady = ref(false);
const videoEl = ref(null);
const liveDateTime = ref('');
const liveGeo = ref(null);
const geoError = ref('');
let mediaStream = null;
let clockTimer = null;
let geoWatchId = null;

function formatDateTime(date = new Date()) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

const liveGeoLabel = computed(() => {
  if (!liveGeo.value) return geoError.value ? 'Lokasi: tidak tersedia' : 'Lokasi: mencari...';
  const { lat, lng, accuracy } = liveGeo.value;
  const acc = accuracy != null ? ` ±${Math.round(accuracy)}m` : '';
  return `Lat ${lat.toFixed(6)}, Lng ${lng.toFixed(6)}${acc}`;
});

function startClock() {
  const tick = () => {
    liveDateTime.value = formatDateTime();
  };
  tick();
  clockTimer = setInterval(tick, 1000);
}

function stopClock() {
  if (clockTimer) {
    clearInterval(clockTimer);
    clockTimer = null;
  }
}

function startGeo() {
  geoError.value = '';
  liveGeo.value = null;
  if (!navigator.geolocation) {
    geoError.value = 'Geolocation tidak didukung di perangkat ini.';
    return;
  }
  geoWatchId = navigator.geolocation.watchPosition(
    (pos) => {
      liveGeo.value = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy: pos.coords.accuracy,
      };
      geoError.value = '';
    },
    (err) => {
      geoError.value = err?.message || 'Gagal membaca lokasi. Izinkan akses GPS.';
    },
    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
  );
}

function stopGeo() {
  if (geoWatchId != null && navigator.geolocation) {
    navigator.geolocation.clearWatch(geoWatchId);
    geoWatchId = null;
  }
}

async function openCamera() {
  cameraOpen.value = true;
  streamReady.value = false;
  startClock();
  startGeo();

  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
    });
    await new Promise((r) => setTimeout(r, 50));
    if (videoEl.value) {
      videoEl.value.srcObject = mediaStream;
      await videoEl.value.play();
      streamReady.value = true;
    }
  } catch (error) {
    console.error(error);
    closeCamera();
    Swal.fire('Kamera', 'Tidak dapat membuka kamera belakang. Pastikan izin kamera aktif.', 'error');
  }
}

function closeCamera() {
  cameraOpen.value = false;
  streamReady.value = false;
  stopClock();
  stopGeo();
  if (mediaStream) {
    mediaStream.getTracks().forEach((t) => t.stop());
    mediaStream = null;
  }
  if (videoEl.value) {
    videoEl.value.srcObject = null;
  }
}

function drawWatermark(ctx, width, height, dateLabel, geoLabel) {
  const pad = Math.max(12, Math.round(width * 0.02));
  const fontSize = Math.max(14, Math.round(width * 0.028));
  ctx.font = `bold ${fontSize}px Arial, sans-serif`;
  const line1 = dateLabel;
  const line2 = geoLabel;
  const textW = Math.max(ctx.measureText(line1).width, ctx.measureText(line2).width);
  const boxW = textW + pad * 2;
  const boxH = fontSize * 2.6 + pad;
  const x = pad;
  const y = height - boxH - pad;

  ctx.fillStyle = 'rgba(0,0,0,0.55)';
  ctx.fillRect(x, y, boxW, boxH);
  ctx.fillStyle = '#FFFFFF';
  ctx.textBaseline = 'top';
  ctx.fillText(line1, x + pad, y + pad * 0.7);
  ctx.fillText(line2, x + pad, y + pad * 0.7 + fontSize * 1.25);
}

async function capture() {
  const video = videoEl.value;
  if (!video || !streamReady.value) return;

  const width = video.videoWidth || 1280;
  const height = video.videoHeight || 720;
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, width, height);

  const capturedAt = new Date();
  const dateLabel = formatDateTime(capturedAt);
  const geo = liveGeo.value;
  const geoLabel = geo
    ? `Lat ${geo.lat.toFixed(6)}, Lng ${geo.lng.toFixed(6)}`
    : 'Lokasi tidak tersedia';

  drawWatermark(ctx, width, height, dateLabel, geoLabel);

  const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
  if (!blob) {
    Swal.fire('Error', 'Gagal mengambil foto.', 'error');
    return;
  }

  const fileName = `ipal_${Date.now()}.jpg`;
  const file = new File([blob], fileName, { type: 'image/jpeg' });
  const previewUrl = URL.createObjectURL(blob);

  items.value = [
    ...items.value,
    {
      key: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
      file,
      previewUrl,
      isNew: true,
      path: null,
      lat: geo?.lat ?? null,
      lng: geo?.lng ?? null,
      captured_at: capturedAt.toISOString(),
      metaLabel: `${dateLabel}${geo ? ` · ${geo.lat.toFixed(5)},${geo.lng.toFixed(5)}` : ''}`,
    },
  ];

  const total = items.value.length;
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: `${props.title} berhasil di-capture (${total})`,
    showConfirmButton: false,
    timer: 1800,
    timerProgressBar: true,
  });
}

function removeAt(index) {
  const next = [...items.value];
  const removed = next.splice(index, 1)[0];
  if (removed?.isNew && removed.previewUrl) {
    URL.revokeObjectURL(removed.previewUrl);
  }
  items.value = next;
}

watch(cameraOpen, (open) => {
  if (!open) closeCamera();
});

onBeforeUnmount(() => {
  closeCamera();
  (items.value || []).forEach((item) => {
    if (item?.isNew && item.previewUrl) URL.revokeObjectURL(item.previewUrl);
  });
});

defineExpose({ openCamera, closeCamera });
</script>
