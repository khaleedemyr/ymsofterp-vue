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
                    class="px-2 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="(serialInUse[item.id] || 0) > 0"
                    :title="(serialInUse[item.id] || 0) > 0 ? 'Ada serial yang sudah digunakan — tidak bisa rollback.' : ''"
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
/** Jumlah serial per item GR yang sudah dipakai (DO / transfer / terima outlet). */
const serialInUse = ref({});

function formatRupiah(val) {
  if (!val) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}

const loadSerialSummary = async () => {
  if (!props.gr?.id) return;
  try {
    const { data } = await axios.get(`/api/food-good-receive/${props.gr.id}/serial-summary`);
    const mapped = {};
    const inUseMap = {};
    (data || []).forEach((row) => {
      mapped[row.good_receive_item_id] = Number(row.total || 0);
      inUseMap[row.good_receive_item_id] = Number(row.in_use || 0);
    });
    serialSummary.value = mapped;
    serialInUse.value = inUseMap;
  } catch (error) {
    serialSummary.value = {};
    serialInUse.value = {};
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
      confirmButtonText: 'Lanjut',
      cancelButtonText: 'Batal',
      inputValidator: (value) => (!value ? 'Unit wajib dipilih' : undefined),
    });

    if (!selected.isConfirmed) return;

    const unitSelected = units.find((u) => Number(u.unit_id) === Number(selected.value));
    const baseQty = Number(unitSelected?.converted_qty ?? 0);
    const baseUnitName = unitSelected?.unit_name || '';

    let allUnits = [];
    try {
      const { data: fetchedUnits } = await axios.get('/api/fgr-serial/units');
      allUnits = fetchedUnits || [];
    } catch (e) { /* ignore */ }

    let unitOptionsHtml = allUnits.map(u => `<option value="${u.id}">${u.name}</option>`).join('');

    const { value: formValues, isConfirmed } = await Swal.fire({
      title: 'Konversi Unit (Opsional)',
      html: `
        <div style="text-align:left;font-size:14px;">
          <div style="margin-bottom:10px;">
            <strong>Unit terpilih:</strong> ${baseUnitName}<br>
            <strong>Qty hasil konversi:</strong> ${baseQty}
          </div>
          <div style="margin-bottom:10px;">
            <label style="font-weight:600;display:block;margin-bottom:4px;">Mode:</label>
            <div style="display:flex;gap:16px;">
              <label style="cursor:pointer;"><input type="radio" name="swal-conv-mode" value="no" checked> Tanpa Konversi</label>
              <label style="cursor:pointer;"><input type="radio" name="swal-conv-mode" value="yes"> Konversi Unit</label>
            </div>
          </div>
          <div id="swal-conv-wrapper" style="display:none;margin-bottom:10px;">
            <div style="margin-bottom:8px;">
              <label style="font-weight:600;display:block;margin-bottom:4px;">Unit Tujuan Serial:</label>
              <select id="swal-repack-unit" class="swal2-select" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;">
                <option value="">-- Pilih Unit --</option>
                ${unitOptionsHtml}
              </select>
            </div>
            <div>
              <label style="font-weight:600;display:block;margin-bottom:4px;">1 <span id="swal-target-unit-label">[unit tujuan]</span> = berapa ${baseUnitName}?</label>
              <input type="number" id="swal-repack-qty" min="0.01" step="0.01" value="1" class="swal2-input" style="width:100%;margin:0;">
            </div>
          </div>
          <div style="margin-top:12px;padding:8px;background:#f3f4f6;border-radius:6px;">
            <span style="font-weight:600;">Jumlah serial:</span> <span id="swal-serial-count">${baseQty}</span>
          </div>
          <div style="margin-top:12px;">
            <label style="font-weight:600;display:block;margin-bottom:4px;">Exp Date (Opsional):</label>
            <input type="date" id="swal-exp-date" class="swal2-input" style="width:100%;margin:0;">
          </div>
        </div>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, generate',
      cancelButtonText: 'Batal',
      didOpen: () => {
        const radios = document.querySelectorAll('input[name="swal-conv-mode"]');
        const convWrapper = document.getElementById('swal-conv-wrapper');
        const unitSelect = document.getElementById('swal-repack-unit');
        const qtyInput = document.getElementById('swal-repack-qty');
        const countDisplay = document.getElementById('swal-serial-count');
        const targetUnitLabel = document.getElementById('swal-target-unit-label');

        const updateCount = () => {
          const mode = document.querySelector('input[name="swal-conv-mode"]:checked')?.value || 'no';
          if (mode === 'yes') {
            const repackQty = Math.max(0.01, parseFloat(qtyInput.value) || 1);
            countDisplay.textContent = Math.ceil(baseQty / repackQty);
          } else {
            countDisplay.textContent = baseQty;
          }
        };

        radios.forEach(r => r.addEventListener('change', (e) => {
          convWrapper.style.display = e.target.value === 'yes' ? 'block' : 'none';
          updateCount();
        }));

        unitSelect.addEventListener('change', () => {
          const selectedOption = unitSelect.options[unitSelect.selectedIndex];
          targetUnitLabel.textContent = selectedOption?.text || '[unit tujuan]';
        });

        qtyInput.addEventListener('input', updateCount);
      },
      preConfirm: () => {
        const mode = document.querySelector('input[name="swal-conv-mode"]:checked')?.value || 'no';
        const expDate = document.getElementById('swal-exp-date')?.value || null;
        if (mode === 'yes') {
          const repackUnitId = document.getElementById('swal-repack-unit')?.value;
          const repackQty = parseFloat(document.getElementById('swal-repack-qty')?.value) || 0;
          if (!repackUnitId) {
            Swal.showValidationMessage('Pilih unit tujuan terlebih dahulu');
            return false;
          }
          if (repackQty <= 0) {
            Swal.showValidationMessage('Qty konversi harus lebih dari 0');
            return false;
          }
          return { repack_unit_id: parseInt(repackUnitId), repack_qty: repackQty, exp_date: expDate };
        }
        return { repack_unit_id: null, repack_qty: null, exp_date: expDate };
      }
    });

    if (!isConfirmed || !formValues) return;

    const response = await axios.post(`/api/food-good-receive-items/${item.id}/generate-serials`, {
      unit_id: Number(selected.value),
      repack_unit_id: formValues.repack_unit_id,
      repack_qty: formValues.repack_qty,
      exp_date: formValues.exp_date || null,
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

    const fmtQty = (v) => v != null ? parseFloat(Number(v).toFixed(4)).toString() : '';
    const fmtExpDate = (v) => {
      if (!v) return '-';
      const d = new Date(v);
      if (Number.isNaN(d.getTime())) return v;
      return d.toLocaleDateString('id-ID');
    };

    const rowsHtml = data
      .slice(0, 200)
      .map(
        (row, idx) => {
          const convInfo = row.repack_unit_id && row.repack_qty
            ? `<span style="background:#f3e8ff;color:#7c3aed;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;">1 ${row.repack_unit_name || '?'} = ${fmtQty(row.repack_qty)} ${row.unit_name || ''}</span>`
            : `<span style="background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;font-size:10px;">Tanpa konversi</span>`;
          return `<tr>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">${idx + 1}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.serial_number}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.unit_name || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${convInfo}</td>
            <td style="border:1px solid #ddd;padding:4px;">${fmtExpDate(row.exp_date)}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.pr_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.po_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.gr_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">
              <button
                type="button"
                class="serial-pdf-btn"
                data-serial="${row.serial_number}"
                data-repack-unit-name="${row.repack_unit_name || ''}"
                data-repack-qty="${row.repack_qty || ''}"
                data-unit-name="${row.unit_name || ''}"
                data-exp-date="${row.exp_date || ''}"
                style="padding:2px 8px;background:#dbeafe;color:#1d4ed8;border-radius:4px;border:0;cursor:pointer;"
              >
                PDF 10x5
              </button>
            </td>
          </tr>`;
        }
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
                <th style="border:1px solid #ddd;padding:4px;">Konversi</th>
                <th style="border:1px solid #ddd;padding:4px;">Exp Date</th>
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
              item.item_name,
              {
                repackUnitName: data[0]?.repack_unit_name || null,
                repackQty: data[0]?.repack_qty || null,
                unitName: data[0]?.unit_name || '',
                expDate: data[0]?.exp_date || null,
              }
            );
          });
        }

        const rowPdfButtons = document.querySelectorAll('.serial-pdf-btn');
        rowPdfButtons.forEach((btn) => {
          btn.addEventListener('click', (event) => {
            const serial = event.target?.getAttribute('data-serial');
            const repackUnitName = event.target?.getAttribute('data-repack-unit-name') || null;
            const repackQty = event.target?.getAttribute('data-repack-qty') || null;
            const unitName = event.target?.getAttribute('data-unit-name') || '';
            const expDate = event.target?.getAttribute('data-exp-date') || null;
            if (serial) {
              downloadSerialPDF([serial], item.item_name, {
                repackUnitName: repackUnitName || null,
                repackQty: repackQty ? parseFloat(repackQty) : null,
                unitName: unitName,
                expDate: expDate || null,
              });
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

const downloadSerialPDF = (serials, itemName, meta = {}) => {
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
    doc.setFontSize(8);
    doc.setFont(undefined, 'bold');
    doc.text(`SERIAL: ${serial}`, x + labelWidth / 2, currentY, { align: 'center' });
    currentY += 4.5;
    doc.setFontSize(9);
    doc.setFont(undefined, 'bold');
    doc.text(`${itemName || ''}`, x + labelWidth / 2, currentY, { align: 'center' });
    currentY += 3.5;

    if (meta?.repackUnitName && meta?.repackQty) {
      const fmtRepackQty = parseFloat(Number(meta.repackQty).toFixed(4)).toString();
      doc.setFontSize(7);
      doc.setFont(undefined, 'bold');
      doc.text(`1 ${meta.repackUnitName.toUpperCase()} = ${fmtRepackQty} ${(meta.unitName || '').toUpperCase()}`, x + labelWidth / 2, currentY, { align: 'center' });
      currentY += 3.5;
    }

    if (meta?.expDate) {
      const expLabel = new Date(meta.expDate).toLocaleDateString('id-ID');
      doc.setFontSize(7);
      doc.setFont(undefined, 'bold');
      doc.text(`EXP: ${expLabel}`, x + labelWidth / 2, currentY, { align: 'center' });
    }
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