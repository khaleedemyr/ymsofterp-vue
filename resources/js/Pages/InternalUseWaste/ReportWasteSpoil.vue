<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-2xl font-bold mb-6">Laporan Spoil & Waste</h1>
      <div class="mb-4 flex justify-end">
        <button @click="goBack" class="btn btn-ghost px-6 py-2 rounded-lg">Kembali</button>
      </div>
      <!-- Filters -->
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in props.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Dari</label>
          <input type="date" v-model="from" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Sampai</label>
          <input type="date" v-model="to" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
      </div>
      <!-- Report Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipe</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Catatan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="row in filteredData" :key="row.id" class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(row.date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.warehouse_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.item_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ typeLabel(row.type) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatNumber(row.qty) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.unit_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.notes || '-' }}</td>
              </tr>
              <tr v-if="!filteredData.length">
                <td colspan="7" class="text-center py-8 text-gray-400">Tidak ada data.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  data: Array,
  warehouses: Array,
  filters: Object
});

const selectedWarehouse = ref(props.filters.warehouse_id || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');

watch([selectedWarehouse, from, to], () => {
  router.get(
    route('internal-use-waste.report-waste-spoil'),
    {
      warehouse_id: selectedWarehouse.value,
      from: from.value,
      to: to.value
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true
    }
  );
});

const filteredData = computed(() => {
  let data = props.data;
  if (selectedWarehouse.value) {
    data = data.filter(row => String(row.warehouse_id) === String(selectedWarehouse.value));
  }
  return data;
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}
function formatNumber(val) {
  if (val == null) return '-';
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
function typeLabel(type) {
  if (type === 'spoil') return 'Spoil';
  if (type === 'waste') return 'Waste';
  return type;
}

function goBack() {
  router.visit(route('internal-use-waste.index'))
}
</script> 