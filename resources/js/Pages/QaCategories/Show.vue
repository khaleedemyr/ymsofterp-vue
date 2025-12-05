<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  category: Object
});

const isDeleting = ref(false);

function edit() {
  router.visit(`/qa-categories/${props.category.id}/edit`);
}

function back() {
  router.visit('/qa-categories');
}

async function hapus() {
  const result = await Swal.fire({
    title: 'Nonaktifkan QA Category?',
    text: `Yakin ingin menonaktifkan QA Category "${props.category.categories}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Nonaktifkan!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  isDeleting.value = true;
  router.delete(`/qa-categories/${props.category.id}`, {
    onSuccess: () => {
      Swal.fire('Berhasil', 'QA Category berhasil dinonaktifkan!', 'success');
    },
    onFinish: () => {
      isDeleting.value = false;
    }
  });
}

async function toggleStatus() {
  const action = props.category.status === 'A' ? 'menonaktifkan' : 'mengaktifkan';
  const result = await Swal.fire({
    title: `${props.category.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'} QA Category?`,
    text: `Yakin ingin ${action} QA Category "${props.category.categories}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: `Ya, ${props.category.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'}!`,
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.patch(`/qa-categories/${props.category.id}/toggle-status`);
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      router.reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal mengubah status QA Category', 'error');
  }
}
</script>

<template>
  <AppLayout title="Detail QA Category">
    <div class="w-full py-8 px-4">
      <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
          <button @click="back" class="text-gray-500 hover:text-gray-700 transition">
            <i class="fa-solid fa-arrow-left text-xl"></i>
          </button>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-list text-blue-500"></i> Detail QA Category
          </h1>
        </div>

        <!-- Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Info -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg p-8">
              <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-800">Informasi QA Category</h2>
                <div class="flex gap-2">
                  <button @click="edit" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-edit mr-2"></i>Edit
                  </button>
                  <button @click="toggleStatus" :class="[
                    'px-4 py-2 rounded-xl font-medium transition',
                    category.status === 'A' ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white'
                  ]">
                    <i :class="category.status === 'A' ? 'fa-solid fa-times-circle mr-2' : 'fa-solid fa-check-circle mr-2'"></i>
                    {{ category.status === 'A' ? 'Nonaktifkan' : 'Aktifkan' }}
                  </button>
                </div>
              </div>

              <div class="space-y-6">
                <!-- Kode Categories -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Kode Categories</label>
                  <p class="text-lg font-mono text-gray-800">{{ category.kode_categories }}</p>
                </div>

                <!-- Categories -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Categories</label>
                  <p class="text-lg font-semibold text-gray-800">{{ category.categories }}</p>
                </div>

                <!-- Status -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Status</label>
                  <span :class="[
                    'inline-flex px-3 py-1 rounded-full text-sm font-semibold',
                    category.status === 'A' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                  ]">
                    <i :class="category.status === 'A' ? 'fa-solid fa-check-circle mr-2' : 'fa-solid fa-times-circle mr-2'"></i>
                    {{ category.status === 'A' ? 'Aktif' : 'Non-Aktif' }}
                  </span>
                </div>

                <!-- Created At -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Dibuat Pada</label>
                  <p class="text-gray-800">{{ new Date(category.created_at).toLocaleString('id-ID') }}</p>
                </div>

                <!-- Updated At -->
                <div>
                  <label class="block text-sm font-medium text-gray-500 mb-2">Diupdate Pada</label>
                  <p class="text-gray-800">{{ new Date(category.updated_at).toLocaleString('id-ID') }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Status Card -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Status</h3>
              <div class="text-center">
                <div :class="[
                  'w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center',
                  category.status === 'A' ? 'bg-green-100' : 'bg-red-100'
                ]">
                  <i :class="[
                    'text-2xl',
                    category.status === 'A' ? 'fa-solid fa-check-circle text-green-600' : 'fa-solid fa-times-circle text-red-600'
                  ]"></i>
                </div>
                <p :class="[
                  'text-lg font-semibold',
                  category.status === 'A' ? 'text-green-800' : 'text-red-800'
                ]">
                  {{ category.status === 'A' ? 'Aktif' : 'Non-Aktif' }}
                </p>
              </div>
            </div>

            <!-- Actions Card -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Aksi</h3>
              <div class="space-y-3">
                <button @click="edit" class="w-full px-4 py-3 bg-yellow-500 hover:bg-yellow-600 text-white rounded-xl font-medium transition flex items-center justify-center">
                  <i class="fa-solid fa-edit mr-2"></i>Edit QA Category
                </button>
                <button @click="toggleStatus" :class="[
                  'w-full px-4 py-3 rounded-xl font-medium transition flex items-center justify-center',
                  category.status === 'A' ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white'
                ]">
                  <i :class="category.status === 'A' ? 'fa-solid fa-times-circle mr-2' : 'fa-solid fa-check-circle mr-2'"></i>
                  {{ category.status === 'A' ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
                <button @click="hapus" :disabled="isDeleting" class="w-full px-4 py-3 bg-gray-500 hover:bg-gray-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-xl font-medium transition flex items-center justify-center">
                  <i v-if="isDeleting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                  <i v-else class="fa-solid fa-trash mr-2"></i>
                  {{ isDeleting ? 'Menghapus...' : 'Nonaktifkan' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
