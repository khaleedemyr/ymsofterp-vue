<template>
  <div class="inline-flex items-center gap-1" @click.stop>
    <button
      type="button"
      class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 transition-colors"
      title="Salin nomor seri"
      @click="copySerial"
    >
      <i class="fa-regular fa-copy"></i>
      <span v-if="showLabel">Copy</span>
    </button>
    <button
      type="button"
      class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-md border border-indigo-200 text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800 transition-colors"
      title="Tampilkan QR"
      @click="emit('show-qr', serialNumber)"
    >
      <i class="fa-solid fa-qrcode"></i>
      <span v-if="showLabel">QR</span>
    </button>
  </div>
</template>

<script setup>
const props = defineProps({
  serialNumber: { type: String, required: true },
  showLabel: { type: Boolean, default: false },
})

const emit = defineEmits(['show-qr'])

const copySerial = async () => {
  const text = (props.serialNumber || '').trim()
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    window.Swal?.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Nomor seri disalin',
      showConfirmButton: false,
      timer: 1800,
      timerProgressBar: true,
    })
  } catch {
    window.Swal?.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: 'Gagal menyalin',
      showConfirmButton: false,
      timer: 2000,
    })
  }
}
</script>
