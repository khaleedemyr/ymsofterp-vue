<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-tags text-blue-500"></i> Asset by Category Report
          </h1>
          <p class="text-gray-600 mt-1">Asset per kategori</p>
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div v-for="category in categorySummary" :key="category.id" class="bg-white rounded-xl shadow-lg p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">{{ category.name }}</h3>
          <p class="text-2xl font-bold text-gray-900">{{ category.count }}</p>
          <p class="text-xs text-gray-500 mt-1">Total Assets</p>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="assets.length === 0">
                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
              <tr v-for="asset in assets" :key="asset.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ asset.category?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                  {{ asset.asset_code }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ asset.name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ asset.current_outlet?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadgeClass(asset.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ asset.status }}
                  </span>
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
  assets: Array,
  categories: Array,
  outlets: Array,
  filters: Object,
});

const categoryId = ref(props.filters?.category_id || '');
const outletId = ref(props.filters?.outlet_id || '');

const categorySummary = computed(() => {
  const summary = {};
  props.assets.forEach(asset => {
    const catId = asset.category_id || 'uncategorized';
    const catName = asset.category?.name || 'Uncategorized';
    if (!summary[catId]) {
      summary[catId] = { id: catId, name: catName, count: 0 };
    }
    summary[catId].count++;
  });
  return Object.values(summary);
});

function applyFilters() {
  router.get('/asset-management/reports/asset-by-category', {
    category_id: categoryId.value,
    outlet_id: outletId.value,
  }, { preserveState: true, replace: true });
}

function exportReport() {
  const params = new URLSearchParams({
    category_id: categoryId.value || '',
    outlet_id: outletId.value || '',
    export: '1',
  });
  window.open(`/asset-management/reports/asset-by-category?${params.toString()}`, '_blank');
}

function getStatusBadgeClass(status) {
  const classes = {
    'Active': 'bg-green-100 text-green-800',
    'Maintenance': 'bg-yellow-100 text-yellow-800',
    'Disposed': 'bg-red-100 text-red-800',
    'Lost': 'bg-gray-100 text-gray-800',
    'Transfer': 'bg-blue-100 text-blue-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}
</script>

