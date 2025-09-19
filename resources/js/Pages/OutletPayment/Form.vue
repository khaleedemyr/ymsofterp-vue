<template>
  <AppLayout>
    <div class="w-full px-4 py-8">
      <h1 class="text-2xl font-bold text-blue-700 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-money-bill-wave"></i>
        {{ isEditing ? 'Edit Payment' : 'Buat Payment Baru' }}
      </h1>
      <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
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
          <div class="lg:col-span-2">
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
          <div class="lg:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Total Amount</label>
            <div class="w-full border border-blue-200 rounded-lg px-4 py-3 bg-gray-50 font-bold text-blue-700 text-lg select-none">
              {{ formatCurrency(totalAmountFromSelectedGRs) }}
            </div>
            <p v-if="selectedGRs.length > 0" class="text-xs text-gray-500 mt-1">
              Total dari {{ selectedGRs.length }} GR yang dipilih
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
const localGRList = ref(props.grList);

// State for GR list with checkboxes
const selectedGRs = ref([]);
const selectAllGR = ref(false);
const expandedGRs = ref(new Set());
const grItems = ref({});
const loadingGRItems = ref({});
const isEditing = computed(() => props.mode === 'edit');
const form = ref({
  outlet_id: '',
  date_from: new Date().toISOString().split('T')[0],
  date_to: new Date().toISOString().split('T')[0],
  gr_ids: [], // Changed from gr_id to gr_ids for multiple selection
  total_amount: 0
});

const grListFiltered = computed(() => {
  if (!form.value.outlet_id) return [];
  return localGRList.value.filter(gr => gr.outlet_id == form.value.outlet_id);
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
// Watch for selected GRs changes to update total amount
watch(selectedGRs, (newSelectedGRs) => {
  form.value.gr_ids = newSelectedGRs;
  form.value.total_amount = totalAmountFromSelectedGRs.value;
  
  // Update select all state
  selectAllGR.value = newSelectedGRs.length === grListFiltered.value.length && grListFiltered.value.length > 0;
}, { deep: true });

// Watch for changes in props.grList to update local list
watch(() => props.grList, (newGRList) => {
  localGRList.value = newGRList;
}, { deep: true });

// Watch for changes in outlet or date range to auto-refresh GR list
watch([() => form.value.outlet_id, () => form.value.date_from, () => form.value.date_to], async ([newOutletId, newDateFrom, newDateTo], [oldOutletId, oldDateFrom, oldDateTo]) => {
  // Only refresh if outlet and both dates are selected and any has changed
  if (newOutletId && newDateFrom && newDateTo && 
      (newOutletId !== oldOutletId || newDateFrom !== oldDateFrom || newDateTo !== oldDateTo)) {
    // Small delay to avoid too many requests
    setTimeout(() => {
      refreshGRList();
    }, 300);
  }
}, { deep: true });
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
      throw new Error('Failed to fetch GR data');
    }
  } catch (error) {
    console.error('Error refreshing GR list:', error);
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
  console.log('submitForm: dipanggil');
  const Swal = (await import('sweetalert2')).default;
  const confirm = await Swal.fire({
    title: isEditing.value ? 'Update Payment?' : 'Simpan Payment?',
    text: isEditing.value ? 'Yakin ingin update data payment ini?' : 'Yakin ingin menyimpan data payment ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: isEditing.value ? 'Update' : 'Simpan',
    cancelButtonText: 'Batal',
  });
  console.log('submitForm: hasil konfirmasi', confirm);
  if (!confirm.isConfirmed) {
    console.log('submitForm: dibatalkan oleh user');
    return;
  }
  Swal.fire({
    title: isEditing.value ? 'Mengupdate...' : 'Menyimpan...',
    allowOutsideClick: false,
    didOpen: () => { Swal.showLoading(); }
  });
  const onSuccess = () => {
    console.log('submitForm: sukses simpan');
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
    console.log('submitForm: error simpan', err);
    Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
  };
  try {
    if (isEditing.value) {
      console.log('submitForm: mode edit, PUT ke backend', form.value);
      await router.put(`/outlet-payments/${form.value.id}`, form.value, { onSuccess, onError });
    } else {
      console.log('submitForm: mode create, POST ke backend', form.value);
      await router.post('/outlet-payments', form.value, { onSuccess, onError });
    }
  } catch (e) {
    onError(e);
  }
}
</script> 