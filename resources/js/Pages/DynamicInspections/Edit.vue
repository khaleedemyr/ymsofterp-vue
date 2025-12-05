<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-3xl font-bold mb-8 text-blue-800 flex items-center gap-3">
        <i class="fa-solid fa-clipboard-check text-blue-500"></i> Edit Outlet/HO Inspection
      </h1>

      <form @submit.prevent="submit" class="max-w-6xl mx-auto">
        <!-- Header Information -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-info-circle text-blue-600"></i>
            Inspection Information
          </h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Unit/Outlet *</label>
              <select 
                v-model="form.outlet_id" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="">Pilih Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Inspection *</label>
              <input 
                v-model="form.inspection_date" 
                type="date" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">PIC</label>
              <input 
                v-model="form.pic_name" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" 
                readonly
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
              <input 
                v-model="form.pic_position" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" 
                readonly
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Divisi</label>
              <input 
                v-model="form.pic_division" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" 
                readonly
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet Leader</label>
              <input 
                v-model="form.outlet_leader" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Masukkan nama outlet leader"
              />
            </div>
          </div>
        </div>

        <!-- Subject Selection -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list-check text-blue-600"></i>
            Pilih Subject Inspection
          </h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div 
              v-for="subject in subjects" 
              :key="subject.id"
              class="flex items-center space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50"
            >
              <input 
                :id="`subject-${subject.id}`"
                v-model="form.selected_subjects"
                :value="subject.id.toString()"
                type="checkbox"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label :for="`subject-${subject.id}`" class="flex-1 cursor-pointer">
                <div class="font-medium text-gray-900">{{ subject.name }}</div>
                <div v-if="subject.description" class="text-sm text-gray-500">{{ subject.description }}</div>
              </label>
            </div>
          </div>
        </div>

        <!-- Dynamic Form for Selected Subjects -->
        <div v-if="form.selected_subjects.length > 0" class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-list text-blue-600"></i>
            Inspection Details
          </h2>

          <div v-for="subjectId in form.selected_subjects" :key="subjectId" class="mb-8">
            <!-- Subject Card -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-4">
              <!-- Subject Header with Buttons -->
              <div class="bg-blue-50 border-b border-blue-200 rounded-t-lg p-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <h3 class="text-lg font-semibold text-blue-800">
                      {{ getSubjectName(subjectId) }}
                    </h3>
                    <!-- Save Status Indicator -->
                    <div v-if="subjectSaveStatus[subjectId]" class="flex items-center gap-2">
                      <span v-if="subjectSaveStatus[subjectId] === 'saving'" class="text-yellow-600 text-sm flex items-center gap-1">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        Saving...
                      </span>
                      <span v-else-if="subjectSaveStatus[subjectId] === 'saved'" class="text-green-600 text-sm flex items-center gap-1">
                        <i class="fa-solid fa-check-circle"></i>
                        Saved
                      </span>
                      <span v-else-if="subjectSaveStatus[subjectId] === 'error'" class="text-red-600 text-sm flex items-center gap-1">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        Error
                      </span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <!-- Add More Button -->
                    <button 
                      type="button"
                      @click="addSubjectInstance(subjectId)"
                      class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition flex items-center gap-1"
                    >
                      <i class="fa-solid fa-plus"></i>
                      Add More
                    </button>
                    
                    <!-- Save Subject Button -->
                    <button 
                      type="button"
                      @click="saveSubject(subjectId)"
                      :disabled="subjectSaveStatus[subjectId] === 'saving'"
                      class="px-3 py-1 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-1"
                    >
                      <i v-if="subjectSaveStatus[subjectId] === 'saving'" class="fa-solid fa-spinner fa-spin"></i>
                      <i v-else class="fa-solid fa-save"></i>
                      Save Subject
                    </button>
                  </div>
                </div>
              </div>

              <!-- Subject Instances -->
              <div v-for="(instance, instanceIndex) in getSubjectInstances(subjectId)" :key="instanceIndex" class="p-4 border-b border-gray-200 last:border-b-0">
                <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium text-gray-700">
                  {{ getSubjectName(subjectId) }} #{{ instanceIndex + 1 }}
                </h4>
                  <button 
                  v-if="getSubjectInstances(subjectId).length > 1"
                    type="button"
                    @click="removeSubjectInstance(subjectId, instanceIndex)"
                  class="text-red-600 hover:text-red-800 transition"
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>

              <div class="space-y-4">
                  <div 
                    v-for="item in getSubjectItems(subjectId)" 
                    :key="item.id"
                  class="border border-gray-200 rounded-lg p-4"
                  >
                  <div class="flex items-start gap-3 mb-3">
                      <input 
                        type="checkbox"
                      :id="`item-${subjectId}-${instanceIndex}-${item.id}`"
                      v-model="getItemData(subjectId, instanceIndex, item.id).is_checked"
                      class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 mt-1"
                    />
                    <label :for="`item-${subjectId}-${instanceIndex}-${item.id}`" class="flex-1 cursor-pointer">
                      <div class="font-medium text-gray-800">{{ item.name }}</div>
                      <div v-if="item.description" class="text-sm text-gray-600">{{ item.description }}</div>
                        </label>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                          <textarea 
                            v-model="getItemData(subjectId, instanceIndex, item.id).notes"
                            rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Tambahkan catatan..."
                          ></textarea>
                        </div>

                    <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2">Dokumentasi</label>
                      <div class="space-y-2">
                        <!-- Camera and Upload Buttons -->
                        <div class="flex gap-2">
                            <button 
                              type="button"
                              @click="openCamera(subjectId, instanceIndex, item.id)"
                            class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition flex items-center gap-1"
                            >
                              <i class="fa-solid fa-camera"></i>
                              Camera
                            </button>
                            <button 
                              type="button"
                              @click="openFileUpload(subjectId, instanceIndex, item.id)"
                            class="px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition flex items-center gap-1"
                            >
                              <i class="fa-solid fa-upload"></i>
                              Upload
                            </button>
                          </div>

                          <!-- Hidden file input -->
                          <input 
                            :data-file-input="`${subjectId}-${instanceIndex}-${item.id}`"
                            type="file" 
                            multiple
                            accept="image/*"
                            @change="handleFileUpload($event, subjectId, instanceIndex, item.id)"
                            class="hidden"
                          />

                          <!-- Thumbnails -->
                          <div v-if="getItemData(subjectId, instanceIndex, item.id).documentation_preview.length > 0" class="flex gap-2 flex-wrap">
                            <div 
                              v-for="(file, index) in getItemData(subjectId, instanceIndex, item.id).documentation_preview" 
                              :key="index"
                              class="relative group"
                            >
                              <img 
                                :src="file.preview" 
                                :alt="`Preview ${index + 1}`"
                                class="w-20 h-20 object-cover rounded-lg border border-gray-200 cursor-pointer hover:shadow-lg transition"
                                @click="showLightbox(getItemData(subjectId, instanceIndex, item.id).documentation_preview.map(f => f.preview), index)"
                              />
                              <button 
                                type="button"
                                @click="removeFile(subjectId, instanceIndex, item.id, index)"
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 transition"
                              >
                              ×
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
              </div>
            </div>
            </div> <!-- Close Subject Card -->
          </div>
        </div>

        <!-- General Notes -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-sticky-note text-blue-600"></i>
            Catatan/Komentar
          </h2>
          <textarea 
            v-model="form.general_notes"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            rows="4"
            placeholder="Tambahkan catatan umum atau komentar..."
          ></textarea>
        </div>

        <!-- Submit Buttons -->
        <div class="flex gap-4 justify-end">
          <a 
            :href="route('dynamic-inspections.show', inspection.id)"
            class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Cancel
          </a>
          <button 
            type="button" 
            @click="submit"
            :disabled="form.selected_subjects.length === 0"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <i class="fa-solid fa-check"></i>
            Selesai
          </button>
        </div>
      </form>

      <!-- Camera Modal -->
      <CameraModal 
        v-if="showCameraModal"
        :is-open="showCameraModal"
        @close="closeCamera"
        @capture="onPhotoCapture"
      />

      <!-- Lightbox Modal -->
      <div v-if="showLightboxModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeLightbox">
        <div class="relative max-w-4xl max-h-full p-4">
          <button 
            @click="closeLightbox"
            class="absolute -top-4 -right-4 bg-white rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors z-10"
          >
            <i class="fa-solid fa-times text-gray-600"></i>
          </button>
          <img 
            :src="lightboxImages[currentImageIndex]" 
            :alt="`Image ${currentImageIndex + 1}`"
            class="max-w-full max-h-full object-contain rounded-lg shadow-2xl"
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
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CameraModal from '@/Components/CameraModal.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  inspection: Object,
  outlets: Array,
  subjects: Array
});

