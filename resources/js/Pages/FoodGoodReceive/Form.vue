<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 relative max-h-[90vh] overflow-y-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-truck"></i> Buat Good Receive
      </h2>
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
        <i class="fa-solid fa-xmark text-2xl"></i>
      </button>
      <div v-if="!poFetched">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Scan QR Code atau Input Nomor PO</label>
          <div class="flex gap-2 mb-2">
            <input v-model="poNumber" type="text" placeholder="Nomor PO" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg" />
            <button @click="fetchPO" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">Cari</button>
            <button @click="showScanner = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg" type="button">
              <i class="fa-solid fa-qrcode"></i>
            </button>
          </div>
          <div v-if="showScanner" class="mb-2">
            <div v-if="cameras.length > 1" class="mb-2">
              <select v-model="selectedCameraId" @change="restartScanner" class="border rounded px-2 py-1">
                <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label }}</option>
              </select>
            </div>
            <div id="qr-reader" style="width: 100%"></div>
            <button @click="closeScanner" class="mt-2 bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1 rounded">Tutup Scanner</button>
          </div>
          <div class="text-xs text-gray-400">Jika kamera tersedia, bisa gunakan scanner QR code (implementasi scanner bisa ditambah jika diinginkan).</div>
        </div>
      </div>
      <div v-else>
        <div class="mb-4">
          <div class="font-bold text-blue-700 mb-2">PO: {{ po.number }}</div>
          <div class="text-sm text-gray-600 mb-2">Supplier: {{ po.supplier_id }} | Tanggal: {{ po.date }}</div>
        </div>
        <form @submit.prevent="submit">
          <table class="min-w-full divide-y divide-gray-200 mb-4">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase">Barang</th>
                <th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase">Qty PO</th>
                <th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase">Qty Diterima</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in items" :key="item.id">
                <td class="px-2 py-1">{{ item.item_name }} <span class='text-xs text-gray-400'>({{ item.unit_name }})</span></td>
                <td class="px-2 py-1 text-right">{{ item.quantity }}</td>
                <td class="px-2 py-1 text-right">
                  <input v-model.number="item.qty_received" type="number" min="0" :max="item.quantity" class="w-20 px-2 py-1 border rounded" required />
                </td>
              </tr>
            </tbody>
          </table>
          <div class="flex justify-end gap-2 mt-4">
            <button type="button" @click="$emit('close')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">Batal</button>
            <button type="submit" :disabled="loading" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold flex items-center gap-2">
              <svg v-if="loading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ loading ? 'Menyimpan...' : 'Simpan' }}</span>
            </button>
          </div>
        </form>
      </div>
      <div v-if="error" class="text-red-500 mt-2">{{ error }}</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const emit = defineEmits(['close']);

const poNumber = ref('');
const po = ref(null);
const items = ref([]);
const poFetched = ref(false);
const error = ref('');
const showScanner = ref(false);
const cameras = ref([]);
const selectedCameraId = ref('');
const loading = ref(false);
let html5QrCode = null;

const fetchPO = async () => {
  error.value = '';
  try {
    const res = await axios.post(route('food-good-receive.fetch-po'), { po_number: poNumber.value });
    po.value = res.data.po;
    items.value = res.data.items.map(item => ({
      ...item,
      qty_received: item.quantity
    }));
    poFetched.value = true;
  } catch (e) {
    error.value = e.response?.data?.message || 'PO tidak ditemukan';
  }
};

const submit = async () => {
  error.value = '';
  // Validasi qty diterima harus antara 90% dan 110% qty PO
  for (const item of items.value) {
    const minQty = item.quantity * 0.9;
    const maxQty = item.quantity * 1.1;
    if (item.qty_received < minQty || item.qty_received > maxQty) {
      error.value = `Qty diterima untuk barang ${item.item_name} harus antara ${minQty.toFixed(2)} dan ${maxQty.toFixed(2)}`;
      return;
    }
  }
  loading.value = true;
  try {
    await axios.post(route('food-good-receive.store'), {
      po_id: po.value.id,
      supplier_id: po.value.supplier_id,
      receive_date: new Date().toISOString().slice(0, 10),
      items: items.value.map(item => ({
        po_item_id: item.id,
        item_id: item.item_id,
        qty_ordered: item.quantity,
        qty_received: item.qty_received,
        unit_id: item.unit_id || 1,
      })),
      notes: '',
    });
    loading.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Good Receive berhasil disimpan',
      timer: 1500,
      showConfirmButton: false
    });
    emit('close');
    window.location.reload();
  } catch (e) {
    loading.value = false;
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal simpan Good Receive',
    });
    error.value = e.response?.data?.message || 'Gagal simpan Good Receive';
  }
};

const closeScanner = () => {
  showScanner.value = false;
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear());
  }
};

watch(showScanner, async (val) => {
  if (val) {
    if (!window.Html5Qrcode) {
      const script = document.createElement('script');
      script.src = 'https://unpkg.com/html5-qrcode';
      script.onload = setupCameras;
      document.body.appendChild(script);
    } else {
      setupCameras();
    }
  }
});

async function setupCameras() {
  if (!window.Html5Qrcode) return;
  cameras.value = await window.Html5Qrcode.getCameras();
  selectedCameraId.value = cameras.value[0]?.id || '';
  startScanner();
}

function startScanner() {
  if (!window.Html5Qrcode || !selectedCameraId.value) return;
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear());
  }
  html5QrCode = new window.Html5Qrcode('qr-reader');
  html5QrCode.start(
    selectedCameraId.value,
    { fps: 10, qrbox: 200 },
    (decodedText) => {
      poNumber.value = decodedText;
      closeScanner();
      fetchPO();
    }
  );
}

function restartScanner() {
  startScanner();
}

onBeforeUnmount(() => {
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear());
  }
});
</script> 