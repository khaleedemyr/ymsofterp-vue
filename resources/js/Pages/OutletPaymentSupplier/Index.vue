<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-money-bill-wave"></i> Outlet Payment Supplier
        </h1>
        <div class="flex gap-2 items-center">
          <button @click="goToUnpaidGRPage" class="bg-gradient-to-r from-red-400 to-red-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-clock mr-1"></i> GR Supplier Belum Dibayar
          </button>
          <button @click="goToCreatePage" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Payment Supplier
          </button>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg overflow-x-auto w-full">
        <table class="w-full min-w-full border border-gray-300">
          <thead>
            <tr class="bg-yellow-100 text-yellow-900">
              <th class="px-4 py-2 border">No. Payment</th>
              <th class="px-4 py-2 border">Tanggal</th>
              <th class="px-4 py-2 border">Outlet</th>
              <th class="px-4 py-2 border">No. GR Supplier</th>
              <th class="px-4 py-2 border text-right">Total Amount</th>
              <th class="px-4 py-2 border">Status</th>
              <th class="px-4 py-2 border">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!payments.data || !payments.data.length">
              <td colspan="7" class="text-center py-10 text-gray-400">Belum ada data Payment Supplier.</td>
            </tr>
            <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-yellow-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-yellow-700">{{ payment.payment_number }}</td>
              <td class="px-6 py-3">{{ formatDate(payment.date) }}</td>
              <td class="px-6 py-3">{{ payment.outlet_name }}</td>
              <td class="px-6 py-3">{{ payment.gr_supplier_number }}</td>
              <td class="px-6 py-3 text-right">{{ formatCurrency(payment.total_amount) }}</td>
              <td class="px-6 py-3">
                <span :class="getStatusClass(payment.status)" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ payment.status }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="viewPayment(payment)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button v-if="payment.status === 'pending'" @click="editPayment(payment)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-pencil-alt mr-1"></i> Edit
                  </button>
                  <button v-if="payment.status === 'pending'" @click="deletePayment(payment)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-trash mr-1"></i> Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in payments.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
          v-html="link.label"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-yellow-600 text-white shadow-lg' : 'bg-white text-yellow-700 hover:bg-yellow-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
          ]"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({
  payments: Object
});
function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}
function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}
function getStatusClass(status) {
  return {
    pending: 'bg-yellow-100 text-yellow-800',
    paid: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800'
  }[status] || 'bg-gray-100 text-gray-800';
}
function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}
function goToCreatePage() {
  router.get('/outlet-payment-suppliers/create');
}
function goToUnpaidGRPage() {
  router.get('/outlet-payment-suppliers/unpaid-gr');
}
function viewPayment(payment) {
  if (payment && payment.id) {
    router.get(`/outlet-payment-suppliers/${payment.id}`);
  }
}
function editPayment(payment) {
  if (payment && payment.id) {
    router.get(`/outlet-payment-suppliers/${payment.id}/edit`);
  }
}
function deletePayment(payment) {
  if (payment && payment.id) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire({
        title: 'Hapus Payment Supplier?',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          router.delete(`/outlet-payment-suppliers/${payment.id}`, {
            onSuccess: () => {
              Swal.fire('Berhasil', 'Payment supplier berhasil dihapus!', 'success');
            },
            onError: () => {
              Swal.fire('Gagal', 'Gagal menghapus Payment supplier', 'error');
            }
          });
        }
      });
    });
  }
}
</script> 