// Form data - using ref like QA guidance
const form = ref({
  outlet_id: props.inspection.outlet_id || '',
  inspection_date: props.inspection.inspection_date ? props.inspection.inspection_date.split(' ')[0] : '',
  pic_name: props.inspection.pic_name || '',
  pic_position: props.inspection.pic_position || '',
  pic_division: props.inspection.pic_division || '',
  outlet_leader: props.inspection.outlet_leader || '',
  selected_subjects: props.inspection.details ? [...new Set(props.inspection.details.map(d => d.inspection_subject_id.toString()))] : [],
  general_notes: props.inspection.general_notes || '',
  details: {},
  subject_instances: {}
});

const errors = ref({});
const isSubmitting = ref(false);
const subjectSaveStatus = ref({}); // Track save status for each subject

// Initialize subjectSaveStatus for existing subjects
if (props.inspection.details && Array.isArray(props.inspection.details)) {
  const existingSubjectIds = [...new Set(props.inspection.details.map(d => d.inspection_subject_id.toString()))];
  existingSubjectIds.forEach(subjectId => {
    subjectSaveStatus.value[subjectId] = null;
  });
}

// Lightbox functionality
const showLightboxModal = ref(false);
const lightboxImages = ref([]);
const currentImageIndex = ref(0);

// Camera functionality
const showCameraModal = ref(false);
const currentFindingId = ref('');

