<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6 flex flex-col items-center">
      <h1 class="text-3xl font-bold mb-8 text-blue-800 flex items-center gap-3">
        <i class="fa-solid fa-barcode text-blue-500"></i> Delivery Order (Scan Barang)
      </h1>
      <!-- Pilih Packing List -->
      <div class="mb-6 flex flex-col md:flex-row gap-4 items-center w-full max-w-xl">
        <label class="font-semibold text-lg">No Packing List</label>
        <select v-model="selectedPackingListId" @change="onPackingListChange" class="border-2 border-blue-400 rounded-lg px-3 py-2 w-full max-w-xs focus:ring-2 focus:ring-blue-500">
          <option value="">Pilih Packing List...</option>
          <option v-for="pl in packingLists" :key="pl.id" :value="pl.id">
            {{ new Date(pl.created_at).toLocaleDateString('id-ID') }} - {{ pl.nama_outlet || '-' }} - {{ pl.packing_number }}
          </option>
        </select>
      </div>
      <!-- Card Info Packing List terpilih -->
      <div v-if="selectedPackingList" class="mb-6 w-full max-w-xl bg-blue-50 border-l-4 border-blue-400 p-4 rounded animate-fade-in">
        <div class="font-bold text-blue-800 mb-1">Info Packing List</div>
        <div><b>Outlet:</b> {{ selectedPackingList.nama_outlet || '-' }}</div>
        <div><b>Warehouse Division:</b> {{ selectedPackingList.division_name || '-' }}</div>
        <div><b>Warehouse:</b> {{ selectedPackingList.warehouse_name || '-' }}</div>
        <div><b>Tanggal Floor Order:</b> {{ selectedPackingList.floor_order_date ? new Date(selectedPackingList.floor_order_date).toLocaleDateString('id-ID') : '-' }}</div>
        <div><b>Nomor Floor Order:</b> {{ selectedPackingList.floor_order_number || '-' }}</div>
        <div><b>Tanggal Packing:</b> {{ selectedPackingList.created_at ? new Date(selectedPackingList.created_at).toLocaleDateString('id-ID') : '-' }}</div>
        <div><b>Nomor Packing:</b> {{ selectedPackingList.packing_number }}</div>
        <div><b>User Packing:</b> {{ selectedPackingList.creator_name || '-' }}</div>
      </div>
      <!-- Tabel Item Packing List -->
      <div v-if="packingListItems.length" class="mb-8 w-full max-w-3xl animate-fade-in">
        <table class="min-w-full rounded-xl overflow-hidden shadow-xl">
          <thead class="bg-blue-100">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">Nama Item</th>
              <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase">Qty Packing List</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">Unit</th>
              <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase">Qty Scan</th>
              <th class="px-4 py-2 text-center text-xs font-bold text-blue-700 uppercase">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in packingListItems" :key="item.id" :class="statusClass(item)">
              <td class="px-4 py-2 text-base">
                {{ item.name }}
                <div class="text-xs text-gray-500 mt-1">
                  Stok: <span :class="{'text-red-600 font-bold': item.stock === 0}">{{ item.stock }}</span> {{ item.unit }}
                </div>
              </td>
              <td class="px-4 py-2 text-right text-lg">{{ item.qty }}</td>
              <td class="px-4 py-2 text-base">{{ item.unit }}</td>
              <td class="px-4 py-2 text-right text-lg font-bold">{{ item.qty_scan }}</td>
              <td class="px-4 py-2 text-center">
                <span v-if="Number(item.qty_scan) === 0" class="text-gray-400 font-bold text-lg">Belum Scan</span>
                <span v-else-if="Number(item.qty_scan).toFixed(2) === Number(item.qty).toFixed(2)" class="text-green-700 font-bold text-lg animate-pulse">OK</span>
                <span v-else-if="Number(item.qty_scan) > Number(item.qty)" class="text-red-700 font-bold text-lg animate-pulse">Lebih</span>
                <span v-else class="text-yellow-700 font-bold text-lg animate-pulse">Kurang</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Scan Barcode -->
      <div v-if="packingListItems.length" class="mb-8 w-full max-w-xl flex flex-col items-center animate-fade-in">
        <label class="font-semibold text-lg mb-2">Scan Barcode</label>
        <input ref="barcodeInput" v-model="barcodeInputVal" @keyup.enter="onScanBarcode" class="border-2 border-blue-400 rounded-lg px-4 py-3 w-full text-xl text-center focus:ring-2 focus:ring-blue-500 shadow-lg" placeholder="Scan barcode di sini..." autofocus />
        <div v-if="scanFeedback" :class="scanFeedbackClass" class="mt-4 font-bold text-xl min-h-[32px]">{{ scanFeedback }}</div>
      </div>
      <button v-if="packingListItems.length" @click="confirmSubmit" :disabled="!isReadyToSubmit || loadingSubmit" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-10 py-4 rounded-2xl font-extrabold text-2xl shadow-xl hover:scale-105 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
        <i v-if="loadingSubmit" class="fa fa-spinner fa-spin mr-2"></i>
        <i v-else class="fa-solid fa-paper-plane mr-2"></i>
        Submit Delivery Order
      </button>
      <!-- Konfirmasi Modal -->
      <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 relative animate-fade-in">
          <div class="font-bold text-2xl mb-4 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-truck-arrow-right"></i> Konfirmasi Submit</div>
          <div class="mb-6 text-lg">Yakin ingin submit Delivery Order ini?</div>
          <div class="flex justify-end gap-3">
            <button @click="showConfirmModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Batal</button>
            <button @click="submitDO" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700 font-bold">Submit</button>
          </div>
        </div>
      </div>
      <!-- Modal Input Qty Scan -->
      <div v-if="showQtyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xs p-8 relative animate-fade-in">
          <div class="font-bold text-xl mb-4 text-blue-700">Input Qty Scan</div>
          <div class="mb-4">Qty Packing List: <b>{{ qtyModalItem?.qty }}</b></div>
          <input id="qty-modal-input" v-model.number="qtyModalValue" type="number" min="1" :max="qtyModalItem?.qty" class="w-full border-2 border-blue-400 rounded-lg px-4 py-2 text-xl text-center mb-4" @keydown="handleQtyModalKey" />
          <div class="flex justify-end gap-3">
            <button @click="showQtyModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Batal</button>
            <button @click="confirmQtyModal" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700 font-bold">OK</button>
          </div>
        </div>
      </div>
      <!-- Modal Alasan Qty Kurang -->
      <div v-if="showReasonModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xs p-8 relative animate-fade-in">
          <div class="font-bold text-xl mb-4 text-blue-700">Pilih Alasan Qty Kurang</div>
          <div class="grid gap-3 mb-4">
            <button v-for="r in reasonOptions" :key="r" @click="selectReason(r)" class="w-full px-4 py-2 rounded bg-blue-100 text-blue-800 font-semibold hover:bg-blue-200">{{ r }}</button>
          </div>
          <button @click="showReasonModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200 w-full">Batal</button>
        </div>
      </div>
    </div>
  </AppLayout>
  <teleport to="body">
    <PrintStruk v-if="printStrukData" v-bind="printStrukData" />
  </teleport>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, computed, h, createApp } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import PrintStruk from './PrintStruk.vue';
