<template>
  <div class="print-container" :style="{ height: numRows * 5 + 'cm' }">
    <div class="print-content" :style="{ height: numRows * 5 + 'cm' }">
      <div v-for="(row, rowIdx) in rows" :key="'row-' + rowIdx" class="barcode-row">
        <div v-for="(sku, colIdx) in row" :key="'sku-' + rowIdx + '-' + colIdx" class="barcode-label">
          <div class="barcode-body">
            <div class="barcode-image">
                             <svg :ref="el => setBarcodeRef(rowIdx * 3 + colIdx, el)" :key="'svg-' + rowIdx + '-' + colIdx"></svg>
            </div>
            <div class="barcode-info">
              <div class="barcode-text"><strong>SKU: {{ sku }}</strong></div>
              <div class="barcode-name"><strong>Nama: {{ props.name }}</strong></div>
              <div v-if="props.warehouseDivision" class="barcode-detail">Warehouse: {{ props.warehouseDivision }}</div>
              <div v-if="props.category" class="barcode-detail">Category: {{ props.category }}</div>
              <div v-if="props.subCategory" class="barcode-detail">Sub Category: {{ props.subCategory }}</div>
              <div v-if="props.itemCode" class="barcode-detail">Code: {{ props.itemCode }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-4 mb-2 text-sm text-yellow-700 bg-yellow-100 border border-yellow-300 rounded p-2 no-print">
             <strong>Tips:</strong> Untuk mencetak {{ props.qty }} label ukuran 10x5cm, atur paper size di print dialog ke <b>{{ paperWidth }}cm x {{ paperHeight }}cm</b> (width x height),<br>
       lalu set margin ke <b>None</b> dan scale ke <b>100%</b> agar hasil presisi. Setiap label berukuran 10cm x 5cm. <b>Orientasi: Landscape</b>
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
  },
  warehouseDivision: String,
  category: String,
  subCategory: String,
  itemCode: String
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

// Helper to chunk array into rows of 3 (ukuran 10x5cm landscape)
const rows = computed(() => {
  const arr = Array.from({ length: props.qty }, () => props.sku);
  const chunked = [];
  for (let i = 0; i < arr.length; i += 3) {
    chunked.push(arr.slice(i, i + 3));
  }
  return chunked;
});

const numRows = computed(() => rows.value.length);
const paperWidth = 29.7; // A4 landscape width (29.7cm)
const paperHeight = 21; // A4 landscape height (21cm)

const generateBarcodes = () => {
  barcodeRefs.value.forEach((el, idx) => {
    if (el) {
      JsBarcode(el, props.sku, {
        width: 1.5,
        height: 50,
        displayValue: false
      });
    }
  });
};

watch(() => rows.value.length, (len) => {
  barcodeRefs.value = Array(len * 3).fill(null); // 3 labels per row
  nextTick(() => generateBarcodes());
});

watch(() => props.sku, () => {
  nextTick(() => generateBarcodes());
});

onMounted(() => {
  barcodeRefs.value = Array(rows.value.length * 3).fill(null); // 3 labels per row
  nextTick(() => generateBarcodes());
});

const doPrint = () => {
  window.print();
};