// Initialize form with existing data
const initializeForm = () => {
  console.log('=== INITIALIZING FORM ===');
  console.log('Props inspection:', props.inspection);
  console.log('Props subjects:', props.subjects);
  
  // Initialize form structure
  form.value.details = {};
  form.value.subject_instances = {};
  
  // Process details if they exist
  if (props.inspection.details && Array.isArray(props.inspection.details) && props.inspection.details.length > 0) {
    console.log('Processing details:', props.inspection.details.length);
    
    // Get unique subject IDs
    const subjectIds = [...new Set(props.inspection.details.map(d => d.inspection_subject_id.toString()))];
    form.value.selected_subjects = subjectIds;
    
    console.log('Selected subjects:', form.value.selected_subjects);
    
    // Initialize details structure
    subjectIds.forEach(subjectId => {
      form.value.subject_instances[subjectId] = [0];
      form.value.details[subjectId] = {};
      
      // Initialize subject save status for existing subjects
      if (!subjectSaveStatus.value[subjectId]) {
        subjectSaveStatus.value[subjectId] = null;
      }
      
      // Get details for this subject
      const subjectDetails = props.inspection.details.filter(d => d.inspection_subject_id == subjectId);
      
      subjectDetails.forEach(detail => {
        const key = `${subjectId}_0_${detail.inspection_subject_item_id}`;
        
        // Handle documentation paths
        const documentationPreview = [];
        if (detail.documentation_paths && Array.isArray(detail.documentation_paths)) {
          detail.documentation_paths.forEach(path => {
            documentationPreview.push({
              file: null,
              preview: `/storage/${path}`,
              isExisting: true,
              path: path
            });
          });
        }
        
        form.value.details[subjectId][key] = {
          subject_id: parseInt(subjectId),
          item_id: parseInt(detail.inspection_subject_item_id),
          is_checked: detail.is_checked || false,
          notes: detail.notes || '',
          documentation: [],
          documentation_preview: documentationPreview
        };
      });
    });
    
    console.log('Form details initialized:', form.value.details);
  } else {
    console.log('No details found');
  }
  
  console.log('=== FORM INITIALIZATION COMPLETE ===');
  console.log('Final form state:', form.value);
};

