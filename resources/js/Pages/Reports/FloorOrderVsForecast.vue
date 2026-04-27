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

function formatRp(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(n)
}

function loadReport() {
  router.get(
    route('reports.floor-order-vs-forecast'),
    {
      outlet_id: outletId.value,
      month: month.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
    }
  )
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
    <div class="max-w-[1600px] mx-auto py-8 px-3 sm:px-4">
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
              class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
              @click="loadReport"
            >
              Tampilkan
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

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1250px] border-collapse text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">
                <th class="sticky left-0 z-20 whitespace-nowrap bg-slate-100 px-3 py-3 shadow-[2px_0_0_rgba(0,0,0,0.06)]">
                  Tanggal
                </th>
                <th class="whitespace-nowrap px-3 py-3">Hari</th>
                <th class="whitespace-nowrap px-3 py-3 text-right">Forecast</th>
                <th class="whitespace-nowrap bg-emerald-50/90 px-3 py-3 text-right text-emerald-900">Revenue</th>
                <th class="whitespace-nowrap bg-indigo-50/90 px-3 py-3 text-right text-indigo-900">
                  F &amp; B Purchase ({{ kitchen_bar_ratio_pct }}%)
                </th>
                <th class="whitespace-nowrap bg-indigo-50/90 px-3 py-3 text-right text-indigo-900">
                  F &amp; B Purchased
                </th>
                <th class="whitespace-nowrap bg-indigo-50/90 px-3 py-3 text-right text-indigo-900">Δ F &amp; B</th>
                <th class="whitespace-nowrap bg-indigo-50/90 px-3 py-3 text-right text-indigo-900">% vs purchase</th>
                <th class="whitespace-nowrap bg-teal-50/90 px-3 py-3 text-right text-teal-900">
                  Svc Purchase ({{ service_ratio_pct }}%)
                </th>
                <th class="whitespace-nowrap bg-teal-50/90 px-3 py-3 text-right text-teal-900">Service Purchased</th>
                <th class="whitespace-nowrap bg-teal-50/90 px-3 py-3 text-right text-teal-900">Δ Svc</th>
                <th class="whitespace-nowrap bg-teal-50/90 px-3 py-3 text-right text-teal-900">% vs purchase</th>
                <th class="whitespace-nowrap px-3 py-3 text-right text-slate-600">RO lain*</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(row, idx) in rows"
                :key="row.date"
                class="transition-colors hover:bg-slate-50/80"
                :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40'"
              >
                <td
                  class="sticky left-0 z-10 whitespace-nowrap border-r border-slate-100 bg-inherit px-3 py-2 font-medium text-slate-800 shadow-[2px_0_0_rgba(0,0,0,0.04)]"
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
                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-500">
                  {{ row.ro_other > 0 ? 'Rp ' + formatRp(row.ro_other) : '—' }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-slate-300 bg-slate-100 font-semibold text-slate-900">
                <td class="sticky left-0 z-10 bg-slate-100 px-3 py-3 shadow-[2px_0_0_rgba(0,0,0,0.06)]" colspan="2">
                  Total bulan
                </td>
                <td class="px-3 py-3 text-right tabular-nums">Rp {{ formatRp(totals.forecast_revenue) }}</td>
                <td class="bg-emerald-100/80 px-3 py-3 text-right tabular-nums text-emerald-950 font-semibold">
                  Rp {{ formatRp(totals.revenue) }}
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
                <td class="px-3 py-3 text-right tabular-nums text-slate-600">
                  {{ totals.ro_other > 0 ? 'Rp ' + formatRp(totals.ro_other) : '—' }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p class="border-t border-slate-100 px-4 py-3 text-[11px] leading-relaxed text-slate-500">
          * <strong>RO lain</strong>: warehouse outlet FO selain nama Kitchen / Bar / Service (misal typo atau warehouse tambahan).
          FO dengan status selain draft / rejected. Nilai Kitchen+Bar dan Service per item =
          qty terima GR × harga RO jika ada GR completed; lainnya subtotal FO.
          Agregasi per tanggal kedatangan =
          <code class="rounded bg-slate-100 px-1">Σ…</code> dikelompokkan ke kolom tersebut.
        </p>
      </div>
    </div>
  </AppLayout>
</template>
