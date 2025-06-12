<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6 flex flex-col items-center">
      <h1 class="text-3xl font-bold mb-8 text-blue-800 flex items-center gap-3">
        <i class="fa-solid fa-barcode text-blue-500"></i> Good Receive Outlet (Scan Barang)
      </h1>
      <!-- Pilih Delivery Order -->
      <div class="mb-6 flex flex-col md:flex-row gap-4 items-center w-full max-w-xl">
        <label class="font-semibold text-lg">No Delivery Order</label>
        <select v-model="selectedDOId" @change="onDOChange" class="border-2 border-blue-400 rounded-lg px-3 py-2 w-full max-w-xs focus:ring-2 focus:ring-blue-500">
          <option value="">Pilih Nomor DO...</option>
          <option v-for="doOpt in doOptions" :key="doOpt.id" :value="doOpt.id">
            {{ doOpt.do_date ? new Date(doOpt.do_date).toLocaleDateString('id-ID') : '-' }} - {{ doOpt.division_name || '-' }} - {{ doOpt.number }}
          </option>
        </select>
      </div>
      <!-- Card Info DO terpilih -->
      <div v-if="doDetail" class="mb-6 w-full max-w-xl bg-blue-50 border-l-4 border-blue-400 p-4 rounded animate-fade-in">
        <div class="font-bold text-blue-800 mb-1">Info Delivery Order</div>
        <div><b>Nomor DO:</b> {{ doDetail.do.do_number }}</div>
        <div><b>Tanggal DO:</b> {{ doDetail.do.do_created_at ? new Date(doDetail.do.do_created_at).toLocaleDateString('id-ID') : '-' }}</div>
        <div><b>Nomor Packing:</b> {{ doDetail.do.packing_number || '-' }}</div>
        <div><b>Nomor Floor Order:</b> {{ doDetail.do.floor_order_number || '-' }}</div>
        <div><b>Tanggal Floor Order:</b> {{ doDetail.do.floor_order_date || '-' }}</div>
      </div>
      <!-- Tabel Item DO -->
      <div v-if="items.length" class="mb-8 w-full max-w-3xl animate-fade-in">
        <table class="min-w-full rounded-xl overflow-hidden shadow-xl">
          <thead class="bg-blue-100">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">Nama Item</th>
              <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase">Qty DO</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">Unit</th>
              <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase">Qty Scan</th>
              <th class="px-4 py-2 text-center text-xs font-bold text-blue-700 uppercase">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.delivery_order_item_id" :class="statusClass(item)">
              <td class="px-4 py-2 text-base">{{ item.item_name }}</td>
              <td class="px-4 py-2 text-right text-lg">{{ item.qty_packing_list }}</td>
              <td class="px-4 py-2 text-base">{{ item.unit }}</td>
              <td class="px-4 py-2 text-right text-lg font-bold">{{ item.qty_scan }}</td>
              <td class="px-4 py-2 text-center">
                <span v-if="Number(item.qty_scan) === 0" class="text-gray-400 font-bold text-lg">Belum Scan</span>
                <span v-else-if="Number(item.qty_scan).toFixed(2) === Number(item.qty_packing_list).toFixed(2)" class="text-green-700 font-bold text-lg animate-pulse">OK</span>
                <span v-else-if="Number(item.qty_scan) > Number(item.qty_packing_list)" class="text-red-700 font-bold text-lg animate-pulse">Lebih</span>
                <span v-else class="text-yellow-700 font-bold text-lg animate-pulse">Kurang</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Scan Barcode -->
      <div v-if="items.length" class="mb-8 w-full max-w-xl flex flex-col items-center animate-fade-in">
        <label class="font-semibold text-lg mb-2">Scan Barcode</label>
        <input ref="barcodeInput" v-model="barcodeInputVal" @keyup.enter="onScanBarcode" class="border-2 border-blue-400 rounded-lg px-4 py-3 w-full text-xl text-center focus:ring-2 focus:ring-blue-500 shadow-lg" placeholder="Scan barcode di sini..." autofocus />
        <div v-if="scanFeedback" :class="scanFeedbackClass" class="mt-4 font-bold text-xl min-h-[32px]">{{ scanFeedback }}</div>
      </div>
      <button v-if="items.length" @click="confirmSubmit" :disabled="!isReadyToSubmit || loadingSubmit" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-10 py-4 rounded-2xl font-extrabold text-2xl shadow-xl hover:scale-105 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
        <i v-if="loadingSubmit" class="fa fa-spinner fa-spin mr-2"></i>
        <i v-else class="fa-solid fa-paper-plane mr-2"></i>
        Submit Good Receive
      </button>
      <!-- Konfirmasi Modal -->
      <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 relative animate-fade-in">
          <div class="font-bold text-2xl mb-4 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-truck-arrow-right"></i> Konfirmasi Submit</div>
          <div class="mb-6 text-lg">Yakin ingin submit Good Receive ini?</div>
          <div class="flex justify-end gap-3">
            <button @click="showConfirmModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Batal</button>
            <button @click="submitGR" :disabled="loadingSubmit" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700 font-bold">
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
          <div class="mb-4">Qty DO: <b>{{ qtyModalItem?.qty_packing_list }}</b></div>
          <input id="qty-modal-input" v-model.number="qtyModalValue" type="number" min="1" :max="qtyModalItem?.qty_packing_list" class="w-full border-2 border-blue-400 rounded-lg px-4 py-2 text-xl text-center mb-4" @keydown="handleQtyModalKey" />
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
import { ref, reactive, onMounted, nextTick, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const doOptions = ref([]);
const selectedDOId = ref('');
const doDetail = ref(null);
const items = reactive([]);
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

const isReadyToSubmit = computed(() => items.length > 0 && items.some(i => i.qty_scan > 0));

onMounted(() => {
  axios.get('/outlet-food-good-receives/available-dos').then(res => {
    doOptions.value = res.data;
  });
});

function onDOChange() {
  const doOpt = doOptions.value.find(opt => String(opt.id) === String(selectedDOId.value));
  if (!doOpt) return;
  axios.get(`/outlet-food-good-receives/do-detail/${doOpt.id}`)
    .then(res => {
      doDetail.value = res.data;
      items.splice(0, items.length, ...res.data.items.map(item => ({ ...item, qty_scan: 0 })));
      barcodeInputVal.value = '';
      scanFeedback.value = '';
      nextTick(() => barcodeInput.value?.focus());
    });
}

function onScanBarcode() {
  const input = barcodeInputVal.value.trim();
  if (!input) return;
  let code = input;
  let qty = 1;
  const match = input.match(/^([\S]+)\s+(\d+)$/);
  if (match) {
    code = match[1];
    qty = parseInt(match[2], 10) || 1;
  }
  const item = items.find(i => Array.isArray(i.barcodes) ? i.barcodes.includes(code) : i.barcode === code);
  if (item) {
    console.log('DEBUG SCAN:', item.item_name, 'unit_type:', item.unit_type);
    const maxQty = Number(item.qty_packing_list);
    const currentScan = Number(item.qty_scan || 0);
    if (item.unit_type === 'kiloan') {
      qtyModalItem.value = item;
      qtyModalValue.value = Math.min(maxQty - currentScan, maxQty - currentScan);
      showQtyModal.value = true;
      barcodeInputVal.value = '';
      nextTick(() => {
        const el = document.getElementById('qty-modal-input');
        if (el) el.focus();
      });
      return;
    }
    qty = 1;
    if (currentScan + qty > maxQty) {
      scanFeedback.value = `❌ Qty scan tidak boleh lebih dari ${maxQty}`;
      scanFeedbackClass.value = 'text-red-600';
      barcodeInputVal.value = '';
      nextTick(() => barcodeInput.value?.focus());
      return;
    }
    item.qty_scan = currentScan + qty;
    scanFeedback.value = `✔️ ${item.item_name} (${item.qty_scan}/${item.qty_packing_list})`;
    scanFeedbackClass.value = Number(item.qty_scan).toFixed(2) === Number(item.qty_packing_list).toFixed(2) ? 'text-green-700' : (Number(item.qty_scan) > Number(item.qty_packing_list) ? 'text-red-700' : 'text-yellow-700');
  } else {
    scanFeedback.value = '❌ Barcode tidak ditemukan di DO!';
    scanFeedbackClass.value = 'text-red-600';
  }
  barcodeInputVal.value = '';
  nextTick(() => barcodeInput.value?.focus());
}

function confirmQtyModal() {
  const item = qtyModalItem.value;
  const maxQty = Number(item.qty_packing_list);
  const inputQty = Number(qtyModalValue.value);
  if (inputQty > maxQty) {
    qtyModalValue.value = maxQty;
    return;
  }
  item.qty_scan = (item.qty_scan || 0) + inputQty;
  showQtyModal.value = false;
  scanFeedback.value = `✔️ ${item.item_name} (${item.qty_scan}/${item.qty_packing_list})`;
  scanFeedbackClass.value = Number(item.qty_scan).toFixed(2) === Number(item.qty_packing_list).toFixed(2) ? 'text-green-700' : (Number(item.qty_scan) > Number(item.qty_packing_list) ? 'text-red-700' : 'text-yellow-700');
  nextTick(() => barcodeInput.value?.focus());
}

function handleQtyModalKey(e) {
  if (e.key === 'Enter') confirmQtyModal();
}

function statusClass(item) {
  if (Number(item.qty_scan).toFixed(2) === Number(item.qty_packing_list).toFixed(2)) return 'bg-green-50 animate-pulse';
  if (Number(item.qty_scan) > Number(item.qty_packing_list)) return 'bg-red-50 animate-pulse';
  if (Number(item.qty_scan) > 0) return 'bg-yellow-50 animate-pulse';
  return '';
}

function confirmSubmit() {
  showConfirmModal.value = true;
}

async function submitGR() {
  loadingSubmit.value = true;
  try {
    // Siapkan payload sesuai backend
    const payload = {
      delivery_order_id: selectedDOId.value,
      receive_date: doDetail.value?.do?.do_created_at ? doDetail.value.do.do_created_at.split('T')[0] : new Date().toISOString().split('T')[0],
      notes: '',
      items: items.map(i => ({
        item_id: i.item_id,
        qty: i.qty_packing_list,
        unit_id: i.unit_id,
        received_qty: i.qty_scan
      }))
    };
    const res = await axios.post('/outlet-food-good-receives', payload);
    showConfirmModal.value = false;
    if (res.data && res.data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Sukses',
        text: 'Good Receive berhasil disimpan!',
        timer: 1500,
        showConfirmButton: false
      });
      window.location.href = '/outlet-food-good-receives';
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: res.data.message || 'Gagal menyimpan Good Receive'
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

function selectReason(reason) {
  selectedReason.value = reason;
  showReasonModal.value = false;
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