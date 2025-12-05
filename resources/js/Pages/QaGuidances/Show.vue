<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  guidance: Object
});


const isDeleting = ref(false);

function edit() {
  router.visit(`/qa-guidances/${props.guidance.id}/edit`);
}

function back() {
  router.visit('/qa-guidances');
}

async function hapus() {
  const result = await Swal.fire({
    title: 'Nonaktifkan QA Guidance?',
    text: `Yakin ingin menonaktifkan QA Guidance untuk departemen "${props.guidance.departemen}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Nonaktifkan!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  isDeleting.value = true;
  router.delete(`/qa-guidances/${props.guidance.id}`, {
    onSuccess: () => {
      Swal.fire('Berhasil', 'QA Guidance berhasil dinonaktifkan!', 'success');
    },
    onFinish: () => {
      isDeleting.value = false;
    }
  });
}

async function toggleStatus() {
  const action = props.guidance.status === 'A' ? 'menonaktifkan' : 'mengaktifkan';
  const result = await Swal.fire({
    title: `${props.guidance.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'} QA Guidance?`,
    text: `Yakin ingin ${action} QA Guidance untuk departemen "${props.guidance.departemen}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: `Ya, ${props.guidance.status === 'A' ? 'Nonaktifkan' : 'Aktifkan'}!`,
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.patch(`/qa-guidances/${props.guidance.id}/toggle-status`);
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      router.reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal mengubah status QA Guidance', 'error');
  }
}
</script>

<template>
  <AppLayout title="Detail QA Guidance">
    <div class="w-full py-8 px-4">
      <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
          <button @click="back" class="text-gray-500 hover:text-gray-700 transition">
            <i class="fa-solid fa-arrow-left text-xl"></i>
          </button>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-check text-blue-500"></i> Detail QA Guidance
          </h1>
        </div>

        <!-- Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Info -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg p-8">
              <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-800">Informasi QA Guidance</h2>
                <div class="flex gap-2">
                  <button @click="edit" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-edit mr-2"></i>Edit
                  </button>
                  <button @click="toggleStatus" :class="[
                    'px-4 py-2 rounded-xl font-medium transition',
                    guidance.status === 'A' ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white'
                  ]">
                    <i :class="guidance.status === 'A' ? 'fa-solid fa-times-circle mr-2' : 'fa-solid fa-check-circle mr-2'"></i>
                    {{ guidance.status === 'A' ? 'Nonaktifkan' : 'Aktifkan' }}
                  </button>
                </div>
              </div>

              <div class="space-y-6">
                <!-- Title -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Title</label>
                  <p class="text-lg font-semibold text-gray-800">{{ guidance.title }}</p>
                </div>

                <!-- Departemen -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Departemen</label>
                  <p class="text-lg font-semibold text-gray-800">{{ guidance.departemen }}</p>
                </div>

                <!-- Categories -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Categories</label>
                  <div class="flex flex-wrap gap-2">
                    <span v-for="category in guidance.transformedCategories" :key="category.category_id" class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                      {{ category.category_name }}
                    </span>
                    <span v-if="!guidance.transformedCategories || guidance.transformedCategories.length === 0" class="text-gray-400">-</span>
                  </div>
                </div>

                <!-- Status -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Status</label>
                  <span :class="[
                    'inline-flex px-3 py-1 rounded-full text-sm font-semibold',
                    guidance.status === 'A' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                  ]">
                    <i :class="guidance.status === 'A' ? 'fa-solid fa-check-circle mr-2' : 'fa-solid fa-times-circle mr-2'"></i>
                    {{ guidance.status === 'A' ? 'Aktif' : 'Non-Aktif' }}
                  </span>
                </div>

                <!-- Created At -->
                <div class="border-b border-gray-200 pb-4">
                  <label class="block text-sm font-medium text-gray-500 mb-2">Dibuat Pada</label>
                  <p class="text-gray-800">{{ new Date(guidance.created_at).toLocaleString('id-ID') }}</p>
                </div>

                <!-- Updated At -->
                <div>
                  <label class="block text-sm font-medium text-gray-500 mb-2">Diupdate Pada</label>
                  <p class="text-gray-800">{{ new Date(guidance.updated_at).toLocaleString('id-ID') }}</p>
                </div>
              </div>
            </div>

            <!-- Complex Parameters Detail -->
            <div class="bg-white rounded-2xl shadow-lg p-8 mt-6">
              <h3 class="text-xl font-bold text-gray-800 mb-6">Categories & Parameters</h3>
              
              <div v-if="guidance.transformedCategories && guidance.transformedCategories.length > 0" class="space-y-8">
                <div v-for="(category, categoryIndex) in guidance.transformedCategories" :key="categoryIndex" class="border border-gray-200 rounded-xl p-6 bg-gray-50">
                  <div class="flex items-center justify-between mb-6">
                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                      <i class="fa-solid fa-tag text-blue-500 mr-2"></i>
                      {{ category.category_name }}
                    </h4>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                      {{ category.parameters?.length || 0 }} Parameter Pemeriksaan
                    </span>
                  </div>
                  
                  <!-- Parameters per Category -->
                  <div v-if="category.parameters && category.parameters.length > 0" class="space-y-4">
                    <div v-for="(parameter, parameterIndex) in category.parameters" :key="parameterIndex" class="border border-gray-300 rounded-lg p-4 bg-white">
                      <div class="flex items-center justify-between mb-4">
                        <h5 class="text-md font-semibold text-gray-800">{{ parameter.parameter_pemeriksaan }}</h5>
                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                          {{ parameter.details?.length || 0 }} Parameter
                        </span>
                      </div>
                      
                      <!-- Parameter Details -->
                      <div v-if="parameter.details && parameter.details.length > 0" class="space-y-3">
                        <div v-for="(detail, detailIndex) in parameter.details" :key="detailIndex" class="bg-gray-50 rounded-lg p-3">
                          <div class="flex items-center justify-between">
                            <div class="flex items-center">
                              <i class="fa-solid fa-cogs text-gray-400 mr-3"></i>
                              <div>
                                <p class="font-medium text-gray-800">{{ detail.parameter_name }}</p>
                                <p class="text-sm text-gray-500">Parameter ID: {{ detail.parameter_id }}</p>
                              </div>
                            </div>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-semibold">
                              {{ detail.point }} Point
                            </span>
                          </div>
                        </div>
                      </div>
                      
                      <div v-else class="text-center py-4 text-gray-400">
                        <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                        Tidak ada parameter detail
                      </div>
                    </div>
                  </div>
                  
                  <div v-else class="text-center py-4 text-gray-400">
                    <i class="fa-solid fa-clipboard-list mr-2"></i>
                    Tidak ada parameter pemeriksaan untuk category ini
                  </div>
                </div>
              </div>
              
              <div v-else class="text-center py-8 text-gray-400">
                <i class="fa-solid fa-clipboard-list text-4xl mb-4"></i>
                <p>Tidak ada categories dan parameters</p>
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
                  guidance.status === 'A' ? 'bg-green-100' : 'bg-red-100'
                ]">
                  <i :class="[
                    'text-2xl',
                    guidance.status === 'A' ? 'fa-solid fa-check-circle text-green-600' : 'fa-solid fa-times-circle text-red-600'
                  ]"></i>
                </div>
                <p :class="[
                  'text-lg font-semibold',
                  guidance.status === 'A' ? 'text-green-800' : 'text-red-800'
                ]">
                  {{ guidance.status === 'A' ? 'Aktif' : 'Non-Aktif' }}
                </p>
              </div>
            </div>

            <!-- Summary Card -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Summary</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">Title:</span>
                  <span class="font-semibold">{{ guidance.title }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Departemen:</span>
                  <span class="font-semibold">{{ guidance.departemen }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Categories:</span>
                  <span class="font-semibold">{{ guidance.transformedCategories?.length || 0 }} categories</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Total Parameter:</span>
                  <span class="font-semibold">{{ guidance.transformedCategories?.reduce((total, cat) => total + (cat.parameters?.length || 0), 0) || 0 }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Total Point:</span>
                  <span class="font-semibold">{{ guidance.transformedCategories?.reduce((total, cat) => 
                    total + (cat.parameters?.reduce((paramTotal, param) => 
                      paramTotal + (param.details?.reduce((detailTotal, detail) => detailTotal + (detail.point || 0), 0) || 0), 0) || 0), 0) || 0 }}</span>
                </div>
              </div>
            </div>

            <!-- Actions Card -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Aksi</h3>
              <div class="space-y-3">
                <button @click="edit" class="w-full px-4 py-3 bg-yellow-500 hover:bg-yellow-600 text-white rounded-xl font-medium transition flex items-center justify-center">
                  <i class="fa-solid fa-edit mr-2"></i>Edit QA Guidance
                </button>
                <button @click="toggleStatus" :class="[
                  'w-full px-4 py-3 rounded-xl font-medium transition flex items-center justify-center',
                  guidance.status === 'A' ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white'
                ]">
                  <i :class="guidance.status === 'A' ? 'fa-solid fa-times-circle mr-2' : 'fa-solid fa-check-circle mr-2'"></i>
                  {{ guidance.status === 'A' ? 'Nonaktifkan' : 'Aktifkan' }}
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
