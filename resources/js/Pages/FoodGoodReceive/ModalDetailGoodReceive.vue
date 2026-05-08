<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-file-lines text-blue-500"></i>
        Detail Good Receive
      </h2>
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
        <i class="fa-solid fa-xmark text-2xl"></i>
      </button>
      <div class="mb-4 grid grid-cols-2 gap-4">
        <div>
          <div class="text-sm text-gray-500">Tanggal</div>
          <div class="font-medium">{{ gr.receive_date }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">No. PO</div>
          <div class="font-medium">{{ gr.po_number }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">Supplier</div>
          <div class="font-medium">{{ gr.supplier_name }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">Petugas</div>
          <div class="font-medium">{{ gr.received_by_name }}</div>
        </div>
      </div>
      <div>
        <div class="font-semibold mb-2">Daftar Item</div>
        <table class="w-full border text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="border px-2 py-1">Nama Item</th>
              <th class="border px-2 py-1">Qty Diterima</th>
              <th class="border px-2 py-1">Unit</th>
              <th class="border px-2 py-1">Harga</th>
              <th class="border px-2 py-1">Total</th>
              <th class="border px-2 py-1">Serial</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in gr.items || []" :key="item.id">
              <td class="border px-2 py-1">{{ item.item_name }}</td>
              <td class="border px-2 py-1">{{ item.qty_received }}</td>
              <td class="border px-2 py-1">{{ item.unit_name }}</td>
              <td class="border px-2 py-1 text-right">{{ formatRupiah(item.price) }}</td>
              <td class="border px-2 py-1 text-right">{{ formatRupiah(item.qty_received * item.price) }}</td>
              <td class="border px-2 py-1">
                <div class="flex flex-col gap-1">
                  <button
                    type="button"
                    class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 hover:bg-blue-200"
                    @click="generateSerial(item)"
                  >
                    Generate Serial
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
                    @click="showSerials(item)"
                  >
                    Lihat Serial ({{ serialSummary[item.id] || 0 }})
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200"
                    @click="rollbackSerial(item)"
                  >
                    Rollback Serial
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import JsBarcode from 'jsbarcode';
import jsPDF from 'jspdf';

const props = defineProps({
  show: Boolean,
  gr: Object
});
const emit = defineEmits(['close']);
const serialSummary = ref({});

function formatRupiah(val) {
  if (!val) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}

const loadSerialSummary = async () => {
  if (!props.gr?.id) return;
  try {
    const { data } = await axios.get(`/api/food-good-receive/${props.gr.id}/serial-summary`);
    const mapped = {};
    (data || []).forEach((row) => {
      mapped[row.good_receive_item_id] = Number(row.total || 0);
    });
    serialSummary.value = mapped;
  } catch (error) {
    serialSummary.value = {};
  }
};

const generateSerial = async (item) => {
  try {
    const { data } = await axios.get(`/api/food-good-receive-items/${item.id}/serial-units`);
    const units = data?.units || [];
    if (!units.length) {
      await Swal.fire('Info', 'Unit konversi item tidak ditemukan.', 'info');
      return;
    }

    const options = units.reduce((acc, unit) => {
      acc[unit.unit_id] = `${unit.unit_name} (qty: ${unit.converted_qty})`;
      return acc;
    }, {});

    const selected = await Swal.fire({
      title: `Generate Serial - ${item.item_name}`,
      html: `Qty diterima: <b>${data.qty_received}</b> ${data.received_unit_name || ''}`,
      input: 'select',
      inputOptions: options,
      inputPlaceholder: 'Pilih unit',
      showCancelButton: true,
      confirmButtonText: 'Generate',
      cancelButtonText: 'Batal',
      inputValidator: (value) => (!value ? 'Unit wajib dipilih' : undefined),
    });

    if (!selected.isConfirmed) return;

    const unitSelected = units.find((u) => Number(u.unit_id) === Number(selected.value));
    const confirm = await Swal.fire({
      title: 'Konfirmasi Generate',
      text: `Akan generate ${unitSelected?.converted_qty ?? 0} serial untuk unit ${unitSelected?.unit_name || ''}.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, generate',
      cancelButtonText: 'Batal',
    });
    if (!confirm.isConfirmed) return;

    const response = await axios.post(`/api/food-good-receive-items/${item.id}/generate-serials`, {
      unit_id: Number(selected.value),
    });

    await Swal.fire('Berhasil', response.data?.message || 'Serial berhasil dibuat.', 'success');
    await loadSerialSummary();
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal generate serial.';
    await Swal.fire('Error', message, 'error');
  }
};

const showSerials = async (item) => {
  try {
    const { data } = await axios.get(`/api/food-good-receive-items/${item.id}/serials`);
    if (!data || !data.length) {
      await Swal.fire('Info', 'Belum ada serial untuk item ini.', 'info');
      return;
    }

    const rowsHtml = data
      .slice(0, 200)
      .map(
        (row, idx) =>
          `<tr>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">${idx + 1}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.serial_number}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.unit_name || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.pr_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.po_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.gr_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">
              <button
                type="button"
                class="serial-pdf-btn"
                data-serial="${row.serial_number}"
                style="padding:2px 8px;background:#dbeafe;color:#1d4ed8;border-radius:4px;border:0;cursor:pointer;"
              >
                PDF 10x5
              </button>
            </td>
          </tr>`
      )
      .join('');

    await Swal.fire({
      title: `Serial - ${item.item_name}`,
      width: 980,
      html: `
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
          <button
            id="download-all-serial-pdf-btn"
            type="button"
            style="padding:6px 10px;background:#dbeafe;color:#1d4ed8;border-radius:6px;border:0;cursor:pointer;font-size:12px;font-weight:600;"
          >
            Download All PDF (10x5cm)
          </button>
        </div>
        <div style="max-height:420px;overflow:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr>
                <th style="border:1px solid #ddd;padding:4px;">No</th>
                <th style="border:1px solid #ddd;padding:4px;">Serial</th>
                <th style="border:1px solid #ddd;padding:4px;">Unit</th>
                <th style="border:1px solid #ddd;padding:4px;">No PR</th>
                <th style="border:1px solid #ddd;padding:4px;">No PO</th>
                <th style="border:1px solid #ddd;padding:4px;">No GR</th>
                <th style="border:1px solid #ddd;padding:4px;">Print</th>
              </tr>
            </thead>
            <tbody>${rowsHtml}</tbody>
          </table>
        </div>
      `,
      didOpen: () => {
        const downloadAllBtn = document.getElementById('download-all-serial-pdf-btn');
        if (downloadAllBtn) {
          downloadAllBtn.addEventListener('click', () => {
            downloadSerialPDF(
              data.map((row) => row.serial_number),
              item.item_name
            );
          });
        }

        const rowPdfButtons = document.querySelectorAll('.serial-pdf-btn');
        rowPdfButtons.forEach((btn) => {
          btn.addEventListener('click', (event) => {
            const serial = event.target?.getAttribute('data-serial');
            if (serial) {
              downloadSerialPDF([serial], item.item_name);
            }
          });
        });
      },
    });
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal mengambil serial.';
    await Swal.fire('Error', message, 'error');
  }
};

const downloadSerialPDF = (serials, itemName) => {
  if (!serials?.length) return;

  // Meniru Items > Manage Barcode > Download PDF (10x5cm)
  const labelWidth = 100; // 10cm
  const labelHeight = 50; // 5cm
  const gap = 5; // 0.5cm
  const marginLeft = 5;
  const marginTop = 5;
  const columnsPerRow = 3;
  const pdfWidth = 297; // A4 landscape
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
    doc.setFontSize(8);
    doc.setFont(undefined, 'bold');
    doc.text(`SERIAL: ${serial}`, x + labelWidth / 2, currentY, { align: 'center' });
    currentY += 4.5;
    doc.setFontSize(9);
    doc.setFont(undefined, 'bold');
    doc.text(`${itemName || ''}`, x + labelWidth / 2, currentY, { align: 'center' });
  });

  const firstSerial = serials[0] || 'serial';
  doc.save(`${firstSerial}_labels_10x5cm.pdf`);
};

const rollbackSerial = async (item) => {
  const confirm = await Swal.fire({
    title: 'Rollback serial?',
    text: 'Semua serial untuk item ini akan dihapus (di GR ini).',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, rollback',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#d33',
  });

  if (!confirm.isConfirmed) return;

  try {
    const { data } = await axios.delete(`/api/food-good-receive-items/${item.id}/serials`);
    await Swal.fire('Berhasil', data?.message || 'Rollback serial berhasil.', 'success');
    await loadSerialSummary();
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal rollback serial.';
    await Swal.fire('Error', message, 'error');
  }
};

watch(
  () => [props.show, props.gr?.id],
  ([show]) => {
    if (show) loadSerialSummary();
  },
  { immediate: true }
);
</script> 