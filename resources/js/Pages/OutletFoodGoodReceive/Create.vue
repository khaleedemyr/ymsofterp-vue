<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2 text-blue-700">
        <i class="fa-solid fa-truck text-blue-500"></i> Good Receive Outlet - Create
      </h1>
      <!-- Step 1: Pilih DO -->
      <div class="mb-6">
        <label class="block text-xs font-bold text-gray-600 mb-1">Pilih Delivery Order</label>
        <select v-model="selectedDOId" @change="onDOChange" class="input input-bordered w-full">
          <option value="">Pilih Nomor DO...</option>
          <option v-for="doOpt in doOptions" :key="doOpt.id" :value="doOpt.id">{{ doOpt.number }}</option>
        </select>
      </div>
      <!-- Step 2: Card Info -->
      <div v-if="doDetail">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="bg-white rounded shadow p-4">
            <div class="font-bold mb-2">Floor Order</div>
            <div v-if="doDetail.do.floor_order_number">No: {{ doDetail.do.floor_order_number }}</div>
            <div v-if="doDetail.do.floor_order_date">Tanggal: {{ doDetail.do.floor_order_date }}</div>
            <div v-if="doDetail.do.floor_order_desc">Keterangan: {{ doDetail.do.floor_order_desc }}</div>
          </div>
          <div class="bg-white rounded shadow p-4">
            <div class="font-bold mb-2">Packing List</div>
            <div v-if="doDetail.do.packing_number">No: {{ doDetail.do.packing_number }}</div>
            <div v-if="doDetail.do.packing_reason">Alasan: {{ doDetail.do.packing_reason }}</div>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="bg-white rounded shadow p-4">
            <div class="font-bold mb-2">Delivery Order</div>
            <div>No: {{ doDetail.do.do_number }}</div>
            <div>Tanggal: {{ formatDate(doDetail.do.do_created_at) }}</div>
          </div>
        </div>
        <div class="bg-white rounded shadow p-4 mb-4">
          <div class="font-bold mb-2">List Item DO</div>
          <table class="min-w-full text-sm">
            <thead>
              <tr>
                <th class="text-left">Item</th>
                <th class="text-left">Satuan</th>
                <th class="text-right">Qty DO</th>
                <th class="text-right">Qty Scan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.delivery_order_item_id">
                <td>{{ item.item_name }}</td>
                <td>{{ item.unit }}</td>
                <td class="text-right">{{ item.qty_packing_list }}</td>
                <td class="text-right">{{ item.qty_scan }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Step 3: Scan -->
        <div class="mb-4">
          <label class="block text-xs font-bold text-gray-600 mb-1">Scan Barcode Item</label>
          <input type="text" v-model="scanBarcode" @keyup.enter="handleScan" class="input input-bordered w-full" placeholder="Scan barcode..." autofocus :disabled="!doDetail" />
        </div>
        <div v-if="showQtyModal" class="mb-4">
          <label>Input Berat (kg):</label>
          <input type="number" v-model.number="inputQty" min="0.01" step="0.01" class="input input-bordered w-32" />
          <button @click="confirmQtyInput" class="ml-2 btn btn-primary">OK</button>
        </div>
        <!-- Step 4: Submit -->
        <button class="btn bg-gradient-to-r from-blue-500 to-blue-700 text-white px-8 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all" :disabled="!allScanned" @click="submitGR">
          Submit Good Receive
        </button>
        <button v-if="goodReceive.status === 'done' && allScanned" @click="processStock">Proses ke Stok</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, nextTick, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const doSearch = ref('')
const doSuggestions = ref([])
const showDOSuggestions = ref(false)
const selectedDO = ref(null)
const doDetail = ref(null)
const items = ref([])
const scanBarcode = ref('')
const scanFeedback = ref('')
const scanFeedbackClass = ref('')
const showQtyModal = ref(false)
const inputQty = ref(0)
const pendingBarcode = ref('')
const goodReceiveId = ref(null)
const doOptions = ref([])
const selectedDOId = ref('')

onMounted(() => {
  // Ambil daftar DO untuk outlet ini
  axios.get('/outlet-food-good-receives/available-dos').then(res => {
    doOptions.value = res.data
  })
})

function onDOChange() {
  const doOpt = doOptions.value.find(opt => String(opt.id) === String(selectedDOId.value))
  if (!doOpt) return
  selectedDO.value = doOpt
  // Fetch detail DO
  axios.get(`/outlet-food-good-receives/do-detail/${doOpt.id}`)
    .then(res => {
      doDetail.value = res.data
      items.value = res.data.items.map(item => ({ ...item, qty_scan: 0, barcode: item.barcode || null }))
    })
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}

function handleScan() {
  const barcode = scanBarcode.value.trim()
  if (!barcode) return;
  const item = items.value.find(i => Array.isArray(i.barcodes) ? i.barcodes.includes(barcode) : i.barcode === barcode)
  if (!item) {
    scanFeedback.value = '❌ Barcode tidak ditemukan di DO!';
    scanFeedbackClass.value = 'text-red-600';
    scanBarcode.value = '';
    nextTick(() => document.querySelector('input[placeholder="Scan barcode..."]').focus());
    return;
  }
  if (item.unit === 'pcs') {
    item.qty_scan = (item.qty_scan || 0) + 1;
    scanFeedback.value = `✔️ ${item.item_name} (${item.qty_scan}/${item.qty_packing_list})`;
    scanFeedbackClass.value = Number(item.qty_scan).toFixed(2) === Number(item.qty_packing_list).toFixed(2) ? 'text-green-700' : (Number(item.qty_scan) > Number(item.qty_packing_list) ? 'text-red-700' : 'text-yellow-700');
  } else {
    // Kiloan, input qty
    showQtyModal.value = true;
    pendingBarcode.value = barcode;
    scanBarcode.value = '';
    nextTick(() => document.querySelector('input[type="number"]').focus());
    return;
  }
  scanBarcode.value = '';
  nextTick(() => document.querySelector('input[placeholder="Scan barcode..."]').focus());
}
function confirmQtyInput() {
  if (inputQty.value <= 0) {
    scanFeedback.value = '❌ Qty harus lebih dari 0';
    scanFeedbackClass.value = 'text-red-600';
    return;
  }
  const item = items.value.find(i => Array.isArray(i.barcodes) ? i.barcodes.includes(pendingBarcode.value) : i.barcode === pendingBarcode.value)
  if (item) {
    item.qty_scan = (item.qty_scan || 0) + Number(inputQty.value);
    scanFeedback.value = `✔️ ${item.item_name} (${item.qty_scan}/${item.qty_packing_list})`;
    scanFeedbackClass.value = Number(item.qty_scan).toFixed(2) === Number(item.qty_packing_list).toFixed(2) ? 'text-green-700' : (Number(item.qty_scan) > Number(item.qty_packing_list) ? 'text-red-700' : 'text-yellow-700');
  }
  showQtyModal.value = false;
  inputQty.value = 0;
  pendingBarcode.value = '';
  nextTick(() => document.querySelector('input[placeholder="Scan barcode..."]').focus());
}
const allScanned = computed(() => {
  return items.value.length && items.value.every(i => Number(i.qty_scan) >= Number(i.qty_packing_list))
})
async function submitGR() {
  if (!selectedDO.value) return;
  if (!allScanned.value) {
    Swal.fire('Semua item harus discan sesuai qty DO!', '', 'error');
    return;
  }
  try {
    // Kirim data qty_scan hasil scan ke backend
    const res = await axios.post(`/outlet-food-good-receives/${selectedDO.value.id}/submit`, {
      items: items.value.map(i => ({ id: i.id, qty_scan: i.qty_scan }))
    });
    if (res.data && res.data.success) {
      Swal.fire('Berhasil', 'Good Receive berhasil disubmit!', 'success');
      setTimeout(() => {
        window.location.href = route('outlet-food-good-receives.index');
      }, 1200);
    } else {
      throw new Error(res.data.message || 'Gagal submit');
    }
  } catch (e) {
    Swal.fire('Gagal', e.response?.data?.message || e.message || 'Gagal submit', 'error');
  }
}
async function processStock() {
  try {
    const res = await axios.post(`/outlet-food-good-receives/${goodReceiveId.value}/process-stock`);
    if (res.data && res.data.success) {
      Swal.fire('Berhasil', 'Stok outlet sudah diupdate!', 'success');
      setTimeout(() => window.location.reload(), 1200);
    } else {
      throw new Error(res.data.message || 'Gagal proses stok');
    }
  } catch (e) {
    Swal.fire('Gagal', e.response?.data?.message || e.message || 'Gagal proses stok', 'error');
  }
}
</script> 