// Helper functions
const getSubjectName = (subjectId) => {
  console.log('getSubjectName called with:', subjectId, 'type:', typeof subjectId);
  console.log('Available subjects:', props.subjects.map(s => ({ id: s.id, name: s.name, type: typeof s.id })));
  const subject = props.subjects.find(s => s.id == subjectId);
  console.log('Found subject:', subject);
  return subject ? subject.name : 'Unknown Subject';
};

const getSubjectItems = (subjectId) => {
  // Get the subject and its items
  const subject = props.subjects.find(s => s.id == subjectId);
  if (!subject) return [];
  
  // Always return all items for the subject (like in create form)
    return subject.items || [];
};

const getSubjectInstances = (subjectId) => {
  // Initialize subject_instances if not exists
  if (!form.value.subject_instances[subjectId]) {
    form.value.subject_instances[subjectId] = [0];
  }
  
  return form.value.subject_instances[subjectId];
};

const getItemData = (subjectId, instanceIndex, itemId) => {
  const key = `${subjectId}_${instanceIndex}_${itemId}`;
  
  // Initialize subject details if not exists
  if (!form.value.details[subjectId]) {
    form.value.details[subjectId] = {};
  }
  
  // Return existing data if available
  if (form.value.details[subjectId][key]) {
    return form.value.details[subjectId][key];
  }
  
  // Create new item data
  let itemData = {
    subject_id: parseInt(subjectId),
    item_id: parseInt(itemId),
    is_checked: false,
    notes: '',
    documentation: [],
    documentation_preview: []
  };
  
  // Load existing data for instance 0 (original data)
  if (instanceIndex === 0 && props.inspection.details) {
    const existingDetail = props.inspection.details.find(detail => 
      detail.inspection_subject_id == subjectId && detail.inspection_subject_item_id == itemId
    );
    
    if (existingDetail) {
      let documentationPreview = [];
      if (existingDetail.documentation_paths && Array.isArray(existingDetail.documentation_paths)) {
        documentationPreview = existingDetail.documentation_paths.map(path => ({
          file: null,
          preview: `/storage/${path}`,
          isExisting: true,
          path: path
        }));
      }
      
      itemData = {
        subject_id: parseInt(subjectId),
        item_id: parseInt(itemId),
        is_checked: existingDetail.is_checked || false,
        notes: existingDetail.notes || '',
        documentation: [],
        documentation_preview: documentationPreview
      };
    }
  }
  
  // For new instances (instanceIndex > 0), create fresh data
  if (instanceIndex > 0) {
    itemData = {
      subject_id: parseInt(subjectId),
      item_id: parseInt(itemId),
      is_checked: false,
      notes: '',
      documentation: [],
      documentation_preview: []
    };
  }
  
  form.value.details[subjectId][key] = itemData;
  return form.value.details[subjectId][key];
};

const addSubjectInstance = (subjectId) => {
  // Initialize subject_instances if not exists
  if (!form.value.subject_instances[subjectId]) {
    form.value.subject_instances[subjectId] = [0];
  }
  
  // Add new instance index
  const newInstanceIndex = form.value.subject_instances[subjectId].length;
  form.value.subject_instances[subjectId].push(newInstanceIndex);
};

const removeSubjectInstance = (subjectId, instanceIndex) => {
  // Remove from subject_instances
  if (form.value.subject_instances[subjectId]) {
    form.value.subject_instances[subjectId].splice(instanceIndex, 1);
  }
  
  // Clean up data for removed instance
  if (form.value.details[subjectId]) {
    Object.keys(form.value.details[subjectId]).forEach(key => {
      if (key.includes(`_${instanceIndex}_`)) {
        delete form.value.details[subjectId][key];
      }
    });
  }
};

// File handling
const handleFileUpload = (event, subjectId, instanceIndex, itemId) => {
  const files = Array.from(event.target.files);
  const itemData = getItemData(subjectId, instanceIndex, itemId);
  
  files.forEach(file => {
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => {
        itemData.documentation.push(file);
        itemData.documentation_preview.push({
          file: file,
          preview: e.target.result
        });
      };
      reader.readAsDataURL(file);
    }
  });
  
  // Clear the input
  event.target.value = '';
};

const removeFile = (subjectId, instanceIndex, itemId, index) => {
  const itemData = getItemData(subjectId, instanceIndex, itemId);
  itemData.documentation.splice(index, 1);
  itemData.documentation_preview.splice(index, 1);
};

