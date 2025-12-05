<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-500 to-green-700 px-6 py-4">
          <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
              <i class="fa-solid fa-shopping-bag"></i> Detail Retail Non Food
            </h1>
            <button @click="goBack" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition-all">
              <i class="fa fa-arrow-left mr-2"></i> Kembali
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="p-6 space-y-6">
          <!-- Transaction Info -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Nomor Transaksi</label>
                <div class="text-lg font-bold text-gray-800">{{ props.retailNonFood.retail_number }}</div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Tanggal Transaksi</label>
                <div class="text-gray-800">{{ formatDate(props.retailNonFood.transaction_date) }}</div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Outlet</label>
                <div class="text-gray-800">{{ props.retailNonFood.outlet?.nama_outlet || '-' }}</div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Category Budget</label>
                <div class="text-gray-800">{{ props.retailNonFood.category_budget?.name || '-' }}</div>
                <div v-if="props.retailNonFood.category_budget" class="text-xs text-gray-500 mt-1">
                  {{ props.retailNonFood.category_budget.division }} - {{ props.retailNonFood.category_budget.subcategory }}
                </div>
              </div>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Status</label>
                <span :class="{
                  'px-3 py-1 text-sm font-semibold rounded-full': true,
                  'bg-yellow-100 text-yellow-800': props.retailNonFood.status === 'pending',
                  'bg-green-100 text-green-800': props.retailNonFood.status === 'approved',
                  'bg-red-100 text-red-800': props.retailNonFood.status === 'rejected'
                }">
                  {{ formatStatus(props.retailNonFood.status) }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Total Amount</label>
                <div class="text-2xl font-bold text-green-600">{{ formatRupiah(props.retailNonFood.total_amount) }}</div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Dibuat Oleh</label>
                <div class="text-gray-800">{{ props.retailNonFood.creator?.name || '-' }}</div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1">Tanggal Dibuat</label>
                <div class="text-gray-800">{{ formatDateTime(props.retailNonFood.created_at) }}</div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Items</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(item, index) in props.retailNonFood.items" :key="item.id">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ index + 1 }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ item.qty }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ item.unit }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900">{{ formatRupiah(item.price) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ formatRupiah(item.subtotal) }}</td>
                  </tr>
                </tbody>
                <tfoot class="bg-gray-50">
                  <tr>
                    <td colspan="5" class="px-4 py-3 text-right font-bold text-gray-900">Total:</td>
                    <td class="px-4 py-3 text-right font-bold text-green-600">{{ formatRupiah(props.retailNonFood.total_amount) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- Notes -->
          <div v-if="props.retailNonFood.notes">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Catatan</h3>
            <div class="bg-gray-50 p-4 rounded-lg">
              <p class="text-gray-700">{{ props.retailNonFood.notes }}</p>
            </div>
          </div>

          <!-- Invoices -->
          <div v-if="props.retailNonFood.invoices && props.retailNonFood.invoices.length" class="mb-8">
            <div class="text-sm text-gray-500 mb-1">Bon/Invoice</div>
            <div class="flex flex-wrap gap-3">
              <div v-for="inv in props.retailNonFood.invoices" :key="inv.id" class="w-32 h-32 border rounded overflow-hidden flex items-center justify-center bg-gray-50">
                <a :href="`/storage/${inv.file_path}`" target="_blank" rel="noopener">
                  <img :src="`/storage/${inv.file_path}`" class="object-contain w-full h-full hover:scale-110 transition-transform duration-200" />
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  retailNonFood: Object
})

function goBack() {
  router.visit('/retail-non-food')
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

function formatDateTime(date) {
  if (!date) return '-'
  return new Date(date).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatStatus(status) {
  const statusMap = {
    pending: 'Menunggu Persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak'
  }
  return statusMap[status] || status
}

function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script> 