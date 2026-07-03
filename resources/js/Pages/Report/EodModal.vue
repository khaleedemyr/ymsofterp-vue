<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div
      id="eod-report-modal"
      class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 relative animate-fadeIn print-modal"
    >
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <button
        @click="printModal"
        class="absolute top-4 right-16 text-gray-400 hover:text-blue-600 text-2xl font-bold"
        title="Print PDF"
      >
        <i class="fa-solid fa-print"></i>
      </button>
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
    const d = new Date(props.summary.tanggal + 'T09:31:00');
    return d.toLocaleString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
  const d = new Date();
  return d.toLocaleString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
});

const printTitle = computed(() => {
  const outlet = (props.summary?.nama_outlet || 'Outlet').replace(/[/\\?*[\]:"]/g, '').trim();
  const date = (props.summary?.tanggal || 'report').replace(/[/\\?*[\]:]/g, '-');
  return `EOD_${outlet.replace(/\s+/g, '_')}_${date}`;
});

function format(val) {
  if (val == null) return '-';
  return typeof val === 'number' ? val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }) : val;
}

function calcAvgCheck(sales, pax) {
  return pax > 0 ? Math.round(sales / pax) : 0;
}

function printModal() {
  const modalContent = document.getElementById('eod-report-modal');
  if (!modalContent) {
    alert('Modal tidak ditemukan!');
    return;
  }

  const clonedModal = modalContent.cloneNode(true);
  clonedModal.querySelectorAll('button').forEach((button) => button.remove());
  clonedModal.querySelectorAll('[title="Print PDF"]').forEach((node) => node.remove());
  clonedModal.style.maxHeight = 'none';
  clonedModal.style.overflow = 'visible';

  const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
    .map((node) => node.outerHTML)
    .join('\n');

  const printWindow = window.open('', '_blank', 'width=800,height=1000');
  if (!printWindow) {
    alert('Popup print diblokir browser. Izinkan popup lalu coba lagi.');
    return;
  }

  printWindow.document.write(`
    <html>
      <head>
        <title>${printTitle.value}</title>
        ${styles}
        <style>
          body { margin: 0; padding: 24px; background: #fff; font-family: system-ui, -apple-system, sans-serif; }
          .print-wrapper { max-width: 480px; margin: 0 auto; }
          .print-wrapper #eod-report-modal {
            width: 100% !important;
            max-width: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 0 !important;
          }
          @page { size: A4; margin: 12mm; }
        </style>
      </head>
      <body>
        <div class="print-wrapper">${clonedModal.outerHTML}</div>
      </body>
    </html>
  `);

  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 400);
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

@media print {
  body > *:not(.fixed) {
    display: none !important;
  }

  .fixed.inset-0 {
    display: none !important;
  }

  .print-modal {
    position: static !important;
    left: auto !important;
    top: auto !important;
    width: 100% !important;
    height: auto !important;
    max-height: none !important;
    background: #fff !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
  }

  .print-modal button,
  .print-modal .fa-times,
  .print-modal .fa-print,
  .print-modal .fa-solid {
    display: none !important;
  }
}
</style>
