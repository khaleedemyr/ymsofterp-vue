<template>
  <div>
    <!-- Tombol Action Plan di dropdown task card -->
    <button 
      v-if="canCreateActionPlan"
      @click="openModal" 
      class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center"
    >
      <i class="fas fa-clipboard-list mr-2"></i>
      Action Plan
    </button>

    <!-- Modal Action Plan -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg w-full max-w-3xl max-h-[90vh] overflow-y-auto">
          <div class="p-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold">Buat Action Plan</h3>
            <button @click="closeModal" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="p-4">
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Action Plan</label>
              <textarea 
                v-model="description" 
                class="w-full border rounded p-2" 
                rows="4"
                placeholder="Masukkan deskripsi action plan..."
              ></textarea>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Media</label>
              <div class="flex flex-wrap gap-2 mb-2">
                <button 
                  @click="openCamera('image')" 
                  class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                >
                  <i class="fas fa-camera mr-1"></i> Ambil Foto
                </button>
                <button 
                  @click="openCamera('video')" 
                  class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                >
                  <i class="fas fa-video mr-1"></i> Rekam Video
                </button>
              </div>
              
              <!-- Preview Media -->
              <div class="grid grid-cols-3 gap-2 mt-2">
                <div 
                  v-for="(media, index) in mediaFiles" 
                  :key="index" 
                  class="relative group aspect-[4/3]"
                >
                  <img 
                    v-if="media.type === 'image'" 
                    :src="media.url" 
                    class="w-full h-full object-contain bg-gray-100 rounded"
                  />
                  <video 
                    v-else 
                    :src="media.url" 
                    class="w-full h-full object-contain bg-gray-100 rounded"
                    controls
                  ></video>
                  <button 
                    @click="removeMedia(index)" 
                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="p-4 border-t flex justify-end gap-2">
            <button 
              @click="closeModal" 
              class="px-4 py-2 border rounded hover:bg-gray-100"
            >
              Batal
            </button>
            <button 
              @click="saveActionPlan" 
              class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
              :disabled="isSaving"
            >
              <i v-if="isSaving" class="fas fa-spinner fa-spin mr-1"></i>
              Simpan
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal Kamera -->
    <Teleport to="body">
      <div v-if="showCamera" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg w-full max-w-2xl">
          <div class="p-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold">
              {{ cameraType === 'image' ? 'Ambil Foto' : 'Rekam Video' }}
            </h3>
            <button @click="closeCamera" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="p-4">
            <div class="flex justify-center gap-2 mb-2">
              <button @click="switchCamera" class="px-3 py-1 bg-gray-400 text-white rounded hover:bg-gray-500">
                <i class="fas fa-sync-alt mr-1"></i> Switch Kamera
              </button>
            </div>
            <div class="mb-4">
              <video 
                ref="videoPreview" 
                class="w-full rounded" 
                autoplay 
                playsinline
              ></video>
              <canvas 
                ref="canvas" 
                class="hidden"
              ></canvas>
            </div>

            <div class="flex justify-center gap-2">
              <button 
                v-if="cameraType === 'image'"
                @click="captureImage" 
                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
              >
                <i class="fas fa-camera mr-1"></i> Ambil Foto
              </button>
              <button 
                v-else
                @click="toggleRecording" 
                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
              >
                <i class="fas" :class="isRecording ? 'fa-stop' : 'fa-video'"></i>
                {{ isRecording ? 'Stop' : 'Mulai' }} Rekam
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  taskId: {
    type: [Number, String],
    required: true
  },
  user: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['action-plan-created']);

// State
const showModal = ref(false);
const showCamera = ref(false);
const description = ref('');
const mediaFiles = ref([]);
const isSaving = ref(false);
const cameraType = ref('image');
const isRecording = ref(false);
const mediaRecorder = ref(null);
const recordedChunks = ref([]);
const facingMode = ref('environment'); // default kamera belakang

// Refs untuk kamera
const videoPreview = ref(null);
const canvas = ref(null);
let stream = null;

// Computed
const canCreateActionPlan = computed(() => {
  return props.user.division_id === 20 && props.user.status === 'A' || 
         props.user.id_role === '5af56935b011a' && props.user.status === 'A';
});

// Methods
function openModal() {
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  description.value = '';
  mediaFiles.value = [];
}

function openCamera(type) {
  cameraType.value = type;
  showCamera.value = true;
  startCamera();
}

function closeCamera() {
  showCamera.value = false;
  stopCamera();
}

function switchCamera() {
  facingMode.value = facingMode.value === 'environment' ? 'user' : 'environment';
  stopCamera();
  startCamera();
}

