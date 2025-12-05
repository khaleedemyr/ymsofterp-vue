<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  video: Object,
});

const videoError = ref(null);
const videoLoading = ref(false);

// Computed properties for video URLs
const videoUrl = computed(() => {
  if (props.video.video_url) {
    return props.video.video_url;
  }
  if (props.video.video_path) {
    return '/storage/' + props.video.video_path;
  }
  return null;
});

const thumbnailUrl = computed(() => {
  if (props.video.thumbnail_url) {
    return props.video.thumbnail_url;
  }
  if (props.video.thumbnail_path) {
    return '/storage/' + props.video.thumbnail_path;
  }
  return null;
});

function goBack() {
  router.visit('/video-tutorials');
}

function openEdit() {
  router.visit(`/video-tutorials/${props.video.id}/edit`);
}

function handleVideoError(event) {
  console.error('Video error:', event);
  videoError.value = 'Gagal memuat video. Pastikan file video tersedia.';
  videoLoading.value = false;
}

function handleVideoLoad() {
  videoLoading.value = true;
  videoError.value = null;
}

function handleVideoLoaded() {
  videoLoading.value = false;
  videoError.value = null;
}

function retryVideo() {
  videoError.value = null;
  videoLoading.value = false;
}

// Debug video URL
console.log('Video data:', props.video);
console.log('Computed video URL:', videoUrl.value);
console.log('Video path:', props.video.video_path);
</script>

<template>
  <AppLayout title="Detail Video Tutorial">
    <div class="max-w-6xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-video text-blue-500"></i> Detail Video Tutorial
        </h1>
        <div class="flex gap-2">
          <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition">
            <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
          </button>
          <button @click="openEdit" class="bg-yellow-500 text-white px-4 py-2 rounded-xl hover:bg-yellow-600 transition">
            <i class="fa-solid fa-pen-to-square mr-2"></i> Edit
          </button>
        </div>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Video Player -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <h2 class="text-xl font-semibold text-gray-800 mb-4">Video Player</h2>
          <div class="aspect-video bg-black rounded-lg overflow-hidden">
            <!-- Video Player -->
            <video 
              v-if="videoUrl" 
              :src="videoUrl" 
              controls 
              controlsList="nodownload"
              class="w-full h-full"
              preload="metadata"
              @error="handleVideoError"
              @loadstart="handleVideoLoad"
              @loadeddata="handleVideoLoaded"
              @canplay="handleVideoLoaded"
            >
              Browser Anda tidak mendukung pemutaran video.
            </video>
            
            <!-- Loading State -->
            <div v-if="videoLoading && !videoError" class="w-full h-full flex items-center justify-center text-white">
              <div class="text-center">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2"></i>
                <p>Memuat video...</p>
              </div>
            </div>
            
            <!-- Error State -->
            <div v-if="videoError" class="w-full h-full flex items-center justify-center text-white">
              <div class="text-center">
                <i class="fa-solid fa-exclamation-triangle text-4xl mb-2 text-yellow-400"></i>
                <p>Gagal memuat video</p>
                <p class="text-sm mt-2">{{ videoError }}</p>
                <button @click="retryVideo" class="mt-2 px-4 py-2 bg-blue-500 rounded hover:bg-blue-600">
                  Coba Lagi
                </button>
              </div>
            </div>
            
            <!-- No Video State -->
            <div v-if="!videoUrl && !videoLoading && !videoError" class="w-full h-full flex items-center justify-center text-white">
              <div class="text-center">
                <i class="fa-solid fa-video text-4xl mb-2"></i>
                <p>Video tidak tersedia</p>
                <p class="text-sm mt-2">Debug: {{ video.video_path }}</p>
                <p class="text-sm mt-1">URL: {{ videoUrl }}</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Video Information -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <h2 class="text-xl font-semibold text-gray-800 mb-4">Informasi Video</h2>
          
          <div class="space-y-4">
            <!-- Title -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Judul</label>
              <p class="text-lg font-semibold text-gray-900">{{ video.title }}</p>
            </div>
            
            <!-- Group -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Group</label>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                {{ video.group?.name || '-' }}
              </span>
            </div>
            
            <!-- Description -->
            <div v-if="video.description">
              <label class="block text-sm font-medium text-gray-500 mb-1">Deskripsi</label>
              <p class="text-gray-900">{{ video.description }}</p>
            </div>
            
            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
              <span :class="[
                'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
                video.status === 'A' 
                  ? 'bg-green-100 text-green-800' 
                  : 'bg-red-100 text-red-800'
              ]">
                {{ video.status_text }}
              </span>
            </div>
            
            <!-- Duration -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Durasi</label>
              <p class="text-gray-900">{{ video.duration_formatted }}</p>
            </div>
            
            <!-- File Size -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Ukuran File</label>
              <p class="text-gray-900">{{ video.video_size_formatted }}</p>
            </div>
            
            <!-- File Name -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Nama File</label>
              <p class="text-gray-900 font-mono text-sm">{{ video.video_name }}</p>
            </div>
            
            <!-- File Type -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Tipe File</label>
              <p class="text-gray-900">{{ video.video_type }}</p>
            </div>
            
            <!-- Created By -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Dibuat Oleh</label>
              <p class="text-gray-900">{{ video.creator_name }}</p>
            </div>
            
            <!-- Created At -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Tanggal Dibuat</label>
              <p class="text-gray-900">{{ new Date(video.created_at).toLocaleString('id-ID') }}</p>
            </div>
            
            <!-- Updated At -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Terakhir Diupdate</label>
              <p class="text-gray-900">{{ new Date(video.updated_at).toLocaleString('id-ID') }}</p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Thumbnail Preview -->
      <div v-if="thumbnailUrl" class="mt-6 bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Thumbnail</h2>
        <div class="max-w-md">
          <img :src="thumbnailUrl" :alt="video.title" class="w-full h-auto rounded-lg shadow">
        </div>
      </div>
    </div>
  </AppLayout>
</template> 

<style scoped>
/* Hide download button in video controls */
video::-webkit-media-controls-download-button {
  display: none !important;
}

video::-webkit-media-controls-enclosure {
  overflow: hidden;
}

video::-webkit-media-controls-panel {
  width: calc(100% + 30px);
}

/* For Firefox */
video {
  -moz-user-select: none;
  -webkit-user-select: none;
  -ms-user-select: none;
  user-select: none;
}
</style> 