<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  videos: Object, // { data, links, meta }
  groups: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const groupId = ref(props.filters?.group_id || '');
const status = ref(props.filters?.status || '');

const debouncedSearch = debounce(() => {
  router.get('/video-tutorials', {
    search: search.value,
    group_id: groupId.value,
    status: status.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  router.visit('/video-tutorials/create');
}

function openEdit(video) {
  router.visit(`/video-tutorials/${video.id}/edit`);
}

function openShow(video) {
  router.visit(`/video-tutorials/${video.id}`);
}

function openGroupCreate() {
  router.visit('/video-tutorial-groups/create');
}

function openGroupIndex() {
  router.visit('/video-tutorial-groups');
}

function openGallery() {
  router.visit('/video-tutorials/gallery');
}

async function playVideo(video) {
  // Generate video URL
  const videoUrl = video.video_url || `/storage/${video.video_path}`;
  
  if (!videoUrl) {
    Swal.fire('Error', 'Video tidak tersedia', 'error');
    return;
  }

  // Create video player HTML
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

async function hapus(video) {
  const result = await Swal.fire({
    title: 'Hapus Video Tutorial?',
    text: `Yakin ingin menghapus video tutorial "${video.title}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('video-tutorials.destroy', video.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Video tutorial berhasil dihapus!', 'success'),
  });
}

async function toggleStatus(video) {
  const statusText = video.status === 'A' ? 'dinonaktifkan' : 'diaktifkan';
  router.patch(route('video-tutorials.toggle-status', video.id), {}, {
    onSuccess: () => Swal.fire('Berhasil', `Video tutorial berhasil ${statusText}!`, 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function handleThumbnailError(event) {
  // Hide broken thumbnail and show fallback
  event.target.style.display = 'none';
  event.target.nextElementSibling.style.display = 'flex';
}

watch([groupId, status], () => {
  router.get('/video-tutorials', {
    search: search.value,
    group_id: groupId.value,
    status: status.value,
  }, { preserveState: true, replace: true });
});
</script>

<template>
  <AppLayout title="Data Video Tutorial">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-video text-blue-500"></i> Data Video Tutorial
        </h1>
        <div class="flex gap-2">
          <button @click="openGroupIndex" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa-solid fa-folder mr-2"></i> Kelola Group
          </button>
          <button @click="openGallery" class="bg-gradient-to-r from-red-500 to-red-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa-solid fa-play-circle mr-2"></i> Video Gallery
          </button>
          <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa-solid fa-plus mr-2"></i> Upload Video Tutorial
          </button>
        </div>
      </div>
      
      <div class="mb-4 flex gap-4">
        <select v-model="groupId" class="form-input rounded-xl">
          <option value="">Semua Group</option>
          <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option>
        </select>
        <select v-model="status" class="form-input rounded-xl">
          <option value="">Semua Status</option>
          <option value="A">Active</option>
          <option value="N">Inactive</option>
        </select>
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari judul, deskripsi, nama file..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>
      
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Thumbnail</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Judul</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Group</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Durasi</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Ukuran</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Dibuat Oleh</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="video in videos.data" :key="video.id" class="hover:bg-blue-50 transition">
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="w-16 h-12 bg-gray-200 rounded-lg overflow-hidden flex items-center justify-center relative cursor-pointer group" @click="playVideo(video)">
                  <img v-if="video.thumbnail_url" :src="video.thumbnail_url" :alt="video.title" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-200" @error="handleThumbnailError">
                  <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-400 to-blue-600 group-hover:scale-110 transition-transform duration-200" :style="{ display: video.thumbnail_url ? 'none' : 'flex' }">
                    <i class="fa-solid fa-video text-white text-lg"></i>
                  </div>
                  <!-- Play button overlay -->
                  <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                    <i class="fa-solid fa-play text-white text-xl opacity-0 group-hover:opacity-100 transition-opacity duration-200"></i>
                  </div>
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="font-semibold text-gray-900">{{ video.title }}</div>
                <div class="text-sm text-gray-500">{{ video.description || '-' }}</div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  {{ video.group?.name || '-' }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                {{ video.duration_formatted }}
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                {{ video.video_size_formatted }}
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <button @click="toggleStatus(video)" :class="[
                  'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition',
                  video.status === 'A' 
                    ? 'bg-green-100 text-green-800 hover:bg-green-200' 
                    : 'bg-red-100 text-red-800 hover:bg-red-200'
                ]">
                  {{ video.status_text }}
                </button>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                {{ video.creator_name }}
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(video)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(video)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="hapus(video)" class="px-2 py-1 rounded bg-red-500 text-white hover:bg-red-600 transition" title="Hapus">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="videos.data.length === 0">
              <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada data video tutorial</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div v-if="videos.links && videos.links.length > 3" class="mt-6 flex justify-center">
        <nav class="flex items-center space-x-2">
          <button 
            v-for="link in videos.links" 
            :key="link.label"
            @click="goToPage(link.url)"
            :disabled="!link.url || link.active"
            :class="[
              'px-3 py-2 rounded-lg text-sm font-medium transition',
              link.active 
                ? 'bg-blue-600 text-white cursor-not-allowed' 
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
/* Custom styles for video lightbox */
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