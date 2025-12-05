<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 p-6">
      <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-2xl font-bold text-white">Buat Template Sertifikat</h1>
            <p class="text-white/70">Upload layout dan atur posisi text untuk sertifikat</p>
          </div>
          <Link :href="route('lms.certificate-templates.index')"
                class="px-4 py-2 bg-white/20 border border-white/30 rounded-xl text-white hover:bg-white/30 transition-all">
            <i class="fas fa-arrow-left mr-2"></i>
            Kembali
          </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
          <!-- Basic Info -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-white font-semibold mb-2">Nama Template</label>
              <input v-model="form.name" type="text" 
                     class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                     placeholder="Masukkan nama template">
              <div v-if="errors.name" class="text-red-400 text-sm mt-1">{{ errors.name }}</div>
            </div>
            
            <div>
              <label class="block text-white font-semibold mb-2">Status</label>
              <select v-model="form.status"
                      class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                <option value="active" style="background-color: #1f2937; color: white;">Aktif</option>
                <option value="inactive" style="background-color: #1f2937; color: white;">Nonaktif</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-white font-semibold mb-2">Deskripsi</label>
            <textarea v-model="form.description" rows="3"
                      class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Deskripsi template (opsional)"></textarea>
          </div>

          <!-- Background Image Upload -->
          <div>
            <label class="block text-white font-semibold mb-2">Background Sertifikat</label>
            <div class="border-2 border-dashed border-white/30 rounded-xl p-8 text-center hover:border-white/50 transition-all">
              <input ref="fileInput" type="file" @change="handleFileUpload" accept="image/*" class="hidden">
              
              <div v-if="!previewImage" @click="$refs.fileInput.click()" class="cursor-pointer">
                <i class="fas fa-cloud-upload-alt text-4xl text-white/50 mb-4"></i>
                <p class="text-white/70 mb-2">Klik untuk upload background sertifikat</p>
                <p class="text-white/50 text-sm">Format: JPG, PNG (Max: 5MB)</p>
              </div>

              <div v-else class="relative">
                <img :src="previewImage" alt="Preview" class="max-h-64 mx-auto rounded-lg">
                <button type="button" @click="removeImage" 
                        class="absolute top-2 right-2 w-8 h-8 bg-red-500 text-white rounded-full hover:bg-red-600 transition-all">
                  <i class="fas fa-times"></i>
                </button>
                <button type="button" @click="$refs.fileInput.click()" 
                        class="mt-4 px-4 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all">
                  Ganti Gambar
                </button>
              </div>
            </div>
            <div v-if="errors.background_image" class="text-red-400 text-sm mt-1">{{ errors.background_image }}</div>
          </div>

          <!-- Preview with Dummy Text -->
          <div v-if="previewImage" class="mb-6">
            <h3 class="text-lg font-semibold text-white mb-4">Preview dengan Dummy Data</h3>
            <div class="bg-white/5 border border-white/10 rounded-xl p-4">
              <div class="bg-white rounded-lg p-4 max-w-3xl mx-auto relative overflow-hidden">
                <div class="relative inline-block">
                  <img ref="backgroundImg" :src="previewImage" alt="Preview" class="w-full max-w-full h-auto" @load="onImageLoad">
                  
                  <!-- Text Overlays -->
                  <div v-if="imageLoaded" class="absolute inset-0">
                    <!-- Nama Peserta -->
                    <div v-if="form.text_positions.participant_name" 
                         :style="getTextOverlayStyle('participant_name')"
                         class="absolute pointer-events-none text-black font-bold">
                      {{ dummyData.participant_name }}
                    </div>
                    
                    <!-- Judul Course -->
                    <div v-if="form.text_positions.course_title"
                         :style="getTextOverlayStyle('course_title')"
                         class="absolute pointer-events-none text-black">
                      {{ dummyData.course_title }}
                    </div>
                    
                    <!-- Tanggal Selesai -->
                    <div v-if="form.text_positions.completion_date"
                         :style="getTextOverlayStyle('completion_date')"
                         class="absolute pointer-events-none text-black">
                      {{ dummyData.completion_date }}
                    </div>
                    
                    <!-- Nomor Sertifikat -->
                    <div v-if="form.text_positions.certificate_number"
                         :style="getTextOverlayStyle('certificate_number')"
                         class="absolute pointer-events-none text-black">
                      {{ dummyData.certificate_number }}
                    </div>
                    
                    <!-- Nama Instruktur -->
                    <div v-if="form.text_positions.instructor_name"
                         :style="getTextOverlayStyle('instructor_name')"
                         class="absolute pointer-events-none text-black">
                      {{ dummyData.instructor_name }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Text Position Settings -->
          <div v-if="previewImage">
            <h3 class="text-lg font-semibold text-white mb-4">Pengaturan Posisi Text</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="(field, key) in form.text_positions" :key="key" 
                   class="backdrop-blur-xl bg-white/5 border border-white/10 rounded-xl p-4">
                <h4 class="text-white font-medium mb-3">{{ getFieldLabel(key) }}</h4>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-white/70 text-sm mb-1">X Position</label>
                    <input v-model.number="field.x" type="number" 
                           class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm"
                           @input="onImageLoad">
                  </div>
                  <div>
                    <label class="block text-white/70 text-sm mb-1">Y Position</label>
                    <input v-model.number="field.y" type="number" 
                           class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm"
                           @input="onImageLoad">
                  </div>
                  <div>
                    <label class="block text-white/70 text-sm mb-1">Font Size</label>
                    <input v-model.number="field.font_size" type="number" 
                           class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm"
                           @input="onImageLoad">
                  </div>
                  <div>
                    <label class="block text-white/70 text-sm mb-1">Font Weight</label>
                    <select v-model="field.font_weight"
                            class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500">
                      <option value="normal" style="background-color: #1f2937; color: white;">Normal</option>
                      <option value="bold" style="background-color: #1f2937; color: white;">Bold</option>
                    </select>
                  </div>
                  <div class="col-span-2">
                    <label class="block text-white/70 text-sm mb-1">Font Family</label>
                    <select v-model="field.font_family"
                            class="w-full bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="onImageLoad">
                      <option value="Arial" style="background-color: #1f2937; color: white;">Arial</option>
                      <option value="Times New Roman" style="background-color: #1f2937; color: white;">Times New Roman</option>
                      <option value="Helvetica" style="background-color: #1f2937; color: white;">Helvetica</option>
                      <option value="Georgia" style="background-color: #1f2937; color: white;">Georgia</option>
                      <option value="Verdana" style="background-color: #1f2937; color: white;">Verdana</option>
                      <option value="Trebuchet MS" style="background-color: #1f2937; color: white;">Trebuchet MS</option>
                      <option value="Impact" style="background-color: #1f2937; color: white;">Impact</option>
                      <option value="Comic Sans MS" style="background-color: #1f2937; color: white;">Comic Sans MS</option>
                      <option value="Courier New" style="background-color: #1f2937; color: white;">Courier New</option>
                      <option value="Palatino" style="background-color: #1f2937; color: white;">Palatino</option>
                      <option value="Garamond" style="background-color: #1f2937; color: white;">Garamond</option>
                      <option value="Bookman" style="background-color: #1f2937; color: white;">Bookman</option>
                    </select>
                  </div>
                </div>
                
                <!-- Quick Position Presets -->
                <div class="mt-3 flex flex-wrap gap-2">
                  <button type="button" @click="setQuickPosition(key, 'center')"
                          class="px-2 py-1 bg-blue-500/20 border border-blue-500/30 rounded text-blue-200 text-xs hover:bg-blue-500/30 transition-all">
                    Center
                  </button>
                  <button type="button" @click="setQuickPosition(key, 'top-center')"
                          class="px-2 py-1 bg-blue-500/20 border border-blue-500/30 rounded text-blue-200 text-xs hover:bg-blue-500/30 transition-all">
                    Top Center
                  </button>
                  <button type="button" @click="setQuickPosition(key, 'bottom-left')"
                          class="px-2 py-1 bg-blue-500/20 border border-blue-500/30 rounded text-blue-200 text-xs hover:bg-blue-500/30 transition-all">
                    Bottom Left
                  </button>
                  <button type="button" @click="setQuickPosition(key, 'bottom-right')"
                          class="px-2 py-1 bg-blue-500/20 border border-blue-500/30 rounded text-blue-200 text-xs hover:bg-blue-500/30 transition-all">
                    Bottom Right
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end space-x-4 pt-6 border-t border-white/10">
            <Link :href="route('lms.certificate-templates.index')"
                  class="px-6 py-3 bg-white/20 border border-white/30 rounded-xl text-white hover:bg-white/30 transition-all">
              Batal
            </Link>
            <button type="submit" :disabled="loading || !form.background_image"
                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl font-semibold hover:from-green-600 hover:to-green-700 transition-all disabled:opacity-50">
              <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-save mr-2"></i>
              {{ loading ? 'Menyimpan...' : 'Simpan Template' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, reactive, watch } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  errors: { type: Object, default: () => ({}) }
})

