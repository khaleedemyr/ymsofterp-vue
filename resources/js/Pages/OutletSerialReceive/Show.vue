<template>
  <Head :title="`GR Serial: ${header.number}`" />
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-purple-800 flex items-center gap-2">
          <i class="fa-solid fa-barcode text-purple-500"></i> Detail GR Serial
        </h1>
        <a href="/outlet-serial-receive" class="text-sm text-blue-600 hover:underline">
          <i class="fa fa-arrow-left"></i> Kembali ke daftar
        </a>
      </div>

      <!-- Header Info -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <span class="text-xs text-gray-500 block">Nomor GR</span>
            <span class="font-mono font-bold text-purple-700 text-lg">{{ header.number }}</span>
          </div>
          <div>
            <span class="text-xs text-gray-500 block">Tanggal</span>
            <span class="font-semibold">{{ formatDate(header.receive_date) }}</span>
          </div>
          <div>
            <span class="text-xs text-gray-500 block">User</span>
            <span class="font-semibold">{{ header.created_by_name || '-' }}</span>
          </div>
          <div>
            <span class="text-xs text-gray-500 block">Jumlah Serial</span>
            <span class="font-bold text-2xl text-green-700">{{ items.length }}</span>
          </div>
        </div>
        <div v-if="header.notes" class="mt-4 pt-3 border-t">
          <span class="text-xs text-gray-500 block">Catatan</span>
          <span class="text-sm">{{ header.notes }}</span>
        </div>
      </div>

      <!-- Items Table -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-lg font-bold text-purple-700 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-list"></i> Daftar Serial
        </h2>
        <div v-if="!items.length" class="text-center text-gray-400 py-8">Tidak ada item.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm divide-y divide-gray-200">
            <thead class="bg-purple-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">No</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Serial</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Item</th>
                <th class="px-3 py-2 text-right text-xs font-bold text-purple-700">Qty</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Unit</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">DO</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Outlet</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Warehouse</th>
                <th class="px-3 py-2 text-right text-xs font-bold text-purple-700">Harga</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Sumber</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in items" :key="row.id" class="hover:bg-purple-50 transition">
                <td class="px-3 py-2">{{ idx + 1 }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ row.serial_number }}</td>
                <td class="px-3 py-2">{{ row.item_name }}</td>
                <td class="px-3 py-2 text-right">{{ fmtQty(row.qty) }}</td>
                <td class="px-3 py-2">{{ row.unit_name || '-' }}</td>
                <td class="px-3 py-2 text-xs">{{ row.delivery_order_number }}</td>
                <td class="px-3 py-2 text-xs">{{ row.outlet_name || row.outlet_id }}</td>
                <td class="px-3 py-2 text-xs">{{ row.warehouse_name || '-' }}</td>
                <td class="px-3 py-2 text-right">{{ formatRupiah(row.cost_small) }}</td>
                <td class="px-3 py-2 text-xs">{{ formatCostSource(row.cost_source) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50">
              <tr>
                <td colspan="3" class="px-3 py-2 text-right font-bold">Total</td>
                <td class="px-3 py-2 text-right font-bold">{{ fmtQty(totalQty) }}</td>
                <td colspan="4"></td>
                <td class="px-3 py-2 text-right font-bold">{{ formatRupiah(totalCost) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

const props = defineProps({
  header: Object,
  items: Array,
})

const totalQty = computed(() => props.items.reduce((sum, i) => sum + Number(i.qty || 0), 0))
const totalCost = computed(() => props.items.reduce((sum, i) => sum + Number(i.cost_small || 0) * Number(i.qty || 0), 0))

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

function formatRupiah(val) {
  if (!val) return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function fmtQty(val) {
  if (!val && val !== 0) return '-'
  let s = Number(val).toFixed(4)
  s = s.replace(/\.?0+$/, '')
  return s
}

function formatCostSource(src) {
  if (!src) return '-'
  if (src === 'fgr_modal_12pct') return 'FGR (Modal+12%)'
  if (src === 'item_prices') return 'Item Price'
  return src
}
</script>
