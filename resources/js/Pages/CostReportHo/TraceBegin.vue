<template>
  <AppLayout>
    <div class="w-full py-8 px-2 max-w-[100rem] mx-auto">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-table text-amber-600"></i>
            Trace begin inventory HO
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Baris diurutkan dari nilai <code class="text-xs bg-gray-100 px-1 rounded">|begin|</code> terbesar — cek saldo kartu vs MAC.
          </p>
        </div>
        <Link
          :href="route('cost-report-ho.index', { load: 1, bulan: filters.bulan })"
          class="text-sm text-blue-600 hover:underline"
        >
          ← Kembali ke Cost Report HO
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan laporan</label>
            <input
              v-model="form.bulan"
              type="month"
              required
              class="min-w-[200px] border border-gray-300 rounded-md px-3 py-2"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Gudang</label>
            <select
              v-model="form.warehouse_id"
              class="min-w-[220px] border border-gray-300 rounded-md px-3 py-2"
            >
              <option :value="''">Semua gudang</option>
              <option v-for="w in warehouseOptions" :key="w.id" :value="String(w.id)">
                {{ w.name }}<span v-if="w.code" class="text-gray-500"> ({{ w.code }})</span>
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Baris (max 200)</label>
            <input
              v-model.number="form.limit"
              type="number"
              min="1"
              max="200"
              class="w-24 border border-gray-300 rounded-md px-3 py-2"
            />
          </div>
          <button
            type="submit"
            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
          >
            Tampilkan
          </button>
          <a
            v-if="form.bulan"
            class="text-sm text-slate-600 border border-slate-300 rounded-md px-3 py-2 hover:bg-slate-50"
            :href="jsonUrl"
            target="_blank"
            rel="noopener noreferrer"
          >
            <i class="fa-solid fa-code mr-1"></i> JSON
          </a>
        </form>

        <div class="mt-4 p-3 bg-amber-50 border border-amber-100 rounded-lg text-sm text-amber-900">
          <div class="font-medium text-amber-800 mb-1">Cutoff & rumus</div>
          <div><strong>Bulan laporan:</strong> {{ trace.bulan }} — <strong>Cutoff:</strong> {{ trace.cutoff_date }} (akhir bulan sebelum bulan laporan)</div>
          <div class="mt-1 text-xs leading-relaxed opacity-90">{{ trace.formula }}</div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-slate-800 text-white sticky top-0 z-10">
              <tr>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">#</th>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Gudang</th>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">SKU</th>
                <th class="px-3 py-3 text-left font-semibold min-w-[12rem]">Nama barang</th>
                <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">Saldo qty</th>
                <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">MAC</th>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Sumber MAC</th>
                <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">Begin baris</th>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap text-xs">Kartu</th>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap text-xs">Histori</th>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap text-xs">Rantai MAC</th>
                <th class="px-3 py-3 text-right font-semibold whitespace-nowrap text-xs">Inv. item</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr
                v-for="(row, idx) in trace.lines"
                :key="row.warehouse_id + '-' + row.inventory_item_id + '-' + idx"
                :class="rowHighlightClass(row.begin_line_value)"
              >
                <td class="px-3 py-2 text-gray-500 tabular-nums">{{ idx + 1 }}</td>
                <td class="px-3 py-2 text-gray-900 font-medium whitespace-nowrap">{{ row.warehouse_name }}</td>
                <td class="px-3 py-2 text-gray-700 font-mono text-xs">{{ row.item_sku || '—' }}</td>
                <td class="px-3 py-2 text-gray-800 max-w-xs truncate" :title="row.item_name || ''">
                  {{ row.item_name || '—' }}
                </td>
                <td class="px-3 py-2 text-right font-mono text-xs tabular-nums">{{ formatQty(row.saldo_qty_small) }}</td>
                <td class="px-3 py-2 text-right font-mono text-xs tabular-nums">{{ formatQty(row.mac_effective) }}</td>
                <td class="px-3 py-2">
                  <span
                    class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                    :class="macSourceClass(row.mac_source)"
                  >
                    {{ row.mac_source }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap">
                  {{ formatMoney(row.begin_line_value) }}
                </td>
                <td class="px-3 py-2 text-xs text-gray-600 font-mono whitespace-nowrap">
                  <div>#{{ row.card_id ?? '—' }}</div>
                  <div class="text-gray-500">{{ row.card_date || '—' }}</div>
                  <div v-if="row.card_cost_per_small != null" class="text-gray-400">
                    c/s: {{ formatQty(row.card_cost_per_small) }}
                  </div>
                </td>
                <td class="px-3 py-2 text-xs text-gray-600 font-mono whitespace-nowrap">
                  <div>#{{ row.hist_id ?? '—' }}</div>
                  <div class="text-gray-500">{{ row.hist_date || '—' }}</div>
                  <div v-if="row.hist_mac_raw != null || row.hist_new_cost_raw != null" class="text-gray-400">
                    mac: {{ row.hist_mac_raw != null ? formatQty(row.hist_mac_raw) : '—' }} /
                    new: {{ row.hist_new_cost_raw != null ? formatQty(row.hist_new_cost_raw) : '—' }}
                  </div>
                  <div v-if="row.hist_reference_type || row.hist_reference_id" class="text-gray-500 mt-0.5">
                    ref: {{ row.hist_reference_type || '—' }} #{{ row.hist_reference_id ?? '—' }}
                    <span v-if="row.hist_type" class="text-gray-400"> · {{ row.hist_type }}</span>
                  </div>
                </td>
                <td class="px-3 py-2 text-xs whitespace-nowrap">
                  <Link
                    class="text-blue-600 hover:underline"
                    :href="
                      route('cost-report-ho.mac-lineage', {
                        warehouse_id: row.warehouse_id,
                        inventory_item_id: row.inventory_item_id,
                        bulan: filters.bulan
                      })
                    "
                  >
                    Lihat rantai
                  </Link>
                </td>
                <td class="px-3 py-2 text-right text-xs text-gray-500 tabular-nums">{{ row.inventory_item_id }}</td>
              </tr>
              <tr v-if="!trace.lines.length">
                <td colspan="12" class="px-4 py-12 text-center text-gray-500">
                  Tidak ada baris kartu stok untuk filter ini, atau belum ada data s/d cutoff.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { reactive, computed } from 'vue'

const props = defineProps({
  trace: {
    type: Object,
    required: true
  },
  warehouseOptions: {
    type: Array,
    default: () => []
  },
  filters: {
    type: Object,
    required: true
  }
})

const form = reactive({
  bulan: props.filters.bulan || '',
  warehouse_id:
    props.filters.warehouse_id != null && props.filters.warehouse_id !== ''
      ? String(props.filters.warehouse_id)
      : '',
  limit: props.filters.limit ?? 100
})

const jsonUrl = computed(() => {
  if (!form.bulan) return '#'
  const q = { bulan: form.bulan, limit: form.limit || 100 }
  if (form.warehouse_id) {
    q.warehouse_id = form.warehouse_id
  }
  return route('cost-report-ho.trace-begin', q)
})

function formatMoney(value) {
  if (value == null || value === '') return '—'
  const num = Number(value)
  if (Number.isNaN(num)) return '—'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(num)
}

function formatQty(value) {
  if (value == null || value === '') return '—'
  const num = Number(value)
  if (Number.isNaN(num)) return '—'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 6
  }).format(num)
}

function macSourceClass(src) {
  switch (src) {
    case 'history_mac':
      return 'bg-emerald-100 text-emerald-800'
    case 'history_new_cost':
      return 'bg-sky-100 text-sky-800'
    case 'card_cost_per_small':
      return 'bg-violet-100 text-violet-800'
    default:
      return 'bg-gray-100 text-gray-600'
  }
}

function rowHighlightClass(beginVal) {
  const n = Math.abs(Number(beginVal) || 0)
  if (n >= 1_000_000_000) return 'bg-red-50 hover:bg-red-100'
  if (n >= 100_000_000) return 'bg-orange-50 hover:bg-orange-100'
  if (n >= 10_000_000) return 'bg-amber-50 hover:bg-amber-100'
  return 'hover:bg-gray-50'
}

function applyFilters() {
  const q = {
    bulan: form.bulan,
    limit: form.limit || 100
  }
  if (form.warehouse_id) {
    q.warehouse_id = form.warehouse_id
  }
  router.get(route('cost-report-ho.trace-begin.view'), q, {
    preserveScroll: true,
    preserveState: false
  })
}
</script>