async function downloadPDF() {
  // Ukuran kertas dalam mm (jsPDF default unit mm) - Ukuran baru 10x5 cm
  const labelWidth = 100; // 10cm
  const labelHeight = 50; // 5cm
  const gap = 5; // 0.5cm gap antar label
  const marginLeft = 5; // mm, margin kiri
  const marginTop = 5; // mm, margin atas
  const pdfWidth = 297; // A4 landscape width (29.7cm)
  const pdfHeight = 210; // A4 landscape height (21cm)
  
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });

  let y = marginTop;
  for (let rowIdx = 0; rowIdx < rows.value.length; rowIdx++) {
    for (let colIdx = 0; colIdx < 3; colIdx++) { // 3 label per baris untuk landscape
      const x = marginLeft + colIdx * (labelWidth + gap);
      const sku = rows.value[rowIdx][colIdx];
      if (!sku) continue;
      
      // Border untuk label
      doc.setDrawColor(0, 0, 0);
      doc.setLineWidth(0.5);
      doc.rect(x, y, labelWidth, labelHeight);
      
             // Render barcode ke canvas - ukuran lebih besar
       const areaBarcodeW = labelWidth - 10; // 90mm
       const areaBarcodeH = 20; // 20mm untuk barcode
       const scale = 3;
       const canvas = document.createElement('canvas');
       canvas.width = areaBarcodeW * scale;
       canvas.height = areaBarcodeH * scale;
       JsBarcode(canvas, sku, { width: 1.5 * scale, height: areaBarcodeH * scale, displayValue: false });
       
       // Masukkan barcode ke PDF (ukuran asli) - center aligned
       const barcodeX = x + (labelWidth - areaBarcodeW) / 2;
       doc.addImage(canvas, 'PNG', barcodeX, y + 3, areaBarcodeW, areaBarcodeH);
       
       // Informasi lengkap di bawah barcode
       const startY = y + areaBarcodeH + 5;
       let currentY = startY;
      
             // SKU/Barcode
       doc.setFontSize(8);
       doc.setFont(undefined, 'bold');
       doc.text(`SKU: ${sku}`, x + labelWidth/2, currentY, { align: 'center' });
       currentY += 4;
       
               // Nama Item
        const itemName = props.name || '';
        doc.setFontSize(9);
        doc.setFont(undefined, 'bold');
        doc.text(`Nama: ${itemName}`, x + labelWidth/2, currentY, { align: 'center' });
        currentY += 4.5;
       
       // Informasi tambahan (jika tersedia dari props)
       if (props.warehouseDivision) {
         doc.setFontSize(6);
         doc.setFont(undefined, 'normal');
         doc.text(`Warehouse: ${props.warehouseDivision}`, x + labelWidth/2, currentY, { align: 'center' });
         currentY += 3;
       }
       
       if (props.category) {
         doc.text(`Category: ${props.category}`, x + labelWidth/2, currentY, { align: 'center' });
         currentY += 3;
       }
       
       if (props.subCategory) {
         doc.text(`Sub Category: ${props.subCategory}`, x + labelWidth/2, currentY, { align: 'center' });
         currentY += 3;
       }
       
       if (props.itemCode) {
         doc.text(`Code: ${props.itemCode}`, x + labelWidth/2, currentY, { align: 'center' });
       }
    }
    y += labelHeight + gap;
  }
  doc.save(`${props.sku}_labels_10x5cm.pdf`);
}
</script>

<style scoped>
.print-container {
  padding: 20px;
  height: auto;
  overflow: visible;
  width: 100%;
  max-width: 29.7cm;
}

.print-content {
  display: flex;
  flex-direction: column;
  gap: 0;
  height: auto;
  overflow: visible;
  width: 100%;
}

.barcode-row {
  display: flex;
  flex-direction: row;
  height: 5cm;
  margin: 0;
  padding: 0;
  gap: 0.5cm;
}

.barcode-label {
  width: 10cm;
  height: 5cm;
  border: 1px solid #000;
  box-sizing: border-box;
  padding: 0.15cm 0.1cm;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  overflow: hidden;
  flex-shrink: 0;
}
.barcode-label:last-child {
  margin-right: 0;
}

.barcode-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  flex: 1;
  width: 100%;
  height: 100%;
}

.barcode-image {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
  height: 1.8cm;
  margin-bottom: 0.1cm;
}

.barcode-info {
  display: flex;
  flex-direction: column;
  gap: 0.02cm;
  width: 100%;
  overflow: hidden;
  flex: 1;
  justify-content: flex-start;
}

.barcode-text {
  font-size: 0.8cm;
  font-weight: bold;
}

.barcode-name {
  font-size: 0.7cm;
  font-weight: bold;
}

.barcode-detail {
  font-size: 0.6cm;
  color: #333;
}

.barcode-image {
  margin-bottom: 0.1cm;
  width: 100%;
  height: 1.8cm;
  display: flex;
  align-items: center;
  justify-content: center;
}

.barcode-image svg {
  width: 100% !important;
  height: 100% !important;
}

.barcode-text {
  font-size: 0.2cm;
  font-weight: bold;
  line-height: 1;
  text-align: center;
  width: 100%;
  margin: 0;
  padding: 0;
}

.barcode-name {
  font-size: 0.25cm;
  font-weight: bold;
  line-height: 1;
  margin: 0.02cm 0 0 0;
  padding: 0;
  word-wrap: break-word;
  max-width: 100%;
  text-align: center;
  width: 100%;
}

.barcode-detail {
  font-size: 0.15cm;
  color: #333;
  line-height: 1;
  margin: 0.01cm 0 0 0;
  padding: 0;
  text-align: center;
  width: 100%;
}

@media print {
  html, body, .print-container, .print-content {
    width: 29.7cm !important;
    height: 21cm !important;
    min-width: 29.7cm !important;
    min-height: 21cm !important;
    max-width: 29.7cm !important;
    max-height: 21cm !important;
    overflow: visible !important;
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
    size: A4 landscape;
    margin: 0;
  }
}
</style> 