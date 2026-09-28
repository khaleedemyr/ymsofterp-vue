<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-4 px-4">
      <h1 class="text-2xl font-bold mb-2 text-gray-900">Track Penjualan Item Warehouse</h1>
      <p class="text-sm text-gray-500 mb-6">
        Total &amp; tren bulanan dari penjualan antar gudang, retail warehouse, dan distribusi ke outlet (Delivery Order).
      </p>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Dari</label>
            <input v-model="dateFrom" type="date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[140px] focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Sampai</label>
            <input v-model="dateTo" type="date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[140px] focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div class="min-w-[200px]">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Gudang Sumber</label>
            <select v-model="warehouseId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="">Semua Gudang</option>
              <option v-for="w in warehouses" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
            </select>
          </div>
          <div class="min-w-[260px] flex-1">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Item</label>
            <Multiselect
              v-model="selectedItem"
              :options="itemOptions"
              :multiple="false"
              :searchable="true"
              :allow-empty="true"
              label="name"
              track-by="id"
              placeholder="Semua item"
              class="text-sm"
            />
          </div>
          <div class="min-w-[180px]">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Cari Nama Item</label>
            <input v-model="search" type="text" placeholder="Filter nama..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" />
          </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-4">
          <span class="text-xs font-semibold text-gray-500 uppercase">Sumber</span>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input v-model="sources.antar_gudang" type="checkbox" class="rounded border-gray-300 text-blue-600" />
            Antar Gudang
          </label>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input v-model="sources.retail" type="checkbox" class="rounded border-gray-300 text-blue-600" />
            Retail
          </label>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input v-model="sources.outlet_gr" type="checkbox" class="rounded border-gray-300 text-blue-600" />
            Distribusi Outlet
          </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
          <button type="button" @click="applyPreset(1)" class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Bulan Ini</button>
          <button type="button" @click="applyPreset(3)" class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 bg-white hover:bg-gray-50">3 Bulan</button>
          <button type="button" @click="applyPreset(6)" class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 bg-white hover:bg-gray-50">6 Bulan</button>
          <div class="flex-1"></div>
          <button
            type="button"
            @click="exportToExcel"
            :disabled="!loaded"
            class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i class="fas fa-file-excel mr-2"></i>
            Export Excel
          </button>
          <button
            type="button"
            @click="reloadData"
            :disabled="loadingReload || !canLoad"
            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
            <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
            Load Data
          </button>
        </div>
        <p v-if="!canLoad" class="mt-2 text-xs text-amber-600">Pilih rentang tanggal dan minimal satu sumber data.</p>
      </div>

      <div v-if="!loaded && !loadingReload" class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center text-gray-500">
        Atur filter lalu klik <strong>Load Data</strong>.
      </div>

      <template v-if="loaded && summary">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-xl border border-blue-100 shadow-sm p-4">
            <div class="text-xs font-semibold uppercase text-blue-600 mb-1">Total Qty</div>
            <div class="text-2xl font-bold text-gray-900">{{ formatQty(summary.total_qty) }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ formatRupiah(summary.total_value) }}</div>
          </div>
          <div class="bg-white rounded-xl border border-indigo-100 shadow-sm p-4">
            <div class="text-xs font-semibold uppercase text-indigo-600 mb-1">Antar Gudang</div>
            <div class="text-2xl font-bold text-gray-900">{{ formatQty(summary.antar_gudang_qty) }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ formatRupiah(summary.antar_gudang_value) }}</div>
          </div>
          <div class="bg-white rounded-xl border border-emerald-100 shadow-sm p-4">
            <div class="text-xs font-semibold uppercase text-emerald-600 mb-1">Retail</div>
            <div class="text-2xl font-bold text-gray-900">{{ formatQty(summary.retail_qty) }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ formatRupiah(summary.retail_value) }}</div>
          </div>
          <div class="bg-white rounded-xl border border-amber-100 shadow-sm p-4">
            <div class="text-xs font-semibold uppercase text-amber-600 mb-1">Distribusi Outlet</div>
            <div class="text-2xl font-bold text-gray-900">{{ formatQty(summary.outlet_gr_qty) }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ formatRupiah(summary.outlet_gr_value) }}</div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6" v-if="monthly.length">
          <h2 class="text-lg font-semibold text-gray-900 mb-1">Tren Qty per Bulan</h2>
          <p class="text-sm text-gray-500 mb-4">Breakdown qty (unit kecil) dari ketiga sumber</p>
          <VueApexCharts type="bar" height="360" :options="chartOptions" :series="chartSeries" />
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto mb-6" v-if="monthly.length">
          <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Breakdown Bulanan</h2>
          </div>
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-slate-100 text-gray-800">
                <th class="px-4 py-2 text-left border-b">Bulan</th>
                <th class="px-4 py-2 text-right border-b">Qty Total</th>
                <th class="px-4 py-2 text-right border-b">Nilai Total</th>
                <th class="px-4 py-2 text-right border-b">Qty Antar Gudang</th>
                <th class="px-4 py-2 text-right border-b">Qty Retail</th>
                <th class="px-4 py-2 text-right border-b">Qty Distribusi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in monthly" :key="row.month" class="border-b border-gray-100 hover:bg-gray-50">
                <td class="px-4 py-2 font-medium">{{ formatMonth(row.month) }}</td>
                <td class="px-4 py-2 text-right">{{ formatQty(row.qty) }}</td>
                <td class="px-4 py-2 text-right">{{ formatRupiah(row.value) }}</td>
                <td class="px-4 py-2 text-right">{{ formatQty(row.antar_gudang_qty) }}</td>
                <td class="px-4 py-2 text-right">{{ formatQty(row.retail_qty) }}</td>
                <td class="px-4 py-2 text-right">{{ formatQty(row.outlet_gr_qty) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
          <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900">Detail per Item</h2>
            <span class="text-xs text-gray-500">{{ items.length }} item</span>
          </div>
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-yellow-200 text-gray-900">
                <th class="px-4 py-2 text-left border-b">Item</th>
                <th class="px-4 py-2 text-left border-b">Unit</th>
                <th class="px-4 py-2 text-right border-b">Qty Total</th>
                <th class="px-4 py-2 text-right border-b">Nilai Total</th>
                <th class="px-4 py-2 text-right border-b">Antar Gudang</th>
                <th class="px-4 py-2 text-right border-b">Retail</th>
                <th class="px-4 py-2 text-right border-b">Distribusi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!items.length">
                <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data untuk filter ini.</td>
              </tr>
              <tr v-for="row in items" :key="row.item_id" class="border-b border-gray-100 hover:bg-gray-50">
                <td class="px-4 py-2">{{ row.item_name }}</td>
                <td class="px-4 py-2">{{ row.unit_name }}</td>
                <td class="px-4 py-2 text-right font-medium">{{ formatQty(row.qty) }}</td>
                <td class="px-4 py-2 text-right">{{ formatRupiah(row.value) }}</td>
                <td class="px-4 py-2 text-right">{{ formatQty(row.antar_gudang_qty) }}</td>
                <td class="px-4 py-2 text-right">{{ formatQty(row.retail_qty) }}</td>
                <td class="px-4 py-2 text-right">{{ formatQty(row.outlet_gr_qty) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import VueApexCharts from 'vue3-apexcharts';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  warehouses: { type: Array, default: () => [] },
  itemOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: null },
  monthly: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  loaded: { type: Boolean, default: false },
});

const dateFrom = ref(props.filters?.dateFrom || '');
const dateTo = ref(props.filters?.dateTo || '');
const warehouseId = ref(props.filters?.warehouse_id ? String(props.filters.warehouse_id) : '');
const search = ref(props.filters?.search || '');
const loadingReload = ref(false);

const initialSources = String(props.filters?.sources || 'antar_gudang,retail,outlet_gr').split(',');
const sources = ref({
  antar_gudang: initialSources.includes('antar_gudang'),
  retail: initialSources.includes('retail'),
  outlet_gr: initialSources.includes('outlet_gr'),
});

const selectedItem = ref(null);
if (props.filters?.item_id) {
  selectedItem.value = props.itemOptions.find((i) => String(i.id) === String(props.filters.item_id)) || null;
}

const sourcesParam = computed(() => {
  const list = [];
  if (sources.value.antar_gudang) list.push('antar_gudang');
  if (sources.value.retail) list.push('retail');
  if (sources.value.outlet_gr) list.push('outlet_gr');
  return list.join(',');
});

const canLoad = computed(() => Boolean(dateFrom.value && dateTo.value && sourcesParam.value));

const chartSeries = computed(() => [
  { name: 'Antar Gudang', data: props.monthly.map((m) => Number(m.antar_gudang_qty) || 0) },
  { name: 'Retail', data: props.monthly.map((m) => Number(m.retail_qty) || 0) },
  { name: 'Distribusi Outlet', data: props.monthly.map((m) => Number(m.outlet_gr_qty) || 0) },
]);

const chartOptions = computed(() => ({
  chart: {
    type: 'bar',
    stacked: true,
    toolbar: { show: false },
    fontFamily: 'inherit',
  },
  plotOptions: {
    bar: { horizontal: false, borderRadius: 4, columnWidth: '55%' },
  },
  dataLabels: { enabled: false },
  stroke: { show: true, width: 1, colors: ['#fff'] },
  xaxis: {
    categories: props.monthly.map((m) => formatMonth(m.month)),
    labels: { style: { fontSize: '12px' } },
  },
  yaxis: {
    labels: {
      formatter: (val) => formatQty(val),
    },
  },
  legend: { position: 'top' },
  colors: ['#4F46E5', '#059669', '#D97706'],
  tooltip: {
    y: {
      formatter: (val) => formatQty(val),
    },
  },
  grid: { borderColor: '#E5E7EB' },
}));

function pad(n) {
  return String(n).padStart(2, '0');
}

function toDateInput(d) {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function applyPreset(months) {
  const to = new Date();
  const from = new Date(to.getFullYear(), to.getMonth() - (months - 1), 1);
  dateFrom.value = toDateInput(from);
  dateTo.value = toDateInput(to);
}

function queryParams() {
  return {
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    warehouse_id: warehouseId.value || '',
    item_id: selectedItem.value?.id || '',
    sources: sourcesParam.value,
    search: search.value || '',
    load: 1,
  };
}

function reloadData() {
  if (!canLoad.value) return;
  loadingReload.value = true;
  router.get('/report-warehouse-item-sales', queryParams(), {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loadingReload.value = false;
    },
  });
}

function exportToExcel() {
  if (!props.loaded) return;
  const params = new URLSearchParams(queryParams());
  window.open(`/report-warehouse-item-sales/export?${params.toString()}`, '_blank');
}

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(Number(value) || 0);
}

function formatQty(value) {
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(Number(value) || 0);
}

function formatMonth(monthKey) {
  if (!monthKey || typeof monthKey !== 'string') return monthKey || '-';
  const [y, m] = monthKey.split('-');
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const idx = Number(m) - 1;
  return `${months[idx] || m} ${y}`;
}
</script>
