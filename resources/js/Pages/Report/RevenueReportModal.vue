<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-8 relative animate-fadeIn overflow-y-auto print-modal" style="max-height: 90vh;" id="revenue-report-modal">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <button @click="exportToExcel" class="absolute top-4 right-16 text-gray-400 hover:text-green-600 text-2xl font-bold" title="Export to Excel">
        <i class="fa-solid fa-file-excel"></i>
      </button>
      <button @click="printModal" class="absolute top-4 right-24 text-gray-400 hover:text-blue-600 text-2xl font-bold" title="Print PDF">
        <i class="fa-solid fa-print"></i>
      </button>
      <div class="text-center mb-4">
        <div class="text-xl font-bold text-gray-800">Revenue Report</div>
        <div class="text-xs text-gray-400 mt-1">{{ tanggal }}</div>
      </div>
      <div class="border-b border-gray-200 mb-4"></div>
      <div class="mb-6">
        <div class="font-bold text-blue-700 mb-2">Total Sales</div>
        <div class="text-3xl font-extrabold text-blue-800 mb-2">{{ formatCurrency(totalSales) }}</div>
        <div v-if="totalDp > 0" class="text-sm text-amber-700 mt-1">
          + DP Reservasi {{ formatCurrency(totalDp) }} = <span class="font-bold text-slate-800">{{ formatCurrency(totalRevenue) }}</span> (Total Revenue)
        </div>
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
      <!-- DP Reservasi - selalu tampil setelah loading -->
      <div class="mb-8">
        <div class="font-bold text-amber-700 mb-3">DP Reservasi</div>
        <div v-if="loadingDp" class="text-gray-400 italic">Loading DP reservasi...</div>
        <template v-else>
          <!-- 1) DP jadwal hari ini -->
          <div class="mb-4">
            <div class="text-sm font-semibold text-amber-800 mb-1">DP Reservasi (jadwal hari ini)</div>
            <template v-if="(dpSummary.total_dp || 0) > 0">
              <div class="text-lg font-semibold text-amber-800 mb-1">{{ formatCurrency(dpSummary.total_dp) }}</div>
              <table class="min-w-full text-sm rounded shadow">
                <thead>
                  <tr class="bg-amber-100 text-amber-900">
                    <th class="px-3 py-2 text-left">Jenis Pembayaran</th>
                    <th class="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in dpBreakdown" :key="'dp-' + (row.payment_type_name || '')" class="bg-white border-b last:border-b-0">
                    <td class="px-3 py-2">{{ row.payment_type_name || '-' }}</td>
                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.total) }}</td>
                  </tr>
                </tbody>
              </table>
            </template>
            <div v-else class="text-gray-500 text-sm italic">Tidak ada DP untuk reservasi dengan jadwal hari ini.</div>
          </div>
          <!-- 2) DP diterima hari ini untuk reservasi tanggal mendatang -->
          <div class="mb-4">
            <div class="text-sm font-semibold text-emerald-800 mb-1">DP Diterima Hari Ini (untuk reservasi tanggal mendatang)</div>
            <template v-if="dpFutureTotal > 0">
              <div class="text-lg font-semibold text-emerald-800 mb-1">{{ formatCurrency(dpFutureTotal) }}</div>
              <table class="min-w-full text-sm rounded shadow">
                <thead>
                  <tr class="bg-emerald-100 text-emerald-900">
                    <th class="px-3 py-2 text-left">Jenis Pembayaran</th>
                    <th class="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in dpFutureBreakdown" :key="'dpf-' + (row.payment_type_name || '')" class="bg-white border-b last:border-b-0">
                    <td class="px-3 py-2">{{ row.payment_type_name || '-' }}</td>
                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.total) }}</td>
                  </tr>
                </tbody>
              </table>
            </template>
            <div v-else class="text-gray-500 text-sm italic">Tidak ada DP diterima hari ini untuk reservasi tanggal mendatang.</div>
          </div>
          <!-- 3) Transaksi hari ini yang menggunakan DP -->
          <div>
            <div class="text-sm font-semibold text-indigo-800 mb-1">Transaksi Hari Ini yang Menggunakan DP</div>
            <template v-if="ordersUsingDp.length > 0">
              <table class="min-w-full text-sm rounded shadow">
                <thead>
                  <tr class="bg-indigo-100 text-indigo-900">
                    <th class="px-3 py-2 text-left">No. Bayar</th>
                    <th class="px-3 py-2 text-left">Reservasi</th>
                    <th class="px-3 py-2 text-right">Total</th>
                    <th class="px-3 py-2 text-right">DP</th>
                    <th class="px-3 py-2 text-left">Tanggal DP</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in ordersUsingDp" :key="'ord-dp-' + idx" class="bg-white border-b last:border-b-0">
                    <td class="px-3 py-2">{{ row.paid_number || '-' }}</td>
                    <td class="px-3 py-2">{{ row.reservation_name || '-' }}</td>
                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.grand_total) }}</td>
                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.dp_amount) }}</td>
                    <td class="px-3 py-2">{{ row.dp_paid_at ? formatDateIndo(row.dp_paid_at) : '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </template>
            <div v-else class="text-gray-500 text-sm italic">Tidak ada transaksi hari ini yang menggunakan DP.</div>
          </div>
        </template>
      </div>
      <!-- Pengeluaran Bahan Baku -->
      <div class="mb-8">
        <div class="font-bold text-red-700 mb-2">Pengeluaran Bahan Baku</div>
        <div v-if="loadingExpenses" class="text-gray-400 italic">Loading...</div>
        <div v-else-if="expenses.retail_food && expenses.retail_food.length">
          <div v-for="trx in expenses.retail_food" :key="'rf-' + trx.id" class="mb-3 border rounded-lg p-3">
            <div class="font-semibold text-gray-800 mb-2">No: {{ trx.retail_number }}</div>
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
                <img v-for="(inv, idx) in trx.invoices" :key="idx" :src="getImageUrl(inv)" alt="Invoice" class="w-20 h-20 object-cover rounded shadow cursor-pointer" @click="openLightbox(trx.invoices, idx)" @error="(e) => e.target.style.display='none'" />
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
          <!-- Group transactions by category for budget info display -->
          <div v-for="(categoryGroup, categoryId) in groupedRnfByCategory" :key="'category-' + categoryId" class="mb-4">
            <!-- Budget Info (show once per category) - Only Division and Category Name -->
            <div v-if="categoryGroup.budget_info" class="mb-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
              <div v-if="categoryGroup.budget_info.division_name" class="text-sm text-black dark:text-black font-medium mb-1">
                {{ categoryGroup.budget_info.division_name }}
              </div>
              <div class="font-bold text-black dark:text-black text-base">
                {{ categoryGroup.budget_info.category_name || 'Category ' + categoryId }}
              </div>
            </div>
            
            <!-- Transactions for this category -->
            <div v-for="trx in categoryGroup.transactions" :key="'rnf-' + trx.id" class="mb-3 border rounded-lg p-3">
              <div class="font-semibold text-gray-800 mb-2">No: {{ trx.retail_number }}</div>
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
                  <img v-for="(inv, idx) in trx.invoices" :key="idx" :src="getImageUrl(inv)" alt="Invoice" class="w-20 h-20 object-cover rounded shadow cursor-pointer" @click="openLightbox(trx.invoices, idx)" @error="(e) => e.target.style.display='none'" />
                </template>
                <span v-else class="italic text-gray-400">no image available</span>
              </div>
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
      
      <!-- Lightbox for Invoice Images -->
      <VueEasyLightbox
        :visible="lightboxVisible"
        :imgs="lightboxImages"
        :index="lightboxIndex"
        @hide="lightboxVisible = false"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import ExcelJS from 'exceljs';
const props = defineProps({
  tanggal: String,
  orders: Array,
  outlets: Array
});
const totalSales = computed(() => {
  return (props.orders || []).reduce((sum, o) => sum + (Number(o.grand_total) || 0), 0);
});
const dpSummary = ref({
  total_dp: 0,
  breakdown: [],
  dp_future_total: 0,
  dp_future_breakdown: [],
  orders_using_dp: []
});
const loadingDp = ref(false);
const totalDp = computed(() => (Number(dpSummary.value.total_dp) || 0) + (Number(dpSummary.value.dp_future_total) || 0));
const dpBreakdown = computed(() => dpSummary.value.breakdown || []);
const dpFutureTotal = computed(() => Number(dpSummary.value.dp_future_total) || 0);
const dpFutureBreakdown = computed(() => dpSummary.value.dp_future_breakdown || []);
const ordersUsingDp = computed(() => dpSummary.value.orders_using_dp || []);
const totalRevenue = computed(() => totalSales.value + totalDp.value);
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

// Get outlet name from orders and outlets
const outletName = computed(() => {
  if (!props.orders || !props.orders.length) return '';
  
  const kodeOutlet = props.orders[0]?.kode_outlet;
  if (!kodeOutlet || !props.outlets) return '';
  
  const found = props.outlets.find(o => o.qr_code === kodeOutlet);
  return found ? found.name : '';
});
const paymentBreakdown = computed(() => {
  const result = {};
  (props.orders || []).forEach(o => {
    if (o.payments && Array.isArray(o.payments)) {
      o.payments.forEach(p => {
        const paymode = p.payment_code || '-';
        // Jangan kurangi change, gunakan amount saja
        const total = (Number(p.amount) || 0);
        result[paymode] = (result[paymode] || 0) + total;
      });
    } else if (o.payment_code) {
      const paymode = o.payment_code || '-';
      // Jangan kurangi change, gunakan amount saja
      const total = (Number(o.amount) || 0);
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
        const total = (Number(p.amount) || 0);
        if (!result[paymode]) result[paymode] = {};
        result[paymode][ptype] = (result[paymode][ptype] || 0) + total;
      });
    } else if (o.payment_code) {
      const paymode = o.payment_code || '-';
      let ptype = o.payment_type;
      if (!ptype) ptype = 'Unknown';
      ptype = String(ptype).toUpperCase(); // kapitalisasi
      const total = (Number(o.amount) || 0);
      if (!result[paymode]) result[paymode] = {};
      result[paymode][ptype] = (result[paymode][ptype] || 0) + total;
    }
  });
  return result;
});
const expenses = ref({ retail_food: [], retail_non_food: [] });
const loadingExpenses = ref(false);
const imagePreview = ref(null);

