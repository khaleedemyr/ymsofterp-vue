<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  selectedOutletId: { type: Number, default: 0 },
  selectedOutletName: { type: String, default: '' },
  selectedMonth: { type: String, default: '' },
  month_label: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({}) },
  canSelectOutlet: { type: Boolean, default: false },
})

const outletId = ref(props.selectedOutletId || 0)
const month = ref(props.selectedMonth || new Date().toISOString().slice(0, 7))
const isLoading = ref(false)

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

function formatPct(value) {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  return `${new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 2 }).format(n)}%`
}

function loadReport() {
  if (isLoading.value) return
  isLoading.value = true

  router.get(
    route('reports.modal-engineering'),
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
  const url = route('reports.modal-engineering.export', {
    outlet_id: outletId.value,
    month: month.value,
  })
  window.open(url, '_blank')
}

const currentOutletDisplayName = computed(() => {
  if (props.selectedOutletName) return props.selectedOutletName
  const id = outletId.value
  const o = (props.outlets || []).find((x) => Number(x.id) === Number(id))
  return o?.name ?? '—'
})
</script>

<template>
  <AppLayout>
    <div class="w-full py-8 px-3 sm:px-4 lg:px-6">
      <div class="mb-6 rounded-2xl bg-gradient-to-br from-indigo-900 via-indigo-800 to-violet-900 p-6 text-white shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200/90">Ops Management</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">Laporan Modal x Engineering</h1>
        <p class="mt-1 text-sm text-indigo-100/90">
          Data harian per outlet: nilai stock cut + category cost type usage, dibanding engineering (total penjualan sebelum diskon/pajak/service).
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
          <div class="md:col-span-2 flex flex-wrap gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100"
              @click="exportExcel"
            >
              <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
              Download Excel
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

        <p v-if="month_label" class="mt-4 text-sm text-slate-600">
          Outlet: <strong>{{ currentOutletDisplayName }}</strong> · Periode: <strong>{{ month_label }}</strong>
        </p>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Tanggal</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Hari</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Stock Cut</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Category Cost Usage</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Total Modal</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Engineering</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">% Modal x Engineering</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-for="row in rows" :key="row.date" class="hover:bg-slate-50/80">
                <td class="whitespace-nowrap px-4 py-2.5 font-medium text-slate-800">{{ row.date }}</td>
                <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ row.day_name }}</td>
                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-800">{{ formatRp(row.stock_cut) }}</td>
                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-800">{{ formatRp(row.category_cost_usage) }}</td>
                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums font-medium text-slate-900">{{ formatRp(row.total_modal) }}</td>
                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-800">{{ formatRp(row.engineering) }}</td>
                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums font-semibold text-indigo-700">
                  {{ formatPct(row.modal_x_engineering_pct) }}
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-amber-50">
              <tr>
                <td class="px-4 py-3 font-bold text-slate-900" colspan="2">Total</td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{{ formatRp(totals.stock_cut) }}</td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{{ formatRp(totals.category_cost_usage) }}</td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{{ formatRp(totals.total_modal) }}</td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{{ formatRp(totals.engineering) }}</td>
                <td class="px-4 py-3 text-right font-bold tabular-nums text-indigo-800">{{ formatPct(totals.modal_x_engineering_pct) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
