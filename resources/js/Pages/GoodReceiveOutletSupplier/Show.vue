<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 relative max-h-[90vh] overflow-y-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-truck"></i> Detail Good Receive Outlet Supplier
      </h2>
      <button @click="handleClose" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
        <i class="fa-solid fa-xmark text-2xl"></i>
      </button>

      <!-- Info GR -->
      <div class="mb-4 p-4 border rounded bg-blue-50">
        <div class="font-semibold text-blue-800">Info Good Receive</div>
        <div class="grid grid-cols-2 gap-4 mt-2">
          <div>
            <div class="text-sm">Nomor GR: <span class="font-mono">{{ gr.gr_number }}</span></div>
            <div class="text-sm">Tanggal: {{ gr.receive_date }}</div>
            <div class="text-sm">Status: 
              <span :class="{
                'text-green-600': gr.status === 'Completed',
                'text-yellow-600': gr.status === 'Partial',
                'text-red-600': gr.status === 'Cancelled'
              }">
                {{ gr.status }}
              </span>
            </div>
          </div>
          <div>
            <div class="text-sm">Outlet: {{ gr.outlet_name }}</div>
            <div class="text-sm">Diterima oleh: {{ gr.received_by_name }}</div>
            <div class="text-sm">Dibuat: {{ gr.created_at }}</div>
          </div>
        </div>
      </div>

      <!-- Info RO -->
      <div class="mb-4 p-4 border rounded bg-blue-50">
        <div class="font-semibold text-blue-800">Info RO Supplier</div>
        <div class="grid grid-cols-2 gap-4 mt-2">
          <div>
            <div class="text-sm">Nomor RO: <span class="font-mono">{{ gr.ro_number }}</span></div>
            <div class="text-sm">Tanggal RO: {{ gr.ro_date }}</div>
          </div>
          <div>
            <div class="text-sm">Supplier: {{ gr.supplier_name }}</div>
          </div>
        </div>
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
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="item in gr.items" :key="item.id">
                <td class="px-4 py-2">{{ item.item_name }}</td>
                <td class="px-4 py-2">{{ item.qty_ordered }}</td>
                <td class="px-4 py-2">{{ item.qty_received }}</td>
                <td class="px-4 py-2">{{ item.unit_name }}</td>
                <td class="px-4 py-2">{{ formatPrice(item.price) }}</td>
                <td class="px-4 py-2">{{ formatPrice((item.qty_received || 0) * (item.price || 0)) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50">
              <tr>
                <td colspan="5" class="px-4 py-2 text-right font-semibold">Grand Total:</td>
                <td class="px-4 py-2 font-semibold">{{ formatPrice(grandTotal) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Tombol Close -->
      <div class="flex justify-end">
        <button
          @click="handleClose"
          class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
        >
          Tutup
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  gr: Object
});

const formatPrice = (price) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(price || 0);
};

const grandTotal = computed(() => {
  if (!props.gr.items) return 0;
  return props.gr.items.reduce((sum, item) => sum + ((Number(item.qty_received) || 0) * (Number(item.price) || 0)), 0);
});

const handleClose = () => {
  window.history.length > 1 ? window.history.back() : window.location.href = '/good-receive-outlet-supplier';
};
</script> 