// Group RNF transactions by category for budget info display
const groupedRnfByCategory = computed(() => {
  if (!expenses.value.retail_non_food || !expenses.value.retail_non_food.length) {
    return {};
  }
  
  const grouped = {};
  expenses.value.retail_non_food.forEach(trx => {
    const categoryId = trx.category_budget_id || 'no-category';
    if (!grouped[categoryId]) {
      grouped[categoryId] = {
        budget_info: trx.budget_info || null,
        transactions: []
      };
    }
    grouped[categoryId].transactions.push(trx);
  });
  
  return grouped;
});

// Lightbox state
const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

// Add image handling function like in Index.vue
const getImageUrl = (image) => {
  if (!image || !image.file_path) return null;
  try {
    return `/storage/${image.file_path}`;
  } catch (error) {
    console.error('Error processing image:', error);
    return null;
  }
}

function previewImage(url) {
  imagePreview.value = url;
}

// Lightbox function for invoice images
function openLightbox(invoices, startIndex = 0) {
  if (!invoices || invoices.length === 0) return;
  lightboxImages.value = invoices.map(inv => getImageUrl(inv)).filter(url => url);
  lightboxIndex.value = startIndex;
  lightboxVisible.value = true;
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
      console.log('fetchExpenses: retail_non_food with budget_info', data.retail_non_food?.map(trx => ({
        id: trx.id,
        retail_number: trx.retail_number,
        category_budget_id: trx.category_budget_id,
        has_budget_info: !!trx.budget_info,
        budget_info: trx.budget_info
      })));
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
function resolveOutletId(kodeOutlet, outlets) {
  if (!kodeOutlet || !outlets?.length) return null;
  const found = outlets.find(o => (o.qr_code || o.kode_outlet) === kodeOutlet);
  return found ? (found.id ?? found.id_outlet) : null;
}
async function fetchDpSummary() {
  if (!props.orders?.length || !props.tanggal) return;
  // Semua outlet unik dari orders (bukan cuma orders[0]), agar DP dari outlet mana pun ikut
  const uniqueKodeOutlets = [...new Set((props.orders || []).map(o => o.kode_outlet).filter(Boolean))];
  if (!uniqueKodeOutlets.length) { loadingDp.value = false; return; }

  let outletsList = props.outlets || [];
  if (!outletsList.length) {
    try {
      const res = await fetch('/api/outlets/report');
      if (res.ok) {
        const data = await res.json();
        outletsList = data.outlets || [];
      }
    } catch (e) {
      console.error('Error fetching outlets for DP summary', e);
    }
  }

  loadingDp.value = true;
  try {
    const merged = {
      total_dp: 0,
      breakdown: {},
      dp_future_total: 0,
      dp_future_breakdown: {},
      orders_using_dp: []
    };
    const toFetch = [];
    for (const kode of uniqueKodeOutlets) {
      const outletId = resolveOutletId(kode, outletsList);
      toFetch.push({ outletId, kodeOutlet: kode });
    }
    for (const { outletId, kodeOutlet } of toFetch) {
      const params = new URLSearchParams({ date: props.tanggal });
      if (outletId) params.set('outlet_id', String(outletId));
      else params.set('kode_outlet', kodeOutlet);
      const res = await fetch(`/api/reservations/dp-summary?${params.toString()}`);
      if (!res.ok) continue;
      const data = await res.json();
      merged.total_dp += Number(data.total_dp ?? 0);
      (data.breakdown || []).forEach(b => {
        const name = b.payment_type_name || 'Lainnya';
        merged.breakdown[name] = (merged.breakdown[name] || 0) + Number(b.total ?? 0);
      });
      merged.dp_future_total += Number(data.dp_future_total ?? 0);
      (data.dp_future_breakdown || []).forEach(b => {
        const name = b.payment_type_name || 'Lainnya';
        merged.dp_future_breakdown[name] = (merged.dp_future_breakdown[name] || 0) + Number(b.total ?? 0);
      });
      merged.orders_using_dp.push(...(data.orders_using_dp || []));
    }
    dpSummary.value = {
      total_dp: merged.total_dp,
      breakdown: Object.entries(merged.breakdown).map(([payment_type_name, total]) => ({ payment_type_name, total })),
      dp_future_total: merged.dp_future_total,
      dp_future_breakdown: Object.entries(merged.dp_future_breakdown).map(([payment_type_name, total]) => ({ payment_type_name, total })),
      orders_using_dp: merged.orders_using_dp
    };
  } catch (e) {
    console.error('fetchDpSummary error', e);
    dpSummary.value = { total_dp: 0, breakdown: [], dp_future_total: 0, dp_future_breakdown: [], orders_using_dp: [] };
  } finally {
    loadingDp.value = false;
  }
}
watch(() => [props.tanggal, props.orders], () => {
  fetchExpenses();
  fetchDpSummary();
}, { immediate: true });
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
function formatNumberForExcel(val) {
  if (val == null || val === '') return 0;
  const num = Number(val);
  return isNaN(num) ? 0 : num;
}

const headerFill = {
  type: 'pattern',
  pattern: 'solid',
  fgColor: { argb: 'FF2563EB' }
};
const headerFont = { bold: true, color: { argb: 'FFFFFFFF' } };
const sectionFont = { bold: true, size: 12 };

function styleHeaderRow(ws, rowIndex) {
  const row = ws.getRow(rowIndex);
  row.eachCell((cell) => {
    cell.fill = headerFill;
    cell.font = headerFont;
    cell.alignment = { vertical: 'middle' };
  });
}

async function exportToExcel() {
  const wb = new ExcelJS.Workbook();
  const ws = wb.addWorksheet('Revenue Report', { views: [{ showGridLines: true }] });

  let rowNum = 1;

  // Title
  ws.getCell(rowNum, 1).value = 'Revenue Report';
  ws.getCell(rowNum, 1).font = { bold: true, size: 16 };
  rowNum += 1;

  ws.getCell(rowNum, 1).value = 'Tanggal';
  ws.getCell(rowNum, 2).value = props.tanggal || '';
  rowNum += 1;

  if (outletName.value) {
    ws.getCell(rowNum, 1).value = 'Outlet';
    ws.getCell(rowNum, 2).value = outletName.value;
    rowNum += 1;
  }
  rowNum += 1;

  // Section: Ringkasan
  ws.getCell(rowNum, 1).value = 'Ringkasan';
  ws.getCell(rowNum, 1).font = sectionFont;
  rowNum += 1;

  const summaryData = [
    ['Total Sales', formatNumberForExcel(totalSales.value)],
    ...(totalDp.value > 0 ? [['DP Reservasi', formatNumberForExcel(totalDp.value)], ['Total Revenue', formatNumberForExcel(totalRevenue.value)]] : []),
    ['Total Cash', formatNumberForExcel(totalCash.value)],
    ['Total Pengeluaran', formatNumberForExcel(totalExpenses.value)],
    ['Nilai Setor Cash', formatNumberForExcel(nilaiSetorCash.value)]
  ];
  summaryData.forEach(([label, value]) => {
    ws.getCell(rowNum, 1).value = label;
    ws.getCell(rowNum, 2).value = value;
    ws.getCell(rowNum, 2).numFmt = '#,##0';
    rowNum += 1;
  });
  rowNum += 1;

  // Section: Breakdown by Payment Method
  ws.getCell(rowNum, 1).value = 'Breakdown by Payment Method';
  ws.getCell(rowNum, 1).font = sectionFont;
  rowNum += 1;

  ws.getRow(rowNum).values = [null, 'Metode Pembayaran', 'Payment Type', 'Total'];
  styleHeaderRow(ws, rowNum);
  rowNum += 1;

  Object.entries(paymentBreakdown.value).forEach(([paymode, total]) => {
    const paymentTypes = paymentTypeBreakdown.value[paymode] || {};
    const typeEntries = Object.entries(paymentTypes);
    if (typeEntries.length === 0) {
      ws.getRow(rowNum).values = [null, paymode || '-', '-', formatNumberForExcel(total)];
      ws.getCell(rowNum, 3).numFmt = '#,##0';
      rowNum += 1;
    } else {
      typeEntries.forEach(([ptype, ptotal], idx) => {
        ws.getRow(rowNum).values = [null, idx === 0 ? (paymode || '-') : '', ptype || '-', formatNumberForExcel(ptotal)];
        ws.getCell(rowNum, 3).numFmt = '#,##0';
        rowNum += 1;
      });
    }
  });
  rowNum += 1;

  if ((dpSummary.value.total_dp || 0) > 0) {
    ws.getCell(rowNum, 1).value = 'DP Reservasi (jadwal hari ini)';
    ws.getCell(rowNum, 1).font = sectionFont;
    rowNum += 1;
    ws.getRow(rowNum).values = [null, 'Jenis Pembayaran', 'Total'];
    styleHeaderRow(ws, rowNum);
    rowNum += 1;
    (dpBreakdown.value || []).forEach(row => {
      ws.getRow(rowNum).values = [null, row.payment_type_name || '-', formatNumberForExcel(row.total)];
      ws.getCell(rowNum, 3).numFmt = '#,##0';
      rowNum += 1;
    });
    ws.getRow(rowNum).values = [null, 'Total', formatNumberForExcel(dpSummary.value.total_dp)];
    ws.getCell(rowNum, 3).numFmt = '#,##0';
    rowNum += 1;
    rowNum += 1;
  }

  if (dpFutureTotal.value > 0) {
    ws.getCell(rowNum, 1).value = 'DP Diterima Hari Ini (untuk reservasi tanggal mendatang)';
    ws.getCell(rowNum, 1).font = sectionFont;
    rowNum += 1;
    ws.getRow(rowNum).values = [null, 'Jenis Pembayaran', 'Total'];
    styleHeaderRow(ws, rowNum);
    rowNum += 1;
    (dpFutureBreakdown.value || []).forEach(row => {
      ws.getRow(rowNum).values = [null, row.payment_type_name || '-', formatNumberForExcel(row.total)];
      ws.getCell(rowNum, 3).numFmt = '#,##0';
      rowNum += 1;
    });
    ws.getRow(rowNum).values = [null, 'Total', formatNumberForExcel(dpFutureTotal.value)];
    ws.getCell(rowNum, 3).numFmt = '#,##0';
    rowNum += 1;
    rowNum += 1;
  }

  if (ordersUsingDp.value && ordersUsingDp.value.length > 0) {
    ws.getCell(rowNum, 1).value = 'Transaksi Hari Ini yang Menggunakan DP';
    ws.getCell(rowNum, 1).font = sectionFont;
    rowNum += 1;
    ws.getRow(rowNum).values = [null, 'No. Bayar', 'Reservasi', 'Total', 'DP', 'Tanggal DP'];
    styleHeaderRow(ws, rowNum);
    rowNum += 1;
    ordersUsingDp.value.forEach(row => {
      ws.getRow(rowNum).values = [null, row.paid_number || '-', row.reservation_name || '-', formatNumberForExcel(row.grand_total), formatNumberForExcel(row.dp_amount), row.dp_paid_at || '-'];
      ws.getCell(rowNum, 4).numFmt = '#,##0';
      ws.getCell(rowNum, 5).numFmt = '#,##0';
      rowNum += 1;
    });
    rowNum += 1;
  }

  // Section: Pengeluaran Bahan Baku
  ws.getCell(rowNum, 1).value = 'Pengeluaran Bahan Baku';
  ws.getCell(rowNum, 1).font = sectionFont;
  rowNum += 1;

  const retailFood = expenses.value.retail_food || [];
  if (retailFood.length === 0) {
    ws.getCell(rowNum, 1).value = 'Tidak ada pengeluaran bahan baku.';
    rowNum += 1;
  } else {
    ws.getRow(rowNum).values = [null, 'No', 'Item', 'Qty', 'Harga', 'Subtotal'];
    styleHeaderRow(ws, rowNum);
    rowNum += 1;
    retailFood.forEach(trx => {
      (trx.items || []).forEach((item, i) => {
        ws.getRow(rowNum).values = [null, i === 0 ? trx.retail_number : '', item.item_name, formatNumberForExcel(item.qty), formatNumberForExcel(item.harga_barang), formatNumberForExcel(item.subtotal)];
        ws.getCell(rowNum, 4).numFmt = '#,##0';
        ws.getCell(rowNum, 5).numFmt = '#,##0';
        ws.getCell(rowNum, 6).numFmt = '#,##0';
        rowNum += 1;
      });
    });
  }
  rowNum += 1;

  // Section: Pengeluaran Non Bahan Baku
  ws.getCell(rowNum, 1).value = 'Pengeluaran Non Bahan Baku';
  ws.getCell(rowNum, 1).font = sectionFont;
  rowNum += 1;

  const retailNonFood = expenses.value.retail_non_food || [];
  if (retailNonFood.length === 0) {
    ws.getCell(rowNum, 1).value = 'Tidak ada pengeluaran non bahan baku.';
    rowNum += 1;
  } else {
    ws.getRow(rowNum).values = [null, 'No', 'Divisi/Kategori', 'Item', 'Qty', 'Unit', 'Harga', 'Subtotal'];
    styleHeaderRow(ws, rowNum);
    rowNum += 1;
    retailNonFood.forEach(trx => {
      const divName = (trx.budget_info && trx.budget_info.division_name) ? trx.budget_info.division_name : '';
      const catName = (trx.budget_info && trx.budget_info.category_name) ? trx.budget_info.category_name : '';
      const catInfo = (divName + ' - ' + catName).trim() || '-';
      (trx.items || []).forEach((item, i) => {
        ws.getRow(rowNum).values = [null, i === 0 ? trx.retail_number : '', i === 0 ? catInfo : '', item.item_name, formatNumberForExcel(item.qty), item.unit || '', formatNumberForExcel(item.price), formatNumberForExcel(item.subtotal)];
        ws.getCell(rowNum, 5).numFmt = '#,##0';
        ws.getCell(rowNum, 7).numFmt = '#,##0';
        ws.getCell(rowNum, 8).numFmt = '#,##0';
        rowNum += 1;
      });
    });
  }

  // Column widths
  ws.getColumn(1).width = 8;
  ws.getColumn(2).width = 22;
  ws.getColumn(3).width = 28;
  ws.getColumn(4).width = 14;
  ws.getColumn(5).width = 14;
  ws.getColumn(6).width = 10;
  ws.getColumn(7).width = 14;
  ws.getColumn(8).width = 14;

  // Filename: Revenue_Report_<outlet>_<date>.xlsx
  const safeDate = (props.tanggal || '').replace(/[/\\?*\[\]:]/g, '-').slice(0, 30) || 'report';
  const safeOutlet = (outletName.value || '')
    .replace(/[/\\?*\[\]:"]/g, '')
    .replace(/\s+/g, '_')
    .slice(0, 40) || '';
  const filename = (safeOutlet ? 'Revenue_Report_' + safeOutlet + '_' + safeDate : 'Revenue_Report_' + safeDate) + '.xlsx';

  const buffer = await wb.xlsx.writeBuffer();
  const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

function printModal() {
  setTimeout(() => {
    const modalContent = document.getElementById('revenue-report-modal');
    if (!modalContent) {
      alert('Modal tidak ditemukan!');
      return;
    }
    // Clone isi modal tanpa tombol
    const cleanContent = modalContent.cloneNode(true);
    const buttons = cleanContent.querySelectorAll('button, .fa-solid');
    buttons.forEach(btn => btn.remove());

    // Buka window baru
    const printWindow = window.open('', '_blank', 'width=900,height=1200');
    printWindow.document.write(`
      <html>
        <head>
          <title>Revenue Report</title>
                     <style>
             body {
               font-family: 'Segoe UI', Arial, sans-serif;
               margin: 0;
               padding: 16px 12px;
               background: #fff;
               color: #222;
               font-size: 10px;
               line-height: 1.2;
             }
             .report-title {
               font-size: 1.2rem;
               font-weight: bold;
               color: #2563eb;
               margin-bottom: 0.25rem;
               text-align: center;
             }
             .report-date {
               font-size: 0.8rem;
               color: #888;
               text-align: center;
               margin-bottom: 0.75rem;
             }
             .report-outlet {
               font-size: 0.9rem;
               color: #2563eb;
               text-align: center;
               margin-bottom: 0.5rem;
               font-weight: 600;
             }
             .summary-section {
               display: flex;
               flex-wrap: wrap;
               gap: 16px;
               margin-bottom: 1rem;
               justify-content: center;
             }
             .summary-card {
               background: #f3f6fa;
               border-radius: 6px;
               box-shadow: 0 1px 4px rgba(0,0,0,0.04);
               padding: 8px 16px;
               min-width: 120px;
               text-align: center;
             }
             .summary-label {
               font-size: 0.7rem;
               color: #666;
               margin-bottom: 0.1rem;
             }
             .summary-value {
               font-size: 1rem;
               font-weight: bold;
               color: #2563eb;
             }
             table {
               width: 100%;
               border-collapse: collapse;
               margin-bottom: 0.75rem;
               font-size: 9px;
             }
             th, td {
               padding: 4px 6px;
               border-bottom: 1px solid #e5e7eb;
             }
             th {
               background: #e0eaff;
               color: #1e293b;
               font-weight: bold;
               font-size: 9px;
             }
             .section-title {
               font-size: 0.9rem;
               font-weight: bold;
               color: #2563eb;
               margin: 1rem 0 0.25rem 0;
             }
             .expense-block {
               border: 1px solid #e5e7eb;
               border-radius: 4px;
               padding: 6px 10px;
               margin-bottom: 0.5rem;
               background: #f9fafb;
               font-size: 9px;
             }
             .expense-title {
               font-weight: bold;
               color: #222;
               font-size: 9px;
             }
             .expense-items {
               margin: 0.25rem 0 0.25rem 0.5rem;
             }
             .expense-items ul {
               margin: 0;
               padding-left: 1rem;
             }
             .expense-items li {
               margin-bottom: 0.1rem;
             }
             .expense-total {
               font-weight: bold;
               color: #2563eb;
             }
             .cash-section {
               background: #e0eaff;
               border-radius: 4px;
               padding: 8px 12px;
               margin-top: 1rem;
               font-size: 0.9rem;
             }
             .cash-row {
               display: flex;
               justify-content: space-between;
               margin-bottom: 0.25rem;
             }
             .cash-label {
               color: #222;
             }
             .cash-value {
               font-weight: bold;
             }
                           @media print {
                body { 
                  margin: 0; 
                  padding: 8px 6px;
                }
                @page {
                  margin: 0.25in;
                  size: A4;
                }
                /* Pastikan semua konten muat dalam 1 halaman */
                .section-title {
                  page-break-after: avoid;
                  page-break-inside: avoid;
                }
                .expense-block {
                  page-break-inside: avoid;
                }
                .cash-section {
                  page-break-inside: avoid;
                }
                table {
                  page-break-inside: avoid;
                }
                /* Kompres spacing lebih lanjut untuk print */
                .summary-section {
                  gap: 8px;
                  margin-bottom: 0.5rem;
                }
                .summary-card {
                  padding: 4px 8px;
                  min-width: 100px;
                }
                .expense-block {
                  padding: 4px 6px;
                  margin-bottom: 0.25rem;
                }
                .cash-section {
                  padding: 6px 8px;
                  margin-top: 0.5rem;
                }
              }
           </style>
        </head>
        <body>
          <div class="report-title">Revenue Report</div>
          ${outletName.value ? `<div class="report-outlet">${outletName.value}</div>` : ''}
          <div class="report-date">${props.tanggal || ''}</div>
                     <!-- Summary Section -->
           <div class="summary-section">
             <div class="summary-card">
               <div class="summary-label">Total Sales</div>
               <div class="summary-value">${formatCurrency(totalSales.value)}</div>
             </div>
             ${totalDp.value > 0 ? `
             <div class="summary-card">
               <div class="summary-label">DP Reservasi</div>
               <div class="summary-value">${formatCurrency(totalDp.value)}</div>
             </div>
             <div class="summary-card">
               <div class="summary-label">Total Revenue</div>
               <div class="summary-value">${formatCurrency(totalRevenue.value)}</div>
             </div>
             ` : ''}
           </div>
                     <!-- Payment Breakdown -->
           <div class="section-title">Breakdown by Payment Method</div>
           <table>
             <thead>
               <tr>
                 <th>Metode Pembayaran</th>
                 <th>Payment Type</th>
                 <th>Total</th>
               </tr>
             </thead>
             <tbody>
               ${Object.entries(paymentBreakdown.value).map(([paymode, total]) => {
                 const paymentTypes = paymentTypeBreakdown.value[paymode] || {};
                 const typeEntries = Object.entries(paymentTypes);
                 
                 if (typeEntries.length === 0) {
                   return `<tr>
                     <td>${paymode || '-'}</td>
                     <td>-</td>
                     <td style="text-align:right">${formatCurrency(total)}</td>
                   </tr>`;
                 }
                 
                 return typeEntries.map(([ptype, ptotal], index) => `
                   <tr>
                     <td>${index === 0 ? (paymode || '-') : ''}</td>
                     <td>${ptype || '-'}</td>
                     <td style="text-align:right">${formatCurrency(ptotal)}</td>
                   </tr>
                 `).join('');
               }).join('')}
             </tbody>
           </table>
          ${totalDp.value > 0 ? `
          <div class="section-title">DP Reservasi</div>
          <table>
            <thead>
              <tr><th>Jenis Pembayaran</th><th>Total</th></tr>
            </thead>
            <tbody>
              ${(dpBreakdown.value || []).map(row => `
                <tr>
                  <td>${row.payment_type_name || '-'}</td>
                  <td style="text-align:right">${formatCurrency(row.total)}</td>
                </tr>
              `).join('')}
              <tr style="font-weight:bold">
                <td>Total DP</td>
                <td style="text-align:right">${formatCurrency(totalDp.value)}</td>
              </tr>
            </tbody>
          </table>
          ` : ''}
          <!-- Pengeluaran Bahan Baku -->
          <div class="section-title">Pengeluaran Bahan Baku</div>
          ${(expenses.value.retail_food || []).length === 0 ? '<div style="color:#888">Tidak ada pengeluaran bahan baku.</div>' : ''}
                    ${(expenses.value.retail_food || []).map(trx => `
            <div class="expense-block">
              <div class="expense-title">No: ${trx.retail_number}</div>
              <div class="expense-items">
                <ul>
                  ${(trx.items || []).map(item => `
                    <li>${item.item_name} - ${item.qty} x ${formatCurrency(item.harga_barang)} = <span class="expense-total">${formatCurrency(item.subtotal)}</span></li>
                    `).join('')}
                </ul>
              </div>
            </div>
          `).join('')}
          <!-- Pengeluaran Non Bahan Baku -->
          <div class="section-title">Pengeluaran Non Bahan Baku</div>
          ${(expenses.value.retail_non_food || []).length === 0 ? '<div style="color:#888">Tidak ada pengeluaran non bahan baku.</div>' : ''}
                    ${(() => {
                      // Group by category for print
                      const grouped = {};
                      (expenses.value.retail_non_food || []).forEach(trx => {
                        const categoryId = trx.category_budget_id || 'no-category';
                        if (!grouped[categoryId]) {
                          grouped[categoryId] = {
                            budget_info: trx.budget_info || null,
                            transactions: []
                          };
                        }
                        grouped[categoryId].transactions.push(trx);
                      });
                      
                      return Object.entries(grouped).map(([categoryId, group]) => {
                        let html = '';
                        // Budget Info (show once per category) - Only Division and Category Name
                        if (group.budget_info) {
                          html += `
                            <div style="margin-bottom: 0.5rem; padding: 0.5rem; background: #e0eaff; border-radius: 4px; font-size: 9px;">
                              ${group.budget_info.division_name ? `<div style="font-size: 8px; color: #000000; margin-bottom: 0.2rem; font-weight: 500;">${group.budget_info.division_name}</div>` : ''}
                              <div style="font-weight: bold; color: #000000; font-size: 10px;">${group.budget_info.category_name || 'Category ' + categoryId}</div>
                            </div>
                          `;
                        }
                        // Transactions for this category
                        group.transactions.forEach(trx => {
                          html += `
                            <div class="expense-block">
                              <div class="expense-title">No: ${trx.retail_number}</div>
                              <div class="expense-items">
                                <ul>
                                  ${(trx.items || []).map(item => `
                                    <li>${item.item_name} - ${item.qty} ${item.unit} x ${formatCurrency(item.price)} = <span class="expense-total">${formatCurrency(item.subtotal)}</span></li>
                                  `).join('')}
                                </ul>
                              </div>
                            </div>
                          `;
                        });
                        return html;
                      }).join('');
                    })()}
           <!-- Summary Section -->
           <div class="section-title">Summary</div>
           <div class="cash-section">
             <div class="cash-row"><span class="cash-label">Total Cash:</span><span class="cash-value">${formatCurrency(totalCash.value)}</span></div>
             <div class="cash-row"><span class="cash-label">Total Pengeluaran:</span><span class="cash-value">${formatCurrency(totalExpenses.value)}</span></div>
             <div class="cash-row" style="font-size:1rem;font-weight:bold;"><span class="cash-label">Nilai Setor Cash:</span><span class="cash-value">${formatCurrency(nilaiSetorCash.value)}</span></div>
           </div>
        </body>
      </html>
    `);
    printWindow.document.close();
    setTimeout(() => {
      printWindow.focus();
      printWindow.print();
      printWindow.close();
    }, 500);
  }, 100);
}
</script>

<style scoped>
/* CSS untuk animasi modal */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: none; }
}
.animate-fadeIn {
  animation: fadeIn 0.25s;
}

/* CSS untuk print */
@media print {
  /* Sembunyikan semua elemen kecuali modal */
  body > *:not(.fixed) {
    display: none !important;
  }
  
  /* Sembunyikan overlay background */
  .fixed.inset-0 {
    display: none !important;
  }
  
  /* Reset styling untuk modal saat print */
  .print-modal {
    position: static !important;
    left: auto !important;
    top: auto !important;
    width: 100% !important;
    height: auto !important;
    max-height: none !important;
    background: #fff !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    z-index: auto !important;
    padding: 20px !important;
    margin: 0 !important;
    overflow: visible !important;
  }
  
  /* Sembunyikan tombol-tombol */
  .print-modal button, 
  .print-modal .fa-times, 
  .print-modal .fa-print,
  .print-modal .fa-solid {
    display: none !important;
  }
  
  /* Pastikan tabel tidak terpotong */
  .print-modal table {
    page-break-inside: avoid !important;
  }
  
  /* Pastikan div tidak terpotong */
  .print-modal div {
    page-break-inside: avoid !important;
  }
  
  /* Reset font size untuk print */
  .print-modal {
    font-size: 12px !important;
  }
  
  .print-modal .text-3xl {
    font-size: 18px !important;
  }
  
  .print-modal .text-xl {
    font-size: 16px !important;
  }
  
  .print-modal .text-lg {
    font-size: 14px !important;
  }
  
  .print-modal .text-sm {
    font-size: 11px !important;
  }
  
  .print-modal .text-xs {
    font-size: 10px !important;
  }
  
  /* Pastikan tidak ada duplikasi */
  .print-modal {
    page-break-after: avoid !important;
    page-break-before: avoid !important;
  }
  
  /* Pastikan hanya satu instance yang di-print */
  body {
    margin: 0 !important;
    padding: 0 !important;
  }
  
  /* Sembunyikan elemen yang tidak perlu */
  .print-modal .fixed.inset-0 {
    display: none !important;
  }
  
  /* Pastikan tidak ada duplikasi halaman */
  @page {
    margin: 0.5in;
  }
}
</style> 