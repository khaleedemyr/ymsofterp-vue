<template>
  <AppLayout title="Test Result">
    <div class="max-w-4xl mx-auto">
      <div class="bg-white shadow-sm rounded-lg">
        <div class="p-6">
          <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
              <i class="fa-solid fa-check text-green-600 text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Test Selesai!</h1>
            <p class="text-gray-600 mt-2">Terima kasih telah menyelesaikan test</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="font-semibold text-gray-900 mb-2">Informasi Test</h3>
              <div class="space-y-2 text-sm">
                <div><span class="font-medium">Judul:</span> {{ testResult.enroll_test.master_soal.judul }}</div>
                <div><span class="font-medium">Percobaan:</span> {{ testResult.attempt_number }}</div>
                <div><span class="font-medium">Status:</span> 
                  <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                    Selesai
                  </span>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
              <h3 class="font-semibold text-gray-900 mb-2">Waktu</h3>
              <div class="space-y-2 text-sm">
                <div><span class="font-medium">Mulai:</span> {{ formatDateTime(testResult.started_at) }}</div>
                <div><span class="font-medium">Selesai:</span> {{ formatDateTime(testResult.completed_at) }}</div>
                <div><span class="font-medium">Durasi:</span> {{ formatDuration(testResult.time_taken_seconds) }}</div>
              </div>
            </div>
          </div>


          <div class="text-center">
            <button @click="goToMyTests" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg inline-flex items-center gap-2">
              <i class="fa-solid fa-arrow-left"></i>
              Kembali ke My Tests
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  testResult: Object
});

function formatDateTime(dateTime) {
  if (!dateTime) return '-';
  return new Date(dateTime).toLocaleString('id-ID');
}

function formatDuration(seconds) {
  if (!seconds) return '-';
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  return `${minutes}m ${remainingSeconds}s`;
}

// Function untuk navigasi ke My Tests
function goToMyTests() {
  // Clear semua event listeners sebelum navigasi
  cleanupEventListeners();
  
  // Navigate menggunakan Inertia router
  router.visit('/my-tests');
}

// Function untuk cleanup event listeners
function cleanupEventListeners() {
  // Hapus semua event listeners yang mungkin masih aktif
  const beforeUnloadHandler = (e) => {
    e.preventDefault();
    e.returnValue = '';
  };
  
  const popstateHandler = () => {
    window.history.pushState(null, '', window.location.href);
  };
  
  window.removeEventListener('beforeunload', beforeUnloadHandler);
  window.removeEventListener('popstate', popstateHandler);
  
  // Reset history state
  window.history.replaceState(null, '', window.location.href);
}

// Clear localStorage dan event listeners saat test selesai
onMounted(() => {
  // Clear semua localStorage untuk test ini
  const keys = Object.keys(localStorage);
  keys.forEach(key => {
    if (key.startsWith(`answer_${props.testResult.id}_`)) {
      localStorage.removeItem(key);
    }
  });

  // Cleanup event listeners
  cleanupEventListeners();
});

// Cleanup saat component di-unmount
onUnmounted(() => {
  cleanupEventListeners();
});
</script>