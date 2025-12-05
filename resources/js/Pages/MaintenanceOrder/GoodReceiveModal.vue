<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative">
        <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <h3 class="text-lg font-medium mb-4">Upload Bukti Penerimaan Barang</h3>
        <div class="mb-4 flex gap-2 items-center">
          <button :class="['px-3 py-1 rounded', mode==='photo' ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700']" @click="mode='photo'">Foto</button>
          <button :class="['px-3 py-1 rounded', mode==='video' ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700']" @click="mode='video'">Video</button>
          <button @click="switchCamera" class="ml-4 px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 text-gray-700 flex items-center gap-1">
            <i class="fas fa-sync-alt"></i> Switch Camera
          </button>
        </div>
        <div class="flex flex-col items-center">
          <video ref="videoRef" autoplay playsinline class="rounded border mb-2 w-full max-w-md h-64 object-cover bg-black"></video>
          <canvas ref="canvasRef" class="hidden"></canvas>
          <div v-if="mode==='photo'">
            <button @click="capturePhoto" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 mb-2">Capture Foto</button>
          </div>
          <div v-else>
            <button v-if="!isRecording" @click="startRecording" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 mb-2">Mulai Rekam</button>
            <button v-else @click="stopRecording" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 mb-2">Stop Rekam</button>
          </div>
        </div>
        <div class="mt-4">
          <div class="font-medium mb-2">Preview Bukti:</div>
          <div class="flex flex-wrap gap-3">
            <div v-for="(file, idx) in receiveFiles" :key="idx" class="relative">
              <img v-if="file.type.startsWith('image/')" :src="file.preview" class="w-24 h-24 object-cover rounded border" />
              <video v-else controls :src="file.preview" class="w-24 h-24 object-cover rounded border"></video>
              <button @click="removeFile(idx)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
          </div>
        </div>
        <div class="mt-4">
          <label class="block text-sm font-medium mb-1">Catatan (opsional)</label>
          <textarea v-model="receiveNotes" class="w-full border rounded p-2" rows="2" placeholder="Catatan penerimaan..."></textarea>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button @click="$emit('close')" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Batal</button>
          <button @click="handleUploadReceive" :disabled="isUploadingReceive" class="px-4 py-2 text-white bg-indigo-500 rounded hover:bg-indigo-600 flex items-center gap-2">
            <div v-if="isUploadingReceive" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <span>{{ isUploadingReceive ? 'Mengupload...' : 'Upload' }}</span>
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  po: Object
});
const emit = defineEmits(['close', 'uploaded']);

const mode = ref('photo');
const receiveFiles = ref([]); // {file, preview, type}
const receiveNotes = ref('');
const isUploadingReceive = ref(false);
const isRecording = ref(false);
const videoRef = ref(null);
const canvasRef = ref(null);
let stream = null;
let mediaRecorder = null;
let recordedChunks = [];
const cameraFacingMode = ref('environment');

watch(() => props.show, async (val) => {
  if (val) {
    receiveFiles.value = [];
    receiveNotes.value = '';
    await startCamera();
  } else {
    stopCamera();
  }
});

onBeforeUnmount(() => {
  stopCamera();
});

async function startCamera() {
  stopCamera();
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: cameraFacingMode.value }, audio: mode.value === 'video' });
    if (videoRef.value) {
      videoRef.value.srcObject = stream;
    }
  } catch (e) {
    Swal.fire('Error', 'Tidak bisa mengakses kamera', 'error');
  }
}
function stopCamera() {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    stream = null;
  }
  if (videoRef.value) videoRef.value.srcObject = null;
}

watch(mode, async () => {
  if (props.show) await startCamera();
});

function capturePhoto() {
  const video = videoRef.value;
  const canvas = canvasRef.value;
  if (!video || !canvas) return;
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  canvas.toBlob(blob => {
    const file = new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
    const preview = URL.createObjectURL(blob);
    receiveFiles.value.push({ file, preview, type: 'image/jpeg' });
  }, 'image/jpeg', 0.95);
}

function startRecording() {
  if (!stream) return;
  recordedChunks = [];
  mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm' });
  mediaRecorder.ondataavailable = e => {
    if (e.data.size > 0) recordedChunks.push(e.data);
  };
  mediaRecorder.onstop = () => {
    const blob = new Blob(recordedChunks, { type: 'video/webm' });
    const file = new File([blob], `video-${Date.now()}.webm`, { type: 'video/webm' });
    const preview = URL.createObjectURL(blob);
    receiveFiles.value.push({ file, preview, type: 'video/webm' });
  };
  mediaRecorder.start();
  isRecording.value = true;
}
function stopRecording() {
  if (mediaRecorder && isRecording.value) {
    mediaRecorder.stop();
    isRecording.value = false;
  }
}

function removeFile(idx) {
  const f = receiveFiles.value[idx];
  if (f && f.preview) URL.revokeObjectURL(f.preview);
  receiveFiles.value.splice(idx, 1);
}

function switchCamera() {
  cameraFacingMode.value = cameraFacingMode.value === 'environment' ? 'user' : 'environment';
  startCamera();
}

async function handleUploadReceive() {
  if (!receiveFiles.value.length) {
    Swal.fire('Error', 'Minimal 1 foto/video wajib diupload', 'error');
    return;
  }
  isUploadingReceive.value = true;
  try {
    const formData = new FormData();
    receiveFiles.value.forEach(({ file }) => {
      formData.append('receive_files[]', file);
    });
    formData.append('receive_notes', receiveNotes.value);
    formData.append('camera_facing_mode', cameraFacingMode.value);
    await axios.post(`/api/purchase-orders/${props.po.id}/receive`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    Swal.fire('Sukses', 'Bukti penerimaan berhasil diupload', 'success');
    emit('uploaded');
    emit('close');
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal upload bukti penerimaan', 'error');
  } finally {
    isUploadingReceive.value = false;
  }
}
</script> 