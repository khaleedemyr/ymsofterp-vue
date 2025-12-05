<template>
  <AppLayout title="Edit Template Sertifikat">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          <i class="fas fa-edit mr-2"></i>
          Edit Template Sertifikat
        </h2>
        <div class="flex items-center space-x-2">
          <Link :href="route('lms.certificate-templates.index')" 
                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>
            Kembali
          </Link>
          <Link :href="route('lms.certificate-templates.preview', template.id)" 
                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
            <i class="fas fa-eye mr-2"></i>
            Preview
          </Link>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="updateTemplate" class="space-y-6">
          <!-- Basic Information -->
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                <i class="fas fa-info-circle mr-2"></i>
                Informasi Dasar
              </h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Nama Template <span class="text-red-500">*</span>
                </label>
                <input v-model="form.name" 
                       type="text" 
                       required
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       :class="{ 'border-red-500': errors.name }"
                       placeholder="Masukkan nama template">
                <p v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</p>
              </div>

              <!-- Description -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Deskripsi
                </label>
                <textarea v-model="form.description" 
                          rows="3"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          :class="{ 'border-red-500': errors.description }"
                          placeholder="Masukkan deskripsi template"></textarea>
                <p v-if="errors.description" class="text-red-500 text-sm mt-1">{{ errors.description }}</p>
              </div>

              <!-- Status -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Status <span class="text-red-500">*</span>
                </label>
                <select v-model="form.status" 
                        required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        :class="{ 'border-red-500': errors.status }">
                  <option value="active">Aktif</option>
                  <option value="inactive">Tidak Aktif</option>
                </select>
                <p v-if="errors.status" class="text-red-500 text-sm mt-1">{{ errors.status }}</p>
              </div>
            </div>
          </div>

          <!-- Background Image -->
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                <i class="fas fa-image mr-2"></i>
                Gambar Background
              </h3>
            </div>
            <div class="px-6 py-4">
              <!-- Current Image -->
              <div v-if="template.background_image" class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Gambar Saat Ini
                </label>
                <div class="relative inline-block">
                  <img :src="getImageUrl(template.background_image)" 
                       alt="Current background" 
                       class="max-w-xs h-auto border border-gray-300 rounded-lg">
                </div>
              </div>

              <!-- Upload New Image -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {{ template.background_image ? 'Ganti Gambar' : 'Upload Gambar' }}
                </label>
                <input @change="handleImageUpload" 
                       type="file" 
                       accept="image/*"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       :class="{ 'border-red-500': errors.background_image }">
                <p v-if="errors.background_image" class="text-red-500 text-sm mt-1">{{ errors.background_image }}</p>
                <p class="text-sm text-gray-500 mt-1">
                  Format yang didukung: JPEG, PNG, JPG. Maksimal 5MB.
                </p>
              </div>

              <!-- Image Preview -->
              <div v-if="imagePreview" class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Preview Gambar Baru
                </label>
                <img :src="imagePreview" 
                     alt="Preview" 
                     class="max-w-xs h-auto border border-gray-300 rounded-lg">
              </div>
            </div>
          </div>

          <!-- Text Positions & Style Settings -->
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                <i class="fas fa-cog mr-2"></i>
                Pengaturan Posisi & Style
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Konfigurasi posisi teks dan pengaturan style (akan dikembangkan lebih lanjut)
              </p>
            </div>
            <div class="px-6 py-4">
              <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                <div class="flex items-center">
                  <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                  <p class="text-sm text-yellow-700 dark:text-yellow-300">
                    Fitur pengaturan posisi teks dan style akan segera tersedia.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="flex items-center justify-end space-x-3">
            <Link :href="route('lms.certificate-templates.index')" 
                  class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              Batal
            </Link>
            <button type="submit" 
                    :disabled="loading"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg transition-colors">
              <span v-if="loading" class="flex items-center">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                Menyimpan...
              </span>
              <span v-else class="flex items-center">
                <i class="fas fa-save mr-2"></i>
                Simpan Perubahan
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  template: {
    type: Object,
    required: true
  },
  errors: {
    type: Object,
    default: () => ({})
  }
})

const loading = ref(false)
const imagePreview = ref(null)

const form = useForm({
  name: props.template.name || '',
  description: props.template.description || '',
  status: props.template.status || 'active',
  background_image: null,
  text_positions: props.template.text_positions || {},
  style_settings: props.template.style_settings || {}
})

const handleImageUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    form.background_image = file
    
    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const updateTemplate = () => {
  loading.value = true
  
  form.put(route('lms.certificate-templates.update', props.template.id), {
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Template sertifikat berhasil diperbarui.'
      })
    },
    onError: (errors) => {
      console.error('Update error:', errors)
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Terjadi kesalahan saat memperbarui template.'
      })
    },
    onFinish: () => {
      loading.value = false
    }
  })
}

const getImageUrl = (imagePath) => {
  if (!imagePath) return ''
  return `/storage/${imagePath}`
}
</script>
