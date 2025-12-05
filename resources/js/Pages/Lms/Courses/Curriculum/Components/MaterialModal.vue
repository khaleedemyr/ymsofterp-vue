<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close"></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-slate-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-white/20">
        <form @submit.prevent="saveMaterial">
          <!-- Header -->
          <div class="bg-gradient-to-r from-blue-600/20 via-purple-600/20 to-pink-600/20 px-6 pt-6 pb-4 border-b border-white/20">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-br from-green-500 to-emerald-500 sm:mx-0 sm:h-10 sm:w-10">
                <i class="fas fa-file-upload text-white text-lg"></i>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-xl leading-6 font-bold text-white">
                  Tambah Materi Baru
                </h3>
                <p class="mt-2 text-sm text-white/80">
                  Tambahkan materi pembelajaran untuk sesi ini.
                </p>
              </div>
            </div>
          </div>

          <!-- Form Content -->
          <div class="bg-slate-800 px-6 pt-6 pb-6">
            <div class="space-y-4">
              <!-- Material Title -->
              <div>
                <label for="title" class="block text-sm font-medium text-white mb-2">
                  Judul Materi *
                </label>
                <input
                  id="title"
                  v-model="form.title"
                  type="text"
                  required
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="Contoh: Slide Presentasi"
                />
              </div>

              <!-- Material Description -->
              <div>
                <label for="description" class="block text-sm font-medium text-white mb-2">
                  Deskripsi Materi
                </label>
                <textarea
                  id="description"
                  v-model="form.description"
                  rows="3"
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="Jelaskan isi materi ini..."
                ></textarea>
              </div>

              <!-- Material Type -->
              <div>
                <label for="material_type" class="block text-sm font-medium text-white mb-2">
                  Jenis Materi *
                </label>
                <select
                  id="material_type"
                  v-model="form.material_type"
                  required
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white"
                >
                  <option value="" class="bg-slate-700 text-white">Pilih Jenis Materi</option>
                  <option value="pdf" class="bg-slate-700 text-white">PDF Document</option>
                  <option value="image" class="bg-slate-700 text-white">Image/Photo</option>
                  <option value="video" class="bg-slate-700 text-white">Video</option>
                  <option value="document" class="bg-slate-700 text-white">Document (Word, Excel, etc.)</option>
                  <option value="link" class="bg-slate-700 text-white">External Link</option>
                </select>
              </div>

              <!-- File Upload (for non-link types) -->
              <div v-if="form.material_type && form.material_type !== 'link'">
                <label for="file" class="block text-sm font-medium text-white mb-2">
                  File Materi *
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-white/20 border-dashed rounded-xl bg-slate-700/50 hover:bg-slate-700 transition-colors">
                  <div class="space-y-1 text-center">
                    <i class="fas fa-cloud-upload-alt text-4xl text-white/60 mx-auto"></i>
                    <div class="flex text-sm text-white/80">
                      <label for="file" class="relative cursor-pointer bg-slate-600 rounded-lg font-medium text-blue-300 hover:text-blue-200 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500 px-4 py-2 transition-colors">
                        <span>Upload file</span>
                        <input
                          id="file"
                          ref="fileInput"
                          type="file"
                          class="sr-only"
                          @change="handleFileChange"
                          :accept="getFileAcceptTypes()"
                        />
                      </label>
                      <p class="pl-1">atau drag and drop</p>
                    </div>
                    <p class="text-xs text-white/60">
                      {{ getFileTypeDescription() }}
                    </p>
                  </div>
                </div>
                <div v-if="selectedFile" class="mt-2 text-sm text-white/70">
                  File dipilih: {{ selectedFile.name }}
                </div>
              </div>

              <!-- External URL (for link type) -->
              <div v-if="form.material_type === 'link'">
                <label for="external_url" class="block text-sm font-medium text-white mb-2">
                  URL Eksternal *
                </label>
                <input
                  id="external_url"
                  v-model="form.external_url"
                  type="url"
                  required
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="https://example.com/materi"
                />
              </div>

              <!-- Order Number -->
              <div>
                <label for="order_number" class="block text-sm font-medium text-white mb-2">
                  Urutan *
                </label>
                <input
                  id="order_number"
                  v-model="form.order_number"
                  type="number"
                  min="1"
                  required
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-indigo-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="1"
                />
              </div>

              <!-- Estimated Duration -->
              <div>
                <label for="estimated_duration_minutes" class="block text-sm font-medium text-gray-700">
                  Durasi Estimasi (menit)
                </label>
                <input
                  id="estimated_duration_minutes"
                  v-model="form.estimated_duration_minutes"
                  type="number"
                  min="1"
                  class="mt-1 block w-full bg-slate-700 border border-white/20 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-white placeholder-white/50"
                  placeholder="15"
                />
              </div>

              <!-- Downloadable Checkbox -->
              <div class="flex items-center">
                <input
                  id="is_downloadable"
                  v-model="form.is_downloadable"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-white/20 rounded bg-slate-700"
                />
                <label for="is_downloadable" class="ml-2 block text-sm text-white">
                  Bisa didownload oleh peserta
                </label>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-slate-700 px-6 py-4 sm:flex sm:flex-row-reverse border-t border-white/20">
            <button
              type="submit"
              :disabled="saving || !canSubmit"
              class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-lg px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-base font-medium text-white hover:from-green-600 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 transform hover:scale-105"
            >
              <i v-if="saving" class="fas fa-spinner fa-spin mr-2"></i>
              {{ saving ? 'Menyimpan...' : 'Tambah Materi' }}
            </button>
            <button
              type="button"
              @click="close"
              class="mt-3 w-full inline-flex justify-center rounded-xl border border-white/20 shadow-lg px-6 py-3 bg-slate-600 text-base font-medium text-white hover:bg-slate-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-300"
            >
              Batal
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  curriculumItem: {
    type: Object,
    required: true
  },
  courseId: {
    type: [String, Number],
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])
const { showToast } = useToast()

