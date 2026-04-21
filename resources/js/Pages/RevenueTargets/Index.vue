<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  selectedOutletId: { type: Number, default: 0 },
  selectedMonth: { type: String, default: '' },
  monthlyTarget: { type: [Number, String, null], default: null },
  existingForecasts: { type: Array, default: () => [] },
  canSelectOutlet: { type: Boolean, default: false },
})

const page = usePage()
const selectedOutletId = ref(props.selectedOutletId || 0)
const selectedMonth = ref(props.selectedMonth || new Date().toISOString().slice(0, 7))
const monthlyTarget = ref(props.monthlyTarget ?? '')
const saving = ref(false)
const suggesting = ref(false)
const suggestError = ref('')
const suggestInfo = ref(null)
const holidays = ref([])
const bulkStartDate = ref('')
const bulkEndDate = ref('')
const bulkMode = ref('set')
const bulkValue = ref('')
const bulkPercent = ref('')

const forecasts = ref([])

function formatLocalDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function parseFormattedNumber(value) {
  if (value === null || value === undefined || value === '') return null
  const raw = String(value).trim().toLowerCase()
  let multiplier = 1

  if (/(k|rb)$/.test(raw)) {
    multiplier = 1_000
  } else if (/(jt|juta)$/.test(raw)) {
    multiplier = 1_000_000
  } else if (/(m|mil|miliar)$/.test(raw)) {
    multiplier = 1_000_000_000
  } else if (/(t|triliun)$/.test(raw)) {
    multiplier = 1_000_000_000_000
  }

  const normalized = raw
    .trim()
    .replace(/\s/g, '')
    .replace(/(k|rb|jt|juta|m|mil|miliar|t|triliun)$/g, '')
    .replace(/\./g, '')
    .replace(',', '.')
    .replace(/[^0-9.-]/g, '')
  if (!normalized || normalized === '-' || normalized === '.') return null
  const num = Number(normalized)
  return Number.isFinite(num) ? num * multiplier : null
}

function formatNumberId(value) {
  const num = parseFormattedNumber(value)
  if (num === null) return ''
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(num)
}

function toEditableNumber(value) {
  const num = parseFormattedNumber(value)
  if (num === null) return ''
  return String(num)
}

function buildDaysInMonth(monthStr) {
  if (!monthStr || !/^\d{4}-\d{2}$/.test(monthStr)) return []
  const [year, month] = monthStr.split('-').map(Number)
  const daysCount = new Date(year, month, 0).getDate()
  const mapped = new Map(
    (props.existingForecasts || []).map((x) => {
      const key = String(x.forecast_date).slice(0, 10)
      return [key, x.forecast_revenue]
    })
  )

  const rows = []
  for (let day = 1; day <= daysCount; day++) {
    const date = new Date(year, month - 1, day)
    const iso = formatLocalDate(date)
    const dayOfWeek = date.getDay()
    const holiday = holidays.value.find((h) => String(h.tgl_libur).slice(0, 10) === iso)
    rows.push({
      forecast_date: iso,
      day_name: date.toLocaleDateString('id-ID', { weekday: 'long' }),
      is_weekend: dayOfWeek === 0 || dayOfWeek === 6,
      is_holiday: Boolean(holiday),
      holiday_desc: holiday?.keterangan || '',
      forecast_revenue: mapped.has(iso) ? formatNumberId(mapped.get(iso)) : '',
    })
  }
  return rows
}

function resetFormFromProps() {
  selectedOutletId.value = props.selectedOutletId || 0
  selectedMonth.value = props.selectedMonth || new Date().toISOString().slice(0, 7)
  monthlyTarget.value = formatNumberId(props.monthlyTarget ?? '')
  forecasts.value = buildDaysInMonth(selectedMonth.value)
  bulkStartDate.value = forecasts.value[0]?.forecast_date || ''
  bulkEndDate.value = forecasts.value[forecasts.value.length - 1]?.forecast_date || ''
}

