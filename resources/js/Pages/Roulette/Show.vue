<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  roulette: Object,
});

function goBack() {
  router.visit('/roulette');
}

function editRoulette() {
  router.visit(`/roulette/${props.roulette.id}/edit`);
}
</script>

<template>
  <AppLayout title="Detail Data Roulette">
    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-8 mt-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-purple-800">Detail Data Roulette</h1>
        <button @click="editRoulette" class="px-4 py-2 rounded bg-yellow-500 text-white hover:bg-yellow-600 transition">
          <i class="fa-solid fa-pen-to-square mr-2"></i>Edit
        </button>
      </div>
      
      <div class="space-y-6">
        <div class="bg-gray-50 rounded-lg p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Data</h2>
          
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-600">Nama</label>
              <p class="mt-1 text-lg font-semibold text-gray-900">{{ roulette.nama || '-' }}</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-600">Email</label>
              <p class="mt-1 text-lg text-gray-900">{{ roulette.email || '-' }}</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-600">No HP</label>
              <p class="mt-1 text-lg text-gray-900">{{ roulette.no_hp || '-' }}</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-600">Tanggal Dibuat</label>
              <p class="mt-1 text-lg text-gray-900">{{ roulette.created_at ? new Date(roulette.created_at).toLocaleDateString('id-ID') : '-' }}</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-600">Terakhir Diupdate</label>
              <p class="mt-1 text-lg text-gray-900">{{ roulette.updated_at ? new Date(roulette.updated_at).toLocaleDateString('id-ID') : '-' }}</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="flex justify-end gap-2 mt-6">
        <button @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300 transition">
          Kembali
        </button>
      </div>
    </div>
  </AppLayout>
</template> 