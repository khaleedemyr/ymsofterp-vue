<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Laporan Stok Akhir Outlet</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="search" type="text" placeholder="Cari nama barang atau outlet..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Outlet</label>
          <select v-model="selectedOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" :disabled="!outletSelectable">
            <option value="">Semua Outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse Outlet</label>
          <select v-model="selectedWarehouseOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse Outlet</option>
            <option v-for="w in filteredWarehouseOutlets" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
        </div>
        <button @click="reloadData" :disabled="loadingReload" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
          <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
          <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Load Data
        </button>
      </div>
      <div v-if="props.error" class="bg-red-50 border-l-4 border-red-400 text-red-800 p-4 rounded my-8 text-center font-semibold">
        {{ props.error }}
      </div>
      <div v-else-if="!hasAnySelection" class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-4 rounded my-8 text-center font-semibold">
        <i class="fas fa-info-circle mr-2"></i>
        Silakan pilih minimal satu filter (Outlet atau Warehouse Outlet), kemudian klik tombol "Load Data" untuk melihat laporan stok akhir.
      </div>
      <div v-else-if="stocks.length === 0" class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 rounded my-8 text-center font-semibold">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Tidak ada data stok untuk filter yang dipilih. Coba ubah filter atau pilih kombinasi filter yang berbeda.
      </div>
      <div v-else class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Kategori</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nama Barang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Small</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Medium</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Large</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal Update</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-if="Object.keys(groupedStocks).length === 0">
              <tr>
                <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data stok.</td>
              </tr>
            </template>
            <template v-else>
              <template v-for="(categoryGroup, categoryName) in groupedStocks" :key="categoryName || 'no-category'">
                <!-- Category Header Row -->
                <tr class="bg-blue-50 hover:bg-blue-100 cursor-pointer" @click="toggleCategory(categoryName || 'no-category')">
                  <td class="px-6 py-3 font-semibold text-blue-700" colspan="8">
                    <div class="flex items-center gap-2">
                      <i :class="expandedCategories.includes(categoryName || 'no-category') ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"></i>
                      <span>{{ categoryName || 'Tanpa Kategori' }}</span>
                      <span class="text-xs font-normal text-gray-600">({{ categoryGroup.length }} item)</span>
                    </div>
                  </td>
                </tr>
                <!-- Item Rows (shown when expanded) -->
                <template v-if="expandedCategories.includes(categoryName || 'no-category')">
                  <tr v-for="row in categoryGroup" :key="row.item_id + '-' + row.outlet_id + '-' + row.warehouse_outlet_id" class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.item_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.outlet_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_outlet_name || '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                      <span v-if="row.qty_small > 0">{{ Number(row.qty_small).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span v-if="row.small_unit_name">{{ row.small_unit_name }}</span></span>
                      <span v-else>-</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                      <span v-if="row.qty_medium > 0">{{ Number(row.qty_medium).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span v-if="row.medium_unit_name">{{ row.medium_unit_name }}</span></span>
                      <span v-else>-</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                      <span v-if="row.qty_large > 0">{{ Number(row.qty_large).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span v-if="row.large_unit_name">{{ row.large_unit_name }}</span></span>
                      <span v-else>-</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.updated_at ? new Date(row.updated_at).toLocaleString() : '-' }}</td>
                  </tr>
                </template>
              </template>
            </template>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4" v-if="Object.keys(groupedStocks).length > 0">
        <div class="text-sm text-gray-600">
          Total {{ Object.keys(groupedStocks).length }} kategori, {{ filteredStocks.length }} item
          <button @click="expandAll" class="ml-4 text-blue-600 hover:text-blue-800 text-sm">Expand All</button>
          <button @click="collapseAll" class="ml-2 text-blue-600 hover:text-blue-800 text-sm">Collapse All</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  stocks: Array,
  outlets: Array,
  user_outlet_id: [String, Number],
  warehouse_outlets: Array,
  error: String
});
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedOutlet = ref('');
const selectedWarehouseOutlet = ref('');
const loadingReload = ref(false);

