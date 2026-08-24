<template>
  <AppLayout>
    <div class="max-w-[1400px] mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-6">
        <i class="fa-solid fa-store text-blue-600"></i>
        Rekap Revenue Outlet
      </h1>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex flex-wrap items-center gap-4 mb-4">
          <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer select-none">
            <input
              v-model="compareEnabled"
              type="checkbox"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            Bandingkan periode
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ compareEnabled ? 'Periode A — From' : 'Tanggal From' }}
            </label>
            <input
              v-model="filters.date_from"
              type="date"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ compareEnabled ? 'Periode A — To' : 'Tanggal To' }}
            </label>
            <input
              v-model="filters.date_to"
              type="date"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>

          <template v-if="compareEnabled">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Periode B — From</label>
              <input
                v-model="filters.compare_from"
                type="date"
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Periode B — To</label>
              <input
                v-model="filters.compare_to"
                type="date"
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              />
            </div>
          </template>

          <div :class="compareEnabled ? 'lg:col-span-4' : 'md:col-span-2'" class="flex justify-end gap-2">
            <button
              type="button"
              @click="fetchReport"
              :disabled="loading"
              class="px-6 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {{ loading ? 'Memuat...' : 'Tampilkan' }}
            </button>
            <button
              type="button"
              @click="exportExcel"
              :disabled="loading || !canSubmit"
              class="px-6 py-2.5 rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
            >
              Export Excel
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-16 text-gray-500">Memuat data...</div>

      <div v-else-if="!showReport" class="text-center py-16 text-gray-400 bg-white rounded-xl shadow">
        Pilih rentang tanggal lalu klik <strong>Tampilkan</strong> untuk melihat rekap revenue outlet.
      </div>

      <template v-else>
        <!-- Column visibility + region controls -->
        <div class="bg-white rounded-xl shadow px-4 py-3 mb-4 flex flex-wrap items-center gap-3 justify-between">
          <div ref="columnMenuRef" class="relative">
            <button
              type="button"
              @click.stop="showColumnMenu = !showColumnMenu"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50"
            >
              <i class="fa-solid fa-columns"></i>
              Kolom
              <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
            </button>
            <div
              v-if="showColumnMenu"
              class="absolute left-0 top-full mt-1 z-30 w-64 bg-white border border-gray-200 rounded-xl shadow-lg p-3"
              @click.stop
            >
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tampilkan kolom</span>
                <button type="button" class="text-xs text-blue-600 hover:underline" @click="showAllColumns">
                  Semua
                </button>
              </div>
              <label
                v-for="metric in metrics"
                :key="metric.key"
                class="flex items-center gap-2 py-1.5 text-sm text-gray-700 cursor-pointer hover:bg-gray-50 rounded px-1"
              >
                <input
                  type="checkbox"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  :checked="visibleColumns[metric.key]"
                  @change="toggleColumn(metric.key)"
                />
                {{ metric.label }}
              </label>
              <p v-if="visibleMetrics.length === 0" class="text-xs text-amber-600 mt-2">
                Minimal tampilkan 1 kolom.
              </p>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              @click="expandAllRegions"
              class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50"
            >
              <i class="fa-solid fa-expand mr-1"></i> Expand semua
            </button>
            <button
              type="button"
              @click="collapseAllRegions"
              class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50"
            >
              <i class="fa-solid fa-compress mr-1"></i> Collapse semua
            </button>
          </div>
        </div>

        <!-- Normal table -->
        <div v-if="!isCompareMode" class="bg-white rounded-xl shadow overflow-x-auto">
          <table class="min-w-full text-sm border-collapse">
            <thead>
              <tr class="bg-gray-900 text-white">
                <th class="px-4 py-3 text-left min-w-[220px] sticky left-0 bg-gray-900 z-10">Outlet</th>
                <th
                  v-for="metric in visibleMetrics"
                  :key="metric.key"
                  class="px-4 py-3 text-right whitespace-nowrap"
                >
                  {{ metric.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in report.groups" :key="groupKey(group)">
                <tr
                  class="bg-indigo-50 border-t-2 border-indigo-200 cursor-pointer hover:bg-indigo-100 select-none"
                  @click="toggleRegion(group)"
                >
                  <td :colspan="1 + visibleMetrics.length" class="px-4 py-2 font-bold text-indigo-900 uppercase tracking-wide text-xs">
                    <i
                      class="fa-solid mr-2 w-3 text-center"
                      :class="isRegionExpanded(group) ? 'fa-chevron-down' : 'fa-chevron-right'"
                    ></i>
                    <i class="fa-solid fa-map-location-dot mr-2"></i>{{ group.region_name }}
                    <span class="ml-2 font-normal text-indigo-600 normal-case">
                      ({{ group.rows?.length || 0 }} outlet)
                    </span>
                  </td>
                </tr>
                <template v-if="isRegionExpanded(group)">
                  <tr
                    v-for="row in group.rows"
                    :key="row.outlet_id"
                    class="border-b border-gray-100 hover:bg-gray-50"
                  >
                    <td class="px-4 py-2.5 font-medium text-gray-800 sticky left-0 bg-white">{{ row.outlet_name }}</td>
                    <td
                      v-for="metric in visibleMetrics"
                      :key="metric.key"
                      class="px-4 py-2.5 text-right"
                      :class="metric.key === 'grand_total' || metric.key === 'avg_check' ? 'font-semibold' : ''"
                    >
                      {{ formatMetric(row[metric.key], metric.key) }}
                    </td>
                  </tr>
                  <tr class="bg-gray-100 font-semibold border-b-2 border-gray-300">
                    <td class="px-4 py-2.5 text-right text-gray-700 sticky left-0 bg-gray-100">Subtotal {{ group.region_name }}</td>
                    <td
                      v-for="metric in visibleMetrics"
                      :key="metric.key"
                      class="px-4 py-2.5 text-right"
                    >
                      {{ formatMetric(group.subtotal[metric.key], metric.key) }}
                    </td>
                  </tr>
                </template>
                <tr v-else class="bg-gray-50 border-b border-gray-200">
                  <td class="px-4 py-2 text-right text-xs text-gray-500 sticky left-0 bg-gray-50 italic">
                    Subtotal {{ group.region_name }}
                  </td>
                  <td
                    v-for="metric in visibleMetrics"
                    :key="metric.key"
                    class="px-4 py-2 text-right text-xs text-gray-600 font-medium"
                  >
                    {{ formatMetric(group.subtotal[metric.key], metric.key) }}
                  </td>
                </tr>
              </template>
            </tbody>
            <tfoot>
              <tr class="bg-blue-900 text-white font-bold">
                <td class="px-4 py-3 sticky left-0 bg-blue-900">GRAND TOTAL</td>
                <td
                  v-for="metric in visibleMetrics"
                  :key="metric.key"
                  class="px-4 py-3 text-right"
                >
                  {{ formatMetric(report.totals[metric.key], metric.key) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Compare table -->
        <div v-else class="bg-white rounded-xl shadow overflow-x-auto">
          <div class="px-4 py-3 border-b border-gray-200 text-xs text-gray-600 flex flex-wrap gap-4">
            <span>
              <span class="inline-block w-2 h-2 rounded-full bg-blue-600 mr-1"></span>
              A: {{ report.date_from }} s/d {{ report.date_to }}
            </span>
            <span>
              <span class="inline-block w-2 h-2 rounded-full bg-amber-500 mr-1"></span>
              B: {{ report.compare_from }} s/d {{ report.compare_to }}
            </span>
            <span class="text-gray-400">Selisih = A − B</span>
          </div>
          <table class="min-w-full text-sm border-collapse">
            <thead>
              <tr class="bg-gray-900 text-white">
                <th
                  rowspan="2"
                  class="px-4 py-3 text-left min-w-[200px] sticky left-0 bg-gray-900 z-10 border-r border-gray-700"
                >
                  Outlet
                </th>
                <th
                  v-for="metric in visibleMetrics"
                  :key="metric.key"
                  colspan="4"
                  class="px-2 py-2 text-center whitespace-nowrap border-l border-gray-700"
                >
                  {{ metric.label }}
                </th>
              </tr>
              <tr class="bg-gray-800 text-white text-xs">
                <template v-for="metric in visibleMetrics" :key="metric.key + '-sub'">
                  <th class="px-2 py-2 text-right whitespace-nowrap border-l border-gray-700 font-normal">A</th>
                  <th class="px-2 py-2 text-right whitespace-nowrap font-normal">B</th>
                  <th class="px-2 py-2 text-right whitespace-nowrap font-normal">Selisih</th>
                  <th class="px-2 py-2 text-right whitespace-nowrap font-normal">%</th>
                </template>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in report.groups" :key="groupKey(group)">
                <tr
                  class="bg-indigo-50 border-t-2 border-indigo-200 cursor-pointer hover:bg-indigo-100 select-none"
                  @click="toggleRegion(group)"
                >
                  <td :colspan="1 + visibleMetrics.length * 4" class="px-4 py-2 font-bold text-indigo-900 uppercase tracking-wide text-xs">
                    <i
                      class="fa-solid mr-2 w-3 text-center"
                      :class="isRegionExpanded(group) ? 'fa-chevron-down' : 'fa-chevron-right'"
                    ></i>
                    <i class="fa-solid fa-map-location-dot mr-2"></i>{{ group.region_name }}
                    <span class="ml-2 font-normal text-indigo-600 normal-case">
                      ({{ group.rows?.length || 0 }} outlet)
                    </span>
                  </td>
                </tr>
                <template v-if="isRegionExpanded(group)">
                  <tr
                    v-for="row in group.rows"
                    :key="row.outlet_id"
                    class="border-b border-gray-100 hover:bg-gray-50"
                  >
                    <td class="px-4 py-2.5 font-medium text-gray-800 sticky left-0 bg-white border-r border-gray-100">
                      {{ row.outlet_name }}
                    </td>
                    <template v-for="metric in visibleMetrics" :key="metric.key">
                      <td class="px-2 py-2 text-right whitespace-nowrap border-l border-gray-50">
                        {{ formatMetric(row[metric.key], metric.key) }}
                      </td>
                      <td class="px-2 py-2 text-right whitespace-nowrap text-gray-600">
                        {{ formatMetric(row[metric.key + '_b'], metric.key) }}
                      </td>
                      <td class="px-2 py-2 text-right whitespace-nowrap" :class="diffClass(row[metric.key + '_diff'])">
                        {{ formatDiff(row[metric.key + '_diff'], metric.key) }}
                      </td>
                      <td class="px-2 py-2 text-right whitespace-nowrap" :class="diffClass(row[metric.key + '_pct'])">
                        {{ formatPct(row[metric.key + '_pct']) }}
                      </td>
                    </template>
                  </tr>
                  <tr class="bg-gray-100 font-semibold border-b-2 border-gray-300">
                    <td class="px-4 py-2.5 text-right text-gray-700 sticky left-0 bg-gray-100 border-r border-gray-200">
                      Subtotal {{ group.region_name }}
                    </td>
                    <template v-for="metric in visibleMetrics" :key="metric.key">
                      <td class="px-2 py-2 text-right whitespace-nowrap border-l border-gray-200">
                        {{ formatMetric(group.subtotal[metric.key], metric.key) }}
                      </td>
                      <td class="px-2 py-2 text-right whitespace-nowrap">
                        {{ formatMetric(group.subtotal[metric.key + '_b'], metric.key) }}
                      </td>
                      <td class="px-2 py-2 text-right whitespace-nowrap" :class="diffClass(group.subtotal[metric.key + '_diff'])">
                        {{ formatDiff(group.subtotal[metric.key + '_diff'], metric.key) }}
                      </td>
                      <td class="px-2 py-2 text-right whitespace-nowrap" :class="diffClass(group.subtotal[metric.key + '_pct'])">
                        {{ formatPct(group.subtotal[metric.key + '_pct']) }}
                      </td>
                    </template>
                  </tr>
                </template>
                <tr v-else class="bg-gray-50 border-b border-gray-200">
                  <td class="px-4 py-2 text-right text-xs text-gray-500 sticky left-0 bg-gray-50 italic border-r border-gray-100">
                    Subtotal {{ group.region_name }}
                  </td>
                  <template v-for="metric in visibleMetrics" :key="metric.key">
                    <td class="px-2 py-2 text-right text-xs text-gray-600 font-medium whitespace-nowrap border-l border-gray-100">
                      {{ formatMetric(group.subtotal[metric.key], metric.key) }}
                    </td>
                    <td class="px-2 py-2 text-right text-xs text-gray-500 whitespace-nowrap">
                      {{ formatMetric(group.subtotal[metric.key + '_b'], metric.key) }}
                    </td>
                    <td class="px-2 py-2 text-right text-xs whitespace-nowrap" :class="diffClass(group.subtotal[metric.key + '_diff'])">
                      {{ formatDiff(group.subtotal[metric.key + '_diff'], metric.key) }}
                    </td>
                    <td class="px-2 py-2 text-right text-xs whitespace-nowrap" :class="diffClass(group.subtotal[metric.key + '_pct'])">
                      {{ formatPct(group.subtotal[metric.key + '_pct']) }}
                    </td>
                  </template>
                </tr>
              </template>
            </tbody>
            <tfoot>
              <tr class="bg-blue-900 text-white font-bold">
                <td class="px-4 py-3 sticky left-0 bg-blue-900 border-r border-blue-800">GRAND TOTAL</td>
                <template v-for="metric in visibleMetrics" :key="metric.key">
                  <td class="px-2 py-3 text-right whitespace-nowrap border-l border-blue-800">
                    {{ formatMetric(report.totals[metric.key], metric.key) }}
                  </td>
                  <td class="px-2 py-3 text-right whitespace-nowrap">
                    {{ formatMetric(report.totals[metric.key + '_b'], metric.key) }}
                  </td>
                  <td class="px-2 py-3 text-right whitespace-nowrap" :class="diffClass(report.totals[metric.key + '_diff'], true)">
                    {{ formatDiff(report.totals[metric.key + '_diff'], metric.key) }}
                  </td>
                  <td class="px-2 py-3 text-right whitespace-nowrap" :class="diffClass(report.totals[metric.key + '_pct'], true)">
                    {{ formatPct(report.totals[metric.key + '_pct']) }}
                  </td>
                </template>
              </tr>
            </tfoot>
          </table>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import Swal from 'sweetalert2';

const metrics = [
  { key: 'total_sales', label: 'Total Sales' },
  { key: 'discount', label: 'Discount' },
  { key: 'service_charge', label: 'Service Charge' },
  { key: 'pb1', label: 'PB 1' },
  { key: 'commfee', label: 'Commfee' },
  { key: 'grand_total', label: 'Grand Total' },
  { key: 'total_pax', label: 'Total Pax' },
  { key: 'avg_check', label: 'Average Check' },
];

const compareEnabled = ref(false);
const showColumnMenu = ref(false);
const columnMenuRef = ref(null);
const collapsedRegions = ref({});
const visibleColumns = reactive(
  Object.fromEntries(metrics.map((m) => [m.key, true]))
);

const filters = reactive({
  date_from: '',
  date_to: '',
  compare_from: '',
  compare_to: '',
});

const loading = ref(false);
const showReport = ref(false);
const report = ref({ groups: [], totals: {}, compare: false });

const isCompareMode = computed(() => !!report.value.compare);

const visibleMetrics = computed(() =>
  metrics.filter((m) => visibleColumns[m.key])
);

const canSubmit = computed(() => {
  if (!filters.date_from || !filters.date_to) return false;
  if (compareEnabled.value && (!filters.compare_from || !filters.compare_to)) return false;
  return true;
});

function groupKey(group) {
  return String(group.region_id ?? group.region_name ?? '0');
}

function isRegionExpanded(group) {
  return !collapsedRegions.value[groupKey(group)];
}

function toggleRegion(group) {
  const key = groupKey(group);
  collapsedRegions.value = {
    ...collapsedRegions.value,
    [key]: !collapsedRegions.value[key],
  };
}

function expandAllRegions() {
  collapsedRegions.value = {};
}

function collapseAllRegions() {
  const next = {};
  for (const group of report.value.groups || []) {
    next[groupKey(group)] = true;
  }
  collapsedRegions.value = next;
}

function toggleColumn(key) {
  const currentlyVisible = metrics.filter((m) => visibleColumns[m.key]).length;
  if (visibleColumns[key] && currentlyVisible <= 1) {
    return;
  }
  visibleColumns[key] = !visibleColumns[key];
}

function showAllColumns() {
  for (const metric of metrics) {
    visibleColumns[metric.key] = true;
  }
}

function onDocumentClick(e) {
  if (!showColumnMenu.value) return;
  if (columnMenuRef.value && !columnMenuRef.value.contains(e.target)) {
    showColumnMenu.value = false;
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));

function formatCurrency(val) {
  const n = Number(val) || 0;
  return n.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
}

function formatNumber(val) {
  const n = Number(val) || 0;
  return n.toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function formatMetric(val, key) {
  if (key === 'total_pax') return formatNumber(val);
  return formatCurrency(val);
}

function formatDiff(val, key) {
  if (val == null) return '-';
  const n = Number(val) || 0;
  const prefix = n > 0 ? '+' : '';
  if (key === 'total_pax') return prefix + formatNumber(n);
  return prefix + formatCurrency(n);
}

function formatPct(val) {
  if (val == null) return '-';
  const n = Number(val);
  const prefix = n > 0 ? '+' : '';
  return prefix + n.toFixed(2) + '%';
}

function diffClass(val, onDark = false) {
  if (val == null) return onDark ? 'text-blue-200' : 'text-gray-400';
  const n = Number(val);
  if (n > 0) return onDark ? 'text-emerald-300' : 'text-emerald-600';
  if (n < 0) return onDark ? 'text-rose-300' : 'text-rose-600';
  return onDark ? 'text-blue-100' : 'text-gray-500';
}

function buildParams() {
  const params = {
    date_from: filters.date_from,
    date_to: filters.date_to,
  };
  if (compareEnabled.value) {
    params.compare_from = filters.compare_from;
    params.compare_to = filters.compare_to;
  }
  return params;
}

function validateFilters() {
  if (!filters.date_from || !filters.date_to) {
    Swal.fire({ icon: 'warning', title: 'Tanggal wajib diisi', text: 'Pilih Tanggal From dan Tanggal To.' });
    return false;
  }
  if (compareEnabled.value && (!filters.compare_from || !filters.compare_to)) {
    Swal.fire({
      icon: 'warning',
      title: 'Periode banding wajib diisi',
      text: 'Isi Periode B From dan To, atau matikan Bandingkan periode.',
    });
    return false;
  }
  return true;
}

async function fetchReport() {
  if (!validateFilters()) return;

  loading.value = true;
  showReport.value = false;
  try {
    const res = await axios.get('/api/report/outlet-revenue-recap', { params: buildParams() });
    report.value = res.data;
    collapsedRegions.value = {};
    showReport.value = true;
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal memuat rekap revenue outlet.';
    Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
  } finally {
    loading.value = false;
  }
}

function exportExcel() {
  if (!validateFilters()) return;

  const query = new URLSearchParams(buildParams()).toString();
  window.open(`/report/outlet-revenue-recap/export?${query}`, '_blank');
}
</script>
