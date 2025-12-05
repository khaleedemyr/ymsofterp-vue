<template>
  <AppLayout>
    <div class="w-full px-4 py-8">
      <h1 class="text-2xl font-bold text-blue-700 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-money-bill-wave"></i>
        {{ isEditing ? 'Edit Payment' : 'Buat Payment Baru' }}
      </h1>
      <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Outlet</label>
            <select v-model="form.outlet_id" class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm" required>
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Rentang Tanggal</label>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs text-gray-500 mb-1">Dari Tanggal</label>
                <input 
                  type="date" 
                  v-model="form.date_from" 
                  class="w-full border border-blue-200 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm text-sm" 
                  required 
                />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Sampai Tanggal</label>
                <input 
                  type="date" 
                  v-model="form.date_to" 
                  class="w-full border border-blue-200 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm text-sm" 
                  required 
                />
              </div>
            </div>
          </div>
          <div class="flex items-end">
            <button 
              type="button"
              @click="loadData" 
              :disabled="!canLoadData || loadingData"
              class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center justify-center font-medium shadow-sm"
            >
              <svg v-if="loadingData" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <i v-else class="fas fa-download mr-2"></i>
              {{ loadingData ? 'Loading...' : 'Load Data' }}
            </button>
          </div>
        </div>
        
        <!-- Tab Navigation -->
        <div class="flex border-b border-gray-200 mb-4">
          <button 
            type="button"
            @click="activeTab = 'gr'"
            :class="[
              'px-4 py-2 text-sm font-medium border-b-2 transition-colors',
              activeTab === 'gr' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            ]"
          >
            <i class="fas fa-truck mr-2"></i>
            Good Receive (GR)
          </button>
          <button 
            type="button"
            @click="activeTab = 'retail'"
            :class="[
              'px-4 py-2 text-sm font-medium border-b-2 transition-colors',
              activeTab === 'retail' 
                ? 'border-blue-500 text-blue-600' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            ]"
          >
            <i class="fas fa-store mr-2"></i>
            Retail Sales
          </button>
        </div>

        <!-- Tab Content Container -->
        <div class="w-full">
          <!-- GR Tab Content -->
            <div v-if="activeTab === 'gr'">
              <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-semibold text-gray-700">Pilih GR</label>
                <button 
                  type="button" 
                  @click="refreshGRList" 
                  :disabled="refreshingGR"
                  class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed transition-all shadow-sm text-sm"
                  title="Refresh Data GR"
                >
                  <i class="fas fa-sync-alt" :class="{ 'animate-spin': refreshingGR }"></i>
                </button>
              </div>
            
            <!-- GR List Container -->
            <div class="border border-blue-200 rounded-lg max-h-96 overflow-y-auto bg-white w-full">
              <!-- Select All -->
              <div v-if="grListFiltered.length > 0" class="p-3 border-b border-gray-200 bg-gray-50">
                <label class="flex items-center cursor-pointer">
                  <input 
                    type="checkbox" 
                    v-model="selectAllGR"
                    @change="toggleSelectAll"
                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                  />
                  <span class="ml-2 text-sm font-medium text-gray-700">Pilih Semua GR</span>
                  <span class="ml-auto text-xs text-gray-500">{{ selectedGRs.length }} dari {{ grListFiltered.length }} dipilih</span>
                </label>
              </div>
              
              <!-- GR List -->
              <div v-if="grListFiltered.length > 0" class="divide-y divide-gray-200">
                <div v-for="gr in grListFiltered" :key="gr.id" class="p-3 hover:bg-gray-50 transition-colors">
                  <!-- GR Header -->
                  <div class="flex items-center">
                    <input 
                      type="checkbox" 
                      :value="gr.id"
                      v-model="selectedGRs"
                      class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    />
                    <div class="ml-3 flex-1">
                      <div class="flex items-center justify-between">
                        <div>
                          <h4 class="text-sm font-medium text-gray-900">{{ gr.number || gr.gr_number || '-' }}</h4>
                          <p class="text-xs text-gray-500">
                            {{ formatDate(gr.receive_date) }} • {{ gr.outlet_name }}<span v-if="gr.warehouse_outlet_name"> • {{ gr.warehouse_outlet_name }}</span>
                          </p>
                        </div>
                        <div class="text-right">
                          <p class="text-sm font-semibold text-blue-600">{{ formatCurrency(gr.total_amount) }}</p>
                          <button 
                            type="button"
                            @click="toggleGRExpanded(gr.id)"
                            class="text-xs text-blue-500 hover:text-blue-700 flex items-center"
                          >
                            <i class="fas" :class="expandedGRs.has(gr.id) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            <span class="ml-1">{{ expandedGRs.has(gr.id) ? 'Tutup' : 'Lihat Item' }}</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Expanded GR Items -->
                  <div v-if="expandedGRs.has(gr.id)" class="mt-3 ml-7">
                    <div v-if="loadingGRItems[gr.id]" class="flex items-center justify-center py-4">
                      <i class="fas fa-spinner fa-spin text-blue-500"></i>
                      <span class="ml-2 text-sm text-gray-500">Memuat item...</span>
                    </div>
                    <div v-else-if="grItems[gr.id] && grItems[gr.id].length > 0" class="bg-gray-50 rounded-lg p-3">
                      <h5 class="text-xs font-medium text-gray-700 mb-2">Detail Item:</h5>
                      <div class="space-y-2">
                        <div v-for="item in grItems[gr.id]" :key="item.id" class="flex justify-between items-center text-xs">
                          <div class="flex-1">
                            <span class="font-medium text-gray-900">{{ item.item_name }}</span>
                            <div class="text-gray-500">
                              Qty GR: {{ formatQty(item.received_qty) }} {{ item.unit }}
                            </div>
                          </div>
                          <div class="text-right">
                            <div class="text-gray-500">Harga: {{ formatCurrency(item.price) }}</div>
                            <div class="font-medium text-green-600">
                              {{ formatCurrency(item.received_qty * item.price) }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div v-else class="text-xs text-gray-500 italic">Tidak ada item</div>
                  </div>
                </div>
              </div>
              
              <!-- Empty State -->
              <div v-else class="p-6 text-center text-gray-500">
                <i class="fas fa-inbox text-2xl mb-2"></i>
                <p class="text-sm">Tidak ada GR yang tersedia</p>
                <p class="text-xs">Pilih outlet dan tanggal untuk melihat GR</p>
              </div>
            </div>
          </div>

          <!-- Retail Sales Tab Content -->
            <div v-if="activeTab === 'retail'">
              <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-semibold text-gray-700">Pilih Retail Sales</label>
                <button 
                  type="button" 
                  @click="refreshRetailSalesList" 
                  :disabled="refreshingRetailSales"
                  class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-green-400 disabled:cursor-not-allowed transition-all shadow-sm text-sm"
                  title="Refresh Data Retail Sales"
                >
                  <i class="fas fa-sync-alt" :class="{ 'animate-spin': refreshingRetailSales }"></i>
                </button>
              </div>
            
            <!-- Retail Sales List Container -->
            <div class="border border-green-200 rounded-lg max-h-96 overflow-y-auto bg-white w-full">
              <!-- Select All -->
              <div v-if="retailSalesListFiltered.length > 0" class="p-3 border-b border-gray-200 bg-gray-50">
                <label class="flex items-center cursor-pointer">
                  <input 
                    type="checkbox" 
                    v-model="selectAllRetailSales"
                    @change="toggleSelectAllRetailSales"
                    class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                  />
                  <span class="ml-2 text-sm font-medium text-gray-700">Pilih Semua Retail Sales</span>
                  <span class="ml-auto text-xs text-gray-500">{{ selectedRetailSales.length }} dari {{ retailSalesListFiltered.length }} dipilih</span>
                </label>
              </div>
              
              <!-- Retail Sales List -->
              <div v-if="retailSalesListFiltered.length > 0" class="divide-y divide-gray-200">
                <div v-for="retail in retailSalesListFiltered" :key="retail.id" class="p-3 hover:bg-gray-50 transition-colors">
                  <!-- Retail Sales Header -->
                  <div class="flex items-center">
                    <input 
                      type="checkbox" 
                      :value="retail.id"
                      v-model="selectedRetailSales"
                      class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                    />
                    <div class="ml-3 flex-1">
                      <div class="flex items-center justify-between">
                        <div>
                          <h4 class="text-sm font-medium text-gray-900">{{ retail.number }}</h4>
                          <p class="text-xs text-gray-500">
                            {{ formatDate(retail.sale_date) }} • {{ retail.customer_name }} ({{ retail.customer_code }})
                          </p>
                          <p class="text-xs text-gray-400">
                            {{ retail.warehouse_name }}<span v-if="retail.division_name"> • {{ retail.division_name }}</span>
                          </p>
                        </div>
                        <div class="text-right">
                          <p class="text-sm font-semibold text-green-600">{{ formatCurrency(retail.total_amount) }}</p>
                          <button 
                            type="button"
                            @click="toggleRetailSalesExpanded(retail.id)"
                            class="text-xs text-green-500 hover:text-green-700 flex items-center"
                          >
                            <i class="fas" :class="expandedRetailSales.has(retail.id) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            <span class="ml-1">{{ expandedRetailSales.has(retail.id) ? 'Tutup' : 'Lihat Item' }}</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Expanded Retail Sales Items -->
                  <div v-if="expandedRetailSales.has(retail.id)" class="mt-3 ml-7">
                    <div v-if="loadingRetailSalesItems[retail.id]" class="flex items-center justify-center py-4">
                      <i class="fas fa-spinner fa-spin text-green-500"></i>
                      <span class="ml-2 text-sm text-gray-500">Memuat item...</span>
                    </div>
                    <div v-else-if="retailSalesItems[retail.id] && retailSalesItems[retail.id].length > 0" class="bg-gray-50 rounded-lg p-3">
                      <h5 class="text-xs font-medium text-gray-700 mb-2">Detail Item:</h5>
                      <div class="space-y-2">
                        <div v-for="item in retailSalesItems[retail.id]" :key="item.id" class="flex justify-between items-center text-xs">
                          <div class="flex-1">
                            <span class="font-medium text-gray-900">{{ item.item_name }}</span>
                            <div class="text-gray-500">
                              Qty: {{ formatQty(item.received_qty) }} {{ item.unit }}
                            </div>
                          </div>
                          <div class="text-right">
                            <div class="text-gray-500">Harga: {{ formatCurrency(item.price) }}</div>
                            <div class="font-medium text-green-600">
                              {{ formatCurrency(item.subtotal) }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div v-else class="text-xs text-gray-500 italic">Tidak ada item</div>
                  </div>
                </div>
              </div>
              
              <!-- Empty State -->
              <div v-else class="p-6 text-center text-gray-500">
                <i class="fas fa-store text-2xl mb-2"></i>
                <p class="text-sm">Tidak ada Retail Sales yang tersedia</p>
                <p class="text-xs">Pilih outlet dan tanggal untuk melihat Retail Sales</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Total Amount Section -->
        <div class="mt-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Total Amount</label>
            <div class="w-full border border-blue-200 rounded-lg px-4 py-3 bg-gray-50 font-bold text-blue-700 text-lg select-none">
              {{ formatCurrency(totalAmountFromSelectedTransactions) }}
            </div>
            <p v-if="selectedGRs.length > 0 || selectedRetailSales.length > 0" class="text-xs text-gray-500 mt-1">
              Total dari {{ selectedGRs.length }} GR dan {{ selectedRetailSales.length }} Retail Sales yang dipilih
            </p>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-8">
          <button type="button" @click="goBack" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold shadow-sm">Batal</button>
          <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-bold shadow-lg hover:bg-blue-700 transition-all">{{ isEditing ? 'Update' : 'Simpan' }}</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
const props = defineProps({
  mode: { type: String, default: 'create' },
  payment: Object,
  outlets: Array,
  grList: Array,
});

// State for refresh functionality
const refreshingGR = ref(false);
const refreshingRetailSales = ref(false);
const localGRList = ref(props.grList);
const localRetailSalesList = ref([]);

// State for Load Data button
const loadingData = ref(false);

// State for active tab
const activeTab = ref('gr');

// State for GR list with checkboxes
const selectedGRs = ref([]);
const selectAllGR = ref(false);
const expandedGRs = ref(new Set());
const grItems = ref({});
const loadingGRItems = ref({});

// State for Retail Sales list with checkboxes
const selectedRetailSales = ref([]);
const selectAllRetailSales = ref(false);
const expandedRetailSales = ref(new Set());
const retailSalesItems = ref({});
const loadingRetailSalesItems = ref({});

const isEditing = computed(() => props.mode === 'edit');

// Computed property to check if Load Data button can be enabled
const canLoadData = computed(() => {
  return form.value.outlet_id && form.value.date_from && form.value.date_to;
});
const form = ref({
  outlet_id: '',
  date_from: new Date().toISOString().split('T')[0],
  date_to: new Date().toISOString().split('T')[0],
  gr_ids: [], // Changed from gr_id to gr_ids for multiple selection
  retail_ids: [], // New field for retail sales
  total_amount: 0
});

const grListFiltered = computed(() => {
  // Data sudah di-filter di backend berdasarkan outlet_id, date_from, date_to
  // Jadi kita hanya perlu return localGRList langsung
  return localGRList.value;
});

const retailSalesListFiltered = computed(() => {
  // Data sudah di-filter di backend berdasarkan outlet_id, date_from, date_to
  // Jadi kita hanya perlu return localRetailSalesList langsung
  return localRetailSalesList.value;
});

// Computed for total amount from selected GRs
const totalAmountFromSelectedGRs = computed(() => {
  if (!selectedGRs.value || selectedGRs.value.length === 0) {
    return 0;
  }
  
  const total = selectedGRs.value.reduce((sum, grId) => {
    const gr = grListFiltered.value.find(g => g.id == grId);
    if (!gr) {
      return sum;
    }
    
    // Safely parse the amount
    let amount = 0;
    if (gr.total_amount !== null && gr.total_amount !== undefined) {
      amount = parseFloat(gr.total_amount);
      if (isNaN(amount)) {
        amount = 0;
      }
    }
    
    return sum + amount;
  }, 0);
  
  return total;
});

// Computed for total amount from selected Retail Sales
const totalAmountFromSelectedRetailSales = computed(() => {
  if (!selectedRetailSales.value || selectedRetailSales.value.length === 0) {
    return 0;
  }
  
  const total = selectedRetailSales.value.reduce((sum, retailId) => {
    const retail = retailSalesListFiltered.value.find(r => r.id == retailId);
    if (!retail) {
      return sum;
    }
    
    // Safely parse the amount
    let amount = 0;
    if (retail.total_amount !== null && retail.total_amount !== undefined) {
      amount = parseFloat(retail.total_amount);
      if (isNaN(amount)) {
        amount = 0;
      }
    }
    
    return sum + amount;
  }, 0);
  
  return total;
});

// Computed for total amount from all selected transactions
// Include GR Supplier total amount (calculated for outlet and date range, like Rekap FJ)
const totalAmountFromSelectedTransactions = computed(() => {
  // Ensure all values are numbers, not strings
  const grTotal = parseFloat(totalAmountFromSelectedGRs.value) || 0;
  const retailTotal = parseFloat(totalAmountFromSelectedRetailSales.value) || 0;
  
  return grTotal + retailTotal;
});
// Watch for selected GRs changes to update total amount
watch(selectedGRs, (newSelectedGRs) => {
  form.value.gr_ids = newSelectedGRs;
  form.value.total_amount = totalAmountFromSelectedTransactions.value;
  
  // Update select all state
  selectAllGR.value = newSelectedGRs.length === grListFiltered.value.length && grListFiltered.value.length > 0;
}, { deep: true });

// Watch for selected Retail Sales changes to update total amount
watch(selectedRetailSales, (newSelectedRetailSales) => {
  form.value.retail_ids = newSelectedRetailSales;
  form.value.total_amount = totalAmountFromSelectedTransactions.value;
  
  // Update select all state
  selectAllRetailSales.value = newSelectedRetailSales.length === retailSalesListFiltered.value.length && retailSalesListFiltered.value.length > 0;
}, { deep: true });

// Watch for changes in props.grList to update local list
watch(() => props.grList, (newGRList) => {
  localGRList.value = newGRList;
}, { deep: true });

// Watch for active tab changes to load data when switching tabs (only if data already loaded)
watch(activeTab, (newTab) => {
  if (newTab === 'retail' && localRetailSalesList.value.length === 0 && canLoadData.value) {
    // Load retail sales data when switching to retail tab if not already loaded
    refreshRetailSalesList();
  }
});
function goBack() {
  router.get('/outlet-payments');
}

// Function to toggle select all GRs
function toggleSelectAll() {
  if (selectAllGR.value) {
    selectedGRs.value = grListFiltered.value.map(gr => gr.id);
  } else {
    selectedGRs.value = [];
  }
}

// Function to toggle select all Retail Sales
function toggleSelectAllRetailSales() {
  if (selectAllRetailSales.value) {
    selectedRetailSales.value = retailSalesListFiltered.value.map(retail => retail.id);
  } else {
    selectedRetailSales.value = [];
  }
}

// Function to toggle GR expansion
function toggleGRExpanded(grId) {
  if (expandedGRs.value.has(grId)) {
    expandedGRs.value.delete(grId);
  } else {
    expandedGRs.value.add(grId);
    // Load GR items if not already loaded
    if (!grItems.value[grId]) {
      loadGRItems(grId);
    }
  }
}

// Function to toggle Retail Sales expansion
function toggleRetailSalesExpanded(retailId) {
  if (expandedRetailSales.value.has(retailId)) {
    expandedRetailSales.value.delete(retailId);
  } else {
    expandedRetailSales.value.add(retailId);
    // Load Retail Sales items if not already loaded
    if (!retailSalesItems.value[retailId]) {
      loadRetailSalesItems(retailId);
    }
  }
}

// Function to load GR items
async function loadGRItems(grId) {
  loadingGRItems.value[grId] = true;
  
  try {
    const response = await fetch(`/outlet-payments/gr-items/${grId}`);
    const data = await response.json();
    
    if (data.success) {
      grItems.value[grId] = data.items;
    } else {
      grItems.value[grId] = [];
    }
  } catch (error) {
    console.error('Error loading GR items:', error);
    grItems.value[grId] = [];
  } finally {
    loadingGRItems.value[grId] = false;
  }
}

// Function to load Retail Sales items
async function loadRetailSalesItems(retailId) {
  loadingRetailSalesItems.value[retailId] = true;
  
  try {
    const response = await fetch(`/outlet-payments/retail-sales-items/${retailId}`);
    const data = await response.json();
    
    if (data.success) {
      retailSalesItems.value[retailId] = data.items;
    } else {
      retailSalesItems.value[retailId] = [];
    }
  } catch (error) {
    console.error('Error loading Retail Sales items:', error);
    retailSalesItems.value[retailId] = [];
  } finally {
    loadingRetailSalesItems.value[retailId] = false;
  }
}

// Function to refresh GR list
async function refreshGRList() {
  refreshingGR.value = true;
  
  try {
    // Fetch fresh GR data with current filters
    const params = new URLSearchParams();
    if (form.value.outlet_id) {
      params.append('outlet_id', form.value.outlet_id);
    }
    if (form.value.date_from) {
      params.append('date_from', form.value.date_from);
    }
    if (form.value.date_to) {
      params.append('date_to', form.value.date_to);
    }
    
    const response = await fetch(`/outlet-payments/gr-list?${params.toString()}`);
    const data = await response.json();
    
        if (data.success) {
          // Update local GR list with fresh data
          localGRList.value = data.grList;

          // Clear selected GRs that are no longer in the list
          selectedGRs.value = selectedGRs.value.filter(grId =>
            data.grList.find(gr => gr.id == grId)
          );
      
      // Update select all state
      selectAllGR.value = selectedGRs.value.length === data.grList.length && data.grList.length > 0;
      
      // Clear expanded GRs and items
      expandedGRs.value.clear();
      grItems.value = {};
      loadingGRItems.value = {};
      
      // Show success message
      const Swal = (await import('sweetalert2')).default;
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Data GR berhasil di-refresh',
        timer: 1500,
        showConfirmButton: false
      });
    } else {
      // If no data, set empty array
      localGRList.value = [];
    }
  } catch (error) {
    console.error('Error refreshing GR list:', error);
    // On error, set empty array
    localGRList.value = [];
    const Swal = (await import('sweetalert2')).default;
    Swal.fire({
      icon: 'error',
      title: 'Gagal!',
      text: 'Gagal me-refresh data GR'
    });
  } finally {
    refreshingGR.value = false;
  }
}

