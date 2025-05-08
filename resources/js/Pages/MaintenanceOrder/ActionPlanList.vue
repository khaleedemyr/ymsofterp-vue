<template>
  <div>
    <div v-if="loading" class="flex justify-center py-4">
      <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
    </div>
    
    <div v-else-if="actionPlans.length === 0" class="text-center py-4 text-gray-500">
      Belum ada action plan
    </div>
    
    <div v-else class="space-y-4">
      <div v-for="plan in actionPlans" :key="plan.id">
        <!-- Header info -->
        <div class="text-xs text-gray-500 mb-2">
          <div>Dibuat oleh: {{ plan.created_by_name }}</div>
          <div>{{ formatDate(plan.created_at) }}</div>
        </div>
        
        <p class="text-sm text-gray-700 whitespace-pre-line mb-3">{{ plan.description }}</p>
        
        <!-- Media Gallery -->
        <div v-if="plan.media && plan.media.length > 0">
          <!-- Photos -->
          <div v-if="getMediaByType(plan.media, 'image').length > 0" class="mb-3">
            <div class="text-xs text-gray-500 mb-1">Photos ({{ getMediaByType(plan.media, 'image').length }})</div>
            <div class="flex flex-wrap gap-2">
              <div 
                v-for="(image, index) in getMediaByType(plan.media, 'image').slice(0, 3)" 
                :key="image.id"
                class="w-12 h-12 rounded border cursor-pointer overflow-hidden"
                @click="openImagePreview(image)"
              >
                <img 
                  :src="'/storage/' + image.file_path" 
                  class="w-full h-full object-cover"
                />
              </div>
              <div 
                v-if="getMediaByType(plan.media, 'image').length > 3"
                class="w-12 h-12 rounded border bg-gray-100 flex items-center justify-center text-xs text-gray-600 cursor-pointer"
                @click="openMediaSlider(getMediaByType(plan.media, 'image'), 3)"
              >
                +{{ getMediaByType(plan.media, 'image').length - 3 }}
              </div>
            </div>
          </div>

          <!-- Videos -->
          <div v-if="getMediaByType(plan.media, 'video').length > 0" class="mb-3">
            <div class="text-xs text-gray-500 mb-1">Videos ({{ getMediaByType(plan.media, 'video').length }})</div>
            <div class="flex flex-wrap gap-2">
              <div 
                v-for="video in getMediaByType(plan.media, 'video')" 
                :key="video.id"
                class="w-16 h-12 rounded bg-gray-100 flex items-center justify-center cursor-pointer relative group"
                @click="openVideoPlayer(video)"
              >
                <div class="absolute inset-0 bg-black/40 group-hover:bg-black/60 flex items-center justify-center transition-all">
                  <i class="fas fa-play text-white"></i>
                </div>
              </div>
            </div>
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
                <img 
                  v-if="selectedImage"
                  :src="'/storage/' + selectedImage.file_path"
                  class="rounded-lg"
                />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Media Slider Modal -->
    <TransitionRoot appear :show="showMediaSlider" as="template">
      <Dialog as="div" @close="closeMediaSlider" class="relative z-50">
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
                <button 
                  v-if="currentSlideIndex > 0"
                  @click="prevSlide" 
                  class="absolute left-4 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 z-10"
                >
                  <i class="fas fa-chevron-left text-3xl"></i>
                </button>
                <button 
                  v-if="currentSlideIndex < totalSlides - 1"
                  @click="nextSlide" 
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 z-10"
                >
                  <i class="fas fa-chevron-right text-3xl"></i>
                </button>

                <!-- Close Button -->
                <button 
                  @click="closeMediaSlider" 
                  class="absolute top-4 right-4 text-white hover:text-gray-300 z-10"
                >
                  <i class="fas fa-times text-xl"></i>
                </button>

                <!-- Main Content -->
                <div class="relative aspect-video bg-black rounded-lg overflow-hidden">
                  <img 
                    v-if="currentSlide"
                    :src="'/storage/' + currentSlide.file_path"
                    class="w-full h-full object-contain"
                  />
                </div>

                <!-- Thumbnails -->
                <div class="flex justify-center gap-2 mt-4">
                  <button 
                    v-for="(media, index) in sliderMedia" 
                    :key="media.id"
                    @click="goToSlide(index)"
                    :class="[
                      'w-16 h-12 rounded overflow-hidden border-2',
                      currentSlideIndex === index ? 'border-white' : 'border-transparent'
                    ]"
                  >
                    <img 
                      :src="'/storage/' + media.file_path"
                      class="w-full h-full object-cover"
                    />
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Video Player Modal -->
    <TransitionRoot appear :show="showVideoPlayer" as="template">
      <Dialog as="div" @close="closeVideoPlayer" class="relative z-50">
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
                <button 
                  @click="closeVideoPlayer" 
                  class="absolute top-4 right-4 text-white hover:text-gray-300 z-10"
                >
                  <i class="fas fa-times text-xl"></i>
                </button>
                <video
                  v-if="selectedVideo"
                  :src="'/storage/' + selectedVideo.file_path"
                  ref="videoPlayer"
                  controls
                  class="w-full"
                />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
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

// State
const actionPlans = ref([]);
const loading = ref(true);
const showImagePreview = ref(false);
const showMediaSlider = ref(false);
const showVideoPlayer = ref(false);
const selectedImage = ref(null);
const selectedVideo = ref(null);
const videoPlayer = ref(null);
const currentSlideIndex = ref(0);
const sliderMedia = ref([]);

// Computed
const currentSlide = computed(() => sliderMedia.value[currentSlideIndex.value]);
const totalSlides = computed(() => sliderMedia.value.length);

// Methods
async function loadActionPlans() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/action-plans/task/${props.taskId}`);
    if (response.data.success) {
      actionPlans.value = response.data.data;
    }
  } catch (error) {
    console.error('Error loading action plans:', error);
  } finally {
    loading.value = false;
  }
}

function formatDate(dateString) {
  return format(new Date(dateString), 'dd MMMM yyyy HH:mm', { locale: id });
}

function getMediaByType(media, type) {
  return media.filter(m => m.media_type === type);
}

function openImagePreview(image) {
  selectedImage.value = image;
  showImagePreview.value = true;
}

function closeImagePreview() {
  showImagePreview.value = false;
  selectedImage.value = null;
}

function openMediaSlider(media, startIndex = 0) {
  sliderMedia.value = media;
  currentSlideIndex.value = startIndex;
  showMediaSlider.value = true;
}

function closeMediaSlider() {
  showMediaSlider.value = false;
  currentSlideIndex.value = 0;
  sliderMedia.value = [];
}

function openVideoPlayer(video) {
  selectedVideo.value = video;
  showVideoPlayer.value = true;
}

function closeVideoPlayer() {
  if (videoPlayer.value) {
    videoPlayer.value.pause();
  }
  showVideoPlayer.value = false;
  selectedVideo.value = null;
}

function prevSlide() {
  if (currentSlideIndex.value > 0) {
    currentSlideIndex.value--;
  }
}

function nextSlide() {
  if (currentSlideIndex.value < totalSlides.value - 1) {
    currentSlideIndex.value++;
  }
}

function goToSlide(index) {
  currentSlideIndex.value = index;
}

// Lifecycle hooks
onMounted(() => {
  loadActionPlans();
});

// Expose methods to parent
defineExpose({
  loadActionPlans
});
</script> 