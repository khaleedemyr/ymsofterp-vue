<template>
  <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-t-2xl">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold">QR Code Training</h2>
          <button @click="$emit('close')" class="text-white/80 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <p class="text-white/80 mt-2">{{ training.course?.title || 'Training' }}</p>
      </div>

      <!-- QR Code Content -->
      <div class="p-6">
        <div class="text-center">
          <!-- QR Code Image -->
          <div class="bg-white p-4 rounded-lg inline-block border-2 border-gray-200">
            <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="QR Code" class="w-64 h-64" />
            <div v-else class="w-64 h-64 bg-gray-200 rounded flex items-center justify-center">
              <div class="text-center">
                <i class="fas fa-qrcode text-gray-400 text-6xl mb-4"></i>
                <p class="text-gray-500">Generating QR Code...</p>
              </div>
            </div>
          </div>
          
          <!-- Training Info -->
          <div class="mt-4 space-y-2">
            <h3 class="text-lg font-semibold text-gray-800">{{ training.course?.title }}</h3>
            <p class="text-gray-600">{{ formatDate(training.scheduled_date) }}</p>
            <p class="text-gray-600">{{ training.start_time }} - {{ training.end_time }}</p>
            <p class="text-gray-600">{{ training.outlet?.nama_outlet }}</p>
          </div>
          
          <!-- Instructions -->
          <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-2">Cara Penggunaan:</h4>
            <ul class="text-sm text-blue-700 space-y-1 text-left">
              <li>• QR Code ini unique untuk training ini</li>
              <li>• Peserta dapat scan untuk check-in</li>
              <li>• QR Code dapat digunakan untuk absensi</li>
              <li>• Setiap jadwal training memiliki QR Code berbeda</li>
            </ul>
          </div>
          
          <!-- Actions -->
          <div class="mt-6 flex space-x-3">
            <button @click="downloadQR" 
                    class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
              <i class="fas fa-download mr-2"></i>
              Download QR
            </button>
            <button @click="printQR" 
                    class="flex-1 px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
              <i class="fas fa-print mr-2"></i>
              Print QR
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  training: Object
})

const emit = defineEmits(['close'])

const qrCodeDataUrl = ref('')

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const generateQRCode = async () => {
  try {
    if (props.training) {
      // Format scheduled_date to Y-m-d format
      const scheduledDate = new Date(props.training.scheduled_date).toISOString().split('T')[0]
      
      // Generate hash using the same method as backend
      const hashInput = props.training.id + props.training.course_id + scheduledDate
      const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(hashInput))
      const hashArray = Array.from(new Uint8Array(hash))
      const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
      
      const data = {
        schedule_id: props.training.id,
        course_id: props.training.course_id,
        scheduled_date: scheduledDate,
        hash: hashHex
      }
      
      const qrData = JSON.stringify(data)
      qrCodeDataUrl.value = await QRCode.toDataURL(qrData, {
        width: 300,
        margin: 2,
        color: {
          dark: '#000000',
          light: '#FFFFFF'
        }
      })
      
      console.log('QR Code generated successfully:', qrCodeDataUrl.value)
      console.log('QR Code data:', data)
    }
  } catch (error) {
    console.error('Error generating QR code:', error)
  }
}

onMounted(() => {
  generateQRCode()
})

const downloadQR = () => {
  if (qrCodeDataUrl.value) {
    const link = document.createElement('a')
    link.href = qrCodeDataUrl.value
    link.download = `qr-code-${props.training.course?.title || 'training'}-${props.training.scheduled_date}.png`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }
}

const printQR = () => {
  const printWindow = window.open('', '_blank')
  printWindow.document.write(`
    <html>
      <head>
        <title>QR Code Training - ${props.training.course?.title}</title>
        <style>
          body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
          .qr-container { margin: 20px 0; }
          .info { margin: 10px 0; }
          @media print { body { margin: 0; } }
        </style>
      </head>
      <body>
        <h1>QR Code Training</h1>
        <div class="qr-container">
          <img src="${props.training.qr_code_url}" alt="QR Code" style="width: 300px; height: 300px;" />
        </div>
        <div class="info">
          <h2>${props.training.course?.title}</h2>
          <p>${formatDate(props.training.scheduled_date)}</p>
          <p>${props.training.start_time} - ${props.training.end_time}</p>
          <p>${props.training.outlet?.nama_outlet}</p>
        </div>
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.print()
}
</script>
