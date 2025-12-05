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

        <!-- CPA Section -->
        <div v-if="inspection.cpas && inspection.cpas.length > 0" class="mt-8">
          <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center gap-3 mb-6">
              <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-clipboard-list text-purple-600"></i>
              </div>
              <div>
                <h3 class="text-xl font-bold text-gray-800">Corrective and Preventive Action (CPA)</h3>
                <p class="text-sm text-gray-500">{{ inspection.cpas.length }} CPA record(s) found</p>
              </div>
            </div>

            <div class="space-y-6">
              <div 
                v-for="(cpa, index) in inspection.cpas" 
                :key="cpa.id"
                class="border border-gray-200 rounded-xl p-6 bg-gray-50"
              >
                <!-- CPA Header -->
                <div class="flex items-start justify-between mb-4">
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 mb-2">CPA #{{ index + 1 }}</h4>
                    <div class="flex items-center gap-4 text-sm text-gray-600">
                      <span>
                        <i class="fa-solid fa-user mr-1"></i>
                        {{ cpa.responsible_person }}
                      </span>
                      <span>
                        <i class="fa-solid fa-calendar mr-1"></i>
                        Due: {{ new Date(cpa.due_date).toLocaleDateString() }}
                      </span>
                      <span :class="[
                        'px-2 py-1 rounded-full text-xs font-medium',
                        cpa.status === 'Completed' ? 'bg-green-100 text-green-800' : 
                        cpa.status === 'Open' ? 'bg-yellow-100 text-yellow-800' : 
                        'bg-red-100 text-red-800'
                      ]">
                        {{ cpa.status }}
                      </span>
                    </div>
                  </div>
                  <div class="text-sm text-gray-500">
                    <i class="fa-solid fa-clock mr-1"></i>
                    {{ new Date(cpa.created_at).toLocaleDateString() }}
                  </div>
                </div>

                <!-- Associated Finding Details -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                  <div class="flex items-center gap-2 mb-3">
                    <i class="fa-solid fa-search text-blue-600"></i>
                    <h5 class="font-semibold text-blue-800">Associated Finding Details</h5>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                      <label class="font-medium text-blue-700">Category:</label>
                      <p class="text-gray-800">{{ cpa.inspection_detail?.category?.categories || '-' }}</p>
                    </div>
                    <div>
                      <label class="font-medium text-blue-700">Parameter Pemeriksaan:</label>
                      <p class="text-gray-800">{{ cpa.inspection_detail?.parameter_pemeriksaan || '-' }}</p>
                    </div>
                    <div>
                      <label class="font-medium text-blue-700">Parameter:</label>
                      <p class="text-gray-800">{{ cpa.inspection_detail?.parameter?.parameter || '-' }}</p>
                    </div>
                    <div>
                      <label class="font-medium text-blue-700">Finding Status:</label>
                      <span :class="[
                        'px-2 py-1 rounded-full text-xs font-medium',
                        cpa.inspection_detail?.status === 'Non-Compliance' ? 'bg-red-100 text-red-800' : 
                        cpa.inspection_detail?.status === 'Compliance' ? 'bg-green-100 text-green-800' : 
                        'bg-yellow-100 text-yellow-800'
                      ]">
                        {{ cpa.inspection_detail?.status || '-' }}
                      </span>
                    </div>
                  </div>

                  <div v-if="cpa.inspection_detail?.notes" class="mt-3">
                    <label class="font-medium text-blue-700">Finding Notes:</label>
                    <p class="text-gray-800 bg-white p-2 rounded border text-sm">{{ cpa.inspection_detail.notes }}</p>
                  </div>

                  <div v-if="cpa.inspection_detail?.photo_paths && cpa.inspection_detail.photo_paths.length > 0" class="mt-3">
                    <label class="font-medium text-blue-700">Finding Evidence:</label>
                    <div class="flex gap-2 mt-2">
                      <img 
                        v-for="(photo, pIndex) in cpa.inspection_detail.photo_paths" 
                        :key="pIndex"
                        :src="`/storage/${photo}`"
                        :alt="`Finding photo ${pIndex + 1}`"
                        class="w-16 h-16 object-cover rounded cursor-pointer hover:opacity-80 transition border border-gray-200"
                        @click="showLightbox(cpa.inspection_detail.photo_paths.map(p => `/storage/${p}`), pIndex)"
                      />
                    </div>
                  </div>

                  <div class="flex items-center justify-between mt-3 pt-3 border-t border-blue-200">
                    <div class="text-xs text-blue-600">
                      <span class="font-medium">Finding ID:</span> #{{ cpa.inspection_detail?.id || 'N/A' }}
                    </div>
                    <div class="text-xs text-blue-600">
                      <span class="font-medium">Inspector:</span> {{ cpa.inspection_detail?.created_by_user?.nama_lengkap || 'Unknown' }}
                    </div>
                  </div>
                </div>

                <!-- Action Plan -->
                <div class="mb-4">
                  <label class="text-sm font-medium text-gray-500 mb-1">Action Plan</label>
                  <p class="text-gray-800 bg-white p-3 rounded-lg border">{{ cpa.action_plan }}</p>
                </div>

                <!-- Notes -->
                <div v-if="cpa.notes" class="mb-4">
                  <label class="text-sm font-medium text-gray-500 mb-1">Notes</label>
                  <p class="text-gray-800 bg-white p-3 rounded-lg border">{{ cpa.notes }}</p>
                </div>

                <!-- Documentation -->
                <div v-if="cpa.documentation_paths && cpa.documentation_paths.length > 0" class="mb-4">
                  <label class="text-sm font-medium text-gray-500 mb-2">
                    Documentation ({{ cpa.documentation_paths.length }} files)
                  </label>
                  <div class="flex gap-2 flex-wrap">
                    <div 
                      v-for="(doc, docIndex) in cpa.documentation_paths" 
                      :key="docIndex"
                      class="relative group"
                    >
                      <img 
                        :src="`/storage/${doc}`"
                        :alt="`CPA Documentation ${docIndex + 1}`"
                        class="w-20 h-20 object-cover rounded-lg border border-gray-200 cursor-pointer hover:shadow-lg transition"
                        @click="showLightbox(cpa.documentation_paths.map(d => `/storage/${d}`), docIndex)"
                        @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex'"
                      />
                      <!-- Fallback jika gambar gagal dimuat -->
                      <div 
                        class="w-20 h-20 bg-gray-100 rounded-lg border border-gray-200 flex flex-col items-center justify-center text-xs text-gray-500 hidden"
                        @click="showLightbox(cpa.documentation_paths.map(d => `/storage/${d}`), docIndex)"
                      >
                        <i class="fa-solid fa-file-image text-lg mb-1"></i>
                        <span>Doc {{ docIndex + 1 }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- CPA Footer -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                  <div class="text-sm text-gray-500">
                    <span class="font-medium">Created by:</span> {{ cpa.created_by?.nama_lengkap || 'Unknown' }}
                  </div>
                  <div v-if="cpa.completion_date" class="text-sm text-green-600">
                    <i class="fa-solid fa-check-circle mr-1"></i>
                    Completed: {{ new Date(cpa.completion_date).toLocaleDateString() }}
                  </div>
                </div>
              </div>
            </div>
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
