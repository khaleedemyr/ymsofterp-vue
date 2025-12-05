<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Laporan Nilai Persediaan Outlet</h1>
      <!-- Search Input -->
      <div class="mb-4">
        <input v-model="search" type="text" placeholder="Cari barang, outlet, kategori..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
      </div>
      
      <!-- Filter Controls -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4">
        <div class="flex flex-col">
          <label class="text-sm font-medium text-gray-700 mb-1">Outlet</label>
          <select v-model="selectedOutlet" :disabled="!outletSelectable" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="o.name">{{ o.name }}</option>
          </select>
        </div>
        
        <div class="flex flex-col">
          <label class="text-sm font-medium text-gray-700 mb-1">Warehouse Outlet</label>
          <select v-model="selectedWarehouseOutlet" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in filteredWarehouseOutlets" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        
        <div class="flex flex-col">
          <label class="text-sm font-medium text-gray-700 mb-1">Kategori</label>
          <select v-model="selectedCategory" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Kategori</option>
            <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
          </select>
        </div>
        
        <div class="flex flex-col">
          <label class="text-sm font-medium text-gray-700 mb-1">Barang</label>
          <select v-model="selectedItem" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Barang</option>
            <option v-for="i in items" :key="i.id" :value="i.name">{{ i.name }}</option>
          </select>
        </div>
      </div>
      
      <!-- Controls Row -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-4">
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm text-gray-600">data</span>
        </div>
        
        <button @click="reloadData" :disabled="loadingReload" class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
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
        Silakan pilih minimal satu filter (Outlet, Warehouse Outlet, Kategori, atau Barang), kemudian klik tombol "Load Data" untuk melihat laporan nilai persediaan.
      </div>
      <div v-else-if="hasAnySelection && stocks.length === 0" class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 rounded my-8 text-center font-semibold">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Tidak ada data persediaan untuk filter yang dipilih. Coba ubah filter atau pilih kombinasi filter yang berbeda.
      </div>
      <div v-else-if="hasAnySelection && stocks.length > 0" class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Barang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Kategori</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Small</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Medium</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Large</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Last Cost Small</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">MAC</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Last Cost Medium</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Last Cost Large</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total Value</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!filteredStocks.length">
              <td colspan="11" class="text-center py-10 text-gray-400">Tidak ada data persediaan.</td>
            </tr>
            <tr v-for="row in paginatedStocks" :key="row.item_name + row.outlet_name" class="hover:bg-gray-50 transition">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.item_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.outlet_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_outlet_name || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.category_name || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayQty(row.qty_small) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayQty(row.qty_medium) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayQty(row.qty_large) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">{{ displayValue(row.last_cost_small) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">{{ displayValue(row.mac) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">{{ displayValue(row.last_cost_medium) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">{{ displayValue(row.last_cost_large) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayValue(row.total_value) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="paginatedStocks.length">
            <tr class="bg-gray-50 font-bold">
              <td colspan="10" class="text-right px-6 py-3">Grand Total</td>
              <td class="px-6 py-3 text-right">{{ displayValue(grandTotal) }}</td>
            </tr>
          </tfoot>
        </table>
        
        <!-- Pagination -->
        <div class="flex justify-between items-center mt-4" v-if="filteredStocks.length">
          <div class="text-sm text-gray-600">
            Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ filteredStocks.length }} data
          </div>
          <div class="flex gap-1">
            <button @click="prevPage" :disabled="page === 1" class="px-3 py-1 rounded border text-sm" :class="page === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&lt;</button>
            <span class="px-2">Halaman {{ page }} / {{ totalPages }}</span>
            <button @click="nextPage" :disabled="page === totalPages" class="px-3 py-1 rounded border text-sm" :class="page === totalPages ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&gt;</button>
          </div>
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
  categories: Array,
  items: Array,
  warehouse_outlets: Array,
  user_outlet_id: [String, Number],
  error: String
});
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedOutlet = ref('');
const selectedCategory = ref('');
const selectedItem = ref('');
const selectedWarehouseOutlet = ref('');
const loadingReload = ref(false)
const outletSelectable = computed(() => String(props.user_outlet_id) === '1');

// Check if user has made any selection
const hasAnySelection = computed(() => {
  return selectedOutlet.value || selectedWarehouseOutlet.value || selectedCategory.value || selectedItem.value;
});

const filteredStocks = computed(() => {
  let data = props.stocks;
  if (selectedOutlet.value) {
    data = data.filter(row => String(row.outlet_name) === String(selectedOutlet.value));
  }
  if (selectedWarehouseOutlet.value) {
    data = data.filter(row => String(row.warehouse_outlet_id) === String(selectedWarehouseOutlet.value));
  }
  if (selectedCategory.value) {
    data = data.filter(row => row.category_name === selectedCategory.value);
  }
  if (selectedItem.value) {
    data = data.filter(row => row.item_name === selectedItem.value);
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.outlet_name && row.outlet_name.toLowerCase().includes(s)) ||
    (row.category_name && row.category_name.toLowerCase().includes(s))
  );
});

// Filter warehouse outlets based on selected outlet
const filteredWarehouseOutlets = computed(() => {
  let warehouseOutlets = props.warehouse_outlets;
  
  // Jika bukan superadmin, hanya tampilkan warehouse outlet milik outlet user
  if (!outletSelectable.value && props.user_outlet_id) {
    warehouseOutlets = warehouseOutlets.filter(wo => String(wo.outlet_id) === String(props.user_outlet_id));
  }
  
  // Jika outlet dipilih, filter berdasarkan outlet tersebut
  if (selectedOutlet.value) {
    const selectedOutletObj = props.outlets.find(o => o.name === selectedOutlet.value);
    if (selectedOutletObj) {
      warehouseOutlets = warehouseOutlets.filter(wo => String(wo.outlet_id) === String(selectedOutletObj.id));
    }
  }
  
  return warehouseOutlets;
});

const totalPages = computed(() => Math.ceil(filteredStocks.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, filteredStocks.value.length));
const paginatedStocks = computed(() => filteredStocks.value.slice(startIndex.value, endIndex.value));

function prevPage() {
  if (page.value > 1) page.value--;
}
function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
watch([perPage, search], () => { page.value = 1; });

// Clear warehouse outlet selection when outlet changes
watch(selectedOutlet, () => {
  selectedWarehouseOutlet.value = '';
});

function displayQty(val) {
  if (!val || Number(val) === 0) return '-';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
function displayValue(val) {
  if (!val || Number(val) === 0) return '-';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const grandTotal = computed(() => paginatedStocks.value.reduce((sum, row) => sum + (Number(row.total_value) || 0), 0));

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
    warehouse_outlet_id: selectedWarehouseOutlet.value || '',
    category_id: selectedCategory.value || '',
    item_id: selectedItem.value || ''
  }
  
  // Remove empty parameters
  Object.keys(params).forEach(key => {
    if (!params[key]) {
      delete params[key]
    }
  })
  
  // Make request to server
  router.get('/outlet-inventory/inventory-value-report', params, {
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

// Set default outlet jika bukan superadmin
onMounted(() => {
  if (!outletSelectable.value && props.user_outlet_id) {
    // Cari outlet user berdasarkan id
    const outletObj = props.outlets.find(o => String(o.id) === String(props.user_outlet_id));
    if (outletObj) {
      selectedOutlet.value = outletObj.name;
    }
  }
});
</script> 