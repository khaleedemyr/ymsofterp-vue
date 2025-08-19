<template>
  <AppLayout>
    <Head title="Tambah Outlet Stock Adjustment" />
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
                  <label class="block text-sm font-medium text-gray-700">Outlet</label>
                  <select v-model="form.outlet_id" class="mt-1 block w-full rounded-md border-gray-300" required :disabled="!props.outlet_selectable">
                    <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                      {{ outlet.name }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Warehouse Outlet</label>
                  <select v-model="form.warehouse_outlet_id" class="mt-1 block w-full rounded-md border-gray-300" required :disabled="!form.outlet_id">
                    <option value="">{{ form.outlet_id ? 'Pilih Warehouse Outlet' : 'Pilih outlet terlebih dahulu' }}</option>
                    <option v-for="wo in warehouse_outlets" :key="wo.id" :value="wo.id">{{ wo.name }}</option>
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
                <div class="flex justify-between items-center mb-2">
                  <label class="block text-sm font-medium text-gray-700">Items</label>
                </div>
                                 <!-- Warning jika outlet belum dipilih -->
                 <div v-if="!form.outlet_id && props.outlet_selectable" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                   <div class="flex items-center">
                     <i class="fa fa-exclamation-triangle text-yellow-600 mr-2"></i>
                     <span class="text-sm text-yellow-800">Pilih outlet terlebih dahulu sebelum menambahkan item</span>
                   </div>
                 </div>
                 <!-- Warning jika warehouse outlet belum dipilih -->
                 <div v-if="form.outlet_id && !form.warehouse_outlet_id" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                   <div class="flex items-center">
                     <i class="fa fa-exclamation-triangle text-yellow-600 mr-2"></i>
                     <span class="text-sm text-yellow-800">Pilih warehouse outlet terlebih dahulu sebelum menambahkan item</span>
                   </div>
                 </div>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                        <th class="px-3 py-2"></th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr v-for="(item, idx) in form.items" :key="idx">
                        <td class="px-3 py-2 min-w-[200px]">
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
                                                             :class="['w-full rounded border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500', (!form.outlet_id && props.outlet_selectable) || (form.outlet_id && !form.warehouse_outlet_id) ? 'bg-gray-100 cursor-not-allowed' : '']"
                               :disabled="(!form.outlet_id && props.outlet_selectable) || (form.outlet_id && !form.warehouse_outlet_id)"
                               required
                               autocomplete="off"
                               :placeholder="!form.outlet_id && props.outlet_selectable ? 'Pilih outlet terlebih dahulu' : (form.outlet_id && !form.warehouse_outlet_id) ? 'Pilih warehouse outlet terlebih dahulu' : 'Cari nama item...'"
                            />
                            <Teleport to="body">
                              <div v-if="item.showDropdown && item.suggestions && item.suggestions.length > 0"
                                :style="getDropdownStyle(idx)"
                                :id="`autocomplete-dropdown-${idx}`"
                                class="fixed z-[99999] bg-white border border-blue-200 rounded shadow max-w-xs w-[260px] max-h-96 overflow-auto mt-1"
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
                                  <div class="text-sm text-gray-600">{{ s.unit_small || s.unit || '' }}</div>
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
                                 <button 
                   type="button" 
                   @click="addItem" 
                   :class="['mt-3 px-4 py-2 rounded font-semibold', (!form.outlet_id && props.outlet_selectable) || (form.outlet_id && !form.warehouse_outlet_id) ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-blue-100 text-blue-700 hover:bg-blue-200']"
                   :disabled="(!form.outlet_id && props.outlet_selectable) || (form.outlet_id && !form.warehouse_outlet_id)"
                 >
                   <i class="fa fa-plus"></i> Tambah Item
                 </button>
              </div>
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Alasan / Catatan</label>
                <input type="text" v-model="form.reason" class="mt-1 block w-full rounded-md border-gray-300" required />
              </div>

              <div class="flex justify-end gap-3">
                <Link
                  :href="route('outlet-food-inventory-adjustment.index')"
                  class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 font-semibold"
                >
                  Cancel
                </Link>
                <button
                  type="submit"
                  class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 font-semibold"
                  :disabled="loading"
                >
                  <span v-if="loading" class="flex items-center gap-2">
                    <div class="loader"></div>
                    Saving...
                  </span>
                  <span v-else>Save</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  outlets: Array,
  items: Array,
  outlet_selectable: Boolean,
  user_outlet_id: [String, Number],
  warehouse_outlets: Array,
})

console.log('Props received:', {
  outlets: props.outlets,
  outlet_selectable: props.outlet_selectable,
  user_outlet_id: props.user_outlet_id,
  warehouse_outlets: props.warehouse_outlets
});

const warehouse_outlets = ref(props.warehouse_outlets || [])

