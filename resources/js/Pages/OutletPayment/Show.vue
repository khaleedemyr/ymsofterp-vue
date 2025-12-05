<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Detail Outlet Payment</h1>
        <button @click="router.get('/outlet-payments')" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md font-semibold hover:bg-gray-700">
          <span class="mr-2"><i class="fas fa-arrow-left"></i></span>
          Kembali
        </button>
      </div>

      <!-- Info Cards (GR, DO, Packing List, RO) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 my-6">
        <!-- Info GR -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl shadow-xl border border-blue-200 p-5">
          <h3 class="font-bold mb-2 text-blue-700 flex items-center gap-2">
            <i class="fa-solid fa-file-invoice"></i> Info GR
          </h3>
          <div class="text-sm">No: <span class="font-semibold">{{ payment.good_receive?.number }}</span></div>
          <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(payment.good_receive?.receive_date) }}</span></div>
          <div class="text-sm">Outlet: <span class="font-semibold">{{ payment.good_receive?.outlet?.name || payment.outlet_name }}</span></div>
          <div class="text-sm">Status: <span class="font-semibold">{{ payment.good_receive?.status }}</span></div>
          <div class="text-sm">Amount: <span class="font-bold text-blue-700">{{ formatCurrency(payment.total_amount) }}</span></div>
          <div class="text-sm">User Input: <span class="font-semibold">{{ payment.good_receive?.creator?.nama_lengkap || '-' }}</span></div>
        </div>
        <!-- Info DO -->
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl shadow-xl border border-green-200 p-5">
          <h3 class="font-bold mb-2 text-green-700 flex items-center gap-2">
            <i class="fa-solid fa-truck"></i> Info DO
          </h3>
          <div class="text-sm">No: <span class="font-semibold">{{ payment.good_receive?.delivery_order?.number || '-' }}</span></div>
          <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(payment.good_receive?.delivery_order?.created_at) }}</span></div>
          <div class="text-sm">User Input: <span class="font-semibold">{{ payment.good_receive?.delivery_order?.creator?.nama_lengkap || '-' }}</span></div>
        </div>
        <!-- Info Packing List -->
        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-2xl shadow-xl border border-yellow-200 p-5">
          <h3 class="font-bold mb-2 text-yellow-700 flex items-center gap-2">
            <i class="fa-solid fa-box"></i> Info Packing List
          </h3>
          <div class="text-sm">No: <span class="font-semibold">{{ payment.good_receive?.packing_list?.packing_number || '-' }}</span></div>
          <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(payment.good_receive?.packing_list?.created_at) }}</span></div>
          <div class="text-sm">User Input: <span class="font-semibold">{{ payment.good_receive?.packing_list?.creator?.nama_lengkap || '-' }}</span></div>
        </div>
        <!-- Info RO -->
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl shadow-xl border border-purple-200 p-5">
          <h3 class="font-bold mb-2 text-purple-700 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-list"></i> Info RO
          </h3>
          <div class="text-sm">No: <span class="font-semibold">{{ payment.good_receive?.floor_order?.order_number || '-' }}</span></div>
          <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(payment.good_receive?.floor_order?.tanggal) }}</span></div>
          <div class="text-sm">User Input: <span class="font-semibold">{{ payment.good_receive?.floor_order?.user?.nama_lengkap || '-' }}</span></div>
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
            <tr v-for="(item, index) in payment.good_receive?.items || []" :key="item.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 border border-gray-200 text-center">{{ index + 1 }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ item.item_name }}</td>
              <td class="px-4 py-2 border border-gray-200">{{ item.unit }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatQty(item.received_qty) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatCurrency(item.price) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatCurrency(item.received_qty * item.price) }}</td>
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

      <!-- Actions -->
      <div v-if="payment.status === 'pending'" class="flex justify-end gap-2 mt-6">
        <button 
          @click="updateStatus('cancelled')"
          class="px-4 py-2 bg-red-600 text-white rounded-md font-semibold hover:bg-red-700"
        >
          Batalkan Payment
        </button>
        <button 
          @click="updateStatus('paid')"
          class="px-4 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700"
        >
          Konfirmasi Pembayaran
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  payment: Object
});

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

function getStatusClass(status) {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    paid: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800'
  };
  return `px-2 py-1 rounded-full text-xs font-medium ${classes[status]}`;
}

async function updateStatus(status) {
  const Swal = (await import('sweetalert2')).default;
  let title = status === 'paid' ? 'Konfirmasi Pembayaran?' : 'Batalkan Payment?';
  let text = status === 'paid' ? 'Yakin ingin mengkonfirmasi pembayaran ini?' : 'Yakin ingin membatalkan payment ini?';
  let confirmButtonText = status === 'paid' ? 'Ya, Konfirmasi' : 'Ya, Batalkan';
  let successText = status === 'paid' ? 'Pembayaran berhasil dikonfirmasi!' : 'Payment berhasil dibatalkan!';
  let errorText = status === 'paid' ? 'Gagal mengkonfirmasi pembayaran' : 'Gagal membatalkan payment';

  const confirm = await Swal.fire({
    title,
    text,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;

  Swal.fire({
    title: 'Memproses...',
    allowOutsideClick: false,
    didOpen: () => { Swal.showLoading(); }
  });

  try {
    await router.put(`/outlet-payments/${props.payment.id}/status`, { status }, {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Berhasil', text: successText });
      },
      onError: () => {
        Swal.fire({ icon: 'error', title: 'Gagal', text: errorText });
      }
    });
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: errorText });
  }
}
</script> 