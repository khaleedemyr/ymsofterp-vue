<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-cogs"></i> Item Engineering
      </h1>
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
          <button @click="exportExcel" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg shadow hover:bg-green-700 transition">Export to Excel</button>
        </div>
      </div>
      <div v-if="loading" class="text-center py-10">
        <span class="text-gray-500">Loading...</span>
      </div>
      <div v-else>
        <!-- View Mode & Sort Controls -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-600">Tampilan:</span>
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden">
              <button @click="viewMode = 'grouped'"
                      :class="['px-3 py-2 text-sm font-medium flex items-center gap-1 transition', viewMode === 'grouped' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100']">
                <i class="fa-solid fa-table-cells"></i> Card
              </button>
              <button @click="viewMode = 'list'"
                      :class="['px-3 py-2 text-sm font-medium flex items-center gap-1 transition border-l border-gray-300', viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100']">
                <i class="fa-solid fa-table-list"></i> List (Excel)
              </button>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-600">Sort by:</span>
            <select v-model="sortField" class="rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm">
              <option value="item_name">Nama Item</option>
              <option value="category_name">Kategori</option>
              <option value="qty_terjual">Qty Terjual</option>
              <option value="harga_jual">Harga Jual</option>
              <option value="subtotal">Subtotal</option>
            </select>
            <button @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'"
                    class="px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-100 text-sm flex items-center gap-1">
              <i :class="sortOrder === 'asc' ? 'fa-solid fa-arrow-up-short-wide' : 'fa-solid fa-arrow-down-wide-short'"></i>
              {{ sortOrder === 'asc' ? 'Asc' : 'Desc' }}
            </button>
          </div>
        </div>

        <!-- Category Grouping (Card) View -->
        <div v-if="viewMode === 'grouped'" class="mb-8">
          <h2 class="text-lg font-bold text-blue-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list"></i> Item Engineering by Category
          </h2>
          <div class="space-y-4">
            <div v-for="(categoryData, categoryName) in sortedItemsByCategory" :key="categoryName" 
                 class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
              <!-- Category Header -->
              <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-6 py-4 cursor-pointer hover:bg-blue-200 transition"
                   @click="toggleCategory(categoryName)">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-4">
                    <i :class="expandedCategories[categoryName] ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'" 
                       class="text-blue-600"></i>
                    <div>
                      <h3 class="font-bold text-blue-800 text-lg">{{ categoryName || 'Uncategorized' }}</h3>
                      <div class="flex gap-6 text-sm text-blue-700">
                        <span>Total Qty: <span class="font-semibold">{{ categoryData.total_qty }}</span></span>
                        <span>Total Sales: <span class="font-semibold">{{ formatCurrency(categoryData.total_subtotal) }}</span></span>
                        <span>Items: <span class="font-semibold">{{ categoryData.items.length }}</span></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Category Items (Collapsible) -->
              <div v-if="expandedCategories[categoryName]" class="bg-white">
                <div class="overflow-x-auto">
                  <table class="min-w-full">
                    <thead>
                      <tr class="bg-gray-50 border-b">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Item</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Terjual</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr v-for="(item, idx) in categoryData.items" :key="item.item_name" 
                          class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ idx + 1 }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ item.qty_terjual }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatCurrency(item.harga_jual) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
                      </tr>
                      <!-- Category Total Row -->
                      <tr class="bg-blue-50 border-t-2 border-blue-200">
                        <td colspan="4" class="px-6 py-3 text-right font-bold text-blue-800">Category Total</td>
                        <td class="px-6 py-3 text-right font-bold text-blue-800">{{ formatCurrency(categoryData.total_subtotal) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Flat List (Excel-like) View -->
        <div v-else class="mb-8">
          <h2 class="text-lg font-bold text-blue-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-table-list"></i> Item Engineering (List View)
          </h2>
          <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
            <table class="min-w-full border-collapse">
              <thead>
                <tr class="bg-gray-100 border-b">
                  <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase border-r border-gray-200">No</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase border-r border-gray-200 cursor-pointer select-none" @click="setSort('category_name')">
                    Kategori <i v-if="sortField==='category_name'" :class="sortOrder==='asc' ? 'fa-solid fa-caret-up' : 'fa-solid fa-caret-down'"></i>
                  </th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase border-r border-gray-200 cursor-pointer select-none" @click="setSort('item_name')">
                    Nama Item <i v-if="sortField==='item_name'" :class="sortOrder==='asc' ? 'fa-solid fa-caret-up' : 'fa-solid fa-caret-down'"></i>
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase border-r border-gray-200 cursor-pointer select-none" @click="setSort('qty_terjual')">
                    Qty Terjual <i v-if="sortField==='qty_terjual'" :class="sortOrder==='asc' ? 'fa-solid fa-caret-up' : 'fa-solid fa-caret-down'"></i>
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase border-r border-gray-200 cursor-pointer select-none" @click="setSort('harga_jual')">
                    Harga Jual <i v-if="sortField==='harga_jual'" :class="sortOrder==='asc' ? 'fa-solid fa-caret-up' : 'fa-solid fa-caret-down'"></i>
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase cursor-pointer select-none" @click="setSort('subtotal')">
                    Subtotal <i v-if="sortField==='subtotal'" :class="sortOrder==='asc' ? 'fa-solid fa-caret-up' : 'fa-solid fa-caret-down'"></i>
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <tr v-for="(item, idx) in flatSortedItems" :key="item.category_name + '-' + item.item_name" class="hover:bg-blue-50 transition">
                  <td class="px-4 py-2 text-sm text-gray-700 border-r border-gray-100">{{ idx + 1 }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 border-r border-gray-100">{{ item.category_name || 'Uncategorized' }}</td>
                  <td class="px-4 py-2 text-sm font-medium text-gray-900 border-r border-gray-100">{{ item.item_name }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 text-right border-r border-gray-100">{{ item.qty_terjual }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 text-right border-r border-gray-100">{{ formatCurrency(item.harga_jual) }}</td>
                  <td class="px-4 py-2 text-sm text-gray-900 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
                </tr>
                <tr v-if="flatSortedItems.length === 0">
                  <td colspan="6" class="px-4 py-6 text-center text-gray-400">Tidak ada data</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Grand Total Summary -->
        <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-6 mb-8 border border-green-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-green-800">Grand Total Summary</h3>
            <div class="text-right">
              <div class="text-2xl font-bold text-green-800">{{ formatCurrency(grandTotal) }}</div>
              <div class="text-sm text-green-600">Total dari semua categories</div>
            </div>
          </div>
        </div>

        <!-- MODIFIER ENGINEERING TABLE -->
        <div class="overflow-x-auto mb-8">
          <h2 class="text-lg font-bold text-blue-800 mb-2 flex items-center gap-2"><i class="fa-solid fa-gears"></i> Modifier Engineering</h2>
          <table class="min-w-full rounded-2xl overflow-hidden shadow-lg">
            <thead>
              <tr class="bg-[#2563eb] text-white font-bold text-base">
                <th class="px-6 py-3 text-left">No</th>
                <th class="px-6 py-3 text-left">Nama Modifier</th>
                <th class="px-6 py-3 text-right">Qty Terjual</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(mod, idx) in modifiers" :key="mod.name" class="bg-white border-b last:border-b-0 hover:bg-blue-50 transition">
                <td class="px-6 py-3">{{ idx + 1 }}</td>
                <td class="px-6 py-3 font-semibold text-gray-800">{{ mod.name }}</td>
                <td class="px-6 py-3 text-right font-semibold">{{ mod.qty }}</td>
              </tr>
            </tbody>
          </table>
        </div>
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
const modifiers = ref([]);
const loading = ref(false);
const user = usePage().props.auth?.user || {};
const userOutletName = ref('');
const grand_total = ref(0);
const grandTotal = computed(() => grand_total.value);
const expandedCategories = ref({});

const viewMode = ref('grouped'); // 'grouped' (card) or 'list' (excel-like)
const sortField = ref('qty_terjual');
const sortOrder = ref('desc');

function setSort(field) {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortField.value = field;
    sortOrder.value = 'desc';
  }
}

function compareItems(a, b) {
  let valA = a[sortField.value];
  let valB = b[sortField.value];
  if (typeof valA === 'string' || typeof valB === 'string') {
    valA = (valA ?? '').toString().toLowerCase();
    valB = (valB ?? '').toString().toLowerCase();
    if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
    if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
    return 0;
  }
  const numA = Number(valA) || 0;
  const numB = Number(valB) || 0;
  return sortOrder.value === 'asc' ? numA - numB : numB - numA;
}

const sortedItemsByCategory = computed(() => {
  const result = {};
  for (const [categoryName, categoryData] of Object.entries(itemsByCategory.value)) {
    result[categoryName] = {
      ...categoryData,
      items: [...categoryData.items].sort(compareItems),
    };
  }
  return result;
});

const flatSortedItems = computed(() => {
  const flat = [];
  for (const [categoryName, categoryData] of Object.entries(itemsByCategory.value)) {
    for (const item of categoryData.items) {
      flat.push({ ...item, category_name: categoryName });
    }
  }
  return flat.sort(compareItems);
});

function formatCurrency(val) {
  if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  if (!val) return '-';
  const num = Number(val);
  if (!isNaN(num)) return num.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  return val;
}

function toggleCategory(categoryName) {
  expandedCategories.value[categoryName] = !expandedCategories.value[categoryName];
}

const fetchRegions = async () => {
  const res = await axios.get('/api/regions');
  regions.value = res.data.regions || [];
};

const fetchOutlets = async () => {
  const res = await axios.get('/api/outlets/report');
  outlets.value = res.data.outlets || [];
  filteredOutlets.value = outlets.value; // Initialize with all outlets
};

const onRegionChange = () => {
  if (filters.region) {
    // Filter outlets by selected region
    filteredOutlets.value = outlets.value.filter(outlet => outlet.region_id == filters.region);
  } else {
    // Show all outlets if no region selected
    filteredOutlets.value = outlets.value;
  }
  // Reset outlet selection when region changes
  filters.outlet = '';
};

const fetchMyOutletQr = async () => {
  const res = await axios.get('/api/my-outlet-qr');
  if (res.data.qr_code) {
    filters.outlet = res.data.qr_code;
  }
  if (res.data.outlet_name) {
    userOutletName.value = res.data.outlet_name;
  }
};

const fetchReport = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/report/item-engineering', { params: filters });
    items.value = res.data.items || [];
    itemsByCategory.value = res.data.items_by_category || {};
    modifiers.value = res.data.modifiers || [];
    grand_total.value = res.data.grand_total || 0;
    
    // Auto-expand first category by default
    const categoryNames = Object.keys(itemsByCategory.value);
    if (categoryNames.length > 0) {
      expandedCategories.value[categoryNames[0]] = true;
    }
  } finally {
    loading.value = false;
  }
};

const exportExcel = () => {
  const params = {
    outlet: filters.outlet,
    date_from: filters.date_from,
    date_to: filters.date_to,
  };
  const query = Object.entries(params).map(([k, v]) => `${k}=${encodeURIComponent(v||'')}`).join('&');
  window.open(`/report/item-engineering/export?${query}`, '_blank');
};

onMounted(async () => {
  if (user.id_outlet == 1) {
    // User dengan id_outlet=1 bisa pilih region dan outlet
    await fetchRegions();
    await fetchOutlets();
  } else {
    // User lain langsung muncul outlet mereka
    await fetchMyOutletQr();
  }
});
</script> 