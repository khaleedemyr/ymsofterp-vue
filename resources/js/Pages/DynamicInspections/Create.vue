<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-3xl font-bold mb-8 text-blue-800 flex items-center gap-3">
        <i class="fa-solid fa-clipboard-check text-blue-500"></i> Outlet/HO Inspection Form
      </h1>

      <form @submit.prevent="submitForm" class="max-w-6xl mx-auto">
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
              <label class="block text-sm font-medium text-gray-700 mb-2">Hari/Tanggal *</label>
              <input 
                v-model="form.inspection_date" 
                type="date" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nama PIC</label>
              <input 
                :value="props.user?.nama_lengkap || ''" 
                disabled 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
              <input 
                :value="userJabatan" 
                disabled 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Divisi</label>
              <input 
                :value="userDivisi" 
                disabled 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet Leader</label>
              <input 
                v-model="form.outlet_leader" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Masukkan nama Outlet Leader"
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
              class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition"
            >
              <label class="flex items-center gap-3 cursor-pointer">
                <input 
                  type="checkbox" 
                  :value="subject.id" 
                  v-model="form.selected_subjects"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                />
                <div>
                  <div class="font-medium text-gray-800">{{ subject.name }}</div>
                  <div v-if="subject.description" class="text-sm text-gray-600">{{ subject.description }}</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Dynamic Inspection Items -->
        <div v-if="form.selected_subjects.length > 0" class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-list text-blue-600"></i>
            Inspection Details
          </h2>

          <div v-for="subjectId in form.selected_subjects" :key="subjectId" class="mb-8">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
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
                  <button 
                    type="button"
                    @click="addSubjectInstance(subjectId)"
                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition flex items-center gap-1"
                  >
                    <i class="fa-solid fa-plus"></i>
                    Add More
                  </button>
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
            <div v-for="(instance, instanceIndex) in getSubjectInstances(subjectId)" :key="instanceIndex" class="mb-6 border border-gray-200 rounded-lg p-4">
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
          </div>
        </div>

        <!-- General Notes -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-comment-dots text-blue-600"></i>
            Catatan/Komentar
          </h2>
          
          <textarea 
            v-model="form.general_notes"
            rows="4"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Tambahkan catatan umum tentang inspection..."
          ></textarea>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-4">
          <button 
            type="button" 
            @click="$inertia.visit(route('dynamic-inspections.index'))"
            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
          >
            Batal
          </button>
          <button 
            type="button" 
            @click="submitForm"
            :disabled="form.selected_subjects.length === 0"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2"
          >
            <i class="fa-solid fa-check"></i>
            Selesai
          </button>
        </div>
      </form>

      <!-- Camera Modal -->
      <CameraModal 
        v-if="showCameraModal" 
        :mode="cameraMode"
        @close="closeCamera"
        @capture="onCameraCapture"
      />

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
import { ref, reactive, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import CameraModal from '@/Pages/MaintenanceOrder/CameraModal.vue';

const props = defineProps({
  outlets: Array,
  subjects: Array,
  user: Object
});

const page = usePage();
const authUser = computed(() => page.props.auth.user);

// Computed properties for user data
const userJabatan = computed(() => {
  if (!props.user) return 'Staff';
  if (props.user.jabatan?.nama_jabatan) return props.user.jabatan.nama_jabatan;
  return 'Staff';
});

const userDivisi = computed(() => {
  if (!props.user) return 'General';
  if (props.user.divisi?.nama_divisi) return props.user.divisi.nama_divisi;
  return 'General';
});

const loading = ref(false);
const subjectSaveStatus = ref({}); // Track save status for each subject

const form = reactive({
  outlet_id: '',
  inspection_date: new Date().toISOString().split('T')[0],
  selected_subjects: [],
  general_notes: '',
  outlet_leader: '',
  details: {},
  subject_instances: {}, // Store multiple instances per subject
  saved_subjects: {} // Track which subjects have been saved
});

// Initialize form with user data
const initializeForm = () => {
  // Set default values from user
  if (props.user) {
    // form.outlet_leader = props.user.nama_lengkap || ''; // Leave outlet leader empty
  }
};

// Camera and Lightbox states
const showCameraModal = ref(false);
const cameraMode = ref('photo');
const currentCameraContext = ref(null); // { subjectId, instanceIndex, itemId }
const showLightboxModal = ref(false);
const lightboxImages = ref([]);
const currentImageIndex = ref(0);

// Initialize item data structure
const initializeItemData = () => {
  props.subjects.forEach(subject => {
    if (!form.details[subject.id]) {
      form.details[subject.id] = {};
    }
    if (!form.subject_instances[subject.id]) {
      form.subject_instances[subject.id] = [0]; // Start with one instance
    }
  });
};

// Get subject name by ID
const getSubjectName = (subjectId) => {
  const subject = props.subjects.find(s => s.id === subjectId);
  return subject ? subject.name : 'Unknown Subject';
};

// Get subject items by subject ID
const getSubjectItems = (subjectId) => {
  const subject = props.subjects.find(s => s.id === subjectId);
  return subject ? subject.active_items : [];
};

// Get subject instances
const getSubjectInstances = (subjectId) => {
  return form.subject_instances[subjectId] || [0];
};

// Add subject instance
const addSubjectInstance = (subjectId) => {
  if (!form.subject_instances[subjectId]) {
    form.subject_instances[subjectId] = [0];
  }
  const newInstanceIndex = form.subject_instances[subjectId].length;
  form.subject_instances[subjectId].push(newInstanceIndex);
};

// Remove subject instance
const removeSubjectInstance = (subjectId, instanceIndex) => {
  form.subject_instances[subjectId].splice(instanceIndex, 1);
  // Clean up data for removed instance
  if (form.details[subjectId]) {
    Object.keys(form.details[subjectId]).forEach(key => {
      if (key.includes(`_${instanceIndex}_`)) {
        delete form.details[subjectId][key];
      }
    });
  }
};

// Get item data for specific instance
const getItemData = (subjectId, instanceIndex, itemId) => {
  const key = `${subjectId}_${instanceIndex}_${itemId}`;
  if (!form.details[subjectId]) {
    form.details[subjectId] = {};
  }
  if (!form.details[subjectId][key]) {
    form.details[subjectId][key] = {
      subject_id: subjectId,
      item_id: itemId,
      is_checked: false,
      notes: '',
      documentation: [],
      documentation_preview: []
    };
  }
  return form.details[subjectId][key];
};

// Open camera
const openCamera = (subjectId, instanceIndex, itemId) => {
  currentCameraContext.value = { subjectId, instanceIndex, itemId };
  cameraMode.value = 'photo';
  showCameraModal.value = true;
};

// Open file upload
const openFileUpload = (subjectId, instanceIndex, itemId) => {
  console.log('Opening file upload for:', subjectId, instanceIndex, itemId);
  const fileInput = document.querySelector(`input[data-file-input="${subjectId}-${instanceIndex}-${itemId}"]`);
  console.log('File input found:', fileInput);
  if (fileInput) {
    fileInput.click();
  } else {
    console.error('File input not found for:', `${subjectId}-${instanceIndex}-${itemId}`);
  }
};

// Handle camera capture
const onCameraCapture = (dataUrl) => {
  if (currentCameraContext.value) {
    const { subjectId, instanceIndex, itemId } = currentCameraContext.value;
    const itemData = getItemData(subjectId, instanceIndex, itemId);
    
    // Convert dataURL to File
    const file = dataURLtoFile(dataUrl, `camera-capture-${Date.now()}.jpg`);
    
    itemData.documentation.push(file);
    itemData.documentation_preview.push({
      file: file,
      preview: dataUrl
    });
  }
  closeCamera();
};

// Close camera
const closeCamera = () => {
  showCameraModal.value = false;
  currentCameraContext.value = null;
};

// Handle file upload
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

// Remove file
const removeFile = (subjectId, instanceIndex, itemId, index) => {
  const itemData = getItemData(subjectId, instanceIndex, itemId);
  itemData.documentation.splice(index, 1);
  itemData.documentation_preview.splice(index, 1);
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

// Navigate lightbox images
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

// Convert dataURL to File
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
  if (!form.outlet_id) {
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih outlet terlebih dahulu!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  if (!form.inspection_date) {
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
    formData.append('outlet_id', form.outlet_id);
    formData.append('inspection_date', form.inspection_date);
    formData.append('general_notes', form.general_notes || '');
    formData.append('outlet_leader', form.outlet_leader || '');
    formData.append('subject_id', subjectId);
    
    // Add details for this subject only
    const details = [];
    const subjectData = form.details[subjectId];
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

    const response = await fetch(route('dynamic-inspections.store-subject'), {
      method: 'POST',
      body: formData,
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    });

    if (response.ok) {
      const result = await response.json();
      subjectSaveStatus.value[subjectId] = 'saved';
      form.saved_subjects[subjectId] = result.inspection_id;
      
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
const submitForm = async () => {
  if (form.selected_subjects.length === 0) {
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih minimal satu subject untuk inspection!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }

  // Check if all subjects are saved
  const unsavedSubjects = form.selected_subjects.filter(subjectId => 
    !form.saved_subjects[subjectId]
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
        // Redirect to index
        router.visit(route('dynamic-inspections.index'));
      }
    });
    return;
  }

  // Get the first saved inspection ID to complete
  const firstSavedInspectionId = Object.values(form.saved_subjects)[0];
  
  if (firstSavedInspectionId) {
    try {
      const response = await fetch(route('dynamic-inspections.complete', firstSavedInspectionId), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'Content-Type': 'application/json'
        }
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
  } else {
    // No saved inspections, just redirect
    router.visit(route('dynamic-inspections.index'));
  }
};

// Initialize on mount
initializeItemData();
initializeForm();
</script>
