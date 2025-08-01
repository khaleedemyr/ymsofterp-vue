<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-8 relative animate-fadeIn overflow-y-auto print-modal" style="max-height: 90vh;" id="revenue-report-modal">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
      <button @click="printModal" class="absolute top-4 right-16 text-gray-400 hover:text-blue-600 text-2xl font-bold" title="Print PDF">
        <i class="fa-solid fa-print"></i>
      </button>
      <div class="text-center mb-4">
        <div class="text-xl font-bold text-gray-800">Revenue Report</div>
        <div class="text-xs text-gray-400 mt-1">{{ tanggal }}</div>
      </div>
      <div class="border-b border-gray-200 mb-4"></div>
      <div class="mb-6">
        <div class="font-bold text-blue-700 mb-2">Total Sales</div>
        <div class="text-3xl font-extrabold text-blue-800 mb-4">{{ formatCurrency(totalSales) }}</div>
      </div>
      <div>
        <div class="font-bold text-green-700 mb-2">Breakdown by Payment Method</div>
        <table class="min-w-full text-sm rounded shadow mb-8">
          <thead>
            <tr class="bg-green-100 text-green-900">
              <th class="px-2 py-2 w-8"></th>
              <th class="px-3 py-2">Metode Pembayaran</th>
              <th class="px-3 py-2 text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(total, paymode) in paymentBreakdown" :key="paymode">
              <tr class="bg-white border-b last:border-b-0">
                <td class="px-2 py-2 text-center">
                  <button @click="toggleExpandPaymode(paymode)" class="focus:outline-none">
                    <i :class="expandedPaymode[paymode] ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"></i>
                  </button>
                </td>
                <td class="px-3 py-2">{{ paymode || '-' }}</td>
                <td class="px-3 py-2 text-right">{{ formatCurrency(total) }}</td>
              </tr>
              <tr v-if="expandedPaymode[paymode]">
                <td></td>
                <td colspan="2" class="bg-blue-50 px-6 py-2">
                  <div class="font-semibold mb-1">Detail {{ paymode }}</div>
                  <table class="min-w-full text-xs mb-2">
                    <thead>
                      <tr class="bg-blue-100 text-blue-900">
                        <th class="px-2 py-1">Payment Type</th>
                        <th class="px-2 py-1 text-right">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(ptotal, ptype) in paymentTypeBreakdown[paymode]" :key="ptype">
                        <td class="px-2 py-1">{{ ptype || '-' }}</td>
                        <td class="px-2 py-1 text-right">{{ formatCurrency(ptotal) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <!-- Pengeluaran Bahan Baku -->
      <div class="mb-8">
        <div class="font-bold text-red-700 mb-2">Pengeluaran Bahan Baku</div>
        <div v-if="loadingExpenses" class="text-gray-400 italic">Loading...</div>
        <div v-else-if="expenses.retail_food && expenses.retail_food.length">
          <div v-for="trx in expenses.retail_food" :key="'rf-' + trx.id" class="mb-3 border rounded-lg p-3">
            <div class="font-semibold text-gray-800 mb-2">No: {{ trx.retail_number }}</div>
            <div class="mb-2">
              <span class="font-semibold">Items:</span>
              <ul class="list-disc ml-6">
                <li v-for="item in trx.items" :key="item.id">
                  {{ item.item_name }} - {{ item.qty }} x {{ formatCurrency(item.harga_barang) }} = <span class="font-bold">{{ formatCurrency(item.subtotal) }}</span>
                </li>
              </ul>
            </div>
            <div class="flex flex-wrap gap-2 items-center mt-2">
              <span class="font-semibold">Invoice:</span>
              <template v-if="trx.invoices.length">
                <img v-for="(inv, idx) in trx.invoices" :key="idx" :src="inv.file_path" alt="Invoice" class="w-20 h-20 object-cover rounded shadow cursor-pointer" @click="previewImage(inv.file_path)" />
              </template>
              <span v-else class="italic text-gray-400">no image available</span>
            </div>
          </div>
        </div>
        <div v-else class="text-gray-400 italic">Tidak ada pengeluaran bahan baku.</div>
      </div>
      <!-- Pengeluaran Non Bahan Baku -->
      <div class="mb-8">
        <div class="font-bold text-purple-700 mb-2">Pengeluaran Non Bahan Baku</div>
        <div v-if="loadingExpenses" class="text-gray-400 italic">Loading...</div>
        <div v-else-if="expenses.retail_non_food && expenses.retail_non_food.length">
          <div v-for="trx in expenses.retail_non_food" :key="'rnf-' + trx.id" class="mb-3 border rounded-lg p-3">
            <div class="font-semibold text-gray-800 mb-2">No: {{ trx.retail_number }}</div>
            <div class="mb-2">
              <span class="font-semibold">Items:</span>
              <ul class="list-disc ml-6">
                <li v-for="item in trx.items" :key="item.id">
                  {{ item.item_name }} - {{ item.qty }} {{ item.unit }} x {{ formatCurrency(item.price) }} = <span class="font-bold">{{ formatCurrency(item.subtotal) }}</span>
                </li>
              </ul>
            </div>
            <div class="flex flex-wrap gap-2 items-center mt-2">
              <span class="font-semibold">Invoice:</span>
              <template v-if="trx.invoices.length">
                <img v-for="(inv, idx) in trx.invoices" :key="idx" :src="inv.file_path" alt="Invoice" class="w-20 h-20 object-cover rounded shadow cursor-pointer" @click="previewImage(inv.file_path)" />
              </template>
              <span v-else class="italic text-gray-400">no image available</span>
            </div>
          </div>
        </div>
        <div v-else class="text-gray-400 italic">Tidak ada pengeluaran non bahan baku.</div>
      </div>
      <!-- Nilai Setor Cash -->
      <div class="mt-8 p-4 bg-blue-50 rounded-lg border-2 border-blue-200">
        <div class="font-bold text-blue-800 mb-2">Nilai Setor Cash</div>
        <div class="text-lg">
          <div class="flex justify-between items-center">
            <span>Total Cash:</span>
            <span class="font-semibold">{{ formatCurrency(totalCash) }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span>Total Pengeluaran:</span>
            <span class="font-semibold">{{ formatCurrency(totalExpenses) }}</span>
          </div>
          <div class="border-t border-blue-300 my-2"></div>
          <div class="flex justify-between items-center text-xl font-bold text-blue-900">
            <span>Nilai Setor Cash:</span>
            <span>{{ formatCurrency(nilaiSetorCash) }}</span>
          </div>
        </div>
      </div>
      <!-- Image Preview Modal -->
      <div v-if="imagePreview" class="fixed inset-0 z-60 flex items-center justify-center bg-black bg-opacity-70" @click.self="imagePreview = null">
        <img :src="imagePreview" class="max-w-full max-h-[80vh] rounded shadow-2xl border-4 border-white" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
const props = defineProps({
  tanggal: String,
  orders: Array,
  outlets: Array
});
const totalSales = computed(() => {
  return (props.orders || []).reduce((sum, o) => sum + (Number(o.grand_total) || 0), 0);
});
const totalCash = computed(() => {
  const entries = Object.entries(paymentBreakdown.value);
  const found = entries.find(([k]) => k && k.toUpperCase() === 'CASH');
  return found ? found[1] : 0;
});
const totalExpenses = computed(() => {
  const retailFoodTotal = (expenses.value.retail_food || []).reduce((sum, rf) => sum + (Number(rf.total_amount) || 0), 0);
  const retailNonFoodTotal = (expenses.value.retail_non_food || []).reduce((sum, rnf) => sum + (Number(rnf.total_amount) || 0), 0);
  return retailFoodTotal + retailNonFoodTotal;
});
const nilaiSetorCash = computed(() => {
  return totalCash.value - totalExpenses.value;
});

// Get outlet name from orders and outlets
const outletName = computed(() => {
  if (!props.orders || !props.orders.length) return '';
  
  const kodeOutlet = props.orders[0]?.kode_outlet;
  if (!kodeOutlet || !props.outlets) return '';
  
  const found = props.outlets.find(o => o.qr_code === kodeOutlet);
  return found ? found.name : '';
});
const paymentBreakdown = computed(() => {
  const result = {};
  (props.orders || []).forEach(o => {
    if (o.payments && Array.isArray(o.payments)) {
      o.payments.forEach(p => {
        const paymode = p.payment_code || '-';
        // Ubah: cash = amount - change
        const total = (Number(p.amount) || 0) - (Number(p.change) || 0);
        result[paymode] = (result[paymode] || 0) + total;
      });
    } else if (o.payment_code) {
      const paymode = o.payment_code || '-';
      // Ubah: cash = amount - change
      const total = (Number(o.amount) || 0) - (Number(o.change) || 0);
      result[paymode] = (result[paymode] || 0) + total;
    }
  });
  return result;
});
const expandedPaymode = ref({});
function toggleExpandPaymode(paymode) {
  expandedPaymode.value[paymode] = !expandedPaymode.value[paymode];
}
// Breakdown per payment_type untuk setiap payment_code
const paymentTypeBreakdown = computed(() => {
  const result = {};
  (props.orders || []).forEach(o => {
    if (o.payments && Array.isArray(o.payments)) {
      o.payments.forEach(p => {
        const paymode = p.payment_code || '-';
        let ptype = p.payment_type;
        if (!ptype && o.payment_type) ptype = o.payment_type;
        if (!ptype) ptype = 'Unknown';
        ptype = String(ptype).toUpperCase(); // kapitalisasi
        const total = (Number(p.amount) || 0) - (Number(p.change) || 0);
        if (!result[paymode]) result[paymode] = {};
        result[paymode][ptype] = (result[paymode][ptype] || 0) + total;
      });
    } else if (o.payment_code) {
      const paymode = o.payment_code || '-';
      let ptype = o.payment_type;
      if (!ptype) ptype = 'Unknown';
      ptype = String(ptype).toUpperCase(); // kapitalisasi
      const total = (Number(o.amount) || 0) - (Number(o.change) || 0);
      if (!result[paymode]) result[paymode] = {};
      result[paymode][ptype] = (result[paymode][ptype] || 0) + total;
    }
  });
  return result;
});
const expenses = ref({ retail_food: [], retail_non_food: [] });
const loadingExpenses = ref(false);
const imagePreview = ref(null);
function previewImage(url) {
  imagePreview.value = url;
}
async function fetchExpenses() {
  console.log('fetchExpenses called', { orders: props.orders, tanggal: props.tanggal, outlets: props.outlets });
  if (!props.orders || !props.orders.length) return;
  
  // Cari kode_outlet dari salah satu order
  const kodeOutlet = props.orders[0]?.kode_outlet;
  let outletId = null;
  
  if (kodeOutlet && props.outlets) {
    const found = props.outlets.find(o => o.qr_code === kodeOutlet);
    outletId = found ? found.id : null;
  }
  
  // Jika tidak ditemukan di outlets array, coba ambil dari API
  if (!outletId && kodeOutlet) {
    try {
      const res = await fetch(`/api/outlets/report`);
      if (res.ok) {
        const data = await res.json();
        const found = data.outlets?.find(o => o.qr_code === kodeOutlet);
        outletId = found ? found.id : null;
      }
    } catch (e) {
      console.error('Error fetching outlets for outlet ID lookup:', e);
    }
  }
  
  if (!outletId || !props.tanggal) {
    console.log('fetchExpenses: missing outletId or tanggal', { outletId, tanggal: props.tanggal });
    return;
  }
  
  loadingExpenses.value = true;
  try {
    console.log('fetchExpenses: fetching', `/api/outlet-expenses?outlet_id=${encodeURIComponent(outletId)}&date=${encodeURIComponent(props.tanggal)}`);
    const res = await fetch(`/api/outlet-expenses?outlet_id=${encodeURIComponent(outletId)}&date=${encodeURIComponent(props.tanggal)}`);
    if (res.ok) {
      const data = await res.json();
      console.log('fetchExpenses: response', data);
      expenses.value = data;
    } else {
      console.log('fetchExpenses: response not ok', res.status);
      expenses.value = { retail_food: [], retail_non_food: [] };
    }
  } catch (e) {
    console.error('fetchExpenses error', e);
    expenses.value = { retail_food: [], retail_non_food: [] };
  } finally {
    loadingExpenses.value = false;
  }
}
watch(() => [props.tanggal, props.orders], fetchExpenses, { immediate: true });
function formatCurrency(val) {
  if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  if (!val) return '-';
  const num = Number(val);
  if (!isNaN(num)) return num.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  return val;
}
function formatDateIndo(dateStr) {
  if (!dateStr) return '-';
  const bulan = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  return `${d.getDate().toString().padStart(2, '0')} ${bulan[d.getMonth()]} ${d.getFullYear()}`;
}
function printModal() {
  setTimeout(() => {
    const modalContent = document.getElementById('revenue-report-modal');
    if (!modalContent) {
      alert('Modal tidak ditemukan!');
      return;
    }
    // Clone isi modal tanpa tombol
    const cleanContent = modalContent.cloneNode(true);
    const buttons = cleanContent.querySelectorAll('button, .fa-solid');
    buttons.forEach(btn => btn.remove());

    // Buka window baru
    const printWindow = window.open('', '_blank', 'width=900,height=1200');
    printWindow.document.write(`
      <html>
        <head>
          <title>Revenue Report</title>
                     <style>
             body {
               font-family: 'Segoe UI', Arial, sans-serif;
               margin: 0;
               padding: 16px 12px;
               background: #fff;
               color: #222;
               font-size: 10px;
               line-height: 1.2;
             }
             .report-title {
               font-size: 1.2rem;
               font-weight: bold;
               color: #2563eb;
               margin-bottom: 0.25rem;
               text-align: center;
             }
             .report-date {
               font-size: 0.8rem;
               color: #888;
               text-align: center;
               margin-bottom: 0.75rem;
             }
             .report-outlet {
               font-size: 0.9rem;
               color: #2563eb;
               text-align: center;
               margin-bottom: 0.5rem;
               font-weight: 600;
             }
             .summary-section {
               display: flex;
               flex-wrap: wrap;
               gap: 16px;
               margin-bottom: 1rem;
               justify-content: center;
             }
             .summary-card {
               background: #f3f6fa;
               border-radius: 6px;
               box-shadow: 0 1px 4px rgba(0,0,0,0.04);
               padding: 8px 16px;
               min-width: 120px;
               text-align: center;
             }
             .summary-label {
               font-size: 0.7rem;
               color: #666;
               margin-bottom: 0.1rem;
             }
             .summary-value {
               font-size: 1rem;
               font-weight: bold;
               color: #2563eb;
             }
             table {
               width: 100%;
               border-collapse: collapse;
               margin-bottom: 0.75rem;
               font-size: 9px;
             }
             th, td {
               padding: 4px 6px;
               border-bottom: 1px solid #e5e7eb;
             }
             th {
               background: #e0eaff;
               color: #1e293b;
               font-weight: bold;
               font-size: 9px;
             }
             .section-title {
               font-size: 0.9rem;
               font-weight: bold;
               color: #2563eb;
               margin: 1rem 0 0.25rem 0;
             }
             .expense-block {
               border: 1px solid #e5e7eb;
               border-radius: 4px;
               padding: 6px 10px;
               margin-bottom: 0.5rem;
               background: #f9fafb;
               font-size: 9px;
             }
             .expense-title {
               font-weight: bold;
               color: #222;
               font-size: 9px;
             }
             .expense-items {
               margin: 0.25rem 0 0.25rem 0.5rem;
             }
             .expense-items ul {
               margin: 0;
               padding-left: 1rem;
             }
             .expense-items li {
               margin-bottom: 0.1rem;
             }
             .expense-total {
               font-weight: bold;
               color: #2563eb;
             }
             .cash-section {
               background: #e0eaff;
               border-radius: 4px;
               padding: 8px 12px;
               margin-top: 1rem;
               font-size: 0.9rem;
             }
             .cash-row {
               display: flex;
               justify-content: space-between;
               margin-bottom: 0.25rem;
             }
             .cash-label {
               color: #222;
             }
             .cash-value {
               font-weight: bold;
             }
                           @media print {
                body { 
                  margin: 0; 
                  padding: 8px 6px;
                }
                @page {
                  margin: 0.25in;
                  size: A4;
                }
                /* Pastikan semua konten muat dalam 1 halaman */
                .section-title {
                  page-break-after: avoid;
                  page-break-inside: avoid;
                }
                .expense-block {
                  page-break-inside: avoid;
                }
                .cash-section {
                  page-break-inside: avoid;
                }
                table {
                  page-break-inside: avoid;
                }
                /* Kompres spacing lebih lanjut untuk print */
                .summary-section {
                  gap: 8px;
                  margin-bottom: 0.5rem;
                }
                .summary-card {
                  padding: 4px 8px;
                  min-width: 100px;
                }
                .expense-block {
                  padding: 4px 6px;
                  margin-bottom: 0.25rem;
                }
                .cash-section {
                  padding: 6px 8px;
                  margin-top: 0.5rem;
                }
              }
           </style>
        </head>
        <body>
          <div class="report-title">Revenue Report</div>
          ${outletName.value ? `<div class="report-outlet">${outletName.value}</div>` : ''}
          <div class="report-date">${props.tanggal || ''}</div>
                     <!-- Summary Section -->
           <div class="summary-section">
             <div class="summary-card">
               <div class="summary-label">Total Sales</div>
               <div class="summary-value">${formatCurrency(totalSales.value)}</div>
             </div>
           </div>
                     <!-- Payment Breakdown -->
           <div class="section-title">Breakdown by Payment Method</div>
           <table>
             <thead>
               <tr>
                 <th>Metode Pembayaran</th>
                 <th>Payment Type</th>
                 <th>Total</th>
               </tr>
             </thead>
             <tbody>
               ${Object.entries(paymentBreakdown.value).map(([paymode, total]) => {
                 const paymentTypes = paymentTypeBreakdown.value[paymode] || {};
                 const typeEntries = Object.entries(paymentTypes);
                 
                 if (typeEntries.length === 0) {
                   return `<tr>
                     <td>${paymode || '-'}</td>
                     <td>-</td>
                     <td style="text-align:right">${formatCurrency(total)}</td>
                   </tr>`;
                 }
                 
                 return typeEntries.map(([ptype, ptotal], index) => `
                   <tr>
                     <td>${index === 0 ? (paymode || '-') : ''}</td>
                     <td>${ptype || '-'}</td>
                     <td style="text-align:right">${formatCurrency(ptotal)}</td>
                   </tr>
                 `).join('');
               }).join('')}
             </tbody>
           </table>
          <!-- Pengeluaran Bahan Baku -->
          <div class="section-title">Pengeluaran Bahan Baku</div>
          ${(expenses.value.retail_food || []).length === 0 ? '<div style="color:#888">Tidak ada pengeluaran bahan baku.</div>' : ''}
                    ${(expenses.value.retail_food || []).map(trx => `
            <div class="expense-block">
              <div class="expense-title">No: ${trx.retail_number}</div>
              <div class="expense-items">
                <ul>
                  ${(trx.items || []).map(item => `
                    <li>${item.item_name} - ${item.qty} x ${formatCurrency(item.harga_barang)} = <span class="expense-total">${formatCurrency(item.subtotal)}</span></li>
                    `).join('')}
                </ul>
              </div>
            </div>
          `).join('')}
          <!-- Pengeluaran Non Bahan Baku -->
          <div class="section-title">Pengeluaran Non Bahan Baku</div>
          ${(expenses.value.retail_non_food || []).length === 0 ? '<div style="color:#888">Tidak ada pengeluaran non bahan baku.</div>' : ''}
                    ${(expenses.value.retail_non_food || []).map(trx => `
            <div class="expense-block">
              <div class="expense-title">No: ${trx.retail_number}</div>
              <div class="expense-items">
                <ul>
                  ${(trx.items || []).map(item => `
                    <li>${item.item_name} - ${item.qty} ${item.unit} x ${formatCurrency(item.price)} = <span class="expense-total">${formatCurrency(item.subtotal)}</span></li>
                    `).join('')}
                </ul>
              </div>
            </div>
                     `).join('')}
           <!-- Summary Section -->
           <div class="section-title">Summary</div>
           <div class="cash-section">
             <div class="cash-row"><span class="cash-label">Total Cash:</span><span class="cash-value">${formatCurrency(totalCash.value)}</span></div>
             <div class="cash-row"><span class="cash-label">Total Pengeluaran:</span><span class="cash-value">${formatCurrency(totalExpenses.value)}</span></div>
             <div class="cash-row" style="font-size:1rem;font-weight:bold;"><span class="cash-label">Nilai Setor Cash:</span><span class="cash-value">${formatCurrency(nilaiSetorCash.value)}</span></div>
           </div>
        </body>
      </html>
    `);
    printWindow.document.close();
    setTimeout(() => {
      printWindow.focus();
      printWindow.print();
      printWindow.close();
    }, 500);
  }, 100);
}
</script>

<style scoped>
/* CSS untuk animasi modal */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: none; }
}
.animate-fadeIn {
  animation: fadeIn 0.25s;
}

/* CSS untuk print */
@media print {
  /* Sembunyikan semua elemen kecuali modal */
  body > *:not(.fixed) {
    display: none !important;
  }
  
  /* Sembunyikan overlay background */
  .fixed.inset-0 {
    display: none !important;
  }
  
  /* Reset styling untuk modal saat print */
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
    z-index: auto !important;
    padding: 20px !important;
    margin: 0 !important;
    overflow: visible !important;
  }
  
  /* Sembunyikan tombol-tombol */
  .print-modal button, 
  .print-modal .fa-times, 
  .print-modal .fa-print,
  .print-modal .fa-solid {
    display: none !important;
  }
  
  /* Pastikan tabel tidak terpotong */
  .print-modal table {
    page-break-inside: avoid !important;
  }
  
  /* Pastikan div tidak terpotong */
  .print-modal div {
    page-break-inside: avoid !important;
  }
  
  /* Reset font size untuk print */
  .print-modal {
    font-size: 12px !important;
  }
  
  .print-modal .text-3xl {
    font-size: 18px !important;
  }
  
  .print-modal .text-xl {
    font-size: 16px !important;
  }
  
  .print-modal .text-lg {
    font-size: 14px !important;
  }
  
  .print-modal .text-sm {
    font-size: 11px !important;
  }
  
  .print-modal .text-xs {
    font-size: 10px !important;
  }
  
  /* Pastikan tidak ada duplikasi */
  .print-modal {
    page-break-after: avoid !important;
    page-break-before: avoid !important;
  }
  
  /* Pastikan hanya satu instance yang di-print */
  body {
    margin: 0 !important;
    padding: 0 !important;
  }
  
  /* Sembunyikan elemen yang tidak perlu */
  .print-modal .fixed.inset-0 {
    display: none !important;
  }
  
  /* Pastikan tidak ada duplikasi halaman */
  @page {
    margin: 0.5in;
  }
}
</style> 