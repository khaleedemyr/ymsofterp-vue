<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 relative max-h-[90vh] overflow-y-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-truck"></i> Buat Good Receive Outlet
      </h2>
      <button @click="handleClose" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
        <i class="fa-solid fa-xmark text-2xl"></i>
      </button>
      <div v-if="!doFetched">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Input Nomor DO</label>
          <div class="flex gap-2 mb-2">
            <input v-model="doNumber" type="text" placeholder="Nomor DO" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg" />
            <button @click="fetchDO" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">Cari</button>
            <button @click="showScanner = true" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
              <i class="fa-solid fa-camera"></i>
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
        </div>
      </div>
      <div v-else>
        <div class="mb-4">
          <div class="font-bold text-blue-700 mb-2">DO: {{ deliveryOrder.number }}</div>
          <div class="text-sm text-gray-600 mb-2">Tanggal: {{ deliveryOrder.date }}</div>
        </div>
        <form @submit.prevent="submit">
          <table class="min-w-full divide-y divide-gray-200 mb-4">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase">Barang</th>
                <th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase">Qty DO</th>
                <th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase">Qty Diterima</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in items" :key="item.id">
                <td class="px-2 py-1">
                  {{ item.item_name }} <span class='text-xs text-gray-400'>({{ item.unit_name }})</span>
                  <button type="button" class="ml-1 px-2 py-1 rounded bg-yellow-100 text-yellow-800 text-xs font-semibold hover:bg-yellow-200 border border-yellow-300 flex items-center gap-1" @click="openSpsModal(item)" :disabled="!item.item_id">
                    <i class="fa fa-info-circle"></i> SPS
                  </button>
                </td>
                <td class="px-2 py-1 text-right">{{ item.quantity }}</td>
                <td class="px-2 py-1 text-right">
                  <input 
                    v-model.number="item.qty_received" 
                    type="number" 
                    min="0" 
                    :max="item.quantity" 
                    step="0.01"
                    class="w-20 px-2 py-1 border rounded" 
                    :class="{'border-red-500': item.qty_error}"
                    required 
                    @input="validateQty(item)"
                  />
                  <div v-if="item.qty_error" class="text-red-500 text-xs mt-1">{{ item.qty_error }}</div>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="flex justify-end gap-2 mt-4">
            <button type="button" @click="handleClose" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">Batal</button>
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
      <Modal :show="spsModal" @close="closeSpsModal">
        <div class="p-4 min-w-[320px] max-w-[90vw]">
          <div class="flex justify-between items-center mb-2">
            <h2 class="text-lg font-bold text-gray-700">Detail Item</h2>
            <button @click="closeSpsModal" class="text-gray-400 hover:text-gray-700"><i class="fa fa-times"></i></button>
          </div>
          <div v-if="spsLoading" class="text-center py-8"><i class="fa fa-spinner fa-spin text-blue-400 text-2xl"></i></div>
          <div v-else-if="spsItem && !spsItem.error">
            <div class="mb-2">
              <span class="font-semibold">Nama:</span> {{ spsItem.name }}
            </div>
            <div class="mb-2">
              <span class="font-semibold">Deskripsi:</span>
              <span v-if="spsItem.description">{{ spsItem.description }}</span>
              <span v-else class="italic text-gray-400">(Tidak ada deskripsi)</span>
            </div>
            <div class="mb-2">
              <span class="font-semibold">Spesifikasi:</span>
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
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import Modal from '@/Components/Modal.vue';

const emit = defineEmits(['close']);

const doNumber = ref('');
const deliveryOrder = ref(null);
const items = ref([]);
const doFetched = ref(false);
const error = ref('');
const loading = ref(false);
const showScanner = ref(false);
const cameras = ref([]);
const selectedCameraId = ref('');
let html5QrCode = null;
const spsModal = ref(false);
const spsItem = ref({});
const spsLoading = ref(false);

const fetchDO = async () => {
  error.value = '';
  try {
    const res = await axios.post(route('outlet-good-receive.fetch-do'), { do_number: doNumber.value });
    deliveryOrder.value = res.data.do;
    items.value = res.data.items.map(item => ({
      ...item,
      qty_received: item.quantity
    }));
    doFetched.value = true;
  } catch (e) {
    error.value = e.response?.data?.message || 'DO tidak ditemukan';
  }
};

const validateQty = (item) => {
  item.qty_error = '';
  if (item.qty_received === null || item.qty_received === undefined) {
    item.qty_error = 'Jumlah harus diisi';
    return false;
  }
  if (item.qty_received < 0) {
    item.qty_error = 'Jumlah tidak boleh negatif';
    return false;
  }
  if (item.qty_received > item.quantity) {
    item.qty_error = `Jumlah tidak boleh melebihi ${item.quantity}`;
    return false;
  }
  // Validate decimal places (max 2 decimal places)
  if (item.qty_received.toString().split('.')[1]?.length > 2) {
    item.qty_error = 'Maksimal 2 angka di belakang koma';
    return false;
  }
  return true;
};

const submit = async () => {
  error.value = '';
  loading.value = true;
  
  // Validate all items before submit
  const isValid = items.value.every(item => validateQty(item));
  if (!isValid) {
    loading.value = false;
    await Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      text: 'Mohon periksa kembali jumlah yang diterima',
    });
    return;
  }

  try {
    await axios.post(route('outlet-good-receive.store'), {
      do_id: deliveryOrder.value.id,
      receive_date: new Date().toISOString().slice(0, 10),
      items: items.value.map(item => ({
        do_item_id: item.id,
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
    handleClose();
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

function handleClose() {
  emit('close');
}

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
  
  try {
    const devices = await window.Html5Qrcode.getCameras();
    cameras.value = devices;
    
    // Find back camera
    const backCamera = devices.find(cam => 
      cam.label.toLowerCase().includes('back') || 
      cam.label.toLowerCase().includes('belakang')
    );
    
    selectedCameraId.value = backCamera?.id || devices[0]?.id || '';
    startScanner();
  } catch (err) {
    console.error('Error getting cameras:', err);
    error.value = 'Gagal mengakses kamera';
  }
}

function startScanner() {
  if (!window.Html5Qrcode || !selectedCameraId.value) return;
  
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear());
  }
  
  html5QrCode = new window.Html5Qrcode('qr-reader');
  html5QrCode.start(
    selectedCameraId.value,
    { 
      fps: 10, 
      qrbox: 200,
      aspectRatio: 1.0
    },
    (decodedText) => {
      doNumber.value = decodedText;
      closeScanner();
      fetchDO();
    },
    (errorMessage) => {
      // Ignore errors
    }
  );
}

function restartScanner() {
  startScanner();
}

function closeScanner() {
  showScanner.value = false;
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
  }
}

onBeforeUnmount(() => {
  if (html5QrCode) {
    try {
      html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
    } catch (e) {
      // ignore
    }
  }
});

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