<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-credit-card"></i> Non Food Payments
        </h1>
        <div class="flex gap-2 items-center">
          <button @click="goToCreatePage" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Payment
          </button>
        </div>
      </div>
      
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <!-- Search Box -->
        <div class="flex-1 min-w-64">
          <input 
            type="text" 
            v-model="filters.search" 
            @input="onSearchChange"
            placeholder="Cari payment number, supplier, creator, PO/PR number..." 
            class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          />
        </div>
        
        <!-- Filters -->
        <select v-model="filters.supplier" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Supplier</option>
          <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
        </select>
        <select v-model="filters.status" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="pending">Pending</option>
          <option value="pending_finance_manager">Pending GM Finance</option>
          <option value="approved">Approved</option>
          <option value="paid">Paid</option>
          <option value="rejected">Rejected</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input type="date" v-model="filters.date" @change="onFilterChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Tanggal" />
        
        <!-- Per Page Selector -->
        <select v-model="filters.per_page" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="10">10 per halaman</option>
          <option value="25">25 per halaman</option>
          <option value="50">50 per halaman</option>
          <option value="100">100 per halaman</option>
        </select>
      </div>
      
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-100 to-blue-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No. Payment</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal Payment</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No. PO/PR</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Kategori</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Creator</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Amount</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Payment Method</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!payments.data || !payments.data.length">
              <td colspan="11" class="text-center py-10 text-gray-400">Belum ada data Non Food Payment.</td>
            </tr>
            <template v-for="payment in payments.data" :key="payment.id">
              <tr v-for="(outlet, index) in payment.outlet_breakdown" :key="`${payment.id}-${outlet.outlet_id || index}`" 
                  class="hover:bg-blue-50 transition shadow-sm"
                  :class="{ 'border-t-2 border-blue-200': index > 0 }">
                <!-- Payment Number (only show on first row) -->
                <td v-if="index === 0" class="px-6 py-3 font-mono font-semibold text-blue-700" :rowspan="payment.outlet_breakdown.length">
                  {{ payment.payment_number }}
                </td>
                <!-- Payment Date (only show on first row) -->
                <td v-if="index === 0" class="px-6 py-3" :rowspan="payment.outlet_breakdown.length">
                  <span class="text-blue-600 font-medium">{{ formatDate(payment.payment_date) }}</span>
                  <div class="text-xs text-gray-500">Payment Date</div>
                </td>
                <!-- Supplier (only show on first row) -->
                <td v-if="index === 0" class="px-6 py-3" :rowspan="payment.outlet_breakdown.length">
                  {{ payment.supplier_name || '-' }}
                </td>
                <!-- PO/PR Number (only show on first row) -->
                <td v-if="index === 0" class="px-6 py-3" :rowspan="payment.outlet_breakdown.length">
                  <div v-if="payment.payment_type === 'PO'">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      <i class="fa fa-file-invoice mr-1"></i> {{ payment.po_number || '-' }}
                    </span>
                  </div>
                  <div v-else-if="payment.payment_type === 'PR'">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      <i class="fa fa-clipboard-list mr-1"></i> {{ payment.pr_number || '-' }}
                    </span>
                  </div>
                  <div v-else>
                    <span class="text-gray-400">-</span>
                  </div>
                </td>
                <!-- Outlet -->
                <td class="px-6 py-3">
                  <div class="font-medium text-gray-900">{{ outlet.outlet_name || '-' }}</div>
                  <div v-if="outlet.pr_number" class="text-xs text-gray-500">PR: {{ outlet.pr_number }}</div>
                </td>
                <!-- Category -->
                <td class="px-6 py-3">
                  <div v-if="outlet.category_name" class="space-y-1">
                    <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">{{ outlet.category_name }}</span>
                    <div v-if="outlet.category_division" class="text-xs text-gray-600">{{ outlet.category_division }}</div>
                    <div v-if="outlet.category_subcategory" class="text-xs text-gray-600">{{ outlet.category_subcategory }}</div>
                  </div>
                  <div v-else class="text-gray-400">-</div>
                </td>
                <!-- Creator (only show on first row) -->
                <td v-if="index === 0" class="px-6 py-3" :rowspan="payment.outlet_breakdown.length">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    <i class="fa fa-user mr-1"></i> {{ payment.creator_name || '-' }}
                  </span>
                </td>
                <!-- Amount -->
                <td class="px-6 py-3 text-right">
                  <div class="font-semibold text-gray-900">{{ formatCurrency(outlet.outlet_total) }}</div>
                  <div v-if="payment.outlet_breakdown.length > 1" class="text-xs text-gray-500">
                    dari {{ formatCurrency(payment.amount) }}
                  </div>
                </td>
                <!-- Payment Method (only show on first row) -->
                <td v-if="index === 0" class="px-6 py-3" :rowspan="payment.outlet_breakdown.length">
                  <span :class="getPaymentMethodClass(payment.payment_method)" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                    {{ getPaymentMethodText(payment.payment_method) }}
                  </span>
                </td>
                <!-- Status (only show on first row) -->
                <td v-if="index === 0" class="px-6 py-3" :rowspan="payment.outlet_breakdown.length">
                  <span :class="getStatusClass(payment.status, payment)" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                    {{ getStatusText(payment.status, payment) }}
                  </span>
                </td>
                <!-- Actions (only show on first row) -->
                <td v-if="index === 0" class="px-6 py-3" :rowspan="payment.outlet_breakdown.length">
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
                    <button v-if="payment.status === 'pending'" @click="approvePayment(payment)" class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition">
                      <i class="fa fa-check mr-1"></i> Approve
                    </button>
                    <button v-if="payment.status === 'approved'" @click="markAsPaid(payment)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                      <i class="fa fa-money-bill-wave mr-1"></i> Paid
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-600">
          Menampilkan {{ payments.from || 0 }} - {{ payments.to || 0 }} dari {{ payments.total || 0 }} data
        </div>
        <div class="flex gap-2">
          <button
            v-for="link in payments.links"
            :key="link.label"
            :disabled="!link.url"
            @click="goToPage(link.url)"
            v-html="link.label"
            class="px-3 py-1 rounded-lg border text-sm font-semibold"
            :class="[
              link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
              !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
            ]"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  payments: Object,
  suppliers: Array,
  filters: Object
});

