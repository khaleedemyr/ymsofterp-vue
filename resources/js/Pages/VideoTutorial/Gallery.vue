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
      <h3 class="text-xl font-bold mb-4 text-white drop-shadow-lg">${video.title}</h3>
      <div class="aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl border-2 border-slate-700 mx-auto" style="max-width: 100%;">
        <video 
          src="${videoUrl}" 
          controls 
          controlsList="nodownload"
          class="w-full h-full rounded-2xl bg-black"
          preload="metadata"
          autoplay
          style="background: #18192b;"
        >
          Browser Anda tidak mendukung pemutaran video.
        </video>
      </div>
      <div class="mt-6 grid grid-cols-2 gap-2 text-sm text-gray-300 text-left max-w-md mx-auto">
        <div><span class="font-semibold text-purple-300">Group:</span> ${video.group?.name || '-'}</div>
        <div><span class="font-semibold text-purple-300">Ukuran:</span> ${video.video_size_formatted}</div>
        <div><span class="font-semibold text-purple-300">Dibuat Oleh:</span> ${video.creator_name}</div>
        <div><span class="font-semibold text-purple-300">Tanggal:</span> ${new Date(video.created_at).toLocaleDateString('id-ID')}</div>
      </div>
    </div>
  `;

  await Swal.fire({
    title: '',
    html: videoHtml,
    width: '900px',
    background: '#18192b',
    showCloseButton: true,
    showConfirmButton: false,
    allowOutsideClick: true,
    customClass: {
      popup: 'swal2-video-popup-dark',
      closeButton: 'swal2-video-close-dark'
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
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden">
      <!-- Animated Background Elements -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-700 rounded-full mix-blend-multiply filter blur-2xl opacity-20 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-700 rounded-full mix-blend-multiply filter blur-2xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute top-40 left-40 w-80 h-80 bg-pink-700 rounded-full mix-blend-multiply filter blur-2xl opacity-20 animate-blob animation-delay-4000"></div>
      </div>
      <div class="max-w-7xl w-full mx-auto py-8 px-4 relative z-10">
        <!-- Header -->
        <div class="text-center mb-12">
          <h1 class="text-5xl font-bold text-white mb-4 flex items-center justify-center gap-4 drop-shadow-2xl">
            <div class="relative">
              <i class="fa-solid fa-play-circle text-6xl bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent animate-pulse"></i>
              <div class="absolute inset-0 bg-gradient-to-r from-purple-400 to-pink-400 rounded-full blur-lg opacity-30 animate-ping"></div>
            </div>
            <span class="bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">
              Video Tutorial Gallery
            </span>
          </h1>
          <p class="text-gray-300 text-xl max-w-2xl mx-auto">
            Temukan dan pelajari video tutorial yang Anda butuhkan
          </p>
        </div>

        <!-- Statistics Cards with Glass Effect -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
          <div class="backdrop-blur-2xl bg-slate-800/60 rounded-3xl p-6 border border-white/20 shadow-3xl hover:shadow-purple-500/40 transition-all duration-500 hover:scale-105 group">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-gray-400 text-sm font-medium">Total Video</p>
                <p class="text-4xl font-bold text-white group-hover:text-purple-300 transition-colors drop-shadow-lg">{{ stats.total_videos }}</p>
              </div>
              <div class="relative">
                <i class="fa-solid fa-video text-4xl text-purple-400 group-hover:scale-110 transition-transform duration-300 drop-shadow-xl"></i>
                <div class="absolute inset-0 bg-purple-400 rounded-full blur-lg opacity-30 group-hover:opacity-50 transition-opacity"></div>
              </div>
            </div>
          </div>
          <div class="backdrop-blur-2xl bg-slate-800/60 rounded-3xl p-6 border border-white/20 shadow-3xl hover:shadow-green-500/40 transition-all duration-500 hover:scale-105 group">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-gray-400 text-sm font-medium">Total Group</p>
                <p class="text-4xl font-bold text-white group-hover:text-green-300 transition-colors drop-shadow-lg">{{ stats.total_groups }}</p>
              </div>
              <div class="relative">
                <i class="fa-solid fa-folder text-4xl text-green-400 group-hover:scale-110 transition-transform duration-300 drop-shadow-xl"></i>
                <div class="absolute inset-0 bg-green-400 rounded-full blur-lg opacity-30 group-hover:opacity-50 transition-opacity"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Search and Filters with Glass Effect -->
        <div class="backdrop-blur-2xl bg-slate-800/60 rounded-3xl border border-white/20 shadow-3xl p-8 mb-8">
          <div class="flex flex-col lg:flex-row gap-6">
            <!-- Search -->
            <div class="flex-1 relative group">
              <div class="absolute inset-0 bg-gradient-to-r from-purple-700/20 to-pink-700/20 rounded-2xl blur-xl group-hover:blur-2xl transition-all duration-300"></div>
              <div class="relative">
                <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 z-10"></i>
                <input
                  v-model="search"
                  @input="onSearchInput"
                  type="text"
                  placeholder="Cari video tutorial, group, atau pembuat..."
                  class="w-full pl-12 pr-4 py-4 rounded-2xl bg-slate-900/60 border border-white/20 text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition-all duration-300 backdrop-blur-sm shadow-lg"
                />
              </div>
            </div>
            <!-- Group Filter -->
            <div class="lg:w-64 relative group">
              <div class="absolute inset-0 bg-gradient-to-r from-blue-700/20 to-cyan-700/20 rounded-2xl blur-xl group-hover:blur-2xl transition-all duration-300"></div>
              <select v-model="groupId" class="relative w-full px-4 py-4 rounded-2xl bg-slate-900/60 border border-white/20 text-white focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition-all duration-300 backdrop-blur-sm shadow-lg">
                <option value="">Semua Group</option>
                <option v-for="group in filteredGroups" :key="group.id" :value="group.id" class="bg-slate-900">
                  {{ group.name }} ({{ group.videos_count }})
                </option>
              </select>
            </div>
            <!-- Sort -->
            <div class="lg:w-48 relative group">
              <div class="absolute inset-0 bg-gradient-to-r from-green-700/20 to-emerald-700/20 rounded-2xl blur-xl group-hover:blur-2xl transition-all duration-300"></div>
              <select v-model="sort" class="relative w-full px-4 py-4 rounded-2xl bg-slate-900/60 border border-white/20 text-white focus:ring-2 focus:ring-green-400 focus:border-green-400 transition-all duration-300 backdrop-blur-sm shadow-lg">
                <option value="newest">Terbaru</option>
                <option value="oldest">Terlama</option>
                <option value="title">Judul A-Z</option>
              </select>
            </div>
            <!-- View Mode -->
            <div class="flex gap-3">
              <button 
                @click="viewMode = 'grid'" 
                :class="[
                  'px-6 py-4 rounded-2xl transition-all duration-300 backdrop-blur-sm border shadow-lg',
                  viewMode === 'grid' 
                    ? 'bg-purple-700/40 text-white border-purple-400 shadow-purple-500/40' 
                    : 'bg-slate-900/60 text-gray-300 border-white/20 hover:bg-slate-800/80 hover:text-white'
                ]"
              >
                <i class="fa-solid fa-th-large text-lg"></i>
              </button>
              <button 
                @click="viewMode = 'list'" 
                :class="[
                  'px-6 py-4 rounded-2xl transition-all duration-300 backdrop-blur-sm border shadow-lg',
                  viewMode === 'list' 
                    ? 'bg-purple-700/40 text-white border-purple-400 shadow-purple-500/40' 
                    : 'bg-slate-900/60 text-gray-300 border-white/20 hover:bg-slate-800/80 hover:text-white'
                ]"
              >
                <i class="fa-solid fa-list text-lg"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Results Count -->
        <div class="mb-6">
          <p class="text-gray-300 text-center">
            Menampilkan {{ videos.data.length }} dari {{ videos.total }} video tutorial
          </p>
        </div>

        <!-- Video Grid -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
          <div 
            v-for="(video, index) in videos.data" 
            :key="video.id" 
            class="group cursor-pointer transform hover:scale-105 hover:rotate-1 transition-all duration-500"
            :style="{ animationDelay: `${index * 100}ms` }"
            @click="playVideo(video)"
          >
            <div class="backdrop-blur-2xl bg-slate-800/70 rounded-3xl border border-white/20 shadow-3xl overflow-hidden hover:shadow-purple-500/40 transition-all duration-500 relative">
              <!-- Thumbnail -->
              <div class="aspect-video bg-gradient-to-br from-purple-700/30 to-pink-700/30 relative overflow-hidden">
                <img 
                  v-if="video.thumbnail_url" 
                  :src="video.thumbnail_url" 
                  :alt="video.title" 
                  class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                  @error="handleThumbnailError"
                >
                <div 
                  v-else
                  class="w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-700 to-pink-700 group-hover:scale-110 transition-transform duration-700"
                >
                  <i class="fa-solid fa-video text-white text-5xl drop-shadow-xl"></i>
                </div>
                <!-- Play Button Overlay -->
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all duration-500 flex items-center justify-center">
                  <div class="bg-white/20 backdrop-blur-sm rounded-full p-4 opacity-0 group-hover:opacity-100 transition-all duration-500 transform scale-75 group-hover:scale-100 shadow-lg">
                    <i class="fa-solid fa-play text-white text-2xl ml-1"></i>
                  </div>
                </div>
                <!-- Glass Effect Overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              </div>
              <!-- Video Info -->
              <div class="p-6">
                <h3 class="font-bold text-white mb-3 line-clamp-2 group-hover:text-purple-300 transition-colors duration-300 text-lg drop-shadow-md">
                  {{ video.title }}
                </h3>
                <p v-if="video.description" class="text-gray-300 mb-4 line-clamp-2 text-sm">
                  {{ video.description }}
                </p>
                <div class="flex items-center justify-between text-sm">
                  <span class="inline-flex items-center px-3 py-1 rounded-full bg-purple-700/30 text-purple-200 border border-purple-400/30 backdrop-blur-sm">
                    {{ video.group?.name || '-' }}
                  </span>
                  <span class="text-gray-400">{{ new Date(video.created_at).toLocaleDateString('id-ID') }}</span>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-gray-400">
                  <span class="flex items-center gap-1">
                    <i class="fa-solid fa-user"></i>
                    {{ video.creator_name }}
                  </span>
                  <span class="flex items-center gap-1">
                    <i class="fa-solid fa-file-video"></i>
                    {{ video.video_size_formatted }}
                  </span>
                </div>
              </div>
              <!-- Hover Glow Effect -->
              <div class="absolute inset-0 rounded-3xl bg-gradient-to-r from-purple-700/0 via-purple-700/20 to-pink-700/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
            </div>
          </div>
        </div>

        <!-- Video List -->
        <div v-else class="space-y-6">
          <div 
            v-for="(video, index) in videos.data" 
            :key="video.id" 
            class="group cursor-pointer transform hover:scale-[1.02] hover:rotate-1 transition-all duration-500"
            :style="{ animationDelay: `${index * 50}ms` }"
            @click="playVideo(video)"
          >
            <div class="backdrop-blur-2xl bg-slate-800/70 rounded-3xl border border-white/20 shadow-3xl overflow-hidden hover:shadow-purple-500/40 transition-all duration-500">
              <div class="flex">
                <!-- Thumbnail -->
                <div class="w-64 h-40 bg-gradient-to-br from-purple-700/30 to-pink-700/30 relative overflow-hidden flex-shrink-0">
                  <img 
                    v-if="video.thumbnail_url" 
                    :src="video.thumbnail_url" 
                    :alt="video.title" 
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                    @error="handleThumbnailError"
                  >
                  <div 
                    v-else
                    class="w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-700 to-pink-700 group-hover:scale-110 transition-transform duration-700"
                  >
                    <i class="fa-solid fa-video text-white text-3xl drop-shadow-xl"></i>
                  </div>
                  <!-- Play Button Overlay -->
                  <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all duration-500 flex items-center justify-center">
                    <div class="bg-white/20 backdrop-blur-sm rounded-full p-3 opacity-0 group-hover:opacity-100 transition-all duration-500 transform scale-75 group-hover:scale-100 shadow-lg">
                      <i class="fa-solid fa-play text-white text-xl ml-1"></i>
                    </div>
                  </div>
                </div>
                <!-- Video Info -->
                <div class="flex-1 p-6">
                  <h3 class="font-bold text-white mb-3 group-hover:text-purple-300 transition-colors duration-300 text-xl drop-shadow-md">
                    {{ video.title }}
                  </h3>
                  <p v-if="video.description" class="text-gray-300 mb-4 text-sm">
                    {{ video.description }}
                  </p>
                  <div class="flex items-center gap-6 text-sm">
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-purple-700/30 text-purple-200 border border-purple-400/30 backdrop-blur-sm">
                      {{ video.group?.name || '-' }}
                    </span>
                    <span class="text-gray-400 flex items-center gap-1">
                      <i class="fa-solid fa-user"></i>
                      {{ video.creator_name }}
                    </span>
                    <span class="text-gray-400 flex items-center gap-1">
                      <i class="fa-solid fa-file-video"></i>
                      {{ video.video_size_formatted }}
                    </span>
                    <span class="text-gray-400">{{ new Date(video.created_at).toLocaleDateString('id-ID') }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="videos.data.length === 0" class="text-center py-20">
          <div class="relative">
            <i class="fa-solid fa-video text-8xl text-gray-600 mb-6 opacity-50"></i>
            <div class="absolute inset-0 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full blur-3xl opacity-20 animate-pulse"></div>
          </div>
          <h3 class="text-2xl font-bold text-gray-300 mb-4">Tidak ada video tutorial ditemukan</h3>
          <p class="text-gray-500 text-lg">Coba ubah filter pencarian Anda</p>
        </div>

        <!-- Pagination -->
        <div v-if="videos.links && videos.links.length > 3" class="mt-12 flex justify-center">
          <nav class="flex items-center space-x-3">
            <button 
              v-for="link in videos.links" 
              :key="link.label"
              @click="goToPage(link.url)"
              :disabled="!link.url || link.active"
              :class="[
                'px-6 py-3 rounded-2xl text-sm font-medium transition-all duration-300 backdrop-blur-sm border',
                link.active 
                  ? 'bg-purple-500/30 text-white border-purple-400 shadow-lg shadow-purple-500/25' 
                  : link.url 
                    ? 'bg-white/10 text-gray-300 border-white/20 hover:bg-white/20 hover:text-white hover:shadow-lg' 
                    : 'bg-gray-800/50 text-gray-500 border-gray-700 cursor-not-allowed'
              ]"
              v-html="link.label"
            ></button>
          </nav>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
@keyframes blob {
  0% {
    transform: translate(0px, 0px) scale(1);
  }
  33% {
    transform: translate(30px, -50px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  100% {
    transform: translate(0px, 0px) scale(1);
  }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

.shadow-3xl {
  box-shadow: 0 10px 40px 0 rgba(80, 0, 120, 0.25), 0 2px 8px 0 rgba(0,0,0,0.25);
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

:deep(.swal2-video-popup-dark) {
  background: #18192b !important;
  border-radius: 24px;
  box-shadow: 0 10px 40px 0 rgba(80,0,120,0.25), 0 2px 8px 0 rgba(0,0,0,0.25);
  padding: 0 0 24px 0;
}
:deep(.swal2-video-close-dark) {
  color: #a1a1aa !important;
  font-size: 28px !important;
  top: 12px !important;
  right: 16px !important;
}
:deep(.swal2-video-close-dark:hover) {
  color: #fff !important;
}

/* Custom scrollbar for webkit browsers */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: rgba(30, 41, 59, 0.2);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: rgba(80, 0, 120, 0.3);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(168, 85, 247, 0.5);
}
</style> 