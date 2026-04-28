<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  selectedOutletId: { type: Number, default: 0 },
  selectedMonth: { type: String, default: '' },
  month_label: { type: String, default: '' },
  monthlyTarget: { type: [Number, String, null], default: null },
  kitchen_bar_ratio_pct: { type: Number, default: 40 },
  service_ratio_pct: { type: Number, default: 5 },
  rows: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({}) },
  has_forecast_header: { type: Boolean, default: false },
  canSelectOutlet: { type: Boolean, default: false },
})

const outletId = ref(props.selectedOutletId || 0)
const month = ref(props.selectedMonth || new Date().toISOString().slice(0, 7))
const isLoading = ref(false)
const selectedRowKey = ref(null)

watch(
  () => props.selectedOutletId,
  (v) => {
    outletId.value = v || 0
  }
)
watch(
  () => props.selectedMonth,
  (v) => {
    month.value = v || new Date().toISOString().slice(0, 7)
  }
)
watch(
  () => props.rows,
  (rows) => {
    if (!Array.isArray(rows)) {
      selectedRowKey.value = null
      return
    }

    const stillExists = rows.some((r) => r?.date === selectedRowKey.value)
    if (!stillExists) selectedRowKey.value = null
  }
)

function toggleRowSelection(rowKey) {
  selectedRowKey.value = selectedRowKey.value === rowKey ? null : rowKey
}

function formatRp(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(n)
}

function loadReport() {
  if (isLoading.value) return
  isLoading.value = true

  router.get(
    route('reports.floor-order-vs-forecast'),
    {
      outlet_id: outletId.value,
      month: month.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => {
        isLoading.value = false
      },
    }
  )
}

function exportExcel() {
  const url = route('reports.floor-order-vs-forecast.export', {
    outlet_id: outletId.value,
    month: month.value,
  })

  window.open(url, '_blank')
}

function diffClass(diff) {
  const d = Number(diff)
  if (!Number.isFinite(d) || d === 0) return 'text-slate-600'
  return d > 0 ? 'text-red-700 font-semibold' : 'text-emerald-700'
}

/** User HO (id_outlet = 1) bisa pilih outlet; selain itu hanya outlet sendiri (tanpa dropdown). */
const currentOutletDisplayName = computed(() => {
  const id = outletId.value
  const list = props.outlets || []
  const o = list.find((x) => Number(x.id) === Number(id))
  return o?.name ?? '—'
})
</script>

