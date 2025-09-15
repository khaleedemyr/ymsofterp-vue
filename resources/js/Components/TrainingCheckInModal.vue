<template>
  <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold">Training Check-In</h2>
            <p class="text-white/80 mt-1">Scan QR Code atau input manual untuk check-in training</p>
          </div>
          <button @click="$emit('close')" class="text-white/80 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
        <!-- QR Code Input Section -->
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-qrcode mr-2 text-purple-500"></i>
            QR Code Check-In
          </h3>
          
          <!-- Manual Input -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Masukkan QR Code:
              </label>
              <div class="flex space-x-2">
                <input 
                  v-model="qrCodeInput"
                  type="text"
                  placeholder="Paste QR Code di sini..."
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  @keyup.enter="processCheckIn"
                />
                <button 
                  @click="processCheckIn"
                  :disabled="!qrCodeInput.trim() || isProcessing"
                  class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <i v-if="isProcessing" class="fas fa-spinner fa-spin"></i>
                  <i v-else class="fas fa-check"></i>
                </button>
              </div>
            </div>
            
            <!-- Test QR Codes -->
            <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
              <div class="flex items-start gap-2">
                <i class="fas fa-info-circle text-yellow-600 mt-0.5"></i>
                <div class="text-sm text-yellow-800">
                  <p class="font-medium mb-2">Test QR Codes:</p>
                  <div class="flex gap-2">
                    <button 
                      @click="useTestQRCode"
                      class="px-3 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600 transition-colors"
                    >
                      <i class="fas fa-flask mr-1"></i>
                      Test QR Code
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Status Display -->
        <div v-if="statusMessage" class="mb-6 p-3 rounded-lg" :class="statusClasses">
          <div class="flex items-center">
            <i :class="statusIcon" class="mr-2"></i>
            <span class="font-medium">{{ statusMessage }}</span>
          </div>
        </div>

        <!-- Instructions -->
        <div class="bg-blue-50 rounded-lg p-4">
          <h4 class="font-semibold text-blue-800 mb-2">
            <i class="fas fa-info-circle mr-2"></i>
            Cara Penggunaan:
          </h4>
          <ul class="text-sm text-blue-700 space-y-1">
            <li>• Copy QR Code dari jadwal training</li>
            <li>• Paste QR Code di field input di atas</li>
            <li>• Klik tombol check atau tekan Enter</li>
            <li>• Sistem akan memvalidasi dan melakukan check-in</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const emit = defineEmits(['close', 'checkin-success'])

// Reactive data
const qrCodeInput = ref('')
const isProcessing = ref(false)
const statusMessage = ref('')

// Test QR Code
const useTestQRCode = () => {
  qrCodeInput.value = '{"schedule_id":11,"course_id":24,"scheduled_date":"2025-09-14","hash":"826749d62c3fad54c9f1347b1b325602403381e074b87352a693963ac079543f"}'
  statusMessage.value = ''
}

// Process check-in
const processCheckIn = async () => {
  if (!qrCodeInput.value.trim()) {
    showError('QR Code tidak boleh kosong')
    return
  }

  isProcessing.value = true
  statusMessage.value = 'Memproses check-in...'

  try {
    console.log('Processing QR Code:', qrCodeInput.value)

    // Use Inertia router.post like the working modal
    await router.post(route('lms.check-in'), {
      qr_code: qrCodeInput.value.trim()
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        console.log('Check-in success:', page)
        
        if (page.props.flash?.success) {
          showSuccess(page.props.flash.success)
          
          // Emit success event with training data
          emit('checkin-success', {
            trainingInfo: page.props.flash.training_info || null,
            trainingSessions: page.props.flash.training_sessions || []
          })
        } else {
          showError('Check-in berhasil tapi tidak ada data training')
        }
      },
      onError: (errors) => {
        console.log('Check-in error:', errors)
        const errorMessage = Object.values(errors)[0] || 'Terjadi kesalahan saat check-in'
        showError(errorMessage)
      },
      onFinish: () => {
        isProcessing.value = false
      }
    })

  } catch (error) {
    console.error('Check-in error:', error)
    showError('Terjadi kesalahan saat memproses check-in')
    isProcessing.value = false
  }
}

// Helper functions
const showSuccess = (message) => {
  statusMessage.value = message
  Swal.fire({
    icon: 'success',
    title: 'Check-in Berhasil!',
    text: message,
    timer: 2000,
    showConfirmButton: false
  })
  
  // Clear input
  qrCodeInput.value = ''
  
  // Close modal after success
  setTimeout(() => {
    emit('close')
  }, 2000)
}

const showError = (message) => {
  statusMessage.value = message
  Swal.fire({
    icon: 'error',
    title: 'Error!',
    text: message
  })
}

// Computed properties
const statusClasses = computed(() => {
  if (statusMessage.value.includes('Berhasil') || statusMessage.value.includes('success')) {
    return 'bg-green-100 text-green-800 border border-green-200'
  } else if (statusMessage.value.includes('Error') || statusMessage.value.includes('error')) {
    return 'bg-red-100 text-red-800 border border-red-200'
  } else if (statusMessage.value.includes('Memproses')) {
    return 'bg-blue-100 text-blue-800 border border-blue-200'
  }
  return 'bg-gray-100 text-gray-800 border border-gray-200'
})

const statusIcon = computed(() => {
  if (statusMessage.value.includes('Berhasil') || statusMessage.value.includes('success')) {
    return 'fas fa-check-circle text-green-600'
  } else if (statusMessage.value.includes('Error') || statusMessage.value.includes('error')) {
    return 'fas fa-exclamation-circle text-red-600'
  } else if (statusMessage.value.includes('Memproses')) {
    return 'fas fa-spinner fa-spin text-blue-600'
  }
  return 'fas fa-info-circle text-gray-600'
})
</script>

<style scoped>
/* Custom styles if needed */
</style>