// Function to load data for both GR and Retail Sales
async function loadData() {
  if (!canLoadData.value) return;
  
  loadingData.value = true;
  
  try {
    // Load both GR and Retail Sales data
    await Promise.all([
      refreshGRList(),
      refreshRetailSalesList()
    ]);
  } catch (error) {
    console.error('Error loading data:', error);
  } finally {
    loadingData.value = false;
  }
}

// Function to refresh Retail Sales list
async function refreshRetailSalesList() {
  refreshingRetailSales.value = true;
  
  try {
    // Fetch fresh Retail Sales data with current filters
    const params = new URLSearchParams();
    if (form.value.outlet_id) {
      params.append('outlet_id', form.value.outlet_id);
    }
    if (form.value.date_from) {
      params.append('date_from', form.value.date_from);
    }
    if (form.value.date_to) {
      params.append('date_to', form.value.date_to);
    }
    
    const response = await fetch(`/outlet-payments/retail-sales-list?${params.toString()}`);
    const data = await response.json();
    
    if (data.success) {
      // Update local Retail Sales list with fresh data
      localRetailSalesList.value = data.retailList;
      
      // Clear selected Retail Sales that are no longer in the list
      selectedRetailSales.value = selectedRetailSales.value.filter(retailId => 
        data.retailList.find(retail => retail.id == retailId)
      );
      
      // Update select all state
      selectAllRetailSales.value = selectedRetailSales.value.length === data.retailList.length && data.retailList.length > 0;
      
      // Clear expanded Retail Sales and items
      expandedRetailSales.value.clear();
      retailSalesItems.value = {};
      loadingRetailSalesItems.value = {};
      
      // Show success message
      const Swal = (await import('sweetalert2')).default;
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Data Retail Sales berhasil di-refresh',
        timer: 1500,
        showConfirmButton: false
      });
    } else {
      throw new Error('Failed to fetch Retail Sales data');
    }
  } catch (error) {
    console.error('Error refreshing Retail Sales list:', error);
    const Swal = (await import('sweetalert2')).default;
    Swal.fire({
      icon: 'error',
      title: 'Gagal!',
      text: 'Gagal me-refresh data Retail Sales'
    });
  } finally {
    refreshingRetailSales.value = false;
  }
}
function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID');
}
function formatCurrency(amount) {
  // Handle NaN, null, undefined, or invalid amounts
  const validAmount = isNaN(amount) || amount === null || amount === undefined ? 0 : parseFloat(amount);
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(validAmount);
}
function formatQty(val) {
  if (val == null) return '';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
async function submitForm() {
  const Swal = (await import('sweetalert2')).default;
  const confirm = await Swal.fire({
    title: isEditing.value ? 'Update Payment?' : 'Simpan Payment?',
    text: isEditing.value ? 'Yakin ingin update data payment ini?' : 'Yakin ingin menyimpan data payment ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: isEditing.value ? 'Update' : 'Simpan',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) {
    return;
  }
  Swal.fire({
    title: isEditing.value ? 'Mengupdate...' : 'Menyimpan...',
    allowOutsideClick: false,
    didOpen: () => { Swal.showLoading(); }
  });
  const onSuccess = () => {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: isEditing.value ? 'Payment berhasil diupdate' : 'Payment berhasil disimpan', timer: 1500, showConfirmButton: false });
    goBack();
  };
  const onError = (err) => {
    let msg = 'Gagal menyimpan data';
    if (err && err.response && err.response.data && err.response.data.message) {
      msg = err.response.data.message;
    } else if (err && err.message) {
      msg = err.message;
    }
    Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
  };
  try {
    if (isEditing.value) {
      await router.put(`/outlet-payments/${form.value.id}`, form.value, { onSuccess, onError });
    } else {
      await router.post('/outlet-payments', form.value, { onSuccess, onError });
    }
  } catch (e) {
    onError(e);
  }
}
</script> 