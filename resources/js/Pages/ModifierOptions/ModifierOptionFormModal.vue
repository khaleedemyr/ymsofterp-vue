<script setup>
import { ref, watch, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  option: Object, // untuk edit
  modifiers: Array,
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  modifier_id: '',
  name: '',
  modifier_bom_json: '',
});

const bomRows = ref([]); // [{item_id, qty, unit_id, stock_cut}]
const allItems = ref([]); // {id, name}
const itemUnits = reactive({}); // item_id -> [{id, name, type}]
const itemSearch = ref([]); // search keyword per baris
const itemsLoading = ref(false);
const itemsError = ref('');

function normalizeBomRow(row) {
  return {
    item_id: row?.item_id !== undefined && row?.item_id !== null && row?.item_id !== ''
      ? Number(row.item_id)
      : '',
    qty: row?.qty ?? '',
    unit_id: row?.unit_id !== undefined && row?.unit_id !== null && row?.unit_id !== ''
      ? Number(row.unit_id)
      : '',
    // Backend: stock_cut false/0/null = Ya ikut potong; true/1 = Tidak ikut.
    stock_cut: row?.stock_cut === undefined
      ? false
      : row.stock_cut === true || row.stock_cut === 1 || row.stock_cut === '1',
  };
}

async function fetchAllItems(includeIds = []) {
  itemsLoading.value = true;
  itemsError.value = '';
  try {
    const params = {};
    const ids = (includeIds || []).filter((id) => Number(id) > 0);
    if (ids.length) {
      params.include_ids = ids.join(',');
    }
    const res = await axios.get('/api/items/for-modifier-bom', { params });
    let items = [];
    if (Array.isArray(res.data)) {
      items = res.data;
    } else if (Array.isArray(res.data?.items)) {
      items = res.data.items;
    } else if (Array.isArray(res.data?.data)) {
      items = res.data.data;
    }
    allItems.value = items.map((i) => ({ id: Number(i.id), name: i.name }));
    if (!allItems.value.length) {
      itemsError.value = 'Daftar item kosong.';
    }
  } catch (e) {
    allItems.value = [];
    itemsError.value = e?.response?.data?.message || e.message || 'Gagal memuat daftar item.';
  } finally {
    itemsLoading.value = false;
  }
}

async function fetchItemUnits(itemId) {
  if (!itemId) return [];
  const key = String(itemId);
  if (itemUnits[key]) return itemUnits[key];
  try {
    const res = await axios.get(`/api/items/${itemId}/detail`);
    const units = (res.data?.item?.units || []).map((u) => ({
      id: Number(u.id),
      name: u.name,
      type: u.type,
    }));
    itemUnits[key] = units;
    return units;
  } catch {
    itemUnits[key] = [];
    return [];
  }
}

async function prefetchBomUnits(rows) {
  const ids = [...new Set(rows.map((r) => Number(r.item_id)).filter((id) => id > 0))];
  await Promise.all(ids.map((id) => fetchItemUnits(id)));
}

function addBomRow() {
  // stock_cut false/0 = ikut dipotong (sama seperti BOM item & Recipe Checker)
  bomRows.value.push({ item_id: '', qty: 1, unit_id: '', stock_cut: false });
}
function removeBomRow(idx) {
  bomRows.value.splice(idx, 1);
}

function ensureItemSearchLength() {
  while (itemSearch.value.length < bomRows.value.length) itemSearch.value.push('');
  while (itemSearch.value.length > bomRows.value.length) itemSearch.value.pop();
}

watch(bomRows, ensureItemSearchLength, { deep: true });

function filteredItems(idx) {
  const keyword = (itemSearch.value[idx] || '').toLowerCase().trim();
  const selectedId = Number(bomRows.value[idx]?.item_id) || null;
  let list = allItems.value;
  if (keyword) {
    list = list.filter((i) => i.name.toLowerCase().includes(keyword));
  }
  // Pastikan item terpilih tetap ada di opsi meski sedang difilter / tidak ketemu keyword
  if (selectedId && !list.some((i) => Number(i.id) === selectedId)) {
    const selected = allItems.value.find((i) => Number(i.id) === selectedId);
    if (selected) list = [selected, ...list];
  }
  return list;
}

async function onItemChange(row) {
  row.unit_id = '';
  if (!row.qty) row.qty = 1;
  await fetchItemUnits(row.item_id);
}

watch(() => props.show, async (val) => {
  if (!val) return;

  let parsedRows = [];
  if (props.mode === 'edit' && props.option) {
    form.modifier_id = props.option.modifier_id;
    form.name = props.option.name;
    if (props.option.modifier_bom_json) {
      try {
        const parsed = JSON.parse(props.option.modifier_bom_json);
        parsedRows = Array.isArray(parsed) ? parsed.map(normalizeBomRow) : [];
      } catch {
        parsedRows = [];
      }
    }
  } else {
    form.modifier_id = '';
    form.name = '';
    parsedRows = [];
  }

  bomRows.value = parsedRows;
  itemSearch.value = parsedRows.map(() => '');

  const includeIds = parsedRows.map((r) => r.item_id).filter(Boolean);
  await fetchAllItems(includeIds);
  await prefetchBomUnits(parsedRows);
});

const isSubmitting = ref(false);

