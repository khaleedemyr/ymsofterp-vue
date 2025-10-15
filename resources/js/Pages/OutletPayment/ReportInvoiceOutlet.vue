<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-2xl font-bold mb-6">Laporan Invoice Outlet</h1>
      <!-- Filter & Searchbar -->
      <form @submit.prevent="applyFilter" class="flex flex-wrap gap-2 mb-4 items-end">
        <div v-if="isSuperuser" class="flex items-center gap-2">
          <label class="text-sm">Outlet</label>
          <select v-model="filterOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua</option>
            <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Cari</label>
          <input v-model="filterSearch" type="text" class="form-input rounded border px-2 py-1" placeholder="Cari nomor invoice, GR, outlet..." />
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Tanggal Dari</label>
          <input v-model="filterFrom" type="date" class="form-input rounded border px-2 py-1" />
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Tanggal Sampai</label>
          <input v-model="filterTo" type="date" class="form-input rounded border px-2 py-1" />
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-bold">Terapkan</button>
      </form>
      <!-- Report Table -->
      <div class="overflow-x-auto bg-white rounded-xl shadow-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th></th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No Invoice</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tgl Invoice</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipe</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No GR/RWS</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tgl GR/RWS</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Total</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="row in props.data" :key="row.payment_id">
              <tr class="hover:bg-blue-50 transition-colors duration-200 group">
                <td class="px-2 py-2 align-top">
                  <button @click="toggleExpand(row.payment_id)" class="focus:outline-none transition-transform duration-200 group-hover:scale-110">
                    <span v-if="expanded[row.payment_id]">▼</span>
                    <span v-else>▶</span>
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{ row.payment_number }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(row.invoice_date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatWarehouse(row) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span :class="row.transaction_type === 'GR' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'" class="px-2 py-1 rounded-full text-xs font-semibold">
                    {{ row.transaction_type }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.gr_number }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(row.gr_date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatRupiah(row.payment_total) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.payment_status }}</td>
              </tr>
              <transition name="fade-expand">
                <tr v-if="expanded[row.payment_id]" :key="'detail-'+row.payment_id">
                  <td></td>
                  <td colspan="7" class="bg-gray-50 px-0 py-0">
                    <div class="rounded-lg border border-blue-100 bg-blue-50/60 shadow-inner mx-4 my-2 overflow-x-auto">
                      <div v-if="!props.details[row.gr_id] || !props.details[row.gr_id].length" class="text-gray-400 py-6 text-center">Tidak ada detail.</div>
                      <div v-else>
                        <table class="w-full text-xs">
                          <thead class="sticky top-0 z-10 bg-blue-100/80">
                            <tr>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Item</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Qty</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Unit</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Harga</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Subtotal</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="item in props.details[row.gr_id]" :key="item.item_name">
                              <td class="px-4 py-2 border-b">{{ item.item_name }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ item.qty }}</td>
                              <td class="px-4 py-2 border-b">{{ item.unit_name }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(item.price) }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(item.subtotal) }}</td>
                            </tr>
                            <tr>
                              <td colspan="4" class="px-4 py-2 border-b font-bold text-right bg-blue-50">Grand Total</td>
                              <td class="px-4 py-2 border-b font-bold text-right bg-blue-50">{{ formatRupiah(row.payment_total) }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
              </transition>
            </template>
            <tr v-if="!props.data.length">
              <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  data: Array,
  details: Object,
  outlets: Array,
  filters: Object,
  user_id_outlet: Number
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const isSuperuser = computed(() => props.user_id_outlet == 1);

const filterOutlet = ref(props.filters?.outlet_id || '');
const filterSearch = ref(props.filters?.search || '');
const filterFrom = ref(props.filters?.from || '');
const filterTo = ref(props.filters?.to || '');

const expanded = ref({});
function toggleExpand(id) {
  expanded.value[id] = !expanded.value[id];
}

function applyFilter() {
  router.get(route('report-invoice-outlet'), {
    outlet_id: isSuperuser.value ? filterOutlet.value : undefined,
    search: filterSearch.value || undefined,
    from: filterFrom.value || undefined,
    to: filterTo.value || undefined,
  }, {
    preserveState: true,
    replace: true
  });
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}
function formatRupiah(val) {
  if (val === null || val === undefined || isNaN(val)) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0 });
}

function formatWarehouse(row) {
  if (row.warehouse_name && row.warehouse_division_name) return row.warehouse_name + ' - ' + row.warehouse_division_name;
  if (row.warehouse_name) return row.warehouse_name;
  if (row.warehouse_division_name) return row.warehouse_division_name;
  return '-';
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