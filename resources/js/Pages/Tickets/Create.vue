<template>
  <AppLayout title="Buat Ticket Baru">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-plus-circle text-blue-500"></i> Buat Ticket Baru
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition-colors">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Title -->
            <div class="md:col-span-2">
              <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
              <input
                type="text"
                id="title"
                v-model="form.title"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': errors.title }"
                placeholder="Masukkan title ticket"
              />
              <p v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title[0] }}</p>
            </div>

            <!-- Category -->
            <div>
              <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
              <select
                id="category_id"
                v-model="form.category_id"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': errors.category_id }"
              >
                <option value="">Pilih Category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
              <p v-if="errors.category_id" class="text-red-500 text-xs mt-1">{{ errors.category_id[0] }}</p>
            </div>

            <!-- Priority -->
            <div>
              <label for="priority_id" class="block text-sm font-medium text-gray-700 mb-1">Priority *</label>
              <select
                id="priority_id"
                v-model="form.priority_id"
                @change="calculateDueDate"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': errors.priority_id }"
              >
                <option value="">Pilih Priority</option>
                <option v-for="priority in priorities" :key="priority.id" :value="priority.id">
                  {{ priority.name }}
                </option>
              </select>
              <p v-if="errors.priority_id" class="text-red-500 text-xs mt-1">{{ errors.priority_id[0] }}</p>
              
              <!-- Due Date Display -->
              <div v-if="calculatedDueDate" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-center">
                  <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
                  <span class="text-sm font-medium text-blue-800">Due Date:</span>
                  <span class="text-sm text-blue-700 ml-2">{{ calculatedDueDate }}</span>
                </div>
              </div>
            </div>

            <!-- Divisi -->
            <div>
              <label for="divisi_id" class="block text-sm font-medium text-gray-700 mb-1">Divisi *</label>
              <select
                id="divisi_id"
                v-model="form.divisi_id"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': errors.divisi_id }"
              >
                <option value="">Pilih Divisi</option>
                <option v-for="divisi in divisis" :key="divisi.id" :value="divisi.id">
                  {{ divisi.nama_divisi }}
                </option>
              </select>
              <p v-if="errors.divisi_id" class="text-red-500 text-xs mt-1">{{ errors.divisi_id[0] }}</p>
            </div>

            <!-- Outlet -->
            <div>
              <label for="outlet_id" class="block text-sm font-medium text-gray-700 mb-1">Outlet *</label>
              <select
                id="outlet_id"
                v-model="form.outlet_id"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': errors.outlet_id }"
              >
                <option value="">Pilih Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="errors.outlet_id" class="text-red-500 text-xs mt-1">{{ errors.outlet_id[0] }}</p>
            </div>



            <!-- Description -->
            <div class="md:col-span-2">
              <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
              <textarea
                id="description"
                v-model="form.description"
                rows="6"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': errors.description }"
                placeholder="Masukkan deskripsi ticket"
              ></textarea>
              <p v-if="errors.description" class="text-red-500 text-xs mt-1">{{ errors.description[0] }}</p>
            </div>

            <!-- Attachments -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Attachments</label>
              <div class="space-y-3">
                <!-- Upload Button -->
                <div class="flex gap-2">
                  <button
                    type="button"
                    @click="openFileUpload"
                    class="flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors"
                  >
                    <i class="fas fa-upload mr-2"></i>
                    Upload File
                  </button>
                  <button
                    type="button"
                    @click="openCamera"
                    class="flex items-center px-4 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors"
                  >
                    <i class="fas fa-camera mr-2"></i>
                    Capture Camera
                  </button>
                </div>

                <!-- File Upload Input (Hidden) -->
                <input
                  ref="fileInput"
                  type="file"
                  multiple
                  accept="image/*,.pdf,.doc,.docx"
                  @change="handleFileUpload"
                  class="hidden"
                />

                <!-- Attachments Preview -->
                <div v-if="attachments.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                  <div
                    v-for="(attachment, index) in attachments"
                    :key="index"
                    class="relative"
                  >
                    <!-- Image Thumbnail -->
                    <div v-if="attachment.type.startsWith('image/')" class="relative group">
                      <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                        <img
                          :src="attachment.preview"
                          :alt="attachment.name"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <button
                        type="button"
                        @click="removeAttachment(index)"
                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 shadow-lg"
                      >
                        <i class="fas fa-times text-xs"></i>
                      </button>
                      <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-70 text-white p-1">
                        <p class="text-xs truncate">{{ attachment.name }}</p>
                      </div>
                    </div>
                    
                    <!-- Non-Image Files -->
                    <div v-else class="relative">
                      <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg flex flex-col items-center justify-center p-2">
                        <i class="fas fa-file-pdf text-red-500 text-2xl mb-1" v-if="attachment.type === 'application/pdf'"></i>
                        <i class="fas fa-file-alt text-gray-500 text-2xl mb-1" v-else></i>
                        <p class="text-xs text-gray-600 text-center truncate w-full">{{ attachment.name }}</p>
                      </div>
                      <button
                        type="button"
                        @click="removeAttachment(index)"
                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 shadow-lg"
                      >
                        <i class="fas fa-times text-xs"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-4 mt-8">
            <button
              type="button"
              @click="goBack"
              class="bg-gray-500 text-white px-6 py-2 rounded-xl hover:bg-gray-600 transition-colors"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-save"></i>
              Simpan Ticket
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  categories: Array,
  priorities: Array,
  divisis: Array,
  outlets: Array,
});

