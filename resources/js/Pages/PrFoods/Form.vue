<script setup>
import { ref, reactive, watch, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  prFood: Object,
  warehouses: Array,
  items: Array,
});

const isEdit = computed(() => !!props.prFood);

const form = useForm({
  tanggal: props.prFood?.tanggal ? props.prFood.tanggal.substring(0, 10) : '',
  warehouse_id: props.prFood?.warehouse_id || '',
  description: props.prFood?.description || '',
  items: props.prFood?.items?.map(i => ({
    item_id: i.item_id,
    item_name: i.item_name || i.item?.name || '',
    qty: i.qty,
    unit: i.unit,
    note: i.note || '',
    arrival_date: i.arrival_date || '',
    suggestions: [],
    showDropdown: false,
    loading: false,
    highlightedIndex: -1,
    available_units: i.available_units || [],
    selected_unit: i.unit || '',
    _rowKey: Date.now() + '-' + Math.random(),
  })) || [
    { item_id: '', item_name: '', qty: '', unit: '', note: '', arrival_date: '', suggestions: [], showDropdown: false, loading: false, highlightedIndex: -1, available_units: [], selected_unit: '', _rowKey: Date.now() + '-' + Math.random() }
  ],
});

const itemInputRefs = ref([]);

// Reset refs jika jumlah item berubah
watch(() => form.items.length, (newLen) => {
  if (!Array.isArray(itemInputRefs.value)) itemInputRefs.value = [];
  itemInputRefs.value.length = newLen;
});

// Tambahkan watch untuk warehouse_id dan item_id agar fetch stok otomatis
watch(() => form.warehouse_id, (newVal) => {
  form.items.forEach((item, idx) => {
    if (item.item_id && newVal) fetchStock(idx);
  });
});

function addItem() {
  form.items.push({ item_id: '', item_name: '', qty: '', unit: '', note: '', arrival_date: '', suggestions: [], showDropdown: false, loading: false, highlightedIndex: -1, available_units: [], selected_unit: '', _rowKey: Date.now() + '-' + Math.random() });
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
  // Tampilkan loading
  Swal.fire({
    title: 'Menyimpan Data...',
    text: 'Mohon tunggu sebentar',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  if (isEdit.value) {
    form.put(route('pr-foods.update', props.prFood.id), {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Data berhasil disimpan',
          timer: 1500,
          showConfirmButton: false
        }).then(() => {
          router.visit('/pr-foods');
        });
      },
      onError: (errors) => {
        Swal.fire({
          icon: 'error',
          title: 'Gagal!',
          text: 'Terjadi kesalahan saat menyimpan data'
        });
      }
    });
  } else {
    form.post(route('pr-foods.store'), {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Data berhasil disimpan',
          timer: 1500,
          showConfirmButton: false
        }).then(() => {
          router.visit('/pr-foods');
        });
      },
      onError: (errors) => {
        Swal.fire({
          icon: 'error',
          title: 'Gagal!',
          text: 'Terjadi kesalahan saat menyimpan data'
        });
      }
    });
  }
}
function goBack() {
  router.visit('/pr-foods');
}

