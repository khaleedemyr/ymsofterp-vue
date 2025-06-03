<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-2xl font-bold mb-6">Butcher Report</h1>
      
      <!-- Filters & Load Data Button -->
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input 
          v-model="search" 
          type="text" 
          placeholder="Cari nomor proses, GR, warehouse, item..." 
          class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" 
        />
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select 
            v-model="selectedWarehouse" 
            class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Dari</label>
          <input 
            type="date" 
            v-model="from" 
            class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Sampai</label>
          <input 
            type="date" 
            v-model="to" 
            class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <button @click="reloadData" :disabled="loadingReload" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
          <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
          <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Load Data
        </button>
      </div>

      <!-- Report Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No. Proses</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No. GR</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item Whole</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item PCS</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Whole</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty PCS</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tgl Potong</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tgl Packing</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Batch EST</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Exp Date</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Susut Air</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">MAC PCS</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <template v-for="process in filteredProcesses" :key="process.id">
                <template v-for="(group, groupIdx) in groupItems(process.items)" :key="group.key">
                  <template v-for="(item, itemIdx) in group.items" :key="`${process.id}-${groupIdx}-${itemIdx}`">
                    <tr class="hover:bg-gray-50 transition">
                      <td v-if="groupIdx === 0 && itemIdx === 0"
                          class="px-6 py-4 whitespace-nowrap text-sm"
                          :rowspan="process.items.length">
                        {{ formatDate(process.process_date) }}
                      </td>
                      <td v-if="groupIdx === 0 && itemIdx === 0"
                          class="px-6 py-4 whitespace-nowrap text-sm"
                          :rowspan="process.items.length">
                        {{ process.number }}
                      </td>
                      <td v-if="groupIdx === 0 && itemIdx === 0"
                          class="px-6 py-4 whitespace-nowrap text-sm"
                          :rowspan="process.items.length">
                        {{ process.warehouse_name }}
                      </td>
                      <td v-if="groupIdx === 0 && itemIdx === 0"
                          class="px-6 py-4 whitespace-nowrap text-sm"
                          :rowspan="process.items.length">
                        {{ process.gr_number }}
                      </td>
                      <td v-if="itemIdx === 0"
                          class="px-6 py-4 whitespace-nowrap text-sm"
                          :rowspan="group.items.length">
                        {{ item.whole_item_name }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">{{ item.pcs_item_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatNumber(item.whole_qty) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatNumber(item.pcs_qty) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">{{ item.unit_name }}</td>
                      <td v-if="itemIdx === 0"
                          class="px-6 py-4 whitespace-nowrap text-sm"
                          :rowspan="group.items.length">
                        {{ formatDate(item.slaughter_date) }}
                      </td>
                      <td v-if="itemIdx === 0"
                          class="px-6 py-4 whitespace-nowrap text-sm"
                          :rowspan="group.items.length">
                        {{ formatDate(item.packing_date) }}
                      </td>
                      <td v-if="itemIdx === 0"
                          class="px-6 py-4 whitespace-nowrap text-sm"
                          :rowspan="group.items.length">
                        {{ item.batch_est }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(item.exp_date) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        {{ item.susut_air.qty ? `${formatNumber(item.susut_air.qty)} ${item.susut_air.unit}` : '-' }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatNumber(item.mac_pcs) }}</td>
                    </tr>
                  </template>
                </template>
              </template>
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
  processes: Array,
  warehouses: Array,
  filters: Object
});

const search = ref(props.filters.search || '');
const selectedWarehouse = ref(props.filters.warehouse_id || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const loadingReload = ref(false)

// Watch filters and update URL
watch([search, selectedWarehouse, from, to], () => {
  router.get(
    route('butcher-processes.report'),
    {
      search: search.value,
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

const filteredProcesses = computed(() => {
  let data = props.processes;
  
  if (selectedWarehouse.value) {
    data = data.filter(process => 
      process.warehouse_name === props.warehouses.find(w => w.id == selectedWarehouse.value)?.name
    );
  }

  if (!search.value) return data;

  const s = search.value.toLowerCase();
  return data.filter(process =>
    process.number.toLowerCase().includes(s) ||
    process.gr_number?.toLowerCase().includes(s) ||
    process.warehouse_name.toLowerCase().includes(s) ||
    process.items.some(item => 
      item.whole_item_name.toLowerCase().includes(s) ||
      item.pcs_item_name.toLowerCase().includes(s)
    )
  );
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

function groupItems(items) {
  // Group by whole_item_name, slaughter_date, packing_date, batch_est
  const groups = [];
  let lastKey = null;
  let group = null;
  items.forEach(item => {
    const key = [
      item.whole_item_name,
      item.slaughter_date,
      item.packing_date,
      item.batch_est
    ].join('|');
    if (key !== lastKey) {
      group = { key, items: [] };
      groups.push(group);
      lastKey = key;
    }
    group.items.push(item);
  });
  return groups;
}

function reloadData() {
  loadingReload.value = true
  window.location.reload()
}
</script> 