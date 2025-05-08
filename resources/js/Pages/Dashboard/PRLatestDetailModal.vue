<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto relative animate-fadeIn">
      <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-xl z-10">
        <i class="fas fa-times"></i>
      </button>
      <div class="p-6">
        <h2 class="text-xl font-bold mb-4">Detail Purchase Requisition</h2>
        <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-2">
          <div><span class="font-semibold">PR Number:</span> {{ pr.pr_number }}</div>
          <div><span class="font-semibold">Task Number:</span> {{ pr.task_number || '-' }}</div>
          <div><span class="font-semibold">Outlet:</span> {{ pr.nama_outlet || '-' }}</div>
          <div><span class="font-semibold">Status:</span> {{ pr.status }}</div>
          <div><span class="font-semibold">Total:</span> {{ formatRupiah(pr.total_amount) }}</div>
          <div><span class="font-semibold">Tanggal:</span> {{ pr.created_at ? pr.created_at.substring(0,10) : '-' }}</div>
          <div><span class="font-semibold">Created By:</span> {{ pr.created_by || '-' }}</div>
        </div>
        <div class="mb-3"><span class="font-semibold">Notes:</span> {{ pr.notes || '-' }}</div>
        <div class="mb-3"><span class="font-semibold">Description:</span> {{ pr.description || '-' }}</div>
        <div class="mb-3"><span class="font-semibold">Specifications:</span> {{ pr.specifications || '-' }}</div>
        <div class="mb-4">
          <div class="font-semibold mb-1">Approval Info:</div>
          <ul class="text-xs ml-2">
            <li>
              Chief Engineering: {{ pr.chief_engineering_approval }}
              <span v-if="pr.chief_engineering_approver"> ({{ pr.chief_engineering_approver }})</span>
              <div v-if="pr.chief_engineering_approval_notes" class="text-gray-500">Notes: {{ pr.chief_engineering_approval_notes }}</div>
            </li>
            <li>
              COO: {{ pr.coo_approval }}
              <span v-if="pr.coo_approver"> ({{ pr.coo_approver }})</span>
              <div v-if="pr.coo_approval_notes" class="text-gray-500">Notes: {{ pr.coo_approval_notes }}</div>
            </li>
            <li>
              CEO: {{ pr.ceo_approval }}
              <span v-if="pr.ceo_approver"> ({{ pr.ceo_approver }})</span>
              <div v-if="pr.ceo_approval_notes" class="text-gray-500">Notes: {{ pr.ceo_approval_notes }}</div>
            </li>
          </ul>
        </div>
        <div class="mb-4">
          <div class="font-semibold mb-1">Items:</div>
          <table class="min-w-full text-xs rounded-xl overflow-hidden">
            <thead class="bg-gray-100">
              <tr>
                <th class="py-1 px-2 text-left">Nama Barang</th>
                <th class="py-1 px-2 text-right">Qty</th>
                <th class="py-1 px-2 text-right">Harga</th>
                <th class="py-1 px-2 text-right">Subtotal</th>
                <th class="py-1 px-2 text-left">Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in pr.items" :key="item.id">
                <td class="py-1 px-2">{{ item.item_name }}</td>
                <td class="py-1 px-2 text-right">{{ item.quantity }}</td>
                <td class="py-1 px-2 text-right">{{ formatRupiah(item.price) }}</td>
                <td class="py-1 px-2 text-right">{{ formatRupiah(item.subtotal) }}</td>
                <td class="py-1 px-2">{{ item.notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
const props = defineProps({ pr: Object, show: Boolean });
const emit = defineEmits(['close']);
function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script> 