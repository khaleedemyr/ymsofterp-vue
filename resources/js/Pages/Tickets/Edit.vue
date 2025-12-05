<template>
  <AppLayout title="Edit Ticket">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-green-500"></i> Edit Ticket - {{ ticket.ticket_number }}
        </h1>
        <div class="flex gap-2">
          <button @click="viewTicket" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-colors">
            <i class="fa-solid fa-eye mr-2"></i> View
          </button>
          <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition-colors">
            <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
          </button>
        </div>
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

            <!-- Status -->
            <div>
              <label for="status_id" class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
              <select
                id="status_id"
                v-model="form.status_id"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                :class="{ 'border-red-500': errors.status_id }"
              >
                <option value="">Pilih Status</option>
                <option v-for="status in statuses" :key="status.id" :value="status.id">
                  {{ status.name }}
                </option>
              </select>
              <p v-if="errors.status_id" class="text-red-500 text-xs mt-1">{{ errors.status_id[0] }}</p>
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

            <!-- Existing Attachments -->
            <div v-if="ticket.attachments && ticket.attachments.length > 0" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Existing Attachments</label>
              <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <div
                  v-for="attachment in ticket.attachments"
                  :key="attachment.id"
                  class="relative"
                >
                  <!-- Image Thumbnail -->
                  <div v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')" class="relative group">
                    <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg overflow-hidden cursor-pointer hover:shadow-lg transition-all duration-200">
                      <img
                        :src="`/storage/${attachment.file_path}`"
                        :alt="attachment.file_name"
                        class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                        @click="openLightbox(attachment)"
                      />
                    </div>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                      <div class="opacity-0 group-hover:opacity-100 bg-white bg-opacity-90 text-gray-800 px-3 py-1 rounded-full text-sm transition-all duration-200 flex items-center gap-2">
                        <i class="fas fa-search-plus"></i>
                        <span>View</span>
                      </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent text-white p-2">
                      <p class="text-xs truncate font-medium">{{ attachment.file_name }}</p>
                    </div>
                  </div>
                  
                  <!-- Non-Image Files -->
                  <div v-else class="relative group">
                    <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg flex flex-col items-center justify-center p-2 cursor-pointer">
                      <i class="fas fa-file-pdf text-red-500 text-2xl mb-1" v-if="attachment.mime_type === 'application/pdf'"></i>
                      <i class="fas fa-file-alt text-gray-500 text-2xl mb-1" v-else></i>
                      <p class="text-xs text-gray-600 text-center truncate w-full">{{ attachment.file_name }}</p>
                    </div>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-200 flex items-center justify-center">
                      <a
                        :href="`/storage/${attachment.file_path}`"
                        target="_blank"
                        class="opacity-0 group-hover:opacity-100 bg-white bg-opacity-90 text-gray-800 px-3 py-1 rounded-full text-sm transition-all duration-200"
                      >
                        <i class="fas fa-download mr-1"></i>Download
                      </a>
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
              class="bg-gradient-to-r from-green-500 to-green-700 text-white px-6 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-save"></i>
              Update Ticket
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Lightbox Modal -->
    <div v-if="lightbox.show" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50" @click="closeLightbox">
      <div class="relative max-w-6xl max-h-full p-4" @click.stop>
        <!-- Close Button -->
        <button 
          @click="closeLightbox"
          class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-all duration-200 hover:bg-opacity-70"
        >
          <i class="fas fa-times"></i>
        </button>

        <!-- Navigation Arrows -->
        <button 
          v-if="lightbox.currentIndex > 0"
          @click="previousImage"
          class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white text-3xl hover:text-gray-300 z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-all duration-200 hover:bg-opacity-70"
        >
          <i class="fas fa-chevron-left"></i>
        </button>

        <button 
          v-if="lightbox.currentIndex < lightbox.images.length - 1"
          @click="nextImage"
          class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white text-3xl hover:text-gray-300 z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-all duration-200 hover:bg-opacity-70"
        >
          <i class="fas fa-chevron-right"></i>
        </button>

        <!-- Main Image -->
        <div class="relative">
          <img 
            :src="lightbox.currentImage.src" 
            :alt="lightbox.currentImage.name"
            class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl"
          />
          
          <!-- Image Info -->
          <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent text-white p-4 rounded-b-lg">
            <div class="flex justify-between items-center">
              <div>
                <p class="text-lg font-semibold">{{ lightbox.currentImage.name }}</p>
                <p class="text-sm opacity-80">{{ lightbox.currentIndex + 1 }} of {{ lightbox.images.length }}</p>
              </div>
              <div class="flex gap-2">
                <a 
                  :href="lightbox.currentImage.src" 
                  target="_blank"
                  class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-3 py-2 rounded-lg transition-all duration-200 flex items-center gap-2"
                >
                  <i class="fas fa-external-link-alt"></i>
                  <span>Open</span>
                </a>
                <a 
                  :href="lightbox.currentImage.src" 
                  download
                  class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-3 py-2 rounded-lg transition-all duration-200 flex items-center gap-2"
                >
                  <i class="fas fa-download"></i>
                  <span>Download</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Thumbnail Strip -->
        <div v-if="lightbox.images.length > 1" class="flex justify-center mt-4 gap-2 overflow-x-auto max-w-full">
          <div 
            v-for="(image, index) in lightbox.images" 
            :key="index"
            @click="goToImage(index)"
            class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden cursor-pointer border-2 transition-all duration-200"
            :class="index === lightbox.currentIndex ? 'border-white' : 'border-transparent hover:border-gray-400'"
          >
            <img 
              :src="image.src" 
              :alt="image.name"
              class="w-full h-full object-cover"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  ticket: Object,
  categories: Array,
  priorities: Array,
  statuses: Array,
  divisis: Array,
  outlets: Array,
});

