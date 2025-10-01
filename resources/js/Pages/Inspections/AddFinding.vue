<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import LightboxModal from '@/Pages/Dashboard/LightboxModal.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  inspection: Object,
  guidance: Object,
  categories: Array,
  parameters: Array,
  existingInspections: Array,
});

const form = ref({
  category_id: '',
  parameter_pemeriksaan: '',
  parameter_id: '',
  point: 0,
  cleanliness_rating: '',
  photos: [],
  notes: '',
});

const errors = ref({});
const isSubmitting = ref(false);

// Camera and photo states
const showCameraModal = ref(false);
const showLightbox = ref(false);
const lightboxImage = ref(null);
const lightboxIndex = ref(0);
const selectedPhotos = ref([]);
const photoPreviews = ref([]);

// Computed properties
const inspectionMode = computed(() => {
  return props.inspection.inspection_mode || 'product';
});

const isCleanlinessMode = computed(() => {
  return inspectionMode.value === 'cleanliness';
});

const selectedCategory = computed(() => {
  return props.categories.find(cat => cat.id == form.value.category_id);
});

const availableParameters = computed(() => {
  if (!form.value.category_id || !props.guidance) return [];
  
  // Find guidance category that matches selected category
  const guidanceCategory = props.guidance.guidance_categories?.find(
    gc => gc.category_id == form.value.category_id
  );
  
  if (!guidanceCategory) return [];
  
  // Get parameters for this category - these are the "parameter pemeriksaan"
  return guidanceCategory.parameters || [];
});

const selectedParameterPemeriksaan = computed(() => {
  if (!form.value.parameter_pemeriksaan) return null;
  
  return availableParameters.value.find(
    param => param.parameter_pemeriksaan === form.value.parameter_pemeriksaan
  );
});

const availableParameterDetails = computed(() => {
  if (!selectedParameterPemeriksaan.value) return [];
  
  // Get details (actual parameters) from the selected parameter pemeriksaan
  return selectedParameterPemeriksaan.value.details || [];
});

const selectedParameterDetail = computed(() => {
  if (!form.value.parameter_id) return null;
  
  return availableParameterDetails.value.find(
    detail => detail.parameter_id == form.value.parameter_id
  );
});

// Watch for parameter changes to auto-set point
watch(() => form.value.parameter_id, (newParameterId) => {
  if (newParameterId && selectedParameterDetail.value) {
    if (isCleanlinessMode.value) {
      // In cleanliness mode, don't auto-set point, let user choose rating
      form.value.point = 0;
    } else {
      // In product mode, auto-set point
      form.value.point = selectedParameterDetail.value.point;
    }
  }
});

// Watch for cleanliness rating changes to calculate point
watch(() => form.value.cleanliness_rating, (newRating) => {
  if (newRating && selectedParameterDetail.value) {
    const basePoint = selectedParameterDetail.value.point;
    switch (newRating) {
      case 'Yes':
        form.value.point = basePoint;
        break;
      case 'No':
        form.value.point = -basePoint;
        break;
      case 'NA':
        form.value.point = 0;
        break;
    }
  }
});

// Watch for category changes to reset form
watch(() => form.value.category_id, () => {
  form.value.parameter_pemeriksaan = '';
  form.value.parameter_id = '';
  form.value.point = 0;
});

// Watch for parameter pemeriksaan changes to reset parameter
watch(() => form.value.parameter_pemeriksaan, () => {
  form.value.parameter_id = '';
  form.value.point = 0;
});


