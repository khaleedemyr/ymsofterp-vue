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
            {{ new Date(pl.created_at).toLocaleDateString('id-ID') }} - {{ pl.nama_outlet || '-' }} - {{ pl.division_name || '-' }} - {{ pl.packing_number }}
          </option>
        </select>
      </div>
      <!-- Card Info Packing List terpilih -->
      <div v-if="selectedPackingList" class="mb-6 w-full max-w-xl bg-blue-50 border-l-4 border-blue-400 p-4 rounded animate-fade-in">
        <div class="font-bold text-blue-800 mb-1">Info Packing List</div>
        <div><b>Outlet:</b> {{ selectedPackingList.nama_outlet || '-' }}</div>
        <div><b>Warehouse Outlet:</b> {{ selectedPackingList.warehouse_outlet_name || '-' }}</div>
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
            <button @click="submitDO" :disabled="loadingSubmit" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700 font-bold">
              <i v-if="loadingSubmit" class="fa fa-spinner fa-spin mr-2"></i>
              Submit
            </button>
          </div>
        </div>
      </div>
      <!-- Modal Input Qty Scan -->
      <div v-if="showQtyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xs p-8 relative animate-fade-in">
          <div class="font-bold text-xl mb-4 text-blue-700">Input Qty Scan</div>
          <div class="mb-2 text-sm text-gray-600">Item: <b class="text-blue-800">{{ qtyModalItem?.name }}</b></div>
          <div class="mb-4">Qty Packing List: <b>{{ qtyModalItem?.qty }}</b></div>
          <input id="qty-modal-input" v-model.number="qtyModalValue" type="number" min="0.01" step="0.01" :max="qtyModalItem?.qty" class="w-full border-2 border-blue-400 rounded-lg px-4 py-2 text-xl text-center mb-4" @keydown="handleQtyModalKey" />
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
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, computed, h, createApp } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import jsPDF from 'jspdf';
import html2canvas from 'html2canvas';
import QRCode from 'qrcode';
import Swal from 'sweetalert2';

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
const qtyModalValue = ref(0.01);
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
const doNumber = ref('');
const error = ref("");
const isLoading = ref(false);

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
  // Ubah parsing qty agar bisa menerima pecahan
  const match = input.match(/^([\S]+)\s+(\d+(?:\.\d+)?)$/);
  if (match) {
    code = match[1];
    qty = parseFloat(match[2]) || 1;
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
    // Selalu tampilkan modal input qty jika scan tanpa qty (qty === 1, artinya user tidak input qty di barcode)
    if (qty === 1) {
      qtyModalItem.value = item;
      // Default value sesuai qty packing list
      qtyModalValue.value = maxQty;
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
    // Simpan barcode hasil scan
    if (!item.barcode) item.barcode = [];
    if (Array.isArray(item.barcode)) {
      for (let i = 0; i < qty; i++) item.barcode.push(code);
    } else {
      item.barcode = [item.barcode, ...Array(qty-1).fill(code)];
    }
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
  // Hapus pembatasan inputQty < 1, biarkan user input < 1
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

async function getBase64FromUrl(url) {
  try {
    const response = await fetch(url);
    const blob = await response.blob();
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onloadend = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  } catch (e) {
    console.error('Gagal fetch logo:', e);
    throw e;
  }
}

async function generateStrukPDF({ orderNumber, date, outlet, items, kasirName, divisionName, warehouseName, roNumber, roDate, roCreatorName }) {
  const pdf = new jsPDF({ unit: 'mm', format: [80, 297] });
  let y = 10;
  // Judul rata tengah, font besar, bold
  pdf.setFontSize(13);
  pdf.setFont(undefined, 'bold');
  pdf.text('DELIVERY ORDER', 40, y, { align: 'center' });
  y += 7;
  // JUSTUS GROUP di bawah judul
  pdf.setFontSize(10);
  pdf.text('JUSTUS GROUP', 40, y, { align: 'center' });
  y += 6;
  // Warehouse division/warehouse rata tengah, bold
  pdf.setFont(undefined, 'bold');
  if (divisionName || warehouseName) {
    pdf.text(`${divisionName || ''}${divisionName && warehouseName ? ' - ' : ''}${warehouseName || ''}`, 40, y, { align: 'center' });
    y += 6;
  }
  pdf.setFont(undefined, 'normal'); // Kembalikan ke normal untuk info lain
  // Info lain, font kecil
  pdf.setFontSize(9);
  pdf.text(`No: ${orderNumber}`, 2, y); y += 4.5;
  pdf.text(`Tanggal: ${date}`, 2, y); y += 4.5;
  pdf.text(`Outlet: ${outlet}`, 2, y); y += 4.5;
  if (roNumber) { pdf.text(`RO: ${roNumber}`, 2, y); y += 4.5; }
  if (roDate) { pdf.text(`Tgl RO: ${roDate}`, 2, y); y += 4.5; }
  if (roCreatorName) { pdf.text(`Pembuat RO: ${roCreatorName}`, 2, y); y += 4.5; }
  // Garis full width, spasi sebelum dan sesudah
  y += 2;
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 3;
  // ITEM LIST
  if (items && items.length) {
    items.forEach(i => {
      const itemLines = pdf.splitTextToSize(i.name, 60);
      itemLines.forEach(line => {
        pdf.text(line, 2, y);
        y += 3.8;
      });
      pdf.text(`${i.qty_scan} ${i.unit_code || i.unit}`, 2, y);
      y += 5;
    });
  } else {
    pdf.text('TIDAK ADA ITEM', 2, y); y += 4.5;
  }
  // Garis full width sebelum kasir
  y += 2;
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 3;
  if (kasirName) { pdf.text(`Kasir: ${kasirName}`, 2, y); y += 4.5; }
  pdf.text('Terima kasih', 2, y);
  pdf.output('dataurlnewwindow');
}

async function submitDO() {
  error.value = '';
  loadingSubmit.value = true;
  try {
    // Generate nomor DO
    const date = new Date().toISOString().slice(2,10).replace(/-/g,'');
    const random = Math.random().toString(36).substring(2,6).toUpperCase();
    doNumber.value = `DO${date}${random}`;
    // Buat DO baru
    const doRes = await axios.post('/delivery-order', {
      packing_list_id: selectedPackingListId.value,
      items: packingListItems.map(item => ({
        id: item.id,
        barcode: Array.isArray(item.barcode) && item.barcode.length > 0 ? item.barcode : (item.barcode ? [item.barcode] : []),
        qty: item.qty,
        qty_scan: item.qty_scan,
        unit: item.unit
      }))
    });
    showConfirmModal.value = false;
    if (doRes.data.success) {
      await generateStrukPDF({
        orderNumber: doNumber.value,
        date: new Date().toLocaleDateString('id-ID'),
        outlet: selectedPackingList.value?.nama_outlet || '-',
        warehouseOutlet: selectedPackingList.value?.warehouse_outlet_name || '-',
        items: packingListItems,
        kasirName: doRes.data.kasir_name || '-',
        divisionName: doRes.data.division_name || '',
        warehouseName: doRes.data.warehouse_name || '',
        roNumber: doRes.data.ro_number || '',
        roDate: doRes.data.ro_date ? new Date(doRes.data.ro_date).toLocaleDateString('id-ID') : '',
        roCreatorName: doRes.data.ro_creator_name || ''
      });
      await Swal.fire({
        icon: 'success',
        title: 'Sukses',
        text: 'Delivery Order berhasil disimpan!',
        timer: 1500,
        showConfirmButton: false
      });
      router.visit('/delivery-order');
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: doRes.data.message || 'Gagal menyimpan Delivery Order'
      });
    }
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan server.'
    });
  } finally {
    loadingSubmit.value = false;
  }
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