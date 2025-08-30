<template>
  <div class="print-container" :style="{ height: numRows * 1.5 + 'cm' }">
    <div class="print-content" :style="{ height: numRows * 1.5 + 'cm' }">
      <div v-for="(row, rowIdx) in rows" :key="'row-' + rowIdx" class="barcode-row">
        <div v-for="(sku, colIdx) in row" :key="'sku-' + rowIdx + '-' + colIdx" class="barcode-label">
          <div class="barcode-body">
            <div class="barcode-image">
              <svg :ref="el => setBarcodeRef(rowIdx * 3 + colIdx, el)" :key="'svg-' + rowIdx + '-' + colIdx"></svg>
            </div>
                         <div class="barcode-info">
               <div class="barcode-text"><strong>{{ sku }}</strong></div>
               <div class="barcode-name"><strong>{{ props.name }}</strong></div>
             </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-4 mb-2 text-sm text-yellow-700 bg-yellow-100 border border-yellow-300 rounded p-2 no-print">
      <strong>Tips Print:</strong> Untuk mencetak {{ props.qty }} label ukuran 2.5x1.2cm dengan presisi:<br>
      • <b>Paper Size:</b> A4 Landscape ({{ paperWidth }}cm x {{ paperHeight }}cm)<br>
             • <b>Margin:</b> Posisi tetap (5mm, 35mm, 65mm dari kiri, 2mm atas)<br>
      • <b>Scale:</b> 100% (Actual Size)<br>
      • <b>Orientation:</b> Landscape<br>
      • <b>Options:</b> Disable "Fit to Page" dan "Shrink to Fit"<br>
      Setiap label berukuran 2.5cm x 1.2cm dengan gap 0.3cm.
    </div>
         <div class="flex justify-end mt-4 no-print">
       <button @click="showPrintPreview = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Tutup</button>
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

// Helper to chunk array into rows of 3 (ukuran 3x1.5cm landscape)
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
        width: 1,
        height: 35,
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



async function downloadPDF() {
  // Ukuran kertas dalam mm (jsPDF default unit mm) - Ukuran kecil yang disesuaikan
  const labelWidth = 25; // 2.5cm - dikurangi untuk menghindari cutoff
  const labelHeight = 12; // 1.2cm - dikurangi untuk menghindari cutoff
  const gap = 3; // 0.3cm gap antar label - ditambah untuk spacing yang lebih baik
  const marginLeft = 25; // mm, margin kiri yang lebih besar lagi untuk alignment yang tepat
  const marginTop = 2; // mm, margin atas minimal
  const pdfWidth = 297; // A4 landscape width (29.7cm)
  const pdfHeight = 210; // A4 landscape height (21cm)
  
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });

  let y = marginTop;
  for (let rowIdx = 0; rowIdx < rows.value.length; rowIdx++) {
    for (let colIdx = 0; colIdx < 3; colIdx++) { // 3 label per baris untuk landscape
      
                     // Posisi tetap untuk setiap label
        let x;
        if (colIdx === 0) {
          x = 5; // Label kiri: 5mm dari tepi
        } else if (colIdx === 1) {
          x = 35; // Label tengah: 35mm dari tepi (5 + 30)
        } else {
          x = 70; // Label kanan: 65mm dari tepi (35 + 30)
        }
      
      const sku = rows.value[rowIdx][colIdx];
      if (!sku) continue;
      
             // Border dihilangkan untuk tampilan yang lebih bersih
      
                     // Render barcode ke canvas - ukuran diperbesar
        const areaBarcodeW = labelWidth - 1; // 24mm - diperbesar
        const areaBarcodeH = 9; // 9mm untuk barcode - disesuaikan
       const scale = 2;
       const canvas = document.createElement('canvas');
       canvas.width = areaBarcodeW * scale;
       canvas.height = areaBarcodeH * scale;
       JsBarcode(canvas, sku, { width: 1 * scale, height: areaBarcodeH * scale, displayValue: false });
       
               // Masukkan barcode ke PDF (ukuran asli) - center aligned
        const barcodeX = x + (labelWidth - areaBarcodeW) / 2;
        doc.addImage(canvas, 'PNG', barcodeX, y + 0.2, areaBarcodeW, areaBarcodeH);
       
               // Informasi di bawah barcode
        const startY = y + areaBarcodeH + 0.5;
       let currentY = startY;
      
                     // SKU/Barcode
        doc.setFontSize(4);
        doc.setFont(undefined, 'bold');
        doc.text(sku, x + labelWidth/2, currentY, { align: 'center' });
        currentY += 1.5;
        
        // Nama Item
        const itemName = props.name || '';
        doc.setFontSize(3);
        doc.setFont(undefined, 'bold');
        doc.text(itemName, x + labelWidth/2, currentY, { align: 'center' });
    }
    y += labelHeight + gap;
  }
  doc.save(`${props.sku}_small_labels_3x1.5cm.pdf`);
}
</script>

<style scoped>
.print-container {
  padding: 0;
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
  height: 1.2cm;
  margin: 0;
  padding: 0;
  gap: 0.3cm;
}

.barcode-label {
  width: 2.5cm;
  height: 1.2cm;
  border: none;
  box-sizing: border-box;
  padding: 0.05cm 0.05cm;
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
  height: 0.9cm;
  margin-bottom: 0.05cm;
}

.barcode-info {
  display: flex;
  flex-direction: column;
  gap: 0.01cm;
  width: 100%;
  overflow: hidden;
  flex: 1;
  justify-content: center;
  min-height: 0.3cm;
}

.barcode-image {
  margin-bottom: 0.05cm;
  width: 100%;
  height: 0.9cm;
  display: flex;
  align-items: center;
  justify-content: center;
}

.barcode-image svg {
  width: 100% !important;
  height: 100% !important;
}

.barcode-text {
  font-size: 0.08cm;
  font-weight: bold;
  line-height: 1;
  text-align: center;
  width: 100%;
  margin: 0;
  padding: 0;
}

.barcode-name {
  font-size: 0.07cm;
  font-weight: bold;
  line-height: 1;
  margin: 0.01cm 0 0 0;
  padding: 0;
  word-wrap: break-word;
  max-width: 100%;
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
      transform: scale(1) !important;
      transform-origin: top left !important;
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
    scale: 100%;
  }
}
</style>
