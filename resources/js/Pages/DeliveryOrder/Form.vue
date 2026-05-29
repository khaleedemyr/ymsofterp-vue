<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6 flex flex-col items-center">
      <h1 class="text-3xl font-bold mb-8 text-blue-800 flex items-center gap-3">
        <i class="fa-solid fa-barcode text-blue-500"></i> Delivery Order (Scan Barang / Nomor Seri)
      </h1>
      <!-- Filter Warehouse Division -->
      <div class="mb-6 flex flex-col md:flex-row gap-4 items-center w-full max-w-xl">
        <label class="font-semibold text-lg">Warehouse Division</label>
        <select v-model="selectedWarehouseDivision" @change="onWarehouseDivisionChange" class="border-2 border-blue-400 rounded-lg px-3 py-2 w-full max-w-xs focus:ring-2 focus:ring-blue-500">
          <option value="">Semua Warehouse Division</option>
          <option v-for="division in warehouseDivisions" :key="division.id" :value="division.id">
            {{ division.name }}
          </option>
        </select>
      </div>
      
      <!-- Pilih Packing List atau RO Supplier GR -->
      <div class="mb-6 flex flex-col md:flex-row gap-4 items-center w-full max-w-xl">
        <label class="font-semibold text-lg">Pilih Sumber</label>
        <select v-model="selectedPackingListId" @change="onPackingListChange" class="border-2 border-blue-400 rounded-lg px-3 py-2 w-full max-w-xs focus:ring-2 focus:ring-blue-500" :disabled="!selectedWarehouseDivision">
          <option value="">Pilih Packing List atau RO Supplier GR...</option>
          <optgroup label="Packing List">
            <option v-for="pl in filteredPackingLists" :key="pl.id" :value="pl.id">
              {{ new Date(pl.created_at).toLocaleDateString('id-ID') }} - {{ pl.nama_outlet || '-' }} - {{ pl.division_name || '-' }} - {{ pl.packing_number }}
            </option>
          </optgroup>
          <optgroup label="RO Supplier GR">
            <option v-for="gr in filteredROSupplierGRs" :key="'gr_' + gr.gr_id" :value="'gr_' + gr.gr_id">
              {{ new Date(gr.created_at).toLocaleDateString('id-ID') }} - {{ gr.nama_outlet || '-' }} - {{ gr.division_name || '-' }} - {{ gr.packing_number }} ({{ gr.supplier_name }})
            </option>
          </optgroup>
        </select>
      </div>
      <!-- Card Info Packing List atau RO Supplier GR terpilih -->
      <div v-if="selectedPackingList" class="mb-6 w-full max-w-xl bg-blue-50 border-l-4 border-blue-400 p-4 rounded animate-fade-in">
        <div class="font-bold text-blue-800 mb-1">
          {{ isROSupplierGR ? 'Info RO Supplier GR' : 'Info Packing List' }}
        </div>
        <div><b>Outlet:</b> {{ selectedPackingList.nama_outlet || '-' }}</div>
        <div><b>Warehouse Outlet:</b> {{ selectedPackingList.warehouse_outlet_name || '-' }}</div>
        <div><b>Warehouse Division:</b> {{ selectedPackingList.division_name || '-' }}</div>
        <div><b>Warehouse:</b> {{ selectedPackingList.warehouse_name || '-' }}</div>
        <div><b>Tanggal Floor Order:</b> {{ selectedPackingList.floor_order_date ? new Date(selectedPackingList.floor_order_date).toLocaleDateString('id-ID') : '-' }}</div>
        <div><b>Nomor Floor Order:</b> {{ selectedPackingList.floor_order_number || '-' }}</div>
        <div v-if="isROSupplierGR">
          <div><b>Supplier:</b> {{ selectedPackingList.supplier_name || '-' }}</div>
          <div><b>Tanggal GR:</b> {{ selectedPackingList.created_at ? new Date(selectedPackingList.created_at).toLocaleDateString('id-ID') : '-' }}</div>
          <div><b>Nomor GR:</b> {{ selectedPackingList.packing_number }}</div>
          <div><b>User GR:</b> {{ selectedPackingList.creator_name || '-' }}</div>
        </div>
        <div v-else>
          <div><b>Tanggal Packing:</b> {{ selectedPackingList.created_at ? new Date(selectedPackingList.created_at).toLocaleDateString('id-ID') : '-' }}</div>
          <div><b>Nomor Packing:</b> {{ selectedPackingList.packing_number }}</div>
          <div><b>User Packing:</b> {{ selectedPackingList.creator_name || '-' }}</div>
        </div>
      </div>
      <!-- Loading Spinner -->
      <div v-if="isLoadingItems" class="mb-8 w-full flex justify-center items-center py-12">
        <div class="text-center">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
          <div class="text-lg font-semibold text-blue-600">Loading items...</div>
        </div>
      </div>
      
      <!-- Tabel Item Packing List -->
      <div v-if="packingListItems.length && !isLoadingItems" class="mb-8 w-full max-w-3xl animate-fade-in">
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
                <span v-if="itemHasSerials(item)" class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800">
                  <i class="fa-solid fa-hashtag mr-0.5"></i> SN: {{ getSerialQtySum(item) }}
                </span>
                <span v-if="Number(item.qty_scan_barcode) > 0" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">
                  <i class="fa-solid fa-barcode mr-0.5"></i> BC: {{ Number(item.qty_scan_barcode).toFixed(2) }}
                </span>
                <div class="text-xs text-gray-500 mt-1 space-y-1">
                  <div v-if="item.units && item.stock">
                    <div v-if="item.units.small_unit && item.stock.small !== undefined">
                      <span class="font-semibold">Small:</span> 
                      <span :class="{'text-red-600 font-bold': item.stock.small === 0}">{{ item.stock.small }}</span> {{ item.units.small_unit }}
                    </div>
                    <div v-if="item.units.medium_unit && item.stock.medium !== undefined">
                      <span class="font-semibold">Medium:</span> 
                      <span :class="{'text-red-600 font-bold': item.stock.medium === 0}">{{ item.stock.medium }}</span> {{ item.units.medium_unit }}
                    </div>
                    <div v-if="item.units.large_unit && item.stock.large !== undefined">
                      <span class="font-semibold">Large:</span> 
                      <span :class="{'text-red-600 font-bold': item.stock.large === 0}">{{ item.stock.large }}</span> {{ item.units.large_unit }}
                    </div>
                  </div>
                  <div v-else class="text-red-600 font-bold">
                    Stok: Tidak tersedia
                  </div>
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
      <!-- Unified scan (auto-detect barcode vs nomor seri) -->
      <div v-if="packingListItems.length && !isLoadingItems" class="mb-8 w-full max-w-xl flex flex-col items-center animate-fade-in">
        <label class="font-semibold text-lg mb-2">Scan Barcode / Nomor Seri</label>
        <p class="text-xs text-gray-500 mb-3 text-center">Sistem otomatis mengenali jenis sticker. Format qty opsional: <code class="bg-gray-100 px-1 rounded">KODE 2.5</code></p>
        <input
          ref="unifiedScanInput"
          v-model="unifiedScanVal"
          @keyup.enter="onUnifiedScan"
          :disabled="resolvingScan"
          class="border-2 border-indigo-400 rounded-lg px-4 py-3 w-full text-xl text-center focus:ring-2 focus:ring-indigo-500 shadow-lg"
          placeholder="Scan di sini..."
          autofocus
        />
        <div v-if="scanFeedback" :class="scanFeedbackClass" class="mt-4 font-bold text-xl min-h-[32px] text-center">{{ scanFeedback }}</div>

        <div v-if="hasScannedSerials" class="mt-6 w-full">
          <div class="text-sm font-bold text-purple-800 mb-2">Nomor Seri yang sudah di-scan:</div>
          <div v-for="item in packingListItems" :key="'serial-' + item.id">
            <div v-if="scannedSerials[item.item_id] && scannedSerials[item.item_id].length" class="mb-3 bg-purple-50 rounded-lg p-3 border border-purple-200">
              <div class="font-semibold text-purple-800 text-sm mb-1">{{ item.name }} ({{ scannedSerials[item.item_id].length }} seri)</div>
              <div class="flex flex-wrap gap-1">
                <span v-for="(sn, idx) in scannedSerials[item.item_id]" :key="sn.serial_number" class="inline-flex items-center bg-white border border-purple-300 rounded px-2 py-0.5 text-xs font-mono">
                  {{ sn.serial_number }}
                  <span v-if="sn.repack_unit_name" class="ml-1 text-purple-600 font-semibold">(+{{ sn.effective_qty }})</span>
                  <button @click="removeSerial(item, idx)" class="ml-1 text-red-500 hover:text-red-700 font-bold">&times;</button>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <button v-if="packingListItems.length && !isLoadingItems" @click="confirmSubmit" :disabled="!isReadyToSubmit || loadingSubmit" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-10 py-4 rounded-2xl font-extrabold text-2xl shadow-xl hover:scale-105 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
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
  roSupplierGRs: Array,
  warehouseDivisions: Array,
});
const packingLists = ref(props.packingLists || []);
const roSupplierGRs = ref(props.roSupplierGRs || []);
const warehouseDivisions = ref(props.warehouseDivisions || []);

