<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  priceChanges: Array
});

const search = ref('');
const showTable = ref(false);
const filteredData = computed(() => {
  if (!search.value) return props.priceChanges;
  return props.priceChanges.filter(row =>
    row.item_name?.toLowerCase().includes(search.value.toLowerCase())
  );
});

function loadData() {
  showTable.value = true;
}
</script>
<template>
  <AppLayout>
    <div class="p-8">
      <h1 class="text-2xl font-bold mb-4 text-blue-700">Laporan Perubahan Harga PO</h1>
      <div class="mb-4 flex flex-col md:flex-row gap-2 items-start md:items-center">
        <input v-model="search" type="text" placeholder="Cari nama barang..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 w-full md:w-1/3" />
        <button @click="loadData" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold shadow">Load Data</button>
      </div>
      <template v-if="showTable">
        <div class="bg-white rounded-xl shadow-lg p-8 text-gray-600 overflow-x-auto">
          <table class="min-w-full border text-xs md:text-sm">
            <thead class="bg-blue-50">
              <tr>
                <th class="border px-2 py-1">No</th>
                <th class="border px-2 py-1">Nama Barang</th>
                <th class="border px-2 py-1">Satuan Large</th>
                <th class="border px-2 py-1">Supplier Awal</th>
                <th class="border px-2 py-1">Harga Awal</th>
                <th class="border px-2 py-1">Supplier Baru</th>
                <th class="border px-2 py-1">Harga Baru</th>
                <th class="border px-2 py-1">Presentase kenaikan/penurunan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredData.length">
                <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada data perubahan harga PO.</td>
              </tr>
              <tr v-for="(row, i) in filteredData" :key="row.item_name + i">
                <td class="border px-2 py-1 text-center">{{ i + 1 }}</td>
                <td class="border px-2 py-1">{{ row.item_name }}</td>
                <td class="border px-2 py-1">{{ row.large_unit_name || '-' }}</td>
                <td class="border px-2 py-1">{{ row.supplier_awal }}</td>
                <td class="border px-2 py-1 text-right">{{ row.harga_awal.toLocaleString('id-ID') }}</td>
                <td class="border px-2 py-1">{{ row.supplier_baru }}</td>
                <td class="border px-2 py-1 text-right">{{ row.harga_baru.toLocaleString('id-ID') }}</td>
                <td class="border px-2 py-1 text-center">
                  <span :class="row.persen > 0 ? 'text-red-600 font-bold' : row.persen < 0 ? 'text-green-600 font-bold' : ''">
                    {{ row.persen > 0 ? '+' : '' }}{{ row.persen }}%
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </AppLayout>
</template> 