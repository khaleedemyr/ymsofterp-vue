<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    
    <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-2xl w-full mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-2xl font-bold text-white">QR Code Scanner</h3>
          <p class="text-white/70 mt-1">{{ mode === 'check-in' ? 'Check-in Peserta' : 'Check-out Peserta' }}</p>
        </div>
        <button @click="$emit('close')" class="text-white/70 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <!-- Scanner Area -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-6 mb-6">
        <div class="text-center">
          <div v-if="!isScanning" class="mb-4">
            <div class="w-64 h-64 bg-gray-800 rounded-lg flex items-center justify-center mx-auto">
              <i class="fas fa-qrcode text-gray-400 text-6xl"></i>
            </div>
            <p class="text-white/70 mt-4">Klik tombol di bawah untuk memulai scanner</p>
          </div>
          
          <div v-else class="mb-4">
            <div class="w-64 h-64 bg-gray-800 rounded-lg flex items-center justify-center mx-auto relative">
              <video ref="video" class="w-full h-full object-cover rounded-lg"></video>
              <div class="absolute inset-0 border-2 border-blue-500 rounded-lg pointer-events-none">
                <div class="absolute top-0 left-0 w-8 h-8 border-t-2 border-l-2 border-blue-500"></div>
                <div class="absolute top-0 right-0 w-8 h-8 border-t-2 border-r-2 border-blue-500"></div>
                <div class="absolute bottom-0 left-0 w-8 h-8 border-b-2 border-l-2 border-blue-500"></div>
                <div class="absolute bottom-0 right-0 w-8 h-8 border-b-2 border-r-2 border-blue-500"></div>
              </div>
            </div>
            <p class="text-white/70 mt-4">Arahkan kamera ke QR Code peserta</p>
          </div>
          
          <div class="flex items-center justify-center space-x-4">
            <button v-if="!isScanning" @click="startScanner"
                    class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-blue-700 transition-all">
              <i class="fas fa-camera mr-2"></i>
              Mulai Scanner
            </button>
            <button v-else @click="stopScanner"
                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl font-semibold hover:from-red-600 hover:to-red-700 transition-all">
              <i class="fas fa-stop mr-2"></i>
              Stop Scanner
            </button>
          </div>
        </div>
      </div>
      
      <!-- Manual Input -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <h4 class="text-lg font-semibold text-white mb-3">Input Manual</h4>
        <div class="flex items-center space-x-3">
          <input v-model="manualQRCode" 
                 type="text" 
                 placeholder="Masukkan QR Code secara manual..."
                 class="flex-1 bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <button @click="processManualQR" 
                  :disabled="!manualQRCode || loading"
                  class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg font-semibold hover:from-green-600 hover:to-green-700 transition-all disabled:opacity-50">
            <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-check mr-2"></i>
            {{ loading ? 'Memproses...' : 'Proses' }}
          </button>
        </div>
      </div>
      
      <!-- Recent Scans -->
      <div v-if="recentScans.length > 0" class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
        <h4 class="text-lg font-semibold text-white mb-3">Scan Terbaru</h4>
        <div class="space-y-2 max-h-32 overflow-y-auto">
          <div v-for="scan in recentScans" :key="scan.id" 
               class="flex items-center justify-between p-2 bg-white/5 rounded-lg">
            <div>
              <div class="text-white font-medium">{{ scan.participant }}</div>
              <div class="text-white/70 text-sm">{{ scan.training }}</div>
            </div>
            <div class="text-right">
              <div class="text-green-400 text-sm font-medium">{{ scan.status }}</div>
              <div class="text-white/50 text-xs">{{ scan.time }}</div>
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
  mode: {
    type: String,
    default: 'check-in',
    validator: (value) => ['check-in', 'check-out'].includes(value)
  }
})

const emit = defineEmits(['close', 'scanned'])

const video = ref(null)
const isScanning = ref(false)
const manualQRCode = ref('')
const loading = ref(false)
const recentScans = ref([])
let stream = null
let interval = null

const startScanner = async () => {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ 
      video: { facingMode: 'environment' } 
    })
    
    if (video.value) {
      video.value.srcObject = stream
      isScanning.value = true
      
      // Start scanning for QR codes
      interval = setInterval(() => {
        // This is a simplified version. In a real implementation,
        // you would use a QR code library like jsQR or zxing
        console.log('Scanning for QR codes...')
      }, 1000)
    }
  } catch (error) {
    console.error('Error accessing camera:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Tidak dapat mengakses kamera. Pastikan izin kamera sudah diberikan.'
    })
  }
}

const stopScanner = () => {
  if (stream) {
    stream.getTracks().forEach(track => track.stop())
    stream = null
  }
  
  if (interval) {
    clearInterval(interval)
    interval = null
  }
  
  isScanning.value = false
}

const processManualQR = async () => {
  if (!manualQRCode.value) return
  
  loading.value = true
  
  try {
    const route = props.mode === 'check-in' ? 'lms.check-in' : 'lms.check-out'
    
    await router.post(route('lms.check-in'), {
      qr_code: manualQRCode.value
    }, {
      onSuccess: (response) => {
        const data = response.props.flash.success || response.props.flash.data
        
        if (data) {
          // Add to recent scans
          recentScans.value.unshift({
            id: Date.now(),
            participant: data.participant || 'Unknown',
            training: data.training || 'Unknown',
            status: props.mode === 'check-in' ? 'Check-in Berhasil' : 'Check-out Berhasil',
            time: new Date().toLocaleTimeString('id-ID')
          })
          
          // Keep only last 5 scans
          if (recentScans.value.length > 5) {
            recentScans.value = recentScans.value.slice(0, 5)
          }
          
          Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: data.message || 'QR Code berhasil diproses'
          })
          
          manualQRCode.value = ''
          emit('scanned', data)
        }
      },
      onError: (errors) => {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: Object.values(errors)[0] || 'QR Code tidak valid atau sudah digunakan'
        })
      }
    })
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat memproses QR Code'
    })
  } finally {
    loading.value = false
  }
}

const processQRCode = (qrData) => {
  // This would be called when a QR code is detected
  processManualQR(qrData)
}

onMounted(() => {
  // Initialize scanner if needed
})

onUnmounted(() => {
  stopScanner()
})
</script>
