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
        <div class="space-y-3">
          <div v-for="cb in payment.contra_bons" :key="cb.id" class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-3">
                <span class="font-semibold text-lg">{{ cb.number }}</span>
                <span v-if="cb.source_type_display === 'PR Foods'" class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">
                  🔵 PR Foods
                </span>
                <span v-else-if="cb.source_type_display === 'RO Supplier'" class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                  🟢 RO Supplier
                </span>
                <span v-else-if="cb.source_type_display === 'Retail Food'" class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm font-semibold">
                  🟣 Retail Food
                </span>
                <span v-else class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-semibold">
                  ⚪ Unknown
                </span>
              </div>
              <div class="text-right">
                <div class="font-bold text-lg text-blue-700">{{ formatCurrency(cb.total_amount) }}</div>
                <div class="text-sm text-gray-500">{{ cb.status }}</div>
              </div>
            </div>
            <div v-if="cb.outlet_names && cb.outlet_names.length > 0" class="text-sm text-orange-600 mt-2">
              <i class="fa fa-map-marker-alt mr-1"></i>
              <strong>Outlet:</strong> {{ cb.outlet_names.join(', ') }}
            </div>
            <div v-if="cb.supplier_invoice_number" class="text-sm text-gray-600 mt-1">
              <strong>No. Invoice:</strong> {{ cb.supplier_invoice_number }}
            </div>
          </div>
        </div>
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