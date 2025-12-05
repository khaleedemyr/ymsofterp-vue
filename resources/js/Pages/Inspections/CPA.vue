<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  inspection: Object,
  findings: Array,
  users: Array, // Users from same outlet
});

const cpaData = ref({});
const loading = ref(false);

// Lightbox functionality
const showImageModal = ref(false);
const selectedImageUrl = ref('');

// File upload functionality
const uploadedFiles = ref({});
const isUploading = ref(false);

// Autocomplete functionality
const showUserDropdown = ref({});
const filteredUsers = ref({});
const userSearchQuery = ref({});

// Camera functionality
const showCameraModal = ref(false);
const currentFindingId = ref(null);
const videoRef = ref(null);
const cameraReady = ref(false);
let cameraStream = null;
const currentFacingMode = ref('environment');

function openImageModal(imageUrl) {
  selectedImageUrl.value = imageUrl;
  showImageModal.value = true;
}

function closeImageModal() {
  showImageModal.value = false;
  selectedImageUrl.value = '';
}

// File upload functions
function handleFileUpload(event, findingId) {
  const files = Array.from(event.target.files);
  if (!uploadedFiles.value[findingId]) {
    uploadedFiles.value[findingId] = [];
  }
  
  files.forEach(file => {
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => {
        uploadedFiles.value[findingId].push({
          file: file,
          preview: e.target.result,
          name: file.name
        });
      };
      reader.readAsDataURL(file);
    }
  });
}

function removeFile(findingId, index) {
  uploadedFiles.value[findingId].splice(index, 1);
}

function getFilePreview(file) {
  return URL.createObjectURL(file);
}

// Camera functions
async function openCamera(findingId) {
  currentFindingId.value = findingId;
  showCameraModal.value = true;
  await initializeCamera();
}

function closeCamera() {
  showCameraModal.value = false;
  currentFindingId.value = null;
  if (cameraStream) {
    cameraStream.getTracks().forEach(track => track.stop());
    cameraStream = null;
  }
  cameraReady.value = false;
}

async function initializeCamera() {
  try {
    if (cameraStream) {
      cameraStream.getTracks().forEach(track => track.stop());
    }

    const constraints = {
      video: {
        facingMode: currentFacingMode.value,
        width: { ideal: 1280 },
        height: { ideal: 720 }
      }
    };

    try {
      cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
    } catch (err) {
      // Fallback to other camera
      currentFacingMode.value = currentFacingMode.value === 'user' ? 'environment' : 'user';
      cameraStream = await navigator.mediaDevices.getUserMedia({
        video: { 
          facingMode: currentFacingMode.value,
          width: { ideal: 1280 },
          height: { ideal: 720 }
        }
      });
    }

    if (videoRef.value) {
      videoRef.value.srcObject = cameraStream;
      videoRef.value.onloadedmetadata = () => {
        cameraReady.value = true;
      };
    }
  } catch (error) {
    console.error('Error accessing camera:', error);
    Swal.fire('Error', 'Tidak dapat mengakses kamera. Pastikan Anda memberikan izin akses kamera.', 'error');
  }
}

async function switchCamera() {
  currentFacingMode.value = currentFacingMode.value === 'user' ? 'environment' : 'user';
  await initializeCamera();
}

