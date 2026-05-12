<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-boxes-stacked text-blue-500"></i>
          Good Receive: {{ goodReceive.gr_number }}
        </h1>
        <div class="flex gap-2">
          <button
            @click="handlePrint"
            class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition"
          >
            <i class="fa-solid fa-print mr-2"></i> Print
          </button>
          <a
            v-if="goodReceive.status === 'draft'"
            :href="`/asset-good-receives/${goodReceive.id}/edit`"
            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition"
          >
            <i class="fa-solid fa-pen-to-square mr-2"></i> Edit
          </a>
          <button
            v-if="goodReceive.status === 'draft'"
            @click="handleDelete"
            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition"
          >
            <i class="fa-solid fa-trash mr-2"></i> Delete
          </button>
          <a
            href="/asset-good-receives"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition"
          >
            <i class="fa-solid fa-arrow-left mr-2"></i> Back
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- GR Header Card -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-800">Good Receive Details</h2>
              <span
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                :class="goodReceive.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
              >
                {{ goodReceive.status.toUpperCase() }}
              </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">GR Number</label>
                <p class="text-base font-semibold text-gray-900">{{ goodReceive.gr_number }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Receive Date</label>
                <p class="text-base text-gray-900">{{ formatDate(goodReceive.receive_date) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">PO Number</label>
                <p class="text-base text-gray-900">
                  <a v-if="goodReceive.po" :href="`/po-ops/${goodReceive.po.id}`" class="text-blue-600 hover:underline font-medium">
                    {{ goodReceive.po?.number || '-' }}
                  </a>
                  <span v-else>-</span>
                </p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Supplier</label>
                <p class="text-base text-gray-900">{{ goodReceive.po?.supplier?.name || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Outlet</label>
                <p class="text-base text-gray-900">{{ goodReceive.outlet?.nama_outlet || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Warehouse</label>
                <p class="text-base text-gray-900">{{ goodReceive.warehouse_outlet?.name || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Received By</label>
                <p class="text-base text-gray-900">{{ goodReceive.receiver?.nama_lengkap || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Created At</label>
                <p class="text-base text-gray-900">{{ formatDateTime(goodReceive.created_at) }}</p>
              </div>
            </div>
            <div v-if="goodReceive.notes" class="mt-4 pt-4 border-t border-gray-200">
              <label class="text-sm font-medium text-gray-500">Notes</label>
              <p class="text-base text-gray-900 mt-1">{{ goodReceive.notes }}</p>
            </div>
          </div>

          <!-- Items Table -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa-solid fa-list mr-2 text-indigo-500"></i> Items
            </h2>
            <div class="overflow-x-auto">
              <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">No</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Item Name</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-blue-700 uppercase">Unit</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase">Qty Ordered</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase">Qty Received</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase">Price</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase">Total</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="(item, idx) in goodReceive.items" :key="item.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-600">{{ idx + 1 }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ item.item_name || item.po_item?.item_name || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-center">{{ item.unit_name || item.po_item?.unit || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ parseFloat(item.qty_ordered).toLocaleString('id-ID') }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 text-right">{{ parseFloat(item.qty_received).toLocaleString('id-ID') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ formatCurrency(item.price) }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 text-right">{{ formatCurrency(item.total) }}</td>
                  </tr>
                </tbody>
                <tfoot class="bg-gray-50">
                  <tr>
                    <td colspan="6" class="px-4 py-3 text-right text-sm font-bold text-gray-700">Grand Total</td>
                    <td class="px-4 py-3 text-right text-base font-bold text-blue-700">{{ formatCurrency(totalAmount) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Summary Card -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Summary</h2>
            <div class="space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Total Items</span>
                <span class="text-sm font-bold text-gray-800">{{ goodReceive.items?.length || 0 }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Total Qty Received</span>
                <span class="text-sm font-bold text-gray-800">{{ totalQtyReceived }}</span>
              </div>
              <div class="border-t border-gray-200 pt-3">
                <div class="flex justify-between items-center">
                  <span class="text-sm font-medium text-gray-700">Grand Total</span>
                  <span class="text-lg font-bold text-blue-700">{{ formatCurrency(totalAmount) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Status Info -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Status Info</h2>
            <div class="space-y-3">
              <div class="flex items-center gap-2">
                <i
                  class="fa-solid fa-circle text-xs"
                  :class="goodReceive.status === 'completed' ? 'text-green-500' : 'text-gray-400'"
                ></i>
                <span class="text-sm text-gray-700">
                  {{ goodReceive.status === 'completed' ? 'This GR has been completed and inventory updated.' : 'This GR is still in draft status.' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  goodReceive: Object,
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount || 0);
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

const formatDateTime = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

const totalAmount = computed(() => {
  if (!props.goodReceive.items) return 0;
  return props.goodReceive.items.reduce((sum, item) => sum + parseFloat(item.total || 0), 0);
});

const totalQtyReceived = computed(() => {
  if (!props.goodReceive.items) return 0;
  return props.goodReceive.items.reduce((sum, item) => sum + parseFloat(item.qty_received || 0), 0);
});

function handlePrint() {
  window.print();
}

async function handleDelete() {
  const result = await Swal.fire({
    title: 'Delete Good Receive?',
    text: `Are you sure you want to delete ${props.goodReceive.gr_number}? This action cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Delete',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
  });

  if (result.isConfirmed) {
    router.delete(`/asset-good-receives/${props.goodReceive.id}`, {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Deleted!',
          text: 'Good Receive has been deleted.',
          timer: 2000,
          showConfirmButton: false,
        });
      },
      onError: () => {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete Good Receive.' });
      },
    });
  }
}
</script>
