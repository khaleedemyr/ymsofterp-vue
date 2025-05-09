<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
        <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div id="print-area">
          <div class="text-center mb-4">
            <img src="/images/logojustusgroup.png" alt="Justus Group" style="max-width: 180px; margin: 0 auto 12px;" />
            <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 8px;">PURCHASE ORDER</h2>
            <div style="color: #666; font-size: 13px; margin-bottom: 10px;">JUSTUS GROUP</div>
          </div>

          <div class="mb-4 text-sm">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p><strong>No. PO:</strong> {{ po.number }}</p>
                <p><strong>Tanggal:</strong> {{ new Date(po.date).toLocaleDateString('id-ID') }}</p>
                <p><strong>Supplier:</strong> {{ po.supplier?.name }}</p>
              </div>
              <div>
                <p><strong>Status:</strong> {{ po.status }}</p>
                <p><strong>Dibuat oleh:</strong> {{ po.creator?.nama_lengkap }}</p>
                <p><strong>Catatan:</strong> {{ po.notes || '-' }}</p>
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
              <tr v-for="(item, idx) in po.items" :key="item.id">
                <td class="border px-2 py-1">{{ idx + 1 }}</td>
                <td class="border px-2 py-1">{{ item.item?.name }}</td>
                <td class="border px-2 py-1">{{ item.quantity }}</td>
                <td class="border px-2 py-1">{{ item.unit?.name }}</td>
                <td class="border px-2 py-1 text-right">{{ formatPrice(item.price) }}</td>
                <td class="border px-2 py-1 text-right">{{ formatPrice(item.total) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="border px-2 py-1 text-right"><strong>Total:</strong></td>
                <td class="border px-2 py-1 text-right"><strong>{{ formatPrice(po.items.reduce((sum, item) => sum + item.total, 0)) }}</strong></td>
              </tr>
            </tfoot>
          </table>

          <div class="grid grid-cols-2 gap-4 mt-8">
            <div>
              <p class="text-sm font-bold mb-2">Dibuat oleh,</p>
              <div class="mt-12">
                <p class="text-sm font-bold">{{ po.creator?.nama_lengkap }}</p>
                <p class="text-xs">{{ po.creator?.jabatan?.nama_jabatan }}</p>
              </div>
            </div>
            <div>
              <p class="text-sm font-bold mb-2">Diterima oleh,</p>
              <div class="mt-12">
                <p class="text-sm font-bold">(___________________)</p>
                <p class="text-xs">Supplier</p>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button @click="$emit('close')" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Tutup</button>
          <button @click="print" class="px-4 py-2 text-white bg-blue-500 rounded hover:bg-blue-600">Print</button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
const props = defineProps({
  show: Boolean,
  po: Object
});

function formatPrice(price) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(price);
}

function print() {
  const printContents = document.getElementById('print-area').innerHTML;
  const printWindow = window.open('', '', 'height=600,width=800');
  printWindow.document.write('<html><head><title>Print PO</title>');
  printWindow.document.write('<style>body{font-family:Arial,sans-serif;}table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;}</style>');
  printWindow.document.write('</head><body>');
  printWindow.document.write(printContents);
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
  printWindow.close();
}
</script> 