const openFileUpload = (subjectId, instanceIndex, itemId) => {
  const input = document.querySelector(`[data-file-input="${subjectId}-${instanceIndex}-${itemId}"]`);
  if (input) {
    input.click();
  }
};

// Camera functionality
const openCamera = (subjectId, instanceIndex, itemId) => {
  currentFindingId.value = `${subjectId}-${instanceIndex}-${itemId}`;
  showCameraModal.value = true;
};

const closeCamera = () => {
  showCameraModal.value = false;
  currentFindingId.value = '';
};

const onPhotoCapture = (dataUrl) => {
  if (currentFindingId.value) {
    const [subjectId, instanceIndex, itemId] = currentFindingId.value.split('-');
    const itemData = getItemData(subjectId, instanceIndex, itemId);
    
    // Convert data URL to File
    const file = dataURLtoFile(dataUrl, `camera-capture-${Date.now()}.jpg`);
    
    itemData.documentation.push(file);
    itemData.documentation_preview.push({
      file: file,
      preview: dataUrl
    });
  }
  
  closeCamera();
};

// Lightbox functionality
const showLightbox = (images, index) => {
  lightboxImages.value = images;
  currentImageIndex.value = index;
  showLightboxModal.value = true;
};

const closeLightbox = () => {
  showLightboxModal.value = false;
  lightboxImages.value = [];
  currentImageIndex.value = 0;
};

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

// Utility function
const dataURLtoFile = (dataurl, filename) => {
  const arr = dataurl.split(',');
  const mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]);
  let n = bstr.length;
  const u8arr = new Uint8Array(n);
  while (n--) {
    u8arr[n] = bstr.charCodeAt(n);
  }
  return new File([u8arr], filename, { type: mime });
};