const form = useForm({
  date: new Date().toISOString().split('T')[0],
  outlet_id: props.outlet_selectable ? '' : String(props.user_outlet_id),
  type: '',
  reason: '',
  items: [newItem()],
  warehouse_outlet_id: '',
})

console.log('Form initialized with outlet_id:', form.outlet_id);

const page = usePage();

console.log('Page props auth user:', page.props.auth.user);
console.log('Region ID from page props:', page.props.auth.user.region_id);

function newItem() {
  return {
    item_id: '',
    item_name: '',
    qty: '',
    unit: '',
    selected_unit: '',
    available_units: [],
    note: '',
    suggestions: [],
    showDropdown: false,
    highlightedIndex: -1,
    loading: false
  }
}

const loading = ref(false);

// Watch for outlet_id changes and load warehouse outlets
watch(() => form.outlet_id, async (newOutletId) => {
  if (newOutletId) {
    await loadWarehouseOutlets(newOutletId);
  } else {
    warehouse_outlets.value = [];
    form.warehouse_outlet_id = '';
  }
});

async function loadWarehouseOutlets(outletId) {
  try {
    const response = await axios.get('/api/outlet-food-inventory-adjustment/warehouse-outlets', {
      params: { outlet_id: outletId }
    });
    warehouse_outlets.value = response.data;
    // Reset warehouse selection if current selection is not in new list
    if (form.warehouse_outlet_id && !warehouse_outlets.value.find(wo => wo.id == form.warehouse_outlet_id)) {
      form.warehouse_outlet_id = '';
    }
  } catch (error) {
    console.error('Error loading warehouse outlets:', error);
    warehouse_outlets.value = [];
    form.warehouse_outlet_id = '';
  }
}

function addItem() {
  form.items.push(newItem());
}
function removeItem(idx) {
  if (form.items.length === 1) return;
  form.items.splice(idx, 1);
}

async function fetchItemSuggestions(idx, q) {
  console.log('fetchItemSuggestions called', idx, q);
  console.log('form.outlet_id:', form.outlet_id);
  console.log('form.warehouse_outlet_id:', form.warehouse_outlet_id);
  
  if (!q || q.length < 2) {
    console.log('Query too short, returning');
    form.items[idx].suggestions = [];
    form.items[idx].highlightedIndex = -1;
    return;
  }
  
  if (!form.outlet_id) {
    console.log('No outlet_id selected, returning');
    form.items[idx].suggestions = [];
    form.items[idx].highlightedIndex = -1;
    return;
  }
  
  if (!form.warehouse_outlet_id) {
    console.log('No warehouse_outlet_id selected, returning');
    form.items[idx].suggestions = [];
    form.items[idx].highlightedIndex = -1;
    return;
  }
  
  form.items[idx].loading = true;
  try {
    // Ambil region_id dari outlet yang dipilih
    const outletResponse = await axios.get(`/api/outlets/${form.outlet_id}`);
    const region_id = outletResponse.data.region_id;
    
    console.log('Region ID from outlet:', region_id);
    
    const params = { 
      q, 
      outlet_id: form.outlet_id, 
      region_id: region_id 
    };
    console.log('Making API request with params:', params);
    
    const res = await axios.get('/items/search-for-outlet-stock-adjustment', { params });
    console.log('API result:', res.data);
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
    console.log('Suggestions:', form.items[idx].suggestions);
    console.log('showDropdown:', form.items[idx].showDropdown);
  } catch (error) {
    console.error('API error:', error);
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
  form.items[idx].unit = e.target.value;
}

function getDropdownStyle(idx) {
  const input = document.getElementById(`item-input-${idx}`);
  if (!input) return {};
  const rect = input.getBoundingClientRect();
  return {
    position: 'fixed',
    left: `${rect.left}px`,
    top: `${rect.bottom}px`,
    width: `${rect.width}px`,
    zIndex: 99999
  };
}

function validateAndSubmit() {
  if (loading.value) return;
  if (!form.date || !form.outlet_id || !form.warehouse_outlet_id || !form.type || !form.reason) {
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
    text: 'Apakah Anda yakin ingin menyimpan outlet stock adjustment ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, simpan!',
    cancelButtonText: 'Batal',
    showLoaderOnConfirm: true,
    preConfirm: () => {
      loading.value = true;
      return form.post(route('outlet-food-inventory-adjustment.store'), {
        onSuccess: () => {
          Swal.fire('Berhasil', 'Outlet stock adjustment berhasil disimpan!', 'success');
        },
        onError: (err) => {
          Swal.fire('Gagal', err?.error || 'Gagal menyimpan data', 'error');
        },
        onFinish: () => {
          loading.value = false;
        }
      });
    },
    allowOutsideClick: () => !Swal.isLoading()
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