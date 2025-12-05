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
        <!-- Category Grouping List -->
        <div class="mb-8">
          <h2 class="text-lg font-bold text-blue-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list"></i> Item Engineering by Category
          </h2>
          <div class="space-y-4">
            <div v-for="(categoryData, categoryName) in itemsByCategory" :key="categoryName" 
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