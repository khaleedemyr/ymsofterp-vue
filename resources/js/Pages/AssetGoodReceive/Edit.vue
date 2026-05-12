<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-pen-to-square text-blue-500"></i> Edit Asset Good Receive
        </h1>
        <a
          href="/asset-good-receives"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition"
        >
          <i class="fa-solid fa-arrow-left mr-2"></i> Back to List
        </a>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Form -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Outlet & Warehouse (Readonly) -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa-solid fa-store mr-2 text-blue-500"></i> Outlet & Warehouse
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                <input
                  type="text"
                  :value="goodReceive.outlet?.nama_outlet || '-'"
                  readonly
                  class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                <input
                  type="text"
                  :value="goodReceive.warehouse_outlet?.name || '-'"
                  readonly
                  class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700"
                />
              </div>
            </div>
          </div>

          <!-- PO Info (Readonly) -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa-solid fa-file-invoice mr-2 text-indigo-500"></i> PO Information
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 p-4 bg-blue-50 rounded-lg">
              <div>
                <span class="text-xs font-medium text-gray-500 uppercase">PO Number</span>
                <p class="text-sm font-semibold text-gray-900">{{ goodReceive.po?.number || '-' }}</p>
              </div>
              <div>
                <span class="text-xs font-medium text-gray-500 uppercase">Supplier</span>
                <p class="text-sm font-semibold text-gray-900">{{ goodReceive.po?.supplier?.name || '-' }}</p>
              </div>
              <div>
                <span class="text-xs font-medium text-gray-500 uppercase">PO Date</span>
                <p class="text-sm font-semibold text-gray-900">{{ goodReceive.po?.date || '-' }}</p>
              </div>
              <div>
                <span class="text-xs font-medium text-gray-500 uppercase">GR Number</span>
                <p class="text-sm font-semibold text-gray-900">{{ goodReceive.gr_number }}</p>
              </div>
            </div>

            <!-- Items Table -->
            <div class="overflow-x-auto">
              <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Item Name</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase">Unit</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Qty Ordered</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Qty Received</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Price</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Total</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-800">{{ item.item_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-center">{{ item.unit }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ item.qty_ordered }}</td>
                    <td class="px-4 py-3 text-right">
                      <input
                        v-model.number="item.qty_received"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-24 px-2 py-1.5 border border-gray-300 rounded-lg text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      />
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ formatCurrency(item.price) }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 text-right">
                      {{ formatCurrency(item.qty_received * item.price) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-gray-50">
                  <tr>
                    <td colspan="5" class="px-4 py-3 text-right text-sm font-bold text-gray-700">Grand Total</td>
                    <td class="px-4 py-3 text-right text-sm font-bold text-blue-700">{{ formatCurrency(grandTotal) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Notes -->
            <div class="mt-6">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <i class="fa-solid fa-comment mr-1"></i> Notes (Optional)
              </label>
              <textarea
                v-model="form.notes"
                rows="3"
                placeholder="Enter additional notes..."
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
              ></textarea>
            </div>

            <!-- Submit -->
            <div class="mt-6 flex justify-end gap-3">
              <a
                href="/asset-good-receives"
                class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition"
              >
                Cancel
              </a>
              <button
                @click="submitForm"
                :disabled="form.processing"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow-lg transition disabled:opacity-50 flex items-center gap-2"
              >
                <i v-if="form.processing" class="fa fa-spinner fa-spin"></i>
                <i v-else class="fa-solid fa-check"></i>
                <span>{{ form.processing ? 'Saving...' : 'Update Good Receive' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Right: Info -->
        <div class="space-y-6">
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa-solid fa-calendar mr-2 text-blue-500"></i> Receive Date
            </h2>
            <input
              v-model="form.receive_date"
              type="date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Status -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Status</h2>
            <div class="flex items-center gap-2">
              <span
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                :class="goodReceive.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
              >
                {{ goodReceive.status.toUpperCase() }}
              </span>
            </div>
            <p class="text-xs text-gray-500 mt-2">Only draft GRs can be edited.</p>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
            <div class="space-y-2">
              <a
                :href="`/asset-good-receives/${goodReceive.id}`"
                class="block w-full text-center px-4 py-2 bg-blue-100 text-blue-800 hover:bg-blue-200 rounded-lg font-semibold transition text-sm"
              >
                <i class="fa-solid fa-eye mr-1"></i> View Detail
              </a>
              <button
                @click="handleDelete"
                class="w-full px-4 py-2 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg font-semibold transition text-sm"
              >
                <i class="fa-solid fa-trash mr-1"></i> Delete GR
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  goodReceive: Object,
  user: Object,
  outlets: Array,
});

const form = useForm({
  po_id: props.goodReceive.po_id,
  outlet_id: props.goodReceive.outlet_id,
  warehouse_outlet_id: props.goodReceive.warehouse_outlet_id,
  receive_date: props.goodReceive.receive_date,
  notes: props.goodReceive.notes || '',
  items: (props.goodReceive.items || []).map(item => ({
    id: item.id,
    po_item_id: item.po_item_id,
    item_id: item.item_id,
    item_name: item.item_name || item.po_item?.item_name || '',
    unit: item.unit_name || item.po_item?.unit || '',
    unit_id: item.unit_id,
    qty_ordered: parseFloat(item.qty_ordered) || 0,
    qty_received: parseFloat(item.qty_received) || 0,
    price: parseFloat(item.price) || 0,
  })),
});

const grandTotal = computed(() => {
  return form.items.reduce((sum, item) => sum + (item.qty_received * item.price), 0);
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount || 0);
};

function submitForm() {
  const hasQty = form.items.some(item => item.qty_received > 0);
  if (!hasQty) {
    Swal.fire({ icon: 'error', title: 'Validation Error', text: 'At least one item must have quantity received.' });
    return;
  }

  form.put(`/asset-good-receives/${props.goodReceive.id}`, {
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Updated!',
        text: 'Asset Good Receive updated successfully.',
        timer: 2000,
        showConfirmButton: false,
      });
    },
    onError: (errors) => {
      const msg = Object.values(errors).flat().join('\n') || 'Failed to update Good Receive.';
      Swal.fire({ icon: 'error', title: 'Error', text: msg });
    },
  });
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
