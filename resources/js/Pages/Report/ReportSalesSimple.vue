<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-chart-line"></i> Sales Report
      </h1>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-6 mb-8">
        <div class="summary-card gradient-blue">
          <div class="summary-label">Total Sales</div>
          <div class="summary-value">{{ formatCurrency(report.summary.total_sales) }}</div>
        </div>
        <div class="summary-card gradient-darkblue">
          <div class="summary-label">Grand Total</div>
          <div class="summary-value">{{ formatCurrency(report.summary.grand_total) }}</div>
        </div>
        <div class="summary-card gradient-green">
          <div class="summary-label">Total Order</div>
          <div class="summary-value">{{ report.summary.total_order }}</div>
        </div>
        <div class="summary-card gradient-yellow">
          <div class="summary-label">Total Pax</div>
          <div class="summary-value">{{ report.summary.total_pax }}</div>
        </div>
        <div class="summary-card gradient-pink">
          <div class="summary-label">Discount</div>
          <div class="summary-value">{{ formatCurrency(report.summary.total_discount) }}</div>
        </div>
        <div class="summary-card gradient-purple">
          <div class="summary-label">Cashback/Redeem</div>
          <div class="summary-value">{{ formatCurrency(report.summary.total_cashback) }}</div>
        </div>
        <div v-if="user.id_outlet == 1" class="summary-card gradient-orange">
          <div class="summary-label">Service Charge</div>
          <div class="summary-value">{{ formatCurrency(report.summary.total_service) }}</div>
        </div>
        <div v-if="user.id_outlet == 1" class="summary-card gradient-indigo">
          <div class="summary-label">PB1</div>
          <div class="summary-value">{{ formatCurrency(report.summary.total_pb1) }}</div>
        </div>
        <div class="summary-card gradient-gray">
          <div class="summary-label">Commfee</div>
          <div class="summary-value">{{ formatCurrency(report.summary.total_commfee) }}</div>
        </div>
        <div class="summary-card gradient-lightgray">
          <div class="summary-label">Rounding</div>
          <div class="summary-value">{{ formatCurrency(report.summary.total_rounding) }}</div>
        </div>
        <div class="summary-card gradient-white border-t-4 border-blue-600">
          <div class="summary-label text-xs font-semibold mb-1">Avg Check</div>
          <div class="summary-value text-lg">{{ formatCurrency(avgCheckSummary) }}</div>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 items-end">
        <div>
          <label class="block text-sm font-medium mb-1">Outlet</label>
          <select v-model="filters.outlet" :disabled="!outletDropdownEnabled" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" required>
            <option value="">Pilih Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.qr_code">{{ outlet.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tanggal From</label>
          <input type="date" v-model="filters.date_from" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tanggal To</label>
          <input type="date" v-model="filters.date_to" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" />
        </div>
        <div class="flex items-end h-full">
          <button @click="fetchReport" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">Tampilkan</button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-10">
        <span class="text-gray-500">Loading...</span>
      </div>

      <div v-else-if="showReport">
        <h2 class="font-semibold mb-2 text-lg text-gray-700">Breakdown per Hari</h2>
        <div class="overflow-x-auto mb-8">
          <table class="min-w-full rounded-2xl overflow-hidden shadow-lg">
            <thead>
              <tr class="bg-[#2563eb] text-white font-bold text-base">
                <th class="px-6 py-3 text-left"></th>
                <th class="px-6 py-3 text-left">Tanggal</th>
                <th class="px-6 py-3 text-right">Total Order</th>
                <th class="px-6 py-3 text-right">Total Pax</th>
                <th class="px-6 py-3 text-right">Avg Check</th>
                <th class="px-6 py-3 text-right">Discount</th>
                <th class="px-6 py-3 text-right">Cashback/Redeem</th>
                <th v-if="user.id_outlet == 1" class="px-6 py-3 text-right">Service</th>
                <th v-if="user.id_outlet == 1" class="px-6 py-3 text-right">PB1</th>
                <th class="px-6 py-3 text-right">Commfee</th>
                <th class="px-6 py-3 text-right">Rounding</th>
                <th class="px-6 py-3 text-right">Total Sales</th>
                <th class="px-6 py-3 text-right">Grand Total</th>
                <th class="px-6 py-3 text-center">EOD</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, tanggal) in report.per_day" :key="tanggal">
                <tr class="bg-white border-b last:border-b-0 hover:bg-blue-50 transition">
                  <td class="px-2 py-3 text-center">
                    <button @click="toggleExpand(tanggal)" class="focus:outline-none">
                      <i :class="expanded[tanggal] ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"></i>
                    </button>
                  </td>
                  <td class="px-6 py-3 font-semibold text-gray-800">{{ tanggal }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ row.total_order }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ row.total_pax }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.avg_check ?? calcAvgCheck(row.grand_total, row.total_pax)) }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.total_discount) }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.total_cashback) }}</td>
                  <td v-if="user.id_outlet == 1" class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.total_service) }}</td>
                  <td v-if="user.id_outlet == 1" class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.total_pb1) }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.total_commfee) }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.total_rounding) }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.total_sales) }}</td>
                  <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(row.grand_total) }}</td>
                  <td class="px-6 py-3 text-center">
                    <div class="flex flex-row justify-end items-center gap-2">
                      <button @click="openEodModal(row, tanggal)" title="EOD" class="bg-blue-600 text-white p-2 rounded-lg shadow hover:bg-blue-700 transition font-bold text-sm">
                        <i class="fa-solid fa-file-invoice"></i>
                      </button>
                      <button @click="openRevenueReport(tanggal)" title="Revenue Report" class="bg-orange-500 text-white p-2 rounded-lg shadow hover:bg-orange-600 transition font-bold text-sm">
                        <i class="fa-solid fa-coins"></i>
                      </button>
                      <button @click="openPerModeModal(tanggal)" title="Mode" class="bg-green-600 text-white p-2 rounded-lg shadow hover:bg-green-700 transition font-bold text-sm">
                        <i class="fa-solid fa-layer-group"></i>
                      </button>
                      <button @click="exportOrderDetail(tanggal)" title="Export" class="bg-yellow-500 text-white p-2 rounded-lg shadow hover:bg-yellow-600 transition font-bold text-sm">
                        <i class="fa-solid fa-file-excel"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="expanded[tanggal]">
                  <td :colspan="user.id_outlet == 1 ? 14 : 12" class="bg-blue-50 px-8 py-4">
                    <div v-if="ordersByDate(tanggal).length">
                      <div class="font-bold mb-2 text-gray-700">Detail Order ({{ tanggal }})</div>
                      <div class="overflow-x-auto">
                        <table class="min-w-full text-sm rounded shadow">
                          <thead>
                            <tr class="bg-blue-200 text-blue-900">
                              <th class="px-3 py-2">No</th>
                              <th class="px-3 py-2">Nomor Order</th>
                              <th class="px-3 py-2">Table</th>
                              <th class="px-3 py-2">Pax</th>
                              <th class="px-3 py-2">Total</th>
                              <th class="px-3 py-2">Discount</th>
                              <th class="px-3 py-2">Cashback</th>
                              <th class="px-3 py-2">Service</th>
                              <th class="px-3 py-2">PB1</th>
                              <th class="px-3 py-2">Grand Total</th>
                              <th class="px-3 py-2">Status</th>
                              <th class="px-3 py-2">Detail</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(order, idx) in ordersByDate(tanggal)" :key="order.id" class="bg-white border-b last:border-b-0 hover:bg-blue-100">
                              <td class="px-3 py-2">{{ idx + 1 }}</td>
                              <td class="px-3 py-2">{{ order.nomor }}</td>
                              <td class="px-3 py-2">{{ order.table }}</td>
                              <td class="px-3 py-2">{{ order.pax }}</td>
                              <td class="px-3 py-2 text-right">{{ formatCurrency(order.total) }}</td>
                              <td class="px-3 py-2 text-right">{{ formatCurrency(calculateDiscount(order)) }}</td>
                              <td class="px-3 py-2 text-right">{{ formatCurrency(order.cashback) }}</td>
                              <td class="px-3 py-2 text-right">{{ formatCurrency(order.service) }}</td>
                              <td class="px-3 py-2 text-right">{{ formatCurrency(order.pb1) }}</td>
                              <td class="px-3 py-2 text-right">{{ formatCurrency(order.grand_total) }}</td>
                              <td class="px-3 py-2">{{ order.status }}</td>
                              <td class="px-3 py-2 text-center">
                                <button @click="openOrderDetail(order)" class="bg-blue-500 text-white px-3 py-1 rounded shadow hover:bg-blue-700 transition text-xs font-bold">
                                  <i class="fa-solid fa-eye"></i> Detail
                                </button>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <div v-else class="text-gray-400 italic">Tidak ada order pada hari ini.</div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <EodModal v-if="showEodModal" :summary="selectedEodRow" :show="showEodModal" @close="showEodModal = false" />
        <OrderDetailModal v-if="showOrderDetailModal" :order="selectedOrderDetail" @close="showOrderDetailModal = false" />
        <PerModeModal v-if="showPerModeModal" :tanggal="selectedPerModeTanggal" :orders="ordersByDate(selectedPerModeTanggal)" @close="showPerModeModal = false" />
        <RevenueReportModal v-if="showRevenueReportModal" :tanggal="selectedRevenueTanggal" :orders="selectedRevenueOrders" :outlets="outlets" :outlet-filter="filters.outlet" @close="showRevenueReportModal = false" />
      </div>
    </div>
  </div>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const filters = reactive({
  outlet: '',
  date_from: '',
  date_to: '',
});
const outlets = ref([]);
const report = reactive({
  summary: { total_sales: 0, total_order: 0, total_pax: 0 },
  per_day: {},
  orders: [],
});
const loading = ref(false);
const showReport = ref(false);
const showEodModal = ref(false);
const selectedEodRow = ref({});
const expanded = reactive({});
const showOrderDetailModal = ref(false);
const selectedOrderDetail = ref({});
const user = usePage().props.auth?.user || {};
const outletDropdownEnabled = ref(false);
const showPerModeModal = ref(false);
const selectedPerModeTanggal = ref(null);
const showRevenueReportModal = ref(false);
const selectedRevenueOrders = ref([]);
const selectedRevenueTanggal = ref('');

