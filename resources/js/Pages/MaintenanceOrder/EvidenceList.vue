<template>
  <div>
    <div v-if="loading" class="flex justify-center py-4">
      <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
    </div>
    <div v-else-if="evidences.length === 0" class="text-center py-4 text-gray-500">
      Belum ada evidence
    </div>
    <div v-else class="space-y-4">
      <div v-for="evidence in evidences" :key="evidence.id" class="mb-4 p-2 rounded bg-gray-50">
        <div class="text-xs text-gray-500 mb-1">
          <span>Dibuat oleh: {{ evidence.created_by_name || '-' }}</span>
          <span class="ml-2">{{ formatDate(evidence.created_at) }}</span>
        </div>
        <div v-if="evidence.notes" class="text-sm text-gray-700 mb-2">{{ evidence.notes }}</div>
        <!-- Photos -->
        <div v-if="evidence.photos && evidence.photos.length" class="mb-2">
          <div class="flex flex-wrap gap-2">
            <img
              v-for="(photo, idx) in evidence.photos.slice(0, 3)"
              :key="photo.id"
              :src="`/storage/${photo.path}`"
              class="w-16 h-16 object-cover rounded cursor-pointer"
              @click="openImagePreview(photo, evidence.photos)"
            />
            <div v-if="evidence.photos.length > 3" class="w-16 h-16 rounded bg-gray-100 flex items-center justify-center text-xs text-gray-600 cursor-pointer" @click="openImageSlider(evidence.photos, 3)">
              +{{ evidence.photos.length - 3 }}
            </div>
          </div>
        </div>
        <!-- Videos -->
        <div v-if="evidence.videos && evidence.videos.length" class="mb-2">
          <div class="flex flex-wrap gap-2">
            <video v-for="video in evidence.videos" :key="video.id" :src="`/storage/${video.path}`" class="w-24 h-16 rounded cursor-pointer" @click="openVideoModal(video)" />
          </div>
        </div>
      </div>
    </div>

    <!-- Image Preview Modal -->
    <TransitionRoot appear :show="showImagePreview" as="template">
      <Dialog as="div" @close="closeImagePreview" class="relative z-50">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/75" />
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
              <DialogPanel class="relative bg-white rounded-lg max-w-3xl">
                <button @click="closeImagePreview" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                  <i class="fas fa-times text-xl"></i>
                </button>
                <img v-if="selectedImage" :src="`/storage/${selectedImage.path}`" class="rounded-lg" />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Image Slider Modal -->
    <TransitionRoot appear :show="showImageSlider" as="template">
      <Dialog as="div" @close="closeImageSlider" class="relative z-50">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/90" />
        </TransitionChild>
        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center">
            <TransitionChild
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="relative w-full max-w-4xl p-4">
                <!-- Navigation Buttons -->
                <button v-if="currentSlideIndex > 0" @click="prevSlide" class="absolute left-4 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-chevron-left text-3xl"></i>
                </button>
                <button v-if="currentSlideIndex < sliderImages.length - 1" @click="nextSlide" class="absolute right-4 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-chevron-right text-3xl"></i>
                </button>
                <!-- Close Button -->
                <button @click="closeImageSlider" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-times text-xl"></i>
                </button>
                <!-- Main Content -->
                <div class="relative aspect-video bg-black rounded-lg overflow-hidden">
                  <img v-if="sliderImages[currentSlideIndex]" :src="`/storage/${sliderImages[currentSlideIndex].path}`" class="w-full h-full object-contain" />
                </div>
                <!-- Thumbnails -->
                <div class="flex justify-center gap-2 mt-4">
                  <button v-for="(img, idx) in sliderImages" :key="img.id" @click="goToSlide(idx)" :class="['w-16 h-12 rounded overflow-hidden border-2', currentSlideIndex === idx ? 'border-white' : 'border-transparent']">
                    <img :src="`/storage/${img.path}`" class="w-full h-full object-cover" />
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Video Player Modal -->
    <TransitionRoot appear :show="showVideoModal" as="template">
      <Dialog as="div" @close="closeVideoModal" class="relative z-50">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/75" />
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
              <DialogPanel class="relative bg-black rounded-lg overflow-hidden max-w-3xl">
                <button @click="closeVideoModal" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-times text-xl"></i>
                </button>
                <video v-if="selectedVideo" :src="`/storage/${selectedVideo.path}`" ref="videoPlayer" controls class="w-full" />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogOverlay,
  DialogPanel,
} from '@headlessui/vue';

const props = defineProps({
  taskId: {
    type: [Number, String],
    required: true
  }
});

const evidences = ref([]);
const loading = ref(true);

// Modal state
const showImagePreview = ref(false);
const selectedImage = ref(null);
const showImageSlider = ref(false);
const sliderImages = ref([]);
const currentSlideIndex = ref(0);
const showVideoModal = ref(false);
const selectedVideo = ref(null);
const videoPlayer = ref(null);

function formatDate(dateString) {
  return format(new Date(dateString), 'dd MMMM yyyy HH:mm', { locale: id });
}

function openImagePreview(photo, allPhotos) {
  selectedImage.value = photo;
  showImagePreview.value = true;
}
function closeImagePreview() {
  showImagePreview.value = false;
  selectedImage.value = null;
}
function openImageSlider(photos, startIdx = 0) {
  sliderImages.value = photos;
  currentSlideIndex.value = startIdx;
  showImageSlider.value = true;
}
function closeImageSlider() {
  showImageSlider.value = false;
  currentSlideIndex.value = 0;
  sliderImages.value = [];
}
function prevSlide() {
  if (currentSlideIndex.value > 0) currentSlideIndex.value--;
}
function nextSlide() {
  if (currentSlideIndex.value < sliderImages.value.length - 1) currentSlideIndex.value++;
}
function goToSlide(idx) {
  currentSlideIndex.value = idx;
}
function openVideoModal(video) {
  selectedVideo.value = video;
  showVideoModal.value = true;
}
function closeVideoModal() {
  if (videoPlayer.value) videoPlayer.value.pause();
  showVideoModal.value = false;
  selectedVideo.value = null;
}

async function loadEvidences() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/maintenance-evidence/${props.taskId}`);
    evidences.value = response.data;
  } catch (error) {
    console.error('Error loading evidences:', error);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadEvidences();
});
</script> 