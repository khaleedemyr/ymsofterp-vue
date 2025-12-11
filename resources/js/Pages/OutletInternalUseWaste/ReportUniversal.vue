<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-2xl font-bold mb-6">Laporan Category Cost Outlet</h1>
      <div class="mb-4 flex justify-end">
        <button @click="goBack" class="btn px-6 py-2 rounded-lg font-semibold bg-gray-200 text-gray-700 hover:bg-gray-300 transition">Kembali</button>
      </div>
      <!-- Total per type -->
      <div v-if="props.total_per_type && Object.keys(props.total_per_type).length" class="mb-4 flex flex-wrap gap-4">
        <div v-for="t in props.types.filter(tt => tt.value)" :key="t.value" class="px-4 py-2 rounded bg-blue-50 text-blue-900 font-semibold shadow">
          {{ t.label }}: <span class="font-bold">{{ formatRupiah(props.total_per_type[t.value] || 0) }}</span>
        </div>
      </div>
      <!-- Filters -->
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
        <p class="text-sm text-yellow-800 mb-3">
          <i class="fa fa-info-circle mr-1"></i>
          <strong>Penting:</strong> Filter tanggal (Dari dan Sampai) wajib diisi dan maksimal range 3 bulan untuk mencegah timeout server.
        </p>
        <div class="flex flex-col md:flex-row md:items-center gap-4">
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium">Dari <span class="text-red-500">*</span></label>
            <input 
              type="date" 
              v-model="from" 
              required
              class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" 
            />
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium">Sampai <span class="text-red-500">*</span></label>
            <input 
              type="date" 
              v-model="to" 
              required
              class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" 
            />
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm">Tipe</label>
            <select v-model="selectedType" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
              <option v-for="t in props.types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm">Outlet</label>
            <select 
              v-model="selectedOutlet" 
              :disabled="!isSuperuser"
              class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'bg-gray-100 cursor-not-allowed': !isSuperuser }"
            >
              <option v-if="isSuperuser" value="">Semua</option>
              <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
            <span v-if="!isSuperuser" class="text-xs text-gray-500">(Outlet Anda)</span>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm">Warehouse Outlet</label>
            <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="">Semua</option>
              <option v-for="w in filteredWarehouseOutlets" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button 
            @click="applyFilters"
            :disabled="!from || !to || loadingData"
            class="px-6 py-2 bg-blue-500 text-white rounded-lg font-semibold hover:bg-blue-600 transition disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <i v-if="loadingData" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa fa-search"></i>
            <span>{{ loadingData ? 'Loading...' : 'Load Data' }}</span>
          </button>
        </div>
      </div>
      <!-- Loading State -->
      <div v-if="loadingData" class="bg-white rounded-xl shadow-lg p-12 text-center">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mb-4"></div>
        <p class="text-gray-600 font-medium">Memuat data laporan...</p>
      </div>
      
      <!-- Report Table -->
      <div v-else-if="props.data && props.data.length > 0" class="overflow-x-auto bg-white rounded-xl shadow-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th></th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipe</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Subtotal MAC</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Catatan</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="row in props.data" :key="row.id">
              <tr class="hover:bg-blue-50 transition-colors duration-200 group">
                <td class="px-2 py-2 align-top">
                  <button @click="toggleExpand(row.id)" class="focus:outline-none transition-transform duration-200 group-hover:scale-110">
                    <span v-if="expanded[row.id]">▼</span>
                    <span v-else>▶</span>
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{ formatDate(row.date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ typeLabel(row.type) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-blue-700">
                  {{ formatRupiah(row.subtotal_mac || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.warehouse_outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.notes || '-' }}</td>
              </tr>
              <transition name="fade-expand">
                <tr v-if="expanded[row.id]" :key="'detail-'+row.id">
                  <td></td>
                  <td colspan="6" class="bg-gray-50 px-0 py-0">
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
                              <th class="px-4 py-2 border-b font-semibold text-gray-700 text-right">Qty × MAC</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700 text-right">Subtotal</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Catatan</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="item in details[row.id]" :key="item.id" class="hover:bg-blue-100/60 transition-colors">
                              <td class="px-4 py-2 border-b">{{ item.item_name }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatNumber(item.qty) }}</td>
                              <td class="px-4 py-2 border-b">{{ item.unit_name }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(item.mac_converted) }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(getQtyTimesMac(item)) }}</td>
                              <td class="px-4 py-2 border-b text-right font-semibold">{{ formatRupiah(item.subtotal_mac) }}</td>
                              <td class="px-4 py-2 border-b">{{ item.note || '-' }}</td>
                            </tr>
                            <tr v-if="details[row.id] && Array.isArray(details[row.id]) && details[row.id].length > 0" class="bg-blue-50 border-t-2 border-blue-300">
                              <td colspan="5" class="px-4 py-2 border-b font-bold text-right text-gray-900">Total MAC</td>
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
              <td colspan="3" class="px-6 py-4 text-right font-bold text-gray-900 text-lg">
                GRAND TOTAL MAC
              </td>
              <td class="px-6 py-4 text-right font-bold text-gray-900 text-xl">
                {{ formatRupiah(grandTotalMacFromProps) }}
              </td>
              <td colspan="3" class="px-6 py-4"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div v-else-if="from && to" class="bg-white rounded-xl shadow-lg p-8 text-center">
        <i class="fa fa-info-circle text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-500">Tidak ada data untuk filter yang dipilih.</p>
      </div>
      <div v-else class="bg-white rounded-xl shadow-lg p-8 text-center">
        <i class="fa fa-calendar-alt text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-500">Silakan isi filter tanggal dan klik tombol "Load Data" untuk melihat data.</p>
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

