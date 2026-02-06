<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 relative animate-fadeIn">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <div class="text-center mb-4">
        <div class="text-xl font-bold text-gray-800">Justus Steak House</div>
        <div class="text-sm text-gray-500">{{ summary.nama_outlet }}</div>
        <div class="text-xs text-gray-400 mt-1">{{ displayDateTime }}</div>
      </div>
      <div class="border-b border-gray-200 mb-2"></div>
      <div class="space-y-2 text-base">
        <div class="flex justify-between"><span>Sales (+)</span><span class="font-semibold">{{ format(summary.total_sales) }}</span></div>
        <div class="flex justify-between"><span>Disc (-)</span><span>{{ format(summary.total_discount) }}</span></div>
        <div class="flex justify-between"><span>Cashback (-)</span><span>{{ format(summary.total_cashback) }}</span></div>
        <div class="flex justify-between font-bold text-lg mt-2"><span>Net Sales (=)</span><span>{{ format(summary.net_sales) }}</span></div>
        <div class="flex justify-between"><span>PB1 10% (+)</span><span>{{ format(summary.total_pb1) }}</span></div>
        <div class="flex justify-between"><span>Service 5% (+)</span><span>{{ format(summary.total_service) }}</span></div>
        <div class="flex justify-between"><span>Commfee (+)</span><span>{{ format(summary.total_commfee) }}</span></div>
        <div class="flex justify-between"><span>Rounding (+)</span><span>{{ format(summary.total_rounding) }}</span></div>
        <div class="border-b border-gray-200 my-2"></div>
        <div class="flex justify-between font-bold text-lg"><span>Grand Total (=)</span><span>{{ format(summary.grand_total) }}</span></div>
        <div class="flex justify-between"><span>Jumlah Pax</span><span>{{ summary.total_pax }}</span></div>
        <div class="flex justify-between"><span>Avg Check</span><span>{{ format(calcAvgCheck(summary.grand_total, summary.total_pax)) }}</span></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
  summary: { type: Object, required: true },
  show: Boolean,
});

// Display date from summary.tanggal (clicked date) instead of current time
const displayDateTime = computed(() => {
  if (props.summary?.tanggal) {
    // Parse the YYYY-MM-DD date from summary
    const d = new Date(props.summary.tanggal + 'T09:31:00'); // Use a fixed time or extract from data
    return d.toLocaleString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
  // Fallback to current date if tanggal is not provided
  const d = new Date();
  return d.toLocaleString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
});

function format(val) {
  if (val == null) return '-';
  return typeof val === 'number' ? val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }) : val;
}
function calcAvgCheck(sales, pax) {
  return pax > 0 ? Math.round(sales / pax) : 0;
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