// Debug logging
console.log('Packing Lists:', packingLists.value);
console.log('RO Supplier GRs:', roSupplierGRs.value);
console.log('Packing Lists length:', packingLists.value.length);
console.log('RO Supplier GRs length:', roSupplierGRs.value.length);
const packingListItems = reactive([]);
const selectedPackingListId = ref('');
const selectedWarehouseDivision = ref('');
const isLoadingItems = ref(false);
const barcodeInputVal = ref('');
const unifiedScanVal = ref('');
const resolvingScan = ref(false);
const scanFeedback = ref('');
const scanFeedbackClass = ref('');
const unifiedScanInput = ref(null);
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

const scannedSerials = reactive({});  // { item_id: [{ serial_number, effective_qty, ... }] }

const hasScannedSerials = computed(() => Object.values(scannedSerials).some(arr => arr && arr.length > 0));

function getSerialQtySum(item) {
  const itemId = item.item_id || item.id;
  const serials = scannedSerials[itemId] || [];
  return serials.reduce((sum, s) => sum + (Number(s.effective_qty) || 1), 0);
}

function syncItemQtyScan(item) {
  item.qty_scan_barcode = Number(item.qty_scan_barcode) || 0;
  item.qty_scan = item.qty_scan_barcode + getSerialQtySum(item);
}

