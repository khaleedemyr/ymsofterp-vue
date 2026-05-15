<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import JsBarcode from 'jsbarcode';
import jsPDF from 'jspdf';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  repack: Object,
  barcodes: Array,
});

/** Serial yang sudah dipakai — rollback tidak diizinkan dari UI */
const serialInUse = ref(0);

onMounted(async () => {
  props.barcodes.forEach((barcode, index) => {
    JsBarcode(`#barcode-${index}`, barcode.barcode, {
      format: "CODE128",
      width: 2,
      height: 100,
      displayValue: true
    });
  });
  try {
    const { data } = await axios.get(`/api/repack/${props.repack.id}/serial-summary`);
    serialInUse.value = Number(data?.in_use || 0);
  } catch (_) {
    serialInUse.value = 0;
  }
});

const print = () => {
  window.print();
};

const downloadSerialPDF = (serials) => {
  if (!serials?.length) return;

  const labelWidth = 100;
  const labelHeight = 50;
  const gap = 5;
  const marginLeft = 5;
  const marginTop = 5;
  const columnsPerRow = 3;
  const pdfWidth = 297;
  const pdfHeight = 210;
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });
  const rowHeight = labelHeight + gap;
  const usableHeight = pdfHeight - (marginTop * 2);
  const rowsPerPage = Math.max(1, Math.floor(usableHeight / rowHeight));

  serials.forEach((serial, idx) => {
    const itemsPerPage = columnsPerRow * rowsPerPage;
    const indexInPage = idx % itemsPerPage;
    if (idx > 0 && indexInPage === 0) {
      doc.addPage([pdfWidth, pdfHeight], 'landscape');
    }

    const rowIdx = Math.floor(indexInPage / columnsPerRow);
    const colIdx = indexInPage % columnsPerRow;
    const x = marginLeft + colIdx * (labelWidth + gap);
    const y = marginTop + rowIdx * rowHeight;

    doc.setDrawColor(0, 0, 0);
    doc.setLineWidth(0.5);
    doc.rect(x, y, labelWidth, labelHeight);

    const areaBarcodeW = labelWidth - 10;
    const areaBarcodeH = 20;
    const scale = 3;
    const canvas = document.createElement('canvas');
    canvas.width = areaBarcodeW * scale;
    canvas.height = areaBarcodeH * scale;
    JsBarcode(canvas, serial, { width: 1.5 * scale, height: areaBarcodeH * scale, displayValue: false });

    const barcodeX = x + (labelWidth - areaBarcodeW) / 2;
    doc.addImage(canvas, 'PNG', barcodeX, y + 3, areaBarcodeW, areaBarcodeH);

    let currentY = y + areaBarcodeH + 5;
    doc.setFontSize(7);
    doc.setFont(undefined, 'bold');
    doc.text(`SERIAL: ${serial}`, x + labelWidth / 2, currentY, { align: 'center' });
    currentY += 3.8;
    doc.setFontSize(8);
    doc.setFont(undefined, 'bold');
    doc.text(`${props.repack?.item_hasil?.name || ''}`, x + labelWidth / 2, currentY, { align: 'center' });
    currentY += 3.2;
    doc.setFontSize(7);
    doc.setFont(undefined, 'normal');
    doc.text(`BATCH: ${props.repack?.repack_number || '-'}`, x + labelWidth / 2, currentY, { align: 'center' });
  });

  const firstSerial = serials[0] || 'serial';
  doc.save(`${firstSerial}_repack_labels_10x5cm.pdf`);
};

const downloadAllPdf = () => {
  downloadSerialPDF(props.barcodes.map((x) => x.barcode));
};

const rollbackSerial = async () => {
  const confirm = await Swal.fire({
    title: 'Rollback serial Repack?',
    text: 'Semua serial repack ini akan dihapus.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, rollback',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#d33',
  });

  if (!confirm.isConfirmed) return;

  try {
    const { data } = await axios.delete(`/api/repack/${props.repack.id}/serials`);
    await Swal.fire('Berhasil', data?.message || 'Rollback serial berhasil', 'success');
    window.location.reload();
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal rollback serial';
    await Swal.fire('Error', message, 'error');
  }
};
</script>

<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-barcode text-blue-500"></i> Print Barcode
        </h1>
        <div class="flex gap-2">
          <button @click="downloadAllPdf" class="bg-indigo-500 text-white px-4 py-2 rounded-lg">
            <i class="fa-solid fa-file-pdf mr-2"></i> Download All PDF 10x5
          </button>
          <button
            @click="rollbackSerial"
            :disabled="serialInUse > 0"
            :title="serialInUse > 0 ? 'Ada serial yang sudah digunakan — tidak bisa rollback.' : ''"
            class="bg-red-500 text-white px-4 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i class="fa-solid fa-rotate-left mr-2"></i> Rollback Serial
          </button>
          <button @click="print" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
            <i class="fa-solid fa-print mr-2"></i> Print
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="mb-4">
          <h2 class="text-lg font-semibold mb-2">Informasi Repack</h2>
          <p>Nomor Repack: {{ repack.repack_number }}</p>
          <p>Item: {{ repack.item_hasil?.name }}</p>
          <p>Jumlah Serial: {{ barcodes.length }}</p>
        </div>

        <div class="grid grid-cols-4 gap-4">
          <div v-for="(barcode, index) in barcodes" :key="index" class="border p-4 text-center">
            <svg :id="`barcode-${index}`"></svg>
            <p class="mt-2 text-sm">{{ barcode.barcode }}</p>
            <button
              type="button"
              class="mt-2 bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs"
              @click="downloadSerialPDF([barcode.barcode])"
            >
              PDF 10x5
            </button>
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