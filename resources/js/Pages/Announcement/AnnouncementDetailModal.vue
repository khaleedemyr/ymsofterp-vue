<script setup>
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  announcement: Object,
});

const emit = defineEmits(['close']);

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  return d.toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function getTargetIcon(targetType) {
  const icons = {
    user: 'fa fa-user',
    jabatan: 'fa fa-id-badge',
    divisi: 'fa fa-building',
    level: 'fa fa-layer-group',
    outlet: 'fa fa-store'
  };
  return icons[targetType] || 'fa fa-users';
}

function closeModal() {
  emit('close');
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden animate-fade-in">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-500 to-blue-700 px-6 py-4 text-white">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <i class="fa-solid fa-bullhorn text-2xl"></i>
            <h3 class="text-xl font-bold">Detail Announcement</h3>
          </div>
          <button @click="closeModal" class="text-white hover:text-gray-200 transition-colors">
            <i class="fa fa-times text-xl"></i>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
        <div class="space-y-6">
          <!-- Judul -->
          <div class="bg-gray-50 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-2">
              <i class="fa fa-heading text-blue-500"></i>
              <span class="font-semibold text-gray-700">Judul</span>
            </div>
            <p class="text-lg font-medium text-gray-900">{{ announcement?.title }}</p>
          </div>

          <!-- Info Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Tanggal -->
            <div class="bg-gray-50 rounded-xl p-4">
              <div class="flex items-center gap-2 mb-2">
                <i class="fa fa-calendar text-blue-500"></i>
                <span class="font-semibold text-gray-700">Tanggal Dibuat</span>
              </div>
              <p class="text-gray-900">{{ formatDate(announcement?.created_at) }}</p>
            </div>

            <!-- Status -->
            <div class="bg-gray-50 rounded-xl p-4">
              <div class="flex items-center gap-2 mb-2">
                <i class="fa fa-info-circle text-blue-500"></i>
                <span class="font-semibold text-gray-700">Status</span>
              </div>
              <span :class="announcement?.status === 'Publish' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                    class="px-3 py-1 rounded-full text-sm font-semibold">
                <i :class="announcement?.status === 'Publish' ? 'fa fa-check' : 'fa fa-clock'" class="mr-1"></i>
                {{ announcement?.status }}
              </span>
            </div>
          </div>

          <!-- Target -->
          <div class="bg-gray-50 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-3">
              <i class="fa fa-users text-blue-500"></i>
              <span class="font-semibold text-gray-700">Target Penerima</span>
            </div>
            <div class="flex flex-wrap gap-2">
              <span v-for="t in announcement?.targets" :key="t.id"
                    class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm flex items-center gap-2">
                <i :class="getTargetIcon(t.target_type)" class="text-blue-500"></i>
                <span class="font-medium">{{ t.target_type }}:</span>
                <span>{{ t.target_name || t.target_id }}</span>
              </span>
            </div>
            <div v-if="!announcement?.targets?.length" class="text-gray-500 text-sm">
              <i class="fa fa-info-circle mr-1"></i>Tidak ada target spesifik
            </div>
          </div>

          <!-- Content -->
          <div v-if="announcement?.content" class="bg-gray-50 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-3">
              <i class="fa fa-file-text text-blue-500"></i>
              <span class="font-semibold text-gray-700">Konten</span>
            </div>
            <div class="bg-white rounded-lg p-4 border">
              <p class="text-gray-900 whitespace-pre-line leading-relaxed">{{ announcement.content }}</p>
            </div>
          </div>

          <!-- Files -->
          <div v-if="announcement?.files?.length" class="bg-gray-50 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-3">
              <i class="fa fa-paperclip text-blue-500"></i>
              <span class="font-semibold text-gray-700">Lampiran File</span>
            </div>
            <div class="space-y-2">
              <div v-for="f in announcement.files" :key="f.id" 
                   class="bg-white rounded-lg p-3 border hover:shadow-md transition-shadow">
                <a :href="`/storage/${f.file_path}`" 
                   target="_blank" 
                   class="flex items-center gap-3 text-blue-600 hover:text-blue-800 transition-colors">
                  <i class="fa fa-file text-lg"></i>
                  <div class="flex-1">
                    <p class="font-medium">{{ f.file_name }}</p>
                    <p class="text-sm text-gray-500">Klik untuk download</p>
                  </div>
                  <i class="fa fa-external-link-alt"></i>
                </a>
              </div>
            </div>
          </div>

          <!-- Image Preview -->
          <div v-if="announcement?.image" class="bg-gray-50 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-3">
              <i class="fa fa-image text-blue-500"></i>
              <span class="font-semibold text-gray-700">Header Image</span>
            </div>
            <div class="bg-white rounded-lg p-4 border">
              <img :src="`/storage/${announcement.image}`" 
                   :alt="announcement.title"
                   class="w-full h-auto rounded-lg max-h-64 object-cover">
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="bg-gray-50 px-6 py-4 border-t">
        <div class="flex justify-end">
          <button @click="closeModal"
                  class="px-6 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-all font-medium">
            <i class="fa fa-times mr-2"></i>Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fade-in {
  from { 
    opacity: 0; 
    transform: translateY(20px) scale(0.95);
  }
  to { 
    opacity: 1; 
    transform: translateY(0) scale(1);
  }
}
.animate-fade-in {
  animation: fade-in 0.3s cubic-bezier(.4,0,.2,1);
}
</style> 