function itemHasSerials(item) {
  return getSerialQtySum(item) > 0;
}

function getMaxBarcodeQty(item) {
  const target = Number(item.qty) || 0;
  return Math.max(0, target - getSerialQtySum(item));
}

function isItemComplete(item) {
  const target = Number(item.qty) || 0;
  const total = Number(item.qty_scan) || 0;
  if (total <= 0) return false;
  if (Math.abs(total - target) < 0.001) return true;
  if (total < target) return !!item.reason;
  return false;
}

const isReadyToSubmit = computed(() => {
  if (!packingListItems.length) return false;
  return packingListItems.every(isItemComplete);
});

function computeScanMode() {
  const hasSerial = hasScannedSerials.value;
  const hasBarcodeQty = packingListItems.some((item) => Number(item.qty_scan_barcode) > 0);
  if (hasSerial && hasBarcodeQty) return 'mixed';
  if (hasSerial) return 'serial';
  return 'barcode';
}
const isROSupplierGR = computed(() => {
  const value = selectedPackingListId.value;
  return value && typeof value === 'string' && value.startsWith('gr_');
});

// Filter packing lists berdasarkan warehouse division
const filteredPackingLists = computed(() => {
  if (!selectedWarehouseDivision.value) return [];
  return packingLists.value.filter(pl => pl.warehouse_division_id == selectedWarehouseDivision.value);
});

