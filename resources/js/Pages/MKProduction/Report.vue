<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-2xl font-bold mb-6">Laporan MK Production</h1>
      <!-- Filter tanggal -->
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <div class="flex items-center gap-2">
          <label class="text-sm">Dari</label>
          <input type="date" v-model="start_date" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Sampai</label>
          <input type="date" v-model="end_date" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <button @click="filter" class="bg-blue-500 text-white px-4 py-2 rounded-lg font-semibold shadow hover:bg-blue-600">Filter</button>
      </div>
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Batch</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Produksi</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Jadi</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Exp Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Notes</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="prod in productions" :key="prod.id" class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(prod.production_date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ prod.batch_number }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ prod.item_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatNumber(prod.qty) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatNumber(prod.qty_jadi) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ prod.warehouse_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(prod.exp_date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ prod.notes }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  productions: Array,
  start_date: String,
  end_date: String,
})

const start_date = ref(props.start_date || '')
const end_date = ref(props.end_date || '')

function filter() {
  router.get(route('mk-production.report'), {
    start_date: start_date.value,
    end_date: end_date.value,
  }, { preserveState: true })
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}
function formatNumber(val) {
  if (val == null) return '-';
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script> 