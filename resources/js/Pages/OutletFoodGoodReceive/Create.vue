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
        <div><b>Warehouse Outlet:</b> {{ doDetail.do.warehouse_outlet_name || '-' }}</div>
      </div>

      <!-- Card Info PO dengan Source Type dan Outlet -->
      <div v-if="doDetail" class="mb-6 w-full max-w-xl bg-green-50 border-l-4 border-green-400 p-4 rounded animate-fade-in">
        <div class="font-bold text-green-800 mb-1">Info Purchase Order</div>
        <div v-if="doDetail.po_info">
          <div><b>Nomor PO:</b> {{ doDetail.po_info.po_number }}</div>
          <div><b>Source Type:</b> 
            <span :class="{
              'bg-blue-100 text-blue-700': doDetail.po_info.source_type_display === 'PR Foods',
              'bg-green-100 text-green-700': doDetail.po_info.source_type_display === 'RO Supplier',
              'bg-gray-100 text-gray-700': doDetail.po_info.source_type_display === 'Unknown'
            }" class="px-2 py-1 rounded-full text-xs font-semibold">
              {{ doDetail.po_info.source_type_display }}
            </span>
          </div>
          <div v-if="doDetail.po_info.outlet_names && doDetail.po_info.outlet_names.length > 0">
            <b>Outlet:</b> 
            <div class="flex flex-wrap gap-1 mt-1">
              <span v-for="outlet in doDetail.po_info.outlet_names" :key="outlet" 
                    class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full">
                {{ outlet }}
              </span>
            </div>
          </div>
          <div v-else-if="doDetail.po_info.source_type_display === 'PR Foods'">
            <b>Outlet:</b> <span class="text-gray-500 text-sm">-</span>
          </div>
        </div>
        <div v-else>
          <div class="text-gray-500 text-sm">Tidak ada informasi PO</div>
        </div>
      </div>
      <!-- Tabel Item DO -->
      <div v-if="items.length" class="mb-8 w-full max-w-3xl animate-fade-in">
        <table class="min-w-full rounded-xl overflow-hidden shadow-xl">
          <thead class="bg-blue-100">
            <tr>
              <th class="px-2 py-2 text-left text-xs font-bold text-blue-700 uppercase">SPS</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">Nama Item</th>
              <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase">Qty DO</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">Unit</th>
              <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase">Qty Scan</th>
              <th class="px-4 py-2 text-center text-xs font-bold text-blue-700 uppercase">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.delivery_order_item_id" :class="statusClass(item)">
              <td class="px-2 py-2">
                <button type="button" class="px-2 py-1 rounded bg-yellow-100 text-yellow-800 text-xs font-semibold hover:bg-yellow-200 border border-yellow-300 flex items-center gap-1" @click="openSpsModal(item)" :disabled="!item.item_id">
                  <i class="fa fa-info-circle"></i> SPS
                </button>
              </td>
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
            <button @click="submitGR" :disabled="loadingSubmit" class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700 font-bold disabled:opacity-50 disabled:cursor-not-allowed">
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
          <input id="qty-modal-input" v-model="qtyModalValue" type="number" min="0.01" step="0.01" inputmode="decimal" :max="qtyModalItem?.qty_packing_list" class="w-full border-2 border-blue-400 rounded-lg px-4 py-2 text-xl text-center mb-4" @keydown="handleQtyModalKey" />
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
      <!-- Modal SPS -->
      <Modal :show="spsModal" @close="closeSpsModal">
        <div class="p-4 min-w-[320px] max-w-[90vw]">
          <div class="flex justify-between items-center mb-2">
            <h2 class="text-lg font-bold text-gray-700">Detail Item</h2>
            <button @click="closeSpsModal" class="text-gray-400 hover:text-gray-700"><i class="fa fa-times"></i></button>
          </div>
          <div v-if="spsLoading" class="text-center py-8"><i class="fa fa-spinner fa-spin text-blue-400 text-2xl"></i></div>
          <div v-else-if="spsItem && !spsItem.error">
            <div class="mb-2"><span class="font-semibold">Nama:</span> {{ spsItem.name }}</div>
            <div class="mb-2"><span class="font-semibold">Deskripsi:</span>
              <span v-if="spsItem.description">{{ spsItem.description }}</span>
              <span v-else class="italic text-gray-400">(Tidak ada deskripsi)</span>
            </div>
            <div class="mb-2"><span class="font-semibold">Spesifikasi:</span>
              <span v-if="spsItem.specification">{{ spsItem.specification }}</span>
              <span v-else class="italic text-gray-400">(Tidak ada spesifikasi)</span>
            </div>
            <div v-if="spsItem.images && spsItem.images.length" class="mb-2">
              <span class="font-semibold">Gambar:</span>
              <div class="flex flex-wrap gap-2 mt-1">
                <img v-for="img in spsItem.images" :key="img.id" :src="img.path.startsWith('http') ? img.path : '/storage/' + img.path" class="w-24 h-24 object-contain border rounded bg-white" />
              </div>
            </div>
          </div>
          <div v-else-if="spsItem && spsItem.error" class="text-red-500 text-center py-4">{{ spsItem.error }}</div>
        </div>
      </Modal>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import Modal from '@/Components/Modal.vue';

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
const lastSubmitTime = ref(0);
const spsModal = ref(false);
const spsItem = ref({});
const spsLoading = ref(false);

