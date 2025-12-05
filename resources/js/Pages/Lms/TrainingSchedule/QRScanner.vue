<template>
  <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-hidden">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold">QR Code Scanner</h2>
          <button @click="$emit('close')" class="text-white/80 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <p class="text-white/80 mt-2">Scan QR Code untuk absensi</p>
      </div>

      <!-- Scanner Container -->
      <div class="p-6">
        <!-- Mode Toggle -->
        <div class="flex bg-gray-100 rounded-lg p-1 mb-4">
          <button 
            @click="mode = 'checkin'"
            :class="mode === 'checkin' ? 'bg-blue-500 text-white' : 'text-gray-600'"
            class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-all"
          >
            <i class="fas fa-sign-in-alt mr-2"></i>
            Check In
          </button>
          <button 
            @click="mode = 'checkout'"
            :class="mode === 'checkout' ? 'bg-green-500 text-white' : 'text-gray-600'"
            class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-all"
          >
            <i class="fas fa-sign-out-alt mr-2"></i>
            Check Out
          </button>
        </div>

        <!-- Scanner -->
        <div class="relative">
          <div id="qr-reader" class="w-full h-64 bg-gray-100 rounded-lg overflow-hidden"></div>
          
          <!-- Manual Input -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Atau masukkan QR Code manual:
            </label>
            <div class="flex space-x-2">
              <input 
                v-model="manualQRCode"
                type="text"
                placeholder="Paste QR Code di sini..."
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                @keyup.enter="processManualQR"
              />
              <button 
                @click="processManualQR"
                :disabled="!manualQRCode.trim()"
                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fas fa-check"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Status -->
        <div v-if="status" class="mt-4 p-3 rounded-lg" :class="statusClasses">
          <div class="flex items-center">
            <i :class="statusIcon" class="mr-2"></i>
            <span class="font-medium">{{ status }}</span>
          </div>
          <p v-if="statusMessage" class="text-sm mt-1 opacity-80">{{ statusMessage }}</p>
        </div>

        <!-- Recent Activity -->
        <div v-if="recentActivity.length > 0" class="mt-6">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Aktivitas Terbaru</h3>
          <div class="space-y-2 max-h-32 overflow-y-auto">
            <div 
              v-for="activity in recentActivity" 
              :key="activity.id"
              class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-sm"
            >
              <div>
                <span class="font-medium">{{ activity.participant }}</span>
                <span class="text-gray-500 ml-2">{{ activity.action }}</span>
              </div>
              <span class="text-xs text-gray-400">{{ activity.time }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  training: Object
})

const emit = defineEmits(['close'])

const mode = ref('checkin')
const manualQRCode = ref('')
const status = ref('')
const statusMessage = ref('')
const recentActivity = ref([])
let html5QrcodeScanner = null

const statusClasses = computed(() => {
  if (status.value.includes('Berhasil')) {
    return 'bg-green-100 text-green-800 border border-green-200'
  } else if (status.value.includes('Error')) {
    return 'bg-red-100 text-red-800 border border-red-200'
  }
  return 'bg-blue-100 text-blue-800 border border-blue-200'
})

const statusIcon = computed(() => {
  if (status.value.includes('Berhasil')) {
    return 'fas fa-check-circle text-green-600'
  } else if (status.value.includes('Error')) {
    return 'fas fa-exclamation-circle text-red-600'
  }
  return 'fas fa-info-circle text-blue-600'
})

onMounted(async () => {
  // Load html5-qrcode library
  if (typeof Html5QrcodeScanner !== 'undefined') {
    initScanner()
  } else {
    // Load the library dynamically
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js'
    script.onload = initScanner
    document.head.appendChild(script)
  }
})

onUnmounted(() => {
  if (html5QrcodeScanner) {
    html5QrcodeScanner.clear()
  }
})

const initScanner = () => {
  html5QrcodeScanner = new Html5QrcodeScanner(
    "qr-reader",
    { 
      fps: 10, 
      qrbox: { width: 250, height: 250 },
      aspectRatio: 1.0
    },
    false
  )

  html5QrcodeScanner.render(onScanSuccess, onScanFailure)
}

const onScanSuccess = (decodedText) => {
  processQRCode(decodedText)
}

const onScanFailure = (error) => {
  // Handle scan failure silently
  console.log('QR scan failed:', error)
}

const processManualQR = () => {
  if (manualQRCode.value.trim()) {
    processQRCode(manualQRCode.value.trim())
    manualQRCode.value = ''
  }
}

const processQRCode = async (qrCode) => {
  try {
    status.value = 'Memproses QR Code...'
    statusMessage.value = ''

    const endpoint = mode.value === 'checkin' ? 'lms.schedules.checkin' : 'lms.schedules.checkout'
    
    const response = await router.post(route(endpoint), {
      qr_code: qrCode
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        if (page.props.flash?.success) {
          status.value = 'Berhasil!'
          statusMessage.value = page.props.flash.success
          
          // Add to recent activity
          const activity = {
            id: Date.now(),
            participant: page.props.flash.participant || 'Unknown',
            action: mode.value === 'checkin' ? 'Check In' : 'Check Out',
            time: new Date().toLocaleTimeString('id-ID')
          }
          recentActivity.value.unshift(activity)
          
          // Keep only last 5 activities
          if (recentActivity.value.length > 5) {
            recentActivity.value = recentActivity.value.slice(0, 5)
          }

          // Show success notification
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: page.props.flash.success,
            timer: 2000,
            showConfirmButton: false
          })
        }
      },
      onError: (errors) => {
        status.value = 'Error!'
        statusMessage.value = Object.values(errors)[0] || 'Terjadi kesalahan'
        
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: statusMessage.value
        })
      }
    })

  } catch (error) {
    status.value = 'Error!'
    statusMessage.value = 'Terjadi kesalahan saat memproses QR Code'
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: statusMessage.value
    })
  }
}
</script>

<style scoped>
/* Custom styles for QR scanner */
#qr-reader {
  border: 2px solid #e5e7eb;
}

#qr-reader video {
  border-radius: 0.5rem;
}

/* Hide html5-qrcode default elements */
#qr-reader__scan_region {
  background: transparent !important;
}

#qr-reader__scan_region > img {
  display: none !important;
}

#qr-reader__camera_selection {
  display: none !important;
}

#qr-reader__dashboard {
  display: none !important;
}
</style>
