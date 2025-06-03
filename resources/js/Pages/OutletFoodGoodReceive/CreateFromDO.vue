<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck text-blue-500"></i> Proses Good Receive
        </h1>
        <Link :href="route('outlet-food-good-receives.index')" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          Kembali
        </Link>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <h2 class="text-xl font-bold text-blue-700 mb-4">Informasi Delivery Order</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-sm text-gray-600">Nomor DO</p>
            <p class="font-semibold">{{ deliveryOrder.number }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Tanggal DO</p>
            <p class="font-semibold">{{ formatDate(deliveryOrder.date) }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Outlet</p>
            <p class="font-semibold">{{ deliveryOrder.outlet_name }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Status</p>
            <p class="font-semibold">{{ deliveryOrder.status }}</p>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <h2 class="text-xl font-bold text-blue-700 mb-4">Daftar Item</h2>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Nama Item</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty DO</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty Diterima</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in deliveryOrder.items" :key="item.id" class="hover:bg-blue-50">
                <td class="px-6 py-3">{{ idx+1 }}</td>
                <td class="px-6 py-3">{{ item.name }}</td>
                <td class="px-6 py-3">{{ item.qty }}</td>
                <td class="px-6 py-3">{{ item.received_qty || 0 }}</td>
                <td class="px-6 py-3">
                  <span :class="item.received_qty >= item.qty ? 'text-green-700 font-bold' : 'text-yellow-700 font-bold'">
                    {{ item.received_qty >= item.qty ? 'Selesai' : 'Belum Selesai' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
        <h2 class="text-xl font-bold text-blue-700 mb-4">Scan Item</h2>
        <div class="mb-4">
          <label class="block text-sm font-semibold mb-1">Barcode Item</label>
          <div class="flex gap-2">
            <input v-model="barcode" type="text" class="w-full border rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Scan barcode item..." @keyup.enter="scanItem" />
            <button @click="showCamera = true" class="bg-green-500 text-white px-3 py-2 rounded flex items-center gap-1">
              <i class="fa fa-camera"></i> Scan dari Kamera
            </button>
          </div>
        </div>
        <div v-if="scanError" class="text-red-600 font-semibold mb-2">{{ scanError }}</div>
        <div class="flex justify-end">
          <button @click="saveGR" :disabled="!isAllItemsReceived || loadingSave" class="px-4 py-2 rounded-md bg-blue-600 text-white font-semibold hover:bg-blue-700 flex items-center">
            <span v-if="loadingSave" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
            Simpan GR
          </button>
        </div>
      </div>
      <!-- Modal Kamera -->
      <div v-if="showCamera" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-lg p-6 relative w-full max-w-md">
          <div v-if="cameras.length > 1" class="mb-2">
            <select v-model="selectedCameraId" @change="switchCamera" class="border rounded px-2 py-1">
              <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label }}</option>
            </select>
          </div>
          <div id="qr-reader" style="width: 100%"></div>
          <button @click="closeCamera" class="mt-4 bg-gray-300 px-4 py-2 rounded">Tutup</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onBeforeUnmount, watch, nextTick } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
  deliveryOrder: {
    type: Object,
    default: () => ({
      items: []
    })
  }
});

const barcode = ref('');
const scanError = ref('');
const loadingSave = ref(false);
const showCamera = ref(false);
let html5QrCode = null;
const cameras = ref([]);
const selectedCameraId = ref('');

watch(showCamera, async (val) => {
  if (val) {
    await nextTick();
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
  try {
    const devices = await window.Html5Qrcode.getCameras();
    cameras.value = devices;
    // Default ke kamera belakang jika ada
    const backCam = devices.find(cam => cam.label.toLowerCase().includes('back') || cam.label.toLowerCase().includes('belakang'));
    selectedCameraId.value = backCam?.id || devices[0]?.id || '';
    startCamera();
  } catch (err) {
    scanError.value = 'Tidak dapat mengakses kamera';
  }
}

function startCamera() {
  if (!window.Html5Qrcode || !selectedCameraId.value) return;
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear());
  }
  html5QrCode = new window.Html5Qrcode('qr-reader');
  html5QrCode.start(
    selectedCameraId.value,
    { fps: 10, qrbox: 250 },
    (decodedText) => {
      barcode.value = decodedText;
      showCamera.value = false;
      html5QrCode.stop().then(() => html5QrCode.clear());
      scanItem();
    },
    (errorMessage) => {}
  );
}

function switchCamera() {
  startCamera();
}

function closeCamera() {
  showCamera.value = false;
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
  }
}

onBeforeUnmount(() => {
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
  }
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function scanItem() {
  scanError.value = '';
  if (!props.deliveryOrder?.items) {
    scanError.value = 'Data DO tidak valid.';
    return;
  }
  const item = props.deliveryOrder.items.find(i => i.barcode === barcode.value);
  if (!item) {
    scanError.value = 'Item tidak ditemukan dalam DO.';
    barcode.value = '';
    return;
  }
  if (item.received_qty >= item.qty) {
    scanError.value = 'Item sudah diterima semua.';
    barcode.value = '';
    return;
  }
  item.received_qty = (item.received_qty || 0) + 1;
  barcode.value = '';
}

const isAllItemsReceived = computed(() => {
  if (!props.deliveryOrder?.items?.length) return false;
  return props.deliveryOrder.items.every(item => (item.received_qty || 0) >= item.qty);
});

async function saveGR() {
  loadingSave.value = true;
  try {
    const res = await fetch('/api/outlet-food-good-receives', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        delivery_order_id: props.deliveryOrder.id,
        items: props.deliveryOrder.items.map(item => ({
          id: item.id,
          received_qty: item.received_qty || 0
        }))
      })
    });
    if (!res.ok) {
      scanError.value = 'Terjadi kesalahan server.';
      return;
    }
    router.visit(route('outlet-food-good-receives.index'));
  } catch (e) {
    scanError.value = 'Terjadi kesalahan server.';
  } finally {
    loadingSave.value = false;
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