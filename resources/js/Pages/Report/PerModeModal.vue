<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-8 relative animate-fadeIn overflow-y-auto" style="max-height: 90vh;">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <div class="text-center mb-4">
        <div class="text-xl font-bold text-gray-800">Rekap Per Mode</div>
        <div class="text-xs text-gray-400 mt-1">{{ tanggal }}</div>
      </div>
      <div class="border-b border-gray-200 mb-4"></div>
      <div class="mb-6">
        <div class="font-bold text-blue-700 mb-2">Per Mode Transaksi</div>
        <table class="min-w-full text-sm rounded shadow">
          <thead>
            <tr class="bg-blue-100 text-blue-900">
              <th class="px-3 py-2">Mode</th>
              <th class="px-3 py-2 text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(total, mode) in perModeTransaksi" :key="mode" class="bg-white border-b last:border-b-0">
              <td class="px-3 py-2">{{ mode || '-' }}</td>
              <td class="px-3 py-2 text-right">{{ formatCurrency(total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div>
        <div class="font-bold text-green-700 mb-2">Per Mode Pembayaran</div>
        <table class="min-w-full text-sm rounded shadow">
          <thead>
            <tr class="bg-green-100 text-green-900">
              <th class="px-3 py-2">Metode Pembayaran</th>
              <th class="px-3 py-2 text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(total, paymode) in perModePembayaran" :key="paymode" class="bg-white border-b last:border-b-0">
              <td class="px-3 py-2">{{ paymode || '-' }}</td>
              <td class="px-3 py-2 text-right">{{ formatCurrency(total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
  tanggal: String,
  orders: Array,
});
// Rekap per mode transaksi
const perModeTransaksi = computed(() => {
  const result = {};
  (props.orders || []).forEach(o => {
    const mode = o.mode || '-';
    result[mode] = (result[mode] || 0) + (Number(o.grand_total) || 0);
  });
  return result;
});
// Rekap per mode pembayaran
const perModePembayaran = computed(() => {
  const result = {};
  (props.orders || []).forEach(o => {
    if (o.payments && Array.isArray(o.payments)) {
      o.payments.forEach(p => {
        const paymode = p.payment_code || '-';
        result[paymode] = (result[paymode] || 0) + (Number(p.amount) || 0);
      });
    } else if (o.payment_code) {
      // fallback jika hanya ada 1 field
      const paymode = o.payment_code || '-';
      result[paymode] = (result[paymode] || 0) + (Number(o.amount) || 0);
    }
  });
  return result;
});
function formatCurrency(val) {
  if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  if (!val) return '-';
  const num = Number(val);
  if (!isNaN(num)) return num.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  return val;
}
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: none; }
}
.animate-fadeIn {
  animation: fadeIn 0.25s;
}
</style> 