// Initialize filters from props, but don't auto-load data
const selectedType = ref(props.filters?.type || '');
const selectedWarehouse = ref(props.filters?.warehouse_outlet_id || '');
const selectedOutlet = ref(isSuperuser.value ? (props.filters?.outlet_id || '') : defaultOutletId.value);
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

// Add filtered warehouse outlets
const filteredWarehouseOutlets = ref([]);

// Function to filter warehouse outlets based on selected outlet
async function filterWarehouseOutlets(outletId) {
  console.log('Filtering warehouse outlets for outlet:', outletId);
  
  if (!outletId) {
    // No outlet selected - show all for superuser, or user's outlet for regular user
    if (isSuperuser.value) {
      filteredWarehouseOutlets.value = props.warehouse_outlets || [];
    } else {
      const userOutletId = props.user_outlet_id || user.value.id_outlet;
      filteredWarehouseOutlets.value = (props.warehouse_outlets || []).filter(wo => wo.outlet_id == userOutletId);
    }
    console.log('No outlet selected, filtered warehouse outlets:', filteredWarehouseOutlets.value.length);
    return;
  }
  
  // Always filter by outlet_id, regardless of user type
  // Convert outletId to number for comparison
  const outletIdNum = Number(outletId);
  
  if (isSuperuser.value) {
    // For superuser, try to fetch from API first (more reliable)
    try {
      const response = await axios.get(`/api/warehouse-outlets/by-outlet/${outletId}`);
      filteredWarehouseOutlets.value = response.data || [];
      console.log('Fetched warehouse outlets from API:', filteredWarehouseOutlets.value.length);
    } catch (error) {
      console.error('Error fetching warehouse outlets:', error);
      // Fallback to filter from props
      filteredWarehouseOutlets.value = (props.warehouse_outlets || []).filter(wo => {
        const woOutletId = Number(wo.outlet_id);
        return woOutletId === outletIdNum;
      });
      console.log('Fallback filtered warehouse outlets:', filteredWarehouseOutlets.value.length, 'from', props.warehouse_outlets?.length || 0, 'total');
    }
  } else {
    // For regular user, filter from existing warehouse outlets
    filteredWarehouseOutlets.value = (props.warehouse_outlets || []).filter(wo => {
      const woOutletId = Number(wo.outlet_id);
      return woOutletId === outletIdNum;
    });
    console.log('Filtered warehouse outlets for regular user:', filteredWarehouseOutlets.value.length, 'from', props.warehouse_outlets?.length || 0, 'total');
  }
}

const expanded = ref({});
const details = ref({});
const loadingDetail = ref({});
const loadingData = ref(false);

// Watch for outlet changes to filter warehouse outlets
watch(selectedOutlet, async (newOutletId, oldOutletId) => {
  console.log('Outlet changed from', oldOutletId, 'to', newOutletId);
  // Reset warehouse outlet selection when outlet changes
  selectedWarehouse.value = '';
  await filterWarehouseOutlets(newOutletId);
}, { immediate: true });

// Initial filter on mount to ensure warehouse outlets are filtered correctly
onMounted(() => {
  if (selectedOutlet.value) {
    filterWarehouseOutlets(selectedOutlet.value);
  } else {
    filterWarehouseOutlets(null);
  }
});

function applyFilters() {
  // Only fetch if date range is provided
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
    route('outlet-internal-use-waste.report-universal'),
    {
      type: selectedType.value,
      warehouse_outlet_id: selectedWarehouse.value,
      outlet_id: isSuperuser.value ? selectedOutlet.value : selectedOutlet.value,
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
  if (type === 'internal_use') return 'Internal Use';
  if (type === 'spoil') return 'Spoil';
  if (type === 'waste') return 'Waste';
  if (type === 'r_and_d') return 'R & D';
  if (type === 'marketing') return 'Marketing';
  if (type === 'non_commodity') return 'Non Commodity';
  if (type === 'guest_supplies') return 'Guest Supplies';
  if (type === 'wrong_maker') return 'Wrong Maker';
  return type;
}
function goBack() {
  router.visit(route('outlet-internal-use-waste.index'));
}

function toggleExpand(id) {
  expanded.value[id] = !expanded.value[id];
  if (expanded.value[id] && !details.value[id]) {
    loadingDetail.value[id] = true;
    axios.get(`/outlet-internal-use-waste/${id}/details`).then(res => {
      details.value[id] = res.data.details || [];
      console.log('Details loaded for row', id, ':', details.value[id]);
      console.log('Total MAC for row', id, ':', grandTotal(details.value[id]));
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

function getQtyTimesMac(item) {
  if (!item || item.qty === null || item.qty === undefined || item.mac_converted === null || item.mac_converted === undefined) {
    return 0;
  }
  return (Number(item.qty) || 0) * (Number(item.mac_converted) || 0);
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

// Calculate grand total MAC for all rows (from expanded details)
const grandTotalMac = computed(() => {
  if (!props.data || !Array.isArray(props.data) || props.data.length === 0) return 0;
  
  let total = 0;
  props.data.forEach(row => {
    if (details.value[row.id] && Array.isArray(details.value[row.id]) && details.value[row.id].length > 0) {
      const rowTotal = grandTotal(details.value[row.id]);
      total += rowTotal;
    }
  });
  
  return total;
});

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