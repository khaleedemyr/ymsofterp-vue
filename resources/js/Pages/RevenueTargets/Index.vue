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
const historyMonthsBack = ref(1)
const historyLoading = ref(false)
const historyMessage = ref('')
const historyMonthCards = ref([])
const historyDetailOpen = ref(false)
const historyDetailLoading = ref(false)
const historyDetailError = ref('')
const historyDetail = ref(null)
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

  const baseRaw = raw
    .trim()
    .replace(/\s/g, '')
    .replace(/(k|rb|jt|juta|m|mil|miliar|t|triliun)$/g, '')
    .replace(/[^0-9,.-]/g, '')

  let normalized = baseRaw
  const hasDot = normalized.includes('.')
  const hasComma = normalized.includes(',')

  if (hasDot && hasComma) {
    // Format Indonesia: 1.234.567,89
    normalized = normalized.replace(/\./g, '').replace(',', '.')
  } else if (hasComma) {
    // Decimal comma: 12345,67
    normalized = normalized.replace(',', '.')
  } else if (hasDot) {
    // Jika pola ribuan bertitik (contoh: 1.200.000.000), hapus titik.
    // Jika bukan pola ribuan (contoh: 40599145.91), anggap titik sebagai desimal.
    const isThousandGrouped = /^-?\d{1,3}(\.\d{3})+$/.test(normalized)
    if (isThousandGrouped) {
      normalized = normalized.replace(/\./g, '')
    }
  }

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