function submit() {
  isSubmitting.value = true;
  errors.value = {};

  const formData = new FormData();
  formData.append('category_id', form.value.category_id);
  formData.append('parameter_pemeriksaan', form.value.parameter_pemeriksaan);
  formData.append('parameter_id', form.value.parameter_id);
  formData.append('point', form.value.point);
  formData.append('notes', form.value.notes);
  formData.append('finding_type', form.value.finding_type);
  
  // Append multiple photos
  form.value.photos.forEach((photo, index) => {
    formData.append(`photos[${index}]`, photo);
  });

  router.post(route('inspections.store-finding', props.inspection.id), formData, {
    onSuccess: () => {
      // Reset form for next finding
      form.value = {
        category_id: '',
        parameter_pemeriksaan: '',
        parameter_id: '',
        point: 0,
        photos: [],
        notes: '',
      };
      
      // Reset photo states
      selectedPhotos.value = [];
      photoPreviews.value = [];
      // Clear file input
      const fileInput = document.querySelector('input[type="file"]');
      if (fileInput) fileInput.value = '';
    },
    onError: (errs) => {
      errors.value = errs;
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function back() {
  router.visit('/inspections');
}

// Camera functions
function openCamera() {
  showCameraModal.value = true;
}

function closeCamera() {
  showCameraModal.value = false;
}

async function onPhotoCapture(dataUrl) {
  try {
    // Convert dataURL to blob
    const response = await fetch(dataUrl);
    const blob = await response.blob();
    const file = new File([blob], `inspection_photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
    
    // Add to photos array
    form.value.photos.push(file);
    selectedPhotos.value.push(file);
    
    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      photoPreviews.value.push(e.target.result);
    };
    reader.readAsDataURL(file);
    
    closeCamera();
  } catch (error) {
    console.error('Error processing photo:', error);
    alert('Gagal memproses foto. Silakan coba lagi.');
  }
}

function removePhoto(index) {
  form.value.photos.splice(index, 1);
  selectedPhotos.value.splice(index, 1);
  photoPreviews.value.splice(index, 1);
}

function openLightbox(index = 0) {
  if (photoPreviews.value.length > 0) {
    lightboxIndex.value = index;
    lightboxImage.value = photoPreviews.value[index];
    showLightbox.value = true;
  }
}

function closeLightbox() {
  showLightbox.value = false;
  lightboxImage.value = null;
}

// Navigation functions
const continueInspection = (inspectionId) => {
  router.visit(route('inspections.add-finding', inspectionId));
};

// User image lightbox
const openUserImage = (imageUrl, userName) => {
  lightboxImage.value = imageUrl;
  lightboxIndex.value = 0;
  showLightbox.value = true;
};

// Photo lightbox for findings
const openPhotoLightbox = (photoPath, allPhotos, index) => {
  photoPreviews.value = allPhotos.map(photo => `/storage/${photo}`);
  lightboxIndex.value = index;
  showLightbox.value = true;
};

// Get initials from name (copied from Home.vue)
const getInitials = (name) => {
  if (!name) return 'U';
  return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().slice(0, 2);
};

// Edit and Delete Finding functions
const editFinding = (findingId) => {
  Swal.fire({
    title: 'Edit Finding',
    text: 'Edit finding functionality will be implemented soon.',
    icon: 'info',
    confirmButtonText: 'OK'
  });
};

const deleteFinding = async (findingId) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel'
  });

  if (result.isConfirmed) {
    try {
      await router.delete(route('inspections.delete-finding', [props.inspection.id, findingId]));
      
      Swal.fire({
        title: 'Deleted!',
        text: 'Finding has been deleted.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
    } catch (error) {
      console.error('Error deleting finding:', error);
      Swal.fire({
        title: 'Error!',
        text: 'Failed to delete finding. Please try again.',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  }
};

function handleFileSelect(event) {
  const files = Array.from(event.target.files);
  if (files.length > 0) {
    files.forEach(file => {
      form.value.photos.push(file);
      selectedPhotos.value.push(file);
      
      // Create preview
      const reader = new FileReader();
      reader.onload = (e) => {
        photoPreviews.value.push(e.target.result);
      };
      reader.readAsDataURL(file);
    });
    
    // Reset input
    event.target.value = '';
  }
}

async function completeInspection() {
  const result = await Swal.fire({
    title: 'Complete Inspection?',
    text: "Are you sure you want to complete this inspection? This action cannot be undone.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, Complete!',
    cancelButtonText: 'Cancel'
  });

  if (result.isConfirmed) {
    try {
      await router.patch(route('inspections.complete', props.inspection.id));
      
      Swal.fire({
        title: 'Completed!',
        text: 'Inspection has been completed successfully.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
    } catch (error) {
      console.error('Error completing inspection:', error);
      Swal.fire({
        title: 'Error!',
        text: 'Failed to complete inspection. Please try again.',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  }
}

// Camera functionality
const video = ref(null);
const stream = ref(null);
const currentFacingMode = ref('environment');

async function initializeCamera() {
  try {
    const constraints = {
      video: { 
        facingMode: currentFacingMode.value,
        width: { ideal: 1280 },
        height: { ideal: 720 }
      }
    };

    try {
      stream.value = await navigator.mediaDevices.getUserMedia(constraints);
    } catch (err) {
      // Jika gagal dengan kamera yang dipilih, coba kamera lainnya
      console.log('Trying fallback camera...');
      currentFacingMode.value = currentFacingMode.value === 'user' ? 'environment' : 'user';
      stream.value = await navigator.mediaDevices.getUserMedia({
        video: { 
          facingMode: currentFacingMode.value,
          width: { ideal: 1280 },
          height: { ideal: 720 }
        }
      });
    }

    if (video.value) {
      video.value.srcObject = stream.value;
      video.value.onloadedmetadata = () => {
        console.log('Video ready:', video.value.videoWidth, 'x', video.value.videoHeight);
      };
    }
  } catch (error) {
    console.error('Error accessing camera:', error);
    alert('Tidak dapat mengakses kamera. Pastikan Anda memberikan izin akses kamera.');
  }
}

async function switchCamera() {
  currentFacingMode.value = currentFacingMode.value === 'user' ? 'environment' : 'user';
  await initializeCamera();
}

function capturePhoto() {
  if (!video.value || !stream.value) {
    alert('Kamera belum siap. Silakan tunggu sebentar.');
    return;
  }
  
  try {
    const canvas = document.createElement('canvas');
    canvas.width = video.value.videoWidth;
    canvas.height = video.value.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video.value, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    onPhotoCapture(dataUrl);
  } catch (error) {
    console.error('Error capturing photo:', error);
    alert('Gagal mengambil foto. Silakan coba lagi.');
  }
}

// Lifecycle hooks
onMounted(() => {
  // Camera will be initialized when modal opens
});

onUnmounted(() => {
  if (stream.value) {
    stream.value.getTracks().forEach(track => track.stop());
  }
});

// Watch for camera modal to initialize camera
watch(showCameraModal, (newVal) => {
  if (newVal) {
    initializeCamera();
  } else {
    if (stream.value) {
      stream.value.getTracks().forEach(track => track.stop());
      stream.value = null;
    }
  }
});
</script>

<template>
  <AppLayout title="Add Finding">
    <div class="w-full py-8 px-4">
      <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center gap-4">
            <button @click="back" class="text-gray-500 hover:text-gray-700 transition">
              <i class="fa-solid fa-arrow-left text-xl"></i>
            </button>
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
              <i class="fa-solid fa-camera text-blue-500"></i>
              Add Finding
            </h1>
          </div>
          <div class="flex gap-3">
            <button @click="completeInspection" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition">
              <i class="fa-solid fa-check mr-2"></i>Complete Inspection
            </button>
          </div>
        </div>

        <!-- Inspection Info -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Inspection Details</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500">Created By</label>
              <p class="text-gray-800 font-semibold">{{ inspection.created_by_user?.name || '-' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Outlet</label>
              <p class="text-gray-800 font-semibold">{{ inspection.outlet?.nama_outlet || '-' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Department</label>
              <p class="text-gray-800 font-semibold">{{ inspection.departemen }}</p>
            </div>
          </div>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-2xl shadow-lg p-8">
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Photo Upload -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-camera mr-2"></i>Photo Evidence *
              </label>
              
              <!-- Photo Actions -->
              <div class="flex gap-2 mb-3">
                <button
                  type="button"
                  @click="openCamera"
                  class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition flex items-center gap-2"
                >
                  <i class="fa-solid fa-camera"></i>
                  Camera
                </button>
                <label for="photo-upload" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2 cursor-pointer">
                  <i class="fa-solid fa-upload"></i>
                  Upload
                </label>
                <input
                  id="photo-upload"
                  type="file"
                  accept="image/*"
                  multiple
                  @change="handleFileSelect"
                  class="hidden"
                />
              </div>

              <!-- Photo Previews -->
              <div v-if="photoPreviews.length > 0" class="space-y-4">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                  <div 
                    v-for="(preview, index) in photoPreviews" 
                    :key="index"
                    class="relative group"
                  >
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-2">
                      <img
                        :src="preview"
                        :alt="`Photo ${index + 1}`"
                        class="w-full h-32 object-cover rounded-lg cursor-pointer hover:opacity-80 transition"
                        @click="openLightbox(index)"
                      />
                      <button
                        type="button"
                        @click="removePhoto(index)"
                        class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs transition opacity-0 group-hover:opacity-100"
                      >
                        <i class="fa-solid fa-times"></i>
                      </button>
                    </div>
                  </div>
                </div>
                
                <!-- Photo Count -->
                <div class="text-center text-sm text-gray-600">
                  {{ photoPreviews.length }} photo(s) selected
                </div>
              </div>

              <!-- No Photo State -->
              <div v-else class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center text-gray-500">
                <i class="fa-solid fa-camera text-4xl mb-2"></i>
                <p>No photos selected</p>
                <p class="text-sm">Use camera or upload button above</p>
              </div>

              <p v-if="errors.photo" class="mt-1 text-sm text-red-600">{{ errors.photo }}</p>
              <p class="mt-1 text-sm text-gray-500">Upload photo evidence of your finding</p>
            </div>

            <!-- Category Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-tag mr-2"></i>Category *
              </label>
              <select 
                v-model="form.category_id" 
                :class="[
                  'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                  errors.category_id ? 'border-red-500' : 'border-gray-300'
                ]"
                required
              >
                <option value="">Select Category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.categories }}
                </option>
              </select>
              <p v-if="errors.category_id" class="mt-1 text-sm text-red-600">{{ errors.category_id }}</p>
            </div>

            <!-- Parameter Pemeriksaan Selection -->
            <div v-if="availableParameters.length > 0">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-clipboard-list mr-2"></i>Parameter Pemeriksaan *
              </label>
              <select 
                v-model="form.parameter_pemeriksaan" 
                :class="[
                  'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                  errors.parameter_pemeriksaan ? 'border-red-500' : 'border-gray-300'
                ]"
                required
              >
                <option value="">Select Parameter Pemeriksaan</option>
                <option v-for="param in availableParameters" :key="param.parameter_pemeriksaan" :value="param.parameter_pemeriksaan">
                  {{ param.parameter_pemeriksaan }}
                </option>
              </select>
              <p v-if="errors.parameter_pemeriksaan" class="mt-1 text-sm text-red-600">{{ errors.parameter_pemeriksaan }}</p>
            </div>

            <!-- Parameter Selection -->
            <div v-if="availableParameterDetails.length > 0">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-cogs mr-2"></i>Parameter *
              </label>
              <select 
                v-model="form.parameter_id" 
                :class="[
                  'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                  errors.parameter_id ? 'border-red-500' : 'border-gray-300'
                ]"
                required
              >
                <option value="">Select Parameter</option>
                <option v-for="detail in availableParameterDetails" :key="detail.parameter_id" :value="detail.parameter_id">
                  {{ detail.parameter?.parameter }} ({{ detail.point }} points)
                </option>
              </select>
              <p v-if="errors.parameter_id" class="mt-1 text-sm text-red-600">{{ errors.parameter_id }}</p>
            </div>

            <!-- Point Display - Product Mode -->
            <div v-if="selectedParameterDetail && !isCleanlinessMode" class="bg-green-50 border border-green-200 rounded-xl p-4">
              <div class="flex items-center justify-between">
                <div>
                  <h4 class="text-sm font-semibold text-green-800">Selected Parameter</h4>
                  <p class="text-sm text-green-700">{{ selectedParameterDetail.parameter?.parameter }}</p>
                </div>
                <div class="text-right">
                  <span class="text-2xl font-bold text-green-800">{{ selectedParameterDetail.point }}</span>
                  <p class="text-sm text-green-600">Points</p>
                </div>
              </div>
            </div>

            <!-- Cleanliness Rating - Cleanliness Mode -->
            <div v-if="selectedParameterDetail && isCleanlinessMode" class="bg-blue-50 border border-blue-200 rounded-xl p-4">
              <div class="mb-4">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">Cleanliness Rating</h4>
                <p class="text-sm text-blue-700 mb-4">{{ selectedParameterDetail.parameter?.parameter }}</p>
                <div class="grid grid-cols-3 gap-3">
                  <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-green-50 transition" :class="form.cleanliness_rating === 'Yes' ? 'border-green-500 bg-green-50' : 'border-gray-300'">
                    <input 
                      type="radio" 
                      v-model="form.cleanliness_rating" 
                      value="Yes" 
                      class="mr-2 text-green-600 focus:ring-green-500"
                    />
                    <div class="text-center">
                      <div class="font-medium text-green-800">Yes</div>
                      <div class="text-xs text-green-600">+{{ selectedParameterDetail.point }} pts</div>
                    </div>
                  </label>
                  
                  <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-red-50 transition" :class="form.cleanliness_rating === 'No' ? 'border-red-500 bg-red-50' : 'border-gray-300'">
                    <input 
                      type="radio" 
                      v-model="form.cleanliness_rating" 
                      value="No" 
                      class="mr-2 text-red-600 focus:ring-red-500"
                    />
                    <div class="text-center">
                      <div class="font-medium text-red-800">No</div>
                      <div class="text-xs text-red-600">-{{ selectedParameterDetail.point }} pts</div>
                    </div>
                  </label>
                  
                  <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition" :class="form.cleanliness_rating === 'NA' ? 'border-gray-500 bg-gray-50' : 'border-gray-300'">
                    <input 
                      type="radio" 
                      v-model="form.cleanliness_rating" 
                      value="NA" 
                      class="mr-2 text-gray-600 focus:ring-gray-500"
                    />
                    <div class="text-center">
                      <div class="font-medium text-gray-800">NA</div>
                      <div class="text-xs text-gray-600">0 pts</div>
                    </div>
                  </label>
                </div>
              </div>
              
              <!-- Point Display -->
              <div v-if="form.cleanliness_rating" class="flex items-center justify-between">
                <div>
                  <h4 class="text-sm font-semibold text-blue-800">Final Points</h4>
                  <p class="text-sm text-blue-700">{{ form.cleanliness_rating }} Rating</p>
                </div>
                <div class="text-right">
                  <span :class="[
                    'text-2xl font-bold',
                    form.point > 0 ? 'text-green-800' : form.point < 0 ? 'text-red-800' : 'text-gray-800'
                  ]">{{ form.point }}</span>
                  <p class="text-sm text-blue-600">Points</p>
                </div>
              </div>
            </div>


            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-sticky-note mr-2"></i>Notes
              </label>
              <textarea 
                v-model="form.notes" 
                rows="3"
                :class="[
                  'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                  errors.notes ? 'border-red-500' : 'border-gray-300'
                ]"
                placeholder="Add any additional notes about this finding..."
              ></textarea>
              <p v-if="errors.notes" class="mt-1 text-sm text-red-600">{{ errors.notes }}</p>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-200">
              <button 
                type="button" 
                @click="back" 
                class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-medium transition"
              >
                <i class="fa-solid fa-times mr-2"></i>Cancel
              </button>
              <button 
                type="submit" 
                :disabled="isSubmitting"
                :class="[
                  'px-6 py-3 rounded-xl font-medium transition',
                  isSubmitting ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-blue-500 hover:bg-blue-600 text-white'
                ]"
              >
                <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-save mr-2"></i>
                {{ isSubmitting ? 'Saving...' : 'Save Finding' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Camera Modal -->
    <div v-if="showCameraModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div class="bg-white w-full h-full sm:h-auto sm:max-h-[90vh] sm:max-w-xl sm:rounded-2xl shadow-2xl relative flex flex-col">
        <!-- Header -->
        <div class="flex-shrink-0 p-4 border-b border-gray-200 bg-gray-50 sm:bg-white sm:rounded-t-2xl">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900">
              Ambil Foto Temuan
            </h2>
            <button 
              @click="closeCamera" 
              class="p-2 text-gray-400 hover:text-red-500 rounded-full hover:bg-gray-100 transition-colors"
            >
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
        </div>

        <!-- Camera Container -->
        <div class="flex-1 flex flex-col min-h-0 p-4">
          <div class="relative bg-black rounded-lg overflow-hidden mb-4 flex-1 min-h-0">
            <video 
              ref="video" 
              autoplay 
              playsinline 
              class="w-full h-full object-cover aspect-[4/3]"
            ></video>
            
            <!-- Switch Camera Button -->
            <button 
              @click="switchCamera" 
              class="absolute top-3 right-3 p-3 bg-black/60 hover:bg-black/80 text-white rounded-full transition-all shadow-lg"
              title="Switch Kamera"
            >
              <i class="fas fa-camera-rotate text-lg"></i>
            </button>
          </div>

          <!-- Control Buttons -->
          <div class="flex-shrink-0 space-y-3">
            <div class="flex justify-center">
              <button 
                @click="capturePhoto" 
                class="w-16 h-16 bg-white rounded-full shadow-lg flex items-center justify-center text-2xl text-gray-700 hover:bg-gray-50 transition-all"
              >
                <i class="fas fa-camera"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox Modal -->
    <LightboxModal 
      v-if="showLightbox"
      :show="showLightbox"
      :mediaList="photoPreviews.map((preview, index) => ({ 
        type: 'image', 
        url: preview, 
        caption: `Photo Evidence ${index + 1}` 
      }))"
      :startIndex="lightboxIndex"
      @close="closeLightbox"
    />

    <!-- Existing Inspections -->
    <div v-if="existingInspections && existingInspections.length > 0" class="mt-8">
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
          <i class="fa-solid fa-list-check text-blue-500 mr-2"></i>
          All Inspections in This Outlet & Date
        </h3>
        
        <div class="grid gap-4">
          <div 
            v-for="otherInspection in existingInspections" 
            :key="otherInspection.id"
            :class="[
              'border rounded-xl p-4 hover:shadow-md transition',
              otherInspection.id === inspection.id 
                ? 'border-blue-300 bg-blue-50' 
                : 'border-gray-200'
            ]"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <h4 class="font-semibold text-gray-800">
                    {{ otherInspection.guidance?.title || 'Inspection' }}
                    <span v-if="otherInspection.id === inspection.id" class="text-blue-600 text-sm">(Current)</span>
                  </h4>
                  <span :class="[
                    'px-2 py-1 rounded-full text-xs font-semibold',
                    otherInspection.status === 'Draft' 
                      ? 'bg-yellow-100 text-yellow-800' 
                      : 'bg-green-100 text-green-800'
                  ]">
                    {{ otherInspection.status }}
                  </span>
                </div>
                
                <div class="flex items-center gap-4 text-sm text-gray-600">
                  <div class="flex items-center gap-2">
                    <div class="flex items-center gap-2">
                      <!-- Avatar User Creator -->
                      <div v-if="otherInspection.created_by_user?.image" class="w-6 h-6 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform" @click="openUserImage(`/storage/${otherInspection.created_by_user.image}`, otherInspection.created_by_user.nama_lengkap)">
                        <img 
                          :src="`/storage/${otherInspection.created_by_user.image}`" 
                          :alt="otherInspection.created_by_user.nama_lengkap"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <div v-else class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                        {{ getInitials(otherInspection.created_by_user?.nama_lengkap || 'U') }}
                      </div>
                      <span class="text-sm font-medium">{{ otherInspection.created_by_user?.nama_lengkap || 'Unknown' }}</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-1">
                    <i class="fa-solid fa-calendar"></i>
                    <span>{{ new Date(otherInspection.inspection_date).toLocaleDateString() }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <i class="fa-solid fa-camera"></i>
                    <span>{{ otherInspection.total_findings }} findings</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <i class="fa-solid fa-star"></i>
                    <span>{{ otherInspection.total_points }} points</span>
                  </div>
                </div>
                
                <!-- Inspection Details -->
                <div v-if="otherInspection.details && otherInspection.details.length > 0" class="mt-3 pt-3 border-t border-gray-200">
                  <h5 class="text-sm font-semibold text-gray-700 mb-2">Findings:</h5>
                  <div class="space-y-2">
                    <div 
                      v-for="detail in otherInspection.details" 
                      :key="detail.id"
                      class="bg-gray-50 rounded-lg p-3"
                    >
                       <div class="flex items-center justify-between mb-2">
                         <div class="flex items-center gap-2">
                           <span class="text-sm font-medium text-gray-800">
                             {{ detail.category?.kode_categories }} - {{ detail.category?.categories }}
                           </span>
                           <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                             {{ detail.point }} pts
                           </span>
                         </div>
                         <div class="flex items-center gap-2">
                           <span class="text-xs text-gray-500">
                             {{ new Date(detail.created_at).toLocaleDateString() }}
                           </span>
                           <div class="flex items-center gap-1">
                             <button
                               @click="editFinding(detail.id)"
                               class="w-6 h-6 bg-blue-500 hover:bg-blue-600 text-white rounded flex items-center justify-center text-xs transition"
                               title="Edit Finding"
                             >
                               <i class="fa-solid fa-edit"></i>
                             </button>
                             <button
                               @click="deleteFinding(detail.id)"
                               class="w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded flex items-center justify-center text-xs transition"
                               title="Delete Finding"
                             >
                               <i class="fa-solid fa-trash"></i>
                             </button>
                           </div>
                         </div>
                       </div>
                       
                       <!-- Creator Info -->
                       <div class="flex items-center gap-2 mb-2">
                         <div class="flex items-center gap-2">
                           <!-- Avatar Creator -->
                           <div v-if="detail.created_by_user?.image" class="w-5 h-5 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform" @click="openUserImage(`/storage/${detail.created_by_user.image}`, detail.created_by_user.nama_lengkap)">
                             <img 
                               :src="`/storage/${detail.created_by_user.image}`" 
                               :alt="detail.created_by_user.nama_lengkap"
                               class="w-full h-full object-cover"
                             />
                           </div>
                           <div v-else class="w-5 h-5 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                             {{ getInitials(detail.created_by_user?.nama_lengkap || 'U') }}
                           </div>
                           <span class="text-xs font-medium text-gray-700">{{ detail.created_by_user?.nama_lengkap || 'Unknown' }}</span>
                         </div>
                       </div>
                      
                      <div class="text-sm text-gray-600">
                        <div class="mb-1">
                          <strong>Parameter:</strong> {{ detail.parameter_pemeriksaan }}
                        </div>
                        <div class="mb-1">
                          <strong>Parameter Detail:</strong> {{ detail.parameter?.kode_parameter }} - {{ detail.parameter?.parameter }}
                        </div>
                        <div v-if="detail.notes" class="mb-1">
                          <strong>Notes:</strong> {{ detail.notes }}
                        </div>
                        
                        <!-- Photo Evidence -->
                        <div v-if="detail.photo_paths && detail.photo_paths.length > 0" class="mt-2">
                          <strong class="text-xs">Photos:</strong>
                          <div class="flex gap-2 mt-1">
                            <img 
                              v-for="(photo, index) in detail.photo_paths" 
                              :key="index"
                              :src="`/storage/${photo}`"
                              :alt="`Finding photo ${index + 1}`"
                              class="w-12 h-12 object-cover rounded cursor-pointer hover:opacity-80 transition"
                              @click="openPhotoLightbox(photo, detail.photo_paths, index)"
                            />
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="flex items-center gap-2">
                <button
                  v-if="otherInspection.status === 'Draft' && otherInspection.id !== inspection.id"
                  @click="continueInspection(otherInspection.id)"
                  class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm transition"
                >
                  <i class="fa-solid fa-play mr-1"></i>
                  Continue
                </button>
                <span 
                  v-if="otherInspection.id === inspection.id"
                  class="px-3 py-1 bg-blue-100 text-blue-800 rounded-lg text-sm font-semibold"
                >
                  <i class="fa-solid fa-check mr-1"></i>
                  Current
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
