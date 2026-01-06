<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-blue-500"></i> Depreciation Report
          </h1>
          <p class="text-gray-600 mt-1">Laporan depresiasi asset</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="exportReport"
            class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-download"></i> Export
          </button>
          <Link
            href="/asset-management/reports"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Asset</label>
            <select
              v-model="assetId"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Asset</option>
              <option v-for="asset in assets" :key="asset.id" :value="asset.id">
                {{ asset.asset_code }} - {{ asset.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select
              v-model="categoryId"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Kategori</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select
              v-model="outletId"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
            <button
              @click="applyFilters"
              class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg"
            >
              Apply Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">Total Purchase Value</h3>
          <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalPurchaseValue) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">Total Accumulated Depreciation</h3>
          <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalAccumulatedDepreciation) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">Total Current Value</h3>
          <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(totalCurrentValue) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">Total Assets</h3>
          <p class="text-2xl font-bold text-gray-900">{{ depreciations.length }}</p>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purchase Price</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Annual Depreciation</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Accumulated</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Value</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Calculated</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="depreciations.length === 0">
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="dep in depreciations" :key="dep.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <p class="text-sm font-medium text-blue-600">{{ dep.asset?.asset_code }}</p>
                    <p class="text-xs text-gray-500">{{ dep.asset?.name }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                  {{ formatCurrency(dep.asset?.purchase_price) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatCurrency(dep.annual_depreciation) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatCurrency(dep.accumulated_depreciation) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-blue-600">
                  {{ formatCurrency(dep.current_value) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(dep.last_calculated_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  depreciations: Array,
  assets: Array,
  categories: Array,
  outlets: Array,
  filters: Object,
});

const assetId = ref(props.filters?.asset_id || '');
const categoryId = ref(props.filters?.category_id || '');
const outletId = ref(props.filters?.outlet_id || '');

const totalPurchaseValue = computed(() => {
  return props.depreciations.reduce((sum, d) => sum + (parseFloat(d.asset?.purchase_price) || 0), 0);
});

const totalAccumulatedDepreciation = computed(() => {
  return props.depreciations.reduce((sum, d) => sum + (parseFloat(d.accumulated_depreciation) || 0), 0);
});

const totalCurrentValue = computed(() => {
  return props.depreciations.reduce((sum, d) => sum + (parseFloat(d.current_value) || 0), 0);
});

function applyFilters() {
  router.get('/asset-management/reports/depreciation', {
    asset_id: assetId.value,
    category_id: categoryId.value,
    outlet_id: outletId.value,
  }, { preserveState: true, replace: true });
}

function exportReport() {
  const params = new URLSearchParams({
    asset_id: assetId.value || '',
    category_id: categoryId.value || '',
    outlet_id: outletId.value || '',
    export: '1',
  });
  window.open(`/asset-management/reports/depreciation?${params.toString()}`, '_blank');
}

function formatCurrency(value) {
  if (value == null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', { 
    style: 'currency', 
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}
</script>

