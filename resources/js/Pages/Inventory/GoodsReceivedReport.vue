<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Laporan Penerimaan Barang (Goods Received Report)</h1>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <input v-model="search" type="text" placeholder="Cari barang, supplier..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.name">{{ w.name }}</option>
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
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nama Vendor</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Satuan</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Qty Item</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Price</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total</th>
              <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Detail</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!paginatedSummary.length">
              <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data penerimaan barang.</td>
            </tr>
            <template v-for="(row, idx) in paginatedSummary" :key="row.tanggal + row.supplier + row.item + row.unit">
              <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.tanggal ? new Date(row.tanggal).toLocaleDateString('id-ID') : '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.supplier }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.item }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.unit }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayQty(row.qty) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayValue(row.price) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ displayValue(row.total) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <button @click="toggleExpand(idx)" class="text-blue-600 hover:underline font-semibold">{{ expanded[idx] ? 'Tutup' : 'Detail' }}</button>
                </td>
              </tr>
              <tr v-if="expanded[idx]">
                <td colspan="8" class="bg-blue-50 px-6 py-4">
                  <div class="overflow-x-auto">
                    <table class="min-w-full border text-xs md:text-sm">
                      <thead class="bg-blue-100">
                        <tr>
                          <th class="border px-2 py-1">Tanggal</th>
                          <th class="border px-2 py-1">Nama Vendor</th>
                          <th class="border px-2 py-1">No PO</th>
                          <th class="border px-2 py-1">No GR</th>
                          <th class="border px-2 py-1">Item</th>
                          <th class="border px-2 py-1">Satuan</th>
                          <th class="border px-2 py-1">Qty Item</th>
                          <th class="border px-2 py-1">Price</th>
                          <th class="border px-2 py-1">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="d in row.details" :key="d.date + d.item_name + d.po_number + d.gr_number">
                          <td class="border px-2 py-1">{{ d.date ? new Date(d.date).toLocaleDateString('id-ID') : '-' }}</td>
                          <td class="border px-2 py-1">{{ d.supplier_name }}</td>
                          <td class="border px-2 py-1">{{ d.po_number }}</td>
                          <td class="border px-2 py-1">{{ d.gr_number }}</td>
                          <td class="border px-2 py-1">{{ d.item_name }}</td>
                          <td class="border px-2 py-1">{{ d.large_unit_name }}</td>
                          <td class="border px-2 py-1 text-right">{{ displayQty(d.qty_large) }}</td>
                          <td class="border px-2 py-1 text-right">{{ displayValue(d.price_large) }}</td>
                          <td class="border px-2 py-1 text-right">{{ displayValue(d.qty_large * d.price_large) }}</td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr class="font-bold bg-blue-100">
                          <td class="border px-2 py-1 text-right" colspan="7">Total</td>
                          <td class="border px-2 py-1 text-right">{{ displayQty(row.qty) }}</td>
                          <td class="border px-2 py-1"></td>
                          <td class="border px-2 py-1 text-right">{{ displayValue(row.total) }}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div class="flex justify-between items-center mt-4" v-if="summaryRows.length">
        <div class="text-sm text-gray-600">
          Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ summaryRows.length }} data
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
  receives: Array,
  warehouses: Array,
  items: Array
});
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedWarehouse = ref('');
const selectedItem = ref('');
const expanded = ref({});

function convertToLarge(row) {
  // row.unit_pr = unit dari PR, row.large_unit_name, row.medium_unit_name, row.small_unit_name
  // row.unit_id, row.large_unit_id, row.medium_unit_id, row.small_unit_id
  // row.qty_received_kg = qty dalam kg (large), row.value_in = total value
  // row.qty_received = qty asli, row.price = harga asli
  let qty = Number(row.qty_received_kg || 0);
  let price = row.value_in && qty ? row.value_in / qty : 0;
  let unit = row.large_unit_name || '-';
  // Jika unit_id bukan large, konversikan
  if (row.unit_id && row.large_unit_id && row.unit_id != row.large_unit_id) {
    // Jika unit medium
    if (row.unit_id == row.medium_unit_id && row.medium_conversion_qty > 0) {
      qty = Number(row.qty_received || 0) * row.medium_conversion_qty;
      price = row.price / row.medium_conversion_qty;
    } else if (row.unit_id == row.small_unit_id && row.small_conversion_qty > 0 && row.medium_conversion_qty > 0) {
      qty = Number(row.qty_received || 0) * row.small_conversion_qty * row.medium_conversion_qty;
      price = row.price / (row.small_conversion_qty * row.medium_conversion_qty);
    }
  }
  return { qty, price, unit };
}

// Group summary: by tanggal, supplier, item, large_unit_name
const summaryRows = computed(() => {
  const map = {};
  props.receives.forEach(row => {
    const key = [row.date, row.supplier_name, row.item_name, row.large_unit_name].join('|');
    const { qty, price, unit } = convertToLarge(row);
    if (!map[key]) {
      map[key] = {
        tanggal: row.date,
        supplier: row.supplier_name,
        item: row.item_name,
        unit,
        qty: 0,
        price: 0,
        total: 0,
        details: []
      };
    }
    map[key].qty += qty;
    map[key].total += qty * price;
    map[key].details.push({ ...row, qty_large: qty, price_large: price, large_unit_name: unit });
  });
  // Hitung price rata-rata per group
  Object.values(map).forEach(g => {
    g.price = g.qty > 0 ? g.total / g.qty : 0;
  });
  // Filter search
  let arr = Object.values(map);
  if (selectedWarehouse.value) {
    arr = arr.filter(g => g.details.some(row => String(row.warehouse_name) === String(selectedWarehouse.value)));
  }
  if (selectedItem.value) {
    arr = arr.filter(g => g.item === selectedItem.value);
  }
  if (search.value) {
    const s = search.value.toLowerCase();
    arr = arr.filter(g =>
      (g.item && g.item.toLowerCase().includes(s)) ||
      (g.supplier && g.supplier.toLowerCase().includes(s))
    );
  }
  return arr;
});
const totalPages = computed(() => Math.ceil(summaryRows.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, summaryRows.value.length));
const paginatedSummary = computed(() => summaryRows.value.slice(startIndex.value, endIndex.value));

function prevPage() { if (page.value > 1) page.value--; }
function nextPage() { if (page.value < totalPages.value) page.value++; }
watch([perPage, search], () => { page.value = 1; });

function displayQty(val) { if (!val || Number(val) === 0) return '-'; return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }
function displayValue(val) { if (!val || Number(val) === 0) return '-'; return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function toggleExpand(idx) { expanded.value[idx] = !expanded.value[idx]; }
</script> 