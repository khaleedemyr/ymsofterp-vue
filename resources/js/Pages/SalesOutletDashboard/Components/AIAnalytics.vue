<template>
  <div class="mb-6 space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Sales Analytics</p>
          <h3 class="text-lg font-bold text-slate-900 mt-0.5">Analisa omzet otomatis</h3>
          <p class="text-xs text-slate-500 mt-1">
            Bandingkan periode terpilih vs rata-rata 3 bulan sebelumnya · decomposisi Pax vs Average Check · region, outlet, daypart, menu
          </p>
        </div>
        <div v-if="loading" class="text-sm text-indigo-600">
          <i class="fa-solid fa-spinner fa-spin mr-1"></i> Menyusun analisa…
        </div>
      </div>

      <div v-if="!loading && !data" class="px-5 py-8 text-center text-slate-400 text-sm">
        Analisa belum tersedia.
      </div>

      <div v-else-if="data" class="p-5 space-y-5">
        <!-- Headline / narrative -->
        <div
          class="rounded-xl border px-4 py-4"
          :class="severityBoxClass"
        >
          <div class="flex items-start gap-3">
            <div class="mt-0.5 rounded-lg p-2" :class="severityIconWrap">
              <i class="fa-solid text-sm" :class="severityIcon"></i>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-bold" :class="severityTitleClass">{{ data.driver?.label }}</p>
              <p class="mt-2 text-sm text-slate-700 whitespace-pre-line leading-relaxed">{{ data.narrative }}</p>
            </div>
          </div>
        </div>

        <!-- KPI comparison -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Omzet periode</p>
            <p class="mt-1 text-xl font-bold text-slate-900">{{ formatCurrency(data.current?.revenue) }}</p>
            <p class="text-xs mt-1" :class="pctClass(data.vs_avg_last_3?.revenue_pct)">
              {{ fmtPct(data.vs_avg_last_3?.revenue_pct) }} vs avg 3 bln
            </p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-sky-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Pax</p>
            <p class="mt-1 text-xl font-bold text-sky-900">{{ formatNumber(data.current?.pax) }}</p>
            <p class="text-xs mt-1" :class="pctClass(data.vs_avg_last_3?.pax_pct)">
              {{ fmtPct(data.vs_avg_last_3?.pax_pct) }} · share driver {{ data.driver?.pax_share_pct }}%
            </p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-amber-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Avg Check</p>
            <p class="mt-1 text-xl font-bold text-amber-900">{{ formatCurrency(data.current?.avg_check) }}</p>
            <p class="text-xs mt-1" :class="pctClass(data.vs_avg_last_3?.avg_check_pct)">
              {{ fmtPct(data.vs_avg_last_3?.avg_check_pct) }} · share driver {{ data.driver?.avg_check_share_pct }}%
            </p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-violet-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">AOV (per order)</p>
            <p class="mt-1 text-xl font-bold text-violet-900">{{ formatCurrency(data.current?.aov) }}</p>
            <p class="text-xs mt-1" :class="pctClass(data.vs_avg_last_3?.aov_pct)">
              {{ fmtPct(data.vs_avg_last_3?.aov_pct) }} vs avg 3 bln
            </p>
          </div>
        </div>

        <!-- Attribution bar -->
        <div class="rounded-xl border border-slate-100 p-4">
          <div class="flex items-center justify-between gap-2 mb-2">
            <p class="text-sm font-semibold text-slate-800">Atribusi perubahan omzet</p>
            <p class="text-xs text-slate-500">Omzet ≈ Pax × Average Check</p>
          </div>
          <div class="h-3 rounded-full overflow-hidden flex bg-slate-100">
            <div
              class="h-full bg-sky-500 transition-all"
              :style="{ width: (data.driver?.pax_share_pct || 0) + '%' }"
              :title="'Pax ' + data.driver?.pax_share_pct + '%'"
            ></div>
            <div
              class="h-full bg-amber-500 transition-all"
              :style="{ width: (data.driver?.avg_check_share_pct || 0) + '%' }"
              :title="'Avg Check ' + data.driver?.avg_check_share_pct + '%'"
            ></div>
          </div>
          <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
            <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-sky-500 mr-1"></span>Pax {{ data.driver?.pax_share_pct }}% (Δ {{ formatCurrency(data.driver?.pax_effect) }})</span>
            <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-amber-500 mr-1"></span>Avg Check {{ data.driver?.avg_check_share_pct }}% (Δ {{ formatCurrency(data.driver?.avg_check_effect) }})</span>
          </div>
        </div>

        <!-- 3-month table -->
        <div>
          <h4 class="text-sm font-semibold text-slate-800 mb-2">Perbandingan 3 bulan ke belakang</h4>
          <div class="overflow-x-auto border border-slate-100 rounded-xl">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 text-right">
                <tr>
                  <th class="px-3 py-2 text-left">Bulan</th>
                  <th class="px-3 py-2">Omzet</th>
                  <th class="px-3 py-2">Pax</th>
                  <th class="px-3 py-2">Avg Check</th>
                  <th class="px-3 py-2">periode vs bulan</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-t border-slate-100 bg-indigo-50/40 font-semibold">
                  <td class="px-3 py-2 text-left text-indigo-900">{{ data.period?.label }} (periode)</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(data.current?.revenue) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatNumber(data.current?.pax) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(data.current?.avg_check) }}</td>
                  <td class="px-3 py-2 text-right text-slate-400">—</td>
                </tr>
                <tr
                  v-for="m in data.compare_months"
                  :key="m.key"
                  class="border-t border-slate-50"
                >
                  <td class="px-3 py-2 text-left text-slate-700">{{ m.label }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(m.revenue) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatNumber(m.pax) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(m.avg_check) }}</td>
                  <td class="px-3 py-2 text-right" :class="pctClass(m.vs_current?.revenue_pct)">
                    {{ fmtPct(m.vs_current?.revenue_pct) }}
                  </td>
                </tr>
                <tr class="border-t border-slate-200 bg-slate-50 font-medium">
                  <td class="px-3 py-2 text-left">Rata-rata 3 bulan</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(data.avg_last_3_months?.revenue) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatNumber(data.avg_last_3_months?.pax) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(data.avg_last_3_months?.avg_check) }}</td>
                  <td class="px-3 py-2 text-right" :class="pctClass(data.vs_avg_last_3?.revenue_pct)">
                    {{ fmtPct(data.vs_avg_last_3?.revenue_pct) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Findings chips -->
        <div v-if="data.findings?.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div
            v-for="(f, idx) in data.findings"
            :key="idx"
            class="rounded-xl border px-4 py-3"
            :class="findingClass(f.severity)"
          >
            <p class="text-xs font-semibold uppercase tracking-wide opacity-70">{{ f.category }}</p>
            <p class="text-sm font-semibold mt-1">{{ f.headline }}</p>
            <p class="text-xs mt-1 opacity-90 leading-relaxed">{{ f.detail }}</p>
          </div>
        </div>

        <!-- Region + Outlet -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
          <div class="rounded-xl border border-slate-100 overflow-hidden">
            <div class="px-4 py-3 bg-rose-50 border-b border-rose-100">
              <p class="text-sm font-semibold text-rose-800">Region terlemah vs avg 3 bulan</p>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="text-xs text-slate-500 uppercase">
                  <tr>
                    <th class="px-3 py-2 text-left">Region</th>
                    <th class="px-3 py-2 text-right">Omzet %</th>
                    <th class="px-3 py-2 text-right">Pax %</th>
                    <th class="px-3 py-2 text-right">Check %</th>
                    <th class="px-3 py-2 text-left">Driver</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="r in data.regions?.worst || []" :key="r.region_name" class="border-t border-slate-50">
                    <td class="px-3 py-2">{{ r.region_name }}</td>
                    <td class="px-3 py-2 text-right" :class="pctClass(r.vs_avg_last_3?.revenue_pct)">{{ fmtPct(r.vs_avg_last_3?.revenue_pct) }}</td>
                    <td class="px-3 py-2 text-right" :class="pctClass(r.vs_avg_last_3?.pax_pct)">{{ fmtPct(r.vs_avg_last_3?.pax_pct) }}</td>
                    <td class="px-3 py-2 text-right" :class="pctClass(r.vs_avg_last_3?.avg_check_pct)">{{ fmtPct(r.vs_avg_last_3?.avg_check_pct) }}</td>
                    <td class="px-3 py-2 text-xs text-slate-600">{{ driverLabel(r.driver?.primary) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="rounded-xl border border-slate-100 overflow-hidden">
            <div class="px-4 py-3 bg-rose-50 border-b border-rose-100">
              <p class="text-sm font-semibold text-rose-800">Outlet paling drop (Δ omzet)</p>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="text-xs text-slate-500 uppercase">
                  <tr>
                    <th class="px-3 py-2 text-left">Outlet</th>
                    <th class="px-3 py-2 text-right">Δ Omzet</th>
                    <th class="px-3 py-2 text-right">Pax %</th>
                    <th class="px-3 py-2 text-right">Check %</th>
                    <th class="px-3 py-2 text-left">Driver</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="o in data.outlets?.worst || []" :key="o.outlet_code" class="border-t border-slate-50">
                    <td class="px-3 py-2">
                      <div class="font-medium text-slate-800">{{ o.outlet_name }}</div>
                      <div class="text-[11px] text-slate-400">{{ o.region_name }}</div>
                    </td>
                    <td class="px-3 py-2 text-right text-rose-700">{{ formatCurrency(o.vs_avg_last_3?.revenue_delta) }}</td>
                    <td class="px-3 py-2 text-right" :class="pctClass(o.vs_avg_last_3?.pax_pct)">{{ fmtPct(o.vs_avg_last_3?.pax_pct) }}</td>
                    <td class="px-3 py-2 text-right" :class="pctClass(o.vs_avg_last_3?.avg_check_pct)">{{ fmtPct(o.vs_avg_last_3?.avg_check_pct) }}</td>
                    <td class="px-3 py-2 text-xs text-slate-600">{{ driverLabel(o.driver_primary) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Daypart + weekday -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
          <div v-for="block in daypartCards" :key="block.key" class="rounded-xl border border-slate-100 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ block.label }}</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatCurrency(block.revenue) }}</p>
            <p class="text-xs mt-1" :class="pctClass(block.revenue_pct)">Omzet {{ fmtPct(block.revenue_pct) }}</p>
            <p class="text-xs text-slate-500 mt-1">Pax {{ fmtPct(block.pax_pct) }} · Check {{ fmtPct(block.check_pct) }}</p>
          </div>
        </div>

        <!-- Menu decliners / gainers -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
          <div class="rounded-xl border border-slate-100 overflow-hidden">
            <div class="px-4 py-3 bg-amber-50 border-b border-amber-100">
              <p class="text-sm font-semibold text-amber-900">Menu paling turun vs avg 3 bulan</p>
            </div>
            <ul class="divide-y divide-slate-50">
              <li
                v-for="item in data.menu?.top_decliners || []"
                :key="item.item_name"
                class="px-4 py-2.5 flex items-center justify-between gap-3 text-sm"
              >
                <span class="text-slate-800 truncate">{{ item.item_name }}</span>
                <span class="text-rose-600 font-medium whitespace-nowrap">{{ formatCurrency(item.revenue_delta) }}</span>
              </li>
            </ul>
          </div>
          <div class="rounded-xl border border-slate-100 overflow-hidden">
            <div class="px-4 py-3 bg-emerald-50 border-b border-emerald-100">
              <p class="text-sm font-semibold text-emerald-900">Menu paling naik vs avg 3 bulan</p>
            </div>
            <ul class="divide-y divide-slate-50">
              <li
                v-for="item in data.menu?.top_gainers || []"
                :key="item.item_name"
                class="px-4 py-2.5 flex items-center justify-between gap-3 text-sm"
              >
                <span class="text-slate-800 truncate">{{ item.item_name }}</span>
                <span class="text-emerald-600 font-medium whitespace-nowrap">{{ formatCurrency(item.revenue_delta) }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: { type: Object, default: null },
  loading: { type: Boolean, default: false },
});

const severityBoxClass = computed(() => {
  const d = props.data?.driver?.direction;
  if (d === 'down') return 'border-rose-200 bg-rose-50/60';
  if (d === 'up') return 'border-emerald-200 bg-emerald-50/60';
  return 'border-slate-200 bg-slate-50/60';
});
const severityIconWrap = computed(() => {
  const d = props.data?.driver?.direction;
  if (d === 'down') return 'bg-rose-100 text-rose-700';
  if (d === 'up') return 'bg-emerald-100 text-emerald-700';
  return 'bg-slate-100 text-slate-600';
});
const severityIcon = computed(() => {
  const d = props.data?.driver?.direction;
  if (d === 'down') return 'fa-arrow-trend-down';
  if (d === 'up') return 'fa-arrow-trend-up';
  return 'fa-minus';
});
const severityTitleClass = computed(() => {
  const d = props.data?.driver?.direction;
  if (d === 'down') return 'text-rose-900';
  if (d === 'up') return 'text-emerald-900';
  return 'text-slate-900';
});

const daypartCards = computed(() => {
  const d = props.data;
  if (!d) return [];
  return [
    {
      key: 'lunch',
      label: 'Lunch',
      revenue: d.daypart?.lunch?.current?.revenue,
      revenue_pct: d.daypart?.lunch?.vs_avg_last_3?.revenue_pct,
      pax_pct: d.daypart?.lunch?.vs_avg_last_3?.pax_pct,
      check_pct: d.daypart?.lunch?.vs_avg_last_3?.avg_check_pct,
    },
    {
      key: 'dinner',
      label: 'Dinner',
      revenue: d.daypart?.dinner?.current?.revenue,
      revenue_pct: d.daypart?.dinner?.vs_avg_last_3?.revenue_pct,
      pax_pct: d.daypart?.dinner?.vs_avg_last_3?.pax_pct,
      check_pct: d.daypart?.dinner?.vs_avg_last_3?.avg_check_pct,
    },
    {
      key: 'weekday',
      label: 'Weekday',
      revenue: d.weekday_weekend?.weekday?.current?.revenue,
      revenue_pct: d.weekday_weekend?.weekday?.vs_avg_last_3?.revenue_pct,
      pax_pct: d.weekday_weekend?.weekday?.vs_avg_last_3?.pax_pct,
      check_pct: d.weekday_weekend?.weekday?.vs_avg_last_3?.avg_check_pct,
    },
    {
      key: 'weekend',
      label: 'Weekend',
      revenue: d.weekday_weekend?.weekend?.current?.revenue,
      revenue_pct: d.weekday_weekend?.weekend?.vs_avg_last_3?.revenue_pct,
      pax_pct: d.weekday_weekend?.weekend?.vs_avg_last_3?.pax_pct,
      check_pct: d.weekday_weekend?.weekend?.vs_avg_last_3?.avg_check_pct,
    },
  ];
});

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(Number(amount || 0));
}

function formatNumber(n) {
  return new Intl.NumberFormat('id-ID').format(Number(n || 0));
}

function fmtPct(v) {
  if (v === null || v === undefined) return 'n/a';
  const n = Number(v);
  return (n >= 0 ? '+' : '') + n.toFixed(1) + '%';
}

function invertPct(v) {
  // vs_current was computed as current - month, so for "how does this month look vs current" invert
  if (v === null || v === undefined) return null;
  return -Number(v);
}

function pctClass(v) {
  if (v === null || v === undefined) return 'text-slate-400';
  if (v > 0) return 'text-emerald-600';
  if (v < 0) return 'text-rose-600';
  return 'text-slate-500';
}

function driverLabel(primary) {
  if (primary === 'pax') return 'Pax';
  if (primary === 'avg_check') return 'Avg Check';
  return 'Campuran';
}

function findingClass(severity) {
  if (severity === 'critical') return 'border-rose-200 bg-rose-50 text-rose-900';
  if (severity === 'warning') return 'border-amber-200 bg-amber-50 text-amber-900';
  if (severity === 'positive') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  return 'border-slate-200 bg-slate-50 text-slate-800';
}
</script>