// Filter RO Supplier GR berdasarkan warehouse division
const filteredROSupplierGRs = computed(() => {
  if (!selectedWarehouseDivision.value) return [];
  return roSupplierGRs.value.filter(gr => gr.warehouse_division_id == selectedWarehouseDivision.value);
});
const selectedPackingList = computed(() => {
  const value = selectedPackingListId.value;
  if (!value) return null;
  
  if (isROSupplierGR.value) {
    const grId = value.substring(3);
    return roSupplierGRs.value.find(gr => gr.gr_id == grId) || null;
  } else {
    return packingLists.value.find(pl => pl.id == value) || null;
  }
});

function onWarehouseDivisionChange() {
  console.log('=== onWarehouseDivisionChange START ===');
  console.log('selectedWarehouseDivision.value:', selectedWarehouseDivision.value);
  
  // Reset selected packing list
  selectedPackingListId.value = '';
  packingListItems.splice(0, packingListItems.length);
  Object.keys(scannedSerials).forEach((k) => delete scannedSerials[k]);
  
  console.log('=== onWarehouseDivisionChange END ===');
}

async function onPackingListChange() {
  console.log('=== onPackingListChange START ===');
  const value = selectedPackingListId.value;
  console.log('selectedPackingListId.value:', value, 'type:', typeof value);
  
  if (!value) {
    console.log('Early return - no value');
    return;
  }
  
  // Set loading state
  isLoadingItems.value = true;
  packingListItems.splice(0, packingListItems.length);
  Object.keys(scannedSerials).forEach((k) => delete scannedSerials[k]);
  
  console.log('Calling API for:', value);
  
  try {
    // Gunakan endpoint yang sama karena sudah dimodifikasi untuk menangani RO Supplier GR
    const res = await axios.get(`/api/packing-list/${value}/items`);
    console.log('API Response:', res.data);
    
    packingListItems.splice(0, packingListItems.length, ...res.data.items.map(item => ({
      ...item,
      qty_scan: 0,
      qty_scan_barcode: 0,
    })));
    
    console.log('Packing list items after update:', packingListItems);
  } catch (error) {
    console.error('API Error:', error);
  } finally {
    // Clear loading state
    isLoadingItems.value = false;
  }
  
  unifiedScanVal.value = '';
  barcodeInputVal.value = '';
  scanFeedback.value = '';
  nextTick(() => unifiedScanInput.value?.focus());
  console.log('=== onPackingListChange END ===');
}

function applySerialScan(serialNumber, serial) {
  const matchedItem = packingListItems.find((i) => (i.item_id || i.id) == serial.item_id);
  if (!matchedItem) {
    scanFeedback.value = '❌ Item tidak ditemukan di Packing List!';
    scanFeedbackClass.value = 'text-red-600';
    return;
  }
  const effectiveQty = Number(serial.effective_qty) || 1;
  const remainingSerial = Number(matchedItem.qty) - (Number(matchedItem.qty_scan_barcode) || 0) - getSerialQtySum(matchedItem);
  if (effectiveQty > remainingSerial + 0.001) {
    scanFeedback.value = `❌ Qty serial melebihi sisa (sisa: ${remainingSerial.toFixed(2)} ${matchedItem.unit})`;
    scanFeedbackClass.value = 'text-red-600';
    return;
  }
  if (!scannedSerials[serial.item_id]) {
    scannedSerials[serial.item_id] = [];
  }
  scannedSerials[serial.item_id].push({
    serial_number: serialNumber,
    effective_qty: effectiveQty,
    repack_unit_name: serial.repack_unit_name || null,
    repack_qty: serial.repack_qty || null,
    unit_name: serial.unit_name || null,
  });
  syncItemQtyScan(matchedItem);
  const convLabel = serial.repack_unit_name
    ? ` (1 ${serial.repack_unit_name} = ${parseFloat(Number(serial.repack_qty).toFixed(4))} ${serial.unit_name})`
    : '';
  scanFeedback.value = `✅ [Seri] ${serial.item_name} - ${serialNumber}${convLabel} (+${effectiveQty})`;
  scanFeedbackClass.value = 'text-green-600';
}

