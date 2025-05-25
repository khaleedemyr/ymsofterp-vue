<template>
  <Head title="Butcher Process Details" />

  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="$inertia.visit('/butcher-processes')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold flex items-center gap-2 ml-4">
          <i class="fa-solid fa-cut text-red-500"></i> Butcher Detail
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
                    <label class="block text-sm font-medium text-gray-700">Process Number</label>
                    <p class="mt-1 text-sm text-gray-900">{{ butcherProcess.number }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Process Date</label>
                    <p class="mt-1 text-sm text-gray-900">{{ formatDate(butcherProcess.process_date) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                    <p class="mt-1 text-sm text-gray-900">{{ butcherProcess.warehouse?.name }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Good Receive</label>
                    <p class="mt-1 text-sm text-gray-900">
                      {{ butcherProcess.gr_number || '-' }}
                    </p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Created By</label>
                    <p class="mt-1 text-sm text-gray-900">{{ butcherProcess.created_by_nama_lengkap || '-' }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Created At</label>
                    <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(butcherProcess.created_at) }}</p>
                  </div>
                </div>
              </div>

              <!-- Items -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Items</h3>
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Whole Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PCS Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Whole Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PCS Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC PCS</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in butcherProcess.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ item.wholeItem?.name || item.whole_item_name || '-' }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ item.pcsItem?.name || item.pcs_item_name || '-' }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ item.whole_qty }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ item.pcs_qty }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ item.unit?.name }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ item.details && item.details.length > 0 ? Number(item.details[0].mac_pcs).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Item Details Card -->
              <div class="max-w-3xl w-full mx-auto">
                <div v-if="butcherProcess.items.length && butcherProcess.items[0].details && butcherProcess.items[0].details.length" class="bg-white rounded-2xl shadow p-6 mb-6">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div><b>Slaughter Date:</b> {{ formatDate(butcherProcess.items[0].details[0].slaughter_date) }}</div>
                    <div><b>Packing Date:</b> {{ formatDate(butcherProcess.items[0].details[0].packing_date) }}</div>
                    <div><b>Batch Est:</b> {{ butcherProcess.items[0].details[0].batch_est }}</div>
                    <div><b>Qty Purchase:</b> {{ butcherProcess.items[0].details[0].qty_purchase }}</div>
                    <div><b>Susut Air Qty:</b> {{ butcherProcess.items[0].details[0].susut_air_qty }}</div>
                    <div><b>Susut Air Unit:</b> {{ butcherProcess.items[0].details[0].susut_air_unit }}</div>
                    <div>
                      <b>Attachment PDF:</b>
                      <a v-if="butcherProcess.items[0].details[0].attachment_pdf" :href="`/storage/${butcherProcess.items[0].details[0].attachment_pdf}`" target="_blank" class="text-blue-600 underline">PDF</a>
                      <span v-else>-</span>
                    </div>
                    <div>
                      <b>Upload Image:</b>
                      <a v-if="butcherProcess.items[0].details[0].upload_image" :href="`/storage/${butcherProcess.items[0].details[0].upload_image}`" target="_blank" class="text-blue-600 underline">Image</a>
                      <span v-else>-</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Certificates -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Halal Certificates</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div v-for="cert in butcherProcess.certificates" :key="cert.id" class="border rounded-lg p-4">
                    <div class="mb-4">
                      <label class="block text-sm font-medium text-gray-700">Producer Name</label>
                      <p class="mt-1 text-sm text-gray-900">{{ cert.producer_name }}</p>
                    </div>
                    <div class="mb-4">
                      <label class="block text-sm font-medium text-gray-700">Certificate Number</label>
                      <p class="mt-1 text-sm text-gray-900">{{ cert.certificate_number }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Certificate File</label>
                      <a
                        :href="cert.file_url"
                        target="_blank"
                        class="mt-1 inline-flex items-center text-sm text-indigo-600 hover:text-indigo-900"
                      >
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        View Certificate
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div v-if="butcherProcess.notes">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Notes</h3>
                <p class="text-sm text-gray-900">{{ butcherProcess.notes }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  butcherProcess: Object
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID')
}

const formatDateTime = (date) => {
  return new Date(date).toLocaleString('id-ID')
}
</script> 