const loading = ref(false)
const previewImage = ref('')
const fileInput = ref(null)
const backgroundImg = ref(null)
const imageLoaded = ref(false)
const imageScale = ref(1)

// Dummy data for preview
const dummyData = reactive({
  participant_name: 'Budi Santoso',
  course_title: 'Advanced JavaScript Programming',
  completion_date: '15 Januari 2025',
  certificate_number: 'CERT-2025-001',
  instructor_name: 'Ahmad Wijaya, M.Kom'
})

const form = reactive({
  name: '',
  description: '',
  background_image: null,
  status: 'active',
  text_positions: {
    participant_name: { x: 400, y: 300, font_size: 32, font_weight: 'bold', font_family: 'Georgia' },
    course_title: { x: 400, y: 350, font_size: 24, font_weight: 'normal', font_family: 'Arial' },
    completion_date: { x: 400, y: 400, font_size: 18, font_weight: 'normal', font_family: 'Arial' },
    certificate_number: { x: 100, y: 500, font_size: 12, font_weight: 'normal', font_family: 'Courier New' },
    instructor_name: { x: 600, y: 500, font_size: 16, font_weight: 'normal', font_family: 'Times New Roman' }
  },
  style_settings: {
    font_family: 'Arial',
    text_color: '#000000',
    text_align: 'center'
  }
})

