<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  filters: Object,
  outlets: Array,
  kpis: Object,
  trend_months: Array,
  monthly_trend: Array,
  outlet_trend_series: Array,
  category_breakdown: Array,
  subcategory_breakdown: Array,
  outlet_composition: Array,
  movement_rows: Array,
  detail_rows: Array,
});

const outletId = ref(props.filters?.outlet_id || '');
const fromDate = ref(props.filters?.from_date || '');
const toDate = ref(props.filters?.to_date || '');
const selectedMovement = ref(null);
const chartMetric = ref('count'); // count | per_audit

const modalOpen = computed(() => !!selectedMovement.value);
const movementDetails = computed(() => {
  if (!selectedMovement.value) return [];
  const outletIdValue = Number(selectedMovement.value.outlet_id || 0);
  const monthKey = String(selectedMovement.value.month_key || '');
  return (props.detail_rows || []).filter((row) =>
    Number(row.outlet_id || 0) === outletIdValue
    && String(row.month_key || '') === monthKey
  );
});

const debouncedFilter = debounce(() => {
  router.get(route('qa2-audits.report-nc-dashboard'), {
    outlet_id: outletId.value,
    from_date: fromDate.value,
    to_date: toDate.value,
  }, {
    preserveState: true,
    replace: true,
  });
}, 250);

watch([outletId, fromDate, toDate], debouncedFilter);

function backToIndex() {
  router.visit(route('qa2-audits.index'));
}

function applyQuickRange(kind) {
  const today = new Date();
  const end = today.toISOString().slice(0, 10);
  const start = new Date(today);
  if (kind === '1m') start.setMonth(start.getMonth() - 1);
  if (kind === '3m') start.setMonth(start.getMonth() - 3);
  if (kind === '6m') start.setMonth(start.getMonth() - 6);
  if (kind === 'ytd') {
    start.setMonth(0);
    start.setDate(1);
  }
  fromDate.value = start.toISOString().slice(0, 10);
  toDate.value = end;
}

function resetFilter() {
  outletId.value = '';
  const today = new Date();
  fromDate.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
  toDate.value = today.toISOString().slice(0, 10);
}

function openMovementDetail(row) {
  selectedMovement.value = row;
}

function closeModal() {
  selectedMovement.value = null;
}

const exportUrl = computed(() => route('qa2-audits.report-nc-dashboard.export', {
  outlet_id: outletId.value || undefined,
  from_date: fromDate.value || undefined,
  to_date: toDate.value || undefined,
}));

const exportDetailUrl = computed(() => route('qa2-audits.report-nc-dashboard.export-detail', {
  outlet_id: outletId.value || undefined,
  from_date: fromDate.value || undefined,
  to_date: toDate.value || undefined,
}));

const hasChartData = computed(() => (props.monthly_trend || []).some((x) => Number(x.nc_count || 0) > 0));

const monthlyTrendOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false } },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 3 },
  xaxis: { categories: props.trend_months || [] },
  yaxis: {
    labels: {
      formatter: (v) => chartMetric.value === 'per_audit'
        ? Number(v || 0).toFixed(2)
        : Number(v || 0).toFixed(0),
    },
  },
  colors: ['#e11d48'],
}));
const monthlyTrendSeries = computed(() => ([
  {
    name: chartMetric.value === 'per_audit' ? 'NC / Audit' : 'NC',
    data: (props.monthly_trend || []).map((x) => (
      chartMetric.value === 'per_audit'
        ? Number(x.nc_per_audit || 0)
        : Number(x.nc_count || 0)
    )),
  },
]));

const outletTrendOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: false } },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  xaxis: { categories: props.trend_months || [] },
  yaxis: {
    labels: {
      formatter: (v) => chartMetric.value === 'per_audit'
        ? Number(v || 0).toFixed(2)
        : Number(v || 0).toFixed(0),
    },
  },
}));
const outletTrendSeries = computed(() =>
  (props.outlet_trend_series || []).map((row) => ({
    name: row.outlet_name,
    data: chartMetric.value === 'per_audit' ? (row.data_per_audit || []) : (row.data || []),
  })));

const categoryOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  xaxis: { categories: (props.category_breakdown || []).map((x) => x.label) },
  colors: ['#f97316'],
}));
const categorySeries = computed(() => ([{
  name: 'NC',
  data: (props.category_breakdown || []).map((x) => Number(x.value || 0)),
}]));

const subcategoryOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  xaxis: { categories: (props.subcategory_breakdown || []).map((x) => x.label) },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
  colors: ['#8b5cf6'],
}));
const subcategorySeries = computed(() => ([{
  name: 'NC',
  data: (props.subcategory_breakdown || []).map((x) => Number(x.value || 0)),
}]));

const outletCompositionOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  xaxis: { categories: (props.outlet_composition || []).map((x) => x.outlet_name) },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
  colors: ['#0ea5e9'],
}));
const outletCompositionSeries = computed(() => ([{
  name: 'NC',
  data: (props.outlet_composition || []).map((x) => Number(x.nc_count || 0)),
}]));

function deltaClass(delta) {
  if (Number(delta || 0) > 0) return 'text-rose-600';
  if (Number(delta || 0) < 0) return 'text-emerald-600';
  return 'text-gray-600';
}

function trendBadgeClass(trend) {
  if (trend === 'naik') return 'bg-rose-100 text-rose-700';
  if (trend === 'turun') return 'bg-emerald-100 text-emerald-700';
  return 'bg-gray-100 text-gray-700';
}

function trendIcon(trend) {
  if (trend === 'naik') return '↑';
  if (trend === 'turun') return '↓';
  return '→';
}
</script>

<template>
  <AppLayout title="QA2 NC Dashboard">
    <div class="space-y-6 p-4 sm:p-6">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900">QA2 NC Dashboard</h1>
            <p class="text-sm text-gray-500">Monitoring pergerakan NC bulanan per outlet, kategori, dan sub kategori.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="backToIndex">
              Back to QA2 Audits
            </button>
            <a :href="exportUrl" class="rounded-lg border border-emerald-300 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50">
              Export Movement
            </a>
            <a :href="exportDetailUrl" class="rounded-lg border border-sky-300 px-3 py-2 text-sm text-sky-700 hover:bg-sky-50">
              Export Detail NC
            </a>
          </div>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="grid gap-3 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Outlet</label>
            <select v-model="outletId" class="w-full rounded-lg border-gray-300 text-sm">
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="String(outlet.id_outlet)">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Date From</label>
            <input v-model="fromDate" type="date" class="w-full rounded-lg border-gray-300 text-sm">
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Date To</label>
            <input v-model="toDate" type="date" class="w-full rounded-lg border-gray-300 text-sm">
          </div>
          <div class="flex flex-wrap items-end gap-2">
            <button type="button" class="rounded-lg border border-indigo-300 px-2 py-2 text-xs text-indigo-700 hover:bg-indigo-50" @click="applyQuickRange('1m')">1M</button>
            <button type="button" class="rounded-lg border border-indigo-300 px-2 py-2 text-xs text-indigo-700 hover:bg-indigo-50" @click="applyQuickRange('3m')">3M</button>
            <button type="button" class="rounded-lg border border-indigo-300 px-2 py-2 text-xs text-indigo-700 hover:bg-indigo-50" @click="applyQuickRange('6m')">6M</button>
            <button type="button" class="rounded-lg border border-indigo-300 px-2 py-2 text-xs text-indigo-700 hover:bg-indigo-50" @click="applyQuickRange('ytd')">YTD</button>
            <button type="button" class="rounded-lg border border-gray-300 px-2 py-2 text-xs text-gray-700 hover:bg-gray-50" @click="resetFilter">Reset</button>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-gray-500">
          Metrik tren chart:
          <span class="font-medium text-gray-700">{{ chartMetric === 'per_audit' ? 'NC per Audit' : 'Jumlah NC' }}</span>
        </p>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-lg border px-3 py-1.5 text-xs font-medium"
            :class="chartMetric === 'count' ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="chartMetric = 'count'"
          >
            Jumlah NC
          </button>
          <button
            type="button"
            class="rounded-lg border px-3 py-1.5 text-xs font-medium"
            :class="chartMetric === 'per_audit' ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="chartMetric = 'per_audit'"
          >
            NC / Audit
          </button>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"><p class="text-xs text-gray-500">Total NC</p><p class="mt-1 text-2xl font-bold text-rose-600">{{ kpis?.total_nc || 0 }}</p></div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"><p class="text-xs text-gray-500">Total Audit</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ kpis?.total_audits || 0 }}</p></div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"><p class="text-xs text-gray-500">Avg NC / Audit</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ Number(kpis?.avg_nc_per_audit || 0).toFixed(2) }}</p></div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <p class="text-xs text-gray-500">MoM Delta NC</p>
          <p class="mt-1 text-2xl font-bold" :class="deltaClass(kpis?.mom_delta)">
            {{ (kpis?.mom_delta || 0) > 0 ? '+' : '' }}{{ kpis?.mom_delta || 0 }}
          </p>
          <p class="text-xs text-gray-500">{{ Number(kpis?.mom_delta_pct || 0).toFixed(2) }}%</p>
          <p v-if="kpis?.latest_month" class="mt-1 text-[11px] text-gray-400">
            {{ kpis?.previous_month || '-' }} → {{ kpis?.latest_month }}
          </p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <p class="text-xs text-gray-500">Highest NC Outlet</p>
          <p class="mt-1 truncate text-sm font-semibold text-gray-900">{{ kpis?.highest_outlet_name || '-' }}</p>
          <p class="text-lg font-bold text-rose-600">{{ kpis?.highest_outlet_nc || 0 }}</p>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-2">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">Monthly NC Trend</h3>
          <apexchart v-if="hasChartData" type="area" height="280" :options="monthlyTrendOptions" :series="monthlyTrendSeries" />
          <p v-else class="py-16 text-center text-sm text-gray-500">Belum ada data tren pada periode ini.</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">Outlet Trend Comparison (Top 5)</h3>
          <apexchart v-if="(outlet_trend_series || []).length" type="line" height="280" :options="outletTrendOptions" :series="outletTrendSeries" />
          <p v-else class="py-16 text-center text-sm text-gray-500">Belum ada data perbandingan outlet.</p>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 xl:col-span-1">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">NC by Category</h3>
          <apexchart type="bar" height="300" :options="categoryOptions" :series="categorySeries" />
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 xl:col-span-1">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">NC by Subcategory (Top 15)</h3>
          <apexchart type="bar" height="300" :options="subcategoryOptions" :series="subcategorySeries" />
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 xl:col-span-1">
          <h3 class="mb-3 text-sm font-semibold text-gray-900">NC Composition by Outlet</h3>
          <apexchart type="bar" height="300" :options="outletCompositionOptions" :series="outletCompositionSeries" />
        </div>
      </div>

      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="border-b border-gray-200 px-4 py-3">
          <h3 class="text-sm font-semibold text-gray-900">Pergerakan NC per Outlet (Month to Month)</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Bulan</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Outlet</th>
                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">NC</th>
                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">Prev NC</th>
                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">Delta</th>
                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">Delta %</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Trend</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="row in (movement_rows || [])" :key="`${row.month_key}-${row.outlet_id}`" class="cursor-pointer hover:bg-gray-50" @click="openMovementDetail(row)">
                <td class="px-3 py-2 text-sm text-gray-700">{{ row.month_key }}</td>
                <td class="px-3 py-2 text-sm text-gray-700">{{ row.outlet_name }}</td>
                <td class="px-3 py-2 text-right text-sm font-semibold text-gray-900">{{ row.nc_count }}</td>
                <td class="px-3 py-2 text-right text-sm text-gray-700">{{ row.prev_nc_count }}</td>
                <td class="px-3 py-2 text-right text-sm font-semibold" :class="deltaClass(row.delta)">
                  {{ Number(row.delta || 0) > 0 ? '+' : '' }}{{ row.delta }}
                </td>
                <td class="px-3 py-2 text-right text-sm text-gray-700">{{ Number(row.delta_pct || 0).toFixed(2) }}%</td>
                <td class="px-3 py-2 text-sm">
                  <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="trendBadgeClass(row.trend)">
                    <span>{{ trendIcon(row.trend) }}</span>
                    {{ row.trend }}
                  </span>
                </td>
              </tr>
              <tr v-if="!(movement_rows || []).length">
                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada data pergerakan NC pada periode ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="max-h-[85vh] w-full max-w-5xl overflow-hidden rounded-xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
            <h4 class="text-sm font-semibold text-gray-900">
              Detail NC - {{ selectedMovement?.outlet_name }} ({{ selectedMovement?.month_key }})
            </h4>
            <button type="button" class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50" @click="closeModal">Tutup</button>
          </div>
          <div class="max-h-[70vh] overflow-auto p-4">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Audit</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Kategori</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Sub Kategori</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Parameter</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Komentar</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="(row, index) in movementDetails" :key="`${row.audit_id}-${index}`">
                  <td class="px-3 py-2 text-xs text-gray-700">
                    <div class="font-semibold text-gray-900">{{ row.audit_number }}</div>
                    <div>{{ row.audit_datetime }}</div>
                  </td>
                  <td class="px-3 py-2 text-xs text-gray-700">{{ row.category_name }}</td>
                  <td class="px-3 py-2 text-xs text-gray-700">{{ row.subcategory_name }}</td>
                  <td class="px-3 py-2 text-xs text-gray-700">
                    <div class="font-semibold">{{ row.parameter_code }}</div>
                    <div>{{ row.parameter_text }}</div>
                  </td>
                  <td class="px-3 py-2 text-xs text-gray-700">{{ row.comment || '-' }}</td>
                </tr>
                <tr v-if="!movementDetails.length">
                  <td colspan="5" class="px-3 py-6 text-center text-xs text-gray-500">Tidak ada detail NC untuk outlet/bulan ini.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
