<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-4 md:px-6">
      <!-- Header -->
      <div class="mb-6">
        <button 
          @click="goBack" 
          class="group mb-4 inline-flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-blue-600 transition-colors duration-200"
        >
          <i class="fa fa-arrow-left group-hover:-translate-x-1 transition-transform duration-200"></i>
          <span class="font-medium">Kembali</span>
        </button>
        <div class="flex items-center gap-4">
          <div class="p-3 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl shadow-lg">
            <i class="fa fa-chart-bar text-white text-2xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Laporan Outlet Stock Adjustment</h1>
            <p class="text-gray-600 mt-1">Laporan penyesuaian stok outlet dengan perhitungan MAC</p>
          </div>
        </div>
      </div>
      <!-- Total per type -->
      <div v-if="props.total_per_type && Object.keys(props.total_per_type).length" class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div 
          v-for="t in props.types.filter(tt => tt.value)" 
          :key="t.value" 
          class="p-5 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border-2 border-blue-200 shadow-lg hover:shadow-xl transition-shadow duration-200"
        >
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-1">{{ t.label }}</div>
              <div class="text-2xl font-bold text-blue-900">{{ formatRupiah(props.total_per_type[t.value] || 0) }}</div>
            </div>
            <div class="p-3 bg-blue-500 rounded-xl">
              <i :class="t.value === 'in' ? 'fa fa-arrow-down' : 'fa fa-arrow-up'" class="text-white text-xl"></i>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Filters -->
      <div class="bg-gradient-to-br from-amber-50 to-yellow-50 border-2 border-amber-200 rounded-2xl shadow-lg p-6 mb-6">
        <div class="flex items-start gap-3 mb-4 p-3 bg-amber-100 rounded-xl border border-amber-300">
          <div class="flex-shrink-0 p-2 bg-amber-500 rounded-lg">
            <i class="fa fa-info-circle text-white"></i>
          </div>
          <div class="text-sm text-amber-900">
            <strong class="block mb-1">Penting:</strong>
            <p>Filter tanggal (Dari dan Sampai) wajib diisi dan maksimal range 3 bulan untuk mencegah timeout server. Hanya data yang sudah <strong>approved</strong> yang akan ditampilkan.</p>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa fa-calendar text-gray-400 mr-2"></i>Dari <span class="text-red-500">*</span>
            </label>
            <input 
              type="date" 
              v-model="from" 
              required
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md" 
            />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa fa-calendar text-gray-400 mr-2"></i>Sampai <span class="text-red-500">*</span>
            </label>
            <input 
              type="date" 
              v-model="to" 
              required
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md" 
            />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa fa-tag text-gray-400 mr-2"></i>Tipe
            </label>
            <select v-model="selectedType" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md">
              <option v-for="t in props.types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa fa-store text-gray-400 mr-2"></i>Outlet
            </label>
            <select 
              v-model="selectedOutlet" 
              :disabled="!isSuperuser"
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md"
              :class="{ 'bg-gray-100 cursor-not-allowed': !isSuperuser }"
            >
              <option v-if="isSuperuser" value="">Semua</option>
              <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
            <span v-if="!isSuperuser" class="text-xs text-gray-500 mt-1 block">(Outlet Anda)</span>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa fa-warehouse text-gray-400 mr-2"></i>Warehouse Outlet
            </label>
            <select v-model="selectedWarehouse" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 shadow-sm hover:shadow-md">
              <option value="">Semua</option>
              <option v-for="w in filteredWarehouseOutlets" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <button 
            @click="exportToExcel"
            :disabled="!from || !to || loadingData || exporting || !props.data || props.data.length === 0"
            class="group relative px-8 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 font-semibold flex items-center gap-2 overflow-hidden disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span class="absolute inset-0 bg-gradient-to-r from-emerald-600 to-emerald-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
            <i v-if="exporting" class="fa fa-spinner fa-spin relative z-10"></i>
            <i v-else class="fa fa-file-excel relative z-10"></i>
            <span class="relative z-10">{{ exporting ? 'Exporting...' : 'Export Excel' }}</span>
          </button>
          <button 
            @click="applyFilters"
            :disabled="!from || !to || loadingData"
            class="group relative px-8 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 font-semibold flex items-center gap-2 overflow-hidden disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span class="absolute inset-0 bg-gradient-to-r from-blue-600 to-blue-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
            <i v-if="loadingData" class="fa fa-spinner fa-spin relative z-10"></i>
            <i v-else class="fa fa-search relative z-10"></i>
            <span class="relative z-10">{{ loadingData ? 'Loading...' : 'Load Data' }}</span>
          </button>
        </div>
      </div>
      <!-- Loading State -->
      <div v-if="loadingData" class="bg-white rounded-2xl shadow-lg border border-gray-100 p-16 text-center">
        <div class="inline-block animate-spin rounded-full h-16 w-16 border-4 border-blue-200 border-t-blue-500 mb-6"></div>
        <p class="text-gray-600 font-semibold text-lg">Memuat data laporan...</p>
        <p class="text-gray-400 text-sm mt-2">Mohon tunggu sebentar</p>
      </div>
      
      <!-- Report Table -->
      <div v-else-if="props.data && props.data.length > 0" class="overflow-x-auto bg-white rounded-2xl shadow-lg border border-gray-100">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
            <tr>
              <th class="w-12"></th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No. Adjustment</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipe</th>
              <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Subtotal MAC</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Alasan</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Dibuat Oleh</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="row in props.data" :key="row.id">
              <tr class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent transition-all duration-200 group">
                <td class="px-4 py-4 align-top">
                  <button 
                    @click="toggleExpand(row.id)" 
                    class="p-2 bg-gray-100 hover:bg-blue-100 text-gray-600 hover:text-blue-600 rounded-lg transition-all duration-200 group-hover:scale-110 focus:outline-none"
                  >
                    <i :class="expanded[row.id] ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" class="text-xs"></i>
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3 shadow-md">
                      <i class="fa fa-file-alt text-white text-sm"></i>
                    </div>
                    <span class="font-mono font-bold text-blue-700">{{ row.number || '-' }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ formatDate(row.date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="typeBadgeClass(row.type)" class="px-3 py-1.5 rounded-full text-xs font-bold shadow-sm">
                    <i :class="row.type === 'in' ? 'fa fa-arrow-down' : 'fa fa-arrow-up'" class="mr-1"></i>
                    {{ typeLabel(row.type) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-blue-700">
                  {{ formatRupiah(row.subtotal_mac || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ row.outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ row.warehouse_outlet_name }}</td>
                <td class="px-6 py-4 text-sm text-gray-700">{{ row.reason || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ row.creator_name || '-' }}</td>
              </tr>
              <transition name="fade-expand">
                <tr v-if="expanded[row.id]" :key="'detail-'+row.id">
                  <td></td>
                  <td colspan="8" class="bg-gray-50 px-0 py-0">
                    <div class="rounded-lg border border-blue-100 bg-blue-50/60 shadow-inner mx-4 my-2 overflow-x-auto">
                      <div v-if="loadingDetail[row.id]" class="text-gray-400 py-6 text-center">Loading...</div>
                      <div v-else-if="details[row.id] && details[row.id].length">
                        <table class="w-full text-xs">
                          <thead class="sticky top-0 z-10 bg-blue-100/80">
                            <tr>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Item</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700 text-right">Qty</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Unit</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700 text-right">MAC<br/>(per unit)</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700 text-right">Subtotal MAC</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Catatan</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="item in details[row.id]" :key="item.id" class="hover:bg-blue-100/60 transition-colors">
                              <td class="px-4 py-2 border-b">{{ item.item_name || '-' }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatNumber(item.qty) }}</td>
                              <td class="px-4 py-2 border-b">{{ item.unit || '-' }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(item.mac || 0) }}</td>
                              <td class="px-4 py-2 border-b text-right font-semibold">{{ formatRupiah(item.subtotal_mac || 0) }}</td>
                              <td class="px-4 py-2 border-b">{{ item.note || '-' }}</td>
                            </tr>
                            <tr v-if="details[row.id] && Array.isArray(details[row.id]) && details[row.id].length > 0" class="bg-blue-50 border-t-2 border-blue-300">
                              <td colspan="4" class="px-4 py-2 border-b font-bold text-right text-gray-900">Total MAC</td>
                              <td class="px-4 py-2 border-b font-bold text-right text-lg text-blue-700">{{ formatRupiah(grandTotal(details[row.id])) }}</td>
                              <td class="px-4 py-2 border-b"></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                      <div v-else class="text-gray-400 py-6 text-center">Tidak ada detail.</div>
                    </div>
                  </td>
                </tr>
              </transition>
            </template>
          </tbody>
          <tfoot v-if="props.data && props.data.length > 0" class="bg-gray-100 border-t-2 border-gray-400">
            <tr>
              <td colspan="4" class="px-6 py-4 text-right font-bold text-gray-900 text-lg">
                GRAND TOTAL MAC
              </td>
              <td class="px-6 py-4 text-right font-bold text-gray-900 text-xl">
                {{ formatRupiah(grandTotalMacFromProps) }}
              </td>
              <td colspan="4" class="px-6 py-4"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div v-else-if="from && to" class="bg-white rounded-2xl shadow-lg border border-gray-100 p-16 text-center">
        <div class="p-4 bg-gray-100 rounded-full inline-block mb-6">
          <i class="fa fa-info-circle text-gray-400 text-5xl"></i>
        </div>
        <p class="text-gray-600 font-semibold text-lg">Tidak ada data untuk filter yang dipilih.</p>
        <p class="text-gray-400 text-sm mt-2">Coba ubah filter atau pilih range tanggal yang berbeda.</p>
      </div>
      <div v-else class="bg-white rounded-2xl shadow-lg border border-gray-100 p-16 text-center">
        <div class="p-4 bg-blue-100 rounded-full inline-block mb-6">
          <i class="fa fa-calendar-alt text-blue-400 text-5xl"></i>
        </div>
        <p class="text-gray-600 font-semibold text-lg">Silakan isi filter tanggal</p>
        <p class="text-gray-400 text-sm mt-2">Pilih range tanggal dan klik tombol "Load Data" untuk melihat data.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  data: Array,
  types: Array,
  warehouse_outlets: Array,
  outlets: Array,
  filters: Object,
  total_per_type: Object,
  user_outlet_id: Number
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const isSuperuser = computed(() => props.user_outlet_id === 1);

// Set default outlet untuk non-admin
const defaultOutletId = computed(() => {
  if (props.user_outlet_id && props.user_outlet_id !== 1 && props.outlets) {
    const userOutlet = props.outlets.find(o => o.id === props.user_outlet_id);
    return userOutlet ? userOutlet.id : '';
  }
  return '';
});

// Initialize filters from props
const selectedType = ref(props.filters?.type || '');
const selectedWarehouse = ref(props.filters?.warehouse_outlet_id || '');
const selectedOutlet = ref(isSuperuser.value ? (props.filters?.outlet_id || '') : defaultOutletId.value);
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

// Add filtered warehouse outlets
const filteredWarehouseOutlets = ref([]);

// Function to filter warehouse outlets based on selected outlet
async function filterWarehouseOutlets(outletId) {
  if (!outletId) {
    if (isSuperuser.value) {
      filteredWarehouseOutlets.value = props.warehouse_outlets || [];
    } else {
      const userOutletId = props.user_outlet_id || user.value.id_outlet;
      filteredWarehouseOutlets.value = (props.warehouse_outlets || []).filter(wo => wo.outlet_id == userOutletId);
    }
    return;
  }
  
  const outletIdNum = Number(outletId);
  
  if (isSuperuser.value) {
    try {
      const response = await axios.get(`/api/outlet-food-inventory-adjustment/warehouse-outlets?outlet_id=${outletId}`);
      filteredWarehouseOutlets.value = response.data || [];
    } catch (error) {
      console.error('Error fetching warehouse outlets:', error);
      filteredWarehouseOutlets.value = (props.warehouse_outlets || []).filter(wo => {
        const woOutletId = Number(wo.outlet_id);
        return woOutletId === outletIdNum;
      });
    }
  } else {
    filteredWarehouseOutlets.value = (props.warehouse_outlets || []).filter(wo => {
      const woOutletId = Number(wo.outlet_id);
      return woOutletId === outletIdNum;
    });
  }
}

const expanded = ref({});
const details = ref({});
const loadingDetail = ref({});
const loadingData = ref(false);
const exporting = ref(false);

// Watch for outlet changes to filter warehouse outlets
watch(selectedOutlet, async (newOutletId) => {
  selectedWarehouse.value = '';
  await filterWarehouseOutlets(newOutletId);
}, { immediate: true });

// Initial filter on mount
onMounted(() => {
  if (selectedOutlet.value) {
    filterWarehouseOutlets(selectedOutlet.value);
  } else {
    filterWarehouseOutlets(null);
  }
});

function applyFilters() {
  if (!from.value || !to.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Filter Wajib',
      text: 'Filter tanggal (Dari dan Sampai) wajib diisi untuk melihat laporan.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    });
    return;
  }
  
  loadingData.value = true;
  
  router.get(
    route('outlet-food-inventory-adjustment.report-universal'),
    {
      type: selectedType.value,
      warehouse_outlet_id: selectedWarehouse.value,
      outlet_id: isSuperuser.value ? selectedOutlet.value : (defaultOutletId.value || props.user_outlet_id),
      from: from.value,
      to: to.value
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onFinish: () => {
        loadingData.value = false;
      },
      onError: () => {
        loadingData.value = false;
      }
    }
  );
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function typeLabel(type) {
  if (type === 'in') return 'Stock In';
  if (type === 'out') return 'Stock Out';
  return type;
}

function typeBadgeClass(type) {
  if (type === 'in') return 'bg-gradient-to-r from-green-100 to-green-50 text-green-800 border border-green-200';
  if (type === 'out') return 'bg-gradient-to-r from-red-100 to-red-50 text-red-800 border border-red-200';
  return 'bg-gradient-to-r from-gray-100 to-gray-50 text-gray-800 border border-gray-200';
}

function goBack() {
  router.visit(route('outlet-food-inventory-adjustment.index'));
}

function exportToExcel() {
  if (!from.value || !to.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Filter Wajib',
      text: 'Filter tanggal (Dari dan Sampai) wajib diisi untuk export.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    });
    return;
  }
  
  if (!props.data || props.data.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Tidak Ada Data',
      text: 'Tidak ada data untuk diekspor. Silakan load data terlebih dahulu.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    });
    return;
  }
  
  exporting.value = true;
  
  // Build query parameters
  const params = new URLSearchParams({
    type: selectedType.value || '',
    warehouse_outlet_id: selectedWarehouse.value || '',
    outlet_id: isSuperuser.value ? (selectedOutlet.value || '') : (defaultOutletId.value || props.user_outlet_id),
    from: from.value,
    to: to.value
  });
  
  // Trigger download
  window.location.href = route('outlet-food-inventory-adjustment.report-universal.export') + '?' + params.toString();
  
  // Reset exporting state after a delay
  setTimeout(() => {
    exporting.value = false;
  }, 2000);
}

function toggleExpand(id) {
  expanded.value[id] = !expanded.value[id];
  if (expanded.value[id] && !details.value[id]) {
    loadingDetail.value[id] = true;
    // Load details from report details endpoint
    axios.get(`/api/outlet-food-inventory-adjustment/${id}/report-details`).then(res => {
      if (res.data && res.data.success && res.data.details) {
        details.value[id] = res.data.details;
      } else {
        details.value[id] = [];
      }
    }).catch((error) => {
      console.error('Error loading details for row', id, ':', error);
      details.value[id] = [];
    }).finally(() => {
      loadingDetail.value[id] = false;
    });
  }
}

function formatRupiah(val) {
  if (val === null || val === undefined || isNaN(val)) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(val) {
  if (val === null || val === undefined || isNaN(val)) return '0';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function grandTotal(items) {
  if (!items || !Array.isArray(items) || items.length === 0) return 0;
  return items.reduce((sum, item) => {
    const subtotal = item.subtotal_mac !== null && item.subtotal_mac !== undefined 
      ? Number(item.subtotal_mac) 
      : 0;
    return sum + subtotal;
  }, 0);
}

// Calculate grand total MAC from props.data (subtotal_mac per row)
const grandTotalMacFromProps = computed(() => {
  if (!props.data || !Array.isArray(props.data) || props.data.length === 0) return 0;
  
  return props.data.reduce((sum, row) => {
    const subtotal = row.subtotal_mac !== null && row.subtotal_mac !== undefined 
      ? Number(row.subtotal_mac) 
      : 0;
    return sum + subtotal;
  }, 0);
});
</script>

<style scoped>
/* Smooth transitions */
* {
  transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-expand-enter-active, .fade-expand-leave-active {
  transition: all 0.3s cubic-bezier(.4,2,.6,1);
  overflow: hidden;
}
.fade-expand-enter-from, .fade-expand-leave-to {
  opacity: 0;
  max-height: 0;
}
.fade-expand-enter-to, .fade-expand-leave-from {
  opacity: 1;
  max-height: 500px;
}
</style>