function formatRp(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(n)
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

async function generateHistorical() {
  suggestError.value = ''
  historyMessage.value = ''
  historyMonthCards.value = []

  if (!selectedOutletId.value || !selectedMonth.value) {
    suggestError.value = 'Pilih outlet dan bulan dulu sebelum generate historis.'
    return
  }

  historyLoading.value = true
  try {
    const { data } = await axios.post(route('outlet-revenue-targets.generate-historical'), {
      outlet_id: selectedOutletId.value,
      end_month: selectedMonth.value,
      months_back: historyMonthsBack.value,
    })

    historyMessage.value = data?.message || 'Generate historis berhasil.'
    historyMonthCards.value = Array.isArray(data?.month_cards) ? data.month_cards : []
  } catch (error) {
    suggestError.value = error?.response?.data?.message || 'Gagal generate historis revenue.'
  } finally {
    historyLoading.value = false
  }
}

async function openHistoryDetail(card) {
  if (!selectedOutletId.value || !card?.ym) return
  historyDetailOpen.value = true
  historyDetailLoading.value = true
  historyDetailError.value = ''
  historyDetail.value = null
  try {
    const { data } = await axios.get(route('outlet-revenue-targets.historical-month-detail'), {
      params: {
        outlet_id: selectedOutletId.value,
        month: card.ym,
      },
    })
    historyDetail.value = data
  } catch (error) {
    historyDetailError.value =
      error?.response?.data?.message || 'Gagal memuat detail historis bulan.'
  } finally {
    historyDetailLoading.value = false
  }
}

function closeHistoryDetail() {
  historyDetailOpen.value = false
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

        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Generate Revenue Historis</p>
          <p class="mb-3 text-[11px] leading-relaxed text-slate-500">
            Bulan di kolom &quot;Bulan&quot; = acuan (misal April). Jumlah bulan = berapa bulan ke belakang dari acuan,
            <strong class="font-semibold text-slate-600">tanpa menyertakan bulan acuan</strong>. Contoh: April + 2 bulan → Februari &amp; Maret (agregasi dari <code class="rounded bg-slate-200 px-1">orders</code>).
            Hanya untuk referensi di layar — <strong class="text-slate-700">tidak menyimpan ke database target/forecast.</strong>
          </p>
          <div class="grid grid-cols-1 gap-2 md:grid-cols-6">
            <div>
              <label class="mb-1 block text-[11px] font-semibold text-slate-500">Jumlah Bulan</label>
              <select
                v-model.number="historyMonthsBack"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              >
                <option v-for="n in 12" :key="n" :value="n">{{ n }} bulan</option>
              </select>
            </div>
            <div class="md:col-span-4 flex items-end justify-start md:justify-end">
              <button
                type="button"
                class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="historyLoading"
                @click="generateHistorical"
              >
                {{ historyLoading ? 'Generating...' : 'Generate Historis (Orders)' }}
              </button>
            </div>
          </div>
          <div v-if="historyMessage" class="mt-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
            {{ historyMessage }}
          </div>

          <div v-if="historyMonthCards.length" class="mt-4">
            <p class="mb-2 text-xs font-semibold text-slate-600">
              Ringkasan per bulan
              <span class="font-normal text-slate-500">— klik card untuk breakdown harian, weekday/weekend, lunch/dinner</span>
            </p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div
                v-for="card in historyMonthCards"
                :key="card.ym"
                role="button"
                tabindex="0"
                class="cursor-pointer rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm ring-1 ring-slate-100 transition hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-400"
                @click="openHistoryDetail(card)"
                @keydown.enter.prevent="openHistoryDetail(card)"
                @keydown.space.prevent="openHistoryDetail(card)"
              >
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="text-sm font-semibold text-slate-800">{{ card.label }}</p>
                    <p class="text-[11px] text-slate-500">{{ card.ym }}</p>
                  </div>
                  <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700">
                    Dari orders
                  </span>
                </div>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                  Rp {{ new Intl.NumberFormat('id-ID').format(card.monthly_total || 0) }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                  Hari ada transaksi: <strong>{{ card.days_with_orders }}</strong>
                </p>
              </div>
            </div>
          </div>
        </div>

        <Teleport to="body">
          <div
            v-if="historyDetailOpen"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 p-3"
            role="dialog"
            aria-modal="true"
            @click.self="closeHistoryDetail"
          >
            <div
              class="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
              @click.stop
            >
              <div class="flex items-start justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Detail historis (orders)</p>
                  <h3 class="mt-0.5 text-lg font-semibold text-slate-900">
                    <span v-if="historyDetail?.outlet?.name">{{ historyDetail.outlet.name }}</span>
                    <span v-if="historyDetail?.month_label" class="text-slate-600"> — {{ historyDetail.month_label }}</span>
                  </h3>
                </div>
                <button
                  type="button"
                  class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100"
                  @click="closeHistoryDetail"
                >
                  Tutup
                </button>
              </div>

              <div class="overflow-y-auto p-5" style="max-height: calc(90vh - 5.5rem)">
                <div v-if="historyDetailLoading" class="py-12 text-center text-sm text-slate-600">Memuat...</div>
                <div v-else-if="historyDetailError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                  {{ historyDetailError }}
                </div>
                <template v-else-if="historyDetail">
                  <div class="mb-5">
                    <h4 class="mb-2 text-sm font-semibold text-slate-800">Revenue harian (tanggal 1 – akhir bulan)</h4>
                    <div class="overflow-auto rounded-lg border border-slate-200">
                      <table class="w-full text-sm">
                        <thead>
                          <tr class="bg-slate-100 text-slate-700">
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Tanggal</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Hari</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Revenue</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="row in historyDetail.daily"
                            :key="row.date"
                            class="border-t border-slate-100"
                          >
                            <td class="px-3 py-2 text-slate-700">{{ row.date }}</td>
                            <td class="px-3 py-2 capitalize text-slate-600">{{ row.day_name }}</td>
                            <td class="px-3 py-2 text-right font-medium text-slate-900">
                              Rp {{ formatRp(row.revenue) }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                      <h4 class="mb-3 text-sm font-semibold text-slate-800">Weekday vs weekend</h4>
                      <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2">
                          <dt class="text-slate-600">Total weekday</dt>
                          <dd class="font-semibold text-slate-900">
                            Rp {{ formatRp(historyDetail.weekday_weekend.weekday_total) }}
                            <span class="block text-[11px] font-normal text-slate-500">
                              {{ historyDetail.weekday_weekend.weekday_orders }} order ·
                              {{ historyDetail.weekday_weekend.weekday_calendar_days }} hari kalender (Sen–Jum)
                            </span>
                          </dd>
                        </div>
                        <div class="flex justify-between gap-2 border-t border-slate-200 pt-2">
                          <dt class="text-slate-600">Total weekend</dt>
                          <dd class="font-semibold text-slate-900">
                            Rp {{ formatRp(historyDetail.weekday_weekend.weekend_total) }}
                            <span class="block text-[11px] font-normal text-slate-500">
                              {{ historyDetail.weekday_weekend.weekend_orders }} order ·
                              {{ historyDetail.weekday_weekend.weekend_calendar_days }} hari kalender (Sab–Min)
                            </span>
                          </dd>
                        </div>
                      </dl>
                    </div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/80 p-4">
                      <h4 class="mb-3 text-sm font-semibold text-slate-800">Rata-rata per hari kalender</h4>
                      <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2">
                          <dt class="text-slate-600">Rata-rata weekday</dt>
                          <dd class="font-semibold text-indigo-900">
                            Rp {{ formatRp(historyDetail.weekday_weekend.avg_weekday_per_calendar_day) }}
                          </dd>
                        </div>
                        <div class="flex justify-between gap-2 border-t border-indigo-200/60 pt-2">
                          <dt class="text-slate-600">Rata-rata weekend</dt>
                          <dd class="font-semibold text-indigo-900">
                            Rp {{ formatRp(historyDetail.weekday_weekend.avg_weekend_per_calendar_day) }}
                          </dd>
                        </div>
                      </dl>
                      <p class="mt-3 text-[11px] leading-relaxed text-slate-600">
                        Rata-rata = total revenue ÷ jumlah hari kalender weekday/weekend di bulan tersebut (bukan rata-rata per order).
                      </p>
                    </div>
                  </div>

                  <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
                    <h4 class="mb-3 text-sm font-semibold text-slate-800">Lunch vs dinner</h4>
                    <p class="mb-3 text-[11px] text-slate-600">
                      {{ historyDetail.lunch_dinner?.lunch?.rule }}
                    </p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <div class="rounded-lg border border-emerald-200 bg-white p-3">
                        <p class="text-xs font-semibold uppercase text-emerald-800">Lunch</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">
                          Rp {{ formatRp(historyDetail.lunch_dinner?.lunch?.revenue) }}
                        </p>
                        <p class="text-[11px] text-slate-500">{{ historyDetail.lunch_dinner?.lunch?.orders ?? 0 }} order</p>
                      </div>
                      <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <p class="text-xs font-semibold uppercase text-slate-700">Dinner</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">
                          Rp {{ formatRp(historyDetail.lunch_dinner?.dinner?.revenue) }}
                        </p>
                        <p class="text-[11px] text-slate-500">{{ historyDetail.lunch_dinner?.dinner?.orders ?? 0 }} order</p>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </Teleport>

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

