<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-blue-800 flex items-center gap-2">
          <i class="fa-solid fa-truck-arrow-right text-blue-500"></i> Detail Delivery Order
        </h1>
      </div>
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="mb-2"><span class="font-semibold text-gray-600">Nomor DO:</span> <span class="text-blue-700">{{ order.number || '-' }}</span></div>
            <div class="mb-2"><span class="font-semibold text-gray-600">Tanggal:</span> {{ formatDate(order.created_at) }}</div>
            <div class="mb-2"><span class="font-semibold text-gray-600">User:</span> {{ order.created_by_name || '-' }}</div>
            <div class="mb-2"><span class="font-semibold text-gray-600">Status:</span> {{ order.do_status || '-' }}</div>
          </div>
          <div>
            <div class="mb-2"><span class="font-semibold text-gray-600">Packing List:</span> {{ order.packing_number || '-' }}</div>
            <div class="mb-2"><span class="font-semibold text-gray-600">Floor Order:</span> {{ order.floor_order_number || '-' }}</div>
            <div class="mb-2"><span class="font-semibold text-gray-600">Tanggal Packing:</span> {{ formatDate(order.packing_date) }}</div>
            <div class="mb-2">
              <span class="font-semibold text-gray-600">Mode Scan:</span>
              <span v-if="order.scan_mode === 'serial'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-purple-100 text-purple-800">
                <i class="fa-solid fa-hashtag mr-1"></i> Nomor Seri
              </span>
              <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800">
                <i class="fa-solid fa-barcode mr-1"></i> Barcode
              </span>
            </div>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-lg font-bold text-blue-700 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-list"></i> Daftar Item
        </h2>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">No</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">Nama Item</th>
                <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase">Qty Packing</th>
                <th class="px-4 py-2 text-right text-xs font-bold text-blue-700 uppercase">Qty Scan</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase">Unit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!items.length">
                <td colspan="5" class="text-center py-8 text-blue-300">Tidak ada item.</td>
              </tr>
              <template v-for="(item, idx) in items" :key="item.id">
                <tr class="hover:bg-blue-50 transition">
                  <td class="px-4 py-2">{{ idx + 1 }}</td>
                  <td class="px-4 py-2">{{ item.item_name || '-' }}</td>
                  <td class="px-4 py-2 text-right">{{ item.qty_packing_list }}</td>
                  <td class="px-4 py-2 text-right">{{ item.qty_scan }}</td>
                  <td class="px-4 py-2">{{ item.unit || '-' }}</td>
                </tr>
                <tr v-if="order.scan_mode === 'serial' && item.serial_list && item.serial_list.length" class="bg-purple-50">
                  <td></td>
                  <td colspan="4" class="px-4 py-2">
                    <div class="text-xs font-semibold text-purple-700 mb-1">Nomor Seri ({{ item.serial_list.length }}):</div>
                    <div class="flex flex-wrap gap-1">
                      <span v-for="sn in item.serial_list" :key="sn.id" class="inline-block bg-white border border-purple-200 rounded px-2 py-0.5 text-xs font-mono text-purple-800">
                        {{ sn.serial_number }}
                        <span v-if="sn.repack_unit_name" class="text-purple-600 font-semibold ml-1">(1 {{ sn.repack_unit_name }} = {{ formatQty(sn.repack_qty) }} {{ sn.unit_name }})</span>
                      </span>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
      <div class="mt-8">
        <Link href="/delivery-order" class="inline-flex items-center btn bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-4 py-2 font-semibold transition">
          <i class="fa fa-arrow-left mr-2"></i> Kembali ke Index
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  order: Object,
  items: Array
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function formatQty(val) {
  if (val == null) return '';
  return parseFloat(Number(val).toFixed(4)).toString();
}

// Untuk menampilkan nama item jika belum ada di data items
const items = computed(() => {
  return props.items.map(item => ({
    ...item,
    name: item.name || item.item_name || '-',
  }));
});
</script> 