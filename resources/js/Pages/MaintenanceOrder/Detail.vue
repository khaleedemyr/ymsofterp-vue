<template>
  <div class="max-w-7xl mx-auto py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-blue-900">Task Detail</h1>
      <div class="flex gap-3">
        <a href="/maintenance-order/list" class="btn-secondary">
          <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
        <button @click="editTask" class="btn-primary">
          <i class="fas fa-edit mr-2"></i> Edit Task
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i>
      <p class="mt-4 text-gray-600">Loading task details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
      <div class="flex items-center">
        <i class="fas fa-exclamation-triangle text-red-600 text-xl mr-3"></i>
        <div>
          <h3 class="text-lg font-medium text-red-800">Error Loading Task</h3>
          <p class="text-red-700 mt-1">{{ error }}</p>
        </div>
      </div>
      <div class="mt-4">
        <button @click="loadTask" class="btn-primary">
          <i class="fas fa-redo mr-2"></i> Try Again
        </button>
      </div>
    </div>

    <!-- Task Details -->
    <div v-else-if="task" class="space-y-6">
      <!-- Basic Info Card -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Task Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700">Task Number</label>
            <p class="mt-1 text-sm text-gray-900">{{ task.task_number }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <span :class="getStatusBadgeClass(task.status)" class="mt-1 inline-block px-2 py-1 text-xs font-medium rounded-full">
              {{ getStatusLabel(task.status) }}
            </span>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Title</label>
            <p class="mt-1 text-sm text-gray-900">{{ task.title }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Priority</label>
            <span :class="getPriorityBadgeClass(task.priority_name)" class="mt-1 inline-block px-2 py-1 text-xs font-medium rounded-full">
              {{ task.priority_name }}
            </span>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Category</label>
            <p class="mt-1 text-sm text-gray-900">{{ task.label_name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Due Date</label>
            <p class="mt-1 text-sm text-gray-900">{{ formatDate(task.due_date) }}</p>
          </div>
        </div>
        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700">Description</label>
          <p class="mt-1 text-sm text-gray-900">{{ task.description }}</p>
        </div>
      </div>

      <!-- Members Card -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Team Members</h2>
        <div class="space-y-3">
          <div v-for="member in task.members" :key="member.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center">
              <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-user text-blue-600"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-900">{{ member.nama_lengkap }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ member.role }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Media & Documents Card -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Attachments</h2>
        
        <!-- Media Files -->
        <div v-if="task.media && task.media.length > 0" class="mb-6">
          <h3 class="text-lg font-medium text-gray-800 mb-3">Media Files</h3>
          
          <!-- Photos -->
          <div v-if="mediaFiles.images.length > 0" class="mb-4">
            <div class="text-sm text-gray-600 mb-2">Photos ({{ mediaFiles.images.length }})</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div v-for="(image, index) in displayedImages" :key="image.id" 
                   class="w-20 h-20 rounded-lg border cursor-pointer overflow-hidden hover:shadow-md transition-shadow"
                   @click="openImagePreview(image)">
                <img :src="image.url" :alt="image.name" class="w-full h-full object-cover" />
              </div>
              <div v-if="mediaFiles.images.length > 4" 
                   class="w-20 h-20 rounded-lg border bg-gray-100 flex items-center justify-center text-xs text-gray-600 cursor-pointer hover:shadow-md transition-shadow"
                   @click="openMediaSlider('image', 4)">
                +{{ mediaFiles.images.length - 4 }}
              </div>
            </div>
          </div>
          
          <!-- Videos -->
          <div v-if="mediaFiles.videos.length > 0" class="mb-4">
            <div class="text-sm text-gray-600 mb-2">Videos ({{ mediaFiles.videos.length }})</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div v-for="video in mediaFiles.videos" :key="video.id"
                   class="w-20 h-20 rounded-lg bg-gray-100 flex items-center justify-center cursor-pointer relative group hover:shadow-md transition-shadow"
                   @click="openVideoPlayer(video)">
                <div class="absolute inset-0 bg-black/40 group-hover:bg-black/60 flex items-center justify-center transition-all rounded-lg">
                  <i class="fas fa-play text-white text-xl"></i>
                </div>
                <span class="text-xs text-gray-600">{{ video.name }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Documents -->
        <div v-if="task.documents && task.documents.length > 0">
          <h3 class="text-lg font-medium text-gray-800 mb-3">Documents</h3>
          <div class="space-y-2">
            <div v-for="doc in task.documents" :key="doc.id" class="flex items-center p-3 bg-gray-50 rounded-lg">
              <i class="fas fa-file-alt text-blue-600 mr-3"></i>
              <span class="text-sm text-gray-900">{{ doc.file_name || 'Document' }}</span>
            </div>
          </div>
        </div>

        <!-- No Attachments -->
        <div v-if="(!task.media || task.media.length === 0) && (!task.documents || task.documents.length === 0)" class="text-center py-8 text-gray-500">
          <i class="fas fa-paperclip text-4xl mb-3"></i>
          <p>No attachments found</p>
        </div>
      </div>

      <!-- Comments Card -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold text-gray-900">Comments</h2>
          <button @click="showCommentModal = true" class="btn-primary">
            <i class="fas fa-plus mr-2"></i> Add Comment
          </button>
        </div>
        
        <!-- Comments List -->
        <div v-if="comments.length > 0" class="space-y-4">
          <div v-for="comment in comments" :key="comment.id" class="border-l-4 border-blue-500 pl-4 py-3">
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <span class="font-medium text-gray-900">{{ comment.user_name }}</span>
                  <span class="text-xs text-gray-500">{{ formatDate(comment.created_at) }}</span>
                </div>
                <p class="text-gray-700 mb-2">{{ comment.comment }}</p>
                
                <!-- Attachments -->
                <div v-if="comment.attachments && comment.attachments.length > 0" class="flex flex-wrap gap-2">
                  <div v-for="attachment in comment.attachments" :key="attachment.id" class="flex items-center gap-1 text-xs text-blue-600">
                    <i class="fas fa-paperclip"></i>
                    <span>{{ attachment.file_name }}</span>
                  </div>
                </div>
              </div>
              
              <!-- Delete Button (only for own comments) -->
              <button 
                v-if="comment.user_id === currentUserId" 
                @click="deleteComment(comment.id)"
                class="text-red-600 hover:text-red-800 text-sm"
                title="Delete comment"
              >
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
        
        <!-- No Comments -->
        <div v-else class="text-center py-8 text-gray-500">
          <i class="fas fa-comments text-4xl mb-3"></i>
          <p>No comments yet. Be the first to comment!</p>
        </div>
      </div>
    </div>

    <!-- Comment Modal -->
    <CommentModal 
      v-if="showCommentModal"
      :task-id="props.id"
      @close="showCommentModal = false"
      @comment-added="onCommentAdded"
    />

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
                <img v-if="currentImage" 
                     :src="currentImage.url" 
                     :alt="currentImage.name"
                     class="rounded-lg" />
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
                <button v-if="currentSlideIndex > 0"
                        @click="prevSlide" 
                        class="absolute left-4 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-chevron-left text-3xl"></i>
                </button>
                <button v-if="currentSlideIndex < totalSlides - 1"
                        @click="nextSlide" 
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-chevron-right text-3xl"></i>
                </button>

                <!-- Close Button -->
                <button @click="closeMediaSlider" 
                        class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-times text-xl"></i>
                </button>

                <!-- Main Content -->
                <div class="relative aspect-video bg-black rounded-lg overflow-hidden">
                  <img v-if="currentSlide.mediaType === 'image'"
                       :src="currentSlide.url"
                       :alt="currentSlide.name"
                       class="w-full h-full object-contain" />
                  <video v-else-if="currentSlide.mediaType === 'video'"
                         :src="currentSlide.url"
                         :ref="el => { if (currentSlide.mediaType === 'video') videoPlayer = el }"
                         controls
                         class="w-full h-full" />
                </div>

                <!-- Thumbnails -->
                <div class="flex justify-center gap-2 mt-4">
                  <button v-for="(media, index) in allMedia" 
                          :key="media.id"
                          @click="goToSlide(index)"
                          :class="[
                            'w-16 h-12 rounded overflow-hidden border-2',
                            currentSlideIndex === index ? 'border-white' : 'border-transparent'
                          ]">
                    <img v-if="media.mediaType === 'image'"
                         :src="media.url"
                         :alt="media.name"
                         class="w-full h-full object-cover" />
                    <div v-else
                         class="w-full h-full bg-gray-800 flex items-center justify-center">
                      <i class="fas fa-play text-white"></i>
                    </div>
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
                <button @click="closeVideoPlayer" 
                        class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-times text-xl"></i>
                </button>
                <video v-if="currentVideo"
                       :src="currentVideo.url"
                       ref="videoPlayer"
                       controls
                       class="w-full" />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import CommentModal from './CommentModal.vue';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogOverlay,
  DialogPanel,
} from '@headlessui/vue';

// Props
const props = defineProps({
  id: String
});

// Reactive data
const task = ref(null);
const loading = ref(true);
const error = ref(null);
const comments = ref([]);
const showCommentModal = ref(false);

// Modal state variables
const showImagePreview = ref(false);
const showMediaSlider = ref(false);
const showVideoPlayer = ref(false);
const currentImage = ref(null);
const currentVideo = ref(null);
const currentSlideIndex = ref(0);
const videoPlayer = ref(null);

// Computed properties
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id || null);

// Media computed properties
const mediaFiles = computed(() => {
  if (!task.value?.media) return { images: [], videos: [] };
  
  return task.value.media.reduce((acc, media) => {
    if (media.file_type && media.file_type.startsWith('image/')) {
      acc.images.push({
        id: media.id,
        url: `/storage/${media.file_path}`,
        name: media.file_name || 'Image',
        type: media.file_type
      });
    } else if (media.file_type && media.file_type.startsWith('video/')) {
      acc.videos.push({
        id: media.id,
        url: `/storage/${media.file_path}`,
        name: media.file_name || 'Video',
        type: media.file_type
      });
    }
    return acc;
  }, { images: [], videos: [] });
});

const displayedImages = computed(() => {
  return mediaFiles.value.images.slice(0, 4);
});

const allMedia = computed(() => {
  const images = mediaFiles.value.images.map(img => ({ ...img, mediaType: 'image' }));
  const videos = mediaFiles.value.videos.map(vid => ({ ...vid, mediaType: 'video' }));
  return [...images, ...videos];
});

const totalSlides = computed(() => allMedia.value.length);

const currentSlide = computed(() => allMedia.value[currentSlideIndex.value] || {});

// Methods
async function loadTask() {
  try {
    loading.value = true;
    error.value = null;
    
    const response = await axios.get(`/api/maintenance-order/${props.id}`);
    task.value = response.data;
    
    // Load comments
    await loadComments();
  } catch (err) {
    console.error('Error loading task:', err);
    error.value = err.response?.data?.error || 'Failed to load task details';
  } finally {
    loading.value = false;
  }
}

async function loadComments() {
  try {
    const response = await axios.get(`/api/maintenance-comments/${props.id}`);
    comments.value = response.data;
  } catch (err) {
    console.error('Error loading comments:', err);
  }
}

async function deleteComment(commentId) {
  try {
    await axios.delete(`/api/maintenance-comments/${commentId}`);
    await loadComments(); // Reload comments
  } catch (err) {
    console.error('Error deleting comment:', err);
  }
}

function onCommentAdded() {
  showCommentModal.value = false;
  loadComments(); // Reload comments
}

function editTask() {
  window.location.href = `/maintenance-order/${props.id}/edit`;
}

function getStatusLabel(status) {
  const labels = {
    'TASK': 'To Do',
    'PR': 'Purchase Requisition',
    'PO': 'Purchase Order',
    'IN_PROGRESS': 'In Progress',
    'IN_REVIEW': 'In Review',
    'DONE': 'Done'
  };
  return labels[status] || status;
}

function getStatusBadgeClass(status) {
  const classes = {
    'TASK': 'bg-gray-100 text-gray-800',
    'PR': 'bg-yellow-100 text-yellow-800',
    'PO': 'bg-blue-100 text-blue-800',
    'IN_PROGRESS': 'bg-purple-100 text-purple-800',
    'IN_REVIEW': 'bg-orange-100 text-orange-800',
    'DONE': 'bg-green-100 text-green-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function getPriorityBadgeClass(priority) {
  const classes = {
    'Low': 'bg-green-100 text-green-800',
    'Medium': 'bg-yellow-100 text-yellow-800',
    'High': 'bg-red-100 text-red-800',
    'Critical': 'bg-purple-100 text-purple-800'
  };
  return classes[priority] || 'bg-gray-100 text-gray-800';
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID');
}

// Media handling methods
function openImagePreview(image) {
  currentImage.value = image;
  showImagePreview.value = true;
}

function closeImagePreview() {
  showImagePreview.value = false;
  currentImage.value = null;
}

function openMediaSlider(type, index) {
  currentSlideIndex.value = index;
  showMediaSlider.value = true;
}

function closeMediaSlider() {
  showMediaSlider.value = false;
  currentSlideIndex.value = 0;
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

function openVideoPlayer(video) {
  currentVideo.value = video;
  showVideoPlayer.value = true;
}

function closeVideoPlayer() {
  showVideoPlayer.value = false;
  currentVideo.value = null;
}

// Lifecycle
onMounted(() => {
  loadTask();
  document.title = `Task Detail - YMSoft`;
});
</script>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default {
  layout: AppLayout
}
</script>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors;
}

.btn-secondary {
  @apply px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors;
}
</style>
