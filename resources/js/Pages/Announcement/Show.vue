<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import VueEasyLightbox from 'vue-easy-lightbox'

const props = defineProps({
  announcement: Object,
})

// Lightbox state
const lightboxVisible = ref(false)
const lightboxImages = ref([])
const lightboxIndex = ref(0)

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  return d.toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function getTargetIcon(targetType) {
  const icons = {
    user: 'fa fa-user',
    jabatan: 'fa fa-id-badge',
    divisi: 'fa fa-building',
    level: 'fa fa-layer-group',
    outlet: 'fa fa-store'
  };
  return icons[targetType] || 'fa fa-users';
}

// Get initials from name
function getInitials(name) {
  if (!name) return 'U'
  
  const words = name.trim().split(' ')
  if (words.length === 1) {
    return words[0].charAt(0).toUpperCase()
  } else {
    return (words[0].charAt(0) + words[words.length - 1].charAt(0)).toUpperCase()
  }
}

// Open avatar lightbox
function openAvatarLightbox(imageUrl) {
  lightboxImages.value = [imageUrl]
  lightboxIndex.value = 0
  lightboxVisible.value = true
}
</script>

<template>
  <AppLayout>
    <Head :title="announcement?.title || 'Detail Announcement'" />
    
    <div class="max-w-4xl mx-auto py-8 px-4">
      <!-- Header -->
      <div class="mb-6">
        <Link 
          :href="route('home')" 
          class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4 transition-colors"
        >
          <i class="fa fa-arrow-left mr-2"></i>
          Kembali ke Beranda
        </Link>
        
        <div class="bg-gradient-to-r from-blue-500 to-blue-700 rounded-2xl p-6 text-white">
          <div class="flex items-center gap-3 mb-4">
            <i class="fa-solid fa-bullhorn text-3xl"></i>
            <div class="flex-1">
              <h1 class="text-2xl font-bold">{{ announcement?.title }}</h1>
              <div class="flex items-center gap-4 mt-2">
                <div class="flex items-center gap-2">
                  <!-- Avatar User Pembuat -->
                  <div v-if="announcement?.creator_avatar" class="w-6 h-6 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform" @click="openAvatarLightbox(`/storage/${announcement.creator_avatar}`)">
                    <img 
                      :src="`/storage/${announcement.creator_avatar}`" 
                      :alt="announcement.creator_name"
                      class="w-full h-full object-cover"
                    />
                  </div>
                  <div v-else class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                    {{ getInitials(announcement?.creator_name) }}
                  </div>
                  <span class="text-blue-100">{{ announcement?.creator_name || 'Unknown' }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <i class="fa fa-calendar text-blue-200"></i>
                  <span class="text-blue-100">{{ formatDate(announcement?.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="flex items-center gap-4">
            <span :class="announcement?.status === 'Publish' ? 'bg-green-500' : 'bg-gray-500'"
                  class="px-3 py-1 rounded-full text-sm font-semibold">
              <i :class="announcement?.status === 'Publish' ? 'fa fa-check' : 'fa fa-clock'" class="mr-1"></i>
              {{ announcement?.status }}
            </span>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Image Header -->
        <div v-if="announcement?.image_path" class="h-64 overflow-hidden">
          <img 
            :src="`/storage/${announcement.image_path}`" 
            :alt="announcement.title"
            class="w-full h-full object-cover"
          />
        </div>

        <!-- Main Content -->
        <div class="p-8">

          <!-- Content -->
          <div v-if="announcement?.content" class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa fa-file-text text-blue-500"></i>
              Isi Pengumuman
            </h3>
            <div class="bg-gray-50 rounded-xl p-6 border">
              <p class="text-gray-900 whitespace-pre-line leading-relaxed text-lg">{{ announcement.content }}</p>
            </div>
          </div>

          <!-- Files -->
          <div v-if="announcement?.files?.length" class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa fa-paperclip text-blue-500"></i>
              Lampiran File
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="f in announcement.files" :key="f.id" 
                   class="bg-gray-50 rounded-xl p-4 border hover:shadow-md transition-shadow">
                <a :href="`/storage/${f.file_path}`" 
                   target="_blank" 
                   class="flex items-center gap-4 text-blue-600 hover:text-blue-800 transition-colors">
                  <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fa fa-file text-xl text-blue-600"></i>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium">{{ f.file_name }}</p>
                    <p class="text-sm text-gray-500">Klik untuk download</p>
                  </div>
                  <i class="fa fa-external-link-alt"></i>
                </a>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="!announcement?.content && !announcement?.files?.length" class="text-center py-12">
            <i class="fa fa-info-circle text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-500">Tidak ada konten tambahan untuk pengumuman ini.</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-8 flex justify-center">
        <Link 
          :href="route('home')" 
          class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-xl font-semibold transition-colors"
        >
          <i class="fa fa-home mr-2"></i>
          Kembali ke Beranda
        </Link>
      </div>
    </div>

    <!-- Lightbox for Avatar Images -->
    <VueEasyLightbox
      :visible="lightboxVisible"
      :imgs="lightboxImages"
      :index="lightboxIndex"
      @hide="lightboxVisible = false"
    />
  </AppLayout>
</template>

<style scoped>
/* Custom styles if needed */
</style>
