<template>
  <div class="mb-6 space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">Opex Analytics</p>
          <h3 class="text-lg font-bold text-slate-900 mt-0.5">Analisa net &amp; spend otomatis</h3>
          <p class="text-xs text-slate-500 mt-1">
            Bandingkan periode terpilih vs rata-rata 3 bulan (span hari sama) · Net ≈ Revenue − Spend · mix GSR/RO · Retail Food · Non Food
          </p>
        </div>
        <div v-if="loading" class="text-sm text-teal-600">
          <i class="fa-solid fa-spinner fa-spin mr-1"></i> Menyusun analisa…
        </div>
      </div>

      <div v-if="!loading && !data" class="px-5 py-8 text-center text-slate-400 text-sm">
        <p>{{ error ? 'Gagal memuat analisa.' : 'Analisa belum tersedia.' }}</p>
        <p v-if="error" class="mt-1 text-xs text-rose-500">{{ error }}</p>
      </div>

      <div v-else-if="data" class="p-5 space-y-5">
        <div class="rounded-xl border px-4 py-4" :class="severityBoxClass">
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

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Net periode</p>
            <p class="mt-1 text-xl font-bold text-slate-900">{{ formatCurrency(data.current?.net) }}</p>
            <p class="text-xs mt-1" :class="pctClass(data.vs_avg_last_3?.net_pct)">
              {{ fmtPct(data.vs_avg_last_3?.net_pct) }} vs avg 3 bln
            </p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-emerald-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Revenue</p>
            <p class="mt-1 text-xl font-bold text-emerald-900">{{ formatCurrency(data.current?.revenue) }}</p>
            <p class="text-xs mt-1" :class="pctClass(data.vs_avg_last_3?.revenue_pct)">
              {{ fmtPct(data.vs_avg_last_3?.revenue_pct) }} · share driver {{ data.driver?.revenue_share_pct }}%
            </p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-rose-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Total Spend</p>
            <p class="mt-1 text-xl font-bold text-rose-900">{{ formatCurrency(data.current?.total_spend) }}</p>
            <p class="text-xs mt-1" :class="invertPctClass(data.vs_avg_last_3?.total_spend_pct)">
              {{ fmtPct(data.vs_avg_last_3?.total_spend_pct) }} · share driver {{ data.driver?.spend_share_pct }}%
            </p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-amber-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Spend Ratio</p>
            <p class="mt-1 text-xl font-bold text-amber-900">{{ fmtNumber(data.current?.spend_ratio_percent) }}%</p>
            <p class="text-xs mt-1" :class="invertPctClass(data.vs_avg_last_3?.spend_ratio_percent)">
              {{ fmtPp(data.vs_avg_last_3?.spend_ratio_percent) }} vs avg 3 bln
            </p>
          </div>
        </div>

        <div class="rounded-xl border border-slate-100 p-4">
          <div class="flex items-center justify-between gap-2 mb-2">
            <p class="text-sm font-semibold text-slate-800">Atribusi perubahan Net</p>
            <p class="text-xs text-slate-500">Net ≈ Revenue − Spend</p>
          </div>
          <div class="h-3 rounded-full overflow-hidden flex bg-slate-100">
            <div
              class="h-full bg-emerald-500 transition-all"
              :style="{ width: (data.driver?.revenue_share_pct || 0) + '%' }"
              :title="'Revenue ' + data.driver?.revenue_share_pct + '%'"
            />
            <div
              class="h-full bg-rose-500 transition-all"
              :style="{ width: (data.driver?.spend_share_pct || 0) + '%' }"
              :title="'Spend ' + data.driver?.spend_share_pct + '%'"
            />
          </div>
          <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
            <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-emerald-500 mr-1" />Revenue {{ data.driver?.revenue_share_pct }}%</span>
            <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-rose-500 mr-1" />Spend {{ data.driver?.spend_share_pct }}%</span>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div
            v-for="item in data.spend_mix?.items || []"
            :key="item.key"
            class="rounded-xl border border-slate-100 p-4"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ item.label }}</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatCurrency(item.current) }}</p>
            <p class="text-xs mt-1" :class="invertPctClass(item.pct)">{{ fmtPct(item.pct) }} vs avg</p>
            <p class="text-[11px] text-slate-400 mt-1">Share Δ spend {{ item.share_of_spend_change_pct }}%</p>
          </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="rounded-xl border border-slate-100 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cover</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatNumber(data.current?.cover) }}</p>
            <p class="text-xs mt-1" :class="pctClass(data.vs_avg_last_3?.cover_pct)">{{ fmtPct(data.vs_avg_last_3?.cover_pct) }}</p>
          </div>
          <div class="rounded-xl border border-slate-100 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Check</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatCurrency(data.current?.avg_check) }}</p>
            <p class="text-xs mt-1" :class="pctClass(data.vs_avg_last_3?.avg_check_pct)">{{ fmtPct(data.vs_avg_last_3?.avg_check_pct) }}</p>
          </div>
          <div class="rounded-xl border border-slate-100 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diskon</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatCurrency(data.current?.discount) }}</p>
            <p class="text-xs mt-1" :class="invertPctClass(data.vs_avg_last_3?.discount_pct)">{{ fmtPct(data.vs_avg_last_3?.discount_pct) }}</p>
          </div>
          <div class="rounded-xl border border-slate-100 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Petty Cash</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatCurrency(data.current?.petty_cash) }}</p>
            <p class="text-xs mt-1" :class="invertPctClass(data.vs_avg_last_3?.petty_cash_pct)">{{ fmtPct(data.vs_avg_last_3?.petty_cash_pct) }}</p>
          </div>
        </div>

        <div v-if="(data.findings || []).length" class="space-y-2">
          <p class="text-sm font-semibold text-slate-800">Temuan utama</p>
          <div
            v-for="(f, idx) in data.findings"
            :key="idx"
            class="rounded-xl border px-4 py-3 text-sm"
            :class="findingClass(f.severity)"
          >
            <p class="font-semibold">{{ f.headline }}</p>
            <p v-if="f.detail" class="mt-1 text-xs opacity-90 leading-relaxed">{{ f.detail }}</p>
          </div>
        </div>

        <div class="rounded-xl border border-slate-100 overflow-hidden">
          <div class="px-4 py-3 bg-slate-50 border-b border-slate-100">
            <p class="text-sm font-semibold text-slate-800">Perbandingan 3 bulan sebelumnya</p>
            <p class="text-[11px] text-slate-500 mt-0.5">
              Span hari: {{ data.period?.compare_day_span || '—' }}
            </p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                  <th class="px-3 py-2 font-semibold">Bulan</th>
                  <th class="px-3 py-2 font-semibold text-right">Net</th>
                  <th class="px-3 py-2 font-semibold text-right">Revenue</th>
                  <th class="px-3 py-2 font-semibold text-right">Spend</th>
                  <th class="px-3 py-2 font-semibold text-right">Ratio</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-t border-slate-50 bg-teal-50/40">
                  <td class="px-3 py-2 font-medium text-teal-900">Periode ini</td>
                  <td class="px-3 py-2 text-right font-medium">{{ formatCurrency(data.current?.net) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(data.current?.revenue) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(data.current?.total_spend) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtNumber(data.current?.spend_ratio_percent) }}%</td>
                </tr>
                <tr
                  v-for="m in data.compare_months || []"
                  :key="m.key"
                  class="border-t border-slate-50"
                >
                  <td class="px-3 py-2 text-slate-700">
                    <div class="font-medium">{{ m.label }}</div>
                    <div class="text-[11px] text-slate-400">{{ m.from }} s/d {{ m.to }}</div>
                  </td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(m.net) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(m.revenue) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(m.total_spend) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtNumber(m.spend_ratio_percent) }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: [Boolean, String], default: false },
})

