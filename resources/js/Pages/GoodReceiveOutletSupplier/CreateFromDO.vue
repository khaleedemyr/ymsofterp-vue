<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck"></i> Buat Good Receive dari Delivery Order
        </h1>
        <button @click="router.visit('/good-receive-outlet-supplier')" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>

      <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-auto p-6">

      <div v-if="deliveryOrder">
        <!-- Info Delivery Order -->
        <div class="mb-4 p-4 border rounded bg-blue-50">
          <div class="font-semibold text-blue-800">Info Delivery Order</div>
          <div class="text-sm mt-1">Nomor DO: <span class="font-mono">{{ deliveryOrder.number }}</span></div>
          <div class="text-sm">Tanggal: {{ deliveryOrder.date }}</div>
          <div class="text-sm">Outlet: {{ deliveryOrder.outlet_name }}</div>
          <div class="text-sm">RO GR Number: {{ deliveryOrder.ro_gr_number }}</div>
          <div class="text-sm">RO Floor Order: {{ deliveryOrder.ro_floor_order_number }}</div>
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
                <tr v-for="item in deliveryOrder.items" :key="item.id">
                  <td class="px-4 py-2">{{ item.name }}</td>
                  <td class="px-4 py-2">{{ item.qty }}</td>
                  <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                      <input
                        type="number"
                        v-model="item.received_qty"
                        class="w-24 px-2 py-1 border rounded"
                        :min="0"
                        :max="item.qty"
                        step="0.01"
                      />
                      <button type="button" @click="item.received_qty = item.qty" class="px-2 py-1 bg-gray-200 rounded text-xs font-bold">=</button>
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
            @click="router.visit('/good-receive-outlet-supplier')"
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
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  deliveryOrder: {
    type: Object,
    required: true
  }
});

const loading = ref(false);

const validateQty = (item) => {
  item.qty_error = '';
  if (item.received_qty === null || item.received_qty === undefined) {
    item.qty_error = 'Jumlah harus diisi';
    return false;
  }
  if (item.received_qty < 0) {
    item.qty_error = 'Jumlah tidak boleh negatif';
    return false;
  }
  if (item.received_qty > item.qty) {
    item.qty_error = `Jumlah tidak boleh melebihi ${item.qty}`;
    return false;
  }
  if (item.received_qty.toString().split('.')[1]?.length > 2) {
    item.qty_error = 'Maksimal 2 angka di belakang koma';
    return false;
  }
  return true;
};

const submit = async () => {
  loading.value = true;
  
  const isValid = props.deliveryOrder.items.every(item => validateQty(item));
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
    console.log('Sending data:', {
      delivery_order_id: props.deliveryOrder.id,
      receive_date: new Date().toISOString().slice(0, 10),
      items: props.deliveryOrder.items.map(item => ({
        item_id: item.item_id,
        qty_ordered: item.qty,
        qty_received: item.received_qty,
        unit_id: 1 // Default to 1 for Kilogram
      }))
    });
    
    await axios.post('/good-receive-outlet-supplier/store-from-do', {
      delivery_order_id: props.deliveryOrder.id,
      receive_date: new Date().toISOString().slice(0, 10),
      items: props.deliveryOrder.items.map(item => ({
        item_id: item.item_id,
        qty_ordered: item.qty,
        qty_received: item.received_qty,
        unit_id: 1 // Default to 1 for Kilogram
      })),
    });
    
    loading.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Good Receive berhasil disimpan',
      timer: 1500,
      showConfirmButton: false
    });
    // Redirect ke index page
    router.visit('/good-receive-outlet-supplier');
  } catch (e) {
    loading.value = false;
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal simpan Good Receive',
    });
  }
};


</script>
