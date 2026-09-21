<template>
  <AppLayout>
    <div class="w-full py-8 px-4 md:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold flex items-center gap-2 text-gray-900">
            <i class="fa-solid fa-heart-pulse text-rose-500"></i>
            Dashboard Kesehatan Cost Gudang
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Ringkas anomali MAC &amp; stok warehouse. Detail scan di
            <a href="/warehouse-mac-anomaly-tracking" class="text-blue-600 underline">Warehouse MAC Anomaly</a>.
          </p>
        </div>
        <div class="flex gap-2">
          <a
            href="/warehouse-mac-anomaly-tracking"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-amber-600 text-white text-sm hover:bg-amber-700"
          >
            <i class="fa-solid fa-radar"></i> Scan detail
          </a>
          <button
            type="button"
            @click="loadSnapshot"
            :disabled="loading"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-blue-600 disabled:bg-blue-300 text-white text-sm hover:bg-blue-700"
          >
            <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }"></i> Muat ulang
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse</label>
            <select v-model="filters.warehouse_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Semua warehouse</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dari tanggal</label>
            <input v-model="filters.date_from" type="date" :min="historyCutoffDate" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai tanggal</label>
            <input v-model="filters.date_to" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div class="flex items-end">
            <button
              type="button"
              @click="loadSnapshot"
              :disabled="loading"
              class="w-full bg-gray-900 disabled:bg-gray-400 text-white px-4 py-2 rounded-md hover:bg-black transition"
            >
              Terapkan filter
            </button>
          </div>
        </div>
        <p v-if="summary" class="text-xs text-gray-500 mt-3">
          Cutoff history: <strong>{{ summary.history_cutoff_date }}</strong>
          · Periode history: {{ summary.date_from }} → {{ summary.date_to }}
          · 7 hari terakhir dari {{ summary.date_7_from }}
        </p>
      </div>

      <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 text-sm text-red-800">
        <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ error }}
      </div>

      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
          <p class="text-xs text-rose-600">Value yatim</p>
          <p class="text-2xl font-bold text-rose-900">{{ kpis.orphan_value ?? '—' }}</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <p class="text-xs text-amber-700">MAC stok terlalu besar</p>
          <p class="text-2xl font-bold text-amber-900">{{ kpis.absolute_high_stock ?? '—' }}</p>
        </div>
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
          <p class="text-xs text-orange-700">MAC stok minus</p>
          <p class="text-2xl font-bold text-orange-900">{{ kpis.negative_mac_stock ?? '—' }}</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <p class="text-xs text-blue-700">Anomali history 7 hari</p>
          <p class="text-2xl font-bold text-blue-900">{{ kpis.history_last_7_days ?? '—' }}</p>
        </div>
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
          <p class="text-xs text-indigo-700">Anomali history periode</p>
          <p class="text-2xl font-bold text-indigo-900">{{ kpis.history_in_period ?? '—' }}</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
          <p class="text-xs text-gray-600">Total (stok + history)</p>
          <p class="text-2xl font-bold text-gray-900">{{ kpis.total_anomalies ?? '—' }}</p>
        </div>
      </div>

      <div v-if="Object.keys(typeBreakdown).length" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Breakdown tipe anomali</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div v-for="(count, type) in typeBreakdown" :key="type" class="bg-gray-50 border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-600">{{ typeLabel(type) }}</p>
            <p class="text-xl font-bold text-gray-900">{{ count }}</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Top warehouse</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stok</th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Yatim</th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">History</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-if="!topWarehouses.length">
                  <td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ loading ? 'Memuat…' : 'Tidak ada data' }}</td>
                </tr>
                <tr v-for="row in topWarehouses" :key="row.warehouse_id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium text-gray-900">{{ row.warehouse_name }}</td>
                  <td class="px-4 py-3 text-right font-semibold text-red-600">{{ row.count }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ row.current_stock }}</td>
                  <td class="px-4 py-3 text-right text-rose-600">{{ row.orphan_value }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ row.history }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Sumber modul</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Modul</th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-if="!moduleBreakdown.length">
                  <td colspan="2" class="px-4 py-6 text-center text-gray-400">{{ loading ? 'Memuat…' : 'Tidak ada data' }}</td>
                </tr>
                <tr v-for="mod in moduleBreakdown" :key="(mod.reference_type || '') + mod.module_name" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <p class="font-medium text-gray-900">{{ mod.module_name }}</p>
                    <p class="text-xs text-gray-500">{{ mod.reference_label }}</p>
                  </td>
                  <td class="px-4 py-3 text-right font-semibold text-red-600">{{ mod.count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-800">Top item bermasalah</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barang</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Hit</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">MAC max</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Flags</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="!topItems.length">
                <td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ loading ? 'Memuat…' : 'Tidak ada data' }}</td>
              </tr>
              <tr v-for="item in topItems" :key="(item.warehouse_id || 0) + '-' + (item.item_id || item.inventory_item_id)" class="hover:bg-gray-50">
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-900">{{ item.item_name }}</p>
                  <p class="text-xs text-gray-500">{{ item.item_code || '—' }}</p>
                </td>
                <td class="px-4 py-3 text-gray-700">{{ item.warehouse_name }}</td>
                <td class="px-4 py-3 text-right font-semibold">{{ item.count }}</td>
                <td class="px-4 py-3 text-right font-mono text-red-700">{{ formatNum(item.max_mac) }}</td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="flag in item.flags"
                      :key="flag"
                      class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium"
                      :class="flag === 'orphan_value' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'"
                    >
                      {{ typeLabel(flag) }}
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Shortcut</h2>
        <div class="mb-4">
          <p class="text-xs font-semibold uppercase text-gray-500 mb-2">Cost / MAC</p>
          <div class="flex flex-wrap gap-2">
            <a
              v-for="s in costShortcuts"
              :key="s.route"
              :href="s.route"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-800 hover:bg-blue-50 hover:border-blue-200"
            >
              <i :class="s.icon" class="text-blue-600"></i>
              {{ s.label }}
            </a>
          </div>
        </div>
        <div>
          <p class="text-xs font-semibold uppercase text-gray-500 mb-2">Warehouse ops</p>
          <div class="flex flex-wrap gap-2">
            <a
              v-for="s in opsShortcuts"
              :key="s.route"
              :href="s.route"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-800 hover:bg-emerald-50 hover:border-emerald-200"
            >
              <i :class="s.icon" class="text-emerald-600"></i>
              {{ s.label }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  warehouses: { type: Array, default: () => [] },
  historyCutoffDate: { type: String, default: '2024-01-01' },
  defaultFilters: { type: Object, default: () => ({}) },
  shortcuts: { type: Array, default: () => [] },
});