const avgCheckSummary = computed(() => {
  const grandTotal = report.summary.grand_total || 0;
  const pax = report.summary.total_pax || 0;
  return pax > 0 ? Math.round(grandTotal / pax) : 0;
});

function calcAvgCheck(grandTotal, pax) {
  return pax > 0 ? Math.round(grandTotal / pax) : 0;
}

function toggleExpand(tanggal) {
  expanded[tanggal] = !expanded[tanggal];
}

function ordersByDate(tanggal) {
  // Ambil order yang tanggalnya sama persis (YYYY-MM-DD)
  return (report.orders || []).filter(o => o.created_at && o.created_at.startsWith(tanggal));
}

const fetchOutlets = async () => {
  const res = await axios.get('/api/outlets/report');
  outlets.value = res.data.outlets || [];
};

const fetchMyOutletQr = async () => {
  const res = await axios.get('/api/my-outlet-qr');
  if (res.data.qr_code) {
    filters.outlet = res.data.qr_code;
  }
};

const fetchReport = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/report/sales-simple', { params: filters });
    Object.assign(report.summary, res.data.summary || {});
    report.per_day = res.data.per_day || {};
    report.orders = res.data.orders || [];
    showReport.value = true;
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  if (user.id_outlet == 1) {
    outletDropdownEnabled.value = true;
    await fetchOutlets();
  } else {
    outletDropdownEnabled.value = false;
    await fetchMyOutletQr();
    // Tetap fetch outlets untuk user non-superuser agar RevenueReportModal bisa mengakses outlet data
    await fetchOutlets();
  }
});

