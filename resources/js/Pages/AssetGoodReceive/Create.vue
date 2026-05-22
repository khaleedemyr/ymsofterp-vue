<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-boxes-stacked text-blue-500"></i> Create Asset Good Receive
        </h1>
        <a
          href="/asset-good-receives"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition"
        >
          <i class="fa-solid fa-arrow-left mr-2"></i> Back to List
        </a>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Form -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Ownership & Location -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa-solid fa-store mr-2 text-blue-500"></i> Pemilik & Lokasi Simpan
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet Pemilik</label>
                <input
                  type="text"
                  :value="ownerOutletDisplay"
                  readonly
                  placeholder="Diisi otomatis dari PO"
                  class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700"
                />
                <p class="text-xs text-gray-500 mt-1">Sama dengan outlet pada baris PO</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet Lokasi Simpan</label>
                <select
                  v-model="form.outlet_id"
                  @change="onOutletChange"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option :value="null">-- Pilih Outlet Lokasi --</option>
                  <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                    {{ outlet.nama_outlet }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                <select
                  v-model="form.warehouse_outlet_id"
                  :disabled="!form.outlet_id || loadingWarehouses"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                >
                  <option :value="null">-- Select Warehouse --</option>
                  <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                    {{ wh.name }}
                  </option>
                </select>
                <p v-if="loadingWarehouses" class="text-xs text-blue-500 mt-1">
                  <i class="fa fa-spinner fa-spin mr-1"></i> Loading warehouses...
                </p>
              </div>
            </div>
          </div>

          <!-- PO Scanner / Input -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa-solid fa-qrcode mr-2 text-green-500"></i> Scan or Input PO Number
            </h2>

            <div class="flex gap-2 mb-3">
              <input
                v-model="poNumber"
                type="text"
                placeholder="Enter PO Number"
                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @keydown.enter.prevent="fetchPO"
              />
              <button
                @click="fetchPO"
                :disabled="!poNumber || loadingPO"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition disabled:opacity-50"
              >
                <i v-if="loadingPO" class="fa fa-spinner fa-spin mr-1"></i>
                <i v-else class="fa-solid fa-search mr-1"></i> Search
              </button>
              <button
                @click="toggleScanner"
                type="button"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition"
              >
                <i class="fa-solid fa-camera"></i>
              </button>
            </div>

            <!-- QR Scanner -->
            <div v-if="showScanner" class="mb-4">
              <div v-if="cameras.length > 1" class="mb-2">
                <select v-model="selectedCameraId" @change="restartScanner" class="w-full px-3 py-2 border rounded-lg text-sm">
                  <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label }}</option>
                </select>
              </div>
              <div id="qr-reader" class="w-full rounded-lg overflow-hidden"></div>
              <button @click="closeScanner" class="mt-2 bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1.5 rounded-lg text-sm">
                <i class="fa-solid fa-xmark mr-1"></i> Close Scanner
              </button>
            </div>

            <p v-if="poError" class="text-red-500 text-sm mt-2">
              <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ poError }}
            </p>
          </div>

          <!-- PO Info & Items -->
          <div v-if="poData" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa-solid fa-file-invoice mr-2 text-indigo-500"></i> PO Information
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 p-4 bg-blue-50 rounded-lg">
              <div>
                <span class="text-xs font-medium text-gray-500 uppercase">PO Number</span>
                <p class="text-sm font-semibold text-gray-900">{{ poData.number }}</p>
              </div>
              <div>
                <span class="text-xs font-medium text-gray-500 uppercase">Supplier</span>
                <p class="text-sm font-semibold text-gray-900">{{ poData.supplier_name }}</p>
              </div>
              <div>
                <span class="text-xs font-medium text-gray-500 uppercase">Date</span>
                <p class="text-sm font-semibold text-gray-900">{{ poData.date }}</p>
              </div>
              <div>
                <span class="text-xs font-medium text-gray-500 uppercase">Status</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  {{ poData.status }}
                </span>
              </div>
            </div>

            <!-- Items Table -->
            <div class="overflow-x-auto">
              <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Item Name</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase">Unit</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Qty Ordered</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Already Received</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Remaining</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Qty to Receive</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Price</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Total</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-800">{{ item.item_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-center">{{ item.unit }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ item.qty_ordered }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ item.qty_already_received }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ item.qty_remaining }}</td>
                    <td class="px-4 py-3 text-right">
                      <input
                        v-model.number="item.qty_received"
                        type="number"
                        min="0"
                        :max="item.qty_remaining"
                        step="0.01"
                        class="w-24 px-2 py-1.5 border border-gray-300 rounded-lg text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      />
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ formatCurrency(item.price) }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 text-right">
                      {{ formatCurrency(item.qty_received * item.price) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-gray-50">
                  <tr>
                    <td colspan="7" class="px-4 py-3 text-right text-sm font-bold text-gray-700">Grand Total</td>
                    <td class="px-4 py-3 text-right text-sm font-bold text-blue-700">{{ formatCurrency(grandTotal) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Notes -->
            <div class="mt-6">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <i class="fa-solid fa-comment mr-1"></i> Notes (Optional)
              </label>
              <textarea
                v-model="form.notes"
                rows="3"
                placeholder="Enter additional notes..."
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
              ></textarea>
            </div>

            <!-- Submit -->
            <div class="mt-6 flex justify-end gap-3">
              <a
                href="/asset-good-receives"
                class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition"
              >
                Cancel
              </a>
              <button
                @click="submitForm"
                :disabled="form.processing"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow-lg transition disabled:opacity-50 flex items-center gap-2"
              >
                <i v-if="form.processing" class="fa fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-check"></i>
                <span>{{ form.processing ? 'Saving...' : 'Save Good Receive' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Right: Receive Date -->
        <div class="space-y-6">
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa-solid fa-calendar mr-2 text-blue-500"></i> Receive Date
            </h2>
            <input
              v-model="form.receive_date"
              type="date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Instructions -->
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
            <h3 class="text-sm font-bold text-blue-800 mb-2">
              <i class="fa-solid fa-info-circle mr-1"></i> Instructions
            </h3>
            <ol class="text-xs text-blue-700 space-y-1.5 list-decimal list-inside">
              <li>Pilih lokasi outlet & gudang (gudang mengikuti outlet lokasi)</li>
              <li>Outlet pemilik terisi otomatis dari PO</li>
              <li>Scan QR code or manually input PO number</li>
              <li>Verify PO details and adjust received quantities</li>
              <li>Add notes if needed</li>
              <li>Click "Save Good Receive" to submit</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  user: Object,
  outlets: Array,
});

const form = useForm({
  po_id: null,
  owner_outlet_id: null,
  outlet_id: null,
  warehouse_outlet_id: null,
  receive_date: new Date().toISOString().split('T')[0],
  notes: '',
  items: [],
});

const poNumber = ref('');
const poData = ref(null);
const poError = ref('');
const loadingPO = ref(false);
const warehouses = ref([]);
const loadingWarehouses = ref(false);
const showScanner = ref(false);
const cameras = ref([]);
const selectedCameraId = ref('');
let html5QrCode = null;

const ownerOutletDisplay = computed(() => {
  if (!form.owner_outlet_id) return '';
  const outlet = props.outlets?.find(o => o.id_outlet == form.owner_outlet_id);
  return outlet?.nama_outlet || `Outlet ${form.owner_outlet_id}`;
});

const grandTotal = computed(() => {
  return form.items.reduce((sum, item) => sum + (item.qty_received * item.price), 0);
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount || 0);
};

async function onOutletChange() {
  form.warehouse_outlet_id = null;
  warehouses.value = [];
  if (form.outlet_id) {
    await fetchWarehouses(form.outlet_id);
  }
}

async function fetchWarehouses(outletId) {
  loadingWarehouses.value = true;
  try {
    const res = await axios.get('/api/warehouse-outlets', { params: { outlet_id: outletId } });
    warehouses.value = res.data.data || res.data || [];
  } catch (e) {
    warehouses.value = [];
  } finally {
    loadingWarehouses.value = false;
  }
}

async function fetchPO() {
  if (!poNumber.value) return;
  poError.value = '';
  loadingPO.value = true;
  try {
    const res = await axios.get('/api/asset-good-receives/fetch-po', { params: { number: poNumber.value } });
    poData.value = res.data.po;
    form.po_id = res.data.po.id;
    if (res.data.suggested_owner_outlet_id) {
      form.owner_outlet_id = res.data.suggested_owner_outlet_id;
    }
    form.items = res.data.items.map(item => ({
      po_item_id: item.id,
      item_id: item.resolved_item_id,
      item_name: item.item_name,
      unit: item.unit,
      unit_id: item.resolved_unit_id,
      qty_ordered: item.quantity,
      qty_already_received: item.qty_already_received || 0,
      qty_remaining: item.qty_remaining,
      qty_received: item.qty_remaining,
      price: item.price,
    }));
  } catch (e) {
    poError.value = e.response?.data?.message || 'PO not found or not eligible for receiving.';
    poData.value = null;
    form.po_id = null;
    form.items = [];
  } finally {
    loadingPO.value = false;
  }
}

function submitForm() {
  if (!form.owner_outlet_id) {
    Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Outlet pemilik belum terisi. Muat PO yang memiliki outlet pada baris item.' });
    return;
  }
  if (!form.outlet_id) {
    Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Pilih outlet lokasi simpan.' });
    return;
  }
  if (!form.po_id) {
    Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please scan or input a PO number.' });
    return;
  }

  const hasQty = form.items.some(item => item.qty_received > 0);
  if (!hasQty) {
    Swal.fire({ icon: 'error', title: 'Validation Error', text: 'At least one item must have quantity to receive.' });
    return;
  }

  form.post('/asset-good-receives', {
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Asset Good Receive created successfully.',
        timer: 2000,
        showConfirmButton: false,
      });
    },
    onError: (errors) => {
      const msg = Object.values(errors).flat().join('\n') || 'Failed to save Good Receive.';
      Swal.fire({ icon: 'error', title: 'Error', text: msg });
    },
  });
}

// QR Scanner
function toggleScanner() {
  if (showScanner.value) {
    closeScanner();
  } else {
    showScanner.value = true;
  }
}

function closeScanner() {
  showScanner.value = false;
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
  }
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
  cameras.value = await window.Html5Qrcode.getCameras();
  selectedCameraId.value = cameras.value[0]?.id || '';
  startScanner();
}

function startScanner() {
  if (!window.Html5Qrcode || !selectedCameraId.value) return;
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
  }
  html5QrCode = new window.Html5Qrcode('qr-reader');
  html5QrCode.start(
    selectedCameraId.value,
    { fps: 10, qrbox: { width: 250, height: 250 } },
    (decodedText) => {
      poNumber.value = decodedText;
      closeScanner();
      fetchPO();
    }
  ).catch(() => {});
}

function restartScanner() {
  startScanner();
}


onBeforeUnmount(() => {
  if (html5QrCode) {
    try { html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {}); } catch (e) {}
  }
});
</script>