const getFieldLabel = (key) => {
  const labels = {
    participant_name: 'Nama Peserta',
    course_title: 'Judul Course',
    completion_date: 'Tanggal Selesai',
    certificate_number: 'Nomor Sertifikat',
    instructor_name: 'Nama Instruktur'
  }
  return labels[key] || key
}

const handleFileUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    // Validate file
    if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Format file harus JPG atau PNG.' })
      return
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB
      Swal.fire({ icon: 'error', title: 'Error', text: 'Ukuran file maksimal 5MB.' })
      return
    }

    form.background_image = file
    
    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      previewImage.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const removeImage = () => {
  form.background_image = null
  previewImage.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const onImageLoad = () => {
  imageLoaded.value = true
  if (backgroundImg.value) {
    // Calculate scale factor for overlay positioning
    const naturalWidth = backgroundImg.value.naturalWidth
    const displayWidth = backgroundImg.value.clientWidth
    imageScale.value = displayWidth / naturalWidth
  }
}

const getTextOverlayStyle = (field) => {
  const position = form.text_positions[field]
  if (!position) return {}
  
  // Scale positions based on image display size
  const scaledX = position.x * imageScale.value
  const scaledY = position.y * imageScale.value
  const scaledFontSize = Math.max(8, position.font_size * imageScale.value)
  
  return {
    left: `${scaledX}px`,
    top: `${scaledY}px`,
    fontSize: `${scaledFontSize}px`,
    fontWeight: position.font_weight,
    fontFamily: position.font_family || 'Arial',
    transform: 'translate(-50%, -50%)', // Center the text
    whiteSpace: 'nowrap'
  }
}

const setQuickPosition = (fieldKey, position) => {
  if (!backgroundImg.value) return
  
  const imgWidth = backgroundImg.value.naturalWidth
  const imgHeight = backgroundImg.value.naturalHeight
  
  let x, y
  
  switch (position) {
    case 'center':
      x = imgWidth / 2
      y = imgHeight / 2
      break
    case 'top-center':
      x = imgWidth / 2
      y = imgHeight * 0.2
      break
    case 'bottom-left':
      x = imgWidth * 0.1
      y = imgHeight * 0.9
      break
    case 'bottom-right':
      x = imgWidth * 0.9
      y = imgHeight * 0.9
      break
    default:
      return
  }
  
  form.text_positions[fieldKey].x = Math.round(x)
  form.text_positions[fieldKey].y = Math.round(y)
  onImageLoad() // Refresh preview
}

// Watch for position changes to update preview
watch(() => form.text_positions, () => {
  // Trigger reactivity
}, { deep: true })

const submit = () => {
  if (!form.background_image) {
    Swal.fire({ icon: 'error', title: 'Error', text: 'Background sertifikat harus diupload.' })
    return
  }

  loading.value = true

  router.post(route('lms.certificate-templates.store'), form, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Template sertifikat berhasil dibuat.' })
    },
    onError: (errors) => {
      Swal.fire({ icon: 'error', title: 'Gagal', text: Object.values(errors)[0] || 'Gagal membuat template.' })
    },
    onFinish: () => {
      loading.value = false
    }
  })
}
</script>

<style scoped>
/* Custom dropdown styling untuk dark theme */
select option {
  background-color: #1f2937 !important;
  color: white !important;
  padding: 8px 12px;
}

select option:hover {
  background-color: #374151 !important;
}

select option:checked {
  background-color: #3b82f6 !important;
}

/* Hide default arrow untuk konsistensi */
select {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='white' viewBox='0 0 16 16'%3e%3cpath d='m7.247 4.86-4.796 5.481c-.566.647-.106 1.659.753 1.659h9.592a1 1 0 0 0 .753-1.659l-4.796-5.48a1 1 0 0 0-1.506 0z'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 8px center;
  background-size: 12px;
}

/* Firefox specific */
select::-moz-focus-inner {
  border: 0;
}

/* Internet Explorer specific */
select::-ms-expand {
  display: none;
}
</style>
