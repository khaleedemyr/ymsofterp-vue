<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-blue-800 flex items-center gap-3">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Outlet/HO Inspection
        </h1>
        <a 
          :href="route('dynamic-inspections.create')"
          class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
        >
          <i class="fa-solid fa-plus"></i>
          Buat Inspection Baru
        </a>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filter Outlet</label>
            <select 
              v-model="filters.outlet_id" 
              @change="applyFilters"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in uniqueOutlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filter Status</label>
            <select 
              v-model="filters.status" 
              @change="applyFilters"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="completed">Completed</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Filter Tanggal</label>
            <input 
              v-model="filters.date" 
              @change="applyFilters"
              type="date" 
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>
      </div>

      <!-- Inspections Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="inspection in inspections.data" 
          :key="inspection.id" 
          class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100"
        >
          <!-- Card Header -->
          <div class="p-6 pb-4">
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ inspection.inspection_number }}</h3>
                <p class="text-sm text-gray-600">{{ inspection.outlet?.nama_outlet || 'N/A' }}</p>
              </div>
              <span :class="[
                'px-3 py-1 rounded-full text-xs font-semibold',
                inspection.status === 'completed' 
                  ? 'bg-green-100 text-green-800' 
                  : 'bg-yellow-100 text-yellow-800'
              ]">
                {{ inspection.status === 'completed' ? 'Completed' : 'Draft' }}
              </span>
            </div>
          </div>

          <!-- Card Body -->
          <div class="px-6 pb-4">
            <div class="space-y-3">
              <!-- PIC Info -->
              <div class="flex items-center gap-3">
                <div v-if="inspection.creator?.avatar" class="w-12 h-12 rounded-full overflow-hidden border-2 border-white shadow-lg cursor-pointer hover:shadow-xl transition-all" @click="openImageModal(getImageUrl(inspection.creator.avatar))">
                  <img :src="getImageUrl(inspection.creator.avatar)" :alt="inspection.pic_name" class="w-full h-full object-cover hover:scale-105 transition-transform" />
                </div>
                <div v-else class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold border-2 border-white shadow-lg">
                  {{ getInitials(inspection.pic_name || 'U') }}
                </div>
                <div class="flex-1">
                  <p class="text-sm font-medium text-gray-900">{{ inspection.pic_name }}</p>
                  <p class="text-xs text-gray-500">{{ inspection.pic_position }}</p>
                  <p class="text-xs text-gray-500">{{ inspection.pic_division }}</p>
                </div>
              </div>

              <!-- Date Info -->
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                  <i class="fa-solid fa-calendar text-green-600"></i>
                </div>
                <div class="flex-1">
                  <p class="text-sm font-medium text-gray-900">Inspection Date</p>
                  <p class="text-xs text-gray-500">{{ formatDate(inspection.inspection_date) }}</p>
                </div>
              </div>

              <!-- Outlet Leader -->
              <div v-if="inspection.outlet_leader" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                  <i class="fa-solid fa-user-tie text-purple-600"></i>
                </div>
                <div class="flex-1">
                  <p class="text-sm font-medium text-gray-900">Outlet Leader</p>
                  <p class="text-xs text-gray-500">{{ inspection.outlet_leader }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Card Footer -->
          <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <div class="flex items-center justify-between">
              <div class="flex gap-2">
                <a 
                  :href="route('dynamic-inspections.show', inspection.id)"
                  class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                  title="View Details"
                >
                  <i class="fa-solid fa-eye"></i>
                  View
                </a>
                <a 
                  :href="route('dynamic-inspections.edit', inspection.id)"
                  class="inline-flex items-center gap-2 px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors"
                  title="Edit"
                >
                  <i class="fa-solid fa-edit"></i>
                  Edit
                </a>
              </div>
              <button 
                @click="deleteInspection(inspection.id)"
                class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors"
                title="Delete"
              >
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- No Data Message -->
      <div v-if="inspections.data.length === 0" class="bg-white rounded-xl shadow-lg p-12 text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-clipboard-list text-gray-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-600 mb-2">Tidak ada Dynamic Inspection</h3>
        <p class="text-gray-500 mb-6">Belum ada inspection yang dibuat. Klik tombol di atas untuk membuat inspection baru.</p>
        <a 
          :href="route('dynamic-inspections.create')"
          class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
        >
          <i class="fa-solid fa-plus"></i>
          Buat Inspection Baru
        </a>
      </div>

      <!-- Pagination -->
      <div v-if="inspections.links && inspections.data.length > 0" class="mt-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
              <a 
                v-if="inspections.prev_page_url"
                :href="inspections.prev_page_url"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                Previous
              </a>
              <a 
                v-if="inspections.next_page_url"
                :href="inspections.next_page_url"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                Next
              </a>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Showing {{ inspections.from }} to {{ inspections.to }} of {{ inspections.total }} results
                </p>
              </div>
              <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                  <a 
                    v-for="link in inspections.links" 
                    :key="link.label"
                    :href="link.url"
                    v-html="link.label"
                    :class="[
                      'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                      link.active 
                        ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' 
                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                    ]"
                  ></a>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lightbox Modal -->
      <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeImageModal">
        <div class="relative max-w-4xl max-h-full p-4">
          <button 
            @click="closeImageModal"
            class="absolute -top-4 -right-4 bg-white rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors z-10"
          >
            <i class="fa-solid fa-times text-gray-600"></i>
          </button>
          <img 
            :src="selectedImageUrl" 
            :alt="'Avatar preview'"
            class="max-w-full max-h-full object-contain rounded-lg shadow-2xl"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  inspections: Object
});

const filters = ref({
  outlet_id: '',
  status: '',
  date: ''
});

// Lightbox functionality
const showImageModal = ref(false);
const selectedImageUrl = ref('');

// Get unique outlets from inspections
const uniqueOutlets = computed(() => {
  const outlets = new Map();
  props.inspections.data.forEach(inspection => {
    if (inspection.outlet) {
      outlets.set(inspection.outlet.id_outlet, inspection.outlet);
    }
  });
  return Array.from(outlets.values());
});

// Format date
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};

// Apply filters
const applyFilters = () => {
  const params = new URLSearchParams();
  
  if (filters.value.outlet_id) {
    params.append('outlet_id', filters.value.outlet_id);
  }
  if (filters.value.status) {
    params.append('status', filters.value.status);
  }
  if (filters.value.date) {
    params.append('date', filters.value.date);
  }

  const queryString = params.toString();
  const url = queryString ? `${window.location.pathname}?${queryString}` : window.location.pathname;
  
  router.visit(url);
};

// Delete inspection
const deleteInspection = (id) => {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus inspection ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('dynamic-inspections.destroy', id), {
        onSuccess: () => {
          Swal.fire({
            title: 'Berhasil!',
            text: 'Inspection berhasil dihapus!',
            icon: 'success',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false
          });
        },
        onError: () => {
          Swal.fire({
            title: 'Error!',
            text: 'Gagal menghapus inspection. Silakan coba lagi.',
            icon: 'error',
            confirmButtonText: 'OK'
          });
        }
      });
    }
  });
};

// Lightbox methods
function getImageUrl(avatar) {
  return `/storage/${avatar}`;
}

function openImageModal(imageUrl) {
  selectedImageUrl.value = imageUrl;
  showImageModal.value = true;
}

function closeImageModal() {
  showImageModal.value = false;
  selectedImageUrl.value = '';
}

function getInitials(name) {
  if (!name) return 'U';
  const words = name.split(' ');
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase();
  }
  return words[0][0].toUpperCase();
}
</script>
