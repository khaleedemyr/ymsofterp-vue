<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 relative max-h-[90vh] overflow-y-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-truck"></i> Buat Good Receive Outlet Supplier
      </h2>
      <button @click="handleClose" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
        <i class="fa-solid fa-xmark text-2xl"></i>
      </button>

      <div v-if="!roFetched">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Pilih RO Supplier</label>
          <div class="flex gap-2">
            <select 
              v-model="selectedRO" 
              class="flex-1 px-4 py-2 border border-gray-300 rounded-lg"
              @change="fetchRO"
            >
              <option value="">Pilih RO Supplier...</option>
              <option v-for="ro in roList" :key="ro.id" :value="ro.ro_number">
                {{ ro.ro_number ? `${ro.ro_number} - ${ro.floor_order_number || ''} - ${ro.tanggal || ''}` : '-' }}
              </option>
            </select>
          </div>
          <div v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</div>
        </div>
      </div>

      <div v-else>
        <!-- Info RO -->
        <div class="mb-4 p-4 border rounded bg-blue-50">
          <div class="font-semibold text-blue-800">Info RO Supplier</div>
          <div class="text-sm mt-1">Nomor: <span class="font-mono">{{ ro.ro_number }}</span></div>
          <div class="text-sm">Tanggal: {{ ro.tanggal }}</div>
          <div class="text-sm">Supplier: {{ ro.supplier_name }}</div>
        </div>

        <!-- Tabel Item -->
        <div class="mb-4">
          <div class="font-semibold mb-2">Daftar Item</div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty Order</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty Terima</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in items" :key="item.id">
                  <td class="px-4 py-2">{{ item.item_name }}</td>
                  <td class="px-4 py-2">{{ item.qty_ordered }}</td>
                  <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                      <input
                        type="number"
                        v-model="item.qty_received"
                        class="w-24 px-2 py-1 border rounded"
                        :min="0"
                        :max="item.qty_ordered"
                        step="0.01"
                      />
                      <button type="button" @click="item.qty_received = item.qty_ordered" class="px-2 py-1 bg-gray-200 rounded text-xs font-bold">=</button>
                    </div>
                    <div v-if="item.qty_error" class="text-xs text-red-600">{{ item.qty_error }}</div>
                  </td>
                  <td class="px-4 py-2">{{ item.unit_name }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Tombol Submit -->
        <div class="flex justify-end gap-2">
          <button
            @click="handleClose"
            class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
          >
            Batal
          </button>
          <button
            @click="submit"
            :disabled="loading"
            class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {{ loading ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const emit = defineEmits(['close', 'success']);

const roList = ref([]);
const selectedRO = ref('');
const ro = ref(null);
const items = ref([]);
const roFetched = ref(false);
const error = ref('');
const loading = ref(false);
const units = ref([]);

onMounted(async () => {
  try {
    const res = await axios.get('/api/ro-suppliers');
    roList.value = res.data;
    // Fetch units
    const resUnits = await axios.get('/api/units');
    units.value = resUnits.data;
  } catch (e) {
    console.error('Error fetching RO list or units:', e);
  }
});

const fetchRO = async () => {
  if (!selectedRO.value) return;
  
  error.value = '';
  try {
    const res = await axios.post('/good-receive-outlet-supplier/fetch-ro', { 
      ro_number: selectedRO.value 
    });
    ro.value = res.data.ro;
    items.value = res.data.items.map(item => {
      const unitObj = units.value.find(u => u.name === item.unit_name);
      return {
        ...item,
        qty_received: 0,
        qty_error: '',
        unit_id: unitObj ? unitObj.id : null
      };
    });
    roFetched.value = true;
  } catch (e) {
    error.value = e.response?.data?.message || 'RO tidak ditemukan';
  }
};

const validateQty = (item) => {
  item.qty_error = '';
  if (item.qty_received === null || item.qty_received === undefined) {
    item.qty_error = 'Jumlah harus diisi';
    return false;
  }
  if (item.qty_received < 0) {
    item.qty_error = 'Jumlah tidak boleh negatif';
    return false;
  }
  if (item.qty_received > item.qty_ordered) {
    item.qty_error = `Jumlah tidak boleh melebihi ${item.qty_ordered}`;
    return false;
  }
  if (item.qty_received.toString().split('.')[1]?.length > 2) {
    item.qty_error = 'Maksimal 2 angka di belakang koma';
    return false;
  }
  return true;
};

const submit = async () => {
  error.value = '';
  loading.value = true;
  
  const isValid = items.value.every(item => validateQty(item));
  if (!isValid) {
    loading.value = false;
    await Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      text: 'Mohon periksa kembali jumlah yang diterima',
    });
    return;
  }

  try {
    await axios.post('/good-receive-outlet-supplier', {
      ro_supplier_id: ro.value.id,
      receive_date: new Date().toISOString().slice(0, 10),
      items: items.value.map(item => ({
        ro_item_id: item.id,
        item_id: item.item_id,
        qty_ordered: item.qty_ordered,
        qty_received: item.qty_received,
        unit_id: item.unit_id,
        price: item.price
      })),
      notes: '',
    });
    
    loading.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Good Receive berhasil disimpan',
      timer: 1500,
      showConfirmButton: false
    });
    emit('success');
    handleClose();
  } catch (e) {
    loading.value = false;
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal simpan Good Receive',
    });
    error.value = e.response?.data?.message || 'Gagal simpan Good Receive';
  }
};

const handleClose = () => {
  emit('close');
};
</script> 