const saving = ref(false)
const selectedFile = ref(null)
const fileInput = ref(null)

const form = ref({
  title: '',
  description: '',
  material_type: '',
  order_number: 1,
  estimated_duration_minutes: 15,
  is_downloadable: true
})

const canSubmit = computed(() => {
  if (!form.value.title || !form.value.material_type) return false
  
  if (form.value.material_type === 'link') {
    return !!form.value.external_url
  } else {
    return !!selectedFile.value
  }
})

const getFileAcceptTypes = () => {
  switch (form.value.material_type) {
    case 'pdf':
      return '.pdf'
    case 'image':
      return 'image/*'
    case 'video':
      return 'video/*'
    case 'document':
      return '.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt'
    default:
      return '*'
  }
}

const getFileTypeDescription = () => {
  switch (form.value.material_type) {
    case 'pdf':
      return 'PDF files up to 100MB'
    case 'image':
      return 'Image files (JPG, PNG, GIF) up to 100MB'
    case 'video':
      return 'Video files (MP4, AVI, MOV) up to 100MB'
    case 'document':
      return 'Document files (Word, Excel, PowerPoint) up to 100MB'
    default:
      return 'All files up to 100MB'
  }
}

const handleFileChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    // Check file size (100MB limit)
    if (file.size > 100 * 1024 * 1024) {
      showToast('File terlalu besar. Maksimal 100MB.', 'error')
      event.target.value = ''
      selectedFile.value = null
      return
    }
    selectedFile.value = file
  }
}

const saveMaterial = async () => {
  try {
    saving.value = true

    const formData = new FormData()
    formData.append('title', form.value.title)
    formData.append('description', form.value.description)
    formData.append('material_type', form.value.material_type)
    formData.append('order_number', form.value.order_number)
    formData.append('estimated_duration_minutes', form.value.estimated_duration_minutes)
    formData.append('is_downloadable', form.value.is_downloadable)

    if (form.value.material_type === 'link') {
      formData.append('external_url', form.value.external_url)
    } else if (selectedFile.value) {
      formData.append('file', selectedFile.value)
    }

    const response = await fetch(`/lms/courses/${props.courseId}/curriculum/sessions/${props.curriculumItem.id}/materials`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: formData
    })

    const data = await response.json()

    if (data.success) {
      showToast('Materi berhasil ditambahkan', 'success')
      emit('saved', data.material)
    } else {
      showToast(data.message || 'Error adding material', 'error')
    }
  } catch (error) {
    console.error('Error adding material:', error)
    showToast('Error adding material', 'error')
  } finally {
    saving.value = false
  }
}

const close = () => {
  // Reset form
  form.value = {
    title: '',
    description: '',
    material_type: '',
    order_number: 1,
    estimated_duration_minutes: 15,
    is_downloadable: true
  }
  selectedFile.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
  emit('close')
}
</script>