watch(
  () => [props.selectedOutletId, props.selectedMonth, props.monthlyTarget, props.existingForecasts],
  () => resetFormFromProps(),
  { immediate: true, deep: true }
)

watch(selectedMonth, (newMonth) => {
  forecasts.value = buildDaysInMonth(newMonth)
  bulkStartDate.value = forecasts.value[0]?.forecast_date || ''
  bulkEndDate.value = forecasts.value[forecasts.value.length - 1]?.forecast_date || ''
})

watch(holidays, () => {
  forecasts.value = buildDaysInMonth(selectedMonth.value)
}, { deep: true })

const totalForecast = computed(() =>
  forecasts.value.reduce((sum, r) => sum + (parseFormattedNumber(r.forecast_revenue) || 0), 0)
)
const totalHolidayDays = computed(() => forecasts.value.filter((r) => r.is_holiday).length)
const totalWeekendDays = computed(() => forecasts.value.filter((r) => !r.is_holiday && r.is_weekend).length)

function loadData() {
  router.get(
    route('outlet-revenue-targets.index'),
    {
      outlet_id: selectedOutletId.value,
      month: selectedMonth.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
    }
  )
}

function save() {
  saving.value = true
  const normalizedMonthlyTarget = parseFormattedNumber(monthlyTarget.value)
  const normalizedForecasts = forecasts.value.map((row) => ({
    forecast_date: row.forecast_date,
    forecast_revenue: parseFormattedNumber(row.forecast_revenue),
  }))
  router.post(
    route('outlet-revenue-targets.store'),
    {
      outlet_id: selectedOutletId.value,
      month: selectedMonth.value,
      monthly_target: normalizedMonthlyTarget ?? 0,
      forecasts: normalizedForecasts,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        saving.value = false
      },
    }
  )
}

async function suggestAI() {
  suggestError.value = ''
  suggestInfo.value = null

  if (!selectedOutletId.value || !selectedMonth.value) {
    suggestError.value = 'Pilih outlet dan bulan dulu sebelum generate suggestion.'
    return
  }
  const normalizedMonthlyTarget = parseFormattedNumber(monthlyTarget.value)
  if (normalizedMonthlyTarget === null || normalizedMonthlyTarget <= 0) {
    suggestError.value = 'Isi Monthly Target dulu (lebih dari 0), baru klik AI Suggest.'
    return
  }

  suggesting.value = true
  try {
    const { data } = await axios.post(route('outlet-revenue-targets.suggest'), {
      outlet_id: selectedOutletId.value,
      month: selectedMonth.value,
      monthly_target: normalizedMonthlyTarget,
    })

    monthlyTarget.value = formatNumberId(normalizedMonthlyTarget)

    const suggestedMap = new Map((data?.forecasts || []).map((x) => [x.forecast_date, x.forecast_revenue]))
    forecasts.value = forecasts.value.map((row) => ({
      ...row,
      forecast_revenue: suggestedMap.has(row.forecast_date) ? formatNumberId(suggestedMap.get(row.forecast_date)) : row.forecast_revenue,
    }))

    suggestInfo.value = data?.factors || null
  } catch (error) {
    suggestError.value = error?.response?.data?.message || 'Gagal generate AI suggestion.'
  } finally {
    suggesting.value = false
  }
}

function onMonthlyTargetFocus() {
  monthlyTarget.value = toEditableNumber(monthlyTarget.value)
}

function onMonthlyTargetBlur() {
  monthlyTarget.value = formatNumberId(monthlyTarget.value)
}

function onForecastFocus(idx) {
  forecasts.value[idx].forecast_revenue = toEditableNumber(forecasts.value[idx].forecast_revenue)
}

function onForecastBlur(idx) {
  forecasts.value[idx].forecast_revenue = formatNumberId(forecasts.value[idx].forecast_revenue)
}

