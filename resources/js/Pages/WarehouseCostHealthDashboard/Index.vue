<template>
  <AppLayout>
    <Head title="Warehouse Cost Health Dashboard" />

    <div class="w-full min-h-screen bg-gray-50">
      <div class="w-full px-4 md:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Warehouse Cost Health</h1>
            <p class="text-gray-600 mt-1">
              Ringkas transaksi gudang + kesehatan MAC / cost.
              <a href="/warehouse-mac-anomaly-tracking" class="text-blue-600 underline">Scan detail anomali</a>
            </p>
          </div>
          <button
            type="button"
            @click="loadSnapshot"
            :disabled="loading"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 disabled:bg-blue-300 text-white text-sm hover:bg-blue-700"
          >
            <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }"></i>
            {{ loading ? 'Memuat…' : 'Muat ulang' }}
          </button>
        </div>

        <!-- Filters: bulan seperti Sales Outlet Dashboard -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Periode (Bulan)</label>
              <input
                v-model="selectedMonth"
                type="month"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
              <p v-if="periodLabel" class="text-xs text-gray-500 mt-2">Periode: {{ periodLabel }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse</label>
              <select
                v-model="filters.warehouse_id"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Semua warehouse</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
              </select>
            </div>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                @click="resetFilters"
                class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
              >
                Reset
              </button>
              <button
                type="button"
                @click="loadSnapshot"
                :disabled="loading"
                class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
              >
                {{ loading ? 'Loading…' : 'Apply Filters' }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 text-sm text-red-800">
          <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ error }}
        </div>

        <div v-if="loading && !hasData" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
          <p class="text-gray-600 mt-2">Memuat data dashboard…</p>
        </div>

        <template v-else>
          <!-- Overview transaksi -->
          <div class="mb-2 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">Transaksi periode</h2>
            <span class="text-sm text-gray-500">Total: <strong>{{ formatNumber(transactions.total_transactions || 0) }}</strong></span>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3 mb-6">
            <a
              v-for="card in (transactions.summary || [])"
              :key="card.key"
              :href="card.route"
              class="bg-white rounded-lg shadow-sm border p-4 hover:border-blue-300 hover:shadow transition"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-xs font-medium text-gray-500 leading-tight">{{ card.label }}</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatNumber(card.count) }}</p>
                  <p v-if="card.amount != null" class="text-xs text-emerald-700 mt-1">{{ formatCurrency(card.amount) }}</p>
                </div>
                <i :class="[card.icon, 'text-blue-500 text-lg']"></i>
              </div>
              <p v-if="card.note" class="text-[10px] text-amber-600 mt-2">{{ card.note }}</p>
            </a>
          </div>

          <!-- Cost health KPIs -->
          <h2 class="text-lg font-semibold text-gray-800 mb-2">Kesehatan cost / MAC</h2>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
              <p class="text-xs text-rose-600">Value yatim</p>
              <p class="text-2xl font-bold text-rose-900">{{ kpis.orphan_value ?? 0 }}</p>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
              <p class="text-xs text-amber-700">MAC stok terlalu besar</p>
              <p class="text-2xl font-bold text-amber-900">{{ kpis.absolute_high_stock ?? 0 }}</p>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
              <p class="text-xs text-orange-700">MAC stok minus</p>
              <p class="text-2xl font-bold text-orange-900">{{ kpis.negative_mac_stock ?? 0 }}</p>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
              <p class="text-xs text-blue-700">Anomali history 7 hari</p>
              <p class="text-2xl font-bold text-blue-900">{{ kpis.history_last_7_days ?? 0 }}</p>
            </div>
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
              <p class="text-xs text-indigo-700">Anomali history periode</p>
              <p class="text-2xl font-bold text-indigo-900">{{ kpis.history_in_period ?? 0 }}</p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
              <p class="text-xs text-gray-600">Total anomali</p>
              <p class="text-2xl font-bold text-gray-900">{{ kpis.total_anomalies ?? 0 }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Recent transactions -->
            <div class="bg-white rounded-lg shadow-sm border">
              <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Transaksi terbaru</h2>
                <span class="text-xs text-gray-500">{{ (transactions.recent || []).length }} baris</span>
              </div>
              <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 sticky top-0">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jenis</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nomor</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-if="!(transactions.recent || []).length">
                      <td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada transaksi di periode ini</td>
                    </tr>
                    <tr v-for="(row, idx) in transactions.recent" :key="idx" class="hover:bg-gray-50">
                      <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ row.date }}</td>
                      <td class="px-4 py-2 text-gray-700">{{ row.type }}</td>
                      <td class="px-4 py-2">
                        <a v-if="row.url" :href="row.url" class="text-blue-600 hover:underline font-medium">{{ row.number }}</a>
                        <span v-else>{{ row.number }}</span>
                      </td>
                      <td class="px-4 py-2 text-gray-600 text-xs">{{ row.warehouse_name }}</td>
                      <td class="px-4 py-2">
                        <span v-if="row.status" class="inline-flex px-2 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-700">{{ row.status }}</span>
                        <span v-else class="text-gray-300">—</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Module breakdown cost -->
            <div class="bg-white rounded-lg shadow-sm border">
              <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Sumber modul anomali MAC</h2>
              </div>
              <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 sticky top-0">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Modul</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-if="!moduleBreakdown.length">
                      <td colspan="2" class="px-4 py-8 text-center text-gray-400">Tidak ada anomali</td>
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

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm border">
              <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Top warehouse (anomali)</h2>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Yatim</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">History</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-if="!topWarehouses.length">
                      <td colspan="4" class="px-4 py-6 text-center text-gray-400">Tidak ada data</td>
                    </tr>
                    <tr v-for="row in topWarehouses" :key="row.warehouse_id" class="hover:bg-gray-50">
                      <td class="px-4 py-3 font-medium text-gray-900">{{ row.warehouse_name }}</td>
                      <td class="px-4 py-3 text-right font-semibold text-red-600">{{ row.count }}</td>
                      <td class="px-4 py-3 text-right text-rose-600">{{ row.orphan_value }}</td>
                      <td class="px-4 py-3 text-right text-gray-700">{{ row.history }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border">
              <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Top item bermasalah</h2>
              </div>
              <div class="overflow-x-auto max-h-[360px] overflow-y-auto">
                <table class="w-full min-w-[640px] text-sm">
                  <thead class="bg-gray-50 sticky top-0">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barang</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Hit</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">MAC max</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-if="!topItems.length">
                      <td colspan="4" class="px-4 py-6 text-center text-gray-400">Tidak ada data</td>
                    </tr>
                    <tr v-for="item in topItems" :key="(item.warehouse_id || 0) + '-' + (item.item_id || item.inventory_item_id)" class="hover:bg-gray-50">
                      <td class="px-4 py-3">
                        <p class="font-medium text-gray-900">{{ item.item_name }}</p>
                        <p class="text-xs text-gray-500">{{ item.item_code || '—' }}</p>
                      </td>
                      <td class="px-4 py-3 text-gray-700">{{ item.warehouse_name }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ item.count }}</td>
                      <td class="px-4 py-3 text-right font-mono text-red-700">{{ formatNum(item.max_mac) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Daily activity strip -->
          <div v-if="(transactions.daily || []).length" class="bg-white rounded-lg shadow-sm border p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Aktivitas harian (GR / Transfer / Retail / DO / Adjustment)</h2>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">GR</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Transfer</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Retail</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">DO</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Adj</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="d in transactions.daily" :key="d.date" class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-gray-700">{{ d.date }}</td>
                    <td class="px-3 py-2 text-right">{{ d.gr }}</td>
                    <td class="px-3 py-2 text-right">{{ d.transfer }}</td>
                    <td class="px-3 py-2 text-right">{{ d.retail }}</td>
                    <td class="px-3 py-2 text-right">{{ d.do }}</td>
                    <td class="px-3 py-2 text-right">{{ d.adjustment }}</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ d.gr + d.transfer + d.retail + d.do + d.adjustment }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm border p-6">
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
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  warehouses: { type: Array, default: () => [] },
  historyCutoffDate: { type: String, default: '2024-01-01' },
  defaultFilters: { type: Object, default: () => ({}) },
  shortcuts: { type: Array, default: () => [] },
});

