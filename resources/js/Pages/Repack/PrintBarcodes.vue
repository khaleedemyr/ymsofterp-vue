<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import JsBarcode from 'jsbarcode';

const props = defineProps({
  repack: Object,
  barcodes: Array,
});

onMounted(() => {
  // Generate barcodes after component is mounted
  props.barcodes.forEach((barcode, index) => {
    JsBarcode(`#barcode-${index}`, barcode.barcode, {
      format: "CODE128",
      width: 2,
      height: 100,
      displayValue: true
    });
  });
});

const print = () => {
  window.print();
};
</script>

<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-barcode text-blue-500"></i> Print Barcode
        </h1>
        <button @click="print" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
          <i class="fa-solid fa-print mr-2"></i> Print
        </button>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="mb-4">
          <h2 class="text-lg font-semibold mb-2">Informasi Repack</h2>
          <p>Nomor Repack: {{ repack.repack_number }}</p>
          <p>Item: {{ repack.item_hasil?.name }}</p>
          <p>Jumlah Barcode: {{ barcodes.length }}</p>
        </div>

        <div class="grid grid-cols-4 gap-4">
          <div v-for="(barcode, index) in barcodes" :key="index" class="border p-4 text-center">
            <svg :id="`barcode-${index}`"></svg>
            <p class="mt-2 text-sm">{{ barcode.barcode }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  .bg-white, .bg-white * {
    visibility: visible;
  }
  .bg-white {
    position: absolute;
    left: 0;
    top: 0;
  }
  button {
    display: none;
  }
}
</style> 