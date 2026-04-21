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

const forecasts = ref([])

function formatLocalDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
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
      forecast_revenue: mapped.has(iso) ? mapped.get(iso) : '',
    })
  }
  return rows
}

function resetFormFromProps() {
  selectedOutletId.value = props.selectedOutletId || 0
  selectedMonth.value = props.selectedMonth || new Date().toISOString().slice(0, 7)
  monthlyTarget.value = props.monthlyTarget ?? ''
  forecasts.value = buildDaysInMonth(selectedMonth.value)
}

watch(
  () => [props.selectedOutletId, props.selectedMonth, props.monthlyTarget, props.existingForecasts],
  () => resetFormFromProps(),
  { immediate: true, deep: true }
)

watch(selectedMonth, (newMonth) => {
  forecasts.value = buildDaysInMonth(newMonth)
})

watch(holidays, () => {
  forecasts.value = buildDaysInMonth(selectedMonth.value)
}, { deep: true })

const totalForecast = computed(() =>
  forecasts.value.reduce((sum, r) => sum + (Number(r.forecast_revenue || 0) || 0), 0)
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
  router.post(
    route('outlet-revenue-targets.store'),
    {
      outlet_id: selectedOutletId.value,
      month: selectedMonth.value,
      monthly_target: monthlyTarget.value,
      forecasts: forecasts.value,
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

  suggesting.value = true
  try {
    const { data } = await axios.post(route('outlet-revenue-targets.suggest'), {
      outlet_id: selectedOutletId.value,
      month: selectedMonth.value,
    })

    monthlyTarget.value = data?.monthly_target_suggested ?? monthlyTarget.value

    const suggestedMap = new Map((data?.forecasts || []).map((x) => [x.forecast_date, x.forecast_revenue]))
    forecasts.value = forecasts.value.map((row) => ({
      ...row,
      forecast_revenue: suggestedMap.has(row.forecast_date) ? suggestedMap.get(row.forecast_date) : row.forecast_revenue,
    }))

    suggestInfo.value = data?.factors || null
  } catch (error) {
    suggestError.value = error?.response?.data?.message || 'Gagal generate AI suggestion.'
  } finally {
    suggesting.value = false
  }
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
              type="number"
              min="0"
              step="0.01"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              placeholder="0"
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
          AI suggestion otomatis dihitung dari histori bulan sebelumnya, pola weekday/weekend/hari libur, dan momentum tren outlet.
          Semua angka tetap bisa diubah manual sebelum simpan.
        </div>

        <div v-if="suggestError" class="mt-3 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ suggestError }}
        </div>

        <div v-if="suggestInfo" class="mt-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-900">
          <span class="font-semibold">AI factors:</span>
          momentum <strong>{{ suggestInfo.momentum_factor }}</strong>, holiday boost
          <strong>{{ suggestInfo.holiday_boost }}</strong>, referensi histori
          <strong>{{ suggestInfo.historical_reference_month }}</strong>.
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
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    placeholder="0"
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

