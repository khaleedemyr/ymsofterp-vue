<template>
  <AppLayout title="Internal Warehouse Transfer Detail">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Internal Warehouse Transfer Detail
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- Header with Back Button -->
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-medium text-gray-900">Transfer #{{ transfer.transfer_number }}</h3>
            <div class="flex space-x-2">
              <Link
                :href="route('internal-warehouse-transfer.index')"
                class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition"
              >
                Back to List
              </Link>
              <button
                @click="confirmDelete"
                class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition"
              >
                Delete
              </button>
            </div>
          </div>

          <!-- Transfer Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-sm font-medium text-gray-700 mb-3">Informasi Transfer</h4>
              <div class="space-y-2">
                <div>
                  <label class="block text-sm font-medium text-gray-600">Transfer Number</label>
                  <div class="mt-1 font-mono">{{ transfer.transfer_number }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-600">Transfer Date</label>
                  <div class="mt-1">{{ formatDate(transfer.transfer_date) }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-600">Notes</label>
                  <div class="mt-1">{{ transfer.notes || '-' }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-600">Created By</label>
                  <div class="mt-1">{{ transfer.creator?.nama_lengkap }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-600">Created At</label>
                  <div class="mt-1">{{ formatDateTime(transfer.created_at) }}</div>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-sm font-medium text-gray-700 mb-3">Informasi Gudang</h4>
              <div class="space-y-2">
                <div>
                  <label class="block text-sm font-medium text-gray-600">Outlet</label>
                  <div class="mt-1">{{ getOutletName(transfer.outlet_id) }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-600">Departemen Asal</label>
                  <div class="mt-1">{{ transfer.warehouse_outlet_from?.name }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-600">Departemen Tujuan</label>
                  <div class="mt-1">{{ transfer.warehouse_outlet_to?.name }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="mb-6">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Items</h4>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Small</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Medium</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Large</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Small</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Medium</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Large</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="item in transfer.items" :key="item.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {{ item.item?.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ item.item?.sku }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ item.unit?.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ formatNumber(item.qty_small) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ formatNumber(item.qty_medium) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ formatNumber(item.qty_large) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ formatCurrency(item.cost_small) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ formatCurrency(item.cost_medium) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ formatCurrency(item.cost_large) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {{ formatCurrency(item.total_cost) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Summary -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Summary</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-600">Total Items</label>
                <div class="mt-1 text-lg font-semibold">{{ transfer.items?.length || 0 }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Total Cost</label>
                <div class="mt-1 text-lg font-semibold">
                  {{ formatCurrency(transfer.items?.reduce((sum, item) => sum + (item.total_cost || 0), 0) || 0) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  transfer: Object,
  outlets: Object,
})

// Format functions
const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

const formatDateTime = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('id-ID')
}

const formatNumber = (number) => {
  if (number === null || number === undefined) return '0'
  return parseFloat(number).toLocaleString('id-ID')
}

const formatCurrency = (amount) => {
  if (amount === null || amount === undefined) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

// Get outlet name
const getOutletName = (outletId) => {
  if (!outletId || !props.outlets) return '-'
  return props.outlets[outletId]?.nama_outlet || '-'
}

// Delete function
const confirmDelete = () => {
  if (confirm('Are you sure you want to delete this transfer?')) {
    router.delete(route('internal-warehouse-transfer.destroy', props.transfer.id), {
      onSuccess: () => {
        router.visit('/internal-warehouse-transfer')
      },
      onError: (errors) => {
        console.error('Error deleting transfer:', errors)
      }
    })
  }
}
</script>
