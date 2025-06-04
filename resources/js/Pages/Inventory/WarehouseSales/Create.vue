<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fas fa-exchange-alt text-blue-500"></i>
          Buat Penjualan Antar Gudang
        </h1>
      </div>
      <form @submit.prevent="onSubmit" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Gudang Asal</label>
            <select v-model="form.source_warehouse_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Gudang Asal</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Gudang Tujuan</label>
            <select v-model="form.target_warehouse_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Gudang Tujuan</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id" :disabled="w.id === form.source_warehouse_id">{{ w.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Keterangan</label>
            <textarea v-model="form.note" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Detail Item</label>
          <div v-if="!canInputItem" class="text-red-600 text-sm mb-2">
            Pilih gudang asal dan tujuan terlebih dahulu, dan pastikan tidak sama.
          </div>
          <div class="overflow-x-auto" :class="{'pointer-events-none opacity-60': !canInputItem}">
            <table class="w-full min-w-full divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Harga</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in form.items" :key="item._rowKey || idx">
                  <td class="px-3 py-2 min-w-[260px]">
                    <div class="relative">
                      <input
                        :id="`item-input-${idx}`"
                        type="text"
                        v-model="item.item_name"
                        @input="onItemInput(idx, $event)"
                        @focus="onItemInput(idx, $event)"
                        @blur="onItemBlur(idx)"
                        @keydown.down="onItemKeydown(idx, $event)"
                        @keydown.up="onItemKeydown(idx, $event)"
                        @keydown.enter="onItemKeydown(idx, $event)"
                        @keydown.esc="onItemKeydown(idx, $event)"
                        class="w-full rounded border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                        required 
                        autocomplete="off" 
                        placeholder="Cari nama item..." 
                      />
                      <Teleport to="body">
                        <div v-if="item.showDropdown && item.suggestions && item.suggestions.length > 0"
                          :style="getDropdownStyle(idx)"
                          :id="`autocomplete-dropdown-${idx}`"
                          class="fixed z-[9999] bg-white border border-blue-200 rounded shadow max-w-xs w-[260px] max-h-96 overflow-auto mt-1"
                        >
                          <div v-for="(s, sidx) in item.suggestions" :key="s.id"
                            :id="`autocomplete-item-${idx}-${sidx}`"
                            @mousedown.prevent="selectItem(idx, s)"
                            :class="['px-3 py-2 flex justify-between items-center cursor-pointer', item.highlightedIndex === sidx ? 'bg-blue-100' : 'hover:bg-blue-50']"
                          >
                            <div>
                              <div class="font-medium">{{ s.name }}</div>
                              <div class="text-xs text-gray-500">{{ s.sku }}</div>
                            </div>
                            <div class="text-sm text-gray-600">{{ s.unit }}</div>
                          </div>
                        </div>
                      </Teleport>
                      <div v-if="item.loading" class="absolute right-2 top-2">
                        <i class="fa fa-spinner fa-spin text-blue-400"></i>
                      </div>
                      <div v-if="!form.source_warehouse_id" class="text-xs text-yellow-600 mt-1">
                        Pilih warehouse terlebih dahulu untuk mencari item.
                      </div>
                      <div v-if="item.stock" class="text-xs text-gray-500 mt-1">
                        Stok: {{ formatStockDisplay(item) }}
                      </div>
                    </div>
                    <div v-if="form.errors[`items.${idx}.item_id` ]" class="text-xs text-red-500 mt-1">
                      {{ form.errors[`items.${idx}.item_id`] }}
                    </div>
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    <input type="number" min="0.01" step="0.01" v-model="item.qty" class="w-full rounded border-gray-300" required />
                    <div v-if="form.errors[`items.${idx}.qty` ]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${idx}.qty`] }}</div>
                    <div v-if="item.stock && item.qty > item.stock.qty_small" class="text-xs text-red-500 mt-1">Qty melebihi stok!</div>
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    <template v-if="item.available_units && item.available_units.length">
                      <select v-model="item.selected_unit" @change="onUnitChange(idx, $event)" class="w-full rounded border-gray-300">
                        <option v-for="u in item.available_units" :key="u" :value="u">{{ u }}</option>
                      </select>
                    </template>
                    <template v-else>
                      <input type="text" v-model="item.unit" class="w-full rounded border-gray-300" required />
                    </template>
                    <div v-if="form.errors[`items.${idx}.unit` ]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${idx}.unit`] }}</div>
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    <span>{{ formatNumber(item.price) }}</span>
                  </td>
                  <td class="px-3 py-2 min-w-[120px]">
                    {{ formatNumber(item.total) }}
                  </td>
                  <td class="px-3 py-2">
                    <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700" :disabled="form.items.length === 1"><i class="fa fa-trash"></i></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <button type="button" @click="addItem" class="mt-2 px-3 py-1 rounded bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200" :disabled="!canInputItem"><i class="fa fa-plus"></i> Tambah Item</button>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
const props = defineProps({ warehouses: Array });
const loading = ref(false);
const form = useForm({
  source_warehouse_id: '',
  target_warehouse_id: '',
  date: new Date().toISOString().split('T')[0],
  items: [getEmptyItem()],
  note: ''
});
function getEmptyItem() {
  return {
    item_id: '',
    item_name: '',
    qty: '',
    selected_unit: '',
    available_units: [],
    price: 0,
    total: 0,
    stock: null,
    note: '',
    suggestions: [],
    showDropdown: false,
    loading: false,
    highlightedIndex: -1,
    _rowKey: Date.now() + '-' + Math.random()
  };
}
function addItem() { form.items.push(getEmptyItem()); }
function removeItem(index) { if (form.items.length === 1) return; form.items.splice(index, 1); }
function goBack() { router.visit('/warehouse-sales'); }
function onItemInput(idx, e) {
  const value = e.target.value;
  const item = form.items[idx];
  item.item_id = '';
  item.item_name = value;
  item.showDropdown = true;
  fetchItemSuggestions(idx, value);
}
function onItemBlur(idx) { setTimeout(() => { form.items[idx].showDropdown = false; }, 200); }
function onItemKeydown(idx, e) {
  const item = form.items[idx];
  if (!item.showDropdown || !item.suggestions.length) return;
  if (e.key === 'ArrowDown') { e.preventDefault(); item.highlightedIndex = (item.highlightedIndex + 1) % item.suggestions.length; }
  else if (e.key === 'ArrowUp') { e.preventDefault(); item.highlightedIndex = (item.highlightedIndex - 1 + item.suggestions.length) % item.suggestions.length; }
  else if (e.key === 'Enter') { e.preventDefault(); if (item.highlightedIndex >= 0 && item.suggestions[item.highlightedIndex]) { selectItem(idx, item.suggestions[item.highlightedIndex]); } }
  else if (e.key === 'Escape') { item.showDropdown = false; }
}
async function fetchItemSuggestions(idx, q) {
  if (!q || q.length < 2 || !form.source_warehouse_id) { form.items[idx].suggestions = []; form.items[idx].highlightedIndex = -1; return; }
  form.items[idx].loading = true;
  try {
    const res = await axios.get('/items/search-for-warehouse-transfer', { params: { q, warehouse_id: form.source_warehouse_id } });
    let items = Array.isArray(res.data) ? res.data : [];
    form.items[idx].suggestions = items.map(item => ({ ...item, available_units: [item.unit_small, item.unit_medium, item.unit_large].filter(Boolean), unit_small: item.unit_small, unit_medium: item.unit_medium, unit_large: item.unit_large, small_unit_id: item.small_unit_id, medium_unit_id: item.medium_unit_id, large_unit_id: item.large_unit_id, }));
    form.items[idx].showDropdown = true;
    form.items[idx].highlightedIndex = 0;
  } catch (error) { form.items[idx].suggestions = []; form.items[idx].highlightedIndex = -1; } finally { form.items[idx].loading = false; }
}
function selectItem(idx, item) {
  form.items[idx].item_id = item.id;
  form.items[idx].item_name = item.name;
  form.items[idx].available_units = [item.unit_small, item.unit_medium, item.unit_large].filter(Boolean);
  form.items[idx].selected_unit = '';
  form.items[idx].suggestions = [];
  form.items[idx].showDropdown = false;
  form.items[idx].highlightedIndex = -1;
  fetchStock(idx);
}
async function fetchStock(idx) {
  const item = form.items[idx];
  if (!item.item_id || !form.source_warehouse_id) return;
  try {
    const res = await axios.get('/api/inventory/stock', { params: { item_id: item.item_id, warehouse_id: form.source_warehouse_id } });
    item.stock = res.data;
  } catch (e) { item.stock = { qty_small: 0, qty_medium: 0, qty_large: 0 }; }
}
function onUnitChange(idx, e) {
  form.items[idx].selected_unit = e.target.value;
  fetchPrice(idx);
}
async function fetchPrice(idx) {
  const item = form.items[idx];
  if (!item.item_id || !item.selected_unit || !form.source_warehouse_id) return;
  try {
    const res = await axios.get('/api/warehouse-sales/item-price', { params: { item_id: item.item_id, warehouse_id: form.source_warehouse_id, unit_type: item.selected_unit } });
    item.price = res.data.price || 0;
    updateTotal(idx);
  } catch (e) { item.price = 0; updateTotal(idx); }
}
function updateTotal(idx) {
  const item = form.items[idx];
  item.total = (Number(item.qty) || 0) * (Number(item.price) || 0);
}
function formatNumber(val) { if (val == null) return 0; if (Number(val) % 1 === 0) return Number(val); return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }
const canInputItem = computed(() => { return form.source_warehouse_id && form.target_warehouse_id && form.source_warehouse_id !== form.target_warehouse_id; });
function formatStockDisplay(item) { if (!item.stock) return 'Stok: 0'; const small = Number(item.stock.qty_small || 0); const medium = Number(item.stock.qty_medium || 0); const large = Number(item.stock.qty_large || 0); return `Stok: ${formatNumber(small)} ${item.stock.unit_small || ''} | ${formatNumber(medium)} ${item.stock.unit_medium || ''} | ${formatNumber(large)} ${item.stock.unit_large || ''}`; }
async function onSubmit() {
  // Validasi sebelum submit
  if (!form.source_warehouse_id || !form.target_warehouse_id || form.source_warehouse_id === form.target_warehouse_id) {
    alert('Gudang asal dan tujuan harus diisi dan tidak boleh sama!');
    return;
  }
  for (const [idx, item] of form.items.entries()) {
    if (!item.item_id) { alert(`Item ke-${idx + 1} belum dipilih!`); return; }
    if (!item.selected_unit) { alert(`Unit item ke-${idx + 1} belum dipilih!`); return; }
    if (!item.qty || item.qty <= 0) { alert(`Qty item ke-${idx + 1} harus diisi!`); return; }
    if (item.stock && item.qty > item.stock.qty_small) { alert(`Qty item ke-${idx + 1} melebihi stok!`); return; }
  }
  const result = await Swal.fire({
    title: 'Simpan Data?',
    text: 'Apakah Anda yakin ingin menyimpan Penjualan Antar Gudang ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
    allowOutsideClick: false
  });
  if (result.isConfirmed) {
    Swal.fire({
      title: 'Menyimpan...',
      text: 'Mohon tunggu sebentar',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => { Swal.showLoading(); }
    });
    loading.value = true;
    form.post(route('warehouse-sales.store'), {
      onFinish: () => {
        loading.value = false;
        Swal.close();
      }
    });
  }
}
function getDropdownStyle(idx) {
  const inputs = document.querySelectorAll('input[placeholder="Cari nama item..."]');
  const input = inputs[idx];
  if (!input) return {}; const rect = input.getBoundingClientRect(); return { left: rect.left + 'px', top: rect.bottom + 'px', width: rect.width + 'px', position: 'fixed', zIndex: 9999 };
}
onMounted(() => {
  if (!form.date) {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    form.date = `${year}-${month}-${day}`;
  }
});
</script> 