// Check if user has made any selection
const hasAnySelection = computed(() => {
  return selectedOutlet.value || selectedWarehouseOutlet.value;
});

// Set outlet filter sesuai user saat mount
onMounted(() => {
  if (props.user_outlet_id && String(props.user_outlet_id) !== '1') {
    selectedOutlet.value = String(props.user_outlet_id);
  }
});

const outletSelectable = computed(() => String(props.user_outlet_id) === '1');

// Filter warehouse outlets based on selected outlet
const filteredWarehouseOutlets = computed(() => {
  let warehouseOutlets = props.warehouse_outlets;
  
  // Jika bukan superadmin, hanya tampilkan warehouse outlet milik outlet user
  if (!outletSelectable.value && props.user_outlet_id) {
    warehouseOutlets = warehouseOutlets.filter(wo => String(wo.outlet_id) === String(props.user_outlet_id));
  }
  
  // Jika outlet dipilih, filter berdasarkan outlet tersebut
  if (selectedOutlet.value) {
    warehouseOutlets = warehouseOutlets.filter(wo => String(wo.outlet_id) === String(selectedOutlet.value));
  }
  
  return warehouseOutlets;
});

const filteredStocks = computed(() => {
  let data = props.stocks;
  if (selectedOutlet.value) {
    data = data.filter(row => String(row.outlet_id) === String(selectedOutlet.value));
  }
  if (selectedWarehouseOutlet.value) {
    data = data.filter(row => String(row.warehouse_outlet_id) === String(selectedWarehouseOutlet.value));
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.outlet_name && row.outlet_name.toLowerCase().includes(s)) ||
    (row.category_name && row.category_name.toLowerCase().includes(s))
  );
});

// Group stocks by category
const groupedStocks = computed(() => {
  const grouped = {};
  filteredStocks.value.forEach(row => {
    const categoryName = row.category_name || null;
    if (!grouped[categoryName]) {
      grouped[categoryName] = [];
    }
    grouped[categoryName].push(row);
  });
  return grouped;
});

// Expanded categories state
const expandedCategories = ref([]);

// Toggle category expansion
function toggleCategory(categoryName) {
  const index = expandedCategories.value.indexOf(categoryName);
  if (index > -1) {
    expandedCategories.value.splice(index, 1);
  } else {
    expandedCategories.value.push(categoryName);
  }
}

// Auto-expand all categories on mount or when data changes
watch(() => props.stocks, () => {
  if (props.stocks && props.stocks.length > 0) {
    const categories = Object.keys(groupedStocks.value);
    expandedCategories.value = categories;
  }
}, { immediate: true });

// Expand/Collapse all functions
function expandAll() {
  const categories = Object.keys(groupedStocks.value);
  expandedCategories.value = categories;
}

function collapseAll() {
  expandedCategories.value = [];
}

// Clear warehouse outlet selection when outlet changes
watch(selectedOutlet, () => {
  selectedWarehouseOutlet.value = '';
});

function reloadData() {
  // Validasi: harus ada minimal satu filter yang dipilih
  if (!hasAnySelection.value) {
    alert('Silakan pilih minimal satu filter terlebih dahulu!');
    return;
  }
  
  loadingReload.value = true
  
  // Prepare parameters
  const params = {
    outlet_id: selectedOutlet.value || '',
    warehouse_outlet_id: selectedWarehouseOutlet.value || ''
  }
  
  // Remove empty parameters
  Object.keys(params).forEach(key => {
    if (!params[key]) {
      delete params[key]
    }
  })
  
  // Make request to server
  router.get('/outlet-inventory/stock-position', params, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      loadingReload.value = false
    },
    onError: (errors) => {
      loadingReload.value = false
      console.error('Error loading data:', errors)
    }
  })
}
</script> 