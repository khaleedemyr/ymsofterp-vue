<script setup>
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  items: Array,
  units: Array,
  warehouses: Array,
  user: Object,
});

const warehouseId = ref('');
const itemAsal = ref('');
const itemHasil = ref('');
const unitHasil = ref('');
const qtyHasil = ref('');
const loading = ref(false);
const sourceStock = ref({
  qty_small: 0,
  qty_medium: 0,
  qty_large: 0,
  small_unit_name: '',
  medium_unit_name: '',
  large_unit_name: '',
});

// Watch itemAsal dan itemHasil untuk set default unit
watch(() => itemHasil.value, (val) => {
  const item = props.items.find(i => i.id == val);
  if (item) unitHasil.value = item.smallUnit?.id || item.small_unit?.id || '';
});
watch([() => warehouseId.value, () => itemAsal.value], async ([warehouse, item]) => {
  if (!warehouse || !item) {
    sourceStock.value = {
      qty_small: 0,
      qty_medium: 0,
      qty_large: 0,
      small_unit_name: '',
      medium_unit_name: '',
      large_unit_name: '',
    };
    return;
  }

  try {
    const { data } = await axios.get('/api/repack/item-stocks', {
      params: {
        warehouse_id: warehouse,
        item_id: item,
      },
    });
    sourceStock.value = {
      qty_small: Number(data?.qty_small || 0),
      qty_medium: Number(data?.qty_medium || 0),
      qty_large: Number(data?.qty_large || 0),
      small_unit_name: data?.small_unit_name || '',
      medium_unit_name: data?.medium_unit_name || '',
      large_unit_name: data?.large_unit_name || '',
    };
  } catch (error) {
    sourceStock.value = {
      qty_small: 0,
      qty_medium: 0,
      qty_large: 0,
      small_unit_name: '',
      medium_unit_name: '',
      large_unit_name: '',
    };
  }
});

const getAvailableUnits = (item) => {
  const arr = [];
  const smallUnit = item?.smallUnit || item?.small_unit || null;
  const mediumUnit = item?.mediumUnit || item?.medium_unit || null;
  const largeUnit = item?.largeUnit || item?.large_unit || null;
  if (smallUnit) arr.push(smallUnit);
  if (mediumUnit) arr.push(mediumUnit);
  if (largeUnit) arr.push(largeUnit);
  return arr.filter((unit, index, self) => unit?.id && self.findIndex((x) => x.id === unit.id) === index);
};

const submit = async () => {
  loading.value = true;
  try {
    const response = await axios.post('/repack', {
      warehouse_id: warehouseId.value,
      item_asal_id: itemAsal.value,
      item_hasil_id: itemHasil.value,
      unit_hasil_id: unitHasil.value,
      qty_hasil: qtyHasil.value,
    });
    Swal.fire({ 
      icon: 'success', 
      title: 'Berhasil', 
      text: response?.data?.message || 'Repack berhasil disimpan!',
      showCancelButton: true,
      confirmButtonText: 'Print Barcode',
      cancelButtonText: 'Kembali ke List'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = `/repack/${response.data.repack.id}/print-barcodes`;
      } else {
        window.location.href = '/repack';
      }
    });
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: err?.response?.data?.message || 'Gagal simpan repack.' });
  } finally {
    loading.value = false;
  }
};
</script>
<template>
  <AppLayout>
    <div class="w-full max-w-xl mx-auto py-8">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fa-solid fa-box-open text-blue-500"></i> Buat Repack
      </h1>
      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="mb-4">
          <label class="block mb-1 font-semibold">Warehouse</label>
          <select v-model="warehouseId" class="w-full px-4 py-2 rounded-xl border border-blue-200">
            <option value="">Pilih Warehouse</option>
            <option v-for="warehouse in props.warehouses" :key="warehouse.id" :value="warehouse.id">
              {{ warehouse.name }}
            </option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">Item Asal</label>
          <select v-model="itemAsal" class="w-full px-4 py-2 rounded-xl border border-blue-200">
            <option value="">Pilih Item Asal</option>
            <option v-for="item in props.items" :key="item.id" :value="item.id">
              {{ item.name }}{{ (item.smallUnit || item.small_unit) ? ' (' + (item.smallUnit?.name || item.small_unit?.name) + ')' : '' }}
            </option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">Stok Item Asal Saat Ini</label>
          <div class="w-full px-4 py-2 rounded-xl border border-blue-200 bg-blue-50 text-blue-800 text-sm">
            <div>{{ sourceStock.qty_small }} {{ sourceStock.small_unit_name || '-' }}</div>
            <div v-if="sourceStock.medium_unit_name">{{ sourceStock.qty_medium }} {{ sourceStock.medium_unit_name }}</div>
            <div v-if="sourceStock.large_unit_name">{{ sourceStock.qty_large }} {{ sourceStock.large_unit_name }}</div>
          </div>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">Item Hasil</label>
          <select v-model="itemHasil" class="w-full px-4 py-2 rounded-xl border border-blue-200">
            <option value="">Pilih Item Hasil</option>
            <option v-for="item in props.items" :key="item.id" :value="item.id">
              {{ item.name }}{{ (item.smallUnit || item.small_unit) ? ' (' + (item.smallUnit?.name || item.small_unit?.name) + ')' : '' }}
            </option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">Satuan Hasil</label>
          <select v-model="unitHasil" class="w-full px-4 py-2 rounded-xl border border-blue-200">
            <option value="">Pilih Satuan</option>
            <option v-for="unit in getAvailableUnits(props.items.find(i => i.id == itemHasil))" :key="unit.id" :value="unit.id">
              {{ unit.name }}
            </option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">Qty Hasil</label>
          <input v-model="qtyHasil" type="number" min="0" class="w-full px-4 py-2 rounded-xl border border-blue-200" />
        </div>
        <button :disabled="loading" @click="submit" class="w-full bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg font-semibold mt-4 disabled:opacity-50">
          <span v-if="loading"><i class="fa fa-spinner fa-spin"></i> Menyimpan...</span>
          <span v-else>Simpan</span>
        </button>
      </div>
    </div>
  </AppLayout>
</template> 