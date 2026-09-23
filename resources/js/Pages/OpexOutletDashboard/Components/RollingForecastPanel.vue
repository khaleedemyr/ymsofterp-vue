<template>
  <section class="mb-8">
    <div class="flex items-center gap-3 mb-3">
      <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
        <i class="fa-solid fa-chart-line text-sm"></i>
      </span>
      <div>
        <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-sky-700">Rolling Auto Forecast</h2>
        <p class="text-xs text-slate-500">
          Proyeksi bergerak dari monthly target + MTD (tidak mengubah Revenue Target / budget RO)
        </p>
      </div>
    </div>

    <div class="rounded-3xl bg-white border border-sky-100 shadow-sm p-5 sm:p-6">
      <div v-if="!outletId" class="py-8 text-center text-slate-400 text-sm">
        Pilih outlet untuk melihat forecast.
      </div>

      <div v-else-if="loading" class="py-10 text-center text-slate-400 text-sm">
        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Menghitung forecast…
      </div>

      <div v-else-if="error" class="rounded-2xl bg-rose-50 border border-rose-100 px-4 py-3 text-sm text-rose-700">
        {{ error }}
      </div>

      <div v-else-if="!data?.has_target" class="rounded-2xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-800">
        {{ data?.message || 'Belum ada monthly target di menu Revenue Target untuk bulan ini.' }}
      </div>

      <template v-else>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
          <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 inline-flex items-center gap-1">
              Monthly Target
              <CardHelpTip :text="helpTips.monthly_target" />
            </p>
            <p class="mt-2 text-xl font-bold text-slate-900">{{ formatCurrency(data.monthly_target) }}</p>
          </div>
          <div class="rounded-2xl bg-sky-50 border border-sky-100 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-600 inline-flex items-center gap-1">
              Actual MTD
              <CardHelpTip :text="helpTips.actual_mtd" />
            </p>
            <p class="mt-2 text-xl font-bold text-sky-900">{{ formatCurrency(data.actual_mtd) }}</p>
            <p class="text-xs text-sky-500 mt-1">s/d {{ data.as_of }} · pace {{ data.pace_factor }}</p>
          </div>
          <div
            class="rounded-2xl border p-4 col-span-2"
            :class="gapPositive ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100'"
          >
            <div class="flex items-center justify-between gap-2">
              <p
                class="text-xs font-semibold uppercase tracking-wide inline-flex items-center gap-1"
                :class="gapPositive ? 'text-emerald-700' : 'text-rose-700'"
              >
                Projected EOM (Realistis)
                <CardHelpTip :text="helpTips.projected_eom" />
              </p>
              <span
                class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"
                :class="gapPositive ? 'bg-emerald-200 text-emerald-800' : 'bg-rose-200 text-rose-800'"
              >
                {{ gapPositive ? 'On track+' : 'Under' }}
              </span>
            </div>
            <p
              class="mt-2 text-xl font-bold"
              :class="gapPositive ? 'text-emerald-900' : 'text-rose-900'"
            >
              {{ formatCurrency(data.projected_eom) }}
            </p>
            <p class="text-xs mt-1" :class="gapPositive ? 'text-emerald-600' : 'text-rose-600'">
              {{ data.pct_of_target }}% target · gap {{ formatCurrency(data.gap_vs_target) }} · mode {{ data.mode }}
            </p>
          </div>
        </div>

        <div v-if="data.scenarios" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
          <div
            v-for="(sc, key) in scenarioCards"
            :key="key"
            class="rounded-2xl border p-4"
            :class="sc.cardClass"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="text-xs font-bold uppercase tracking-wide inline-flex items-center gap-1" :class="sc.titleClass">
                {{ sc.label }}
                <CardHelpTip :text="helpTips[sc.key] || sc.note || ''" />
              </p>
              <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full" :class="sc.badgeClass">
                {{ sc.pct }}%
              </span>
            </div>
            <p class="mt-2 text-lg font-bold" :class="sc.valueClass">{{ formatCurrency(sc.eom) }}</p>
            <p class="text-xs mt-1" :class="sc.gapClass">Gap {{ formatCurrency(sc.gap) }}</p>
            <p class="mt-1.5 text-xs font-medium text-slate-600 inline-flex items-center gap-1">
              Pace {{ sc.pace != null ? sc.pace : '—' }}
              <CardHelpTip :text="helpTips.pace" />
            </p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-5 text-sm text-slate-600">
          <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5">
            <span class="font-semibold text-slate-800">{{ data.remaining_weekdays }}</span> weekday sisa
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 px-3 py-1.5 text-indigo-800">
            <span class="font-semibold">{{ data.remaining_weekends }}</span> weekend sisa (Sabtu–Minggu)
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-xl bg-amber-50 px-3 py-1.5 text-amber-800">
            <span class="font-semibold">{{ data.remaining_holidays }}</span> libur/event sisa
          </span>
        </div>

        <div class="mb-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-2">Actual / Skenario vs Baseline</h3>
          <apexchart
            v-if="chartSeries.length"
            type="line"
            height="300"
            :options="chartOptions"
            :series="chartSeries"
          />
        </div>

        <div v-if="data.history_compare?.length" class="mb-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-2">Compare 3 Bulan Belakang</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div
              v-for="h in data.history_compare"
              :key="h.month"
              class="rounded-2xl border border-slate-100 bg-slate-50 p-4"
            >
              <div class="font-semibold text-slate-800">{{ h.label }}</div>
              <div class="text-lg font-bold text-slate-900 mt-1">{{ formatCurrency(h.total) }}</div>
              <div class="mt-2 grid grid-cols-3 gap-1 text-xs text-slate-500">
                <div>WD avg<br><span class="font-medium text-slate-700">{{ formatCompact(h.avg_weekday) }}</span></div>
                <div>WE avg<br><span class="font-medium text-slate-700">{{ formatCompact(h.avg_weekend) }}</span></div>
                <div>Libur avg<br><span class="font-medium text-slate-700">{{ formatCompact(h.avg_holiday) }}</span></div>
              </div>
            </div>
          </div>
        </div>

        <div>
          <h3 class="text-sm font-semibold text-slate-800 mb-2">Day-by-day</h3>
          <div class="overflow-x-auto max-h-72 overflow-y-auto rounded-2xl border border-slate-100">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 sticky top-0 z-10">
                <tr class="text-left text-xs uppercase text-slate-500">
                  <th class="px-3 py-2">Tanggal</th>
                  <th class="px-3 py-2">Hari</th>
                  <th class="px-3 py-2">Tipe</th>
                  <th class="px-3 py-2 text-right">Actual</th>
                  <th class="px-3 py-2 text-right">Pesimis</th>
                  <th class="px-3 py-2 text-right">Realistis</th>
                  <th class="px-3 py-2 text-right">Optimis</th>
                  <th class="px-3 py-2 text-right">Baseline</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="d in data.days"
                  :key="d.forecast_date"
                  class="border-t border-slate-50"
                  :class="d.status === 'forecast' ? 'bg-sky-50/50' : ''"
                >
                  <td class="px-3 py-1.5 tabular-nums">{{ d.forecast_date }}</td>
                  <td class="px-3 py-1.5">{{ d.day_name }}</td>
                  <td class="px-3 py-1.5">
                    <span
                      class="inline-block text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded"
                      :class="dayTypeBadge(d.day_type)"
                    >{{ d.day_type }}</span>
                    <span v-if="d.holiday_name" class="ml-1 text-xs text-slate-400">{{ d.holiday_name }}</span>
                  </td>
                  <td class="px-3 py-1.5 text-right tabular-nums">
                    {{ d.actual != null ? formatCompact(d.actual) : '—' }}
                  </td>
                  <td class="px-3 py-1.5 text-right tabular-nums text-rose-700">{{ formatCompact(d.projected_pessimistic ?? d.projected) }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums font-medium text-sky-800">{{ formatCompact(d.projected_realistic ?? d.projected) }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700">{{ formatCompact(d.projected_optimistic ?? d.projected) }}</td>
                  <td class="px-3 py-1.5 text-right tabular-nums text-slate-500">{{ formatCompact(d.baseline) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import CardHelpTip from '@/Components/CardHelpTip.vue'

const props = defineProps({
  outletId: { type: [Number, String], default: null },
  month: { type: String, default: '' }, // YYYY-MM
})

const loading = ref(false)
const error = ref(null)
const data = ref(null)
const chartSeries = ref([])
const chartOptions = ref({
  chart: {
    id: 'opex-rolling-forecast',
    toolbar: { show: true },
    fontFamily: 'inherit',
    zoom: { enabled: false },
  },
  stroke: { width: [3, 2, 2, 2], curve: 'smooth', dashArray: [0, 0, 4, 6] },
  colors: ['#0284c7', '#f59e0b', '#ef4444', '#10b981'],
  grid: { padding: { bottom: 8 } },
  xaxis: {
    categories: [],
    tickAmount: 10,
    labels: {
      rotate: 0,
      hideOverlappingLabels: true,
      trim: false,
      style: { fontSize: '11px' },
    },
    title: { text: 'Tanggal', style: { fontSize: '11px', fontWeight: 500 } },
  },
  legend: { position: 'top' },
  tooltip: {
    shared: true,
    intersect: false,
    y: { formatter: (val) => formatCurrency(val) },
  },
  yaxis: { labels: { formatter: (val) => formatCompact(val), style: { fontSize: '11px' } } },
})

const gapPositive = computed(() => (data.value?.gap_vs_target ?? 0) >= 0)

const helpTips = {
  monthly_target:
    'Monthly target dari menu Revenue Target outlet untuk bulan ini.\n\nDipakai sebagai acuan baseline harian (dibagi weekday / weekend / libur).\nTidak diubah oleh Rolling Forecast.',
  actual_mtd:
    'Actual MTD = total revenue orders sampai kemarin (as_of).\n\nPace = Actual MTD ÷ expected-to-date (sum baseline s/d as_of).\nPace < 1 = di bawah plan; > 1 = di atas plan.',
  projected_eom:
    'Projected EOM patokan utama = skenario Realistis.\n\n= Actual MTD + sisa hari (blend 50% pace MTD + 50% baseline).\nGap = Projected EOM − Monthly Target.',
  pace:
    'Rumus Pace (sama untuk semua skenario):\n\nPace = Actual MTD ÷ Expected-to-date\n\n• Actual MTD = Σ revenue orders s/d as_of (kemarin)\n• Expected-to-date = Σ baseline harian s/d as_of\n• Baseline harian = pembagian monthly target per tipe hari (weekday / weekend / libur)\n\nClamp: min 0,45 · max 1,55\nJika belum ≥2 hari dengan baseline, atau MTD/expected = 0 → Pace = 1,00\n\nPace < 1 = di bawah plan · Pace > 1 = di atas plan',
  pessimistic:
    'Pesimis: sisa hari lanjut di pace MTD saat ini.\n\nProyeksi sisa hari ≈ baseline × Pace\nJika MTD lemah, sisa hari juga diasumsikan lemah.\nPaling konservatif dari tiga skenario.\n\nPace = Actual MTD ÷ Expected-to-date (clamp 0,45–1,55).',
  realistic:
    'Realistis: patokan utama projected EOM.\n\nProyeksi sisa hari = blend 50% (baseline × Pace) + 50% baseline plan.\nLebih seimbang antara tren aktual dan target harian.\n\nPace = Actual MTD ÷ Expected-to-date (clamp 0,45–1,55).',
  optimistic:
    'Optimis:\n• Jika Pace < 1 → sisa hari kembali ke baseline plan (tidak memaksa catch-up di atas plan)\n• Jika Pace ≥ 1 → sisa hari = baseline × Pace (upside dipertahankan)\n\nPace = Actual MTD ÷ Expected-to-date (clamp 0,45–1,55).',
}

const scenarioCards = computed(() => {
  const sc = data.value?.scenarios || {}
  const pace = data.value?.pace_factor != null ? Number(data.value.pace_factor) : null
  const mapStyle = (key, gap) => {
    const under = gap < 0
    if (key === 'pessimistic') {
      return {
        cardClass: 'border-rose-100 bg-rose-50/50',
        titleClass: 'text-rose-700',
        badgeClass: under ? 'bg-rose-200 text-rose-800' : 'bg-emerald-200 text-emerald-800',
        valueClass: 'text-rose-900',
        gapClass: under ? 'text-rose-600' : 'text-emerald-600',
      }
    }
    if (key === 'optimistic') {
      return {
        cardClass: 'border-emerald-100 bg-emerald-50/50',
        titleClass: 'text-emerald-700',
        badgeClass: under ? 'bg-amber-200 text-amber-800' : 'bg-emerald-200 text-emerald-800',
        valueClass: 'text-emerald-900',
        gapClass: under ? 'text-amber-700' : 'text-emerald-600',
      }
    }
    return {
      cardClass: 'border-sky-100 bg-sky-50/60 ring-1 ring-sky-200',
      titleClass: 'text-sky-700',
      badgeClass: under ? 'bg-rose-200 text-rose-800' : 'bg-emerald-200 text-emerald-800',
      valueClass: 'text-sky-900',
      gapClass: under ? 'text-rose-600' : 'text-emerald-600',
    }
  }

  return ['pessimistic', 'realistic', 'optimistic'].map((key) => {
    const row = sc[key] || {}
    const gap = Number(row.gap_vs_target || 0)
    return {
      key,
      label: row.label || key,
      note: row.note || '',
      eom: row.projected_eom || 0,
      gap,
      pct: row.pct_of_target || 0,
      pace,
      ...mapStyle(key, gap),
    }
  })
})

/** Full ISO dates for tooltip (index-aligned with chart categories). */
const chartDayDates = ref([])

watch(
  () => [props.outletId, props.month],
  () => {
    fetchForecast()
  },
  { immediate: true }
)

async function fetchForecast() {
  if (!props.outletId || !props.month) {
    data.value = null
    chartSeries.value = []
    chartDayDates.value = []
    error.value = null
    return
  }

  loading.value = true
  error.value = null
  try {
    const res = await axios.get('/api/outlet-dashboard/rolling-forecast', {
      params: {
        id_outlet: props.outletId,
        month: props.month,
      },
    })
    data.value = res.data
    buildChart(res.data)
  } catch (err) {
    error.value = err?.response?.data?.message || err?.message || 'Gagal memuat rolling forecast.'
    data.value = err?.response?.data || null
    chartSeries.value = []
    chartDayDates.value = []
  } finally {
    loading.value = false
  }
}

function buildChart(payload) {
  const days = payload?.days || []
  if (!days.length) {
    chartSeries.value = []
    chartDayDates.value = []
    return
  }

  chartDayDates.value = days.map((d) => d.forecast_date)
  const shortLabels = days.map((d) => String(Number(String(d.forecast_date).slice(8, 10))))

  chartOptions.value = {
    ...chartOptions.value,
    xaxis: {
      ...chartOptions.value.xaxis,
      categories: shortLabels,
      tickAmount: Math.min(15, days.length),
      labels: {
        rotate: 0,
        hideOverlappingLabels: true,
        style: { fontSize: '11px' },
      },
      title: { text: 'Tanggal', style: { fontSize: '11px', fontWeight: 500 } },
    },
    tooltip: {
      shared: true,
      intersect: false,
      x: {
        formatter: (_val, opts) => {
          const idx = opts?.dataPointIndex ?? 0
          return chartDayDates.value[idx] || String(_val)
        },
      },
      y: { formatter: (val) => formatCurrency(val) },
    },
  }

  // Past days: all scenario lines = actual; future = respective scenario
  const seriesDay = (field) =>
    days.map((d) => {
      if (d.status === 'actual') return Number(d.actual ?? d.projected) || 0
      return Number(d[field] ?? d.projected) || 0
    })

  chartSeries.value = [
    { name: 'Realistis', data: seriesDay('projected_realistic') },
    { name: 'Baseline Target', data: days.map((d) => Number(d.baseline) || 0) },
    { name: 'Pesimis', data: seriesDay('projected_pessimistic') },
    { name: 'Optimis', data: seriesDay('projected_optimistic') },
  ]
}

function dayTypeBadge(type) {
  switch (type) {
    case 'weekend':
      return 'bg-indigo-100 text-indigo-800'
    case 'holiday':
      return 'bg-amber-100 text-amber-800'
    case 'ramadan':
      return 'bg-violet-100 text-violet-800'
    default:
      return 'bg-slate-100 text-slate-700'
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(Number(value) || 0)
}

function formatCompact(value) {
  const n = Number(value) || 0
  if (Math.abs(n) >= 1_000_000_000) return 'Rp ' + (n / 1_000_000_000).toFixed(1) + 'M'
  if (Math.abs(n) >= 1_000_000) return 'Rp ' + (n / 1_000_000).toFixed(1) + 'jt'
  return 'Rp ' + n.toLocaleString('id-ID', { maximumFractionDigits: 0 })
}
</script>
