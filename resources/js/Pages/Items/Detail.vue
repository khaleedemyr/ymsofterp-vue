<template>
  <Head title="Item Detail" />

  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="$inertia.visit('/items')" class="text-blue-500 hover:underline">
          <i class="fa fa-arrow-left"></i> Kembali ke Items
        </button>
        <h1 class="text-2xl font-bold flex items-center gap-2 ml-4">
          <i class="fa-solid fa-box text-blue-500"></i> Detail Item PCS
        </h1>
      </div>

      <!-- Item Information -->
      <div class="bg-white rounded-lg shadow-sm border mb-6">
        <div class="p-6">
          <h2 class="text-xl font-semibold mb-4">Informasi Item</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nama Item</label>
              <p class="mt-1 text-lg font-semibold text-gray-900">{{ item.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">SKU</label>
              <p class="mt-1 text-lg text-gray-900">{{ item.sku || '-' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Kategori</label>
              <p class="mt-1 text-gray-900">{{ item.category?.name || '-' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Sub Kategori</label>
              <p class="mt-1 text-gray-900">{{ item.subCategory?.name || '-' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Warehouse Division</label>
              <p class="mt-1 text-gray-900">{{ item.warehouseDivision?.name || '-' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Expire Days</label>
              <p class="mt-1 text-gray-900">{{ item.exp || '-' }} hari</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Small Unit</label>
              <p class="mt-1 text-gray-900">{{ item.smallUnit?.name || '-' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Medium Unit</label>
              <p class="mt-1 text-gray-900">{{ item.mediumUnit?.name || '-' }}</p>
            </div>
            <div v-if="item.description" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
              <p class="mt-1 text-gray-900">{{ item.description }}</p>
            </div>
            <div v-if="item.specification" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Spesifikasi</label>
              <p class="mt-1 text-gray-900">{{ item.specification }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Barcodes -->
      <div class="bg-white rounded-lg shadow-sm border mb-6">
        <div class="p-6">
          <h2 class="text-xl font-semibold mb-4">Barcodes</h2>
          <div v-if="barcodes.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="barcode in barcodes" :key="barcode.id" class="border rounded-lg p-4">
              <div class="font-mono text-lg font-bold text-center">{{ barcode.barcode }}</div>
              <div class="text-xs text-gray-500 text-center mt-1">
                {{ formatDate(barcode.created_at) }}
              </div>
            </div>
          </div>
          <div v-else class="text-gray-500 text-center py-4">
            Tidak ada barcode untuk item ini
          </div>
        </div>
      </div>

      <!-- Butcher Processes -->
      <div class="bg-white rounded-lg shadow-sm border mb-6">
        <div class="p-6">
          <h2 class="text-xl font-semibold mb-4">Butcher Processes</h2>
          <div v-if="butcherProcesses.length > 0" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Process Number</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Process Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Whole Item</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Whole Qty</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PCS Qty</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="process in butcherProcesses" :key="process.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <a :href="`/butcher-processes/${process.id}`" class="text-blue-600 hover:underline">
                      {{ process.number }}
                    </a>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDate(process.process_date) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ process.whole_item_name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ process.whole_qty }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ process.pcs_qty }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-gray-500 text-center py-4">
            Tidak ada data butcher process untuk item ini
          </div>
        </div>
      </div>

      

      <!-- Good Receives -->
      <div class="bg-white rounded-lg shadow-sm border mb-6">
        <div class="p-6">
          <h2 class="text-xl font-semibold mb-4">Good Receives (Pembelian)</h2>
          <div v-if="goodReceives.length > 0" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GR Number</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receive Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Received</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="gr in goodReceives" :key="gr.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <a :href="`/food-good-receives/${gr.id}`" class="text-blue-600 hover:underline">
                      {{ gr.gr_number }}
                    </a>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDate(gr.receive_date) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ gr.supplier_name }}
                  </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                     {{ gr.qty_received }}
                   </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-gray-500 text-center py-4">
            Tidak ada data good receive untuk item ini
          </div>
        </div>
      </div>

      <!-- Halal Certificates -->
      <div class="bg-white rounded-lg shadow-sm border mb-6">
        <div class="p-6">
          <h2 class="text-xl font-semibold mb-4">Sertifikat Halal</h2>
          <div v-if="halalCertificates.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="cert in halalCertificates" :key="cert.id" class="border rounded-lg p-4">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Producer Name</label>
                <p class="mt-1 text-sm text-gray-900">{{ cert.producer_name }}</p>
              </div>
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Certificate Number</label>
                <p class="mt-1 text-sm text-gray-900">{{ cert.certificate_number }}</p>
              </div>
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Process Date</label>
                <p class="mt-1 text-sm text-gray-900">{{ formatDate(cert.process_date) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Certificate File</label>
                                 <a
                   :href="cert.file_path"
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
          <div v-else class="text-gray-500 text-center py-4">
            Tidak ada sertifikat halal untuk item ini
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
  item: {
    type: Object,
    required: true
  },
  goodReceives: {
    type: Array,
    default: () => []
  },
     butcherProcesses: {
     type: Array,
     default: () => []
   },
   halalCertificates: {
    type: Array,
    default: () => []
  },
  barcodes: {
    type: Array,
    default: () => []
  }
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID')
}


</script>
