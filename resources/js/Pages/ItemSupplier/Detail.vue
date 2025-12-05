<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-10">
      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="flex justify-between items-center mb-8">
          <h1 class="text-2xl font-bold flex items-center gap-2 text-blue-700">
            <i class="fa-solid fa-link text-blue-500"></i> Detail Item Supplier
          </h1>
          <button @click="goBack" class="btn btn-ghost px-4 py-2 rounded-lg">
            <i class="fa fa-arrow-left mr-2"></i> Kembali
          </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <div>
            <div class="text-sm text-gray-500 mb-1">Supplier</div>
            <div class="font-medium">{{ itemSupplier.supplier?.name || '-' }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Item</div>
            <div class="font-medium">{{ itemSupplier.item?.name || '-' }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Unit</div>
            <div class="font-medium">{{ itemSupplier.unit?.name || '-' }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Harga</div>
            <div class="font-medium">{{ formatRupiah(itemSupplier.price) }}</div>
          </div>
        </div>
        <div>
          <div class="text-sm text-gray-500 mb-2">Outlet yang Menggunakan Item Ini</div>
          <ul class="list-disc ml-6">
            <li v-for="o in itemSupplier.item_supplier_outlets" :key="o.id">
              {{ o.outlet?.nama_outlet || '-' }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  itemSupplier: Object
})

function goBack() {
  router.visit('/item-supplier')
}
function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script> 