const severityBoxClass = computed(() => {
  const d = props.data?.driver?.direction
  if (d === 'down') return 'border-rose-200 bg-rose-50/60'
  if (d === 'up') return 'border-emerald-200 bg-emerald-50/60'
  return 'border-slate-200 bg-slate-50/60'
})
const severityIconWrap = computed(() => {
  const d = props.data?.driver?.direction
  if (d === 'down') return 'bg-rose-100 text-rose-700'
  if (d === 'up') return 'bg-emerald-100 text-emerald-700'
  return 'bg-slate-100 text-slate-600'
})
const severityIcon = computed(() => {
  const d = props.data?.driver?.direction
  if (d === 'down') return 'fa-arrow-trend-down'
  if (d === 'up') return 'fa-arrow-trend-up'
  return 'fa-minus'
})
const severityTitleClass = computed(() => {
  const d = props.data?.driver?.direction
  if (d === 'down') return 'text-rose-900'
  if (d === 'up') return 'text-emerald-900'
  return 'text-slate-900'
})

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(Number(amount || 0))
}

function formatNumber(n) {
  return new Intl.NumberFormat('id-ID').format(Number(n || 0))
}

function fmtNumber(n) {
  if (n === null || n === undefined) return 'n/a'
  return Number(n).toFixed(1)
}

function fmtPct(v) {
  if (v === null || v === undefined) return 'n/a'
  const n = Number(v)
  return (n >= 0 ? '+' : '') + n.toFixed(1) + '%'
}

function fmtPp(v) {
  if (v === null || v === undefined) return 'n/a'
  const n = Number(v)
  return (n >= 0 ? '+' : '') + n.toFixed(1) + ' pp'
}

function pctClass(v) {
  if (v === null || v === undefined) return 'text-slate-400'
  if (v > 0) return 'text-emerald-600'
  if (v < 0) return 'text-rose-600'
  return 'text-slate-500'
}

/** Spend naik = buruk (merah), spend turun = baik (hijau) */
function invertPctClass(v) {
  if (v === null || v === undefined) return 'text-slate-400'
  if (v > 0) return 'text-rose-600'
  if (v < 0) return 'text-emerald-600'
  return 'text-slate-500'
}

function findingClass(severity) {
  if (severity === 'critical') return 'border-rose-200 bg-rose-50 text-rose-900'
  if (severity === 'warning') return 'border-amber-200 bg-amber-50 text-amber-900'
  if (severity === 'positive') return 'border-emerald-200 bg-emerald-50 text-emerald-900'
  return 'border-slate-200 bg-slate-50 text-slate-800'
}
</script>