function currentMonthValue() {
  return new Date().toISOString().substring(0, 7);
}

function monthLabel(monthValue) {
  if (!monthValue) return '';
  const [year, month] = monthValue.split('-').map(Number);
  const label = new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
  return label.charAt(0).toUpperCase() + label.slice(1);
}

const selectedMonth = ref(props.defaultFilters.period || currentMonthValue());
const filters = ref({
  warehouse_id: props.defaultFilters.warehouse_id ?? '',
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
const periodMeta = ref(null);
const transactions = ref({ summary: [], recent: [], daily: [], total_transactions: 0 });

const periodLabel = computed(() => periodMeta.value?.label || monthLabel(selectedMonth.value));
const hasData = computed(() => (transactions.value.summary || []).length > 0 || Object.keys(kpis.value).length > 0);
const costShortcuts = computed(() => props.shortcuts.filter((s) => s.group === 'cost'));
const opsShortcuts = computed(() => props.shortcuts.filter((s) => s.group === 'ops'));

const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0);
const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
const formatNum = (value) => {
  const n = Number(value);
  if (Number.isNaN(n)) return value ?? '—';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n);
};

const resetFilters = () => {
  selectedMonth.value = currentMonthValue();
  filters.value.warehouse_id = '';
  loadSnapshot();
};

const loadSnapshot = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = {
      period: selectedMonth.value,
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
    periodMeta.value = data.period || null;
    transactions.value = data.transactions || { summary: [], recent: [], daily: [], total_transactions: 0 };
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'Gagal memuat snapshot';
    kpis.value = {};
    moduleBreakdown.value = [];
    topWarehouses.value = [];
    topItems.value = [];
    transactions.value = { summary: [], recent: [], daily: [], total_transactions: 0 };
  } finally {
    loading.value = false;
  }
};

watch(selectedMonth, () => {
  // auto-apply when month changes (mirip UX cepat)
});

onMounted(loadSnapshot);
</script>
