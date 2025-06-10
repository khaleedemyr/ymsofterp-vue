<script setup>
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  floorOrders: Array,
  warehouseDivisions: Array,
});

const selectedFO = ref('');
const selectedDivision = ref('');
const items = ref([]);
const loadingItems = ref(false);
const error = ref('');
const showPrint = ref(false);
const showReasonModal = ref(false);
const showPreviewModal = ref(false);
const reason = ref('');
const reasonOptions = [
  'Stok di warehouse tidak cukup',
  'Barang rusak/cacat',
  'Barang sudah diganti item lain',
  'Permintaan outlet berubah',
  'Lainnya',
];
const reasonItemId = ref(null);
const itemStocks = ref({});
const isSubmitting = ref(false);

const foDetail = computed(() => props.floorOrders.find(f => f.id == selectedFO.value) || {});

const selectedDivisionName = computed(() => {
  const div = props.warehouseDivisions.find(d => d.id == selectedDivision.value);
  return div ? div.name : '-';
});

const filteredFOItems = computed(() => {
  if (!foDetail.value || !selectedDivision.value) return [];
  return (foDetail.value.items || []).filter(item => {
    // warehouse_division_id bisa di item.item atau item langsung
    const divId = item.item?.warehouse_division_id || item.warehouse_division_id;
    return String(divId) === String(selectedDivision.value);
  });
});

const itemsByCategory = computed(() => {
  const map = {};
  items.value.forEach(item => {
    const cat = item.item?.category?.name || 'Tanpa Kategori';
    if (!map[cat]) map[cat] = [];
    map[cat].push(item);
  });
  return map;
});

watch([selectedFO, selectedDivision], async ([fo, div]) => {
  if (fo && div) {
    loadingItems.value = true;
    error.value = '';
    try {
      const res = await axios.get('/api/packing-list/available-items', {
        params: { fo_id: fo, division_id: div }
      });
      items.value = (res.data.items || []).map(item => ({
        ...item,
        source: 'warehouse', // default
        checked: true,
        input_qty: null // input qty kosong saat load
      }));
      // Fetch stock
      const itemIds = items.value.map(i => i.item_id || i.item?.id).filter(Boolean);
      if (itemIds.length) {
        const stockRes = await axios.post('/api/packing-list/item-stocks', {
          warehouse_division_id: div,
          item_ids: itemIds
        });
        itemStocks.value = stockRes.data.stocks || {};
      } else {
        itemStocks.value = {};
      }
    } catch (e) {
      error.value = 'Gagal mengambil data item.';
      items.value = [];
      itemStocks.value = {};
    } finally {
      loadingItems.value = false;
    }
  } else {
    items.value = [];
    itemStocks.value = {};
  }
});

async function onSubmit() {
  if (!selectedFO.value || !selectedDivision.value || items.value.length === 0) return;
  // Tidak perlu lagi konfirmasi qty kurang, langsung preview
  showPreviewModal.value = true;
}

async function confirmSubmit() {
  // Loop untuk semua item checked yang butuh reason
  for (let i = 0; i < items.value.length; i++) {
    const item = items.value[i];
    const qtyOrder = Number(item.qty ?? item.qty_order);
    if (item.checked && Number(item.input_qty) < qtyOrder && (!item.reason || item.reason === '')) {
      reasonItemId.value = item.id;
      showReasonModal.value = true;
      // Tunggu user memilih reason
      await new Promise(resolve => {
        const unwatch = watch(showReasonModal, (val) => {
          if (!val) {
            unwatch();
            resolve();
          }
        });
      });
      // Setelah modal ditutup, cek lagi
      if (!item.reason || item.reason === '') {
        await Swal.fire({
          icon: 'warning',
          title: 'Alasan Wajib Diisi',
          text: 'Ada item dengan qty kurang dari permintaan FO, alasan harus diisi!',
        });
        return;
      }
    }
  }
  const data = {
    food_floor_order_id: selectedFO.value,
    warehouse_division_id: selectedDivision.value,
    items: items.value
      .filter(i => i.checked)
      .map(i => ({
        food_floor_order_item_id: i.id,
        qty: i.input_qty ?? 0,
        unit: i.unit,
        source: i.source,
        reason: i.reason || null
      }))
  };
  // Debug: cek reason sebelum kirim ke backend
  console.log('DATA TO BACKEND', JSON.parse(JSON.stringify(data)));
  showPreviewModal.value = false;
  isSubmitting.value = true;
  try {
    const res = await axios.post('/packing-list', data);
    isSubmitting.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Packing List berhasil dibuat!'
    });
    window.location.href = '/packing-list';
  } catch (e) {
    isSubmitting.value = false;
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e?.response?.data?.message || 'Gagal membuat Packing List.'
    });
  }
}

