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
            <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 8px;">DAFTAR BARANG UNTUK BIDDING</h2>
            <div style="color: #666; font-size: 13px; margin-bottom: 10px;">JUSTUS GROUP</div>
          </div>
          <table class="w-full text-xs border mb-4" style="border-collapse: collapse;">
            <thead class="bg-gray-100">
              <tr>
                <th class="border px-2 py-1">No</th>
                <th class="border px-2 py-1">Nama Barang</th>
                <th class="border px-2 py-1">Spesifikasi</th>
                <th class="border px-2 py-1">Qty</th>
                <th class="border px-2 py-1">Unit</th>
                <th class="border px-2 py-1">Harga Penawaran</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in items" :key="item.id">
                <td class="border px-2 py-1">{{ idx + 1 }}</td>
                <td class="border px-2 py-1">{{ item.item_name }}</td>
                <td class="border px-2 py-1">{{ item.specifications || '-' }}</td>
                <td class="border px-2 py-1">{{ item.quantity }}</td>
                <td class="border px-2 py-1">{{ item.unit_name || '-' }}</td>
                <td class="border px-2 py-1"></td>
              </tr>
            </tbody>
          </table>
          <div style="margin-top: 32px;">
            <strong>Terms & Conditions:</strong>
            <ul style="font-size: 13px; color: #444; margin-top: 8px; margin-bottom: 0; padding-left: 18px;">
              <li>Harga yang ditawarkan sudah termasuk PPN dan biaya pengiriman ke lokasi.</li>
              <li>Penawaran berlaku minimum 14 hari sejak tanggal penawaran.</li>
              <li>Barang yang ditawarkan harus sesuai dengan spesifikasi yang tertera.</li>
              <li>Keputusan pemenang tender sepenuhnya menjadi hak Justus Group.</li>
              <li>Penawaran dikirimkan kembali ke bagian purchasing beserta dokumen pendukung.</li>
            </ul>
          </div>
          <p class="text-xs text-gray-500 mt-4">Silakan isi harga penawaran pada kolom di atas dan kirimkan kembali ke bagian purchasing.</p>
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
  items: Array
});

function print() {
  const printContents = document.getElementById('print-area').innerHTML;
  const printWindow = window.open('', '', 'height=600,width=800');
  printWindow.document.write('<html><head><title>Print</title>');
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