<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-file-lines text-blue-500"></i>
        Detail Good Receive
      </h2>
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
        <i class="fa-solid fa-xmark text-2xl"></i>
      </button>
      <div class="mb-4 grid grid-cols-2 gap-4">
        <div>
          <div class="text-sm text-gray-500">Tanggal</div>
          <div class="font-medium">{{ gr.receive_date }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">No. PO</div>
          <div class="font-medium">{{ gr.po_number }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">Supplier</div>
          <div class="font-medium">{{ gr.supplier_name }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">Petugas</div>
          <div class="font-medium">{{ gr.received_by_name }}</div>
        </div>
      </div>
      <div>
        <div class="font-semibold mb-2">Daftar Item</div>
        <table class="w-full border text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="border px-2 py-1">Nama Item</th>
              <th class="border px-2 py-1">Qty Diterima</th>
              <th class="border px-2 py-1">Unit</th>
              <th class="border px-2 py-1">Harga</th>
              <th class="border px-2 py-1">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in gr.items || []" :key="item.id">
              <td class="border px-2 py-1">{{ item.item_name }}</td>
              <td class="border px-2 py-1">{{ item.qty_received }}</td>
              <td class="border px-2 py-1">{{ item.unit_name }}</td>
              <td class="border px-2 py-1 text-right">{{ formatRupiah(item.price) }}</td>
              <td class="border px-2 py-1 text-right">{{ formatRupiah(item.qty_received * item.price) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  show: Boolean,
  gr: Object
});
const emit = defineEmits(['close']);
function formatRupiah(val) {
  if (!val) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script> 