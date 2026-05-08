<template>
  <AppLayout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <div class="bg-white rounded-xl shadow p-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-scale-balanced text-blue-600"></i>
          Master MoQ
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          Pilih item, lihat konversi unit, lalu set MoQ untuk unit penjualan.
        </p>
      </div>

      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">Input MoQ</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2 relative">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Item</label>
            <input
              v-model="itemKeyword"
              @input="onSearchItem"
              type="text"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              placeholder="Ketik nama / SKU item..."
            />
            <div
              v-if="itemSuggestions.length > 0"
              class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow max-h-64 overflow-y-auto"
            >
              <button
                v-for="item in itemSuggestions"
                :key="item.id"
                type="button"
                @click="selectItem(item)"
                class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-b-0"
              >
                <div class="font-medium text-gray-800">{{ item.name }}</div>
                <div class="text-xs text-gray-500">{{ item.sku }}</div>
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Item Terpilih</label>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm min-h-[42px]">
              <span v-if="selectedItem">{{ selectedItem.name }} ({{ selectedItem.sku }})</span>
              <span v-else class="text-gray-400">Belum dipilih</span>
            </div>
          </div>
        </div>

        <div v-if="selectedItem && unitOptions.length > 0" class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Unit Penjualan</label>
            <select
              v-model="form.unit_id"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">Pilih Unit</option>
              <option v-for="unit in unitOptions" :key="unit.unit_id" :value="unit.unit_id">
                {{ unit.unit_name }}
              </option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
              {{ selectedUnit?.conversion_note || 'Pilih unit untuk melihat konversi.' }}
            </p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nilai Konversi</label>
            <input
              :value="selectedUnit?.conversion_qty || ''"
              type="number"
              class="w-full rounded-lg border-gray-300 bg-gray-100"
              readonly
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">MoQ Dijual per</label>
            <input
              v-model.number="form.moq_qty"
              type="number"
              min="0.0001"
              step="0.0001"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              placeholder="contoh: 2"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" v-if="selectedItem">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan</label>
            <input
              v-model="form.notes"
              type="text"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              placeholder="Opsional"
            />
          </div>
          <div class="flex items-end">
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-blue-600" />
              <span class="text-sm text-gray-700">Aktif</span>
            </label>
          </div>
        </div>

        <div class="flex justify-end">
          <button
            type="button"
            @click="saveMoq"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
            :disabled="saving"
          >
            <i class="fa-solid fa-save mr-1"></i>
            {{ saving ? 'Menyimpan...' : 'Simpan MoQ' }}
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-semibold text-gray-800">Data MoQ</h2>
          <button type="button" @click="loadRows" class="px-3 py-1.5 text-sm rounded bg-gray-100 hover:bg-gray-200">
            Refresh
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm border border-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 border">Item</th>
                <th class="px-3 py-2 border">SKU</th>
                <th class="px-3 py-2 border">Unit</th>
                <th class="px-3 py-2 border">Konversi</th>
                <th class="px-3 py-2 border">MoQ</th>
                <th class="px-3 py-2 border">Status</th>
                <th class="px-3 py-2 border">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="7" class="px-3 py-4 text-center text-gray-500 border">Belum ada data MoQ.</td>
              </tr>
              <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
                <td class="px-3 py-2 border">{{ row.item_name }}</td>
                <td class="px-3 py-2 border">{{ row.item_sku }}</td>
                <td class="px-3 py-2 border">{{ row.unit_name }}</td>
                <td class="px-3 py-2 border">{{ formatQty(row.conversion_qty) }}</td>
                <td class="px-3 py-2 border font-semibold">{{ formatQty(row.moq_qty) }}</td>
                <td class="px-3 py-2 border">
                  <span :class="row.is_active ? 'text-green-600' : 'text-red-600'">
                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td class="px-3 py-2 border space-x-1">
                  <button
                    type="button"
                    @click="editRow(row)"
                    class="px-2 py-1 rounded bg-amber-100 text-amber-700 hover:bg-amber-200"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    @click="removeRow(row.id)"
                    class="px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200"
                  >
                    Hapus
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const itemKeyword = ref('');
const itemSuggestions = ref([]);
const selectedItem = ref(null);
const unitOptions = ref([]);
const rows = ref([]);
const saving = ref(false);

