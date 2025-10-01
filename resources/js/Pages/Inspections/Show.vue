<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  inspection: Object,
});

// Lightbox refs
const visibleRef = ref(false);
const indexRef = ref(0);
const imgsRef = ref([]);

function back() {
  router.visit('/inspections');
}

function addFinding() {
  router.visit(`/inspections/${props.inspection.id}/add-finding`);
}

function completeInspection() {
  router.patch(route('inspections.complete', props.inspection.id));
}

// Lightbox functions
function showLightbox(images, index = 0) {
  imgsRef.value = images;
  indexRef.value = index;
  visibleRef.value = true;
}

function hideLightbox() {
  visibleRef.value = false;
}
</script>

<template>
  <AppLayout title="Inspection Details">
    <div class="w-full py-8 px-4">
      <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center gap-4">
            <button 
              @click="back"
              class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition"
            >
              <i class="fa-solid fa-arrow-left text-xl"></i>
            </button>
            <div>
              <h1 class="text-2xl font-bold text-gray-800">Inspection Details</h1>
              <p class="text-gray-600">{{ inspection.guidance?.title || 'Inspection' }}</p>
            </div>
          </div>
          
          <div class="flex items-center gap-3">
            <span :class="[
              'px-3 py-1 rounded-full text-sm font-semibold',
              inspection.status === 'Draft' 
                ? 'bg-yellow-100 text-yellow-800' 
                : 'bg-green-100 text-green-800'
            ]">
              {{ inspection.status }}
            </span>
          </div>
        </div>

        <!-- Inspection Info -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Inspection Information</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="text-sm font-medium text-gray-500">Outlet</label>
              <p class="text-gray-800 font-semibold">{{ inspection.outlet?.nama_outlet || '-' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Department</label>
              <p class="text-gray-800 font-semibold">{{ inspection.departemen }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Inspector</label>
              <p class="text-gray-800 font-semibold">{{ inspection.created_by_user?.nama_lengkap || '-' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Date</label>
              <p class="text-gray-800 font-semibold">{{ new Date(inspection.inspection_date).toLocaleDateString() }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Total Findings</label>
              <p class="text-gray-800 font-semibold">{{ inspection.total_findings }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Total Points</label>
              <p class="text-gray-800 font-semibold">{{ inspection.total_points }}</p>
            </div>
          </div>
          
          <!-- Auditees Section -->
          <div v-if="inspection.auditees && inspection.auditees.length > 0" class="mt-6 pt-6 border-t border-gray-200">
            <h4 class="text-md font-semibold text-gray-800 mb-3">Auditees</h4>
            <div class="flex flex-wrap gap-2">
              <span 
                v-for="auditee in inspection.auditees" 
                :key="auditee.id"
                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800"
              >
                <i class="fa-solid fa-user mr-2"></i>
                {{ auditee.nama_lengkap }}
              </span>
            </div>
          </div>
        </div>

        <!-- Findings -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800">Findings</h3>
            <button
              v-if="inspection.status === 'Draft'"
              @click="addFinding"
              class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition"
            >
              <i class="fa-solid fa-plus mr-2"></i>
              Add Finding
            </button>
          </div>

          <div v-if="inspection.details && inspection.details.length > 0" class="space-y-4">
            <div 
              v-for="(detail, index) in inspection.details" 
              :key="detail.id"
              class="border border-gray-200 rounded-xl p-4"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-3 mb-2">
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                      Finding #{{ index + 1 }}
                    </span>
                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                      {{ detail.point }} Points
                    </span>
                  </div>
                  
                  <div class="space-y-2">
                    <div>
                      <label class="text-sm font-medium text-gray-500">Category</label>
                      <p class="text-gray-800">{{ detail.category?.categories || '-' }}</p>
                    </div>
                    <div>
                      <label class="text-sm font-medium text-gray-500">Parameter Pemeriksaan</label>
                      <p class="text-gray-800">{{ detail.parameter_pemeriksaan }}</p>
                    </div>
                    <div>
                      <label class="text-sm font-medium text-gray-500">Parameter</label>
                      <p class="text-gray-800">{{ detail.parameter?.parameter || '-' }}</p>
                    </div>
                    <div v-if="detail.notes">
                      <label class="text-sm font-medium text-gray-500">Notes</label>
                      <p class="text-gray-800">{{ detail.notes }}</p>
                    </div>
                  </div>
                </div>
                
                <!-- Photos -->
                <div v-if="detail.photo_paths && detail.photo_paths.length > 0" class="ml-4">
                  <div class="flex gap-2">
                    <img 
                      v-for="(photo, photoIndex) in detail.photo_paths.slice(0, 3)" 
                      :key="photoIndex"
                      :src="`/storage/${photo}`"
                      :alt="`Photo ${photoIndex + 1}`"
                      class="w-16 h-16 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-80 transition"
                      @click="showLightbox(detail.photo_paths.map(p => `/storage/${p}`), photoIndex)"
                    />
                    <div 
                      v-if="detail.photo_paths.length > 3" 
                      class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-xs text-gray-500 cursor-pointer hover:bg-gray-200 transition"
                      @click="showLightbox(detail.photo_paths.map(p => `/storage/${p}`), 3)"
                    >
                      +{{ detail.photo_paths.length - 3 }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-8 text-gray-500">
            <i class="fa-solid fa-camera text-4xl mb-2"></i>
            <p>No findings yet</p>
            <p class="text-sm">Start adding findings to this inspection</p>
          </div>
        </div>

        <!-- Actions -->
        <div v-if="inspection.status === 'Draft'" class="mt-8 flex justify-end">
          <button
            @click="completeInspection"
            class="px-6 py-3 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition"
          >
            <i class="fa-solid fa-check mr-2"></i>
            Complete Inspection
          </button>
        </div>
      </div>
    </div>
    
    <!-- Vue Easy Lightbox -->
    <vue-easy-lightbox
      :visible="visibleRef"
      :imgs="imgsRef"
      :index="indexRef"
      @hide="hideLightbox"
    />
  </AppLayout>
</template>