<template>
  <AppLayout>
    <div class="w-full py-8 px-3 sm:px-4 lg:px-6">
      <div
        class="mb-6 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-6 text-white shadow-xl"
      >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200/90">Laporan</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">RO Food Floor vs Forecast Harian</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-300">
          Per <strong class="text-white">tanggal kedatangan</strong> FO, pembagian warehouse outlet
          <strong class="text-white">Kitchen + Bar</strong> vs <strong class="text-white">Service</strong>.
          Nilai per item: jika sudah ada <strong class="text-white">GR Outlet (completed)</strong>, dipakai
          <strong class="text-white">Σ qty terima × harga RO</strong> per item (sama seperti detail GR di Invoice Outlet); jika belum ada GR untuk baris tersebut, dipakai
          <strong class="text-white">subtotal FO</strong>.
          Kolom <strong class="text-white">Discount</strong> mengambil referensi dari Sales Report:
          <strong class="text-white">discount + manual discount amount</strong> per tanggal.
          Kolom <strong class="text-white">Cost Menu</strong> dan <strong class="text-white">Cost Modifier</strong>
          mengambil referensi cost dari logika <strong class="text-white">Report Cost Menu</strong> di Stock Cut
          untuk tanggal yang sama. Kolom <strong class="text-white">Category Cost Usage</strong> mengambil nilai
          dari <strong class="text-white">Category Cost Outlet</strong> type Usage pada tanggal yang sama. Semuanya dijumlahkan di
          <strong class="text-white">Total Cost</strong>.
          Plafon dibandingkan dengan
          <strong class="text-white">{{ kitchen_bar_ratio_pct }}%</strong> dan
          <strong class="text-white">{{ service_ratio_pct }}%</strong> dari
          <strong class="text-white">forecast revenue harian</strong> (Revenue Targets).
        </p>
      </div>

      <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-6 md:items-end">
          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Outlet</label>
            <div
              v-if="!canSelectOutlet"
              class="flex min-h-[42px] w-full items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-medium text-slate-900"
            >
              {{ currentOutletDisplayName }}
            </div>
            <select
              v-else
              v-model.number="outletId"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
            >
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Bulan</label>
            <input
              v-model="month"
              type="month"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
            />
          </div>
          <div class="md:col-span-2 flex gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100"
              @click="exportExcel"
            >
              <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
              Export Excel
            </button>
            <button
              type="button"
              :disabled="isLoading"
              class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition"
              :class="isLoading ? 'cursor-not-allowed bg-indigo-400' : 'bg-indigo-600 hover:bg-indigo-700'"
              @click="loadReport"
            >
              <i v-if="isLoading" class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
              {{ isLoading ? 'Memuat...' : 'Tampilkan' }}
            </button>
          </div>
        </div>

        <div
          v-if="!has_forecast_header"
          class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
          Belum ada data <strong>Revenue Targets</strong> (forecast harian) untuk outlet &amp; bulan ini. Forecast ditampilkan
          sebagai 0 — isi di menu Revenue Targets agar plafon {{ kitchen_bar_ratio_pct }}% / {{ service_ratio_pct }}%
          bermakna.
        </div>
        <div v-else-if="monthlyTarget != null" class="mt-4 text-sm text-slate-600">
          <span class="font-semibold text-slate-800">{{ month_label }}</span>
          · Monthly target tersimpan:
          <strong class="tabular-nums text-slate-900">Rp {{ formatRp(monthlyTarget) }}</strong>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[2900px] border-collapse text-sm">
            <thead class="sticky top-0 z-10">
              <tr class="border-b border-slate-300 bg-slate-100 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-700">
                <th rowspan="2" class="whitespace-nowrap px-3 py-3">
                  Tanggal
                </th>
                <th rowspan="2" class="whitespace-nowrap px-3 py-3">Hari</th>
                <th rowspan="2" class="whitespace-nowrap px-3 py-3 text-right">Forecast</th>
                <th colspan="3" class="bg-emerald-50/90 px-3 py-2 text-emerald-900">Revenue</th>
                <th colspan="5" class="bg-fuchsia-50/60 px-3 py-2 text-fuchsia-900">Cost</th>
                <th colspan="3" class="bg-sky-50/90 px-3 py-2 text-sky-900">Begin Stock</th>
                <th colspan="4" class="bg-indigo-50/90 px-3 py-2 text-indigo-900">F &amp; B Purchase</th>
                <th colspan="4" class="bg-teal-50/90 px-3 py-2 text-teal-900">Service Purchase</th>
                <th colspan="2" class="bg-orange-50/90 px-3 py-2 text-orange-900">Outlet Transfer</th>
                <th colspan="2" class="bg-purple-50/90 px-3 py-2 text-purple-900">Stock Adjustment</th>
                <th colspan="3" class="bg-amber-50/90 px-3 py-2 text-amber-900">Stock on Hand</th>
              </tr>
              <tr class="border-b border-slate-200 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-700">
                <th class="whitespace-nowrap bg-emerald-50/60 px-3 py-3 text-right text-emerald-900">Revenue</th>
                <th class="whitespace-nowrap bg-red-50/60 px-3 py-3 text-right text-red-900">Discount</th>
                <th class="whitespace-nowrap bg-rose-50/60 px-3 py-3 text-right text-rose-900">% Disc</th>
                <th class="whitespace-nowrap bg-cyan-50/60 px-3 py-3 text-right text-cyan-900">Menu</th>
                <th class="whitespace-nowrap bg-fuchsia-50/60 px-3 py-3 text-right text-fuchsia-900">Modifier</th>
                <th class="whitespace-nowrap bg-violet-50/60 px-3 py-3 text-right text-violet-900">Usage</th>
                <th class="whitespace-nowrap bg-rose-50/60 px-3 py-3 text-right text-rose-900">Total</th>
                <th class="whitespace-nowrap bg-pink-50/60 px-3 py-3 text-right text-pink-900">% Cost</th>
                <th class="whitespace-nowrap bg-sky-50/60 px-3 py-3 text-right text-sky-900">F &amp; B</th>
                <th class="whitespace-nowrap bg-sky-50/60 px-3 py-3 text-right text-sky-900">Service</th>
                <th class="whitespace-nowrap bg-sky-50/60 px-3 py-3 text-right text-sky-900">Total</th>
                <th class="whitespace-nowrap bg-indigo-50/60 px-3 py-3 text-right text-indigo-900">Budget ({{ kitchen_bar_ratio_pct }}%)</th>
                <th class="whitespace-nowrap bg-indigo-50/60 px-3 py-3 text-right text-indigo-900">Purchased</th>
                <th class="whitespace-nowrap bg-indigo-50/60 px-3 py-3 text-right text-indigo-900">Variance</th>
                <th class="whitespace-nowrap bg-indigo-50/60 px-3 py-3 text-right text-indigo-900">%</th>
                <th class="whitespace-nowrap bg-teal-50/60 px-3 py-3 text-right text-teal-900">Budget ({{ service_ratio_pct }}%)</th>
                <th class="whitespace-nowrap bg-teal-50/60 px-3 py-3 text-right text-teal-900">Purchased</th>
                <th class="whitespace-nowrap bg-teal-50/60 px-3 py-3 text-right text-teal-900">Variance</th>
                <th class="whitespace-nowrap bg-teal-50/60 px-3 py-3 text-right text-teal-900">%</th>
                <th class="whitespace-nowrap bg-orange-50/60 px-3 py-3 text-right text-orange-900">Transfer Out</th>
                <th class="whitespace-nowrap bg-orange-50/60 px-3 py-3 text-right text-orange-900">Transfer In</th>
                <th class="whitespace-nowrap bg-purple-50/60 px-3 py-3 text-right text-purple-900">Adj In</th>
                <th class="whitespace-nowrap bg-purple-50/60 px-3 py-3 text-right text-purple-900">Adj Out</th>
                <th class="whitespace-nowrap bg-amber-50/60 px-3 py-3 text-right text-amber-900">F &amp; B</th>
                <th class="whitespace-nowrap bg-orange-50/60 px-3 py-3 text-right text-orange-900">Service</th>
                <th class="whitespace-nowrap bg-yellow-50/60 px-3 py-3 text-right text-yellow-900">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(row, idx) in rows"
                :key="row.date"
                class="group cursor-pointer select-none transition-all duration-100 hover:outline hover:outline-2 hover:-outline-offset-2 hover:outline-indigo-400 hover:[&>td]:bg-amber-200/75"
                :class="[
                  idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40',
                  selectedRowKey === row.date
                    ? 'outline outline-2 -outline-offset-2 outline-indigo-600 [&>td]:!bg-indigo-200/80 [&>td]:!text-slate-900'
                    : ''
                ]"
                @click="toggleRowSelection(row.date)"
              >
                <td
                  class="whitespace-nowrap px-3 py-2 font-medium text-slate-800"
                >
                  {{ row.date }}
                </td>
                <td class="whitespace-nowrap px-3 py-2 capitalize text-slate-600">{{ row.day_name }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-900">
                  {{ row.forecast_revenue > 0 ? 'Rp ' + formatRp(row.forecast_revenue) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-emerald-50/40 px-3 py-2 text-right tabular-nums font-medium text-emerald-950">
                  {{ row.revenue > 0 ? 'Rp ' + formatRp(row.revenue) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-red-50/40 px-3 py-2 text-right tabular-nums font-medium text-red-950">
                  {{ row.discount > 0 ? 'Rp ' + formatRp(row.discount) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-rose-50/40 px-3 py-2 text-right tabular-nums font-medium text-rose-950">
                  {{ row.pct_discount != null ? row.pct_discount + '%' : '—' }}
                </td>
                <td class="whitespace-nowrap bg-cyan-50/40 px-3 py-2 text-right tabular-nums font-medium text-cyan-950">
                  {{ row.cost_menu > 0 ? 'Rp ' + formatRp(row.cost_menu) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-fuchsia-50/40 px-3 py-2 text-right tabular-nums font-medium text-fuchsia-950">
                  {{ row.cost_modifier > 0 ? 'Rp ' + formatRp(row.cost_modifier) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-violet-50/40 px-3 py-2 text-right tabular-nums font-medium text-violet-950">
                  {{ row.category_cost_usage > 0 ? 'Rp ' + formatRp(row.category_cost_usage) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-rose-50/40 px-3 py-2 text-right tabular-nums font-semibold text-rose-950">
                  {{ row.cost_total > 0 ? 'Rp ' + formatRp(row.cost_total) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-pink-50/40 px-3 py-2 text-right tabular-nums font-semibold text-pink-950">
                  {{ row.pct_cost != null ? row.pct_cost + '%' : '—' }}
                </td>
                <td class="whitespace-nowrap bg-sky-50/40 px-3 py-2 text-right tabular-nums font-medium text-sky-950">
                  {{ row.begin_stock_kitchen_bar > 0 ? 'Rp ' + formatRp(row.begin_stock_kitchen_bar) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-sky-50/40 px-3 py-2 text-right tabular-nums font-medium text-sky-950">
                  {{ row.begin_stock_service > 0 ? 'Rp ' + formatRp(row.begin_stock_service) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-sky-50/40 px-3 py-2 text-right tabular-nums font-medium text-sky-950">
                  {{ row.begin_stock_total > 0 ? 'Rp ' + formatRp(row.begin_stock_total) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-indigo-50/40 px-3 py-2 text-right tabular-nums text-indigo-950">
                  {{ row.forecast_revenue > 0 ? 'Rp ' + formatRp(row.cap_kitchen_bar) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-indigo-50/40 px-3 py-2 text-right tabular-nums font-medium text-indigo-950">
                  {{ row.ro_kitchen_bar > 0 ? 'Rp ' + formatRp(row.ro_kitchen_bar) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-indigo-50/40 px-3 py-2 text-right tabular-nums" :class="diffClass(row.diff_kitchen_bar)">
                  {{
                    row.forecast_revenue > 0 || row.ro_kitchen_bar > 0
                      ? (row.diff_kitchen_bar >= 0 ? '+' : '') + 'Rp ' + formatRp(row.diff_kitchen_bar)
                      : '—'
                  }}
                </td>
                <td class="whitespace-nowrap bg-indigo-50/40 px-3 py-2 text-right tabular-nums text-slate-700">
                  {{ row.pct_kitchen_bar_vs_cap != null ? row.pct_kitchen_bar_vs_cap + '%' : '—' }}
                </td>
                <td class="whitespace-nowrap bg-teal-50/40 px-3 py-2 text-right tabular-nums text-teal-950">
                  {{ row.forecast_revenue > 0 ? 'Rp ' + formatRp(row.cap_service) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-teal-50/40 px-3 py-2 text-right tabular-nums font-medium text-teal-950">
                  {{ row.ro_service > 0 ? 'Rp ' + formatRp(row.ro_service) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-teal-50/40 px-3 py-2 text-right tabular-nums" :class="diffClass(row.diff_service)">
                  {{
                    row.forecast_revenue > 0 || row.ro_service > 0
                      ? (row.diff_service >= 0 ? '+' : '') + 'Rp ' + formatRp(row.diff_service)
                      : '—'
                  }}
                </td>
                <td class="whitespace-nowrap bg-teal-50/40 px-3 py-2 text-right tabular-nums text-slate-700">
                  {{ row.pct_service_vs_cap != null ? row.pct_service_vs_cap + '%' : '—' }}
                </td>
                <td class="whitespace-nowrap bg-orange-50/40 px-3 py-2 text-right tabular-nums font-medium text-orange-950">
                  {{ row.transfer_out > 0 ? 'Rp ' + formatRp(row.transfer_out) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-orange-50/40 px-3 py-2 text-right tabular-nums font-medium text-orange-950">
                  {{ row.transfer_in > 0 ? 'Rp ' + formatRp(row.transfer_in) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-purple-50/40 px-3 py-2 text-right tabular-nums font-medium text-purple-950">
                  {{ row.adj_in > 0 ? 'Rp ' + formatRp(row.adj_in) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-purple-50/40 px-3 py-2 text-right tabular-nums font-medium text-purple-950">
                  {{ row.adj_out > 0 ? 'Rp ' + formatRp(row.adj_out) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-amber-50/40 px-3 py-2 text-right tabular-nums font-medium text-amber-950">
                  {{ row.stock_on_hand_kitchen_bar > 0 ? 'Rp ' + formatRp(row.stock_on_hand_kitchen_bar) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-orange-50/40 px-3 py-2 text-right tabular-nums font-medium text-orange-950">
                  {{ row.stock_on_hand_service > 0 ? 'Rp ' + formatRp(row.stock_on_hand_service) : '—' }}
                </td>
                <td class="whitespace-nowrap bg-yellow-50/40 px-3 py-2 text-right tabular-nums font-medium text-yellow-950">
                  {{ row.stock_on_hand_total > 0 ? 'Rp ' + formatRp(row.stock_on_hand_total) : '—' }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-slate-300 bg-slate-100 font-semibold text-slate-900">
                <td class="bg-slate-100 px-3 py-3" colspan="2">
                  Total bulan (SOH: posisi akhir bulan)
                </td>
                <td class="px-3 py-3 text-right tabular-nums">Rp {{ formatRp(totals.forecast_revenue) }}</td>
                <td class="bg-emerald-100/80 px-3 py-3 text-right tabular-nums text-emerald-950 font-semibold">
                  Rp {{ formatRp(totals.revenue) }}
                </td>
                <td class="bg-red-100/80 px-3 py-3 text-right tabular-nums text-red-950 font-semibold">
                  Rp {{ formatRp(totals.discount) }}
                </td>
                <td class="bg-rose-100/80 px-3 py-3 text-right tabular-nums text-rose-950 font-semibold">
                  {{ totals.pct_discount != null ? totals.pct_discount + '%' : '—' }}
                </td>
                <td class="bg-cyan-100/80 px-3 py-3 text-right tabular-nums text-cyan-950 font-semibold">
                  Rp {{ formatRp(totals.cost_menu) }}
                </td>
                <td class="bg-fuchsia-100/80 px-3 py-3 text-right tabular-nums text-fuchsia-950 font-semibold">
                  Rp {{ formatRp(totals.cost_modifier) }}
                </td>
                <td class="bg-violet-100/80 px-3 py-3 text-right tabular-nums text-violet-950 font-semibold">
                  Rp {{ formatRp(totals.category_cost_usage) }}
                </td>
                <td class="bg-rose-100/80 px-3 py-3 text-right tabular-nums text-rose-950 font-semibold">
                  Rp {{ formatRp(totals.cost_total) }}
                </td>
                <td class="bg-pink-100/80 px-3 py-3 text-right tabular-nums text-pink-950 font-semibold">
                  {{ totals.pct_cost != null ? totals.pct_cost + '%' : '—' }}
                </td>
                <td class="bg-sky-100/80 px-3 py-3 text-right tabular-nums text-sky-950 font-semibold">
                  Rp {{ formatRp(totals.begin_stock_kitchen_bar_start) }}
                </td>
                <td class="bg-sky-100/80 px-3 py-3 text-right tabular-nums text-sky-950 font-semibold">
                  Rp {{ formatRp(totals.begin_stock_service_start) }}
                </td>
                <td class="bg-sky-100/80 px-3 py-3 text-right tabular-nums text-sky-950 font-semibold">
                  Rp {{ formatRp(totals.begin_stock_total_start) }}
                </td>
                <td class="bg-indigo-100/80 px-3 py-3 text-right tabular-nums text-indigo-950">
                  Rp {{ formatRp(totals.cap_kitchen_bar) }}
                </td>
                <td class="bg-indigo-100/80 px-3 py-3 text-right tabular-nums text-indigo-950">
                  Rp {{ formatRp(totals.ro_kitchen_bar) }}
                </td>
                <td class="bg-indigo-100/80 px-3 py-3 text-right tabular-nums" :class="diffClass(totals.diff_kitchen_bar)">
                  {{ totals.diff_kitchen_bar >= 0 ? '+' : '' }}Rp {{ formatRp(totals.diff_kitchen_bar) }}
                </td>
                <td class="bg-indigo-100/80 px-3 py-3 text-right text-slate-600">—</td>
                <td class="bg-teal-100/80 px-3 py-3 text-right tabular-nums text-teal-950">
                  Rp {{ formatRp(totals.cap_service) }}
                </td>
                <td class="bg-teal-100/80 px-3 py-3 text-right tabular-nums text-teal-950">
                  Rp {{ formatRp(totals.ro_service) }}
                </td>
                <td class="bg-teal-100/80 px-3 py-3 text-right tabular-nums" :class="diffClass(totals.diff_service)">
                  {{ totals.diff_service >= 0 ? '+' : '' }}Rp {{ formatRp(totals.diff_service) }}
                </td>
                <td class="bg-teal-100/80 px-3 py-3 text-right text-slate-600">—</td>
                <td class="bg-orange-100/80 px-3 py-3 text-right tabular-nums text-orange-950 font-semibold">
                  Rp {{ formatRp(totals.transfer_out) }}
                </td>
                <td class="bg-orange-100/80 px-3 py-3 text-right tabular-nums text-orange-950 font-semibold">
                  Rp {{ formatRp(totals.transfer_in) }}
                </td>
                <td class="bg-purple-100/80 px-3 py-3 text-right tabular-nums text-purple-950 font-semibold">
                  Rp {{ formatRp(totals.adj_in) }}
                </td>
                <td class="bg-purple-100/80 px-3 py-3 text-right tabular-nums text-purple-950 font-semibold">
                  Rp {{ formatRp(totals.adj_out) }}
                </td>
                <td class="bg-amber-100/80 px-3 py-3 text-right tabular-nums text-amber-950 font-semibold">
                  Rp {{ formatRp(totals.stock_on_hand_kitchen_bar_end) }}
                </td>
                <td class="bg-orange-100/80 px-3 py-3 text-right tabular-nums text-orange-950 font-semibold">
                  Rp {{ formatRp(totals.stock_on_hand_service_end) }}
                </td>
                <td class="bg-yellow-100/80 px-3 py-3 text-right tabular-nums text-yellow-950 font-semibold">
                  Rp {{ formatRp(totals.stock_on_hand_total_end) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div class="rounded-2xl border-l border-r border-b border-slate-200 bg-white">
        <p class="border-t border-slate-100 px-4 py-3 text-[11px] leading-relaxed text-slate-500">
          * <strong>Cost Menu</strong>: cost bahan baku menu dari order item yang sudah <strong>stock cut</strong> pada tanggal tersebut.
          * <strong>Cost Modifier</strong>: cost bahan modifier dari JSON modifier order item yang sudah <strong>stock cut</strong> pada tanggal tersebut.
          * <strong>Category Cost Usage</strong>: subtotal MAC dari <strong>Category Cost Outlet</strong> dengan type <strong>Usage</strong> pada tanggal tersebut.
          * <strong>Total Cost</strong>: penjumlahan cost menu, cost modifier, dan category cost usage.
          * <strong>Discount</strong>: total <strong>discount + manual discount amount</strong> pada tanggal tersebut.
          * <strong>% Disc</strong>: persentase <strong>Discount / Revenue</strong> pada tanggal tersebut.
          * <strong>% Cost</strong>: persentase <strong>Total Cost / Revenue</strong> pada tanggal tersebut.
          * <strong>RO lain</strong>: warehouse outlet FO selain nama Kitchen / Bar / Service (misal typo atau warehouse tambahan).
          FO dengan status selain draft / rejected. Nilai Kitchen+Bar dan Service per item =
          qty terima GR × harga RO jika ada GR completed; lainnya subtotal FO.
          Kolom <strong>Purchased</strong> (F&amp;B dan Service) mencakup <strong>RO (Floor Order)</strong> dan <strong>Retail Food</strong> (status approved) yang dikategorikan berdasarkan warehouse.
          Kolom <strong>Outlet Transfer</strong>: <strong>Transfer Out</strong> = nilai stok yang dikirim keluar outlet ini; <strong>Transfer In</strong> = nilai stok yang diterima dari outlet lain (status approved, dari kartu stok).
          Kolom <strong>Stock Adjustment</strong>: <strong>Adj In</strong> = nilai adjustment penambahan stok; <strong>Adj Out</strong> = nilai adjustment pengurangan stok (dari menu Outlet Stock Adjustment, status approved).
          Kolom <strong>Begin Stock</strong> menampilkan posisi harta stok <strong>awal hari</strong> (begin-of-day).
          Kolom <strong>SOH</strong> menampilkan posisi harta stok <strong>akhir hari</strong> (end-of-day),
          sedangkan nilai SOH di footer adalah posisi <strong>akhir bulan</strong>.
          Agregasi per tanggal kedatangan =
          <code class="rounded bg-slate-100 px-1">Σ…</code> dikelompokkan ke kolom tersebut.
        </p>
      </div>
    </div>
  </AppLayout>
</template>