const form = ref({
  title: '',
  description: '',
  category_id: '',
  priority_id: '',
  divisi_id: '',
  outlet_id: '',
});

const loading = ref(false);
const errors = ref({});
const calculatedDueDate = ref('');
const attachments = ref([]);
const fileInput = ref(null);

// Calculate due date based on priority
function calculateDueDate() {
  if (form.value.priority_id) {
    const priority = props.priorities.find(p => p.id == form.value.priority_id);
    if (priority && priority.max_days) {
      const dueDate = new Date();
      dueDate.setDate(dueDate.getDate() + priority.max_days);
      calculatedDueDate.value = dueDate.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }
  } else {
    calculatedDueDate.value = '';
  }
}

// File upload functions
function openFileUpload() {
  fileInput.value.click();
}

function handleFileUpload(event) {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    if (file.size <= 10 * 1024 * 1024) { // 10MB limit
      const attachment = {
        file: file,
        name: file.name,
        type: file.type,
        size: file.size,
        preview: null
      };
      
      // Create preview for images
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
          attachment.preview = e.target.result;
        };
        reader.readAsDataURL(file);
      }
      
      attachments.value.push(attachment);
    } else {
      Swal.fire({
        title: 'File terlalu besar!',
        text: 'Ukuran file maksimal 10MB',
        icon: 'warning',
        confirmButtonText: 'OK'
      });
    }
  });
  event.target.value = '';
}

function removeAttachment(index) {
  attachments.value.splice(index, 1);
}

// Camera functions
function openCamera() {
  // Create camera modal similar to daily report
  const modal = document.createElement('div');
  modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
  modal.innerHTML = `
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">Capture Camera</h3>
        <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="space-y-4">
        <video id="camera-video" class="w-full h-64 bg-gray-200 rounded" autoplay></video>
        <div class="flex gap-2">
          <button id="capture-btn" class="flex-1 bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
            <i class="fas fa-camera mr-2"></i>Capture
          </button>
          <button onclick="this.closest('.fixed').remove()" class="flex-1 bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">
            Cancel
          </button>
        </div>
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  
  // Initialize camera
  navigator.mediaDevices.getUserMedia({ video: true })
    .then(stream => {
      const video = modal.querySelector('#camera-video');
      video.srcObject = stream;
      
      modal.querySelector('#capture-btn').addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        
        canvas.toBlob(blob => {
          const file = new File([blob], `camera-capture-${Date.now()}.jpg`, { type: 'image/jpeg' });
          const attachment = {
            file: file,
            name: file.name,
            type: file.type,
            size: file.size,
            preview: canvas.toDataURL('image/jpeg', 0.8)
          };
          
          attachments.value.push(attachment);
          
          // Stop camera and remove modal
          stream.getTracks().forEach(track => track.stop());
          modal.remove();
        }, 'image/jpeg', 0.8);
      });
    })
    .catch(err => {
      console.error('Camera access denied:', err);
      Swal.fire({
        title: 'Camera tidak dapat diakses',
        text: 'Pastikan browser memiliki izin untuk mengakses camera',
        icon: 'error',
        confirmButtonText: 'OK'
      });
      modal.remove();
    });
}

async function submitForm() {
  loading.value = true;
  errors.value = {};

  try {
    const formData = new FormData();
    
    // Add form fields
    Object.keys(form.value).forEach(key => {
      formData.append(key, form.value[key]);
    });
    
    // Add attachments
    attachments.value.forEach((attachment, index) => {
      formData.append(`attachments[${index}]`, attachment.file);
    });

    const response = await axios.post('/tickets', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    
    if (response.data.success) {
      Swal.fire('Berhasil!', response.data.message, 'success');
      router.visit('/tickets');
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors;
      Swal.fire('Validasi Gagal', 'Mohon periksa kembali input Anda.', 'error');
    } else {
      Swal.fire('Error!', error.response?.data?.message || 'Gagal membuat ticket.', 'error');
    }
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.visit('/tickets');
}
</script>
