<template>
  <TransitionRoot appear :show="show" as="template">
    <Dialog as="div" @close="closeModal" class="relative z-[8888]">
      <TransitionChild
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <DialogOverlay class="fixed inset-0 bg-black/40" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <TransitionChild
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel class="w-full max-w-3xl transform overflow-hidden rounded-lg bg-white p-6 text-left align-middle shadow-xl transition-all">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                  Add Evidence
                </h3>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                  <i class="fas fa-times"></i>
                </button>
              </div>

              <div class="space-y-4">
                <!-- Pilihan Mode -->
                <div v-if="!showCameraModal" class="flex gap-4 justify-center">
                  <button @click="openCamera('photo')" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Ambil Foto</button>
                  <button @click="openCamera('video')" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Rekam Video</button>
                </div>

                <!-- Camera Modal -->
                <CameraModal v-if="showCameraModal" :mode="cameraMode" @close="closeCamera" @capture="handleCapture" />

                <!-- Captured Media -->
                <div v-if="capturedPhotos.length > 0 || capturedVideos.length > 0" class="space-y-4">
                  <h4 class="font-medium text-gray-700">Captured Media</h4>
                  <!-- Photos -->
                  <div v-if="capturedPhotos.length > 0" class="space-y-2">
                    <h5 class="text-sm font-medium text-gray-600">Photos</h5>
                    <div class="flex flex-wrap gap-2">
                      <div v-for="(photo, index) in capturedPhotos" :key="index" class="relative">
                        <img :src="photo" class="w-24 h-24 object-cover rounded" />
                        <button @click="removePhoto(index)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600">
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                  <!-- Videos -->
                  <div v-if="capturedVideos.length > 0" class="space-y-2">
                    <h5 class="text-sm font-medium text-gray-600">Videos</h5>
                    <div class="flex flex-wrap gap-2">
                      <div v-for="(vid, i) in capturedVideos" :key="i" class="relative">
                        <video :src="vid.url" class="w-24 h-24 object-cover rounded" controls></video>
                        <button @click="removeVideo(i)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600">
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-gray-700">Notes</label>
                  <textarea
                    v-model="notes"
                    rows="3"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="Add notes about the evidence..."
                  ></textarea>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                  <button
                    @click="submitEvidence"
                    :disabled="isSubmitting || (!capturedPhotos.length && !capturedVideos.length)"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <span v-if="isSubmitting">Submitting...</span>
                    <span v-else>Submit Evidence</span>
                  </button>
                </div>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { ref } from 'vue';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogOverlay,
  DialogPanel,
} from '@headlessui/vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import CameraModal from './CameraModal.vue';

const props = defineProps({
  taskId: {
    type: [String, Number],
    required: true
  }
});

const emit = defineEmits(['close', 'evidence-added']);

const show = ref(true);
const notes = ref('');
const isSubmitting = ref(false);
const capturedPhotos = ref([]);
const capturedVideos = ref([]);

// Camera modal state
const showCameraModal = ref(false);
const cameraMode = ref('photo');

function openCamera(mode) {
  cameraMode.value = mode;
  showCameraModal.value = true;
}
function closeCamera() {
  showCameraModal.value = false;
}
function handleCapture(data) {
  if (cameraMode.value === 'photo') {
    capturedPhotos.value.push(data);
  } else if (cameraMode.value === 'video') {
    capturedVideos.value.push({
      blob: data,
      url: URL.createObjectURL(data)
    });
  }
  showCameraModal.value = false;
}

const closeModal = () => {
  emit('close');
};

const removePhoto = (index) => {
  capturedPhotos.value.splice(index, 1);
};

const removeVideo = (index) => {
  if (capturedVideos.value[index]?.url) {
    URL.revokeObjectURL(capturedVideos.value[index].url);
  }
  capturedVideos.value.splice(index, 1);
};

function dataURLtoFile(dataurl, filename) {
  var arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1], bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
  while (n--) u8arr[n] = bstr.charCodeAt(n);
  return new File([u8arr], filename, { type: mime });
}

const submitEvidence = async () => {
  if (!capturedPhotos.value.length && !capturedVideos.value.length) {
    Swal.fire('Warning', 'Please capture at least one photo or video', 'warning');
    return;
  }

  isSubmitting.value = true;

  try {
    const formData = new FormData();
    formData.append('task_id', props.taskId);
    formData.append('notes', notes.value);

    // Add photos
    capturedPhotos.value.forEach((photo, i) => {
      const file = dataURLtoFile(photo, `photo_${i}.jpg`);
      if (file && file.size > 0) {
        console.log('Adding photo:', file.name, file.type, file.size);
        formData.append('media[]', file);
      }
    });

    // Add videos
    capturedVideos.value.forEach((item, i) => {
      const file = new File([item.blob], `video_${i}.webm`, { type: 'video/webm' });
      if (file && file.size > 0) {
        console.log('Adding video:', file.name, file.type, file.size);
        formData.append('media[]', file);
      }
    });

    // Debug: log all FormData entries
    console.log('FormData contents:');
    for (let pair of formData.entries()) {
      console.log(pair[0], pair[1], pair[1]?.type, pair[1]?.name, pair[1]?.size);
    }

    const response = await axios.post('/api/maintenance-evidence', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    console.log('Response:', response.data);

    Swal.fire('Success', 'Evidence submitted successfully', 'success');
    emit('evidence-added');
    closeModal();
    
    // Cleanup and reset
    capturedPhotos.value = [];
    capturedVideos.value = [];
    notes.value = '';
  } catch (error) {
    console.error('Error submitting evidence:', error);
    let msg = 'Failed to submit evidence';
    if (error.response?.data?.errors) {
      msg = Object.values(error.response.data.errors).flat().join('<br>');
    } else if (error.response?.data?.message) {
      msg = error.response.data.message;
    }
    Swal.fire('Error', msg, 'error');
  } finally {
    isSubmitting.value = false;
  }
};
</script> 