<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-2xl font-bold mb-6">Laporan Analisis Pemotongan</h1>
      <!-- Filters & Load Data Button -->
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
        <button @click="reloadData" :disabled="loadingReload" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
          <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
          <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Load Data
        </button>
      </div>
      <!-- Table Analisis -->
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto mb-8">
        <h2 class="text-lg font-bold px-4 pt-4">Analisis Per Proses/Item</h2>
        <table class="min-w-full border border-gray-300 border-collapse divide-y divide-gray-200 mt-2">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Warehouse</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Item Whole</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Input Whole</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Susut Air</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Susut Air (%)</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Unit Susut</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Item PCS</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Output PCS</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Output KG</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Efisiensi (%)</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Cost/Unit Hasil</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="(group, groupIdx) in groupedAnalisis" :key="group.key">
              <template v-for="(row, idx) in group.rows" :key="group.key + '-' + idx">
                <tr class="hover:bg-gray-50 transition">
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm border border-gray-300">{{ formatDate(row.tanggal) }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm border border-gray-300">{{ row.warehouse }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm border border-gray-300">{{ row.item_whole }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm text-right border border-gray-300">{{ formatNumber(row.input_whole) }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm text-right border border-gray-300">{{ formatNumber(row.susut_air) }}</td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm text-right border border-gray-300">
                    {{ row.input_whole && row.susut_air ? ((row.susut_air / row.input_whole) * 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00' }}
                  </td>
                  <td v-if="idx === 0" :rowspan="group.rows.length" class="px-4 py-3 whitespace-nowrap text-sm border border-gray-300">{{ row.susut_air_unit || '-' }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm border border-gray-300">{{ row.item_pcs }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-right border border-gray-300">{{ formatNumber(row.output_pcs) }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-right border border-gray-300">{{ formatNumber(row.output_kg) }}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-right border border-gray-300">
                    {{ row.input_whole > 0 ? ((row.output_kg / row.input_whole) * 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00' }}
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-right border border-gray-300">{{ formatCurrency(row.cost_per_unit) }}</td>
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
  analisis: Array,
  tren: Array,
  warehouses: Array,
  filters: Object
});

const selectedWarehouse = ref(props.filters.warehouse_id || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const loadingReload = ref(false)

watch([selectedWarehouse, from, to], () => {
  router.get(
    route('butcher-processes.analysis-report'),
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

const filteredAnalisis = computed(() => {
  let data = props.analisis;
  if (selectedWarehouse.value) {
    data = data.filter(row => row.warehouse === props.warehouses.find(w => w.id == selectedWarehouse.value)?.name);
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
function formatCurrency(val) {
  if (val == null) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// Grouping logic for merge row
const groupedAnalisis = computed(() => {
  // Group by: tanggal, warehouse, item_whole, input_whole, susut_air, susut_air_unit
  const groups = [];
  let lastKey = null;
  let currentGroup = null;
  filteredAnalisis.value.forEach(row => {
    const key = [row.tanggal, row.warehouse, row.item_whole, row.input_whole, row.susut_air, row.susut_air_unit].join('|');
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

function reloadData() {
  loadingReload.value = true
  window.location.reload()
}
</script> 