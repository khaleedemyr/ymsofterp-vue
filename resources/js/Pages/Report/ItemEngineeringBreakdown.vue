<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-2 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-layer-group"></i> Item Engineering Breakdown
      </h1>
      <p class="text-sm text-gray-500 mb-6">
        Mirip Item Engineering, dengan breakdown bahan yang di-cut (BOM): qty, unit, dan cost.
      </p>

      <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8 items-end">
        <div v-if="user.id_outlet == 1">
          <label class="block text-sm font-medium mb-1">Region</label>
          <select v-model="filters.region" @change="onRegionChange" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2">
            <option value="">Pilih Region</option>
            <option v-for="region in regions" :key="region.id" :value="region.id">{{ region.name }}</option>
          </select>
        </div>
        <div v-if="user.id_outlet == 1">
          <label class="block text-sm font-medium mb-1">Outlet</label>
          <select v-model="filters.outlet" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2">
            <option value="">Pilih Outlet</option>
            <option v-for="outlet in filteredOutlets" :key="outlet.id" :value="outlet.qr_code">{{ outlet.name }}</option>
          </select>
        </div>
        <div v-else>
          <label class="block text-sm font-medium mb-1">Outlet</label>
          <input type="text" :value="userOutletName" class="block w-full rounded-lg border-gray-300 shadow-sm bg-gray-100 cursor-not-allowed px-3 py-2" readonly />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tanggal From</label>
          <input type="date" v-model="filters.date_from" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tanggal To</label>
          <input type="date" v-model="filters.date_to" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" />
        </div>
        <div class="flex items-end h-full gap-2">
          <button @click="fetchReport" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">Tampilkan</button>
          <button @click="exportExcel" :disabled="!items.length" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg shadow hover:bg-green-700 transition disabled:opacity-50">Export Excel</button>
        </div>
      </div>

      <div v-if="errorMsg" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ errorMsg }}
      </div>

      <div v-if="loading" class="text-center py-10">
        <span class="text-gray-500">Loading...</span>
      </div>

      <div v-else-if="hasLoaded">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
            <div class="text-xs font-semibold uppercase text-blue-600">Total Sales</div>
            <div class="text-xl font-bold text-blue-900">{{ formatCurrency(grandSales) }}</div>
          </div>
          <div class="rounded-xl border border-rose-100 bg-rose-50 p-4">
            <div class="text-xs font-semibold uppercase text-rose-600">Total Cost Bahan (BOM)</div>
            <div class="text-xl font-bold text-rose-900">{{ formatCurrency(grandCost) }}</div>
          </div>
          <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
            <div class="text-xs font-semibold uppercase text-emerald-600">Menu</div>
            <div class="text-xl font-bold text-emerald-900">{{ items.length }}</div>
          </div>
        </div>

        <div class="space-y-4">
          <div
            v-for="(categoryData, categoryName) in itemsByCategory"
            :key="categoryName"
            class="border border-gray-200 rounded-lg overflow-hidden shadow-sm"
          >
            <div
              class="bg-gradient-to-r from-blue-50 to-blue-100 px-6 py-4 cursor-pointer hover:bg-blue-200 transition"
              @click="toggleCategory(categoryName)"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <i
                    :class="expandedCategories[categoryName] ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"
                    class="text-blue-600"
                  />
                  <div>
                    <h3 class="font-bold text-blue-800 text-lg">{{ categoryName || 'Uncategorized' }}</h3>
                    <div class="flex flex-wrap gap-4 text-sm text-blue-700">
                      <span>Qty: <b>{{ categoryData.total_qty }}</b></span>
                      <span>Sales: <b>{{ formatCurrency(categoryData.total_subtotal) }}</b></span>
                      <span>Cost: <b>{{ formatCurrency(categoryData.total_cost) }}</b></span>
                      <span>Items: <b>{{ categoryData.items.length }}</b></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="expandedCategories[categoryName]" class="bg-white overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="bg-gray-50 border-b text-xs font-medium text-gray-500 uppercase">
                    <th class="px-4 py-3 text-left w-10"></th>
                    <th class="px-4 py-3 text-left">Nama Menu</th>
                    <th class="px-4 py-3 text-right">Qty Terjual</th>
                    <th class="px-4 py-3 text-right">Harga Jual</th>
                    <th class="px-4 py-3 text-right">Sales</th>
                    <th class="px-4 py-3 text-right">Cost/Porsi</th>
                    <th class="px-4 py-3 text-right">Total Cost</th>
                    <th class="px-4 py-3 text-center">Bahan</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <template v-for="item in categoryData.items" :key="item.item_id">
                    <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleItem(item.item_id)">
                      <td class="px-4 py-3 text-blue-600">
                        <i :class="expandedItems[item.item_id] ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'" />
                      </td>
                      <td class="px-4 py-3 font-medium text-gray-900">{{ item.item_name }}</td>
                      <td class="px-4 py-3 text-right">{{ item.qty_terjual }}</td>
                      <td class="px-4 py-3 text-right">{{ formatCurrency(item.harga_jual) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatCurrency(item.subtotal) }}</td>
                      <td class="px-4 py-3 text-right">{{ formatCurrency(item.cost_per_portion) }}</td>
                      <td class="px-4 py-3 text-right font-semibold text-rose-700">{{ formatCurrency(item.total_cost) }}</td>
                      <td class="px-4 py-3 text-center">
                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                          {{ item.cut_count }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="expandedItems[item.item_id]">
                      <td colspan="8" class="bg-slate-50 px-4 py-3">
                        <div class="rounded-lg border border-rose-100 bg-white p-3">
                          <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-rose-700">
                            Bahan di-cut (BOM) — qty = BOM × qty terjual
                          </div>
                          <div v-if="!item.cut_items?.length" class="text-sm text-gray-500 italic">
                            Tidak ada BOM / bahan stock-cut untuk menu ini.
                          </div>
                          <table v-else class="min-w-full text-sm">
                            <thead>
                              <tr class="border-b text-xs uppercase text-gray-500">
                                <th class="py-2 text-left">Bahan</th>
                                <th class="py-2 text-right">Qty BOM/Porsi</th>
                                <th class="py-2 text-right">Qty Total</th>
                                <th class="py-2 text-left">Unit</th>
                                <th class="py-2 text-right">Cost/Unit</th>
                                <th class="py-2 text-right">Cost</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="cut in item.cut_items" :key="cut.material_item_id + '-' + cut.unit" class="border-b border-gray-50">
                                <td class="py-2 font-medium text-gray-800">{{ cut.material_name }}</td>
                                <td class="py-2 text-right">{{ formatQty(cut.bom_qty) }}</td>
                                <td class="py-2 text-right">{{ formatQty(cut.qty) }}</td>
                                <td class="py-2">{{ cut.unit }}</td>
                                <td class="py-2 text-right">{{ formatCurrency(cut.unit_cost) }}</td>
                                <td class="py-2 text-right font-semibold">{{ formatCurrency(cut.cost) }}</td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div v-if="!items.length" class="text-center py-12 text-gray-500">
          Tidak ada data untuk filter ini.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import * as XLSX from 'xlsx';

const filters = reactive({
  region: '',
  outlet: '',
  date_from: '',
  date_to: '',
});
const regions = ref([]);
const outlets = ref([]);
const filteredOutlets = ref([]);
const items = ref([]);
const itemsByCategory = ref({});
const loading = ref(false);
const hasLoaded = ref(false);
const errorMsg = ref('');
const user = usePage().props.auth?.user || {};
const userOutletName = ref('');
const grandSales = ref(0);
const grandCost = ref(0);
const expandedCategories = ref({});
const expandedItems = ref({});

function formatCurrency(val) {
  const num = Number(val);
  if (!Number.isFinite(num)) return '-';
  return num.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
}

function formatQty(val) {
  const num = Number(val);
  if (!Number.isFinite(num)) return '-';
  return num.toLocaleString('id-ID', { maximumFractionDigits: 4 });
}

function toggleCategory(name) {
  expandedCategories.value[name] = !expandedCategories.value[name];
}

function toggleItem(id) {
  expandedItems.value[id] = !expandedItems.value[id];
}

const fetchRegions = async () => {
  const res = await axios.get('/api/regions');
  regions.value = res.data.regions || [];
};

const fetchOutlets = async () => {
  const res = await axios.get('/api/outlets/report');
  outlets.value = res.data.outlets || [];
  filteredOutlets.value = outlets.value;
};

const onRegionChange = () => {
  if (filters.region) {
    filteredOutlets.value = outlets.value.filter((o) => o.region_id == filters.region);
  } else {
    filteredOutlets.value = outlets.value;
  }
  filters.outlet = '';
};

const fetchMyOutletQr = async () => {
  const res = await axios.get('/api/my-outlet-qr');
  if (res.data.qr_code) filters.outlet = res.data.qr_code;
  if (res.data.outlet_name) userOutletName.value = res.data.outlet_name;
};

const fetchReport = async () => {
  errorMsg.value = '';
  if (!filters.outlet) {
    errorMsg.value = 'Pilih outlet dulu — cost bahan dihitung per outlet.';
    return;
  }
  loading.value = true;
  hasLoaded.value = false;
  try {
    const res = await axios.get('/api/report/item-engineering-breakdown', { params: filters });
    items.value = res.data.items || [];
    itemsByCategory.value = res.data.items_by_category || {};
    grandSales.value = res.data.grand_total_sales || 0;
    grandCost.value = res.data.grand_total_cost || 0;
    expandedItems.value = {};
    const cats = Object.keys(itemsByCategory.value);
    expandedCategories.value = {};
    if (cats.length) expandedCategories.value[cats[0]] = true;
    hasLoaded.value = true;
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || 'Gagal memuat report.';
    items.value = [];
    itemsByCategory.value = {};
    hasLoaded.value = true;
  } finally {
    loading.value = false;
  }
};

const exportExcel = () => {
  const rows = [['Category', 'Menu', 'Qty Terjual', 'Harga Jual', 'Sales', 'Cost/Porsi', 'Total Cost', 'Bahan', 'Qty BOM/Porsi', 'Qty Total', 'Unit', 'Cost/Unit', 'Cost Bahan']];
  for (const item of items.value) {
    if (!item.cut_items?.length) {
      rows.push([
        item.category_name,
        item.item_name,
        item.qty_terjual,
        item.harga_jual,
        item.subtotal,
        item.cost_per_portion,
        item.total_cost,
        '-',
        '',
        '',
        '',
        '',
        '',
      ]);
      continue;
    }
    for (const cut of item.cut_items) {
      rows.push([
        item.category_name,
        item.item_name,
        item.qty_terjual,
        item.harga_jual,
        item.subtotal,
        item.cost_per_portion,
        item.total_cost,
        cut.material_name,
        cut.bom_qty,
        cut.qty,
        cut.unit,
        cut.unit_cost,
        cut.cost,
      ]);
    }
  }
  const ws = XLSX.utils.aoa_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Breakdown');
  XLSX.writeFile(wb, `item-engineering-breakdown_${filters.date_from || 'all'}_${filters.date_to || 'all'}.xlsx`);
};

onMounted(async () => {
  if (user.id_outlet == 1) {
    await fetchRegions();
    await fetchOutlets();
  } else {
    await fetchMyOutletQr();
  }
});
</script>
