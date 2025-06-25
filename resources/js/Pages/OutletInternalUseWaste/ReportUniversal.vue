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
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <div class="flex items-center gap-2">
          <label class="text-sm">Tipe</label>
          <select v-model="selectedType" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="t in props.types" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse Outlet</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua</option>
            <option v-for="w in filteredWarehouseOutlets" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div v-if="isSuperuser" class="flex items-center gap-2">
          <label class="text-sm">Outlet</label>
          <select v-model="selectedOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua</option>
            <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Dari</label>
          <input type="date" v-model="from" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Sampai</label>
          <input type="date" v-model="to" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
      </div>
      <!-- Report Table -->
      <div class="overflow-x-auto bg-white rounded-xl shadow-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th></th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipe</th>
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
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.warehouse_outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.notes || '-' }}</td>
              </tr>
              <transition name="fade-expand">
                <tr v-if="expanded[row.id]" :key="'detail-'+row.id">
                  <td></td>
                  <td colspan="5" class="bg-gray-50 px-0 py-0">
                    <div class="rounded-lg border border-blue-100 bg-blue-50/60 shadow-inner mx-4 my-2 overflow-x-auto">
                      <div v-if="loadingDetail[row.id]" class="text-gray-400 py-6 text-center">Loading...</div>
                      <div v-else-if="details[row.id] && details[row.id].length">
                        <table class="w-full text-xs">
                          <thead class="sticky top-0 z-10 bg-blue-100/80">
                            <tr>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Item</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Qty</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Unit</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">MAC</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Subtotal MAC</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Catatan</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="item in details[row.id]" :key="item.id" class="hover:bg-blue-100/60 transition-colors">
                              <td class="px-4 py-2 border-b">{{ item.item_name }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ item.qty }}</td>
                              <td class="px-4 py-2 border-b">{{ item.unit_name }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(item.mac_converted) }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(item.subtotal_mac) }}</td>
                              <td class="px-4 py-2 border-b">{{ item.note || '-' }}</td>
                            </tr>
                            <tr v-if="details[row.id] && details[row.id].length">
                              <td colspan="4" class="px-4 py-2 border-b font-bold text-right bg-blue-50">Grand Total</td>
                              <td class="px-4 py-2 border-b font-bold text-right bg-blue-50">{{ formatRupiah(grandTotal(details[row.id])) }}</td>
                              <td class="px-4 py-2 border-b bg-blue-50"></td>
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
            <tr v-if="!props.data.length">
              <td colspan="6" class="text-center py-8 text-gray-400">Tidak ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  data: Array,
  types: Array,
  warehouse_outlets: Array,
  outlets: Array,
  filters: Object,
  total_per_type: Object
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const isSuperuser = computed(() => user.value.id_outlet == 1);

const selectedType = ref(props.filters.type || '');
const selectedWarehouse = ref(props.filters.warehouse_outlet_id || '');
const selectedOutlet = ref(props.filters.outlet_id || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');

// Add filtered warehouse outlets
const filteredWarehouseOutlets = ref(props.warehouse_outlets || []);

const expanded = ref({});
const details = ref({});
const loadingDetail = ref({});

// Watch for outlet changes to filter warehouse outlets
watch(selectedOutlet, async (newOutletId) => {
  // Reset warehouse outlet selection when outlet changes
  selectedWarehouse.value = '';
  
  if (newOutletId && isSuperuser.value) {
    // For superuser, fetch warehouse outlets for selected outlet
    try {
      const response = await axios.get(`/api/warehouse-outlets/by-outlet/${newOutletId}`);
      filteredWarehouseOutlets.value = response.data;
    } catch (error) {
      console.error('Error fetching warehouse outlets:', error);
      filteredWarehouseOutlets.value = [];
    }
  } else if (newOutletId && !isSuperuser.value) {
    // For regular user, filter from existing warehouse outlets
    filteredWarehouseOutlets.value = props.warehouse_outlets.filter(wo => wo.outlet_id == newOutletId);
  } else {
    // No outlet selected, show all warehouse outlets for superuser or empty for regular user
    if (isSuperuser.value) {
      filteredWarehouseOutlets.value = props.warehouse_outlets;
    } else {
      filteredWarehouseOutlets.value = props.warehouse_outlets.filter(wo => wo.outlet_id == user.value.id_outlet);
    }
  }
}, { immediate: true });

watch([selectedType, selectedWarehouse, selectedOutlet, from, to], () => {
  router.get(
    route('outlet-internal-use-waste.report-universal'),
    {
      type: selectedType.value,
      warehouse_outlet_id: selectedWarehouse.value,
      outlet_id: isSuperuser.value ? selectedOutlet.value : undefined,
      from: from.value,
      to: to.value
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true
    }
  );
});

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
      details.value[id] = res.data.details;
    }).catch(() => {
      details.value[id] = [];
    }).finally(() => {
      loadingDetail.value[id] = false;
    });
  }
}

function formatRupiah(val) {
  if (val === null || val === undefined || isNaN(val)) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0 });
}

function grandTotal(items) {
  if (!items || !items.length) return 0;
  return items.reduce((sum, item) => sum + (Number(item.subtotal_mac) || 0), 0);
}
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