const form = ref({
  item_id: '',
  unit_id: '',
  conversion_qty: '',
  moq_qty: '',
  notes: '',
  is_active: true,
});

const selectedUnit = computed(() => unitOptions.value.find((u) => u.unit_id === Number(form.value.unit_id)));

const formatQty = (value) => {
  const number = Number(value);
  if (Number.isNaN(number)) return '-';
  return number.toLocaleString('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 4,
  });
};

const onSearchItem = async () => {
  if (!itemKeyword.value || itemKeyword.value.length < 2) {
    itemSuggestions.value = [];
    return;
  }

  const response = await axios.get('/api/master-moq/items', { params: { q: itemKeyword.value } });
  itemSuggestions.value = response.data || [];
};

const selectItem = async (item) => {
  selectedItem.value = item;
  itemKeyword.value = `${item.name} (${item.sku})`;
  itemSuggestions.value = [];
  form.value.item_id = item.id;
  form.value.unit_id = '';
  form.value.conversion_qty = '';
  form.value.moq_qty = '';

  const response = await axios.get(`/api/master-moq/items/${item.id}/units`);
  unitOptions.value = response.data?.units || [];
};

const saveMoq = async () => {
  if (!form.value.item_id || !form.value.unit_id || !form.value.moq_qty) {
    Swal.fire('Validasi', 'Item, unit, dan nilai MoQ wajib diisi.', 'warning');
    return;
  }

  if (!selectedUnit.value) {
    Swal.fire('Validasi', 'Unit belum valid.', 'warning');
    return;
  }

  try {
    saving.value = true;
    await axios.post('/api/master-moq', {
      item_id: form.value.item_id,
      unit_id: form.value.unit_id,
      conversion_qty: selectedUnit.value.conversion_qty,
      moq_qty: form.value.moq_qty,
      notes: form.value.notes,
      is_active: form.value.is_active,
    });

    await Swal.fire('Berhasil', 'Data MoQ berhasil disimpan.', 'success');
    await loadRows();
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menyimpan data MoQ.';
    Swal.fire('Error', message, 'error');
  } finally {
    saving.value = false;
  }
};

const removeRow = async (id) => {
  const confirm = await Swal.fire({
    title: 'Hapus data?',
    text: 'Data MoQ akan dihapus.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
  });

  if (!confirm.isConfirmed) return;

  try {
    await axios.delete(`/api/master-moq/${id}`);
    await loadRows();
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menghapus data.';
    Swal.fire('Error', message, 'error');
  }
};

const editRow = async (row) => {
  const result = await Swal.fire({
    title: `Edit MoQ - ${row.item_name}`,
    input: 'number',
    inputLabel: `MoQ (${row.unit_name})`,
    inputValue: Number(row.moq_qty),
    inputAttributes: {
      min: 0.0001,
      step: 0.0001,
    },
    showCancelButton: true,
    confirmButtonText: 'Simpan',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      const num = Number(value);
      if (!value || Number.isNaN(num) || num <= 0) {
        return 'MoQ harus lebih dari 0.';
      }
      return null;
    },
  });

  if (!result.isConfirmed) return;

  try {
    await axios.put(`/api/master-moq/${row.id}`, {
      moq_qty: Number(result.value),
      notes: row.notes || null,
      is_active: Boolean(row.is_active),
    });
    await Swal.fire('Berhasil', 'Nilai MoQ berhasil diupdate.', 'success');
    await loadRows();
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal update data MoQ.';
    Swal.fire('Error', message, 'error');
  }
};

const loadRows = async () => {
  const response = await axios.get('/api/master-moq');
  rows.value = response.data || [];
};

loadRows();
</script>
