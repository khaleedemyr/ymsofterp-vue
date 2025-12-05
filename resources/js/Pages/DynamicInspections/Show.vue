<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-blue-800 flex items-center gap-3">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> 
          Outlet/HO Inspection Detail
        </h1>
        <div class="flex gap-2">
          <a 
            :href="route('dynamic-inspections.edit', inspection.id)"
            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center gap-2"
          >
            <i class="fa-solid fa-edit"></i>
            Edit
          </a>
          <a 
            :href="route('dynamic-inspections.index')"
            class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center gap-2"
          >
            <i class="fa-solid fa-arrow-left"></i>
            Kembali
          </a>
        </div>
      </div>

      <!-- Inspection Information -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-info-circle text-blue-600"></i>
          Inspection Information
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <div class="space-y-3">
              <div>
                <label class="text-sm font-medium text-gray-500">Inspection Number</label>
                <p class="text-lg font-semibold text-gray-800">{{ inspection.inspection_number }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Outlet</label>
                <p class="text-lg font-semibold text-gray-800">{{ inspection.outlet?.nama_outlet || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Tanggal Inspection</label>
                <p class="text-lg font-semibold text-gray-800">{{ formatDate(inspection.inspection_date) }}</p>
              </div>
            </div>
          </div>

          <div>
            <div class="space-y-3">
              <div>
                <label class="text-sm font-medium text-gray-500">PIC</label>
                <p class="text-lg font-semibold text-gray-800">{{ inspection.pic_name }}</p>
                <p class="text-sm text-gray-600">{{ inspection.pic_position }} - {{ inspection.pic_division }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Outlet Leader</label>
                <p class="text-lg font-semibold text-gray-800">{{ inspection.outlet_leader || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Status</label>
                <span :class="[
                  'px-3 py-1 text-sm font-medium rounded-full',
                  inspection.status === 'completed' 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-yellow-100 text-yellow-800'
                ]">
                  {{ inspection.status === 'completed' ? 'Completed' : 'Draft' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- General Notes -->
        <div v-if="inspection.general_notes" class="mt-6">
          <label class="text-sm font-medium text-gray-500">Catatan/Komentar</label>
          <div class="mt-2 p-4 bg-gray-50 rounded-lg">
            <p class="text-gray-800 whitespace-pre-wrap">{{ inspection.general_notes }}</p>
          </div>
        </div>
      </div>

      <!-- Inspection Details by Subject -->
      <div v-if="groupedDetails.length > 0" class="space-y-6">
        <div 
          v-for="group in groupedDetails" 
          :key="group.subject_id"
          class="bg-white rounded-xl shadow-lg p-6"
        >
          <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list-check text-blue-600"></i>
            {{ group.subject_name }}
          </h3>

          <div class="space-y-4">
            <div 
              v-for="detail in group.items" 
              :key="detail.id"
              class="border border-gray-200 rounded-lg p-4"
            >
              <div class="flex items-start gap-3 mb-3">
                <div class="flex-shrink-0 mt-1">
                  <i :class="[
                    'fa-solid text-lg',
                    detail.is_checked ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600'
                  ]"></i>
                </div>
                <div class="flex-1">
                  <div class="font-medium text-gray-800">
                    {{ detail.subjectItem?.name || detail.subject_item?.name || 'Unknown Item' }}
                  </div>
                  <div v-if="detail.subjectItem?.description || detail.subject_item?.description" class="text-sm text-gray-600 mt-1">
                    {{ detail.subjectItem?.description || detail.subject_item?.description }}
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div v-if="detail.notes" class="mb-3">
                <label class="text-sm font-medium text-gray-500">Notes</label>
                <div class="mt-1 p-3 bg-gray-50 rounded-lg">
                  <p class="text-gray-800 whitespace-pre-wrap">{{ detail.notes }}</p>
                </div>
              </div>

              <!-- Documentation -->
              <div v-if="detail.documentation_paths && detail.documentation_paths.length > 0" class="mb-3">
                <label class="text-sm font-medium text-gray-500">Dokumentasi ({{ detail.documentation_paths.length }} files)</label>
                <div class="mt-2 flex gap-2 flex-wrap">
                  <img 
                    v-for="(doc, index) in detail.documentation_paths" 
                    :key="index"
                    :src="`/storage/${doc}`"
                    :alt="`Documentation ${index + 1}`"
                    class="w-20 h-20 object-cover rounded-lg border border-gray-200 cursor-pointer hover:shadow-lg transition"
                    @click="showLightbox(detail.documentation_paths.map(d => `/storage/${d}`), index)"
                    @error="console.error('Image failed to load:', `/storage/${doc}`, 'Doc value:', doc)"
                    @load="console.log('Image loaded successfully:', `/storage/${doc}`, 'Doc value:', doc)"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No Details Message -->
      <div v-else class="bg-white rounded-xl shadow-lg p-6 text-center">
        <i class="fa-solid fa-clipboard-list text-gray-400 text-4xl mb-4"></i>
        <h3 class="text-lg font-medium text-gray-600 mb-2">Tidak ada detail inspection</h3>
        <p class="text-gray-500">Belum ada item yang di-inspect untuk inspection ini.</p>
      </div>

      <!-- Lightbox Modal -->
      <div v-if="showLightboxModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeLightbox">
        <div class="relative max-w-4xl max-h-full p-4">
          <button 
            @click="closeLightbox"
            class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 z-10"
          >
            <i class="fa-solid fa-times"></i>
          </button>
          <img 
            :src="lightboxImages[currentImageIndex]"
            :alt="`Image ${currentImageIndex + 1}`"
            class="max-w-full max-h-full object-contain"
          />
          <div v-if="lightboxImages.length > 1" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2">
            <button 
              @click="previousImage"
              class="bg-white bg-opacity-20 text-white px-3 py-1 rounded hover:bg-opacity-30"
            >
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span class="bg-white bg-opacity-20 text-white px-3 py-1 rounded">
              {{ currentImageIndex + 1 }} / {{ lightboxImages.length }}
            </span>
            <button 
              @click="nextImage"
              class="bg-white bg-opacity-20 text-white px-3 py-1 rounded hover:bg-opacity-30"
            >
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  inspection: Object
});

const showLightboxModal = ref(false);
const lightboxImages = ref([]);
const currentImageIndex = ref(0);

// Group details by subject
const groupedDetails = computed(() => {
  const groups = {};
  
  props.inspection.details.forEach(detail => {
    const subjectId = detail.inspection_subject_id;
    const subjectName = detail.subject?.name || detail.subject_name || 'Unknown Subject';
    
    // Debug documentation_paths
    console.log('Detail data:', {
      id: detail.id,
      documentation_paths: detail.documentation_paths,
      type: typeof detail.documentation_paths,
      isArray: Array.isArray(detail.documentation_paths),
      length: detail.documentation_paths?.length
    });
    
    if (!groups[subjectId]) {
      groups[subjectId] = {
        subject_id: subjectId,
        subject_name: subjectName,
        items: []
      };
    }
    
    groups[subjectId].items.push(detail);
  });
  
  return Object.values(groups);
});

// Format date
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};

// Show lightbox
const showLightbox = (images, index) => {
  lightboxImages.value = images;
  currentImageIndex.value = index;
  showLightboxModal.value = true;
};

// Close lightbox
const closeLightbox = () => {
  showLightboxModal.value = false;
  lightboxImages.value = [];
  currentImageIndex.value = 0;
};

// Navigate images
const previousImage = () => {
  if (currentImageIndex.value > 0) {
    currentImageIndex.value--;
  }
};

const nextImage = () => {
  if (currentImageIndex.value < lightboxImages.value.length - 1) {
    currentImageIndex.value++;
  }
};
</script>
