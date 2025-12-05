<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Laporan Perubahan Harga PO per Item</h1>
      <form class="flex flex-wrap gap-4 mb-6 items-end" @submit.prevent="reload">
        <div>
          <label class="block text-sm font-medium mb-1">Dari Tanggal</label>
          <input type="date" v-model="from_date" class="border rounded px-2 py-1" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Sampai Tanggal</label>
          <input type="date" v-model="to_date" class="border rounded px-2 py-1" />
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Tampilkan</button>
      </form>
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Barang</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Harga Sebelumnya</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Supplier Sebelumnya</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Tgl PO Sebelumnya</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Harga Sekarang</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Supplier Sekarang</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Tgl PO Sekarang</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">% Perubahan</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!reports.length">
              <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data perubahan harga.</td>
            </tr>
            <tr v-for="row in reports" :key="row.item_name + row.latest_po_number" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.item_name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatCurrency(row.prev_price) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm">{{ row.prev_supplier }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm">{{ formatDate(row.prev_po_date) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatCurrency(row.latest_price) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm">{{ row.latest_supplier }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm">{{ formatDate(row.latest_po_date) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                <span :class="percentClass(row.percent_change)">
                  {{ row.percent_change === null ? '-' : (row.percent_change > 0 ? '+' : '') + row.percent_change + '%' }}
                </span>
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
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const props = defineProps({
  reports: Array,
  from_date: String,
  to_date: String
});
const from_date = ref(props.from_date || '');
const to_date = ref(props.to_date || '');
const reports = ref(props.reports || []);

function reload() {
  router.get(route('inventory.po-price-change-report'), {
    from_date: from_date.value,
    to_date: to_date.value
  }, { preserveState: true, preserveScroll: true });
}
function formatCurrency(val) {
  if (val === null || val === undefined) return '-';
  return Number(val).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
}
function formatDate(val) {
  if (!val) return '-';
  return new Date(val).toLocaleDateString('id-ID');
}
function percentClass(val) {
  if (val === null) return 'text-gray-400';
  if (val > 0) return 'text-red-600 font-bold';
  if (val < 0) return 'text-green-600 font-bold';
  return 'text-gray-600';
}
</script> 