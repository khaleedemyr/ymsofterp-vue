<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-arrow-trend-up text-blue-600"></i>
            {{ isEdit ? 'Edit' : 'Tambah' }} Upselling Sales Achievement
          </h1>
        </div>
        <Link :href="route('upselling-sales-achievement.index')" class="text-gray-600 hover:text-gray-800">
          <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="bg-white rounded-xl shadow p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Outlet <span class="text-red-500">*</span></label>
            <select
              v-model="form.outlet_id"
              required
              @change="onOutletChange"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Bulan <span class="text-red-500">*</span></label>
            <select v-model="form.month" required class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
              <option value="">Pilih Bulan</option>
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun <span class="text-red-500">*</span></label>
            <select v-model="form.year" required class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
              <option value="">Pilih Tahun</option>
              <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
            </select>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Detail Target Item</h2>
            <button
              type="button"
              @click="addRow"
              :disabled="!form.outlet_id"
              class="px-3 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 text-sm"
            >
              <i class="fa-solid fa-plus mr-1"></i> Tambah Item
            </button>
          </div>

          <p v-if="!form.outlet_id" class="text-sm text-amber-600 mb-4">Pilih outlet terlebih dahulu untuk menambah item.</p>

          <div class="overflow-x-auto overflow-y-visible">
            <table class="min-w-full text-sm border border-gray-200 relative">
              <thead class="bg-gray-800 text-white">
                <tr>
                  <th class="px-3 py-2 text-left w-8">#</th>
                  <th class="px-3 py-2 text-left min-w-[280px]">FB Product Projection</th>
                  <th class="px-3 py-2 text-right min-w-[120px]">Average Check</th>
                  <th class="px-3 py-2 text-right min-w-[100px]">Cover</th>
                  <th class="px-3 py-2 text-right min-w-[130px]">FB Revenue</th>
                  <th class="px-3 py-2 text-center w-16">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="form.items.length === 0">
                  <td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada item. Klik "Tambah Item".</td>
                </tr>
                <tr v-for="(row, idx) in form.items" :key="row._key" class="border-t align-top overflow-visible">
                  <td class="px-3 py-3 text-gray-500">{{ idx + 1 }}</td>
                  <td class="px-3 py-3 overflow-visible">
                    <div class="relative">
                      <input
                        :id="`usa-item-input-${idx}`"
                        v-model="row.search"
                        type="text"
                        :disabled="!form.outlet_id"
                        placeholder="Cari item..."
                        autocomplete="off"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                        @input="onItemSearch(idx)"
                        @focus="onItemFocus(idx)"
                        @blur="onItemBlur(idx)"
                      />
                      <Teleport to="body">
                        <div
                          v-if="row.showDropdown && row.suggestions.length"
                          :style="getDropdownStyle(idx)"
                          class="fixed z-[99999] bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto"
                        >
                          <button
                            v-for="item in row.suggestions"
                            :key="item.id"
                            type="button"
                            class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-b-0"
                            @mousedown.prevent="selectItem(idx, item)"
                          >
                            <div class="font-medium text-gray-800">{{ item.name }}</div>
                            <div class="text-xs text-gray-500">{{ item.category_label }}</div>
                          </button>
                        </div>
                      </Teleport>
                    </div>
                    <div v-if="row.item_name" class="mt-1 text-xs text-gray-600">
                      {{ row.item_name }}
                      <span v-if="row.category_label" class="text-gray-400">· {{ row.category_label }}</span>
                    </div>
                  </td>
                  <td class="px-3 py-3 text-right font-medium whitespace-nowrap">
                    {{ formatCurrency(row.average_check) }}
                  </td>
                  <td class="px-3 py-3">
                    <input
                      v-model.number="row.cover"
                      type="number"
                      min="1"
                      step="1"
                      class="w-full rounded-lg border-gray-300 text-right focus:border-blue-500 focus:ring-blue-500"
                      @input="recalcRow(idx)"
                    />
                  </td>
                  <td class="px-3 py-3 text-right font-semibold whitespace-nowrap">
                    {{ formatCurrency(row.fb_revenue) }}
                  </td>
                  <td class="px-3 py-3 text-center">
                    <button
                      type="button"
                      @click="removeRow(idx)"
                      class="text-red-600 hover:text-red-800 p-1"
                      title="Hapus baris"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="form.items.length">
                <tr class="bg-gray-50 font-semibold border-t">
                  <td colspan="3" class="px-3 py-2 text-right">Total</td>
                  <td class="px-3 py-2 text-right">{{ totalCover }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(totalFbRevenue) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('upselling-sales-achievement.index')" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
            Batal
          </Link>
          <button
            type="submit"
            :disabled="saving || form.items.length === 0"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {{ saving ? 'Menyimpan...' : (isEdit ? 'Update' : 'Simpan') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, default: null },
  outlets: { type: Array, default: () => [] },
  monthOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.record?.id);
const saving = ref(false);
let rowKey = 0;

function makeRow(data = {}) {
  return {
    _key: ++rowKey,
    item_id: data.item_id || '',
    item_name: data.item_name || '',
    category_label: data.category_label || '',
    average_check: Number(data.average_check) || 0,
    cover: Number(data.cover) || 1,
    fb_revenue: Number(data.fb_revenue) || 0,
    search: data.item_name || '',
    suggestions: [],
    showDropdown: false,
    searchTimer: null,
  };
}

const form = useForm({
  outlet_id: props.record?.outlet_id || '',
  month: props.record?.month || '',
  year: props.record?.year || '',
  items: [],
});

onMounted(() => {
  if (props.record?.items?.length) {
    form.items = props.record.items.map((item) => makeRow(item));
    form.items.forEach((_, idx) => recalcRow(idx));
  }
});

const totalCover = computed(() => form.items.reduce((sum, row) => sum + (Number(row.cover) || 0), 0));
const totalFbRevenue = computed(() => form.items.reduce((sum, row) => sum + (Number(row.fb_revenue) || 0), 0));

function formatCurrency(val) {
  const n = Number(val) || 0;
  return n.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
}

function onOutletChange() {
  form.items = [];
}

function addRow() {
  if (!form.outlet_id) return;
  form.items.push(makeRow({ cover: 1 }));
}

function removeRow(idx) {
  form.items.splice(idx, 1);
}

function recalcRow(idx) {
  const row = form.items[idx];
  const cover = Math.max(1, parseInt(row.cover, 10) || 1);
  row.cover = cover;
  row.fb_revenue = Math.round((Number(row.average_check) || 0) * cover);
}

function onItemFocus(idx) {
  form.items[idx].showDropdown = true;
}

function onItemBlur(idx) {
  setTimeout(() => {
    form.items[idx].showDropdown = false;
  }, 200);
}

function getDropdownStyle(idx) {
  const input = document.getElementById(`usa-item-input-${idx}`);
  if (!input) return {};
  const rect = input.getBoundingClientRect();
  return {
    left: `${rect.left}px`,
    top: `${rect.bottom + 4}px`,
    width: `${Math.max(rect.width, 280)}px`,
  };
}

function onItemSearch(idx) {
  const row = form.items[idx];
  row.item_id = '';
  row.item_name = '';
  row.category_label = '';
  row.average_check = 0;
  row.fb_revenue = 0;
  row.showDropdown = true;

  if (row.searchTimer) clearTimeout(row.searchTimer);
  if (!form.outlet_id || row.search.length < 1) {
    row.suggestions = [];
    return;
  }

  row.searchTimer = setTimeout(async () => {
    try {
      const res = await axios.get(route('upselling-sales-achievement.search-items'), {
        params: { outlet_id: form.outlet_id, q: row.search },
      });
      row.suggestions = res.data.items || [];
    } catch {
      row.suggestions = [];
    }
  }, 300);
}

function selectItem(idx, item) {
  const row = form.items[idx];
  const duplicate = form.items.some((r, i) => i !== idx && r.item_id === item.id);
  if (duplicate) {
    Swal.fire({ icon: 'warning', title: 'Item duplikat', text: 'Item ini sudah ada di daftar.' });
    return;
  }

  row.item_id = item.id;
  row.item_name = item.name;
  row.category_label = item.category_label;
  row.average_check = item.average_check;
  row.search = item.name;
  row.suggestions = [];
  row.showDropdown = false;
  recalcRow(idx);
}

function submit() {
  const invalid = form.items.some((row) => !row.item_id || !row.cover);
  if (invalid) {
    Swal.fire({ icon: 'error', title: 'Data belum lengkap', text: 'Pastikan semua baris sudah memilih item dan cover.' });
    return;
  }

  const payload = {
    outlet_id: form.outlet_id,
    month: form.month,
    year: form.year,
    items: form.items.map((row) => ({
      item_id: row.item_id,
      item_name: row.item_name,
      category_label: row.category_label,
      average_check: row.average_check,
      cover: row.cover,
      fb_revenue: row.fb_revenue,
    })),
  };

  saving.value = true;
  const options = {
    onFinish: () => { saving.value = false; },
    onError: (errors) => {
      const msg = Object.values(errors).flat().join('\n');
      Swal.fire({ icon: 'error', title: 'Gagal menyimpan', text: msg || 'Periksa kembali input Anda.' });
    },
  };

  if (isEdit.value) {
    form.transform(() => payload).put(route('upselling-sales-achievement.update', props.record.id), options);
  } else {
    form.transform(() => payload).post(route('upselling-sales-achievement.store'), options);
  }
}
</script>
