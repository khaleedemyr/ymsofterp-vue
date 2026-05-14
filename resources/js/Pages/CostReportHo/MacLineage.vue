<template>
  <AppLayout>
    <div class="w-full py-8 px-2 max-w-[100rem] mx-auto">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Rantai MAC — histori biaya</h1>
          <p v-if="warehouse && item" class="text-sm text-gray-600 mt-1">
            <strong>{{ warehouse.name }}</strong>
            <span v-if="warehouse.code" class="text-gray-500"> ({{ warehouse.code }})</span>
            · {{ item.item_name }}
            <span v-if="item.item_sku" class="font-mono text-xs ml-1">{{ item.item_sku }}</span>
            · inv#{{ item.inventory_item_id }}
          </p>
        </div>
        <Link
          :href="route('cost-report-ho.trace-begin.view', { bulan: back_bulan })"
          class="text-sm text-blue-600 hover:underline"
        >
          ← Trace begin
        </Link>
      </div>

      <div class="bg-amber-50 border border-amber-100 rounded-lg p-4 mb-6 text-sm text-amber-950">
        <p class="font-medium text-amber-900 mb-1">Sumber nilai MAC di database</p>
        <p class="mb-2">{{ mac_formula_note }}</p>
        <p v-if="stock" class="text-xs">
          Stok saat ini (food_inventory_stocks): qty_small = <strong>{{ fmt(stock.qty_small) }}</strong>,
          value = <strong>{{ fmt(stock.value) }}</strong>,
          last_cost_small = <strong>{{ fmt(stock.last_cost_small) }}</strong>.
          <span v-if="implied_mac_from_stock != null">
            Implisit value/qty = <strong>{{ fmt(implied_mac_from_stock) }}</strong> (bandingkan dengan MAC histori terbaru).
          </span>
        </p>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-slate-800 text-white">
              <tr>
                <th class="px-2 py-2 text-left">ID</th>
                <th class="px-2 py-2 text-left">Tanggal</th>
                <th class="px-2 py-2 text-left">Tipe</th>
                <th class="px-2 py-2 text-left">Ref</th>
                <th class="px-2 py-2 text-right">old_cost</th>
                <th class="px-2 py-2 text-right">new_cost</th>
                <th class="px-2 py-2 text-right font-semibold">mac</th>
                <th class="px-2 py-2 text-left">GR #</th>
                <th class="px-2 py-2 text-left text-xs">created_at</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="h in histories" :key="h.id" class="hover:bg-gray-50">
                <td class="px-2 py-1.5 font-mono text-xs">{{ h.id }}</td>
                <td class="px-2 py-1.5 whitespace-nowrap">{{ h.date }}</td>
                <td class="px-2 py-1.5">{{ h.type }}</td>
                <td class="px-2 py-1.5 font-mono text-xs">
                  {{ h.reference_type }} #{{ h.reference_id }}
                </td>
                <td class="px-2 py-1.5 text-right font-mono text-xs">{{ fmt(h.old_cost) }}</td>
                <td class="px-2 py-1.5 text-right font-mono text-xs">{{ fmt(h.new_cost) }}</td>
                <td class="px-2 py-1.5 text-right font-mono text-xs font-semibold">{{ fmt(h.mac) }}</td>
                <td class="px-2 py-1.5 text-xs">{{ h.gr_number || '—' }}</td>
                <td class="px-2 py-1.5 text-xs text-gray-500">{{ h.created_at }}</td>
              </tr>
              <tr v-if="!histories.length">
                <td colspan="9" class="px-4 py-8 text-center text-gray-500">Tidak ada baris histori.</td>
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
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  warehouse: { type: Object, default: null },
  item: { type: Object, default: null },
  stock: { type: Object, default: null },
  implied_mac_from_stock: { type: Number, default: null },
  histories: { type: Array, default: () => [] },
  mac_formula_note: { type: String, default: '' },
  back_bulan: { type: String, default: '' }
})

function fmt(v) {
  if (v == null || v === '') return '—'
  const n = Number(v)
  if (Number.isNaN(n)) return '—'
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 8 }).format(n)
}
</script>
