<template>
  <teleport to="body">
    <div
      v-if="serialNumber"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/40"
      @click.self="emit('close')"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="p-5 border-b flex justify-between items-center">
          <h3 class="text-lg font-bold text-gray-900">QR Nomor Seri</h3>
          <button type="button" class="p-2 hover:bg-gray-100 rounded-lg text-gray-500" @click="emit('close')">
            <i class="fa fa-times"></i>
          </button>
        </div>
        <div class="p-6 flex flex-col items-center gap-4">
          <p class="text-center font-mono font-bold text-indigo-700 text-lg break-all">{{ serialNumber }}</p>
          <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-inner">
            <QrcodeVue :value="serialNumber" :size="240" level="M" />
          </div>
          <p class="text-xs text-gray-500 text-center">Scan QR untuk mendapatkan nomor seri</p>
        </div>
        <div class="p-4 border-t flex justify-end">
          <button
            type="button"
            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200"
            @click="emit('close')"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import QrcodeVue from 'qrcode.vue'

defineProps({
  serialNumber: { type: String, default: '' },
})

const emit = defineEmits(['close'])
</script>
