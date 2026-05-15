<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fa-solid fa-recycle text-green-500"></i> Detail Internal Use & Waste
      </h1>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="mb-4">
          <div class="mb-2">
            <b>No. dokumen:</b> {{ headerId }}
            <span
              v-if="header.document_mode && header.document_mode !== 'normal'"
              class="ml-2 inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold"
              :class="{
                'bg-indigo-100 text-indigo-700': header.document_mode === 'serial',
                'bg-purple-100 text-purple-700': header.document_mode === 'mixed',
              }"
            >{{ documentModeLabel(header.document_mode) }}</span>
          </div>
          <div class="mb-2"><b>Tipe:</b> {{ typeLabel(header.type) }}</div>
          <div class="mb-2"><b>Tanggal:</b> {{ formatDate(header.date) }}</div>
          <div class="mb-2"><b>Warehouse:</b> {{ header.warehouse_name }}</div>
          <div v-if="header.type === 'internal_use' && header.nama_ruko" class="mb-2"><b>Ruko:</b> {{ header.nama_ruko }}</div>
          <div v-if="header.notes" class="mb-2"><b>Catatan dokumen:</b> {{ header.notes }}</div>
        </div>

        <div v-if="serialItems.length > 0" class="mb-6">
          <b class="text-indigo-700">Detail Nomor Seri</b>
          <table class="w-full mt-2 border text-sm">
            <thead>
              <tr class="bg-indigo-50">
                <th class="px-2 py-1 border text-left">Serial</th>
                <th class="px-2 py-1 border text-left">Item</th>
                <th class="px-2 py-1 border text-right">Qty</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, idx) in serialItems" :key="idx">
                <td class="px-2 py-1 border font-mono">{{ s.serial_number }}</td>
                <td class="px-2 py-1 border">{{ s.item_name }}</td>
                <td class="px-2 py-1 border text-right">{{ formatNumber(s.qty) }} {{ s.unit_name }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div>
          <b>Daftar item (qty):</b>
          <table class="w-full mt-2 border text-sm">
            <thead>
              <tr class="bg-gray-100">
                <th class="px-2 py-1 border">Item</th>
                <th class="px-2 py-1 border">Qty</th>
                <th class="px-2 py-1 border">Unit</th>
                <th class="px-2 py-1 border">Catatan baris</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="lines.length === 0">
                <td colspan="4" class="px-2 py-4 text-center text-gray-400">Tidak ada item qty</td>
              </tr>
              <tr v-for="ln in lines" :key="ln.id">
                <td class="px-2 py-1 border">{{ ln.item_name }}</td>
                <td class="px-2 py-1 border">{{ formatNumber(ln.qty) }}</td>
                <td class="px-2 py-1 border">{{ ln.unit_name }}</td>
                <td class="px-2 py-1 border">{{ ln.line_notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <button
            v-if="!header.document_mode || header.document_mode === 'normal'"
            type="button"
            class="btn btn-outline border rounded-lg px-4 py-2"
            @click="goEdit"
          >Edit</button>
          <button type="button" class="btn btn-ghost px-6 py-2 rounded-lg border" @click="goBack">Kembali</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  headerId: { type: Number, required: true },
  header: { type: Object, required: true },
  lines: { type: Array, required: true },
  serialItems: { type: Array, default: () => [] },
})

function documentModeLabel(mode) {
  if (mode === 'serial') return 'Serial'
  if (mode === 'mixed') return 'Campuran'
  return null
}

function typeLabel(type) {
  if (type === 'internal_use') return 'Internal Use'
  if (type === 'spoil') return 'Spoil'
  if (type === 'waste') return 'Waste'
  return type
}
function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}
function formatNumber(val) {
  if (val == null) return '-'
  if (Number(val) % 1 === 0) return Number(val)
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
function goBack() {
  router.visit(route('internal-use-waste.index'))
}
function goEdit() {
  router.visit(route('internal-use-waste.edit', props.headerId))
}
</script>