async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ 
      video: { facingMode: facingMode.value },
      audio: cameraType.value === 'video'
    });
    if (videoPreview.value) {
      videoPreview.value.srcObject = stream;
    }
  } catch (error) {
    console.error('Error accessing camera:', error);
    Swal.fire({
      title: 'Error',
      text: 'Tidak dapat mengakses kamera. Pastikan Anda memberikan izin akses kamera.',
      icon: 'error'
    });
  }
}

function stopCamera() {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    stream = null;
  }
  if (videoPreview.value) {
    videoPreview.value.srcObject = null;
  }
  if (isRecording.value) {
    stopRecording();
  }
}

function captureImage() {
  if (!videoPreview.value || !canvas.value) return;
  
  const context = canvas.value.getContext('2d');
  canvas.value.width = videoPreview.value.videoWidth;
  canvas.value.height = videoPreview.value.videoHeight;
  context.drawImage(videoPreview.value, 0, 0);
  
  const imageUrl = canvas.value.toDataURL('image/jpeg', 0.8);
  const timestamp = new Date().getTime();
  const filename = `image_${timestamp}.jpg`;
  
  mediaFiles.value.push({
    type: 'image',
    url: imageUrl,
    file: dataURLtoFile(imageUrl, `image_${timestamp}`)
  });
  
  closeCamera();
}

function toggleRecording() {
  if (!isRecording.value) {
    startRecording();
  } else {
    stopRecording();
  }
}

function startRecording() {
  recordedChunks.value = [];
  mediaRecorder.value = new MediaRecorder(stream, {
    mimeType: 'video/webm;codecs=vp8,opus'
  });
  
  mediaRecorder.value.ondataavailable = (event) => {
    if (event.data.size > 0) {
      recordedChunks.value.push(event.data);
    }
  };
  
  mediaRecorder.value.onstop = async () => {
    const blob = new Blob(recordedChunks.value, { type: 'video/webm' });
    const url = URL.createObjectURL(blob);
    const timestamp = new Date().getTime();
    const filename = `video_${timestamp}.webm`;
    
    mediaFiles.value.push({
      type: 'video',
      url: url,
      file: new File([blob], filename, { type: 'video/webm' })
    });
    closeCamera();
  };
  
  mediaRecorder.value.start();
  isRecording.value = true;
}

function stopRecording() {
  if (mediaRecorder.value && isRecording.value) {
    mediaRecorder.value.stop();
    isRecording.value = false;
  }
}

function removeMedia(index) {
  mediaFiles.value.splice(index, 1);
}

function dataURLtoFile(dataurl, filename) {
  const arr = dataurl.split(',');
  const mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]);
  let n = bstr.length;
  const u8arr = new Uint8Array(n);
  while (n--) {
    u8arr[n] = bstr.charCodeAt(n);
  }
  const extension = mime.split('/')[1];
  const fullFilename = `${filename}.${extension}`;
  return new File([u8arr], fullFilename, { type: mime });
}

async function saveActionPlan() {
  if (!description.value.trim()) {
    Swal.fire({
      title: 'Perhatian',
      text: 'Deskripsi action plan tidak boleh kosong',
      icon: 'warning'
    });
    return;
  }

  isSaving.value = true;
  
  try {
    const formData = new FormData();
    formData.append('task_id', props.taskId);
    formData.append('description', description.value);
    
    console.log('Media files to upload:', mediaFiles.value);
    
    mediaFiles.value.forEach((media) => {
      formData.append('media[]', media.file, media.file.name);
    });

    for (let [key, value] of formData.entries()) {
      console.log(`${key}:`, value);
    }

    const response = await axios.post('/api/action-plans', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
        'Accept': 'application/json'
      }
    });

    if (response.data.success) {
      Swal.fire({
        title: 'Berhasil',
        text: 'Action plan berhasil disimpan',
        icon: 'success'
      });
      
      emit('action-plan-created');
      closeModal();
    }
  } catch (error) {
    console.error('Error saving action plan:', error);
    console.log('Error response:', error.response?.data);
    
    let errorMessage = 'Gagal menyimpan action plan';
    
    if (error.response?.data?.errors) {
      const errors = Object.values(error.response.data.errors).flat();
      errorMessage = errors.join('\n');
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    }
    
    Swal.fire({
      title: 'Error',
      text: errorMessage,
      icon: 'error'
    });
  } finally {
    isSaving.value = false;
  }
}

// Lifecycle hooks
onBeforeUnmount(() => {
  stopCamera();
});
</script> 