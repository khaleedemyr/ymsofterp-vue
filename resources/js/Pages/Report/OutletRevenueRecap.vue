<template>
  <AppLayout>
    <div class="max-w-[1400px] mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-6">
        <i class="fa-solid fa-store text-blue-600"></i>
        Rekap Revenue Outlet
      </h1>

      <div class="bg-white rounded-xl shadow p-6 mb-6 space-y-4">
        <div class="flex flex-wrap items-center gap-4">
          <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer select-none">
            <input
              v-model="compareEnabled"
              type="checkbox"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              @change="onCompareToggle"
            />
            Bandingkan periode
          </label>
          <span v-if="compareEnabled" class="text-xs text-gray-500">
            Bisa 2–{{ maxPeriods }} periode. Selisih/% dihitung vs Periode 1.
          </span>
        </div>

        <!-- Single period -->
        <div v-if="!compareEnabled" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal From</label>
            <input
              v-model="periods[0].from"
              type="date"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal To</label>
            <input
              v-model="periods[0].to"
              type="date"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div class="md:col-span-2 flex justify-end gap-2">
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

        <!-- Multi period -->
        <div v-else class="space-y-3">
          <div
            v-for="(period, index) in periods"
            :key="index"
            class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end p-3 rounded-lg bg-gray-50 border border-gray-100"
          >
            <div class="md:col-span-2 flex items-center gap-2 pb-2 md:pb-0">
              <span
                class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold text-white"
                :class="periodColor(index)"
              >
                P{{ index + 1 }}
              </span>
              <span class="text-sm font-medium text-gray-700">Periode {{ index + 1 }}</span>
            </div>
            <div class="md:col-span-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
              <input
                v-model="period.from"
                type="date"
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              />
            </div>
            <div class="md:col-span-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
              <input
                v-model="period.to"
                type="date"
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              />
            </div>
            <div class="md:col-span-2 flex justify-end">
              <button
                v-if="periods.length > 2"
                type="button"
                @click="removePeriod(index)"
                class="px-3 py-2 rounded-lg border border-rose-200 text-rose-600 text-sm hover:bg-rose-50"
                title="Hapus periode"
              >
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3">
            <button
              type="button"
              @click="addPeriod"
              :disabled="periods.length >= maxPeriods"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-dashed border-blue-300 text-blue-700 text-sm hover:bg-blue-50 disabled:opacity-40"
            >
              <i class="fa-solid fa-plus"></i>
              Tambah periode
              <span class="text-xs text-gray-400">({{ periods.length }}/{{ maxPeriods }})</span>
            </button>
            <div class="flex gap-2">
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
      </div>

      <div v-if="loading" class="text-center py-16 text-gray-500">Memuat data...</div>

      <div v-else-if="!showReport" class="text-center py-16 text-gray-400 bg-white rounded-xl shadow">
        Pilih rentang tanggal lalu klik <strong>Tampilkan</strong> untuk melihat rekap revenue outlet.
      </div>

      <template v-else>
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
                      {{ formatMetric(scalarVal(row, metric.key), metric.key) }}
                    </td>
                  </tr>
                  <tr class="bg-gray-100 font-semibold border-b-2 border-gray-300">
                    <td class="px-4 py-2.5 text-right text-gray-700 sticky left-0 bg-gray-100">Subtotal {{ group.region_name }}</td>
                    <td
                      v-for="metric in visibleMetrics"
                      :key="metric.key"
                      class="px-4 py-2.5 text-right"
                    >
                      {{ formatMetric(scalarVal(group.subtotal, metric.key), metric.key) }}
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
                    {{ formatMetric(scalarVal(group.subtotal, metric.key), metric.key) }}
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
                  {{ formatMetric(scalarVal(report.totals, metric.key), metric.key) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Compare multi-period table -->
        <div v-else class="bg-white rounded-xl shadow overflow-x-auto">
          <div class="px-4 py-3 border-b border-gray-200 text-xs text-gray-600 flex flex-wrap gap-4">
            <span
              v-for="(p, i) in report.periods"
              :key="i"
              class="inline-flex items-center gap-1.5"
            >
              <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold text-white" :class="periodColor(i)">
                {{ p.label }}
              </span>
              {{ p.from }} s/d {{ p.to }}
            </span>
            <span class="text-gray-400">Selisih/% = vs Periode 1</span>
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
                  :colspan="compareColspan"
                  class="px-2 py-2 text-center whitespace-nowrap border-l border-gray-700"
                >
                  {{ metric.label }}
                </th>
              </tr>
              <tr class="bg-gray-800 text-white text-xs">
                <template v-for="metric in visibleMetrics" :key="metric.key + '-sub'">
                  <th
                    v-for="(p, i) in report.periods"
                    :key="metric.key + '-p' + i"
                    class="px-2 py-2 text-right whitespace-nowrap border-l border-gray-700 font-normal"
                  >
                    {{ p.label }}
                  </th>
                  <template v-for="(p, i) in report.periods" :key="metric.key + '-d' + i">
                    <template v-if="i > 0">
                      <th class="px-2 py-2 text-right whitespace-nowrap font-normal text-gray-300">Δ{{ p.label }}</th>
                      <th class="px-2 py-2 text-right whitespace-nowrap font-normal text-gray-300">%{{ p.label }}</th>
                    </template>
                  </template>
                </template>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in report.groups" :key="groupKey(group)">
                <tr
                  class="bg-indigo-50 border-t-2 border-indigo-200 cursor-pointer hover:bg-indigo-100 select-none"
                  @click="toggleRegion(group)"
                >
                  <td
                    :colspan="1 + visibleMetrics.length * compareColspan"
                    class="px-4 py-2 font-bold text-indigo-900 uppercase tracking-wide text-xs"
                  >
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
                      <td
                        v-for="(p, i) in report.periods"
                        :key="metric.key + '-v' + i"
                        class="px-2 py-2 text-right whitespace-nowrap border-l border-gray-50"
                      >
                        {{ formatMetric(periodVal(row, metric.key, i), metric.key) }}
                      </td>
                      <template v-for="(p, i) in report.periods" :key="metric.key + '-diff' + i">
                        <template v-if="i > 0">
                          <td class="px-2 py-2 text-right whitespace-nowrap" :class="diffClass(periodDiff(row, metric.key, i))">
                            {{ formatDiff(periodDiff(row, metric.key, i), metric.key) }}
                          </td>
                          <td class="px-2 py-2 text-right whitespace-nowrap" :class="diffClass(periodPct(row, metric.key, i))">
                            {{ formatPct(periodPct(row, metric.key, i)) }}
                          </td>
                        </template>
                      </template>
                    </template>
                  </tr>
                  <tr class="bg-gray-100 font-semibold border-b-2 border-gray-300">
                    <td class="px-4 py-2.5 text-right text-gray-700 sticky left-0 bg-gray-100 border-r border-gray-200">
                      Subtotal {{ group.region_name }}
                    </td>
                    <template v-for="metric in visibleMetrics" :key="metric.key">
                      <td
                        v-for="(p, i) in report.periods"
                        :key="metric.key + '-sv' + i"
                        class="px-2 py-2 text-right whitespace-nowrap border-l border-gray-200"
                      >
                        {{ formatMetric(periodVal(group.subtotal, metric.key, i), metric.key) }}
                      </td>
                      <template v-for="(p, i) in report.periods" :key="metric.key + '-sd' + i">
                        <template v-if="i > 0">
                          <td class="px-2 py-2 text-right whitespace-nowrap" :class="diffClass(periodDiff(group.subtotal, metric.key, i))">
                            {{ formatDiff(periodDiff(group.subtotal, metric.key, i), metric.key) }}
                          </td>
                          <td class="px-2 py-2 text-right whitespace-nowrap" :class="diffClass(periodPct(group.subtotal, metric.key, i))">
                            {{ formatPct(periodPct(group.subtotal, metric.key, i)) }}
                          </td>
                        </template>
                      </template>
                    </template>
                  </tr>
                </template>
                <tr v-else class="bg-gray-50 border-b border-gray-200">
                  <td class="px-4 py-2 text-right text-xs text-gray-500 sticky left-0 bg-gray-50 italic border-r border-gray-100">
                    Subtotal {{ group.region_name }}
                  </td>
                  <template v-for="metric in visibleMetrics" :key="metric.key">
                    <td
                      v-for="(p, i) in report.periods"
                      :key="metric.key + '-csv' + i"
                      class="px-2 py-2 text-right text-xs text-gray-600 font-medium whitespace-nowrap border-l border-gray-100"
                    >
                      {{ formatMetric(periodVal(group.subtotal, metric.key, i), metric.key) }}
                    </td>
                    <template v-for="(p, i) in report.periods" :key="metric.key + '-csd' + i">
                      <template v-if="i > 0">
                        <td class="px-2 py-2 text-right text-xs whitespace-nowrap" :class="diffClass(periodDiff(group.subtotal, metric.key, i))">
                          {{ formatDiff(periodDiff(group.subtotal, metric.key, i), metric.key) }}
                        </td>
                        <td class="px-2 py-2 text-right text-xs whitespace-nowrap" :class="diffClass(periodPct(group.subtotal, metric.key, i))">
                          {{ formatPct(periodPct(group.subtotal, metric.key, i)) }}
                        </td>
                      </template>
                    </template>
                  </template>
                </tr>
              </template>
            </tbody>
            <tfoot>
              <tr class="bg-blue-900 text-white font-bold">
                <td class="px-4 py-3 sticky left-0 bg-blue-900 border-r border-blue-800">GRAND TOTAL</td>
                <template v-for="metric in visibleMetrics" :key="metric.key">
                  <td
                    v-for="(p, i) in report.periods"
                    :key="metric.key + '-tv' + i"
                    class="px-2 py-3 text-right whitespace-nowrap border-l border-blue-800"
                  >
                    {{ formatMetric(periodVal(report.totals, metric.key, i), metric.key) }}
                  </td>
                  <template v-for="(p, i) in report.periods" :key="metric.key + '-td' + i">
                    <template v-if="i > 0">
                      <td class="px-2 py-3 text-right whitespace-nowrap" :class="diffClass(periodDiff(report.totals, metric.key, i), true)">
                        {{ formatDiff(periodDiff(report.totals, metric.key, i), metric.key) }}
                      </td>
                      <td class="px-2 py-3 text-right whitespace-nowrap" :class="diffClass(periodPct(report.totals, metric.key, i), true)">
                        {{ formatPct(periodPct(report.totals, metric.key, i)) }}
                      </td>
                    </template>
                  </template>
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

const maxPeriods = 6;

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

const periodColors = [
  'bg-blue-600',
  'bg-amber-500',
  'bg-emerald-600',
  'bg-violet-600',
  'bg-rose-500',
  'bg-cyan-600',
];

const compareEnabled = ref(false);
const showColumnMenu = ref(false);
const columnMenuRef = ref(null);
const collapsedRegions = ref({});
const visibleColumns = reactive(
  Object.fromEntries(metrics.map((m) => [m.key, true]))
);

const periods = ref([
  { from: '', to: '' },
]);

const loading = ref(false);
const showReport = ref(false);
const report = ref({ groups: [], totals: {}, compare: false, periods: [] });

const isCompareMode = computed(() => !!report.value.compare);

const visibleMetrics = computed(() =>
  metrics.filter((m) => visibleColumns[m.key])
);

const compareColspan = computed(() => {
  const n = report.value.period_count || report.value.periods?.length || 2;
  // P1..Pn values + for each i>0: Δ and %
  return n + (n - 1) * 2;
});

const canSubmit = computed(() => {
  if (!periods.value.length) return false;
  return periods.value.every((p) => p.from && p.to);
});

function periodColor(index) {
  return periodColors[index % periodColors.length];
}

function onCompareToggle() {
  if (compareEnabled.value) {
    while (periods.value.length < 2) {
      periods.value.push({ from: '', to: '' });
    }
  } else {
    periods.value = [{ from: periods.value[0]?.from || '', to: periods.value[0]?.to || '' }];
  }
}

function addPeriod() {
  if (periods.value.length >= maxPeriods) return;
  periods.value.push({ from: '', to: '' });
}

function removePeriod(index) {
  if (periods.value.length <= 2) return;
  periods.value.splice(index, 1);
}

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

function scalarVal(row, key) {
  const v = row?.[key];
  if (Array.isArray(v)) return v[0] ?? 0;
  return v ?? 0;
}

function periodVal(row, key, index) {
  const v = row?.[key];
  if (Array.isArray(v)) return v[index] ?? 0;
  return index === 0 ? (v ?? 0) : 0;
}

function periodDiff(row, key, index) {
  const v = row?.[key + '_diff'];
  if (Array.isArray(v)) return v[index] ?? null;
  return null;
}

function periodPct(row, key, index) {
  const v = row?.[key + '_pct'];
  if (Array.isArray(v)) return v[index] ?? null;
  return null;
}

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
  const list = compareEnabled.value
    ? periods.value
    : [periods.value[0]];

  return {
    periods: list.map((p) => ({ from: p.from, to: p.to })),
  };
}

function validateFilters() {
  const list = compareEnabled.value ? periods.value : [periods.value[0]];

  for (let i = 0; i < list.length; i++) {
    if (!list[i].from || !list[i].to) {
      Swal.fire({
        icon: 'warning',
        title: 'Tanggal wajib diisi',
        text: `Lengkapi From/To untuk Periode ${i + 1}.`,
      });
      return false;
    }
  }

  if (compareEnabled.value && list.length < 2) {
    Swal.fire({
      icon: 'warning',
      title: 'Minimal 2 periode',
      text: 'Mode banding membutuhkan minimal 2 periode.',
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
    const params = buildParams();
    const qs = new URLSearchParams();
    params.periods.forEach((p, i) => {
      qs.append(`periods[${i}][from]`, p.from);
      qs.append(`periods[${i}][to]`, p.to);
    });
    const res = await axios.get(`/api/report/outlet-revenue-recap?${qs.toString()}`);
    report.value = res.data;
    collapsedRegions.value = {};
    showReport.value = true;
  } catch (e) {
    const msg = e?.response?.data?.message
      || Object.values(e?.response?.data?.errors || {})?.[0]?.[0]
      || 'Gagal memuat rekap revenue outlet.';
    Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
  } finally {
    loading.value = false;
  }
}

function exportExcel() {
  if (!validateFilters()) return;

  const params = buildParams();
  const qs = new URLSearchParams();
  params.periods.forEach((p, i) => {
    qs.append(`periods[${i}][from]`, p.from);
    qs.append(`periods[${i}][to]`, p.to);
  });
  window.open(`/report/outlet-revenue-recap/export?${qs.toString()}`, '_blank');
}
</script>