async function onUnifiedScan() {
  const raw = unifiedScanVal.value.trim();
  if (!raw || resolvingScan.value) return;

  let code = raw;
  let presetQty = null;
  const match = raw.match(/^([\S]+)\s+(\d+(?:\.\d+)?)$/);
  if (match) {
    code = match[1];
    presetQty = parseFloat(match[2]);
  }

  for (const [, serials] of Object.entries(scannedSerials)) {
    if (serials.some((s) => s.serial_number === code)) {
      scanFeedback.value = '❌ Nomor seri sudah di-scan sebelumnya!';
      scanFeedbackClass.value = 'text-red-600';
      unifiedScanVal.value = '';
      nextTick(() => unifiedScanInput.value?.focus());
      return;
    }
  }

  const pl = selectedPackingList.value;
  const warehouseId = pl?.warehouse_id || 1;
  const itemIds = packingListItems.map((i) => i.item_id || i.id);

  resolvingScan.value = true;
  scanFeedback.value = '';
  try {
    const res = await axios.post('/api/delivery-order/resolve-scan', {
      code,
      packing_list_id: selectedPackingListId.value,
      warehouse_id: warehouseId,
      item_ids: itemIds,
    });

    if (res.data.type === 'serial') {
      if (!res.data.valid) {
        scanFeedback.value = `❌ ${res.data.message}`;
        scanFeedbackClass.value = 'text-red-600';
      } else {
        applySerialScan(code, res.data.serial);
      }
    } else if (res.data.type === 'barcode') {
      barcodeInputVal.value = code;
      onScanBarcode(code, presetQty);
    } else {
      scanFeedback.value = `❌ ${res.data.message || 'Kode tidak dikenali.'}`;
      scanFeedbackClass.value = 'text-red-600';
    }
  } catch (_e) {
    scanFeedback.value = '❌ Gagal memproses scan (server error).';
    scanFeedbackClass.value = 'text-red-600';
  } finally {
    resolvingScan.value = false;
    unifiedScanVal.value = '';
    nextTick(() => unifiedScanInput.value?.focus());
  }
}

