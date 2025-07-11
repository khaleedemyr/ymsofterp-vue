<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-8 relative animate-fadeIn overflow-y-auto print-modal" style="max-height: 90vh;">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <button @click="printModal" class="absolute top-4 right-16 text-gray-400 hover:text-blue-600 text-2xl font-bold" title="Print PDF">
        <i class="fa-solid fa-print"></i>
      </button>
      <div class="text-center mb-4">
        <div class="text-xl font-bold text-gray-800">Revenue Report</div>
        <div class="text-xs text-gray-400 mt-1">{{ tanggal }}</div>
      </div>
      <div class="border-b border-gray-200 mb-4"></div>
      <div class="mb-6">
        <div class="font-bold text-blue-700 mb-2">Total Sales</div>
        <div class="text-3xl font-extrabold text-blue-800 mb-4">{{ formatCurrency(totalSales) }}</div>
      </div>
      <div>
        <div class="font-bold text-green-700 mb-2">Breakdown by Payment Method</div>
        <table class="min-w-full text-sm rounded shadow mb-8">
          <thead>
            <tr class="bg-green-100 text-green-900">
              <th class="px-2 py-2 w-8"></th>
              <th class="px-3 py-2">Metode Pembayaran</th>
              <th class="px-3 py-2 text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(total, paymode) in paymentBreakdown" :key="paymode">
              <tr class="bg-white border-b last:border-b-0">
                <td class="px-2 py-2 text-center">
                  <button @click="toggleExpandPaymode(paymode)" class="focus:outline-none">
                    <i :class="expandedPaymode[paymode] ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"></i>
                  </button>
                </td>
                <td class="px-3 py-2">{{ paymode || '-' }}</td>
                <td class="px-3 py-2 text-right">{{ formatCurrency(total) }}</td>
              </tr>
              <tr v-if="expandedPaymode[paymode]">
                <td></td>
                <td colspan="2" class="bg-blue-50 px-6 py-2">
                  <div class="font-semibold mb-1">Detail {{ paymode }}</div>
                  <table class="min-w-full text-xs mb-2">
                    <thead>
                      <tr class="bg-blue-100 text-blue-900">
                        <th class="px-2 py-1">Payment Type</th>
                        <th class="px-2 py-1 text-right">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(ptotal, ptype) in paymentTypeBreakdown[paymode]" :key="ptype">
                        <td class="px-2 py-1">{{ ptype || '-' }}</td>
                        <td class="px-2 py-1 text-right">{{ formatCurrency(ptotal) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <!-- Pengeluaran Bahan Baku -->
      <div class="mb-8">
        <div class="font-bold text-red-700 mb-2">Pengeluaran Bahan Baku</div>
        <div v-if="loadingExpenses" class="text-gray-400 italic">Loading...</div>
        <div v-else-if="expenses.retail_food && expenses.retail_food.length">
          <div v-for="trx in expenses.retail_food" :key="'rf-' + trx.id" class="mb-6 border rounded-lg p-4">
            <div class="font-semibold text-gray-800 mb-1">No: {{ trx.retail_number }} | Tanggal: {{ formatDateIndo(trx.transaction_date) }}</div>
            <div class="text-gray-600 mb-2">Total: <span class="font-bold">{{ formatCurrency(trx.total_amount) }}</span></div>
            <div class="mb-2">
              <span class="font-semibold">Items:</span>
              <ul class="list-disc ml-6">
                <li v-for="item in trx.items" :key="item.id">
                  {{ item.item_name }} - {{ item.qty }} x {{ formatCurrency(item.harga_barang) }} = <span class="font-bold">{{ formatCurrency(item.subtotal) }}</span>
                </li>
              </ul>
            </div>
            <div class="flex flex-wrap gap-2 items-center mt-2">
              <span class="font-semibold">Invoice:</span>
              <template v-if="trx.invoices.length">
                <img v-for="(inv, idx) in trx.invoices" :key="idx" :src="inv.file_path" alt="Invoice" class="w-20 h-20 object-cover rounded shadow cursor-pointer" @click="previewImage(inv.file_path)" />
              </template>
              <span v-else class="italic text-gray-400">no image available</span>
            </div>
          </div>
        </div>
        <div v-else class="text-gray-400 italic">Tidak ada pengeluaran bahan baku.</div>
      </div>
      <!-- Pengeluaran Non Bahan Baku -->
      <div class="mb-8">
        <div class="font-bold text-purple-700 mb-2">Pengeluaran Non Bahan Baku</div>
        <div v-if="loadingExpenses" class="text-gray-400 italic">Loading...</div>
        <div v-else-if="expenses.retail_non_food && expenses.retail_non_food.length">
          <div v-for="trx in expenses.retail_non_food" :key="'rnf-' + trx.id" class="mb-6 border rounded-lg p-4">
            <div class="font-semibold text-gray-800 mb-1">No: {{ trx.retail_number }} | Tanggal: {{ formatDateIndo(trx.transaction_date) }}</div>
            <div class="text-gray-600 mb-2">Total: <span class="font-bold">{{ formatCurrency(trx.total_amount) }}</span></div>
            <div class="mb-2">
              <span class="font-semibold">Items:</span>
              <ul class="list-disc ml-6">
                <li v-for="item in trx.items" :key="item.id">
                  {{ item.item_name }} - {{ item.qty }} {{ item.unit }} x {{ formatCurrency(item.price) }} = <span class="font-bold">{{ formatCurrency(item.subtotal) }}</span>
                </li>
              </ul>
            </div>
            <div class="flex flex-wrap gap-2 items-center mt-2">
              <span class="font-semibold">Invoice:</span>
              <template v-if="trx.invoices.length">
                <img v-for="(inv, idx) in trx.invoices" :key="idx" :src="inv.file_path" alt="Invoice" class="w-20 h-20 object-cover rounded shadow cursor-pointer" @click="previewImage(inv.file_path)" />
              </template>
              <span v-else class="italic text-gray-400">no image available</span>
            </div>
          </div>
        </div>
        <div v-else class="text-gray-400 italic">Tidak ada pengeluaran non bahan baku.</div>
      </div>
      <!-- Nilai Setor Cash -->
      <div class="mt-8 p-4 bg-blue-50 rounded-lg border-2 border-blue-200">
        <div class="font-bold text-blue-800 mb-2">Nilai Setor Cash</div>
        <div class="text-lg">
          <div class="flex justify-between items-center">
            <span>Total Cash:</span>
            <span class="font-semibold">{{ formatCurrency(totalCash) }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span>Total Pengeluaran:</span>
            <span class="font-semibold">{{ formatCurrency(totalExpenses) }}</span>
          </div>
          <div class="border-t border-blue-300 my-2"></div>
          <div class="flex justify-between items-center text-xl font-bold text-blue-900">
            <span>Nilai Setor Cash:</span>
            <span>{{ formatCurrency(nilaiSetorCash) }}</span>
          </div>
        </div>
      </div>
      <!-- Image Preview Modal -->
      <div v-if="imagePreview" class="fixed inset-0 z-60 flex items-center justify-center bg-black bg-opacity-70" @click.self="imagePreview = null">
        <img :src="imagePreview" class="max-w-full max-h-[80vh] rounded shadow-2xl border-4 border-white" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
const props = defineProps({
  tanggal: String,
  orders: Array,
  outlets: Array
});
const totalSales = computed(() => {
  return (props.orders || []).reduce((sum, o) => sum + (Number(o.grand_total) || 0), 0);
});
const totalCash = computed(() => {
  const entries = Object.entries(paymentBreakdown.value);
  const found = entries.find(([k]) => k && k.toUpperCase() === 'CASH');
  return found ? found[1] : 0;
});
const totalExpenses = computed(() => {
  const retailFoodTotal = (expenses.value.retail_food || []).reduce((sum, rf) => sum + (Number(rf.total_amount) || 0), 0);
  const retailNonFoodTotal = (expenses.value.retail_non_food || []).reduce((sum, rnf) => sum + (Number(rnf.total_amount) || 0), 0);
  return retailFoodTotal + retailNonFoodTotal;
});
const nilaiSetorCash = computed(() => {
  return totalCash.value - totalExpenses.value;
});
const paymentBreakdown = computed(() => {
  const result = {};
  (props.orders || []).forEach(o => {
    if (o.payments && Array.isArray(o.payments)) {
      o.payments.forEach(p => {
        const paymode = p.payment_code || '-';
        // Ubah: cash = amount - change
        const total = (Number(p.amount) || 0) - (Number(p.change) || 0);
        result[paymode] = (result[paymode] || 0) + total;
      });
    } else if (o.payment_code) {
      const paymode = o.payment_code || '-';
      // Ubah: cash = amount - change
      const total = (Number(o.amount) || 0) - (Number(o.change) || 0);
      result[paymode] = (result[paymode] || 0) + total;
    }
  });
  return result;
});
const expandedPaymode = ref({});
function toggleExpandPaymode(paymode) {
  expandedPaymode.value[paymode] = !expandedPaymode.value[paymode];
}
// Breakdown per payment_type untuk setiap payment_code
const paymentTypeBreakdown = computed(() => {
  const result = {};
  (props.orders || []).forEach(o => {
    if (o.payments && Array.isArray(o.payments)) {
      o.payments.forEach(p => {
        const paymode = p.payment_code || '-';
        let ptype = p.payment_type;
        if (!ptype && o.payment_type) ptype = o.payment_type;
        if (!ptype) ptype = 'Unknown';
        ptype = String(ptype).toUpperCase(); // kapitalisasi
        const total = (Number(p.amount) || 0) - (Number(p.change) || 0);
        if (!result[paymode]) result[paymode] = {};
        result[paymode][ptype] = (result[paymode][ptype] || 0) + total;
      });
    } else if (o.payment_code) {
      const paymode = o.payment_code || '-';
      let ptype = o.payment_type;
      if (!ptype) ptype = 'Unknown';
      ptype = String(ptype).toUpperCase(); // kapitalisasi
      const total = (Number(o.amount) || 0) - (Number(o.change) || 0);
      if (!result[paymode]) result[paymode] = {};
      result[paymode][ptype] = (result[paymode][ptype] || 0) + total;
    }
  });
  return result;
});
const expenses = ref({ retail_food: [], retail_non_food: [] });
const loadingExpenses = ref(false);
const imagePreview = ref(null);
function previewImage(url) {
  imagePreview.value = url;
}
async function fetchExpenses() {
  console.log('fetchExpenses called', { orders: props.orders, tanggal: props.tanggal, outlets: props.outlets });
  if (!props.orders || !props.orders.length) return;
  
  // Cari kode_outlet dari salah satu order
  const kodeOutlet = props.orders[0]?.kode_outlet;
  let outletId = null;
  
  if (kodeOutlet && props.outlets) {
    const found = props.outlets.find(o => o.qr_code === kodeOutlet);
    outletId = found ? found.id : null;
  }
  
  // Jika tidak ditemukan di outlets array, coba ambil dari API
  if (!outletId && kodeOutlet) {
    try {
      const res = await fetch(`/api/outlets/report`);
      if (res.ok) {
        const data = await res.json();
        const found = data.outlets?.find(o => o.qr_code === kodeOutlet);
        outletId = found ? found.id : null;
      }
    } catch (e) {
      console.error('Error fetching outlets for outlet ID lookup:', e);
    }
  }
  
  if (!outletId || !props.tanggal) {
    console.log('fetchExpenses: missing outletId or tanggal', { outletId, tanggal: props.tanggal });
    return;
  }
  
  loadingExpenses.value = true;
  try {
    console.log('fetchExpenses: fetching', `/api/outlet-expenses?outlet_id=${encodeURIComponent(outletId)}&date=${encodeURIComponent(props.tanggal)}`);
    const res = await fetch(`/api/outlet-expenses?outlet_id=${encodeURIComponent(outletId)}&date=${encodeURIComponent(props.tanggal)}`);
    if (res.ok) {
      const data = await res.json();
      console.log('fetchExpenses: response', data);
      expenses.value = data;
    } else {
      console.log('fetchExpenses: response not ok', res.status);
      expenses.value = { retail_food: [], retail_non_food: [] };
    }
  } catch (e) {
    console.error('fetchExpenses error', e);
    expenses.value = { retail_food: [], retail_non_food: [] };
  } finally {
    loadingExpenses.value = false;
  }
}
watch(() => [props.tanggal, props.orders], fetchExpenses, { immediate: true });
function formatCurrency(val) {
  if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  if (!val) return '-';
  const num = Number(val);
  if (!isNaN(num)) return num.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  return val;
}
function formatDateIndo(dateStr) {
  if (!dateStr) return '-';
  const bulan = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  return `${d.getDate().toString().padStart(2, '0')} ${bulan[d.getMonth()]} ${d.getFullYear()}`;
}
function printModal() {
  window.print();
}
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: none; }
}
.animate-fadeIn {
  animation: fadeIn 0.25s;
}
@media print {
  body * {
    visibility: hidden !important;
  }
  .print-modal, .print-modal * {
    visibility: visible !important;
  }
  .print-modal {
    position: absolute !important;
    left: 0; top: 0; width: 100vw; height: auto; background: #fff !important; box-shadow: none !important;
    z-index: 9999 !important;
    padding: 0 !important;
  }
  .print-modal button, .print-modal .fa-times, .print-modal .fa-print {
    display: none !important;
  }
}
</style> 