<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-xl max-h-[90vh] overflow-y-auto relative">
      <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-xl">
        <i class="fas fa-times"></i>
      </button>
      <h2 class="text-lg font-bold mb-2">{{ mode === 'photo' ? 'Ambil Foto' : 'Rekam Video' }}</h2>
      
      <!-- Container untuk video dan tombol switch camera -->
      <div class="relative">
        <video ref="video" autoplay playsinline class="w-full rounded mb-2"></video>
        <button 
          @click="switchCamera" 
          class="absolute top-2 right-2 p-2 bg-black/50 hover:bg-black/70 text-white rounded-full transition-all"
          title="Switch Kamera"
        >
          <i class="fas fa-camera-rotate"></i>
        </button>
      </div>

      <div v-if="mode === 'photo'" class="flex gap-2">
        <button @click="capturePhoto" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          <i class="fas fa-camera mr-2"></i> Capture
        </button>
        <button @click="$emit('close')" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Batal</button>
      </div>
      <div v-else class="flex gap-2">
        <button v-if="!isRecording" @click="startRecording" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          <i class="fas fa-video mr-2"></i> Start Recording
        </button>
        <button v-if="isRecording" @click="stopRecording" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
          <i class="fas fa-stop mr-2"></i> Stop
        </button>
        <button @click="$emit('close')" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Batal</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
const emit = defineEmits(['close', 'capture']);
const props = defineProps({ mode: { type: String, default: 'photo' } });

const video = ref(null);
let stream = null;
let mediaRecorder = null;
let recordedChunks = [];
const isRecording = ref(false);
const videoPreview = ref('');
const currentFacingMode = ref('environment'); // Mulai dengan kamera belakang

// Fungsi untuk mendapatkan stream kamera
async function initializeCamera(forceFacingMode = null) {
  try {
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
    }

    const constraints = {
      video: {
        facingMode: forceFacingMode || currentFacingMode.value
      },
      audio: props.mode === 'video'
    };

    try {
      stream = await navigator.mediaDevices.getUserMedia(constraints);
    } catch (err) {
      // Jika gagal dengan kamera yang dipilih, coba kamera lainnya
      console.log('Trying fallback camera...');
      currentFacingMode.value = currentFacingMode.value === 'user' ? 'environment' : 'user';
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: currentFacingMode.value },
        audio: props.mode === 'video'
      });
    }

    video.value.srcObject = stream;
    
    // Reinitialize mediaRecorder jika dalam mode video
    if (props.mode === 'video' && isRecording.value) {
      startRecording();
    }
  } catch (error) {
    console.error('Error accessing camera:', error);
  }
}

// Fungsi untuk switch kamera
async function switchCamera() {
  currentFacingMode.value = currentFacingMode.value === 'user' ? 'environment' : 'user';
  await initializeCamera();
}

onMounted(async () => {
  await initializeCamera();
});

onUnmounted(() => {
  if (stream) stream.getTracks().forEach(track => track.stop());
  if (videoPreview.value) URL.revokeObjectURL(videoPreview.value);
});

function capturePhoto() {
  const canvas = document.createElement('canvas');
  canvas.width = video.value.videoWidth;
  canvas.height = video.value.videoHeight;
  canvas.getContext('2d').drawImage(video.value, 0, 0);
  const dataUrl = canvas.toDataURL('image/png');
  emit('capture', dataUrl);
}

function startRecording() {
  recordedChunks = [];
  mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm' });
  mediaRecorder.ondataavailable = e => {
    if (e.data.size > 0) recordedChunks.push(e.data);
  };
  mediaRecorder.onstop = () => {
    const blob = new Blob(recordedChunks, { type: 'video/webm' });
    videoPreview.value = URL.createObjectURL(blob);
  };
  mediaRecorder.start();
  isRecording.value = true;
}

function stopRecording() {
  if (mediaRecorder && isRecording.value) {
    mediaRecorder.stop();
    isRecording.value = false;
    // Setelah selesai rekam, emit hasil ke parent dan tutup modal
    mediaRecorder.onstop = () => {
      const blob = new Blob(recordedChunks, { type: 'video/webm' });
      emit('capture', blob);
      // Tidak perlu set videoPreview, modal akan tertutup otomatis
    };
  }
}

function emitVideo() {
  const blob = new Blob(recordedChunks, { type: 'video/webm' });
  emit('capture', blob);
  videoPreview.value = '';
}
</script>