function onScanBarcode(overrideCode = null, overrideQty = undefined) {
  const input = (overrideCode ?? barcodeInputVal.value).trim();
  if (!input) return;
  let code = input;
  let qty = overrideQty !== undefined ? overrideQty : null;
  if (overrideQty === undefined) {
    const match = input.match(/^([\S]+)\s+(\d+(?:\.\d+)?)$/);
    if (match) {
      code = match[1];
      qty = parseFloat(match[2]);
    }
  }
  // Cari item yang barcodes-nya mengandung code
  const item = packingListItems.find(i => Array.isArray(i.barcodes) ? i.barcodes.includes(code) : i.barcode === code);
  if (item) {
    const maxBarcode = getMaxBarcodeQty(item);
    const currentBarcode = Number(item.qty_scan_barcode || 0);
    
    // Cari stock yang sesuai dengan unit item dengan unit conversion yang proper
    let stock = 0;
    let stockUnit = '';
    if (item.stock && item.units) {
      // Debug logging untuk troubleshooting
      console.log('=== STOCK DEBUG ===');
      console.log('Item:', item.name);
      console.log('Item unit:', item.unit);
      console.log('Available units:', item.units);
      console.log('Stock data:', item.stock);
      console.log('Conversion factors:', {
        small_conversion_qty: item.small_conversion_qty,
        medium_conversion_qty: item.medium_conversion_qty
      });
      
      if (item.unit === item.units.small_unit) {
        stock = Number(item.stock.small ?? 0);
        stockUnit = item.units.small_unit;
        console.log('Using small unit stock:', stock, stockUnit);
      } else if (item.unit === item.units.medium_unit) {
        stock = Number(item.stock.medium ?? 0);
        stockUnit = item.units.medium_unit;
        console.log('Using medium unit stock:', stock, stockUnit);
      } else if (item.unit === item.units.large_unit) {
        stock = Number(item.stock.large ?? 0);
        stockUnit = item.units.large_unit;
        console.log('Using large unit stock:', stock, stockUnit);
      } else {
        // Unit conversion: convert semua stock ke unit yang diminta
        const smallStock = Number(item.stock.small ?? 0);
        const mediumStock = Number(item.stock.medium ?? 0);
        const largeStock = Number(item.stock.large ?? 0);
        
        console.log('Raw stock values:', { smallStock, mediumStock, largeStock });
        
        // Convert semua ke small unit dulu, lalu ke unit yang diminta
        const totalSmall = smallStock + (mediumStock * (item.small_conversion_qty || 1)) + (largeStock * (item.small_conversion_qty || 1) * (item.medium_conversion_qty || 1));
        
        console.log('Total small unit stock:', totalSmall);
        
        // Convert dari small ke unit yang diminta
        if (item.unit === item.units.small_unit) {
          stock = totalSmall;
          stockUnit = item.units.small_unit;
        } else if (item.unit === item.units.medium_unit) {
          stock = totalSmall / (item.small_conversion_qty || 1);
          stockUnit = item.units.medium_unit;
        } else if (item.unit === item.units.large_unit) {
          stock = totalSmall / ((item.small_conversion_qty || 1) * (item.medium_conversion_qty || 1));
          stockUnit = item.units.large_unit;
        } else {
          // Fallback: gunakan stock yang paling besar
          stock = Math.max(smallStock, mediumStock, largeStock);
          stockUnit = item.unit;
        }
        
        console.log('Final calculated stock:', stock, stockUnit);
      }
    }
    
    console.log('=== FINAL STOCK VALIDATION ===');
    console.log('Available stock:', stock, stockUnit);
    console.log('Requested qty:', qty);
    console.log('Current barcode:', currentBarcode);
    console.log('Total needed:', currentBarcode + qty);
    console.log('Stock >= needed?', stock >= (currentBarcode + qty));
    
    // Validasi stock tersedia
    if (stock <= 0) {
      scanFeedback.value = `❌ Stok tidak tersedia (${stock} ${stockUnit})`;
      scanFeedbackClass.value = 'text-red-600';
      barcodeInputVal.value = '';
      nextTick(() => unifiedScanInput.value?.focus());
      return;
    }
    
    // Validasi qty scan tidak melebihi stock yang tersedia
    if (currentBarcode + qty > stock) {
      scanFeedback.value = `❌ Qty scan tidak boleh melebihi stock (${stock} ${stockUnit})`;
      scanFeedbackClass.value = 'text-red-600';
      barcodeInputVal.value = '';
      nextTick(() => unifiedScanInput.value?.focus());
      return;
    }
    
    // Selalu tampilkan modal input qty jika scan tanpa qty (qty === null, artinya user tidak input qty di barcode)
    if (qty === null) {
      qtyModalItem.value = item;
      const remainingQty = maxBarcode - currentBarcode;
      qtyModalValue.value = Math.max(0.01, remainingQty);
      showQtyModal.value = true;
      barcodeInputVal.value = '';
      nextTick(() => {
        const el = document.getElementById('qty-modal-input');
        if (el) el.focus();
      });
      return;
    }
    
    // Jika qty sudah diinput di barcode, langsung proses
    if (qty !== null) {
      if (currentBarcode + qty > maxBarcode) {
        scanFeedback.value = `❌ Qty barcode melebihi sisa (max barcode: ${maxBarcode}, sisa: ${(maxBarcode - currentBarcode).toFixed(2)})`;
        scanFeedbackClass.value = 'text-red-600';
        barcodeInputVal.value = '';
        nextTick(() => unifiedScanInput.value?.focus());
        return;
      }
      
      if (currentBarcode + qty > stock) {
        scanFeedback.value = `❌ Qty scan tidak boleh melebihi stock (${stock} ${stockUnit})`;
        scanFeedbackClass.value = 'text-red-600';
        barcodeInputVal.value = '';
        nextTick(() => unifiedScanInput.value?.focus());
        return;
      }
      
      item.qty_scan_barcode = currentBarcode + qty;
      syncItemQtyScan(item);
      scanFeedback.value = `✅ [Barcode] +${qty} ${item.unit} (BC: ${item.qty_scan_barcode}/${maxBarcode})`;
      scanFeedbackClass.value = 'text-green-600';
      barcodeInputVal.value = '';
      if (!overrideCode) nextTick(() => unifiedScanInput.value?.focus());
      return;
    }
  } else {
    scanFeedback.value = '❌ Barcode tidak ditemukan di Packing List!';
    scanFeedbackClass.value = 'text-red-600';
  }
  barcodeInputVal.value = '';
  if (!overrideCode) nextTick(() => unifiedScanInput.value?.focus());
}