const formatCurrency = (val) => {
  if (typeof val !== 'number') val = Number(val) || 0;
  return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
};

function calculateDiscount(order) {
  // Pastikan konversi yang aman
  const discount = Number(order.discount) || 0;
  const manualDiscount = Number(order.manual_discount_amount) || 0;
  
  console.log('DEBUG DISCOUNT:', {
    orderId: order.id,
    nomor: order.nomor,
    discount: order.discount,
    manualDiscount: order.manual_discount_amount,
    discountParsed: discount,
    manualDiscountParsed: manualDiscount
  });
  
  // Jika keduanya > 0, ambil yang terbesar
  if (discount > 0 && manualDiscount > 0) {
    return Math.max(discount, manualDiscount);
  }
  // Jika hanya salah satu yang > 0, gunakan yang ada
  return discount + manualDiscount;
}

function openEodModal(row, tanggal) {
  selectedEodRow.value = {
    ...row,
    tanggal,
    nama_outlet: report.orders[0]?.nama_outlet || '',
  };
  showEodModal.value = true;
}

function openOrderDetail(order) {
  selectedOrderDetail.value = order;
  showOrderDetailModal.value = true;
}

function openPerModeModal(tanggal) {
  selectedPerModeTanggal.value = tanggal;
  showPerModeModal.value = true;
}