const isReadyToSubmit = computed(() => items.length > 0);

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
  const match = input.match(/^([\S]+)\s+(\d+(?:\.\d+)?)$/);
  if (match) {
    code = match[1];
    qty = parseFloat(match[2]) || 1;
  }
  const item = items.find(i => Array.isArray(i.barcodes) ? i.barcodes.includes(code) : i.barcode === code);
  if (item) {
    const maxQty = Number(item.qty_packing_list);
    const currentScan = Number(item.qty_scan || 0);
    if (item.unit_type === 'kiloan') {
      qtyModalItem.value = item;
      // Default value sesuai sisa qty yang belum di-scan, minimal 0.01
      const remainingQty = maxQty - currentScan;
      if (remainingQty <= 0) {
        scanFeedback.value = `❌ Qty scan sudah mencapai maksimal (${maxQty})`;
        scanFeedbackClass.value = 'text-red-600';
        barcodeInputVal.value = '';
        nextTick(() => barcodeInput.value?.focus());
        return;
      }
      qtyModalValue.value = Math.max(0.01, remainingQty);
      showQtyModal.value = true;
      barcodeInputVal.value = '';
      nextTick(() => {
        const el = document.getElementById('qty-modal-input');
        if (el) el.focus();
      });
      return;
    }
    // Untuk item non-kiloan, qty default = 1 jika scan tanpa qty
    if (qty === 1 && !input.match(/\s+\d/)) {
      qty = 1;
    }
    
    // Jika scan akan melebihi qty DO, set ke qty DO maksimal
    if (currentScan + qty > maxQty) {
      const remainingQty = maxQty - currentScan;
      if (remainingQty <= 0) {
        scanFeedback.value = `❌ Qty scan sudah mencapai maksimal (${maxQty})`;
        scanFeedbackClass.value = 'text-red-600';
        barcodeInputVal.value = '';
        nextTick(() => barcodeInput.value?.focus());
        return;
      }
      // Set qty scan ke maksimal yang diizinkan
      qty = remainingQty;
    }
    item.qty_scan = currentScan + qty;
    const isExact = Number(item.qty_scan).toFixed(2) === Number(item.qty_packing_list).toFixed(2);
    const isOver = Number(item.qty_scan) > Number(item.qty_packing_list);
    
    if (isExact) {
      scanFeedback.value = `✅ ${item.item_name} (${item.qty_scan.toFixed(2)}/${item.qty_packing_list}) - LENGKAP!`;
      scanFeedbackClass.value = 'text-green-700';
    } else if (isOver) {
      scanFeedback.value = `⚠️ ${item.item_name} (${item.qty_scan.toFixed(2)}/${item.qty_packing_list}) - LEBIH!`;
      scanFeedbackClass.value = 'text-red-700';
    } else {
      scanFeedback.value = `✔️ ${item.item_name} (${item.qty_scan.toFixed(2)}/${item.qty_packing_list})`;
      scanFeedbackClass.value = 'text-yellow-700';
    }
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
  const currentScan = Number(item.qty_scan || 0);
  const inputQty = Number(qtyModalValue.value);
  
  // Validasi input minimal 0.01
  if (inputQty < 0.01) {
    qtyModalValue.value = 0.01;
    return;
  }
  
  // Validasi tidak boleh melebihi sisa qty
  const remainingQty = maxQty - currentScan;
  if (inputQty > remainingQty) {
    qtyModalValue.value = remainingQty;
    return;
  }
  
  item.qty_scan = currentScan + inputQty;
  showQtyModal.value = false;
  
  const isExact = Number(item.qty_scan).toFixed(2) === Number(item.qty_packing_list).toFixed(2);
  const isOver = Number(item.qty_scan) > Number(item.qty_packing_list);
  
  if (isExact) {
    scanFeedback.value = `✅ ${item.item_name} (${item.qty_scan.toFixed(2)}/${item.qty_packing_list}) - LENGKAP!`;
    scanFeedbackClass.value = 'text-green-700';
  } else if (isOver) {
    scanFeedback.value = `⚠️ ${item.item_name} (${item.qty_scan.toFixed(2)}/${item.qty_packing_list}) - LEBIH!`;
    scanFeedbackClass.value = 'text-red-700';
  } else {
    scanFeedback.value = `✔️ ${item.item_name} (${item.qty_scan.toFixed(2)}/${item.qty_packing_list})`;
    scanFeedbackClass.value = 'text-yellow-700';
  }
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

async function confirmSubmit() {
  // Validasi sebelum submit
  const errors = [];
  
  if (!selectedDOId.value) {
    errors.push('Delivery Order belum dipilih');
  }
  
  if (!items.length) {
    errors.push('Tidak ada item untuk diproses');
  }
  
  // Cek apakah ada item yang belum di-scan (hanya warning, bukan error)
  const unscannedItems = items.filter(item => Number(item.qty_scan) === 0);
  const incompleteItems = items.filter(item => {
    const qtyScan = Number(item.qty_scan);
    const qtyDO = Number(item.qty_packing_list);
    return qtyScan > 0 && qtyScan < qtyDO;
  });
  
  if (errors.length > 0) {
    await Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      html: errors.map(err => `• ${err}`).join('<br>'),
      confirmButtonText: 'OK',
      width: '500px'
    });
    return;
  }
  
  // Tampilkan warning jika ada item yang belum di-scan atau incomplete
  let warningMessage = '';
  if (unscannedItems.length > 0) {
    warningMessage += `<p><strong>⚠️ ${unscannedItems.length} item belum di-scan:</strong><br>${unscannedItems.map(i => i.item_name).join(', ')}</p>`;
  }
  if (incompleteItems.length > 0) {
    warningMessage += `<p><strong>⚠️ ${incompleteItems.length} item qty scan kurang dari DO:</strong><br>${incompleteItems.map(i => `${i.item_name} (${i.qty_scan}/${i.qty_packing_list})`).join(', ')}</p>`;
  }
  
  // Tampilkan konfirmasi dengan detail
  const totalItems = items.length;
  const completedItems = items.filter(item => Number(item.qty_scan) > 0).length;
  const totalQty = items.reduce((sum, item) => sum + Number(item.qty_scan), 0);
  
  const result = await Swal.fire({
    icon: warningMessage ? 'warning' : 'question',
    title: 'Konfirmasi Submit Good Receive',
    html: `
      <div class="text-left">
        <p><strong>Delivery Order:</strong> ${doDetail.value?.do?.do_number || 'N/A'}</p>
        <p><strong>Total Item:</strong> ${completedItems}/${totalItems}</p>
        <p><strong>Total Qty Scan:</strong> ${totalQty.toFixed(2)}</p>
        <p><strong>Warehouse Outlet:</strong> ${doDetail.value?.do?.warehouse_outlet_name || 'N/A'}</p>
        ${warningMessage ? `<br><div class="bg-yellow-50 p-3 rounded border-l-4 border-yellow-400">${warningMessage}</div>` : ''}
        <br>
        <p class="text-sm text-gray-600">Data akan disimpan ke inventory dan tidak dapat diubah.</p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Ya, Submit!',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    width: '600px'
  });
  
  if (result.isConfirmed) {
    showConfirmModal.value = true;
  }
}

async function submitGR() {
  // Prevent double submit with time-based debounce
  const now = Date.now();
  if (loadingSubmit.value || (now - lastSubmitTime.value < 2000)) {
    console.log('Submit already in progress or too soon, ignoring duplicate request');
    return;
  }
  
  lastSubmitTime.value = now;
  loadingSubmit.value = true;
  
  // Disable all submit buttons to prevent multiple clicks
  const submitButtons = document.querySelectorAll('button[onclick*="submitGR"], button[class*="bg-blue-600"]');
  submitButtons.forEach(btn => {
    btn.disabled = true;
    btn.style.opacity = '0.5';
  });
  try {
    
    
    // Siapkan payload sesuai backend
    const payload = {
      delivery_order_id: Number(selectedDOId.value),
      receive_date: new Date().toISOString().split('T')[0], // Gunakan tanggal hari ini
      notes: '',
      warehouse_outlet_id: doDetail.value?.do?.warehouse_outlet_id ? Number(doDetail.value.do.warehouse_outlet_id) : null,
      items: items.map(i => ({
        item_id: Number(i.item_id),
        qty: Number(i.qty_packing_list),
        unit_id: Number(i.unit_id),
        received_qty: Number(i.qty_scan)
      }))
    };
    
    
    // Validasi payload sebelum kirim
    if (!payload.delivery_order_id) {
      throw new Error('Delivery Order ID tidak boleh kosong');
    }
    
    if (!payload.items || payload.items.length === 0) {
      throw new Error('Items tidak boleh kosong');
    }
    
    // Validasi setiap item
    for (let i = 0; i < payload.items.length; i++) {
      const item = payload.items[i];
      
      if (!item.item_id) {
        throw new Error(`Item ${i + 1}: Item ID tidak boleh kosong`);
      }
      if (!item.unit_id) {
        throw new Error(`Item ${i + 1}: Unit ID tidak boleh kosong`);
      }
      if (item.received_qty < 0) {
        throw new Error(`Item ${i + 1}: Received Qty tidak boleh negatif`);
      }
      
      // Validasi tipe data
      if (typeof item.item_id !== 'number') {
        throw new Error(`Item ${i + 1}: Item ID harus berupa angka`);
      }
      if (typeof item.unit_id !== 'number') {
        throw new Error(`Item ${i + 1}: Unit ID harus berupa angka`);
      }
      if (typeof item.received_qty !== 'number') {
        throw new Error(`Item ${i + 1}: Received Qty harus berupa angka`);
      }
    }
    
    
    const res = await axios.post('/outlet-food-good-receives', payload);
    showConfirmModal.value = false;
    
    if (res.data && res.data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Good Receive berhasil disimpan dan inventory telah diperbarui!',
        timer: 2000,
        showConfirmButton: false
      });
      window.location.href = '/outlet-food-good-receives';
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal Menyimpan',
        text: res.data?.message || 'Gagal menyimpan Good Receive. Silakan coba lagi.',
        confirmButtonText: 'OK'
      });
    }
  } catch (e) {
    
    let errorMessage = 'Terjadi kesalahan server. Silakan coba lagi.';
    let errorDetails = '';
    let showDetails = false;
    
    // Handle different types of errors
    if (e.message && !e.response) {
      // Custom validation error
      errorMessage = e.message;
      errorDetails = `Validation Error: ${e.message}`;
      showDetails = true;
    } else if (e.response) {
      // Server responded with error status
      const status = e.response.status;
      const data = e.response.data;
      
      if (status === 422) {
        // Check if it's a duplicate submission error
        if (data.message && (data.message.includes('sudah pernah disubmit') || data.message.includes('sudah pernah dibuat'))) {
          const duplicateInfo = data.duplicate_info || {};
          const existingTime = duplicateInfo.submitted_at ? new Date(duplicateInfo.submitted_at).toLocaleString('id-ID') : 'Tidak diketahui';
          const timeDiff = duplicateInfo.time_diff || 0;
          const remainingTime = Math.max(0, 30 - timeDiff);
          
          await Swal.fire({
            icon: 'warning',
            title: 'Data Duplikasi Terdeteksi',
            html: `
              <div class="text-left">
                <div class="mb-4">
                  <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mb-2"></i>
                  <p class="text-lg font-semibold text-gray-800">${data.message}</p>
                </div>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                  <div class="flex">
                    <div class="flex-shrink-0">
                      <i class="fas fa-info-circle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm text-yellow-700">
                        <strong>Detail Duplikasi:</strong><br>
                        • Delivery Order: ${doDetail.value?.do?.do_number || 'N/A'}<br>
                        • Data Sebelumnya: ${duplicateInfo.existing_number || 'N/A'}<br>
                        • Waktu Submit Sebelumnya: ${existingTime}<br>
                        • Selisih Waktu: ${timeDiff} detik yang lalu<br>
                        • Tunggu: ${remainingTime} detik lagi
                      </p>
                    </div>
                  </div>
                </div>
                <div class="mt-4 text-sm text-gray-600">
                  <strong>Solusi:</strong><br>
                  ${remainingTime > 0 ? `1. Tunggu ${remainingTime} detik sebelum submit ulang<br>` : ''}
                  2. Refresh halaman untuk memulai ulang<br>
                  3. Periksa apakah data sudah tersimpan di halaman Good Receive<br>
                  4. Jika data sudah ada, gunakan fitur Edit untuk mengubah
                </div>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'OK, Saya Mengerti',
            cancelButtonText: 'Lihat Data Sebelumnya',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#3b82f6',
            width: '700px',
            customClass: {
              popup: 'swal-wide'
            }
          }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) {
              // User clicked "Lihat Data Sebelumnya"
              const existingId = duplicateInfo.existing_id;
              if (existingId) {
                window.location.href = `/outlet-food-good-receives/${existingId}`;
              } else {
                window.location.href = '/outlet-food-good-receives';
              }
            }
          });
          return; // Exit early for duplicate error
        }
        
        // Regular validation errors
        const errors = data.errors;
        if (errors) {
          const errorList = Object.values(errors).flat().join('\n• ');
          errorMessage = `Validasi gagal:\n• ${errorList}`;
        } else {
          errorMessage = data.message || 'Data tidak valid';
        }
        errorDetails = `Status: ${status}\nResponse: ${JSON.stringify(data, null, 2)}`;
        showDetails = true;
      } else if (status === 404) {
        errorMessage = 'Delivery Order tidak ditemukan';
        errorDetails = `Status: ${status}\nURL: ${e.config?.url}\nResponse: ${JSON.stringify(data, null, 2)}`;
        showDetails = true;
      } else if (status === 500) {
        errorMessage = 'Terjadi kesalahan server. Silakan hubungi administrator.';
        errorDetails = `Status: ${status}\nError: ${data?.message || 'Unknown server error'}\nFile: ${data?.file || 'Unknown'}\nLine: ${data?.line || 'Unknown'}\nTrace: ${data?.trace ? data.trace.slice(0, 3).join('\n') : 'No trace'}`;
        showDetails = true;
      } else {
        errorMessage = data?.message || `Error ${status}: ${data?.error || 'Unknown error'}`;
        errorDetails = `Status: ${status}\nResponse: ${JSON.stringify(data, null, 2)}`;
        showDetails = true;
      }
    } else if (e.request) {
      // Network error
      errorMessage = 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
      errorDetails = `Network Error\nRequest: ${JSON.stringify(e.request, null, 2)}`;
      showDetails = true;
    } else {
      // Other errors
      errorMessage = e.message || 'Terjadi kesalahan tidak diketahui';
      errorDetails = `Error: ${e.message}\nStack: ${e.stack}`;
      showDetails = true;
    }
    
    // Tampilkan error dengan detail jika diperlukan
    if (showDetails) {
      const result = await Swal.fire({
        icon: 'error',
        title: 'Error',
        html: `
          <div class="text-left">
            <div class="mb-3">
              <strong>Pesan Error:</strong><br>
              ${errorMessage.replace(/\n/g, '<br>')}
            </div>
            <details class="text-xs text-gray-600">
              <summary class="cursor-pointer hover:text-gray-800 mb-2">
                <strong>Detail Teknis (Klik untuk melihat)</strong>
              </summary>
              <pre class="bg-gray-100 p-2 rounded text-xs overflow-auto max-h-40">${errorDetails}</pre>
            </details>
          </div>
        `,
        confirmButtonText: 'OK',
        width: '600px',
        customClass: {
          popup: 'swal-wide'
        }
      });
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Error',
        html: errorMessage.replace(/\n/g, '<br>'),
        confirmButtonText: 'OK',
        width: '500px'
      });
    }
  } finally {
    loadingSubmit.value = false;
    showConfirmModal.value = false;
    
    // Re-enable all submit buttons
    const submitButtons = document.querySelectorAll('button[onclick*="submitGR"], button[class*="bg-blue-600"]');
    submitButtons.forEach(btn => {
      btn.disabled = false;
      btn.style.opacity = '1';
    });
  }
}

function selectReason(reason) {
  selectedReason.value = reason;
  showReasonModal.value = false;
}

async function openSpsModal(item) {
  if (!item.item_id) return;
  spsLoading.value = true;
  spsModal.value = true;
  try {
    const res = await axios.get(`/api/items/${item.item_id}/detail`);
    spsItem.value = res.data.item;
  } catch (e) {
    spsItem.value = { error: 'Gagal mengambil data item' };
  } finally {
    spsLoading.value = false;
  }
}

function closeSpsModal() {
  spsModal.value = false;
  spsItem.value = {};
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

/* Custom styling untuk SweetAlert detail error */
:global(.swal-wide) {
  max-width: 600px !important;
}

:global(.swal-wide details) {
  margin-top: 10px;
}

:global(.swal-wide summary) {
  padding: 5px;
  background-color: #f3f4f6;
  border-radius: 4px;
  cursor: pointer;
}

:global(.swal-wide summary:hover) {
  background-color: #e5e7eb;
}

:global(.swal-wide pre) {
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: 'Courier New', monospace;
  line-height: 1.4;
}
</style> 