import jsPDF from 'jspdf';
import html2canvas from 'html2canvas';
import QRCode from 'qrcode';

const props = defineProps({
  packingLists: Array,
});
const packingLists = ref(props.packingLists || []);
const packingListItems = reactive([]);
const selectedPackingListId = ref('');
const barcodeInputVal = ref('');
const scanFeedback = ref('');
const scanFeedbackClass = ref('');
const barcodeInput = ref(null);
const showConfirmModal = ref(false);
const showQtyModal = ref(false);
const qtyModalValue = ref(1);
const qtyModalItem = ref(null);
const showReasonModal = ref(false);
const reasonOptions = [
  'Stok kurang',
  'Barang rusak',
  'Permintaan berubah',
  'Lainnya',
];
const selectedReason = ref('');
const loadingSubmit = ref(false);
const printStrukData = ref(null);

const isReadyToSubmit = computed(() => packingListItems.length > 0 && packingListItems.some(i => i.qty_scan > 0));
const selectedPackingList = computed(() => packingLists.value.find(pl => pl.id == selectedPackingListId.value) || null);

async function onPackingListChange() {
  if (!selectedPackingListId.value) return;
  const res = await axios.get(`/api/packing-list/${selectedPackingListId.value}/items`);
  packingListItems.splice(0, packingListItems.length, ...res.data.items.map(item => ({
    ...item,
    qty_scan: 0
  })));
  barcodeInputVal.value = '';
  scanFeedback.value = '';
  nextTick(() => barcodeInput.value?.focus());
}

