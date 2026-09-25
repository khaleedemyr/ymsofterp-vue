<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-500"></i>
            Report RWS Sudah Diterima
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Daftar Retail Warehouse Sale (RWS) yang sudah punya pasangan Retail Food Justus/Yuditama — tampil nomor RWS &amp; RF.
          </p>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden mb-6">
        <div class="p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Filter</h3>
          <form @submit.prevent="loadData" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
              <input type="date" v-model="filters.from_date" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
              <input type="date" v-model="filters.to_date" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
              <select v-model="filters.outlet_id" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Semua Outlet</option>
                <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
              <input
                type="text"
                v-model="filters.search"
                placeholder="Nomor RWS / RF / outlet..."
                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex flex-wrap gap-2">
              <button
                type="submit"
                :disabled="isLoading"
                class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50 font-semibold"
              >
                <i v-if="!isLoading" class="fa fa-search mr-2"></i>
                <i v-else class="fa fa-spinner fa-spin mr-2"></i>
                {{ isLoading ? 'Memuat...' : 'Load Data' }}
              </button>
              <button type="button" @click="clearFilters" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                <i class="fa fa-times mr-2"></i> Clear
              </button>
              <button
                type="button"
                @click="exportExcel"
                :disabled="!dataLoaded || !rows.length"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 font-semibold"
              >
                <i class="fa fa-file-excel mr-2"></i> Export Excel
              </button>
            </div>
          </form>
        </div>
      </div>

      <div v-if="!dataLoaded && !isLoading" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-8 text-center mb-6">
        <i class="fa fa-info-circle text-emerald-500 text-4xl mb-4"></i>
        <p class="text-gray-700 text-lg font-semibold mb-2">Data Belum Dimuat</p>
        <p class="text-gray-600">Pilih periode lalu klik Load Data untuk melihat RWS yang sudah diterima di Retail Food.</p>
      </div>

      <template v-else-if="dataLoaded">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <p class="text-sm font-medium text-gray-500">Total RWS</p>
            <p class="text-2xl font-semibold text-emerald-600 mt-1">{{ summary.total_rws || 0 }}</p>
          </div>
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <p class="text-sm font-medium text-gray-500">Total RF</p>
            <p class="text-2xl font-semibold text-blue-600 mt-1">{{ summary.total_rf || 0 }}</p>
          </div>
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <p class="text-sm font-medium text-gray-500">Total Nominal RWS</p>
            <p class="text-2xl font-semibold text-amber-600 mt-1">{{ formatCurrency(summary.total_amount) }}</p>
          </div>
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <p class="text-sm font-medium text-gray-500">Outlet</p>
            <p class="text-2xl font-semibold text-slate-700 mt-1">{{ summary.outlet_count || 0 }}</p>
          </div>
        </div>

        <div v-if="by_outlet.length" class="bg-white rounded-2xl shadow-2xl overflow-hidden mb-6">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Ringkasan per Outlet</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah RWS</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah RF</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Nominal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="o in by_outlet" :key="o.outlet_id" class="hover:bg-emerald-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ o.outlet_name }}</td>
                    <td class="px-4 py-3 text-right">{{ o.total_rws }}</td>
                    <td class="px-4 py-3 text-right">{{ o.total_rf || 0 }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ formatCurrency(o.total_amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-medium text-gray-900">Detail RWS ↔ Retail Food</h3>
              <p class="text-sm text-gray-500">{{ rows.length }} dokumen</p>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-emerald-50">
                  <tr>
                    <th class="px-3 py-3 text-left text-xs font-bold text-emerald-800 uppercase w-10"></th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-emerald-800 uppercase">Nomor RWS</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-emerald-800 uppercase">Tgl RWS</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-emerald-800 uppercase">Nomor RF</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-emerald-800 uppercase">Tgl RF</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-emerald-800 uppercase">Outlet</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-emerald-800 uppercase">Nominal RWS</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-emerald-800 uppercase">Nominal RF</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-emerald-800 uppercase">Match</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-if="!rows.length">
                    <td colspan="9" class="px-4 py-10 text-center text-gray-400">
                      Tidak ada RWS sudah diterima di periode ini.
                    </td>
                  </tr>
                  <template v-for="row in rows" :key="row.id">
                    <tr class="hover:bg-emerald-50/50 cursor-pointer" @click="toggleExpand(row.id)">
                      <td class="px-3 py-3 text-gray-400">
                        <i :class="expanded.includes(row.id) ? 'fa fa-chevron-down text-emerald-500' : 'fa fa-chevron-right'"></i>
                      </td>
                      <td class="px-4 py-3 font-mono font-semibold text-blue-700">
                        <a :href="`/retail-warehouse-sale/${row.id}`" class="hover:underline" @click.stop>{{ row.number }}</a>
                      </td>
                      <td class="px-4 py-3">{{ formatDate(row.sale_date) }}</td>
                      <td class="px-4 py-3 font-mono font-semibold text-emerald-700">
                        <a v-if="row.rf_id" :href="`/retail-food/${row.rf_id}`" class="hover:underline" @click.stop>{{ row.rf_number }}</a>
                        <span v-else>{{ row.rf_number || '-' }}</span>
                      </td>
                      <td class="px-4 py-3">{{ formatDate(row.rf_date) }}</td>
                      <td class="px-4 py-3 font-medium">{{ row.outlet_name || '-' }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(row.total_amount) }}</td>
                      <td class="px-4 py-3 text-right text-emerald-700">{{ formatCurrency(row.rf_amount) }}</td>
                      <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-800">
                          {{ row.match_type || '-' }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="expanded.includes(row.id)">
                      <td colspan="9" class="px-6 py-3 bg-gray-50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-3">
                          <div>
                            <p class="text-gray-500">Customer</p>
                            <p class="font-medium">{{ row.customer_name || '-' }}</p>
                          </div>
                          <div>
                            <p class="text-gray-500">Warehouse</p>
                            <p class="font-medium">{{ row.warehouse_name || '-' }}</p>
                          </div>
                          <div>
                            <p class="text-gray-500">Supplier RF</p>
                            <p class="font-medium">{{ row.rf_supplier_name || '-' }}</p>
                          </div>
                          <div>
                            <p class="text-gray-500">Notes</p>
                            <p class="font-medium">{{ row.notes || '-' }}</p>
                          </div>
                        </div>
                        <div v-if="row.items && row.items.length" class="text-sm">
                          <p class="font-semibold text-gray-700 mb-2">Item:</p>
                          <ul class="space-y-1">
                            <li v-for="(it, idx) in row.items" :key="idx" class="flex justify-between max-w-3xl">
                              <span>{{ it.item_name }} — {{ formatQty(it.qty) }} {{ it.unit || '' }} @ {{ formatCurrency(it.price) }}</span>
                              <span class="font-medium">{{ formatCurrency(it.subtotal) }}</span>
                            </li>
                          </ul>
                        </div>
                        <p v-else class="text-sm text-gray-400">Tidak ada detail item.</p>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLoading } from '@/Composables/useLoading';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  by_outlet: { type: Array, default: () => [] },
  summary: {
    type: Object,
    default: () => ({ total_rws: 0, total_amount: 0, total_rf: 0, outlet_count: 0 }),
  },
  outlets: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  dataLoaded: { type: Boolean, default: false },
});