function removeSerial(item, idx) {
  const itemId = item.item_id || item.id;
  if (scannedSerials[itemId] && scannedSerials[itemId].length > idx) {
    scannedSerials[itemId].splice(idx, 1);
    syncItemQtyScan(item);
  }
}

function confirmQtyModal() {
  const item = qtyModalItem.value;
  const maxBarcode = getMaxBarcodeQty(item);
  const currentBarcode = Number(item.qty_scan_barcode || 0);
  const inputQty = Number(qtyModalValue.value);
  
  // Cari stock yang sesuai dengan unit item dengan unit conversion yang proper
  let stock = 0;
  let stockUnit = '';
  if (item.stock && item.units) {
    // Debug logging untuk troubleshooting
    console.log('=== QTY MODAL STOCK DEBUG ===');
    console.log('Item:', item.name);
    console.log('Item unit:', item.unit);
    console.log('Available units:', item.units);
    console.log('Stock data:', item.stock);
    console.log('Conversion factors:', {
      small_conversion_qty: item.small_conversion_qty,
      medium_conversion_qty: item.medium_conversion_qty
    });
    
    if (item.unit === item.units.small_unit) {
      stock = Number(item.stock.small ?? 0);
      stockUnit = item.units.small_unit;
      console.log('Using small unit stock:', stock, stockUnit);
    } else if (item.unit === item.units.medium_unit) {
      stock = Number(item.stock.medium ?? 0);
      stockUnit = item.units.medium_unit;
      console.log('Using medium unit stock:', stock, stockUnit);
    } else if (item.unit === item.units.large_unit) {
      stock = Number(item.stock.large ?? 0);
      stockUnit = item.units.large_unit;
      console.log('Using large unit stock:', stock, stockUnit);
    } else {
      // Unit conversion: convert semua stock ke unit yang diminta
      const smallStock = Number(item.stock.small ?? 0);
      const mediumStock = Number(item.stock.medium ?? 0);
      const largeStock = Number(item.stock.large ?? 0);
      
      console.log('Raw stock values:', { smallStock, mediumStock, largeStock });
      
      // Convert semua ke small unit dulu, lalu ke unit yang diminta
      const totalSmall = smallStock + (mediumStock * (item.small_conversion_qty || 1)) + (largeStock * (item.small_conversion_qty || 1) * (item.medium_conversion_qty || 1));
      
      console.log('Total small unit stock:', totalSmall);
      
      // Convert dari small ke unit yang diminta
      if (item.unit === item.units.small_unit) {
        stock = totalSmall;
        stockUnit = item.units.small_unit;
      } else if (item.unit === item.units.medium_unit) {
        stock = totalSmall / (item.small_conversion_qty || 1);
        stockUnit = item.units.medium_unit;
      } else if (item.unit === item.units.large_unit) {
        stock = totalSmall / ((item.small_conversion_qty || 1) * (item.medium_conversion_qty || 1));
        stockUnit = item.units.large_unit;
      } else {
        // Fallback: gunakan stock yang paling besar
        stock = Math.max(smallStock, mediumStock, largeStock);
        stockUnit = item.unit;
      }
      
      console.log('Final calculated stock:', stock, stockUnit);
    }
  }
  
  console.log('=== QTY MODAL FINAL VALIDATION ===');
  console.log('Available stock:', stock, stockUnit);
  console.log('Input qty:', inputQty);
  console.log('Current barcode:', currentBarcode);
  console.log('Total needed:', currentBarcode + inputQty);
  console.log('Stock >= needed?', stock >= (currentBarcode + inputQty));
  
  // Validasi stock tersedia
  if (stock <= 0) {
    scanFeedback.value = `❌ Stok tidak tersedia (${stock} ${stockUnit})`;
    scanFeedbackClass.value = 'text-red-600';
    nextTick(() => document.getElementById('qty-modal-input')?.focus());
    return;
  }
  
  // Validasi qty scan tidak melebihi stock yang tersedia
  if (currentBarcode + inputQty > stock) {
    const maxAllowed = stock - currentBarcode;
    qtyModalValue.value = Math.max(0.01, maxAllowed);
    scanFeedback.value = `❌ Qty scan tidak boleh melebihi stock (sisa: ${maxAllowed} ${stockUnit})`;
    scanFeedbackClass.value = 'text-red-600';
    nextTick(() => document.getElementById('qty-modal-input')?.focus());
    return;
  }
  
  if (currentBarcode + inputQty > maxBarcode) {
    const maxAllowed = maxBarcode - currentBarcode;
    qtyModalValue.value = Math.max(0.01, maxAllowed);
    scanFeedback.value = `❌ Qty barcode melebihi sisa (max: ${maxBarcode}, sisa: ${maxAllowed.toFixed(2)})`;
    scanFeedbackClass.value = 'text-red-600';
    nextTick(() => document.getElementById('qty-modal-input')?.focus());
    return;
  }
  const targetTotal = Number(item.qty);
  if (inputQty + getSerialQtySum(item) < targetTotal) {
    showQtyModal.value = false;
    showReasonModal.value = true;
    selectedReason.value = '';
    return;
  }
  item.qty_scan_barcode = currentBarcode + inputQty;
  item.reason = '';
  syncItemQtyScan(item);
  showQtyModal.value = false;
  scanFeedback.value = `✔️ ${item.name} (BC: ${item.qty_scan_barcode}, SN: ${getSerialQtySum(item)}, total: ${item.qty_scan}/${item.qty})`;
  scanFeedbackClass.value = 'text-green-700';
  nextTick(() => unifiedScanInput.value?.focus());
}