function onScanBarcode() {
  const input = barcodeInputVal.value.trim();
  if (!input) return;
  let code = input;
  let qty = 1;
  const match = input.match(/^(\S+)\s+(\d+)$/);
  if (match) {
    code = match[1];
    qty = parseInt(match[2], 10) || 1;
  }
  // Cari item yang barcodes-nya mengandung code
  const item = packingListItems.find(i => Array.isArray(i.barcodes) ? i.barcodes.includes(code) : i.barcode === code);
  if (item) {
    const maxQty = Number(item.qty);
    const currentScan = Number(item.qty_scan || 0);
    const stock = Number(item.stock ?? 0);
    if (currentScan + qty > stock) {
      scanFeedback.value = `❌ Qty scan tidak boleh melebihi stock (${stock} ${item.unit})`;
      scanFeedbackClass.value = 'text-red-600';
      barcodeInputVal.value = '';
      nextTick(() => barcodeInput.value?.focus());
      return;
    }
    if (maxQty > 1 && qty === 1) {
      // Tampilkan modal input qty
      qtyModalItem.value = item;
      qtyModalValue.value = Math.min(maxQty - currentScan, stock - currentScan);
      showQtyModal.value = true;
      barcodeInputVal.value = '';
      nextTick(() => {
        const el = document.getElementById('qty-modal-input');
        if (el) el.focus();
      });
      return;
    }
    if (currentScan + qty > maxQty) {
      scanFeedback.value = `❌ Qty scan tidak boleh lebih dari ${maxQty}`;
      scanFeedbackClass.value = 'text-red-600';
      barcodeInputVal.value = '';
      nextTick(() => barcodeInput.value?.focus());
      return;
    }
    item.qty_scan = currentScan + qty;
    scanFeedback.value = `✔️ ${item.name} (${item.qty_scan}/${item.qty})`;
    scanFeedbackClass.value = Number(item.qty_scan).toFixed(2) === Number(item.qty).toFixed(2) ? 'text-green-700' : (Number(item.qty_scan) > Number(item.qty) ? 'text-red-700' : 'text-yellow-700');
  } else {
    scanFeedback.value = '❌ Barcode tidak ditemukan di Packing List!';
    scanFeedbackClass.value = 'text-red-600';
  }
  barcodeInputVal.value = '';
  nextTick(() => barcodeInput.value?.focus());
}

function confirmQtyModal() {
  const item = qtyModalItem.value;
  const maxQty = Number(item.qty);
  const inputQty = Number(qtyModalValue.value);
  const stock = Number(item.stock ?? 0);
  if (inputQty > stock) {
    qtyModalValue.value = stock;
    scanFeedback.value = `❌ Qty scan tidak boleh melebihi stock (${stock} ${item.unit})`;
    scanFeedbackClass.value = 'text-red-600';
    nextTick(() => document.getElementById('qty-modal-input')?.focus());
    return;
  }
  if (inputQty > maxQty) {
    qtyModalValue.value = maxQty;
    return;
  }
  if (inputQty < maxQty) {
    // Tampilkan modal alasan
    showQtyModal.value = false;
    showReasonModal.value = true;
    selectedReason.value = '';
    return;
  }
  // Jika qty sama, langsung set
  item.qty_scan = inputQty;
  item.reason = '';
  showQtyModal.value = false;
  scanFeedback.value = `✔️ ${item.name} (${item.qty_scan}/${item.qty})`;
  scanFeedbackClass.value = 'text-green-700';
  nextTick(() => barcodeInput.value?.focus());
}

function handleQtyModalKey(e) {
  if (e.key === 'Enter') confirmQtyModal();
}

function selectReason(reason) {
  const item = qtyModalItem.value;
  item.qty_scan = Number(qtyModalValue.value);
  item.reason = reason;
  showReasonModal.value = false;
  scanFeedback.value = `✔️ ${item.name} (${item.qty_scan}/${item.qty}) - ${reason}`;
  scanFeedbackClass.value = 'text-yellow-700';
  nextTick(() => barcodeInput.value?.focus());
}

function statusClass(item) {
  if (Number(item.qty_scan).toFixed(2) === Number(item.qty).toFixed(2)) return 'bg-green-50 animate-pulse';
  if (Number(item.qty_scan) > Number(item.qty)) return 'bg-red-50 animate-pulse';
  if (Number(item.qty_scan) > 0) return 'bg-yellow-50 animate-pulse';
  return '';
}

function confirmSubmit() {
  showConfirmModal.value = true;
}

function printStrukToNewWindow(strukData) {
  const printWindow = window.open('', '', 'width=400,height=600');
  if (!printWindow) return;
  // Inject root dan style
  printWindow.document.write(`
    <html><head>
      <title>Print Struk</title>
      <style>
        html, body { width: 80mm !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
        body * { visibility: hidden !important; }
        #struk, #struk * { visibility: visible !important; }
        #struk { position: absolute !important; left: 0 !important; top: 0 !important; width: 80mm !important; min-width: 80mm !important; max-width: 80mm !important; margin: 0 !important; padding: 0 !important; background: #fff !important; z-index: 99999 !important; }
      </style>
    </head><body><div id="print-root"></div></body></html>
  `);
  printWindow.document.close();
  // Render komponen struk ke window baru
  const app = createApp({
    render() {
      return h(PrintStruk, strukData);
    }
  });
  app.mount(printWindow.document.getElementById('print-root'));
  setTimeout(() => {
    printWindow.focus();
    printWindow.print();
    setTimeout(() => printWindow.close(), 500);
  }, 500);
}

