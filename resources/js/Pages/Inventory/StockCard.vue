<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Kartu Stok (Stock Card)</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="search" type="text" placeholder="Cari barang, warehouse, referensi, keterangan..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Barang</label>
          <select v-model="selectedItem" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Barang</option>
            <option v-for="i in items" :key="i.id" :value="i.name">{{ i.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Barang</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Masuk (Qty)</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Keluar (Qty)</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Saldo (Qty)</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Referensi</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Keterangan</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!filteredCards.length">
              <td colspan="11" class="text-center py-10 text-gray-400">Tidak ada data kartu stok.</td>
            </tr>
            <tr v-for="row in paginatedCards" :key="row.id" class="hover:bg-gray-50 transition">
              <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.date ? new Date(row.date).toLocaleDateString('id-ID') : '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.item_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                <template v-if="row.display_large > 0">
                  {{ Number(row.display_large).toLocaleString('id-ID') }} {{ row.large_unit_name }}
                </template>
                <template v-else>-</template>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                <template v-if="row.display_medium > 0 && row.medium_unit_name !== row.large_unit_name">
                  {{ Number(row.display_medium).toLocaleString('id-ID') }} {{ row.medium_unit_name }}
                </template>
                <template v-else>-</template>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                <template v-if="row.display_small > 0 && row.small_unit_name !== row.medium_unit_name && row.small_unit_name !== row.large_unit_name">
                  {{ Number(row.display_small).toLocaleString('id-ID') }} {{ row.small_unit_name }}
                </template>
                <template v-else>-</template>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <template v-if="row.reference_type === 'good_receive' && row.reference_number">
                  Good Receive {{ row.reference_number }}
                </template>
                <template v-else>
                  {{ row.reference_type ? row.reference_type + (row.reference_id ? ' #' + row.reference_id : '') : '-' }}
                </template>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.description || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4" v-if="filteredCards.length">
        <div class="text-sm text-gray-600">
          Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ filteredCards.length }} data
        </div>
        <div class="flex gap-1">
          <button @click="prevPage" :disabled="page === 1" class="px-3 py-1 rounded border text-sm" :class="page === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&lt;</button>
          <span class="px-2">Halaman {{ page }} / {{ totalPages }}</span>
          <button @click="nextPage" :disabled="page === totalPages" class="px-3 py-1 rounded border text-sm" :class="page === totalPages ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&gt;</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
const props = defineProps({
  cards: Array,
  warehouses: Array,
  items: Array
});
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedWarehouse = ref('');
const selectedItem = ref('');

const filteredCards = computed(() => {
  let data = props.cards;
  if (selectedWarehouse.value) {
    data = data.filter(row => String(row.warehouse_name) === String(props.warehouses.find(w => w.id == selectedWarehouse.value)?.name));
  }
  if (selectedItem.value) {
    data = data.filter(row => row.item_name === selectedItem.value);
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.warehouse_name && row.warehouse_name.toLowerCase().includes(s)) ||
    (row.reference_type && row.reference_type.toLowerCase().includes(s)) ||
    (row.description && row.description.toLowerCase().includes(s))
  );
});

const totalPages = computed(() => Math.ceil(filteredCards.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, filteredCards.value.length));
const paginatedCards = computed(() => filteredCards.value.slice(startIndex.value, endIndex.value));

function prevPage() {
  if (page.value > 1) page.value--;
}
function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
watch([perPage, search], () => { page.value = 1; });
</script>