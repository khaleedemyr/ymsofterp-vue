<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto relative animate-fadeIn">
      <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-xl z-10">
        <i class="fas fa-times"></i>
      </button>
      <div class="p-6">
        <h2 class="text-xl font-bold mb-4">Detail Purchase Order</h2>
        <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-2">
          <div><span class="font-semibold">PO Number:</span> {{ po.po_number }}</div>
          <div><span class="font-semibold">Created By:</span> {{ po.created_by || '-' }}</div>
          <div><span class="font-semibold">Supplier:</span> {{ po.supplier_name || '-' }}</div>
          <div><span class="font-semibold">Status:</span> {{ po.status }}</div>
          <div><span class="font-semibold">Total:</span> {{ formatRupiah(po.total_amount) }}</div>
          <div><span class="font-semibold">Tanggal:</span> {{ po.created_at ? po.created_at.substring(0,10) : '-' }}</div>
        </div>
        <div class="mb-3"><span class="font-semibold">Notes:</span> {{ po.notes || '-' }}</div>
        <div class="mb-4">
          <div class="font-semibold mb-1">Approval Info:</div>
          <ul class="text-xs ml-2">
            <li>
              Purchasing Manager: {{ po.purchasing_manager_approval }}
              <span v-if="po.purchasing_manager_approver"> ({{ po.purchasing_manager_approver }})</span>
              <div v-if="po.purchasing_manager_approval_notes" class="text-gray-500">Notes: {{ po.purchasing_manager_approval_notes }}</div>
            </li>
            <li>
              GM Finance: {{ po.gm_finance_approval }}
              <span v-if="po.gm_finance_approver"> ({{ po.gm_finance_approver }})</span>
              <div v-if="po.gm_finance_approval_notes" class="text-gray-500">Notes: {{ po.gm_finance_approval_notes }}</div>
            </li>
            <li>
              COO: {{ po.coo_approval }}
              <span v-if="po.coo_approver"> ({{ po.coo_approver }})</span>
              <div v-if="po.coo_approval_notes" class="text-gray-500">Notes: {{ po.coo_approval_notes }}</div>
            </li>
            <li>
              CEO: {{ po.ceo_approval }}
              <span v-if="po.ceo_approver"> ({{ po.ceo_approver }})</span>
              <div v-if="po.ceo_approval_notes" class="text-gray-500">Notes: {{ po.ceo_approval_notes }}</div>
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
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in po.items" :key="item.id">
                <td class="py-1 px-2">{{ item.item_name }}</td>
                <td class="py-1 px-2 text-right">{{ item.quantity }}</td>
                <td class="py-1 px-2 text-right">{{ formatRupiah(item.price) }}</td>
                <td class="py-1 px-2 text-right">{{ formatRupiah(item.subtotal) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mb-4">
          <div class="font-semibold mb-1">Invoice:</div>
          <div class="flex flex-row flex-wrap gap-3">
            <template v-for="(inv, idx) in po.invoices" :key="inv.id">
              <img v-if="inv.invoice_file_path" :src="'/storage/' + inv.invoice_file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(invoiceMedia, idx)" />
            </template>
          </div>
        </div>
        <div class="mb-4">
          <div class="font-semibold mb-1">Good Receive:</div>
          <div class="flex flex-row flex-wrap gap-3">
            <template v-for="(rec, idx) in po.receives" :key="rec.id">
              <img v-if="rec.file_type && rec.file_type.startsWith('image/')" :src="'/storage/' + rec.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(receiveMedia, idx)" />
              <video v-else-if="rec.file_type && rec.file_type.startsWith('video/')" :src="'/storage/' + rec.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(receiveMedia, idx)" />
            </template>
          </div>
        </div>
      </div>
      <LightboxModal :show="lightboxShow" :mediaList="lightboxMedia" :startIndex="lightboxIndex" @close="lightboxShow = false" />
    </div>
  </div>
</template>
<script setup>
import { ref, computed, watch } from 'vue';
import LightboxModal from './LightboxModal.vue';
const props = defineProps({ po: Object, show: Boolean });
const emit = defineEmits(['close']);
const lightboxShow = ref(false);
const lightboxMedia = ref([]);
const lightboxIndex = ref(0);
const invoiceMedia = computed(() => (props.po?.invoices || []).map(inv => ({ type: 'image', url: '/storage/' + inv.invoice_file_path, caption: inv.invoice_number })));
const receiveMedia = computed(() => (props.po?.receives || []).map(rec => ({ type: rec.file_type && rec.file_type.startsWith('video/') ? 'video' : 'image', url: '/storage/' + rec.file_path, caption: rec.notes || 'Good Receive' })));
function openLightbox(mediaArr, idx) {
  lightboxMedia.value = Array.isArray(mediaArr) ? mediaArr : mediaArr.value;
  lightboxIndex.value = idx;
  lightboxShow.value = true;
}
function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script>
<style scoped>
</style> 