// Save individual subject
const saveSubject = async (subjectId) => {
  if (!form.value.outlet_id) {
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih outlet terlebih dahulu!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  if (!form.value.inspection_date) {
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih tanggal inspection terlebih dahulu!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  subjectSaveStatus.value[subjectId] = 'saving';

  try {
    // Prepare form data for this subject
    const formData = new FormData();
    formData.append('outlet_id', form.value.outlet_id);
    formData.append('inspection_date', form.value.inspection_date);
    formData.append('general_notes', form.value.general_notes || '');
    formData.append('outlet_leader', form.value.outlet_leader || '');
    formData.append('subject_id', subjectId);
    
    // Add details for this subject only - handle all instances
    const details = [];
    const subjectData = form.value.details[subjectId];
    if (subjectData) {
      Object.values(subjectData).forEach(itemData => {
        // Only include items that have been checked or have notes/documentation
        if (itemData.is_checked || itemData.notes || (itemData.documentation && itemData.documentation.length > 0)) {
          details.push({
            subject_id: itemData.subject_id,
            item_id: itemData.item_id,
            is_checked: itemData.is_checked,
            notes: itemData.notes || '',
            documentation: itemData.documentation || []
          });
        }
      });
    }
    
    console.log('Subject data for', subjectId, ':', subjectData);
    console.log('Details to save:', details);
    console.log('General notes being sent:', form.value.general_notes);

  if (details.length === 0) {
      Swal.fire({
        title: 'Peringatan!',
        text: 'Tidak ada data untuk disimpan pada subject ini!',
        icon: 'warning',
        confirmButtonText: 'OK'
      });
      subjectSaveStatus.value[subjectId] = 'error';
    return;
  }

    // Add details as JSON
    formData.append('details', JSON.stringify(details));

    // Add files separately - handle file uploads
    details.forEach((detail, detailIndex) => {
      if (detail.documentation && detail.documentation.length > 0) {
        detail.documentation.forEach((file, fileIndex) => {
          if (file instanceof File) {
            formData.append(`details[${detailIndex}][documentation][${fileIndex}]`, file);
          }
        });
      }
    });

    console.log('Saving subject:', subjectId, 'with details:', details);

    const response = await fetch(route('dynamic-inspections.update-subject', props.inspection.id), {
      method: 'POST',
      body: formData,
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    });

    if (response.ok) {
      const result = await response.json();
      subjectSaveStatus.value[subjectId] = 'saved';
      
      // Show success message
      Swal.fire({
        title: 'Berhasil!',
        text: `Subject "${getSubjectName(subjectId)}" berhasil disimpan!`,
        icon: 'success',
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false
      });
    } else {
      const error = await response.json();
      subjectSaveStatus.value[subjectId] = 'error';
      Swal.fire({
        title: 'Error!',
        text: `Error saving subject: ${error.message || 'Unknown error'}`,
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error saving subject:', error);
    subjectSaveStatus.value[subjectId] = 'error';
    Swal.fire({
      title: 'Error!',
      text: 'Terjadi kesalahan saat menyimpan subject!',
      icon: 'error',
      confirmButtonText: 'OK'
    });
  }
};

// Submit form - now just saves remaining unsaved subjects
const submit = async () => {
  console.log('=== SUBMIT FORM CALLED ===');
  console.log('Form state:', form.value);
  console.log('Form outlet_id:', form.value.outlet_id);
  console.log('Form inspection_date:', form.value.inspection_date);
  console.log('Form selected_subjects:', form.value.selected_subjects);
  
  // Validate required fields
  if (!form.value.outlet_id) {
    console.log('Validation failed: outlet_id is empty');
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih outlet terlebih dahulu!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  if (!form.value.inspection_date) {
    console.log('Validation failed: inspection_date is empty');
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih tanggal inspection terlebih dahulu!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  if (form.value.selected_subjects.length === 0) {
    console.log('Validation failed: selected_subjects is empty');
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih minimal satu subject untuk inspection!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }

  // Check if all subjects are saved
  const unsavedSubjects = form.value.selected_subjects.filter(subjectId => 
    subjectSaveStatus.value[subjectId] !== 'saved'
  );

  if (unsavedSubjects.length > 0) {
    const subjectNames = unsavedSubjects.map(id => getSubjectName(id)).join(', ');
    Swal.fire({
      title: 'Konfirmasi',
      text: `Masih ada subject yang belum disimpan: ${subjectNames}. Lanjutkan?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, Lanjutkan',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        // Complete the inspection
        completeInspection();
      }
    });
    return;
  }

  // Complete the inspection
  completeInspection();
};

// Complete inspection function
const completeInspection = async () => {
  try {
    const response = await fetch(route('dynamic-inspections.complete', props.inspection.id), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        general_notes: form.value.general_notes,
        outlet_leader: form.value.outlet_leader
      })
    });

    if (response.ok) {
      const result = await response.json();
      Swal.fire({
        title: 'Berhasil!',
        text: result.message,
        icon: 'success',
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false
      }).then(() => {
        // Redirect to index
        router.visit(route('dynamic-inspections.index'));
      });
    } else {
      const error = await response.json();
      Swal.fire({
        title: 'Error!',
        text: error.error || 'Gagal menyelesaikan inspection!',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error completing inspection:', error);
    Swal.fire({
      title: 'Error!',
      text: 'Terjadi kesalahan saat menyelesaikan inspection!',
      icon: 'error',
      confirmButtonText: 'OK'
    });
  }
};

// Watch for changes in selected subjects to initialize new subjects
watch(() => form.value.selected_subjects, (newSubjects, oldSubjects) => {
  // Find newly added subjects
  const newSubjectIds = newSubjects.filter(subjectId => !oldSubjects.includes(subjectId));
  
  newSubjectIds.forEach(subjectId => {
    // Initialize subject details if not exists
    if (!form.value.details[subjectId]) {
      form.value.details[subjectId] = {};
    }
    
    // Initialize subject instances if not exists
    if (!form.value.subject_instances[subjectId]) {
      form.value.subject_instances[subjectId] = [0];
    }
    
    // Initialize subject save status
    if (!subjectSaveStatus.value[subjectId]) {
      subjectSaveStatus.value[subjectId] = null;
    }
  });
}, { deep: true });

// Initialize on mount
onMounted(() => {
  console.log('Component mounted, initializing form...');
  console.log('Props received:', {
    inspection: props.inspection,
    outlets: props.outlets,
    subjects: props.subjects
  });
  nextTick(() => {
    initializeForm();
  });
});
</script>