async function getBase64FromUrl(url) {
  const response = await fetch(url);
  const blob = await response.blob();
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

async function generateStrukPDF({ orderNumber, date, outlet, items }) {
  try {
    // Ukuran 80mm = 226.77pt, tinggi dinamis
    const pageWidth = 226.77; // 80mm
    let y = 20;
    const pdf = new jsPDF({ unit: 'pt', format: [pageWidth, 600], orientation: 'portrait' });
    pdf.setFont('courier', 'normal');
    pdf.setFontSize(13);
    pdf.text('DELIVERY ORDER', pageWidth/2, y, { align: 'center' });
    y += 18;
    pdf.setFontSize(10);
    pdf.text(`No: ${orderNumber}`, 10, y);
    y += 13;
    pdf.text(`Tanggal: ${date}`, 10, y);
    y += 13;
    pdf.text(`Outlet: ${outlet}`, 10, y);
    y += 13;
    pdf.line(10, y, pageWidth-10, y);
    y += 8;
    // Header tabel
    pdf.text('Item', 10, y);
    pdf.text('Qty', pageWidth/2, y, { align: 'center' });
    pdf.text('Unit', pageWidth-10, y, { align: 'right' });
    y += 10;
    pdf.text('-------------------------------', 10, y);
    y += 10;
    // Isi item
    items.forEach(i => {
      pdf.text(i.name, 10, y);
      pdf.text(String(i.qty_scan), pageWidth/2, y, { align: 'center' });
      pdf.text(i.unit, pageWidth-10, y, { align: 'right' });
      y += 12;
    });
    pdf.text('-------------------------------', 10, y);
    y += 16;
    pdf.text('Terima kasih', pageWidth/2, y, { align: 'center' });
    y += 18;
    pdf.setFontSize(9);
    pdf.text('Cetak struk by jsPDF', pageWidth/2, y, { align: 'center' });
    // Resize page height
    pdf.internal.pageSize.setHeight(y+20);
    pdf.autoPrint();
    pdf.output('dataurlnewwindow');
  } catch (err) {
    console.error('Gagal generate PDF struk:', err);
    alert('Gagal generate PDF struk. Cek console untuk detail error.');
  }
}

function submitDO() {
  showConfirmModal.value = false;
  loadingSubmit.value = true;
  const itemsToSubmit = packingListItems
    .filter(i => i.qty_scan > 0)
    .map(i => ({
      id: i.item_id || i.id, // fallback jika item_id tidak ada
      barcode: Array.isArray(i.barcodes) ? (i.barcodes[0] || null) : (i.barcode || null),
      qty: i.qty,
      qty_scan: i.qty_scan,
      unit: i.unit,
      name: i.name, // untuk struk
      reason: i.reason || null,
    }));
  if (!selectedPackingListId.value || itemsToSubmit.length === 0) {
    scanFeedback.value = 'Pilih packing list dan scan minimal 1 item!';
    scanFeedbackClass.value = 'text-red-600';
    loadingSubmit.value = false;
    return;
  }
  router.post('/delivery-order', {
    packing_list_id: selectedPackingListId.value,
    items: itemsToSubmit,
  }, {
    onSuccess: (page) => {
      scanFeedback.value = 'Delivery Order berhasil disimpan!';
      scanFeedbackClass.value = 'text-green-700';
      const orderNumber = page?.props?.orderNumber || 'DO Terakhir';
      const date = new Date().toLocaleDateString('id-ID');
      const outlet = selectedPackingList.value?.nama_outlet || '-';
      const strukData = {
        orderNumber,
        date,
        outlet,
        items: itemsToSubmit,
      };
      // printStrukToNewWindow(strukData);
      generateStrukPDF({ orderNumber, date, outlet, items: itemsToSubmit });
      selectedPackingListId.value = '';
      packingListItems.splice(0);
    },
    onError: (errors) => {
      scanFeedback.value = errors?.message || 'Gagal menyimpan Delivery Order!';
      scanFeedbackClass.value = 'text-red-600';
    },
    onFinish: () => {
      loadingSubmit.value = false;
    }
  });
}
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.5s;
}
</style> 