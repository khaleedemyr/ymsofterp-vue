<template>
  <teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40"
      @click.self="emit('close')"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="p-5 border-b flex justify-between items-center">
          <h3 class="text-lg font-bold text-gray-900">QR Code Asset</h3>
          <button type="button" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500" @click="emit('close')">
            <i class="fa fa-times"></i>
          </button>
        </div>

        <div class="p-6 flex flex-col items-center gap-4">
          <p class="text-center font-mono font-bold text-teal-700 text-base break-all">{{ serialNumber }}</p>

          <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-inner">
            <img v-if="qrPreviewUrl" :src="qrPreviewUrl" alt="QR Code" class="w-60 h-60" />
            <div v-else class="w-60 h-60 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-sm">
              Membuat QR...
            </div>
          </div>

          <div class="w-full text-center space-y-1.5 text-sm">
            <p class="font-semibold text-gray-800 break-words">{{ itemName || '-' }}</p>
            <p class="text-gray-600 break-words">{{ outletName || '-' }}</p>
            <p class="text-gray-500 break-words">{{ warehouseName || '-' }}</p>
          </div>
        </div>

        <div class="p-4 border-t flex flex-wrap justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200"
            @click="emit('close')"
          >
            Tutup
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-lg text-sm font-semibold hover:bg-teal-700 disabled:opacity-50"
            :disabled="!downloadUrl"
            @click="downloadQr"
          >
            <i class="fa-solid fa-download"></i>
            Download QR
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  open: { type: Boolean, default: false },
  serialNumber: { type: String, default: '' },
  itemName: { type: String, default: '' },
  outletName: { type: String, default: '' },
  warehouseName: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const qrPreviewUrl = ref('')
const downloadUrl = ref('')

const safeFileName = computed(() => {
  const raw = (props.serialNumber || 'asset-serial').replace(/[^\w.-]+/g, '_')
  return `asset-qr-${raw}.png`
})

function wrapText(ctx, text, maxWidth) {
  const words = String(text || '-').split(/\s+/)
  const lines = []
  let line = ''
  for (const word of words) {
    const test = line ? `${line} ${word}` : word
    if (ctx.measureText(test).width > maxWidth && line) {
      lines.push(line)
      line = word
    } else {
      line = test
    }
  }
  if (line) lines.push(line)
  return lines.length ? lines : ['-']
}

async function buildImages() {
  qrPreviewUrl.value = ''
  downloadUrl.value = ''

  const serial = (props.serialNumber || '').trim()
  if (!serial) return

  try {
    qrPreviewUrl.value = await QRCode.toDataURL(serial, {
      width: 480,
      margin: 2,
      errorCorrectionLevel: 'M',
      color: { dark: '#000000', light: '#FFFFFF' },
    })

    const qrSize = 360
    const paddingX = 40
    const paddingTop = 36
    const gap = 18
    const lineHeight = 28
    const canvasWidth = 520

    const measureCanvas = document.createElement('canvas')
    const measureCtx = measureCanvas.getContext('2d')
    const maxTextWidth = canvasWidth - paddingX * 2

    measureCtx.font = 'bold 22px Arial, sans-serif'
    const serialLines = wrapText(measureCtx, serial, maxTextWidth)
    measureCtx.font = 'bold 20px Arial, sans-serif'
    const itemLines = wrapText(measureCtx, props.itemName || '-', maxTextWidth)
    measureCtx.font = '18px Arial, sans-serif'
    const outletLines = wrapText(measureCtx, props.outletName || '-', maxTextWidth)
    const warehouseLines = wrapText(measureCtx, props.warehouseName || '-', maxTextWidth)

    const textBlockHeight =
      (serialLines.length + itemLines.length + outletLines.length + warehouseLines.length) * lineHeight + gap * 3

    const canvasHeight = paddingTop + qrSize + gap + textBlockHeight + 40
    const canvas = document.createElement('canvas')
    canvas.width = canvasWidth
    canvas.height = canvasHeight
    const ctx = canvas.getContext('2d')

    ctx.fillStyle = '#FFFFFF'
    ctx.fillRect(0, 0, canvasWidth, canvasHeight)

    const qrImg = await new Promise((resolve, reject) => {
      const img = new Image()
      img.onload = () => resolve(img)
      img.onerror = reject
      img.src = qrPreviewUrl.value
    })

    const qrX = (canvasWidth - qrSize) / 2
    ctx.drawImage(qrImg, qrX, paddingTop, qrSize, qrSize)

    let y = paddingTop + qrSize + gap + 8
    const drawLines = (lines, font, color) => {
      ctx.font = font
      ctx.fillStyle = color
      ctx.textAlign = 'center'
      for (const line of lines) {
        ctx.fillText(line, canvasWidth / 2, y)
        y += lineHeight
      }
    }

    drawLines(serialLines, 'bold 22px Arial, sans-serif', '#0f766e')
    y += 6
    drawLines(itemLines, 'bold 20px Arial, sans-serif', '#111827')
    y += 4
    drawLines(outletLines, '18px Arial, sans-serif', '#374151')
    y += 2
    drawLines(warehouseLines, '18px Arial, sans-serif', '#6b7280')

    downloadUrl.value = canvas.toDataURL('image/png')
  } catch (error) {
    console.error('Failed to generate asset serial QR:', error)
  }
}

function downloadQr() {
  if (!downloadUrl.value) return
  const link = document.createElement('a')
  link.href = downloadUrl.value
  link.download = safeFileName.value
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

watch(
  () => [props.open, props.serialNumber, props.itemName, props.outletName, props.warehouseName],
  ([isOpen]) => {
    if (isOpen) buildImages()
  },
  { immediate: true }
)
</script>
