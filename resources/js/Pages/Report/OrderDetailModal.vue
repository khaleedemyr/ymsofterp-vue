<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 relative animate-fadeIn overflow-y-auto" style="max-height: 90vh;">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <div class="text-center mb-4">
        <div class="text-xl font-bold text-gray-800">Detail Order</div>
        <div class="text-xs text-gray-400 mt-1">{{ order.nomor }}</div>
      </div>
      <div class="border-b border-gray-200 mb-2"></div>
      <div class="space-y-2 text-base mb-4">
        <template v-for="(val, key) in order" :key="key">
          <div v-if="key !== 'items' && key !== 'promo' && key !== 'manual_discount_amount' && key !== 'manual_discount_reason' && key !== 'id' && key !== 'payments'" class="flex justify-between border-b last:border-b-0 py-1">
            <span class="font-semibold text-gray-600">{{ formatKey(key) }}</span>
            <span class="text-gray-800">{{ formatValue(key, val) }}</span>
          </div>
        </template>
      </div>
      <div v-if="order.payments && Array.isArray(order.payments) && order.payments.length" class="mb-4">
        <div class="font-bold text-purple-700 mb-1">Payments</div>
        <div class="bg-purple-50 rounded p-3 text-sm">
          <div v-for="(payment, idx) in order.payments" :key="idx" class="flex justify-between">
            <span class="font-semibold text-gray-600">{{ payment.payment_code }}</span>
            <span class="text-gray-800">{{ formatCurrency(paymentAmount(payment)) }}</span>
          </div>
        </div>
      </div>
      <div v-if="manualDiscountVisible" class="mb-4">
        <div class="font-bold text-red-700 mb-1">Manual Discount</div>
        <div class="flex justify-between py-1">
          <span class="font-semibold text-gray-600">Amount</span>
          <span class="text-gray-800">{{ formatCurrency(order.manual_discount_amount) }}</span>
        </div>
        <div class="flex justify-between py-1">
          <span class="font-semibold text-gray-600">Reason</span>
          <span class="text-gray-800">{{ order.manual_discount_reason || '-' }}</span>
        </div>
      </div>
      <div v-if="promoDiscountVisible" class="mb-4">
        <div class="font-bold text-green-700 mb-1">Promo Discount</div>
        <div class="flex justify-between py-1">
          <span class="font-semibold text-gray-600">Amount</span>
          <span class="text-gray-800">{{ formatCurrency(promoDiscountAmount) }}</span>
        </div>
      </div>
      <div v-if="order.items && order.items.length" class="mb-4">
        <div class="font-bold text-blue-700 mb-2">Items</div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm rounded shadow">
            <thead>
              <tr class="bg-blue-100 text-blue-900">
                <th class="px-3 py-2">No</th>
                <th class="px-3 py-2">Nama Item</th>
                <th class="px-3 py-2">Qty</th>
                <th class="px-3 py-2">Harga</th>
                <th class="px-3 py-2">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in order.items" :key="item.id || idx" class="bg-white border-b last:border-b-0">
                <td class="px-3 py-2">{{ idx + 1 }}</td>
                <td class="px-3 py-2">{{ item.item_name || '-' }}</td>
                <td class="px-3 py-2 text-right">{{ item.qty || 0 }}</td>
                <td class="px-3 py-2 text-right">{{ formatCurrency(item.price || 0) }}</td>
                <td class="px-3 py-2 text-right">{{ formatCurrency(item.subtotal || 0) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="order.promo" class="mb-2">
        <div class="font-bold text-blue-700 mb-1">Promo</div>
        <div class="bg-blue-50 rounded p-3 text-sm">
          <div v-for="(val, key) in order.promo" :key="key" class="flex justify-between">
            <span class="font-semibold text-gray-600">{{ formatKey(key) }}</span>
            <span class="text-gray-800">{{ formatValue(key, val) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
  order: { type: Object, required: true },
});
function formatKey(key) {
  // Ubah snake_case ke Judul
  const keyMap = {
    'created_at': 'Transaction Time',
    'waiters': 'Waiter',
    'mode': 'Mode',

    'nomor': 'Order Number',
    'table': 'Table',
    'pax': 'Pax',
    'total': 'Total',
    'discount': 'Discount',
    'cashback': 'Cashback',
    'dpp': 'DPP',
    'pb1': 'PB1',
    'service': 'Service Charge',
    'grand_total': 'Grand Total',
    'status': 'Status',
    'kode_outlet': 'Outlet Code',
    'nama_outlet': 'Outlet Name',
  };
  
  return keyMap[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}
function formatValue(key, val) {
  if (key.match(/total|discount|cashback|service|pb1|grand|dpp|rounding|harga|price|subtotal/i)) {
    if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  }
  if (key === 'created_at') {
    return val ? new Date(val).toLocaleString('id-ID') : '-';
  }

  return val ?? '-';
}
function formatCurrency(val) {
  if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  if (!val) return '-';
  const num = Number(val);
  if (!isNaN(num)) return num.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  return val;
}
function paymentAmount(payment) {
  // payment.amount - payment.change (if exists)
  const amount = Number(payment.amount) || 0;
  const change = Number(payment.change) || 0;
  return amount - change;
}
const manualDiscountVisible = computed(() => {
  const val = props.order.manual_discount_amount;
  return val !== undefined && val !== null && String(val).replace(/\D/g, '') !== '' && Number(val) > 0;
});

const promoDiscountAmount = computed(() => {
  const discount = parseInt(props.order.discount) || 0;
  const manualDiscount = parseFloat(props.order.manual_discount_amount) || 0;
  
  // Jika keduanya > 0, ambil yang terbesar
  if (discount > 0 && manualDiscount > 0) {
    return Math.max(discount, manualDiscount);
  }
  // Jika hanya salah satu yang > 0, gunakan yang ada
  return discount + manualDiscount;
});

const promoDiscountVisible = computed(() => {
  return promoDiscountAmount.value > 0;
});
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