function handleQtyModalKey(e) {
  if (e.key === 'Enter') confirmQtyModal();
}

function selectReason(reason) {
  const item = qtyModalItem.value;
  const addQty = Number(qtyModalValue.value);
  item.qty_scan_barcode = (Number(item.qty_scan_barcode) || 0) + addQty;
  item.reason = reason;
  syncItemQtyScan(item);
  showReasonModal.value = false;
  scanFeedback.value = `✔️ ${item.name} (total: ${item.qty_scan}/${item.qty}) - ${reason}`;
  scanFeedbackClass.value = 'text-yellow-700';
  nextTick(() => unifiedScanInput.value?.focus());
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
    // Build scanned_serials payload for serial mode
    const scannedSerialsPayload = Object.entries(scannedSerials)
      .filter(([, serials]) => serials && serials.length > 0)
      .map(([itemId, serials]) => ({ item_id: parseInt(itemId), serial_numbers: serials.map((s) => s.serial_number) }));

    const pl = selectedPackingList.value;
    const doRes = await axios.post('/delivery-order', {
      packing_list_id: selectedPackingListId.value,
      scan_mode: computeScanMode(),
      outlet_id: pl?.outlet_id || null,
      warehouse_outlet_id: pl?.warehouse_outlet_id || null,
      scanned_serials: scannedSerialsPayload,
      items: packingListItems.map(item => ({
        id: item.id,
        barcode: Array.isArray(item.barcode) && item.barcode.length > 0 ? item.barcode : (item.barcode ? [item.barcode] : []),
        qty: item.qty,
        qty_scan: item.qty_scan,
        qty_scan_barcode: Number(item.qty_scan_barcode) || 0,
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