async function submit() {
  isSubmitting.value = true;
  form.modifier_bom_json = JSON.stringify(
    bomRows.value.map((row) => ({
      item_id: row.item_id === '' ? null : Number(row.item_id),
      qty: row.qty === '' || row.qty === null ? 0 : Number(row.qty),
      unit_id: row.unit_id === '' ? null : Number(row.unit_id),
      stock_cut: !!row.stock_cut,
    }))
  );
  if (props.mode === 'create') {
    form.post(route('modifier-options.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Option berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => { isSubmitting.value = false; },
      onFinish: () => { isSubmitting.value = false; },
    });
  } else if (props.mode === 'edit' && props.option) {
    form.put(route('modifier-options.update', props.option.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Option berhasil diupdate!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => { isSubmitting.value = false; },
      onFinish: () => { isSubmitting.value = false; },
    });
  }
}

function closeModal() {
  emit('close');
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 transition-all p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-auto p-0 animate-fade-in max-h-[92vh] overflow-y-auto">
      <div class="px-8 pt-8 pb-2">
        <div class="flex items-center gap-2 mb-6">
          <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M16 7a4 4 0 01-8 0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 3v4m0 0a4 4 0 01-4 4H4m8-4a4 4 0 014 4h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Modifier Option</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700">Modifier</label>
            <select v-model="form.modifier_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Modifier</option>
              <option v-for="mod in modifiers" :key="mod.id" :value="mod.id">{{ mod.name }}</option>
            </select>
            <div v-if="form.errors.modifier_id" class="text-xs text-red-500 mt-1">{{ form.errors.modifier_id }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Option</label>
            <input v-model="form.name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
            <div v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</div>
          </div>

          <!-- BOM Modifier Section -->
          <div class="mt-6">
            <div class="flex items-center gap-2 mb-2">
              <span class="font-semibold text-gray-700">BOM Modifier (Potong Stok)</span>
              <button type="button" @click="addBomRow" class="ml-auto bg-blue-500 text-white px-3 py-1 rounded shadow hover:bg-blue-700 text-xs">+ Tambah Baris</button>
            </div>
            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mb-2">
              Kolom <b>Skip Cut</b>: centang = baris <b>tidak</b> ikut dipotong di Stock Cut (nilai stock_cut=1). Biarkan kosong agar ikut dipotong.
            </p>
            <p v-if="itemsLoading" class="text-xs text-blue-600 mb-2">Memuat daftar item...</p>
            <p v-else-if="itemsError" class="text-xs text-red-600 mb-2">{{ itemsError }}</p>
            <p v-else class="text-xs text-gray-500 mb-2">{{ allItems.length }} item tersedia di dropdown</p>
            <div v-if="bomRows.length === 0" class="text-gray-400 italic mb-2">Belum ada BOM</div>
            <div v-if="bomRows.length" class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg shadow-sm">
              <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-blue-100 text-blue-900 z-10">
                  <tr>
                    <th class="px-2 py-1 text-left">Item</th>
                    <th class="px-2 py-1 text-left">Qty</th>
                    <th class="px-2 py-1 text-left">Unit</th>
                    <th class="px-2 py-1 text-center" title="Centang = tidak ikut potong stok">Skip Cut</th>
                    <th class="px-2 py-1"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in bomRows" :key="idx" class="bg-white border-b last:border-b-0">
                    <td class="px-2 py-1 min-w-[220px]">
                      <input v-model="itemSearch[idx]" placeholder="Cari item..." class="w-full mb-1 rounded border-gray-200 px-2 py-1 text-xs" />
                      <select v-model="row.item_id" class="w-full rounded border-gray-300" @change="onItemChange(row)">
                        <option value="">Pilih Item</option>
                        <option v-for="item in filteredItems(idx)" :key="item.id" :value="item.id">{{ item.name }}</option>
                      </select>
                    </td>
                    <td class="px-2 py-1">
                      <input v-model="row.qty" type="number" min="0" step="any" class="w-20 rounded border-gray-300" />
                    </td>
                    <td class="px-2 py-1 min-w-[140px]">
                      <select v-model="row.unit_id" class="w-full rounded border-gray-300">
                        <option value="">Pilih Unit</option>
                        <option
                          v-for="unit in itemUnits[String(row.item_id)] || []"
                          :key="unit.id"
                          :value="unit.id"
                        >{{ unit.name }} ({{ unit.type }})</option>
                      </select>
                    </td>
                    <td class="px-2 py-1 text-center">
                      <input type="checkbox" v-model="row.stock_cut" class="rounded border-gray-300" />
                    </td>
                    <td class="px-2 py-1 text-center">
                      <button type="button" @click="removeBomRow(idx)" class="text-red-500 hover:text-red-700"><i class="fa fa-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </form>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-2xl sticky bottom-0">
        <button type="button" @click="closeModal" class="inline-flex items-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition sm:w-auto sm:text-sm mr-2">
          Batal
        </button>
        <button type="button" @click="submit" :disabled="isSubmitting" class="inline-flex items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
          <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isSubmitting ? (mode === 'edit' ? 'Menyimpan...' : 'Menambah...') : (mode === 'edit' ? 'Simpan' : 'Tambah') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px);}
  to { opacity: 1; transform: translateY(0);}
}
.animate-fade-in {
  animation: fade-in 0.3s cubic-bezier(.4,0,.2,1);
}
</style>
