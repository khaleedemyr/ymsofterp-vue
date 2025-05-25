<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck-arrow-right text-blue-500"></i> Delivery Order
        </h1>
        <Link href="/delivery-order/create" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Delivery Order
        </Link>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Packing List</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Floor Order</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!orders.length">
              <td colspan="6" class="text-center py-10 text-blue-300">Tidak ada data Delivery Order.</td>
            </tr>
            <tr v-for="(order, idx) in orders" :key="order.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ idx + 1 }}</td>
              <td class="px-6 py-3">{{ formatDate(order.created_at) }}</td>
              <td class="px-6 py-3">{{ order.packing_number || '-' }}</td>
              <td class="px-6 py-3">{{ order.floor_order_number || '-' }}</td>
              <td class="px-6 py-3">{{ order.created_by_name || '-' }}</td>
              <td class="px-6 py-3">
                <Link :href="`/delivery-order/${order.id}`" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
const props = defineProps({ orders: Array });
function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}
</script> 