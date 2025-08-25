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
            <div class="text-sm">Warehouse Outlet: {{ gr.warehouse_outlet_name || '-' }}</div>
            <div class="text-sm">Diterima oleh: {{ gr.received_by_name }}</div>
          </div>
        </div>
      </div>

      <!-- Info PO/RO -->
      <div class="mb-4 p-4 border rounded bg-blue-50">
        <div class="font-semibold text-blue-800">{{ gr.delivery_order_id ? 'Info PO' : 'Info RO Supplier' }}</div>
        <div class="grid grid-cols-2 gap-4 mt-2">
          <div>
            <div class="text-sm" v-if="gr.delivery_order_id">
              Nomor PO: <span class="font-mono">{{ gr.po_number || '-' }}</span>
            </div>
            <div class="text-sm" v-else>
              Nomor RO: <span class="font-mono">{{ gr.ro_number || '-' }}</span>
            </div>
            <div class="text-sm" v-if="gr.delivery_order_id">
              Tanggal PO: {{ gr.po_date ? new Date(gr.po_date).toLocaleDateString('id-ID') : '-' }}
            </div>
            <div class="text-sm" v-else>
              Tanggal RO: {{ gr.ro_date || '-' }}
            </div>
          </div>
                     <div>
             <div class="text-sm">Supplier: {{ gr.supplier_name || '-' }}</div>
             <div class="text-sm" v-if="gr.delivery_order_id">Dibuat oleh: {{ gr.po_creator_name || '-' }}</div>
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
               </tr>
             </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
               <tr v-for="item in gr.items" :key="item.id">
                 <td class="px-4 py-2">{{ item.item_name }}</td>
                 <td class="px-4 py-2">{{ item.qty_ordered }}</td>
                 <td class="px-4 py-2">{{ item.qty_received }}</td>
                 <td class="px-4 py-2">{{ item.unit_name }}</td>
               </tr>
             </tbody>
                         <tfoot class="bg-gray-50">
               <tr>
                 <td colspan="4" class="px-4 py-2 text-right font-semibold">Total Item: {{ gr.items ? gr.items.length : 0 }}</td>
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
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  gr: Object
});



const handleClose = () => {
  window.history.length > 1 ? window.history.back() : window.location.href = '/good-receive-outlet-supplier';
};
</script> 