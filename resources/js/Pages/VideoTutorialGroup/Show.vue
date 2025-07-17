<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  group: Object,
});

function goBack() {
  router.visit('/video-tutorial-groups');
}

function openEdit() {
  router.visit(`/video-tutorial-groups/${props.group.id}/edit`);
}

function openVideoCreate() {
  router.visit('/video-tutorials/create');
}

function openVideoDetail(video) {
  router.visit(`/video-tutorials/${video.id}`);
}
</script>

<template>
  <AppLayout title="Detail Group Video Tutorial">
    <div class="max-w-6xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-folder text-green-500"></i> Detail Group Video Tutorial
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
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Group Information -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <h2 class="text-xl font-semibold text-gray-800 mb-4">Informasi Group</h2>
          
          <div class="space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Nama Group</label>
              <p class="text-lg font-semibold text-gray-900">{{ group.name }}</p>
            </div>
            
            <!-- Description -->
            <div v-if="group.description">
              <label class="block text-sm font-medium text-gray-500 mb-1">Deskripsi</label>
              <p class="text-gray-900">{{ group.description }}</p>
            </div>
            
            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
              <span :class="[
                'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
                group.status === 'A' 
                  ? 'bg-green-100 text-green-800' 
                  : 'bg-red-100 text-red-800'
              ]">
                {{ group.status_text }}
              </span>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-blue-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-blue-600">{{ group.videos_count || 0 }}</div>
                <div class="text-sm text-blue-600">Total Video</div>
              </div>
              <div class="bg-green-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-green-600">{{ group.active_videos_count || 0 }}</div>
                <div class="text-sm text-green-600">Video Aktif</div>
              </div>
            </div>
            
            <!-- Created By -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Dibuat Oleh</label>
              <p class="text-gray-900">{{ group.creator?.name || '-' }}</p>
            </div>
            
            <!-- Created At -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Tanggal Dibuat</label>
              <p class="text-gray-900">{{ new Date(group.created_at).toLocaleString('id-ID') }}</p>
            </div>
            
            <!-- Updated At -->
            <div>
              <label class="block text-sm font-medium text-gray-500 mb-1">Terakhir Diupdate</label>
              <p class="text-gray-900">{{ new Date(group.updated_at).toLocaleString('id-ID') }}</p>
            </div>
          </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <h2 class="text-xl font-semibold text-gray-800 mb-4">Aksi Cepat</h2>
          
          <div class="space-y-4">
            <button @click="openVideoCreate" class="w-full bg-blue-600 text-white px-4 py-3 rounded-xl hover:bg-blue-700 transition flex items-center justify-center gap-2">
              <i class="fa-solid fa-plus"></i>
              Tambah Video Tutorial Baru
            </button>
            
            <div class="bg-gray-50 rounded-xl p-4">
              <h3 class="font-medium text-gray-800 mb-2">Statistik Group</h3>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-600">Total Video:</span>
                  <span class="font-medium">{{ group.videos_count || 0 }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Video Aktif:</span>
                  <span class="font-medium text-green-600">{{ group.active_videos_count || 0 }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Video Inaktif:</span>
                  <span class="font-medium text-red-600">{{ (group.videos_count || 0) - (group.active_videos_count || 0) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Videos List -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold text-gray-800">Daftar Video Tutorial</h2>
          <button @click="openVideoCreate" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition">
            <i class="fa-solid fa-plus mr-2"></i> Tambah Video
          </button>
        </div>
        
        <div v-if="group.videos && group.videos.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="video in group.videos" :key="video.id" class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition">
            <!-- Thumbnail -->
            <div class="aspect-video bg-gray-200 relative">
              <img v-if="video.thumbnail_url" :src="video.thumbnail_url" :alt="video.title" class="w-full h-full object-cover">
              <div v-else class="w-full h-full flex items-center justify-center">
                <i class="fa-solid fa-video text-4xl text-gray-400"></i>
              </div>
              <div class="absolute top-2 right-2">
                <span :class="[
                  'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                  video.status === 'A' 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-red-100 text-red-800'
                ]">
                  {{ video.status_text }}
                </span>
              </div>
            </div>
            
            <!-- Video Info -->
            <div class="p-4">
              <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">{{ video.title }}</h3>
              <p v-if="video.description" class="text-sm text-gray-600 mb-3 line-clamp-2">{{ video.description }}</p>
              
              <div class="flex justify-between items-center text-sm text-gray-500 mb-3">
                <span>{{ video.duration_formatted }}</span>
                <span>{{ video.video_size_formatted }}</span>
              </div>
              
              <button @click="openVideoDetail(video)" class="w-full bg-blue-100 text-blue-700 px-3 py-2 rounded-lg hover:bg-blue-200 transition text-sm">
                <i class="fa-solid fa-eye mr-1"></i> Lihat Detail
              </button>
            </div>
          </div>
        </div>
        
        <div v-else class="text-center py-12 text-gray-500">
          <i class="fa-solid fa-video text-4xl mb-4"></i>
          <p class="text-lg font-medium mb-2">Belum ada video tutorial</p>
          <p class="text-sm mb-4">Mulai dengan menambahkan video tutorial pertama ke group ini</p>
          <button @click="openVideoCreate" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition">
            <i class="fa-solid fa-plus mr-2"></i> Tambah Video Pertama
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style> 