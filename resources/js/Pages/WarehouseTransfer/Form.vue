<script setup>
import { ref, reactive, watch, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  warehouses: Array,
  items: Array,
  editData: Object,
});

const isEdit = computed(() => !!props.editData);

const form = useForm({
  transfer_date: props.editData?.transfer_date || '',
  warehouse_from_id: props.editData?.warehouse_from_id || '',
  warehouse_to_id: props.editData?.warehouse_to_id || '',
  notes: props.editData?.notes || '',
  items: props.editData?.items?.length ? JSON.parse(JSON.stringify(props.editData.items)) : [
    { item_id: '', item_name: '', qty: '', unit: '', note: '', suggestions: [], showDropdown: false, loading: false, highlightedIndex: -1, available_units: [], selected_unit: '', _rowKey: Date.now() + '-' + Math.random() }
  ],
});

const itemInputRefs = ref([]);
watch(() => form.items.length, (newLen) => {
  if (!Array.isArray(itemInputRefs.value)) itemInputRefs.value = [];
  itemInputRefs.value.length = newLen;
});

function addItem() {
  form.items.push({ item_id: '', item_name: '', qty: '', unit: '', note: '', suggestions: [], showDropdown: false, loading: false, highlightedIndex: -1, available_units: [], selected_unit: '', _rowKey: Date.now() + '-' + Math.random() });
  nextTick(() => {
    setTimeout(() => {
      const lastIdx = form.items.length - 1;
      if (itemInputRefs.value[lastIdx]) {
        itemInputRefs.value[lastIdx].focus();
      }
    }, 0);
  });
}
function removeItem(idx) {
  if (form.items.length === 1) return;
  form.items.splice(idx, 1);
}
function onSubmit() {
  // Log debug qty dan stok
  for (const [idx, item] of form.items.entries()) {
    console.log('DEBUG VALIDASI QTY', { item, stock: item.stock, qty: item.qty, unit: item.selected_unit || item.unit });
    const qty = Number(item.qty || 0);
    let stock = 0;
    const unit = item.selected_unit || item.unit;
    if (unit === item.stock?.unit_small) {
      stock = Number(item.stock?.qty_small || 0);
    } else if (unit === item.stock?.unit_medium) {
      stock = Number(item.stock?.qty_medium || 0);
    } else if (unit === item.stock?.unit_large) {
      stock = Number(item.stock?.qty_large || 0);
    } else {
      stock = 0;
    }
    if (qty > stock) {
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: `Qty item \"${item.item_name}\" melebihi stok di gudang asal untuk unit ${unit} (${stock})`,
      });
      return;
    }
  }
  Swal.fire({
    title: isEdit.value ? 'Menyimpan Perubahan...' : 'Menyimpan Data...',
    text: 'Mohon tunggu sebentar',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => { Swal.showLoading(); }
  });
  if (isEdit.value) {
    form.put(`/warehouse-transfer/${props.editData.id}`, {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Data berhasil diupdate', timer: 1500, showConfirmButton: false }).then(() => { router.visit('/warehouse-transfer'); });
      },
      onError: () => {
        Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan saat update data' });
      }
    });
  } else {
    form.post('/warehouse-transfer', {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Data berhasil disimpan', timer: 1500, showConfirmButton: false }).then(() => { router.visit('/warehouse-transfer'); });
      },
      onError: () => {
        Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan saat menyimpan data' });
      }
    });
  }
}
function onDelete() {
  Swal.fire({
    title: 'Hapus Data?',
    text: 'Data transfer dan mutasi stok akan dihapus. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/warehouse-transfer/${props.editData.id}`, {
        onSuccess: () => {
          Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Data berhasil dihapus', timer: 1500, showConfirmButton: false }).then(() => { router.visit('/warehouse-transfer'); });
        },
        onError: () => {
          Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan saat menghapus data' });
        }
      });
    }
  });
}
function goBack() {
  router.visit('/warehouse-transfer');
}

// Autocomplete logic for item
async function fetchItemSuggestions(idx, q) {
  console.log('fetchItemSuggestions called', { idx, q, warehouse: form.warehouse_from_id });
  if (!q || q.length < 2 || !form.warehouse_from_id) {
    form.items[idx].suggestions = [];
    form.items[idx].highlightedIndex = -1;
    return;
  }
  form.items[idx].loading = true;
  try {
    const res = await axios.get('/items/search-for-warehouse-transfer', {
      params: { q, warehouse_id: form.warehouse_from_id }
    });
    console.log('API response:', res.data);
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
  } catch (error) {
    console.error('Error fetching suggestions:', error);
    form.items[idx].suggestions = [];
    form.items[idx].highlightedIndex = -1;
  } finally {
    form.items[idx].loading = false;
  }
}

// Tambahkan fungsi fetchStock untuk mengambil stok dari gudang asal
async function fetchStock(idx) {
  const item = form.items[idx];
  if (!item.item_id || !form.warehouse_from_id) return;
  try {
    const res = await axios.get('/api/inventory/stock', {
      params: { item_id: item.item_id, warehouse_id: form.warehouse_from_id }
    });
    // Simpan stok ke item
    item.stock = res.data;
    // Konversi stok jika qty_small, medium_conversion_qty, small_conversion_qty tersedia
    if (item.stock && item.stock.qty_small != null && item.stock.medium_conversion_qty && item.stock.small_conversion_qty) {
      const small = Number(item.stock.qty_small || 0);
      const mediumConv = Number(item.stock.small_conversion_qty || 1);
      const largeConv = Number(item.stock.medium_conversion_qty || 1);
      const qty_large = Math.floor(small / (mediumConv * largeConv));
      const sisa_setelah_large = small % (mediumConv * largeConv);
      const qty_medium = Math.floor(sisa_setelah_large / mediumConv);
      const qty_small = sisa_setelah_large % mediumConv;
      item.stock_display = {
        qty_large,
        qty_medium,
        qty_small
      };
    } else {
      item.stock_display = null;
    }
  } catch (e) {
    item.stock = { qty_small: 0, qty_medium: 0, qty_large: 0 };
    item.stock_display = null;
  }
}

