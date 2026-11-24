<template>
  <AppLayout>
    <div class="max-w-xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fa-solid fa-recycle text-green-500"></i> Detail Category Cost Outlet
      </h1>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="mb-4">
          <div class="mb-2">
            <b>Nomor:</b> 
            <span class="font-semibold" :class="header.number && header.number.startsWith('DRAFT-') ? 'text-orange-600' : 'text-blue-600'">
              {{ header.number || '-' }}
            </span>
          </div>
          <div class="mb-2"><b>ID:</b> {{ header.id }}</div>
          <div class="mb-2"><b>Tipe:</b> {{ typeLabel(header.type) }}</div>
          <div class="mb-2"><b>Tanggal:</b> {{ formatDate(header.date) }}</div>
          <div class="mb-2"><b>Outlet:</b> {{ header.outlet_name }}</div>
          <div v-if="header.type === 'internal_use' && header.nama_ruko" class="mb-2"><b>Ruko:</b> {{ header.nama_ruko }}</div>
        </div>
        <div>
          <b>Detail Item:</b>
          <table class="w-full mt-2 border text-sm">
            <thead>
              <tr class="bg-gray-100">
                <th class="px-2 py-1 border">Mode</th>
                <th class="px-2 py-1 border">Item</th>
                <th v-if="isUsageType" class="px-2 py-1 border">Stock On Hand</th>
                <th v-if="isUsageType" class="px-2 py-1 border">Stock Fisik</th>
                <th class="px-2 py-1 border">{{ isUsageType ? 'Qty Usage' : 'Qty' }}</th>
                <th class="px-2 py-1 border">Unit</th>
                <th class="px-2 py-1 border">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="item in details" :key="item.id">
                <tr>
                  <td class="px-2 py-1 border">
                    <span
                      class="inline-flex px-2 py-0.5 rounded text-xs font-semibold"
                      :class="item.line_mode === 'menu' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'"
                    >
                      {{ item.line_mode === 'menu' ? 'Menu' : 'Bahan' }}
                    </span>
                  </td>
                  <td class="px-2 py-1 border">
                    <div>{{ item.item_name }}</div>
                    <div v-if="item.modifier_labels?.length" class="mt-1 flex flex-wrap gap-1">
                      <span
                        v-for="(label, i) in item.modifier_labels"
                        :key="i"
                        class="inline-flex px-1.5 py-0.5 rounded text-[11px] bg-emerald-100 text-emerald-800 border border-emerald-200"
                      >
                        {{ label }}
                      </span>
                    </div>
                  </td>
                  <td v-if="isUsageType" class="px-2 py-1 border">{{ formatNumber(item.stock_on_hand) }}</td>
                  <td v-if="isUsageType" class="px-2 py-1 border">{{ formatNumber(item.physical_qty) }}</td>
                  <td class="px-2 py-1 border">{{ formatNumber(item.qty) }}</td>
                  <td class="px-2 py-1 border">{{ item.unit_name }}</td>
                  <td class="px-2 py-1 border">{{ item.note || '-' }}</td>
                </tr>
                <tr v-if="item.line_mode === 'menu' && item.bom_preview?.length">
                  <td :colspan="isUsageType ? 7 : 5" class="px-3 py-2 border bg-emerald-50/60">
                    <div class="text-xs font-semibold text-emerald-800 mb-1">
                      BOM yang dipotong × {{ formatNumber(item.qty) }} porsi
                      <span v-if="item.modifier_labels?.length"> (menu + modifier)</span>
                    </div>
                    <ul class="text-xs text-emerald-900 space-y-0.5">
                      <li v-for="(bom, bomIdx) in item.bom_preview" :key="`${bom.material_item_id}-${bomIdx}`">
                        {{ bom.material_name }}:
                        {{ formatNumber(bom.qty_total) }} {{ bom.unit_name || '' }}
                        <span class="text-emerald-700/80">({{ formatNumber(bom.qty_per_portion) }} / porsi)</span>
                      </li>
                    </ul>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div class="mt-6 flex justify-end">
          <button @click="goBack" class="btn btn-ghost px-6 py-2 rounded-lg">Kembali</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  id: [String, Number],
  header: Object,
  details: Array
})

const isUsageType = computed(() => {
  const t = props.header?.type
  return t === 'usage' || t === 'stock_cut'
})

function typeLabel(type) {
  if (type === 'internal_use') return 'Internal Use'
  if (type === 'spoil') return 'Spoil'
  if (type === 'waste') return 'Waste'
  if (type === 'usage') return 'Usage'
  if (type === 'stock_cut') return 'Usage' /* legacy */
  if (type === 'r_and_d') return 'R & D'
  if (type === 'marketing') return 'Marketing'
  if (type === 'non_commodity') return 'Non Commodity'
  if (type === 'guest_supplies') return 'Guest Supplies'
  if (type === 'wrong_maker') return 'Wrong Maker'
  if (type === 'training') return 'Training'
  return type;
}
function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}
function formatNumber(val) {
  if (val == null) return '-'
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
function goBack() {
  router.visit(route('outlet-internal-use-waste.index'))
}
</script>
