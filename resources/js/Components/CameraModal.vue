<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
    <!-- Mobile-first responsive design -->
    <div class="bg-white w-full h-full sm:h-auto sm:max-h-[90vh] sm:max-w-xl sm:rounded-2xl shadow-2xl relative flex flex-col">
      <!-- Header -->
      <div class="flex-shrink-0 p-4 border-b border-gray-200 bg-gray-50 sm:bg-white sm:rounded-t-2xl">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-gray-900">
            {{ mode === 'photo' ? 'Ambil Foto' : 'Rekam Video' }}
          </h2>
          <button 
            @click="$emit('close')" 
            class="p-2 text-gray-400 hover:text-red-500 rounded-full hover:bg-gray-100 transition-colors"
          >
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>

      <!-- Camera Container - Flexible height for mobile -->
      <div class="flex-1 flex flex-col min-h-0 p-4">
        <!-- Video Container with proper aspect ratio -->
        <div class="relative bg-black rounded-lg overflow-hidden mb-4 flex-1 min-h-0">
          <video 
            ref="video" 
            autoplay 
            playsinline 
            class="w-full h-full object-cover"
            :class="mode === 'photo' ? 'aspect-[4/3]' : 'aspect-video'"
          ></video>
          
          <!-- Switch Camera Button - Repositioned for better visibility -->
          <button 
            @click="switchCamera" 
            class="absolute top-3 right-3 p-3 bg-black/60 hover:bg-black/80 text-white rounded-full transition-all shadow-lg"
            title="Switch Kamera"
          >
            <i class="fas fa-camera-rotate text-lg"></i>
          </button>

          <!-- Camera Status Indicator -->
          <div v-if="mode === 'video'" class="absolute top-3 left-3">
            <div v-if="isRecording" class="flex items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-full text-sm font-medium">
              <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
              Recording...
            </div>
          </div>
        </div>

        <!-- Control Buttons - Always visible at bottom -->
        <div class="flex-shrink-0 space-y-3">
          <!-- Photo Mode Controls -->
          <div v-if="mode === 'photo'" class="flex gap-3">
            <button 
              @click="$emit('close')" 
              class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium"
            >
              Batal
            </button>
            <button 
              @click="capturePhoto" 
              class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium shadow-lg"
            >
              <i class="fas fa-camera mr-2"></i> Capture
            </button>
          </div>

          <!-- Video Mode Controls -->
          <div v-else class="flex gap-3">
            <button 
              @click="$emit('close')" 
              class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium"
            >
              Batal
            </button>
            <button 
              v-if="!isRecording" 
              @click="startRecording" 
              class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium shadow-lg"
            >
              <i class="fas fa-video mr-2"></i> Mulai Rekam
            </button>
            <button 
              v-if="isRecording" 
              @click="stopRecording" 
              class="flex-1 px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium shadow-lg"
            >
              <i class="fas fa-stop mr-2"></i> Stop Rekam
            </button>
          </div>

          <!-- Additional Info for Mobile -->
          <div class="text-center text-xs text-gray-500 px-2">
            <p v-if="mode === 'photo'">Posisikan kamera dan tekan Capture</p>
            <p v-else-if="!isRecording">Tekan Mulai Rekam untuk memulai</p>
            <p v-else>Tekan Stop Rekam untuk mengakhiri</p>
          </div>
        </div>
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
        facingMode: forceFacingMode || currentFacingMode.value,
        width: { ideal: 1280 },
        height: { ideal: 720 }
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
        video: { 
          facingMode: currentFacingMode.value,
          width: { ideal: 1280 },
          height: { ideal: 720 }
        },
        audio: props.mode === 'video'
      });
    }

    if (video.value) {
      video.value.srcObject = stream;
      // Wait for video to be ready
      video.value.onloadedmetadata = () => {
        console.log('Video ready:', video.value.videoWidth, 'x', video.value.videoHeight);
      };
    }
    
    // Reinitialize mediaRecorder jika dalam mode video
    if (props.mode === 'video' && isRecording.value) {
      startRecording();
    }
  } catch (error) {
    console.error('Error accessing camera:', error);
    // Show user-friendly error
    alert('Tidak dapat mengakses kamera. Pastikan Anda memberikan izin akses kamera.');
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
  if (!video.value || !stream) {
    alert('Kamera belum siap. Silakan tunggu sebentar.');
    return;
  }
  
  try {
    const canvas = document.createElement('canvas');
    canvas.width = video.value.videoWidth;
    canvas.height = video.value.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video.value, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    emit('capture', dataUrl);
  } catch (error) {
    console.error('Error capturing photo:', error);
    alert('Gagal mengambil foto. Silakan coba lagi.');
  }
}

function startRecording() {
  if (!stream) {
    alert('Kamera belum siap. Silakan tunggu sebentar.');
    return;
  }
  
  try {
    recordedChunks = [];
    mediaRecorder = new MediaRecorder(stream, { 
      mimeType: 'video/webm;codecs=vp8,opus' 
    });
    
    mediaRecorder.ondataavailable = e => {
      if (e.data.size > 0) recordedChunks.push(e.data);
    };
    
    mediaRecorder.onstop = () => {
      const blob = new Blob(recordedChunks, { type: 'video/webm' });
      videoPreview.value = URL.createObjectURL(blob);
    };
    
    mediaRecorder.start();
    isRecording.value = true;
    console.log('Recording started');
  } catch (error) {
    console.error('Error starting recording:', error);
    alert('Gagal memulai perekaman. Silakan coba lagi.');
  }
}

function stopRecording() {
  if (mediaRecorder && isRecording.value) {
    try {
      mediaRecorder.stop();
      isRecording.value = false;
      console.log('Recording stopped');
      
      // Setelah selesai rekam, emit hasil ke parent dan tutup modal
      mediaRecorder.onstop = () => {
        const blob = new Blob(recordedChunks, { type: 'video/webm' });
        emit('capture', blob);
        // Tidak perlu set videoPreview, modal akan tertutup otomatis
      };
    } catch (error) {
      console.error('Error stopping recording:', error);
      alert('Gagal menghentikan perekaman. Silakan coba lagi.');
    }
  }
}

function emitVideo() {
  const blob = new Blob(recordedChunks, { type: 'video/webm' });
  emit('capture', blob);
  videoPreview.value = '';
}
</script>

<style scoped>
/* Mobile-first responsive styles */
@media (max-width: 640px) {
  .fixed.inset-0 {
    padding: 0;
  }
}

/* Ensure video maintains aspect ratio on mobile */
video {
  width: 100%;
  height: auto;
  max-height: 60vh;
}

/* Smooth transitions */
.transition-all {
  transition: all 0.2s ease-in-out;
}

/* Button hover effects */
button:hover {
  transform: translateY(-1px);
}

button:active {
  transform: translateY(0);
}
</style>