function capturePhoto() {
  if (!videoRef.value || !cameraStream) {
    Swal.fire('Error', 'Kamera belum siap. Silakan tunggu sebentar.', 'error');
    return;
  }
  
  try {
    const canvas = document.createElement('canvas');
    canvas.width = videoRef.value.videoWidth;
    canvas.height = videoRef.value.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(videoRef.value, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    onPhotoCapture(dataUrl);
  } catch (error) {
    console.error('Error capturing photo:', error);
    Swal.fire('Error', 'Gagal mengambil foto. Silakan coba lagi.', 'error');
  }
}

async function onPhotoCapture(dataUrl) {
  if (!currentFindingId.value) return;
  
  try {
    // Convert dataURL to blob
    const response = await fetch(dataUrl);
    const blob = await response.blob();
    const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
    
    // Add to uploaded files
    if (!uploadedFiles.value[currentFindingId.value]) {
      uploadedFiles.value[currentFindingId.value] = [];
    }
    
    uploadedFiles.value[currentFindingId.value].push({
      file: file,
      preview: dataUrl,
      name: file.name
    });
    
    closeCamera();
  } catch (error) {
    console.error('Error processing photo:', error);
    Swal.fire('Error', 'Gagal memproses foto', 'error');
  }
}

// Initialize CPA data for each finding
const initializeCPAData = () => {
  props.findings.forEach(finding => {
    cpaData.value[finding.id] = {
      action_plan: '',
      responsible_person: '',
      due_date: '',
      notes: '',
    };
    
    // Initialize autocomplete states
    showUserDropdown.value[finding.id] = false;
    filteredUsers.value[finding.id] = props.users || [];
    userSearchQuery.value[finding.id] = '';
  });
};

// Initialize on component mount
initializeCPAData();

const saveAllCPA = async () => {
  // Validate all findings
  const validationErrors = [];
  
  props.findings.forEach((finding, index) => {
    const data = cpaData.value[finding.id];
    const files = uploadedFiles.value[finding.id] || [];
    
    if (!data.action_plan || !data.responsible_person || !data.due_date) {
      validationErrors.push(`Finding #${index + 1}: Please fill in action plan, responsible person and due date`);
    }
    
    if (files.length === 0) {
      validationErrors.push(`Finding #${index + 1}: Please upload at least one documentation image`);
    }
  });

  if (validationErrors.length > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Validation Error',
      html: validationErrors.join('<br>'),
    });
    return;
  }

  // Show confirmation dialog
  const result = await Swal.fire({
    title: 'Save All CPA?',
    text: `Are you sure you want to save CPA for ${props.findings.length} finding(s)?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#7c3aed',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, Save All!',
    cancelButtonText: 'Cancel'
  });

  if (!result.isConfirmed) return;

  loading.value = true;
  
  try {
    // Save all findings one by one
    for (const finding of props.findings) {
      const data = cpaData.value[finding.id];
      const files = uploadedFiles.value[finding.id] || [];
      
      const formData = new FormData();
      formData.append('inspection_detail_id', finding.id);
      formData.append('action_plan', data.action_plan);
      formData.append('responsible_person', data.responsible_person);
      formData.append('due_date', data.due_date);
      formData.append('notes', data.notes || '');
      
      // Append files
      files.forEach((fileData, index) => {
        formData.append(`documentation[${index}]`, fileData.file);
      });

      // Use axios instead of router.post for better control
      const response = await axios.post(route('inspections.cpa.store', props.inspection.id), formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      
      if (!response.data.success) {
        throw new Error(response.data.message || 'Failed to save CPA');
      }
    }
    
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: `All CPA saved successfully for ${props.findings.length} finding(s)!`,
      confirmButtonColor: '#10b981',
      timer: 2000,
      timerProgressBar: true
    }).then(() => {
      // Redirect to inspections index
      router.visit(route('inspections.index'));
    });
    
    // Clear all uploaded files
    props.findings.forEach(finding => {
      uploadedFiles.value[finding.id] = [];
    });
    
  } catch (error) {
    console.error('CPA Save Error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || error.message || 'Failed to save some CPA. Please check and try again.',
      confirmButtonColor: '#ef4444'
    });
  } finally {
    loading.value = false;
  }
};

// Autocomplete functions
function handleUserSearch(findingId, query) {
  userSearchQuery.value[findingId] = query;
  
  if (query.length > 0) {
    filteredUsers.value[findingId] = props.users.filter(user => 
      user.nama_lengkap.toLowerCase().includes(query.toLowerCase())
    );
    showUserDropdown.value[findingId] = true;
  } else {
    filteredUsers.value[findingId] = props.users || [];
    showUserDropdown.value[findingId] = false;
  }
}

function selectUser(findingId, user) {
  cpaData.value[findingId].responsible_person = user.nama_lengkap;
  userSearchQuery.value[findingId] = user.nama_lengkap;
  showUserDropdown.value[findingId] = false;
}

function toggleUserDropdown(findingId) {
  showUserDropdown.value[findingId] = !showUserDropdown.value[findingId];
  if (showUserDropdown.value[findingId]) {
    filteredUsers.value[findingId] = props.users || [];
  }
}

function closeUserDropdown(findingId) {
  showUserDropdown.value[findingId] = false;
}

const goBack = () => {
  router.visit(route('inspections.index'));
};
</script>

<template>
  <AppLayout title="Corrective and Preventive Action (CPA)">
    <div class="w-full py-8 px-4">
      <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center gap-4">
            <button @click="goBack" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
              <i class="fa-solid fa-arrow-left text-xl"></i>
            </button>
            <div>
              <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fa-solid fa-clipboard-list text-purple-500"></i>
                Corrective and Preventive Action (CPA)
              </h1>
              <p class="text-gray-600 mt-2">
                {{ inspection.outlet?.nama_outlet }} - {{ inspection.departemen }}
              </p>
            </div>
          </div>
        </div>

        <!-- Inspection Info Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 border-l-4 border-purple-500">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-2">Inspection Details</h3>
              <p class="text-sm text-gray-600"><strong>Date:</strong> {{ new Date(inspection.inspection_date).toLocaleDateString('id-ID') }}</p>
              <p class="text-sm text-gray-600"><strong>Guidance:</strong> {{ inspection.guidance?.title || '-' }}</p>
              <p class="text-sm text-gray-600"><strong>Status:</strong> 
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-semibold',
                  inspection.status === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                ]">
                  {{ inspection.status }}
                </span>
              </p>
            </div>
            
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-2">Findings Summary</h3>
              <p class="text-sm text-gray-600"><strong>Total Findings:</strong> {{ findings.length }}</p>
              <p class="text-sm text-gray-600"><strong>Score:</strong> {{ inspection.score || 0 }}%</p>
            </div>
            
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-2">Auditees</h3>
              <div class="space-y-1">
                <div v-for="auditee in inspection.auditees" :key="auditee.id" class="text-sm text-gray-600">
                  {{ auditee.nama_lengkap }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Findings and CPA Forms -->
        <div v-if="findings.length > 0" class="space-y-8">
          <div 
            v-for="finding in findings" 
            :key="finding.id"
            class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200"
          >
            <!-- Finding Header -->
            <div class="flex items-start justify-between mb-6">
              <div class="flex-1">
                <h3 class="text-xl font-bold text-gray-800 mb-2">
                  Finding #{{ findings.indexOf(finding) + 1 }}
                </h3>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <h4 class="font-semibold text-gray-800 mb-3">Finding Details:</h4>
              
              <!-- Category and Parameter Info -->
              <div class="space-y-2">
                <div>
                  <label class="text-sm font-medium text-gray-500">Category</label>
                  <p class="text-gray-800">{{ finding.category?.categories || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-500">Parameter Pemeriksaan</label>
                  <p class="text-gray-800">{{ finding.parameter_pemeriksaan || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-500">Parameter</label>
                  <p class="text-gray-800">{{ finding.parameter?.parameter || '-' }}</p>
                </div>
                <div v-if="finding.notes">
                  <label class="text-sm font-medium text-gray-500">Notes</label>
                  <p class="text-gray-800">{{ finding.notes }}</p>
                </div>
              </div>

              <!-- Photo Evidence -->
              <div v-if="finding.photo_paths && finding.photo_paths.length > 0" class="mt-4">
                <label class="text-sm font-medium text-gray-500">Photo Evidence</label>
                <div class="flex gap-2 mt-2">
                  <img 
                    v-for="(photo, index) in finding.photo_paths" 
                    :key="index"
                    :src="`/storage/${photo}`"
                    :alt="`Finding photo ${index + 1}`"
                    class="w-16 h-16 object-cover rounded cursor-pointer hover:opacity-80 transition"
                    @click="openImageModal(`/storage/${photo}`)"
                  />
                </div>
              </div>

              <!-- Status and Inspector -->
              <div class="flex items-center justify-between">
                <div>
                  <span :class="[
                    'px-3 py-1 rounded-full text-sm font-semibold',
                    finding.status === 'Non-Compliance' ? 'bg-red-100 text-red-800' : 
                    finding.status === 'Compliance' ? 'bg-green-100 text-green-800' : 
                    'bg-yellow-100 text-yellow-800'
                  ]">
                    {{ finding.status }}
                  </span>
                </div>
                <div class="text-sm text-gray-600">
                  <span class="font-medium">Inspector:</span> {{ finding.created_by_user?.nama_lengkap || 'Unknown' }}
                </div>
              </div>
            </div>
              </div>
            </div>

            <!-- CPA Form -->
            <div class="space-y-6">
              <!-- Action Plan -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Action Plan *
                </label>
                <textarea
                  v-model="cpaData[finding.id].action_plan"
                  rows="4"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                  placeholder="Describe the action plan to address this finding..."
                ></textarea>
              </div>

              <!-- Responsible Person and Due Date -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="relative">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Responsible Person *
                  </label>
                  <div class="relative">
                    <input
                      v-model="userSearchQuery[finding.id]"
                      @input="handleUserSearch(finding.id, $event.target.value)"
                      @focus="toggleUserDropdown(finding.id)"
                      @blur="setTimeout(() => closeUserDropdown(finding.id), 200)"
                      type="text"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                      placeholder="Search responsible person..."
                    />
                    <button
                      type="button"
                      @click="toggleUserDropdown(finding.id)"
                      class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                      <i class="fa-solid fa-chevron-down"></i>
                    </button>
                  </div>
                  
                  <!-- User Dropdown -->
                  <div 
                    v-if="showUserDropdown[finding.id] && filteredUsers[finding.id].length > 0"
                    class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                  >
                    <div 
                      v-for="user in filteredUsers[finding.id]" 
                      :key="user.id"
                      @click="selectUser(finding.id, user)"
                      class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                    >
                      <div class="flex items-center gap-3">
                        <div v-if="user.avatar" class="w-8 h-8 rounded-full overflow-hidden">
                          <img :src="`/storage/${user.avatar}`" :alt="user.nama_lengkap" class="w-full h-full object-cover" />
                        </div>
                        <div v-else class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                          {{ user.nama_lengkap?.charAt(0) || 'U' }}
                        </div>
                        <div>
                          <p class="font-medium text-gray-800">{{ user.nama_lengkap }}</p>
                          <p v-if="user.jabatan?.nama_jabatan" class="text-sm text-gray-500">{{ user.jabatan.nama_jabatan }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- No Results -->
                  <div 
                    v-if="showUserDropdown[finding.id] && filteredUsers[finding.id].length === 0 && userSearchQuery[finding.id].length > 0"
                    class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg"
                  >
                    <div class="px-4 py-3 text-gray-500 text-center">
                      No users found
                    </div>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Due Date *
                  </label>
                  <input
                    v-model="cpaData[finding.id].due_date"
                    type="date"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                  />
                </div>
              </div>

              <!-- Documentation Upload -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Documentation *
                </label>
                
                <!-- Upload Actions -->
                <div class="flex gap-2 mb-3">
                  <button
                    type="button"
                    @click="openCamera(finding.id)"
                    class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition flex items-center gap-2"
                  >
                    <i class="fa-solid fa-camera"></i>
                    Camera
                  </button>
                  <label 
                    :for="`file-upload-${finding.id}`"
                    class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2 cursor-pointer"
                  >
                    <i class="fa-solid fa-upload"></i>
                    Upload
                  </label>
                  <input
                    type="file"
                    multiple
                    accept="image/*"
                    @change="handleFileUpload($event, finding.id)"
                    class="hidden"
                    :id="`file-upload-${finding.id}`"
                  />
                </div>

                <!-- Uploaded Files Preview -->
                <div v-if="uploadedFiles[finding.id] && uploadedFiles[finding.id].length > 0" class="mt-4">
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div 
                      v-for="(file, index) in uploadedFiles[finding.id]" 
                      :key="index"
                      class="relative group"
                    >
                      <img 
                        :src="file.preview" 
                        :alt="file.name"
                        class="w-full h-24 object-cover rounded-lg border border-gray-200 cursor-pointer hover:shadow-lg transition"
                        @click="openImageModal(file.preview)"
                      />
                      <button 
                        @click="removeFile(finding.id, index)"
                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 transition"
                      >
                        <i class="fa-solid fa-times"></i>
                      </button>
                      <p class="text-xs text-gray-500 mt-1 truncate">{{ file.name }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Notes (Optional) -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Notes (Optional)
                </label>
                <textarea
                  v-model="cpaData[finding.id].notes"
                  rows="3"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                  placeholder="Additional notes or comments..."
                ></textarea>
              </div>

            </div>
          </div>
        </div>

        <!-- Save All Button -->
        <div v-if="findings.length > 0" class="mt-8 flex justify-center">
          <button
            @click="saveAllCPA"
            :disabled="loading"
            class="px-8 py-4 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white rounded-xl font-medium transition flex items-center gap-3 text-lg"
          >
            <i v-if="loading" class="fa-solid fa-spinner fa-spin text-xl"></i>
            <i v-else class="fa-solid fa-save text-xl"></i>
            {{ loading ? 'Saving All CPA...' : 'Save All CPA' }}
          </button>
        </div>

        <!-- No Findings State -->
        <div v-else class="text-center py-12">
          <i class="fa-solid fa-clipboard-check text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-500 mb-2">No Findings Found</h3>
          <p class="text-gray-400 mb-6">This inspection has no findings to input CPA for.</p>
          <button @click="goBack" class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-medium transition">
            <i class="fa-solid fa-arrow-left mr-2"></i>Back to Inspections
          </button>
        </div>
      </div>

      <!-- Lightbox Modal -->
      <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeImageModal">
        <div class="relative max-w-4xl max-h-[90vh] p-4" @click.stop>
          <button 
            @click="closeImageModal"
            class="absolute -top-4 -right-4 bg-white rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors z-10"
          >
            <i class="fa-solid fa-times text-gray-600"></i>
          </button>
          <img 
            :src="selectedImageUrl" 
            :alt="'Finding documentation'"
            class="max-w-full max-h-full object-contain rounded-lg shadow-2xl"
          />
        </div>
      </div>

      <!-- Camera Modal -->
      <div v-if="showCameraModal" class="fixed inset-0 z-50 bg-black bg-opacity-75 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
          <!-- Camera Header -->
          <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold flex items-center">
                <i class="fa-solid fa-camera mr-2"></i>
                Take Documentation Photo
              </h3>
              <button 
                @click="closeCamera"
                class="text-white hover:text-gray-200 text-xl"
              >
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>

          <!-- Camera Content -->
          <div class="p-4">
            <div class="relative bg-black rounded-lg overflow-hidden mb-4" style="aspect-ratio: 16/9;">
              <video 
                ref="videoRef"
                autoplay 
                muted 
                playsinline
                class="w-full h-full object-cover"
              ></video>
              <div v-if="!cameraReady" class="absolute inset-0 flex items-center justify-center bg-gray-800">
                <div class="text-white text-center">
                  <i class="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
                  <p>Initializing camera...</p>
                </div>
              </div>
            </div>

            <!-- Camera Controls -->
            <div class="flex items-center justify-between">
              <button 
                @click="switchCamera"
                class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2"
              >
                <i class="fa-solid fa-camera-rotate"></i>
                Switch Camera
              </button>
              
              <div class="flex gap-3">
                <button 
                  @click="closeCamera"
                  class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium"
                >
                  Cancel
                </button>
                <button 
                  @click="capturePhoto"
                  class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium shadow-lg"
                >
                  <i class="fa-solid fa-camera mr-2"></i>
                  Capture
                </button>
              </div>
            </div>

            <!-- Instructions -->
            <div class="text-center text-sm text-gray-500 mt-4">
              <p>Position the camera and press Capture to take a photo</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
