<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  videos: Object, // { data, links, meta }
  groups: Array,
  stats: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const groupId = ref(props.filters?.group_id || '');
const sort = ref(props.filters?.sort || 'newest');
const viewMode = ref('grid'); // grid or list

const debouncedSearch = debounce(() => {
  router.get('/video-tutorials/gallery', {
    search: search.value,
    group_id: groupId.value,
    sort: sort.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openDetail(video) {
  router.visit(`/video-tutorials/${video.id}`);
}

function formatDuration(seconds) {
  if (!seconds) return 'Unknown';
  
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;
  
  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  }
  return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

function formatTotalDuration(seconds) {
  if (!seconds) return '0 jam';
  
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  
  if (hours > 0) {
    return `${hours} jam ${minutes} menit`;
  }
  return `${minutes} menit`;
}

async function playVideo(video) {
  const videoUrl = video.video_url || `/storage/${video.video_path}`;
  
  if (!videoUrl) {
    Swal.fire('Error', 'Video tidak tersedia', 'error');
    return;
  }

  const videoHtml = `
    <div class="text-center">
      <h3 class="text-lg font-semibold mb-4 text-gray-800">${video.title}</h3>
      <div class="aspect-video bg-black rounded-lg overflow-hidden">
        <video 
          src="${videoUrl}" 
          controls 
          controlsList="nodownload"
          class="w-full h-full"
          preload="metadata"
          autoplay
        >
          Browser Anda tidak mendukung pemutaran video.
        </video>
      </div>
      <div class="mt-4 text-sm text-gray-600">
        <p><strong>Group:</strong> ${video.group?.name || '-'}</p>
        <p><strong>Durasi:</strong> ${video.duration_formatted}</p>
        <p><strong>Ukuran:</strong> ${video.video_size_formatted}</p>
        <p><strong>Dibuat Oleh:</strong> ${video.creator_name}</p>
        <p><strong>Tanggal:</strong> ${new Date(video.created_at).toLocaleDateString('id-ID')}</p>
      </div>
    </div>
  `;

  await Swal.fire({
    title: '',
    html: videoHtml,
    width: '800px',
    showCloseButton: true,
    showConfirmButton: false,
    allowOutsideClick: true,
    customClass: {
      popup: 'swal2-video-popup',
      closeButton: 'swal2-video-close'
    }
  });
}

function handleThumbnailError(event) {
  event.target.style.display = 'none';
  event.target.nextElementSibling.style.display = 'flex';
}

watch([groupId, sort], () => {
  router.get('/video-tutorials/gallery', {
    search: search.value,
    group_id: groupId.value,
    sort: sort.value,
  }, { preserveState: true, replace: true });
});

const filteredGroups = computed(() => {
  return props.groups.filter(group => group.videos_count > 0);
});
</script>

<template>
  <AppLayout title="Video Tutorial Gallery">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
            <i class="fa-solid fa-play-circle text-red-500"></i> Video Tutorial Gallery
          </h1>
          <p class="text-gray-600 mt-2">Temukan dan pelajari video tutorial yang Anda butuhkan</p>
        </div>
      </div>

      <!-- Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4 rounded-xl">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm opacity-90">Total Video</p>
              <p class="text-2xl font-bold">{{ stats.total_videos }}</p>
            </div>
            <i class="fa-solid fa-video text-3xl opacity-80"></i>
          </div>
        </div>
        <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4 rounded-xl">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm opacity-90">Total Group</p>
              <p class="text-2xl font-bold">{{ stats.total_groups }}</p>
            </div>
            <i class="fa-solid fa-folder text-3xl opacity-80"></i>
          </div>
        </div>
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-4 rounded-xl">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm opacity-90">Total Durasi</p>
              <p class="text-2xl font-bold">{{ formatTotalDuration(stats.total_duration) }}</p>
            </div>
            <i class="fa-solid fa-clock text-3xl opacity-80"></i>
          </div>
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4">
          <!-- Search -->
          <div class="flex-1">
            <div class="relative">
              <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
              <input
                v-model="search"
                @input="onSearchInput"
                type="text"
                placeholder="Cari video tutorial, group, atau pembuat..."
                class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-400 focus:border-red-400 transition"
              />
            </div>
          </div>

          <!-- Group Filter -->
          <div class="lg:w-64">
            <select v-model="groupId" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-400 focus:border-red-400 transition">
              <option value="">Semua Group</option>
              <option v-for="group in filteredGroups" :key="group.id" :value="group.id">
                {{ group.name }} ({{ group.videos_count }})
              </option>
            </select>
          </div>

          <!-- Sort -->
          <div class="lg:w-48">
            <select v-model="sort" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-400 focus:border-red-400 transition">
              <option value="newest">Terbaru</option>
              <option value="oldest">Terlama</option>
              <option value="title">Judul A-Z</option>
              <option value="duration">Durasi Terpendek</option>
            </select>
          </div>

          <!-- View Mode -->
          <div class="flex gap-2">
            <button 
              @click="viewMode = 'grid'" 
              :class="[
                'px-4 py-3 rounded-xl transition',
                viewMode === 'grid' 
                  ? 'bg-red-500 text-white' 
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              <i class="fa-solid fa-th-large"></i>
            </button>
            <button 
              @click="viewMode = 'list'" 
              :class="[
                'px-4 py-3 rounded-xl transition',
                viewMode === 'list' 
                  ? 'bg-red-500 text-white' 
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              <i class="fa-solid fa-list"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Results Count -->
      <div class="mb-4">
        <p class="text-gray-600">
          Menampilkan {{ videos.data.length }} dari {{ videos.total }} video tutorial
        </p>
      </div>

      <!-- Video Grid -->
      <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div 
          v-for="video in videos.data" 
          :key="video.id" 
          class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-2xl transition-all duration-300 cursor-pointer group"
          @click="playVideo(video)"
        >
          <!-- Thumbnail -->
          <div class="aspect-video bg-gray-200 relative overflow-hidden">
            <img 
              v-if="video.thumbnail_url" 
              :src="video.thumbnail_url" 
              :alt="video.title" 
              class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
              @error="handleThumbnailError"
            >
            <div 
              v-else
              class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-400 to-red-600 group-hover:scale-110 transition-transform duration-300"
            >
              <i class="fa-solid fa-video text-white text-4xl"></i>
            </div>
            
            <!-- Duration Badge -->
            <div class="absolute bottom-2 right-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
              {{ video.duration_formatted }}
            </div>
            
            <!-- Play Button Overlay -->
            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-300 flex items-center justify-center">
              <i class="fa-solid fa-play text-white text-4xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></i>
            </div>
          </div>

          <!-- Video Info -->
          <div class="p-4">
            <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-red-600 transition-colors">
              {{ video.title }}
            </h3>
            <p v-if="video.description" class="text-sm text-gray-600 mb-3 line-clamp-2">
              {{ video.description }}
            </p>
            
            <div class="flex items-center justify-between text-xs text-gray-500">
              <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                {{ video.group?.name || '-' }}
              </span>
              <span>{{ new Date(video.created_at).toLocaleDateString('id-ID') }}</span>
            </div>
            
            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
              <span>{{ video.creator_name }}</span>
              <span>{{ video.video_size_formatted }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Video List -->
      <div v-else class="space-y-4">
        <div 
          v-for="video in videos.data" 
          :key="video.id" 
          class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 cursor-pointer group"
          @click="playVideo(video)"
        >
          <div class="flex">
            <!-- Thumbnail -->
            <div class="w-48 h-32 bg-gray-200 relative overflow-hidden flex-shrink-0">
              <img 
                v-if="video.thumbnail_url" 
                :src="video.thumbnail_url" 
                :alt="video.title" 
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                @error="handleThumbnailError"
              >
              <div 
                v-else
                class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-400 to-red-600 group-hover:scale-110 transition-transform duration-300"
              >
                <i class="fa-solid fa-video text-white text-2xl"></i>
              </div>
              
              <!-- Duration Badge -->
              <div class="absolute bottom-2 right-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                {{ video.duration_formatted }}
              </div>
              
              <!-- Play Button Overlay -->
              <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-300 flex items-center justify-center">
                <i class="fa-solid fa-play text-white text-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></i>
              </div>
            </div>

            <!-- Video Info -->
            <div class="flex-1 p-4">
              <h3 class="font-semibold text-gray-900 mb-2 group-hover:text-red-600 transition-colors">
                {{ video.title }}
              </h3>
              <p v-if="video.description" class="text-sm text-gray-600 mb-3">
                {{ video.description }}
              </p>
              
              <div class="flex items-center gap-4 text-sm text-gray-500">
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                  {{ video.group?.name || '-' }}
                </span>
                <span>{{ video.creator_name }}</span>
                <span>{{ video.video_size_formatted }}</span>
                <span>{{ new Date(video.created_at).toLocaleDateString('id-ID') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="videos.data.length === 0" class="text-center py-12">
        <i class="fa-solid fa-video text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-semibold text-gray-600 mb-2">Tidak ada video tutorial ditemukan</h3>
        <p class="text-gray-500">Coba ubah filter pencarian Anda</p>
      </div>

      <!-- Pagination -->
      <div v-if="videos.links && videos.links.length > 3" class="mt-8 flex justify-center">
        <nav class="flex items-center space-x-2">
          <button 
            v-for="link in videos.links" 
            :key="link.label"
            @click="goToPage(link.url)"
            :disabled="!link.url || link.active"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition',
              link.active 
                ? 'bg-red-500 text-white cursor-not-allowed' 
                : link.url 
                  ? 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300' 
                  : 'bg-gray-100 text-gray-400 cursor-not-allowed'
            ]"
            v-html="link.label"
          ></button>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.bg-3d {
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15), 0 1.5px 4px 0 rgba(31, 38, 135, 0.08);
}

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

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

:deep(.swal2-video-popup) {
  border-radius: 16px;
}

:deep(.swal2-video-close) {
  color: #6b7280;
  font-size: 24px;
}

:deep(.swal2-video-close:hover) {
  color: #374151;
}
</style> 