function formatNumber(val) {
  if (val == null) return 0;
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
function formatStockDisplay(item) {
  if (!item.stock) return 'Stok: 0';
  const small = Number(item.stock.qty_small || 0);
  const medium = Number(item.stock.qty_medium || 0);
  const large = Number(item.stock.qty_large || 0);
  return `Stok: ${formatNumber(small)} ${item.stock.unit_small || ''} | ${formatNumber(medium)} ${item.stock.unit_medium || ''} | ${formatNumber(large)} ${item.stock.unit_large || ''}`;
}

// Panggil fetchStock setiap kali item dipilih atau warehouse_from_id berubah
watch(() => form.warehouse_from_id, (newVal) => {
  form.items.forEach((item, idx) => {
    if (item.item_id && newVal) fetchStock(idx);
  });
});

function selectItem(idx, item) {
  form.items[idx].item_id = item.id;
  form.items[idx].item_name = item.name;
  form.items[idx].unit = item.unit_small || item.unit || '';
  form.items[idx].selected_unit = item.unit_small || item.unit || '';
  form.items[idx].available_units = [item.unit_small, item.unit_medium, item.unit_large].filter(Boolean);
  form.items[idx].suggestions = [];
  form.items[idx].showDropdown = false;
  form.items[idx].highlightedIndex = -1;
  // Fetch stok setelah pilih item
  if (form.warehouse_from_id) {
    nextTick(() => {
      fetchStock(idx);
    });
  }
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
  // Cari input yang sesuai
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

// F1 focus ke input item paling bawah
function handleF1Focus(e) {
  if (e.key === 'F1') {
    e.preventDefault();
    const lastIdx = form.items.length - 1;
    const inputId = `item-input-${lastIdx}`;
    const input = document.getElementById(inputId);
    if (input) {
      input.focus();
      input.select();
      // Tambahkan highlight visual
      input.style.outline = '2px solid #2563eb';
      input.style.boxShadow = '0 0 0 2px rgba(37, 99, 235, 0.2)';
      setTimeout(() => {
        input.style.outline = '';
        input.style.boxShadow = '';
      }, 1000);
    }
  }
}

// Validasi sebelum input item
const canInputItem = computed(() => {
  return form.warehouse_from_id && form.warehouse_to_id && form.warehouse_from_id !== form.warehouse_to_id;
});

onMounted(() => {
  window.addEventListener('keydown', handleF1Focus);
  // Set tanggal otomatis ke hari ini jika belum diset
  if (!form.transfer_date) {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    form.transfer_date = `${year}-${month}-${day}`;
  }
});
onUnmounted(() => {
  window.removeEventListener('keydown', handleF1Focus);
});
</script>
<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-right-left text-blue-500"></i>
          <span v-if="isEdit">Edit Pindah Gudang</span>
          <span v-else>Tambah Pindah Gudang</span>
        </h1>
      </div>
      <form @submit.prevent="onSubmit" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.transfer_date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
            <div v-if="form.errors.transfer_date" class="text-xs text-red-500 mt-1">{{ form.errors.transfer_date }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Gudang Asal</label>
            <select v-model="form.warehouse_from_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Gudang Asal</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
            <div v-if="form.errors.warehouse_from_id" class="text-xs text-red-500 mt-1">{{ form.errors.warehouse_from_id }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Gudang Tujuan</label>
            <select v-model="form.warehouse_to_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Gudang Tujuan</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
            <div v-if="form.errors.warehouse_to_id" class="text-xs text-red-500 mt-1">{{ form.errors.warehouse_to_id }}</div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Keterangan</label>
          <textarea v-model="form.notes" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
          <div v-if="form.errors.notes" class="text-xs text-red-500 mt-1">{{ form.errors.notes }}</div>
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
                      <div v-if="!form.warehouse_from_id" class="text-xs text-yellow-600 mt-1">
                        Pilih warehouse terlebih dahulu untuk mencari item.
                      </div>
                      <div v-if="item.stock" class="text-xs text-gray-500 mt-1">
                        Stok: {{ formatStockDisplay(item) }}
                      </div>
                    </div>
                    <div v-if="form.errors[`items.${idx}.item_id`]" class="text-xs text-red-500 mt-1">
                      {{ form.errors[`items.${idx}.item_id`] }}
                    </div>
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    <input type="number" min="0.01" step="0.01" v-model="item.qty" class="w-full rounded border-gray-300" required />
                    <div v-if="form.errors[`items.${idx}.qty`]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${idx}.qty`] }}</div>
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
                    <div v-if="form.errors[`items.${idx}.unit`]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${idx}.unit`] }}</div>
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
          <button type="button" @click="addItem" class="mt-2 px-3 py-1 rounded bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200" :disabled="!canInputItem"><i class="fa fa-plus"></i> Tambah Item</button>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button v-if="isEdit" type="button" @click="onDelete" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Hapus</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template> 