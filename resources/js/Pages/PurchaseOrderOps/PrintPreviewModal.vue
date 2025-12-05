<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col relative">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200">
          <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-lg"></i>
          </button>
          <h3 class="text-lg font-semibold text-gray-800">Preview Purchase Order Ops</h3>
        </div>
        
        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 pt-4">
          <div id="print-area">
            <div class="text-center mb-4 print-header">
              <img src="/images/logojustusgroup.png" alt="Justus Group" class="logo-print" style="max-width: 180px; margin: 0 auto 12px; display:block;" />
              <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 8px;">PURCHASE ORDER OPS</h2>
            </div>

            <div class="mb-4 text-sm">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p><strong>No. PO:</strong> {{ po.number }}</p>
                  <p><strong>Tanggal:</strong> {{ new Date(po.date).toLocaleDateString('id-ID') }}</p>
                  <p><strong>Supplier:</strong> {{ po.supplier?.name }}</p>
                  <p><strong>Tanggal Kedatangan:</strong> {{ po.arrival_date ? new Date(po.arrival_date).toLocaleDateString('id-ID') : '-' }}</p>
                  <p><strong>Status:</strong> {{ po.status }}</p>
                </div>
                <div>
                  <p><strong>Dibuat oleh:</strong> {{ po.creator?.nama_lengkap }}</p>
                  <p><strong>Catatan:</strong> {{ po.notes || '-' }}</p>
                  <p><strong>PPN:</strong> {{ po.ppn_enabled ? 'Ya' : 'Tidak' }}</p>
                  <p><strong>Subtotal:</strong> Rp {{ new Intl.NumberFormat('id-ID').format(po.subtotal) }}</p>
                  <p><strong>PPN Amount:</strong> Rp {{ new Intl.NumberFormat('id-ID').format(po.ppn_amount || 0) }}</p>
                  <p><strong>Grand Total:</strong> Rp {{ new Intl.NumberFormat('id-ID').format(po.grand_total) }}</p>
                </div>
              </div>
            </div>

            <table class="w-full text-xs border mb-4" style="border-collapse: collapse;">
              <thead class="bg-gray-100">
                <tr>
                  <th class="border px-2 py-1">No</th>
                  <th class="border px-2 py-1">Nama Barang</th>
                  <th class="border px-2 py-1">Qty</th>
                  <th class="border px-2 py-1">Unit</th>
                  <th class="border px-2 py-1">Harga</th>
                  <th class="border px-2 py-1">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in po.items" :key="item.id">
                  <td class="border px-2 py-1 text-center">{{ index + 1 }}</td>
                  <td class="border px-2 py-1">{{ item.item_name }}</td>
                  <td class="border px-2 py-1 text-center">{{ item.quantity }}</td>
                  <td class="border px-2 py-1 text-center">{{ item.unit }}</td>
                  <td class="border px-2 py-1 text-right">{{ new Intl.NumberFormat('id-ID').format(item.price) }}</td>
                  <td class="border px-2 py-1 text-right">{{ new Intl.NumberFormat('id-ID').format(item.total) }}</td>
                </tr>
              </tbody>
            </table>

            <div class="text-right text-sm">
              <div class="mb-2">
                <span class="font-semibold">Subtotal:</span> 
                <span class="ml-4">Rp {{ new Intl.NumberFormat('id-ID').format(po.subtotal) }}</span>
              </div>
              <div v-if="po.ppn_enabled" class="mb-2">
                <span class="font-semibold">PPN (11%):</span> 
                <span class="ml-4">Rp {{ new Intl.NumberFormat('id-ID').format(po.ppn_amount || 0) }}</span>
              </div>
              <div class="border-t pt-2 font-bold text-lg">
                <span>Grand Total:</span> 
                <span class="ml-4">Rp {{ new Intl.NumberFormat('id-ID').format(po.grand_total) }}</span>
              </div>
            </div>

            <!-- Approval Flow -->
            <div v-if="po.approval_flows && po.approval_flows.length > 0" class="mt-6">
              <h4 class="font-semibold mb-2">Approval Flow:</h4>
              <div class="space-y-1 text-xs">
                <div v-for="flow in po.approval_flows" :key="flow.id" class="flex justify-between">
                  <span>Level {{ flow.approval_level }}: {{ flow.approver?.nama_lengkap }}</span>
                  <span :class="getStatusClass(flow.status)">{{ flow.status }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Footer -->
        <div class="p-6 pt-4 border-t border-gray-200 flex justify-end gap-3">
          <button 
            @click="$emit('close')" 
            class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50"
          >
            Tutup
          </button>
          <button 
            @click="printPO" 
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            <i class="fas fa-print mr-2"></i>
            Print
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  po: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['close']);

const getStatusClass = (status) => {
  const classes = {
    'PENDING': 'text-yellow-600',
    'APPROVED': 'text-green-600',
    'REJECTED': 'text-red-600'
  };
  return classes[status] || 'text-gray-600';
};

const printPO = () => {
  const printArea = document.getElementById('print-area');
  const originalContents = document.body.innerHTML;
  
  document.body.innerHTML = printArea.innerHTML;
  window.print();
  document.body.innerHTML = originalContents;
  
  // Reload the page to restore the original content
  window.location.reload();
};
</script>

<style scoped>
@media print {
  .print-header {
    page-break-inside: avoid;
  }
  
  .logo-print {
    max-width: 150px !important;
  }
  
  table {
    page-break-inside: avoid;
  }
  
  tr {
    page-break-inside: avoid;
  }
}
</style>