const { showLoading, hideLoading } = useLoading();

const today = new Date().toISOString().split('T')[0];
const monthStart = (() => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
})();

const filters = ref({
  from_date: props.filters?.from_date || monthStart,
  to_date: props.filters?.to_date || today,
  outlet_id: props.filters?.outlet_id || '',
  search: props.filters?.search || '',
});

const dataLoaded = ref(props.dataLoaded || false);
const isLoading = ref(false);
const expanded = ref([]);

watch(() => props.dataLoaded, (v) => {
  dataLoaded.value = !!v;
  isLoading.value = false;
});

function toggleExpand(id) {
  const idx = expanded.value.indexOf(id);
  if (idx === -1) expanded.value.push(id);
  else expanded.value.splice(idx, 1);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(value) {
  if (value == null || value === '') return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}

function formatQty(value) {
  if (value == null) return '-';
  return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 3 });
}

function loadData() {
  isLoading.value = true;
  showLoading('Memuat RWS sudah diterima...', 'Mohon tunggu');
  router.get('/report-rws-received', { ...filters.value, load_data: 1 }, {
    preserveState: true,
    replace: true,
    onFinish: () => {
      isLoading.value = false;
      hideLoading();
    },
  });
}

function clearFilters() {
  filters.value = {
    from_date: monthStart,
    to_date: today,
    outlet_id: '',
    search: '',
  };
  dataLoaded.value = false;
  router.get('/report-rws-received', {}, { preserveState: true, replace: true });
}

function exportExcel() {
  const params = new URLSearchParams({
    from_date: filters.value.from_date || '',
    to_date: filters.value.to_date || '',
    outlet_id: filters.value.outlet_id || '',
    search: filters.value.search || '',
  });
  window.location.href = `/report-rws-received/export?${params.toString()}`;
}
</script>