function applyBulkUpdate() {
  if (!bulkStartDate.value || !bulkEndDate.value) {
    suggestError.value = 'Isi tanggal awal dan akhir untuk bulk update.'
    return
  }

  const start = bulkStartDate.value <= bulkEndDate.value ? bulkStartDate.value : bulkEndDate.value
  const end = bulkStartDate.value <= bulkEndDate.value ? bulkEndDate.value : bulkStartDate.value

  if (bulkMode.value === 'set') {
    const base = parseFormattedNumber(bulkValue.value)
    if (base === null || base < 0) {
      suggestError.value = 'Isi nominal bulk update yang valid.'
      return
    }

    forecasts.value = forecasts.value.map((row) => {
      if (row.forecast_date >= start && row.forecast_date <= end) {
        return { ...row, forecast_revenue: formatNumberId(base) }
      }
      return row
    })
    suggestError.value = ''
    return
  }

  const pct = Number(bulkPercent.value)
  if (!Number.isFinite(pct)) {
    suggestError.value = 'Isi persentase bulk update yang valid.'
    return
  }

  forecasts.value = forecasts.value.map((row) => {
    if (row.forecast_date < start || row.forecast_date > end) return row
    const current = parseFormattedNumber(row.forecast_revenue) || 0
    const updated = current * (1 + pct / 100)
    return { ...row, forecast_revenue: formatNumberId(updated) }
  })
  suggestError.value = ''
}

async function fetchHolidays() {
  try {
    const { data } = await axios.get('/api/holidays')
    holidays.value = Array.isArray(data) ? data : []
  } catch (error) {
    holidays.value = []
  }
}