const filters = ref({
  supplier: props.filters?.supplier || '',
  status: props.filters?.status || '',
  date: props.filters?.date || '',
  search: props.filters?.search || '',
  per_page: props.filters?.per_page || '10'
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

function getStatusClass(status, payment = null) {
  // If status is pending but Finance Manager already approved, show as orange (Pending GM Finance)
  if (status === 'pending' && payment && payment.approved_finance_manager_by) {
    return 'bg-orange-100 text-orange-800';
  }
  
  return {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    paid: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-800'
  }[status] || 'bg-gray-100 text-gray-800';
}

function getStatusText(status, payment = null) {
  // If status is pending but Finance Manager already approved, show as "Pending GM Finance"
  if (status === 'pending' && payment && payment.approved_finance_manager_by) {
    return 'Pending GM Finance';
  }
  
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

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function onFilterChange() {
  router.get('/non-food-payments', { ...filters.value }, { preserveState: true, replace: true });
}

// Search with debounce
let searchTimeout = null;
function onSearchChange() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get('/non-food-payments', { ...filters.value }, { preserveState: true, replace: true });
  }, 500); // 500ms debounce
}

function goToCreatePage() {
  router.get('/non-food-payments/create');
}

function viewPayment(payment) {
  if (payment && payment.id) {
    router.get(`/non-food-payments/${payment.id}`);
  }
}

function editPayment(payment) {
  if (payment && payment.id) {
    router.get(`/non-food-payments/${payment.id}/edit`);
  }
}

function deletePayment(payment) {
  if (payment && payment.id) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire({
        title: 'Hapus Payment?',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          router.delete(`/non-food-payments/${payment.id}`, {
            onSuccess: () => {
              Swal.fire('Berhasil', 'Payment berhasil dihapus!', 'success');
            },
            onError: () => {
              Swal.fire('Gagal', 'Gagal menghapus Payment', 'error');
            }
          });
        }
      });
    });
  }
}

function approvePayment(payment) {
  if (payment && payment.id) {
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
}

function markAsPaid(payment) {
  if (payment && payment.id) {
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
}
</script>
