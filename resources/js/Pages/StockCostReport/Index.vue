<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-2xl font-bold mb-6">Laporan Stok & Cost</h1>
      <!-- Filters -->
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
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
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item Whole</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Pengurangan Whole</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit Whole</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item PCS</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Penambahan PCS</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">MAC</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Saldo</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Nilai Stok</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="(group, groupIdx) in mergedRows" :key="group.key">
              <template v-for="(row, idx) in group.rows" :key="group.key + '-' + idx">
                <tr class="hover:bg-gray-50 transition">
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm">{{ formatDate(row.tanggal) }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm">{{ row.warehouse }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm">{{ row.item_whole }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatNumber(row.pengurangan_whole) }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm">{{ row.unit_whole || row.unit }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">{{ row.item_pcs }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">{{ row.unit }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatNumber(row.penambahan_pcs) }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatCurrency(row.mac) }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatNumber(row.saldo) }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatCurrency(row.nilai_stok) }}</td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  report: Array,
  warehouses: Array,
  filters: Object
});

const selectedWarehouse = ref(props.filters.warehouse_id || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');

watch([selectedWarehouse, from, to], () => {
  router.get(
    route('inventory.stock-cost-report'),
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

const filteredReport = computed(() => {
  let data = props.report;
  if (selectedWarehouse.value) {
    data = data.filter(row => row.warehouse === props.warehouses.find(w => w.id == selectedWarehouse.value)?.name);
  }
  return data;
});

// Grouping logic for merge row
const mergedRows = computed(() => {
  // Group by: tanggal, warehouse, item_whole, pengurangan_whole, unit_whole
  const groups = [];
  let lastKey = null;
  let currentGroup = null;
  props.report.forEach(row => {
    // Gunakan unit_whole jika ada, fallback ke unit
    const unitWhole = row.unit_whole || row.unit;
    const key = [row.tanggal, row.warehouse, row.item_whole, row.pengurangan_whole, unitWhole].join('|');
    if (key !== lastKey) {
      if (currentGroup) groups.push(currentGroup);
      currentGroup = { key, rows: [row] };
      lastKey = key;
    } else {
      currentGroup.rows.push(row);
    }
  });
  if (currentGroup) groups.push(currentGroup);
  return groups;
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
function formatCurrency(val) {
  if (val == null) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script> 