// Autocomplete logic for item
async function fetchItemSuggestions(idx, q) {
  if (!q || q.length < 2) {
    form.items[idx].suggestions = [];
    form.items[idx].highlightedIndex = -1;
    return;
  }
  form.items[idx].loading = true;
  try {
    const res = await axios.get('/api/items/search-for-pr?q=' + encodeURIComponent(q));
    // Tambahkan available_units (small, medium, large) ke setiap suggestion
    form.items[idx].suggestions = res.data.map(item => ({
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
    console.error('Error fetching suggestions:', error);
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
  // Simpan konversi ke item
  form.items[idx].medium_conversion_qty = item.medium_conversion_qty;
  form.items[idx].small_conversion_qty = item.small_conversion_qty;
  // Fetch stok setelah pilih item
  if (form.warehouse_id) {
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
    scrollDropdownToHighlighted(idx);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    item.highlightedIndex = (item.highlightedIndex - 1 + item.suggestions.length) % item.suggestions.length;
    scrollDropdownToHighlighted(idx);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (item.highlightedIndex >= 0 && item.suggestions[item.highlightedIndex]) {
      selectItem(idx, item.suggestions[item.highlightedIndex]);
    }
  } else if (e.key === 'Escape') {
    item.showDropdown = false;
  }
}
function scrollDropdownToHighlighted(idx) {
  nextTick(() => {
    const dropdown = document.getElementById(`autocomplete-dropdown-${idx}`);
    const highlighted = document.getElementById(`autocomplete-item-${idx}-${form.items[idx].highlightedIndex}`);
    if (dropdown && highlighted) {
      const dropdownRect = dropdown.getBoundingClientRect();
      const highlightedRect = highlighted.getBoundingClientRect();
      if (highlightedRect.top < dropdownRect.top) {
        dropdown.scrollTop -= (dropdownRect.top - highlightedRect.top);
      } else if (highlightedRect.bottom > dropdownRect.bottom) {
        dropdown.scrollTop += (highlightedRect.bottom - dropdownRect.bottom);
      }
    }
  });
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

async function fetchStock(idx) {
  const item = form.items[idx];
  if (!item.item_id || !form.warehouse_id) return;
  try {
    const res = await axios.get('/api/inventory/stock', {
      params: { item_id: item.item_id, warehouse_id: form.warehouse_id }
    });
    console.log('Stock response:', res.data); // Debug log
    // Simpan stok ke item
    item.stock = res.data;
    // Konversi stok jika qty_small, medium_conversion_qty, small_conversion_qty tersedia
    if (item.stock && item.medium_conversion_qty && item.small_conversion_qty) {
      const small = Number(item.stock.qty_small || 0);
      const mediumConv = Number(item.small_conversion_qty || 1);
      const largeConv = Number(item.medium_conversion_qty || 1);
      console.log('Conversion values:', { small, mediumConv, largeConv }); // Debug log
      const qty_large = Math.floor(small / (mediumConv * largeConv));
      const sisa_setelah_large = small % (mediumConv * largeConv);
      const qty_medium = Math.floor(sisa_setelah_large / mediumConv);
      const qty_small = sisa_setelah_large % mediumConv;
      item.stock_display = {
        qty_large,
        qty_medium,
        qty_small
      };
      console.log('Converted stock:', item.stock_display); // Debug log
    } else {
      item.stock_display = null;
    }
  } catch (e) {
    console.error('Error fetching stock:', e); // Debug log
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
  if (item.stock) {
    return `Stok: ${formatNumber(item.stock.qty_small)} ${item.stock.unit_small || ''} | ${formatNumber(item.stock.qty_medium)} ${item.stock.unit_medium || ''} | ${formatNumber(item.stock.qty_large)} ${item.stock.unit_large || ''}`;
  }
  return 'Stok: 0';
}

const spsModal = ref(false);
const spsItem = ref({});
const spsLoading = ref(false);

async function openSpsModal(item) {
  if (!item.item_id) return;
  spsLoading.value = true;
  spsModal.value = true;
  try {
    const res = await axios.get(`/api/items/${item.item_id}`);
    spsItem.value = res.data.item;
  } catch (e) {
    spsItem.value = { error: 'Gagal mengambil data item' };
  } finally {
    spsLoading.value = false;
  }
}
function closeSpsModal() {
  spsModal.value = false;
  spsItem.value = {};
}

onMounted(() => {
  window.addEventListener('keydown', handleF1Focus);
  
  // Set tanggal otomatis ke hari ini jika bukan mode edit dan tanggal belum diset
  if (!isEdit.value && !form.tanggal) {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    form.tanggal = `${year}-${month}-${day}`;
  }
});
onUnmounted(() => {
  window.removeEventListener('keydown', handleF1Focus);
});

function handleF1Focus(e) {
  if (e.key === 'F1') {
    e.preventDefault();
    console.log('F1 pressed!');
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
</script>
<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-file-invoice text-blue-500"></i> {{ isEdit ? 'Edit' : 'Tambah' }} PR Foods
        </h1>
      </div>
      <form @submit.prevent="onSubmit" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.tanggal" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
            <div v-if="form.errors.tanggal" class="text-xs text-red-500 mt-1">{{ form.errors.tanggal }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Warehouse</label>
            <select v-model="form.warehouse_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Warehouse</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
            <div v-if="form.errors.warehouse_id" class="text-xs text-red-500 mt-1">{{ form.errors.warehouse_id }}</div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Keterangan</label>
          <textarea v-model="form.description" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
          <div v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Detail Item</label>
          <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Note</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tgl Kedatangan</th>
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
                    <div v-if="form.errors[`items.${idx}.item_id`]" class="text-xs text-red-500 mt-1">
                      {{ form.errors[`items.${idx}.item_id`] }}
                    </div>
                    <!-- Tampilkan stok -->
                    <div v-if="item.stock" class="text-xs text-gray-500 mt-1">
                      Stok: {{ formatStockDisplay(item) }}
                    </div>
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    <input type="number" min="0.01" step="0.01" v-model="item.qty" class="w-full rounded border-gray-300" required />
                    <div v-if="form.errors[`items.${idx}.qty`]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${idx}.qty`] }}</div>
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    <!-- Dropdown unit jika available_units ada -->
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
                  <td class="px-3 py-2 min-w-[120px]">
                    <input type="date" v-model="item.arrival_date" class="w-full rounded border-gray-300" />
                    <div v-if="form.errors[`items.${idx}.arrival_date` ]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${idx}.arrival_date`] }}</div>
                  </td>
                  <td class="px-3 py-2">
                    <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700" :disabled="form.items.length === 1"><i class="fa fa-trash"></i></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <button type="button" @click="addItem" class="mt-2 px-3 py-1 rounded bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200"><i class="fa fa-plus"></i> Tambah Item</button>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50">{{ isEdit ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
      </form>
    </div>
    <Modal :show="spsModal" @close="closeSpsModal">
      <div class="p-4 min-w-[320px] max-w-[90vw]">
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-lg font-bold text-gray-700">Detail Item</h2>
          <button @click="closeSpsModal" class="text-gray-400 hover:text-gray-700"><i class="fa fa-times"></i></button>
        </div>
        <div v-if="spsLoading" class="text-center py-8"><i class="fa fa-spinner fa-spin text-blue-400 text-2xl"></i></div>
        <div v-else-if="spsItem && !spsItem.error">
          <div class="mb-2">
            <span class="font-semibold">Nama:</span> {{ spsItem.name }}
          </div>
          <div class="mb-2">
            <span class="font-semibold">Deskripsi:</span>
            <span v-if="spsItem.description">{{ spsItem.description }}</span>
            <span v-else class="italic text-gray-400">(Tidak ada deskripsi)</span>
          </div>
          <div class="mb-2">
            <span class="font-semibold">Spesifikasi:</span>
            <span v-if="spsItem.specification">{{ spsItem.specification }}</span>
            <span v-else class="italic text-gray-400">(Tidak ada spesifikasi)</span>
          </div>
          <div v-if="spsItem.images && spsItem.images.length" class="mb-2">
            <span class="font-semibold">Gambar:</span>
            <div class="flex flex-wrap gap-2 mt-1">
              <img v-for="img in spsItem.images" :key="img.id" :src="img.path.startsWith('http') ? img.path : '/storage/' + img.path" class="w-24 h-24 object-contain border rounded bg-white" />
            </div>
          </div>
        </div>
        <div v-else-if="spsItem && spsItem.error" class="text-red-500 text-center py-4">{{ spsItem.error }}</div>
      </div>
    </Modal>
  </AppLayout>
</template> 