fetchHolidays()
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-3 sm:px-4">
      <div class="mb-6 rounded-2xl bg-gradient-to-r from-slate-800 to-slate-700 p-5 text-white shadow-lg">
        <p class="text-xs uppercase tracking-wider text-slate-300">Revenue Planning</p>
        <h1 class="mt-1 text-2xl font-semibold">Monthly Target & Daily Forecast</h1>
        <p class="mt-1 text-sm text-slate-200">Gunakan AI Suggest sebagai baseline, lalu sesuaikan angka sesuai strategi outlet.</p>
      </div>

      <div
        v-if="page.props.flash?.success"
        class="mb-4 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-700 shadow-sm"
      >
        {{ page.props.flash.success }}
      </div>

      <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-300 hover:shadow-md">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Outlet</label>
            <select
              v-model.number="selectedOutletId"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              :disabled="!canSelectOutlet"
            >
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Bulan</label>
            <input
              v-model="selectedMonth"
              type="month"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Monthly Target</label>
            <input
              v-model="monthlyTarget"
              type="text"
              inputmode="decimal"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              placeholder="0"
              @focus="onMonthlyTargetFocus"
              @blur="onMonthlyTargetBlur"
            />
          </div>
          <div class="flex items-end gap-2">
            <button
              type="button"
              class="rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
              @click="loadData"
            >
              Load
            </button>
            <button
              type="button"
              class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="suggesting"
              @click="suggestAI"
            >
              {{ suggesting ? 'Generating...' : 'AI Suggest' }}
            </button>
            <button
              type="button"
              class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="saving"
              @click="save"
            >
              {{ saving ? 'Saving...' : 'Simpan' }}
            </button>
          </div>
        </div>

        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
          Monthly Target wajib diisi dulu. AI Suggest akan membagi forecast harian berdasarkan pola 3 bulan terakhir + kalender (weekday/weekend/libur),
          lalu menyesuaikan total agar tetap mengikuti Monthly Target yang kamu input.
        </div>

        <div v-if="suggestError" class="mt-3 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ suggestError }}
        </div>

        <div v-if="suggestInfo" class="mt-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-900">
          <span class="font-semibold">AI factors:</span>
          momentum <strong>{{ suggestInfo.momentum_factor }}</strong>, trend
          <strong>{{ suggestInfo.trend_factor }}</strong>, global economy
          <strong>{{ suggestInfo.global_economy_factor }}</strong>, holiday boost
          <strong>{{ suggestInfo.holiday_boost }}</strong>, rata-rata 3 bulan
          <strong>Rp {{ new Intl.NumberFormat('id-ID').format(suggestInfo.last3_average_monthly || 0) }}</strong>,
          target input <strong>Rp {{ new Intl.NumberFormat('id-ID').format(suggestInfo.input_monthly_target || 0) }}</strong>,
          normalisasi <strong>{{ suggestInfo.normalization_factor }}</strong>.
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-300 hover:shadow-md">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold text-slate-800">Daily Forecast</h2>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700"
            >Total Forecast: Rp {{ new Intl.NumberFormat('id-ID').format(totalForecast) }}</span
          >
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-3 text-xs">
          <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-slate-700">
            Hari Libur: <strong>{{ totalHolidayDays }}</strong>
          </span>
          <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-slate-700">
            Weekend: <strong>{{ totalWeekendDays }}</strong>
          </span>
          <span class="inline-flex items-center gap-2 text-slate-600">
            <span class="inline-block h-3 w-3 rounded bg-amber-100 border border-amber-300"></span>
            Weekend
          </span>
          <span class="inline-flex items-center gap-2 text-slate-600">
            <span class="inline-block h-3 w-3 rounded bg-red-100 border border-red-300"></span>
            Hari Libur
          </span>
        </div>

        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Bulk Update</p>
          <div class="grid grid-cols-1 gap-2 md:grid-cols-6">
            <input
              v-model="bulkStartDate"
              type="date"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
            />
            <input
              v-model="bulkEndDate"
              type="date"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
            />
            <select
              v-model="bulkMode"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
            >
              <option value="set">Set Nominal</option>
              <option value="percent">Naik/Turun %</option>
            </select>
            <input
              v-if="bulkMode === 'set'"
              v-model="bulkValue"
              type="text"
              inputmode="decimal"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              placeholder="Nominal"
            />
            <input
              v-else
              v-model="bulkPercent"
              type="number"
              step="0.1"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              placeholder="Persen, contoh -5"
            />
            <div class="md:col-span-2 flex items-center">
              <button
                type="button"
                class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                @click="applyBulkUpdate"
              >
                Terapkan ke Rentang Tanggal
              </button>
            </div>
          </div>
        </div>

        <div class="overflow-auto rounded-xl border border-slate-200">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-100 text-slate-700">
                <th class="sticky top-0 z-10 bg-slate-100 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Tanggal</th>
                <th class="sticky top-0 z-10 bg-slate-100 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Hari</th>
                <th class="sticky top-0 z-10 bg-slate-100 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide">Forecast Revenue</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, idx) in forecasts"
                :key="row.forecast_date"
                class="border-t border-slate-200 transition-colors duration-200 hover:bg-indigo-50/40"
                :class="{
                  'bg-red-50': row.is_holiday,
                  'bg-amber-50': !row.is_holiday && row.is_weekend,
                }"
              >
                <td class="px-3 py-2.5 font-medium text-slate-700">{{ row.forecast_date }}</td>
                <td class="px-3 py-2.5">
                  <div class="flex items-center gap-2">
                    <span class="text-slate-700">{{ row.day_name }}</span>
                    <span v-if="row.is_holiday" class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-700">
                      Libur
                    </span>
                    <span v-else-if="row.is_weekend" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700">
                      Weekend
                    </span>
                  </div>
                  <div v-if="row.is_holiday && row.holiday_desc" class="text-[11px] text-red-700 mt-0.5">
                    {{ row.holiday_desc }}
                  </div>
                </td>
                <td class="px-3 py-2.5">
                  <input
                    v-model="forecasts[idx].forecast_revenue"
                    type="text"
                    inputmode="decimal"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    placeholder="0"
                    @focus="onForecastFocus(idx)"
                    @blur="onForecastBlur(idx)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

