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

      <!-- Production Info -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Produksi</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600">Tanggal Produksi</label>
            <p class="text-sm text-gray-900">{{ formatDate(prod.production_date) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Batch Number</label>
            <p class="text-sm text-gray-900">{{ prod.batch_number || '-' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Outlet</label>
            <p class="text-sm text-gray-900">{{ outlet?.nama_outlet }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Warehouse Outlet</label>
            <p class="text-sm text-gray-900">{{ warehouse_outlet?.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Item Hasil Produksi</label>
            <p class="text-sm text-gray-900">{{ item?.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Qty Produksi</label>
            <p class="text-sm text-gray-900">{{ prod.qty }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Qty Jadi</label>
            <p class="text-sm text-gray-900">{{ prod.qty_jadi }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600">Unit</label>
            <p class="text-sm text-gray-900">{{ getUnitName(prod.unit_id) }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600">Catatan</label>
            <p class="text-sm text-gray-900">{{ prod.notes || '-' }}</p>
          </div>
        </div>
      </div>

      <!-- BOM Information -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Bill of Materials (BOM)</h2>
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
              <tr v-for="item in bom" :key="item.material_item_id" class="border-b">
                                 <td class="py-2 px-4">{{ item.material_name }}</td>
                 <td class="py-2 px-4">{{ formatNumber(item.qty) }}</td>
                 <td class="py-2 px-4">{{ item.unit_name }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Stock Card Information -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Kartu Stok</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b bg-gray-50">
                <th class="text-left py-2 px-4">Tanggal</th>
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
              <tr v-for="card in stockCard" :key="card.id" class="border-b">
                <td class="py-2 px-4">{{ formatDate(card.date) }}</td>
                <td class="py-2 px-4">
                  <span :class="card.in_qty_small > 0 ? 'text-green-600' : 'text-red-600'" class="font-semibold">
                    {{ card.in_qty_small > 0 ? 'IN' : 'OUT' }}
                  </span>
                </td>
                                 <td class="py-2 px-4">{{ formatNumber(card.in_qty_small || card.out_qty_small || 0) }}</td>
                 <td class="py-2 px-4">{{ formatNumber(card.in_qty_medium || card.out_qty_medium || 0) }}</td>
                 <td class="py-2 px-4">{{ formatNumber(card.in_qty_large || card.out_qty_large || 0) }}</td>
                <td class="py-2 px-4">{{ formatCurrency(card.value_in || card.value_out || 0) }}</td>
                <td class="py-2 px-4">{{ formatCurrency(card.saldo_value) }}</td>
                <td class="py-2 px-4">{{ card.description }}</td>
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
import { router } from '@inertiajs/vue3'

const props = defineProps({
  prod: Object,
  item: Object,
  outlet: Object,
  warehouse_outlet: Object,
  stockCard: Array,
  bom: Array,
})

function goBack() {
  router.visit(route('outlet-wip.index'))
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID')
}

function formatCurrency(amount) {
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

function getUnitName(unitId) {
  if (!props.item) return ''
  
  if (props.item.small_unit_id == unitId) return props.item.small_unit_name
  if (props.item.medium_unit_id == unitId) return props.item.medium_unit_name
  if (props.item.large_unit_id == unitId) return props.item.large_unit_name
  
  return ''
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
