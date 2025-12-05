<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="btn btn-ghost !px-3 !py-2 rounded-full shadow hover:bg-blue-50">
          <i class="fa fa-arrow-left text-lg"></i>
        </button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-industry text-blue-500"></i> Detail Produksi WIP
        </h1>
      </div>

      <!-- Header Info -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Produksi</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600">Nomor</label>
            <p 
              class="text-sm font-semibold"
              :class="header.number && header.number.startsWith('DRAFT-') ? 'text-orange-600' : 'text-blue-600'"
            >
              {{ header.number || 'Data Lama' }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Status</label>
            <span 
              class="px-2 py-1 rounded text-xs font-semibold inline-block"
              :class="{
                'bg-orange-100 text-orange-700': header.status === 'DRAFT',
                'bg-blue-100 text-blue-700': header.status === 'SUBMITTED',
                'bg-green-100 text-green-700': header.status === 'PROCESSED'
              }"
            >
              {{ header.status }}
            </span>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Tanggal Produksi</label>
            <p class="text-sm text-gray-900">{{ formatDate(header.production_date) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Batch Number</label>
            <p class="text-sm text-gray-900">{{ header.batch_number || '-' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Outlet</label>
            <p class="text-sm text-gray-900">{{ outlet?.nama_outlet || '-' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Warehouse Outlet</label>
            <p class="text-sm text-gray-900">{{ warehouse_outlet?.name || '-' }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600">Catatan</label>
            <p class="text-sm text-gray-900">{{ header.notes || '-' }}</p>
          </div>
        </div>
      </div>

      <!-- Production Items -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Item Produksi</h2>
        <div class="space-y-4">
          <div 
            v-for="(production, index) in productions" 
            :key="production.id || index"
            class="border rounded-lg p-4"
          >
            <h3 class="font-semibold text-gray-700 mb-3">Item Produksi #{{ index + 1 }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-600">Item Hasil Produksi</label>
                <p class="text-sm text-gray-900">{{ production.item_name || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Qty Produksi</label>
                <p class="text-sm text-gray-900">{{ formatNumber(production.qty) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Qty Jadi</label>
                <p class="text-sm text-gray-900">{{ formatNumber(production.qty_jadi) }} {{ production.unit_name || '' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Unit</label>
                <p class="text-sm text-gray-900">{{ production.unit_name || '-' }}</p>
              </div>
            </div>

            <!-- BOM for this production -->
            <div v-if="getBomForItem(production.item_id).length > 0" class="mt-4">
              <h4 class="text-sm font-semibold text-gray-700 mb-2">Bill of Materials (BOM)</h4>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b bg-gray-50">
                      <th class="text-left py-2 px-4">Material</th>
                      <th class="text-left py-2 px-4">Qty</th>
                      <th class="text-left py-2 px-4">Unit</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="bomItem in getBomForItem(production.item_id)" :key="bomItem.material_item_id" class="border-b">
                      <td class="py-2 px-4">{{ bomItem.material_name }}</td>
                      <td class="py-2 px-4">{{ formatNumber(bomItem.qty) }}</td>
                      <td class="py-2 px-4">{{ bomItem.unit_name }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stock Card Information -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Kartu Stok</h2>
        <div v-if="stockCards && stockCards.length > 0" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b bg-gray-50">
                <th class="text-left py-2 px-4">Tanggal</th>
                <th class="text-left py-2 px-4">Item</th>
                <th class="text-left py-2 px-4">Tipe</th>
                <th class="text-left py-2 px-4">Qty Small</th>
                <th class="text-left py-2 px-4">Qty Medium</th>
                <th class="text-left py-2 px-4">Qty Large</th>
                <th class="text-left py-2 px-4">Value</th>
                <th class="text-left py-2 px-4">Saldo</th>
                <th class="text-left py-2 px-4">Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="card in stockCards" :key="card.id" class="border-b">
                <td class="py-2 px-4">{{ formatDate(card.date) }}</td>
                <td class="py-2 px-4 font-medium">{{ card.item_name || '-' }}</td>
                <td class="py-2 px-4">
                  <span 
                    :class="isInTransaction(card) ? 'text-green-600' : 'text-red-600'" 
                    class="font-semibold"
                  >
                    {{ isInTransaction(card) ? 'IN' : 'OUT' }}
                  </span>
                </td>
                <td class="py-2 px-4">
                  <span v-if="isInTransaction(card)">{{ formatNumber(card.in_qty_small || 0) }}</span>
                  <span v-else>{{ formatNumber(card.out_qty_small || 0) }}</span>
                </td>
                <td class="py-2 px-4">
                  <span v-if="isInTransaction(card)">{{ formatNumber(card.in_qty_medium || 0) }}</span>
                  <span v-else>{{ formatNumber(card.out_qty_medium || 0) }}</span>
                </td>
                <td class="py-2 px-4">
                  <span v-if="isInTransaction(card)">{{ formatNumber(card.in_qty_large || 0) }}</span>
                  <span v-else>{{ formatNumber(card.out_qty_large || 0) }}</span>
                </td>
                <td class="py-2 px-4">
                  <span v-if="isInTransaction(card)">{{ formatCurrency(card.value_in || 0) }}</span>
                  <span v-else>{{ formatCurrency(card.value_out || 0) }}</span>
                </td>
                <td class="py-2 px-4">{{ formatCurrency(card.saldo_value || 0) }}</td>
                <td class="py-2 px-4">{{ card.description || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          Tidak ada kartu stok
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  header: Object,
  productions: Array,
  outlet: Object,
  warehouse_outlet: Object,
  stockCards: Array,
  bomData: Object,
})

function getBomForItem(itemId) {
  return props.bomData?.[itemId] || []
}

function goBack() {
  router.visit(route('outlet-wip.index'))
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

function formatCurrency(amount) {
  if (amount === null || amount === undefined) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(amount)
}

function formatNumber(value) {
  if (value === null || value === undefined) return '0.00'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

function isInTransaction(card) {
  // Check if it's an IN transaction (has in_qty_small > 0 or in_qty_medium > 0 or in_qty_large > 0)
  const hasInQty = (card.in_qty_small && parseFloat(card.in_qty_small) > 0) ||
                   (card.in_qty_medium && parseFloat(card.in_qty_medium) > 0) ||
                   (card.in_qty_large && parseFloat(card.in_qty_large) > 0)
  return hasInQty
}
</script>

<style scoped>
.btn.btn-ghost {
  background: transparent;
  border: none;
  color: #6b7280;
}

.btn.btn-ghost:hover {
  background: #f3f4f6;
}
</style>
