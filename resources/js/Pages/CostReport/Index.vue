<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-coins text-amber-500"></i>
          Cost Report
        </h1>
      </div>

      <!-- Filter -->
      <div class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
            <input
              type="month"
              v-model="filters.bulan"
              @change="loadReport"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div class="flex items-end gap-2">
            <button
              @click="loadReport"
              :disabled="loading"
              class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-1"></i>
              <i v-else class="fa-solid fa-search mr-1"></i>
              {{ loading ? 'Loading...' : 'Load Data' }}
            </button>
            <a
              :href="exportUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700 transition"
            >
              <i class="fa-solid fa-file-excel mr-1"></i>
              Export to Excel
            </a>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-1" aria-label="Tabs">
          <button
            type="button"
            @click="activeTab = 'cost_inventory'"
            :class="activeTab === 'cost_inventory' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="px-4 py-3 border-b-2 font-medium text-sm transition"
          >
            Cost Inventory
          </button>
          <button
            type="button"
            @click="activeTab = 'cogs'"
            :class="activeTab === 'cogs' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="px-4 py-3 border-b-2 font-medium text-sm transition"
          >
            COGS
          </button>
          <button
            type="button"
            @click="activeTab = 'category_cost'"
            :class="activeTab === 'category_cost' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="px-4 py-3 border-b-2 font-medium text-sm transition"
          >
            Category Cost
          </button>
        </nav>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-blue-500">
          <i class="fa-solid fa-spinner fa-spin text-4xl mb-4"></i>
          <p class="text-lg font-medium">Memuat data...</p>
        </div>
      </div>

      <!-- Tab: Cost Inventory -->
      <div v-else-if="activeTab === 'cost_inventory'" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Begin Inventory (Total MAC)</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Official Cost</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost RND</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet Transfer</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Barang Tersedia</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ending Inventory</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS Aktual</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Before Discount</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales After Discount</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Discount vs Sales</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(row, index) in (reportRows || [])" :key="row.outlet_id">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ index + 1 }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.outlet_name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.total_begin_mac) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.official_cost) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.cost_rnd) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right" :class="(row.outlet_transfer || 0) < 0 ? 'text-red-600' : 'text-gray-900'">{{ formatNumber(row.outlet_transfer) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ formatNumber(row.total_barang_tersedia) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.ending_inventory) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.cogs_aktual) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.sales_before_discount) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.discount) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.sales_after_discount) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_discount != null ? (Number(row.pct_discount).toFixed(2) + '%') : '-' }}</td>
            </tr>
            <tr v-if="!reportRows || reportRows.length === 0">
              <td colspan="13" class="px-4 py-8 text-center text-gray-500">Tidak ada data. Pilih bulan lalu klik Load Data.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tab: COGS (outlet sama dengan Cost Inventory, kolom COGS + Category Cost + Meal Employees) -->
      <div v-else-if="activeTab === 'cogs'" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Category Cost</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Meal Employees</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS Pembanding</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Deviasi</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toleransi 2%</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% COGS Pembanding</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% COGS Actual Before Disc</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% COGS Actual After Disc</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% COGS Foods</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Deviasi</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Category Cost</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(row, index) in (cogsRows || [])" :key="row.outlet_id">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ index + 1 }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.outlet_name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.cogs) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.category_cost) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.meal_employees) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ formatNumber(row.cogs_pembanding) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right" :class="(row.deviasi || 0) < 0 ? 'text-red-600' : (row.deviasi || 0) > 0 ? 'text-green-600' : 'text-gray-900'">{{ formatNumber(row.deviasi) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.toleransi_2_pct) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_cogs_pembanding != null ? (Number(row.pct_cogs_pembanding).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_cogs_actual_before_disc != null ? (Number(row.pct_cogs_actual_before_disc).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_cogs_actual_after_disc != null ? (Number(row.pct_cogs_actual_after_disc).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_cogs_foods != null ? (Number(row.pct_cogs_foods).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right" :class="(row.pct_deviasi || 0) < 0 ? 'text-red-600' : (row.pct_deviasi || 0) > 0 ? 'text-green-600' : 'text-gray-900'">{{ row.pct_deviasi != null ? (Number(row.pct_deviasi).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_category_cost != null ? (Number(row.pct_category_cost).toFixed(2) + '%') : '-' }}</td>
            </tr>
            <tr v-if="!cogsRows || cogsRows.length === 0">
              <td colspan="14" class="px-4 py-8 text-center text-gray-500">Tidak ada data. Pilih bulan lalu klik Load Data.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tab: Category Cost (Outlet, Guest Supplies, Spoilage, Waste, Non Commodity, Category Cost + masing-masing %) -->
      <div v-else-if="activeTab === 'category_cost'" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Guest Supplies</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Guest Supplies</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Spoilage</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Spoilage</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Waste</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Waste</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Non Commodity</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Non Commodity</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Category Cost</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Category Cost</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(row, index) in (categoryCostRows || [])" :key="row.outlet_id">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ index + 1 }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.outlet_name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.guest_supplies) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_guest_supplies != null ? (Number(row.pct_guest_supplies).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.spoilage) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_spoilage != null ? (Number(row.pct_spoilage).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.waste) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_waste != null ? (Number(row.pct_waste).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.non_commodity) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_non_commodity != null ? (Number(row.pct_non_commodity).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ formatNumber(row.category_cost) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_category_cost != null ? (Number(row.pct_category_cost).toFixed(2) + '%') : '-' }}</td>
            </tr>
            <tr v-if="!categoryCostRows || categoryCostRows.length === 0">
              <td colspan="12" class="px-4 py-8 text-center text-gray-500">Tidak ada data. Pilih bulan lalu klik Load Data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  reportRows: { type: Array, default: () => [] },
  cogsRows: { type: Array, default: () => [] },
  categoryCostRows: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ bulan: '' }) },
});

const loading = ref(false);
const filters = ref({ ...props.filters });
const activeTab = ref('cost_inventory'); // 'cost_inventory' | 'cogs' | 'category_cost'

watch(() => props.filters, (v) => {
  filters.value = { ...v };
}, { immediate: true });

const exportUrl = computed(() => {
  const bulan = filters.value.bulan || '';
  return `/cost-report/export?bulan=${encodeURIComponent(bulan)}`;
});

function formatNumber(value) {
  if (value == null || value === '') return '0';
  const num = Number(value);
  if (isNaN(num)) return '0';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
}

function loadReport() {
  loading.value = true;
  router.get('/cost-report', {
    bulan: filters.value.bulan,
    load: 1, // lazy load: server hanya hitung data saat user klik Load Data
  }, {
    preserveState: false,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    },
  });
}
</script>
