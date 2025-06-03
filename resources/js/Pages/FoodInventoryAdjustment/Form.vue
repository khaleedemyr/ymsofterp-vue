<template>
  <AppLayout>
    <Head title="Tambah Stock Adjustment" />
    <div class="py-12">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <form @submit.prevent="validateAndSubmit" @keydown.enter.prevent>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                  <input type="date" v-model="form.date" class="mt-1 block w-full rounded-md border-gray-300" required />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Gudang</label>
                  <select v-model="form.warehouse_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                    <option value="">Pilih Gudang</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                  </select>
                </div>
              </div>
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Tipe Adjustment</label>
                <select v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300" required>
                  <option value="">Pilih Tipe</option>
                  <option value="in">Stock In</option>
                  <option value="out">Stock Out</option>
                </select>
              </div>
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Detail Item</label>
                <div class="overflow-x-auto">
                  <table class="w-full min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                      <tr>
                        <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Note</th>
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
                          </div>
                        </td>
                        <td class="px-3 py-2 min-w-[100px]">
                          <input type="number" min="0.01" step="0.01" v-model="item.qty" class="w-full rounded border-gray-300" required />
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
                        </td>
                        <td class="px-3 py-2 min-w-[120px]">
                          <input type="text" v-model="item.note" class="w-full rounded border-gray-300" />
                        </td>
                        <td class="px-3 py-2">
                          <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700" :disabled="form.items.length === 1"><i class="fa fa-trash"></i></button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <button type="button" @click="addItem" class="mt-3 px-4 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 font-semibold"><i class="fa fa-plus"></i> Tambah Item</button>
              </div>
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Alasan / Catatan</label>
                <input type="text" v-model="form.reason" class="mt-1 block w-full rounded-md border-gray-300" required />
              </div>
              <div class="flex justify-end gap-2 mt-6">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold" :disabled="loading">
                  {{ loading ? 'Menyimpan...' : 'Simpan' }}
                </button>
                <Link :href="route('food-inventory-adjustment.index')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 font-semibold">Batal</Link>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <div v-if="loading" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
      <div class="bg-white px-6 py-4 rounded shadow flex items-center gap-2">
        <span class="loader border-2 border-blue-500 border-t-transparent rounded-full w-6 h-6 animate-spin"></span>
        <span>Menyimpan...</span>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, nextTick } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  warehouses: Array,
  items: Array,
  editData: Object,
});

function newItem() {
  return {
    item_id: '',
    item_name: '',
    qty: '',
    available_units: [],
    selected_unit: '',
    unit: '',
    note: '',
    suggestions: [],
    showDropdown: false,
    loading: false,
    highlightedIndex: -1,
    _rowKey: Date.now() + '-' + Math.random(),
  };
}

const form = useForm({
  date: props.editData?.date || new Date().toISOString().slice(0, 10),
  warehouse_id: props.editData?.warehouse_id || '',
  type: props.editData?.type || '',
  items: props.editData?.items?.length ? JSON.parse(JSON.stringify(props.editData.items)) : [newItem()],
  reason: props.editData?.reason || '',
});

const loading = ref(false);

function addItem() {
  form.items.push(newItem());
}
function removeItem(idx) {
  if (form.items.length === 1) return;
  form.items.splice(idx, 1);
}

async function fetchItemSuggestions(idx, q) {
  if (!q || q.length < 2 || !form.warehouse_id) {
    form.items[idx].suggestions = [];
    form.items[idx].highlightedIndex = -1;
    return;
  }
  form.items[idx].loading = true;
  try {
    const res = await axios.get('/items/search-for-warehouse-transfer', {
      params: { q, warehouse_id: form.warehouse_id }
    });
    let items = Array.isArray(res.data) ? res.data : [];
    form.items[idx].suggestions = items.map(item => ({
      ...item,
      available_units: [item.unit_small, item.unit_medium, item.unit_large].filter(Boolean),
      unit_small: item.unit_small,
      unit_medium: item.unit_medium,
      unit_large: item.unit_large,
      small_unit_id: item.small_unit_id,
      medium_unit_id: item.medium_unit_id,
      large_unit_id: item.large_unit_id,
    }));
    form.items[idx].showDropdown = true;
    form.items[idx].highlightedIndex = 0;
  } catch (error) {
    form.items[idx].suggestions = [];
    form.items[idx].highlightedIndex = -1;
  } finally {
    form.items[idx].loading = false;
  }
}

function selectItem(idx, item) {
  form.items[idx].item_id = item.id;
  form.items[idx].item_name = item.name;
  form.items[idx].unit = item.unit_small || item.unit || '';
  form.items[idx].selected_unit = item.unit_small || item.unit || '';
  form.items[idx].available_units = [item.unit_small, item.unit_medium, item.unit_large].filter(Boolean);
  form.items[idx].suggestions = [];
  form.items[idx].showDropdown = false;
  form.items[idx].highlightedIndex = -1;
}
function onItemInput(idx, e) {
  const value = e.target.value;
  form.items[idx].item_id = '';
  form.items[idx].item_name = value;
  form.items[idx].showDropdown = true;
  fetchItemSuggestions(idx, value);
}
function onItemBlur(idx) {
  setTimeout(() => {
    form.items[idx].showDropdown = false;
  }, 200);
}
function onItemKeydown(idx, e) {
  const item = form.items[idx];
  if (!item.showDropdown || !item.suggestions.length) return;
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    item.highlightedIndex = (item.highlightedIndex + 1) % item.suggestions.length;
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    item.highlightedIndex = (item.highlightedIndex - 1 + item.suggestions.length) % item.suggestions.length;
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (item.highlightedIndex >= 0 && item.suggestions[item.highlightedIndex]) {
      selectItem(idx, item.suggestions[item.highlightedIndex]);
    }
  } else if (e.key === 'Escape') {
    item.showDropdown = false;
  }
}
function onUnitChange(idx, e) {
  form.items[idx].selected_unit = e.target.value;
  form.items[idx].unit = e.target.value;
}
function getDropdownStyle(idx) {
  const inputs = document.querySelectorAll('input[placeholder="Cari nama item..."]');
  const input = inputs[idx];
  if (!input) return {};
  const rect = input.getBoundingClientRect();
  return {
    left: rect.left + 'px',
    top: rect.bottom + 'px',
    width: rect.width + 'px',
    position: 'fixed',
    zIndex: 9999
  };
}

function validateAndSubmit() {
  if (loading.value) return;
  if (!form.date || !form.warehouse_id || !form.type || !form.reason) {
    Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      text: 'Semua field wajib diisi.'
    });
    return;
  }
  if (!form.items.length || form.items.some(item => !item.item_id || !item.qty || !item.selected_unit)) {
    Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      text: 'Setiap item wajib dipilih, qty dan unit diisi.'
    });
    return;
  }
  Swal.fire({
    title: 'Konfirmasi Simpan',
    text: 'Apakah Anda yakin ingin menyimpan stock adjustment ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, simpan!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      loading.value = true;
      form.post(route('food-inventory-adjustment.store'), {
        onFinish: () => loading.value = false
      });
    }
  });
}
</script>

<style scoped>
.loader {
  border-width: 2px;
  border-style: solid;
  border-radius: 9999px;
  width: 1.5rem;
  height: 1.5rem;
  border-top-color: transparent;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style> 