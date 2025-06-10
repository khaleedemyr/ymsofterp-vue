<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2 text-blue-700">
        <i class="fa-solid fa-truck text-blue-500"></i> Good Receive Outlet - Create
      </h1>
      <!-- Step 1: Pilih DO -->
      <div class="mb-6">
        <label class="block text-xs font-bold text-gray-600 mb-1">Pilih Delivery Order</label>
        <input type="text" v-model="doSearch" @input="searchDO" placeholder="Cari nomor DO..." class="input input-bordered w-full" :disabled="selectedDO" />
        <ul v-if="showDOSuggestions && doSuggestions.length" class="border rounded bg-white mt-1 max-h-40 overflow-auto">
          <li v-for="doOpt in doSuggestions" :key="doOpt.id" @click="selectDO(doOpt)" class="px-3 py-2 hover:bg-blue-100 cursor-pointer">{{ doOpt.number }}</li>
        </ul>
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
import { ref, computed } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const doSearch = ref('')
const doSuggestions = ref([])
const showDOSuggestions = ref(false)
const selectedDO = ref(null)
const doDetail = ref(null)
const items = ref([])
const scanBarcode = ref('')
const showQtyModal = ref(false)
const inputQty = ref(0)
const pendingBarcode = ref('')
const goodReceiveId = ref(null)

function searchDO() {
  if (doSearch.value.length < 2) {
    doSuggestions.value = []
    showDOSuggestions.value = false
    return
  }
  axios.get('/outlet-food-good-receives/available-dos', { params: { q: doSearch.value } })
    .then(res => {
      doSuggestions.value = res.data
      showDOSuggestions.value = true
    })
}
function selectDO(doOpt) {
  selectedDO.value = doOpt
  doSearch.value = doOpt.number
  showDOSuggestions.value = false
  // Fetch detail DO
  axios.get(`/outlet-food-good-receives/do-detail/${doOpt.id}`)
    .then(res => {
      doDetail.value = res.data
      items.value = res.data.items
    })
}
function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}

function handleScan() {
  const barcode = scanBarcode.value.trim()
  const item = items.value.find(i => i.barcode === barcode)
  if (!item) {
    Swal.fire('Barcode tidak ditemukan di DO!', '', 'error')
    scanBarcode.value = ''
    return
  }
  if (item.unit === 'pcs') {
    postScan(barcode, 1)
  } else {
    // Kiloan, input qty
    showQtyModal.value = true
    pendingBarcode.value = barcode
  }
  scanBarcode.value = ''
}
function confirmQtyInput() {
  if (inputQty.value <= 0) {
    Swal.fire('Qty harus lebih dari 0', '', 'error')
    return
  }
  postScan(pendingBarcode.value, inputQty.value)
  showQtyModal.value = false
  inputQty.value = 0
  pendingBarcode.value = ''
}
async function postScan(barcode, qty) {
  try {
    await axios.post('/outlet-food-good-receives/scan', {
      good_receive_id: goodReceiveId.value,
      barcode,
      qty
    })
    // Refresh item list
    if (selectedDO.value) {
      const res = await axios.get(`/outlet-food-good-receives/do-detail/${selectedDO.value.id}`)
      items.value = res.data.items
    }
  } catch (e) {
    Swal.fire(e.response?.data?.message || 'Gagal scan', '', 'error')
  }
}
const allScanned = computed(() => {
  return items.value.length && items.value.every(i => Number(i.qty_scan) >= Number(i.qty_packing_list))
})
async function submitGR() {
  if (!selectedDO.value) return;
  try {
    const res = await axios.post(`/outlet-food-good-receives/${selectedDO.value.id}/submit`);
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