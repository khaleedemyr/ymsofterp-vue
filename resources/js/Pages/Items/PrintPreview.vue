<template>
  <div class="print-container" :style="{ height: numRows * 1.5 + 'cm' }">
    <div class="print-content" :style="{ height: numRows * 1.5 + 'cm' }">
      <div v-for="(row, rowIdx) in rows" :key="'row-' + rowIdx" class="barcode-row">
        <div v-for="(sku, colIdx) in row" :key="'sku-' + rowIdx + '-' + colIdx" class="barcode-label">
          <div class="barcode-body">
            <div class="barcode-image">
              <svg :ref="el => setBarcodeRef(rowIdx * 3 + colIdx, el)" :key="'svg-' + rowIdx + '-' + colIdx"></svg>
            </div>
            <div class="barcode-text">{{ sku }}</div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-4 mb-2 text-sm text-yellow-700 bg-yellow-100 border border-yellow-300 rounded p-2 no-print">
      <strong>Tips:</strong> Untuk mencetak {{ props.qty }} label, atur paper size di print dialog ke <b>{{ paperWidth }}cm x {{ paperHeight }}cm</b> (width x height),<br>
      lalu set margin ke <b>None</b> dan scale ke <b>100%</b> agar hasil presisi.
    </div>
    <div class="flex justify-end mt-4 no-print">
      <button @click="showPrintPreview = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Tutup</button>
      <button @click="doPrint" class="ml-2 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Print</button>
      <button @click="downloadPDF" class="ml-2 px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Download PDF</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick } from 'vue';
import JsBarcode from 'jsbarcode';
import jsPDF from 'jspdf';

const props = defineProps({
  sku: String,
  name: String,
  qty: {
    type: Number,
    default: 1
  }
});

const showPrintPreview = ref(true);
const barcodeRefs = ref([]);

function setBarcodeRef(idx, el) {
  if (!barcodeRefs.value) barcodeRefs.value = [];
  while (barcodeRefs.value.length <= idx) {
    barcodeRefs.value.push(null);
  }
  barcodeRefs.value[idx] = el;
}

// Helper to chunk array into rows of 3
const rows = computed(() => {
  const arr = Array.from({ length: props.qty }, () => props.sku);
  const chunked = [];
  for (let i = 0; i < arr.length; i += 3) {
    chunked.push(arr.slice(i, i + 3));
  }
  return chunked;
});

const numRows = computed(() => rows.value.length);
const paperWidth = 9.4;
const paperHeight = computed(() => (numRows.value * 1.5).toFixed(2));

const generateBarcodes = () => {
  barcodeRefs.value.forEach((el, idx) => {
    if (el) {
      JsBarcode(el, props.sku, {
        width: 2,
        height: 50,
        displayValue: false
      });
    }
  });
};

watch(() => rows.value.length, (len) => {
  barcodeRefs.value = Array(len * 3).fill(null);
  nextTick(() => generateBarcodes());
});

watch(() => props.sku, () => {
  nextTick(() => generateBarcodes());
});

onMounted(() => {
  barcodeRefs.value = Array(rows.value.length * 3).fill(null);
  nextTick(() => generateBarcodes());
});

const doPrint = () => {
  window.print();
};

async function downloadPDF() {
  // Ukuran kertas dalam mm (jsPDF default unit mm)
  const labelWidth = 30; // 3cm
  const labelHeight = 15; // 1.5cm
  const gap = 3; // 0.3cm
  const pdfWidth = 94; // 9.4cm
  const pdfHeight = numRows.value * labelHeight; // total tinggi
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });

  let y = 0;
  for (let rowIdx = 0; rowIdx < rows.value.length; rowIdx++) {
    for (let colIdx = 0; colIdx < 3; colIdx++) {
      const x = colIdx * (labelWidth + gap);
      const sku = rows.value[rowIdx][colIdx];
      if (!sku) continue;
      // Render barcode ke canvas proporsional, scale 3x
      const areaBarcodeW = labelWidth - 4; // 26mm
      const areaBarcodeH = 14; // mm
      const scale = 3;
      const canvas = document.createElement('canvas');
      canvas.width = areaBarcodeW * scale;
      canvas.height = areaBarcodeH * scale;
      JsBarcode(canvas, sku, { width: 1.5 * scale, height: areaBarcodeH * scale, displayValue: false });
      // Masukkan barcode ke PDF (ukuran asli)
      doc.addImage(canvas, 'PNG', x + 2, y + 1.5, areaBarcodeW, areaBarcodeH);
      doc.setFontSize(8);
      doc.text(sku, x + labelWidth / 2, y + 13.5, { align: 'center' }); // SKU
    }
    y += labelHeight;
  }
  doc.save(`${props.sku}_labels.pdf`);
}
</script>

<style scoped>
.print-container {
  padding: 20px;
  height: auto;
  overflow: visible;
}

.print-content {
  display: flex;
  flex-direction: column;
  gap: 0;
  height: auto;
  overflow: visible;
}

.barcode-row {
  display: flex;
  flex-direction: row;
  height: 1.5cm;
  margin: 0;
  padding: 0;
}

.barcode-label {
  width: 3cm;
  height: 1.5cm;
  /* border: 1px solid #000; */
  box-sizing: border-box;
  padding: 0.1cm 0.1cm 0 0.1cm;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  background: #fff;
  margin-right: 0.2cm;
}
.barcode-label:last-child {
  margin-right: 0;
}

.barcode-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
}

.barcode-image {
  margin-bottom: 0.05cm;
  width: 2.5cm;
  height: 0.7cm;
  display: flex;
  align-items: center;
  justify-content: center;
}

.barcode-image svg {
  width: 100% !important;
  height: 100% !important;
}

.barcode-text {
  font-size: 0.38em;
  text-align: center;
  line-height: 1;
}

@media print {
  html, body, .print-container, .print-content {
    width: 9.4cm !important;
    height: 1.5cm !important;
    min-width: 9.4cm !important;
    min-height: 1.5cm !important;
    max-width: 9.4cm !important;
    max-height: 1.5cm !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    background: none !important;
    page-break-after: avoid !important;
    page-break-before: avoid !important;
    page-break-inside: avoid !important;
    break-after: avoid !important;
    break-before: avoid !important;
    break-inside: avoid !important;
  }
  .barcode-row, .barcode-label {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
  .no-print {
    display: none !important;
  }
  @page {
    size: 9.4cm 1.5cm;
    margin: 0;
  }
}
</style> 