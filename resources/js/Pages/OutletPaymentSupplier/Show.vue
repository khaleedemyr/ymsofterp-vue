<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Detail Outlet Payment Supplier</h1>
        <button @click="goBack" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md font-semibold hover:bg-gray-700">
          <span class="mr-2"><i class="fas fa-arrow-left"></i></span>
          Kembali
        </button>
      </div>
      <div v-if="payment.status === 'pending'" class="flex gap-2 mb-4">
        <button @click="confirmPayment" class="px-4 py-2 bg-green-600 text-white rounded font-semibold hover:bg-green-700 flex items-center gap-2">
          <i class="fa fa-check"></i> Konfirmasi Pembayaran
        </button>
        <button @click="cancelPayment" class="px-4 py-2 bg-red-600 text-white rounded font-semibold hover:bg-red-700 flex items-center gap-2">
          <i class="fa fa-times"></i> Batalkan Pembayaran
        </button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
        <!-- Info GR Supplier -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl shadow-xl border border-blue-200 p-5">
          <h3 class="font-bold mb-2 text-blue-700 flex items-center gap-2">
            <i class="fa-solid fa-file-invoice"></i> Info GR Supplier
          </h3>
          <div class="text-sm">No: <span class="font-semibold">{{ payment.gr_supplier?.gr_number }}</span></div>
          <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(payment.gr_supplier?.receive_date) }}</span></div>
          <div class="text-sm">Outlet: <span class="font-semibold">{{ payment.gr_supplier?.outlet_name }}</span></div>
          <div class="text-sm">Status: <span class="font-semibold">{{ payment.gr_supplier?.status }}</span></div>
          <div class="text-sm">Amount: <span class="font-bold text-blue-700">{{ formatCurrency(payment.total_amount) }}</span></div>
        </div>
        <!-- Info RO Supplier -->
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl shadow-xl border border-purple-200 p-5">
          <h3 class="font-bold mb-2 text-purple-700 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-list"></i> Info RO Supplier
          </h3>
          <div class="text-sm">No: <span class="font-semibold">{{ payment.gr_supplier?.ro_number }}</span></div>
          <!-- Tambahkan field lain dari RO Supplier jika perlu -->
        </div>
      </div>
      <!-- Items Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto w-full">
        <table class="w-full min-w-full border border-gray-300">
          <thead>
            <tr class="bg-yellow-300 text-gray-900">
              <th class="px-4 py-2 border border-gray-300">No</th>
              <th class="px-4 py-2 border border-gray-300">Item</th>
              <th class="px-4 py-2 border border-gray-300">Unit</th>
              <th class="px-4 py-2 border border-gray-300">Qty</th>
              <th class="px-4 py-2 border border-gray-300">Price</th>
              <th class="px-4 py-2 border border-gray-300">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in payment.items || []" :key="item.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 border border-gray-200 text-center">{{ index + 1 }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ item.item_name }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ item.unit_name }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatQty(item.qty_received) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatCurrency(item.price) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatCurrency(item.qty_received * item.price) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-50">
              <td colspan="5" class="px-4 py-2 border border-gray-200 text-right font-semibold">Total</td>
              <td class="px-4 py-2 border border-gray-200 text-right font-semibold">{{ formatCurrency(payment.total_amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  payment: Object
});
const payment = props.payment;

function goBack() {
  router.get('/outlet-payment-suppliers');
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID');
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(amount);
}

function formatQty(qty) {
  return Number(qty).toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

async function confirmPayment() {
  const result = await Swal.fire({
    title: 'Konfirmasi Pembayaran?',
    text: 'Yakin ingin mengkonfirmasi pembayaran ini? Status akan menjadi PAID.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Konfirmasi',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
  router.put(`/outlet-payment-suppliers/${payment.id}/status`, { status: 'paid' }, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Pembayaran dikonfirmasi', timer: 1500, showConfirmButton: false });
      router.reload();
    },
    onError: (err) => {
      let msg = 'Gagal update status';
      if (err && err.response && err.response.data && err.response.data.message) {
        msg = err.response.data.message;
      } else if (err && err.message) {
        msg = err.message;
      }
      Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
    }
  });
}

async function cancelPayment() {
  const result = await Swal.fire({
    title: 'Batalkan Pembayaran?',
    text: 'Yakin ingin membatalkan pembayaran ini? Status akan menjadi CANCELED.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Batalkan',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
  router.put(`/outlet-payment-suppliers/${payment.id}/status`, { status: 'canceled' }, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Pembayaran dibatalkan', timer: 1500, showConfirmButton: false });
      router.reload();
    },
    onError: (err) => {
      let msg = 'Gagal update status';
      if (err && err.response && err.response.data && err.response.data.message) {
        msg = err.response.data.message;
      } else if (err && err.message) {
        msg = err.message;
      }
      Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
    }
  });
}
</script> 