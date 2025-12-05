<template>
  <div class="announcement-list h-full flex flex-col" :class="isNight ? 'text-white' : 'text-slate-800'">
    <!-- Header -->
    <div class="flex items-center justify-between mb-3 flex-shrink-0">
      <h3 class="text-base font-semibold flex items-center gap-2" :class="isNight ? 'text-white' : 'text-slate-800'">
        <i class="fas fa-bullhorn" :class="isNight ? 'text-indigo-400' : 'text-indigo-600'"></i>
        Pengumuman Terbaru
      </h3>
      <button 
        @click="$emit('show-all')"
        class="text-xs flex items-center gap-1"
        :class="isNight ? 'text-indigo-400 hover:text-indigo-300' : 'text-indigo-600 hover:text-indigo-800'"
      >
        Lihat Semua
        <i class="fas fa-arrow-right text-xs"></i>
      </button>
    </div>

    <!-- Content Area -->
    <div class="flex-1 min-h-0 overflow-hidden">
      <!-- Loading State -->
      <div v-if="loading" class="space-y-2 h-full overflow-y-auto">
        <div v-for="n in 3" :key="n" class="animate-pulse">
          <div class="rounded-lg p-3" :class="isNight ? 'bg-slate-700' : 'bg-gray-200'">
            <div class="h-3 rounded w-3/4 mb-2" :class="isNight ? 'bg-slate-600' : 'bg-gray-300'"></div>
            <div class="h-2 rounded w-1/2" :class="isNight ? 'bg-slate-600' : 'bg-gray-300'"></div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="announcements.length === 0" class="h-full flex items-center justify-center">
        <div class="text-center">
          <div class="mb-2" :class="isNight ? 'text-slate-400' : 'text-gray-400'">
            <i class="fas fa-bullhorn text-3xl"></i>
          </div>
          <p class="text-sm" :class="isNight ? 'text-slate-300' : 'text-gray-500'">Belum ada pengumuman untuk Anda</p>
        </div>
      </div>

      <!-- Announcements List -->
      <div v-else class="h-full overflow-y-auto space-y-2 pr-2">
        <div 
          v-for="announcement in announcements" 
          :key="announcement.id"
          class="rounded-lg border p-3 hover:shadow-lg transition-all duration-200 cursor-pointer backdrop-blur-sm"
          :class="[
            isNight ? 'bg-slate-700/80 border-slate-600 hover:bg-slate-700/90' : 'bg-white/80 border-slate-200 hover:bg-white/90'
          ]"
          @click="viewAnnouncement(announcement.id)"
        >
          <!-- Announcement Header -->
          <div class="flex items-start justify-between mb-2">
            <div class="flex-1 mr-2">
              <h4 class="font-medium text-sm line-clamp-1" :class="isNight ? 'text-white' : 'text-slate-900'">
                {{ announcement.title }}
              </h4>
              <div class="flex items-center gap-2 mt-1">
                <!-- Avatar User Pembuat -->
                <div v-if="announcement.creator_avatar" class="w-5 h-5 rounded-full overflow-hidden flex-shrink-0 cursor-pointer hover:scale-110 transition-transform" @click="openAvatarLightbox(`/storage/${announcement.creator_avatar}`)">
                  <img 
                    :src="`/storage/${announcement.creator_avatar}`" 
                    :alt="announcement.creator_name"
                    class="w-full h-full object-cover"
                  />
                </div>
                <div v-else class="w-5 h-5 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                  {{ getInitials(announcement.creator_name) }}
                </div>
                <span class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                  {{ announcement.creator_name || 'Unknown' }}
                </span>
              </div>
            </div>
            <span class="text-xs whitespace-nowrap" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
              {{ announcement.created_at_formatted }}
            </span>
          </div>

          <!-- Content Preview -->
          <p v-if="announcement.content" class="text-xs line-clamp-2 mb-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
            {{ announcement.content }}
          </p>

          <!-- Image Preview (Smaller) -->
          <div v-if="announcement.image_path" class="mb-2">
            <img 
              :src="`/storage/${announcement.image_path}`" 
              :alt="announcement.title"
              class="w-full h-20 object-cover rounded-lg"
            />
          </div>

          <!-- Files Indicator -->
          <div v-if="announcement.files && announcement.files.length > 0" class="flex items-center gap-1 mb-2">
            <i class="fas fa-paperclip text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-400'"></i>
            <span class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
              {{ announcement.files.length }} file
            </span>
          </div>

          <!-- Target Info -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1">
              <i class="fas fa-users text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-400'"></i>
              <span class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                {{ getTargetNames(announcement.targets) }}
              </span>
            </div>
            <i class="fas fa-chevron-right text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-400'"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Lightbox for Avatar Images -->
  <VueEasyLightbox
    :visible="lightboxVisible"
    :imgs="lightboxImages"
    :index="lightboxIndex"
    @hide="lightboxVisible = false"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'
import VueEasyLightbox from 'vue-easy-lightbox'

const props = defineProps({
  isNight: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['show-all'])

const announcements = ref([])
const loading = ref(true)

// Lightbox state
const lightboxVisible = ref(false)
const lightboxImages = ref([])
const lightboxIndex = ref(0)

// Fetch user announcements
const fetchAnnouncements = async () => {
  try {
    loading.value = true
    const response = await axios.get('/api/user-announcements')
    announcements.value = response.data.announcements || []
  } catch (error) {
    console.error('Error fetching announcements:', error)
    announcements.value = []
  } finally {
    loading.value = false
  }
}

// Get target names for display
const getTargetNames = (targets) => {
  if (!targets || targets.length === 0) return 'Semua'
  
  const names = targets.map(target => target.target_name).filter(Boolean)
  if (names.length === 0) return 'Semua'
  
  if (names.length <= 2) {
    return names.join(', ')
  } else {
    return `${names.slice(0, 2).join(', ')} dan ${names.length - 2} lainnya`
  }
}

// Get initials from name
const getInitials = (name) => {
  if (!name) return 'U'
  
  const words = name.trim().split(' ')
  if (words.length === 1) {
    return words[0].charAt(0).toUpperCase()
  } else {
    return (words[0].charAt(0) + words[words.length - 1].charAt(0)).toUpperCase()
  }
}

// Open avatar lightbox
const openAvatarLightbox = (imageUrl) => {
  lightboxImages.value = [imageUrl]
  lightboxIndex.value = 0
  lightboxVisible.value = true
}

// View announcement detail
const viewAnnouncement = (id) => {
  router.visit(`/announcement/${id}`)
}

onMounted(() => {
  fetchAnnouncements()
})
</script>

<style scoped>
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Custom scrollbar */
.overflow-y-auto::-webkit-scrollbar {
  width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}
</style>