const filters = ref({
  warehouse_id: props.defaultFilters.warehouse_id ?? '',
  date_from: props.defaultFilters.date_from,
  date_to: props.defaultFilters.date_to,
  max_mac: props.defaultFilters.max_mac ?? 10_000_000,
});

const loading = ref(false);
const error = ref('');
const kpis = ref({});
const typeBreakdown = ref({});
const moduleBreakdown = ref([]);
const topWarehouses = ref([]);
const topItems = ref([]);
const summary = ref(null);

const costShortcuts = computed(() => props.shortcuts.filter((s) => s.group === 'cost'));
const opsShortcuts = computed(() => props.shortcuts.filter((s) => s.group === 'ops'));

const typeLabel = (type) => {
  const map = {
    negative_mac: 'MAC minus',
    negative_new_cost: 'Biaya masuk minus',
    spike_percent: 'Lonjakan % MAC',
    spike_multiplier: 'MAC melonjak kelipatan',
    absolute_high: 'MAC terlalu besar',
    current_stock: 'Anomali stok saat ini',
    orphan_value: 'Value yatim (qty=0)',
  };
  return map[type] || type;
};

const formatNum = (value) => {
  const n = Number(value);
  if (Number.isNaN(n)) return value ?? '—';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n);
};

const loadSnapshot = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = {
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      max_mac: filters.value.max_mac,
    };
    if (filters.value.warehouse_id) {
      params.warehouse_id = filters.value.warehouse_id;
    }
    const { data } = await axios.get('/api/warehouse-cost-health-dashboard/snapshot', { params });
    if (data.status !== 'success') {
      throw new Error(data.message || 'Gagal memuat snapshot');
    }
    kpis.value = data.kpis || {};
    typeBreakdown.value = data.type_breakdown || {};
    moduleBreakdown.value = data.module_breakdown || [];
    topWarehouses.value = data.top_warehouses || [];
    topItems.value = data.top_items || [];
    summary.value = data.summary || null;
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'Gagal memuat snapshot';
    kpis.value = {};
    typeBreakdown.value = {};
    moduleBreakdown.value = [];
    topWarehouses.value = [];
    topItems.value = [];
    summary.value = null;
  } finally {
    loading.value = false;
  }
};

onMounted(loadSnapshot);
</script>
