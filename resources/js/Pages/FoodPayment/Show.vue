<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-money-bill-transfer text-blue-500"></i> Detail Food Payment
        </h1>
      </div>
      <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <div class="mb-2"><span class="font-semibold">Nomor:</span> {{ payment.number }}</div>
            <div class="mb-2"><span class="font-semibold">Tanggal:</span> {{ formatDate(payment.date) }}</div>
            <div class="mb-2"><span class="font-semibold">Supplier:</span> {{ payment.supplier?.name }}</div>
            <div class="mb-2"><span class="font-semibold">Payment Type:</span> {{ payment.payment_type }}</div>
            <div class="mb-2"><span class="font-semibold">Status:</span> <span :class="statusClass(payment.status)">{{ payment.status }}</span></div>
          </div>
          <div>
            <div class="mb-2"><span class="font-semibold">Total:</span> {{ formatCurrency(payment.total) }}</div>
            <div class="mb-2"><span class="font-semibold">Notes:</span> {{ payment.notes || '-' }}</div>
            <div class="mb-2"><span class="font-semibold">Dibuat oleh:</span> {{ payment.creator?.nama_lengkap }}</div>
          </div>
        </div>
        <div class="mb-4">
          <span class="font-semibold">Bukti Transfer:</span>
          <div v-if="payment.bukti_transfer_path">
            <img v-if="isImage(payment.bukti_transfer_path)" :src="`/storage/${payment.bukti_transfer_path}`" class="max-w-xs rounded shadow mt-2" />
            <a v-else :href="`/storage/${payment.bukti_transfer_path}`" target="_blank" class="text-blue-500 hover:underline mt-2 inline-block">
              <i class="fas fa-file-pdf mr-1"></i> Preview PDF
            </a>
          </div>
          <div v-else class="text-gray-400">Tidak ada bukti transfer</div>
        </div>
      </div>
      <div class="bg-blue-50 rounded-lg shadow p-6 mb-6">
        <h3 class="font-bold mb-2">Daftar Contra Bon yang Dibayar</h3>
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-blue-100">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Nomor</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
              <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cb in payment.contra_bons" :key="cb.id">
              <td class="px-4 py-2">{{ cb.number }}</td>
              <td class="px-4 py-2">{{ formatCurrency(cb.total_amount) }}</td>
              <td class="px-4 py-2">{{ cb.status }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({ payment: Object });
function goBack() { router.visit('/food-payments'); }
function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}
function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}
function isImage(path) {
  return path && (path.endsWith('.jpg') || path.endsWith('.jpeg') || path.endsWith('.png'));
}
function statusClass(status) {
  return {
    'draft': 'bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs',
    'approved': 'bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs',
    'rejected': 'bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs',
    'paid': 'bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs',
  }[status] || '';
}
</script> 