const form = ref({
  title: '',
  description: '',
  category_id: '',
  priority_id: '',
  status_id: '',
  divisi_id: '',
  outlet_id: '',
});

const loading = ref(false);
const errors = ref({});

// Lightbox state
const lightbox = ref({
  show: false,
  currentIndex: 0,
  images: [],
  currentImage: {}
});
const calculatedDueDate = ref('');

onMounted(() => {
  // Initialize form with ticket data
  form.value = {
    title: props.ticket.title || '',
    description: props.ticket.description || '',
    category_id: props.ticket.category_id || '',
    priority_id: props.ticket.priority_id || '',
    status_id: props.ticket.status_id || '',
    divisi_id: props.ticket.divisi_id || '',
    outlet_id: props.ticket.outlet_id || '',
  };
  
  // Calculate initial due date
  calculateDueDate();
  
  // Add keyboard event listener
  document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  // Remove keyboard event listener
  document.removeEventListener('keydown', handleKeydown);
  // Restore body scroll if lightbox was open
  document.body.style.overflow = '';
});

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

async function submitForm() {
  loading.value = true;
  errors.value = {};

  try {
    const response = await axios.put(`/tickets/${props.ticket.id}`, form.value);
    if (response.data.success) {
      Swal.fire('Berhasil!', response.data.message, 'success');
      router.visit(`/tickets/${props.ticket.id}`);
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors;
      Swal.fire('Validasi Gagal', 'Mohon periksa kembali input Anda.', 'error');
    } else {
      Swal.fire('Error!', error.response?.data?.message || 'Gagal memperbarui ticket.', 'error');
    }
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.visit('/tickets');
}

// Lightbox methods
function openLightbox(attachment) {
  // Get all image attachments
  const imageAttachments = props.ticket.attachments.filter(att => 
    att.mime_type && att.mime_type.startsWith('image/')
  );
  
  // Find current attachment index
  const currentIndex = imageAttachments.findIndex(att => att.id === attachment.id);
  
  // Set lightbox data
  lightbox.value = {
    show: true,
    currentIndex: currentIndex,
    images: imageAttachments.map(att => ({
      src: `/storage/${att.file_path}`,
      name: att.file_name,
      id: att.id
    })),
    currentImage: {
      src: `/storage/${attachment.file_path}`,
      name: attachment.file_name,
      id: attachment.id
    }
  };
  
  // Prevent body scroll
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  lightbox.value.show = false;
  document.body.style.overflow = '';
}

function nextImage() {
  if (lightbox.value.currentIndex < lightbox.value.images.length - 1) {
    lightbox.value.currentIndex++;
    lightbox.value.currentImage = lightbox.value.images[lightbox.value.currentIndex];
  }
}

function previousImage() {
  if (lightbox.value.currentIndex > 0) {
    lightbox.value.currentIndex--;
    lightbox.value.currentImage = lightbox.value.images[lightbox.value.currentIndex];
  }
}

function goToImage(index) {
  lightbox.value.currentIndex = index;
  lightbox.value.currentImage = lightbox.value.images[index];
}

// Keyboard navigation for lightbox
function handleKeydown(event) {
  if (!lightbox.value.show) return;
  
  switch (event.key) {
    case 'Escape':
      closeLightbox();
      break;
    case 'ArrowLeft':
      previousImage();
      break;
    case 'ArrowRight':
      nextImage();
      break;
  }
}

function viewTicket() {
  router.visit(`/tickets/${props.ticket.id}`);
}
</script>
