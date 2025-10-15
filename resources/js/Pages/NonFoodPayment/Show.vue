<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-eye"></i> Detail Non Food Payment
        </h1>
        <div class="flex gap-2">
          <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-arrow-left mr-1"></i> Kembali
          </button>
          <button v-if="payment.status === 'pending'" @click="editPayment" class="bg-yellow-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-pencil-alt mr-1"></i> Edit
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Payment Information -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Basic Information -->
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Payment</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Payment Number</label>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ payment.payment_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <span :class="getStatusClass(payment.status)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
                  {{ getStatusText(payment.status) }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Supplier</label>
                <p class="mt-1 text-gray-900">{{ payment.supplier?.name || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Amount</label>
                <p class="mt-1 text-lg font-bold text-green-600">{{ formatCurrency(payment.amount) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                <span :class="getPaymentMethodClass(payment.payment_method)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
                  {{ getPaymentMethodText(payment.payment_method) }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Payment Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate(payment.payment_date) }}</p>
              </div>
              <div v-if="payment.due_date">
                <label class="block text-sm font-medium text-gray-700">Due Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate(payment.due_date) }}</p>
              </div>
              <div v-if="payment.reference_number">
                <label class="block text-sm font-medium text-gray-700">Reference Number</label>
                <p class="mt-1 text-gray-900">{{ payment.reference_number }}</p>
              </div>
            </div>
            
            <div v-if="payment.description" class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <p class="mt-1 text-gray-900">{{ payment.description }}</p>
            </div>
            
            <div v-if="payment.notes" class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Notes</label>
              <p class="mt-1 text-gray-900">{{ payment.notes }}</p>
            </div>
          </div>

          <!-- Purchase Order Information -->
          <div v-if="payment.purchase_order_ops" class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Purchase Order Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">PO Number</label>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ payment.purchase_order_ops.number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">PO Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate(payment.purchase_order_ops.date) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">PO Status</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                  {{ payment.purchase_order_ops.status }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Grand Total</label>
                <p class="mt-1 text-lg font-bold text-blue-600">{{ formatCurrency(payment.purchase_order_ops.grand_total) }}</p>
              </div>
            </div>

            <!-- PO Items -->
            <div v-if="payment.purchase_order_ops.items && payment.purchase_order_ops.items.length > 0" class="mt-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-3">PO Items</h3>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in payment.purchase_order_ops.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.quantity }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(item.price) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ formatCurrency(item.total) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Purchase Requisition Information -->
          <div v-if="payment.purchase_requisition" class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Purchase Requisition Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">PR Number</label>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ payment.purchase_requisition.pr_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">PR Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate(payment.purchase_requisition.date) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <p class="mt-1 text-gray-900">{{ payment.purchase_requisition.title || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Amount</label>
                <p class="mt-1 text-lg font-bold text-green-600">{{ formatCurrency(payment.purchase_requisition.amount) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                  {{ payment.purchase_requisition.status }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Priority</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800">
                  {{ payment.purchase_requisition.priority }}
                </span>
              </div>
            </div>
            
            <div v-if="payment.purchase_requisition.description" class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <p class="mt-1 text-gray-900">{{ payment.purchase_requisition.description }}</p>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Actions -->
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Actions</h2>
            <div class="space-y-3">
              <button v-if="payment.status === 'pending'" @click="approvePayment" class="w-full bg-green-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa fa-check mr-2"></i> Approve
              </button>
              <button v-if="payment.status === 'pending'" @click="rejectPayment" class="w-full bg-red-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa fa-times mr-2"></i> Reject
              </button>
              <button v-if="payment.status === 'approved'" @click="markAsPaid" class="w-full bg-blue-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa fa-money-bill-wave mr-2"></i> Mark as Paid
              </button>
              <button v-if="['pending', 'approved'].includes(payment.status)" @click="cancelPayment" class="w-full bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa fa-ban mr-2"></i> Cancel
              </button>
            </div>
          </div>

          <!-- Payment Details -->
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Payment Details</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">Created By</label>
                <p class="mt-1 text-gray-900">{{ payment.creator?.nama_lengkap || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Created At</label>
                <p class="mt-1 text-gray-900">{{ formatDateTime(payment.created_at) }}</p>
              </div>
              <div v-if="payment.approved_by">
                <label class="block text-sm font-medium text-gray-700">Approved By</label>
                <p class="mt-1 text-gray-900">{{ payment.approver?.nama_lengkap || '-' }}</p>
              </div>
              <div v-if="payment.approved_at">
                <label class="block text-sm font-medium text-gray-700">Approved At</label>
                <p class="mt-1 text-gray-900">{{ formatDateTime(payment.approved_at) }}</p>
              </div>
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

const props = defineProps({
  payment: Object
});

const payment = props.payment;

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleString('id-ID', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function getStatusClass(status) {
  return {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    paid: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-800'
  }[status] || 'bg-gray-100 text-gray-800';
}

function getStatusText(status) {
  return {
    pending: 'Pending',
    approved: 'Approved',
    paid: 'Paid',
    rejected: 'Rejected',
    cancelled: 'Cancelled'
  }[status] || status;
}

function getPaymentMethodClass(method) {
  return {
    cash: 'bg-green-100 text-green-800',
    transfer: 'bg-blue-100 text-blue-800',
    check: 'bg-purple-100 text-purple-800'
  }[method] || 'bg-gray-100 text-gray-800';
}

function getPaymentMethodText(method) {
  return {
    cash: 'Cash',
    transfer: 'Transfer',
    check: 'Check'
  }[method] || method;
}

function goBack() {
  router.get('/non-food-payments');
}

function editPayment() {
  router.get(`/non-food-payments/${payment.id}/edit`);
}

function approvePayment() {
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Approve Payment?',
      text: 'Apakah Anda yakin ingin menyetujui payment ini?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Approve!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post(`/non-food-payments/${payment.id}/approve`, {}, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Payment berhasil disetujui!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menyetujui Payment', 'error');
          }
        });
      }
    });
  });
}

function rejectPayment() {
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Reject Payment?',
      text: 'Apakah Anda yakin ingin menolak payment ini?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Reject!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post(`/non-food-payments/${payment.id}/reject`, {}, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Payment berhasil ditolak!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menolak Payment', 'error');
          }
        });
      }
    });
  });
}

function markAsPaid() {
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Tandai sebagai Dibayar?',
      text: 'Apakah Anda yakin payment ini sudah dibayar?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Tandai!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post(`/non-food-payments/${payment.id}/mark-as-paid`, {}, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Payment berhasil ditandai sebagai dibayar!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menandai Payment sebagai dibayar', 'error');
          }
        });
      }
    });
  });
}

function cancelPayment() {
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Cancel Payment?',
      text: 'Apakah Anda yakin ingin membatalkan payment ini?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Cancel!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post(`/non-food-payments/${payment.id}/cancel`, {}, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Payment berhasil dibatalkan!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal membatalkan Payment', 'error');
          }
        });
      }
    });
  });
}
</script>