function onQtyInput(item, idx) {
  const qtyOrder = Number(item.qty ?? item.qty_order);
  if (item.input_qty !== null && Number(item.input_qty) < qtyOrder) {
    reasonItemId.value = item.id;
    showReasonModal.value = true;
  } else {
    item.reason = '';
  }
}

function selectReason(r) {
  if (reasonItemId.value !== null) {
    const found = items.value.find(i => i.id === reasonItemId.value);
    if (found) {
      found.reason = r;
      console.log('Reason set for id', reasonItemId.value, r, found);
    }
    reasonItemId.value = null;
  }
  showReasonModal.value = false;
}

function printFO() {
  const printContents = document.getElementById('print-area').innerHTML;
  const printWindow = window.open('', '', 'height=600,width=800');
  printWindow.document.write('<html><head><title>Print FO</title>');
  printWindow.document.write(
    '<style>' +
      'body{font-family:Arial,sans-serif;}' +
      'table{border-collapse:collapse;width:100%;}' +
      'th,td{border:1px solid #ddd;padding:8px;}' +
      '.print-header { text-align:center; }' +
      '.logo-print { max-width:180px; margin:0 auto 12px; display:block; }' +
      '@media print {' +
        '.print-header { display:block !important; }' +
        '.logo-print { display:block !important; }' +
      '}' +
    '</style>'
  );
  printWindow.document.write('</head><body>');
  printWindow.document.write(printContents);
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 500);
}
</script>
<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-box text-blue-500"></i> Buat Packing List
      </h1>
      <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Request Order (RO)</label>
          <select v-model="selectedFO" class="w-full rounded border-gray-300">
            <option value="">Pilih Request Order (RO)</option>
            <option v-for="fo in props.floorOrders" :key="fo.id" :value="fo.id">
              {{ fo.outlet?.nama_outlet }} - {{ fo.tanggal }} - {{ fo.fo_mode }} - {{ fo.order_number }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse Division</label>
          <select v-model="selectedDivision" class="w-full rounded border-gray-300">
            <option value="">Pilih Warehouse Division</option>
            <option v-for="div in props.warehouseDivisions" :key="div.id" :value="div.id">
              {{ div.name }}
            </option>
          </select>
        </div>
      </div>
      <div v-if="selectedFO">
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded mb-4">
          <div class="font-bold text-blue-800 mb-1">Detail Request Order (RO)</div>
          <div v-if="foDetail">
            <div><b>Outlet:</b> {{ foDetail.outlet?.nama_outlet }}</div>
            <div><b>Tanggal:</b> {{ foDetail.tanggal }}</div>
            <div><b>RO Mode:</b> {{ foDetail.fo_mode }}</div>
            <div><b>Nomor:</b> {{ foDetail.order_number }}</div>
            <div><b>Creator:</b> {{ foDetail.user?.nama_lengkap || '-' }}</div>
          </div>
        </div>
        <button @click="showPrint = true" :disabled="!selectedDivision" class="mb-4 px-4 py-2 rounded bg-blue-500 text-white font-semibold hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
          <i class="fas fa-print mr-2"></i> Print Preview Request Order (RO)
        </button>
      </div>
      <div v-if="!loadingItems && items.length === 0 && selectedFO && selectedDivision" class="text-center text-gray-500 my-8">
        Semua item di warehouse division ini sudah di-packing.
      </div>
      <div v-if="loadingItems" class="text-center py-8">
        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
        <p class="mt-2 text-gray-600">Memuat data item...</p>
      </div>
      <div v-if="error" class="text-red-600 mb-4">{{ error }}</div>
      <div v-if="items.length">
        <div v-for="(catItems, catName) in itemsByCategory" :key="catName" class="mb-6">
          <div class="font-bold text-blue-700 text-lg mb-2">{{ catName }}</div>
          <table class="w-full mb-2">
            <thead>
              <tr class="bg-blue-50">
                <th class="py-2 text-left">No</th>
                <th class="py-2 text-left">Pilih</th>
                <th class="py-2 text-left">Nama Item</th>
                <th class="py-2 text-left">Qty Order</th>
                <th class="py-2 text-left">Input Qty</th>
                <th class="py-2 text-left">Unit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in catItems" :key="item.id">
                <td>{{ idx + 1 }}</td>
                <td><input type="checkbox" v-model="item.checked" /></td>
                <td>{{ item.item?.name || item.item_name }}
                  <div class="text-xs text-gray-500 mt-1">
                    Stok: <span :class="{'text-red-600 font-bold': item.stock === 0}">{{ item.stock }}</span> {{ item.unit }}
                  </div>
                </td>
                <td>{{ item.qty ?? item.qty_order }}</td>
                <td class="flex items-center gap-2">
                  <input type="number" v-model.number="item.input_qty" min="0" :max="item.stock" step="0.01" class="w-20 rounded border-gray-300 text-right" :placeholder="'Qty'" @input="onQtyInput(item, idx)" />
                  <button type="button" @click="item.input_qty = item.qty ?? item.qty_order" class="px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200" title="Isi qty sesuai pesanan">=</button>
                </td>
                <td>{{ item.unit }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <button @click="onSubmit" :disabled="!selectedFO || !selectedDivision || !items.length || isSubmitting" class="px-6 py-2 rounded bg-blue-600 text-white font-bold text-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
        <i class="fas fa-save mr-2"></i> Submit
        <span v-if="isSubmitting" class="ml-2"><i class="fas fa-spinner fa-spin"></i></span>
      </button>
      <div v-if="showPrint" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative" style="max-height:80vh; overflow-y:auto;">
          <button @click="showPrint = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-lg"></i>
          </button>
          <div id="print-area">
            <div class="text-center mb-4 print-header">
              <img src="/images/logojustusgroup.png" alt="Justus Group" class="logo-print" style="max-width: 180px; margin: 0 auto 12px; display:block;" />
              <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 8px;">FLOOR ORDER</h2>
            </div>
            <div class="mb-4 text-sm">
              <div><b>Outlet:</b> {{ foDetail.outlet?.nama_outlet }}</div>
              <div><b>Tanggal:</b> {{ foDetail.tanggal }}</div>
              <div><b>RO Mode:</b> {{ foDetail.fo_mode }}</div>
              <div><b>Nomor:</b> {{ foDetail.order_number }}</div>
              <div><b>Creator:</b> {{ foDetail.user?.nama_lengkap || '-' }}</div>
              <div><b>Warehouse Division:</b> {{ selectedDivisionName }}</div>
            </div>
            <template v-if="selectedDivision">
              <div v-for="(catItems, catName) in itemsByCategory" :key="catName" class="mb-6">
                <div class="font-bold text-blue-700 text-sm mb-1">{{ catName }}</div>
                <table class="w-full text-xs border mb-2" style="border-collapse: collapse;">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="border px-2 py-1">No</th>
                      <th class="border px-2 py-1">Nama Item</th>
                      <th class="border px-2 py-1">Qty</th>
                      <th class="border px-2 py-1">Unit</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, idx) in catItems" :key="item.id">
                      <td class="border px-2 py-1">{{ idx + 1 }}</td>
                      <td class="border px-2 py-1">{{ item.item?.name || item.item_name }}</td>
                      <td class="border px-2 py-1">{{ item.input_qty ?? item.qty ?? item.qty_order }}</td>
                      <td class="border px-2 py-1">{{ item.unit }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
            <template v-else>
              <div class="text-center text-gray-500 py-8">Pilih Warehouse Division terlebih dahulu</div>
            </template>
          </div>
          <div class="flex justify-end gap-2 mt-4">
            <button @click="showPrint = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Tutup</button>
            <button @click="printFO" :disabled="!selectedDivision" class="px-4 py-2 text-white bg-blue-500 rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">Print</button>
          </div>
        </div>
      </div>
      <div v-if="showReasonModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <div class="font-bold text-lg mb-4 text-blue-700">Pilih Alasan Qty Kurang</div>
          <div class="grid gap-3 mb-4">
            <button v-for="r in reasonOptions" :key="r" @click="selectReason(r)" class="w-full px-4 py-2 rounded bg-blue-100 text-blue-800 font-semibold hover:bg-blue-200">{{ r }}</button>
          </div>
          <button @click="showReasonModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200 w-full">Batal</button>
        </div>
      </div>
      <!-- Preview Modal -->
      <div v-if="showPreviewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-4xl p-6 relative" style="max-height:90vh;">
          <button @click="showPreviewModal = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-lg"></i>
          </button>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Preview Packing List</h3>
          <div class="overflow-y-auto" style="max-height: calc(90vh - 180px);">
            <div class="mb-4 text-sm">
              <div><b>Outlet:</b> {{ foDetail.outlet?.nama_outlet }}</div>
              <div><b>Tanggal:</b> {{ foDetail.tanggal }}</div>
              <div><b>RO Mode:</b> {{ foDetail.fo_mode }}</div>
              <div><b>Nomor:</b> {{ foDetail.order_number }}</div>
              <div><b>Creator:</b> {{ foDetail.user?.nama_lengkap || '-' }}</div>
              <div><b>Warehouse Division:</b> {{ selectedDivisionName }}</div>
            </div>
            <div v-for="(catItems, catName) in itemsByCategory" :key="catName" class="mb-6">
              <div class="font-bold text-blue-700 text-lg mb-2">{{ catName }}</div>
              <table class="w-full mb-2">
                <thead>
                  <tr class="bg-blue-50">
                    <th class="py-2 px-3 text-left">No</th>
                    <th class="py-2 px-3 text-left">Nama Item</th>
                    <th class="py-2 px-3 text-left">Qty Order</th>
                    <th class="py-2 px-3 text-left">Qty Input</th>
                    <th class="py-2 px-3 text-left">Unit</th>
                    <th class="py-2 px-3 text-left">Alasan (jika ada)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in catItems.filter(i => i.checked)" :key="item.id" class="border-b">
                    <td class="py-2 px-3">{{ idx + 1 }}</td>
                    <td class="py-2 px-3">{{ item.item?.name || item.item_name }}</td>
                    <td class="py-2 px-3">{{ item.qty ?? item.qty_order }}</td>
                    <td class="py-2 px-3">{{ item.input_qty }}</td>
                    <td class="py-2 px-3">{{ item.unit }}</td>
                    <td class="py-2 px-3 text-red-600">{{ item.reason || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-4 pt-4 border-t">
            <button @click="showPreviewModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">
              <i class="fas fa-times mr-2"></i> Batal
            </button>
            <button @click="confirmSubmit" :disabled="isSubmitting" class="px-4 py-2 text-white bg-blue-500 rounded hover:bg-blue-600">
              <i class="fas fa-check mr-2"></i> Konfirmasi Submit
              <span v-if="isSubmitting" class="ml-2"><i class="fas fa-spinner fa-spin"></i></span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 