function openRevenueReport(tanggal) {
  selectedRevenueTanggal.value = tanggal;
  selectedRevenueOrders.value = ordersByDate(tanggal);
  showRevenueReportModal.value = true;
}

function exportOrderDetail(tanggal) {
  const params = {
    outlet: filters.outlet,
    date: tanggal,
    date_from: filters.date_from,
    date_to: filters.date_to,
  };
  const query = Object.entries(params).map(([k, v]) => `${k}=${encodeURIComponent(v||'')}`).join('&');
  window.open(`/report/sales-simple/export-order-detail?${query}`, '_blank');
}

import { defineAsyncComponent } from 'vue';
const EodModal = defineAsyncComponent(() => import('./EodModal.vue'));
const OrderDetailModal = defineAsyncComponent(() => import('./OrderDetailModal.vue'));
const PerModeModal = defineAsyncComponent(() => import('./PerModeModal.vue'));
const RevenueReportModal = defineAsyncComponent(() => import('./RevenueReportModal.vue'));
</script>

<style scoped>
.summary-card {
  @apply rounded-2xl shadow-xl p-6 flex flex-col items-center transition-transform duration-200 cursor-pointer;
  min-width: 160px;
  min-height: 110px;
  box-shadow: 0 8px 24px 0 rgba(0,0,0,0.10), 0 1.5px 4px 0 rgba(0,0,0,0.08);
  border: none;
}
.summary-card:hover {
  transform: translateY(-8px) scale(1.04);
  box-shadow: 0 16px 32px 0 rgba(0,0,0,0.14), 0 3px 8px 0 rgba(0,0,0,0.10);
}
.summary-label {
  font-size: 0.95rem;
  font-weight: 600;
  color: #222;
  margin-bottom: 0.5rem;
  text-shadow: 0 1px 2px rgba(255,255,255,0.15);
}
.summary-value {
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -1px;
  color: #1e293b;
  text-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.gradient-blue {
  background: linear-gradient(135deg, #e0eaff 0%, #b6d0ff 100%);
}
.gradient-green {
  background: linear-gradient(135deg, #e6ffe6 0%, #b2f2b2 100%);
}
.gradient-yellow {
  background: linear-gradient(135deg, #fffbe6 0%, #ffe9b2 100%);
}
.gradient-pink {
  background: linear-gradient(135deg, #ffe6f0 0%, #ffb2d6 100%);
}

.gradient-purple {
  background: linear-gradient(135deg, #f3e6ff 0%, #d1b2ff 100%);
}
.gradient-orange {
  background: linear-gradient(135deg, #fff3e6 0%, #ffd1b2 100%);
}
.gradient-indigo {
  background: linear-gradient(135deg, #e6eaff 0%, #b2baff 100%);
}
.gradient-gray {
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
}
.gradient-lightgray {
  background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
}
.gradient-white {
  background: linear-gradient(135deg, #fff 0%, #f3f4f6 100%);
}
.gradient-darkblue {
  background: linear-gradient(135deg, #dbeafe 0%, #1e3a8a 100%);
}
</style> 