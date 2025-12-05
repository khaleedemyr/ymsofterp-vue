<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col relative">
        <!-- Header -->
        <div class="p-6 pb-4 border-b border-gray-200">
          <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-lg"></i>
          </button>
          <h3 class="text-lg font-semibold text-gray-800">Preview Purchase Order</h3>
        </div>
        
        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 pt-4">
          <div id="print-area">
            <div class="text-center mb-4 print-header">
              <img src="/images/logojustusgroup.png" alt="Justus Group" class="logo-print" style="max-width: 180px; margin: 0 auto 12px; display:block;" />
              <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 8px;">PURCHASE ORDER</h2>
            </div>

            <div class="mb-4 text-sm">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p><strong>No. PO:</strong> {{ po.number }}</p>
                  <p><strong>Tanggal:</strong> {{ new Date(po.date).toLocaleDateString('id-ID') }}</p>
                  <p><strong>Supplier:</strong> {{ po.supplier?.name }}</p>
                  <p><strong>Tanggal Kedatangan:</strong> {{ po.arrival_date ? new Date(po.arrival_date).toLocaleDateString('id-ID') : '-' }}</p>
                  <p><strong>Outlet Pengiriman:</strong> {{ selectedOutlet?.nama_outlet || '-' }}</p>
                  <p><strong>Alamat Pengiriman:</strong> {{ selectedOutlet?.lokasi || '-' }}</p>
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
                  <td colspan="5" class="border px-2 py-1 text-right"><strong>Subtotal:</strong></td>
                  <td class="border px-2 py-1 text-right"><strong>{{ formatPrice(po.subtotal || po.items.reduce((sum, item) => sum + (Number(item.total) || 0), 0)) }}</strong></td>
                </tr>
                <tr v-if="po.ppn_enabled">
                  <td colspan="5" class="border px-2 py-1 text-right"><strong>PPN (11%):</strong></td>
                  <td class="border px-2 py-1 text-right"><strong>{{ formatPrice(po.ppn_amount || 0) }}</strong></td>
                </tr>
                <tr>
                  <td colspan="5" class="border px-2 py-1 text-right"><strong>Grand Total:</strong></td>
                  <td class="border px-2 py-1 text-right"><strong>{{ formatPrice(po.grand_total || calculateGrandTotal()) }}</strong></td>
                </tr>
              </tfoot>
            </table>

            <table style="width:100%; margin-top: 40px;">
              <tr>
                <td style="vertical-align:top; width:60%;">
                  <p class="text-sm font-bold mb-2">Dibuat oleh,</p>
                  <div style="margin-top:48px;">
                    <p class="text-sm font-bold">{{ po.creator?.nama_lengkap }}</p>
                    <p class="text-xs">{{ po.creator?.jabatan?.nama_jabatan }}</p>
                  </div>
                </td>
                <td style="vertical-align:top; text-align:right; width:40%;">
                  <div id="qrcode"></div>
                </td>
              </tr>
            </table>
          </div>
        </div>
        
        <!-- Footer Controls -->
        <div class="p-6 pt-4 border-t border-gray-200 bg-gray-50">
          <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2">
              <label class="text-sm font-medium text-gray-700">Pilih Outlet Pengiriman:</label>
              <select 
                v-model="selectedOutletId"
                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
              >
                <option value="">Pilih Outlet</option>
                <option v-for="outlet in activeOutlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
            </div>
            <div class="flex justify-end gap-2">
              <button @click="$emit('close')" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Tutup</button>
              <button @click="print" class="px-4 py-2 text-white bg-blue-500 rounded hover:bg-blue-600">Print</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { onMounted, watch, ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  po: Object
});

const activeOutlets = ref([]);
const selectedOutletId = ref('');
const selectedOutlet = computed(() =>
  activeOutlets.value.find(o => o.id_outlet == selectedOutletId.value)
);

// Fetch active outlets
const fetchOutlets = async () => {
  try {
    const response = await axios.get(route('outlets.list'));
    activeOutlets.value = response.data.filter(outlet => outlet.status === 'A');
  } catch (error) {
    console.error('Error fetching outlets:', error);
  }
};

const formatPrice = (value) => {
  if (typeof value !== 'number') value = Number(value) || 0;
  return 'Rp ' + value.toLocaleString('id-ID');
}

const calculateGrandTotal = () => {
  const subtotal = props.po.subtotal || props.po.items.reduce((sum, item) => sum + (Number(item.total) || 0), 0);
  if (props.po.ppn_enabled) {
    const ppnAmount = props.po.ppn_amount || (subtotal * 0.11);
    return subtotal + ppnAmount;
  }
  return subtotal;
};

function generateQRCode() {
  if (!props.po?.number) return;
  const qrDiv = document.getElementById('qrcode');
  if (qrDiv) qrDiv.innerHTML = '';
  const scriptId = 'qrcodejs-cdn';
  if (!document.getElementById(scriptId)) {
    const script = document.createElement('script');
    script.id = scriptId;
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
    script.onload = () => {
      new window.QRCode(qrDiv, {
        text: props.po.number,
        width: 80,
        height: 80
      });
    };
    document.body.appendChild(script);
  } else {
    new window.QRCode(qrDiv, {
      text: props.po.number,
      width: 80,
      height: 80
    });
  }
}

watch(() => props.show, (val) => {
  if (val) {
    setTimeout(generateQRCode, 200);
    fetchOutlets();
  }
});

onMounted(() => {
  if (props.show) {
    setTimeout(generateQRCode, 200);
    fetchOutlets();
  }
});

function print() {
  const printContents = document.getElementById('print-area').innerHTML;
  const printWindow = window.open('', '', 'height=600,width=800');
  printWindow.document.write('<html><head><title>Print PO</title>');
  printWindow.document.write(
    '<style>' +
      'body{font-family:Arial,sans-serif;}' +
      'table{border-collapse:collapse;width:100%;}' +
      'th,td{border:1px solid #ddd;padding:8px;}' +
      '.print-header { text-align:center; }' +
      '.logo-print { max-width:180px; margin:0 auto 12px; display:block; }' +
      '@media print {' +
        '.print-header { display:block !important; }' +
        '.logo-print { display:block !important; }' +
      '}' +
    '</style>' +
    "<script src='https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'><\/script>"
  );
  printWindow.document.write('</head><body>');
  printWindow.document.write(printContents);
  printWindow.document.write(
    "<script>" +
      "var qrDiv = document.getElementById('qrcode');" +
      "if(qrDiv && !qrDiv.hasChildNodes()) {" +
        "new QRCode(qrDiv, { text: '" + props.po.number + "', width: 80, height: 80 });" +
      "}" +
    "<\/script>"
  );
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
    // Setelah print, catat waktu print ke backend
    axios.post(route('po-foods.mark-printed', props.po.id)).then(res => {
      if (res.data.printed_at) {
        props.po.printed_at = res.data.printed_at;
      }
    });
  }, 500);
}
</script> 