<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-blue-100 relative">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-blue-800">Payment & Invoice Details</h2>
        <button @click="$emit('close')" class="text-gray-400 hover:text-blue-600 text-2xl">×</button>
      </div>
      <!-- Lightbox Preview -->
      <div v-if="showLightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80">
        <div class="absolute top-4 right-8">
          <button @click="closeLightbox" class="text-white text-3xl font-bold">&times;</button>
        </div>
        <img :src="lightboxImg" class="max-h-[80vh] max-w-[90vw] rounded shadow-lg border-4 border-white" />
      </div>
      <div class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <h3 class="font-semibold text-blue-700 mb-1">PO Information</h3>
            <p><span class="font-bold">PO Number:</span> {{ po.po_number }}</p>
            <p><span class="font-bold">Date:</span> {{ formatDate(po.created_at) }}</p>
            <p><span class="font-bold">Total Amount:</span> {{ formatCurrency(po.total_amount) }}</p>
          </div>
          <div>
            <h3 class="font-semibold text-blue-700 mb-1">Supplier</h3>
            <p><span class="font-bold">Name:</span> {{ po.supplier?.name }}</p>
            <p><span class="font-bold">Contact:</span> {{ po.supplier?.contact_person }}</p>
            <p><span class="font-bold">Phone:</span> {{ po.supplier?.phone }}</p>
            <p><span class="font-bold">Email:</span> {{ po.supplier?.email }}</p>
          </div>
        </div>
      </div>
      <div class="mb-4">
        <h3 class="font-semibold text-blue-700 mb-2">Payments</h3>
        <table class="min-w-full text-sm mb-2">
          <thead>
            <tr class="bg-blue-50">
              <th class="px-2 py-1 text-left">Amount</th>
              <th class="px-2 py-1 text-left">Date</th>
              <th class="px-2 py-1 text-left">Method</th>
              <th class="px-2 py-1 text-left">Proof</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pay in po.payments" :key="pay.id">
              <td class="px-2 py-1">{{ formatCurrency(pay.payment_amount) }}</td>
              <td class="px-2 py-1">{{ formatDate(pay.payment_date) }}</td>
              <td class="px-2 py-1">{{ pay.payment_method }}</td>
              <td class="px-2 py-1">
                <template v-if="isImage(pay.payment_proof_path)">
                  <img :src="getProofUrl(pay.payment_proof_path)" class="w-14 h-14 object-cover rounded cursor-pointer border hover:scale-105 transition" @click="openLightbox(getProofUrl(pay.payment_proof_path))" alt="Proof" />
                </template>
                <template v-else>
                  <a v-if="pay.payment_proof_path" :href="getProofUrl(pay.payment_proof_path)" target="_blank" class="text-blue-600 underline">View</a>
                  <span v-else class="text-gray-400">-</span>
                </template>
              </td>
            </tr>
            <tr v-if="!po.payments || !po.payments.length">
              <td colspan="4" class="text-center text-gray-400 py-2">No payments</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div>
        <h3 class="font-semibold text-blue-700 mb-2">Invoices</h3>
        <div v-if="po.invoices && po.invoices.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div v-for="inv in po.invoices" :key="inv.id" class="border rounded-lg p-2 flex flex-col items-center bg-blue-50">
            <template v-if="isImage(inv.invoice_file_path)">
              <img :src="getInvoiceUrl(inv.invoice_file_path)" class="w-32 h-24 object-cover rounded mb-1 cursor-pointer hover:scale-105 transition" @click="openLightbox(getInvoiceUrl(inv.invoice_file_path))" alt="Invoice" />
            </template>
            <template v-else>
              <a :href="getInvoiceUrl(inv.invoice_file_path)" target="_blank" class="text-blue-600 underline flex flex-col items-center">
                <i class="fas fa-file-alt text-2xl mb-1"></i>
                <span>File</span>
              </a>
            </template>
            <div class="text-xs text-blue-900 font-semibold mt-1">{{ inv.invoice_number }}</div>
            <div class="text-xs text-gray-500">{{ formatDate(inv.invoice_date) }}</div>
          </div>
        </div>
        <div v-else class="text-gray-400 text-sm">No invoices</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
const props = defineProps({
  po: Object
});

const showLightbox = ref(false);
const lightboxImg = ref('');

function openLightbox(img) {
  lightboxImg.value = img;
  showLightbox.value = true;
}
function closeLightbox() {
  showLightbox.value = false;
  lightboxImg.value = '';
}

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return isNaN(d) ? '-' : d.toLocaleDateString('id-ID');
}
function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(amount);
}
function isImage(path) {
  return /\.(jpg|jpeg|png|gif|webp)$/i.test(path || '');
}
function getInvoiceUrl(path) {
  if (!path) return '';
  // Path dari database, cukup tambahkan /storage/
  return '/storage/' + path.replace(/^\/?/, '');
}
function getProofUrl(path) {
  if (!path) return '';
  // Path dari database, cukup tambahkan /storage/
  return '/storage/' + path.replace(/^\/?/, '');
}
</script> 