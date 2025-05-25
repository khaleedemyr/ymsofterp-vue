<template>
  <Head title="MK Production Details" />
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="$inertia.visit('/mk-production')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold flex items-center gap-2 ml-4">
          <i class="fa-solid fa-industry text-blue-500"></i> MK Production Detail
        </h1>
      </div>
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
              <!-- Basic Information -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Production Date</label>
                    <p class="mt-1 text-sm text-gray-900">{{ formatDate(prod.production_date) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Batch Number</label>
                    <p class="mt-1 text-sm text-gray-900">{{ prod.batch_number }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Item</label>
                    <p class="mt-1 text-sm text-gray-900">{{ item?.name }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                    <p class="mt-1 text-sm text-gray-900">{{ warehouse?.name }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Qty Produksi</label>
                    <p class="mt-1 text-sm text-gray-900">{{ Number(prod.qty).toFixed(2) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Qty Jadi</label>
                    <p class="mt-1 text-sm text-gray-900">{{ Number(prod.qty_jadi).toFixed(2) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <p class="mt-1 text-sm text-gray-900">{{ prod.notes }}</p>
                  </div>
                </div>
              </div>

              <!-- Stock Card -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Stock Card</h3>
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">In Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Out Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="card in stockCard" :key="card.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(card.date) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ Number(card.in_qty_small).toFixed(2) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ Number(card.out_qty_small).toFixed(2) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ Number(card.saldo_qty_small).toFixed(2) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ card.description }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- BOM -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">BOM (Bill of Materials)</h3>
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Material</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty per 1</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="b in bom" :key="b.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ b.material_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ Number(b.qty).toFixed(2) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ b.unit_name }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  prod: Object,
  item: Object,
  warehouse: Object,
  stockCard: Array,
  bom: Array
})

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}
</script> 