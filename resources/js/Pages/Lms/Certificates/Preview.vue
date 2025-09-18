<template>
  <AppLayout title="Preview Sertifikat">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          <i class="fas fa-certificate mr-2"></i>
          Preview Sertifikat
        </h2>
        <div class="flex items-center space-x-2">
          <button @click="downloadCertificate" 
                  class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
            <i class="fas fa-download mr-2"></i>
            Download PDF
          </button>
          <button @click="goBack" 
                  class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>
            Kembali
          </button>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Certificate Info -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              {{ certificate.course?.title || 'Training Course' }}
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Nomor Sertifikat: {{ certificate.certificate_number }}
            </p>
          </div>
          <div class="px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peserta</label>
                <p class="text-sm text-gray-900 dark:text-white">
                  {{ certificate.user?.nama_lengkap || 'Nama Peserta' }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Terbit</label>
                <p class="text-sm text-gray-900 dark:text-white">
                  {{ formatDate(certificate.issued_at) }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <span :class="[
                  'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                  certificate.status === 'active' 
                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                ]">
                  {{ certificate.status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Certificate Preview -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              <i class="fas fa-certificate mr-2"></i>
              Preview Sertifikat
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Preview sertifikat dengan data asli
            </p>
          </div>
          <div class="p-6">
            <!-- Certificate Container -->
            <div class="max-w-4xl mx-auto">
              <div class="relative bg-white border-2 border-gray-300 shadow-lg overflow-hidden" 
                   :style="{ aspectRatio: '4/3' }">
                <!-- Background Image -->
                <img v-if="certificate.template?.background_image" 
                     :src="getImageUrl(certificate.template.background_image)"
                     alt="Certificate Background"
                     class="absolute inset-0 w-full h-full object-cover object-center">
                
                <!-- Certificate Content -->
                <div class="relative z-10 h-full flex flex-col justify-center items-center p-8">
                  <!-- Title -->
                  <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">
                      SERTIFIKAT
                    </h1>
                    <div class="w-24 h-1 bg-blue-600 mx-auto"></div>
                  </div>

                  <!-- Dynamic Certificate Text based on Template -->
                  <div class="relative w-full h-full">
                    <!-- Participant Name -->
                    <div v-if="getTemplatePosition('participant_name')" 
                         :style="getTextStyle('participant_name')"
                         class="absolute">
                      {{ sampleData.participant_name }}
                    </div>

                    <!-- Course Title -->
                    <div v-if="getTemplatePosition('course_title')" 
                         :style="getTextStyle('course_title')"
                         class="absolute">
                      {{ sampleData.course_title }}
                    </div>

                    <!-- Completion Date -->
                    <div v-if="getTemplatePosition('completion_date')" 
                         :style="getTextStyle('completion_date')"
                         class="absolute">
                      {{ sampleData.completion_date }}
                    </div>

                    <!-- Certificate Number -->
                    <div v-if="getTemplatePosition('certificate_number')" 
                         :style="getTextStyle('certificate_number')"
                         class="absolute">
                      {{ sampleData.certificate_number }}
                    </div>

                    <!-- Training Location -->
                    <div v-if="getTemplatePosition('training_location')" 
                         :style="getTextStyle('training_location')"
                         class="absolute">
                      {{ sampleData.training_location }}
                    </div>

                    <!-- Instructor Name -->
                    <div v-if="getTemplatePosition('instructor_name')" 
                         :style="getTextStyle('instructor_name')"
                         class="absolute">
                      {{ sampleData.instructor_name }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Certificate Data Info -->
            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
              <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">
                <i class="fas fa-info-circle mr-1"></i>
                Data Sertifikat:
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-blue-700 dark:text-blue-300">
                <div><strong>Nama Peserta:</strong> {{ sampleData.participant_name }}</div>
                <div><strong>Judul Pelatihan:</strong> {{ sampleData.course_title }}</div>
                <div><strong>Tanggal Selesai:</strong> {{ sampleData.completion_date }}</div>
                <div><strong>Nomor Sertifikat:</strong> {{ sampleData.certificate_number }}</div>
                <div><strong>Lokasi Training:</strong> {{ sampleData.training_location }}</div>
                <div><strong>Instruktur:</strong> {{ sampleData.instructor_name }}</div>
                <div><strong>Template:</strong> {{ certificate.template?.name || 'Default Template' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  certificate: {
    type: Object,
    required: true
  },
  sampleData: {
    type: Object,
    required: true
  }
})

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('id-ID', { 
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  })
}

// Get template position for a field
const getTemplatePosition = (field) => {
  return props.certificate.template?.text_positions?.[field] || null
}

// Convert template positioning to CSS style
const getTextStyle = (field) => {
  const position = getTemplatePosition(field)
  if (!position) return {}
  
  // Convert pixel to percentage (assuming 896px width, 672px height for 4:3 aspect ratio)
  const xPercent = (parseInt(position.x) / 896) * 100
  const yPercent = (parseInt(position.y) / 672) * 100
  
  // Map font family
  const fontFamilyMap = {
    'Arial': 'Arial, sans-serif',
    'Georgia': 'Georgia, serif',
    'Times New Roman': 'Times New Roman, serif',
    'Courier New': 'Courier New, monospace',
    'Impact': 'Impact, Arial Black, sans-serif',
    'helvetica': 'Arial, sans-serif',
    'times': 'Times New Roman, serif'
  }
  
  const fontFamily = fontFamilyMap[position.font_family] || 'Arial, sans-serif'
  const fontSize = parseInt(position.font_size) + 'px'
  const fontWeight = position.font_weight === 'bold' ? 'bold' : 'normal'
  
  console.log(`Field: ${field}, Template Pos: (${position.x}, ${position.y}), Font Size: ${position.font_size}, Calculated CSS: (left: ${xPercent}%, top: ${yPercent}%, font-size: ${fontSize})`)

  return {
    position: 'absolute',
    left: xPercent + '%',
    top: yPercent + '%',
    fontFamily: fontFamily,
    fontSize: fontSize,
    fontWeight: fontWeight,
    color: '#000000',
    textAlign: 'center',
    whiteSpace: 'nowrap'
  }
}

const getImageUrl = (imagePath) => {
  if (!imagePath) return ''
  return `/storage/${imagePath}`
}

const downloadCertificate = () => {
  const downloadUrl = route('lms.certificates.download', props.certificate.id)
  window.open(downloadUrl, '_blank')
}

const goBack = () => {
  window.history.back()
}
</script>
